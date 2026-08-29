<?php
/**
 * Course Meta Fields Handler
 * Manages custom meta fields for learning courses
 *
 * @package SennaCareers
 * @subpackage Learning
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Course_Meta_Fields {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Register meta fields for courses
        add_action('init', [$this, 'register_course_meta_fields']);
    }

    /**
     * Register all course-specific meta fields
     */
    public function register_course_meta_fields() {

        // Distinguish between job posts and course posts
        register_post_meta('sffc_job', 'content_type', [
            'type' => 'string',
            'description' => 'Type of content: job or course',
            'single' => true,
            'default' => 'job',
            'show_in_rest' => true
        ]);

        // ============================================================
        // COURSE-SPECIFIC META FIELDS
        // ============================================================

        // Course Provider (reusing sffc_company field)
        // e.g., "MENA Careers Academy", "Claude AI", "Expert Instructor"

        // Learning Mode (reusing sffc_location field)
        // e.g., "Self-paced", "Live Sessions", "Hybrid", "Online"

        // Course Description (reusing sffc_description field)

        // Prerequisites (reusing sffc_requirements field)

        // Skills Taught (reusing sffc_skills field - array)

        // New Course Fields
        register_post_meta('sffc_job', 'course_category', [
            'type' => 'string',
            'description' => 'Course category: Financial Modeling, Interview Prep, Excel, etc.',
            'single' => true,
            'show_in_rest' => true
        ]);

        register_post_meta('sffc_job', 'difficulty_level', [
            'type' => 'string',
            'description' => 'Difficulty: Beginner, Intermediate, Advanced, Expert',
            'single' => true,
            'show_in_rest' => true
        ]);

        register_post_meta('sffc_job', 'estimated_hours', [
            'type' => 'number',
            'description' => 'Estimated completion time in hours',
            'single' => true,
            'show_in_rest' => true
        ]);

        register_post_meta('sffc_job', 'total_lessons', [
            'type' => 'integer',
            'description' => 'Total number of lessons',
            'single' => true,
            'show_in_rest' => true
        ]);

        register_post_meta('sffc_job', 'total_quizzes', [
            'type' => 'integer',
            'description' => 'Total number of quizzes',
            'single' => true,
            'show_in_rest' => true
        ]);

        register_post_meta('sffc_job', 'has_certificate', [
            'type' => 'boolean',
            'description' => 'Whether course offers certificate',
            'single' => true,
            'default' => true,
            'show_in_rest' => true
        ]);

        register_post_meta('sffc_job', 'has_ai_tutor', [
            'type' => 'boolean',
            'description' => 'Whether course has AI tutor (Claude)',
            'single' => true,
            'default' => true,
            'show_in_rest' => true
        ]);

        register_post_meta('sffc_job', 'course_modules', [
            'type' => 'string',
            'description' => 'JSON array of course modules',
            'single' => true,
            'show_in_rest' => true
        ]);

        register_post_meta('sffc_job', 'instructor_name', [
            'type' => 'string',
            'description' => 'Instructor name',
            'single' => true,
            'default' => 'Claude AI',
            'show_in_rest' => true
        ]);

        register_post_meta('sffc_job', 'instructor_bio', [
            'type' => 'string',
            'description' => 'Instructor biography',
            'single' => true,
            'show_in_rest' => true
        ]);

        register_post_meta('sffc_job', 'certification_type', [
            'type' => 'string',
            'description' => 'Type of certification offered',
            'single' => true,
            'default' => 'MENA Careers Certified Analyst',
            'show_in_rest' => true
        ]);

        register_post_meta('sffc_job', 'course_price', [
            'type' => 'string',
            'description' => 'Course pricing tier: Free, Premium, Enterprise',
            'single' => true,
            'default' => 'Free',
            'show_in_rest' => true
        ]);

        register_post_meta('sffc_job', 'learning_outcomes', [
            'type' => 'string',
            'description' => 'JSON array of learning outcomes',
            'single' => true,
            'show_in_rest' => true
        ]);
    }

    /**
     * Get all meta fields for a course
     *
     * @param int $course_id Post ID
     * @return array Course metadata
     */
    public function get_course_meta($course_id) {
        // Check if this is actually a course
        if (get_post_meta($course_id, 'content_type', true) !== 'course') {
            return [];
        }

        return [
            'content_type' => get_post_meta($course_id, 'content_type', true),

            // Reused job fields
            'course_provider' => get_post_meta($course_id, 'sffc_company', true),
            'learning_mode' => get_post_meta($course_id, 'sffc_location', true),
            'description' => get_post_meta($course_id, 'sffc_description', true),
            'prerequisites' => get_post_meta($course_id, 'sffc_requirements', true),
            'skills_taught' => get_post_meta($course_id, 'sffc_skills', true),

            // Course-specific fields
            'category' => get_post_meta($course_id, 'course_category', true),
            'difficulty_level' => get_post_meta($course_id, 'difficulty_level', true),
            'estimated_hours' => (int) get_post_meta($course_id, 'estimated_hours', true),
            'total_lessons' => (int) get_post_meta($course_id, 'total_lessons', true),
            'total_quizzes' => (int) get_post_meta($course_id, 'total_quizzes', true),
            'has_certificate' => (bool) get_post_meta($course_id, 'has_certificate', true),
            'has_ai_tutor' => (bool) get_post_meta($course_id, 'has_ai_tutor', true),
            'modules' => json_decode(get_post_meta($course_id, 'course_modules', true), true),
            'instructor_name' => get_post_meta($course_id, 'instructor_name', true),
            'instructor_bio' => get_post_meta($course_id, 'instructor_bio', true),
            'certification_type' => get_post_meta($course_id, 'certification_type', true),
            'price' => get_post_meta($course_id, 'course_price', true),
            'learning_outcomes' => json_decode(get_post_meta($course_id, 'learning_outcomes', true), true)
        ];
    }

    /**
     * Update course meta fields
     *
     * @param int $course_id Post ID
     * @param array $meta_data Associative array of meta key => value
     * @return bool Success
     */
    public function update_course_meta($course_id, $meta_data) {
        // Set content type as course
        update_post_meta($course_id, 'content_type', 'course');

        // Map user-friendly keys to actual meta keys
        $meta_mapping = [
            'course_provider' => 'sffc_company',
            'learning_mode' => 'sffc_location',
            'description' => 'sffc_description',
            'prerequisites' => 'sffc_requirements',
            'skills_taught' => 'sffc_skills',
            'category' => 'course_category',
            'difficulty_level' => 'difficulty_level',
            'estimated_hours' => 'estimated_hours',
            'total_lessons' => 'total_lessons',
            'total_quizzes' => 'total_quizzes',
            'has_certificate' => 'has_certificate',
            'has_ai_tutor' => 'has_ai_tutor',
            'modules' => 'course_modules',
            'instructor_name' => 'instructor_name',
            'instructor_bio' => 'instructor_bio',
            'certification_type' => 'certification_type',
            'price' => 'course_price',
            'learning_outcomes' => 'learning_outcomes'
        ];

        foreach ($meta_data as $key => $value) {
            if (isset($meta_mapping[$key])) {
                $meta_key = $meta_mapping[$key];

                // JSON encode arrays
                if (in_array($key, ['modules', 'learning_outcomes', 'skills_taught']) && is_array($value)) {
                    $value = json_encode($value);
                }

                update_post_meta($course_id, $meta_key, $value);
            }
        }

        return true;
    }

    /**
     * Create a new course
     *
     * @param string $title Course title
     * @param array $meta_data Course metadata
     * @return int|WP_Error Course ID or error
     */
    public function create_course($title, $meta_data = []) {
        $course_id = wp_insert_post([
            'post_title' => $title,
            'post_type' => 'sffc_job',
            'post_status' => 'publish',
            'post_content' => isset($meta_data['description']) ? $meta_data['description'] : ''
        ]);

        if (is_wp_error($course_id)) {
            return $course_id;
        }

        // Set as course
        update_post_meta($course_id, 'content_type', 'course');

        // Update all meta fields
        if (!empty($meta_data)) {
            $this->update_course_meta($course_id, $meta_data);
        }

        return $course_id;
    }

    /**
     * Check if a post is a course (vs a job)
     *
     * @param int $post_id Post ID
     * @return bool
     */
    public function is_course($post_id) {
        return get_post_meta($post_id, 'content_type', true) === 'course';
    }

    /**
     * Get all courses
     *
     * @param array $args WP_Query arguments
     * @return WP_Query
     */
    public function get_courses($args = []) {
        $defaults = [
            'post_type' => 'sffc_job',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'content_type',
                    'value' => 'course',
                    'compare' => '='
                ]
            ]
        ];

        $args = wp_parse_args($args, $defaults);

        return new WP_Query($args);
    }
}

// Initialize
SFFC_Course_Meta_Fields::get_instance();
