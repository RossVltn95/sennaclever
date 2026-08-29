<?php
/**
 * Feed Manager Admin Interface
 * 
 * Allows testing individual feeds and adding new feeds
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Feed_Manager_Admin {

    private const FETCH_LIMIT = 50;
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_page'], 25);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // AJAX handlers
        add_action('wp_ajax_sffc_test_single_feed', [$this, 'ajax_test_single_feed']);
        add_action('wp_ajax_sffc_add_workday_feed', [$this, 'ajax_add_workday_feed']);
        add_action('wp_ajax_sffc_add_xml_feed', [$this, 'ajax_add_xml_feed']);
        add_action('wp_ajax_sffc_remove_feed', [$this, 'ajax_remove_feed']);
        add_action('wp_ajax_sffc_fetch_from_feed', [$this, 'ajax_fetch_from_feed']);
        add_action('wp_ajax_sffc_edit_feed', [$this, 'ajax_edit_feed']);
        add_action('wp_ajax_sffc_get_feed_data', [$this, 'ajax_get_feed_data']);
        add_action('wp_ajax_sffc_bulk_operation', [$this, 'ajax_bulk_operation']);
    }
    
    public function add_admin_page() {
        add_submenu_page(
            'sffc-crm',
            'Role Feed Manager',
            'Role Feed Manager',
            'manage_options',
            'sffc-feed-manager',
            [$this, 'render_page']
        );

        add_submenu_page(
            'sffc-dashboard',
            'Role Feed Manager',
            'Role Feed Manager',
            'manage_options',
            'sffc-feed-manager',
            [$this, 'render_page']
        );
    }
    
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'sffc-feed-manager') === false) {
            return;
        }
        
        $css_path = SFFC_PLUGIN_DIR . 'admin/css/feed-manager.css';
        $css_version = file_exists($css_path) ? filemtime($css_path) : SFFC_VERSION;
        wp_enqueue_style('sffc-feed-manager', SFFC_PLUGIN_URL . 'admin/css/feed-manager.css', [], $css_version);
        
        // Use enhanced version if it exists, otherwise fall back to original
        $js_file = file_exists(SFFC_PLUGIN_DIR . 'admin/js/feed-manager-enhanced.js') 
            ? 'admin/js/feed-manager-enhanced.js' 
            : 'admin/js/feed-manager.js';
            
        $js_path = SFFC_PLUGIN_DIR . $js_file;
        $js_version = file_exists($js_path) ? filemtime($js_path) : SFFC_VERSION;
        wp_enqueue_script('sffc-feed-manager', SFFC_PLUGIN_URL . $js_file, ['jquery'], $js_version, true);
        
        wp_localize_script('sffc-feed-manager', 'sffcFeedManager', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_feed_manager'),
            'fetchLimit' => self::FETCH_LIMIT,
        ]);
    }
    
    public function render_page() {
        ?>
        <div class="wrap">
            <h1>Investment Role Feed Manager</h1>
            <p>Test ATS feeds, add new sources, and queue asset management, private equity, and adjacent investment roles for CRM editorial review.</p>
            
            <!-- Workday Feeds -->
            <div class="sffc-feed-section">
                <h2>Workday API Feeds</h2>
                <div class="sffc-progressive-fetch" data-feed-target="#workday-feeds-list" data-feed-label="Workday API Feeds">
                    <button type="button" class="button button-primary sffc-fetch-section-feeds">Fetch All Workday Feeds</button>
                    <button type="button" class="button sffc-stop-section-fetch" disabled>Stop</button>
                    <div class="sffc-progressive-fetch__status" aria-live="polite">Ready to fetch this section.</div>
                    <div class="sffc-progressive-fetch__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <span></span>
                    </div>
                </div>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="3%"><input type="checkbox" id="select-all-workday" /></th>
                            <th width="17%">Company</th>
                            <th width="30%">Endpoint</th>
                            <th width="10%">Status</th>
                            <th width="10%">Roles</th>
                            <th width="30%">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="workday-feeds-list">
                        <?php $this->render_workday_feeds('banking'); ?>
                    </tbody>
                </table>
                
                <h3>Add New Workday Feed</h3>
                <form id="add-workday-form" class="feed-form">
                    <table class="form-table">
                        <tr>
                            <th><label>Company Key</label></th>
                            <td><input type="text" name="company_key" placeholder="e.g., goldman_sachs" required /></td>
                        </tr>
                        <tr>
                            <th><label>Company Name</label></th>
                            <td><input type="text" name="company_name" placeholder="e.g., Goldman Sachs" required /></td>
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
                    <button type="submit" class="button button-primary">Add Workday Feed</button>
                </form>
            </div>
            
            <!-- XML Feeds -->
            <div class="sffc-feed-section">
                <h2>XML / ATS Feeds</h2>
                <div class="sffc-progressive-fetch" data-feed-target="#xml-feeds-list" data-feed-label="XML / ATS Feeds">
                    <button type="button" class="button button-primary sffc-fetch-section-feeds">Fetch All XML / ATS Feeds</button>
                    <button type="button" class="button sffc-stop-section-fetch" disabled>Stop</button>
                    <div class="sffc-progressive-fetch__status" aria-live="polite">Ready to fetch this section.</div>
                    <div class="sffc-progressive-fetch__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <span></span>
                    </div>
                </div>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="3%"><input type="checkbox" id="select-all-xml" /></th>
                            <th width="17%">Source</th>
                            <th width="40%">Feed URL</th>
                            <th width="10%">Status</th>
                            <th width="10%">Roles</th>
                            <th width="20%">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="xml-feeds-list">
                        <?php $this->render_xml_feeds(); ?>
                    </tbody>
                </table>

                <h2 style="margin-top:28px;">Job Aggregators</h2>
                <div class="sffc-progressive-fetch" data-feed-target="#aggregator-feeds-list" data-feed-label="Job Aggregators">
                    <button type="button" class="button button-primary sffc-fetch-section-feeds">Fetch All Job Aggregators</button>
                    <button type="button" class="button sffc-stop-section-fetch" disabled>Stop</button>
                    <div class="sffc-progressive-fetch__status" aria-live="polite">Ready to fetch this section.</div>
                    <div class="sffc-progressive-fetch__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <span></span>
                    </div>
                </div>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="3%"><input type="checkbox" id="select-all-aggregators" /></th>
                            <th width="17%">Source</th>
                            <th width="40%">Feed URL</th>
                            <th width="10%">Status</th>
                            <th width="10%">Roles</th>
                            <th width="20%">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="aggregator-feeds-list">
                        <?php $this->render_workday_feeds('job_aggregator'); ?>
                        <?php $this->render_xml_feeds('job_aggregator'); ?>
                    </tbody>
                </table>
                
                <h3>Add New XML Feed</h3>
                <form id="add-xml-form" class="feed-form">
                    <table class="form-table">
                        <tr>
                            <th><label>Source Key</label></th>
                            <td><input type="text" name="source_key" placeholder="e.g., recruiterfeed" required /></td>
                        </tr>
                        <tr>
                            <th><label>Source Name</label></th>
                            <td><input type="text" name="source_name" placeholder="e.g., Recruiter Feed" required /></td>
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
                                    <option value="wp_job_manager_rss">WP Job Manager RSS</option>
                                    <option value="indeed">Indeed Feed</option>
                                    <option value="greenhouse">Greenhouse API</option>
                                    <option value="workable">Workable Search</option>
                                    <option value="workable_board">Workable Company Board</option>
                                    <option value="bayt_careers">Bayt Careers</option>
                                    <option value="successfactors">SAP SuccessFactors</option>
                                    <option value="phenom">Phenom</option>
                                    <option value="talentbrew_search">TalentBrew Search</option>
                                    <option value="smartrecruiters">SmartRecruiters</option>
                                    <option value="lever">Lever</option>
                                    <option value="recruitee">Recruitee</option>
                                    <option value="job_listing_page">Curated Listing Page</option>
                                    <option value="jisr_careers">Jisr Careers</option>
                                    <option value="oracle_cx">Oracle Candidate Experience</option>
                                    <option value="goldman_higher">Goldman Sachs Higher</option>
                                    <option value="deutsche_bank_beesite">Deutsche Bank Beesite</option>
                                    <option value="comeet">Comeet</option>
                                    <option value="agfund">AGFUND Careers</option>
                                    <option value="teamtailor_rss">Teamtailor RSS</option>
                                    <option value="eightfold">Eightfold</option>
                                    <option value="michael_page">Michael Page</option>
                                    <option value="aventus">Aventus Global</option>
                                    <option value="venture_search">Venture Search</option>
                                    <option value="mubadala_takafo">Mubadala Takafo</option>
                                    <option value="alvarez_marsal">Alvarez & Marsal Careers</option>
                                    <option value="consider_board">Consider Portfolio Board</option>
                                    <option value="custom">Custom XML</option>
                                </select>
                                <p class="description">Choose "Workable Search" for jobs.workable.com/search URLs, "Workable Company Board" for apply.workable.com company boards such as ADIA, ADIC, or Emirates Investment Authority, "WP Job Manager RSS" for WordPress job feeds such as Doha Bank, "Bayt Careers" for Bayt-powered careers portals such as Al Rajhi Bank, "SAP SuccessFactors" for careers portals such as stc or Elm, "Phenom" for Phenom-powered career portals such as Majid Al Futtaim, "TalentBrew Search" for location search pages such as BlackRock Saudi Arabia, "Jisr Careers" for Jisr-hosted careers pages such as Merak Capital, "Oracle Candidate Experience" for Oracle CX career portals such as Emirates NBD and FAB, "Comeet" for Comeet-hosted careers pages such as CBD, "Teamtailor RSS" for Teamtailor career sites such as Savills Middle East, "Eightfold" for Eightfold portals such as HSBC, "Recruitee" for Recruitee public offer APIs, "Curated Listing Page" for vetted listing pages with direct job links, "Michael Page" for michaelpage.ae job listing URLs, "Aventus Global" for aventusglobal.com/jobs, "Venture Search" for venturesearch.com/jobs, "Mubadala Takafo" for Mubadala's professional careers page, "Alvarez & Marsal Careers" for Alvarez & Marsal country search pages, or "Consider Portfolio Board" for portfolio jobs boards such as Wa'ed.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Source Type</label></th>
                            <td>
                                <select name="source_type">
                                    <option value="company">Company (Direct)</option>
                                    <option value="recruiter">Recruiter/Agency</option>
                                    <option value="job_aggregator">Job Aggregator</option>
                                </select>
                                <p class="description">Select whether this feed comes directly from a company, through a recruiter/agency, or from a job aggregator.</p>
                            </td>
                        </tr>
                    </table>
                    <button type="submit" class="button button-primary">Add XML Feed</button>
                </form>
            </div>
            
            <!-- Test Results -->
            <div id="feed-test-results" style="display: none;">
                <h3>Test Results</h3>
                <div id="test-output"></div>
            </div>
            
            <!-- Edit Feed Modal -->
            <div id="edit-feed-modal" class="feed-modal" style="display: none;">
                <div class="modal-content">
                    <span class="close-modal">&times;</span>
                    <h2>Edit Feed Configuration</h2>
                    <form id="edit-feed-form">
                        <input type="hidden" id="edit-feed-type" name="feed_type">
                        <input type="hidden" id="edit-feed-original-key" name="original_key">
                        
                        <table class="form-table">
                            <tr>
                                <th><label>Feed Key</label></th>
                                <td><input type="text" id="edit-feed-key" name="feed_key" required /></td>
                            </tr>
                            <tr>
                                <th><label>Company/Source Name</label></th>
                                <td><input type="text" id="edit-feed-name" name="feed_name" required /></td>
                            </tr>
                            <tr class="workday-fields">
                                <th><label>Base URL</label></th>
                                <td><input type="url" id="edit-base-url" name="base_url" /></td>
                            </tr>
                            <tr class="workday-fields">
                                <th><label>Endpoint</label></th>
                                <td><input type="text" id="edit-endpoint" name="endpoint" /></td>
                            </tr>
                            <tr class="workday-fields">
                                <th><label>Careers Path</label></th>
                                <td><input type="text" id="edit-careers-path" name="careers_path" /></td>
                            </tr>
                            <tr class="xml-fields" style="display: none;">
                                <th><label>Feed URL</label></th>
                                <td><input type="url" id="edit-feed-url" name="feed_url" /></td>
                            </tr>
                            <tr class="xml-fields" style="display: none;">
                                <th><label>Feed Type</label></th>
                                <td>
                                    <select id="edit-xml-type" name="xml_type">
                                        <option value="sitemap">XML Sitemap</option>
                                        <option value="website">Website Page</option>
                                        <option value="rss">RSS Feed</option>
                                        <option value="wp_job_manager_rss">WP Job Manager RSS</option>
                                        <option value="indeed">Indeed Feed</option>
                                        <option value="greenhouse">Greenhouse API</option>
                                        <option value="workable">Workable Search</option>
                                        <option value="workable_board">Workable Company Board</option>
                                        <option value="bayt_careers">Bayt Careers</option>
                                        <option value="successfactors">SAP SuccessFactors</option>
                                        <option value="phenom">Phenom</option>
                                        <option value="talentbrew_search">TalentBrew Search</option>
                                        <option value="smartrecruiters">SmartRecruiters</option>
                                        <option value="lever">Lever</option>
                                        <option value="recruitee">Recruitee</option>
                                        <option value="job_listing_page">Curated Listing Page</option>
                                        <option value="jisr_careers">Jisr Careers</option>
                                        <option value="oracle_cx">Oracle Candidate Experience</option>
                                        <option value="goldman_higher">Goldman Sachs Higher</option>
                                        <option value="deutsche_bank_beesite">Deutsche Bank Beesite</option>
                                        <option value="comeet">Comeet</option>
                                        <option value="agfund">AGFUND Careers</option>
                                        <option value="teamtailor_rss">Teamtailor RSS</option>
                                        <option value="eightfold">Eightfold</option>
                                        <option value="michael_page">Michael Page</option>
                                        <option value="aventus">Aventus Global</option>
                                        <option value="venture_search">Venture Search</option>
                                        <option value="mubadala_takafo">Mubadala Takafo</option>
                                        <option value="alvarez_marsal">Alvarez & Marsal Careers</option>
                                        <option value="consider_board">Consider Portfolio Board</option>
                                        <option value="custom">Custom XML</option>
                                    </select>
                                </td>
                            </tr>
                            <tr class="xml-fields" style="display: none;">
                                <th><label>Source Type</label></th>
                                <td>
                                    <select id="edit-source-type" name="source_type">
                                        <option value="company">Company (Direct)</option>
                                        <option value="recruiter">Recruiter/Agency</option>
                                        <option value="job_aggregator">Job Aggregator</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Status</label></th>
                                <td>
                                    <select id="edit-status" name="status">
                                        <option value="working">Working</option>
                                        <option value="untested">Untested</option>
                                        <option value="error">Error</option>
                                        <option value="auth_required">Auth Required</option>
                                        <option value="disabled">Disabled</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary">Save Changes</button>
                            <button type="button" class="button cancel-edit">Cancel</button>
                        </p>
                    </form>
                </div>
            </div>
            
            <!-- Bulk Operations -->
            <div class="bulk-operations" style="margin-top: 20px;">
                <h3>Bulk Operations</h3>
                <p class="description">Fetch imports are filtered to investment and finance roles, then queued for editorial review before publishing.</p>
                <select id="bulk-action">
                    <option value="">Select Action</option>
                    <option value="test">Test Selected Feeds</option>
                    <option value="fetch">Fetch Roles from Selected</option>
                    <option value="enable">Enable Selected</option>
                    <option value="disable">Disable Selected</option>
                    <option value="remove">Remove Selected</option>
                </select>
                <button class="button" id="apply-bulk-action">Apply</button>
                <span id="bulk-status"></span>
            </div>

            <!-- Auto Submit Feeds -->
            <div class="sffc-feed-section sffc-auto-submit-feeds">
                <h2>Auto Submit Feeds</h2>
                <p class="description">Feeds where MENA Careers can attempt candidate-authorised application submission. Greenhouse is the first supported provider and is detected from the existing feed configuration.</p>
                <?php $this->render_auto_submit_feeds(); ?>
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
        
        /* Modal Styles */
        .feed-modal {
            position: fixed;
            z-index: 100000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 60%;
            max-width: 700px;
            border-radius: 4px;
        }
        .close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close-modal:hover,
        .close-modal:focus {
            color: #000;
        }
        .bulk-operations {
            background: white;
            padding: 20px;
            border: 1px solid #ccd0d4;
            margin-top: 20px;
        }
        .wp-list-table input[type="checkbox"] {
            margin-right: 8px;
        }
        .sffc-auto-submit-feeds {
            margin-top: 20px;
        }
        .sffc-auto-submit-feeds .widefat code {
            display: block;
            white-space: normal;
            word-break: break-word;
        }
        .sffc-auto-submit-feeds__capabilities {
            margin: 0;
            padding-left: 18px;
        }
        </style>
        <?php
    }

    private function render_auto_submit_feeds() {
        $feeds = $this->get_auto_submit_greenhouse_feeds();
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="13%">Feed</th>
                    <th width="12%">Board Token</th>
                    <th width="15%">Company</th>
                    <th width="24%">Fetch Enrichment</th>
                    <th width="24%">Browser Submit Config</th>
                    <th width="12%">Readiness</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($feeds)) : ?>
                    <tr>
                        <td colspan="6">No Greenhouse feeds found.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($feeds as $feed) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($feed['key']); ?></strong>
                            <br><span><?php echo esc_html($feed['source_type']); ?></span>
                        </td>
                        <td><code><?php echo esc_html($feed['board_token']); ?></code></td>
                        <td>
                            <?php echo esc_html($feed['company_name']); ?>
                            <?php if (!empty($feed['allowed_locations'])) : ?>
                                <br><small><?php echo esc_html('Locations: ' . implode(', ', $feed['allowed_locations'])); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <code><?php echo esc_html($feed['jobs_api_url']); ?></code>
                            <code><?php echo esc_html($feed['questions_url_pattern']); ?></code>
                        </td>
                        <td>
                            <code><?php echo esc_html($feed['hosted_job_url_pattern']); ?></code>
                            <code><?php echo esc_html($feed['submit_url_pattern']); ?></code>
                            <ul class="sffc-auto-submit-feeds__capabilities">
                                <?php foreach ($feed['required_runtime_data'] as $item) : ?>
                                    <li><?php echo esc_html($item); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                        <td>
                            <span class="feed-status <?php echo esc_attr($feed['readiness_class']); ?>"><?php echo esc_html($feed['readiness']); ?></span>
                            <br><small><?php echo esc_html($feed['method']); ?></small>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function get_auto_submit_greenhouse_feeds() {
        $feeds = [];

        foreach ($this->get_xml_feeds() as $key => $feed) {
            if (($feed['type'] ?? '') !== 'greenhouse') {
                continue;
            }

            $url = (string) ($feed['url'] ?? '');
            $board_token = $this->extract_greenhouse_board_token($url);
            if ($board_token === '') {
                continue;
            }

            $company_name = sanitize_text_field((string) ($feed['company_name'] ?? ($feed['name'] ?? ucwords(str_replace('_', ' ', $key)))));
            $jobs_api_url = 'https://boards-api.greenhouse.io/v1/boards/' . rawurlencode($board_token) . '/jobs?content=true';

            $feeds[] = [
                'key' => sanitize_key($key),
                'company_name' => $company_name,
                'source_type' => sanitize_text_field((string) ($feed['source_type'] ?? 'company')),
                'source_platform' => sanitize_text_field((string) ($feed['source_platform'] ?? 'Greenhouse')),
                'board_token' => $board_token,
                'jobs_api_url' => $jobs_api_url,
                'questions_url_pattern' => 'https://boards-api.greenhouse.io/v1/boards/' . $board_token . '/jobs/{job_id}?questions=true',
                'hosted_job_url_pattern' => 'https://job-boards.greenhouse.io/' . $board_token . '/jobs/{job_id}',
                'submit_url_pattern' => 'https://boards.greenhouse.io/' . $board_token . '/jobs/{job_id}',
                'method' => 'Browser form automation',
                'readiness' => 'Ready to map',
                'readiness_class' => 'status-working',
                'allowed_locations' => array_values(array_filter(array_map('sanitize_text_field', (array) ($feed['allowed_locations'] ?? [])))),
                'company_logo' => esc_url_raw((string) ($feed['company_logo'] ?? '')),
                'required_runtime_data' => [
                    'job_id from fetched Greenhouse job id',
                    'questions schema from ?questions=true',
                    'candidate identity, phone, CV, location, and required custom answers',
                    'confirmation capture after hosted form submit',
                ],
            ];
        }

        usort($feeds, static function ($a, $b) {
            return strcasecmp($a['company_name'], $b['company_name']);
        });

        return $feeds;
    }

    private function extract_greenhouse_board_token($url) {
        $url = (string) $url;
        if ($url === '') {
            return '';
        }

        if (preg_match('~boards-api(?:\.eu)?\.greenhouse\.io/v1/boards/([^/?#]+)/jobs~i', $url, $matches)) {
            return sanitize_key(rawurldecode($matches[1]));
        }

        if (preg_match('~(?:boards|job-boards)(?:\.eu)?\.greenhouse\.io/([^/?#]+)/jobs~i', $url, $matches)) {
            return sanitize_key(rawurldecode($matches[1]));
        }

        return '';
    }

    private function extract_greenhouse_job_id_from_job(array $job) {
        foreach (['external_id', 'job_id', 'greenhouse_job_id'] as $key) {
            $value = trim((string) ($job[$key] ?? ''));
            if ($value !== '' && preg_match('/^\d+$/', $value)) {
                return $value;
            }
        }

        foreach (['id', 'url', 'application_url'] as $key) {
            $value = trim((string) ($job[$key] ?? ''));
            if ($value !== '' && preg_match('/(?:jobs\/|_)(\d{6,})(?:[/?#]|$)/', $value, $matches)) {
                return $matches[1];
            }
        }

        return '';
    }

    private function fetch_greenhouse_application_schema($board_token, $job_id, array $job, array $feed, $source_key) {
        $hosted_url = $this->resolve_greenhouse_hosted_job_url($board_token, $job_id, $job);
        $submit_url = $this->build_greenhouse_submit_url($board_token, $job_id, $hosted_url);
        $schema_url = 'https://boards-api.greenhouse.io/v1/boards/' . rawurlencode((string) $board_token) . '/jobs/' . rawurlencode((string) $job_id) . '?questions=true';
        $response = wp_remote_get($schema_url, [
            'timeout' => 30,
            'redirection' => 3,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return $this->fetch_greenhouse_application_schema_from_hosted_page($board_token, $job_id, $job, $feed, $source_key, $hosted_url, $submit_url);
        }

        $payload = json_decode((string) wp_remote_retrieve_body($response), true);
        if (empty($payload['id']) || empty($payload['questions']) || !is_array($payload['questions'])) {
            return $this->fetch_greenhouse_application_schema_from_hosted_page($board_token, $job_id, $job, $feed, $source_key, $hosted_url, $submit_url);
        }

        return $this->build_greenhouse_application_schema_from_payload($payload, $job, $feed, $source_key, $schema_url, $hosted_url, $submit_url, $board_token, $job_id);
    }

    private function build_greenhouse_application_schema_from_payload(array $payload, array $job, array $feed, $source_key, $schema_url, $hosted_url, $submit_url, $board_token, $job_id) {
        $questions = [];
        foreach ((array) ($payload['questions'] ?? []) as $question) {
            if (!is_array($question)) {
                continue;
            }

            $fields = [];
            foreach ((array) ($question['fields'] ?? []) as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $fields[] = [
                    'name' => sanitize_text_field((string) ($field['name'] ?? '')),
                    'type' => sanitize_text_field((string) ($field['type'] ?? '')),
                    'values' => $this->normalize_greenhouse_schema_values((array) ($field['values'] ?? [])),
                ];
            }

            $questions[] = [
                'label' => sanitize_text_field((string) ($question['label'] ?? '')),
                'required' => !empty($question['required']),
                'description' => wp_kses_post((string) ($question['description'] ?? '')),
                'fields' => $fields,
            ];
        }

        if (empty($questions)) {
            return [];
        }

        $location_questions = [];
        foreach ((array) ($payload['location_questions'] ?? []) as $question) {
            if (!is_array($question)) {
                continue;
            }

            $fields = [];
            foreach ((array) ($question['fields'] ?? []) as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $fields[] = [
                    'name' => sanitize_text_field((string) ($field['name'] ?? '')),
                    'type' => sanitize_text_field((string) ($field['type'] ?? '')),
                    'values' => $this->normalize_greenhouse_schema_values((array) ($field['values'] ?? [])),
                ];
            }

            $location_questions[] = [
                'label' => sanitize_text_field((string) ($question['label'] ?? '')),
                'required' => !empty($question['required']),
                'fields' => $fields,
            ];
        }

        $location_value = '';
        if (is_array($payload['location'] ?? null)) {
            $location_value = (string) ($payload['location']['name'] ?? '');
        } elseif (!empty($payload['job_post_location'])) {
            $location_value = (string) $payload['job_post_location'];
        }

        return [
            'provider' => 'greenhouse',
            'source_key' => sanitize_key((string) $source_key),
            'source_platform' => sanitize_text_field((string) ($feed['source_platform'] ?? 'Greenhouse')),
            'board_token' => sanitize_key((string) $board_token),
            'job_id' => sanitize_text_field((string) $job_id),
            'internal_job_id' => sanitize_text_field((string) ($payload['internal_job_id'] ?? '')),
            'title' => sanitize_text_field((string) ($payload['title'] ?? ($job['title'] ?? ''))),
            'company_name' => sanitize_text_field((string) ($payload['company_name'] ?? ($feed['company_name'] ?? ($job['company'] ?? '')))),
            'location' => sanitize_text_field($location_value !== '' ? $location_value : (string) ($job['location'] ?? '')),
            'schema_url' => esc_url_raw($schema_url),
            'hosted_url' => esc_url_raw($hosted_url),
            'submit_url' => esc_url_raw($submit_url),
            'absolute_url' => esc_url_raw((string) ($payload['absolute_url'] ?? ($payload['public_url'] ?? ($job['url'] ?? '')))),
            'data_compliance' => is_array($payload['data_compliance'] ?? null) ? $payload['data_compliance'] : [],
            'questions' => $questions,
            'location_questions' => $location_questions,
            'required_question_count' => count(array_filter($questions, static function ($question) {
                return !empty($question['required']);
            })),
            'field_count' => array_sum(array_map(static function ($question) {
                return count((array) ($question['fields'] ?? []));
            }, $questions)),
            'discovered_at' => current_time('mysql'),
        ];
    }

    private function resolve_greenhouse_hosted_job_url($board_token, $job_id, array $job) {
        foreach (['absolute_url', 'application_url', 'url', 'hosted_url'] as $key) {
            $candidate = esc_url_raw((string) ($job[$key] ?? ''));
            if ($candidate !== '' && preg_match('~job-boards(?:\.eu)?\.greenhouse\.io/[^/?#]+/jobs/\d+~i', $candidate)) {
                return $candidate;
            }
        }

        return esc_url_raw('https://job-boards.greenhouse.io/' . rawurlencode((string) $board_token) . '/jobs/' . rawurlencode((string) $job_id));
    }

    private function build_greenhouse_submit_url($board_token, $job_id, $hosted_url = '') {
        $host = wp_parse_url((string) $hosted_url, PHP_URL_HOST);
        $submit_host = (is_string($host) && stripos($host, 'job-boards.eu.greenhouse.io') !== false)
            ? 'boards.eu.greenhouse.io'
            : 'boards.greenhouse.io';

        return esc_url_raw('https://' . $submit_host . '/' . rawurlencode((string) $board_token) . '/jobs/' . rawurlencode((string) $job_id));
    }

    private function fetch_greenhouse_application_schema_from_hosted_page($board_token, $job_id, array $job, array $feed, $source_key, $hosted_url, $submit_url) {
        if ($hosted_url === '') {
            return [];
        }

        $response = wp_remote_get($hosted_url, [
            'timeout' => 30,
            'redirection' => 3,
            'headers' => [
                'Accept' => 'text/html',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $html = (string) wp_remote_retrieve_body($response);
        if (!preg_match('~window\.__remixContext\s*=\s*(\{.*?\});\s*</script>~s', $html, $matches)) {
            return [];
        }

        $context = json_decode($matches[1], true);
        if (!is_array($context)) {
            return [];
        }

        $route_payload = $this->find_greenhouse_remix_job_payload($context);
        if (empty($route_payload['jobPost']) || !is_array($route_payload['jobPost'])) {
            return [];
        }

        $job_post = (array) $route_payload['jobPost'];
        $payload = [
            'id' => $route_payload['jobPostId'] ?? $job_id,
            'internal_job_id' => $route_payload['jobPostId'] ?? $job_id,
            'title' => $job_post['title'] ?? ($job['title'] ?? ''),
            'company_name' => $job_post['company_name'] ?? ($feed['company_name'] ?? ($job['company'] ?? '')),
            'job_post_location' => $job_post['job_post_location'] ?? ($job['location'] ?? ''),
            'public_url' => $job_post['public_url'] ?? $hosted_url,
            'questions' => is_array($job_post['questions'] ?? null) ? $job_post['questions'] : [],
            'location_questions' => is_array($job_post['location_questions'] ?? null) ? $job_post['location_questions'] : [],
            'data_compliance' => is_array($job_post['data_compliance'] ?? null) ? $job_post['data_compliance'] : [],
        ];

        if (!empty($route_payload['submitPath'])) {
            $submit_url = esc_url_raw((string) $route_payload['submitPath']);
        }

        return $this->build_greenhouse_application_schema_from_payload($payload, $job, $feed, $source_key, $hosted_url, $hosted_url, $submit_url, $board_token, $job_id);
    }

    private function find_greenhouse_remix_job_payload(array $node) {
        if (!empty($node['jobPost']) && is_array($node['jobPost']) && !empty($node['jobPost']['questions'])) {
            return $node;
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $found = $this->find_greenhouse_remix_job_payload($value);
                if (!empty($found)) {
                    return $found;
                }
            }
        }

        return [];
    }

    private function normalize_greenhouse_schema_values(array $values) {
        $normalized = [];
        foreach ($values as $value) {
            if (!is_array($value)) {
                continue;
            }

            $normalized[] = [
                'label' => sanitize_text_field((string) ($value['label'] ?? '')),
                'value' => sanitize_text_field((string) ($value['value'] ?? '')),
            ];
        }

        return $normalized;
    }
    
    private function render_workday_feeds($source_type_filter = '') {
        $feeds = $this->get_workday_feeds();
        
        foreach ($feeds as $key => $feed) {
            $source_type = $feed['source_type'] ?? $this->get_workday_feed_source_type($key, $feed);
            if ($source_type_filter !== '' && $source_type !== $source_type_filter) {
                continue;
            }

            $status = $feed['status'] ?? 'untested';
            $status_class = $this->get_status_class($feed['status'] ?? 'untested');
            $fetch_disabled = in_array($status, ['disabled', 'auth_required', 'needs_fix'], true);
            ?>
            <tr data-feed-key="<?php echo esc_attr($key); ?>">
                <td><input type="checkbox" class="feed-checkbox" data-type="workday" data-key="<?php echo esc_attr($key); ?>" <?php disabled($fetch_disabled); ?> /></td>
                <td><strong><?php echo esc_html($feed['company_name'] ?? ucfirst($key)); ?></strong></td>
                <td><code><?php echo esc_html($feed['base_url'] . $feed['endpoint']); ?></code></td>
                <td><span class="feed-status <?php echo $status_class; ?>"><?php echo esc_html($status); ?></span></td>
                <td class="job-count">-</td>
                <td>
                    <button class="button test-feed" data-type="workday" data-key="<?php echo esc_attr($key); ?>">Test</button>
                    <button class="button fetch-jobs" data-type="workday" data-key="<?php echo esc_attr($key); ?>" <?php disabled($fetch_disabled); ?>>Fetch <?php echo esc_html((string) self::FETCH_LIMIT); ?> Roles</button>
                    <button class="button edit-feed" data-type="workday" data-key="<?php echo esc_attr($key); ?>">Edit</button>
                    <?php if (!empty($feed['custom']) || isset($_GET['allow_edit_all'])): ?>
                    <button class="button remove-feed" data-type="workday" data-key="<?php echo esc_attr($key); ?>">Remove</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
        }
    }
    
    private function render_xml_feeds($source_type_filter = '') {
        $feeds = $this->get_xml_feeds();
        
        foreach ($feeds as $key => $feed) {
            $source_type = $feed['source_type'] ?? '';
            if ($source_type_filter === 'job_aggregator' && $source_type !== 'job_aggregator') {
                continue;
            }
            if ($source_type_filter === '' && $source_type === 'job_aggregator') {
                continue;
            }
            $status = sanitize_html_class($feed['status'] ?? 'untested');
            $fetch_disabled = in_array($status, ['disabled', 'auth_required', 'needs_fix', 'error'], true);
            ?>
            <tr data-feed-key="<?php echo esc_attr($key); ?>">
                <td><input type="checkbox" class="feed-checkbox" data-type="xml" data-key="<?php echo esc_attr($key); ?>" <?php disabled($fetch_disabled); ?> /></td>
                <td><strong><?php echo esc_html($feed['name'] ?? ucfirst($key)); ?></strong></td>
                <td><code><?php echo esc_html($feed['url']); ?></code></td>
                <td><span class="feed-status status-<?php echo esc_attr($status); ?>"><?php echo esc_html($feed['status'] ?? 'untested'); ?></span></td>
                <td class="job-count">-</td>
                <td>
                    <button class="button test-feed" data-type="xml" data-key="<?php echo esc_attr($key); ?>">Test</button>
                    <button class="button fetch-jobs" data-type="xml" data-key="<?php echo esc_attr($key); ?>" <?php disabled($fetch_disabled); ?>>Fetch <?php echo esc_html((string) self::FETCH_LIMIT); ?> Roles</button>
                    <button class="button edit-feed" data-type="xml" data-key="<?php echo esc_attr($key); ?>">Edit</button>
                    <?php if (!empty($feed['custom']) || isset($_GET['allow_edit_all'])): ?>
                    <button class="button remove-feed" data-type="xml" data-key="<?php echo esc_attr($key); ?>">Remove</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
        }
    }
    
    private function get_workday_feeds() {
        // Get default feeds from the Workday fetcher
        if (class_exists('SFFC_Workday_Job_Fetcher_V2')) {
            $fetcher = new SFFC_Workday_Job_Fetcher_V2();
            $reflection = new ReflectionClass($fetcher);
            $property = $reflection->getProperty('workday_instances');
            $property->setAccessible(true);
            $default_feeds = $property->getValue($fetcher);
            
            // Add company names
            foreach ($default_feeds as $key => &$feed) {
                $feed['company_name'] = $this->get_company_display_name($key);
                $feed['source_type'] = $this->get_workday_feed_source_type($key, $feed);
            }
            
            // Merge with custom feeds
            $custom_feeds = get_option('sffc_custom_workday_feeds', []);
            foreach ($custom_feeds as $key => &$feed) {
                $feed['source_type'] = $this->get_workday_feed_source_type($key, $feed);
            }
            
            return array_merge($default_feeds, $custom_feeds);
        }
        
        return get_option('sffc_custom_workday_feeds', []);
    }

    private function get_workday_feed_source_type($key, $feed = []) {
        if (!empty($feed['source_type'])) {
            return sanitize_key($feed['source_type']);
        }

        $banking_feeds = [
            'fca',
            'imf',
            'ubs',
            'deutschebank',
            'citi',
            'bankofamerica',
            'morganstanley',
            'jpmorgan',
            'statestreet',
            'cibc',
            'santander',
            'lloyds',
            'rothschild',
            'houlihan_lokey',
            'moelis',
            'pru_pgim',
        ];

        return in_array($key, $banking_feeds, true) ? 'banking' : 'job_aggregator';
    }
    
    private function get_xml_feeds() {
        // Get default feeds from XML fetcher
        if (class_exists('SFFC_XML_Job_Fetcher')) {
            $fetcher = new SFFC_XML_Job_Fetcher();
            $reflection = new ReflectionClass($fetcher);
            $property = $reflection->getProperty('xml_sources');
            $property->setAccessible(true);
            $default_feeds = $property->getValue($fetcher);
            
            // Merge with custom feeds
            $custom_feeds = get_option('sffc_custom_xml_feeds', []);
            
            return array_merge($default_feeds, $custom_feeds);
        }
        
        return get_option('sffc_custom_xml_feeds', []);
    }
    
    private function get_company_display_name($key) {
        $names = [
            'blackstone' => 'Blackstone',
            'barings' => 'Barings',
            'moelis' => 'Moelis & Company',
            'houlihan_lokey' => 'Houlihan Lokey',
            'rothschild' => 'Rothschild & Co',
            'lloyds' => 'Lloyds Banking Group',
            'santander' => 'Santander',
            'cibc' => 'CIBC',
            'statestreet' => 'State Street',
            'fca' => 'FCA',
            'aviva' => 'Aviva',
            'mfs' => 'MFS Investment Management',
            'jpmorgan' => 'J.P. Morgan',
            'morganstanley' => 'Morgan Stanley',
            'bankofamerica' => 'Bank of America',
            'goldman_sachs' => 'Goldman Sachs',
            'juliusbaer' => 'Julius Baer',
            'juliusbaer_uae' => 'Julius Baer UAE',
            'capitalgroup' => 'Capital Group',
            'quilter' => 'Quilter',
            'cdpq' => 'CDPQ',
            'cdpq_infra' => 'CDPQ Infra',
            'robeco' => 'Robeco',
            'apollo' => 'Apollo Global Management',
            'ardian' => 'Ardian',
            'three_i_intern_career' => '3i',
            'icg_external_careers' => 'ICG',
            'harbourvest_hvp' => 'HarbourVest Partners',
            'capitaland_development' => 'CapitaLand Development',
            'keppel_careers' => 'Keppel',
            'cambridge_associates' => 'Cambridge Associates',
            'ontario_teachers_london' => 'Ontario Teachers London',
            'bain_capital_public' => 'Bain Capital',
            'apexgroup_middle_east' => 'Apex Group Middle East'
        ];
        
        return $names[$key] ?? ucwords(str_replace('_', ' ', $key));
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
     * AJAX: Test single feed
     */
    public function ajax_test_single_feed() {
        ob_start();
        
        if (!current_user_can('manage_options')) {
            ob_end_clean();
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('sffc_feed_manager', 'nonce');
        
        $type = sanitize_text_field($_POST['type']);
        $key = sanitize_text_field($_POST['key']);
        
        if ($type === 'workday') {
            $result = $this->test_workday_feed($key);
        } else {
            $result = $this->test_xml_feed($key);
        }
        
        ob_end_clean();
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Fetch jobs from specific feed
     */
    public function ajax_fetch_from_feed() {
        ob_start();
        
        if (!current_user_can('manage_options')) {
            ob_end_clean();
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('sffc_feed_manager', 'nonce');
        
        // Increase timeout for fetching jobs
        @set_time_limit(300); // 5 minutes
        @ini_set('max_execution_time', 300);
        
        $type = sanitize_text_field($_POST['type']);
        $key = sanitize_text_field($_POST['key']);
        $save = isset($_POST['save']) && $_POST['save'] === 'true';
        
        $result = [
            'success' => false,
            'jobs' => [],
            'saved' => 0,
            'updated' => 0,
            'skipped' => 0,
            'eligible' => 0,
            'schemas_discovered' => 0,
            'schemas_cached' => 0,
            'schemas_failed' => 0,
            'error' => '',
        ];

        $jobs_result = $this->fetch_feed_jobs($type, $key);

        if (!empty($jobs_result['success'])) {
            $result['success'] = true;
            $result['jobs'] = $jobs_result['jobs'];

            if ($save) {
                $import_stats = $this->import_jobs_to_drafts($jobs_result['jobs'], $type, $key);
                $result['saved'] = $import_stats['saved'];
                $result['updated'] = $import_stats['updated'];
                $result['skipped'] = $import_stats['skipped'];
                $result['eligible'] = $import_stats['eligible'];
                $result['schemas_discovered'] = $import_stats['schemas_discovered'];
                $result['schemas_cached'] = $import_stats['schemas_cached'];
                $result['schemas_failed'] = $import_stats['schemas_failed'];
            }
        } else {
            $result['error'] = $jobs_result['error'] ?? __('No jobs were returned from this feed.', 'senna-finance');
        }
        
        ob_end_clean();
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Add Workday feed
     */
    public function ajax_add_workday_feed() {
        ob_start();
        
        if (!current_user_can('manage_options')) {
            ob_end_clean();
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('sffc_feed_manager', 'nonce');
        
        $key = sanitize_text_field($_POST['company_key']);
        $feed = [
            'company_name' => sanitize_text_field($_POST['company_name']),
            'base_url' => esc_url_raw($_POST['base_url']),
            'endpoint' => sanitize_text_field($_POST['endpoint']),
            'careers_path' => sanitize_text_field($_POST['careers_path']),
            'status' => 'new',
            'source_type' => 'banking',
            'custom' => true
        ];
        
        $custom_feeds = get_option('sffc_custom_workday_feeds', []);
        $custom_feeds[$key] = $feed;
        update_option('sffc_custom_workday_feeds', $custom_feeds);
        
        ob_end_clean();
        wp_send_json_success(['message' => 'Feed added successfully']);
    }
    
    /**
     * AJAX: Add XML feed
     */
    public function ajax_add_xml_feed() {
        ob_start();
        
        if (!current_user_can('manage_options')) {
            ob_end_clean();
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('sffc_feed_manager', 'nonce');
        
        $key = sanitize_text_field($_POST['source_key']);
        $feed_type = sanitize_text_field($_POST['feed_type']);
        $feed_url = esc_url_raw($_POST['feed_url']);
        if ($feed_type === 'wp_job_manager_rss') {
            $feed_type = 'rss';
            $feed_url = $this->normalize_wp_job_manager_rss_url($feed_url);
        }

        $feed = [
            'name' => sanitize_text_field($_POST['source_name']),
            'url' => $feed_url,
            'type' => $feed_type,
            'source_type' => sanitize_text_field($_POST['source_type']),
            'custom' => true
        ];
        
        $custom_feeds = get_option('sffc_custom_xml_feeds', []);
        $custom_feeds[$key] = $feed;
        update_option('sffc_custom_xml_feeds', $custom_feeds);
        
        ob_end_clean();
        wp_send_json_success(['message' => 'Feed added successfully']);
    }
    
    /**
     * AJAX: Remove feed
     */
    public function ajax_remove_feed() {
        ob_start();
        
        if (!current_user_can('manage_options')) {
            ob_end_clean();
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('sffc_feed_manager', 'nonce');
        
        $type = sanitize_text_field($_POST['type']);
        $key = sanitize_text_field($_POST['key']);
        
        if ($type === 'workday') {
            // Check if it's a custom feed
            $custom_feeds = get_option('sffc_custom_workday_feeds', []);
            if (isset($custom_feeds[$key])) {
                unset($custom_feeds[$key]);
                update_option('sffc_custom_workday_feeds', $custom_feeds);
            } else {
                // For default feeds, mark as disabled instead of removing
                $disabled_feeds = get_option('sffc_disabled_feeds', []);
                $disabled_feeds[] = 'workday_' . $key;
                update_option('sffc_disabled_feeds', $disabled_feeds);
            }
        } else {
            $custom_feeds = get_option('sffc_custom_xml_feeds', []);
            if (isset($custom_feeds[$key])) {
                unset($custom_feeds[$key]);
                update_option('sffc_custom_xml_feeds', $custom_feeds);
            } else {
                $disabled_feeds = get_option('sffc_disabled_feeds', []);
                $disabled_feeds[] = 'xml_' . $key;
                update_option('sffc_disabled_feeds', $disabled_feeds);
            }
        }
        
        ob_end_clean();
        wp_send_json_success(['message' => 'Feed removed']);
    }
    
    /**
     * AJAX: Edit feed
     */
    public function ajax_edit_feed() {
        ob_start();
        
        if (!current_user_can('manage_options')) {
            ob_end_clean();
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('sffc_feed_manager', 'nonce');
        
        $type = sanitize_text_field($_POST['feed_type']);
        $original_key = sanitize_text_field($_POST['original_key']);
        $new_key = sanitize_text_field($_POST['feed_key']);
        
        if ($type === 'workday') {
            $all_feeds = $this->get_workday_feeds();
            $custom_feeds = get_option('sffc_custom_workday_feeds', []);
            
            // Build updated feed data
            $feed_data = [
                'company_name' => sanitize_text_field($_POST['feed_name']),
                'base_url' => esc_url_raw($_POST['base_url']),
                'endpoint' => sanitize_text_field($_POST['endpoint']),
                'careers_path' => sanitize_text_field($_POST['careers_path']),
                'status' => sanitize_text_field($_POST['status']),
                'source_type' => sanitize_text_field($_POST['source_type'] ?? ($all_feeds[$original_key]['source_type'] ?? 'banking')),
                'custom' => true
            ];
            
            // If renaming, remove old key
            if ($original_key !== $new_key && isset($custom_feeds[$original_key])) {
                unset($custom_feeds[$original_key]);
            }
            
            // Save with new key
            $custom_feeds[$new_key] = $feed_data;
            update_option('sffc_custom_workday_feeds', $custom_feeds);
            
        } else {
            $custom_feeds = get_option('sffc_custom_xml_feeds', []);
            
            $xml_type = sanitize_text_field($_POST['xml_type']);
            $feed_url = esc_url_raw($_POST['feed_url']);
            if ($xml_type === 'wp_job_manager_rss') {
                $xml_type = 'rss';
                $feed_url = $this->normalize_wp_job_manager_rss_url($feed_url);
            }

            $feed_data = [
                'name' => sanitize_text_field($_POST['feed_name']),
                'url' => $feed_url,
                'type' => $xml_type,
                'source_type' => sanitize_text_field($_POST['source_type'] ?? 'company'),
                'status' => sanitize_text_field($_POST['status']),
                'custom' => true
            ];
            
            if ($original_key !== $new_key && isset($custom_feeds[$original_key])) {
                unset($custom_feeds[$original_key]);
            }
            
            $custom_feeds[$new_key] = $feed_data;
            update_option('sffc_custom_xml_feeds', $custom_feeds);
        }
        
        ob_end_clean();
        wp_send_json_success(['message' => 'Feed updated successfully']);
    }
    
    /**
     * AJAX: Get feed data for editing
     */
    public function ajax_get_feed_data() {
        ob_start();
        
        if (!current_user_can('manage_options')) {
            ob_end_clean();
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('sffc_feed_manager', 'nonce');
        
        $type = sanitize_text_field($_POST['type']);
        $key = sanitize_text_field($_POST['key']);
        
        if ($type === 'workday') {
            $feeds = $this->get_workday_feeds();
        } else {
            $feeds = $this->get_xml_feeds();
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
    
    /**
     * AJAX: Bulk operations
     */
    public function ajax_bulk_operation() {
        ob_start();
        
        if (!current_user_can('manage_options')) {
            ob_end_clean();
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('sffc_feed_manager', 'nonce');
        
        $action = sanitize_text_field($_POST['action_type']);
        $feeds = isset($_POST['feeds']) ? array_map('sanitize_text_field', $_POST['feeds']) : [];
        
        $results = [
            'success' => 0,
            'failed' => 0,
            'schemas_discovered' => 0,
            'schemas_cached' => 0,
            'schemas_failed' => 0,
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

                case 'fetch':
                    $fetch = $this->fetch_feed_jobs($type, $key);

                    if (!empty($fetch['success'])) {
                        $import = $this->import_jobs_to_drafts($fetch['jobs'], $type, $key);
                        if (($import['saved'] + $import['updated']) > 0) {
                            $results['success']++;
                        } else {
                            $results['failed']++;
                        }
                        $results['schemas_discovered'] += (int) ($import['schemas_discovered'] ?? 0);
                        $results['schemas_cached'] += (int) ($import['schemas_cached'] ?? 0);
                        $results['schemas_failed'] += (int) ($import['schemas_failed'] ?? 0);
                        $results['messages'][] = sprintf(
                            '%s: %d fetched, %d eligible, %d draft(s) queued, %d skipped, %d schema(s) discovered, %d cached',
                            $feed_id,
                            count($fetch['jobs']),
                            $import['eligible'],
                            $import['saved'],
                            $import['skipped'],
                            (int) ($import['schemas_discovered'] ?? 0),
                            (int) ($import['schemas_cached'] ?? 0)
                        );
                    } else {
                        $results['failed']++;
                        $results['messages'][] = sprintf(
                            '%s: %s',
                            $feed_id,
                            $fetch['error'] ?? 'Fetch failed'
                        );
                    }
                    break;
                    
                case 'remove':
                    // Reuse the remove logic
                    if ($type === 'workday') {
                        $custom_feeds = get_option('sffc_custom_workday_feeds', []);
                        if (isset($custom_feeds[$key])) {
                            unset($custom_feeds[$key]);
                            update_option('sffc_custom_workday_feeds', $custom_feeds);
                            $results['success']++;
                        }
                    } else {
                        $custom_feeds = get_option('sffc_custom_xml_feeds', []);
                        if (isset($custom_feeds[$key])) {
                            unset($custom_feeds[$key]);
                            update_option('sffc_custom_xml_feeds', $custom_feeds);
                            $results['success']++;
                        }
                    }
                    break;
                    
                case 'disable':
                    $disabled_feeds = get_option('sffc_disabled_feeds', []);
                    $disabled_feeds[] = $feed_id;
                    update_option('sffc_disabled_feeds', array_unique($disabled_feeds));
                    $results['success']++;
                    break;
                    
                case 'enable':
                    $disabled_feeds = get_option('sffc_disabled_feeds', []);
                    $disabled_feeds = array_diff($disabled_feeds, [$feed_id]);
                    update_option('sffc_disabled_feeds', $disabled_feeds);
                    $results['success']++;
                    break;
            }
        }
        
        ob_end_clean();
        wp_send_json_success($results);
    }

    private function fetch_feed_jobs($type, $key) {
        if ($type === 'workday') {
            if (!class_exists('SFFC_Workday_Job_Fetcher_V2')) {
                return ['success' => false, 'jobs' => [], 'error' => 'Workday fetcher not available'];
            }

            $fetcher = new SFFC_Workday_Job_Fetcher_V2();
            $jobs_result = $fetcher->get_jobs($key, ['limit' => self::FETCH_LIMIT]);

            if (is_wp_error($jobs_result)) {
                return ['success' => false, 'jobs' => [], 'error' => $jobs_result->get_error_message()];
            }

            $jobs = !empty($jobs_result['jobs']) && is_array($jobs_result['jobs']) ? $jobs_result['jobs'] : [];
            return ['success' => !empty($jobs), 'jobs' => $jobs, 'error' => empty($jobs) ? 'No jobs returned' : ''];
        }

        if (!class_exists('SFFC_XML_Job_Fetcher')) {
            return ['success' => false, 'jobs' => [], 'error' => 'XML fetcher not available'];
        }

        $feeds = $this->get_xml_feeds();
        if (empty($feeds[$key]['url'])) {
            return ['success' => false, 'jobs' => [], 'error' => 'Feed not found'];
        }

        $fetcher = new SFFC_XML_Job_Fetcher();
        $jobs = method_exists($fetcher, 'fetch_from_source_key')
            ? $fetcher->fetch_from_source_key($key, self::FETCH_LIMIT)
            : [];
        if (empty($jobs)) {
            $jobs = $fetcher->fetch_from_source($feeds[$key]['url'], self::FETCH_LIMIT);
        }

        return ['success' => !empty($jobs), 'jobs' => is_array($jobs) ? $jobs : [], 'error' => empty($jobs) ? 'No jobs returned' : ''];
    }

    private function import_jobs_to_drafts(array $jobs, $type, $source_key) {
        if (!class_exists('SFFC_CRM_Job_Draft')) {
            require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-job-draft.php';
        }

        $draft_model = new SFFC_CRM_Job_Draft();
        $stats = [
            'saved' => 0,
            'updated' => 0,
            'skipped' => 0,
            'eligible' => 0,
            'schemas_discovered' => 0,
            'schemas_cached' => 0,
            'schemas_failed' => 0,
        ];

        foreach ($jobs as $job) {
            $job = (array) $job;
            $payload = $this->normalize_feed_job_for_crm($job, $type, $source_key);
            if (empty($payload)) {
                $stats['skipped']++;
                continue;
            }

            $stats['eligible']++;

            $schema_result = $this->maybe_fetch_auto_submit_schema_for_job($job, $type, $source_key);
            if (!empty($schema_result['schema'])) {
                $job['auto_submit_schema'] = $schema_result['schema'];
                $job['auto_submit_schema_status'] = $schema_result['status'];
                if ($schema_result['status'] === 'cached') {
                    $stats['schemas_cached']++;
                } else {
                    $stats['schemas_discovered']++;
                }
            } elseif (($schema_result['status'] ?? '') === 'failed') {
                $stats['schemas_failed']++;
            }

            $existing_crm_post_id = $this->find_existing_crm_post_id($payload);
            if ($existing_crm_post_id > 0) {
                if (!empty($job['auto_submit_schema'])) {
                    $this->refresh_existing_crm_auto_submit_schema((int) $existing_crm_post_id, $job['auto_submit_schema'], (string) ($job['auto_submit_schema_status'] ?? 'discovered'));
                }
                $stats['skipped']++;
                continue;
            }

            $draft_payload = $this->build_draft_payload_from_feed_job($payload, $job, $type, $source_key);
            $existing_draft = $draft_model->find_duplicate($draft_payload);
            if ($existing_draft) {
                if ($this->refresh_existing_draft_dates_from_feed($draft_model, $existing_draft, $draft_payload)) {
                    $stats['updated']++;
                } else {
                    $stats['skipped']++;
                }
                continue;
            }

            $draft_id = $draft_model->create($draft_payload);
            if (!empty($draft_id)) {
                $stats['saved']++;
            } else {
                $stats['skipped']++;
            }
        }

        return $stats;
    }

    private function refresh_existing_draft_dates_from_feed(SFFC_CRM_Job_Draft $draft_model, array $existing_draft, array $draft_payload) {
        $draft_id = (int) ($existing_draft['id'] ?? 0);
        if ($draft_id <= 0) {
            return false;
        }

        $updates = [];
        foreach (['posted_at', 'raw_posted_at', 'raw_seniority', 'raw_experience_years'] as $field) {
            $incoming_value = sanitize_text_field((string) ($draft_payload[$field] ?? ''));
            $existing_value = sanitize_text_field((string) ($existing_draft[$field] ?? ''));
            if ($incoming_value !== '' && $incoming_value !== $existing_value) {
                $updates[$field] = $incoming_value;
            }
        }

        $incoming_payload = is_array($draft_payload['extracted_payload'] ?? null) ? $draft_payload['extracted_payload'] : [];
        $incoming_schema = is_array($incoming_payload['auto_submit_schema'] ?? null) ? $incoming_payload['auto_submit_schema'] : [];
        if (!empty($incoming_schema)) {
            $existing_payload = $existing_draft['extracted_payload'] ?? [];
            if (is_string($existing_payload) && $existing_payload !== '') {
                $decoded_payload = json_decode($existing_payload, true);
                $existing_payload = is_array($decoded_payload) ? $decoded_payload : [];
            }
            $existing_payload = is_array($existing_payload) ? $existing_payload : [];
            $existing_schema = is_array($existing_payload['auto_submit_schema'] ?? null) ? $existing_payload['auto_submit_schema'] : [];
            if (empty($existing_schema) || wp_json_encode($existing_schema) !== wp_json_encode($incoming_schema)) {
                $existing_payload['auto_submit_provider'] = $incoming_payload['auto_submit_provider'] ?? sanitize_key((string) ($incoming_schema['provider'] ?? ''));
                $existing_payload['auto_submit_supported'] = true;
                $existing_payload['auto_submit_schema_status'] = $incoming_payload['auto_submit_schema_status'] ?? 'discovered';
                $existing_payload['auto_submit_schema'] = $incoming_schema;
                $updates['extracted_payload'] = $existing_payload;
            }
        }

        if (empty($updates)) {
            return false;
        }

        return $draft_model->update($draft_id, $updates);
    }

    private function refresh_existing_crm_auto_submit_schema($crm_post_id, array $schema, $schema_status = 'discovered') {
        $crm_post_id = (int) $crm_post_id;
        if ($crm_post_id <= 0 || empty($schema)) {
            return false;
        }

        global $wpdb;

        $jobs_post_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT jobs_post_id FROM {$wpdb->prefix}sffc_crm_posts WHERE id = %d LIMIT 1",
            $crm_post_id
        ));

        if ($jobs_post_id <= 0) {
            $jobs_post_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT pm.post_id
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = %s
                   AND pm.meta_value = %s
                   AND p.post_type = %s
                 ORDER BY pm.meta_id DESC
                 LIMIT 1",
                '_crm_post_id',
                (string) $crm_post_id,
                'jobs'
            ));
        }

        if ($jobs_post_id <= 0) {
            return false;
        }

        update_post_meta($jobs_post_id, '_sffc_auto_submit_supported', '1');
        update_post_meta($jobs_post_id, '_sffc_auto_submit_provider', sanitize_key((string) ($schema['provider'] ?? 'greenhouse')));
        update_post_meta($jobs_post_id, '_sffc_auto_submit_schema_status', sanitize_key((string) $schema_status));
        update_post_meta($jobs_post_id, '_sffc_auto_submit_schema', wp_json_encode($schema));
        update_post_meta($jobs_post_id, '_sffc_greenhouse_board_token', sanitize_text_field((string) ($schema['board_token'] ?? '')));
        update_post_meta($jobs_post_id, '_sffc_greenhouse_job_id', sanitize_text_field((string) ($schema['job_id'] ?? '')));

        if (class_exists('SFFC_CRM_Shortcodes')) {
            SFFC_CRM_Shortcodes::invalidate_cv_match_job_posts_cache((int) $jobs_post_id);
        }

        return true;
    }

    private function build_draft_payload_from_feed_job(array $payload, array $job, $type, $source_key) {
        $source_url = esc_url_raw((string) ($payload['source_url'] ?? ($payload['application_url'] ?? '')));
        $external_job_id = sanitize_text_field((string) ($job['id'] ?? ($job['job_id'] ?? ($job['external_id'] ?? ''))));
        $source_platform = sanitize_text_field((string) ($job['source_platform'] ?? ($job['source_name'] ?? ($source_key ?: $type))));
        $raw_posted_at = sanitize_text_field((string) (
            $job['posted_date']
            ?? ($job['date_posted']
            ?? ($job['published_at']
            ?? ($job['posted_at']
            ?? ($job['created_at'] ?? ''))))
        ));
        $posted_at = '';

        if ($raw_posted_at !== '') {
            $posted_at = sanitize_text_field((string) ($payload['posted_at'] ?? ''));
            if ($posted_at === '') {
                $posted_at = $this->normalize_posted_at($raw_posted_at);
            }
        }

        return [
            'source_url' => $source_url,
            'application_url' => esc_url_raw((string) ($payload['application_url'] ?? $source_url)),
            'source_platform' => $source_platform,
            'external_job_id' => $external_job_id,
            'raw_title' => sanitize_text_field((string) ($payload['role_title'] ?? '')),
            'raw_company' => sanitize_text_field((string) ($payload['company'] ?? '')),
            'raw_location' => sanitize_text_field((string) ($payload['location'] ?? '')),
            'raw_location_city' => sanitize_text_field((string) ($payload['location_city'] ?? '')),
            'raw_location_country' => sanitize_text_field((string) ($payload['location_country'] ?? '')),
            'raw_salary_text' => sanitize_text_field((string) ($payload['salary_text'] ?? '')),
            'raw_company_logo' => esc_url_raw((string) ($job['company_logo'] ?? ($job['logo'] ?? ''))),
            'raw_sector' => sanitize_text_field((string) ($payload['sector'] ?? '')),
            'raw_seniority' => sanitize_text_field((string) ($payload['seniority'] ?? '')),
            'raw_experience_years' => sanitize_text_field((string) ($payload['experience_years'] ?? '')),
            'posted_at' => $posted_at,
            'raw_posted_at' => $raw_posted_at,
            'raw_content' => wp_kses_post((string) ($payload['content'] ?? '')),
            'extracted_payload' => [
                'feed_type' => $type,
                'source_key' => $source_key,
                'original_title' => sanitize_text_field((string) ($payload['original_title'] ?? ($job['title'] ?? ''))),
                'title_cleanup' => is_array($payload['title_cleanup'] ?? null) ? $payload['title_cleanup'] : [],
                'posted_at' => $posted_at,
                'raw_posted_at' => $raw_posted_at,
                'feed_job' => $job,
                'normalized_payload' => $payload,
                'auto_submit_provider' => !empty($job['auto_submit_schema']) ? sanitize_key((string) ($job['auto_submit_schema']['provider'] ?? '')) : '',
                'auto_submit_supported' => !empty($job['auto_submit_schema']),
                'auto_submit_schema_status' => sanitize_key((string) ($job['auto_submit_schema_status'] ?? '')),
                'auto_submit_schema' => is_array($job['auto_submit_schema'] ?? null) ? $job['auto_submit_schema'] : [],
            ],
            'confidence_score' => $this->score_feed_draft_payload($payload, $job),
            'status' => 'new',
        ];
    }

    private function maybe_fetch_auto_submit_schema_for_job(array $job, $type, $source_key) {
        if ($type !== 'xml') {
            return ['status' => 'not_supported', 'schema' => []];
        }

        $feeds = $this->get_xml_feeds();
        $feed = is_array($feeds[$source_key] ?? null) ? $feeds[$source_key] : [];
        $feed_provider = sanitize_key((string) ($feed['type'] ?? ''));
        $supported_providers = [
            'greenhouse',
            'recruitee',
            'lever',
            'successfactors',
            'pinpoint',
            'teamtailor_rss',
            'teamtailor',
            'michael_page',
        ];
        if (!in_array($feed_provider, $supported_providers, true)) {
            return ['status' => 'not_supported', 'schema' => []];
        }

        $cache_key = $feed_provider . '_' . sanitize_key((string) $source_key) . '_' . sanitize_key((string) ($job['external_id'] ?? ($job['id'] ?? md5((string) ($job['url'] ?? '')))));
        $cache = get_option('sffc_auto_submit_schema_cache', []);
        $cache = is_array($cache) ? $cache : [];
        if (!empty($cache[$cache_key]) && is_array($cache[$cache_key])) {
            return ['status' => 'cached', 'schema' => $cache[$cache_key]];
        }

        if ($feed_provider !== 'greenhouse') {
            $schema = $this->fetch_application_workspace_schema_for_provider($feed_provider, $job, $feed, $source_key);
            if (empty($schema)) {
                return ['status' => 'failed', 'schema' => []];
            }

            $cache[$cache_key] = $schema;
            update_option('sffc_auto_submit_schema_cache', $cache, false);

            return ['status' => 'discovered', 'schema' => $schema];
        }

        $board_token = $this->extract_greenhouse_board_token((string) ($feed['url'] ?? ''));
        if ($board_token === '') {
            return ['status' => 'failed', 'schema' => []];
        }

        $job_id = $this->extract_greenhouse_job_id_from_job($job);
        if ($job_id === '') {
            return ['status' => 'failed', 'schema' => []];
        }

        $cache_key = 'greenhouse_' . sanitize_key($board_token) . '_' . sanitize_text_field($job_id);
        if (!empty($cache[$cache_key]) && is_array($cache[$cache_key])) {
            return ['status' => 'cached', 'schema' => $cache[$cache_key]];
        }

        $schema = $this->fetch_greenhouse_application_schema($board_token, $job_id, $job, $feed, $source_key);
        if (empty($schema)) {
            return ['status' => 'failed', 'schema' => []];
        }

        $cache[$cache_key] = $schema;
        update_option('sffc_auto_submit_schema_cache', $cache, false);

        return ['status' => 'discovered', 'schema' => $schema];
    }

    private function fetch_application_workspace_schema_for_provider($provider, array $job, array $feed, $source_key) {
        switch (sanitize_key((string) $provider)) {
            case 'recruitee':
                return $this->fetch_recruitee_application_workspace_schema($job, $feed, $source_key);
            case 'lever':
                return $this->fetch_lever_application_workspace_schema($job, $feed, $source_key);
            case 'successfactors':
                return $this->fetch_successfactors_application_workspace_schema($job, $feed, $source_key);
            case 'pinpoint':
                return $this->fetch_pinpoint_application_workspace_schema($job, $feed, $source_key);
            case 'teamtailor':
            case 'teamtailor_rss':
                return $this->fetch_teamtailor_application_workspace_schema($job, $feed, $source_key);
            case 'michael_page':
                return $this->fetch_michael_page_application_workspace_schema($job, $feed, $source_key);
        }

        return [];
    }

    private function build_application_workspace_schema($provider, array $job, array $feed, $source_key, $schema_url, $hosted_url, array $questions, array $extra = []) {
        $questions = array_values(array_filter($questions, static function ($question) {
            return is_array($question) && trim((string) ($question['label'] ?? '')) !== '';
        }));

        if (empty($questions)) {
            return [];
        }

        $field_count = array_sum(array_map(static function ($question) {
            return max(1, count((array) ($question['fields'] ?? [])));
        }, $questions));

        return array_merge([
            'provider' => sanitize_key((string) $provider),
            'source_key' => sanitize_key((string) $source_key),
            'source_platform' => sanitize_text_field((string) ($feed['source_platform'] ?? $provider)),
            'job_id' => sanitize_text_field((string) ($job['external_id'] ?? ($job['id'] ?? ''))),
            'title' => sanitize_text_field((string) ($job['title'] ?? '')),
            'company_name' => sanitize_text_field((string) ($job['company'] ?? ($feed['company_name'] ?? ''))),
            'location' => sanitize_text_field((string) ($job['location'] ?? '')),
            'schema_url' => esc_url_raw((string) $schema_url),
            'hosted_url' => esc_url_raw((string) $hosted_url),
            'absolute_url' => esc_url_raw((string) ($job['url'] ?? $hosted_url)),
            'questions' => $questions,
            'location_questions' => [],
            'required_question_count' => count(array_filter($questions, static function ($question) {
                return !empty($question['required']);
            })),
            'field_count' => $field_count,
            'discovered_at' => current_time('mysql'),
        ], $extra);
    }

    private function normalize_application_workspace_question($label, $required = false, $type = 'text', array $values = [], $name = '') {
        $label = $this->clean_application_workspace_text($label);
        if ($label === '') {
            return [];
        }

        $normalized_values = [];
        foreach ($values as $value) {
            $value_label = is_array($value) ? (string) ($value['label'] ?? ($value['value'] ?? '')) : (string) $value;
            $value_value = is_array($value) ? (string) ($value['value'] ?? $value_label) : $value_label;
            $value_label = sanitize_text_field($this->clean_application_workspace_text($value_label));
            if ($value_label !== '') {
                $normalized_values[] = [
                    'label' => $value_label,
                    'value' => sanitize_text_field($this->clean_application_workspace_text($value_value)),
                ];
            }
        }

        return [
            'label' => sanitize_text_field($label),
            'required' => (bool) $required,
            'description' => '',
            'fields' => [[
                'name' => sanitize_text_field((string) $name),
                'type' => sanitize_key((string) $type),
                'values' => $normalized_values,
            ]],
        ];
    }

    private function clean_application_workspace_text($text) {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(wp_strip_all_tags((string) $text), ENT_QUOTES, 'UTF-8')));
    }

    private function fetch_recruitee_application_workspace_schema(array $job, array $feed, $source_key) {
        $api_url = (string) ($feed['api_url'] ?? '');
        if ($api_url === '') {
            $host = wp_parse_url((string) ($feed['url'] ?? ($job['url'] ?? '')), PHP_URL_HOST);
            if ($host) {
                $api_url = 'https://' . $host . '/api/offers';
            }
        }
        if ($api_url === '') {
            return [];
        }

        $response = wp_remote_get($api_url, [
            'timeout' => 20,
            'redirection' => 3,
            'headers' => ['Accept' => 'application/json'],
        ]);
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) >= 400) {
            return [];
        }

        $payload = json_decode((string) wp_remote_retrieve_body($response), true);
        $offers = is_array($payload['offers'] ?? null) ? $payload['offers'] : (is_array($payload) ? $payload : []);
        $target_id = (string) ($job['external_id'] ?? '');
        $target_title = strtolower($this->clean_application_workspace_text($job['title'] ?? ''));
        $offer = [];
        foreach ($offers as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            $candidate_id = (string) ($candidate['id'] ?? ($candidate['slug'] ?? ''));
            $candidate_title = strtolower($this->clean_application_workspace_text(($candidate['title'] ?? '') ?: ($candidate['translations']['en']['title'] ?? '')));
            if (($target_id !== '' && $candidate_id === $target_id) || ($target_title !== '' && $candidate_title === $target_title)) {
                $offer = $candidate;
                break;
            }
        }
        if (empty($offer)) {
            return [];
        }

        $questions = [];
        if (($offer['options_cv'] ?? '') !== 'off') {
            $questions[] = $this->normalize_application_workspace_question('CV / resume upload', (($offer['options_cv'] ?? '') === 'required'), 'file', [], 'resume');
        }
        if (($offer['options_cover_letter'] ?? '') !== 'off') {
            $questions[] = $this->normalize_application_workspace_question('Cover letter', (($offer['options_cover_letter'] ?? '') === 'required'), 'file', [], 'cover_letter');
        }
        foreach ((array) ($offer['open_questions'] ?? []) as $question) {
            if (!is_array($question)) {
                continue;
            }
            $questions[] = $this->normalize_application_workspace_question(
                $question['body'] ?? ($question['question'] ?? ($question['title'] ?? '')),
                !empty($question['required']),
                (string) ($question['kind'] ?? $question['type'] ?? 'text'),
                (array) ($question['answers'] ?? ($question['options'] ?? [])),
                (string) ($question['id'] ?? '')
            );
        }

        return $this->build_application_workspace_schema(
            'recruitee',
            $job,
            $feed,
            $source_key,
            $api_url,
            (string) (($offer['careers_apply_url'] ?? '') ?: ($offer['careers_url'] ?? ($job['url'] ?? ''))),
            $questions
        );
    }

    private function fetch_lever_application_workspace_schema(array $job, array $feed, $source_key) {
        $hosted_url = esc_url_raw((string) ($job['url'] ?? ''));
        if ($hosted_url === '') {
            return [];
        }
        if (stripos($hosted_url, '/apply') === false) {
            $hosted_url = rtrim($hosted_url, '/') . '/apply';
        }

        $response = wp_remote_get($hosted_url, [
            'timeout' => 20,
            'redirection' => 3,
            'headers' => ['Accept' => 'text/html,application/xhtml+xml'],
        ]);
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $html = (string) wp_remote_retrieve_body($response);
        $questions = [];
        if (preg_match_all('/baseTemplate&quot;:\s*&quot;([^&]+)&quot;.*?label&quot;:\s*&quot;([^&]+)&quot;.*?required&quot;:\s*(true|false)/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $questions[] = $this->normalize_application_workspace_question(
                    html_entity_decode($match[2], ENT_QUOTES, 'UTF-8'),
                    $match[3] === 'true',
                    html_entity_decode($match[1], ENT_QUOTES, 'UTF-8')
                );
            }
        }

        if (empty($questions) && preg_match('/window\.__LEVER_POSTING__\s*=\s*(\{.*?\});/s', $html, $match)) {
            $decoded = json_decode($match[1], true);
            foreach ((array) ($decoded['cards'] ?? []) as $card) {
                foreach ((array) ($card['fields'] ?? []) as $field) {
                    if (is_array($field)) {
                        $questions[] = $this->normalize_application_workspace_question($field['label'] ?? '', !empty($field['required']), $field['baseTemplate'] ?? 'text');
                    }
                }
            }
        }

        return $this->build_application_workspace_schema('lever', $job, $feed, $source_key, $hosted_url, $hosted_url, $questions);
    }

    private function fetch_successfactors_application_workspace_schema(array $job, array $feed, $source_key) {
        $job_url = esc_url_raw((string) ($job['url'] ?? ''));
        if ($job_url === '') {
            return [];
        }

        $response = wp_remote_get($job_url, [
            'timeout' => 25,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
            ],
        ]);
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $html = (string) wp_remote_retrieve_body($response);
        $schema = $this->build_successfactors_application_workspace_schema_from_html($html, $job, $feed, $source_key, $job_url);
        if (empty($schema)) {
            return [];
        }

        return $schema;
    }

    private function build_successfactors_application_workspace_schema_from_html($html, array $job, array $feed, $source_key, $job_url) {
        $html = (string) $html;
        $sso_url = '';
        $source_id = '';
        $locale = '';
        $internal_id = '';

        if (preg_match('/ssoUrl\s*:\s*[\'"]([^\'"]+)[\'"]/i', $html, $matches)) {
            $sso_url = esc_url_raw((string) $matches[1]);
        } elseif (preg_match('~https://career\d+\.successfactors\.(?:com|eu)~i', $html, $matches)) {
            $sso_url = esc_url_raw((string) $matches[0]);
        } elseif (preg_match('~https://performancemanager(\d+)\.successfactors\.eu~i', $html, $matches)) {
            $sso_url = esc_url_raw('https://career' . $matches[1] . '.successfactors.eu');
        }

        if (preg_match('/sourceId\s*:\s*[\'"]([^\'"]+)[\'"]/i', $html, $matches)) {
            $source_id = sanitize_text_field((string) $matches[1]);
        }

        if (preg_match('/locale\s*:\s*[\'"]([A-Za-z_-]+)[\'"]/i', $html, $matches)) {
            $locale = sanitize_text_field((string) $matches[1]);
        }
        if ($locale === '' && preg_match('/[?&]locale=([A-Za-z_-]+)/i', $job_url, $matches)) {
            $locale = sanitize_text_field((string) $matches[1]);
        }
        if ($locale === '') {
            $locale = sanitize_text_field((string) ($feed['locale'] ?? 'en_US'));
        }

        if (preg_match('/internalId\s*:\s*"([^"-]+)(?:-[^"]*)?"/i', $html, $matches)) {
            $internal_id = sanitize_text_field((string) $matches[1]);
        } elseif (preg_match('/"internalId"\s*:\s*"([^"-]+)(?:-[^"]*)?"/i', $html, $matches)) {
            $internal_id = sanitize_text_field((string) $matches[1]);
        } else {
            $internal_id = sanitize_text_field((string) ($job['external_id'] ?? ($job['job_id'] ?? '')));
        }

        $company = '';
        if (stripos($source_id, 'JATS-') === 0) {
            $company = substr($source_id, 5);
        }
        if ($company === '' && preg_match('/[?&](?:company|career_company)=([^&"\']+)/i', $html, $matches)) {
            $company = rawurldecode((string) $matches[1]);
        }
        $company = sanitize_text_field($company);

        if ($sso_url === '' || $company === '' || $internal_id === '') {
            return [];
        }

        $embed_url = esc_url_raw(rtrim($sso_url, '/') . '/career?company=' . rawurlencode($company) . '&site=&lang=' . rawurlencode($locale) . '&login_ns=register&career_ns=job_application&career_job_req_id=' . rawurlencode($internal_id) . '&jobPipeline=Direct&clientId=jobs2web');
        $questions = $this->fetch_successfactors_application_questions($embed_url);
        if (empty($questions)) {
            $questions = [
                $this->normalize_application_workspace_question('Resume/CV upload', true, 'file', [], 'resume'),
                $this->normalize_application_workspace_question('Email address', true, 'email', [], 'email'),
                $this->normalize_application_workspace_question('First name', true, 'text', [], 'first_name'),
                $this->normalize_application_workspace_question('Last name', true, 'text', [], 'last_name'),
            ];
        }

        return $this->build_application_workspace_schema(
            'successfactors',
            $job,
            $feed,
            $source_key,
            $job_url,
            $embed_url,
            $questions,
            [
                'source_id' => $source_id,
                'company_code' => $company,
                'sso_url' => $sso_url,
                'application_embed_url' => $embed_url,
                'external_job_id' => sanitize_text_field((string) ($job['external_id'] ?? ($job['id'] ?? ''))),
                'internal_job_id' => $internal_id,
            ]
        );
    }

    private function fetch_successfactors_application_questions($embed_url) {
        $embed_url = esc_url_raw((string) $embed_url);
        if ($embed_url === '') {
            return [];
        }

        $response = wp_remote_get($embed_url, [
            'timeout' => 25,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
            ],
        ]);
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) >= 400) {
            return [];
        }

        return $this->extract_application_workspace_questions_from_html_form((string) wp_remote_retrieve_body($response));
    }

    private function fetch_pinpoint_application_workspace_schema(array $job, array $feed, $source_key) {
        $hosted_url = esc_url_raw((string) ($job['url'] ?? ''));
        if ($hosted_url === '') {
            return [];
        }
        $schema_url = preg_replace('~/applications/new/?$~i', '', $hosted_url);
        $schema_url = rtrim((string) $schema_url, '/') . '/applications/new';

        $response = wp_remote_get($schema_url, [
            'timeout' => 20,
            'redirection' => 3,
            'headers' => ['Accept' => 'text/html,application/xhtml+xml'],
        ]);
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $html = (string) wp_remote_retrieve_body($response);
        $questions = [];
        if (preg_match_all('/"label"\s*:\s*"([^"]+)".{0,700}?"required"\s*:\s*(true|false)/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $questions[] = $this->normalize_application_workspace_question(stripslashes($match[1]), $match[2] === 'true', 'text');
            }
        }
        if (preg_match_all('/"question"\s*:\s*"([^"]+)".{0,700}?"required"\s*:\s*(true|false)/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $questions[] = $this->normalize_application_workspace_question(stripslashes($match[1]), $match[2] === 'true', 'text');
            }
        }

        $questions = $this->dedupe_application_workspace_questions($questions);
        return $this->build_application_workspace_schema('pinpoint', $job, $feed, $source_key, $schema_url, $schema_url, $questions);
    }

    private function fetch_teamtailor_application_workspace_schema(array $job, array $feed, $source_key) {
        $hosted_url = esc_url_raw((string) ($job['url'] ?? ''));
        if ($hosted_url === '') {
            return [];
        }
        $schema_url = preg_replace('~/applications/new/?$~i', '', $hosted_url);
        $schema_url = rtrim((string) $schema_url, '/') . '/applications/new';

        $response = wp_remote_get($schema_url, [
            'timeout' => 20,
            'redirection' => 3,
            'headers' => ['Accept' => 'text/html,application/xhtml+xml'],
        ]);
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $html = (string) wp_remote_retrieve_body($response);
        $questions = $this->extract_application_workspace_questions_from_html_form($html);
        return $this->build_application_workspace_schema('teamtailor', $job, $feed, $source_key, $schema_url, $schema_url, $questions);
    }

    private function fetch_michael_page_application_workspace_schema(array $job, array $feed, $source_key) {
        $hosted_url = esc_url_raw((string) ($job['url'] ?? ''));
        if ($hosted_url === '') {
            return [];
        }
        $schema_url = str_replace('/job-detail/', '/job-apply/', $hosted_url);

        $response = wp_remote_get($schema_url, [
            'timeout' => 20,
            'redirection' => 4,
            'headers' => ['Accept' => 'text/html,application/xhtml+xml'],
        ]);
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $html = (string) wp_remote_retrieve_body($response);
        $questions = $this->extract_application_workspace_questions_from_html_form($html);
        if (stripos($html, 'captcha') !== false) {
            $questions[] = $this->normalize_application_workspace_question('Captcha verification', true, 'captcha', [], 'captcha');
        }

        return $this->build_application_workspace_schema('michael_page', $job, $feed, $source_key, $schema_url, $schema_url, $questions);
    }

    private function extract_application_workspace_questions_from_html_form($html) {
        if (trim((string) $html) === '' || !class_exists('DOMDocument')) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . (string) $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $questions = [];
        foreach ($xpath->query('//input|//textarea|//select') as $field) {
            if (!$field instanceof DOMElement) {
                continue;
            }
            $type = strtolower((string) $field->getAttribute('type'));
            $name = (string) $field->getAttribute('name');
            if (in_array($type, ['hidden', 'submit', 'button', 'reset'], true) || $name === '' || strpos($name, 'authenticity_token') !== false || strpos($name, 'form_build_id') !== false || strpos($name, 'form_id') !== false) {
                continue;
            }

            $id = (string) $field->getAttribute('id');
            $label = '';
            if ($id !== '') {
                $label_node = $xpath->query('//label[@for=' . $this->xpath_literal($id) . ']')->item(0);
                if ($label_node) {
                    $label = $label_node->textContent;
                }
            }
            if ($label === '') {
                $label = str_replace(['_', '-'], ' ', preg_replace('/^(candidate|applicant|job_application)\[?|\]?$/', '', $name));
            }

            $values = [];
            if (strtolower($field->tagName) === 'select') {
                foreach ($xpath->query('.//option', $field) as $option) {
                    if ($option instanceof DOMElement) {
                        $option_label = $this->clean_application_workspace_text($option->textContent);
                        if ($option_label !== '') {
                            $values[] = [
                                'label' => $option_label,
                                'value' => (string) $option->getAttribute('value'),
                            ];
                        }
                    }
                }
            }

            $questions[] = $this->normalize_application_workspace_question(
                $label,
                $field->hasAttribute('required') || strpos((string) $field->getAttribute('class'), 'required') !== false || strpos((string) $field->getAttribute('aria-required'), 'true') !== false,
                strtolower($field->tagName) === 'textarea' ? 'textarea' : ($type ?: strtolower($field->tagName)),
                $values,
                $name
            );
        }

        return $this->dedupe_application_workspace_questions($questions);
    }

    private function dedupe_application_workspace_questions(array $questions) {
        $seen = [];
        $deduped = [];
        foreach ($questions as $question) {
            if (!is_array($question)) {
                continue;
            }
            $label = strtolower($this->clean_application_workspace_text($question['label'] ?? ''));
            if ($label === '' || isset($seen[$label])) {
                continue;
            }
            $seen[$label] = true;
            $deduped[] = $question;
        }

        return $deduped;
    }

    private function xpath_literal($value) {
        $value = (string) $value;
        if (strpos($value, '"') === false) {
            return '"' . $value . '"';
        }
        if (strpos($value, "'") === false) {
            return "'" . $value . "'";
        }
        return 'concat("' . str_replace('"', '", \'"\', "', $value) . '")';
    }

    private function score_feed_draft_payload(array $payload, array $job) {
        $score = 0;

        foreach ([
            'role_title' => 18,
            'company' => 18,
            'location' => 14,
            'application_url' => 14,
            'content' => 18,
            'sector' => 8,
            'seniority' => 6,
            'experience_years' => 4,
        ] as $field => $points) {
            if (!empty($payload[$field])) {
                $score += $points;
            }
        }

        if (!empty($job['posted_date'])) {
            $score += 4;
        }

        if (!empty($job['company_logo']) || !empty($job['logo'])) {
            $score += 4;
        }

        return min(100, $score);
    }

    private function normalize_feed_job_for_crm(array $job, $type, $source_key) {
        $title = sanitize_text_field((string) ($job['title'] ?? ''));
        $company = sanitize_text_field((string) ($job['company'] ?? ($job['source_name'] ?? '')));
        $location = sanitize_text_field((string) ($job['location'] ?? ''));
        $apply_url = esc_url_raw((string) ($job['url'] ?? ($job['apply_url'] ?? '')));
        $description = $this->build_job_content($job);

        if ($title === '' || $company === '' || $apply_url === '') {
            return null;
        }

        if (!$this->is_target_investment_role($title, $location, $description, $job)) {
            return null;
        }

        if (!$this->is_finance_role($title, $description, $job)) {
            return null;
        }

        $seniority = $this->infer_job_seniority($title, $description);
        if ($seniority === null) {
            return null;
        }

        $location_details = $this->infer_location_details($location, $description);
        $sector = $this->infer_job_sector($title, $description, $job);
        $posted_at = $this->normalize_posted_at($job['posted_date'] ?? '');
        $salary = $this->normalize_salary_data($job['estimated_salary'] ?? null);
        $keywords = $this->build_keywords($job, $sector, $seniority, $location_details);
        $is_recruiter = !empty($job['via_recruiter']) || (($job['source_type'] ?? '') === 'recruiter');
        $title_cleanup = $this->normalize_feed_job_title($title, $company, $location);
        $display_title = $title_cleanup['title'] ?? $title;

        return [
            'role_title' => $display_title !== '' ? $display_title : $title,
            'original_title' => $title,
            'title_cleanup' => $title_cleanup,
            'company' => $company,
            'location' => $location,
            'location_city' => $location_details['city'],
            'location_country' => $location_details['country'],
            'content' => $description,
            'posted_at' => $posted_at,
            'application_url' => $apply_url,
            'source_url' => $apply_url,
            'source' => $type . '_feed',
            'sector' => $sector,
            'seniority' => $seniority,
            'experience_years' => $this->estimate_experience_years($seniority),
            'keywords' => $keywords,
            'salary_text' => $salary['display'],
            'salary_min' => $salary['min'],
            'salary_max' => $salary['max'],
            'salary_currency' => $salary['currency'],
            'response_label' => 'Queued from feed',
            'recruiter_display_name' => $is_recruiter ? sanitize_text_field((string) ($job['recruiter_name'] ?? ($job['source_name'] ?? $company))) : '',
            'recruiter_display_company' => $is_recruiter ? sanitize_text_field((string) ($job['source_name'] ?? '')) : '',
        ];
    }

    private function build_job_content(array $job) {
        $parts = array_filter([
            wp_strip_all_tags((string) ($job['description'] ?? '')),
            wp_strip_all_tags((string) ($job['responsibilities'] ?? '')),
            wp_strip_all_tags((string) ($job['qualifications'] ?? '')),
            wp_strip_all_tags((string) ($job['department'] ?? '')),
            wp_strip_all_tags((string) ($job['job_family'] ?? '')),
            wp_strip_all_tags((string) ($job['category'] ?? '')),
        ]);

        $content = trim(preg_replace('/\s+/', ' ', implode("\n\n", $parts)));
        return $content !== '' ? $content : sanitize_text_field((string) ($job['title'] ?? ''));
    }

    private function clean_feed_job_title($title, $company = '', $location = '') {
        $cleanup = $this->normalize_feed_job_title($title, $company, $location);
        return sanitize_text_field((string) ($cleanup['title'] ?? $title));
    }

    private function normalize_feed_job_title($title, $company = '', $location = '') {
        if (!class_exists('SFFC_CRM_Job_Title_Normalizer') && defined('SFFC_PLUGIN_DIR') && file_exists(SFFC_PLUGIN_DIR . 'includes/class-crm-job-title-normalizer.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-crm-job-title-normalizer.php';
        }

        if (class_exists('SFFC_CRM_Job_Title_Normalizer')) {
            return SFFC_CRM_Job_Title_Normalizer::normalize($title, $company, $location);
        }

        $original = sanitize_text_field((string) $title);
        $title = html_entity_decode(wp_strip_all_tags($original), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = str_replace(['–', '—'], '-', $title);
        $title = preg_replace('/(?<=[a-z0-9\)])\.(?=[A-Z])/', ' - ', (string) $title);
        $title = preg_replace('/\s+/', ' ', trim((string) $title));

        if ($title === '') {
            return [
                'original_title' => $original,
                'title' => '',
                'changed' => false,
                'cleanup_score' => 0,
            ];
        }

        $title = $this->normalize_feed_title_programme_language($title);
        $title = preg_replace('/^\s*[A-Z]{2,}\s+Lab\s*[-|]\s*/', '', (string) $title);

        foreach ($this->build_feed_title_company_cleanup_patterns($company) as $pattern) {
            $title = preg_replace($pattern, ' ', $title);
        }

        $noise_patterns = array_merge($this->build_feed_title_location_cleanup_patterns($location), [
            '/\b(?:for\s+)?(?:uae|u\.a\.e\.|emirati|saudi|ksa|qatar(?:i)?|bahraini|kuwaiti|omani)\s+nationals?\b/i',
            '/\b(?:uae|u\.a\.e\.|saudi|ksa|qatari|qatar|bahraini|kuwaiti|omani)\s+citizens?\b/i',
            '/\b(?:national\s+talent|nationals?\s+only|local\s+national)\b/i',
            '/\b(?:rbg|retail banking group|private banking investment advisors?)\b/i',
            '/\b(?:english|arabic|french|german|spanish|mandarin|bilingual)\s+(?:speaker|speaking|required|language)\b/i',
            '/\((?:[^)]*\b(?:english|arabic|french|german|spanish|mandarin|bilingual)\b[^)]*)\)/i',
            '/\(([A-Z]{2,6})\)/',
            '/\b(?:m\/f\/d|f\/m\/d|m\/w\/d|f\/m\/x|all genders)\b/i',
            '/(?:^|[\s\(\[-])(?:20\d{2})(?=\s+[A-Z])/i',
            '/(?:[$£€]|AED|SAR|QAR|USD|GBP|EUR)\s?\d[\d,]*(?:\.\d+)?\s*(?:k|m)?(?:\s*[-\/]\s*(?:[$£€]|AED|SAR|QAR|USD|GBP|EUR)?\s?\d[\d,]*(?:\.\d+)?\s*(?:k|m)?)?(?:\s*(?:per\s+)?(?:hour|hr|day|month|year|annum|pa|p\.a\.))?/i',
            '/\b\d[\d,]*(?:\.\d+)?\s*(?:k|m)?\s*(?:[-\/]\s*\d[\d,]*(?:\.\d+)?\s*(?:k|m)?)?\s*(?:per\s+)?(?:hour|hr|day|month|year|annum|pa|p\.a\.)\b/i',
            '/\b(?:remote|hybrid|on[-\s]?site)\b/i',
            '/\b(?:full[-\s]?time|part[-\s]?time|permanent|temporary|contract|fixed[-\s]?term)\b/i',
            '/\b(?:apply now|job details|careers?|external|easy apply|linkedin)\b/i',
            '/\b(?:job\s*)?(?:id|req|requisition|reference)\s*[:#-]?\s*[a-z]{0,4}[-_ ]?\d{2,}\b/i',
            '/\b(?:jr|req|r)[-_ ]?\d{3,}\b/i',
            '/(?:^|[\s\-\.])\d{1,4}(?=[\s\-\.]|$)/',
        ]);

        foreach ($noise_patterns as $pattern) {
            $title = preg_replace($pattern, ' ', $title);
        }

        $title = preg_replace('/\s*[\(\[\{]\s*[\)\]\}]\s*/', ' ', (string) $title);
        $title = preg_replace('/\s+([,\|\/])\s+/', '$1 ', (string) $title);
        $title = preg_replace('/\s*[\|\/]\s*/', ' / ', (string) $title);
        $title = preg_replace('/\s+-\s+/', ' - ', (string) $title);
        $title = preg_replace('/(?:\s*[-\/]\s*){2,}/', ' - ', (string) $title);
        $title = preg_replace('/(?:\s*[-,\.\|\/]\s*)+$/', '', (string) $title);
        $title = preg_replace('/^(?:\s*[-,\.\|\/]\s*)+/', '', (string) $title);
        $title = preg_replace('/\b(senior)\s+\1\b/i', '$1', (string) $title);
        $title = preg_replace('/\b(associate)\s+\1\b/i', '$1', (string) $title);
        $title = preg_replace('/\b(program)\b/i', 'Programme', (string) $title);
        $title = preg_replace('/\bprogramme\s+programme\b/i', 'Programme', (string) $title);
        $title = preg_replace('/\s+/', ' ', trim((string) $title));

        $clean_title = sanitize_text_field($title !== '' ? $title : $original);
        return [
            'original_title' => $original,
            'title' => $clean_title,
            'changed' => strcasecmp($clean_title, $original) !== 0,
            'cleanup_score' => strcasecmp($clean_title, $original) !== 0 ? 50 : 0,
        ];
    }

    private function normalize_feed_title_programme_language($title) {
        $title = preg_replace('/\b(emirati[sz]ation|emiritisation)\s+initiative\b/i', 'National Initiative', (string) $title);
        $title = preg_replace('/\b(emirati[sz]ation|emiritisation)\s+(graduate\s+)?programme\b/i', 'National $2Programme', (string) $title);
        $title = preg_replace('/\b(emirati[sz]ation|emiritisation)\s+(graduate\s+)?program\b/i', 'National $2Programme', (string) $title);
        $title = preg_replace('/\bgraduate\s+program\b/i', 'Graduate Programme', (string) $title);
        $title = preg_replace('/\bprogram\b/i', 'Programme', (string) $title);
        $title = preg_replace('/\bNational Initiative\s*[\/|,-]\s*Graduate Programme\b/i', 'National Initiative Programme', (string) $title);

        return $title;
    }

    private function build_feed_title_company_cleanup_patterns($company) {
        $company = trim((string) $company);
        if ($company === '') {
            return [];
        }

        $company_quoted = preg_quote($company, '/');
        $company_root = preg_replace('/\b(?:llc|ltd|limited|plc|inc|corp|corporation|company|co|group|bank|asset management|capital|partners?)\b\.?/i', '', $company);
        $company_root = preg_replace('/\s+/', ' ', trim((string) $company_root));
        $patterns = [
            '/(?:^|\s+[-|]\s*)' . $company_quoted . '(?:\s*[-|]\s*|\s*$)/i',
            '/\b(?:at|with|for)\s+' . $company_quoted . '\b/i',
            '/\b' . $company_quoted . '\b/i',
        ];

        if ($company_root !== '' && strlen($company_root) >= 4 && strcasecmp($company_root, $company) !== 0) {
            $root_quoted = preg_quote($company_root, '/');
            $patterns[] = '/(?:^|\s+[-|]\s*)' . $root_quoted . '(?:\s*[-|]\s*|\s*$)/i';
            $patterns[] = '/\b(?:at|with|for)\s+' . $root_quoted . '\b/i';
            $patterns[] = '/\b' . $root_quoted . '\b/i';
        }

        return $patterns;
    }

    private function build_feed_title_location_cleanup_patterns($location) {
        $locations = [
            'Dubai', 'Abu Dhabi', 'Riyadh', 'Doha', 'United Arab Emirates', 'UAE', 'U.A.E.',
            'Saudi Arabia', 'KSA', 'Qatar', 'Bahrain', 'Kuwait', 'Oman', 'Middle East', 'MENA',
            'London', 'United Kingdom', 'UK', 'Europe', 'Germany',
        ];

        foreach (preg_split('/[,\/|]+/', (string) $location) ?: [] as $part) {
            $part = trim((string) $part);
            if ($part !== '') {
                $locations[] = $part;
            }
        }

        $patterns = [];
        foreach (array_unique($locations) as $item) {
            if (strlen($item) < 2) {
                continue;
            }
            $quoted = preg_quote($item, '/');
            $patterns[] = '/(?:^|\s+[-|,\/]\s*)' . $quoted . '(?:\s*[-|,\/]\s*|\s*$)/i';
            $patterns[] = '/\s*[\(\[\{]\s*' . $quoted . '\s*[\)\]\}]\s*/i';
        }

        return $patterns;
    }

    private function is_target_investment_role($title, $location, $description, array $job) {
        $haystack = strtolower(implode(' ', array_filter([
            (string) $title,
            (string) $location,
            (string) $description,
            (string) ($job['source_name'] ?? ''),
            (string) ($job['category'] ?? ''),
            (string) ($job['department'] ?? ''),
            (string) ($job['job_family'] ?? ''),
            (string) ($job['company'] ?? ''),
        ])));

        foreach ($this->get_private_equity_location_map() as $entry) {
            foreach ($entry['keywords'] as $keyword) {
                if (strpos($haystack, strtolower($keyword)) !== false) {
                    return true;
                }
            }
        }

        foreach ([
            'private equity',
            'private_equity',
            'investment',
            'investor relations',
            'asset management',
            'wealth management',
            'portfolio',
            'credit',
            'real estate',
            'infrastructure',
            'strategic partners',
            'tactical opportunities',
            'venture capital',
            'growth equity',
            'capital formation',
            'investment banking',
            'm&a',
            'mergers and acquisitions',
            'sovereign wealth',
            'gcc',
            'gulf',
        ] as $keyword) {
            if (strpos($haystack, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    private function is_finance_role($title, $description, array $job) {
        $haystack = strtolower(implode(' ', array_filter([
            (string) $title,
            (string) $description,
            (string) ($job['category'] ?? ''),
            (string) ($job['job_family'] ?? ''),
            (string) ($job['department'] ?? ''),
            (string) ($job['company'] ?? ''),
            (string) ($job['source_name'] ?? ''),
        ])));

        $keywords = [
            'finance', 'financial', 'investment', 'bank', 'banking', 'treasury', 'fp&a',
            'planning and analysis', 'asset management', 'wealth', 'portfolio', 'private equity',
            'venture capital', 'fund', 'capital markets', 'credit', 'risk', 'compliance',
            'accounting', 'controller', 'controllership', 'audit', 'advisory', 'investor relations',
            'strategy', 'corporate finance', 'sovereign wealth'
        ];

        foreach ($keywords as $keyword) {
            if (strpos($haystack, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    private function infer_job_seniority($title, $description) {
        $title = $this->normalize_job_seniority_text($title);
        $text = $this->normalize_job_seniority_text(trim((string) $title . ' ' . (string) $description));

        $title_rules = [
            'intern' => '/\b(intern(ship)?|off cycle|offcycle|summer analyst|summer intern|placement|trainee|management trainee|graduate trainee|graduate(?:\s+[a-z0-9]+){0,4}\s+(programme|program)|campus|emirati[sz]ation graduate|emiritisation graduate|emiratisation programme|emiratization program)\b/',
            'board' => '/\b(board member|board director|chair(man|woman)|non executive director|non-executive director|independent director)\b/',
            'c_level' => '/\b(c suite|c-suite|head of function|chief\s+(executive|financial|operating|investment|risk|technology|information|marketing|people|commercial|strategy|compliance|legal)\s+officer|chief\s+[a-z]+(?:\s+[a-z]+)?\s+officer|ceo|cfo|coo|cio|cto|cmo|cro|chro|cpo|ciso)\b/',
            'partner' => '/\b(managing partner|founding partner|general partner)\b|(?<!business\s)(?<!customer\s)(?<!success\s)\bpartner\b(?!\s+(manager|success|operations|sales|marketing|finance|account|channel|relationship|solutions))/',
            'md' => '/\b(managing director|general manager)\b|\bmd\b/',
            'senior_vp' => '/\b(executive vice president|senior vice president|svp|evp)\b/',
            'vp' => '/\b(assistant vice president|associate vice president|vice president|principal|avp|vp)\b/',
            'director' => '/\b(executive director|senior director|director|associate director|regional head|country head|global head|head of|chief of staff)\b/',
            'senior_associate' => '/\b(senior associate|senior relationship manager|senior manager|lead manager|senior consultant)\b/',
            'senior_analyst' => '/\b(senior analyst|sr analyst|sr\. analyst|lead analyst|senior officer|senior specialist)\b/',
            'analyst' => '/\b(analyst|junior analyst|investment analyst|research analyst|data analyst|finance analyst|assistant relationship manager|junior relationship manager|assistant manager|junior manager|relationship officer|officer|specialist|coordinator|graduate|entry level|entry-level)\b/',
            'associate' => '/\b(associate|relationship manager|investment manager|portfolio manager|finance manager|trade finance manager|operations manager|product manager|project manager|programme manager|program manager|manager|consultant)\b/',
        ];

        foreach ($title_rules as $seniority => $pattern) {
            if ($title !== '' && preg_match($pattern, $title)) {
                return $seniority;
            }
        }

        $years = $this->detect_job_seniority_years($text);
        if ($years !== null) {
            if ($years <= 2) {
                return 'analyst';
            }
            if ($years <= 4) {
                return 'senior_analyst';
            }
            if ($years <= 6) {
                return 'associate';
            }
            if ($years <= 8) {
                return 'senior_associate';
            }
            if ($years <= 10) {
                return 'vp';
            }
            if ($years <= 12) {
                return 'senior_vp';
            }
            return 'director';
        }

        return 'other';
    }

    private function normalize_job_seniority_text($value) {
        $value = html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES, 'UTF-8');
        $value = strtolower(str_replace(['–', '—', '/', '&'], ['-', '-', ' ', ' and '], $value));
        $value = preg_replace('/[^a-z0-9\+\.\-\s]/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim((string) $value);
    }

    private function detect_job_seniority_years($text) {
        $text = (string) $text;
        if (preg_match('/\b(?:minimum of|at least|minimum|required|requires|requirement)\s+(\d{1,2})\+?\s+years?\b/', $text, $matches)) {
            return (int) $matches[1];
        }
        if (preg_match('/\b(\d{1,2})\+?\s+years?\s+(?:of\s+)?(?:relevant\s+)?experience\b/', $text, $matches)) {
            return (int) $matches[1];
        }
        if (preg_match('/\b(\d{1,2})\s*(?:-|to)\s*(\d{1,2})\s+years?\b/', $text, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function infer_job_sector($title, $description, array $job) {
        $haystack = strtolower(trim(implode(' ', array_filter([
            (string) $title,
            (string) $description,
            (string) ($job['category'] ?? ''),
            (string) ($job['job_family'] ?? ''),
            (string) ($job['department'] ?? ''),
        ]))));

        $map = [
            'pe' => ['private equity', 'buyout', 'growth equity', 'lbo'],
            'vc' => ['venture capital', 'venture investing'],
            'hedge_fund' => ['hedge fund', 'public markets', 'quant'],
            'asset_management' => ['asset management', 'wealth', 'portfolio', 'fund management'],
            'ib' => ['investment banking', 'capital markets', 'm&a', 'ecm', 'dcm', 'leveraged finance'],
            'corporate' => ['treasury', 'fp&a', 'financial planning', 'corporate finance', 'strategic finance', 'finance manager', 'controller', 'controllership', 'accounting'],
            'credit' => ['credit', 'special situations', 'direct lending'],
            'government' => ['sovereign wealth', 'public investment', 'government'],
            'consulting' => ['strategy', 'advisory', 'consulting'],
        ];

        foreach ($map as $sector => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($haystack, $keyword) !== false) {
                    return $sector;
                }
            }
        }

        return 'other';
    }

    private function infer_location_details($location, $description) {
        $haystack = strtolower(trim($location . ' ' . $description));

        foreach ($this->get_private_equity_location_map() as $entry) {
            foreach ($entry['keywords'] as $keyword) {
                if (strpos($haystack, strtolower($keyword)) !== false) {
                    return [
                        'city' => $entry['city'],
                        'country' => $entry['country'],
                    ];
                }
            }
        }

        return ['city' => '', 'country' => ''];
    }

    private function get_private_equity_location_map() {
        return [
            ['city' => 'Dubai', 'country' => 'United Arab Emirates', 'keywords' => ['dubai', 'difc', 'uae', 'united arab emirates']],
            ['city' => 'Abu Dhabi', 'country' => 'United Arab Emirates', 'keywords' => ['abu dhabi', 'adgm']],
            ['city' => 'Riyadh', 'country' => 'Saudi Arabia', 'keywords' => ['riyadh', 'saudi arabia', 'ksa']],
            ['city' => 'Jeddah', 'country' => 'Saudi Arabia', 'keywords' => ['jeddah']],
            ['city' => 'Dammam', 'country' => 'Saudi Arabia', 'keywords' => ['dammam', 'khobar', 'dhahran']],
            ['city' => 'Doha', 'country' => 'Qatar', 'keywords' => ['doha', 'qatar', 'qfc']],
            ['city' => 'Kuwait City', 'country' => 'Kuwait', 'keywords' => ['kuwait city', 'kuwait']],
            ['city' => 'Manama', 'country' => 'Bahrain', 'keywords' => ['manama', 'bahrain']],
            ['city' => 'Muscat', 'country' => 'Oman', 'keywords' => ['muscat', 'oman']],
            ['city' => 'Cairo', 'country' => 'Egypt', 'keywords' => ['cairo', 'egypt']],
            ['city' => 'Alexandria', 'country' => 'Egypt', 'keywords' => ['alexandria']],
            ['city' => 'Amman', 'country' => 'Jordan', 'keywords' => ['amman', 'jordan']],
            ['city' => 'Beirut', 'country' => 'Lebanon', 'keywords' => ['beirut', 'lebanon']],
        ];
    }

    private function normalize_posted_at($posted_date) {
        $posted_date = trim((string) $posted_date);
        if ($posted_date === '') {
            return '';
        }

        $timestamp = $this->parse_day_first_numeric_date($posted_date);
        if (!$timestamp) {
            $timestamp = strtotime($posted_date);
        }

        if (!$timestamp) {
            return '';
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function parse_day_first_numeric_date($date) {
        $date = trim((string) $date);
        if (!preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{2,4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$/', $date, $matches)) {
            return false;
        }

        $day = (int) $matches[1];
        $month = (int) $matches[2];
        $year = (int) $matches[3];
        if ($year < 100) {
            $year += 2000;
        }

        $hour = isset($matches[4]) ? (int) $matches[4] : 0;
        $minute = isset($matches[5]) ? (int) $matches[5] : 0;
        $second = isset($matches[6]) ? (int) $matches[6] : 0;

        if (!checkdate($month, $day, $year)) {
            return false;
        }

        return gmmktime($hour, $minute, $second, $month, $day, $year);
    }

    private function normalize_salary_data($estimated_salary) {
        if (is_array($estimated_salary)) {
            return [
                'min' => !empty($estimated_salary['min']) ? (int) $estimated_salary['min'] : null,
                'max' => !empty($estimated_salary['max']) ? (int) $estimated_salary['max'] : null,
                'currency' => !empty($estimated_salary['currency']) ? sanitize_text_field((string) $estimated_salary['currency']) : '',
                'display' => !empty($estimated_salary['display']) ? sanitize_text_field((string) $estimated_salary['display']) : '',
            ];
        }

        return ['min' => null, 'max' => null, 'currency' => '', 'display' => ''];
    }

    private function estimate_experience_years($seniority) {
        $map = [
            'intern' => '0-1',
            'analyst' => '1-3',
            'senior_analyst' => '3-5',
            'associate' => '4-6',
            'senior_associate' => '6-8',
            'vp' => '8-10',
            'senior_vp' => '10-12',
            'director' => '10-15',
            'md' => '12+',
            'partner' => '15+',
            'c_level' => '15+',
            'board' => '15+',
        ];

        return $map[$seniority] ?? '';
    }

    private function build_keywords(array $job, $sector, $seniority, array $location_details) {
        $keywords = array_filter([
            sanitize_text_field((string) ($job['category'] ?? '')),
            sanitize_text_field((string) ($job['job_family'] ?? '')),
            sanitize_text_field((string) ($job['department'] ?? '')),
            sanitize_text_field((string) ($job['source_name'] ?? '')),
            $sector,
            $seniority,
            $location_details['city'],
            $location_details['country'],
        ]);

        return implode(', ', array_unique($keywords));
    }

    private function find_existing_crm_post_id(array $payload) {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_crm_posts';

        if (!empty($payload['application_url'])) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE application_url = %s LIMIT 1",
                $payload['application_url']
            ));
            if (!empty($existing)) {
                return (int) $existing;
            }
        }

        if (!empty($payload['source_url'])) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE source_url = %s LIMIT 1",
                $payload['source_url']
            ));
            if (!empty($existing)) {
                return (int) $existing;
            }
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE role_title = %s AND company = %s AND location = %s LIMIT 1",
            $payload['role_title'],
            $payload['company'],
            $payload['location']
        ));

        return !empty($existing) ? (int) $existing : 0;
    }

    private function normalize_wp_job_manager_rss_url($url) {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        if (strpos($url, 'feed=job_feed') !== false) {
            return esc_url_raw($url);
        }

        $parts = wp_parse_url($url);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return esc_url_raw($url);
        }

        return esc_url_raw($parts['scheme'] . '://' . $parts['host'] . '/?feed=job_feed');
    }
    
    private function test_workday_feed($key) {
        if (!class_exists('SFFC_Workday_Job_Fetcher_V2')) {
            return ['success' => false, 'error' => 'Workday fetcher not available'];
        }
        
        $fetcher = new SFFC_Workday_Job_Fetcher_V2();
        $test = $fetcher->test_connection($key);
        
        return $test;
    }
    
    private function test_xml_feed($key) {
        if (!class_exists('SFFC_XML_Job_Fetcher')) {
            return ['success' => false, 'error' => 'XML fetcher not available'];
        }
        
        $feeds = $this->get_xml_feeds();
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
}

// Initialize
SFFC_Feed_Manager_Admin::get_instance();
