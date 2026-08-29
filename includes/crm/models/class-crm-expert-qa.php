<?php
/**
 * Expert Q&A Model
 * Stores questions submitted by members and expert answers
 *
 * @package SennaCareers
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Expert_QA {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sffc_crm_expert_qa';
        $this->maybe_prepare_table();
    }

    /**
     * Store a new question from a user
     */
    public function create_question($data) {
        global $wpdb;

        $insert_data = [
            'user_id' => isset($data['user_id']) ? intval($data['user_id']) : null,
            'user_name' => sanitize_text_field($data['user_name'] ?? ''),
            'user_email' => sanitize_email($data['user_email'] ?? ''),
            'question' => wp_kses_post($data['question'] ?? ''),
            'status' => 'pending',
        ];

        if (empty($insert_data['question'])) {
            return false;
        }

        $result = $wpdb->insert(
            $this->table,
            $insert_data,
            ['%d', '%s', '%s', '%s', '%s']
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update question / answer
     */
    public function update($id, $data) {
        global $wpdb;

        $update_data = [];
        $formats = [];

        $fields = ['question', 'answer', 'status', 'answered_by', 'answered_by_name', 'answered_by_title'];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            switch ($field) {
                case 'question':
                case 'answer':
                    $update_data[$field] = wp_kses_post($data[$field]);
                    $formats[] = '%s';
                    break;
                case 'status':
                    $status = in_array($data[$field], ['pending', 'answered'], true) ? $data[$field] : 'pending';
                    $update_data[$field] = $status;
                    $formats[] = '%s';
                    break;
                case 'answered_by':
                    $update_data[$field] = intval($data[$field]);
                    $formats[] = '%d';
                    break;
                case 'answered_by_name':
                case 'answered_by_title':
                    $update_data[$field] = sanitize_text_field($data[$field]);
                    $formats[] = '%s';
                    break;
            }
        }

        if (isset($data['answered_at'])) {
            $update_data['answered_at'] = sanitize_text_field($data['answered_at']);
            $formats[] = '%s';
        }

        if (empty($update_data)) {
            return false;
        }

        return $wpdb->update(
            $this->table,
            $update_data,
            ['id' => intval($id)],
            $formats,
            ['%d']
        ) !== false;
    }

    /**
     * Get single item
     */
    public function get($id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            intval($id)
        ), ARRAY_A);
    }

    /**
     * Get answered questions
     */
    public function get_answered($limit = 10) {
        global $wpdb;

        $limit = max(1, intval($limit));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE status = 'answered'
             ORDER BY answered_at DESC, updated_at DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);
    }

    /**
     * Get items with optional filters
     */
    public function get_all($args = []) {
        global $wpdb;

        $defaults = [
            'status' => null,
            'order' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $values = [];

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = sanitize_key($args['status']);
        }

        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where) . " ORDER BY created_at {$order}";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Delete an entry
     */
    public function delete($id) {
        global $wpdb;

        return $wpdb->delete($this->table, ['id' => intval($id)], ['%d']) !== false;
    }

    private function maybe_prepare_table() {
        global $wpdb;
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $this->table
        ));

        if ($table_exists) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            user_name varchar(200),
            user_email varchar(200),
            question text NOT NULL,
            answer longtext,
            status enum('pending','answered') DEFAULT 'pending',
            answered_by bigint(20) DEFAULT NULL,
            answered_by_name varchar(200),
            answered_by_title varchar(200),
            answered_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_user (user_id)
        ) $charset_collate;";

        dbDelta($sql);
    }
}
