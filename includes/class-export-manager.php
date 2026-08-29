<?php
/**
 * Export Manager - PDF and Word Document Generation
 * Handles CV export to various formats
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include vendor autoloader
require_once dirname(__FILE__) . '/vendor-autoload.php';

// Prevent duplicate class declaration
if (!class_exists('SFFC_Export_Manager')) {

class SFFC_Export_Manager {
    
    private $export_dir;
    private $temp_dir;
    private $generated_dir;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->export_dir = plugin_dir_path(dirname(__FILE__)) . 'exports/';
        $this->temp_dir = $this->export_dir . 'temp/';
        $this->generated_dir = $this->export_dir . 'generated/';
        
        // Ensure directories exist
        $this->ensure_directories();
    }
    
    /**
     * Ensure export directories exist
     */
    private function ensure_directories() {
        $dirs = [$this->export_dir, $this->temp_dir, $this->generated_dir];
        
        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
        }
    }
    
    /**
     * Main export function
     */
    public function export($version_id, $format = 'pdf') {
        try {
            // Check if libraries are loaded
            if (!defined('SFFC_LIBRARIES_LOADED') || !SFFC_LIBRARIES_LOADED) {
                throw new Exception('Export libraries not installed. Please run install-libraries.sh');
            }
            
            // Get CV version data
            $cv_data = $this->get_cv_version_data($version_id);
            if (!$cv_data) {
                throw new Exception('CV version not found');
            }
            
            // Parse tailored content
            $content = json_decode($cv_data->tailored_content, true);
            if (!$content) {
                $content = $this->get_original_cv_data($cv_data->master_cv_id);
            }
            
            // Generate document based on format
            $result = null;
            switch (strtolower($format)) {
                case 'pdf':
                    $result = $this->generate_pdf($content, $cv_data);
                    break;
                    
                case 'word':
                case 'docx':
                    $result = $this->generate_word($content, $cv_data);
                    break;
                    
                case 'ats':
                    $result = $this->generate_ats_optimized($content, $cv_data);
                    break;
                    
                default:
                    throw new Exception('Unsupported format: ' . $format);
            }
            
            // Update export count
            $this->update_export_count($version_id);
            
            return $result;
            
        } catch (Exception $e) {
            error_log('Export Manager Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate PDF document
     */
    private function generate_pdf($content, $cv_data) {
        // Check if TCPDF is available
        if (!class_exists('TCPDF')) {
            throw new Exception('TCPDF library not found. Please run install-libraries.sh');
        }
        
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('SFFC Career Platform');
        $pdf->SetAuthor($content['personal_info']['name'] ?? 'Candidate');
        $pdf->SetTitle('Tailored CV - ' . ($cv_data->job_title ?? 'Position'));
        $pdf->SetSubject('Professional CV');
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Set margins
        $pdf->SetMargins(20, 15, 20);
        $pdf->SetAutoPageBreak(true, 15);
        
        // Add page
        $pdf->AddPage();
        
        // Set fonts
        $pdf->SetFont('helvetica', '', 11);
        
        // Build CV content
        $html = $this->build_pdf_html($content, $cv_data);
        
        // Write HTML
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Generate filename
        $filename = $this->generate_filename($content, 'pdf');
        $filepath = $this->generated_dir . $filename;
        
        // Save PDF
        $pdf->Output($filepath, 'F');
        
        // Generate download URL
        $download_url = $this->create_download_url($filename);
        
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'download_url' => $download_url,
            'format' => 'pdf'
        ];
    }
    
    /**
     * Generate Word document
     */
    private function generate_word($content, $cv_data) {
        // Check if PHPWord is available
        if (!class_exists('\\PhpOffice\\PhpWord\\PhpWord')) {
            throw new Exception('PHPWord library not found. Please run install-libraries.sh');
        }
        
        // Create new Word document
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        
        // Set document properties
        $properties = $phpWord->getDocInfo();
        $properties->setCreator('SFFC Career Platform');
        $properties->setTitle('Tailored CV - ' . ($cv_data->job_title ?? 'Position'));
        $properties->setSubject('Professional CV');
        $properties->setDescription('Tailored CV for ' . ($cv_data->company_name ?? 'Company'));
        
        // Add styles
        $this->add_word_styles($phpWord);
        
        // Add main section
        $section = $phpWord->addSection([
            'marginTop' => 1440,    // 1 inch
            'marginBottom' => 1440,
            'marginLeft' => 1440,
            'marginRight' => 1440
        ]);
        
        // Build CV content
        $this->build_word_content($section, $content, $cv_data);
        
        // Generate filename
        $filename = $this->generate_filename($content, 'docx');
        $filepath = $this->generated_dir . $filename;
        
        // Save document
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($filepath);
        
        // Generate download URL
        $download_url = $this->create_download_url($filename);
        
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'download_url' => $download_url,
            'format' => 'docx'
        ];
    }
    
    /**
     * Generate ATS-optimized version
     */
    private function generate_ats_optimized($content, $cv_data) {
        // ATS-optimized is a simple text-based PDF with no formatting
        if (!class_exists('TCPDF')) {
            throw new Exception('TCPDF library not found');
        }
        
        $pdf = new TCPDF();
        $pdf->SetCreator('SFFC Career Platform');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();
        $pdf->SetFont('courier', '', 11);
        
        // Build ATS-friendly content (plain text, no tables or graphics)
        $text = $this->build_ats_text($content, $cv_data);
        
        // Use MultiCell for plain text
        $pdf->MultiCell(0, 5, $text, 0, 'L', false);
        
        // Generate filename
        $filename = $this->generate_filename($content, 'ats.pdf');
        $filepath = $this->generated_dir . $filename;
        
        // Save PDF
        $pdf->Output($filepath, 'F');
        
        // Generate download URL
        $download_url = $this->create_download_url($filename);
        
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'download_url' => $download_url,
            'format' => 'ats_pdf'
        ];
    }
    
    /**
     * Build HTML content for PDF
     */
    private function build_pdf_html($content, $cv_data) {
        $html = '<style>
            h1 { color: #132D51; font-size: 24px; margin-bottom: 10px; }
            h2 { color: #2D6A4F; font-size: 18px; margin-top: 20px; margin-bottom: 10px; border-bottom: 2px solid #2D6A4F; padding-bottom: 5px; }
            h3 { color: #132D51; font-size: 14px; margin-top: 15px; margin-bottom: 5px; }
            p { line-height: 1.6; margin-bottom: 10px; }
            ul { margin-left: 20px; margin-bottom: 10px; }
            li { margin-bottom: 5px; }
            .contact-info { font-size: 11px; color: #666; margin-bottom: 20px; }
            .skills-list { margin: 10px 0; }
            .skill-item { display: inline-block; background: #F0F0F0; padding: 3px 10px; margin: 3px; border-radius: 3px; font-size: 10px; }
        </style>';
        
        // Header with name
        $name = $content['personal_info']['name'] ?? 'Professional';
        $html .= '<h1>' . htmlspecialchars($name) . '</h1>';
        
        // Contact information
        $html .= '<div class="contact-info">';
        if (!empty($content['personal_info']['email'])) {
            $html .= htmlspecialchars($content['personal_info']['email']) . ' | ';
        }
        if (!empty($content['personal_info']['phone'])) {
            $html .= htmlspecialchars($content['personal_info']['phone']) . ' | ';
        }
        if (!empty($content['personal_info']['location'])) {
            $html .= htmlspecialchars($content['personal_info']['location']);
        }
        $html .= '</div>';
        
        // Professional summary
        if (!empty($content['summary'])) {
            $html .= '<h2>Professional Summary</h2>';
            $html .= '<p>' . nl2br(htmlspecialchars($content['summary'])) . '</p>';
        }
        
        // Experience
        if (!empty($content['experience']) && is_array($content['experience'])) {
            $html .= '<h2>Professional Experience</h2>';
            foreach ($content['experience'] as $job) {
                $html .= '<h3>' . htmlspecialchars($job['title'] ?? '') . ' - ' . htmlspecialchars($job['company'] ?? '') . '</h3>';
                $html .= '<p style="color: #666; font-size: 10px;">' . htmlspecialchars($job['dates'] ?? '') . '</p>';
                if (!empty($job['description'])) {
                    $html .= '<p>' . nl2br(htmlspecialchars($job['description'])) . '</p>';
                }
                if (!empty($job['achievements']) && is_array($job['achievements'])) {
                    $html .= '<ul>';
                    foreach ($job['achievements'] as $achievement) {
                        $html .= '<li>' . htmlspecialchars($achievement) . '</li>';
                    }
                    $html .= '</ul>';
                }
            }
        }
        
        // Education
        if (!empty($content['education']) && is_array($content['education'])) {
            $html .= '<h2>Education</h2>';
            foreach ($content['education'] as $edu) {
                $html .= '<h3>' . htmlspecialchars($edu['degree'] ?? '') . ' - ' . htmlspecialchars($edu['school'] ?? '') . '</h3>';
                $html .= '<p style="color: #666; font-size: 10px;">' . htmlspecialchars($edu['dates'] ?? '') . '</p>';
            }
        }
        
        // Skills
        if (!empty($content['skills']) && is_array($content['skills'])) {
            $html .= '<h2>Skills</h2>';
            $html .= '<div class="skills-list">';
            foreach ($content['skills'] as $skill) {
                $html .= '<span class="skill-item">' . htmlspecialchars($skill) . '</span> ';
            }
            $html .= '</div>';
        }
        
        // Certifications
        if (!empty($content['certifications']) && is_array($content['certifications'])) {
            $html .= '<h2>Certifications</h2>';
            $html .= '<ul>';
            foreach ($content['certifications'] as $cert) {
                $html .= '<li>' . htmlspecialchars($cert) . '</li>';
            }
            $html .= '</ul>';
        }
        
        return $html;
    }
    
    /**
     * Build Word document content
     */
    private function build_word_content($section, $content, $cv_data) {
        // Add name as title
        $name = $content['personal_info']['name'] ?? 'Professional';
        $section->addText($name, 'heading1');
        
        // Contact information
        $contactText = '';
        if (!empty($content['personal_info']['email'])) {
            $contactText .= $content['personal_info']['email'] . ' | ';
        }
        if (!empty($content['personal_info']['phone'])) {
            $contactText .= $content['personal_info']['phone'] . ' | ';
        }
        if (!empty($content['personal_info']['location'])) {
            $contactText .= $content['personal_info']['location'];
        }
        $section->addText($contactText, 'contact');
        $section->addTextBreak();
        
        // Professional summary
        if (!empty($content['summary'])) {
            $section->addText('Professional Summary', 'heading2');
            $section->addText($content['summary'], 'normal');
            $section->addTextBreak();
        }
        
        // Experience
        if (!empty($content['experience']) && is_array($content['experience'])) {
            $section->addText('Professional Experience', 'heading2');
            
            foreach ($content['experience'] as $job) {
                $jobTitle = ($job['title'] ?? '') . ' - ' . ($job['company'] ?? '');
                $section->addText($jobTitle, 'heading3');
                $section->addText($job['dates'] ?? '', 'date');
                
                if (!empty($job['description'])) {
                    $section->addText($job['description'], 'normal');
                }
                
                if (!empty($job['achievements']) && is_array($job['achievements'])) {
                    foreach ($job['achievements'] as $achievement) {
                        $section->addListItem($achievement, 0, 'normal');
                    }
                }
                $section->addTextBreak();
            }
        }
        
        // Education
        if (!empty($content['education']) && is_array($content['education'])) {
            $section->addText('Education', 'heading2');
            
            foreach ($content['education'] as $edu) {
                $eduTitle = ($edu['degree'] ?? '') . ' - ' . ($edu['school'] ?? '');
                $section->addText($eduTitle, 'heading3');
                $section->addText($edu['dates'] ?? '', 'date');
                $section->addTextBreak();
            }
        }
        
        // Skills
        if (!empty($content['skills']) && is_array($content['skills'])) {
            $section->addText('Skills', 'heading2');
            $skillsText = implode(' • ', $content['skills']);
            $section->addText($skillsText, 'normal');
            $section->addTextBreak();
        }
        
        // Certifications
        if (!empty($content['certifications']) && is_array($content['certifications'])) {
            $section->addText('Certifications', 'heading2');
            foreach ($content['certifications'] as $cert) {
                $section->addListItem($cert, 0, 'normal');
            }
        }
    }
    
    /**
     * Add Word document styles
     */
    private function add_word_styles($phpWord) {
        // Heading 1 - Name
        $phpWord->addFontStyle('heading1', [
            'name' => 'Arial',
            'size' => 20,
            'color' => '132D51',
            'bold' => true
        ]);
        
        // Heading 2 - Section headers
        $phpWord->addFontStyle('heading2', [
            'name' => 'Arial',
            'size' => 14,
            'color' => '2D6A4F',
            'bold' => true
        ]);
        
        // Heading 3 - Job titles
        $phpWord->addFontStyle('heading3', [
            'name' => 'Arial',
            'size' => 12,
            'color' => '132D51',
            'bold' => true
        ]);
        
        // Normal text
        $phpWord->addFontStyle('normal', [
            'name' => 'Arial',
            'size' => 11
        ]);
        
        // Contact info
        $phpWord->addFontStyle('contact', [
            'name' => 'Arial',
            'size' => 10,
            'color' => '666666'
        ]);
        
        // Date text
        $phpWord->addFontStyle('date', [
            'name' => 'Arial',
            'size' => 10,
            'color' => '666666',
            'italic' => true
        ]);
    }
    
    /**
     * Build ATS-optimized text
     */
    private function build_ats_text($content, $cv_data) {
        $text = '';
        
        // Name
        $text .= strtoupper($content['personal_info']['name'] ?? 'PROFESSIONAL') . "\n";
        $text .= str_repeat('-', 50) . "\n\n";
        
        // Contact
        if (!empty($content['personal_info']['email'])) {
            $text .= "Email: " . $content['personal_info']['email'] . "\n";
        }
        if (!empty($content['personal_info']['phone'])) {
            $text .= "Phone: " . $content['personal_info']['phone'] . "\n";
        }
        if (!empty($content['personal_info']['location'])) {
            $text .= "Location: " . $content['personal_info']['location'] . "\n";
        }
        if (!empty($content['personal_info']['linkedin'])) {
            $text .= "LinkedIn: " . $content['personal_info']['linkedin'] . "\n";
        }
        $text .= "\n";
        
        // Summary
        if (!empty($content['summary'])) {
            $text .= "PROFESSIONAL SUMMARY\n";
            $text .= str_repeat('-', 30) . "\n";
            $text .= $content['summary'] . "\n\n";
        }
        
        // Experience
        if (!empty($content['experience']) && is_array($content['experience'])) {
            $text .= "PROFESSIONAL EXPERIENCE\n";
            $text .= str_repeat('-', 30) . "\n";
            
            foreach ($content['experience'] as $job) {
                $text .= strtoupper($job['title'] ?? '') . "\n";
                $text .= ($job['company'] ?? '') . " | " . ($job['dates'] ?? '') . "\n";
                
                if (!empty($job['description'])) {
                    $text .= $job['description'] . "\n";
                }
                
                if (!empty($job['achievements']) && is_array($job['achievements'])) {
                    foreach ($job['achievements'] as $achievement) {
                        $text .= "• " . $achievement . "\n";
                    }
                }
                $text .= "\n";
            }
        }
        
        // Education
        if (!empty($content['education']) && is_array($content['education'])) {
            $text .= "EDUCATION\n";
            $text .= str_repeat('-', 30) . "\n";
            
            foreach ($content['education'] as $edu) {
                $text .= strtoupper($edu['degree'] ?? '') . "\n";
                $text .= ($edu['school'] ?? '') . " | " . ($edu['dates'] ?? '') . "\n\n";
            }
        }
        
        // Skills
        if (!empty($content['skills']) && is_array($content['skills'])) {
            $text .= "SKILLS\n";
            $text .= str_repeat('-', 30) . "\n";
            $text .= implode(', ', $content['skills']) . "\n\n";
        }
        
        // Keywords (for ATS)
        if (!empty($cv_data->keywords_added)) {
            $keywords = json_decode($cv_data->keywords_added, true);
            if (is_array($keywords) && !empty($keywords)) {
                $text .= "KEY COMPETENCIES\n";
                $text .= str_repeat('-', 30) . "\n";
                $text .= implode(', ', $keywords) . "\n\n";
            }
        }
        
        // Certifications
        if (!empty($content['certifications']) && is_array($content['certifications'])) {
            $text .= "CERTIFICATIONS\n";
            $text .= str_repeat('-', 30) . "\n";
            foreach ($content['certifications'] as $cert) {
                $text .= "• " . $cert . "\n";
            }
        }
        
        return $text;
    }
    
    /**
     * Generate unique filename
     */
    private function generate_filename($content, $format) {
        $name = $content['personal_info']['name'] ?? 'CV';
        $name = sanitize_file_name($name);
        $timestamp = date('Ymd_His');
        $random = substr(md5(uniqid()), 0, 6);
        
        $extension = ($format === 'word' || $format === 'docx') ? 'docx' : 'pdf';
        
        return sprintf('%s_%s_%s.%s', $name, $timestamp, $random, $extension);
    }
    
    /**
     * Create secure download URL
     */
    private function create_download_url($filename) {
        // Create download nonce
        $nonce = wp_create_nonce('download_cv_' . $filename);
        
        // Build URL
        $url = add_query_arg([
            'action' => 'sffc_download_cv',
            'file' => $filename,
            'nonce' => $nonce
        ], admin_url('admin-ajax.php'));
        
        return $url;
    }
    
    /**
     * Get CV version data from database
     */
    private function get_cv_version_data($version_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_cv_versions';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $version_id
        ));
    }
    
    /**
     * Get original CV data
     */
    private function get_original_cv_data($master_cv_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_cv_master';
        
        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT parsed_data FROM $table WHERE id = %d",
            $master_cv_id
        ));
        
        if ($data && $data->parsed_data) {
            return json_decode($data->parsed_data, true);
        }
        
        return [];
    }
    
    /**
     * Update export count
     */
    private function update_export_count($version_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_cv_versions';
        
        $wpdb->query($wpdb->prepare(
            "UPDATE $table 
             SET exported_count = exported_count + 1,
                 last_exported = CURRENT_TIMESTAMP
             WHERE id = %d",
            $version_id
        ));
    }
    
    /**
     * Handle file download
     */
    public static function handle_download() {
        // Verify nonce
        if (!isset($_GET['nonce']) || !isset($_GET['file'])) {
            wp_die('Invalid request');
        }
        
        $filename = sanitize_file_name($_GET['file']);
        
        if (!wp_verify_nonce($_GET['nonce'], 'download_cv_' . $filename)) {
            wp_die('Security check failed');
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_die('Please log in to download');
        }
        
        // Build file path
        $filepath = plugin_dir_path(dirname(__FILE__)) . 'exports/generated/' . $filename;
        
        if (!file_exists($filepath)) {
            wp_die('File not found');
        }
        
        // Get mime type
        $mime_type = mime_content_type($filepath);
        if (!$mime_type) {
            if (strpos($filename, '.pdf') !== false) {
                $mime_type = 'application/pdf';
            } elseif (strpos($filename, '.docx') !== false) {
                $mime_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            } else {
                $mime_type = 'application/octet-stream';
            }
        }
        
        // Send download headers
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output file
        readfile($filepath);
        exit;
    }
}

} // End if (!class_exists('SFFC_Export_Manager'))

// Register download handler
add_action('wp_ajax_sffc_download_cv', ['SFFC_Export_Manager', 'handle_download']);
add_action('wp_ajax_nopriv_sffc_download_cv', ['SFFC_Export_Manager', 'handle_download']);