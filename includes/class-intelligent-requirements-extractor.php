<?php

/**
 * Intelligent Requirements Extractor
 * 
 * Identifies and prioritizes key requirements from job descriptions
 * Uses NLP-like patterns to extract must-have vs nice-to-have requirements
 * 
 * @package MENA Careers
 * @since 5.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Intelligent_Requirements_Extractor
{

    private static $instance = null;

    /**
     * Requirement indicator patterns
     */
    private $must_have_patterns = [
        '/must\s+have/i',
        '/required/i',
        '/essential/i',
        '/mandatory/i',
        '/minimum\s+requirement/i',
        '/you\s+will\s+need/i',
        '/we\s+require/i',
        '/candidates\s+must/i',
        '/it\s+is\s+critical/i',
        '/non-negotiable/i',
        '/absolutely\s+necessary/i',
        '/compulsory/i',
        '/prerequisite/i',
        '/fundamental/i',
        '/you\s+must/i',
        '/applicants\s+must/i',
        '/experience\s+in\s+.+\s+is\s+required/i',
        '/proven\s+track\s+record\s+in/i',
        '/demonstrated\s+experience/i',
        '/hands-on\s+experience\s+with/i',
        '/strong\s+background\s+in/i',
        '/extensive\s+knowledge\s+of/i',
        '/expert\s+knowledge\s+of/i',
        '/proficiency\s+in/i',
        '/fluent\s+in/i',
        '/certification\s+in/i',
        '/qualified\s+in/i',
        '/degree\s+in/i',
    ];

    /**
     * Nice-to-have patterns
     */
    private $nice_to_have_patterns = [
        '/nice\s+to\s+have/i',
        '/preferred/i',
        '/desirable/i',
        '/ideal/i',
        '/bonus/i',
        '/plus/i',
        '/advantageous/i',
        '/beneficial/i',
        '/would\s+be\s+(?:a\s+)?plus/i',
        '/considered\s+(?:a\s+)?(?:strong\s+)?asset/i',
        '/welcome/i',
        '/helpful/i',
        '/appreciated/i',
    ];

    /**
     * Technical requirement categories
     */
    private $requirement_categories = [
        'programming_languages' => [
            'pattern' => '/\b(python|java|javascript|typescript|c\+\+|c#|ruby|go|rust|scala|kotlin|swift|php|perl|r|matlab|julia|sql|vba|sas|stata)\b/i',
            'weight' => 10,
            'label' => 'Programming'
        ],
        'frameworks' => [
            'pattern' => '/\b(react|angular|vue|django|flask|spring|\.net|rails|express|fastapi|tensorflow|pytorch|scikit-learn|pandas|numpy)\b/i',
            'weight' => 8,
            'label' => 'Frameworks'
        ],
        'databases' => [
            'pattern' => '/\b(sql|mysql|postgresql|mongodb|oracle|redis|cassandra|dynamodb|elasticsearch|neo4j|snowflake|bigquery)\b/i',
            'weight' => 7,
            'label' => 'Database'
        ],
        'cloud_platforms' => [
            'pattern' => '/\b(aws|azure|gcp|google cloud|kubernetes|docker|terraform|jenkins|gitlab|github)\b/i',
            'weight' => 8,
            'label' => 'Cloud/DevOps'
        ],
        'financial_platforms' => [
            'pattern' => '/\b(bloomberg|reuters|factset|capital iq|pitchbook|dealogic|morningstar|refinitiv|eikon|murex|calypso|fidessa|charles river|simcorp)\b/i',
            'weight' => 9,
            'label' => 'Financial Systems'
        ],
        'financial_concepts' => [
            'pattern' => '/\b(derivatives|options|futures|swaps|bonds|equities|fx|fixed income|structured products|credit|risk management|var|portfolio management|trading|hedge|arbitrage|alpha|beta|sharpe ratio|black-scholes|monte carlo|stochastic|basel|ifrs|mifid)\b/i',
            'weight' => 9,
            'label' => 'Financial Knowledge'
        ],
        'certifications' => [
            'pattern' => '/\b(cfa|frm|caia|pmp|cpa|aca|acca|cia|cima|mba|phd|masters?|bachelor)\b/i',
            'weight' => 8,
            'label' => 'Certifications'
        ],
        'soft_skills' => [
            'pattern' => '/\b(leadership|management|communication|presentation|stakeholder|negotiation|problem-solving|analytical|strategic|attention to detail|team player|self-starter|initiative)\b/i',
            'weight' => 5,
            'label' => 'Soft Skills'
        ],
        'experience_years' => [
            'pattern' => '/(\d+)\+?\s*(?:to\s+\d+\s*)?years?\s+(?:of\s+)?(?:relevant\s+)?experience/i',
            'weight' => 10,
            'label' => 'Experience'
        ],
        'education_level' => [
            'pattern' => '/\b(?:bachelor|master|phd|doctorate|degree|diploma|qualification)\b/i',
            'weight' => 7,
            'label' => 'Education'
        ],
        'industry_experience' => [
            'pattern' => '/experience\s+(?:in|with)\s+(?:investment banking|private equity|hedge fund|asset management|wealth management|corporate finance|capital markets|m&a|leveraged finance|structured finance|quantitative|algorithmic trading|risk management|compliance|audit|consulting|fintech|insurtech|regtech)/i',
            'weight' => 9,
            'label' => 'Industry Experience'
        ]
    ];

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Extract key requirements from job data
     */
    public function extract_key_requirements($job_data)
    {
        // Compile all text
        $full_text = $this->compile_text($job_data);

        // Split into sections
        $sections = $this->identify_sections($full_text);

        // Extract requirements from each section
        $all_requirements = [];

        // Priority 1: Extract from requirements/qualifications section
        if (!empty($sections['requirements'])) {
            $req_from_section = $this->extract_from_section($sections['requirements'], 'must_have');
            $all_requirements = array_merge($all_requirements, $req_from_section);
        }

        if (!empty($sections['qualifications'])) {
            $qual_from_section = $this->extract_from_section($sections['qualifications'], 'must_have');
            $all_requirements = array_merge($all_requirements, $qual_from_section);
        }

        // Priority 2: Extract from responsibilities if needed
        if (count($all_requirements) < 3 && !empty($sections['responsibilities'])) {
            $resp_requirements = $this->extract_from_section($sections['responsibilities'], 'implied');
            $all_requirements = array_merge($all_requirements, $resp_requirements);
        }

        // Priority 3: Extract from full description if still needed
        if (count($all_requirements) < 3) {
            $desc_requirements = $this->extract_from_full_text($full_text);
            $all_requirements = array_merge($all_requirements, $desc_requirements);
        }

        // Deduplicate and prioritize
        $prioritized = $this->prioritize_requirements($all_requirements);

        // Format for display
        return $this->format_requirements($prioritized);
    }

    /**
     * Compile all text from job data
     */
    private function compile_text($job_data)
    {
        $texts = [
            $job_data['qualifications'] ?? '',
            $job_data['requirements'] ?? '',
            $job_data['responsibilities'] ?? '',
            $job_data['description'] ?? ''
        ];

        return implode("\n", array_filter($texts));
    }

    /**
     * Identify sections in the text
     */
    private function identify_sections($text)
    {
        $sections = [
            'requirements' => '',
            'qualifications' => '',
            'responsibilities' => '',
            'description' => ''
        ];

        // Try to split by headers
        $patterns = [
            'requirements' => '/(?:requirements?|what we.?re looking for|what you.?ll need|essential criteria|must haves?)(.*?)(?=(?:responsibilities|qualifications|benefits|how to apply|about us|$))/is',
            'qualifications' => '/(?:qualifications?|skills? and experience|about you|ideal candidate|required skills?)(.*?)(?=(?:responsibilities|requirements|benefits|how to apply|about us|$))/is',
            'responsibilities' => '/(?:responsibilities|what you.?ll do|key responsibilities|duties|role overview|the role)(.*?)(?=(?:requirements|qualifications|benefits|how to apply|about us|$))/is',
        ];

        foreach ($patterns as $section => $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $sections[$section] = $matches[1];
            }
        }

        // If no sections found, treat entire text as description
        if (empty($sections['requirements']) && empty($sections['qualifications'])) {
            $sections['description'] = $text;
        }

        return $sections;
    }

    /**
     * Extract requirements from a specific section
     */
    private function extract_from_section($section_text, $priority = 'must_have')
    {
        $requirements = [];

        // Split into sentences or bullet points
        $lines = $this->split_into_requirements($section_text);

        foreach ($lines as $line) {
            $line = trim($line);
            if (strlen($line) < 10) continue;

            // Check if it's a must-have
            $is_must_have = false;
            foreach ($this->must_have_patterns as $pattern) {
                if (preg_match($pattern, $line)) {
                    $is_must_have = true;
                    break;
                }
            }

            // Check if it's nice-to-have
            $is_nice_to_have = false;
            if (!$is_must_have) {
                foreach ($this->nice_to_have_patterns as $pattern) {
                    if (preg_match($pattern, $line)) {
                        $is_nice_to_have = true;
                        break;
                    }
                }
            }

            // Extract specific requirements from the line
            $extracted = $this->extract_specific_requirements($line);

            foreach ($extracted as $req) {
                $req['priority'] = $is_must_have ? 'must_have' : ($is_nice_to_have ? 'nice_to_have' : $priority);
                $req['source_text'] = $line;
                $requirements[] = $req;
            }
        }

        return $requirements;
    }

    /**
     * Split text into individual requirements
     */
    private function split_into_requirements($text)
    {
        $requirements = [];

        // First try to split by bullet points or numbers
        if (preg_match_all('/[•·▪▫◦‣⁃★☆►▸→⇒]+\s*(.+?)(?=[•·▪▫◦‣⁃★☆►▸→⇒\n]|$)/s', $text, $matches)) {
            $requirements = $matches[1];
        } elseif (preg_match_all('/\d+[\.\)]\s*(.+?)(?=\d+[\.\)]|$)/s', $text, $matches)) {
            $requirements = $matches[1];
        } elseif (preg_match_all('/^[-–—]\s*(.+?)$/m', $text, $matches)) {
            $requirements = $matches[1];
        } else {
            // Split by sentences
            $sentences = preg_split('/[.!?]\s+/', $text);
            foreach ($sentences as $sentence) {
                if (strlen(trim($sentence)) > 20) {
                    $requirements[] = $sentence;
                }
            }
        }

        return $requirements;
    }

    /**
     * Extract specific requirements from a line of text
     */
    private function extract_specific_requirements($text)
    {
        $requirements = [];
        $text_lower = strtolower($text);

        // Check each category
        foreach ($this->requirement_categories as $category => $config) {
            if (preg_match_all($config['pattern'], $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $requirement = [
                        'type' => $category,
                        'value' => $match[0],
                        'label' => $config['label'],
                        'weight' => $config['weight'],
                        'context' => $this->extract_context($text, $match[0])
                    ];

                    // Special handling for experience years
                    if ($category === 'experience_years') {
                        $requirement['value'] = $this->format_experience_requirement($match[0]);
                    }

                    // Special handling for certifications
                    if ($category === 'certifications') {
                        $requirement['value'] = strtoupper($match[0]);
                    }

                    $requirements[] = $requirement;
                }
            }
        }

        // If no specific requirements found, extract key phrases
        if (empty($requirements)) {
            $key_phrases = $this->extract_key_phrases($text);
            foreach ($key_phrases as $phrase) {
                $requirements[] = [
                    'type' => 'general',
                    'value' => $phrase,
                    'label' => 'Requirement',
                    'weight' => 5,
                    'context' => ''
                ];
            }
        }

        return $requirements;
    }

    /**
     * Extract context around a requirement
     */
    private function extract_context($text, $requirement)
    {
        $pos = stripos($text, $requirement);
        if ($pos === false) return '';

        // Get surrounding words
        $start = max(0, $pos - 50);
        $end = min(strlen($text), $pos + strlen($requirement) + 50);
        $context = substr($text, $start, $end - $start);

        // Clean up
        $context = trim(preg_replace('/\s+/', ' ', $context));

        // Extract meaningful context
        if (preg_match('/(?:experience (?:with|in)|knowledge of|proficiency in|expertise in|familiar with|understanding of|ability to|skills in)\s+' . preg_quote($requirement, '/') . '/i', $context, $matches)) {
            return $matches[0];
        }

        return '';
    }

    /**
     * Extract key phrases when no specific requirements found
     */
    private function extract_key_phrases($text)
    {
        $phrases = [];

        // Look for common requirement patterns
        $patterns = [
            '/(?:experience|expertise|knowledge|proficiency|skills?)\s+(?:in|with)\s+([^,.;]+)/i',
            '/(?:ability|able)\s+to\s+([^,.;]+)/i',
            '/(?:strong|excellent|good|solid)\s+([^,.;]+)\s+skills?/i',
            '/(?:proven|demonstrated|extensive)\s+([^,.;]+)/i',
            '/(?:familiar|comfortable)\s+with\s+([^,.;]+)/i',
            '/(?:understanding|knowledge)\s+of\s+([^,.;]+)/i',
            '/(?:must be able to|should be able to)\s+([^,.;]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $phrase) {
                    $phrase = trim($phrase);
                    if (strlen($phrase) > 5 && strlen($phrase) < 50) {
                        $phrases[] = $phrase;
                    }
                }
            }
        }

        return array_unique($phrases);
    }

    /**
     * Extract requirements from full text when sections not available
     */
    private function extract_from_full_text($text)
    {
        $requirements = [];

        // Look for must-have indicators anywhere in text
        foreach ($this->must_have_patterns as $pattern) {
            if (preg_match_all($pattern . '.*?([^.!?]{10,100})/i', $text, $matches)) {
                foreach ($matches[1] as $match) {
                    $extracted = $this->extract_specific_requirements($match);
                    foreach ($extracted as $req) {
                        $req['priority'] = 'must_have';
                        $requirements[] = $req;
                    }
                }
            }
        }

        // If still not enough, extract from entire text
        if (count($requirements) < 3) {
            $all_extracted = $this->extract_specific_requirements($text);
            foreach ($all_extracted as $req) {
                $req['priority'] = 'implied';
                $requirements[] = $req;
            }
        }

        return $requirements;
    }

    /**
     * Prioritize and deduplicate requirements
     */
    private function prioritize_requirements($requirements)
    {
        // Remove duplicates
        $unique = [];
        $seen = [];

        foreach ($requirements as $req) {
            $key = strtolower($req['value']);
            if (!isset($seen[$key])) {
                $unique[] = $req;
                $seen[$key] = true;
            }
        }

        // Sort by priority and weight
        usort($unique, function ($a, $b) {
            // Priority order: must_have > implied > nice_to_have
            $priority_order = ['must_have' => 3, 'implied' => 2, 'nice_to_have' => 1];
            $a_priority = $priority_order[$a['priority']] ?? 0;
            $b_priority = $priority_order[$b['priority']] ?? 0;

            if ($a_priority !== $b_priority) {
                return $b_priority - $a_priority;
            }

            // Then by weight
            return ($b['weight'] ?? 0) - ($a['weight'] ?? 0);
        });

        return $unique;
    }

    /**
     * Format experience requirement
     */
    private function format_experience_requirement($text)
    {
        if (preg_match('/(\d+)\+?\s*(?:to\s+(\d+)\s*)?years?/i', $text, $matches)) {
            $min = $matches[1];
            $max = $matches[2] ?? null;

            if ($max) {
                return "{$min}-{$max} years experience";
            } else {
                return "{$min}+ years experience";
            }
        }
        return $text;
    }

    /**
     * Format requirements for display
     */
    private function format_requirements($requirements)
    {
        $formatted = [];
        $count = 0;
        $max_requirements = 5; // Show up to 5 key requirements

        // Group by type for better display
        $grouped = [];
        foreach ($requirements as $req) {
            $type = $req['type'];
            if (!isset($grouped[$type])) {
                $grouped[$type] = [];
            }
            $grouped[$type][] = $req;
        }

        // Format grouped requirements
        foreach ($grouped as $type => $reqs) {
            if ($count >= $max_requirements) break;

            // For similar requirements, combine them
            if (count($reqs) > 1 && in_array($type, ['programming_languages', 'frameworks', 'databases', 'financial_platforms'])) {
                $values = array_map(function ($r) {
                    return $r['value'];
                }, array_slice($reqs, 0, 3));
                $formatted[] = [
                    'text' => implode(', ', $values),
                    'label' => $reqs[0]['label'],
                    'priority' => $reqs[0]['priority'],
                    'icon' => $this->get_icon_for_type($type)
                ];
                $count++;
            } else {
                // Add individual requirements
                foreach ($reqs as $req) {
                    if ($count >= $max_requirements) break;

                    $text = $req['value'];
                    if (!empty($req['context'])) {
                        $text = $req['context'];
                    }

                    $formatted[] = [
                        'text' => $this->clean_requirement_text($text),
                        'label' => $req['label'],
                        'priority' => $req['priority'],
                        'icon' => $this->get_icon_for_type($req['type'])
                    ];
                    $count++;
                }
            }
        }

        // If we don't have at least 3, add some general requirements
        if (count($formatted) < 3) {
            $defaults = $this->get_default_requirements_for_role($requirements);
            foreach ($defaults as $default) {
                if (count($formatted) >= 3) break;
                $formatted[] = $default;
            }
        }

        return array_slice($formatted, 0, $max_requirements);
    }

    /**
     * Clean requirement text for display
     */
    private function clean_requirement_text($text)
    {
        // Remove common prefixes
        $text = preg_replace('/^(?:must have|required|essential|mandatory|minimum requirement|you will need|we require)\s*/i', '', $text);

        // Capitalize first letter
        $text = ucfirst(trim($text));

        // Limit length
        if (strlen($text) > 60) {
            $text = substr($text, 0, 57) . '...';
        }

        return $text;
    }

    /**
     * Get icon for requirement type
     */
    private function get_icon_for_type($type)
    {
        $icons = [
            'programming_languages' => '►',
            'frameworks' => '◆',
            'databases' => '▪',
            'cloud_platforms' => '◈',
            'financial_platforms' => '▸',
            'financial_concepts' => '◉',
            'certifications' => '★',
            'soft_skills' => '◦',
            'experience_years' => '▹',
            'education_level' => '▫',
            'industry_experience' => '■',
            'general' => '•'
        ];

        return $icons[$type] ?? '•';
    }

    /**
     * Get default requirements based on role
     */
    private function get_default_requirements_for_role($existing_requirements)
    {
        // Analyze existing requirements to determine role type
        $has_technical = false;
        $has_financial = false;

        foreach ($existing_requirements as $req) {
            if (in_array($req['type'], ['programming_languages', 'frameworks', 'databases'])) {
                $has_technical = true;
            }
            if (in_array($req['type'], ['financial_platforms', 'financial_concepts'])) {
                $has_financial = true;
            }
        }

        $defaults = [];

        if ($has_technical && $has_financial) {
            // Quant/FinTech role
            $defaults = [
                ['text' => 'Strong analytical and problem-solving skills', 'label' => 'Skills', 'priority' => 'implied', 'icon' => '◆'],
                ['text' => 'Experience with financial markets', 'label' => 'Experience', 'priority' => 'implied', 'icon' => '◉'],
                ['text' => 'Excellent communication skills', 'label' => 'Soft Skills', 'priority' => 'implied', 'icon' => '◦']
            ];
        } elseif ($has_financial) {
            // Finance role
            $defaults = [
                ['text' => 'Strong financial analysis skills', 'label' => 'Skills', 'priority' => 'implied', 'icon' => '▸'],
                ['text' => 'Attention to detail', 'label' => 'Skills', 'priority' => 'implied', 'icon' => '▪'],
                ['text' => 'Team collaboration', 'label' => 'Soft Skills', 'priority' => 'implied', 'icon' => '◦']
            ];
        } elseif ($has_technical) {
            // Tech role
            $defaults = [
                ['text' => 'Problem-solving abilities', 'label' => 'Skills', 'priority' => 'implied', 'icon' => '◆'],
                ['text' => 'Version control (Git)', 'label' => 'Technical', 'priority' => 'implied', 'icon' => '►'],
                ['text' => 'Agile methodology', 'label' => 'Process', 'priority' => 'implied', 'icon' => '◈']
            ];
        } else {
            // General role
            $defaults = [
                ['text' => 'Relevant industry experience', 'label' => 'Experience', 'priority' => 'implied', 'icon' => '■'],
                ['text' => 'Strong communication skills', 'label' => 'Soft Skills', 'priority' => 'implied', 'icon' => '◦'],
                ['text' => 'Bachelor\'s degree or equivalent', 'label' => 'Education', 'priority' => 'implied', 'icon' => '▫']
            ];
        }

        return $defaults;
    }
}
