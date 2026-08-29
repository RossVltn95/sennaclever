<?php
/**
 * Job Data Cleanup Script
 *
 * Phase 5: Database cleanup for sffc_job removal
 *
 * USAGE:
 * 1. Via WP-CLI: wp eval-file wp-content/plugins/senna-careers/admin/cleanup-job-data.php
 * 2. Via Admin: Navigate to MENA Careers > Job Cleanup in WordPress admin
 *
 * WARNING: This script permanently deletes job-related data.
 * Make a database backup before running!
 *
 * @package SennaCareers
 * @since 2025-12-24
 */

// Security check - must be run in WordPress context
if (!defined('ABSPATH')) {
    // Allow running via WP-CLI
    if (php_sapi_name() !== 'cli') {
        exit('This script must be run within WordPress.');
    }
}

/**
 * Job Data Cleanup Class
 */
class SFFC_Job_Data_Cleanup {

    /**
     * Job taxonomies to clean up
     */
    private $job_taxonomies = [
        'job_location',
        'job_industry',
        'job_level',
        'job_company',
        'job_type',
        'job_skills'
    ];

    /**
     * Custom tables to drop
     */
    private $custom_tables = [
        'sffc_job_queue',
        'sffc_jobs',
        'sffc_job_analysis',
        'sffc_job_applications',
        'sffc_saved_jobs',
        'sffc_job_preferences'
    ];

    /**
     * Run the cleanup
     */
    public function run($dry_run = true) {
        global $wpdb;

        $results = [
            'dry_run' => $dry_run,
            'jobs_count' => 0,
            'postmeta_deleted' => 0,
            'posts_deleted' => 0,
            'term_relationships_deleted' => 0,
            'term_taxonomy_deleted' => 0,
            'terms_deleted' => 0,
            'tables_dropped' => [],
            'usermeta_deleted' => 0,
            'options_deleted' => 0,
            'errors' => []
        ];

        // Step 1: Count and delete job posts
        $results['jobs_count'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'sffc_job'"
        );

        if (!$dry_run && $results['jobs_count'] > 0) {
            // Delete post meta first
            $results['postmeta_deleted'] = $wpdb->query(
                "DELETE pm FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE p.post_type = 'sffc_job'"
            );

            // Delete posts
            $results['posts_deleted'] = $wpdb->query(
                "DELETE FROM {$wpdb->posts} WHERE post_type = 'sffc_job'"
            );
        }

        // Step 2: Clean up taxonomy terms
        $taxonomy_list = "'" . implode("','", $this->job_taxonomies) . "'";

        // Count term relationships
        $term_rel_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->term_relationships} tr
             INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             WHERE tt.taxonomy IN ({$taxonomy_list})"
        );

        // Count term taxonomy entries
        $term_tax_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ({$taxonomy_list})"
        );

        if (!$dry_run) {
            // Delete term relationships
            $results['term_relationships_deleted'] = $wpdb->query(
                "DELETE tr FROM {$wpdb->term_relationships} tr
                 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                 WHERE tt.taxonomy IN ({$taxonomy_list})"
            );

            // Get term IDs before deleting term_taxonomy
            $term_ids = $wpdb->get_col(
                "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ({$taxonomy_list})"
            );

            // Delete term taxonomy entries
            $results['term_taxonomy_deleted'] = $wpdb->query(
                "DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ({$taxonomy_list})"
            );

            // Delete orphaned terms
            if (!empty($term_ids)) {
                $term_ids_list = implode(',', array_map('intval', $term_ids));
                $results['terms_deleted'] = $wpdb->query(
                    "DELETE FROM {$wpdb->terms} WHERE term_id IN ({$term_ids_list})"
                );
            }
        } else {
            $results['term_relationships_deleted'] = $term_rel_count;
            $results['term_taxonomy_deleted'] = $term_tax_count;
        }

        // Step 3: Drop custom tables
        foreach ($this->custom_tables as $table) {
            $full_table = $wpdb->prefix . $table;
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$full_table}'");

            if ($table_exists) {
                if (!$dry_run) {
                    $wpdb->query("DROP TABLE IF EXISTS {$full_table}");
                }
                $results['tables_dropped'][] = $full_table;
            }
        }

        // Step 4: Clean user meta
        $usermeta_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta}
             WHERE meta_key LIKE '%sffc_job%'
             OR meta_key LIKE '%saved_job%'
             OR meta_key LIKE '%job_preference%'"
        );

        if (!$dry_run && $usermeta_count > 0) {
            $results['usermeta_deleted'] = $wpdb->query(
                "DELETE FROM {$wpdb->usermeta}
                 WHERE meta_key LIKE '%sffc_job%'
                 OR meta_key LIKE '%saved_job%'
                 OR meta_key LIKE '%job_preference%'"
            );
        } else {
            $results['usermeta_deleted'] = $usermeta_count;
        }

        // Step 5: Clean options/transients
        $options_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options}
             WHERE option_name LIKE '%sffc_job%'
             OR option_name LIKE '%_transient_sffc_shared_jobs%'
             OR option_name LIKE '%_transient_timeout_sffc_shared_jobs%'"
        );

        if (!$dry_run && $options_count > 0) {
            $results['options_deleted'] = $wpdb->query(
                "DELETE FROM {$wpdb->options}
                 WHERE option_name LIKE '%sffc_job%'
                 OR option_name LIKE '%_transient_sffc_shared_jobs%'
                 OR option_name LIKE '%_transient_timeout_sffc_shared_jobs%'"
            );
        } else {
            $results['options_deleted'] = $options_count;
        }

        return $results;
    }

    /**
     * Display results
     */
    public function display_results($results) {
        $mode = $results['dry_run'] ? 'DRY RUN (no changes made)' : 'EXECUTED';

        echo "\n";
        echo "========================================\n";
        echo "  SFFC Job Data Cleanup - {$mode}\n";
        echo "========================================\n\n";

        echo "Jobs found: {$results['jobs_count']}\n";
        echo "Post meta to delete: {$results['postmeta_deleted']}\n";
        echo "Posts to delete: {$results['posts_deleted']}\n";
        echo "Term relationships to delete: {$results['term_relationships_deleted']}\n";
        echo "Term taxonomy entries to delete: {$results['term_taxonomy_deleted']}\n";
        echo "Terms to delete: {$results['terms_deleted']}\n";
        echo "User meta to delete: {$results['usermeta_deleted']}\n";
        echo "Options/transients to delete: {$results['options_deleted']}\n";

        if (!empty($results['tables_dropped'])) {
            echo "\nTables to drop:\n";
            foreach ($results['tables_dropped'] as $table) {
                echo "  - {$table}\n";
            }
        } else {
            echo "\nNo custom job tables found.\n";
        }

        if (!empty($results['errors'])) {
            echo "\nErrors:\n";
            foreach ($results['errors'] as $error) {
                echo "  - {$error}\n";
            }
        }

        echo "\n========================================\n";

        if ($results['dry_run']) {
            echo "This was a DRY RUN. No data was deleted.\n";
            echo "To execute cleanup, run with --execute flag.\n";
        } else {
            echo "Cleanup COMPLETE. Data has been deleted.\n";
        }
        echo "========================================\n\n";
    }
}

