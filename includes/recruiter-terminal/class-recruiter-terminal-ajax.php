<?php
/**
 * Recruiter Terminal AJAX Handlers
 *
 * Handles all AJAX requests for the Recruiter Terminal.
 *
 * @package SennaFinanceCareer
 * @subpackage RecruiterTerminal
 */

if (!defined('ABSPATH')) {
    exit;
}

class Recruiter_Terminal_Ajax {

    /**
     * Initialize AJAX handlers
     */
    public static function init() {
        $actions = array(
            // Brief CRUD (new v2.0 messaging-first system)
            'rt_save_brief'         => 'save_brief',
            'rt_get_brief'          => 'get_brief',
            'rt_delete_brief'       => 'delete_brief',
            'rt_submit_brief'       => 'submit_brief',
            'rt_get_briefs'         => 'get_briefs',
            'rt_get_brief_responses'=> 'get_brief_responses',
            'rt_get_response'       => 'get_response',
            'rt_update_response_status' => 'update_response_status',
            'rt_add_note'           => 'add_note',
            'rt_get_notes'          => 'get_notes',

            // Legacy Campaign CRUD (kept for backwards compatibility)
            'rt_save_campaign'      => 'save_campaign',
            'rt_save_draft'         => 'save_draft',
            'rt_get_campaign'       => 'get_campaign',
            'rt_delete_campaign'    => 'delete_campaign',
            'rt_submit_campaign'    => 'submit_campaign',
            'rt_pause_campaign'     => 'pause_campaign',
            'rt_resume_campaign'    => 'resume_campaign',

            // Candidate matching
            'rt_find_matches'       => 'find_matches',
            'rt_add_targets'        => 'add_targets',
            'rt_remove_target'      => 'remove_target',

            // Dashboard & Feed
            'rt_get_campaigns'      => 'get_campaigns',
            'rt_get_feed'           => 'get_feed',
            'rt_get_activity'       => 'get_feed',
            'rt_get_stats'          => 'get_stats',

            // Templates & Email
            'rt_get_templates'      => 'get_templates',
            'rt_preview_email'      => 'preview_email',
            'rt_send_test_email'    => 'send_test_email',

            // Response handling
            'rt_submit_response'        => 'submit_response',
            'rt_save_response_message'  => 'save_response_message',

            // Export
            'rt_export_results'     => 'export_results',
            'rt_export_responses'   => 'export_responses',

            // Admin actions (briefs)
            'rt_approve_brief'          => 'approve_brief',
            'rt_reject_brief'           => 'reject_brief',
            'rt_get_pending_briefs'     => 'get_pending_briefs',
            'rt_get_brief_preview'      => 'get_brief_preview',

            // Legacy Admin actions (campaigns)
            'rt_approve_campaign'       => 'approve_campaign',
            'rt_reject_campaign'        => 'reject_campaign',
            'rt_get_pending_campaigns'  => 'get_pending_campaigns',
            'rt_get_campaign_preview'   => 'get_campaign_preview',
        );

        foreach ($actions as $action => $method) {
            add_action('wp_ajax_' . $action, array(__CLASS__, $method));
        }

        // Public actions (for response submission - no login required)
        add_action('wp_ajax_nopriv_rt_submit_response', array(__CLASS__, 'submit_response'));
        add_action('wp_ajax_nopriv_rt_save_response_message', array(__CLASS__, 'save_response_message'));
    }

