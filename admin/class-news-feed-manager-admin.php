<?php
/**
 * News Feed Manager Admin Interface
 * 
 * Duplicate of Feed Manager but specifically for sffc_pe_news content generation
 * These feeds fetch job data but create news articles instead of job posts
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_News_Feed_Manager_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_page'], 26);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // AJAX handlers for news feeds
        add_action('wp_ajax_sffc_test_news_feed', [$this, 'ajax_test_single_feed']);
        add_action('wp_ajax_sffc_add_news_workday_feed', [$this, 'ajax_add_workday_feed']);
        add_action('wp_ajax_sffc_add_news_xml_feed', [$this, 'ajax_add_xml_feed']);
        add_action('wp_ajax_sffc_remove_news_feed', [$this, 'ajax_remove_feed']);
        add_action('wp_ajax_sffc_fetch_news_data', [$this, 'ajax_fetch_from_feed']);
        add_action('wp_ajax_sffc_edit_news_feed', [$this, 'ajax_edit_feed']);
        add_action('wp_ajax_sffc_get_news_feed_data', [$this, 'ajax_get_feed_data']);
        add_action('wp_ajax_sffc_news_bulk_operation', [$this, 'ajax_bulk_operation']);
        add_action('wp_ajax_sffc_generate_weekly_news', [$this, 'ajax_generate_weekly_news']);
    }
    
    public function add_admin_page() {
        // Menu registration handled by job system integration
        // This method is kept for backwards compatibility
    }
    
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'sffc-news-feed-manager') === false) {
            return;
        }
        
        wp_enqueue_style('sffc-news-feed-manager', SFFC_PLUGIN_URL . 'admin/css/feed-manager.css', [], SFFC_VERSION);
        wp_enqueue_script('sffc-news-feed-manager', SFFC_PLUGIN_URL . 'admin/js/news-feed-manager.js', ['jquery'], SFFC_VERSION, true);
        
        wp_localize_script('sffc-news-feed-manager', 'sffcNewsFeedManager', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_news_feed_manager')
        ]);
    }
    
    public function render_page() {
        ?>
        <div class="wrap">
            <h1>News Feed Manager</h1>
            <p>Manage feeds specifically for generating weekly news articles and trend analysis. These feeds collect job data but create news content instead of job posts.</p>
            
            <!-- Weekly News Generation -->
            <div class="sffc-news-generation-section">
                <h2>Weekly News Generation</h2>
                <div style="background: #f0f8ff; border: 1px solid #b3d9ff; padding: 20px; margin: 20px 0; border-radius: 5px;">
                    <h3>📊 Generate Weekly Market Analysis</h3>
                    <p>Analyze job postings from all active news feeds to create weekly trend reports:</p>
                    <ul>
                        <li>• Company hiring trends (e.g., "Blackstone hiring 4 positions in London, up from 2 last week")</li>
                        <li>• Role-level analysis (senior vs. junior positions)</li>
                        <li>• Market movement insights</li>
                        <li>• Salary trend analysis</li>
                        <li>• Location-based hiring patterns</li>
                    </ul>
                    <button class="button button-primary button-hero" id="sffc-generate-weekly-news">
                        <span class="dashicons dashicons-chart-line" style="margin-top: 4px;"></span>
                        Generate Weekly News Article
                    </button>
                    <div id="sffc-news-generation-status" style="margin-top: 15px; display: none;">
                        <div class="sffc-progress-bar">
                            <div class="sffc-progress-fill"></div>
                        </div>
                        <p class="sffc-status-message"></p>
                    </div>
                </div>
            </div>
            
            <!-- News Workday Feeds -->
            <div class="sffc-feed-section">
                <h2>News Workday API Feeds</h2>
                <p class="description">These are duplicates of job feeds used specifically for news generation. They collect job data but create sffc_pe_news posts instead of job posts.</p>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="3%"><input type="checkbox" id="select-all-news-workday" /></th>
                            <th width="17%">Company</th>
                            <th width="30%">Endpoint</th>
                            <th width="10%">Status</th>
                            <th width="10%">Jobs Tracked</th>
                            <th width="30%">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="news-workday-feeds-list">
                        <?php $this->render_news_workday_feeds(); ?>
                    </tbody>
                </table>
                
                <h3>Add New News Workday Feed</h3>
                <form id="add-news-workday-form" class="feed-form">
                    <table class="form-table">
                        <tr>
                            <th><label>Company Key</label></th>
                            <td><input type="text" name="company_key" placeholder="e.g., goldman_sachs_news" required /></td>
                        </tr>
                        <tr>
                            <th><label>Company Name</label></th>
                            <td><input type="text" name="company_name" placeholder="e.g., Goldman Sachs (News)" required /></td>
                        </tr>
                        <tr>
                            <th><label>Base URL</label></th>
                            <td><input type="url" name="base_url" placeholder="https://company.wd1.myworkdayjobs.com" required /></td>
                        </tr>
                        <tr>
                            <th><label>Endpoint Path</label></th>
                            <td><input type="text" name="endpoint" placeholder="/wday/cxs/company/careers/jobs" required /></td>
                        </tr>
                        <tr>
                            <th><label>Careers Path</label></th>
                            <td><input type="text" name="careers_path" placeholder="/en-US/careers" required /></td>
                        </tr>
                    </table>
                    <button type="submit" class="button button-primary">Add News Workday Feed</button>
                </form>
            </div>
            
            <!-- News XML Feeds -->
            <div class="sffc-feed-section">
                <h2>News XML/RSS Feeds</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="3%"><input type="checkbox" id="select-all-news-xml" /></th>
                            <th width="17%">Source</th>
                            <th width="40%">Feed URL</th>
                            <th width="10%">Status</th>
                            <th width="10%">Jobs Tracked</th>
                            <th width="20%">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="news-xml-feeds-list">
                        <?php $this->render_news_xml_feeds(); ?>
                    </tbody>
                </table>
                
                <h3>Add New News XML Feed</h3>
                <form id="add-news-xml-form" class="feed-form">
                    <table class="form-table">
                        <tr>
                            <th><label>Source Key</label></th>
                            <td><input type="text" name="source_key" placeholder="e.g., finatal_news" required /></td>
                        </tr>
                        <tr>
                            <th><label>Source Name</label></th>
                            <td><input type="text" name="source_name" placeholder="e.g., Finatal (News Feed)" required /></td>
                        </tr>
                        <tr>
                            <th><label>Feed URL</label></th>
                            <td><input type="url" name="feed_url" placeholder="https://example.com/jobs.xml" required /></td>
                        </tr>
                        <tr>
                            <th><label>Feed Type</label></th>
                            <td>
                                <select name="feed_type">
                                    <option value="sitemap">XML Sitemap</option>
                                    <option value="website">Website Page</option>
                                    <option value="rss">RSS Feed</option>
                                    <option value="indeed">Indeed Feed</option>
                                    <option value="greenhouse">Greenhouse API</option>
                                    <option value="custom">Custom XML</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Source Type</label></th>
                            <td>
                                <select name="source_type">
                                    <option value="company">Company (Direct)</option>
                                    <option value="recruiter">Recruiter/Agency</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <button type="submit" class="button button-primary">Add News XML Feed</button>
                </form>
            </div>
            
            <!-- Bulk Operations -->
            <div class="bulk-operations" style="margin-top: 20px;">
                <h3>Bulk Operations</h3>
                <select id="news-bulk-action">
                    <option value="">Select Action</option>
                    <option value="test">Test Selected Feeds</option>
                    <option value="fetch_data">Fetch Data from Selected</option>
                    <option value="generate_news">Generate News from Selected</option>
                    <option value="enable">Enable Selected</option>
                    <option value="disable">Disable Selected</option>
                    <option value="remove">Remove Selected</option>
                </select>
                <button class="button" id="apply-news-bulk-action">Apply</button>
                <span id="news-bulk-status"></span>
            </div>
        </div>
        
        <style>
        .sffc-feed-section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .sffc-news-generation-section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .feed-form {
            max-width: 600px;
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #e1e1e1;
        }
        .feed-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-working { background: #d4edda; color: #155724; }
        .status-error { background: #f8d7da; color: #721c24; }
        .status-untested { background: #fff3cd; color: #856404; }
        .status-disabled { background: #e2e3e5; color: #383d41; }
        .bulk-operations {
            background: white;
            padding: 20px;
            border: 1px solid #ccd0d4;
            margin-top: 20px;
        }
        .sffc-progress-bar {
            width: 100%;
            height: 20px;
            background: #f1f1f1;
            border-radius: 10px;
            overflow: hidden;
        }
        .sffc-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #00a32a, #00d084);
            width: 0%;
            transition: width 0.3s ease;
        }
        </style>
        <?php
    }
    
    private function render_news_workday_feeds() {
        $feeds = $this->get_news_workday_feeds();
        
        foreach ($feeds as $key => $feed) {
            $status_class = $this->get_status_class($feed['status'] ?? 'untested');
            ?>
            <tr data-feed-key="<?php echo esc_attr($key); ?>">
                <td><input type="checkbox" class="news-feed-checkbox" data-type="workday" data-key="<?php echo esc_attr($key); ?>" /></td>
                <td><strong><?php echo esc_html($feed['company_name'] ?? ucfirst($key)); ?></strong></td>
                <td><code><?php echo esc_html($feed['base_url'] . $feed['endpoint']); ?></code></td>
                <td><span class="feed-status <?php echo $status_class; ?>"><?php echo esc_html($feed['status'] ?? 'untested'); ?></span></td>
                <td class="job-count">-</td>
                <td>
                    <button class="button test-news-feed" data-type="workday" data-key="<?php echo esc_attr($key); ?>">Test</button>
                    <button class="button fetch-news-data" data-type="workday" data-key="<?php echo esc_attr($key); ?>">Fetch Data</button>
                    <button class="button edit-news-feed" data-type="workday" data-key="<?php echo esc_attr($key); ?>">Edit</button>
                    <button class="button remove-news-feed" data-type="workday" data-key="<?php echo esc_attr($key); ?>">Remove</button>
                </td>
            </tr>
            <?php
        }
    }
    
    private function render_news_xml_feeds() {
        $feeds = $this->get_news_xml_feeds();
        
        foreach ($feeds as $key => $feed) {
            ?>
            <tr data-feed-key="<?php echo esc_attr($key); ?>">
                <td><input type="checkbox" class="news-feed-checkbox" data-type="xml" data-key="<?php echo esc_attr($key); ?>" /></td>
                <td><strong><?php echo esc_html($feed['name'] ?? ucfirst($key)); ?></strong></td>
                <td><code><?php echo esc_html($feed['url']); ?></code></td>
                <td><span class="feed-status status-untested">untested</span></td>
                <td class="job-count">-</td>
                <td>
                    <button class="button test-news-feed" data-type="xml" data-key="<?php echo esc_attr($key); ?>">Test</button>
                    <button class="button fetch-news-data" data-type="xml" data-key="<?php echo esc_attr($key); ?>">Fetch Data</button>
                    <button class="button edit-news-feed" data-type="xml" data-key="<?php echo esc_attr($key); ?>">Edit</button>
                    <button class="button remove-news-feed" data-type="xml" data-key="<?php echo esc_attr($key); ?>">Remove</button>
                </td>
            </tr>
            <?php
        }
    }
    
    private function get_news_workday_feeds() {
        // Get feeds specifically for news generation
        $news_feeds = get_option('sffc_news_workday_feeds', []);
        
        // If no news feeds exist, duplicate from job feeds
        if (empty($news_feeds)) {
            $job_feeds = get_option('sffc_custom_workday_feeds', []);
            if (!empty($job_feeds)) {
                // Copy job feeds to news feeds with news flag
                foreach ($job_feeds as $key => $feed) {
                    $feed['news_feed'] = true;
                    $news_feeds[$key] = $feed;
                }
                update_option('sffc_news_workday_feeds', $news_feeds);
            }
        }
        
        return $news_feeds;
    }
    
    private function get_news_xml_feeds() {
        // Get feeds specifically for news generation
        $news_feeds = get_option('sffc_news_xml_feeds', []);
        
        // If no news feeds exist, duplicate from job feeds
        if (empty($news_feeds)) {
            $job_feeds = get_option('sffc_custom_xml_feeds', []);
            
            // Also include hiring-focused feeds from the installer
            global $wpdb;
            $table_name = $wpdb->prefix . 'sffc_xml_feeds';
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                $hiring_feeds = $wpdb->get_results(
                    "SELECT feed_name, feed_url, feed_category FROM {$table_name} WHERE hiring_focused = 1 AND is_active = 1",
                    ARRAY_A
                );
                
                // Convert hiring feeds to XML feed format
                foreach ($hiring_feeds as $feed) {
                    $key = sanitize_key($feed['feed_name']);
                    $job_feeds[$key] = [
                        'name' => $feed['feed_name'],
                        'url' => $feed['feed_url'],
                        'type' => 'rss',
                        'source_type' => $feed['feed_category'],
                        'news_feed' => true,
                        'hiring_focused' => true
                    ];
                }
            }
            
            if (!empty($job_feeds)) {
                // Copy job feeds to news feeds with news flag
                foreach ($job_feeds as $key => $feed) {
                    $feed['news_feed'] = true;
                    $news_feeds[$key] = $feed;
                }
                update_option('sffc_news_xml_feeds', $news_feeds);
            }
        }
        
        return $news_feeds;
    }
    
    private function get_status_class($status) {
        switch($status) {
            case 'working':
            case 'new':
                return 'status-working';
            case 'error':
            case 'auth_required':
            case 'needs_fix':
                return 'status-error';
            case 'disabled':
                return 'status-disabled';
            default:
                return 'status-untested';
        }
    }
    
    /**
     * AJAX: Test single news feed
     */
    public function ajax_test_single_feed() {
        ob_start();
        
        if (!current_user_can('manage_options')) {
            ob_end_clean();
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('sffc_news_feed_manager', 'nonce');
        
        $type = sanitize_text_field($_POST['type']);
        $key = sanitize_text_field($_POST['key']);
        
        // Use the same test logic as the regular feed manager
        if ($type === 'workday') {
            $result = $this->test_workday_feed($key);
        } else {
            $result = $this->test_xml_feed($key);
        }
        
        ob_end_clean();
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Fetch data from feed for news generation
     */
    public function ajax_fetch_from_feed() {
        ob_start();
        
        if (!current_user_can('manage_options')) {
            ob_end_clean();
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('sffc_news_feed_manager', 'nonce');
        
        // Increase limits safely for data fetching
        if (!ini_get('safe_mode')) {
            @set_time_limit(300); // 5 minutes
            @ini_set('max_execution_time', 300);
            @ini_set('memory_limit', '512M');
        }
        
        $type = sanitize_text_field($_POST['type']);
        $key = sanitize_text_field($_POST['key']);
        
        $result = ['success' => false, 'jobs' => [], 'analysis' => null];
        
        if ($type === 'workday' && class_exists('SFFC_Workday_Job_Fetcher_V2')) {
            try {
                $fetcher = new SFFC_Workday_Job_Fetcher_V2();
                $jobs_result = $fetcher->get_jobs($key, ['limit' => 50]);
                
                if (!is_wp_error($jobs_result) && !empty($jobs_result['jobs'])) {
                    $result['success'] = true;
                    $result['jobs'] = $jobs_result['jobs'];
                    $result['analysis'] = $this->analyze_job_data($jobs_result['jobs'], $key);
                } else {
                    $result['error'] = 'No jobs found or API error';
                }
            } catch (Exception $e) {
                error_log('News Feed Workday Error: ' . $e->getMessage());
                $result['error'] = 'Workday fetcher error: ' . $e->getMessage();
            }
        } elseif ($type === 'xml' && class_exists('SFFC_XML_Job_Fetcher')) {
            try {
                $fetcher = new SFFC_XML_Job_Fetcher();
                $feeds = $this->get_news_xml_feeds();
                
                if (isset($feeds[$key])) {
                    $jobs = method_exists($fetcher, 'fetch_from_source_key')
                        ? $fetcher->fetch_from_source_key($key, 50)
                        : [];
                    if (empty($jobs)) {
                        $jobs = $fetcher->fetch_from_source($feeds[$key]['url'], 50);
                    }
                    if (!empty($jobs)) {
                        $result['success'] = true;
                        $result['jobs'] = $jobs;
                        $result['analysis'] = $this->analyze_job_data($jobs, $key);
                    } else {
                        $result['error'] = 'No jobs found in XML feed';
                    }
                } else {
                    $result['error'] = 'Feed configuration not found';
                }
            } catch (Exception $e) {
                error_log('News Feed XML Error: ' . $e->getMessage());
                $result['error'] = 'XML fetcher error: ' . $e->getMessage();
            }
        } else {
            $result['error'] = 'Required fetcher classes not available';
        }
        
        ob_end_clean();
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Generate weekly news article
     */
    public function ajax_generate_weekly_news() {
        ob_start();
        
        if (!current_user_can('manage_options')) {
            ob_end_clean();
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('sffc_news_feed_manager', 'nonce');
        
        // Increase limits safely for article generation
        if (!ini_get('safe_mode')) {
            @set_time_limit(600); // 10 minutes
            @ini_set('max_execution_time', 600);
            @ini_set('memory_limit', '1024M');
        }
        
        $result = $this->generate_weekly_news_article();
        
        ob_end_clean();
        wp_send_json_success($result);
    }
    
    /**
     * Analyze job data for trends
     */
    private function analyze_job_data($jobs, $source_key) {
        $analysis = [
            'total_jobs' => count($jobs),
            'companies' => [],
            'locations' => [],
            'role_levels' => [],
            'departments' => [],
            'salary_ranges' => []
        ];
        
        foreach ($jobs as $job) {
            // Extract company info
            $company = $this->extract_company_name($job, $source_key);
            $analysis['companies'][$company] = ($analysis['companies'][$company] ?? 0) + 1;
            
            // Extract location
            $location = $this->extract_location($job);
            if ($location) {
                $analysis['locations'][$location] = ($analysis['locations'][$location] ?? 0) + 1;
            }
            
            // Analyze role level
            $level = $this->extract_role_level($job);
            if ($level) {
                $analysis['role_levels'][$level] = ($analysis['role_levels'][$level] ?? 0) + 1;
            }
            
            // Extract salary if available
            $salary = $this->extract_salary($job);
            if ($salary) {
                $analysis['salary_ranges'][] = $salary;
            }
        }
        
        return $analysis;
    }
    
    /**
     * Extract company name from job data
     */
    private function extract_company_name($job, $source_key) {
        // Try to get company name from various job fields
        if (!empty($job['company'])) {
            return $job['company'];
        }
        
        if (!empty($job['company_name'])) {
            return $job['company_name'];
        }
        
        // Fallback to source key transformation
        $company_names = [
            'blackstone' => 'Blackstone',
            'goldman_sachs' => 'Goldman Sachs',
            'jpmorgan' => 'J.P. Morgan',
            'morganstanley' => 'Morgan Stanley',
            // Add more mappings as needed
        ];
        
        return $company_names[$source_key] ?? ucwords(str_replace('_', ' ', $source_key));
    }
    
    /**
     * Extract location from job data
     */
    private function extract_location($job) {
        $location_fields = ['location', 'city', 'office', 'workplace_location'];
        
        foreach ($location_fields as $field) {
            if (!empty($job[$field])) {
                return $job[$field];
            }
        }
        
        return null;
    }
    
    /**
     * Extract role level from job title and description
     */
    private function extract_role_level($job) {
        $title = strtolower($job['title'] ?? '');
        $description = strtolower($job['description'] ?? '');
        $content = $title . ' ' . $description;
        
        if (preg_match('/\b(senior|sr\.?|lead|principal|director|vp|vice president|managing director|head of)\b/i', $content)) {
            return 'Senior';
        }
        
        if (preg_match('/\b(junior|jr\.?|entry|graduate|intern|associate|analyst)\b/i', $content)) {
            return 'Junior';
        }
        
        return 'Mid-Level';
    }
    
    /**
     * Extract salary information
     */
    private function extract_salary($job) {
        $salary_fields = ['salary', 'compensation', 'pay'];
        
        foreach ($salary_fields as $field) {
            if (!empty($job[$field])) {
                // Basic salary extraction - could be enhanced
                if (preg_match('/[\£\$\€]?(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)/i', $job[$field], $matches)) {
                    return floatval(str_replace(',', '', $matches[1]));
                }
            }
        }
        
        return null;
    }
    
    /**
     * Generate weekly news article
     */
    private function generate_weekly_news_article() {
        // Collect data from all active news feeds
        $all_data = [];
        $workday_feeds = $this->get_news_workday_feeds();
        $xml_feeds = $this->get_news_xml_feeds();
        
        // Fetch from Workday feeds
        if (class_exists('SFFC_Workday_Job_Fetcher_V2')) {
            $fetcher = new SFFC_Workday_Job_Fetcher_V2();
            foreach ($workday_feeds as $key => $feed) {
                if ($feed['status'] !== 'disabled') {
                    $jobs_result = $fetcher->get_jobs($key, ['limit' => 50]);
                    if (!is_wp_error($jobs_result) && !empty($jobs_result['jobs'])) {
                        $all_data[$key] = $jobs_result['jobs'];
                    }
                }
            }
        }
        
        // Fetch from XML feeds
        if (class_exists('SFFC_XML_Job_Fetcher')) {
            $fetcher = new SFFC_XML_Job_Fetcher();
            foreach ($xml_feeds as $key => $feed) {
                if ($feed['status'] !== 'disabled') {
                    $jobs = method_exists($fetcher, 'fetch_from_source_key')
                        ? $fetcher->fetch_from_source_key($key, 50)
                        : [];
                    if (empty($jobs)) {
                        $jobs = $fetcher->fetch_from_source($feed['url'], 50);
                    }
                    if (!empty($jobs)) {
                        $all_data[$key] = $jobs;
                    }
                }
            }
        }
        
        if (empty($all_data)) {
            return [
                'success' => false,
                'message' => 'No job data found from active news feeds'
            ];
        }
        
        // Analyze aggregated data
        $comprehensive_analysis = $this->generate_comprehensive_analysis($all_data);
        
        // Generate article using Claude API
        try {
            $article_content = $this->generate_article_with_claude($comprehensive_analysis);
            
            // Create the news post
            $post_id = $this->create_news_post($article_content, $comprehensive_analysis);
            
            return [
                'success' => true,
                'post_id' => $post_id,
                'analysis' => $comprehensive_analysis,
                'article_preview' => wp_trim_words($article_content['content'], 100)
            ];
        } catch (Exception $e) {
            error_log('Weekly news generation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to generate article: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate comprehensive analysis from all feeds
     */
    private function generate_comprehensive_analysis($all_data) {
        $analysis = [
            'total_jobs' => 0,
            'companies' => [],
            'week_over_week' => [],
            'trending_roles' => [],
            'salary_trends' => [],
            'location_trends' => [],
            'insights' => []
        ];
        
        foreach ($all_data as $source => $jobs) {
            $source_analysis = $this->analyze_job_data($jobs, $source);
            $analysis['total_jobs'] += $source_analysis['total_jobs'];
            
            // Merge company data
            foreach ($source_analysis['companies'] as $company => $count) {
                $analysis['companies'][$company] = ($analysis['companies'][$company] ?? 0) + $count;
            }
            
            // Store source-specific data for comparison
            $analysis['sources'][$source] = $source_analysis;
        }
        
        // Generate insights
        $analysis['insights'] = $this->generate_market_insights($analysis);
        
        return $analysis;
    }
    
    /**
     * Generate market insights from analysis
     */
    private function generate_market_insights($analysis) {
        $insights = [];
        
        // Company hiring trends
        arsort($analysis['companies']);
        $top_companies = array_slice($analysis['companies'], 0, 5, true);
        
        foreach ($top_companies as $company => $count) {
            $insights[] = "{$company} is actively hiring with {$count} open positions";
        }
        
        // Overall market sentiment
        if ($analysis['total_jobs'] > 100) {
            $insights[] = "Strong hiring activity with {$analysis['total_jobs']} total positions across all tracked companies";
        } elseif ($analysis['total_jobs'] > 50) {
            $insights[] = "Moderate hiring activity with {$analysis['total_jobs']} total positions";
        } else {
            $insights[] = "Conservative hiring environment with {$analysis['total_jobs']} total positions";
        }
        
        return $insights;
    }
    
    /**
     * Generate article content using Claude API
     */
    private function generate_article_with_claude($analysis) {
        if (!class_exists('SFFC_Claude_API_Manager')) {
            error_log('Claude API Manager not available, using fallback');
            return [
                'title' => 'Weekly Private Equity & Finance Hiring Report - ' . date('F j, Y'),
                'content' => $this->generate_fallback_article($analysis)
            ];
        }
        
        try {
            $claude = SFFC_Claude_API_Manager::get_instance();
        } catch (Exception $e) {
            error_log('Failed to initialize Claude API Manager: ' . $e->getMessage());
            return [
                'title' => 'Weekly Private Equity & Finance Hiring Report - ' . date('F j, Y'),
                'content' => $this->generate_fallback_article($analysis)
            ];
        }
        
        $prompt = "Based on the following job market data, write a comprehensive weekly hiring report for the private equity and finance industry:

**Market Data Summary:**
- Total positions tracked: {$analysis['total_jobs']}
- Active companies: " . count($analysis['companies']) . "
- Top hiring companies: " . implode(', ', array_keys(array_slice($analysis['companies'], 0, 5, true))) . "

**Key Insights:**
" . implode("\n", $analysis['insights']) . "

**Requirements:**
1. Write an engaging title
2. Create a 500-800 word article with the following sections:
   - Executive Summary (2-3 sentences)
   - Company Spotlight (highlight top 3-5 hiring companies)
   - Market Trends Analysis
   - Role-Level Insights (if data available)
   - Regional Focus (if location data available)
   - Looking Ahead (1-2 predictions)

3. Include specific numbers and percentages where possible
4. Use professional but accessible language
5. Add actionable insights for job seekers
6. Format with clear headings and bullet points

Please format as JSON with 'title' and 'content' keys.";

        $response = $claude->send_message($prompt, [
            'max_tokens' => 2000,
            'temperature' => 0.7
        ]);
        
        if ($response && !is_wp_error($response)) {
            $parsed = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && $parsed && isset($parsed['title']) && isset($parsed['content'])) {
                return $parsed;
            } else {
                error_log('Failed to parse Claude response as JSON: ' . json_last_error_msg() . '. Raw response: ' . substr($response, 0, 200));
            }
        }
        
        // Fallback if Claude fails
        return [
            'title' => 'Weekly Private Equity & Finance Hiring Report',
            'content' => $this->generate_fallback_article($analysis)
        ];
    }
    
    /**
     * Generate fallback article without Claude
     */
    private function generate_fallback_article($analysis) {
        $date = date('F j, Y');
        $content = "# Weekly Private Equity & Finance Hiring Report - {$date}\n\n";
        
        $content .= "## Executive Summary\n\n";
        $content .= "This week we tracked {$analysis['total_jobs']} open positions across " . count($analysis['companies']) . " leading financial institutions. ";
        
        if ($analysis['total_jobs'] > 100) {
            $content .= "The market shows strong hiring momentum with increased activity across multiple sectors.\n\n";
        } else {
            $content .= "Hiring activity remains steady with selective recruitment across key roles.\n\n";
        }
        
        $content .= "## Company Spotlight\n\n";
        arsort($analysis['companies']);
        $top_companies = array_slice($analysis['companies'], 0, 5, true);
        
        foreach ($top_companies as $company => $count) {
            $content .= "• **{$company}**: {$count} open positions\n";
        }
        
        $content .= "\n## Key Insights\n\n";
        foreach ($analysis['insights'] as $insight) {
            $content .= "• {$insight}\n";
        }
        
        $content .= "\n## Market Outlook\n\n";
        $content .= "Based on current hiring patterns, we expect continued activity in the coming weeks. Job seekers should focus on companies showing consistent hiring growth and prepare for competitive selection processes.\n\n";
        $content .= "*Data compiled from company career pages and recruitment feeds. Analysis generated on " . date('Y-m-d H:i:s') . "*";
        
        return $content;
    }
    
    /**
     * Create news post
     */
    private function create_news_post($article_content, $analysis) {
        // Validate inputs
        if (empty($article_content['title']) || empty($article_content['content'])) {
            throw new Exception('Article title or content is empty');
        }
        
        // Sanitize title and content
        $title = wp_strip_all_tags($article_content['title']);
        $content = wp_kses_post($article_content['content']);
        
        // Determine if this is deal-related content
        $is_deal_content = $this->is_deal_related_content($article_content, $analysis);
        $post_type = $is_deal_content ? 'sffc_pe_deal' : 'sffc_pe_news';
        
        $post_data = [
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => $post_type,
            'post_author' => get_current_user_id(),
            'meta_input' => [
                'sffc_news_type' => $is_deal_content ? 'deal_report' : 'weekly_report',
                'sffc_analysis_data' => wp_slash(json_encode($analysis)),
                'sffc_generated_date' => current_time('Y-m-d H:i:s'),
                'sffc_job_count' => intval($analysis['total_jobs']),
                'sffc_company_count' => intval(count($analysis['companies'])),
                'sffc_content_type' => $is_deal_content ? 'deal' : 'news'
            ]
        ];
        
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            throw new Exception('Failed to create post: ' . $post_id->get_error_message());
        }
        
        return $post_id;
    }
    
    /**
     * Determine if content is deal-related
     */
    private function is_deal_related_content($article_content, $analysis) {
        $deal_keywords = [
            // Direct deal terms
            'acquisition', 'buyout', 'merger', 'deal', 'transaction', 'purchase',
            'exit', 'sale', 'investment', 'funding', 'capital raise', 'ipo',
            
            // PE/VC specific deal terms
            'portfolio company', 'add-on', 'platform company', 'bolt-on',
            'growth capital', 'expansion capital', 'recapitalization', 'recap',
            'leveraged buyout', 'lbo', 'management buyout', 'mbo',
            'secondary buyout', 'dividend recap', 'refinancing',
            
            // Deal structure terms
            'series a', 'series b', 'series c', 'seed round', 'bridge round',
            'convertible', 'preferred equity', 'mezzanine', 'debt financing',
            'credit facility', 'term loan', 'revolving credit',
            
            // Deal size indicators
            'million', 'billion', '$m', '$b', 'valuation', 'enterprise value',
            'equity value', 'transaction value', 'consideration',
            
            // Deal parties
            'acquirer', 'target', 'seller', 'buyer', 'investor', 'sponsor',
            'consortium', 'syndicate', 'co-investor', 'lead investor',
            
            // Deal completion terms
            'closed', 'completed', 'signed', 'announced', 'agreed',
            'pending', 'expected to close', 'subject to approval'
        ];
        
        $content_text = strtolower($article_content['title'] . ' ' . $article_content['content']);
        
        // Count deal-related keywords
        $deal_score = 0;
        foreach ($deal_keywords as $keyword) {
            if (strpos($content_text, strtolower($keyword)) !== false) {
                $deal_score++;
            }
        }
        
        // Check for financial amounts (strong indicator of deals)
        $has_financial_amounts = preg_match('/\$\d+(?:\.\d+)?\s*(?:million|billion|m|b)\b/i', $content_text);
        if ($has_financial_amounts) {
            $deal_score += 3;
        }
        
        // Check analysis data for deal indicators
        if (isset($analysis['deal_indicators'])) {
            $deal_score += count($analysis['deal_indicators']);
        }
        
        // Check for company transaction patterns in analysis
        if (isset($analysis['companies'])) {
            foreach ($analysis['companies'] as $company => $count) {
                // If company appears with transaction-related context
                if (strpos($content_text, strtolower($company)) !== false) {
                    $company_context = $this->extract_company_context($content_text, $company);
                    foreach ($deal_keywords as $keyword) {
                        if (strpos($company_context, strtolower($keyword)) !== false) {
                            $deal_score += 2;
                            break;
                        }
                    }
                }
            }
        }
        
        // Return true if deal score is above threshold
        return $deal_score >= 3;
    }
    
    /**
     * Extract context around company mentions for deal detection
     */
    private function extract_company_context($content, $company, $context_length = 100) {
        $company_pos = stripos($content, $company);
        if ($company_pos === false) {
            return '';
        }
        
        $start = max(0, $company_pos - $context_length);
        $length = strlen($company) + ($context_length * 2);
        
        return substr($content, $start, $length);
    }
    
    // Additional AJAX methods for add/edit/remove feeds - similar to original but for news feeds
    public function ajax_add_workday_feed() {
        // Similar to original but saves to sffc_news_workday_feeds option
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('sffc_news_feed_manager', 'nonce');
        
        $key = sanitize_text_field($_POST['company_key']);
        $feed = [
            'company_name' => sanitize_text_field($_POST['company_name']),
            'base_url' => esc_url_raw($_POST['base_url']),
            'endpoint' => sanitize_text_field($_POST['endpoint']),
            'careers_path' => sanitize_text_field($_POST['careers_path']),
            'status' => 'new',
            'news_feed' => true
        ];
        
        $news_feeds = get_option('sffc_news_workday_feeds', []);
        $news_feeds[$key] = $feed;
        update_option('sffc_news_workday_feeds', $news_feeds);
        
        wp_send_json_success(['message' => 'News feed added successfully']);
    }
    
    public function ajax_add_xml_feed() {
        // Similar implementation for XML feeds
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('sffc_news_feed_manager', 'nonce');
        
        $key = sanitize_text_field($_POST['source_key']);
        $feed = [
            'name' => sanitize_text_field($_POST['source_name']),
            'url' => esc_url_raw($_POST['feed_url']),
            'type' => sanitize_text_field($_POST['feed_type']),
            'source_type' => sanitize_text_field($_POST['source_type']),
            'news_feed' => true
        ];
        
        $news_feeds = get_option('sffc_news_xml_feeds', []);
        $news_feeds[$key] = $feed;
        update_option('sffc_news_xml_feeds', $news_feeds);
        
        wp_send_json_success(['message' => 'News XML feed added successfully']);
    }
    
    // Include other required methods for remove, edit, test etc.
    // ... (similar implementations but for news feeds)
    
    private function test_workday_feed($key) {
        // Reuse existing workday test logic
        if (!class_exists('SFFC_Workday_Job_Fetcher_V2')) {
            return ['success' => false, 'error' => 'Workday fetcher not available'];
        }
        
        $fetcher = new SFFC_Workday_Job_Fetcher_V2();
        return $fetcher->test_connection($key);
    }
    
    private function test_xml_feed($key) {
        // Reuse existing XML test logic
        if (!class_exists('SFFC_XML_Job_Fetcher')) {
            return ['success' => false, 'error' => 'XML fetcher not available'];
        }
        
        $feeds = $this->get_news_xml_feeds();
        if (!isset($feeds[$key])) {
            return ['success' => false, 'error' => 'Feed not found'];
        }
        
        $fetcher = new SFFC_XML_Job_Fetcher();
        $jobs = method_exists($fetcher, 'fetch_from_source_key')
            ? $fetcher->fetch_from_source_key($key, 1)
            : [];
        if (empty($jobs)) {
            $jobs = $fetcher->fetch_from_source($feeds[$key]['url'], 1);
        }
        
        return [
            'success' => !empty($jobs),
            'total_jobs' => count($jobs),
            'sample_job' => $jobs[0] ?? null
        ];
    }
    
    // Implement remaining AJAX methods...
    public function ajax_remove_feed() { 
        ob_start();
        
        if (!current_user_can('manage_options')) {
            ob_end_clean();
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('sffc_news_feed_manager', 'nonce');
        
        $type = sanitize_text_field($_POST['type']);
        $key = sanitize_text_field($_POST['key']);
        
        if ($type === 'workday') {
            $custom_feeds = get_option('sffc_news_workday_feeds', []);
            if (isset($custom_feeds[$key])) {
                unset($custom_feeds[$key]);
                update_option('sffc_news_workday_feeds', $custom_feeds);
            }
        } else {
            $custom_feeds = get_option('sffc_news_xml_feeds', []);
            if (isset($custom_feeds[$key])) {
                unset($custom_feeds[$key]);
                update_option('sffc_news_xml_feeds', $custom_feeds);
            }
        }
        
        ob_end_clean();
        wp_send_json_success(['message' => 'News feed removed']);
    }
    
    public function ajax_edit_feed() { 
        ob_start();
        
        if (!current_user_can('manage_options')) {
            ob_end_clean();
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('sffc_news_feed_manager', 'nonce');
        
        $type = sanitize_text_field($_POST['feed_type']);
        $original_key = sanitize_text_field($_POST['original_key']);
        $new_key = sanitize_text_field($_POST['feed_key']);
        
        if ($type === 'workday') {
            $custom_feeds = get_option('sffc_news_workday_feeds', []);
            
            $feed_data = [
                'company_name' => sanitize_text_field($_POST['feed_name']),
                'base_url' => esc_url_raw($_POST['base_url']),
                'endpoint' => sanitize_text_field($_POST['endpoint']),
                'careers_path' => sanitize_text_field($_POST['careers_path']),
                'status' => sanitize_text_field($_POST['status']),
                'news_feed' => true
            ];
            
            if ($original_key !== $new_key && isset($custom_feeds[$original_key])) {
                unset($custom_feeds[$original_key]);
            }
            
            $custom_feeds[$new_key] = $feed_data;
            update_option('sffc_news_workday_feeds', $custom_feeds);
            
        } else {
            $custom_feeds = get_option('sffc_news_xml_feeds', []);
            
            $feed_data = [
                'name' => sanitize_text_field($_POST['feed_name']),
                'url' => esc_url_raw($_POST['feed_url']),
                'type' => sanitize_text_field($_POST['xml_type']),
                'status' => sanitize_text_field($_POST['status']),
                'news_feed' => true
            ];
            
            if ($original_key !== $new_key && isset($custom_feeds[$original_key])) {
                unset($custom_feeds[$original_key]);
            }
            
            $custom_feeds[$new_key] = $feed_data;
            update_option('sffc_news_xml_feeds', $custom_feeds);
        }
        
        ob_end_clean();
        wp_send_json_success(['message' => 'News feed updated successfully']);
    }
    
    public function ajax_get_feed_data() { 
        ob_start();
        
        if (!current_user_can('manage_options')) {
            ob_end_clean();
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('sffc_news_feed_manager', 'nonce');
        
        $type = sanitize_text_field($_POST['type']);
        $key = sanitize_text_field($_POST['key']);
        
        if ($type === 'workday') {
            $feeds = $this->get_news_workday_feeds();
        } else {
            $feeds = $this->get_news_xml_feeds();
        }
        
        if (!isset($feeds[$key])) {
            ob_end_clean();
            wp_send_json_error(['message' => 'Feed not found']);
            return;
        }
        
        $feed_data = $feeds[$key];
        $feed_data['key'] = $key;
        
        ob_end_clean();
        wp_send_json_success($feed_data);
    }
    
    public function ajax_bulk_operation() { 
        ob_start();
        
        if (!current_user_can('manage_options')) {
            ob_end_clean();
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('sffc_news_feed_manager', 'nonce');
        
        $action = sanitize_text_field($_POST['action_type']);
        $feeds = isset($_POST['feeds']) ? array_map('sanitize_text_field', $_POST['feeds']) : [];
        
        $results = [
            'success' => 0,
            'failed' => 0,
            'messages' => []
        ];
        
        foreach ($feeds as $feed_id) {
            list($type, $key) = explode('_', $feed_id, 2);
            
            switch ($action) {
                case 'test':
                    if ($type === 'workday') {
                        $test = $this->test_workday_feed($key);
                    } else {
                        $test = $this->test_xml_feed($key);
                    }
                    
                    if ($test['success']) {
                        $results['success']++;
                    } else {
                        $results['failed']++;
                    }
                    break;
                    
                case 'remove':
                    if ($type === 'workday') {
                        $custom_feeds = get_option('sffc_news_workday_feeds', []);
                        if (isset($custom_feeds[$key])) {
                            unset($custom_feeds[$key]);
                            update_option('sffc_news_workday_feeds', $custom_feeds);
                            $results['success']++;
                        }
                    } else {
                        $custom_feeds = get_option('sffc_news_xml_feeds', []);
                        if (isset($custom_feeds[$key])) {
                            unset($custom_feeds[$key]);
                            update_option('sffc_news_xml_feeds', $custom_feeds);
                            $results['success']++;
                        }
                    }
                    break;
            }
        }
        
        ob_end_clean();
        wp_send_json_success($results);
    }
}

// Note: Initialization handled in main plugin file
