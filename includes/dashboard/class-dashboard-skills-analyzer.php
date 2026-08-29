<?php
/**
 * Dashboard Skills Analyzer
 *
 * Analyzes user skills against market demand, identifies gaps,
 * and provides upskilling recommendations.
 *
 * @package SFFC_Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Dashboard_Skills_Analyzer {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Cache duration in seconds (2 hours)
     */
    const CACHE_DURATION = 7200;

    /**
     * Skills database with demand scores and categories
     */
    private $skills_database = array(
        // Technical Skills - Finance
        'Financial Modeling' => array('category' => 'Technical', 'base_demand' => 88, 'trend' => 'up', 'salary_impact' => 15),
        'Valuation' => array('category' => 'Technical', 'base_demand' => 85, 'trend' => 'stable', 'salary_impact' => 12),
        'DCF Analysis' => array('category' => 'Technical', 'base_demand' => 82, 'trend' => 'stable', 'salary_impact' => 10),
        'LBO Modeling' => array('category' => 'Technical', 'base_demand' => 78, 'trend' => 'up', 'salary_impact' => 18),
        'M&A' => array('category' => 'Technical', 'base_demand' => 80, 'trend' => 'up', 'salary_impact' => 15),
        'Due Diligence' => array('category' => 'Technical', 'base_demand' => 75, 'trend' => 'stable', 'salary_impact' => 10),
        'Credit Analysis' => array('category' => 'Technical', 'base_demand' => 72, 'trend' => 'stable', 'salary_impact' => 8),
        'Risk Management' => array('category' => 'Technical', 'base_demand' => 76, 'trend' => 'up', 'salary_impact' => 12),
        'Portfolio Management' => array('category' => 'Technical', 'base_demand' => 74, 'trend' => 'stable', 'salary_impact' => 10),
        'Derivatives' => array('category' => 'Technical', 'base_demand' => 68, 'trend' => 'stable', 'salary_impact' => 12),
        'Fixed Income' => array('category' => 'Technical', 'base_demand' => 70, 'trend' => 'stable', 'salary_impact' => 10),
        'Equity Research' => array('category' => 'Technical', 'base_demand' => 72, 'trend' => 'down', 'salary_impact' => 8),
        'Accounting' => array('category' => 'Technical', 'base_demand' => 80, 'trend' => 'stable', 'salary_impact' => 8),
        'Financial Reporting' => array('category' => 'Technical', 'base_demand' => 75, 'trend' => 'stable', 'salary_impact' => 6),

        // Technology Skills
        'Excel' => array('category' => 'Technology', 'base_demand' => 95, 'trend' => 'stable', 'salary_impact' => 5),
        'VBA' => array('category' => 'Technology', 'base_demand' => 70, 'trend' => 'down', 'salary_impact' => 5),
        'Python' => array('category' => 'Technology', 'base_demand' => 85, 'trend' => 'up', 'salary_impact' => 15),
        'SQL' => array('category' => 'Technology', 'base_demand' => 82, 'trend' => 'up', 'salary_impact' => 10),
        'R' => array('category' => 'Technology', 'base_demand' => 65, 'trend' => 'stable', 'salary_impact' => 8),
        'Tableau' => array('category' => 'Technology', 'base_demand' => 72, 'trend' => 'up', 'salary_impact' => 8),
        'Power BI' => array('category' => 'Technology', 'base_demand' => 75, 'trend' => 'up', 'salary_impact' => 8),
        'Bloomberg Terminal' => array('category' => 'Technology', 'base_demand' => 78, 'trend' => 'stable', 'salary_impact' => 5),
        'FactSet' => array('category' => 'Technology', 'base_demand' => 68, 'trend' => 'stable', 'salary_impact' => 5),
        'Capital IQ' => array('category' => 'Technology', 'base_demand' => 70, 'trend' => 'stable', 'salary_impact' => 5),
        'Refinitiv' => array('category' => 'Technology', 'base_demand' => 65, 'trend' => 'stable', 'salary_impact' => 5),
        'Machine Learning' => array('category' => 'Technology', 'base_demand' => 70, 'trend' => 'up', 'salary_impact' => 20),
        'Data Science' => array('category' => 'Technology', 'base_demand' => 72, 'trend' => 'up', 'salary_impact' => 18),
        'AWS' => array('category' => 'Technology', 'base_demand' => 60, 'trend' => 'up', 'salary_impact' => 12),
        'Alteryx' => array('category' => 'Technology', 'base_demand' => 55, 'trend' => 'up', 'salary_impact' => 8),

        // Soft Skills
        'Communication' => array('category' => 'Soft Skills', 'base_demand' => 90, 'trend' => 'stable', 'salary_impact' => 5),
        'Presentation' => array('category' => 'Soft Skills', 'base_demand' => 85, 'trend' => 'stable', 'salary_impact' => 5),
        'Leadership' => array('category' => 'Soft Skills', 'base_demand' => 80, 'trend' => 'stable', 'salary_impact' => 10),
        'Teamwork' => array('category' => 'Soft Skills', 'base_demand' => 88, 'trend' => 'stable', 'salary_impact' => 3),
        'Problem Solving' => array('category' => 'Soft Skills', 'base_demand' => 92, 'trend' => 'stable', 'salary_impact' => 5),
        'Analytical Thinking' => array('category' => 'Soft Skills', 'base_demand' => 90, 'trend' => 'stable', 'salary_impact' => 5),
        'Attention to Detail' => array('category' => 'Soft Skills', 'base_demand' => 88, 'trend' => 'stable', 'salary_impact' => 3),
        'Time Management' => array('category' => 'Soft Skills', 'base_demand' => 85, 'trend' => 'stable', 'salary_impact' => 3),
        'Stakeholder Management' => array('category' => 'Soft Skills', 'base_demand' => 78, 'trend' => 'up', 'salary_impact' => 8),
        'Client Relations' => array('category' => 'Soft Skills', 'base_demand' => 80, 'trend' => 'stable', 'salary_impact' => 8),

        // Certifications (treated as skills)
        'CFA' => array('category' => 'Certification', 'base_demand' => 75, 'trend' => 'stable', 'salary_impact' => 15),
        'FRM' => array('category' => 'Certification', 'base_demand' => 65, 'trend' => 'up', 'salary_impact' => 12),
        'CPA' => array('category' => 'Certification', 'base_demand' => 70, 'trend' => 'stable', 'salary_impact' => 12),
        'CAIA' => array('category' => 'Certification', 'base_demand' => 55, 'trend' => 'stable', 'salary_impact' => 10),
        'Series 7' => array('category' => 'Certification', 'base_demand' => 60, 'trend' => 'stable', 'salary_impact' => 5),
        'Series 63' => array('category' => 'Certification', 'base_demand' => 55, 'trend' => 'stable', 'salary_impact' => 3),
    );

    /**
     * Course recommendations database
     */
    private $courses_database = array(
        'Python' => array(
            array('name' => 'Python for Finance - Coursera', 'provider' => 'Coursera', 'duration' => '4 weeks', 'level' => 'Beginner'),
            array('name' => 'DataCamp Python for Data Science', 'provider' => 'DataCamp', 'duration' => '20 hours', 'level' => 'Beginner'),
        ),
        'SQL' => array(
            array('name' => 'SQL for Data Analysis', 'provider' => 'Udacity', 'duration' => '4 weeks', 'level' => 'Beginner'),
            array('name' => 'DataCamp SQL Fundamentals', 'provider' => 'DataCamp', 'duration' => '15 hours', 'level' => 'Beginner'),
        ),
        'Financial Modeling' => array(
            array('name' => 'Financial Modeling & Valuation', 'provider' => 'Wall Street Prep', 'duration' => '40 hours', 'level' => 'Intermediate'),
            array('name' => 'Breaking Into Wall Street Modeling', 'provider' => 'BIWS', 'duration' => '50 hours', 'level' => 'Intermediate'),
        ),
        'LBO Modeling' => array(
            array('name' => 'LBO Modeling Course', 'provider' => 'Wall Street Prep', 'duration' => '20 hours', 'level' => 'Advanced'),
            array('name' => 'Private Equity Modeling', 'provider' => 'BIWS', 'duration' => '30 hours', 'level' => 'Advanced'),
        ),
        'Power BI' => array(
            array('name' => 'Microsoft Power BI Data Analyst', 'provider' => 'Microsoft Learn', 'duration' => '8 weeks', 'level' => 'Beginner'),
            array('name' => 'Power BI Essential Training', 'provider' => 'LinkedIn Learning', 'duration' => '5 hours', 'level' => 'Beginner'),
        ),
        'Tableau' => array(
            array('name' => 'Tableau Desktop Specialist', 'provider' => 'Tableau', 'duration' => '6 weeks', 'level' => 'Beginner'),
            array('name' => 'Data Visualization with Tableau', 'provider' => 'Coursera', 'duration' => '5 weeks', 'level' => 'Beginner'),
        ),
        'Machine Learning' => array(
            array('name' => 'Machine Learning by Stanford', 'provider' => 'Coursera', 'duration' => '11 weeks', 'level' => 'Intermediate'),
            array('name' => 'ML for Trading', 'provider' => 'Udacity', 'duration' => '4 months', 'level' => 'Advanced'),
        ),
        'CFA' => array(
            array('name' => 'CFA Level I Prep', 'provider' => 'Kaplan Schweser', 'duration' => '300 hours', 'level' => 'Advanced'),
            array('name' => 'CFA Program', 'provider' => 'CFA Institute', 'duration' => '300+ hours', 'level' => 'Advanced'),
        ),
        'FRM' => array(
            array('name' => 'FRM Part I Prep', 'provider' => 'Kaplan Schweser', 'duration' => '200 hours', 'level' => 'Advanced'),
            array('name' => 'FRM Certification', 'provider' => 'GARP', 'duration' => '200+ hours', 'level' => 'Advanced'),
        ),
    );

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Initialize
    }

    /**
     * Get complete skills analysis for user
     */
    public function get_skills_analysis($user_id) {
        $cache_key = 'sffc_skills_analysis_' . $user_id;
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $user_skills = $this->get_user_skills($user_id);
        $user_profile = $this->get_user_profile($user_id);

        $analysis = array(
            'skills' => $this->analyze_user_skills($user_skills),
            'gaps' => $this->identify_skill_gaps($user_skills, $user_profile),
            'recommendations' => $this->generate_recommendations($user_skills, $user_profile),
            'radar_data' => $this->generate_radar_data($user_skills),
            'summary' => $this->generate_summary($user_skills, $user_profile),
        );

        set_transient($cache_key, $analysis, self::CACHE_DURATION);

        return $analysis;
    }

    /**
     * Analyze user's skills with demand data
     */
    private function analyze_user_skills($user_skills) {
        $analyzed = array();

        foreach ($user_skills as $skill) {
            $skill_name = is_array($skill) ? ($skill['skill_name'] ?? $skill['name'] ?? '') : $skill;
            $skill_name = trim($skill_name);

            if (empty($skill_name)) {
                continue;
            }

            // Find matching skill in database
            $skill_data = $this->find_skill_match($skill_name);

            if ($skill_data) {
                // Calculate current demand with market adjustments
                $demand = $this->calculate_current_demand($skill_data);

                $analyzed[] = array(
                    'name' => $skill_name,
                    'demand' => $demand,
                    'trend' => $skill_data['trend'],
                    'category' => $skill_data['category'],
                    'salary_impact' => $skill_data['salary_impact'],
                );
            } else {
                // Unknown skill - assign moderate demand
                $analyzed[] = array(
                    'name' => $skill_name,
                    'demand' => 50,
                    'trend' => 'stable',
                    'category' => 'Other',
                    'salary_impact' => 5,
                );
            }
        }

        // Sort by demand (highest first)
        usort($analyzed, function($a, $b) {
            return $b['demand'] - $a['demand'];
        });

        return $analyzed;
    }

    /**
     * Find matching skill in database (fuzzy matching)
     */
    private function find_skill_match($skill_name) {
        $skill_lower = strtolower(trim($skill_name));

        // Direct match
        foreach ($this->skills_database as $name => $data) {
            if (strtolower($name) === $skill_lower) {
                return $data;
            }
        }

        // Partial match
        foreach ($this->skills_database as $name => $data) {
            if (strpos(strtolower($name), $skill_lower) !== false ||
                strpos($skill_lower, strtolower($name)) !== false) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Calculate current demand with market adjustments
     */
    private function calculate_current_demand($skill_data) {
        $base = $skill_data['base_demand'];

        // Adjust based on trend
        switch ($skill_data['trend']) {
            case 'up':
                $base += rand(3, 8);
                break;
            case 'down':
                $base -= rand(3, 8);
                break;
        }

        // Add slight randomization for realism
        $base += rand(-2, 2);

        return max(0, min(100, $base));
    }

    /**
     * Identify skill gaps based on market demand
     */
    private function identify_skill_gaps($user_skills, $user_profile) {
        $user_skill_names = array_map(function($s) {
            return strtolower(is_array($s) ? ($s['skill_name'] ?? $s['name'] ?? '') : $s);
        }, $user_skills);

        $gaps = array();
        $industries = (array)($user_profile['preferred_industries'] ?? array());

        // Get high-demand skills user doesn't have
        foreach ($this->skills_database as $skill_name => $skill_data) {
            $skill_lower = strtolower($skill_name);

            // Skip if user already has this skill
            $has_skill = false;
            foreach ($user_skill_names as $user_skill) {
                if (strpos($skill_lower, $user_skill) !== false ||
                    strpos($user_skill, $skill_lower) !== false) {
                    $has_skill = true;
                    break;
                }
            }

            if ($has_skill) {
                continue;
            }

            // Only include high-demand skills
            if ($skill_data['base_demand'] < 70) {
                continue;
            }

            // Calculate relevance score based on user's target industries
            $relevance = $this->calculate_skill_relevance($skill_name, $skill_data, $industries);

            if ($relevance > 0) {
                $gaps[] = array(
                    'skill' => $skill_name,
                    'demand' => $skill_data['base_demand'],
                    'trend' => $skill_data['trend'],
                    'category' => $skill_data['category'],
                    'salary_impact' => $skill_data['salary_impact'],
                    'relevance' => $relevance,
                    'importance' => $this->calculate_importance($skill_data, $relevance),
                );
            }
        }

        // Sort by importance
        usort($gaps, function($a, $b) {
            $score_a = $a['importance'] === 'High' ? 3 : ($a['importance'] === 'Medium' ? 2 : 1);
            $score_b = $b['importance'] === 'High' ? 3 : ($b['importance'] === 'Medium' ? 2 : 1);
            if ($score_a === $score_b) {
                return $b['demand'] - $a['demand'];
            }
            return $score_b - $score_a;
        });

        return array_slice($gaps, 0, 10);
    }

    /**
     * Calculate skill relevance to user's target industries
     */
    private function calculate_skill_relevance($skill_name, $skill_data, $industries) {
        $relevance = 50; // Base relevance

        // Industry-specific relevance
        $industry_skills = array(
            'Investment Banking' => array('Financial Modeling', 'Valuation', 'M&A', 'DCF Analysis', 'Excel', 'PowerPoint'),
            'Private Equity' => array('LBO Modeling', 'Due Diligence', 'Financial Modeling', 'M&A', 'Portfolio Management'),
            'Hedge Fund' => array('Python', 'Machine Learning', 'Derivatives', 'Risk Management', 'Bloomberg Terminal'),
            'Asset Management' => array('Portfolio Management', 'CFA', 'Equity Research', 'Fixed Income', 'Risk Management'),
            'Consulting' => array('Excel', 'PowerPoint', 'Problem Solving', 'Communication', 'Data Science'),
            'FinTech' => array('Python', 'SQL', 'Machine Learning', 'AWS', 'Data Science'),
        );

        foreach ($industries as $industry) {
            if (isset($industry_skills[$industry])) {
                if (in_array($skill_name, $industry_skills[$industry])) {
                    $relevance += 30;
                }
            }
        }

        // Trending skills get bonus
        if ($skill_data['trend'] === 'up') {
            $relevance += 10;
        }

        // High salary impact gets bonus
        if ($skill_data['salary_impact'] >= 15) {
            $relevance += 10;
        }

        return min(100, $relevance);
    }

    /**
     * Calculate importance level
     */
    private function calculate_importance($skill_data, $relevance) {
        $score = ($skill_data['base_demand'] * 0.4) + ($relevance * 0.4) + ($skill_data['salary_impact'] * 0.2);

        if ($score >= 70) {
            return 'High';
        } elseif ($score >= 50) {
            return 'Medium';
        }
        return 'Low';
    }

    /**
     * Generate upskilling recommendations
     */
    private function generate_recommendations($user_skills, $user_profile) {
        $gaps = $this->identify_skill_gaps($user_skills, $user_profile);
        $recommendations = array();

        foreach (array_slice($gaps, 0, 5) as $gap) {
            $skill = $gap['skill'];
            $courses = $this->courses_database[$skill] ?? array();

            $rec = array(
                'skill' => $skill,
                'importance' => $gap['importance'],
                'salary_uplift' => '+' . $gap['salary_impact'] . '%',
                'courses' => array_slice($courses, 0, 2),
                'time_estimate' => $this->estimate_learning_time($skill),
            );

            // Add certification info if applicable
            if ($gap['category'] === 'Certification') {
                $rec['type'] = 'certification';
                $rec['description'] = 'Professional certification that demonstrates expertise';
            } else {
                $rec['type'] = 'skill';
                $rec['description'] = $this->get_skill_description($skill, $gap);
            }

            $recommendations[] = $rec;
        }

        return $recommendations;
    }

    /**
     * Estimate learning time for a skill
     */
    private function estimate_learning_time($skill) {
        $times = array(
            'Python' => '2-3 months',
            'SQL' => '1-2 months',
            'Financial Modeling' => '2-4 months',
            'LBO Modeling' => '1-2 months',
            'Power BI' => '1-2 months',
            'Tableau' => '1-2 months',
            'Machine Learning' => '4-6 months',
            'CFA' => '18-24 months',
            'FRM' => '6-12 months',
        );

        return $times[$skill] ?? '2-3 months';
    }

    /**
     * Get skill description
     */
    private function get_skill_description($skill, $gap) {
        $descriptions = array(
            'Python' => 'Essential programming language for data analysis and automation in finance',
            'SQL' => 'Database querying skill critical for data-driven decision making',
            'Financial Modeling' => 'Core skill for valuation and financial analysis roles',
            'LBO Modeling' => 'Specialized modeling skill highly valued in private equity',
            'Power BI' => 'Business intelligence tool for creating interactive dashboards',
            'Machine Learning' => 'Emerging skill for quantitative and technology-focused roles',
        );

        if (isset($descriptions[$skill])) {
            return $descriptions[$skill];
        }

        if ($gap['trend'] === 'up') {
            return 'Trending skill with growing demand in the market';
        }

        return 'In-demand skill that can enhance your profile';
    }

    /**
     * Generate radar chart data for skill categories
     */
    private function generate_radar_data($user_skills) {
        $categories = array(
            'Technical' => array('user' => 0, 'market' => 80, 'count' => 0),
            'Technology' => array('user' => 0, 'market' => 75, 'count' => 0),
            'Soft Skills' => array('user' => 0, 'market' => 85, 'count' => 0),
            'Certification' => array('user' => 0, 'market' => 70, 'count' => 0),
        );

        // Calculate user's score in each category
        foreach ($user_skills as $skill) {
            $skill_name = is_array($skill) ? ($skill['skill_name'] ?? $skill['name'] ?? '') : $skill;
            $skill_data = $this->find_skill_match($skill_name);

            if ($skill_data && isset($categories[$skill_data['category']])) {
                $cat = $skill_data['category'];
                $categories[$cat]['user'] += $skill_data['base_demand'];
                $categories[$cat]['count']++;
            }
        }

        // Average the scores
        foreach ($categories as $cat => &$data) {
            if ($data['count'] > 0) {
                $data['user'] = round($data['user'] / $data['count']);
            }
        }

        return array(
            'labels' => array_keys($categories),
            'datasets' => array(
                array(
                    'label' => 'Your Skills',
                    'data' => array_column($categories, 'user'),
                    'backgroundColor' => 'rgba(30, 58, 95, 0.2)',
                    'borderColor' => '#1e3a5f',
                    'pointBackgroundColor' => '#1e3a5f',
                ),
                array(
                    'label' => 'Market Demand',
                    'data' => array_column($categories, 'market'),
                    'backgroundColor' => 'rgba(5, 150, 105, 0.2)',
                    'borderColor' => '#059669',
                    'pointBackgroundColor' => '#059669',
                ),
            ),
        );
    }

    /**
     * Generate skills summary
     */
    private function generate_summary($user_skills, $user_profile) {
        $analyzed = $this->analyze_user_skills($user_skills);

        $high_demand = count(array_filter($analyzed, function($s) {
            return $s['demand'] >= 70;
        }));

        $trending = count(array_filter($analyzed, function($s) {
            return $s['trend'] === 'up';
        }));

        $total = count($analyzed);

        $total_salary_impact = array_sum(array_column($analyzed, 'salary_impact'));

        return array(
            'total_skills' => $total,
            'high_demand_skills' => $high_demand,
            'trending_skills' => $trending,
            'estimated_salary_premium' => $total_salary_impact . '%',
            'strongest_category' => $this->get_strongest_category($analyzed),
            'weakest_category' => $this->get_weakest_category($analyzed, $user_profile),
        );
    }

    /**
     * Get user's strongest skill category
     */
    private function get_strongest_category($analyzed_skills) {
        $categories = array();

        foreach ($analyzed_skills as $skill) {
            $cat = $skill['category'];
            if (!isset($categories[$cat])) {
                $categories[$cat] = array('total' => 0, 'count' => 0);
            }
            $categories[$cat]['total'] += $skill['demand'];
            $categories[$cat]['count']++;
        }

        $strongest = '';
        $highest_avg = 0;

        foreach ($categories as $cat => $data) {
            $avg = $data['count'] > 0 ? $data['total'] / $data['count'] : 0;
            if ($avg > $highest_avg) {
                $highest_avg = $avg;
                $strongest = $cat;
            }
        }

        return $strongest ?: 'Technical';
    }

    /**
     * Get weakest category that needs improvement
     */
    private function get_weakest_category($analyzed_skills, $user_profile) {
        $categories = array(
            'Technical' => 0,
            'Technology' => 0,
            'Soft Skills' => 0,
            'Certification' => 0,
        );

        foreach ($analyzed_skills as $skill) {
            if (isset($categories[$skill['category']])) {
                $categories[$skill['category']]++;
            }
        }

        // Find category with least skills
        asort($categories);
        $weakest = array_key_first($categories);

        return $weakest;
    }

    /**
     * Get user skills from database
     */
    private function get_user_skills($user_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_user_skills';

        $skills = $wpdb->get_results($wpdb->prepare(
            "SELECT skill_name, proficiency_level FROM $table WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        return $skills ?: array();
    }

    /**
     * Get user profile
     */
    private function get_user_profile($user_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_user_profiles';

        $profile = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        if ($profile) {
            // Decode JSON fields
            $json_fields = array('preferred_industries', 'preferred_locations', 'certifications');
            foreach ($json_fields as $field) {
                if (!empty($profile[$field]) && is_string($profile[$field])) {
                    $decoded = json_decode($profile[$field], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $profile[$field] = $decoded;
                    }
                }
            }
        }

        return $profile ?: array();
    }

    /**
     * Clear skills analysis cache for user
     */
    public function clear_cache($user_id) {
        delete_transient('sffc_skills_analysis_' . $user_id);
    }

    /**
     * Get trending skills in the market
     */
    public function get_trending_skills($limit = 10) {
        $trending = array();

        foreach ($this->skills_database as $name => $data) {
            if ($data['trend'] === 'up' && $data['base_demand'] >= 70) {
                $trending[] = array(
                    'name' => $name,
                    'demand' => $data['base_demand'],
                    'category' => $data['category'],
                    'salary_impact' => $data['salary_impact'],
                );
            }
        }

        // Sort by demand
        usort($trending, function($a, $b) {
            return $b['demand'] - $a['demand'];
        });

        return array_slice($trending, 0, $limit);
    }
}

// Initialize
SFFC_Dashboard_Skills_Analyzer::get_instance();
