<?php
/**
 * CRM Job Preferences Helper Functions
 *
 * Manages user job matching preferences stored in WordPress user meta
 *
 * @package SennaCareers
 * @since 7.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get all job preferences for a user
 *
 * @param int $user_id User ID
 * @return array Job preferences
 */
function sffc_crm_get_job_preferences($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return [];
    }

    return [
        'target_roles' => get_user_meta($user_id, 'sffc_crm_target_roles', true) ?: [],
        'target_sectors' => get_user_meta($user_id, 'sffc_crm_target_sectors', true) ?: [],
        'target_seniority' => get_user_meta($user_id, 'sffc_crm_target_seniority', true) ?: [],
        'target_locations' => get_user_meta($user_id, 'sffc_crm_target_locations', true) ?: [],
        'target_countries' => get_user_meta($user_id, 'sffc_crm_target_countries', true) ?: [],
        'salary_min' => get_user_meta($user_id, 'sffc_crm_salary_min', true) ?: 0,
        'salary_max' => get_user_meta($user_id, 'sffc_crm_salary_max', true) ?: 0,
        'salary_currency' => get_user_meta($user_id, 'sffc_crm_salary_currency', true) ?: 'GBP',
        'work_arrangement' => get_user_meta($user_id, 'sffc_crm_work_arrangement', true) ?: [],
        'internship_duration' => get_user_meta($user_id, 'sffc_crm_internship_duration', true) ?: '',
        'start_date_pref' => get_user_meta($user_id, 'sffc_crm_start_date_pref', true) ?: '',
        'university' => get_user_meta($user_id, 'sffc_crm_university', true) ?: '',
    ];
}

/**
 * Save job preferences for a user
 *
 * @param int $user_id User ID
 * @param array $preferences Preferences to save
 * @return bool Success
 */
function sffc_crm_save_job_preferences($user_id, $preferences) {
    if (!$user_id || !is_array($preferences)) {
        return false;
    }

    $allowed_keys = [
        'target_roles',
        'target_sectors',
        'target_seniority',
        'target_locations',
        'target_countries',
        'salary_min',
        'salary_max',
        'salary_currency',
        'work_arrangement',
        'internship_duration',
        'start_date_pref',
        'university',
    ];

    foreach ($preferences as $key => $value) {
        if (in_array($key, $allowed_keys)) {
            update_user_meta($user_id, 'sffc_crm_' . $key, $value);
        }
    }

    return true;
}

function sffc_crm_normalize_text_list($values, $limit = 24) {
    if (!is_array($values)) {
        $values = preg_split('/[\r\n,]+/', (string) $values);
    }

    $normalized = [];
    foreach ((array) $values as $value) {
        $value = sanitize_text_field(trim((string) $value));
        if ($value === '') {
            continue;
        }

        $lookup = function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
        if (isset($normalized[$lookup])) {
            continue;
        }

        $normalized[$lookup] = $value;
        if (count($normalized) >= max(1, (int) $limit)) {
            break;
        }
    }

    return array_values($normalized);
}

function sffc_crm_get_premium_search_profile_defaults() {
    return [
        'enabled' => false,
        'membership_tier' => 'free',
        'subscription_status' => '',
        'subscription_amount' => 0,
        'target_roles' => [],
        'target_sectors' => [],
        'target_locations' => [],
        'target_seniority' => [],
        'target_skills' => [],
        'work_modes' => [],
        'excluded_roles' => [],
        'excluded_locations' => [],
        'excluded_sectors' => [],
        'visa_status' => '',
        'delivery_frequency' => 'daily',
        'preferred_email' => '',
        'active_cv_upload_id' => 0,
        'cv_source' => '',
        'cv_text_hash' => '',
        'last_role_title' => '',
        'preferred_location' => '',
        'updated_at' => '',
        'source' => 'none',
    ];
}

function sffc_crm_user_has_paid_job_match_access($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    if (user_can($user_id, 'manage_options')) {
        return true;
    }

    if (!class_exists('SFFC_MemberPress_Integration')) {
        return false;
    }

    $memberpress = SFFC_MemberPress_Integration::get_instance();
    if (!$memberpress || !method_exists($memberpress, 'get_user_subscriptions')) {
        return false;
    }

    $subscriptions = (array) $memberpress->get_user_subscriptions($user_id);
    foreach ($subscriptions as $subscription) {
        $status = sanitize_key((string) ($subscription['status'] ?? ''));
        $amount = (float) ($subscription['total'] ?? $subscription['price'] ?? 0);
        if (in_array($status, ['active', 'confirmed', 'complete', 'trial', 'pending'], true) && $amount > 0) {
            return true;
        }
    }

    return false;
}

function sffc_crm_normalize_premium_search_profile($profile) {
    $defaults = sffc_crm_get_premium_search_profile_defaults();
    $profile = is_array($profile) ? wp_parse_args($profile, $defaults) : $defaults;

    $profile['enabled'] = !empty($profile['enabled']);
    $profile['membership_tier'] = sanitize_key((string) ($profile['membership_tier'] ?? 'free')) ?: 'free';
    $profile['subscription_status'] = sanitize_key((string) ($profile['subscription_status'] ?? ''));
    $profile['subscription_amount'] = max(0, (float) ($profile['subscription_amount'] ?? 0));
    $profile['target_roles'] = sffc_crm_normalize_text_list($profile['target_roles'] ?? []);
    $profile['target_sectors'] = sffc_crm_normalize_text_list($profile['target_sectors'] ?? []);
    $profile['target_locations'] = sffc_crm_normalize_text_list($profile['target_locations'] ?? []);
    $profile['target_seniority'] = array_values(array_filter(array_map('sanitize_key', (array) ($profile['target_seniority'] ?? []))));
    $profile['target_skills'] = sffc_crm_normalize_text_list($profile['target_skills'] ?? [], 32);
    $profile['work_modes'] = array_values(array_filter(array_map('sanitize_key', (array) ($profile['work_modes'] ?? []))));
    $profile['excluded_roles'] = sffc_crm_normalize_text_list($profile['excluded_roles'] ?? []);
    $profile['excluded_locations'] = sffc_crm_normalize_text_list($profile['excluded_locations'] ?? []);
    $profile['excluded_sectors'] = sffc_crm_normalize_text_list($profile['excluded_sectors'] ?? []);
    $profile['visa_status'] = sanitize_text_field((string) ($profile['visa_status'] ?? ''));
    $profile['delivery_frequency'] = sanitize_key((string) ($profile['delivery_frequency'] ?? 'daily')) ?: 'daily';
    if (!in_array($profile['delivery_frequency'], ['instant', 'daily', 'weekly'], true)) {
        $profile['delivery_frequency'] = 'daily';
    }
    $profile['preferred_email'] = sanitize_email((string) ($profile['preferred_email'] ?? ''));
    $profile['active_cv_upload_id'] = max(0, (int) ($profile['active_cv_upload_id'] ?? 0));
    $profile['cv_source'] = sanitize_key((string) ($profile['cv_source'] ?? ''));
    $profile['cv_text_hash'] = sanitize_text_field((string) ($profile['cv_text_hash'] ?? ''));
    $profile['last_role_title'] = sanitize_text_field((string) ($profile['last_role_title'] ?? ''));
    $profile['preferred_location'] = sanitize_text_field((string) ($profile['preferred_location'] ?? ''));
    $profile['updated_at'] = sanitize_text_field((string) ($profile['updated_at'] ?? ''));
    $profile['source'] = sanitize_key((string) ($profile['source'] ?? 'none')) ?: 'none';

    return $profile;
}

function sffc_crm_get_premium_search_profile($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return sffc_crm_get_premium_search_profile_defaults();
    }

    $stored = get_user_meta($user_id, 'sffc_crm_premium_search_profile', true);
    return sffc_crm_normalize_premium_search_profile($stored);
}

function sffc_crm_save_premium_search_profile($user_id, $profile) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    $normalized = sffc_crm_normalize_premium_search_profile($profile);
    if ($normalized['updated_at'] === '') {
        $normalized['updated_at'] = current_time('mysql');
    }

    update_user_meta($user_id, 'sffc_crm_premium_search_profile', $normalized);
    update_user_meta($user_id, 'sffc_crm_alert_frequency', $normalized['delivery_frequency']);
    update_user_meta($user_id, 'sffc_crm_digest_daily', $normalized['delivery_frequency'] === 'daily' ? 1 : 0);
    update_user_meta($user_id, 'sffc_crm_digest_weekly', $normalized['delivery_frequency'] === 'weekly' ? 1 : 0);

    return $normalized;
}

