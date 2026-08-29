<?php
/**
 * Seed Courses - Create initial course catalog
 *
 * @package SennaCareers
 * @subpackage Learning
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Seed_Courses {

    private static $instance = null;
    private $course_meta_handler;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Load course meta handler
        require_once SFFC_PLUGIN_DIR . 'includes/learning/class-course-meta-fields.php';
        $this->course_meta_handler = SFFC_Course_Meta_Fields::get_instance();
    }

    /**
     * Create all seed courses
     * Called during plugin activation or via admin action
     */
    public function create_seed_courses() {
        // Check if courses already exist
        if (get_option('sffc_seed_courses_created', false)) {
            return; // Already seeded
        }

        $courses = [
            $this->get_course_data_3_statement(),
            $this->get_course_data_dcf(),
            $this->get_course_data_lbo(),
            $this->get_course_data_excel(),
            $this->get_course_data_ib_interview()
        ];

        foreach ($courses as $course_data) {
            $course_id = $this->course_meta_handler->create_course(
                $course_data['title'],
                $course_data['meta']
            );

            if (!is_wp_error($course_id)) {
                // Store course modules as separate post meta for easy retrieval
                if (isset($course_data['modules'])) {
                    update_post_meta($course_id, 'course_modules', json_encode($course_data['modules']));
                }
            }
        }

        // Mark as seeded
        update_option('sffc_seed_courses_created', true);
    }

    /**
     * Course 1: 3-Statement Financial Modeling (Beginner)
     */
    private function get_course_data_3_statement() {
        return [
            'title' => '3-Statement Financial Modeling',
            'meta' => [
                'course_provider' => 'MENA Careers Academy',
                'learning_mode' => 'Self-paced',
                'category' => 'Financial Modeling',
                'difficulty_level' => 'Beginner',
                'estimated_hours' => 6,
                'total_lessons' => 12,
                'total_quizzes' => 3,
                'has_certificate' => true,
                'has_ai_tutor' => true,
                'instructor_name' => 'Claude AI + Expert Review',
                'certification_type' => 'MENA Careers Certified - Financial Statements',
                'price' => 'Free',
                'prerequisites' => 'Basic accounting knowledge helpful but not required',
                'skills_taught' => ['Income Statement Modeling', 'Balance Sheet Linking', 'Cash Flow Construction', 'Excel Formulas', 'Financial Analysis'],
                'description' => 'Master the foundation of financial modeling by building integrated 3-statement models from scratch. Learn how income statements, balance sheets, and cash flow statements connect and flow together.',
                'learning_outcomes' => [
                    'Build integrated 3-statement models from scratch',
                    'Understand financial statement relationships and linkages',
                    'Master Excel formulas for financial modeling',
                    'Calculate key metrics (FCF, ROIC, ROE)',
                    'Model real company financials (Apple, Tesla examples)'
                ],
                'modules' => [
                    [
                        'module_id' => 1,
                        'title' => 'Income Statement Construction',
                        'lessons' => 5,
                        'topics' => ['Revenue modeling', 'COGS and gross margin', 'Operating expenses', 'EBITDA, EBIT, Net Income']
                    ],
                    [
                        'module_id' => 2,
                        'title' => 'Balance Sheet Modeling',
                        'lessons' => 4,
                        'topics' => ['Assets: Current vs Non-current', 'Liabilities structure', 'Shareholders equity', 'Balance sheet linkages']
                    ],
                    [
                        'module_id' => 3,
                        'title' => 'Cash Flow Statement',
                        'lessons' => 3,
                        'topics' => ['Operating cash flow (indirect method)', 'Investing activities', 'Financing activities', 'Free cash flow calculation']
                    ]
                ]
            ]
        ];
    }

    /**
     * Course 2: DCF Valuation Mastery (Intermediate)
     */
    private function get_course_data_dcf() {
        return [
            'title' => 'DCF Valuation Mastery',
            'meta' => [
                'course_provider' => 'MENA Careers Academy',
                'learning_mode' => 'Self-paced',
                'category' => 'Financial Modeling',
                'difficulty_level' => 'Intermediate',
                'estimated_hours' => 12,
                'total_lessons' => 24,
                'total_quizzes' => 6,
                'has_certificate' => true,
                'has_ai_tutor' => true,
                'instructor_name' => 'Claude AI + Expert Review',
                'certification_type' => 'MENA Careers Certified - DCF Valuation',
                'price' => 'Free',
                'prerequisites' => 'Completion of 3-Statement Modeling or equivalent knowledge',
                'skills_taught' => ['DCF Analysis', 'WACC Calculation', 'Terminal Value', 'Sensitivity Analysis', 'Enterprise Valuation'],
                'description' => 'Build complete DCF models for any company. Master discount rate calculations, free cash flow forecasting, terminal value methods, and sensitivity analysis.',
                'learning_outcomes' => [
                    'Build complete DCF models for any company',
                    'Calculate WACC using market data',
                    'Determine appropriate terminal value assumptions',
                    'Perform sensitivity and scenario analysis',
                    'Present valuation ranges with confidence'
                ],
                'modules' => [
                    [
                        'module_id' => 1,
                        'title' => 'DCF Fundamentals',
                        'lessons' => 6,
                        'topics' => ['Time value of money', 'WACC calculation', 'Free cash flow forecasting', 'Projection periods', 'Sensitivity analysis']
                    ],
                    [
                        'module_id' => 2,
                        'title' => 'Terminal Value Calculation',
                        'lessons' => 4,
                        'topics' => ['Gordon Growth Method', 'Exit Multiple Method', 'Perpetuity growth assumptions', 'Terminal value as % of EV']
                    ],
                    [
                        'module_id' => 3,
                        'title' => 'EV to Equity Value Bridge',
                        'lessons' => 4,
                        'topics' => ['EV calculation', 'Net debt adjustments', 'Minority interests', 'Preferred stock']
                    ],
                    [
                        'module_id' => 4,
                        'title' => 'Advanced DCF Techniques',
                        'lessons' => 10,
                        'topics' => ['Mid-year convention', 'Unlevered vs levered FCF', 'APV method', 'Multi-stage growth', 'Monte Carlo simulation']
                    ]
                ]
            ]
        ];
    }

    /**
     * Course 3: LBO Modeling for Private Equity (Advanced)
     */
    private function get_course_data_lbo() {
        return [
            'title' => 'LBO Modeling for Private Equity',
            'meta' => [
                'course_provider' => 'MENA Careers Academy',
                'learning_mode' => 'Self-paced',
                'category' => 'Financial Modeling',
                'difficulty_level' => 'Advanced',
                'estimated_hours' => 16,
                'total_lessons' => 32,
                'total_quizzes' => 8,
                'has_certificate' => true,
                'has_ai_tutor' => true,
                'instructor_name' => 'Claude AI + Expert Review',
                'certification_type' => 'MENA Careers Certified - LBO Modeling',
                'price' => 'Free',
                'prerequisites' => 'DCF Valuation Mastery or equivalent PE experience',
                'skills_taught' => ['LBO Mechanics', 'Debt Structuring', 'Returns Analysis', 'Sources & Uses', 'PE Deal Economics'],
                'description' => 'Build LBO models from scratch in 60 minutes. Master PE firm economics, debt structuring, returns analysis, and learn to evaluate deal feasibility like a professional investor.',
                'learning_outcomes' => [
                    'Build LBO models from scratch in 60 minutes',
                    'Understand PE firm economics and deal structures',
                    'Calculate IRR and MOIC for deals',
                    'Model debt paydown schedules with cash sweeps',
                    'Evaluate LBO feasibility and returns sensitivity'
                ],
                'modules' => [
                    [
                        'module_id' => 1,
                        'title' => 'LBO Mechanics',
                        'lessons' => 8,
                        'topics' => ['LBO transaction overview', 'Sources & Uses', 'Debt structure', 'Equity contribution', 'Management rollover', 'Transaction fees']
                    ],
                    [
                        'module_id' => 2,
                        'title' => 'Operating Model',
                        'lessons' => 8,
                        'topics' => ['Revenue growth assumptions', 'EBITDA margin expansion', 'Working capital changes', 'Capex forecasting']
                    ],
                    [
                        'module_id' => 3,
                        'title' => 'Debt Schedule',
                        'lessons' => 8,
                        'topics' => ['Debt tranches and priorities', 'Mandatory vs optional prepayments', 'Cash sweep provisions', 'Interest expense', 'Debt covenants']
                    ],
                    [
                        'module_id' => 4,
                        'title' => 'Returns Analysis',
                        'lessons' => 8,
                        'topics' => ['Exit valuation', 'Equity waterfall', 'IRR calculation', 'MOIC', 'Sensitivity tables', 'Management incentives']
                    ]
                ]
            ]
        ];
    }

    /**
     * Course 4: Excel Mastery for Finance (Beginner to Intermediate)
     */
    private function get_course_data_excel() {
        return [
            'title' => 'Excel Mastery for Finance',
            'meta' => [
                'course_provider' => 'MENA Careers Academy',
                'learning_mode' => 'Self-paced',
                'category' => 'Excel',
                'difficulty_level' => 'Intermediate',
                'estimated_hours' => 8,
                'total_lessons' => 16,
                'total_quizzes' => 4,
                'has_certificate' => true,
                'has_ai_tutor' => true,
                'instructor_name' => 'Claude AI + Expert Review',
                'certification_type' => 'MENA Careers Certified - Excel for Finance',
                'price' => 'Free',
                'prerequisites' => 'Basic Excel knowledge (formulas, simple functions)',
                'skills_taught' => ['Advanced Formulas', 'Pivot Tables', 'Financial Dashboards', 'VBA Basics', 'Model Best Practices'],
                'description' => 'Master advanced Excel techniques used in finance. Learn formulas, pivot tables, dashboards, model structure, and VBA automation to pass Excel assessments and work like a pro.',
                'learning_outcomes' => [
                    'Master advanced Excel formulas (INDEX/MATCH, array formulas)',
                    'Build financial dashboards with pivot tables',
                    'Structure models following best practices',
                    'Automate repetitive tasks with VBA',
                    'Pass Excel assessments in finance interviews'
                ],
                'modules' => [
                    [
                        'module_id' => 1,
                        'title' => 'Essential Formulas',
                        'lessons' => 4,
                        'topics' => ['VLOOKUP, HLOOKUP, INDEX/MATCH', 'SUMIFS, AVERAGEIFS, COUNTIFS', 'IF, AND, OR, nested IF']
                    ],
                    [
                        'module_id' => 2,
                        'title' => 'Data Analysis Tools',
                        'lessons' => 4,
                        'topics' => ['Pivot tables and charts', 'Data validation', 'Conditional formatting']
                    ],
                    [
                        'module_id' => 3,
                        'title' => 'Financial Model Best Practices',
                        'lessons' => 4,
                        'topics' => ['Model structure (inputs, calcs, outputs)', 'Color coding and formatting', 'Error checking', 'Documentation']
                    ],
                    [
                        'module_id' => 4,
                        'title' => 'VBA Automation',
                        'lessons' => 4,
                        'topics' => ['Recording macros', 'Basic VBA syntax', 'Automating tasks', 'Custom functions']
                    ]
                ]
            ]
        ];
    }

    /**
     * Course 5: Investment Banking Technical Interview Prep (All Levels)
     */
    private function get_course_data_ib_interview() {
        return [
            'title' => 'Investment Banking Technical Interview Prep',
            'meta' => [
                'course_provider' => 'MENA Careers Academy',
                'learning_mode' => 'Self-paced',
                'category' => 'Interview Prep',
                'difficulty_level' => 'Intermediate',
                'estimated_hours' => 8,
                'total_lessons' => 16,
                'total_quizzes' => 4,
                'has_certificate' => true,
                'has_ai_tutor' => true,
                'instructor_name' => 'Claude AI + Expert Review',
                'certification_type' => 'MENA Careers Certified - IB Interview Ready',
                'price' => 'Free',
                'prerequisites' => 'Financial modeling knowledge recommended',
                'skills_taught' => ['Technical Interview Questions', 'Valuation Methods', 'M&A Analysis', 'Behavioral Fit', 'Case Studies'],
                'description' => 'Master 80+ common investment banking interview questions. Learn to explain technical concepts clearly, walk through valuations confidently, and ace behavioral questions.',
                'learning_outcomes' => [
                    'Answer 80+ common IB interview questions confidently',
                    'Explain technical concepts (DCF, comps, precedents) clearly',
                    'Walk through M&A process and accretion/dilution',
                    'Think through case studies methodically',
                    'Articulate career motivation and fit for banking'
                ],
                'modules' => [
                    [
                        'module_id' => 1,
                        'title' => 'Accounting Fundamentals',
                        'lessons' => 4,
                        'topics' => ['3 financial statements', 'Impact analysis scenarios', 'EBIT vs EBITDA', 'Free cash flow calculation']
                    ],
                    [
                        'module_id' => 2,
                        'title' => 'Valuation Methods',
                        'lessons' => 5,
                        'topics' => ['DCF walkthrough', 'Comparable companies', 'Precedent transactions', 'Enterprise value calculation']
                    ],
                    [
                        'module_id' => 3,
                        'title' => 'M&A and Deals',
                        'lessons' => 4,
                        'topics' => ['M&A process', 'Accretion/dilution', 'Synergies', 'Deal motivations']
                    ],
                    [
                        'module_id' => 4,
                        'title' => 'Behavioral Questions',
                        'lessons' => 3,
                        'topics' => ['Why investment banking', 'Resume walkthrough', 'Leadership examples', 'STAR method']
                    ]
                ]
            ]
        ];
    }

    /**
     * Get course creation status
     */
    public function are_courses_seeded() {
        return get_option('sffc_seed_courses_created', false);
    }

    /**
     * Reset seed courses flag (for re-seeding)
     */
    public function reset_seed_flag() {
        delete_option('sffc_seed_courses_created');
    }
}

// Initialize
SFFC_Seed_Courses::get_instance();
