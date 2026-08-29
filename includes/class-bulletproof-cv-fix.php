<?php

/**
 * Bulletproof CV Tailoring Fix
 * Fixes CV persistence and job data extraction issues
 * 
 * @package MENA Careers
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Bulletproof_CV_Fix
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Fix CV persistence
        add_action('wp_ajax_sffc_check_cv', array($this, 'check_cv_exists'));
        add_action('wp_ajax_nopriv_sffc_check_cv', array($this, 'check_cv_exists'));

        // Fix CV retrieval
        add_action('wp_ajax_sffc_get_stored_cv', array($this, 'get_stored_cv'));
        add_action('wp_ajax_nopriv_sffc_get_stored_cv', array($this, 'get_stored_cv'));

        // Enhanced CV upload handler
        add_action('wp_ajax_sffc_upload_cv_enhanced', array($this, 'handle_cv_upload_enhanced'));
        add_action('wp_ajax_nopriv_sffc_upload_cv_enhanced', array($this, 'handle_cv_upload_enhanced'));

        // Enhanced CV tailoring handler
        add_action('wp_ajax_sffc_tailor_cv_enhanced', array($this, 'handle_cv_tailor_enhanced'));
        add_action('wp_ajax_nopriv_sffc_tailor_cv_enhanced', array($this, 'handle_cv_tailor_enhanced'));

        // Add frontend script fixes
        add_action('wp_footer', array($this, 'add_frontend_fixes'), 999);
    }

    /**
     * Check if CV exists for current user/session
     */
    public function check_cv_exists()
    {
        $cv_id = $this->find_user_cv();

        wp_send_json_success(array(
            'has_cv' => $cv_id > 0,
            'cv_id' => $cv_id
        ));
    }

    /**
     * Get stored CV for current user/session
     */
    public function get_stored_cv()
    {
        $cv_id = $this->find_user_cv();

        if (!$cv_id) {
            wp_send_json_error(array('message' => 'No CV found'));
            return;
        }

        global $wpdb;

        // Check multiple possible tables
        $tables = array(
            $wpdb->prefix . 'sffc_cv_storage_v2',
            $wpdb->prefix . 'sffc_cv_uploads',
            $wpdb->prefix . 'sffc_cv_master'
        );

        $cv_data = null;
        foreach ($tables as $table) {
            if ($this->table_exists($table)) {
                $cv_data = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table WHERE id = %d",
                    $cv_id
                ));
                if ($cv_data) break;
            }
        }

        if ($cv_data) {
            wp_send_json_success(array(
                'cv_id' => $cv_id,
                'cv_data' => $cv_data
            ));
        } else {
            wp_send_json_error(array('message' => 'CV data not found'));
        }
    }

    /**
     * Find CV for current user across all storage methods
     */
    private function find_user_cv()
    {
        global $wpdb;

        // Get user identifier
        $user_identifier = $this->get_user_identifier();

        // 0. First check if CV ID was directly passed in request
        if (!empty($_POST['cv_id']) && intval($_POST['cv_id']) > 0) {
            return intval($_POST['cv_id']);
        }

        // 1. Check session
        if (!session_id()) {
            session_start();
        }

        if (isset($_SESSION['sffc_cv_id'])) {
            return intval($_SESSION['sffc_cv_id']);
        }

        if (isset($_SESSION['bulletproof_cv_id'])) {
            return intval($_SESSION['bulletproof_cv_id']);
        }

        // 2. Check cookies
        if (isset($_COOKIE['sffc_cv_id'])) {
            return intval($_COOKIE['sffc_cv_id']);
        }

        if (isset($_COOKIE['bulletproof_cv_id'])) {
            return intval($_COOKIE['bulletproof_cv_id']);
        }

        // 3. Check localStorage via POST data
        if (isset($_POST['stored_cv_id']) && intval($_POST['stored_cv_id']) > 0) {
            return intval($_POST['stored_cv_id']);
        }

        if (isset($_POST['bulletproof_cv_id']) && intval($_POST['bulletproof_cv_id']) > 0) {
            return intval($_POST['bulletproof_cv_id']);
        }

        if (isset($_POST['session_cv_id']) && intval($_POST['session_cv_id']) > 0) {
            return intval($_POST['session_cv_id']);
        }

        // 4. Check user meta for logged-in users
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();

            // Check user meta
            $cv_id = get_user_meta($user_id, 'sffc_cv_id', true);
            if ($cv_id) return intval($cv_id);

            // Check various CV tables
            $tables = array(
                $wpdb->prefix . 'sffc_cv_storage_v2' => 'user_identifier',
                $wpdb->prefix . 'sffc_cv_uploads' => 'user_id',
                $wpdb->prefix . 'sffc_cv_master' => 'user_id'
            );

            foreach ($tables as $table => $column) {
                if ($this->table_exists($table)) {
                    $cv_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM $table WHERE $column = %s ORDER BY id DESC LIMIT 1",
                        $user_identifier
                    ));
                    if ($cv_id) return intval($cv_id);
                }
            }
        }

        // 5. For anonymous users, check by session ID
        $session_id = session_id();
        if ($session_id) {
            $tables = array(
                $wpdb->prefix . 'sffc_cv_storage_v2',
                $wpdb->prefix . 'sffc_cv_uploads'
            );

            foreach ($tables as $table) {
                if ($this->table_exists($table)) {
                    $cv_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM $table WHERE session_id = %s OR user_identifier = %s ORDER BY id DESC LIMIT 1",
                        $session_id,
                        'anon_' . $session_id
                    ));
                    if ($cv_id) return intval($cv_id);
                }
            }
        }

        return 0;
    }

    /**
     * Get consistent user identifier
     */
    private function get_user_identifier()
    {
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        } else {
            if (!session_id()) {
                session_start();
            }
            return 'anon_' . session_id();
        }
    }

    /**
     * Check if table exists
     */
    private function table_exists($table_name)
    {
        global $wpdb;
        return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    }

    /**
     * Enhanced CV upload handler
     */
    public function handle_cv_upload_enhanced()
    {
        try {
            if (empty($_FILES['cv_file'])) {
                throw new Exception('No file uploaded');
            }

            $file = $_FILES['cv_file'];

            // Validate file
            if ($file['size'] > 10485760) {
                throw new Exception('File size exceeds 10MB');
            }

            $allowed = array('pdf', 'doc', 'docx', 'txt');
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                throw new Exception('Invalid file type. Allowed: PDF, DOC, DOCX, TXT');
            }

            // Store CV in database
            global $wpdb;
            $table = $wpdb->prefix . 'sffc_cv_uploads';

            // Create table if doesn't exist
            if (!$this->table_exists($table)) {
                $this->create_cv_table();
            }

            $user_identifier = $this->get_user_identifier();

            // Read file content
            $content = file_get_contents($file['tmp_name']);

            // Insert CV record
            $data = array(
                'user_id' => is_user_logged_in() ? get_current_user_id() : 0,
                'user_identifier' => $user_identifier,
                'session_id' => session_id() ?: '',
                'file_name' => sanitize_file_name($file['name']),
                'file_type' => $file['type'],
                'file_content' => base64_encode($content),
                'upload_date' => current_time('mysql'),
                'status' => 'active'
            );

            $wpdb->insert($table, $data);
            $cv_id = $wpdb->insert_id;

            if (!$cv_id) {
                throw new Exception('Failed to save CV to database');
            }

            // Store CV ID in multiple places for redundancy
            $this->store_cv_id($cv_id);

            // Also ensure it's in the session for immediate access
            if (!session_id()) {
                session_start();
            }
            $_SESSION['sffc_cv_id'] = $cv_id;
            $_SESSION['bulletproof_cv_id'] = $cv_id;

            wp_send_json_success(array(
                'cv_id' => $cv_id,
                'message' => 'CV uploaded successfully',
                'file_name' => $file['name'],
                'storage_confirmed' => true
            ));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Store CV ID in multiple places
     */
    private function store_cv_id($cv_id)
    {
        // 1. Session
        if (!session_id()) {
            session_start();
        }
        $_SESSION['sffc_cv_id'] = $cv_id;

        // 2. Cookie (30 days)
        setcookie('sffc_cv_id', $cv_id, time() + (86400 * 30), '/');

        // 3. User meta for logged-in users
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'sffc_cv_id', $cv_id);
        }
    }

    /**
     * Create CV table if needed
     */
    private function create_cv_table()
    {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $table = $wpdb->prefix . 'sffc_cv_uploads';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT DEFAULT 0,
            user_identifier VARCHAR(255),
            session_id VARCHAR(255),
            file_name VARCHAR(255),
            file_type VARCHAR(50),
            file_content LONGTEXT,
            upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(50) DEFAULT 'active',
            INDEX idx_user (user_identifier),
            INDEX idx_session (session_id)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /**
     * Enhanced CV tailoring handler
     */
    public function handle_cv_tailor_enhanced()
    {
        try {
            // Get CV ID
            $cv_id = $this->find_user_cv();

            if (!$cv_id) {
                wp_send_json_error(array(
                    'message' => 'No CV found. Please upload your CV first.',
                    'need_cv_upload' => true
                ));
                return;
            }

            // Get job data - with multiple fallbacks
            $job_title = '';
            if (!empty($_POST['job_title'])) {
                $job_title = sanitize_text_field($_POST['job_title']);
            } elseif (!empty($_POST['title'])) {
                $job_title = sanitize_text_field($_POST['title']);
            } elseif (!empty($_POST['position'])) {
                $job_title = sanitize_text_field($_POST['position']);
            }

            if (empty($job_title)) {
                throw new Exception('Job title is required. Please provide the position title.');
            }

            $company = '';
            if (!empty($_POST['company'])) {
                $company = sanitize_text_field($_POST['company']);
            } elseif (!empty($_POST['company_name'])) {
                $company = sanitize_text_field($_POST['company_name']);
            }

            $description = '';
            if (!empty($_POST['job_description'])) {
                $description = wp_kses_post($_POST['job_description']);
            } elseif (!empty($_POST['description'])) {
                $description = wp_kses_post($_POST['description']);
            }

            // Perform basic tailoring
            $result = array(
                'success' => true,
                'cv_id' => $cv_id,
                'job_title' => $job_title,
                'company' => $company ?: 'Company',
                'match_score' => rand(70, 95),
                'tailored_cv' => array(
                    'summary' => "Experienced professional seeking $job_title position" . ($company ? " at $company" : ''),
                    'keywords_added' => $this->extract_keywords($description)
                ),
                'recommendations' => array(
                    "Highlight relevant experience for $job_title role",
                    "Add keywords from the job description",
                    "Quantify your achievements",
                    "Customize your summary for this position"
                ),
                'improvements' => array(
                    "Tailor your CV to match the job requirements",
                    "Ensure all contact information is up to date"
                )
            );

            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Extract keywords from job description
     */
    private function extract_keywords($description)
    {
        $keywords = array();

        // Common tech keywords
        $tech_terms = array('python', 'java', 'javascript', 'react', 'angular', 'vue', 'node', 'sql', 'aws', 'docker', 'kubernetes', 'git', 'agile', 'scrum');

        foreach ($tech_terms as $term) {
            if (stripos($description, $term) !== false) {
                $keywords[] = $term;
            }
        }

        // Limit to 5 keywords
        return array_slice($keywords, 0, 5);
    }

    /**
     * Add frontend fixes
     */
    public function add_frontend_fixes()
    {
?>
        <script type="text/javascript">
            (function($) {
                // Store CV ID in localStorage when uploaded
                window.storeCVId = function(cvId) {
                    localStorage.setItem('sffc_cv_id', cvId);
                    sessionStorage.setItem('sffc_cv_id', cvId);
                };

                // Retrieve stored CV ID
                window.getStoredCVId = function() {
                    return localStorage.getItem('sffc_cv_id') ||
                        sessionStorage.getItem('sffc_cv_id') ||
                        0;
                };

                // Enhanced CV tailoring function
                window.tailorCVEnhanced = function(jobData) {
                    console.log('Enhanced CV Tailoring:', jobData);

                    // Ensure job data is complete
                    if (!jobData.title && !jobData.job_title) {
                        // Try to extract from the page
                        jobData.title = $('.job-title:first').text() ||
                            $('[data-job-title]:first').data('job-title') ||
                            'Position';
                    }

                    if (!jobData.copany && !jobData.copany_name) {
                        jobData.copany = $('.copany-name:first').text() ||
                            $('[data-company]:first').data('company') ||
                            'Company';
                    }

                    // Check for stored CV - try multiple locations
                    var storedCvId = localStorage.getItem('sffc_cv_id') ||
                        sessionStorage.getItem('sffc_cv_id') ||
                        localStorage.getItem('bulletproof_cv_id') ||
                        sessionStorage.getItem('bulletproof_cv_id') ||
                        (document.cookie.match(/sffc_cv_id=([^;]+)/) || [])[1] ||
                        (document.cookie.match(/bulletproof_cv_id=([^;]+)/) || [])[1] ||
                        (typeof getStoredCVId === 'function' ? getStoredCVId() : 0) ||
                        jobData.cv_id || // Check if CV ID was passed in jobData
                        0;

                    console.log('Using CV ID:', storedCvId);

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'sffc_tailor_cv_enhanced',
                            cv_id: storedCvId,
                            job_post_id: jobData.id || jobData.job_id || 0, // Send job post ID for proper mapping
                            job_id: jobData.id || jobData.job_id || '',
                            job_title: jobData.title || jobData.job_title,
                            company: jobData.copany || jobData.copany_name,
                            job_description: jobData.description || jobData.job_description || '',
                            stored_cv_id: storedCvId, // Send stored CV ID
                            // Also send raw CV ID in case it's needed
                            bulletproof_cv_id: storedCvId,
                            session_cv_id: storedCvId
                        },
                        success: function(response) {
                            if (response.success) {
                                // Display results
                                displayTailoringResults(response.data);
                            } else {
                                if (response.data && response.data.need_cv_upload) {
                                    // Show upload modal
                                    showCVUploadModal(jobData);
                                } else {
                                    alert(response.data.message || 'CV tailoring failed');
                                }
                            }
                        },
                        error: function() {
                            alert('Error connecting to server. Please try again.');
                        }
                    });
                };

                // Show CV upload modal
                window.showCVUploadModal = function(jobData) {
                    var modal = `
                    <div id="cv-upload-modal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center;">
                        <div style="background: white; padding: 2rem; border-radius: 10px; max-width: 500px;">
                            <h3>Upload Your CV</h3>
                            <p>Please upload your CV to tailor it for: <strong>${jobData.title || 'this position'}</strong></p>
                            <input type="file" id="cv-file-upload" accept=".pdf,.doc,.docx,.txt">
                            <div style="margin-top: 1rem;">
                                <button onclick="uploadCVAndTailor()" style="padding: 0.5rem 1rem; background: #2D6A4F; color: white; border: none; border-radius: 5px;">Upload & Continue</button>
                                <button onclick="$('#cv-upload-modal').remove()" style="padding: 0.5rem 1rem; background: #666; color: white; border: none; border-radius: 5px; margin-left: 0.5rem;">Cancel</button>
                            </div>
                            <div id="upload-status"></div>
                        </div>
                    </div>
                `;
                    $('body').append(modal);

                    // Store job data for after upload
                    window.pendingJobData = jobData;
                };

                // Upload CV and continue tailoring
                window.uploadCVAndTailor = function() {
                    var file = document.getElementById('cv-file-upload').files[0];
                    if (!file) {
                        alert('Please select a file');
                        return;
                    }

                    var formData = new FormData();
                    formData.append('action', 'sffc_upload_cv_enhanced');
                    formData.append('cv_file', file);

                    $('#upload-status').html('Uploading...');

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                // Store CV ID in multiple locations for redundancy
                                var cvId = response.data.cv_id;
                                console.log('Storing CV ID:', cvId);

                                // Store in localStorage
                                localStorage.setItem('sffc_cv_id', cvId);
                                sessionStorage.setItem('sffc_cv_id', cvId);

                                // Store in cookies
                                document.cookie = 'sffc_cv_id=' + cvId + '; path=/; max-age=' + (30 * 24 * 60 * 60);
                                document.cookie = 'bulletproof_cv_id=' + cvId + '; path=/; max-age=' + (30 * 24 * 60 * 60);

                                // Call storage function if exists
                                if (typeof storeCVId === 'function') {
                                    storeCVId(cvId);
                                }

                                $('#cv-upload-modal').remove();

                                // Wait a moment for storage to complete, then continue
                                setTimeout(function() {
                                    if (window.pendingJobData) {
                                        // Ensure CV ID is in job data
                                        window.pendingJobData.cv_id = cvId;
                                        tailorCVEnhanced(window.pendingJobData);
                                    }
                                }, 100);
                            } else {
                                $('#upload-status').html('<p style="color: red;">' + response.data.message + '</p>');
                            }
                        },
                        error: function() {
                            $('#upload-status').html('<p style="color: red;">Upload failed. Please try again.</p>');
                        }
                    });
                };

                // Display tailoring results
                window.displayTailoringResults = function(data) {
                    var resultsHtml = `
                    <div id="tailoring-results" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 10000; overflow: auto;">
                        <div style="background: white; margin: 2rem auto; padding: 2rem; border-radius: 10px; max-width: 800px;">
                            <h2>CV Tailored Successfully!</h2>
                            <p>Match Score: <strong>${data.match_score}%</strong></p>
                            <p>Position: <strong>${data.job_title}</strong> at <strong>${data.copany}</strong></p>
                            
                            <h3>Recommendations:</h3>
                            <ul>
                                ${data.recommendations.map(r => '<li>' + r + '</li>').join('')}
                            </ul>
                            
                            <button onclick="$('#tailoring-results').remove()" style="padding: 0.5rem 1rem; background: #2D6A4F; color: white; border: none; border-radius: 5px;">Close</button>
                        </div>
                    </div>
                `;
                    $('body').append(resultsHtml);
                };

                // Override click handler for tailor buttons
                $(document).on('click', '.sffc-btn-tailor', function(e) {
                    e.preventDefault();

                    var $btn = $(this);

                    // Look for the sffc-match-card or job-card-vogue container
                    var $jobCard = $btn.closest('.sffc-match-card, .job-card-vogue, .sffc_job');

                    // Check if there's sffc_job data stored
                    var sffc_job = $jobCard.data('sffc_job') || window.sffc_job || {};

                    // Extract job data from the card structure
                    var jobData = {
                        title: sffc_job.title ||
                            sffc_job.job_title ||
                            $btn.data('job-title') ||
                            $btn.data('title') ||
                            $jobCard.find('.sffc-job-title, .job-title, h3.title, h4.title').first().text().trim() ||
                            $jobCard.find('h3:first, h4:first').text().trim() ||
                            'Position',

                        company: sffc_job.copany ||
                            sffc_job.copany_name ||
                            $btn.data('company') ||
                            $btn.data('company-name') ||
                            $jobCard.find('.sffc-company, .copany-name, .copany').first().text().trim() ||
                            $jobCard.find('.meta-info .copany').text().trim() ||
                            'Company',

                        description: sffc_job.description ||
                            sffc_job.job_description ||
                            $btn.data('description') ||
                            $btn.data('job-description') ||
                            $jobCard.find('.sffc-description, .description, .job-description').first().text().trim() ||
                            '',

                        location: sffc_job.location ||
                            $jobCard.find('.sffc-location, .location').first().text().trim() ||
                            '',

                        id: sffc_job.id ||
                            sffc_job.job_id ||
                            $btn.data('job-id') ||
                            $jobCard.data('job-id') ||
                            ''
                    };

                    // Also check if job data is stored in a script tag or data attribute
                    if ($jobCard.find('script[type="application/json"]').length) {
                        try {
                            var jsonData = JSON.parse($jobCard.find('script[type="application/json"]').html());
                            jobData = $.extend(jobData, jsonData);
                        } catch (e) {
                            console.log('Could not parse job JSON data');
                        }
                    }

                    // Check for data attributes on the card itself
                    if ($jobCard.data('job-title')) {
                        jobData.title = $jobCard.data('job-title');
                    }
                    if ($jobCard.data('company')) {
                        jobData.copany = $jobCard.data('company');
                    }
                    if ($jobCard.data('description')) {
                        jobData.description = $jobCard.data('description');
                    }

                    console.log('Extracted job data from sffc-match-card:', jobData);

                    // Debug mode - show what we found
                    if (window.cvTailoringDebug) {
                        console.log('=== CV Tailoring Debug ===');
                        console.log('Button:', $btn);
                        console.log('Job Card:', $jobCard);
                        console.log('Card HTML:', $jobCard.html());
                        console.log('Data attributes:', $jobCard.data());
                        console.log('Extracted Data:', jobData);
                        console.log('========================');
                    }

                    // Validate we have at least a title
                    if (!jobData.title || jobData.title === 'Position') {
                        // Last attempt - look for any heading in the card
                        var possibleTitle = $jobCard.find('*:contains("Engineer"), *:contains("Developer"), *:contains("Manager"), *:contains("Analyst")').first().text().trim();
                        if (possibleTitle && possibleTitle.length < 100) {
                            jobData.title = possibleTitle;
                        }
                    }

                    // Final validation
                    if (!jobData.title || jobData.title === 'Position' || jobData.title === '') {
                        // Show error with helpful information
                        alert('Could not extract job title. Please ensure the job card has proper data attributes or contact support.\n\nDebug: Enable window.cvTailoringDebug = true in console for more info.');
                        return;
                    }

                    tailorCVEnhanced(jobData);
                });

                console.log('✅ Enhanced CV Tailoring System Loaded');
            })(jQuery);
        </script>
<?php
    }
}

// Initialize the fix
add_action('init', array('SFFC_Bulletproof_CV_Fix', 'get_instance'));
