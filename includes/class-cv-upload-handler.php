<?php

/**
 * CV Upload Handler for senna AutoFill Assistant
 * 
 * Handles CV uploads, parsing, and data extraction for autofill functionality
 * 
 * @package MENA Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CV_Upload_Handler
{

    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Database table names
     */
    private $table_cv_uploads;
    private $table_parsed_profiles;
    private $table_autofill_tokens;
    private $table_platform_patterns;
    private $table_autofill_applications;

    /**
     * Maximum file size in bytes (10MB)
     */
    private $max_file_size = 10485760;

    /**
     * Allowed file types
     */
    private $allowed_types = ['pdf', 'doc', 'docx'];

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;

        // Set table names
        $this->table_cv_uploads = $wpdb->prefix . 'sffc_cv_uploads';
        $this->table_parsed_profiles = $wpdb->prefix . 'sffc_parsed_profiles';
        $this->table_autofill_tokens = $wpdb->prefix . 'sffc_autofill_tokens';
        $this->table_platform_patterns = $wpdb->prefix . 'sffc_platform_patterns';
        $this->table_autofill_applications = $wpdb->prefix . 'sffc_autofill_applications';

        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Get instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // AJAX handlers for logged-in users
        add_action('wp_ajax_sffc_upload_cv', [$this, 'ajax_upload_cv']);
        add_action('wp_ajax_sffc_parse_cv', [$this, 'ajax_parse_cv']);
        add_action('wp_ajax_sffc_get_parsed_profile', [$this, 'ajax_get_parsed_profile']);
        add_action('wp_ajax_sffc_get_parse_status', [$this, 'ajax_get_parse_status']);
        add_action('wp_ajax_sffc_save_parsed_profile', [$this, 'ajax_save_parsed_profile']);
        add_action('wp_ajax_sffc_generate_autofill_token', [$this, 'ajax_generate_token']);

        // AJAX handlers for non-logged-in users
        add_action('wp_ajax_nopriv_sffc_upload_cv', [$this, 'ajax_upload_cv']);
        add_action('wp_ajax_nopriv_sffc_parse_cv', [$this, 'ajax_parse_cv']);
        add_action('wp_ajax_nopriv_sffc_get_parsed_profile', [$this, 'ajax_get_parsed_profile']);
        add_action('wp_ajax_nopriv_sffc_get_parse_status', [$this, 'ajax_get_parse_status']);
        add_action('wp_ajax_nopriv_sffc_save_parsed_profile', [$this, 'ajax_save_parsed_profile']);
        add_action('wp_ajax_nopriv_sffc_generate_autofill_token', [$this, 'ajax_generate_token']);

        // Database initialization
        add_action('init', [$this, 'maybe_create_tables']);
    }

    /**
     * Create database tables if they don't exist
     */
    public function maybe_create_tables()
    {
        // Don't try to create tables during AJAX requests
        if (wp_doing_ajax()) {
            return;
        }

        // Check if tables need to be created
        if (get_option('sffc_cv_tables_created') === '1.0.0') {
            return;
        }

        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        // Table 1: CV Uploads
        $sql1 = "CREATE TABLE IF NOT EXISTS {$this->table_cv_uploads} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(100) NOT NULL,
            file_name VARCHAR(255),
            file_path VARCHAR(500),
            file_type VARCHAR(50),
            file_size INT,
            upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            parse_status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
            error_message TEXT,
            INDEX idx_user_id (user_id),
            INDEX idx_status (parse_status)
        ) $charset_collate;";

        // Table 2: Parsed Profiles
        $sql2 = "CREATE TABLE IF NOT EXISTS {$this->table_parsed_profiles} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(100) NOT NULL UNIQUE,
            cv_upload_id INT,
            parsed_data LONGTEXT,
            platform_formats LONGTEXT,
            extraction_confidence DECIMAL(3,2),
            last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            version INT DEFAULT 1,
            INDEX idx_user_id (user_id)
        ) $charset_collate;";

        // Table 3: AutoFill Tokens
        $sql3 = "CREATE TABLE IF NOT EXISTS {$this->table_autofill_tokens} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(100) NOT NULL,
            token VARCHAR(64) UNIQUE,
            device_id VARCHAR(255),
            device_name VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME,
            last_used DATETIME,
            is_active BOOLEAN DEFAULT TRUE,
            INDEX idx_user_token (user_id, token),
            INDEX idx_expires (expires_at)
        ) $charset_collate;";

        // Table 4: Platform Patterns
        $sql4 = "CREATE TABLE IF NOT EXISTS {$this->table_platform_patterns} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            platform_name VARCHAR(100),
            url_pattern VARCHAR(500),
            dom_selectors LONGTEXT,
            field_mappings LONGTEXT,
            priority INT DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            last_verified DATETIME,
            success_rate DECIMAL(5,2),
            INDEX idx_platform (platform_name),
            INDEX idx_active (is_active)
        ) $charset_collate;";

        // Table 5: Application Tracking
        $sql5 = "CREATE TABLE IF NOT EXISTS {$this->table_autofill_applications} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(100) NOT NULL,
            platform VARCHAR(100),
            company_name VARCHAR(255),
            position_title VARCHAR(255),
            application_url TEXT,
            autofill_success BOOLEAN,
            fields_filled INT,
            fields_failed INT,
            error_log LONGTEXT,
            time_spent INT,
            applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_apps (user_id),
            INDEX idx_platform (platform),
            INDEX idx_date (applied_at)
        ) $charset_collate;";

        // Execute table creation
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);
        dbDelta($sql5);

        // Insert default platform patterns
        $this->insert_default_platform_patterns();

        // Mark tables as created
        update_option('sffc_cv_tables_created', '1.0.0');

        // Log table creation
        error_log('senna CV tables created successfully');
    }

    /**
     * Insert default platform patterns
     */
    private function insert_default_platform_patterns()
    {
        global $wpdb;

        $platforms = [
            [
                'platform_name' => 'workday',
                'url_pattern' => '%.myworkdayjobs.co%',
                'dom_selectors' => json_encode([
                    'name' => ['#legalName', '[name="legalName"]', '.legal-name-field'],
                    'email' => ['#email', '[type="email"]', '.email-field'],
                    'phone' => ['#phone', '[type="tel"]', '.phone-field']
                ]),
                'field_mappings' => json_encode([
                    'full_name' => 'legalName',
                    'email' => 'email',
                    'phone' => 'phone'
                ]),
                'priority' => 10,
                'is_active' => 1
            ],
            [
                'platform_name' => 'greenhouse',
                'url_pattern' => '%greenhouse.io%',
                'dom_selectors' => json_encode([
                    'name' => ['#first_name', '#last_name', '[name*="name"]'],
                    'email' => ['#email', '[name="email"]'],
                    'phone' => ['#phone', '[name="phone"]']
                ]),
                'field_mappings' => json_encode([
                    'first_name' => 'first_name',
                    'last_name' => 'last_name',
                    'email' => 'email'
                ]),
                'priority' => 9,
                'is_active' => 1
            ],
            [
                'platform_name' => 'lever',
                'url_pattern' => '%lever.co%',
                'dom_selectors' => json_encode([
                    'name' => ['[name="name"]', '.application-name'],
                    'email' => ['[name="email"]', '.application-email'],
                    'phone' => ['[name="phone"]', '.application-phone']
                ]),
                'field_mappings' => json_encode([
                    'full_name' => 'name',
                    'email' => 'email',
                    'phone' => 'phone'
                ]),
                'priority' => 8,
                'is_active' => 1
            ]
        ];

        foreach ($platforms as $platform) {
            $wpdb->insert($this->table_platform_patterns, $platform);
        }
    }

    /**
     * AJAX handler for CV upload
     */
    public function ajax_upload_cv()
    {
        // Verify nonce - check multiple possible nonce names for maximum compatibility
        $nonce_valid = false;
        $nonce_to_check = null;

        // Get the nonce from various possible fields
        if (isset($_POST['nonce'])) {
            $nonce_to_check = $_POST['nonce'];
        } else if (isset($_POST['_ajax_nonce'])) {
            $nonce_to_check = $_POST['_ajax_nonce'];
        } else if (isset($_POST['security'])) {
            $nonce_to_check = $_POST['security'];
        }

        // Check against all possible nonce actions
        if ($nonce_to_check) {
            $nonce_valid = wp_verify_nonce($nonce_to_check, 'sffc_public_nonce') ||  // Main frontend nonce
                wp_verify_nonce($nonce_to_check, 'sffc_cv_upload') ||      // CV upload specific
                wp_verify_nonce($nonce_to_check, 'sffc_ajax_nonce') ||     // Generic AJAX nonce
                wp_verify_nonce($nonce_to_check, 'cv_tailoring_nonce');    // CV tailoring nonce
        }

        if (!$nonce_valid) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        // Check file upload
        if (empty($_FILES['cv_file'])) {
            wp_send_json_error(['message' => 'No file uploaded']);
        }

        $file = $_FILES['cv_file'];

        // Get user ID or use session for anonymous users
        $user_id = 0;
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
        } else {
            // For anonymous users, use session ID
            if (!session_id()) {
                session_start();
            }
            $user_id = 'anon_' . session_id();
        }

        // Validate file
        $validation = $this->validate_file($file);
        if (!$validation['valid']) {
            wp_send_json_error(['message' => $validation['error']]);
        }

        // Upload file
        $upload = $this->upload_file($file, $user_id);
        if (!$upload['success']) {
            wp_send_json_error(['message' => $upload['error']]);
        }

        // Save to database
        global $wpdb;
        $insert = $wpdb->insert(
            $this->table_cv_uploads,
            [
                'user_id' => $user_id,
                'file_name' => sanitize_file_name($file['name']),
                'file_path' => $upload['path'],
                'file_type' => $validation['type'],
                'file_size' => $file['size'],
                'parse_status' => 'pending'
            ]
        );

        if (!$insert) {
            wp_send_json_error(['message' => 'Failed to save upload record']);
        }

        $upload_id = $wpdb->insert_id;

        // Trigger parsing
        $this->trigger_parsing($upload_id);

        wp_send_json_success([
            'upload_id' => $upload_id,
            'cv_id' => $upload_id, // Add cv_id for compatibility with CV tailoring
            'message' => 'CV uploaded successfully. Processing...',
            'file_name' => sanitize_file_name($file['name'])
        ]);
    }

    /**
     * Validate uploaded file
     */
    private function validate_file($file)
    {
        // Check file size
        if ($file['size'] > $this->max_file_size) {
            return ['valid' => false, 'error' => 'File size exceeds 10MB limit'];
        }

        // Check file type
        $file_type = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_type, $this->allowed_types)) {
            return ['valid' => false, 'error' => 'Invalid file type. Please upload PDF, DOC, or DOCX'];
        }

        // Check for errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Upload failed. Please try again'];
        }

        return ['valid' => true, 'type' => $file_type];
    }

    /**
     * Upload file to server
     */
    private function upload_file($file, $user_id)
    {
        $upload_dir = wp_upload_dir();
        $cv_dir = $upload_dir['basedir'] . '/senna-cvs/' . $user_id;

        // Create directory if doesn't exist
        if (!file_exists($cv_dir)) {
            wp_mkdir_p($cv_dir);
        }

        // Generate unique filename
        $filename = time() . '_' . sanitize_file_name($file['name']);
        $filepath = $cv_dir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => false, 'error' => 'Failed to save file'];
        }

        return [
            'success' => true,
            'path' => $filepath,
            'url' => $upload_dir['baseurl'] . '/senna-cvs/' . $user_id . '/' . $filename
        ];
    }

    /**
     * Trigger CV parsing
     */
    private function trigger_parsing($upload_id)
    {
        // Schedule immediate parsing
        wp_schedule_single_event(time(), 'sffc_parse_cv', [$upload_id]);

        // For immediate processing in current request
        $this->parse_cv($upload_id);
    }

    /**
     * Parse CV and extract data
     */
    public function parse_cv($upload_id)
    {
        global $wpdb;

        // Get upload record
        $upload = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_cv_uploads} WHERE id = %d",
            $upload_id
        ));

        if (!$upload) {
            return false;
        }

        // Update status to processing
        $wpdb->update(
            $this->table_cv_uploads,
            ['parse_status' => 'processing'],
            ['id' => $upload_id]
        );

        // Extract text based on file type
        $text = '';
        switch ($upload->file_type) {
            case 'pdf':
                $text = $this->extract_pdf_text($upload->file_path);
                break;
            case 'docx':
                $text = $this->extract_docx_text($upload->file_path);
                break;
            case 'doc':
                $text = $this->extract_doc_text($upload->file_path);
                break;
        }

        if (empty($text)) {
            $wpdb->update(
                $this->table_cv_uploads,
                [
                    'parse_status' => 'failed',
                    'error_message' => 'Failed to extract text from file'
                ],
                ['id' => $upload_id]
            );
            return false;
        }

        // Parse with AI
        $parsed_data = $this->parse_with_ai($text);

        if (!$parsed_data) {
            // Fallback to pattern matching
            $parsed_data = $this->parse_with_patterns($text);
        }

        // Save parsed profile
        $this->save_parsed_profile($upload->user_id, $upload_id, $parsed_data);

        // Update status
        $wpdb->update(
            $this->table_cv_uploads,
            ['parse_status' => 'completed'],
            ['id' => $upload_id]
        );

        // Trigger matching integration after successful parsing
        $this->trigger_matching_integration($upload->user_id, $upload_id, $parsed_data);

        return true;
    }

    /**
     * Save parsed profile
     */
    private function save_parsed_profile($user_id, $upload_id, $parsed_data)
    {
        global $wpdb;

        // Generate platform-specific formats
        $platform_formats = $this->generate_platform_formats($parsed_data);

        // Check if profile exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_parsed_profiles} WHERE user_id = %d",
            $user_id
        ));

        if ($existing) {
            // Update existing
            $wpdb->update(
                $this->table_parsed_profiles,
                [
                    'cv_upload_id' => $upload_id,
                    'parsed_data' => json_encode($parsed_data),
                    'platform_formats' => json_encode($platform_formats),
                    'extraction_confidence' => $parsed_data['confidence'] ?? 0.85,
                    'version' => ['version = version + 1']
                ],
                ['user_id' => $user_id]
            );
        } else {
            // Insert new
            $wpdb->insert(
                $this->table_parsed_profiles,
                [
                    'user_id' => $user_id,
                    'cv_upload_id' => $upload_id,
                    'parsed_data' => json_encode($parsed_data),
                    'platform_formats' => json_encode($platform_formats),
                    'extraction_confidence' => $parsed_data['confidence'] ?? 0.85
                ]
            );
        }
    }

    /**
     * Generate platform-specific formats
     */
    private function generate_platform_formats($parsed_data)
    {
        return [
            'workday' => $this->format_for_workday($parsed_data),
            'greenhouse' => $this->format_for_greenhouse($parsed_data),
            'lever' => $this->format_for_lever($parsed_data),
            'generic' => $this->format_generic($parsed_data)
        ];
    }

    /**
     * Format data for Workday
     */
    private function format_for_workday($data)
    {
        return [
            'legalName' => $data['personal']['full_name'] ?? '',
            'preferredName' => $data['personal']['first_name'] ?? '',
            'email' => $data['personal']['email'] ?? '',
            'phone' => $data['personal']['phone'] ?? '',
            'experience' => array_map(function ($exp) {
                return [
                    'company' => $exp['company'] ?? '',
                    'title' => $exp['title'] ?? '',
                    'startDate' => $this->format_date($exp['start_date'] ?? '', 'MM/YYYY'),
                    'endDate' => $exp['current'] ? 'Present' : $this->format_date($exp['end_date'] ?? '', 'MM/YYYY'),
                    'description' => substr($exp['description'] ?? '', 0, 2000)
                ];
            }, $data['experience'] ?? [])
        ];
    }

    /**
     * Format data for Greenhouse
     */
    private function format_for_greenhouse($data)
    {
        $names = $this->split_name($data['personal']['full_name'] ?? '');
        return [
            'first_name' => $names['first'],
            'last_name' => $names['last'],
            'email' => $data['personal']['email'] ?? '',
            'phone' => $data['personal']['phone'] ?? '',
            'resume_text' => $data['raw_text'] ?? ''
        ];
    }

    /**
     * Format data for Lever
     */
    private function format_for_lever($data)
    {
        return [
            'name' => $data['personal']['full_name'] ?? '',
            'email' => $data['personal']['email'] ?? '',
            'phone' => $data['personal']['phone'] ?? '',
            'resume' => $data['raw_text'] ?? '',
            'linkedin' => $data['personal']['linkedin'] ?? ''
        ];
    }

    /**
     * Generic format
     */
    private function format_generic($data)
    {
        return $data;
    }

    /**
     * Helper: Split name
     */
    private function split_name($full_name)
    {
        $parts = explode(' ', $full_name);
        return [
            'first' => $parts[0] ?? '',
            'last' => implode(' ', array_slice($parts, 1)) ?: ''
        ];
    }

    /**
     * Helper: Format date
     */
    private function format_date($date, $format)
    {
        if (empty($date)) return '';

        $timestamp = strtotime($date);
        if (!$timestamp) return $date;

        switch ($format) {
            case 'MM/YYYY':
                return date('m/Y', $timestamp);
            case 'MM/DD/YYYY':
                return date('m/d/Y', $timestamp);
            case 'YYYY-MM-DD':
                return date('Y-m-d', $timestamp);
            default:
                return $date;
        }
    }

    /**
     * Extract text from PDF file
     */
    private function extract_pdf_text($filepath)
    {
        // Include document parser if not already loaded
        if (!class_exists('SFFC_Document_Parser')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-document-parser.php';
        }

        $parser = SFFC_Document_Parser::get_instance();
        $text = $parser->extract_pdf_text($filepath);

        // Clean the extracted text
        if (!empty($text)) {
            $text = $parser->clean_text($text);
        }

        return $text;
    }

    /**
     * Extract text from DOCX file
     */
    private function extract_docx_text($filepath)
    {
        // Include document parser if not already loaded
        if (!class_exists('SFFC_Document_Parser')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-document-parser.php';
        }

        $parser = SFFC_Document_Parser::get_instance();
        $text = $parser->extract_docx_text($filepath);

        // Clean the extracted text
        if (!empty($text)) {
            $text = $parser->clean_text($text);
        }

        return $text;
    }

    /**
     * Extract text from DOC file
     */
    private function extract_doc_text($filepath)
    {
        // Include document parser if not already loaded
        if (!class_exists('SFFC_Document_Parser')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-document-parser.php';
        }

        $parser = SFFC_Document_Parser::get_instance();
        $text = $parser->extract_doc_text($filepath);

        // Clean the extracted text
        if (!empty($text)) {
            $text = $parser->clean_text($text);
        }

        return $text;
    }

    /**
     * Parse with AI using AI Parser Service
     */
    private function parse_with_ai($text)
    {
        // Include AI parser service if not already loaded
        if (!class_exists('SFFC_AI_Parser_Service')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-ai-parser-service.php';
        }

        $ai_parser = SFFC_AI_Parser_Service::get_instance();
        return $ai_parser->parse_cv_text($text);
    }

    /**
     * Enhanced pattern matching fallback
     */
    private function parse_with_patterns($text)
    {
        // Use the enhanced fallback parsing from AI Parser Service
        if (!class_exists('SFFC_AI_Parser_Service')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-ai-parser-service.php';
        }

        $ai_parser = SFFC_AI_Parser_Service::get_instance();

        // Use reflection to access the private method
        $reflection = new ReflectionClass($ai_parser);
        $method = $reflection->getMethod('get_fallback_parse');
        $method->setAccessible(true);

        return $method->invoke($ai_parser, $text);
    }

    /**
     * Generate autofill token
     */
    public function ajax_generate_token()
    {
        // Verify nonce - check multiple possible nonce names
        $nonce_valid = false;
        if (isset($_POST['nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'sffc_autofill_token') ||
                wp_verify_nonce($_POST['nonce'], 'sffc_ajax_nonce');
        } else if (isset($_POST['_ajax_nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['_ajax_nonce'], 'sffc_autofill_token');
        }

        if (!$nonce_valid) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        // Check user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Please log in to generate token']);
        }

        $user_id = get_current_user_id();
        $device_id = sanitize_text_field($_POST['device_id'] ?? '');
        $device_name = sanitize_text_field($_POST['device_name'] ?? 'Chrome Browser');

        // Generate unique token
        $token = bin2hex(random_bytes(32));

        // Set expiration (30 days)
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));

        // Save to database
        global $wpdb;
        $insert = $wpdb->insert(
            $this->table_autofill_tokens,
            [
                'user_id' => $user_id,
                'token' => $token,
                'device_id' => $device_id,
                'device_name' => $device_name,
                'expires_at' => $expires_at,
                'is_active' => 1
            ]
        );

        if (!$insert) {
            wp_send_json_error(['message' => 'Failed to generate token']);
        }

        wp_send_json_success([
            'token' => $token,
            'expires_at' => $expires_at,
            'message' => 'Token generated successfully'
        ]);
    }

    /**
     * Trigger matching integration after CV upload and parsing
     */
    private function trigger_matching_integration($user_id, $upload_id, $parsed_data)
    {
        try {
            // Prepare CV data for matching processor
            $cv_data = [
                'upload_id' => $upload_id,
                'parsed_data' => $parsed_data,
                'uploaded_at' => current_time('mysql')
            ];

            // Hook for CV uploaded event (used by CV match processor)
            do_action('sffc_cv_uploaded', $user_id, $cv_data);

            // Hook for CV parsed event (triggers automatic matching)
            do_action('sffc_cv_parsed', $user_id, $cv_data);

            error_log("Triggered matching integration for user $user_id after CV upload");
        } catch (Exception $e) {
            error_log("Error triggering matching integration for user $user_id: " . $e->getMessage());
        }
    }
}

// Initialize
SFFC_CV_Upload_Handler::get_instance();
