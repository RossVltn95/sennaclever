<?php
/**
 * Market Settings - Admin interface for Market Analysis Mode configuration
 * 
 * @package SennaCareers
 * @subpackage Admin
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Market_Settings {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Settings options
     */
    private $options;
    
    /**
     * Get instance
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
        $this->options = get_option('sffc_market_settings', $this->get_defaults());
        $this->init_hooks();
    }
    
    /**
     * Get default settings
     */
    private function get_defaults() {
        return array(
            // Feed Settings
            'feeds' => array(
                'bloomberg' => array(
                    'enabled' => true,
                    'url' => 'https://feeds.bloomberg.com/markets/news.rss',
                    'refresh_interval' => 15
                ),
                'ft' => array(
                    'enabled' => true,
                    'url' => 'https://www.ft.com/markets?format=rss',
                    'refresh_interval' => 15
                ),
                'wsj' => array(
                    'enabled' => true,
                    'url' => 'https://feeds.a.dj.com/rss/RSSMarketsMain.xml',
                    'refresh_interval' => 15
                ),
                'custom_feeds' => array()
            ),
            
            // Analysis Settings
            'analysis' => array(
                'enable_why_analysis' => true,
                'enable_career_impact' => true,
                'enable_knowledge_checks' => true,
                'analysis_depth' => 'deep', // surface, medium, deep
                'visual_complexity' => 'advanced', // basic, standard, advanced
                'max_visuals_per_response' => 3
            ),
            
            // Claude API Settings
            'claude_api' => array(
                'model' => 'claude-3-opus-20240229',
                'max_tokens' => 4000,
                'temperature' => 0.7,
                'enable_caching' => true,
                'cache_duration' => 3600
            ),
            
            // Performance Settings
            'performance' => array(
                'feed_cache_duration' => 900, // 15 minutes
                'analysis_cache_duration' => 3600, // 1 hour
                'max_feed_items' => 10,
                'enable_background_updates' => true,
                'cleanup_old_data_days' => 30
            ),
            
            // Display Settings
            'display' => array(
                'show_source_attribution' => true,
                'show_timestamp' => true,
                'show_confidence_scores' => false,
                'enable_interactive_visuals' => true,
                'theme' => 'premium' // premium, professional, minimal
            )
        );
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX handlers
        add_action('wp_ajax_sffc_save_market_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_sffc_test_feed', array($this, 'ajax_test_feed'));
        add_action('wp_ajax_sffc_refresh_market_data', array($this, 'ajax_refresh_data'));
        add_action('wp_ajax_sffc_clear_market_cache', array($this, 'ajax_clear_cache'));
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'sffc_market_settings_group',
            'sffc_market_settings',
            array($this, 'sanitize_settings')
        );
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Sanitize feed settings
        if (isset($input['feeds'])) {
            foreach ($input['feeds'] as $feed_key => $feed_data) {
                if ($feed_key === 'custom_feeds') {
                    $sanitized['feeds']['custom_feeds'] = $this->sanitize_custom_feeds($feed_data);
                } else {
                    $sanitized['feeds'][$feed_key] = array(
                        'enabled' => isset($feed_data['enabled']) ? (bool) $feed_data['enabled'] : false,
                        'url' => esc_url_raw($feed_data['url']),
                        'refresh_interval' => absint($feed_data['refresh_interval'])
                    );
                }
            }
        }
        
        // Sanitize analysis settings
        if (isset($input['analysis'])) {
            $sanitized['analysis'] = array(
                'enable_why_analysis' => isset($input['analysis']['enable_why_analysis']),
                'enable_career_impact' => isset($input['analysis']['enable_career_impact']),
                'enable_knowledge_checks' => isset($input['analysis']['enable_knowledge_checks']),
                'analysis_depth' => sanitize_text_field($input['analysis']['analysis_depth']),
                'visual_complexity' => sanitize_text_field($input['analysis']['visual_complexity']),
                'max_visuals_per_response' => absint($input['analysis']['max_visuals_per_response'])
            );
        }
        
        // Sanitize Claude API settings
        if (isset($input['claude_api'])) {
            $sanitized['claude_api'] = array(
                'model' => sanitize_text_field($input['claude_api']['model']),
                'max_tokens' => absint($input['claude_api']['max_tokens']),
                'temperature' => floatval($input['claude_api']['temperature']),
                'enable_caching' => isset($input['claude_api']['enable_caching']),
                'cache_duration' => absint($input['claude_api']['cache_duration'])
            );
        }
        
        // Sanitize performance settings
        if (isset($input['performance'])) {
            $sanitized['performance'] = array(
                'feed_cache_duration' => absint($input['performance']['feed_cache_duration']),
                'analysis_cache_duration' => absint($input['performance']['analysis_cache_duration']),
                'max_feed_items' => absint($input['performance']['max_feed_items']),
                'enable_background_updates' => isset($input['performance']['enable_background_updates']),
                'cleanup_old_data_days' => absint($input['performance']['cleanup_old_data_days'])
            );
        }
        
        // Sanitize display settings
        if (isset($input['display'])) {
            $sanitized['display'] = array(
                'show_source_attribution' => isset($input['display']['show_source_attribution']),
                'show_timestamp' => isset($input['display']['show_timestamp']),
                'show_confidence_scores' => isset($input['display']['show_confidence_scores']),
                'enable_interactive_visuals' => isset($input['display']['enable_interactive_visuals']),
                'theme' => sanitize_text_field($input['display']['theme'])
            );
        }
        
        return $sanitized;
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Market Analysis Mode Settings</h1>
            
            <div class="sffc-settings-header">
                <p>Configure how MENA Careers analyzes and presents market intelligence.</p>
            </div>
            
            <form method="post" action="options.php" id="sffc-market-settings-form">
                <?php settings_fields('sffc_market_settings_group'); ?>
                
                <!-- Tabs -->
                <h2 class="nav-tab-wrapper">
                    <a href="#feeds" class="nav-tab nav-tab-active">Data Feeds</a>
                    <a href="#analysis" class="nav-tab">Analysis</a>
                    <a href="#api" class="nav-tab">Claude API</a>
                    <a href="#performance" class="nav-tab">Performance</a>
                    <a href="#display" class="nav-tab">Display</a>
                    <a href="#diagnostics" class="nav-tab">Diagnostics</a>
                </h2>
                
                <!-- Feed Settings Tab -->
                <div id="feeds" class="tab-content">
                    <h3>Market Data Feeds</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Bloomberg</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sffc_market_settings[feeds][bloomberg][enabled]" 
                                           <?php checked($this->options['feeds']['bloomberg']['enabled']); ?> />
                                    Enable Bloomberg Feed
                                </label>
                                <br>
                                <input type="url" name="sffc_market_settings[feeds][bloomberg][url]" 
                                       value="<?php echo esc_attr($this->options['feeds']['bloomberg']['url']); ?>" 
                                       class="regular-text" />
                                <br>
                                <label>
                                    Refresh every 
                                    <input type="number" name="sffc_market_settings[feeds][bloomberg][refresh_interval]" 
                                           value="<?php echo esc_attr($this->options['feeds']['bloomberg']['refresh_interval']); ?>" 
                                           class="small-text" /> minutes
                                </label>
                                <button type="button" class="button test-feed" data-feed="bloomberg">Test Feed</button>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Financial Times</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sffc_market_settings[feeds][ft][enabled]" 
                                           <?php checked($this->options['feeds']['ft']['enabled']); ?> />
                                    Enable FT Feed
                                </label>
                                <br>
                                <input type="url" name="sffc_market_settings[feeds][ft][url]" 
                                       value="<?php echo esc_attr($this->options['feeds']['ft']['url']); ?>" 
                                       class="regular-text" />
                                <br>
                                <label>
                                    Refresh every 
                                    <input type="number" name="sffc_market_settings[feeds][ft][refresh_interval]" 
                                           value="<?php echo esc_attr($this->options['feeds']['ft']['refresh_interval']); ?>" 
                                           class="small-text" /> minutes
                                </label>
                                <button type="button" class="button test-feed" data-feed="ft">Test Feed</button>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Wall Street Journal</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sffc_market_settings[feeds][wsj][enabled]" 
                                           <?php checked($this->options['feeds']['wsj']['enabled']); ?> />
                                    Enable WSJ Feed
                                </label>
                                <br>
                                <input type="url" name="sffc_market_settings[feeds][wsj][url]" 
                                       value="<?php echo esc_attr($this->options['feeds']['wsj']['url']); ?>" 
                                       class="regular-text" />
                                <br>
                                <label>
                                    Refresh every 
                                    <input type="number" name="sffc_market_settings[feeds][wsj][refresh_interval]" 
                                           value="<?php echo esc_attr($this->options['feeds']['wsj']['refresh_interval']); ?>" 
                                           class="small-text" /> minutes
                                </label>
                                <button type="button" class="button test-feed" data-feed="wsj">Test Feed</button>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Custom Feeds</th>
                            <td>
                                <div id="custom-feeds-container">
                                    <p class="description">Add custom RSS/XML feeds for specific markets or sources.</p>
                                    <button type="button" class="button" id="add-custom-feed">Add Custom Feed</button>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Analysis Settings Tab -->
                <div id="analysis" class="tab-content" style="display:none;">
                    <h3>Analysis Configuration</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Analysis Features</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sffc_market_settings[analysis][enable_why_analysis]" 
                                           <?php checked($this->options['analysis']['enable_why_analysis']); ?> />
                                    Enable WHY Analysis (Deep causality chains)
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="sffc_market_settings[analysis][enable_career_impact]" 
                                           <?php checked($this->options['analysis']['enable_career_impact']); ?> />
                                    Enable Career Impact Analysis
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="sffc_market_settings[analysis][enable_knowledge_checks]" 
                                           <?php checked($this->options['analysis']['enable_knowledge_checks']); ?> />
                                    Enable Knowledge Check Questions
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Analysis Depth</th>
                            <td>
                                <select name="sffc_market_settings[analysis][analysis_depth]">
                                    <option value="surface" <?php selected($this->options['analysis']['analysis_depth'], 'surface'); ?>>
                                        Surface (Quick insights)
                                    </option>
                                    <option value="medium" <?php selected($this->options['analysis']['analysis_depth'], 'medium'); ?>>
                                        Medium (Balanced analysis)
                                    </option>
                                    <option value="deep" <?php selected($this->options['analysis']['analysis_depth'], 'deep'); ?>>
                                        Deep (Complete causality chains)
                                    </option>
                                </select>
                                <p class="description">Deeper analysis provides more insights but takes longer.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Visual Complexity</th>
                            <td>
                                <select name="sffc_market_settings[analysis][visual_complexity]">
                                    <option value="basic" <?php selected($this->options['analysis']['visual_complexity'], 'basic'); ?>>
                                        Basic (Simple charts)
                                    </option>
                                    <option value="standard" <?php selected($this->options['analysis']['visual_complexity'], 'standard'); ?>>
                                        Standard (Mixed visuals)
                                    </option>
                                    <option value="advanced" <?php selected($this->options['analysis']['visual_complexity'], 'advanced'); ?>>
                                        Advanced (Full visual library)
                                    </option>
                                </select>
                                <p class="description">Advanced visuals include all 40 component types.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Max Visuals per Response</th>
                            <td>
                                <input type="number" name="sffc_market_settings[analysis][max_visuals_per_response]" 
                                       value="<?php echo esc_attr($this->options['analysis']['max_visuals_per_response']); ?>" 
                                       min="1" max="5" class="small-text" />
                                <p class="description">Number of visual components to include in each response.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Claude API Settings Tab -->
                <div id="api" class="tab-content" style="display:none;">
                    <h3>Claude API Configuration</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Model</th>
                            <td>
                                <select name="sffc_market_settings[claude_api][model]">
                                    <option value="claude-3-opus-20240229" <?php selected($this->options['claude_api']['model'], 'claude-3-opus-20240229'); ?>>
                                        Claude 3 Opus (Most capable)
                                    </option>
                                    <option value="claude-3-sonnet-20240229" <?php selected($this->options['claude_api']['model'], 'claude-3-sonnet-20240229'); ?>>
                                        Claude 3 Sonnet (Balanced)
                                    </option>
                                    <option value="claude-3-haiku-20240307" <?php selected($this->options['claude_api']['model'], 'claude-3-haiku-20240307'); ?>>
                                        Claude 3 Haiku (Fastest)
                                    </option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Max Tokens</th>
                            <td>
                                <input type="number" name="sffc_market_settings[claude_api][max_tokens]" 
                                       value="<?php echo esc_attr($this->options['claude_api']['max_tokens']); ?>" 
                                       min="100" max="8000" class="small-text" />
                                <p class="description">Maximum response length (4000 recommended).</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Temperature</th>
                            <td>
                                <input type="number" name="sffc_market_settings[claude_api][temperature]" 
                                       value="<?php echo esc_attr($this->options['claude_api']['temperature']); ?>" 
                                       min="0" max="1" step="0.1" class="small-text" />
                                <p class="description">0 = Deterministic, 1 = Creative (0.7 recommended).</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Caching</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sffc_market_settings[claude_api][enable_caching]" 
                                           <?php checked($this->options['claude_api']['enable_caching']); ?> />
                                    Enable response caching
                                </label>
                                <br>
                                <label>
                                    Cache duration: 
                                    <input type="number" name="sffc_market_settings[claude_api][cache_duration]" 
                                           value="<?php echo esc_attr($this->options['claude_api']['cache_duration']); ?>" 
                                           class="small-text" /> seconds
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Performance Settings Tab -->
                <div id="performance" class="tab-content" style="display:none;">
                    <h3>Performance Optimization</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Cache Settings</th>
                            <td>
                                <label>
                                    Feed cache duration: 
                                    <input type="number" name="sffc_market_settings[performance][feed_cache_duration]" 
                                           value="<?php echo esc_attr($this->options['performance']['feed_cache_duration']); ?>" 
                                           class="small-text" /> seconds
                                </label>
                                <br>
                                <label>
                                    Analysis cache duration: 
                                    <input type="number" name="sffc_market_settings[performance][analysis_cache_duration]" 
                                           value="<?php echo esc_attr($this->options['performance']['analysis_cache_duration']); ?>" 
                                           class="small-text" /> seconds
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Data Limits</th>
                            <td>
                                <label>
                                    Max feed items to process: 
                                    <input type="number" name="sffc_market_settings[performance][max_feed_items]" 
                                           value="<?php echo esc_attr($this->options['performance']['max_feed_items']); ?>" 
                                           class="small-text" />
                                </label>
                                <br>
                                <label>
                                    Clean up data older than: 
                                    <input type="number" name="sffc_market_settings[performance][cleanup_old_data_days]" 
                                           value="<?php echo esc_attr($this->options['performance']['cleanup_old_data_days']); ?>" 
                                           class="small-text" /> days
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Background Processing</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sffc_market_settings[performance][enable_background_updates]" 
                                           <?php checked($this->options['performance']['enable_background_updates']); ?> />
                                    Enable background feed updates
                                </label>
                                <p class="description">Updates feeds automatically via cron jobs.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Display Settings Tab -->
                <div id="display" class="tab-content" style="display:none;">
                    <h3>Display Preferences</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Information Display</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sffc_market_settings[display][show_source_attribution]" 
                                           <?php checked($this->options['display']['show_source_attribution']); ?> />
                                    Show source attribution
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="sffc_market_settings[display][show_timestamp]" 
                                           <?php checked($this->options['display']['show_timestamp']); ?> />
                                    Show timestamps
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="sffc_market_settings[display][show_confidence_scores]" 
                                           <?php checked($this->options['display']['show_confidence_scores']); ?> />
                                    Show confidence scores
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="sffc_market_settings[display][enable_interactive_visuals]" 
                                           <?php checked($this->options['display']['enable_interactive_visuals']); ?> />
                                    Enable interactive visuals
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Theme</th>
                            <td>
                                <select name="sffc_market_settings[display][theme]">
                                    <option value="premium" <?php selected($this->options['display']['theme'], 'premium'); ?>>
                                        Premium (Cream & Gold)
                                    </option>
                                    <option value="professional" <?php selected($this->options['display']['theme'], 'professional'); ?>>
                                        Professional (Navy & White)
                                    </option>
                                    <option value="minimal" <?php selected($this->options['display']['theme'], 'minimal'); ?>>
                                        Minimal (Clean & Simple)
                                    </option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Diagnostics Tab -->
                <div id="diagnostics" class="tab-content" style="display:none;">
                    <h3>System Diagnostics</h3>
                    
                    <div class="diagnostic-section">
                        <h4>Feed Status</h4>
                        <div id="feed-status-container">
                            <button type="button" class="button" id="check-all-feeds">Check All Feeds</button>
                            <div id="feed-status-results"></div>
                        </div>
                    </div>
                    
                    <div class="diagnostic-section">
                        <h4>Cache Management</h4>
                        <button type="button" class="button" id="clear-feed-cache">Clear Feed Cache</button>
                        <button type="button" class="button" id="clear-analysis-cache">Clear Analysis Cache</button>
                        <button type="button" class="button button-primary" id="clear-all-cache">Clear All Cache</button>
                        <div id="cache-status"></div>
                    </div>
                    
                    <div class="diagnostic-section">
                        <h4>Market Data</h4>
                        <button type="button" class="button" id="refresh-market-data">Refresh Market Data</button>
                        <button type="button" class="button" id="analyze-top-stories">Analyze Top Stories</button>
                        <div id="market-data-status"></div>
                    </div>
                    
                    <div class="diagnostic-section">
                        <h4>API Usage</h4>
                        <div id="api-usage-stats"></div>
                    </div>
                </div>
                
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        
        <style>
            .nav-tab-wrapper { margin-bottom: 20px; }
            .tab-content { background: white; padding: 20px; border: 1px solid #ccd0d4; }
            .diagnostic-section { margin-bottom: 30px; padding: 20px; background: #f9f9f9; border-radius: 5px; }
            .diagnostic-section h4 { margin-top: 0; }
            .diagnostic-section button { margin-right: 10px; margin-bottom: 10px; }
            #feed-status-results, #cache-status, #market-data-status, #api-usage-stats { 
                margin-top: 15px; 
                padding: 10px; 
                background: white; 
                border: 1px solid #ddd; 
                border-radius: 3px;
                min-height: 50px;
            }
            .test-feed { margin-left: 10px; }
            .feed-test-result { 
                margin-top: 10px; 
                padding: 10px; 
                border-radius: 3px; 
            }
            .feed-test-success { background: #d4edda; color: #155724; }
            .feed-test-error { background: #f8d7da; color: #721c24; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab navigation
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.tab-content').hide();
                $($(this).attr('href')).show();
            });
            
            // Test feed
            $('.test-feed').on('click', function() {
                const button = $(this);
                const feed = button.data('feed');
                button.prop('disabled', true).text('Testing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sffc_test_feed',
                        feed: feed,
                        nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            button.after('<div class="feed-test-result feed-test-success">✓ Feed working! Found ' + response.data.count + ' items.</div>');
                        } else {
                            button.after('<div class="feed-test-result feed-test-error">✗ Error: ' + response.data.message + '</div>');
                        }
                        setTimeout(function() {
                            $('.feed-test-result').fadeOut(function() { $(this).remove(); });
                        }, 5000);
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Test Feed');
                    }
                });
            });
            
            // Check all feeds
            $('#check-all-feeds').on('click', function() {
                const button = $(this);
                button.prop('disabled', true).text('Checking...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sffc_check_all_feeds',
                        nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            let html = '<h5>Feed Status Report</h5><ul>';
                            $.each(response.data, function(feed, status) {
                                const icon = status.success ? '✓' : '✗';
                                const color = status.success ? 'green' : 'red';
                                html += '<li style="color: ' + color + '">' + icon + ' ' + feed + ': ' + status.message + '</li>';
                            });
                            html += '</ul>';
                            $('#feed-status-results').html(html);
                        }
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Check All Feeds');
                    }
                });
            });
            
            // Clear cache functions
            $('#clear-all-cache').on('click', function() {
                if (!confirm('Clear all cached market data?')) return;
                
                const button = $(this);
                button.prop('disabled', true).text('Clearing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sffc_clear_market_cache',
                        type: 'all',
                        nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#cache-status').html('<div style="color: green;">✓ ' + response.data.message + '</div>');
                        }
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Clear All Cache');
                    }
                });
            });
            
            // Refresh market data
            $('#refresh-market-data').on('click', function() {
                const button = $(this);
                button.prop('disabled', true).text('Refreshing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sffc_refresh_market_data',
                        nonce: '<?php echo wp_create_nonce('sffc_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#market-data-status').html('<div style="color: green;">✓ Updated ' + response.data.count + ' market items</div>');
                        }
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Refresh Market Data');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('sffc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $settings = $_POST['settings'];
        $sanitized = $this->sanitize_settings($settings);
        
        update_option('sffc_market_settings', $sanitized);
        
        wp_send_json_success(array('message' => 'Settings saved successfully'));
    }
    
    /**
     * AJAX: Test feed
     */
    public function ajax_test_feed() {
        check_ajax_referer('sffc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $feed_key = sanitize_text_field($_POST['feed']);
        
        // Get feed manager
        require_once SFFC_PLUGIN_DIR . 'includes/class-market-feed-manager.php';
        $feed_manager = SFFC_Market_Feed_Manager::get_instance();
        
        // Test the feed
        $result = $feed_manager->fetch_feed($feed_key);
        
        if ($result && !empty($result['items'])) {
            wp_send_json_success(array(
                'message' => 'Feed is working',
                'count' => count($result['items'])
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to fetch feed'));
        }
    }
    
    /**
     * AJAX: Clear cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer('sffc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $type = sanitize_text_field($_POST['type']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_market_cache';
        
        if ($type === 'all') {
            // Validate and escape table name to prevent SQL injection
            if (strpos($table_name, $wpdb->prefix . 'sffc_') === 0) {
                $wpdb->query("TRUNCATE TABLE `" . esc_sql($table_name) . "`");
            }
            delete_transient('sffc_market_intelligence');
            
            // Clear all feed transients
            $feeds = array('bloomberg', 'ft', 'wsj', 'expansion', 'ilsole');
            foreach ($feeds as $feed) {
                delete_transient('sffc_feed_' . $feed);
            }
            
            wp_send_json_success(array('message' => 'All cache cleared'));
        } else {
            wp_send_json_error(array('message' => 'Invalid cache type'));
        }
    }
    
    /**
     * AJAX: Refresh market data
     */
    public function ajax_refresh_data() {
        check_ajax_referer('sffc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Get market integration
        require_once SFFC_PLUGIN_DIR . 'includes/class-market-mode-integration.php';
        $integration = SFFC_Market_Mode_Integration::get_instance();
        
        // Force refresh
        $integration->update_feeds();
        $integration->refresh_analysis();
        
        wp_send_json_success(array(
            'message' => 'Market data refreshed',
            'count' => 10 // This would be dynamic in production
        ));
    }
    
    /**
     * Sanitize custom feeds
     * 
     * @param array $feeds Array of custom feed data
     * @return array Sanitized custom feeds
     */
    private function sanitize_custom_feeds($feeds) {
        $sanitized = array();
        
        if (!is_array($feeds)) {
            return $sanitized;
        }
        
        foreach ($feeds as $index => $feed) {
            if (isset($feed['url']) && !empty($feed['url'])) {
                $sanitized[$index] = array(
                    'name' => isset($feed['name']) ? sanitize_text_field($feed['name']) : '',
                    'url' => esc_url_raw($feed['url']),
                    'enabled' => isset($feed['enabled']) ? (bool) $feed['enabled'] : true,
                    'category' => isset($feed['category']) ? sanitize_text_field($feed['category']) : 'general'
                );
            }
        }
        
        return $sanitized;
    }
}