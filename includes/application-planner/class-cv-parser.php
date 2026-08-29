<?php
/**
 * CV Parser
 *
 * Extracts structured data from CVs/resumes including:
 * - Total years of experience
 * - Credentials and qualifications
 * - Work history with companies and roles
 * - Education details
 * - Skills and keywords
 * - Quantified achievements
 *
 * @package SennaCareers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Planner_CV_Parser {

    /**
     * Knowledge base instance
     */
    private $knowledge_base;

    /**
     * Strong action verbs for bullet analysis
     */
    private $strong_verbs = [
        'led', 'managed', 'developed', 'created', 'built', 'launched', 'drove',
        'delivered', 'executed', 'implemented', 'designed', 'architected',
        'established', 'transformed', 'spearheaded', 'pioneered', 'orchestrated',
        'negotiated', 'secured', 'closed', 'generated', 'increased', 'reduced',
        'optimized', 'streamlined', 'automated', 'restructured', 'scaled',
    ];

    /**
     * Weak action verbs that should be replaced
     */
    private $weak_verbs = [
        'helped', 'assisted', 'supported', 'participated', 'contributed',
        'worked on', 'was responsible for', 'was involved in', 'handled',
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->knowledge_base = SFFC_Knowledge_Base::get_instance();
    }

    /**
     * Parse CV text
     *
     * @param string $cv_text Raw CV text
     * @return array Parsed CV data
     */
    public function parse($cv_text) {
        $cv_text = $this->clean_text($cv_text);
        $cv_lower = strtolower($cv_text);
        $current_role = $this->extract_current_role($cv_text);
        $role_family_guess = $this->extract_role_family_guess($cv_lower, strtolower((string) ($current_role['role'] ?? '')));

        return [
            'raw_text' => $cv_text,
            'contact' => $this->extract_contact($cv_text),
            'experience' => $this->extract_experience($cv_text),
            'total_years' => $this->calculate_total_years($cv_text),
            'credentials' => $this->extract_credentials($cv_text),
            'education' => $this->extract_education($cv_text),
            'skills' => $this->extract_skills($cv_lower),
            'keywords' => $this->extract_keywords($cv_lower),
            'sectors' => $this->extract_sectors($cv_lower),
            'role_family_guess' => $role_family_guess,
            'capabilities' => $this->extract_capabilities($cv_lower, $role_family_guess['key']),
            'tools' => $this->extract_tools($cv_lower),
            'languages' => $this->extract_languages($cv_lower),
            'bullet_analysis' => $this->analyze_bullets($cv_text),
            'quantified_achievements' => $this->extract_quantified($cv_text),
            'current_role' => $current_role,
        ];
    }

    /**
     * Clean and normalize text
     */
    private function clean_text($text) {
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        // Normalize line breaks
        $text = preg_replace('/[\r\n]+/', "\n", $text);
        // Remove special unicode characters
        $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
        return trim($text);
    }

    /**
     * Extract contact information
     */
    private function extract_contact($text) {
        $contact = [
            'email' => '',
            'phone' => '',
            'linkedin' => '',
            'location' => '',
        ];

        // Email
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches)) {
            $contact['email'] = $matches[0];
        }

        // Phone
        if (preg_match('/[\+]?[(]?[0-9]{1,3}[)]?[-\s\.]?[(]?[0-9]{1,4}[)]?[-\s\.]?[0-9]{3,6}[-\s\.]?[0-9]{0,4}/', $text, $matches)) {
            $contact['phone'] = $matches[0];
        }

        // LinkedIn
        if (preg_match('/linkedin\.com\/in\/([a-zA-Z0-9\-]+)/', $text, $matches)) {
            $contact['linkedin'] = $matches[1];
        }

        // Location (first few lines usually contain location)
        $first_lines = substr($text, 0, 500);
        $major_cities = ['London', 'New York', 'Dubai', 'Hong Kong', 'Singapore', 'San Francisco', 'Chicago', 'Boston', 'Los Angeles', 'Paris', 'Frankfurt', 'Sydney', 'Toronto', 'Mumbai'];

        foreach ($major_cities as $city) {
            if (stripos($first_lines, $city) !== false) {
                $contact['location'] = $city;
                break;
            }
        }

        return $contact;
    }

    /**
     * Extract work experience entries
     */
    private function extract_experience($text) {
        $experience = [];

        // Common date patterns
        $date_pattern = '(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s*\d{4}';
        $present_pattern = '(?:Present|Current|Now|Ongoing)';
        $range_pattern = "({$date_pattern})\s*[-–to]+\s*({$date_pattern}|{$present_pattern})";

        // Find all date ranges
        preg_match_all("/{$range_pattern}/i", $text, $matches, PREG_OFFSET_CAPTURE);

        if (!empty($matches[0])) {
            foreach ($matches[0] as $index => $match) {
                $position = $match[1];
                $date_range = $match[0];

                // Extract context around the date (company/role)
                $context_start = max(0, $position - 200);
                $context = substr($text, $context_start, 400);

                // Try to find company and role
                $entry = [
                    'date_range' => $date_range,
                    'start_date' => $matches[1][$index][0] ?? '',
                    'end_date' => $matches[2][$index][0] ?? '',
                    'is_current' => preg_match("/{$present_pattern}/i", $matches[2][$index][0] ?? ''),
                    'company' => $this->extract_company_from_context($context),
                    'role' => $this->extract_role_from_context($context),
                    'bullets' => [],
                ];

                // Calculate duration
                $entry['years'] = $this->calculate_duration($entry['start_date'], $entry['end_date']);

                $experience[] = $entry;
            }
        }

        return $experience;
    }

    /**
     * Extract company name from context
     */
    private function extract_company_from_context($context) {
        // Common patterns
        $patterns = [
            '/([A-Z][A-Za-z0-9\s&\.\-]{2,30})\s*(?:\||–|-)\s*[A-Z]/i',
            '/(?:at|@)\s+([A-Z][A-Za-z0-9\s&\.\-]{2,30})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $context, $matches)) {
                return trim($matches[1]);
            }
        }

        return '';
    }

    /**
     * Extract role from context
     */
    private function extract_role_from_context($context) {
        // Common role titles
        $roles = [
            'Partner', 'Managing Director', 'Director', 'Senior Vice President', 'Vice President', 'VP',
            'Principal', 'Senior Associate', 'Associate', 'Senior Analyst', 'Analyst',
            'Manager', 'Senior Manager', 'Head of', 'Lead', 'Chief', 'CEO', 'CFO', 'COO', 'CTO',
        ];

        foreach ($roles as $role) {
            if (stripos($context, $role) !== false) {
                // Try to get fuller role title
                if (preg_match("/({$role}[A-Za-z,\s\-]{0,30})/i", $context, $matches)) {
                    return trim($matches[1]);
                }
                return $role;
            }
        }

        return '';
    }

    /**
     * Calculate total years of experience
     */
    private function calculate_total_years($text) {
        $experience = $this->extract_experience($text);
        $total = 0;

        foreach ($experience as $entry) {
            $total += $entry['years'];
        }

        // Round to nearest 0.5
        return round($total * 2) / 2;
    }

    /**
     * Calculate duration between dates
     */
    private function calculate_duration($start, $end) {
        try {
            $start_date = new DateTime($start);

            if (preg_match('/present|current|now|ongoing/i', $end)) {
                $end_date = new DateTime();
            } else {
                $end_date = new DateTime($end);
            }

            $diff = $start_date->diff($end_date);
            return $diff->y + ($diff->m / 12);

        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Extract credentials from CV
     */
    private function extract_credentials($text) {
        $credentials_data = $this->knowledge_base->get_credentials();
        $found = [];

        foreach ($credentials_data as $key => $cred) {
            foreach ($cred['patterns'] as $pattern) {
                $regex_pattern = '/\b' . preg_quote($pattern, '/') . '\b/i';

                if (preg_match($regex_pattern, $text, $matches)) {
                    // Check for "in progress" or "candidate"
                    $context = $this->get_context_around($text, $matches[0], 50);
                    $in_progress = preg_match('/candidate|level\s*[123]|in\s*progress|pursuing|expected/i', $context);

                    $found[$key] = [
                        'credential' => $cred['canonical'],
                        'full_name' => $cred['full_name'],
                        'category' => $cred['category'],
                        'in_progress' => $in_progress,
                        'matched_text' => $matches[0],
                    ];
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * Get context around a match
     */
    private function get_context_around($text, $match, $chars = 100) {
        $pos = stripos($text, $match);
        if ($pos === false) return '';

        $start = max(0, $pos - $chars);
        $length = strlen($match) + ($chars * 2);

        return substr($text, $start, $length);
    }

    /**
     * Extract education details
     */
    private function extract_education($text) {
        $education = [];

        // Target schools
        $target_schools = [
            'Harvard', 'Stanford', 'Wharton', 'LBS', 'London Business School',
            'INSEAD', 'Columbia', 'Booth', 'Kellogg', 'MIT', 'Yale', 'Princeton',
            'Oxford', 'Cambridge', 'HEC', 'Chicago', 'NYU Stern', 'UCLA Anderson',
            'Duke Fuqua', 'Berkeley Haas', 'Michigan Ross', 'Northwestern',
            'IIM', 'ISB', 'CEIBS', 'HKUST',
        ];

        foreach ($target_schools as $school) {
            if (stripos($text, $school) !== false) {
                $context = $this->get_context_around($text, $school, 100);

                // Try to find degree and year
                $degree = '';
                if (preg_match('/(?:MBA|M\.?B\.?A|Master|Bachelor|BSc|BA|BS|MS|MA|PhD)/i', $context, $deg_match)) {
                    $degree = $deg_match[0];
                }

                $year = '';
                if (preg_match('/20\d{2}|19\d{2}/', $context, $year_match)) {
                    $year = $year_match[0];
                }

                $education[] = [
                    'school' => $school,
                    'degree' => $degree,
                    'year' => $year,
                    'is_target_school' => true,
                ];
            }
        }

        // Also find any MBA mentions
        if (empty($education) && preg_match('/MBA|M\.?B\.?A/i', $text)) {
            if (preg_match('/MBA[^,\n]{0,50}(?:from|at|,)\s*([A-Z][A-Za-z\s]{5,40})/i', $text, $matches)) {
                $education[] = [
                    'school' => trim($matches[1]),
                    'degree' => 'MBA',
                    'year' => '',
                    'is_target_school' => false,
                ];
            }
        }

        return $education;
    }

    /**
     * Extract skills mentioned
     */
    private function extract_skills($text) {
        $skills = [];

        // Common skills section patterns
        if (preg_match('/(?:skills|expertise|competencies)[:\s]+([^\n]{20,300})/i', $text, $matches)) {
            $skills_text = $matches[1];
            // Split by common delimiters
            $skill_list = preg_split('/[,;|•·]\s*/', $skills_text);

            foreach ($skill_list as $skill) {
                $skill = trim($skill);
                if (strlen($skill) > 2 && strlen($skill) < 50) {
                    $skills[] = $skill;
                }
            }
        }

        return $skills;
    }

    /**
     * Extract keywords from CV (matching JD keyword patterns)
     */
    private function extract_keywords($text) {
        $synonyms_data = $this->knowledge_base->get_synonyms();
        $keywords = [];

        foreach ($synonyms_data as $key => $data) {
            $canonical = $data['canonical'];
            $count = 0;

            // Count canonical term
            $count += $this->count_term_occurrences($text, strtolower($canonical));

            // Count variations
            foreach ($data['variations'] as $variation) {
                $count += $this->count_term_occurrences($text, strtolower($variation));
            }

            if ($count > 0) {
                $keywords[$key] = [
                    'canonical' => $canonical,
                    'count' => $count,
                ];
            }
        }

        return $keywords;
    }

    /**
     * Infer likely role family from current role and CV language.
     */
    private function extract_role_family_guess($text, $current_role_text = '') {
        $taxonomy = $this->knowledge_base->get_role_taxonomy();
        $families = (array) ($taxonomy['role_families'] ?? []);
        $best_key = '';
        $best_score = 0;
        $best_matches = [];

        foreach ($families as $key => $family) {
            $score = 0;
            $matches = [];

            foreach ((array) ($family['title_signals'] ?? []) as $signal) {
                $signal_text = strtolower((string) $signal);
                $count = $this->count_term_occurrences($current_role_text, $signal_text);
                $count += $this->count_term_occurrences($text, $signal_text);
                if ($count > 0) {
                    $score += $count * 6;
                    $matches[] = (string) $signal;
                }
            }

            foreach ((array) ($family['keyword_signals'] ?? []) as $signal) {
                $count = $this->count_term_occurrences($text, strtolower((string) $signal));
                if ($count > 0) {
                    $score += $count * 2;
                    $matches[] = (string) $signal;
                }
            }

            if ($score > $best_score) {
                $best_score = $score;
                $best_key = (string) $key;
                $best_matches = array_values(array_unique($matches));
            }
        }

        return [
            'key' => $best_key,
            'label' => (string) (($families[$best_key]['label'] ?? 'General Finance')),
            'confidence' => max(0, min(100, (int) round($best_score * 4))),
            'matched_signals' => array_slice($best_matches, 0, 8),
        ];
    }

    /**
     * Extract explicit and adjacent capability evidence from the CV.
     */
    private function extract_capabilities($text, $role_family_key = '') {
        $taxonomy = $this->knowledge_base->get_role_taxonomy();
        $capabilities = (array) ($taxonomy['capabilities'] ?? []);
        $family_weights = (array) (($taxonomy['role_families'][$role_family_key]['capability_weights'] ?? []));
        $results = [];

        foreach ($capabilities as $key => $capability) {
            $matched_terms = [];
            $adjacent_terms = [];
            $direct_count = 0;
            $adjacent_count = 0;

            foreach ((array) ($capability['signals'] ?? []) as $signal) {
                $count = $this->count_term_occurrences($text, strtolower((string) $signal));
                if ($count > 0) {
                    $direct_count += $count;
                    $matched_terms[] = (string) $signal;
                }
            }

            foreach ((array) ($capability['adjacent_signals'] ?? []) as $signal) {
                $count = $this->count_term_occurrences($text, strtolower((string) $signal));
                if ($count > 0) {
                    $adjacent_count += $count;
                    $adjacent_terms[] = (string) $signal;
                }
            }

            $weight = (float) ($family_weights[$key] ?? 1.0);
            $score = (int) round(min(100, (($direct_count * 18) + ($adjacent_count * 7)) * $weight));
            if ($score <= 0) {
                continue;
            }

            $results[$key] = [
                'label' => (string) ($capability['label'] ?? $key),
                'score' => $score,
                'direct_terms' => array_slice(array_values(array_unique($matched_terms)), 0, 8),
                'adjacent_terms' => array_slice(array_values(array_unique($adjacent_terms)), 0, 8),
                'evidence_level' => $direct_count > 0 ? 'explicit' : 'adjacent',
                'direct_count' => $direct_count,
                'adjacent_count' => $adjacent_count,
            ];
        }

        uasort($results, function ($a, $b) {
            return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
        });

        return $results;
    }

    /**
     * Extract explicit tools/platforms mentioned in the CV.
     */
    private function extract_tools($text) {
        $taxonomy = $this->knowledge_base->get_role_taxonomy();
        $tool_capability = (array) (($taxonomy['capabilities']['market_data_tools']['signals'] ?? []));
        $tools = [];

        foreach ($tool_capability as $tool) {
            $count = $this->count_term_occurrences($text, strtolower((string) $tool));
            if ($count > 0) {
                $tools[] = [
                    'name' => (string) $tool,
                    'count' => $count,
                ];
            }
        }

        return $tools;
    }

    /**
     * Extract language signals from the CV.
     */
    private function extract_languages($text) {
        $taxonomy = $this->knowledge_base->get_role_taxonomy();
        $languages = [];

        foreach ((array) ($taxonomy['languages'] ?? []) as $key => $signals) {
            $matched = false;
            foreach ((array) $signals as $signal) {
                if ($this->count_term_occurrences($text, strtolower((string) $signal)) > 0) {
                    $matched = true;
                    break;
                }
            }

            if ($matched) {
                $proficiency = 'mentioned';
                if (preg_match('/(?:native|fluent|near-native)[^.]{0,40}' . preg_quote((string) $key, '/') . '/i', $text)) {
                    $proficiency = 'strong';
                }

                $languages[$key] = [
                    'language' => ucfirst((string) $key),
                    'proficiency' => $proficiency,
                ];
            }
        }

        return $languages;
    }

    /**
     * Count term occurrences with word-boundary-safe matching.
     */
    private function count_term_occurrences($text, $term) {
        $term = trim((string) $term);
        if ($term === '' || $text === '') {
            return 0;
        }

        $pattern = '/(?<![a-z0-9])' . preg_quote($term, '/') . '(?![a-z0-9])/i';
        preg_match_all($pattern, (string) $text, $matches);
        return count($matches[0]);
    }

    /**
     * Extract sectors from CV
     */
    private function extract_sectors($text) {
        $industries_data = $this->knowledge_base->get_industries();
        $found = [];

        foreach ($industries_data as $key => $industry) {
            $mentioned = false;
            $sub_sectors = [];

            // Check canonical name
            if (stripos($text, strtolower($industry['canonical'])) !== false) {
                $mentioned = true;
            }

            // Check sub-sectors
            foreach ($industry['sub_sectors'] as $sub) {
                if (stripos($text, strtolower($sub)) !== false) {
                    $mentioned = true;
                    $sub_sectors[] = $sub;
                }
            }

            // Check key terms
            foreach ($industry['key_terms'] as $term) {
                if (stripos($text, strtolower($term)) !== false) {
                    $mentioned = true;
                }
            }

            if ($mentioned) {
                $found[$key] = [
                    'name' => $industry['canonical'],
                    'sub_sectors' => $sub_sectors,
                ];
            }
        }

        return $found;
    }

    /**
     * Analyze bullet points for quality
     */
    private function analyze_bullets($text) {
        $analysis = [
            'total_bullets' => 0,
            'strong_bullets' => 0,
            'weak_bullets' => 0,
            'quantified_bullets' => 0,
            'outcome_bullets' => 0,
            'quantified_outcome_bullets' => 0,
            'scope_only_bullets' => 0,
            'weak_examples' => [],
            'strong_examples' => [],
            'outcome_examples' => [],
            'scope_only_examples' => [],
        ];

        // Find bullets
        preg_match_all('/[-•*]\s*([^\n]{20,300})/i', $text, $matches);

        if (empty($matches[1])) {
            return $analysis;
        }

        $analysis['total_bullets'] = count($matches[1]);

        foreach ($matches[1] as $bullet) {
            $bullet = trim($bullet);
            $is_strong = false;
            $is_quantified = false;
            $has_outcome = false;
            $has_weak_verb = false;

            // Check for strong verbs
            foreach ($this->strong_verbs as $verb) {
                if (preg_match('/\b' . $verb . '/i', $bullet)) {
                    $is_strong = true;
                    break;
                }
            }

            // Check for weak verbs
            foreach ($this->weak_verbs as $verb) {
                if (preg_match('/\b' . $verb . '/i', $bullet)) {
                    $has_weak_verb = true;
                    break;
                }
            }

            // Check for quantification
            if (preg_match('/\$[\d,]+|\d+%|\d+x|\d+\s*(?:million|billion|deals|transactions|clients)/i', $bullet)) {
                $is_quantified = true;
                $analysis['quantified_bullets']++;
            }

            if ($this->has_measurable_outcome($bullet)) {
                $has_outcome = true;
                $analysis['outcome_bullets']++;
                if (count($analysis['outcome_examples']) < 3) {
                    $analysis['outcome_examples'][] = $bullet;
                }
            }

            if ($is_quantified && $has_outcome) {
                $analysis['quantified_outcome_bullets']++;
            } elseif ($is_quantified) {
                $analysis['scope_only_bullets']++;
                if (count($analysis['scope_only_examples']) < 3) {
                    $analysis['scope_only_examples'][] = $bullet;
                }
            }

            if ($is_strong && !$has_weak_verb) {
                $analysis['strong_bullets']++;
                if (count($analysis['strong_examples']) < 3) {
                    $analysis['strong_examples'][] = $bullet;
                }
            } else if ($has_weak_verb) {
                $analysis['weak_bullets']++;
                if (count($analysis['weak_examples']) < 3) {
                    $analysis['weak_examples'][] = [
                        'bullet' => $bullet,
                        'issue' => $this->identify_bullet_issue($bullet),
                    ];
                }
            }
        }

        return $analysis;
    }

    /**
     * Identify issue with a weak bullet
     */
    private function identify_bullet_issue($bullet) {
        $issues = [];

        // Check for weak verbs
        foreach ($this->weak_verbs as $verb) {
            if (preg_match('/\b' . $verb . '/i', $bullet)) {
                $issues[] = "Uses passive verb '{$verb}' - use active verbs like 'led', 'drove', 'delivered'";
                break;
            }
        }

        // Check for lack of quantification
        if (!preg_match('/\$[\d,]+|\d+%|\d+x|\d+\s*(?:million|billion|deals)/i', $bullet)) {
            $issues[] = 'No quantified impact - add numbers, percentages, or deal values';
        }

        if (!$this->has_measurable_outcome($bullet)) {
            $issues[] = 'Describes activity, not outcome - show what changed, improved, or was delivered';
        }

        if (preg_match('/\$[\d,]+|\d+%|\d+x|\d+\s*(?:million|billion|deals|transactions|clients)/i', $bullet) && !$this->has_measurable_outcome($bullet)) {
            $issues[] = 'Numbers are present, but the result is still unclear';
        }

        // Check for vague language
        if (preg_match('/various|several|multiple|some|many/i', $bullet)) {
            $issues[] = 'Vague quantity - specify exact numbers';
        }

        return implode('; ', $issues);
    }

    private function has_measurable_outcome($bullet) {
        $bullet = trim((string) $bullet);
        if ($bullet === '') {
            return false;
        }

        $outcome_patterns = [
            '/\b(result(?:ed|ing)?\s+in|led\s+to|contribut(?:ed|ing)\s+to|driving|delivering)\b/i',
            '/\b(increas(?:ed|ing)|reduc(?:ed|ing)|improv(?:ed|ing)|grew|grown|boost(?:ed|ing)|cut|lower(?:ed|ing)|sav(?:ed|ing)|generat(?:ed|ing)|achiev(?:ed|ing)|delivered|accelerat(?:ed|ing)|optimiz(?:ed|ing)|secured|closed)\b/i',
            '/\b(improvement|efficiency|accuracy|revenue|returns?|irr|ebitda|margin|cost savings?|turnaround time|throughput|conversion|yield|aum|valuation)\b/i',
        ];

        foreach ($outcome_patterns as $pattern) {
            if (preg_match($pattern, $bullet)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract quantified achievements
     */
    private function extract_quantified($text) {
        $achievements = [];

        // Dollar amounts
        preg_match_all('/\$[\d,]+(?:\s*(?:million|billion|M|B|MM|mn))?/i', $text, $dollar_matches);
        foreach ($dollar_matches[0] as $match) {
            $achievements[] = [
                'type' => 'dollar_amount',
                'value' => $match,
            ];
        }

        // Percentages
        preg_match_all('/\d+(?:\.\d+)?%/', $text, $pct_matches);
        foreach ($pct_matches[0] as $match) {
            $achievements[] = [
                'type' => 'percentage',
                'value' => $match,
            ];
        }

        // Multiples
        preg_match_all('/\d+(?:\.\d+)?x\s*(?:return|multiple|growth|increase)?/i', $text, $mult_matches);
        foreach ($mult_matches[0] as $match) {
            $achievements[] = [
                'type' => 'multiple',
                'value' => $match,
            ];
        }

        // Deal counts
        preg_match_all('/(\d+)\s*(?:deals?|transactions?|acquisitions?|investments?)/i', $text, $deal_matches);
        foreach ($deal_matches[0] as $match) {
            $achievements[] = [
                'type' => 'deal_count',
                'value' => $match,
            ];
        }

        return $achievements;
    }

    /**
     * Extract current role information
     */
    private function extract_current_role($text) {
        $experience = $this->extract_experience($text);

        foreach ($experience as $entry) {
            if ($entry['is_current']) {
                return [
                    'company' => $entry['company'],
                    'role' => $entry['role'],
                    'years_in_role' => $entry['years'],
                ];
            }
        }

        // Fallback: first entry is likely current
        if (!empty($experience)) {
            return [
                'company' => $experience[0]['company'],
                'role' => $experience[0]['role'],
                'years_in_role' => $experience[0]['years'],
            ];
        }

        return [
            'company' => '',
            'role' => '',
            'years_in_role' => 0,
        ];
    }

    /**
     * Get a summary of parsed CV data
     */
    public function get_summary($parsed_data) {
        $summary = [];

        if (!empty($parsed_data['contact']['location'])) {
            $summary['location'] = $parsed_data['contact']['location'];
        }

        if ($parsed_data['total_years'] > 0) {
            $summary['total_experience'] = $parsed_data['total_years'] . ' years';
        }

        if (!empty($parsed_data['current_role']['role'])) {
            $summary['current_role'] = $parsed_data['current_role']['role'];
            if (!empty($parsed_data['current_role']['company'])) {
                $summary['current_role'] .= ' at ' . $parsed_data['current_role']['company'];
            }
        }

        if (!empty($parsed_data['credentials'])) {
            $creds = array_map(function ($c) {
                return $c['credential'] . ($c['in_progress'] ? ' (In Progress)' : '');
            }, $parsed_data['credentials']);
            $summary['credentials'] = implode(', ', $creds);
        }

        if (!empty($parsed_data['education'])) {
            $edu = array_map(function ($e) {
                return ($e['degree'] ? $e['degree'] . ' - ' : '') . $e['school'];
            }, $parsed_data['education']);
            $summary['education'] = implode('; ', $edu);
        }

        if (!empty($parsed_data['sectors'])) {
            $sectors = array_map(function ($s) {
                return $s['name'];
            }, $parsed_data['sectors']);
            $summary['sectors'] = implode(', ', $sectors);
        }

        if (!empty($parsed_data['role_family_guess']['label'])) {
            $summary['role_family'] = (string) $parsed_data['role_family_guess']['label'];
        }

        if (!empty($parsed_data['capabilities'])) {
            $summary['capabilities'] = implode(', ', array_slice(array_map(function ($item) {
                return (string) ($item['label'] ?? '');
            }, array_values((array) $parsed_data['capabilities'])), 0, 5));
        }

        $summary['bullet_quality'] = [
            'total' => $parsed_data['bullet_analysis']['total_bullets'],
            'strong' => $parsed_data['bullet_analysis']['strong_bullets'],
            'weak' => $parsed_data['bullet_analysis']['weak_bullets'],
            'quantified' => $parsed_data['bullet_analysis']['quantified_bullets'],
        ];

        $summary['top_keywords'] = array_slice(array_keys($parsed_data['keywords']), 0, 10);

        return $summary;
    }
}
