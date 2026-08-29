<?php
/**
 * Expert Management System
 * 
 * Handles expert registration, approval, profiles, and booking management
 * 
 * @package SFFC_Expert_Mode
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Expert_Management {
    
    private static $instance = null;
    private $table_name;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'expert_profiles';
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Admin hooks
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'handle_admin_actions']);
        
        // AJAX handlers
        add_action('wp_ajax_approve_expert', [$this, 'ajax_approve_expert']);
        add_action('wp_ajax_reject_expert', [$this, 'ajax_reject_expert']);
        add_action('wp_ajax_update_expert_profile', [$this, 'ajax_update_expert_profile']);
        add_action('wp_ajax_get_expert_availability', [$this, 'ajax_get_expert_availability']);
        add_action('wp_ajax_search_approved_experts', [$this, 'ajax_search_approved_experts']);
        
        // Public expert application
        add_action('wp_ajax_apply_as_expert', [$this, 'ajax_apply_as_expert']);
        
        // Shortcode for expert application form
        add_shortcode('expert_application_form', [$this, 'render_application_form']);
    }
    
    /**
     * Create database tables for expert profiles
     */
    private function maybe_create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            status enum('pending','approved','rejected','suspended') DEFAULT 'pending',
            expertise_areas text,
            bio text,
            hourly_rate decimal(10,2) DEFAULT 0.00,
            currency varchar(3) DEFAULT 'USD',
            experience_years int(11) DEFAULT 0,
            certifications text,
            languages text,
            timezone varchar(50),
            availability_schedule text,
            booking_url varchar(255),
            calendly_url varchar(255),
            linkedin_url varchar(255),
            company varchar(255),
            job_title varchar(255),
            industries text,
            consultation_types text,
            rating decimal(3,2) DEFAULT 0.00,
            total_consultations int(11) DEFAULT 0,
            total_earnings decimal(10,2) DEFAULT 0.00,
            profile_views int(11) DEFAULT 0,
            featured tinyint(1) DEFAULT 0,
            admin_notes text,
            approved_by bigint(20),
            approved_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY featured (featured)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Create expert availability table
        $availability_table = $wpdb->prefix . 'expert_availability';
        $sql = "CREATE TABLE IF NOT EXISTS {$availability_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            expert_id bigint(20) NOT NULL,
            day_of_week tinyint(1) NOT NULL COMMENT '0=Sunday, 6=Saturday',
            start_time time NOT NULL,
            end_time time NOT NULL,
            is_available tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY expert_id (expert_id),
            KEY day_of_week (day_of_week)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Create booking sessions table
        $bookings_table = $wpdb->prefix . 'expert_bookings';
        $sql = "CREATE TABLE IF NOT EXISTS {$bookings_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            expert_id bigint(20) NOT NULL,
            client_id bigint(20) NOT NULL,
            conversation_id bigint(20),
            booking_date date NOT NULL,
            booking_time time NOT NULL,
            duration_minutes int(11) DEFAULT 60,
            status enum('pending','confirmed','completed','cancelled','no-show') DEFAULT 'pending',
            meeting_url varchar(500),
            payment_status enum('pending','paid','refunded') DEFAULT 'pending',
            payment_amount decimal(10,2) DEFAULT 0.00,
            payment_transaction_id varchar(255),
            client_notes text,
            expert_notes text,
            rating int(11),
            review text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY expert_id (expert_id),
            KEY client_id (client_id),
            KEY booking_date (booking_date),
            KEY status (status)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        add_menu_page(
            'Expert Management',
            'Experts',
            'manage_options',
            'expert-management',
            [$this, 'render_admin_page'],
            'dashicons-businessperson',
            30
        );
        
        add_submenu_page(
            'expert-management',
            'All Experts',
            'All Experts',
            'manage_options',
            'expert-management',
            [$this, 'render_admin_page']
        );
        
        add_submenu_page(
            'expert-management',
            'Pending Approval',
            'Pending Approval',
            'manage_options',
            'expert-pending',
            [$this, 'render_pending_page']
        );
        
        add_submenu_page(
            'expert-management',
            'Add Expert',
            'Add Expert',
            'manage_options',
            'expert-add',
            [$this, 'render_add_expert_page']
        );
        
        add_submenu_page(
            'expert-management',
            'Bookings',
            'Bookings',
            'manage_options',
            'expert-bookings',
            [$this, 'render_bookings_page']
        );
    }
    
    /**
     * Render main admin page
     */
    public function render_admin_page() {
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        $experts = $this->get_experts($status_filter);
        ?>
        <div class="wrap">
            <h1>Expert Management</h1>
            
            <ul class="subsubsub">
                <li><a href="?page=expert-management" <?php echo $status_filter === 'all' ? 'class="current"' : ''; ?>>All <span class="count">(<?php echo $this->count_experts('all'); ?>)</span></a> |</li>
                <li><a href="?page=expert-management&status=approved" <?php echo $status_filter === 'approved' ? 'class="current"' : ''; ?>>Approved <span class="count">(<?php echo $this->count_experts('approved'); ?>)</span></a> |</li>
                <li><a href="?page=expert-management&status=pending" <?php echo $status_filter === 'pending' ? 'class="current"' : ''; ?>>Pending <span class="count">(<?php echo $this->count_experts('pending'); ?>)</span></a> |</li>
                <li><a href="?page=expert-management&status=rejected" <?php echo $status_filter === 'rejected' ? 'class="current"' : ''; ?>>Rejected <span class="count">(<?php echo $this->count_experts('rejected'); ?>)</span></a></li>
            </ul>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Expert</th>
                        <th>Expertise</th>
                        <th>Rate</th>
                        <th>Status</th>
                        <th>Consultations</th>
                        <th>Rating</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($experts as $expert): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html(get_userdata($expert->user_id)->display_name); ?></strong><br>
                                <span class="description"><?php echo esc_html($expert->job_title); ?> at <?php echo esc_html($expert->company); ?></span>
                            </td>
                            <td><?php echo esc_html($this->format_expertise_areas($expert->expertise_areas)); ?></td>
                            <td><?php echo esc_html($expert->currency . ' ' . number_format($expert->hourly_rate, 2)); ?>/hr</td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($expert->status); ?>">
                                    <?php echo ucfirst($expert->status); ?>
                                </span>
                                <?php if ($expert->featured): ?>
                                    <span class="featured-badge">Featured</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo intval($expert->total_consultations); ?></td>
                            <td>
                                <?php if ($expert->rating > 0): ?>
                                    ⭐ <?php echo number_format($expert->rating, 1); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?page=expert-add&expert_id=<?php echo $expert->id; ?>" class="button button-small">Edit</a>
                                <?php if ($expert->status === 'pending'): ?>
                                    <button class="button button-primary button-small approve-expert" data-expert-id="<?php echo $expert->id; ?>">Approve</button>
                                    <button class="button button-secondary button-small reject-expert" data-expert-id="<?php echo $expert->id; ?>">Reject</button>
                                <?php elseif ($expert->status === 'approved'): ?>
                                    <button class="button button-secondary button-small suspend-expert" data-expert-id="<?php echo $expert->id; ?>">Suspend</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($experts)): ?>
                        <tr>
                            <td colspan="7">No experts found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <style>
            .status-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 500;
            }
            .status-approved { background: #d4edda; color: #155724; }
            .status-pending { background: #fff3cd; color: #856404; }
            .status-rejected { background: #f8d7da; color: #721c24; }
            .status-suspended { background: #e2e3e5; color: #383d41; }
            .featured-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                background: #ffd700;
                color: #333;
                font-size: 11px;
                font-weight: 600;
                margin-left: 5px;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.approve-expert').on('click', function() {
                var expertId = $(this).data('expert-id');
                if (confirm('Approve this expert?')) {
                    $.post(ajaxurl, {
                        action: 'approve_expert',
                        expert_id: expertId,
                        _wpnonce: '<?php echo wp_create_nonce('expert_management'); ?>'
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    });
                }
            });
            
            $('.reject-expert').on('click', function() {
                var expertId = $(this).data('expert-id');
                var reason = prompt('Rejection reason (optional):');
                if (reason !== null) {
                    $.post(ajaxurl, {
                        action: 'reject_expert',
                        expert_id: expertId,
                        reason: reason,
                        _wpnonce: '<?php echo wp_create_nonce('expert_management'); ?>'
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render add/edit expert page
     */
    public function render_add_expert_page() {
        $expert_id = isset($_GET['expert_id']) ? intval($_GET['expert_id']) : 0;
        $expert = $expert_id ? $this->get_expert_by_id($expert_id) : null;
        $users = get_users(['orderby' => 'display_name']);
        ?>
        <div class="wrap">
            <h1><?php echo $expert ? 'Edit Expert' : 'Add Expert'; ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('save_expert', 'expert_nonce'); ?>
                <input type="hidden" name="action" value="save_expert">
                <?php if ($expert): ?>
                    <input type="hidden" name="expert_id" value="<?php echo $expert->id; ?>">
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">User</th>
                        <td>
                            <?php if ($expert): ?>
                                <strong><?php echo esc_html(get_userdata($expert->user_id)->display_name); ?></strong>
                                <input type="hidden" name="user_id" value="<?php echo $expert->user_id; ?>">
                            <?php else: ?>
                                <select name="user_id" required>
                                    <option value="">Select User</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user->ID; ?>"><?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Status</th>
                        <td>
                            <select name="status">
                                <option value="pending" <?php selected($expert->status ?? '', 'pending'); ?>>Pending</option>
                                <option value="approved" <?php selected($expert->status ?? '', 'approved'); ?>>Approved</option>
                                <option value="rejected" <?php selected($expert->status ?? '', 'rejected'); ?>>Rejected</option>
                                <option value="suspended" <?php selected($expert->status ?? '', 'suspended'); ?>>Suspended</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Job Title</th>
                        <td>
                            <input type="text" name="job_title" value="<?php echo esc_attr($expert->job_title ?? ''); ?>" class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Company</th>
                        <td>
                            <input type="text" name="company" value="<?php echo esc_attr($expert->company ?? ''); ?>" class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Expertise Areas</th>
                        <td>
                            <textarea name="expertise_areas" rows="3" class="large-text"><?php echo esc_textarea($expert->expertise_areas ?? ''); ?></textarea>
                            <p class="description">Comma-separated list of expertise areas (e.g., Private Equity, M&A, Investment Banking)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Bio</th>
                        <td>
                            <textarea name="bio" rows="5" class="large-text"><?php echo esc_textarea($expert->bio ?? ''); ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Hourly Rate</th>
                        <td>
                            <input type="number" name="hourly_rate" value="<?php echo esc_attr($expert->hourly_rate ?? '150'); ?>" step="0.01" min="0" style="width: 100px;">
                            <select name="currency">
                                <option value="USD" <?php selected($expert->currency ?? 'USD', 'USD'); ?>>USD</option>
                                <option value="EUR" <?php selected($expert->currency ?? '', 'EUR'); ?>>EUR</option>
                                <option value="GBP" <?php selected($expert->currency ?? '', 'GBP'); ?>>GBP</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Years of Experience</th>
                        <td>
                            <input type="number" name="experience_years" value="<?php echo esc_attr($expert->experience_years ?? ''); ?>" min="0" style="width: 100px;">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Industries</th>
                        <td>
                            <input type="text" name="industries" value="<?php echo esc_attr($expert->industries ?? ''); ?>" class="large-text">
                            <p class="description">Comma-separated list of industries</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Calendly URL</th>
                        <td>
                            <input type="url" name="calendly_url" value="<?php echo esc_attr($expert->calendly_url ?? ''); ?>" class="large-text">
                            <p class="description">Expert's Calendly booking URL (optional)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">LinkedIn URL</th>
                        <td>
                            <input type="url" name="linkedin_url" value="<?php echo esc_attr($expert->linkedin_url ?? ''); ?>" class="large-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Languages</th>
                        <td>
                            <input type="text" name="languages" value="<?php echo esc_attr($expert->languages ?? 'English'); ?>" class="regular-text">
                            <p class="description">Comma-separated list of languages</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Timezone</th>
                        <td>
                            <select name="timezone">
                                <?php echo wp_timezone_choice($expert->timezone ?? wp_timezone_string()); ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Featured Expert</th>
                        <td>
                            <label>
                                <input type="checkbox" name="featured" value="1" <?php checked($expert->featured ?? 0, 1); ?>>
                                Feature this expert in listings
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Admin Notes</th>
                        <td>
                            <textarea name="admin_notes" rows="3" class="large-text"><?php echo esc_textarea($expert->admin_notes ?? ''); ?></textarea>
                            <p class="description">Internal notes (not visible to users)</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button($expert ? 'Update Expert' : 'Add Expert'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Handle admin form submissions
     */
    public function handle_admin_actions() {
        if (!isset($_POST['action']) || $_POST['action'] !== 'save_expert') {
            return;
        }
        
        if (!wp_verify_nonce($_POST['expert_nonce'], 'save_expert')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        global $wpdb;
        
        $data = [
            'user_id' => intval($_POST['user_id']),
            'status' => sanitize_text_field($_POST['status']),
            'job_title' => sanitize_text_field($_POST['job_title']),
            'company' => sanitize_text_field($_POST['company']),
            'expertise_areas' => sanitize_text_field($_POST['expertise_areas']),
            'bio' => sanitize_textarea_field($_POST['bio']),
            'hourly_rate' => floatval($_POST['hourly_rate']),
            'currency' => sanitize_text_field($_POST['currency']),
            'experience_years' => intval($_POST['experience_years']),
            'industries' => sanitize_text_field($_POST['industries']),
            'calendly_url' => esc_url_raw($_POST['calendly_url']),
            'linkedin_url' => esc_url_raw($_POST['linkedin_url']),
            'languages' => sanitize_text_field($_POST['languages']),
            'timezone' => sanitize_text_field($_POST['timezone']),
            'featured' => isset($_POST['featured']) ? 1 : 0,
            'admin_notes' => sanitize_textarea_field($_POST['admin_notes'])
        ];
        
        if ($_POST['status'] === 'approved' && (!isset($_POST['expert_id']) || !$this->get_expert_by_id($_POST['expert_id'])->approved_by)) {
            $data['approved_by'] = get_current_user_id();
            $data['approved_at'] = current_time('mysql');
        }
        
        if (isset($_POST['expert_id'])) {
            // Update existing expert
            $wpdb->update($this->table_name, $data, ['id' => intval($_POST['expert_id'])]);
            $message = 'Expert updated successfully.';
        } else {
            // Add new expert
            $wpdb->insert($this->table_name, $data);
            $message = 'Expert added successfully.';
            
            // Grant expert_mode_access to the user
            update_user_meta($data['user_id'], 'expert_mode_access', '1');
            update_user_meta($data['user_id'], 'is_expert', '1');
        }
        
        // Redirect with success message
        wp_redirect(admin_url('admin.php?page=expert-management&message=' . urlencode($message)));
        exit;
    }
    
    /**
     * AJAX: Approve expert
     */
    public function ajax_approve_expert() {
        check_ajax_referer('expert_management', '_wpnonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $expert_id = intval($_POST['expert_id']);
        
        global $wpdb;
        $result = $wpdb->update(
            $this->table_name,
            [
                'status' => 'approved',
                'approved_by' => get_current_user_id(),
                'approved_at' => current_time('mysql')
            ],
            ['id' => $expert_id]
        );
        
        if ($result !== false) {
            // Get expert user_id and grant access
            $expert = $this->get_expert_by_id($expert_id);
            update_user_meta($expert->user_id, 'expert_mode_access', '1');
            update_user_meta($expert->user_id, 'is_expert', '1');
            
            // Send approval email
            $this->send_approval_email($expert->user_id);
            
            wp_send_json_success(['message' => 'Expert approved successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to approve expert']);
        }
    }
    
    /**
     * AJAX: Search approved experts
     */
    public function ajax_search_approved_experts() {
        $search = sanitize_text_field($_POST['search'] ?? '');
        $expertise = sanitize_text_field($_POST['expertise'] ?? '');
        $limit = intval($_POST['limit'] ?? 20);
        $offset = intval($_POST['offset'] ?? 0);
        
        global $wpdb;
        
        $where_clauses = ["e.status = 'approved'"];
        $prepare_values = [];
        
        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = "(u.display_name LIKE %s OR e.bio LIKE %s OR e.expertise_areas LIKE %s OR e.copany LIKE %s)";
            $prepare_values[] = $search_like;
            $prepare_values[] = $search_like;
            $prepare_values[] = $search_like;
            $prepare_values[] = $search_like;
        }
        
        if (!empty($expertise)) {
            $expertise_like = '%' . $wpdb->esc_like($expertise) . '%';
            $where_clauses[] = "e.expertise_areas LIKE %s";
            $prepare_values[] = $expertise_like;
        }
        
        $where_clause = implode(' AND ', $where_clauses);
        $prepare_values[] = $limit;
        $prepare_values[] = $offset;
        
        $query = "
            SELECT e.*, u.display_name, u.user_email
            FROM {$this->table_name} e
            JOIN {$wpdb->users} u ON e.user_id = u.ID
            WHERE {$where_clause}
            ORDER BY e.featured DESC, e.rating DESC, e.total_consultations DESC
            LIMIT %d OFFSET %d
        ";
        
        $experts = $wpdb->get_results($wpdb->prepare($query, ...$prepare_values));
        
        // Add avatar URLs and format data
        foreach ($experts as $expert) {
            $expert->avatar_url = get_avatar_url($expert->user_id, 60);
            $expert->expertise_list = array_map('trim', explode(',', $expert->expertise_areas));
            $expert->industries_list = array_map('trim', explode(',', $expert->industries));
            $expert->languages_list = array_map('trim', explode(',', $expert->languages));
        }
        
        wp_send_json_success(['experts' => $experts]);
    }
    
    /**
     * Get experts by status
     */
    private function get_experts($status = 'all') {
        global $wpdb;
        
        $where = $status === 'all' ? '1=1' : "status = %s";
        $query = "SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY created_at DESC";
        
        if ($status === 'all') {
            return $wpdb->get_results($query);
        } else {
            return $wpdb->get_results($wpdb->prepare($query, $status));
        }
    }
    
    /**
     * Get expert by ID
     */
    private function get_expert_by_id($expert_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $expert_id));
    }
    
    /**
     * Get expert by user ID
     */
    public function get_expert_by_user_id($user_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE user_id = %d", $user_id));
    }
    
    /**
     * Count experts by status
     */
    private function count_experts($status = 'all') {
        global $wpdb;
        
        if ($status === 'all') {
            return $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        } else {
            return $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s", $status));
        }
    }
    
    /**
     * Format expertise areas for display
     */
    private function format_expertise_areas($expertise) {
        $areas = array_map('trim', explode(',', $expertise));
        return implode(', ', array_slice($areas, 0, 3)) . (count($areas) > 3 ? '...' : '');
    }
    
    /**
     * Send approval email to expert
     */
    private function send_approval_email($user_id) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $subject = 'Your Expert Application Has Been Approved!';
        $message = "
        <html>
        <body>
            <h2>Congratulations, {$user->display_name}!</h2>
            <p>Your application to become an expert on our platform has been approved.</p>
            <p>You can now:</p>
            <ul>
                <li>Receive consultation requests from clients</li>
                <li>Set your availability and rates</li>
                <li>Share your expertise through Expert Mode</li>
            </ul>
            <p><a href='" . home_url('/expert-mode/') . "'>Access Expert Mode</a></p>
            <p>Best regards,<br>The Team</p>
        </body>
        </html>
        ";
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($user->user_email, $subject, $message, $headers);
    }
    
    /**
     * Render expert application form shortcode
     */
    public function render_application_form($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to apply as an expert.</p>';
        }
        
        $user_id = get_current_user_id();
        $existing = $this->get_expert_by_user_id($user_id);
        
        if ($existing) {
            if ($existing->status === 'approved') {
                return '<p>You are already an approved expert!</p>';
            } elseif ($existing->status === 'pending') {
                return '<p>Your expert application is pending review.</p>';
            }
        }
        
        ob_start();
        ?>
        <form class="expert-application-form" id="expertApplicationForm">
            <h3>Apply to Become an Expert</h3>
            
            <div class="form-group">
                <label>Job Title *</label>
                <input type="text" name="job_title" required>
            </div>
            
            <div class="form-group">
                <label>Company *</label>
                <input type="text" name="company" required>
            </div>
            
            <div class="form-group">
                <label>Areas of Expertise *</label>
                <input type="text" name="expertise_areas" placeholder="e.g., Private Equity, M&A, Investment Banking" required>
            </div>
            
            <div class="form-group">
                <label>Years of Experience *</label>
                <input type="number" name="experience_years" min="1" required>
            </div>
            
            <div class="form-group">
                <label>Bio *</label>
                <textarea name="bio" rows="4" required></textarea>
            </div>
            
            <div class="form-group">
                <label>Hourly Consultation Rate (USD)</label>
                <input type="number" name="hourly_rate" min="50" step="10" value="150">
            </div>
            
            <div class="form-group">
                <label>LinkedIn Profile</label>
                <input type="url" name="linkedin_url">
            </div>
            
            <button type="submit" class="button">Submit Application</button>
            
            <div id="applicationMessage"></div>
        </form>
        
        <script>
        jQuery('#expertApplicationForm').on('submit', function(e) {
            e.preventDefault();
            
            var formData = jQuery(this).serialize();
            jQuery('#applicationMessage').html('<p>Submitting...</p>');
            
            jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                action: 'apply_as_expert',
                ...Object.fromEntries(new FormData(this)),
                _wpnonce: '<?php echo wp_create_nonce('expert_application'); ?>'
            }, function(response) {
                if (response.success) {
                    jQuery('#applicationMessage').html('<p style="color: green;">' + response.data.message + '</p>');
                    jQuery('#expertApplicationForm')[0].reset();
                } else {
                    jQuery('#applicationMessage').html('<p style="color: red;">' + response.data.message + '</p>');
                }
            });
        });
        </script>
        
        <style>
        .expert-application-form {
            max-width: 600px;
            margin: 20px 0;
        }
        .expert-application-form .form-group {
            margin-bottom: 20px;
        }
        .expert-application-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .expert-application-form input,
        .expert-application-form textarea,
        .expert-application-form select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .expert-application-form button {
            background: #0073aa;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Apply as expert
     */
    public function ajax_apply_as_expert() {
        check_ajax_referer('expert_application', '_wpnonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Please log in to apply']);
        }
        
        $user_id = get_current_user_id();
        
        // Check if already applied
        if ($this->get_expert_by_user_id($user_id)) {
            wp_send_json_error(['message' => 'You have already applied']);
        }
        
        global $wpdb;
        
        $data = [
            'user_id' => $user_id,
            'status' => 'pending',
            'job_title' => sanitize_text_field($_POST['job_title']),
            'company' => sanitize_text_field($_POST['company']),
            'expertise_areas' => sanitize_text_field($_POST['expertise_areas']),
            'experience_years' => intval($_POST['experience_years']),
            'bio' => sanitize_textarea_field($_POST['bio']),
            'hourly_rate' => floatval($_POST['hourly_rate'] ?? 150),
            'linkedin_url' => esc_url_raw($_POST['linkedin_url'] ?? ''),
            'currency' => 'USD',
            'languages' => 'English',
            'timezone' => wp_timezone_string()
        ];
        
        $result = $wpdb->insert($this->table_name, $data);
        
        if ($result) {
            // Notify admins
            $this->notify_admins_new_application($user_id);
            
            wp_send_json_success(['message' => 'Application submitted successfully! We will review and get back to you soon.']);
        } else {
            wp_send_json_error(['message' => 'Failed to submit application']);
        }
    }
    
    /**
     * Notify admins of new expert application
     */
    private function notify_admins_new_application($user_id) {
        $user = get_userdata($user_id);
        $admin_email = get_option('admin_email');
        
        $subject = 'New Expert Application';
        $message = "A new expert application has been submitted by {$user->display_name}.\n\n";
        $message .= "Review: " . admin_url('admin.php?page=expert-pending');
        
        wp_mail($admin_email, $subject, $message);
    }
}

// Initialize
SFFC_Expert_Management::get_instance();
