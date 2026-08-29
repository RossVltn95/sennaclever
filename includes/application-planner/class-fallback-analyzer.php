<?php
/**
 * Fallback Analyzer
 *
 * Intelligent rule-based analyzer that runs when Claude API is unavailable.
 * Uses knowledge base assets to provide quality analysis:
 * - Synonym-aware keyword matching
 * - Template-based recommendations
 * - Industry-aware strategic advice
 *
 * @package SennaCareers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Fallback_Analyzer {

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
     * @param array $jd_data Parsed JD data
     * @param array $cv_data Parsed CV data
     * @param array $preliminary Preliminary gap analysis
     * @return array Complete analysis result
     */
    public function analyze($jd_data, $cv_data, $preliminary) {
        // Step 1: Enhanced keyword matching with synonyms
        $keyword_gaps = $this->analyze_keywords_with_synonyms($jd_data, $cv_data);

        // Step 2: Apply dealbreaker rules
        $dealbreakers = $this->identify_dealbreakers($jd_data, $cv_data, $preliminary);

        // Step 3: Identify critical gaps
        $critical_gaps = $this->identify_critical_gaps($jd_data, $cv_data, $preliminary);

        // Step 4: Identify improvements
        $improvements = $this->identify_improvements($jd_data, $cv_data, $keyword_gaps);

        // Step 5: Identify strengths
        $strengths = $this->identify_strengths($jd_data, $cv_data);

        // Step 6: Generate strategic advice
        $strategic_options = $this->generate_strategic_advice($dealbreakers, $critical_gaps, $jd_data, $cv_data);

        return [
            'dealbreakers' => $dealbreakers,
            'critical_gaps' => $critical_gaps,
            'improvements' => $improvements,
            'strengths' => $strengths,
            'strategic_options' => $strategic_options,
            'keyword_gaps' => $keyword_gaps,
        ];
    }

    /**
     * Analyze keywords with synonym matching
     */
    private function analyze_keywords_with_synonyms($jd_data, $cv_data) {
        $jd_keywords = $jd_data['keywords'];
        $cv_keywords = $cv_data['keywords'];
        $gaps = [];

        foreach ($jd_keywords as $key => $jd_kw) {
            // Get all synonyms for this keyword
            $synonyms = $this->knowledge_base->get_synonyms_for($jd_kw['canonical']);

            // Check if ANY synonym appears in CV
            $cv_count = 0;
            $matched_term = null;

            foreach ($synonyms as $synonym) {
                $synonym_key = $this->find_keyword_key($cv_keywords, $synonym);
                if ($synonym_key && isset($cv_keywords[$synonym_key])) {
                    $cv_count += $cv_keywords[$synonym_key]['count'];
                    $matched_term = $cv_keywords[$synonym_key]['canonical'];
                }
            }

            if ($cv_count === 0) {
                // True gap - no synonyms found
                $gaps[] = [
                    'keyword' => $jd_kw['canonical'],
                    'jd_count' => $jd_kw['count'],
                    'cv_count' => 0,
                    'severity' => $jd_kw['is_important'] ? 'critical' : 'improvement',
                    'synonyms_checked' => $synonyms,
                    'gap_type' => 'missing',
                ];
            } elseif ($cv_count < $jd_kw['count'] / 2) {
                // Partial gap - mentioned but underemphasized
                $gaps[] = [
                    'keyword' => $jd_kw['canonical'],
                    'jd_count' => $jd_kw['count'],
                    'cv_count' => $cv_count,
                    'matched_via' => $matched_term,
                    'severity' => 'improvement',
                    'gap_type' => 'underemphasized',
                    'note' => "Found as '{$matched_term}' but underemphasized vs JD",
                ];
            }
        }

        return $gaps;
    }

    /**
     * Find keyword key by term
     */
    private function find_keyword_key($keywords, $term) {
        $term_lower = strtolower($term);

        foreach ($keywords as $key => $data) {
            if (strtolower($data['canonical']) === $term_lower) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Identify dealbreakers using rules
     */
    private function identify_dealbreakers($jd_data, $cv_data, $preliminary) {
        $dealbreakers = [];
        $rules = $this->knowledge_base->get_dealbreaker_rules();

        // 1. Experience gap > 2 years
        if (isset($preliminary['experience']) && $preliminary['experience']['gap'] > 2) {
            $rule = $rules['experience_gap'] ?? [];
            $dealbreakers[] = [
                'type' => 'experience_gap',
                'title' => 'Experience Gap',
                'requirement' => $preliminary['experience']['required'] . '+ years',
                'candidate_has' => $preliminary['experience']['candidate_has'] . ' years',
                'reality_check' => $rule['fallback_advice'] ?? 'This is typically a hard filter for senior roles.',
                'can_overcome' => false,
                'fix_strategy' => $rule['fix_strategies'][0] ?? 'Consider targeting roles one level below.',
            ];
        }

        // 2. Missing required credentials
        foreach ($preliminary as $key => $gap) {
            if (strpos($key, 'credential_') === 0 && $gap['severity'] === 'dealbreaker') {
                $rule = $rules['missing_required_credential'] ?? [];
                $dealbreakers[] = [
                    'type' => 'missing_credential',
                    'title' => "Missing Required: {$gap['credential']}",
                    'requirement' => $gap['credential'] . ' (Required)',
                    'candidate_has' => 'Not found in CV',
                    'reality_check' => $rule['fallback_advice'] ?? 'Many firms use credentials as ATS filters.',
                    'can_overcome' => true,
                    'fix_strategy' => $rule['fix_strategies'][0] ?? 'Start the credential immediately.',
                ];
            }
        }

        // 3. Geographic mismatch (if required)
        if (isset($preliminary['location']) && $preliminary['location']['severity'] === 'dealbreaker') {
            $rule = $rules['geographic_mismatch'] ?? [];
            $dealbreakers[] = [
                'type' => 'geographic',
                'title' => 'Location Mismatch',
                'requirement' => 'Based in ' . $preliminary['location']['required'],
                'candidate_has' => 'Based in ' . $preliminary['location']['candidate_has'],
                'reality_check' => $rule['fallback_advice'] ?? 'Address relocation in cover letter.',
                'can_overcome' => true,
                'fix_strategy' => $rule['fix_strategies'][0] ?? 'State relocation plans in application.',
            ];
        }

        // 4. Seniority mismatch (2+ levels)
        if (isset($preliminary['seniority']) && $preliminary['seniority']['severity'] === 'dealbreaker') {
            $rule = $rules['seniority_mismatch'] ?? [];
            $dealbreakers[] = [
                'type' => 'seniority',
                'title' => 'Seniority Gap',
                'requirement' => ucfirst($preliminary['seniority']['required_level']) . ' level',
                'candidate_has' => $preliminary['seniority']['candidate_years'] . ' years experience',
                'reality_check' => $rule['fallback_advice'] ?? 'Targeting roles 2+ levels up rarely succeeds.',
                'can_overcome' => false,
                'fix_strategy' => $rule['fix_strategies'][0] ?? 'Target roles one level up instead.',
            ];
        }

        return $dealbreakers;
    }

    /**
     * Identify critical gaps
     */
    private function identify_critical_gaps($jd_data, $cv_data, $preliminary) {
        $critical_gaps = [];

        // 1. Experience gap 1-2 years
        if (isset($preliminary['experience']) && $preliminary['experience']['gap'] > 0 && $preliminary['experience']['gap'] <= 2) {
            $critical_gaps[] = [
                'type' => 'experience_gap',
                'title' => 'Experience Gap (Addressable)',
                'gap_description' => $preliminary['experience']['gap'] . ' year gap vs requirement',
                'impact' => 'May require strong cover letter or warm introduction',
                'fix' => 'Emphasize accelerated career progression and equivalent responsibility',
            ];
        }

        // 2. Sector mismatch (non-transferable)
        if (isset($preliminary['sector']) && $preliminary['sector']['severity'] === 'critical') {
            $jd_sectors = implode(', ', $preliminary['sector']['required']);
            $cv_sectors = implode(', ', $preliminary['sector']['candidate_has']);

            if ($preliminary['sector']['transferable']) {
                $transfer = $this->knowledge_base->check_sector_transfer(
                    $preliminary['sector']['candidate_has'][0] ?? '',
                    $preliminary['sector']['required'][0] ?? ''
                );

                $critical_gaps[] = [
                    'type' => 'sector_gap',
                    'title' => 'Sector Experience Gap',
                    'gap_description' => "JD focuses on {$jd_sectors}; you have {$cv_sectors}",
                    'impact' => 'May be addressable through transferable experience',
                    'fix' => $transfer['narrative'] ?? 'Highlight transferable skills in cover letter',
                ];
            } else {
                $critical_gaps[] = [
                    'type' => 'sector_gap',
                    'title' => 'Sector Experience Gap',
                    'gap_description' => "JD focuses on {$jd_sectors}; you have {$cv_sectors}",
                    'impact' => 'Sector specialists are typically preferred',
                    'fix' => 'Research the sector deeply and demonstrate knowledge in interviews',
                ];
            }
        }

        // 3. Location mismatch (if not dealbreaker)
        if (isset($preliminary['location']) && $preliminary['location']['severity'] === 'critical') {
            $critical_gaps[] = [
                'type' => 'location_gap',
                'title' => 'Location Consideration',
                'gap_description' => "Role is in {$preliminary['location']['required']}",
                'impact' => 'May require relocation discussion',
                'fix' => 'Address willingness to relocate in cover letter opening',
            ];
        }

        // 4. Missing preferred credentials
        foreach ($preliminary as $key => $gap) {
            if (strpos($key, 'credential_') === 0 && !$gap['is_required'] && $gap['severity'] === 'improvement') {
                $critical_gaps[] = [
                    'type' => 'credential_gap',
                    'title' => "Preferred: {$gap['credential']}",
                    'gap_description' => "JD lists {$gap['credential']} as preferred",
                    'impact' => 'Not a dealbreaker but differentiator for other candidates',
                    'fix' => "Consider starting {$gap['credential']} or highlighting equivalent",
                ];
            }
        }

        // 5. Capability gaps in the target brief
        foreach ((array) ($jd_data['capabilities'] ?? []) as $capability_key => $capability) {
            $required_score = (int) ($capability['priority_score'] ?? 0);
            $candidate_score = (int) (($cv_data['capabilities'][$capability_key]['score'] ?? 0));
            if ($required_score < 35 || $candidate_score >= max(25, (int) round($required_score * 0.45))) {
                continue;
            }

            $critical_gaps[] = [
                'type' => 'capability_gap',
                'title' => (string) ($capability['label'] ?? $capability_key),
                'gap_description' => sprintf(
                    'The JD is screening for %s, but your CV does not evidence it strongly enough yet.',
                    (string) ($capability['label'] ?? $capability_key)
                ),
                'impact' => !empty($capability['is_required'])
                    ? 'This is a core screening capability for the brief.'
                    : 'This would materially improve recruiter confidence.',
                'fix' => sprintf(
                    'Add one explicit bullet showing %s with a concrete outcome, tool, or transaction context.',
                    strtolower((string) ($capability['label'] ?? $capability_key))
                ),
            ];
        }

        return $critical_gaps;
    }

    /**
     * Identify improvements
     */
    private function identify_improvements($jd_data, $cv_data, $keyword_gaps) {
        $improvements = [];
        $rewrite_templates = $this->knowledge_base->get_rewrite_templates();

        // 1. Missing keywords
        foreach ($keyword_gaps as $gap) {
            if ($gap['severity'] === 'improvement') {
                $improvements[] = [
                    'type' => 'keyword_gap',
                    'title' => "Add: {$gap['keyword']}",
                    'current' => $gap['gap_type'] === 'missing' ? 'Not mentioned in CV' : "Mentioned {$gap['cv_count']}x (JD has {$gap['jd_count']}x)",
                    'suggested' => "Add '{$gap['keyword']}' to relevant experience bullets",
                    'impact' => 'Improves ATS match and keyword alignment',
                ];
            }
        }

        // 2. Weak bullets from CV analysis
        $bullet_analysis = $cv_data['bullet_analysis'] ?? [];
        if (!empty($bullet_analysis['weak_examples'])) {
            foreach ($bullet_analysis['weak_examples'] as $weak) {
                // Find matching rewrite template
                $template = null;
                foreach ($rewrite_templates as $key => $tpl) {
                    if (preg_match('/' . $tpl['trigger'] . '/i', $weak['bullet'])) {
                        $template = $tpl;
                        break;
                    }
                }

                $improvements[] = [
                    'type' => 'weak_bullet',
                    'title' => 'Rewrite Weak Bullet',
                    'current' => substr($weak['bullet'], 0, 100) . '...',
                    'suggested' => $template ? $template['templates']['generic'] ?? 'Use stronger action verb and add quantification' : 'Use stronger action verb and add quantification',
                    'impact' => $template['why_it_matters'] ?? 'Stronger bullets increase recruiter interest',
                ];
            }
        }

        // 3. Low quantification
        $quantified = $bullet_analysis['quantified_bullets'] ?? 0;
        $quantified_outcomes = $bullet_analysis['quantified_outcome_bullets'] ?? 0;
        $total = $bullet_analysis['total_bullets'] ?? 1;
        $quant_rate = $total > 0 ? ($quantified / $total) * 100 : 0;
        $outcome_rate = $total > 0 ? ($quantified_outcomes / $total) * 100 : 0;

        if ($quant_rate < 50) {
            $template = $rewrite_templates['no_quantification'] ?? [];
            $improvements[] = [
                'type' => 'quantification',
                'title' => 'Add More Numbers',
                'current' => "Only " . round($quant_rate) . "% of bullets have numbers",
                'suggested' => 'Add deal sizes, team sizes, percentages, or counts to more bullets',
                'impact' => $template['why_it_matters'] ?? 'Quantified bullets are 40% more likely to catch attention',
            ];
        }

        if ($outcome_rate < 35) {
            $improvements[] = [
                'type' => 'quantified_outcomes',
                'title' => 'Show More Measurable Outcomes',
                'current' => "Only " . round($outcome_rate) . "% of bullets tie numbers to a clear result",
                'suggested' => 'Turn scope-only bullets into result bullets by showing what improved, closed, saved, or accelerated.',
                'impact' => 'Recruiters trust quantified outcomes more than raw activity or scope alone.',
            ];
        }

        // 4. Under-positioned capabilities
        foreach ((array) ($jd_data['capabilities'] ?? []) as $capability_key => $capability) {
            $required_score = (int) ($capability['priority_score'] ?? 0);
            $candidate_score = (int) (($cv_data['capabilities'][$capability_key]['score'] ?? 0));

            if ($required_score < 25 || $candidate_score <= 0 || $candidate_score >= $required_score) {
                continue;
            }

            $improvements[] = [
                'type' => 'capability_positioning',
                'title' => 'Sharpen: ' . (string) ($capability['label'] ?? $capability_key),
                'current' => 'The capability may be present in your background, but it is not yet prominent enough in the CV.',
                'suggested' => sprintf(
                    'Move %s closer to the top of the CV and attach it to a quantified or role-specific example.',
                    strtolower((string) ($capability['label'] ?? $capability_key))
                ),
                'impact' => 'Improves recruiter recognition of your fit against the strongest parts of the brief.',
            ];
        }

        return $improvements;
    }

    /**
     * Identify strengths
     */
    private function identify_strengths($jd_data, $cv_data) {
        $strengths = [];

        // 1. Experience match or exceed
        if ($jd_data['experience']['min_years'] && $cv_data['total_years'] >= $jd_data['experience']['min_years']) {
            $strengths[] = [
                'type' => 'experience_match',
                'title' => 'Experience Requirement Met',
                'description' => "You have {$cv_data['total_years']} years vs {$jd_data['experience']['min_years']}+ required",
                'leverage' => 'Emphasize depth and progression in your experience',
            ];
        }

        // 2. Credential matches
        foreach ($jd_data['credentials'] as $key => $jd_cred) {
            if (isset($cv_data['credentials'][$key])) {
                $cv_cred = $cv_data['credentials'][$key];
                $status = $cv_cred['in_progress'] ? ' (In Progress)' : '';

                $strengths[] = [
                    'type' => 'credential_match',
                    'title' => "{$jd_cred['credential']} Match{$status}",
                    'description' => "You have {$jd_cred['credential']}" . ($jd_cred['is_required'] ? ' which is required' : ' which is preferred'),
                    'leverage' => 'Highlight certification early in CV and cover letter',
                ];
            }
        }

        // 3. Sector overlap
        $jd_sectors = array_keys($jd_data['sectors']);
        $cv_sectors = array_keys($cv_data['sectors']);
        $overlap = array_intersect($jd_sectors, $cv_sectors);

        if (!empty($overlap)) {
            $overlap_names = array_map(function ($key) use ($jd_data) {
                return $jd_data['sectors'][$key]['name'];
            }, $overlap);

            $strengths[] = [
                'type' => 'sector_match',
                'title' => 'Sector Experience Aligned',
                'description' => 'You have experience in: ' . implode(', ', $overlap_names),
                'leverage' => 'Reference specific sector deals and knowledge in cover letter',
            ];
        }

        // 4. Target school education
        foreach ($cv_data['education'] as $edu) {
            if ($edu['is_target_school']) {
                $strengths[] = [
                    'type' => 'target_school',
                    'title' => "Target School: {$edu['school']}",
                    'description' => ($edu['degree'] ? $edu['degree'] . ' from ' : '') . $edu['school'],
                    'leverage' => 'Many PE/IB firms value target school backgrounds',
                ];
                break; // Only mention one
            }
        }

        // 5. Strong quantification
        $bullet_analysis = $cv_data['bullet_analysis'] ?? [];
        $quantified = $bullet_analysis['quantified_bullets'] ?? 0;
        $quantified_outcomes = $bullet_analysis['quantified_outcome_bullets'] ?? 0;
        $total = $bullet_analysis['total_bullets'] ?? 1;

        if ($total > 0 && ($quantified / $total) > 0.6) {
            $strengths[] = [
                'type' => 'quantification',
                'title' => 'Well-Quantified CV',
                'description' => round(($quantified / $total) * 100) . '% of your bullets include numbers',
                'leverage' => 'Your CV demonstrates measurable impact effectively',
            ];
        }

        if ($total > 0 && ($quantified_outcomes / $total) >= 0.4) {
            $strengths[] = [
                'type' => 'quantified_outcomes',
                'title' => 'Outcome-Led Evidence',
                'description' => round(($quantified_outcomes / $total) * 100) . '% of your bullets link the work to a measurable result',
                'leverage' => 'Keep those result-led bullets high in the CV because they make impact easier for recruiters to trust quickly',
            ];
        }

        // 6. Location match
        if (!empty($jd_data['location']['city']) && !empty($cv_data['contact']['location'])) {
            if (strtolower($jd_data['location']['city']) === strtolower($cv_data['contact']['location'])) {
                $strengths[] = [
                    'type' => 'location_match',
                    'title' => 'Location Aligned',
                    'description' => "You're based in {$cv_data['contact']['location']} where the role is located",
                    'leverage' => 'No relocation needed - one less concern for the employer',
                ];
            }
        }

        // 7. Capability overlap
        foreach ((array) ($jd_data['capabilities'] ?? []) as $capability_key => $capability) {
            $required_score = (int) ($capability['priority_score'] ?? 0);
            $cv_capability = (array) ($cv_data['capabilities'][$capability_key] ?? []);
            $candidate_score = (int) ($cv_capability['score'] ?? 0);

            if ($required_score < 25 || $candidate_score < max(35, (int) round($required_score * 0.7))) {
                continue;
            }

            $strengths[] = [
                'type' => 'capability_match',
                'title' => (string) ($capability['label'] ?? $capability_key),
                'description' => !empty($cv_capability['direct_terms'])
                    ? sprintf(
                        'Your CV already signals %s through %s.',
                        (string) ($capability['label'] ?? $capability_key),
                        implode(', ', array_slice((array) ($cv_capability['direct_terms'] ?? []), 0, 2))
                    )
                    : sprintf('Your profile is directionally strong in %s.', (string) ($capability['label'] ?? $capability_key)),
                'leverage' => sprintf(
                    'Bring %s forward earlier so recruiters do not have to infer it.',
                    strtolower((string) ($capability['label'] ?? $capability_key))
                ),
            ];
        }

        return $strengths;
    }

    /**
     * Generate strategic advice
     */
    private function generate_strategic_advice($dealbreakers, $critical_gaps, $jd_data, $cv_data) {
        $options = [];
        $dealbreaker_count = count($dealbreakers);
        $critical_count = count($critical_gaps);

        if ($dealbreaker_count >= 2) {
            // Multiple dealbreakers - low chance
            $options[] = [
                'type' => 'do_not_apply',
                'title' => 'Consider Alternative Roles',
                'description' => "With {$dealbreaker_count} dealbreakers, cold applying has very low success probability. Focus on roles that better match your current profile.",
                'success_probability' => '< 5%',
            ];

            $options[] = [
                'type' => 'network_first',
                'title' => 'Network Before Applying',
                'description' => 'If committed to this role, secure a warm introduction from someone at the firm. This can help bypass initial filters.',
                'success_probability' => '10-15%',
            ];

        } elseif ($dealbreaker_count === 1) {
            // Single dealbreaker - addressable
            $db = $dealbreakers[0];

            if ($db['can_overcome']) {
                $options[] = [
                    'type' => 'apply_with_mitigation',
                    'title' => 'Apply with Strong Cover Letter',
                    'description' => "Address your {$db['type']} gap directly in paragraph 1 of your cover letter. Explain how you'll overcome it.",
                    'success_probability' => '20-30%',
                ];
            }

            $options[] = [
                'type' => 'network_parallel',
                'title' => 'Apply + Network Simultaneously',
                'description' => 'Submit your application but also reach out to people at the firm for informational conversations.',
                'success_probability' => '25-35%',
            ];

        } elseif ($critical_count > 0) {
            // No dealbreakers but critical gaps
            $options[] = [
                'type' => 'apply_strategically',
                'title' => 'Apply with Targeted CV',
                'description' => 'Make targeted improvements to your CV addressing the critical gaps, then apply within 48 hours.',
                'success_probability' => '35-45%',
            ];

            $options[] = [
                'type' => 'tailor_application',
                'title' => 'Craft Custom Cover Letter',
                'description' => 'Write a cover letter that proactively addresses your gaps and emphasizes your transferable strengths.',
                'success_probability' => '40-50%',
            ];

        } else {
            // Strong candidate
            $options[] = [
                'type' => 'apply_now',
                'title' => 'Apply Immediately',
                'description' => "You're a strong candidate. Apply within 24-48 hours of the posting for best visibility.",
                'success_probability' => '55-65%',
            ];

            $options[] = [
                'type' => 'prepare_interviews',
                'title' => 'Start Interview Prep',
                'description' => 'Begin preparing for interviews now. Research the firm, prepare case studies, and practice your story.',
                'success_probability' => '60-70%',
            ];
        }

        return $options;
    }
}
