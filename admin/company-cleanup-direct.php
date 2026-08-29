<?php
/**
 * Direct access file for Company Cleanup utility
 * 
 * Access this directly if menu isn't showing:
 * /wp-admin/admin.php?page=sffc-company-cleanup
 * 
 * Or from Tools menu if parent menu doesn't exist
 */

// Ensure this file is being accessed within WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Load the cleanup class if not already loaded
if (!class_exists('SFFC_Company_Taxonomy_Cleanup')) {
    require_once plugin_dir_path(dirname(__FILE__)) . 'admin/merge-duplicate-companies.php';
}

// Register direct access URL
add_action('admin_init', function() {
    // Register the page even if menu doesn't exist
    if (isset($_GET['page']) && $_GET['page'] === 'sffc-company-cleanup') {
        // Ensure user has permission
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Check if the class exists and render
        if (class_exists('SFFC_Company_Taxonomy_Cleanup')) {
            // The page will be rendered by the registered callback
            return;
        }
    }
});

// Alternative: Add to admin notices to show link if needed
add_action('admin_notices', function() {
    // Only show on plugins page or main dashboard
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->id, ['plugins', 'dashboard'])) {
        return;
    }
    
    // Check if there are duplicate companies
    $terms = get_terms([
        'taxonomy' => 'job_company',
        'hide_empty' => false,
        'fields' => 'names'
    ]);
    
    if (is_wp_error($terms) || empty($terms)) {
        return;
    }
    
    // Simple duplicate check (can be refined)
    $unique = array_unique(array_map('strtolower', $terms));
    if (count($unique) < count($terms)) {
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong>senna:</strong> Duplicate companies detected in job listings. 
                <a href="<?php echo admin_url('admin.php?page=sffc-company-cleanup'); ?>">Run Company Cleanup</a> |
                <a href="<?php echo admin_url('tools.php?page=sffc-company-cleanup'); ?>">Access via Tools</a>
            </p>
        </div>
        <?php
    }
});