<?php
/**
 * Post Type Cleanup - One-time script to delete deprecated post types and their content
 *
 * IMPORTANT: Run this ONCE via admin, then delete this file
 *
 * @package SennaCareers
 * @since 11.15.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Post_Type_Cleanup {

    private static $instance = null;

    /**
     * Post types to be deleted
     */
    private $post_types_to_delete = array(
        'prep_case_study',
        'prep_interview_q',
        'prep_term',
        'prep_model_guide',
        'prep_day_in_life',
        'prep_deal',
        'prep_company',
        'prep_practice_question',
        'prep_model_template',
        'sffc_company',
        'sffc_research',
        'sffc_candidate_opp',
        'sffc_candidate_conv',
        'sffc_career_insights',
        'sffc_news_article',
        'sffc_salary_guide',
        'sffc_consultant',
    );

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Add admin menu for cleanup
        add_action('admin_menu', array($this, 'add_cleanup_menu'));

        // Handle cleanup action
        add_action('admin_init', array($this, 'handle_cleanup_action'));
    }

    /**
     * Add cleanup menu under Tools
     */
    public function add_cleanup_menu() {
        add_management_page(
            'MENA Careers Post Type Cleanup',
            'MENA Careers Cleanup',
            'manage_options',
            'sffc-post-type-cleanup',
            array($this, 'render_cleanup_page')
        );
    }

    /**
     * Render cleanup admin page
     */
    public function render_cleanup_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        // Get counts for each post type
        $counts = array();
        foreach ($this->post_types_to_delete as $post_type) {
            $count = wp_count_posts($post_type);
            $total = 0;
            if ($count) {
                foreach ($count as $status => $num) {
                    $total += (int) $num;
                }
            }
            if ($total > 0) {
                $counts[$post_type] = $total;
            }
        }

        ?>
        <div class="wrap">
            <h1>MENA Careers Post Type Cleanup</h1>

            <div class="notice notice-warning">
                <p><strong>Warning:</strong> This will permanently delete all posts for the selected post types. This action cannot be undone!</p>
            </div>

            <?php if (isset($_GET['cleaned']) && $_GET['cleaned'] === 'success'): ?>
            <div class="notice notice-success">
                <p>Cleanup completed successfully! <?php echo intval($_GET['deleted']); ?> posts were deleted.</p>
            </div>
            <?php endif; ?>

            <h2>Post Types to Clean Up</h2>

            <?php if (empty($counts)): ?>
            <p>No posts found for any of the deprecated post types. Nothing to clean up.</p>
            <?php else: ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Post Type</th>
                        <th>Post Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($counts as $post_type => $count): ?>
                    <tr>
                        <td><code><?php echo esc_html($post_type); ?></code></td>
                        <td><?php echo esc_html($count); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th>Total</th>
                        <th><?php echo array_sum($counts); ?></th>
                    </tr>
                </tfoot>
            </table>

            <form method="post" style="margin-top: 20px;">
                <?php wp_nonce_field('sffc_cleanup_posts', 'sffc_cleanup_nonce'); ?>
                <input type="hidden" name="sffc_cleanup_action" value="delete_all">

                <p>
                    <label>
                        <input type="checkbox" name="confirm_delete" value="1" required>
                        I understand this will permanently delete all <?php echo array_sum($counts); ?> posts
                    </label>
                </p>

                <p>
                    <button type="submit" class="button button-primary button-large" style="background: #dc3545; border-color: #dc3545;">
                        Delete All Posts (<?php echo array_sum($counts); ?> posts)
                    </button>
                </p>
            </form>
            <?php endif; ?>

            <hr style="margin: 30px 0;">

            <h2>All Post Types to Remove</h2>
            <p>These post types will be removed from the codebase:</p>
            <ul style="list-style: disc; margin-left: 20px;">
                <?php foreach ($this->post_types_to_delete as $pt): ?>
                <li><code><?php echo esc_html($pt); ?></code></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    /**
     * Handle cleanup action
     */
    public function handle_cleanup_action() {
        if (!isset($_POST['sffc_cleanup_action']) || $_POST['sffc_cleanup_action'] !== 'delete_all') {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        if (!wp_verify_nonce($_POST['sffc_cleanup_nonce'], 'sffc_cleanup_posts')) {
            wp_die('Security check failed');
        }

        if (!isset($_POST['confirm_delete']) || $_POST['confirm_delete'] !== '1') {
            wp_die('Please confirm the deletion');
        }

        $deleted_count = 0;

        foreach ($this->post_types_to_delete as $post_type) {
            $deleted_count += $this->delete_all_posts_of_type($post_type);
        }

        // Also clean up any orphaned post meta
        $this->cleanup_orphaned_meta();

        // Redirect with success message
        wp_redirect(admin_url('tools.php?page=sffc-post-type-cleanup&cleaned=success&deleted=' . $deleted_count));
        exit;
    }

    /**
     * Delete all posts of a specific type
     */
    private function delete_all_posts_of_type($post_type) {
        global $wpdb;

        $deleted = 0;

        // Get all posts of this type
        $posts = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
            $post_type
        ));

        if (empty($posts)) {
            return 0;
        }

        foreach ($posts as $post_id) {
            // Delete post meta first
            $wpdb->delete($wpdb->postmeta, array('post_id' => $post_id));

            // Delete term relationships
            $wpdb->delete($wpdb->term_relationships, array('object_id' => $post_id));

            // Delete the post (force delete, skip trash)
            if (wp_delete_post($post_id, true)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Clean up orphaned post meta
     */
    private function cleanup_orphaned_meta() {
        global $wpdb;

        // Delete meta for posts that no longer exist
        $wpdb->query("
            DELETE pm FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.ID IS NULL
        ");
    }
}

// Initialize
SFFC_Post_Type_Cleanup::get_instance();
