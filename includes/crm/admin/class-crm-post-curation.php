<?php
/**
 * CRM Post Curation
 * Advanced post management and extraction
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Post_Curation {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Extract structured data from post content
     */
    public function extract_post_data($content, $additional_context = []) {
        $data = [
            'role_title' => '',
            'company' => '',
            'location' => '',
            'location_city' => '',
            'location_country' => '',
            'salary_min' => null,
            'salary_max' => null,
            'salary_currency' => 'USD',
            'salary_text' => '',
            'seniority' => '',
            'sector' => '',
            'experience_years' => '',
            'requirements' => [],
            'skills_mentioned' => [],
            'is_remote' => false,
            'is_hybrid' => false,
        ];

        // Clean content
        $content = strip_tags($content);
        $content_lower = strtolower($content);

        // Extract role title (usually first line or after "hiring" / "looking for")
        $data['role_title'] = $this->extract_role_title($content);

        // Extract location
        $location = $this->extract_location($content);
        if ($location) {
            $data['location'] = $location['full'];
            $data['location_city'] = $location['city'];
            $data['location_country'] = $location['country'];
        }

        // Extract salary
        $salary = $this->extract_salary($content);
        if ($salary) {
            $data['salary_min'] = $salary['min'];
            $data['salary_max'] = $salary['max'];
            $data['salary_currency'] = $salary['currency'];
            $data['salary_text'] = $salary['text'];
        }

        // Extract seniority
        $data['seniority'] = $this->extract_seniority($content, $data['role_title']);

        // Extract sector
        $data['sector'] = $this->extract_sector($content_lower);

        // Extract experience years
        $data['experience_years'] = $this->extract_experience($content);

        // Check remote/hybrid
        $data['is_remote'] = preg_match('/\b(remote|work from home|wfh)\b/i', $content) === 1;
        $data['is_hybrid'] = preg_match('/\bhybrid\b/i', $content) === 1;

        // Extract skills
        $data['skills_mentioned'] = $this->extract_skills($content);

        // Merge with additional context
        foreach ($additional_context as $key => $value) {
            if (!empty($value) && isset($data[$key])) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Extract role title from content
     */
    private function extract_role_title($content) {
        $lines = explode("\n", trim($content));

        // Common patterns
        $patterns = [
            '/(?:hiring|looking for|seeking|recruiting)\s+(?:a\s+)?([^\.]+)/i',
            '/(?:role|position|opportunity):\s*(.+)/i',
            '/^([A-Z][^\.]{10,60})$/m', // Capitalized line that looks like a title
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $title = trim($matches[1]);
                // Clean up common prefixes/suffixes
                $title = preg_replace('/^(a|an|the)\s+/i', '', $title);
                $title = preg_replace('/\s+(role|position|opportunity)$/i', '', $title);

                if (strlen($title) > 5 && strlen($title) < 100) {
                    return $title;
                }
            }
        }

        // Fallback: first non-empty line that looks like a title
        foreach ($lines as $line) {
            $line = trim($line);
            if (strlen($line) > 10 && strlen($line) < 80 && !preg_match('/^(hi|hello|we|our|i\'m|looking)/i', $line)) {
                return $line;
            }
        }

        return '';
    }

    /**
     * Extract location from content
     */
    private function extract_location($content) {
        // Known cities/countries
        $cities = [
            'London' => 'UK',
            'Dubai' => 'UAE',
            'Abu Dhabi' => 'UAE',
            'New York' => 'USA',
            'NYC' => 'USA',
            'Singapore' => 'Singapore',
            'Hong Kong' => 'Hong Kong',
            'Frankfurt' => 'Germany',
            'Paris' => 'France',
            'Zurich' => 'Switzerland',
            'Geneva' => 'Switzerland',
            'Luxembourg' => 'Luxembourg',
            'Amsterdam' => 'Netherlands',
            'Munich' => 'Germany',
            'Milan' => 'Italy',
            'Madrid' => 'Spain',
            'Dublin' => 'Ireland',
            'Sydney' => 'Australia',
            'Melbourne' => 'Australia',
            'Toronto' => 'Canada',
            'Chicago' => 'USA',
            'Los Angeles' => 'USA',
            'San Francisco' => 'USA',
            'Boston' => 'USA',
            'Miami' => 'USA',
            'Riyadh' => 'Saudi Arabia',
            'Jeddah' => 'Saudi Arabia',
            'Doha' => 'Qatar',
            'Bahrain' => 'Bahrain',
            'Kuwait' => 'Kuwait',
            'Mumbai' => 'India',
            'Delhi' => 'India',
            'Bangalore' => 'India',
        ];

        foreach ($cities as $city => $country) {
            if (stripos($content, $city) !== false) {
                return [
                    'city' => $city,
                    'country' => $country,
                    'full' => $city . ', ' . $country,
                ];
            }
        }

        // Try to match "Location: X" pattern
        if (preg_match('/location[:\s]+([^,\n]+(?:,\s*[^,\n]+)?)/i', $content, $matches)) {
            $location = trim($matches[1]);
            $parts = array_map('trim', explode(',', $location));

            return [
                'city' => $parts[0] ?? '',
                'country' => $parts[1] ?? '',
                'full' => $location,
            ];
        }

        return null;
    }

    /**
     * Extract salary from content
     */
    private function extract_salary($content) {
        // Currency patterns
        $currencies = [
            'USD' => ['$', 'USD', 'US$'],
            'GBP' => ['£', 'GBP'],
            'EUR' => ['€', 'EUR'],
            'AED' => ['AED', 'Dhs', 'Dirhams'],
            'SGD' => ['SGD', 'S$'],
            'HKD' => ['HKD', 'HK$'],
            'CHF' => ['CHF'],
            'SAR' => ['SAR'],
        ];

        // Common salary patterns
        $patterns = [
            // Range: $100k-$150k, £100,000 - £150,000
            '/([£$€]|AED|GBP|USD|EUR|SGD|CHF|SAR)\s*([\d,]+)[kK]?\s*[-–to]+\s*[£$€]?\s*([\d,]+)[kK]?/i',
            // Single value: $150k, £150,000
            '/([£$€]|AED|GBP|USD|EUR|SGD|CHF|SAR)\s*([\d,]+)[kK]?(?:\s*(?:per\s+)?(?:annum|pa|year|annual))?/i',
            // Text-based: "salary: 100-150k"
            '/salary[:\s]+([\d,]+)[kK]?\s*[-–to]+\s*([\d,]+)[kK]?/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $currency = 'USD';

                // Detect currency
                foreach ($currencies as $code => $symbols) {
                    foreach ($symbols as $symbol) {
                        if (stripos($matches[0], $symbol) !== false) {
                            $currency = $code;
                            break 2;
                        }
                    }
                }

                // Parse values
                $value1 = (int) str_replace(',', '', $matches[2] ?? $matches[1]);
                $value2 = isset($matches[3]) ? (int) str_replace(',', '', $matches[3]) : null;

                // Handle 'k' notation
                if ($value1 < 1000 && stripos($matches[0], 'k') !== false) {
                    $value1 *= 1000;
                    if ($value2) $value2 *= 1000;
                }

                return [
                    'min' => $value1,
                    'max' => $value2 ?: $value1,
                    'currency' => $currency,
                    'text' => trim($matches[0]),
                ];
            }
        }

        return null;
    }

    /**
     * Extract seniority level
     */
    private function extract_seniority($content, $role_title = '') {
        $title = $this->normalize_seniority_text($role_title);
        if ($title === '') {
            $lines = preg_split('/\r\n|\r|\n/', wp_strip_all_tags((string) $content));
            $title = $this->normalize_seniority_text((string) ($lines[0] ?? ''));
        }

        $text = $this->normalize_seniority_text($content);
        $title_rules = [
            'intern' => '/\b(intern(ship)?|off cycle|offcycle|summer analyst|summer intern|placement|trainee|management trainee|graduate trainee|graduate(?:\s+[a-z0-9]+){0,4}\s+(programme|program)|campus|emirati[sz]ation graduate|emiritisation graduate|emiratisation programme|emiratization program)\b/',
            'board' => '/\b(board member|board director|chair(man|woman)|non executive director|non-executive director|independent director)\b/',
            'c_level' => '/\b(c suite|c-suite|head of function|chief\s+(executive|financial|operating|investment|risk|technology|information|marketing|people|commercial|strategy|compliance|legal)\s+officer|chief\s+[a-z]+(?:\s+[a-z]+)?\s+officer|ceo|cfo|coo|cio|cto|cmo|cro|chro|cpo|ciso)\b/',
            'partner' => '/\b(managing partner|founding partner|general partner)\b|(?<!business\s)(?<!customer\s)(?<!success\s)\bpartner\b(?!\s+(manager|success|operations|sales|marketing|finance|account|channel|relationship|solutions))/',
            'md' => '/\b(managing director|general manager)\b|\bmd\b/',
            'senior_vp' => '/\b(executive vice president|senior vice president|svp|evp)\b/',
            'vp' => '/\b(assistant vice president|associate vice president|vice president|principal|avp|vp)\b/',
            'director' => '/\b(executive director|senior director|director|associate director|regional head|country head|global head|head of|chief of staff)\b/',
            'senior_associate' => '/\b(senior associate|senior relationship manager|senior manager|lead manager|senior consultant)\b/',
            'senior_analyst' => '/\b(senior analyst|sr analyst|sr\. analyst|lead analyst|senior officer|senior specialist)\b/',
            'analyst' => '/\b(analyst|junior analyst|investment analyst|research analyst|data analyst|finance analyst|assistant relationship manager|junior relationship manager|assistant manager|junior manager|relationship officer|officer|specialist|coordinator|graduate|entry level|entry-level)\b/',
            'associate' => '/\b(associate|relationship manager|investment manager|portfolio manager|finance manager|trade finance manager|operations manager|product manager|project manager|programme manager|program manager|manager|consultant)\b/',
        ];

        foreach ($title_rules as $level => $pattern) {
            if ($title !== '' && preg_match($pattern, $title)) {
                return $level;
            }
        }

        if (preg_match('/\b(?:minimum of|at least|minimum|required|requires|requirement)\s+(\d{1,2})\+?\s+years?\b/', $text, $matches)
            || preg_match('/\b(\d{1,2})\+?\s+years?\s+(?:of\s+)?(?:relevant\s+)?experience\b/', $text, $matches)
            || preg_match('/\b(\d{1,2})\s*(?:-|to)\s*(\d{1,2})\s+years?\b/', $text, $matches)
        ) {
            $years = (int) $matches[1];
            if ($years <= 2) {
                return 'analyst';
            }
            if ($years <= 4) {
                return 'senior_analyst';
            }
            if ($years <= 6) {
                return 'associate';
            }
            if ($years <= 8) {
                return 'senior_associate';
            }
            if ($years <= 10) {
                return 'vp';
            }
            if ($years <= 12) {
                return 'senior_vp';
            }
            return 'director';
        }

        return 'other';
    }

    private function normalize_seniority_text($value) {
        $value = html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES, 'UTF-8');
        $value = strtolower(str_replace(['–', '—', '/', '&'], ['-', '-', ' ', ' and '], $value));
        $value = preg_replace('/[^a-z0-9\+\.\-\s]/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim((string) $value);
    }

    /**
     * Extract sector
     */
    private function extract_sector($content_lower) {
        $sector_map = [
            'pe' => ['private equity', 'pe ', ' pe,', 'buyout', 'lbo', 'growth equity'],
            'vc' => ['venture capital', ' vc ', 'vc,', 'venture'],
            'hedge_fund' => ['hedge fund', 'hedgefund', 'hf ', 'asset management', 'quant fund'],
            'ib' => ['investment bank', ' ib ', 'ib,', 'm&a', 'capital markets', 'ecm', 'dcm', 'leveraged finance'],
            'corporate' => ['corporate', 'fp&a', 'treasury', 'corporate finance', 'strategic finance'],
            'consulting' => ['consulting', 'advisory', 'strategy'],
            'real_estate' => ['real estate', 'property', 'reit'],
            'infrastructure' => ['infrastructure', 'infra'],
            'credit' => ['credit', 'distressed', 'special situations', 'direct lending'],
        ];

        foreach ($sector_map as $sector => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($content_lower, $keyword) !== false) {
                    return $sector;
                }
            }
        }

        return 'other';
    }

    /**
     * Extract experience years
     */
    private function extract_experience($content) {
        $patterns = [
            '/(\d+)\+?\s*(?:years?|yrs?)(?:\s+(?:of\s+)?experience)?/i',
            '/experience[:\s]+(\d+)\+?\s*(?:years?|yrs?)/i',
            '/(\d+)\s*-\s*(\d+)\s*(?:years?|yrs?)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                if (isset($matches[2])) {
                    return $matches[1] . '-' . $matches[2] . ' years';
                }
                return $matches[1] . '+ years';
            }
        }

        return '';
    }

    /**
     * Extract mentioned skills
     */
    private function extract_skills($content) {
        $skills = [];
        $content_lower = strtolower($content);

        $skill_keywords = [
            // Technical
            'excel', 'powerpoint', 'financial modeling', 'valuation', 'lbo', 'dcf',
            'python', 'sql', 'vba', 'power bi', 'tableau',
            // Finance specific
            'm&a', 'due diligence', 'deal execution', 'portfolio management',
            'fundraising', 'investor relations', 'capital raising',
            // Soft skills
            'leadership', 'communication', 'presentation', 'stakeholder management',
            // Qualifications
            'cfa', 'mba', 'aca', 'acca', 'cpa',
        ];

        foreach ($skill_keywords as $skill) {
            if (strpos($content_lower, strtolower($skill)) !== false) {
                $skills[] = $skill;
            }
        }

        return array_unique($skills);
    }

    /**
     * Parse LinkedIn post format
     */
    public function parse_linkedin_post($content, $recruiter_data = []) {
        $extracted = $this->extract_post_data($content);

        // Create content snippet
        $snippet = wp_trim_words(strip_tags($content), 40, '...');

        return [
            'content' => $content,
            'content_snippet' => $snippet,
            'role_title' => $extracted['role_title'],
            'company' => $extracted['company'],
            'location' => $extracted['location'],
            'location_city' => $extracted['location_city'],
            'location_country' => $extracted['location_country'],
            'salary_min' => $extracted['salary_min'],
            'salary_max' => $extracted['salary_max'],
            'salary_currency' => $extracted['salary_currency'],
            'salary_text' => $extracted['salary_text'],
            'seniority' => $extracted['seniority'],
            'sector' => $extracted['sector'],
            'experience_years' => $extracted['experience_years'],
            'requirements' => json_encode($extracted['requirements']),
            'skills_mentioned' => json_encode($extracted['skills_mentioned']),
            'is_remote' => $extracted['is_remote'] ? 1 : 0,
            'is_hybrid' => $extracted['is_hybrid'] ? 1 : 0,
            'source' => 'linkedin',
        ];
    }
}

// Initialize
SFFC_CRM_Post_Curation::get_instance();
