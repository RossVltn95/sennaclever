<?php
/**
 * Requirements Extractor
 * Advanced system for extracting job requirements from job descriptions
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Requirements_Extractor {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Finance-specific skills taxonomy
     */
    private $skills_patterns = [
        'Technical' => [
            'Excel' => ['excel', 'ms excel', 'microsoft excel', 'spreadsheet', 'vlookup', 'pivot table'],
            'PowerPoint' => ['powerpoint', 'ppt', 'presentations', 'slide deck'],
            'Python' => ['python', 'pandas', 'numpy', 'matplotlib'],
            'R' => ['\br\b', 'r programming', 'rstudio'],
            'SQL' => ['sql', 'database', 'mysql', 'postgresql', 'oracle'],
            'VBA' => ['vba', 'visual basic', 'macro'],
            'MATLAB' => ['matlab', 'mathematical modeling'],
            'Tableau' => ['tableau', 'data visualization'],
            'Power BI' => ['power bi', 'powerbi', 'microsoft bi'],
            'Bloomberg Terminal' => ['bloomberg', 'terminal', 'bbg'],
            'FactSet' => ['factset', 'fact set'],
            'Reuters Eikon' => ['reuters', 'eikon', 'refinitiv']
        ],
        'Financial Analysis' => [
            'Financial Modeling' => ['financial model', 'dcf', 'discounted cash flow', 'lbo', 'leveraged buyout'],
            'Valuation' => ['valuation', 'comps', 'comparable company', 'precedent transaction'],
            'Financial Statement Analysis' => ['financial statement', '10-k', '10-q', 'balance sheet', 'income statement'],
            'Credit Analysis' => ['credit analysis', 'credit risk', 'default risk'],
            'Risk Management' => ['risk management', 'var', 'value at risk', 'monte carlo'],
            'Portfolio Management' => ['portfolio', 'asset allocation', 'portfolio optimization'],
            'Derivatives' => ['derivatives', 'options', 'futures', 'swaps'],
            'Fixed Income' => ['fixed income', 'bonds', 'credit', 'rates'],
            'Equity Research' => ['equity research', 'stock analysis', 'equity valuation']
        ],
        'Industry Knowledge' => [
            'Investment Banking' => ['investment banking', 'ib', 'm&a', 'mergers', 'acquisitions', 'ipo'],
            'Private Equity' => ['private equity', 'pe', 'buyout', 'growth equity'],
            'Venture Capital' => ['venture capital', 'vc', 'startup', 'early stage'],
            'Asset Management' => ['asset management', 'fund management', 'institutional'],
            'Hedge Funds' => ['hedge fund', 'alternative investment', 'long short'],
            'Commercial Banking' => ['commercial banking', 'corporate banking', 'lending'],
            'Compliance' => ['compliance', 'regulatory', 'aml', 'kyc'],
            'Audit' => ['audit', 'internal audit', 'sox', 'sarbanes oxley']
        ],
        'Certifications' => [
            'CFA' => ['cfa', 'chartered financial analyst'],
            'FRM' => ['frm', 'financial risk manager'],
            'CPA' => ['cpa', 'certified public accountant'],
            'ACCA' => ['acca', 'chartered certified accountant'],
            'CAIA' => ['caia', 'alternative investment analyst'],
            'PRM' => ['prm', 'professional risk manager']
        ],
        'Soft Skills' => [
            'Communication' => ['communication', 'presentation', 'written', 'verbal'],
            'Leadership' => ['leadership', 'team lead', 'management', 'mentor'],
            'Analytical' => ['analytical', 'problem solving', 'critical thinking'],
            'Attention to Detail' => ['attention to detail', 'detail oriented', 'accuracy'],
            'Client Management' => ['client', 'customer', 'stakeholder', 'relationship']
        ]
    ];
    
    /**
     * Experience level patterns
     */
    private $experience_patterns = [
        'entry_level' => ['entry level', 'graduate', 'junior', '0-2 years', '1-3 years'],
        'mid_level' => ['mid level', 'experienced', '3-5 years', '4-7 years', 'senior'],
        'senior_level' => ['senior', 'lead', '5+ years', '7+ years', '10+ years', 'director'],
        'executive' => ['executive', 'vp', 'vice president', 'managing director', 'c-level']
    ];
    
    /**
     * Education patterns
     */
    private $education_patterns = [
        'bachelor' => ['bachelor', 'ba', 'bs', 'undergraduate'],
        'master' => ['master', 'mba', 'ma', 'ms', 'graduate'],
        'phd' => ['phd', 'doctorate', 'doctoral'],
        'professional' => ['cfa', 'cpa', 'frm', 'acca', 'certification']
    ];
    
    public function __construct() {
        // Hook into job save to extract requirements
        add_action('save_post', [$this, 'extract_requirements_on_save'], 20, 3);
    }
    
    /**
     * Extract all requirements from job description
     */
    public function extract_all_requirements($job_description, $job_title = '', $company = '') {
        $text = strtolower($job_description . ' ' . $job_title);
        
        $requirements = [
            'technical_skills' => $this->extract_technical_skills($text),
            'financial_skills' => $this->extract_financial_skills($text),
            'industry_knowledge' => $this->extract_industry_knowledge($text),
            'certifications' => $this->extract_certifications($text),
            'soft_skills' => $this->extract_soft_skills($text),
            'experience_level' => $this->extract_experience_level($text),
            'education_required' => $this->extract_education_requirements($text),
            'must_haves' => $this->identify_must_haves($text),
            'nice_to_haves' => $this->identify_nice_to_haves($text),
            'salary_range' => $this->extract_salary_range($job_description),
            'location_requirements' => $this->extract_location_requirements($text),
            'visa_requirements' => $this->extract_visa_requirements($text),
            'nationality_requirements' => $this->extract_nationality_requirements($text)
        ];
        
        // Calculate requirement weights
        $requirements['weights'] = $this->calculate_requirement_weights($requirements);
        
        return $requirements;
    }
    
    /**
     * Extract technical skills
     */
    public function extract_technical_skills($text) {
        $found_skills = [];
        
        foreach ($this->skills_patterns['Technical'] as $skill => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match('/\b' . preg_quote($pattern, '/') . '\b/i', $text)) {
                    $proficiency = $this->extract_proficiency_level($text, $pattern);
                    $found_skills[] = [
                        'skill' => $skill,
                        'proficiency' => $proficiency,
                        'category' => 'Technical',
                        'required' => $this->is_skill_required($text, $pattern)
                    ];
                    break; // Only add once per skill
                }
            }
        }
        
        return $found_skills;
    }
    
    /**
     * Extract financial analysis skills
     */
    public function extract_financial_skills($text) {
        $found_skills = [];
        
        foreach ($this->skills_patterns['Financial Analysis'] as $skill => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match('/\b' . preg_quote($pattern, '/') . '\b/i', $text)) {
                    $found_skills[] = [
                        'skill' => $skill,
                        'proficiency' => $this->extract_proficiency_level($text, $pattern),
                        'category' => 'Financial Analysis',
                        'required' => $this->is_skill_required($text, $pattern)
                    ];
                    break;
                }
            }
        }
        
        return $found_skills;
    }
    
    /**
     * Extract industry knowledge
     */
    public function extract_industry_knowledge($text) {
        $found_knowledge = [];
        
        foreach ($this->skills_patterns['Industry Knowledge'] as $area => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match('/\b' . preg_quote($pattern, '/') . '\b/i', $text)) {
                    $found_knowledge[] = [
                        'area' => $area,
                        'category' => 'Industry Knowledge',
                        'required' => $this->is_skill_required($text, $pattern)
                    ];
                    break;
                }
            }
        }
        
        return $found_knowledge;
    }
    
    /**
     * Extract certifications
     */
    public function extract_certifications($text) {
        $found_certs = [];
        
        foreach ($this->skills_patterns['Certifications'] as $cert => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match('/\b' . preg_quote($pattern, '/') . '\b/i', $text)) {
                    $level = $this->extract_certification_level($text, $pattern);
                    $found_certs[] = [
                        'certification' => $cert,
                        'level' => $level,
                        'category' => 'Certifications',
                        'required' => $this->is_skill_required($text, $pattern)
                    ];
                    break;
                }
            }
        }
        
        return $found_certs;
    }
    
    /**
     * Extract soft skills
     */
    public function extract_soft_skills($text) {
        $found_skills = [];
        
        foreach ($this->skills_patterns['Soft Skills'] as $skill => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match('/\b' . preg_quote($pattern, '/') . '\b/i', $text)) {
                    $found_skills[] = [
                        'skill' => $skill,
                        'category' => 'Soft Skills',
                        'required' => $this->is_skill_required($text, $pattern)
                    ];
                    break;
                }
            }
        }
        
        return $found_skills;
    }
    
    /**
     * Extract experience level requirements
     */
    public function extract_experience_level($text) {
        // Look for specific year patterns first
        if (preg_match('/(\d+)[\s\-\+]*(?:to|-)?\s*(\d+)?\s*(?:years?|yrs?)\s*(?:of\s*)?(?:experience|exp)/i', $text, $matches)) {
            $min_years = intval($matches[1]);
            $max_years = isset($matches[2]) ? intval($matches[2]) : $min_years;
            
            return [
                'min_years' => $min_years,
                'max_years' => $max_years,
                'level' => $this->categorize_experience_level($min_years),
                'specific' => true
            ];
        }
        
        // Look for general level indicators
        foreach ($this->experience_patterns as $level => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match('/\b' . preg_quote($pattern, '/') . '\b/i', $text)) {
                    return [
                        'level' => str_replace('_', ' ', $level),
                        'min_years' => $this->level_to_years($level)['min'],
                        'max_years' => $this->level_to_years($level)['max'],
                        'specific' => false
                    ];
                }
            }
        }
        
        return [
            'level' => 'Not specified',
            'min_years' => 0,
            'max_years' => null,
            'specific' => false
        ];
    }
    
    /**
     * Extract education requirements
     */
    public function extract_education_requirements($text) {
        $requirements = [];
        
        foreach ($this->education_patterns as $level => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match('/\b' . preg_quote($pattern, '/') . '\b/i', $text)) {
                    $requirements[] = [
                        'level' => $level,
                        'required' => $this->is_education_required($text, $pattern)
                    ];
                }
            }
        }
        
        // Extract specific degree fields
        $degree_fields = $this->extract_degree_fields($text);
        if (!empty($degree_fields)) {
            $requirements['fields'] = $degree_fields;
        }
        
        return $requirements;
    }
    
    /**
     * Identify must-have requirements
     */
    public function identify_must_haves($text) {
        $must_have_indicators = [
            'required', 'must have', 'essential', 'mandatory', 'minimum', 
            'prerequisite', 'necessary', 'needed', 'critical'
        ];
        
        $must_haves = [];
        
        foreach ($must_have_indicators as $indicator) {
            // Find sentences containing must-have indicators
            $pattern = '/[^.!?]*\b' . preg_quote($indicator, '/') . '\b[^.!?]*[.!?]/i';
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[0] as $sentence) {
                    $must_haves[] = trim($sentence);
                }
            }
        }
        
        return array_unique($must_haves);
    }
    
    /**
     * Identify nice-to-have requirements
     */
    public function identify_nice_to_haves($text) {
        $nice_to_have_indicators = [
            'preferred', 'desirable', 'nice to have', 'plus', 'bonus', 
            'advantage', 'beneficial', 'ideal', 'would be great'
        ];
        
        $nice_to_haves = [];
        
        foreach ($nice_to_have_indicators as $indicator) {
            $pattern = '/[^.!?]*\b' . preg_quote($indicator, '/') . '\b[^.!?]*[.!?]/i';
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[0] as $sentence) {
                    $nice_to_haves[] = trim($sentence);
                }
            }
        }
        
        return array_unique($nice_to_haves);
    }
    
    /**
     * Extract salary range
     */
    public function extract_salary_range($text) {
        $salary_patterns = [
            // £50k - £70k
            '/£(\d{2,3})k?\s*[-–]\s*£?(\d{2,3})k/i',
            // $80,000 - $120,000
            '/\$(\d{1,3}(?:,\d{3})*)\s*[-–]\s*\$?(\d{1,3}(?:,\d{3})*)/i',
            // £50,000-£70,000
            '/£(\d{1,3}(?:,\d{3})*)\s*[-–]\s*£?(\d{1,3}(?:,\d{3})*)/i',
            // Up to £100k
            '/up\s*to\s*[£$](\d{2,3})k?/i',
            // £60k+
            '/[£$](\d{2,3})k?\+/i'
        ];
        
        foreach ($salary_patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $min_salary = $this->normalize_salary($matches[1]);
                $max_salary = isset($matches[2]) ? $this->normalize_salary($matches[2]) : $min_salary;
                
                return [
                    'min' => $min_salary,
                    'max' => $max_salary,
                    'currency' => $this->detect_currency($matches[0]),
                    'raw' => $matches[0]
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Extract location requirements
     */
    public function extract_location_requirements($text) {
        $locations = [];
        
        // Common finance locations
        $finance_locations = [
            'London', 'New York', 'Singapore', 'Hong Kong', 'Dubai', 'Frankfurt',
            'Paris', 'Tokyo', 'Sydney', 'Toronto', 'Zurich', 'Geneva'
        ];
        
        foreach ($finance_locations as $location) {
            if (preg_match('/\b' . preg_quote($location, '/') . '\b/i', $text)) {
                $locations[] = $location;
            }
        }
        
        // Check for remote work
        if (preg_match('/\b(?:remote|work from home|wfh|hybrid)\b/i', $text)) {
            $locations[] = 'Remote';
        }
        
        return array_unique($locations);
    }
    
    /**
     * Extract visa requirements
     */
    public function extract_visa_requirements($text) {
        $visa_patterns = [
            'sponsorship' => ['visa sponsorship', 'sponsor visa', 'work permit'],
            'no_sponsorship' => ['no sponsorship', 'no visa sponsorship', 'right to work'],
            'citizen_required' => ['citizen', 'citizenship required', 'must be citizen'],
            'eu_citizen' => ['eu citizen', 'european citizen', 'eu passport']
        ];

        foreach ($visa_patterns as $requirement => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match('/\b' . preg_quote($pattern, '/') . '\b/i', $text)) {
                    return $requirement;
                }
            }
        }

        return 'not_specified';
    }

    /**
     * Extract nationality requirements
     * Detects specific nationality requirements like "UAE National", "Saudi National", etc.
     */
    public function extract_nationality_requirements($text) {
        $nationality_patterns = [
            // GCC Countries
            'uae_national' => ['uae national', 'emirati', 'emirates national', 'local national uae'],
            'saudi_national' => ['saudi national', 'saudi arabia national', 'ksa national', 'local national saudi'],
            'qatari_national' => ['qatari national', 'qatar national', 'local national qatar'],
            'kuwaiti_national' => ['kuwaiti national', 'kuwait national', 'local national kuwait'],
            'bahraini_national' => ['bahraini national', 'bahrain national', 'local national bahrain'],
            'omani_national' => ['omani national', 'oman national', 'local national oman'],
            'gcc_national' => ['gcc national', 'gulf national', 'gcc citizen'],

            // Other common requirements
            'eu_national' => ['eu national', 'european union national', 'eu passport holder'],
            'uk_national' => ['uk national', 'british national', 'british citizen'],
            'us_national' => ['us national', 'us citizen', 'american citizen'],
            'singaporean' => ['singapore national', 'singaporean', 'singapore citizen'],
            'hong_kong' => ['hong kong national', 'hk national', 'hong kong permanent resident']
        ];

        $found_requirements = [];

        foreach ($nationality_patterns as $nationality => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match('/\b' . preg_quote($pattern, '/') . '\b/i', $text)) {
                    $found_requirements[] = [
                        'nationality' => $nationality,
                        'display_name' => ucwords(str_replace('_', ' ', $nationality)),
                        'is_critical' => true,
                        'matched_text' => $pattern
                    ];
                    break; // Only add once per nationality type
                }
            }
        }

        return !empty($found_requirements) ? $found_requirements : null;
    }
    
    /**
     * Helper methods
     */
    
    private function extract_proficiency_level($text, $skill) {
        $context = $this->get_skill_context($text, $skill);
        
        if (preg_match('/\b(?:expert|advanced|senior|lead)\b/i', $context)) {
            return 'Advanced';
        } elseif (preg_match('/\b(?:intermediate|proficient|solid)\b/i', $context)) {
            return 'Intermediate';
        } elseif (preg_match('/\b(?:basic|beginner|junior|entry)\b/i', $context)) {
            return 'Beginner';
        }
        
        return 'Intermediate'; // Default
    }
    
    private function is_skill_required($text, $skill) {
        $context = $this->get_skill_context($text, $skill);
        
        $required_indicators = ['required', 'must', 'essential', 'mandatory', 'minimum'];
        $optional_indicators = ['preferred', 'nice to have', 'plus', 'bonus'];
        
        foreach ($required_indicators as $indicator) {
            if (preg_match('/\b' . preg_quote($indicator, '/') . '\b/i', $context)) {
                return true;
            }
        }
        
        foreach ($optional_indicators as $indicator) {
            if (preg_match('/\b' . preg_quote($indicator, '/') . '\b/i', $context)) {
                return false;
            }
        }
        
        return true; // Default to required if unclear
    }
    
    private function get_skill_context($text, $skill, $words_around = 10) {
        $pattern = '/(?:\S+\s+){0,' . $words_around . '}' . preg_quote($skill, '/') . '(?:\s+\S+){0,' . $words_around . '}/i';
        
        if (preg_match($pattern, $text, $matches)) {
            return $matches[0];
        }
        
        return '';
    }
    
    private function extract_certification_level($text, $cert) {
        $context = $this->get_skill_context($text, $cert);
        
        // Check for specific levels (CFA Level 1, 2, 3)
        if (preg_match('/level\s*(\d)/i', $context, $matches)) {
            return 'Level ' . $matches[1];
        }
        
        return 'Any Level';
    }
    
    private function categorize_experience_level($years) {
        if ($years <= 2) return 'entry_level';
        if ($years <= 5) return 'mid_level';
        if ($years <= 10) return 'senior_level';
        return 'executive';
    }
    
    private function level_to_years($level) {
        $mapping = [
            'entry_level' => ['min' => 0, 'max' => 2],
            'mid_level' => ['min' => 3, 'max' => 5],
            'senior_level' => ['min' => 5, 'max' => 10],
            'executive' => ['min' => 10, 'max' => null]
        ];
        
        return $mapping[$level] ?? ['min' => 0, 'max' => null];
    }
    
    private function extract_degree_fields($text) {
        $fields = [
            'Finance', 'Economics', 'Business', 'Accounting', 'Mathematics',
            'Engineering', 'Computer Science', 'Statistics', 'Physics'
        ];
        
        $found_fields = [];
        
        foreach ($fields as $field) {
            if (preg_match('/\b' . preg_quote($field, '/') . '\b/i', $text)) {
                $found_fields[] = $field;
            }
        }
        
        return $found_fields;
    }
    
    private function is_education_required($text, $education) {
        $context = $this->get_skill_context($text, $education);
        
        if (preg_match('/\b(?:required|minimum|must|essential)\b/i', $context)) {
            return true;
        }
        
        if (preg_match('/\b(?:preferred|desirable|plus)\b/i', $context)) {
            return false;
        }
        
        return true; // Default to required
    }
    
    private function normalize_salary($salary_string) {
        // Remove commas and convert k to thousands
        $salary = str_replace(',', '', $salary_string);
        
        if (preg_match('/(\d+)k?$/i', $salary, $matches)) {
            $number = intval($matches[1]);
            
            // If it's less than 1000, assume it's in thousands (e.g., 50k = 50000)
            if ($number < 1000) {
                return $number * 1000;
            }
            
            return $number;
        }
        
        return intval($salary);
    }
    
    private function detect_currency($salary_text) {
        if (strpos($salary_text, '£') !== false) return 'GBP';
        if (strpos($salary_text, '$') !== false) return 'USD';
        if (strpos($salary_text, '€') !== false) return 'EUR';
        
        return 'USD'; // Default
    }
    
    private function calculate_requirement_weights($requirements) {
        $weights = [
            'technical_skills' => 0.4,
            'financial_skills' => 0.3,
            'experience_level' => 0.2,
            'education_required' => 0.1
        ];
        
        return $weights;
    }
    
    /**
     * Hook to extract requirements when job is saved
     */
    public function extract_requirements_on_save($post_id, $post, $update) {
        // Only process job posts
        if ($post->post_type !== 'sffc_job') {
            return;
        }
        
        // Avoid infinite loops
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        $job_description = $post->post_content;
        $job_title = $post->post_title;
        $company = get_post_meta($post_id, 'company', true);
        
        if (!empty($job_description)) {
            $requirements = $this->extract_all_requirements($job_description, $job_title, $company);
            
            // Save requirements as post meta
            update_post_meta($post_id, 'job_requirements_structured', $requirements);

            // Also save individual requirement types for easier querying
            update_post_meta($post_id, 'job_technical_skills', $requirements['technical_skills']);
            update_post_meta($post_id, 'job_experience_level', $requirements['experience_level']);
            update_post_meta($post_id, 'job_education_required', $requirements['education_required']);
            update_post_meta($post_id, 'job_salary_range', $requirements['salary_range']);
            update_post_meta($post_id, 'job_nationality_requirements', $requirements['nationality_requirements']);
        }
    }
    
    /**
     * Get structured requirements for a job
     */
    public function get_job_requirements($post_id) {
        $requirements = get_post_meta($post_id, 'job_requirements_structured', true);
        
        if (empty($requirements)) {
            // Extract requirements if not already done
            $post = get_post($post_id);
            if ($post && $post->post_type === 'sffc_job') {
                $job_description = $post->post_content;
                $job_title = $post->post_title;
                $company = get_post_meta($post_id, 'company', true);
                
                $requirements = $this->extract_all_requirements($job_description, $job_title, $company);
                update_post_meta($post_id, 'job_requirements_structured', $requirements);
            }
        }
        
        return $requirements ?: [];
    }

    /**
     * Bulk extract requirements for all existing job posts
     * Useful for populating requirements on existing posts after adding new extraction logic
     *
     * @param int $limit Maximum number of posts to process (0 = unlimited)
     * @param int $offset Starting offset for pagination
     * @return array Processing results
     */
    public function bulk_extract_requirements($limit = 0, $offset = 0) {
        $args = [
            'post_type' => 'sffc_job',
            'post_status' => 'publish',
            'posts_per_page' => $limit > 0 ? $limit : -1,
            'offset' => $offset,
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $posts = get_posts($args);
        $results = [
            'total' => count($posts),
            'processed' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'nationality_found' => 0
        ];

        foreach ($posts as $post) {
            try {
                $job_description = $post->post_content;
                $job_title = $post->post_title;
                $company = get_post_meta($post->ID, 'company', true);

                if (empty($job_description)) {
                    $results['skipped']++;
                    continue;
                }

                // Extract all requirements
                $requirements = $this->extract_all_requirements($job_description, $job_title, $company);

                // Save requirements as post meta
                update_post_meta($post->ID, 'job_requirements_structured', $requirements);
                update_post_meta($post->ID, 'job_technical_skills', $requirements['technical_skills']);
                update_post_meta($post->ID, 'job_experience_level', $requirements['experience_level']);
                update_post_meta($post->ID, 'job_education_required', $requirements['education_required']);
                update_post_meta($post->ID, 'job_salary_range', $requirements['salary_range']);
                update_post_meta($post->ID, 'job_nationality_requirements', $requirements['nationality_requirements']);

                $results['processed']++;
                $results['updated']++;

                // Track nationality requirements found
                if (!empty($requirements['nationality_requirements'])) {
                    $results['nationality_found']++;
                }

            } catch (Exception $e) {
                $results['errors'][] = [
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Get posts with nationality requirements (for verification/debugging)
     *
     * @return array List of posts with nationality requirements
     */
    public function get_posts_with_nationality_requirements() {
        $args = [
            'post_type' => 'sffc_job',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'job_nationality_requirements',
                    'compare' => 'EXISTS'
                ]
            ]
        ];

        $posts = get_posts($args);
        $results = [];

        foreach ($posts as $post) {
            $nationality_reqs = get_post_meta($post->ID, 'job_nationality_requirements', true);
            if (!empty($nationality_reqs) && is_array($nationality_reqs)) {
                $results[] = [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'requirements' => $nationality_reqs
                ];
            }
        }

        return $results;
    }
}

// Initialize
SFFC_Requirements_Extractor::get_instance();