// Run if called directly via WP-CLI
if (defined('WP_CLI') && WP_CLI) {
    $cleanup = new SFFC_Job_Data_Cleanup();

    // Check for --execute flag
    $dry_run = !in_array('--execute', $GLOBALS['argv'] ?? []);

    $results = $cleanup->run($dry_run);
    $cleanup->display_results($results);

} elseif (defined('ABSPATH') && is_admin()) {
    // Register admin page
    add_action('admin_menu', function() {
        add_submenu_page(
            'sffc-dashboard',
            'Job Data Cleanup',
            'Job Cleanup',
            'manage_options',
            'sffc-job-cleanup',
            'sffc_render_job_cleanup_page'
        );
    }, 99);
}

/**
 * Render the admin cleanup page
 */
function sffc_render_job_cleanup_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }

    $cleanup = new SFFC_Job_Data_Cleanup();
    $executed = false;
    $results = null;

    // Handle form submission
    if (isset($_POST['sffc_cleanup_action']) && wp_verify_nonce($_POST['sffc_cleanup_nonce'], 'sffc_job_cleanup')) {
        $dry_run = $_POST['sffc_cleanup_action'] !== 'execute';
        $results = $cleanup->run($dry_run);
        $executed = !$dry_run;
    } else {
        // Default to dry run preview
        $results = $cleanup->run(true);
    }

    ?>
    <div class="wrap">
        <h1>Job Data Cleanup</h1>

        <div class="notice notice-warning">
            <p><strong>Warning:</strong> This will permanently delete all job-related data from the database. Make sure you have a backup before proceeding!</p>
        </div>

        <?php if ($executed): ?>
        <div class="notice notice-success">
            <p><strong>Cleanup completed successfully!</strong></p>
        </div>
        <?php endif; ?>

        <div class="card" style="max-width: 600px; padding: 20px;">
            <h2>Cleanup Preview</h2>

            <table class="widefat" style="margin-bottom: 20px;">
                <tbody>
                    <tr>
                        <td><strong>Job Posts</strong></td>
                        <td><?php echo intval($results['jobs_count']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Post Meta Entries</strong></td>
                        <td><?php echo intval($results['postmeta_deleted']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Term Relationships</strong></td>
                        <td><?php echo intval($results['term_relationships_deleted']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Term Taxonomy Entries</strong></td>
                        <td><?php echo intval($results['term_taxonomy_deleted']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>User Meta Entries</strong></td>
                        <td><?php echo intval($results['usermeta_deleted']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Options/Transients</strong></td>
                        <td><?php echo intval($results['options_deleted']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Custom Tables</strong></td>
                        <td><?php echo !empty($results['tables_dropped']) ? implode(', ', $results['tables_dropped']) : 'None found'; ?></td>
                    </tr>
                </tbody>
            </table>

            <?php if (!$executed): ?>
            <form method="post" onsubmit="return confirm('Are you sure you want to permanently delete all job data? This cannot be undone!');">
                <?php wp_nonce_field('sffc_job_cleanup', 'sffc_cleanup_nonce'); ?>
                <p>
                    <button type="submit" name="sffc_cleanup_action" value="preview" class="button">
                        Refresh Preview
                    </button>
                    <button type="submit" name="sffc_cleanup_action" value="execute" class="button button-primary" style="background: #dc3545; border-color: #dc3545;">
                        Execute Cleanup
                    </button>
                </p>
            </form>
            <?php else: ?>
            <p><em>Cleanup has been executed. Refresh the page to see updated counts.</em></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
