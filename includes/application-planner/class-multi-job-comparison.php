<?php
/**
 * Multi-Job Comparison
 *
 * Allows candidates to compare their CV against multiple jobs simultaneously.
 * Integrates with CRM to pull saved jobs for comparison.
 *
 * @package SennaCareers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Multi_Job_Comparison {

    /**
     * Gap Analyzer instance
     */
    private $gap_analyzer;

    /**
     * Maximum jobs to compare at once
     */
    private $max_jobs = 5;

    /**
     * Constructor
     */
    public function __construct() {
        $this->gap_analyzer = new SFFC_Gap_Analyzer();
        // Use fallback only for speed in comparison mode
        $this->gap_analyzer->set_mode('fallback_only');
    }

    /**
     * Get jobs from CRM for current user
     *
     * @param int $user_id User ID
     * @return array Jobs from CRM
     */
    public function get_crm_jobs($user_id) {
        global $wpdb;

        // Check if CRM tables exist
        $table = $wpdb->prefix . 'sffc_crm_applications';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;

        if (!$table_exists) {
            return [
                'success' => false,
                'error' => 'crm_not_available',
                'jobs' => [],
            ];
        }

        // Get saved and applied jobs from CRM
        $jobs = $wpdb->get_results($wpdb->prepare("
            SELECT
                a.id,
                a.job_id,
                a.job_title,
                a.company_name,
                a.job_description,
                a.status,
                a.created_at
            FROM {$table} a
            WHERE a.user_id = %d
            AND a.status IN ('saved', 'applied', 'interviewing')
            ORDER BY a.created_at DESC
            LIMIT 20
        ", $user_id), ARRAY_A);

        if (empty($jobs)) {
            return [
                'success' => true,
                'jobs' => [],
                'message' => 'No saved jobs found in CRM',
            ];
        }

        // Format for frontend
        $formatted = [];
        foreach ($jobs as $job) {
            $formatted[] = [
                'id' => $job['id'],
                'job_id' => $job['job_id'],
                'title' => $job['job_title'],
                'company' => $job['company_name'],
                'status' => $job['status'],
                'has_description' => !empty($job['job_description']),
                'description' => $job['job_description'] ?? '',
                'saved_at' => $job['created_at'],
            ];
        }

        return [
            'success' => true,
            'jobs' => $formatted,
        ];
    }

    /**
     * Compare CV against multiple jobs
     *
     * @param string $cv_text Raw CV text
     * @param array $jobs Array of job objects with descriptions
     * @return array Comparison results
     */
    public function compare($cv_text, $jobs) {
        if (count($jobs) > $this->max_jobs) {
            $jobs = array_slice($jobs, 0, $this->max_jobs);
        }

        $results = [];
        $scores = [];

        foreach ($jobs as $job) {
            if (empty($job['description'])) {
                $results[] = [
                    'job_id' => $job['id'] ?? $job['job_id'] ?? 'unknown',
                    'title' => $job['title'] ?? 'Unknown Role',
                    'company' => $job['company'] ?? 'Unknown Company',
                    'error' => 'No job description available',
                    'score' => null,
                ];
                continue;
            }

            // Run quick analysis for each job
            $analysis = $this->gap_analyzer->quick_analyze($job['description'], $cv_text);

            $result = [
                'job_id' => $job['id'] ?? $job['job_id'] ?? 'unknown',
                'title' => $job['title'] ?? 'Unknown Role',
                'company' => $job['company'] ?? 'Unknown Company',
                'score' => $analysis['score'],
                'risk_level' => $analysis['risk_level'],
                'dealbreaker_count' => $analysis['dealbreaker_count'],
                'critical_count' => $analysis['critical_count'],
                'keyword_match' => $analysis['keyword_match_rate'],
            ];

            $results[] = $result;
            $scores[] = $analysis['score'];
        }

        // Sort by score descending (best matches first)
        usort($results, function ($a, $b) {
            $score_a = $a['score'] ?? 0;
            $score_b = $b['score'] ?? 0;
            return $score_b - $score_a;
        });

        // Calculate statistics
        $valid_scores = array_filter($scores, fn($s) => $s !== null);
        $stats = [
            'total_compared' => count($jobs),
            'successful' => count($valid_scores),
            'avg_score' => count($valid_scores) > 0 ? round(array_sum($valid_scores) / count($valid_scores), 1) : 0,
            'max_score' => count($valid_scores) > 0 ? max($valid_scores) : 0,
            'min_score' => count($valid_scores) > 0 ? min($valid_scores) : 0,
        ];

        // Categorize results
        $categories = [
            'strong_matches' => array_filter($results, fn($r) => ($r['risk_level'] ?? '') === 'strong'),
            'competitive' => array_filter($results, fn($r) => ($r['risk_level'] ?? '') === 'competitive'),
            'moderate' => array_filter($results, fn($r) => ($r['risk_level'] ?? '') === 'moderate'),
            'high_risk' => array_filter($results, fn($r) => ($r['risk_level'] ?? '') === 'high'),
        ];

        return [
            'success' => true,
            'results' => $results,
            'statistics' => $stats,
            'categories' => [
                'strong_matches' => count($categories['strong_matches']),
                'competitive' => count($categories['competitive']),
                'moderate' => count($categories['moderate']),
                'high_risk' => count($categories['high_risk']),
            ],
            'recommendation' => $this->generate_recommendation($results, $stats, $categories),
        ];
    }

    /**
     * Generate comparison recommendation
     */
    private function generate_recommendation($results, $stats, $categories) {
        $strong = count($categories['strong_matches']);
        $competitive = count($categories['competitive']);
        $high_risk = count($categories['high_risk']);
        $total = $stats['total_compared'];

        if ($strong > 0) {
            $best = $categories['strong_matches'][0];
            return [
                'type' => 'positive',
                'headline' => "Strong match found!",
                'message' => "You're a strong candidate for {$best['title']} at {$best['company']} (score: {$best['score']}). Apply immediately.",
                'action' => 'apply_to_best',
                'target_job' => $best,
            ];
        }

        if ($competitive > 0) {
            $best = $categories['competitive'][0];
            return [
                'type' => 'neutral',
                'headline' => "Competitive matches available",
                'message' => "You're competitive for {$competitive} role(s). {$best['title']} at {$best['company']} is your best option (score: {$best['score']}).",
                'action' => 'optimize_and_apply',
                'target_job' => $best,
            ];
        }

        if ($stats['avg_score'] >= 50) {
            return [
                'type' => 'caution',
                'headline' => "Moderate fit across roles",
                'message' => "Average score: {$stats['avg_score']}. Consider optimizing your CV or targeting roles that better match your experience.",
                'action' => 'optimize_cv',
            ];
        }

        return [
            'type' => 'warning',
            'headline' => "Limited matches found",
            'message' => "Your current profile has limited alignment with these roles. Consider targeting different positions or gaining additional qualifications.",
            'action' => 'reconsider_targets',
        ];
    }

    /**
     * Generate comparison matrix for visualization
     *
     * @param array $comparison_result Result from compare()
     * @return array Matrix data for charts
     */
    public function generate_matrix($comparison_result) {
        $results = $comparison_result['results'];

        // Prepare data for radar chart
        $radar_data = [];
        foreach ($results as $result) {
            if (isset($result['score'])) {
                $radar_data[] = [
                    'label' => substr($result['title'], 0, 20) . (strlen($result['title']) > 20 ? '...' : ''),
                    'score' => $result['score'],
                    'keyword_match' => $result['keyword_match'] ?? 0,
                    'dealbreakers' => max(0, 100 - ($result['dealbreaker_count'] * 30)),
                    'critical_gaps' => max(0, 100 - ($result['critical_count'] * 10)),
                ];
            }
        }

        // Prepare data for bar chart
        $bar_data = [];
        foreach ($results as $result) {
            if (isset($result['score'])) {
                $bar_data[] = [
                    'label' => $result['company'] . ' - ' . substr($result['title'], 0, 15),
                    'score' => $result['score'],
                    'color' => $this->get_score_color($result['score']),
                ];
            }
        }

        // Prepare data for scatter plot (keyword match vs overall score)
        $scatter_data = [];
        foreach ($results as $result) {
            if (isset($result['score'])) {
                $scatter_data[] = [
                    'x' => $result['keyword_match'] ?? 0,
                    'y' => $result['score'],
                    'label' => $result['title'],
                    'company' => $result['company'],
                ];
            }
        }

        return [
            'radar' => $radar_data,
            'bar' => $bar_data,
            'scatter' => $scatter_data,
        ];
    }

    /**
     * Get color based on score
     */
    private function get_score_color($score) {
        if ($score >= 80) {
            return '#10b981'; // Green
        } elseif ($score >= 60) {
            return '#3b82f6'; // Blue
        } elseif ($score >= 40) {
            return '#f59e0b'; // Orange
        } else {
            return '#ef4444'; // Red
        }
    }

    /**
     * Export comparison as summary
     */
    public function export_summary($comparison_result) {
        $results = $comparison_result['results'];
        $stats = $comparison_result['statistics'];
        $recommendation = $comparison_result['recommendation'];

        $summary = "# Job Comparison Summary\n\n";
        $summary .= "Generated: " . current_time('F j, Y g:i A') . "\n\n";

        $summary .= "## Overview\n";
        $summary .= "- Jobs compared: {$stats['total_compared']}\n";
        $summary .= "- Average score: {$stats['avg_score']}/100\n";
        $summary .= "- Best match score: {$stats['max_score']}/100\n\n";

        $summary .= "## Recommendation\n";
        $summary .= "**{$recommendation['headline']}**\n";
        $summary .= "{$recommendation['message']}\n\n";

        $summary .= "## Detailed Results\n\n";

        foreach ($results as $idx => $result) {
            $rank = $idx + 1;
            $score = $result['score'] ?? 'N/A';
            $risk = ucfirst($result['risk_level'] ?? 'unknown');

            $summary .= "### #{$rank}: {$result['title']} at {$result['company']}\n";
            $summary .= "- Score: {$score}/100 ({$risk} risk)\n";

            if (isset($result['dealbreaker_count'])) {
                $summary .= "- Dealbreakers: {$result['dealbreaker_count']}\n";
            }
            if (isset($result['critical_count'])) {
                $summary .= "- Critical gaps: {$result['critical_count']}\n";
            }
            if (isset($result['keyword_match'])) {
                $summary .= "- Keyword match: {$result['keyword_match']}%\n";
            }

            $summary .= "\n";
        }

        return $summary;
    }

    /**
     * Get detailed analysis for a specific job from comparison
     *
     * @param string $cv_text CV text
     * @param array $job Job data with description
     * @return array Full analysis result
     */
    public function get_detailed_analysis($cv_text, $job) {
        // Switch to full analysis mode
        $full_analyzer = new SFFC_Gap_Analyzer();
        $full_analyzer->set_mode('ai_first');

        return $full_analyzer->analyze($job['description'], $cv_text);
    }
}
