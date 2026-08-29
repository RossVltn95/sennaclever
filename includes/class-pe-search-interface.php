<?php
/**
 * Private Equity Search Interface
 * Google-style search engine for PE content
 * 
 * @package SennaCareers
 * @since 10.23.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_PE_Search_Interface {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Search modes configuration with professional SVG icons
     */
    private $search_modes = array(
        'jobs' => array(
            'label' => 'Jobs',
            'post_type' => 'sffc_job',
            'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>',
            'placeholder' => 'Search positions, companies, locations...',
            'color' => '#0f172a',
            'gradient' => 'linear-gradient(135deg, #1e293b 0%, #334155 100%)'
        ),
        'insights' => array(
            'label' => 'Insights',
            'post_type' => 'sffc_pe_news',
            'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13,2 13,9 20,9"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>',
            'placeholder' => 'Search market intelligence, deals, news...',
            'color' => '#0a4940',
            'gradient' => 'linear-gradient(135deg, #0a4940 0%, #22c55e 100%)'
        ),
        'recruiters' => array(
            'label' => 'Recruiters',
            'post_type' => 'sffc_recruiter',
            'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',
            'placeholder' => 'Search recruiters, specializations...',
            'color' => '#1e40af',
            'gradient' => 'linear-gradient(135deg, #1e40af 0%, #2563eb 100%)'
        ),
        'templates' => array(
            'label' => 'Templates',
            'post_type' => 'sffc_salary_guide',
            'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"></rect><line x1="7" y1="8" x2="17" y2="8"></line><line x1="7" y1="12" x2="17" y2="12"></line><line x1="7" y1="16" x2="13" y2="16"></line></svg>',
            'placeholder' => 'Search playbooks, frameworks, templates...',
            'color' => '#0f172a',
            'gradient' => 'linear-gradient(135deg, #0f172a 0%, #334155 100%)'
        )
    );
    
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
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register shortcodes
        add_shortcode('sffc_pe_search', array($this, 'render_search_interface'));
        add_shortcode('sffc_pe_search_compact', array($this, 'render_compact_search'));
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // AJAX handlers for autocomplete
        add_action('wp_ajax_sffc_search_autocomplete', array($this, 'handle_autocomplete'));
        add_action('wp_ajax_nopriv_sffc_search_autocomplete', array($this, 'handle_autocomplete'));
        
        // AJAX handler for search execution
        add_action('wp_ajax_sffc_execute_search', array($this, 'handle_search_execution'));
        add_action('wp_ajax_nopriv_sffc_execute_search', array($this, 'handle_search_execution'));
    }
    
    /**
     * Render search interface shortcode
     */
    public function render_search_interface($atts = array()) {
        $atts = shortcode_atts(array(
            'default_mode' => 'jobs',
            'show_modes' => 'all',
            'placeholder' => '',
            'style' => 'default',
            'results_page' => '',
            'class' => ''
        ), $atts);
        
        // Generate unique ID for this search instance - FIXED: WordPress version compatibility
        $search_id = 'sffc-search-' . (function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid());
        
        // Start output buffering
        ob_start();
        ?>
        
        <div class="sffc-pe-search-container <?php echo esc_attr($atts['class']); ?>" 
             id="<?php echo esc_attr($search_id); ?>"
             data-results-page="<?php echo esc_attr($atts['results_page'] ?: '/search-results/'); ?>">
            
            <!-- Search Header -->
            <div class="sffc-search-header">
                <div class="sffc-search-logo">
                    <div class="sffc-logo-icon">S</div>
                    <div class="sffc-logo-text">
                        <span class="sffc-logo-primary">MENA Careers</span>
                    </div>
                </div>
                <div class="sffc-logo-tagline">The Place for Private Equity Professionals</div>
            </div>
            
            <!-- Mode Selector Tabs -->
            <div class="sffc-search-modes">
                <?php foreach ($this->search_modes as $mode_key => $mode_config): ?>
                    <button type="button" 
                            class="sffc-mode-tab <?php echo $mode_key === $atts['default_mode'] ? 'active' : ''; ?>"
                            data-mode="<?php echo esc_attr($mode_key); ?>"
                            data-placeholder="<?php echo esc_attr($mode_config['placeholder']); ?>"
                            data-color="<?php echo esc_attr($mode_config['color']); ?>">
                        <span class="sffc-mode-icon"><?php echo $mode_config['icon']; ?></span>
                        <span class="sffc-mode-label"><?php echo esc_html($mode_config['label']); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            
            <!-- Main Search Bar -->
            <div class="sffc-search-main">
                <div class="sffc-search-bar-container">
                    <div class="sffc-search-bar">
                        <div class="sffc-search-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                        </div>
                        
                        <input type="text" 
                               class="sffc-search-input"
                               placeholder="<?php echo esc_attr($this->search_modes[$atts['default_mode']]['placeholder']); ?>"
                               autocomplete="off"
                               spellcheck="false"
                               data-mode="<?php echo esc_attr($atts['default_mode']); ?>"
                               data-results-page="<?php echo esc_attr($atts['results_page']); ?>">
                        
                        <div class="sffc-search-actions">
                            <button type="button" class="sffc-search-clear" title="Clear search">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                            
                            <div class="sffc-search-divider"></div>
                            
                            <button type="button" class="sffc-voice-search" title="Voice search">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                                    <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                                    <line x1="12" y1="19" x2="12" y2="23"></line>
                                    <line x1="8" y1="23" x2="16" y2="23"></line>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Autocomplete Dropdown -->
                    <div class="sffc-autocomplete-dropdown" style="display: none;">
                        <div class="sffc-autocomplete-content">
                            <!-- Dynamic suggestions will be loaded here -->
                        </div>
                    </div>
                </div>
                
                <!-- Search Buttons -->
                <div class="sffc-search-buttons">
                    <button type="submit" class="sffc-search-btn sffc-search-submit">
                        PE Search
                    </button>
                    <button type="button" class="sffc-search-btn sffc-feeling-lucky">
                        I'm Feeling Lucky
                    </button>
                </div>
            </div>
            
            <!-- Quick Filters (Hidden by default, shown after search) -->
            <div class="sffc-quick-filters" style="display: none;">
                <div class="sffc-filter-pills">
                    <!-- Dynamic filter pills will be populated here -->
                </div>
            </div>
            
            <!-- Search Stats (for results page) -->
            <div class="sffc-search-stats" style="display: none;">
                <span class="sffc-results-count">About <strong>0</strong> results</span>
                <span class="sffc-search-time">(<strong>0.00</strong> seconds)</span>
            </div>
            
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render compact search interface shortcode
     */
    public function render_compact_search($atts = array()) {
        $atts = shortcode_atts(array(
            'default_mode' => 'jobs',
            'show_modes' => 'all',
            'placeholder' => '',
            'style' => 'compact',
            'results_page' => '',
            'class' => ''
        ), $atts);
        
        // Generate unique ID for this search instance
        $search_id = 'sffc-compact-search-' . (function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid());
        
        // Start output buffering
        ob_start();
        ?>
        
        <div class="sffc-pe-search-compact <?php echo esc_attr($atts['class']); ?>" 
             id="<?php echo esc_attr($search_id); ?>"
             data-results-page="<?php echo esc_attr($atts['results_page'] ?: '/search-results/'); ?>">
            
            <!-- Mode Selector Tabs - Compact Style -->
            <div class="sffc-search-modes-compact">
                <?php foreach ($this->search_modes as $mode_key => $mode_config): ?>
                    <button type="button" 
                            class="sffc-mode-tab-compact <?php echo $mode_key === $atts['default_mode'] ? 'active' : ''; ?>"
                            data-mode="<?php echo esc_attr($mode_key); ?>"
                            data-placeholder="<?php echo esc_attr($mode_config['placeholder']); ?>"
                            data-color="<?php echo esc_attr($mode_config['color']); ?>">
                        <span class="sffc-mode-icon"><?php echo $mode_config['icon']; ?></span>
                        <span class="sffc-mode-label"><?php echo esc_html($mode_config['label']); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            
            <!-- Main Search Bar - Compact Style -->
            <div class="sffc-search-main-compact">
                <div class="sffc-search-bar-container-compact">
                    <div class="sffc-search-bar-compact">
                        <div class="sffc-search-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                        </div>
                        
                        <input type="text" 
                               class="sffc-search-input"
                               placeholder="<?php echo esc_attr($this->search_modes[$atts['default_mode']]['placeholder']); ?>"
                               autocomplete="off"
                               spellcheck="false"
                               data-mode="<?php echo esc_attr($atts['default_mode']); ?>"
                               data-results-page="<?php echo esc_attr($atts['results_page']); ?>">
                        
                        <div class="sffc-search-actions">
                            <button type="button" class="sffc-search-clear" title="Clear search">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Autocomplete Dropdown -->
                    <div class="sffc-autocomplete-dropdown" style="display: none;">
                        <div class="sffc-autocomplete-content">
                            <!-- Dynamic suggestions will be loaded here -->
                        </div>
                    </div>
                </div>
                
                <!-- Search Buttons - Compact Style -->
                <div class="sffc-search-buttons-compact">
                    <button type="submit" class="sffc-search-btn-compact sffc-search-submit-compact">
                        PE Search
                    </button>
                    <button type="button" class="sffc-search-btn-compact sffc-feeling-lucky-compact">
                        I'm Feeling Lucky
                    </button>
                </div>
            </div>
            
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Handle autocomplete AJAX requests
     */
    public function handle_autocomplete() {
        // FIXED: Proper nonce verification for autocomplete
        check_ajax_referer('sffc_search_nonce', 'nonce');
        
        $query = sanitize_text_field($_GET['q'] ?? '');
        $mode = sanitize_text_field($_GET['mode'] ?? 'jobs');
        $limit = intval($_GET['limit'] ?? 8);
        
        if (strlen($query) < 2) {
            wp_send_json_success(array('suggestions' => array()));
            return;
        }
        
        $suggestions = $this->get_autocomplete_suggestions($query, $mode, $limit);
        
        wp_send_json_success(array(
            'suggestions' => $suggestions,
            'query' => $query,
            'mode' => $mode
        ));
    }
    
    /**
     * Get autocomplete suggestions - Now uses comprehensive autosuggestion library
     */
    private function get_autocomplete_suggestions($query, $mode, $limit = 8) {
        // Use the new comprehensive autosuggestion library
        if (class_exists('SFFC_Autosuggestion_Library')) {
            $library = SFFC_Autosuggestion_Library::get_instance();
            $suggestions = $library->get_suggestions($query, $mode, $limit);
            
            // Already formatted correctly by the library
            return $suggestions;
        }
        
        // Use search query processor as fallback
        if (class_exists('SFFC_Search_Query')) {
            $search_processor = SFFC_Search_Query::get_instance();
            $suggestions = $search_processor->get_autocomplete_suggestions($query, $mode, $limit);
            
            // Format suggestions for frontend compatibility
            $formatted_suggestions = array();
            foreach ($suggestions as $suggestion) {
                $formatted_suggestions[] = array(
                    'text' => $suggestion['text'],
                    'type' => $suggestion['type'],
                    'category' => ucfirst(str_replace('_', ' ', $suggestion['type'])),
                    'icon' => $this->get_suggestion_icon($suggestion['type']),
                    'score' => $suggestion['score'] ?? 1.0
                );
            }
            
            return $formatted_suggestions;
        }
        
        // Final fallback to legacy method
        return $this->get_legacy_autocomplete_suggestions($query, $mode, $limit);
    }
    
    /**
     * Legacy autocomplete method (fallback)
     */
    private function get_legacy_autocomplete_suggestions($query, $mode, $limit = 8) {
        $suggestions = array();
        
        if (!isset($this->search_modes[$mode])) {
            return $suggestions;
        }
        
        $mode_config = $this->search_modes[$mode];
        $post_type = $mode_config['post_type'];
        
        // Search in post titles first
        $title_results = $this->search_post_titles($query, $post_type, 4);
        
        // Search in meta fields (companies, locations, etc.)
        $meta_results = $this->search_meta_fields($query, $post_type, 4);
        
        // Combine and format suggestions
        $all_results = array_merge($title_results, $meta_results);
        
        // Remove duplicates and limit results
        $unique_suggestions = array();
        $seen_text = array();
        
        foreach ($all_results as $result) {
            $suggestion_text = $result['text'];
            if (!in_array($suggestion_text, $seen_text) && count($unique_suggestions) < $limit) {
                $unique_suggestions[] = $result;
                $seen_text[] = $suggestion_text;
            }
        }
        
        return $unique_suggestions;
    }
    
    /**
     * Get icon for suggestion type
     */
    private function get_suggestion_icon($type) {
        $icons = array(
            'company' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"></path><path d="M5 21V7l8-4v18"></path><path d="M19 21V11l-6-4"></path></svg>',
            'location' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="10" r="3"></circle><path d="M12 21.7c5-4.7 8-9.7 8-11.7 0-4.4-3.6-8-8-8s-8 3.6-8 8c0 2 3 7 8 11.7z"></path></svg>',
            'popular_search' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15,3 21,3 21,9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>',
            'default' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>'
        );
        
        return $icons[$type] ?? $icons['default'];
    }
    
    /**
     * Search post titles
     */
    private function search_post_titles($query, $post_type, $limit) {
        global $wpdb;
        
        $sql = $wpdb->prepare("
            SELECT DISTINCT post_title as text, 'title' as type, ID as post_id
            FROM {$wpdb->posts} 
            WHERE post_type = %s 
            AND post_status = 'publish' 
            AND post_title LIKE %s
            ORDER BY post_date DESC
            LIMIT %d
        ", $post_type, '%' . $wpdb->esc_like($query) . '%', $limit);
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        // Format results
        foreach ($results as &$result) {
            // Find the search mode for this post type
            $mode_key = '';
            foreach ($this->search_modes as $key => $mode) {
                if ($mode['post_type'] === $post_type) {
                    $mode_key = $key;
                    break;
                }
            }
            
            $result['icon'] = isset($this->search_modes[$mode_key]) ? $this->search_modes[$mode_key]['icon'] : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14,2 14,8 20,8"></polyline></svg>';
            $result['category'] = ucfirst(str_replace('sffc_', '', $post_type));
        }
        
        return $results;
    }
    
    /**
     * Search meta fields
     */
    private function search_meta_fields($query, $post_type, $limit) {
        global $wpdb;
        
        // Define searchable meta keys per post type
        $meta_keys = array(
            'sffc_job' => array('sffc_company_name', 'sffc_location', 'sffc_job_category'),
            'sffc_pe_news' => array('news_company', 'news_sector', 'news_deal_type'),
            'sffc_recruiter' => array('company_name', 'specialization', 'location'),
            'sffc_company' => array('company_sector', 'company_type', 'fund_size'),
            'sffc_deal' => array('deal_company', 'deal_sector', 'deal_type')
        );
        
        if (!isset($meta_keys[$post_type])) {
            return array();
        }
        
        $meta_key_list = "'" . implode("','", $meta_keys[$post_type]) . "'";
        
        $sql = $wpdb->prepare("
            SELECT DISTINCT pm.meta_value as text, pm.meta_key as type, p.ID as post_id
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = %s 
            AND p.post_status = 'publish'
            AND pm.meta_key IN ({$meta_key_list})
            AND pm.meta_value LIKE %s
            AND pm.meta_value != ''
            ORDER BY p.post_date DESC
            LIMIT %d
        ", $post_type, '%' . $wpdb->esc_like($query) . '%', $limit);
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        // Format results
        foreach ($results as &$result) {
            $result['icon'] = $this->get_meta_icon($result['type']);
            $result['category'] = $this->get_meta_category($result['type']);
        }
        
        return $results;
    }
    
    /**
     * Get icon for meta field type
     */
    private function get_meta_icon($meta_key) {
        $icons = array(
            'sffc_company_name' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"></path><path d="M5 21V7l8-4v18"></path><path d="M19 21V11l-6-4"></path></svg>',
            'company_name' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"></path><path d="M5 21V7l8-4v18"></path><path d="M19 21V11l-6-4"></path></svg>',
            'sffc_location' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="10" r="3"></circle><path d="M12 21.7c5-4.7 8-9.7 8-11.7 0-4.4-3.6-8-8-8s-8 3.6-8 8c0 2 3 7 8 11.7z"></path></svg>',
            'location' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="10" r="3"></circle><path d="M12 21.7c5-4.7 8-9.7 8-11.7 0-4.4-3.6-8-8-8s-8 3.6-8 8c0 2 3 7 8 11.7z"></path></svg>',
            'sffc_job_category' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>',
            'specialization' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26 12,2"></polygon></svg>',
            'company_sector' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="8" rx="1"></rect><path d="M17 14v7"></path><path d="M7 14v7"></path><path d="M17 3v3"></path><path d="M7 3v3"></path></svg>',
            'deal_type' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>',
            'fund_size' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v6m0 6v6"></path><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>'
        );
        
        return $icons[$meta_key] ?? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14,2 14,8 20,8"></polyline></svg>';
    }
    
    /**
     * Get category for meta field type
     */
    private function get_meta_category($meta_key) {
        $categories = array(
            'sffc_company_name' => 'Company',
            'company_name' => 'Company',
            'sffc_location' => 'Location',
            'location' => 'Location',
            'sffc_job_category' => 'Category',
            'specialization' => 'Specialty',
            'company_sector' => 'Sector',
            'deal_type' => 'Deal Type',
            'fund_size' => 'Fund Size'
        );
        
        return $categories[$meta_key] ?? 'Other';
    }
    
    /**
     * Handle search execution
     */
    public function handle_search_execution() {
        // FIXED: Proper nonce verification for search execution
        check_ajax_referer('sffc_search_nonce', 'nonce');
        
        $query = sanitize_text_field($_POST['q'] ?? '');
        $mode = sanitize_text_field($_POST['mode'] ?? 'jobs');
        $results_page = sanitize_text_field($_POST['results_page'] ?? '');

        if ($mode === 'news') {
            $mode = 'insights';
        }

        if (empty($query)) {
            wp_send_json_error('Empty search query');
            return;
        }
        
        // Build results URL
        $results_url = $this->build_results_url($query, $mode, $results_page);
        
        wp_send_json_success(array(
            'redirect_url' => $results_url,
            'query' => $query,
            'mode' => $mode
        ));
    }
    
    /**
     * Build results URL - SEO-FRIENDLY URLs with fallback
     */
    private function build_results_url($query, $mode, $results_page = '') {
        // Clean and deduplicate query terms
        $cleaned_query = $this->deduplicate_query_terms($query);
        
        // Use SEO-friendly URL structure if SEO permalinks are available
        if (class_exists('SFFC_SEO_Permalinks')) {
            return SFFC_SEO_Permalinks::generate_search_url($cleaned_query, $mode);
        }
        
        // Fallback to traditional query parameter structure
        // Method 1: Use explicitly specified results page
        if (!empty($results_page)) {
            $base_url = get_permalink($results_page);
        } else {
            // Method 2: Auto-detect page with [sffc_pe_search_results] shortcode
            $base_url = $this->find_search_results_page();
        }
        
        if (empty($base_url)) {
            $base_url = home_url('/');
        }

        return add_query_arg(array(
            'q' => urlencode($cleaned_query),
            'mode' => $mode,
            'search' => '1'
        ), $base_url);
    }
    
    /**
     * Deduplicate and clean query terms
     */
    private function deduplicate_query_terms($query) {
        // Basic sanitization
        $query = sanitize_text_field($query);
        $query = trim($query);
        
        if (empty($query)) {
            return '';
        }
        
        // Split by common delimiters and whitespace
        $terms = preg_split('/[\s,+&]+/', $query);
        
        // Remove empty terms and convert to lowercase for comparison
        $terms = array_filter($terms, function($term) {
            return !empty(trim($term));
        });
        
        // Remove duplicates (case-insensitive)
        $unique_terms = array();
        $seen_terms = array();
        
        foreach ($terms as $term) {
            $term_clean = trim($term);
            $term_lower = strtolower($term_clean);
            
            if (!in_array($term_lower, $seen_terms) && !empty($term_clean)) {
                $unique_terms[] = $term_clean;
                $seen_terms[] = $term_lower;
            }
        }
        
        // Remove excessive repetition patterns (like "Top Top Top")
        $final_terms = array();
        $prev_term = '';
        
        foreach ($unique_terms as $term) {
            if (strtolower($term) !== strtolower($prev_term)) {
                $final_terms[] = $term;
                $prev_term = $term;
            }
        }
        
        // Rejoin with single spaces
        return implode(' ', $final_terms);
    }
    
    /**
     * Find the page that contains [sffc_pe_search_results] shortcode
     */
    private function find_search_results_page() {
        // Search for pages containing the shortcode
        $pages = get_posts(array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            's' => 'sffc_pe_search_results' // Simple content search
        ));
        
        foreach ($pages as $page) {
            if (has_shortcode($page->post_content, 'sffc_pe_search_results')) {
                return get_permalink($page->ID);
            }
        }
        
        // Fallback: try to find by content search
        global $wpdb;
        $results_page = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = 'page' 
             AND post_status = 'publish' 
             AND post_content LIKE %s 
             LIMIT 1",
            '%sffc_pe_search_results%'
        ));
        
        if ($results_page) {
            return get_permalink($results_page);
        }
        
        // Final fallback
        return home_url('/search-results/');
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets() {
        global $post;

        if (is_admin() || !is_a($post, 'WP_Post')) {
            return;
        }

        $content = (string) ($post->post_content ?? '');
        $should_enqueue =
            has_shortcode($content, 'sffc_pe_search') ||
            has_shortcode($content, 'sffc_pe_search_compact');

        if (!$should_enqueue) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'sffc-pe-search',
            SFFC_PLUGIN_URL . 'assets/css/pe-search-interface.css',
            array(),
            SFFC_VERSION
        );
        
        // Enqueue autocomplete dropdown fix CSS
        wp_enqueue_style(
            'sffc-autocomplete-dropdown-fix',
            SFFC_PLUGIN_URL . 'assets/css/autocomplete-dropdown-fix.css',
            array('sffc-pe-search'),
            SFFC_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'sffc-pe-search',
            SFFC_PLUGIN_URL . 'assets/js/pe-search-interface.js',
            array('jquery'),
            SFFC_VERSION,
            true
        );
        
        // Enqueue autocomplete dropdown fix JavaScript
        wp_enqueue_script(
            'sffc-autocomplete-dropdown-fix',
            SFFC_PLUGIN_URL . 'assets/js/autocomplete-dropdown-fix.js',
            array('jquery', 'sffc-pe-search'),
            SFFC_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('sffc-pe-search', 'sffc_search', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'plugin_url' => SFFC_PLUGIN_URL,
            'nonce' => wp_create_nonce('sffc_search_nonce'),
            'modes' => $this->search_modes,
            'strings' => array(
                'searching' => 'Searching...',
                'no_results' => 'No suggestions found',
                'try_different' => 'Try a different search term',
                'voice_not_supported' => 'Voice search not supported in this browser'
            )
        ));
    }
    
    /**
     * Get search modes
     */
    public function get_search_modes() {
        return $this->search_modes;
    }
}

// Initialize
SFFC_PE_Search_Interface::get_instance();