function sffc_crm_sync_premium_search_profile_from_apply_chat($user_id, $memory = [], $resume_context = [], $profile_preferences = [], $membership = []) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    $memory = is_array($memory) ? $memory : [];
    $resume_context = is_array($resume_context) ? $resume_context : [];
    $profile_preferences = is_array($profile_preferences) ? $profile_preferences : [];
    $membership = is_array($membership) ? $membership : [];

    $existing = sffc_crm_get_premium_search_profile($user_id);
    $job_preferences = function_exists('sffc_crm_get_job_preferences')
        ? sffc_crm_get_job_preferences($user_id)
        : [];
    $target_roles = sffc_crm_normalize_text_list(array_merge(
        (array) ($memory['target_functions'] ?? []),
        (array) ($profile_preferences['target_roles'] ?? [])
    ));
    $target_sectors = sffc_crm_normalize_text_list(array_merge(
        (array) ($memory['target_sectors'] ?? []),
        (array) ($profile_preferences['target_sectors'] ?? [])
    ));
    $target_locations = sffc_crm_normalize_text_list(array_merge(
        (array) ($memory['target_locations'] ?? []),
        (array) ($profile_preferences['target_locations'] ?? []),
        array_filter([(string) ($memory['preferred_location'] ?? '')])
    ));
    $excluded_roles = sffc_crm_normalize_text_list(array_merge(
        (array) ($memory['excluded_target_functions'] ?? []),
        (array) ($profile_preferences['excluded_roles'] ?? [])
    ));
    $excluded_locations = sffc_crm_normalize_text_list(array_merge(
        (array) ($memory['excluded_target_locations'] ?? []),
        (array) ($profile_preferences['excluded_locations'] ?? [])
    ));
    $excluded_sectors = sffc_crm_normalize_text_list(array_merge(
        (array) ($memory['excluded_target_sectors'] ?? []),
        (array) ($profile_preferences['excluded_sectors'] ?? [])
    ));

    $delivery_frequency = sanitize_key((string) ($memory['delivery_frequency'] ?? $existing['delivery_frequency'] ?? 'daily'));
    if (!in_array($delivery_frequency, ['instant', 'daily', 'weekly'], true)) {
        $delivery_frequency = 'daily';
    }

    $cv_text = (string) ($resume_context['cv_text'] ?? '');
    $profile = [
        'enabled' => !empty($memory['premium_onboarding_completed']) || !empty($memory['premiumOnboardingCompleted']) || !empty($existing['enabled']) || !empty($target_roles) || !empty($target_locations),
        'membership_tier' => sanitize_key((string) ($membership['tier'] ?? $existing['membership_tier'] ?? 'free')),
        'subscription_status' => sanitize_key((string) ($membership['status'] ?? $existing['subscription_status'] ?? '')),
        'subscription_amount' => (float) ($membership['amount'] ?? $existing['subscription_amount'] ?? 0),
        'target_roles' => $target_roles,
        'target_sectors' => $target_sectors,
        'target_locations' => $target_locations,
        'target_seniority' => array_values(array_filter(array_map('sanitize_key', (array) ($job_preferences['target_seniority'] ?? $existing['target_seniority'] ?? [])))),
        'target_skills' => sffc_crm_normalize_text_list((array) ($memory['target_skills'] ?? []), 32),
        'work_modes' => array_values(array_filter(array_map('sanitize_key', (array) ($memory['work_style_preferences'] ?? [])))),
        'excluded_roles' => $excluded_roles,
        'excluded_locations' => $excluded_locations,
        'excluded_sectors' => $excluded_sectors,
        'visa_status' => sanitize_text_field((string) ($memory['global_visa_status'] ?? $existing['visa_status'] ?? '')),
        'delivery_frequency' => $delivery_frequency,
        'preferred_email' => sanitize_email((string) (wp_get_current_user()->user_email ?? $existing['preferred_email'] ?? '')),
        'active_cv_upload_id' => max(0, (int) ($resume_context['active_upload_id'] ?? $existing['active_cv_upload_id'] ?? 0)),
        'cv_source' => sanitize_key((string) ($resume_context['source'] ?? $existing['cv_source'] ?? '')),
        'cv_text_hash' => $cv_text !== '' ? md5($cv_text) : sanitize_text_field((string) ($existing['cv_text_hash'] ?? '')),
        'last_role_title' => sanitize_text_field((string) ($memory['last_role_title'] ?? $existing['last_role_title'] ?? '')),
        'preferred_location' => sanitize_text_field((string) ($memory['preferred_location'] ?? $existing['preferred_location'] ?? '')),
        'updated_at' => current_time('mysql'),
        'source' => 'apply_chat',
    ];

    if (!empty($target_roles) || !empty($target_sectors) || !empty($target_locations)) {
        sffc_crm_save_job_preferences($user_id, [
            'target_roles' => $target_roles,
            'target_sectors' => $target_sectors,
            'target_locations' => $target_locations,
        ]);
    }

    return sffc_crm_save_premium_search_profile($user_id, $profile);
}

/**
 * Get specific preference field
 *
 * @param int $user_id User ID
 * @param string $field Field name (without sffc_crm_ prefix)
 * @param mixed $default Default value if not set
 * @return mixed Field value
 */
function sffc_crm_get_preference($user_id, $field, $default = '') {
    if (!$user_id) {
        return $default;
    }

    $value = get_user_meta($user_id, 'sffc_crm_' . $field, true);
    return $value ?: $default;
}

/**
 * Check if user has any preferences set
 *
 * @param int $user_id User ID
 * @return bool True if user has at least one preference set
 */
function sffc_crm_has_preferences($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return false;
    }

    $preferences = sffc_crm_get_job_preferences($user_id);

    // Check if any array preferences have values
    $array_fields = ['target_roles', 'target_sectors', 'target_seniority', 'target_locations', 'target_countries', 'work_arrangement'];
    foreach ($array_fields as $field) {
        if (!empty($preferences[$field]) && is_array($preferences[$field])) {
            return true;
        }
    }

    // Check if salary range is set
    if (!empty($preferences['salary_min']) || !empty($preferences['salary_max'])) {
        return true;
    }

    return false;
}

/**
 * Get available sector options
 *
 * @return array Sector options [value => label]
 */
function sffc_crm_get_sector_options() {
    return [
        'pe' => __('Private Equity', 'senna-finance'),
        'ib' => __('Investment Banking', 'senna-finance'),
        'vc' => __('Venture Capital', 'senna-finance'),
        'hedge_fund' => __('Hedge Fund', 'senna-finance'),
        'asset_management' => __('Asset Management', 'senna-finance'),
        'private_credit' => __('Private Credit / Direct Lending', 'senna-finance'),
        'family_office' => __('Family Office', 'senna-finance'),
        'consulting' => __('Management Consulting', 'senna-finance'),
        'corporate' => __('Corporate Finance / Strategy', 'senna-finance'),
        'fintech' => __('FinTech / Startups', 'senna-finance'),
        'real_estate' => __('Real Estate Investing', 'senna-finance'),
        'infrastructure' => __('Infrastructure / Project Finance', 'senna-finance'),
        'energy' => __('Energy / Natural Resources', 'senna-finance'),
        'healthcare' => __('Healthcare / Life Sciences', 'senna-finance'),
        'technology' => __('Technology / Software', 'senna-finance'),
        'government' => __('Government / Sovereign Funds', 'senna-finance'),
        'non_profit' => __('Impact / Non-Profit', 'senna-finance'),
        'other' => __('Other', 'senna-finance'),
    ];
}

/**
 * Get available seniority options
 *
 * @return array Seniority options [value => label]
 */
function sffc_crm_get_seniority_options() {
    return [
        'intern' => __('Early-Career / Legacy Intake', 'senna-finance'),
        'analyst' => __('Manager / Senior Manager (2-5 yrs)', 'senna-finance'),
        'senior_analyst' => __('Manager / Team Lead (4-6 yrs)', 'senna-finance'),
        'associate' => __('Associate (4-7 yrs)', 'senna-finance'),
        'senior_associate' => __('Senior Associate (6-8 yrs)', 'senna-finance'),
        'vp' => __('Vice President / Principal', 'senna-finance'),
        'senior_vp' => __('Senior Vice President / Head', 'senna-finance'),
        'director' => __('Director / Executive Director', 'senna-finance'),
        'md' => __('Managing Director', 'senna-finance'),
        'partner' => __('Partner', 'senna-finance'),
        'c_level' => __('C-Level / Head of Function', 'senna-finance'),
        'board' => __('Board / Advisor', 'senna-finance'),
        'other' => __('Other', 'senna-finance'),
    ];
}

/**
 * Get available work arrangement options
 *
 * @return array Work arrangement options [value => label]
 */
function sffc_crm_get_work_arrangement_options() {
    return [
        'remote' => 'Remote',
        'hybrid' => 'Hybrid',
        'onsite' => 'On-site',
    ];
}

/**
 * Get active post groups for alert filtering
 *
 * @return array [group_id => group_name]
 */
function sffc_crm_get_alert_group_options() {
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    if (!class_exists('SFFC_CRM_Post_Group')) {
        require_once SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-post-group.php';
    }

    $group_model = new SFFC_CRM_Post_Group();
    $groups = $group_model->get_all([
        'is_active' => 1,
        'include_post_count' => false,
        'order_by' => 'display_order',
        'order' => 'ASC',
    ]);

    $options = [];
    foreach ($groups as $group) {
        $options[(int) $group['id']] = $group['name'];
    }

    $cached = $options;
    return $options;
}

/**
 * Fetch post group IDs for a post
 *
 * @param int $post_id
 * @return array
 */
function sffc_crm_get_post_group_ids($post_id) {
    if (!$post_id) {
        return [];
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sffc_crm_post_group_relationships';
    $group_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT group_id FROM {$table} WHERE post_id = %d",
        $post_id
    ));

    if (empty($group_ids)) {
        return [];
    }

    return array_map('intval', $group_ids);
}

/**
 * Role alert type options
 */
function sffc_crm_get_internship_type_options() {
    return [
        'summer'    => __('Permanent Finance Roles', 'senna-finance'),
        'off_cycle' => __('Confidential Search Mandates', 'senna-finance'),
        'spring'    => __('Leadership / Team-Buildout Hires', 'senna-finance'),
        'placement' => __('Platform Buildout / Transformation Roles', 'senna-finance'),
    ];
}

/**
 * Shared empty structure for internship alert preferences.
 */
function sffc_crm_get_empty_alert_preferences() {
    return [
        'enabled'     => false,
        'sectors'     => [],
        'types'       => [],
        'locations'   => [],
        'work_modes'  => [],
        'groups'      => [],
        'source'      => 'none',
        'customized'  => false,
        'profile_id'  => 0,
        'profile_name'=> '',
    ];
}

/**
 * Normalize a preference payload into the standard structure.
 */
function sffc_crm_normalize_alert_preferences($prefs) {
    $defaults = sffc_crm_get_empty_alert_preferences();
    $prefs = is_array($prefs) ? wp_parse_args($prefs, $defaults) : $defaults;

    $locations = $prefs['locations'];
    if (!is_array($locations)) {
        $locations = preg_split('/[,\n]/', (string) $locations);
    }

    return [
        'enabled'      => !empty($prefs['enabled']),
        'sectors'      => array_values(array_filter(array_map('sanitize_key', (array) ($prefs['sectors'] ?? [])))),
        'types'        => array_values(array_filter(array_map('sanitize_key', (array) ($prefs['types'] ?? [])))),
        'locations'    => array_values(array_filter(array_map('sanitize_text_field', array_map('trim', (array) $locations)))),
        'work_modes'   => array_values(array_filter(array_map('sanitize_key', (array) ($prefs['work_modes'] ?? [])))),
        'groups'       => array_values(array_filter(array_map('intval', (array) ($prefs['groups'] ?? [])))),
        'source'       => sanitize_key($prefs['source'] ?? 'none') ?: 'none',
        'customized'   => !empty($prefs['customized']),
        'profile_id'   => intval($prefs['profile_id'] ?? 0),
        'profile_name' => sanitize_text_field($prefs['profile_name'] ?? ''),
    ];
}

/**
 * Read user-owned alert preferences only.
 */
function sffc_crm_get_user_alert_preferences_raw($user_id) {
    if (!$user_id) {
        return sffc_crm_get_empty_alert_preferences();
    }

    $locations = get_user_meta($user_id, 'sffc_crm_alert_locations', true);
    if (!is_array($locations)) {
        $locations = array_filter(array_map('trim', explode(',', (string) $locations)));
    }

    return sffc_crm_normalize_alert_preferences([
        'enabled'    => (bool) get_user_meta($user_id, 'sffc_crm_alerts_enabled', true),
        'sectors'    => get_user_meta($user_id, 'sffc_crm_alert_sectors', true) ?: [],
        'types'      => get_user_meta($user_id, 'sffc_crm_alert_types', true) ?: [],
        'locations'  => $locations,
        'work_modes' => get_user_meta($user_id, 'sffc_crm_alert_work_modes', true) ?: [],
        'groups'     => get_user_meta($user_id, 'sffc_crm_alert_groups', true) ?: [],
        'source'     => 'user',
        'customized' => sffc_crm_user_has_customized_alert_preferences($user_id),
    ]);
}

