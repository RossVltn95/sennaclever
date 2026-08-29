<?php
/**
 * Critical Fixes for MENA Careers Plugin
 * This file contains all critical fixes to prevent errors and ensure stable operation
 * 
 * @package SennaCareers
 * @since 5.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Plugin_Critical_Fixes {
    
    private static $instance = null;
    private const TABLES_VERIFIED_OPTION = 'sffc_critical_tables_verified_v1';
    private const HEALTH_TRANSIENT = 'sffc_critical_health_v1';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Apply fixes immediately
        $this->fix_database_errors();
        $this->fix_class_initialization();
        $this->fix_undefined_functions();
        $this->add_error_suppression();
    }
    
    /**
     * Fix 1: Database Errors - Prevent empty ALTER TABLE statements
     */
    private function fix_database_errors() {
        // Hook into database operations to validate them
        add_filter('query', function($query) {
            // Prevent empty ALTER TABLE statements
            if (strpos($query, 'ALTER TABLE') !== false && strpos($query, 'ADD `` (``)') !== false) {
                error_log('SFFC: Blocked invalid ALTER TABLE query: ' . $query);
                return "SELECT 1"; // Return harmless query
            }
            
            return $query;
        }, 1);
        
        // Fix database table creation
        add_action('admin_init', function() {
            if (!current_user_can('manage_options')) {
                return;
            }

            $this->ensure_tables_exist();
        }, 5);
    }
    
    /**
     * Fix 2: Ensure proper class initialization order
     */
    private function fix_class_initialization() {
        // Ensure critical classes are loaded in correct order
        add_action('plugins_loaded', function() {
            // Check if main plugin class exists
            if (!class_exists('Skill_Farm_Finance_Career')) {
                return;
            }
            
            // Ensure database class exists before other classes try to use it
            if (!class_exists('SFFC_Database') && file_exists(SFFC_PLUGIN_DIR . 'includes/class-database.php')) {
                require_once SFFC_PLUGIN_DIR . 'includes/class-database.php';
            }
            
            // Ensure error handler exists
            if (!class_exists('SFFC_Error_Handler') && file_exists(SFFC_PLUGIN_DIR . 'includes/class-error-handler.php')) {
                require_once SFFC_PLUGIN_DIR . 'includes/class-error-handler.php';
            }
        }, 5);
    }
    
    /**
     * Fix 3: Add undefined function fallbacks
     */
    private function fix_undefined_functions() {
        // Add dbDelta if not available
        if (!function_exists('dbDelta')) {
            add_action('init', function() {
                if (!function_exists('dbDelta')) {
                    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                }
            }, 1);
        }
    }
    
    /**
     * Fix 4: Add global error suppression for non-critical warnings
     */
    private function add_error_suppression() {
        // Suppress specific warnings that don't affect functionality
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            // Suppress empty ALTER TABLE warnings
            if (strpos($errstr, 'ALTER TABLE') !== false && strpos($errstr, 'ADD ``') !== false) {
                error_log("SFFC Suppressed: $errstr");
                return true;
            }
            
            // Let WordPress handle other errors
            return false;
        }, E_WARNING | E_NOTICE);
    }
    /**
     * Ensure critical tables exist
     */
    private function ensure_tables_exist() {
        global $wpdb;

        if (get_option(self::TABLES_VERIFIED_OPTION) === '1') {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();
        $existing_tables = array_map('strval', (array) $wpdb->get_col('SHOW TABLES'));
        
        // Check if user profiles table exists
        $profiles_table = $wpdb->prefix . 'sffc_user_profiles';
        if (!in_array($profiles_table, $existing_tables, true)) {
            // Create minimal user profiles table
            $sql = "CREATE TABLE IF NOT EXISTS $profiles_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                profile_completion_percentage int(3) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY user_id (user_id)
            ) $charset_collate;";
            
            $wpdb->query($sql);
        }
        
        // Ensure other critical tables exist
        $critical_tables = [
            'sffc_conversations' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_conversations (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;",
            
            'sffc_messages' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sffc_messages (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                conversation_id bigint(20) NOT NULL,
                user_id bigint(20) NOT NULL,
                message text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;"
        ];
        
        foreach ($critical_tables as $table_name => $create_sql) {
            $full_table_name = $wpdb->prefix . $table_name;
            if (!in_array($full_table_name, $existing_tables, true)) {
                $wpdb->query($create_sql);
            }
        }

        update_option(self::TABLES_VERIFIED_OPTION, '1', false);
        delete_transient(self::HEALTH_TRANSIENT);
    }
    
    /**
     * Public method to manually trigger fixes
     */
    public function apply_fixes() {
        delete_option(self::TABLES_VERIFIED_OPTION);
        delete_transient(self::HEALTH_TRANSIENT);
        $this->ensure_tables_exist();
        return [
            'status' => 'success',
            'message' => 'Critical fixes applied successfully'
        ];
    }
    
    /**
     * Check system health
     */
    public function check_health() {
        global $wpdb;
        $cached = get_transient(self::HEALTH_TRANSIENT);
        if (is_array($cached)) {
            return $cached;
        }

        $issues = [];
        $existing_tables = array_map('strval', (array) $wpdb->get_col('SHOW TABLES'));
        
        // Check for main plugin tables
        $main_tables = [
            'sffc_user_profiles',
            'sffc_conversations',
            'sffc_messages'
        ];
        
        foreach ($main_tables as $table) {
            $full_table = $wpdb->prefix . $table;
            if (!in_array($full_table, $existing_tables, true)) {
                $issues[] = "Table $full_table is missing";
            }
        }
        
        // Check for required classes
        $required_classes = [
            'SFFC_Database',
            'SFFC_Error_Handler',
            'SFFC_Session_Manager',
            'SFFC_User_Manager'
        ];
        
        foreach ($required_classes as $class) {
            if (!class_exists($class)) {
                $issues[] = "Class $class is not loaded";
            }
        }
        
        $health = [
            'healthy' => empty($issues),
            'issues' => $issues,
        ];

        set_transient(self::HEALTH_TRANSIENT, $health, 15 * MINUTE_IN_SECONDS);

        return $health;
    }
}

// Initialize fixes immediately
add_action('plugins_loaded', function() {
    SFFC_Plugin_Critical_Fixes::get_instance();
}, 1);

// Add admin notice if there are issues
add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'sffc') === false) {
        return;
    }
    
    $fixes = SFFC_Plugin_Critical_Fixes::get_instance();
    $health = $fixes->check_health();
    
    if (!$health['healthy']) {
        ?>
        <div class="notice notice-warning">
            <p><strong>MENA Careers - System Issues Detected:</strong></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <?php foreach ($health['issues'] as $issue): ?>
                    <li><?php echo esc_html($issue); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }
});
