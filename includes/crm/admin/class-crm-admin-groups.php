<?php
/**
 * CRM Admin Groups Management
 * Handles post group categorization in WordPress admin
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Admin_Groups {

    private $group_model;
    private $contact_group_model;

    public function __construct() {
        require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-post-group.php';
        require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-hr-contact-group.php';
        $this->group_model = new SFFC_CRM_Post_Group();
        $this->contact_group_model = new SFFC_CRM_HR_Contact_Group();

        add_action('admin_init', [$this, 'handle_group_actions']);
    }

    private function get_group_type() {
        $group_type = isset($_REQUEST['group_type']) ? sanitize_key(wp_unslash((string) $_REQUEST['group_type'])) : 'posts';
        return $group_type === 'hr_contacts' ? 'hr_contacts' : 'posts';
    }

    private function get_model_for_type($group_type) {
        return $group_type === 'hr_contacts' ? $this->contact_group_model : $this->group_model;
    }

    private function get_count_key_for_type($group_type) {
        return $group_type === 'hr_contacts' ? 'contact_count' : 'post_count';
    }

    private function get_admin_url($args = []) {
        return admin_url('admin.php?' . http_build_query(array_merge(['page' => 'sffc-crm-groups'], $args)));
    }

    private function render_group_tabs($group_type) {
        ?>
        <h2 class="nav-tab-wrapper" style="margin-top: 16px;">
            <a class="nav-tab <?php echo $group_type === 'posts' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($this->get_admin_url(['group_type' => 'posts'])); ?>">
                <?php esc_html_e('Post Groups', 'senna-finance'); ?>
            </a>
            <a class="nav-tab <?php echo $group_type === 'hr_contacts' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($this->get_admin_url(['group_type' => 'hr_contacts'])); ?>">
                <?php esc_html_e('HR Contact Groups', 'senna-finance'); ?>
            </a>
        </h2>
        <?php
    }

    /**
     * Render groups list page
     */
    public function render_list_page() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $group_type = $this->get_group_type();
        $model = $this->get_model_for_type($group_type);
        $count_key = $this->get_count_key_for_type($group_type);
        $count_label = $group_type === 'hr_contacts' ? __('Contacts', 'senna-finance') : __('Posts', 'senna-finance');
        $page_title = $group_type === 'hr_contacts' ? __('HR Contact Groups', 'senna-finance') : __('Post Groups', 'senna-finance');

        // Get all groups
        $groups = $model->get_all([
            'is_active' => null,
            'include_post_count' => true,
            'include_contact_count' => true
        ]);

        // Handle messages
        $message = '';
        if (isset($_GET['saved'])) {
            $message = '<div class="notice notice-success is-dismissible"><p>Group saved successfully.</p></div>';
        } elseif (isset($_GET['deleted'])) {
            $message = '<div class="notice notice-success is-dismissible"><p>Group deleted successfully.</p></div>';
        } elseif (isset($_GET['error'])) {
            $error_msg = isset($_GET['error_msg']) ? urldecode($_GET['error_msg']) : 'An error occurred.';
            $message = '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_msg) . '</p></div>';
        }

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html($page_title); ?></h1>
            <a href="<?php echo esc_url($this->get_admin_url(['group_type' => $group_type, 'action' => 'new'])); ?>" class="page-title-action">Add New Group</a>
            <hr class="wp-header-end">
            <?php $this->render_group_tabs($group_type); ?>

            <?php echo $message; ?>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 40%;">Name</th>
                        <th style="width: 20%;">Slug</th>
                        <th style="width: 10%; text-align: center;"><?php echo esc_html($count_label); ?></th>
                        <th style="width: 10%; text-align: center;">Order</th>
                        <?php if ($group_type === 'posts'): ?>
                            <th style="width: 10%; text-align: center;">Access</th>
                        <?php endif; ?>
                        <th style="width: 10%; text-align: center;">Status</th>
                        <th style="width: 10%; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($groups)): ?>
                        <tr>
                            <td colspan="<?php echo $group_type === 'posts' ? '7' : '6'; ?>" style="text-align: center; padding: 40px;">
                                <p style="font-size: 16px; color: #666;">No groups found.</p>
                                <p><a href="<?php echo esc_url($this->get_admin_url(['group_type' => $group_type, 'action' => 'new'])); ?>" class="button button-primary">Create Your First Group</a></p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($groups as $group): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($group['name']); ?></strong>
                                    <?php if ($group['description']): ?>
                                        <br><span style="color: #666; font-size: 13px;"><?php echo esc_html(wp_trim_words($group['description'], 15)); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($group['location'])): ?>
                                        <br><span style="color: #2271b1; font-size: 13px;">Location: <?php echo esc_html($group['location']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><code><?php echo esc_html($group['slug']); ?></code></td>
                                <td style="text-align: center;"><?php echo (int)($group[$count_key] ?? 0); ?></td>
                                <td style="text-align: center;"><?php echo (int)$group['display_order']; ?></td>
                                <?php if ($group_type === 'posts'): ?>
                                    <td style="text-align: center;">
                                        <?php if (!empty($group['is_premium'])): ?>
                                            <span style="display: inline-flex; align-items: center; min-height: 22px; padding: 0 8px; border-radius: 999px; background: #0a66c2; color: #fff; font-size: 11px; font-weight: 700;">Premium</span>
                                        <?php else: ?>
                                            <span style="color: #666;">Free</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td style="text-align: center;">
                                    <?php if ($group['is_active']): ?>
                                        <span style="color: #46b450; font-weight: 600;">Active</span>
                                    <?php else: ?>
                                        <span style="color: #999;">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <a href="<?php echo esc_url($this->get_admin_url(['group_type' => $group_type, 'action' => 'edit', 'id' => (int) $group['id']])); ?>" class="button button-small">Edit</a>
                                    <?php $delete_message = $group_type === 'hr_contacts' ? __('Are you sure you want to delete this group? All contact associations will be removed.', 'senna-finance') : __('Are you sure you want to delete this group? All post associations will be removed.', 'senna-finance'); ?>
                                    <a href="<?php echo esc_url(wp_nonce_url($this->get_admin_url(['group_type' => $group_type, 'action' => 'delete', 'id' => (int) $group['id']]), 'delete_group_' . $group_type . '_' . $group['id'])); ?>"
                                       class="button button-small"
                                       onclick="return confirm('<?php echo esc_js($delete_message); ?>');"
                                       style="color: #b32d2e;">Delete</a>
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
     * Render add/edit group form
     */
    public function render_form_page() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $group_type = $this->get_group_type();
        $model = $this->get_model_for_type($group_type);
        $group_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $group = null;

        if ($group_id) {
            $group = $model->get_by_id($group_id);
            if (!$group) {
                wp_die(__('Group not found.'));
            }
        }

        $is_edit = (bool)$group_id;
        $title = $is_edit
            ? ($group_type === 'hr_contacts' ? 'Edit HR Contact Group' : 'Edit Post Group')
            : ($group_type === 'hr_contacts' ? 'Add HR Contact Group' : 'Add Post Group');

        // Default values
        $name = $group['name'] ?? '';
        $slug = $group['slug'] ?? '';
        $description = $group['description'] ?? '';
        $location = $group['location'] ?? '';
        $icon = $group['icon'] ?? '';
        $display_order = $group['display_order'] ?? 0;
        $is_active = isset($group['is_active']) ? (int)$group['is_active'] : 1;
        $is_premium = isset($group['is_premium']) ? (int)$group['is_premium'] : 0;

        ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>
            <a href="<?php echo esc_url($this->get_admin_url(['group_type' => $group_type])); ?>" class="page-title-action">← Back to Groups</a>
            <hr class="wp-header-end">
            <?php $this->render_group_tabs($group_type); ?>

            <form method="post" action="<?php echo esc_url($this->get_admin_url(['group_type' => $group_type])); ?>" style="max-width: 800px;">
                <?php wp_nonce_field('save_crm_group', 'crm_group_nonce'); ?>
                <input type="hidden" name="action" value="save_group">
                <input type="hidden" name="group_type" value="<?php echo esc_attr($group_type); ?>">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="group_name">Group Name <span style="color: red;">*</span></label></th>
                        <td>
                            <input type="text"
                                   id="group_name"
                                   name="group_name"
                                   value="<?php echo esc_attr($name); ?>"
                                   class="regular-text"
                                   required>
                            <p class="description"><?php echo esc_html($group_type === 'hr_contacts' ? __('Display name for the contact group (e.g., "Dubai Finance HR Contacts")', 'senna-finance') : __('Display name for the group (e.g., "Finance Internships in France")', 'senna-finance')); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="group_slug">Slug <span style="color: red;">*</span></label></th>
                        <td>
                            <input type="text"
                                   id="group_slug"
                                   name="group_slug"
                                   value="<?php echo esc_attr($slug); ?>"
                                   class="regular-text"
                                   pattern="[a-z0-9-]+"
                                   <?php echo !$is_edit ? 'required' : ''; ?>>
                            <p class="description">URL-friendly identifier (lowercase letters, numbers, and hyphens only). Auto-generated if left blank.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="group_description">Description</label></th>
                        <td>
                            <textarea id="group_description"
                                      name="group_description"
                                      rows="4"
                                      class="large-text"><?php echo esc_textarea($description); ?></textarea>
                            <p class="description">Optional description for admin reference.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="group_location">Location</label></th>
                        <td>
                            <input type="text"
                                   id="group_location"
                                   name="group_location"
                                   value="<?php echo esc_attr($location); ?>"
                                   class="regular-text"
                                   placeholder="<?php echo esc_attr($group_type === 'hr_contacts' ? 'Dubai, UAE' : 'London, UK'); ?>">
                            <p class="description">Optional. Used to prioritize this list for users detected near this location.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="group_icon">Icon URL</label></th>
                        <td>
                            <input type="url"
                                   id="group_icon"
                                   name="group_icon"
                                   value="<?php echo esc_url($icon); ?>"
                                   class="regular-text">
                            <p class="description">Optional icon/image URL for dashboard cards.</p>
                            <?php if ($icon): ?>
                                <p><img src="<?php echo esc_url($icon); ?>" alt="" style="max-width: 100px; max-height: 100px; margin-top: 10px;"></p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="group_order">Display Order</label></th>
                        <td>
                            <input type="number"
                                   id="group_order"
                                   name="group_order"
                                   value="<?php echo esc_attr($display_order); ?>"
                                   min="0"
                                   step="1"
                                   class="small-text">
                            <p class="description">Lower numbers appear first on the dashboard (0 = first).</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="group_active">Status</label></th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       id="group_active"
                                       name="group_active"
                                       value="1"
                                       <?php checked($is_active, 1); ?>>
                                Active (visible on dashboard)
                            </label>
                        </td>
                    </tr>

                    <?php if ($group_type === 'posts'): ?>
                        <tr>
                            <th scope="row"><label for="group_premium">Premium Tracker</label></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           id="group_premium"
                                           name="group_premium"
                                           value="1"
                                           <?php checked($is_premium, 1); ?>>
                                    Premium only
                                </label>
                                <p class="description">Premium groups appear as locked Premium Trackers on the community frontend and send non-premium users to the memberships page.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" value="<?php echo $is_edit ? 'Update Group' : 'Create Group'; ?>">
                    <a href="<?php echo esc_url($this->get_admin_url(['group_type' => $group_type])); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Auto-generate slug from name if creating new group
            <?php if (!$is_edit): ?>
            $('#group_name').on('input', function() {
                var name = $(this).val();
                var slug = name.toLowerCase()
                    .replace(/[^a-z0-9\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-')
                    .trim();
                $('#group_slug').val(slug);
            });
            <?php endif; ?>
        });
        </script>
        <?php
    }

    /**
     * Handle group actions (save, delete)
     */
    public function handle_group_actions() {
        // Save group
        if (isset($_POST['action']) && $_POST['action'] === 'save_group') {
            // Verify nonce
            if (!isset($_POST['crm_group_nonce']) || !wp_verify_nonce($_POST['crm_group_nonce'], 'save_crm_group')) {
                wp_die(__('Security check failed.'));
            }

            // Check permissions
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions.'));
            }

            $group_type = $this->get_group_type();
            $model = $this->get_model_for_type($group_type);
            $group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
            $name = sanitize_text_field($_POST['group_name']);
            $slug = !empty($_POST['group_slug']) ? sanitize_title($_POST['group_slug']) : sanitize_title($name);
            $description = isset($_POST['group_description']) ? wp_kses_post($_POST['group_description']) : '';
            $location = isset($_POST['group_location']) ? sanitize_text_field($_POST['group_location']) : '';
            $icon = isset($_POST['group_icon']) ? esc_url_raw($_POST['group_icon']) : '';
            $display_order = isset($_POST['group_order']) ? (int)$_POST['group_order'] : 0;
            $is_active = isset($_POST['group_active']) ? 1 : 0;
            $is_premium = isset($_POST['group_premium']) ? 1 : 0;

            $data = [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'location' => $location,
                'icon' => $icon,
                'display_order' => $display_order,
                'is_active' => $is_active
            ];

            if ($group_type === 'posts') {
                $data['is_premium'] = $is_premium;
            }

            if ($group_id) {
                // Update existing group
                $result = $model->update($group_id, $data);
            } else {
                // Create new group
                $result = $model->create($data);
            }

            if ($result) {
                wp_redirect($this->get_admin_url(['group_type' => $group_type, 'saved' => '1']));
                exit;
            } else {
                wp_redirect($this->get_admin_url(['group_type' => $group_type, 'error' => '1', 'error_msg' => 'Failed to save group.']));
                exit;
            }
        }

        // Delete group
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
            $group_id = (int)$_GET['id'];
            $group_type = $this->get_group_type();
            $model = $this->get_model_for_type($group_type);

            // Verify nonce
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_group_' . $group_type . '_' . $group_id)) {
                wp_die(__('Security check failed.'));
            }

            // Check permissions
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions.'));
            }

            $result = $model->delete($group_id);

            if ($result) {
                wp_redirect($this->get_admin_url(['group_type' => $group_type, 'deleted' => '1']));
                exit;
            } else {
                wp_redirect($this->get_admin_url(['group_type' => $group_type, 'error' => '1', 'error_msg' => 'Failed to delete group.']));
                exit;
            }
        }
    }
}
