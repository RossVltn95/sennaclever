<?php
/**
 * Candidate Conversations Admin
 *
 * Admin interface for managing candidate conversations and messages
 *
 * @package SennaFinanceCareer
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Candidate_Conversations_Admin {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Add meta boxes
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_sffc_candidate_conv', [$this, 'save_meta_boxes'], 10, 2);

        // Add columns to admin list
        add_filter('manage_sffc_candidate_conv_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_sffc_candidate_conv_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);

        // Filter by candidate
        add_action('restrict_manage_posts', [$this, 'add_candidate_filter']);
        add_filter('parse_query', [$this, 'filter_by_candidate']);

        // AJAX handler for sending admin messages
        add_action('wp_ajax_sffc_admin_send_message', [$this, 'ajax_send_message']);

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        global $post_type;

        if ($hook === 'post.php' && $post_type === 'sffc_candidate_conv') {
            wp_enqueue_script(
                'sffc-conversation-admin',
                SFFC_PLUGIN_URL . 'admin/js/conversation-admin.js',
                ['jquery'],
                SFFC_VERSION,
                true
            );

            wp_localize_script('sffc-conversation-admin', 'sffcConvAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sffc_admin_send_message')
            ]);
        }
    }

    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'sffc_conv_candidate',
            'Candidate Assignment',
            [$this, 'render_candidate_meta_box'],
            'sffc_candidate_conv',
            'side',
            'high'
        );

        add_meta_box(
            'sffc_conv_contact',
            'Contact Information',
            [$this, 'render_contact_meta_box'],
            'sffc_candidate_conv',
            'normal',
            'high'
        );

        add_meta_box(
            'sffc_conv_messages',
            'Conversation Messages',
            [$this, 'render_messages_meta_box'],
            'sffc_candidate_conv',
            'normal',
            'default'
        );
    }

    /**
     * Render candidate assignment meta box
     */
    public function render_candidate_meta_box($post) {
        wp_nonce_field('sffc_conv_meta', 'sffc_conv_meta_nonce');

        $candidate_user_id = get_post_meta($post->ID, '_candidate_user_id', true);
        $is_starred = get_post_meta($post->ID, '_is_starred', true);
        $is_unread = get_post_meta($post->ID, '_is_unread', true);

        $users = get_users([
            'orderby' => 'display_name',
            'order' => 'ASC'
        ]);
        ?>
        <p>
            <label for="sffc_candidate_user_id"><strong>Select Candidate:</strong></label><br>
            <select name="sffc_candidate_user_id" id="sffc_candidate_user_id" style="width: 100%;">
                <option value="">-- Select Candidate --</option>
                <?php foreach ($users as $user) : ?>
                    <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($candidate_user_id, $user->ID); ?>>
                        <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label>
                <input type="checkbox" name="sffc_is_starred" value="1" <?php checked($is_starred, '1'); ?>>
                Starred
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="sffc_is_unread" value="1" <?php checked($is_unread, '1'); ?>>
                Has Unread Messages
            </label>
        </p>
        <?php
    }

    /**
     * Render contact information meta box
     */
    public function render_contact_meta_box($post) {
        $contact_name = get_post_meta($post->ID, '_contact_name', true);
        $contact_title = get_post_meta($post->ID, '_contact_title', true);
        $contact_company = get_post_meta($post->ID, '_contact_company', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="sffc_contact_name">Contact Name</label></th>
                <td><input type="text" name="sffc_contact_name" id="sffc_contact_name" value="<?php echo esc_attr($contact_name); ?>" class="large-text" placeholder="e.g., Sarah Chen"></td>
            </tr>
            <tr>
                <th><label for="sffc_contact_title">Contact Title</label></th>
                <td><input type="text" name="sffc_contact_title" id="sffc_contact_title" value="<?php echo esc_attr($contact_title); ?>" class="large-text" placeholder="e.g., Talent Acquisition Manager"></td>
            </tr>
            <tr>
                <th><label for="sffc_contact_company">Contact Company</label></th>
                <td><input type="text" name="sffc_contact_company" id="sffc_contact_company" value="<?php echo esc_attr($contact_company); ?>" class="large-text" placeholder="e.g., Goldman Sachs"></td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render messages meta box
     */
    public function render_messages_meta_box($post) {
        // Get existing messages
        $messages = [];
        if (class_exists('SFFC_Candidate_Conversations_Database')) {
            $messages = SFFC_Candidate_Conversations_Database::get_messages($post->ID);
        }
        ?>
        <style>
            .sffc-messages-container {
                max-height: 400px;
                overflow-y: auto;
                border: 1px solid #ddd;
                padding: 15px;
                background: #f9f9f9;
                margin-bottom: 15px;
            }
            .sffc-message {
                margin-bottom: 15px;
                padding: 10px 15px;
                border-radius: 8px;
                max-width: 80%;
            }
            .sffc-message--admin {
                background: #0073aa;
                color: white;
                margin-left: auto;
            }
            .sffc-message--candidate {
                background: white;
                border: 1px solid #ddd;
            }
            .sffc-message-meta {
                font-size: 11px;
                opacity: 0.7;
                margin-top: 5px;
            }
            .sffc-new-message-form {
                background: #fff;
                padding: 15px;
                border: 1px solid #ddd;
            }
            .sffc-new-message-form textarea {
                width: 100%;
                min-height: 80px;
            }
            .sffc-no-messages {
                color: #666;
                text-align: center;
                padding: 20px;
            }
        </style>

        <div class="sffc-messages-container" id="sffc-messages-container">
            <?php if (empty($messages)) : ?>
                <p class="sffc-no-messages">No messages in this conversation yet.</p>
            <?php else : ?>
                <?php foreach ($messages as $msg) : ?>
                    <div class="sffc-message sffc-message--<?php echo $msg->sender_type === 'admin' ? 'admin' : 'candidate'; ?>">
                        <div class="sffc-message-text"><?php echo esc_html($msg->message_text); ?></div>
                        <div class="sffc-message-meta">
                            <?php echo $msg->sender_type === 'admin' ? 'Admin' : 'Candidate'; ?> &bull;
                            <?php echo esc_html(date('M j, Y g:i a', strtotime($msg->sent_at))); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($post->post_status === 'publish') : ?>
            <div class="sffc-new-message-form">
                <p><strong>Send New Message (as Admin):</strong></p>
                <textarea id="sffc_new_message" placeholder="Type your message to the candidate..."></textarea>
                <p>
                    <button type="button" class="button button-primary" id="sffc_send_message" data-post-id="<?php echo $post->ID; ?>">
                        Send Message
                    </button>
                    <span id="sffc_message_status" style="margin-left: 10px;"></span>
                </p>
            </div>
        <?php else : ?>
            <p><em>Save this conversation as Published to send messages.</em></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Save meta box data
     */
    public function save_meta_boxes($post_id, $post) {
        // Verify nonce
        if (!isset($_POST['sffc_conv_meta_nonce']) || !wp_verify_nonce($_POST['sffc_conv_meta_nonce'], 'sffc_conv_meta')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save candidate assignment
        if (isset($_POST['sffc_candidate_user_id'])) {
            update_post_meta($post_id, '_candidate_user_id', intval($_POST['sffc_candidate_user_id']));
        }

        // Save flags
        update_post_meta($post_id, '_is_starred', isset($_POST['sffc_is_starred']) ? '1' : '0');
        update_post_meta($post_id, '_is_unread', isset($_POST['sffc_is_unread']) ? '1' : '0');

        // Save contact info
        $contact_fields = ['contact_name', 'contact_title', 'contact_company'];
        foreach ($contact_fields as $field) {
            if (isset($_POST['sffc_' . $field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST['sffc_' . $field]));
            }
        }
    }

    /**
     * AJAX handler for sending admin messages
     */
    public function ajax_send_message() {
        check_ajax_referer('sffc_admin_send_message', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';

        if (!$post_id || !$message) {
            wp_send_json_error('Missing required fields');
        }

        // Verify post exists and is a conversation
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'sffc_candidate_conv') {
            wp_send_json_error('Invalid conversation');
        }

        // Add message to database
        if (class_exists('SFFC_Candidate_Conversations_Database')) {
            $message_id = SFFC_Candidate_Conversations_Database::add_message(
                $post_id,
                'admin',
                get_current_user_id(),
                $message
            );

            if ($message_id) {
                wp_send_json_success([
                    'message_id' => $message_id,
                    'time' => current_time('M j, Y g:i a')
                ]);
            }
        }

        wp_send_json_error('Failed to send message');
    }

    /**
     * Add custom admin columns
     */
    public function add_admin_columns($columns) {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            if ($key === 'date') {
                $new_columns['candidate'] = 'Candidate';
                $new_columns['contact'] = 'Contact';
                $new_columns['messages'] = 'Messages';
                $new_columns['status'] = 'Status';
            }
            $new_columns[$key] = $value;
        }
        return $new_columns;
    }

    /**
     * Render custom admin columns
     */
    public function render_admin_columns($column, $post_id) {
        switch ($column) {
            case 'candidate':
                $user_id = get_post_meta($post_id, '_candidate_user_id', true);
                if ($user_id) {
                    $user = get_userdata($user_id);
                    if ($user) {
                        echo esc_html($user->display_name);
                    } else {
                        echo '<em>User not found</em>';
                    }
                } else {
                    echo '<em>Not assigned</em>';
                }
                break;

            case 'contact':
                $name = get_post_meta($post_id, '_contact_name', true);
                $company = get_post_meta($post_id, '_contact_company', true);
                if ($name) {
                    echo esc_html($name);
                    if ($company) {
                        echo '<br><small>' . esc_html($company) . '</small>';
                    }
                } else {
                    echo '—';
                }
                break;

            case 'messages':
                if (class_exists('SFFC_Candidate_Conversations_Database')) {
                    $messages = SFFC_Candidate_Conversations_Database::get_messages($post_id);
                    echo count($messages);
                } else {
                    echo '—';
                }
                break;

            case 'status':
                $is_starred = get_post_meta($post_id, '_is_starred', true);
                $is_unread = get_post_meta($post_id, '_is_unread', true);
                if ($is_unread) echo '<span style="background: #d63638; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">UNREAD</span> ';
                if ($is_starred) echo '<span style="background: #f0b849; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">STARRED</span>';
                if (!$is_unread && !$is_starred) echo '—';
                break;
        }
    }

    /**
     * Add candidate filter dropdown
     */
    public function add_candidate_filter($post_type) {
        if ($post_type !== 'sffc_candidate_conv') {
            return;
        }

        global $wpdb;
        $user_ids = $wpdb->get_col("
            SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
            WHERE meta_key = '_candidate_user_id'
            AND meta_value != ''
            AND meta_value != '0'
        ");

        if (empty($user_ids)) {
            return;
        }

        $selected = isset($_GET['candidate_filter']) ? intval($_GET['candidate_filter']) : 0;
        ?>
        <select name="candidate_filter">
            <option value="">All Candidates</option>
            <?php foreach ($user_ids as $user_id) :
                $user = get_userdata($user_id);
                if (!$user) continue;
            ?>
                <option value="<?php echo esc_attr($user_id); ?>" <?php selected($selected, $user_id); ?>>
                    <?php echo esc_html($user->display_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Filter by candidate
     */
    public function filter_by_candidate($query) {
        global $pagenow;

        if (!is_admin() || $pagenow !== 'edit.php') {
            return;
        }

        if (!isset($query->query['post_type']) || $query->query['post_type'] !== 'sffc_candidate_conv') {
            return;
        }

        if (isset($_GET['candidate_filter']) && !empty($_GET['candidate_filter'])) {
            $query->query_vars['meta_query'] = [
                [
                    'key' => '_candidate_user_id',
                    'value' => intval($_GET['candidate_filter']),
                    'compare' => '='
                ]
            ];
        }
    }
}

// Initialize
SFFC_Candidate_Conversations_Admin::get_instance();
