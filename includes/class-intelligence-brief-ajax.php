<?php
/**
 * Intelligence Brief AJAX Handler
 *
 * Handles AJAX requests for generating and downloading intelligence briefs.
 *
 * @package Skill_Farm_Finance_Career
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Intelligence_Brief_Ajax {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Enrichment service
     */
    private $enrichment;

    /**
     * Brief generator
     */
    private $generator;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX actions
        add_action('wp_ajax_sffc_generate_intelligence_brief', array($this, 'generate_brief'));
        add_action('wp_ajax_nopriv_sffc_generate_intelligence_brief', array($this, 'generate_brief'));

        add_action('wp_ajax_sffc_download_intelligence_brief', array($this, 'download_brief'));
        add_action('wp_ajax_nopriv_sffc_download_intelligence_brief', array($this, 'download_brief'));

        add_action('wp_ajax_sffc_check_brief_status', array($this, 'check_brief_status'));
        add_action('wp_ajax_nopriv_sffc_check_brief_status', array($this, 'check_brief_status'));

        add_action('wp_ajax_sffc_enrich_article', array($this, 'enrich_article'));

        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Lazy load enrichment service
     */
    private function get_enrichment() {
        if (null === $this->enrichment) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-intelligence-enrichment.php';
            $this->enrichment = SFFC_Intelligence_Enrichment::get_instance();
        }
        return $this->enrichment;
    }

    /**
     * Lazy load generator
     */
    private function get_generator() {
        if (null === $this->generator) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-intelligence-brief-generator.php';
            $this->generator = SFFC_Intelligence_Brief_Generator::get_instance();
        }
        return $this->generator;
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        if (!is_singular(array('sffc_pe_news', 'sffc_pe_deal', 'sffc_pe_signal', 'sffc_pe_markets', 'post'))) {
            return;
        }

        wp_enqueue_script(
            'sffc-intelligence-brief',
            plugins_url('assets/js/intelligence-brief.js', dirname(__FILE__)),
            array('jquery'),
            SFFC_VERSION,
            true
        );

        wp_localize_script('sffc-intelligence-brief', 'sffcBrief', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_intelligence_brief'),
            'postId' => get_the_ID(),
            'strings' => array(
                'generating' => __('Generating Intelligence Brief...', 'sffc'),
                'enriching' => __('Gathering data from sources...', 'sffc'),
                'downloading' => __('Preparing download...', 'sffc'),
                'error' => __('An error occurred. Please try again.', 'sffc'),
                'incomplete' => __('Unable to gather enough data for a complete brief.', 'sffc'),
            ),
        ));
    }

    /**
     * Generate intelligence brief (AJAX)
     */
    public function generate_brief() {
        // Verify nonce - return JSON error instead of dying
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'sffc_intelligence_brief')) {
            wp_send_json_error(array(
                'message' => 'Security check failed. Please refresh the page and try again.',
                'code' => 'invalid_nonce'
            ));
        }

        $post_id = intval($_POST['post_id'] ?? 0);

        if (!$post_id) {
            wp_send_json_error(array('message' => 'Invalid post ID'));
        }

        $post = get_post($post_id);

        if (!$post) {
            wp_send_json_error(array('message' => 'Post not found'));
        }

        // Check if we have existing intelligence data
        $intelligence = get_post_meta($post_id, '_sffc_extracted_intelligence', true);
        $enrichment_date = get_post_meta($post_id, '_sffc_enrichment_date', true);

        // Re-enrich if data is older than 24 hours or doesn't exist
        $needs_enrichment = empty($intelligence) ||
            empty($enrichment_date) ||
            strtotime($enrichment_date) < strtotime('-24 hours');

        if ($needs_enrichment) {
            $enrichment = $this->get_enrichment();
            $intelligence = $enrichment->enrich_article($post_id);

            if (is_wp_error($intelligence)) {
                wp_send_json_error(array(
                    'message' => $intelligence->get_error_message(),
                    'code' => $intelligence->get_error_code(),
                ));
            }
        }

        // Check completeness - but DON'T block generation
        // The assembler will fill gaps with fallback content
        $completeness = get_post_meta($post_id, '_sffc_completeness_status', true);

        // Only block if we have absolutely no data at all
        if (empty($intelligence) || (is_array($intelligence) && empty($intelligence['entities']['primary_company']['name']))) {
            wp_send_json_error(array(
                'message' => 'Unable to extract company information from article. Please ensure the article contains clear company names and transaction details.',
                'missing' => $completeness['missing'] ?? array('Company Name'),
                'completeness_score' => $completeness['completeness_score'] ?? 0,
            ));
        }

        // Generate brief data structure
        $generator = $this->get_generator();
        $brief_data = $generator->generate_brief_from_post($post_id);

        if (is_wp_error($brief_data)) {
            wp_send_json_error(array(
                'message' => $brief_data->get_error_message(),
            ));
        }

        // Validate brief data
        $validation = $generator->validate_brief_data($brief_data);

        wp_send_json_success(array(
            'message' => 'Brief ready for download',
            'post_id' => $post_id,
            'is_complete' => $validation['is_complete'],
            'missing' => $validation['missing'],
            'download_url' => add_query_arg(array(
                'action' => 'sffc_download_intelligence_brief',
                'post_id' => $post_id,
                'nonce' => wp_create_nonce('sffc_download_brief_' . $post_id),
            ), admin_url('admin-ajax.php')),
        ));
    }

    /**
     * Download intelligence brief PDF (AJAX)
     */
    public function download_brief() {
        $post_id = intval($_GET['post_id'] ?? 0);
        $nonce = $_GET['nonce'] ?? '';

        if (!wp_verify_nonce($nonce, 'sffc_download_brief_' . $post_id)) {
            wp_die('Security check failed');
        }

        if (!$post_id) {
            wp_die('Invalid post ID');
        }

        $generator = $this->get_generator();
        $brief_data = $generator->generate_brief_from_post($post_id);

        if (is_wp_error($brief_data)) {
            wp_die('Error generating brief: ' . $brief_data->get_error_message());
        }

        // Generate and stream PDF
        $generator->generate_pdf($brief_data, true);
    }

    /**
     * Check brief status (AJAX)
     */
    public function check_brief_status() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'sffc_intelligence_brief')) {
            wp_send_json_error(array('message' => 'Security check failed', 'code' => 'invalid_nonce'));
        }

        $post_id = intval($_POST['post_id'] ?? 0);

        if (!$post_id) {
            wp_send_json_error(array('message' => 'Invalid post ID'));
        }

        $intelligence = get_post_meta($post_id, '_sffc_extracted_intelligence', true);
        $enrichment_date = get_post_meta($post_id, '_sffc_enrichment_date', true);
        $completeness = get_post_meta($post_id, '_sffc_completeness_status', true);

        wp_send_json_success(array(
            'has_intelligence' => !empty($intelligence),
            'enrichment_date' => $enrichment_date,
            'is_complete' => $completeness['is_complete'] ?? false,
            'completeness_score' => $completeness['completeness_score'] ?? 0,
            'missing' => $completeness['missing'] ?? array(),
        ));
    }

    /**
     * Manually trigger article enrichment (AJAX - admin only)
     */
    public function enrich_article() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'sffc_intelligence_brief')) {
            wp_send_json_error(array('message' => 'Security check failed', 'code' => 'invalid_nonce'));
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $post_id = intval($_POST['post_id'] ?? 0);

        if (!$post_id) {
            wp_send_json_error(array('message' => 'Invalid post ID'));
        }

        $enrichment = $this->get_enrichment();
        $intelligence = $enrichment->enrich_article($post_id);

        if (is_wp_error($intelligence)) {
            wp_send_json_error(array(
                'message' => $intelligence->get_error_message(),
                'code' => $intelligence->get_error_code(),
            ));
        }

        $completeness = get_post_meta($post_id, '_sffc_completeness_status', true);

        wp_send_json_success(array(
            'message' => 'Article enriched successfully',
            'is_complete' => $completeness['is_complete'] ?? false,
            'completeness_score' => $completeness['completeness_score'] ?? 0,
            'missing' => $completeness['missing'] ?? array(),
            'intelligence' => $intelligence,
        ));
    }
}

// Initialize
SFFC_Intelligence_Brief_Ajax::get_instance();