/**
 * Check whether a user has already customized alert preferences.
 */
function sffc_crm_user_has_customized_alert_preferences($user_id) {
    if (!$user_id) {
        return false;
    }

    $explicit = get_user_meta($user_id, 'sffc_crm_alert_preferences_customized', true);
    if ($explicit !== '') {
        return (bool) $explicit;
    }

    $tracked_meta_keys = [
        'sffc_crm_alerts_enabled',
        'sffc_crm_alert_sectors',
        'sffc_crm_alert_types',
        'sffc_crm_alert_locations',
        'sffc_crm_alert_work_modes',
        'sffc_crm_alert_groups',
    ];

    foreach ($tracked_meta_keys as $meta_key) {
        if (metadata_exists('user', $user_id, $meta_key)) {
            return true;
        }
    }

    return false;
}

/**
 * Get all admin-managed default alert profiles.
 */
function sffc_crm_get_alert_default_profiles($args = []) {
    global $wpdb;

    $table = $wpdb->prefix . 'sffc_crm_alert_default_profiles';
    $assignment_table = $wpdb->prefix . 'sffc_crm_alert_profile_users';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table || $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $assignment_table)) !== $assignment_table) {
        return [];
    }

    $defaults = [
        'is_active' => null,
    ];
    $args = wp_parse_args($args, $defaults);

    $where = ['1=1'];
    $values = [];

    if ($args['is_active'] !== null) {
        $where[] = 'p.is_active = %d';
        $values[] = (int) $args['is_active'];
    }

    $cache_key = 'sffc_crm_alert_default_profiles_' . md5(wp_json_encode([
        'is_active' => $args['is_active'],
    ]));
    $use_cache = empty($_POST);

    if ($use_cache) {
        $cached_profiles = get_transient($cache_key);
        if (is_array($cached_profiles)) {
            return $cached_profiles;
        }
    }

    $query = "SELECT p.*, COUNT(apu.user_id) AS assigned_user_count
              FROM {$table} p
              LEFT JOIN {$assignment_table} apu ON apu.profile_id = p.id
              WHERE " . implode(' AND ', $where) . "
              GROUP BY p.id
              ORDER BY p.name ASC";

    if (!empty($values)) {
        $query = $wpdb->prepare($query, $values);
    }

    $profiles = $wpdb->get_results($query, ARRAY_A);
    if (empty($profiles)) {
        return [];
    }

    foreach ($profiles as &$profile) {
        $profile = sffc_crm_prepare_alert_default_profile($profile);
    }
    unset($profile);

    if ($use_cache) {
        set_transient($cache_key, $profiles, 5 * MINUTE_IN_SECONDS);
    }

    return $profiles;
}

/**
 * Convert a DB row into a normalized default alert profile structure.
 */
function sffc_crm_prepare_alert_default_profile($profile) {
    $profile = is_array($profile) ? $profile : [];

    return [
        'id'                  => intval($profile['id'] ?? 0),
        'name'                => sanitize_text_field($profile['name'] ?? ''),
        'description'         => wp_kses_post($profile['description'] ?? ''),
        'enabled'             => !empty($profile['enabled_by_default']),
        'sectors'             => array_values(array_filter(array_map('sanitize_key', (array) json_decode((string) ($profile['sectors'] ?? '[]'), true)))),
        'types'               => array_values(array_filter(array_map('sanitize_key', (array) json_decode((string) ($profile['types'] ?? '[]'), true)))),
        'locations'           => array_values(array_filter(array_map('sanitize_text_field', (array) json_decode((string) ($profile['locations'] ?? '[]'), true)))),
        'work_modes'          => array_values(array_filter(array_map('sanitize_key', (array) json_decode((string) ($profile['work_modes'] ?? '[]'), true)))),
        'groups'              => array_values(array_filter(array_map('intval', (array) json_decode((string) ($profile['group_ids'] ?? ($profile['groups'] ?? '[]')), true)))),
        'is_active'           => !empty($profile['is_active']),
        'assigned_user_count' => intval($profile['assigned_user_count'] ?? 0),
        'created_at'          => $profile['created_at'] ?? '',
        'updated_at'          => $profile['updated_at'] ?? '',
    ];
}

/**
 * Load one default alert profile by ID.
 */
function sffc_crm_get_alert_default_profile($profile_id) {
    global $wpdb;

    $profile_id = intval($profile_id);
    if ($profile_id <= 0) {
        return null;
    }

    $table = $wpdb->prefix . 'sffc_crm_alert_default_profiles';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
        return null;
    }

    $profile = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $profile_id), ARRAY_A);

    return $profile ? sffc_crm_prepare_alert_default_profile($profile) : null;
}

/**
 * Save or update a default alert profile.
 */
function sffc_crm_save_alert_default_profile($profile_id, $profile) {
    global $wpdb;

    $table = $wpdb->prefix . 'sffc_crm_alert_default_profiles';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
        return new WP_Error('profile_table_missing', __('Default alert profile table is missing. Create the tables first.', 'senna-finance'));
    }

    $profile_id = intval($profile_id);
    $normalized = sffc_crm_normalize_alert_preferences($profile);

    $data = [
        'name'               => sanitize_text_field($profile['name'] ?? ''),
        'description'        => wp_kses_post($profile['description'] ?? ''),
        'enabled_by_default' => !empty($normalized['enabled']) ? 1 : 0,
        'sectors'            => wp_json_encode($normalized['sectors']),
        'types'              => wp_json_encode($normalized['types']),
        'locations'          => wp_json_encode($normalized['locations']),
        'work_modes'         => wp_json_encode($normalized['work_modes']),
        'group_ids'          => wp_json_encode($normalized['groups']),
        'is_active'          => !empty($profile['is_active']) ? 1 : 0,
    ];
    $format = ['%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d'];

    if ($data['name'] === '') {
        return new WP_Error('missing_name', __('Profile name is required.', 'senna-finance'));
    }

    if ($profile_id > 0) {
        $updated = $wpdb->update($table, $data, ['id' => $profile_id], $format, ['%d']);
        if ($updated === false) {
            return new WP_Error('profile_update_failed', __('Unable to update the default alert profile.', 'senna-finance'));
        }
        return $profile_id;
    }

    $inserted = $wpdb->insert($table, $data, $format);
    if (!$inserted) {
        return new WP_Error('profile_create_failed', __('Unable to create the default alert profile.', 'senna-finance'));
    }

    return intval($wpdb->insert_id);
}

/**
 * Delete a default alert profile and its user assignments.
 */
function sffc_crm_delete_alert_default_profile($profile_id) {
    global $wpdb;

    $profile_id = intval($profile_id);
    if ($profile_id <= 0) {
        return false;
    }

    $table = $wpdb->prefix . 'sffc_crm_alert_default_profiles';
    $assignment_table = $wpdb->prefix . 'sffc_crm_alert_profile_users';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table || $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $assignment_table)) !== $assignment_table) {
        return false;
    }

    $wpdb->delete($assignment_table, ['profile_id' => $profile_id], ['%d']);

    return $wpdb->delete($table, ['id' => $profile_id], ['%d']) !== false;
}

/**
 * Get the assigned default alert profile for a user.
 */
function sffc_crm_get_user_alert_default_profile($user_id) {
    global $wpdb;

    $user_id = intval($user_id);
    if ($user_id <= 0) {
        return null;
    }

    $assignment_table = $wpdb->prefix . 'sffc_crm_alert_profile_users';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $assignment_table)) !== $assignment_table) {
        return null;
    }

    $profile_id = intval($wpdb->get_var($wpdb->prepare(
        "SELECT profile_id FROM {$assignment_table} WHERE user_id = %d LIMIT 1",
        $user_id
    )));

    if ($profile_id <= 0) {
        return null;
    }

    return sffc_crm_get_alert_default_profile($profile_id);
}

/**
 * Assign one default alert profile to many users.
 */
function sffc_crm_assign_alert_default_profile_to_users($profile_id, $user_ids, $assigned_by = 0) {
    global $wpdb;

    $profile_id = intval($profile_id);
    $user_ids = array_values(array_filter(array_map('intval', (array) $user_ids)));
    if ($profile_id <= 0 || empty($user_ids)) {
        return 0;
    }

    $profile = sffc_crm_get_alert_default_profile($profile_id);
    if (!$profile) {
        return 0;
    }

    $table = $wpdb->prefix . 'sffc_crm_alert_profile_users';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
        return 0;
    }

    $processed = 0;

    foreach ($user_ids as $user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            continue;
        }

        $existing = intval($wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE user_id = %d LIMIT 1",
            $user_id
        )));

        if ($existing > 0) {
            $result = $wpdb->update(
                $table,
                [
                    'profile_id'   => $profile_id,
                    'assigned_by'  => intval($assigned_by),
                    'assigned_at'  => current_time('mysql'),
                    'updated_at'   => current_time('mysql'),
                ],
                ['id' => $existing],
                ['%d', '%d', '%s', '%s'],
                ['%d']
            );
        } else {
            $result = $wpdb->insert(
                $table,
                [
                    'profile_id'  => $profile_id,
                    'user_id'     => $user_id,
                    'assigned_by' => intval($assigned_by),
                    'assigned_at' => current_time('mysql'),
                    'updated_at'  => current_time('mysql'),
                ],
                ['%d', '%d', '%d', '%s', '%s']
            );
        }

        if ($result !== false) {
            $processed++;
        }
    }

    return $processed;
}

/**
 * Clear assigned default profiles for users.
 */
function sffc_crm_clear_alert_default_profiles_for_users($user_ids) {
    global $wpdb;

    $user_ids = array_values(array_filter(array_map('intval', (array) $user_ids)));
    if (empty($user_ids)) {
        return 0;
    }

    $table = $wpdb->prefix . 'sffc_crm_alert_profile_users';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
        return 0;
    }

    $processed = 0;

    foreach ($user_ids as $user_id) {
        $result = $wpdb->delete($table, ['user_id' => $user_id], ['%d']);
        if ($result !== false) {
            $processed += (int) $result;
        }
    }

    return $processed;
}

/**
 * Get internship alert preferences for a user, including inherited admin defaults.
 */
function sffc_crm_get_alert_preferences($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return sffc_crm_get_empty_alert_preferences();
    }

    $customized = sffc_crm_user_has_customized_alert_preferences($user_id);
    $user_prefs = sffc_crm_get_user_alert_preferences_raw($user_id);

    if ($customized) {
        $user_prefs['customized'] = true;
        $user_prefs['source'] = 'user';
        return $user_prefs;
    }

    $profile = sffc_crm_get_user_alert_default_profile($user_id);
    if ($profile && !empty($profile['is_active'])) {
        return sffc_crm_normalize_alert_preferences([
            'enabled'      => $profile['enabled'],
            'sectors'      => $profile['sectors'],
            'types'        => $profile['types'],
            'locations'    => $profile['locations'],
            'work_modes'   => $profile['work_modes'],
            'groups'       => $profile['groups'],
            'source'       => 'default_profile',
            'customized'   => false,
            'profile_id'   => $profile['id'],
            'profile_name' => $profile['name'],
        ]);
    }

    return sffc_crm_get_empty_alert_preferences();
}

