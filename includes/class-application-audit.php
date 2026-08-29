<?php

/**
 * Application Audit System V2
 *
 * Dynamic, role-aware audit that generates questions from actual job requirements.
 * Uses SFFC_Job_Requirements_Extractor for intelligent requirement extraction.
 * Creates premium dashboard-style reports like Ahrefs/SEMrush health scores.
 *
 * @package SFFC
 * @since 11.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Application_Audit
{
    private static $instance = null;

    /**
     * Reference to the extractor
     */
    private $extractor = null;

    /**
     * Severity levels for issues (Premium dashboard style)
     */
    private $severity_config = array(
        'critical' => array(
            'label' => 'Critical',
            'color' => '#dc2626',
            'bg_color' => '#fef2f2',
            'border_color' => '#fecaca',
            'icon' => 'error',
            'description' => 'Likely disqualifying - address before applying'
        ),
        'warning' => array(
            'label' => 'Warning',
            'color' => '#d97706',
            'bg_color' => '#fffbeb',
            'border_color' => '#fde68a',
            'icon' => 'warning',
            'description' => 'May reduce chances - consider improving'
        ),
        'notice' => array(
            'label' => 'Notice',
            'color' => '#2563eb',
            'bg_color' => '#eff6ff',
            'border_color' => '#bfdbfe',
            'icon' => 'info',
            'description' => 'Opportunity to strengthen your profile'
        ),
        'passed' => array(
            'label' => 'Passed',
            'color' => '#059669',
            'bg_color' => '#ecfdf5',
            'border_color' => '#a7f3d0',
            'icon' => 'check_circle',
            'description' => 'Meets or exceeds requirements'
        )
    );

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Get extractor instance
        if (class_exists('SFFC_Job_Requirements_Extractor')) {
            $this->extractor = SFFC_Job_Requirements_Extractor::get_instance();
        }

        // AJAX handlers
        add_action('wp_ajax_sffc_get_job_audit', array($this, 'ajax_get_job_audit'));
        add_action('wp_ajax_nopriv_sffc_get_job_audit', array($this, 'ajax_get_job_audit'));
        add_action('wp_ajax_sffc_generate_audit_report', array($this, 'ajax_generate_audit_report'));
        add_action('wp_ajax_nopriv_sffc_generate_audit_report', array($this, 'ajax_generate_audit_report'));
        add_action('wp_ajax_sffc_generate_content', array($this, 'ajax_generate_content'));
        add_action('wp_ajax_nopriv_sffc_generate_content', array($this, 'ajax_generate_content'));

        // Shortcodes
        add_shortcode('sffc_application_audit', array($this, 'render_audit_shortcode'));
        add_shortcode('sffc_audit_button', array($this, 'render_audit_button_shortcode'));
    }

    /**
     * Get dynamic audit configuration from job requirements
     *
     * @param int $job_id The job post ID
     * @return array Audit configuration with dynamically generated questions
     */
    public function get_job_audit_config($job_id)
    {
        if (!$this->extractor) {
            return $this->get_fallback_config($job_id);
        }

        // Extract requirements from job
        $requirements = $this->extractor->extract_requirements($job_id);

        // Build questions from extracted requirements
        $questions = $this->build_dynamic_questions($requirements);

        // Get job metadata
        $job_data = $this->get_job_data($job_id);

        return array(
            'job_id' => $job_id,
            'job_data' => $job_data,
            'role_category' => $requirements['role_category'],
            'extraction_confidence' => $requirements['extraction_confidence'],
            'questions' => $questions,
            'total_questions' => count($questions),
            'severity_config' => $this->severity_config,
            'requirements_summary' => array(
                'skills_count' => count($requirements['skills']),
                'qualifications_count' => count($requirements['qualifications']),
                'experience' => $requirements['experience'],
            )
        );
    }

    /**
     * Build dynamic questions from extracted requirements
     */
    private function build_dynamic_questions($requirements)
    {
        $questions = array();
        $question_index = 0;

        // 1. Experience Question (always first)
        $questions[] = $this->build_experience_question($requirements['experience'], $question_index++);

        // 2. Skill Questions (from extracted skills)
        $skill_count = 0;
        $max_skill_questions = 8; // Cap to avoid overwhelming users

        foreach ($requirements['skills'] as $skill_key => $skill) {
            if ($skill_count >= $max_skill_questions) {
                break;
            }

            $questions[] = array(
                'id' => 'skill_' . $skill_key,
                'index' => $question_index++,
                'type' => 'scale',
                'category' => 'Skills',
                'sub_category' => $skill['category'],
                'question' => $skill['audit_question'],
                'skill_name' => $skill['name'],
                'weight' => $skill['weight'],
                'source' => $skill['source'],
                'is_critical' => $skill['weight'] === 'high',
                'options' => $this->simplify_options($skill['options']),
                'gap_message' => $this->generate_gap_message($skill['name'], $skill['weight']),
            );
            $skill_count++;
        }

        // 3. Qualification Questions (from extracted qualifications)
        foreach ($requirements['qualifications'] as $qual_key => $qual) {
            $questions[] = array(
                'id' => 'qual_' . $qual_key,
                'index' => $question_index++,
                'type' => 'scale',
                'category' => 'Qualifications',
                'sub_category' => 'Credentials',
                'question' => $qual['audit_question'],
                'qualification_name' => $qual['name'],
                'full_name' => $qual['full_name'],
                'weight' => $qual['weight'],
                'is_required' => $qual['is_required'] ?? false,
                'source' => $qual['source'],
                'is_critical' => $qual['is_required'] ?? false,
                'options' => $this->simplify_options($qual['options']),
                'gap_message' => $this->generate_qualification_gap_message($qual),
            );
        }

        // 4. Motivation Question (always last)
        $questions[] = $this->build_motivation_question($question_index++);

        return $questions;
    }

    /**
     * Build experience question from extracted requirements
     */
    private function build_experience_question($experience, $index)
    {
        $min = $experience['min'] ?? 2;
        $max = $experience['max'] ?? 5;

        // Build experience-specific options (shortened)
        $options = array();

        if ($max >= 10) {
            $options[] = array('label' => '10+ years', 'score' => 100);
        }
        if ($max >= 7 || $min >= 5) {
            $options[] = array('label' => '7-10 years', 'score' => 90);
        }
        if ($max >= 5 || $min >= 3) {
            $options[] = array('label' => '5-7 years', 'score' => 80);
        }
        $options[] = array('label' => '3-5 years', 'score' => 65);
        $options[] = array('label' => '2-3 years', 'score' => 50);
        $options[] = array('label' => '1-2 years', 'score' => 35);
        $options[] = array('label' => 'Less than 1 year', 'score' => 15);
        $options[] = array('label' => 'No direct experience', 'score' => 5);

        $experience_text = $max ? "{$min}-{$max} years" : "{$min}+ years";

        return array(
            'id' => 'experience_years',
            'index' => $index,
            'type' => 'scale',
            'category' => 'Experience',
            'sub_category' => 'Years',
            'question' => "How many years of relevant experience do you have?",
            'context' => "This role typically requires {$experience_text} of experience.",
            'weight' => 'high',
            'source' => $experience['source'],
            'is_critical' => true,
            'options' => $options,
            'gap_message' => "Experience level is one of the primary screening criteria. Roles like this typically require {$experience_text}.",
            'required_range' => array('min' => $min, 'max' => $max),
        );
    }

    /**
     * Build motivation question
     */
    private function build_motivation_question($index)
    {
        return array(
            'id' => 'motivation_fit',
            'index' => $index,
            'type' => 'scale',
            'category' => 'Fit',
            'sub_category' => 'Motivation',
            'question' => 'How aligned is this role with your career goals?',
            'weight' => 'medium',
            'source' => 'standard',
            'is_critical' => false,
            'options' => array(
                array('label' => 'Perfect fit', 'score' => 100),
                array('label' => 'Strong fit', 'score' => 80),
                array('label' => 'Good fit', 'score' => 60),
                array('label' => 'Partial fit', 'score' => 40),
                array('label' => 'Just exploring', 'score' => 20),
            ),
            'gap_message' => 'Employers can sense enthusiasm. Strong motivation often differentiates candidates with similar qualifications.',
        );
    }

    /**
     * Simplify option labels to be more concise
     */
    private function simplify_options($options)
    {
        if (empty($options)) {
            return $this->get_default_options();
        }

        $simplified = array();
        foreach ($options as $option) {
            $label = $option['label'];

            // Shorten common patterns
            $label = preg_replace('/^Expert\s*[-–]\s*/i', 'Expert: ', $label);
            $label = preg_replace('/^Advanced\s*[-–]\s*/i', 'Advanced: ', $label);
            $label = preg_replace('/^Intermediate\s*[-–]\s*/i', 'Intermediate: ', $label);
            $label = preg_replace('/^Basic\s*[-–]\s*/i', 'Basic: ', $label);

            // Truncate if still too long (keep first 40 chars)
            if (strlen($label) > 45) {
                // Try to cut at word boundary
                $label = substr($label, 0, 42);
                $lastSpace = strrpos($label, ' ');
                if ($lastSpace > 30) {
                    $label = substr($label, 0, $lastSpace);
                }
            }

            $simplified[] = array(
                'label' => $label,
                'score' => $option['score']
            );
        }

        return $simplified;
    }

    /**
     * Default options for skills/qualifications
     */
    private function get_default_options()
    {
        return array(
            array('label' => 'Expert', 'score' => 100),
            array('label' => 'Advanced', 'score' => 75),
            array('label' => 'Intermediate', 'score' => 50),
            array('label' => 'Basic', 'score' => 25),
            array('label' => 'No experience', 'score' => 0),
        );
    }

    /**
     * Generate gap message for a skill
     */
    private function generate_gap_message($skill_name, $weight)
    {
        $messages = array(
            'high' => "{$skill_name} is a core requirement for this role. Candidates with strong {$skill_name} skills are significantly more likely to advance in the hiring process.",
            'medium' => "{$skill_name} is valued for this position. Demonstrating proficiency can strengthen your application.",
            'low' => "{$skill_name} is beneficial for this role but may not be strictly required.",
        );

        return $messages[$weight] ?? $messages['medium'];
    }

    /**
     * Generate gap message for a qualification
     */
    private function generate_qualification_gap_message($qual)
    {
        if ($qual['is_required'] ?? false) {
            return "{$qual['full_name']} ({$qual['name']}) is listed as a requirement. Without this credential, your application may be filtered out automatically.";
        }
        return "{$qual['full_name']} ({$qual['name']}) is preferred for this role. Having this credential can differentiate you from other candidates.";
    }

    /**
     * Get job metadata
     */
    private function get_job_data($job_id)
    {
        return array(
            'job_id' => $job_id,
            'job_title' => get_the_title($job_id),
            'company' => get_post_meta($job_id, 'sffc_actual_company', true) ?: get_post_meta($job_id, 'sffc_source_name', true),
            'location' => get_post_meta($job_id, 'sffc_location_city', true),
            'location_country' => get_post_meta($job_id, 'sffc_location_country', true),
            'job_family' => get_post_meta($job_id, 'sffc_job_family', true),
            'experience_level' => get_post_meta($job_id, 'sffc_experience_level', true),
            'salary_display' => get_post_meta($job_id, 'sffc_salary_display', true),
            'application_url' => get_post_meta($job_id, 'sffc_application_url', true),
            'posted_date' => get_the_date('Y-m-d', $job_id),
        );
    }

    /**
     * Calculate comprehensive audit report
     *
     * @param int $job_id The job post ID
     * @param array $responses User responses to audit questions
     * @return array Detailed audit report
     */
    public function calculate_audit_report($job_id, $responses)
    {
        $config = $this->get_job_audit_config($job_id);
        $questions = $config['questions'];

        // Calculate scores
        $total_weighted_score = 0;
        $total_weight = 0;
        $category_scores = array();
        $issues = array(
            'critical' => array(),
            'warning' => array(),
            'notice' => array()
        );
        $passed = array();

        foreach ($questions as $question) {
            $question_id = $question['id'];
            $response = isset($responses[$question_id]) ? $responses[$question_id] : null;

            if ($response === null) {
                continue;
            }

            // Find the score for this response
            $question_score = 0;
            $selected_label = '';
            foreach ($question['options'] as $option) {
                if ($option['label'] === $response || (isset($option['value']) && $option['value'] === $response)) {
                    $question_score = $option['score'];
                    $selected_label = $option['label'];
                    break;
                }
            }

            // Calculate weight multiplier
            $weight_multiplier = array('high' => 3, 'medium' => 2, 'low' => 1);
            $weight = $weight_multiplier[$question['weight'] ?? 'medium'] ?? 2;

            $total_weighted_score += $question_score * $weight;
            $total_weight += 100 * $weight;

            // Track by category
            $category = $question['category'];
            if (!isset($category_scores[$category])) {
                $category_scores[$category] = array(
                    'name' => $category,
                    'total_score' => 0,
                    'max_score' => 0,
                    'questions' => array()
                );
            }
            $category_scores[$category]['total_score'] += $question_score * $weight;
            $category_scores[$category]['max_score'] += 100 * $weight;
            $category_scores[$category]['questions'][] = array(
                'question' => $question['question'],
                'score' => $question_score,
                'response' => $selected_label
            );

            // Classify issues by severity
            $issue_data = array(
                'id' => $question_id,
                'category' => $category,
                'question' => $question['question'],
                'skill_name' => $question['skill_name'] ?? $question['qualification_name'] ?? null,
                'score' => $question_score,
                'response' => $selected_label,
                'message' => $question['gap_message'],
                'is_critical' => $question['is_critical'] ?? false,
                'weight' => $question['weight'] ?? 'medium',
            );

            if ($question_score >= 75) {
                $passed[] = $issue_data;
            } elseif ($question_score < 30 && ($question['is_critical'] ?? false)) {
                $issues['critical'][] = $issue_data;
            } elseif ($question_score < 50) {
                $issues['warning'][] = $issue_data;
            } elseif ($question_score < 75) {
                $issues['notice'][] = $issue_data;
            }
        }

        // Calculate final score
        $final_score = $total_weight > 0 ? round(($total_weighted_score / $total_weight) * 100) : 0;

        // Calculate category percentages
        foreach ($category_scores as $key => $cat) {
            $category_scores[$key]['percentage'] = $cat['max_score'] > 0
                ? round(($cat['total_score'] / $cat['max_score']) * 100)
                : 0;
        }

        // Determine health grade
        $health = $this->calculate_health_grade($final_score, count($issues['critical']), count($issues['warning']));

        // Generate smart recommendations
        $recommendations = $this->generate_recommendations($issues, $config['job_data'], $final_score);

        // Calculate issue counts for dashboard
        $total_issues = count($issues['critical']) + count($issues['warning']) + count($issues['notice']);
        $visible_count = 3;
        $hidden_count = max(0, $total_issues - $visible_count);

        return array(
            'job_data' => $config['job_data'],
            'role_category' => $config['role_category'],
            'extraction_confidence' => $config['extraction_confidence'],

            // Health Score Dashboard
            'health_score' => $final_score,
            'health_grade' => $health,

            // Category Breakdown
            'category_scores' => $category_scores,

            // Issues by Severity (Ahrefs-style)
            'issues' => array(
                'critical' => $issues['critical'],
                'warning' => $issues['warning'],
                'notice' => $issues['notice'],
                'counts' => array(
                    'critical' => count($issues['critical']),
                    'warning' => count($issues['warning']),
                    'notice' => count($issues['notice']),
                    'passed' => count($passed),
                    'total' => $total_issues,
                    'hidden' => $hidden_count,
                ),
            ),

            // Passed Checks
            'passed' => array_slice($passed, 0, 5),

            // Recommendations
            'recommendations' => $recommendations,

            // Smart message Pitch
            'smart_apply' => $this->generate_smart_apply_pitch($final_score, $issues, $config['job_data']),

            // Metadata
            'generated_at' => current_time('mysql'),
            'questions_answered' => count($responses),
            'total_questions' => $config['total_questions'],
        );
    }

    /**
     * Calculate health grade (Ahrefs/SEMrush style)
     */
    private function calculate_health_grade($score, $critical_count, $warning_count)
    {
        if ($critical_count >= 3 || $score < 25) {
            return array(
                'grade' => 'F',
                'label' => 'Critical Gaps',
                'color' => '#dc2626',
                'bg_gradient' => 'linear-gradient(135deg, #dc2626 0%, #b91c1c 100%)',
                'ring_color' => '#fecaca',
                'message' => 'Multiple critical requirements not met. Application likely to be screened out.',
                'action' => 'Address critical gaps before applying'
            );
        }
        if ($critical_count >= 1 || $score < 40) {
            return array(
                'grade' => 'D',
                'label' => 'Below Requirements',
                'color' => '#ea580c',
                'bg_gradient' => 'linear-gradient(135deg, #ea580c 0%, #c2410c 100%)',
                'ring_color' => '#fed7aa',
                'message' => 'Key requirements not fully met. Consider strengthening profile.',
                'action' => 'Improve weak areas to increase chances'
            );
        }
        if ($warning_count >= 3 || $score < 55) {
            return array(
                'grade' => 'C',
                'label' => 'Partial Match',
                'color' => '#d97706',
                'bg_gradient' => 'linear-gradient(135deg, #d97706 0%, #b45309 100%)',
                'ring_color' => '#fde68a',
                'message' => 'Some requirements met, but gaps remain. Worth applying with strong cover letter.',
                'action' => 'Highlight transferable skills'
            );
        }
        if ($score < 70) {
            return array(
                'grade' => 'B-',
                'label' => 'Moderate Match',
                'color' => '#65a30d',
                'bg_gradient' => 'linear-gradient(135deg, #65a30d 0%, #4d7c0f 100%)',
                'ring_color' => '#bef264',
                'message' => 'Meets most requirements. Good candidate with some areas to address.',
                'action' => 'Apply with targeted application'
            );
        }
        if ($score < 85) {
            return array(
                'grade' => 'B+',
                'label' => 'Good Match',
                'color' => '#22c55e',
                'bg_gradient' => 'linear-gradient(135deg, #22c55e 0%, #16a34a 100%)',
                'ring_color' => '#86efac',
                'message' => 'Strong profile alignment. Minor optimizations could help.',
                'action' => 'Apply confidently'
            );
        }
        return array(
            'grade' => 'A',
            'label' => 'Excellent Match',
            'color' => '#059669',
            'bg_gradient' => 'linear-gradient(135deg, #059669 0%, #047857 100%)',
            'ring_color' => '#6ee7b7',
            'message' => 'Outstanding fit for this role. High confidence in your candidacy.',
            'action' => 'Apply now - strong candidate'
        );
    }

    /**
     * Generate actionable recommendations
     */
    private function generate_recommendations($issues, $job_data, $score)
    {
        $recommendations = array();

        // Critical issues first
        foreach (array_slice($issues['critical'], 0, 2) as $issue) {
            $recommendations[] = array(
                'priority' => 'critical',
                'icon' => 'priority_high',
                'title' => 'Critical: ' . ($issue['skill_name'] ?? 'Key Requirement'),
                'description' => $issue['message'],
                'action' => 'Smart message includes coaching to position your transferable experience.',
                'type' => 'gap'
            );
        }

        // Warning issues
        foreach (array_slice($issues['warning'], 0, 2) as $issue) {
            $recommendations[] = array(
                'priority' => 'warning',
                'icon' => 'trending_up',
                'title' => 'Improve: ' . ($issue['skill_name'] ?? 'Profile Area'),
                'description' => $issue['message'],
                'action' => 'Highlight relevant experience in your application.',
                'type' => 'improvement'
            );
        }

        // Positive recommendation if score is decent
        if ($score >= 60) {
            $recommendations[] = array(
                'priority' => 'positive',
                'icon' => 'thumb_up',
                'title' => 'Strong Foundation',
                'description' => 'Your profile has solid alignment with key requirements.',
                'action' => 'Focus on presenting your experience effectively.',
                'type' => 'strength'
            );
        }

        // Smart message recommendation (always)
        $company = $job_data['company'] ?: 'this employer';
        $recommendations[] = array(
            'priority' => 'action',
            'icon' => 'rocket_launch',
            'title' => 'Maximize Your Chances',
            'description' => "Smart message optimizes your application for {$company} and identifies similar roles.",
            'action' => 'One-click application to 10+ matching positions.',
            'type' => 'cta'
        );

        return $recommendations;
    }

    /**
     * Generate Smart message conversion pitch
     */
    private function generate_smart_apply_pitch($score, $issues, $job_data)
    {
        $critical_count = count($issues['critical']);
        $warning_count = count($issues['warning']);
        $total_issues = $critical_count + $warning_count + count($issues['notice']);

        $pitch = array(
            'show_urgency' => $critical_count > 0 || $warning_count > 2,
            'headline' => '',
            'subheadline' => '',
            'benefits' => array(),
            'cta_text' => 'Apply Smart',
            'cta_secondary' => 'View Full Report'
        );

        if ($critical_count >= 2) {
            $pitch['headline'] = "{$critical_count} Critical Issues Found";
            $pitch['subheadline'] = "Your application may be automatically filtered out";
            $pitch['urgency_color'] = '#dc2626';
            $pitch['benefits'] = array(
                'Reposition gaps as transferable strengths',
                'AI-optimized application for ' . ($job_data['company'] ?: 'this role'),
                'Apply to 10+ similar roles simultaneously',
                'Get notified when better-matched roles open'
            );
            $pitch['cta_text'] = 'Fix Issues & Apply';
        } elseif ($critical_count >= 1 || $warning_count >= 3) {
            $pitch['headline'] = "{$total_issues} Issues May Affect Your Chances";
            $pitch['subheadline'] = "Candidates with optimized profiles are 3x more likely to get interviews";
            $pitch['urgency_color'] = '#d97706';
            $pitch['benefits'] = array(
                'Address all ' . $total_issues . ' issues automatically',
                'Stand out from other applicants',
                'Apply to similar roles at top firms',
                'Track application status in real-time'
            );
            $pitch['cta_text'] = 'Optimize & Apply';
        } else {
            $pitch['headline'] = $score >= 70 ? 'Good Match - Make It Great' : 'Improve Your Chances';
            $pitch['subheadline'] = "Small optimizations can make a big difference";
            $pitch['urgency_color'] = '#2563eb';
            $pitch['benefits'] = array(
                'Polish your application to perfection',
                'Apply to 10+ roles with one click',
                'Priority review from recruiters',
                'Access exclusive unlisted roles'
            );
            $pitch['cta_text'] = 'Apply Smart';
        }

        return $pitch;
    }

    /**
     * Fallback config when extractor isn't available
     */
    private function get_fallback_config($job_id)
    {
        return array(
            'job_id' => $job_id,
            'job_data' => $this->get_job_data($job_id),
            'role_category' => 'generic',
            'extraction_confidence' => 0,
            'questions' => array(
                $this->build_experience_question(array('min' => 2, 'max' => 5, 'source' => 'default'), 0),
                $this->build_motivation_question(1),
            ),
            'total_questions' => 2,
            'severity_config' => $this->severity_config,
        );
    }

    /**
     * Shortcode: Render audit container
     */
    public function render_audit_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'job_id' => 0,
        ), $atts, 'sffc_application_audit');

        $job_id = intval($atts['job_id']);

        if (!$job_id) {
            global $post;
            if ($post && $post->post_type === 'sffc_job') {
                $job_id = $post->ID;
            }
        }

        if (!$job_id) {
            return '<div class="sffc-audit-error">No job specified for audit.</div>';
        }

        $container_id = 'sffc-audit-' . $job_id . '-' . wp_rand(1000, 9999);

        ob_start();
