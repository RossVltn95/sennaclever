<?php
/**
 * Mock Interview System
 * AI-powered interview simulator for IB, PE, HF, Consulting
 *
 * @package SennaCareers
 * @subpackage Learning
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Mock_Interview {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get interview types
     */
    public function get_interview_types() {
        return [
            'investment_banking' => [
                'name' => 'Investment Banking',
                'icon' => '🏦',
                'description' => 'Technical and behavioral questions for IB roles',
                'difficulty_levels' => ['Analyst', 'Associate', 'VP']
            ],
            'private_equity' => [
                'name' => 'Private Equity',
                'icon' => '💼',
                'description' => 'LBO modeling, deal experience, and market views',
                'difficulty_levels' => ['Associate', 'Senior Associate', 'VP']
            ],
            'hedge_fund' => [
                'name' => 'Hedge Fund',
                'icon' => '📈',
                'description' => 'Stock pitches, portfolio management, and market knowledge',
                'difficulty_levels' => ['Analyst', 'Associate', 'PM']
            ],
            'consulting' => [
                'name' => 'Management Consulting',
                'icon' => '💡',
                'description' => 'Case studies, frameworks, and problem-solving',
                'difficulty_levels' => ['Analyst', 'Consultant', 'Manager']
            ]
        ];
    }

    /**
     * Get interview question bank by type and difficulty
     */
    public function get_interview_questions($interview_type, $difficulty_level) {
        $questions = [];

        switch ($interview_type) {
            case 'investment_banking':
                $questions = $this->get_ib_questions($difficulty_level);
                break;
            case 'private_equity':
                $questions = $this->get_pe_questions($difficulty_level);
                break;
            case 'hedge_fund':
                $questions = $this->get_hf_questions($difficulty_level);
                break;
            case 'consulting':
                $questions = $this->get_consulting_questions($difficulty_level);
                break;
        }

        // Shuffle and select 8 questions for the interview
        shuffle($questions);
        return array_slice($questions, 0, 8);
    }

    /**
     * Investment Banking questions
     */
    private function get_ib_questions($level) {
        $questions = [
            // Technical - Accounting
            [
                'category' => 'technical',
                'question' => 'Walk me through the three financial statements and how they\'re linked.',
                'what_tested' => 'Understanding of financial statement fundamentals',
                'key_points' => [
                    'Income statement shows revenues and expenses',
                    'Net income flows to balance sheet (retained earnings) and cash flow statement',
                    'Cash flow statement reconciles net income to cash',
                    'Balance sheet must balance (Assets = Liabilities + Equity)'
                ],
                'common_mistakes' => 'Not explaining the circular linkages clearly',
                'ideal_time' => '2-3 minutes'
            ],
            [
                'category' => 'technical',
                'question' => 'If depreciation increases by $10, walk me through the impact on the three statements.',
                'what_tested' => 'Understanding of non-cash charges and tax effects',
                'key_points' => [
                    'Income statement: Operating income down by $10, but tax savings of $3-4',
                    'Net income down by $6-7',
                    'Cash flow statement: Add back $10 depreciation, net cash up by $3-4',
                    'Balance sheet: PP&E down by $10, cash up by $3-4, retained earnings down by $6-7'
                ],
                'common_mistakes' => 'Forgetting the tax shield on depreciation',
                'ideal_time' => '2-3 minutes'
            ],
            [
                'category' => 'technical',
                'question' => 'How do you calculate enterprise value?',
                'what_tested' => 'Valuation fundamentals',
                'key_points' => [
                    'EV = Market Cap + Debt + Preferred Stock + Minority Interest - Cash',
                    'Explains why we add debt (acquiring company assumes it)',
                    'Explains why we subtract cash (it\'s available to pay down debt)',
                    'EV represents the takeover price of a company'
                ],
                'common_mistakes' => 'Not explaining the logic behind each component',
                'ideal_time' => '1-2 minutes'
            ],
            [
                'category' => 'technical',
                'question' => 'Walk me through a DCF valuation.',
                'what_tested' => 'Core valuation methodology',
                'key_points' => [
                    '1. Project free cash flows (5-10 years)',
                    '2. Calculate discount rate (WACC)',
                    '3. Calculate terminal value',
                    '4. Discount all cash flows to present',
                    '5. Add PV of cash flows and terminal value = Enterprise Value',
                    '6. Subtract net debt to get equity value'
                ],
                'common_mistakes' => 'Not mentioning terminal value or discount rate',
                'ideal_time' => '3-4 minutes'
            ],
            [
                'category' => 'technical',
                'question' => 'What are the three main valuation methodologies?',
                'what_tested' => 'Valuation knowledge breadth',
                'key_points' => [
                    '1. Intrinsic (DCF) - discounted cash flow analysis',
                    '2. Comparable Companies - trading multiples',
                    '3. Precedent Transactions - acquisition multiples',
                    'Each has pros and cons',
                    'DCF most theoretically sound but sensitive to assumptions'
                ],
                'common_mistakes' => 'Not explaining when to use each method',
                'ideal_time' => '2-3 minutes'
            ],
            // M&A Questions
            [
                'category' => 'technical',
                'question' => 'Walk me through an M&A process from start to finish.',
                'what_tested' => 'Understanding of deal flow',
                'key_points' => [
                    '1. Target identification and approach',
                    '2. NDA and initial discussions',
                    '3. Management presentation and CIM',
                    '4. First round bids (indicative)',
                    '5. Due diligence',
                    '6. Final bids and negotiation',
                    '7. Definitive agreement',
                    '8. Closing'
                ],
                'common_mistakes' => 'Missing key steps or wrong order',
                'ideal_time' => '2-3 minutes'
            ],
            [
                'category' => 'technical',
                'question' => 'What is accretion/dilution analysis?',
                'what_tested' => 'M&A impact analysis',
                'key_points' => [
                    'Measures impact of acquisition on acquirer\'s EPS',
                    'Accretive if combined EPS > standalone EPS',
                    'Dilutive if combined EPS < standalone EPS',
                    'Depends on: price paid, synergies, deal structure',
                    'All-cash vs all-stock has different impacts'
                ],
                'common_mistakes' => 'Not mentioning synergies or deal structure',
                'ideal_time' => '2 minutes'
            ],
            // Behavioral
            [
                'category' => 'behavioral',
                'question' => 'Why investment banking?',
                'what_tested' => 'Motivation and career clarity',
                'key_points' => [
                    'Genuine interest in finance/deals',
                    'Desire to learn financial modeling and valuation',
                    'Exposure to different industries and companies',
                    'Strong work ethic and ability to handle pressure',
                    'Platform for long-term career in finance'
                ],
                'common_mistakes' => 'Generic answers or focusing only on money/prestige',
                'ideal_time' => '1-2 minutes'
            ],
            [
                'category' => 'behavioral',
                'question' => 'Tell me about a time you worked on a team project under a tight deadline.',
                'what_tested' => 'Teamwork and time management',
                'key_points' => [
                    'Use STAR method (Situation, Task, Action, Result)',
                    'Specific example with details',
                    'Your specific role and contributions',
                    'How you handled pressure',
                    'Positive outcome'
                ],
                'common_mistakes' => 'Vague examples or taking all credit',
                'ideal_time' => '2-3 minutes'
            ],
            [
                'category' => 'behavioral',
                'question' => 'Why our bank specifically?',
                'what_tested' => 'Research and genuine interest',
                'key_points' => [
                    'Specific deals the bank worked on',
                    'Industry expertise or geographic strength',
                    'Culture and mentorship',
                    'Recent news or initiatives',
                    'Connection to your interests/background'
                ],
                'common_mistakes' => 'Generic answers that apply to any bank',
                'ideal_time' => '1-2 minutes'
            ],
            // Case/Market
            [
                'category' => 'case',
                'question' => 'Pitch me a stock (long or short).',
                'what_tested' => 'Investment thinking and communication',
                'key_points' => [
                    'Company overview and business model',
                    'Investment thesis (why now?)',
                    'Key drivers and catalysts',
                    'Valuation support',
                    'Risks to thesis',
                    'Clear recommendation'
                ],
                'common_mistakes' => 'No clear thesis or weak valuation support',
                'ideal_time' => '3-5 minutes'
            ],
            [
                'category' => 'case',
                'question' => 'Tell me about a recent deal you\'ve been following.',
                'what_tested' => 'Market awareness and deal understanding',
                'key_points' => [
                    'Deal details (buyer, target, price)',
                    'Strategic rationale',
                    'Valuation (multiple paid)',
                    'Your opinion on the deal',
                    'Potential issues or synergies'
                ],
                'common_mistakes' => 'Surface-level knowledge without depth',
                'ideal_time' => '2-3 minutes'
            ]
        ];

        // Filter by difficulty level
        if ($level === 'Analyst') {
            // Return more fundamental questions
            return array_slice($questions, 0, 10);
        } elseif ($level === 'Associate') {
            // Mix of fundamental and advanced
            return $questions;
        } else {
            // More complex, strategic questions
            return array_slice($questions, 4, 12);
        }
    }

    /**
     * Private Equity questions
     */
    private function get_pe_questions($level) {
        $questions = [
            [
                'category' => 'technical',
                'question' => 'Walk me through an LBO model.',
                'what_tested' => 'Core PE technical skill',
                'key_points' => [
                    '1. Sources & Uses (how deal is funded)',
                    '2. Operating model (5-year projections)',
                    '3. Debt schedule (paydown over time)',
                    '4. Returns calculation (IRR, MOIC)',
                    '5. Exit assumptions (multiple method)',
                    'Sensitivity analysis on key drivers'
                ],
                'common_mistakes' => 'Missing debt schedule or returns calc',
                'ideal_time' => '3-4 minutes'
            ],
            [
                'category' => 'technical',
                'question' => 'What makes a good LBO candidate?',
                'what_tested' => 'Understanding of PE investment criteria',
                'key_points' => [
                    'Stable, predictable cash flows',
                    'Low capex requirements',
                    'Asset-light business model',
                    'Strong market position/brand',
                    'Management quality',
                    'Growth potential',
                    'Low cyclicality'
                ],
                'common_mistakes' => 'Only mentioning cash flow without other factors',
                'ideal_time' => '2-3 minutes'
            ],
            [
                'category' => 'technical',
                'question' => 'How do you calculate IRR?',
                'what_tested' => 'Returns measurement knowledge',
                'key_points' => [
                    'Discount rate that makes NPV = 0',
                    'Equity invested vs equity value at exit',
                    'Time-weighted return',
                    'Formula or Excel IRR function',
                    'Typically target 20%+ for PE'
                ],
                'common_mistakes' => 'Confusing with simple ROI',
                'ideal_time' => '1-2 minutes'
            ],
            [
                'category' => 'case',
                'question' => 'Would you buy this company at 10x EBITDA?',
                'what_tested' => 'Quick LBO feasibility analysis',
                'key_points' => [
                    'Need more information (industry, growth, margins)',
                    'Compare to industry multiples',
                    'Run quick LBO math (can we get 20% IRR?)',
                    'Consider exit multiple (10x entry, what exit?)',
                    'Debt capacity (leverage multiples)',
                    'Value creation opportunities'
                ],
                'common_mistakes' => 'Yes/no answer without analysis',
                'ideal_time' => '3-4 minutes'
            ],
            [
                'category' => 'behavioral',
                'question' => 'Why private equity over investment banking?',
                'what_tested' => 'Career clarity and PE understanding',
                'key_points' => [
                    'Principal investing vs advisory',
                    'Long-term value creation focus',
                    'Closer to operations and strategy',
                    'Ownership mindset',
                    'Interest in specific sectors'
                ],
                'common_mistakes' => 'Only mentioning lifestyle or compensation',
                'ideal_time' => '1-2 minutes'
            ],
            [
                'category' => 'behavioral',
                'question' => 'Tell me about a deal you worked on.',
                'what_tested' => 'Deal experience and storytelling',
                'key_points' => [
                    'Company overview and sector',
                    'Your specific role',
                    'Key challenges',
                    'Analytical work performed',
                    'Outcome',
                    'What you learned'
                ],
                'common_mistakes' => 'Too high-level or unclear on your contribution',
                'ideal_time' => '3-4 minutes'
            ],
            [
                'category' => 'case',
                'question' => 'Pitch me a stock.',
                'what_tested' => 'Investment thinking',
                'key_points' => [
                    'Strong thesis with catalyst',
                    'Valuation support',
                    'Industry tailwinds',
                    'Risks acknowledged',
                    'Clear recommendation'
                ],
                'common_mistakes' => 'Weak thesis or no valuation work',
                'ideal_time' => '4-5 minutes'
            ],
            [
                'category' => 'case',
                'question' => 'What sectors are you interested in and why?',
                'what_tested' => 'Sector knowledge and passion',
                'key_points' => [
                    'Specific sector(s)',
                    'Current trends and dynamics',
                    'Recent deals in sector',
                    'Why personally interested',
                    'Connects to PE investing'
                ],
                'common_mistakes' => 'Generic interest with no depth',
                'ideal_time' => '2-3 minutes'
            ]
        ];

        return $questions;
    }

    /**
     * Hedge Fund questions
     */
    private function get_hf_questions($level) {
        $questions = [
            [
                'category' => 'market',
                'question' => 'What\'s happening in markets today?',
                'what_tested' => 'Market awareness and daily preparation',
                'key_points' => [
                    'Major indices performance',
                    'Key market drivers (news, economic data)',
                    'Sector rotation',
                    'Notable stock movers',
                    'Your view on sustainability'
                ],
                'common_mistakes' => 'Not checking markets before interview',
                'ideal_time' => '2-3 minutes'
            ],
            [
                'category' => 'technical',
                'question' => 'Pitch me a long idea.',
                'what_tested' => 'Investment research and conviction',
                'key_points' => [
                    'Investment thesis (why undervalued?)',
                    'Catalyst for re-rating',
                    'Valuation support (comps, DCF)',
                    'Industry tailwinds',
                    'Risk factors',
                    'Position sizing/timing'
                ],
                'common_mistakes' => 'Weak thesis or no catalyst',
                'ideal_time' => '5-7 minutes'
            ],
            [
                'category' => 'technical',
                'question' => 'Pitch me a short idea.',
                'what_tested' => 'Short-side research skills',
                'key_points' => [
                    'Why overvalued?',
                    'Business deterioration thesis',
                    'Accounting red flags',
                    'Valuation stretched',
                    'Negative catalysts coming',
                    'Risk management (stop loss)'
                ],
                'common_mistakes' => 'Not addressing short risks (timing, borrow cost)',
                'ideal_time' => '5-7 minutes'
            ],
            [
                'category' => 'technical',
                'question' => 'How do you generate investment ideas?',
                'what_tested' => 'Investment process',
                'key_points' => [
                    'Screens and filters',
                    'Industry research',
                    'Reading earnings transcripts',
                    'Expert calls',
                    'Competitor analysis',
                    'Proprietary data sources'
                ],
                'common_mistakes' => 'No clear process or only stock screeners',
                'ideal_time' => '2-3 minutes'
            ],
            [
                'category' => 'case',
                'question' => 'How do you construct a portfolio?',
                'what_tested' => 'Portfolio management skills',
                'key_points' => [
                    'Position sizing methodology',
                    'Sector diversification',
                    'Long/short balance',
                    'Risk management (stop losses, hedges)',
                    'Concentration limits',
                    'Rebalancing process'
                ],
                'common_mistakes' => 'No risk management discussion',
                'ideal_time' => '3-4 minutes'
            ],
            [
                'category' => 'behavioral',
                'question' => 'Tell me about a losing trade.',
                'what_tested' => 'Honesty and learning from mistakes',
                'key_points' => [
                    'What went wrong',
                    'When you realized it',
                    'How you handled exit',
                    'What you learned',
                    'How you improved process'
                ],
                'common_mistakes' => 'Blaming external factors only',
                'ideal_time' => '2-3 minutes'
            ]
        ];

        return $questions;
    }

    /**
     * Consulting questions
     */
    private function get_consulting_questions($level) {
        $questions = [
            [
                'category' => 'case',
                'question' => 'A retail company is experiencing declining profits. How would you approach this?',
                'what_tested' => 'Profitability framework',
                'key_points' => [
                    'Clarifying questions first',
                    'Profit = Revenue - Costs structure',
                    'Revenue: volume and price analysis',
                    'Costs: fixed and variable breakdown',
                    'Industry trends and competition',
                    'Hypothesis-driven approach'
                ],
                'common_mistakes' => 'Jumping to solutions without structure',
                'ideal_time' => '30-40 minutes (full case)'
            ],
            [
                'category' => 'case',
                'question' => 'Estimate the market size for coffee shops in London.',
                'what_tested' => 'Market sizing and estimation',
                'key_points' => [
                    'Clear assumptions stated',
                    'Top-down or bottom-up approach',
                    'Logical breakdowns',
                    'Round numbers for easy math',
                    'Sanity check at the end'
                ],
                'common_mistakes' => 'Not stating assumptions clearly',
                'ideal_time' => '10-15 minutes'
            ],
            [
                'category' => 'behavioral',
                'question' => 'Why consulting?',
                'what_tested' => 'Career motivation',
                'key_points' => [
                    'Problem-solving interest',
                    'Exposure to industries',
                    'Learning and development',
                    'Team environment',
                    'Impact on businesses'
                ],
                'common_mistakes' => 'Only mentioning travel or prestige',
                'ideal_time' => '1-2 minutes'
            ],
            [
                'category' => 'behavioral',
                'question' => 'Tell me about a time you led a team.',
                'what_tested' => 'Leadership skills',
                'key_points' => [
                    'STAR method',
                    'Specific situation',
                    'Your leadership actions',
                    'How you motivated team',
                    'Positive outcome',
                    'What you learned'
                ],
                'common_mistakes' => 'Vague example or no outcome',
                'ideal_time' => '2-3 minutes'
            ]
        ];

        return $questions;
    }

    /**
     * Evaluate user answer using AI
     */
    public function evaluate_answer($question, $user_answer, $interview_context) {
        // In production, this would call Claude API
        // For now, return structured feedback format

        $feedback = [
            'score' => 0,
            'strengths' => [],
            'missed_points' => [],
            'better_answer' => '',
            'follow_up_questions' => []
        ];

        // Simulate AI evaluation based on question metadata
        $key_points = $question['key_points'] ?? [];
        $user_answer_lower = strtolower($user_answer);

        // Simple scoring logic (in production, use Claude API)
        $points_covered = 0;
        foreach ($key_points as $point) {
            // Check if user mentioned key concepts
            $keywords = explode(' ', strtolower($point));
            $found = false;
            foreach ($keywords as $keyword) {
                if (strlen($keyword) > 4 && strpos($user_answer_lower, $keyword) !== false) {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                $points_covered++;
                $feedback['strengths'][] = 'Mentioned ' . substr($point, 0, 50) . '...';
            } else {
                $feedback['missed_points'][] = $point;
            }
        }

        // Calculate score
        $total_points = count($key_points);
        $feedback['score'] = $total_points > 0 ? round(($points_covered / $total_points) * 10, 1) : 5;

        // Adjust for answer length
        $word_count = str_word_count($user_answer);
        if ($word_count < 50) {
            $feedback['score'] -= 1;
            $feedback['missed_points'][] = 'Answer could be more detailed';
        } elseif ($word_count > 300) {
            $feedback['score'] -= 0.5;
            $feedback['missed_points'][] = 'Answer could be more concise';
        }

        // Generate better answer suggestion
        $feedback['better_answer'] = $this->generate_model_answer($question);

        return $feedback;
    }

    /**
     * Generate model answer for a question
     */
    private function generate_model_answer($question) {
        $key_points = $question['key_points'] ?? [];

        $model_answer = "Here's a strong way to answer:\n\n";
        foreach ($key_points as $i => $point) {
            $model_answer .= ($i + 1) . ". " . $point . "\n";
        }

        return $model_answer;
    }

    /**
     * Calculate overall interview score
     */
    public function calculate_interview_score($all_feedback) {
        $total_score = 0;
        $count = count($all_feedback);

        if ($count === 0) {
            return [
                'overall' => 0,
                'technical' => 0,
                'behavioral' => 0,
                'case' => 0
            ];
        }

        $category_scores = [
            'technical' => [],
            'behavioral' => [],
            'case' => [],
            'market' => []
        ];

        foreach ($all_feedback as $feedback) {
            $score = $feedback['score'];
            $category = $feedback['question']['category'] ?? 'technical';

            $total_score += $score;
            $category_scores[$category][] = $score;
        }

        $result = [
            'overall' => round(($total_score / $count) * 10, 1), // Convert to /100 scale
            'technical' => 0,
            'behavioral' => 0,
            'case' => 0
        ];

        // Calculate category averages
        if (!empty($category_scores['technical'])) {
            $result['technical'] = round((array_sum($category_scores['technical']) / count($category_scores['technical'])) * 10, 1);
        }
        if (!empty($category_scores['behavioral'])) {
            $result['behavioral'] = round((array_sum($category_scores['behavioral']) / count($category_scores['behavioral'])) * 10, 1);
        }
        if (!empty($category_scores['case']) || !empty($category_scores['market'])) {
            $case_scores = array_merge($category_scores['case'], $category_scores['market']);
            $result['case'] = round((array_sum($case_scores) / count($case_scores)) * 10, 1);
        }

        return $result;
    }

    /**
     * Generate recommendations based on weak areas
     */
    public function generate_recommendations($all_feedback, $scores) {
        $recommendations = [];

        // Identify weak areas
        if ($scores['technical'] < 70) {
            $recommendations[] = [
                'area' => 'Technical Knowledge',
                'course_id' => null, // Will be populated with actual course IDs
                'suggestion' => 'Review financial modeling fundamentals',
                'priority' => 'high'
            ];
        }

        if ($scores['behavioral'] < 70) {
            $recommendations[] = [
                'area' => 'Behavioral Responses',
                'course_id' => null,
                'suggestion' => 'Practice STAR method for behavioral questions',
                'priority' => 'medium'
            ];
        }

        if ($scores['case'] < 70) {
            $recommendations[] = [
                'area' => 'Case Studies',
                'course_id' => null,
                'suggestion' => 'Work through more case study examples',
                'priority' => 'high'
            ];
        }

        // Add specific recommendations based on commonly missed points
        $all_missed = [];
        foreach ($all_feedback as $feedback) {
            $all_missed = array_merge($all_missed, $feedback['missed_points']);
        }

        // Identify patterns in missed points
        $missed_counts = array_count_values($all_missed);
        arsort($missed_counts);

        return $recommendations;
    }

    /**
     * Save interview to database
     */
    public function save_interview($user_id, $interview_data) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_mock_interviews';

        $inserted = $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'interview_type' => $interview_data['type'],
                'difficulty_level' => $interview_data['level'],
                'questions' => json_encode($interview_data['questions']),
                'user_responses' => json_encode($interview_data['responses']),
                'ai_feedback' => json_encode($interview_data['feedback']),
                'overall_score' => $interview_data['scores']['overall'],
                'technical_score' => $interview_data['scores']['technical'],
                'behavioral_score' => $interview_data['scores']['behavioral'],
                'case_score' => $interview_data['scores']['case'],
                'strengths' => json_encode($interview_data['strengths']),
                'areas_for_improvement' => json_encode($interview_data['improvements']),
                'recommended_courses' => json_encode($interview_data['recommendations']),
                'conducted_at' => current_time('mysql'),
                'duration_seconds' => $interview_data['duration']
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%d']
        );

        if ($inserted) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get user interview history
     */
    public function get_user_interviews($user_id, $limit = 10) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_mock_interviews';

        $interviews = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table
             WHERE user_id = %d
             ORDER BY conducted_at DESC
             LIMIT %d",
            $user_id,
            $limit
        ), ARRAY_A);

        return $interviews;
    }

    /**
     * Get interview statistics
     */
    public function get_interview_stats($user_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_mock_interviews';

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_interviews,
                AVG(overall_score) as avg_score,
                MAX(overall_score) as best_score,
                AVG(duration_seconds) as avg_duration
             FROM $table
             WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        // Get score trend (last 5 interviews)
        $recent = $wpdb->get_results($wpdb->prepare(
            "SELECT overall_score, conducted_at
             FROM $table
             WHERE user_id = %d
             ORDER BY conducted_at DESC
             LIMIT 5",
            $user_id
        ), ARRAY_A);

        $stats['score_trend'] = array_reverse($recent);

        return $stats;
    }
}

// Initialize
SFFC_Mock_Interview::get_instance();
