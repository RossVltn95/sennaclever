<?php
/**
 * PDF Exporter
 *
 * Generates PDF reports from application analysis.
 * Uses server-side HTML generation for client-side PDF rendering.
 *
 * @package SennaCareers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_PDF_Exporter {

    /**
     * Report Generator instance
     */
    private $report_generator;

    /**
     * Constructor
     */
    public function __construct() {
        $this->report_generator = new SFFC_Report_Generator();
    }

    /**
     * Generate HTML for PDF export
     *
     * @param array $analysis_result Full analysis result
     * @param array $options Export options
     * @return string HTML content for PDF
     */
    public function generate_html($analysis_result, $options = []) {
        $defaults = [
            'include_charts' => true,
            'include_keywords' => true,
            'include_ats' => true,
            'include_recommendations' => true,
            'branding' => true,
        ];

        $options = array_merge($defaults, $options);

        $html = $this->get_pdf_styles();
        $html .= '<div class="pdf-container">';

        // Header
        $html .= $this->render_header($analysis_result, $options);

        // Executive Summary
        $html .= $this->render_executive_summary($analysis_result);

        // Score Overview
        $html .= $this->render_score_section($analysis_result);

        // Dealbreakers
        if (!empty($analysis_result['dealbreakers'])) {
            $html .= $this->render_dealbreakers($analysis_result['dealbreakers']);
        }

        // Critical Gaps
        if (!empty($analysis_result['critical_gaps'])) {
            $html .= $this->render_critical_gaps($analysis_result['critical_gaps']);
        }

        // Improvements
        if (!empty($analysis_result['improvements'])) {
            $html .= $this->render_improvements($analysis_result['improvements']);
        }

        // Strengths
        if (!empty($analysis_result['strengths'])) {
            $html .= $this->render_strengths($analysis_result['strengths']);
        }

        // Keyword Analysis
        if ($options['include_keywords'] && !empty($analysis_result['keyword_analysis'])) {
            $html .= $this->render_keyword_analysis($analysis_result['keyword_analysis']);
        }

        // ATS Analysis
        if ($options['include_ats'] && !empty($analysis_result['ats_analysis'])) {
            $html .= $this->render_ats_analysis($analysis_result['ats_analysis']);
        }

        // Strategic Options
        if ($options['include_recommendations'] && !empty($analysis_result['strategic_options'])) {
            $html .= $this->render_strategic_options($analysis_result['strategic_options']);
        }

        // Footer
        $html .= $this->render_footer($options);

        $html .= '</div>';

        return $html;
    }

    /**
     * Get PDF-specific styles
     */
    private function get_pdf_styles() {
        return <<<STYLES
<style>
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        font-size: 11px;
        line-height: 1.5;
        color: #1a1a1a;
    }

    .pdf-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 40px;
    }

    .pdf-header {
        text-align: center;
        border-bottom: 2px solid #1a1a1a;
        padding-bottom: 20px;
        margin-bottom: 30px;
    }

    .pdf-header h1 {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 10px;
        letter-spacing: -0.5px;
    }

    .pdf-header .meta {
        color: #666;
        font-size: 12px;
    }

    .section {
        margin-bottom: 30px;
        page-break-inside: avoid;
    }

    .section-title {
        font-size: 14px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-bottom: 1px solid #ddd;
        padding-bottom: 8px;
        margin-bottom: 15px;
    }

    .score-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .score-number {
        font-size: 48px;
        font-weight: 700;
        color: #1a1a1a;
    }

    .score-label {
        font-size: 12px;
        color: #666;
        text-transform: uppercase;
    }

    .risk-badge {
        display: inline-block;
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .risk-high { background: #fee2e2; color: #dc2626; }
    .risk-moderate { background: #fef3c7; color: #d97706; }
    .risk-competitive { background: #dbeafe; color: #2563eb; }
    .risk-strong { background: #d1fae5; color: #059669; }

    .issue-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 15px;
        margin-bottom: 12px;
    }

    .issue-header {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }

    .issue-severity {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 10px;
    }

    .severity-high { background: #dc2626; }
    .severity-medium { background: #f59e0b; }
    .severity-low { background: #10b981; }

    .issue-title {
        font-weight: 600;
        font-size: 12px;
    }

    .issue-body {
        font-size: 11px;
        color: #4b5563;
        margin-left: 18px;
    }

    .issue-fix {
        background: #f0fdf4;
        border-left: 3px solid #10b981;
        padding: 8px 12px;
        margin-top: 10px;
        font-size: 11px;
    }

    .keyword-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
    }

    .keyword-item {
        display: flex;
        align-items: center;
        padding: 6px 10px;
        background: #f3f4f6;
        border-radius: 4px;
        font-size: 10px;
    }

    .keyword-match { background: #d1fae5; }
    .keyword-missing { background: #fee2e2; }

    .keyword-status {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        margin-right: 8px;
    }

    .match-yes { background: #10b981; }
    .match-no { background: #ef4444; }

    .ats-score {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .ats-metric {
        flex: 1;
        min-width: 120px;
        background: #f8f9fa;
        padding: 12px;
        border-radius: 6px;
        text-align: center;
    }

    .ats-metric-value {
        font-size: 24px;
        font-weight: 700;
    }

    .ats-metric-label {
        font-size: 10px;
        color: #666;
        text-transform: uppercase;
    }

    .strategy-card {
        display: flex;
        align-items: flex-start;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 6px;
        margin-bottom: 10px;
    }

    .strategy-number {
        width: 24px;
        height: 24px;
        background: #1a1a1a;
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 600;
        margin-right: 15px;
        flex-shrink: 0;
    }

    .strategy-content h4 {
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .strategy-content p {
        font-size: 11px;
        color: #4b5563;
    }

    .probability {
        display: inline-block;
        padding: 2px 8px;
        background: #e5e7eb;
        border-radius: 10px;
        font-size: 10px;
        margin-top: 8px;
    }

    .strength-card {
        background: #f0fdf4;
        border-left: 4px solid #10b981;
        padding: 12px 15px;
        margin-bottom: 10px;
    }

    .strength-card h4 {
        font-size: 12px;
        font-weight: 600;
        color: #059669;
        margin-bottom: 5px;
    }

    .strength-card p {
        font-size: 11px;
        color: #4b5563;
    }

    .pdf-footer {
        margin-top: 40px;
        padding-top: 20px;
        border-top: 1px solid #e5e7eb;
        text-align: center;
        font-size: 10px;
        color: #9ca3af;
    }

    .page-break {
        page-break-before: always;
    }
</style>
STYLES;
    }

    /**
     * Render PDF header
     */
    private function render_header($analysis, $options) {
        $role = esc_html($analysis['meta']['role_title'] ?? 'Unknown Role');
        $company = esc_html($analysis['meta']['company'] ?? 'Unknown Company');
        $date = date('F j, Y', strtotime($analysis['meta']['analyzed_at'] ?? 'now'));

        $html = '<div class="pdf-header">';
        $html .= '<h1>Application Gap Analysis</h1>';
        $html .= '<div class="meta">';
        $html .= "<strong>{$role}</strong> at <strong>{$company}</strong><br>";
        $html .= "Generated: {$date}";
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render executive summary
     */
    private function render_executive_summary($analysis) {
        $score = $analysis['score'] ?? 0;
        $risk = $analysis['risk_level'] ?? 'unknown';
        $dealbreakers = count($analysis['dealbreakers'] ?? []);
        $critical = count($analysis['critical_gaps'] ?? []);

        $verdict = $this->get_verdict_text($risk, $score, $dealbreakers);

        $html = '<div class="section">';
        $html .= '<h2 class="section-title">Executive Summary</h2>';
        $html .= '<p style="font-size: 12px; margin-bottom: 15px;">' . esc_html($verdict) . '</p>';

        $html .= '<div style="display: flex; gap: 20px;">';
        $html .= '<div><strong>Score:</strong> ' . $score . '/100</div>';
        $html .= '<div><strong>Risk Level:</strong> ' . ucfirst($risk) . '</div>';
        $html .= '<div><strong>Dealbreakers:</strong> ' . $dealbreakers . '</div>';
        $html .= '<div><strong>Critical Gaps:</strong> ' . $critical . '</div>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Get verdict text based on analysis
     */
    private function get_verdict_text($risk, $score, $dealbreakers) {
        if ($dealbreakers > 0) {
            return "This application has {$dealbreakers} dealbreaker(s) that significantly reduce your chances. Address these critical issues before applying.";
        }

        switch ($risk) {
            case 'strong':
                return "Strong fit for this role. Your profile aligns well with the requirements. Apply promptly and prepare for interviews.";
            case 'competitive':
                return "Competitive candidate with minor gaps. Make targeted improvements to your CV to maximize your chances.";
            case 'moderate':
                return "Moderate fit with some notable gaps. Consider whether this role is the right target or if CV optimization could close the gaps.";
            case 'high':
                return "Significant gaps exist between your profile and this role. Consider targeting roles that better match your current experience.";
            default:
                return "Analysis complete. Review the detailed findings below.";
        }
    }

    /**
     * Render score section
     */
    private function render_score_section($analysis) {
        $score = $analysis['score'] ?? 0;
        $risk = $analysis['risk_level'] ?? 'unknown';

        $html = '<div class="section">';
        $html .= '<div class="score-card">';

        $html .= '<div>';
        $html .= '<div class="score-number">' . $score . '</div>';
        $html .= '<div class="score-label">Overall Score</div>';
        $html .= '</div>';

        $html .= '<div>';
        $html .= '<span class="risk-badge risk-' . $risk . '">' . ucfirst($risk) . ' Risk</span>';
        $html .= '</div>';

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render dealbreakers section
     */
    private function render_dealbreakers($dealbreakers) {
        $html = '<div class="section">';
        $html .= '<h2 class="section-title" style="color: #dc2626;">Dealbreakers</h2>';

        foreach ($dealbreakers as $item) {
            $html .= '<div class="issue-card" style="border-left: 3px solid #dc2626;">';
            $html .= '<div class="issue-header">';
            $html .= '<span class="issue-severity severity-high"></span>';
            $html .= '<span class="issue-title">' . esc_html($item['title'] ?? 'Dealbreaker') . '</span>';
            $html .= '</div>';

            if (!empty($item['requirement'])) {
                $html .= '<div class="issue-body">';
                $html .= '<strong>Required:</strong> ' . esc_html($item['requirement']) . '<br>';
                $html .= '<strong>You have:</strong> ' . esc_html($item['candidate_has'] ?? 'Not found');
                $html .= '</div>';
            }

            if (!empty($item['fix_strategy'])) {
                $html .= '<div class="issue-fix">';
                $html .= '<strong>Strategy:</strong> ' . esc_html($item['fix_strategy']);
                $html .= '</div>';
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render critical gaps section
     */
    private function render_critical_gaps($gaps) {
        $html = '<div class="section">';
        $html .= '<h2 class="section-title" style="color: #d97706;">Critical Gaps</h2>';

        foreach ($gaps as $gap) {
            $html .= '<div class="issue-card" style="border-left: 3px solid #f59e0b;">';
            $html .= '<div class="issue-header">';
            $html .= '<span class="issue-severity severity-medium"></span>';
            $html .= '<span class="issue-title">' . esc_html($gap['title'] ?? 'Gap') . '</span>';
            $html .= '</div>';

            $html .= '<div class="issue-body">';
            $html .= esc_html($gap['gap_description'] ?? $gap['description'] ?? '');
            $html .= '</div>';

            if (!empty($gap['fix'])) {
                $html .= '<div class="issue-fix">';
                $html .= '<strong>Fix:</strong> ' . esc_html($gap['fix']);
                $html .= '</div>';
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render improvements section
     */
    private function render_improvements($improvements) {
        $html = '<div class="section">';
        $html .= '<h2 class="section-title">Suggested Improvements</h2>';

        foreach ($improvements as $item) {
            $html .= '<div class="issue-card">';
            $html .= '<div class="issue-header">';
            $html .= '<span class="issue-severity severity-low"></span>';
            $html .= '<span class="issue-title">' . esc_html($item['title'] ?? 'Improvement') . '</span>';
            $html .= '</div>';

            if (!empty($item['current'])) {
                $html .= '<div class="issue-body">';
                $html .= '<strong>Current:</strong> ' . esc_html($item['current']) . '<br>';
                $html .= '<strong>Suggested:</strong> ' . esc_html($item['suggested'] ?? '');
                $html .= '</div>';
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render strengths section
     */
    private function render_strengths($strengths) {
        $html = '<div class="section">';
        $html .= '<h2 class="section-title" style="color: #059669;">Your Strengths</h2>';

        foreach ($strengths as $strength) {
            $html .= '<div class="strength-card">';
            $html .= '<h4>' . esc_html($strength['title'] ?? 'Strength') . '</h4>';
            $html .= '<p>' . esc_html($strength['description'] ?? '') . '</p>';

            if (!empty($strength['leverage'])) {
                $html .= '<p style="margin-top: 8px;"><strong>Leverage:</strong> ' . esc_html($strength['leverage']) . '</p>';
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render keyword analysis
     */
    private function render_keyword_analysis($keyword_data) {
        $html = '<div class="section page-break">';
        $html .= '<h2 class="section-title">Keyword Analysis</h2>';

        $match_rate = $keyword_data['match_rate'] ?? 0;
        $html .= '<p style="margin-bottom: 15px;">';
        $html .= "<strong>Match Rate:</strong> {$match_rate}% ";
        $html .= "({$keyword_data['matched_keywords']} of {$keyword_data['total_jd_keywords']} keywords found)";
        $html .= '</p>';

        $html .= '<div class="keyword-grid">';

        foreach ($keyword_data['details'] ?? [] as $keyword => $detail) {
            $match = $detail['match'] ? 'keyword-match' : 'keyword-missing';
            $status = $detail['match'] ? 'match-yes' : 'match-no';

            $html .= '<div class="keyword-item ' . $match . '">';
            $html .= '<span class="keyword-status ' . $status . '"></span>';
            $html .= esc_html($detail['keyword']);
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render ATS analysis
     */
    private function render_ats_analysis($ats_data) {
        $html = '<div class="section">';
        $html .= '<h2 class="section-title">ATS Compatibility</h2>';

        $html .= '<div class="ats-score">';

        $breakdown = $ats_data['breakdown'] ?? [];

        $metrics = [
            'keywords' => ['label' => 'Keywords', 'max' => 30],
            'format' => ['label' => 'Format', 'max' => 25],
            'structure' => ['label' => 'Structure', 'max' => 20],
            'title_match' => ['label' => 'Title Match', 'max' => 15],
            'recency' => ['label' => 'Recency', 'max' => 10],
        ];

        foreach ($metrics as $key => $meta) {
            if (isset($breakdown[$key])) {
                $score = $breakdown[$key]['score'] ?? 0;
                $max = $meta['max'];
                $html .= '<div class="ats-metric">';
                $html .= '<div class="ats-metric-value">' . $score . '/' . $max . '</div>';
                $html .= '<div class="ats-metric-label">' . $meta['label'] . '</div>';
                $html .= '</div>';
            }
        }

        $html .= '</div>';

        // ATS verdict
        $verdict = $ats_data['verdict'] ?? 'unknown';
        $verdict_text = [
            'likely_pass' => 'Your CV should pass most ATS filters.',
            'at_risk' => 'Your CV may be filtered by some ATS systems.',
            'likely_fail' => 'Your CV has issues that may cause ATS rejection.',
        ];

        $html .= '<p style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px;">';
        $html .= '<strong>Verdict:</strong> ' . ($verdict_text[$verdict] ?? 'Analysis complete.');
        $html .= '</p>';

        // ATS issues
        if (!empty($ats_data['issues'])) {
            $html .= '<div style="margin-top: 15px;">';
            $html .= '<strong>Issues to Fix:</strong>';
            $html .= '<ul style="margin-top: 8px; padding-left: 20px;">';

            foreach (array_slice($ats_data['issues'], 0, 5) as $issue) {
                $html .= '<li style="margin-bottom: 5px;">';
                $html .= esc_html($issue['message'] ?? '');
                if (!empty($issue['fix'])) {
                    $html .= ' <em>(' . esc_html($issue['fix']) . ')</em>';
                }
                $html .= '</li>';
            }

            $html .= '</ul>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render strategic options
     */
    private function render_strategic_options($options) {
        $html = '<div class="section">';
        $html .= '<h2 class="section-title">Strategic Options</h2>';

        foreach ($options as $idx => $option) {
            $num = $idx + 1;

            $html .= '<div class="strategy-card">';
            $html .= '<span class="strategy-number">' . $num . '</span>';
            $html .= '<div class="strategy-content">';
            $html .= '<h4>' . esc_html($option['title'] ?? 'Option') . '</h4>';
            $html .= '<p>' . esc_html($option['description'] ?? '') . '</p>';

            if (!empty($option['success_probability'])) {
                $html .= '<span class="probability">Success: ' . esc_html($option['success_probability']) . '</span>';
            }

            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render footer
     */
    private function render_footer($options) {
        $html = '<div class="pdf-footer">';

        if ($options['branding']) {
            $html .= 'Generated by MENA Careers Application Planner<br>';
        }

        $html .= 'This analysis is for guidance only. Results may vary based on individual circumstances.';
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate filename for PDF
     *
     * @param array $analysis Analysis result
     * @return string Filename
     */
    public function generate_filename($analysis) {
        $role = sanitize_title($analysis['meta']['role_title'] ?? 'role');
        $company = sanitize_title($analysis['meta']['company'] ?? 'company');
        $date = date('Y-m-d');

        return "gap-analysis-{$role}-{$company}-{$date}.pdf";
    }

    /**
     * Save report to database for later access
     *
     * @param int $user_id User ID
     * @param array $analysis Analysis result
     * @return int|false Report ID or false on failure
     */
    public function save_report($user_id, $analysis) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_application_reports';

        $data = [
            'user_id' => $user_id,
            'role_title' => $analysis['meta']['role_title'] ?? '',
            'company' => $analysis['meta']['company'] ?? '',
            'score' => $analysis['score'] ?? 0,
            'risk_level' => $analysis['risk_level'] ?? 'unknown',
            'analysis_data' => json_encode($analysis),
            'created_at' => current_time('mysql'),
        ];

        $result = $wpdb->insert($table, $data, ['%d', '%s', '%s', '%d', '%s', '%s', '%s']);

        if ($result === false) {
            error_log('PDF Exporter: Failed to save report - ' . $wpdb->last_error);
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get saved reports for user
     *
     * @param int $user_id User ID
     * @param int $limit Number of reports to retrieve
     * @return array Reports
     */
    public function get_user_reports($user_id, $limit = 10) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_application_reports';

        return $wpdb->get_results($wpdb->prepare("
            SELECT id, role_title, company, score, risk_level, created_at
            FROM {$table}
            WHERE user_id = %d
            ORDER BY created_at DESC
            LIMIT %d
        ", $user_id, $limit), ARRAY_A);
    }

    /**
     * Get specific report
     *
     * @param int $report_id Report ID
     * @param int $user_id User ID (for security)
     * @return array|null Report data
     */
    public function get_report($report_id, $user_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_application_reports';

        $report = $wpdb->get_row($wpdb->prepare("
            SELECT *
            FROM {$table}
            WHERE id = %d AND user_id = %d
        ", $report_id, $user_id), ARRAY_A);

        if ($report && !empty($report['analysis_data'])) {
            $report['analysis_data'] = json_decode($report['analysis_data'], true);
        }

        return $report;
    }

    /**
     * Delete report
     *
     * @param int $report_id Report ID
     * @param int $user_id User ID (for security)
     * @return bool Success
     */
    public function delete_report($report_id, $user_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_application_reports';

        $result = $wpdb->delete($table, [
            'id' => $report_id,
            'user_id' => $user_id,
        ], ['%d', '%d']);

        return $result !== false;
    }
}
