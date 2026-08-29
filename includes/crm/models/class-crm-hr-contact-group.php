<?php
/**
 * CRM HR Contact Group Model
 * Handles HR contact grouping for curated contact lists.
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_HR_Contact_Group {

    private $table;
    private $relationships_table;
    private $contacts_table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sffc_crm_hr_contact_groups';
        $this->relationships_table = $wpdb->prefix . 'sffc_crm_hr_contact_group_relationships';
        $this->contacts_table = $wpdb->prefix . 'sffc_crm_hr_outreach';
    }

    private function maybe_prepare_tables() {
        global $wpdb;

        $groups_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->table)) === $this->table;
        $relationships_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->relationships_table)) === $this->relationships_table;

        if ($groups_exists && $relationships_exists) {
            return;
        }

        if (!class_exists('SFFC_CRM_Database_Schema') && defined('SFFC_PLUGIN_DIR')) {
            $schema_file = SFFC_PLUGIN_DIR . 'includes/crm/class-crm-database-schema.php';
            if (file_exists($schema_file)) {
                require_once $schema_file;
            }
        }

        if (class_exists('SFFC_CRM_Database_Schema') && method_exists('SFFC_CRM_Database_Schema', 'get_instance')) {
            $schema = SFFC_CRM_Database_Schema::get_instance();
            if (method_exists($schema, 'force_schema_update')) {
                $schema->force_schema_update();
            }
        }
    }

    public function get_all($args = []) {
        global $wpdb;

        $defaults = [
            'is_active' => null,
            'order_by' => 'display_order',
            'order' => 'ASC',
            'include_contact_count' => true,
        ];

        $args = wp_parse_args($args, $defaults);
        $where = ['1=1'];
        $values = [];

        if ($args['is_active'] !== null) {
            $where[] = 'g.is_active = %d';
            $values[] = (int) $args['is_active'];
        }

        $order_by = in_array($args['order_by'], ['display_order', 'name', 'created_at'], true)
            ? $args['order_by']
            : 'display_order';
        $order = strtoupper((string) $args['order']) === 'DESC' ? 'DESC' : 'ASC';
        $where_clause = implode(' AND ', $where);

        if (!empty($args['include_contact_count'])) {
            $query = "SELECT g.*, COUNT(cgr.contact_id) as contact_count
                      FROM {$this->table} g
                      LEFT JOIN {$this->relationships_table} cgr ON g.id = cgr.group_id
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

    public function get_by_id($id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            (int) $id
        ), ARRAY_A);
    }

    public function create($data) {
        global $wpdb;

        if (empty($data['slug']) && !empty($data['name'])) {
            $data['slug'] = $this->generate_unique_slug($data['name']);
        }

        $result = $wpdb->insert(
            $this->table,
            [
                'name' => sanitize_text_field($data['name'] ?? ''),
                'slug' => sanitize_title($data['slug'] ?? ''),
                'description' => isset($data['description']) ? wp_kses_post($data['description']) : '',
                'location' => isset($data['location']) ? sanitize_text_field($data['location']) : '',
                'icon' => isset($data['icon']) ? esc_url_raw($data['icon']) : '',
                'display_order' => isset($data['display_order']) ? (int) $data['display_order'] : 0,
                'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%d']
        );

        return $result ? (int) $wpdb->insert_id : false;
    }

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
            $update_data['display_order'] = (int) $data['display_order'];
            $format[] = '%d';
        }
        if (isset($data['is_active'])) {
            $update_data['is_active'] = (int) $data['is_active'];
            $format[] = '%d';
        }

        if (empty($update_data)) {
            return false;
        }

        return $wpdb->update($this->table, $update_data, ['id' => (int) $id], $format, ['%d']) !== false;
    }

    public function delete($id) {
        global $wpdb;

        $wpdb->delete($this->relationships_table, ['group_id' => (int) $id], ['%d']);
        return $wpdb->delete($this->table, ['id' => (int) $id], ['%d']) !== false;
    }

    public function get_groups_for_contact($contact_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT g.*
             FROM {$this->table} g
             INNER JOIN {$this->relationships_table} cgr ON g.id = cgr.group_id
             WHERE cgr.contact_id = %d
             ORDER BY g.name ASC",
            (int) $contact_id
        ), ARRAY_A);
    }

    public function set_groups_for_contact($contact_id, array $group_ids) {
        global $wpdb;

        $contact_id = (int) $contact_id;
        if ($contact_id <= 0) {
            return false;
        }

        $wpdb->delete($this->relationships_table, ['contact_id' => $contact_id], ['%d']);

        $group_ids = array_values(array_unique(array_filter(array_map('absint', $group_ids))));
        foreach ($group_ids as $group_id) {
            if ($group_id <= 0) {
                continue;
            }
            $wpdb->insert(
                $this->relationships_table,
                [
                    'contact_id' => $contact_id,
                    'group_id' => $group_id,
                ],
                ['%d', '%d']
            );
        }

        return true;
    }

    public function get_contacts_by_group($group_id, $args = []) {
        global $wpdb;

        $defaults = [
            'limit' => null,
            'offset' => 0,
        ];
        $args = wp_parse_args($args, $defaults);

        $query = $wpdb->prepare(
            "SELECT DISTINCT c.*
             FROM {$this->contacts_table} c
             INNER JOIN {$this->relationships_table} cgr ON c.id = cgr.contact_id
             WHERE cgr.group_id = %d
             ORDER BY c.updated_at DESC, c.company_name ASC, c.contact_name ASC",
            (int) $group_id
        );

        if (!empty($args['limit'])) {
            $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", (int) $args['limit'], (int) $args['offset']);
        }

        return $wpdb->get_results($query, ARRAY_A);
    }

    public function count_contacts_in_group($group_id) {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT c.id)
             FROM {$this->contacts_table} c
             INNER JOIN {$this->relationships_table} cgr ON c.id = cgr.contact_id
             WHERE cgr.group_id = %d",
            (int) $group_id
        ));
    }

    private function generate_unique_slug($name) {
        global $wpdb;

        $slug = sanitize_title($name);
        $original_slug = $slug;
        $counter = 1;

        while ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table} WHERE slug = %s", $slug))) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