    /**
     * Verify nonce and user login
     */
    private static function verify_request($require_login = true) {
        if (!check_ajax_referer('recruiter_terminal_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security check failed. Please refresh the page.', 'senna-finance'),
            ), 403);
        }

        if ($require_login && !is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => __('You must be logged in to perform this action.', 'senna-finance'),
            ), 401);
        }

        return get_current_user_id();
    }

    /**
     * Verify campaign ownership
     */
    private static function verify_campaign_ownership($campaign_id, $user_id) {
        $campaign = Recruiter_Terminal_DB::get_campaign($campaign_id);

        if (!$campaign) {
            wp_send_json_error(array(
                'message' => __('Campaign not found.', 'senna-finance'),
            ), 404);
        }

        if ($campaign->user_id != $user_id && !current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to access this campaign.', 'senna-finance'),
            ), 403);
        }

        return $campaign;
    }

    // =========================================================================
    // CAMPAIGN CRUD
    // =========================================================================

    /**
     * Save draft (used by step navigation)
     */
    public static function save_draft() {
        $user_id = self::verify_request();

        $campaign_id = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $brief = isset($_POST['brief']) ? sanitize_textarea_field($_POST['brief']) : '';
        // Support both field names for backwards compatibility
        if (empty($brief)) {
            $brief = isset($_POST['candidate_brief']) ? sanitize_textarea_field($_POST['candidate_brief']) : '';
        }
        $target_seniority = isset($_POST['target_seniority']) ? sanitize_text_field($_POST['target_seniority']) : '';
        $target_location = isset($_POST['target_location']) ? sanitize_text_field($_POST['target_location']) : '';
        $outreach_subject = isset($_POST['outreach_subject']) ? sanitize_text_field($_POST['outreach_subject']) : '';
        $outreach_message = isset($_POST['outreach_message']) ? wp_kses_post($_POST['outreach_message']) : '';
        $selected_candidates = isset($_POST['selected_candidates']) ? $_POST['selected_candidates'] : array();
        $step = isset($_POST['step']) ? absint($_POST['step']) : 1;

        // Parse full criteria from brief
        $parsed_criteria = self::parse_criteria($brief, $target_seniority, $target_location);

        // Build data array
        $data = array(
            'title'            => $title,
            'brief'            => $brief,
            'outreach_subject' => $outreach_subject,
            'outreach_message' => $outreach_message,
            'parsed_criteria'  => wp_json_encode($parsed_criteria),
        );

        if ($campaign_id > 0) {
            // Update existing campaign
            $campaign = Recruiter_Terminal_DB::get_campaign($campaign_id);

            if (!$campaign || $campaign->user_id != $user_id) {
                wp_send_json_error(array(
                    'message' => __('Campaign not found or access denied.', 'senna-finance'),
                ), 403);
            }

            // Can only edit draft or rejected campaigns
            if (!in_array($campaign->status, array('draft', 'rejected'))) {
                wp_send_json_error(array(
                    'message' => __('This campaign cannot be edited.', 'senna-finance'),
                ), 400);
            }

            $result = Recruiter_Terminal_DB::update_campaign($campaign_id, $data);

            if (is_wp_error($result)) {
                wp_send_json_error(array(
                    'message' => $result->get_error_message(),
                ), 500);
            }
        } else {
            // Create new campaign
            if (empty($title)) {
                wp_send_json_error(array(
                    'message' => __('Campaign title is required.', 'senna-finance'),
                ), 400);
            }

            $data['user_id'] = $user_id;
            $data['status'] = 'draft';

            $campaign_id = Recruiter_Terminal_DB::create_campaign($data);

            if (is_wp_error($campaign_id)) {
                wp_send_json_error(array(
                    'message' => $campaign_id->get_error_message(),
                ), 500);
            }
        }

        // Handle selected candidates (step 3)
        if ($step >= 3 && !empty($selected_candidates) && is_array($selected_candidates)) {
            // Get matched candidates from session or re-query
            // For now, we'll add them via the add_targets method structure
            $candidates_to_add = array();

            foreach ($selected_candidates as $candidate_id) {
                // Get user data if available
                $user = get_userdata($candidate_id);
                if ($user) {
                    $candidates_to_add[] = array(
                        'user_id'     => $candidate_id,
                        'email'       => $user->user_email,
                        'name'        => $user->display_name,
                        'title'       => get_user_meta($candidate_id, 'current_title', true),
                        'company'     => get_user_meta($candidate_id, 'current_company', true),
                        'location'    => get_user_meta($candidate_id, 'current_location', true),
                        'match_score' => 0,
                    );
                }
            }

            if (!empty($candidates_to_add)) {
                Recruiter_Terminal_DB::add_targets($campaign_id, $candidates_to_add);
            }
        }

        wp_send_json_success(array(
            'message'     => __('Draft saved.', 'senna-finance'),
            'campaign_id' => $campaign_id,
        ));
    }

    /**
     * Save campaign (create or update)
     */
    public static function save_campaign() {
        $user_id = self::verify_request();

        $campaign_id = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $brief = isset($_POST['brief']) ? sanitize_textarea_field($_POST['brief']) : '';
        $outreach_subject = isset($_POST['outreach_subject']) ? sanitize_text_field($_POST['outreach_subject']) : '';
        $outreach_message = isset($_POST['outreach_message']) ? wp_kses_post($_POST['outreach_message']) : '';
        $scheduled_at = isset($_POST['scheduled_at']) ? sanitize_text_field($_POST['scheduled_at']) : '';
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : 'draft';

        // Validate required fields
        if (empty($title)) {
            wp_send_json_error(array(
                'message' => __('Campaign title is required.', 'senna-finance'),
                'field'   => 'title',
            ), 400);
        }

        // Prepare data
        $data = array(
            'title'            => $title,
            'brief'            => $brief,
            'outreach_subject' => $outreach_subject,
            'outreach_message' => $outreach_message,
            'scheduled_at'     => !empty($scheduled_at) ? date('Y-m-d H:i:s', strtotime($scheduled_at)) : null,
        );

        // Only allow certain status transitions
        $allowed_statuses = array('draft');
        if (in_array($status, $allowed_statuses)) {
            $data['status'] = $status;
        }

        if ($campaign_id > 0) {
            // Update existing campaign
            $campaign = self::verify_campaign_ownership($campaign_id, $user_id);

            // Can only edit draft or rejected campaigns
            if (!in_array($campaign->status, array('draft', 'rejected'))) {
                wp_send_json_error(array(
                    'message' => __('This campaign cannot be edited in its current state.', 'senna-finance'),
                ), 400);
            }

            $result = Recruiter_Terminal_DB::update_campaign($campaign_id, $data);

            if (is_wp_error($result)) {
                wp_send_json_error(array(
                    'message' => $result->get_error_message(),
                ), 500);
            }
        } else {
            // Create new campaign
            $data['user_id'] = $user_id;
            $data['status'] = 'draft';

            $campaign_id = Recruiter_Terminal_DB::create_campaign($data);

            if (is_wp_error($campaign_id)) {
                wp_send_json_error(array(
                    'message' => $campaign_id->get_error_message(),
                ), 500);
            }
        }

        // Get updated campaign
        $campaign = Recruiter_Terminal_DB::get_campaign($campaign_id);

        wp_send_json_success(array(
            'message'     => __('Campaign saved successfully.', 'senna-finance'),
            'campaign_id' => $campaign_id,
            'campaign'    => $campaign,
        ));
    }

    /**
     * Get campaign data
     */
    public static function get_campaign() {
        $user_id = self::verify_request();

        $campaign_id = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;

        if (!$campaign_id) {
            wp_send_json_error(array(
                'message' => __('Campaign ID is required.', 'senna-finance'),
            ), 400);
        }

        $campaign = self::verify_campaign_ownership($campaign_id, $user_id);
        $targets = Recruiter_Terminal_DB::get_campaign_targets($campaign_id);
        $stats = Recruiter_Terminal_DB::get_campaign_stats($campaign_id);

        wp_send_json_success(array(
            'campaign' => $campaign,
            'targets'  => $targets,
            'stats'    => $stats,
        ));
    }

    /**
     * Delete campaign
     */
    public static function delete_campaign() {
        $user_id = self::verify_request();

        $campaign_id = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;

        if (!$campaign_id) {
            wp_send_json_error(array(
                'message' => __('Campaign ID is required.', 'senna-finance'),
            ), 400);
        }

        $campaign = self::verify_campaign_ownership($campaign_id, $user_id);

        // Can only delete draft campaigns
        if ($campaign->status !== 'draft') {
            wp_send_json_error(array(
                'message' => __('Only draft campaigns can be deleted.', 'senna-finance'),
            ), 400);
        }

        $result = Recruiter_Terminal_DB::delete_campaign($campaign_id);

        if (!$result) {
            wp_send_json_error(array(
                'message' => __('Failed to delete campaign.', 'senna-finance'),
            ), 500);
        }

        wp_send_json_success(array(
            'message' => __('Campaign deleted successfully.', 'senna-finance'),
        ));
    }

    /**
     * Submit campaign for review
     */
    public static function submit_campaign() {
        $user_id = self::verify_request();

        $campaign_id = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;

        if (!$campaign_id) {
            wp_send_json_error(array(
                'message' => __('Campaign ID is required.', 'senna-finance'),
            ), 400);
        }

        $campaign = self::verify_campaign_ownership($campaign_id, $user_id);

        // Must be draft or rejected
        if (!in_array($campaign->status, array('draft', 'rejected'))) {
            wp_send_json_error(array(
                'message' => __('This campaign has already been submitted.', 'senna-finance'),
            ), 400);
        }

        // Validate campaign has required data
        if (empty($campaign->brief)) {
            wp_send_json_error(array(
                'message' => __('Campaign brief is required.', 'senna-finance'),
            ), 400);
        }

        // Check for targets
        $stats = Recruiter_Terminal_DB::get_campaign_stats($campaign_id);
        if ($stats->total < 1) {
            wp_send_json_error(array(
                'message' => __('Please select at least one candidate to target.', 'senna-finance'),
            ), 400);
        }

        // Check for scheduled date
        if (empty($campaign->scheduled_at)) {
            wp_send_json_error(array(
                'message' => __('Please set a scheduled date for the campaign.', 'senna-finance'),
            ), 400);
        }

        // Update status
        $result = Recruiter_Terminal_DB::update_campaign($campaign_id, array(
            'status'       => 'pending_review',
            'submitted_at' => current_time('mysql'),
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
            ), 500);
        }

        // Log activity
        Recruiter_Terminal_DB::log_activity($campaign_id, null, 'campaign_submitted', array(
            'target_count' => $stats->total,
        ));

        // Notify admins (optional - can implement email notification here)
        do_action('rt_campaign_submitted', $campaign_id);

        wp_send_json_success(array(
            'message' => __('Campaign submitted for review. You will be notified once it is approved.', 'senna-finance'),
            'status'  => 'pending_review',
        ));
    }

    /**
     * Pause active campaign
     */
    public static function pause_campaign() {
        $user_id = self::verify_request();

        $campaign_id = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;
        $campaign = self::verify_campaign_ownership($campaign_id, $user_id);

        if ($campaign->status !== 'active') {
            wp_send_json_error(array(
                'message' => __('Only active campaigns can be paused.', 'senna-finance'),
            ), 400);
        }

        Recruiter_Terminal_DB::update_campaign($campaign_id, array(
            'status' => 'paused',
        ));

        Recruiter_Terminal_DB::log_activity($campaign_id, null, 'campaign_paused', array());

        wp_send_json_success(array(
            'message' => __('Campaign paused.', 'senna-finance'),
            'status'  => 'paused',
        ));
    }

    /**
     * Resume paused campaign
     */
    public static function resume_campaign() {
        $user_id = self::verify_request();

        $campaign_id = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;
        $campaign = self::verify_campaign_ownership($campaign_id, $user_id);

        if ($campaign->status !== 'paused') {
            wp_send_json_error(array(
                'message' => __('Only paused campaigns can be resumed.', 'senna-finance'),
            ), 400);
        }

        Recruiter_Terminal_DB::update_campaign($campaign_id, array(
            'status' => 'active',
        ));

        Recruiter_Terminal_DB::log_activity($campaign_id, null, 'campaign_resumed', array());

        wp_send_json_success(array(
            'message' => __('Campaign resumed.', 'senna-finance'),
            'status'  => 'active',
        ));
    }

    // =========================================================================
    // CANDIDATE MATCHING
    // =========================================================================

    /**
     * Find matching candidates based on brief
     */
    public static function find_matches() {
        $user_id = self::verify_request();

        $brief = isset($_POST['brief']) ? sanitize_textarea_field($_POST['brief']) : '';
        $seniority = isset($_POST['seniority']) ? sanitize_text_field($_POST['seniority']) : '';
        $location = isset($_POST['location']) ? sanitize_text_field($_POST['location']) : '';
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 100;

        if (empty($brief)) {
            wp_send_json_error(array(
                'message' => __('Please describe the candidate you are looking for.', 'senna-finance'),
            ), 400);
        }

        // Extract keywords from brief
        $keywords = self::extract_keywords($brief);

        if (empty($keywords)) {
            wp_send_json_error(array(
                'message' => __('Could not extract search criteria from your brief. Please be more specific.', 'senna-finance'),
            ), 400);
        }

        // Parse criteria from brief
        $parsed_criteria = self::parse_criteria($brief, $seniority, $location);

        // Find matching candidates from user profiles
        $candidates = self::search_candidates($keywords, $brief, $limit, $parsed_criteria);

        // Score and sort candidates
        $scored_candidates = self::score_candidates($candidates, $keywords, $brief, $parsed_criteria);

        wp_send_json_success(array(
            'candidates'      => $scored_candidates,
            'count'           => count($scored_candidates),
            'keywords'        => $keywords,
            'parsed_criteria' => $parsed_criteria,
        ));
    }

    /**
     * Parse criteria from brief and form inputs
     */
    private static function parse_criteria($brief, $seniority = '', $location = '') {
        $criteria = array(
            'seniority'        => $seniority,
            'location'         => $location,
            'years_experience' => null,
            'skills'           => array(),
            'industries'       => array(),
        );

        $brief_lower = strtolower($brief);

        // Parse seniority from brief if not provided
        if (empty($seniority)) {
            $seniority_patterns = array(
                'c-suite'  => array('ceo', 'cfo', 'coo', 'cto', 'cio', 'chief', 'c-level', 'c-suite'),
                'director' => array('director', 'vp ', 'vice president', 'head of', 'svp', 'evp'),
                'senior'   => array('senior', 'sr.', 'sr ', 'lead', 'principal', 'staff'),
                'mid'      => array('mid-level', 'mid level', 'experienced', '3-5 years', '5+ years'),
                'entry'    => array('entry', 'junior', 'jr.', 'jr ', 'associate', 'graduate', '0-2 years'),
            );

            foreach ($seniority_patterns as $level => $patterns) {
                foreach ($patterns as $pattern) {
                    if (strpos($brief_lower, $pattern) !== false) {
                        $criteria['seniority'] = $level;
                        break 2;
                    }
                }
            }
        }

        // Parse years of experience from brief
        if (preg_match('/(\d+)\+?\s*(?:years?|yrs?)(?:\s+(?:of\s+)?experience)?/i', $brief, $matches)) {
            $criteria['years_experience'] = (int) $matches[1];
        } elseif (preg_match('/(\d+)\s*-\s*(\d+)\s*(?:years?|yrs?)/i', $brief, $matches)) {
            // Range like "5-10 years"
            $criteria['years_experience'] = (int) round(((int) $matches[1] + (int) $matches[2]) / 2);
        }

        // Parse location from brief if not provided
        if (empty($location)) {
            $location_patterns = array(
                'london', 'new york', 'nyc', 'san francisco', 'sf', 'chicago', 'boston',
                'los angeles', 'la', 'singapore', 'hong kong', 'tokyo', 'dubai',
                'remote', 'hybrid', 'on-site', 'onsite',
            );

            foreach ($location_patterns as $loc) {
                if (strpos($brief_lower, $loc) !== false) {
                    $criteria['location'] = ucwords($loc);
                    break;
                }
            }
        }

        // Parse common finance/investment skills
        $skill_patterns = array(
            'private equity', 'pe', 'venture capital', 'vc', 'hedge fund',
            'investment banking', 'ib', 'm&a', 'mergers', 'acquisitions',
            'financial modeling', 'valuation', 'due diligence', 'lbo',
            'portfolio management', 'asset management', 'risk management',
            'quantitative', 'quant', 'trading', 'derivatives', 'fixed income',
            'equity research', 'credit', 'restructuring', 'capital markets',
            'cfa', 'mba', 'cpa', 'frm',
        );

        foreach ($skill_patterns as $skill) {
            if (strpos($brief_lower, $skill) !== false) {
                $criteria['skills'][] = $skill;
            }
        }

        // Parse industries
        $industry_patterns = array(
            'technology' => array('tech', 'technology', 'software', 'saas'),
            'healthcare' => array('healthcare', 'health', 'pharma', 'biotech'),
            'finance'    => array('finance', 'financial services', 'banking', 'fintech'),
            'real_estate'=> array('real estate', 'property', 'reit'),
            'energy'     => array('energy', 'oil', 'gas', 'renewable'),
            'consumer'   => array('consumer', 'retail', 'cpg', 'fmcg'),
        );

        foreach ($industry_patterns as $industry => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($brief_lower, $pattern) !== false) {
                    $criteria['industries'][] = $industry;
                    break;
                }
            }
        }

        $criteria['skills'] = array_unique($criteria['skills']);
        $criteria['industries'] = array_unique($criteria['industries']);

        return $criteria;
    }

    /**
     * Extract keywords from natural language brief
     */
    private static function extract_keywords($brief) {
        // Common stop words to filter out
        $stop_words = array(
            'i', 'me', 'my', 'am', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
            'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should',
            'a', 'an', 'the', 'and', 'but', 'or', 'for', 'nor', 'on', 'at', 'to', 'from',
            'by', 'with', 'in', 'out', 'of', 'up', 'down', 'as', 'into', 'through',
            'looking', 'looking for', 'need', 'want', 'find', 'search', 'seeking',
            'someone', 'person', 'candidate', 'professional', 'individual',
            'who', 'that', 'which', 'this', 'these', 'those', 'it', 'its',
            'can', 'able', 'experience', 'experienced', 'background',
            'work', 'working', 'worked', 'job', 'role', 'position',
        );

        // Convert to lowercase and extract words
        $brief_lower = strtolower($brief);
        $words = str_word_count($brief_lower, 1, '-');

        // Filter out stop words and short words
        $keywords = array_filter($words, function($word) use ($stop_words) {
            return strlen($word) > 2 && !in_array($word, $stop_words);
        });

        // Get unique keywords
        $keywords = array_unique(array_values($keywords));

        // Limit to top 10 keywords
        return array_slice($keywords, 0, 10);
    }

    /**
     * Search candidates in database
     */
    private static function search_candidates($keywords, $brief, $limit, $parsed_criteria = array()) {
        global $wpdb;

        if (empty($keywords)) {
            return array();
        }

        // Build LIKE conditions for each keyword
        $like_conditions = array();
        $params = array();

        foreach ($keywords as $keyword) {
            $like = '%' . $wpdb->esc_like($keyword) . '%';
            $like_conditions[] = "(
                p.current_title LIKE %s OR
                p.target_role LIKE %s OR
                p.current_company LIKE %s OR
                p.skills_summary LIKE %s OR
                p.current_location LIKE %s OR
                p.preferred_locations LIKE %s
            )";
            $params = array_merge($params, array($like, $like, $like, $like, $like, $like));
        }

        $where_clause = implode(' OR ', $like_conditions);

        // Additional filters based on parsed criteria
        $additional_conditions = array();

        // Location filter
        if (!empty($parsed_criteria['location'])) {
            $loc_like = '%' . $wpdb->esc_like($parsed_criteria['location']) . '%';
            $additional_conditions[] = "(p.current_location LIKE %s OR p.preferred_locations LIKE %s)";
            $params[] = $loc_like;
            $params[] = $loc_like;
        }

        // Experience filter - allow some flexibility
        if (!empty($parsed_criteria['years_experience'])) {
            $min_years = max(0, $parsed_criteria['years_experience'] - 3);
            $max_years = $parsed_criteria['years_experience'] + 5;
            $additional_conditions[] = "(p.years_experience >= %d AND p.years_experience <= %d)";
            $params[] = $min_years;
            $params[] = $max_years;
        }

        // Combine conditions
        $final_where = "($where_clause)";
        if (!empty($additional_conditions)) {
            // Additional conditions are optional boosters, not strict filters
            // We'll use them in scoring instead
        }

        // Add limit
        $params[] = $limit * 2; // Get more candidates for better scoring

        // Query user profiles table
        $sql = "SELECT
                    p.user_id,
                    p.current_title,
                    p.current_company,
                    p.years_experience,
                    p.current_location,
                    p.preferred_locations,
                    p.target_role,
                    p.skills_summary,
                    p.career_stage,
                    p.availability_status,
                    u.user_email,
                    u.display_name
                FROM {$wpdb->prefix}sffc_user_profiles p
                INNER JOIN {$wpdb->users} u ON p.user_id = u.ID
                WHERE $final_where
                AND p.career_stage IS NOT NULL
                AND u.user_email != ''
                LIMIT %d";

        $results = $wpdb->get_results($wpdb->prepare($sql, $params));

        return $results ? $results : array();
    }

    /**
     * Score candidates based on match quality
     *
     * Scoring Weights (from build plan):
     * - Title match: 30%
     * - Skills match: 25%
     * - Experience: 20%
     * - Location: 15%
     * - Availability: 10%
     */
    private static function score_candidates($candidates, $keywords, $brief, $parsed_criteria = array()) {
        $scored = array();

        foreach ($candidates as $candidate) {
            $score = 0;
            $matches = array();

            // Build searchable text
            $searchable = strtolower(implode(' ', array(
                $candidate->current_title ?? '',
                $candidate->current_company ?? '',
                $candidate->skills_summary ?? '',
                $candidate->current_location ?? '',
                $candidate->target_role ?? '',
            )));

            // Score based on keyword matches
            $title_matched = false;
            $skills_matched = false;

            foreach ($keywords as $keyword) {
                $keyword_lower = strtolower($keyword);

                // Title match (30% weight - max 30 points)
                if (!$title_matched && stripos($candidate->current_title ?? '', $keyword) !== false) {
                    $score += 30;
                    $matches[] = 'title';
                    $title_matched = true;
                }

                // Skills match (25% weight - max 25 points)
                if (!$skills_matched && stripos($candidate->skills_summary ?? '', $keyword) !== false) {
                    $score += 25;
                    $matches[] = 'skills';
                    $skills_matched = true;
                }

                // Company match (bonus)
                if (stripos($candidate->current_company ?? '', $keyword) !== false) {
                    $score += 5;
                    $matches[] = 'company';
                }

                // Target role match (bonus)
                if (stripos($candidate->target_role ?? '', $keyword) !== false) {
                    $score += 5;
                    $matches[] = 'target_role';
                }
            }

            // Experience match (20% weight - max 20 points)
            if (!empty($parsed_criteria['years_experience']) && !empty($candidate->years_experience)) {
                $target_years = (int) $parsed_criteria['years_experience'];
                $candidate_years = (int) $candidate->years_experience;
                $diff = abs($target_years - $candidate_years);

                if ($diff === 0) {
                    $score += 20;
                    $matches[] = 'experience_exact';
                } elseif ($diff <= 2) {
                    $score += 15;
                    $matches[] = 'experience_close';
                } elseif ($diff <= 5) {
                    $score += 10;
                    $matches[] = 'experience';
                }
            } elseif (!empty($candidate->years_experience) && (int) $candidate->years_experience >= 5) {
                // Bonus for experienced candidates when no target specified
                $score += 5;
            }

            // Seniority match (bonus within experience)
            if (!empty($parsed_criteria['seniority'])) {
                $seniority_matches = self::check_seniority_match(
                    $parsed_criteria['seniority'],
                    $candidate->current_title ?? '',
                    $candidate->career_stage ?? ''
                );
                if ($seniority_matches) {
                    $score += 10;
                    $matches[] = 'seniority';
                }
            }

            // Location match (15% weight - max 15 points)
            $location_matched = false;
            if (!empty($parsed_criteria['location'])) {
                $target_location = strtolower($parsed_criteria['location']);
                $candidate_location = strtolower($candidate->current_location ?? '');
                $preferred_locations = strtolower($candidate->preferred_locations ?? '');

                if (strpos($candidate_location, $target_location) !== false ||
                    strpos($preferred_locations, $target_location) !== false) {
                    $score += 15;
                    $matches[] = 'location';
                    $location_matched = true;
                }

                // Partial match for remote/hybrid
                if (!$location_matched && ($target_location === 'remote' || $target_location === 'hybrid')) {
                    if (strpos($candidate_location, 'remote') !== false ||
                        strpos($preferred_locations, 'remote') !== false) {
                        $score += 10;
                        $matches[] = 'location_flexible';
                    }
                }
            }

            // Availability bonus (10% weight - max 10 points)
            if (!empty($candidate->availability_status)) {
                if ($candidate->availability_status === 'actively_looking') {
                    $score += 10;
                    $matches[] = 'actively_looking';
                } elseif ($candidate->availability_status === 'open_to_opportunities') {
                    $score += 5;
                    $matches[] = 'open_to_opportunities';
                }
            }

            // Normalize score to 0-100
            $score = min(100, $score);

            // Only include candidates with a minimum score
            if ($score >= 15) {
                $scored[] = array(
                    'user_id'     => $candidate->user_id,
                    'email'       => $candidate->user_email,
                    'name'        => $candidate->display_name,
                    'title'       => $candidate->current_title ?? '',
                    'company'     => $candidate->current_company ?? '',
                    'location'    => $candidate->current_location ?? '',
                    'experience'  => $candidate->years_experience ?? '',
                    'match_score' => $score,
                    'matches'     => array_unique($matches),
                );
            }
        }

        // Sort by score descending
        usort($scored, function($a, $b) {
            return $b['match_score'] - $a['match_score'];
        });

        return $scored;
    }

    /**
     * Check if candidate seniority matches target
     */
    private static function check_seniority_match($target_seniority, $title, $career_stage) {
        $title_lower = strtolower($title);
        $stage_lower = strtolower($career_stage);

        $seniority_indicators = array(
            'c-suite'  => array('ceo', 'cfo', 'coo', 'cto', 'cio', 'chief', 'president'),
            'director' => array('director', 'vp', 'vice president', 'head', 'svp', 'evp', 'managing'),
            'senior'   => array('senior', 'sr.', 'sr ', 'lead', 'principal', 'staff'),
            'mid'      => array('manager', 'analyst', 'specialist', 'consultant'),
            'entry'    => array('junior', 'jr.', 'jr ', 'associate', 'assistant', 'trainee', 'intern'),
        );

        if (!isset($seniority_indicators[$target_seniority])) {
            return false;
        }

        foreach ($seniority_indicators[$target_seniority] as $indicator) {
            if (strpos($title_lower, $indicator) !== false) {
                return true;
            }
        }

        // Also check career stage mapping
        $stage_mapping = array(
            'c-suite'  => array('executive', 'c-level'),
            'director' => array('director', 'vp', 'head'),
            'senior'   => array('senior', 'lead'),
            'mid'      => array('mid', 'experienced'),
            'entry'    => array('entry', 'junior', 'graduate'),
        );

        if (isset($stage_mapping[$target_seniority])) {
            foreach ($stage_mapping[$target_seniority] as $stage) {
                if (strpos($stage_lower, $stage) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Add targets to campaign
     */
    public static function add_targets() {
        $user_id = self::verify_request();

        $campaign_id = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;
        $candidates = isset($_POST['candidates']) ? $_POST['candidates'] : array();

        if (!$campaign_id) {
            wp_send_json_error(array(
                'message' => __('Campaign ID is required.', 'senna-finance'),
            ), 400);
        }

        $campaign = self::verify_campaign_ownership($campaign_id, $user_id);

        // Can only add targets to draft campaigns
        if (!in_array($campaign->status, array('draft', 'rejected'))) {
            wp_send_json_error(array(
                'message' => __('Cannot add targets to this campaign in its current state.', 'senna-finance'),
            ), 400);
        }

        if (empty($candidates) || !is_array($candidates)) {
            wp_send_json_error(array(
                'message' => __('Please select at least one candidate.', 'senna-finance'),
            ), 400);
        }

        // Sanitize candidate data
        $sanitized = array();
        foreach ($candidates as $c) {
            if (!empty($c['email']) && is_email($c['email'])) {
                $sanitized[] = array(
                    'user_id'     => isset($c['user_id']) ? absint($c['user_id']) : null,
                    'email'       => sanitize_email($c['email']),
                    'name'        => sanitize_text_field($c['name'] ?? ''),
                    'title'       => sanitize_text_field($c['title'] ?? ''),
                    'company'     => sanitize_text_field($c['company'] ?? ''),
                    'location'    => sanitize_text_field($c['location'] ?? ''),
                    'match_score' => absint($c['match_score'] ?? 0),
                );
            }
        }

        if (empty($sanitized)) {
            wp_send_json_error(array(
                'message' => __('No valid candidates provided.', 'senna-finance'),
            ), 400);
        }

        $result = Recruiter_Terminal_DB::add_targets($campaign_id, $sanitized);

        // Log activity
        Recruiter_Terminal_DB::log_activity($campaign_id, null, 'targets_added', array(
            'added'   => $result['added'],
            'skipped' => $result['skipped'],
        ));

        // Get updated stats
        $stats = Recruiter_Terminal_DB::get_campaign_stats($campaign_id);

        wp_send_json_success(array(
            'message' => sprintf(
                __('%d candidates added to campaign. %d skipped (already added).', 'senna-finance'),
                $result['added'],
                $result['skipped']
            ),
            'added'   => $result['added'],
            'skipped' => $result['skipped'],
            'stats'   => $stats,
        ));
    }

    /**
     * Remove target from campaign
     */
    public static function remove_target() {
        $user_id = self::verify_request();

        $campaign_id = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;
        $target_id = isset($_POST['target_id']) ? absint($_POST['target_id']) : 0;

        if (!$campaign_id || !$target_id) {
            wp_send_json_error(array(
                'message' => __('Invalid request.', 'senna-finance'),
            ), 400);
        }

        $campaign = self::verify_campaign_ownership($campaign_id, $user_id);

        // Can only remove targets from draft campaigns
        if ($campaign->status !== 'draft') {
            wp_send_json_error(array(
                'message' => __('Cannot remove targets from this campaign.', 'senna-finance'),
            ), 400);
        }

        Recruiter_Terminal_DB::remove_target($target_id, $campaign_id);

        wp_send_json_success(array(
            'message' => __('Candidate removed.', 'senna-finance'),
        ));
    }

    // =========================================================================
    // DASHBOARD & FEED
    // =========================================================================

    /**
     * Get user's campaigns
     */
    public static function get_campaigns() {
        $user_id = self::verify_request();

        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : null;
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 50;
        $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;

        $campaigns = Recruiter_Terminal_DB::get_user_campaigns($user_id, $status, $limit, $offset);

        wp_send_json_success(array(
            'campaigns' => $campaigns,
            'count'     => count($campaigns),
        ));
    }

    /**
     * Get activity feed for campaign
     */
    public static function get_feed() {
        $user_id = self::verify_request();

        $campaign_id = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;
        $since_id = isset($_POST['since_id']) ? absint($_POST['since_id']) : 0;
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 50;

        if (!$campaign_id) {
            wp_send_json_error(array(
                'message' => __('Campaign ID is required.', 'senna-finance'),
            ), 400);
        }

        self::verify_campaign_ownership($campaign_id, $user_id);

        $feed = Recruiter_Terminal_DB::get_activity_feed($campaign_id, $limit, $since_id);
        $stats = Recruiter_Terminal_DB::get_campaign_stats($campaign_id);

        // Get the latest ID for next poll
        $latest_id = !empty($feed) ? $feed[0]->id : $since_id;

        wp_send_json_success(array(
            'feed'      => $feed,
            'stats'     => $stats,
            'latest_id' => $latest_id,
        ));
    }

    /**
     * Get campaign statistics
     */
    public static function get_stats() {
        $user_id = self::verify_request();

        $campaign_id = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;

        if (!$campaign_id) {
            wp_send_json_error(array(
                'message' => __('Campaign ID is required.', 'senna-finance'),
            ), 400);
        }

        self::verify_campaign_ownership($campaign_id, $user_id);

        $stats = Recruiter_Terminal_DB::get_campaign_stats($campaign_id);

        wp_send_json_success(array(
            'stats' => $stats,
        ));
    }

    // =========================================================================
    // TEMPLATES
    // =========================================================================

    /**
     * Get email templates
     */
    public static function get_templates() {
        $user_id = self::verify_request();

        $templates = Recruiter_Terminal_DB::get_templates($user_id);

        wp_send_json_success(array(
            'templates' => $templates,
        ));
    }

    /**
     * Preview email with variables replaced
     */
    public static function preview_email() {
        $user_id = self::verify_request();

        $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
        $body = isset($_POST['body']) ? wp_kses_post($_POST['body']) : '';
        $campaign_title = isset($_POST['campaign_title']) ? sanitize_text_field($_POST['campaign_title']) : 'Sample Campaign';

        // Sample data for preview
        $sample_data = array(
            'candidate_name'    => 'John Smith',
            'candidate_title'   => 'Senior Risk Manager',
            'candidate_company' => 'Goldman Sachs',
            'role_title'        => $campaign_title,
            'recruiter_name'    => wp_get_current_user()->display_name,
            'response_buttons'  => '<p>[Yes, I\'m interested] [No thanks]</p>',
            'unsubscribe_link'  => '<a href="#">Unsubscribe</a>',
        );

        // Replace variables
        foreach ($sample_data as $key => $value) {
            $subject = str_replace('{{' . $key . '}}', $value, $subject);
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }

        wp_send_json_success(array(
            'subject' => $subject,
            'body'    => nl2br($body),
        ));
    }

    // =========================================================================
    // RESPONSE HANDLING
    // =========================================================================

    /**
     * Submit candidate response (v2.0 brief-response system)
     *
     * Handles responses from candidates to role briefs.
     * No login required - candidates respond via public opportunity page.
     */
    public static function submit_response() {
        // Check if this is a v2.0 brief response (has brief_id)
        $brief_id = isset($_POST['brief_id']) ? absint($_POST['brief_id']) : 0;

        if ($brief_id > 0) {
            // v2.0 Brief Response System
            self::submit_brief_response();
            return;
        }

        // Legacy campaign-target system (kept for backwards compatibility)
        self::submit_legacy_response();
    }

    /**
     * Handle v2.0 brief response submission
     */
    private static function submit_brief_response() {
        $brief_id = absint($_POST['brief_id']);
        $tracking_id = isset($_POST['tracking_id']) ? sanitize_text_field($_POST['tracking_id']) : '';
        $candidate_name = isset($_POST['candidate_name']) ? sanitize_text_field($_POST['candidate_name']) : '';
        $candidate_email = isset($_POST['candidate_email']) ? sanitize_email($_POST['candidate_email']) : '';
        $candidate_phone = isset($_POST['candidate_phone']) ? sanitize_text_field($_POST['candidate_phone']) : '';
        $candidate_linkedin = isset($_POST['candidate_linkedin']) ? esc_url_raw($_POST['candidate_linkedin']) : '';
        $message = isset($_POST['candidate_message']) ? sanitize_textarea_field($_POST['candidate_message']) : '';
        $response_type = isset($_POST['response_type']) ? sanitize_key($_POST['response_type']) : 'interested';

        // Validate required fields
        if (empty($candidate_name)) {
            wp_send_json_error(array(
                'message' => __('Please enter your name.', 'senna-finance'),
            ), 400);
        }

        if (empty($candidate_email) || !is_email($candidate_email)) {
            wp_send_json_error(array(
                'message' => __('Please enter a valid email address.', 'senna-finance'),
            ), 400);
        }

        // Verify brief exists and is active
        $brief = Recruiter_Terminal_DB::get_brief($brief_id);
        if (!$brief) {
            wp_send_json_error(array(
                'message' => __('This opportunity is no longer available.', 'senna-finance'),
            ), 404);
        }

        if ($brief->status !== 'active') {
            wp_send_json_error(array(
                'message' => __('This opportunity is no longer accepting responses.', 'senna-finance'),
            ), 400);
        }

        // Check if candidate has already responded to this brief
        global $wpdb;
        $tables = Recruiter_Terminal_DB::get_table_names();
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$tables['responses']} WHERE brief_id = %d AND candidate_email = %s",
            $brief_id,
            $candidate_email
        ));

        if ($existing) {
            wp_send_json_error(array(
                'message' => __('You have already responded to this opportunity.', 'senna-finance'),
            ), 400);
        }

        // Generate tracking ID for the response if not provided
        if (empty($tracking_id)) {
            $tracking_id = wp_generate_uuid4();
        }

        // Determine status based on response type
        $status = ($response_type === 'not_interested') ? 'declined' : 'pending';

        // Create the response
        $response_id = Recruiter_Terminal_DB::create_response(array(
            'brief_id'           => $brief_id,
            'tracking_id'        => $tracking_id,
            'candidate_name'     => $candidate_name,
            'candidate_email'    => $candidate_email,
            'candidate_phone'    => $candidate_phone,
            'candidate_linkedin' => $candidate_linkedin,
            'message'            => $message,
            'status'             => $status,
        ));

        if (is_wp_error($response_id)) {
            wp_send_json_error(array(
                'message' => __('Failed to submit response. Please try again.', 'senna-finance'),
            ), 500);
        }

        // Fire action for notifications
        do_action('rt_brief_response_received', $response_id, $brief_id, $brief->user_id);

        // Return appropriate message based on response type
        if ($response_type === 'not_interested') {
            wp_send_json_success(array(
                'message' => __('Thank you for letting us know. We appreciate your feedback.', 'senna-finance'),
                'response_type' => 'not_interested',
            ));
        }

        wp_send_json_success(array(
            'message' => __('Thank you! The recruiter will be in touch soon.', 'senna-finance'),
            'response_type' => 'interested',
        ));
    }

    /**
     * Legacy campaign-target response submission (backwards compatibility)
     */
    private static function submit_legacy_response() {
        // This can be called without login (candidate responding)
        if (!check_ajax_referer('recruiter_terminal_nonce', 'nonce', false)) {
            // Try alternate nonce for public response
            if (!isset($_POST['tracking_id'])) {
                wp_send_json_error(array(
                    'message' => __('Invalid request.', 'senna-finance'),
                ), 403);
            }
        }

        $tracking_id = isset($_POST['tracking_id']) ? sanitize_text_field($_POST['tracking_id']) : '';
        $response = isset($_POST['response']) ? sanitize_key($_POST['response']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';

        if (empty($tracking_id)) {
            wp_send_json_error(array(
                'message' => __('Invalid tracking ID.', 'senna-finance'),
            ), 400);
        }

        $valid_responses = array('interested', 'not_interested', 'maybe');
        if (!in_array($response, $valid_responses)) {
            wp_send_json_error(array(
                'message' => __('Invalid response type.', 'senna-finance'),
            ), 400);
        }

        // Get target
        $target = Recruiter_Terminal_DB::get_target_by_tracking_id($tracking_id);

        if (!$target) {
            wp_send_json_error(array(
                'message' => __('Invalid or expired link.', 'senna-finance'),
            ), 404);
        }

        // Update target
        Recruiter_Terminal_DB::update_target($target->id, array(
            'response_status'  => $response,
            'response_message' => $message,
            'responded_at'     => current_time('mysql'),
        ));

        // Log activity
        Recruiter_Terminal_DB::log_activity(
            $target->campaign_id,
            $target->id,
            'candidate_responded',
            array(
                'response' => $response,
                'has_message' => !empty($message),
            )
        );

        // Notify recruiter (optional - implement email notification)
        do_action('rt_candidate_responded', $target->id, $response, $message);

        wp_send_json_success(array(
            'message' => __('Thank you for your response!', 'senna-finance'),
        ));
    }

    /**
     * Save response message from candidate
     */
    public static function save_response_message() {
        // Verify nonce for response message
        if (!check_ajax_referer('rt_response_message', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'senna-finance'),
            ), 403);
        }

        $target_id = isset($_POST['target_id']) ? absint($_POST['target_id']) : 0;
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';

        if (!$target_id) {
            wp_send_json_error(array(
                'message' => __('Invalid request.', 'senna-finance'),
            ), 400);
        }

        // Update target with message
        Recruiter_Terminal_DB::update_target($target_id, array(
            'response_message' => $message,
        ));

        // Log activity
        global $wpdb;
        $tables = Recruiter_Terminal_DB::get_table_names();
        $target = $wpdb->get_row($wpdb->prepare(
            "SELECT campaign_id FROM {$tables['targets']} WHERE id = %d",
            $target_id
        ));

        if ($target) {
            Recruiter_Terminal_DB::log_activity(
                $target->campaign_id,
                $target_id,
                'response_message_added',
                array('has_message' => !empty($message))
            );
        }

        wp_send_json_success(array(
            'message' => __('Message saved successfully.', 'senna-finance'),
        ));
    }

    // =========================================================================
    // EMAIL OPERATIONS
    // =========================================================================

    /**
     * Send test email
     */
    public static function send_test_email() {
        $user_id = self::verify_request();

        $campaign_id = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        if (!$campaign_id) {
            wp_send_json_error(array(
                'message' => __('Campaign ID is required.', 'senna-finance'),
            ), 400);
        }

        if (!is_email($email)) {
            wp_send_json_error(array(
                'message' => __('Please enter a valid email address.', 'senna-finance'),
            ), 400);
        }

        $campaign = self::verify_campaign_ownership($campaign_id, $user_id);

        // Send test email
        $result = Recruiter_Terminal_Email::send_test_email($campaign_id, $email);

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
            ), 500);
        }

        if ($result) {
            wp_send_json_success(array(
                'message' => sprintf(__('Test email sent to %s', 'senna-finance'), $email),
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to send test email. Please check your email settings.', 'senna-finance'),
            ), 500);
        }
    }

    // =========================================================================
    // EXPORT OPERATIONS
    // =========================================================================

    /**
     * Export campaign results as CSV
     */
    public static function export_results() {
        // For GET requests (download)
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $campaign_id = isset($_GET['campaign_id']) ? absint($_GET['campaign_id']) : 0;
            $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : '';

            if (!wp_verify_nonce($nonce, 'recruiter_terminal_nonce')) {
                wp_die(__('Security check failed.', 'senna-finance'));
            }

            if (!is_user_logged_in()) {
                wp_die(__('You must be logged in.', 'senna-finance'));
            }

            $user_id = get_current_user_id();
        } else {
            $user_id = self::verify_request();
            $campaign_id = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;
        }

        if (!$campaign_id) {
            wp_die(__('Campaign ID is required.', 'senna-finance'));
        }

        // Verify ownership
        $campaign = Recruiter_Terminal_DB::get_campaign($campaign_id);
        if (!$campaign || ($campaign->user_id != $user_id && !current_user_can('manage_options'))) {
            wp_die(__('Access denied.', 'senna-finance'));
        }

        // Get all targets
        $targets = Recruiter_Terminal_DB::get_campaign_targets($campaign_id);

        // Build CSV
        $filename = sanitize_file_name($campaign->title) . '-results-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // CSV header
        fputcsv($output, array(
            'Name',
            'Email',
            'Title',
            'Company',
            'Location',
            'Match Score',
            'Email Status',
            'Response',
            'Response Message',
            'Sent At',
            'Opened At',
            'Responded At',
        ));

        // CSV rows
        foreach ($targets as $target) {
            fputcsv($output, array(
                $target->candidate_name,
                $target->candidate_email,
                $target->candidate_title,
                $target->candidate_company,
                $target->candidate_location,
                $target->match_score . '%',
                $target->email_status,
                $target->response_status,
                $target->response_message,
                $target->sent_at,
                $target->opened_at,
                $target->responded_at,
            ));
        }

        fclose($output);
        exit;
    }

    /**
     * Get pending campaigns for admin review
     */
    public static function get_pending_campaigns() {
        // Verify admin capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to access this.', 'senna-finance'),
            ), 403);
        }

        check_ajax_referer('recruiter_terminal_nonce', 'nonce');

        global $wpdb;
        $table = $wpdb->prefix . 'rt_campaigns';

        $campaigns = $wpdb->get_results(
            "SELECT c.*, u.display_name as recruiter_name, u.user_email as recruiter_email
             FROM {$table} c
             INNER JOIN {$wpdb->users} u ON c.user_id = u.ID
             WHERE c.status = 'pending_review'
             ORDER BY c.created_at DESC"
        );

        $result = array();
        foreach ($campaigns as $campaign) {
            $stats = Recruiter_Terminal_DB::get_campaign_stats($campaign->id);
            $result[] = array(
                'id'              => $campaign->id,
                'title'           => $campaign->title,
                'brief'           => $campaign->brief,
                'recruiter_name'  => $campaign->recruiter_name,
                'recruiter_email' => $campaign->recruiter_email,
                'target_count'    => $stats->total,
                'scheduled_at'    => $campaign->scheduled_at,
                'created_at'      => $campaign->created_at,
            );
        }

        wp_send_json_success(array(
            'campaigns' => $result,
            'count'     => count($result),
        ));
    }

    /**
     * Get campaign preview for admin
     */
    public static function get_campaign_preview() {
        // Verify admin capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to access this.', 'senna-finance'),
            ), 403);
        }

        // Admin uses different nonce
        if (!check_ajax_referer('rt_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'senna-finance'),
            ), 403);
        }

        $campaign_id = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;

        if (!$campaign_id) {
            wp_send_json_error(array(
                'message' => __('Campaign ID is required.', 'senna-finance'),
            ), 400);
        }

        // Get preview HTML from admin class
        $html = Recruiter_Terminal_Admin::get_campaign_preview($campaign_id);

        // Get campaign status for footer actions
        $campaign = Recruiter_Terminal_DB::get_campaign($campaign_id);
        $footer = '';

        if ($campaign) {
            if ($campaign->status === 'pending_review') {
                $footer = '<button type="button" class="button" data-action="close-modal">' . esc_html__('Close', 'senna-finance') . '</button>';
                $footer .= '<button type="button" class="button" data-action="reject" data-campaign-id="' . esc_attr($campaign_id) . '">' . esc_html__('Reject', 'senna-finance') . '</button>';
                $footer .= '<button type="button" class="button button-primary" data-action="approve" data-campaign-id="' . esc_attr($campaign_id) . '">' . esc_html__('Approve Campaign', 'senna-finance') . '</button>';
            } else {
                $footer = '<button type="button" class="button button-primary" data-action="close-modal">' . esc_html__('Close', 'senna-finance') . '</button>';
            }
        }

        wp_send_json_success(array(
            'html'   => $html,
            'footer' => $footer,
        ));
    }

    /**
     * Approve a campaign
     */
    public static function approve_campaign() {
        // Verify admin capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to approve campaigns.', 'senna-finance'),
            ), 403);
        }

        // Accept either admin or recruiter terminal nonce
        if (!check_ajax_referer('rt_admin_nonce', 'nonce', false) &&
            !check_ajax_referer('recruiter_terminal_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'senna-finance'),
            ), 403);
        }

        $campaign_id = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;

        if (!$campaign_id) {
            wp_send_json_error(array(
                'message' => __('Invalid campaign ID.', 'senna-finance'),
            ), 400);
        }

        $campaign = Recruiter_Terminal_DB::get_campaign($campaign_id);

        if (!$campaign) {
            wp_send_json_error(array(
                'message' => __('Campaign not found.', 'senna-finance'),
            ), 404);
        }

        if ($campaign->status !== 'pending_review') {
            wp_send_json_error(array(
                'message' => __('This campaign is not pending review.', 'senna-finance'),
            ), 400);
        }

        // Approve the campaign
        $result = Recruiter_Terminal_DB::update_campaign($campaign_id, array(
            'status'      => 'approved',
            'approved_at' => current_time('mysql'),
            'approved_by' => get_current_user_id(),
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
            ), 500);
        }

        // Log activity
        Recruiter_Terminal_DB::log_activity($campaign_id, null, 'campaign_approved', array(
            'approved_by' => get_current_user_id(),
        ));

        // Notify recruiter
        $recruiter = get_userdata($campaign->user_id);
        if ($recruiter && $recruiter->user_email) {
            wp_mail(
                $recruiter->user_email,
                sprintf(__('Your campaign "%s" has been approved', 'senna-finance'), $campaign->title),
                sprintf(
                    __("Good news! Your campaign \"%s\" has been approved and will be sent as scheduled.\n\nYou can track responses in your Recruiter Terminal dashboard.\n\nBest regards,\nSenna Intelligence", 'senna-finance'),
                    $campaign->title
                )
            );
        }

        do_action('rt_campaign_approved', $campaign_id);

        wp_send_json_success(array(
            'message' => __('Campaign approved successfully.', 'senna-finance'),
        ));
    }

    /**
     * Reject a campaign
     */
    public static function reject_campaign() {
        // Verify admin capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to reject campaigns.', 'senna-finance'),
            ), 403);
        }

        // Accept either admin or recruiter terminal nonce
        if (!check_ajax_referer('rt_admin_nonce', 'nonce', false) &&
            !check_ajax_referer('recruiter_terminal_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'senna-finance'),
            ), 403);
        }

        $campaign_id = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;
        $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';

        if (!$campaign_id) {
            wp_send_json_error(array(
                'message' => __('Invalid campaign ID.', 'senna-finance'),
            ), 400);
        }

        $campaign = Recruiter_Terminal_DB::get_campaign($campaign_id);

        if (!$campaign) {
            wp_send_json_error(array(
                'message' => __('Campaign not found.', 'senna-finance'),
            ), 404);
        }

        if ($campaign->status !== 'pending_review') {
            wp_send_json_error(array(
                'message' => __('This campaign is not pending review.', 'senna-finance'),
            ), 400);
        }

        // Reject the campaign
        $result = Recruiter_Terminal_DB::update_campaign($campaign_id, array(
            'status'           => 'rejected',
            'rejection_reason' => $reason,
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
            ), 500);
        }

        // Log activity
        Recruiter_Terminal_DB::log_activity($campaign_id, null, 'campaign_rejected', array(
            'rejected_by' => get_current_user_id(),
            'reason'      => $reason,
        ));

        // Notify recruiter
        $recruiter = get_userdata($campaign->user_id);
        if ($recruiter && $recruiter->user_email) {
            $message = sprintf(
                __("Your campaign \"%s\" requires changes before it can be approved.\n\n", 'senna-finance'),
                $campaign->title
            );

            if (!empty($reason)) {
                $message .= sprintf(__("Feedback: %s\n\n", 'senna-finance'), $reason);
            }

            $message .= __("Please update your campaign and resubmit for review.\n\nBest regards,\nSenna Intelligence", 'senna-finance');

            wp_mail(
                $recruiter->user_email,
                sprintf(__('Your campaign "%s" requires changes', 'senna-finance'), $campaign->title),
                $message
            );
        }

        do_action('rt_campaign_rejected', $campaign_id, $reason);

        wp_send_json_success(array(
            'message' => __('Campaign rejected.', 'senna-finance'),
        ));
    }

    // =========================================================================
    // BRIEF CRUD (v2.0 Messaging-First System)
    // =========================================================================

    /**
     * Save brief (create or update)
     */
    public static function save_brief() {
        $user_id = self::verify_request();

        $brief_id = isset($_POST['brief_id']) ? absint($_POST['brief_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $brief_text = isset($_POST['brief']) ? wp_kses_post($_POST['brief']) : '';
        $location = isset($_POST['location']) ? sanitize_text_field($_POST['location']) : '';
        $sector = isset($_POST['sector']) ? sanitize_text_field($_POST['sector']) : '';
        $salary_range = isset($_POST['salary_range']) ? sanitize_text_field($_POST['salary_range']) : '';
        $experience_level = isset($_POST['experience_level']) ? sanitize_text_field($_POST['experience_level']) : '';
        $target_locations = isset($_POST['target_locations']) ? sanitize_text_field($_POST['target_locations']) : '';
        $keywords = isset($_POST['keywords']) ? sanitize_text_field($_POST['keywords']) : '';
        $expires_at = isset($_POST['expires_at']) ? sanitize_text_field($_POST['expires_at']) : '';

        // Build criteria JSON
        $criteria = array(
            'experience_level'  => $experience_level,
            'target_locations'  => $target_locations,
            'keywords'          => $keywords,
        );

        // Prepare data
        $data = array(
            'title'        => $title,
            'brief'        => $brief_text,
            'location'     => $location,
            'sector'       => $sector,
            'salary_range' => $salary_range,
            'parsed_criteria' => wp_json_encode($criteria),
            'expires_at'   => !empty($expires_at) ? date('Y-m-d H:i:s', strtotime($expires_at)) : null,
        );

        if ($brief_id > 0) {
            // Update existing brief
            $brief = Recruiter_Terminal_DB::get_brief($brief_id);

            if (!$brief || $brief->user_id != $user_id) {
                wp_send_json_error(array(
                    'message' => __('Brief not found or access denied.', 'senna-finance'),
                ), 403);
            }

            // Can only edit draft or rejected briefs
            if (!in_array($brief->status, array('draft', 'rejected'))) {
                wp_send_json_error(array(
                    'message' => __('This brief cannot be edited in its current state.', 'senna-finance'),
                ), 400);
            }

            $result = Recruiter_Terminal_DB::update_brief($brief_id, $data);

            if (is_wp_error($result)) {
                wp_send_json_error(array(
                    'message' => $result->get_error_message(),
                ), 500);
            }
        } else {
            // Create new brief
            if (empty($title)) {
                wp_send_json_error(array(
                    'message' => __('Brief title is required.', 'senna-finance'),
                    'field'   => 'title',
                ), 400);
            }

            $data['user_id'] = $user_id;
            $data['status'] = 'draft';

            $brief_id = Recruiter_Terminal_DB::create_brief($data);

            if (is_wp_error($brief_id)) {
                wp_send_json_error(array(
                    'message' => $brief_id->get_error_message(),
                ), 500);
            }
        }

        // Get updated brief
        $brief = Recruiter_Terminal_DB::get_brief($brief_id);

        wp_send_json_success(array(
            'message'  => __('Brief saved.', 'senna-finance'),
            'brief_id' => $brief_id,
            'brief'    => $brief,
        ));
    }

    /**
     * Get brief data
     */
    public static function get_brief() {
        $user_id = self::verify_request();

        $brief_id = isset($_POST['brief_id']) ? absint($_POST['brief_id']) : 0;

        if (!$brief_id) {
            wp_send_json_error(array(
                'message' => __('Brief ID is required.', 'senna-finance'),
            ), 400);
        }

        $brief = Recruiter_Terminal_DB::get_brief($brief_id);

        if (!$brief) {
            wp_send_json_error(array(
                'message' => __('Brief not found.', 'senna-finance'),
            ), 404);
        }

        // Check ownership (unless admin)
        if ($brief->user_id != $user_id && !current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Access denied.', 'senna-finance'),
            ), 403);
        }

        // Get analytics
        $analytics = Recruiter_Terminal_DB::get_brief_analytics($brief_id);

        wp_send_json_success(array(
            'brief'     => $brief,
            'analytics' => $analytics,
        ));
    }

    /**
     * Delete brief
     */
    public static function delete_brief() {
        $user_id = self::verify_request();

        $brief_id = isset($_POST['brief_id']) ? absint($_POST['brief_id']) : 0;

        if (!$brief_id) {
            wp_send_json_error(array(
                'message' => __('Brief ID is required.', 'senna-finance'),
            ), 400);
        }

        $brief = Recruiter_Terminal_DB::get_brief($brief_id);

        if (!$brief || $brief->user_id != $user_id) {
            wp_send_json_error(array(
                'message' => __('Brief not found or access denied.', 'senna-finance'),
            ), 403);
        }

        // Can only delete draft briefs
        if ($brief->status !== 'draft') {
            wp_send_json_error(array(
                'message' => __('Only draft briefs can be deleted.', 'senna-finance'),
            ), 400);
        }

        global $wpdb;
        $tables = Recruiter_Terminal_DB::get_table_names();
        $result = $wpdb->delete($tables['briefs'], array('id' => $brief_id), array('%d'));

        if ($result === false) {
            wp_send_json_error(array(
                'message' => __('Failed to delete brief.', 'senna-finance'),
            ), 500);
        }

        wp_send_json_success(array(
            'message' => __('Brief deleted.', 'senna-finance'),
        ));
    }

    /**
     * Submit brief for review
     */
    public static function submit_brief() {
        $user_id = self::verify_request();

        $brief_id = isset($_POST['brief_id']) ? absint($_POST['brief_id']) : 0;

        if (!$brief_id) {
            wp_send_json_error(array(
                'message' => __('Brief ID is required.', 'senna-finance'),
            ), 400);
        }

        $brief = Recruiter_Terminal_DB::get_brief($brief_id);

        if (!$brief || $brief->user_id != $user_id) {
            wp_send_json_error(array(
                'message' => __('Brief not found or access denied.', 'senna-finance'),
            ), 403);
        }

        // Must be draft or rejected
        if (!in_array($brief->status, array('draft', 'rejected'))) {
            wp_send_json_error(array(
                'message' => __('This brief has already been submitted.', 'senna-finance'),
            ), 400);
        }

        // Validate required fields
        if (empty($brief->title)) {
            wp_send_json_error(array(
                'message' => __('Brief title is required.', 'senna-finance'),
            ), 400);
        }

        if (empty($brief->brief)) {
            wp_send_json_error(array(
                'message' => __('Brief description is required.', 'senna-finance'),
            ), 400);
        }

        // Update status
        $result = Recruiter_Terminal_DB::update_brief($brief_id, array(
            'status'       => 'pending_review',
            'submitted_at' => current_time('mysql'),
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
            ), 500);
        }

        // Notify admin
        do_action('rt_brief_submitted', $brief_id);

        // Send email notification to admin
        $admin_email = get_option('admin_email');
        $recruiter = get_userdata($user_id);
        $recruiter_name = $recruiter ? $recruiter->display_name : 'Unknown';

        wp_mail(
            $admin_email,
            sprintf(__('[MENA Careers] New Brief for Review: %s', 'senna-finance'), $brief->title),
            sprintf(
                __("A new brief has been submitted for review.\n\nTitle: %s\nRecruiter: %s\nLocation: %s\nSector: %s\n\nPlease log in to the admin panel to review and approve.\n\nBest regards,\nSenna Intelligence", 'senna-finance'),
                $brief->title,
                $recruiter_name,
                $brief->location,
                $brief->sector
            )
        );

        wp_send_json_success(array(
            'message' => __('Brief submitted for review. You will be notified once it is approved.', 'senna-finance'),
            'status'  => 'pending_review',
        ));
    }

    /**
     * Get recruiter's briefs
     */
    public static function get_briefs() {
        $user_id = self::verify_request();

        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : null;
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 50;
        $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;

        global $wpdb;
        $tables = Recruiter_Terminal_DB::get_table_names();

        $where = array('user_id = %d');
        $params = array($user_id);

        if ($status && $status !== 'all') {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        $where_clause = implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;

        $briefs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['briefs']} WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $params
        ));

        // Get response counts for each brief
        foreach ($briefs as &$brief) {
            $brief->response_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['responses']} WHERE brief_id = %d",
                $brief->id
            ));
            $brief->unread_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['responses']} WHERE brief_id = %d AND status = 'pending'",
                $brief->id
            ));
        }

        wp_send_json_success(array(
            'briefs' => $briefs,
            'count'  => count($briefs),
        ));
    }

    /**
     * Get responses for a brief
     */
    public static function get_brief_responses() {
        $user_id = self::verify_request();

        $brief_id = isset($_POST['brief_id']) ? absint($_POST['brief_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : null;

        if (!$brief_id) {
            wp_send_json_error(array(
                'message' => __('Brief ID is required.', 'senna-finance'),
            ), 400);
        }

        // Verify ownership
        $brief = Recruiter_Terminal_DB::get_brief($brief_id);
        if (!$brief || ($brief->user_id != $user_id && !current_user_can('manage_options'))) {
            wp_send_json_error(array(
                'message' => __('Access denied.', 'senna-finance'),
            ), 403);
        }

        global $wpdb;
        $tables = Recruiter_Terminal_DB::get_table_names();

        $where = array('brief_id = %d');
        $params = array($brief_id);

        if ($status && $status !== 'all') {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        $where_clause = implode(' AND ', $where);

        $responses = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['responses']} WHERE $where_clause ORDER BY starred DESC, created_at DESC",
            $params
        ));

        wp_send_json_success(array(
            'responses' => $responses,
            'count'     => count($responses),
        ));
    }

    /**
     * Get single response with notes
     */
    public static function get_response() {
        $user_id = self::verify_request();

        $response_id = isset($_POST['response_id']) ? absint($_POST['response_id']) : 0;

        if (!$response_id) {
            wp_send_json_error(array(
                'message' => __('Response ID is required.', 'senna-finance'),
            ), 400);
        }

        global $wpdb;
        $tables = Recruiter_Terminal_DB::get_table_names();

        $response = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, b.title as brief_title, b.user_id
             FROM {$tables['responses']} r
             INNER JOIN {$tables['briefs']} b ON r.brief_id = b.id
             WHERE r.id = %d",
            $response_id
        ));

        if (!$response) {
            wp_send_json_error(array(
                'message' => __('Response not found.', 'senna-finance'),
            ), 404);
        }

        // Verify ownership
        if ($response->user_id != $user_id && !current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Access denied.', 'senna-finance'),
            ), 403);
        }

        // Get notes
        $notes = $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, u.display_name as author_name
             FROM {$tables['notes']} n
             LEFT JOIN {$wpdb->users} u ON n.recruiter_id = u.ID
             WHERE n.response_id = %d
             ORDER BY n.created_at ASC",
            $response_id
        ));

        // Mark as viewed if pending
        if ($response->status === 'pending') {
            $wpdb->update(
                $tables['responses'],
                array('status' => 'viewed', 'viewed_at' => current_time('mysql')),
                array('id' => $response_id),
                array('%s', '%s'),
                array('%d')
            );
            $response->status = 'viewed';
        }

        wp_send_json_success(array(
            'response' => $response,
            'notes'    => $notes,
        ));
    }

    /**
     * Update response status
     */
    public static function update_response_status() {
        $user_id = self::verify_request();

        $response_id = isset($_POST['response_id']) ? absint($_POST['response_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : '';
        $starred = isset($_POST['starred']) ? (bool) $_POST['starred'] : null;

        if (!$response_id) {
            wp_send_json_error(array(
                'message' => __('Response ID is required.', 'senna-finance'),
            ), 400);
        }

        global $wpdb;
        $tables = Recruiter_Terminal_DB::get_table_names();

        // Verify ownership
        $response = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, b.user_id
             FROM {$tables['responses']} r
             INNER JOIN {$tables['briefs']} b ON r.brief_id = b.id
             WHERE r.id = %d",
            $response_id
        ));

        if (!$response || ($response->user_id != $user_id && !current_user_can('manage_options'))) {
            wp_send_json_error(array(
                'message' => __('Access denied.', 'senna-finance'),
            ), 403);
        }

        $update_data = array();
        $update_format = array();

        if (!empty($status)) {
            $valid_statuses = array('pending', 'viewed', 'responded', 'not_interested', 'archived', 'hired', 'rejected');
            if (!in_array($status, $valid_statuses)) {
                wp_send_json_error(array(
                    'message' => __('Invalid status.', 'senna-finance'),
                ), 400);
            }
            $update_data['status'] = $status;
            $update_format[] = '%s';
        }

        if ($starred !== null) {
            $update_data['starred'] = $starred ? 1 : 0;
            $update_format[] = '%d';
        }

        if (!empty($update_data)) {
            $wpdb->update(
                $tables['responses'],
                $update_data,
                array('id' => $response_id),
                $update_format,
                array('%d')
            );
        }

        wp_send_json_success(array(
            'message' => __('Response updated.', 'senna-finance'),
        ));
    }

    /**
     * Add note to response
     */
    public static function add_note() {
        $user_id = self::verify_request();

        $response_id = isset($_POST['response_id']) ? absint($_POST['response_id']) : 0;
        $note = isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : '';

        if (!$response_id || empty($note)) {
            wp_send_json_error(array(
                'message' => __('Response ID and note are required.', 'senna-finance'),
            ), 400);
        }

        global $wpdb;
        $tables = Recruiter_Terminal_DB::get_table_names();

        // Verify ownership
        $response = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, b.user_id
             FROM {$tables['responses']} r
             INNER JOIN {$tables['briefs']} b ON r.brief_id = b.id
             WHERE r.id = %d",
            $response_id
        ));

        if (!$response || ($response->user_id != $user_id && !current_user_can('manage_options'))) {
            wp_send_json_error(array(
                'message' => __('Access denied.', 'senna-finance'),
            ), 403);
        }

        $note_id = Recruiter_Terminal_DB::add_note($response_id, $user_id, $note);

        if (is_wp_error($note_id)) {
            wp_send_json_error(array(
                'message' => $note_id->get_error_message(),
            ), 500);
        }

        // Get the created note with author info
        $created_note = $wpdb->get_row($wpdb->prepare(
            "SELECT n.*, u.display_name as author_name
             FROM {$tables['notes']} n
             LEFT JOIN {$wpdb->users} u ON n.recruiter_id = u.ID
             WHERE n.id = %d",
            $note_id
        ));

        wp_send_json_success(array(
            'message' => __('Note added.', 'senna-finance'),
            'note'    => $created_note,
        ));
    }

    /**
     * Get notes for a response
     */
    public static function get_notes() {
        $user_id = self::verify_request();

        $response_id = isset($_POST['response_id']) ? absint($_POST['response_id']) : 0;

        if (!$response_id) {
            wp_send_json_error(array(
                'message' => __('Response ID is required.', 'senna-finance'),
            ), 400);
        }

        global $wpdb;
        $tables = Recruiter_Terminal_DB::get_table_names();

        // Verify ownership
        $response = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, b.user_id
             FROM {$tables['responses']} r
             INNER JOIN {$tables['briefs']} b ON r.brief_id = b.id
             WHERE r.id = %d",
            $response_id
        ));

        if (!$response || ($response->user_id != $user_id && !current_user_can('manage_options'))) {
            wp_send_json_error(array(
                'message' => __('Access denied.', 'senna-finance'),
            ), 403);
        }

        $notes = $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, u.display_name as author_name
             FROM {$tables['notes']} n
             LEFT JOIN {$wpdb->users} u ON n.recruiter_id = u.ID
             WHERE n.response_id = %d
             ORDER BY n.created_at ASC",
            $response_id
        ));

        wp_send_json_success(array(
            'notes' => $notes,
        ));
    }

    /**
     * Export responses as CSV
     */
    public static function export_responses() {
        // For GET requests (download)
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $brief_id = isset($_GET['brief_id']) ? absint($_GET['brief_id']) : 0;
            $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : '';

            if (!wp_verify_nonce($nonce, 'recruiter_terminal_nonce')) {
                wp_die(__('Security check failed.', 'senna-finance'));
            }

            if (!is_user_logged_in()) {
                wp_die(__('You must be logged in.', 'senna-finance'));
            }

            $user_id = get_current_user_id();
        } else {
            $user_id = self::verify_request();
            $brief_id = isset($_POST['brief_id']) ? absint($_POST['brief_id']) : 0;
        }

        if (!$brief_id) {
            wp_die(__('Brief ID is required.', 'senna-finance'));
        }

        // Verify ownership
        $brief = Recruiter_Terminal_DB::get_brief($brief_id);
        if (!$brief || ($brief->user_id != $user_id && !current_user_can('manage_options'))) {
            wp_die(__('Access denied.', 'senna-finance'));
        }

        global $wpdb;
        $tables = Recruiter_Terminal_DB::get_table_names();

        // Get all responses
        $responses = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['responses']} WHERE brief_id = %d ORDER BY created_at DESC",
            $brief_id
        ));

        // Build CSV
        $filename = sanitize_file_name($brief->title) . '-candidates-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // CSV header
        fputcsv($output, array(
            'Name',
            'Email',
            'Phone',
            'LinkedIn',
            'Message',
            'Status',
            'Starred',
            'Responded At',
        ));

        // CSV rows
        foreach ($responses as $response) {
            fputcsv($output, array(
                $response->candidate_name,
                $response->candidate_email,
                $response->candidate_phone,
                $response->candidate_linkedin,
                $response->message,
                $response->status,
                $response->starred ? 'Yes' : 'No',
                $response->created_at,
            ));
        }

        fclose($output);
        exit;
    }

    // =========================================================================
    // ADMIN BRIEF OPERATIONS
    // =========================================================================

    /**
     * Get pending briefs for admin review
     */
    public static function get_pending_briefs() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Access denied.', 'senna-finance'),
            ), 403);
        }

        check_ajax_referer('recruiter_terminal_nonce', 'nonce');

        global $wpdb;
        $tables = Recruiter_Terminal_DB::get_table_names();

        $briefs = $wpdb->get_results(
            "SELECT b.*, u.display_name as recruiter_name, u.user_email as recruiter_email
             FROM {$tables['briefs']} b
             INNER JOIN {$wpdb->users} u ON b.user_id = u.ID
             WHERE b.status = 'pending_review'
             ORDER BY b.created_at DESC"
        );

        wp_send_json_success(array(
            'briefs' => $briefs,
            'count'  => count($briefs),
        ));
    }

    /**
     * Approve a brief
     */
    public static function approve_brief() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Access denied.', 'senna-finance'),
            ), 403);
        }

        if (!check_ajax_referer('rt_admin_nonce', 'nonce', false) &&
            !check_ajax_referer('recruiter_terminal_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'senna-finance'),
            ), 403);
        }

        $brief_id = isset($_POST['brief_id']) ? absint($_POST['brief_id']) : 0;

        if (!$brief_id) {
            wp_send_json_error(array(
                'message' => __('Brief ID is required.', 'senna-finance'),
            ), 400);
        }

        $brief = Recruiter_Terminal_DB::get_brief($brief_id);

        if (!$brief) {
            wp_send_json_error(array(
                'message' => __('Brief not found.', 'senna-finance'),
            ), 404);
        }

        if ($brief->status !== 'pending_review') {
            wp_send_json_error(array(
                'message' => __('This brief is not pending review.', 'senna-finance'),
            ), 400);
        }

        $result = Recruiter_Terminal_DB::update_brief($brief_id, array(
            'status'      => 'active',
            'approved_at' => current_time('mysql'),
            'approved_by' => get_current_user_id(),
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
            ), 500);
        }

        // Notify recruiter
        $recruiter = get_userdata($brief->user_id);
        if ($recruiter && $recruiter->user_email) {
            wp_mail(
                $recruiter->user_email,
                sprintf(__('Your brief "%s" has been approved', 'senna-finance'), $brief->title),
                sprintf(
                    __("Good news! Your brief \"%s\" has been approved and is now active.\n\nOur team will begin reaching out to qualified candidates. You'll receive notifications when candidates respond.\n\nBest regards,\nSenna Intelligence", 'senna-finance'),
                    $brief->title
                )
            );
        }

        do_action('rt_brief_approved', $brief_id);

        wp_send_json_success(array(
            'message' => __('Brief approved.', 'senna-finance'),
        ));
    }

    /**
     * Reject a brief
     */
    public static function reject_brief() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Access denied.', 'senna-finance'),
            ), 403);
        }

        if (!check_ajax_referer('rt_admin_nonce', 'nonce', false) &&
            !check_ajax_referer('recruiter_terminal_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'senna-finance'),
            ), 403);
        }

        $brief_id = isset($_POST['brief_id']) ? absint($_POST['brief_id']) : 0;
        $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';

        if (!$brief_id) {
            wp_send_json_error(array(
                'message' => __('Brief ID is required.', 'senna-finance'),
            ), 400);
        }

        $brief = Recruiter_Terminal_DB::get_brief($brief_id);

        if (!$brief) {
            wp_send_json_error(array(
                'message' => __('Brief not found.', 'senna-finance'),
            ), 404);
        }

        if ($brief->status !== 'pending_review') {
            wp_send_json_error(array(
                'message' => __('This brief is not pending review.', 'senna-finance'),
            ), 400);
        }

        $result = Recruiter_Terminal_DB::update_brief($brief_id, array(
            'status'           => 'rejected',
            'rejection_reason' => $reason,
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
            ), 500);
        }

        // Notify recruiter
        $recruiter = get_userdata($brief->user_id);
        if ($recruiter && $recruiter->user_email) {
            $message = sprintf(
                __("Your brief \"%s\" requires changes before it can be approved.\n\n", 'senna-finance'),
                $brief->title
            );

            if (!empty($reason)) {
                $message .= sprintf(__("Feedback: %s\n\n", 'senna-finance'), $reason);
            }

            $message .= __("Please update your brief and resubmit for review.\n\nBest regards,\nSenna Intelligence", 'senna-finance');

            wp_mail(
                $recruiter->user_email,
                sprintf(__('Your brief "%s" requires changes', 'senna-finance'), $brief->title),
                $message
            );
        }

        do_action('rt_brief_rejected', $brief_id, $reason);

        wp_send_json_success(array(
            'message' => __('Brief rejected.', 'senna-finance'),
        ));
    }

    /**
     * Get brief preview for admin
     */
    public static function get_brief_preview() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Access denied.', 'senna-finance'),
            ), 403);
        }

        if (!check_ajax_referer('rt_admin_nonce', 'nonce', false) &&
            !check_ajax_referer('recruiter_terminal_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'senna-finance'),
            ), 403);
        }

        $brief_id = isset($_POST['brief_id']) ? absint($_POST['brief_id']) : 0;

        if (!$brief_id) {
            wp_send_json_error(array(
                'message' => __('Brief ID is required.', 'senna-finance'),
            ), 400);
        }

        $brief = Recruiter_Terminal_DB::get_brief($brief_id);

        if (!$brief) {
            wp_send_json_error(array(
                'message' => __('Brief not found.', 'senna-finance'),
            ), 404);
        }

        $recruiter = get_userdata($brief->user_id);
        $recruiter_name = $recruiter ? $recruiter->display_name : 'Unknown';
        $recruiter_email = $recruiter ? $recruiter->user_email : '';

        ob_start();
        ?>
        <div class="brief-preview">
            <div class="brief-preview__header">
                <h2><?php echo esc_html($brief->title); ?></h2>
                <span class="brief-status brief-status--<?php echo esc_attr($brief->status); ?>">
                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $brief->status))); ?>
                </span>
            </div>

            <div class="brief-preview__meta">
                <p><strong><?php esc_html_e('Recruiter:', 'senna-finance'); ?></strong> <?php echo esc_html($recruiter_name); ?> (<?php echo esc_html($recruiter_email); ?>)</p>
                <?php if ($brief->location): ?>
                    <p><strong><?php esc_html_e('Location:', 'senna-finance'); ?></strong> <?php echo esc_html($brief->location); ?></p>
                <?php endif; ?>
                <?php if ($brief->sector): ?>
                    <p><strong><?php esc_html_e('Sector:', 'senna-finance'); ?></strong> <?php echo esc_html($brief->sector); ?></p>
                <?php endif; ?>
                <?php if ($brief->salary_range): ?>
                    <p><strong><?php esc_html_e('Salary Range:', 'senna-finance'); ?></strong> <?php echo esc_html($brief->salary_range); ?></p>
                <?php endif; ?>
                <p><strong><?php esc_html_e('Submitted:', 'senna-finance'); ?></strong> <?php echo esc_html($brief->submitted_at ? date('M j, Y g:i a', strtotime($brief->submitted_at)) : 'N/A'); ?></p>
            </div>

            <div class="brief-preview__content">
                <h3><?php esc_html_e('Brief Description', 'senna-finance'); ?></h3>
                <?php echo wp_kses_post(wpautop($brief->brief)); ?>
            </div>

            <?php if ($brief->parsed_criteria): ?>
                <div class="brief-preview__criteria">
                    <h3><?php esc_html_e('Search Criteria', 'senna-finance'); ?></h3>
                    <pre><?php echo esc_html(json_encode(json_decode($brief->parsed_criteria), JSON_PRETTY_PRINT)); ?></pre>
                </div>
            <?php endif; ?>
        </div>
        <?php
        $html = ob_get_clean();

        wp_send_json_success(array(
            'html'  => $html,
            'brief' => $brief,
        ));
    }
}
