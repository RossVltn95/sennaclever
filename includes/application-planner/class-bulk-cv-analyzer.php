<?php
/**
 * Bulk CV Analyzer
 *
 * Analyzes multiple CVs against a single job description
 * and ranks candidates by match quality.
 *
 * @package SennaCareers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Bulk_CV_Analyzer {

    /**
     * Dependencies
     */
    private $gap_analyzer;
    private $ai_analyzer;

    /**
     * Constructor
     */
    public function __construct() {
        // Load dependencies
        require_once SFFC_PLUGIN_DIR . 'includes/application-planner/class-gap-analyzer.php';
        require_once SFFC_PLUGIN_DIR . 'includes/application-planner/class-ai-analyzer.php';

        $this->gap_analyzer = new SFFC_Gap_Analyzer();
        $this->ai_analyzer = new SFFC_AI_Analyzer();
    }

    /**
     * Analyze multiple CVs against a single JD
     *
     * @param string $jd_text Job description text
     * @param array $cv_data_array Array of CV data: [['name' => 'Sarah', 'text' => '...'], ...]
     * @return array Ranked analysis results
     */
    public function analyze_bulk($jd_text, $cv_data_array) {
        if (empty($jd_text) || empty($cv_data_array)) {
            return [
                'success' => false,
                'message' => 'Job description and at least one CV are required.',
            ];
        }

        $results = [];
        $processed = 0;
        $failed = 0;

        // Process each CV
        foreach ($cv_data_array as $index => $cv_data) {
            $cv_text = $cv_data['text'] ?? '';
            $cv_name = $cv_data['name'] ?? "Candidate " . ($index + 1);

            if (empty($cv_text)) {
                $failed++;
                continue;
            }

            try {
                // Use quick_analyze for speed (no AI, just rule-based)
                $analysis = $this->gap_analyzer->quick_analyze($jd_text, $cv_text);

                // Extract candidate name from CV if not provided
                if ($cv_name === "Candidate " . ($index + 1)) {
                    $extracted_name = $this->extract_candidate_name($cv_text);
                    if ($extracted_name) {
                        $cv_name = $extracted_name;
                    }
                }

                // Build result
                $results[] = [
                    'id' => $index,
                    'name' => $cv_name,
                    'score' => $analysis['score'],
                    'risk_level' => $analysis['risk_level'],
                    'dealbreaker_count' => $analysis['dealbreaker_count'],
                    'critical_count' => $analysis['critical_count'],
                    'keyword_match_rate' => $analysis['keyword_match_rate'],
                    'cv_text' => $cv_text, // Store for deep analysis later if needed
                    'recommendation' => $this->get_recommendation($analysis['score'], $analysis['dealbreaker_count']),
                    'action_priority' => $this->get_action_priority($analysis['score'], $analysis['dealbreaker_count']),
                ];

                $processed++;

            } catch (Exception $e) {
                error_log('Bulk CV Analyzer: Failed to analyze CV ' . $cv_name . ': ' . $e->getMessage());
                $failed++;
            }
        }

        // Sort by score descending (highest scores first)
        usort($results, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // Add rank to each result
        foreach ($results as $rank => &$result) {
            $result['rank'] = $rank + 1;
        }

        return [
            'success' => true,
            'total_submitted' => count($cv_data_array),
            'processed' => $processed,
            'failed' => $failed,
            'results' => $results,
            'top_candidate' => $results[0] ?? null,
            'stats' => $this->calculate_stats($results),
        ];
    }

    /**
     * Generate AI summary for selected candidates (for shortlist)
     *
     * @param string $jd_text Job description
     * @param array $candidate_ids Array of candidate IDs to analyze
     * @param array $all_results Full results array from analyze_bulk
     * @return array AI summaries for each candidate
     */
    public function generate_ai_summaries($jd_text, $candidate_ids, $all_results) {
        if (!$this->ai_analyzer->is_available()) {
            return [
                'success' => false,
                'message' => 'AI analysis not available. Please check API configuration.',
            ];
        }

        $summaries = [];
        $processed = 0;
        $failed = 0;

        foreach ($candidate_ids as $id) {
            // Find candidate in results
            $candidate = null;
            foreach ($all_results as $result) {
                if ($result['id'] == $id) {
                    $candidate = $result;
                    break;
                }
            }

            if (!$candidate) {
                $failed++;
                continue;
            }

            try {
                // Run full AI analysis
                $ai_result = $this->ai_analyzer->analyze_full($jd_text, $candidate['cv_text']);

                if ($ai_result['success']) {
                    $summaries[] = [
                        'id' => $id,
                        'name' => $candidate['name'],
                        'score' => $candidate['score'],
                        'rank' => $candidate['rank'],
                        'ai_summary' => $ai_result['data'],
                    ];
                    $processed++;
                } else {
                    error_log('Bulk CV Analyzer: AI analysis failed for ' . $candidate['name'] . ': ' . $ai_result['reason']);
                    $failed++;
                }

            } catch (Exception $e) {
                error_log('Bulk CV Analyzer: Exception analyzing ' . $candidate['name'] . ': ' . $e->getMessage());
                $failed++;
            }
        }

        return [
            'success' => true,
            'processed' => $processed,
            'failed' => $failed,
            'summaries' => $summaries,
        ];
    }

    /**
     * Run deep AI analysis on a single candidate
     *
     * @param string $jd_text Job description
     * @param string $cv_text CV text
     * @return array Full analysis result
     */
    public function analyze_candidate_deep($jd_text, $cv_text) {
        return $this->ai_analyzer->analyze_full($jd_text, $cv_text);
    }

    /**
     * Extract candidate name from CV text
     * Looks for name patterns at the beginning of the CV
     *
     * @param string $cv_text CV text
     * @return string|null Extracted name or null
     */
    private function extract_candidate_name($cv_text) {
        // Get first 500 characters
        $header = substr($cv_text, 0, 500);

        // Common patterns for names at start of CV
        $patterns = [
            // Name on first line
            '/^([A-Z][a-z]+ [A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)/m',
            // Name: or NAME: prefix
            '/(?:Name|NAME):\s*([A-Z][a-z]+ [A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)/i',
            // All caps name
            '/^([A-Z]{2,}\s+[A-Z]{2,}(?:\s+[A-Z]{2,})?)/m',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $header, $matches)) {
                $name = trim($matches[1]);
                // Validate it looks like a name (2-4 words, reasonable length)
                $words = explode(' ', $name);
                if (count($words) >= 2 && count($words) <= 4 && strlen($name) <= 50) {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * Get recommendation text based on score and dealbreakers
     *
     * @param int $score Match score 0-100
     * @param int $dealbreaker_count Number of dealbreakers
     * @return string Recommendation
     */
    private function get_recommendation($score, $dealbreaker_count) {
        if ($dealbreaker_count > 0 && $score < 60) {
            return 'DO_NOT_PURSUE';
        } elseif ($score >= 80) {
            return 'PRIORITY_CONTACT';
        } elseif ($score >= 65) {
            return 'STRONG_CANDIDATE';
        } elseif ($score >= 50) {
            return 'CONSIDER_SCREENING';
        } else {
            return 'WEAK_MATCH';
        }
    }

    /**
     * Get action priority (1-5, 1 being highest)
     *
     * @param int $score Match score
     * @param int $dealbreaker_count Dealbreakers
     * @return int Priority 1-5
     */
    private function get_action_priority($score, $dealbreaker_count) {
        if ($dealbreaker_count > 0 && $score < 60) {
            return 5; // Lowest priority
        } elseif ($score >= 80) {
            return 1; // Highest priority
        } elseif ($score >= 65) {
            return 2;
        } elseif ($score >= 50) {
            return 3;
        } else {
            return 4;
        }
    }

    /**
     * Calculate statistics across all results
     *
     * @param array $results Analysis results
     * @return array Statistics
     */
    private function calculate_stats($results) {
        if (empty($results)) {
            return [
                'avg_score' => 0,
                'top_score' => 0,
                'strong_fit_count' => 0,
                'competitive_count' => 0,
                'moderate_count' => 0,
                'weak_count' => 0,
            ];
        }

        $scores = array_column($results, 'score');
        $strong = 0;
        $competitive = 0;
        $moderate = 0;
        $weak = 0;

        foreach ($results as $result) {
            $score = $result['score'];
            if ($score >= 80) {
                $strong++;
            } elseif ($score >= 65) {
                $competitive++;
            } elseif ($score >= 50) {
                $moderate++;
            } else {
                $weak++;
            }
        }

        return [
            'avg_score' => round(array_sum($scores) / count($scores), 1),
            'top_score' => max($scores),
            'strong_fit_count' => $strong,
            'competitive_count' => $competitive,
            'moderate_count' => $moderate,
            'weak_count' => $weak,
        ];
    }

    /**
     * Export results to CSV format
     *
     * @param array $results Analysis results
     * @return string CSV content
     */
    public function export_to_csv($results) {
        if (empty($results)) {
            return '';
        }

        $csv = "Rank,Candidate Name,Score,Risk Level,Recommendation,Dealbreakers,Critical Issues,Keyword Match\n";

        foreach ($results as $result) {
            $csv .= sprintf(
                "%d,\"%s\",%d,%s,%s,%d,%d,%d%%\n",
                $result['rank'],
                str_replace('"', '""', $result['name']),
                $result['score'],
                $result['risk_level'],
                $result['recommendation'],
                $result['dealbreaker_count'],
                $result['critical_count'],
                $result['keyword_match_rate']
            );
        }

        return $csv;
    }

    /**
     * Export shortlist to PDF with AI summaries
     *
     * @param string $jd_text Job description
     * @param array $summaries AI summaries from generate_ai_summaries()
     * @return string HTML content for PDF
     */
    public function export_shortlist_pdf($jd_text, $summaries) {
        if (empty($summaries)) {
            return '';
        }

        $date = date('F j, Y');
        $total = count($summaries);

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Candidate Shortlist - MENA Careers</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 11px;
            line-height: 1.6;
            color: #1e293b;
            background: #fff;
            padding: 40px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 24px;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 32px;
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            color: #0f5132;
        }

        .header-meta {
            text-align: right;
            color: #64748b;
            font-size: 10px;
        }

        .header-meta strong {
            display: block;
            font-size: 14px;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .intro {
            background: #f8fafc;
            border-left: 4px solid #0f5132;
            padding: 20px;
            margin-bottom: 32px;
            border-radius: 8px;
        }

        .intro h2 {
            font-size: 16px;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .intro p {
            font-size: 12px;
            color: #475569;
            line-height: 1.6;
        }

        .candidate-section {
            margin-bottom: 40px;
            page-break-inside: avoid;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            background: #fff;
        }

        .candidate-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
        }

        .candidate-title {
            flex: 1;
        }

        .candidate-rank {
            width: 48px;
            height: 48px;
            background: #0f5132;
            color: #fff;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            margin-right: 16px;
        }

        .candidate-name {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .candidate-score {
            font-size: 14px;
            font-weight: 600;
            color: #0f5132;
        }

        .section {
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .summary-box {
            background: #fffbeb;
            border-left: 3px solid #d97706;
            padding: 16px;
            border-radius: 6px;
            font-size: 12px;
            line-height: 1.6;
            color: #78350f;
            margin-bottom: 16px;
        }

        .strengths-list, .gaps-list, .recommendations-list {
            list-style: none;
            padding: 0;
        }

        .strengths-list li {
            padding: 10px 12px;
            margin-bottom: 8px;
            background: #f0fdf4;
            border-left: 3px solid #059669;
            border-radius: 4px;
            font-size: 11px;
            line-height: 1.5;
        }

        .gaps-list li {
            padding: 10px 12px;
            margin-bottom: 8px;
            background: #fef2f2;
            border-left: 3px solid #dc2626;
            border-radius: 4px;
            font-size: 11px;
            line-height: 1.5;
        }

        .recommendations-list li {
            padding: 10px 12px;
            margin-bottom: 8px;
            background: #eff6ff;
            border-left: 3px solid #2563eb;
            border-radius: 4px;
            font-size: 11px;
            line-height: 1.5;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            color: #94a3b8;
            font-size: 10px;
        }

        @media print {
            body { padding: 20px; }
            .candidate-section { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">MENA Careers</div>
        <div class="header-meta">
            <strong>Candidate Shortlist</strong>
            {$date}
        </div>
    </div>

    <div class="intro">
        <h2>📋 Shortlist Summary</h2>
        <p><strong>{$total} candidates</strong> selected from bulk CV screening. Each candidate has been analyzed by AI for detailed insights.</p>
    </div>
HTML;

        // Add each candidate summary
        foreach ($summaries as $summary) {
            $ai_data = $summary['ai_summary'];
            $exec_summary = $ai_data['executive_summary'] ?? [];
            $skills = $ai_data['skills_breakdown'] ?? [];
            $strengths = $ai_data['strengths_to_highlight'] ?? [];
            $red_flags = $ai_data['red_flags'] ?? [];
            $overall = $ai_data['overall_assessment'] ?? [];

            $verdict = htmlspecialchars($exec_summary['verdict'] ?? 'Analysis complete');
            $recommendation = htmlspecialchars($exec_summary['recommendation'] ?? 'REVIEW');

            $html .= <<<CANDIDATE
    <div class="candidate-section">
        <div class="candidate-header">
            <div class="candidate-rank">#{$summary['rank']}</div>
            <div class="candidate-title">
                <h3 class="candidate-name">{$summary['name']}</h3>
                <div class="candidate-score">{$summary['score']}% Match · {$recommendation}</div>
            </div>
        </div>

        <div class="summary-box">
            <strong>AI Assessment:</strong> {$verdict}
        </div>
CANDIDATE;

            // Strengths
            if (!empty($strengths)) {
                $html .= '<div class="section"><h4 class="section-title">✓ Key Strengths</h4><ul class="strengths-list">';
                foreach (array_slice($strengths, 0, 5) as $strength) {
                    $str = htmlspecialchars($strength['strength'] ?? '');
                    $rel = htmlspecialchars($strength['relevance'] ?? $strength['how_to_leverage'] ?? '');
                    $html .= "<li><strong>{$str}</strong><br>{$rel}</li>";
                }
                $html .= '</ul></div>';
            }

            // Red Flags / Gaps
            if (!empty($red_flags)) {
                $html .= '<div class="section"><h4 class="section-title">⚠ Areas of Concern</h4><ul class="gaps-list">';
                foreach (array_slice($red_flags, 0, 4) as $flag) {
                    $issue = htmlspecialchars($flag['issue'] ?? '');
                    $mitigation = htmlspecialchars($flag['mitigation'] ?? '');
                    $html .= "<li><strong>{$issue}</strong><br>Mitigation: {$mitigation}</li>";
                }
                $html .= '</ul></div>';
            }

            // Missing Skills
            $missing_skills = $skills['missing_skills'] ?? [];
            if (!empty($missing_skills)) {
                $html .= '<div class="section"><h4 class="section-title">📚 Skills to Develop</h4><ul class="recommendations-list">';
                foreach (array_slice($missing_skills, 0, 4) as $skill) {
                    $skill_name = htmlspecialchars($skill['skill'] ?? '');
                    $importance = htmlspecialchars($skill['importance'] ?? '');
                    $html .= "<li><strong>{$skill_name}</strong> ({$importance})</li>";
                }
                $html .= '</ul></div>';
            }

            // Final recommendation
            $final_rec = htmlspecialchars($overall['final_recommendation'] ?? '');
            if ($final_rec) {
                $html .= "<div class=\"section\"><h4 class=\"section-title\">💡 Recommendation</h4><p style=\"font-size: 11px; line-height: 1.6; color: #475569;\">{$final_rec}</p></div>";
            }

            $html .= '</div>';
        }

        $html .= <<<FOOTER
    <div class="footer">
        Generated by MENA Careers AI Screening Platform · joinsenna.com<br>
        {$date}
    </div>
</body>
</html>
FOOTER;

        return $html;
    }
}
