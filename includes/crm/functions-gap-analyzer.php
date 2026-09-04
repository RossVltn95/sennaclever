<?php
/**
 * CRM Gap Analyzer Helpers
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('sffc_build_crm_jd_text_for_analysis')) {
    /**
     * Convert CRM post HTML into readable plain text while preserving layout.
     *
     * @param string $content Raw HTML content.
     * @return string
     */
    function sffc_format_crm_jd_content_for_text($content) {
        $content = (string) $content;
        if ($content === '') {
            return '';
        }

        $replacements = [
            '/<\s*br\s*\/?>/i' => "\n",
            '/<\s*li\b[^>]*>/i' => "\n• ",
            '/<\s*\/li\s*>/i' => '',
            '/<\s*\/(?:p|div|section|article|header|footer|aside|blockquote|h[1-6]|tr)\s*>/i' => "\n\n",
            '/<\s*\/(?:ul|ol|table)\s*>/i' => "\n\n",
            '/<\s*\/(?:td|th)\s*>/i' => "\t",
        ];

        foreach ($replacements as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = wp_strip_all_tags($content);
        $content = preg_replace("/\r\n?/", "\n", $content);
        $content = preg_replace("/[ \t]+\n/", "\n", $content);
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        return trim((string) $content);
    }

    function sffc_clean_crm_jd_text_for_analysis($content) {
        $content = trim(wp_strip_all_tags((string) $content));
        if ($content === '') {
            return '';
        }

        $patterns = [
            '/\bAbout\s+MENA\s+Careers\b.*$/is',
            '/\bMENA\s+Careers\s+is\s+a\s+finance\s+community\b.*$/is',
            '/\bFor\s+more\s+information,\s+visit\s+joinsenna\.com\b.*$/is',
            '/\bAbout\s+Senna\b.*$/is',
        ];

        foreach ($patterns as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }

        $content = preg_replace('/[ \t]+/', ' ', (string) $content);
        $content = preg_replace('/\R{3,}/', "\n\n", (string) $content);

        return trim((string) $content);
    }

    /**
     * Build the full JD text for analysis (CRM version)
     *
     * @param array $post CRM post array with job data
     * @return string
     */
    function sffc_build_crm_jd_text_for_analysis($post) {
        $job_title = $post['role_title'] ?? '';
        $company_name = $post['company'] ?? '';
        $job_location = $post['location'] ?? '';
        $location_country = $post['location_country'] ?? '';
        $sector = $post['sector'] ?? '';
        $seniority = $post['seniority'] ?? '';

        $requirements = $post['requirements'] ?? [];
        if (is_string($requirements)) {
            $requirements = json_decode($requirements, true) ?: [];
        }

        $skills = $post['skills_mentioned'] ?? [];
        if (is_string($skills)) {
            $skills = json_decode($skills, true) ?: [];
        }

        $jd_text = "Job Title: {$job_title}\n";

        if ($company_name) {
            $jd_text .= "Company: {$company_name}\n";
        }

        $loc = $job_location ?: $location_country;
        if ($loc) {
            $jd_text .= "Location: {$loc}\n";
        }

        if ($sector) {
            $jd_text .= "Industry: {$sector}\n";
        }

        if ($seniority) {
            $seniority_labels = [
                'intern' => '0-1 years (Intern / Off-cycle)',
                'analyst' => '0-2 years (Analyst)',
                'senior_analyst' => '2-4 years (Senior Analyst)',
                'associate' => '2-4 years (Associate)',
                'senior_associate' => '4-6 years (Senior Associate)',
                'vp' => '4-7 years (VP)',
                'senior_vp' => '8-10 years (Senior VP)',
                'director' => '7-10 years (Director)',
                'md' => '10+ years (Managing Director)',
                'partner' => '15+ years (Partner)',
                'c_level' => '15+ years (C-Level)',
                'board' => 'Board / Advisor',
            ];
            $exp = $seniority_labels[$seniority] ?? $seniority;
            $jd_text .= "Experience Required: {$exp}\n";
        }

        $jd_text .= "\n";

        if (!empty($requirements)) {
            $jd_text .= "Key Requirements:\n" . implode("\n", $requirements) . "\n\n";
        }

        if (!empty($skills)) {
            $jd_text .= "Skills & Background:\n" . implode(", ", $skills) . "\n\n";
        }

        $content = $post['content'] ?? '';
        if ($content) {
            $formatted_content = sffc_format_crm_jd_content_for_text($content);
            if ($formatted_content !== '') {
                $jd_text .= "Job Description:\n" . sffc_clean_crm_jd_text_for_analysis($formatted_content);
            }
        }

        return sffc_clean_crm_jd_text_for_analysis($jd_text);
    }
}