/**
 * Save internship alert preferences
 */
function sffc_crm_save_alert_preferences($user_id, $prefs) {
    if (!$user_id) {
        return false;
    }

    $normalized = sffc_crm_normalize_alert_preferences($prefs);
    $enabled = !empty($normalized['enabled']);
    update_user_meta($user_id, 'sffc_crm_alerts_enabled', $enabled ? '1' : '0');
    update_user_meta($user_id, 'sffc_crm_alert_preferences_customized', '1');

    update_user_meta($user_id, 'sffc_crm_alert_sectors', $normalized['sectors']);

    update_user_meta($user_id, 'sffc_crm_alert_types', $normalized['types']);
    update_user_meta($user_id, 'sffc_crm_alert_locations', $normalized['locations']);
    update_user_meta($user_id, 'sffc_crm_alert_work_modes', $normalized['work_modes']);
    update_user_meta($user_id, 'sffc_crm_alert_groups', $normalized['groups']);

    return sffc_crm_get_alert_preferences($user_id);
}

/**
 * Determine if post is an internship-style role
 */
function sffc_crm_alert_post_is_internship($post) {
    $title = strtolower($post['role_title'] ?? '');
    $content = strtolower($post['content'] ?? '');
    $keywords = ['intern', 'internship', 'summer', 'spring', 'off-cycle', 'placement'];

    foreach ($keywords as $keyword) {
        if (strpos($title, $keyword) !== false || strpos($content, $keyword) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Check if a post matches alert preferences
 */
function sffc_crm_alert_matches_post($prefs, $post) {
    if (empty($prefs['enabled']) || !sffc_crm_alert_post_is_internship($post)) {
        return false;
    }

    if (!empty($prefs['sectors']) && !in_array($post['sector'] ?? '', $prefs['sectors'], true)) {
        return false;
    }

    if (!empty($prefs['types'])) {
        $title = strtolower($post['role_title'] ?? '');
        $matched_type = false;
        foreach ($prefs['types'] as $type) {
            if ($type === 'summer' && (strpos($title, 'summer') !== false || strpos($title, 'jun') !== false)) {
                $matched_type = true;
                break;
            }
            if ($type === 'off_cycle' && (strpos($title, 'off-cycle') !== false || strpos($title, 'off cycle') !== false)) {
                $matched_type = true;
                break;
            }
            if ($type === 'spring' && strpos($title, 'spring') !== false) {
                $matched_type = true;
                break;
            }
            if ($type === 'placement' && (strpos($title, 'placement') !== false || strpos($title, 'industrial') !== false)) {
                $matched_type = true;
                break;
            }
        }

        if (!$matched_type) {
            return false;
        }
    }

    if (!empty($prefs['locations'])) {
        $location_blob = strtolower(trim(($post['location'] ?? '') . ' ' . ($post['location_country'] ?? '') . ' ' . ($post['location_city'] ?? '')));
        $location_match = false;
        foreach ($prefs['locations'] as $pref_location) {
            if (!$pref_location) {
                continue;
            }
            if (strpos($location_blob, strtolower($pref_location)) !== false) {
                $location_match = true;
                break;
            }
        }

        if (!$location_match) {
            return false;
        }
    }

    if (!empty($prefs['work_modes'])) {
        $mode = 'onsite';
        if (!empty($post['is_remote'])) {
            $mode = 'remote';
        } elseif (!empty($post['is_hybrid'])) {
            $mode = 'hybrid';
        }

        if (!in_array($mode, $prefs['work_modes'], true)) {
            return false;
        }
    }

    if (!empty($prefs['groups'])) {
        $post_group_ids = isset($post['post_group_ids']) ? array_map('intval', (array) $post['post_group_ids']) : [];
        if (empty($post_group_ids)) {
            return false;
        }

        $matched = array_intersect($post_group_ids, array_map('intval', (array) $prefs['groups']));
        if (empty($matched)) {
            return false;
        }
    }

    return true;
}

/**
 * Get the CRM membership tier used for alert routing.
 */
function sffc_crm_get_alert_membership_tier($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return 'free';
    }

    if (class_exists('SFFC_CRM_MemberPress_Integration')) {
        return SFFC_CRM_MemberPress_Integration::get_instance()->get_crm_tier($user_id);
    }

    return 'free';
}

/**
 * Paid users receive instant alerts. Everyone else is grouped into digests.
 */
function sffc_crm_user_has_instant_alert_access($user_id) {
    return in_array(sffc_crm_get_alert_membership_tier($user_id), ['insider', 'pro'], true);
}

function sffc_crm_get_free_alert_digest_history($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }

    $history = get_user_meta($user_id, 'sffc_crm_free_alert_digest_history', true);
    if (!is_array($history)) {
        return [];
    }

    $normalized = [];
    foreach ($history as $post_id => $entry) {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            continue;
        }

        if (is_array($entry)) {
            $normalized[$post_id] = [
                'count' => max(0, (int) ($entry['count'] ?? 0)),
                'last_sent_at' => sanitize_text_field($entry['last_sent_at'] ?? ''),
            ];
            continue;
        }

        $normalized[$post_id] = [
            'count' => max(0, (int) $entry),
            'last_sent_at' => '',
        ];
    }

    return $normalized;
}

function sffc_crm_record_free_alert_digest_posts($user_id, $posts) {
    $user_id = (int) $user_id;
    if ($user_id <= 0 || empty($posts)) {
        return;
    }

    $history = sffc_crm_get_free_alert_digest_history($user_id);
    $sent_at = current_time('mysql');

    foreach ((array) $posts as $post) {
        $post_id = (int) ($post['id'] ?? 0);
        if ($post_id <= 0) {
            continue;
        }

        $existing = $history[$post_id] ?? ['count' => 0, 'last_sent_at' => ''];
        $history[$post_id] = [
            'count' => min(2, ((int) $existing['count']) + 1),
            'last_sent_at' => $sent_at,
        ];
    }

    update_user_meta($user_id, 'sffc_crm_free_alert_digest_history', $history);
}

/**
 * Get current internship matches for alert preferences.
 */
function sffc_crm_get_matching_internship_posts_for_alerts($prefs, $args = []) {
    global $wpdb;

    $prefs = sffc_crm_normalize_alert_preferences($prefs);
    if (empty($prefs['enabled'])) {
        return [
            'total' => 0,
            'posts' => [],
        ];
    }

    $defaults = [
        'limit' => 6,
        'scan_limit' => 250,
        'user_id' => 0,
        'last_sent_at' => '',
        'max_digest_repeats' => 2,
    ];
    $args = wp_parse_args($args, $defaults);

    $table = $wpdb->prefix . 'sffc_crm_posts';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT *
         FROM {$table}
         WHERE is_active = 1
           AND admin_approved = 1
         ORDER BY posted_at DESC, id DESC
         LIMIT %d",
        max(25, intval($args['scan_limit']))
    ), ARRAY_A);

    if (empty($rows)) {
        return [
            'total' => 0,
            'posts' => [],
        ];
    }

    $matches = [];
    $user_id = (int) $args['user_id'];
    $digest_history = $user_id > 0 ? sffc_crm_get_free_alert_digest_history($user_id) : [];
    $max_digest_repeats = max(1, (int) $args['max_digest_repeats']);
    $last_sent_ts = !empty($args['last_sent_at']) ? strtotime((string) $args['last_sent_at']) : 0;

    foreach ($rows as $row) {
        if (!sffc_crm_alert_post_is_internship($row)) {
            continue;
        }

        $row['post_group_ids'] = sffc_crm_get_post_group_ids((int) ($row['id'] ?? 0));
        if (sffc_crm_alert_matches_post($prefs, $row)) {
            $post_id = (int) ($row['id'] ?? 0);
            $history_entry = $digest_history[$post_id] ?? ['count' => 0, 'last_sent_at' => ''];
            if ($user_id > 0 && (int) ($history_entry['count'] ?? 0) >= $max_digest_repeats) {
                continue;
            }

            $posted_at_ts = !empty($row['posted_at']) ? strtotime((string) $row['posted_at']) : 0;
            $is_new = $last_sent_ts > 0 ? ($posted_at_ts > $last_sent_ts) : ($posted_at_ts >= strtotime('-2 days'));
            $row['_digest_repeat_count'] = (int) ($history_entry['count'] ?? 0);
            $row['_digest_is_new'] = $is_new;
            $matches[] = $row;
        }
    }

    if (!empty($matches)) {
        $new_matches = [];
        $older_matches = [];

        foreach ($matches as $match) {
            if (!empty($match['_digest_is_new'])) {
                $new_matches[] = $match;
            } else {
                $older_matches[] = $match;
            }
        }

        if (count($new_matches) > 1) {
            usort($new_matches, static function ($a, $b) {
                $a_time = !empty($a['posted_at']) ? strtotime((string) $a['posted_at']) : 0;
                $b_time = !empty($b['posted_at']) ? strtotime((string) $b['posted_at']) : 0;
                return $b_time <=> $a_time;
            });
        }

        if (count($older_matches) > 1) {
            shuffle($older_matches);
            usort($older_matches, static function ($a, $b) {
                $a_repeat = (int) ($a['_digest_repeat_count'] ?? 0);
                $b_repeat = (int) ($b['_digest_repeat_count'] ?? 0);
                return $a_repeat <=> $b_repeat;
            });
        }

        $matches = array_merge($new_matches, $older_matches);
    }

    return [
        'total' => count($matches),
        'posts' => array_slice($matches, 0, max(1, intval($args['limit']))),
    ];
}

function sffc_crm_get_alert_digest_destination_url() {
    return apply_filters('sffc_crm_alert_digest_destination_url', 'https://joinsenna.com/terminal/');
}

function sffc_crm_get_alert_membership_upgrade_url() {
    return apply_filters('sffc_crm_alert_membership_upgrade_url', 'https://joinsenna.com/memberships/');
}

function sffc_crm_alert_email_sending_disabled() {
    return (bool) get_option('sffc_crm_alert_email_sending_disabled', false);
}

function sffc_crm_internship_alerts_disabled() {
    return (bool) apply_filters('sffc_crm_internship_alerts_disabled', true);
}

