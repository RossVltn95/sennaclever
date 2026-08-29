<?php
/**
 * HR Outreach Model
 * Stores curated talent acquisition & key team contacts
 *
 * @package SennaCareers
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_HR_Outreach {

    private $table;
    private static $columns_ready = false;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sffc_crm_hr_outreach';
        $this->maybe_prepare_table();
    }

    public function get_all($args = []) {
        global $wpdb;

        $defaults = [
            'limit' => 50,
            'offset' => 0,
            'search' => '',
            'program_type' => '',
        ];
        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $params = [];

        if (!empty($args['search'])) {
            $where[] = "(company_name LIKE %s OR company_url LIKE %s OR location LIKE %s OR regions LIKE %s OR industry LIKE %s OR program_types LIKE %s OR process LIKE %s OR skills LIKE %s OR role_focus LIKE %s OR contact_name LIKE %s OR contact_title LIKE %s OR contact_email LIKE %s OR contact_phone LIKE %s OR contact_linkedin LIKE %s OR team_contacts LIKE %s OR notes LIKE %s)";
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            for ($i = 0; $i < 16; $i++) {
                $params[] = $search;
            }
        }

        if (!empty($args['program_type'])) {
            $where[] = "(FIND_IN_SET(%s, program_types))";
            $params[] = $args['program_type'];
        }

        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where) . " ORDER BY CASE WHEN source_context = 'active_recruiter' THEN 1 ELSE 0 END ASC, company_name ASC, contact_name ASC LIMIT %d OFFSET %d";
        $params[] = (int) $args['limit'];
        $params[] = (int) $args['offset'];

        $query = $wpdb->prepare($sql, $params);
        $results = $wpdb->get_results($query, ARRAY_A);

        if (empty($results)) {
            return [];
        }

        return array_map([$this, 'hydrate_record'], $results);
    }

    public function count_all($args = []) {
        global $wpdb;

        $defaults = [
            'search' => '',
            'program_type' => '',
        ];
        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $params = [];

        if (!empty($args['search'])) {
            $where[] = "(company_name LIKE %s OR company_url LIKE %s OR location LIKE %s OR regions LIKE %s OR industry LIKE %s OR program_types LIKE %s OR process LIKE %s OR skills LIKE %s OR role_focus LIKE %s OR contact_name LIKE %s OR contact_title LIKE %s OR contact_email LIKE %s OR contact_phone LIKE %s OR contact_linkedin LIKE %s OR team_contacts LIKE %s OR notes LIKE %s)";
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            for ($i = 0; $i < 16; $i++) {
                $params[] = $search;
            }
        }

        if (!empty($args['program_type'])) {
            $where[] = "(FIND_IN_SET(%s, program_types))";
            $params[] = $args['program_type'];
        }

        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE " . implode(' AND ', $where);

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return (int) $wpdb->get_var($sql);
    }

    public function get($id) {
        global $wpdb;
        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id), ARRAY_A);
        return $record ? $this->hydrate_record($record) : null;
    }

    public function save($data) {
        global $wpdb;

        $clean = [
            'company_name' => sanitize_text_field($data['company_name'] ?? ''),
            'company_logo' => esc_url_raw($data['company_logo'] ?? ''),
            'company_url' => esc_url_raw($data['company_url'] ?? ''),
            'location' => sanitize_text_field($data['location'] ?? ''),
            'regions' => sanitize_text_field($data['regions'] ?? ''),
            'industry' => implode(',', array_map('sanitize_key', $data['industry'] ?? [])),
            'program_types' => implode(',', array_map('sanitize_key', $data['program_types'] ?? [])),
            'process' => implode(',', array_map('sanitize_key', $data['process'] ?? [])),
            'hire_interns' => !empty($data['hire_interns']) ? 1 : 0,
            'hire_graduates' => !empty($data['hire_graduates']) ? 1 : 0,
            'hire_analysts' => !empty($data['hire_analysts']) ? 1 : 0,
            'hire_associates' => !empty($data['hire_associates']) ? 1 : 0,
            'hire_seniors' => !empty($data['hire_seniors']) ? 1 : 0,
            'hire_private_equity_candidates' => !empty($data['hire_private_equity_candidates']) ? 1 : 0,
            'hire_expats' => !empty($data['hire_expats']) ? 1 : 0,
            'hire_cfa_holders' => !empty($data['hire_cfa_holders']) ? 1 : 0,
            'hire_oxbridge' => !empty($data['hire_oxbridge']) ? 1 : 0,
            'hire_russell_group' => !empty($data['hire_russell_group']) ? 1 : 0,
            'hire_non_target' => !empty($data['hire_non_target']) ? 1 : 0,
            'hire_mba' => !empty($data['hire_mba']) ? 1 : 0,
            'hire_visa_sponsorship' => !empty($data['hire_visa_sponsorship']) ? 1 : 0,
            'hire_arabic_speakers' => !empty($data['hire_arabic_speakers']) ? 1 : 0,
            'hire_bilingual' => !empty($data['hire_bilingual']) ? 1 : 0,
            'hire_trainee' => !empty($data['hire_trainee']) ? 1 : 0,
            'hire_placement' => !empty($data['hire_placement']) ? 1 : 0,
            'skills' => implode(',', array_map('sanitize_key', $data['skills'] ?? [])),
            'role_focus' => wp_kses_post($data['role_focus'] ?? ''),
            'last_hire_proof' => wp_kses_post($data['last_hire_proof'] ?? ''),
            'interview_questions_url' => esc_url_raw($data['interview_questions_url'] ?? ''),
            'cv_template_url' => esc_url_raw($data['cv_template_url'] ?? ''),
            'cover_letter_url' => esc_url_raw($data['cover_letter_url'] ?? ''),
            'company_intel_url' => esc_url_raw($data['company_intel_url'] ?? ''),
            'contact_name' => sanitize_text_field($data['contact_name'] ?? ''),
            'contact_title' => sanitize_text_field($data['contact_title'] ?? ''),
            'contact_email' => sanitize_email($data['contact_email'] ?? ''),
            'contact_phone' => sanitize_text_field($data['contact_phone'] ?? ''),
            'contact_linkedin' => esc_url_raw($data['contact_linkedin'] ?? ''),
            'contact_photo' => esc_url_raw($data['contact_photo'] ?? ''),
            'team_contacts' => wp_json_encode($this->sanitize_team_contacts($data['team_contacts'] ?? [])),
            'notes' => wp_kses_post($data['notes'] ?? ''),
            'source_context' => sanitize_key($data['source_context'] ?? 'curated'),
        ];

        if (empty($clean['company_name']) || empty($clean['contact_name'])) {
            return new WP_Error('missing_fields', __('Company and contact name are required.', 'senna-finance'));
        }

        $columns = $wpdb->get_col("DESCRIBE {$this->table}", 0);
        foreach (array_keys($clean) as $field) {
            if (!in_array($field, $columns, true)) {
                unset($clean[$field]);
            }
        }

        if (!empty($data['id'])) {
            $wpdb->update($this->table, $clean, ['id' => (int) $data['id']]);
            return (int) $data['id'];
        }

        $wpdb->insert($this->table, $clean);
        return (int) $wpdb->insert_id;
    }

    public function delete($id) {
        global $wpdb;
        return $wpdb->delete($this->table, ['id' => (int) $id]) !== false;
    }

    private function sanitize_team_contacts($raw_contacts) {
        $clean = [];

        if (empty($raw_contacts) || !is_array($raw_contacts)) {
            return $clean;
        }

        foreach ($raw_contacts as $contact) {
            if (empty($contact['name'])) {
                continue;
            }

            $clean[] = [
                'name' => sanitize_text_field($contact['name'] ?? ''),
                'title' => sanitize_text_field($contact['title'] ?? ''),
                'email' => sanitize_email($contact['email'] ?? ''),
                'phone' => sanitize_text_field($contact['phone'] ?? ''),
                'linkedin' => esc_url_raw($contact['linkedin'] ?? ''),
            ];
        }

        return $clean;
    }

    private function hydrate_record($record) {
        $record['program_types'] = array_filter(array_map('trim', explode(',', $record['program_types'] ?? '')));
        $record['industry'] = array_filter(array_map('trim', explode(',', $record['industry'] ?? '')));
        $record['process'] = array_filter(array_map('trim', explode(',', $record['process'] ?? '')));
        $record['skills'] = array_filter(array_map('trim', explode(',', $record['skills'] ?? '')));
        $record['team_contacts'] = json_decode($record['team_contacts'] ?? '[]', true);
        if (!is_array($record['team_contacts'])) {
            $record['team_contacts'] = [];
        }
        return $record;
    }

    private function maybe_prepare_table() {
        global $wpdb;
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $this->table
        ));

        if (!$table_exists) {
            $schema = SFFC_CRM_Database_Schema::get_instance();
            $schema->create_tables();
        }

        if (self::$columns_ready) {
            return;
        }

        $columns = [
            'hire_associates' => 'tinyint(1) DEFAULT 0',
            'hire_seniors' => 'tinyint(1) DEFAULT 0',
            'hire_private_equity_candidates' => 'tinyint(1) DEFAULT 0',
            'hire_expats' => 'tinyint(1) DEFAULT 0',
            'hire_cfa_holders' => 'tinyint(1) DEFAULT 0',
            'hire_arabic_speakers' => 'tinyint(1) DEFAULT 0',
            'hire_bilingual' => 'tinyint(1) DEFAULT 0',
            'source_context' => "varchar(50) DEFAULT 'curated'",
        ];

        foreach ($columns as $column => $definition) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$this->table} LIKE %s", $column));
            if ($exists !== $column) {
                $wpdb->query("ALTER TABLE {$this->table} ADD COLUMN {$column} {$definition}");
            }
        }

        self::$columns_ready = true;
    }
}
