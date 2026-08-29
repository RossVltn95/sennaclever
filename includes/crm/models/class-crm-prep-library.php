<?php
/**
 * CRM Prep Library Model
 * Stores reusable prep material resources for the CRM
 *
 * @package SennaCareers
 * @since 7.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Prep_Library {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sffc_crm_prep_library';
    }

    /**
     * Get all prep materials
     */
    public function get_all($args = []) {
        global $wpdb;

        $defaults = [
            'is_active' => null,
            'order' => 'ASC',
            'limit' => null,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $values = [];

        if ($args['is_active'] !== null) {
            $where[] = 'is_active = %d';
            $values[] = $args['is_active'] ? 1 : 0;
        }

        $sql = "SELECT * FROM {$this->table}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY display_order ASC, created_at DESC";

        if ($args['limit']) {
            $sql .= $wpdb->prepare(' LIMIT %d', intval($args['limit']));
        }

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get single material
     */
    public function get($id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ), ARRAY_A);
    }

    /**
     * Create material
     */
    public function create($data) {
        global $wpdb;

        $insert = $this->prepare_data($data);
        $result = $wpdb->insert($this->table, $insert['data'], $insert['format']);

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update material
     */
    public function update($id, $data) {
        global $wpdb;

        $prepared = $this->prepare_data($data, false);

        if (empty($prepared['data'])) {
            return false;
        }

        return $wpdb->update(
            $this->table,
            $prepared['data'],
            ['id' => $id],
            $prepared['format'],
            ['%d']
        ) !== false;
    }

    /**
     * Delete material
     */
    public function delete($id) {
        global $wpdb;

        return $wpdb->delete($this->table, ['id' => $id], ['%d']) !== false;
    }

    private function prepare_data($data, $include_required = true) {
        $prepared = [
            'title' => isset($data['title']) ? sanitize_text_field($data['title']) : null,
            'description' => isset($data['description']) ? wp_kses_post($data['description']) : null,
            'resource_url' => isset($data['resource_url']) ? esc_url_raw($data['resource_url']) : null,
            'attachment_id' => isset($data['attachment_id']) ? intval($data['attachment_id']) : null,
            'material_type' => isset($data['material_type']) ? sanitize_key($data['material_type']) : null,
            'icon_slug' => isset($data['icon_slug']) ? sanitize_key($data['icon_slug']) : null,
            'display_order' => isset($data['display_order']) ? intval($data['display_order']) : 0,
            'is_active' => isset($data['is_active']) ? (int) !empty($data['is_active']) : 1,
        ];

        if ($include_required && empty($prepared['title'])) {
            $prepared['title'] = __('Prep Material', 'senna-finance');
        }

        // Remove nulls for updates
        $data_out = [];
        $format = [];
        foreach ($prepared as $key => $value) {
            if ($value === null && !$include_required) {
                continue;
            }

            $data_out[$key] = $value;
            $format[] = in_array($key, ['display_order', 'is_active', 'attachment_id'], true) ? '%d' : '%s';
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

    /**
     * Icon options
     */
    public static function get_icon_options() {
        return [
            'document' => [
                'label' => __('Document', 'senna-finance'),
                'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 2h8l5 5v13a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><path d="M14 2v6h6"/></svg>'
            ],
            'spark' => [
                'label' => __('Strategy', 'senna-finance'),
                'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m12 2-2 7h7l-8 13 2-8H4l8-12z"/></svg>'
            ],
            'playbook' => [
                'label' => __('Playbook', 'senna-finance'),
                'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 4v16"/><path d="m11 8 2.5 2.5L11 13"/><path d="m17 8-2.5 2.5L17 13"/></svg>'
            ],
            'lightbulb' => [
                'label' => __('Insight', 'senna-finance'),
                'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M9 18a7 7 0 1 1 6 0"/></svg>'
            ],
            'rocket' => [
                'label' => __('Fast Track', 'senna-finance'),
                'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 13s-1 4 2 7c0 0 3 0 7-4s8-9 4-13c-4-4-9 0-13 4S0 11 0 11z"/><path d="M6.5 7.5l2 2"/></svg>'
            ],
        ];
    }

    public static function get_icon_svg($slug) {
        $icons = self::get_icon_options();
        return $icons[$slug]['svg'] ?? $icons['document']['svg'];
    }
}
