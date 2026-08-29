<?php
/**
 * CV Parser - Comprehensive CV data extraction
 * Extracts all relevant information from uploaded CVs
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prevent duplicate class declaration
if (!class_exists('SFFC_CV_Parser')) {

class SFFC_CV_Parser {
    
    private $supported_formats = array('pdf', 'docx', 'doc', 'txt', 'rtf');
    private $max_file_size = 5242880; // 5MB
    private $document_parser = null;
    
    public function __construct() {
        add_action('init', array($this, 'ensure_upload_dir'));
    }
    
    /**
     * Ensure upload directory exists
     */
    public function ensure_upload_dir() {
        $upload_dir = wp_upload_dir();
        $cv_dir = $upload_dir['basedir'] . '/cv-uploads';
        
        if (!file_exists($cv_dir)) {
            wp_mkdir_p($cv_dir);
            
            // Add .htaccess for security
            $htaccess_content = "Options -Indexes\nDeny from all";
            file_put_contents($cv_dir . '/.htaccess', $htaccess_content);
        }
    }
    
    /**
     * Upload and parse CV file
     */
    public function upload_and_parse($file) {
        try {
            // Validate file
            $validation = $this->validate_file($file);
            if (!$validation['success']) {
                return $validation;
            }
            
            // Move uploaded file
            $upload_result = $this->save_uploaded_file($file);
            if (!$upload_result['success']) {
                return $upload_result;
            }
            
            // Parse CV content
            $parsed_data = $this->parse_cv_file($upload_result['file_path'], $file['type']);
            
            // Save to database
            $cv_id = $this->save_cv_to_database($parsed_data, $upload_result['file_path']);
            
            return array(
                'success' => true,
                'cv_id' => $cv_id,
                'parsed_data' => $parsed_data,
                'message' => 'CV uploaded and parsed successfully'
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validate_file($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return array(
                'success' => false,
                'message' => 'File upload failed'
            );
        }
        
        // Check file size
        if ($file['size'] > $this->max_file_size) {
            return array(
                'success' => false,
                'message' => 'File size exceeds 5MB limit'
            );
        }
        
        // Check file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $this->supported_formats)) {
            return array(
                'success' => false,
                'message' => 'Unsupported file format. Please upload PDF, Word, or text file'
            );
        }
        
        return array('success' => true);
    }
    
    /**
     * Save uploaded file
     */
    private function save_uploaded_file($file) {
        $upload_dir = wp_upload_dir();
        $cv_dir = $upload_dir['basedir'] . '/cv-uploads';
        
        // Generate unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = wp_unique_filename($cv_dir, sanitize_file_name($file['name']));
        $file_path = $cv_dir . '/' . $filename;
        
        // Move file
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            return array(
                'success' => false,
                'message' => 'Failed to save uploaded file'
            );
        }
        
        return array(
            'success' => true,
            'file_path' => $file_path,
            'file_name' => $filename
        );
    }
    
    /**
     * Parse CV file based on format
     */
    private function parse_cv_file($file_path, $mime_type) {
        $text = '';
        
        // Extract text based on file type
        if (strpos($mime_type, 'pdf') !== false) {
            $text = $this->parse_pdf($file_path);
        } elseif (strpos($mime_type, 'word') !== false || strpos($mime_type, 'document') !== false) {
            $text = $this->parse_word($file_path);
        } else {
            $text = file_get_contents($file_path);
        }
        
        // Extract structured data from text
        return $this->extract_cv_data($text);
    }
    
    /**
     * Extract structured data from CV text
     */
    private function extract_cv_data($text) {
        $data = array(
            'full_text' => $text,
            'personal_info' => $this->extract_personal_info($text),
            'summary' => $this->extract_summary($text),
            'experience' => $this->extract_experience($text),
            'education' => $this->extract_education($text),
            'skills' => $this->extract_skills($text),
            'certifications' => $this->extract_certifications($text),
            'projects' => $this->extract_projects($text),
            'publications' => $this->extract_publications($text),
            'languages' => $this->extract_languages($text),
            'achievements' => $this->extract_achievements($text),
            'references' => $this->extract_references($text)
        );
        
        // Calculate CV completeness score
        $data['completeness_score'] = $this->calculate_completeness($data);
        
        // Extract keywords for ATS
        $data['keywords'] = $this->extract_keywords($text);
        
        return $data;
    }
    
    /**
     * Extract personal information
     */
    private function extract_personal_info($text) {
        $info = array(
            'name' => '',
            'email' => '',
            'phone' => '',
            'location' => '',
            'linkedin' => '',
            'github' => '',
            'website' => ''
        );
        
        // Extract email
        preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $text, $email_match);
        if (!empty($email_match[0])) {
            $info['email'] = $email_match[0];
        }
        
        // Extract phone
        preg_match('/(?:\+?1[-.]?)?\(?([0-9]{3})\)?[-.]?([0-9]{3})[-.]?([0-9]{4})/', $text, $phone_match);
        if (!empty($phone_match[0])) {
            $info['phone'] = $phone_match[0];
        }
        
        // Extract LinkedIn
        preg_match('/linkedin\.co\/in\/[a-z0-9-]+/i', $text, $linkedin_match);
        if (!empty($linkedin_match[0])) {
            $info['linkedin'] = 'https://' . $linkedin_match[0];
        }
        
        // Extract GitHub
        preg_match('/github\.co\/[a-z0-9-]+/i', $text, $github_match);
        if (!empty($github_match[0])) {
            $info['github'] = 'https://' . $github_match[0];
        }
        
        // Extract name (usually at the beginning)
        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && !preg_match('/[0-9@.]/', $line) && strlen($line) < 50) {
                // Likely a name
                $info['name'] = $line;
                break;
            }
        }
        
        // Extract location
        preg_match('/([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*),\s*([A-Z]{2})/', $text, $location_match);
        if (!empty($location_match[0])) {
            $info['location'] = $location_match[0];
        }
        
        return $info;
    }
    
    /**
     * Extract professional summary
     */
    private function extract_summary($text) {
        $summary = '';
        
        // Look for summary section
        $patterns = array(
            '/(?:professional\s+)?summary\s*:?\s*([^\n]{50,500})/i',
            '/(?:career\s+)?objective\s*:?\s*([^\n]{50,500})/i',
            '/(?:profile|about\s+me)\s*:?\s*([^\n]{50,500})/i'
        );
        
        foreach ($patterns as $pattern) {
            preg_match($pattern, $text, $matches);
            if (!empty($matches[1])) {
                $summary = trim($matches[1]);
                break;
            }
        }
        
        // If no explicit summary, try to extract from beginning
        if (empty($summary)) {
            $lines = explode("\n", $text);
            foreach ($lines as $i => $line) {
                if ($i > 5 && strlen($line) > 100) {
                    $summary = trim($line);
                    break;
                }
            }
        }
        
        return $summary;
    }
    
    /**
     * Extract work experience
     */
    private function extract_experience($text) {
        $experiences = array();
        
        // Split text into sections
        $sections = preg_split('/(?:experience|employment|work\s+history)/i', $text, 2);
        if (count($sections) < 2) {
            return $experiences;
        }
        
        $experience_text = $sections[1];
        
        // Split by job entries (look for dates)
        $job_entries = preg_split('/([A-Z][a-z]+\s+\d{4}\s*[-–]\s*(?:[A-Z][a-z]+\s+\d{4}|Present))/i', 
                                  $experience_text, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        for ($i = 0; $i < count($job_entries) - 1; $i += 2) {
            if (!empty($job_entries[$i + 1])) {
                $date_range = $job_entries[$i + 1];
                $job_details = $job_entries[$i + 2] ?? '';
                
                // Extract job details
                $lines = array_filter(explode("\n", $job_details));
                if (count($lines) >= 1) {
                    $title = '';
                    $company = '';
                    $responsibilities = array();
                    
                    // First non-empty line is usually title
                    if (isset($lines[0])) {
                        $title = trim($lines[0]);
                    }
                    
                    // Second line often company
                    if (isset($lines[1])) {
                        $company = trim($lines[1]);
                    }
                    
                    // Rest are responsibilities
                    for ($j = 2; $j < count($lines); $j++) {
                        if (strlen($lines[$j]) > 10) {
                            $responsibilities[] = trim($lines[$j]);
                        }
                    }
                    
                    $experiences[] = array(
                        'title' => $title,
                        'company' => $company,
                        'date_range' => $date_range,
                        'responsibilities' => $responsibilities,
                        'achievements' => $this->extract_achievements_from_text(implode(' ', $responsibilities))
                    );
                }
            }
        }
        
        return $experiences;
    }
    
    /**
     * Extract education
     */
    private function extract_education($text) {
        $education = array();
        
        // Education patterns
        $degree_patterns = array(
            '/(?:bachelor|b\.s\.|b\.a\.|bs|ba)\s+(?:of\s+)?([^,\n]{3,50})/i',
            '/(?:master|m\.s\.|m\.a\.|mba|ms|ma)\s+(?:of\s+)?([^,\n]{3,50})/i',
            '/(?:ph\.?d\.?|doctorate)\s+(?:in\s+)?([^,\n]{3,50})/i'
        );
        
        foreach ($degree_patterns as $pattern) {
            preg_match_all($pattern, $text, $matches);
            if (!empty($matches[0])) {
                foreach ($matches[0] as $i => $degree) {
                    // Try to find institution
                    $institution = '';
                    $context = substr($text, strpos($text, $degree), 200);
                    preg_match('/(?:university|college|institute|school)\s+(?:of\s+)?([^,\n]{3,50})/i', 
                              $context, $inst_match);
                    if (!empty($inst_match[0])) {
                        $institution = $inst_match[0];
                    }
                    
                    // Extract year
                    $year = '';
                    preg_match('/(19|20)\d{2}/', $context, $year_match);
                    if (!empty($year_match[0])) {
                        $year = $year_match[0];
                    }
                    
                    $education[] = array(
                        'degree' => trim($degree),
                        'field' => isset($matches[1][$i]) ? trim($matches[1][$i]) : '',
                        'institution' => $institution,
                        'year' => $year
                    );
                }
            }
        }
        
        return $education;
    }
    
    /**
     * Extract skills with categorization
     */
    private function extract_skills($text) {
        $skills = array(
            'technical' => array(),
            'soft' => array(),
            'languages' => array(),
            'frameworks' => array(),
            'tools' => array()
        );
        
        // Look for skills section
        $sections = preg_split('/(?:skills|competencies|expertise)/i', $text, 2);
        if (count($sections) >= 2) {
            $skills_text = substr($sections[1], 0, 1000); // Limit search area
            
            // Common technical skills
            $tech_skills = array(
                'Python', 'Java', 'JavaScript', 'C\+\+', 'C#', 'PHP', 'Ruby', 'Go',
                'SQL', 'HTML', 'CSS', 'React', 'Angular', 'Vue', 'Node.js', 'Django',
                'Spring', 'Laravel', 'Express', 'MongoDB', 'PostgreSQL', 'MySQL',
                'AWS', 'Azure', 'GCP', 'Docker', 'Kubernetes', 'Git', 'Jenkins'
            );
            
            foreach ($tech_skills as $skill) {
                if (preg_match('/' . preg_quote($skill, '/') . '/i', $skills_text)) {
                    $skills['technical'][] = $skill;
                }
            }
            
            // Soft skills
            $soft_patterns = array(
                'leadership', 'communication', 'teamwork', 'problem-solving',
                'analytical', 'creative', 'organized', 'detail-oriented'
            );
            
            foreach ($soft_patterns as $skill) {
                if (stripos($skills_text, $skill) !== false) {
                    $skills['soft'][] = ucfirst($skill);
                }
            }
        }
        
        // Deduplicate
        foreach ($skills as $category => $skill_list) {
            $skills[$category] = array_unique($skill_list);
        }
        
        return $skills;
    }
    
    /**
     * Extract certifications
     */
    private function extract_certifications($text) {
        $certifications = array();
        
        // Common certification patterns
        $patterns = array(
            '/(?:certified|certification)\s+([A-Z][A-Za-z\s\+\-]{3,50})/i',
            '/([A-Z]{2,}[A-Z\+\-]*(?:\s+[A-Z]{2,})*)/'
        );
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $cert) {
                    if (strlen($cert) > 3 && strlen($cert) < 50) {
                        $certifications[] = trim($cert);
                    }
                }
            }
        }
        
        return array_unique($certifications);
    }
    
    /**
     * Helper methods
     */
    private function extract_achievements_from_text($text) {
        $achievements = array();
        
        // Look for quantified achievements
        $patterns = array(
            '/(increased|improved|reduced|saved|generated|managed)[^.]{10,100}\d+%?/i',
            '/\$\d+[KMB]?[^.]{5,100}/i',
            '/(led|managed|directed)[^.]{10,100}/i'
        );
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches);
            if (!empty($matches[0])) {
                $achievements = array_merge($achievements, $matches[0]);
            }
        }
        
        return array_slice($achievements, 0, 5); // Limit to 5
    }
    
    /**
     * Calculate CV completeness score
     */
    private function calculate_completeness($data) {
        $score = 0;
        $max_score = 100;
        
        // Check each section
        if (!empty($data['personal_info']['name'])) $score += 10;
        if (!empty($data['personal_info']['email'])) $score += 10;
        if (!empty($data['summary'])) $score += 15;
        if (!empty($data['experience'])) $score += 25;
        if (!empty($data['education'])) $score += 15;
        if (!empty($data['skills']['technical'])) $score += 15;
        if (!empty($data['certifications'])) $score += 5;
        if (!empty($data['achievements'])) $score += 5;
        
        return min($score, $max_score);
    }
    
    /**
     * Extract keywords for ATS
     */
    private function extract_keywords($text) {
        // Extract important keywords
        $keywords = array();
        
        // Technical terms
        preg_match_all('/[A-Z][a-z]+(?:[A-Z][a-z]+)+/', $text, $camel_case);
        $keywords = array_merge($keywords, $camel_case[0]);
        
        // Acronyms
        preg_match_all('/\b[A-Z]{2,}\b/', $text, $acronyms);
        $keywords = array_merge($keywords, $acronyms[0]);
        
        // Skills and technologies
        $tech_keywords = array(
            'Agile', 'Scrum', 'DevOps', 'CI/CD', 'Machine Learning',
            'Data Analysis', 'Project Management', 'Cloud Computing'
        );
        
        foreach ($tech_keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $keywords[] = $keyword;
            }
        }
        
        return array_unique($keywords);
    }
    
    /**
     * Parse PDF file
     */
    private function parse_pdf($file_path) {
        $parser = $this->get_document_parser();

        if ($parser instanceof SFFC_Document_Parser) {
            $content = $parser->extract_pdf_text($file_path);
            if (!empty($content)) {
                return $content;
            }
        }

        if (function_exists('shell_exec')) {
            $command = sprintf('pdftotext -layout %s - 2>&1', escapeshellarg($file_path));
            $content = shell_exec($command);
            if (!empty($content)) {
                return $content;
            }
        }

        $fallback = @file_get_contents($file_path);
        return $fallback !== false ? $fallback : '';
    }

    /**
     * Parse Word document
     */
    private function parse_word($file_path) {
        $parser = $this->get_document_parser();
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        if ($parser instanceof SFFC_Document_Parser) {
            if ($ext === 'docx') {
                $content = $parser->extract_docx_text($file_path);
            } else {
                $content = $parser->extract_doc_text($file_path);
            }

            if (!empty($content)) {
                return $content;
            }
        }

        if ($ext === 'doc' && function_exists('shell_exec')) {
            $command = sprintf('catdoc %s 2>&1', escapeshellarg($file_path));
            $content = shell_exec($command);
            if (!empty($content)) {
                return $content;
            }
        }

        if ($ext === 'docx') {
            $zip = new ZipArchive();
            if ($zip->open($file_path) === true) {
                $content = $zip->getFromName('word/document.xml');
                $zip->close();
                if ($content !== false) {
                    return strip_tags($content);
                }
            }
        }

        $fallback = @file_get_contents($file_path);
        return $fallback !== false ? $fallback : '';
    }

    /**
     * Shared access to the document parser singleton
     */
    private function get_document_parser() {
        if ($this->document_parser instanceof SFFC_Document_Parser) {
            return $this->document_parser;
        }

        if (!class_exists('SFFC_Document_Parser')) {
            $parser_path = __DIR__ . '/class-document-parser.php';
            if (file_exists($parser_path)) {
                require_once $parser_path;
            }
        }

        if (class_exists('SFFC_Document_Parser')) {
            $this->document_parser = SFFC_Document_Parser::get_instance();
        }

        return $this->document_parser;
    }

    /**
     * Save CV to database
     */
    private function save_cv_to_database($parsed_data, $file_path) {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_cv_master';
        
        $data = array(
            'user_id' => get_current_user_id(),
            'cv_name' => $parsed_data['personal_info']['name'] . ' CV',
            'original_file_path' => $file_path,
            'parsed_data' => json_encode($parsed_data),
            'extracted_text' => $parsed_data['full_text'],
            'skills' => json_encode($parsed_data['skills']),
            'experience' => json_encode($parsed_data['experience']),
            'education' => json_encode($parsed_data['education'])
        );
        
        $wpdb->insert($table, $data);
        return $wpdb->insert_id;
    }
    
    /**
     * Get parsed CV from database
     */
    public function get_parsed_cv($cv_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_cv_master';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $cv_id
        ), ARRAY_A);
        
        if ($result) {
            $result['parsed_data'] = json_decode($result['parsed_data'], true);
            $result['skills'] = json_decode($result['skills'], true);
            $result['experience'] = json_decode($result['experience'], true);
            $result['education'] = json_decode($result['education'], true);
            return $result;
        }
        
        // Fallback: Try the cv_uploads table from CV Upload Handler
        $uploads_table = $wpdb->prefix . 'sffc_cv_uploads';
        $parsed_table = $wpdb->prefix . 'sffc_parsed_profiles';
        
        // Check if the upload exists
        $upload = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $uploads_table WHERE id = %d",
            $cv_id
        ), ARRAY_A);
        
        if ($upload) {
            // Check for parsed data
            $parsed = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $parsed_table WHERE cv_upload_id = %d",
                $cv_id
            ), ARRAY_A);
            
            // Return a compatible structure
            $cv_data = array(
                'id' => $upload['id'],
                'user_id' => $upload['user_id'],
                'filename' => $upload['file_name'],
                'status' => $upload['parse_status'],
                'parsed_data' => array(
                    'personal_info' => array('name' => 'User', 'email' => ''),
                    'skills' => array('technical' => array(), 'soft' => array()),
                    'experience' => array(),
                    'education' => array()
                )
            );
            
            if ($parsed && $parsed['profile_data']) {
                $profile = json_decode($parsed['profile_data'], true);
                if ($profile) {
                    $cv_data['parsed_data'] = $profile;
                }
                if ($parsed['extracted_text']) {
                    $cv_data['raw_text'] = $parsed['extracted_text'];
                }
            }
            
            return $cv_data;
        }
        
        return null;
    }
}

} // End if (!class_exists('SFFC_CV_Parser'))
