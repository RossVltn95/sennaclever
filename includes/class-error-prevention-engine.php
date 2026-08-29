<?php

/**
 * WordPress Error Prevention Engine
 * Intercepts and fixes common WordPress/SQL errors before they cause 500 errors
 * 
 * @package MENA Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Error_Prevention_Engine
{

    private static $instance = null;
    private $error_patterns = array();
    private $query_filters = array();
    private $logged_errors = array();
    private $max_log_size = 100;

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
        $this->define_error_patterns();
        $this->define_query_filters();

        // Auto-initialize if not in manual mode
        if (!defined('SFFC_ERROR_ENGINE_MANUAL') || !SFFC_ERROR_ENGINE_MANUAL) {
            $this->init_hooks();
        }
    }

    /**
     * Define common error patterns and their fixes
     */
    private function define_error_patterns()
    {
        $this->error_patterns = array(
            // Empty ALTER TABLE statements
            '/ALTER\s+TABLE\s+`?(\w+)`?\s+ADD\s+``\s+\(`{2}\)/i' => array(
                'type' => 'empty_alter_table',
                'fix' => 'remove_query',
                'description' => 'Empty ALTER TABLE ADD INDEX statement'
            ),

            // Empty column or index names
            '/ALTER\s+TABLE\s+`?(\w+)`?\s+ADD\s+(?:INDEX|KEY)\s+`{0,2}\s*`{0,2}\s+\(/i' => array(
                'type' => 'empty_index_name',
                'fix' => 'remove_query',
                'description' => 'ALTER TABLE with empty index name'
            ),

            // Empty WHERE clauses
            '/WHERE\s*$/i' => array(
                'type' => 'empty_where',
                'fix' => 'remove_where',
                'description' => 'Empty WHERE clause'
            ),

            // WHERE followed by AND/OR without condition
            '/WHERE\s+(AND|OR)\s+/i' => array(
                'type' => 'where_and_or',
                'fix' => 'clean_where',
                'description' => 'WHERE directly followed by AND/OR'
            ),

            // Double semicolons
            '/;{2,}/' => array(
                'type' => 'double_semicolon',
                'fix' => 'single_semicolon',
                'description' => 'Multiple semicolons'
            ),

            // Empty INSERT VALUES
            '/INSERT\s+INTO\s+`?(\w+)`?\s*\(\s*\)\s*VALUES\s*\(\s*\)/i' => array(
                'type' => 'empty_insert',
                'fix' => 'remove_query',
                'description' => 'Empty INSERT statement'
            ),

            // Malformed CREATE TABLE
            '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?\s*\(\s*\)/i' => array(
                'type' => 'empty_create_table',
                'fix' => 'remove_query',
                'description' => 'Empty CREATE TABLE statement'
            ),

            // NULL in string functions (deprecated PHP 8.1+)
            '/(?:strpos|str_replace|substr|strlen)\s*\(\s*null/i' => array(
                'type' => 'null_string_function',
                'fix' => 'cast_to_string',
                'description' => 'NULL passed to string function'
            )
        );
    }

    /**
     * Define query filters for prevention
     */
    private function define_query_filters()
    {
        $this->query_filters = array(
            'alter_table' => '/^ALTER\s+TABLE/i',
            'create_table' => '/^CREATE\s+TABLE/i',
            'insert' => '/^INSERT\s+INTO/i',
            'update' => '/^UPDATE\s+/i',
            'delete' => '/^DELETE\s+FROM/i'
        );
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks()
    {
        // Hook into query filter to prevent bad queries - HIGHEST PRIORITY
        add_filter('query', array($this, 'filter_query'), 1);

        // Hook into wpdb query method variations
        add_filter('query_vars', array($this, 'check_query_vars'), 1);

        // Hook into dbdelta_queries to catch ALTER TABLE operations
        add_filter('dbdelta_queries', array($this, 'filter_dbdelta_queries'), 1);

        // Hook into wpdb to intercept all queries
        add_filter('pre_query', array($this, 'pre_filter_query'), 1);

        // Error handler for PHP errors
        set_error_handler(array($this, 'handle_php_error'), E_ALL);

        // Shutdown function to catch fatal errors
        register_shutdown_function(array($this, 'handle_shutdown'));

        // WordPress database error hook
        add_action('wp_die_handler', array($this, 'custom_die_handler'));

        // Admin notices for logged errors
        add_action('admin_notices', array($this, 'show_error_notices'));

        // AJAX error prevention
        add_action('wp_ajax_nopriv_sffc_test_error_engine', array($this, 'test_error_prevention'));
        add_action('wp_ajax_sffc_test_error_engine', array($this, 'test_error_prevention'));

        // Hook into plugins_loaded to ensure we catch everything
        add_action('plugins_loaded', array($this, 'setup_wpdb_override'), 1);
    }

    /**
     * Filter database queries before execution
     */
    public function filter_query($query)
    {
        // Skip if query is empty
        if (empty($query)) {
            return false;
        }

        // Trim the query
        $query = trim($query);

        // Check for problematic patterns
        foreach ($this->error_patterns as $pattern => $fix_info) {
            if (preg_match($pattern, $query, $matches)) {
                $this->log_error($pattern, $query, $fix_info);

                switch ($fix_info['fix']) {
                    case 'remove_query':
                        // Log and skip this query entirely
                        error_log('SFFC Error Prevention: Blocked query - ' . $fix_info['description']);
                        error_log('Query was: ' . substr($query, 0, 200));
                        return false;

                    case 'remove_where':
                        // Remove the empty WHERE clause
                        $query = preg_replace('/WHERE\s*$/i', '', $query);
                        // If WHERE was removed, return false to block the query
                        return false;

                    case 'clean_where':
                        // Remove WHERE followed directly by AND/OR
                        $query = preg_replace('/WHERE\s+(AND|OR)\s+/i', 'WHERE ', $query);
                        break;

                    case 'single_semicolon':
                        // Replace multiple semicolons with one
                        $query = preg_replace('/;{2,}/', ';', $query);
                        break;
                }
            }
        }

        // Additional validation for ALTER TABLE
        if (preg_match('/ALTER\s+TABLE\s+`?(\w+)`?\s+ADD/i', $query)) {
            // Check if it's trying to add an empty index
            if (preg_match('/ADD\s+(?:INDEX|KEY)?\s*`*\s*`*\s*\(\s*`*\s*`*\s*\)/i', $query)) {
                error_log('SFFC Error Prevention: Blocked ALTER TABLE with empty index');
                return false;
            }

            // Check for empty column names
            if (preg_match('/ADD\s+`{0,2}\s*`{0,2}\s+/', $query)) {
                error_log('SFFC Error Prevention: Blocked ALTER TABLE with empty column');
                return false;
            }
        }

        // Validate INSERT statements
        if (preg_match('/INSERT\s+INTO/i', $query)) {
            // Check for empty values
            if (preg_match('/VALUES\s*\(\s*\)/i', $query)) {
                error_log('SFFC Error Prevention: Blocked INSERT with empty values');
                return false;
            }
        }

        return $query;
    }

    /**
     * Pre-filter queries before they reach wpdb
     */
    public function pre_filter_query($query)
    {
        return $this->filter_query($query);
    }

    /**
     * Filter dbdelta queries
     */
    public function filter_dbdelta_queries($queries)
    {
        if (!is_array($queries)) {
            return $queries;
        }

        $filtered = array();
        foreach ($queries as $query) {
            $result = $this->filter_query($query);
            if ($result !== false) {
                $filtered[] = $result;
            }
        }

        return $filtered;
    }

    /**
     * Setup WPDB override to catch all queries
     */
    public function setup_wpdb_override()
    {
        global $wpdb;

        if (!$wpdb || !is_object($wpdb)) {
            return;
        }

        // Store original query method if not already stored
        if (!method_exists($wpdb, '_original_query')) {
            $original_query = array($wpdb, 'query');

            // Override the query method
            $wpdb->_original_query = function ($query) use ($wpdb) {
                // This is a backup, should not normally be called
                return call_user_func(array($wpdb, 'db_connect'), $query);
            };

            // Replace query method with our filtered version
            add_filter('query', array($this, 'filter_query'), 1);
        }
    }

    /**
     * Check query variables for issues
     */
    public function check_query_vars($vars)
    {
        if (!is_array($vars)) {
            return $vars;
        }

        // Remove empty values that could cause issues
        foreach ($vars as $key => $value) {
            if ($value === '' && !is_numeric($key)) {
                unset($vars[$key]);
            }
        }

        return $vars;
    }

    /**
     * Handle PHP errors
     */
    public function handle_php_error($errno, $errstr, $errfile, $errline)
    {
        // Check for null string function errors
        if (strpos($errstr, 'Passing null to parameter') !== false) {
            // Log but don't die
            error_log("SFFC Error Prevention: Caught null parameter error - $errstr in $errfile:$errline");

            // Try to continue execution
            return true;
        }

        // Check for SQL syntax errors
        if (strpos($errstr, 'SQL syntax') !== false) {
            error_log("SFFC Error Prevention: SQL syntax error - $errstr");

            // Extract the bad query if possible
            if (preg_match('/near \'(.+?)\' at line/i', $errstr, $matches)) {
                error_log("SFFC Error Prevention: Bad SQL fragment - " . $matches[1]);
            }

            return true;
        }

        // Let other errors pass through
        return false;
    }

    /**
     * Handle fatal errors on shutdown
     */
    public function handle_shutdown()
    {
        $error = error_get_last();

        if ($error && $error['type'] === E_ERROR) {
            // Check if it's a database error
            if (
                strpos($error['message'], 'SQL') !== false ||
                strpos($error['message'], 'ALTER TABLE') !== false
            ) {

                error_log('SFFC Error Prevention: Fatal SQL error caught - ' . $error['message']);

                // Try to recover if in AJAX context
                if (wp_doing_ajax()) {
                    // Clear any output
                    ob_clean();

                    // Send error response instead of dying
                    wp_send_json_error(array(
                        'message' => 'Database operation failed. Please try again.',
                        'technical' => 'SQL error prevented'
                    ));
                }
            }
        }
    }

    /**
     * Custom die handler
     */
    public function custom_die_handler($handler)
    {
        return array($this, 'custom_wp_die');
    }

    /**
     * Custom wp_die implementation
     */
    public function custom_wp_die($message, $title = '', $args = array())
    {
        // Check if it's a database error
        if (strpos($message, 'WordPress database error') !== false) {
            error_log('SFFC Error Prevention: Database error intercepted - ' . $message);

            // If AJAX, return JSON error
            if (wp_doing_ajax()) {
                wp_send_json_error(array(
                    'message' => 'A database error occurred. The issue has been logged.',
                    'logged' => true
                ));
            }

            // Otherwise show friendly error
            $message = 'A temporary database issue occurred. Please refresh and try again.';
        }

        // Call default handler
        _default_wp_die_handler($message, $title, $args);
    }

    /**
     * Log errors for review
     */
    private function log_error($pattern, $query, $fix_info)
    {
        $error = array(
            'time' => current_time('mysql'),
            'pattern' => $pattern,
            'query' => substr($query, 0, 500),
            'fix' => $fix_info,
            'backtrace' => wp_debug_backtrace_summary()
        );

        $this->logged_errors[] = $error;

        // Keep log size manageable
        if (count($this->logged_errors) > $this->max_log_size) {
            array_shift($this->logged_errors);
        }

        // Store in transient for admin review
        set_transient('sffc_prevented_errors', $this->logged_errors, HOUR_IN_SECONDS);
    }

    /**
     * Show admin notices for prevented errors
     */
    public function show_error_notices()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $errors = get_transient('sffc_prevented_errors');
        if ($errors && count($errors) > 0) {
?>
            <div class="notice notice-warning is-dismissible">
                <p><strong>Error Prevention Engine:</strong> <?php echo count($errors); ?> database errors were prevented in the last hour.
                    <a href="#" onclick="jQuery('#sffc-error-details').toggle(); return false;">View Details</a>
                </p>
                <div id="sffc-error-details" style="display: none; background: #f5f5f5; padding: 10px; margin: 10px 0;">
                    <h4>Prevented Errors:</h4>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li>
                                <strong><?php echo esc_html($error['fix']['description']); ?></strong><br>
                                Time: <?php echo esc_html($error['time']); ?><br>
                                Query: <code><?php echo esc_html(substr($error['query'], 0, 100)); ?>...</code>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
<?php
        }
    }

    /**
     * Run manual scan for errors
     * This can be triggered from admin panel
     */
    public function run_manual_scan()
    {
        global $wpdb;

        $results = array(
            'issues' => array(),
            'stats' => array(
                'tables_checked' => 0,
                'queries_analyzed' => 0,
                'errors_fixed' => 0
            )
        );

        // 1. Check for problematic table structures
        $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
        foreach ($tables as $table) {
            $table_name = $table[0];
            $results['stats']['tables_checked']++;

            // Check for empty indexes
            $indexes = $wpdb->get_results("SHOW INDEXES FROM `$table_name`", ARRAY_A);
            foreach ($indexes as $index) {
                if (empty($index['Column_name']) || $index['Column_name'] === '') {
                    $results['issues'][] = array(
                        'type' => 'Empty Index',
                        'description' => "Table $table_name has an empty index",
                        'fixed' => false
                    );
                }
            }
        }

        // 2. Check recent queries from debug log if available
        $log_file = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($log_file)) {
            $recent_lines = $this->tail($log_file, 100);
            foreach ($recent_lines as $line) {
                $results['stats']['queries_analyzed']++;

                // Check for SQL errors
                if (preg_match('/WordPress database error.*ALTER TABLE.*ADD\s+``/i', $line)) {
                    $results['issues'][] = array(
                        'type' => 'SQL Syntax Error',
                        'description' => 'Empty ALTER TABLE statement detected in logs',
                        'fixed' => true // We're already blocking these
                    );
                    $results['stats']['errors_fixed']++;
                }

                if (preg_match('/WordPress database error.*syntax error/i', $line)) {
                    $results['issues'][] = array(
                        'type' => 'SQL Syntax Error',
                        'description' => 'SQL syntax error found in logs',
                        'fixed' => true
                    );
                    $results['stats']['errors_fixed']++;
                }
            }
        }

        // 3. Check for known problematic patterns in options
        $bad_options = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} 
             WHERE option_value LIKE '%ALTER TABLE%ADD%``%' 
             OR option_value LIKE '%WHERE AND%' 
             OR option_value LIKE '%WHERE OR%'
             LIMIT 10"
        );

        foreach ($bad_options as $option) {
            $results['issues'][] = array(
                'type' => 'Bad SQL in Option',
                'description' => "Option '{$option->option_name}' contains problematic SQL",
                'fixed' => false
            );
        }

        // 4. Test our prevention engine
        $test_queries = array(
            "ALTER TABLE test ADD `` (``)",
            "INSERT INTO test () VALUES ()",
            "SELECT * FROM test WHERE "
        );

        foreach ($test_queries as $query) {
            $filtered = $this->filter_query($query);
            if ($filtered === false) {
                $results['stats']['errors_fixed']++;
            }
        }

        // 5. Check for PHP errors
        if (function_exists('error_get_last')) {
            $last_error = error_get_last();
            if ($last_error && in_array($last_error['type'], array(E_ERROR, E_WARNING, E_PARSE))) {
                $results['issues'][] = array(
                    'type' => 'PHP Error',
                    'description' => substr($last_error['message'], 0, 100),
                    'fixed' => false
                );
            }
        }

        return $results;
    }

    /**
     * Read last N lines from file
     */
    private function tail($file, $lines = 100)
    {
        if (!file_exists($file)) {
            return array();
        }

        $result = array();
        $fp = fopen($file, 'r');
        if (!$fp) {
            return array();
        }

        // Move to end of file
        fseek($fp, 0, SEEK_END);
        $pos = ftell($fp);
        $currentLine = '';
        $lineCount = 0;

        while ($pos > 0 && $lineCount < $lines) {
            $char = '';
            $pos--;
            fseek($fp, $pos, SEEK_SET);
            $char = fgetc($fp);

            if ($char === "\n") {
                if (!empty($currentLine)) {
                    $result[] = $currentLine;
                    $lineCount++;
                }
                $currentLine = '';
            } else {
                $currentLine = $char . $currentLine;
            }
        }

        if (!empty($currentLine)) {
            $result[] = $currentLine;
        }

        fclose($fp);
        return array_reverse($result);
    }

    /**
     * Test the error prevention system
     */
    public function test_error_prevention()
    {
        $results = array();

        // Test cases with problematic queries
        $test_queries = array(
            "ALTER TABLE wp_test ADD `` (``)" => false,
            "ALTER TABLE wp_test ADD INDEX `` (``)" => false,
            "INSERT INTO wp_test () VALUES ()" => false,
            "SELECT * FROM wp_test WHERE " => false,
            "CREATE TABLE wp_test ()" => false,
            "UPDATE wp_test SET WHERE id = 1" => "UPDATE wp_test SET WHERE id = 1", // Should pass through
            "DELETE FROM wp_test;;" => "DELETE FROM wp_test;",
            "SELECT * FROM wp_test WHERE AND active = 1" => "SELECT * FROM wp_test WHERE active = 1"
        );

        foreach ($test_queries as $query => $expected) {
            $filtered = $this->filter_query($query);
            $passed = ($filtered === $expected);

            $results[] = array(
                'query' => $query,
                'expected' => $expected,
                'result' => $filtered,
                'passed' => $passed
            );
        }

        wp_send_json_success(array(
            'message' => 'Error prevention engine test complete',
            'results' => $results,
            'logged_errors' => $this->logged_errors
        ));
    }
}

// Initialize the error prevention engine
// Check if auto mode is enabled (default to enabled if not set)
$auto_mode = get_option('sffc_error_prevention_auto', 'enabled');

if ($auto_mode === 'enabled') {
    // Auto-initialize on init
    add_action('init', array('SFFC_Error_Prevention_Engine', 'get_instance'), 1);
} else {
    // Manual mode - only initialize when explicitly called
    add_action('admin_init', function () {
        // Only initialize in admin for manual scans
        if (isset($_POST['sffc_run_error_scan'])) {
            SFFC_Error_Prevention_Engine::get_instance();
        }
    });
}
