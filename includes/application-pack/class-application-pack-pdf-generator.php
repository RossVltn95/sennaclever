<?php
/**
 * Application Pack PDF Generator
 *
 * Generates consulting-grade PDFs for Application Pack documents using TCPDF.
 *
 * @package SFFC_Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/class-application-pack-design-system.php';

class SFFC_Application_Pack_PDF_Generator {

    /**
     * TCPDF instance
     */
    private $pdf;

    /**
     * Design system reference
     */
    private $design;

    /**
     * Current Y position
     */
    private $y;

    /**
     * Constructor
     */
    public function __construct() {
        $this->design = new SFFC_Application_Pack_Design_System();
        $this->load_tcpdf();
    }

    /**
     * Load TCPDF library
     */
    private function load_tcpdf() {
        $tcpdf_path = SFFC_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php';

        if (!file_exists($tcpdf_path)) {
            throw new Exception('TCPDF library not found.');
        }

        require_once $tcpdf_path;
    }

    /**
     * Initialize PDF document
     */
    private function init_pdf($title = 'Application Pack Document') {
        $this->pdf = new TCPDF('P', 'pt', 'A4', true, 'UTF-8', false);

        // Set document properties
        $this->pdf->SetCreator('MENA Careers Finance');
        $this->pdf->SetAuthor('MENA Careers Finance');
        $this->pdf->SetTitle($title);

        // Remove default header/footer
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);

        // Set margins
        $margins = SFFC_Application_Pack_Design_System::SPACING;
        $this->pdf->SetMargins($margins['margin_page'], $margins['margin_page'], $margins['margin_page']);

        // Set auto page breaks
        $this->pdf->SetAutoPageBreak(true, $margins['margin_page']);

        // Add first page
        $this->pdf->AddPage();

        // Set initial position
        $this->y = $margins['margin_page'];
    }

    /**
     * Generate CV PDF
     */
    public function generate_cv($cv_content, $job_data, $user_name) {
        $this->init_pdf($user_name . ' - CV - ' . $job_data['title']);

        $colors = SFFC_Application_Pack_Design_System::COLORS;
        $typo = SFFC_Application_Pack_Design_System::TYPOGRAPHY;
        $space = SFFC_Application_Pack_Design_System::SPACING;

        // Header Section
        $this->render_cv_header($cv_content['header']);

        // Executive Summary
        $this->render_section_header('Executive Profile');
        $this->render_executive_box($cv_content['executive_summary']);

        // Two Column: Qualifications & Metrics
        $this->render_two_column_section(
            'Key Qualifications',
            $cv_content['key_qualifications'],
            'Core Metrics',
            $cv_content['metrics']
        );

        // Experience Section
        $this->render_section_header('Professional Experience');
        $this->render_experience($cv_content['experience']);

        // Skills Section
        $this->render_section_header('Skills & Expertise');
        $this->render_skills($cv_content['skills']);

        // Education Section
        if (!empty($cv_content['education'])) {
            $this->render_section_header('Education');
            $this->render_education($cv_content['education']);
        }

        // Footer
        $this->render_footer($job_data);

        return $this->pdf->Output('', 'S'); // Return as string
    }

    /**
     * Render CV header
     */
    private function render_cv_header($header) {
        $colors = SFFC_Application_Pack_Design_System::COLORS;
        $typo = SFFC_Application_Pack_Design_System::TYPOGRAPHY;

        // Name
        $this->pdf->SetFont('helvetica', 'B', $typo['size_h1']);
        $this->pdf->SetTextColorArray($this->design::get_color('primary'));
        $this->pdf->Cell(0, 24, $header['name'], 0, 1, 'L');

        // Subtitle (headline + contact)
        $this->pdf->SetFont('helvetica', '', $typo['size_body']);
        $this->pdf->SetTextColorArray($this->design::get_color('text_medium'));

        $contact_parts = array();
        $contact_parts[] = $header['headline'];
        if (!empty($header['contact']['location'])) {
            $contact_parts[] = $header['contact']['location'];
        }
        if (!empty($header['contact']['email'])) {
            $contact_parts[] = $header['contact']['email'];
        }

        $this->pdf->Cell(0, 14, implode('  |  ', $contact_parts), 0, 1, 'L');
        $this->pdf->Ln(16);
    }

    /**
     * Render section header
     */
    private function render_section_header($text) {
        $colors = SFFC_Application_Pack_Design_System::COLORS;
        $typo = SFFC_Application_Pack_Design_System::TYPOGRAPHY;

        $this->pdf->Ln(8);

        // Section title
        $this->pdf->SetFont('helvetica', 'B', $typo['size_h2']);
        $this->pdf->SetTextColorArray($this->design::get_color('primary'));
        $this->pdf->Cell(0, 18, strtoupper($text), 0, 1, 'L');

        // Gold underline
        $this->pdf->SetDrawColorArray($this->design::get_color('secondary'));
        $this->pdf->SetLineWidth(1.5);
        $y = $this->pdf->GetY();
        $this->pdf->Line(50, $y, 150, $y);

        $this->pdf->Ln(8);
    }

    /**
     * Render executive summary box
     */
    private function render_executive_box($text) {
        $colors = SFFC_Application_Pack_Design_System::COLORS;
        $typo = SFFC_Application_Pack_Design_System::TYPOGRAPHY;

        $x = $this->pdf->GetX();
        $y = $this->pdf->GetY();

        // Background box
        $this->pdf->SetFillColorArray($this->design::get_color('background_alt'));
        $this->pdf->RoundedRect($x, $y, 495, 60, 3, '1111', 'F');

        // Gold left border
        $this->pdf->SetFillColorArray($this->design::get_color('secondary'));
        $this->pdf->Rect($x, $y, 3, 60, 'F');

        // Text inside box
        $this->pdf->SetXY($x + 16, $y + 12);
        $this->pdf->SetFont('helvetica', '', $typo['size_body']);
        $this->pdf->SetTextColorArray($this->design::get_color('text_dark'));
        $this->pdf->MultiCell(463, 36, $text, 0, 'L', false);

        $this->pdf->SetY($y + 70);
    }

    /**
     * Render two column section
     */
    private function render_two_column_section($left_title, $left_items, $right_title, $right_items) {
        $typo = SFFC_Application_Pack_Design_System::TYPOGRAPHY;
        $start_y = $this->pdf->GetY();

        // Left column
        $this->pdf->SetX(50);
        $this->pdf->SetFont('helvetica', 'B', $typo['size_h3']);
        $this->pdf->SetTextColorArray($this->design::get_color('text_dark'));
        $this->pdf->Cell(230, 16, $left_title, 0, 1, 'L');

        $this->pdf->SetFont('helvetica', '', $typo['size_body']);
        $this->pdf->SetTextColorArray($this->design::get_color('text_dark'));

        foreach ($left_items as $item) {
            $this->pdf->SetX(50);
            $this->pdf->Cell(10, 14, chr(149), 0, 0, 'L'); // Bullet
            $this->pdf->Cell(220, 14, $item, 0, 1, 'L');
        }

        $left_end_y = $this->pdf->GetY();

        // Right column
        $this->pdf->SetXY(295, $start_y);
        $this->pdf->SetFont('helvetica', 'B', $typo['size_h3']);
        $this->pdf->Cell(250, 16, $right_title, 0, 1, 'L');

        $this->pdf->SetX(295);
        foreach ($right_items as $metric) {
            if (is_array($metric)) {
                $this->pdf->SetFont('helvetica', 'B', 14);
                $this->pdf->SetTextColorArray($this->design::get_color('primary'));
                $this->pdf->Cell(80, 18, $metric['value'], 0, 0, 'L');

                $this->pdf->SetFont('helvetica', '', $typo['size_small']);
                $this->pdf->SetTextColorArray($this->design::get_color('text_light'));
                $this->pdf->Cell(170, 18, strtoupper($metric['label']), 0, 1, 'L');
                $this->pdf->SetX(295);
            } else {
                $this->pdf->SetFont('helvetica', '', $typo['size_body']);
                $this->pdf->SetTextColorArray($this->design::get_color('text_dark'));
                $this->pdf->Cell(10, 14, chr(149), 0, 0, 'L');
                $this->pdf->Cell(240, 14, $metric, 0, 1, 'L');
                $this->pdf->SetX(295);
            }
        }

        // Move to end of tallest column
        $this->pdf->SetY(max($left_end_y, $this->pdf->GetY()) + 8);
    }

    /**
     * Render experience section
     */
    private function render_experience($experience) {
        $typo = SFFC_Application_Pack_Design_System::TYPOGRAPHY;

        foreach ($experience as $exp) {
            // Check for page break
            if ($this->pdf->GetY() > 700) {
                $this->pdf->AddPage();
            }

            // Company and dates row
            $this->pdf->SetFont('helvetica', 'B', $typo['size_body']);
            $this->pdf->SetTextColorArray($this->design::get_color('text_dark'));
            $this->pdf->Cell(350, 16, $exp['company'] ?? '', 0, 0, 'L');

            $this->pdf->SetFont('helvetica', '', $typo['size_small']);
            $this->pdf->SetTextColorArray($this->design::get_color('text_light'));
            $date_range = ($exp['start_date'] ?? '') . ' - ' . ($exp['end_date'] ?? 'Present');
            $this->pdf->Cell(145, 16, $date_range, 0, 1, 'R');

            // Job title
            $this->pdf->SetFont('helvetica', 'I', $typo['size_body']);
            $this->pdf->SetTextColorArray($this->design::get_color('text_medium'));
            $this->pdf->Cell(0, 14, $exp['title'] ?? '', 0, 1, 'L');

            // Achievements/bullets
            if (!empty($exp['achievements'])) {
                $this->pdf->SetFont('helvetica', '', $typo['size_body']);
                $this->pdf->SetTextColorArray($this->design::get_color('text_dark'));

                foreach ($exp['achievements'] as $bullet) {
                    // Trim to 180 chars
                    $bullet = strlen($bullet) > 180 ? substr($bullet, 0, 177) . '...' : $bullet;
                    $this->pdf->Cell(12, 14, chr(149), 0, 0, 'L'); // Bullet
                    $this->pdf->MultiCell(483, 14, $bullet, 0, 'L', false);
                }
            }

            $this->pdf->Ln(8);
        }
    }

    /**
     * Render skills section
     */
    private function render_skills($skills) {
        $typo = SFFC_Application_Pack_Design_System::TYPOGRAPHY;

        $all_skills = array_merge(
            $skills['matched'] ?? array(),
            $skills['additional'] ?? array()
        );

        if (empty($all_skills)) {
            return;
        }

        // Calculate skill tags per row
        $x_start = 50;
        $x = $x_start;
        $y = $this->pdf->GetY();
        $max_width = 495;
        $tag_height = 20;
        $tag_padding = 8;
        $tag_margin = 6;

        foreach ($all_skills as $index => $skill) {
            // Calculate tag width
            $this->pdf->SetFont('helvetica', '', $typo['size_small']);
            $text_width = $this->pdf->GetStringWidth($skill);
            $tag_width = $text_width + ($tag_padding * 2);

            // Check if we need to wrap to next line
            if ($x + $tag_width > $x_start + $max_width) {
                $x = $x_start;
                $y += $tag_height + $tag_margin;
            }

            // Draw tag background
            $is_matched = $index < count($skills['matched'] ?? array());
            if ($is_matched) {
                $this->pdf->SetFillColorArray(array(232, 244, 248)); // Light blue for matched
                $this->pdf->SetDrawColorArray($this->design::get_color('primary_light'));
            } else {
                $this->pdf->SetFillColorArray($this->design::get_color('background_alt'));
                $this->pdf->SetDrawColorArray($this->design::get_color('border'));
            }

            $this->pdf->RoundedRect($x, $y, $tag_width, $tag_height, 2, '1111', 'DF');

            // Draw text
            $this->pdf->SetXY($x + $tag_padding, $y + 5);
            $this->pdf->SetTextColorArray($this->design::get_color('text_dark'));
            $this->pdf->Cell($text_width, 10, $skill, 0, 0, 'L');

            $x += $tag_width + $tag_margin;
        }

        $this->pdf->SetY($y + $tag_height + 16);
    }

    /**
     * Render education section
     */
    private function render_education($education) {
        $typo = SFFC_Application_Pack_Design_System::TYPOGRAPHY;

        foreach ($education as $edu) {
            $this->pdf->SetFont('helvetica', 'B', $typo['size_body']);
            $this->pdf->SetTextColorArray($this->design::get_color('text_dark'));
            $this->pdf->Cell(350, 16, $edu['institution'] ?? '', 0, 0, 'L');

            $this->pdf->SetFont('helvetica', '', $typo['size_small']);
            $this->pdf->SetTextColorArray($this->design::get_color('text_light'));
            $this->pdf->Cell(145, 16, $edu['year'] ?? '', 0, 1, 'R');

            $this->pdf->SetFont('helvetica', 'I', $typo['size_body']);
            $this->pdf->SetTextColorArray($this->design::get_color('text_medium'));
            $this->pdf->Cell(0, 14, $edu['degree'] ?? '', 0, 1, 'L');

            $this->pdf->Ln(4);
        }
    }

    /**
     * Render footer
     */
    private function render_footer($job_data) {
        $typo = SFFC_Application_Pack_Design_System::TYPOGRAPHY;

        // Add footer on last page
        $this->pdf->SetY(-60);

        // Divider line
        $this->pdf->SetDrawColorArray($this->design::get_color('border'));
        $this->pdf->SetLineWidth(0.5);
        $this->pdf->Line(50, $this->pdf->GetY(), 545, $this->pdf->GetY());

        $this->pdf->Ln(8);

        // Footer text
        $this->pdf->SetFont('helvetica', '', $typo['size_tiny']);
        $this->pdf->SetTextColorArray($this->design::get_color('text_light'));
        $this->pdf->Cell(0, 12, 'Tailored for ' . $job_data['title'] . ' at ' . $job_data['company'], 0, 1, 'C');
    }

    /**
     * Save PDF to file
     */
    public function save_to_file($content, $filename) {
        $upload_dir = wp_upload_dir();
        $pack_dir = $upload_dir['basedir'] . '/sffc-application-packs';

        if (!file_exists($pack_dir)) {
            wp_mkdir_p($pack_dir);
            file_put_contents($pack_dir . '/.htaccess', 'deny from all');
        }

        $file_path = $pack_dir . '/' . $filename;
        file_put_contents($file_path, $content);

        return array(
            'path' => $file_path,
            'url' => $upload_dir['baseurl'] . '/sffc-application-packs/' . $filename,
        );
    }

    /**
     * Generate cover letter PDF
     */
    public function generate_cover_letter($content, $job_data, $user_name) {
        $this->init_pdf($user_name . ' - Cover Letter - ' . $job_data['company']);

        $typo = SFFC_Application_Pack_Design_System::TYPOGRAPHY;

        // Date
        $this->pdf->SetFont('helvetica', '', $typo['size_body']);
        $this->pdf->SetTextColorArray($this->design::get_color('text_dark'));
        $this->pdf->Cell(0, 16, date('F j, Y'), 0, 1, 'R');

        $this->pdf->Ln(16);

        // Recipient
        if (!empty($content['recipient'])) {
            $this->pdf->Cell(0, 14, $content['recipient'], 0, 1, 'L');
        }
        $this->pdf->Cell(0, 14, $job_data['company'], 0, 1, 'L');

        $this->pdf->Ln(16);

        // Subject line
        $this->pdf->SetFont('helvetica', 'B', $typo['size_body']);
        $this->pdf->Cell(0, 14, 'RE: ' . $job_data['title'] . ' Application', 0, 1, 'L');

        $this->pdf->Ln(8);

        // Divider
        $this->pdf->SetDrawColorArray($this->design::get_color('border'));
        $this->pdf->SetLineWidth(0.5);
        $this->pdf->Line(50, $this->pdf->GetY(), 545, $this->pdf->GetY());

        $this->pdf->Ln(16);

        // Body paragraphs
        $this->pdf->SetFont('helvetica', '', $typo['size_body']);
        $this->pdf->SetTextColorArray($this->design::get_color('text_dark'));

        foreach ($content['paragraphs'] as $paragraph) {
            $this->pdf->MultiCell(495, 16, $paragraph, 0, 'L', false);
            $this->pdf->Ln(8);
        }

        $this->pdf->Ln(16);

        // Closing
        $this->pdf->Cell(0, 14, 'Regards,', 0, 1, 'L');
        $this->pdf->Ln(24);
        $this->pdf->SetFont('helvetica', 'B', $typo['size_body']);
        $this->pdf->Cell(0, 14, $content['sender_name'], 0, 1, 'L');

        // Contact info
        $this->pdf->SetFont('helvetica', '', $typo['size_small']);
        $this->pdf->SetTextColorArray($this->design::get_color('text_medium'));

        $contact = array();
        if (!empty($content['phone'])) $contact[] = $content['phone'];
        if (!empty($content['email'])) $contact[] = $content['email'];
        if (!empty($content['linkedin'])) $contact[] = $content['linkedin'];

        $this->pdf->Cell(0, 14, implode('  |  ', $contact), 0, 1, 'L');

        return $this->pdf->Output('', 'S');
    }

    /**
     * Generate interview prep PDF
     */
    public function generate_interview_prep($content, $job_data) {
        $this->init_pdf('Interview Preparation Brief - ' . $job_data['company']);

        $typo = SFFC_Application_Pack_Design_System::TYPOGRAPHY;

        // Title with colored background
        $this->pdf->SetFillColorArray($this->design::get_color('primary'));
        $this->pdf->Rect(0, 0, 595, 70, 'F');

        $this->pdf->SetY(20);
        $this->pdf->SetFont('helvetica', 'B', $typo['size_h1']);
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->Cell(0, 24, 'INTERVIEW PREPARATION BRIEF', 0, 1, 'L');

        $this->pdf->SetFont('helvetica', '', $typo['size_h2']);
        $this->pdf->Cell(0, 18, $job_data['company'] . ' - ' . $job_data['title'], 0, 1, 'L');

        $this->pdf->SetY(80);

        // Company Facts Row
        if (!empty($content['company_facts'])) {
            $this->render_company_facts_row($content['company_facts']);
        }

        // Role Overview
        $this->render_section_header('Role Overview');
        $this->render_executive_box($content['role_overview'] ?? 'Position requires strong analytical and communication skills.');

        // Experience Alignment Table
        $this->render_section_header('Your Experience Alignment');
        if (!empty($content['alignment'])) {
            $this->render_alignment_table($content['alignment']);
        }

        // Key Talking Points
        if (!empty($content['talking_points'])) {
            $this->render_section_header('Key Talking Points');
            foreach ($content['talking_points'] as $point) {
                $this->pdf->SetFillColorArray($this->design::get_color('background_alt'));
                $x = $this->pdf->GetX();
                $y = $this->pdf->GetY();

                // Gold left border
                $this->pdf->SetFillColorArray($this->design::get_color('secondary'));
                $this->pdf->Rect($x, $y, 3, 16, 'F');

                // Background
                $this->pdf->SetFillColorArray($this->design::get_color('background_alt'));
                $this->pdf->Rect($x + 3, $y, 492, 16, 'F');

                $this->pdf->SetXY($x + 10, $y + 4);
                $this->pdf->SetFont('helvetica', '', $typo['size_body']);
                $this->pdf->SetTextColorArray($this->design::get_color('text_dark'));
                $this->pdf->Cell(485, 8, $point, 0, 1, 'L');
                $this->pdf->Ln(4);
            }
        }

        // Check page break
        if ($this->pdf->GetY() > 650) {
            $this->pdf->AddPage();
        }

        // Anticipated Questions
        $this->render_section_header('Anticipated Questions');
        if (!empty($content['questions'])) {
            foreach (array_slice($content['questions'], 0, 5) as $q) {
                if ($this->pdf->GetY() > 700) {
                    $this->pdf->AddPage();
                }

                $this->pdf->SetFont('helvetica', 'B', $typo['size_body']);
                $this->pdf->SetTextColorArray($this->design::get_color('text_dark'));
                $this->pdf->Cell(16, 14, 'Q:', 0, 0, 'L');
                $this->pdf->MultiCell(479, 14, $q['question'], 0, 'L', false);

                $this->pdf->SetFont('helvetica', '', $typo['size_body']);
                $this->pdf->SetTextColorArray($this->design::get_color('text_medium'));
                $this->pdf->SetX(66);
                $this->pdf->MultiCell(429, 12, 'Prep: ' . ($q['guidance'] ?? ''), 0, 'L', false);
                $this->pdf->Ln(6);
            }
        }

        // Questions to Ask
        if ($this->pdf->GetY() > 650) {
            $this->pdf->AddPage();
        }

        $this->render_section_header('Questions to Ask');
        if (!empty($content['questions_to_ask'])) {
            // Two column layout
            $questions = $content['questions_to_ask'];
            $mid = ceil(count($questions) / 2);
            $start_y = $this->pdf->GetY();

            // Left column
            $this->pdf->SetX(50);
            for ($i = 0; $i < $mid; $i++) {
                if (isset($questions[$i])) {
                    $this->pdf->SetFont('helvetica', '', $typo['size_body']);
                    $this->pdf->SetTextColorArray($this->design::get_color('text_dark'));
                    $this->pdf->Cell(10, 12, chr(149), 0, 0, 'L');
                    $this->pdf->MultiCell(217, 12, $questions[$i], 0, 'L', false);
                }
            }
            $left_end_y = $this->pdf->GetY();

            // Right column
            $this->pdf->SetXY(295, $start_y);
            for ($i = $mid; $i < count($questions); $i++) {
                if (isset($questions[$i])) {
                    $this->pdf->SetFont('helvetica', '', $typo['size_body']);
                    $this->pdf->SetTextColorArray($this->design::get_color('text_dark'));
                    $this->pdf->Cell(10, 12, chr(149), 0, 0, 'L');
                    $this->pdf->MultiCell(217, 12, $questions[$i], 0, 'L', false);
                    $this->pdf->SetX(295);
                }
            }

            $this->pdf->SetY(max($left_end_y, $this->pdf->GetY()) + 8);
        }

        // Potential Challenges
        if (!empty($content['challenges'])) {
            if ($this->pdf->GetY() > 650) {
                $this->pdf->AddPage();
            }

            $this->render_section_header('Prepare For These Concerns');
            foreach ($content['challenges'] as $challenge) {
                $y = $this->pdf->GetY();

                // Border box
                $this->pdf->SetDrawColorArray($this->design::get_color('border'));
                $this->pdf->RoundedRect(50, $y, 495, 36, 2, '1111', 'D');

                // Concern
                $this->pdf->SetXY(55, $y + 4);
                $this->pdf->SetFont('helvetica', 'B', $typo['size_body']);
                $this->pdf->SetTextColorArray(array(192, 86, 33)); // Warning orange
                $this->pdf->Cell(485, 12, '! ' . ($challenge['concern'] ?? ''), 0, 1, 'L');

                // Response
                $this->pdf->SetX(55);
                $this->pdf->SetFont('helvetica', '', $typo['size_body']);
                $this->pdf->SetTextColorArray($this->design::get_color('text_medium'));
                $this->pdf->Cell(485, 12, '-> ' . ($challenge['response'] ?? ''), 0, 1, 'L');

                $this->pdf->SetY($y + 42);
            }
        }

        return $this->pdf->Output('', 'S');
    }

    /**
     * Render company facts row
     */
    private function render_company_facts_row($facts) {
        $typo = SFFC_Application_Pack_Design_System::TYPOGRAPHY;
        $num_facts = count($facts);
        $cell_width = 495 / $num_facts;
        $start_x = 50;

        foreach ($facts as $index => $fact) {
            $x = $start_x + ($index * $cell_width);

            // Background
            $this->pdf->SetFillColorArray($this->design::get_color('background_alt'));
            $this->pdf->Rect($x, $this->pdf->GetY(), $cell_width - 4, 36, 'F');

            // Label
            $this->pdf->SetXY($x + 4, $this->pdf->GetY() + 4);
            $this->pdf->SetFont('helvetica', '', $typo['size_tiny']);
            $this->pdf->SetTextColorArray($this->design::get_color('text_light'));
            $this->pdf->Cell($cell_width - 8, 10, strtoupper($fact['label']), 0, 0, 'C');

            // Value
            $this->pdf->SetXY($x + 4, $this->pdf->GetY() + 10);
            $this->pdf->SetFont('helvetica', 'B', $typo['size_body']);
            $this->pdf->SetTextColorArray($this->design::get_color('primary'));
            $this->pdf->Cell($cell_width - 8, 14, $fact['value'], 0, 0, 'C');
        }

        $this->pdf->Ln(44);
    }

    /**
     * Render alignment table
     */
    private function render_alignment_table($alignment) {
        $typo = SFFC_Application_Pack_Design_System::TYPOGRAPHY;

        // Header row
        $this->pdf->SetFillColorArray($this->design::get_color('primary'));
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->SetFont('helvetica', 'B', $typo['size_body']);
        $this->pdf->Cell(247, 20, 'They Need', 1, 0, 'C', true);
        $this->pdf->Cell(248, 20, 'You Have', 1, 1, 'C', true);

        // Data rows
        $this->pdf->SetFillColorArray($this->design::get_color('background_alt'));
        $this->pdf->SetTextColorArray($this->design::get_color('text_dark'));
        $this->pdf->SetFont('helvetica', '', $typo['size_body']);

        $fill = false;
        foreach ($alignment as $row) {
            $this->pdf->Cell(247, 18, $row['need'] ?? '', 1, 0, 'L', $fill);
            $this->pdf->Cell(248, 18, $row['have'] ?? '', 1, 1, 'L', $fill);
            $fill = !$fill;
        }

        $this->pdf->Ln(8);
    }

    /**
     * Generate company intel brief PDF
     */
    public function generate_company_brief($content, $job_data) {
        $this->init_pdf('Company Intelligence Brief - ' . $job_data['company']);

        $typo = SFFC_Application_Pack_Design_System::TYPOGRAPHY;

        // Header with colored background
        $this->pdf->SetFillColorArray($this->design::get_color('primary'));
        $this->pdf->Rect(0, 0, 595, 70, 'F');

        $this->pdf->SetY(20);
        $this->pdf->SetFont('helvetica', 'B', $typo['size_h1']);
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->Cell(0, 24, 'COMPANY INTELLIGENCE BRIEF', 0, 1, 'L');

        $this->pdf->SetFont('helvetica', '', $typo['size_h2']);
        $this->pdf->Cell(0, 18, $job_data['company'] . ' | ' . ($job_data['industry'] ?? 'Financial Services'), 0, 1, 'L');

        $this->pdf->SetY(80);

        // Company Profile Grid
        $profile = $content['company_profile'];
        $profile_items = array(
            array('label' => 'Company', 'value' => $profile['name']),
            array('label' => 'Industry', 'value' => $profile['industry']),
            array('label' => 'Type', 'value' => $profile['type']),
            array('label' => 'Location', 'value' => $profile['location']),
        );
        $this->render_company_facts_row($profile_items);

        // Overview
        $this->render_executive_box($profile['overview'] ?? '');

        // Industry Position
        $this->render_section_header('Industry Position');
        if (!empty($content['industry_position'])) {
            foreach ($content['industry_position'] as $point) {
                $this->pdf->SetFont('helvetica', '', $typo['size_body']);
                $this->pdf->SetTextColorArray($this->design::get_color('text_dark'));
                $this->pdf->Cell(12, 14, chr(149), 0, 0, 'L');
                $this->pdf->MultiCell(483, 14, $point, 0, 'L', false);
            }
        }

        // Business Model
        $this->render_section_header('Business Model');
        $this->render_executive_box($content['business_model'] ?? '');

        // Culture & Values
        $this->render_section_header('Culture & Values');
        if (!empty($content['culture_values'])) {
            foreach ($content['culture_values'] as $point) {
                $this->pdf->SetFont('helvetica', '', $typo['size_body']);
                $this->pdf->SetTextColorArray($this->design::get_color('text_dark'));
                $this->pdf->Cell(12, 14, chr(149), 0, 0, 'L');
                $this->pdf->MultiCell(483, 14, $point, 0, 'L', false);
            }
        }

        // Page break check
        if ($this->pdf->GetY() > 600) {
            $this->pdf->AddPage();
        }

        // Research Topics
        $this->render_section_header('Research Topics');
        if (!empty($content['recent_developments']['research_topics'])) {
            // Yellow highlight box
            $y = $this->pdf->GetY();
            $this->pdf->SetFillColor(255, 249, 230);
            $this->pdf->SetDrawColorArray($this->design::get_color('secondary'));
            $this->pdf->RoundedRect(50, $y, 495, 70, 3, '1111', 'DF');

            $this->pdf->SetXY(58, $y + 8);
            $this->pdf->SetFont('helvetica', 'B', $typo['size_body']);
            $this->pdf->SetTextColorArray($this->design::get_color('secondary'));
            $this->pdf->Cell(479, 12, 'Topics to Research Before Your Interview:', 0, 1, 'L');

            foreach ($content['recent_developments']['research_topics'] as $topic) {
                $this->pdf->SetX(58);
                $this->pdf->SetFont('helvetica', '', $typo['size_body']);
                $this->pdf->SetTextColorArray($this->design::get_color('text_dark'));
                $this->pdf->Cell(10, 12, chr(149), 0, 0, 'L');
                $this->pdf->Cell(469, 12, $topic, 0, 1, 'L');
            }

            $this->pdf->SetY($y + 78);
        }

        // Leadership to Research
        if ($this->pdf->GetY() > 650) {
            $this->pdf->AddPage();
        }

        $this->render_section_header('Key Leadership to Research');
        if (!empty($content['leadership']['roles_to_research'])) {
            foreach ($content['leadership']['roles_to_research'] as $role) {
                $y = $this->pdf->GetY();
                $this->pdf->SetFillColorArray($this->design::get_color('background_alt'));
                $this->pdf->Rect(50, $y, 495, 16, 'F');

                $this->pdf->SetXY(55, $y + 4);
                $this->pdf->SetFont('helvetica', 'B', $typo['size_body']);
                $this->pdf->SetTextColorArray($this->design::get_color('text_dark'));
                $this->pdf->Cell(250, 8, $role['title'], 0, 0, 'L');

                $this->pdf->SetFont('helvetica', '', $typo['size_small']);
                $this->pdf->SetTextColorArray($this->design::get_color('text_medium'));
                $this->pdf->Cell(240, 8, $role['why'], 0, 1, 'R');

                $this->pdf->SetY($y + 20);
            }
        }

        // Competitive Landscape
        if ($this->pdf->GetY() > 600) {
            $this->pdf->AddPage();
        }

        $this->render_section_header('Competitive Landscape');
        if (!empty($content['competitors'])) {
            // Two column layout
            $competitors = $content['competitors'];
            $mid = ceil(count($competitors) / 2);
            $start_y = $this->pdf->GetY();

            // Left column
            for ($i = 0; $i < $mid; $i++) {
                if (isset($competitors[$i])) {
                    $y = $this->pdf->GetY();
                    $this->pdf->SetFillColorArray($this->design::get_color('background_alt'));
                    $this->pdf->Rect(50, $y, 235, 26, 'F');

                    $this->pdf->SetXY(55, $y + 4);
                    $this->pdf->SetFont('helvetica', 'B', $typo['size_body']);
                    $this->pdf->SetTextColorArray($this->design::get_color('primary'));
                    $this->pdf->Cell(225, 8, $competitors[$i]['name'], 0, 1, 'L');

                    $this->pdf->SetX(55);
                    $this->pdf->SetFont('helvetica', '', $typo['size_small']);
                    $this->pdf->SetTextColorArray($this->design::get_color('text_medium'));
                    $this->pdf->Cell(225, 8, $competitors[$i]['differentiator'], 0, 1, 'L');

                    $this->pdf->SetY($y + 30);
                }
            }
            $left_end_y = $this->pdf->GetY();

            // Right column
            $this->pdf->SetY($start_y);
            for ($i = $mid; $i < count($competitors); $i++) {
                if (isset($competitors[$i])) {
                    $y = $this->pdf->GetY();
                    $this->pdf->SetFillColorArray($this->design::get_color('background_alt'));
                    $this->pdf->Rect(295, $y, 235, 26, 'F');

                    $this->pdf->SetXY(300, $y + 4);
                    $this->pdf->SetFont('helvetica', 'B', $typo['size_body']);
                    $this->pdf->SetTextColorArray($this->design::get_color('primary'));
                    $this->pdf->Cell(225, 8, $competitors[$i]['name'], 0, 1, 'L');

                    $this->pdf->SetX(300);
                    $this->pdf->SetFont('helvetica', '', $typo['size_small']);
                    $this->pdf->SetTextColorArray($this->design::get_color('text_medium'));
                    $this->pdf->Cell(225, 8, $competitors[$i]['differentiator'], 0, 1, 'L');

                    $this->pdf->SetY($y + 30);
                }
            }

            $this->pdf->SetY(max($left_end_y, $this->pdf->GetY()) + 8);
        }

        // Interview Intelligence
        if ($this->pdf->GetY() > 650) {
            $this->pdf->AddPage();
        }

        $this->render_section_header('Interview Intelligence');
        $this->pdf->SetFont('helvetica', '', $typo['size_body']);
        $this->pdf->SetTextColorArray($this->design::get_color('text_medium'));
        $this->pdf->Cell(0, 12, 'Reference these points to demonstrate preparation:', 0, 1, 'L');

        if (!empty($content['interview_intel'])) {
            foreach ($content['interview_intel'] as $intel) {
                $y = $this->pdf->GetY();

                // Gold left border
                $this->pdf->SetFillColorArray($this->design::get_color('secondary'));
                $this->pdf->Rect(50, $y, 3, 14, 'F');

                // Background
                $this->pdf->SetFillColorArray($this->design::get_color('background_alt'));
                $this->pdf->Rect(53, $y, 492, 14, 'F');

                $this->pdf->SetXY(60, $y + 3);
                $this->pdf->SetFont('helvetica', '', $typo['size_body']);
                $this->pdf->SetTextColorArray($this->design::get_color('text_dark'));
                $this->pdf->Cell(480, 8, $intel, 0, 1, 'L');

                $this->pdf->SetY($y + 18);
            }
        }

        return $this->pdf->Output('', 'S');
    }
}
