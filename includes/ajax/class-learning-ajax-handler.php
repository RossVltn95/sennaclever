<?php
/**
 * Learning Platform AJAX Handler
 * Handles all AJAX requests for learning platform
 *
 * @package SennaCareers
 * @subpackage Learning
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Learning_Ajax_Handler {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Register AJAX endpoints
        add_action('wp_ajax_sffc_get_learning_courses', [$this, 'get_courses']);
        add_action('wp_ajax_nopriv_sffc_get_learning_courses', [$this, 'get_courses']);

        add_action('wp_ajax_sffc_search_learning_courses', [$this, 'search_courses']);
        add_action('wp_ajax_nopriv_sffc_search_learning_courses', [$this, 'search_courses']);

        add_action('wp_ajax_sffc_enroll_course', [$this, 'enroll_course']);

        add_action('wp_ajax_sffc_get_user_learning_progress', [$this, 'get_user_progress']);

        add_action('wp_ajax_sffc_get_interview_history', [$this, 'get_interview_history']);

        add_action('wp_ajax_sffc_get_user_certificates', [$this, 'get_user_certificates']);

        add_action('wp_ajax_sffc_mark_lesson_complete', [$this, 'mark_lesson_complete']);

        // Mock Interview endpoints
        add_action('wp_ajax_sffc_start_mock_interview', [$this, 'start_mock_interview']);
        add_action('wp_ajax_sffc_submit_interview_answer', [$this, 'submit_interview_answer']);
        add_action('wp_ajax_sffc_end_mock_interview', [$this, 'end_mock_interview']);
        add_action('wp_ajax_sffc_get_interview_stats', [$this, 'get_interview_stats']);
    }

    /**
     * Get all learning courses
     */
    public function get_courses() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_crm_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        // Get courses from database
        $args = [
            'post_type' => 'sffc_job',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'content_type',
                    'value' => 'course',
                    'compare' => '='
                ]
            ],
            'orderby' => 'menu_order title',
            'order' => 'ASC'
        ];

        $query = new WP_Query($args);

        $courses = [];
        $user_id = get_current_user_id();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $course_id = get_the_ID();

                // Get course meta
                $category = get_post_meta($course_id, 'course_category', true);
                $difficulty = get_post_meta($course_id, 'difficulty_level', true);
                $hours = get_post_meta($course_id, 'estimated_hours', true);
                $lessons = get_post_meta($course_id, 'total_lessons', true);
                $instructor = get_post_meta($course_id, 'instructor_name', true);
                $skills = get_post_meta($course_id, 'sffc_skills', true);

                // Get user progress if logged in
                $progress = 0;
                if ($user_id) {
                    $progress = $this->get_course_progress($user_id, $course_id);
                }

                $courses[] = [
                    'id' => $course_id,
                    'title' => get_the_title(),
                    'slug' => get_post_field('post_name', $course_id),
                    'description' => get_post_meta($course_id, 'sffc_description', true),
                    'category' => $category,
                    'difficulty' => $difficulty,
                    'hours' => intval($hours),
                    'lessons' => intval($lessons),
                    'instructor' => $instructor,
                    'skills' => is_array($skills) ? $skills : [],
                    'progress' => $progress
                ];
            }
            wp_reset_postdata();
        }

        wp_send_json_success(['courses' => $courses]);
    }

    /**
     * Search courses by query
     */
    public function search_courses() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_crm_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        $query_string = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';

        if (empty($query_string)) {
            $this->get_courses();
            return;
        }

        $args = [
            'post_type' => 'sffc_job',
            'posts_per_page' => -1,
            's' => $query_string,
            'meta_query' => [
                [
                    'key' => 'content_type',
                    'value' => 'course',
                    'compare' => '='
                ]
            ]
        ];

        $query = new WP_Query($args);

        $courses = [];
        $user_id = get_current_user_id();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $course_id = get_the_ID();

                $category = get_post_meta($course_id, 'course_category', true);
                $difficulty = get_post_meta($course_id, 'difficulty_level', true);
                $hours = get_post_meta($course_id, 'estimated_hours', true);
                $lessons = get_post_meta($course_id, 'total_lessons', true);
                $instructor = get_post_meta($course_id, 'instructor_name', true);
                $skills = get_post_meta($course_id, 'sffc_skills', true);

                $progress = 0;
                if ($user_id) {
                    $progress = $this->get_course_progress($user_id, $course_id);
                }

                $courses[] = [
                    'id' => $course_id,
                    'title' => get_the_title(),
                    'slug' => get_post_field('post_name', $course_id),
                    'description' => get_post_meta($course_id, 'sffc_description', true),
                    'category' => $category,
                    'difficulty' => $difficulty,
                    'hours' => intval($hours),
                    'lessons' => intval($lessons),
                    'instructor' => $instructor,
                    'skills' => is_array($skills) ? $skills : [],
                    'progress' => $progress
                ];
            }
            wp_reset_postdata();
        }

        wp_send_json_success(['courses' => $courses]);
    }

    /**
     * Enroll user in course
     */
    public function enroll_course() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_crm_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Please log in to enroll']);
        }

        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        if (!$course_id) {
            wp_send_json_error(['message' => 'Invalid course ID']);
        }

        global $wpdb;

        // Create initial progress record
        $result = $wpdb->insert(
            $wpdb->prefix . 'sffc_learning_progress',
            [
                'user_id' => $user_id,
                'course_id' => $course_id,
                'progress_percentage' => 0.00,
                'last_accessed' => current_time('mysql'),
                'completed' => 0
            ],
            ['%d', '%d', '%f', '%s', '%d']
        );

        if ($result === false) {
            wp_send_json_error(['message' => 'Enrollment failed. You may already be enrolled.']);
        }

        wp_send_json_success([
            'message' => 'Enrolled successfully!',
            'course_id' => $course_id
        ]);
    }

    /**
     * Get user's learning progress
     */
    public function get_user_progress() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_crm_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_success([
                'enrolled' => 0,
                'hours' => 0,
                'streak' => 0,
                'certificates' => 0,
                'in_progress' => [],
                'completed' => []
            ]);
            return;
        }

        global $wpdb;

        // Get enrolled courses
        $in_progress = $wpdb->get_results($wpdb->prepare(
            "SELECT course_id, progress_percentage, last_accessed
             FROM {$wpdb->prefix}sffc_learning_progress
             WHERE user_id = %d AND completed = 0 AND progress_percentage > 0
             ORDER BY last_accessed DESC",
            $user_id
        ));

        // Get completed courses
        $completed = $wpdb->get_results($wpdb->prepare(
            "SELECT course_id, completed_at
             FROM {$wpdb->prefix}sffc_learning_progress
             WHERE user_id = %d AND completed = 1
             ORDER BY completed_at DESC",
            $user_id
        ));

        // Get certificates count
        $certificates = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sffc_certificates WHERE user_id = %d",
            $user_id
        ));

        // Get total hours learned (sum of time_spent_seconds / 3600)
        $total_seconds = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(time_spent_seconds) FROM {$wpdb->prefix}sffc_learning_progress WHERE user_id = %d",
            $user_id
        ));
        $hours = $total_seconds ? round($total_seconds / 3600, 1) : 0;

        // Calculate streak (simplified - count consecutive days)
        $streak = 0; // TODO: Implement streak calculation

        // Format in-progress courses
        $in_progress_formatted = [];
        foreach ($in_progress as $item) {
            $course = get_post($item->course_id);
            if ($course) {
                $in_progress_formatted[] = [
                    'id' => $item->course_id,
                    'title' => $course->post_title,
                    'progress' => round($item->progress_percentage, 0),
                    'last_lesson' => 'Lesson ' . ceil($item->progress_percentage / 10) // Estimate
                ];
            }
        }

        // Format completed courses
        $completed_formatted = [];
        foreach ($completed as $item) {
            $course = get_post($item->course_id);
            if ($course) {
                $completed_formatted[] = [
                    'id' => $item->course_id,
                    'title' => $course->post_title,
                    'completed_date' => date('M j, Y', strtotime($item->completed_at))
                ];
            }
        }

        wp_send_json_success([
            'enrolled' => count($in_progress) + count($completed),
            'hours' => $hours,
            'streak' => $streak,
            'certificates' => intval($certificates),
            'in_progress' => $in_progress_formatted,
            'completed' => $completed_formatted
        ]);
    }

    /**
     * Get interview history
     */
    public function get_interview_history() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_crm_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_success(['interviews' => []]);
            return;
        }

        global $wpdb;

        $interviews = $wpdb->get_results($wpdb->prepare(
            "SELECT interview_type, overall_score, conducted_at
             FROM {$wpdb->prefix}sffc_mock_interviews
             WHERE user_id = %d
             ORDER BY conducted_at DESC
             LIMIT 10",
            $user_id
        ));

        $formatted = [];
        foreach ($interviews as $interview) {
            $formatted[] = [
                'type' => $interview->interview_type,
                'score' => round($interview->overall_score, 0),
                'date' => date('M j, Y', strtotime($interview->conducted_at))
            ];
        }

        wp_send_json_success($formatted);
    }

    /**
     * Get user certificates
     */
    public function get_user_certificates() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_crm_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_success(['certificates' => []]);
            return;
        }

        global $wpdb;

        $certificates = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sffc_certificates
             WHERE user_id = %d
             ORDER BY issued_at DESC",
            $user_id
        ));

        $formatted = [];
        foreach ($certificates as $cert) {
            $course = get_post($cert->course_id);
            $formatted[] = [
                'id' => $cert->id,
                'course_id' => $cert->course_id,
                'course_title' => $course ? $course->post_title : 'Unknown Course',
                'certificate_number' => $cert->certificate_number,
                'issued_date' => date('M j, Y', strtotime($cert->issued_at)),
                'pdf_url' => $cert->pdf_url,
                'verification_url' => $cert->credential_url
            ];
        }

        wp_send_json_success($formatted);
    }

    /**
     * Helper: Get course progress for user
     */
    private function get_course_progress($user_id, $course_id) {
        global $wpdb;

        $progress = $wpdb->get_var($wpdb->prepare(
            "SELECT progress_percentage FROM {$wpdb->prefix}sffc_learning_progress
             WHERE user_id = %d AND course_id = %d
             ORDER BY id DESC LIMIT 1",
            $user_id,
            $course_id
        ));

        return $progress ? round(floatval($progress), 0) : 0;
    }

    /**
     * Mark lesson as complete
     */
    public function mark_lesson_complete() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_crm_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Please log in']);
        }

        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        $module_id = isset($_POST['module_id']) ? intval($_POST['module_id']) : 0;
        $lesson_id = isset($_POST['lesson_id']) ? intval($_POST['lesson_id']) : 0;

        if (!$course_id || !$module_id || !$lesson_id) {
            wp_send_json_error(['message' => 'Invalid parameters']);
        }

        // Get or create progress record
        global $wpdb;

        $progress_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sffc_learning_progress
             WHERE user_id = %d AND course_id = %d
             ORDER BY id DESC LIMIT 1",
            $user_id,
            $course_id
        ));

        if (!$progress_record) {
            // Create initial progress record
            $wpdb->insert(
                $wpdb->prefix . 'sffc_learning_progress',
                [
                    'user_id' => $user_id,
                    'course_id' => $course_id,
                    'module_id' => $module_id,
                    'lesson_id' => $lesson_id,
                    'progress_percentage' => 0.00,
                    'last_accessed' => current_time('mysql'),
                    'completed' => 0
                ],
                ['%d', '%d', '%d', '%d', '%f', '%s', '%d']
            );
        }

        // Get completed lessons list
        $lesson_key = "module_{$module_id}_lesson_{$lesson_id}";
        $completed_lessons = get_user_meta($user_id, "course_{$course_id}_completed_lessons", true);
        $completed_lessons = is_array($completed_lessons) ? $completed_lessons : [];

        // Add to completed if not already there
        if (!in_array($lesson_key, $completed_lessons)) {
            $completed_lessons[] = $lesson_key;
            update_user_meta($user_id, "course_{$course_id}_completed_lessons", $completed_lessons);
        }

        // Calculate progress percentage
        $total_lessons = (int) get_post_meta($course_id, 'total_lessons', true);
        $progress_percentage = $total_lessons > 0 ? (count($completed_lessons) / $total_lessons) * 100 : 0;

        // Update progress record
        $wpdb->update(
            $wpdb->prefix . 'sffc_learning_progress',
            [
                'module_id' => $module_id,
                'lesson_id' => $lesson_id,
                'progress_percentage' => $progress_percentage,
                'last_accessed' => current_time('mysql'),
                'completed' => $progress_percentage >= 100 ? 1 : 0,
                'completed_at' => $progress_percentage >= 100 ? current_time('mysql') : null
            ],
            [
                'user_id' => $user_id,
                'course_id' => $course_id
            ],
            ['%d', '%d', '%f', '%s', '%d', '%s'],
            ['%d', '%d']
        );

        wp_send_json_success([
            'message' => 'Lesson marked complete!',
            'progress_percentage' => round($progress_percentage, 1),
            'completed_lessons' => count($completed_lessons),
            'total_lessons' => $total_lessons
        ]);
    }

    /**
     * Start mock interview
     */
    public function start_mock_interview() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_crm_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Please log in']);
        }

        $interview_type = isset($_POST['interview_type']) ? sanitize_text_field($_POST['interview_type']) : '';
        $difficulty_level = isset($_POST['difficulty_level']) ? sanitize_text_field($_POST['difficulty_level']) : '';

        if (!$interview_type || !$difficulty_level) {
            wp_send_json_error(['message' => 'Invalid parameters']);
        }

        // Load mock interview class
        require_once SFFC_PLUGIN_DIR . 'includes/learning/class-mock-interview.php';
        $mock_interview = SFFC_Mock_Interview::get_instance();

        // Get questions for this interview
        $questions = $mock_interview->get_interview_questions($interview_type, $difficulty_level);

        // Store interview session in transient
        $session_id = 'interview_' . $user_id . '_' . time();
        set_transient($session_id, [
            'type' => $interview_type,
            'level' => $difficulty_level,
            'questions' => $questions,
            'current_question' => 0,
            'responses' => [],
            'feedback' => [],
            'start_time' => time()
        ], 2 * HOUR_IN_SECONDS);

        wp_send_json_success([
            'session_id' => $session_id,
            'total_questions' => count($questions),
            'first_question' => $questions[0],
            'interview_type' => $interview_type,
            'difficulty_level' => $difficulty_level
        ]);
    }

    /**
     * Submit interview answer and get feedback
     */
    public function submit_interview_answer() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_crm_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Please log in']);
        }

        $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
        $answer = isset($_POST['answer']) ? wp_kses_post($_POST['answer']) : '';

        if (!$session_id || !$answer) {
            wp_send_json_error(['message' => 'Invalid parameters']);
        }

        // Get session data
        $session = get_transient($session_id);
        if (!$session) {
            wp_send_json_error(['message' => 'Session expired']);
        }

        // Load mock interview class
        require_once SFFC_PLUGIN_DIR . 'includes/learning/class-mock-interview.php';
        $mock_interview = SFFC_Mock_Interview::get_instance();

        $current_index = $session['current_question'];
        $current_question = $session['questions'][$current_index];

        // Evaluate answer
        $feedback = $mock_interview->evaluate_answer($current_question, $answer, $session);
        $feedback['question'] = $current_question;

        // Store response and feedback
        $session['responses'][] = [
            'question_index' => $current_index,
            'answer' => $answer,
            'timestamp' => time()
        ];
        $session['feedback'][] = $feedback;

        // Move to next question
        $session['current_question']++;
        $has_next = $session['current_question'] < count($session['questions']);
        $next_question = $has_next ? $session['questions'][$session['current_question']] : null;

        // Update session
        set_transient($session_id, $session, 2 * HOUR_IN_SECONDS);

        wp_send_json_success([
            'feedback' => $feedback,
            'has_next' => $has_next,
            'next_question' => $next_question,
            'progress' => [
                'current' => $session['current_question'],
                'total' => count($session['questions'])
            ]
        ]);
    }

    /**
     * End interview and save results
     */
    public function end_mock_interview() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_crm_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Please log in']);
        }

        $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';

        if (!$session_id) {
            wp_send_json_error(['message' => 'Invalid parameters']);
        }

        // Get session data
        $session = get_transient($session_id);
        if (!$session) {
            wp_send_json_error(['message' => 'Session expired']);
        }

        // Load mock interview class
        require_once SFFC_PLUGIN_DIR . 'includes/learning/class-mock-interview.php';
        $mock_interview = SFFC_Mock_Interview::get_instance();

        // Calculate final scores
        $scores = $mock_interview->calculate_interview_score($session['feedback']);

        // Generate recommendations
        $recommendations = $mock_interview->generate_recommendations($session['feedback'], $scores);

        // Extract strengths and improvements
        $all_strengths = [];
        $all_improvements = [];
        foreach ($session['feedback'] as $fb) {
            $all_strengths = array_merge($all_strengths, $fb['strengths']);
            $all_improvements = array_merge($all_improvements, $fb['missed_points']);
        }
        $all_strengths = array_unique($all_strengths);
        $all_improvements = array_unique($all_improvements);

        // Save to database
        $interview_data = [
            'type' => $session['type'],
            'level' => $session['level'],
            'questions' => $session['questions'],
            'responses' => $session['responses'],
            'feedback' => $session['feedback'],
            'scores' => $scores,
            'strengths' => array_slice($all_strengths, 0, 5),
            'improvements' => array_slice($all_improvements, 0, 5),
            'recommendations' => $recommendations,
            'duration' => time() - $session['start_time']
        ];

        $interview_id = $mock_interview->save_interview($user_id, $interview_data);

        // Delete session
        delete_transient($session_id);

        wp_send_json_success([
            'interview_id' => $interview_id,
            'scores' => $scores,
            'strengths' => $all_strengths,
            'improvements' => $all_improvements,
            'recommendations' => $recommendations,
            'duration' => $interview_data['duration'],
            'questions_answered' => count($session['responses'])
        ]);
    }

    /**
     * Get interview statistics
     */
    public function get_interview_stats() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffc_crm_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Please log in']);
        }

        // Load mock interview class
        require_once SFFC_PLUGIN_DIR . 'includes/learning/class-mock-interview.php';
        $mock_interview = SFFC_Mock_Interview::get_instance();

        $stats = $mock_interview->get_interview_stats($user_id);
        $history = $mock_interview->get_user_interviews($user_id, 10);

        wp_send_json_success([
            'stats' => $stats,
            'history' => $history
        ]);
    }
}

// Initialize
SFFC_Learning_Ajax_Handler::get_instance();
