<?php
/**
 * Dynamic Post Type Filters Admin
 * Admin interface for creating filters from custom post types
 * 
 * @package SennaCareers
 * @since 10.20.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Dynamic_Post_Type_Filters_Admin {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Dynamic Post Filters',
            'Post Type Filters',
            'manage_options',
            'sffc-dynamic-filters',
            array($this, 'admin_page'),
            'dashicons-filter',
            26
        );
    }
    
    /**
     * Main admin page
     */
    public function admin_page() {
        $filter_manager = SFFC_Dynamic_Post_Type_Filters::get_instance();
        $filter_configs = $filter_manager->get_filter_configs();
        $available_post_types = $this->get_available_post_types();
        
        // Debug: Show what's in the database
        echo '<!-- DEBUG: Filter configs count: ' . count($filter_configs) . ' -->';
        echo '<!-- DEBUG: Filter configs: ' . print_r($filter_configs, true) . ' -->';
        
        // Force show the create section for debugging
        $show_create_section = empty($filter_configs);
        ?>
        <div class="wrap">
            <h1>Dynamic Post Type Filters</h1>
            <p>Create filters that pull real data from any custom post type and display it in intelligence cards, just like M42's job system.</p>
            
            <?php if ($show_create_section): ?>
            <div class="create-defaults-section">
                <h3>🎯 Setup Default ZENA Filters</h3>
                <p>No filters found! Click the button below to create the default ZENA filters (DEALS, PEOPLE, FUNDS, MARKETS, JOBS) with proper post type mappings.</p>
                <button type="button" class="create-defaults-btn" id="create-default-filters">Create Default Filters</button>
                <div id="defaults-status"></div>
            </div>
            <?php endif; ?>
            
            <!-- Always show this section for debugging -->
            <div class="create-defaults-section" style="background: #e8f4fd; border-color: #2196f3;">
                <h3>🔧 Debug & Manual Creation</h3>
                <p><strong>Current filter count:</strong> <?php echo count($filter_configs); ?></p>
                <p><strong>Available post types:</strong> <?php echo implode(', ', array_keys($available_post_types)); ?></p>
                <button type="button" class="create-defaults-btn" id="create-default-filters-force">Force Create Default Filters</button>
                <button type="button" class="create-defaults-btn" style="background: #dc3545; margin-left: 10px;" id="clear-all-filters">Clear All Filters</button>
                <div id="debug-status"></div>
            </div>
            
            <div class="sffc-admin-container">
                <div class="sffc-admin-main">
                    <div class="sffc-card">
                        <div class="sffc-card-header">
                            <h2>Post Type Filters</h2>
                            <button type="button" class="button button-primary" id="add-post-type-filter">Add New Filter</button>
                        </div>
                        
                        <?php if (!empty($filter_configs)): ?>
                        <div class="bulk-actions-bar" style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">
                            <label>
                                <input type="checkbox" id="select-all-filters"> Select All
                            </label>
                            <button type="button" class="button" id="bulk-delete-filters" disabled>Delete Selected</button>
                            <button type="button" class="button" id="bulk-activate-filters" disabled>Activate Selected</button>
                            <button type="button" class="button" id="bulk-deactivate-filters" disabled>Deactivate Selected</button>
                            <span class="bulk-actions-info" style="margin-left: 15px; color: #666;"></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="sffc-filters-list">
                            <?php if (empty($filter_configs)): ?>
                                <div class="no-items">
                                    <p>No post type filters created yet.</p>
                                    <p><a href="#" id="add-first-filter" class="button button-primary">Create Your First Filter</a></p>
                                </div>
                            <?php else: ?>
                                <table class="wp-list-table widefat fixed striped" id="filters-table">
                                    <thead>
                                        <tr>
                                            <th scope="col" style="width: 30px;">Order</th>
                                            <th scope="col">Filter</th>
                                            <th scope="col">Post Type</th>
                                            <th scope="col">Posts Count</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="filters-sortable">
                                        <?php 
                                        // Sort by priority
                                        uasort($filter_configs, function($a, $b) {
                                            return ($a['priority'] ?? 999) - ($b['priority'] ?? 999);
                                        });
                                        
                                        foreach ($filter_configs as $key => $config): ?>
                                            <tr data-filter-key="<?php echo esc_attr($key); ?>" class="filter-row">
                                                <td class="reorder-handle" style="cursor: move; text-align: center;">
                                                    <span class="dashicons dashicons-move" title="Drag to reorder"></span>
                                                    <small style="display: block; color: #666;"><?php echo esc_html($config['priority'] ?? 999); ?></small>
                                                </td>
                                                <td>
                                                    <strong>
                                                        <?php if (strpos($config['icon'], '<svg') !== false): ?>
                                                            <?php echo wp_kses($config['icon'], array(
                                                                'svg' => array('width' => array(), 'height' => array(), 'viewBox' => array(), 'fill' => array()),
                                                                'path' => array('d' => array())
                                                            )); ?>
                                                        <?php else: ?>
                                                            <?php echo esc_html($config['icon']); ?>
                                                        <?php endif; ?>
                                                        <?php echo esc_html($config['label']); ?>
                                                    </strong>
                                                    <div class="row-actions">
                                                        <span class="color-indicator" style="background-color: <?php echo esc_attr($config['color']); ?>"></span>
                                                        Priority: <?php echo esc_html($config['priority']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <code><?php echo esc_html($config['post_type']); ?></code>
                                                    <?php if (!post_type_exists($config['post_type'])): ?>
                                                        <br><span class="error-text">⚠️ Post type doesn't exist</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $post_count = wp_count_posts($config['post_type']);
                                                    $published_count = isset($post_count->publish) ? $post_count->publish : 0;
                                                    echo $published_count . ' published';
                                                    ?>
                                                    <?php if ($published_count > 0): ?>
                                                        <br><a href="<?php echo admin_url('edit.php?post_type=' . $config['post_type']); ?>" target="_blank">View Posts</a>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge <?php echo $config['active'] ? 'active' : 'inactive'; ?>">
                                                        <?php echo $config['active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <input type="checkbox" class="filter-checkbox" value="<?php echo esc_attr($key); ?>">
                                                    <button type="button" class="button button-small edit-filter" data-filter-key="<?php echo esc_attr($key); ?>" title="Edit filter">Edit</button>
                                                    <button type="button" class="button button-small test-filter" data-filter-key="<?php echo esc_attr($key); ?>" title="Test filter">Test</button>
                                                    <button type="button" class="button button-small button-link-delete delete-filter" data-filter-key="<?php echo esc_attr($key); ?>" title="Delete filter">Delete</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="sffc-admin-sidebar">
                    <div class="sffc-card">
                        <h3>How It Works</h3>
                        <ol>
                            <li><strong>Create Custom Post Type:</strong> First ensure your custom post type exists (e.g., 'markets', 'deals')</li>
                            <li><strong>Add Posts:</strong> Create posts in that post type with custom fields</li>
                            <li><strong>Configure Filter:</strong> Map post fields to card elements</li>
                            <li><strong>Test:</strong> Use test button to see how cards will look</li>
                        </ol>
                    </div>
                    
                    <div class="sffc-card">
                        <h3>Available Post Types</h3>
                        <?php if (empty($available_post_types)): ?>
                            <p><em>No custom post types found.</em></p>
                            <p><a href="https://developer.wordpress.org/plugins/post-types/" target="_blank">Learn how to create custom post types</a></p>
                        <?php else: ?>
                            <ul>
                                <?php foreach ($available_post_types as $post_type => $label): ?>
                                    <li>
                                        <strong><?php echo esc_html($label); ?></strong><br>
                                        <code><?php echo esc_html($post_type); ?></code>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    
                    <div class="sffc-card">
                        <h3>Field Mapping</h3>
                        <p><strong>Post Fields:</strong></p>
                        <ul>
                            <li><code>post_title</code> - Post title</li>
                            <li><code>post_excerpt</code> - Post excerpt</li>
                            <li><code>post_content</code> - Post content</li>
                        </ul>
                        <p><strong>Meta Fields:</strong> Use any custom field key</p>
                        <p><em>Example:</em> <code>market_value</code>, <code>deal_amount</code>, <code>fund_size</code></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filter Configuration Modal -->
        <div id="post-type-filter-modal" class="sffc-modal" style="display: none;">
            <div class="sffc-modal-content large">
                <div class="sffc-modal-header">
                    <h3 id="modal-title">Add Post Type Filter</h3>
                    <button type="button" class="sffc-modal-close">&times;</button>
                </div>
                
                <form id="post-type-filter-form">
                    <div class="sffc-form-columns">
                        <div class="sffc-form-column">
                            <h4>Basic Configuration</h4>
                            
                            <div class="sffc-form-row">
                                <label for="filter-key">Filter Key:</label>
                                <input type="text" id="filter-key" name="filter_key" placeholder="e.g., markets" required>
                                <small>Unique identifier (lowercase, no spaces)</small>
                            </div>
                            
                            <div class="sffc-form-row">
                                <label for="filter-label">Display Label:</label>
                                <input type="text" id="filter-label" name="label" placeholder="e.g., Market Intelligence" required>
                            </div>
                            
                            <div class="sffc-form-row">
                                <label for="post-type">Post Type:</label>
                                <select id="post-type" name="post_type" required>
                                    <option value="">Select post type...</option>
                                    <?php foreach ($available_post_types as $post_type => $label): ?>
                                        <option value="<?php echo esc_attr($post_type); ?>"><?php echo esc_html($label); ?> (<?php echo esc_html($post_type); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="sffc-form-row">
                                <label for="filter-icon">Icon:</label>
                                <div class="icon-input-container">
                                    <input type="text" id="filter-icon" name="icon" placeholder='SVG or text like "M&A"' value='<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>'>
                                    <button type="button" class="button icon-picker-btn" id="open-icon-picker">Choose Icon</button>
                                </div>
                                <div class="icon-preview" id="icon-preview">
                                    <span class="preview-label">Preview:</span>
                                    <span class="preview-icon"></span>
                                </div>
                                <small>Use SVG code, short text like "M&A", or choose from icon library</small>
                            </div>
                            
                            <div class="sffc-form-row">
                                <label for="filter-color">Color:</label>
                                <input type="text" id="filter-color" name="color" class="color-picker" value="#3b82f6">
                            </div>
                            
                            <div class="sffc-form-row">
                                <label for="filter-priority">Priority:</label>
                                <input type="number" id="filter-priority" name="priority" value="10" min="1" max="100">
                                <small>Lower numbers appear first</small>
                            </div>
                            
                            <div class="sffc-form-row">
                                <label>
                                    <input type="checkbox" id="filter-active" name="active" checked>
                                    Active (visible in filters)
                                </label>
                            </div>
                        </div>
                        
                        <div class="sffc-form-column">
                            <h4>Field Mapping</h4>
                            <p><small>Map post/meta fields to card elements:</small></p>
                            
                            <div class="sffc-form-row">
                                <label for="title-field">Title Field:</label>
                                <input type="text" id="title-field" name="title_field" placeholder="post_title" value="post_title">
                                <small>Field for card headline</small>
                            </div>
                            
                            <div class="sffc-form-row">
                                <label for="summary-field">Summary Field:</label>
                                <input type="text" id="summary-field" name="summary_field" placeholder="post_excerpt" value="post_excerpt">
                                <small>Field for card description</small>
                            </div>
                            
                            <div class="sffc-form-row">
                                <label for="metric-field">Metric Value Field:</label>
                                <input type="text" id="metric-field" name="metric_field" placeholder="e.g., market_value">
                                <small>Meta field for main metric</small>
                            </div>
                            
                            <div class="sffc-form-row">
                                <label for="change-field">Change Field:</label>
                                <input type="text" id="change-field" name="change_field" placeholder="e.g., market_change">
                                <small>Meta field for change percentage</small>
                            </div>
                            
                            <div class="sffc-form-row">
                                <label for="category-field">Category Field:</label>
                                <input type="text" id="category-field" name="category_field" placeholder="e.g., market_category">
                                <small>Meta field for category badge</small>
                            </div>
                            
                            <div class="sffc-form-row">
                                <label for="source-field">Source Field:</label>
                                <input type="text" id="source-field" name="source_field" placeholder="e.g., data_source">
                                <small>Meta field for data source</small>
                            </div>
                            
                            <div class="sffc-form-row">
                                <label for="required-meta">Required Meta Fields:</label>
                                <input type="text" id="required-meta" name="required_meta" placeholder="field1,field2,field3">
                                <small>Comma-separated list of required meta fields</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="sffc-form-actions">
                        <button type="submit" class="button button-primary">Save Filter Configuration</button>
                        <button type="button" class="button button-secondary sffc-modal-close">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Test Results Modal -->
        <div id="test-results-modal" class="sffc-modal" style="display: none;">
            <div class="sffc-modal-content large">
                <div class="sffc-modal-header">
                    <h3>Filter Test Results</h3>
                    <button type="button" class="sffc-modal-close">&times;</button>
                </div>
                
                <div id="test-results-content">
                    <!-- Test results will be loaded here -->
                </div>
                
                <div class="sffc-form-actions">
                    <button type="button" class="button button-secondary sffc-modal-close">Close</button>
                </div>
            </div>
        </div>
        
        <!-- Icon Picker Modal -->
        <div id="icon-picker-modal" class="sffc-modal" style="display: none;">
            <div class="sffc-modal-content large">
                <div class="sffc-modal-header">
                    <h3>Choose an Icon</h3>
                    <button type="button" class="sffc-modal-close">&times;</button>
                </div>
                
                <div class="icon-picker-content">
                    <div class="icon-picker-tabs">
                        <button type="button" class="icon-tab active" data-tab="predefined">Icon Library</button>
                        <button type="button" class="icon-tab" data-tab="custom">Custom SVG</button>
                        <button type="button" class="icon-tab" data-tab="text">Text Icons</button>
                    </div>
                    
                    <div class="icon-tab-content" id="tab-predefined">
                        <h4>Business & Finance Icons</h4>
                        <div class="icon-grid" data-category="business">
                            <div class="icon-option" data-icon='<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>' title="Star (Deals)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                <span>Deals</span>
                            </div>
                            <div class="icon-option" data-icon='<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6h-6z"/></svg>' title="Chart (Markets)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6h-6z"/></svg>
                                <span>Markets</span>
                            </div>
                            <div class="icon-option" data-icon='<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20 6h-2.18c.11-.31.18-.65.18-1a3 3 0 0 0-6 0c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2z"/></svg>' title="Briefcase (Jobs)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20 6h-2.18c.11-.31.18-.65.18-1a3 3 0 0 0-6 0c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2z"/></svg>
                                <span>Jobs</span>
                            </div>
                            <div class="icon-option" data-icon='<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M16 4c0-1.11.89-2 2-2s2 .89 2 2-.89 2-2 2-2-.89-2-2zM4 18v-4h3v-3c0-1.1.9-2 2-2h2c1.1 0 2 .9 2 2v3h3v4H4zm12-5.5c.83 0 1.5-.67 1.5-1.5s-.67-1.5-1.5-1.5-1.5.67-1.5 1.5.67 1.5 1.5 1.5zm3 .5h-2v7h2v-7z"/></svg>' title="People (Team)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M16 4c0-1.11.89-2 2-2s2 .89 2 2-.89 2-2 2-2-.89-2-2zM4 18v-4h3v-3c0-1.1.9-2 2-2h2c1.1 0 2 .9 2 2v3h3v4H4zm12-5.5c.83 0 1.5-.67 1.5-1.5s-.67-1.5-1.5-1.5-1.5.67-1.5 1.5.67 1.5 1.5 1.5zm3 .5h-2v7h2v-7z"/></svg>
                                <span>People</span>
                            </div>
                            <div class="icon-option" data-icon='<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/></svg>' title="Document (Funds)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/></svg>
                                <span>Funds</span>
                            </div>
                            <div class="icon-option" data-icon='<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M7 4V2a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v2h4a1 1 0 0 1 0 2h-1v14a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V6H3a1 1 0 0 1 0-2h4z"/></svg>' title="Bank (Banking)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M7 4V2a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v2h4a1 1 0 0 1 0 2h-1v14a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V6H3a1 1 0 0 1 0-2h4z"/></svg>
                                <span>Banking</span>
                            </div>
                            <div class="icon-option" data-icon='<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l-5.5 9h11L12 2z"/></svg>' title="Triangle (Investment)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l-5.5 9h11L12 2z"/></svg>
                                <span>Investment</span>
                            </div>
                            <div class="icon-option" data-icon='<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2z"/></svg>' title="Circle (General)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2z"/></svg>
                                <span>General</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="icon-tab-content" id="tab-custom" style="display: none;">
                        <h4>Paste Your Custom SVG</h4>
                        <textarea id="custom-svg-input" placeholder="Paste your SVG code here...&#10;&#10;Example:&#10;&lt;svg width=&quot;16&quot; height=&quot;16&quot; viewBox=&quot;0 0 24 24&quot; fill=&quot;currentColor&quot;&gt;&#10;  &lt;path d=&quot;M12 2l3.09 6.26L22 9.27...&quot;/&gt;&#10;&lt;/svg&gt;" rows="8"></textarea>
                        <div class="custom-svg-preview">
                            <span>Preview:</span>
                            <div id="custom-svg-preview-area"></div>
                        </div>
                        <button type="button" class="button" id="use-custom-svg">Use Custom SVG</button>
                    </div>
                    
                    <div class="icon-tab-content" id="tab-text" style="display: none;">
                        <h4>Text-Based Icons</h4>
                        <div class="text-icon-grid">
                            <div class="text-icon-option" data-icon="M&A">M&A</div>
                            <div class="text-icon-option" data-icon="PE">PE</div>
                            <div class="text-icon-option" data-icon="VC">VC</div>
                            <div class="text-icon-option" data-icon="IB">IB</div>
                            <div class="text-icon-option" data-icon="AM">AM</div>
                            <div class="text-icon-option" data-icon="HF">HF</div>
                            <div class="text-icon-option" data-icon="RE">RE</div>
                            <div class="text-icon-option" data-icon="DATA">DATA</div>
                            <div class="text-icon-option" data-icon="NEWS">NEWS</div>
                            <div class="text-icon-option" data-icon="ALL">ALL</div>
                        </div>
                        <div class="custom-text-input">
                            <input type="text" id="custom-text-input" placeholder="Enter custom text (2-4 characters work best)" maxlength="6">
                            <button type="button" class="button" id="use-custom-text">Use Custom Text</button>
                        </div>
                    </div>
                </div>
                
                <div class="sffc-form-actions">
                    <button type="button" class="button button-secondary sffc-modal-close">Cancel</button>
                </div>
            </div>
        </div>
        
        <style>
            .sffc-admin-container { display: flex; gap: 20px; margin-top: 20px; }
            .sffc-admin-main { flex: 1; }
            .sffc-admin-sidebar { width: 300px; }
            .sffc-card { background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-bottom: 20px; }
            .sffc-card h3, .sffc-card h4 { margin-top: 0; color: #23282d; }
            .sffc-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
            .color-indicator { display: inline-block; width: 12px; height: 12px; border-radius: 2px; margin-right: 5px; }
            .status-badge { padding: 3px 6px; border-radius: 3px; font-size: 11px; }
            .status-badge.active { background: #00a32a; color: white; }
            .status-badge.inactive { background: #dba617; color: white; }
            .error-text { color: #dc3232; font-size: 12px; }
            .no-items { text-align: center; padding: 40px; color: #666; }
            .sffc-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center; }
            .sffc-modal-content { background: white; width: 700px; max-width: 90vw; max-height: 90vh; border-radius: 4px; overflow: hidden; }
            .sffc-modal-content.large { width: 900px; }
            .sffc-modal-header { padding: 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; background: #f9f9f9; }
            .sffc-modal-close { background: none; border: none; font-size: 20px; cursor: pointer; }
            .sffc-form-columns { display: flex; gap: 30px; padding: 20px; }
            .sffc-form-column { flex: 1; }
            .sffc-form-row { margin-bottom: 15px; }
            .sffc-form-row label { display: block; margin-bottom: 5px; font-weight: 600; }
            .sffc-form-row input, .sffc-form-row select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
            .sffc-form-row small { color: #666; font-size: 12px; margin-top: 3px; display: block; }
            .sffc-form-actions { padding: 20px; border-top: 1px solid #ddd; text-align: right; background: #f9f9f9; }
            #test-results-content { padding: 20px; max-height: 60vh; overflow-y: auto; }
        </style>
        <?php
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
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin page
        if ($hook !== 'toplevel_page_sffc-dynamic-filters') {
            return;
        }
        
        // Enqueue WordPress color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Enqueue jQuery UI for sortable
        wp_enqueue_script('jquery-ui-sortable');
        
        // Get plugin URL from the main plugin file path
        $plugin_url = plugin_dir_url(dirname(__FILE__));
        
        // Enqueue our admin CSS
        wp_enqueue_style(
            'sffc-dynamic-filters-admin',
            $plugin_url . 'admin/css/dynamic-post-type-filters-admin.css',
            array(),
            '10.20.0'
        );
        
        // Enqueue our admin JavaScript
        wp_enqueue_script(
            'sffc-dynamic-filters-admin',
            $plugin_url . 'admin/js/dynamic-post-type-filters-admin.js',
            array('jquery', 'wp-color-picker', 'jquery-ui-sortable'),
            '10.20.0',
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script('sffc-dynamic-filters-admin', 'sffc_dynamic_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_dynamic_admin_nonce'),
            'plugin_url' => $plugin_url
        ));
    }
}

// Initialize
SFFC_Dynamic_Post_Type_Filters_Admin::get_instance();