<?php
/**
 * Gap Analyzer
 *
 * Orchestrates the analysis pipeline:
 * 1. Parses JD and CV
 * 2. Runs preliminary rule-based analysis
 * 3. Attempts Claude API analysis
 * 4. Falls back to enhanced rule-based if needed
 * 5. Calculates unified score
 *
 * @package SennaCareers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Gap_Analyzer {

    /**
     * Dependencies
     */
    private $jd_parser;
    private $cv_parser;
    private $ai_analyzer;
    private $fallback_analyzer;
    private $ats_analyzer;
    private $knowledge_base;

    /**
     * Analysis mode
     * ai_first | fallback_only | ai_only
     */
    private $mode = 'ai_first';

    /**
     * Constructor
     */
    public function __construct() {
        $this->jd_parser = new SFFC_JD_Parser();
        $this->cv_parser = new SFFC_Planner_CV_Parser();
        $this->ai_analyzer = new SFFC_AI_Analyzer();
        $this->fallback_analyzer = new SFFC_Fallback_Analyzer();
        $this->ats_analyzer = new SFFC_ATS_Analyzer();
        $this->knowledge_base = SFFC_Knowledge_Base::get_instance();
    }

    /**
     * Set analysis mode
     *
     * @param string $mode ai_first | fallback_only | ai_only
     */
    public function set_mode($mode) {
        $this->mode = $mode;
    }

    /**
     * Main analysis method
     *
     * @param string $jd_text Raw job description text
     * @param string $cv_text Raw CV text
     * @return array Complete analysis result
     */
    public function analyze($jd_text, $cv_text) {
        // Step 1: Parse inputs
        $jd_data = $this->jd_parser->parse($jd_text);
        $cv_data = $this->cv_parser->parse($cv_text);

        // Step 2: Run preliminary rule-based analysis
        $preliminary = $this->run_preliminary_analysis($jd_data, $cv_data);

        // Step 3: Run ATS analysis
        $ats_result = $this->ats_analyzer->analyze($cv_text, $jd_data);

        // Step 4: Attempt AI analysis (if enabled)
        $analysis_result = null;
        $source = 'fallback';

        if ($this->mode !== 'fallback_only') {
            $ai_result = $this->ai_analyzer->analyze($jd_data, $cv_data, $preliminary);

            if ($ai_result['success']) {
                $analysis_result = $ai_result['data'];
                $source = 'ai';
            }
        }

        // Step 5: Fallback to enhanced rule-based if needed
        if (!$analysis_result && $this->mode !== 'ai_only') {
            $analysis_result = $this->fallback_analyzer->analyze($jd_data, $cv_data, $preliminary);
            $source = 'fallback';
        }

        // Step 6: Calculate unified score
        $score = $this->calculate_score($analysis_result);

        // Step 7: Compile final result
        return [
            'meta' => [
                'role_title' => $jd_data['role_title'],
                'company' => $jd_data['company'],
                'analysis_source' => $source,
                'analyzed_at' => current_time('mysql'),
            ],
            'jd_summary' => $this->jd_parser->get_summary($jd_data),
            'cv_summary' => $this->cv_parser->get_summary($cv_data),
            'score' => $score['score'],
            'risk_level' => $score['risk_level'],
            'score_breakdown' => $score['breakdown'],
            'dealbreakers' => $analysis_result['dealbreakers'] ?? [],
            'critical_gaps' => $analysis_result['critical_gaps'] ?? [],
            'improvements' => $analysis_result['improvements'] ?? [],
            'strengths' => $analysis_result['strengths'] ?? [],
            'keyword_analysis' => $this->analyze_keywords($jd_data, $cv_data),
            'ats_analysis' => $ats_result,
            'strategic_options' => $analysis_result['strategic_options'] ?? $this->generate_strategic_options($score, $analysis_result),
            'raw_data' => [
                'jd' => $jd_data,
                'cv' => $cv_data,
            ],
        ];
    }

    /**
     * Structured analysis payload tailored for inst-terminal.
     *
     * Returns the Claude-style schema expected by the terminal UI while using
     * deterministic analysis as the core engine whenever AI is unavailable.
     *
     * @param string $jd_text Raw job description text
     * @param string $cv_text Raw CV text
     * @return array
     */
    public function analyze_for_terminal($jd_text, $cv_text) {
        $jd_data = $this->jd_parser->parse($jd_text);
        $cv_data = $this->cv_parser->parse($cv_text);
        $preliminary = $this->run_preliminary_analysis($jd_data, $cv_data);
        $ats_result = $this->ats_analyzer->analyze($cv_text, $jd_data);

        if ($this->mode !== 'fallback_only') {
            $ai_result = $this->ai_analyzer->analyze($jd_data, $cv_data, $preliminary);
            if (!empty($ai_result['success']) && !empty($ai_result['data']) && is_array($ai_result['data'])) {
                $ai_result['data']['source'] = 'claude';
                $ai_result['data']['analysis_source'] = 'claude';
                $ai_result['data']['meta'] = [
                    'role_title' => (string) ($jd_data['role_title'] ?? ''),
                    'company' => (string) ($jd_data['company'] ?? ''),
                    'analysis_source' => 'claude',
                    'analyzed_at' => current_time('mysql'),
                ];

                return $ai_result['data'];
            }
        }

        $fallback_result = $this->fallback_analyzer->analyze($jd_data, $cv_data, $preliminary);

        return $this->build_terminal_fallback_result($jd_data, $cv_data, $preliminary, $ats_result, $fallback_result);
    }

    /**
     * Run preliminary rule-based analysis
     *
     * Quick scan to identify obvious gaps before AI/full analysis
     */
    private function run_preliminary_analysis($jd_data, $cv_data) {
        $gaps = [];

        // 1. Experience gap
        if ($jd_data['experience']['min_years'] && $cv_data['total_years']) {
            $gap = $jd_data['experience']['min_years'] - $cv_data['total_years'];
            if ($gap > 0) {
                $gaps['experience'] = [
                    'type' => 'experience_gap',
                    'required' => $jd_data['experience']['min_years'],
                    'candidate_has' => $cv_data['total_years'],
                    'gap' => $gap,
                    'severity' => $gap > 2 ? 'dealbreaker' : ($gap > 1 ? 'critical' : 'improvement'),
                ];
            }
        }

        // 2. Missing credentials
        foreach ($jd_data['credentials'] as $key => $jd_cred) {
            if ($jd_cred['is_required'] && !isset($cv_data['credentials'][$key])) {
                $gaps['credential_' . $key] = [
                    'type' => 'missing_credential',
                    'credential' => $jd_cred['credential'],
                    'is_required' => true,
                    'severity' => 'dealbreaker',
                ];
            } elseif (!$jd_cred['is_required'] && !isset($cv_data['credentials'][$key])) {
                $gaps['credential_' . $key] = [
                    'type' => 'missing_credential',
                    'credential' => $jd_cred['credential'],
                    'is_required' => false,
                    'severity' => 'improvement',
                ];
            }
        }

        // 3. Location mismatch
        if (!empty($jd_data['location']['city']) && !empty($cv_data['contact']['location'])) {
            if (strtolower($jd_data['location']['city']) !== strtolower($cv_data['contact']['location'])) {
                $gaps['location'] = [
                    'type' => 'location_mismatch',
                    'required' => $jd_data['location']['city'],
                    'candidate_has' => $cv_data['contact']['location'],
                    'severity' => $jd_data['location']['is_required'] ? 'dealbreaker' : 'critical',
                ];
            }
        }

        // 4. Sector mismatch
        $jd_sectors = array_keys($jd_data['sectors']);
        $cv_sectors = array_keys($cv_data['sectors']);
        $sector_overlap = array_intersect($jd_sectors, $cv_sectors);

        if (!empty($jd_sectors) && empty($sector_overlap)) {
            // Check for transferable experience
            $transferable = false;
            foreach ($cv_sectors as $cv_sector) {
                foreach ($jd_sectors as $jd_sector) {
                    $transfer = $this->knowledge_base->check_sector_transfer($cv_sector, $jd_sector);
                    if ($transfer) {
                        $transferable = true;
                        break 2;
                    }
                }
            }

            $gaps['sector'] = [
                'type' => 'sector_mismatch',
                'required' => $jd_sectors,
                'candidate_has' => $cv_sectors,
                'transferable' => $transferable,
                'severity' => $transferable ? 'improvement' : 'critical',
            ];
        }

        // 5. Seniority mismatch
        if ($jd_data['seniority']['level'] !== 'unknown') {
            $jd_years = $jd_data['seniority']['years_range'];
            $cv_years = $cv_data['total_years'];

            if ($cv_years < $jd_years[0]) {
                $gaps['seniority'] = [
                    'type' => 'seniority_mismatch',
                    'required_level' => $jd_data['seniority']['level'],
                    'expected_years' => $jd_years,
                    'candidate_years' => $cv_years,
                    'severity' => ($jd_years[0] - $cv_years) > 3 ? 'dealbreaker' : 'critical',
                ];
            }
        }

        // 6. Nationality requirements
        // Note: Flagged as dealbreaker for visibility but does NOT reduce match score
        if (!empty($jd_data['nationality_requirements'])) {
            foreach ($jd_data['nationality_requirements'] as $idx => $nat_req) {
                $gaps['nationality_' . $idx] = [
                    'type' => 'nationality_requirement',
                    'required_nationality' => $nat_req['display_name'] ?? ucwords(str_replace('_', ' ', $nat_req['nationality'])),
                    'nationality_code' => $nat_req['nationality'],
                    'candidate_nationality' => $cv_data['nationality'] ?? 'unknown',
                    'severity' => 'dealbreaker',
                    'score_impact' => 0, // Critical flag but no score penalty
                    'message' => 'This position requires ' . ($nat_req['display_name'] ?? $nat_req['nationality']) . ' status. Please verify your eligibility.',
                ];
            }
        }

        return $gaps;
    }

    /**
     * Analyze keyword matching
     */
    private function analyze_keywords($jd_data, $cv_data) {
        $jd_keywords = $jd_data['keywords'];
        $cv_keywords = $cv_data['keywords'];

        $result = [
            'total_jd_keywords' => count($jd_keywords),
            'matched_keywords' => 0,
            'missing_keywords' => [],
            'match_rate' => 0,
            'details' => [],
        ];

        foreach ($jd_keywords as $key => $jd_kw) {
            $cv_count = isset($cv_keywords[$key]) ? $cv_keywords[$key]['count'] : 0;

            $detail = [
                'keyword' => $jd_kw['canonical'],
                'jd_count' => $jd_kw['count'],
                'cv_count' => $cv_count,
                'match' => $cv_count > 0,
                'importance' => $jd_kw['is_important'] ? 'high' : 'medium',
            ];

            if ($cv_count > 0) {
                $result['matched_keywords']++;
            } else {
                $result['missing_keywords'][] = $jd_kw['canonical'];
            }

            $result['details'][$key] = $detail;
        }

        $result['match_rate'] = $result['total_jd_keywords'] > 0
            ? round(($result['matched_keywords'] / $result['total_jd_keywords']) * 100, 1)
            : 0;

        return $result;
    }

    /**
     * Build a rich deterministic payload that mirrors the Claude report schema.
     */
    private function build_terminal_fallback_result($jd_data, $cv_data, $preliminary, $ats_result, $fallback_result) {
        $keyword_analysis = $this->analyze_keywords($jd_data, $cv_data);
        $capability_alignment = $this->build_terminal_capability_alignment($jd_data, $cv_data);
        $missing_keywords = array_values(array_slice((array) ($keyword_analysis['missing_keywords'] ?? []), 0, 8));
        $matched_keywords = [];

        foreach ((array) ($keyword_analysis['details'] ?? []) as $detail) {
            if (!empty($detail['match']) && !empty($detail['keyword'])) {
                $matched_keywords[] = (string) $detail['keyword'];
            }
        }

        $matched_keywords = array_values(array_unique(array_slice($matched_keywords, 0, 12)));

        $strength_signals = $this->build_terminal_strength_signals($jd_data, $cv_data, $fallback_result, $matched_keywords, $capability_alignment);
        $missing_signals = $this->build_terminal_missing_signals($jd_data, $cv_data, $preliminary, $fallback_result, $missing_keywords, $capability_alignment);
        $requirements = $this->build_terminal_requirements_analysis($jd_data, $cv_data, $preliminary, $matched_keywords, $missing_signals, $capability_alignment);
        $scores = $this->build_terminal_scores($jd_data, $cv_data, $preliminary, $ats_result, $keyword_analysis, $strength_signals, $missing_signals, $capability_alignment);
        $relevant_roles = $this->build_terminal_relevant_roles($jd_data, $cv_data, $matched_keywords, $missing_keywords);
        $experience_improvements = $this->build_terminal_experience_improvements($jd_data, $cv_data, $fallback_result, $missing_keywords, $matched_keywords);
        $cv_improvements = $this->build_terminal_cv_improvements($jd_data, $cv_data, $fallback_result, $missing_keywords, $matched_keywords);
        $cover_points = $this->build_terminal_cover_points($jd_data, $cv_data, $strength_signals, $missing_signals);
        $interview_prep = $this->build_terminal_interview_prep($jd_data, $strength_signals, $missing_signals);
        $red_flags = $this->build_terminal_red_flags($fallback_result, $missing_signals, $preliminary);
        $overall = $this->build_terminal_overall_assessment($jd_data, $cv_data, $scores, $strength_signals, $missing_signals, $fallback_result);
        $executive_summary = $this->build_terminal_executive_summary($jd_data, $cv_data, $scores, $strength_signals, $missing_signals, $fallback_result);

        return [
            'source' => 'keyword_fallback',
            'analysis_source' => 'keyword_fallback',
            'meta' => [
                'role_title' => (string) ($jd_data['role_title'] ?? ''),
                'company' => (string) ($jd_data['company'] ?? ''),
                'analysis_source' => 'keyword_fallback',
                'analyzed_at' => current_time('mysql'),
            ],
            'executive_summary' => $executive_summary,
            'scores' => $scores,
            'skills_breakdown' => [
                'matched_skills' => array_slice($this->build_terminal_matched_skills($jd_data, $cv_data, $matched_keywords, $strength_signals), 0, 8),
                'missing_skills' => array_slice($this->build_terminal_missing_skills($jd_data, $missing_signals), 0, 8),
                'transferable_skills' => array_slice($this->build_terminal_transferable_skills($jd_data, $cv_data, $missing_signals), 0, 5),
            ],
            'requirements_analysis' => $requirements,
            'experience_analysis' => [
                'role_family' => [
                    'target' => (string) ($jd_data['role_family']['label'] ?? ''),
                    'candidate' => (string) ($cv_data['role_family_guess']['label'] ?? ''),
                ],
                'years_required' => $this->format_terminal_years_required($jd_data),
                'years_candidate_has' => $this->format_terminal_years_candidate_has($cv_data),
                'experience_gap' => $this->format_terminal_experience_gap($preliminary, $scores['experience_match']),
                'relevant_roles' => $relevant_roles,
                'industry_fit' => [
                    'required_industries' => $this->get_terminal_sector_names($jd_data),
                    'candidate_industries' => $this->get_terminal_sector_names($cv_data),
                    'assessment' => $this->build_terminal_industry_fit_assessment($jd_data, $cv_data, $preliminary),
                ],
            ],
            'experience_improvements' => $experience_improvements,
            'keyword_analysis' => [
                'total_jd_keywords' => (int) ($keyword_analysis['total_jd_keywords'] ?? 0),
                'matched_keywords' => (int) ($keyword_analysis['matched_keywords'] ?? 0),
                'match_percentage' => (int) round($scores['keywords_match']),
                'critical_missing' => $missing_keywords,
                'well_represented' => $matched_keywords,
                'suggested_additions' => array_slice($missing_keywords, 0, 6),
                'capability_alignment' => array_slice($capability_alignment, 0, 6),
            ],
            'red_flags' => $red_flags,
            'strengths_to_highlight' => $strength_signals,
            'cv_improvements' => $cv_improvements,
            'cover_letter_points' => $cover_points,
            'interview_prep' => $interview_prep,
            'overall_assessment' => $overall,
            'ats_analysis' => $ats_result,
        ];
    }

    private function build_terminal_scores($jd_data, $cv_data, $preliminary, $ats_result, $keyword_analysis, $strength_signals, $missing_signals, $capability_alignment) {
        $ats_score = (int) round($ats_result['percentage'] ?? 0);
        $keywords_score = (int) round($keyword_analysis['match_rate'] ?? 0);
        $experience_score = $this->calculate_terminal_experience_score($jd_data, $cv_data, $preliminary);
        $education_score = $this->calculate_terminal_education_score($jd_data, $cv_data, $preliminary);
        $skills_score = $this->calculate_terminal_skills_score($jd_data, $cv_data, $keyword_analysis, $preliminary, count($strength_signals), count($missing_signals), $capability_alignment);

        $overall = (int) round(
            ($skills_score * 0.40) +
            ($experience_score * 0.28) +
            ($keywords_score * 0.14) +
            ($education_score * 0.08) +
            ($ats_score * 0.10)
        );

        $dealbreaker_count = count((array) ($preliminary ?? []));
        if (!empty($preliminary['nationality_0'])) {
            $dealbreaker_count -= 1;
        }

        if ($dealbreaker_count > 0) {
            $overall -= min(18, $dealbreaker_count * 6);
        }

        return [
            'overall' => max(0, min(100, $overall)),
            'skills_match' => max(0, min(100, $skills_score)),
            'experience_match' => max(0, min(100, $experience_score)),
            'education_match' => max(0, min(100, $education_score)),
            'keywords_match' => max(0, min(100, $keywords_score)),
        ];
    }

    private function build_terminal_capability_alignment($jd_data, $cv_data) {
        $alignment = [];
        $jd_capabilities = (array) ($jd_data['capabilities'] ?? []);
        $cv_capabilities = (array) ($cv_data['capabilities'] ?? []);

        foreach ($jd_capabilities as $key => $jd_capability) {
            $cv_capability = (array) ($cv_capabilities[$key] ?? []);
            $required_score = (int) ($jd_capability['priority_score'] ?? 0);
            $candidate_score = (int) ($cv_capability['score'] ?? 0);
            $gap = max(0, $required_score - $candidate_score);

            $alignment[$key] = [
                'key' => (string) $key,
                'label' => (string) ($jd_capability['label'] ?? $key),
                'required_score' => $required_score,
                'candidate_score' => $candidate_score,
                'gap' => $gap,
                'is_required' => !empty($jd_capability['is_required']),
                'jd_terms' => array_values((array) ($jd_capability['matched_terms'] ?? [])),
                'cv_terms' => array_values(array_unique(array_merge(
                    (array) ($cv_capability['direct_terms'] ?? []),
                    (array) ($cv_capability['adjacent_terms'] ?? [])
                ))),
                'status' => $candidate_score >= max(35, (int) round($required_score * 0.7))
                    ? 'strong'
                    : ($candidate_score >= max(18, (int) round($required_score * 0.35)) ? 'partial' : 'missing'),
                'evidence_level' => (string) ($cv_capability['evidence_level'] ?? 'missing'),
            ];
        }

        uasort($alignment, function ($a, $b) {
            return ($b['required_score'] ?? 0) <=> ($a['required_score'] ?? 0);
        });

        return $alignment;
    }

    private function calculate_terminal_skills_score($jd_data, $cv_data, $keyword_analysis, $preliminary, $strength_count, $missing_count, $capability_alignment) {
        $score = (int) round($keyword_analysis['match_rate'] ?? 0);
        $capability_scores = [];

        foreach ((array) $capability_alignment as $item) {
            $required = max(1, (int) ($item['required_score'] ?? 0));
            $candidate = max(0, (int) ($item['candidate_score'] ?? 0));
            $capability_scores[] = min(100, (int) round(($candidate / $required) * 100));
        }

        if (!empty($capability_scores)) {
            $score = (int) round(($score * 0.25) + ((array_sum($capability_scores) / count($capability_scores)) * 0.75));
        }

        $required_credentials = 0;
        $matched_credentials = 0;
        foreach ((array) ($jd_data['credentials'] ?? []) as $key => $credential) {
            $required_credentials++;
            if (isset($cv_data['credentials'][$key])) {
                $matched_credentials++;
            }
        }
        if ($required_credentials > 0) {
            $score = (int) round(($score * 0.82) + ((($matched_credentials / max(1, $required_credentials)) * 100) * 0.18));
        }

        $score += min(8, $strength_count * 2);
        $score -= min(18, $missing_count * 3);

        if (!empty($preliminary['credential_cfa']) && ($preliminary['credential_cfa']['severity'] ?? '') === 'dealbreaker') {
            $score -= 8;
        }

        return max(0, min(100, $score));
    }

    private function calculate_terminal_experience_score($jd_data, $cv_data, $preliminary) {
        $years = (float) ($cv_data['total_years'] ?? 0);
        $required_min = (float) ($jd_data['experience']['min_years'] ?? 0);
        $score = 68;

        if ($required_min > 0) {
            if ($years >= $required_min) {
                $score = 84 + min(12, (int) round(($years - $required_min) * 3));
            } elseif (($required_min - $years) <= 1) {
                $score = 68;
            } elseif (($required_min - $years) <= 2) {
                $score = 54;
            } else {
                $score = 36;
            }
        } elseif ($years > 0) {
            $score = min(92, 58 + ((int) round($years * 4)));
        }

        $jd_sectors = array_keys((array) ($jd_data['sectors'] ?? []));
        $cv_sectors = array_keys((array) ($cv_data['sectors'] ?? []));
        $sector_overlap = array_intersect($jd_sectors, $cv_sectors);
        if (!empty($sector_overlap)) {
            $score += 8;
        } elseif (!empty($jd_sectors) && !empty($cv_sectors)) {
            $score -= 8;
        }

        if (!empty($preliminary['seniority'])) {
            $severity = (string) ($preliminary['seniority']['severity'] ?? '');
            if ($severity === 'dealbreaker') {
                $score -= 25;
            } elseif ($severity === 'critical') {
                $score -= 12;
            }
        }

        $jd_family = (string) ($jd_data['role_family']['key'] ?? '');
        $cv_family = (string) ($cv_data['role_family_guess']['key'] ?? '');
        if ($jd_family !== '' && $cv_family !== '') {
            if ($jd_family === $cv_family) {
                $score += 8;
            } else {
                $score -= 6;
            }
        }

        return max(0, min(100, $score));
    }

    private function calculate_terminal_education_score($jd_data, $cv_data, $preliminary) {
        $score = 58;

        if (!empty($cv_data['education'])) {
            $score += 14;
            foreach ((array) $cv_data['education'] as $education) {
                if (!empty($education['is_target_school'])) {
                    $score += 12;
                    break;
                }
            }
        }

        if (!empty($cv_data['credentials'])) {
            $score += 10;
        }

        if (!empty($preliminary['credential_cfa']) && ($preliminary['credential_cfa']['severity'] ?? '') === 'dealbreaker') {
            $score -= 18;
        }

        return max(0, min(100, $score));
    }

    private function build_terminal_executive_summary($jd_data, $cv_data, $scores, $strength_signals, $missing_signals, $fallback_result) {
        $overall = (int) ($scores['overall'] ?? 0);
        $role_title = (string) ($jd_data['role_title'] ?? 'Role');
        $company = (string) ($jd_data['company'] ?? '');
        $location = $this->format_terminal_location_label($jd_data);
        $top_strength = !empty($strength_signals[0]['strength']) ? (string) $strength_signals[0]['strength'] : 'relevant role-fit signals';
        $top_gap = !empty($missing_signals[0]) ? (string) $missing_signals[0] : 'clearer proof against the brief';
        $outcome_summary = $this->build_terminal_outcome_signal_summary($cv_data);

        if ($overall >= 80) {
            $risk = 'STRONG_FIT';
            $recommendation = 'APPLY';
            $verdict = sprintf('Your background already reads credibly against %s, with %s surfacing strongly. The main task now is to sharpen the wording so recruiters see the fit immediately rather than making them infer it.', $role_title, $top_strength);
        } elseif ($overall >= 65) {
            $risk = 'COMPETITIVE';
            $recommendation = 'APPLY';
            $verdict = sprintf('You look directionally competitive for %s, but the CV still needs stronger evidence around %s. The fit is there, yet the application will perform better if you make those signals explicit before applying.', $role_title, $top_gap);
        } elseif ($overall >= 50) {
            $risk = 'MODERATE';
            $recommendation = 'APPLY_WITH_CAUTION';
            $verdict = sprintf('There is a plausible route into %s, but recruiters are likely to hesitate because %s is not visible enough. This is closer to a repositioning exercise than a ready-to-send application.', $role_title, $top_gap);
        } else {
            $risk = 'HIGH_RISK';
            $recommendation = 'CONSIDER_ALTERNATIVES';
            $verdict = sprintf('This brief is likely to screen hard for %s, and your current CV does not evidence that clearly enough. You would need significant repositioning or a more adjacent role target before this becomes a strong application.', $top_gap);
        }

        if ($outcome_summary !== '') {
            $verdict .= ' ' . $outcome_summary;
        }

        return [
            'role_title' => $role_title,
            'company' => $company,
            'location' => $location,
            'match_score' => $overall,
            'risk_level' => $risk,
            'verdict' => $verdict,
            'recommendation' => $recommendation,
            'key_insight' => sprintf('The report is rewarding visible proof of %s while penalising vague or missing evidence around %s.', $top_strength, $top_gap),
        ];
    }

    private function format_terminal_location_label($jd_data) {
        $city = trim((string) ($jd_data['location']['city'] ?? ''));
        $country = trim((string) ($jd_data['location']['country'] ?? ''));

        if ($city !== '' && $country !== '') {
            return $city . ', ' . $country;
        }

        if ($city !== '') {
            return $city;
        }

        return '';
    }

    private function build_terminal_strength_signals($jd_data, $cv_data, $fallback_result, $matched_keywords, $capability_alignment) {
        $signals = [];

        foreach ((array) $capability_alignment as $item) {
            if (($item['status'] ?? '') !== 'strong') {
                continue;
            }

            $signals[] = [
                'strength' => (string) ($item['label'] ?? 'Relevant capability'),
                'relevance' => sprintf(
                    'The brief is screening for %s and your CV already evidences it through %s.',
                    (string) ($item['label'] ?? 'this capability'),
                    !empty($item['cv_terms']) ? implode(', ', array_slice((array) $item['cv_terms'], 0, 2)) : 'direct experience'
                ),
                'how_to_leverage' => sprintf(
                    'Keep %s visible in the summary and first recruiter-facing bullet.',
                    (string) ($item['label'] ?? 'this strength')
                ),
            ];
        }

        foreach ((array) ($fallback_result['strengths'] ?? []) as $strength) {
            $signals[] = [
                'strength' => (string) ($strength['title'] ?? 'Relevant experience'),
                'relevance' => (string) ($strength['description'] ?? ''),
                'how_to_leverage' => (string) ($strength['leverage'] ?? 'Bring this forward earlier in your CV and opening pitch.'),
            ];
        }

        foreach (array_slice($matched_keywords, 0, 5) as $keyword) {
            $signals[] = [
                'strength' => (string) $keyword,
                'relevance' => sprintf('This term already appears in your CV and aligns with the live brief for %s.', (string) ($jd_data['role_title'] ?? 'the role')),
                'how_to_leverage' => sprintf('Lead with %s in your summary, skills section, or first recruiter-facing bullet.', (string) $keyword),
            ];
        }

        return $this->unique_terminal_items($signals, 'strength');
    }

    private function build_terminal_missing_signals($jd_data, $cv_data, $preliminary, $fallback_result, $missing_keywords, $capability_alignment) {
        $signals = [];

        foreach ((array) $capability_alignment as $item) {
            if (($item['status'] ?? '') === 'strong') {
                continue;
            }

            $signals[] = (string) ($item['label'] ?? '');
        }

        $signals = array_merge($signals, array_values($missing_keywords));

        foreach ((array) ($fallback_result['critical_gaps'] ?? []) as $gap) {
            if (!empty($gap['title'])) {
                $signals[] = (string) $gap['title'];
            }
        }

        foreach ((array) ($preliminary ?? []) as $gap) {
            if (!empty($gap['credential'])) {
                $signals[] = (string) $gap['credential'];
            } elseif (!empty($gap['type']) && $gap['type'] === 'location_mismatch') {
                $signals[] = 'location fit';
            } elseif (!empty($gap['type']) && $gap['type'] === 'sector_mismatch') {
                $signals[] = 'sector relevance';
            }
        }

        foreach ((array) ($jd_data['languages'] ?? []) as $key => $language) {
            if (empty($cv_data['languages'][$key])) {
                $signals[] = (string) ($language['language'] ?? ucfirst((string) $key));
            }
        }

        $signals = array_values(array_unique(array_filter(array_map('trim', $signals))));
        return array_slice($signals, 0, 8);
    }

    private function build_terminal_matched_skills($jd_data, $cv_data, $matched_keywords, $strength_signals) {
        $items = [];

        foreach (array_slice((array) $strength_signals, 0, 5) as $strength) {
            $label = trim((string) ($strength['strength'] ?? ''));
            if ($label === '') {
                continue;
            }

            $items[] = [
                'skill' => $label,
                'jd_requirement' => sprintf('The brief is materially rewarding %s.', $label),
                'cv_evidence' => (string) ($strength['relevance'] ?? 'Your CV already contains relevant evidence here.'),
                'strength_level' => 'proficient',
            ];
        }

        foreach (array_slice($matched_keywords, 0, 8) as $keyword) {
            $items[] = [
                'skill' => (string) $keyword,
                'jd_requirement' => sprintf('The brief explicitly or repeatedly screens for %s.', (string) $keyword),
                'cv_evidence' => sprintf('Your CV already signals %s, which gives recruiters an easy screening hook.', (string) $keyword),
                'strength_level' => 'proficient',
            ];
        }

        foreach ((array) ($cv_data['credentials'] ?? []) as $credential) {
            if (empty($credential['credential'])) {
                continue;
            }
            $items[] = [
                'skill' => (string) $credential['credential'],
                'jd_requirement' => 'Credential or qualification signal',
                'cv_evidence' => !empty($credential['in_progress'])
                    ? sprintf('%s is already in progress and should be surfaced clearly.', (string) $credential['credential'])
                    : sprintf('%s is visible and adds credibility to the application.', (string) $credential['credential']),
                'strength_level' => !empty($credential['in_progress']) ? 'basic' : 'expert',
            ];
        }

        return $this->unique_terminal_items($items, 'skill');
    }

    private function build_terminal_missing_skills($jd_data, $missing_signals) {
        $items = [];
        $required_capabilities = [];

        foreach ((array) ($jd_data['capabilities'] ?? []) as $capability) {
            $required_capabilities[(string) ($capability['label'] ?? '')] = !empty($capability['is_required']);
        }

        foreach ($missing_signals as $signal) {
            $importance = 'important';
            foreach ((array) ($jd_data['keywords'] ?? []) as $keyword) {
                if ((string) ($keyword['canonical'] ?? '') === (string) $signal) {
                    $importance = !empty($keyword['is_important']) ? 'critical' : 'important';
                    break;
                }
            }

            if (!empty($required_capabilities[(string) $signal])) {
                $importance = 'critical';
            }

            $items[] = [
                'skill' => (string) $signal,
                'importance' => $importance,
                'suggestion' => sprintf('Add one bullet or summary line that makes %s explicit instead of leaving recruiters to infer it.', (string) $signal),
            ];
        }

        return $this->unique_terminal_items($items, 'skill');
    }

    private function build_terminal_transferable_skills($jd_data, $cv_data, $missing_signals) {
        $transferable = [];
        $current_role = (string) (($cv_data['current_role']['role'] ?? '') ?: '');

        if ($current_role !== '') {
            foreach (array_slice($missing_signals, 0, 3) as $signal) {
                $transferable[] = [
                    'skill' => (string) $signal,
                    'relevance' => sprintf('Your current background can still be framed as adjacent to %s if the evidence is tied to commercial outcomes, analysis, or stakeholder impact.', (string) $signal),
                    'positioning' => sprintf('Frame %s experience in %s language, then connect it directly to the role requirements.', $current_role, (string) $signal),
                ];
            }
        }

        return $this->unique_terminal_items($transferable, 'skill');
    }

    private function build_terminal_requirements_analysis($jd_data, $cv_data, $preliminary, $matched_keywords, $missing_signals, $capability_alignment) {
        $requirements = [];

        foreach ((array) $capability_alignment as $item) {
            $term = (string) ($item['label'] ?? '');
            if ($term === '') {
                continue;
            }

            $requirements[] = [
                'requirement' => $term,
                'match_status' => ($item['status'] ?? '') === 'strong'
                    ? 'STRONG_MATCH'
                    : (($item['status'] ?? '') === 'partial' ? 'PARTIAL_MATCH' : 'NOT_FOUND'),
                'gap_severity' => ($item['status'] ?? '') === 'strong'
                    ? 'low'
                    : (!empty($item['is_required']) ? 'critical' : 'significant'),
                'cv_evidence' => !empty($item['cv_terms'])
                    ? sprintf('Current CV signals %s via %s.', $term, implode(', ', array_slice((array) $item['cv_terms'], 0, 2)))
                    : sprintf('%s is not yet clearly surfaced in the current CV wording.', $term),
                'action_needed' => ($item['status'] ?? '') === 'strong'
                    ? sprintf('Keep %s visible near the top of the CV.', $term)
                    : sprintf('Add one role-specific bullet proving %s with a quantified or concrete example.', $term),
            ];
        }

        foreach ((array) ($jd_data['credentials'] ?? []) as $key => $credential) {
            $term = (string) ($credential['credential'] ?? '');
            if ($term === '') {
                continue;
            }
            $matched = isset($cv_data['credentials'][$key]);
            $requirements[] = [
                'requirement' => $term,
                'match_status' => $matched ? 'STRONG_MATCH' : 'NOT_FOUND',
                'gap_severity' => $matched ? 'low' : (!empty($credential['is_required']) ? 'critical' : 'significant'),
                'cv_evidence' => $matched
                    ? sprintf('%s is already visible in your profile.', $term)
                    : sprintf('%s is not visible in your current CV.', $term),
                'action_needed' => $matched
                    ? sprintf('Surface %s early in the document.', $term)
                    : sprintf('If relevant, add %s or an equivalent qualification signal.', $term),
            ];
        }

        foreach ((array) ($jd_data['tools'] ?? []) as $tool) {
            $term = (string) ($tool['name'] ?? '');
            if ($term === '') {
                continue;
            }

            $matched = false;
            foreach ((array) ($cv_data['tools'] ?? []) as $cv_tool) {
                if (strcasecmp((string) ($cv_tool['name'] ?? ''), $term) === 0) {
                    $matched = true;
                    break;
                }
            }

            $requirements[] = [
                'requirement' => $term,
                'match_status' => $matched ? 'STRONG_MATCH' : 'NOT_FOUND',
                'gap_severity' => $matched ? 'low' : 'significant',
                'cv_evidence' => $matched
                    ? sprintf('%s is already visible in the CV.', $term)
                    : sprintf('%s is not currently visible in the CV.', $term),
                'action_needed' => $matched
                    ? sprintf('Keep %s in the skills or experience section.', $term)
                    : sprintf('Add %s only if you have genuinely used it in role-relevant work.', $term),
            ];
        }

        foreach ((array) ($jd_data['languages'] ?? []) as $key => $language) {
            $term = (string) ($language['language'] ?? ucfirst((string) $key));
            $matched = !empty($cv_data['languages'][$key]);
            $requirements[] = [
                'requirement' => $term,
                'match_status' => $matched ? 'STRONG_MATCH' : 'NOT_FOUND',
                'gap_severity' => $matched ? 'low' : (!empty($language['is_required']) ? 'critical' : 'significant'),
                'cv_evidence' => $matched
                    ? sprintf('%s is already visible in the profile.', $term)
                    : sprintf('%s is not clearly visible in the CV.', $term),
                'action_needed' => $matched
                    ? sprintf('Keep %s easy to scan in the header, summary, or skills section.', $term)
                    : sprintf('Only add %s if it is real and recruiter-relevant.', $term),
            ];
        }

        if (!empty($preliminary['experience'])) {
            $requirements[] = [
                'requirement' => sprintf('%s+ years of experience', (string) ($preliminary['experience']['required'] ?? 'Required')),
                'match_status' => (($preliminary['experience']['severity'] ?? '') === 'improvement') ? 'PARTIAL_MATCH' : 'NOT_FOUND',
                'gap_severity' => (string) ($preliminary['experience']['severity'] ?? 'significant'),
                'cv_evidence' => sprintf('Current CV suggests %s years of experience.', (string) ($preliminary['experience']['candidate_has'] ?? 'limited')),
                'action_needed' => 'Emphasize scope, pace of progression, and equivalent responsibility if experience is lighter than requested.',
            ];
        }

        return array_slice($requirements, 0, 16);
    }

    private function build_terminal_relevant_roles($jd_data, $cv_data, $matched_keywords, $missing_keywords) {
        $roles = [];
        $target_terms = array_slice(array_values(array_unique(array_merge($matched_keywords, $missing_keywords))), 0, 6);

        foreach (array_slice((array) ($cv_data['experience'] ?? []), 0, 3) as $entry) {
            $role = (string) ($entry['role'] ?? '');
            $company = (string) ($entry['company'] ?? '');
            $years = (float) ($entry['years'] ?? 0);
            if ($role === '' && $company === '') {
                continue;
            }

            $context = strtolower(trim($role . ' ' . $company));
            $role_matches = [];
            $role_gaps = [];

            foreach ($target_terms as $term) {
                if ($term !== '' && strpos($context, strtolower($term)) !== false) {
                    $role_matches[] = (string) $term;
                } elseif (count($role_gaps) < 2) {
                    $role_gaps[] = (string) $term;
                }
            }

            $relevance_score = 58 + (count($role_matches) * 12) + min(12, (int) round($years * 4));
            $relevance_score = max(35, min(92, $relevance_score));

            $roles[] = [
                'role' => $role !== '' ? $role : 'Relevant role',
                'company' => $company,
                'duration' => $years > 0 ? sprintf('%.1f years', $years) : '',
                'relevance_score' => $relevance_score,
                'key_matches' => !empty($role_matches) ? $role_matches : array_slice($matched_keywords, 0, 2),
                'gaps' => !empty($role_gaps) ? $role_gaps : array_slice($missing_keywords, 0, 2),
                'bullet_improvements' => [
                    [
                        'original' => sprintf('%s at %s', $role !== '' ? $role : 'Role', $company !== '' ? $company : 'current employer'),
                        'improved' => sprintf('Reframe this role around %s, with one quantified bullet showing commercial or analytical impact.', !empty($role_matches[0]) ? $role_matches[0] : 'the strongest role-fit signal'),
                        'reason' => 'Recruiters need immediate proof that the experience translates into the live brief.',
                        'keywords_added' => array_slice(!empty($role_gaps) ? $role_gaps : $missing_keywords, 0, 2),
                    ],
                ],
                'missing_achievements' => array_slice($role_gaps, 0, 2),
            ];
        }

        if (empty($roles) && !empty($cv_data['current_role']['role'])) {
            $roles[] = [
                'role' => (string) $cv_data['current_role']['role'],
                'company' => (string) ($cv_data['current_role']['company'] ?? ''),
                'duration' => !empty($cv_data['current_role']['years_in_role']) ? sprintf('%.1f years', (float) $cv_data['current_role']['years_in_role']) : '',
                'relevance_score' => 60,
                'key_matches' => array_slice($matched_keywords, 0, 2),
                'gaps' => array_slice($missing_keywords, 0, 2),
                'bullet_improvements' => [],
                'missing_achievements' => array_slice($missing_keywords, 0, 2),
            ];
        }

        return $roles;
    }

    private function build_terminal_experience_improvements($jd_data, $cv_data, $fallback_result, $missing_keywords, $matched_keywords) {
        $priority_fixes = [];
        $verb_upgrades = [];
        $quant_fixes = [];
        $keyword_integration = [];
        $achievement_reframes = [];
        $additional_experience = [];

        foreach (array_slice((array) ($fallback_result['improvements'] ?? []), 0, 4) as $index => $improvement) {
            $priority_fixes[] = [
                'priority' => $index + 1,
                'issue' => (string) ($improvement['title'] ?? 'Sharpen this evidence'),
                'current_text' => (string) ($improvement['current'] ?? 'The signal is weak or missing.'),
                'improved_text' => (string) ($improvement['suggested'] ?? 'Rewrite this bullet using stronger role-specific language and quantification.'),
                'impact' => 'high',
                'jd_alignment' => (string) ($improvement['impact'] ?? 'Improves recruiter recognition and ATS alignment.'),
            ];
        }

        foreach ((array) ($cv_data['bullet_analysis']['weak_examples'] ?? []) as $weak_example) {
            $bullet = (string) ($weak_example['bullet'] ?? '');
            if ($bullet === '') {
                continue;
            }

            $weak_verb = '';
            if (preg_match('/\b(helped|assisted|supported|participated|contributed|handled)\b/i', $bullet, $match)) {
                $weak_verb = strtolower((string) $match[1]);
            }

            $strong_verb = $this->suggest_terminal_action_verb($bullet);
            $verb_upgrades[] = [
                'weak_verb' => $weak_verb !== '' ? $weak_verb : 'weak phrasing',
                'strong_verb' => $strong_verb,
                'example_rewrite' => sprintf('%s — now framed around %s and measurable impact.', $bullet, $strong_verb),
            ];
        }

        if (empty($verb_upgrades)) {
            $verb_upgrades[] = [
                'weak_verb' => 'supported',
                'strong_verb' => 'executed',
                'example_rewrite' => 'Replace generic support language with clear ownership, outcome, and quantified impact.',
            ];
        }

        $bullet_analysis = (array) ($cv_data['bullet_analysis'] ?? []);
        $total_bullets = (int) ($bullet_analysis['total_bullets'] ?? 0);
        $quantified_bullets = (int) ($bullet_analysis['quantified_bullets'] ?? 0);
        $quantified_outcome_bullets = (int) ($bullet_analysis['quantified_outcome_bullets'] ?? 0);
        $scope_only_examples = array_slice((array) ($bullet_analysis['scope_only_examples'] ?? []), 0, 2);

        if ($quantified_bullets < $total_bullets) {
            $quant_fixes[] = [
                'original' => 'Several bullets describe work without numbers.',
                'improved' => 'Add percentages, deal counts, model scope, portfolio size, or reporting impact wherever the work supported a measurable result.',
            ];
        }

        if ($total_bullets > 0 && $quantified_outcome_bullets < max(1, (int) ceil($total_bullets * 0.35))) {
            $quant_fixes[] = [
                'original' => sprintf(
                    'Only %d of %d bullets currently show a measurable outcome.',
                    $quantified_outcome_bullets,
                    $total_bullets
                ),
                'improved' => 'Turn scope-only bullets into result bullets by pairing the work with what changed: improved efficiency, reduced risk, faster turnaround, stronger returns, or a completed transaction outcome.',
            ];
        }

        foreach ($scope_only_examples as $example) {
            $quant_fixes[] = [
                'original' => (string) $example,
                'improved' => 'Keep the number, but add the result it drove so the bullet reads as measurable impact rather than scope alone.',
            ];
        }

        foreach (array_slice($missing_keywords, 0, 4) as $keyword) {
            $keyword_integration[] = [
                'missing_keyword' => (string) $keyword,
                'suggested_placement' => 'Work experience and summary',
                'integration_example' => sprintf('Use %s in a real achievement bullet rather than stuffing it into a skills list.', (string) $keyword),
            ];
            $additional_experience[] = sprintf('One bullet showing %s in practice with a clear commercial, analytical, or deal-related outcome.', (string) $keyword);
        }

        foreach (array_slice($matched_keywords, 0, 3) as $keyword) {
            $achievement_reframes[] = [
                'current_duty' => sprintf('%s is probably present but under-positioned.', (string) $keyword),
                'achievement_version' => sprintf('Move %s earlier and tie it to a result so recruiters can spot the fit within seconds.', (string) $keyword),
            ];
        }

        return [
            'summary' => $this->build_terminal_experience_summary($jd_data, $cv_data, $missing_keywords, $matched_keywords),
            'priority_fixes' => $priority_fixes,
            'action_verb_upgrades' => array_slice($verb_upgrades, 0, 4),
            'quantification_fixes' => array_slice($quant_fixes, 0, 3),
            'keyword_integration' => array_slice($keyword_integration, 0, 4),
            'achievement_reframes' => array_slice($achievement_reframes, 0, 4),
            'additional_experience_to_add' => array_slice($additional_experience, 0, 4),
        ];
    }

    private function build_terminal_cv_improvements($jd_data, $cv_data, $fallback_result, $missing_keywords, $matched_keywords) {
        $improvements = [];
        $priority_sections = [
            'Professional Summary',
            'Work Experience',
            'Skills',
            'Education',
        ];

        foreach (array_slice((array) ($fallback_result['improvements'] ?? []), 0, 4) as $index => $improvement) {
            $section = $priority_sections[$index] ?? 'Application Positioning';
            $improvements[] = [
                'section' => $section,
                'current' => (string) ($improvement['current'] ?? 'The current CV phrasing is too generic.'),
                'suggested' => (string) ($improvement['suggested'] ?? 'Use the JD language more directly and add a quantified proof point.'),
                'impact' => (string) ($improvement['impact'] ?? 'Makes the application easier for recruiters to screen quickly.'),
            ];
        }

        foreach (array_slice($missing_keywords, 0, 3) as $keyword) {
            $improvements[] = [
                'section' => 'ATS Keywords',
                'current' => sprintf('%s is not clearly visible in the current CV.', (string) $keyword),
                'suggested' => sprintf('Add %s naturally into a recent bullet, your summary, or your skills section where it is genuinely evidenced.', (string) $keyword),
                'impact' => 'Improves ATS alignment and recruiter confidence.',
            ];
        }

        return array_slice($improvements, 0, 8);
    }

    private function build_terminal_cover_points($jd_data, $cv_data, $strength_signals, $missing_signals) {
        $points = [];
        $role_title = (string) ($jd_data['role_title'] ?? 'the role');

        if (!empty($strength_signals[0]['strength'])) {
            $points[] = sprintf('Open by connecting %s directly to %s so the recruiter understands your strongest fit immediately.', (string) $strength_signals[0]['strength'], $role_title);
        }

        if (!empty($missing_signals[0])) {
            $points[] = sprintf('Address the weaker signal around %s proactively and explain the closest evidence you already have.', (string) $missing_signals[0]);
        }

        if (!empty($strength_signals[1]['strength'])) {
            $points[] = sprintf('Use a second paragraph to reinforce %s with one quantified outcome.', (string) $strength_signals[1]['strength']);
        }

        $points[] = 'Make the closing paragraph commercially useful: state why this brief, why now, and why your profile can contribute quickly.';

        return array_values(array_slice(array_unique(array_filter(array_map('trim', $points))), 0, 5));
    }

    private function build_terminal_interview_prep($jd_data, $strength_signals, $missing_signals) {
        $role_title = (string) ($jd_data['role_title'] ?? 'this role');
        $top_strength = !empty($strength_signals[0]['strength']) ? (string) $strength_signals[0]['strength'] : 'your strongest relevant experience';
        $top_gap = !empty($missing_signals[0]) ? (string) $missing_signals[0] : 'your transition into the role';

        return [
            [
                'category' => 'behavioral',
                'likely_question' => sprintf('Why are you a strong fit for %s?', $role_title),
                'why_theyll_ask' => 'They want to see whether you can frame your background clearly against the live brief.',
                'suggested_response_angle' => sprintf('Anchor the answer around %s, then back it up with one quantified proof point and one reason this role is a logical next step.', $top_strength),
                'example_answer' => sprintf('The clearest reason I fit %s is that I already bring %s, and I have evidence of applying that in a real working context. What excites me most is the chance to use that foundation in a role where the commercial and analytical impact is even more direct.', $role_title, $top_strength),
            ],
            [
                'category' => 'technical',
                'likely_question' => sprintf('Talk me through your experience with %s.', $top_strength),
                'why_theyll_ask' => 'They are testing whether the headline fit on your CV is backed by substance.',
                'suggested_response_angle' => 'Use a concise problem-action-result structure and quantify the outcome wherever possible.',
            ],
            [
                'category' => 'situational',
                'likely_question' => sprintf('What would you need to ramp up fastest in relation to %s?', $top_gap),
                'why_theyll_ask' => 'They want to see honesty, self-awareness, and a credible ramp-up plan.',
                'suggested_response_angle' => sprintf('Acknowledge the gap around %s directly, then explain the adjacent evidence you already have and how quickly you would close the gap.', $top_gap),
            ],
            [
                'category' => 'behavioral',
                'likely_question' => 'Walk me through a time you worked under pressure with competing demands.',
                'why_theyll_ask' => 'This tests execution quality, prioritisation, and stakeholder credibility.',
                'suggested_response_angle' => 'Choose a concrete example with deadlines, explain the trade-offs, and end with the measurable outcome.',
            ],
            [
                'category' => 'situational',
                'likely_question' => 'What would make you worth interviewing over a candidate with a more obvious background?',
                'why_theyll_ask' => 'They want to hear your differentiated value, not generic enthusiasm.',
                'suggested_response_angle' => sprintf('Lead with %s, then explain why your perspective is distinctive and commercially useful.', $top_strength),
            ],
        ];
    }

    private function build_terminal_red_flags($fallback_result, $missing_signals, $preliminary) {
        $flags = [];

        foreach ((array) ($fallback_result['dealbreakers'] ?? []) as $dealbreaker) {
            $flags[] = [
                'issue' => (string) ($dealbreaker['title'] ?? 'Potential dealbreaker'),
                'severity' => 'dealbreaker',
                'evidence' => (string) ($dealbreaker['candidate_has'] ?? ''),
                'mitigation' => (string) ($dealbreaker['fix_strategy'] ?? 'Address this directly in your positioning.'),
            ];
        }

        foreach ((array) ($fallback_result['critical_gaps'] ?? []) as $gap) {
            $flags[] = [
                'issue' => (string) ($gap['title'] ?? 'Critical gap'),
                'severity' => 'serious',
                'evidence' => (string) ($gap['gap_description'] ?? ''),
                'mitigation' => (string) ($gap['fix'] ?? 'Address this before applying.'),
            ];
        }

        if (empty($flags) && !empty($missing_signals[0])) {
            $flags[] = [
                'issue' => sprintf('%s is not visible enough in the CV.', (string) $missing_signals[0]),
                'severity' => 'serious',
                'evidence' => 'The terminal did not find strong direct evidence of this requirement in the pasted CV.',
                'mitigation' => sprintf('Add one clear bullet, summary line, or skills reference that proves %s.', (string) $missing_signals[0]),
            ];
        }

        return array_slice($flags, 0, 6);
    }

    private function build_terminal_overall_assessment($jd_data, $cv_data, $scores, $strength_signals, $missing_signals, $fallback_result) {
        $overall = (int) ($scores['overall'] ?? 0);
        $strength_names = array_slice(array_values(array_filter(array_map(function ($item) {
            return is_array($item) ? (string) ($item['strength'] ?? '') : '';
        }, $strength_signals))), 0, 4);
        $gap_names = array_slice(array_values(array_filter(array_map('strval', $missing_signals))), 0, 4);

        if ($overall >= 80) {
            $competitive_position = 'Strong fit with clear recruiter-facing signals already present.';
            $success_probability = '60-75%';
        } elseif ($overall >= 65) {
            $competitive_position = 'Competitive if the CV is tightened before applying.';
            $success_probability = '40-55%';
        } elseif ($overall >= 50) {
            $competitive_position = 'Borderline fit that depends on better positioning and stronger proof.';
            $success_probability = '20-35%';
        } else {
            $competitive_position = 'Weak direct fit without substantial repositioning or a more adjacent brief.';
            $success_probability = '5-15%';
        }

        $final_recommendation = !empty($fallback_result['strategic_options'][0]['description'])
            ? (string) $fallback_result['strategic_options'][0]['description']
            : 'Use the missing signals, CV rewrites, and cover-letter points in this report before deciding whether to apply.';

        return [
            'fit_percentage' => $overall,
            'primary_strengths' => $strength_names,
            'primary_gaps' => $gap_names,
            'competitive_position' => $competitive_position,
            'success_probability' => $success_probability,
            'final_recommendation' => $final_recommendation,
        ];
    }

    private function build_terminal_experience_summary($jd_data, $cv_data, $missing_keywords, $matched_keywords) {
        $role_title = (string) ($jd_data['role_title'] ?? 'the role');
        $outcome_summary = $this->build_terminal_outcome_signal_summary($cv_data, false);

        if (!empty($matched_keywords) && !empty($missing_keywords)) {
            $summary = sprintf(
                'Your CV already signals %s, but %s still needs to be stated more explicitly for %s.',
                implode(' and ', array_slice($matched_keywords, 0, 2)),
                implode(' and ', array_slice($missing_keywords, 0, 2)),
                $role_title
            );
            return $outcome_summary !== '' ? $summary . ' ' . $outcome_summary : $summary;
        }

        if (!empty($matched_keywords)) {
            $summary = sprintf('Your experience is directionally relevant for %s, especially around %s. The next step is to make the strongest bullets easier to scan quickly.', $role_title, implode(' and ', array_slice($matched_keywords, 0, 2)));
            return $outcome_summary !== '' ? $summary . ' ' . $outcome_summary : $summary;
        }

        $summary = sprintf('The current CV does not yet give recruiters enough direct evidence for %s. Reframing the experience with clearer role language and quantified outcomes will matter.', $role_title);
        return $outcome_summary !== '' ? $summary . ' ' . $outcome_summary : $summary;
    }

    private function build_terminal_outcome_signal_summary($cv_data, $headline = true) {
        $bullet_analysis = (array) ($cv_data['bullet_analysis'] ?? []);
        $total = (int) ($bullet_analysis['total_bullets'] ?? 0);
        $quantified_outcomes = (int) ($bullet_analysis['quantified_outcome_bullets'] ?? 0);

        if ($total <= 0) {
            return '';
        }

        $rate = $quantified_outcomes / $total;

        if ($rate >= 0.5) {
            return $headline
                ? sprintf('The CV already does a good job showing measurable outcomes in %d of %d bullets.', $quantified_outcomes, $total)
                : sprintf('Measured outcomes are already visible in %d of %d bullets, which helps the experience feel credible faster.', $quantified_outcomes, $total);
        }

        if ($rate >= 0.25) {
            return $headline
                ? sprintf('Measured outcomes only show up in %d of %d bullets, so the impact is still inconsistent across the CV.', $quantified_outcomes, $total)
                : sprintf('Only %d of %d bullets currently show measurable outcomes, so some impact is still getting lost.', $quantified_outcomes, $total);
        }

        return $headline
            ? sprintf('Measured outcomes are barely visible right now: only %d of %d bullets show a clear result.', $quantified_outcomes, $total)
            : sprintf('Only %d of %d bullets currently show a clear measurable result, which weakens recruiter confidence in the impact.', $quantified_outcomes, $total);
    }

    private function build_terminal_industry_fit_assessment($jd_data, $cv_data, $preliminary) {
        $jd_sectors = $this->get_terminal_sector_names($jd_data);
        $cv_sectors = $this->get_terminal_sector_names($cv_data);

        if (!empty(array_intersect($jd_sectors, $cv_sectors))) {
            return sprintf('Your sector background already overlaps with the brief through %s.', implode(', ', array_slice(array_intersect($jd_sectors, $cv_sectors), 0, 2)));
        }

        if (!empty($preliminary['sector']) && !empty($preliminary['sector']['transferable'])) {
            return 'There is no direct sector overlap, but the background looks transferable if you position the adjacent knowledge credibly.';
        }

        if (!empty($jd_sectors)) {
            return sprintf('The role is leaning toward %s, while your CV does not yet show that sector clearly enough.', implode(', ', array_slice($jd_sectors, 0, 2)));
        }

        return 'Sector fit is not a major differentiator in this brief, so the CV should win on capability and evidence instead.';
    }

    private function format_terminal_years_required($jd_data) {
        $min = (float) ($jd_data['experience']['min_years'] ?? 0);
        $max = (float) ($jd_data['experience']['max_years'] ?? 0);
        if ($min > 0 && $max > 0) {
            return sprintf('%s-%s years', $min, $max);
        }
        if ($min > 0) {
            return sprintf('%s+ years', $min);
        }
        return 'Not specified';
    }

    private function format_terminal_years_candidate_has($cv_data) {
        $years = (float) ($cv_data['total_years'] ?? 0);
        return $years > 0 ? sprintf('%.1f years', $years) : 'Not clearly detected';
    }

    private function format_terminal_experience_gap($preliminary, $experience_score) {
        if (!empty($preliminary['experience'])) {
            $gap = $preliminary['experience'];
            if (($gap['gap'] ?? 0) > 0) {
                return sprintf('Approximately %s year gap versus the stated requirement.', (string) $gap['gap']);
            }
        }

        if ($experience_score >= 75) {
            return 'Experience depth looks comfortably aligned with the brief.';
        }
        if ($experience_score >= 55) {
            return 'Experience looks directionally relevant but needs sharper proof of fit.';
        }
        return 'Experience alignment is weak or not yet clearly evidenced in the CV.';
    }

    private function get_terminal_sector_names($data) {
        $names = [];
        foreach ((array) ($data['sectors'] ?? []) as $sector) {
            if (!empty($sector['name'])) {
                $names[] = (string) $sector['name'];
            }
        }
        return array_values(array_unique($names));
    }

    private function suggest_terminal_action_verb($bullet) {
        $lower = strtolower((string) $bullet);
        if (strpos($lower, 'analysis') !== false || strpos($lower, 'model') !== false) {
            return 'developed';
        }
        if (strpos($lower, 'report') !== false || strpos($lower, 'presentation') !== false) {
            return 'delivered';
        }
        if (strpos($lower, 'project') !== false || strpos($lower, 'implementation') !== false) {
            return 'executed';
        }
        return 'led';
    }

    private function unique_terminal_items($items, $field) {
        $seen = [];
        $result = [];

        foreach ((array) $items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $value = trim((string) ($item[$field] ?? ''));
            if ($value === '') {
                continue;
            }
            $key = strtolower($value);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $item;
        }

        return $result;
    }

    /**
     * Calculate unified score
     */
    private function calculate_score($analysis_result) {
        $base_score = 100;

        // Penalties
        $penalties = [
            'dealbreaker' => -30,
            'critical' => -10,
            'improvement' => -3,
        ];

        // Bonuses
        $bonuses = [
            'strong_match' => 5,
            'target_school' => 5,
            'exact_experience' => 10,
        ];

        $score = $base_score;
        $breakdown = [
            'base' => $base_score,
            'penalties' => [],
            'bonuses' => [],
        ];

        // Apply penalties for dealbreakers
        $dealbreakers = $analysis_result['dealbreakers'] ?? [];
        foreach ($dealbreakers as $db) {
            $penalty = $penalties['dealbreaker'];
            $score += $penalty;
            $breakdown['penalties'][] = [
                'type' => $db['type'] ?? 'dealbreaker',
                'points' => $penalty,
            ];
        }

        // Apply penalties for critical gaps
        $critical = $analysis_result['critical_gaps'] ?? [];
        foreach ($critical as $gap) {
            $penalty = $penalties['critical'];
            $score += $penalty;
            $breakdown['penalties'][] = [
                'type' => $gap['type'] ?? 'critical',
                'points' => $penalty,
            ];
        }

        // Apply penalties for improvements
        $improvements = $analysis_result['improvements'] ?? [];
        foreach ($improvements as $imp) {
            $penalty = $penalties['improvement'];
            $score += $penalty;
            $breakdown['penalties'][] = [
                'type' => $imp['type'] ?? 'improvement',
                'points' => $penalty,
            ];
        }

        // Apply bonuses for strengths
        $strengths = $analysis_result['strengths'] ?? [];
        foreach ($strengths as $strength) {
            $bonus = $bonuses['strong_match'];
            $score += $bonus;
            $breakdown['bonuses'][] = [
                'type' => $strength['type'] ?? 'strength',
                'points' => $bonus,
            ];
        }

        // Clamp score
        $score = max(0, min(100, $score));

        // Determine risk level
        $risk_level = $this->determine_risk_level($score, count($dealbreakers));

        return [
            'score' => $score,
            'risk_level' => $risk_level,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Determine risk level from score
     */
    private function determine_risk_level($score, $dealbreaker_count = 0) {
        // Any dealbreakers = at least moderate risk
        if ($dealbreaker_count > 0 && $score > 60) {
            return 'moderate';
        }

        if ($score <= 40) {
            return 'high';
        } elseif ($score <= 60) {
            return 'moderate';
        } elseif ($score <= 80) {
            return 'competitive';
        } else {
            return 'strong';
        }
    }

    /**
     * Generate strategic options based on analysis
     */
    private function generate_strategic_options($score, $analysis_result) {
        $options = [];
        $risk = $score['risk_level'];
        $dealbreakers = $analysis_result['dealbreakers'] ?? [];

        if ($risk === 'high') {
            $options[] = [
                'type' => 'do_not_apply',
                'title' => 'Consider Alternative Roles',
                'description' => 'This role has significant gaps that may be difficult to overcome. Consider targeting roles that better match your current profile.',
                'success_probability' => '< 10%',
            ];

            if (count($dealbreakers) > 0) {
                $options[] = [
                    'type' => 'network_first',
                    'title' => 'Network Before Applying',
                    'description' => 'If you\'re set on this role, secure a warm introduction first. Cold applications with dealbreakers rarely succeed.',
                    'success_probability' => '15-20%',
                ];
            }
        } elseif ($risk === 'moderate') {
            $options[] = [
                'type' => 'apply_with_strategy',
                'title' => 'Apply with Strong Cover Letter',
                'description' => 'Address your gaps directly in your cover letter. Focus on transferable skills and demonstrate genuine fit.',
                'success_probability' => '25-35%',
            ];

            $options[] = [
                'type' => 'network_parallel',
                'title' => 'Apply + Network Simultaneously',
                'description' => 'Submit application but also reach out to people at the firm for informational conversations.',
                'success_probability' => '35-45%',
            ];
        } elseif ($risk === 'competitive') {
            $options[] = [
                'type' => 'apply_now',
                'title' => 'Apply Promptly',
                'description' => 'You\'re a competitive candidate. Apply within 48 hours of posting to maximize visibility.',
                'success_probability' => '45-55%',
            ];

            $options[] = [
                'type' => 'tailor_cv',
                'title' => 'Optimize CV for This Role',
                'description' => 'Make targeted improvements to your CV before applying. Small optimizations can make a difference at this level.',
                'success_probability' => '55-65%',
            ];
        } else {
            $options[] = [
                'type' => 'strong_candidate',
                'title' => 'Strong Fit - Apply Immediately',
                'description' => 'You\'re a strong candidate for this role. Apply now and prepare for interviews.',
                'success_probability' => '65-75%',
            ];
        }

        return $options;
    }

    /**
     * Quick analysis for comparison mode (lighter weight)
     */
    public function quick_analyze($jd_text, $cv_text) {
        $jd_data = $this->jd_parser->parse($jd_text);
        $cv_data = $this->cv_parser->parse($cv_text);
        $preliminary = $this->run_preliminary_analysis($jd_data, $cv_data);

        // Use fallback only for speed
        $result = $this->fallback_analyzer->analyze($jd_data, $cv_data, $preliminary);
        $score = $this->calculate_score($result);

        return [
            'score' => $score['score'],
            'risk_level' => $score['risk_level'],
            'dealbreaker_count' => count($result['dealbreakers'] ?? []),
            'critical_count' => count($result['critical_gaps'] ?? []),
            'keyword_match_rate' => $this->analyze_keywords($jd_data, $cv_data)['match_rate'],
        ];
    }
}
