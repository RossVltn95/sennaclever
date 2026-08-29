<?php
/**
 * Learning Optimizer - Phase 6
 * Machine learning-inspired optimization for pattern recognition
 * 
 * @package SennaCareers
 * @since 6.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Learning_Optimizer {
    
    private static $instance = null;
    private $pattern_library;
    private $db_prefix;
    
    /**
     * Learning configuration
     */
    private $config = array(
        'min_confidence_threshold' => 0.6,
        'learning_rate' => 0.1,
        'decay_factor' => 0.95,
        'batch_size' => 100,
        'history_limit' => 10000,
        'update_frequency' => 86400 // Daily updates
    );
    
    /**
     * Pattern performance metrics
     */
    private $metrics = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->db_prefix = $wpdb->prefix;
        $this->load_dependencies();
        $this->initialize_metrics();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/patterns/class-pattern-library.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/patterns/class-pattern-library.php';
            $this->pattern_library = SFFC_Pattern_Library::get_instance();
        }
    }
    
    /**
     * Initialize performance metrics from database
     */
    private function initialize_metrics() {
        $cached_metrics = get_transient('sffc_pattern_metrics');
        
        if ($cached_metrics === false) {
            $this->metrics = $this->calculate_pattern_metrics();
            set_transient('sffc_pattern_metrics', $this->metrics, $this->config['update_frequency']);
        } else {
            $this->metrics = $cached_metrics;
        }
    }
    
    /**
     * Record pattern match for learning
     */
    public function record_pattern_match($query, $pattern, $entities, $response_data) {
        global $wpdb;
        
        $table_name = $this->db_prefix . 'sffc_pattern_history';
        
        // Store pattern match
        $wpdb->insert(
            $table_name,
            array(
                'user_query' => $query,
                'detected_pattern' => $pattern['intent'] ?? 'unknown',
                'entities_extracted' => json_encode($entities),
                'response_template_used' => $response_data['template_used'] ?? '',
                'response_source' => $response_data['source'] ?? 'pattern',
                'user_session' => $this->get_session_id(),
                'confidence_score' => $pattern['confidence'] ?? 0,
                'response_time' => $response_data['response_time'] ?? 0,
                'user_feedback' => null // Will be updated if user provides feedback
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s')
        );
        
        $record_id = $wpdb->insert_id;
        
        // Update pattern metrics
        $this->update_pattern_metrics($pattern['intent'], true);
        
        return $record_id;
    }
    
    /**
     * Record user feedback for learning
     */
    public function record_feedback($record_id, $feedback_type, $feedback_value) {
        global $wpdb;
        
        $table_name = $this->db_prefix . 'sffc_pattern_history';
        
        // Update record with feedback
        $wpdb->update(
            $table_name,
            array(
                'user_feedback' => json_encode(array(
                    'type' => $feedback_type,
                    'value' => $feedback_value,
                    'timestamp' => current_time('mysql')
                ))
            ),
            array('id' => $record_id),
            array('%s'),
            array('%d')
        );
        
        // Get pattern from record
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT detected_pattern FROM $table_name WHERE id = %d",
            $record_id
        ));
        
        if ($record) {
            // Update metrics based on feedback
            $positive_feedback = in_array($feedback_type, array('helpful', 'accurate', 'thumbs_up'));
            $this->update_pattern_metrics($record->detected_pattern, $positive_feedback);
        }
    }
    
    /**
     * Calculate pattern performance metrics
     */
    private function calculate_pattern_metrics() {
        global $wpdb;
        
        $table_name = $this->db_prefix . 'sffc_pattern_history';
        $metrics = array();
        
        // Get pattern usage statistics
        $results = $wpdb->get_results("
            SELECT 
                detected_pattern,
                COUNT(*) as usage_count,
                AVG(confidence_score) as avg_confidence,
                AVG(response_time) as avg_response_time,
                SUM(CASE WHEN user_feedback LIKE '%helpful%' THEN 1 ELSE 0 END) as positive_feedback,
                SUM(CASE WHEN user_feedback IS NOT NULL THEN 1 ELSE 0 END) as total_feedback
            FROM $table_name
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY detected_pattern
        ");
        
        foreach ($results as $row) {
            $success_rate = $row->total_feedback > 0 
                ? ($row->positive_feedback / $row->total_feedback) 
                : 0.5; // Default to neutral
            
            $metrics[$row->detected_pattern] = array(
                'usage_count' => (int)$row->usage_count,
                'avg_confidence' => (float)$row->avg_confidence,
                'avg_response_time' => (float)$row->avg_response_time,
                'success_rate' => $success_rate,
                'performance_score' => $this->calculate_performance_score($row),
                'last_updated' => current_time('mysql')
            );
        }
        
        return $metrics;
    }
    
    /**
     * Calculate performance score for a pattern
     */
    private function calculate_performance_score($stats) {
        // Weighted scoring
        $weights = array(
            'usage' => 0.2,
            'confidence' => 0.3,
            'speed' => 0.2,
            'success' => 0.3
        );
        
        // Normalize values
        $usage_score = min($stats->usage_count / 100, 1); // Cap at 100 uses
        $confidence_score = $stats->avg_confidence;
        $speed_score = max(0, 1 - ($stats->avg_response_time / 5)); // 5 second baseline
        $success_score = $stats->total_feedback > 0 
            ? ($stats->positive_feedback / $stats->total_feedback)
            : 0.5;
        
        // Calculate weighted score
        $score = ($usage_score * $weights['usage']) +
                ($confidence_score * $weights['confidence']) +
                ($speed_score * $weights['speed']) +
                ($success_score * $weights['success']);
        
        return round($score, 3);
    }
    
    /**
     * Update pattern metrics with new interaction
     */
    private function update_pattern_metrics($pattern_name, $positive_outcome) {
        if (!isset($this->metrics[$pattern_name])) {
            $this->metrics[$pattern_name] = array(
                'usage_count' => 0,
                'success_count' => 0,
                'failure_count' => 0,
                'avg_confidence' => 0.5,
                'performance_score' => 0.5
            );
        }
        
        // Update counts
        $this->metrics[$pattern_name]['usage_count']++;
        
        if ($positive_outcome) {
            $this->metrics[$pattern_name]['success_count']++;
        } else {
            $this->metrics[$pattern_name]['failure_count']++;
        }
        
        // Update success rate with exponential moving average
        $current_success = $positive_outcome ? 1 : 0;
        $this->metrics[$pattern_name]['success_rate'] = 
            ($this->metrics[$pattern_name]['success_rate'] ?? 0.5) * (1 - $this->config['learning_rate']) +
            $current_success * $this->config['learning_rate'];
        
        // Recalculate performance score
        $this->metrics[$pattern_name]['performance_score'] = 
            $this->calculate_simple_performance_score($this->metrics[$pattern_name]);
        
        // Save updated metrics
        set_transient('sffc_pattern_metrics', $this->metrics, $this->config['update_frequency']);
    }
    
    /**
     * Get optimized pattern for query
     */
    public function get_optimized_pattern($query, $initial_matches) {
        // If no metrics available, return initial matches
        if (empty($this->metrics)) {
            return $initial_matches;
        }
        
        // Adjust confidence based on historical performance
        if (isset($initial_matches['intent']) && 
            isset($this->metrics[$initial_matches['intent']])) {
            
            $performance = $this->metrics[$initial_matches['intent']];
            
            // Boost or reduce confidence based on performance
            $confidence_adjustment = ($performance['performance_score'] - 0.5) * 0.2;
            $initial_matches['confidence'] = min(1.0, max(0, 
                $initial_matches['confidence'] + $confidence_adjustment
            ));
            
            // Add performance data to matches
            $initial_matches['performance_data'] = $performance;
        }
        
        // Check for alternative patterns if confidence is low
        if ($initial_matches['confidence'] < $this->config['min_confidence_threshold']) {
            $alternatives = $this->find_alternative_patterns($query);
            
            if (!empty($alternatives)) {
                $initial_matches['alternatives'] = $alternatives;
            }
        }
        
        return $initial_matches;
    }
    
    /**
     * Find alternative pattern matches
     */
    private function find_alternative_patterns($query) {
        global $wpdb;
        
        $table_name = $this->db_prefix . 'sffc_pattern_history';
        
        // Find similar queries that were successful
        $similar_queries = $wpdb->get_results($wpdb->prepare("
            SELECT 
                detected_pattern,
                COUNT(*) as count,
                AVG(confidence_score) as avg_confidence
            FROM $table_name
            WHERE user_feedback LIKE '%helpful%'
                AND LENGTH(user_query) BETWEEN %d AND %d
                AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY detected_pattern
            ORDER BY count DESC
            LIMIT 3
        ", strlen($query) * 0.7, strlen($query) * 1.3));
        
        $alternatives = array();
        
        foreach ($similar_queries as $alt) {
            if (isset($this->metrics[$alt->detected_pattern])) {
                $alternatives[] = array(
                    'pattern' => $alt->detected_pattern,
                    'confidence' => $alt->avg_confidence,
                    'performance' => $this->metrics[$alt->detected_pattern]['performance_score']
                );
            }
        }
        
        return $alternatives;
    }
    
    /**
     * Analyze query complexity for learning
     */
    public function analyze_complexity($query) {
        $complexity_factors = array(
            'length' => strlen($query),
            'word_count' => str_word_count($query),
            'entities' => $this->count_entities($query),
            'technical_terms' => $this->count_technical_terms($query),
            'operators' => $this->count_operators($query)
        );
        
        // Calculate complexity score
        $complexity_score = 0;
        
        if ($complexity_factors['length'] > 100) $complexity_score += 0.2;
        if ($complexity_factors['word_count'] > 15) $complexity_score += 0.2;
        if ($complexity_factors['entities'] > 2) $complexity_score += 0.2;
        if ($complexity_factors['technical_terms'] > 3) $complexity_score += 0.2;
        if ($complexity_factors['operators'] > 1) $complexity_score += 0.2;
        
        return array(
            'score' => $complexity_score,
            'factors' => $complexity_factors,
            'classification' => $this->classify_complexity($complexity_score)
        );
    }
    
    /**
     * Get learning insights
     */
    public function get_learning_insights() {
        global $wpdb;
        
        $table_name = $this->db_prefix . 'sffc_pattern_history';
        
        // Top performing patterns
        $top_patterns = array();
        foreach ($this->metrics as $pattern => $data) {
            if ($data['usage_count'] > 10) {
                $top_patterns[$pattern] = $data['performance_score'];
            }
        }
        arsort($top_patterns);
        
        // Recent trends
        $recent_trends = $wpdb->get_results("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as query_count,
                AVG(confidence_score) as avg_confidence
            FROM $table_name
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        
        // Common entities
        $common_entities = $wpdb->get_results("
            SELECT 
                entities_extracted,
                COUNT(*) as count
            FROM $table_name
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND entities_extracted != 'null'
            GROUP BY entities_extracted
            ORDER BY count DESC
            LIMIT 10
        ");
        
        // Failed patterns (low confidence or negative feedback)
        $problem_patterns = $wpdb->get_results("
            SELECT 
                detected_pattern,
                AVG(confidence_score) as avg_confidence,
                COUNT(*) as count
            FROM $table_name
            WHERE (confidence_score < 0.5 OR user_feedback LIKE '%unhelpful%')
                AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY detected_pattern
            HAVING count > 5
            ORDER BY count DESC
        ");
        
        return array(
            'top_patterns' => array_slice($top_patterns, 0, 5, true),
            'recent_trends' => $recent_trends,
            'common_entities' => $this->parse_common_entities($common_entities),
            'problem_patterns' => $problem_patterns,
            'total_queries' => $this->get_total_queries(),
            'average_confidence' => $this->get_average_confidence(),
            'learning_status' => $this->get_learning_status()
        );
    }
    
    /**
     * Optimize response generation based on learning
     */
    public function optimize_response($pattern, $template, $data) {
        // Check if we have performance data for this pattern
        if (!isset($this->metrics[$pattern])) {
            return array(
                'template' => $template,
                'data' => $data,
                'optimizations' => array()
            );
        }
        
        $performance = $this->metrics[$pattern];
        $optimizations = array();
        
        // If pattern has high success rate, we can be more confident
        if ($performance['success_rate'] > 0.8) {
            $optimizations[] = 'high_confidence';
            // Can use more detailed template
            $template = $this->enhance_template($template);
        }
        
        // If pattern has low success rate, add fallbacks
        if ($performance['success_rate'] < 0.5) {
            $optimizations[] = 'add_fallbacks';
            // Add clarification options
            $data['clarifications'] = $this->generate_clarifications($pattern);
        }
        
        // If response time is slow, simplify
        if (isset($performance['avg_response_time']) && $performance['avg_response_time'] > 3) {
            $optimizations[] = 'simplify';
            // Use simpler template
            $template = $this->simplify_template($template);
        }
        
        return array(
            'template' => $template,
            'data' => $data,
            'optimizations' => $optimizations,
            'confidence_boost' => ($performance['performance_score'] - 0.5) * 0.2
        );
    }
    
    /**
     * Helper methods
     */
    
    private function get_session_id() {
        if (!session_id()) {
            session_start();
        }
        return session_id();
    }
    
    private function calculate_simple_performance_score($metrics) {
        $usage_weight = min($metrics['usage_count'] / 100, 1) * 0.3;
        $success_weight = ($metrics['success_rate'] ?? 0.5) * 0.7;
        
        return round($usage_weight + $success_weight, 3);
    }
    
    private function count_entities($query) {
        // Simple entity counting
        preg_match_all('/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\b/', $query, $matches);
        return count($matches[0]);
    }
    
    private function count_technical_terms($query) {
        $technical_terms = array(
            'PE', 'LBO', 'M&A', 'IPO', 'EBITDA', 'DCF', 'IRR', 'ROI', 'ROE',
            'P/E', 'EV', 'WACC', 'NPV', 'YTD', 'QoQ', 'YoY', 'bps', 'CAGR'
        );
        
        $count = 0;
        foreach ($technical_terms as $term) {
            if (stripos($query, $term) !== false) {
                $count++;
            }
        }
        
        return $count;
    }
    
    private function count_operators($query) {
        $operators = array(' vs ', ' versus ', ' and ', ' or ', ' compared to ', ' relative to ');
        $count = 0;
        
        foreach ($operators as $op) {
            $count += substr_count(strtolower($query), $op);
        }
        
        return $count;
    }
    
    private function classify_complexity($score) {
        if ($score >= 0.8) return 'very_complex';
        if ($score >= 0.6) return 'complex';
        if ($score >= 0.4) return 'moderate';
        if ($score >= 0.2) return 'simple';
        return 'very_simple';
    }
    
    private function parse_common_entities($raw_entities) {
        $parsed = array();
        
        foreach ($raw_entities as $row) {
            $entities = json_decode($row->entities_extracted, true);
            if (is_array($entities)) {
                foreach ($entities as $type => $values) {
                    if (!isset($parsed[$type])) {
                        $parsed[$type] = array();
                    }
                    if (is_array($values)) {
                        foreach ($values as $value) {
                            $parsed[$type][] = $value;
                        }
                    }
                }
            }
        }
        
        // Count occurrences
        foreach ($parsed as $type => &$values) {
            $values = array_count_values($values);
            arsort($values);
            $values = array_slice($values, 0, 5, true);
        }
        
        return $parsed;
    }
    
    private function get_total_queries() {
        global $wpdb;
        $table_name = $this->db_prefix . 'sffc_pattern_history';
        
        return $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    }
    
    private function get_average_confidence() {
        global $wpdb;
        $table_name = $this->db_prefix . 'sffc_pattern_history';
        
        $avg = $wpdb->get_var("
            SELECT AVG(confidence_score) 
            FROM $table_name 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        
        return round((float)$avg, 3);
    }
    
    private function get_learning_status() {
        $total = $this->get_total_queries();
        
        if ($total < 100) return 'initializing';
        if ($total < 1000) return 'learning';
        if ($total < 10000) return 'improving';
        
        return 'optimized';
    }
    
    private function enhance_template($template) {
        // Add more detail to template
        return $template;
    }
    
    private function simplify_template($template) {
        // Remove complex parts from template
        return $template;
    }
    
    private function generate_clarifications($pattern) {
        $clarifications = array(
            'market_status' => array(
                'Would you like specific index data?',
                'Are you interested in sector performance?',
                'Do you want to see intraday changes?'
            ),
            'company_analysis' => array(
                'Would you like fundamental metrics?',
                'Are you interested in recent news?',
                'Do you want peer comparison?'
            ),
            'private_equity' => array(
                'Are you looking for recent deals?',
                'Would you like fund performance data?',
                'Are you interested in specific sectors?'
            )
        );
        
        return $clarifications[$pattern] ?? array('Could you provide more details?');
    }
    
    /**
     * Database schema for learning tables
     */
    public function create_learning_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Extended pattern history table
        $table_pattern_history = $this->db_prefix . 'sffc_pattern_history';
        $sql_pattern = "CREATE TABLE IF NOT EXISTS $table_pattern_history (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_query text,
            detected_pattern varchar(100),
            entities_extracted longtext,
            response_template_used varchar(100),
            response_source varchar(50),
            user_session varchar(100),
            confidence_score decimal(3,2),
            response_time decimal(5,2),
            user_feedback text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_pattern (detected_pattern),
            KEY idx_session (user_session),
            KEY idx_created (created_at),
            KEY idx_confidence (confidence_score)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_pattern);
    }
}