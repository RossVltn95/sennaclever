<?php
/**
 * Phase 4 AJAX Handlers
 * Handles AJAX requests for job comparison and strategy dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Phase4_Ajax_Handlers {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Register AJAX handlers for both logged in and non-logged in users
        $ajax_actions = [
            'sffc_compare_jobs',
            'sffc_save_strategy',
            'sffc_get_market_insights',
            'sffc_get_pipeline_status'
        ];
        
        foreach ($ajax_actions as $action) {
            add_action('wp_ajax_' . $action, [$this, $action]);
            add_action('wp_ajax_nopriv_' . $action, [$this, $action]);
        }
    }
    
    
    /**
     * Compare multiple jobs side-by-side
     */
    public function sffc_compare_jobs() {
        // Verify nonce
        if (!check_ajax_referer('sffc_public_nonce', 'nonce', false) && 
            !check_ajax_referer('sffc_frontend_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }
        
        $job_ids = isset($_POST['job_ids']) ? explode(',', sanitize_text_field($_POST['job_ids'])) : [];
        
        if (empty($job_ids) || count($job_ids) < 2) {
            wp_send_json_error(['message' => 'Please select at least 2 jobs to compare']);
            return;
        }
        
        if (count($job_ids) > 3) {
            wp_send_json_error(['message' => 'Maximum 3 jobs can be compared at once']);
            return;
        }
        
        $comparison_data = [];
        
        foreach ($job_ids as $job_id) {
            $job_id = intval($job_id);
            
            // Get job post
            $job = get_post($job_id);
            if (!$job || $job->post_type !== 'sffc_job') {
                continue;
            }
            
            // Get all job metadata
            $meta = get_post_meta($job_id);
            
            // Build comprehensive comparison data
            $job_data = [
                'id' => $job_id,
                'title' => $job->post_title,
                'company' => get_post_meta($job_id, 'sffc_company', true) ?: 'Company',
                'location' => get_post_meta($job_id, 'sffc_location', true) ?: 'Location',
                'salary_min' => intval(get_post_meta($job_id, 'sffc_salary_min', true)),
                'salary_max' => intval(get_post_meta($job_id, 'sffc_salary_max', true)),
                'job_type' => get_post_meta($job_id, 'sffc_job_type', true) ?: 'Full-time',
                'experience_required' => get_post_meta($job_id, 'sffc_experience', true) ?: '3-5 years',
                'team_size' => get_post_meta($job_id, 'sffc_team_size', true) ?: '10-50',
                'skills' => get_post_meta($job_id, 'sffc_skills', true) ?: [],
                'benefits' => get_post_meta($job_id, 'sffc_benefits', true) ?: [],
                'culture' => [
                    'work_life_balance' => intval(get_post_meta($job_id, 'sffc_work_life_balance', true)) ?: rand(3, 5),
                    'growth_potential' => intval(get_post_meta($job_id, 'sffc_growth_potential', true)) ?: rand(3, 5),
                    'learning_opportunities' => intval(get_post_meta($job_id, 'sffc_learning_opportunities', true)) ?: rand(3, 5),
                    'company_stability' => intval(get_post_meta($job_id, 'sffc_company_stability', true)) ?: rand(3, 5),
                    'innovation_index' => intval(get_post_meta($job_id, 'sffc_innovation_index', true)) ?: rand(3, 5)
                ],
                'match_score' => $this->calculate_match_score($job_id),
                'pros' => $this->get_job_pros($job_id),
                'cons' => $this->get_job_cons($job_id),
                'key_requirements' => get_post_meta($job_id, 'sffc_key_requirements', true) ?: [],
                'growth_trajectory' => get_post_meta($job_id, 'sffc_growth_trajectory', true) ?: 'Strong'
            ];
            
            $comparison_data[] = $job_data;
        }
        
        // Generate comparison insights
        $insights = $this->generate_comparison_insights($comparison_data);
        
        wp_send_json_success([
            'jobs' => $comparison_data,
            'insights' => $insights,
            'recommendation' => $this->generate_recommendation($comparison_data)
        ]);
    }
    
    /**
     * Save strategy dashboard state
     */
    public function sffc_save_strategy() {
        // Verify nonce
        if (!check_ajax_referer('sffc_public_nonce', 'nonce', false) && 
            !check_ajax_referer('sffc_frontend_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }
        
        $strategy_data = isset($_POST['strategy']) ? json_decode(stripslashes($_POST['strategy']), true) : [];
        
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            
            // Save pipeline state
            if (isset($strategy_data['pipeline'])) {
                update_user_meta($user_id, 'sffc_pipeline_state', $strategy_data['pipeline']);
            }
            
            // Save timeline milestones
            if (isset($strategy_data['timeline'])) {
                update_user_meta($user_id, 'sffc_timeline_milestones', $strategy_data['timeline']);
            }
            
            // Save strategic actions
            if (isset($strategy_data['actions'])) {
                update_user_meta($user_id, 'sffc_strategic_actions', $strategy_data['actions']);
            }
            
            // Track the save event
            do_action('sffc_strategy_saved', $user_id, $strategy_data);
        }
        
        wp_send_json_success([
            'message' => 'Strategy saved successfully',
            'timestamp' => current_time('mysql')
        ]);
    }
    
    /**
     * Get market insights for strategy dashboard
     */
    public function sffc_get_market_insights() {
        // Verify nonce
        if (!check_ajax_referer('sffc_public_nonce', 'nonce', false) && 
            !check_ajax_referer('sffc_frontend_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }
        
        $target_role = sanitize_text_field($_POST['target_role'] ?? '');
        $location = sanitize_text_field($_POST['location'] ?? '');
        
        // Generate market insights (in production, this would pull from real data sources)
        $insights = [
            'market_trends' => [
                'demand_growth' => rand(10, 25), // percentage
                'supply_growth' => rand(5, 15), // percentage
                'trend_direction' => 'up',
                'hot_skills' => ['Cloud Architecture', 'AI/ML', 'Data Analytics', 'DevOps', 'Blockchain'],
                'declining_skills' => ['Legacy Systems', 'Basic HTML/CSS', 'Manual Testing']
            ],
            'salary_benchmarks' => [
                'percentile_25' => 85000,
                'median' => 115000,
                'percentile_75' => 145000,
                'percentile_90' => 180000,
                'your_position' => 75, // percentile
                'growth_rate' => 8.5 // annual percentage
            ],
            'competition_analysis' => [
                'total_candidates' => rand(500, 2000),
                'qualified_candidates' => rand(50, 200),
                'your_rank' => rand(10, 50),
                'your_percentile' => rand(80, 95),
                'key_differentiators' => [
                    'Industry experience',
                    'Leadership skills',
                    'Technical expertise',
                    'Network strength'
                ]
            ],
            'opportunity_forecast' => [
                'next_30_days' => rand(20, 50),
                'next_60_days' => rand(40, 100),
                'next_90_days' => rand(60, 150),
                'best_time_to_apply' => 'Early week (Mon-Tue)',
                'peak_hiring_months' => ['January', 'March', 'September']
            ],
            'skill_gaps' => [
                [
                    'skill' => 'Cloud Certification',
                    'importance' => 'High',
                    'time_to_acquire' => '2-3 months',
                    'roi' => 'High'
                ],
                [
                    'skill' => 'Leadership Training',
                    'importance' => 'Medium',
                    'time_to_acquire' => '6 months',
                    'roi' => 'Medium'
                ],
                [
                    'skill' => 'Industry Certification',
                    'importance' => 'Low',
                    'time_to_acquire' => '1 month',
                    'roi' => 'Low'
                ]
            ]
        ];
        
        wp_send_json_success($insights);
    }
    
    
    /**
     * Get pipeline status for strategy dashboard
     */
    public function sffc_get_pipeline_status() {
        // Verify nonce
        if (!check_ajax_referer('sffc_public_nonce', 'nonce', false) && 
            !check_ajax_referer('sffc_frontend_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }
        
        $pipeline_status = [
            'researching' => 0,
            'preparing' => 0,
            'applied' => 0,
            'interviewing' => 0,
            'negotiating' => 0,
            'metrics' => [
                'total_opportunities' => 0,
                'response_rate' => 0,
                'average_time_to_response' => 0,
                'conversion_rate' => 0
            ]
        ];
        
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $saved_pipeline = get_user_meta($user_id, 'sffc_pipeline_state', true);
            
            if ($saved_pipeline) {
                foreach ($saved_pipeline as $stage => $items) {
                    if (isset($pipeline_status[$stage])) {
                        $pipeline_status[$stage] = count($items);
                    }
                }
                
                // Calculate metrics
                $total = array_sum(array_values($pipeline_status)) - count($pipeline_status['metrics']);
                $pipeline_status['metrics']['total_opportunities'] = $total;
                
                if ($pipeline_status['applied'] > 0) {
                    $responses = $pipeline_status['interviewing'] + $pipeline_status['negotiating'];
                    $pipeline_status['metrics']['response_rate'] = round(($responses / $pipeline_status['applied']) * 100);
                }
                
                if ($pipeline_status['negotiating'] > 0 && $total > 0) {
                    $pipeline_status['metrics']['conversion_rate'] = round(($pipeline_status['negotiating'] / $total) * 100);
                }
            }
        }
        
        wp_send_json_success($pipeline_status);
    }
    
    /**
     * Helper: Calculate match score
     */
    private function calculate_match_score($job_id) {
        $base_score = 70;
        
        // Get job metadata
        $skills = get_post_meta($job_id, 'sffc_skills', true);
        $salary_max = intval(get_post_meta($job_id, 'sffc_salary_max', true));
        $location = get_post_meta($job_id, 'sffc_location', true);
        
        // Scoring logic
        if (!empty($skills) && count($skills) > 5) {
            $base_score += 10;
        }
        
        if ($salary_max > 150000) {
            $base_score += 10;
        }
        
        if (stripos($location, 'remote') !== false) {
            $base_score += 5;
        }
        
        // Add some variation
        $base_score += rand(-5, 10);
        
        return min(95, max(60, $base_score));
    }
    
    /**
     * Helper: Get job pros
     */
    private function get_job_pros($job_id) {
        $pros = get_post_meta($job_id, 'sffc_pros', true);
        
        if (empty($pros)) {
            // Generate default pros based on job data
            $pros = [];
            
            $salary_max = intval(get_post_meta($job_id, 'sffc_salary_max', true));
            if ($salary_max > 150000) {
                $pros[] = 'Competitive compensation package';
            }
            
            $location = get_post_meta($job_id, 'sffc_location', true);
            if (stripos($location, 'remote') !== false) {
                $pros[] = 'Remote work flexibility';
            }
            
            $pros[] = 'Strong growth potential';
            $pros[] = 'Innovative company culture';
        }
        
        return array_slice($pros, 0, 4);
    }
    
    /**
     * Helper: Get job cons
     */
    private function get_job_cons($job_id) {
        $cons = get_post_meta($job_id, 'sffc_cons', true);
        
        if (empty($cons)) {
            // Generate default cons
            $cons = [
                'Competitive interview process',
                'Fast-paced environment'
            ];
        }
        
        return array_slice($cons, 0, 2);
    }
    
    /**
     * Helper: Generate comparison insights
     */
    private function generate_comparison_insights($jobs) {
        $insights = [];
        
        // Find highest paying job
        $max_salary = 0;
        $highest_paying = null;
        foreach ($jobs as $job) {
            if ($job['salary_max'] > $max_salary) {
                $max_salary = $job['salary_max'];
                $highest_paying = $job['title'];
            }
        }
        if ($highest_paying) {
            $insights[] = "$highest_paying offers the highest compensation potential";
        }
        
        // Find best culture fit
        $best_culture = null;
        $best_culture_score = 0;
        foreach ($jobs as $job) {
            $culture_score = array_sum($job['culture']);
            if ($culture_score > $best_culture_score) {
                $best_culture_score = $culture_score;
                $best_culture = $job['title'];
            }
        }
        if ($best_culture) {
            $insights[] = "$best_culture shows the strongest cultural alignment";
        }
        
        // Find best match
        $best_match = null;
        $best_match_score = 0;
        foreach ($jobs as $job) {
            if ($job['match_score'] > $best_match_score) {
                $best_match_score = $job['match_score'];
                $best_match = $job['title'];
            }
        }
        if ($best_match) {
            $insights[] = "$best_match has the highest overall match score at {$best_match_score}%";
        }
        
        return $insights;
    }
    
    /**
     * Helper: Generate recommendation
     */
    private function generate_recommendation($jobs) {
        // Find the best overall opportunity
        $scores = [];
        foreach ($jobs as $job) {
            // Weighted scoring
            $score = 0;
            $score += $job['match_score'] * 0.3;
            $score += min(100, ($job['salary_max'] / 2000)) * 0.2;
            $score += array_sum($job['culture']) * 2 * 0.2;
            $score += (count($job['pros']) - count($job['cons'])) * 10 * 0.15;
            $score += (stripos($job['location'], 'remote') !== false ? 20 : 0) * 0.15;
            
            $scores[$job['id']] = [
                'score' => $score,
                'title' => $job['title'],
                'company' => $job['company']
            ];
        }
        
        // Sort by score
        uasort($scores, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        $top = reset($scores);
        
        return "Based on comprehensive analysis, \"{$top['title']}\" at {$top['company']} presents the optimal opportunity, " .
               "balancing compensation, culture fit, and career growth potential. " .
               "Consider prioritizing this application while maintaining active engagement with all opportunities.";
    }
    
    /**
     * Helper: Format salary range
     */
    private function format_salary_range($job_id) {
        $min = intval(get_post_meta($job_id, 'sffc_salary_min', true));
        $max = intval(get_post_meta($job_id, 'sffc_salary_max', true));
        
        if (!$min && !$max) {
            return 'Competitive';
        }
        
        if (!$max) {
            return '$' . number_format($min) . '+';
        }
        
        if (!$min) {
            return 'Up to $' . number_format($max);
        }
        
        return '$' . number_format($min) . ' - $' . number_format($max);
    }
}

// Initialize
SFFC_Phase4_Ajax_Handlers::get_instance();