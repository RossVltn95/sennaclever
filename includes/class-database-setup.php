<?php
/**
 * Database Setup Class
 * Handles creation and updates of database tables
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Database_Setup {
    
    /**
     * Current database version
     */
    const DB_VERSION = '1.2.0';
    
    /**
     * Option name for storing DB version
     */
    const VERSION_OPTION = 'sffc_db_version';
    
    /**
     * Initialize database setup
     */
    public static function init() {
        // Check if we need to update database
        $current_version = get_option(self::VERSION_OPTION, '0');
        
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            self::create_tables();
            update_option(self::VERSION_OPTION, self::DB_VERSION);
        }
    }
    
    /**
     * Create all necessary tables
     */
    public static function create_tables() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create application leads table
        self::create_leads_table();
        
        // Create guest sessions table
        self::create_sessions_table();
    }
    
    /**
     * Create application leads table
     */
    private static function create_leads_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_application_leads';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(20),
            job_id bigint(20),
            job_title varchar(255),
            company varchar(255),
            stage_reached varchar(50),
            is_registered tinyint(1) DEFAULT 0,
            is_converted tinyint(1) DEFAULT 0,
            session_token varchar(255),
            ip_address varchar(45),
            user_agent text,
            referrer_url text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email (email),
            KEY stage_reached (stage_reached),
            KEY is_registered (is_registered),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create guest sessions table
     */
    private static function create_sessions_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_guest_sessions';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_token varchar(255) NOT NULL,
            email varchar(100),
            name varchar(100),
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_activity datetime DEFAULT CURRENT_TIMESTAMP,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY session_token (session_token),
            KEY is_active (is_active),
            KEY created_at (created_at),
            KEY email (email)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Clean up expired sessions (called via cron)
     */
    public static function cleanup_expired_sessions() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_guest_sessions';
        
        // Mark sessions older than 30 minutes as inactive
        $wpdb->query(
            "UPDATE $table_name 
             SET is_active = 0 
             WHERE is_active = 1 
             AND last_activity < DATE_SUB(NOW(), INTERVAL 30 MINUTE)"
        );
        
        // Delete sessions older than 24 hours
        $wpdb->query(
            "DELETE FROM $table_name 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
    }
    
    /**
     * Uninstall tables (called on plugin deletion)
     */
    public static function uninstall() {
        global $wpdb;
        
        // Only run if explicitly deleting plugin data
        if (!defined('SFFC_DELETE_DATA') || !SFFC_DELETE_DATA) {
            return;
        }
        
        // Drop tables
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sffc_application_leads");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sffc_guest_sessions");
        
        // Delete version option
        delete_option(self::VERSION_OPTION);
    }
}

// Initialize on activation
register_activation_hook(__FILE__, array('SFFC_Database_Setup', 'init'));

// Schedule cleanup cron
if (!wp_next_scheduled('sffc_cleanup_sessions')) {
    wp_schedule_event(time(), 'hourly', 'sffc_cleanup_sessions');
}
add_action('sffc_cleanup_sessions', array('SFFC_Database_Setup', 'cleanup_expired_sessions'));