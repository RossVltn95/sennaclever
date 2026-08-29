<?php
/**
 * ATS Analyzer
 *
 * Simulates how Applicant Tracking Systems parse and score CVs.
 * Analyzes:
 * - Keyword density
 * - Format parsability
 * - Section structure
 * - Job title match
 * - Experience recency
 *
 * @package SennaCareers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_ATS_Analyzer {

    /**
     * Knowledge base instance
     */
    private $knowledge_base;

    /**
     * Constructor
     */
    public function __construct() {
        $this->knowledge_base = SFFC_Knowledge_Base::get_instance();
    }

    /**
     * Main analysis method
     *
     * @param string $cv_text Raw CV text
     * @param array $jd_data Parsed JD data
     * @return array ATS analysis result
     */
    public function analyze($cv_text, $jd_data) {
        $keyword_score = $this->calculate_keyword_density_score($cv_text, $jd_data['keywords']);
        $format_score = $this->calculate_format_score($cv_text);
        $structure_score = $this->calculate_structure_score($cv_text);
        $title_score = $this->calculate_title_match_score($cv_text, $jd_data);
        $recency_score = $this->calculate_recency_score($cv_text);

        $total_score = $keyword_score['score'] + $format_score['score'] +
                       $structure_score['score'] + $title_score['score'] +
                       $recency_score['score'];

        $max_score = 100;

        // Collect all issues
        $all_issues = array_merge(
            $format_score['issues'],
            $structure_score['issues'],
            $title_score['issues'],
            $recency_score['issues']
        );

        // Sort by severity
        usort($all_issues, function ($a, $b) {
            $severity_order = ['high' => 0, 'medium' => 1, 'low' => 2];
            return ($severity_order[$a['severity'] ?? 'low'] ?? 2) -
                   ($severity_order[$b['severity'] ?? 'low'] ?? 2);
        });

        // Determine pass/fail
        $ats_verdict = $total_score >= 70 ? 'likely_pass' :
                       ($total_score >= 50 ? 'at_risk' : 'likely_fail');

        return [
            'total_score' => $total_score,
            'max_score' => $max_score,
            'percentage' => round(($total_score / $max_score) * 100),
            'verdict' => $ats_verdict,
            'breakdown' => [
                'keywords' => $keyword_score,
                'format' => $format_score,
                'structure' => $structure_score,
                'title_match' => $title_score,
                'recency' => $recency_score,
            ],
            'issues' => $all_issues,
            'summary' => $this->generate_ats_summary($ats_verdict, $total_score, $all_issues),
        ];
    }

    /**
     * Calculate keyword density score (30 points max)
     */
    private function calculate_keyword_density_score($cv_text, $jd_keywords) {
        $cv_lower = strtolower($cv_text);
        $total_keywords = count($jd_keywords);
        $found_keywords = 0;
        $keyword_details = [];

        foreach ($jd_keywords as $key => $jd_kw) {
            // Count occurrences in CV
            $keyword = strtolower($jd_kw['canonical']);
            $cv_count = substr_count($cv_lower, $keyword);

            // Also check synonyms
            $synonyms = $this->knowledge_base->get_synonyms_for($jd_kw['canonical']);
            foreach ($synonyms as $synonym) {
                if ($synonym !== $jd_kw['canonical']) {
                    $cv_count += substr_count($cv_lower, strtolower($synonym));
                }
            }

            if ($cv_count > 0) {
                $found_keywords++;
            }

            $keyword_details[$key] = [
                'keyword' => $jd_kw['canonical'],
                'jd_count' => $jd_kw['count'],
                'cv_count' => $cv_count,
                'match' => $cv_count > 0,
            ];
        }

        $match_rate = $total_keywords > 0 ? $found_keywords / $total_keywords : 0;
        $score = round($match_rate * 30);

        return [
            'score' => $score,
            'max' => 30,
            'match_rate' => round($match_rate * 100, 1),
            'found' => $found_keywords,
            'total' => $total_keywords,
            'details' => $keyword_details,
        ];
    }

    /**
     * Calculate format parsability score (25 points max)
     */
    private function calculate_format_score($cv_text) {
        $score = 25;
        $issues = [];

        // Check for standard section headers
        $headers = ['experience', 'education', 'skills', 'summary', 'work history', 'employment'];
        $found_headers = 0;
        foreach ($headers as $header) {
            if (stripos($cv_text, $header) !== false) {
                $found_headers++;
            }
        }

        if ($found_headers < 3) {
            $score -= 5;
            $issues[] = [
                'type' => 'missing_headers',
                'severity' => 'medium',
                'message' => 'Missing standard section headers (Experience, Education, Skills)',
                'fix' => 'Add clear section headers that ATS can recognize',
            ];
        }

        // Check for tables (often problematic)
        if (preg_match('/\|.+\|/', $cv_text)) {
            $score -= 5;
            $issues[] = [
                'type' => 'table_detected',
                'severity' => 'high',
                'message' => 'Table formatting detected - many ATS systems cannot parse tables',
                'fix' => 'Convert tables to simple bullet points',
            ];
        }

        // Check for special characters that break parsing
        $special_chars = ['│', '├', '└', '─', '►', '◆', '◇', '○'];
        foreach ($special_chars as $char) {
            if (strpos($cv_text, $char) !== false) {
                $score -= 2;
                $issues[] = [
                    'type' => 'special_characters',
                    'severity' => 'low',
                    'message' => "Special character '{$char}' may not parse correctly",
                    'fix' => 'Use standard bullet points (• or -) instead',
                ];
                break;
            }
        }

        // Check for proper date formatting
        $date_pattern = '/\b(Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s+\d{4}\b/i';
        if (!preg_match($date_pattern, $cv_text)) {
            $score -= 3;
            $issues[] = [
                'type' => 'date_format',
                'severity' => 'medium',
                'message' => 'Date format may not be ATS-friendly',
                'fix' => 'Use format: "Month Year - Month Year" (e.g., "Jan 2020 - Dec 2022")',
            ];
        }

        // Check for contact info
        $has_email = preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $cv_text);
        $has_phone = preg_match('/[\+]?[(]?[0-9]{1,3}[)]?[-\s\.]?[(]?[0-9]{1,4}[)]?[-\s\.]?[0-9]{3,6}/', $cv_text);

        if (!$has_email || !$has_phone) {
            $score -= 5;
            $issues[] = [
                'type' => 'contact_info',
                'severity' => 'high',
                'message' => 'Missing or unparseable contact information',
                'fix' => 'Include email and phone number in standard format at top of CV',
            ];
        }

        // Check for graphics/images indicators
        if (preg_match('/\[image\]|\[photo\]|\[logo\]/i', $cv_text)) {
            $score -= 3;
            $issues[] = [
                'type' => 'graphics',
                'severity' => 'medium',
                'message' => 'Graphics or images may not be parsed by ATS',
                'fix' => 'Use text-only format for ATS applications',
            ];
        }

        return [
            'score' => max(0, $score),
            'max' => 25,
            'issues' => $issues,
        ];
    }

    /**
     * Calculate section structure score (20 points max)
     */
    private function calculate_structure_score($cv_text) {
        $score = 20;
        $issues = [];
        $cv_lower = strtolower($cv_text);

        // Expected sections in order
        $expected_sections = [
            'contact' => ['contact', 'email', 'phone', 'address'],
            'summary' => ['summary', 'profile', 'objective', 'about'],
            'experience' => ['experience', 'work history', 'employment', 'career', 'professional experience'],
            'education' => ['education', 'qualifications', 'academic', 'degree'],
            'skills' => ['skills', 'competencies', 'technical skills', 'expertise'],
        ];

        $found_sections = [];

        foreach ($expected_sections as $section => $keywords) {
            foreach ($keywords as $keyword) {
                $pos = stripos($cv_lower, $keyword);
                if ($pos !== false) {
                    $found_sections[$section] = $pos;
                    break;
                }
            }
        }

        // Check for missing required sections
        if (!isset($found_sections['experience'])) {
            $score -= 5;
            $issues[] = [
                'type' => 'missing_section',
                'severity' => 'high',
                'message' => 'Experience section not clearly labeled',
                'fix' => "Add a clear 'Experience' or 'Work History' header",
            ];
        }

        if (!isset($found_sections['education'])) {
            $score -= 5;
            $issues[] = [
                'type' => 'missing_section',
                'severity' => 'high',
                'message' => 'Education section not clearly labeled',
                'fix' => "Add a clear 'Education' header",
            ];
        }

        // Check section order for experienced candidates
        if (isset($found_sections['experience']) && isset($found_sections['education'])) {
            if ($found_sections['education'] < $found_sections['experience']) {
                $score -= 3;
                $issues[] = [
                    'type' => 'section_order',
                    'severity' => 'low',
                    'message' => 'Education appears before Experience - for experienced candidates, Experience should come first',
                    'fix' => 'Move Experience section above Education',
                ];
            }
        }

        // Check for summary at top
        if (!isset($found_sections['summary']) || (isset($found_sections['summary']) && $found_sections['summary'] > 500)) {
            $score -= 2;
            $issues[] = [
                'type' => 'missing_summary',
                'severity' => 'low',
                'message' => 'No summary section at top of CV',
                'fix' => 'Add a brief professional summary highlighting key qualifications',
            ];
        }

        return [
            'score' => max(0, $score),
            'max' => 20,
            'found_sections' => array_keys($found_sections),
            'issues' => $issues,
        ];
    }

    /**
     * Calculate job title match score (15 points max)
     */
    private function calculate_title_match_score($cv_text, $jd_data) {
        $score = 15;
        $issues = [];

        $target_title = strtolower($jd_data['role_title'] ?? '');
        if (empty($target_title)) {
            return [
                'score' => 10, // Neutral if no title
                'max' => 15,
                'match_type' => 'unknown',
                'issues' => [],
            ];
        }

        $cv_lower = strtolower($cv_text);

        // Check for exact title match
        if (strpos($cv_lower, $target_title) !== false) {
            return [
                'score' => 15,
                'max' => 15,
                'match_type' => 'exact',
                'issues' => [],
            ];
        }

        // Check for partial matches
        $title_words = preg_split('/\s+/', $target_title);
        $title_words = array_filter($title_words, fn($w) => strlen($w) > 3);
        $matched_words = 0;

        foreach ($title_words as $word) {
            if (strpos($cv_lower, $word) !== false) {
                $matched_words++;
            }
        }

        $match_rate = count($title_words) > 0 ? $matched_words / count($title_words) : 0;

        if ($match_rate >= 0.5) {
            $score = 10;
            $issues[] = [
                'type' => 'partial_title_match',
                'severity' => 'medium',
                'message' => "Your titles partially match '{$jd_data['role_title']}'",
                'fix' => "Consider adding '{$jd_data['role_title']}' to your summary if applicable",
            ];
            $match_type = 'partial';
        } else {
            $score = 5;
            $issues[] = [
                'type' => 'weak_title_match',
                'severity' => 'high',
                'message' => "No clear match for target role '{$jd_data['role_title']}'",
                'fix' => "Add a summary section that mentions '{$jd_data['role_title']}' if you have relevant experience",
            ];
            $match_type = 'weak';
        }

        return [
            'score' => $score,
            'max' => 15,
            'match_type' => $match_type,
            'issues' => $issues,
        ];
    }

    /**
     * Calculate experience recency score (10 points max)
     */
    private function calculate_recency_score($cv_text) {
        $score = 10;
        $issues = [];
        $current_year = (int) date('Y');

        // Look for recent years in CV
        $recent_years = [];
        for ($y = $current_year; $y >= $current_year - 2; $y--) {
            if (strpos($cv_text, (string) $y) !== false) {
                $recent_years[] = $y;
            }
        }

        if (empty($recent_years)) {
            $score = 3;
            $issues[] = [
                'type' => 'outdated_cv',
                'severity' => 'high',
                'message' => 'No dates from the past 2 years detected',
                'fix' => 'Ensure your most recent experience is clearly dated',
            ];
        }

        // Check for "Present" or "Current"
        if (!preg_match('/\b(present|current|ongoing|now)\b/i', $cv_text)) {
            $score -= 2;
            $issues[] = [
                'type' => 'no_current_role',
                'severity' => 'medium',
                'message' => 'No current role indicator found',
                'fix' => "Use 'Present' or 'Current' for your current position",
            ];
        }

        // Check for employment gaps (years missing between mentioned years)
        preg_match_all('/\b(20\d{2})\b/', $cv_text, $year_matches);
        $years_mentioned = array_unique($year_matches[1]);
        sort($years_mentioned);

        if (count($years_mentioned) >= 2) {
            $min_year = (int) min($years_mentioned);
            $max_year = (int) max($years_mentioned);

            for ($y = $min_year + 1; $y < $max_year; $y++) {
                if (!in_array((string) $y, $years_mentioned)) {
                    // Potential gap
                    $score -= 1;
                    break; // Only penalize once
                }
            }
        }

        return [
            'score' => max(0, $score),
            'max' => 10,
            'recent_years' => $recent_years,
            'issues' => $issues,
        ];
    }

    /**
     * Generate ATS summary
     */
    private function generate_ats_summary($verdict, $score, $issues) {
        $high_issues = array_filter($issues, fn($i) => ($i['severity'] ?? '') === 'high');
        $issue_count = count($high_issues);

        switch ($verdict) {
            case 'likely_pass':
                if ($issue_count > 0) {
                    return "Your CV should pass most ATS filters, but fix {$issue_count} issue(s) to improve your chances.";
                }
                return "Your CV is well-formatted for ATS systems. It should pass initial screens successfully.";

            case 'at_risk':
                return "Your CV may be filtered by some ATS systems. Address the {$issue_count} high-priority issues.";

            case 'likely_fail':
                return "Your CV has formatting issues that will likely cause ATS rejection. Fix critical issues before applying.";

            default:
                return "ATS analysis complete. Score: {$score}/100.";
        }
    }
}
