<?php
/**
 * Application Pack Generator
 *
 * Main orchestrator for generating Application Pack documents.
 * Handles CV, Cover Letter, Networking Messages, Interview Prep, etc.
 *
 * @package SFFC_Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include dependencies
require_once dirname(__FILE__) . '/class-application-pack-design-system.php';
require_once dirname(__FILE__) . '/class-application-pack-pdf-generator.php';
require_once dirname(__FILE__) . '/class-application-pack-docx-generator.php';
require_once dirname(__FILE__) . '/class-application-pack-tiers.php';
require_once dirname(__FILE__) . '/class-application-pack-toolkit.php';
require_once dirname(__FILE__) . '/class-application-pack-admin.php';
require_once dirname(__FILE__) . '/class-application-pack-claude-prompts.php';

class SFFC_Application_Pack_Generator {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Database table name
     */
    private $table_packs;

    /**
     * Pack types and their configurations
     */
    private $pack_types = array(
        'cv' => array(
            'name' => 'Tailored CV',
            'description' => 'Consulting-grade CV tailored to the specific role',
            'formats' => array('pdf', 'docx'),
            'credits' => 1,
        ),
        'cover_letter' => array(
            'name' => 'Cover Letter',
            'description' => 'Professionally crafted cover letter',
            'formats' => array('pdf', 'docx'),
            'credits' => 1,
        ),
        'networking' => array(
            'name' => 'Networking Messages',
            'description' => 'LinkedIn and email outreach templates',
            'formats' => array('text'),
            'credits' => 0,
        ),
        'interview_prep' => array(
            'name' => 'Interview Prep Sheet',
            'description' => 'Comprehensive interview preparation brief',
            'formats' => array('pdf'),
            'credits' => 1,
        ),
        'company_brief' => array(
            'name' => 'Company Intel Brief',
            'description' => 'Company research and intelligence summary',
            'formats' => array('pdf'),
            'credits' => 1,
        ),
        'full_pack' => array(
            'name' => 'Full Application Pack',
            'description' => 'Complete package with all documents',
            'formats' => array('zip'),
            'credits' => 3,
        ),
    );

    /**
     * MemberPress product IDs for Application Pack access
     * Set via WordPress options: sffc_app_pack_product_ids
     */
    private $mepr_product_ids = array();

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
        global $wpdb;
        $this->table_packs = $wpdb->prefix . 'sffc_application_packs';

        $this->init();
    }

    /**
     * Initialize hooks and handlers
     */
    private function init() {
        // AJAX handlers
        add_action('wp_ajax_sffc_generate_app_pack', array($this, 'ajax_generate_pack'));
        add_action('wp_ajax_sffc_preview_app_pack', array($this, 'ajax_preview_pack'));
        add_action('wp_ajax_sffc_download_app_pack', array($this, 'ajax_download_pack'));
        add_action('wp_ajax_sffc_get_pack_credits', array($this, 'ajax_get_credits'));
        add_action('wp_ajax_sffc_check_pack_access', array($this, 'ajax_check_access'));

        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Create database table for tracking pack generation
     */
    public function create_database_table() {
        global $wpdb;

        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_packs}'") === $this->table_packs;

        if (!$table_exists) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE IF NOT EXISTS {$this->table_packs} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                job_id BIGINT UNSIGNED NOT NULL,
                pack_type VARCHAR(50) NOT NULL,
                generated_content LONGTEXT,
                file_path VARCHAR(500),
                download_count INT DEFAULT 0,
                credits_used INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_job (job_id),
                INDEX idx_type (pack_type),
                INDEX idx_created (created_at)
            ) $charset_collate;";

            dbDelta($sql);
        }
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        // Don't enqueue if Application Pack is disabled
        if (!$this->is_enabled()) {
            return;
        }

        // Only enqueue on pages that actually need the Application Pack
        // Check if we're on a single job post or a page with relevant shortcodes
        $should_enqueue = false;

        // Check for single job posts
        if (is_singular('sffc_job')) {
            $should_enqueue = true;
        }

        // Check for pages/posts containing Application Pack shortcodes
        if (!$should_enqueue) {
            global $post;
            if ($post && is_a($post, 'WP_Post')) {
                $content = $post->post_content;
                if (
                    has_shortcode($content, 'sffc_recruiter_post_article') ||
                    has_shortcode($content, 'sffc_crm_post_article') ||
                    has_shortcode($content, 'sffc_job_cards') ||
                    has_shortcode($content, 'sffc_application_pack')
                ) {
                    $should_enqueue = true;
                }
            }
        }

        // Don't load the modal JS on pages that don't need it
        if (!$should_enqueue) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'sffc-application-pack',
            plugins_url('assets/css/application-pack.css', dirname(dirname(__FILE__))),
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'sffc-application-pack',
            plugins_url('assets/js/application-pack.js', dirname(dirname(__FILE__))),
            array('jquery'),
            '1.0.0',
            true
        );

        $is_restricted = SFFC_Application_Pack_Admin::is_restricted();
        $credit_system = SFFC_Application_Pack_Admin::is_credit_system_enabled();

        wp_localize_script('sffc-application-pack', 'sffcAppPack', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_app_pack_nonce'),
            'packTypes' => $this->pack_types,
            'isEnabled' => true,
            'isRestricted' => $is_restricted,
            'hasAccess' => $this->user_has_access(),
            'creditSystemEnabled' => $credit_system,
            'credits' => $this->get_user_credits(),
            'upgradeUrl' => $this->get_upgrade_url(),
            'isLoggedIn' => is_user_logged_in(),
            'loginUrl' => wp_login_url(get_permalink()),
        ));
    }

    /**
     * Check if MemberPress is available
     */
    public function is_memberpress_active() {
        return class_exists('MeprUser') && class_exists('MeprProduct');
    }

    /**
     * Check if Application Pack feature is enabled
     */
    public function is_enabled() {
        return SFFC_Application_Pack_Admin::is_enabled();
    }

    /**
     * Check if user has access to Application Pack
     * Uses admin settings to determine if restrictions are enabled
     */
    public function user_has_access($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false; // Must be logged in
        }

        // Check if restrictions are enabled in admin settings
        if (!SFFC_Application_Pack_Admin::is_restricted()) {
            return true; // No restrictions enabled - all logged-in users have access
        }

        // MemberPress check
        if (!$this->is_memberpress_active()) {
            return true; // No MemberPress = no restrictions possible
        }

        $allowed_products = SFFC_Application_Pack_Admin::get_allowed_products();

        if (empty($allowed_products)) {
            return true; // No products configured = no restrictions
        }

        $mepr_user = new MeprUser($user_id);
        $active_products = $mepr_user->active_product_subscriptions();

        foreach ($active_products as $product_id) {
            if (in_array($product_id, $allowed_products)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get user's Application Pack credits
     */
    public function get_user_credits($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return 0;
        }

        // Check if credit system is enabled
        if (!SFFC_Application_Pack_Admin::is_credit_system_enabled()) {
            return 999; // Unlimited when credit system is disabled
        }

        // Get user's current credits
        $credits = get_user_meta($user_id, 'sffc_app_pack_credits', true);

        // If no credits set, initialize with monthly allowance
        if ($credits === '' || $credits === false) {
            $monthly_credits = SFFC_Application_Pack_Admin::get_monthly_credits();
            update_user_meta($user_id, 'sffc_app_pack_credits', $monthly_credits);
            update_user_meta($user_id, 'sffc_app_pack_credits_reset', date('Y-m'));
            return $monthly_credits;
        }

        // Check for monthly reset
        $last_reset = get_user_meta($user_id, 'sffc_app_pack_credits_reset', true);
        $current_month = date('Y-m');

        if ($last_reset !== $current_month) {
            $monthly_credits = SFFC_Application_Pack_Admin::get_monthly_credits();
            update_user_meta($user_id, 'sffc_app_pack_credits', $monthly_credits);
            update_user_meta($user_id, 'sffc_app_pack_credits_reset', $current_month);
            return $monthly_credits;
        }

        return intval($credits);
    }

    /**
     * Deduct credits from user
     */
    public function deduct_credits($user_id, $amount) {
        // If credit system is disabled, always succeed
        if (!SFFC_Application_Pack_Admin::is_credit_system_enabled()) {
            return true;
        }

        $current = $this->get_user_credits($user_id);
        if ($current < $amount) {
            return false;
        }

        update_user_meta($user_id, 'sffc_app_pack_credits', $current - $amount);
        return true;
    }

    /**
     * Get MemberPress upgrade URL
     */
    public function get_upgrade_url() {
        return SFFC_Application_Pack_Admin::get_upgrade_url();
    }

    /**
     * Get user tier from MemberPress
     */
    public function get_user_tier($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$this->is_memberpress_active() || !$user_id) {
            return 'free';
        }

        $mepr_user = new MeprUser($user_id);
        $active_products = $mepr_user->active_product_subscriptions();

        // Check against configured product tiers
        $executive_products = get_option('sffc_executive_product_ids', array());
        $professional_products = get_option('sffc_professional_product_ids', array());

        foreach ($active_products as $product_id) {
            if (in_array($product_id, (array)$executive_products)) {
                return 'executive';
            }
            if (in_array($product_id, (array)$professional_products)) {
                return 'professional';
            }
        }

        return 'free';
    }

    /**
     * Get job data by ID
     */
    public function get_job_data($job_id) {
        $job = get_post($job_id);

        if (!$job || $job->post_type !== 'sffc_job') {
            return null;
        }

        $meta = get_post_meta($job_id);

        return array(
            'id' => $job_id,
            'title' => $job->post_title,
            'company' => isset($meta['_company_name'][0]) ? $meta['_company_name'][0] : '',
            'location' => isset($meta['_location'][0]) ? $meta['_location'][0] : '',
            'description' => $job->post_content,
            'requirements' => isset($meta['_requirements'][0]) ? $meta['_requirements'][0] : '',
            'skills' => isset($meta['_skills_required'][0]) ? maybe_unserialize($meta['_skills_required'][0]) : array(),
            'salary_min' => isset($meta['_salary_min'][0]) ? $meta['_salary_min'][0] : '',
            'salary_max' => isset($meta['_salary_max'][0]) ? $meta['_salary_max'][0] : '',
            'experience_level' => isset($meta['_experience_level'][0]) ? $meta['_experience_level'][0] : '',
            'employment_type' => isset($meta['_employment_type'][0]) ? $meta['_employment_type'][0] : '',
            'industry' => isset($meta['_industry'][0]) ? $meta['_industry'][0] : '',
            'department' => isset($meta['_department'][0]) ? $meta['_department'][0] : '',
        );
    }

    /**
     * Get user profile data
     */
    public function get_user_profile($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return null;
        }

        // Get professional profile
        $profile = get_user_meta($user_id, 'sffc_professional_profile', true);
        if (!is_array($profile)) {
            $profile = array();
        }

        // Get career journey
        $career_journey = get_user_meta($user_id, 'senna_intake_data', true);
        if (!is_array($career_journey)) {
            $career_journey = array();
        }

        // Get CV data if available
        $cv_data = $this->get_user_cv_data($user_id);

        return array(
            'user_id' => $user_id,
            'name' => $user->display_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->user_email,
            'profile' => $profile,
            'career_journey' => $career_journey,
            'cv_data' => $cv_data,
            'headline' => isset($profile['headline']) ? $profile['headline'] : '',
            'summary' => isset($profile['summary']) ? $profile['summary'] : '',
            'experience' => isset($profile['experience']) ? $profile['experience'] : array(),
            'education' => isset($profile['education']) ? $profile['education'] : array(),
            'skills' => isset($profile['skills']) ? $profile['skills'] : array(),
        );
    }

    /**
     * Get user's uploaded CV data
     */
    private function get_user_cv_data($user_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_ultimate_cv_uploads';

        $cv = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND status = 'active' ORDER BY upload_date DESC LIMIT 1",
            $user_id
        ), ARRAY_A);

        return $cv;
    }

    /**
     * AJAX: Generate Application Pack
     */
    public function ajax_generate_pack() {
        check_ajax_referer('sffc_app_pack_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please log in to generate an Application Pack.'));
        }

        $user_id = get_current_user_id();
        $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
        $pack_type = isset($_POST['pack_type']) ? sanitize_text_field($_POST['pack_type']) : 'cv';

        if (!$job_id) {
            wp_send_json_error(array('message' => 'Invalid job ID.'));
        }

        if (!isset($this->pack_types[$pack_type])) {
            wp_send_json_error(array('message' => 'Invalid pack type.'));
        }

        // Check access
        if (!$this->user_has_access($user_id)) {
            wp_send_json_error(array(
                'message' => 'Please upgrade to access Application Pack.',
                'upgrade_url' => $this->get_upgrade_url(),
            ));
        }

        // Check credits
        $credits_needed = $this->pack_types[$pack_type]['credits'];
        if ($this->get_user_credits($user_id) < $credits_needed) {
            wp_send_json_error(array(
                'message' => 'Insufficient credits.',
                'credits_needed' => $credits_needed,
                'upgrade_url' => $this->get_upgrade_url(),
            ));
        }

        // Get job and user data
        $job_data = $this->get_job_data($job_id);
        if (!$job_data) {
            wp_send_json_error(array('message' => 'Job not found.'));
        }

        $user_profile = $this->get_user_profile($user_id);

        // Generate the pack
        try {
            $result = $this->generate_pack($pack_type, $job_data, $user_profile);

            // Deduct credits
            $this->deduct_credits($user_id, $credits_needed);

            // Log the generation
            $this->log_pack_generation($user_id, $job_id, $pack_type, $result);

            wp_send_json_success(array(
                'message' => 'Application Pack generated successfully.',
                'pack_type' => $pack_type,
                'content' => $result['content'],
                'preview_html' => $result['preview_html'],
                'download_url' => $result['download_url'],
            ));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Generation failed: ' . $e->getMessage()));
        }
    }

    /**
     * Generate a specific pack type
     */
    public function generate_pack($pack_type, $job_data, $user_profile) {
        switch ($pack_type) {
            case 'cv':
                return $this->generate_cv($job_data, $user_profile);
            case 'cover_letter':
                return $this->generate_cover_letter($job_data, $user_profile);
            case 'networking':
                return $this->generate_networking_messages($job_data, $user_profile);
            case 'interview_prep':
                return $this->generate_interview_prep($job_data, $user_profile);
            case 'company_brief':
                return $this->generate_company_brief($job_data);
            case 'full_pack':
                return $this->generate_full_pack($job_data, $user_profile);
            default:
                throw new Exception('Unknown pack type: ' . $pack_type);
        }
    }

    /**
     * Generate Tailored CV
     */
    private function generate_cv($job_data, $user_profile) {
        // Use existing CV tailoring system if available
        if (class_exists('SFFC_Ultimate_CV_Tailoring')) {
            $cv_tailor = SFFC_Ultimate_CV_Tailoring::get_instance();
            // Delegate to existing system but wrap result
        }

        // Build CV content structure
        $cv_content = $this->build_cv_content($job_data, $user_profile);

        // Generate preview HTML
        $preview_html = $this->render_cv_preview($cv_content, $job_data);

        return array(
            'content' => $cv_content,
            'preview_html' => $preview_html,
            'download_url' => '', // Will be set after file generation
        );
    }

    /**
     * Build CV content structure
     */
    private function build_cv_content($job_data, $user_profile) {
        // Get the enhanced prompts manager for high-quality content
        $prompts = SFFC_Application_Pack_Claude_Prompts::get_instance();

        // Get Claude API for backward compatibility
        $claude = null;
        if (class_exists('SFFC_Claude_API_Manager')) {
            $claude = SFFC_Claude_API_Manager::get_instance();
        }

        $content = array(
            'header' => array(
                'name' => $user_profile['name'],
                'headline' => $user_profile['headline'] ?: $job_data['title'] . ' Professional',
                'contact' => array(
                    'email' => $user_profile['email'],
                    'location' => isset($user_profile['profile']['location']) ? $user_profile['profile']['location'] : '',
                    'phone' => isset($user_profile['profile']['phone']) ? $user_profile['profile']['phone'] : '',
                    'linkedin' => isset($user_profile['profile']['linkedin']) ? $user_profile['profile']['linkedin'] : '',
                ),
            ),
            'executive_summary' => $this->generate_executive_summary($job_data, $user_profile, $prompts),
            'key_qualifications' => $this->extract_key_qualifications($job_data, $user_profile),
            'metrics' => $this->extract_key_metrics($user_profile),
            'experience' => $this->tailor_experience($job_data, $user_profile, $claude),
            'education' => $user_profile['education'],
            'skills' => $this->match_skills($job_data, $user_profile),
            'certifications' => isset($user_profile['profile']['certifications']) ? $user_profile['profile']['certifications'] : array(),
        );

        return $content;
    }

    /**
     * Generate executive summary using enhanced Claude prompts
     */
    private function generate_executive_summary($job_data, $user_profile, $prompts = null) {
        // Try using enhanced prompts manager first
        if ($prompts && $prompts instanceof SFFC_Application_Pack_Claude_Prompts && $prompts->is_available()) {
            $result = $prompts->generate_executive_summary($job_data, $user_profile);
            if ($result) {
                return $result;
            }
        }

        // Fallback: Try basic Claude API
        if ($prompts && $prompts instanceof SFFC_Claude_API_Manager && $prompts->is_available()) {
            $prompt = sprintf(
                "Write a 3-4 sentence executive summary for a CV. The candidate is applying for: %s at %s.

Their background includes: %s

Key job requirements: %s

Write in first person implied (no 'I'), professional tone, focusing on value proposition.
Maximum 4 sentences, 60 words. Include quantifiable achievements if mentioned.",
                $job_data['title'],
                $job_data['company'],
                $user_profile['summary'] ?: 'Finance professional with diverse experience',
                substr($job_data['requirements'] ?: $job_data['description'], 0, 500)
            );

            $result = $prompts->call_api($prompt, array(
                'mode' => 'cv_tailoring',
                'max_tokens' => 200,
                'temperature' => 0.7,
            ));

            if (isset($result['content'][0]['text'])) {
                return trim($result['content'][0]['text']);
            }
        }

        // Final fallback: Template
        return sprintf(
            "Results-driven %s professional with expertise in %s. Seeking to leverage proven track record in %s to deliver value at %s.",
            $job_data['title'],
            implode(', ', array_slice($job_data['skills'] ?: array('financial analysis', 'strategic planning'), 0, 3)),
            $job_data['industry'] ?: 'financial services',
            $job_data['company']
        );
    }

    /**
     * Extract key qualifications matched to job
     */
    private function extract_key_qualifications($job_data, $user_profile) {
        $qualifications = array();

        // Extract from job requirements
        $job_skills = $job_data['skills'] ?: array();
        $user_skills = $user_profile['skills'] ?: array();

        // Find matching skills
        foreach ($job_skills as $skill) {
            if (is_array($skill)) {
                $skill_name = $skill['name'] ?? $skill[0] ?? '';
            } else {
                $skill_name = $skill;
            }

            foreach ($user_skills as $user_skill) {
                if (stripos($user_skill, $skill_name) !== false || stripos($skill_name, $user_skill) !== false) {
                    $qualifications[] = $skill_name;
                    break;
                }
            }
        }

        // Add experience-based qualifications
        if (!empty($user_profile['experience'])) {
            $years = count($user_profile['experience']) * 2; // Rough estimate
            $qualifications[] = $years . '+ years of finance experience';
        }

        return array_slice(array_unique($qualifications), 0, 6);
    }

    /**
     * Extract key metrics from user profile
     */
    private function extract_key_metrics($user_profile) {
        $metrics = array();

        // Try to extract from experience bullets
        if (!empty($user_profile['experience'])) {
            foreach ($user_profile['experience'] as $exp) {
                if (isset($exp['achievements'])) {
                    foreach ($exp['achievements'] as $achievement) {
                        // Look for numbers
                        if (preg_match('/\$[\d.]+[MBK]?|\d+%|\d+ (deals|transactions|clients)/i', $achievement, $matches)) {
                            $metrics[] = $matches[0];
                        }
                    }
                }
            }
        }

        // Default metrics if none found
        if (empty($metrics)) {
            return array(
                array('value' => '—', 'label' => 'AUM'),
                array('value' => '—', 'label' => 'Deals'),
                array('value' => '—', 'label' => 'Returns'),
            );
        }

        return array_slice($metrics, 0, 3);
    }

    /**
     * Tailor experience bullets to job
     */
    private function tailor_experience($job_data, $user_profile, $claude = null) {
        $experience = $user_profile['experience'] ?: array();

        if (empty($experience)) {
            return array();
        }

        // For each experience entry, tailor bullets
        foreach ($experience as &$exp) {
            if (isset($exp['achievements']) && $claude && $claude->is_available()) {
                $exp['achievements'] = $this->tailor_bullets($exp['achievements'], $job_data, $claude);
            }
        }

        return $experience;
    }

    /**
     * Tailor individual bullets using Claude
     */
    private function tailor_bullets($bullets, $job_data, $claude) {
        if (empty($bullets)) {
            return $bullets;
        }

        $bullets_text = implode("\n", $bullets);

        $prompt = sprintf(
            "Rewrite these CV bullets to better match this job: %s at %s.

Original bullets:
%s

Job requirements summary:
%s

Rules:
- Keep each bullet under 180 characters
- Start with strong action verbs
- Preserve all factual information and numbers
- Use industry keywords: %s
- Maximum 4 bullets

Return only the rewritten bullets, one per line.",
            $job_data['title'],
            $job_data['company'],
            $bullets_text,
            substr($job_data['requirements'] ?: $job_data['description'], 0, 300),
            implode(', ', array_slice($job_data['skills'] ?: array(), 0, 5))
        );

        $result = $claude->call_api($prompt, array(
            'mode' => 'cv_tailoring',
            'max_tokens' => 400,
            'temperature' => 0.6,
        ));

        if (isset($result['content'][0]['text'])) {
            $tailored = array_filter(explode("\n", trim($result['content'][0]['text'])));
            return array_slice($tailored, 0, 4);
        }

        return $bullets;
    }

    /**
     * Match user skills to job requirements
     */
    private function match_skills($job_data, $user_profile) {
        $job_skills = $job_data['skills'] ?: array();
        $user_skills = $user_profile['skills'] ?: array();

        $matched = array();
        $additional = array();

        foreach ($user_skills as $skill) {
            $is_match = false;
            foreach ($job_skills as $job_skill) {
                $job_skill_name = is_array($job_skill) ? ($job_skill['name'] ?? '') : $job_skill;
                if (stripos($skill, $job_skill_name) !== false || stripos($job_skill_name, $skill) !== false) {
                    $matched[] = $skill;
                    $is_match = true;
                    break;
                }
            }
            if (!$is_match) {
                $additional[] = $skill;
            }
        }

        return array(
            'matched' => array_slice($matched, 0, 8),
            'additional' => array_slice($additional, 0, 4),
        );
    }

    /**
     * Render CV preview HTML
     */
    private function render_cv_preview($cv_content, $job_data) {
        $design = new SFFC_Application_Pack_Design_System();

        ob_start();
        ?>
        <style><?php echo $design::get_preview_css(); ?></style>
        <div class="sffc-app-pack-preview">
            <!-- Header -->
            <h1><?php echo esc_html($cv_content['header']['name']); ?></h1>
            <p class="subtitle">
                <?php echo esc_html($cv_content['header']['headline']); ?>
                <?php if (!empty($cv_content['header']['contact']['location'])): ?>
                    | <?php echo esc_html($cv_content['header']['contact']['location']); ?>
                <?php endif; ?>
                <?php if (!empty($cv_content['header']['contact']['email'])): ?>
                    | <?php echo esc_html($cv_content['header']['contact']['email']); ?>
                <?php endif; ?>
            </p>

            <!-- Executive Summary -->
            <h2>Executive Profile</h2>
            <div class="executive-box">
                <p><?php echo esc_html($cv_content['executive_summary']); ?></p>
            </div>

            <!-- Two Column: Qualifications & Metrics -->
            <div class="two-column">
                <div>
                    <h3>Key Qualifications</h3>
                    <ul>
                        <?php foreach ($cv_content['key_qualifications'] as $qual): ?>
                            <li><?php echo esc_html($qual); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div>
                    <h3>Core Metrics</h3>
                    <div class="metrics-row" style="flex-direction: column; gap: 8px;">
                        <?php foreach ($cv_content['metrics'] as $metric): ?>
                            <?php if (is_array($metric)): ?>
                                <div class="metric-box" style="display: flex; justify-content: space-between; text-align: left;">
                                    <span class="metric-label"><?php echo esc_html($metric['label']); ?></span>
                                    <span class="metric-value" style="font-size: 14pt;"><?php echo esc_html($metric['value']); ?></span>
                                </div>
                            <?php else: ?>
                                <div class="metric-box" style="text-align: left;">
                                    <span><?php echo esc_html($metric); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Experience -->
            <h2>Professional Experience</h2>
            <?php foreach ($cv_content['experience'] as $exp): ?>
                <div class="experience-entry">
                    <div class="experience-header">
                        <span class="company-name"><?php echo esc_html($exp['company'] ?? ''); ?></span>
                        <span class="date-range"><?php echo esc_html(($exp['start_date'] ?? '') . ' - ' . ($exp['end_date'] ?? 'Present')); ?></span>
                    </div>
                    <div class="job-title"><?php echo esc_html($exp['title'] ?? ''); ?></div>
                    <?php if (!empty($exp['achievements'])): ?>
                        <ul>
                            <?php foreach ($exp['achievements'] as $bullet): ?>
                                <li><?php echo esc_html($bullet); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <!-- Skills -->
            <h2>Skills & Expertise</h2>
            <div class="skill-tags">
                <?php foreach ($cv_content['skills']['matched'] as $skill): ?>
                    <span class="skill-tag" style="background: #e8f4f8; border-color: #2c5282;"><?php echo esc_html($skill); ?></span>
                <?php endforeach; ?>
                <?php foreach ($cv_content['skills']['additional'] as $skill): ?>
                    <span class="skill-tag"><?php echo esc_html($skill); ?></span>
                <?php endforeach; ?>
            </div>

            <!-- Education -->
            <?php if (!empty($cv_content['education'])): ?>
                <h2>Education</h2>
                <?php foreach ($cv_content['education'] as $edu): ?>
                    <div class="experience-entry">
                        <div class="experience-header">
                            <span class="company-name"><?php echo esc_html($edu['institution'] ?? ''); ?></span>
                            <span class="date-range"><?php echo esc_html($edu['year'] ?? ''); ?></span>
                        </div>
                        <div class="job-title"><?php echo esc_html($edu['degree'] ?? ''); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="footer">
                Tailored for <?php echo esc_html($job_data['title']); ?> at <?php echo esc_html($job_data['company']); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate Cover Letter
     */
    private function generate_cover_letter($job_data, $user_profile) {
        // Get Claude API for content generation
        $claude = null;
        if (class_exists('SFFC_Claude_API_Manager')) {
            $claude = SFFC_Claude_API_Manager::get_instance();
        }

        // Build cover letter content
        $content = $this->build_cover_letter_content($job_data, $user_profile, $claude);

        // Generate preview HTML
        $preview_html = $this->render_cover_letter_preview($content, $job_data);

        return array(
            'content' => $content,
            'preview_html' => $preview_html,
            'download_url' => '',
        );
    }

    /**
     * Build cover letter content structure
     */
    private function build_cover_letter_content($job_data, $user_profile, $claude = null) {
        // Get enhanced prompts manager
        $prompts = SFFC_Application_Pack_Claude_Prompts::get_instance();

        $content = array(
            'sender_name' => $user_profile['name'],
            'email' => $user_profile['email'],
            'phone' => isset($user_profile['profile']['phone']) ? $user_profile['profile']['phone'] : '',
            'linkedin' => isset($user_profile['profile']['linkedin']) ? $user_profile['profile']['linkedin'] : '',
            'recipient' => $this->get_hiring_manager_line($job_data),
            'date' => date('F j, Y'),
            'paragraphs' => array(),
        );

        // Try enhanced prompts manager first (better quality)
        if ($prompts->is_available()) {
            $result = $prompts->generate_cover_letter($job_data, $user_profile);
            if ($result) {
                // Parse the 4 paragraphs from the response
                $paragraphs = array_filter(array_map('trim', preg_split('/\n\n+/', $result)));
                if (count($paragraphs) >= 3) {
                    $content['paragraphs'] = array_values($paragraphs);
                    return $content;
                }
            }
        }

        // Fallback to basic Claude API
        if ($claude && $claude->is_available()) {
            $content['paragraphs'] = $this->generate_cover_letter_paragraphs_claude($job_data, $user_profile, $claude);
        } else {
            $content['paragraphs'] = $this->generate_cover_letter_paragraphs_template($job_data, $user_profile);
        }

        return $content;
    }

    /**
     * Get hiring manager line
     */
    private function get_hiring_manager_line($job_data) {
        if (!empty($job_data['hiring_manager'])) {
            return $job_data['hiring_manager'];
        }
        return 'Hiring Manager';
    }

    /**
     * Generate cover letter paragraphs using Claude
     */
    private function generate_cover_letter_paragraphs_claude($job_data, $user_profile, $claude) {
        $experience_summary = '';
        if (!empty($user_profile['experience'])) {
            $exp = $user_profile['experience'][0] ?? array();
            $experience_summary = sprintf(
                "%s at %s",
                $exp['title'] ?? 'Professional',
                $exp['company'] ?? 'leading firm'
            );
        }

        $skills_list = implode(', ', array_slice($user_profile['skills'] ?? array('analytical skills', 'financial modeling'), 0, 5));

        $career_goal = '';
        if (!empty($user_profile['career_journey']['goal_description'])) {
            $career_goal = $user_profile['career_journey']['goal_description'];
        }

        $prompt = sprintf(
            "Write a professional cover letter for a finance industry job application. Return ONLY the body paragraphs (no greeting or closing).

POSITION: %s at %s
LOCATION: %s

CANDIDATE BACKGROUND:
- Current/Recent Role: %s
- Key Skills: %s
- Career Goal: %s
- Professional Summary: %s

JOB REQUIREMENTS (key points):
%s

INSTRUCTIONS:
1. Write exactly 4 paragraphs
2. Paragraph 1 (HOOK): Opening that shows genuine interest and connects your background to this specific role. Mention the company by name. 2-3 sentences max.
3. Paragraph 2 (VALUE PROPOSITION): What unique value you bring. Connect your experience to their needs. Be specific about relevant achievements. 3-4 sentences.
4. Paragraph 3 (EVIDENCE): One or two concrete examples/achievements that demonstrate your fit. Include metrics if possible. 3-4 sentences.
5. Paragraph 4 (CLOSE): Express enthusiasm, mention you'd welcome the opportunity to discuss further. 2 sentences max.

STYLE:
- Professional but personable (McKinsey/Bain tone)
- Confident without arrogance
- Specific, not generic
- No clichés like 'I am writing to apply' or 'I believe I would be a great fit'
- Total length: 250-350 words

Return each paragraph separated by [PARA] marker.",
            $job_data['title'],
            $job_data['company'],
            $job_data['location'] ?? 'Not specified',
            $experience_summary,
            $skills_list,
            $career_goal ?: 'Career advancement in finance',
            $user_profile['summary'] ?? 'Experienced finance professional',
            substr($job_data['requirements'] ?? $job_data['description'] ?? '', 0, 600)
        );

        $result = $claude->call_api($prompt, array(
            'mode' => 'cv_tailoring',
            'max_tokens' => 800,
            'temperature' => 0.7,
        ));

        if (isset($result['content'][0]['text'])) {
            $text = trim($result['content'][0]['text']);
            // Split by paragraph marker or double newlines
            if (strpos($text, '[PARA]') !== false) {
                $paragraphs = array_map('trim', explode('[PARA]', $text));
            } else {
                $paragraphs = array_map('trim', preg_split('/\n\n+/', $text));
            }
            $paragraphs = array_filter($paragraphs);

            if (count($paragraphs) >= 3) {
                return array_values($paragraphs);
            }
        }

        // Fallback if Claude fails
        return $this->generate_cover_letter_paragraphs_template($job_data, $user_profile);
    }

    /**
     * Generate cover letter paragraphs using templates (fallback)
     */
    private function generate_cover_letter_paragraphs_template($job_data, $user_profile) {
        $current_role = '';
        $current_company = '';

        if (!empty($user_profile['experience'])) {
            $exp = $user_profile['experience'][0];
            $current_role = $exp['title'] ?? '';
            $current_company = $exp['company'] ?? '';
        }

        $skills = array_slice($user_profile['skills'] ?? array(), 0, 3);
        $skills_text = !empty($skills) ? implode(', ', $skills) : 'financial analysis and strategic thinking';

        // Opening paragraph
        $para1 = sprintf(
            "The %s opportunity at %s immediately caught my attention. %s's reputation for excellence in %s aligns perfectly with my career trajectory and professional values.",
            $job_data['title'],
            $job_data['company'],
            $job_data['company'],
            $job_data['industry'] ?? 'the financial sector'
        );

        // Value proposition
        $para2 = sprintf(
            "As a %s with experience at %s, I have developed strong capabilities in %s. My background has prepared me to deliver immediate value in this role, combining technical proficiency with the strategic mindset essential for success in %s.",
            $current_role ?: 'finance professional',
            $current_company ?: 'respected institutions',
            $skills_text,
            $job_data['department'] ?? 'this position'
        );

        // Evidence paragraph
        $para3 = "Throughout my career, I have consistently demonstrated the ability to drive results while maintaining the highest professional standards. My experience includes managing complex projects, collaborating with cross-functional teams, and delivering insights that inform strategic decisions.";

        // Closing paragraph
        $para4 = sprintf(
            "I would welcome the opportunity to discuss how my background and enthusiasm could benefit %s. Thank you for considering my application.",
            $job_data['company']
        );

        return array($para1, $para2, $para3, $para4);
    }

    /**
     * Render cover letter preview HTML
     */
    private function render_cover_letter_preview($content, $job_data) {
        $design = new SFFC_Application_Pack_Design_System();

        ob_start();
        ?>
        <style><?php echo $design::get_preview_css(); ?></style>
        <div class="sffc-app-pack-preview" style="font-family: Georgia, 'Times New Roman', serif;">
            <!-- Date -->
            <p style="text-align: right; color: var(--app-pack-text-medium); margin-bottom: 24px;">
                <?php echo esc_html($content['date']); ?>
            </p>

            <!-- Recipient -->
            <div style="margin-bottom: 24px;">
                <p style="margin: 0;"><?php echo esc_html($content['recipient']); ?></p>
                <p style="margin: 0;"><?php echo esc_html($job_data['company']); ?></p>
                <?php if (!empty($job_data['location'])): ?>
                    <p style="margin: 0; color: var(--app-pack-text-medium);"><?php echo esc_html($job_data['location']); ?></p>
                <?php endif; ?>
            </div>

            <!-- Subject Line -->
            <p style="font-weight: 600; color: var(--app-pack-primary); margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid var(--app-pack-border);">
                RE: <?php echo esc_html($job_data['title']); ?> Application
            </p>

            <!-- Salutation -->
            <p style="margin-top: 24px;">Dear <?php echo esc_html($content['recipient']); ?>,</p>

            <!-- Body Paragraphs -->
            <?php foreach ($content['paragraphs'] as $paragraph): ?>
                <p style="margin: 16px 0; line-height: 1.6; text-align: justify;">
                    <?php echo esc_html($paragraph); ?>
                </p>
            <?php endforeach; ?>

            <!-- Closing -->
            <div style="margin-top: 32px;">
                <p style="margin-bottom: 32px;">Regards,</p>
                <p style="font-weight: 600; margin: 0;"><?php echo esc_html($content['sender_name']); ?></p>
                <?php
                $contact_parts = array();
                if (!empty($content['phone'])) $contact_parts[] = $content['phone'];
                if (!empty($content['email'])) $contact_parts[] = $content['email'];
                if (!empty($content['linkedin'])) $contact_parts[] = $content['linkedin'];
                if (!empty($contact_parts)):
                ?>
                    <p style="color: var(--app-pack-text-medium); font-size: 9pt; margin-top: 4px;">
                        <?php echo esc_html(implode('  |  ', $contact_parts)); ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="footer" style="margin-top: 48px;">
                Cover Letter for <?php echo esc_html($job_data['title']); ?> at <?php echo esc_html($job_data['company']); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate Networking Messages
     */
    private function generate_networking_messages($job_data, $user_profile) {
        // Get Claude API for content generation
        $claude = null;
        if (class_exists('SFFC_Claude_API_Manager')) {
            $claude = SFFC_Claude_API_Manager::get_instance();
        }

        // Build all networking message types
        $content = $this->build_networking_content($job_data, $user_profile, $claude);

        // Generate preview HTML
        $preview_html = $this->render_networking_preview($content, $job_data);

        return array(
            'content' => $content,
            'preview_html' => $preview_html,
            'download_url' => '', // Text-based, no file download
        );
    }

    /**
     * Build networking messages content
     */
    private function build_networking_content($job_data, $user_profile, $claude = null) {
        // Try enhanced prompts manager first - generates all 5 messages in one call
        $prompts = SFFC_Application_Pack_Claude_Prompts::get_instance();

        if ($prompts->is_available()) {
            $result = $prompts->generate_networking_messages($job_data, $user_profile);
            if ($result) {
                $parsed = $prompts->parse_networking_messages($result);
                if (!empty($parsed)) {
                    // Map the parsed messages to our expected format
                    return array(
                        'linkedin_connect' => isset($parsed['linkedin_connection']) ? $parsed['linkedin_connection'] : $this->generate_linkedin_connection($job_data, $user_profile, $claude),
                        'linkedin_inmail' => isset($parsed['linkedin_inmail']) ? $parsed['linkedin_inmail'] : $this->generate_linkedin_inmail($job_data, $user_profile, $claude),
                        'email_hiring_manager' => isset($parsed['email_hiring_manager']) ? $parsed['email_hiring_manager'] : $this->generate_email_hiring_manager($job_data, $user_profile, $claude),
                        'email_recruiter' => isset($parsed['email_recruiter']) ? $parsed['email_recruiter'] : $this->generate_email_recruiter($job_data, $user_profile, $claude),
                        'referral_request' => isset($parsed['referral_request']) ? $parsed['referral_request'] : $this->generate_referral_request($job_data, $user_profile, $claude),
                    );
                }
            }
        }

        // Fallback to individual message generation
        $messages = array(
            'linkedin_connect' => $this->generate_linkedin_connection($job_data, $user_profile, $claude),
            'linkedin_inmail' => $this->generate_linkedin_inmail($job_data, $user_profile, $claude),
            'email_hiring_manager' => $this->generate_email_hiring_manager($job_data, $user_profile, $claude),
            'email_recruiter' => $this->generate_email_recruiter($job_data, $user_profile, $claude),
            'referral_request' => $this->generate_referral_request($job_data, $user_profile, $claude),
        );

        return $messages;
    }

    /**
     * Generate LinkedIn connection request (300 char limit)
     */
    private function generate_linkedin_connection($job_data, $user_profile, $claude = null) {
        $current_role = '';
        if (!empty($user_profile['experience'])) {
            $exp = $user_profile['experience'][0];
            $current_role = $exp['title'] ?? 'finance professional';
        }

        if ($claude && $claude->is_available()) {
            $prompt = sprintf(
                "Write a LinkedIn connection request for someone applying to %s at %s.

SENDER: %s, currently %s
TARGET: Hiring manager or team member at %s

CONSTRAINTS:
- MAXIMUM 280 characters (LinkedIn limit is 300, leave buffer)
- Be genuine, not salesy
- Reference specific interest in the company/role
- Don't ask for a job directly
- Professional but warm tone

Return ONLY the message text, nothing else.",
                $job_data['title'],
                $job_data['company'],
                $user_profile['name'],
                $current_role ?: 'a finance professional',
                $job_data['company']
            );

            $result = $claude->call_api($prompt, array(
                'mode' => 'cv_tailoring',
                'max_tokens' => 150,
                'temperature' => 0.7,
            ));

            if (isset($result['content'][0]['text'])) {
                $message = trim($result['content'][0]['text']);
                // Enforce character limit
                if (strlen($message) > 295) {
                    $message = substr($message, 0, 292) . '...';
                }
                return array(
                    'title' => 'LinkedIn Connection Request',
                    'description' => 'Send to hiring managers or team members',
                    'message' => $message,
                    'char_limit' => 300,
                    'char_count' => strlen($message),
                );
            }
        }

        // Template fallback
        $message = sprintf(
            "Hi! I noticed %s is hiring for %s. As a %s with experience in %s, I'm very interested in learning more about the team. Would love to connect.",
            $job_data['company'],
            $job_data['title'],
            $current_role ?: 'finance professional',
            $job_data['industry'] ?? 'financial services'
        );

        if (strlen($message) > 295) {
            $message = sprintf(
                "Hi! I saw the %s role at %s and I'm very interested. Would love to connect and learn more about the team.",
                $job_data['title'],
                $job_data['company']
            );
        }

        return array(
            'title' => 'LinkedIn Connection Request',
            'description' => 'Send to hiring managers or team members',
            'message' => $message,
            'char_limit' => 300,
            'char_count' => strlen($message),
        );
    }

    /**
     * Generate LinkedIn InMail (longer form)
     */
    private function generate_linkedin_inmail($job_data, $user_profile, $claude = null) {
        $current_role = '';
        $current_company = '';
        if (!empty($user_profile['experience'])) {
            $exp = $user_profile['experience'][0];
            $current_role = $exp['title'] ?? '';
            $current_company = $exp['company'] ?? '';
        }

        $skills_text = implode(', ', array_slice($user_profile['skills'] ?? array(), 0, 3));

        if ($claude && $claude->is_available()) {
            $prompt = sprintf(
                "Write a LinkedIn InMail for someone reaching out about %s at %s.

SENDER: %s
CURRENT ROLE: %s at %s
KEY SKILLS: %s

JOB DETAILS:
- Title: %s
- Company: %s
- Industry: %s

INSTRUCTIONS:
1. Subject line (compelling, under 60 chars)
2. Opening that shows you've done research
3. Brief value proposition (what you bring)
4. Soft ask (conversation, not job)
5. Professional close

FORMAT:
Subject: [subject line]

[message body - 150-200 words max]

Be specific, not generic. Show genuine interest.",
                $job_data['title'],
                $job_data['company'],
                $user_profile['name'],
                $current_role ?: 'Finance Professional',
                $current_company ?: 'a leading firm',
                $skills_text ?: 'financial analysis, strategic planning',
                $job_data['title'],
                $job_data['company'],
                $job_data['industry'] ?? 'Financial Services'
            );

            $result = $claude->call_api($prompt, array(
                'mode' => 'cv_tailoring',
                'max_tokens' => 400,
                'temperature' => 0.7,
            ));

            if (isset($result['content'][0]['text'])) {
                $text = trim($result['content'][0]['text']);
                // Parse subject and body
                $subject = '';
                $body = $text;

                if (preg_match('/Subject:\s*(.+?)(?:\n|$)/i', $text, $matches)) {
                    $subject = trim($matches[1]);
                    $body = trim(preg_replace('/Subject:\s*.+?(?:\n|$)/i', '', $text));
                }

                return array(
                    'title' => 'LinkedIn InMail',
                    'description' => 'For reaching out to people you\'re not connected with',
                    'subject' => $subject ?: 'Interested in ' . $job_data['title'] . ' opportunity',
                    'message' => $body,
                );
            }
        }

        // Template fallback
        $subject = sprintf("Interest in %s at %s", $job_data['title'], $job_data['company']);
        $body = sprintf(
            "Hi,

I came across the %s position at %s and was immediately drawn to it. %s's work in %s aligns perfectly with my background and career interests.

Currently, I'm a %s with experience in %s. I've been following %s's growth and am impressed by the team's approach to %s.

I'd welcome the chance to learn more about the role and share how my experience might contribute to your team's goals. Would you be open to a brief conversation?

Best regards,
%s",
            $job_data['title'],
            $job_data['company'],
            $job_data['company'],
            $job_data['industry'] ?? 'the industry',
            $current_role ?: 'finance professional',
            $skills_text ?: 'financial analysis and strategic planning',
            $job_data['company'],
            $job_data['department'] ?? 'innovation',
            $user_profile['first_name'] ?? $user_profile['name']
        );

        return array(
            'title' => 'LinkedIn InMail',
            'description' => 'For reaching out to people you\'re not connected with',
            'subject' => $subject,
            'message' => $body,
        );
    }

    /**
     * Generate cold email to hiring manager
     */
    private function generate_email_hiring_manager($job_data, $user_profile, $claude = null) {
        $current_role = '';
        $current_company = '';
        if (!empty($user_profile['experience'])) {
            $exp = $user_profile['experience'][0];
            $current_role = $exp['title'] ?? '';
            $current_company = $exp['company'] ?? '';
        }

        $skills_text = implode(', ', array_slice($user_profile['skills'] ?? array(), 0, 4));

        if ($claude && $claude->is_available()) {
            $prompt = sprintf(
                "Write a cold email to a hiring manager about %s at %s.

SENDER: %s, %s at %s
SKILLS: %s

JOB: %s at %s (%s)

Write a professional email with:
1. Subject line (specific, compelling)
2. Opening that shows research/specific interest
3. 2-3 sentences on relevant experience
4. Clear but soft call-to-action
5. Professional signature placeholder

FORMAT:
Subject: [subject]

[salutation]

[body - 120-180 words]

[closing]
[Name]

Tone: Confident but respectful. Not desperate or overly formal.",
                $job_data['title'],
                $job_data['company'],
                $user_profile['name'],
                $current_role ?: 'Finance Professional',
                $current_company ?: 'a leading institution',
                $skills_text ?: 'financial analysis, modeling, strategic planning',
                $job_data['title'],
                $job_data['company'],
                $job_data['industry'] ?? 'Financial Services'
            );

            $result = $claude->call_api($prompt, array(
                'mode' => 'cv_tailoring',
                'max_tokens' => 450,
                'temperature' => 0.7,
            ));

            if (isset($result['content'][0]['text'])) {
                $text = trim($result['content'][0]['text']);
                $subject = '';
                $body = $text;

                if (preg_match('/Subject:\s*(.+?)(?:\n|$)/i', $text, $matches)) {
                    $subject = trim($matches[1]);
                    $body = trim(preg_replace('/Subject:\s*.+?(?:\n|$)/i', '', $text));
                }

                return array(
                    'title' => 'Email to Hiring Manager',
                    'description' => 'Direct outreach to the hiring manager',
                    'subject' => $subject ?: $job_data['title'] . ' Role - ' . $user_profile['name'],
                    'message' => $body,
                );
            }
        }

        // Template fallback
        $subject = sprintf("%s Role - Experienced %s", $job_data['title'], $current_role ?: 'Professional');
        $body = sprintf(
            "Dear Hiring Manager,

I recently discovered the %s position at %s and wanted to reach out directly to express my strong interest.

As a %s with experience in %s, I've developed expertise that closely aligns with what you're looking for. My background includes %s, which I believe would translate well to this role.

I've attached my CV for your review and would welcome the opportunity to discuss how I could contribute to %s. Would you have 15 minutes for a brief call this week or next?

Thank you for your time and consideration.

Best regards,
%s
%s",
            $job_data['title'],
            $job_data['company'],
            $current_role ?: 'finance professional',
            $skills_text ?: 'financial analysis and strategic initiatives',
            $job_data['industry'] ?? 'financial services',
            $job_data['company'],
            $user_profile['name'],
            $user_profile['email']
        );

        return array(
            'title' => 'Email to Hiring Manager',
            'description' => 'Direct outreach to the hiring manager',
            'subject' => $subject,
            'message' => $body,
        );
    }

    /**
     * Generate cold email to recruiter
     */
    private function generate_email_recruiter($job_data, $user_profile, $claude = null) {
        $current_role = '';
        if (!empty($user_profile['experience'])) {
            $current_role = $user_profile['experience'][0]['title'] ?? '';
        }

        $skills_text = implode(', ', array_slice($user_profile['skills'] ?? array(), 0, 3));

        if ($claude && $claude->is_available()) {
            $prompt = sprintf(
                "Write an email to a recruiter about %s at %s.

CANDIDATE: %s, currently %s
SKILLS: %s
TARGET ROLE: %s at %s

Write a recruiter-friendly email:
1. Subject line that gets opened
2. Quick intro (who you are)
3. Why this specific role interests you
4. Key qualifications in 2-3 bullet points
5. Ask about the process/timeline

FORMAT:
Subject: [subject]

Hi [Recruiter],

[body with bullet points - 100-150 words]

[closing]
[Name]

Tone: Professional, efficient, easy to scan.",
                $job_data['title'],
                $job_data['company'],
                $user_profile['name'],
                $current_role ?: 'a finance professional',
                $skills_text ?: 'financial analysis',
                $job_data['title'],
                $job_data['company']
            );

            $result = $claude->call_api($prompt, array(
                'mode' => 'cv_tailoring',
                'max_tokens' => 400,
                'temperature' => 0.7,
            ));

            if (isset($result['content'][0]['text'])) {
                $text = trim($result['content'][0]['text']);
                $subject = '';
                $body = $text;

                if (preg_match('/Subject:\s*(.+?)(?:\n|$)/i', $text, $matches)) {
                    $subject = trim($matches[1]);
                    $body = trim(preg_replace('/Subject:\s*.+?(?:\n|$)/i', '', $text));
                }

                return array(
                    'title' => 'Email to Recruiter',
                    'description' => 'For internal or external recruiters',
                    'subject' => $subject ?: 'Application: ' . $job_data['title'],
                    'message' => $body,
                );
            }
        }

        // Template fallback
        $subject = sprintf("Application: %s - %s", $job_data['title'], $user_profile['name']);
        $body = sprintf(
            "Hi,

I'm reaching out regarding the %s position at %s. I believe my background makes me a strong candidate:

• Currently %s with %s experience
• Key skills: %s
• Strong interest in %s's work in %s

I've submitted my application through the portal and wanted to introduce myself directly. I'd appreciate any insights on the timeline or process.

My CV is attached for your reference. Happy to provide any additional information.

Best regards,
%s
%s
%s",
            $job_data['title'],
            $job_data['company'],
            $current_role ?: 'a finance professional',
            $job_data['industry'] ?? 'financial services',
            $skills_text ?: 'financial analysis, modeling, strategic planning',
            $job_data['company'],
            $job_data['department'] ?? 'the industry',
            $user_profile['name'],
            $user_profile['email'],
            isset($user_profile['profile']['phone']) ? $user_profile['profile']['phone'] : ''
        );

        return array(
            'title' => 'Email to Recruiter',
            'description' => 'For internal or external recruiters',
            'subject' => $subject,
            'message' => $body,
        );
    }

    /**
     * Generate referral request email
     */
    private function generate_referral_request($job_data, $user_profile, $claude = null) {
        $current_role = '';
        if (!empty($user_profile['experience'])) {
            $current_role = $user_profile['experience'][0]['title'] ?? '';
        }

        if ($claude && $claude->is_available()) {
            $prompt = sprintf(
                "Write an email asking a contact for a referral to %s at %s.

SENDER: %s, %s
TARGET COMPANY: %s
ROLE: %s

Write a referral request that:
1. Acknowledges the relationship (placeholder for contact name)
2. Explains why you're interested in this specific role
3. Makes a clear but polite ask
4. Makes it easy to say yes (or no)
5. Offers to send materials

FORMAT:
Subject: [subject]

Hi [Contact Name],

[body - 100-140 words]

[closing]
[Name]

Tone: Warm, respectful of their time, not presumptuous.",
                $job_data['title'],
                $job_data['company'],
                $user_profile['name'],
                $current_role ?: 'finance professional',
                $job_data['company'],
                $job_data['title']
            );

            $result = $claude->call_api($prompt, array(
                'mode' => 'cv_tailoring',
                'max_tokens' => 350,
                'temperature' => 0.7,
            ));

            if (isset($result['content'][0]['text'])) {
                $text = trim($result['content'][0]['text']);
                $subject = '';
                $body = $text;

                if (preg_match('/Subject:\s*(.+?)(?:\n|$)/i', $text, $matches)) {
                    $subject = trim($matches[1]);
                    $body = trim(preg_replace('/Subject:\s*.+?(?:\n|$)/i', '', $text));
                }

                return array(
                    'title' => 'Referral Request',
                    'description' => 'Ask a contact at the company for a referral',
                    'subject' => $subject ?: 'Quick question about ' . $job_data['company'],
                    'message' => $body,
                );
            }
        }

        // Template fallback
        $subject = sprintf("Quick question about %s", $job_data['company']);
        $body = sprintf(
            "Hi [Contact Name],

I hope you're doing well! I wanted to reach out because I saw that %s is hiring for a %s position, and I immediately thought of you.

Given my background as a %s, this role seems like an excellent fit. I'm particularly excited about %s's work in %s.

Would you be comfortable referring me or connecting me with someone on the team? I completely understand if you're not able to - no pressure at all.

If helpful, I can send over my CV and a brief summary of why I'm interested.

Thanks so much for considering this. I really appreciate it.

Best,
%s",
            $job_data['company'],
            $job_data['title'],
            $current_role ?: 'finance professional',
            $job_data['company'],
            $job_data['industry'] ?? 'the industry',
            $user_profile['first_name'] ?? $user_profile['name']
        );

        return array(
            'title' => 'Referral Request',
            'description' => 'Ask a contact at the company for a referral',
            'subject' => $subject,
            'message' => $body,
        );
    }

    /**
     * Render networking messages preview HTML
     */
    private function render_networking_preview($content, $job_data) {
        $design = new SFFC_Application_Pack_Design_System();

        ob_start();
        ?>
        <style>
            <?php echo $design::get_preview_css(); ?>
            .sffc-networking-messages {
                display: flex;
                flex-direction: column;
                gap: 24px;
            }
            .sffc-networking-card {
                background: var(--app-pack-background);
                border: 1px solid var(--app-pack-border);
                border-radius: 8px;
                overflow: hidden;
            }
            .sffc-networking-card-header {
                background: var(--app-pack-background-alt);
                padding: 12px 16px;
                border-bottom: 1px solid var(--app-pack-border);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .sffc-networking-card-title {
                font-weight: 600;
                color: var(--app-pack-primary);
                margin: 0;
                font-size: 11pt;
            }
            .sffc-networking-card-desc {
                font-size: 9pt;
                color: var(--app-pack-text-light);
                margin: 2px 0 0 0;
            }
            .sffc-networking-card-body {
                padding: 16px;
            }
            .sffc-networking-subject {
                font-weight: 600;
                color: var(--app-pack-text-dark);
                margin-bottom: 12px;
                padding-bottom: 8px;
                border-bottom: 1px dashed var(--app-pack-border);
                font-size: 10pt;
            }
            .sffc-networking-message {
                font-size: 10pt;
                line-height: 1.6;
                color: var(--app-pack-text-dark);
                white-space: pre-wrap;
                font-family: Georgia, 'Times New Roman', serif;
            }
            .sffc-networking-copy-btn {
                background: var(--app-pack-primary);
                color: white;
                border: none;
                padding: 6px 12px;
                border-radius: 4px;
                font-size: 9pt;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 4px;
                transition: background 0.2s;
            }
            .sffc-networking-copy-btn:hover {
                background: var(--app-pack-primary-light);
            }
            .sffc-networking-copy-btn.copied {
                background: #276749;
            }
            .sffc-networking-char-count {
                font-size: 8pt;
                color: var(--app-pack-text-light);
                margin-top: 8px;
            }
            .sffc-networking-char-count.warning {
                color: #c05621;
            }
        </style>
        <div class="sffc-app-pack-preview sffc-networking-messages">
            <div style="margin-bottom: 16px;">
                <h2 style="margin: 0 0 4px 0; border: none; padding: 0;">Networking Messages</h2>
                <p style="color: var(--app-pack-text-medium); margin: 0; font-size: 10pt;">
                    Tailored outreach templates for <?php echo esc_html($job_data['title']); ?> at <?php echo esc_html($job_data['company']); ?>
                </p>
            </div>

            <?php foreach ($content as $key => $msg): ?>
            <div class="sffc-networking-card" data-message-type="<?php echo esc_attr($key); ?>">
                <div class="sffc-networking-card-header">
                    <div>
                        <p class="sffc-networking-card-title"><?php echo esc_html($msg['title']); ?></p>
                        <p class="sffc-networking-card-desc"><?php echo esc_html($msg['description']); ?></p>
                    </div>
                    <button class="sffc-networking-copy-btn" data-copy-target="<?php echo esc_attr($key); ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                        </svg>
                        Copy
                    </button>
                </div>
                <div class="sffc-networking-card-body">
                    <?php if (!empty($msg['subject'])): ?>
                    <div class="sffc-networking-subject">
                        Subject: <?php echo esc_html($msg['subject']); ?>
                    </div>
                    <?php endif; ?>
                    <div class="sffc-networking-message" id="msg-<?php echo esc_attr($key); ?>">
<?php echo esc_html($msg['message']); ?>
                    </div>
                    <?php if (isset($msg['char_limit'])): ?>
                    <div class="sffc-networking-char-count <?php echo $msg['char_count'] > $msg['char_limit'] ? 'warning' : ''; ?>">
                        <?php echo $msg['char_count']; ?> / <?php echo $msg['char_limit']; ?> characters
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="footer" style="margin-top: 16px;">
                Click "Copy" to copy any message to your clipboard
            </div>
        </div>

        <script>
        (function() {
            document.querySelectorAll('.sffc-networking-copy-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var target = this.getAttribute('data-copy-target');
                    var card = this.closest('.sffc-networking-card');
                    var subject = card.querySelector('.sffc-networking-subject');
                    var message = card.querySelector('.sffc-networking-message');

                    var textToCopy = '';
                    if (subject) {
                        textToCopy = subject.textContent.trim() + '\n\n';
                    }
                    textToCopy += message.textContent.trim();

                    navigator.clipboard.writeText(textToCopy).then(function() {
                        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Copied!';
                        btn.classList.add('copied');
                        setTimeout(function() {
                            btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg> Copy';
                            btn.classList.remove('copied');
                        }, 2000);
                    });
                });
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate Interview Prep Sheet
     */
    private function generate_interview_prep($job_data, $user_profile) {
        // Get Claude API for content generation
        $claude = null;
        if (class_exists('SFFC_Claude_API_Manager')) {
            $claude = SFFC_Claude_API_Manager::get_instance();
        }

        // Build interview prep content
        $content = $this->build_interview_prep_content($job_data, $user_profile, $claude);

        // Generate preview HTML
        $preview_html = $this->render_interview_prep_preview($content, $job_data);

        return array(
            'content' => $content,
            'preview_html' => $preview_html,
            'download_url' => '',
        );
    }

    /**
     * Build interview prep content structure
     */
    private function build_interview_prep_content($job_data, $user_profile, $claude = null) {
        // Get enhanced prompts manager
        $prompts = SFFC_Application_Pack_Claude_Prompts::get_instance();

        $content = array(
            'role_overview' => $this->generate_role_overview($job_data, $claude),
            'company_facts' => $this->generate_company_facts($job_data),
            'alignment' => $this->generate_experience_alignment($job_data, $user_profile, $claude),
            'questions' => array(),
            'questions_to_ask' => array(),
            'talking_points' => array(),
            'challenges' => $this->generate_potential_challenges($job_data, $user_profile, $claude),
        );

        // Try enhanced prompts for interview questions (much better quality)
        if ($prompts->is_available()) {
            // Generate interview questions
            $questions_result = $prompts->generate_interview_questions($job_data, $user_profile);
            if ($questions_result) {
                $parsed_questions = $prompts->parse_interview_questions($questions_result);
                if (!empty($parsed_questions)) {
                    $content['questions'] = $parsed_questions;
                }
            }

            // Generate questions to ask
            $questions_to_ask_result = $prompts->generate_questions_to_ask($job_data);
            if ($questions_to_ask_result) {
                // Parse Q: ... Why: ... format
                $content['questions_to_ask'] = $this->parse_questions_to_ask($questions_to_ask_result);
            }

            // Generate talking points
            $talking_points_result = $prompts->generate_talking_points($job_data, $user_profile);
            if ($talking_points_result) {
                $content['talking_points'] = $talking_points_result;
            }
        }

        // Fallback to old methods if enhanced prompts didn't work
        if (empty($content['questions'])) {
            $content['questions'] = $this->generate_interview_questions($job_data, $user_profile, $claude);
        }
        if (empty($content['questions_to_ask'])) {
            $content['questions_to_ask'] = $this->generate_questions_to_ask($job_data, $claude);
        }
        if (empty($content['talking_points'])) {
            $content['talking_points'] = $this->generate_talking_points($job_data, $user_profile, $claude);
        }

        return $content;
    }

    /**
     * Parse questions to ask from Claude response
     */
    private function parse_questions_to_ask($response) {
        $questions = array();

        // Match Q: ... Why: ... pattern
        preg_match_all('/Q:\s*(.+?)(?=\n*Why:)\s*Why:\s*(.+?)(?=\n*Q:|\n*$)/is', $response, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $questions[] = array(
                'question' => trim($match[1]),
                'rationale' => trim($match[2]),
            );
        }

        // If parsing failed, return raw questions split by newlines
        if (empty($questions) && !empty($response)) {
            $lines = array_filter(array_map('trim', explode("\n", $response)));
            foreach ($lines as $line) {
                if (stripos($line, 'Q:') === 0) {
                    $questions[] = array(
                        'question' => trim(substr($line, 2)),
                        'rationale' => '',
                    );
                }
            }
        }

        return $questions;
    }

    /**
     * Generate role overview summary
     */
    private function generate_role_overview($job_data, $claude = null) {
        if ($claude && $claude->is_available()) {
            $prompt = sprintf(
                "Summarize this job role in 2-3 sentences for interview preparation. Focus on:
1. Core responsibility
2. Key skills needed
3. What success looks like

JOB: %s at %s
DESCRIPTION: %s

Write a concise, insightful summary (60 words max).",
                $job_data['title'],
                $job_data['company'],
                substr($job_data['description'] ?? '', 0, 800)
            );

            $result = $claude->call_api($prompt, array(
                'mode' => 'cv_tailoring',
                'max_tokens' => 150,
                'temperature' => 0.6,
            ));

            if (isset($result['content'][0]['text'])) {
                return trim($result['content'][0]['text']);
            }
        }

        // Template fallback
        return sprintf(
            "The %s role at %s focuses on %s within the %s sector. Key requirements include strong analytical capabilities, excellent communication skills, and the ability to work in a fast-paced environment. Success will be measured by your ability to deliver results and collaborate effectively with stakeholders.",
            $job_data['title'],
            $job_data['company'],
            $job_data['department'] ?? 'strategic initiatives',
            $job_data['industry'] ?? 'financial services'
        );
    }

    /**
     * Generate company quick facts
     */
    private function generate_company_facts($job_data) {
        return array(
            array('label' => 'Company', 'value' => $job_data['company']),
            array('label' => 'Industry', 'value' => $job_data['industry'] ?? 'Financial Services'),
            array('label' => 'Location', 'value' => $job_data['location'] ?? 'Not specified'),
            array('label' => 'Role Type', 'value' => $job_data['employment_type'] ?? 'Full-time'),
            array('label' => 'Experience', 'value' => $job_data['experience_level'] ?? 'Mid-Senior'),
        );
    }

    /**
     * Generate experience alignment (They Need vs You Have)
     */
    private function generate_experience_alignment($job_data, $user_profile, $claude = null) {
        $alignment = array();

        // Extract requirements from job
        $job_skills = $job_data['skills'] ?? array();
        $user_skills = $user_profile['skills'] ?? array();
        $user_experience = $user_profile['experience'] ?? array();

        if ($claude && $claude->is_available() && !empty($job_data['requirements'])) {
            $prompt = sprintf(
                "Create an experience alignment table for interview prep.

JOB REQUIREMENTS:
%s

CANDIDATE BACKGROUND:
- Current/Recent Role: %s
- Skills: %s
- Experience Summary: %s

Create 5 rows matching job needs to candidate experience.
FORMAT (return ONLY this, one per line):
NEED: [requirement] | HAVE: [matching experience]

Be specific and quantify where possible.",
                substr($job_data['requirements'] ?? $job_data['description'], 0, 600),
                isset($user_experience[0]) ? ($user_experience[0]['title'] ?? '') . ' at ' . ($user_experience[0]['company'] ?? '') : 'Finance professional',
                implode(', ', array_slice($user_skills, 0, 5)),
                $user_profile['summary'] ?? 'Experienced finance professional'
            );

            $result = $claude->call_api($prompt, array(
                'mode' => 'cv_tailoring',
                'max_tokens' => 400,
                'temperature' => 0.6,
            ));

            if (isset($result['content'][0]['text'])) {
                $lines = explode("\n", trim($result['content'][0]['text']));
                foreach ($lines as $line) {
                    if (preg_match('/NEED:\s*(.+?)\s*\|\s*HAVE:\s*(.+)/i', $line, $matches)) {
                        $alignment[] = array(
                            'need' => trim($matches[1]),
                            'have' => trim($matches[2]),
                        );
                    }
                }
                if (count($alignment) >= 3) {
                    return array_slice($alignment, 0, 6);
                }
            }
        }

        // Template fallback - match skills
        $matched = 0;
        foreach ($job_skills as $skill) {
            $skill_name = is_array($skill) ? ($skill['name'] ?? $skill[0] ?? '') : $skill;
            if (empty($skill_name)) continue;

            $have = 'Relevant experience in ' . strtolower($skill_name);
            foreach ($user_skills as $user_skill) {
                if (stripos($user_skill, $skill_name) !== false || stripos($skill_name, $user_skill) !== false) {
                    $have = 'Direct experience: ' . $user_skill;
                    break;
                }
            }

            $alignment[] = array(
                'need' => $skill_name,
                'have' => $have,
            );

            $matched++;
            if ($matched >= 5) break;
        }

        // Add generic alignments if needed
        if (count($alignment) < 3) {
            $defaults = array(
                array('need' => 'Strong analytical skills', 'have' => 'Demonstrated through previous roles'),
                array('need' => 'Communication abilities', 'have' => 'Stakeholder management experience'),
                array('need' => 'Team collaboration', 'have' => 'Cross-functional project experience'),
            );
            $alignment = array_merge($alignment, array_slice($defaults, 0, 3 - count($alignment)));
        }

        return array_slice($alignment, 0, 6);
    }

    /**
     * Generate anticipated interview questions with guidance
     */
    private function generate_interview_questions($job_data, $user_profile, $claude = null) {
        $questions = array();

        $current_role = '';
        if (!empty($user_profile['experience'])) {
            $current_role = $user_profile['experience'][0]['title'] ?? '';
        }

        if ($claude && $claude->is_available()) {
            $prompt = sprintf(
                "Generate 6 likely interview questions for %s at %s with preparation guidance.

CANDIDATE: %s, currently %s
JOB REQUIREMENTS: %s

For each question, provide:
1. The likely question
2. Brief guidance on how to answer (using their background)

FORMAT (return exactly this format):
Q: [question]
A: [guidance for answering]

Include mix of:
- Behavioral (STAR method)
- Technical/skills
- Motivation/fit
- Situational

Be specific to this role and industry.",
                $job_data['title'],
                $job_data['company'],
                $user_profile['name'],
                $current_role ?: 'finance professional',
                substr($job_data['requirements'] ?? $job_data['description'], 0, 500)
            );

            $result = $claude->call_api($prompt, array(
                'mode' => 'cv_tailoring',
                'max_tokens' => 800,
                'temperature' => 0.7,
            ));

            if (isset($result['content'][0]['text'])) {
                $text = $result['content'][0]['text'];
                preg_match_all('/Q:\s*(.+?)(?:\n|$).*?A:\s*(.+?)(?=\n\nQ:|\n\n|$)/s', $text, $matches, PREG_SET_ORDER);

                foreach ($matches as $match) {
                    $questions[] = array(
                        'question' => trim($match[1]),
                        'guidance' => trim($match[2]),
                    );
                }

                if (count($questions) >= 4) {
                    return array_slice($questions, 0, 6);
                }
            }
        }

        // Template fallback
        $industry = $job_data['industry'] ?? 'finance';
        return array(
            array(
                'question' => "Tell me about yourself and why you're interested in this role.",
                'guidance' => "Structure: Current role → Key achievements → Why this opportunity. Connect your background to " . $job_data['company'] . "'s needs."
            ),
            array(
                'question' => "Walk me through a challenging project you led and how you handled obstacles.",
                'guidance' => "Use STAR method. Pick a relevant example showing leadership, problem-solving, and results. Quantify impact."
            ),
            array(
                'question' => "Why " . $job_data['company'] . "? What attracts you to our firm?",
                'guidance' => "Research recent news, deals, or initiatives. Connect company values/strategy to your career goals."
            ),
            array(
                'question' => "How do you handle tight deadlines and competing priorities?",
                'guidance' => "Give specific example. Emphasize organization, communication, and delivering under pressure."
            ),
            array(
                'question' => "Where do you see yourself in 3-5 years?",
                'guidance' => "Show ambition aligned with realistic growth at " . $job_data['company'] . ". Demonstrate commitment."
            ),
            array(
                'question' => "What questions do you have for us?",
                'guidance' => "Prepare 3-4 thoughtful questions about team, growth, strategy. Shows genuine interest and preparation."
            ),
        );
    }

    /**
     * Generate questions to ask the interviewer
     */
    private function generate_questions_to_ask($job_data, $claude = null) {
        if ($claude && $claude->is_available()) {
            $prompt = sprintf(
                "Generate 6 impressive questions a candidate should ask when interviewing for %s at %s.

Questions should:
1. Show genuine interest and research
2. Reveal important information
3. Demonstrate strategic thinking
4. NOT be easily answered on website

Mix of:
- Role/team specific
- Company strategy/culture
- Growth opportunities
- Current challenges

Return just the questions, one per line. No numbering.",
                $job_data['title'],
                $job_data['company']
            );

            $result = $claude->call_api($prompt, array(
                'mode' => 'cv_tailoring',
                'max_tokens' => 350,
                'temperature' => 0.7,
            ));

            if (isset($result['content'][0]['text'])) {
                $questions = array_filter(array_map('trim', explode("\n", $result['content'][0]['text'])));
                // Remove any numbering
                $questions = array_map(function($q) {
                    return preg_replace('/^\d+[\.\)]\s*/', '', $q);
                }, $questions);
                $questions = array_filter($questions);

                if (count($questions) >= 4) {
                    return array_slice(array_values($questions), 0, 6);
                }
            }
        }

        // Template fallback
        return array(
            "What does success look like in this role in the first 90 days?",
            "How would you describe the team culture and working style?",
            "What are the biggest challenges facing the team right now?",
            "How has this role evolved, and where do you see it going?",
            "What opportunities exist for professional development and growth?",
            "What's the decision-making timeline for this position?",
        );
    }

    /**
     * Generate key talking points
     */
    private function generate_talking_points($job_data, $user_profile, $claude = null) {
        $current_role = '';
        $achievements = array();

        if (!empty($user_profile['experience'])) {
            $exp = $user_profile['experience'][0];
            $current_role = ($exp['title'] ?? '') . ' at ' . ($exp['company'] ?? '');
            $achievements = $exp['achievements'] ?? array();
        }

        if ($claude && $claude->is_available()) {
            $prompt = sprintf(
                "Create 4 key talking points for a candidate interviewing for %s at %s.

CANDIDATE BACKGROUND:
- Current: %s
- Achievements: %s
- Skills: %s

Each talking point should:
1. Be a memorable statement they can deliver
2. Connect their experience to job requirements
3. Include a quantifiable result or specific example
4. Be 1-2 sentences max

FORMAT:
• [talking point]

Focus on value proposition and differentiation.",
                $job_data['title'],
                $job_data['company'],
                $current_role ?: 'Finance professional',
                implode('; ', array_slice($achievements, 0, 3)) ?: 'Various finance achievements',
                implode(', ', array_slice($user_profile['skills'] ?? array(), 0, 4))
            );

            $result = $claude->call_api($prompt, array(
                'mode' => 'cv_tailoring',
                'max_tokens' => 350,
                'temperature' => 0.7,
            ));

            if (isset($result['content'][0]['text'])) {
                $text = $result['content'][0]['text'];
                preg_match_all('/[•\-\*]\s*(.+?)(?=\n[•\-\*]|\n\n|$)/s', $text, $matches);
                if (!empty($matches[1])) {
                    $points = array_map('trim', $matches[1]);
                    $points = array_filter($points);
                    if (count($points) >= 3) {
                        return array_slice(array_values($points), 0, 4);
                    }
                }
            }
        }

        // Template fallback
        $skills = implode(' and ', array_slice($user_profile['skills'] ?? array('analytical skills', 'financial modeling'), 0, 2));
        return array(
            "I bring a strong combination of $skills that directly applies to this role's requirements.",
            "My track record includes delivering results under pressure while maintaining high quality standards.",
            "I'm drawn to " . $job_data['company'] . " because of its reputation for excellence in " . ($job_data['industry'] ?? 'the industry') . ".",
            "I'm at a career stage where I'm looking for increased responsibility and this role offers exactly that opportunity.",
        );
    }

    /**
     * Generate potential challenges and how to address them
     */
    private function generate_potential_challenges($job_data, $user_profile, $claude = null) {
        $current_role = '';
        $years_exp = 0;

        if (!empty($user_profile['experience'])) {
            $current_role = $user_profile['experience'][0]['title'] ?? '';
            $years_exp = count($user_profile['experience']) * 2; // rough estimate
        }

        if ($claude && $claude->is_available()) {
            $prompt = sprintf(
                "Identify 3 potential concerns an interviewer might have about this candidate for %s at %s, and how to address them.

CANDIDATE:
- Current role: %s
- Skills: %s
- Experience level: ~%d years

JOB REQUIREMENTS:
%s

For each concern:
1. What they might worry about
2. How to proactively address it

FORMAT:
CONCERN: [concern]
ADDRESS: [how to handle]

Be honest but constructive. Focus on reframing potential weaknesses as strengths.",
                $job_data['title'],
                $job_data['company'],
                $current_role ?: 'Finance professional',
                implode(', ', array_slice($user_profile['skills'] ?? array(), 0, 4)),
                $years_exp,
                substr($job_data['requirements'] ?? $job_data['description'], 0, 400)
            );

            $result = $claude->call_api($prompt, array(
                'mode' => 'cv_tailoring',
                'max_tokens' => 400,
                'temperature' => 0.7,
            ));

            if (isset($result['content'][0]['text'])) {
                $text = $result['content'][0]['text'];
                $challenges = array();
                preg_match_all('/CONCERN:\s*(.+?)(?:\n|$).*?ADDRESS:\s*(.+?)(?=\n\nCONCERN:|\n\n|$)/si', $text, $matches, PREG_SET_ORDER);

                foreach ($matches as $match) {
                    $challenges[] = array(
                        'concern' => trim($match[1]),
                        'response' => trim($match[2]),
                    );
                }

                if (count($challenges) >= 2) {
                    return array_slice($challenges, 0, 3);
                }
            }
        }

        // Template fallback
        return array(
            array(
                'concern' => 'Limited direct experience in this specific industry segment',
                'response' => 'Emphasize transferable skills and quick learning ability. Highlight relevant adjacent experience and genuine enthusiasm to grow in this area.'
            ),
            array(
                'concern' => 'May be overqualified or flight risk',
                'response' => 'Articulate specific reasons for interest in this role and company. Show how it fits your long-term career goals.'
            ),
            array(
                'concern' => 'Cultural or team fit uncertainty',
                'response' => 'Research company values and culture. Prepare specific examples that demonstrate alignment with their working style.'
            ),
        );
    }

    /**
     * Render interview prep preview HTML
     */
    private function render_interview_prep_preview($content, $job_data) {
        $design = new SFFC_Application_Pack_Design_System();

        ob_start();
        ?>
        <style>
            <?php echo $design::get_preview_css(); ?>
            .sffc-interview-prep { font-size: 10pt; }
            .sffc-interview-prep .prep-header {
                background: linear-gradient(135deg, var(--app-pack-primary) 0%, var(--app-pack-primary-light) 100%);
                color: white;
                padding: 24px;
                margin: -40px -40px 24px -40px;
                border-radius: 0;
            }
            .sffc-interview-prep .prep-header h1 {
                color: white;
                margin: 0 0 4px 0;
                font-size: 16pt;
            }
            .sffc-interview-prep .prep-header .subtitle {
                color: rgba(255,255,255,0.85);
                margin: 0;
            }
            .sffc-interview-prep .section-title {
                color: var(--app-pack-primary);
                font-size: 11pt;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 1px;
                border-bottom: 2px solid var(--app-pack-secondary);
                padding-bottom: 6px;
                margin: 24px 0 12px 0;
            }
            .sffc-interview-prep .overview-box {
                background: var(--app-pack-background-alt);
                border-left: 3px solid var(--app-pack-secondary);
                padding: 16px;
                margin: 12px 0;
                line-height: 1.6;
            }
            .sffc-interview-prep .facts-grid {
                display: grid;
                grid-template-columns: repeat(5, 1fr);
                gap: 12px;
                margin: 12px 0;
            }
            .sffc-interview-prep .fact-item {
                text-align: center;
                padding: 12px 8px;
                background: var(--app-pack-background-alt);
                border-radius: 4px;
            }
            .sffc-interview-prep .fact-label {
                font-size: 8pt;
                color: var(--app-pack-text-light);
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .sffc-interview-prep .fact-value {
                font-size: 10pt;
                font-weight: 600;
                color: var(--app-pack-primary);
                margin-top: 4px;
            }
            .sffc-interview-prep .alignment-table {
                width: 100%;
                border-collapse: collapse;
                margin: 12px 0;
            }
            .sffc-interview-prep .alignment-table th {
                background: var(--app-pack-primary);
                color: white;
                padding: 10px 12px;
                font-size: 9pt;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .sffc-interview-prep .alignment-table td {
                padding: 10px 12px;
                border-bottom: 1px solid var(--app-pack-border);
                font-size: 9pt;
            }
            .sffc-interview-prep .alignment-table tr:nth-child(even) td {
                background: var(--app-pack-background-alt);
            }
            .sffc-interview-prep .question-item {
                margin: 16px 0;
                padding: 12px;
                background: var(--app-pack-background-alt);
                border-radius: 4px;
            }
            .sffc-interview-prep .question-text {
                font-weight: 600;
                color: var(--app-pack-text-dark);
                margin-bottom: 8px;
            }
            .sffc-interview-prep .question-guidance {
                color: var(--app-pack-text-medium);
                font-size: 9pt;
                padding-left: 12px;
                border-left: 2px solid var(--app-pack-secondary);
            }
            .sffc-interview-prep .ask-list {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                margin: 12px 0;
            }
            .sffc-interview-prep .ask-item {
                padding: 10px 12px;
                background: var(--app-pack-background-alt);
                border-radius: 4px;
                font-size: 9pt;
            }
            .sffc-interview-prep .talking-point {
                padding: 10px 14px;
                margin: 8px 0;
                background: linear-gradient(90deg, var(--app-pack-background-alt) 0%, transparent 100%);
                border-left: 3px solid var(--app-pack-secondary);
                font-size: 9pt;
            }
            .sffc-interview-prep .challenge-item {
                margin: 12px 0;
                padding: 12px;
                border: 1px solid var(--app-pack-border);
                border-radius: 4px;
            }
            .sffc-interview-prep .challenge-concern {
                color: #c05621;
                font-weight: 600;
                font-size: 9pt;
                margin-bottom: 6px;
            }
            .sffc-interview-prep .challenge-response {
                color: var(--app-pack-text-medium);
                font-size: 9pt;
            }
        </style>
        <div class="sffc-app-pack-preview sffc-interview-prep">
            <!-- Header -->
            <div class="prep-header">
                <h1>Interview Preparation Brief</h1>
                <p class="subtitle"><?php echo esc_html($job_data['title']); ?> at <?php echo esc_html($job_data['company']); ?></p>
            </div>

            <!-- Company Facts -->
            <div class="facts-grid">
                <?php foreach ($content['company_facts'] as $fact): ?>
                <div class="fact-item">
                    <div class="fact-label"><?php echo esc_html($fact['label']); ?></div>
                    <div class="fact-value"><?php echo esc_html($fact['value']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Role Overview -->
            <div class="section-title">Role Overview</div>
            <div class="overview-box">
                <?php echo esc_html($content['role_overview']); ?>
            </div>

            <!-- Experience Alignment -->
            <div class="section-title">Your Experience Alignment</div>
            <table class="alignment-table">
                <thead>
                    <tr>
                        <th style="width: 50%;">They Need</th>
                        <th style="width: 50%;">You Have</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($content['alignment'] as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row['need']); ?></td>
                        <td><?php echo esc_html($row['have']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Key Talking Points -->
            <div class="section-title">Key Talking Points</div>
            <?php foreach ($content['talking_points'] as $point): ?>
            <div class="talking-point"><?php echo esc_html($point); ?></div>
            <?php endforeach; ?>

            <!-- Anticipated Questions -->
            <div class="section-title">Anticipated Questions</div>
            <?php foreach (array_slice($content['questions'], 0, 4) as $q): ?>
            <div class="question-item">
                <div class="question-text">Q: <?php echo esc_html($q['question']); ?></div>
                <div class="question-guidance"><?php echo esc_html($q['guidance']); ?></div>
            </div>
            <?php endforeach; ?>

            <!-- Questions to Ask -->
            <div class="section-title">Questions to Ask</div>
            <div class="ask-list">
                <?php foreach ($content['questions_to_ask'] as $q): ?>
                <div class="ask-item">• <?php echo esc_html($q); ?></div>
                <?php endforeach; ?>
            </div>

            <!-- Potential Challenges -->
            <div class="section-title">Prepare For These Concerns</div>
            <?php foreach ($content['challenges'] as $challenge): ?>
            <div class="challenge-item">
                <div class="challenge-concern">⚠ <?php echo esc_html($challenge['concern']); ?></div>
                <div class="challenge-response">→ <?php echo esc_html($challenge['response']); ?></div>
            </div>
            <?php endforeach; ?>

            <div class="footer" style="margin-top: 24px;">
                Interview Prep for <?php echo esc_html($job_data['title']); ?> at <?php echo esc_html($job_data['company']); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate Company Brief
     */
    private function generate_company_brief($job_data) {
        // Get Claude API for content generation
        $claude = null;
        if (class_exists('SFFC_Claude_API_Manager')) {
            $claude = SFFC_Claude_API_Manager::get_instance();
        }

        // Build company intel content
        $content = $this->build_company_brief_content($job_data, $claude);

        // Generate preview HTML
        $preview_html = $this->render_company_brief_preview($content, $job_data);

        return array(
            'content' => $content,
            'preview_html' => $preview_html,
            'download_url' => '',
        );
    }

    /**
     * Build company intel brief content
     */
    private function build_company_brief_content($job_data, $claude = null) {
        // Get enhanced prompts manager
        $prompts = SFFC_Application_Pack_Claude_Prompts::get_instance();

        // Try comprehensive company brief generation (much better quality)
        if ($prompts->is_available()) {
            $result = $prompts->generate_company_brief($job_data);
            if ($result) {
                $parsed = $prompts->parse_company_brief($result);
                if (!empty($parsed)) {
                    // Merge parsed content with our structure
                    return array(
                        'company_profile' => array_merge(
                            $this->generate_company_profile($job_data, null), // Get basic facts
                            array('overview' => $parsed['company_overview'] ?? '')
                        ),
                        'industry_position' => $parsed['business_model_strategy'] ?? $this->generate_industry_position($job_data, $claude),
                        'business_model' => $parsed['business_model_strategy'] ?? $this->generate_business_model($job_data, $claude),
                        'culture_values' => $parsed['culture_values'] ?? $this->generate_culture_values($job_data, $claude),
                        'recent_developments' => $parsed['recent_news'] ?? $this->generate_recent_developments($job_data, $claude),
                        'leadership' => $parsed['key_people'] ?? $this->generate_leadership_info($job_data, $claude),
                        'competitors' => $parsed['competitive_landscape'] ?? $this->generate_competitor_landscape($job_data, $claude),
                        'interview_intel' => $parsed['interview_intelligence'] ?? $this->generate_interview_intel($job_data, $claude),
                        'talking_points' => $parsed['talking_points'] ?? array(),
                    );
                }
            }
        }

        // Fallback to individual section generation
        $content = array(
            'company_profile' => $this->generate_company_profile($job_data, $claude),
            'industry_position' => $this->generate_industry_position($job_data, $claude),
            'business_model' => $this->generate_business_model($job_data, $claude),
            'culture_values' => $this->generate_culture_values($job_data, $claude),
            'recent_developments' => $this->generate_recent_developments($job_data, $claude),
            'leadership' => $this->generate_leadership_info($job_data, $claude),
            'competitors' => $this->generate_competitor_landscape($job_data, $claude),
            'interview_intel' => $this->generate_interview_intel($job_data, $claude),
        );

        return $content;
    }

    /**
     * Generate company profile section
     */
    private function generate_company_profile($job_data, $claude = null) {
        $profile = array(
            'name' => $job_data['company'],
            'industry' => $job_data['industry'] ?? 'Financial Services',
            'location' => $job_data['location'] ?? 'Not specified',
            'type' => $this->infer_company_type($job_data),
            'size' => $this->infer_company_size($job_data),
        );

        // Generate overview using Claude
        if ($claude && $claude->is_available()) {
            $prompt = sprintf(
                "Write a brief 2-3 sentence overview of %s as a company in the %s industry.
Focus on: what they do, their market position, and reputation.
If you don't have specific information, provide a plausible general description based on the industry.
Keep it factual and professional. Max 50 words.",
                $job_data['company'],
                $job_data['industry'] ?? 'financial services'
            );

            $result = $claude->call_api($prompt, array(
                'mode' => 'cv_tailoring',
                'max_tokens' => 120,
                'temperature' => 0.6,
            ));

            if (isset($result['content'][0]['text'])) {
                $profile['overview'] = trim($result['content'][0]['text']);
            }
        }

        if (empty($profile['overview'])) {
            $profile['overview'] = sprintf(
                "%s is a %s operating in the %s sector. The company is known for its commitment to excellence and professional standards in the industry.",
                $job_data['company'],
                $profile['type'],
                $profile['industry']
            );
        }

        return $profile;
    }

    /**
     * Infer company type from job data
     */
    private function infer_company_type($job_data) {
        $company = strtolower($job_data['company'] ?? '');
        $industry = strtolower($job_data['industry'] ?? '');

        if (strpos($company, 'bank') !== false || strpos($industry, 'bank') !== false) {
            return 'Financial Institution';
        } elseif (strpos($company, 'capital') !== false || strpos($industry, 'private equity') !== false) {
            return 'Investment Firm';
        } elseif (strpos($company, 'venture') !== false || strpos($industry, 'venture') !== false) {
            return 'Venture Capital Firm';
        } elseif (strpos($company, 'asset') !== false || strpos($industry, 'asset management') !== false) {
            return 'Asset Manager';
        } elseif (strpos($industry, 'consulting') !== false) {
            return 'Consulting Firm';
        } elseif (strpos($industry, 'hedge') !== false) {
            return 'Hedge Fund';
        }

        return 'Financial Services Firm';
    }

    /**
     * Infer company size from job data
     */
    private function infer_company_size($job_data) {
        $company = strtolower($job_data['company'] ?? '');

        // Check for known large institutions
        $large_indicators = array('jpmorgan', 'goldman', 'morgan stanley', 'blackrock', 'blackstone', 'kkr', 'carlyle', 'apollo', 'citadel', 'bridgewater');
        foreach ($large_indicators as $indicator) {
            if (strpos($company, $indicator) !== false) {
                return 'Large Enterprise (1000+ employees)';
            }
        }

        // Default based on typical PE/finance firm size
        if (strpos(strtolower($job_data['industry'] ?? ''), 'private equity') !== false) {
            return 'Mid-size (50-500 employees)';
        }

        return 'Information not available';
    }

    /**
     * Generate industry position analysis
     */
    private function generate_industry_position($job_data, $claude = null) {
        if ($claude && $claude->is_available()) {
            $prompt = sprintf(
                "Describe %s's position in the %s industry in 3-4 bullet points.
Include:
- Market position/tier
- Key strengths or differentiators
- Target clients/market segment
- Geographic focus

FORMAT:
• [point]

Be specific but if unknown, provide educated assessment based on industry norms.
Max 4 bullets, each under 20 words.",
                $job_data['company'],
                $job_data['industry'] ?? 'financial services'
            );

            $result = $claude->call_api($prompt, array(
                'mode' => 'cv_tailoring',
                'max_tokens' => 250,
                'temperature' => 0.7,
            ));

            if (isset($result['content'][0]['text'])) {
                $text = $result['content'][0]['text'];
                preg_match_all('/[•\-\*]\s*(.+?)(?=\n[•\-\*]|\n\n|$)/s', $text, $matches);
                if (!empty($matches[1]) && count($matches[1]) >= 2) {
                    return array_map('trim', array_slice($matches[1], 0, 4));
                }
            }
        }

        // Template fallback
        $industry = $job_data['industry'] ?? 'Financial Services';
        return array(
            "Established player in the $industry sector",
            "Focus on delivering value to institutional and/or high-net-worth clients",
            "Competitive positioning based on expertise and track record",
            "Active in key financial markets globally",
        );
    }

    /**
     * Generate business model insights
     */
    private function generate_business_model($job_data, $claude = null) {
        if ($claude && $claude->is_available()) {
            $prompt = sprintf(
                "Explain the likely business model of %s, a %s company, in 2-3 sentences.
Cover: How they make money, key revenue streams, client types.
If specific info unknown, provide typical model for this type of firm.
Max 60 words, professional tone.",
                $job_data['company'],
                $job_data['industry'] ?? 'financial services'
            );

            $result = $claude->call_api($prompt, array(
                'mode' => 'cv_tailoring',
                'max_tokens' => 150,
                'temperature' => 0.6,
            ));

            if (isset($result['content'][0]['text'])) {
                return trim($result['content'][0]['text']);
            }
        }

        // Template fallback based on industry
        $industry = strtolower($job_data['industry'] ?? '');

        if (strpos($industry, 'private equity') !== false) {
            return "Revenue primarily from management fees (typically 1.5-2% of AUM) and carried interest (typically 20% of profits above hurdle rate). Focus on acquiring, improving, and exiting portfolio companies over 3-7 year holding periods.";
        } elseif (strpos($industry, 'asset management') !== false) {
            return "Revenue driven by management fees based on assets under management (AUM), with potential performance fees. Focus on delivering consistent returns across various investment strategies and asset classes.";
        } elseif (strpos($industry, 'investment banking') !== false) {
            return "Revenue from advisory fees (M&A, restructuring), underwriting fees (debt and equity offerings), and trading/market-making activities. Relationship-driven business with focus on large corporate and institutional clients.";
        }

        return "Revenue generated through professional services, management fees, and performance-based compensation. Focus on delivering value to clients through expertise, market access, and transaction execution.";
    }

    /**
     * Generate culture and values insights
     */
    private function generate_culture_values($job_data, $claude = null) {
        if ($claude && $claude->is_available()) {
            $prompt = sprintf(
                "Describe the likely workplace culture at %s (%s industry) in 4 bullet points.
Include:
- Work style/pace
- Team dynamics
- Professional development
- Work-life considerations

FORMAT:
• [point]

Be realistic about finance industry expectations. Max 4 bullets, each under 15 words.",
                $job_data['company'],
                $job_data['industry'] ?? 'financial services'
            );

            $result = $claude->call_api($prompt, array(
                'mode' => 'cv_tailoring',
                'max_tokens' => 200,
                'temperature' => 0.7,
            ));

            if (isset($result['content'][0]['text'])) {
                $text = $result['content'][0]['text'];
                preg_match_all('/[•\-\*]\s*(.+?)(?=\n[•\-\*]|\n\n|$)/s', $text, $matches);
                if (!empty($matches[1]) && count($matches[1]) >= 2) {
                    return array_map('trim', array_slice($matches[1], 0, 4));
                }
            }
        }

        // Template fallback
        return array(
            "Fast-paced, results-oriented environment with high performance expectations",
            "Collaborative team culture with emphasis on mentorship and knowledge sharing",
            "Strong focus on professional development and career progression",
            "Demanding hours during peak periods; rewards high performers generously",
        );
    }

    /**
     * Generate recent developments/news
     */
    private function generate_recent_developments($job_data, $claude = null) {
        if ($claude && $claude->is_available()) {
            $prompt = sprintf(
                "List 3 types of recent developments a candidate should research about %s before an interview.
These should be realistic topics to look up, not specific news items.

FORMAT:
• [topic to research]

Examples: Recent deals, leadership changes, strategic initiatives, market expansion, new products.
Make them specific to %s industry.",
                $job_data['company'],
                $job_data['industry'] ?? 'financial services'
            );

            $result = $claude->call_api($prompt, array(
                'mode' => 'cv_tailoring',
                'max_tokens' => 200,
                'temperature' => 0.7,
            ));

            if (isset($result['content'][0]['text'])) {
                $text = $result['content'][0]['text'];
                preg_match_all('/[•\-\*]\s*(.+?)(?=\n[•\-\*]|\n\n|$)/s', $text, $matches);
                if (!empty($matches[1]) && count($matches[1]) >= 2) {
                    return array(
                        'research_topics' => array_map('trim', array_slice($matches[1], 0, 4)),
                        'note' => 'Research these topics before your interview for current information.',
                    );
                }
            }
        }

        // Template fallback
        $industry = $job_data['industry'] ?? 'financial services';
        return array(
            'research_topics' => array(
                "Recent transactions, deals, or investments by {$job_data['company']}",
                "Any recent leadership appointments or organizational changes",
                "New fund launches, products, or strategic initiatives",
                "Industry awards, rankings, or media coverage",
            ),
            'note' => 'Research these topics before your interview for current information.',
        );
    }

    /**
     * Generate leadership information
     */
    private function generate_leadership_info($job_data, $claude = null) {
        return array(
            'note' => "Research key leadership before your interview",
            'roles_to_research' => array(
                array('title' => 'CEO / Managing Partner', 'why' => 'Sets overall firm strategy and culture'),
                array('title' => 'Head of ' . ($job_data['department'] ?? 'your target division'), 'why' => 'Direct influence on your team'),
                array('title' => 'Chief Investment Officer', 'why' => 'Drives investment philosophy'),
                array('title' => 'Your potential direct manager', 'why' => 'Day-to-day working relationship'),
            ),
            'research_tips' => array(
                "Check LinkedIn for background and career trajectory",
                "Look for recent interviews, podcasts, or conference appearances",
                "Note any shared connections or alma maters",
                "Understand their investment philosophy or management style",
            ),
        );
    }

    /**
     * Generate competitor landscape
     */
    private function generate_competitor_landscape($job_data, $claude = null) {
        if ($claude && $claude->is_available()) {
            $prompt = sprintf(
                "List 4-5 likely competitors of %s in the %s space.
For each, provide the company name and a brief differentiator (5-8 words).

FORMAT:
• [Competitor Name]: [brief differentiator]

Focus on direct competitors in same market segment.",
                $job_data['company'],
                $job_data['industry'] ?? 'financial services'
            );

            $result = $claude->call_api($prompt, array(
                'mode' => 'cv_tailoring',
                'max_tokens' => 250,
                'temperature' => 0.7,
            ));

            if (isset($result['content'][0]['text'])) {
                $text = $result['content'][0]['text'];
                $competitors = array();
                preg_match_all('/[•\-\*]\s*([^:]+):\s*(.+?)(?=\n|$)/s', $text, $matches, PREG_SET_ORDER);

                foreach ($matches as $match) {
                    $competitors[] = array(
                        'name' => trim($match[1]),
                        'differentiator' => trim($match[2]),
                    );
                }

                if (count($competitors) >= 3) {
                    return array_slice($competitors, 0, 5);
                }
            }
        }

        // Template fallback based on industry
        $industry = strtolower($job_data['industry'] ?? '');

        if (strpos($industry, 'private equity') !== false) {
            return array(
                array('name' => 'Blackstone', 'differentiator' => 'Largest alternative asset manager globally'),
                array('name' => 'KKR', 'differentiator' => 'Pioneer in leveraged buyouts'),
                array('name' => 'Carlyle Group', 'differentiator' => 'Strong government and defense sector expertise'),
                array('name' => 'Apollo Global', 'differentiator' => 'Credit-focused with distressed expertise'),
                array('name' => 'TPG', 'differentiator' => 'Growth equity and impact investing focus'),
            );
        } elseif (strpos($industry, 'investment banking') !== false) {
            return array(
                array('name' => 'Goldman Sachs', 'differentiator' => 'Premier M&A advisory and trading'),
                array('name' => 'Morgan Stanley', 'differentiator' => 'Wealth management integration strength'),
                array('name' => 'JPMorgan', 'differentiator' => 'Full-service with massive balance sheet'),
                array('name' => 'Bank of America', 'differentiator' => 'Strong corporate lending relationships'),
                array('name' => 'Citi', 'differentiator' => 'Global footprint and emerging markets'),
            );
        }

        return array(
            array('name' => 'Major Industry Player A', 'differentiator' => 'Market leader with broad capabilities'),
            array('name' => 'Specialized Competitor B', 'differentiator' => 'Niche expertise in specific segments'),
            array('name' => 'Growing Challenger C', 'differentiator' => 'Innovative approach and technology focus'),
            array('name' => 'Regional Player D', 'differentiator' => 'Strong local market presence'),
        );
    }

    /**
     * Generate interview intel - things to mention/reference
     */
    private function generate_interview_intel($job_data, $claude = null) {
        if ($claude && $claude->is_available()) {
            $prompt = sprintf(
                "Provide 5 specific things a candidate should research and potentially reference in an interview at %s for a %s role.

These should be concrete, actionable items that show preparation.

FORMAT:
• [specific thing to research/reference]

Make them specific to this company and role type.",
                $job_data['company'],
                $job_data['title']
            );

            $result = $claude->call_api($prompt, array(
                'mode' => 'cv_tailoring',
                'max_tokens' => 300,
                'temperature' => 0.7,
            ));

            if (isset($result['content'][0]['text'])) {
                $text = $result['content'][0]['text'];
                preg_match_all('/[•\-\*]\s*(.+?)(?=\n[•\-\*]|\n\n|$)/s', $text, $matches);
                if (!empty($matches[1]) && count($matches[1]) >= 3) {
                    return array_map('trim', array_slice($matches[1], 0, 5));
                }
            }
        }

        // Template fallback
        return array(
            "Research {$job_data['company']}'s most notable recent transaction or deal",
            "Understand their investment thesis or strategic focus areas",
            "Know key metrics: AUM, fund size, portfolio company count",
            "Familiarize yourself with their most successful investments/exits",
            "Identify any unique aspects of their culture or approach",
        );
    }

    /**
     * Render company brief preview HTML
     */
    private function render_company_brief_preview($content, $job_data) {
        $design = new SFFC_Application_Pack_Design_System();

        ob_start();
        ?>
        <style>
            <?php echo $design::get_preview_css(); ?>
            .sffc-company-brief { font-size: 10pt; }
            .sffc-company-brief .brief-header {
                background: linear-gradient(135deg, var(--app-pack-primary) 0%, var(--app-pack-primary-light) 100%);
                color: white;
                padding: 24px;
                margin: -40px -40px 24px -40px;
            }
            .sffc-company-brief .brief-header h1 {
                color: white;
                margin: 0 0 4px 0;
                font-size: 16pt;
            }
            .sffc-company-brief .brief-header .subtitle {
                color: rgba(255,255,255,0.85);
                margin: 0;
            }
            .sffc-company-brief .section-title {
                color: var(--app-pack-primary);
                font-size: 11pt;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 1px;
                border-bottom: 2px solid var(--app-pack-secondary);
                padding-bottom: 6px;
                margin: 24px 0 12px 0;
            }
            .sffc-company-brief .profile-grid {
                display: grid;
                grid-template-columns: repeat(5, 1fr);
                gap: 8px;
                margin: 12px 0;
            }
            .sffc-company-brief .profile-item {
                text-align: center;
                padding: 10px 6px;
                background: var(--app-pack-background-alt);
                border-radius: 4px;
            }
            .sffc-company-brief .profile-label {
                font-size: 8pt;
                color: var(--app-pack-text-light);
                text-transform: uppercase;
            }
            .sffc-company-brief .profile-value {
                font-size: 9pt;
                font-weight: 600;
                color: var(--app-pack-primary);
                margin-top: 4px;
            }
            .sffc-company-brief .overview-text {
                background: var(--app-pack-background-alt);
                border-left: 3px solid var(--app-pack-secondary);
                padding: 14px 16px;
                margin: 12px 0;
                line-height: 1.6;
                font-size: 10pt;
            }
            .sffc-company-brief .bullet-list {
                margin: 8px 0;
                padding-left: 0;
                list-style: none;
            }
            .sffc-company-brief .bullet-list li {
                padding: 6px 0 6px 20px;
                position: relative;
                font-size: 9pt;
            }
            .sffc-company-brief .bullet-list li::before {
                content: "•";
                color: var(--app-pack-secondary);
                font-weight: bold;
                position: absolute;
                left: 0;
            }
            .sffc-company-brief .copetitor-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                margin: 12px 0;
            }
            .sffc-company-brief .copetitor-item {
                padding: 10px 12px;
                background: var(--app-pack-background-alt);
                border-radius: 4px;
            }
            .sffc-company-brief .copetitor-name {
                font-weight: 600;
                color: var(--app-pack-primary);
                font-size: 9pt;
            }
            .sffc-company-brief .copetitor-diff {
                color: var(--app-pack-text-medium);
                font-size: 8pt;
                margin-top: 2px;
            }
            .sffc-company-brief .research-box {
                background: #fff9e6;
                border: 1px solid var(--app-pack-secondary);
                border-radius: 4px;
                padding: 12px 16px;
                margin: 12px 0;
            }
            .sffc-company-brief .research-title {
                color: var(--app-pack-secondary);
                font-weight: 600;
                font-size: 9pt;
                margin-bottom: 8px;
            }
            .sffc-company-brief .leadership-item {
                display: flex;
                justify-content: space-between;
                padding: 8px 12px;
                background: var(--app-pack-background-alt);
                margin: 4px 0;
                border-radius: 4px;
            }
            .sffc-company-brief .leadership-title {
                font-weight: 600;
                font-size: 9pt;
            }
            .sffc-company-brief .leadership-why {
                color: var(--app-pack-text-medium);
                font-size: 8pt;
            }
            .sffc-company-brief .intel-item {
                padding: 8px 12px;
                margin: 4px 0;
                background: linear-gradient(90deg, var(--app-pack-background-alt) 0%, transparent 100%);
                border-left: 3px solid var(--app-pack-secondary);
                font-size: 9pt;
            }
        </style>
        <div class="sffc-app-pack-preview sffc-company-brief">
            <!-- Header -->
            <div class="brief-header">
                <h1>Company Intelligence Brief</h1>
                <p class="subtitle"><?php echo esc_html($job_data['company']); ?> | <?php echo esc_html($job_data['industry'] ?? 'Financial Services'); ?></p>
            </div>

            <!-- Company Profile -->
            <div class="profile-grid">
                <div class="profile-item">
                    <div class="profile-label">Company</div>
                    <div class="profile-value"><?php echo esc_html($content['company_profile']['name']); ?></div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">Industry</div>
                    <div class="profile-value"><?php echo esc_html($content['company_profile']['industry']); ?></div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">Type</div>
                    <div class="profile-value"><?php echo esc_html($content['company_profile']['type']); ?></div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">Location</div>
                    <div class="profile-value"><?php echo esc_html($content['company_profile']['location']); ?></div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">Size</div>
                    <div class="profile-value" style="font-size: 8pt;"><?php echo esc_html($content['company_profile']['size']); ?></div>
                </div>
            </div>

            <div class="overview-text">
                <?php echo esc_html($content['company_profile']['overview']); ?>
            </div>

            <!-- Industry Position -->
            <div class="section-title">Industry Position</div>
            <ul class="bullet-list">
                <?php foreach ($content['industry_position'] as $point): ?>
                <li><?php echo esc_html($point); ?></li>
                <?php endforeach; ?>
            </ul>

            <!-- Business Model -->
            <div class="section-title">Business Model</div>
            <div class="overview-text">
                <?php echo esc_html($content['business_model']); ?>
            </div>

            <!-- Culture & Values -->
            <div class="section-title">Culture & Values</div>
            <ul class="bullet-list">
                <?php foreach ($content['culture_values'] as $point): ?>
                <li><?php echo esc_html($point); ?></li>
                <?php endforeach; ?>
            </ul>

            <!-- Recent Developments -->
            <div class="section-title">Research Topics</div>
            <div class="research-box">
                <div class="research-title">📋 Topics to Research Before Your Interview:</div>
                <ul class="bullet-list" style="margin: 0;">
                    <?php foreach ($content['recent_developments']['research_topics'] as $topic): ?>
                    <li><?php echo esc_html($topic); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Leadership -->
            <div class="section-title">Key Leadership to Research</div>
            <?php foreach ($content['leadership']['roles_to_research'] as $role): ?>
            <div class="leadership-item">
                <span class="leadership-title"><?php echo esc_html($role['title']); ?></span>
                <span class="leadership-why"><?php echo esc_html($role['why']); ?></span>
            </div>
            <?php endforeach; ?>

            <!-- Competitors -->
            <div class="section-title">Competitive Landscape</div>
            <div class="competitor-grid">
                <?php foreach ($content['competitors'] as $competitor): ?>
                <div class="competitor-item">
                    <div class="competitor-name"><?php echo esc_html($competitor['name']); ?></div>
                    <div class="competitor-diff"><?php echo esc_html($competitor['differentiator']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Interview Intel -->
            <div class="section-title">Interview Intelligence</div>
            <p style="color: var(--app-pack-text-medium); font-size: 9pt; margin-bottom: 8px;">
                Reference these points to demonstrate preparation:
            </p>
            <?php foreach ($content['interview_intel'] as $intel): ?>
            <div class="intel-item"><?php echo esc_html($intel); ?></div>
            <?php endforeach; ?>

            <div class="footer" style="margin-top: 24px;">
                Company Intel Brief for <?php echo esc_html($job_data['company']); ?> | Prepared for Interview
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate Full Pack (all documents)
     */
    private function generate_full_pack($job_data, $user_profile) {
        // Generate all individual documents
        $cv_result = $this->generate_cv($job_data, $user_profile);
        $cover_letter_result = $this->generate_cover_letter($job_data, $user_profile);
        $interview_prep_result = $this->generate_interview_prep($job_data, $user_profile);
        $company_brief_result = $this->generate_company_brief($job_data);
        $networking_result = $this->generate_networking_messages($job_data, $user_profile);

        // Bundle all content
        $content = array(
            'cv' => $cv_result['content'],
            'cover_letter' => $cover_letter_result['content'],
            'interview_prep' => $interview_prep_result['content'],
            'company_brief' => $company_brief_result['content'],
            'networking' => $networking_result['content'],
        );

        // Generate preview HTML
        $preview_html = $this->render_full_pack_preview($content, $job_data, $user_profile);

        return array(
            'content' => $content,
            'preview_html' => $preview_html,
            'download_url' => '',
        );
    }

    /**
     * Render full pack preview HTML
     */
    private function render_full_pack_preview($content, $job_data, $user_profile) {
        $design = new SFFC_Application_Pack_Design_System();

        ob_start();
        ?>
        <style>
            <?php echo $design::get_preview_css(); ?>
            .sffc-full-pack { font-size: 10pt; }
            .sffc-full-pack .pack-header {
                background: linear-gradient(135deg, var(--app-pack-primary) 0%, var(--app-pack-primary-light) 100%);
                color: white;
                padding: 32px;
                margin: -40px -40px 24px -40px;
                text-align: center;
            }
            .sffc-full-pack .pack-header h1 {
                color: white;
                margin: 0 0 8px 0;
                font-size: 20pt;
            }
            .sffc-full-pack .pack-header .subtitle {
                color: rgba(255,255,255,0.9);
                margin: 0;
                font-size: 11pt;
            }
            .sffc-full-pack .pack-summary {
                display: grid;
                grid-template-columns: repeat(5, 1fr);
                gap: 12px;
                margin: 24px 0;
            }
            .sffc-full-pack .pack-item {
                text-align: center;
                padding: 16px 8px;
                background: var(--app-pack-background-alt);
                border-radius: 8px;
                border: 2px solid transparent;
                transition: all 0.2s;
            }
            .sffc-full-pack .pack-item:hover {
                border-color: var(--app-pack-secondary);
            }
            .sffc-full-pack .pack-item-icon {
                font-size: 24px;
                margin-bottom: 8px;
            }
            .sffc-full-pack .pack-item-name {
                font-weight: 600;
                color: var(--app-pack-primary);
                font-size: 9pt;
            }
            .sffc-full-pack .pack-item-desc {
                color: var(--app-pack-text-medium);
                font-size: 8pt;
                margin-top: 4px;
            }
            .sffc-full-pack .pack-item-check {
                color: #276749;
                font-size: 16px;
                margin-top: 8px;
            }
            .sffc-full-pack .section-preview {
                margin: 24px 0;
                border: 1px solid var(--app-pack-border);
                border-radius: 8px;
                overflow: hidden;
            }
            .sffc-full-pack .section-header {
                background: var(--app-pack-background-alt);
                padding: 12px 16px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid var(--app-pack-border);
            }
            .sffc-full-pack .section-title {
                font-weight: 600;
                color: var(--app-pack-primary);
                margin: 0;
                border: none;
                padding: 0;
            }
            .sffc-full-pack .section-badge {
                background: var(--app-pack-secondary);
                color: white;
                padding: 2px 8px;
                border-radius: 4px;
                font-size: 8pt;
                font-weight: 600;
            }
            .sffc-full-pack .section-body {
                padding: 16px;
                max-height: 200px;
                overflow-y: auto;
            }
            .sffc-full-pack .cv-preview-mini {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 16px;
            }
            .sffc-full-pack .cv-section {
                font-size: 9pt;
            }
            .sffc-full-pack .cv-section h4 {
                color: var(--app-pack-primary);
                font-size: 9pt;
                margin: 0 0 8px 0;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .sffc-full-pack .cv-section ul {
                margin: 0;
                padding-left: 16px;
            }
            .sffc-full-pack .cv-section li {
                margin: 2px 0;
            }
            .sffc-full-pack .value-box {
                background: linear-gradient(90deg, #fff9e6 0%, var(--app-pack-background) 100%);
                border: 1px solid var(--app-pack-secondary);
                border-radius: 8px;
                padding: 20px;
                margin: 24px 0;
                text-align: center;
            }
            .sffc-full-pack .value-title {
                font-weight: 600;
                color: var(--app-pack-primary);
                font-size: 12pt;
                margin-bottom: 8px;
            }
            .sffc-full-pack .value-list {
                display: flex;
                justify-content: center;
                gap: 24px;
                flex-wrap: wrap;
                margin-top: 12px;
            }
            .sffc-full-pack .value-item {
                display: flex;
                align-items: center;
                gap: 6px;
                font-size: 9pt;
                color: var(--app-pack-text-dark);
            }
            .sffc-full-pack .value-check {
                color: #276749;
            }
        </style>
        <div class="sffc-app-pack-preview sffc-full-pack">
            <!-- Header -->
            <div class="pack-header">
                <h1>Complete Application Pack</h1>
                <p class="subtitle"><?php echo esc_html($job_data['title']); ?> at <?php echo esc_html($job_data['company']); ?></p>
            </div>

            <!-- Pack Summary -->
            <div class="pack-summary">
                <div class="pack-item">
                    <div class="pack-item-icon">📄</div>
                    <div class="pack-item-name">Tailored CV</div>
                    <div class="pack-item-desc">PDF & DOCX</div>
                    <div class="pack-item-check">✓</div>
                </div>
                <div class="pack-item">
                    <div class="pack-item-icon">✉️</div>
                    <div class="pack-item-name">Cover Letter</div>
                    <div class="pack-item-desc">PDF & DOCX</div>
                    <div class="pack-item-check">✓</div>
                </div>
                <div class="pack-item">
                    <div class="pack-item-icon">🎯</div>
                    <div class="pack-item-name">Interview Prep</div>
                    <div class="pack-item-desc">PDF Guide</div>
                    <div class="pack-item-check">✓</div>
                </div>
                <div class="pack-item">
                    <div class="pack-item-icon">🏢</div>
                    <div class="pack-item-name">Company Intel</div>
                    <div class="pack-item-desc">PDF Brief</div>
                    <div class="pack-item-check">✓</div>
                </div>
                <div class="pack-item">
                    <div class="pack-item-icon">💬</div>
                    <div class="pack-item-name">Networking</div>
                    <div class="pack-item-desc">5 Templates</div>
                    <div class="pack-item-check">✓</div>
                </div>
            </div>

            <!-- Value Box -->
            <div class="value-box">
                <div class="value-title">Everything You Need to Land This Role</div>
                <div class="value-list">
                    <div class="value-item"><span class="value-check">✓</span> ATS-optimized CV</div>
                    <div class="value-item"><span class="value-check">✓</span> Personalized cover letter</div>
                    <div class="value-item"><span class="value-check">✓</span> Interview Q&A prep</div>
                    <div class="value-item"><span class="value-check">✓</span> Company research brief</div>
                    <div class="value-item"><span class="value-check">✓</span> Ready-to-send networking messages</div>
                </div>
            </div>

            <!-- CV Preview -->
            <div class="section-preview">
                <div class="section-header">
                    <span class="section-title">Tailored CV Preview</span>
                    <span class="section-badge">PDF + DOCX</span>
                </div>
                <div class="section-body">
                    <div class="cv-preview-mini">
                        <div class="cv-section">
                            <h4>Executive Summary</h4>
                            <p style="font-size: 9pt; color: var(--app-pack-text-medium); margin: 0;">
                                <?php echo esc_html(substr($content['cv']['executive_summary'] ?? 'AI-generated executive summary tailored to the role...', 0, 200)); ?>...
                            </p>
                        </div>
                        <div class="cv-section">
                            <h4>Key Qualifications</h4>
                            <ul>
                                <?php
                                $quals = array_slice($content['cv']['key_qualifications'] ?? array(), 0, 3);
                                foreach ($quals as $qual):
                                ?>
                                <li><?php echo esc_html(substr($qual, 0, 50)); ?>...</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cover Letter Preview -->
            <div class="section-preview">
                <div class="section-header">
                    <span class="section-title">Cover Letter Preview</span>
                    <span class="section-badge">PDF + DOCX</span>
                </div>
                <div class="section-body">
                    <p style="font-size: 9pt; color: var(--app-pack-text-medium); margin: 0; font-style: italic;">
                        "<?php
                        $first_para = $content['cover_letter']['paragraphs'][0] ?? 'Opening paragraph introducing yourself and expressing interest...';
                        echo esc_html(substr($first_para, 0, 250));
                        ?>..."
                    </p>
                </div>
            </div>

            <!-- Interview Prep Preview -->
            <div class="section-preview">
                <div class="section-header">
                    <span class="section-title">Interview Prep Sheet</span>
                    <span class="section-badge">PDF</span>
                </div>
                <div class="section-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="cv-section">
                            <h4>Anticipated Questions</h4>
                            <ul>
                                <?php
                                $questions = array_slice($content['interview_prep']['questions'] ?? array(), 0, 2);
                                foreach ($questions as $q):
                                ?>
                                <li><?php echo esc_html(substr($q['question'] ?? '', 0, 45)); ?>...</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="cv-section">
                            <h4>Your Talking Points</h4>
                            <ul>
                                <?php
                                $points = array_slice($content['interview_prep']['talking_points'] ?? array(), 0, 2);
                                foreach ($points as $point):
                                ?>
                                <li><?php echo esc_html(substr($point, 0, 45)); ?>...</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Company Brief Preview -->
            <div class="section-preview">
                <div class="section-header">
                    <span class="section-title">Company Intelligence Brief</span>
                    <span class="section-badge">PDF</span>
                </div>
                <div class="section-body">
                    <p style="font-size: 9pt; color: var(--app-pack-text-medium); margin: 0 0 8px 0;">
                        <?php echo esc_html($content['company_brief']['company_profile']['overview'] ?? 'Company overview and market position...'); ?>
                    </p>
                    <p style="font-size: 8pt; color: var(--app-pack-text-light); margin: 0;">
                        <strong>Includes:</strong> Industry position, business model, culture, competitors, research topics, leadership info
                    </p>
                </div>
            </div>

            <!-- Networking Preview -->
            <div class="section-preview">
                <div class="section-header">
                    <span class="section-title">Networking Messages</span>
                    <span class="section-badge">5 TEMPLATES</span>
                </div>
                <div class="section-body">
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <span style="background: var(--app-pack-background-alt); padding: 4px 10px; border-radius: 4px; font-size: 8pt;">LinkedIn Connect</span>
                        <span style="background: var(--app-pack-background-alt); padding: 4px 10px; border-radius: 4px; font-size: 8pt;">LinkedIn InMail</span>
                        <span style="background: var(--app-pack-background-alt); padding: 4px 10px; border-radius: 4px; font-size: 8pt;">Email to Hiring Manager</span>
                        <span style="background: var(--app-pack-background-alt); padding: 4px 10px; border-radius: 4px; font-size: 8pt;">Email to Recruiter</span>
                        <span style="background: var(--app-pack-background-alt); padding: 4px 10px; border-radius: 4px; font-size: 8pt;">Referral Request</span>
                    </div>
                </div>
            </div>

            <div class="footer" style="margin-top: 24px; text-align: center;">
                <p style="margin: 0; font-size: 9pt;">Download as a single ZIP file containing all documents</p>
                <p style="margin: 4px 0 0 0; font-size: 8pt; color: var(--app-pack-text-light);">
                    Prepared for <?php echo esc_html($user_profile['name']); ?> | <?php echo esc_html($job_data['title']); ?> at <?php echo esc_html($job_data['company']); ?>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate ZIP file with all pack documents
     */
    public function generate_full_pack_zip($content, $job_data, $user_profile) {
        $upload_dir = wp_upload_dir();
        $pack_dir = $upload_dir['basedir'] . '/sffc-application-packs';

        if (!file_exists($pack_dir)) {
            wp_mkdir_p($pack_dir);
            file_put_contents($pack_dir . '/.htaccess', 'deny from all');
        }

        // Create temporary directory for files
        $temp_dir = $pack_dir . '/temp-' . uniqid();
        wp_mkdir_p($temp_dir);

        $pdf_gen = new SFFC_Application_Pack_PDF_Generator();
        $docx_gen = new SFFC_Application_Pack_DOCX_Generator();

        $files_to_zip = array();
        $user_name = sanitize_file_name($user_profile['name']);
        $company = sanitize_file_name($job_data['company']);

        try {
            // Generate CV (PDF + DOCX)
            $cv_pdf = $pdf_gen->generate_cv($content['cv'], $job_data, $user_profile['name']);
            $cv_pdf_path = $temp_dir . "/{$user_name}-CV-{$company}.pdf";
            file_put_contents($cv_pdf_path, $cv_pdf);
            $files_to_zip[] = $cv_pdf_path;

            $cv_docx = $docx_gen->generate_cv($content['cv'], $job_data, $user_profile['name']);
            $cv_docx_path = $temp_dir . "/{$user_name}-CV-{$company}.docx";
            file_put_contents($cv_docx_path, $cv_docx);
            $files_to_zip[] = $cv_docx_path;

            // Generate Cover Letter (PDF + DOCX)
            $cl_pdf = $pdf_gen->generate_cover_letter($content['cover_letter'], $job_data, $user_profile['name']);
            $cl_pdf_path = $temp_dir . "/{$user_name}-Cover-Letter-{$company}.pdf";
            file_put_contents($cl_pdf_path, $cl_pdf);
            $files_to_zip[] = $cl_pdf_path;

            $cl_docx = $docx_gen->generate_cover_letter($content['cover_letter'], $job_data, $user_profile['name']);
            $cl_docx_path = $temp_dir . "/{$user_name}-Cover-Letter-{$company}.docx";
            file_put_contents($cl_docx_path, $cl_docx);
            $files_to_zip[] = $cl_docx_path;

            // Generate Interview Prep (PDF only)
            $ip_pdf = $pdf_gen->generate_interview_prep($content['interview_prep'], $job_data);
            $ip_pdf_path = $temp_dir . "/{$user_name}-Interview-Prep-{$company}.pdf";
            file_put_contents($ip_pdf_path, $ip_pdf);
            $files_to_zip[] = $ip_pdf_path;

            // Generate Company Brief (PDF only)
            $cb_pdf = $pdf_gen->generate_company_brief($content['company_brief'], $job_data);
            $cb_pdf_path = $temp_dir . "/{$user_name}-Company-Brief-{$company}.pdf";
            file_put_contents($cb_pdf_path, $cb_pdf);
            $files_to_zip[] = $cb_pdf_path;

            // Generate Networking Messages (TXT file)
            $networking_txt = $this->generate_networking_txt($content['networking'], $job_data);
            $net_txt_path = $temp_dir . "/{$user_name}-Networking-Messages-{$company}.txt";
            file_put_contents($net_txt_path, $networking_txt);
            $files_to_zip[] = $net_txt_path;

            // Create ZIP file
            $zip_filename = "{$user_name}-Application-Pack-{$company}-" . date('Ymd') . ".zip";
            $zip_path = $pack_dir . '/' . $zip_filename;

            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                foreach ($files_to_zip as $file) {
                    $zip->addFile($file, basename($file));
                }
                $zip->close();
            } else {
                throw new Exception('Failed to create ZIP archive.');
            }

            // Clean up temp files
            foreach ($files_to_zip as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            rmdir($temp_dir);

            return array(
                'path' => $zip_path,
                'url' => $upload_dir['baseurl'] . '/sffc-application-packs/' . $zip_filename,
                'filename' => $zip_filename,
            );

        } catch (Exception $e) {
            // Clean up on error
            foreach ($files_to_zip as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            if (is_dir($temp_dir)) {
                rmdir($temp_dir);
            }
            throw $e;
        }
    }

    /**
     * Generate networking messages as TXT file
     */
    private function generate_networking_txt($networking_content, $job_data) {
        $txt = "NETWORKING MESSAGES\n";
        $txt .= "==================\n";
        $txt .= "For: {$job_data['title']} at {$job_data['company']}\n";
        $txt .= "Generated: " . date('F j, Y') . "\n\n";
        $txt .= str_repeat("=", 60) . "\n\n";

        foreach ($networking_content as $key => $msg) {
            $txt .= strtoupper($msg['title']) . "\n";
            $txt .= str_repeat("-", strlen($msg['title'])) . "\n";
            $txt .= $msg['description'] . "\n\n";

            if (!empty($msg['subject'])) {
                $txt .= "Subject: " . $msg['subject'] . "\n\n";
            }

            $txt .= $msg['message'] . "\n";

            if (isset($msg['char_limit'])) {
                $txt .= "\n[Character count: {$msg['char_count']}/{$msg['char_limit']}]\n";
            }

            $txt .= "\n" . str_repeat("=", 60) . "\n\n";
        }

        $txt .= "---\n";
        $txt .= "Generated by MENA Careers Finance Application Pack\n";

        return $txt;
    }

    /**
     * Log pack generation to database
     */
    private function log_pack_generation($user_id, $job_id, $pack_type, $result) {
        global $wpdb;

        $wpdb->insert(
            $this->table_packs,
            array(
                'user_id' => $user_id,
                'job_id' => $job_id,
                'pack_type' => $pack_type,
                'generated_content' => wp_json_encode($result['content']),
                'credits_used' => $this->pack_types[$pack_type]['credits'],
            ),
            array('%d', '%d', '%s', '%s', '%d')
        );

        return $wpdb->insert_id;
    }

    /**
     * AJAX: Preview pack
     */
    public function ajax_preview_pack() {
        check_ajax_referer('sffc_app_pack_nonce', 'nonce');

        $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
        $pack_type = isset($_POST['pack_type']) ? sanitize_text_field($_POST['pack_type']) : 'cv';

        if (!$job_id) {
            wp_send_json_error(array('message' => 'Invalid job ID.'));
        }

        $job_data = $this->get_job_data($job_id);
        $user_profile = $this->get_user_profile();

        // Generate preview without saving
        try {
            $result = $this->generate_pack($pack_type, $job_data, $user_profile);
            wp_send_json_success(array(
                'preview_html' => $result['preview_html'],
            ));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX: Download pack
     */
    public function ajax_download_pack() {
        check_ajax_referer('sffc_app_pack_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please log in.'));
        }

        $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
        $pack_type = isset($_POST['pack_type']) ? sanitize_text_field($_POST['pack_type']) : 'cv';
        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'pdf';

        if (!$job_id) {
            wp_send_json_error(array('message' => 'Invalid job ID.'));
        }

        $user_id = get_current_user_id();
        $job_data = $this->get_job_data($job_id);
        $user_profile = $this->get_user_profile($user_id);

        if (!$job_data) {
            wp_send_json_error(array('message' => 'Job not found.'));
        }

        try {
            // Generate the pack content
            $result = $this->generate_pack($pack_type, $job_data, $user_profile);

            // Generate file based on format
            $file_content = null;
            $filename = sanitize_file_name($user_profile['name'] . '-' . $pack_type . '-' . $job_data['company']);

            if ($format === 'pdf') {
                $pdf_gen = new SFFC_Application_Pack_PDF_Generator();

                switch ($pack_type) {
                    case 'cv':
                        $file_content = $pdf_gen->generate_cv($result['content'], $job_data, $user_profile['name']);
                        break;
                    case 'cover_letter':
                        $file_content = $pdf_gen->generate_cover_letter($result['content'], $job_data, $user_profile['name']);
                        break;
                    case 'interview_prep':
                        $file_content = $pdf_gen->generate_interview_prep($result['content'], $job_data);
                        break;
                    case 'company_brief':
                        $file_content = $pdf_gen->generate_company_brief($result['content'], $job_data);
                        break;
                    default:
                        $file_content = $pdf_gen->generate_cv($result['content'], $job_data, $user_profile['name']);
                }

                $filename .= '.pdf';
                $saved = $pdf_gen->save_to_file($file_content, $filename);

            } elseif ($format === 'docx') {
                $docx_gen = new SFFC_Application_Pack_DOCX_Generator();

                switch ($pack_type) {
                    case 'cv':
                        $file_content = $docx_gen->generate_cv($result['content'], $job_data, $user_profile['name']);
                        break;
                    case 'cover_letter':
                        $file_content = $docx_gen->generate_cover_letter($result['content'], $job_data, $user_profile['name']);
                        break;
                    default:
                        $file_content = $docx_gen->generate_cv($result['content'], $job_data, $user_profile['name']);
                }

                $filename .= '.docx';
                $saved = $docx_gen->save_to_file($file_content, $filename);

            } elseif ($format === 'zip' && $pack_type === 'full_pack') {
                // Generate ZIP with all documents
                $saved = $this->generate_full_pack_zip($result['content'], $job_data, $user_profile);
                $filename = $saved['filename'] ?? $filename . '.zip';

            } else {
                wp_send_json_error(array('message' => 'Unsupported format: ' . $format));
            }

            if ($saved && isset($saved['url'])) {
                // Update download count in database
                $this->increment_download_count($user_id, $job_id, $pack_type);

                wp_send_json_success(array(
                    'download_url' => $saved['url'],
                    'filename' => $filename,
                ));
            } else {
                wp_send_json_error(array('message' => 'Failed to save file.'));
            }

        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Export failed: ' . $e->getMessage()));
        }
    }

    /**
     * Increment download count
     */
    private function increment_download_count($user_id, $job_id, $pack_type) {
        global $wpdb;

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, download_count FROM {$this->table_packs}
             WHERE user_id = %d AND job_id = %d AND pack_type = %s
             ORDER BY created_at DESC LIMIT 1",
            $user_id, $job_id, $pack_type
        ));

        if ($existing) {
            $wpdb->update(
                $this->table_packs,
                array('download_count' => $existing->download_count + 1),
                array('id' => $existing->id),
                array('%d'),
                array('%d')
            );
        }
    }

    /**
     * AJAX: Get user credits
     */
    public function ajax_get_credits() {
        check_ajax_referer('sffc_app_pack_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in.'));
        }

        wp_send_json_success(array(
            'credits' => $this->get_user_credits(),
            'has_access' => $this->user_has_access(),
        ));
    }

    /**
     * AJAX: Check access
     */
    public function ajax_check_access() {
        check_ajax_referer('sffc_app_pack_nonce', 'nonce');

        wp_send_json_success(array(
            'has_access' => $this->user_has_access(),
            'tier' => $this->get_user_tier(),
            'credits' => $this->get_user_credits(),
            'upgrade_url' => $this->get_upgrade_url(),
        ));
    }
}

// Initialize
add_action('plugins_loaded', function() {
    SFFC_Application_Pack_Generator::get_instance();
});
