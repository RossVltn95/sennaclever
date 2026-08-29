<?php
/**
 * CRM Resource Library Model
 *
 * @package SennaCareers
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Resource_Library {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sffc_crm_resource_library';
    }

    public function get_all($args = []) {
        global $wpdb;

        $defaults = [
            'is_active' => null,
            'is_featured' => null,
            'is_case_study' => null,
            'resource_type' => '',
            'category' => '',
            'limit' => null,
        ];
        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $values = [];

        if ($args['is_active'] !== null) {
            $where[] = 'is_active = %d';
            $values[] = !empty($args['is_active']) ? 1 : 0;
        }

        if ($args['is_featured'] !== null) {
            $where[] = 'is_featured = %d';
            $values[] = !empty($args['is_featured']) ? 1 : 0;
        }

        if ($args['is_case_study'] !== null) {
            $where[] = 'is_case_study = %d';
            $values[] = !empty($args['is_case_study']) ? 1 : 0;
        }

        if ($args['resource_type'] !== '') {
            $where[] = 'resource_type = %s';
            $values[] = sanitize_key($args['resource_type']);
        }

        if ($args['category'] !== '') {
            $where[] = 'category = %s';
            $values[] = sanitize_text_field($args['category']);
        }

        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where) . " ORDER BY is_featured DESC, display_order ASC, created_at DESC";

        if ($args['limit']) {
            $sql .= $wpdb->prepare(' LIMIT %d', absint($args['limit']));
        }

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }

    public function get($id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", absint($id)), ARRAY_A);
    }

    public function create($data) {
        global $wpdb;

        $prepared = $this->prepare_data($data);
        $result = $wpdb->insert($this->table, $prepared['data'], $prepared['format']);

        return $result ? $wpdb->insert_id : false;
    }

    public function update($id, $data) {
        global $wpdb;

        $prepared = $this->prepare_data($data, false);
        if (empty($prepared['data'])) {
            return false;
        }

        return $wpdb->update($this->table, $prepared['data'], ['id' => absint($id)], $prepared['format'], ['%d']) !== false;
    }

    public function delete($id) {
        global $wpdb;

        return $wpdb->delete($this->table, ['id' => absint($id)], ['%d']) !== false;
    }

    private function prepare_data($data, $include_required = true) {
        $resource_type = isset($data['resource_type']) ? sanitize_key($data['resource_type']) : null;
        if ($resource_type && !array_key_exists($resource_type, self::get_type_options())) {
            $resource_type = 'link';
        }

        $access_level = isset($data['access_level']) ? sanitize_key($data['access_level']) : null;
        if ($access_level && !array_key_exists($access_level, self::get_access_options())) {
            $access_level = 'free';
        }

        $prepared = [
            'title' => isset($data['title']) ? sanitize_text_field($data['title']) : null,
            'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : null,
            'resource_type' => $resource_type,
            'category' => isset($data['category']) ? sanitize_text_field($data['category']) : null,
            'resource_url' => isset($data['resource_url']) ? esc_url_raw($data['resource_url']) : null,
            'attachment_id' => isset($data['attachment_id']) ? absint($data['attachment_id']) : null,
            'thumbnail_url' => isset($data['thumbnail_url']) ? esc_url_raw($data['thumbnail_url']) : null,
            'access_level' => $access_level,
            'display_order' => isset($data['display_order']) ? intval($data['display_order']) : null,
            'is_featured' => isset($data['is_featured']) ? (int) !empty($data['is_featured']) : null,
            'is_case_study' => isset($data['is_case_study']) ? (int) !empty($data['is_case_study']) : null,
            'is_active' => isset($data['is_active']) ? (int) !empty($data['is_active']) : null,
        ];

        if ($include_required) {
            if (empty($prepared['title'])) {
                $prepared['title'] = __('Resource', 'senna-finance');
            }
            if (empty($prepared['resource_type'])) {
                $prepared['resource_type'] = 'link';
            }
            if (empty($prepared['access_level'])) {
                $prepared['access_level'] = 'free';
            }
            if ($prepared['display_order'] === null) {
                $prepared['display_order'] = 0;
            }
            if ($prepared['is_featured'] === null) {
                $prepared['is_featured'] = 0;
            }
            if ($prepared['is_case_study'] === null) {
                $prepared['is_case_study'] = 0;
            }
            if ($prepared['is_active'] === null) {
                $prepared['is_active'] = 1;
            }
        }

        $data_out = [];
        $format = [];
        foreach ($prepared as $key => $value) {
            if ($value === null && !$include_required) {
                continue;
            }
            $data_out[$key] = $value;
            $format[] = in_array($key, ['attachment_id', 'display_order', 'is_featured', 'is_case_study', 'is_active'], true) ? '%d' : '%s';
        }

        if ($include_required) {
            $data_out['created_at'] = current_time('mysql');
            $data_out['updated_at'] = current_time('mysql');
            $format[] = '%s';
            $format[] = '%s';
        } else {
            $data_out['updated_at'] = current_time('mysql');
            $format[] = '%s';
        }

        return [
            'data' => $data_out,
            'format' => $format,
        ];
    }

    public static function get_type_options() {
        return [
            'video' => __('Video', 'senna-finance'),
            'pdf' => __('PDF', 'senna-finance'),
            'docx' => __('Word', 'senna-finance'),
            'xlsx' => __('Excel', 'senna-finance'),
            'post' => __('Post', 'senna-finance'),
            'link' => __('Link', 'senna-finance'),
        ];
    }

    public static function get_access_options() {
        return [
            'free' => __('Free', 'senna-finance'),
            'member' => __('Member', 'senna-finance'),
            'premium' => __('Premium', 'senna-finance'),
        ];
    }
}
