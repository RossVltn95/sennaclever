<?php
/**
 * CRM Company Prep Model
 * Handles company prep materials and requests
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Company_Prep {

    private $table;
    private $materials_table;
    private $requests_table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sffc_crm_company_prep';
        $this->materials_table = $wpdb->prefix . 'sffc_crm_prep_materials';
        $this->requests_table = $wpdb->prefix . 'sffc_crm_prep_requests';
    }

    /**
     * Get all companies
     */
    public function get_all($args = []) {
        global $wpdb;

        $defaults = [
            'active_only' => true,
            'orderby' => 'company_name',
            'order' => 'ASC',
        ];

        $args = array_merge($defaults, $args);

        $where = [];
        if ($args['active_only']) {
            $where[] = 'is_active = 1';
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $order_clause = sprintf('ORDER BY %s %s', $args['orderby'], $args['order']);

        $sql = "SELECT * FROM {$this->table} {$where_clause} {$order_clause}";

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get company by ID
     */
    public function get($id) {
        global $wpdb;

        $company = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ), ARRAY_A);

        if ($company) {
            // Decode JSON fields
            $company['regions_covered'] = json_decode($company['regions_covered'] ?? '[]', true) ?: [];

            // Get materials count
            $company['materials_count'] = $this->get_materials_count($id);
        }

        return $company;
    }

    /**
     * Create company
     */
    public function create($data) {
        global $wpdb;

        // Encode JSON fields
        if (isset($data['regions_covered']) && is_array($data['regions_covered'])) {
            $data['regions_covered'] = json_encode($data['regions_covered']);
        }

        $defaults = [
            'is_active' => 1,
        ];

        $data = array_merge($defaults, $data);

        $wpdb->insert($this->table, $data);

        return $wpdb->insert_id;
    }

    /**
     * Update company
     */
    public function update($id, $data) {
        global $wpdb;

        // Encode JSON fields
        if (isset($data['regions_covered']) && is_array($data['regions_covered'])) {
            $data['regions_covered'] = json_encode($data['regions_covered']);
        }

        return $wpdb->update(
            $this->table,
            $data,
            ['id' => $id]
        );
    }

    /**
     * Delete company
     */
    public function delete($id) {
        global $wpdb;

        // Soft delete
        return $wpdb->update(
            $this->table,
            ['is_active' => 0],
            ['id' => $id]
        );
    }

    /**
     * Get materials for a company
     */
    public function get_materials($company_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->materials_table} WHERE company_id = %d ORDER BY created_at DESC",
            $company_id
        ), ARRAY_A);
    }

    /**
     * Get materials count
     */
    public function get_materials_count($company_id) {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->materials_table} WHERE company_id = %d",
            $company_id
        ));
    }

    /**
     * Add material
     */
    public function add_material($data) {
        global $wpdb;

        $wpdb->insert($this->materials_table, $data);

        return $wpdb->insert_id;
    }

    /**
     * Delete material
     */
    public function delete_material($id) {
        global $wpdb;

        return $wpdb->delete($this->materials_table, ['id' => $id]);
    }

    /**
     * Create prep request
     */
    public function create_request($user_id, $company_id) {
        global $wpdb;

        // Check if request already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->requests_table} WHERE user_id = %d AND company_id = %d",
            $user_id,
            $company_id
        ), ARRAY_A);

        if ($existing) {
            return [
                'success' => false,
                'message' => __('You have already requested materials for this company.', 'senna-finance'),
                'request_id' => $existing['id'],
                'status' => $existing['status'],
            ];
        }

        $wpdb->insert($this->requests_table, [
            'user_id' => $user_id,
            'company_id' => $company_id,
            'status' => 'pending',
        ]);

        return [
            'success' => true,
            'request_id' => $wpdb->insert_id,
            'status' => 'pending',
        ];
    }

    /**
     * Get requests
     */
    public function get_requests($args = []) {
        global $wpdb;

        $defaults = [
            'status' => null,
            'user_id' => null,
            'orderby' => 'requested_at',
            'order' => 'DESC',
        ];

        $args = array_merge($defaults, $args);

        $where = [];
        if ($args['status']) {
            $where[] = $wpdb->prepare('r.status = %s', $args['status']);
        }
        if ($args['user_id']) {
            $where[] = $wpdb->prepare('r.user_id = %d', $args['user_id']);
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $order_clause = sprintf('ORDER BY r.%s %s', $args['orderby'], $args['order']);

        $sql = "SELECT r.*, c.company_name, c.logo_url, c.banner_url,
                       u.display_name as user_name, u.user_email
                FROM {$this->requests_table} r
                LEFT JOIN {$this->table} c ON c.id = r.company_id
                LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
                {$where_clause}
                {$order_clause}";

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Approve request
     */
    public function approve_request($request_id, $admin_id, $send_materials = true) {
        global $wpdb;

        $result = $wpdb->update(
            $this->requests_table,
            [
                'status' => 'approved',
                'approved_by' => $admin_id,
                'materials_sent' => $send_materials ? 1 : 0,
                'responded_at' => current_time('mysql'),
            ],
            ['id' => $request_id]
        );

        if ($result && $send_materials) {
            $this->send_materials_email($request_id);
        }

        return $result;
    }

    /**
     * Reject request
     */
    public function reject_request($request_id, $admin_id, $notes = '') {
        global $wpdb;

        $result = $wpdb->update(
            $this->requests_table,
            [
                'status' => 'rejected',
                'approved_by' => $admin_id,
                'notes' => $notes,
                'responded_at' => current_time('mysql'),
            ],
            ['id' => $request_id]
        );

        if ($result) {
            $this->send_rejection_email($request_id, $notes);
        }

        return $result;
    }

    /**
     * Send materials email to user
     */
    private function send_materials_email($request_id) {
        global $wpdb;

        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, c.company_name, u.user_email, u.display_name
             FROM {$this->requests_table} r
             LEFT JOIN {$this->table} c ON c.id = r.company_id
             LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
             WHERE r.id = %d",
            $request_id
        ), ARRAY_A);

        if (!$request) {
            return false;
        }

        // Get materials
        $materials = $this->get_materials($request['company_id']);

        if (empty($materials)) {
            return false;
        }

        $to = $request['user_email'];
        $subject = sprintf('Prep Materials for %s', $request['company_name']);

        $message = sprintf("Hi %s,\n\n", $request['display_name']);
        $message .= sprintf("Your request for prep materials from %s has been approved!\n\n", $request['company_name']);
        $message .= "Here are the materials:\n\n";

        foreach ($materials as $material) {
            $message .= sprintf("- %s\n  %s\n\n", $material['file_name'], $material['file_url']);
        }

        $message .= "Good luck with your preparation!\n\n";
        $message .= "Best regards,\nMENA Careers Team";

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Send rejection email to user
     */
    private function send_rejection_email($request_id, $notes = '') {
        global $wpdb;

        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, c.company_name, u.user_email, u.display_name
             FROM {$this->requests_table} r
             LEFT JOIN {$this->table} c ON c.id = r.company_id
             LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
             WHERE r.id = %d",
            $request_id
        ), ARRAY_A);

        if (!$request || empty($request['user_email'])) {
            return false;
        }

        $to = $request['user_email'];
        $subject = sprintf(__('Update on %s prep materials', 'senna-finance'), $request['company_name']);

        $message = sprintf("Hi %s,\n\n", $request['display_name']);
        $message .= sprintf(__('Thank you for your interest in %s.', 'senna-finance'), $request['company_name']);
        $message .= "\n" . __('After reviewing the request we are unable to share materials right now.', 'senna-finance') . "\n\n";

        if (!empty($notes)) {
            $message .= __('Reason provided:', 'senna-finance') . ' ' . $notes . "\n\n";
        }

        $message .= __('If you have any questions, simply reply to this email and our team will help.', 'senna-finance') . "\n\n";
        $message .= __('Best regards,', 'senna-finance') . "\n" . __('MENA Careers Team', 'senna-finance');

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Check if user has requested for a company
     */
    public function has_user_requested($user_id, $company_id) {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->requests_table} WHERE user_id = %d AND company_id = %d",
            $user_id,
            $company_id
        ));
    }

    /**
     * Get user request status for a company
     */
    public function get_user_request_status($user_id, $company_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT status, materials_sent FROM {$this->requests_table}
             WHERE user_id = %d AND company_id = %d",
            $user_id,
            $company_id
        ), ARRAY_A);
    }
}
