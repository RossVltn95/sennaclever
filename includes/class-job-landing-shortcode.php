<?php

/**
 * Job Landing Page Shortcode
 *
 * LinkedIn conversion-optimized landing page with accordion layout
 * Reuses existing CRM match card styling and data models
 *
 * @package Senna_Finance
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Job_Landing_Shortcode
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('init', [$this, 'register_shortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_shortcodes()
    {
        add_shortcode('sffc_job_landing', [$this, 'render_landing']);
    }

    public function enqueue_assets()
    {
        global $post;

        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'sffc_job_landing')) {
            return;
        }

        // Reuse existing CRM CSS (has all match card styles)
        wp_enqueue_style('sffc-crm-main', SFFC_PLUGIN_URL . 'assets/css/crm/crm-main.css', [], SFFC_VERSION);

        // Minimal additional CSS for accordion behavior
        wp_enqueue_style('sffc-job-landing', SFFC_PLUGIN_URL . 'assets/css/job-landing.css', ['sffc-crm-main'], SFFC_VERSION);

        // Accordion JavaScript
        wp_enqueue_script('sffc-job-landing', SFFC_PLUGIN_URL . 'assets/js/job-landing.js', ['jquery'], SFFC_VERSION, true);

        // Localize for JavaScript
        wp_localize_script('sffc-job-landing', 'sffcJobLanding', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_job_landing_nonce'),
            'isLoggedIn' => is_user_logged_in(),
            'userId' => get_current_user_id(),
            'crmUrl' => home_url('/terminal/')
        ]);
    }

    public function render_landing($atts = [])
    {
        $atts = shortcode_atts([
            'role_id' => 0,
            'post_id' => 0, // Support WordPress post ID (sffc_recruiter_post)
            'sector' => '',
            'location' => '',
            'seniority' => '',
            'limit' => 20
        ], $atts);

        // Priority: role_id (CRM table) > post_id (WordPress) > current post
        $role_id = intval($atts['role_id']);

        if (!$role_id && !empty($atts['post_id'])) {
            // Get CRM post ID from WordPress post meta
            $role_id = $this->get_crm_post_id_from_wp_post(intval($atts['post_id']));
        }

        if (!$role_id) {
            // Auto-detect from current post if on sffc_recruiter_post page
            global $post;
            if (is_singular('sffc_recruiter_post') && $post) {
                $role_id = $this->get_crm_post_id_from_wp_post($post->ID);
            }
        }

        // Get featured role
        $featured_role = $this->get_featured_role($role_id);
        if (!$featured_role) {
            return '<div class="sffc-job-landing-error">Role not found</div>';
        }

        // Get similar roles
        $similar_roles = $this->get_similar_roles($featured_role, $atts);

        // Combine: featured + similar
        $all_roles = array_merge([$featured_role], $similar_roles);

        // Generate blurred scores for logged-out users
        $is_logged_in = is_user_logged_in();
        if (!$is_logged_in) {
            $all_roles = $this->add_blurred_scores($all_roles);
        }

        ob_start();
        $this->render_landing_html($all_roles, $featured_role, $is_logged_in);
        return ob_get_clean();
    }

    private function get_featured_role($role_id)
    {
        if (!class_exists('SFFC_CRM_Post')) {
            return null;
        }

        $crm_post = new SFFC_CRM_Post();
        $role = $crm_post->get($role_id);

        return $role;
    }

    private function get_similar_roles($featured_role, $atts)
    {
        if (!class_exists('SFFC_CRM_Post')) {
            return [];
        }

        $crm_post = new SFFC_CRM_Post();

        // Build query args from featured role attributes
        $args = [
            'sector' => !empty($atts['sector']) ? $atts['sector'] : $featured_role['sector'],
            'location_country' => !empty($atts['location']) ? $atts['location'] : $featured_role['location_country'],
            'seniority' => !empty($atts['seniority']) ? $atts['seniority'] : $featured_role['seniority'],
            'per_page' => intval($atts['limit']),
            'page' => 1,
            'approved_only' => true,
            'orderby' => 'posted_at',
            'order' => 'DESC'
        ];

        $results = $crm_post->get_feed($args);

        // Exclude featured role from results
        if (!empty($results)) {
            $results = array_filter($results, function ($role) use ($featured_role) {
                return $role['id'] !== $featured_role['id'];
            });

            // Re-index array
            $results = array_values($results);
        }

        return $results;
    }

    private function add_blurred_scores($roles)
    {
        foreach ($roles as &$role) {
            // Generate random score: 65-92%
            $role['match_score'] = rand(65, 92);

            // Add blur flag
            $role['score_blurred'] = true;
        }

        return $roles;
    }

    private function render_landing_html($roles, $featured_role, $is_logged_in)
    {
?>
        <div class="sffc-job-landing-container">

            <!-- Dummy Filters -->
            <div class="sffc-job-landing-filters">
                <button class="sffc-job-landing-filter" data-filter="sector">
                    <span>Sector: <?php echo esc_html(ucwords(str_replace('-', ' ', $featured_role['sector'] ?? 'All'))); ?></span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
                <button class="sffc-job-landing-filter" data-filter="location">
                    <span>Location: <?php echo esc_html($featured_role['location_country'] ?? 'All'); ?></span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
                <button class="sffc-job-landing-filter" data-filter="seniority">
                    <span>Level: <?php echo esc_html(ucwords(str_replace('_', ' ', $featured_role['seniority'] ?? 'All'))); ?></span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
            </div>

            <!-- Accordion Rows -->
            <div class="sffc-job-landing-rows">
                <?php foreach ($roles as $index => $role): ?>
                    <?php $this->render_accordion_row($role, $index, $is_logged_in); ?>
                <?php endforeach; ?>
            </div>

            <?php if (!$is_logged_in): ?>
                <!-- Signup CTA Footer -->
                <div class="sffc-job-landing-footer">
                    <div class="sffc-job-landing-signup-prompt">
                        <h3>Unlock All Match Scores</h3>
                        <p>Upload your CV to see your real match percentage for <?php echo count($roles); ?> roles</p>
                        <button class="sffc-job-landing-btn-primary sffc-job-landing-signup-trigger">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                            Upload CV & Sign Up
                        </button>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    <?php
    }

    private function render_accordion_row($role, $index, $is_logged_in)
    {
        $is_first = ($index === 0);
        $is_blurred = !$is_logged_in;
        $avatar_blurred = !$is_logged_in && $index > 0; // Blur avatars after first

        $match_score = isset($role['match_score']) ? floatval($role['match_score']) : 0;
        $match_color = $this->get_match_color($match_score);

        $recruiter_name = $role['recruiter_name'] ?? 'Recruiter';
        $recruiter_firm = $role['recruiter_firm'] ?? $role['recruiter_display_company'] ?? '';
        $recruiter_photo = $role['recruiter_photo'] ?? '';
        $recruiter_initial = substr($recruiter_name, 0, 1);

        // Calculate circle position for score badge
        $angle = ($match_score / 100) * 360;
        $radians = ($angle - 90) * (M_PI / 180);
        $radius = 35;
        $scoreX = 40 + ($radius * cos($radians));
        $scoreY = 40 + ($radius * sin($radians));

        $expanded_class = $is_first ? ' sffc-job-landing-row--expanded' : '';
        $blur_class = $is_blurred ? ' sffc-job-landing-row--blurred' : '';
    ?>

        <div class="sffc-job-landing-row<?php echo $expanded_class . $blur_class; ?>" data-role-id="<?php echo esc_attr($role['id']); ?>">

            <!-- Row Header (Always Visible) -->
            <div class="sffc-job-landing-header">

                <!-- Match Indicator with Avatar -->
                <div class="sffc-crm-match-indicator">
                    <div class="sffc-crm-match-circle-container">
                        <svg class="sffc-crm-match-circle" width="80" height="80" viewBox="0 0 80 80">
                            <circle class="sffc-crm-match-circle-bg" cx="40" cy="40" r="35" fill="none" stroke="#e5e7eb" stroke-width="5"></circle>
                            <circle class="sffc-crm-match-circle-fg" cx="40" cy="40" r="35" fill="none" stroke="<?php echo esc_attr($match_color); ?>" stroke-width="5" stroke-dasharray="<?php echo esc_attr($match_score * 2.199); ?> 219.91" stroke-linecap="round" transform="rotate(-90 40 40)"></circle>
                        </svg>

                        <!-- Recruiter Avatar -->
                        <div class="sffc-crm-match-avatar<?php echo $avatar_blurred ? ' sffc-job-landing-avatar--blurred' : ''; ?>">
                            <?php if ($recruiter_photo): ?>
                                <img src="<?php echo esc_url($recruiter_photo); ?>" alt="<?php echo esc_attr($recruiter_name); ?>" data-initial="<?php echo esc_attr($recruiter_initial); ?>">
                            <?php else: ?>
                                <div class="sffc-crm-avatar-placeholder"><?php echo esc_html($recruiter_initial); ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Score Badge -->
                        <div class="sffc-crm-match-score<?php echo $is_blurred ? ' sffc-job-landing-score--blurred' : ''; ?>" style="left: <?php echo $scoreX; ?>px; top: <?php echo $scoreY; ?>px; color: <?php echo esc_attr($match_color); ?>; border-color: <?php echo esc_attr($match_color); ?>;">
                            <?php echo intval($match_score); ?>%
                        </div>
                    </div>

                    <div class="sffc-crm-match-recruiter-name"><?php echo esc_html($recruiter_name); ?></div>
                </div>

                <!-- Content -->
                <div class="sffc-crm-match-content">
                    <div class="sffc-crm-match-header">
                        <h4 class="sffc-crm-match-title"><?php echo esc_html($role['role_title'] ?? 'Untitled'); ?></h4>
                        <?php
                        $meta_parts = [];
                        if (!empty($role['company'])) $meta_parts[] = esc_html($role['company']);
                        if (!empty($role['location'])) $meta_parts[] = esc_html($role['location']);
                        if (!empty($meta_parts)):
                        ?>
                            <div class="sffc-crm-match-meta"><?php echo implode(' • ', $meta_parts); ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Chevron -->
                    <div class="sffc-job-landing-chevron">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Expanded Content -->
            <div class="sffc-job-landing-content">
                <div class="sffc-job-landing-expanded-inner">

                    <!-- Job Description -->
                    <?php if (!empty($role['content'])): ?>
                        <div class="sffc-job-landing-description">
                            <h4>Job Description</h4>
                            <div class="sffc-job-landing-jd-text">
                                <?php echo wp_kses_post(nl2br($role['content'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Match Reasons (generic for logged-out) -->
                    <?php if (!empty($role['requirements']) || !empty($role['skills_mentioned'])): ?>
                        <div class="sffc-crm-match-reasons">
                            <?php
                            // Show generic match reasons based on JD
                            $reasons = [];
                            if (!empty($role['experience_years'])) {
                                $reasons[] = $role['experience_years'] . ' experience required';
                            }
                            if (!empty($role['sector'])) {
                                $reasons[] = ucwords(str_replace('-', ' ', $role['sector'])) . ' background';
                            }
                            if (!empty($role['seniority'])) {
                                $reasons[] = ucwords(str_replace('_', ' ', $role['seniority'])) . ' level';
                            }

                            foreach (array_slice($reasons, 0, 3) as $reason):
                            ?>
                                <li>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                    <span><?php echo esc_html($reason); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- CTA -->
                    <div class="sffc-job-landing-cta">
                        <?php if (!$is_logged_in): ?>
                            <button class="sffc-crm-btn sffc-crm-btn-primary sffc-job-landing-signup-trigger">
                                Upload CV to See Match Score
                            </button>
                        <?php else: ?>
                            <button class="sffc-crm-btn sffc-crm-btn-primary" onclick="window.location.href='<?php echo esc_url(home_url('/crm/?role=' . $role['id'])); ?>'">
                                View in CRM Dashboard
                            </button>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

        </div>
<?php
    }

    private function get_crm_post_id_from_wp_post($wp_post_id)
    {
        if (!$wp_post_id) {
            return 0;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sffc_crm_posts';

        // Find CRM post by WordPress post ID
        $crm_post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE wp_post_id = %d AND is_active = 1 LIMIT 1",
            $wp_post_id
        ));

        return intval($crm_post_id);
    }

    private function get_match_color($score)
    {
        if ($score >= 80) return '#16a34a'; // Green
        if ($score >= 60) return '#f59e0b'; // Amber
        return '#dc2626'; // Red
    }

    private function escape_html($text)
    {
        return esc_html($text);
    }
}

// Initialize
add_action('plugins_loaded', function () {
    SFFC_Job_Landing_Shortcode::get_instance();
});
