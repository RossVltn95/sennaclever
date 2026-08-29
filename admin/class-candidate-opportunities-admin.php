<?php
/**
 * Candidate Opportunities Admin
 *
 * Admin interface for managing candidate opportunities
 *
 * @package SennaFinanceCareer
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Candidate_Opportunities_Admin {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Add meta boxes for opportunity editing
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_sffc_candidate_opp', [$this, 'save_meta_boxes'], 10, 2);

        // Add columns to admin list
        add_filter('manage_sffc_candidate_opp_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_sffc_candidate_opp_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);

        // Make columns sortable
        add_filter('manage_edit-sffc_candidate_opp_sortable_columns', [$this, 'sortable_columns']);

        // Filter by candidate
        add_action('restrict_manage_posts', [$this, 'add_candidate_filter']);
        add_filter('parse_query', [$this, 'filter_by_candidate']);
    }

    /**
     * Add meta boxes for opportunity editing
     */
    public function add_meta_boxes() {
        add_meta_box(
            'sffc_opp_candidate',
            'Candidate Assignment',
            [$this, 'render_candidate_meta_box'],
            'sffc_candidate_opp',
            'side',
            'high'
        );

        add_meta_box(
            'sffc_opp_details',
            'Opportunity Details',
            [$this, 'render_details_meta_box'],
            'sffc_candidate_opp',
            'normal',
            'high'
        );

        add_meta_box(
            'sffc_opp_recruiter',
            'Recruiter Information',
            [$this, 'render_recruiter_meta_box'],
            'sffc_candidate_opp',
            'normal',
            'default'
        );

        add_meta_box(
            'sffc_opp_match',
            'Match Reasons',
            [$this, 'render_match_meta_box'],
            'sffc_candidate_opp',
            'normal',
            'default'
        );
    }

    /**
     * Render candidate assignment meta box
     */
    public function render_candidate_meta_box($post) {
        wp_nonce_field('sffc_opp_meta', 'sffc_opp_meta_nonce');

        $candidate_user_id = get_post_meta($post->ID, '_candidate_user_id', true);
        $is_saved = get_post_meta($post->ID, '_is_saved', true);
        $is_new = get_post_meta($post->ID, '_is_new', true);

        // Get all users who could be candidates (subscribers, or custom role)
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
                <input type="checkbox" name="sffc_is_new" value="1" <?php checked($is_new, '1'); ?>>
                Mark as New
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="sffc_is_saved" value="1" <?php checked($is_saved, '1'); ?>>
                Saved by Candidate
            </label>
        </p>
        <?php
    }

    /**
     * Render opportunity details meta box
     */
    public function render_details_meta_box($post) {
        $company = get_post_meta($post->ID, '_company', true);
        $location = get_post_meta($post->ID, '_location', true);
        $salary = get_post_meta($post->ID, '_salary', true);
        $source = get_post_meta($post->ID, '_source', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="sffc_company">Company</label></th>
                <td><input type="text" name="sffc_company" id="sffc_company" value="<?php echo esc_attr($company); ?>" class="large-text"></td>
            </tr>
            <tr>
                <th><label for="sffc_location">Location</label></th>
                <td><input type="text" name="sffc_location" id="sffc_location" value="<?php echo esc_attr($location); ?>" class="large-text" placeholder="e.g., Paris, France"></td>
            </tr>
            <tr>
                <th><label for="sffc_salary">Salary Range</label></th>
                <td><input type="text" name="sffc_salary" id="sffc_salary" value="<?php echo esc_attr($salary); ?>" class="large-text" placeholder="e.g., $150,000 - $180,000"></td>
            </tr>
            <tr>
                <th><label for="sffc_source">Source</label></th>
                <td>
                    <select name="sffc_source" id="sffc_source">
                        <option value="recruiter_interest" <?php selected($source, 'recruiter_interest'); ?>>Recruiter Interest</option>
                        <option value="profile_view" <?php selected($source, 'profile_view'); ?>>Profile View</option>
                        <option value="campaign_match" <?php selected($source, 'campaign_match'); ?>>Campaign Match</option>
                        <option value="direct_application" <?php selected($source, 'direct_application'); ?>>Direct Application</option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render recruiter information meta box
     */
    public function render_recruiter_meta_box($post) {
        $recruiter_name = get_post_meta($post->ID, '_recruiter_name', true);
        $recruiter_title = get_post_meta($post->ID, '_recruiter_title', true);
        $recruiter_company = get_post_meta($post->ID, '_recruiter_company', true);
        $recruiter_status = get_post_meta($post->ID, '_recruiter_status', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="sffc_recruiter_name">Recruiter Name</label></th>
                <td><input type="text" name="sffc_recruiter_name" id="sffc_recruiter_name" value="<?php echo esc_attr($recruiter_name); ?>" class="large-text"></td>
            </tr>
            <tr>
                <th><label for="sffc_recruiter_title">Recruiter Title</label></th>
                <td><input type="text" name="sffc_recruiter_title" id="sffc_recruiter_title" value="<?php echo esc_attr($recruiter_title); ?>" class="large-text" placeholder="e.g., Talent Acquisition Manager"></td>
            </tr>
            <tr>
                <th><label for="sffc_recruiter_company">Recruiter Company</label></th>
                <td><input type="text" name="sffc_recruiter_company" id="sffc_recruiter_company" value="<?php echo esc_attr($recruiter_company); ?>" class="large-text"></td>
            </tr>
            <tr>
                <th><label for="sffc_recruiter_status">Status Message</label></th>
                <td><input type="text" name="sffc_recruiter_status" id="sffc_recruiter_status" value="<?php echo esc_attr($recruiter_status); ?>" class="large-text" placeholder="e.g., Interested in your profile"></td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render match reasons meta box
     */
    public function render_match_meta_box($post) {
        $match_reasons = get_post_meta($post->ID, '_match_reasons', true);
        if (!is_array($match_reasons)) {
            $match_reasons = [];
        }
        ?>
        <p>Add reasons why this opportunity matches the candidate's profile (one per line):</p>
        <textarea name="sffc_match_reasons" id="sffc_match_reasons" rows="5" class="large-text" placeholder="8 years experience (required: 5+)&#10;CFA certification&#10;PE background&#10;Location match"><?php echo esc_textarea(implode("\n", $match_reasons)); ?></textarea>
        <?php
    }

    /**
     * Save meta box data
     */
    public function save_meta_boxes($post_id, $post) {
        // Verify nonce
        if (!isset($_POST['sffc_opp_meta_nonce']) || !wp_verify_nonce($_POST['sffc_opp_meta_nonce'], 'sffc_opp_meta')) {
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
        update_post_meta($post_id, '_is_new', isset($_POST['sffc_is_new']) ? '1' : '0');
        update_post_meta($post_id, '_is_saved', isset($_POST['sffc_is_saved']) ? '1' : '0');

        // Save opportunity details
        $text_fields = ['company', 'location', 'salary', 'source'];
        foreach ($text_fields as $field) {
            if (isset($_POST['sffc_' . $field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST['sffc_' . $field]));
            }
        }

        // Save recruiter info
        $recruiter_fields = ['recruiter_name', 'recruiter_title', 'recruiter_company', 'recruiter_status'];
        foreach ($recruiter_fields as $field) {
            if (isset($_POST['sffc_' . $field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST['sffc_' . $field]));
            }
        }

        // Save match reasons
        if (isset($_POST['sffc_match_reasons'])) {
            $reasons = explode("\n", sanitize_textarea_field($_POST['sffc_match_reasons']));
            $reasons = array_map('trim', $reasons);
            $reasons = array_filter($reasons);
            update_post_meta($post_id, '_match_reasons', $reasons);
        }
    }

    /**
     * Add custom admin columns
     */
    public function add_admin_columns($columns) {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            if ($key === 'date') {
                $new_columns['candidate'] = 'Candidate';
                $new_columns['company'] = 'Company';
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

            case 'company':
                echo esc_html(get_post_meta($post_id, '_company', true) ?: '—');
                break;

            case 'status':
                $is_new = get_post_meta($post_id, '_is_new', true);
                $is_saved = get_post_meta($post_id, '_is_saved', true);
                if ($is_new) echo '<span style="background: #0073aa; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">NEW</span> ';
                if ($is_saved) echo '<span style="background: #46b450; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">SAVED</span>';
                if (!$is_new && !$is_saved) echo '—';
                break;
        }
    }

    /**
     * Make columns sortable
     */
    public function sortable_columns($columns) {
        $columns['candidate'] = 'candidate';
        $columns['company'] = 'company';
        return $columns;
    }

    /**
     * Add candidate filter dropdown
     */
    public function add_candidate_filter($post_type) {
        if ($post_type !== 'sffc_candidate_opp') {
            return;
        }

        // Get all users with opportunities
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

        if (!isset($query->query['post_type']) || $query->query['post_type'] !== 'sffc_candidate_opp') {
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
SFFC_Candidate_Opportunities_Admin::get_instance();