function sffc_crm_get_email_sender_context_labels() {
    return [
        'default' => __('Default / fallback sender', 'senna-finance'),
        'internship_alerts' => __('Role alerts', 'senna-finance'),
        'top_match_exit' => __('Top-match onboarding emails', 'senna-finance'),
        'job_post_followup' => __('Job post follow-up emails', 'senna-finance'),
        'application_queue' => __('Application queue emails', 'senna-finance'),
        'custom_email' => __('Custom/manual emails', 'senna-finance'),
        'subscription_cancellation' => __('Subscription cancellation emails', 'senna-finance'),
        'expert_outreach' => __('Expert outreach confirmations', 'senna-finance'),
        'admin_notifications' => __('Admin notifications', 'senna-finance'),
        'security' => __('Password and security emails', 'senna-finance'),
    ];
}

function sffc_crm_get_default_email_sender_contexts() {
    return [
        'default' => 'support',
        'internship_alerts' => 'emily',
        'top_match_exit' => 'emily',
        'job_post_followup' => 'emily',
        'application_queue' => 'support',
        'custom_email' => 'emily',
        'subscription_cancellation' => 'support',
        'expert_outreach' => 'support',
        'admin_notifications' => 'support',
        'security' => 'support',
    ];
}

function sffc_crm_get_default_email_senders() {
    $legacy_email = sanitize_email(get_option('sffc_crm_sendgrid_alert_from_email', 'support.team@joinsenna.com'));
    $legacy_name = sanitize_text_field(get_option('sffc_crm_sendgrid_alert_from_name', 'MENA Careers'));
    if (!$legacy_email) {
        $legacy_email = 'support.team@joinsenna.com';
    }
    if ($legacy_name === '') {
        $legacy_name = 'MENA Careers';
    }

    return [
        'support' => [
            'key' => 'support',
            'label' => __('Support', 'senna-finance'),
            'from_name' => $legacy_name,
            'from_email' => $legacy_email,
            'reply_to_name' => $legacy_name,
            'reply_to_email' => $legacy_email,
            'batch_limit' => 500,
            'enabled' => true,
        ],
        'emily' => [
            'key' => 'emily',
            'label' => __('Emily O', 'senna-finance'),
            'from_name' => __('Emily @ MENA Careers', 'senna-finance'),
            'from_email' => $legacy_email,
            'reply_to_name' => __('Emily @ MENA Careers', 'senna-finance'),
            'reply_to_email' => $legacy_email,
            'batch_limit' => 500,
            'enabled' => true,
        ],
        'noreply' => [
            'key' => 'noreply',
            'label' => __('No Reply', 'senna-finance'),
            'from_name' => __('MENA Careers', 'senna-finance'),
            'from_email' => $legacy_email,
            'reply_to_name' => $legacy_name,
            'reply_to_email' => $legacy_email,
            'batch_limit' => 500,
            'enabled' => true,
        ],
    ];
}

function sffc_crm_sanitize_email_senders($raw_senders) {
    $senders = [];
    foreach ((array) $raw_senders as $sender) {
        $key = sanitize_key($sender['key'] ?? '');
        $label = sanitize_text_field(wp_unslash($sender['label'] ?? ''));
        $from_name = sanitize_text_field(wp_unslash($sender['from_name'] ?? ''));
        $from_email = sanitize_email(wp_unslash($sender['from_email'] ?? ''));
        $reply_to_name = sanitize_text_field(wp_unslash($sender['reply_to_name'] ?? ''));
        $reply_to_email = sanitize_email(wp_unslash($sender['reply_to_email'] ?? ''));
        $batch_limit = max(1, min(10000, intval($sender['batch_limit'] ?? 500)));

        if ($key === '' && $label !== '') {
            $key = sanitize_key($label);
        }
        if ($key === '' || $from_email === '') {
            continue;
        }

        $senders[$key] = [
            'key' => $key,
            'label' => $label !== '' ? $label : ucwords(str_replace(['_', '-'], ' ', $key)),
            'from_name' => $from_name !== '' ? $from_name : 'MENA Careers',
            'from_email' => $from_email,
            'reply_to_name' => $reply_to_name,
            'reply_to_email' => $reply_to_email,
            'batch_limit' => $batch_limit,
            'enabled' => !empty($sender['enabled']),
        ];
    }

    if (empty($senders)) {
        return sffc_crm_get_default_email_senders();
    }

    return $senders;
}

function sffc_crm_get_email_senders() {
    $stored = get_option('sffc_crm_email_senders', []);
    if (is_array($stored) && !empty($stored)) {
        return sffc_crm_sanitize_email_senders($stored);
    }

    return sffc_crm_get_default_email_senders();
}

function sffc_crm_sanitize_email_sender_contexts($raw_contexts, $senders = null) {
    $senders = is_array($senders) ? $senders : sffc_crm_get_email_senders();
    $sender_keys = array_keys($senders);
    $defaults = sffc_crm_get_default_email_sender_contexts();
    $contexts = [];

    foreach (sffc_crm_get_email_sender_context_labels() as $context => $label) {
        $raw_value = $raw_contexts[$context] ?? ($defaults[$context] ?? '');
        $raw_keys = is_array($raw_value) ? $raw_value : [$raw_value];
        $sender_keys_for_context = [];
        foreach ($raw_keys as $raw_key) {
            $sender_key = sanitize_key($raw_key);
            if ($sender_key && isset($senders[$sender_key])) {
                $sender_keys_for_context[] = $sender_key;
            }
        }

        if (empty($sender_keys_for_context)) {
            $default_key = sanitize_key($defaults[$context] ?? '');
            if ($default_key && isset($senders[$default_key])) {
                $sender_keys_for_context[] = $default_key;
            }
        }
        if (empty($sender_keys_for_context) && !empty($sender_keys)) {
            $sender_keys_for_context[] = $sender_keys[0];
        }

        $contexts[$context] = array_values(array_unique($sender_keys_for_context));
    }

    return $contexts;
}

function sffc_crm_get_email_sender_contexts() {
    return sffc_crm_sanitize_email_sender_contexts(get_option('sffc_crm_email_sender_contexts', []));
}

function sffc_crm_get_email_sender($context = 'default') {
    $senders = sffc_crm_get_email_senders();
    $contexts = sffc_crm_sanitize_email_sender_contexts(get_option('sffc_crm_email_sender_contexts', []), $senders);
    $context = sanitize_key($context ?: 'default');
    $sender_keys = $contexts[$context] ?? ($contexts['default'] ?? []);
    if (!is_array($sender_keys)) {
        $sender_keys = [$sender_keys];
    }
    $sender_key = $sender_keys[0] ?? '';
    $sender = $senders[$sender_key] ?? null;

    if (!$sender || empty($sender['enabled'])) {
        foreach ($senders as $candidate) {
            if (!empty($candidate['enabled'])) {
                $sender = $candidate;
                break;
            }
        }
    }

    if (!$sender) {
        $sender = reset($senders);
    }

    return $sender ?: [
        'key' => 'support',
        'label' => 'Support',
        'from_name' => 'MENA Careers',
        'from_email' => 'support.team@joinsenna.com',
        'reply_to_name' => 'MENA Careers',
        'reply_to_email' => 'support.team@joinsenna.com',
        'batch_limit' => 500,
        'enabled' => true,
    ];
}

function sffc_crm_get_email_sender_pool($context = 'default') {
    $senders = sffc_crm_get_email_senders();
    $contexts = sffc_crm_sanitize_email_sender_contexts(get_option('sffc_crm_email_sender_contexts', []), $senders);
    $context = sanitize_key($context ?: 'default');
    $sender_keys = $contexts[$context] ?? ($contexts['default'] ?? []);
    if (!is_array($sender_keys)) {
        $sender_keys = [$sender_keys];
    }

    $pool = [];
    foreach ($sender_keys as $sender_key) {
        if (!empty($senders[$sender_key]) && !empty($senders[$sender_key]['enabled'])) {
            $pool[] = $senders[$sender_key];
        }
    }

    if (empty($pool)) {
        $fallback = sffc_crm_get_email_sender($context);
        if (!empty($fallback)) {
            $pool[] = $fallback;
        }
    }

    return $pool;
}

function sffc_crm_build_email_headers($context = 'default', $content_type = 'text/html', $extra_headers = []) {
    $sender = sffc_crm_get_email_sender($context);
    $headers = ['Content-Type: ' . $content_type . '; charset=UTF-8'];
    $headers[] = 'From: ' . $sender['from_name'] . ' <' . $sender['from_email'] . '>';

    if (!empty($sender['reply_to_email'])) {
        $reply_to_name = !empty($sender['reply_to_name']) ? $sender['reply_to_name'] : $sender['from_name'];
        $headers[] = 'Reply-To: ' . $reply_to_name . ' <' . $sender['reply_to_email'] . '>';
    }

    return array_merge($headers, (array) $extra_headers);
}

function sffc_crm_get_excluded_user_ids() {
    static $excluded_user_ids = null;

    if ($excluded_user_ids !== null) {
        return $excluded_user_ids;
    }

    $raw_value = (string) get_option('sffc_crm_excluded_users', '');
    if ($raw_value === '') {
        $excluded_user_ids = [];
        return $excluded_user_ids;
    }

    $tokens = preg_split('/[\r\n,]+/', $raw_value);
    $tokens = array_values(array_filter(array_map('trim', (array) $tokens)));
    if (empty($tokens)) {
        $excluded_user_ids = [];
        return $excluded_user_ids;
    }

    $user_ids = [];
    foreach ($tokens as $token) {
        if ($token === '') {
            continue;
        }

        if (ctype_digit($token)) {
            $user_ids[] = (int) $token;
            continue;
        }

        $email = sanitize_email($token);
        if ($email === '') {
            continue;
        }

        $user = get_user_by('email', $email);
        if ($user && !empty($user->ID)) {
            $user_ids[] = (int) $user->ID;
        }
    }

    $excluded_user_ids = array_values(array_unique(array_filter($user_ids)));
    return $excluded_user_ids;
}

function sffc_crm_user_is_excluded($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    return in_array($user_id, sffc_crm_get_excluded_user_ids(), true);
}

