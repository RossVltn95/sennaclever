<?php
/**
 * CRM Recruiter Model
 * Handles recruiter data operations
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Recruiter {

    private $table;
    private $saved_table;
    private $posts_table;
    private $activity_table;
    private $notes_table;
    private $tags_table;
    private $recruiter_tags_table;
    private $outreach_table;
    private static $columns_ready = false;
    private const COLUMNS_READY_OPTION = 'sffc_crm_recruiters_columns_ready_v1';

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sffc_crm_recruiters';
        $this->saved_table = $wpdb->prefix . 'sffc_crm_saved_recruiters';
        $this->posts_table = $wpdb->prefix . 'sffc_crm_posts';
        $this->activity_table = $wpdb->prefix . 'sffc_crm_activity';
        $this->notes_table = $wpdb->prefix . 'sffc_crm_notes';
        $this->tags_table = $wpdb->prefix . 'sffc_crm_tags';
        $this->recruiter_tags_table = $wpdb->prefix . 'sffc_crm_recruiter_tags';
        $this->outreach_table = $wpdb->prefix . 'sffc_crm_outreach';

        $this->maybe_prepare_columns();
    }

    private function maybe_prepare_columns() {
        if (self::$columns_ready) {
            return;
        }

        if (get_option(self::COLUMNS_READY_OPTION) === '1') {
            self::$columns_ready = true;
            return;
        }

        global $wpdb;
        $existing_columns = array_map('strval', (array) $wpdb->get_col("SHOW COLUMNS FROM {$this->table}"));

        if (!in_array('default_company', $existing_columns, true)) {
            $wpdb->query("ALTER TABLE {$this->table} ADD COLUMN default_company varchar(200) DEFAULT NULL");
        }

        if (!in_array('default_company_logo', $existing_columns, true)) {
            $wpdb->query("ALTER TABLE {$this->table} ADD COLUMN default_company_logo varchar(500) DEFAULT NULL");
        }

        self::$columns_ready = true;
        update_option(self::COLUMNS_READY_OPTION, '1', false);
    }

    /**
     * Get recruiter by ID
     */
    public function get($id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ), ARRAY_A);
    }

    /**
     * Get recruiter with full details (posts, user relationship status)
     */
    public function get_with_details($id, $user_id) {
        global $wpdb;

        $recruiter = $this->get($id);

        if (!$recruiter) {
            return null;
        }

        // Parse JSON fields
        $recruiter['sectors'] = json_decode($recruiter['sectors'], true) ?: [];
        $recruiter['seniority_levels'] = json_decode($recruiter['seniority_levels'], true) ?: [];
        $recruiter['regions'] = json_decode($recruiter['regions'], true) ?: [];

        // Get user's relationship with this recruiter
        $saved = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->saved_table} WHERE user_id = %d AND recruiter_id = %d",
            $user_id,
            $id
        ), ARRAY_A);

        $recruiter['is_saved'] = !empty($saved);
        $recruiter['status'] = $saved ? $saved['status'] : null;
        $recruiter['tags'] = $saved ? explode(',', $saved['tags']) : [];
        $recruiter['priority'] = $saved ? $saved['priority'] : null;
        $recruiter['user_notes'] = $saved ? $saved['notes'] : null;

        // Get recent posts by this recruiter
        $recruiter['recent_posts'] = $wpdb->get_results($wpdb->prepare(
            "SELECT id, role_title, company, location, posted_at, content_snippet
             FROM {$this->posts_table}
             WHERE recruiter_id = %d AND is_active = 1
             ORDER BY posted_at DESC
             LIMIT 5",
            $id
        ), ARRAY_A);

        // Get notes
        $recruiter['notes'] = $wpdb->get_results($wpdb->prepare(
            "SELECT id, content, is_pinned, created_at
             FROM {$this->notes_table}
             WHERE user_id = %d AND entity_type = 'recruiter' AND entity_id = %d
             ORDER BY is_pinned DESC, created_at DESC",
            $user_id,
            $id
        ), ARRAY_A);

        // Get activity timeline
        $recruiter['activity'] = $wpdb->get_results($wpdb->prepare(
            "SELECT activity_type, activity_data, created_at
             FROM {$this->activity_table}
             WHERE user_id = %d AND recruiter_id = %d
             ORDER BY created_at DESC
             LIMIT 20",
            $user_id,
            $id
        ), ARRAY_A);

        return $recruiter;
    }

    /**
     * Get recruiter by LinkedIn ID
     */
    public function get_by_linkedin($linkedin_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE linkedin_id = %s",
            $linkedin_id
        ), ARRAY_A);
    }

    /**
     * Find recruiter by email
     */
    public function find_by_email($email) {
        global $wpdb;

        if (empty($email)) {
            return null;
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE email = %s",
            $email
        ), ARRAY_A);
    }

    /**
     * Create a new recruiter
     */
    public function create($data) {
        global $wpdb;

        // Debug: Log incoming data
        error_log('SFFC CRM Recruiter Create - Incoming data: ' . print_r($data, true));

        if (isset($data['id'])) {
            $data['id'] = intval($data['id']);
        }

        // Parse name into first/last
        if (!empty($data['name']) && empty($data['first_name'])) {
            $name_parts = explode(' ', $data['name'], 2);
            $data['first_name'] = $name_parts[0];
            $data['last_name'] = $name_parts[1] ?? '';
        }

        // Encode JSON fields
        $json_fields = ['sectors', 'seniority_levels', 'regions'];
        foreach ($json_fields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }

        $defaults = [
            'is_active' => 1,
            'is_verified' => 0,
            'total_posts' => 0,
            'data_source' => 'manual',
        ];

        $data = array_merge($defaults, $data);

        // Debug: Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table}'");
        if (!$table_exists) {
            error_log('SFFC CRM Recruiter Create - ERROR: Table does not exist: ' . $this->table);
            // Try to create tables
            $db_schema = SFFC_CRM_Database_Schema::get_instance();
            $db_schema->create_tables();
        }

        $result = $wpdb->insert($this->table, $data);

        // Debug: Log result
        error_log('SFFC CRM Recruiter Create - Insert result: ' . ($result ? 'success, ID: ' . $wpdb->insert_id : 'failed'));
        if (!$result) {
            error_log('SFFC CRM Recruiter Create - DB Error: ' . $wpdb->last_error);
            error_log('SFFC CRM Recruiter Create - Last Query: ' . $wpdb->last_query);
        }

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update recruiter
     */
    public function update($id, $data) {
        global $wpdb;

        // Encode JSON fields
        $json_fields = ['sectors', 'seniority_levels', 'regions'];
        foreach ($json_fields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }

        return $wpdb->update(
            $this->table,
            $data,
            ['id' => $id]
        );
    }

    /**
     * Save recruiter for a user
     */
    public function save_recruiter($user_id, $recruiter_id, $data = []) {
        global $wpdb;

        // Check limit
        $limit = apply_filters('sffc_crm_recruiter_limit', 20, $user_id);
        if ($limit > 0) {
            $current_count = $this->get_saved_count($user_id);
            if ($current_count >= $limit) {
                return new WP_Error('limit_reached', 'You have reached your saved recruiters limit');
            }
        }

        $insert_data = array_merge([
            'user_id' => $user_id,
            'recruiter_id' => $recruiter_id,
            'status' => 'new',
            'priority' => 'medium',
        ], $data);

        $result = $wpdb->replace($this->saved_table, $insert_data);

        if ($result) {
            $this->log_activity($user_id, $recruiter_id, 'recruiter_saved');
        }

        return $result;
    }

    /**
     * Unsave recruiter for a user
     */
    public function unsave_recruiter($user_id, $recruiter_id) {
        global $wpdb;

        $result = $wpdb->delete($this->saved_table, [
            'user_id' => $user_id,
            'recruiter_id' => $recruiter_id,
        ]);

        if ($result) {
            $this->log_activity($user_id, $recruiter_id, 'recruiter_unsaved');
        }

        return $result;
    }

    /**
     * Update saved recruiter status
     */
    public function update_status($user_id, $recruiter_id, $status, $notes = null) {
        global $wpdb;

        $data = ['status' => $status];

        if ($status === 'contacted') {
            $data['last_contacted_at'] = current_time('mysql');
        } elseif ($status === 'replied') {
            $data['last_reply_at'] = current_time('mysql');
        }

        if ($notes !== null) {
            $data['notes'] = $notes;
        }

        $result = $wpdb->update(
            $this->saved_table,
            $data,
            [
                'user_id' => $user_id,
                'recruiter_id' => $recruiter_id,
            ]
        );

        if ($result) {
            $this->log_activity($user_id, $recruiter_id, 'status_changed', ['new_status' => $status]);
        }

        return $result;
    }

    /**
     * Get user's saved recruiters
     */
    public function get_saved_recruiters($user_id, $args = []) {
        global $wpdb;

        $defaults = [
            'page' => 1,
            'per_page' => 20,
            'status' => null,
            'search' => null,
            'orderby' => 'saved_at',
            'order' => 'DESC',
        ];

        $args = array_merge($defaults, $args);

        $where = ["sr.user_id = %d"];
        $params = [$user_id];

        if ($args['status']) {
            $where[] = "sr.status = %s";
            $params[] = $args['status'];
        }

        if ($args['search']) {
            $where[] = "(r.name LIKE %s OR r.firm LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }

        $where_clause = implode(' AND ', $where);
        $offset = ($args['page'] - 1) * $args['per_page'];

        $sql = $wpdb->prepare(
            "SELECT r.*, sr.status, sr.notes as user_notes, sr.tags, sr.priority,
                    sr.saved_at, sr.last_contacted_at, sr.last_reply_at, sr.next_followup_at
             FROM {$this->saved_table} sr
             JOIN {$this->table} r ON r.id = sr.recruiter_id
             WHERE {$where_clause}
             ORDER BY sr.{$args['orderby']} {$args['order']}
             LIMIT %d OFFSET %d",
            array_merge($params, [$args['per_page'], $offset])
        );

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get user's saved recruiters count
     */
    public function get_saved_count($user_id, $status = null) {
        global $wpdb;

        if ($status) {
            return $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->saved_table} WHERE user_id = %d AND status = %s",
                $user_id,
                $status
            ));
        }

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->saved_table} WHERE user_id = %d",
            $user_id
        ));
    }

    /**
     * Get user's recruiters (alias for get_saved_recruiters for REST API)
     */
    public function get_user_recruiters($user_id, $args = []) {
        return $this->get_saved_recruiters($user_id, $args);
    }

    /**
     * Get user's recruiters count (alias)
     */
    public function get_user_recruiters_count($user_id, $args = []) {
        return $this->get_saved_count($user_id, $args['status'] ?? null);
    }

    /**
     * Search all recruiters (for adding new ones)
     */
    public function search($query, $limit = 20) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, firm, title, photo_url, linkedin_url
             FROM {$this->table}
             WHERE is_active = 1
             AND (name LIKE %s OR firm LIKE %s)
             ORDER BY total_posts DESC
             LIMIT %d",
            '%' . $wpdb->esc_like($query) . '%',
            '%' . $wpdb->esc_like($query) . '%',
            $limit
        ), ARRAY_A);
    }

    /**
     * Get or create recruiter from post data
     * Used when importing posts to auto-create recruiter profiles
     */
    public function get_or_create_from_post_data($data) {
        // Check if recruiter exists by LinkedIn ID
        if (!empty($data['linkedin_id'])) {
            $existing = $this->get_by_linkedin($data['linkedin_id']);
            if ($existing) {
                // Update post count
                $this->increment_post_count($existing['id']);
                return $existing['id'];
            }
        }

        // Check by name + firm
        if (!empty($data['name']) && !empty($data['firm'])) {
            global $wpdb;
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$this->table} WHERE name = %s AND firm = %s",
                $data['name'],
                $data['firm']
            ), ARRAY_A);

            if ($existing) {
                $this->increment_post_count($existing['id']);
                return $existing['id'];
            }
        }

        // Create new recruiter
        $data['data_source'] = $data['data_source'] ?? 'post_import';
        return $this->create($data);
    }

    /**
     * Increment recruiter's post count
     */
    public function increment_post_count($recruiter_id) {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table}
             SET total_posts = total_posts + 1, last_post_date = %s
             WHERE id = %d",
            current_time('mysql'),
            $recruiter_id
        ));
    }

    /**
     * Log activity
     */
    private function log_activity($user_id, $recruiter_id, $type, $data = []) {
        global $wpdb;

        $wpdb->insert($this->activity_table, [
            'user_id' => $user_id,
            'recruiter_id' => $recruiter_id,
            'activity_type' => $type,
            'activity_data' => json_encode($data),
        ]);
    }

    /**
     * Add note to recruiter
     */
    public function add_note($user_id, $recruiter_id, $content, $is_pinned = false) {
        global $wpdb;

        $result = $wpdb->insert($this->notes_table, [
            'user_id' => $user_id,
            'entity_type' => 'recruiter',
            'entity_id' => $recruiter_id,
            'content' => $content,
            'is_pinned' => $is_pinned ? 1 : 0,
        ]);

        if ($result) {
            $this->log_activity($user_id, $recruiter_id, 'note_added');
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get recruiter stats for dashboard
     */
    public function get_user_stats($user_id) {
        global $wpdb;

        $stats = [
            'total' => 0,
            'new' => 0,
            'contacted' => 0,
            'replied' => 0,
            'in_conversation' => 0,
            'dormant' => 0,
        ];

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) as count
             FROM {$this->saved_table}
             WHERE user_id = %d
             GROUP BY status",
            $user_id
        ), ARRAY_A);

        foreach ($results as $row) {
            $stats[$row['status']] = (int) $row['count'];
            $stats['total'] += (int) $row['count'];
        }

        return $stats;
    }

    // ============================================
    // TAGS MANAGEMENT (Phase 2)
    // ============================================

    /**
     * Get all user's tags
     */
    public function get_user_tags($user_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->tags_table}
             WHERE user_id = %d
             ORDER BY usage_count DESC, name ASC",
            $user_id
        ), ARRAY_A);
    }

    /**
     * Create a new tag
     */
    public function create_tag($user_id, $name, $color = '#6b7280') {
        global $wpdb;

        $result = $wpdb->insert($this->tags_table, [
            'user_id' => $user_id,
            'name' => sanitize_text_field($name),
            'color' => sanitize_hex_color($color) ?: '#6b7280',
        ]);

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update a tag
     */
    public function update_tag($user_id, $tag_id, $data) {
        global $wpdb;

        $update_data = [];
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }
        if (isset($data['color'])) {
            $update_data['color'] = sanitize_hex_color($data['color']) ?: '#6b7280';
        }

        return $wpdb->update(
            $this->tags_table,
            $update_data,
            ['id' => $tag_id, 'user_id' => $user_id]
        );
    }

    /**
     * Delete a tag
     */
    public function delete_tag($user_id, $tag_id) {
        global $wpdb;

        // Remove all recruiter associations
        $wpdb->delete($this->recruiter_tags_table, [
            'user_id' => $user_id,
            'tag_id' => $tag_id,
        ]);

        // Delete the tag
        return $wpdb->delete($this->tags_table, [
            'id' => $tag_id,
            'user_id' => $user_id,
        ]);
    }

    /**
     * Add tag to recruiter
     */
    public function add_tag_to_recruiter($user_id, $recruiter_id, $tag_id) {
        global $wpdb;

        $result = $wpdb->insert($this->recruiter_tags_table, [
            'user_id' => $user_id,
            'recruiter_id' => $recruiter_id,
            'tag_id' => $tag_id,
        ]);

        if ($result) {
            // Increment tag usage count
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->tags_table} SET usage_count = usage_count + 1 WHERE id = %d",
                $tag_id
            ));
            $this->log_activity($user_id, $recruiter_id, 'tag_added', ['tag_id' => $tag_id]);
        }

        return $result;
    }

    /**
     * Remove tag from recruiter
     */
    public function remove_tag_from_recruiter($user_id, $recruiter_id, $tag_id) {
        global $wpdb;

        $result = $wpdb->delete($this->recruiter_tags_table, [
            'user_id' => $user_id,
            'recruiter_id' => $recruiter_id,
            'tag_id' => $tag_id,
        ]);

        if ($result) {
            // Decrement tag usage count
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->tags_table} SET usage_count = GREATEST(0, usage_count - 1) WHERE id = %d",
                $tag_id
            ));
            $this->log_activity($user_id, $recruiter_id, 'tag_removed', ['tag_id' => $tag_id]);
        }

        return $result;
    }

    /**
     * Get tags for a recruiter
     */
    public function get_recruiter_tags($user_id, $recruiter_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT t.* FROM {$this->tags_table} t
             JOIN {$this->recruiter_tags_table} rt ON rt.tag_id = t.id
             WHERE rt.user_id = %d AND rt.recruiter_id = %d
             ORDER BY t.name ASC",
            $user_id,
            $recruiter_id
        ), ARRAY_A);
    }

    // ============================================
    // RECRUITER INTELLIGENCE (Phase 2)
    // ============================================

    /**
     * Get recruiter intelligence data
     */
    public function get_intelligence($recruiter_id, $user_id) {
        global $wpdb;

        $intelligence = [];

        // Get sectors from posts
        $sectors = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT sector FROM {$this->posts_table}
             WHERE recruiter_id = %d AND sector IS NOT NULL AND sector != ''
             LIMIT 10",
            $recruiter_id
        ));
        $intelligence['sectors'] = $sectors;

        // Get seniority levels from posts
        $seniorities = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT seniority FROM {$this->posts_table}
             WHERE recruiter_id = %d AND seniority IS NOT NULL
             LIMIT 10",
            $recruiter_id
        ));
        $intelligence['seniority_levels'] = $seniorities;

        // Get locations from posts
        $locations = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT location FROM {$this->posts_table}
             WHERE recruiter_id = %d AND location IS NOT NULL AND location != ''
             LIMIT 10",
            $recruiter_id
        ));
        $intelligence['locations'] = $locations;

        // Get post frequency
        $post_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_posts,
                MIN(posted_at) as first_post,
                MAX(posted_at) as last_post,
                COUNT(CASE WHEN posted_at > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as posts_last_30d
             FROM {$this->posts_table}
             WHERE recruiter_id = %d",
            $recruiter_id
        ), ARRAY_A);
        $intelligence['post_stats'] = $post_stats;

        // Get response rate (if user has sent outreach)
        if ($user_id) {
            $outreach_stats = $wpdb->get_row($wpdb->prepare(
                "SELECT
                    COUNT(*) as total_sent,
                    COUNT(CASE WHEN status = 'replied' THEN 1 END) as replied,
                    AVG(CASE WHEN replied_at IS NOT NULL THEN DATEDIFF(replied_at, sent_at) END) as avg_response_days
                 FROM {$this->outreach_table}
                 WHERE user_id = %d AND recruiter_id = %d AND status IN ('sent', 'opened', 'clicked', 'replied')",
                $user_id,
                $recruiter_id
            ), ARRAY_A);
            $intelligence['user_outreach_stats'] = $outreach_stats;
        }

        // Get global response metrics (aggregated across all users)
        $global_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_outreach,
                COUNT(CASE WHEN status = 'replied' THEN 1 END) as replied_count,
                AVG(CASE WHEN replied_at IS NOT NULL THEN DATEDIFF(replied_at, sent_at) END) as avg_response_days
             FROM {$this->outreach_table}
             WHERE recruiter_id = %d AND status IN ('sent', 'opened', 'clicked', 'replied')",
            $recruiter_id
        ), ARRAY_A);

        if ($global_stats['total_outreach'] > 0) {
            $intelligence['response_rate'] = round(($global_stats['replied_count'] / $global_stats['total_outreach']) * 100, 1);
            $intelligence['avg_response_days'] = $global_stats['avg_response_days'] ? round($global_stats['avg_response_days'], 1) : null;
        }

        return $intelligence;
    }

    // ============================================
    // ENHANCED LIST VIEW (Phase 2)
    // ============================================

    /**
     * Get saved recruiters with enhanced filtering
     */
    public function get_saved_recruiters_enhanced($user_id, $args = []) {
        global $wpdb;

        $defaults = [
            'page' => 1,
            'per_page' => 20,
            'status' => null,
            'tag_id' => null,
            'priority' => null,
            'search' => null,
            'sector' => null,
            'location' => null,
            'has_recent_posts' => null,
            'orderby' => 'saved_at',
            'order' => 'DESC',
        ];

        $args = array_merge($defaults, $args);

        $where = ["sr.user_id = %d"];
        $params = [$user_id];
        $join = "";

        if ($args['status']) {
            $where[] = "sr.status = %s";
            $params[] = $args['status'];
        }

        if ($args['priority']) {
            $where[] = "sr.priority = %s";
            $params[] = $args['priority'];
        }

        if ($args['tag_id']) {
            $join .= " JOIN {$this->recruiter_tags_table} rt ON rt.recruiter_id = sr.recruiter_id AND rt.user_id = sr.user_id";
            $where[] = "rt.tag_id = %d";
            $params[] = $args['tag_id'];
        }

        if ($args['search']) {
            $where[] = "(r.name LIKE %s OR r.firm LIKE %s OR r.title LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }

        if ($args['sector']) {
            $where[] = "r.sectors LIKE %s";
            $params[] = '%' . $wpdb->esc_like($args['sector']) . '%';
        }

        if ($args['location']) {
            $where[] = "(r.location LIKE %s OR r.regions LIKE %s)";
            $location_term = '%' . $wpdb->esc_like($args['location']) . '%';
            $params[] = $location_term;
            $params[] = $location_term;
        }

        if ($args['has_recent_posts']) {
            $where[] = "r.last_post_date > DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }

        $where_clause = implode(' AND ', $where);
        $offset = ($args['page'] - 1) * $args['per_page'];

        // Build order clause
        $order_map = [
            'saved_at' => 'sr.saved_at',
            'name' => 'r.name',
            'firm' => 'r.firm',
            'last_contacted' => 'sr.last_contacted_at',
            'last_reply' => 'sr.last_reply_at',
            'last_post' => 'r.last_post_date',
        ];
        $order_col = $order_map[$args['orderby']] ?? 'sr.saved_at';
        $order_dir = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = $wpdb->prepare(
            "SELECT r.*, sr.status, sr.notes as user_notes, sr.priority,
                    sr.saved_at, sr.last_contacted_at, sr.last_reply_at, sr.next_followup_at,
                    (SELECT COUNT(*) FROM {$this->posts_table} WHERE recruiter_id = r.id AND is_active = 1) as active_posts_count
             FROM {$this->saved_table} sr
             JOIN {$this->table} r ON r.id = sr.recruiter_id
             {$join}
             WHERE {$where_clause}
             ORDER BY {$order_col} {$order_dir}
             LIMIT %d OFFSET %d",
            array_merge($params, [$args['per_page'], $offset])
        );

        $recruiters = $wpdb->get_results($sql, ARRAY_A);

        // Attach tags to each recruiter
        foreach ($recruiters as &$recruiter) {
            $recruiter['tags'] = $this->get_recruiter_tags($user_id, $recruiter['id']);
        }

        return $recruiters;
    }

    /**
     * Get total count for enhanced list
     */
    public function get_saved_count_enhanced($user_id, $args = []) {
        global $wpdb;

        $where = ["sr.user_id = %d"];
        $params = [$user_id];
        $join = "";

        if (!empty($args['status'])) {
            $where[] = "sr.status = %s";
            $params[] = $args['status'];
        }

        if (!empty($args['tag_id'])) {
            $join .= " JOIN {$this->recruiter_tags_table} rt ON rt.recruiter_id = sr.recruiter_id AND rt.user_id = sr.user_id";
            $where[] = "rt.tag_id = %d";
            $params[] = $args['tag_id'];
        }

        if (!empty($args['search'])) {
            $where[] = "(r.name LIKE %s OR r.firm LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }

        $where_clause = implode(' AND ', $where);

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT sr.id)
             FROM {$this->saved_table} sr
             JOIN {$this->table} r ON r.id = sr.recruiter_id
             {$join}
             WHERE {$where_clause}",
            $params
        ));
    }

    // ============================================
    // RECRUITERS WITH POSTS (for Recruiters tab)
    // ============================================

    /**
     * Get all recruiters that have active posts
     */
    public function get_recruiters_with_posts($user_id, $args = []) {
        global $wpdb;

        $defaults = [
            'page' => 1,
            'per_page' => 20,
            'search' => null,
            'sector' => null,
            'location' => null,
            'has_recent_posts' => null,
            'orderby' => 'last_post_date',
            'order' => 'DESC',
        ];

        $args = array_merge($defaults, $args);

        $where = ["r.is_active = 1"];
        $params = [];

        // Only recruiters with active approved posts
        $where[] = "EXISTS (SELECT 1 FROM {$this->posts_table} p WHERE p.recruiter_id = r.id AND p.is_active = 1 AND p.admin_approved = 1)";

        if ($args['search']) {
            $where[] = "(r.name LIKE %s OR r.firm LIKE %s OR r.title LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }

        if ($args['sector']) {
            $where[] = "r.sectors LIKE %s";
            $params[] = '%' . $wpdb->esc_like($args['sector']) . '%';
        }

        if ($args['location']) {
            $where[] = "(r.location LIKE %s OR r.regions LIKE %s)";
            $location_term = '%' . $wpdb->esc_like($args['location']) . '%';
            $params[] = $location_term;
            $params[] = $location_term;
        }

        if ($args['has_recent_posts']) {
            $where[] = "r.last_post_date > DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }

        $where_clause = implode(' AND ', $where);
        $offset = ($args['page'] - 1) * $args['per_page'];

        // Build order clause
        $order_map = [
            'last_post_date' => 'r.last_post_date',
            'name' => 'r.name',
            'firm' => 'r.firm',
            'total_posts' => 'r.total_posts',
        ];
        $order_col = $order_map[$args['orderby']] ?? 'r.last_post_date';
        $order_dir = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Check if user has saved each recruiter
        $saved_subquery = $user_id
            ? "(SELECT 1 FROM {$this->saved_table} sr WHERE sr.recruiter_id = r.id AND sr.user_id = " . intval($user_id) . " LIMIT 1)"
            : "0";

        $sql = "SELECT r.*,
                    (SELECT COUNT(*) FROM {$this->posts_table} WHERE recruiter_id = r.id AND is_active = 1 AND admin_approved = 1) as active_posts_count,
                    ({$saved_subquery}) as is_saved
             FROM {$this->table} r
             WHERE {$where_clause}
             ORDER BY {$order_col} {$order_dir}
             LIMIT %d OFFSET %d";

        $params[] = $args['per_page'];
        $params[] = $offset;

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $recruiters = $wpdb->get_results($sql, ARRAY_A);

        // Attach user-specific data if logged in
        if ($user_id) {
            foreach ($recruiters as &$recruiter) {
                $recruiter['tags'] = $this->get_recruiter_tags($user_id, $recruiter['id']);

                // Get saved status details
                $saved = $wpdb->get_row($wpdb->prepare(
                    "SELECT status, notes, priority, saved_at FROM {$this->saved_table}
                     WHERE user_id = %d AND recruiter_id = %d",
                    $user_id, $recruiter['id']
                ), ARRAY_A);

                if ($saved) {
                    $recruiter['status'] = $saved['status'];
                    $recruiter['user_notes'] = $saved['notes'];
                    $recruiter['priority'] = $saved['priority'];
                    $recruiter['saved_at'] = $saved['saved_at'];
                } else {
                    $recruiter['status'] = null;
                    $recruiter['user_notes'] = null;
                    $recruiter['priority'] = null;
                    $recruiter['saved_at'] = null;
                }
            }
        }

        return $recruiters;
    }

    /**
     * Get total count of recruiters with posts
     */
    public function get_recruiters_with_posts_count($args = []) {
        global $wpdb;

        $where = ["r.is_active = 1"];
        $params = [];

        // Only recruiters with active approved posts
        $where[] = "EXISTS (SELECT 1 FROM {$this->posts_table} p WHERE p.recruiter_id = r.id AND p.is_active = 1 AND p.admin_approved = 1)";

        if (!empty($args['search'])) {
            $where[] = "(r.name LIKE %s OR r.firm LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }

        if (!empty($args['sector'])) {
            $where[] = "r.sectors LIKE %s";
            $params[] = '%' . $wpdb->esc_like($args['sector']) . '%';
        }

        if (!empty($args['location'])) {
            $where[] = "(r.location LIKE %s OR r.regions LIKE %s)";
            $location_term = '%' . $wpdb->esc_like($args['location']) . '%';
            $params[] = $location_term;
            $params[] = $location_term;
        }

        $where_clause = implode(' AND ', $where);

        $sql = "SELECT COUNT(DISTINCT r.id) FROM {$this->table} r WHERE {$where_clause}";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return (int) $wpdb->get_var($sql);
    }

    // ============================================
    // NOTES MANAGEMENT (Phase 2)
    // ============================================

    /**
     * Get notes for a recruiter
     */
    public function get_notes($user_id, $recruiter_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->notes_table}
             WHERE user_id = %d AND entity_type = 'recruiter' AND entity_id = %d
             ORDER BY is_pinned DESC, created_at DESC",
            $user_id,
            $recruiter_id
        ), ARRAY_A);
    }

    /**
     * Update a note
     */
    public function update_note($user_id, $note_id, $content) {
        global $wpdb;

        return $wpdb->update(
            $this->notes_table,
            ['content' => $content],
            ['id' => $note_id, 'user_id' => $user_id]
        );
    }

    /**
     * Delete a note
     */
    public function delete_note($user_id, $note_id) {
        global $wpdb;

        return $wpdb->delete($this->notes_table, [
            'id' => $note_id,
            'user_id' => $user_id,
        ]);
    }

    /**
     * Toggle note pinned status
     */
    public function toggle_note_pinned($user_id, $note_id) {
        global $wpdb;

        $current = $wpdb->get_var($wpdb->prepare(
            "SELECT is_pinned FROM {$this->notes_table} WHERE id = %d AND user_id = %d",
            $note_id,
            $user_id
        ));

        if ($current === null) {
            return false;
        }

        return $wpdb->update(
            $this->notes_table,
            ['is_pinned' => $current ? 0 : 1],
            ['id' => $note_id, 'user_id' => $user_id]
        );
    }

    /**
     * Set next follow-up date
     */
    public function set_followup($user_id, $recruiter_id, $date) {
        global $wpdb;

        $result = $wpdb->update(
            $this->saved_table,
            ['next_followup_at' => $date],
            ['user_id' => $user_id, 'recruiter_id' => $recruiter_id]
        );

        if ($result) {
            $this->log_activity($user_id, $recruiter_id, 'followup_set', ['date' => $date]);
        }

        return $result;
    }

    /**
     * Get recruiters with upcoming follow-ups
     */
    public function get_upcoming_followups($user_id, $days = 7) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, sr.status, sr.next_followup_at
             FROM {$this->saved_table} sr
             JOIN {$this->table} r ON r.id = sr.recruiter_id
             WHERE sr.user_id = %d
               AND sr.next_followup_at IS NOT NULL
               AND sr.next_followup_at <= DATE_ADD(NOW(), INTERVAL %d DAY)
               AND sr.next_followup_at >= NOW()
             ORDER BY sr.next_followup_at ASC",
            $user_id,
            $days
        ), ARRAY_A);
    }
}
