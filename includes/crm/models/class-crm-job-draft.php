<?php
/**
 * CRM Job Draft Model
 * Stores raw scanner/import intake before editorial approval.
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Job_Draft {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sffc_crm_job_drafts';
        $this->maybe_prepare_table();
    }

    public function create(array $data) {
        global $wpdb;

        $data = $this->sanitize_data($data);
        $data = wp_parse_args($data, [
            'status' => 'new',
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
        ]);

        if (empty($data['source_hash'])) {
            $data['source_hash'] = $this->build_source_hash($data);
        }

        $duplicate = $this->find_duplicate($data);
        if ($duplicate) {
            $data['duplicate_of'] = (int) $duplicate['id'];
            $data['status'] = 'duplicate';
        }

        $data = $this->filter_existing_columns($data);
        $result = $wpdb->insert($this->table, $data);

        return $result ? (int) $wpdb->insert_id : false;
    }

    public function get($id) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", (int) $id),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        foreach (['extracted_payload', 'rewritten_payload'] as $field) {
            if (!empty($row[$field])) {
                $decoded = json_decode((string) $row[$field], true);
                if (is_array($decoded)) {
                    $row[$field] = $decoded;
                }
            }
        }

        return $row;
    }

    public function query(array $args = []) {
        global $wpdb;

        $args = wp_parse_args($args, [
            'status' => '',
            'include_approved' => false,
            'search' => '',
            'queue_filter' => '',
            'sort' => 'posted_desc',
            'limit' => 50,
            'offset' => 0,
        ]);

        $clauses = $this->build_query_clauses($args);

        $limit = max(1, min(200, (int) $args['limit']));
        $offset = max(0, (int) $args['offset']);
        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $clauses['where']) . " ORDER BY " . $this->get_order_by((string) $args['sort']) . " LIMIT %d OFFSET %d";
        $values = $clauses['values'];
        $values[] = $limit;
        $values[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
    }

    public function count(array $args = []) {
        global $wpdb;

        $args = wp_parse_args($args, [
            'status' => '',
            'include_approved' => false,
            'search' => '',
            'queue_filter' => '',
        ]);

        $clauses = $this->build_query_clauses($args);
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE " . implode(' AND ', $clauses['where']);

        if (empty($clauses['values'])) {
            return (int) $wpdb->get_var($sql);
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, $clauses['values']));
    }

    private function build_query_clauses(array $args) {
        global $wpdb;

        $where = ['1=1'];
        $values = [];
        $status = sanitize_key((string) ($args['status'] ?? ''));

        if ($status !== '') {
            $where[] = 'status = %s';
            $values[] = $status;
        } elseif (empty($args['include_approved'])) {
            $where[] = "(status IS NULL OR status <> 'approved')";
        }

        if ($args['search'] !== '') {
            $like = '%' . $wpdb->esc_like((string) $args['search']) . '%';
            $where[] = '(raw_title LIKE %s OR raw_company LIKE %s OR raw_location LIKE %s OR source_url LIKE %s)';
            array_push($values, $like, $like, $like, $like);
        }

        switch (sanitize_key((string) ($args['queue_filter'] ?? ''))) {
            case 'duplicates':
                $where[] = "(status = 'duplicate' OR duplicate_of IS NOT NULL)";
                break;
            case 'low_confidence':
                $where[] = 'confidence_score < %d';
                $values[] = 60;
                break;
            case 'missing_application':
                $where[] = "(application_url IS NULL OR application_url = '')";
                break;
            case 'missing_company':
                $where[] = "(raw_company IS NULL OR raw_company = '')";
                break;
            case 'missing_location':
                $where[] = "(raw_location IS NULL OR raw_location = '')";
                break;
            case 'unassigned_tracker':
                $where[] = "(rewritten_payload IS NULL OR rewritten_payload = '' OR rewritten_payload NOT LIKE %s)";
                $values[] = '%post_group_ids%';
                break;
            case 'recent_7_days':
                $where[] = 'COALESCE(posted_at, created_at) >= %s';
                $values[] = wp_date('Y-m-d H:i:s', current_time('timestamp') - (7 * DAY_IN_SECONDS));
                break;
        }

        return [
            'where' => $where,
            'values' => $values,
        ];
    }

    private function get_order_by($sort) {
        switch (sanitize_key((string) $sort)) {
            case 'created_desc':
                return 'created_at DESC';
            case 'confidence_desc':
                return 'confidence_score DESC, posted_at IS NULL ASC, posted_at DESC, created_at DESC';
            case 'company_asc':
                return "raw_company = '' ASC, raw_company ASC, posted_at IS NULL ASC, posted_at DESC";
            case 'status_asc':
                return 'status ASC, posted_at IS NULL ASC, posted_at DESC, created_at DESC';
            case 'posted_desc':
            default:
                return 'posted_at IS NULL ASC, posted_at DESC, created_at DESC';
        }
    }

    public function update($id, array $data) {
        global $wpdb;

        $data = $this->filter_existing_columns($this->sanitize_data($data));
        if (empty($data)) {
            return false;
        }

        return $wpdb->update($this->table, $data, ['id' => (int) $id]) !== false;
    }

    public function mark_approved($id, $crm_post_id) {
        return $this->update((int) $id, [
            'status' => 'approved',
            'approved_crm_post_id' => (int) $crm_post_id,
            'approved_by' => get_current_user_id(),
            'approved_at' => current_time('mysql'),
        ]);
    }

    public function mark_rejected($id) {
        return $this->update((int) $id, [
            'status' => 'rejected',
            'rejected_by' => get_current_user_id(),
            'rejected_at' => current_time('mysql'),
        ]);
    }

    public function delete($id) {
        global $wpdb;

        return $wpdb->delete($this->table, ['id' => (int) $id], ['%d']) !== false;
    }

    public function delete_many(array $ids) {
        $deleted = 0;
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        foreach ($ids as $id) {
            if ($this->delete($id)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public function find_duplicate(array $data) {
        global $wpdb;

        foreach ($this->build_dedupe_urls($data) as $source_url) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$this->table}
                     WHERE source_url = %s OR application_url = %s
                     ORDER BY id DESC LIMIT 1",
                    $source_url,
                    $source_url
                ),
                ARRAY_A
            );
            if ($row) {
                return $row;
            }
        }

        $source_hashes = array_values(array_unique(array_filter([
            sanitize_text_field((string) ($data['source_hash'] ?? '')),
            $this->build_content_fingerprint($data),
        ])));

        foreach ($source_hashes as $source_hash) {
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$this->table} WHERE source_hash = %s ORDER BY id DESC LIMIT 1", $source_hash),
                ARRAY_A
            );
            if ($row) {
                return $row;
            }
        }

        $title = $this->normalize_dedupe_text($data['raw_title'] ?? '');
        $company = $this->normalize_dedupe_text($data['raw_company'] ?? '');
        $location = $this->normalize_dedupe_location($data['raw_location'] ?? '');
        if ($title !== '' && $company !== '' && $location !== '') {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$this->table}
                     WHERE LOWER(TRIM(raw_title)) = %s
                       AND LOWER(TRIM(raw_company)) = %s
                       AND LOWER(TRIM(raw_location)) = %s
                     ORDER BY id DESC LIMIT 1",
                    $title,
                    $company,
                    $location
                ),
                ARRAY_A
            );
            if ($row) {
                return $row;
            }
        }

        $existing_crm_post_id = $this->find_duplicate_crm_post_id($data);
        if ($existing_crm_post_id > 0) {
            return [
                'id' => 0,
                'status' => 'approved',
                'approved_crm_post_id' => $existing_crm_post_id,
            ];
        }

        $existing_jobs_post_id = $this->find_duplicate_jobs_post_id($data);
        if ($existing_jobs_post_id > 0) {
            return [
                'id' => 0,
                'status' => 'approved',
                'jobs_post_id' => $existing_jobs_post_id,
            ];
        }

        return null;
    }

    public function build_source_hash(array $data) {
        $fingerprint = $this->build_content_fingerprint($data);
        if ($fingerprint !== '') {
            return $fingerprint;
        }

        return hash('sha256', implode('|', $this->build_dedupe_urls($data)));
    }

    private function build_content_fingerprint(array $data) {
        $parts = array_filter([
            $this->normalize_dedupe_text($data['raw_title'] ?? ''),
            $this->normalize_dedupe_text($data['raw_company'] ?? ''),
            $this->normalize_dedupe_location($data['raw_location'] ?? ''),
        ]);

        if (count($parts) < 3) {
            return '';
        }

        return hash('sha256', implode('|', $parts));
    }

    private function build_dedupe_urls(array $data) {
        $urls = [];
        foreach (['source_url', 'application_url'] as $field) {
            $raw_url = trim(html_entity_decode((string) ($data[$field] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($raw_url !== '') {
                $urls[] = strtolower(rtrim($raw_url, '/'));
            }

            $normalized_url = $this->normalize_dedupe_url($raw_url);
            if ($normalized_url !== '') {
                $urls[] = $normalized_url;
            }
        }

        return array_values(array_unique($urls));
    }

    private function normalize_dedupe_url($url) {
        $url = trim(html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') {
            return '';
        }

        $parts = wp_parse_url($url);
        if (empty($parts['host'])) {
            return strtolower(rtrim($url, '/'));
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower((string) $parts['host']);
        $path = '/' . ltrim((string) ($parts['path'] ?? ''), '/');
        $path = rtrim($path, '/');
        $query = [];

        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
            foreach (array_keys($query) as $key) {
                if (preg_match('/^(utm_|fbclid$|gclid$|msclkid$|source$|src$|ref$|referrer$|campaign$)/i', (string) $key)) {
                    unset($query[$key]);
                }
            }
            ksort($query);
        }

        $normalized = $scheme . '://' . $host . $path;
        if (!empty($query)) {
            $normalized .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return strtolower($normalized);
    }

    private function normalize_dedupe_text($value) {
        $value = strtolower(wp_strip_all_tags((string) $value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);
        $value = preg_replace('/\s+/', ' ', trim((string) $value));

        return (string) $value;
    }

    private function normalize_dedupe_location($value) {
        $value = $this->normalize_dedupe_text($value);
        $aliases = [
            'dubai uae' => 'dubai united arab emirates',
            'dubai united arab emirates' => 'dubai united arab emirates',
            'abu dhabi uae' => 'abu dhabi united arab emirates',
            'abu dhabi united arab emirates' => 'abu dhabi united arab emirates',
            'riyadh ksa' => 'riyadh saudi arabia',
            'riyadh saudi arabia' => 'riyadh saudi arabia',
            'doha qatar' => 'doha qatar',
        ];

        return $aliases[$value] ?? $value;
    }

    private function find_duplicate_crm_post_id(array $data) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_crm_posts';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return 0;
        }

        foreach ($this->build_dedupe_urls($data) as $url) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table}
                 WHERE application_url = %s OR source_url = %s
                 ORDER BY id DESC LIMIT 1",
                $url,
                $url
            ));
            if (!empty($existing)) {
                return (int) $existing;
            }
        }

        $title = $this->normalize_dedupe_text($data['raw_title'] ?? '');
        $company = $this->normalize_dedupe_text($data['raw_company'] ?? '');
        $location = $this->normalize_dedupe_location($data['raw_location'] ?? '');
        if ($title === '' || $company === '' || $location === '') {
            return 0;
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table}
             WHERE LOWER(TRIM(role_title)) = %s
               AND LOWER(TRIM(company)) = %s
               AND LOWER(TRIM(location)) = %s
             ORDER BY id DESC LIMIT 1",
            $title,
            $company,
            $location
        ));

        return !empty($existing) ? (int) $existing : 0;
    }

    private function find_duplicate_jobs_post_id(array $data) {
        global $wpdb;

        foreach ($this->build_dedupe_urls($data) as $url) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT pm.post_id
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE p.post_type = %s
                   AND pm.meta_key IN ('applicationLink', 'newapplicationLink', '_application_url', 'sffc_application_url')
                   AND pm.meta_value = %s
                 ORDER BY pm.meta_id DESC LIMIT 1",
                'jobs',
                $url
            ));
            if (!empty($existing)) {
                return (int) $existing;
            }
        }

        return 0;
    }

    private function sanitize_data(array $data) {
        $json_fields = ['extracted_payload', 'rewritten_payload'];
        foreach ($json_fields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = wp_json_encode($data[$field], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (in_array($key, ['extracted_payload', 'rewritten_payload'], true)) {
                $data[$key] = is_string($value) ? wp_check_invalid_utf8($value) : $value;
                continue;
            }

            if ($key === 'raw_content') {
                $data[$key] = is_string($value) ? wp_kses_post($value) : $value;
                continue;
            }

            if ($key === 'posted_at') {
                $data[$key] = $this->normalize_datetime_value($value);
                continue;
            }

            if ($key === 'error_message') {
                $data[$key] = sanitize_textarea_field((string) $value);
                continue;
            }

            if (in_array($key, ['source_url', 'application_url', 'raw_company_logo'], true)) {
                $data[$key] = esc_url_raw((string) $value);
                continue;
            }

            if (in_array($key, ['created_by', 'approved_by', 'rejected_by', 'approved_crm_post_id', 'duplicate_of'], true)) {
                $data[$key] = (int) $value;
                continue;
            }

            if ($key === 'confidence_score') {
                $data[$key] = max(0, min(100, (float) $value));
                continue;
            }

            $data[$key] = sanitize_text_field((string) $value);
        }

        return $data;
    }

    private function normalize_datetime_value($value) {
        $value = trim(sanitize_text_field((string) $value));
        if ($value === '' || preg_match('/^\d{4}$/', $value) || preg_match('/^0{4}-0{2}-0{2}/', $value)) {
            return null;
        }

        $timestamp = strtotime($value);
        if (!$timestamp || $timestamp <= 0) {
            return null;
        }

        $now = current_time('timestamp');
        if ($timestamp > ($now + DAY_IN_SECONDS) || $timestamp < strtotime('2000-01-01 00:00:00')) {
            return null;
        }

        return wp_date('Y-m-d H:i:s', $timestamp);
    }

    private function filter_existing_columns(array $data) {
        global $wpdb;

        $columns = $wpdb->get_col("DESCRIBE {$this->table}", 0);
        if (empty($columns)) {
            return [];
        }

        return array_intersect_key($data, array_flip($columns));
    }

    private function maybe_prepare_table() {
        global $wpdb;

        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->table));
        if ($exists === $this->table) {
            $this->maybe_upgrade_table();
            return;
        }

        if (class_exists('SFFC_CRM_Database_Schema')) {
            SFFC_CRM_Database_Schema::get_instance()->create_tables();
        }

        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->table));
        if ($exists !== $this->table) {
            $this->create_table();
        }
        $this->maybe_upgrade_table();
    }

    private function maybe_upgrade_table() {
        global $wpdb;

        $columns = $wpdb->get_col("DESCRIBE {$this->table}", 0);
        if (!is_array($columns)) {
            return;
        }

        if (!in_array('posted_at', $columns, true)) {
            $wpdb->query("ALTER TABLE {$this->table} ADD posted_at datetime DEFAULT NULL AFTER raw_experience_years");
            $columns[] = 'posted_at';
        }

        if (!in_array('raw_posted_at', $columns, true)) {
            $wpdb->query("ALTER TABLE {$this->table} ADD raw_posted_at varchar(160) DEFAULT NULL AFTER posted_at");
        }

        $index = $wpdb->get_var("SHOW INDEX FROM {$this->table} WHERE Key_name = 'idx_posted_at'");
        if (!$index) {
            $wpdb->query("ALTER TABLE `{$this->table}` ADD INDEX `idx_posted_at` (`posted_at`)");
        }
    }

    private function create_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            source_url varchar(500) DEFAULT NULL,
            application_url varchar(500) DEFAULT NULL,
            source_platform varchar(80) DEFAULT NULL,
            source_platform_custom varchar(120) DEFAULT NULL,
            external_job_id varchar(160) DEFAULT NULL,
            source_hash varchar(64) DEFAULT NULL,
            raw_title varchar(300) DEFAULT NULL,
            raw_company varchar(200) DEFAULT NULL,
            raw_location varchar(200) DEFAULT NULL,
            raw_location_city varchar(100) DEFAULT NULL,
            raw_location_country varchar(100) DEFAULT NULL,
            raw_salary_text varchar(160) DEFAULT NULL,
            raw_company_logo varchar(500) DEFAULT NULL,
            raw_sector varchar(100) DEFAULT NULL,
            raw_seniority varchar(80) DEFAULT NULL,
            raw_experience_years varchar(50) DEFAULT NULL,
            posted_at datetime DEFAULT NULL,
            raw_posted_at varchar(160) DEFAULT NULL,
            raw_content longtext,
            extracted_payload longtext,
            rewritten_payload longtext,
            confidence_score decimal(5,2) DEFAULT 0.00,
            duplicate_of bigint(20) DEFAULT NULL,
            status varchar(40) DEFAULT 'new',
            error_message text DEFAULT NULL,
            approved_crm_post_id bigint(20) DEFAULT NULL,
            approved_by bigint(20) DEFAULT NULL,
            approved_at datetime DEFAULT NULL,
            rejected_by bigint(20) DEFAULT NULL,
            rejected_at datetime DEFAULT NULL,
            created_by bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_source_hash (source_hash),
            KEY idx_source_platform (source_platform),
            KEY idx_approved_crm_post (approved_crm_post_id),
            KEY idx_duplicate_of (duplicate_of),
            KEY idx_created_at (created_at),
            KEY idx_posted_at (posted_at),
            FULLTEXT KEY ft_draft_search (raw_title, raw_company, raw_location, raw_content)
        ) {$charset_collate};";

        dbDelta($sql);
    }
}