function sffc_crm_build_free_alert_digest_subject($prefs, $total_matches, $posts = []) {
    $prefs = sffc_crm_normalize_alert_preferences($prefs);
    $sector_options = sffc_crm_get_sector_options();
    $type_options = sffc_crm_get_internship_type_options();

    $keyword = '';
    if (count((array) ($prefs['sectors'] ?? [])) > 1) {
        $keyword = __('Finance Roles', 'senna-finance');
    } elseif (!empty($prefs['sectors'][0])) {
        $keyword = $sector_options[$prefs['sectors'][0]] ?? ucwords(str_replace('_', ' ', $prefs['sectors'][0]));
    } elseif (!empty($prefs['types'][0])) {
        $keyword = $type_options[$prefs['types'][0]] ?? ucwords(str_replace('_', ' ', $prefs['types'][0]));
    } elseif (!empty($posts[0]['sector'])) {
        $keyword = $sector_options[$posts[0]['sector']] ?? ucwords(str_replace('_', ' ', $posts[0]['sector']));
    }

    if ($keyword === '') {
        $keyword = __('Role', 'senna-finance');
    }

    if (
        stripos($keyword, 'role') === false
        && stripos($keyword, 'mandate') === false
        && stripos($keyword, 'hire') === false
    ) {
        $keyword .= ' ' . __('Roles', 'senna-finance');
    }

    $location = '';
    $city_counts = [];
    foreach ((array) $posts as $post) {
        $city = sanitize_text_field($post['location_city'] ?? '');
        if ($city !== '') {
            if (!isset($city_counts[$city])) {
                $city_counts[$city] = 0;
            }
            $city_counts[$city]++;
        }
    }

    if (!empty($city_counts)) {
        arsort($city_counts);
        $location = (string) array_key_first($city_counts);
    } elseif (!empty($posts[0]['location_city'])) {
        $location = sanitize_text_field($posts[0]['location_city']);
    } elseif (!empty($posts[0]['location'])) {
        $location = sanitize_text_field($posts[0]['location']);
    } elseif (!empty($prefs['locations'][0])) {
        $location = sanitize_text_field($prefs['locations'][0]);
    }

    $count_label = sprintf(
        _n('%d Opportunity', '%d Opportunities', max(1, (int) $total_matches), 'senna-finance'),
        max(1, (int) $total_matches)
    );

    return trim($keyword . ($location ? ' ' . $location : '') . ' - ' . $count_label);
}

function sffc_crm_enrich_alert_post_with_recruiter_details($post) {
    static $recruiter_cache = [];

    if (!is_array($post)) {
        return $post;
    }

    $recruiter_id = (int) ($post['recruiter_id'] ?? 0);
    if ($recruiter_id <= 0) {
        return $post;
    }

    if (!isset($recruiter_cache[$recruiter_id])) {
        $recruiter_cache[$recruiter_id] = null;
        if (class_exists('SFFC_CRM_Recruiter')) {
            $recruiter_model = new SFFC_CRM_Recruiter();
            $recruiter_cache[$recruiter_id] = $recruiter_model->get($recruiter_id);
        }
    }

    $recruiter = $recruiter_cache[$recruiter_id];
    if (empty($recruiter) || !is_array($recruiter)) {
        return $post;
    }

    if (empty($post['recruiter_name']) && !empty($recruiter['name'])) {
        $post['recruiter_name'] = $recruiter['name'];
    }
    if (empty($post['recruiter_title']) && !empty($recruiter['title'])) {
        $post['recruiter_title'] = $recruiter['title'];
    }
    if (empty($post['recruiter_firm']) && !empty($recruiter['firm'])) {
        $post['recruiter_firm'] = $recruiter['firm'];
    }
    if (empty($post['recruiter_email']) && !empty($recruiter['email'])) {
        $post['recruiter_email'] = $recruiter['email'];
    }
    if (empty($post['recruiter_linkedin']) && !empty($recruiter['linkedin_url'])) {
        $post['recruiter_linkedin'] = $recruiter['linkedin_url'];
    }
    if (empty($post['recruiter_photo_url']) && !empty($recruiter['photo_url'])) {
        $post['recruiter_photo_url'] = $recruiter['photo_url'];
    }
    if (empty($post['recruiter_photo']) && !empty($recruiter['photo_url'])) {
        $post['recruiter_photo'] = $recruiter['photo_url'];
    }

    return $post;
}

function sffc_crm_get_recruiter_full_name_for_alerts($post) {
    $raw_name = trim((string) ($post['recruiter_name'] ?? ''));
    if ($raw_name === '') {
        $raw_name = trim((string) ($post['recruiter_display_name'] ?? ''));
    }

    return $raw_name !== '' ? $raw_name : __('Hiring Team', 'senna-finance');
}

function sffc_crm_get_recruiter_masked_name_for_alerts($post) {
    $raw_name = sffc_crm_get_recruiter_full_name_for_alerts($post);

    if ($raw_name === __('Hiring Team', 'senna-finance')) {
        return __('Hiring Team', 'senna-finance');
    }

    $parts = preg_split('/\s+/', $raw_name);
    $first_name = trim((string) ($parts[0] ?? ''));
    if ($first_name === '') {
        return __('Hiring Team', 'senna-finance');
    }

    $last_initial = '';
    if (count($parts) > 1) {
        $last_part = trim((string) end($parts));
        if ($last_part !== '') {
            $last_initial = strtoupper(function_exists('mb_substr') ? mb_substr($last_part, 0, 1) : substr($last_part, 0, 1));
        }
    }

    if ($last_initial !== '') {
        return $first_name . ' ' . $last_initial . '.';
    }

    return $first_name;
}

function sffc_crm_get_recruiter_first_name_for_alerts($post) {
    $raw_name = trim((string) ($post['recruiter_name'] ?? ''));
    if ($raw_name === '') {
        $raw_name = trim((string) ($post['recruiter_display_name'] ?? ''));
    }
    if ($raw_name === '') {
        return __('Recruiter', 'senna-finance');
    }

    $parts = preg_split('/\s+/', $raw_name);
    $first_name = trim((string) ($parts[0] ?? ''));

    return $first_name !== '' ? $first_name : __('Recruiter', 'senna-finance');
}

function sffc_crm_get_recruiter_avatar_html_for_alerts($post, $size = 56) {
    $size = max(36, (int) $size);
    $photo_url = '';
    if (!empty($post['recruiter_photo_url'])) {
        $photo_url = esc_url($post['recruiter_photo_url']);
    } elseif (!empty($post['recruiter_photo'])) {
        $photo_url = esc_url($post['recruiter_photo']);
    }

    $masked_name = sffc_crm_get_recruiter_masked_name_for_alerts($post);
    $initial_source = trim((string) ($post['recruiter_name'] ?? $post['recruiter_display_name'] ?? $masked_name));
    $initial = strtoupper(function_exists('mb_substr') ? mb_substr($initial_source ?: 'R', 0, 1) : substr($initial_source ?: 'R', 0, 1));
    if ($initial === '') {
        $initial = 'R';
    }

    if ($photo_url !== '') {
        return '<img src="' . $photo_url . '" alt="' . esc_attr($masked_name) . '" style="width:' . (int) $size . 'px;height:' . (int) $size . 'px;border-radius:999px;object-fit:cover;display:block;">';
    }

    return '<span style="width:' . (int) $size . 'px;height:' . (int) $size . 'px;border-radius:999px;background:#0d353e;color:#ffffff;font-weight:700;font-size:' . max(18, (int) floor($size * 0.36)) . 'px;display:block;text-align:center;line-height:' . (int) $size . 'px;font-family:Arial,\'Helvetica Neue\',Helvetica,sans-serif;">' . esc_html($initial) . '</span>';
}

function sffc_crm_alert_email_is_arabic_locale($locale) {
    return strpos(strtolower((string) $locale), 'ar') === 0;
}

function sffc_crm_get_alert_email_locale($user) {
    if ($user instanceof WP_User && function_exists('get_user_locale')) {
        return (string) get_user_locale($user);
    }

    $user_id = is_object($user) ? (int) ($user->ID ?? 0) : (int) ($user['ID'] ?? 0);
    if ($user_id > 0 && function_exists('get_user_locale')) {
        return (string) get_user_locale($user_id);
    }

    return (string) get_locale();
}

function sffc_crm_alert_localize_email_copy($english, $arabic, $locale) {
    return sffc_crm_alert_email_is_arabic_locale($locale) ? $arabic : $english;
}

