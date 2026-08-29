<?php
/**
 * Lesson Content Manager
 * Handles lesson content storage, retrieval, and rendering
 *
 * @package SennaCareers
 * @subpackage Learning
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Lesson_Content {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Hook to add rewrite rules for course viewer
        add_action('init', [$this, 'add_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'course_viewer_template']);
    }

    /**
     * Add custom rewrite rules for course viewer
     */
    public function add_rewrite_rules() {
        // URL structure: /course/{course_slug}/module/{module_id}/lesson/{lesson_id}
        add_rewrite_rule(
            '^course/([^/]+)/module/([0-9]+)/lesson/([0-9]+)/?$',
            'index.php?course_slug=$matches[1]&module_id=$matches[2]&lesson_id=$matches[3]',
            'top'
        );

        // URL structure: /course/{course_slug}
        add_rewrite_rule(
            '^course/([^/]+)/?$',
            'index.php?course_slug=$matches[1]',
            'top'
        );
    }

    /**
     * Add custom query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'course_slug';
        $vars[] = 'module_id';
        $vars[] = 'lesson_id';
        return $vars;
    }

    /**
     * Handle course viewer template
     */
    public function course_viewer_template() {
        $course_slug = get_query_var('course_slug');

        if (!$course_slug) {
            return;
        }

        // Find course by slug
        $args = [
            'post_type' => 'sffc_job',
            'name' => $course_slug,
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => 'content_type',
                    'value' => 'course',
                    'compare' => '='
                ]
            ]
        ];

        $query = new WP_Query($args);

        if (!$query->have_posts()) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }

        // Load course viewer template
        $query->the_post();
        $this->render_course_viewer();
        exit;
    }

    /**
     * Render course viewer page
     */
    private function render_course_viewer() {
        $course_id = get_the_ID();
        $module_id = get_query_var('module_id');
        $lesson_id = get_query_var('lesson_id');

        // Get course metadata
        $course_meta = SFFC_Course_Meta_Fields::get_instance()->get_course_meta($course_id);
        $modules = $course_meta['modules'] ?? [];
        $modules = is_array($modules) ? $modules : [];

        // Get user progress
        $user_id = get_current_user_id();
        $progress_data = $this->get_user_course_progress($user_id, $course_id);

        // Determine what to show
        if ($module_id && $lesson_id) {
            // Show specific lesson
            $lesson_content = $this->get_lesson_content($course_id, $module_id, $lesson_id);
            $this->render_lesson_viewer($course_id, $course_meta, $modules, $module_id, $lesson_id, $lesson_content, $progress_data);
        } else {
            // Show course overview
            $this->render_course_overview($course_id, $course_meta, $modules, $progress_data);
        }
    }

    /**
     * Get lesson content
     */
    public function get_lesson_content($course_id, $module_id, $lesson_id) {
        // Get all lessons for this course
        $lessons = get_post_meta($course_id, 'lesson_content', true);
        $lessons = is_array($lessons) ? $lessons : [];

        // Find specific lesson
        $lesson_key = "module_{$module_id}_lesson_{$lesson_id}";

        if (isset($lessons[$lesson_key])) {
            return $lessons[$lesson_key];
        }

        // Return default AI-generated content structure
        return $this->generate_ai_lesson_content($course_id, $module_id, $lesson_id);
    }

    /**
     * Generate AI lesson content (Claude AI)
     * This will be called when lesson content doesn't exist yet
     */
    private function generate_ai_lesson_content($course_id, $module_id, $lesson_id) {
        $modules = get_post_meta($course_id, 'modules', true);
        $modules = is_array($modules) ? $modules : [];

        $module = null;
        foreach ($modules as $m) {
            if ($m['module_id'] == $module_id) {
                $module = $m;
                break;
            }
        }

        if (!$module) {
            return [
                'title' => 'Lesson Not Found',
                'content' => 'This lesson content is being generated.',
                'type' => 'text'
            ];
        }

        $topics = $module['topics'] ?? [];
        $topic_index = $lesson_id - 1;
        $topic = isset($topics[$topic_index]) ? $topics[$topic_index] : "Lesson $lesson_id";

        // Default content structure for AI to populate
        return [
            'lesson_id' => $lesson_id,
            'module_id' => $module_id,
            'title' => $topic,
            'duration' => '10 min',
            'type' => 'video_and_text', // Options: text, video, video_and_text, interactive
            'video_url' => '', // To be populated with video URL
            'content' => $this->get_placeholder_content($topic),
            'code_examples' => [],
            'key_takeaways' => [
                "Understanding {$topic}",
                "Practical applications",
                "Common pitfalls to avoid"
            ],
            'resources' => [],
            'quiz_id' => null,
            'completed' => false
        ];
    }

    /**
     * Get placeholder content for lessons
     */
    private function get_placeholder_content($topic) {
        return "<h2>{$topic}</h2>

<p>This lesson covers the fundamentals of {$topic} in financial modeling and analysis.</p>

<h3>Learning Objectives</h3>
<ul>
    <li>Understand the core concepts of {$topic}</li>
    <li>Learn practical applications in real-world scenarios</li>
    <li>Master industry-standard techniques</li>
</ul>

<h3>Key Concepts</h3>
<p>In this comprehensive lesson, you'll explore how {$topic} fits into the broader context of financial analysis. We'll break down complex concepts into digestible pieces, ensuring you have a solid foundation before moving to advanced applications.</p>

<div class='lesson-note'>
    <strong>💡 Pro Tip:</strong> This content will be enhanced with AI-generated explanations, examples, and interactive elements. For now, use this as a framework to understand the lesson structure.
</div>

<h3>Practical Application</h3>
<p>Let's examine how {$topic} is used in real investment banking and private equity scenarios. You'll see step-by-step walkthroughs of common calculations and modeling techniques.</p>";
    }

    /**
     * Get user progress for a course
     */
    private function get_user_course_progress($user_id, $course_id) {
        if (!$user_id) {
            return [
                'enrolled' => false,
                'progress_percentage' => 0,
                'completed_lessons' => [],
                'last_lesson' => null
            ];
        }

        global $wpdb;

        // Get progress record
        $progress = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sffc_learning_progress
             WHERE user_id = %d AND course_id = %d
             ORDER BY id DESC LIMIT 1",
            $user_id,
            $course_id
        ));

        if (!$progress) {
            return [
                'enrolled' => false,
                'progress_percentage' => 0,
                'completed_lessons' => [],
                'last_lesson' => null
            ];
        }

        // Get completed lessons
        $completed_lessons = get_user_meta($user_id, "course_{$course_id}_completed_lessons", true);
        $completed_lessons = is_array($completed_lessons) ? $completed_lessons : [];

        return [
            'enrolled' => true,
            'progress_percentage' => $progress->progress_percentage,
            'completed_lessons' => $completed_lessons,
            'last_lesson' => [
                'module_id' => $progress->module_id,
                'lesson_id' => $progress->lesson_id
            ],
            'time_spent' => $progress->time_spent_seconds,
            'last_accessed' => $progress->last_accessed
        ];
    }

    /**
     * Render course overview page
     */
    private function render_course_overview($course_id, $course_meta, $modules, $progress_data) {
        get_header();
        ?>
        <div class="sffc-course-viewer-wrapper">
            <div class="sffc-course-overview">
                <!-- Course Header -->
                <header class="sffc-course-header">
                    <div class="sffc-course-header-content">
                        <div class="sffc-course-breadcrumb">
                            <a href="<?php echo esc_url(home_url('/crm/?tab=learning')); ?>">Learning</a>
                            <span>/</span>
                            <span><?php echo esc_html($course_meta['category']); ?></span>
                        </div>
                        <h1 class="sffc-course-title"><?php the_title(); ?></h1>
                        <p class="sffc-course-subtitle"><?php echo esc_html($course_meta['description']); ?></p>

                        <div class="sffc-course-meta-bar">
                            <span class="sffc-course-meta-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                <?php echo esc_html($course_meta['estimated_hours']); ?> hours
                            </span>
                            <span class="sffc-course-meta-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                                </svg>
                                <?php echo esc_html($course_meta['total_lessons']); ?> lessons
                            </span>
                            <span class="sffc-course-meta-item">
                                <span class="sffc-crm-difficulty-badge <?php echo esc_attr(strtolower($course_meta['difficulty_level'])); ?>">
                                    <?php echo esc_html($course_meta['difficulty_level']); ?>
                                </span>
                            </span>
                        </div>

                        <?php if ($progress_data['enrolled']): ?>
                        <div class="sffc-course-progress-bar">
                            <div class="sffc-progress-bar-wrapper">
                                <div class="sffc-progress-bar">
                                    <div class="sffc-progress-fill" style="width: <?php echo esc_attr($progress_data['progress_percentage']); ?>%"></div>
                                </div>
                                <span class="sffc-progress-text"><?php echo esc_html(round($progress_data['progress_percentage'])); ?>% complete</span>
                            </div>
                            <?php if ($progress_data['last_lesson']['module_id']): ?>
                            <a href="<?php echo esc_url($this->get_lesson_url($course_id, $progress_data['last_lesson']['module_id'], $progress_data['last_lesson']['lesson_id'])); ?>" class="sffc-btn sffc-btn-primary">
                                Continue Learning
                            </a>
                            <?php else: ?>
                            <a href="<?php echo esc_url($this->get_lesson_url($course_id, 1, 1)); ?>" class="sffc-btn sffc-btn-primary">
                                Start Course
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <button class="sffc-btn sffc-btn-primary sffc-enroll-course-btn" data-course-id="<?php echo esc_attr($course_id); ?>">
                            Enroll Now - Free
                        </button>
                        <?php endif; ?>
                    </div>
                </header>

                <!-- Course Curriculum -->
                <section class="sffc-course-curriculum">
                    <h2>Course Curriculum</h2>

                    <?php foreach ($modules as $module): ?>
                    <div class="sffc-module-card">
                        <div class="sffc-module-header">
                            <h3>
                                <span class="sffc-module-number">Module <?php echo esc_html($module['module_id']); ?></span>
                                <?php echo esc_html($module['title']); ?>
                            </h3>
                            <span class="sffc-module-lessons"><?php echo esc_html($module['lessons']); ?> lessons</span>
                        </div>

                        <ul class="sffc-lesson-list">
                            <?php
                            $topics = $module['topics'] ?? [];
                            for ($i = 0; $i < $module['lessons']; $i++):
                                $lesson_id = $i + 1;
                                $topic = isset($topics[$i]) ? $topics[$i] : "Lesson $lesson_id";
                                $lesson_key = "module_{$module['module_id']}_lesson_{$lesson_id}";
                                $is_completed = in_array($lesson_key, $progress_data['completed_lessons']);
                                $is_accessible = $progress_data['enrolled'] || $lesson_id === 1; // First lesson is preview
                            ?>
                            <li class="sffc-lesson-item <?php echo $is_completed ? 'completed' : ''; ?>">
                                <?php if ($is_accessible): ?>
                                <a href="<?php echo esc_url($this->get_lesson_url($course_id, $module['module_id'], $lesson_id)); ?>" class="sffc-lesson-link">
                                    <span class="sffc-lesson-icon">
                                        <?php if ($is_completed): ?>
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                        <?php else: ?>
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polygon points="5 3 19 12 5 21 5 3"></polygon>
                                        </svg>
                                        <?php endif; ?>
                                    </span>
                                    <span class="sffc-lesson-title"><?php echo esc_html($topic); ?></span>
                                    <?php if ($lesson_id === 1 && !$progress_data['enrolled']): ?>
                                    <span class="sffc-lesson-badge">Preview</span>
                                    <?php endif; ?>
                                </a>
                                <?php else: ?>
                                <span class="sffc-lesson-link locked">
                                    <span class="sffc-lesson-icon">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                        </svg>
                                    </span>
                                    <span class="sffc-lesson-title"><?php echo esc_html($topic); ?></span>
                                    <span class="sffc-lesson-badge">Locked</span>
                                </span>
                                <?php endif; ?>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </div>
                    <?php endforeach; ?>
                </section>

                <!-- Learning Outcomes -->
                <?php if (!empty($course_meta['learning_outcomes'])): ?>
                <section class="sffc-course-outcomes">
                    <h2>What You'll Learn</h2>
                    <ul class="sffc-outcomes-list">
                        <?php foreach ($course_meta['learning_outcomes'] as $outcome): ?>
                        <li>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            <?php echo esc_html($outcome); ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>
            </div>
        </div>
        <?php
        get_footer();
    }

    /**
     * Render lesson viewer
     */
    private function render_lesson_viewer($course_id, $course_meta, $modules, $module_id, $lesson_id, $lesson_content, $progress_data) {
        get_header();

        // Calculate previous/next lessons
        $nav = $this->get_lesson_navigation($course_id, $modules, $module_id, $lesson_id);
        $lesson_key = "module_{$module_id}_lesson_{$lesson_id}";
        $is_completed = in_array($lesson_key, $progress_data['completed_lessons']);

        ?>
        <div class="sffc-lesson-viewer-wrapper">
            <!-- Lesson Sidebar -->
            <aside class="sffc-lesson-sidebar">
                <div class="sffc-lesson-sidebar-header">
                    <a href="<?php echo esc_url($this->get_course_url($course_id)); ?>" class="sffc-back-to-course">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                        <?php echo esc_html(get_the_title($course_id)); ?>
                    </a>
                </div>

                <div class="sffc-lesson-progress-overview">
                    <div class="sffc-progress-circle">
                        <svg width="60" height="60">
                            <circle cx="30" cy="30" r="26" fill="none" stroke="#E5E7EB" stroke-width="4"></circle>
                            <circle cx="30" cy="30" r="26" fill="none" stroke="#2F5233" stroke-width="4"
                                    stroke-dasharray="<?php echo (163 * $progress_data['progress_percentage'] / 100); ?> 163"
                                    transform="rotate(-90 30 30)"></circle>
                            <text x="30" y="35" text-anchor="middle" font-size="14" font-weight="bold" fill="#2F5233">
                                <?php echo esc_html(round($progress_data['progress_percentage'])); ?>%
                            </text>
                        </svg>
                    </div>
                    <p class="sffc-progress-label">Course Progress</p>
                </div>

                <nav class="sffc-lesson-nav">
                    <?php foreach ($modules as $module): ?>
                        <div class="sffc-module-nav-item <?php echo $module['module_id'] == $module_id ? 'active' : ''; ?>">
                            <div class="sffc-module-nav-header">
                                <span class="sffc-module-nav-number">Module <?php echo esc_html($module['module_id']); ?></span>
                                <span class="sffc-module-nav-title"><?php echo esc_html($module['title']); ?></span>
                            </div>
                            <ul class="sffc-lesson-nav-list">
                                <?php
                                $topics = $module['topics'] ?? [];
                                for ($i = 0; $i < $module['lessons']; $i++):
                                    $l_id = $i + 1;
                                    $topic = isset($topics[$i]) ? $topics[$i] : "Lesson $l_id";
                                    $l_key = "module_{$module['module_id']}_lesson_{$l_id}";
                                    $is_current = ($module['module_id'] == $module_id && $l_id == $lesson_id);
                                    $is_done = in_array($l_key, $progress_data['completed_lessons']);
                                    $is_accessible = $progress_data['enrolled'];
                                ?>
                                <li class="sffc-lesson-nav-item <?php echo $is_current ? 'current' : ''; ?> <?php echo $is_done ? 'completed' : ''; ?>">
                                    <?php if ($is_accessible): ?>
                                    <a href="<?php echo esc_url($this->get_lesson_url($course_id, $module['module_id'], $l_id)); ?>">
                                        <span class="sffc-lesson-nav-icon">
                                            <?php if ($is_done): ?>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg>
                                            <?php else: ?>
                                            <span class="sffc-lesson-number"><?php echo $l_id; ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="sffc-lesson-nav-text"><?php echo esc_html($topic); ?></span>
                                    </a>
                                    <?php else: ?>
                                    <span class="sffc-lesson-nav-locked">
                                        <span class="sffc-lesson-nav-icon">🔒</span>
                                        <span class="sffc-lesson-nav-text"><?php echo esc_html($topic); ?></span>
                                    </span>
                                    <?php endif; ?>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </nav>
            </aside>

            <!-- Lesson Content -->
            <main class="sffc-lesson-main">
                <div class="sffc-lesson-content-wrapper">
                    <div class="sffc-lesson-header">
                        <div class="sffc-lesson-meta">
                            <span class="sffc-lesson-module">Module <?php echo esc_html($module_id); ?></span>
                            <span class="sffc-lesson-duration"><?php echo esc_html($lesson_content['duration'] ?? '15 min'); ?></span>
                        </div>
                        <h1 class="sffc-lesson-title"><?php echo esc_html($lesson_content['title']); ?></h1>
                    </div>

                    <div class="sffc-lesson-body">
                        <?php echo wp_kses_post($lesson_content['content']); ?>
                    </div>

                    <?php if (!empty($lesson_content['key_takeaways'])): ?>
                    <div class="sffc-lesson-takeaways">
                        <h3>Key Takeaways</h3>
                        <ul>
                            <?php foreach ($lesson_content['key_takeaways'] as $takeaway): ?>
                            <li><?php echo esc_html($takeaway); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- Lesson Actions -->
                    <div class="sffc-lesson-actions">
                        <?php if (!$is_completed): ?>
                        <button class="sffc-btn sffc-btn-primary sffc-mark-complete-btn"
                                data-course-id="<?php echo esc_attr($course_id); ?>"
                                data-module-id="<?php echo esc_attr($module_id); ?>"
                                data-lesson-id="<?php echo esc_attr($lesson_id); ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            Mark as Complete
                        </button>
                        <?php else: ?>
                        <div class="sffc-lesson-completed-badge">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            <span>Lesson Completed</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Lesson Navigation -->
                    <div class="sffc-lesson-navigation">
                        <?php if ($nav['previous']): ?>
                        <a href="<?php echo esc_url($nav['previous']['url']); ?>" class="sffc-lesson-nav-btn sffc-lesson-nav-prev">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="19" y1="12" x2="5" y2="12"></line>
                                <polyline points="12 19 5 12 12 5"></polyline>
                            </svg>
                            <span>
                                <small>Previous</small>
                                <strong><?php echo esc_html($nav['previous']['title']); ?></strong>
                            </span>
                        </a>
                        <?php endif; ?>

                        <?php if ($nav['next']): ?>
                        <a href="<?php echo esc_url($nav['next']['url']); ?>" class="sffc-lesson-nav-btn sffc-lesson-nav-next">
                            <span>
                                <small>Next</small>
                                <strong><?php echo esc_html($nav['next']['title']); ?></strong>
                            </span>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
        <?php
        get_footer();
    }

    /**
     * Get previous and next lesson URLs
     */
    private function get_lesson_navigation($course_id, $modules, $current_module_id, $current_lesson_id) {
        $all_lessons = [];

        // Build flat list of all lessons
        foreach ($modules as $module) {
            $topics = $module['topics'] ?? [];
            for ($i = 0; $i < $module['lessons']; $i++) {
                $all_lessons[] = [
                    'module_id' => $module['module_id'],
                    'lesson_id' => $i + 1,
                    'title' => isset($topics[$i]) ? $topics[$i] : "Lesson " . ($i + 1),
                    'url' => $this->get_lesson_url($course_id, $module['module_id'], $i + 1)
                ];
            }
        }

        // Find current lesson index
        $current_index = null;
        foreach ($all_lessons as $index => $lesson) {
            if ($lesson['module_id'] == $current_module_id && $lesson['lesson_id'] == $current_lesson_id) {
                $current_index = $index;
                break;
            }
        }

        return [
            'previous' => isset($all_lessons[$current_index - 1]) ? $all_lessons[$current_index - 1] : null,
            'next' => isset($all_lessons[$current_index + 1]) ? $all_lessons[$current_index + 1] : null,
        ];
    }

    /**
     * Get lesson URL
     */
    private function get_lesson_url($course_id, $module_id, $lesson_id) {
        $course_slug = get_post_field('post_name', $course_id);
        return home_url("/course/{$course_slug}/module/{$module_id}/lesson/{$lesson_id}");
    }

    /**
     * Get course URL
     */
    public function get_course_url($course_id) {
        $course_slug = get_post_field('post_name', $course_id);
        return home_url("/course/{$course_slug}");
    }
}

// Initialize
SFFC_Lesson_Content::get_instance();
