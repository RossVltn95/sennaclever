<?php

/**
 * Automatic Message Sender
 * Sends personalized job match notifications via Emily Bradshaw (user_id: 28134)
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Automatic_Message_Sender
{

    private static $instance = null;
    private $emily_id = 28134;
    private $daily_message_limit = 5;
    private $min_match_score = 70.0;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Hook into WordPress cron events
        add_action('sffc_process_user_matches', [$this, 'process_user_matches']);
        add_action('sffc_process_job_matches', [$this, 'process_job_matches']);

        // Schedule daily cleanup
        add_action('sffc_daily_match_cleanup', [$this, 'cleanup_old_matches']);

        // Register cron schedules if not already scheduled
        if (!wp_next_scheduled('sffc_daily_match_cleanup')) {
            wp_schedule_event(time(), 'daily', 'sffc_daily_match_cleanup');
        }

        // AJAX handlers for frontend interaction
        add_action('wp_ajax_sffc_respond_to_match', [$this, 'handle_match_response']);
        add_action('wp_ajax_sffc_get_match_details', [$this, 'get_match_details']);
    }

    /**
     * Process matches for a specific user (triggered after CV upload)
     */
    public function process_user_matches($user_id)
    {
        try {
            // Check if user has reached daily limit
            if ($this->has_reached_daily_limit($user_id)) {
                error_log("User $user_id has reached daily message limit");
                return;
            }

            // Get user profile and CV data
            $user_profile = $this->get_comprehensive_user_profile($user_id);
            if (!$user_profile) {
                error_log("No profile found for user $user_id");
                return;
            }

            // Get recent jobs that might match
            $recent_jobs = $this->get_recent_jobs();
            $matches_sent = 0;

            foreach ($recent_jobs as $job) {
                if ($matches_sent >= $this->daily_message_limit) {
                    break;
                }

                // Skip if already sent
                $cv_processor = SFFC_CV_Match_Processor::get_instance();
                if ($cv_processor->is_match_already_sent($user_id, $job->ID)) {
                    continue;
                }

                // Calculate match score
                $match_result = $this->calculate_enhanced_match($user_profile, $job);

                if ($match_result && $match_result['overall_score'] >= $this->min_match_score) {
                    $success = $this->send_match_notification($user_id, $job, $match_result);
                    if ($success) {
                        $matches_sent++;
                    }
                }
            }

            error_log("Processed matches for user $user_id: $matches_sent matches sent");
        } catch (Exception $e) {
            error_log("Error processing user matches for $user_id: " . $e->getMessage());
        }
    }

    /**
     * Process matches for a new job (triggered when job is published)
     */
    public function process_job_matches($job_id)
    {
        try {
            $job = get_post($job_id);
            if (!$job || $job->post_type !== 'sffc_job') {
                return;
            }

            // Get all users with complete profiles
            $eligible_users = $this->get_eligible_users();
            $matches_sent = 0;

            foreach ($eligible_users as $user_id) {
                // Check daily limit for user
                if ($this->has_reached_daily_limit($user_id)) {
                    continue;
                }

                // Skip if already sent
                $cv_processor = SFFC_CV_Match_Processor::get_instance();
                if ($cv_processor->is_match_already_sent($user_id, $job_id)) {
                    continue;
                }

                // Get user profile
                $user_profile = $this->get_comprehensive_user_profile($user_id);
                if (!$user_profile) {
                    continue;
                }

                // Calculate match score
                $match_result = $this->calculate_enhanced_match($user_profile, $job);

                if ($match_result && $match_result['overall_score'] >= $this->min_match_score) {
                    $success = $this->send_match_notification($user_id, $job, $match_result);
                    if ($success) {
                        $matches_sent++;
                    }
                }
            }

            error_log("Processed job matches for job $job_id: $matches_sent notifications sent");
        } catch (Exception $e) {
            error_log("Error processing job matches for $job_id: " . $e->getMessage());
        }
    }

    /**
     * Get comprehensive user profile including CV data
     */
    private function get_comprehensive_user_profile($user_id)
    {
        global $wpdb;

        // Get basic profile
        $profile_table = $wpdb->prefix . 'sffc_user_profiles';
        $profile = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $profile_table WHERE user_id = %d",
            $user_id
        ));

        if (!$profile) {
            return null;
        }

        // Get skills
        $skills_table = $wpdb->prefix . 'sffc_user_skills';
        $skills = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $skills_table WHERE user_id = %d",
            $user_id
        ));

        // Get CV extraction data
        $cv_processor = SFFC_CV_Match_Processor::get_instance();
        $cv_data = $cv_processor->get_cached_cv_data($user_id);

        // Combine all data
        $comprehensive_profile = [
            'user_id' => $user_id,
            'profile' => $profile,
            'skills' => $skills,
            'cv_data' => $cv_data,
            'user_info' => get_userdata($user_id)
        ];

        return $comprehensive_profile;
    }

    /**
     * Calculate enhanced match score using CV data and job title matching
     * Note: Job matching system deprecated - returns default scores
     */
    private function calculate_enhanced_match($user_profile, $job)
    {
        // Job matching classes removed - return default score structure
        return [
            'overall_score' => 0,
            'base_match' => ['overall_score' => 0],
            'title_match' => 0,
            'skills_match' => 0,
            'location_match' => 0,
            'match_reasons' => [],
            'calculated_at' => current_time('mysql'),
            'deprecated' => true
        ];
    }

    /**
     * Calculate enhanced title matching with fuzzy logic
     * Note: Job title matcher deprecated - returns default score
     */
    private function calculate_enhanced_title_match($cv_titles, $current_title, $job_title, $job_requirements)
    {
        // Title matcher class removed - return 0
        return 0;
    }

    /**
     * Calculate enhanced skills matching
     */
    private function calculate_enhanced_skills_match($user_skills, $cv_skills, $job_skills)
    {
        if (empty($job_skills)) {
            return 80; // Default score if no specific skills required
        }

        // Combine user skills from profile and CV
        $all_user_skills = [];
        foreach ($user_skills as $skill) {
            $all_user_skills[] = strtolower($skill->skill_name);
        }
        foreach ($cv_skills as $skill) {
            $all_user_skills[] = strtolower($skill);
        }
        $all_user_skills = array_unique($all_user_skills);

        $matched_skills = 0;
        $total_skills = count($job_skills);

        foreach ($job_skills as $job_skill) {
            $skill_name = is_array($job_skill) ? $job_skill['skill'] : $job_skill;
            $skill_lower = strtolower($skill_name);

            // Direct match
            if (in_array($skill_lower, $all_user_skills)) {
                $matched_skills++;
                continue;
            }

            // Fuzzy matching for similar skills
            foreach ($all_user_skills as $user_skill) {
                if ($this->calculate_skill_similarity($user_skill, $skill_lower) > 0.8) {
                    $matched_skills += 0.8; // Partial credit for similar skills
                    break;
                }
            }
        }

        return min(100, ($matched_skills / $total_skills) * 100);
    }

    /**
     * Calculate skill similarity
     */
    private function calculate_skill_similarity($skill1, $skill2)
    {
        // Simple similarity calculation
        $skill1 = strtolower(trim($skill1));
        $skill2 = strtolower(trim($skill2));

        if ($skill1 === $skill2) {
            return 1.0;
        }

        // Check if one contains the other
        if (strpos($skill1, $skill2) !== false || strpos($skill2, $skill1) !== false) {
            return 0.9;
        }

        // Levenshtein distance for similar strings
        $distance = levenshtein($skill1, $skill2);
        $max_length = max(strlen($skill1), strlen($skill2));

        if ($max_length === 0) {
            return 1.0;
        }

        return 1 - ($distance / $max_length);
    }

    /**
     * Calculate location matching
     */
    private function calculate_location_match($cv_locations, $preferred_locations, $job_location)
    {
        if (empty($job_location)) {
            return 90; // No location restriction
        }

        $all_locations = array_merge($cv_locations, $preferred_locations);
        $job_location_lower = strtolower($job_location);

        // Check for exact matches
        foreach ($all_locations as $location) {
            if (stripos($location, $job_location) !== false || stripos($job_location, $location) !== false) {
                return 100;
            }
        }

        // Check for remote work
        if (stripos($job_location_lower, 'remote') !== false) {
            return 95;
        }

        // Regional matching (same country/continent)
        $user_regions = $this->extract_regions($all_locations);
        $job_region = $this->extract_regions([$job_location]);

        if (!empty(array_intersect($user_regions, $job_region))) {
            return 70;
        }

        return 30; // Different regions
    }

    /**
     * Extract regions from locations
     */
    private function extract_regions($locations)
    {
        $regions = [];

        $region_mapping = [
            'europe' => ['london', 'paris', 'frankfurt', 'zurich', 'geneva', 'amsterdam', 'milan', 'madrid'],
            'north_america' => ['new york', 'toronto', 'chicago', 'san francisco', 'boston', 'los angeles'],
            'asia_pacific' => ['singapore', 'hong kong', 'tokyo', 'sydney', 'mumbai', 'shanghai'],
            'private_equity' => ['dubai', 'doha', 'riyadh', 'abu dhabi'],
        ];

        foreach ($locations as $location) {
            $location_lower = strtolower($location);
            foreach ($region_mapping as $region => $cities) {
                foreach ($cities as $city) {
                    if (strpos($location_lower, $city) !== false) {
                        $regions[] = $region;
                        break 2;
                    }
                }
            }
        }

        return array_unique($regions);
    }

    /**
     * Generate match reasons for the message
     */
    private function generate_match_reasons($title_score, $skills_score, $location_score)
    {
        $reasons = [];

        if ($title_score >= 80) {
            $reasons[] = "Your experience aligns perfectly with this role";
        } elseif ($title_score >= 60) {
            $reasons[] = "Your background is highly relevant to this position";
        }

        if ($skills_score >= 80) {
            $reasons[] = "You have the key skills they're looking for";
        } elseif ($skills_score >= 60) {
            $reasons[] = "Your skillset matches many of their requirements";
        }

        if ($location_score >= 90) {
            $reasons[] = "The location fits your preferences perfectly";
        } elseif ($location_score >= 70) {
            $reasons[] = "The location is within your preferred region";
        }

        return $reasons;
    }

    /**
     * Send match notification to user
     */
    private function send_match_notification($user_id, $job, $match_result)
    {
        try {
            // Get messaging system
            $messaging = SkillFarm_Messaging_Messages::get_instance();

            // Get user info
            $user = get_userdata($user_id);
            if (!$user) {
                return false;
            }

            // Generate personalized message
            $message_content = $this->generate_match_message($user, $job, $match_result);

            // Prepare message data
            $message_data = [
                'sender_id' => $this->emily_id,
                'recipient_id' => $user_id,
                'subject' => $this->generate_message_subject($job, $match_result),
                'content' => $message_content,
                'category' => 'opportunities',
                'priority' => $match_result['overall_score'] >= 90 ? 'high' : 'normal'
            ];

            // Send message
            $result = $messaging->send_message($message_data);

            if (!is_wp_error($result)) {
                // Record the sent match
                $cv_processor = SFFC_CV_Match_Processor::get_instance();
                $cv_processor->record_sent_match(
                    $user_id,
                    $job->ID,
                    $match_result['overall_score'],
                    $result, // message_id
                    $match_result
                );

                error_log("Match notification sent to user $user_id for job {$job->ID}");
                return true;
            } else {
                error_log("Failed to send match notification: " . $result->get_error_message());
                return false;
            }
        } catch (Exception $e) {
            error_log("Error sending match notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate personalized message subject
     */
    private function generate_message_subject($job, $match_result)
    {
        $company = get_post_meta($job->ID, 'company', true) ?: 'Leading Firm';
        $score = round($match_result['overall_score']);

        return "Perfect Match: {$job->post_title} at {$company} ({$score}% compatibility)";
    }

    /**
     * Generate personalized message content
     */
    private function generate_match_message($user, $job, $match_result)
    {
        $first_name = $user->first_name ?: $user->display_name;
        $company = get_post_meta($job->ID, 'company', true) ?: 'a leading firm';
        $location = get_post_meta($job->ID, 'location', true) ?: 'Global';
        $score = round($match_result['overall_score']);

        $message = "<p>Dear {$first_name},</p>";

        $message .= "<p>I've identified an exceptional opportunity that aligns perfectly with your profile - <strong>{$job->post_title}</strong> at <strong>{$company}</strong>.</p>";

        $message .= "<p><strong>Why this is a perfect match for you ({$score}% compatibility):</strong></p>";
        $message .= "<ul>";

        foreach ($match_result['match_reasons'] as $reason) {
            $message .= "<li>{$reason}</li>";
        }

        $message .= "</ul>";

        // Add job details
        $message .= "<p><strong>Position Details:</strong></p>";
        $message .= "<ul>";
        $message .= "<li><strong>Role:</strong> {$job->post_title}</li>";
        $message .= "<li><strong>Company:</strong> {$company}</li>";
        $message .= "<li><strong>Location:</strong> {$location}</li>";

        $salary = get_post_meta($job->ID, 'salary_range', true);
        if ($salary) {
            $message .= "<li><strong>Compensation:</strong> {$salary}</li>";
        }

        $message .= "</ul>";

        // Add job excerpt if available
        if (!empty($job->post_excerpt)) {
            $message .= "<p><strong>Role Overview:</strong><br>";
            $message .= wp_trim_words($job->post_excerpt, 50) . "</p>";
        }

        // Add match analysis
        if ($match_result['base_match']['gaps']['missing_skills'] ?? false) {
            $missing_skills = $match_result['base_match']['gaps']['missing_skills'];
            if (count($missing_skills) <= 2) {
                $skills_list = implode(', ', array_column($missing_skills, 'skill'));
                $message .= "<p><strong>Growth Opportunity:</strong> This role offers the chance to develop your skills in {$skills_list}.</p>";
            }
        }

        // Call to action
        $message .= "<p><strong>Next Steps:</strong></p>";
        $message .= "<ol>";
        $message .= "<li>Review the full job description in your opportunities dashboard</li>";
        $message .= "<li>I'm happy to provide additional insights about the role and company</li>";
        $message .= "<li>Let me know if you'd like me to facilitate an introduction</li>";
        $message .= "</ol>";

        $message .= "<p>This opportunity won't be available for long, and with your background, you'd be an ideal candidate.</p>";

        $message .= "<p>Looking forward to supporting your next career move.</p>";

        $message .= "<p>Best regards,<br>";
        $message .= "<strong>Emily Bradshaw</strong><br>";
        $message .= "<em>Career Success Manager</em><br>";
        $message .= "senna | <a href=\"mailto:emily.bradshaw@senna.com\">emily.bradshaw@senna.com</a></p>";

        return $message;
    }

    /**
     * Check if user has reached daily message limit
     */
    private function has_reached_daily_limit($user_id)
    {
        global $wpdb;

        $sent_table = $wpdb->prefix . 'sffc_sent_matches';
        $today = date('Y-m-d');

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sent_table WHERE user_id = %d AND DATE(sent_at) = %s",
            $user_id,
            $today
        ));

        return $count >= $this->daily_message_limit;
    }

    /**
     * Get recent jobs for matching
     */
    private function get_recent_jobs($limit = 50)
    {
        return get_posts([
            'post_type' => 'sffc_job',
            'post_status' => 'publish',
            'numberposts' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => '_job_status',
                    'value' => 'active',
                    'compare' => '=',
                ],
            ],
        ]);
    }

    /**
     * Get eligible users for matching
     */
    private function get_eligible_users()
    {
        global $wpdb;

        $profile_table = $wpdb->prefix . 'sffc_user_profiles';

        $users = $wpdb->get_col(
            "SELECT user_id FROM $profile_table 
             WHERE profile_completion_percentage >= 70 
             AND years_experience > 0 
             AND current_title IS NOT NULL 
             AND current_title != ''"
        );

        return array_map('intval', $users);
    }

    /**
     * Prepare profile data for matching
     */
    private function prepare_profile_for_matching($user_profile)
    {
        $profile = $user_profile['profile'];
        $skills = $user_profile['skills'];
        $cv_data = $user_profile['cv_data'];

        return [
            'user_id' => $profile->user_id,
            'skills' => array_map(function ($skill) {
                return [
                    'skill_name' => $skill->skill_name,
                    'proficiency_level' => $skill->proficiency_level,
                ];
            }, $skills),
            'years_experience' => $profile->years_experience,
            'career_stage' => $profile->career_stage,
            'current_title' => $profile->current_title,
            'preferred_locations' => json_decode($profile->preferred_locations ?? '[]', true),
            'preferred_industries' => json_decode($profile->preferred_industries ?? '[]', true),
            'cv_titles' => $cv_data['job_titles'] ?? [],
            'cv_skills' => $cv_data['skills'] ?? [],
        ];
    }

    /**
     * Extract basic job requirements from job post
     */
    private function extract_basic_job_requirements($job)
    {
        $requirements = [
            'technical_skills' => [],
            'experience_level' => [],
            'location_requirements' => [],
        ];

        $content = $job->post_content . ' ' . $job->post_excerpt;
        $content_lower = strtolower($content);

        // Extract common skills
        $common_skills = ['excel', 'python', 'sql', 'bloomberg', 'powerpoint', 'valuation', 'modeling'];
        foreach ($common_skills as $skill) {
            if (strpos($content_lower, $skill) !== false) {
                $requirements['technical_skills'][] = ['skill' => $skill, 'required' => true];
            }
        }

        // Extract experience requirements
        preg_match('/(\d+)\+?\s*years?\s*(of\s*)?(experience|exp)/i', $content, $matches);
        if (!empty($matches[1])) {
            $requirements['experience_level'] = [
                'min_years' => intval($matches[1]),
                'max_years' => intval($matches[1]) + 5,
            ];
        }

        // Extract location
        $location = get_post_meta($job->ID, 'location', true);
        if ($location) {
            $requirements['location_requirements'] = [$location];
        }

        return $requirements;
    }

    /**
     * Cleanup old matches (run daily)
     */
    public function cleanup_old_matches()
    {
        global $wpdb;

        $sent_table = $wpdb->prefix . 'sffc_sent_matches';

        // Delete matches older than 30 days
        $wpdb->query(
            "DELETE FROM $sent_table WHERE sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        error_log("Cleaned up old match records");
    }

    /**
     * Handle user response to match (AJAX)
     */
    public function handle_match_response()
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not authenticated']);
        }

        $user_id = get_current_user_id();
        $job_id = intval($_POST['job_id'] ?? 0);
        $response = sanitize_text_field($_POST['response'] ?? '');

        if (!$job_id || !in_array($response, ['interested', 'not_interested', 'applied'])) {
            wp_send_json_error(['message' => 'Invalid data']);
        }

        global $wpdb;
        $sent_table = $wpdb->prefix . 'sffc_sent_matches';

        $updated = $wpdb->update(
            $sent_table,
            ['user_response' => $response],
            ['user_id' => $user_id, 'job_id' => $job_id],
            ['%s'],
            ['%d', '%d']
        );

        if ($updated) {
            wp_send_json_success(['message' => 'Response recorded']);
        } else {
            wp_send_json_error(['message' => 'Failed to record response']);
        }
    }

    /**
     * Get match details (AJAX)
     */
    public function get_match_details()
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not authenticated']);
        }

        $user_id = get_current_user_id();
        $job_id = intval($_POST['job_id'] ?? 0);

        if (!$job_id) {
            wp_send_json_error(['message' => 'Invalid job ID']);
        }

        global $wpdb;
        $sent_table = $wpdb->prefix . 'sffc_sent_matches';

        $match = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $sent_table WHERE user_id = %d AND job_id = %d",
            $user_id,
            $job_id
        ));

        if ($match) {
            $match_details = json_decode($match->match_details, true);
            wp_send_json_success([
                'match_score' => $match->match_score,
                'details' => $match_details,
                'sent_at' => $match->sent_at,
                'user_response' => $match->user_response
            ]);
        } else {
            wp_send_json_error(['message' => 'Match not found']);
        }
    }
}

// Initialize
SFFC_Automatic_Message_Sender::get_instance();
