<?php
/**
 * Match Display Frontend
 * Handles the display of match scores and badges on job cards
 * Integrates profile data with job matching algorithm
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Match_Display_Frontend {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add filters to modify job card display
        add_filter('sffc_job_card_data', [$this, 'add_match_data_to_job'], 10, 2);
        add_filter('sffc_job_card_html', [$this, 'add_match_badge_to_card'], 10, 2);
        
        // AJAX handlers for match calculation
        add_action('wp_ajax_nopriv_sffc_calculate_job_match', [$this, 'ajax_calculate_match']);
        add_action('wp_ajax_sffc_calculate_job_match', [$this, 'ajax_calculate_match']);
        
        add_action('wp_ajax_nopriv_sffc_get_job_matches_batch', [$this, 'ajax_get_matches_batch']);
        add_action('wp_ajax_sffc_get_job_matches_batch', [$this, 'ajax_get_matches_batch']);
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    /**
     * Enqueue CSS and JS for match display
     */
    public function enqueue_assets() {
        global $post;
        
        // Load on pages with opportunities
        if (is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'career_opportunities') ||
            has_shortcode($post->post_content, 'sffc_profile_builder')
        )) {
            // CSS for match badges
            wp_enqueue_style(
                'sffc-match-badges',
                SFFC_PLUGIN_URL . 'assets/css/match-badges.css',
                [],
                SFFC_VERSION
            );
            
            // JavaScript for match updates
            wp_enqueue_script(
                'sffc-match-updater',
                SFFC_PLUGIN_URL . 'assets/js/match-updater.js',
                ['jquery'],
                SFFC_VERSION,
                true
            );
            
            // Localize script
            wp_localize_script('sffc-match-updater', 'sffc_match', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sffc_match_nonce')
            ]);
        }
    }
    
    /**
     * Add match data to job array
     */
    public function add_match_data_to_job($job_data, $job_id) {
        // Get user profile
        $user_profile = $this->get_current_user_profile();
        
        if (empty($user_profile)) {
            // No profile, return base score
            $job_data['match_score'] = 0;
            $job_data['match_strength'] = 'No Profile';
            $job_data['match_reasons'] = [];
            return $job_data;
        }
        
        // Calculate match using Job Matcher
        if (class_exists('SFFC_Job_Matcher')) {
            $matcher = SFFC_Job_Matcher::get_instance();
            
            // Get job requirements
            $job_requirements = $this->extract_job_requirements($job_id);
            
            // Calculate match
            $match_result = $matcher->calculate_overall_match(
                $user_profile,
                $job_requirements,
                $job_id
            );
            
            $job_data['match_score'] = $match_result['overall_score'];
            $job_data['match_strength'] = $match_result['match_strength'];
            $job_data['match_reasons'] = $this->format_match_reasons($match_result);
            $job_data['skill_gaps'] = $match_result['gaps'] ?? [];
        } else {
            // Fallback simple matching
            $job_data['match_score'] = $this->calculate_simple_match($user_profile, $job_data);
            $job_data['match_strength'] = $this->get_match_strength($job_data['match_score']);
            $job_data['match_reasons'] = $this->generate_simple_reasons($job_data['match_score']);
        }
        
        return $job_data;
    }
    
    /**
     * Get current user profile
     */
    private function get_current_user_profile() {
        $session_id = $this->get_session_id();
        
        if (is_user_logged_in()) {
            global $wpdb;
            $user_id = get_current_user_id();
            
            // Get profile from database
            $profile_table = $wpdb->prefix . 'sffc_user_profiles';
            $profile = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $profile_table WHERE user_id = %d",
                $user_id
            ), ARRAY_A);
            
            if ($profile) {
                // Get skills
                $skills_table = $wpdb->prefix . 'sffc_user_skills';
                $skills = $wpdb->get_results($wpdb->prepare(
                    "SELECT skill_name, proficiency_level FROM $skills_table WHERE user_id = %d",
                    $user_id
                ), ARRAY_A);
                
                $profile['skills'] = $skills;
                $profile['user_id'] = $user_id;
                
                // Parse preferred locations
                if (!empty($profile['preferred_locations'])) {
                    $profile['preferred_locations'] = maybe_unserialize($profile['preferred_locations']);
                }
                
                return $profile;
            }
        } else {
            // Check session/transient for anonymous users
            $session_profile = get_transient('sffc_profile_' . $session_id);
            if ($session_profile) {
                return $session_profile;
            }
        }
        
        return [];
    }
    
    /**
     * Get or create session ID
     */
    private function get_session_id() {
        if (isset($_COOKIE['sffc_session_id'])) {
            return sanitize_text_field($_COOKIE['sffc_session_id']);
        }
        
        $session_id = 'anon_' . wp_generate_uuid4();
        setcookie('sffc_session_id', $session_id, time() + (30 * DAY_IN_SECONDS), '/');
        
        return $session_id;
    }
    
    /**
     * Extract job requirements from job post
     */
    private function extract_job_requirements($job_id) {
        $requirements = [
            'technical_skills' => [],
            'experience_level' => [],
            'location_requirements' => [],
            'industry' => ''
        ];
        
        // Get job meta data
        $skills = get_post_meta($job_id, 'sffc_skills', true);
        if (is_array($skills)) {
            foreach ($skills as $skill) {
                $requirements['technical_skills'][] = [
                    'skill' => $skill,
                    'required' => true,
                    'proficiency' => 'Intermediate'
                ];
            }
        }
        
        // Get location
        $location = get_post_meta($job_id, 'sffc_location', true);
        if ($location) {
            $requirements['location_requirements'][] = $location;
        }
        
        // Get experience requirements
        $experience = get_post_meta($job_id, 'sffc_experience_required', true);
        if ($experience) {
            $requirements['experience_level'] = [
                'min_years' => intval($experience),
                'preferred_years' => intval($experience) + 2
            ];
        }
        
        // Get industry
        $company = get_post_meta($job_id, 'sffc_company', true);
        $requirements['industry'] = $this->determine_industry($company);
        
        return $requirements;
    }
    
    /**
     * Determine industry from company name
     */
    private function determine_industry($company) {
        $company_lower = strtolower($company);
        
        // Investment banks
        if (strpos($company_lower, 'goldman') !== false ||
            strpos($company_lower, 'morgan stanley') !== false ||
            strpos($company_lower, 'jp morgan') !== false ||
            strpos($company_lower, 'barclays') !== false ||
            strpos($company_lower, 'citi') !== false) {
            return 'Investment Banking';
        }
        
        // Private equity
        if (strpos($company_lower, 'blackstone') !== false ||
            strpos($company_lower, 'kkr') !== false ||
            strpos($company_lower, 'apollo') !== false ||
            strpos($company_lower, 'carlyle') !== false) {
            return 'Private Equity';
        }
        
        // Asset management
        if (strpos($company_lower, 'blackrock') !== false ||
            strpos($company_lower, 'vanguard') !== false ||
            strpos($company_lower, 'fidelity') !== false) {
            return 'Asset Management';
        }
        
        return 'Financial Services';
    }
    
    /**
     * Calculate simple match score (fallback)
     */
    private function calculate_simple_match($profile, $job) {
        $score = 50; // Base score
        
        // Check skills match
        if (!empty($profile['skills']) && !empty($job['skills'])) {
            $user_skills = array_column($profile['skills'], 'skill_name');
            $job_skills = is_array($job['skills']) ? $job['skills'] : [];
            
            $matched = array_intersect($user_skills, $job_skills);
            if (count($matched) > 0) {
                $skill_match = (count($matched) / count($job_skills)) * 30;
                $score += $skill_match;
            }
        }
        
        // Check location match
        if (!empty($profile['preferred_locations']) && !empty($job['location'])) {
            $locations = is_array($profile['preferred_locations']) ? 
                       $profile['preferred_locations'] : 
                       [$profile['preferred_locations']];
            
            foreach ($locations as $loc) {
                if (stripos($job['location'], $loc) !== false) {
                    $score += 10;
                    break;
                }
            }
        }
        
        // Check salary match
        if (!empty($profile['salary_target_min']) && !empty($job['salary_min'])) {
            if ($job['salary_min'] >= $profile['salary_target_min']) {
                $score += 10;
            }
        }
        
        return min(100, max(0, round($score)));
    }
    
    /**
     * Get match strength label
     */
    private function get_match_strength($score) {
        if ($score >= 90) return 'Perfect Match';
        if ($score >= 80) return 'Very Strong';
        if ($score >= 70) return 'Strong Match';
        if ($score >= 60) return 'Good Match';
        if ($score >= 50) return 'Moderate Match';
        return 'Stretch Opportunity';
    }
    
    /**
     * Generate simple match reasons
     */
    private function generate_simple_reasons($score) {
        $reasons = [];
        
        if ($score >= 80) {
            $reasons[] = 'Excellent skills alignment';
            $reasons[] = 'Strong career fit';
            $reasons[] = 'Great progression opportunity';
        } elseif ($score >= 60) {
            $reasons[] = 'Good skills match';
            $reasons[] = 'Career development potential';
            $reasons[] = 'Relevant experience valued';
        } else {
            $reasons[] = 'Transferable skills';
            $reasons[] = 'Growth opportunity';
            $reasons[] = 'New sector experience';
        }
        
        return array_slice($reasons, 0, 3);
    }
    
    /**
     * Format match reasons from matcher result
     */
    private function format_match_reasons($match_result) {
        $reasons = [];
        
        // Check category scores
        if (!empty($match_result['category_scores'])) {
            if ($match_result['category_scores']['technical_skills'] >= 80) {
                $reasons[] = 'Excellent technical skills match';
            }
            if ($match_result['category_scores']['experience'] >= 80) {
                $reasons[] = 'Perfect experience level';
            }
            if ($match_result['category_scores']['industry_domain'] >= 80) {
                $reasons[] = 'Strong industry alignment';
            }
        }
        
        // Add recommendations if no strong reasons
        if (empty($reasons) && !empty($match_result['recommendations'])) {
            $reasons = array_slice($match_result['recommendations'], 0, 3);
        }
        
        // Default reasons
        if (empty($reasons)) {
            $reasons = $this->generate_simple_reasons($match_result['overall_score']);
        }
        
        return $reasons;
    }
    
    /**
     * AJAX: Calculate match for single job
     */
    public function ajax_calculate_match() {
        check_ajax_referer('sffc_match_nonce', 'nonce');
        
        $job_id = intval($_POST['job_id'] ?? 0);
        
        if (!$job_id) {
            wp_send_json_error(['message' => 'Invalid job ID']);
            return;
        }
        
        // Get job data
        $job_data = [
            'id' => $job_id,
            'skills' => get_post_meta($job_id, 'sffc_skills', true),
            'location' => get_post_meta($job_id, 'sffc_location', true),
            'salary_min' => get_post_meta($job_id, 'sffc_salary_min', true),
            'salary_max' => get_post_meta($job_id, 'sffc_salary_max', true)
        ];
        
        // Add match data
        $job_with_match = $this->add_match_data_to_job($job_data, $job_id);
        
        wp_send_json_success([
            'match_score' => $job_with_match['match_score'],
            'match_strength' => $job_with_match['match_strength'],
            'match_reasons' => $job_with_match['match_reasons']
        ]);
    }
    
    /**
     * AJAX: Get matches for multiple jobs
     */
    public function ajax_get_matches_batch() {
        check_ajax_referer('sffc_match_nonce', 'nonce');
        
        $job_ids = array_map('intval', $_POST['job_ids'] ?? []);
        
        if (empty($job_ids)) {
            wp_send_json_error(['message' => 'No job IDs provided']);
            return;
        }
        
        $matches = [];
        
        foreach ($job_ids as $job_id) {
            $job_data = ['id' => $job_id];
            $job_with_match = $this->add_match_data_to_job($job_data, $job_id);
            
            $matches[$job_id] = [
                'score' => $job_with_match['match_score'],
                'strength' => $job_with_match['match_strength'],
                'reasons' => $job_with_match['match_reasons']
            ];
        }
        
        wp_send_json_success(['matches' => $matches]);
    }
    
    /**
     * Add match badge HTML to job card
     */
    public function add_match_badge_to_card($html, $job_data) {
        if (empty($job_data['match_score'])) {
            return $html;
        }
        
        $score = $job_data['match_score'];
        $strength = $job_data['match_strength'];
        $class = $this->get_match_class($score);
        
        $badge_html = sprintf(
            '<div class="sffc-match-indicator %s" data-job-id="%d">
                <span class="sffc-match-score">%d%%</span>
                <span class="sffc-match-label">%s</span>
            </div>',
            esc_attr($class),
            esc_attr($job_data['id']),
            esc_attr($score),
            esc_html($strength)
        );
        
        // Insert badge into card HTML
        // This would need to be adjusted based on actual card structure
        $html = str_replace('<div class="sffc-company-header">', 
                           '<div class="sffc-company-header">' . $badge_html, 
                           $html);
        
        return $html;
    }
    
    /**
     * Get CSS class for match score
     */
    private function get_match_class($score) {
        if ($score >= 90) return 'perfect-match';
        if ($score >= 80) return 'very-strong-match';
        if ($score >= 70) return 'strong-match';
        if ($score >= 60) return 'good-match';
        if ($score >= 50) return 'moderate-match';
        return 'stretch-match';
    }
}

// Initialize
SFFC_Match_Display_Frontend::get_instance();