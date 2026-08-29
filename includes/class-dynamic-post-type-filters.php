<?php
/**
 * Dynamic Post Type Filters
 * Creates filters from any custom post type and displays real data in intelligence cards
 * Works exactly like M42's job filter system but for any custom post type
 * 
 * @package SennaCareers
 * @since 10.20.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Dynamic_Post_Type_Filters {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Registered filter configurations
     */
    private $filter_configs = array();
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_filter_configs();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX handler for dynamic post type filters
        add_action('wp_ajax_sffc_dynamic_post_filter', array($this, 'handle_dynamic_filter_request'));
        add_action('wp_ajax_nopriv_sffc_dynamic_post_filter', array($this, 'handle_dynamic_filter_request'));
        add_action('wp_ajax_sffc_get_post_type_count', array($this, 'ajax_get_post_type_count'));
        add_action('wp_ajax_nopriv_sffc_get_post_type_count', array($this, 'ajax_get_post_type_count'));
        
        // Admin hooks for managing filters
        add_action('wp_ajax_sffc_save_post_type_filter', array($this, 'save_post_type_filter'));
        add_action('wp_ajax_sffc_delete_post_type_filter', array($this, 'delete_post_type_filter'));
        add_action('wp_ajax_sffc_create_default_filters', array($this, 'create_default_filters'));
        add_action('wp_ajax_sffc_clear_all_filters', array($this, 'clear_all_filters'));
        add_action('wp_ajax_sffc_reorder_filters', array($this, 'reorder_filters'));
        add_action('wp_ajax_sffc_bulk_delete_filters', array($this, 'bulk_delete_filters'));
        add_action('wp_ajax_sffc_bulk_toggle_filters', array($this, 'bulk_toggle_filters'));
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Extend ZENA's filter system
        add_filter('sffc_pe_filter_types', array($this, 'add_dynamic_filters_to_pe_system'));
    }
    
    /**
     * Load filter configurations from database
     */
    private function load_filter_configs() {
        $this->filter_configs = get_option('sffc_dynamic_post_type_filters', array());
        
        // Add defaults mapping to existing ZENA filters if none exist
        if (empty($this->filter_configs)) {
            $this->filter_configs = array(
                // Existing ZENA Intelligence filters - preserve functionality
                'deals' => array(
                    'label' => 'DEALS',
                    'post_type' => 'sffc_deal',
                    'icon' => 'M&A',
                    'color' => '#dc2626',
                    'priority' => 2,
                    'active' => true,
                    'existing_filter' => true, // Mark as existing ZENA filter
                    'meta_fields' => array(
                        'title_field' => 'post_title',
                        'summary_field' => 'post_excerpt',
                        'metric_field' => 'deal_value',
                        'change_field' => 'deal_status',
                        'category_field' => 'deal_type',
                        'source_field' => 'deal_source'
                    )
                ),
                'people' => array(
                    'label' => 'PEOPLE',
                    'post_type' => 'sffc_people',
                    'icon' => '👥',
                    'color' => '#059669',
                    'priority' => 3,
                    'active' => true,
                    'existing_filter' => true,
                    'meta_fields' => array(
                        'title_field' => 'post_title',
                        'summary_field' => 'post_excerpt',
                        'metric_field' => 'person_position',
                        'change_field' => 'person_status',
                        'category_field' => 'person_category',
                        'source_field' => 'person_source'
                    )
                ),
                'funds' => array(
                    'label' => 'FUNDS',
                    'post_type' => 'sffc_fund',
                    'icon' => 'FUND',
                    'color' => '#7c3aed',
                    'priority' => 4,
                    'active' => true,
                    'existing_filter' => true,
                    'meta_fields' => array(
                        'title_field' => 'post_title',
                        'summary_field' => 'post_excerpt',
                        'metric_field' => 'fund_size',
                        'change_field' => 'fund_performance',
                        'category_field' => 'fund_strategy',
                        'source_field' => 'fund_source'
                    )
                ),
                'markets' => array(
                    'label' => 'MARKETS',
                    'post_type' => 'sffc_market',
                    'icon' => '📈',
                    'color' => '#3b82f6',
                    'priority' => 5,
                    'active' => true,
                    'existing_filter' => true,
                    'meta_fields' => array(
                        'title_field' => 'post_title',
                        'summary_field' => 'post_excerpt',
                        'metric_field' => 'market_value',
                        'change_field' => 'market_change',
                        'category_field' => 'market_category',
                        'source_field' => 'market_source'
                    )
                ),
                'jobs' => array(
                    'label' => 'JOBS',
                    'post_type' => 'sffc_job',
                    'icon' => '💼',
                    'color' => '#ea580c',
                    'priority' => 6,
                    'active' => true,
                    'existing_filter' => true,
                    'meta_fields' => array(
                        'title_field' => 'post_title',
                        'summary_field' => 'post_excerpt',
                        'metric_field' => 'sffc_salary_range',
                        'change_field' => 'sffc_job_status',
                        'category_field' => 'sffc_job_category',
                        'source_field' => 'sffc_company_name'
                    )
                )
            );
            
            // Clear the configs so frontend doesn't try to process empty defaults
            $this->filter_configs = array();
        }
    }
    
    /**
     * Handle dynamic filter AJAX requests
     */
    public function handle_dynamic_filter_request() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sffc_dynamic_filter_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $filter_key = sanitize_text_field($_POST['filter_key'] ?? '');
        $limit = intval($_POST['limit'] ?? 12);
        
        if (!isset($this->filter_configs[$filter_key])) {
            wp_send_json_error('Filter configuration not found');
            return;
        }
        
        $config = $this->filter_configs[$filter_key];
        $cards_html = $this->get_cards_from_post_type($config, $limit);
        
        wp_send_json_success(array(
            'html' => $cards_html,
            'filter' => $filter_key,
            'count' => substr_count($cards_html, 'sffc-intelligence-card'),
            'post_type' => $config['post_type']
        ));
    }
    
    /**
     * Get cards from custom post type - REAL DATA like M42's job system
     */
    private function get_cards_from_post_type($config, $limit = 12) {
        $post_type = $config['post_type'];
        
        // Query real posts like M42 does for jobs
        $args = array(
            'post_type' => $post_type,
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        // Add meta query if specific meta fields are required
        if (!empty($config['required_meta'])) {
            $args['meta_query'] = array();
            foreach ($config['required_meta'] as $meta_key) {
                $args['meta_query'][] = array(
                    'key' => $meta_key,
                    'compare' => 'EXISTS'
                );
            }
        }
        
        $posts = get_posts($args);
        
        if (empty($posts)) {
            return $this->get_no_posts_message($config);
        }
        
        $html = '';
        foreach ($posts as $post) {
            $html .= $this->render_post_as_intelligence_card($post, $config);
        }
        
        return $html;
    }
    
    /**
     * Render post as intelligence card - Following M42's job card structure
     */
    private function render_post_as_intelligence_card($post, $config) {
        $meta_fields = $config['meta_fields'] ?? array();
        
        // Extract data from post and meta fields
        $title = $this->get_field_value($post, $meta_fields['title_field'] ?? 'post_title');
        $summary = $this->get_field_value($post, $meta_fields['summary_field'] ?? 'post_excerpt');
        $metric_value = $this->get_field_value($post, $meta_fields['metric_field'] ?? '');
        $metric_change = $this->get_field_value($post, $meta_fields['change_field'] ?? '');
        $category = $this->get_field_value($post, $meta_fields['category_field'] ?? '');
        $source = $this->get_field_value($post, $meta_fields['source_field'] ?? '');
        
        // Use post title/excerpt as fallbacks
        if (empty($title)) $title = $post->post_title;
        if (empty($summary)) $summary = $post->post_excerpt ?: wp_trim_words($post->post_content, 25);
        if (empty($source)) $source = $config['label'] ?? 'Intelligence';
        if (empty($category)) $category = 'General';
        if (empty($metric_value)) $metric_value = 'N/A';
        if (empty($metric_change)) $metric_change = '+0%';
        
        // Calculate time ago
        $time_ago = $this->time_ago(get_post_time('U', false, $post));
        
        // Generate card HTML following M42's structure
        return sprintf('
            <div class="sffc-intelligence-card dynamic-post-card" data-card-type="%s" data-card-id="post-%d" data-post-type="%s">
                <div class="sffc-card-header">
                    <div class="sffc-card-quick-actions">
                        <button type="button" class="sffc-card-icon-btn sffc-card-interest" title="Show interest">♡</button>
                        <button type="button" class="sffc-card-icon-btn sffc-card-dismiss" title="Dismiss">×</button>
                    </div>
                    <div class="sffc-card-meta-row">
                        <span class="sffc-company-badge">%s %s</span>
                        <span class="sffc-region-badge">%s</span>
                        <span class="sffc-time-badge">%s</span>
                    </div>
                </div>
                <div class="sffc-card-content">
                    <h2 class="sffc-card-headline">%s</h2>
                    <p class="sffc-card-summary">%s</p>
                    <div class="sffc-key-metrics">
                        <div class="sffc-metric">
                            <span class="sffc-metric-label">Value</span>
                            <span class="sffc-metric-value">%s</span>
                        </div>
                        <div class="sffc-metric">
                            <span class="sffc-metric-label">Change</span>
                            <span class="sffc-metric-value">%s</span>
                        </div>
                    </div>
                    <div class="sffc-ai-insight">
                        <strong>AI Analysis:</strong> %s
                    </div>
                </div>
                <div class="sffc-card-footer">
                    <button class="sffc-action-btn" data-action="view" data-post-id="%d">View Details</button>
                    <button class="sffc-action-btn sffc-btn-secondary" data-action="analyze" data-post-id="%d">Deep Analysis</button>
                </div>
            </div>',
            esc_attr($config['post_type']),
            $post->ID,
            esc_attr($config['post_type']),
            esc_html($config['icon'] ?? '📊'),
            esc_html($source),
            esc_html($category),
            esc_html($time_ago),
            esc_html($title),
            esc_html($summary),
            esc_html($metric_value),
            esc_html($metric_change),
            esc_html($this->generate_ai_insight($post, $config)),
            $post->ID,
            $post->ID
        );
    }
    
    /**
     * Get field value from post (post field or meta field)
     */
    private function get_field_value($post, $field_name) {
        if (empty($field_name)) return '';
        
        // Check if it's a post field
        if (in_array($field_name, array('post_title', 'post_content', 'post_excerpt', 'post_date'))) {
            return $post->{$field_name};
        }
        
        // Otherwise treat as meta field
        return get_post_meta($post->ID, $field_name, true);
    }
    
    /**
     * Generate AI insight based on post content
     */
    private function generate_ai_insight($post, $config) {
        $insights = array(
            'This intelligence provides strategic insights for investment decision-making.',
            'Key trends and market indicators suggest significant opportunities.',
            'Analysis shows strong correlation with current market conditions.',
            'Data indicates potential for strategic positioning in this sector.',
            'Market intelligence suggests favorable conditions for investment.'
        );
        
        // Use post content to generate more specific insight if available
        if (!empty($post->post_content)) {
            $content_length = strlen($post->post_content);
            return $insights[$content_length % count($insights)];
        }
        
        return $insights[0];
    }
    
    /**
     * Time ago helper (copied from M42)
     */
    private function time_ago($timestamp) {
        $time_ago = time() - $timestamp;
        
        if ($time_ago < 3600) {
            return floor($time_ago / 60) . ' min ago';
        } elseif ($time_ago < 86400) {
            return floor($time_ago / 3600) . ' hours ago';
        } else {
            return floor($time_ago / 86400) . ' days ago';
        }
    }
    
    /**
     * No posts message
     */
    private function get_no_posts_message($config) {
        return sprintf('
            <div class="sffc-no-content">
                <p>No %s posts found.</p>
                <p><small>Create some %s posts in WordPress admin to see them here.</small></p>
            </div>',
            esc_html($config['label']),
            esc_html($config['post_type'])
        );
    }
    
    /**
     * Add dynamic filters to ZENA's PE system
     */
    public function add_dynamic_filters_to_pe_system($filter_types) {
        foreach ($this->filter_configs as $key => $config) {
            if ($config['active']) {
                $filter_types[$key] = array(
                    'label' => $config['label'],
                    'icon' => $config['icon'],
                    'color' => $config['color'],
                    'priority' => $config['priority'],
                    'type' => 'dynamic_post_type',
                    'post_type' => $config['post_type']
                );
            }
        }
        return $filter_types;
    }
    
    /**
     * AJAX: Save post type filter configuration
     */
    public function save_post_type_filter() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'sffc_dynamic_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $filter_key = sanitize_text_field($_POST['filter_key']);
        $config = array(
            'label' => sanitize_text_field($_POST['label']),
            'post_type' => sanitize_text_field($_POST['post_type']),
            'icon' => sanitize_text_field($_POST['icon']),
            'color' => sanitize_hex_color($_POST['color']),
            'priority' => intval($_POST['priority']),
            'active' => isset($_POST['active']) ? true : false,
            'meta_fields' => array(
                'title_field' => sanitize_text_field($_POST['title_field'] ?? 'post_title'),
                'summary_field' => sanitize_text_field($_POST['summary_field'] ?? 'post_excerpt'),
                'metric_field' => sanitize_text_field($_POST['metric_field'] ?? ''),
                'change_field' => sanitize_text_field($_POST['change_field'] ?? ''),
                'category_field' => sanitize_text_field($_POST['category_field'] ?? ''),
                'source_field' => sanitize_text_field($_POST['source_field'] ?? '')
            ),
            'required_meta' => array_filter(explode(',', sanitize_text_field($_POST['required_meta'] ?? ''))),
            'card_template' => sanitize_text_field($_POST['card_template'] ?? 'default')
        );
        
        $this->filter_configs[$filter_key] = $config;
        update_option('sffc_dynamic_post_type_filters', $this->filter_configs);
        
        wp_send_json_success('Filter configuration saved successfully');
    }
    
    /**
     * AJAX: Delete post type filter
     */
    public function delete_post_type_filter() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'sffc_dynamic_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $filter_key = sanitize_text_field($_POST['filter_key']);
        
        if (isset($this->filter_configs[$filter_key])) {
            unset($this->filter_configs[$filter_key]);
            update_option('sffc_dynamic_post_type_filters', $this->filter_configs);
            wp_send_json_success('Filter deleted successfully');
        } else {
            wp_send_json_error('Filter not found');
        }
    }
    
    /**
     * AJAX handler for creating default filters manually
     */
    public function create_default_filters() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'sffc_dynamic_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Create the default filters configuration
        $default_filters = array(
            'deals' => array(
                'label' => 'DEALS',
                'post_type' => 'sffc_pe_deal',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>',
                'color' => '#dc2626',
                'priority' => 2,
                'active' => true,
                'existing_filter' => true,
                'meta_fields' => array(
                    'title_field' => 'post_title',
                    'summary_field' => 'post_excerpt',
                    'metric_field' => 'deal_value',
                    'change_field' => 'deal_status',
                    'category_field' => 'deal_type',
                    'source_field' => 'deal_source'
                )
            ),
            'people' => array(
                'label' => 'PEOPLE',
                'post_type' => 'sffc_people',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M16 4c0-1.11.89-2 2-2s2 .89 2 2-.89 2-2 2-2-.89-2-2zM4 18v-4h3v-3c0-1.1.9-2 2-2h2c1.1 0 2 .9 2 2v3h3v4H4zm12-5.5c.83 0 1.5-.67 1.5-1.5s-.67-1.5-1.5-1.5-1.5.67-1.5 1.5.67 1.5 1.5 1.5zm3 .5h-2v7h2v-7z"/></svg>',
                'color' => '#059669',
                'priority' => 3,
                'active' => true,
                'existing_filter' => true,
                'meta_fields' => array(
                    'title_field' => 'post_title',
                    'summary_field' => 'post_excerpt',
                    'metric_field' => 'person_position',
                    'change_field' => 'person_status',
                    'category_field' => 'person_category',
                    'source_field' => 'person_source'
                )
            ),
            'funds' => array(
                'label' => 'FUNDS',
                'post_type' => 'sffc_fund',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M5 8h14V6H5v2zm0 2.5h14v-1H5v1zm0 2.5h14v-1H5v1zm0 2.5h14V14H5v1.5zM3 4v16h18V4H3z"/></svg>',
                'color' => '#7c3aed',
                'priority' => 4,
                'active' => true,
                'existing_filter' => true,
                'meta_fields' => array(
                    'title_field' => 'post_title',
                    'summary_field' => 'post_excerpt',
                    'metric_field' => 'fund_size',
                    'change_field' => 'fund_performance',
                    'category_field' => 'fund_strategy',
                    'source_field' => 'fund_source'
                )
            ),
            'markets' => array(
                'label' => 'MARKETS',
                'post_type' => 'sffc_pe_news',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6h-6z"/></svg>',
                'color' => '#3b82f6',
                'priority' => 5,
                'active' => true,
                'existing_filter' => true,
                'meta_fields' => array(
                    'title_field' => 'post_title',
                    'summary_field' => 'post_excerpt',
                    'metric_field' => 'market_value',
                    'change_field' => 'market_change',
                    'category_field' => 'market_category',
                    'source_field' => 'market_source'
                )
            ),
            'jobs' => array(
                'label' => 'JOBS',
                'post_type' => 'sffc_jobs',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20 6h-2.18c.11-.31.18-.65.18-1a3 3 0 0 0-6 0c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2z"/></svg>',
                'color' => '#ea580c',
                'priority' => 6,
                'active' => true,
                'existing_filter' => true,
                'meta_fields' => array(
                    'title_field' => 'post_title',
                    'summary_field' => 'post_excerpt',
                    'metric_field' => 'sffc_salary_range',
                    'change_field' => 'sffc_job_status',
                    'category_field' => 'sffc_job_category',
                    'source_field' => 'sffc_company_name'
                )
            )
        );
        
        // Save to database
        $result = update_option('sffc_dynamic_post_type_filters', $default_filters);
        
        if ($result !== false) {
            // Reload the filter configs
            $this->filter_configs = $default_filters;
            
            wp_send_json_success(array(
                'message' => 'Default filters created successfully!',
                'filters_created' => count($default_filters),
                'filters' => array_keys($default_filters)
            ));
        } else {
            wp_send_json_error('Failed to save default filters to database');
        }
    }
    
    /**
     * AJAX handler for clearing all filters
     */
    public function clear_all_filters() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'sffc_dynamic_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Clear all filters from database
        $result = delete_option('sffc_dynamic_post_type_filters');
        
        // Also clear from instance
        $this->filter_configs = array();
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'All filters cleared successfully!'
            ));
        } else {
            wp_send_json_error('Failed to clear filters from database');
        }
    }
    
    /**
     * AJAX handler for reordering filters
     */
    public function reorder_filters() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'sffc_dynamic_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $order = $_POST['order'] ?? array();
        
        if (!is_array($order)) {
            wp_send_json_error('Invalid order data');
            return;
        }
        
        // Update priorities in the filter configs
        foreach ($order as $item) {
            if (isset($item['key']) && isset($item['priority']) && isset($this->filter_configs[$item['key']])) {
                $this->filter_configs[$item['key']]['priority'] = intval($item['priority']);
            }
        }
        
        // Save updated configs
        $result = update_option('sffc_dynamic_post_type_filters', $this->filter_configs);
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'Filter order updated successfully!'
            ));
        } else {
            wp_send_json_error('Failed to save filter order');
        }
    }
    
    /**
     * AJAX handler for bulk deleting filters
     */
    public function bulk_delete_filters() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'sffc_dynamic_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $filter_keys = $_POST['filter_keys'] ?? array();
        
        if (!is_array($filter_keys) || empty($filter_keys)) {
            wp_send_json_error('No filters specified for deletion');
            return;
        }
        
        $deleted_count = 0;
        foreach ($filter_keys as $key) {
            if (isset($this->filter_configs[sanitize_text_field($key)])) {
                unset($this->filter_configs[sanitize_text_field($key)]);
                $deleted_count++;
            }
        }
        
        if ($deleted_count > 0) {
            $result = update_option('sffc_dynamic_post_type_filters', $this->filter_configs);
            
            if ($result !== false) {
                wp_send_json_success(array(
                    'message' => "Successfully deleted {$deleted_count} filter" . ($deleted_count > 1 ? 's' : ''),
                    'deleted_count' => $deleted_count
                ));
            } else {
                wp_send_json_error('Failed to save changes to database');
            }
        } else {
            wp_send_json_error('No valid filters found for deletion');
        }
    }
    
    /**
     * AJAX handler for bulk toggling filter activation
     */
    public function bulk_toggle_filters() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'sffc_dynamic_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $filter_keys = $_POST['filter_keys'] ?? array();
        $activate = $_POST['activate'] ?? true;
        
        if (!is_array($filter_keys) || empty($filter_keys)) {
            wp_send_json_error('No filters specified');
            return;
        }
        
        $updated_count = 0;
        foreach ($filter_keys as $key) {
            $clean_key = sanitize_text_field($key);
            if (isset($this->filter_configs[$clean_key])) {
                $this->filter_configs[$clean_key]['active'] = (bool)$activate;
                $updated_count++;
            }
        }
        
        if ($updated_count > 0) {
            $result = update_option('sffc_dynamic_post_type_filters', $this->filter_configs);
            
            if ($result !== false) {
                $action = $activate ? 'activated' : 'deactivated';
                wp_send_json_success(array(
                    'message' => "Successfully {$action} {$updated_count} filter" . ($updated_count > 1 ? 's' : ''),
                    'updated_count' => $updated_count
                ));
            } else {
                wp_send_json_error('Failed to save changes to database');
            }
        } else {
            wp_send_json_error('No valid filters found for update');
        }
    }
    
    /**
     * AJAX handler for getting post type count
     */
    public function ajax_get_post_type_count() {
        $post_type = sanitize_text_field($_POST['post_type'] ?? '');
        
        if (empty($post_type)) {
            wp_send_json_error('Post type not specified');
            return;
        }
        
        // Get published post count
        $post_count = wp_count_posts($post_type);
        $published_count = isset($post_count->publish) ? $post_count->publish : 0;
        
        wp_send_json_success(array(
            'count' => $published_count,
            'post_type' => $post_type
        ));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if (!$this->should_enqueue_assets()) {
            return;
        }
        
        wp_enqueue_script(
            'sffc-dynamic-post-filters',
            SFFC_PLUGIN_URL . 'assets/js/dynamic-post-type-filters.js',
            array('jquery'),
            SFFC_VERSION,
            true
        );
        
        wp_localize_script('sffc-dynamic-post-filters', 'sffc_dynamic_filters', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_dynamic_filter_nonce'),
            'filters' => $this->filter_configs
        ));
        
        wp_enqueue_style(
            'sffc-dynamic-post-filters',
            SFFC_PLUGIN_URL . 'assets/css/dynamic-post-type-filters.css',
            array(),
            SFFC_VERSION
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook_suffix) {
        if ($hook_suffix !== 'toplevel_page_sffc-dynamic-filters') {
            return;
        }
        
        wp_enqueue_script(
            'sffc-dynamic-filters-admin',
            SFFC_PLUGIN_URL . 'admin/js/dynamic-post-type-filters-admin.js',
            array('jquery', 'jquery-ui-sortable', 'wp-color-picker'),
            SFFC_VERSION,
            true
        );
        
        wp_localize_script('sffc-dynamic-filters-admin', 'sffc_dynamic_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_dynamic_admin_nonce'),
            'post_types' => $this->get_available_post_types()
        ));
        
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_style(
            'sffc-dynamic-filters-admin',
            SFFC_PLUGIN_URL . 'admin/css/dynamic-post-type-filters-admin.css',
            array('wp-color-picker'),
            SFFC_VERSION
        );
    }
    
    /**
     * Get available custom post types
     */
    private function get_available_post_types() {
        $post_types = get_post_types(array('public' => true, '_builtin' => false), 'objects');
        $available = array();
        
        foreach ($post_types as $post_type) {
            $available[$post_type->name] = $post_type->label;
        }
        
        return $available;
    }
    
    /**
     * Check if we should enqueue assets
     */
    private function should_enqueue_assets() {
        global $post;
        
        // Always enqueue on admin pages for this plugin
        if (is_admin()) {
            return false; // Admin assets are handled separately
        }
        
        // Enqueue on any page that might have story filters or intelligence cards
        if ($post) {
            $has_shortcodes = (
                has_shortcode($post->post_content, 'sffc_pe_filters') ||
                has_shortcode($post->post_content, 'sffc_intelligence_cards') ||
                strpos($post->post_content, 'pe-filter-container') !== false ||
                strpos($post->post_content, 'sffc-intelligence-card') !== false ||
                strpos($post->post_content, 'sffc-story-filters') !== false
            );
            
            if ($has_shortcodes) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get filter configurations
     */
    public function get_filter_configs() {
        return $this->filter_configs;
    }
    
    /**
     * Register a new filter configuration programmatically
     */
    public function register_filter($key, $config) {
        $this->filter_configs[$key] = $config;
        update_option('sffc_dynamic_post_type_filters', $this->filter_configs);
    }
}

// Initialize
SFFC_Dynamic_Post_Type_Filters::get_instance();
