<?php
/**
 * Matching Engine - Sophisticated CV-Job matching algorithm
 * Calculates comprehensive match scores with weighted factors
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prevent duplicate class declaration
if (!class_exists('SFFC_Matching_Engine')) {

class SFFC_Matching_Engine {
    
    private $weights = array(
        'technicalSkills' => 0.25,
        'experience' => 0.20,
        'education' => 0.15,
        'softSkills' => 0.15,
        'keywords' => 0.10,
        'responsibilities' => 0.10,
        'culture' => 0.05
    );
    
    /**
     * Calculate comprehensive match score
     */
    public function calculate_match($cv_data, $job_analysis) {
        $scores = array();
        
        // Calculate individual scores
        $scores['technicalSkills'] = $this->calculate_skill_match(
            $cv_data['parsed_data']['skills']['technical'] ?? array(),
            $job_analysis['required_skills'] ?? array(),
            $job_analysis['preferred_skills'] ?? array()
        );
        
        $scores['experience'] = $this->calculate_experience_match(
            $cv_data['parsed_data']['experience'] ?? array(),
            $job_analysis['experience'] ?? array()
        );
        
        $scores['education'] = $this->calculate_education_match(
            $cv_data['parsed_data']['education'] ?? array(),
            $job_analysis['education'] ?? array()
        );
        
        $scores['softSkills'] = $this->calculate_soft_skill_match(
            $cv_data['parsed_data']['skills']['soft'] ?? array(),
            $job_analysis['soft_skills'] ?? array()
        );
        
        $scores['keywords'] = $this->calculate_keyword_optimization(
            $cv_data['extracted_text'] ?? '',
            $job_analysis['keywords'] ?? array()
        );
        
        $scores['responsibilities'] = $this->calculate_responsibility_match(
            $cv_data['parsed_data']['experience'] ?? array(),
            $job_analysis['responsibilities'] ?? array()
        );
        
        $scores['culture'] = $this->calculate_culture_fit(
            $cv_data['parsed_data'] ?? array(),
            $job_analysis['culture'] ?? array()
        );
        
        // Calculate weighted total
        $total_score = 0;
        foreach ($this->weights as $factor => $weight) {
            $total_score += ($scores[$factor] * $weight);
        }
        
        // Generate insights
        $strengths = $this->identify_strengths($scores, $cv_data, $job_analysis);
        $gaps = $this->identify_gaps($scores, $cv_data, $job_analysis);
        $recommendations = $this->generate_recommendations($scores, $gaps, $job_analysis);
        
        return array(
            'overall_score' => round($total_score),
            'breakdown' => $scores,
            'strengths' => $strengths,
            'gaps' => $gaps,
            'recommendations' => $recommendations,
            'match_level' => $this->determine_match_level($total_score)
        );
    }
    
    /**
     * Calculate technical skill match with partial credit
     */
    private function calculate_skill_match($cv_skills, $required_skills, $preferred_skills) {
        if (empty($required_skills) && empty($preferred_skills)) {
            return 75; // Default score if no skills specified
        }
        
        $cv_skills_lower = array_map('strtolower', $cv_skills);
        $score = 0;
        $max_score = 0;
        
        // Check required skills (70% weight)
        foreach ($required_skills as $skill) {
            $max_score += 70 / max(1, count($required_skills));
            if ($this->has_skill($skill, $cv_skills_lower)) {
                $score += 70 / max(1, count($required_skills));
            } elseif ($this->has_similar_skill($skill, $cv_skills_lower)) {
                $score += 35 / max(1, count($required_skills)); // 50% partial credit
            }
        }
        
        // Check preferred skills (30% weight)
        foreach ($preferred_skills as $skill) {
            $max_score += 30 / max(1, count($preferred_skills));
            if ($this->has_skill($skill, $cv_skills_lower)) {
                $score += 30 / max(1, count($preferred_skills));
            } elseif ($this->has_similar_skill($skill, $cv_skills_lower)) {
                $score += 15 / max(1, count($preferred_skills)); // 50% partial credit
            }
        }
        
        return $max_score > 0 ? min(100, ($score / $max_score) * 100) : 0;
    }
    
    /**
     * Calculate experience match with seniority alignment
     */
    private function calculate_experience_match($cv_experience, $job_requirements) {
        $score = 0;
        
        // Years of experience matching
        $cv_years = $this->calculate_total_experience($cv_experience);
        $required_years = $job_requirements['years_min'] ?? 0;
        $preferred_years = $job_requirements['years_max'] ?? $required_years + 5;
        
        if ($cv_years >= $required_years) {
            if ($cv_years <= $preferred_years) {
                $score += 40; // Perfect range match
            } else if ($cv_years <= $preferred_years + 3) {
                $score += 35; // Slightly overqualified
            } else {
                $score += 25; // Overqualified
            }
        } else if ($cv_years >= $required_years - 2) {
            $score += 20; // Close to requirement
        } else {
            $score += 10; // Under-qualified
        }
        
        // Relevant experience matching
        if (!empty($job_requirements['specific_experience'])) {
            $relevant_score = $this->calculate_relevant_experience(
                $cv_experience,
                $job_requirements['specific_experience']
            );
            $score += $relevant_score * 0.3;
        }
        
        // Management experience if required
        if ($job_requirements['management_experience'] ?? false) {
            if ($this->has_management_experience($cv_experience)) {
                $score += 20;
            }
        }
        
        // Industry experience
        if (!empty($job_requirements['industry_experience'])) {
            if ($this->has_industry_experience($cv_experience, $job_requirements['industry_experience'])) {
                $score += 10;
            }
        }
        
        return min(100, $score);
    }
    
    /**
     * Calculate education match
     */
    private function calculate_education_match($cv_education, $job_education) {
        if (empty($job_education['degree_level'])) {
            return 80; // Default if no education specified
        }
        
        $score = 0;
        
        // Degree level matching
        $degree_hierarchy = array(
            'High School' => 1,
            'Associate' => 2,
            'Bachelor\'s' => 3,
            'Master\'s' => 4,
            'MBA' => 4,
            'PhD' => 5,
            'Doctorate' => 5
        );
        
        $cv_degree_level = $this->extract_highest_degree($cv_education);
        $required_level = $job_education['degree_level'];
        
        $cv_level_num = $degree_hierarchy[$cv_degree_level] ?? 0;
        $required_level_num = $degree_hierarchy[$required_level] ?? 3;
        
        if ($cv_level_num >= $required_level_num) {
            $score += 50;
        } elseif ($cv_level_num == $required_level_num - 1) {
            $score += 30;
        } else {
            $score += 10;
        }
        
        // Field of study matching
        if (!empty($job_education['degree_field'])) {
            if ($this->has_relevant_field($cv_education, $job_education['degree_field'])) {
                $score += 30;
            } else {
                $score += 10; // Any degree is better than none
            }
        }
        
        // Certifications
        if (!empty($job_education['certifications'])) {
            $cert_match = $this->calculate_certification_match(
                $cv_education['certifications'] ?? array(),
                $job_education['certifications']
            );
            $score += $cert_match * 0.2;
        }
        
        return min(100, $score);
    }
    
    /**
     * Calculate soft skill match
     */
    private function calculate_soft_skill_match($cv_soft_skills, $job_soft_skills) {
        if (empty($job_soft_skills)) {
            return 75;
        }
        
        $matched = 0;
        $cv_skills_lower = array_map('strtolower', $cv_soft_skills);
        
        foreach ($job_soft_skills as $skill_data) {
            $skill = is_array($skill_data) ? $skill_data['skill'] : $skill_data;
            $importance = is_array($skill_data) ? $skill_data['importance'] : 'Mentioned';
            
            $weight = 1;
            switch ($importance) {
                case 'Required':
                    $weight = 2;
                    break;
                case 'Preferred':
                    $weight = 1.5;
                    break;
            }
            
            if ($this->has_skill($skill, $cv_skills_lower)) {
                $matched += $weight;
            }
        }
        
        $max_possible = count($job_soft_skills) * 2; // Max weight
        return min(100, ($matched / $max_possible) * 100);
    }
    
    /**
     * Calculate keyword optimization
     */
    private function calculate_keyword_optimization($cv_text, $job_keywords) {
        if (empty($job_keywords)) {
            return 70;
        }
        
        $cv_text_lower = strtolower($cv_text);
        $keyword_scores = array();
        
        foreach ($job_keywords as $keyword) {
            $keyword_lower = strtolower($keyword);
            $count = substr_count($cv_text_lower, $keyword_lower);
            
            // Ideal density is 2-3 occurrences per 1000 words
            $word_count = str_word_count($cv_text);
            $ideal_count = max(1, floor($word_count / 500));
            
            if ($count == 0) {
                $keyword_scores[] = 0;
            } elseif ($count <= $ideal_count) {
                $keyword_scores[] = ($count / $ideal_count) * 100;
            } elseif ($count <= $ideal_count * 2) {
                $keyword_scores[] = 100 - (($count - $ideal_count) * 10);
            } else {
                $keyword_scores[] = 50; // Over-optimized
            }
        }
        
        return !empty($keyword_scores) ? 
               min(100, array_sum($keyword_scores) / count($keyword_scores)) : 0;
    }
    
    /**
     * Calculate responsibility match
     */
    private function calculate_responsibility_match($cv_experience, $job_responsibilities) {
        if (empty($job_responsibilities['primary'])) {
            return 75;
        }
        
        $cv_responsibilities = $this->extract_all_responsibilities($cv_experience);
        $cv_text = implode(' ', $cv_responsibilities);
        $cv_text_lower = strtolower($cv_text);
        
        $matched = 0;
        $total = 0;
        
        // Check primary responsibilities (70% weight)
        foreach ($job_responsibilities['primary'] as $responsibility) {
            $total += 70 / max(1, count($job_responsibilities['primary']));
            if ($this->has_similar_responsibility($responsibility, $cv_text_lower)) {
                $matched += 70 / max(1, count($job_responsibilities['primary']));
            }
        }
        
        // Check secondary responsibilities (30% weight)
        if (!empty($job_responsibilities['secondary'])) {
            foreach ($job_responsibilities['secondary'] as $responsibility) {
                $total += 30 / max(1, count($job_responsibilities['secondary']));
                if ($this->has_similar_responsibility($responsibility, $cv_text_lower)) {
                    $matched += 30 / max(1, count($job_responsibilities['secondary']));
                }
            }
        }
        
        return $total > 0 ? min(100, ($matched / $total) * 100) : 75;
    }
    
    /**
     * Calculate culture fit
     */
    private function calculate_culture_fit($cv_data, $job_culture) {
        // This is a simplified culture fit calculation
        // In production, this would use more sophisticated NLP
        $score = 70; // Base score
        
        if (!empty($job_culture['work_style'])) {
            // Check if CV mentions compatible work styles
            $cv_text = $cv_data['full_text'] ?? '';
            foreach ($job_culture['work_style'] as $style) {
                if (stripos($cv_text, $style) !== false) {
                    $score += 10;
                }
            }
        }
        
        if (!empty($job_culture['values'])) {
            // Check value alignment
            $cv_text = $cv_data['summary'] ?? '';
            foreach ($job_culture['values'] as $value) {
                if (stripos($cv_text, $value) !== false) {
                    $score += 5;
                }
            }
        }
        
        return min(100, $score);
    }
    
    /**
     * Helper: Check if candidate has skill
     */
    private function has_skill($required_skill, $cv_skills) {
        $required_lower = strtolower($required_skill);
        return in_array($required_lower, $cv_skills);
    }
    
    /**
     * Helper: Check for similar skills
     */
    private function has_similar_skill($required_skill, $cv_skills) {
        $skill_synonyms = array(
            'javascript' => array('js', 'node.js', 'nodejs', 'react', 'vue', 'angular'),
            'python' => array('django', 'flask', 'pandas', 'numpy'),
            'java' => array('spring', 'springboot', 'hibernate'),
            'database' => array('sql', 'mysql', 'postgresql', 'mongodb', 'oracle'),
            'cloud' => array('aws', 'azure', 'gcp', 'google cloud'),
            'devops' => array('docker', 'kubernetes', 'jenkins', 'ci/cd', 'terraform')
        );
        
        $required_lower = strtolower($required_skill);
        
        // Check direct synonyms
        foreach ($skill_synonyms as $main_skill => $synonyms) {
            if ($required_lower == $main_skill || in_array($required_lower, $synonyms)) {
                foreach ($cv_skills as $cv_skill) {
                    if ($cv_skill == $main_skill || in_array($cv_skill, $synonyms)) {
                        return true;
                    }
                }
            }
        }
        
        // Check partial matches
        foreach ($cv_skills as $cv_skill) {
            if (strpos($cv_skill, $required_lower) !== false || 
                strpos($required_lower, $cv_skill) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Helper: Calculate total years of experience
     */
    private function calculate_total_experience($cv_experience) {
        if (empty($cv_experience)) {
            return 0;
        }
        
        $total_months = 0;
        
        foreach ($cv_experience as $job) {
            if (!empty($job['date_range'])) {
                $months = $this->parse_date_range($job['date_range']);
                $total_months += $months;
            }
        }
        
        return round($total_months / 12, 1);
    }
    
    /**
     * Helper: Parse date range to months
     */
    private function parse_date_range($date_range) {
        // Simplified date parsing
        // Format: "Jan 2020 - Dec 2022" or "2020 - Present"
        
        if (stripos($date_range, 'present') !== false) {
            // Extract start date and calculate to present
            preg_match('/(\d{4})/', $date_range, $matches);
            if (!empty($matches[1])) {
                $start_year = intval($matches[1]);
                $current_year = date('Y');
                return ($current_year - $start_year) * 12;
            }
        }
        
        // Extract years
        preg_match_all('/(\d{4})/', $date_range, $matches);
        if (count($matches[1]) >= 2) {
            $start = intval($matches[1][0]);
            $end = intval($matches[1][1]);
            return ($end - $start) * 12;
        }
        
        return 12; // Default to 1 year if can't parse
    }
    
    /**
     * Helper: Check for management experience
     */
    private function has_management_experience($cv_experience) {
        $management_keywords = array(
            'managed', 'led', 'supervised', 'directed', 'coordinated',
            'team lead', 'manager', 'supervisor', 'director', 'head of',
            'chief', 'vp', 'vice president', 'direct reports'
        );
        
        foreach ($cv_experience as $job) {
            $job_text = strtolower(json_encode($job));
            foreach ($management_keywords as $keyword) {
                if (strpos($job_text, $keyword) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Helper: Extract all responsibilities
     */
    private function extract_all_responsibilities($cv_experience) {
        $responsibilities = array();
        
        foreach ($cv_experience as $job) {
            if (!empty($job['responsibilities'])) {
                $responsibilities = array_merge($responsibilities, $job['responsibilities']);
            }
            if (!empty($job['achievements'])) {
                $responsibilities = array_merge($responsibilities, $job['achievements']);
            }
        }
        
        return $responsibilities;
    }
    
    /**
     * Helper: Check similar responsibility
     */
    private function has_similar_responsibility($required, $cv_text) {
        // Extract key action verbs and objects
        $action_verbs = array(
            'develop', 'design', 'implement', 'manage', 'lead',
            'create', 'build', 'maintain', 'support', 'analyze',
            'optimize', 'improve', 'coordinate', 'collaborate'
        );
        
        $required_lower = strtolower($required);
        
        // Check direct mention
        if (strpos($cv_text, $required_lower) !== false) {
            return true;
        }
        
        // Check for similar action verbs
        foreach ($action_verbs as $verb) {
            if (strpos($required_lower, $verb) !== false && 
                strpos($cv_text, $verb) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Identify candidate strengths
     */
    private function identify_strengths($scores, $cv_data, $job_analysis) {
        $strengths = array();
        
        // Identify high-scoring areas
        foreach ($scores as $area => $score) {
            if ($score >= 80) {
                $strengths[] = array(
                    'area' => $this->format_area_name($area),
                    'score' => $score,
                    'description' => $this->get_strength_description($area, $score, $cv_data, $job_analysis)
                );
            }
        }
        
        // Sort by score
        usort($strengths, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        return array_slice($strengths, 0, 5); // Top 5 strengths
    }
    
    /**
     * Identify gaps
     */
    private function identify_gaps($scores, $cv_data, $job_analysis) {
        $gaps = array();
        
        // Check for missing required skills
        if (!empty($job_analysis['required_skills'])) {
            $cv_skills = $cv_data['parsed_data']['skills']['technical'] ?? array();
            foreach ($job_analysis['required_skills'] as $skill) {
                if (!$this->has_skill($skill, array_map('strtolower', $cv_skills))) {
                    $gaps[] = array(
                        'type' => 'skill',
                        'item' => $skill,
                        'severity' => 'high',
                        'recommendation' => "Add '$skill' to your skills if you have experience with it"
                    );
                }
            }
        }
        
        // Check for experience gaps
        if ($scores['experience'] < 60) {
            $cv_years = $this->calculate_total_experience($cv_data['parsed_data']['experience'] ?? array());
            $required_years = $job_analysis['experience']['years_min'] ?? 0;
            
            if ($cv_years < $required_years) {
                $gaps[] = array(
                    'type' => 'experience',
                    'item' => "Years of experience",
                    'severity' => 'medium',
                    'recommendation' => "Highlight transferable skills and relevant projects to compensate for experience gap"
                );
            }
        }
        
        // Check for education gaps
        if ($scores['education'] < 50 && !empty($job_analysis['education']['degree_level'])) {
            $gaps[] = array(
                'type' => 'education',
                'item' => $job_analysis['education']['degree_level'],
                'severity' => 'low',
                'recommendation' => "Emphasize relevant certifications, courses, or self-study"
            );
        }

        // Check for nationality requirements
        // Note: These are flagged as critical warnings but do NOT impact the match score
        if (!empty($job_analysis['nationality_requirements'])) {
            foreach ($job_analysis['nationality_requirements'] as $nat_req) {
                $gaps[] = array(
                    'type' => 'nationality_requirement',
                    'item' => $nat_req['display_name'] ?? ucwords(str_replace('_', ' ', $nat_req['nationality'])),
                    'severity' => 'critical',
                    'is_dealbreaker' => true,
                    'score_impact' => 0, // No score penalty per user requirement
                    'recommendation' => "This role requires " . ($nat_req['display_name'] ?? $nat_req['nationality']) . " status. Verify eligibility before applying."
                );
            }
        }

        return $gaps;
    }
    
    /**
     * Generate tailored recommendations
     */
    private function generate_recommendations($scores, $gaps, $job_analysis) {
        $recommendations = array();
        
        // Skill recommendations
        if ($scores['technicalSkills'] < 70) {
            $recommendations[] = array(
                'priority' => 'high',
                'category' => 'Skills',
                'action' => 'Add missing technical skills to your CV',
                'details' => 'Include variations and related technologies you\'ve worked with'
            );
        }
        
        // Keyword recommendations
        if ($scores['keywords'] < 60) {
            $recommendations[] = array(
                'priority' => 'high',
                'category' => 'Keywords',
                'action' => 'Optimize keyword usage',
                'details' => 'Incorporate job-specific keywords naturally throughout your CV'
            );
        }
        
        // Experience recommendations
        if ($scores['experience'] < 70) {
            $recommendations[] = array(
                'priority' => 'medium',
                'category' => 'Experience',
                'action' => 'Emphasize relevant experience',
                'details' => 'Highlight achievements and responsibilities that align with the job requirements'
            );
        }
        
        // Responsibility recommendations
        if ($scores['responsibilities'] < 60) {
            $recommendations[] = array(
                'priority' => 'medium',
                'category' => 'Responsibilities',
                'action' => 'Align your experience descriptions',
                'details' => 'Mirror the language used in the job description for similar responsibilities'
            );
        }
        
        // General recommendations
        $recommendations[] = array(
            'priority' => 'low',
            'category' => 'Format',
            'action' => 'Ensure ATS compatibility',
            'details' => 'Use standard section headers and avoid complex formatting'
        );
        
        return $recommendations;
    }
    
    /**
     * Helper: Format area name
     */
    private function format_area_name($area) {
        $names = array(
            'technicalSkills' => 'Technical Skills',
            'experience' => 'Professional Experience',
            'education' => 'Education & Certifications',
            'softSkills' => 'Soft Skills',
            'keywords' => 'Keyword Optimization',
            'responsibilities' => 'Role Alignment',
            'culture' => 'Cultural Fit'
        );
        
        return $names[$area] ?? ucfirst($area);
    }
    
    /**
     * Helper: Get strength description
     */
    private function get_strength_description($area, $score, $cv_data, $job_analysis) {
        $descriptions = array(
            'technicalSkills' => 'Strong technical skill alignment with job requirements',
            'experience' => 'Excellent experience match for this position',
            'education' => 'Educational background meets or exceeds requirements',
            'softSkills' => 'Demonstrated soft skills align well with role needs',
            'keywords' => 'CV is well-optimized for ATS systems',
            'responsibilities' => 'Previous responsibilities closely match this role',
            'culture' => 'Good cultural and work style fit'
        );
        
        return $descriptions[$area] ?? 'Strong match in this area';
    }
    
    /**
     * Helper: Determine match level
     */
    private function determine_match_level($score) {
        if ($score >= 85) {
            return 'Excellent Match';
        } elseif ($score >= 70) {
            return 'Strong Match';
        } elseif ($score >= 55) {
            return 'Good Match';
        } elseif ($score >= 40) {
            return 'Fair Match';
        } else {
            return 'Weak Match';
        }
    }
    
    /**
     * Helper: Extract highest degree
     */
    private function extract_highest_degree($cv_education) {
        $degree_hierarchy = array(
            'PhD' => 5,
            'Doctorate' => 5,
            'Master\'s' => 4,
            'MBA' => 4,
            'Bachelor\'s' => 3,
            'Associate' => 2,
            'High School' => 1
        );
        
        $highest = 'High School';
        $highest_level = 1;
        
        foreach ($cv_education as $edu) {
            $degree_text = $edu['degree'] ?? '';
            foreach ($degree_hierarchy as $degree => $level) {
                if (stripos($degree_text, $degree) !== false && $level > $highest_level) {
                    $highest = $degree;
                    $highest_level = $level;
                }
            }
        }
        
        return $highest;
    }
    
    /**
     * Helper: Check relevant field of study
     */
    private function has_relevant_field($cv_education, $required_fields) {
        foreach ($cv_education as $edu) {
            $field = strtolower($edu['field'] ?? '');
            foreach ($required_fields as $required) {
                if (stripos($field, strtolower($required)) !== false) {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * Helper: Calculate certification match
     */
    private function calculate_certification_match($cv_certs, $required_certs) {
        if (empty($required_certs)) {
            return 100;
        }
        
        $matched = 0;
        $cv_certs_lower = array_map('strtolower', $cv_certs);
        
        foreach ($required_certs as $cert) {
            if (in_array(strtolower($cert), $cv_certs_lower)) {
                $matched++;
            }
        }
        
        return ($matched / count($required_certs)) * 100;
    }
    
    /**
     * Helper: Check industry experience
     */
    private function has_industry_experience($cv_experience, $required_industries) {
        $cv_text = strtolower(json_encode($cv_experience));
        
        foreach ($required_industries as $industry) {
            if (stripos($cv_text, strtolower($industry)) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Helper: Calculate relevant experience
     */
    private function calculate_relevant_experience($cv_experience, $required_experience) {
        $cv_text = strtolower(json_encode($cv_experience));
        $matched = 0;
        
        foreach ($required_experience as $req) {
            if (stripos($cv_text, strtolower($req)) !== false) {
                $matched++;
            }
        }
        
        return min(100, ($matched / max(1, count($required_experience))) * 100);
    }
}

} // End if (!class_exists('SFFC_Matching_Engine'))