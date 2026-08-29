<?php

/**
 * Professional CV Tailoring System
 * A complete rewrite with bulletproof implementation
 * 
 * @package MENA Careers
 * @version 5.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Professional_CV_System
{

    private static $instance = null;
    private $claude_api;
    private $upload_dir;
    private $debug = true;
    private $document_parser = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Set upload directory
        $upload_dir = wp_upload_dir();
        $this->upload_dir = $upload_dir['basedir'] . '/cv-tailoring';

        // Create directory if needed
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
        }

        // Initialize AJAX handlers
        add_action('wp_ajax_professional_cv_upload', array($this, 'handle_cv_upload'));
        add_action('wp_ajax_nopriv_professional_cv_upload', array($this, 'handle_cv_upload'));

        add_action('wp_ajax_professional_cv_tailor', array($this, 'handle_cv_tailoring'));
        add_action('wp_ajax_nopriv_professional_cv_tailor', array($this, 'handle_cv_tailoring'));

        add_action('wp_ajax_professional_cv_download', array($this, 'handle_cv_download'));
        add_action('wp_ajax_nopriv_professional_cv_download', array($this, 'handle_cv_download'));

        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    private function get_claude_api()
    {
        if ($this->claude_api instanceof SFFC_Claude_API_Manager) {
            return $this->claude_api;
        }

        if (!class_exists('SFFC_Claude_API_Manager')) {
            $claude_file = plugin_dir_path(__FILE__) . 'class-claude-api-manager.php';
            if (file_exists($claude_file)) {
                require_once $claude_file;
            }
        }

        if (class_exists('SFFC_Claude_API_Manager')) {
            $this->claude_api = SFFC_Claude_API_Manager::get_instance();
        }

        return $this->claude_api;
    }

    /**
     * Flexible nonce verification helper
     */
    private function verify_request_nonce()
    {
        $nonce_valid = false;

        // Check multiple possible nonce field names
        $nonce_fields = array('nonce', 'security', '_ajax_nonce', 'sffc_nonce');
        $nonce = '';

        foreach ($nonce_fields as $field) {
            if (isset($_POST[$field])) {
                $nonce = $_POST[$field];
                break;
            } elseif (isset($_REQUEST[$field])) {
                $nonce = $_REQUEST[$field];
                break;
            }
        }

        // Try to verify with multiple possible nonce names
        $nonce_names = array('sffc_ajax_nonce', 'sffc_cv_upload', 'sffc_public_nonce', 'sffc_ultimate_cv_nonce');

        if (!empty($nonce)) {
            foreach ($nonce_names as $nonce_name) {
                if (wp_verify_nonce($nonce, $nonce_name)) {
                    $nonce_valid = true;
                    break;
                }
            }
        }

        // If still no valid nonce, check if user is logged in
        if (!$nonce_valid && is_user_logged_in()) {
            $nonce_valid = true;
        }

        // Final check
        if (!$nonce_valid && empty($nonce)) {
            throw new Exception('Please refresh the page and try again');
        }

        return $nonce_valid;
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets()
    {
        global $post;

        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $content = (string) ($post->post_content ?? '');
        $should_enqueue = false;
        $shortcodes = array(
            'sffc_application_audit',
            'sffc_audit_button',
            'sffc_crm_reddit_feed',
            'sffc_crm_reddit_dashboard',
            'sffc_crm_reddit_job',
            'sffc_crm_console_search',
            'sffc_profile_builder',
            'sffc_contact_capture',
            'sffc_contact_flow',
            'sffc_dubai_career_flow',
            'sffc_recruiter_intro_onboarding',
            'sffc_recruiter_intro_onboarding_general',
            'sffc_guest_search',
            'sffc_app_pack_preview',
            'sffc_service_preview',
            'career_opportunities',
            'senna_chat'
        );

        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($content, $shortcode)) {
                $should_enqueue = true;
                break;
            }
        }

        if (!$should_enqueue) {
            return;
        }

        wp_enqueue_style(
            'sffc-cv-tailoring-interface',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/cv-tailoring-interface.css',
            array(),
            SFFC_VERSION
        );
    }

    /**
     * Handle CV text/file upload and parsing
     */
    public function handle_cv_upload()
    {
        try {
            // Flexible nonce verification
            $this->verify_request_nonce();

            $cv_text = '';

            // Check if text was pasted
            if (!empty($_POST['cv_text'])) {
                $cv_text = sanitize_textarea_field($_POST['cv_text']);
            }
            // Check if file was uploaded
            elseif (!empty($_FILES['cv_file'])) {
                $cv_text = $this->extract_text_from_file($_FILES['cv_file']);
            }
            // Check if we have stored CV from profile
            elseif (!empty($_POST['use_profile'])) {
                $cv_text = $this->get_profile_cv();
            }

            if (empty($cv_text)) {
                throw new Exception('No CV content provided');
            }

            // Parse CV into structured format
            $parsed_cv = $this->parse_cv_structure($cv_text);

            // Store in session for next step
            $cv_id = $this->store_parsed_cv($parsed_cv, $cv_text);

            wp_send_json_success(array(
                'cv_id' => $cv_id,
                'parsed' => $parsed_cv,
                'message' => 'CV successfully parsed! I found ' . count($parsed_cv['experience']) . ' work experiences and ' . count($parsed_cv['education']) . ' education entries.',
                'preview' => $this->generate_text_preview($parsed_cv),
                'text' => $cv_text,
            ));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Parse CV text into structured format using Intelligence Engine
     * This is the CRITICAL function that must work perfectly
     */
    private function parse_cv_structure($text)
    {
        error_log('[CV Parser] Starting intelligent parse');

        // First save the text to a temp file for Python parser
        $temp_file = tempnam($this->upload_dir, 'cv_') . '.txt';
        file_put_contents($temp_file, $text);

        try {
            // Try Python Intelligence Engine first
            $parsed = $this->call_python_parser($temp_file);

            if ($parsed && !isset($parsed['error'])) {
                error_log('[CV Parser] Python parser succeeded with confidence: ' .
                    ($parsed['metadata']['parse_confidence'] ?? 'unknown'));

                // Convert to our format
                $structure = $this->convert_python_to_structure($parsed);

                // Clean up temp file
                @unlink($temp_file);

                return $structure;
            }

            error_log('[CV Parser] Python parser failed, using PHP fallback');
        } catch (Exception $e) {
            error_log('[CV Parser] Exception in Python parser: ' . $e->getMessage());
        }

        // Clean up temp file
        @unlink($temp_file);

        // Fallback to PHP parser
        $structure = array(
            'contact' => array(
                'name' => '',
                'address' => '',
                'phone' => '',
                'email' => '',
                'linkedin' => ''
            ),
            'summary' => '',
            'experience' => array(),
            'education' => array(),
            'qualifications' => array(),
            'skills' => array(
                'technical' => array(),
                'languages' => array(),
                'interests' => array()
            )
        );

        // Clean the text first
        $text = $this->clean_text($text);
        $lines = explode("\n", $text);

        // Extract contact info (usually at top)
        $structure['contact'] = $this->extract_contact_info($lines);

        // Split into sections
        $sections = $this->split_into_sections($text);

        // Parse each section
        foreach ($sections as $section_name => $section_content) {
            switch ($section_name) {
                case 'experience':
                case 'professional experience':
                case 'work experience':
                    $structure['experience'] = $this->parse_experience_section($section_content);
                    break;

                case 'education':
                    $structure['education'] = $this->parse_education_section($section_content);
                    break;

                case 'summary':
                case 'personal summary':
                case 'profile':
                    $structure['summary'] = $this->clean_summary($section_content);
                    break;

                case 'skills':
                case 'skills & interests':
                case 'technical skills':
                    $structure['skills'] = $this->parse_skills_section($section_content);
                    break;

                case 'qualifications':
                case 'certifications':
                case 'projects':
                    $structure['qualifications'] = $this->parse_qualifications_section($section_content);
                    break;
            }
        }

        // Validate we got the essential parts
        if (empty($structure['experience'])) {
            // Try alternative parsing methods
            $structure['experience'] = $this->fallback_parse_experience($text);
        }

        return $structure;
    }

    /**
     * Extract contact information from CV
     */
    private function extract_contact_info(&$lines)
    {
        $contact = array(
            'name' => '',
            'address' => '',
            'phone' => '',
            'email' => '',
            'linkedin' => ''
        );

        // Name is usually the first non-empty line
        foreach ($lines as $i => $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Check if it's a name (no numbers, no common words)
            if (
                !preg_match('/\d/', $line) &&
                !preg_match('/experience|education|skills|summary/i', $line) &&
                strlen($line) < 50
            ) {
                $contact['name'] = $line;
                unset($lines[$i]);
                break;
            }
        }

        // Extract email
        foreach ($lines as $i => $line) {
            if (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $line, $matches)) {
                $contact['email'] = $matches[1];

                // This line might have other contact info too
                if (preg_match('/(\+?\d[\d\s\-\(\)]+\d)/', $line, $phone_matches)) {
                    $contact['phone'] = $phone_matches[1];
                }
                if (preg_match('/linkedin\.co\/in\/([^\s\/]+)/i', $line, $linkedin_matches)) {
                    $contact['linkedin'] = 'linkedin.com/in/' . $linkedin_matches[1];
                }

                unset($lines[$i]);
                break;
            }
        }

        // Extract phone if not found
        if (empty($contact['phone'])) {
            foreach ($lines as $i => $line) {
                if (preg_match('/(\+?\d[\d\s\-\(\)]{8,})/', $line, $matches)) {
                    $contact['phone'] = $matches[1];
                    unset($lines[$i]);
                    break;
                }
            }
        }

        // Extract address (line with city/state pattern)
        foreach ($lines as $i => $line) {
            if (preg_match('/([A-Za-z\s]+,\s*[A-Z]{2})|([A-Za-z\s]+,\s*[A-Za-z\s]+)/', $line, $matches)) {
                if (!preg_match('/experience|education|skills/i', $line)) {
                    $contact['address'] = trim($line);
                    unset($lines[$i]);
                    break;
                }
            }
        }

        return $contact;
    }

    /**
     * Split CV into major sections
     */
    private function split_into_sections($text)
    {
        $sections = array();

        // Common section headers
        $headers = array(
            'experience' => '/^(professional\s+)?experience|work\s+experience/im',
            'education' => '/^education/im',
            'skills' => '/^(technical\s+)?skills(\s+&\s+interests)?/im',
            'summary' => '/^(personal\s+|professional\s+)?summary|profile|objective/im',
            'qualifications' => '/^qualifications?|certifications?|projects?/im'
        );

        // Split by headers
        $current_section = 'header';
        $current_content = '';

        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            $found_header = false;

            foreach ($headers as $section => $pattern) {
                if (preg_match($pattern, trim($line))) {
                    // Save previous section
                    if ($current_section !== 'header' && !empty($current_content)) {
                        $sections[$current_section] = trim($current_content);
                    }

                    $current_section = $section;
                    $current_content = '';
                    $found_header = true;
                    break;
                }
            }

            if (!$found_header && $current_section !== 'header') {
                $current_content .= $line . "\n";
            }
        }

        // Save last section
        if ($current_section !== 'header' && !empty($current_content)) {
            $sections[$current_section] = trim($current_content);
        }

        return $sections;
    }

    /**
     * Parse experience section into structured format
     */
    private function parse_experience_section($text)
    {
        $experiences = array();

        // Pattern 1: Company Name • Role • Location • Date
        // Pattern 2: Company Name, Location\nRole\nDate
        // Pattern 3: Role at Company\nLocation | Date

        // Split into job blocks (usually separated by blank lines or dates)
        $blocks = preg_split('/\n\s*\n/', $text);

        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block)) continue;

            $job = array(
                'company' => '',
                'role' => '',
                'location' => '',
                'dates' => '',
                'bullets' => array()
            );

            $lines = explode("\n", $block);
            $bullet_started = false;

            foreach ($lines as $line) {
                $line = trim($line);

                // Check if it's a bullet point
                if (preg_match('/^[•·▪▫◦‣⁃\-\*]\s*(.+)/', $line, $matches)) {
                    $job['bullets'][] = $matches[1];
                    $bullet_started = true;
                }
                // If bullets have started, continue adding them
                elseif ($bullet_started && !empty($line)) {
                    // Might be continuation of previous bullet
                    if (!preg_match('/\d{4}/', $line) && !preg_match('/^[A-Z]/', $line)) {
                        $job['bullets'][count($job['bullets']) - 1] .= ' ' . $line;
                    }
                }
                // Parse header info
                elseif (!$bullet_started) {
                    // Check for date pattern
                    if (preg_match('/((?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+\d{4}|present|\d{4})/i', $line, $matches)) {
                        $job['dates'] = $line;
                    }
                    // Check for location (City, State/Country)
                    elseif (preg_match('/([A-Za-z\s]+,\s*[A-Z]{2})|([A-Za-z\s]+,\s*[A-Za-z\s]+)/', $line)) {
                        if (empty($job['company'])) {
                            // First line with location might be "Company, Location"
                            $parts = explode(',', $line);
                            $job['company'] = trim($parts[0]);
                            $job['location'] = trim(implode(',', array_slice($parts, 1)));
                        } else {
                            $job['location'] = $line;
                        }
                    }
                    // Likely company or role
                    elseif (empty($job['company'])) {
                        $job['company'] = $line;
                    } elseif (empty($job['role'])) {
                        $job['role'] = $line;
                    }
                }
            }

            // Only add if we have meaningful content
            if (!empty($job['company']) || !empty($job['role'])) {
                // Ensure bullets are properly formatted and within length limit
                $job['bullets'] = array_map(function ($bullet) {
                    $bullet = trim($bullet);
                    // Ensure bullet is not too long (180 char limit)
                    if (strlen($bullet) > 180) {
                        // Try to split at a logical point
                        $bullet = substr($bullet, 0, 177) . '...';
                    }
                    return $bullet;
                }, $job['bullets']);

                $experiences[] = $job;
            }
        }

        return $experiences;
    }

    /**
     * Parse education section
     */
    private function parse_education_section($text)
    {
        $education = array();

        $blocks = preg_split('/\n\s*\n/', $text);

        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block)) continue;

            $edu = array(
                'institution' => '',
                'degree' => '',
                'location' => '',
                'graduation' => '',
                'coursework' => array(),
                'gpa' => ''
            );

            $lines = explode("\n", $block);

            foreach ($lines as $line) {
                $line = trim($line);

                // Check for GPA
                if (preg_match('/GPA[:\s]+([0-9.]+)/', $line, $matches)) {
                    $edu['gpa'] = $matches[1];
                }
                // Check for graduation date
                elseif (preg_match('/((?:Expected\s+)?\d{4}|(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+\d{4})/i', $line, $matches)) {
                    $edu['graduation'] = $matches[1];
                }
                // Check for coursework
                elseif (preg_match('/coursework|relevant courses|modules/i', $line)) {
                    // Next line(s) might be coursework
                    continue;
                }
                // Check for location
                elseif (preg_match('/([A-Za-z\s]+,\s*[A-Z]{2})|([A-Za-z\s]+,\s*[A-Za-z\s]+)/', $line)) {
                    $edu['location'] = $line;
                }
                // First line is usually institution
                elseif (empty($edu['institution'])) {
                    $edu['institution'] = $line;
                }
                // Second line is usually degree
                elseif (empty($edu['degree'])) {
                    $edu['degree'] = $line;
                }
                // Might be coursework
                else {
                    $courses = preg_split('/[,;]/', $line);
                    foreach ($courses as $course) {
                        $course = trim($course);
                        if (!empty($course) && strlen($course) < 50) {
                            $edu['coursework'][] = $course;
                        }
                    }
                }
            }

            if (!empty($edu['institution']) || !empty($edu['degree'])) {
                $education[] = $edu;
            }
        }

        return $education;
    }

    /**
     * Parse skills section
     */
    private function parse_skills_section($text)
    {
        $skills = array(
            'technical' => array(),
            'languages' => array(),
            'interests' => array()
        );

        $lines = explode("\n", $text);
        $current_category = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Check for category headers
            if (preg_match('/technical\s*skills?\s*:?/i', $line)) {
                $current_category = 'technical';
                // Extract skills from same line if present
                $line = preg_replace('/technical\s*skills?\s*:?\s*/i', '', $line);
            } elseif (preg_match('/languages?\s*:?/i', $line)) {
                $current_category = 'languages';
                $line = preg_replace('/languages?\s*:?\s*/i', '', $line);
            } elseif (preg_match('/interests?\s*:?/i', $line)) {
                $current_category = 'interests';
                $line = preg_replace('/interests?\s*:?\s*/i', '', $line);
            }

            // Parse skills from line
            if (!empty($line) && !empty($current_category)) {
                $items = preg_split('/[,;]/', $line);
                foreach ($items as $item) {
                    $item = trim($item);
                    if (!empty($item)) {
                        $skills[$current_category][] = $item;
                    }
                }
            }
        }

        return $skills;
    }

    /**
     * Parse qualifications/projects section
     */
    private function parse_qualifications_section($text)
    {
        $qualifications = array();

        $blocks = preg_split('/\n\s*\n/', $text);

        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block)) continue;

            $qual = array(
                'institution' => '',
                'name' => '',
                'date' => '',
                'details' => array()
            );

            $lines = explode("\n", $block);
            $details_started = false;

            foreach ($lines as $line) {
                $line = trim($line);

                // Check if it's a bullet point
                if (preg_match('/^[•·▪▫◦‣⁃\-\*]\s*(.+)/', $line, $matches)) {
                    $qual['details'][] = $matches[1];
                    $details_started = true;
                } elseif ($details_started) {
                    // Continuation of details
                    if (!empty($qual['details'])) {
                        $qual['details'][count($qual['details']) - 1] .= ' ' . $line;
                    }
                } else {
                    // Header info
                    if (preg_match('/\d{4}/', $line)) {
                        $qual['date'] = $line;
                    } elseif (empty($qual['name'])) {
                        $qual['name'] = $line;
                    } elseif (empty($qual['institution'])) {
                        $qual['institution'] = $line;
                    }
                }
            }

            if (!empty($qual['name'])) {
                $qualifications[] = $qual;
            }
        }

        return $qualifications;
    }

    /**
     * Clean summary text
     */
    private function clean_summary($text)
    {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Remove section header if still present
        $text = preg_replace('/^(personal\s+|professional\s+)?summary\s*:?\s*/i', '', $text);

        return trim($text);
    }

    /**
     * Fallback experience parser if main parser fails
     */
    private function fallback_parse_experience($text)
    {
        $experiences = array();

        // Look for common patterns
        // Pattern: Company names often have Inc, LLC, Ltd, Corporation, etc.
        $pattern = '/([A-Z][A-Za-z\s&]+(?:Inc|LLC|Ltd|Corporation|Corp|Company|Co\.|Group|Partners|LLP))/';

        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[1] as $company) {
                $experiences[] = array(
                    'company' => trim($company),
                    'role' => 'Position',
                    'location' => '',
                    'dates' => '',
                    'bullets' => array('Experience details to be provided')
                );
            }
        }

        // If still nothing, create placeholder
        if (empty($experiences)) {
            $experiences[] = array(
                'company' => '[Company Name]',
                'role' => '[Position Title]',
                'location' => '[City, State]',
                'dates' => '[Start Date - End Date]',
                'bullets' => array(
                    'Please provide your work experience details',
                    'Include key achievements and responsibilities'
                )
            );
        }

        return $experiences;
    }

    /**
     * Clean text helper
     */
    private function clean_text($text)
    {
        // Remove multiple spaces
        $text = preg_replace('/[ \t]+/', ' ', $text);

        // Fix common OCR errors
        $text = str_replace(['•', '·', '▪', '▫', '◦', '‣', '⁃'], '•', $text);

        // Remove page numbers and headers
        $text = preg_replace('/Page \d+ of \d+/i', '', $text);
        $text = preg_replace('/^\d+\s*$/m', '', $text);

        // Normalize line breaks
        $text = str_replace("\r\n", "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return $text;
    }

    /**
     * Handle CV tailoring with POWERFUL integrated engine
     */
    public function handle_cv_tailoring()
    {
        try {
            // Flexible nonce verification
            $this->verify_request_nonce();

            $cv_id = sanitize_text_field($_POST['cv_id']);
            $job_title = sanitize_text_field($_POST['job_title']);
            $company = sanitize_text_field($_POST['company']);
            $job_description = sanitize_textarea_field($_POST['job_description']);

            // Get stored CV
            $cv_data = $this->get_stored_cv($cv_id);
            if (!$cv_data) {
                throw new Exception('CV not found. Please upload again.');
            }

            // Use POWER Tailoring Engine for maximum impact
            require_once plugin_dir_path(__FILE__) . 'class-cv-power-tailoring.php';
            $power_engine = new SFFC_CV_Power_Tailoring();

            // Prepare job data
            $job_data = array(
                'id' => sanitize_text_field($_POST['job_id'] ?? 'manual'),
                'title' => $job_title,
                'company' => $company,
                'description' => $job_description
            );

            // POWER TAILOR the CV
            $result = $power_engine->power_tailor($cv_data, $job_data);

            if ($result['success']) {
                // Store tailored version for download
                $tailored_id = $this->store_tailored_cv($result['tailored_cv'], $cv_id, $job_data);

                // Return WSJ formatted response
                $wsj_html = $this->generate_wsj_cv_display($result['tailored_cv'], $job_title, $company, $result['match_score']);

                wp_send_json_success(array(
                    'tailored_id' => $tailored_id,
                    'tailored_cv' => $result['tailored_cv'],
                    'wsj_display' => $wsj_html,
                    'preview' => $this->generate_text_preview($result['tailored_cv']),
                    'match_score' => $result['match_score'],
                    'analysis' => $result['analysis'],
                    'transformations' => $result['transformations'],
                    'message' => "CV optimized for {$job_title} at {$company}",
                    'download_ready' => true,
                    'use_wsj_display' => true
                ));
                return;
            }

            // Should never reach here with power engine
            $tailored_cv = $cv_data;

            // Tailor experience bullets
            foreach ($tailored_cv['experience'] as &$job) {
                $job['bullets'] = $this->tailor_bullets_with_claude(
                    $job['bullets'],
                    $job_title,
                    $company,
                    $job_description
                );
            }

            // Tailor summary if present
            if (!empty($tailored_cv['summary'])) {
                $tailored_cv['summary'] = $this->tailor_summary_with_claude(
                    $tailored_cv['summary'],
                    $job_title,
                    $company,
                    $job_description
                );
            }

            // Optimize skills based on job
            $tailored_cv['skills'] = $this->optimize_skills_for_job(
                $tailored_cv['skills'],
                $job_description
            );

            // Store tailored version - create job_data array for consistency
            $job_data_for_storage = array(
                'title' => $job_title,
                'company' => $company
            );
            $tailored_id = $this->store_tailored_cv($tailored_cv, $cv_id, $job_data_for_storage);

            // Return WSJ formatted response
            $wsj_html = $this->generate_wsj_cv_display($tailored_cv, $job_title, $company, 75);

            wp_send_json_success(array(
                'tailored_id' => $tailored_id,
                'tailored_cv' => $tailored_cv,
                'wsj_display' => $wsj_html,
                'preview' => $this->generate_text_preview($tailored_cv),
                'message' => "CV tailored for {$job_title} at {$company}",
                'download_ready' => true,
                'use_wsj_display' => true
            ));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Tailor bullets with Claude API
     */
    private function tailor_bullets_with_claude($bullets, $job_title, $company, $job_description)
    {
        if (empty($bullets)) return $bullets;

        // Extract key requirements from job description
        $key_skills = $this->extract_key_requirements($job_description);

        $prompt = "You are tailoring CV bullets for a {$job_title} role at {$company}.

KEY REQUIREMENTS from job:
" . implode("\n", array_slice($key_skills, 0, 5)) . "

ORIGINAL BULLETS:
" . implode("\n", array_map(function ($b, $i) {
            return ($i + 1) . ". " . $b;
        }, $bullets, array_keys($bullets))) . "

INSTRUCTIONS:
1. Rewrite each bullet to emphasize skills relevant to this role
2. Each bullet MUST be less than 180 characters
3. Start with strong action verbs (Led, Managed, Analyzed, Executed, Developed)
4. Include metrics/numbers where possible
5. Incorporate relevant keywords naturally
6. Maintain truthfulness - reframe but don't fabricate

Output ONLY the rewritten bullets, numbered 1-" . count($bullets) . ", one per line.";

        // Use Claude API to tailor bullets
        $result = array('success' => false);

        $claude_api = $this->get_claude_api();
        if ($claude_api && method_exists($claude_api, 'send_message')) {
            $result = $claude_api->send_message($prompt, array(), 'cv_tailoring');
        } else {
            // Fallback: Basic optimization without AI
            error_log('[CV System] Claude API not available, using basic optimization');
            return $this->optimize_bullets_basic($bullets, $key_skills);
        }

        if ($result['success']) {
            // Parse response into bullets
            $new_bullets = array();
            $lines = explode("\n", $result['response']);

            foreach ($lines as $line) {
                // Remove numbering and clean
                $line = preg_replace('/^\d+[\.\)]\s*/', '', trim($line));
                if (!empty($line) && strlen($line) < 180) {
                    $new_bullets[] = $line;
                }
            }

            // Ensure we have the right number of bullets
            if (count($new_bullets) >= count($bullets)) {
                return array_slice($new_bullets, 0, count($bullets));
            }
        }

        // Fallback: return original bullets if Claude fails
        return $bullets;
    }

    /**
     * Basic bullet optimization without AI
     */
    private function optimize_bullets_basic($bullets, $key_skills)
    {
        $optimized = array();

        $action_verbs = array(
            'Led',
            'Managed',
            'Developed',
            'Analyzed',
            'Executed',
            'Implemented',
            'Streamlined',
            'Coordinated',
            'Delivered',
            'Achieved',
            'Increased',
            'Reduced',
            'Generated',
            'Built'
        );

        foreach ($bullets as $bullet) {
            // Ensure it starts with action verb
            $has_action = false;
            foreach ($action_verbs as $verb) {
                if (stripos($bullet, $verb) === 0) {
                    $has_action = true;
                    break;
                }
            }

            if (!$has_action) {
                // Add action verb
                $bullet = $action_verbs[array_rand($action_verbs)] . ' ' . lcfirst($bullet);
            }

            // Trim to 180 chars
            if (strlen($bullet) > 180) {
                $bullet = substr($bullet, 0, 177) . '...';
            }

            $optimized[] = $bullet;
        }

        return $optimized;
    }

    /**
     * Tailor summary with Claude
     */
    private function tailor_summary_with_claude($summary, $job_title, $company, $job_description)
    {
        $prompt = "Rewrite this professional summary for a {$job_title} role at {$company}:

ORIGINAL SUMMARY:
{$summary}

JOB FOCUS:
" . substr($job_description, 0, 200) . "

Create a 2-3 sentence summary that:
1. Highlights relevant experience for this specific role
2. Mentions key skills they're looking for
3. Shows enthusiasm for this particular opportunity
4. Stays under 300 characters total

Output ONLY the rewritten summary.";

        $claude_api = $this->get_claude_api();
        if (!$claude_api || !method_exists($claude_api, 'send_message')) {
            return $summary;
        }

        $result = $claude_api->send_message($prompt, array(), 'cv_tailoring');

        if ($result['success']) {
            $new_summary = trim($result['response']);
            if (!empty($new_summary) && strlen($new_summary) < 300) {
                return $new_summary;
            }
        }

        return $summary;
    }

    /**
     * Extract key requirements from job description
     */
    private function extract_key_requirements($job_description)
    {
        $requirements = array();

        // Common requirement patterns
        $patterns = array(
            '/(?:required|must have|looking for|need|essential)[:\s]+([^.]+)/i',
            '/(?:experience with|knowledge of|proficient in)[:\s]+([^.]+)/i',
            '/(?:strong|excellent|proven)[:\s]+([^.]+skills?)/i'
        );

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $job_description, $matches)) {
                foreach ($matches[1] as $match) {
                    $requirements[] = trim($match);
                }
            }
        }

        // Also extract specific skills mentioned
        $skills = array(
            'Financial modeling',
            'Valuation',
            'DCF',
            'LBO',
            'M&A',
            'Due diligence',
            'Portfolio management',
            'Excel',
            'PowerPoint',
            'Python',
            'SQL',
            'Bloomberg',
            'Capital IQ',
            'PitchBook'
        );

        foreach ($skills as $skill) {
            if (stripos($job_description, $skill) !== false) {
                $requirements[] = $skill;
            }
        }

        return array_unique($requirements);
    }

    /**
     * Optimize skills for job
     */
    private function optimize_skills_for_job($skills, $job_description)
    {
        // Add relevant technical skills if mentioned in job
        $technical_keywords = array(
            'Excel',
            'Financial modeling',
            'PowerPoint',
            'Python',
            'SQL',
            'Bloomberg Terminal',
            'Capital IQ',
            'FactSet',
            'VBA'
        );

        foreach ($technical_keywords as $keyword) {
            if (stripos($job_description, $keyword) !== false) {
                if (!in_array($keyword, $skills['technical'])) {
                    array_unshift($skills['technical'], $keyword);
                }
            }
        }

        // Limit to top 6-8 skills
        $skills['technical'] = array_slice($skills['technical'], 0, 8);

        return $skills;
    }

    /**
     * Generate PDF download
     */
    public function handle_cv_download()
    {
        try {
            // Flexible nonce verification
            $this->verify_request_nonce();

            $tailored_id = sanitize_text_field($_POST['tailored_id']);

            // Get tailored CV data
            $cv_data = $this->get_tailored_cv($tailored_id);
            if (!$cv_data) {
                throw new Exception('Tailored CV not found.');
            }

            // Generate professional PDF
            $pdf_path = $this->generate_professional_pdf($cv_data);

            // Create download URL
            $upload_dir = wp_upload_dir();
            $pdf_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $pdf_path);

            wp_send_json_success(array(
                'download_url' => $pdf_url,
                'filename' => basename($pdf_path),
                'message' => 'Your tailored CV is ready for download!'
            ));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Store tailored CV for download
     */
    private function store_tailored_cv($tailored_cv, $original_cv_id, $job_data)
    {
        // Generate unique ID
        $tailored_id = 'tailored_' . $original_cv_id . '_' . time();

        // Store in transient (WordPress temp storage)
        set_transient($tailored_id, $tailored_cv, 3600); // Store for 1 hour

        // Also store job data for reference
        set_transient($tailored_id . '_job', $job_data, 3600);

        return $tailored_id;
    }

    /**
     * Get tailored CV from storage
     */
    private function get_tailored_cv($tailored_id)
    {
        return get_transient($tailored_id);
    }

    /**
     * Generate professional PDF with proper IB/PE formatting
     */
    private function generate_professional_pdf($cv_data)
    {
        if (!class_exists('TCPDF')) {
            // Try multiple paths for TCPDF
            $tcpdf_paths = array(
                plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php',
                plugin_dir_path(dirname(__FILE__)) . 'CARDS/vendor/autoload.php',
                plugin_dir_path(dirname(__FILE__)) . 'vendor/tecnickcom/tcpdf/tcpdf.php',
                plugin_dir_path(dirname(__FILE__)) . 'CARDS/vendor/tecnickcom/tcpdf/tcpdf.php'
            );

            $loaded = false;
            foreach ($tcpdf_paths as $path) {
                if (file_exists($path)) {
                    require_once $path;
                    $loaded = true;
                    break;
                }
            }

            if (!$loaded || !class_exists('TCPDF')) {
                // Fallback: Generate simple HTML for now
                return $this->generate_simple_html_cv($cv_data);
            }
        }

        // Create new PDF document - A4, Portrait
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        // Remove TCPDF branding
        $pdf->SetCreator('');
        $pdf->SetKeywords('');
        $pdf->setFooterData(array(255, 255, 255), array(255, 255, 255));
        $pdf->setFooterFont(array('helvetica', '', 0));

        // Document properties
        $pdf->SetCreator('MENA Careers Professional CV');
        $pdf->SetAuthor($cv_data['contact']['name']);
        $pdf->SetTitle($cv_data['contact']['name'] . ' - CV');

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins - tight for professional look
        $pdf->SetMargins(15, 10, 15);
        $pdf->SetAutoPageBreak(true, 10);

        // Add page
        $pdf->AddPage();

        // --- NAME (CENTER, LARGE) ---
        $pdf->SetFont('helvetica', 'B', 16);
        $name = html_entity_decode($cv_data['contact']['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $pdf->Cell(0, 7, strtoupper($name), 0, 1, 'C');

        // --- CONTACT LINE (CENTER, SMALL) ---
        $pdf->SetFont('helvetica', '', 9);
        $contact_line = array();
        if (!empty($cv_data['contact']['address'])) $contact_line[] = $cv_data['contact']['address'];
        if (!empty($cv_data['contact']['phone'])) $contact_line[] = $cv_data['contact']['phone'];
        if (!empty($cv_data['contact']['email'])) $contact_line[] = $cv_data['contact']['email'];
        if (!empty($cv_data['contact']['linkedin'])) $contact_line[] = $cv_data['contact']['linkedin'];

        $pdf->Cell(0, 5, implode(' | ', $contact_line), 0, 1, 'C');

        // Add small space
        $pdf->Ln(2);

        // --- PERSONAL SUMMARY (if exists) ---
        if (!empty($cv_data['summary'])) {
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, 6, 'PERSONAL SUMMARY', 'B', 1, 'L');

            $pdf->SetFont('helvetica', '', 10);
            $summary = html_entity_decode($cv_data['summary'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $summary = str_replace('&amp;', '&', $summary);
            $pdf->MultiCell(0, 5, $summary, 0, 'L');
            $pdf->Ln(2);
        }

        // --- EXPERIENCE ---
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, 'EXPERIENCE', 'B', 1, 'L');
        $pdf->Ln(1);

        foreach ($cv_data['experience'] as $job) {
            // Company (Bold, Left) | Location (Right)
            $pdf->SetFont('helvetica', 'B', 10);
            $company_width = $pdf->GetStringWidth($job['company']);
            $pdf->Cell($company_width, 5, $job['company'], 0, 0, 'L');

            if (!empty($job['location'])) {
                $pdf->SetFont('helvetica', '', 10);
                $pdf->Cell(0, 5, $job['location'], 0, 1, 'R');
            } else {
                $pdf->Ln();
            }

            // Role (Italic, Left) | Dates (Right)
            $pdf->SetFont('helvetica', 'I', 10);
            $role_width = $pdf->GetStringWidth($job['role']);
            $pdf->Cell($role_width, 5, $job['role'], 0, 0, 'L');

            if (!empty($job['dates'])) {
                $pdf->SetFont('helvetica', '', 10);
                $pdf->Cell(0, 5, $job['dates'], 0, 1, 'R');
            } else {
                $pdf->Ln();
            }

            // Bullets
            $pdf->SetFont('helvetica', '', 10);
            foreach ($job['bullets'] as $bullet) {
                // Clean bullet text
                $bullet = html_entity_decode($bullet, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $bullet = str_replace('&amp;', '&', $bullet);
                $bullet = trim($bullet);

                // Bullet with proper formatting
                $pdf->SetX($pdf->GetX() + 5); // Indent
                $pdf->Cell(3, 5, '•', 0, 0, 'L');
                $pdf->MultiCell(0, 5, ' ' . $bullet, 0, 'L');
            }

            $pdf->Ln(2);
        }

        // --- EDUCATION ---
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, 'EDUCATION', 'B', 1, 'L');
        $pdf->Ln(1);

        foreach ($cv_data['education'] as $edu) {
            // Institution (Bold, Left) | Location (Right)
            $pdf->SetFont('helvetica', 'B', 10);
            $inst_width = $pdf->GetStringWidth($edu['institution']);
            $pdf->Cell($inst_width, 5, $edu['institution'], 0, 0, 'L');

            if (!empty($edu['location'])) {
                $pdf->SetFont('helvetica', '', 10);
                $pdf->Cell(0, 5, $edu['location'], 0, 1, 'R');
            } else {
                $pdf->Ln();
            }

            // Degree (Italic, Left) | Graduation (Right)
            $pdf->SetFont('helvetica', 'I', 10);
            $degree_text = $edu['degree'];
            if (!empty($edu['gpa'])) {
                $degree_text .= ' (GPA: ' . $edu['gpa'] . ')';
            }
            $degree_width = $pdf->GetStringWidth($degree_text);
            $pdf->Cell($degree_width, 5, $degree_text, 0, 0, 'L');

            if (!empty($edu['graduation'])) {
                $pdf->SetFont('helvetica', '', 10);
                $pdf->Cell(0, 5, $edu['graduation'], 0, 1, 'R');
            } else {
                $pdf->Ln();
            }

            // Coursework if present
            if (!empty($edu['coursework'])) {
                $pdf->SetFont('helvetica', '', 9);
                $pdf->Cell(5, 5, '', 0, 0); // Indent
                $pdf->MultiCell(0, 5, 'Relevant Coursework: ' . implode(', ', $edu['coursework']), 0, 'L');
            }

            $pdf->Ln(2);
        }

        // --- QUALIFICATIONS/PROJECTS (if exists) ---
        if (!empty($cv_data['qualifications'])) {
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, 6, 'QUALIFICATIONS & PROJECTS', 'B', 1, 'L');
            $pdf->Ln(1);

            foreach ($cv_data['qualifications'] as $qual) {
                // Name (Bold, Left) | Date (Right)
                $pdf->SetFont('helvetica', 'B', 10);
                $name_width = $pdf->GetStringWidth($qual['name']);
                $pdf->Cell($name_width, 5, $qual['name'], 0, 0, 'L');

                if (!empty($qual['date'])) {
                    $pdf->SetFont('helvetica', '', 10);
                    $pdf->Cell(0, 5, $qual['date'], 0, 1, 'R');
                } else {
                    $pdf->Ln();
                }

                // Institution if present
                if (!empty($qual['institution'])) {
                    $pdf->SetFont('helvetica', 'I', 9);
                    $pdf->Cell(0, 5, $qual['institution'], 0, 1, 'L');
                }

                // Details
                $pdf->SetFont('helvetica', '', 10);
                foreach ($qual['details'] as $detail) {
                    $detail = html_entity_decode($detail, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $detail = str_replace('&amp;', '&', $detail);
                    $pdf->SetX($pdf->GetX() + 5);
                    $pdf->Cell(3, 5, '•', 0, 0, 'L');
                    $pdf->MultiCell(0, 5, ' ' . $detail, 0, 'L');
                }

                $pdf->Ln(1);
            }
        }

        // --- SKILLS & INTERESTS ---
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, 'SKILLS & INTERESTS', 'B', 1, 'L');
        $pdf->Ln(1);

        $pdf->SetFont('helvetica', '', 10);

        // Technical Skills
        if (!empty($cv_data['skills']['technical'])) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(25, 5, 'Technical Skills:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 5, implode(', ', $cv_data['skills']['technical']), 0, 1, 'L');
        }

        // Languages
        if (!empty($cv_data['skills']['languages'])) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(25, 5, 'Languages:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 5, implode(', ', $cv_data['skills']['languages']), 0, 1, 'L');
        }

        // Interests
        if (!empty($cv_data['skills']['interests'])) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(25, 5, 'Interests:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 5, implode(', ', $cv_data['skills']['interests']), 0, 1, 'L');
        }

        // Save PDF
        $filename = 'CV_' . preg_replace('/[^A-Za-z0-9]/', '_', $cv_data['contact']['name']) . '_' . date('Ymd') . '.pdf';
        $filepath = $this->upload_dir . '/' . $filename;

        $pdf->Output($filepath, 'F');

        return $filepath;
    }

    /**
     * Generate simple HTML CV as fallback when TCPDF is not available
     */
    private function generate_simple_html_cv($cv_data)
    {
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>CV</title></head><body style="font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;">';

        // Name
        $html .= '<h1 style="text-align: center; font-size: 24px; margin-bottom: 5px;">' . esc_html($cv_data['contact']['name']) . '</h1>';

        // Contact
        $contact_parts = array();
        if (!empty($cv_data['contact']['email'])) $contact_parts[] = $cv_data['contact']['email'];
        if (!empty($cv_data['contact']['phone'])) $contact_parts[] = $cv_data['contact']['phone'];
        if (!empty($cv_data['contact']['address'])) $contact_parts[] = $cv_data['contact']['address'];

        $html .= '<p style="text-align: center; font-size: 12px; color: #666; margin-bottom: 20px;">' . esc_html(implode(' | ', $contact_parts)) . '</p>';

        // Summary
        if (!empty($cv_data['summary'])) {
            $html .= '<h2 style="font-size: 16px; border-bottom: 2px solid #333; padding-bottom: 5px;">PERSONAL SUMMARY</h2>';
            $html .= '<p style="font-size: 14px; line-height: 1.5;">' . esc_html($cv_data['summary']) . '</p>';
        }

        // Experience
        $html .= '<h2 style="font-size: 16px; border-bottom: 2px solid #333; padding-bottom: 5px; margin-top: 20px;">EXPERIENCE</h2>';
        foreach ($cv_data['experience'] as $job) {
            $html .= '<div style="margin-bottom: 15px;">';
            $html .= '<div style="display: flex; justify-content: space-between;">';
            $html .= '<strong>' . esc_html($job['company']) . '</strong>';
            $html .= '<span>' . esc_html($job['location']) . '</span>';
            $html .= '</div>';
            $html .= '<div style="display: flex; justify-content: space-between; font-style: italic; font-size: 13px;">';
            $html .= '<span>' . esc_html($job['role']) . '</span>';
            $html .= '<span>' . esc_html($job['dates']) . '</span>';
            $html .= '</div>';
            $html .= '<ul style="margin-top: 5px; padding-left: 20px;">';
            foreach ($job['bullets'] as $bullet) {
                $html .= '<li style="font-size: 13px; line-height: 1.4; margin-bottom: 3px;">' . esc_html($bullet) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }

        // Education
        $html .= '<h2 style="font-size: 16px; border-bottom: 2px solid #333; padding-bottom: 5px; margin-top: 20px;">EDUCATION</h2>';
        foreach ($cv_data['education'] as $edu) {
            $html .= '<div style="margin-bottom: 15px;">';
            $html .= '<div style="display: flex; justify-content: space-between;">';
            $html .= '<strong>' . esc_html($edu['institution']) . '</strong>';
            $html .= '<span>' . esc_html($edu['location']) . '</span>';
            $html .= '</div>';
            $html .= '<div style="display: flex; justify-content: space-between; font-style: italic; font-size: 13px;">';
            $html .= '<span>' . esc_html($edu['degree']) . '</span>';
            $html .= '<span>' . esc_html($edu['graduation']) . '</span>';
            $html .= '</div>';
            if (!empty($edu['coursework'])) {
                $html .= '<p style="font-size: 12px; margin-top: 5px;">Relevant Coursework: ' . esc_html(implode(', ', $edu['coursework'])) . '</p>';
            }
            $html .= '</div>';
        }

        // Skills
        $html .= '<h2 style="font-size: 16px; border-bottom: 2px solid #333; padding-bottom: 5px; margin-top: 20px;">SKILLS & INTERESTS</h2>';
        if (!empty($cv_data['skills']['technical'])) {
            $html .= '<p style="font-size: 13px;"><strong>Technical Skills:</strong> ' . esc_html(implode(', ', $cv_data['skills']['technical'])) . '</p>';
        }
        if (!empty($cv_data['skills']['languages'])) {
            $html .= '<p style="font-size: 13px;"><strong>Languages:</strong> ' . esc_html(implode(', ', $cv_data['skills']['languages'])) . '</p>';
        }
        if (!empty($cv_data['skills']['interests'])) {
            $html .= '<p style="font-size: 13px;"><strong>Interests:</strong> ' . esc_html(implode(', ', $cv_data['skills']['interests'])) . '</p>';
        }

        $html .= '</body></html>';

        // Save HTML file
        $filename = 'CV_' . preg_replace('/[^A-Za-z0-9]/', '_', $cv_data['contact']['name']) . '_' . date('Ymd') . '.html';
        $filepath = $this->upload_dir . '/' . $filename;

        file_put_contents($filepath, $html);

        return $filepath;
    }

    /**
     * Helper functions for data storage
     */
    private function store_parsed_cv($parsed_cv, $original_text)
    {
        $cv_id = 'cv_' . wp_generate_password(12, false);

        set_transient($cv_id, array(
            'parsed' => $parsed_cv,
            'original' => $original_text
        ), HOUR_IN_SECONDS);

        return $cv_id;
    }

    private function get_stored_cv($cv_id)
    {
        $data = get_transient($cv_id);
        return $data ? $data['parsed'] : false;
    }

    // Removed duplicate methods - using the ones defined at lines 1068-1086

    private function generate_text_preview($cv_data)
    {
        $preview = $cv_data['contact']['name'] . "\n";
        $preview .= str_repeat('-', 50) . "\n\n";

        if (!empty($cv_data['summary'])) {
            $preview .= "SUMMARY\n" . $cv_data['summary'] . "\n\n";
        }

        $preview .= "EXPERIENCE\n";
        foreach ($cv_data['experience'] as $job) {
            $preview .= $job['company'] . " - " . $job['role'] . "\n";
            foreach ($job['bullets'] as $bullet) {
                $preview .= "• " . $bullet . "\n";
            }
            $preview .= "\n";
        }

        return $preview;
    }

    /**
     * Extract text from uploaded file
     */
    private function extract_text_from_file($file)
    {
        $allowed_types = array('pdf', 'doc', 'docx', 'txt');
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed_types)) {
            throw new Exception('Invalid file type. Please upload PDF, Word, or text file.');
        }

        // Save temporary file
        $temp_file = $this->upload_dir . '/temp_' . uniqid() . '.' . $file_ext;
        move_uploaded_file($file['tmp_name'], $temp_file);

        $text = '';

        // Extract based on file type
        $parser = $this->get_document_parser();
        switch ($file_ext) {
            case 'pdf':
                if ($parser instanceof SFFC_Document_Parser) {
                    $text = $parser->extract_pdf_text($temp_file);
                }
                if (empty($text) && function_exists('shell_exec')) {
                    $command = sprintf('pdftotext -layout %s - 2>&1', escapeshellarg($temp_file));
                    $fallback = shell_exec($command);
                    if (!empty($fallback) && strpos($fallback, 'command not found') === false) {
                        $text = $fallback;
                    }
                }
                break;

            case 'docx':
                if ($parser instanceof SFFC_Document_Parser) {
                    $text = $parser->extract_docx_text($temp_file);
                }
                if (empty($text) && class_exists('ZipArchive')) {
                    $zip = new ZipArchive();
                    if ($zip->open($temp_file) === TRUE) {
                        $content = $zip->getFromName('word/document.xml');
                        if ($content) {
                            $text = strip_tags($content);
                        }
                        $zip->close();
                    }
                }
                break;

            case 'doc':
                if ($parser instanceof SFFC_Document_Parser) {
                    $text = $parser->extract_doc_text($temp_file);
                }
                if (empty($text) && function_exists('shell_exec')) {
                    $command = sprintf('catdoc %s 2>&1', escapeshellarg($temp_file));
                    $fallback = shell_exec($command);
                    if (!empty($fallback) && strpos($fallback, 'command not found') === false) {
                        $text = $fallback;
                    }
                }
                break;

            case 'txt':
                $text = file_get_contents($temp_file);
                break;
        }

        // Clean up temp file
        unlink($temp_file);

        if (empty($text)) {
            throw new Exception('Could not extract text from file. Please paste your CV instead.');
        }

        return $text;
    }

    /**
     * Get CV from user profile if available
     */
    private function get_profile_cv()
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return '';
        }

        // Check if user has stored CV
        $profile_cv = get_user_meta($user_id, 'professional_cv', true);

        if (empty($profile_cv)) {
            // Try to build from profile data
            $profile_data = get_user_meta($user_id, 'profile_data', true);
            if ($profile_data) {
                return $this->build_cv_from_profile($profile_data);
            }
        }

        return $profile_cv;
    }

    private function build_cv_from_profile($profile_data)
    {
        // Convert profile data to CV format
        $cv_text = '';

        if (!empty($profile_data['name'])) {
            $cv_text .= $profile_data['name'] . "\n";
        }

        if (!empty($profile_data['experience'])) {
            $cv_text .= "\nEXPERIENCE\n";
            foreach ($profile_data['experience'] as $job) {
                $cv_text .= $job['company'] . "\n";
                $cv_text .= $job['role'] . "\n";
                $cv_text .= $job['dates'] . "\n";
                if (!empty($job['description'])) {
                    $cv_text .= "• " . $job['description'] . "\n";
                }
                $cv_text .= "\n";
            }
        }

        return $cv_text;
    }

    /**
     * Call CV Intelligence Engine - Now using WSJ CV Bridge
     */
    private function call_python_parser($file_path)
    {
        try {
            // Use WSJ CV Bridge - the new secure parser without hardcoded data
            require_once plugin_dir_path(__FILE__) . 'class-wsj-cv-bridge.php';

            // Extract text from file
            $text_content = '';
            $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
            if ($ext === 'docx') {
                // Extract text from DOCX
                $zip = new ZipArchive();
                if ($zip->open($file_path) === TRUE) {
                    $content = $zip->getFromName('word/document.xml');
                    $zip->close();
                    if ($content) {
                        // Better text extraction from Word XML
                        $xml = simplexml_load_string($content);
                        if ($xml) {
                            $text_content = strip_tags($xml->asXML());
                            $text_content = preg_replace('/\s+/', ' ', $text_content);
                        }
                    }
                }
            } else if ($ext === 'pdf') {
                // Extract text from PDF (you may need a PDF parser library)
                $text_content = $this->extract_pdf_text($file_path);
            } else {
                $text_content = file_get_contents($file_path);
            }

            // Use WSJ CV Bridge for parsing
            error_log('[CV Parser] Using WSJ CV Bridge - secure parser without hardcoded data');

            $parsed_data = SFFC_WSJ_CV_Bridge::parse_cv_text($text_content);
            $result = SFFC_WSJ_CV_Bridge::convert_to_legacy_format($parsed_data);

            if (!$result['success']) {
                error_log('[CV Parser] All parsing methods failed');
                return null;
            }

            // Convert to expected format
            $parsed = array(
                'success' => true,
                'contact' => $result['data']['contact'],
                'summary' => $result['data']['summary'],
                'experience' => $result['data']['experience'],
                'education' => $result['data']['education'],
                'skills' => $result['data']['skills'],
                'metadata' => $result['metadata']
            );

            error_log('[CV Parser] Successfully parsed with ' . $result['metadata']['confidence_score'] . '% confidence');

            return $parsed;
        } catch (Exception $e) {
            error_log('[CV Parser] PHP parser exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Convert parser output to our structure
     */
    private function convert_python_to_structure($parsed)
    {
        $structure = array(
            'contact' => array(
                'name' => $parsed['contact']['name'] ?? '',
                'address' => $parsed['contact']['address'] ?? '',
                'phone' => $parsed['contact']['phone'] ?? '',
                'email' => $parsed['contact']['email'] ?? '',
                'linkedin' => $parsed['contact']['linkedin'] ?? ''
            ),
            'summary' => $parsed['summary'] ?? '',
            'experience' => array(),
            'education' => array(),
            'qualifications' => $parsed['certifications'] ?? array(),
            'skills' => $parsed['skills'] ?? array(
                'technical' => array(),
                'languages' => array(),
                'interests' => array()
            ),
            'metadata' => $parsed['metadata'] ?? array()
        );

        // Convert experience
        if (!empty($parsed['experience'])) {
            foreach ($parsed['experience'] as $exp) {
                $structure['experience'][] = array(
                    'company' => $exp['company'] ?? '',
                    'role' => $exp['role'] ?? '',
                    'location' => $exp['location'] ?? '',
                    'dates' => $exp['dates'] ?? '',
                    'bullets' => $exp['bullets'] ?? array()
                );
            }
        }

        // Convert education
        if (!empty($parsed['education'])) {
            foreach ($parsed['education'] as $edu) {
                $structure['education'][] = array(
                    'institution' => $edu['institution'] ?? '',
                    'degree' => $edu['degree'] ?? '',
                    'location' => $edu['location'] ?? '',
                    'graduation' => $edu['dates'] ?? '',
                    'coursework' => $edu['coursework'] ?? array(),
                    'gpa' => $edu['gpa'] ?? ''
                );
            }
        }

        return $structure;
    }

    /**
     * Enhanced contact extraction for PHP fallback
     */
    private function extract_contact_enhanced($text)
    {
        $contact = array(
            'name' => '',
            'address' => '',
            'phone' => '',
            'email' => '',
            'linkedin' => ''
        );

        // Extract email with better pattern
        if (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $text, $matches)) {
            $contact['email'] = $matches[1];
        }

        // Extract phone with multiple patterns
        $phone_patterns = array(
            '/(\+?44\s?[0-9]{2,4}\s?[0-9]{6,8})/',  // UK
            '/(\+?1?\s?\(?\d{3}\)?\s?\d{3}[\s-]?\d{4})/',  // US
            '/(\+?\d{1,3}[\s-]?\d{4,14})/'  // International
        );

        foreach ($phone_patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $contact['phone'] = trim($matches[1]);
                break;
            }
        }

        // Extract LinkedIn
        if (preg_match('/linkedin\.co\/in\/([a-zA-Z0-9-]+)/i', $text, $matches)) {
            $contact['linkedin'] = 'linkedin.com/in/' . $matches[1];
        }

        // Extract name (first line that's not contact info)
        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Skip if contains contact info
            if (
                strpos($line, '@') !== false ||
                preg_match('/\d{4,}/', $line) ||
                preg_match('/experience|education|skills/i', $line)
            ) {
                continue;
            }

            // Likely name if 1-4 words and not too long
            $words = str_word_count($line);
            if ($words >= 1 && $words <= 4 && strlen($line) < 50) {
                $contact['name'] = $line;
                break;
            }
        }

        // Extract address (look for postcodes)
        if (preg_match('/[A-Z]{1,2}\d{1,2}\s*\d[A-Z]{2}/i', $text, $matches, PREG_OFFSET_CAPTURE)) {
            // UK postcode found, extract surrounding text
            $start = max(0, $matches[0][1] - 100);
            $address_context = substr($text, $start, 200);

            // Find the line containing the postcode
            $address_lines = explode("\n", $address_context);
            foreach ($address_lines as $line) {
                if (strpos($line, $matches[0][0]) !== false) {
                    $contact['address'] = trim($line);
                    break;
                }
            }
        }

        return $contact;
    }

    /**
     * Enhanced experience extraction for PHP fallback
     */
    private function extract_experience_enhanced($text)
    {
        $experiences = array();

        // Find experience section more reliably
        $exp_start = false;
        $exp_text = '';

        $lines = explode("\n", $text);
        foreach ($lines as $i => $line) {
            if (preg_match('/^(PROFESSIONAL\s+)?EXPERIENCE|WORK\s+EXPERIENCE/i', $line)) {
                $exp_start = $i;
                break;
            }
        }

        if ($exp_start !== false) {
            // Extract until next major section
            for ($i = $exp_start + 1; $i < count($lines); $i++) {
                if (preg_match('/^(EDUCATION|SKILLS|QUALIFICATIONS|CERTIFICATIONS|PROJECTS)/i', $lines[$i])) {
                    break;
                }
                $exp_text .= $lines[$i] . "\n";
            }
        }

        if (empty($exp_text)) {
            // Fallback: look for date patterns
            $exp_text = $text;
        }

        // Parse experience blocks
        $current_exp = null;
        $lines = explode("\n", $exp_text);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Check for date pattern (indicates new job)
            if (preg_match('/((?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+\d{4}.*?(?:Present|Current|(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+\d{4}))/i', $line, $matches)) {
                // Save previous experience
                if ($current_exp && !empty($current_exp['company'])) {
                    $experiences[] = $current_exp;
                }

                // Start new experience
                $current_exp = array(
                    'company' => '',
                    'role' => '',
                    'location' => '',
                    'dates' => $matches[1],
                    'bullets' => array()
                );

                // Check if company/role is on same line
                $remainder = str_replace($matches[1], '', $line);
                if (!empty(trim($remainder))) {
                    // Parse company/role from remainder
                    if (preg_match('/(.+?)\s*[-–]\s*(.+)/', $remainder, $parts)) {
                        $current_exp['company'] = trim($parts[1]);
                        $current_exp['role'] = trim($parts[2]);
                    } else {
                        $current_exp['company'] = trim($remainder);
                    }
                }
            }
            // Check for bullet point
            elseif (preg_match('/^[•·▪▫◦‣⁃\-\*]\s*(.+)/', $line, $matches)) {
                if ($current_exp) {
                    $bullet = trim($matches[1]);
                    // Ensure max 180 chars
                    if (strlen($bullet) > 180) {
                        $bullet = substr($bullet, 0, 177) . '...';
                    }
                    $current_exp['bullets'][] = $bullet;
                }
            }
            // Other lines
            elseif ($current_exp) {
                // Check if it's company/role/location
                if (empty($current_exp['company'])) {
                    $current_exp['company'] = $line;
                } elseif (empty($current_exp['role'])) {
                    $current_exp['role'] = $line;
                } elseif (empty($current_exp['location']) && preg_match('/[A-Za-z]+,\s*[A-Za-z]+/', $line)) {
                    $current_exp['location'] = $line;
                } elseif (strlen($line) > 20) {
                    // Might be a bullet without marker
                    $current_exp['bullets'][] = substr($line, 0, 180);
                }
            }
        }

        // Save last experience
        if ($current_exp && !empty($current_exp['company'])) {
            $experiences[] = $current_exp;
        }

        return $experiences;
    }

    /**
     * Enhanced education extraction for PHP fallback
     */
    private function extract_education_enhanced($text)
    {
        $education = array();

        // Find education section
        $edu_start = false;
        $edu_text = '';

        $lines = explode("\n", $text);
        foreach ($lines as $i => $line) {
            if (preg_match('/^EDUCATION/i', $line)) {
                $edu_start = $i;
                break;
            }
        }

        if ($edu_start !== false) {
            for ($i = $edu_start + 1; $i < count($lines); $i++) {
                if (preg_match('/^(EXPERIENCE|SKILLS|QUALIFICATIONS|CERTIFICATIONS|PROJECTS)/i', $lines[$i])) {
                    break;
                }
                $edu_text .= $lines[$i] . "\n";
            }
        }

        if (!empty($edu_text)) {
            // Parse education entries
            $current_edu = null;
            $lines = explode("\n", $edu_text);

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                // Check for university/college
                if (preg_match('/University|College|School|Institute/i', $line)) {
                    // Save previous education
                    if ($current_edu) {
                        $education[] = $current_edu;
                    }

                    $current_edu = array(
                        'institution' => $line,
                        'degree' => '',
                        'location' => '',
                        'graduation' => '',
                        'coursework' => array(),
                        'gpa' => ''
                    );
                }
                // Check for degree
                elseif (preg_match('/B\.?S\.?c?\.?|B\.?A\.?|M\.?S\.?c?\.?|M\.?A\.?|Ph\.?D\.?|MBA|Bachelor|Master|Diploma/i', $line)) {
                    if ($current_edu) {
                        $current_edu['degree'] = $line;

                        // Check for date in same line
                        if (preg_match('/(\d{4})/', $line, $matches)) {
                            $current_edu['graduation'] = $matches[1];
                        }
                    }
                }
                // Check for GPA
                elseif (preg_match('/GPA[:\s]*([0-9.]+)/i', $line, $matches)) {
                    if ($current_edu) {
                        $current_edu['gpa'] = $matches[1];
                    }
                }
                // Check for coursework
                elseif ($current_edu && preg_match('/Modules|Coursework|Relevant/i', $line)) {
                    // Next items might be courses
                    $courses = preg_split('/[,;]/', $line);
                    foreach ($courses as $course) {
                        $course = preg_replace('/^(Modules|Coursework|Relevant)[:\s]*/i', '', trim($course));
                        if (!empty($course) && strlen($course) < 50) {
                            $current_edu['coursework'][] = $course;
                        }
                    }
                }
            }

            // Save last education
            if ($current_edu) {
                $education[] = $current_edu;
            }
        }

        return $education;
    }

    /**
     * Enhanced skills extraction for PHP fallback
     */
    private function extract_skills_enhanced($text)
    {
        $skills = array(
            'technical' => array(),
            'languages' => array(),
            'interests' => array()
        );

        // Find skills section
        if (preg_match('/SKILLS[\s&]*(?:INTERESTS)?(.+?)(?:EXPERIENCE|EDUCATION|QUALIFICATIONS|$)/is', $text, $matches)) {
            $skills_text = $matches[1];

            // Parse technical skills
            if (preg_match('/Technical\s*Skills?\s*:?\s*([^\n]+)/i', $skills_text, $tech_match)) {
                $items = preg_split('/[,;]/', $tech_match[1]);
                foreach ($items as $item) {
                    $item = trim($item);
                    if (!empty($item)) {
                        $skills['technical'][] = $item;
                    }
                }
            }

            // Parse languages
            if (preg_match('/Languages?\s*:?\s*([^\n]+)/i', $skills_text, $lang_match)) {
                $items = preg_split('/[,;]/', $lang_match[1]);
                foreach ($items as $item) {
                    $item = trim($item);
                    if (!empty($item) && !preg_match('/Programming/i', $item)) {
                        $skills['languages'][] = $item;
                    }
                }
            }

            // Parse interests
            if (preg_match('/Interests?\s*:?\s*([^\n]+)/i', $skills_text, $int_match)) {
                $items = preg_split('/[,;]/', $int_match[1]);
                foreach ($items as $item) {
                    $item = trim($item);
                    if (!empty($item)) {
                        $skills['interests'][] = $item;
                    }
                }
            }
        }

        return $skills;
    }

    /**
     * Extract summary section
     */
    private function extract_summary($text)
    {
        if (preg_match('/(SUMMARY|PROFILE|OBJECTIVE)(.+?)(?:EXPERIENCE|EDUCATION|SKILLS)/is', $text, $matches)) {
            return trim($matches[2]);
        }
        return '';
    }

    /**
     * Extract certifications
     */
    private function extract_certifications($text)
    {
        $certs = array();

        if (preg_match('/(CERTIFICATIONS?|QUALIFICATIONS?)(.+?)(?:EXPERIENCE|EDUCATION|SKILLS|$)/is', $text, $matches)) {
            $cert_text = $matches[2];
            $lines = explode("\n", $cert_text);

            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && strlen($line) > 5) {
                    // Clean bullet points
                    $line = preg_replace('/^[•·▪▫◦‣⁃\-\*]\s*/', '', $line);
                    if (!empty($line)) {
                        $certs[] = $line;
                    }
                }
            }
        }

        return $certs;
    }

    /**
     * Generate WSJ CV Display HTML for Chat
     */
    private function generate_wsj_cv_display($cv_data, $job_title, $company, $match_score)
    {
        // Use same structure as job-analysis-container for consistency
        $html = '<div class="cv-analysis-container" style="background: white; border-radius: 12px; padding: 20px; margin: 10px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">';

        // Header - Simple and clean
        $html .= '<div class="cv-analysis-header" style="border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px;">';
        $html .= '<h3 style="color: #1a472a; margin: 0 0 8px; font-size: 18px;">📊 CV Analysis Complete</h3>';
        $html .= '<p style="color: #666; margin: 0; font-size: 14px;">Tailored for <strong>' . esc_html($job_title) . '</strong> at <strong>' . esc_html($company) . '</strong></p>';
        $html .= '</div>';

        // Match Score Display
        $html .= '<div class="wsj-cv-metrics" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0;">';
        $html .= '<div style="background: linear-gradient(135deg, #f0f9f4, #fff); padding: 15px; border-radius: 8px; border-left: 4px solid #2d6a4f;">';
        $html .= '<div style="font-size: 13px; color: #666;">Match Score</div>';
        $html .= '<div style="font-size: 24px; font-weight: 700; color: #1a472a;">' . $match_score . '%</div>';
        $html .= '</div>';

        if (!empty($cv_data['experience'])) {
            $html .= '<div style="background: linear-gradient(135deg, #f0f9f4, #fff); padding: 15px; border-radius: 8px; border-left: 4px solid #2d6a4f;">';
            $html .= '<div style="font-size: 13px; color: #666;">Experience</div>';
            $html .= '<div style="font-size: 24px; font-weight: 700; color: #1a472a;">' . count($cv_data['experience']) . ' roles</div>';
            $html .= '</div>';
        }

        if (!empty($cv_data['skills'])) {
            $html .= '<div style="background: linear-gradient(135deg, #f0f9f4, #fff); padding: 15px; border-radius: 8px; border-left: 4px solid #2d6a4f;">';
            $html .= '<div style="font-size: 13px; color: #666;">Skills Matched</div>';
            $html .= '<div style="font-size: 24px; font-weight: 700; color: #1a472a;">' . count($cv_data['skills']) . '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';

        // CV Content - Simplified for chat
        $html .= '<div class="cv-content" style="margin-top: 20px;">';

        // Name and Contact
        if (!empty($cv_data['name'])) {
            $html .= '<div style="text-align: center; margin-bottom: 25px;">';
            $html .= '<h1 style="color: #1a472a; font-size: 28px; margin: 0; font-weight: 700; letter-spacing: -0.5px;">' . esc_html($cv_data['name']) . '</h1>';

            $contact_parts = array();
            if (!empty($cv_data['email'])) $contact_parts[] = esc_html($cv_data['email']);
            if (!empty($cv_data['phone'])) $contact_parts[] = esc_html($cv_data['phone']);
            if (!empty($cv_data['location'])) $contact_parts[] = esc_html($cv_data['location']);

            if (!empty($contact_parts)) {
                $html .= '<p style="color: #666; margin: 8px 0 0; font-size: 14px;">' . implode(' • ', $contact_parts) . '</p>';
            }
            $html .= '</div>';
        }

        // Experience Section
        if (!empty($cv_data['experience'])) {
            $html .= '<div style="margin-bottom: 25px;">';
            $html .= '<h3 style="color: #1a472a; font-size: 16px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #f0f0f0; padding-bottom: 8px; margin-bottom: 15px;">Experience</h3>';

            foreach ($cv_data['experience'] as $exp) {
                $html .= '<div style="margin-bottom: 20px;">';
                $html .= '<div style="margin-bottom: 8px;">';
                $html .= '<strong style="color: #1a472a; font-size: 15px;">' . esc_html($exp['role']) . '</strong>';
                $html .= ' <span style="color: #666; font-style: italic;">at</span> ';
                $html .= '<strong style="color: #2d6a4f;">' . esc_html($exp['company']) . '</strong>';
                $html .= '</div>';

                if (!empty($exp['dates'])) {
                    $html .= '<div style="color: #666; font-size: 13px; margin-bottom: 10px;">' . esc_html($exp['dates']);
                    if (!empty($exp['location'])) {
                        $html .= ' • ' . esc_html($exp['location']);
                    }
                    $html .= '</div>';
                }

                if (!empty($exp['bullets'])) {
                    $html .= '<ul style="margin: 10px 0 0 20px; padding: 0; color: #333;">';
                    foreach ($exp['bullets'] as $bullet) {
                        $html .= '<li style="margin-bottom: 6px; font-size: 14px; line-height: 1.5;">' . esc_html($bullet) . '</li>';
                    }
                    $html .= '</ul>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        // Education Section
        if (!empty($cv_data['education'])) {
            $html .= '<div style="margin-bottom: 25px;">';
            $html .= '<h3 style="color: #1a472a; font-size: 16px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #f0f0f0; padding-bottom: 8px; margin-bottom: 15px;">Education</h3>';

            foreach ($cv_data['education'] as $edu) {
                $html .= '<div style="margin-bottom: 15px;">';
                $html .= '<strong style="color: #1a472a; font-size: 15px;">' . esc_html($edu['degree']) . '</strong><br>';
                $html .= '<span style="color: #2d6a4f;">' . esc_html($edu['school']) . '</span>';
                if (!empty($edu['dates'])) {
                    $html .= ' <span style="color: #666; font-size: 13px;">• ' . esc_html($edu['dates']) . '</span>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        // Skills Section
        if (!empty($cv_data['skills'])) {
            $html .= '<div style="margin-bottom: 25px;">';
            $html .= '<h3 style="color: #1a472a; font-size: 16px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #f0f0f0; padding-bottom: 8px; margin-bottom: 15px;">Skills</h3>';
            $html .= '<div style="display: flex; flex-wrap: wrap; gap: 8px;">';
            foreach ($cv_data['skills'] as $skill) {
                $html .= '<span style="background: linear-gradient(135deg, #f0f9f4, #fff); padding: 6px 12px; border-radius: 20px; border: 1px solid #e0e0e0; font-size: 13px; color: #1a472a;">' . esc_html($skill) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>'; // End cv-content

        // Download Button - Simpler style for chat
        $html .= '<div style="text-align: center; margin-top: 20px;">';
        $html .= '<button onclick="downloadTailoredCV()" style="background: linear-gradient(135deg, #d4af37, #f4d03f); color: #1a472a; padding: 12px 28px; border: none; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; box-shadow: 0 2px 8px rgba(212, 175, 55, 0.3);">';
        $html .= '📄 Download Tailored CV';
        $html .= '</button>';
        $html .= '</div>';

        $html .= '</div>'; // End cv-analysis-container

        return $html;
    }

    /**
     * Shared accessor for the document parser service
     */
    private function get_document_parser()
    {
        if ($this->document_parser instanceof SFFC_Document_Parser) {
            return $this->document_parser;
        }

        if (!class_exists('SFFC_Document_Parser')) {
            $parser_path = plugin_dir_path(__FILE__) . 'class-document-parser.php';
            if (file_exists($parser_path)) {
                require_once $parser_path;
            }
        }

        if (class_exists('SFFC_Document_Parser')) {
            $this->document_parser = SFFC_Document_Parser::get_instance();
        }

        return $this->document_parser;
    }
}

// Initialize the system on WordPress init
add_action('init', function () {
    SFFC_Professional_CV_System::get_instance();
}, 10);