function sffc_crm_build_free_alert_digest_email_payload($user, $posts, $prefs, $args = []) {
    $recipient = is_object($user) ? $user->user_email : ($user['user_email'] ?? '');
    if (!$recipient || empty($posts)) {
        return false;
    }

    $prefs         = sffc_crm_normalize_alert_preferences($prefs);
    $logo_url      = esc_url('https://media.joinsenna.com/2026/02/senna111-1024x280.png');
    $sector_opts   = sffc_crm_get_sector_options();
    $terminal_url  = esc_url(!empty($args['cta_url']) ? $args['cta_url'] : sffc_crm_get_alert_digest_destination_url());
    $posts         = array_values((array) $posts);
    $total_matches = max(count($posts), (int) ($args['total_matches'] ?? count($posts)));
    $primary_post  = sffc_crm_enrich_alert_post_with_recruiter_details((array) $posts[0]);
    $locale        = sffc_crm_get_alert_email_locale($user);
    $is_arabic     = sffc_crm_alert_email_is_arabic_locale($locale);
    $dir           = $is_arabic ? 'rtl' : 'ltr';
    $align         = $is_arabic ? 'right' : 'left';

    $user_display_name = is_object($user) ? ($user->display_name ?? '') : ($user['display_name'] ?? '');
    $user_id = is_object($user) ? (int) ($user->ID ?? 0) : (int) ($user['ID'] ?? 0);
    $user_first_name = $user_id > 0 ? trim((string) get_user_meta($user_id, 'first_name', true)) : '';
    if ($user_first_name === '') {
        $user_first_name = trim((string) $user_display_name);
    }
    if ($user_first_name !== '') {
        $user_name_parts = preg_split('/\s+/', $user_first_name);
        $user_first_name = trim((string) ($user_name_parts[0] ?? $user_first_name));
    }
    if ($user_first_name === '') {
        $user_first_name = sffc_crm_alert_localize_email_copy(__('there', 'senna-finance'), 'هناك', $locale);
    }

    $extract_labels = static function ($raw, $allowed_types = []) {
        if (empty($raw)) {
            return [];
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $raw = $decoded;
            } else {
                $raw = preg_split('/[,;\n]+/', $raw);
            }
        }

        $labels = [];
        foreach ((array) $raw as $item) {
            $type = '';
            $label = '';
            if (is_array($item)) {
                $type = strtolower((string) ($item['type'] ?? $item['category'] ?? ''));
                $label = trim((string) ($item['label'] ?? $item['keyword'] ?? $item['name'] ?? $item['value'] ?? ''));
            } else {
                $label = trim((string) $item);
            }

            if ($label === '') {
                continue;
            }
            if (!empty($allowed_types) && $type !== '' && !in_array($type, $allowed_types, true)) {
                continue;
            }
            $label = sanitize_text_field($label);
            $labels[] = function_exists('mb_strtolower') ? mb_strtolower($label) : strtolower($label);
        }

        $labels = array_values(array_unique(array_filter($labels)));
        return array_slice($labels, 0, 5);
    };

    $role_label = trim((string) ($primary_post['role_title'] ?? sffc_crm_alert_localize_email_copy(__('New opportunity', 'senna-finance'), 'فرصة جديدة', $locale)));
    $company_label = trim((string) ($primary_post['company'] ?? ''));
    $location = trim((string) ($primary_post['location'] ?? ''));
    if ($location === '') {
        $location = trim(implode(', ', array_filter([
            $primary_post['location_city'] ?? '',
            $primary_post['location_country'] ?? '',
        ])));
    }
    if ($location === '' && !empty($prefs['locations'][0])) {
        $location = trim((string) $prefs['locations'][0]);
    }
    if ($location === '') {
        $location = sffc_crm_alert_localize_email_copy(__('your target location', 'senna-finance'), 'الموقع الذي تستهدفه', $locale);
    }

    $sector_key = trim((string) ($primary_post['sector'] ?? ''));
    $sector_label = $sector_key !== '' ? ($sector_opts[$sector_key] ?? ucwords(str_replace('_', ' ', $sector_key))) : '';
    $company_type = $sector_label !== ''
        ? ($is_arabic ? 'فريق ' . $sector_label : strtolower($sector_label) . ' team')
        : sffc_crm_alert_localize_email_copy(__('finance team', 'senna-finance'), 'فريق مالي', $locale);
    $experience_range = trim((string) ($primary_post['experience_years'] ?? ''));
    if ($experience_range !== '' && preg_match('/^\d+(?:\.\d+)?\+?$/', $experience_range)) {
        $experience_range = $is_arabic
            ? sprintf('خبرة %s سنوات', $experience_range)
            : sprintf(__('%s years experience', 'senna-finance'), $experience_range);
    } elseif ($experience_range !== '' && preg_match('/^\d+\s*-\s*\d+$/', $experience_range)) {
        $experience_range = $is_arabic
            ? sprintf('خبرة %s سنوات', $experience_range)
            : sprintf(__('%s years experience', 'senna-finance'), $experience_range);
    } elseif ($experience_range !== '' && stripos($experience_range, 'year') === false && stripos($experience_range, 'experience') === false) {
        $experience_range = $is_arabic
            ? sprintf('%s سنوات من الخبرة المناسبة', $experience_range)
            : sprintf(__('%s years of relevant experience', 'senna-finance'), $experience_range);
    }
    if ($experience_range === '') {
        $seniority = strtolower((string) ($primary_post['seniority'] ?? ''));
        $experience_map = [
            'analyst' => sffc_crm_alert_localize_email_copy(__('2-5 years of relevant experience', 'senna-finance'), 'من سنتين إلى 5 سنوات من الخبرة المناسبة', $locale),
            'associate' => sffc_crm_alert_localize_email_copy(__('2-5 years of relevant experience', 'senna-finance'), 'من سنتين إلى 5 سنوات من الخبرة المناسبة', $locale),
            'vp' => sffc_crm_alert_localize_email_copy(__('5-8 years of relevant experience', 'senna-finance'), 'من 5 إلى 8 سنوات من الخبرة المناسبة', $locale),
            'director' => sffc_crm_alert_localize_email_copy(__('8+ years of relevant experience', 'senna-finance'), '8 سنوات خبرة أو أكثر', $locale),
            'md' => sffc_crm_alert_localize_email_copy(__('10+ years of relevant experience', 'senna-finance'), '10 سنوات خبرة أو أكثر', $locale),
            'partner' => sffc_crm_alert_localize_email_copy(__('10+ years of relevant experience', 'senna-finance'), '10 سنوات خبرة أو أكثر', $locale),
            'c_level' => sffc_crm_alert_localize_email_copy(__('10+ years of relevant experience', 'senna-finance'), '10 سنوات خبرة أو أكثر', $locale),
        ];
        $experience_range = $experience_map[$seniority] ?? sffc_crm_alert_localize_email_copy(__('relevant finance experience', 'senna-finance'), 'خبرة مالية مناسبة', $locale);
    }

    $skills = $extract_labels($primary_post['skills_mentioned'] ?? [], ['skill', 'software', 'certification']);
    if (empty($skills)) {
        $skills = $extract_labels($primary_post['keywords'] ?? [], ['skill', 'software', 'certification']);
    }
    if (empty($skills)) {
        $skills = $extract_labels($primary_post['keywords'] ?? []);
    }
    if (empty($skills)) {
        $skills = [sffc_crm_alert_localize_email_copy(__('the required finance skill set', 'senna-finance'), 'المهارات المالية المطلوبة', $locale)];
    }
    $key_skills = implode(', ', array_slice($skills, 0, 5));

    $salary = trim((string) ($primary_post['salary_text'] ?? ''));
    if ($salary === '') {
        $salary_min = (int) ($primary_post['salary_min'] ?? 0);
        $salary_max = (int) ($primary_post['salary_max'] ?? 0);
        $currency = strtoupper((string) ($primary_post['salary_currency'] ?? ''));
        $symbols = ['GBP' => '£', 'USD' => '$', 'EUR' => '€', 'AED' => 'AED ', 'SAR' => 'SAR '];
        $symbol = $symbols[$currency] ?? ($currency !== '' ? $currency . ' ' : '');
        if ($salary_min > 0 && $salary_max > 0) {
            $salary = $symbol . number_format($salary_min) . ' - ' . $symbol . number_format($salary_max);
        } elseif ($salary_min > 0) {
            $salary = $symbol . number_format($salary_min) . '+';
        } elseif ($salary_max > 0) {
            $salary = $is_arabic ? sprintf('حتى %s', $symbol . number_format($salary_max)) : sprintf(__('Up to %s', 'senna-finance'), $symbol . number_format($salary_max));
        }
    }

    $details_rows = [
        sffc_crm_alert_localize_email_copy(__('Role', 'senna-finance'), 'الدور', $locale) => $role_label,
        sffc_crm_alert_localize_email_copy(__('Location', 'senna-finance'), 'الموقع', $locale) => $location,
        sffc_crm_alert_localize_email_copy(__('Experience', 'senna-finance'), 'الخبرة', $locale) => $experience_range,
        sffc_crm_alert_localize_email_copy(__('Relevant skills', 'senna-finance'), 'المهارات المناسبة', $locale) => $key_skills,
    ];
    if ($company_label !== '') {
        $details_rows = array_merge([sffc_crm_alert_localize_email_copy(__('Company', 'senna-finance'), 'الشركة', $locale) => $company_label], $details_rows);
    }
    if ($salary !== '') {
        $details_rows[sffc_crm_alert_localize_email_copy(__('Salary', 'senna-finance'), 'الراتب', $locale)] = $salary;
    }

    $details_html = '';
    foreach ($details_rows as $label => $value) {
        if (trim((string) $value) === '') {
            continue;
        }
        $details_html .= '<p style="margin:0 0 10px;color:#202124;font-size:14px;line-height:1.55;"><strong style="display:inline-block;min-width:120px;color:#202124;">' . esc_html($label) . ':</strong> <strong style="color:#202124;">' . esc_html($value) . '</strong></p>';
    }

    $subject_prefix = !empty($args['is_test']) ? sffc_crm_alert_localize_email_copy(__('Test: ', 'senna-finance'), 'اختبار: ', $locale) : '';
    $subject = $subject_prefix . ($is_arabic
        ? sprintf('فرصة جديدة: %1$s في %2$s', $role_label, $location)
        : sprintf(__('New Role: %1$s in %2$s', 'senna-finance'), $role_label, $location));
    $sender = function_exists('sffc_crm_get_email_sender') ? sffc_crm_get_email_sender('internship_alerts') : [];
    $sender_name = !empty($sender['from_name']) ? $sender['from_name'] : sffc_crm_alert_localize_email_copy(__('Emily @ MENA Careers', 'senna-finance'), 'إيميلي من سنّا', $locale);
    $from_email = !empty($sender['from_email']) ? sanitize_email($sender['from_email']) : sanitize_email(get_option('sffc_crm_sendgrid_alert_from_email', 'support.team@joinsenna.com'));
    $reply_to_name = !empty($sender['reply_to_name']) ? $sender['reply_to_name'] : $sender_name;
    $reply_to_email = !empty($sender['reply_to_email']) ? sanitize_email($sender['reply_to_email']) : $from_email;

    $role_esc = esc_html($role_label);
    $company_type_esc = esc_html($company_type);
    $location_esc = esc_html($location);
    $experience_esc = esc_html($experience_range);
    $skills_esc = esc_html($key_skills);
    $first_name_esc = esc_html($user_first_name);
    $sender_esc = esc_html($sender_name);
    $matches_context = $total_matches > 1
        ? esc_html($is_arabic
            ? sprintf('لقينا %d فرص مناسبة بحسب تفضيلاتك الحالية. وهذه أقوى فرصة نبدأ بها أولاً.', $total_matches)
            : sprintf(_n('We found %d relevant role from your current criteria. This is the strongest one to review first.', 'We found %d relevant roles from your current criteria. This is the strongest one to review first.', $total_matches, 'senna-finance'), $total_matches))
        : esc_html(sffc_crm_alert_localize_email_copy(__('This role was selected from your current MENA Careers criteria.', 'senna-finance'), 'تم اختيار هذه الفرصة بناءً على تفضيلاتك الحالية في سنّا.', $locale));

    $opportunity_for = esc_html($is_arabic ? 'فرصة مناسبة لـ ' . $user_first_name : 'Opportunity for ' . $user_first_name);
    $greeting = esc_html($is_arabic ? 'مرحباً ' . $user_first_name . '،' : 'Hi ' . $user_first_name . ',');
    $opening = esc_html($is_arabic ? 'أتمنى أن أمورك ماشية بشكل جيد.' : "I hope you're keeping well.");
    $role_intro = $is_arabic
        ? 'حددنا لك فرصة <strong>' . $role_esc . '</strong> مع <strong>' . $company_type_esc . '</strong> في <strong>' . $location_esc . '</strong>. ومن تفاصيل الدور، يبدو مناسباً لمن لديهم <strong>' . $experience_esc . '</strong>، خصوصاً مع خبرة في <strong>' . $skills_esc . '</strong>.'
        : 'We’ve identified a <strong>' . $role_esc . '</strong> opportunity with a <strong>' . $company_type_esc . '</strong> based in <strong>' . $location_esc . '</strong>. From the role details, it looks suited to candidates with <strong>' . $experience_esc . '</strong>, particularly with exposure to <strong>' . $skills_esc . '</strong>.';
    $platform_intro = esc_html($is_arabic
        ? 'بدلاً من الرجوع والإياب عبر البريد، وضعت لك تفاصيل الفرصة كاملة داخل منصة سنّا، بما فيها سياق الفريق والمسؤوليات:'
        : 'Rather than going back and forth over email, I’ve put the full role details, including team context and responsibilities, on the MENA Careers platform:');
    $cta_label = esc_html($is_arabic ? 'اعرض التفاصيل الكاملة وقدّم' : 'View full details and apply');
    $interest_copy = esc_html($is_arabic
        ? 'إذا كانت مناسبة لك، تقدر تبدي اهتمامك مباشرة من المنصة خلال دقائق، ونحن نكمل من هناك.'
        : 'If it looks relevant, you can express interest directly on the platform in a couple of clicks, and we’ll take it forward.');
    $fallback_copy = esc_html($is_arabic
        ? 'وإذا ما كانت هي المناسبة، ما عندك مشكلة. سأستمر في ترشيح فرص أقرب لخلفيتك.'
        : 'If it’s not quite right, no problem. I’ll keep you in mind for other roles that align more closely with your profile.');
    $signoff = esc_html($is_arabic ? 'مع خالص التحية،' : 'Best,');
    $footer_note = esc_html($is_arabic
        ? 'تم إرسال هذه الرسالة بخصوص فرصة قد تناسب تفضيلاتك الحالية في سنّا.'
        : 'This message was sent regarding a role that may match your current MENA Careers criteria.');

    $body =
        '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" dir="' . esc_attr($dir) . '" style="background:#f6f8fc;padding:32px 16px;margin:0;direction:' . esc_attr($dir) . ';text-align:' . esc_attr($align) . ';">' .
          '<tr>' .
            '<td align="center">' .
              '<table role="presentation" cellpadding="0" cellspacing="0" width="640" style="width:100%;max-width:640px;background:#ffffff;border:1px solid #dadce0;border-radius:8px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;color:#202124;direction:' . esc_attr($dir) . ';text-align:' . esc_attr($align) . ';">' .
                '<tr>' .
                  '<td style="padding:20px 24px;border-bottom:1px solid #eceff1;background:#ffffff;">' .
                    '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' .
                      '<tr>' .
                        '<td valign="middle">' .
                          '<img src="' . $logo_url . '" alt="MENA Careers" style="display:block;height:22px;width:auto;">' .
                        '</td>' .
                        '<td valign="middle" align="right" style="font-size:14px;color:#5f6368;line-height:1.4;">' . $opportunity_for . '</td>' .
                      '</tr>' .
                    '</table>' .
                  '</td>' .
                '</tr>' .
                '<tr>' .
                  '<td style="padding:32px 24px;font-size:15px;line-height:1.7;color:#202124;">' .
                    '<p style="margin:0 0 16px;">' . $greeting . '</p>' .
                    '<p style="margin:0 0 16px;">' . $opening . '</p>' .
                    '<p style="margin:0 0 16px;">' . $role_intro . '</p>' .
                    '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:20px 0 24px;background:#f8f9fa;border:1px solid #e8eaed;border-radius:6px;">' .
                      '<tr><td style="padding:16px 18px;">' . $details_html . '</td></tr>' .
                    '</table>' .
                    '<p style="margin:0 0 16px;color:#5f6368;">' . $matches_context . '</p>' .
                    '<p style="margin:0 0 16px;">' . $platform_intro . '</p>' .
                    '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px 0;">' .
                      '<tr><td><a href="' . $terminal_url . '" style="display:inline-block;background:#0d6955;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:6px;font-size:14px;font-weight:bold;">' . $cta_label . '</a></td></tr>' .
                    '</table>' .
                    '<p style="margin:0 0 16px;">' . $interest_copy . '</p>' .
                    '<p style="margin:0 0 16px;">' . $fallback_copy . '</p>' .
                    '<p style="margin:28px 0 0;">' . $signoff . '<br>' . $sender_esc . '</p>' .
                  '</td>' .
                '</tr>' .
                '<tr>' .
                  '<td style="padding:16px 24px 24px;font-size:12px;color:#5f6368;line-height:1.6;">' . $footer_note . '</td>' .
                '</tr>' .
              '</table>' .
            '</td>' .
          '</tr>' .
        '</table>';

    return [
        'recipient'  => $recipient,
        'subject'    => $subject,
        'body'       => $body,
        'headers'    => [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $sender_name . ' <' . $from_email . '>',
            'Reply-To: ' . $reply_to_name . ' <' . $reply_to_email . '>',
        ],
        'categories' => ['internship_alert_digest', 'instant_intro'],
    ];
}

