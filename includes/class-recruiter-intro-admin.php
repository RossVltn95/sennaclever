<?php
/**
 * Recruiter Intro Admin Panel
 *
 * Admin interface for managing manual recruiter outreach.
 * Allows ops team to log submissions, update statuses, and manage recruiters.
 *
 * @package SennaCareers
 * @since 12.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Recruiter_Intro_Admin {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Status options
     */
    private $status_options = [
        'sent' => 'Sent',
        'viewed' => 'Viewed',
        'responded' => 'Responded',
        'no_response' => 'No Response',
        'declined' => 'Declined'
    ];

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
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // AJAX handlers
        add_action('wp_ajax_sffc_admin_get_campaigns', [$this, 'ajax_get_campaigns']);
        add_action('wp_ajax_sffc_admin_create_submission', [$this, 'ajax_create_submission']);
        add_action('wp_ajax_sffc_admin_update_submission', [$this, 'ajax_update_submission']);
        add_action('wp_ajax_sffc_admin_create_opportunity', [$this, 'ajax_create_opportunity']);
        add_action('wp_ajax_sffc_admin_get_recruiters', [$this, 'ajax_get_recruiters']);
        add_action('wp_ajax_sffc_admin_add_recruiter', [$this, 'ajax_add_recruiter']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'sffc-dashboard',
            'Recruiter Outreach',
            'Recruiter Outreach',
            'edit_posts',
            'sffc-recruiter-outreach',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'sffc-recruiter-outreach') === false) {
            return;
        }

        wp_enqueue_style(
            'sffc-recruiter-outreach-admin',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/recruiter-outreach-admin.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'sffc-recruiter-outreach-admin',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/recruiter-outreach-admin.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script('sffc-recruiter-outreach-admin', 'sffcOutreachAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_outreach_admin_nonce'),
            'statuses' => $this->status_options
        ]);
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'campaigns';
        ?>
        <div class="wrap sffc-outreach-admin">
            <h1>Recruiter Outreach</h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=sffc-recruiter-outreach&tab=campaigns"
                   class="nav-tab <?php echo $current_tab === 'campaigns' ? 'nav-tab-active' : ''; ?>">
                    Campaigns Queue
                </a>
                <a href="?page=sffc-recruiter-outreach&tab=submissions"
                   class="nav-tab <?php echo $current_tab === 'submissions' ? 'nav-tab-active' : ''; ?>">
                    All Submissions
                </a>
                <a href="?page=sffc-recruiter-outreach&tab=recruiters"
                   class="nav-tab <?php echo $current_tab === 'recruiters' ? 'nav-tab-active' : ''; ?>">
                    Recruiter Directory
                </a>
            </nav>

            <div class="sffc-outreach-content">
                <?php
                switch ($current_tab) {
                    case 'submissions':
                        $this->render_submissions_tab();
                        break;
                    case 'recruiters':
                        $this->render_recruiters_tab();
                        break;
                    default:
                        $this->render_campaigns_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render campaigns queue tab
     */
    private function render_campaigns_tab() {
        global $wpdb;

        $campaigns_table = $wpdb->prefix . 'sffc_intro_campaigns';
        $submissions_table = $wpdb->prefix . 'sffc_intro_submissions';

        // Get active campaigns with submission counts
        $campaigns = $wpdb->get_results("
            SELECT
                c.*,
                u.display_name as user_name,
                u.user_email,
                (SELECT COUNT(*) FROM $submissions_table WHERE campaign_id = c.id) as submission_count
            FROM $campaigns_table c
            LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
            WHERE c.status = 'active'
            ORDER BY c.created_at DESC
        ");
        ?>
        <div class="sffc-campaigns-queue">
            <h2>Active Campaigns Awaiting Outreach</h2>

            <?php if (empty($campaigns)): ?>
                <div class="sffc-empty-state">
                    <p>No active campaigns requiring outreach.</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Target Roles</th>
                            <th>Industries</th>
                            <th>Locations</th>
                            <th>Submissions</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaigns as $campaign): ?>
                            <?php
                            $target_roles = json_decode($campaign->target_roles, true) ?: [];
                            $target_industries = json_decode($campaign->target_industries, true) ?: [];
                            $target_locations = json_decode($campaign->target_locations, true) ?: [];
                            ?>
                            <tr data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
                                <td>
                                    <strong><?php echo esc_html($campaign->user_name); ?></strong><br>
                                    <small><?php echo esc_html($campaign->user_email); ?></small>
                                </td>
                                <td>
                                    <?php echo esc_html(implode(', ', array_slice($target_roles, 0, 3))); ?>
                                    <?php if (count($target_roles) > 3): ?>
                                        <span class="sffc-more">+<?php echo count($target_roles) - 3; ?> more</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(implode(', ', $target_industries)); ?></td>
                                <td><?php echo esc_html(implode(', ', $target_locations)); ?></td>
                                <td>
                                    <span class="sffc-count"><?php echo intval($campaign->submission_count); ?></span>
                                </td>
                                <td><?php echo human_time_diff(strtotime($campaign->created_at)) . ' ago'; ?></td>
                                <td>
                                    <button type="button" class="button sffc-log-submission-btn"
                                            data-campaign-id="<?php echo esc_attr($campaign->id); ?>"
                                            data-user-id="<?php echo esc_attr($campaign->user_id); ?>"
                                            data-user-name="<?php echo esc_attr($campaign->user_name); ?>">
                                        Log Submission
                                    </button>
                                    <a href="#" class="sffc-view-submissions"
                                       data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
                                        View All
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Log Submission Modal -->
        <div id="sffc-submission-modal" class="sffc-modal" style="display:none;">
            <div class="sffc-modal-content">
                <div class="sffc-modal-header">
                    <h3>Log Submission</h3>
                    <button type="button" class="sffc-modal-close">&times;</button>
                </div>
                <form id="sffc-submission-form">
                    <input type="hidden" name="campaign_id" id="submission-campaign-id">
                    <input type="hidden" name="user_id" id="submission-user-id">

                    <div class="sffc-form-row">
                        <label>Candidate:</label>
                        <p id="submission-user-name" class="sffc-form-static"></p>
                    </div>

                    <div class="sffc-form-row">
                        <label for="submission-recruiter">Recruiter:</label>
                        <select id="submission-recruiter" name="recruiter_id" required>
                            <option value="">Select a recruiter...</option>
                        </select>
                        <button type="button" class="button sffc-add-recruiter-btn">+ Add New</button>
                    </div>

                    <div class="sffc-form-row">
                        <label for="submission-notes">Notes (internal):</label>
                        <textarea id="submission-notes" name="notes" rows="3"
                                  placeholder="Any notes about this submission..."></textarea>
                    </div>

                    <div class="sffc-modal-footer">
                        <button type="button" class="button sffc-modal-cancel">Cancel</button>
                        <button type="submit" class="button button-primary">Log Submission</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Recruiter Modal -->
        <div id="sffc-recruiter-modal" class="sffc-modal" style="display:none;">
            <div class="sffc-modal-content">
                <div class="sffc-modal-header">
                    <h3>Add Recruiter</h3>
                    <button type="button" class="sffc-modal-close">&times;</button>
                </div>
                <form id="sffc-recruiter-form">
                    <div class="sffc-form-row">
                        <label for="recruiter-name">Name: *</label>
                        <input type="text" id="recruiter-name" name="contact_name" required>
                    </div>

                    <div class="sffc-form-row">
                        <label for="recruiter-email">Email: *</label>
                        <input type="email" id="recruiter-email" name="contact_email" required>
                    </div>

                    <div class="sffc-form-row">
                        <label for="recruiter-company">Company: *</label>
                        <input type="text" id="recruiter-company" name="company" required>
                    </div>

                    <div class="sffc-form-row">
                        <label for="recruiter-title">Title:</label>
                        <input type="text" id="recruiter-title" name="job_title"
                               placeholder="e.g., Senior Recruiter, Talent Partner">
                    </div>

                    <div class="sffc-form-row">
                        <label for="recruiter-linkedin">LinkedIn URL:</label>
                        <input type="url" id="recruiter-linkedin" name="linkedin_url"
                               placeholder="https://linkedin.com/in/...">
                    </div>

                    <div class="sffc-form-row">
                        <label for="recruiter-photo">Photo URL:</label>
                        <input type="url" id="recruiter-photo" name="photo_url"
                               placeholder="https://...">
                    </div>

                    <div class="sffc-form-row">
                        <label for="recruiter-specialties">Specialties:</label>
                        <input type="text" id="recruiter-specialties" name="specialties"
                               placeholder="e.g., Finance, Private Equity, Tech">
                    </div>

                    <div class="sffc-form-row">
                        <label for="recruiter-roles">Typical Roles:</label>
                        <input type="text" id="recruiter-roles" name="typical_roles"
                               placeholder="e.g., VP, Director, Analyst">
                    </div>

                    <div class="sffc-modal-footer">
                        <button type="button" class="button sffc-modal-cancel">Cancel</button>
                        <button type="submit" class="button button-primary">Add Recruiter</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render all submissions tab
     */
    private function render_submissions_tab() {
        global $wpdb;

        $submissions_table = $wpdb->prefix . 'sffc_intro_submissions';
        $recruiters_table = $wpdb->prefix . 'sffc_recruiter_contacts';
        $campaigns_table = $wpdb->prefix . 'sffc_intro_campaigns';

        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 30;
        $offset = ($page - 1) * $per_page;

        $where = "1=1";
        if ($status_filter) {
            $where .= $wpdb->prepare(" AND s.status = %s", $status_filter);
        }

        $total = $wpdb->get_var("SELECT COUNT(*) FROM $submissions_table s WHERE $where");

        $submissions = $wpdb->get_results($wpdb->prepare("
            SELECT
                s.*,
                r.contact_name as recruiter_name,
                r.company_name as recruiter_company,
                u.display_name as user_name,
                u.user_email
            FROM $submissions_table s
            LEFT JOIN $recruiters_table r ON s.recruiter_id = r.id
            LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
            WHERE $where
            ORDER BY s.sent_at DESC
            LIMIT %d OFFSET %d
        ", $per_page, $offset));
        ?>
        <div class="sffc-submissions-list">
            <h2>All Submissions</h2>

            <div class="sffc-filter-bar">
                <select id="sffc-status-filter" onchange="location.href='?page=sffc-recruiter-outreach&tab=submissions&status=' + this.value">
                    <option value="">All Statuses</option>
                    <?php foreach ($this->status_options as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>"
                            <?php selected($status_filter, $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (empty($submissions)): ?>
                <div class="sffc-empty-state">
                    <p>No submissions found.</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Recruiter</th>
                            <th>Status</th>
                            <th>Sent</th>
                            <th>Viewed</th>
                            <th>Responded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $sub): ?>
                            <tr data-submission-id="<?php echo esc_attr($sub->id); ?>">
                                <td>
                                    <strong><?php echo esc_html($sub->user_name); ?></strong><br>
                                    <small><?php echo esc_html($sub->user_email); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($sub->recruiter_name); ?></strong><br>
                                    <small><?php echo esc_html($sub->recruiter_company); ?></small>
                                </td>
                                <td>
                                    <span class="sffc-status sffc-status-<?php echo esc_attr($sub->status); ?>">
                                        <?php echo esc_html($this->status_options[$sub->status] ?? $sub->status); ?>
                                    </span>
                                </td>
                                <td><?php echo $sub->sent_at ? date('M j, Y', strtotime($sub->sent_at)) : '-'; ?></td>
                                <td><?php echo $sub->viewed_at ? date('M j, Y', strtotime($sub->viewed_at)) : '-'; ?></td>
                                <td><?php echo $sub->responded_at ? date('M j, Y', strtotime($sub->responded_at)) : '-'; ?></td>
                                <td>
                                    <select class="sffc-update-status" data-submission-id="<?php echo esc_attr($sub->id); ?>">
                                        <?php foreach ($this->status_options as $value => $label): ?>
                                            <option value="<?php echo esc_attr($value); ?>"
                                                <?php selected($sub->status, $value); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($sub->status === 'responded'): ?>
                                        <button type="button" class="button sffc-create-opportunity-btn"
                                                data-submission-id="<?php echo esc_attr($sub->id); ?>">
                                            Create Opportunity
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php
                // Pagination
                $total_pages = ceil($total / $per_page);
                if ($total_pages > 1):
                ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php
                            echo paginate_links([
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $page
                            ]);
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render recruiters directory tab
     */
    private function render_recruiters_tab() {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_recruiter_contacts';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 30;
        $offset = ($page - 1) * $per_page;

        $where = "1=1";
        if ($search) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where .= $wpdb->prepare(" AND (contact_name LIKE %s OR company_name LIKE %s OR contact_email LIKE %s)",
                $search_like, $search_like, $search_like);
        }

        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where");

        $recruiters = $wpdb->get_results($wpdb->prepare("
            SELECT *, company_name as company, contact_role as job_title, contact_linkedin as linkedin_url
            FROM $table
            WHERE $where
            ORDER BY contact_name ASC
            LIMIT %d OFFSET %d
        ", $per_page, $offset));
        ?>
        <div class="sffc-recruiters-directory">
            <h2>Recruiter Directory</h2>

            <div class="sffc-filter-bar">
                <form method="get">
                    <input type="hidden" name="page" value="sffc-recruiter-outreach">
                    <input type="hidden" name="tab" value="recruiters">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>"
                           placeholder="Search recruiters...">
                    <button type="submit" class="button">Search</button>
                </form>
                <button type="button" class="button button-primary sffc-add-recruiter-btn">
                    + Add Recruiter
                </button>
            </div>

            <?php if (empty($recruiters)): ?>
                <div class="sffc-empty-state">
                    <p>No recruiters found. Add your first recruiter contact.</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:50px;">Photo</th>
                            <th>Name</th>
                            <th>Company</th>
                            <th>Title</th>
                            <th>Email</th>
                            <th>Specialties</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recruiters as $rec): ?>
                            <?php $specialties = json_decode($rec->specialties, true) ?: []; ?>
                            <tr data-recruiter-id="<?php echo esc_attr($rec->id); ?>">
                                <td>
                                    <?php if ($rec->photo_url): ?>
                                        <img src="<?php echo esc_url($rec->photo_url); ?>"
                                             alt="" class="sffc-recruiter-thumb">
                                    <?php else: ?>
                                        <div class="sffc-recruiter-initials">
                                            <?php echo esc_html(substr($rec->contact_name, 0, 2)); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($rec->contact_name); ?></strong>
                                    <?php if ($rec->linkedin_url): ?>
                                        <a href="<?php echo esc_url($rec->linkedin_url); ?>"
                                           target="_blank" class="sffc-linkedin-link">
                                            <span class="dashicons dashicons-linkedin"></span>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($rec->company); ?></td>
                                <td><?php echo esc_html($rec->job_title); ?></td>
                                <td><?php echo esc_html($rec->contact_email); ?></td>
                                <td>
                                    <?php if (!empty($specialties)): ?>
                                        <?php foreach (array_slice($specialties, 0, 3) as $spec): ?>
                                            <span class="sffc-tag"><?php echo esc_html($spec); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="button sffc-edit-recruiter-btn"
                                            data-recruiter-id="<?php echo esc_attr($rec->id); ?>">
                                        Edit
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php
                $total_pages = ceil($total / $per_page);
                if ($total_pages > 1):
                ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php
                            echo paginate_links([
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $page
                            ]);
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * AJAX: Get active campaigns
     */
    public function ajax_get_campaigns() {
        check_ajax_referer('sffc_outreach_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        global $wpdb;

        $campaigns_table = $wpdb->prefix . 'sffc_intro_campaigns';

        $campaigns = $wpdb->get_results("
            SELECT c.*, u.display_name as user_name, u.user_email
            FROM $campaigns_table c
            LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
            WHERE c.status = 'active'
            ORDER BY c.created_at DESC
        ");

        wp_send_json_success(['campaigns' => $campaigns]);
    }

    /**
     * AJAX: Create submission
     */
    public function ajax_create_submission() {
        check_ajax_referer('sffc_outreach_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : null;
        $recruiter_id = isset($_POST['recruiter_id']) ? intval($_POST['recruiter_id']) : 0;
        $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';

        if (!$user_id || !$recruiter_id) {
            wp_send_json_error(['message' => 'Missing required fields']);
            return;
        }

        $tracker = SFFC_Intro_Submission_Tracker::get_instance();
        $result = $tracker->create_submission([
            'user_id' => $user_id,
            'campaign_id' => $campaign_id,
            'recruiter_id' => $recruiter_id,
            'notes' => $notes
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }

        wp_send_json_success([
            'message' => 'Submission logged successfully',
            'submission_id' => $result
        ]);
    }

    /**
     * AJAX: Update submission status
     */
    public function ajax_update_submission() {
        check_ajax_referer('sffc_outreach_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : null;

        if (!$submission_id || !$status) {
            wp_send_json_error(['message' => 'Missing required fields']);
            return;
        }

        if (!array_key_exists($status, $this->status_options)) {
            wp_send_json_error(['message' => 'Invalid status']);
            return;
        }

        $tracker = SFFC_Intro_Submission_Tracker::get_instance();
        $result = $tracker->update_submission_status($submission_id, $status, $notes);

        if ($result === false) {
            wp_send_json_error(['message' => 'Failed to update submission']);
            return;
        }

        wp_send_json_success([
            'message' => 'Submission updated',
            'status' => $status
        ]);
    }

    /**
     * AJAX: Create opportunity from responded submission
     */
    public function ajax_create_opportunity() {
        check_ajax_referer('sffc_outreach_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
        $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';

        if (!$submission_id) {
            wp_send_json_error(['message' => 'Missing submission ID']);
            return;
        }

        // Get submission details
        $tracker = SFFC_Intro_Submission_Tracker::get_instance();
        $submission = $tracker->get_submission($submission_id);

        if (!$submission) {
            wp_send_json_error(['message' => 'Submission not found']);
            return;
        }

        global $wpdb;

        $table = $wpdb->prefix . 'sffc_opportunities';

        $result = $wpdb->insert($table, [
            'user_id' => $submission->user_id,
            'campaign_id' => $submission->campaign_id,
            'submission_id' => $submission_id,
            'recruiter_id' => $submission->recruiter_id,
            'status' => 'new',
            'opportunity_type' => 'general_interest',
            'notes' => $notes,
            'created_at' => current_time('mysql')
        ]);

        if ($result === false) {
            wp_send_json_error(['message' => 'Failed to create opportunity']);
            return;
        }

        wp_send_json_success([
            'message' => 'Opportunity created successfully',
            'opportunity_id' => $wpdb->insert_id
        ]);
    }

    /**
     * AJAX: Get recruiters list
     */
    public function ajax_get_recruiters() {
        check_ajax_referer('sffc_outreach_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        global $wpdb;

        $table = $wpdb->prefix . 'sffc_recruiter_contacts';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        $where = "1=1";
        if ($search) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where .= $wpdb->prepare(" AND (contact_name LIKE %s OR company_name LIKE %s)",
                $search_like, $search_like);
        }

        $recruiters = $wpdb->get_results("
            SELECT id, contact_name, company_name as company, contact_role as job_title, photo_url
            FROM $table
            WHERE $where
            ORDER BY contact_name ASC
            LIMIT 50
        ");

        wp_send_json_success(['recruiters' => $recruiters]);
    }

    /**
     * AJAX: Add/update recruiter
     */
    public function ajax_add_recruiter() {
        check_ajax_referer('sffc_outreach_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        $data = [
            'contact_name' => isset($_POST['contact_name']) ? sanitize_text_field($_POST['contact_name']) : '',
            'contact_email' => isset($_POST['contact_email']) ? sanitize_email($_POST['contact_email']) : '',
            'company_name' => isset($_POST['company']) ? sanitize_text_field($_POST['company']) : '',
            'contact_role' => isset($_POST['job_title']) ? sanitize_text_field($_POST['job_title']) : '',
            'contact_linkedin' => isset($_POST['linkedin_url']) ? esc_url_raw($_POST['linkedin_url']) : '',
            'photo_url' => isset($_POST['photo_url']) ? esc_url_raw($_POST['photo_url']) : '',
        ];

        // Handle specialties and roles as JSON
        if (isset($_POST['specialties'])) {
            $specialties = array_map('trim', explode(',', sanitize_text_field($_POST['specialties'])));
            $data['specialties'] = json_encode(array_filter($specialties));
        }

        if (isset($_POST['typical_roles'])) {
            $roles = array_map('trim', explode(',', sanitize_text_field($_POST['typical_roles'])));
            $data['typical_roles'] = json_encode(array_filter($roles));
        }

        if (empty($data['contact_name']) || empty($data['contact_email']) || empty($data['company_name'])) {
            wp_send_json_error(['message' => 'Name, email, and company are required']);
            return;
        }

        global $wpdb;

        $table = $wpdb->prefix . 'sffc_recruiter_contacts';

        // Check if updating or inserting
        $recruiter_id = isset($_POST['recruiter_id']) ? intval($_POST['recruiter_id']) : 0;

        if ($recruiter_id) {
            $result = $wpdb->update($table, $data, ['id' => $recruiter_id]);
        } else {
            $result = $wpdb->insert($table, $data);
            $recruiter_id = $wpdb->insert_id;
        }

        if ($result === false) {
            wp_send_json_error(['message' => 'Database error']);
            return;
        }

        wp_send_json_success([
            'message' => 'Recruiter saved successfully',
            'recruiter_id' => $recruiter_id,
            'recruiter' => [
                'id' => $recruiter_id,
                'contact_name' => $data['contact_name'],
                'company' => $data['company']
            ]
        ]);
    }
}

// Initialize
SFFC_Recruiter_Intro_Admin::get_instance();
