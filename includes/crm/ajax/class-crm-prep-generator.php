<?php
/**
 * CRM Prep Materials Generator - AJAX Handler
 * Handles cover letter and interview questions generation via Claude
 *
 * @package SennaCareers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Prep_Generator {

    /**
     * Initialize hooks
     */
    public function __construct() {
        add_action('wp_ajax_sffc_generate_cover_letter', array($this, 'generate_cover_letter'));
        add_action('wp_ajax_sffc_generate_interview_questions', array($this, 'generate_interview_questions'));
    }

    /**
     * Generate cover letter via AJAX
     */
    public function generate_cover_letter() {
        // Verify nonce
        if (!check_ajax_referer('sffc_crm_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => 'Invalid security token'
            ));
            return;
        }

        // Check user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => 'You must be logged in to generate materials.'
            ));
            return;
        }

        // Get post ID
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(array(
                'message' => 'Invalid post ID.'
            ));
            return;
        }

        // Get post data
        $post_data = $this->get_post_data($post_id);
        if (!$post_data) {
            wp_send_json_error(array(
                'message' => 'Post not found.'
            ));
            return;
        }

        // Build prompt
        require_once dirname(__FILE__) . '/../class-crm-prep-prompt-builder.php';
        $prompt_builder = new SFFC_CRM_Prep_Prompt_Builder();
        $prompt = $prompt_builder->build_cover_letter_prompt($post_data);

        // Call Claude API
        $claude = SFFC_Claude_API_Manager::get_instance();
        if (!$claude) {
            wp_send_json_error(array(
                'message' => 'Claude API is not available. Please check your configuration.'
            ));
            return;
        }

        $response = $claude->send_message(
            'Generate a cover letter based on the following job posting.',
            array('system_prompt' => $prompt),
            'prep_materials'
        );

        if (empty($response)) {
            wp_send_json_error(array(
                'message' => 'Failed to generate cover letter. The API returned an empty response.'
            ));
            return;
        }

        // Clean up response (remove any markdown formatting if present)
        $html = $this->clean_response($response);

        // Save to database
        $this->save_cover_letter($post_id, $html);

        // Return success
        wp_send_json_success(array(
            'html' => $html,
            'content' => $html
        ));
    }

    /**
     * Generate interview questions via AJAX
     */
    public function generate_interview_questions() {
        // Verify nonce
        if (!check_ajax_referer('sffc_crm_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => 'Invalid security token'
            ));
            return;
        }

        // Check user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => 'You must be logged in to generate materials.'
            ));
            return;
        }

        // Get post ID
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(array(
                'message' => 'Invalid post ID.'
            ));
            return;
        }

        // Get post data
        $post_data = $this->get_post_data($post_id);
        if (!$post_data) {
            wp_send_json_error(array(
                'message' => 'Post not found.'
            ));
            return;
        }

        // Build prompt
        require_once dirname(__FILE__) . '/../class-crm-prep-prompt-builder.php';
        $prompt_builder = new SFFC_CRM_Prep_Prompt_Builder();
        $prompt = $prompt_builder->build_interview_questions_prompt($post_data);

        // Call Claude API
        $claude = SFFC_Claude_API_Manager::get_instance();
        if (!$claude) {
            wp_send_json_error(array(
                'message' => 'Claude API is not available. Please check your configuration.'
            ));
            return;
        }

        $response = $claude->send_message(
            'Generate interview questions based on the following job posting.',
            array('system_prompt' => $prompt),
            'prep_materials'
        );

        if (empty($response)) {
            wp_send_json_error(array(
                'message' => 'Failed to generate interview questions. The API returned an empty response.'
            ));
            return;
        }

        // Clean up response
        $html = $this->clean_response($response);

        // Save to database
        $this->save_interview_questions($post_id, $html);

        // Return success
        wp_send_json_success(array(
            'html' => $html,
            'content' => $html
        ));
    }


    /**
     * Get post data from database
     *
     * @param int $post_id
     * @return array|false
     */
    private function get_post_data($post_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_crm_posts';

        $post = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, role_title, company, sector, seniority, content, content_snippet, keywords, location
                FROM {$table}
                WHERE id = %d",
                $post_id
            ),
            ARRAY_A
        );

        return $post ? $post : false;
    }

    /**
     * Clean Claude response
     * Remove markdown code blocks, ensure proper HTML
     *
     * @param string $response
     * @return string
     */
    private function clean_response($response) {
        // Remove markdown code blocks
        $response = preg_replace('/```html\s*/', '', $response);
        $response = preg_replace('/```\s*$/', '', $response);
        $response = trim($response);

        // Ensure we have HTML paragraphs
        if (strpos($response, '<p>') === false && strpos($response, '<div') === false) {
            // Convert plain text to HTML paragraphs
            $paragraphs = explode("\n\n", $response);
            $html = '';
            foreach ($paragraphs as $para) {
                $para = trim($para);
                if (!empty($para)) {
                    $html .= '<p>' . nl2br(esc_html($para)) . '</p>';
                }
            }
            $response = $html;
        }

        return $response;
    }


    /**
     * Save cover letter to database
     *
     * @param int $post_id
     * @param string $html
     */
    private function save_cover_letter($post_id, $html) {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_crm_posts';

        $wpdb->update(
            $table,
            array('cover_letter_html' => $html),
            array('id' => $post_id),
            array('%s'),
            array('%d')
        );
    }

    /**
     * Save interview questions to database
     *
     * @param int $post_id
     * @param string $html
     */
    private function save_interview_questions($post_id, $html) {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_crm_posts';

        $wpdb->update(
            $table,
            array('interview_questions' => $html),
            array('id' => $post_id),
            array('%s'),
            array('%d')
        );
    }
}

// Initialize
new SFFC_CRM_Prep_Generator();
