<?php
/**
 * CRM Admin - Company Prep Management
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Admin_Company_Prep {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_init', [$this, 'handle_actions']);

        // AJAX handlers
        add_action('wp_ajax_sffc_add_prep_material', [$this, 'ajax_add_material']);
        add_action('wp_ajax_sffc_delete_prep_material', [$this, 'ajax_delete_material']);
        add_action('wp_ajax_sffc_approve_prep_request', [$this, 'ajax_approve_request']);
        add_action('wp_ajax_sffc_reject_prep_request', [$this, 'ajax_reject_request']);
        add_action('wp_ajax_sffc_request_prep_materials', [$this, 'ajax_request_materials']);
        add_action('wp_ajax_nopriv_sffc_request_prep_materials', [$this, 'ajax_request_materials']);
    }

    /**
     * Handle admin actions
     */
    public function handle_actions() {
        // Save company
        if (isset($_POST['save_company_prep']) && wp_verify_nonce($_POST['company_prep_nonce'], 'save_company_prep')) {
            $this->handle_save_company();
        }

        if (isset($_POST['sffc_save_prep_library']) && wp_verify_nonce($_POST['prep_library_nonce'] ?? '', 'sffc_save_prep_library')) {
            $this->handle_save_prep_library_item();
        }

        if (isset($_POST['sffc_save_expert_qa']) && wp_verify_nonce($_POST['expert_qa_nonce'] ?? '', 'sffc_save_expert_qa')) {
            $this->handle_save_expert_qa();
        }

        // Delete company
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_company_' . $id)) {
                $this->handle_delete_company($id);
            }
        }

        if (isset($_GET['library_action']) && $_GET['library_action'] === 'delete' && !empty($_GET['library_id'])) {
            $library_id = intval($_GET['library_id']);
            if ($library_id && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'delete_prep_library_' . $library_id)) {
                $this->handle_delete_prep_library_item($library_id);
            }
        }

        if (isset($_GET['qa_action']) && $_GET['qa_action'] === 'delete' && !empty($_GET['qa_id'])) {
            $qa_id = intval($_GET['qa_id']);
            if ($qa_id && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'delete_expert_qa_' . $qa_id)) {
                $this->handle_delete_expert_qa($qa_id);
            }
        }
    }

    /**
     * Handle save company
     */
    private function handle_save_company() {
        if (!current_user_can('manage_options')) {
            return;
        }

        require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-company-prep.php';
        $model = new SFFC_CRM_Company_Prep();

        $company_id = intval($_POST['company_id']);
        $queued_materials = [];

        if (isset($_POST['prep_materials_queue'])) {
            $materials_json = wp_unslash($_POST['prep_materials_queue']);
            $decoded = json_decode($materials_json, true);
            if (is_array($decoded)) {
                $queued_materials = $decoded;
            }
        }

        $data = [
            'company_name' => sanitize_text_field($_POST['company_name']),
            'company_website' => esc_url_raw($_POST['company_website'] ?? ''),
            'link_url' => esc_url_raw($_POST['link_url'] ?? ''),
            'location' => sanitize_text_field($_POST['location'] ?? ''),
            'regions_covered' => sanitize_text_field($_POST['regions_covered'] ?? ''),
            'logo_url' => esc_url_raw($_POST['logo_url'] ?? ''),
            'banner_url' => esc_url_raw($_POST['banner_url'] ?? ''),
        ];

        $saved_company_id = $company_id;

        if ($company_id) {
            $model->update($company_id, $data);
        } else {
            $saved_company_id = $model->create($data);
            $company_id = $saved_company_id;
        }

        if ($saved_company_id && !empty($queued_materials)) {
            foreach ($queued_materials as $material) {
                $file_name = sanitize_text_field($material['file_name'] ?? '');
                $file_url = esc_url_raw($material['file_url'] ?? '');

                if (!$file_name || !$file_url) {
                    continue;
                }

                $model->add_material([
                    'company_id' => $saved_company_id,
                    'file_name' => $file_name,
                    'file_url' => $file_url,
                    'file_type' => sanitize_text_field($material['file_type'] ?? ''),
                    'file_size' => isset($material['file_size']) ? intval($material['file_size']) : 0,
                ]);
            }
        }

        $redirect_id = $saved_company_id ?: $company_id;

        wp_redirect(add_query_arg([
            'page' => 'sffc-crm-company-prep',
            'action' => 'edit',
            'id' => $redirect_id,
            'saved' => '1'
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Handle delete company
     */
    private function handle_delete_company($id) {
        if (!current_user_can('manage_options')) {
            return;
        }

        require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-company-prep.php';
        $model = new SFFC_CRM_Company_Prep();
        $model->delete($id);

        wp_redirect(add_query_arg([
            'page' => 'sffc-crm-company-prep',
            'deleted' => '1'
        ], admin_url('admin.php')));
        exit;
    }

    private function handle_save_prep_library_item() {
        if (!current_user_can('manage_options')) {
            return;
        }

        require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-prep-library.php';
        $model = new SFFC_CRM_Prep_Library();

        $item_id = intval($_POST['library_id'] ?? 0);

        $attachment_id = intval($_POST['library_attachment_id'] ?? 0);
        $resource_url = esc_url_raw($_POST['library_resource_url'] ?? '');
        if (!$resource_url && $attachment_id) {
            $resource_url = wp_get_attachment_url($attachment_id) ?: '';
        }

        $data = [
            'title' => sanitize_text_field($_POST['library_title'] ?? ''),
            'description' => sanitize_textarea_field($_POST['library_description'] ?? ''),
            'resource_url' => $resource_url,
            'attachment_id' => $attachment_id,
            'material_type' => sanitize_key($_POST['library_type'] ?? ''),
            'icon_slug' => sanitize_key($_POST['library_icon'] ?? 'document'),
            'display_order' => intval($_POST['library_display_order'] ?? 0),
            'is_active' => isset($_POST['library_is_active']) ? 1 : 0,
        ];

        if ($item_id) {
            $model->update($item_id, $data);
        } else {
            $item_id = $model->create($data);
        }

        wp_redirect(add_query_arg([
            'page' => 'sffc-crm-company-prep',
            'action' => 'library',
            'library_saved' => '1',
            'library_id' => $item_id,
        ], admin_url('admin.php')));
        exit;
    }

    private function handle_delete_prep_library_item($id) {
        if (!current_user_can('manage_options')) {
            return;
        }

        require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-prep-library.php';
        $model = new SFFC_CRM_Prep_Library();
        $model->delete($id);

        wp_redirect(add_query_arg([
            'page' => 'sffc-crm-company-prep',
            'action' => 'library',
            'library_deleted' => '1'
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * AJAX: Add prep material
     */
    public function ajax_add_material() {
        check_ajax_referer('add_prep_material', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-company-prep.php';
        $model = new SFFC_CRM_Company_Prep();

        $data = [
            'company_id' => intval($_POST['company_id']),
            'file_name' => sanitize_text_field($_POST['file_name']),
            'file_url' => esc_url_raw($_POST['file_url']),
            'file_type' => sanitize_text_field($_POST['file_type'] ?? ''),
            'file_size' => intval($_POST['file_size'] ?? 0),
        ];

        $material_id = $model->add_material($data);

        if ($material_id) {
            wp_send_json_success(['material_id' => $material_id]);
        } else {
            wp_send_json_error(['message' => 'Failed to add material']);
        }
    }

    /**
     * AJAX: Delete prep material
     */
    public function ajax_delete_material() {
        check_ajax_referer('delete_prep_material', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-company-prep.php';
        $model = new SFFC_CRM_Company_Prep();

        $material_id = intval($_POST['material_id']);
        $result = $model->delete_material($material_id);

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(['message' => 'Failed to delete material']);
        }
    }

    /**
     * AJAX: Approve prep request
     */
    public function ajax_approve_request() {
        check_ajax_referer('approve_prep_request', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-company-prep.php';
        $model = new SFFC_CRM_Company_Prep();

        $request_id = intval($_POST['request_id']);
        $admin_id = get_current_user_id();

        $result = $model->approve_request($request_id, $admin_id, true);

        if ($result) {
            wp_send_json_success(['message' => 'Request approved and materials sent']);
        } else {
            wp_send_json_error(['message' => 'Failed to approve request']);
        }
    }

    /**
     * AJAX: Reject prep request
     */
    public function ajax_reject_request() {
        check_ajax_referer('reject_prep_request', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-company-prep.php';
        $model = new SFFC_CRM_Company_Prep();

        $request_id = intval($_POST['request_id']);
        $admin_id = get_current_user_id();
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');

        $result = $model->reject_request($request_id, $admin_id, $notes);

        if ($result) {
            wp_send_json_success(['message' => 'Request rejected']);
        } else {
            wp_send_json_error(['message' => 'Failed to reject request']);
        }
    }

    /**
     * AJAX: Request prep materials (frontend)
     */
    public function ajax_request_materials() {
        if (!is_user_logged_in()) {
            wp_send_json_error([
                'message' => __('Please sign in to request prep materials.', 'senna-finance'),
            ]);
        }

        check_ajax_referer('request_prep_materials', 'nonce');

        require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-company-prep.php';
        $model = new SFFC_CRM_Company_Prep();

        $user_id = get_current_user_id();
        $company_id = intval($_POST['company_id']);

        $result = $model->create_request($user_id, $company_id);

        if ($result['success']) {
            // Send admin notification
            $this->send_admin_notification($user_id, $company_id);
            wp_send_json_success([
                'message' => __('Thanks! Your request has been received. Our prep team will review it shortly.', 'senna-finance'),
                'status' => $result['status'] ?? 'pending',
                'request_id' => $result['request_id'] ?? 0,
            ]);
        }

        $error_message = !empty($result['message'])
            ? $result['message']
            : __('Unable to submit your request right now. Please try again.', 'senna-finance');

        wp_send_json_error([
            'message' => $error_message,
            'status' => $result['status'] ?? '',
        ]);
    }

    /**
     * Send admin notification for new request
     */
    private function send_admin_notification($user_id, $company_id) {
        global $wpdb;

        require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-company-prep.php';
        $model = new SFFC_CRM_Company_Prep();

        $company = $model->get($company_id);
        $user = get_userdata($user_id);

        $admin_email = get_option('admin_email');
        $subject = sprintf('New Prep Material Request - %s', $company['company_name']);

        $message = "";
        $message .= sprintf("A new prep material request has been submitted via the CRM.\n\n");
        $message .= sprintf("User: %s (%s)\n", $user ? $user->display_name : __('Unknown user', 'senna-finance'), $user ? $user->user_email : __('no email', 'senna-finance'));
        $message .= sprintf("User ID: %d\n", $user_id);
        $message .= sprintf("View profile: %s\n\n", esc_url_raw(admin_url('user-edit.php?user_id=' . $user_id)));
        $message .= sprintf("Company: %s\n", $company['company_name']);
        if (!empty($company['company_website'])) {
            $message .= sprintf("Company site: %s\n", $company['company_website']);
        }
        if (!empty($company['location'])) {
            $message .= sprintf("Location: %s\n", $company['location']);
        }
        $message .= "\n";
        $message .= sprintf("Review and approve: %s\n", admin_url('admin.php?page=sffc-crm-company-prep&action=requests'));

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Render company prep form
     */
    public function render_form($company = null) {
        require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-company-prep.php';
        $model = new SFFC_CRM_Company_Prep();

        $company_id = $company['id'] ?? 0;
        $materials = $company_id ? $model->get_materials($company_id) : [];

        ?>
        <div class="wrap sffc-crm-admin">
            <h1><?php echo $company_id ? esc_html__('Edit Company', 'senna-finance') : esc_html__('Add Company', 'senna-finance'); ?></h1>

            <?php if (isset($_GET['saved'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Company saved successfully!', 'senna-finance'); ?></p></div>
            <?php endif; ?>

            <form method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field('save_company_prep', 'company_prep_nonce'); ?>
                <input type="hidden" name="company_id" value="<?php echo esc_attr($company_id); ?>">

                <h2 class="title"><?php esc_html_e('Company Details', 'senna-finance'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="company_name"><?php esc_html_e('Company Name', 'senna-finance'); ?> *</label></th>
                        <td>
                            <input type="text" name="company_name" id="company_name" class="regular-text" required
                                   value="<?php echo esc_attr($company['company_name'] ?? ''); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="company_website"><?php esc_html_e('Company Website', 'senna-finance'); ?></label></th>
                        <td>
                            <input type="url" name="company_website" id="company_website" class="large-text"
                                   value="<?php echo esc_attr($company['company_website'] ?? ''); ?>"
                                   placeholder="https://example.com">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="link_url"><?php esc_html_e('Link URL', 'senna-finance'); ?></label></th>
                        <td>
                            <input type="url" name="link_url" id="link_url" class="large-text"
                                   value="<?php echo esc_attr($company['link_url'] ?? ''); ?>"
                                   placeholder="https://example.com/careers">
                            <p class="description"><?php esc_html_e('Custom link for "View Company" or "Apply Now" button', 'senna-finance'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="location"><?php esc_html_e('Location / Region', 'senna-finance'); ?></label></th>
                        <td>
                            <input type="text" name="location" id="location" class="regular-text"
                                   value="<?php echo esc_attr($company['location'] ?? ''); ?>"
                                   placeholder="London, UK">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="regions_covered"><?php esc_html_e('Regions Covered', 'senna-finance'); ?></label></th>
                        <td>
                            <input type="text" name="regions_covered" id="regions_covered" class="large-text"
                                   value="<?php echo esc_attr($company['regions_covered'] ?? ''); ?>"
                                   placeholder="Europe, private equity">
                            <p class="description"><?php esc_html_e('Comma-separated list of regions', 'senna-finance'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="logo_url"><?php esc_html_e('Company Logo URL', 'senna-finance'); ?></label></th>
                        <td>
                            <div style="margin-bottom: 10px;">
                                <div id="logo-preview" style="width: 100px; height: 100px; border: 1px solid #ddd; border-radius: 4px; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-bottom: 10px; background: #f9f9f9;">
                                    <?php if (!empty($company['logo_url'])): ?>
                                        <img src="<?php echo esc_url($company['logo_url']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 12px;"><?php esc_html_e('No logo', 'senna-finance'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <input type="url" name="logo_url" id="logo_url" class="large-text" value="<?php echo esc_attr($company['logo_url'] ?? ''); ?>" placeholder="https://example.com/logo.png">
                            <p class="description"><?php esc_html_e('Paste image URL or upload from media library', 'senna-finance'); ?></p>
                            <button type="button" class="button" id="upload-logo"><?php esc_html_e('Upload from Media Library', 'senna-finance'); ?></button>
                            <button type="button" class="button" id="clear-logo"><?php esc_html_e('Clear', 'senna-finance'); ?></button>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="banner_url"><?php esc_html_e('Company Banner URL', 'senna-finance'); ?></label></th>
                        <td>
                            <div style="margin-bottom: 10px;">
                                <div id="banner-preview" style="width: 300px; height: 100px; border: 1px solid #ddd; border-radius: 4px; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-bottom: 10px; background: #f9f9f9;">
                                    <?php if (!empty($company['banner_url'])): ?>
                                        <img src="<?php echo esc_url($company['banner_url']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 12px;"><?php esc_html_e('No banner', 'senna-finance'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <input type="url" name="banner_url" id="banner_url" class="large-text" value="<?php echo esc_attr($company['banner_url'] ?? ''); ?>" placeholder="https://example.com/banner.png">
                            <p class="description"><?php esc_html_e('Paste image URL or upload from media library', 'senna-finance'); ?></p>
                            <button type="button" class="button" id="upload-banner"><?php esc_html_e('Upload from Media Library', 'senna-finance'); ?></button>
                            <button type="button" class="button" id="clear-banner"><?php esc_html_e('Clear', 'senna-finance'); ?></button>
                        </td>
                    </tr>
                </table>

                <h2 class="title"><?php esc_html_e('Prep Materials', 'senna-finance'); ?></h2>
                <div class="sffc-prep-materials-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;">
                    <?php if ($company_id && !empty($materials)): ?>
                        <table class="widefat fixed striped" style="margin:0;">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('File Name', 'senna-finance'); ?></th>
                                    <th><?php esc_html_e('Type', 'senna-finance'); ?></th>
                                    <th><?php esc_html_e('Size', 'senna-finance'); ?></th>
                                    <th><?php esc_html_e('Actions', 'senna-finance'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($materials as $material): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo esc_url($material['file_url']); ?>" target="_blank">
                                                <?php echo esc_html($material['file_name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo esc_html(strtoupper($material['file_type'] ?? 'file')); ?></td>
                                        <td><?php echo $material['file_size'] ? size_format($material['file_size']) : '-'; ?></td>
                                        <td>
                                            <button type="button" class="button button-small delete-material" data-id="<?php echo esc_attr($material['id']); ?>" style="color: #dc2626;">
                                                <?php esc_html_e('Delete', 'senna-finance'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="padding:24px;">
                            <p class="description" style="margin:0;">
                                <?php echo $company_id
                                    ? esc_html__('No prep documents uploaded yet. Use the uploader below to add PDF, DOC, XLS, or PPT files.', 'senna-finance')
                                    : esc_html__('No files yet. Queue prep documents below and they will be attached as soon as you create this company.', 'senna-finance'); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="sffc-prep-uploader" style="margin-top:20px;">
                    <h3 style="margin-top:0;">
                        <?php esc_html_e('Upload Prep Documents', 'senna-finance'); ?>
                    </h3>
                    <p class="description" style="margin-bottom:12px;">
                        <?php if ($company_id): ?>
                            <?php esc_html_e('Select multiple files from the media library. They will be saved instantly and appear in the table above.', 'senna-finance'); ?>
                        <?php else: ?>
                            <?php esc_html_e('Queue files now—even before the company exists. They will be attached automatically once you click "Create Company".', 'senna-finance'); ?>
                        <?php endif; ?>
                    </p>
                    <button type="button" class="button" id="upload-material"><?php esc_html_e('Select Files (PDF, DOC, XLS, PPT)', 'senna-finance'); ?></button>
                    <input type="hidden" name="prep_materials_queue" id="prep-materials-queue" value="[]">
                    <div id="queued-materials" class="sffc-prep-queue" style="margin-top:12px; border:1px solid #e5e7eb; border-radius:8px; background:#fbfbfb; padding:12px; <?php echo $company_id ? 'display:none;' : ''; ?>">
                        <p class="queue-empty" style="margin:0; color:#6b7280;">
                            <?php esc_html_e('No new files queued yet.', 'senna-finance'); ?>
                        </p>
                        <ul class="sffc-prep-queue-list" style="list-style:none; margin:8px 0 0; padding:0;"></ul>
                    </div>
                </div>

                <p class="submit">
                    <button type="submit" name="save_company_prep" class="button button-primary button-large">
                        <?php echo $company_id ? esc_html__('Update Company', 'senna-finance') : esc_html__('Create Company', 'senna-finance'); ?>
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=sffc-crm-company-prep'); ?>" class="button button-large">
                        <?php esc_html_e('Cancel', 'senna-finance'); ?>
                    </a>
                </p>
            </form>

            <script>
            jQuery(document).ready(function($) {
                var logoFrame, bannerFrame, materialFrame;
                var companyId = <?php echo intval($company_id); ?>;
                var queuedMaterialsInput = $('#prep-materials-queue');
                var $queueWrapper = $('#queued-materials');
                var $queueList = $('.sffc-prep-queue-list');
                var $queueEmpty = $queueWrapper.find('.queue-empty');
                var queuedMaterials = [];

                // Logo URL input - update preview on change
                $('#logo_url').on('input change', function() {
                    var url = $(this).val().trim();
                    if (url && (url.match(/\.(jpeg|jpg|gif|png|svg|webp)$/i) || url.indexOf('http') === 0)) {
                        $('#logo-preview').html('<img src="' + url + '" style="width:100%;height:100%;object-fit:cover;" onerror="this.parentElement.innerHTML=\'<span style=&quot;color:#dc2626;font-size:12px;&quot;>Invalid image</span>\';">');
                    } else if (!url) {
                        $('#logo-preview').html('<span style="color:#999;font-size:12px;"><?php esc_html_e('No logo', 'senna-finance'); ?></span>');
                    }
                });

                // Banner URL input - update preview on change
                $('#banner_url').on('input change', function() {
                    var url = $(this).val().trim();
                    if (url && (url.match(/\.(jpeg|jpg|gif|png|svg|webp)$/i) || url.indexOf('http') === 0)) {
                        $('#banner-preview').html('<img src="' + url + '" style="width:100%;height:100%;object-fit:cover;" onerror="this.parentElement.innerHTML=\'<span style=&quot;color:#dc2626;font-size:12px;&quot;>Invalid image</span>\';">');
                    } else if (!url) {
                        $('#banner-preview').html('<span style="color:#999;font-size:12px;"><?php esc_html_e('No banner', 'senna-finance'); ?></span>');
                    }
                });

                // Logo upload from media library
                $('#upload-logo').on('click', function(e) {
                    e.preventDefault();
                    if (logoFrame) {
                        logoFrame.open();
                        return;
                    }
                    logoFrame = wp.media({
                        title: '<?php esc_html_e('Select Logo', 'senna-finance'); ?>',
                        button: { text: '<?php esc_html_e('Use this image', 'senna-finance'); ?>' },
                        multiple: false
                    });
                    logoFrame.on('select', function() {
                        var attachment = logoFrame.state().get('selection').first().toJSON();
                        $('#logo_url').val(attachment.url).trigger('change');
                    });
                    logoFrame.open();
                });

                $('#clear-logo').on('click', function(e) {
                    e.preventDefault();
                    $('#logo_url').val('').trigger('change');
                });

                // Banner upload from media library
                $('#upload-banner').on('click', function(e) {
                    e.preventDefault();
                    if (bannerFrame) {
                        bannerFrame.open();
                        return;
                    }
                    bannerFrame = wp.media({
                        title: '<?php esc_html_e('Select Banner', 'senna-finance'); ?>',
                        button: { text: '<?php esc_html_e('Use this image', 'senna-finance'); ?>' },
                        multiple: false
                    });
                    bannerFrame.on('select', function() {
                        var attachment = bannerFrame.state().get('selection').first().toJSON();
                        $('#banner_url').val(attachment.url).trigger('change');
                    });
                    bannerFrame.open();
                });

                $('#clear-banner').on('click', function(e) {
                    e.preventDefault();
                    $('#banner_url').val('').trigger('change');
                });

                try {
                    var existingQueue = JSON.parse(queuedMaterialsInput.val() || '[]');
                    if (Array.isArray(existingQueue) && existingQueue.length) {
                        queuedMaterials = existingQueue;
                        renderQueuedMaterials();
                    }
                } catch (err) {}

                $('#upload-material').on('click', function(e) {
                    e.preventDefault();
                    if (materialFrame) {
                        materialFrame.open();
                        return;
                    }
                    materialFrame = wp.media({
                        title: '<?php esc_html_e('Select Material', 'senna-finance'); ?>',
                        button: { text: '<?php esc_html_e('Add Files', 'senna-finance'); ?>' },
                        multiple: true
                    });
                    materialFrame.on('select', function() {
                        var attachments = materialFrame.state().get('selection').toJSON();
                        uploadMaterials(attachments);
                    });
                    materialFrame.open();
                });

                function uploadMaterials(attachments) {
                    attachments.forEach(function(attachment) {
                        if (companyId) {
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'sffc_add_prep_material',
                                    company_id: companyId,
                                    file_name: attachment.filename || attachment.title || attachment.name || '<?php echo esc_js(__('Prep Document', 'senna-finance')); ?>',
                                    file_url: attachment.url,
                                    file_type: attachment.subtype || attachment.type || '',
                                    file_size: attachment.filesizeInBytes || 0,
                                    nonce: '<?php echo wp_create_nonce('add_prep_material'); ?>'
                                },
                                success: function(response) {
                                    if (response && response.success) {
                                        location.reload();
                                    } else {
                                        var errorMsg = (response && response.data && response.data.message) ? response.data.message : '<?php esc_html_e('Failed to upload material. Please try again.', 'senna-finance'); ?>';
                                        alert(errorMsg);
                                    }
                                },
                                error: function() {
                                    alert('<?php esc_html_e('Failed to upload material. Please try again.', 'senna-finance'); ?>');
                                }
                            });
                        } else {
                            queueMaterial({
                                file_name: attachment.filename || attachment.title || attachment.name || '<?php echo esc_js(__('Prep Document', 'senna-finance')); ?>',
                                file_url: attachment.url,
                                file_type: attachment.subtype || attachment.type || '',
                                file_size: attachment.filesizeInBytes || 0
                            });
                        }
                    });
                }

                function queueMaterial(material) {
                    queuedMaterials.push(material);
                    renderQueuedMaterials();
                }

                function renderQueuedMaterials() {
                    if (!queuedMaterials.length) {
                        $queueList.empty();
                        $queueEmpty.show();
                        if (companyId) {
                            $queueWrapper.hide();
                        }
                    } else {
                        $queueEmpty.hide();
                        var items = queuedMaterials.map(function(material, index) {
                            var metaParts = [];
                            if (material.file_type) {
                                metaParts.push(material.file_type.toUpperCase());
                            }
                            if (material.file_size) {
                                metaParts.push(formatBytes(material.file_size));
                            }
                            var meta = metaParts.length ? ' <span class="description">(' + metaParts.join(' • ') + ')</span>' : '';
                            return '<li class="sffc-prep-queue-row" style="display:flex;align-items:center;justify-content:space-between;padding:6px 0;border-bottom:1px solid #e5e7eb;">'
                                + '<span><strong>' + material.file_name + '</strong>' + meta + '</span>'
                                + '<button type="button" class="button-link-delete remove-queued-material" data-index="' + index + '">&times;</button>'
                                + '</li>';
                        }).join('');
                        $queueList.html(items);
                        $queueWrapper.show();
                        $queueList.children('.sffc-prep-queue-row:last').css('border-bottom', 'none');
                    }

                    queuedMaterialsInput.val(JSON.stringify(queuedMaterials));
                }

                function formatBytes(bytes) {
                    if (!bytes) {
                        return '';
                    }
                    var units = ['B', 'KB', 'MB', 'GB'];
                    var i = 0;
                    while (bytes >= 1024 && i < units.length - 1) {
                        bytes /= 1024;
                        i++;
                    }
                    var value = i === 0 ? Math.round(bytes) : bytes.toFixed(1);
                    return value + ' ' + units[i];
                }

                $queueList.on('click', '.remove-queued-material', function() {
                    var index = $(this).data('index');
                    queuedMaterials.splice(index, 1);
                    renderQueuedMaterials();
                });

                if (companyId) {
                    $('.delete-material').on('click', function() {
                        if (!confirm('<?php esc_html_e('Are you sure?', 'senna-finance'); ?>')) {
                            return;
                        }
                        var materialId = $(this).data('id');
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'sffc_delete_prep_material',
                                material_id: materialId,
                                nonce: '<?php echo wp_create_nonce('delete_prep_material'); ?>'
                            },
                            success: function(response) {
                                if (response && response.success) {
                                    location.reload();
                                } else {
                                    var deleteMsg = (response && response.data && response.data.message) ? response.data.message : '<?php esc_html_e('Failed to delete material.', 'senna-finance'); ?>';
                                    alert(deleteMsg);
                                }
                            },
                            error: function() {
                                alert('<?php esc_html_e('Failed to delete material.', 'senna-finance'); ?>');
                            }
                        });
                    });
                }
            });
            </script>
        </div>
        <?php
    }

    /**
     * Render prep requests page
     */
    public function render_requests() {
        require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-company-prep.php';
        $model = new SFFC_CRM_Company_Prep();

        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : null;
        $requests = $model->get_requests(['status' => $status_filter]);

        $pending_count = count(array_filter($requests, function($r) { return $r['status'] === 'pending'; }));
        $approved_count = count(array_filter($requests, function($r) { return $r['status'] === 'approved'; }));
        $rejected_count = count(array_filter($requests, function($r) { return $r['status'] === 'rejected'; }));

        ?>
        <div class="wrap sffc-crm-admin">
            <h1><?php esc_html_e('Prep Material Requests', 'senna-finance'); ?></h1>

            <ul class="subsubsub">
                <li>
                    <a href="<?php echo admin_url('admin.php?page=sffc-crm-company-prep&action=requests'); ?>" class="<?php echo !$status_filter ? 'current' : ''; ?>">
                        <?php esc_html_e('All', 'senna-finance'); ?> <span class="count">(<?php echo count($requests); ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo admin_url('admin.php?page=sffc-crm-company-prep&action=requests&status=pending'); ?>" class="<?php echo $status_filter === 'pending' ? 'current' : ''; ?>">
                        <?php esc_html_e('Pending', 'senna-finance'); ?> <span class="count">(<?php echo $pending_count; ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo admin_url('admin.php?page=sffc-crm-company-prep&action=requests&status=approved'); ?>" class="<?php echo $status_filter === 'approved' ? 'current' : ''; ?>">
                        <?php esc_html_e('Approved', 'senna-finance'); ?> <span class="count">(<?php echo $approved_count; ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo admin_url('admin.php?page=sffc-crm-company-prep&action=requests&status=rejected'); ?>" class="<?php echo $status_filter === 'rejected' ? 'current' : ''; ?>">
                        <?php esc_html_e('Rejected', 'senna-finance'); ?> <span class="count">(<?php echo $rejected_count; ?>)</span>
                    </a>
                </li>
            </ul>

            <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('User', 'senna-finance'); ?></th>
                        <th><?php esc_html_e('Company', 'senna-finance'); ?></th>
                        <th><?php esc_html_e('Requested', 'senna-finance'); ?></th>
                        <th><?php esc_html_e('Status', 'senna-finance'); ?></th>
                        <th><?php esc_html_e('Actions', 'senna-finance'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="5"><?php esc_html_e('No requests found.', 'senna-finance'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($request['user_name']); ?></strong>
                                    <br><a href="mailto:<?php echo esc_attr($request['user_email']); ?>"><?php echo esc_html($request['user_email']); ?></a>
                                </td>
                                <td><?php echo esc_html($request['company_name']); ?></td>
                                <td><?php echo esc_html(human_time_diff(strtotime($request['requested_at'])) . ' ago'); ?></td>
                                <td>
                                    <?php if ($request['status'] === 'pending'): ?>
                                        <span style="color: #f59e0b;">⏳ <?php esc_html_e('Pending', 'senna-finance'); ?></span>
                                    <?php elseif ($request['status'] === 'approved'): ?>
                                        <span style="color: #10b981;">✓ <?php esc_html_e('Approved', 'senna-finance'); ?></span>
                                    <?php else: ?>
                                        <span style="color: #dc2626;">✗ <?php esc_html_e('Rejected', 'senna-finance'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($request['status'] === 'pending'): ?>
                                        <button type="button" class="button button-small button-primary approve-request" data-id="<?php echo esc_attr($request['id']); ?>">
                                            <?php esc_html_e('Approve & Send', 'senna-finance'); ?>
                                        </button>
                                        <button type="button" class="button button-small reject-request" data-id="<?php echo esc_attr($request['id']); ?>" style="color: #dc2626;">
                                            <?php esc_html_e('Reject', 'senna-finance'); ?>
                                        </button>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.approve-request').on('click', function() {
                var requestId = $(this).data('id');
                if (!confirm('<?php esc_html_e('Approve this request and send materials to the user?', 'senna-finance'); ?>')) {
                    return;
                }
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sffc_approve_prep_request',
                        request_id: requestId,
                        nonce: '<?php echo wp_create_nonce('approve_prep_request'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message || '<?php esc_html_e('Failed to approve request', 'senna-finance'); ?>');
                        }
                    }
                });
            });

            $('.reject-request').on('click', function() {
                var requestId = $(this).data('id');
                var notes = prompt('<?php esc_html_e('Reason for rejection (optional):', 'senna-finance'); ?>');
                if (notes === null) return;

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sffc_reject_prep_request',
                        request_id: requestId,
                        notes: notes,
                        nonce: '<?php echo wp_create_nonce('reject_prep_request'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message || '<?php esc_html_e('Failed to reject request', 'senna-finance'); ?>');
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function render_library() {
        require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-prep-library.php';
        $model = new SFFC_CRM_Prep_Library();

        $edit_id = isset($_GET['library_id']) ? intval($_GET['library_id']) : 0;
        $editing_item = $edit_id ? $model->get($edit_id) : null;
        $items = $model->get_all(['is_active' => null]);
        $icon_options = SFFC_CRM_Prep_Library::get_icon_options();
        $type_options = [
            'guide' => __('Guide / Handbook', 'senna-finance'),
            'template' => __('Template', 'senna-finance'),
            'practice' => __('Practice Pack', 'senna-finance'),
            'interview' => __('Interview Intel', 'senna-finance'),
            'other' => __('Other', 'senna-finance'),
        ];

        $attachment_id = intval($editing_item['attachment_id'] ?? 0);
        $attachment_name = '';
        if ($attachment_id) {
            $attachment_name = get_the_title($attachment_id);
            if (!$attachment_name) {
                $attachment_name = basename(wp_get_attachment_url($attachment_id));
            }
        }

        ?>
        <div class="wrap sffc-crm-admin">
            <h1><?php esc_html_e('Prep Materials Library', 'senna-finance'); ?></h1>

            <?php if (isset($_GET['library_saved'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Prep material saved.', 'senna-finance'); ?></p></div>
            <?php endif; ?>

            <?php if (isset($_GET['library_deleted'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Prep material deleted.', 'senna-finance'); ?></p></div>
            <?php endif; ?>

            <div class="sffc-crm-prep-library">
                <div class="sffc-crm-prep-library-form" style="background:#fff; padding:24px; border:1px solid #e5e7eb; border-radius:8px; margin-bottom:30px;">
                    <h2 style="margin-top:0;">
                        <?php echo $edit_id ? esc_html__('Edit Material', 'senna-finance') : esc_html__('Add New Material', 'senna-finance'); ?>
                    </h2>
                    <form method="post">
                        <?php wp_nonce_field('sffc_save_prep_library', 'prep_library_nonce'); ?>
                        <input type="hidden" name="library_id" value="<?php echo esc_attr($edit_id); ?>">

                        <table class="form-table">
                            <tr>
                                <th><label for="library_title"><?php esc_html_e('Title', 'senna-finance'); ?></label></th>
                                <td>
                                    <input type="text" class="regular-text" id="library_title" name="library_title" required value="<?php echo esc_attr($editing_item['title'] ?? ''); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th><label for="library_description"><?php esc_html_e('Description', 'senna-finance'); ?></label></th>
                                <td>
                                    <textarea id="library_description" name="library_description" rows="3" class="large-text" placeholder="<?php esc_attr_e('Short summary shown in the CRM card...', 'senna-finance'); ?>"><?php echo esc_textarea($editing_item['description'] ?? ''); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="library_resource_url"><?php esc_html_e('Resource URL', 'senna-finance'); ?></label></th>
                                <td>
                                    <input type="url" id="library_resource_url" name="library_resource_url" class="large-text" value="<?php echo esc_attr($editing_item['resource_url'] ?? ''); ?>" placeholder="https://...">
                                    <p class="description"><?php esc_html_e('Paste a link to Google Docs, Notion, or any hosted file. Selecting a media file below will autofill this field.', 'senna-finance'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Media Library File', 'senna-finance'); ?></th>
                                <td>
                                    <div style="display:flex; gap:10px; align-items:center;">
                                        <button type="button" class="button" id="sffc-crm-select-library-media"><?php esc_html_e('Choose File', 'senna-finance'); ?></button>
                                        <span id="sffc-crm-library-media-label" style="color:#475569;">
                                            <?php echo $attachment_name ? esc_html($attachment_name) : esc_html__('No file selected', 'senna-finance'); ?>
                                        </span>
                                    </div>
                                    <input type="hidden" name="library_attachment_id" id="library_attachment_id" value="<?php echo esc_attr($attachment_id); ?>">
                                    <p class="description"><?php esc_html_e('Optional. Use this to host PDFs directly in WordPress.', 'senna-finance'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="library_type"><?php esc_html_e('Material Type', 'senna-finance'); ?></label></th>
                                <td>
                                    <select id="library_type" name="library_type">
                                        <?php foreach ($type_options as $key => $label): ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($editing_item['material_type'] ?? 'guide', $key); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Icon', 'senna-finance'); ?></th>
                                <td>
                                    <fieldset style="display:flex; flex-wrap:wrap; gap:10px;">
                                        <?php foreach ($icon_options as $slug => $icon): ?>
                                            <label style="display:flex; align-items:center; gap:8px; border:1px solid #e5e7eb; padding:8px 12px; border-radius:6px; cursor:pointer;">
                                                <input type="radio" name="library_icon" value="<?php echo esc_attr($slug); ?>" <?php checked(($editing_item['icon_slug'] ?? 'document') === $slug); ?>>
                                                <span class="sffc-crm-prep-folder-icon" style="color:#0D353E;">
                                                    <?php echo $icon['svg']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                </span>
                                                <span><?php echo esc_html($icon['label']); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </fieldset>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="library_display_order"><?php esc_html_e('Display Order', 'senna-finance'); ?></label></th>
                                <td>
                                    <input type="number" id="library_display_order" name="library_display_order" value="<?php echo esc_attr($editing_item['display_order'] ?? 0); ?>" style="width:100px;">
                                    <p class="description"><?php esc_html_e('Lower numbers appear first.', 'senna-finance'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Status', 'senna-finance'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="library_is_active" <?php checked(($editing_item['is_active'] ?? 1) == 1); ?>>
                                        <?php esc_html_e('Active (visible to members)', 'senna-finance'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="submit" name="sffc_save_prep_library" class="button button-primary button-large">
                                <?php echo $edit_id ? esc_html__('Update Material', 'senna-finance') : esc_html__('Add Material', 'senna-finance'); ?>
                            </button>
                            <?php if ($edit_id): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=sffc-crm-company-prep&action=library')); ?>" class="button button-large"><?php esc_html_e('Cancel', 'senna-finance'); ?></a>
                            <?php endif; ?>
                        </p>
                    </form>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Title', 'senna-finance'); ?></th>
                            <th><?php esc_html_e('Type', 'senna-finance'); ?></th>
                            <th><?php esc_html_e('Link', 'senna-finance'); ?></th>
                            <th><?php esc_html_e('Icon', 'senna-finance'); ?></th>
                            <th><?php esc_html_e('Status', 'senna-finance'); ?></th>
                            <th><?php esc_html_e('Actions', 'senna-finance'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="6"><?php esc_html_e('No prep materials added yet.', 'senna-finance'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($item['title']); ?></strong>
                                        <?php if (!empty($item['description'])): ?>
                                            <br><span class="description"><?php echo esc_html(wp_trim_words($item['description'], 14)); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($type_options[$item['material_type']] ?? __('—', 'senna-finance')); ?></td>
                                    <td>
                                        <?php if (!empty($item['resource_url'])): ?>
                                            <a href="<?php echo esc_url($item['resource_url']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open', 'senna-finance'); ?> ↗</a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo isset($icon_options[$item['icon_slug']]) ? $icon_options[$item['icon_slug']]['label'] : '—'; ?></td>
                                    <td>
                                        <?php if ($item['is_active']): ?>
                                            <span style="color:#10b981;">● <?php esc_html_e('Active', 'senna-finance'); ?></span>
                                        <?php else: ?>
                                            <span style="color:#6b7280;">○ <?php esc_html_e('Hidden', 'senna-finance'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url(add_query_arg(['page' => 'sffc-crm-company-prep', 'action' => 'library', 'library_id' => $item['id']])); ?>" class="button button-small"><?php esc_html_e('Edit', 'senna-finance'); ?></a>
                                        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg([
                                            'page' => 'sffc-crm-company-prep',
                                            'action' => 'library',
                                            'library_action' => 'delete',
                                            'library_id' => $item['id'],
                                        ]), 'delete_prep_library_' . $item['id'])); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e('Delete this material?', 'senna-finance'); ?>');" style="color:#dc2626;">
                                            <?php esc_html_e('Delete', 'senna-finance'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        jQuery(function($) {
            var mediaFrame;
            $('#sffc-crm-select-library-media').on('click', function(e) {
                e.preventDefault();

                if (mediaFrame) {
                    mediaFrame.open();
                    return;
                }

                mediaFrame = wp.media({
                    title: '<?php esc_html_e('Select Prep Material', 'senna-finance'); ?>',
                    button: { text: '<?php esc_html_e('Use this file', 'senna-finance'); ?>' },
                    multiple: false
                });

                mediaFrame.on('select', function() {
                    var attachment = mediaFrame.state().get('selection').first().toJSON();
                    $('#library_attachment_id').val(attachment.id);
                    $('#library_resource_url').val(attachment.url);
                    $('#sffc-crm-library-media-label').text(attachment.filename);
                });

                mediaFrame.open();
            });
        });
        </script>
        <?php
    }

    private function handle_save_expert_qa() {
        if (!current_user_can('manage_options')) {
            return;
        }

        require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-expert-qa.php';
        $model = new SFFC_CRM_Expert_QA();

        $qa_id = intval($_POST['expert_qa_id'] ?? 0);
        $answer = wp_kses_post($_POST['expert_qa_answer'] ?? '');
        $status = sanitize_key($_POST['expert_qa_status'] ?? 'pending');
        if (!in_array($status, ['pending', 'answered'], true)) {
            $status = 'pending';
        }

        if (!$qa_id) {
            return;
        }

        $current_user = wp_get_current_user();
        $expert_name = sanitize_text_field($_POST['expert_qa_expert_name'] ?? ($current_user->display_name ?: 'MENA Careers Expert'));
        $expert_title = sanitize_text_field($_POST['expert_qa_expert_title'] ?? '');

        $update = [
            'answer' => $answer,
            'status' => $status,
            'answered_by' => $current_user->ID,
            'answered_by_name' => $expert_name,
            'answered_by_title' => $expert_title,
        ];

        if ($status === 'answered' && !empty($answer)) {
            $update['answered_at'] = current_time('mysql');
        }

        $model->update($qa_id, $update);

        wp_redirect(add_query_arg([
            'page' => 'sffc-crm-company-prep',
            'action' => 'qa',
            'qa_saved' => '1',
        ], admin_url('admin.php')));
        exit;
    }

    private function handle_delete_expert_qa($id) {
        if (!current_user_can('manage_options')) {
            return;
        }

        require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-expert-qa.php';
        $model = new SFFC_CRM_Expert_QA();
        $model->delete($id);

        wp_redirect(add_query_arg([
            'page' => 'sffc-crm-company-prep',
            'action' => 'qa',
            'qa_deleted' => '1'
        ], admin_url('admin.php')));
        exit;
    }

    public function render_expert_qa_page() {
        require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-expert-qa.php';
        $model = new SFFC_CRM_Expert_QA();

        $qa_action = isset($_GET['qa_action']) ? sanitize_text_field($_GET['qa_action']) : 'list';
        $qa_id = isset($_GET['qa_id']) ? intval($_GET['qa_id']) : 0;
        $editing_item = ($qa_action === 'edit' && $qa_id) ? $model->get($qa_id) : null;
        $items = $model->get_all(['status' => null]);

        ?>
        <div class="wrap sffc-crm-admin">
            <h1><?php esc_html_e('Expert Q&A', 'senna-finance'); ?></h1>

            <?php if (isset($_GET['qa_saved'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Answer saved.', 'senna-finance'); ?></p></div>
            <?php endif; ?>

            <?php if (isset($_GET['qa_deleted'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Question deleted.', 'senna-finance'); ?></p></div>
            <?php endif; ?>

            <?php if ($editing_item): ?>
                <div class="sffc-crm-prep-library-form" style="background:#fff; padding:24px; border:1px solid #e5e7eb; border-radius:8px; margin-bottom:30px;">
                    <h2 style="margin-top:0;">
                        <?php esc_html_e('Answer Question', 'senna-finance'); ?>
                    </h2>
                    <form method="post">
                        <?php wp_nonce_field('sffc_save_expert_qa', 'expert_qa_nonce'); ?>
                        <input type="hidden" name="expert_qa_id" value="<?php echo esc_attr($editing_item['id']); ?>">

                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e('Question', 'senna-finance'); ?></th>
                                <td>
                                    <p style="margin:0; font-weight:600;">
                                        <?php echo esc_html($editing_item['question']); ?>
                                    </p>
                                    <p class="description">
                                        <?php esc_html_e('Asked by', 'senna-finance'); ?>
                                        <?php echo esc_html($editing_item['user_name'] ?: __('MENA Careers member', 'senna-finance')); ?>
                                        <?php if (!empty($editing_item['created_at'])): ?>
                                            • <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($editing_item['created_at']))); ?>
                                        <?php endif; ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="expert_qa_answer"><?php esc_html_e('Expert Answer', 'senna-finance'); ?></label></th>
                                <td>
                                    <textarea id="expert_qa_answer" name="expert_qa_answer" rows="6" class="large-text" placeholder="<?php esc_attr_e('Share the steps, frameworks, or insider advice...', 'senna-finance'); ?>"><?php echo esc_textarea($editing_item['answer'] ?? ''); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="expert_qa_expert_name"><?php esc_html_e('Expert Name', 'senna-finance'); ?></label></th>
                                <td>
                                    <input type="text" id="expert_qa_expert_name" name="expert_qa_expert_name" class="regular-text" value="<?php echo esc_attr($editing_item['answered_by_name'] ?? ''); ?>" placeholder="<?php esc_attr_e('e.g., Charlotte (ex-Rothschild)', 'senna-finance'); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th><label for="expert_qa_expert_title"><?php esc_html_e('Expert Title', 'senna-finance'); ?></label></th>
                                <td>
                                    <input type="text" id="expert_qa_expert_title" name="expert_qa_expert_title" class="regular-text" value="<?php echo esc_attr($editing_item['answered_by_title'] ?? ''); ?>" placeholder="<?php esc_attr_e('ex-Barclays Associate Director', 'senna-finance'); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th><label for="expert_qa_status"><?php esc_html_e('Status', 'senna-finance'); ?></label></th>
                                <td>
                                    <select id="expert_qa_status" name="expert_qa_status">
                                        <option value="pending" <?php selected($editing_item['status'], 'pending'); ?>><?php esc_html_e('Pending', 'senna-finance'); ?></option>
                                        <option value="answered" <?php selected($editing_item['status'], 'answered'); ?>><?php esc_html_e('Answered', 'senna-finance'); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e('Set to “Answered” to publish this Q&A to the dashboard stream.', 'senna-finance'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="submit" name="sffc_save_expert_qa" class="button button-primary button-large"><?php esc_html_e('Save Answer', 'senna-finance'); ?></button>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sffc-crm-company-prep&action=qa')); ?>" class="button button-large"><?php esc_html_e('Cancel', 'senna-finance'); ?></a>
                        </p>
                    </form>
                </div>
            <?php endif; ?>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Question', 'senna-finance'); ?></th>
                        <th><?php esc_html_e('Member', 'senna-finance'); ?></th>
                        <th><?php esc_html_e('Status', 'senna-finance'); ?></th>
                        <th><?php esc_html_e('Answer Preview', 'senna-finance'); ?></th>
                        <th><?php esc_html_e('Actions', 'senna-finance'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="5"><?php esc_html_e('No questions have been submitted yet.', 'senna-finance'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html(wp_trim_words($item['question'], 16)); ?></strong>
                                    <?php if (!empty($item['created_at'])): ?>
                                        <br><span class="description"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($item['created_at']))); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo esc_html($item['user_name'] ?: __('MENA Careers member', 'senna-finance')); ?>
                                    <?php if (!empty($item['user_email'])): ?>
                                        <br><a href="mailto:<?php echo esc_attr($item['user_email']); ?>" class="description"><?php echo esc_html($item['user_email']); ?></a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['status'] === 'answered'): ?>
                                        <span style="color:#10b981; font-weight:600;"><?php esc_html_e('Answered', 'senna-finance'); ?></span>
                                    <?php else: ?>
                                        <span style="color:#f97316; font-weight:600;"><?php esc_html_e('Pending', 'senna-finance'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($item['answer'])): ?>
                                        <span class="description"><?php echo esc_html(wp_trim_words($item['answer'], 18)); ?></span>
                                    <?php else: ?>
                                        <span class="description"><?php esc_html_e('Awaiting expert response', 'senna-finance'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg([
                                        'page' => 'sffc-crm-company-prep',
                    
                                        'action' => 'qa',
                                        'qa_action' => 'edit',
                                        'qa_id' => $item['id'],
                                    ])); ?>" class="button button-small">
                                        <?php esc_html_e('Answer', 'senna-finance'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg([
                                        'page' => 'sffc-crm-company-prep',
                                        'action' => 'qa',
                                        'qa_action' => 'delete',
                                        'qa_id' => $item['id'],
                                    ]), 'delete_expert_qa_' . $item['id'])); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e('Delete this question?', 'senna-finance'); ?>');" style="color:#dc2626;">
                                        <?php esc_html_e('Delete', 'senna-finance'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

// Initialize
SFFC_CRM_Admin_Company_Prep::get_instance();
