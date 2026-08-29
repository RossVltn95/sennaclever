<?php

/**
 * Search Query Database Cleanup Manager
 * 
 * Removes repetitive, low-quality, and non-SEO friendly search queries
 * to optimize database performance and maintain data quality.
 * 
 * @package SennaCareers
 * @since 11.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Search_Query_Cleanup
{
    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks()
    {
        // Schedule cleanup cron job
        add_action('wp', array($this, 'schedule_cleanup_cron'));
        
        // Cleanup cron handler
        add_action('sffc_search_cleanup_cron', array($this, 'perform_scheduled_cleanup'));
        
        // Admin actions
        add_action('wp_ajax_sffc_manual_search_cleanup', array($this, 'manual_cleanup_ajax'));
        
        // Plugin activation
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Plugin deactivation  
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Schedule cron job for regular cleanup
     */
    public function schedule_cleanup_cron()
    {
        if (!wp_next_scheduled('sffc_search_cleanup_cron')) {
            // Schedule daily cleanup at 3 AM
            wp_schedule_event(strtotime('3:00 AM'), 'daily', 'sffc_search_cleanup_cron');
        }
    }

    /**
     * Perform scheduled cleanup
     */
    public function perform_scheduled_cleanup()
    {
        $this->log_cleanup('Starting scheduled search query cleanup');
        
        $results = array(
            'removed_repetitive' => $this->cleanup_repetitive_queries(),
            'removed_non_seo' => $this->cleanup_non_seo_queries(),
            'removed_spam' => $this->cleanup_spam_queries(),
            'removed_old' => $this->cleanup_old_queries(),
            'optimized_table' => $this->optimize_table()
        );
        
        $total_removed = array_sum(array_filter($results, 'is_numeric'));
        
        $this->log_cleanup("Cleanup completed. Removed {$total_removed} records");
        
        // Store cleanup statistics
        update_option('sffc_last_cleanup_results', array(
            'date' => current_time('mysql'),
            'results' => $results
        ));
        
        return $results;
    }

    /**
     * Manual cleanup via AJAX
     */
    public function manual_cleanup_ajax()
    {
        // Verify nonce and permissions
        if (!check_ajax_referer('sffc_cleanup_nonce', 'nonce', false) || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }

        $results = $this->perform_scheduled_cleanup();
        wp_send_json_success($results);
    }

    /**
     * Remove repetitive/duplicate queries
     */
    private function cleanup_repetitive_queries()
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sffc_search_queries';
        
        // Find queries that appear more than 50 times from same IP/user
        $duplicates_sql = "
            SELECT query_text, ip_address, user_id, COUNT(*) as count
            FROM {$table}
            WHERE created_date > DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY query_text, COALESCE(ip_address, ''), COALESCE(user_id, 0)
            HAVING count > 50
        ";
        
        $duplicates = $wpdb->get_results($duplicates_sql);
        $removed = 0;
        
        foreach ($duplicates as $duplicate) {
            // Keep only the 10 most recent instances
            $keep_ids_sql = $wpdb->prepare("
                SELECT id FROM {$table}
                WHERE query_text = %s 
                  AND (ip_address = %s OR (ip_address IS NULL AND %s IS NULL))
                  AND (user_id = %d OR (user_id IS NULL AND %d IS NULL))
                ORDER BY created_date DESC
                LIMIT 10
            ", 
                $duplicate->query_text, 
                $duplicate->ip_address, 
                $duplicate->ip_address,
                $duplicate->user_id ?: 0, 
                $duplicate->user_id ?: 0
            );
            
            $keep_ids = $wpdb->get_col($keep_ids_sql);
            
            if (!empty($keep_ids)) {
                $keep_ids_str = implode(',', array_map('intval', $keep_ids));
                
                $delete_sql = $wpdb->prepare("
                    DELETE FROM {$table}
                    WHERE query_text = %s 
                      AND (ip_address = %s OR (ip_address IS NULL AND %s IS NULL))
                      AND (user_id = %d OR (user_id IS NULL AND %d IS NULL))
                      AND id NOT IN ({$keep_ids_str})
                ",
                    $duplicate->query_text,
                    $duplicate->ip_address,
                    $duplicate->ip_address, 
                    $duplicate->user_id ?: 0,
                    $duplicate->user_id ?: 0
                );
                
                $deleted = $wpdb->query($delete_sql);
                $removed += $deleted;
            }
        }
        
        return $removed;
    }

    /**
     * Remove non-SEO friendly queries
     */
    private function cleanup_non_seo_queries()
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sffc_search_queries';
        
        $non_seo_patterns = array(
            // Repetitive words (top top top, best best best)
            'regex' => array(
                '/\\b(\\w+)\\s+\\1\\s+\\1\\b/i', // word repeated 3+ times
                '/^(top|best|good|great|nice)\\s+\\1/i', // repeated adjectives
            ),
            
            // Non-descriptive queries
            'like' => array(
                '%top top%',
                '%best best%', 
                '%good good%',
                '%nice nice%',
                '%great great%',
                '%awesome awesome%',
                '%excellent excellent%',
                '%perfect perfect%',
            ),
            
            // Too short or too long queries
            'length' => array(
                'min' => 3,  // Too short
                'max' => 100 // Too long
            ),
            
            // Spam patterns
            'spam' => array(
                '%test%test%',
                '%asdf%',
                '%qwerty%',
                '%123456%',
                '%click here%',
                '%buy now%',
                '%free money%',
                '%get rich%',
            )
        );
        
        $removed = 0;
        
        // Remove queries with repetitive words using regex
        foreach ($non_seo_patterns['regex'] as $pattern) {
            $queries = $wpdb->get_results("SELECT id, query_text FROM {$table}");
            $ids_to_delete = array();
            
            foreach ($queries as $query) {
                if (preg_match($pattern, $query->query_text)) {
                    $ids_to_delete[] = $query->id;
                }
            }
            
            if (!empty($ids_to_delete)) {
                $ids_str = implode(',', array_map('intval', $ids_to_delete));
                $deleted = $wpdb->query("DELETE FROM {$table} WHERE id IN ({$ids_str})");
                $removed += $deleted;
            }
        }
        
        // Remove queries matching LIKE patterns
        foreach ($non_seo_patterns['like'] as $pattern) {
            $deleted = $wpdb->query($wpdb->prepare("
                DELETE FROM {$table} 
                WHERE LOWER(query_text) LIKE %s
            ", strtolower($pattern)));
            $removed += $deleted;
        }
        
        // Remove queries that are too short or too long
        $deleted = $wpdb->query($wpdb->prepare("
            DELETE FROM {$table}
            WHERE LENGTH(TRIM(query_text)) < %d 
               OR LENGTH(query_text) > %d
        ", $non_seo_patterns['length']['min'], $non_seo_patterns['length']['max']));
        $removed += $deleted;
        
        // Remove spam patterns
        foreach ($non_seo_patterns['spam'] as $spam_pattern) {
            $deleted = $wpdb->query($wpdb->prepare("
                DELETE FROM {$table} 
                WHERE LOWER(query_text) LIKE %s
            ", strtolower($spam_pattern)));
            $removed += $deleted;
        }
        
        return $removed;
    }

    /**
     * Remove spam and bot queries
     */
    private function cleanup_spam_queries()
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sffc_search_queries';
        
        // Remove queries with 0 results that appear frequently (likely spam)
        $removed = $wpdb->query("
            DELETE FROM {$table}
            WHERE results_count = 0
              AND query_text IN (
                  SELECT query_text FROM (
                      SELECT query_text, COUNT(*) as freq
                      FROM {$table}
                      WHERE results_count = 0 
                        AND created_date > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                      GROUP BY query_text
                      HAVING freq > 10
                  ) as spam_queries
              )
        ");
        
        // Remove queries from suspicious user agents (bots)
        $bot_agents = array(
            '%bot%', '%crawler%', '%spider%', '%scraper%',
            '%curl%', '%wget%', '%python%', '%automated%'
        );
        
        foreach ($bot_agents as $agent_pattern) {
            $deleted = $wpdb->query($wpdb->prepare("
                DELETE FROM {$table}
                WHERE LOWER(user_agent) LIKE %s
            ", $agent_pattern));
            $removed += $deleted;
        }
        
        return $removed;
    }

    /**
     * Remove old queries beyond retention period
     */
    private function cleanup_old_queries()
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sffc_search_queries';
        
        // Keep queries for 90 days only
        $retention_days = apply_filters('sffc_search_query_retention_days', 90);
        
        $removed = $wpdb->query($wpdb->prepare("
            DELETE FROM {$table}
            WHERE created_date < DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $retention_days));
        
        return $removed;
    }

    /**
     * Optimize database table
     */
    private function optimize_table()
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sffc_search_queries';
        
        // Optimize table structure
        $wpdb->query("OPTIMIZE TABLE {$table}");
        
        // Update table statistics
        $wpdb->query("ANALYZE TABLE {$table}");
        
        return true;
    }

    /**
     * Get cleanup statistics
     */
    public function get_cleanup_stats()
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sffc_search_queries';
        
        $stats = array();
        
        // Total queries
        $stats['total_queries'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        
        // Queries by period
        $stats['last_7_days'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$table}
            WHERE created_date > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        
        $stats['last_30_days'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$table}
            WHERE created_date > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        // Duplicate queries count
        $stats['potential_duplicates'] = $wpdb->get_var("
            SELECT COUNT(*) FROM (
                SELECT query_text, ip_address, COUNT(*) as freq
                FROM {$table}
                WHERE created_date > DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY query_text, ip_address
                HAVING freq > 10
            ) as dupes
        ");
        
        // Non-SEO queries count  
        $non_seo_count = 0;
        $queries = $wpdb->get_results("SELECT query_text FROM {$table} LIMIT 1000");
        foreach ($queries as $query) {
            if (preg_match('/\\b(\\w+)\\s+\\1\\s+\\1\\b/i', $query->query_text) ||
                strlen(trim($query->query_text)) < 3 ||
                strlen($query->query_text) > 100) {
                $non_seo_count++;
            }
        }
        $stats['estimated_non_seo'] = $non_seo_count;
        
        // Table size
        $table_status = $wpdb->get_row("SHOW TABLE STATUS LIKE '{$table}'");
        $stats['table_size_mb'] = round(($table_status->Data_length + $table_status->Index_length) / 1024 / 1024, 2);
        
        // Last cleanup
        $last_cleanup = get_option('sffc_last_cleanup_results');
        $stats['last_cleanup'] = $last_cleanup ? $last_cleanup['date'] : 'Never';
        
        return $stats;
    }

    /**
     * Log cleanup activity
     */
    private function log_cleanup($message)
    {
        error_log("[SFFC Search Cleanup] {$message}");
        
        // Store in option for admin viewing
        $logs = get_option('sffc_cleanup_logs', array());
        $logs[] = array(
            'time' => current_time('mysql'),
            'message' => $message
        );
        
        // Keep only last 50 log entries
        $logs = array_slice($logs, -50);
        update_option('sffc_cleanup_logs', $logs);
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        $this->schedule_cleanup_cron();
        $this->log_cleanup('Search query cleanup system activated');
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        wp_clear_scheduled_hook('sffc_search_cleanup_cron');
        $this->log_cleanup('Search query cleanup system deactivated');
    }
    
    /**
     * Emergency cleanup - removes most problematic queries quickly
     */
    public function emergency_cleanup()
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sffc_search_queries';
        $removed = 0;
        
        // Remove obvious spam (queries with no results, repeated many times)
        $removed += $wpdb->query("
            DELETE FROM {$table}
            WHERE results_count = 0 
              AND query_text IN (
                  SELECT * FROM (
                      SELECT query_text 
                      FROM {$table}
                      GROUP BY query_text
                      HAVING COUNT(*) > 100
                  ) as spam
              )
        ");
        
        // Remove very old queries (older than 30 days)
        $removed += $wpdb->query("
            DELETE FROM {$table}
            WHERE created_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        // Remove obviously non-SEO queries
        $removed += $wpdb->query("
            DELETE FROM {$table}
            WHERE LENGTH(TRIM(query_text)) < 3 
               OR query_text LIKE '%top top%'
               OR query_text LIKE '%test test%'
               OR query_text LIKE '%asdf%'
        ");
        
        $this->optimize_table();
        $this->log_cleanup("Emergency cleanup completed. Removed {$removed} records");
        
        return $removed;
    }
}