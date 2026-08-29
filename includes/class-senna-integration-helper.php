<?php

/**
 * MENA Careers AI Integration Helper
 * 
 * Provides intelligent career strategy responses and analysis
 * Integrates with Claude API for premium conversational AI
 * 
 * @package MENA Careers
 * @since 5.3.2
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Senna_Integration_Helper
{

    private static $instance = null;
    private $claude_api = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Register AJAX handlers for direct access
        add_action('wp_ajax_sffc_prepare_senna_context', [$this, 'ajax_prepare_senna_context']);
        // Application strategy removed - CV tailoring will replace this
        add_action('wp_ajax_sffc_analyze_job_fit', [$this, 'ajax_analyze_job_fit']);
        add_action('wp_ajax_sffc_generate_cover_letter_points', [$this, 'ajax_generate_cover_letter_points']);
        add_action('wp_ajax_sffc_prepare_interview_insights', [$this, 'ajax_prepare_interview_insights']);

        // Public access versions
        add_action('wp_ajax_nopriv_sffc_prepare_senna_context', [$this, 'ajax_prepare_senna_context']);
        // Application strategy removed - CV tailoring will replace this
        add_action('wp_ajax_nopriv_sffc_analyze_job_fit', [$this, 'ajax_analyze_job_fit']);

        // Ultimate interface Claude query
        add_action('wp_ajax_sffc_senna_claude_query', [$this, 'ajax_claude_query']);
        add_action('wp_ajax_nopriv_sffc_senna_claude_query', [$this, 'ajax_claude_query']);
    }

    private function get_claude_api()
    {
        if ($this->claude_api instanceof SFFC_Claude_API_Manager) {
            return $this->claude_api;
        }

        if (class_exists('SFFC_Claude_API_Manager')) {
            $this->claude_api = SFFC_Claude_API_Manager::get_instance();
        }

        return $this->claude_api;
    }

    /**
     * Process career query with AI
     */
    public function process_career_query($query_data)
    {
        $message = $query_data['message'] ?? '';
        $context = $query_data['context'] ?? [];
        $conversation_id = $query_data['conversation_id'] ?? '';
        $mode = $query_data['mode'] ?? 'career_strategy';

        // Check if Claude API is available
        $claude_api = $this->get_claude_api();
        if ($claude_api) {
            // Map to Claude's mode system
            $claude_mode = $this->get_claude_mode($mode);

            // Build the full context for Claude
            $claude_context = [
                'mode' => $claude_mode,
                'conversation_history' => $context['messageHistory'] ?? [],
                'profile' => $context['profile'] ?? [],
                'career_journey' => $context['career_journey'] ?? [],
                'system_prompt' => $context['system_prompt'] ?? '',
                'lesson_state' => $context['lesson_state'] ?? []
            ];

            // Add context to message
            $enhanced_message = $this->build_user_prompt($message, $context);

            // Call Claude API with mapped mode
            $response = $claude_api->send_message($enhanced_message, $claude_context, $claude_mode);

            if ($response && isset($response['success']) && $response['success']) {
                return $this->format_ai_response($response['response'], $context);
            }
        }

        // Fallback if Claude is not available or fails
        return $this->generate_intelligent_fallback($message, $context);
    }

    /**
     * Get appropriate mode for Claude based on context
     */
    private function get_claude_mode($original_mode)
    {
        // Map our modes to Claude's existing modes
        $mode_map = [
            'career_strategy' => 'career',
            'application_strategy' => 'opportunities',
            'interview_prep' => 'career',
            'salary_negotiation' => 'career',
            'skills' => 'skills',
            'market' => 'market',
            'pe_tutor' => 'pe_tutor'
        ];

        return $mode_map[$original_mode] ?? 'career';
    }

    /**
     * Build user prompt with context
     */
    private function build_user_prompt($message, $context)
    {
        if (isset($context['mode']) && $context['mode'] === 'pe_tutor') {
            return $message; // keep lesson instructions pure for PE tutor sessions
        }

        $prompt = $message;


        // Add user profile context if available
        if (!empty($context['profile'])) {
            $prompt .= "\n\nMy background: ";
            if (isset($context['profile']['experience_years'])) {
                $prompt .= "{$context['profile']['experience_years']} years experience";
            }
            if (isset($context['profile']['current_role'])) {
                $prompt .= ", currently {$context['profile']['current_role']}";
            }
            if (isset($context['profile']['target_salary'])) {
                $prompt .= ", targeting $" . $context['profile']['target_salary'] . "k";
            }
        }

        // Add preferences if available
        if (!empty($context['preferences'])) {
            $prompt .= "\n\nPreferences: " . json_encode($context['preferences']);
        }

        return $prompt;
    }


    /**
     * Format AI response for display
     */
    private function format_ai_response($content, $context)
    {
        // Check if response contains special formatting hints
        $response = [
            'type' => 'text',
            'content' => $content,
            'cards' => [],
            'actions' => [],
            'suggestions' => []
        ];

        // Extract any structured data from response
        if (strpos($content, '[ANALYSIS]') !== false) {
            $response['type'] = 'analysis';
            $response['cards'] = $this->extract_analysis_cards($content, $context);
        }

        // Generate contextual suggestions
        $response['suggestions'] = $this->generate_suggestions($content, $context);

        // Add relevant actions
        $response['actions'] = $this->generate_actions($content, $context);

        return $response;
    }

    /**
     * Generate intelligent fallback response
     */
    private function generate_intelligent_fallback($message, $context)
    {
        if (($context['mode'] ?? '') === 'pe_tutor') {
            return [
                'type' => 'text',
                'content' => $this->generate_pe_tutor_fallback($message, $context),
                'suggestions' => [
                    'Start with LBO basics',
                    'Teach me debt schedules',
                    'Give me an IRR practice question',
                    'Build an investment memo'
                ]
            ];
        }

        $message_lower = strtolower($message);
        $shortlist_count = count($context['shortlist'] ?? []);


        if (strpos($message_lower, 'salary') !== false || strpos($message_lower, 'compensation') !== false) {
            return $this->generate_salary_insights($context);
        }

        if (strpos($message_lower, 'interview') !== false || strpos($message_lower, 'prepare') !== false) {
            return $this->generate_interview_tips($context);
        }

        if (strpos($message_lower, 'strategy') !== false || strpos($message_lower, 'apply') !== false) {
            return $this->generate_application_strategy($context);
        }


        // Default strategic response
        return [
            'type' => 'text',
            'content' => $this->get_strategic_response($message, $context),
            'suggestions' => [
                "Analyze available opportunities",
                "Compare compensation packages",
                "Evaluate career progression paths",
                "Assess cultural fit factors"
            ]
        ];
    }

    /**
     * Local learning-coach response when the API manager is unavailable.
     */
    private function generate_pe_tutor_fallback($message, $context = [])
    {
        $message_lower = strtolower($message);
        $lesson_state = is_array($context['lesson_state'] ?? null) ? $context['lesson_state'] : [];
        $style = !empty($lesson_state['learningStyle'])
            ? $lesson_state['learningStyle']
            : $this->detect_tutor_learning_style($message);
        $needs_feedback = !empty($lesson_state['needsFeedback']);
        $track = !empty($lesson_state['currentTrack'])
            ? $lesson_state['currentTrack']
            : $this->detect_finance_learning_track($message_lower);

        if ($needs_feedback) {
            return $this->format_tutor_fallback_lesson(
                'Let me check your working before we move on.',
                'Feedback Loop',
                'In PE modelling, the habit is: check the formula, check the signs, then check whether the answer makes commercial sense.',
                [
                    'If you calculated enterprise value, the formula is EBITDA x entry multiple',
                    'If you calculated debt, the formula is EBITDA x leverage multiple',
                    'If you calculated sponsor equity, the formula is entry EV - opening debt',
                    'If you calculated MOIC, the formula is exit equity value / sponsor equity invested'
                ],
                'Write your calculation in one line using the formula, for example: Entry EV = GBP 20m x 10.0x = GBP 200m.',
                $style
            );
        }

        if ($this->is_job_related_tutor_query($message_lower)) {
            return $this->format_tutor_fallback_lesson(
                'Let us keep this room focused on learning. I will turn that into the technical skill underneath it.',
                'Finance Analysis Lens',
                'The learning question depends on the track: bankers value companies and transactions, asset managers evaluate securities and portfolios, and PE investors assess deals and returns.',
                [
                    'IB: what is the company worth and how does a transaction change value?',
                    'AM: what return, risk, and benchmark exposure does this security add?',
                    'PE: can the deal create enough equity value after leverage and exit?'
                ],
                'Choose one track: investment banking, asset management, or private equity.',
                $style
            );
        }

        if ($track === 'asset_management') {
            return $this->format_tutor_fallback_lesson(
                'Let us use an asset management lens.',
                'Active Return and Attribution',
                'Asset managers need to explain not just performance, but the source of performance versus a benchmark.',
                [
                    'Portfolio return: 9%',
                    'Benchmark return: 7%',
                    'Active return: 9% - 7% = 2%',
                    'Attribution then asks whether that 2% came from allocation, selection, or factor exposure'
                ],
                'If a fund returns 6% and the benchmark returns 4.5%, what is active return?',
                $style
            );
        }

        if ($track === 'investment_banking') {
            return $this->format_tutor_fallback_lesson(
                'Let us use an investment banking lens.',
                'Enterprise Value from Trading Comps',
                'Bankers often start with operating metrics and market multiples to estimate enterprise value.',
                [
                    'Company EBITDA: GBP 25m',
                    'Selected EV/EBITDA multiple: 9.0x',
                    'Enterprise value: GBP 25m x 9.0x = GBP 225m',
                    'Equity value then equals enterprise value minus net debt'
                ],
                'A company has GBP 40m EBITDA and trades at 7.5x EV/EBITDA. What is enterprise value?',
                $style
            );
        }

        if (strpos($message_lower, 'debt') !== false) {
            return $this->format_tutor_fallback_lesson(
                'Good, we are now in the financing part of the model.',
                'Debt Schedule',
                'The debt schedule tracks how opening debt becomes ending debt after repayments. Interest affects cash flow, but principal repayment is what lowers debt.',
                [
                    'Opening debt: GBP 100m',
                    'Mandatory repayment: GBP 5m',
                    'Cash sweep: GBP 10m',
                    'Ending debt: GBP 100m - GBP 5m - GBP 10m = GBP 85m'
                ],
                'Try this: opening debt is GBP 120m, mandatory repayment is GBP 6m, and cash sweep is GBP 14m. What is ending debt?',
                $style
            );
        }

        if (strpos($message_lower, 'irr') !== false || strpos($message_lower, 'moic') !== false) {
            return $this->format_tutor_fallback_lesson(
                'Now we are measuring the sponsor outcome.',
                'MOIC and IRR',
                'MOIC measures total money made. IRR measures speed. The same MOIC is better when achieved faster.',
                [
                    'Sponsor equity invested: GBP 80m',
                    'Exit equity value: GBP 200m',
                    'MOIC = GBP 200m / GBP 80m = 2.5x'
                ],
                'If a sponsor invests GBP 100m and exits for GBP 220m, what is the MOIC?',
                $style
            );
        }

        return $this->format_tutor_fallback_lesson(
            'We will build the lesson from first principles.',
            'Finance Technical Fundamentals',
            'The core tracks are investment banking valuation and transactions, asset management portfolio analysis, and private equity deal returns.',
            [
                'IB: convert company performance into valuation and transaction impact',
                'AM: connect securities to portfolio risk, return, and benchmark outcomes',
                'PE: connect purchase price, leverage, cash flow, and exit value'
            ],
            'Which track do you want next: investment banking, asset management, or private equity?',
            $style
        );
    }

    private function detect_finance_learning_track($message_lower)
    {
        if (preg_match('/\b(asset management|portfolio|benchmark|duration|bond|fixed income|equity research|stock pitch|sharpe|tracking error|attribution|fund)\b/', $message_lower)) {
            return 'asset_management';
        }
        if (preg_match('/\b(investment banking|ib|m&a|merger|acquisition|dcf|comps|precedent|accretion|dilution|eps|pitchbook|ipo)\b/', $message_lower)) {
            return 'investment_banking';
        }
        if (preg_match('/\b(private equity|lbo|buyout|sponsor|moic|cash sweep|debt schedule|carry)\b/', $message_lower)) {
            return 'private_equity';
        }
        return 'general_finance';
    }

    private function detect_tutor_learning_style($message)
    {
        $lower = strtolower($message);
        if (preg_match('/\b(simple|beginner|confused|lost|explain like)\b/', $lower)) {
            return 'beginner';
        }
        if (preg_match('/\b(formula|calculate|math|number|model|excel)\b/', $lower)) {
            return 'numeric';
        }
        if (preg_match('/\b(why|intuition|concept|conceptual)\b/', $lower)) {
            return 'conceptual';
        }
        if (preg_match('/\b(short|brief|quick|concise)\b/', $lower)) {
            return 'concise';
        }
        return 'balanced';
    }

    private function is_job_related_tutor_query($message_lower)
    {
        return preg_match('/\b(job|jobs|role|roles|opening|openings|opportunit|hiring|recruit|application|apply|cv|resume|salary|compensation|interview)\b/', $message_lower);
    }

    private function format_tutor_fallback_lesson($continuation, $title, $concept, $steps, $practice, $style)
    {
        $style_note = '';
        if ($style === 'beginner') {
            $style_note = '<p><strong>Plain-English lens:</strong> understand the direction first, then the formula.</p>';
        } elseif ($style === 'numeric') {
            $style_note = '<p><strong>Model lens:</strong> write the formula, plug in the inputs, then sanity-check the output.</p>';
        } elseif ($style === 'conceptual') {
            $style_note = '<p><strong>Intuition:</strong> value creation comes from paying the right price, improving the business, and reducing debt.</p>';
        } elseif ($style === 'concise') {
            $style_note = '<p><strong>Short version:</strong> one rule, one example, one check.</p>';
        }

        $items = '';
        foreach ($steps as $step) {
            $items .= '<li>' . $step . '</li>';
        }

        return '<p>' . $continuation . '</p>'
            . '<h3>' . $title . '</h3>'
            . '<p>' . $concept . '</p>'
            . $style_note
            . '<h4>Worked Example</h4>'
            . '<ul>' . $items . '</ul>'
            . '<h4>Your Turn</h4>'
            . '<p>' . $practice . '</p>';
    }


    /**
     * Generate salary insights
     */
    private function generate_salary_insights($context)
    {
        // Use default market range for analysis
        $salaries = [100000, 120000, 140000]; // Default market range

        $min_salary = min($salaries);
        $max_salary = max($salaries);
        $avg_salary = array_sum($salaries) / count($salaries);

        $content = "**Compensation Analysis:**\n\n";
        $content .= "Market range for similar roles: $" . number_format($min_salary) . " - $" . number_format($max_salary) . "\n";
        $content .= "Average compensation: $" . number_format($avg_salary) . "\n\n";

        $content .= "**Negotiation Strategy:**\n";
        $content .= "• Target the 75th percentile: $" . number_format($avg_salary * 1.15) . "\n";
        $content .= "• Use competing offers to justify higher range\n";
        $content .= "• Focus on total compensation, not just base\n";
        $content .= "• Consider signing bonuses and equity\n";

        return [
            'type' => 'analysis',
            'content' => $content,
            'cards' => [
                [
                    'type' => 'salary',
                    'title' => 'Compensation Insights',
                    'data' => [
                        'market_range' => '$' . round($min_salary / 1000) . 'k - $' . round($max_salary / 1000) . 'k',
                        'your_target' => '$' . round($avg_salary * 1.15 / 1000) . 'k',
                        'percentile' => '75',
                        'factors' => [
                            'Experience' => '+10-15%',
                            'Competing Offers' => '+5-10%',
                            'Specialized Skills' => '+5-8%'
                        ]
                    ]
                ]
            ],
            'suggestions' => [
                "How to negotiate effectively",
                "Evaluate total compensation",
                "Compare with market rates",
                "Timing negotiation tactics"
            ]
        ];
    }

    /**
     * Generate interview tips
     */
    private function generate_interview_tips($context)
    {
        $company = 'your target company';
        $role = 'this role';

        $content = "**Interview Preparation Strategy:**\n\n";
        $content .= "For {$role} at {$company}, focus on these key areas:\n\n";

        $content .= "**1. Behavioral Questions (STAR Method):**\n";
        $content .= "• Leadership: Prepare 3 examples of leading through ambiguity\n";
        $content .= "• Problem-solving: Highlight analytical frameworks used\n";
        $content .= "• Collaboration: Emphasize cross-functional success\n\n";

        $content .= "**2. Technical Competencies:**\n";
        $content .= "• Review industry-specific frameworks\n";
        $content .= "• Prepare case study approach\n";
        $content .= "• Quantify all achievements\n\n";

        $content .= "**3. Strategic Questions to Ask:**\n";
        $content .= "• \"What are the 90-day priorities for this role?\"\n";
        $content .= "• \"How does this position contribute to strategic objectives?\"\n";
        $content .= "• \"What differentiates top performers here?\"\n";

        return [
            'type' => 'text',
            'content' => $content,
            'actions' => [
                ['label' => 'Practice Questions', 'action' => 'practice_interview'],
                ['label' => 'Company Research', 'action' => 'research_company']
            ],
            'suggestions' => [
                "Common questions for this role",
                "How to structure my answers",
                "Questions to ask the interviewer",
                "Post-interview follow-up"
            ]
        ];
    }

    /**
     * Generate application strategy
     */
    private function generate_application_strategy($context)
    {
        $content = "**Strategic Application Plan:**\n\n";

        $content .= "**Application Strategy:**\n";
        $content .= "• Customize each application with company-specific language\n";
        $content .= "• Lead with your most differentiating achievement\n";
        $content .= "• Mirror the job description's priority order\n";
        $content .= "• Include quantified results in every bullet point\n\n";

        $content .= "**Follow-up Protocol:**\n";
        $content .= "• Connect with hiring manager on LinkedIn (Day 1)\n";
        $content .= "• Send application confirmation (Day 2)\n";
        $content .= "• Follow up if no response (Day 7)\n";

        return [
            'type' => 'text',
            'content' => $content,
            'actions' => [
                ['label' => 'Generate Cover Letter Points', 'action' => 'cover_letter'],
                ['label' => 'Optimize Resume', 'action' => 'resume_optimization']
            ],
            'suggestions' => [
                "Cover letter key points",
                "Resume optimization tips",
                "LinkedIn outreach templates",
                "Follow-up email examples"
            ]
        ];
    }


    /**
     * Get strategic response for general queries
     */
    private function get_strategic_response($message, $context)
    {
        $responses = [
            "I'm here to help you navigate your career journey strategically. Based on your profile, I can provide personalized insights on opportunity selection, application strategy, interview preparation, and salary negotiation. What aspect would you like to explore first?",

            "Let's take a strategic approach to your career advancement. I can analyze your shortlisted opportunities, identify optimal application timing, or help you craft compelling narratives that resonate with hiring managers. What's your priority today?",

            "Career success requires both strategy and execution. I'm here to provide data-driven insights and actionable advice tailored to your unique situation. Would you like to start with opportunity analysis or application planning?"
        ];

        return $responses[array_rand($responses)];
    }

    /**
     * Generate contextual suggestions
     */
    private function generate_suggestions($content, $context)
    {
        $suggestions = [];

        // Based on content
        if (strpos($content, 'salary') !== false || strpos($content, 'compensation') !== false) {
            $suggestions[] = "How do I negotiate effectively?";
            $suggestions[] = "What benefits should I prioritize?";
        }

        if (strpos($content, 'interview') !== false) {
            $suggestions[] = "Common questions for my level";
            $suggestions[] = "How to handle difficult questions";
        }


        // Always include strategic options
        $strategic = [
            "Analyze market trends",
            "Evaluate career progression",
            "Assess company cultures",
            "Create 90-day plan"
        ];

        $suggestions = array_merge($suggestions, array_slice($strategic, 0, 4 - count($suggestions)));

        return array_slice(array_unique($suggestions), 0, 4);
    }

    /**
     * Generate relevant actions
     */
    private function generate_actions($content, $context)
    {
        $actions = [];

        if (strpos($content, 'application') !== false) {
            $actions[] = ['label' => 'Start Application', 'action' => 'apply'];
        }

        if (strpos($content, 'interview') !== false) {
            $actions[] = ['label' => 'Practice Interview', 'action' => 'practice'];
        }

        return $actions;
    }

    /**
     * Extract analysis cards from content
     */
    private function extract_analysis_cards($content, $context)
    {
        // This would parse structured content and generate appropriate cards
        return [];
    }

    /**
     * Identify key strength of a job
     */
    private function identify_key_strength($job)
    {
        if (($job['match_score'] ?? 0) >= 85) {
            return "Exceptional skill alignment";
        } elseif (isset($job['company_size']) && $job['company_size'] === 'Enterprise') {
            return "Established brand & resources";
        } else {
            return "Growth opportunity";
        }
    }

    /**
     * Assess growth potential
     */
    private function assess_growth_potential($job)
    {
        $score = $job['match_score'] ?? 75;
        if ($score >= 80) {
            return "High - immediate impact likely";
        } elseif ($score >= 70) {
            return "Moderate - strong foundation";
        } else {
            return "Developmental - stretch opportunity";
        }
    }

    /**
     * AJAX handler for context preparation
     */
    public function ajax_prepare_senna_context()
    {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        $user_id = get_current_user_id();
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');

        $context = [
            'user_id' => $user_id,
            'session_id' => $session_id,
            'timestamp' => current_time('mysql')
        ];

        wp_send_json_success(['context' => $context]);
    }

    /**
     * AJAX handler for application strategy
     */
    public function ajax_get_application_strategy()
    {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        $job_data = json_decode(stripslashes($_POST['job_data'] ?? '{}'), true);
        $user_profile = json_decode(stripslashes($_POST['user_profile'] ?? '{}'), true);

        $strategy = $this->generate_application_strategy([
            'shortlist' => [$job_data],
            'profile' => $user_profile
        ]);

        wp_send_json_success(['strategy' => $strategy]);
    }

    /**
     * AJAX handler for job fit analysis
     */
    public function ajax_analyze_job_fit()
    {
        check_ajax_referer('sffc_public_nonce', 'nonce');

        $job_data = json_decode(stripslashes($_POST['job_data'] ?? '{}'), true);
        $user_profile = json_decode(stripslashes($_POST['user_profile'] ?? '{}'), true);

        $analysis = [
            'match_score' => $this->calculate_match_score($job_data, $user_profile),
            'strengths' => $this->identify_strengths($job_data, $user_profile),
            'gaps' => $this->identify_gaps($job_data, $user_profile),
            'strategy' => $this->create_fit_strategy($job_data, $user_profile)
        ];

        wp_send_json_success(['analysis' => $analysis]);
    }

    /**
     * Calculate match score
     */
    private function calculate_match_score($job, $profile)
    {
        // Simplified scoring logic
        $base_score = 70;

        if (isset($profile['experience_years']) && isset($job['experience_required'])) {
            if ($profile['experience_years'] >= $job['experience_required']) {
                $base_score += 10;
            }
        }

        if (isset($profile['skills']) && isset($job['skills'])) {
            $matching_skills = array_intersect($profile['skills'], $job['skills']);
            $skill_match = count($matching_skills) / max(count($job['skills']), 1);
            $base_score += $skill_match * 15;
        }

        return min(95, $base_score);
    }

    /**
     * Identify strengths
     */
    private function identify_strengths($job, $profile)
    {
        return [
            "Strong technical skill alignment",
            "Relevant industry experience",
            "Cultural fit indicators positive"
        ];
    }

    /**
     * Identify gaps
     */
    private function identify_gaps($job, $profile)
    {
        return [
            "Consider strengthening leadership examples",
            "Highlight more quantitative achievements"
        ];
    }

    /**
     * Create fit strategy
     */
    private function create_fit_strategy($job, $profile)
    {
        return "Position yourself as a strategic hire who can deliver immediate value while bringing fresh perspectives. Emphasize your unique combination of skills and experience.";
    }

    /**
     * AJAX handler for Claude queries from Ultimate interface
     */
    public function ajax_claude_query()
    {
        // Verify nonce
        if (!check_ajax_referer('sffc_public_nonce', 'nonce', false)) {
            if (!check_ajax_referer('sffc_frontend_nonce', 'nonce', false)) {
                wp_send_json_error(['message' => 'Security check failed']);
                return;
            }
        }

        $message = sanitize_text_field($_POST['message'] ?? '');
        $context = json_decode(stripslashes($_POST['context'] ?? '{}'), true);

        if (empty($message)) {
            wp_send_json_error(['message' => 'No message provided']);
            return;
        }

        // Process with Claude or fallback
        $query_data = [
            'message' => $message,
            'context' => $context,
            'mode' => 'career_strategy'
        ];

        $response = $this->process_career_query($query_data);

        if ($response && isset($response['content'])) {
            wp_send_json_success(['response' => $response['content']]);
        } else {
            // Use intelligent fallback
            $fallback = $this->generate_intelligent_fallback($message, $context);
            wp_send_json_success(['response' => $fallback['content'] ?? 'Let me help you with your career strategy.']);
        }
    }
}

// Initialize
SFFC_Senna_Integration_Helper::get_instance();
