<?php
/**
 * Vendor Autoloader Wrapper
 * Safely includes Composer autoloader
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if vendor autoload exists
$vendor_autoload = dirname(dirname(__FILE__)) . '/vendor/autoload.php';

if (file_exists($vendor_autoload)) {
    require_once $vendor_autoload;
    
    // Define constant to indicate libraries are loaded
    if (!defined('SFFC_LIBRARIES_LOADED')) {
        define('SFFC_LIBRARIES_LOADED', true);
    }
} else {
    // Libraries not installed
    if (!defined('SFFC_LIBRARIES_LOADED')) {
        define('SFFC_LIBRARIES_LOADED', false);
    }
    
    // Admin notice if in admin area
    if (is_admin()) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-warning"><p>';
            echo 'SFFC Plugin: Required libraries not installed. ';
            echo 'Please run <code>bash install-libraries.sh</code> in the plugin directory.';
            echo '</p></div>';
        });
    }
}
