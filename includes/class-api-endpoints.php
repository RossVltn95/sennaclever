<?php
/**
 * REST API Endpoints for MENA Careers Career Strategy Platform
 * Provides all necessary endpoints for the premium frontend interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_API_Endpoints {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('rest_api_init', [$this, 'register_endpoints']);
    }
    
    /**
     * Register all REST API endpoints
     */
    public function register_endpoints() {
        $namespace = 'sffc/v1';
        
        // Curated opportunities endpoint
        register_rest_route($namespace, '/opportunities', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_curated_opportunities'],
            'permission_callback' => [$this, 'check_authentication'],
            'args' => [
                'limit' => [
                    'default' => 6,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0 && $param <= 50;
                    }
                ],
                'offset' => [
                    'default' => 0,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param >= 0;
                    }
                ]
            ]
        ]);
        
        
        // Job analysis endpoints
        register_rest_route($namespace, '/analysis/(?P<job_id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_job_analysis'],
            'permission_callback' => [$this, 'check_premium_access'],
            'args' => [
                'job_id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param) && get_post($param) !== null;
                    }
                ]
            ]
        ]);
        
        // Preference tracking endpoint
        register_rest_route($namespace, '/preferences/track', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'track_interaction'],
            'permission_callback' => [$this, 'check_authentication'],
            'args' => [
                'job_id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
                'action' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return in_array($param, ['view', 'save', 'hide', 'interested', 'not_interested', 'apply']);
                    }
                ],
                'duration' => [
                    'default' => 0,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param >= 0;
                    }
                ]
            ]
        ]);
        
        // User profile endpoint
        register_rest_route($namespace, '/user/profile', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_user_profile'],
            'permission_callback' => [$this, 'check_authentication']
        ]);
        
        // Chat/MENA Careers endpoints
        register_rest_route($namespace, '/chat/context', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_senna_context'],
            'permission_callback' => [$this, 'check_premium_access']
        ]);
        
        register_rest_route($namespace, '/chat/message', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'process_chat_message'],
            'permission_callback' => [$this, 'check_premium_access'],
            'args' => [
                'message' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param) && strlen(trim($param)) > 0;
                    }
                ],
                'context' => [
                    'default' => [],
                    'validate_callback' => function($param) {
                        return is_array($param);
                    }
                ]
            ]
        ]);

        // PE Research endpoint for newsroom terminal
        register_rest_route($namespace, '/research/query', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'process_research_query'],
            'permission_callback' => [$this, 'check_premium_access'],
            'args' => [
                'query' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param) && strlen(trim($param)) > 0;
                    }
                ],
                'category' => [
                    'default' => 'general',
                    'validate_callback' => function($param) {
                        return is_string($param);
                    }
                ]
            ]
        ]);
    }
    
    /**
     * Get curated opportunities for the user
     */
    public function get_curated_opportunities($request) {
        $user_id = get_current_user_id();
        $limit = $request->get_param('limit');
        $offset = $request->get_param('offset');
        
        try {
            // Get job matcher instance
            if (!class_exists('SFFC_Job_Matcher')) {
                return new WP_Error('missing_matcher', 'Job matcher not available', ['status' => 500]);
            }
            
            $matcher = SFFC_Job_Matcher::get_instance();
            $matches = $matcher->calculate_user_job_matches($user_id, $limit, $offset);
            
            $opportunities = [];
            foreach ($matches as $match) {
                $job_id = $match['job_id'];
                $job = get_post($job_id);
                
                if (!$job) continue;
                
                // Get job meta
                $company = get_post_meta($job_id, 'company', true);
                $location = get_post_meta($job_id, 'location', true);
                $salary_min = get_post_meta($job_id, 'salary_min', true);
                $salary_max = get_post_meta($job_id, 'salary_max', true);
                $job_type = get_post_meta($job_id, 'job_type', true);
                
                // Generate strategy preview using MENA Careers helper
                $strategy_preview = $this->generate_strategy_preview($user_id, $job_id, $match['match_data']);
                
                $opportunities[] = [
                    'id' => $job_id,
                    'title' => $job->post_title,
                    'company' => [
                        'name' => $company ?: 'Company',
                        'logo' => substr($company, 0, 1) ?: 'C'
                    ],
                    'location' => $location ?: 'Location TBD',
                    'salary' => $this->format_salary($salary_min, $salary_max),
                    'job_type' => $job_type ?: 'Full-time',
                    'match' => [
                        'score' => round($match['match_data']['overall_score']),
                        'level' => $this->get_match_level($match['match_data']['overall_score']),
                        'indicator_color' => $this->get_match_color($match['match_data']['overall_score'])
                    ],
                    'strategy_preview' => $strategy_preview,
                    'highlights' => $this->extract_highlights($match['match_data']),
                    'posted_date' => $job->post_date,
                    'is_shortlisted' => false
                ];
            }
            
            return rest_ensure_response([
                'opportunities' => $opportunities,
                'total_available' => $this->get_total_opportunities($user_id),
                'user_preferences' => $this->get_user_preference_summary($user_id)
            ]);
            
        } catch (Exception $e) {
            error_log('SFFC API Error - get_curated_opportunities: ' . $e->getMessage());
            return new WP_Error('api_error', 'Failed to fetch opportunities', ['status' => 500]);
        }
    }
    
    
    
    
    /**
     * Get comprehensive job analysis
     */
    public function get_job_analysis($request) {
        $user_id = get_current_user_id();
        $job_id = $request->get_param('job_id');
        
        try {
            if (!class_exists('SFFC_Senna_Integration_Helper')) {
                return new WP_Error('missing_senna', 'MENA Careers integration not available', ['status' => 500]);
            }
            
            $senna_helper = SFFC_Senna_Integration_Helper::get_instance();
            
            // Get comprehensive analysis
            $fit_analysis = $senna_helper->analyze_job_fit($user_id, $job_id);
            $application_strategy = $senna_helper->generate_application_strategy($user_id, $job_id);
            $cover_letter_points = $senna_helper->generate_cover_letter_points($user_id, $job_id);
            $interview_insights = $senna_helper->prepare_interview_insights($user_id, $job_id);
            
            // Get job details
            $job = get_post($job_id);
            $company = get_post_meta($job_id, 'company', true);
            
            return rest_ensure_response([
                'job' => [
                    'id' => $job_id,
                    'title' => $job->post_title,
                    'company' => $company
                ],
                'fit_analysis' => $fit_analysis,
                'application_strategy' => $application_strategy,
                'cover_letter_points' => $cover_letter_points,
                'interview_insights' => $interview_insights,
                'generated_at' => current_time('mysql')
            ]);
            
        } catch (Exception $e) {
            error_log('SFFC API Error - get_job_analysis: ' . $e->getMessage());
            return new WP_Error('api_error', 'Failed to generate analysis', ['status' => 500]);
        }
    }
    
    /**
     * Track user interaction for preference learning
     */
    public function track_interaction($request) {
        $user_id = get_current_user_id();
        $job_id = $request->get_param('job_id');
        $action = $request->get_param('action');
        $duration = $request->get_param('duration');
        
        try {
            if (!class_exists('SFFC_Job_Preference_Tracker')) {
                return new WP_Error('missing_tracker', 'Preference tracker not available', ['status' => 500]);
            }
            
            $tracker = SFFC_Job_Preference_Tracker::get_instance();
            
            $data = [
                'duration' => $duration,
                'session_id' => session_id(),
                'timestamp' => current_time('mysql')
            ];
            
            $result = $tracker->track_job_interaction($user_id, $job_id, $action, $data);
            
            return rest_ensure_response([
                'success' => true,
                'interaction_id' => $result,
                'preferences_updated' => true
            ]);
            
        } catch (Exception $e) {
            error_log('SFFC API Error - track_interaction: ' . $e->getMessage());
            return new WP_Error('api_error', 'Failed to track interaction', ['status' => 500]);
        }
    }
    
    /**
     * Get user profile data
     */
    public function get_user_profile($request) {
        $user_id = get_current_user_id();
        
        try {
            if (!class_exists('SFFC_User_Profile_Manager')) {
                return new WP_Error('missing_profile', 'Profile manager not available', ['status' => 500]);
            }
            
            $profile_manager = SFFC_User_Profile_Manager::get_instance();
            $profile = $profile_manager->get_user_profile($user_id);
            
            return rest_ensure_response([
                'profile' => $profile,
                'completion_percentage' => $profile['profile_completion_percentage'] ?? 0,
                'subscription_status' => $this->get_subscription_status($user_id),
                'preferences_learned' => $this->get_learned_preferences_count($user_id)
            ]);
            
        } catch (Exception $e) {
            error_log('SFFC API Error - get_user_profile: ' . $e->getMessage());
            return new WP_Error('api_error', 'Failed to fetch profile', ['status' => 500]);
        }
    }
    
    /**
     * Get MENA Careers context for chat
     */
    public function get_senna_context($request) {
        $user_id = get_current_user_id();
        
        try {
            if (!class_exists('SFFC_Senna_Integration_Helper')) {
                return new WP_Error('missing_senna', 'MENA Careers integration not available', ['status' => 500]);
            }
            
            $senna_helper = SFFC_Senna_Integration_Helper::get_instance();
            $context = $senna_helper->prepare_senna_context($user_id);
            
            return rest_ensure_response($context);
            
        } catch (Exception $e) {
            error_log('SFFC API Error - get_senna_context: ' . $e->getMessage());
            return new WP_Error('api_error', 'Failed to prepare context', ['status' => 500]);
        }
    }
    
    /**
     * Process chat message with MENA Careers
     */
    public function process_chat_message($request) {
        $user_id = get_current_user_id();
        $message = $request->get_param('message');
        $context = $request->get_param('context');
        
        try {
            // This would integrate with Claude API through the existing hybrid response manager
            if (!class_exists('SFFC_Hybrid_Response_Manager')) {
                return new WP_Error('missing_hybrid', 'Response manager not available', ['status' => 500]);
            }
            
            $response_manager = SFFC_Hybrid_Response_Manager::get_instance();
            
            // Generate contextual response based on user's shortlist and profile
            $response = $response_manager->generate_contextual_response($message, $context, $user_id);
            
            return rest_ensure_response([
                'response' => $response,
                'timestamp' => current_time('mysql'),
                'context_updated' => true
            ]);
            
        } catch (Exception $e) {
            error_log('SFFC API Error - process_chat_message: ' . $e->getMessage());
            return new WP_Error('api_error', 'Failed to process message', ['status' => 500]);
        }
    }

    /**
     * Process PE research query through Claude
     */
    public function process_research_query($request) {
        $query = trim($request->get_param('query'));
        $category = $request->get_param('category');

        try {
            // Get Claude API Manager
            if (!class_exists('SFFC_Claude_API_Manager')) {
                return new WP_Error('missing_claude', 'Claude API not available', ['status' => 500]);
            }

            $claude = SFFC_Claude_API_Manager::get_instance();

            // Add category context to query for better responses
            $category_context = $this->get_category_context($category);
            $enhanced_query = $category_context ? "[Research Focus: {$category_context}]\n\n{$query}" : $query;

            // Call Claude API
            $result = $claude->call_api($enhanced_query, [
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => 2048,
                'temperature' => 0.4,
                'mode' => 'pe_research'
            ]);

            // Extract response text
            $response_text = '';
            if (!empty($result['content'][0]['text'])) {
                $response_text = $result['content'][0]['text'];
            } elseif (!empty($result['response'])) {
                $response_text = $result['response'];
            }

            // Format the response as HTML and sanitize
            $formatted_content = $this->format_research_response($response_text, $category);
            $sanitized_content = wp_kses_post($formatted_content);

            return rest_ensure_response([
                'success' => true,
                'content' => $sanitized_content,
                'source' => $result['source'] ?? 'unknown',
                'category' => $category,
                'timestamp' => current_time('mysql')
            ]);

        } catch (Exception $e) {
            error_log('SFFC API Error - process_research_query: ' . $e->getMessage());
            return new WP_Error('api_error', 'Failed to process research query', ['status' => 500]);
        }
    }

    /**
     * Get category context string for research queries
     */
    private function get_category_context($category) {
        $contexts = [
            'thesis' => 'Investment Thesis Development - Focus on value creation levers, entry/exit strategies, and scenario analysis',
            'screening' => 'Target Screening - Focus on financial metrics, growth characteristics, competitive positioning, and deal sourcing',
            'market' => 'Market Intelligence - Focus on deal activity trends, valuation multiples, sector dynamics, and competitive landscape',
            'diligence' => 'Due Diligence - Focus on key questions, red flags, quality of earnings adjustments, and risk assessment',
            'value' => 'Value Creation - Focus on operational improvements, revenue growth initiatives, margin expansion, and best practices'
        ];

        return $contexts[$category] ?? '';
    }

    /**
     * Format research response as structured HTML
     */
    private function format_research_response($response_text, $category) {
        // If response already contains HTML structure, return as-is
        if (strpos($response_text, '<h3>') !== false || strpos($response_text, '<h4>') !== false) {
            // Clean up any markdown artifacts
            $response_text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $response_text);
            $response_text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $response_text);
            return $response_text;
        }

        // Convert markdown-style formatting to HTML
        $html = $response_text;

        // Convert headers
        $html = preg_replace('/^### (.*?)$/m', '<h4>$1</h4>', $html);
        $html = preg_replace('/^## (.*?)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^# (.*?)$/m', '<h3>$1</h3>', $html);

        // Convert bold and italic
        $html = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $html);

        // Convert bullet points
        $lines = explode("\n", $html);
        $in_list = false;
        $formatted_lines = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Check if line is a bullet point
            if (preg_match('/^[-•*]\s+(.*)$/', $trimmed, $matches)) {
                if (!$in_list) {
                    $formatted_lines[] = '<ul>';
                    $in_list = true;
                }
                $formatted_lines[] = '<li>' . $matches[1] . '</li>';
            } else {
                if ($in_list) {
                    $formatted_lines[] = '</ul>';
                    $in_list = false;
                }

                // Wrap non-empty, non-header lines in paragraphs
                if (!empty($trimmed) && !preg_match('/^<[hul]/', $trimmed)) {
                    $formatted_lines[] = '<p>' . $trimmed . '</p>';
                } else {
                    $formatted_lines[] = $line;
                }
            }
        }

        if ($in_list) {
            $formatted_lines[] = '</ul>';
        }

        $html = implode("\n", $formatted_lines);

        // Clean up multiple empty paragraphs
        $html = preg_replace('/<p><\/p>/', '', $html);
        $html = preg_replace('/\n{3,}/', "\n\n", $html);

        return $html;
    }

    // === PERMISSION CALLBACKS ===
    
    public function check_authentication($request) {
        // Allow if user is logged in
        if (is_user_logged_in()) {
            return true;
        }
        
        // Check for valid nonce in header
        $nonce = $request->get_header('X-WP-Nonce');
        if ($nonce && wp_verify_nonce($nonce, 'wp_rest')) {
            return true;
        }
        
        // For development/testing - can be removed in production
        // return true;
        
        return new \WP_Error(
            'rest_forbidden',
            'You must be logged in to access this endpoint.',
            array('status' => 401)
        );
    }
    
    public function check_premium_access($request) {
        if (!is_user_logged_in()) {
            // Check for valid nonce in header
            $nonce = $request->get_header('X-WP-Nonce');
            if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
                return new \WP_Error(
                    'rest_forbidden',
                    'Premium access required.',
                    array('status' => 403)
                );
            }
        }
        
        // Check if user has premium access via MemberPress
        $user_id = get_current_user_id();
        
        // For now, allow all logged-in users (can be restricted later)
        if ($user_id > 0) {
            return true;
        }
        
        // Basic check - can be enhanced with MemberPress integration
        if (function_exists('mepr_user_has_access')) {
            return mepr_user_has_access($user_id, 'premium-features');
        }
        
        // Fallback check
        return user_can($user_id, 'premium_access') || user_can($user_id, 'read');
    }
    
    // === HELPER METHODS ===
    
    private function generate_strategy_preview($user_id, $job_id, $match_data) {
        // Generate a brief strategy preview based on match data
        $strengths = $match_data['technical_skills']['matches'] ?? [];
        
        if (!empty($strengths)) {
            $top_strength = array_keys($strengths)[0] ?? 'experience';
            return "Your {$top_strength} experience directly aligns with their requirements";
        }
        
        return "Strategic positioning opportunity identified based on your profile";
    }
    
    private function format_salary($min, $max) {
        if ($min && $max) {
            return "£{$min}-{$max}k";
        } elseif ($min) {
            return "£{$min}k+";
        } elseif ($max) {
            return "Up to £{$max}k";
        }
        return "Competitive";
    }
    
    private function get_match_level($score) {
        if ($score >= 90) return "Exceptional Fit";
        if ($score >= 80) return "Strong Alignment";  
        if ($score >= 70) return "Good Match";
        if ($score >= 60) return "Strategic Fit";
        return "Emerging Fit";
    }
    
    private function get_match_color($score) {
        if ($score >= 80) return "#22C55E"; // Green
        if ($score >= 60) return "#F59E0B"; // Amber
        return "#6B7280"; // Gray
    }
    
    private function extract_highlights($match_data) {
        $highlights = [];
        
        // Extract top 3 matching skills
        if (!empty($match_data['technical_skills']['matches'])) {
            $skills = array_keys($match_data['technical_skills']['matches']);
            $highlights = array_slice($skills, 0, 3);
        }
        
        return $highlights;
    }
    
    
    private function get_total_opportunities($user_id) {
        // Get total number of available opportunities for this user
        $args = [
            'post_type' => 'sffc_job',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ];
        
        $jobs = get_posts($args);
        return count($jobs);
    }
    
    private function get_user_preference_summary($user_id) {
        if (!class_exists('SFFC_Job_Preference_Tracker')) {
            return [];
        }
        
        $tracker = SFFC_Job_Preference_Tracker::get_instance();
        $preferences = $tracker->get_user_preferences($user_id);
        
        $summary = [];
        foreach ($preferences as $type => $prefs) {
            $summary[$type] = count($prefs);
        }
        
        return $summary;
    }
    
    
    
    
    private function get_subscription_limits($user_id) {
        $subscription = $this->get_subscription_status($user_id);
        
        return [
            'current_tier' => $subscription
        ];
    }
    
    private function get_subscription_status($user_id) {
        // Integration with MemberPress or subscription system
        if (function_exists('mepr_get_user_active_subscriptions')) {
            $subscriptions = mepr_get_user_active_subscriptions($user_id);
            if (!empty($subscriptions)) {
                // Return highest tier subscription
                return 'premium'; // Simplified
            }
        }
        
        return 'free';
    }
    
    private function get_learned_preferences_count($user_id) {
        if (!class_exists('SFFC_Job_Preference_Tracker')) {
            return 0;
        }
        
        $tracker = SFFC_Job_Preference_Tracker::get_instance();
        $preferences = $tracker->get_user_preferences($user_id);
        
        $count = 0;
        foreach ($preferences as $type => $prefs) {
            $count += count($prefs);
        }
        
        return $count;
    }
    
    private function track_preference_learning($user_id, $job_id, $action) {
        if (!class_exists('SFFC_Job_Preference_Tracker')) {
            return;
        }
        
        $tracker = SFFC_Job_Preference_Tracker::get_instance();
        $tracker->track_job_interaction($user_id, $job_id, $action, [
            'context' => 'api_interaction',
            'timestamp' => current_time('mysql')
        ]);
    }
}

// Initialize only when WordPress is ready
if (defined('ABSPATH')) {
    add_action('init', function() {
        SFFC_API_Endpoints::get_instance();
    }, 5);
}