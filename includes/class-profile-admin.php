<?php
/**
 * Profile Admin Interface
 * Admin interface for user profile management
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Profile_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_page'], 27);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // AJAX handlers for admin
        add_action('wp_ajax_sffc_admin_get_user_profile', [$this, 'ajax_admin_get_user_profile']);
        add_action('wp_ajax_sffc_admin_save_user_profile', [$this, 'ajax_admin_save_user_profile']);
        add_action('wp_ajax_sffc_search_users', [$this, 'ajax_search_users']);
    }
    
    public function add_admin_page() {
        add_submenu_page(
            'sffc-dashboard',
            'User Profiles',
            'User Profiles',
            'manage_options',
            'sffc-user-profiles',
            [$this, 'render_page']
        );
    }
    
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'sffc-user-profiles') === false) {
            return;
        }
        
        wp_enqueue_style('sffc-profile-admin', SFFC_PLUGIN_URL . 'admin/css/profile-admin.css', [], SFFC_VERSION);
        wp_enqueue_script('sffc-profile-admin', SFFC_PLUGIN_URL . 'admin/js/profile-admin.js', ['jquery', 'select2'], SFFC_VERSION, true);
        
        // Include Select2 for tag inputs
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery']);
        
        wp_localize_script('sffc-profile-admin', 'sffcProfileAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_profile_admin'),
            'skillsTaxonomy' => $this->get_skills_taxonomy()
        ]);
    }
    
    public function render_page() {
        $current_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : get_current_user_id();
        ?>
        <div class="wrap">
            <h1>User Profile Management</h1>
            
            <!-- User Selector -->
            <div class="user-selector-section">
                <h2>Select User</h2>
                <div class="user-search-container">
                    <select id="user-selector" style="width: 300px;">
                        <option value="">Search for a user...</option>
                    </select>
                    <button type="button" id="load-profile" class="button button-primary">Load Profile</button>
                    <button type="button" id="create-new-profile" class="button">Create New Profile</button>
                </div>
            </div>
            
            <!-- Profile Editor -->
            <div id="profile-editor" style="display: none;">
                <form id="profile-form">
                    <input type="hidden" id="profile-user-id" name="user_id" value="<?php echo $current_user_id; ?>">
                    
                    <!-- Basic Information -->
                    <div class="profile-section">
                        <h2>Basic Information</h2>
                        <div class="profile-completion">
                            <div class="completion-bar">
                                <div class="completion-fill" id="completion-fill" style="width: 0%"></div>
                            </div>
                            <span id="completion-text">0% Complete</span>
                        </div>
                        
                        <table class="form-table">
                            <tr>
                                <th><label>Career Stage</label></th>
                                <td>
                                    <select name="career_stage" id="career_stage">
                                        <option value="Graduate">Graduate</option>
                                        <option value="Analyst">Analyst</option>
                                        <option value="Associate">Associate</option>
                                        <option value="Vice President">Vice President</option>
                                        <option value="Director">Director</option>
                                        <option value="Managing Director">Managing Director</option>
                                        <option value="Partner">Partner</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Years of Experience</label></th>
                                <td><input type="number" name="years_experience" id="years_experience" min="0" max="50" /></td>
                            </tr>
                            <tr>
                                <th><label>Current Title</label></th>
                                <td><input type="text" name="current_title" id="current_title" style="width: 100%;" /></td>
                            </tr>
                            <tr>
                                <th><label>Current Company</label></th>
                                <td><input type="text" name="current_company" id="current_company" style="width: 100%;" /></td>
                            </tr>
                            <tr>
                                <th><label>Current Salary</label></th>
                                <td><input type="number" name="salary_current" id="salary_current" step="1000" /></td>
                            </tr>
                            <tr>
                                <th><label>Target Salary Range</label></th>
                                <td>
                                    <input type="number" name="salary_target_min" id="salary_target_min" step="1000" placeholder="Min" style="width: 45%;" />
                                    <span> - </span>
                                    <input type="number" name="salary_target_max" id="salary_target_max" step="1000" placeholder="Max" style="width: 45%;" />
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Skills Section -->
                    <div class="profile-section">
                        <h2>Skills</h2>
                        <div class="skills-container">
                            <div class="skill-input-section">
                                <h3>Add Skill</h3>
                                <table class="form-table">
                                    <tr>
                                        <th><label>Skill Name</label></th>
                                        <td>
                                            <select id="new-skill-name" style="width: 100%;">
                                                <option value="">Select or type skill...</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label>Category</label></th>
                                        <td>
                                            <select id="new-skill-category">
                                                <option value="Technical">Technical</option>
                                                <option value="Financial Analysis">Financial Analysis</option>
                                                <option value="Industry Knowledge">Industry Knowledge</option>
                                                <option value="Software">Software</option>
                                                <option value="Certifications">Certifications</option>
                                                <option value="Soft Skills">Soft Skills</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label>Proficiency</label></th>
                                        <td>
                                            <select id="new-skill-proficiency">
                                                <option value="Beginner">Beginner</option>
                                                <option value="Intermediate">Intermediate</option>
                                                <option value="Advanced">Advanced</option>
                                                <option value="Expert">Expert</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label>Years Experience</label></th>
                                        <td><input type="number" id="new-skill-years" min="1" max="20" value="1" /></td>
                                    </tr>
                                </table>
                                <button type="button" id="add-skill" class="button button-primary">Add Skill</button>
                            </div>
                            
                            <div class="skills-list-section">
                                <h3>Current Skills</h3>
                                <div id="skills-list">
                                    <!-- Skills will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Experience Section -->
                    <div class="profile-section">
                        <h2>Experience</h2>
                        <div class="experience-container">
                            <button type="button" id="add-experience" class="button button-primary">Add Experience</button>
                            <div id="experience-list">
                                <!-- Experience items will be loaded here -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Preferences Section -->
                    <div class="profile-section">
                        <h2>Preferences</h2>
                        <table class="form-table">
                            <tr>
                                <th><label>Preferred Locations</label></th>
                                <td>
                                    <select name="preferred_locations[]" id="preferred_locations" multiple style="width: 100%;">
                                        <option value="London">London</option>
                                        <option value="New York">New York</option>
                                        <option value="Dubai">Dubai</option>
                                        <option value="Singapore">Singapore</option>
                                        <option value="Hong Kong">Hong Kong</option>
                                        <option value="Frankfurt">Frankfurt</option>
                                        <option value="Paris">Paris</option>
                                        <option value="Sydney">Sydney</option>
                                        <option value="Toronto">Toronto</option>
                                        <option value="Tokyo">Tokyo</option>
                                        <option value="Remote">Remote</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Preferred Industries</label></th>
                                <td>
                                    <select name="preferred_industries[]" id="preferred_industries" multiple style="width: 100%;">
                                        <option value="Investment Banking">Investment Banking</option>
                                        <option value="Private Equity">Private Equity</option>
                                        <option value="Venture Capital">Venture Capital</option>
                                        <option value="Asset Management">Asset Management</option>
                                        <option value="Hedge Funds">Hedge Funds</option>
                                        <option value="Commercial Banking">Commercial Banking</option>
                                        <option value="Insurance">Insurance</option>
                                        <option value="Real Estate">Real Estate</option>
                                        <option value="Consulting">Consulting</option>
                                        <option value="Corporate Finance">Corporate Finance</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Notice Period</label></th>
                                <td>
                                    <select name="notice_period" id="notice_period">
                                        <option value="Immediate">Immediate</option>
                                        <option value="1 week">1 week</option>
                                        <option value="2 weeks">2 weeks</option>
                                        <option value="1 month">1 month</option>
                                        <option value="2 months">2 months</option>
                                        <option value="3 months">3 months</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Visa Status</label></th>
                                <td>
                                    <select name="visa_status" id="visa_status">
                                        <option value="Citizen">Citizen</option>
                                        <option value="Permanent Resident">Permanent Resident</option>
                                        <option value="Work Visa">Work Visa</option>
                                        <option value="Student Visa">Student Visa</option>
                                        <option value="Requires Sponsorship">Requires Sponsorship</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary button-large">Save Profile</button>
                        <span id="save-status"></span>
                    </p>
                </form>
            </div>
        </div>
        
        <!-- Experience Modal -->
        <div id="experience-modal" class="profile-modal" style="display: none;">
            <div class="modal-content">
                <span class="close-modal">&times;</span>
                <h2>Add Experience</h2>
                <form id="experience-form">
                    <input type="hidden" id="experience-id" name="experience_id">
                    
                    <table class="form-table">
                        <tr>
                            <th><label>Company Name*</label></th>
                            <td><input type="text" name="company_name" id="exp-company-name" required style="width: 100%;" /></td>
                        </tr>
                        <tr>
                            <th><label>Job Title*</label></th>
                            <td><input type="text" name="job_title" id="exp-job-title" required style="width: 100%;" /></td>
                        </tr>
                        <tr>
                            <th><label>Industry</label></th>
                            <td>
                                <select name="industry" id="exp-industry" style="width: 100%;">
                                    <option value="">Select Industry</option>
                                    <option value="Investment Banking">Investment Banking</option>
                                    <option value="Private Equity">Private Equity</option>
                                    <option value="Asset Management">Asset Management</option>
                                    <option value="Hedge Funds">Hedge Funds</option>
                                    <option value="Commercial Banking">Commercial Banking</option>
                                    <option value="Insurance">Insurance</option>
                                    <option value="Consulting">Consulting</option>
                                    <option value="Technology">Technology</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Start Date*</label></th>
                            <td><input type="date" name="start_date" id="exp-start-date" required /></td>
                        </tr>
                        <tr>
                            <th><label>End Date</label></th>
                            <td>
                                <input type="date" name="end_date" id="exp-end-date" />
                                <label><input type="checkbox" id="exp-is-current" name="is_current" value="1"> Current Role</label>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Description</label></th>
                            <td><textarea name="description" id="exp-description" rows="4" style="width: 100%;"></textarea></td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary">Save Experience</button>
                        <button type="button" class="button cancel-modal">Cancel</button>
                    </p>
                </form>
            </div>
        </div>
        
        <style>
        .profile-section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .profile-completion {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .copletion-bar {
            flex: 1;
            height: 20px;
            background: #e1e1e1;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .copletion-fill {
            height: 100%;
            background: linear-gradient(90deg, #ff6b6b, #ffd93d, #6bcf7f);
            transition: width 0.3s ease;
        }
        
        .skills-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .skill-tag {
            display: inline-block;
            background: #f0f8ff;
            border: 1px solid #2271b1;
            padding: 8px 12px;
            margin: 5px;
            border-radius: 20px;
            font-size: 13px;
        }
        
        .skill-tag .remove-skill {
            color: #dc3232;
            margin-left: 8px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .experience-item {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        
        .experience-item h4 {
            margin: 0 0 5px 0;
            color: #23282d;
        }
        
        .experience-meta {
            color: #666;
            font-size: 13px;
            margin-bottom: 10px;
        }
        
        .profile-modal {
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
            width: 70%;
            max-width: 800px;
            border-radius: 4px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .user-search-container {
            display: flex;
            gap: 10px;
            align-items: center;
            margin: 20px 0;
        }
        </style>
        <?php
    }
    
    private function get_skills_taxonomy() {
        $profile_manager = SFFC_User_Profile_Manager::get_instance();
        return $profile_manager->get_skills_taxonomy();
    }
    
    /**
     * AJAX: Search users
     */
    public function ajax_search_users() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $search = sanitize_text_field($_GET['q'] ?? '');
        
        $users = get_users([
            'search' => '*' . $search . '*',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'number' => 20
        ]);
        
        $results = [];
        foreach ($users as $user) {
            $results[] = [
                'id' => $user->ID,
                'text' => $user->display_name . ' (' . $user->user_email . ')'
            ];
        }
        
        wp_send_json(['results' => $results]);
    }
    
    /**
     * AJAX: Get user profile for admin
     */
    public function ajax_admin_get_user_profile() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('sffc_profile_admin', 'nonce');
        
        $user_id = intval($_POST['user_id'] ?? 0);
        if (!$user_id) {
            wp_send_json_error(['message' => 'Invalid user ID']);
        }
        
        $profile_manager = SFFC_User_Profile_Manager::get_instance();
        $profile = $profile_manager->get_user_profile($user_id);
        
        wp_send_json_success($profile);
    }
    
    /**
     * AJAX: Save user profile from admin
     */
    public function ajax_admin_save_user_profile() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('sffc_profile_admin', 'nonce');
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $profile_data = $_POST['profile_data'] ?? [];
        
        if (!$user_id) {
            wp_send_json_error(['message' => 'Invalid user ID']);
        }
        
        $profile_manager = SFFC_User_Profile_Manager::get_instance();
        $result = $profile_manager->save_profile($user_id, $profile_data);
        
        if ($result) {
            wp_send_json_success(['message' => 'Profile saved successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to save profile']);
        }
    }
}

// Initialize
SFFC_Profile_Admin::get_instance();