<?php
/**
 * CRM Applicant Model
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Applicant {

    /**
     * Applicants table name
     *
     * @var string
     */
    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sffc_crm_applicants';
    }

    /**
     * Insert a captured applicant
     */
    public function log_applicant($data = []) {
        global $wpdb;

        $defaults = [
            'user_id' => 0,
            'recruiter_post_id' => 0,
            'crm_post_id' => 0,
            'recruiter_id' => 0,
            'job_title' => '',
            'company' => '',
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'materials' => [],
            'source' => 'wp',
            'form_data' => [],
        ];

        $data = wp_parse_args($data, $defaults);

        $materials = is_array($data['materials']) ? array_filter(array_map('sanitize_text_field', $data['materials']), 'strlen') : [];
        $form_data = is_array($data['form_data']) ? $data['form_data'] : [];
        $form_payload = [];
        if (!empty($form_data)) {
            foreach ($form_data as $key => $value) {
                $safe_key = sanitize_key($key);
                if (is_scalar($value)) {
                    $form_payload[$safe_key] = sanitize_text_field($value);
                } elseif (is_array($value)) {
                    $form_payload[$safe_key] = array_map('sanitize_text_field', array_map('strval', $value));
                }
            }
        }

        $insert = $wpdb->insert(
            $this->table,
            [
                'user_id'          => $data['user_id'] ? intval($data['user_id']) : null,
                'recruiter_post_id'=> $data['recruiter_post_id'] ? intval($data['recruiter_post_id']) : null,
                'crm_post_id'      => $data['crm_post_id'] ? intval($data['crm_post_id']) : null,
                'recruiter_id'     => $data['recruiter_id'] ? intval($data['recruiter_id']) : null,
                'job_title'        => sanitize_text_field($data['job_title']),
                'company'          => sanitize_text_field($data['company']),
                'first_name'       => sanitize_text_field($data['first_name']),
                'last_name'        => sanitize_text_field($data['last_name']),
                'email'            => sanitize_email($data['email']),
                'materials'        => wp_json_encode(array_values($materials)),
                'source'           => sanitize_text_field($data['source']),
                'form_data'        => $form_payload ? wp_json_encode($form_payload) : null,
            ],
            ['%d','%d','%d','%d','%s','%s','%s','%s','%s','%s','%s','%s']
        );

        if ($insert === false) {
            return new WP_Error('db_insert_error', $wpdb->last_error);
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Fetch applicants for admin view
     */
    public function get_applicants($args = []) {
        global $wpdb;

        $defaults = [
            'paged' => 1,
            'per_page' => 25,
            'search' => '',
        ];
        $args = wp_parse_args($args, $defaults);

        $paged = max(1, intval($args['paged']));
        $per_page = max(1, intval($args['per_page']));
        $offset = ($paged - 1) * $per_page;

        $where = '1=1';
        $params = [];

        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where .= " AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR job_title LIKE %s OR company LIKE %s)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $params[] = $per_page;
        $params[] = $offset;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $params
        );

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Count applicants for pagination
     */
    public function count_applicants($args = []) {
        global $wpdb;

        $where = '1=1';
        $params = [];

        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where .= " AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR job_title LIKE %s OR company LIKE %s)";
            $params = [$like, $like, $like, $like, $like];
        }

        if (!empty($params)) {
            $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$this->table} WHERE {$where}", $params);
        } else {
            $sql = "SELECT COUNT(*) FROM {$this->table}";
        }

        return (int) $wpdb->get_var($sql);
    }
}