?>
        <div id="<?php echo esc_attr($container_id); ?>" class="sffc-audit-wrapper" data-job-id="<?php echo esc_attr($job_id); ?>"></div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof SennaApplicationAudit !== 'undefined') {
                    SennaApplicationAudit.init(<?php echo $job_id; ?>, '#<?php echo $container_id; ?>');
                }
            });
        </script>
    <?php
        return ob_get_clean();
    }

    /**
     * Shortcode: Render audit trigger button
     */
    public function render_audit_button_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'job_id' => 0,
            'text' => 'Check Your Fit',
            'class' => '',
            'style' => 'primary',
        ), $atts, 'sffc_audit_button');

        $job_id = intval($atts['job_id']);

        if (!$job_id) {
            global $post;
            if ($post && $post->post_type === 'sffc_job') {
                $job_id = $post->ID;
            }
        }

        if (!$job_id) {
            return '';
        }

        $button_class = 'sffc-audit-trigger sffc-audit-trigger--' . esc_attr($atts['style']);
        if (!empty($atts['class'])) {
            $button_class .= ' ' . esc_attr($atts['class']);
        }

        ob_start();
    ?>
        <button
            type="button"
            class="<?php echo esc_attr($button_class); ?>"
            data-audit-job-id="<?php echo esc_attr($job_id); ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 11l3 3L22 4" />
                <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
            </svg>
            <?php echo esc_html($atts['text']); ?>
        </button>
