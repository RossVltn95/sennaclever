<?php
/**
 * CRM Post Group Model
 * Handles post group/category management
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Post_Group {

    private $table;
    private $relationships_table;
    private $posts_table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sffc_crm_post_groups';
        $this->relationships_table = $wpdb->prefix . 'sffc_crm_post_group_relationships';
        $this->posts_table = $wpdb->prefix . 'sffc_crm_posts';
        $this->ensure_schema();
    }

    private function ensure_schema() {
        global $wpdb;

        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->table)) === $this->table;
        if (!$table_exists) {
            return;
        }

        $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$this->table} LIKE %s", 'is_premium'));
        if (empty($column)) {
            $wpdb->query("ALTER TABLE {$this->table} ADD COLUMN is_premium tinyint(1) DEFAULT 0 AFTER is_active");
        }
    }

    /**
     * Get all groups
     *
     * @param array $args Optional filters
     * @return array
     */
    public function get_all($args = []) {
        global $wpdb;

        $defaults = [
            'is_active' => null,
            'order_by' => 'display_order',
            'order' => 'ASC',
            'include_post_count' => true
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $values = [];

        if ($args['is_active'] !== null) {
            $where[] = 'g.is_active = %d';
            $values[] = (int)$args['is_active'];
        }

        $where_clause = implode(' AND ', $where);

        $order_by = in_array($args['order_by'], ['display_order', 'name', 'created_at'])
            ? $args['order_by']
            : 'display_order';

        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        if ($args['include_post_count']) {
            $query = "SELECT g.*, COUNT(pgr.post_id) as post_count
                      FROM {$this->table} g
                      LEFT JOIN {$this->relationships_table} pgr ON g.id = pgr.group_id
                      WHERE {$where_clause}
                      GROUP BY g.id
                      ORDER BY g.{$order_by} {$order}";
        } else {
            $query = "SELECT g.*
                      FROM {$this->table} g
                      WHERE {$where_clause}
                      ORDER BY g.{$order_by} {$order}";
        }

        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Get group by ID
     *
     * @param int $id
     * @return array|null
     */
    public function get_by_id($id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ), ARRAY_A);
    }

    /**
     * Get group by slug
     *
     * @param string $slug
     * @return array|null
     */
    public function get_by_slug($slug) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE slug = %s",
            $slug
        ), ARRAY_A);
    }

    /**
     * Create new group
     *
     * @param array $data
     * @return int|false Group ID on success, false on failure
     */
    public function create($data) {
        global $wpdb;

        // Generate slug from name if not provided
        if (empty($data['slug']) && !empty($data['name'])) {
            $data['slug'] = $this->generate_unique_slug($data['name']);
        }

        $insert_data = [
            'name' => sanitize_text_field($data['name']),
            'slug' => sanitize_title($data['slug']),
            'description' => isset($data['description']) ? wp_kses_post($data['description']) : '',
            'location' => isset($data['location']) ? sanitize_text_field($data['location']) : '',
            'icon' => isset($data['icon']) ? esc_url_raw($data['icon']) : '',
            'display_order' => isset($data['display_order']) ? (int)$data['display_order'] : 0,
            'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'is_premium' => isset($data['is_premium']) ? (int)$data['is_premium'] : 0
        ];

        $result = $wpdb->insert($this->table, $insert_data, [
            '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d'
        ]);

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update group
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data) {
        global $wpdb;

        $update_data = [];
        $format = [];

        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $format[] = '%s';
        }

        if (isset($data['slug'])) {
            $update_data['slug'] = sanitize_title($data['slug']);
            $format[] = '%s';
        }

        if (isset($data['description'])) {
            $update_data['description'] = wp_kses_post($data['description']);
            $format[] = '%s';
        }

        if (isset($data['location'])) {
            $update_data['location'] = sanitize_text_field($data['location']);
            $format[] = '%s';
        }

        if (isset($data['icon'])) {
            $update_data['icon'] = esc_url_raw($data['icon']);
            $format[] = '%s';
        }

        if (isset($data['display_order'])) {
            $update_data['display_order'] = (int)$data['display_order'];
            $format[] = '%d';
        }

        if (isset($data['is_active'])) {
            $update_data['is_active'] = (int)$data['is_active'];
            $format[] = '%d';
        }

        if (isset($data['is_premium'])) {
            $update_data['is_premium'] = (int)$data['is_premium'];
            $format[] = '%d';
        }

        if (empty($update_data)) {
            return false;
        }

        return $wpdb->update(
            $this->table,
            $update_data,
            ['id' => $id],
            $format,
            ['%d']
        ) !== false;
    }

    /**
     * Delete group
     *
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        global $wpdb;

        // Delete relationships first
        $wpdb->delete($this->relationships_table, ['group_id' => $id], ['%d']);

        // Delete the group
        return $wpdb->delete($this->table, ['id' => $id], ['%d']) !== false;
    }

    /**
     * Get posts by group
     *
     * @param int $group_id
     * @param array $args Optional filters
     * @return array
     */
    public function get_posts_by_group($group_id, $args = []) {
        global $wpdb;

        $defaults = [
            'limit' => null,
            'offset' => 0,
            'is_active' => 1,
            'admin_approved' => 1
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['pgr.group_id = %d'];
        $values = [$group_id];

        if ($args['is_active'] !== null) {
            $where[] = 'p.is_active = %d';
            $values[] = (int)$args['is_active'];
        }

        if ($args['admin_approved'] !== null) {
            $where[] = 'p.admin_approved = %d';
            $values[] = (int)$args['admin_approved'];
        }

        $where_clause = implode(' AND ', $where);

        $query = "SELECT DISTINCT p.*, r.name as recruiter_name, r.firm as recruiter_firm,
                         r.photo_url as recruiter_photo, r.title as recruiter_title,
                         r.email as recruiter_email, r.linkedin_url as recruiter_linkedin
                 FROM {$this->posts_table} p
                  INNER JOIN {$this->relationships_table} pgr ON p.id = pgr.post_id
                  LEFT JOIN {$wpdb->prefix}sffc_crm_recruiters r ON r.id = p.recruiter_id
                  WHERE {$where_clause}
                  ORDER BY p.posted_at DESC";

        if ($args['limit']) {
            $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }

        $query = $wpdb->prepare($query, $values);

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Count posts within a group (honors visibility filters)
     *
     * @param int   $group_id Group ID
     * @param array $args     Optional filters (is_active, admin_approved)
     * @return int
     */
    public function count_posts_in_group($group_id, $args = []) {
        global $wpdb;

        $defaults = [
            'is_active' => 1,
            'admin_approved' => 1,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['pgr.group_id = %d'];
        $values = [$group_id];

        if ($args['is_active'] !== null) {
            $where[] = 'p.is_active = %d';
            $values[] = (int) $args['is_active'];
        }

        if ($args['admin_approved'] !== null) {
            $where[] = 'p.admin_approved = %d';
            $values[] = (int) $args['admin_approved'];
        }

        $where_clause = implode(' AND ', $where);

        $sql = "SELECT COUNT(DISTINCT p.id)
                FROM {$this->posts_table} p
                INNER JOIN {$this->relationships_table} pgr ON p.id = pgr.post_id
                WHERE {$where_clause}";

        return (int) $wpdb->get_var($wpdb->prepare($sql, $values));
    }

    /**
     * Get groups for a post
     *
     * @param int $post_id
     * @return array
     */
    public function get_groups_for_post($post_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT g.*
             FROM {$this->table} g
             INNER JOIN {$this->relationships_table} pgr ON g.id = pgr.group_id
             WHERE pgr.post_id = %d
             ORDER BY g.name ASC",
            $post_id
        ), ARRAY_A);
    }

    /**
     * Check if a post already belongs to a group
     *
     * @param int $post_id
     * @param int $group_id
     * @return bool
     */
    public function post_in_group($post_id, $group_id) {
        global $wpdb;

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$this->relationships_table} WHERE post_id = %d AND group_id = %d LIMIT 1",
            $post_id,
            $group_id
        ));

        return !empty($exists);
    }

    /**
     * Assign post to group
     *
     * @param int $post_id
     * @param int $group_id
     * @return bool
     */
    public function assign_post($post_id, $group_id) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->relationships_table,
            [
                'post_id' => $post_id,
                'group_id' => $group_id
            ],
            ['%d', '%d']
        );

        return $result !== false;
    }

    /**
     * Suggest active tracker groups for a role using dynamic group names and role signals.
     *
     * @param array $post_data Role data from CRM post, scanner draft, or feed payload.
     * @param int   $limit     Maximum group IDs to return.
     * @param int   $min_score Minimum match score required.
     * @return array
     */
    public function suggest_groups_for_post(array $post_data, $limit = 8, $min_score = 42, array $args = []) {
        $groups = $this->get_all([
            'is_active' => 1,
            'include_post_count' => false,
        ]);

        if (empty($groups)) {
            return [];
        }

        $signals = $this->build_post_group_match_signals($post_data);
        $signals['strict_location_match'] = !empty($args['strict_location_match']);
        $ranked = [];

        foreach ($groups as $group) {
            $score = $this->score_post_group_match($group, $signals);
            if ($score >= $min_score) {
                $ranked[] = [
                    'id' => (int) $group['id'],
                    'score' => $score,
                    'name' => (string) ($group['name'] ?? ''),
                ];
            }
        }

        usort($ranked, static function($a, $b) {
            if ($a['score'] === $b['score']) {
                return strcasecmp($a['name'], $b['name']);
            }
            return $b['score'] <=> $a['score'];
        });

        return array_slice(array_column($ranked, 'id'), 0, max(1, (int) $limit));
    }

    public function filter_group_ids_for_post(array $group_ids, array $post_data, array $args = []) {
        $group_ids = array_values(array_unique(array_filter(array_map('intval', $group_ids))));
        if (empty($group_ids)) {
            return [];
        }

        $groups = $this->get_all([
            'is_active' => 1,
            'include_post_count' => false,
        ]);
        if (empty($groups)) {
            return [];
        }

        $wanted = array_flip($group_ids);
        $signals = $this->build_post_group_match_signals($post_data);
        $signals['strict_location_match'] = !empty($args['strict_location_match']);
        $min_score = max(1, (int) ($args['min_score'] ?? 1));
        $filtered = [];

        foreach ($groups as $group) {
            $group_id = (int) ($group['id'] ?? 0);
            if ($group_id <= 0 || !isset($wanted[$group_id])) {
                continue;
            }

            if ($this->is_post_group_location_allowed($group, $signals) && $this->score_post_group_match($group, $signals) >= $min_score) {
                $filtered[] = $group_id;
            }
        }

        return $filtered;
    }

    private function build_post_group_match_signals(array $post_data) {
        $title = $this->normalize_group_match_text($post_data['role_title'] ?? ($post_data['raw_title'] ?? ''));
        $company = $this->normalize_group_match_text($post_data['company'] ?? ($post_data['raw_company'] ?? ''));
        $location = $this->normalize_group_match_text(implode(' ', array_filter([
            $post_data['location'] ?? ($post_data['raw_location'] ?? ''),
            $post_data['location_city'] ?? ($post_data['raw_location_city'] ?? ''),
            $post_data['location_country'] ?? ($post_data['raw_location_country'] ?? ''),
        ])));
        $sector = $this->normalize_group_match_text($post_data['sector'] ?? ($post_data['raw_sector'] ?? ''));
        $seniority = $this->normalize_group_match_text($post_data['seniority'] ?? ($post_data['raw_seniority'] ?? ''));
        $content = $this->normalize_group_match_text(implode(' ', array_filter([
            $post_data['content'] ?? ($post_data['raw_content'] ?? ''),
            $post_data['keywords'] ?? '',
            $post_data['source_platform'] ?? '',
        ])));
        $haystack = trim($title . ' ' . $company . ' ' . $location . ' ' . $sector . ' ' . $seniority . ' ' . $content);

        return [
            'title' => $title,
            'company' => $company,
            'location' => $location,
            'sector' => $sector,
            'seniority' => $seniority,
            'content' => $content,
            'haystack' => $haystack,
            'explicit_locations' => $this->detect_group_match_locations($location),
            'locations' => $this->detect_group_match_locations($location . ' ' . $haystack),
            'sectors' => $this->detect_group_match_sectors($title . ' ' . $sector . ' ' . $content),
            'seniorities' => $this->detect_group_match_seniorities($title . ' ' . $seniority . ' ' . $content),
        ];
    }

    private function score_post_group_match(array $group, array $signals) {
        $name = $this->normalize_group_match_text($group['name'] ?? '');
        $description = $this->normalize_group_match_text($group['description'] ?? '');
        $location = $this->normalize_group_match_text($group['location'] ?? '');
        $slug = $this->normalize_group_match_text($group['slug'] ?? '');
        $group_text = trim($name . ' ' . $description . ' ' . $location . ' ' . $slug);

        if ($group_text === '') {
            return 0;
        }

        $score = 0;
        $group_locations = $this->detect_group_match_locations($group_text);
        $group_sectors = $this->detect_group_match_sectors($group_text);
        $group_seniorities = $this->detect_group_match_seniorities($group_text);

        if (!$this->is_post_group_location_allowed($group, $signals)) {
            return 0;
        }

        if (!empty($group_locations) && $this->has_group_location_overlap($group_locations, $signals)) {
            $score += 30;
        } elseif (!empty($group_locations)) {
            $score -= 24;
        }

        if (!empty($group_sectors) && !empty(array_intersect($group_sectors, $signals['sectors']))) {
            $score += 34;
        } elseif (in_array('finance', $group_sectors, true) && in_array('accounting', $signals['sectors'], true)) {
            $score += 24;
        } elseif (!empty($group_sectors)) {
            $score -= 18;
        }

        if (!empty($group_seniorities) && !empty(array_intersect($group_seniorities, $signals['seniorities']))) {
            $score += 26;
        } elseif (!empty($group_seniorities)) {
            $score -= 14;
        }

        if (strpos($group_text, 'jobs') !== false || strpos($group_text, 'roles') !== false) {
            $score += 5;
        }

        foreach ($this->extract_group_match_tokens($signals['title']) as $token) {
            if (strlen($token) >= 5 && strpos($group_text, $token) !== false) {
                $score += 5;
            }
        }

        if (strpos($group_text, 'senna community') !== false || strpos($group_text, 'mena careers community') !== false) {
            $score += 12;
        }

        if (empty($group_locations) && empty($group_sectors) && empty($group_seniorities)) {
            $score -= 20;
        }

        return max(0, min(100, $score));
    }

    private function is_post_group_location_allowed(array $group, array $signals) {
        $group_text = trim($this->normalize_group_match_text($group['name'] ?? '') . ' ' . $this->normalize_group_match_text($group['description'] ?? '') . ' ' . $this->normalize_group_match_text($group['location'] ?? '') . ' ' . $this->normalize_group_match_text($group['slug'] ?? ''));
        $group_locations = $this->detect_group_match_locations($group_text);
        $role_locations = !empty($signals['explicit_locations']) ? $signals['explicit_locations'] : ($signals['locations'] ?? []);

        if (empty($role_locations)) {
            return true;
        }

        if (empty($group_locations)) {
            return empty($signals['strict_location_match']);
        }

        if (!$this->has_group_location_overlap($group_locations, $signals)) {
            return false;
        }

        if (!empty($signals['strict_location_match']) && $this->has_specific_role_location($role_locations)) {
            $specific_group_locations = array_diff($group_locations, ['middle_east', 'europe', 'asia']);
            if (empty($specific_group_locations)) {
                return false;
            }
        }

        return true;
    }

    private function is_group_location_compatible(array $group_locations, array $signals) {
        if (empty($group_locations)) {
            return true;
        }

        $role_locations = !empty($signals['explicit_locations']) ? $signals['explicit_locations'] : ($signals['locations'] ?? []);
        if (empty($role_locations)) {
            return true;
        }

        return $this->has_group_location_overlap($group_locations, $signals);
    }

    private function has_specific_role_location(array $role_locations) {
        foreach ($role_locations as $role_location) {
            if (!in_array($role_location, ['middle_east', 'europe', 'asia'], true)) {
                return true;
            }
        }

        return false;
    }

    private function has_group_location_overlap(array $group_locations, array $signals) {
        $role_locations = !empty($signals['explicit_locations']) ? $signals['explicit_locations'] : ($signals['locations'] ?? []);
        foreach ($group_locations as $group_location) {
            foreach ($role_locations as $role_location) {
                if ($this->is_group_location_pair_compatible($group_location, $role_location)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function is_group_location_pair_compatible($group_location, $role_location) {
        if ($group_location === $role_location) {
            return true;
        }

        $regional = [
            'dubai' => ['dubai'],
            'abu_dhabi' => ['abu_dhabi'],
            'uae' => ['dubai', 'abu_dhabi', 'uae'],
            'riyadh' => ['riyadh'],
            'saudi' => ['riyadh', 'saudi'],
            'doha' => ['doha'],
            'qatar' => ['doha', 'qatar'],
            'middle_east' => ['dubai', 'abu_dhabi', 'uae', 'riyadh', 'saudi', 'doha', 'qatar', 'middle_east'],
            'london' => ['london'],
            'germany' => ['germany', 'europe'],
            'europe' => ['london', 'germany', 'europe'],
            'asia' => ['asia'],
        ];

        return isset($regional[$group_location]) && in_array($role_location, $regional[$group_location], true);
    }

    private function detect_group_match_locations($text) {
        $text = $this->normalize_group_match_text($text);
        $matches = [];
        $map = [
            'dubai' => '/\bdubai\b/',
            'abu_dhabi' => '/\babu dhabi\b/',
            'uae' => '/\b(uae|united arab emirates)\b/',
            'riyadh' => '/\briyadh\b/',
            'saudi' => '/\b(saudi|saudi arabia|ksa|jeddah|dammam|khobar|dhahran)\b/',
            'doha' => '/\bdoha\b/',
            'qatar' => '/\bqatar\b/',
            'middle_east' => '/\b(middle east|mena|gcc)\b/',
            'london' => '/\b(london|united kingdom|uk|england)\b/',
            'germany' => '/\b(germany|frankfurt|munich|berlin)\b/',
            'europe' => '/\b(europe|european|france|paris|spain|madrid|italy|milan|germany|frankfurt|munich|berlin|netherlands|amsterdam|luxembourg|switzerland|zurich|geneva|ireland|dublin)\b/',
            'asia' => '/\b(asia|singapore|hong kong|india|mumbai|china|japan)\b/',
        ];

        foreach ($map as $key => $pattern) {
            if (preg_match($pattern, $text)) {
                $matches[] = $key;
            }
        }

        return array_values(array_unique($matches));
    }

    private function detect_group_match_sectors($text) {
        $text = $this->normalize_group_match_text($text);
        $matches = [];
        $map = [
            'private_equity' => '/\b(private equity|pe\b|buyout|growth equity|investment associate|investment analyst|portfolio monitoring)\b/',
            'investment_banking' => '/\b(investment banking|m and a|m&a|mergers acquisitions|corporate finance|transaction services)\b/',
            'finance' => '/\b(finance|financial|fp and a|fpa|treasury|trade finance|emirates nbd)\b/',
            'accounting' => '/\b(accountant|accounting|audit|tax|controller|bookkeeper|accounts payable|accounts receivable)\b/',
            'real_estate' => '/\b(real estate|property|reit|development manager|asset management real estate)\b/',
            'investor_relations' => '/\b(investor relations|capital formation|fundraising|client solutions|placement agent)\b/',
            'asset_management' => '/\b(asset management|portfolio manager|investment management|wealth management|family office|private banking)\b/',
            'private_credit' => '/\b(private credit|credit fund|direct lending|leveraged finance|structured credit)\b/',
            'venture_capital' => '/\b(venture capital|vc\b|startup|growth investing)\b/',
        ];

        foreach ($map as $key => $pattern) {
            if (preg_match($pattern, $text)) {
                $matches[] = $key;
            }
        }

        return array_values(array_unique($matches));
    }

    private function detect_group_match_seniorities($text) {
        $text = $this->normalize_group_match_text($text);
        $matches = [];

        if (preg_match('/\b(intern(ship|ships)?|graduate(?:\s+[a-z0-9]+){0,4}\s+(programme|program)|summer analyst|trainee|campus)\b/', $text)) {
            $matches[] = 'intern';
        }
        if (!empty($matches)) {
            return array_values(array_unique($matches));
        }
        if (preg_match('/\b(vp|avp|vice president|principal|director|head of|chief|managing director|partner|c suite|c-suite|board)\b/', $text)) {
            return ['senior'];
        }
        if (preg_match('/\b(analyst|junior analyst|assistant relationship manager|junior relationship manager|officer|specialist|coordinator)\b/', $text)) {
            $matches[] = 'analyst';
        }
        if (preg_match('/\b(associate|relationship manager|manager|consultant)\b/', $text)) {
            $matches[] = 'associate';
        }
        if (preg_match('/\bsenior\b/', $text)) {
            $matches[] = 'senior';
        }

        return array_values(array_unique($matches));
    }

    private function normalize_group_match_text($value) {
        $value = html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES, 'UTF-8');
        $value = strtolower(str_replace(['–', '—', '/', '&'], ['-', '-', ' ', ' and '], $value));
        $value = preg_replace('/[^a-z0-9\+\.\-\s]/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim((string) $value);
    }

    private function extract_group_match_tokens($text) {
        $tokens = preg_split('/\s+/', $this->normalize_group_match_text($text));
        $stop = [
            'jobs' => true,
            'roles' => true,
            'role' => true,
            'and' => true,
            'the' => true,
            'for' => true,
            'with' => true,
            'in' => true,
            'of' => true,
            'to' => true,
        ];

        return array_values(array_filter((array) $tokens, static function($token) use ($stop) {
            return $token !== '' && empty($stop[$token]);
        }));
    }

    /**
     * Remove post from group
     *
     * @param int $post_id
     * @param int $group_id
     * @return bool
     */
    public function remove_post($post_id, $group_id) {
        global $wpdb;

        return $wpdb->delete(
            $this->relationships_table,
            [
                'post_id' => $post_id,
                'group_id' => $group_id
            ],
            ['%d', '%d']
        ) !== false;
    }

    /**
     * Remove all groups from a post
     *
     * @param int $post_id
     * @return bool
     */
    public function remove_all_groups($post_id) {
        global $wpdb;

        return $wpdb->delete(
            $this->relationships_table,
            ['post_id' => $post_id],
            ['%d']
        ) !== false;
    }

    /**
     * Generate unique slug
     *
     * @param string $name
     * @return string
     */
    private function generate_unique_slug($name) {
        global $wpdb;

        $slug = sanitize_title($name);
        $original_slug = $slug;
        $counter = 1;

        while ($this->slug_exists($slug)) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if slug exists
     *
     * @param string $slug
     * @param int|null $exclude_id Optional ID to exclude from check
     * @return bool
     */
    private function slug_exists($slug, $exclude_id = null) {
        global $wpdb;

        $query = "SELECT id FROM {$this->table} WHERE slug = %s";
        $values = [$slug];

        if ($exclude_id) {
            $query .= " AND id != %d";
            $values[] = $exclude_id;
        }

        return (bool)$wpdb->get_var($wpdb->prepare($query, $values));
    }
}
