<?php
/**
 * CRM Template Model
 * Handles message templates for outreach
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Template {

    private $table;
    private $outreach_table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sffc_crm_templates';
        $this->outreach_table = $wpdb->prefix . 'sffc_crm_outreach';
    }

    /**
     * Get a template by ID
     */
    public function get($id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ), ARRAY_A);
    }

    /**
     * Get all templates for a user (including system templates)
     */
    public function get_user_templates($user_id, $args = []) {
        global $wpdb;

        $defaults = [
            'channel' => null,
            'template_type' => null,
            'include_system' => true,
            'active_only' => true,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = [];
        $params = [];

        // User templates or system templates
        if ($args['include_system']) {
            $where[] = "(user_id = %d OR is_system = 1)";
            $params[] = $user_id;
        } else {
            $where[] = "user_id = %d";
            $params[] = $user_id;
        }

        // Channel filter
        if ($args['channel']) {
            $where[] = "(channel = %s OR channel = 'both')";
            $params[] = $args['channel'];
        }

        // Type filter
        if ($args['template_type']) {
            $where[] = "template_type = %s";
            $params[] = $args['template_type'];
        }

        // Active filter
        if ($args['active_only']) {
            $where[] = "is_active = 1";
        }

        $where_clause = implode(' AND ', $where);

        $sql = "SELECT * FROM {$this->table}
                WHERE $where_clause
                ORDER BY is_system DESC, usage_count DESC, name ASC";

        return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
    }

    /**
     * Get system templates only
     */
    public function get_system_templates($channel = null, $type = null) {
        global $wpdb;

        $where = ["is_system = 1", "is_active = 1"];
        $params = [];

        if ($channel) {
            $where[] = "(channel = %s OR channel = 'both')";
            $params[] = $channel;
        }

        if ($type) {
            $where[] = "template_type = %s";
            $params[] = $type;
        }

        $where_clause = implode(' AND ', $where);

        $sql = "SELECT * FROM {$this->table} WHERE $where_clause ORDER BY name ASC";

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Create a new template
     */
    public function create($user_id, $data) {
        global $wpdb;

        // Extract variables from content
        $variables = $this->extract_variables($data['content']);
        if (!empty($data['subject'])) {
            $variables = array_unique(array_merge($variables, $this->extract_variables($data['subject'])));
        }

        $result = $wpdb->insert($this->table, [
            'user_id' => $user_id,
            'name' => sanitize_text_field($data['name']),
            'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
            'channel' => in_array($data['channel'], ['email', 'linkedin', 'both']) ? $data['channel'] : 'email',
            'template_type' => in_array($data['template_type'], ['initial', 'followup', 'connection', 'thank_you', 'custom']) ? $data['template_type'] : 'custom',
            'subject' => isset($data['subject']) ? sanitize_text_field($data['subject']) : '',
            'content' => wp_kses_post($data['content']),
            'variables_used' => json_encode($variables),
            'is_system' => 0,
            'is_active' => 1,
        ]);

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Update a template
     */
    public function update($template_id, $user_id, $data) {
        global $wpdb;

        // Verify ownership (can't edit system templates)
        $template = $this->get($template_id);
        if (!$template || ($template['is_system'] && $template['user_id'] != $user_id)) {
            return false;
        }

        $update_data = [];

        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }

        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
        }

        if (isset($data['channel'])) {
            $update_data['channel'] = in_array($data['channel'], ['email', 'linkedin', 'both']) ? $data['channel'] : 'email';
        }

        if (isset($data['template_type'])) {
            $update_data['template_type'] = in_array($data['template_type'], ['initial', 'followup', 'connection', 'thank_you', 'custom']) ? $data['template_type'] : 'custom';
        }

        if (isset($data['subject'])) {
            $update_data['subject'] = sanitize_text_field($data['subject']);
        }

        if (isset($data['content'])) {
            $update_data['content'] = wp_kses_post($data['content']);

            // Re-extract variables
            $variables = $this->extract_variables($data['content']);
            if (!empty($data['subject'])) {
                $variables = array_unique(array_merge($variables, $this->extract_variables($data['subject'])));
            }
            $update_data['variables_used'] = json_encode($variables);
        }

        if (isset($data['is_active'])) {
            $update_data['is_active'] = $data['is_active'] ? 1 : 0;
        }

        if (empty($update_data)) {
            return true;
        }

        return $wpdb->update(
            $this->table,
            $update_data,
            ['id' => $template_id, 'user_id' => $user_id]
        ) !== false;
    }

    /**
     * Delete a template
     */
    public function delete($template_id, $user_id) {
        global $wpdb;

        // Can't delete system templates
        $template = $this->get($template_id);
        if (!$template || $template['is_system']) {
            return false;
        }

        return $wpdb->delete($this->table, [
            'id' => $template_id,
            'user_id' => $user_id
        ]) !== false;
    }

    /**
     * Duplicate a template (including system templates)
     */
    public function duplicate($template_id, $user_id) {
        global $wpdb;

        $template = $this->get($template_id);
        if (!$template) {
            return false;
        }

        $new_data = [
            'name' => $template['name'] . ' (Copy)',
            'description' => $template['description'],
            'channel' => $template['channel'],
            'template_type' => $template['template_type'],
            'subject' => $template['subject'],
            'content' => $template['content'],
        ];

        return $this->create($user_id, $new_data);
    }

    /**
     * Increment usage count
     */
    public function increment_usage($template_id) {
        global $wpdb;

        return $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table} SET usage_count = usage_count + 1 WHERE id = %d",
            $template_id
        ));
    }

    /**
     * Update reply rate for template
     */
    public function update_reply_rate($template_id) {
        global $wpdb;

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'replied' THEN 1 END) as replied
             FROM {$this->outreach_table}
             WHERE template_id = %d AND status NOT IN ('draft', 'scheduled')",
            $template_id
        ), ARRAY_A);

        if ($stats && $stats['total'] > 0) {
            $reply_rate = ($stats['replied'] / $stats['total']) * 100;

            $wpdb->update(
                $this->table,
                ['reply_rate' => $reply_rate],
                ['id' => $template_id]
            );
        }
    }

    /**
     * Extract variable placeholders from content
     */
    public function extract_variables($content) {
        preg_match_all('/\{([a-z_]+)\}/', $content, $matches);
        return array_unique($matches[1] ?? []);
    }

    /**
     * Get available variables with descriptions
     */
    public function get_available_variables() {
        return [
            'recruiter_name' => 'Full name of the recruiter',
            'recruiter_first_name' => 'First name of the recruiter',
            'recruiter_firm' => 'Recruiter\'s company/firm',
            'recruiter_title' => 'Recruiter\'s job title',
            'role_title' => 'Job role being discussed',
            'company' => 'Company hiring for the role',
            'location' => 'Location of the role',
            'salary_range' => 'Salary range if available',
            'post_snippet' => 'Excerpt from original post',
            'candidate_name' => 'Your full name',
            'candidate_first_name' => 'Your first name',
            'candidate_current_role' => 'Your current job title',
            'candidate_company' => 'Your current company',
            'candidate_experience' => 'Your experience summary',
            'candidate_years' => 'Your years of experience',
            'candidate_email' => 'Your email address',
            'candidate_phone' => 'Your phone number',
            'candidate_linkedin' => 'Your LinkedIn URL',
        ];
    }

    /**
     * Render template with variables
     */
    public function render($template_id, $variables) {
        $template = $this->get($template_id);
        if (!$template) {
            return null;
        }

        $subject = $template['subject'];
        $content = $template['content'];

        foreach ($variables as $key => $value) {
            $placeholder = '{' . $key . '}';
            $subject = str_replace($placeholder, $value, $subject);
            $content = str_replace($placeholder, $value, $content);
        }

        return [
            'subject' => $subject,
            'content' => $content,
            'channel' => $template['channel'],
            'template_id' => $template_id,
        ];
    }

    /**
     * Render content with variables (without template)
     */
    public function render_content($content, $variables, $subject = '') {
        foreach ($variables as $key => $value) {
            $placeholder = '{' . $key . '}';
            $content = str_replace($placeholder, $value, $content);
            if ($subject) {
                $subject = str_replace($placeholder, $value, $subject);
            }
        }

        return [
            'subject' => $subject,
            'content' => $content,
        ];
    }

    /**
     * Get template performance stats
     */
    public function get_template_stats($template_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_sent,
                COUNT(CASE WHEN status = 'opened' OR status = 'clicked' OR status = 'replied' THEN 1 END) as opened,
                COUNT(CASE WHEN status = 'clicked' OR status = 'replied' THEN 1 END) as clicked,
                COUNT(CASE WHEN status = 'replied' THEN 1 END) as replied,
                AVG(CASE WHEN replied_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, sent_at, replied_at) END) as avg_reply_hours
             FROM {$this->outreach_table}
             WHERE template_id = %d AND status NOT IN ('draft', 'scheduled', 'failed', 'bounced')",
            $template_id
        ), ARRAY_A);
    }
}
