<?php
/**
 * PE Flush Rewrite Rules
 * Utility to flush rewrite rules and ensure PE post types are visible
 */

if (!defined('ABSPATH')) {
    exit;
}

// Flush rewrite rules to ensure custom post types are registered
flush_rewrite_rules();

// Check if PE Content Manager exists
if (class_exists('SFFC_PE_Content_Manager')) {
    $manager = SFFC_PE_Content_Manager::get_instance();
    echo '<div class="notice notice-success"><p>PE Content Manager loaded successfully.</p></div>';
} else {
    echo '<div class="notice notice-error"><p>PE Content Manager not found!</p></div>';
}

// Check registered post types
$post_types = get_post_types(array('public' => true), 'objects');
$pe_post_types = array('sffc_pe_news', 'sffc_pe_deal', 'sffc_market_intel');
$found = array();

echo '<h2>Registered PE Post Types:</h2>';
echo '<ul>';
foreach ($pe_post_types as $type) {
    if (isset($post_types[$type])) {
        echo '<li>✅ <strong>' . $type . '</strong> - Registered successfully</li>';
        $found[] = $type;
    } else {
        echo '<li>❌ <strong>' . $type . '</strong> - NOT registered</li>';
    }
}
echo '</ul>';

// If all post types are found, show links
if (count($found) === count($pe_post_types)) {
    echo '<h3>Quick Links to PE Content:</h3>';
    echo '<ul>';
    echo '<li><a href="' . admin_url('edit.php?post_type=sffc_pe_news') . '">View PE News</a></li>';
    echo '<li><a href="' . admin_url('edit.php?post_type=sffc_pe_deal') . '">View PE Deals</a></li>';
    echo '<li><a href="' . admin_url('edit.php?post_type=sffc_market_intel') . '">View Market Intelligence</a></li>';
    echo '</ul>';
    
    echo '<div class="notice notice-success"><p>✅ All PE post types are registered! Rewrite rules have been flushed. You should now see them in the menu.</p></div>';
} else {
    echo '<div class="notice notice-warning">';
    echo '<p>Some post types are not registered. Try these steps:</p>';
    echo '<ol>';
    echo '<li>Deactivate and reactivate the plugin</li>';
    echo '<li>Go to Settings > Permalinks and click "Save Changes"</li>';
    echo '<li>Clear any caching plugins</li>';
    echo '</ol>';
    echo '</div>';
}

// Show current counts
echo '<h3>Current Content Count:</h3>';
echo '<table class="widefat">';
echo '<thead><tr><th>Post Type</th><th>Published</th><th>Draft</th><th>Total</th></tr></thead>';
echo '<tbody>';
foreach ($found as $type) {
    $counts = wp_count_posts($type);
    echo '<tr>';
    echo '<td>' . $type . '</td>';
    echo '<td>' . ($counts->publish ?? 0) . '</td>';
    echo '<td>' . ($counts->draft ?? 0) . '</td>';
    echo '<td>' . (($counts->publish ?? 0) + ($counts->draft ?? 0)) . '</td>';
    echo '</tr>';
}
echo '</tbody>';
echo '</table>';
?>