<?php
/**
 * Recruiter Self-Service Portal
 *
 * Allows recruiters to view and respond to introduction requests
 * without needing a WordPress admin account.
 *
 * @package SennaCareers
 * @since 12.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Recruiter_Portal {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Token expiry in seconds (7 days)
     */
    const TOKEN_EXPIRY = 604800;

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
        // Register shortcode for portal page
        add_shortcode('sffc_recruiter_portal', [$this, 'render_portal']);

        // AJAX handlers (public - uses token auth)
        add_action('wp_ajax_sffc_recruiter_respond', [$this, 'ajax_respond_to_intro']);
        add_action('wp_ajax_nopriv_sffc_recruiter_respond', [$this, 'ajax_respond_to_intro']);

        add_action('wp_ajax_sffc_recruiter_get_intros', [$this, 'ajax_get_introductions']);
        add_action('wp_ajax_nopriv_sffc_recruiter_get_intros', [$this, 'ajax_get_introductions']);

        // Enqueue portal assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Handle token-based login from email links
        add_action('template_redirect', [$this, 'handle_token_login']);

        // Create portal page on init if it doesn't exist
        add_action('init', [$this, 'maybe_create_portal_page']);
    }

    /**
     * Create the recruiter portal page if it doesn't exist
     */
    public function maybe_create_portal_page() {
        // Only run once per request
        static $checked = false;
        if ($checked) return;
        $checked = true;

        // Check if we've already created the page
        $portal_page_id = get_option('sffc_recruiter_portal_page_id');
        if ($portal_page_id && get_post($portal_page_id)) {
            return;
        }

        // Check if a page with the shortcode already exists
        global $wpdb;
        $existing = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'page'
             AND post_status = 'publish'
             AND post_content LIKE '%[sffc_recruiter_portal]%'
             LIMIT 1"
        );

        if ($existing) {
            update_option('sffc_recruiter_portal_page_id', $existing);
            return;
        }

        // Create the page
        $page_id = wp_insert_post([
            'post_title' => 'Recruiter Portal',
            'post_name' => 'recruiter-portal',
            'post_content' => '[sffc_recruiter_portal]',
            'post_status' => 'publish',
            'post_type' => 'page',
            'comment_status' => 'closed',
            'ping_status' => 'closed'
        ]);

        if ($page_id && !is_wp_error($page_id)) {
            update_option('sffc_recruiter_portal_page_id', $page_id);
        }
    }

    /**
     * Enqueue portal assets
     */
    public function enqueue_assets() {
        global $post;

        if (!$post || !has_shortcode($post->post_content, 'sffc_recruiter_portal')) {
            return;
        }

        wp_enqueue_style(
            'sffc-recruiter-portal',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/recruiter-portal.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'sffc-recruiter-portal',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/recruiter-portal.js',
            ['jquery'],
            '1.0.0',
            true
        );

        // Get token from URL or session
        $token = $this->get_current_token();

        wp_localize_script('sffc-recruiter-portal', 'sffcRecruiterPortal', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'token' => $token,
            'nonce' => wp_create_nonce('sffc_recruiter_portal_nonce')
        ]);
    }

    /**
     * Handle token-based login from email links
     */
    public function handle_token_login() {
        if (!isset($_GET['recruiter_token'])) {
            return;
        }

        $token = sanitize_text_field($_GET['recruiter_token']);
        $validation = $this->validate_token($token);

        if ($validation['valid']) {
            // Store token in session for subsequent requests
            if (!session_id()) {
                session_start();
            }
            $_SESSION['sffc_recruiter_token'] = $token;
            $_SESSION['sffc_recruiter_id'] = $validation['recruiter_id'];

            // Redirect to clean URL (remove token from URL for security)
            $clean_url = remove_query_arg('recruiter_token');
            wp_safe_redirect($clean_url);
            exit;
        }
    }

    /**
     * Get current token from session or URL
     */
    private function get_current_token() {
        // Check URL first
        if (isset($_GET['recruiter_token'])) {
            return sanitize_text_field($_GET['recruiter_token']);
        }

        // Check session
        if (!session_id()) {
            session_start();
        }

        return $_SESSION['sffc_recruiter_token'] ?? '';
    }

    /**
     * Generate a secure access token for a recruiter
     *
     * @param int $recruiter_id Recruiter contact ID
     * @return string Token
     */
    public static function generate_token($recruiter_id) {
        $token = wp_generate_password(32, false);
        $hash = wp_hash($token);

        // Store token hash with expiry
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_recruiter_contacts';

        $wpdb->update(
            $table,
            [
                'access_token_hash' => $hash,
                'token_expires_at' => date('Y-m-d H:i:s', time() + self::TOKEN_EXPIRY)
            ],
            ['id' => $recruiter_id]
        );

        return $token;
    }

    /**
     * Validate a recruiter token
     *
     * @param string $token Token to validate
     * @return array ['valid' => bool, 'recruiter_id' => int|null]
     */
    private function validate_token($token) {
        if (empty($token)) {
            return ['valid' => false, 'recruiter_id' => null];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sffc_recruiter_contacts';

        // Find recruiter with matching token hash
        $hash = wp_hash($token);

        $recruiter = $wpdb->get_row($wpdb->prepare(
            "SELECT id, contact_name, contact_email, token_expires_at
             FROM $table
             WHERE access_token_hash = %s
             AND token_expires_at > NOW()",
            $hash
        ));

        if (!$recruiter) {
            return ['valid' => false, 'recruiter_id' => null];
        }

        return [
            'valid' => true,
            'recruiter_id' => $recruiter->id,
            'recruiter_name' => $recruiter->contact_name,
            'recruiter_email' => $recruiter->contact_email
        ];
    }

    /**
     * Get recruiter from current session/token
     */
    private function get_current_recruiter() {
        $token = $this->get_current_token();
        return $this->validate_token($token);
    }

    /**
     * Render the recruiter portal
     */
    public function render_portal($atts) {
        $recruiter = $this->get_current_recruiter();

        if (!$recruiter['valid']) {
            return $this->render_login_required();
        }

        ob_start();
        ?>
        <div class="sffc-recruiter-portal" data-recruiter-id="<?php echo esc_attr($recruiter['recruiter_id']); ?>" data-token="<?php echo esc_attr($this->get_current_token()); ?>">
            <div class="sffc-rp-header">
                <div class="sffc-rp-welcome">
                    <h1>Welcome, <?php echo esc_html($recruiter['recruiter_name']); ?></h1>
                    <p>Review candidate introductions for your open positions</p>
                </div>
                <div class="sffc-rp-stats" id="sffc-rp-stats">
                    <!-- Stats loaded via JS -->
                </div>
            </div>

            <div class="sffc-rp-filters">
                <select class="sffc-rp-filter-select">
                    <option value="all">All Introductions</option>
                    <option value="sent" selected>Awaiting Response</option>
                    <option value="accepted">Accepted</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>

            <div class="sffc-rp-content">
                <div class="sffc-rp-loading">
                    <div class="sffc-rp-spinner"></div>
                    <p>Loading introductions...</p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render login required message
     */
    private function render_login_required() {
        ob_start();
        ?>
        <div class="sffc-recruiter-portal sffc-rp-login-required">
            <div class="sffc-rp-login-card">
                <div class="sffc-rp-login-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
                    </svg>
                </div>
                <h2>Recruiter Portal</h2>
                <p>Please use the link from your email to access the portal.</p>
                <p class="sffc-rp-login-hint">
                    If you need a new access link, please contact us at
                    <a href="mailto:<?php echo esc_attr(get_option('admin_email')); ?>">
                        <?php echo esc_html(get_option('admin_email')); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX: Get introductions for recruiter
     */
    public function ajax_get_introductions() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sffc_recruiter_portal_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }

        $token = sanitize_text_field($_POST['token'] ?? '');
        $status = sanitize_text_field($_POST['status'] ?? '');

        $recruiter = $this->validate_token($token);
        if (!$recruiter['valid']) {
            wp_send_json_error(['message' => 'Invalid or expired access token']);
            return;
        }

        global $wpdb;
        $intro_table = $wpdb->prefix . 'sffc_introduction_requests';

        $sql = "SELECT * FROM $intro_table WHERE recruiter_id = %d";
        $params = [$recruiter['recruiter_id']];

        if ($status && $status !== 'all') {
            $sql .= " AND status = %s";
            $params[] = $status;
        }
        // If no filter or 'all', show all statuses (no additional WHERE clause)

        $sql .= " ORDER BY created_at DESC LIMIT 50";

        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        // Enrich with candidate and job data
        $introductions = [];
        foreach ($results as $row) {
            $user = get_user_by('id', $row['user_id']);
            $profile = get_user_meta($row['user_id'], 'sffc_career_profile', true) ?: [];

            $introductions[] = [
                'id' => $row['id'],
                'candidate' => [
                    'name' => $user ? $user->display_name : 'Unknown',
                    'email' => $user ? $user->user_email : '',
                    'headline' => $profile['professional_headline'] ?? $profile['job_title'] ?? '',
                    'experience' => $profile['years_experience'] ?? '',
                    'location' => $profile['current_location'] ?? '',
                    'avatar_url' => $user ? get_avatar_url($user->ID, ['size' => 80]) : ''
                ],
                'job' => [
                    'id' => $row['job_id'],
                    'title' => get_the_title($row['job_id']),
                    'company' => get_post_meta($row['job_id'], 'sffc_actual_company', true)
                        ?: get_post_meta($row['job_id'], 'sffc_source_name', true),
                    'url' => get_permalink($row['job_id'])
                ],
                'message' => $row['message'],
                'score' => $row['compatibility_score'],
                'status' => $row['status'],
                'created_at' => $row['created_at'],
                'cv_url' => get_user_meta($row['user_id'], 'sffc_cv_url', true)
            ];
        }

        // Get stats
        $stats = $this->get_recruiter_stats($recruiter['recruiter_id']);

        wp_send_json_success([
            'introductions' => $introductions,
            'stats' => $stats
        ]);
    }

    /**
     * Get stats for a recruiter
     */
    private function get_recruiter_stats($recruiter_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_introduction_requests';

        $awaiting = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE recruiter_id = %d AND status IN ('sent', 'awaiting_response')",
            $recruiter_id
        ));

        $accepted = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE recruiter_id = %d AND status = 'accepted'",
            $recruiter_id
        ));

        $rejected = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE recruiter_id = %d AND status = 'rejected'",
            $recruiter_id
        ));

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE recruiter_id = %d",
            $recruiter_id
        ));

        return [
            'awaiting' => intval($awaiting),
            'accepted' => intval($accepted),
            'rejected' => intval($rejected),
            'total' => intval($total)
        ];
    }

    /**
     * AJAX: Respond to an introduction
     */
    public function ajax_respond_to_intro() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sffc_recruiter_portal_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }

        $token = sanitize_text_field($_POST['token'] ?? '');
        $intro_id = intval($_POST['intro_id'] ?? 0);
        $response = sanitize_text_field($_POST['response'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $feedback = sanitize_textarea_field($_POST['feedback'] ?? '');

        // Validate token
        $recruiter = $this->validate_token($token);
        if (!$recruiter['valid']) {
            wp_send_json_error(['message' => 'Invalid or expired access token']);
            return;
        }

        // Validate response
        if (!in_array($response, ['accepted', 'rejected'])) {
            wp_send_json_error(['message' => 'Invalid response']);
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sffc_introduction_requests';

        // Verify this introduction belongs to this recruiter
        $intro = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND recruiter_id = %d",
            $intro_id,
            $recruiter['recruiter_id']
        ));

        if (!$intro) {
            wp_send_json_error(['message' => 'Introduction not found']);
            return;
        }

        // Update the introduction
        $updated = $wpdb->update(
            $table,
            [
                'status' => $response,
                'recruiter_response' => $message,
                'recruiter_feedback' => $feedback,
                'responded_at' => current_time('mysql')
            ],
            ['id' => $intro_id]
        );

        if ($updated === false) {
            wp_send_json_error(['message' => 'Failed to update']);
            return;
        }

        // Update recruiter stats
        $contacts_table = $wpdb->prefix . 'sffc_recruiter_contacts';
        $wpdb->query($wpdb->prepare(
            "UPDATE $contacts_table SET total_responses = total_responses + 1 WHERE id = %d",
            $recruiter['recruiter_id']
        ));

        // Recalculate response rate
        $this->update_recruiter_response_rate($recruiter['recruiter_id']);

        // Notify the candidate
        $this->notify_candidate_of_response($intro_id, $response, $message, $feedback);

        wp_send_json_success([
            'message' => $response === 'accepted'
                ? 'Candidate accepted! They will be notified.'
                : 'Response recorded. The candidate will be notified.',
            'new_status' => $response
        ]);
    }

    /**
     * Update recruiter response rate
     */
    private function update_recruiter_response_rate($recruiter_id) {
        global $wpdb;
        $contacts_table = $wpdb->prefix . 'sffc_recruiter_contacts';
        $intro_table = $wpdb->prefix . 'sffc_introduction_requests';

        // Calculate response rate
        $sent = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $intro_table WHERE recruiter_id = %d AND status IN ('sent', 'awaiting_response', 'accepted', 'rejected', 'expired')",
            $recruiter_id
        ));

        $responded = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $intro_table WHERE recruiter_id = %d AND status IN ('accepted', 'rejected')",
            $recruiter_id
        ));

        $rate = $sent > 0 ? round(($responded / $sent) * 100, 2) : 0;

        // Calculate average response time
        $avg_days = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(DATEDIFF(responded_at, sent_at))
             FROM $intro_table
             WHERE recruiter_id = %d
             AND responded_at IS NOT NULL
             AND sent_at IS NOT NULL",
            $recruiter_id
        ));

        $wpdb->update(
            $contacts_table,
            [
                'response_rate' => $rate,
                'avg_response_days' => $avg_days ? round($avg_days, 2) : null
            ],
            ['id' => $recruiter_id]
        );
    }

    /**
     * Notify candidate of recruiter response
     */
    private function notify_candidate_of_response($intro_id, $response, $message, $feedback) {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_introduction_requests';

        $intro = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $intro_id
        ));

        if (!$intro) {
            return;
        }

        $user = get_user_by('id', $intro->user_id);
        if (!$user) {
            return;
        }

        $job_title = get_the_title($intro->job_id);
        $job_company = get_post_meta($intro->job_id, 'sffc_actual_company', true)
            ?: get_post_meta($intro->job_id, 'sffc_source_name', true);

        if ($response === 'accepted') {
            $subject = sprintf('Great news from %s!', $job_company);
            $body = sprintf(
                "Hi %s,\n\n" .
                "Exciting news! The recruiting team at %s has accepted your introduction for the %s position.\n\n" .
                "%s\n\n" .
                "What happens next:\n" .
                "- The recruiter may reach out to you directly at %s\n" .
                "- Keep an eye on your inbox for next steps\n" .
                "- Make sure your profile and CV are up to date\n\n" .
                "Congratulations on making a great impression!\n\n" .
                "Best regards,\n" .
                "The MENA Careers Team",
                $user->display_name,
                $job_company,
                $job_title,
                $message ? "Message from the recruiter:\n\"$message\"\n" : "",
                $user->user_email
            );
        } else {
            $subject = sprintf('Update on your application to %s', $job_company);
            $body = sprintf(
                "Hi %s,\n\n" .
                "Thank you for your interest in the %s position at %s.\n\n" .
                "Unfortunately, the recruiting team has decided to move forward with other candidates at this time.\n\n" .
                "%s\n\n" .
                "Don't be discouraged - this is just one opportunity. Your profile shows strong potential, and we're confident the right match is out there.\n\n" .
                "Keep exploring:\n" .
                "- Browse more matched roles: %s\n" .
                "- Request introductions to other positions that fit your background\n\n" .
                "Best of luck with your job search!\n\n" .
                "The MENA Careers Team",
                $user->display_name,
                $job_title,
                $job_company,
                $feedback ? "Feedback from the recruiter:\n\"$feedback\"\n" : "",
                home_url('/jobs/')
            );
        }

        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        wp_mail($user->user_email, $subject, $body, $headers);

        // Mark as notified
        $wpdb->update(
            $table,
            ['candidate_notified' => 1],
            ['id' => $intro_id]
        );

        // Fire action for external hooks
        do_action('sffc_recruiter_responded', $intro_id, $response, $intro->user_id);
    }

    /**
     * Send portal access email to recruiter
     *
     * @param int $recruiter_id Recruiter contact ID
     * @param array $pending_intros Optional array of pending introductions to highlight
     * @return bool Success
     */
    public static function send_portal_access_email($recruiter_id, $pending_intros = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_recruiter_contacts';

        $recruiter = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $recruiter_id
        ));

        if (!$recruiter || empty($recruiter->contact_email)) {
            return false;
        }

        // Generate new token
        $token = self::generate_token($recruiter_id);

        // Build portal URL
        $portal_url = home_url('/recruiter-portal/?recruiter_token=' . $token);

        $intro_count = count($pending_intros);
        $intro_text = '';

        if ($intro_count > 0) {
            $intro_text = "\n\nYou have $intro_count candidate introduction(s) waiting for your review:\n\n";
            foreach (array_slice($pending_intros, 0, 5) as $intro) {
                $intro_text .= sprintf(
                    "- %s for %s (%d%% match)\n",
                    $intro['candidate_name'],
                    $intro['job_title'],
                    $intro['score']
                );
            }
            if ($intro_count > 5) {
                $intro_text .= sprintf("... and %d more\n", $intro_count - 5);
            }
        }

        $subject = $intro_count > 0
            ? sprintf('[Action Required] %d candidate introduction%s awaiting your review', $intro_count, $intro_count > 1 ? 's' : '')
            : 'Access Your MENA Careers Recruiter Portal';

        $body = sprintf(
            "Hi %s,\n\n" .
            "You have access to the MENA Careers Recruiter Portal where you can review and respond to candidate introductions." .
            "%s\n\n" .
            "Access your portal here:\n%s\n\n" .
            "This link is valid for 7 days and is unique to you - please don't share it.\n\n" .
            "What you can do in the portal:\n" .
            "- Review candidate profiles and match scores\n" .
            "- Accept candidates you'd like to interview\n" .
            "- Decline with optional feedback\n" .
            "- View candidate CVs and messages\n\n" .
            "If you have any questions, reply to this email.\n\n" .
            "Best regards,\n" .
            "The MENA Careers Team",
            $recruiter->contact_name,
            $intro_text,
            $portal_url
        );

        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        return wp_mail($recruiter->contact_email, $subject, $body, $headers);
    }
}

// Initialize
SFFC_Recruiter_Portal::get_instance();