function sffc_crm_send_free_alert_digest_email($user, $posts, $prefs, $args = []) {
    if (sffc_crm_alert_email_sending_disabled() || sffc_crm_internship_alerts_disabled()) {
        return false;
    }

    $payload = sffc_crm_build_free_alert_digest_email_payload($user, $posts, $prefs, $args);
    if (!$payload) {
        return false;
    }

    $sendgrid = class_exists('SFFC_CRM_SendGrid_Service') ? SFFC_CRM_SendGrid_Service::get_instance() : null;
    if ($sendgrid && $sendgrid->is_configured()) {
        $result = $sendgrid->send_email(
            $payload['subject'],
            $payload['body'],
            $payload['recipient'],
            null,
            [
                'user_id' => is_object($user) ? $user->ID : intval($user['ID'] ?? 0),
                'digest'  => 'free_alerts',
            ],
            $payload['categories'] ?? ['internship_alert_digest'],
            'internship_alerts'
        );

        return !is_wp_error($result);
    }

    return wp_mail(
        $payload['recipient'],
        $payload['subject'],
        $payload['body'],
        $payload['headers']
    );
}

/**
 * Build alert email payload for a user
 */
function sffc_crm_build_alert_email_payload($user, $post, $args = []) {
    if (empty($post) || empty($post['role_title']) || empty($post['company'])) {
        return false;
    }

    $post = sffc_crm_enrich_alert_post_with_recruiter_details($post);
    $payload = sffc_crm_build_free_alert_digest_email_payload($user, [$post], [], array_merge($args, [
        'total_matches' => 1,
    ]));

    if (!empty($payload)) {
        $payload['categories'] = ['role_alert', 'instant_intro'];
    }

    return $payload;
}

/**
 * Send alert email to a user
 */
function sffc_crm_send_alert_email($user, $post, $args = []) {
    if (sffc_crm_alert_email_sending_disabled() || sffc_crm_internship_alerts_disabled()) {
        return false;
    }

    $payload = sffc_crm_build_alert_email_payload($user, $post, $args);
    if (!$payload) {
        return false;
    }

    return wp_mail(
        $payload['recipient'],
        $payload['subject'],
        $payload['body'],
        $payload['headers']
    );
}

/**
 * Dispatch alerts for a post
 */
function sffc_crm_dispatch_internship_alerts($post_id) {
    if (sffc_crm_internship_alerts_disabled()) {
        return [
            'queued' => 0,
            'matched' => 0,
            'digest_matched' => 0,
            'disabled' => true,
        ];
    }

    if (!$post_id || !class_exists('SFFC_CRM_Internship_Alert_Queue')) {
        return [
            'queued' => 0,
            'matched' => 0,
        ];
    }

    return SFFC_CRM_Internship_Alert_Queue::get_instance()->enqueue_post_alerts($post_id);
}

/**
 * Fetch a sample internship post for testing
 */
function sffc_crm_get_sample_internship_post() {
    global $wpdb;
    $table = $wpdb->prefix . 'sffc_crm_posts';
    $row = $wpdb->get_row("SELECT * FROM {$table} ORDER BY posted_at DESC LIMIT 1", ARRAY_A);

    if ($row) {
        return $row;
    }

    return [
        'role_title' => __('Investment Banking Summer Analyst', 'senna-finance'),
        'company' => 'Global Bank',
        'location' => 'London, United Kingdom',
        'sector' => 'ib',
        'content' => __('Join our 10-week summer analyst program working alongside live M&A transactions and client pitches across EMEA.', 'senna-finance'),
        'application_url' => home_url('/'),
    ];
}

function sffc_crm_get_sample_internship_digest_posts($limit = 5) {
    global $wpdb;

    $limit = max(1, min(3, intval($limit)));
    $table = $wpdb->prefix . 'sffc_crm_posts';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT *
         FROM {$table}
         WHERE is_active = 1
           AND admin_approved = 1
         ORDER BY posted_at DESC, id DESC
         LIMIT %d",
        $limit
    ), ARRAY_A);

    $posts = [];
    foreach ((array) $rows as $row) {
        if (!sffc_crm_alert_post_is_internship($row)) {
            continue;
        }
        $posts[] = $row;
    }

    if (!empty($posts)) {
        return $posts;
    }

    $sample = sffc_crm_get_sample_internship_post();
    $fallback = [];
    for ($i = 0; $i < $limit; $i++) {
        $entry = $sample;
        $entry['id'] = $i + 1;
        $entry['posted_at'] = date('Y-m-d H:i:s', current_time('timestamp') - ($i * 3 * HOUR_IN_SECONDS));
        if ($i === 1) {
            $entry['role_title'] = __('Private Equity Summer Analyst', 'senna-finance');
            $entry['company'] = 'Northern Capital';
            $entry['location'] = 'London, United Kingdom';
        } elseif ($i === 2) {
            $entry['role_title'] = __('Investment Banking Associate', 'senna-finance');
            $entry['company'] = 'Atlas Partners';
            $entry['location'] = 'Paris, France';
        } elseif ($i === 3) {
            $entry['role_title'] = __('Growth Equity Vice President', 'senna-finance');
            $entry['company'] = 'Vertex Growth';
            $entry['location'] = 'Berlin, Germany';
        }
        $fallback[] = $entry;
    }

    return $fallback;
}

/**
 * Format salary range for display
 *
 * @param int $user_id User ID
 * @return string Formatted salary range (e.g., "£50,000 - £70,000")
 */
function sffc_crm_format_salary_range($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return '';
    }

    $salary_min = get_user_meta($user_id, 'sffc_crm_salary_min', true);
    $salary_max = get_user_meta($user_id, 'sffc_crm_salary_max', true);
    $currency = get_user_meta($user_id, 'sffc_crm_salary_currency', true) ?: 'GBP';

    if (!$salary_min && !$salary_max) {
        return '';
    }

    $currency_symbols = [
        'GBP' => '£',
        'USD' => '$',
        'EUR' => '€',
        'CHF' => 'CHF ',
    ];

    $symbol = $currency_symbols[$currency] ?? $currency . ' ';

    if ($salary_min && $salary_max) {
        return $symbol . number_format($salary_min) . ' - ' . $symbol . number_format($salary_max);
    } elseif ($salary_min) {
        return $symbol . number_format($salary_min) . '+';
    } else {
        return 'Up to ' . $symbol . number_format($salary_max);
    }
}

/**
 * Delete all job preferences for a user
 *
 * @param int $user_id User ID
 * @return bool Success
 */
function sffc_crm_delete_job_preferences($user_id) {
    if (!$user_id) {
        return false;
    }

    $meta_keys = [
        'sffc_crm_target_roles',
        'sffc_crm_target_sectors',
        'sffc_crm_target_seniority',
        'sffc_crm_target_locations',
        'sffc_crm_target_countries',
        'sffc_crm_salary_min',
        'sffc_crm_salary_max',
        'sffc_crm_salary_currency',
        'sffc_crm_work_arrangement',
        'sffc_crm_internship_duration',
        'sffc_crm_start_date_pref',
    ];

    foreach ($meta_keys as $key) {
        delete_user_meta($user_id, $key);
    }

    return true;
}
