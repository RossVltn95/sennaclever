<?php
/**
 * CRM Post Model
 * Handles recruiter opportunity posts
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Post {

    private $table;
    private $recruiters_table;
    private $saved_table;
    private $activity_table;
    private static $columns_ready = false;
    private const COLUMNS_READY_OPTION = 'sffc_crm_posts_columns_ready_v4';

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sffc_crm_posts';
        $this->recruiters_table = $wpdb->prefix . 'sffc_crm_recruiters';
        $this->saved_table = $wpdb->prefix . 'sffc_crm_saved_posts';
        $this->activity_table = $wpdb->prefix . 'sffc_crm_activity';

        $this->maybe_prepare_columns();
    }

    private function maybe_prepare_columns() {
        if (self::$columns_ready) {
            return;
        }

        global $wpdb;

        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->table));
        if ($table_exists !== $this->table) {
            return;
        }

        $existing_columns = array_map('strval', (array) $wpdb->get_col("SHOW COLUMNS FROM {$this->table}", 0));
        $required_columns = [
            'publish_to_jobs' => "ADD COLUMN publish_to_jobs tinyint(1) DEFAULT 1",
            'company_logo' => "ADD COLUMN company_logo varchar(500) DEFAULT NULL",
            'source_platform' => "ADD COLUMN source_platform varchar(80) DEFAULT NULL",
            'source_platform_custom' => "ADD COLUMN source_platform_custom varchar(120) DEFAULT NULL",
            'exclude_from_early_bird' => "ADD COLUMN exclude_from_early_bird tinyint(1) DEFAULT 0",
            'post_status' => "ADD COLUMN post_status enum('open','closed') DEFAULT 'open'",
            'is_early_bird' => "ADD COLUMN is_early_bird tinyint(1) DEFAULT 0",
            'application_url' => "ADD COLUMN application_url varchar(500) DEFAULT NULL",
            'source_url' => "ADD COLUMN source_url varchar(500) DEFAULT NULL",
            'wp_post_id' => "ADD COLUMN wp_post_id bigint(20) DEFAULT NULL",
            'jobs_post_id' => "ADD COLUMN jobs_post_id bigint(20) DEFAULT NULL",
            'keywords' => "ADD COLUMN keywords longtext DEFAULT NULL",
            'recruiter_display_name' => "ADD COLUMN recruiter_display_name varchar(255) DEFAULT NULL",
            'recruiter_display_company' => "ADD COLUMN recruiter_display_company varchar(255) DEFAULT NULL",
            'response_label' => "ADD COLUMN response_label varchar(100) DEFAULT NULL",
            'response_badge' => "ADD COLUMN response_badge varchar(100) DEFAULT NULL",
            'jobseeker_notes' => "ADD COLUMN jobseeker_notes text DEFAULT NULL",
            'knockout_questions' => "ADD COLUMN knockout_questions longtext DEFAULT NULL",
            'materials' => "ADD COLUMN materials longtext DEFAULT NULL",
            'interview_questions' => "ADD COLUMN interview_questions longtext DEFAULT NULL",
            'interview_questions_docx' => "ADD COLUMN interview_questions_docx varchar(500) DEFAULT NULL",
            'cv_template_docx' => "ADD COLUMN cv_template_docx varchar(500) DEFAULT NULL",
            'cover_letter_html' => "ADD COLUMN cover_letter_html longtext DEFAULT NULL",
            'cover_letter_docx' => "ADD COLUMN cover_letter_docx varchar(500) DEFAULT NULL",
            'case_study_pdf' => "ADD COLUMN case_study_pdf varchar(500) DEFAULT NULL",
            'opening_date' => "ADD COLUMN opening_date varchar(100) DEFAULT NULL",
            'closing_date' => "ADD COLUMN closing_date varchar(100) DEFAULT NULL",
            'starting_date' => "ADD COLUMN starting_date varchar(100) DEFAULT NULL",
            'duration' => "ADD COLUMN duration varchar(100) DEFAULT NULL",
            'application_process' => "ADD COLUMN application_process longtext DEFAULT NULL",
            'team_contacts' => "ADD COLUMN team_contacts longtext DEFAULT NULL",
        ];

        foreach ($required_columns as $column => $definition) {
            if (in_array($column, $existing_columns, true)) {
                continue;
            }

            $wpdb->query("ALTER TABLE {$this->table} {$definition}");
            if ($wpdb->last_error) {
                error_log(sprintf('SFFC CRM Post schema repair failed for %s.%s: %s', $this->table, $column, $wpdb->last_error));
            } else {
                $existing_columns[] = $column;
            }
        }

        $recruiters_table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->recruiters_table));
        if ($recruiters_table_exists === $this->recruiters_table) {
            $recruiter_columns = array_map('strval', (array) $wpdb->get_col("SHOW COLUMNS FROM {$this->recruiters_table}", 0));
            if (!in_array('default_company_logo', $recruiter_columns, true)) {
                $wpdb->query("ALTER TABLE {$this->recruiters_table} ADD COLUMN default_company_logo varchar(500) DEFAULT NULL");
                if ($wpdb->last_error) {
                    error_log(sprintf('SFFC CRM Post schema repair failed for %s.default_company_logo: %s', $this->recruiters_table, $wpdb->last_error));
                }
            }
        }

        self::$columns_ready = true;
        update_option(self::COLUMNS_READY_OPTION, '1', false);
    }

    /**
     * Get post by ID
     */
    public function get($id) {
        global $wpdb;

        $post = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, r.name as recruiter_name, r.firm as recruiter_firm,
                    r.photo_url as recruiter_photo, r.title as recruiter_title,
                    r.email as recruiter_email, r.linkedin_url as recruiter_linkedin
             FROM {$this->table} p
             LEFT JOIN {$this->recruiters_table} r ON r.id = p.recruiter_id
             WHERE p.id = %d",
            $id
        ), ARRAY_A);

        if ($post) {
            $post['requirements'] = json_decode($post['requirements'], true) ?: [];
            $post['skills_mentioned'] = json_decode($post['skills_mentioned'], true) ?: [];
            $post['knockout_questions'] = json_decode($post['knockout_questions'], true) ?: [];
            $post['application_process'] = json_decode($post['application_process'] ?? '[]', true) ?: [];
            $post['team_contacts'] = json_decode($post['team_contacts'] ?? '[]', true) ?: [];
            $post['materials'] = json_decode($post['materials'] ?? '[]', true) ?: [];
        }

        return $post;
    }

    /**
     * Get post with user context (saved status, etc.)
     */
    public function get_with_user_context($id, $user_id) {
        $post = $this->get($id);

        if (!$post) {
            return null;
        }

        if ($user_id) {
            global $wpdb;

            $saved = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->saved_table} WHERE user_id = %d AND post_id = %d",
                $user_id,
                $id
            ), ARRAY_A);

            $post['is_saved'] = !empty($saved);
            $post['saved_folder'] = $saved ? $saved['folder'] : null;
            $post['user_notes'] = $saved ? $saved['notes'] : null;
        }

        // Log view activity
        if ($user_id) {
            $this->log_activity($user_id, $post['recruiter_id'], $id, 'post_viewed');
        }

        return $post;
    }

    /**
     * Get feed of posts with filtering
     */
    public function get_feed($args = []) {
        global $wpdb;

        $defaults = [
            'page' => 1,
            'per_page' => 20,
            'sector' => null,
            'seniority' => null,
            'location' => null,
            'location_country' => null,
            'search' => null,
            'role_title' => null,
            'recruiter_name' => null,
            'post_status' => null,
            'recruiter_id' => null,
            'min_salary' => null,
            'max_salary' => null,
            'is_remote' => null,
            'posted_after' => null,
            'orderby' => 'posted_at',
            'order' => 'DESC',
            'approved_only' => true,
            'user_id' => null, // For checking saved status
            'keywords' => null,
            'start_date' => null,
            'duration_months' => null,
        ];

        $args = array_merge($defaults, $args);

        if (!empty($args['user_id']) && function_exists('sffc_crm_user_is_excluded') && sffc_crm_user_is_excluded((int) $args['user_id'])) {
            return [];
        }

        $where = ["p.is_active = 1"];
        $params = [];

        if ($args['post_status']) {
            if ($args['post_status'] === 'open') {
                $where[] = "(p.post_status IS NULL OR p.post_status = 'open')";
            } else {
                $where[] = "p.post_status = %s";
                $params[] = $args['post_status'];
            }
        } else {
            // Filter out closed posts from the feed by default
            $where[] = "(p.post_status IS NULL OR p.post_status = 'open')";
        }

        if ($args['approved_only']) {
            $where[] = "p.admin_approved = 1";
        }

        if ($args['sector']) {
            $where[] = "p.sector = %s";
            $params[] = $args['sector'];
        }

        if ($args['seniority']) {
            $where[] = "p.seniority = %s";
            $params[] = $args['seniority'];
        }

        if ($args['location']) {
            $where[] = "(p.location LIKE %s OR p.location_city LIKE %s OR p.location_country LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['location']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }

        if ($args['location_country']) {
            $where[] = "p.location_country = %s";
            $params[] = $args['location_country'];
        }

        if ($args['search']) {
            $where[] = "(p.role_title LIKE %s OR p.content LIKE %s OR p.company LIKE %s OR r.name LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }

        if ($args['role_title']) {
            $where[] = "p.role_title LIKE %s";
            $params[] = '%' . $wpdb->esc_like($args['role_title']) . '%';
        }

        if ($args['recruiter_name']) {
            $where[] = "(r.name LIKE %s OR r.firm LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['recruiter_name']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }

        if ($args['keywords']) {
            $keyword_terms = preg_split('/[\s,]+/', $args['keywords']);
            $keyword_terms = array_filter(array_map('trim', (array) $keyword_terms));
            foreach ($keyword_terms as $keyword_term) {
                if ($keyword_term === '') {
                    continue;
                }
                $like = '%' . $wpdb->esc_like($keyword_term) . '%';
                $where[] = "(p.keywords LIKE %s OR p.role_title LIKE %s OR p.company LIKE %s OR p.content LIKE %s)";
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }
        }

        if ($args['recruiter_id']) {
            $where[] = "p.recruiter_id = %d";
            $params[] = $args['recruiter_id'];
        }

        if ($args['min_salary']) {
            $where[] = "p.salary_max >= %d";
            $params[] = $args['min_salary'];
        }

        if ($args['max_salary']) {
            $where[] = "p.salary_min <= %d";
            $params[] = $args['max_salary'];
        }

        if ($args['is_remote'] !== null) {
            $where[] = "p.is_remote = %d";
            $params[] = $args['is_remote'] ? 1 : 0;
        }

        if ($args['posted_after']) {
            $where[] = "p.posted_at >= %s";
            $params[] = $args['posted_after'];
        }

        if ($args['start_date']) {
            $where[] = "(p.starting_date IS NOT NULL AND p.starting_date >= %s)";
            $params[] = $args['start_date'];
        }

        $where_clause = implode(' AND ', $where);
        $offset = ($args['page'] - 1) * $args['per_page'];

        // Build saved subquery if user_id provided
        $saved_join = "";
        $saved_select = ", 0 as is_saved";
        if ($args['user_id']) {
            $saved_join = $wpdb->prepare(
                "LEFT JOIN {$this->saved_table} sp ON sp.post_id = p.id AND sp.user_id = %d",
                $args['user_id']
            );
            $saved_select = ", IF(sp.id IS NOT NULL, 1, 0) as is_saved";
        }

        // Add new columns (columns confirmed to exist)
        $new_columns = ", p.response_badge, p.jobseeker_notes, p.materials";

        $sql = "SELECT p.id, p.role_title, p.company, p.location, p.location_city, p.location_country,
                       p.salary_min, p.salary_max, p.salary_currency, p.salary_text,
                       p.seniority, p.sector, p.experience_years, p.content, p.content_snippet,
                       p.admin_approved, p.is_active, p.post_status, p.source, p.source_platform,
                       p.source_platform_custom, p.publish_to_jobs, p.created_at, p.updated_at, p.posted_at,
                       p.is_remote, p.is_hybrid, p.engagement_count, p.is_featured, p.is_early_bird, p.exclude_from_early_bird,
                       p.application_url, p.source_url, p.wp_post_id, p.jobs_post_id, p.keywords,
                       COALESCE(NULLIF(p.company_logo, ''), r.default_company_logo) AS company_logo,
                       p.recruiter_display_name, p.recruiter_display_company,
                       p.response_label{$new_columns},
                       p.interview_questions, p.interview_questions_docx, p.cv_template_docx,
                       p.cover_letter_html, p.cover_letter_docx, p.case_study_pdf,
                       p.opening_date, p.closing_date, p.starting_date, p.duration,
                       p.knockout_questions, p.application_process, p.team_contacts,
                       r.id as recruiter_id, r.name as recruiter_name, r.firm as recruiter_firm,
                       r.photo_url as recruiter_photo, r.title as recruiter_title,
                       r.email as recruiter_email, r.linkedin_url as recruiter_linkedin,
                       r.phone as recruiter_phone,
                       COALESCE(open_roles.open_roles_count, 0) as recruiter_open_roles_count
                       {$saved_select}
                FROM {$this->table} p
                LEFT JOIN {$this->recruiters_table} r ON r.id = p.recruiter_id
                LEFT JOIN (
                    SELECT recruiter_id, COUNT(*) AS open_roles_count
                    FROM {$this->table}
                    WHERE is_active = 1 OR is_active IS NULL
                    GROUP BY recruiter_id
                ) open_roles ON open_roles.recruiter_id = p.recruiter_id
                {$saved_join}
                WHERE {$where_clause}
                ORDER BY p.is_featured DESC, p.{$args['orderby']} {$args['order']}
                LIMIT %d OFFSET %d";

        $params[] = $args['per_page'];
        $params[] = $offset;

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        // Debug logging
        error_log('SFFC CRM Feed Query: ' . $sql);

        $results = $wpdb->get_results($sql, ARRAY_A);

        // Log any SQL errors
        if ($wpdb->last_error) {
            error_log('SFFC CRM Feed SQL Error: ' . $wpdb->last_error);
        }

        error_log('SFFC CRM Feed Results Count: ' . count($results));

        // Convert is_early_bird to proper boolean integer for JavaScript
        foreach ($results as &$row) {
            if (isset($row['is_early_bird'])) {
                $row['is_early_bird'] = (int) $row['is_early_bird'];
            }
            if (isset($row['exclude_from_early_bird'])) {
                $row['exclude_from_early_bird'] = (int) $row['exclude_from_early_bird'];
            }
        }

        if ($args['duration_months'] !== null) {
            $results = $this->filter_results_by_duration($results, (float) $args['duration_months']);
        }

        return $results;
    }

    /**
     * Get feed filtered by post groups
     *
     * @param string|array $group_slugs Group slug(s) to filter by
     * @param array $args Additional filter arguments
     * @return array
     */
    public function get_feed_by_groups($group_slugs, $args = []) {
        global $wpdb;

        if (empty($group_slugs)) {
            return $this->get_feed($args);
        }

        // Ensure array
        if (!is_array($group_slugs)) {
            $group_slugs = [$group_slugs];
        }

        // Convert slugs to IDs
        $placeholders = implode(',', array_fill(0, count($group_slugs), '%s'));
        $query = "SELECT id FROM {$wpdb->prefix}sffc_crm_post_groups WHERE slug IN ({$placeholders})";
        $group_ids = $wpdb->get_col($wpdb->prepare($query, $group_slugs));

        if (empty($group_ids)) {
            return [];
        }

        // Set up defaults
        $defaults = [
            'page' => 1,
            'per_page' => 20,
            'orderby' => 'posted_at',
            'order' => 'DESC',
            'user_id' => null,
            'location' => null,
            'keywords' => null,
            'start_date' => null,
            'duration_months' => null,
        ];

        $args = array_merge($defaults, $args);

        // Build saved subquery if user_id provided
        $saved_join = "";
        $saved_select = ", 0 as is_saved";
        if ($args['user_id']) {
            $saved_join = $wpdb->prepare(
                "LEFT JOIN {$this->saved_table} sp ON sp.post_id = p.id AND sp.user_id = %d",
                $args['user_id']
            );
            $saved_select = ", IF(sp.id IS NOT NULL, 1, 0) as is_saved";
        }

        // Add new columns (columns confirmed to exist)
        $new_columns = ", p.response_badge, p.jobseeker_notes, p.materials";

        $offset = ($args['page'] - 1) * $args['per_page'];

        $where = [
            'pgr.group_id IN (' . implode(',', array_fill(0, count($group_ids), '%d')) . ')',
            "p.is_active = 1",
            "p.admin_approved = 1",
            "(p.post_status IS NULL OR p.post_status = 'open')",
        ];

        $params = $group_ids;

        if (!empty($args['location'])) {
            $where[] = "(p.location LIKE %s OR p.location_city LIKE %s OR p.location_country LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['location']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }

        if (!empty($args['keywords'])) {
            $keyword_terms = preg_split('/[\s,]+/', $args['keywords']);
            $keyword_terms = array_filter(array_map('trim', (array) $keyword_terms));
            foreach ($keyword_terms as $keyword_term) {
                if ($keyword_term === '') {
                    continue;
                }
                $like = '%' . $wpdb->esc_like($keyword_term) . '%';
                $where[] = "(p.keywords LIKE %s OR p.role_title LIKE %s OR p.company LIKE %s OR p.content LIKE %s)";
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }
        }

        if (!empty($args['start_date'])) {
            $where[] = "(p.starting_date IS NOT NULL AND p.starting_date >= %s)";
            $params[] = $args['start_date'];
        }

        $where_clause = implode(' AND ', $where);

        $sql = "SELECT DISTINCT p.id, p.role_title, p.company, p.location, p.location_country,
                       p.salary_min, p.salary_max, p.salary_currency, p.salary_text,
                       p.seniority, p.sector, p.experience_years, p.content, p.content_snippet, p.posted_at,
                       p.is_remote, p.is_hybrid, p.engagement_count, p.is_featured, p.is_early_bird, p.exclude_from_early_bird,
                       p.application_url, p.source_url, p.wp_post_id, p.jobs_post_id, p.keywords,
                       COALESCE(NULLIF(p.company_logo, ''), r.default_company_logo) AS company_logo,
                       p.recruiter_display_name, p.recruiter_display_company,
                       p.response_label{$new_columns},
                       p.interview_questions, p.interview_questions_docx, p.cv_template_docx,
                       p.cover_letter_html, p.cover_letter_docx, p.case_study_pdf,
                       p.opening_date, p.closing_date, p.starting_date, p.duration,
                       p.knockout_questions, p.application_process, p.team_contacts,
                       r.id as recruiter_id, r.name as recruiter_name, r.firm as recruiter_firm,
                       r.photo_url as recruiter_photo, r.title as recruiter_title,
                       r.email as recruiter_email, r.linkedin_url as recruiter_linkedin
                       {$saved_select}
                FROM {$this->table} p
                INNER JOIN {$wpdb->prefix}sffc_crm_post_group_relationships pgr ON p.id = pgr.post_id
                LEFT JOIN {$this->recruiters_table} r ON r.id = p.recruiter_id
                {$saved_join}
                WHERE {$where_clause}
                ORDER BY p.is_featured DESC, p.{$args['orderby']} {$args['order']}
                LIMIT %d OFFSET %d";

        // Prepare parameters: group_ids, filters, per_page, offset
        $prepare_params = array_merge($params, [$args['per_page'], $offset]);
        $sql = $wpdb->prepare($sql, $prepare_params);

        $results = $wpdb->get_results($sql, ARRAY_A);

        // Convert is_early_bird to proper boolean integer
        foreach ($results as &$row) {
            if (isset($row['is_early_bird'])) {
                $row['is_early_bird'] = (int) $row['is_early_bird'];
            }
            if (isset($row['exclude_from_early_bird'])) {
                $row['exclude_from_early_bird'] = (int) $row['exclude_from_early_bird'];
            }
        }

        if ($args['duration_months'] !== null) {
            $results = $this->filter_results_by_duration($results, (float) $args['duration_months']);
        }

        return $results;
    }

    /**
     * Get posts matching user's job preferences (ANY criteria - OR logic)
     *
     * @param int $user_id User ID
     * @param array $args Additional filter args (page, per_page)
     * @return array Posts matching ANY user preference
     */
    public function get_preference_matches($user_id, $args = []) {
        global $wpdb;

        if (!$user_id) {
            return [];
        }

        // Get user preferences
        $target_sectors = get_user_meta($user_id, 'sffc_crm_target_sectors', true) ?: [];
        $target_seniority = get_user_meta($user_id, 'sffc_crm_target_seniority', true) ?: [];
        $target_countries = get_user_meta($user_id, 'sffc_crm_target_countries', true) ?: [];
        $target_locations = get_user_meta($user_id, 'sffc_crm_target_locations', true) ?: [];
        $salary_min = get_user_meta($user_id, 'sffc_crm_salary_min', true);
        $salary_max = get_user_meta($user_id, 'sffc_crm_salary_max', true);
        $work_arrangement = get_user_meta($user_id, 'sffc_crm_work_arrangement', true) ?: [];
        $open_to_types = get_user_meta($user_id, 'sffc_crm_open_to_types', true) ?: [];

        // Build WHERE conditions (OR logic for ANY match)
        $or_conditions = [];
        $params = [];

        // "Open to" filtering by role type keywords in role_title
        if (!empty($open_to_types) && is_array($open_to_types)) {
            $role_type_conditions = [];

            foreach ($open_to_types as $type) {
                if ($type === 'internships') {
                    // Match: internship, intern, summer, off-cycle, insight
                    $role_type_conditions[] = "(p.role_title LIKE %s OR p.role_title LIKE %s OR p.role_title LIKE %s OR p.role_title LIKE %s OR p.role_title LIKE %s)";
                    $params[] = '%' . $wpdb->esc_like('intern') . '%';
                    $params[] = '%' . $wpdb->esc_like('summer') . '%';
                    $params[] = '%' . $wpdb->esc_like('off-cycle') . '%';
                    $params[] = '%' . $wpdb->esc_like('off cycle') . '%';
                    $params[] = '%' . $wpdb->esc_like('insight') . '%';
                } elseif ($type === 'graduate_roles') {
                    // Match: graduate, trainee, entry level
                    $role_type_conditions[] = "(p.role_title LIKE %s OR p.role_title LIKE %s OR p.role_title LIKE %s)";
                    $params[] = '%' . $wpdb->esc_like('graduate') . '%';
                    $params[] = '%' . $wpdb->esc_like('trainee') . '%';
                    $params[] = '%' . $wpdb->esc_like('entry level') . '%';
                } elseif ($type === 'analyst_positions') {
                    // Match: analyst, junior, associate
                    $role_type_conditions[] = "(p.role_title LIKE %s OR p.role_title LIKE %s OR p.role_title LIKE %s)";
                    $params[] = '%' . $wpdb->esc_like('analyst') . '%';
                    $params[] = '%' . $wpdb->esc_like('junior') . '%';
                    $params[] = '%' . $wpdb->esc_like('associate') . '%';
                } elseif ($type === 'other_roles') {
                    // Other roles = NOT internships AND NOT graduate roles AND NOT explicitly analyst
                    // (roles that don't contain any of the above keywords)
                    $role_type_conditions[] = "(p.role_title NOT LIKE %s AND p.role_title NOT LIKE %s AND p.role_title NOT LIKE %s AND p.role_title NOT LIKE %s AND p.role_title NOT LIKE %s AND p.role_title NOT LIKE %s AND p.role_title NOT LIKE %s)";
                    $params[] = '%' . $wpdb->esc_like('intern') . '%';
                    $params[] = '%' . $wpdb->esc_like('summer') . '%';
                    $params[] = '%' . $wpdb->esc_like('graduate') . '%';
                    $params[] = '%' . $wpdb->esc_like('trainee') . '%';
                    $params[] = '%' . $wpdb->esc_like('entry level') . '%';
                    $params[] = '%' . $wpdb->esc_like('insight') . '%';
                    $params[] = '%' . $wpdb->esc_like('off-cycle') . '%';
                }
            }

            if (!empty($role_type_conditions)) {
                $or_conditions[] = '(' . implode(' OR ', $role_type_conditions) . ')';
            }
        }

        // Sector match
        if (!empty($target_sectors) && is_array($target_sectors)) {
            $placeholders = implode(',', array_fill(0, count($target_sectors), '%s'));
            $or_conditions[] = "p.sector IN ($placeholders)";
            $params = array_merge($params, $target_sectors);
        }

        // Seniority match
        if (!empty($target_seniority) && is_array($target_seniority)) {
            $placeholders = implode(',', array_fill(0, count($target_seniority), '%s'));
            $or_conditions[] = "p.seniority IN ($placeholders)";
            $params = array_merge($params, $target_seniority);
        }

        // Country match
        if (!empty($target_countries) && is_array($target_countries)) {
            $placeholders = implode(',', array_fill(0, count($target_countries), '%s'));
            $or_conditions[] = "p.location_country IN ($placeholders)";
            $params = array_merge($params, $target_countries);
        }

        // Location city match (partial match)
        if (!empty($target_locations) && is_array($target_locations)) {
            $location_conditions = [];
            foreach ($target_locations as $location) {
                $location_conditions[] = "p.location LIKE %s";
                $params[] = '%' . $wpdb->esc_like($location) . '%';
            }
            if (!empty($location_conditions)) {
                $or_conditions[] = '(' . implode(' OR ', $location_conditions) . ')';
            }
        }

        // Salary range overlap (post salary overlaps with user's target range)
        if ($salary_min && $salary_max) {
            $or_conditions[] = "(p.salary_max >= %d AND p.salary_min <= %d)";
            $params[] = $salary_min;
            $params[] = $salary_max;
        } elseif ($salary_min) {
            // User has minimum only - find posts with max salary >= user's minimum
            $or_conditions[] = "p.salary_max >= %d";
            $params[] = $salary_min;
        } elseif ($salary_max) {
            // User has maximum only - find posts with min salary <= user's maximum
            $or_conditions[] = "p.salary_min <= %d";
            $params[] = $salary_max;
        }

        // Work arrangement
        if (!empty($work_arrangement) && is_array($work_arrangement)) {
            $work_conditions = [];
            if (in_array('remote', $work_arrangement)) {
                $work_conditions[] = "p.is_remote = 1";
            }
            if (in_array('hybrid', $work_arrangement)) {
                $work_conditions[] = "p.is_hybrid = 1";
            }
            if (in_array('onsite', $work_arrangement)) {
                // Onsite means not remote and not hybrid
                $work_conditions[] = "(p.is_remote = 0 AND p.is_hybrid = 0)";
            }
            if (!empty($work_conditions)) {
                $or_conditions[] = '(' . implode(' OR ', $work_conditions) . ')';
            }
        }

        // If no preferences set, return empty array
        if (empty($or_conditions)) {
            return [];
        }

        $or_clause = '(' . implode(' OR ', $or_conditions) . ')';

        // Pagination
        $page = isset($args['page']) ? intval($args['page']) : 1;
        $per_page = isset($args['per_page']) ? intval($args['per_page']) : 20;
        $offset = ($page - 1) * $per_page;

        // Build saved subquery
        $saved_join = $wpdb->prepare(
            "LEFT JOIN {$this->saved_table} sp ON sp.post_id = p.id AND sp.user_id = %d",
            $user_id
        );
        $saved_select = ", IF(sp.id IS NOT NULL, 1, 0) as is_saved";

        // Build full query
        $sql = "SELECT p.id, p.role_title, p.company, p.location, p.location_country, p.location_city,
                       p.salary_min, p.salary_max, p.salary_currency, p.salary_text,
                       p.seniority, p.sector, p.experience_years, p.content, p.content_snippet, p.posted_at,
                       p.is_remote, p.is_hybrid, p.engagement_count, p.is_featured, p.is_early_bird, p.exclude_from_early_bird,
                       p.application_url, p.source_url, p.wp_post_id, p.jobs_post_id, p.keywords,
                       COALESCE(NULLIF(p.company_logo, ''), r.default_company_logo) AS company_logo,
                       p.recruiter_display_name, p.recruiter_display_company,
                       p.interview_questions, p.interview_questions_docx, p.cv_template_docx,
                       p.cover_letter_html, p.cover_letter_docx, p.case_study_pdf,
                       p.opening_date, p.closing_date, p.starting_date, p.duration,
                       p.knockout_questions, p.application_process, p.team_contacts,
                       r.id as recruiter_id, r.name as recruiter_name, r.firm as recruiter_firm,
                       r.photo_url as recruiter_photo, r.title as recruiter_title,
                       r.email as recruiter_email, r.linkedin_url as recruiter_linkedin
                       {$saved_select}
                FROM {$this->table} p
                LEFT JOIN {$this->recruiters_table} r ON r.id = p.recruiter_id
                {$saved_join}
                WHERE p.is_active = 1
                AND p.admin_approved = 1
                AND (p.post_status IS NULL OR p.post_status = 'open')
                AND $or_clause
                ORDER BY p.is_featured DESC, p.posted_at DESC
                LIMIT %d OFFSET %d";

        $params[] = $per_page;
        $params[] = $offset;

        $query = $wpdb->prepare($sql, $params);
        $results = $wpdb->get_results($query, ARRAY_A);

        // Convert is_early_bird to proper boolean integer
        foreach ($results as &$row) {
            if (isset($row['is_early_bird'])) {
                $row['is_early_bird'] = (int) $row['is_early_bird'];
            }
            if (isset($row['exclude_from_early_bird'])) {
                $row['exclude_from_early_bird'] = (int) $row['exclude_from_early_bird'];
            }
        }

        return $results;
    }

    /**
     * Get feed count for pagination
     */
    public function get_feed_count($args = []) {
        global $wpdb;

        $defaults = [
            'sector' => null,
            'seniority' => null,
            'location' => null,
            'search' => null,
            'role_title' => null,
            'recruiter_name' => null,
            'post_status' => null,
            'recruiter_id' => null,
            'approved_only' => true,
            'pending_only' => false,
            'user_id' => null,
        ];

        $args = array_merge($defaults, $args);

        if (!empty($args['user_id']) && function_exists('sffc_crm_user_is_excluded') && sffc_crm_user_is_excluded((int) $args['user_id'])) {
            return 0;
        }

        $where = ["p.is_active = 1"];
        $params = [];

        if ($args['pending_only']) {
            $where[] = "p.admin_approved = 0";
        } elseif ($args['approved_only']) {
            $where[] = "p.admin_approved = 1";
        }

        if ($args['post_status']) {
            if ($args['post_status'] === 'open') {
                $where[] = "(p.post_status IS NULL OR p.post_status = 'open')";
            } else {
                $where[] = "p.post_status = %s";
                $params[] = $args['post_status'];
            }
        }

        if ($args['sector']) {
            $where[] = "p.sector = %s";
            $params[] = $args['sector'];
        }

        if ($args['seniority']) {
            $where[] = "p.seniority = %s";
            $params[] = $args['seniority'];
        }

        if ($args['location']) {
            $where[] = "(p.location LIKE %s OR p.location_city LIKE %s OR p.location_country LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['location']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }

        if ($args['search']) {
            $where[] = "(p.role_title LIKE %s OR p.content LIKE %s OR p.company LIKE %s OR r.name LIKE %s OR r.firm LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }

        if ($args['role_title']) {
            $where[] = "p.role_title LIKE %s";
            $params[] = '%' . $wpdb->esc_like($args['role_title']) . '%';
        }

        if ($args['recruiter_name']) {
            $where[] = "(r.name LIKE %s OR r.firm LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['recruiter_name']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }

        if ($args['recruiter_id']) {
            $where[] = "p.recruiter_id = %d";
            $params[] = $args['recruiter_id'];
        }

        $where_clause = implode(' AND ', $where);

        $sql = "SELECT COUNT(*)
                FROM {$this->table} p
                LEFT JOIN {$this->recruiters_table} r ON r.id = p.recruiter_id
                WHERE {$where_clause}";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Create a new post
     */
    public function create($data) {
        global $wpdb;
        $this->ensure_experience_years_column();

        // Debug: Log incoming data
        error_log('SFFC CRM Post Create - Incoming data: ' . print_r($data, true));

        $data = $this->maybe_populate_content_snippet($data);

        // Encode JSON fields
        $json_fields = ['requirements', 'skills_mentioned', 'knockout_questions', 'materials', 'application_process', 'team_contacts'];
        foreach ($json_fields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }

        $defaults = [
            'is_active' => 1,
            'admin_approved' => 1,  // Auto-approve posts
            'source' => 'manual',
            'posted_at' => current_time('mysql'),
        ];

        $data = array_merge($defaults, $data);
        if (array_key_exists('posted_at', $data)) {
            $data['posted_at'] = $this->normalize_posted_at_value($data['posted_at'], current_time('mysql'));
        }

        // Debug: Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table}'");
        if (!$table_exists) {
            error_log('SFFC CRM Post Create - ERROR: Table does not exist: ' . $this->table);
            // Try to create tables
            $db_schema = SFFC_CRM_Database_Schema::get_instance();
            $db_schema->create_tables();
            $this->maybe_prepare_columns();
        }

        // Remove fields that don't exist in the database to prevent insert failure
        $columns = $wpdb->get_col("DESCRIBE {$this->table}", 0);
        if (empty($columns)) {
            error_log('SFFC CRM Post Create - ERROR: Could not describe table after schema preparation: ' . $this->table);
            return false;
        }
        foreach (array_keys($data) as $field) {
            if (!in_array($field, $columns)) {
                error_log('SFFC CRM Post Create - Removing non-existent field: ' . $field);
                unset($data[$field]);
            }
        }

        $result = $wpdb->insert($this->table, $data);

        // Debug: Log result
        error_log('SFFC CRM Post Create - Insert result: ' . ($result ? 'success' : 'failed'));
        if (!$result) {
            error_log('SFFC CRM Post Create - DB Error: ' . $wpdb->last_error);
            error_log('SFFC CRM Post Create - Last Query: ' . $wpdb->last_query);
        }

        if ($result) {
            // Update recruiter post count
            if (!empty($data['recruiter_id'])) {
                $recruiter_model = new SFFC_CRM_Recruiter();
                $recruiter_model->increment_post_count($data['recruiter_id']);
            }

            $post_id = (int) $wpdb->insert_id;
            do_action('sffc_crm_editorial_tracker_post_published', $post_id, true);

            return $post_id;
        }

        return false;
    }

    /**
     * Update post
     */
    public function update($id, $data) {
        global $wpdb;
        $this->ensure_experience_years_column();

        $existing = [];
        if ((int) $id > 0) {
            $existing = (array) $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", (int) $id),
                ARRAY_A
            );
        }

        $data = $this->maybe_populate_content_snippet($data, $existing);

        // Encode JSON fields
        $json_fields = ['requirements', 'skills_mentioned', 'knockout_questions', 'materials', 'application_process', 'team_contacts'];
        foreach ($json_fields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }

        if (array_key_exists('posted_at', $data)) {
            $fallback = !empty($existing['posted_at']) ? (string) $existing['posted_at'] : current_time('mysql');
            $data['posted_at'] = $this->normalize_posted_at_value($data['posted_at'], $fallback);
        }

        // Remove fields that don't exist in the database to prevent update failure
        $columns = $wpdb->get_col("DESCRIBE {$this->table}", 0);
        foreach (array_keys($data) as $field) {
            if (!in_array($field, $columns)) {
                error_log('SFFC CRM Post Update - Removing non-existent field: ' . $field);
                unset($data[$field]);
            }
        }

        return $wpdb->update(
            $this->table,
            $data,
            ['id' => $id]
        );
    }

    private function normalize_posted_at_value($value, $fallback = null) {
        $value = is_scalar($value) ? trim((string) $value) : '';
        if ($value === '' || preg_match('/^\d{4}$/', $value) || preg_match('/^0{4}-0{2}-0{2}/', $value)) {
            return $fallback;
        }

        $timestamp = strtotime($value);
        if (!$timestamp || $timestamp <= 0) {
            return $fallback;
        }

        $now = current_time('timestamp');
        if ($timestamp > ($now + DAY_IN_SECONDS) || $timestamp < strtotime('2000-01-01 00:00:00')) {
            return $fallback;
        }

        return wp_date('Y-m-d H:i:s', $timestamp);
    }

    private function ensure_experience_years_column() {
        global $wpdb;
        $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$this->table} LIKE %s", 'experience_years'));
        if ($column !== 'experience_years') {
            $wpdb->query("ALTER TABLE {$this->table} ADD COLUMN experience_years varchar(50) DEFAULT NULL AFTER skills_mentioned");
        }
    }

    private function maybe_populate_content_snippet(array $data, array $existing = []) {
        if (!empty($data['content_snippet'])) {
            return $data;
        }

        $summary = $this->build_senna_post_summary(array_merge($existing, $data));
        if ($summary !== '') {
            $data['content_snippet'] = $summary;
            return $data;
        }

        $content = (string) ($data['content'] ?? ($existing['content'] ?? ''));
        if ($content !== '') {
            $data['content_snippet'] = wp_trim_words(strip_tags($content), 40, '...');
        }

        return $data;
    }

    private function build_senna_post_summary(array $data) {
        $role_title = sanitize_text_field((string) ($data['role_title'] ?? ''));
        $company = sanitize_text_field((string) ($data['company'] ?? ''));
        $location = sanitize_text_field((string) ($data['location'] ?? ($data['location_country'] ?? '')));
        $seniority = sanitize_text_field((string) ($data['seniority'] ?? ''));
        $experience_years = sanitize_text_field((string) ($data['experience_years'] ?? ''));

        $lead = $role_title;
        if ($company !== '') {
            $lead .= ' at ' . $company;
        }

        $context = [];
        if ($location !== '') {
            $context[] = 'based in ' . $location;
        }
        if ($seniority !== '') {
            $context[] = $seniority . '-level remit';
        }
        if ($experience_years !== '') {
            $context[] = 'experience target ' . $experience_years;
        }

        $summary = trim($lead);
        if ($summary !== '' && !empty($context)) {
            $summary .= ' ' . implode(', ', $context);
        }

        if ($summary !== '') {
            $summary = rtrim($summary, '. ') . '.';
        }

        $focus_terms = $this->extract_senna_post_focus_terms($data);
        if (!empty($focus_terms)) {
            $summary .= ' Focus on ' . implode(', ', array_slice($focus_terms, 0, 3)) . '.';
        } else {
            $detail_sentence = $this->extract_senna_post_detail_sentence((string) ($data['content'] ?? ''));
            if ($detail_sentence !== '') {
                $summary .= ' ' . $detail_sentence;
            }
        }

        $summary = preg_replace('/\s+/', ' ', trim($summary));
        if ($summary === '') {
            return '';
        }

        return function_exists('mb_strimwidth')
            ? trim((string) mb_strimwidth($summary, 0, 255, '...'))
            : trim((string) substr($summary, 0, 252) . (strlen($summary) > 252 ? '...' : ''));
    }

    private function extract_senna_post_focus_terms(array $data) {
        $terms = [];

        foreach (['keywords', 'requirements', 'skills_mentioned'] as $field_key) {
            $raw_value = $data[$field_key] ?? [];
            if (is_string($raw_value)) {
                $decoded = json_decode($raw_value, true);
                if (is_array($decoded)) {
                    $raw_value = $decoded;
                } else {
                    $raw_value = preg_split('/[,|\\n]+/', $raw_value);
                }
            }

            foreach ((array) $raw_value as $candidate) {
                $term = sanitize_text_field((string) $candidate);
                $term = preg_replace('/\s+/', ' ', trim($term));
                if ($term === '' || strlen($term) < 3) {
                    continue;
                }
                $terms[] = $term;
            }
        }

        $unique_terms = [];
        $seen = [];
        foreach ($terms as $term) {
            $key = strtolower($term);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique_terms[] = $term;
            if (count($unique_terms) >= 5) {
                break;
            }
        }

        return $unique_terms;
    }

    private function extract_senna_post_detail_sentence($content) {
        $content = trim(wp_strip_all_tags((string) $content));
        if ($content === '') {
            return '';
        }

        $content = preg_replace('/\s+/', ' ', $content);
        $sentences = preg_split('/(?<=[.!?])\s+/', $content);
        foreach ((array) $sentences as $sentence) {
            $sentence = trim((string) $sentence);
            if (strlen($sentence) < 32) {
                continue;
            }

            $sentence = function_exists('mb_strimwidth')
                ? trim((string) mb_strimwidth($sentence, 0, 140, '...'))
                : trim((string) substr($sentence, 0, 137) . (strlen($sentence) > 137 ? '...' : ''));

            return rtrim($sentence, '. ') . '.';
        }

        return '';
    }

    /**
     * Save post for a user
     */
    public function save_post($user_id, $post_id, $folder = 'default', $notes = '') {
        global $wpdb;

        $post = $this->get($post_id);
        if (!$post) {
            return new WP_Error('not_found', 'Post not found');
        }

        $result = $wpdb->replace($this->saved_table, [
            'user_id' => $user_id,
            'post_id' => $post_id,
            'recruiter_id' => $post['recruiter_id'],
            'folder' => $folder,
            'notes' => $notes,
        ]);

        if ($result) {
            $this->log_activity($user_id, $post['recruiter_id'], $post_id, 'post_saved');
        }

        return $result;
    }

    /**
     * Unsave post for a user
     */
    public function unsave_post($user_id, $post_id) {
        global $wpdb;

        $saved = $wpdb->get_row($wpdb->prepare(
            "SELECT recruiter_id FROM {$this->saved_table} WHERE user_id = %d AND post_id = %d",
            $user_id,
            $post_id
        ), ARRAY_A);

        $result = $wpdb->delete($this->saved_table, [
            'user_id' => $user_id,
            'post_id' => $post_id,
        ]);

        if ($result && $saved) {
            $this->log_activity($user_id, $saved['recruiter_id'], $post_id, 'post_unsaved');
        }

        return $result;
    }

    /**
     * Get user's saved posts
     */
    public function get_saved_posts($user_id, $folder = null) {
        global $wpdb;

        $where = "sp.user_id = %d";
        $params = [$user_id];

        if ($folder) {
            $where .= " AND sp.folder = %s";
            $params[] = $folder;
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.id, p.role_title, p.company, p.location, p.salary_text,
                    p.seniority, p.sector, p.experience_years, p.content_snippet, p.posted_at, p.response_label,
                    p.interview_questions, p.cv_template_docx, p.cover_letter_html,
                    p.cover_letter_docx, p.case_study_pdf,
                    r.name as recruiter_name, r.firm as recruiter_firm, r.photo_url as recruiter_photo,
                    sp.folder, sp.notes as user_notes, sp.saved_at
             FROM {$this->saved_table} sp
             JOIN {$this->table} p ON p.id = sp.post_id
             LEFT JOIN {$this->recruiters_table} r ON r.id = p.recruiter_id
             WHERE {$where}
             ORDER BY sp.saved_at DESC",
            $params
        ), ARRAY_A);
    }

    /**
     * Get saved posts count
     */
    public function get_saved_count($user_id) {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->saved_table} WHERE user_id = %d",
            $user_id
        ));
    }

    /**
     * Get filter options (for frontend dropdowns)
     */
    public function get_filter_options() {
        global $wpdb;

        $sectors = $wpdb->get_col(
            "SELECT DISTINCT sector FROM {$this->table}
             WHERE sector IS NOT NULL AND sector != '' AND is_active = 1 AND admin_approved = 1
             ORDER BY sector"
        );

        $seniorities = $wpdb->get_col(
            "SELECT DISTINCT seniority FROM {$this->table}
             WHERE seniority IS NOT NULL AND seniority != '' AND is_active = 1 AND admin_approved = 1
             ORDER BY FIELD(seniority, 'intern', 'analyst', 'senior_analyst', 'associate', 'senior_associate', 'vp', 'senior_vp', 'director', 'md', 'partner', 'c_level', 'board', 'other')"
        );

        $countries = $wpdb->get_col(
            "SELECT DISTINCT location_country FROM {$this->table}
             WHERE location_country IS NOT NULL AND location_country != '' AND is_active = 1 AND admin_approved = 1
             ORDER BY location_country"
        );

        $firms = $wpdb->get_col(
            "SELECT DISTINCT r.firm FROM {$this->recruiters_table} r
             JOIN {$this->table} p ON p.recruiter_id = r.id
             WHERE r.firm IS NOT NULL AND r.firm != '' AND p.is_active = 1 AND p.admin_approved = 1
             ORDER BY r.firm"
        );

        $recruiters = $wpdb->get_col(
            "SELECT DISTINCT r.name FROM {$this->recruiters_table} r
             JOIN {$this->table} p ON p.recruiter_id = r.id
             WHERE r.name IS NOT NULL AND r.name != '' AND p.is_active = 1 AND p.admin_approved = 1
             ORDER BY r.name"
        );

        return [
            'sectors' => $sectors,
            'seniorities' => $seniorities,
            'countries' => $countries,
            'firms' => $firms,
            'recruiters' => $recruiters,
        ];
    }

    /**
     * Approve post
     */
    public function approve($id, $admin_notes = '') {
        return $this->update($id, [
            'admin_approved' => 1,
            'admin_notes' => $admin_notes,
        ]);
    }

    /**
     * Reject/deactivate post
     */
    public function reject($id, $admin_notes = '') {
        return $this->update($id, [
            'admin_approved' => 0,
            'is_active' => 0,
            'admin_notes' => $admin_notes,
        ]);
    }

    /**
     * Get posts pending approval
     */
    public function get_pending_posts($limit = 50) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, r.name as recruiter_name, r.firm as recruiter_firm
             FROM {$this->table} p
             LEFT JOIN {$this->recruiters_table} r ON r.id = p.recruiter_id
             WHERE p.is_active = 1 AND p.admin_approved = 0
             ORDER BY p.created_at DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);
    }

    /**
     * Log activity
     */
    private function log_activity($user_id, $recruiter_id, $post_id, $type, $data = []) {
        global $wpdb;

        $wpdb->insert($this->activity_table, [
            'user_id' => $user_id,
            'recruiter_id' => $recruiter_id,
            'post_id' => $post_id,
            'activity_type' => $type,
            'activity_data' => json_encode($data),
        ]);
    }

    /**
     * Get post stats for admin dashboard
     */
    public function get_admin_stats() {
        global $wpdb;

        return [
            'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}"),
            'active' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE is_active = 1"),
            'approved' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE admin_approved = 1 AND is_active = 1"),
            'pending' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE admin_approved = 0 AND is_active = 1"),
            'this_week' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE created_at >= %s",
                date('Y-m-d', strtotime('-7 days'))
            )),
        ];
    }

    /**
     * Check for duplicate post
     */
    public function is_duplicate($source, $source_id) {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE source = %s AND source_id = %s",
            $source,
            $source_id
        ));
    }

    /**
     * Get full post detail for modal view
     */
    public function get_full_detail($post_id, $user_id = 0) {
        global $wpdb;

        $post = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*,
                    r.id as recruiter_id, r.name as recruiter_name, r.firm as recruiter_firm,
                    r.photo_url as recruiter_photo, r.title as recruiter_title,
                    r.email as recruiter_email, r.linkedin_url as recruiter_linkedin,
                    r.sectors as recruiter_specializations, r.is_verified as recruiter_verified
             FROM {$this->table} p
             LEFT JOIN {$this->recruiters_table} r ON r.id = p.recruiter_id
             WHERE p.id = %d AND (p.is_active = 1 OR p.is_active IS NULL)",
            $post_id
        ), ARRAY_A);

        if (!$post) {
            return null;
        }

        // Decode JSON fields
        $post['requirements'] = json_decode($post['requirements'], true) ?: [];
        $post['skills_mentioned'] = json_decode($post['skills_mentioned'], true) ?: [];
        $post['knockout_questions'] = json_decode($post['knockout_questions'] ?? '[]', true) ?: [];
        $post['application_process'] = json_decode($post['application_process'] ?? '[]', true) ?: [];
        $post['team_contacts'] = json_decode($post['team_contacts'] ?? '[]', true) ?: [];
        $post['materials'] = json_decode($post['materials'] ?? '[]', true) ?: [];

        // Check if saved by user
        if ($user_id) {
            $saved = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->saved_table} WHERE user_id = %d AND post_id = %d",
                $user_id,
                $post_id
            ), ARRAY_A);

            $post['is_saved'] = !empty($saved) ? 1 : 0;
            $post['saved_folder'] = $saved ? $saved['folder'] : null;
            $post['user_notes'] = $saved ? $saved['notes'] : null;

            // Check if in pipeline
            $pipeline_table = $wpdb->prefix . 'sffc_crm_pipeline';
            $in_pipeline = $wpdb->get_row($wpdb->prepare(
                "SELECT id, stage FROM {$pipeline_table} WHERE user_id = %d AND post_id = %d",
                $user_id,
                $post_id
            ), ARRAY_A);

            $post['in_pipeline'] = !empty($in_pipeline) ? 1 : 0;
            $post['pipeline_stage'] = $in_pipeline ? $in_pipeline['stage'] : null;

            // Log view
            $this->log_activity($user_id, $post['recruiter_id'], $post_id, 'post_detail_viewed');
        } else {
            $post['is_saved'] = 0;
            $post['in_pipeline'] = 0;
        }

        return $post;
    }

    /**
     * Get keywords for a post (decoded from JSON)
     *
     * @param int $post_id
     * @return array Array of keyword objects
     */
    public function get_keywords($post_id) {
        global $wpdb;

        $keywords_json = $wpdb->get_var($wpdb->prepare(
            "SELECT keywords FROM {$this->table} WHERE id = %d",
            $post_id
        ));

        if (empty($keywords_json)) {
            return [];
        }

        $keywords = json_decode($keywords_json, true);
        return is_array($keywords) ? $keywords : [];
    }

    /**
     * Save keywords for a post
     *
     * @param int $post_id
     * @param array $keywords Array of keyword objects
     * @param bool $manual Whether keywords were manually edited
     * @return bool
     */
    public function save_keywords($post_id, $keywords, $manual = false) {
        global $wpdb;

        $keywords_json = !empty($keywords) ? json_encode($keywords) : null;

        return $wpdb->update(
            $this->table,
            [
                'keywords' => $keywords_json,
                'keywords_manual' => $manual ? 1 : 0
            ],
            ['id' => $post_id],
            ['%s', '%d'],
            ['%d']
        ) !== false;
    }

    /**
     * Extract and save keywords for a post
     *
     * @param int $post_id
     * @param bool $force Force extraction even if manual keywords exist
     * @return array Extracted keywords
     */
    public function extract_keywords_for_post($post_id, $force = false) {
        global $wpdb;

        // Get post data
        $post = $wpdb->get_row($wpdb->prepare(
            "SELECT content, keywords_manual, location, role_title, company FROM {$this->table} WHERE id = %d",
            $post_id
        ), ARRAY_A);

        if (!$post) {
            return [];
        }

        // Don't auto-extract if manually edited (unless forced)
        if (!$force && $post['keywords_manual'] == 1) {
            return $this->get_keywords($post_id);
        }

        // Extract keywords
        $extractor = new SFFC_CRM_Keyword_Extractor();
        $keywords = $extractor->extract_keywords($post['content'], [
            'location' => $post['location'],
            'role_title' => $post['role_title'],
            'company' => $post['company']
        ]);

        // Save keywords
        $this->save_keywords($post_id, $keywords, false);

        return $keywords;
    }

    public static function parse_duration_input($value) {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $entries = self::extract_duration_entries($value);
        if (empty($entries)) {
            return null;
        }

        return (float) round($entries[0], 2);
    }

    private function filter_results_by_duration(array $results, $target_months) {
        if ($target_months === null) {
            return $results;
        }

        $filtered = [];
        foreach ($results as $row) {
            $duration_text = $row['duration'] ?? '';
            if ($this->duration_matches($duration_text, $target_months)) {
                $filtered[] = $row;
            }
        }

        return array_values($filtered);
    }

    private function duration_matches($text, $target_months) {
        if (!$text) {
            return false;
        }

        $values = self::extract_duration_entries($text);
        if (empty($values)) {
            return false;
        }

        if (count($values) >= 2 && $this->has_range_indicator($text)) {
            $min = min($values);
            $max = max($values);
            return $target_months >= $min && $target_months <= $max;
        }

        foreach ($values as $val) {
            if (abs($val - $target_months) <= 0.75) {
                return true;
            }
        }

        return false;
    }

    private function has_range_indicator($text) {
        return (strpos($text, '-') !== false) || stripos($text, 'to') !== false;
    }

    private static function extract_duration_entries($text) {
        if (!$text) {
            return [];
        }

        $pattern = '/(\d+(?:\.\d+)?)\s*(years?|yrs?|yr|y|months?|mos?|mo|mth|weeks?|wks?|wk|days?|d)?/i';
        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);

        $entries = [];
        $last_unit = 'month';

        foreach ($matches as $match) {
            $number = isset($match[1]) ? (float) $match[1] : null;
            if ($number === null) {
                continue;
            }

            $unit = !empty($match[2]) ? strtolower($match[2]) : $last_unit;
            if (!$unit) {
                $unit = 'month';
            }
            $last_unit = $unit;

            $months = self::convert_to_months($number, $unit);
            if ($months !== null) {
                $entries[] = $months;
            }
        }

        return $entries;
    }

    private static function convert_to_months($value, $unit) {
        $unit = strtolower($unit);

        if (in_array($unit, ['year', 'years', 'yr', 'yrs', 'y'], true)) {
            return $value * 12;
        }

        if (in_array($unit, ['month', 'months', 'mo', 'mos', 'mth'], true)) {
            return $value;
        }

        if (in_array($unit, ['week', 'weeks', 'wk', 'wks'], true)) {
            return $value / 4;
        }

        if (in_array($unit, ['day', 'days', 'd'], true)) {
            return $value / 30;
        }

        return $value;
    }
}
