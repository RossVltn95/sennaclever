<?php
/**
 * Report Generator
 *
 * Generates consistent report structure from both AI and fallback analysis.
 * Formats data for frontend display.
 *
 * @package SennaCareers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Report_Generator {

    /**
     * Risk level labels
     */
    private $risk_labels = [
        'high' => 'High Risk',
        'moderate' => 'Moderate Risk',
        'competitive' => 'Competitive',
        'strong' => 'Strong Fit',
    ];

    /**
     * Risk level colors
     */
    private $risk_colors = [
        'high' => '#dc2626',
        'moderate' => '#d97706',
        'competitive' => '#ca8a04',
        'strong' => '#16a34a',
    ];

    /**
     * Generate report from analysis result
     *
     * @param array $analysis_result Full analysis result from Gap Analyzer
     * @return array Formatted report for frontend
     */
    public function generate($analysis_result) {
        return [
            'meta' => $this->generate_meta($analysis_result),
            'verdict' => $this->generate_verdict($analysis_result),
            'metrics' => $this->generate_metrics($analysis_result),
            'sections' => [
                'dealbreakers' => $this->format_dealbreakers($analysis_result['dealbreakers'] ?? []),
                'critical_gaps' => $this->format_critical_gaps($analysis_result['critical_gaps'] ?? []),
                'improvements' => $this->format_improvements($analysis_result['improvements'] ?? []),
                'strengths' => $this->format_strengths($analysis_result['strengths'] ?? []),
                'keywords' => $this->format_keyword_table($analysis_result['keyword_analysis'] ?? []),
                'ats' => $this->format_ats_analysis($analysis_result['ats_analysis'] ?? []),
            ],
            'actions' => $this->generate_actions($analysis_result),
        ];
    }

    /**
     * Generate meta information
     */
    private function generate_meta($result) {
        return [
            'role_title' => $result['meta']['role_title'] ?? '',
            'company' => $result['meta']['company'] ?? '',
            'analysis_source' => $result['meta']['analysis_source'] ?? 'fallback',
            'analyzed_at' => $result['meta']['analyzed_at'] ?? current_time('mysql'),
            'report_id' => $result['meta']['report_id'] ?? null,
        ];
    }

    /**
     * Generate verdict section
     */
    private function generate_verdict($result) {
        $score = $result['score'] ?? 0;
        $risk_level = $result['risk_level'] ?? 'unknown';

        return [
            'score' => $score,
            'risk_level' => $risk_level,
            'risk_label' => $this->risk_labels[$risk_level] ?? 'Unknown',
            'risk_color' => $this->risk_colors[$risk_level] ?? '#6b7280',
            'summary' => $this->generate_summary($result),
            'recommendation' => $this->generate_recommendation($result),
        ];
    }

    /**
     * Generate summary text
     */
    private function generate_summary($result) {
        $score = $result['score'] ?? 0;
        $dealbreakers = count($result['dealbreakers'] ?? []);
        $critical = count($result['critical_gaps'] ?? []);
        $strengths = count($result['strengths'] ?? []);

        if ($score >= 80) {
            return "You're a strong candidate for this role. Your profile aligns well with the requirements.";
        } elseif ($score >= 60) {
            if ($dealbreakers > 0) {
                return "You have {$dealbreakers} dealbreaker(s) to address, but otherwise you're competitive. Focus on mitigating gaps.";
            }
            return "You're a competitive candidate with {$critical} areas to strengthen. Targeted improvements will help.";
        } elseif ($score >= 40) {
            if ($dealbreakers > 0) {
                return "You have {$dealbreakers} significant gap(s) that may be difficult to overcome. Consider alternative approaches.";
            }
            return "You have multiple gaps to address. Consider whether this role is the right target.";
        } else {
            return "This role has significant gaps relative to your profile. Consider targeting roles that better match your experience.";
        }
    }

    /**
     * Generate recommendation
     */
    private function generate_recommendation($result) {
        $options = $result['strategic_options'] ?? [];

        if (!empty($options)) {
            $top_option = $options[0];
            return $top_option['description'] ?? '';
        }

        $score = $result['score'] ?? 0;

        if ($score >= 70) {
            return "Apply now and begin interview preparation.";
        } elseif ($score >= 50) {
            return "Address critical gaps in your CV before applying.";
        } else {
            return "Consider targeting roles that better match your current profile.";
        }
    }

    /**
     * Generate key metrics
     */
    private function generate_metrics($result) {
        $keyword_analysis = $result['keyword_analysis'] ?? [];
        $ats = $result['ats_analysis'] ?? [];

        return [
            [
                'label' => 'Fit Score',
                'value' => ($result['score'] ?? 0) . '%',
                'sublabel' => $this->risk_labels[$result['risk_level'] ?? 'unknown'] ?? '',
            ],
            [
                'label' => 'Dealbreakers',
                'value' => count($result['dealbreakers'] ?? []),
                'sublabel' => 'Cannot Fix',
            ],
            [
                'label' => 'Critical Gaps',
                'value' => count($result['critical_gaps'] ?? []),
                'sublabel' => 'Must Address',
            ],
            [
                'label' => 'Keywords Match',
                'value' => ($keyword_analysis['match_rate'] ?? 0) . '%',
                'sublabel' => ($keyword_analysis['matched_keywords'] ?? 0) . '/' . ($keyword_analysis['total_jd_keywords'] ?? 0),
            ],
        ];
    }

    /**
     * Format dealbreakers section
     */
    private function format_dealbreakers($dealbreakers) {
        $formatted = [];

        foreach ($dealbreakers as $db) {
            $formatted[] = [
                'type' => $db['type'] ?? 'unknown',
                'title' => $db['title'] ?? 'Issue',
                'icon' => '❌',
                'severity' => 'dealbreaker',
                'details' => [
                    'jd_requires' => $db['requirement'] ?? '',
                    'cv_shows' => $db['candidate_has'] ?? '',
                ],
                'reality_check' => $db['reality_check'] ?? '',
                'can_overcome' => $db['can_overcome'] ?? false,
                'fix_strategy' => $db['fix_strategy'] ?? '',
            ];
        }

        return [
            'title' => 'Dealbreakers',
            'icon' => '❌',
            'count' => count($formatted),
            'items' => $formatted,
        ];
    }

    /**
     * Format critical gaps section
     */
    private function format_critical_gaps($gaps) {
        $formatted = [];

        foreach ($gaps as $gap) {
            $formatted[] = [
                'type' => $gap['type'] ?? 'unknown',
                'title' => $gap['title'] ?? 'Gap',
                'icon' => '⚠️',
                'severity' => 'critical',
                'description' => $gap['gap_description'] ?? '',
                'impact' => $gap['impact'] ?? '',
                'fix' => $gap['fix'] ?? '',
            ];
        }

        return [
            'title' => 'Critical Gaps',
            'icon' => '⚠️',
            'count' => count($formatted),
            'items' => $formatted,
        ];
    }

    /**
     * Format improvements section
     */
    private function format_improvements($improvements) {
        $formatted = [];

        foreach ($improvements as $imp) {
            $formatted[] = [
                'type' => $imp['type'] ?? 'unknown',
                'title' => $imp['title'] ?? 'Improvement',
                'icon' => '💡',
                'severity' => 'improvement',
                'current' => $imp['current'] ?? '',
                'suggested' => $imp['suggested'] ?? '',
                'impact' => $imp['impact'] ?? '',
            ];
        }

        return [
            'title' => 'Improvements',
            'icon' => '💡',
            'count' => count($formatted),
            'items' => $formatted,
        ];
    }

    /**
     * Format strengths section
     */
    private function format_strengths($strengths) {
        $formatted = [];

        foreach ($strengths as $strength) {
            $formatted[] = [
                'type' => $strength['type'] ?? 'unknown',
                'title' => $strength['title'] ?? 'Strength',
                'icon' => '💪',
                'description' => $strength['description'] ?? '',
                'leverage' => $strength['leverage'] ?? '',
            ];
        }

        return [
            'title' => 'Your Strengths',
            'icon' => '💪',
            'count' => count($formatted),
            'items' => $formatted,
        ];
    }

    /**
     * Format keyword analysis table
     */
    private function format_keyword_table($keyword_analysis) {
        $rows = [];
        $details = $keyword_analysis['details'] ?? [];

        // Sort by importance and match status
        uasort($details, function ($a, $b) {
            // Prioritize unmatched important keywords
            if (!$a['match'] && $a['importance'] === 'high') return -1;
            if (!$b['match'] && $b['importance'] === 'high') return 1;
            if (!$a['match']) return -1;
            if (!$b['match']) return 1;
            return $b['jd_count'] - $a['jd_count'];
        });

        foreach ($details as $key => $detail) {
            $rows[] = [
                'keyword' => $detail['keyword'],
                'jd_count' => $detail['jd_count'],
                'cv_count' => $detail['cv_count'],
                'match' => $detail['match'],
                'importance' => $detail['importance'],
                'status' => $detail['match'] ? 'match' : 'gap',
                'status_icon' => $detail['match'] ? '✅' : '❌',
            ];
        }

        return [
            'title' => 'Keyword Analysis',
            'summary' => [
                'match_rate' => $keyword_analysis['match_rate'] ?? 0,
                'matched' => $keyword_analysis['matched_keywords'] ?? 0,
                'total' => $keyword_analysis['total_jd_keywords'] ?? 0,
            ],
            'missing' => array_slice($keyword_analysis['missing_keywords'] ?? [], 0, 10),
            'rows' => array_slice($rows, 0, 15),
        ];
    }

    /**
     * Format ATS analysis
     */
    private function format_ats_analysis($ats) {
        if (empty($ats)) {
            return null;
        }

        return [
            'title' => 'ATS Compatibility',
            'score' => $ats['total_score'] ?? 0,
            'max_score' => $ats['max_score'] ?? 100,
            'percentage' => $ats['percentage'] ?? 0,
            'verdict' => $ats['verdict'] ?? 'unknown',
            'verdict_label' => $this->get_ats_verdict_label($ats['verdict'] ?? ''),
            'breakdown' => $ats['breakdown'] ?? [],
            'issues' => array_slice($ats['issues'] ?? [], 0, 5),
        ];
    }

    /**
     * Get ATS verdict label
     */
    private function get_ats_verdict_label($verdict) {
        $labels = [
            'likely_pass' => 'Likely to Pass',
            'at_risk' => 'At Risk',
            'likely_fail' => 'Likely to Fail',
        ];
        return $labels[$verdict] ?? 'Unknown';
    }

    /**
     * Generate action buttons
     */
    private function generate_actions($result) {
        $options = $result['strategic_options'] ?? [];
        $actions = [];

        // Primary action based on score
        if ($result['score'] >= 60) {
            $actions[] = [
                'type' => 'primary',
                'label' => 'Fix My CV',
                'icon' => '🔧',
                'action' => 'fix_cv',
            ];
        }

        // Cover letter action if there are gaps
        if (count($result['dealbreakers'] ?? []) > 0 || count($result['critical_gaps'] ?? []) > 0) {
            $actions[] = [
                'type' => 'secondary',
                'label' => 'Write Cover Letter',
                'icon' => '✉️',
                'action' => 'cover_letter',
            ];
        }

        // Save report
        $actions[] = [
            'type' => 'tertiary',
            'label' => 'Save Report',
            'icon' => '💾',
            'action' => 'save_report',
        ];

        // Export PDF
        $actions[] = [
            'type' => 'tertiary',
            'label' => 'Export PDF',
            'icon' => '📄',
            'action' => 'export_pdf',
        ];

        // Re-analyze
        $actions[] = [
            'type' => 'tertiary',
            'label' => 'Re-analyze',
            'icon' => '🔄',
            'action' => 'reanalyze',
        ];

        return [
            'primary' => array_filter($actions, fn($a) => $a['type'] === 'primary'),
            'secondary' => array_filter($actions, fn($a) => $a['type'] === 'secondary'),
            'tertiary' => array_filter($actions, fn($a) => $a['type'] === 'tertiary'),
            'next_steps' => $options,
        ];
    }

    /**
     * Generate HTML for a section (used by PDF export)
     */
    public function render_section_html($section_key, $section_data) {
        ob_start();

        switch ($section_key) {
            case 'dealbreakers':
            case 'critical_gaps':
            case 'improvements':
            case 'strengths':
                $this->render_gap_section($section_data);
                break;

            case 'keywords':
                $this->render_keyword_section($section_data);
                break;

            case 'ats':
                $this->render_ats_section($section_data);
                break;
        }

        return ob_get_clean();
    }

    /**
     * Render gap section HTML
     */
    private function render_gap_section($section) {
        if (empty($section['items'])) return;
        ?>
        <div class="sffc-planner-section">
            <h4 class="sffc-planner-section-header">
                <span class="sffc-planner-section-icon"><?php echo esc_html($section['icon']); ?></span>
                <span class="sffc-planner-section-title"><?php echo esc_html($section['title']); ?></span>
                <span class="sffc-planner-section-count"><?php echo esc_html($section['count']); ?></span>
            </h4>
            <div class="sffc-planner-section-items">
                <?php foreach ($section['items'] as $item): ?>
                    <div class="sffc-planner-gap-card sffc-planner-gap-card--<?php echo esc_attr($item['severity']); ?>">
                        <div class="sffc-planner-gap-header">
                            <span class="sffc-planner-gap-icon"><?php echo esc_html($item['icon']); ?></span>
                            <span class="sffc-planner-gap-title"><?php echo esc_html($item['title']); ?></span>
                        </div>
                        <div class="sffc-planner-gap-body">
                            <?php if (!empty($item['details'])): ?>
                                <div class="sffc-planner-gap-comparison">
                                    <div class="sffc-planner-gap-comparison-item">
                                        <span class="sffc-planner-gap-comparison-label">JD Requires</span>
                                        <span class="sffc-planner-gap-comparison-value"><?php echo esc_html($item['details']['jd_requires']); ?></span>
                                    </div>
                                    <div class="sffc-planner-gap-comparison-item">
                                        <span class="sffc-planner-gap-comparison-label">Your CV Shows</span>
                                        <span class="sffc-planner-gap-comparison-value"><?php echo esc_html($item['details']['cv_shows']); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($item['reality_check'])): ?>
                                <div class="sffc-planner-gap-reality-check">
                                    <strong>Reality Check:</strong> <?php echo esc_html($item['reality_check']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($item['fix_strategy']) || !empty($item['fix'])): ?>
                                <div class="sffc-planner-gap-fix">
                                    <span class="sffc-planner-gap-fix-label">How to Fix</span>
                                    <span class="sffc-planner-gap-fix-text"><?php echo esc_html($item['fix_strategy'] ?? $item['fix']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render keyword section HTML
     */
    private function render_keyword_section($section) {
        ?>
        <div class="sffc-planner-section">
            <h4 class="sffc-planner-section-header">
                <span class="sffc-planner-section-icon">📊</span>
                <span class="sffc-planner-section-title"><?php echo esc_html($section['title']); ?></span>
                <span class="sffc-planner-section-count"><?php echo esc_html($section['summary']['match_rate']); ?>%</span>
            </h4>
            <div class="sffc-planner-keyword-chart">
                <?php foreach ($section['rows'] as $row): ?>
                    <div class="sffc-planner-keyword-row">
                        <span class="sffc-planner-keyword-label"><?php echo esc_html($row['keyword']); ?></span>
                        <div class="sffc-planner-keyword-bar">
                            <div class="sffc-planner-keyword-bar-fill sffc-planner-keyword-bar-fill--<?php echo $row['match'] ? 'match' : 'gap'; ?>"
                                 style="width: <?php echo min(100, ($row['cv_count'] / max(1, $row['jd_count'])) * 100); ?>%"></div>
                        </div>
                        <span class="sffc-planner-keyword-count"><?php echo esc_html($row['cv_count']); ?>/<?php echo esc_html($row['jd_count']); ?></span>
                        <span class="sffc-planner-keyword-status sffc-planner-keyword-status--<?php echo $row['match'] ? 'match' : 'gap'; ?>">
                            <?php echo esc_html($row['status_icon']); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($section['missing'])): ?>
                <div class="sffc-planner-missing-keywords">
                    <strong>Missing Keywords:</strong>
                    <?php echo esc_html(implode(', ', $section['missing'])); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render ATS section HTML
     */
    private function render_ats_section($section) {
        if (empty($section)) return;
        ?>
        <div class="sffc-planner-section sffc-planner-section--ats">
            <h4 class="sffc-planner-section-header">
                <span class="sffc-planner-section-icon">🤖</span>
                <span class="sffc-planner-section-title"><?php echo esc_html($section['title']); ?></span>
                <span class="sffc-planner-section-count"><?php echo esc_html($section['percentage']); ?>%</span>
            </h4>
            <div class="sffc-planner-ats-score">
                <div class="sffc-planner-ats-score-value"><?php echo esc_html($section['score']); ?></div>
                <div class="sffc-planner-ats-score-label"><?php echo esc_html($section['verdict_label']); ?></div>
            </div>
            <?php if (!empty($section['issues'])): ?>
                <div class="sffc-planner-ats-issues">
                    <?php foreach ($section['issues'] as $issue): ?>
                        <div class="sffc-planner-ats-issue sffc-planner-ats-issue--<?php echo esc_attr($issue['severity'] ?? 'medium'); ?>">
                            <span class="sffc-planner-ats-issue-message"><?php echo esc_html($issue['message'] ?? ''); ?></span>
                            <?php if (!empty($issue['fix'])): ?>
                                <span class="sffc-planner-ats-issue-fix">Fix: <?php echo esc_html($issue['fix']); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
