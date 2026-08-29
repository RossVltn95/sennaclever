<?php
/**
 * Recruiter Introduction Admin Dashboard
 *
 * Admin interface for managing introduction requests.
 * Allows team to review, approve, and track introductions.
 *
 * @package SennaCareers
 * @since 12.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Recruiter_Introduction_Admin {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Valid status transitions
     */
    private $status_flow = [
        'pending_review' => ['approved', 'rejected'],
        'approved' => ['sent'],
        'sent' => ['awaiting_response'],
        'awaiting_response' => ['accepted', 'rejected', 'expired'],
        'accepted' => [],
        'rejected' => [],
        'expired' => []
    ];

    /**
     * Status labels for display
     */
    private $status_labels = [
        'pending_review' => 'Pending Review',
        'approved' => 'Approved',
        'sent' => 'Sent to Recruiter',
        'awaiting_response' => 'Awaiting Response',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
        'expired' => 'Expired'
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

        // AJAX handlers for admin actions
        add_action('wp_ajax_sffc_update_intro_status', [$this, 'ajax_update_status']);
        add_action('wp_ajax_sffc_add_pitch_message', [$this, 'ajax_add_pitch_message']);
        add_action('wp_ajax_sffc_record_recruiter_response', [$this, 'ajax_record_response']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'sffc-dashboard',
            'Introduction Requests',
            'Introductions',
            'edit_posts',
            'sffc-introductions',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'sffc-introductions') === false) {
            return;
        }

        wp_enqueue_style(
            'sffc-intro-admin',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/recruiter-introduction-admin.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'sffc-intro-admin',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/recruiter-introduction-admin.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script('sffc-intro-admin', 'sffcIntroAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_intro_admin_nonce'),
            'status_labels' => $this->status_labels
        ]);
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Get current filter
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        // Get requests
        $requests = $this->get_requests($status_filter, $search);

        // Get stats
        $stats = $this->get_stats();

        // Check if viewing single request
        $single_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($single_id) {
            $this->render_single_request($single_id);
            return;
        }

        ?>
        <div class="wrap sffc-intro-admin">
            <h1 class="wp-heading-inline">Introduction Requests</h1>

            <!-- Stats Overview -->
            <div class="sffc-intro-stats">
                <div class="sffc-intro-stat">
                    <span class="sffc-intro-stat-value"><?php echo esc_html($stats['pending']); ?></span>
                    <span class="sffc-intro-stat-label">Pending Review</span>
                </div>
                <div class="sffc-intro-stat">
                    <span class="sffc-intro-stat-value"><?php echo esc_html($stats['awaiting']); ?></span>
                    <span class="sffc-intro-stat-label">Awaiting Response</span>
                </div>
                <div class="sffc-intro-stat">
                    <span class="sffc-intro-stat-value"><?php echo esc_html($stats['accepted']); ?></span>
                    <span class="sffc-intro-stat-label">Accepted</span>
                </div>
                <div class="sffc-intro-stat">
                    <span class="sffc-intro-stat-value"><?php echo esc_html($stats['response_rate']); ?>%</span>
                    <span class="sffc-intro-stat-label">Response Rate</span>
                </div>
            </div>

            <!-- Filters -->
            <div class="sffc-intro-filters">
                <form method="get">
                                        <input type="hidden" name="page" value="sffc-introductions">

                    <select name="status">
                        <option value="">All Statuses</option>
                        <?php foreach ($this->status_labels as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($status_filter, $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search candidates...">

                    <button type="submit" class="button">Filter</button>
                </form>
            </div>

            <!-- Requests Table -->
            <table class="wp-list-table widefat fixed striped sffc-intro-table">
                <thead>
                    <tr>
                        <th class="column-candidate">Candidate</th>
                        <th class="column-job">Job</th>
                        <th class="column-score">Match</th>
                        <th class="column-status">Status</th>
                        <th class="column-date">Requested</th>
                        <th class="column-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="6" class="sffc-intro-empty">
                                No introduction requests found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $request): ?>
                            <tr data-request-id="<?php echo esc_attr($request['id']); ?>">
                                <td class="column-candidate">
                                    <strong>
                                        <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $request['user_id'])); ?>">
                                            <?php echo esc_html($request['user_name']); ?>
                                        </a>
                                    </strong>
                                    <br>
                                    <span class="sffc-intro-email"><?php echo esc_html($request['user_email']); ?></span>
                                </td>
                                <td class="column-job">
                                    <a href="<?php echo esc_url(get_permalink($request['job_id'])); ?>" target="_blank">
                                        <?php echo esc_html($request['job_title']); ?>
                                    </a>
                                    <br>
                                    <span class="sffc-intro-company"><?php echo esc_html($request['job_company']); ?></span>
                                </td>
                                <td class="column-score">
                                    <span class="sffc-intro-score sffc-intro-score-<?php echo $this->get_score_class($request['compatibility_score']); ?>">
                                        <?php echo esc_html($request['compatibility_score']); ?>%
                                    </span>
                                </td>
                                <td class="column-status">
                                    <span class="sffc-intro-status sffc-intro-status-<?php echo esc_attr($request['status']); ?>">
                                        <?php echo esc_html($this->status_labels[$request['status']] ?? $request['status']); ?>
                                    </span>
                                </td>
                                <td class="column-date">
                                    <?php echo esc_html(human_time_diff(strtotime($request['created_at']), current_time('timestamp'))); ?> ago
                                </td>
                                <td class="column-actions">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=sffc-introductions&id=' . $request['id'])); ?>"
                                       class="button button-small">
                                        View
                                    </a>
                                    <?php if ($request['status'] === 'pending_review'): ?>
                                        <button class="button button-small button-primary sffc-approve-btn"
                                                data-id="<?php echo esc_attr($request['id']); ?>">
                                            Approve
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render single request detail view
     */
    private function render_single_request($id) {
        $request = $this->get_request($id);

        if (!$request) {
            echo '<div class="wrap"><h1>Request Not Found</h1></div>';
            return;
        }

        $next_statuses = $this->status_flow[$request['status']] ?? [];

        ?>
        <div class="wrap sffc-intro-admin sffc-intro-single">
            <h1>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sffc-introductions')); ?>">&larr;</a>
                Introduction Request #<?php echo esc_html($id); ?>
            </h1>

            <div class="sffc-intro-detail-grid">
                <!-- Main Content -->
                <div class="sffc-intro-detail-main">
                    <!-- Candidate Card -->
                    <div class="sffc-intro-card">
                        <h3>Candidate</h3>
                        <div class="sffc-intro-candidate">
                            <?php echo get_avatar($request['user_id'], 64); ?>
                            <div class="sffc-intro-candidate-info">
                                <strong><?php echo esc_html($request['user_name']); ?></strong>
                                <span><?php echo esc_html($request['user_email']); ?></span>
                                <span class="sffc-intro-score sffc-intro-score-<?php echo $this->get_score_class($request['compatibility_score']); ?>">
                                    <?php echo esc_html($request['compatibility_score']); ?>% Match
                                </span>
                            </div>
                        </div>

                        <h4>Candidate's Message</h4>
                        <div class="sffc-intro-message">
                            <?php echo nl2br(esc_html($request['message'])); ?>
                        </div>

                        <?php if (!empty($request['pitch_message'])): ?>
                            <h4>Pitch Message (Sent to Recruiter)</h4>
                            <div class="sffc-intro-pitch">
                                <?php echo nl2br(esc_html($request['pitch_message'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Job Card -->
                    <div class="sffc-intro-card">
                        <h3>Position</h3>
                        <div class="sffc-intro-job">
                            <strong><?php echo esc_html($request['job_title']); ?></strong>
                            <span><?php echo esc_html($request['job_company']); ?></span>
                            <a href="<?php echo esc_url(get_permalink($request['job_id'])); ?>" target="_blank" class="button button-small">
                                View Job
                            </a>
                        </div>
                    </div>

                    <!-- Recruiter Response -->
                    <?php if (!empty($request['recruiter_response']) || !empty($request['recruiter_feedback'])): ?>
                        <div class="sffc-intro-card">
                            <h3>Recruiter Response</h3>
                            <?php if (!empty($request['recruiter_response'])): ?>
                                <div class="sffc-intro-response">
                                    <?php echo nl2br(esc_html($request['recruiter_response'])); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($request['recruiter_feedback'])): ?>
                                <h4>Feedback</h4>
                                <div class="sffc-intro-feedback">
                                    <?php echo nl2br(esc_html($request['recruiter_feedback'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="sffc-intro-detail-sidebar">
                    <!-- Status Card -->
                    <div class="sffc-intro-card">
                        <h3>Status</h3>
                        <span class="sffc-intro-status sffc-intro-status-<?php echo esc_attr($request['status']); ?>">
                            <?php echo esc_html($this->status_labels[$request['status']] ?? $request['status']); ?>
                        </span>

                        <?php if (!empty($next_statuses)): ?>
                            <div class="sffc-intro-status-actions">
                                <label>Update Status:</label>
                                <?php foreach ($next_statuses as $next_status): ?>
                                    <button class="button sffc-status-btn"
                                            data-id="<?php echo esc_attr($id); ?>"
                                            data-status="<?php echo esc_attr($next_status); ?>">
                                        <?php echo esc_html($this->status_labels[$next_status]); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Add Pitch Message (for pending/approved) -->
                        <?php if (in_array($request['status'], ['pending_review', 'approved'])): ?>
                            <div class="sffc-intro-pitch-form">
                                <label>Pitch Message to Recruiter:</label>
                                <textarea id="pitch-message" rows="4" placeholder="Write your pitch for this candidate..."><?php echo esc_textarea($request['pitch_message'] ?? ''); ?></textarea>
                                <button class="button button-primary sffc-save-pitch-btn" data-id="<?php echo esc_attr($id); ?>">
                                    Save Pitch
                                </button>
                            </div>
                        <?php endif; ?>

                        <!-- Record Response (for awaiting_response) -->
                        <?php if ($request['status'] === 'awaiting_response'): ?>
                            <div class="sffc-intro-response-form">
                                <label>Record Recruiter Response:</label>
                                <textarea id="recruiter-response" rows="3" placeholder="What did the recruiter say?"></textarea>
                                <textarea id="recruiter-feedback" rows="2" placeholder="Feedback for candidate (optional)"></textarea>
                                <div class="sffc-intro-response-btns">
                                    <button class="button button-primary sffc-record-response-btn"
                                            data-id="<?php echo esc_attr($id); ?>"
                                            data-outcome="accepted">
                                        Mark Accepted
                                    </button>
                                    <button class="button sffc-record-response-btn"
                                            data-id="<?php echo esc_attr($id); ?>"
                                            data-outcome="rejected">
                                        Mark Rejected
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Timeline -->
                    <div class="sffc-intro-card">
                        <h3>Timeline</h3>
                        <ul class="sffc-intro-timeline">
                            <li>
                                <span class="sffc-intro-timeline-date"><?php echo esc_html(date('M j, Y g:ia', strtotime($request['created_at']))); ?></span>
                                <span class="sffc-intro-timeline-event">Request submitted</span>
                            </li>
                            <?php if ($request['reviewed_at']): ?>
                                <li>
                                    <span class="sffc-intro-timeline-date"><?php echo esc_html(date('M j, Y g:ia', strtotime($request['reviewed_at']))); ?></span>
                                    <span class="sffc-intro-timeline-event">Reviewed</span>
                                </li>
                            <?php endif; ?>
                            <?php if ($request['sent_at']): ?>
                                <li>
                                    <span class="sffc-intro-timeline-date"><?php echo esc_html(date('M j, Y g:ia', strtotime($request['sent_at']))); ?></span>
                                    <span class="sffc-intro-timeline-event">Sent to recruiter</span>
                                </li>
                            <?php endif; ?>
                            <?php if ($request['responded_at']): ?>
                                <li>
                                    <span class="sffc-intro-timeline-date"><?php echo esc_html(date('M j, Y g:ia', strtotime($request['responded_at']))); ?></span>
                                    <span class="sffc-intro-timeline-event">Recruiter responded</span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get introduction requests
     */
    private function get_requests($status = '', $search = '', $limit = 50) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_introduction_requests';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return [];
        }

        $sql = "SELECT * FROM $table_name WHERE 1=1";
        $params = [];

        if ($status) {
            $sql .= " AND status = %s";
            $params[] = $status;
        }

        if ($search) {
            // Search by user email or name - we'll filter in PHP
        }

        $sql .= " ORDER BY created_at DESC LIMIT %d";
        $params[] = $limit;

        if (!empty($params)) {
            $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        } else {
            $results = $wpdb->get_results($sql, ARRAY_A);
        }

        // Enrich with user and job data
        foreach ($results as &$row) {
            $user = get_user_by('id', $row['user_id']);
            $row['user_name'] = $user ? $user->display_name : 'Unknown';
            $row['user_email'] = $user ? $user->user_email : '';

            $row['job_title'] = get_the_title($row['job_id']) ?: 'Job #' . $row['job_id'];
            $row['job_company'] = get_post_meta($row['job_id'], 'sffc_actual_company', true)
                ?: get_post_meta($row['job_id'], 'sffc_source_name', true);

            // Filter by search if needed
            if ($search) {
                $search_lower = strtolower($search);
                if (strpos(strtolower($row['user_name']), $search_lower) === false &&
                    strpos(strtolower($row['user_email']), $search_lower) === false &&
                    strpos(strtolower($row['job_title']), $search_lower) === false) {
                    $row = null;
                }
            }
        }

        return array_filter($results);
    }

    /**
     * Get single request
     */
    private function get_request($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_introduction_requests';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        // Enrich with user and job data
        $user = get_user_by('id', $row['user_id']);
        $row['user_name'] = $user ? $user->display_name : 'Unknown';
        $row['user_email'] = $user ? $user->user_email : '';

        $row['job_title'] = get_the_title($row['job_id']) ?: 'Job #' . $row['job_id'];
        $row['job_company'] = get_post_meta($row['job_id'], 'sffc_actual_company', true)
            ?: get_post_meta($row['job_id'], 'sffc_source_name', true);

        return $row;
    }

    /**
     * Get stats
     */
    private function get_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_introduction_requests';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return [
                'pending' => 0,
                'awaiting' => 0,
                'accepted' => 0,
                'response_rate' => 0
            ];
        }

        $pending = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending_review'");
        $awaiting = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'awaiting_response'");
        $accepted = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'accepted'");

        $sent = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status IN ('sent', 'awaiting_response', 'accepted', 'rejected')");
        $responded = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status IN ('accepted', 'rejected')");

        $response_rate = $sent > 0 ? round(($responded / $sent) * 100) : 0;

        return [
            'pending' => intval($pending),
            'awaiting' => intval($awaiting),
            'accepted' => intval($accepted),
            'response_rate' => $response_rate
        ];
    }

    /**
     * Get score class for styling
     */
    private function get_score_class($score) {
        if ($score >= 80) return 'high';
        if ($score >= 60) return 'medium';
        return 'low';
    }

    /**
     * AJAX: Update status
     */
    public function ajax_update_status() {
        check_ajax_referer('sffc_intro_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permission denied']);
            return;
        }

        $id = intval($_POST['id'] ?? 0);
        $new_status = sanitize_text_field($_POST['status'] ?? '');

        if (!$id || !$new_status) {
            wp_send_json_error(['message' => 'Invalid request']);
            return;
        }

        // Validate status
        if (!isset($this->status_labels[$new_status])) {
            wp_send_json_error(['message' => 'Invalid status']);
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_introduction_requests';

        // Get current request
        $current = $wpdb->get_row($wpdb->prepare(
            "SELECT status FROM $table_name WHERE id = %d",
            $id
        ));

        if (!$current) {
            wp_send_json_error(['message' => 'Request not found']);
            return;
        }

        // Validate transition
        $allowed = $this->status_flow[$current->status] ?? [];
        if (!in_array($new_status, $allowed)) {
            wp_send_json_error(['message' => 'Invalid status transition']);
            return;
        }

        // Determine which timestamp to set
        $update_data = ['status' => $new_status];

        if ($new_status === 'approved') {
            $update_data['reviewed_at'] = current_time('mysql');
        } elseif ($new_status === 'sent') {
            $update_data['sent_at'] = current_time('mysql');
        } elseif (in_array($new_status, ['accepted', 'rejected'])) {
            $update_data['responded_at'] = current_time('mysql');
        }

        $updated = $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $id]
        );

        if ($updated === false) {
            wp_send_json_error(['message' => 'Failed to update status']);
            return;
        }

        // Send automated recruiter email when marked as 'sent'
        if ($new_status === 'sent') {
            // Ensure we have a valid recruiter contact before sending
            $contact_id = $this->ensure_recruiter_contact($id);
            if (!$contact_id) {
                error_log('SFFC Introduction: No recruiter contact found/created for request ' . $id);
            }

            $email_result = $this->send_recruiter_introduction_email($id);
            if (!$email_result['success']) {
                // Log error but don't fail the status update
                error_log('SFFC Introduction: Failed to send recruiter email for request ' . $id . ': ' . $email_result['message']);
            }
        }

        // Notify candidate if accepted/rejected
        if (in_array($new_status, ['accepted', 'rejected'])) {
            $this->notify_candidate($id, $new_status);
        }

        wp_send_json_success([
            'message' => 'Status updated',
            'new_status' => $new_status,
            'label' => $this->status_labels[$new_status]
        ]);
    }

    /**
     * AJAX: Add/update pitch message
     */
    public function ajax_add_pitch_message() {
        check_ajax_referer('sffc_intro_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permission denied']);
            return;
        }

        $id = intval($_POST['id'] ?? 0);
        $pitch = sanitize_textarea_field($_POST['pitch'] ?? '');

        if (!$id) {
            wp_send_json_error(['message' => 'Invalid request']);
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_introduction_requests';

        $updated = $wpdb->update(
            $table_name,
            ['pitch_message' => $pitch],
            ['id' => $id]
        );

        if ($updated === false) {
            wp_send_json_error(['message' => 'Failed to save pitch']);
            return;
        }

        wp_send_json_success(['message' => 'Pitch saved']);
    }

    /**
     * AJAX: Record recruiter response
     */
    public function ajax_record_response() {
        check_ajax_referer('sffc_intro_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permission denied']);
            return;
        }

        $id = intval($_POST['id'] ?? 0);
        $outcome = sanitize_text_field($_POST['outcome'] ?? '');
        $response = sanitize_textarea_field($_POST['response'] ?? '');
        $feedback = sanitize_textarea_field($_POST['feedback'] ?? '');

        if (!$id || !in_array($outcome, ['accepted', 'rejected'])) {
            wp_send_json_error(['message' => 'Invalid request']);
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_introduction_requests';

        $updated = $wpdb->update(
            $table_name,
            [
                'status' => $outcome,
                'recruiter_response' => $response,
                'recruiter_feedback' => $feedback,
                'responded_at' => current_time('mysql')
            ],
            ['id' => $id]
        );

        if ($updated === false) {
            wp_send_json_error(['message' => 'Failed to record response']);
            return;
        }

        // Notify candidate
        $this->notify_candidate($id, $outcome);

        wp_send_json_success([
            'message' => 'Response recorded',
            'new_status' => $outcome
        ]);
    }

    /**
     * Notify candidate of introduction outcome
     */
    private function notify_candidate($request_id, $outcome) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_introduction_requests';

        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $request_id
        ));

        if (!$request) {
            return;
        }

        $user = get_user_by('id', $request->user_id);
        if (!$user) {
            return;
        }

        $job_title = get_the_title($request->job_id);
        $job_company = get_post_meta($request->job_id, 'sffc_actual_company', true)
            ?: get_post_meta($request->job_id, 'sffc_source_name', true);

        if ($outcome === 'accepted') {
            $subject = sprintf('Great news! Your introduction to %s was accepted', $job_company);
            $body = sprintf(
                "Hi %s,\n\n" .
                "Great news! The recruiting team at %s has accepted your introduction for the %s position.\n\n" .
                "%s\n\n" .
                "Next steps: They may reach out to you directly, or we'll provide further instructions soon.\n\n" .
                "Best of luck!\n" .
                "The MENA Careers Team",
                $user->display_name,
                $job_company,
                $job_title,
                $request->recruiter_response ? "Their message:\n" . $request->recruiter_response : ""
            );
        } else {
            $subject = sprintf('Update on your introduction to %s', $job_company);
            $body = sprintf(
                "Hi %s,\n\n" .
                "Unfortunately, the recruiting team at %s has decided not to proceed with your introduction for the %s position at this time.\n\n" .
                "%s\n\n" .
                "Don't be discouraged - there are many other opportunities that match your profile. We'll keep working to find the right fit for you.\n\n" .
                "View more matched roles: %s\n\n" .
                "Best regards,\n" .
                "The MENA Careers Team",
                $user->display_name,
                $job_company,
                $job_title,
                $request->recruiter_feedback ? "Feedback:\n" . $request->recruiter_feedback : "",
                home_url('/jobs/')
            );
        }

        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        wp_mail($user->user_email, $subject, $body, $headers);

        // Mark as notified
        $wpdb->update(
            $table_name,
            ['candidate_notified' => 1],
            ['id' => $request_id]
        );
    }

    /**
     * Send automated introduction email to recruiter
     *
     * Called when status changes to 'sent'. Sends a professional email
     * introducing the candidate to the recruiter with all relevant context.
     *
     * @param int $request_id The introduction request ID
     * @return array ['success' => bool, 'message' => string]
     */
    private function send_recruiter_introduction_email($request_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_introduction_requests';
        $contacts_table = $wpdb->prefix . 'sffc_recruiter_contacts';

        // Get the request
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $request_id
        ));

        if (!$request) {
            return ['success' => false, 'message' => 'Request not found'];
        }

        // Get candidate info
        $candidate = get_user_by('id', $request->user_id);
        if (!$candidate) {
            return ['success' => false, 'message' => 'Candidate not found'];
        }

        // Get job info
        $job_title = get_the_title($request->job_id);
        $job_company = get_post_meta($request->job_id, 'sffc_actual_company', true)
            ?: get_post_meta($request->job_id, 'sffc_source_name', true);
        $job_url = get_permalink($request->job_id);

        // Get candidate profile for context
        $profile = get_user_meta($request->user_id, 'sffc_career_profile', true) ?: [];
        $cv_url = get_user_meta($request->user_id, 'sffc_cv_url', true);

        // Determine recruiter email
        $recruiter_email = null;
        $recruiter_name = 'Hiring Team';

        // First try: Get from recruiter contacts table
        if ($request->recruiter_id) {
            $contact = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $contacts_table WHERE id = %d AND accepts_introductions = 1",
                $request->recruiter_id
            ));

            if ($contact) {
                $recruiter_email = $contact->contact_email;
                $recruiter_name = $contact->contact_name;
            }
        }

        // Second try: Get from job meta
        if (!$recruiter_email) {
            $job_recruiter_email = get_post_meta($request->job_id, 'sffc_recruiter_email', true);
            $job_recruiter_name = get_post_meta($request->job_id, 'sffc_recruiter_name', true);

            if ($job_recruiter_email) {
                $recruiter_email = $job_recruiter_email;
                $recruiter_name = $job_recruiter_name ?: 'Hiring Team';
            }
        }

        // Third try: Get from user meta if recruiter_id is a user
        if (!$recruiter_email && $request->recruiter_id) {
            $recruiter_user = get_user_by('id', $request->recruiter_id);
            if ($recruiter_user) {
                $recruiter_email = $recruiter_user->user_email;
                $recruiter_name = $recruiter_user->display_name;
            }
        }

        // Fallback to admin if no recruiter email found
        if (!$recruiter_email) {
            $recruiter_email = get_option('admin_email');
            $recruiter_name = 'Team';
        }

        // Build the email subject
        $subject = sprintf(
            'Candidate Introduction: %s for %s',
            $candidate->display_name,
            $job_title
        );

        // Build candidate highlights
        $highlights = [];
        if ($request->compatibility_score) {
            $highlights[] = sprintf('%d%% match score', $request->compatibility_score);
        }
        if (!empty($profile['years_experience'])) {
            $highlights[] = sprintf('%s years experience', $profile['years_experience']);
        }
        if (!empty($profile['current_location'])) {
            $highlights[] = 'Based in ' . $profile['current_location'];
        }

        $highlights_text = !empty($highlights) ? implode(' • ', $highlights) : '';

        // Use the pitch message if available, otherwise generate one
        $pitch = $request->pitch_message;
        if (empty($pitch)) {
            $pitch = $this->generate_default_pitch($candidate, $request, $profile, $job_title, $job_company);
        }

        // Build the email body (HTML)
        $body_html = $this->build_recruiter_email_html([
            'recruiter_name' => $recruiter_name,
            'candidate_name' => $candidate->display_name,
            'candidate_email' => $candidate->user_email,
            'job_title' => $job_title,
            'job_company' => $job_company,
            'job_url' => $job_url,
            'match_score' => $request->compatibility_score,
            'highlights' => $highlights_text,
            'pitch' => $pitch,
            'candidate_message' => $request->message,
            'cv_url' => $cv_url,
            'response_url' => $this->get_response_url($request_id)
        ]);

        // Build plain text fallback
        $body_text = $this->build_recruiter_email_text([
            'recruiter_name' => $recruiter_name,
            'candidate_name' => $candidate->display_name,
            'candidate_email' => $candidate->user_email,
            'job_title' => $job_title,
            'job_company' => $job_company,
            'match_score' => $request->compatibility_score,
            'pitch' => $pitch,
            'candidate_message' => $request->message
        ]);

        // Set up headers for HTML email
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: MENA Careers <introductions@' . parse_url(home_url(), PHP_URL_HOST) . '>',
            'Reply-To: ' . get_option('admin_email')
        ];

        // Send the email
        $sent = wp_mail($recruiter_email, $subject, $body_html, $headers);

        if ($sent) {
            // Update recruiter contact stats if we have one
            if ($request->recruiter_id) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE $contacts_table SET total_intros_sent = total_intros_sent + 1 WHERE id = %d",
                    $request->recruiter_id
                ));
            }

            // Log successful send
            do_action('sffc_introduction_email_sent', $request_id, $recruiter_email);

            return ['success' => true, 'message' => 'Email sent to ' . $recruiter_email];
        }

        return ['success' => false, 'message' => 'wp_mail failed'];
    }

    /**
     * Generate a default pitch message if none was provided
     */
    private function generate_default_pitch($candidate, $request, $profile, $job_title, $job_company) {
        $pitch_parts = [];

        $pitch_parts[] = sprintf(
            "I'd like to introduce %s as a candidate for the %s position at %s.",
            $candidate->display_name,
            $job_title,
            $job_company
        );

        if ($request->compatibility_score >= 80) {
            $pitch_parts[] = sprintf(
                "Based on our matching analysis, %s is a strong fit for this role with a %d%% compatibility score.",
                $candidate->first_name ?: $candidate->display_name,
                $request->compatibility_score
            );
        } elseif ($request->compatibility_score >= 60) {
            $pitch_parts[] = sprintf(
                "%s shows solid alignment with the role requirements (%d%% match).",
                $candidate->first_name ?: $candidate->display_name,
                $request->compatibility_score
            );
        }

        if (!empty($profile['years_experience'])) {
            $pitch_parts[] = sprintf(
                "They bring %s years of relevant experience to the table.",
                $profile['years_experience']
            );
        }

        $pitch_parts[] = "I believe they would be a valuable addition to your candidate pipeline and worth a conversation.";

        return implode("\n\n", $pitch_parts);
    }

    /**
     * Build HTML email for recruiter
     */
    private function build_recruiter_email_html($data) {
        $score_color = '#10b981'; // green
        if ($data['match_score'] < 70) {
            $score_color = '#f59e0b'; // amber
        }
        if ($data['match_score'] < 50) {
            $score_color = '#ef4444'; // red
        }

        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Introduction</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f4f4f5; line-height: 1.6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f5; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #18181b; padding: 24px 32px;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 600;">
                                Candidate Introduction
                            </h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 32px;">
                            <p style="margin: 0 0 20px 0; color: #374151;">
                                Hi <?php echo esc_html($data['recruiter_name']); ?>,
                            </p>

                            <!-- Candidate Card -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td width="60" valign="top">
                                                    <div style="width: 50px; height: 50px; background-color: #18181b; border-radius: 50%; color: #ffffff; font-size: 18px; font-weight: 600; text-align: center; line-height: 50px;">
                                                        <?php echo esc_html(strtoupper(substr($data['candidate_name'], 0, 1))); ?>
                                                    </div>
                                                </td>
                                                <td valign="top">
                                                    <h3 style="margin: 0 0 4px 0; color: #18181b; font-size: 18px;">
                                                        <?php echo esc_html($data['candidate_name']); ?>
                                                    </h3>
                                                    <p style="margin: 0 0 8px 0; color: #6b7280; font-size: 14px;">
                                                        <?php echo esc_html($data['candidate_email']); ?>
                                                    </p>
                                                    <?php if ($data['highlights']): ?>
                                                    <p style="margin: 0; color: #374151; font-size: 13px;">
                                                        <?php echo esc_html($data['highlights']); ?>
                                                    </p>
                                                    <?php endif; ?>
                                                </td>
                                                <?php if ($data['match_score']): ?>
                                                <td width="80" align="right" valign="top">
                                                    <div style="background-color: <?php echo esc_attr($score_color); ?>; color: #ffffff; padding: 8px 12px; border-radius: 20px; font-size: 14px; font-weight: 600;">
                                                        <?php echo esc_html($data['match_score']); ?>%
                                                    </div>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Position -->
                            <p style="margin: 0 0 20px 0; color: #6b7280; font-size: 14px;">
                                For: <strong style="color: #18181b;"><?php echo esc_html($data['job_title']); ?></strong>
                                <?php if ($data['job_company']): ?>
                                    at <?php echo esc_html($data['job_company']); ?>
                                <?php endif; ?>
                            </p>

                            <!-- Pitch -->
                            <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 16px; margin-bottom: 24px;">
                                <p style="margin: 0; color: #374151; white-space: pre-line;">
                                    <?php echo esc_html($data['pitch']); ?>
                                </p>
                            </div>

                            <?php if (!empty($data['candidate_message'])): ?>
                            <!-- Candidate's Message -->
                            <h4 style="margin: 0 0 12px 0; color: #18181b; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">
                                From the Candidate
                            </h4>
                            <div style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 24px;">
                                <p style="margin: 0; color: #374151; font-style: italic; white-space: pre-line;">
                                    "<?php echo esc_html($data['candidate_message']); ?>"
                                </p>
                            </div>
                            <?php endif; ?>

                            <!-- CTA -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding: 16px 0;">
                                        <?php if ($data['cv_url']): ?>
                                        <a href="<?php echo esc_url($data['cv_url']); ?>" style="display: inline-block; background-color: #18181b; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: 600; margin-right: 12px;">
                                            View CV
                                        </a>
                                        <?php endif; ?>
                                        <a href="mailto:<?php echo esc_attr($data['candidate_email']); ?>?subject=Re: <?php echo esc_attr($data['job_title']); ?> Application" style="display: inline-block; background-color: #ffffff; color: #18181b; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: 600; border: 1px solid #18181b;">
                                            Contact Candidate
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 20px 32px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; color: #6b7280; font-size: 13px; text-align: center;">
                                This introduction was sent via <a href="<?php echo esc_url(home_url()); ?>" style="color: #18181b;">MENA Careers</a>
                                <br>
                                <a href="<?php echo esc_url($data['response_url']); ?>" style="color: #6b7280;">Let us know your decision</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Build plain text email for recruiter (fallback)
     */
    private function build_recruiter_email_text($data) {
        $text = "Hi {$data['recruiter_name']},\n\n";
        $text .= "CANDIDATE INTRODUCTION\n";
        $text .= str_repeat('-', 40) . "\n\n";
        $text .= "Candidate: {$data['candidate_name']}\n";
        $text .= "Email: {$data['candidate_email']}\n";
        if ($data['match_score']) {
            $text .= "Match Score: {$data['match_score']}%\n";
        }
        $text .= "\nPosition: {$data['job_title']}";
        if ($data['job_company']) {
            $text .= " at {$data['job_company']}";
        }
        $text .= "\n\n";
        $text .= str_repeat('-', 40) . "\n\n";
        $text .= "{$data['pitch']}\n\n";

        if (!empty($data['candidate_message'])) {
            $text .= str_repeat('-', 40) . "\n";
            $text .= "FROM THE CANDIDATE:\n\n";
            $text .= "\"{$data['candidate_message']}\"\n\n";
        }

        $text .= str_repeat('-', 40) . "\n";
        $text .= "To contact this candidate, reply to: {$data['candidate_email']}\n\n";
        $text .= "---\n";
        $text .= "Sent via MENA Careers\n";
        $text .= home_url() . "\n";

        return $text;
    }

    /**
     * Get response URL for recruiter to provide feedback
     */
    private function get_response_url($request_id) {
        // Generate portal URL if we have a recruiter contact
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_introduction_requests';

        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT recruiter_id FROM $table_name WHERE id = %d",
            $request_id
        ));

        if ($request && $request->recruiter_id) {
            // Get or generate portal token
            if (class_exists('SFFC_Recruiter_Portal')) {
                $token = SFFC_Recruiter_Portal::generate_token($request->recruiter_id);
                return home_url('/recruiter-portal/?recruiter_token=' . $token);
            }
        }

        // Fallback to admin
        return admin_url('admin.php?page=sffc-introductions&id=' . $request_id);
    }

    /**
     * Find or create a recruiter contact record
     *
     * @param int $job_id The job post ID
     * @return int|null The recruiter contact ID or null if not found/created
     */
    public function find_or_create_recruiter_contact($job_id) {
        global $wpdb;
        $contacts_table = $wpdb->prefix . 'sffc_recruiter_contacts';

        // Get recruiter info from job meta
        $recruiter_email = get_post_meta($job_id, 'sffc_recruiter_email', true);
        $recruiter_name = get_post_meta($job_id, 'sffc_recruiter_name', true);
        $recruiter_phone = get_post_meta($job_id, 'sffc_recruiter_phone', true);
        $recruiter_post_id = get_post_meta($job_id, '_sffc_recruiter_id', true);

        // If no email, try to get from recruiter post
        if (empty($recruiter_email) && $recruiter_post_id) {
            $recruiter_email = get_post_meta($recruiter_post_id, 'recruiter_email', true);
            $recruiter_name = $recruiter_name ?: get_the_title($recruiter_post_id);
        }

        // If still no email, try job source company email
        if (empty($recruiter_email)) {
            // Cannot create contact without email
            return null;
        }

        // Check if contact already exists by email
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $contacts_table WHERE contact_email = %s",
            $recruiter_email
        ));

        if ($existing) {
            return $existing->id;
        }

        // Get company name from job
        $company_name = get_post_meta($job_id, 'sffc_actual_company', true)
            ?: get_post_meta($job_id, 'sffc_source_name', true);

        // Create new contact
        $inserted = $wpdb->insert(
            $contacts_table,
            [
                'recruiter_post_id' => $recruiter_post_id ?: null,
                'contact_name' => $recruiter_name ?: 'Hiring Team',
                'contact_email' => $recruiter_email,
                'contact_phone' => $recruiter_phone ?: null,
                'company_name' => $company_name,
                'accepts_introductions' => 1,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%s']
        );

        if ($inserted) {
            return $wpdb->insert_id;
        }

        return null;
    }

    /**
     * Update introduction request with proper recruiter contact ID
     *
     * Called before sending introduction to ensure recruiter_id points to contacts table
     *
     * @param int $request_id The introduction request ID
     * @return int|null The recruiter contact ID or null
     */
    public function ensure_recruiter_contact($request_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_introduction_requests';

        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $request_id
        ));

        if (!$request) {
            return null;
        }

        // Get or create the recruiter contact
        $contact_id = $this->find_or_create_recruiter_contact($request->job_id);

        if ($contact_id && $contact_id != $request->recruiter_id) {
            // Update the request with the correct contact ID
            $wpdb->update(
                $table_name,
                ['recruiter_id' => $contact_id],
                ['id' => $request_id]
            );
        }

        return $contact_id;
    }
}

// Initialize
SFFC_Recruiter_Introduction_Admin::get_instance();