<?php
        return ob_get_clean();
    }

    /**
     * AJAX: Get audit config
     */
    public function ajax_get_job_audit()
    {
        $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;

        if (!$job_id) {
            wp_send_json_error(array('message' => 'Invalid job ID'));
            return;
        }

        $config = $this->get_job_audit_config($job_id);
        wp_send_json_success($config);
    }

    /**
     * AJAX: Generate audit report
     */
    public function ajax_generate_audit_report()
    {
        $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
        $responses = isset($_POST['responses']) ? json_decode(stripslashes($_POST['responses']), true) : array();

        if (!$job_id) {
            wp_send_json_error(array('message' => 'Invalid job ID'));
            return;
        }

        $report = $this->calculate_audit_report($job_id, $responses);

        // Add comprehensive intelligence sections (previews - full content on demand)
        $report['intelligence'] = $this->generate_intelligence_previews($job_id, $report);

        wp_send_json_success($report);
    }

    /**
     * AJAX: Generate full content on demand (cover letter, CV, etc.)
     */
    public function ajax_generate_content()
    {
        $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
        $content_type = isset($_POST['content_type']) ? sanitize_text_field($_POST['content_type']) : '';
        $user_context = isset($_POST['user_context']) ? json_decode(stripslashes($_POST['user_context']), true) : array();

        if (!$job_id || !$content_type) {
            wp_send_json_error(array('message' => 'Invalid request'));
            return;
        }

        $content = $this->generate_full_content($job_id, $content_type, $user_context);
        wp_send_json_success($content);
    }

    /**
     * Generate intelligence section previews (lightweight, no API calls)
     */
    private function generate_intelligence_previews($job_id, $report)
    {
        $job_data = $report['job_data'];
        $issues = $report['issues'];
        $health_score = $report['health_score'];

        // Get job description for keyword extraction
        $job_description = get_post_field('post_content', $job_id);

        // Get enhanced summary from job meta (generated by Job Summary Builder)
        $enhanced_summary = get_post_meta($job_id, 'sffc_enhanced_summary', true);
        if (is_string($enhanced_summary)) {
            $enhanced_summary = json_decode($enhanced_summary, true);
        }

        return array(
            // Location Intelligence
            'locations' => $this->generate_location_intelligence($job_data),

            // Timing Intelligence
            'timing' => $this->generate_timing_intelligence($job_data),

            // CV Keywords (critical for ATS)
            'cv_keywords' => $this->extract_cv_keywords($job_id, $job_description),

            // Referral Strategy
            'networking' => $this->generate_networking_strategy($job_data),

            // Cover Letter Preview (first 2-3 sentences only)
            'cover_letter_preview' => $this->generate_cover_letter_preview($job_data, $health_score),

            // CV Template Preview
            'cv_template_preview' => $this->generate_cv_template_preview($job_data, $issues),

            // Perfect Roles (similar positions)
            'perfect_roles' => $this->find_perfect_roles($job_id, $job_data),

            // Companies to Consider
            'target_companies' => $this->generate_target_companies($job_data),

            // Enhanced Summary Data (from AI-generated job analysis)
            'interview_battlecard' => $this->get_interview_battlecard($enhanced_summary, $job_data),

            // Application Checklist
            'application_checklist' => $this->get_application_checklist($enhanced_summary, $job_data),

            // Questions to Ask Interviewer
            'questions_to_ask' => $this->get_questions_to_ask($enhanced_summary),

            // Role Reality (what the job is really like)
            'role_reality' => $this->get_role_reality($enhanced_summary),

            // Stand Out Factors (how to differentiate)
            'stand_out_factors' => $this->get_stand_out_factors($enhanced_summary),

            // Career Trajectory
            'career_trajectory' => $this->get_career_trajectory($enhanced_summary),
        );
    }

    /**
     * Get interview battlecard data from enhanced summary
     */
    private function get_interview_battlecard($enhanced_summary, $job_data)
    {
        $battlecard = $enhanced_summary['interview_battlecard'] ?? array();

        if (empty($battlecard)) {
            // Provide fallback data
            return array(
                'title' => 'Interview Preparation',
                'subtitle' => 'Key topics to prepare for',
                'stages' => array(
                    array(
                        'name' => 'Phone Screen',
                        'focus' => 'Motivation and background fit',
                        'tips' => array('Prepare your story', 'Research the company', 'Have questions ready'),
                    ),
                    array(
                        'name' => 'Technical Interview',
                        'focus' => 'Skills assessment',
                        'tips' => array('Review key concepts', 'Prepare examples', 'Practice problem-solving'),
                    ),
                    array(
                        'name' => 'Final Round',
                        'focus' => 'Culture fit and leadership',
                        'tips' => array('Be authentic', 'Show enthusiasm', 'Discuss career goals'),
                    ),
                ),
                'has_data' => false,
            );
        }

        return array(
            'title' => 'Interview Battlecard',
            'subtitle' => 'Insider prep for ' . ($job_data['job_title'] ?? 'this role'),
            'stages' => $battlecard['stages'] ?? array(),
            'likely_questions' => $battlecard['likely_questions'] ?? array(),
            'key_topics' => $battlecard['key_topics'] ?? array(),
            'has_data' => true,
        );
    }

    /**
     * Get application checklist from enhanced summary
     */
    private function get_application_checklist($enhanced_summary, $job_data)
    {
        $checklist = $enhanced_summary['application_checklist'] ?? array();

        if (empty($checklist)) {
            // Provide smart fallback
            $company = $job_data['company'] ?? 'the company';
            $job_title = $job_data['job_title'] ?? 'this role';

            return array(
                'title' => 'Pre-Application Checklist',
                'subtitle' => 'Complete before applying',
                'items' => array(
                    array('task' => "Research {$company}'s recent news and deals", 'priority' => 'high'),
                    array('task' => 'Tailor your CV to highlight relevant experience', 'priority' => 'high'),
                    array('task' => 'Prepare 2-3 specific examples demonstrating key skills', 'priority' => 'high'),
                    array('task' => 'Review the job description for critical keywords', 'priority' => 'medium'),
                    array('task' => 'Connect with current employees on LinkedIn', 'priority' => 'medium'),
                    array('task' => 'Prepare answers for "Why this company?" and "Why this role?"', 'priority' => 'medium'),
                    array('task' => 'Update LinkedIn profile to match your application', 'priority' => 'low'),
                    array('task' => 'Have your portfolio or deal sheet ready if applicable', 'priority' => 'low'),
                ),
                'has_data' => false,
            );
        }

        return array(
            'title' => 'Application Checklist',
            'subtitle' => 'Your pre-application checklist',
            'items' => is_array($checklist) ? array_map(function ($item) {
                if (is_string($item)) {
                    return array('task' => $item, 'priority' => 'medium');
                }
                return $item;
            }, $checklist) : array(),
            'has_data' => true,
        );
    }

    /**
     * Get questions to ask from enhanced summary
     */
    private function get_questions_to_ask($enhanced_summary)
    {
        $questions = $enhanced_summary['questions_to_ask_them'] ?? array();

        if (empty($questions)) {
            return array(
                'title' => 'Questions to Ask',
                'subtitle' => 'Show genuine interest',
                'questions' => array(
                    "What does success look like in this role in the first 6 months?",
                    "How would you describe the team's culture and working style?",
                    "What are the biggest challenges the team is currently facing?",
                    "What opportunities for growth and development exist?",
                    "What's the typical career progression from this role?",
                ),
                'has_data' => false,
            );
        }

        return array(
            'title' => 'Questions to Ask',
            'subtitle' => 'Impress with thoughtful questions',
            'questions' => $questions,
            'has_data' => true,
        );
    }

    /**
     * Get role reality data from enhanced summary
     */
    private function get_role_reality($enhanced_summary)
    {
        $reality = $enhanced_summary['role_reality'] ?? array();

        if (empty($reality)) {
            return array(
                'title' => 'Role Reality',
                'has_data' => false,
            );
        }

        return array(
            'title' => 'What This Job Is Really Like',
            'subtitle' => 'Beyond the job description',
            'day_in_life' => $reality['day_in_life'] ?? '',
            'challenges' => $reality['challenges'] ?? array(),
            'rewards' => $reality['rewards'] ?? array(),
            'work_style' => $reality['work_style'] ?? '',
            'has_data' => true,
        );
    }

    /**
     * Get stand out factors from enhanced summary
     */
    private function get_stand_out_factors($enhanced_summary)
    {
        $factors = $enhanced_summary['stand_out_factors'] ?? array();

        if (empty($factors)) {
            return array(
                'title' => 'How to Stand Out',
                'has_data' => false,
            );
        }

        return array(
            'title' => 'How to Stand Out',
            'subtitle' => 'Differentiate yourself from other candidates',
            'factors' => $factors,
            'has_data' => true,
        );
    }

    /**
     * Get career trajectory from enhanced summary
     */
    private function get_career_trajectory($enhanced_summary)
    {
        $trajectory = $enhanced_summary['career_trajectory'] ?? array();

        if (empty($trajectory)) {
            return array(
                'title' => 'Career Trajectory',
                'has_data' => false,
            );
        }

        return array(
            'title' => 'Career Path',
            'subtitle' => 'Where this role can lead',
            'steps' => $trajectory['steps'] ?? $trajectory,
            'timeline' => $trajectory['timeline'] ?? '',
            'has_data' => true,
        );
    }

    /**
     * Location Intelligence - Best markets for this role
     */
    private function generate_location_intelligence($job_data)
    {
        $job_family = $job_data['job_family'] ?? '';
        $current_location = $job_data['location'] ?? '';
        $country = $job_data['location_country'] ?? '';

        // Finance/PE specific location data
        $location_data = array(
            'private_equity' => array(
                array('city' => 'New York', 'country' => 'USA', 'score' => 98, 'jobs_available' => '2,400+', 'avg_salary' => '$185K'),
                array('city' => 'London', 'country' => 'UK', 'score' => 95, 'jobs_available' => '1,800+', 'avg_salary' => '£125K'),
                array('city' => 'Hong Kong', 'country' => 'HK', 'score' => 88, 'jobs_available' => '650+', 'avg_salary' => 'HK$1.4M'),
                array('city' => 'Singapore', 'country' => 'SG', 'score' => 85, 'jobs_available' => '420+', 'avg_salary' => 'S$180K'),
                array('city' => 'San Francisco', 'country' => 'USA', 'score' => 82, 'jobs_available' => '380+', 'avg_salary' => '$175K'),
            ),
            'investment_banking' => array(
                array('city' => 'New York', 'country' => 'USA', 'score' => 99, 'jobs_available' => '4,200+', 'avg_salary' => '$165K'),
                array('city' => 'London', 'country' => 'UK', 'score' => 96, 'jobs_available' => '2,800+', 'avg_salary' => '£95K'),
                array('city' => 'Hong Kong', 'country' => 'HK', 'score' => 90, 'jobs_available' => '1,100+', 'avg_salary' => 'HK$1.2M'),
                array('city' => 'Frankfurt', 'country' => 'DE', 'score' => 78, 'jobs_available' => '520+', 'avg_salary' => '€85K'),
                array('city' => 'Dubai', 'country' => 'UAE', 'score' => 75, 'jobs_available' => '380+', 'avg_salary' => 'AED 450K'),
            ),
            'corporate_finance' => array(
                array('city' => 'New York', 'country' => 'USA', 'score' => 95, 'jobs_available' => '3,100+', 'avg_salary' => '$145K'),
                array('city' => 'London', 'country' => 'UK', 'score' => 92, 'jobs_available' => '2,200+', 'avg_salary' => '£85K'),
                array('city' => 'Chicago', 'country' => 'USA', 'score' => 85, 'jobs_available' => '1,400+', 'avg_salary' => '$125K'),
                array('city' => 'Singapore', 'country' => 'SG', 'score' => 80, 'jobs_available' => '680+', 'avg_salary' => 'S$140K'),
                array('city' => 'Sydney', 'country' => 'AU', 'score' => 75, 'jobs_available' => '520+', 'avg_salary' => 'A$135K'),
            ),
        );

        // Default to corporate finance if no match
        $family_key = 'corporate_finance';
        if (stripos($job_family, 'private equity') !== false || stripos($job_family, 'pe') !== false) {
            $family_key = 'private_equity';
        } elseif (stripos($job_family, 'investment banking') !== false || stripos($job_family, 'ib') !== false) {
            $family_key = 'investment_banking';
        }

        $locations = $location_data[$family_key];

        // Mark current location if in list
        foreach ($locations as &$loc) {
            $loc['is_current'] = (stripos($current_location, $loc['city']) !== false);
        }

        return array(
            'title' => 'Best Markets for This Role',
            'subtitle' => 'Based on job availability and compensation data',
            'locations' => $locations,
            'insight' => $this->generate_location_insight($locations, $current_location),
        );
    }

    /**
     * Timing Intelligence - Best time to apply
     */
    private function generate_timing_intelligence($job_data)
    {
        $posted_date = $job_data['posted_date'] ?? '';
        $days_posted = 0;

        if ($posted_date) {
            $posted = new DateTime($posted_date);
            $now = new DateTime();
            $days_posted = $now->diff($posted)->days;
        }

        // Calculate urgency
        $urgency = 'moderate';
        $urgency_message = '';

        if ($days_posted <= 3) {
            $urgency = 'high';
            $urgency_message = 'Recently posted - apply within 48 hours for best visibility';
        } elseif ($days_posted <= 7) {
            $urgency = 'high';
            $urgency_message = 'Posted this week - strong timing to apply now';
        } elseif ($days_posted <= 14) {
            $urgency = 'moderate';
            $urgency_message = 'Posted 1-2 weeks ago - still good timing';
        } else {
            $urgency = 'low';
            $urgency_message = 'Posted over 2 weeks ago - may have many applicants already';
        }

        // Best days/times data
        $best_times = array(
            array('day' => 'Tuesday', 'time' => '10:00 AM', 'effectiveness' => 94),
            array('day' => 'Wednesday', 'time' => '9:00 AM', 'effectiveness' => 91),
            array('day' => 'Monday', 'time' => '11:00 AM', 'effectiveness' => 87),
            array('day' => 'Thursday', 'time' => '2:00 PM', 'effectiveness' => 82),
        );

        // Hiring cycle insights
        $current_month = date('n');
        $hiring_seasons = array(
            1 => array('season' => 'High', 'note' => 'January hiring surge - budgets reset'),
            2 => array('season' => 'High', 'note' => 'Q1 hiring push continues'),
            3 => array('season' => 'High', 'note' => 'Strong hiring before Q1 close'),
            4 => array('season' => 'Moderate', 'note' => 'Post-Q1 adjustments'),
            5 => array('season' => 'Moderate', 'note' => 'Pre-summer hiring'),
            6 => array('season' => 'Low', 'note' => 'Summer slowdown begins'),
            7 => array('season' => 'Low', 'note' => 'Vacation season - slower responses'),
            8 => array('season' => 'Low', 'note' => 'Quiet period - prep for Q4'),
            9 => array('season' => 'High', 'note' => 'Fall hiring surge'),
            10 => array('season' => 'High', 'note' => 'Q4 budget utilization'),
            11 => array('season' => 'Moderate', 'note' => 'Pre-holiday push'),
            12 => array('season' => 'Low', 'note' => 'Holiday slowdown'),
        );

        return array(
            'title' => 'Optimal Application Timing',
            'days_posted' => $days_posted,
            'urgency' => $urgency,
            'urgency_message' => $urgency_message,
            'best_times' => $best_times,
            'current_season' => $hiring_seasons[$current_month],
            'recommendation' => $days_posted <= 7
                ? 'Apply today - timing is excellent'
                : 'Apply within the next 24-48 hours',
        );
    }

    /**
     * Extract critical CV keywords from job description
     */
    private function extract_cv_keywords($job_id, $job_description)
    {
        // Get extracted requirements if available
        $requirements = array();
        if ($this->extractor) {
            $requirements = $this->extractor->extract_requirements($job_id);
        }

        // Build keyword categories
        $keywords = array(
            'must_have' => array(),
            'should_have' => array(),
            'nice_to_have' => array(),
        );

        // From extracted skills
        if (!empty($requirements['skills'])) {
            foreach ($requirements['skills'] as $skill) {
                $keyword = array(
                    'term' => $skill['name'],
                    'frequency' => 'mentioned ' . ($skill['count'] ?? 1) . 'x',
                    'ats_critical' => $skill['weight'] === 'high',
                );

                if ($skill['weight'] === 'high') {
                    $keywords['must_have'][] = $keyword;
                } elseif ($skill['weight'] === 'medium') {
                    $keywords['should_have'][] = $keyword;
                } else {
                    $keywords['nice_to_have'][] = $keyword;
                }
            }
        }

        // Add standard finance keywords if sparse
        if (count($keywords['must_have']) < 3) {
            $finance_keywords = array(
                array('term' => 'Financial Analysis', 'frequency' => 'industry standard', 'ats_critical' => true),
                array('term' => 'Excel / Financial Modeling', 'frequency' => 'industry standard', 'ats_critical' => true),
                array('term' => 'Valuation', 'frequency' => 'industry standard', 'ats_critical' => false),
            );
            $keywords['must_have'] = array_merge($keywords['must_have'], $finance_keywords);
        }

        return array(
            'title' => 'ATS-Critical Keywords',
            'subtitle' => 'Include these exact terms in your CV',
            'keywords' => $keywords,
            'ats_tip' => 'Use exact phrasing from the job description. ATS systems match keywords literally.',
        );
    }

    /**
     * Generate networking strategy with outreach templates
     */
    private function generate_networking_strategy($job_data)
    {
        $company = $job_data['company'] ?? 'the company';
        $job_title = $job_data['job_title'] ?? 'this role';
        $job_family = $job_data['job_family'] ?? '';

        // Determine relevant contacts based on role
        $contacts = array();

        if (stripos($job_family, 'private equity') !== false) {
            $contacts = array(
                array(
                    'role' => 'Partner / Managing Director',
                    'why' => 'Decision maker for hiring at senior levels',
                    'approach' => 'Reference specific deals or fund strategy',
                ),
                array(
                    'role' => 'Principal / VP',
                    'why' => 'Often leads day-to-day hiring process',
                    'approach' => 'Focus on relevant deal experience',
                ),
                array(
                    'role' => 'Current Associates',
                    'why' => 'Insider perspective on culture and process',
                    'approach' => 'Ask about their path and experience',
                ),
            );
        } else {
            $contacts = array(
                array(
                    'role' => 'Hiring Manager / Department Head',
                    'why' => 'Direct decision maker for this role',
                    'approach' => 'Reference specific challenges they face',
                ),
                array(
                    'role' => 'HR / Talent Acquisition',
                    'why' => 'Controls interview scheduling',
                    'approach' => 'Professional, process-focused inquiry',
                ),
                array(
                    'role' => 'Team Members',
                    'why' => 'May provide referrals and insights',
                    'approach' => 'Genuine interest in their work',
                ),
            );
        }

        // Generate email template preview
        $email_preview = "Subject: {$job_title} Opportunity - Quick Question\n\n";
        $email_preview .= "Hi [Name],\n\n";
        $email_preview .= "I came across the {$job_title} role at {$company} and was impressed by...";

        // Generate LinkedIn message preview
        $linkedin_preview = "Hi [Name], I noticed you're at {$company} and I'm exploring the {$job_title} opportunity. Would love to learn more about the team's current focus...";

        return array(
            'title' => 'Referral Strategy',
            'subtitle' => 'Who to contact and how',
            'contacts' => $contacts,
            'templates' => array(
                'email' => array(
                    'preview' => $email_preview,
                    'full_available' => true,
                ),
                'linkedin' => array(
                    'preview' => $linkedin_preview,
                    'full_available' => true,
                ),
            ),
            'tip' => 'Personalize every message. Generic outreach has <5% response rate.',
        );
    }

    /**
     * Generate cover letter preview (first few sentences only)
     */
    private function generate_cover_letter_preview($job_data, $health_score)
    {
        $company = $job_data['company'] ?? '[Company]';
        $job_title = $job_data['job_title'] ?? 'this position';

        // Tailor opening based on score
        $opening = '';
        if ($health_score >= 80) {
            $opening = "With a strong track record in {$job_title}-relevant experience, I am excited to bring my expertise to {$company}.";
        } elseif ($health_score >= 60) {
            $opening = "My background in finance, combined with my passion for {$company}'s mission, makes me a compelling candidate for the {$job_title} role.";
        } else {
            $opening = "While transitioning into this space, I bring transferable skills and fresh perspective that would add unique value to {$company}.";
        }

        $preview = "Dear Hiring Manager,\n\n{$opening} Having researched {$company}'s recent initiatives, I am particularly drawn to...\n\n[Full letter generated on demand]";

        return array(
            'title' => 'Tailored Cover Letter',
            'preview' => $preview,
            'word_count_estimate' => '250-300 words',
            'sections' => array('Opening Hook', 'Relevant Experience', 'Company Fit', 'Call to Action'),
            'generation_available' => true,
        );
    }

    /**
     * Generate CV template preview
     */
    private function generate_cv_template_preview($job_data, $issues)
    {
        $job_title = $job_data['job_title'] ?? 'Target Role';

        // Identify sections to emphasize based on gaps
        $emphasis = array();
        $critical_gaps = $issues['critical'] ?? array();

        foreach ($critical_gaps as $gap) {
            if (isset($gap['category'])) {
                $emphasis[] = $gap['category'];
            }
        }

        return array(
            'title' => 'Optimized CV Template',
            'subtitle' => 'ATS-friendly format tailored for ' . $job_title,
            'format' => '1-page professional',
            'sections' => array(
                array('name' => 'Professional Summary', 'lines' => '3-4 lines', 'priority' => 'high'),
                array('name' => 'Key Achievements', 'lines' => '4-6 bullet points', 'priority' => 'high'),
                array('name' => 'Professional Experience', 'lines' => 'Last 3 roles', 'priority' => 'high'),
                array('name' => 'Education & Certifications', 'lines' => '2-3 lines', 'priority' => 'medium'),
                array('name' => 'Technical Skills', 'lines' => '1-2 lines', 'priority' => 'medium'),
            ),
            'emphasis_areas' => array_unique($emphasis),
            'preview_available' => true,
            'download_formats' => array('PDF', 'DOCX'),
        );
    }

    /**
     * Find similar/perfect roles
     */
    private function find_perfect_roles($job_id, $job_data)
    {
        $similar_roles = array();

        // Query similar jobs
        $args = array(
            'post_type' => 'sffc_job',
            'posts_per_page' => 5,
            'post__not_in' => array($job_id),
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'sffc_job_family',
                    'value' => $job_data['job_family'] ?? '',
                    'compare' => 'LIKE',
                ),
            ),
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $similar_roles[] = array(
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'company' => get_post_meta(get_the_ID(), 'sffc_actual_company', true) ?: get_post_meta(get_the_ID(), 'sffc_source_name', true),
                    'location' => get_post_meta(get_the_ID(), 'sffc_location_city', true),
                    'match_reason' => 'Similar role type',
                    'url' => get_permalink(),
                );
            }
            wp_reset_postdata();
        }

        return array(
            'title' => 'Perfect Roles For You',
            'subtitle' => 'Based on your profile and this role',
            'roles' => $similar_roles,
            'view_all_url' => '/jobs/?similar_to=' . $job_id,
        );
    }

    /**
     * Generate target companies list
     */
    private function generate_target_companies($job_data)
    {
        $location = $job_data['location'] ?? '';
        $job_family = $job_data['job_family'] ?? '';
        $current_company = $job_data['company'] ?? '';

        // Finance industry companies by category
        $companies_by_type = array(
            'private_equity' => array(
                array('name' => 'Blackstone', 'type' => 'Mega Fund', 'hiring_score' => 92),
                array('name' => 'KKR', 'type' => 'Mega Fund', 'hiring_score' => 89),
                array('name' => 'Apollo', 'type' => 'Mega Fund', 'hiring_score' => 87),
                array('name' => 'Carlyle', 'type' => 'Mega Fund', 'hiring_score' => 85),
                array('name' => 'TPG', 'type' => 'Mega Fund', 'hiring_score' => 82),
                array('name' => 'Warburg Pincus', 'type' => 'Growth Equity', 'hiring_score' => 80),
                array('name' => 'General Atlantic', 'type' => 'Growth Equity', 'hiring_score' => 78),
                array('name' => 'Silver Lake', 'type' => 'Tech PE', 'hiring_score' => 76),
            ),
            'investment_banking' => array(
                array('name' => 'Goldman Sachs', 'type' => 'Bulge Bracket', 'hiring_score' => 95),
                array('name' => 'Morgan Stanley', 'type' => 'Bulge Bracket', 'hiring_score' => 93),
                array('name' => 'J.P. Morgan', 'type' => 'Bulge Bracket', 'hiring_score' => 92),
                array('name' => 'Bank of America', 'type' => 'Bulge Bracket', 'hiring_score' => 88),
                array('name' => 'Citi', 'type' => 'Bulge Bracket', 'hiring_score' => 85),
                array('name' => 'Evercore', 'type' => 'Elite Boutique', 'hiring_score' => 82),
                array('name' => 'Lazard', 'type' => 'Elite Boutique', 'hiring_score' => 80),
                array('name' => 'Moelis', 'type' => 'Elite Boutique', 'hiring_score' => 78),
            ),
            'corporate_finance' => array(
                array('name' => 'Apple', 'type' => 'Tech', 'hiring_score' => 90),
                array('name' => 'Google', 'type' => 'Tech', 'hiring_score' => 88),
                array('name' => 'Amazon', 'type' => 'Tech', 'hiring_score' => 87),
                array('name' => 'Microsoft', 'type' => 'Tech', 'hiring_score' => 85),
                array('name' => 'Meta', 'type' => 'Tech', 'hiring_score' => 82),
                array('name' => 'Netflix', 'type' => 'Tech', 'hiring_score' => 78),
                array('name' => 'Salesforce', 'type' => 'Tech', 'hiring_score' => 75),
            ),
        );

        // Determine category
        $category = 'corporate_finance';
        if (stripos($job_family, 'private equity') !== false) {
            $category = 'private_equity';
        } elseif (stripos($job_family, 'investment banking') !== false) {
            $category = 'investment_banking';
        }

        $companies = $companies_by_type[$category];

        // Filter out current company
        $companies = array_filter($companies, function ($c) use ($current_company) {
            return stripos($current_company, $c['name']) === false;
        });

        return array(
            'title' => 'Companies to Consider',
            'subtitle' => 'Top employers in ' . ($location ?: 'your market'),
            'companies' => array_values(array_slice($companies, 0, 6)),
            'insight' => 'These companies frequently hire for similar roles and have strong career progression.',
        );
    }

    /**
     * Generate location insight
     */
    private function generate_location_insight($locations, $current_location)
    {
        $top_location = $locations[0]['city'] ?? 'New York';

        if ($current_location && stripos($current_location, $top_location) !== false) {
            return "You're in {$top_location} - the top market for this role. Excellent positioning.";
        }

        return "{$top_location} leads in job availability. Consider remote-first roles or relocation for maximum opportunities.";
    }

    /**
     * Generate full content on demand (Claude API)
     */
    private function generate_full_content($job_id, $content_type, $user_context)
    {
        $job_data = $this->get_job_data($job_id);
        $job_description = get_post_field('post_content', $job_id);

        switch ($content_type) {
            case 'cover_letter':
                return $this->generate_full_cover_letter($job_data, $job_description, $user_context);
            case 'cv_template':
                return $this->generate_full_cv_template($job_data, $job_description, $user_context);
            case 'email_template':
                return $this->generate_full_email_template($job_data, $user_context);
            case 'linkedin_message':
                return $this->generate_full_linkedin_message($job_data, $user_context);
            default:
                return array('error' => 'Unknown content type');
        }
    }

    /**
     * Generate full cover letter using Claude API
     */
    private function generate_full_cover_letter($job_data, $job_description, $user_context)
    {
        $api_key = get_option('sffc_claude_api_key', '');

        if (empty($api_key)) {
            return $this->generate_fallback_cover_letter($job_data, $user_context);
        }

        $prompt = $this->build_cover_letter_prompt($job_data, $job_description, $user_context);

        $response = $this->call_claude_api($api_key, $prompt, 1000);

        if ($response && isset($response['content'])) {
            return array(
                'success' => true,
                'content' => $response['content'],
                'word_count' => str_word_count($response['content']),
            );
        }

        return $this->generate_fallback_cover_letter($job_data, $user_context);
    }

    /**
     * Build cover letter prompt
     */
    private function build_cover_letter_prompt($job_data, $job_description, $user_context)
    {
        $company = $job_data['company'] ?? '[Company]';
        $job_title = $job_data['job_title'] ?? 'this position';
        $user_experience = $user_context['experience'] ?? '';
        $user_strengths = $user_context['strengths'] ?? array();

        return "Write a compelling, professional cover letter for a {$job_title} position at {$company}.

Job Description Summary:
{$job_description}

Candidate Background:
- Experience: {$user_experience}
- Key Strengths: " . implode(', ', $user_strengths) . "

Requirements:
1. 250-300 words maximum
2. Professional but personable tone
3. Specific reference to the company
4. Clear value proposition
5. Strong call to action
6. No generic phrases like 'I am writing to apply'

Format as a proper letter with greeting and signature placeholder.";
    }

    /**
     * Fallback cover letter (no API)
     */
    private function generate_fallback_cover_letter($job_data, $user_context)
    {
        $company = $job_data['company'] ?? '[Company]';
        $job_title = $job_data['job_title'] ?? 'this position';
        $candidate_first_name = $this->get_application_audit_candidate_first_name($user_context);

        $template = "Dear Hiring Manager,

Your {$job_title} role immediately caught my attention. {$company}'s reputation for [specific achievement/value] aligns perfectly with my career aspirations and professional values.

In my current role, I have [key achievement relevant to the position]. This experience has equipped me with [relevant skill 1] and [relevant skill 2], which directly translate to the requirements outlined in your job description.

What excites me most about this opportunity is [specific aspect of role/company]. I am confident that my background in [relevant area] would allow me to contribute meaningfully from day one.

I would welcome the opportunity to discuss how my experience can benefit {$company}. Thank you for considering my application.

Best regards,
{$candidate_first_name}";

        return array(
            'success' => true,
            'content' => $template,
            'word_count' => str_word_count($template),
            'is_template' => true,
            'note' => 'Template generated. Customize the bracketed sections with your specific details.',
        );
    }

    private function get_application_audit_candidate_first_name($user_context)
    {
        $candidate_sources = array(
            $user_context['first_name'] ?? '',
            $user_context['firstName'] ?? '',
            $user_context['name'] ?? '',
            $user_context['full_name'] ?? '',
        );

        $current_user = wp_get_current_user();
        if ($current_user instanceof WP_User && $current_user->exists()) {
            $candidate_sources[] = $current_user->first_name;
            $candidate_sources[] = $current_user->display_name;
        }

        foreach ($candidate_sources as $candidate_source) {
            $candidate_source = trim((string) $candidate_source);
            if ($candidate_source === '') {
                continue;
            }

            $name_parts = preg_split('/\s+/', $candidate_source);
            $first_name = trim((string) ($name_parts[0] ?? ''));
            if ($first_name !== '') {
                return $first_name;
            }
        }

        return 'Candidate';
    }

    /**
     * Generate full CV template
     */
    private function generate_full_cv_template($job_data, $job_description, $user_context)
    {
        $job_title = $job_data['job_title'] ?? 'Target Role';
        $candidate_first_name = $this->get_application_audit_candidate_first_name($user_context);

        $template = array(
            'format' => 'single_page',
            'sections' => array(
                array(
                    'name' => 'header',
                    'content' => array(
                        'name' => $candidate_first_name,
                        'title' => $job_title . ' Professional',
                        'contact' => '[Email] | [Phone] | [LinkedIn] | [Location]',
                    ),
                ),
                array(
                    'name' => 'summary',
                    'title' => 'Professional Summary',
                    'content' => "[X] years of experience in [relevant field]. Proven track record in [key achievement]. Expertise in [skill 1], [skill 2], and [skill 3]. Seeking to leverage [unique value prop] at {$job_data['company']}.",
                ),
                array(
                    'name' => 'achievements',
                    'title' => 'Key Achievements',
                    'bullets' => array(
                        '• Delivered [quantified result] by implementing [action]',
                        '• Led [project/initiative] resulting in [measurable outcome]',
                        '• Recognized for [achievement] with [award/recognition]',
                        '• Improved [metric] by [X%] through [method]',
                    ),
                ),
                array(
                    'name' => 'experience',
                    'title' => 'Professional Experience',
                    'jobs' => array(
                        array(
                            'title' => '[Current Title]',
                            'company' => '[Current Company]',
                            'period' => '[Start] - Present',
                            'bullets' => array(
                                '• [Action verb] + [task] + [result/impact]',
                                '• [Action verb] + [task] + [result/impact]',
                                '• [Action verb] + [task] + [result/impact]',
                            ),
                        ),
                    ),
                ),
                array(
                    'name' => 'education',
                    'title' => 'Education & Certifications',
                    'items' => array(
                        '[Degree], [University], [Year]',
                        '[Certification], [Issuing Body]',
                    ),
                ),
                array(
                    'name' => 'skills',
                    'title' => 'Technical Skills',
                    'content' => '[Skill 1] • [Skill 2] • [Skill 3] • [Skill 4] • [Skill 5]',
                ),
            ),
            'styling_notes' => array(
                'Font: Calibri or Arial, 10-11pt',
                'Margins: 0.5-0.75 inches',
                'Use bold for company names and titles',
                'Consistent bullet formatting',
                'No photos or graphics for ATS compatibility',
            ),
        );

        return array(
            'success' => true,
            'template' => $template,
            'download_available' => true,
        );
    }

    /**
     * Generate full email template
     */
    private function generate_full_email_template($job_data, $user_context)
    {
        $company = $job_data['company'] ?? '[Company]';
        $job_title = $job_data['job_title'] ?? 'this role';
        $candidate_first_name = $this->get_application_audit_candidate_first_name($user_context);

        $template = "Subject: {$job_title} Opportunity - Quick Question

Hi [Name],

I came across your profile while researching {$company}'s team, and I noticed your impressive work in [specific area]. I'm exploring the {$job_title} opportunity and wanted to reach out.

A bit about me: I'm currently [brief background] with experience in [relevant area]. What drew me to {$company} specifically is [genuine reason - recent news, company values, etc.].

Would you have 15 minutes for a brief call this week? I'd love to learn more about:
- The team's current priorities
- What success looks like in this role
- Your experience at {$company}

I completely understand if you're busy - any guidance would be greatly appreciated.

Best regards,
{$candidate_first_name}

P.S. I noticed [something specific about their work/posts]. [Brief relevant comment].";

        return array(
            'success' => true,
            'content' => $template,
            'subject_line' => "{$job_title} Opportunity - Quick Question",
            'tips' => array(
                'Send Tuesday-Thursday, 9-11 AM recipient\'s time',
                'Follow up once after 5-7 days if no response',
                'Personalize the P.S. for each recipient',
            ),
        );
    }

    /**
     * Generate full LinkedIn message
     */
    private function generate_full_linkedin_message($job_data, $user_context)
    {
        $company = $job_data['company'] ?? '[Company]';
        $job_title = $job_data['job_title'] ?? 'this role';
        $candidate_first_name = $this->get_application_audit_candidate_first_name($user_context);

        $template = "Hi [Name],

I noticed you're at {$company} and I'm exploring the {$job_title} opportunity. Your background in [their specialty] really resonates with my experience in [relevant area].

I'd love to learn about your journey at {$company} and any insights on what makes someone successful in this type of role.

Would you be open to a brief chat? Happy to work around your schedule.

Thanks for considering!
{$candidate_first_name}";

        return array(
            'success' => true,
            'content' => $template,
            'character_count' => strlen($template),
            'tips' => array(
                'Keep under 300 characters for connection requests',
                'Reference something specific from their profile',
                'Be genuinely curious, not transactional',
            ),
        );
    }

    /**
     * Call Claude API
     */
    private function call_claude_api($api_key, $prompt, $max_tokens = 1000)
    {
        $url = 'https://api.anthropic.com/v1/messages';

        $body = array(
            'model' => 'claude-3-haiku-20240307',
            'max_tokens' => $max_tokens,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt,
                ),
            ),
        );

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
            ),
            'body' => json_encode($body),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            error_log('Claude API error: ' . $response->get_error_message());
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['content'][0]['text'])) {
            return array(
                'content' => $body['content'][0]['text'],
            );
        }

        return null;
    }
}

// Initialize
SFFC_Application_Audit::get_instance();
