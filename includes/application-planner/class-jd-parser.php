<?php
/**
 * Job Description Parser
 *
 * Extracts structured data from job descriptions including:
 * - Years of experience required
 * - Required/preferred credentials
 * - Location requirements
 * - Sector/industry focus
 * - Seniority level
 * - Keywords and their frequency
 *
 * @package SennaCareers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_JD_Parser {

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
     * Parse job description text
     *
     * @param string $jd_text Raw job description text
     * @return array Parsed JD data
     */
    public function parse($jd_text) {
        $jd_text = $this->clean_text($jd_text);
        $jd_lower = strtolower($jd_text);
        $role_title = $this->extract_role_title($jd_text);
        $role_family = $this->extract_role_family($jd_lower, strtolower($role_title));

        return [
            'raw_text' => $jd_text,
            'role_title' => $role_title,
            'company' => $this->extract_company($jd_text),
            'experience' => $this->extract_experience($jd_lower),
            'credentials' => $this->extract_credentials($jd_text),
            'location' => $this->extract_location($jd_text),
            'sectors' => $this->extract_sectors($jd_lower),
            'seniority' => $this->extract_seniority($jd_lower),
            'role_family' => $role_family,
            'capabilities' => $this->extract_capabilities($jd_lower, $role_family['key']),
            'tools' => $this->extract_tools($jd_lower),
            'languages' => $this->extract_languages($jd_lower),
            'keywords' => $this->extract_keywords($jd_lower),
            'requirements' => $this->categorize_requirements($jd_text),
            'remote_policy' => $this->extract_remote_policy($jd_lower),
        ];
    }

    /**
     * Clean and normalize text
     */
    private function clean_text($text) {
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        // Remove special unicode characters
        $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
        return trim($text);
    }

    /**
     * Extract role title from JD
     */
    private function extract_role_title($text) {
        // Common patterns for role title
        $patterns = [
            '/(?:position|role|job\s*title|title)\s*[:\-]?\s*([A-Z][^\n\r.]{5,60})/i',
            '/^([A-Z][A-Za-z\s\-,&]+(?:Analyst|Associate|Manager|Director|VP|Partner|Officer|Lead|Senior|Junior|Principal|Head))/m',
            '/hiring\s+(?:a|an)\s+([A-Z][^\n\r.]{5,50})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }

        // Fallback: first line if it looks like a title
        $first_line = strtok($text, "\n");
        if (strlen($first_line) < 80 && strlen($first_line) > 5) {
            return trim($first_line);
        }

        return '';
    }

    /**
     * Extract company name from JD
     */
    private function extract_company($text) {
        $patterns = [
            '/(?:company|firm|organization)\s*[:\-]?\s*([A-Z][A-Za-z0-9\s&\-\.]{2,40})/i',
            '/(?:about|at|join)\s+([A-Z][A-Za-z0-9\s&\-\.]{2,30})\s*[,\.\n]/i',
            '/([A-Z][A-Za-z0-9&\-\.]+)\s+is\s+(?:seeking|hiring|looking)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $company = trim($matches[1]);
                // Filter out common false positives
                $false_positives = ['The', 'This', 'Our', 'We', 'A', 'An'];
                if (!in_array($company, $false_positives)) {
                    return $company;
                }
            }
        }

        return '';
    }

    /**
     * Extract years of experience required
     */
    private function extract_experience($text) {
        $result = [
            'min_years' => null,
            'max_years' => null,
            'preferred_years' => null,
            'raw_matches' => [],
            'is_required' => false,
        ];

        // Pattern for X+ years
        if (preg_match('/(\d+)\+?\s*(?:years?|yrs?)\s*(?:of\s*)?(?:experience|exp)/i', $text, $matches)) {
            $result['min_years'] = intval($matches[1]);
            $result['is_required'] = true;
            $result['raw_matches'][] = $matches[0];
        }

        // Pattern for X-Y years
        if (preg_match('/(\d+)\s*[-–to]+\s*(\d+)\s*(?:years?|yrs?)/i', $text, $matches)) {
            $result['min_years'] = intval($matches[1]);
            $result['max_years'] = intval($matches[2]);
            $result['is_required'] = true;
            $result['raw_matches'][] = $matches[0];
        }

        // Pattern for minimum X years
        if (preg_match('/(?:minimum|at\s*least|min)\s*(\d+)\s*(?:years?|yrs?)/i', $text, $matches)) {
            $result['min_years'] = intval($matches[1]);
            $result['is_required'] = true;
            $result['raw_matches'][] = $matches[0];
        }

        // Check for "required" vs "preferred" context
        if (preg_match('/prefer(?:red|ably)?\s*(\d+)\+?\s*(?:years?|yrs?)/i', $text, $matches)) {
            $result['preferred_years'] = intval($matches[1]);
            $result['raw_matches'][] = $matches[0];
        }

        return $result;
    }

    /**
     * Extract credentials (CFA, MBA, etc.)
     */
    private function extract_credentials($text) {
        $credentials_data = $this->knowledge_base->get_credentials();
        $found = [];

        foreach ($credentials_data as $key => $cred) {
            foreach ($cred['patterns'] as $pattern) {
                // Escape special regex characters in pattern
                $regex_pattern = '/\b' . preg_quote($pattern, '/') . '\b/i';

                if (preg_match($regex_pattern, $text, $matches)) {
                    // Determine if required or preferred
                    $is_required = $this->is_requirement_required($text, $matches[0]);

                    $found[$key] = [
                        'credential' => $cred['canonical'],
                        'full_name' => $cred['full_name'],
                        'category' => $cred['category'],
                        'is_required' => $is_required,
                        'matched_text' => $matches[0],
                    ];
                    break; // Found this credential, move to next
                }
            }
        }

        return $found;
    }

    /**
     * Check if a requirement is required vs preferred
     */
    private function is_requirement_required($text, $match_text) {
        // Find context around the match
        $pos = strpos(strtolower($text), strtolower($match_text));
        if ($pos === false) return true;

        $context_start = max(0, $pos - 100);
        $context = substr($text, $context_start, 200);
        $context_lower = strtolower($context);

        // Check for "required" indicators
        $required_patterns = [
            'required', 'must have', 'essential', 'mandatory', 'need to have'
        ];

        // Check for "preferred" indicators
        $preferred_patterns = [
            'preferred', 'nice to have', 'desirable', 'ideally', 'plus', 'bonus', 'advantageous'
        ];

        foreach ($required_patterns as $pattern) {
            if (strpos($context_lower, $pattern) !== false) {
                return true;
            }
        }

        foreach ($preferred_patterns as $pattern) {
            if (strpos($context_lower, $pattern) !== false) {
                return false;
            }
        }

        // Default to required if no context found
        return true;
    }

    /**
     * Extract location requirements
     */
    private function extract_location($text) {
        $result = [
            'city' => '',
            'country' => '',
            'region' => '',
            'is_required' => false,
            'raw_match' => '',
        ];

        // Common location patterns
        $patterns = [
            // City, Country format
            '/(?:location|based\s*in|located\s*in|office\s*in)\s*[:\-]?\s*([A-Z][a-zA-Z\s]+),\s*([A-Z][a-zA-Z\s]+)/i',
            // Just city
            '/(?:location|based\s*in|located\s*in)\s*[:\-]?\s*([A-Z][a-zA-Z\s]{2,30})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $result['raw_match'] = $matches[0];
                $result['city'] = trim($matches[1]);
                if (isset($matches[2])) {
                    $result['country'] = trim($matches[2]);
                }
                break;
            }
        }

        // Known cities
        $major_cities = [
            'London' => 'UK',
            'New York' => 'USA',
            'Dubai' => 'UAE',
            'Hong Kong' => 'Hong Kong',
            'Singapore' => 'Singapore',
            'San Francisco' => 'USA',
            'Chicago' => 'USA',
            'Boston' => 'USA',
            'Los Angeles' => 'USA',
            'Frankfurt' => 'Germany',
            'Paris' => 'France',
            'Sydney' => 'Australia',
            'Toronto' => 'Canada',
            'Mumbai' => 'India',
        ];

        foreach ($major_cities as $city => $country) {
            if (stripos($text, $city) !== false) {
                $result['city'] = $city;
                $result['country'] = $country;
                break;
            }
        }

        // Check if location is required
        if (preg_match('/must\s*be\s*(?:based|located)\s*in/i', $text)) {
            $result['is_required'] = true;
        }

        return $result;
    }

    /**
     * Extract sectors/industries
     */
    private function extract_sectors($text) {
        $industries_data = $this->knowledge_base->get_industries();
        $found = [];

        foreach ($industries_data as $key => $industry) {
            // Check canonical name
            if (stripos($text, strtolower($industry['canonical'])) !== false) {
                $found[$key] = [
                    'name' => $industry['canonical'],
                    'sub_sectors' => [],
                    'is_focus' => true,
                ];
            }

            // Check sub-sectors
            foreach ($industry['sub_sectors'] as $sub) {
                if (stripos($text, strtolower($sub)) !== false) {
                    if (!isset($found[$key])) {
                        $found[$key] = [
                            'name' => $industry['canonical'],
                            'sub_sectors' => [],
                            'is_focus' => false,
                        ];
                    }
                    $found[$key]['sub_sectors'][] = $sub;
                }
            }

            // Check key terms
            foreach ($industry['key_terms'] as $term) {
                if (stripos($text, strtolower($term)) !== false) {
                    if (!isset($found[$key])) {
                        $found[$key] = [
                            'name' => $industry['canonical'],
                            'sub_sectors' => [],
                            'is_focus' => false,
                        ];
                    }
                }
            }
        }

        return $found;
    }

    /**
     * Extract seniority level
     */
    private function extract_seniority($text) {
        $seniority_data = $this->knowledge_base->get_seniority_levels();

        foreach ($seniority_data as $level => $data) {
            foreach ($data['titles'] as $title) {
                if (stripos($text, strtolower($title)) !== false) {
                    return [
                        'level' => $level,
                        'matched_title' => $title,
                        'years_range' => $data['years_range'],
                        'typical_responsibilities' => $data['typical_responsibilities'],
                    ];
                }
            }
        }

        return [
            'level' => 'unknown',
            'matched_title' => '',
            'years_range' => [0, 99],
            'typical_responsibilities' => [],
        ];
    }

    /**
     * Extract and count keywords
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
                    'is_important' => $count >= 2, // Mentioned multiple times = important
                ];
            }
        }

        // Sort by count descending
        uasort($keywords, function ($a, $b) {
            return $b['count'] - $a['count'];
        });

        return $keywords;
    }

    /**
     * Categorize requirements into must-have vs nice-to-have
     */
    private function categorize_requirements($text) {
        $result = [
            'must_have' => [],
            'nice_to_have' => [],
        ];

        // Split by common section headers
        $sections = preg_split('/(?:requirements?|qualifications?|what\s*we(?:\'re)?\s*looking\s*for)/i', $text);

        if (count($sections) > 1) {
            $req_section = $sections[1];

            // Extract bullet points
            $bullets = preg_split('/[\n\r]+\s*[-•*]\s*/', $req_section);

            foreach ($bullets as $bullet) {
                $bullet = trim($bullet);
                if (strlen($bullet) < 10) continue;

                // Check if it's preferred or required
                if (preg_match('/prefer|nice\s*to\s*have|plus|bonus|ideal/i', $bullet)) {
                    $result['nice_to_have'][] = $bullet;
                } else {
                    $result['must_have'][] = $bullet;
                }
            }
        }

        return $result;
    }

    /**
     * Detect the most likely role family using title and JD language.
     */
    private function extract_role_family($text, $role_title) {
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
                $count = $this->count_term_occurrences($role_title, $signal_text);
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

        if ($best_key === '' && !empty($families)) {
            $best_key = 'private_equity';
        }

        return [
            'key' => $best_key,
            'label' => (string) (($families[$best_key]['label'] ?? 'General Finance')),
            'confidence' => max(0, min(100, (int) round($best_score * 4))),
            'matched_signals' => array_slice($best_matches, 0, 8),
        ];
    }

    /**
     * Extract capability requirements from the JD using taxonomy signals.
     */
    private function extract_capabilities($text, $role_family_key = '') {
        $taxonomy = $this->knowledge_base->get_role_taxonomy();
        $capabilities = (array) ($taxonomy['capabilities'] ?? []);
        $family_weights = (array) (($taxonomy['role_families'][$role_family_key]['capability_weights'] ?? []));
        $results = [];

        foreach ($capabilities as $key => $capability) {
            $matched_terms = [];
            $signal_count = 0;

            foreach ((array) ($capability['signals'] ?? []) as $signal) {
                $count = $this->count_term_occurrences($text, strtolower((string) $signal));
                if ($count > 0) {
                    $signal_count += $count * 2;
                    $matched_terms[] = (string) $signal;
                }
            }

            foreach ((array) ($capability['adjacent_signals'] ?? []) as $signal) {
                $count = $this->count_term_occurrences($text, strtolower((string) $signal));
                if ($count > 0) {
                    $signal_count += $count;
                    $matched_terms[] = (string) $signal;
                }
            }

            $weight = (float) ($family_weights[$key] ?? 1.0);
            $priority_score = (int) round(min(100, $signal_count * 10 * $weight));

            if ($priority_score <= 0 && !isset($family_weights[$key])) {
                continue;
            }

            $is_required = $priority_score >= 40 || $this->text_contains_requirement_context($text, $matched_terms);
            $results[$key] = [
                'label' => (string) ($capability['label'] ?? $key),
                'matched_terms' => array_slice(array_values(array_unique($matched_terms)), 0, 8),
                'priority_score' => $priority_score > 0 ? $priority_score : (int) round($weight * 28),
                'is_required' => $is_required,
                'match_count' => $signal_count,
            ];
        }

        uasort($results, function ($a, $b) {
            return ($b['priority_score'] ?? 0) <=> ($a['priority_score'] ?? 0);
        });

        return $results;
    }

    /**
     * Extract tools/platform signals from the JD.
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
     * Extract language requirements from the JD.
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
                $languages[$key] = [
                    'language' => ucfirst((string) $key),
                    'is_required' => preg_match('/required|must have|fluent|native|near-native/i', $text) === 1,
                ];
            }
        }

        return $languages;
    }

    /**
     * Count term occurrences with safer word boundaries than substr_count.
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
     * Check whether matched terms appear in explicit requirement language.
     */
    private function text_contains_requirement_context($text, $matched_terms) {
        if (empty($matched_terms)) {
            return false;
        }

        foreach ((array) $matched_terms as $term) {
            $pattern = '/(?:required|must have|essential|strong|proven|advanced)[^.]{0,120}' . preg_quote((string) $term, '/') . '/i';
            if (preg_match($pattern, (string) $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract remote work policy
     */
    private function extract_remote_policy($text) {
        if (preg_match('/fully?\s*remote|100%\s*remote|work\s*from\s*anywhere/i', $text)) {
            return 'fully_remote';
        }

        if (preg_match('/hybrid|flexible\s*working|mix\s*of\s*(?:remote|office)/i', $text)) {
            return 'hybrid';
        }

        if (preg_match('/on[\-\s]*site|in[\-\s]*office|office[\-\s]*based/i', $text)) {
            return 'onsite';
        }

        return 'unknown';
    }

    /**
     * Get a summary of extracted data for debugging/display
     */
    public function get_summary($parsed_data) {
        $summary = [];

        if (!empty($parsed_data['role_title'])) {
            $summary['role'] = $parsed_data['role_title'];
        }

        if (!empty($parsed_data['company'])) {
            $summary['company'] = $parsed_data['company'];
        }

        if ($parsed_data['experience']['min_years']) {
            $exp = $parsed_data['experience']['min_years'];
            if ($parsed_data['experience']['max_years']) {
                $exp .= '-' . $parsed_data['experience']['max_years'];
            } else {
                $exp .= '+';
            }
            $summary['experience'] = $exp . ' years';
        }

        if (!empty($parsed_data['credentials'])) {
            $creds = array_map(function ($c) {
                return $c['credential'] . ($c['is_required'] ? ' (Required)' : ' (Preferred)');
            }, $parsed_data['credentials']);
            $summary['credentials'] = implode(', ', $creds);
        }

        if (!empty($parsed_data['location']['city'])) {
            $summary['location'] = $parsed_data['location']['city'];
            if (!empty($parsed_data['location']['country'])) {
                $summary['location'] .= ', ' . $parsed_data['location']['country'];
            }
        }

        if (!empty($parsed_data['sectors'])) {
            $sectors = array_map(function ($s) {
                return $s['name'];
            }, $parsed_data['sectors']);
            $summary['sectors'] = implode(', ', $sectors);
        }

        if ($parsed_data['seniority']['level'] !== 'unknown') {
            $summary['seniority'] = ucfirst($parsed_data['seniority']['level']);
        }

        if (!empty($parsed_data['role_family']['label'])) {
            $summary['role_family'] = (string) $parsed_data['role_family']['label'];
        }

        if (!empty($parsed_data['capabilities'])) {
            $summary['capabilities'] = implode(', ', array_slice(array_map(function ($item) {
                return (string) ($item['label'] ?? '');
            }, array_values((array) $parsed_data['capabilities'])), 0, 5));
        }

        $summary['top_keywords'] = array_slice(array_keys($parsed_data['keywords']), 0, 10);

        return $summary;
    }
}
