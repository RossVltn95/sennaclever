<?php
/**
 * CRM Job Draft Admin
 * Review queue for scanner/imported jobs before they become approved CRM posts.
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Job_Draft_Admin {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'add_menu'], 35);
        add_action('admin_init', [$this, 'handle_actions']);
    }

    public function add_menu() {
        add_submenu_page(
            'sffc-crm',
            __('Job Scanner', 'senna-finance'),
            __('Job Scanner', 'senna-finance'),
            'manage_options',
            'sffc-crm-job-scanner',
            [$this, 'render_page']
        );
    }

    public function handle_actions() {
        if (empty($_POST['sffc_crm_job_draft_action'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage job drafts.', 'senna-finance'));
        }

        $action = sanitize_key((string) $_POST['sffc_crm_job_draft_action']);
        $bulk_actions = ['bulk_delete', 'bulk_add_trackers', 'bulk_apply_status', 'bulk_apply_suggested_trackers', 'bulk_update_posted_date', 'bulk_update_location'];
        $nonce_action = in_array($action, $bulk_actions, true)
            ? 'sffc_crm_job_draft_bulk'
            : 'sffc_crm_job_draft_' . $action;
        check_admin_referer($nonce_action);

        if ($action === 'ingest') {
            $this->handle_ingest();
            return;
        }

        if (in_array($action, $bulk_actions, true)) {
            $this->handle_bulk_action($action);
            return;
        }

        $draft_id = absint($_POST['draft_id'] ?? 0);
        if ($draft_id <= 0) {
            $this->redirect(['draft_status' => 'missing']);
        }

        if ($action === 'reject') {
            $model = new SFFC_CRM_Job_Draft();
            $model->mark_rejected($draft_id);
            $this->redirect(['draft_status' => 'rejected']);
        }

        if ($action === 'approve') {
            $this->handle_approve($draft_id);
            return;
        }

        if ($action === 'update_location') {
            $this->handle_update_location($draft_id);
            return;
        }
    }

    private function handle_bulk_action($action) {
        $draft_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['draft_ids'] ?? [])))));
        if (empty($draft_ids)) {
            $this->redirect(['draft_status' => 'bulk_missing']);
        }

        $draft_model = new SFFC_CRM_Job_Draft();

        if ($action === 'bulk_delete') {
            $deleted = $draft_model->delete_many($draft_ids);
            $this->redirect([
                'draft_status' => 'bulk_deleted',
                'draft_count' => $deleted,
            ]);
        }

        if ($action === 'bulk_apply_status') {
            $target_status = sanitize_key((string) ($_POST['bulk_status'] ?? ''));
            if (in_array($target_status, ['approve_promote_senna', 'approve_promote'], true)) {
                $promoted = 0;
                $skipped = 0;
                $failed = 0;
                $rewrite_processor = $target_status === 'approve_promote' ? 'claude' : 'senna';

                foreach ($draft_ids as $draft_id) {
                    $result = $this->handle_approve($draft_id, false, [
                        'rewrite_before_publish' => true,
                        'rewrite_processor' => $rewrite_processor,
                        'publish_to_jobs' => true,
                        'exclude_from_early_bird' => false,
                    ]);

                    if (!empty($result['success'])) {
                        if (($result['reason'] ?? '') === 'already_promoted') {
                            $skipped++;
                        } else {
                            $promoted++;
                        }
                    } else {
                        $failed++;
                    }
                }

                $this->redirect([
                    'draft_status' => 'bulk_approved_promoted',
                    'draft_count' => $promoted,
                    'draft_skipped_count' => $skipped,
                    'draft_failed_count' => $failed,
                ]);
            }

            if (!in_array($target_status, $this->get_status_options(), true)) {
                $this->redirect(['draft_status' => 'bulk_status_missing']);
            }

            $updated = 0;
            foreach ($draft_ids as $draft_id) {
                if ($draft_model->update($draft_id, ['status' => $target_status])) {
                    $updated++;
                }
            }

            $this->redirect([
                'draft_status' => 'bulk_status_applied',
                'draft_count' => $updated,
                'applied_status' => $target_status,
            ]);
        }

        if ($action === 'bulk_apply_suggested_trackers') {
            $updated = 0;

            foreach ($draft_ids as $draft_id) {
                $draft = $draft_model->get($draft_id);
                if (!$draft) {
                    continue;
                }

                $suggested_group_ids = $this->limit_draft_group_ids_to_one($this->suggest_draft_group_ids($draft));
                if (empty($suggested_group_ids)) {
                    continue;
                }

                $payload = is_array($draft['rewritten_payload'] ?? null) ? $draft['rewritten_payload'] : [];
                $payload['post_group_ids'] = $suggested_group_ids;

                if ($draft_model->update($draft_id, ['rewritten_payload' => $payload])) {
                    $updated++;
                }
            }

            $this->redirect([
                'draft_status' => 'bulk_suggested_trackers_applied',
                'draft_count' => $updated,
            ]);
        }

        if ($action === 'bulk_update_posted_date') {
            $manual_date = sanitize_text_field((string) ($_POST['bulk_posted_date'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $manual_date)) {
                $this->redirect(['draft_status' => 'bulk_date_missing']);
            }

            $posted_at = $this->normalize_job_posted_at($manual_date . ' ' . wp_date('H:i:s', current_time('timestamp')));
            if ($posted_at === '') {
                $posted_at = $this->normalize_job_posted_at($manual_date . ' 12:00:00');
            }
            if ($posted_at === '') {
                $this->redirect(['draft_status' => 'bulk_date_missing']);
            }

            require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-post.php';

            $post_model = new SFFC_CRM_Post();
            $updated = 0;
            $crm_updated = 0;

            foreach ($draft_ids as $draft_id) {
                $draft = $draft_model->get($draft_id);
                if (!$draft) {
                    continue;
                }

                $payload = $this->get_draft_extracted_payload($draft);
                $payload['posted_at'] = $posted_at;
                $payload['raw_posted_at'] = $manual_date;
                if (isset($payload['normalized_payload']) && is_array($payload['normalized_payload'])) {
                    $payload['normalized_payload']['posted_at'] = $posted_at;
                }
                if (isset($payload['feed_job']) && is_array($payload['feed_job'])) {
                    $payload['feed_job']['posted_date'] = $manual_date;
                }

                if ($draft_model->update($draft_id, [
                    'posted_at' => $posted_at,
                    'raw_posted_at' => $manual_date,
                    'extracted_payload' => $payload,
                ])) {
                    $updated++;
                }

                $crm_post_id = (int) ($draft['approved_crm_post_id'] ?? 0);
                if ($crm_post_id > 0 && $post_model->update($crm_post_id, ['posted_at' => $posted_at]) !== false) {
                    $this->sync_promoted_wp_post_date($crm_post_id, $posted_at);
                    $crm_updated++;
                }
            }

            $this->redirect([
                'draft_status' => 'bulk_date_updated',
                'draft_count' => $updated,
                'crm_post_count' => $crm_updated,
            ]);
        }

        if ($action === 'bulk_update_location') {
            $manual_location = sanitize_text_field((string) ($_POST['bulk_location'] ?? ''));
            if ($manual_location === '') {
                $this->redirect(['draft_status' => 'bulk_location_missing']);
            }

            $updated = 0;
            $crm_updated = 0;

            foreach ($draft_ids as $draft_id) {
                $result = $this->update_draft_location($draft_id, $manual_location);
                if (!empty($result['draft_updated'])) {
                    $updated++;
                }
                if (!empty($result['crm_updated'])) {
                    $crm_updated++;
                }
            }

            $this->redirect([
                'draft_status' => 'bulk_location_updated',
                'draft_count' => $updated,
                'crm_post_count' => $crm_updated,
            ]);
        }

        $group_ids = $this->limit_draft_group_ids_to_one((array) ($_POST['bulk_post_groups'] ?? ($_POST['bulk_post_group'] ?? [])));
        if (empty($group_ids)) {
            $this->redirect(['draft_status' => 'bulk_groups_missing']);
        }

        require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-post-group.php';
        $updated = 0;
        $assigned_posts = 0;
        $group_model = new SFFC_CRM_Post_Group();

        foreach ($draft_ids as $draft_id) {
            $draft = $draft_model->get($draft_id);
            if (!$draft) {
                continue;
            }

            $payload = is_array($draft['rewritten_payload'] ?? null) ? $draft['rewritten_payload'] : [];
            $draft_group_ids = $this->limit_draft_group_ids_to_one($this->filter_draft_group_ids($group_ids, $draft));
            $payload['post_group_ids'] = $draft_group_ids;

            if ($draft_model->update($draft_id, ['rewritten_payload' => $payload])) {
                $updated++;
            }

            $crm_post_id = (int) ($draft['approved_crm_post_id'] ?? 0);
            if ($crm_post_id > 0 && $group_model) {
                $group_model->remove_all_groups($crm_post_id);
                foreach ($draft_group_ids as $group_id) {
                    $group_model->assign_post($crm_post_id, $group_id);
                }
                $assigned_posts++;
            }
        }

        $this->redirect([
            'draft_status' => 'bulk_trackers_added',
            'draft_count' => $updated,
            'crm_post_count' => $assigned_posts,
        ]);
    }

    private function handle_ingest() {
        $scanner = new SFFC_CRM_Job_Scanner();
        $model = new SFFC_CRM_Job_Draft();

        $scan = $scanner->scan([
            'source_url' => esc_url_raw($_POST['source_url'] ?? ''),
            'source_platform' => sanitize_text_field($_POST['source_platform'] ?? ''),
            'raw_content' => wp_unslash($_POST['raw_content'] ?? ''),
        ]);

        $manual_company_logo = esc_url_raw((string) ($_POST['company_logo'] ?? ''));
        $manual_seniority = $this->normalize_seniority_key($_POST['seniority'] ?? '');
        $manual_location = sanitize_text_field((string) ($_POST['location'] ?? ''));
        if ($manual_company_logo !== '') {
            $scan['raw_company_logo'] = $manual_company_logo;
        }
        if ($manual_seniority !== '') {
            $scan['raw_seniority'] = $manual_seniority;
        }
        if ($manual_location !== '') {
            $location_parts = $this->split_location($manual_location);
            $scan['raw_location'] = $manual_location;
            $scan['raw_location_city'] = $location_parts['city'];
            $scan['raw_location_country'] = $location_parts['country'];
        }
        if (empty($scan['raw_seniority'])) {
            $scan['raw_seniority'] = $this->detect_job_seniority($scan['raw_title'] ?? '', $scan['raw_content'] ?? '');
        }

        if (empty($scan['raw_content']) && empty($scan['raw_title'])) {
            $this->redirect(['draft_status' => 'scan_failed']);
        }

        $scan_posted_at = $this->normalize_job_posted_at($scan['posted_at'] ?? '');
        if ($scan_posted_at === '') {
            $scan_posted_at = current_time('mysql');
        }
        $scan['posted_at'] = $scan_posted_at;

        $draft_id = $model->create([
            'source_url' => $scan['source_url'] ?? '',
            'application_url' => $scan['application_url'] ?? '',
            'source_platform' => $scan['source_platform'] ?? '',
            'external_job_id' => $scan['external_job_id'] ?? '',
            'raw_title' => $scan['raw_title'] ?? '',
            'raw_company' => $scan['raw_company'] ?? '',
            'raw_location' => $scan['raw_location'] ?? '',
            'raw_location_city' => $scan['raw_location_city'] ?? '',
            'raw_location_country' => $scan['raw_location_country'] ?? '',
            'raw_salary_text' => $scan['raw_salary_text'] ?? '',
            'raw_company_logo' => $scan['raw_company_logo'] ?? '',
            'raw_sector' => $scan['raw_sector'] ?? '',
            'raw_seniority' => $scan['raw_seniority'] ?? '',
            'raw_experience_years' => $scan['raw_experience_years'] ?? '',
            'posted_at' => $scan_posted_at,
            'raw_posted_at' => $scan['raw_posted_at'] ?? '',
            'raw_content' => $scan['raw_content'] ?? '',
            'extracted_payload' => $scan,
            'confidence_score' => $scan['confidence_score'] ?? 0,
            'status' => $scan['status'] ?? 'new',
            'error_message' => $scan['error_message'] ?? '',
        ]);

        $this->redirect([
            'draft_status' => $draft_id ? 'created' : 'create_failed',
            'draft_id' => $draft_id ?: 0,
        ]);
    }

    private function handle_update_location($draft_id) {
        $manual_location = sanitize_text_field((string) ($_POST['location'] ?? ''));
        if ($manual_location === '') {
            $this->redirect(['draft_status' => 'location_missing', 'draft_id' => $draft_id]);
        }

        $result = $this->update_draft_location($draft_id, $manual_location);
        $this->redirect([
            'draft_status' => !empty($result['draft_updated']) ? 'location_updated' : 'location_update_failed',
            'draft_id' => $draft_id,
            'crm_post_count' => !empty($result['crm_updated']) ? 1 : 0,
        ]);
    }

    private function update_draft_location($draft_id, $location) {
        $draft_id = absint($draft_id);
        $location = sanitize_text_field((string) $location);
        if ($draft_id <= 0 || $location === '') {
            return [
                'draft_updated' => false,
                'crm_updated' => false,
            ];
        }

        $draft_model = new SFFC_CRM_Job_Draft();
        $draft = $draft_model->get($draft_id);
        if (!$draft) {
            return [
                'draft_updated' => false,
                'crm_updated' => false,
            ];
        }

        $location_parts = $this->split_location($location);
        $payload = $this->get_draft_extracted_payload($draft);
        $payload['raw_location'] = $location;
        $payload['location'] = $location;
        $payload['raw_location_city'] = $location_parts['city'];
        $payload['raw_location_country'] = $location_parts['country'];
        if (isset($payload['normalized_payload']) && is_array($payload['normalized_payload'])) {
            $payload['normalized_payload']['location'] = $location;
            $payload['normalized_payload']['location_city'] = $location_parts['city'];
            $payload['normalized_payload']['location_country'] = $location_parts['country'];
        }
        if (isset($payload['feed_job']) && is_array($payload['feed_job'])) {
            $payload['feed_job']['location'] = $location;
        }

        $update_data = [
            'raw_location' => $location,
            'raw_location_city' => $location_parts['city'],
            'raw_location_country' => $location_parts['country'],
            'extracted_payload' => $payload,
        ];

        $rewritten_payload = is_array($draft['rewritten_payload'] ?? null) ? $draft['rewritten_payload'] : [];
        if (!empty($rewritten_payload)) {
            $field_overrides = is_array($rewritten_payload['field_overrides'] ?? null) ? $rewritten_payload['field_overrides'] : [];
            $field_overrides['location'] = $location;
            $rewritten_payload['field_overrides'] = $field_overrides;
            $update_data['rewritten_payload'] = $rewritten_payload;
        }

        $draft_updated = $draft_model->update($draft_id, $update_data);
        $crm_updated = false;
        $crm_post_id = absint($draft['approved_crm_post_id'] ?? 0);
        if ($crm_post_id > 0) {
            require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-post.php';
            $post_model = new SFFC_CRM_Post();
            $crm_updated = $post_model->update($crm_post_id, [
                'location' => $location,
                'location_city' => $location_parts['city'],
                'location_country' => $location_parts['country'],
            ]) !== false;
        }

        return [
            'draft_updated' => (bool) $draft_updated,
            'crm_updated' => (bool) $crm_updated,
        ];
    }

    private function handle_approve($draft_id, $redirect = true, array $defaults = []) {
        $draft_model = new SFFC_CRM_Job_Draft();
        $draft = $draft_model->get($draft_id);
        if (!$draft) {
            if (!$redirect) {
                return [
                    'success' => false,
                    'reason' => 'missing',
                    'draft_id' => (int) $draft_id,
                ];
            }
            $this->redirect(['draft_status' => 'missing']);
        }

        if (!$redirect && !empty($draft['approved_crm_post_id'])) {
            return [
                'success' => true,
                'reason' => 'already_promoted',
                'draft_id' => (int) $draft_id,
                'crm_post_id' => (int) $draft['approved_crm_post_id'],
            ];
        }

        $content = (string) ($draft['raw_content'] ?? '');
        $rewrite_processor = sanitize_key((string) ($defaults['rewrite_processor'] ?? ($_POST['rewrite_processor'] ?? 'claude')));
        if (!in_array($rewrite_processor, ['senna', 'claude'], true)) {
            $rewrite_processor = 'claude';
        }
        if (!empty($_POST['rewrite_with_claude']) || (!empty($defaults['rewrite_with_claude']))) {
            $rewrite_processor = 'claude';
        }
        $should_rewrite = array_key_exists('rewrite_before_publish', $defaults)
            ? (bool) $defaults['rewrite_before_publish']
            : (array_key_exists('rewrite_with_claude', $defaults)
                ? (bool) $defaults['rewrite_with_claude']
                : (!array_key_exists('rewrite_before_publish', $_POST) || !empty($_POST['rewrite_before_publish']) || !empty($_POST['rewrite_with_claude'])));
        $field_overrides = [];

        if ($should_rewrite && $rewrite_processor === 'claude' && class_exists('SFFC_CRM_Admin')) {
            $field_overrides = SFFC_CRM_Admin::get_instance()->sffc_crm_infer_scanned_post_fields([
                'role_title' => $draft['raw_title'] ?? '',
                'company' => $draft['raw_company'] ?? '',
                'location' => $draft['raw_location'] ?? '',
                'sector' => $draft['raw_sector'] ?? '',
                'seniority' => $draft['raw_seniority'] ?? '',
                'experience_years' => $draft['raw_experience_years'] ?? '',
                'source_url' => $draft['source_url'] ?? '',
                'application_url' => $draft['application_url'] ?? '',
                'content' => $content,
            ], [
                'role_title' => $draft['raw_title'] ?? '',
                'company' => $draft['raw_company'] ?? '',
                'location' => $draft['raw_location'] ?? '',
                'sector' => $draft['raw_sector'] ?? '',
                'seniority' => $draft['raw_seniority'] ?? '',
                'experience_years' => $draft['raw_experience_years'] ?? '',
            ]);
        }

        if ($should_rewrite && class_exists('SFFC_CRM_Admin')) {
            $rewritten = SFFC_CRM_Admin::get_instance()->sffc_crm_rewrite_scanned_post_content([
                'role_title' => $field_overrides['role_title'] ?? ($draft['raw_title'] ?? ''),
                'company' => $field_overrides['company'] ?? ($draft['raw_company'] ?? ''),
                'location' => $field_overrides['location'] ?? ($draft['raw_location'] ?? ''),
                'sector' => $field_overrides['sector'] ?? ($draft['raw_sector'] ?? ''),
                'seniority' => $field_overrides['seniority'] ?? ($draft['raw_seniority'] ?? ''),
                'content' => $content,
            ], $rewrite_processor);

            if ($rewritten !== '') {
                $content = $rewritten;
            }
        }

        $draft_extracted_payload = $this->get_draft_extracted_payload($draft);
        $auto_submit_schema = is_array($draft_extracted_payload['auto_submit_schema'] ?? null) ? $draft_extracted_payload['auto_submit_schema'] : [];
        $auto_submit_provider = sanitize_key((string) ($draft_extracted_payload['auto_submit_provider'] ?? ($auto_submit_schema['provider'] ?? '')));
        $auto_submit_schema_status = sanitize_key((string) ($draft_extracted_payload['auto_submit_schema_status'] ?? ''));

        $promoted_posted_at = $this->resolve_draft_posted_at($draft);
        $saved_payload = is_array($draft['rewritten_payload'] ?? null) ? $draft['rewritten_payload'] : [];
        $saved_group_ids = $this->limit_draft_group_ids_to_one($this->filter_draft_group_ids((array) ($saved_payload['post_group_ids'] ?? []), $draft));
        $selected_group_ids = $this->limit_draft_group_ids_to_one((array) ($_POST['post_groups'] ?? ($_POST['post_group'] ?? [])));
        if (empty($selected_group_ids)) {
            $selected_group_ids = $saved_group_ids;
        }

        $posted_source_platform = sanitize_text_field((string) ($_POST['source_platform'] ?? ''));
        $posted_company_logo = esc_url_raw((string) ($_POST['company_logo'] ?? ''));
        $posted_seniority = $this->normalize_seniority_key($_POST['seniority'] ?? '');
        $posted_company = sanitize_text_field((string) ($_POST['company'] ?? ''));
        $posted_location = sanitize_text_field((string) ($_POST['location'] ?? ''));

        $submitted_source_platform = $posted_source_platform !== '' ? $posted_source_platform : sanitize_text_field((string) ($draft['source_platform'] ?? ''));
        $submitted_company_logo = $posted_company_logo !== '' ? $posted_company_logo : esc_url_raw((string) ($draft['raw_company_logo'] ?? ''));
        $submitted_seniority = $posted_seniority !== '' ? $posted_seniority : $this->normalize_seniority_key($field_overrides['seniority'] ?? ($draft['raw_seniority'] ?? ''));
        if ($submitted_seniority === '') {
            $submitted_seniority = $this->detect_job_seniority($field_overrides['role_title'] ?? ($draft['raw_title'] ?? ''), $content);
        }
        $submitted_company = $posted_company !== '' ? $posted_company : sanitize_text_field((string) ($field_overrides['company'] ?? ($draft['raw_company'] ?? '')));
        $promoted_location = $posted_location !== '' ? $posted_location : sanitize_text_field((string) ($field_overrides['location'] ?? ($draft['raw_location'] ?? '')));
        $location_parts = $this->split_location($promoted_location);
        $approval_role_title = $field_overrides['role_title'] ?? ($draft['raw_title'] ?? '');
        $final_title_cleanup = $this->get_title_cleanup_for_value($approval_role_title, $submitted_company, $promoted_location);
        $submitted_role_title = sanitize_text_field((string) ($final_title_cleanup['title'] ?? $approval_role_title));
        if ($submitted_company_logo !== '' && class_exists('SFFC_CRM_Admin')) {
            $submitted_company_logo = SFFC_CRM_Admin::get_instance()->sffc_crm_cache_company_logo_url(
                $submitted_company_logo,
                $submitted_company,
                (string) ($draft['source_url'] ?: ($draft['application_url'] ?? ''))
            );
        }

        $post_data = [
            'recruiter_id' => 0,
            'role_title' => $submitted_role_title,
            'company' => $submitted_company,
            'location' => $promoted_location,
            'location_city' => sanitize_text_field($location_parts['city']),
            'location_country' => sanitize_text_field($location_parts['country']),
            'sector' => sanitize_text_field($field_overrides['sector'] ?? ($draft['raw_sector'] ?? '')),
            'seniority' => $submitted_seniority,
            'experience_years' => sanitize_text_field($field_overrides['experience_years'] ?? ($draft['raw_experience_years'] ?? '')),
            'salary_text' => sanitize_text_field($draft['raw_salary_text'] ?? ''),
            'content' => wp_kses_post($content),
            'source_url' => esc_url_raw($draft['source_url'] ?? ''),
            'source_id' => $this->build_promoted_source_id($draft),
            'application_url' => esc_url_raw($draft['application_url'] ?? ''),
            'admin_approved' => 1,
            'publish_to_jobs' => array_key_exists('publish_to_jobs', $defaults)
                ? ((bool) $defaults['publish_to_jobs'] ? 1 : 0)
                : (!empty($_POST['publish_to_jobs']) ? 1 : 0),
            'is_active' => 1,
            'is_featured' => 0,
            'is_early_bird' => 0,
            'exclude_from_early_bird' => array_key_exists('exclude_from_early_bird', $defaults)
                ? ((bool) $defaults['exclude_from_early_bird'] ? 1 : 0)
                : (!empty($_POST['exclude_from_early_bird']) ? 1 : 0),
            'source' => 'import',
            'company_logo' => $submitted_company_logo,
            'source_platform' => $submitted_source_platform,
            'post_status' => 'open',
            'posted_at' => $promoted_posted_at,
        ];

        if (empty($selected_group_ids)) {
            require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-post-group.php';
            $group_model = new SFFC_CRM_Post_Group();
            $selected_group_ids = $this->limit_draft_group_ids_to_one($group_model->suggest_groups_for_post($post_data, 1, 42, [
                'strict_location_match' => true,
            ]));
        } else {
            require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-post-group.php';
            $group_model = new SFFC_CRM_Post_Group();
            $selected_group_ids = $this->limit_draft_group_ids_to_one($group_model->filter_group_ids_for_post($selected_group_ids, $post_data, [
                'strict_location_match' => true,
                'min_score' => 42,
            ]));
        }

        if ($post_data['role_title'] === '' || trim(wp_strip_all_tags($post_data['content'])) === '') {
            if (!$redirect) {
                return [
                    'success' => false,
                    'reason' => 'incomplete',
                    'draft_id' => (int) $draft_id,
                ];
            }
            $this->redirect(['draft_status' => 'approve_incomplete', 'draft_id' => $draft_id]);
        }

        $post_model = new SFFC_CRM_Post();
        $crm_post_id = $post_model->create($post_data);
        if (!$crm_post_id) {
            if (!$redirect) {
                return [
                    'success' => false,
                    'reason' => 'create_failed',
                    'draft_id' => (int) $draft_id,
                ];
            }
            $this->redirect(['draft_status' => 'approve_failed', 'draft_id' => $draft_id]);
        }

        if (!empty($auto_submit_schema)) {
            $post_data['auto_submit_provider'] = $auto_submit_provider !== '' ? $auto_submit_provider : 'greenhouse';
            $post_data['auto_submit_supported'] = 1;
            $post_data['auto_submit_schema_status'] = $auto_submit_schema_status !== '' ? $auto_submit_schema_status : 'discovered';
            $post_data['auto_submit_schema'] = $auto_submit_schema;
        }

        if (class_exists('SFFC_CRM_Admin')) {
            SFFC_CRM_Admin::get_instance()->sffc_crm_finalize_promoted_job_draft((int) $crm_post_id, $post_data, $selected_group_ids);
        }

        $approval_payload = $draft_extracted_payload;
        $approval_payload['raw_company'] = $submitted_company;
        $approval_payload['company'] = $submitted_company;
        $approval_payload['raw_location'] = $promoted_location;
        $approval_payload['location'] = $promoted_location;
        $approval_payload['raw_location_city'] = $location_parts['city'];
        $approval_payload['raw_location_country'] = $location_parts['country'];
        $approval_payload['raw_seniority'] = $submitted_seniority;
        $approval_payload['seniority'] = $submitted_seniority;
        if ($submitted_company_logo !== '') {
            $approval_payload['raw_company_logo'] = $submitted_company_logo;
            $approval_payload['company_logo'] = $submitted_company_logo;
        }
        if (isset($approval_payload['normalized_payload']) && is_array($approval_payload['normalized_payload'])) {
            $approval_payload['normalized_payload']['company'] = $submitted_company;
            $approval_payload['normalized_payload']['location'] = $promoted_location;
            $approval_payload['normalized_payload']['location_city'] = $location_parts['city'];
            $approval_payload['normalized_payload']['location_country'] = $location_parts['country'];
            $approval_payload['normalized_payload']['seniority'] = $submitted_seniority;
        }
        if (isset($approval_payload['feed_job']) && is_array($approval_payload['feed_job'])) {
            $approval_payload['feed_job']['company'] = $submitted_company;
            $approval_payload['feed_job']['location'] = $promoted_location;
        }

        $draft_model->update($draft_id, [
            'source_platform' => $submitted_source_platform,
            'raw_company_logo' => $submitted_company_logo,
            'raw_seniority' => $submitted_seniority,
            'raw_company' => $submitted_company,
            'raw_location' => $promoted_location,
            'raw_location_city' => sanitize_text_field($location_parts['city']),
            'raw_location_country' => sanitize_text_field($location_parts['country']),
            'extracted_payload' => $approval_payload,
            'rewritten_payload' => [
                'content' => $content,
                'crm_post_id' => (int) $crm_post_id,
                'field_overrides' => array_merge($field_overrides, [
                    'company' => $submitted_company,
                    'location' => $promoted_location,
                    'seniority' => $submitted_seniority,
                ]),
                'title_cleanup' => $final_title_cleanup,
                'rewritten_with_claude' => $should_rewrite,
                'post_group_ids' => $selected_group_ids,
            ],
        ]);
        $draft_model->mark_approved($draft_id, (int) $crm_post_id);

        if (!$redirect) {
            return [
                'success' => true,
                'draft_id' => (int) $draft_id,
                'crm_post_id' => (int) $crm_post_id,
            ];
        }

        $this->redirect([
            'draft_status' => 'approved',
            'draft_id' => $draft_id,
            'crm_post_id' => (int) $crm_post_id,
        ]);
    }

    private function resolve_draft_posted_at(array $draft) {
        $extracted = $this->get_draft_extracted_payload($draft);
        $candidates = [
            $draft['posted_at'] ?? '',
            $extracted['posted_at'] ?? '',
            $extracted['normalized_payload']['posted_at'] ?? '',
            $extracted['raw_posted_at'] ?? '',
            $draft['raw_posted_at'] ?? '',
            $extracted['feed_job']['posted_date'] ?? '',
        ];

        foreach ($candidates as $candidate) {
            $candidate = $this->normalize_job_posted_at($candidate);
            if ($candidate !== '') {
                return $this->shuffle_promoted_posted_at($candidate, $draft);
            }
        }

        return $this->shuffle_promoted_posted_at(current_time('mysql'), $draft);
    }

    private function shuffle_promoted_posted_at($posted_at, array $draft) {
        $normalized_posted_at = $this->normalize_job_posted_at($posted_at);
        $source_timestamp = $normalized_posted_at !== '' ? strtotime($normalized_posted_at) : 0;
        $now = current_time('timestamp');

        if (!$source_timestamp || $source_timestamp > $now) {
            $source_timestamp = $now;
        }

        $age_seconds = max(0, $now - $source_timestamp);
        $instant_window = 48 * HOUR_IN_SECONDS;
        $seed = $this->build_draft_shuffle_seed($draft);

        if ($age_seconds <= $instant_window) {
            $max_offset = max(1, $instant_window - MINUTE_IN_SECONDS);
            $offset = $seed % $max_offset;

            return wp_date('Y-m-d H:i:s', $now - $offset);
        }

        $bucket_start = max($instant_window + HOUR_IN_SECONDS, (int) floor($age_seconds / DAY_IN_SECONDS) * DAY_IN_SECONDS);
        $bucket_span = max(HOUR_IN_SECONDS, DAY_IN_SECONDS - MINUTE_IN_SECONDS);
        $offset = $bucket_start + ($seed % $bucket_span);

        return wp_date('Y-m-d H:i:s', max(0, $now - $offset));
    }

    private function normalize_job_posted_at($value) {
        $value = trim(sanitize_text_field((string) $value));
        if ($value === '' || preg_match('/^\d{4}$/', $value) || preg_match('/^0{4}-0{2}-0{2}/', $value)) {
            return '';
        }

        $timestamp = strtotime($value);
        if (!$timestamp || $timestamp <= 0) {
            return '';
        }

        $now = current_time('timestamp');
        if ($timestamp > ($now + DAY_IN_SECONDS) || $timestamp < strtotime('2000-01-01 00:00:00')) {
            return '';
        }

        return wp_date('Y-m-d H:i:s', $timestamp);
    }

    private function sync_promoted_wp_post_date($crm_post_id, $posted_at) {
        global $wpdb;

        $crm_post_id = absint($crm_post_id);
        $posted_at = $this->normalize_job_posted_at($posted_at);
        if ($crm_post_id <= 0 || $posted_at === '') {
            return;
        }

        $table = $wpdb->prefix . 'sffc_crm_posts';
        $columns = $wpdb->get_col("DESCRIBE {$table}", 0);
        if (empty($columns)) {
            return;
        }

        $wp_post_column = '';
        foreach (['wp_post_id', 'jobs_post_id'] as $column) {
            if (in_array($column, $columns, true)) {
                $wp_post_column = $column;
                break;
            }
        }

        if ($wp_post_column === '') {
            return;
        }

        $wp_post_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT {$wp_post_column} FROM {$table} WHERE id = %d",
            $crm_post_id
        ));
        if ($wp_post_id <= 0 || get_post_status($wp_post_id) === false) {
            return;
        }

        wp_update_post([
            'ID' => $wp_post_id,
            'post_date' => $posted_at,
            'post_date_gmt' => get_gmt_from_date($posted_at),
        ]);
    }

    private function build_draft_shuffle_seed(array $draft) {
        $seed_parts = [
            $draft['id'] ?? '',
            $draft['source_hash'] ?? '',
            $draft['external_job_id'] ?? '',
            $draft['source_url'] ?? '',
            $draft['application_url'] ?? '',
            $draft['raw_title'] ?? '',
            $draft['raw_company'] ?? '',
        ];
        $seed_input = implode('|', array_map('strval', array_filter($seed_parts, static function ($part) {
            return $part !== null && $part !== '';
        })));

        if ($seed_input === '') {
            $seed_input = wp_json_encode($draft);
        }

        return (int) hexdec(substr(hash('sha256', (string) $seed_input), 0, 8));
    }

    private function get_title_cleanup_for_value($title, $company = '', $location = '') {
        if (!class_exists('SFFC_CRM_Job_Title_Normalizer') && defined('SFFC_PLUGIN_DIR') && file_exists(SFFC_PLUGIN_DIR . 'includes/class-crm-job-title-normalizer.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-crm-job-title-normalizer.php';
        }

        if (class_exists('SFFC_CRM_Job_Title_Normalizer')) {
            return SFFC_CRM_Job_Title_Normalizer::normalize($title, $company, $location);
        }

        $title = sanitize_text_field((string) $title);
        return [
            'original_title' => $title,
            'title' => $title,
            'changed' => false,
            'cleanup_score' => 0,
        ];
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $model = new SFFC_CRM_Job_Draft();
        $status = sanitize_key((string) ($_GET['status'] ?? ''));
        $search = sanitize_text_field((string) ($_GET['s'] ?? ''));
        $queue_filter = sanitize_key((string) ($_GET['queue_filter'] ?? ''));
        $sort = sanitize_key((string) ($_GET['sort'] ?? 'posted_desc'));
        $page_size = max(20, min(200, absint($_GET['page_size'] ?? 80)));
        $paged = max(1, absint($_GET['paged'] ?? 1));
        $status_options = $this->get_status_options();
        $filter_options = $this->get_queue_filter_options();
        $sort_options = $this->get_sort_options();
        $query_args = [
            'status' => $status,
            'search' => $search,
            'queue_filter' => $queue_filter,
        ];
        $total_drafts = $model->count($query_args);
        $total_pages = max(1, (int) ceil($total_drafts / $page_size));
        $paged = min($paged, $total_pages);
        $offset = ($paged - 1) * $page_size;
        $drafts = $model->query([
            'status' => $status,
            'search' => $search,
            'queue_filter' => $queue_filter,
            'sort' => $sort,
            'limit' => $page_size,
            'offset' => $offset,
        ]);
        $groups = $this->get_groups();

        ?>
        <div class="wrap sffc-crm-job-scanner">
            <h1><?php esc_html_e('Job Scanner', 'senna-finance'); ?></h1>
            <p style="max-width: 900px; color: #475569;">
                <?php esc_html_e('Ingest raw roles into a draft queue first. Only approved drafts are rewritten, promoted into CRM posts, assigned to trackers, and mirrored to the jobs post type.', 'senna-finance'); ?>
            </p>
            <style>
                .sffc-crm-job-scanner .sffc-crm-job-draft-status-badge {
                    display: inline-flex;
                    align-items: center;
                    min-height: 24px;
                    padding: 3px 10px;
                    border-radius: 999px;
                    font-size: 11px;
                    font-weight: 800;
                    line-height: 1.2;
                    letter-spacing: .04em;
                    text-transform: uppercase;
                }

                .sffc-crm-job-scanner .sffc-crm-job-draft-status-badge.is-approved {
                    background: #dcfce7;
                    border: 1px solid #86efac;
                    color: #166534;
                }

                .sffc-crm-job-scanner .sffc-crm-job-draft-status-badge.is-rejected,
                .sffc-crm-job-scanner .sffc-crm-job-draft-status-badge.is-failed {
                    background: #fee2e2;
                    border: 1px solid #fca5a5;
                    color: #991b1b;
                }

                .sffc-crm-job-scanner .sffc-crm-job-draft-status-badge.is-duplicate {
                    background: #fef3c7;
                    border: 1px solid #fcd34d;
                    color: #92400e;
                }

                .sffc-crm-job-scanner .sffc-crm-job-draft-status-badge.is-new {
                    background: #eff6ff;
                    border: 1px solid #bfdbfe;
                    color: #1d4ed8;
                }

                .sffc-crm-job-scanner .sffc-crm-job-draft-status-badge.is-default {
                    background: #f1f5f9;
                    border: 1px solid #cbd5e1;
                    color: #334155;
                }

                .sffc-crm-job-scanner .sffc-crm-job-embed-status-badge {
                    display: inline-flex;
                    align-items: center;
                    min-height: 24px;
                    padding: 3px 10px;
                    border-radius: 999px;
                    font-size: 11px;
                    font-weight: 800;
                    line-height: 1.2;
                    letter-spacing: .04em;
                    text-transform: uppercase;
                    background: #f1f5f9;
                    border: 1px solid #cbd5e1;
                    color: #334155;
                }

                .sffc-crm-job-scanner .sffc-crm-job-embed-status-badge.is-embeddable,
                .sffc-crm-job-scanner .sffc-crm-job-embed-status-badge.is-likely {
                    background: #dcfce7;
                    border-color: #86efac;
                    color: #166534;
                }

                .sffc-crm-job-scanner .sffc-crm-job-embed-status-badge.is-blocked {
                    background: #fee2e2;
                    border-color: #fca5a5;
                    color: #991b1b;
                }

                .sffc-crm-job-scanner .sffc-crm-job-embed-status-badge.is-unknown {
                    background: #fef3c7;
                    border-color: #fcd34d;
                    color: #92400e;
                }
            </style>

            <?php $this->render_notice(); ?>

            <div class="postbox" style="margin-top:18px;">
                <div class="postbox-header">
                    <h2><?php esc_html_e('Scan a role', 'senna-finance'); ?></h2>
                </div>
                <div class="inside">
                    <form method="post">
                        <?php wp_nonce_field('sffc_crm_job_draft_ingest'); ?>
                        <input type="hidden" name="sffc_crm_job_draft_action" value="ingest">
                        <table class="form-table">
                            <tr>
                                <th><label for="source_url"><?php esc_html_e('Job URL', 'senna-finance'); ?></label></th>
                                <td>
                                    <input type="url" name="source_url" id="source_url" class="large-text" placeholder="https://company.com/careers/job">
                                    <p class="description"><?php esc_html_e('Best on company careers pages and ATS pages. LinkedIn/Indeed may require pasted text if blocked.', 'senna-finance'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="source_platform"><?php esc_html_e('Source Platform', 'senna-finance'); ?></label></th>
                                <td>
                                    <input type="text" name="source_platform" id="source_platform" class="regular-text" placeholder="<?php esc_attr_e('Company Website, LinkedIn, Greenhouse, Lever', 'senna-finance'); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th><label for="location"><?php esc_html_e('Location', 'senna-finance'); ?></label></th>
                                <td>
                                    <input type="text" name="location" id="location" class="regular-text" placeholder="<?php esc_attr_e('Dubai, United Arab Emirates', 'senna-finance'); ?>">
                                    <p class="description"><?php esc_html_e('Optional. Use this when the scanner cannot detect the correct location from the source.', 'senna-finance'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="company_logo"><?php esc_html_e('Company Logo', 'senna-finance'); ?></label></th>
                                <td>
                                    <input type="url" name="company_logo" id="company_logo" class="large-text" placeholder="https://company.com/logo.png">
                                    <p class="description"><?php esc_html_e('Optional. If the scanner finds a logo, this can be left blank.', 'senna-finance'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="seniority"><?php esc_html_e('Seniority', 'senna-finance'); ?></label></th>
                                <td>
                                    <select name="seniority" id="seniority">
                                        <option value=""><?php esc_html_e('Auto-detect', 'senna-finance'); ?></option>
                                        <?php foreach ($this->get_seniority_options() as $value => $label) : ?>
                                            <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php esc_html_e('Leave as Auto-detect unless the source page is ambiguous.', 'senna-finance'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="raw_content"><?php esc_html_e('Paste Raw Job Text', 'senna-finance'); ?></label></th>
                                <td>
                                    <textarea name="raw_content" id="raw_content" rows="8" class="large-text" placeholder="<?php esc_attr_e('Paste the raw job description or recruiter post here.', 'senna-finance'); ?>"></textarea>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" class="button button-primary button-large"><?php esc_html_e('Scan into Draft Queue', 'senna-finance'); ?></button>
                        </p>
                    </form>
                </div>
            </div>

            <form method="get" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin:18px 0;">
                <input type="hidden" name="page" value="sffc-crm-job-scanner">
                <select name="status">
                    <option value=""><?php esc_html_e('Active queue', 'senna-finance'); ?></option>
                    <?php foreach ($status_options as $option) : ?>
                        <option value="<?php echo esc_attr($option); ?>" <?php selected($status, $option); ?>><?php echo esc_html(ucfirst($option)); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="queue_filter">
                    <option value=""><?php esc_html_e('All drafts', 'senna-finance'); ?></option>
                    <?php foreach ($filter_options as $option => $label) : ?>
                        <option value="<?php echo esc_attr($option); ?>" <?php selected($queue_filter, $option); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="sort">
                    <?php foreach ($sort_options as $option => $label) : ?>
                        <option value="<?php echo esc_attr($option); ?>" <?php selected($sort, $option); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="page_size">
                    <?php foreach ([20, 50, 80, 100, 200] as $option) : ?>
                        <option value="<?php echo esc_attr($option); ?>" <?php selected($page_size, $option); ?>><?php echo esc_html(sprintf(__('%d per page', 'senna-finance'), $option)); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search drafts', 'senna-finance'); ?>">
                <button class="button"><?php esc_html_e('Filter', 'senna-finance'); ?></button>
            </form>
            <p style="display:flex; gap:8px; flex-wrap:wrap; margin:0 0 12px;">
                <?php foreach ($filter_options as $option => $label) : ?>
                    <?php
                    $filter_url = add_query_arg([
                        'page' => 'sffc-crm-job-scanner',
                        'queue_filter' => $option,
                        'status' => $status,
                        'sort' => $sort,
                        'page_size' => $page_size,
                    ], admin_url('admin.php'));
                    ?>
                    <a class="button <?php echo $queue_filter === $option ? 'button-primary' : ''; ?>" href="<?php echo esc_url($filter_url); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </p>

            <form method="post" id="sffc-crm-job-draft-bulk-form" style="display:flex; gap:12px; align-items:flex-start; flex-wrap:wrap; margin:18px 0; padding:12px; background:#fff; border:1px solid #dcdcde;">
                <?php wp_nonce_field('sffc_crm_job_draft_bulk'); ?>
                <input type="hidden" name="sffc_crm_job_draft_action" id="sffc-crm-job-draft-bulk-action" value="bulk_add_trackers">
                <label style="padding-top:6px;">
                    <input type="checkbox" id="sffc-crm-job-draft-select-all">
                    <?php esc_html_e('Select all visible drafts', 'senna-finance'); ?>
                </label>
                <span id="sffc-crm-job-draft-selected-count" style="padding-top:7px; color:#475569;"><?php esc_html_e('0 drafts selected', 'senna-finance'); ?></span>
                <div style="display:flex; gap:8px; align-items:center; padding-top:1px;">
                    <label for="sffc-crm-job-draft-bulk-status" class="screen-reader-text"><?php esc_html_e('Apply status', 'senna-finance'); ?></label>
                    <select name="bulk_status" id="sffc-crm-job-draft-bulk-status">
                        <option value=""><?php esc_html_e('Apply status', 'senna-finance'); ?></option>
                        <option value="approve_promote"><?php esc_html_e('Approve + Promote to CRM Posts', 'senna-finance'); ?></option>
                        <option value="approve_promote_senna"><?php esc_html_e('Approve + route to CRM with Senna', 'senna-finance'); ?></option>
                        <?php foreach ($status_options as $option) : ?>
                            <option value="<?php echo esc_attr($option); ?>">
                                <?php echo esc_html($option === 'approved' ? __('Approved status only', 'senna-finance') : ucfirst($option)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button" onclick="document.getElementById('sffc-crm-job-draft-bulk-action').value='bulk_apply_status';"><?php esc_html_e('Apply', 'senna-finance'); ?></button>
                </div>
                <div style="display:flex; gap:8px; align-items:center; padding-top:1px;">
                    <label for="sffc-crm-job-draft-bulk-posted-date" style="font-weight:600;"><?php esc_html_e('Posted date', 'senna-finance'); ?></label>
                    <input type="date" name="bulk_posted_date" id="sffc-crm-job-draft-bulk-posted-date">
                    <button type="submit" class="button" onclick="document.getElementById('sffc-crm-job-draft-bulk-action').value='bulk_update_posted_date';"><?php esc_html_e('Update dates', 'senna-finance'); ?></button>
                </div>
                <div style="display:flex; gap:8px; align-items:center; padding-top:1px;">
                    <label for="sffc-crm-job-draft-bulk-location" style="font-weight:600;"><?php esc_html_e('Location', 'senna-finance'); ?></label>
                    <input type="text" name="bulk_location" id="sffc-crm-job-draft-bulk-location" class="regular-text" placeholder="<?php esc_attr_e('London, United Kingdom', 'senna-finance'); ?>">
                    <button type="submit" class="button" onclick="document.getElementById('sffc-crm-job-draft-bulk-action').value='bulk_update_location';"><?php esc_html_e('Update locations', 'senna-finance'); ?></button>
                </div>
                <div>
                    <strong><?php esc_html_e('Bulk add to trackers', 'senna-finance'); ?></strong>
                    <div style="max-height:120px; min-width:280px; overflow:auto; margin-top:6px; padding:8px; border:1px solid #dbe2ea; background:#fff;">
                        <?php foreach ($groups as $group) : ?>
                            <label style="display:block; margin-bottom:6px;">
                                <input type="radio" name="bulk_post_group" value="<?php echo esc_attr($group['id']); ?>">
                                <?php echo esc_html($group['name']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <p style="margin:0; padding-top:24px;">
                    <button type="submit" class="button" onclick="document.getElementById('sffc-crm-job-draft-bulk-action').value='bulk_apply_suggested_trackers';"><?php esc_html_e('Apply Suggested Trackers', 'senna-finance'); ?></button>
                    <button type="submit" class="button button-primary" onclick="document.getElementById('sffc-crm-job-draft-bulk-action').value='bulk_add_trackers';"><?php esc_html_e('Add Trackers to Selected', 'senna-finance'); ?></button>
                    <button type="submit" class="button button-link-delete" onclick="document.getElementById('sffc-crm-job-draft-bulk-action').value='bulk_delete'; return confirm('<?php echo esc_js(__('Remove all selected draft records? This cannot be undone.', 'senna-finance')); ?>');"><?php esc_html_e('Remove All Selected Drafts', 'senna-finance'); ?></button>
                </p>
            </form>

            <p style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin:0 0 12px;">
                <span style="color:#475569;"><?php echo esc_html(sprintf(_n('%d draft found', '%d drafts found', $total_drafts, 'senna-finance'), $total_drafts)); ?></span>
                <button type="button" class="button" id="sffc-crm-job-draft-expand-all"><?php esc_html_e('Expand all', 'senna-finance'); ?></button>
                <button type="button" class="button" id="sffc-crm-job-draft-collapse-all"><?php esc_html_e('Collapse all', 'senna-finance'); ?></button>
            </p>

            <div class="sffc-crm-job-draft-list">
                <?php if (empty($drafts)) : ?>
                    <div class="notice notice-info inline"><p><?php esc_html_e('No job drafts found yet.', 'senna-finance'); ?></p></div>
                <?php endif; ?>
                <?php foreach ($drafts as $draft) : ?>
                    <?php $this->render_draft_card($draft, $groups); ?>
                <?php endforeach; ?>
            </div>
            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom" style="margin-top:14px;">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo esc_html(sprintf(__('Page %1$d of %2$d', 'senna-finance'), $paged, $total_pages)); ?></span>
                        <?php
                        $base_args = [
                            'page' => 'sffc-crm-job-scanner',
                            'status' => $status,
                            'queue_filter' => $queue_filter,
                            'sort' => $sort,
                            'page_size' => $page_size,
                            's' => $search,
                        ];
                        ?>
                        <?php if ($paged > 1) : ?>
                            <a class="button" href="<?php echo esc_url(add_query_arg(array_merge($base_args, ['paged' => $paged - 1]), admin_url('admin.php'))); ?>"><?php esc_html_e('Previous', 'senna-finance'); ?></a>
                        <?php endif; ?>
                        <?php if ($paged < $total_pages) : ?>
                            <a class="button" href="<?php echo esc_url(add_query_arg(array_merge($base_args, ['paged' => $paged + 1]), admin_url('admin.php'))); ?>"><?php esc_html_e('Next', 'senna-finance'); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var selectAll = document.getElementById('sffc-crm-job-draft-select-all');
                    if (!selectAll) {
                        return;
                    }

                    var countNode = document.getElementById('sffc-crm-job-draft-selected-count');
                    var bulkForm = document.getElementById('sffc-crm-job-draft-bulk-form');
                    var bulkAction = document.getElementById('sffc-crm-job-draft-bulk-action');
                    var bulkStatus = document.getElementById('sffc-crm-job-draft-bulk-status');
                    var expandAll = document.getElementById('sffc-crm-job-draft-expand-all');
                    var collapseAll = document.getElementById('sffc-crm-job-draft-collapse-all');
                    var checkboxes = Array.prototype.slice.call(document.querySelectorAll('.sffc-crm-job-draft-select'));
                    var cards = Array.prototype.slice.call(document.querySelectorAll('.sffc-crm-job-draft-card'));

                    function updateSelectedCount() {
                        var selected = checkboxes.filter(function(checkbox) {
                            return checkbox.checked;
                        }).length;

                        if (countNode) {
                            countNode.textContent = selected + (selected === 1 ? ' draft selected' : ' drafts selected');
                        }

                        selectAll.checked = selected > 0 && selected === checkboxes.length;
                        selectAll.indeterminate = selected > 0 && selected < checkboxes.length;
                    }

                    selectAll.addEventListener('change', function() {
                        checkboxes.forEach(function(checkbox) {
                            checkbox.checked = selectAll.checked;
                        });
                        updateSelectedCount();
                    });

                    checkboxes.forEach(function(checkbox) {
                        checkbox.addEventListener('change', updateSelectedCount);
                    });

                    if (bulkStatus && bulkAction) {
                        bulkStatus.addEventListener('change', function() {
                            if (bulkStatus.value) {
                                bulkAction.value = 'bulk_apply_status';
                            }
                        });
                    }

                    if (bulkForm) {
                        bulkForm.addEventListener('submit', function(event) {
                            if (!checkboxes.some(function(checkbox) { return checkbox.checked; })) {
                                event.preventDefault();
                                alert('<?php echo esc_js(__('Select at least one draft first.', 'senna-finance')); ?>');
                                return;
                            }

                            if (bulkAction && bulkAction.value === 'bulk_apply_status' && bulkStatus && !bulkStatus.value) {
                                event.preventDefault();
                                alert('<?php echo esc_js(__('Choose a status to apply first.', 'senna-finance')); ?>');
                                return;
                            }

                            var bulkLocation = document.getElementById('sffc-crm-job-draft-bulk-location');
                            if (bulkAction && bulkAction.value === 'bulk_update_location' && bulkLocation && !bulkLocation.value.trim()) {
                                event.preventDefault();
                                alert('<?php echo esc_js(__('Enter a location before updating selected drafts.', 'senna-finance')); ?>');
                            }
                        });
                    }

                    function setCardExpanded(card, expanded) {
                        card.classList.toggle('is-collapsed', !expanded);
                        var body = card.querySelector('.sffc-crm-job-draft-card-body');
                        var toggle = card.querySelector('.sffc-crm-job-draft-toggle');

                        if (body) {
                            body.hidden = !expanded;
                            body.style.display = expanded ? 'grid' : 'none';
                        }

                        if (toggle) {
                            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                            toggle.textContent = expanded
                                ? '<?php echo esc_js(__('Collapse', 'senna-finance')); ?>'
                                : '<?php echo esc_js(__('Expand', 'senna-finance')); ?>';
                        }
                    }

                    cards.forEach(function(card) {
                        setCardExpanded(card, false);
                        var toggle = card.querySelector('.sffc-crm-job-draft-toggle');
                        if (toggle) {
                            toggle.addEventListener('click', function() {
                                setCardExpanded(card, toggle.getAttribute('aria-expanded') !== 'true');
                            });
                        }
                    });

                    if (expandAll) {
                        expandAll.addEventListener('click', function() {
                            cards.forEach(function(card) {
                                setCardExpanded(card, true);
                            });
                        });
                    }

                    if (collapseAll) {
                        collapseAll.addEventListener('click', function() {
                            cards.forEach(function(card) {
                                setCardExpanded(card, false);
                            });
                        });
                    }

                    updateSelectedCount();
                });
            </script>
        </div>
        <?php
    }

    private function render_draft_card(array $draft, array $groups) {
        $status = sanitize_key((string) ($draft['status'] ?? 'new'));
        $status_badge = $this->get_draft_status_badge($status);
        $confidence = (float) ($draft['confidence_score'] ?? 0);
        $saved_payload = is_array($draft['rewritten_payload'] ?? null) ? $draft['rewritten_payload'] : [];
        $saved_group_ids = $this->limit_draft_group_ids_to_one($this->filter_draft_group_ids((array) ($saved_payload['post_group_ids'] ?? []), $draft));
        $suggested_group_ids = empty($saved_group_ids) ? $this->suggest_draft_group_ids($draft) : [];
        $suggested_group_ids = $this->limit_draft_group_ids_to_one($suggested_group_ids);
        $checked_group_ids = $this->limit_draft_group_ids_to_one(!empty($saved_group_ids) ? $saved_group_ids : $suggested_group_ids);
        $detected_seniority = $this->normalize_seniority_key($draft['raw_seniority'] ?? '');
        if ($detected_seniority === '') {
            $detected_seniority = $this->detect_job_seniority($draft['raw_title'] ?? '', $draft['raw_content'] ?? '');
        }
        $original_title = $this->get_draft_original_title($draft);
        $title_cleanup = $this->get_draft_title_cleanup($draft);
        $posted_label = $this->get_draft_posted_label($draft);
        $clean_title = sanitize_text_field((string) ($draft['raw_title'] ?? ''));
        $title_was_cleaned = $original_title !== '' && strcasecmp($original_title, $clean_title) !== 0;
        $cleanup_score = (int) ($title_cleanup['cleanup_score'] ?? 0);
        $cleanup_signals = $this->format_title_cleanup_signals($title_cleanup);
        $warning_badges = $this->get_draft_warning_badges($draft, $saved_group_ids, $suggested_group_ids);
        $audit_label = $this->get_draft_audit_label($draft);
        $embed_url = trim((string) ($draft['application_url'] ?? ''));
        if ($embed_url === '') {
            $embed_url = trim((string) ($draft['source_url'] ?? ''));
        }
        $embed_badge = class_exists('SFFC_CRM_Admin')
            ? SFFC_CRM_Admin::get_embed_test_result($embed_url, (string) ($draft['source_platform'] ?? ''))
            : [
                'label' => __('Embed unknown', 'senna-finance'),
                'class' => 'is-unknown',
                'detail' => __('No reliable embed signal was detected yet.', 'senna-finance'),
            ];
        $card_body_id = 'sffc-crm-job-draft-card-body-' . absint($draft['id'] ?? 0);
        $card_border = $this->get_draft_card_border_style($status, !empty($warning_badges));
        $card_status_class = sanitize_html_class('sffc-crm-job-draft-card--' . ($status !== '' ? $status : 'new'));
        ?>
        <div class="postbox sffc-crm-job-draft-card <?php echo esc_attr($card_status_class); ?>" style="margin-bottom:16px; <?php echo esc_attr($card_border); ?>">
            <div class="inside" style="padding-bottom:10px;">
                <div style="display:flex; gap:10px; align-items:flex-start; justify-content:space-between;">
                    <div style="min-width:0;">
                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin:0 0 8px;">
                            <input type="checkbox" form="sffc-crm-job-draft-bulk-form" name="draft_ids[]" value="<?php echo esc_attr($draft['id']); ?>" class="sffc-crm-job-draft-select" style="margin-right:8px;">
                            <span class="sffc-crm-job-draft-status-badge <?php echo esc_attr($status_badge['class']); ?>"><?php echo esc_html($status_badge['label']); ?></span>
                            <span class="sffc-crm-job-embed-status-badge <?php echo esc_attr($embed_badge['class'] ?? 'is-unknown'); ?>" title="<?php echo esc_attr($embed_badge['detail'] ?? ''); ?>"><?php echo esc_html($embed_badge['label'] ?? __('Embed unknown', 'senna-finance')); ?></span>
                            <?php if (!empty($warning_badges)) : ?>
                                <?php foreach ($warning_badges as $badge) : ?>
                                    <span style="display:inline-flex; align-items:center; min-height:22px; padding:3px 9px; border:1px solid <?php echo esc_attr($badge['border']); ?>; border-radius:999px; background:<?php echo esc_attr($badge['background']); ?>; color:<?php echo esc_attr($badge['color']); ?>; font-size:11px; line-height:1.2; font-weight:700; text-transform:uppercase;"><?php echo esc_html($badge['label']); ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if ($title_was_cleaned) : ?>
                                <span style="display:inline-flex; align-items:center; gap:6px; padding:4px 8px; border:1px solid #bfdbfe; border-radius:999px; background:#eff6ff; color:#1d4ed8; font-size:12px; font-weight:700; text-transform:uppercase;">
                                    <?php echo esc_html(sprintf(__('Cleaned title · %d%%', 'senna-finance'), $cleanup_score)); ?>
                                </span>
                            <?php endif; ?>
                            <strong><?php echo esc_html($clean_title ?: __('Untitled role', 'senna-finance')); ?></strong>
                        </div>
                        <p style="margin:0; color:#475569;">
                            <?php echo esc_html(implode(' · ', array_filter([
                                $draft['raw_company'] ?? '',
                                $draft['raw_location'] ?? '',
                                $draft['raw_sector'] ?? '',
                                $draft['raw_seniority'] ?? '',
                                $posted_label,
                            ]))); ?>
                        </p>
                    </div>
                    <button type="button" class="button sffc-crm-job-draft-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr($card_body_id); ?>">
                        <?php esc_html_e('Expand', 'senna-finance'); ?>
                    </button>
                </div>
            </div>
            <div class="inside sffc-crm-job-draft-card-body" id="<?php echo esc_attr($card_body_id); ?>" style="display:grid; grid-template-columns: 1fr 320px; gap:18px; border-top:1px solid #dcdcde;">
                <div>
                    <?php if ($title_was_cleaned) : ?>
                        <p style="margin:0 0 8px; color:#64748b;">
                            <span style="font-weight:600;"><?php esc_html_e('Original:', 'senna-finance'); ?></span>
                            <?php echo esc_html($original_title); ?>
                        </p>
                        <?php if (!empty($cleanup_signals)) : ?>
                            <p style="margin:0 0 8px; display:flex; gap:6px; flex-wrap:wrap;">
                                <?php foreach ($cleanup_signals as $signal) : ?>
                                    <span style="display:inline-flex; padding:3px 7px; border-radius:999px; background:#f1f5f9; color:#475569; font-size:11px;"><?php echo esc_html($signal); ?></span>
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <p style="margin:0 0 8px; color:#64748b;">
                        <?php printf(esc_html__('Confidence: %s%%', 'senna-finance'), esc_html(number_format_i18n($confidence, 0))); ?>
                        <?php if (!empty($draft['duplicate_of'])) : ?>
                            <?php printf(esc_html__(' · Possible duplicate of draft #%d', 'senna-finance'), (int) $draft['duplicate_of']); ?>
                        <?php endif; ?>
                    </p>
                    <?php if ($audit_label !== '') : ?>
                        <p style="margin:0 0 8px; color:#64748b;"><?php echo esc_html($audit_label); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($draft['error_message'])) : ?>
                        <p style="margin:0 0 8px; color:#92400e;"><?php echo esc_html($draft['error_message']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($draft['source_url'])) : ?>
                        <p style="margin:0 0 8px;"><a href="<?php echo esc_url($draft['source_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($draft['source_url']); ?></a></p>
                    <?php endif; ?>
                    <details>
                        <summary><?php esc_html_e('Raw source preview', 'senna-finance'); ?></summary>
                        <div style="max-height:260px; overflow:auto; margin-top:10px; padding:12px; border:1px solid #dbe2ea; background:#f8fafc; white-space:pre-wrap;"><?php echo esc_html(wp_trim_words(wp_strip_all_tags((string) ($draft['raw_content'] ?? '')), 260, '...')); ?></div>
                    </details>
                </div>
                <div>
                    <form method="post" style="margin-bottom:12px; padding:10px; border:1px solid #dbe2ea; background:#f8fafc;">
                        <?php wp_nonce_field('sffc_crm_job_draft_update_location'); ?>
                        <input type="hidden" name="sffc_crm_job_draft_action" value="update_location">
                        <input type="hidden" name="draft_id" value="<?php echo esc_attr($draft['id']); ?>">
                        <label for="draft_location_save_<?php echo esc_attr($draft['id']); ?>"><strong><?php esc_html_e('Location', 'senna-finance'); ?></strong></label>
                        <input type="text" id="draft_location_save_<?php echo esc_attr($draft['id']); ?>" name="location" class="widefat" value="<?php echo esc_attr($draft['raw_location'] ?? ''); ?>" placeholder="<?php esc_attr_e('Dubai, United Arab Emirates', 'senna-finance'); ?>" style="margin-top:6px;">
                        <button type="submit" class="button" style="margin-top:8px;"><?php esc_html_e('Save location', 'senna-finance'); ?></button>
                    </form>

                    <?php if ($status !== 'approved' || empty($draft['approved_crm_post_id'])) : ?>
                        <form method="post" style="margin-bottom:10px;">
                            <?php wp_nonce_field('sffc_crm_job_draft_approve'); ?>
                            <input type="hidden" name="sffc_crm_job_draft_action" value="approve">
                            <input type="hidden" name="draft_id" value="<?php echo esc_attr($draft['id']); ?>">
                            <p>
                                <input type="hidden" name="rewrite_before_publish" value="0">
                                <label><input type="checkbox" name="rewrite_before_publish" value="1" checked> <?php esc_html_e('Rewrite before publishing', 'senna-finance'); ?></label>
                                <span style="display:block; margin-top:4px; color:#64748b;"><?php esc_html_e('Claude is the default rewrite processor. Use the Senna approval button only when you want the deterministic parser.', 'senna-finance'); ?></span>
                            </p>
                            <p>
                                <label><input type="checkbox" name="publish_to_jobs" value="1" checked> <?php esc_html_e('Mirror to jobs post type', 'senna-finance'); ?></label>
                            </p>
                            <p>
                                <label><input type="checkbox" name="exclude_from_early_bird" value="1"> <?php esc_html_e('Exclude from Live Instant Posts', 'senna-finance'); ?></label>
                            </p>
                            <p>
                                <label for="draft_source_platform_<?php echo esc_attr($draft['id']); ?>"><strong><?php esc_html_e('Source', 'senna-finance'); ?></strong></label><br>
                                <input type="text" id="draft_source_platform_<?php echo esc_attr($draft['id']); ?>" name="source_platform" class="widefat" value="<?php echo esc_attr($draft['source_platform'] ?? ''); ?>" placeholder="<?php esc_attr_e('Workday, Greenhouse, LinkedIn, Company Website', 'senna-finance'); ?>">
                            </p>
                            <p>
                                <label for="draft_company_<?php echo esc_attr($draft['id']); ?>"><strong><?php esc_html_e('Company', 'senna-finance'); ?></strong></label><br>
                                <input type="text" id="draft_company_<?php echo esc_attr($draft['id']); ?>" name="company" class="widefat" value="<?php echo esc_attr($draft['raw_company'] ?? ''); ?>">
                            </p>
                            <p>
                                <label for="draft_location_<?php echo esc_attr($draft['id']); ?>"><strong><?php esc_html_e('Location', 'senna-finance'); ?></strong></label><br>
                                <input type="text" id="draft_location_<?php echo esc_attr($draft['id']); ?>" name="location" class="widefat" value="<?php echo esc_attr($draft['raw_location'] ?? ''); ?>" placeholder="<?php esc_attr_e('London, United Kingdom', 'senna-finance'); ?>">
                                <span style="display:block; margin-top:4px; color:#64748b;"><?php esc_html_e('This controls tracker matching and the promoted CRM post location.', 'senna-finance'); ?></span>
                            </p>
                            <p>
                                <label for="draft_company_logo_<?php echo esc_attr($draft['id']); ?>"><strong><?php esc_html_e('Company Logo', 'senna-finance'); ?></strong></label><br>
                                <input type="url" id="draft_company_logo_<?php echo esc_attr($draft['id']); ?>" name="company_logo" class="widefat" value="<?php echo esc_attr($draft['raw_company_logo'] ?? ''); ?>" placeholder="https://company.com/logo.png">
                            </p>
                            <p>
                                <label for="draft_seniority_<?php echo esc_attr($draft['id']); ?>"><strong><?php esc_html_e('Seniority', 'senna-finance'); ?></strong></label><br>
                                <select id="draft_seniority_<?php echo esc_attr($draft['id']); ?>" name="seniority" class="widefat">
                                    <option value=""><?php esc_html_e('-- Select --', 'senna-finance'); ?></option>
                                    <?php foreach ($this->get_seniority_options() as $value => $label) : ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected($detected_seniority, $value); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </p>
                            <p><strong><?php esc_html_e('Trackers', 'senna-finance'); ?></strong></p>
                            <?php if (!empty($suggested_group_ids)) : ?>
                                <p style="margin:4px 0 8px; color:#047857; font-size:12px;">
                                    <?php esc_html_e('Suggested trackers are pre-selected from matching title, company, location, sector, seniority, and raw source text. Location-specific trackers require a compatible role location.', 'senna-finance'); ?>
                                </p>
                            <?php endif; ?>
                            <div style="max-height:130px; overflow:auto; border:1px solid #dbe2ea; padding:8px; background:#fff;">
                                <?php foreach ($groups as $group) : ?>
                                    <label style="display:block; margin-bottom:6px;">
                                        <input type="radio" name="post_group" value="<?php echo esc_attr($group['id']); ?>" <?php checked(in_array((int) $group['id'], $checked_group_ids, true)); ?>>
                                        <?php echo esc_html($group['name']); ?>
                                        <?php if (in_array((int) $group['id'], $suggested_group_ids, true)) : ?>
                                            <span style="color:#047857; font-size:11px;"><?php esc_html_e('Suggested', 'senna-finance'); ?></span>
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                <button type="submit" name="rewrite_processor" value="claude" class="button button-primary"><?php esc_html_e('Approve + promote to CRM posts', 'senna-finance'); ?></button>
                                <button type="submit" name="rewrite_processor" value="senna" class="button"><?php esc_html_e('Approve + route to CRM with Senna', 'senna-finance'); ?></button>
                            </p>
                        </form>
                    <?php elseif (!empty($draft['approved_crm_post_id'])) : ?>
                        <p><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=sffc-crm-add-post&id=' . absint($draft['approved_crm_post_id']))); ?>"><?php esc_html_e('Edit CRM Post', 'senna-finance'); ?></a></p>
                    <?php endif; ?>

                    <?php if (!in_array($status, ['approved', 'rejected'], true)) : ?>
                        <form method="post">
                            <?php wp_nonce_field('sffc_crm_job_draft_reject'); ?>
                            <input type="hidden" name="sffc_crm_job_draft_action" value="reject">
                            <input type="hidden" name="draft_id" value="<?php echo esc_attr($draft['id']); ?>">
                            <button type="submit" class="button button-secondary"><?php esc_html_e('Reject Draft', 'senna-finance'); ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function get_draft_status_badge($status) {
        $status = sanitize_key((string) $status);
        $badges = [
            'approved' => [
                'label' => __('Approved', 'senna-finance'),
                'class' => 'is-approved',
            ],
            'rejected' => [
                'label' => __('Rejected', 'senna-finance'),
                'class' => 'is-rejected',
            ],
            'failed' => [
                'label' => __('Failed', 'senna-finance'),
                'class' => 'is-failed',
            ],
            'duplicate' => [
                'label' => __('Duplicate', 'senna-finance'),
                'class' => 'is-duplicate',
            ],
            'new' => [
                'label' => __('New', 'senna-finance'),
                'class' => 'is-new',
            ],
        ];

        if (isset($badges[$status])) {
            return $badges[$status];
        }

        return [
            'label' => ucwords(str_replace('_', ' ', $status !== '' ? $status : 'new')),
            'class' => 'is-default',
        ];
    }

    private function get_draft_card_border_style($status, $has_warnings = false) {
        $status = sanitize_key((string) $status);

        if ($status === 'approved') {
            return 'border-left:4px solid #16a34a;';
        }

        if (in_array($status, ['rejected', 'failed'], true)) {
            return 'border-left:4px solid #dc2626;';
        }

        return $has_warnings ? 'border-left:4px solid #d97706;' : '';
    }

    private function get_draft_extracted_payload(array $draft) {
        $payload = $draft['extracted_payload'] ?? [];

        if (is_array($payload)) {
            return $payload;
        }

        if (is_string($payload) && $payload !== '') {
            $decoded = json_decode($payload, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function get_draft_posted_label(array $draft) {
        $posted_at = sanitize_text_field((string) ($draft['posted_at'] ?? ''));
        $normalized_posted_at = $this->normalize_job_posted_at($posted_at);

        if ($normalized_posted_at !== '') {
            $timestamp = strtotime($normalized_posted_at);
            return sprintf(__('Posted %s ago', 'senna-finance'), human_time_diff($timestamp, current_time('timestamp')));
        }

        if ($posted_at !== '' && !preg_match('/^0{4}-0{2}-0{2}/', $posted_at) && !preg_match('/^\d{4}$/', $posted_at)) {
            return sprintf(__('Posted %s', 'senna-finance'), $posted_at);
        }

        $extracted = $this->get_draft_extracted_payload($draft);
        $raw = sanitize_text_field((string) (
            $draft['raw_posted_at']
            ?? ($extracted['raw_posted_at']
            ?? ($extracted['feed_job']['posted_date'] ?? ''))
        ));

        if ($raw === '') {
            return '';
        }

        return sprintf(__('Posted %s', 'senna-finance'), $raw);
    }

    private function get_draft_original_title(array $draft) {
        $extracted = $this->get_draft_extracted_payload($draft);
        $candidates = [
            $extracted['title_cleanup']['original_title'] ?? '',
            $extracted['original_title'] ?? '',
            $extracted['feed_job']['title'] ?? '',
            $extracted['normalized_payload']['original_title'] ?? '',
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                return sanitize_text_field((string) $candidate);
            }
        }

        return '';
    }

    private function get_draft_title_cleanup(array $draft) {
        $extracted = $this->get_draft_extracted_payload($draft);
        if (is_array($extracted['title_cleanup'] ?? null)) {
            return $extracted['title_cleanup'];
        }
        if (is_array($extracted['normalized_payload']['title_cleanup'] ?? null)) {
            return $extracted['normalized_payload']['title_cleanup'];
        }

        return [];
    }

    private function format_title_cleanup_signals(array $cleanup) {
        $signals = [];
        $map = [
            'removed_company' => __('Removed company', 'senna-finance'),
            'removed_location' => __('Removed location', 'senna-finance'),
            'nationality_requirement' => __('Nationality', 'senna-finance'),
            'language_requirements' => __('Language', 'senna-finance'),
            'work_mode' => __('Work mode', 'senna-finance'),
            'employment_type' => __('Employment', 'senna-finance'),
            'salary_text' => __('Salary', 'senna-finance'),
            'programme_type' => __('Programme', 'senna-finance'),
            'intake_year' => __('Intake', 'senna-finance'),
        ];

        foreach ($map as $key => $label) {
            $value = $cleanup[$key] ?? '';
            $values = is_array($value) ? array_values(array_filter(array_map('strval', $value))) : array_filter([(string) $value]);
            if (empty($values)) {
                continue;
            }
            $signals[] = $label . ': ' . implode(', ', array_slice($values, 0, 3));
        }

        return array_slice($signals, 0, 8);
    }

    private function get_draft_warning_badges(array $draft, array $saved_group_ids, array $suggested_group_ids) {
        $badges = [];
        $confidence = (float) ($draft['confidence_score'] ?? 0);

        if (trim((string) ($draft['raw_company'] ?? '')) === '') {
            $badges[] = $this->format_warning_badge(__('Missing Company', 'senna-finance'), 'critical');
        }
        if (trim((string) ($draft['raw_location'] ?? '')) === '') {
            $badges[] = $this->format_warning_badge(__('Missing Location', 'senna-finance'), 'critical');
        }
        if (trim((string) ($draft['application_url'] ?? '')) === '') {
            $badges[] = $this->format_warning_badge(__('No Application Link', 'senna-finance'), 'warning');
        }
        if ($confidence > 0 && $confidence < 60) {
            $badges[] = $this->format_warning_badge(__('Low Confidence', 'senna-finance'), 'quality');
        }
        if (!empty($draft['duplicate_of']) || sanitize_key((string) ($draft['status'] ?? '')) === 'duplicate') {
            $badges[] = $this->format_warning_badge(__('Possible Duplicate', 'senna-finance'), 'duplicate');
        }
        if (trim((string) ($draft['error_message'] ?? '')) !== '') {
            $badges[] = $this->format_warning_badge(__('Source Issue', 'senna-finance'), 'source');
        }
        if (empty($saved_group_ids) && empty($suggested_group_ids)) {
            $badges[] = $this->format_warning_badge(__('No Tracker Match', 'senna-finance'), 'tracker');
        }

        return array_slice($badges, 0, 6);
    }

    private function get_draft_audit_label(array $draft) {
        $parts = [];

        if (!empty($draft['created_at'])) {
            $parts[] = sprintf(__('Scanned %s', 'senna-finance'), $this->format_admin_datetime($draft['created_at']));
        }
        if (!empty($draft['created_by'])) {
            $user = get_userdata((int) $draft['created_by']);
            if ($user) {
                $parts[] = sprintf(__('by %s', 'senna-finance'), $user->display_name);
            }
        }
        if (!empty($draft['approved_at'])) {
            $parts[] = sprintf(__('approved %s', 'senna-finance'), $this->format_admin_datetime($draft['approved_at']));
        }
        if (!empty($draft['rejected_at'])) {
            $parts[] = sprintf(__('rejected %s', 'senna-finance'), $this->format_admin_datetime($draft['rejected_at']));
        }

        return implode(' · ', array_filter($parts));
    }

    private function format_admin_datetime($value) {
        $timestamp = strtotime((string) $value);
        if (!$timestamp) {
            return sanitize_text_field((string) $value);
        }

        return sprintf(__('%s ago', 'senna-finance'), human_time_diff($timestamp, current_time('timestamp')));
    }

    private function format_warning_badge($label, $tone = 'warning') {
        $palette = [
            'critical' => ['background' => '#fee2e2', 'border' => '#fca5a5', 'color' => '#991b1b'],
            'warning' => ['background' => '#fef3c7', 'border' => '#fcd34d', 'color' => '#92400e'],
            'quality' => ['background' => '#ffedd5', 'border' => '#fdba74', 'color' => '#9a3412'],
            'duplicate' => ['background' => '#f3e8ff', 'border' => '#d8b4fe', 'color' => '#6b21a8'],
            'source' => ['background' => '#e0f2fe', 'border' => '#7dd3fc', 'color' => '#075985'],
            'tracker' => ['background' => '#dcfce7', 'border' => '#86efac', 'color' => '#166534'],
        ];
        $colors = $palette[$tone] ?? $palette['warning'];

        return [
            'label' => $label,
            'background' => $colors['background'],
            'border' => $colors['border'],
            'color' => $colors['color'],
        ];
    }

    private function get_status_options() {
        return ['new', 'duplicate', 'approved', 'rejected', 'failed'];
    }

    private function get_queue_filter_options() {
        return [
            'duplicates' => __('Duplicates', 'senna-finance'),
            'low_confidence' => __('Low confidence', 'senna-finance'),
            'missing_application' => __('Missing application URL', 'senna-finance'),
            'missing_company' => __('Missing company', 'senna-finance'),
            'missing_location' => __('Missing location', 'senna-finance'),
            'unassigned_tracker' => __('Unassigned to tracker', 'senna-finance'),
            'recent_7_days' => __('Recent 7 days', 'senna-finance'),
        ];
    }

    private function get_sort_options() {
        return [
            'posted_desc' => __('Newest posted', 'senna-finance'),
            'created_desc' => __('Newest scanned', 'senna-finance'),
            'confidence_desc' => __('Highest confidence', 'senna-finance'),
            'company_asc' => __('Company A-Z', 'senna-finance'),
            'status_asc' => __('Status A-Z', 'senna-finance'),
        ];
    }

    private function get_seniority_options() {
        return [
            'intern' => __('Intern / Graduate', 'senna-finance'),
            'analyst' => __('Analyst', 'senna-finance'),
            'senior_analyst' => __('Senior Analyst', 'senna-finance'),
            'associate' => __('Associate / Manager', 'senna-finance'),
            'senior_associate' => __('Senior Associate / Senior Manager', 'senna-finance'),
            'vp' => __('Associate Director / AVP', 'senna-finance'),
            'senior_vp' => __('VP / SVP / EVP', 'senna-finance'),
            'director' => __('Director / Head of', 'senna-finance'),
            'md' => __('Managing Director / General Manager', 'senna-finance'),
            'partner' => __('Partner', 'senna-finance'),
            'c_level' => __('C-Suite', 'senna-finance'),
            'board' => __('Board Member / Chairman', 'senna-finance'),
            'other' => __('Other', 'senna-finance'),
        ];
    }

    private function normalize_seniority_key($value) {
        $value = sanitize_key((string) $value);
        return array_key_exists($value, $this->get_seniority_options()) ? $value : '';
    }

    private function detect_job_seniority($title, $description = '') {
        $title_text = $this->normalize_seniority_text($title);
        $text = $this->normalize_seniority_text(trim((string) $title . ' ' . (string) $description));

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
            if ($title_text !== '' && preg_match($pattern, $title_text)) {
                return $seniority;
            }
        }

        $years = $this->detect_seniority_years($text);
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

    private function normalize_seniority_text($value) {
        $value = html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES, 'UTF-8');
        $value = strtolower(str_replace(['–', '—', '/', '&'], ['-', '-', ' ', ' and '], $value));
        $value = preg_replace('/[^a-z0-9\+\.\-\s]/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim((string) $value);
    }

    private function detect_seniority_years($text) {
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

    private function render_notice() {
        $status = sanitize_key((string) ($_GET['draft_status'] ?? ''));
        if ($status === '') {
            return;
        }

        $messages = [
            'created' => __('Draft created from scanned source.', 'senna-finance'),
            'create_failed' => __('Could not create the job draft.', 'senna-finance'),
            'scan_failed' => __('The scanner could not extract useful job content.', 'senna-finance'),
            'approved' => __('Draft approved and promoted to CRM posts.', 'senna-finance'),
            'approve_failed' => __('Could not promote this draft.', 'senna-finance'),
            'approve_incomplete' => __('This draft needs at least a role title and content before approval.', 'senna-finance'),
            'rejected' => __('Draft rejected.', 'senna-finance'),
            'missing' => __('Draft not found.', 'senna-finance'),
            'bulk_missing' => __('Select at least one draft first.', 'senna-finance'),
            'bulk_groups_missing' => __('Choose at least one tracker before applying the bulk tracker action.', 'senna-finance'),
            'bulk_status_missing' => __('Choose a status before applying the bulk status action.', 'senna-finance'),
            'bulk_date_missing' => __('Choose a valid posted date before updating selected drafts.', 'senna-finance'),
            'bulk_location_missing' => __('Enter a location before updating selected drafts.', 'senna-finance'),
            'bulk_deleted' => __('Selected drafts deleted.', 'senna-finance'),
            'bulk_trackers_added' => __('Selected drafts were added to the chosen trackers.', 'senna-finance'),
            'bulk_suggested_trackers_applied' => __('Suggested trackers were applied to selected drafts where matches were found.', 'senna-finance'),
            'bulk_status_applied' => __('Selected drafts were updated to the chosen status.', 'senna-finance'),
            'bulk_approved_promoted' => __('Selected drafts were approved and promoted to CRM posts.', 'senna-finance'),
            'bulk_date_updated' => __('Selected draft and CRM post dates were updated.', 'senna-finance'),
            'bulk_location_updated' => __('Selected draft and CRM post locations were updated.', 'senna-finance'),
            'location_missing' => __('Enter a location before saving this draft.', 'senna-finance'),
            'location_updated' => __('Draft location updated.', 'senna-finance'),
            'location_update_failed' => __('Could not update this draft location.', 'senna-finance'),
        ];

        if (empty($messages[$status])) {
            return;
        }

        if ($status === 'bulk_approved_promoted') {
            $promoted_count = max(0, intval($_GET['draft_count'] ?? 0));
            $skipped_count = max(0, intval($_GET['draft_skipped_count'] ?? 0));
            $failed_count = max(0, intval($_GET['draft_failed_count'] ?? 0));
            $messages[$status] = sprintf(
                __('%1$d drafts promoted to CRM posts. %2$d already promoted. %3$d failed.', 'senna-finance'),
                $promoted_count,
                $skipped_count,
                $failed_count
            );
        }

        if ($status === 'bulk_date_updated') {
            $draft_count = max(0, intval($_GET['draft_count'] ?? 0));
            $crm_post_count = max(0, intval($_GET['crm_post_count'] ?? 0));
            $messages[$status] = sprintf(
                __('%1$d draft dates updated. %2$d linked CRM posts updated.', 'senna-finance'),
                $draft_count,
                $crm_post_count
            );
        }

        if ($status === 'bulk_location_updated') {
            $draft_count = max(0, intval($_GET['draft_count'] ?? 0));
            $crm_post_count = max(0, intval($_GET['crm_post_count'] ?? 0));
            $messages[$status] = sprintf(
                __('%1$d draft locations updated. %2$d linked CRM posts updated.', 'senna-finance'),
                $draft_count,
                $crm_post_count
            );
        }

        $class = in_array($status, ['created', 'approved', 'rejected'], true) ? 'notice-success' : 'notice-error';
        if (in_array($status, ['bulk_deleted', 'bulk_trackers_added', 'bulk_suggested_trackers_applied', 'bulk_status_applied', 'bulk_approved_promoted', 'bulk_date_updated', 'bulk_location_updated', 'location_updated'], true)) {
            $class = 'notice-success';
        }
        echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($messages[$status]) . '</p></div>';
    }

    private function get_groups() {
        require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-post-group.php';
        $group_model = new SFFC_CRM_Post_Group();
        return $group_model->get_all([
            'is_active' => 1,
            'include_post_count' => false,
        ]);
    }

    private function suggest_draft_group_ids(array $draft) {
        require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-post-group.php';
        $group_model = new SFFC_CRM_Post_Group();

        return $this->limit_draft_group_ids_to_one($group_model->suggest_groups_for_post([
            'role_title' => $draft['raw_title'] ?? '',
            'company' => $draft['raw_company'] ?? '',
            'location' => $draft['raw_location'] ?? '',
            'location_city' => $draft['raw_location_city'] ?? '',
            'location_country' => $draft['raw_location_country'] ?? '',
            'sector' => $draft['raw_sector'] ?? '',
            'seniority' => $draft['raw_seniority'] ?? '',
            'content' => $draft['raw_content'] ?? '',
            'source_platform' => $draft['source_platform'] ?? '',
        ], 1, 42, [
            'strict_location_match' => true,
        ]));
    }

    private function filter_draft_group_ids(array $group_ids, array $draft) {
        if (empty($group_ids)) {
            return [];
        }

        require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-post-group.php';
        $group_model = new SFFC_CRM_Post_Group();

        return $this->limit_draft_group_ids_to_one($group_model->filter_group_ids_for_post($group_ids, [
            'role_title' => $draft['raw_title'] ?? '',
            'company' => $draft['raw_company'] ?? '',
            'location' => $draft['raw_location'] ?? '',
            'location_city' => $draft['raw_location_city'] ?? '',
            'location_country' => $draft['raw_location_country'] ?? '',
            'sector' => $draft['raw_sector'] ?? '',
            'seniority' => $draft['raw_seniority'] ?? '',
            'content' => $draft['raw_content'] ?? '',
            'source_platform' => $draft['source_platform'] ?? '',
        ], [
            'strict_location_match' => true,
            'min_score' => 42,
        ]));
    }

    private function limit_draft_group_ids_to_one(array $group_ids) {
        $group_ids = array_values(array_unique(array_filter(array_map('intval', $group_ids))));

        return array_slice($group_ids, 0, 1);
    }

    private function split_location($location) {
        $parts = array_values(array_filter(array_map('trim', explode(',', (string) $location))));
        if (empty($parts)) {
            return [
                'city' => '',
                'country' => '',
            ];
        }

        return [
            'city' => sanitize_text_field((string) ($parts[0] ?? '')),
            'country' => sanitize_text_field((string) ($parts[count($parts) - 1] ?? '')),
        ];
    }

    private function build_promoted_source_id(array $draft) {
        $source_id = sanitize_text_field((string) ($draft['external_job_id'] ?: ($draft['source_hash'] ?? '')));
        if ($source_id === '') {
            $source_id = hash('sha256', implode('|', [
                $draft['source_url'] ?? '',
                $draft['raw_title'] ?? '',
                $draft['raw_company'] ?? '',
                $draft['raw_location'] ?? '',
            ]));
        }

        return substr('draft-' . absint($draft['id'] ?? 0) . '-' . $source_id, 0, 100);
    }

    private function redirect(array $args = []) {
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php?page=sffc-crm-job-scanner')));
        exit;
    }
}

SFFC_CRM_Job_Draft_Admin::get_instance();
