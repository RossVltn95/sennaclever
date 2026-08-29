<?php

/**
 * Recruiter Manager
 *
 * Registers the recruiter custom post type, taxonomies, admin UI, and templating.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Recruiter_Manager
{
    const POST_TYPE = 'sffc_recruiter';
    const FRONTEND_ROLE_OPTION = 'sffc_recruiter_frontend_role';

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomies']);
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_meta_boxes']);
        add_action('admin_menu', [$this, 'register_admin_tools']);
        add_action('admin_post_sffc_save_recruiter_role', [$this, 'handle_role_update']);
        add_action('template_redirect', [$this, 'handle_frontend_update']);
        add_action('init', [$this, 'register_shortcodes']);
        add_filter('single_template', [$this, 'maybe_override_single_template']);
        add_filter('pre_get_document_title', [$this, 'filter_document_title'], 20);
        add_filter('document_title_parts', [$this, 'filter_document_title_parts'], 20);
        add_action('wp_head', [$this, 'output_head_meta'], 5);
        add_action('init', [$this, 'maybe_seed_samples'], 20);

        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('sffc recruiter:backfill', [$this, 'cli_backfill_recruiters']);
            WP_CLI::add_command('sffc recruiter:seed', [$this, 'cli_seed_sample_recruiters']);
        }
    }

    /**
     * Register Recruiter CPT.
     */
    public function register_post_type()
    {
        $labels = [
            'name' => __('Recruiters', 'senna-finance'),
            'singular_name' => __('Recruiter', 'senna-finance'),
            'add_new' => __('Add New Recruiter', 'senna-finance'),
            'add_new_item' => __('Add New Recruiter', 'senna-finance'),
            'edit_item' => __('Edit Recruiter', 'senna-finance'),
            'new_item' => __('New Recruiter', 'senna-finance'),
            'view_item' => __('View Recruiter', 'senna-finance'),
            'search_items' => __('Search Recruiters', 'senna-finance'),
            'not_found' => __('No recruiters found', 'senna-finance'),
            'menu_name' => __('Recruiters', 'senna-finance'),
        ];

        register_post_type(self::POST_TYPE, [
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => 'recruiters', 'with_front' => false],
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-businessperson',
        ]);
    }

    /**
     * Register Recruiter taxonomies.
     */
    public function register_taxonomies()
    {
        $taxonomies = [
            'sffc_recruiter_industry' => ['singular' => __('Industry', 'senna-finance'), 'plural' => __('Industries', 'senna-finance')],
            'sffc_recruiter_role' => ['singular' => __('Role', 'senna-finance'), 'plural' => __('Roles', 'senna-finance')],
            'sffc_recruiter_function' => ['singular' => __('Function', 'senna-finance'), 'plural' => __('Functions', 'senna-finance')],
            'sffc_recruiter_region' => ['singular' => __('Region', 'senna-finance'), 'plural' => __('Regions', 'senna-finance')],
        ];

        foreach ($taxonomies as $taxonomy => $label) {
            register_taxonomy($taxonomy, self::POST_TYPE, [
                'labels' => [
                    'name' => $label['plural'],
                    'singular_name' => $label['singular'],
                    'add_new_item' => sprintf(__('Add New %s', 'senna-finance'), $label['singular']),
                    'new_item_name' => sprintf(__('New %s Name', 'senna-finance'), $label['singular']),
                ],
                'hierarchical' => true,
                'public' => true,
                'rewrite' => ['slug' => str_replace('sffc_recruiter_', '', $taxonomy)],
                'show_in_rest' => true,
                'show_admin_column' => true,
            ]);
        }
    }

    /**
     * Bootstrap recruiter job templates.
     */
    public function register_meta_boxes()
    {
        add_meta_box('sffc_recruiter_overview', __('Recruiter Overview', 'senna-finance'), [$this, 'render_overview_meta_box'], self::POST_TYPE, 'normal', 'high');
        add_meta_box('sffc_recruiter_ctas', __('Primary CTAs', 'senna-finance'), [$this, 'render_cta_meta_box'], self::POST_TYPE, 'normal', 'default');
        add_meta_box('sffc_recruiter_consultants', __('Key Consultants', 'senna-finance'), [$this, 'render_consultants_meta_box'], self::POST_TYPE, 'normal', 'default');
        add_meta_box('sffc_recruiter_services', __('Solutions & Clients', 'senna-finance'), [$this, 'render_services_meta_box'], self::POST_TYPE, 'normal', 'default');
        add_meta_box('sffc_recruiter_process', __('Search Process', 'senna-finance'), [$this, 'render_process_meta_box'], self::POST_TYPE, 'normal', 'default');
        add_meta_box('sffc_recruiter_case_studies', __('Case Studies', 'senna-finance'), [$this, 'render_case_studies_meta_box'], self::POST_TYPE, 'normal', 'default');
        add_meta_box('sffc_recruiter_testimonials', __('Client Reviews & Testimonials', 'senna-finance'), [$this, 'render_testimonials_meta_box'], self::POST_TYPE, 'normal', 'default');
        add_meta_box('sffc_recruiter_contact', __('Contact Details', 'senna-finance'), [$this, 'render_contact_meta_box'], self::POST_TYPE, 'side', 'default');
        add_meta_box('sffc_recruiter_metrics', __('Performance Metrics', 'senna-finance'), [$this, 'render_metrics_meta_box'], self::POST_TYPE, 'side', 'default');
    }

    private function get_meta($post_id, $key, $default = '')
    {
        $value = get_post_meta($post_id, $key, true);
        if ($value === '' || $value === null) {
            return $default;
        }

        return $value;
    }

    public function render_overview_meta_box($post)
    {
        wp_nonce_field('sffc_recruiter_meta', 'sffc_recruiter_meta_nonce');

        $fields = [
            'tagline' => $this->get_meta($post->ID, '_sffc_recruiter_tagline'),
            'rating' => $this->get_meta($post->ID, '_sffc_recruiter_rating'),
            'location' => $this->get_meta($post->ID, '_sffc_recruiter_location'),
            'founded' => $this->get_meta($post->ID, '_sffc_recruiter_founded'),
            'size' => $this->get_meta($post->ID, '_sffc_recruiter_size'),
            'video' => $this->get_meta($post->ID, '_sffc_recruiter_video'),
            'front_end' => $this->get_meta($post->ID, '_sffc_recruiter_frontend_edit'),
            'locations_summary' => $this->get_meta($post->ID, '_sffc_recruiter_locations_summary'),
            'role_focus' => $this->get_meta($post->ID, '_sffc_recruiter_role_focus'),
            'candidate_seniority' => $this->get_meta($post->ID, '_sffc_recruiter_candidate_seniority'),
        ];

?>
        <p>
            <label for="sffc_recruiter_tagline"><strong><?php esc_html_e('Tagline', 'senna-finance'); ?></strong></label><br>
            <input type="text" class="widefat" name="sffc_recruiter_tagline" id="sffc_recruiter_tagline" value="<?php echo esc_attr($fields['tagline']); ?>">
        </p>
        <div class="sffc-recruiter-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;">
            <?php
            $inputs = [
                'rating' => __('Rating (0-5)', 'senna-finance'),
                'location' => __('Primary Location', 'senna-finance'),
                'founded' => __('Founded Year', 'senna-finance'),
                'size' => __('Team Size', 'senna-finance'),
            ];
            foreach ($inputs as $key => $label) :
            ?>
                <p>
                    <label for="sffc_recruiter_<?php echo esc_attr($key); ?>"><strong><?php echo esc_html($label); ?></strong></label>
                    <input type="text" class="widefat" name="sffc_recruiter_<?php echo esc_attr($key); ?>" id="sffc_recruiter_<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($fields[$key]); ?>">
                </p>
            <?php endforeach; ?>
        </div>
        <p>
            <label for="sffc_recruiter_video"><strong><?php esc_html_e('Video Embed URL (optional)', 'senna-finance'); ?></strong></label>
            <input type="url" class="widefat" name="sffc_recruiter_video" id="sffc_recruiter_video" value="<?php echo esc_attr($fields['video']); ?>">
        </p>
        <p>
            <label for="sffc_recruiter_locations_summary"><strong><?php esc_html_e('Locations Covered (summary)', 'senna-finance'); ?></strong></label>
            <input type="text" class="widefat" name="sffc_recruiter_locations_summary" id="sffc_recruiter_locations_summary" value="<?php echo esc_attr($fields['locations_summary']); ?>" placeholder="<?php esc_attr_e('Example: Global | North America | Europe', 'senna-finance'); ?>">
        </p>
        <p>
            <label for="sffc_recruiter_role_focus"><strong><?php esc_html_e('Role Focus (summary)', 'senna-finance'); ?></strong></label>
            <input type="text" class="widefat" name="sffc_recruiter_role_focus" id="sffc_recruiter_role_focus" value="<?php echo esc_attr($fields['role_focus']); ?>" placeholder="<?php esc_attr_e('Example: Investment, Portfolio, Operating Partners', 'senna-finance'); ?>">
        </p>
        <p>
            <label for="sffc_recruiter_candidate_seniority"><strong><?php esc_html_e('Candidate Seniority', 'senna-finance'); ?></strong></label>
            <input type="text" class="widefat" name="sffc_recruiter_candidate_seniority" id="sffc_recruiter_candidate_seniority" value="<?php echo esc_attr($fields['candidate_seniority']); ?>" placeholder="<?php esc_attr_e('Example: Mid-market to C-suite', 'senna-finance'); ?>">
        </p>
        <p>
            <label><input type="checkbox" name="sffc_recruiter_frontend_edit" value="1" <?php checked($fields['front_end'], '1'); ?>>
                <?php esc_html_e('Allow front-end editing for approved recruiter users', 'senna-finance'); ?>
            </label>
        </p>
    <?php
    }

    public function render_cta_meta_box($post)
    {
        $ctas = [
            'jobs' => $this->get_meta($post->ID, '_sffc_recruiter_cta_jobs'),
            'cv' => $this->get_meta($post->ID, '_sffc_recruiter_cta_cv'),
            'contact' => $this->get_meta($post->ID, '_sffc_recruiter_cta_contact'),
            'call' => $this->get_meta($post->ID, '_sffc_recruiter_cta_call'),
        ];

    ?>
        <div class="sffc-recruiter-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
            <?php
            $labels = [
                'jobs' => __('View Jobs URL', 'senna-finance'),
                'cv' => __('Submit CV URL', 'senna-finance'),
                'contact' => __('Contact Recruiter URL', 'senna-finance'),
            ];
            foreach ($labels as $key => $label) :
            ?>
                <p>
                    <label for="sffc_recruiter_cta_<?php echo esc_attr($key); ?>"><strong><?php echo esc_html($label); ?></strong></label>
                    <input type="url" class="widefat" name="sffc_recruiter_cta_<?php echo esc_attr($key); ?>" id="sffc_recruiter_cta_<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($ctas[$key]); ?>">
                </p>
            <?php endforeach; ?>
        </div>
        <p>
            <label for="sffc_recruiter_cta_call"><strong><?php esc_html_e('Request a Call URL (optional)', 'senna-finance'); ?></strong></label>
            <input type="url" class="widefat" name="sffc_recruiter_cta_call" id="sffc_recruiter_cta_call" value="<?php echo esc_attr($ctas['call']); ?>">
        </p>
    <?php
    }

    public function render_consultants_meta_box($post)
    {
        $consultants = $this->get_repeater_meta($post->ID, '_sffc_recruiter_consultants');
        $this->print_repeater_assets();

        if (empty($consultants)) {
            $consultants[] = ['name' => '', 'title' => '', 'photo' => '', 'linkedin' => '', 'specialization' => ''];
        }

    ?>
        <div id="sffc-recruiter-consultants" class="sffc-admin-repeatable">
            <?php foreach ($consultants as $consultant) : ?>
                <div class="sffc-admin-repeatable__row">
                    <div class="sffc-admin-repeatable__grid">
                        <p>
                            <label><?php esc_html_e('Name', 'senna-finance'); ?></label>
                            <input type="text" name="sffc_recruiter_consultants[name][]" value="<?php echo esc_attr($consultant['name']); ?>">
                        </p>
                        <p>
                            <label><?php esc_html_e('Title', 'senna-finance'); ?></label>
                            <input type="text" name="sffc_recruiter_consultants[title][]" value="<?php echo esc_attr($consultant['title']); ?>">
                        </p>
                        <p>
                            <label><?php esc_html_e('Photo Attachment ID or URL', 'senna-finance'); ?></label>
                            <input type="text" name="sffc_recruiter_consultants[photo][]" value="<?php echo esc_attr($consultant['photo']); ?>" placeholder="<?php esc_attr_e('123 or https://media.skillfarm.co/...', 'senna-finance'); ?>">
                        </p>
                        <p>
                            <label><?php esc_html_e('LinkedIn URL', 'senna-finance'); ?></label>
                            <input type="url" name="sffc_recruiter_consultants[linkedin][]" value="<?php echo esc_url($consultant['linkedin']); ?>">
                        </p>
                        <p>
                            <label><?php esc_html_e('Specialization', 'senna-finance'); ?></label>
                            <input type="text" name="sffc_recruiter_consultants[specialization][]" value="<?php echo esc_attr($consultant['specialization']); ?>">
                        </p>
                    </div>
                    <p class="sffc-admin-repeatable__actions">
                        <button type="button" class="button-link-delete sffc-admin-row-remove"><?php esc_html_e('Remove consultant', 'senna-finance'); ?></button>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
        <p>
            <button type="button" class="button button-secondary sffc-admin-row-add" data-target="#sffc-recruiter-consultants"><?php esc_html_e('Add consultant', 'senna-finance'); ?></button>
        </p>
    <?php
    }

    public function render_services_meta_box($post)
    {
        $services = $this->get_repeater_meta($post->ID, '_sffc_recruiter_services');
        $logos = $this->get_meta($post->ID, '_sffc_recruiter_client_logos', []);
        if (!is_array($logos)) {
            $decoded = json_decode($logos, true);
            $logos = is_array($decoded) ? $decoded : [];
        }
        $this->print_repeater_assets();

        if (empty($services)) {
            $services[] = ['title' => '', 'description' => ''];
        }

?>
        <div id="sffc-recruiter-services" class="sffc-admin-repeatable">
            <?php foreach ($services as $service) : ?>
                <div class="sffc-admin-repeatable__row">
                    <div class="sffc-admin-repeatable__grid">
                        <p>
                            <label><?php esc_html_e('Service Title', 'senna-finance'); ?></label>
                            <input type="text" name="sffc_recruiter_services[title][]" value="<?php echo esc_attr($service['title']); ?>">
                        </p>
                    </div>
                    <p>
                        <label><?php esc_html_e('Description', 'senna-finance'); ?></label>
                        <textarea name="sffc_recruiter_services[description][]" rows="3" class="widefat"><?php echo esc_textarea($service['description']); ?></textarea>
                    </p>
                    <p class="sffc-admin-repeatable__actions">
                        <button type="button" class="button-link-delete sffc-admin-row-remove"><?php esc_html_e('Remove service', 'senna-finance'); ?></button>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
        <p>
            <button type="button" class="button button-secondary sffc-admin-row-add" data-target="#sffc-recruiter-services"><?php esc_html_e('Add service', 'senna-finance'); ?></button>
        </p>
        <p>
            <label for="sffc_recruiter_client_logos"><strong><?php esc_html_e('Client Logo IDs (comma-separated)', 'senna-finance'); ?></strong></label>
            <input type="text" class="widefat" name="sffc_recruiter_client_logos" id="sffc_recruiter_client_logos" value="<?php echo esc_attr(implode(',', $logos)); ?>" placeholder="42,128,256">
        </p>
    <?php
    }

    public function render_process_meta_box($post)
    {
        $process = $this->get_repeater_meta($post->ID, '_sffc_recruiter_process');
        $this->print_repeater_assets();

        if (empty($process)) {
            $process[] = ['title' => '', 'description' => ''];
        }

    ?>
        <p class="description"><?php esc_html_e('Outline the steps candidates and clients experience when partnering with your agency.', 'senna-finance'); ?></p>
        <div id="sffc-recruiter-process" class="sffc-admin-repeatable">
            <?php foreach ($process as $step) : ?>
                <div class="sffc-admin-repeatable__row">
                    <div class="sffc-admin-repeatable__grid">
                        <p>
                            <label><?php esc_html_e('Step Title', 'senna-finance'); ?></label>
                            <input type="text" name="sffc_recruiter_process[title][]" value="<?php echo esc_attr($step['title']); ?>">
                        </p>
                    </div>
                    <p>
                        <label><?php esc_html_e('Description', 'senna-finance'); ?></label>
                        <textarea name="sffc_recruiter_process[description][]" rows="3" class="widefat"><?php echo esc_textarea($step['description']); ?></textarea>
                    </p>
                    <p class="sffc-admin-repeatable__actions">
                        <button type="button" class="button-link-delete sffc-admin-row-remove"><?php esc_html_e('Remove step', 'senna-finance'); ?></button>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
        <p>
            <button type="button" class="button button-secondary sffc-admin-row-add" data-target="#sffc-recruiter-process"><?php esc_html_e('Add process step', 'senna-finance'); ?></button>
        </p>
    <?php
    }

    public function render_case_studies_meta_box($post)
    {
        $cases = $this->get_repeater_meta($post->ID, '_sffc_recruiter_case_studies');
        $this->print_repeater_assets();

        if (empty($cases)) {
            $cases[] = ['title' => '', 'summary' => '', 'impact' => '', 'link' => ''];
        }

    ?>
        <p class="description"><?php esc_html_e('Highlight a few marquee searches you have delivered. These appear in the Case Studies section.', 'senna-finance'); ?></p>
        <div id="sffc-recruiter-case-studies" class="sffc-admin-repeatable">
            <?php foreach ($cases as $case) : ?>
                <div class="sffc-admin-repeatable__row">
                    <div class="sffc-admin-repeatable__grid">
                        <p>
                            <label><?php esc_html_e('Engagement Title', 'senna-finance'); ?></label>
                            <input type="text" name="sffc_recruiter_case_studies[title][]" value="<?php echo esc_attr($case['title']); ?>">
                        </p>
                        <p>
                            <label><?php esc_html_e('External Case Study URL', 'senna-finance'); ?></label>
                            <input type="url" name="sffc_recruiter_case_studies[link][]" value="<?php echo esc_attr($case['link']); ?>" placeholder="https://">
                        </p>
                    </div>
                    <p>
                        <label><?php esc_html_e('Summary', 'senna-finance'); ?></label>
                        <textarea name="sffc_recruiter_case_studies[summary][]" rows="3" class="widefat"><?php echo esc_textarea($case['summary']); ?></textarea>
                    </p>
                    <p>
                        <label><?php esc_html_e('Result / Impact', 'senna-finance'); ?></label>
                        <textarea name="sffc_recruiter_case_studies[impact][]" rows="2" class="widefat"><?php echo esc_textarea($case['impact']); ?></textarea>
                    </p>
                    <p class="sffc-admin-repeatable__actions">
                        <button type="button" class="button-link-delete sffc-admin-row-remove"><?php esc_html_e('Remove case study', 'senna-finance'); ?></button>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
        <p>
            <button type="button" class="button button-secondary sffc-admin-row-add" data-target="#sffc-recruiter-case-studies"><?php esc_html_e('Add case study', 'senna-finance'); ?></button>
        </p>
    <?php
    }

    public function render_contact_meta_box($post)
    {
        $fields = [
            'person' => $this->get_meta($post->ID, '_sffc_recruiter_contact_person'),
            'email' => $this->get_meta($post->ID, '_sffc_recruiter_contact_email'),
            'phone' => $this->get_meta($post->ID, '_sffc_recruiter_contact_phone'),
            'address' => $this->get_meta($post->ID, '_sffc_recruiter_contact_address'),
            'website' => $this->get_meta($post->ID, '_sffc_recruiter_contact_website'),
            'cta_label' => $this->get_meta($post->ID, '_sffc_recruiter_contact_cta_label'),
            'cta_url' => $this->get_meta($post->ID, '_sffc_recruiter_contact_cta_url'),
        ];

    ?>
        <p>
            <label for="sffc_recruiter_contact_person"><strong><?php esc_html_e('Primary Contact', 'senna-finance'); ?></strong></label>
            <input type="text" class="widefat" name="sffc_recruiter_contact_person" id="sffc_recruiter_contact_person" value="<?php echo esc_attr($fields['person']); ?>">
        </p>
        <p>
            <label for="sffc_recruiter_contact_email"><strong><?php esc_html_e('Contact Email', 'senna-finance'); ?></strong></label>
            <input type="email" class="widefat" name="sffc_recruiter_contact_email" id="sffc_recruiter_contact_email" value="<?php echo esc_attr($fields['email']); ?>">
        </p>
        <p>
            <label for="sffc_recruiter_contact_phone"><strong><?php esc_html_e('Contact Phone', 'senna-finance'); ?></strong></label>
            <input type="text" class="widefat" name="sffc_recruiter_contact_phone" id="sffc_recruiter_contact_phone" value="<?php echo esc_attr($fields['phone']); ?>">
        </p>
        <p>
            <label for="sffc_recruiter_contact_address"><strong><?php esc_html_e('Address / Locations', 'senna-finance'); ?></strong></label>
            <textarea name="sffc_recruiter_contact_address" id="sffc_recruiter_contact_address" rows="3" class="widefat"><?php echo esc_textarea($fields['address']); ?></textarea>
        </p>
        <p>
            <label for="sffc_recruiter_contact_website"><strong><?php esc_html_e('Website URL', 'senna-finance'); ?></strong></label>
            <input type="url" class="widefat" name="sffc_recruiter_contact_website" id="sffc_recruiter_contact_website" value="<?php echo esc_attr($fields['website']); ?>" placeholder="https://">
        </p>
        <p>
            <label for="sffc_recruiter_contact_cta_label"><strong><?php esc_html_e('Contact CTA Label', 'senna-finance'); ?></strong></label>
            <input type="text" class="widefat" name="sffc_recruiter_contact_cta_label" id="sffc_recruiter_contact_cta_label" value="<?php echo esc_attr($fields['cta_label']); ?>" placeholder="<?php esc_attr_e('Book intro call', 'senna-finance'); ?>">
        </p>
        <p>
            <label for="sffc_recruiter_contact_cta_url"><strong><?php esc_html_e('Contact CTA URL', 'senna-finance'); ?></strong></label>
            <input type="url" class="widefat" name="sffc_recruiter_contact_cta_url" id="sffc_recruiter_contact_cta_url" value="<?php echo esc_attr($fields['cta_url']); ?>" placeholder="https://">
        </p>
    <?php
    }

    public function render_testimonials_meta_box($post)
    {
        $testimonials = $this->get_repeater_meta($post->ID, '_sffc_recruiter_testimonials');
        $this->print_repeater_assets();

        if (empty($testimonials)) {
            $testimonials[] = ['quote' => '', 'name' => '', 'role' => '', 'rating' => ''];
        }

    ?>
        <div id="sffc-recruiter-testimonials" class="sffc-admin-repeatable">
            <?php foreach ($testimonials as $testimonial) : ?>
                <div class="sffc-admin-repeatable__row">
                    <p>
                        <label><?php esc_html_e('Quote', 'senna-finance'); ?></label>
                        <textarea name="sffc_recruiter_testimonials[quote][]" rows="3" class="widefat"><?php echo esc_textarea($testimonial['quote']); ?></textarea>
                    </p>
                    <div class="sffc-admin-repeatable__grid">
                        <p>
                            <label><?php esc_html_e('Name', 'senna-finance'); ?></label>
                            <input type="text" name="sffc_recruiter_testimonials[name][]" value="<?php echo esc_attr($testimonial['name']); ?>">
                        </p>
                        <p>
                            <label><?php esc_html_e('Role / Context', 'senna-finance'); ?></label>
                            <input type="text" name="sffc_recruiter_testimonials[role][]" value="<?php echo esc_attr($testimonial['role']); ?>">
                        </p>
                        <p>
                            <label><?php esc_html_e('Rating (0-5)', 'senna-finance'); ?></label>
                            <input type="number" step="0.1" min="0" max="5" name="sffc_recruiter_testimonials[rating][]" value="<?php echo esc_attr($testimonial['rating']); ?>">
                        </p>
                    </div>
                    <p class="sffc-admin-repeatable__actions">
                        <button type="button" class="button-link-delete sffc-admin-row-remove"><?php esc_html_e('Remove testimonial', 'senna-finance'); ?></button>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
        <p>
            <button type="button" class="button button-secondary sffc-admin-row-add" data-target="#sffc-recruiter-testimonials"><?php esc_html_e('Add testimonial', 'senna-finance'); ?></button>
        </p>
        <?php
    }

    public function render_metrics_meta_box($post)
    {
        $metrics = [
            'placements' => $this->get_meta($post->ID, '_sffc_recruiter_metric_placements'),
            'time' => $this->get_meta($post->ID, '_sffc_recruiter_metric_time'),
            'nps' => $this->get_meta($post->ID, '_sffc_recruiter_metric_nps'),
            'mandates' => $this->get_meta($post->ID, '_sffc_recruiter_metric_mandates'),
        ];

        $fields = [
            'placements' => __('Placements (12 mo)', 'senna-finance'),
            'time' => __('Average Time to Place (days)', 'senna-finance'),
            'nps' => __('Candidate NPS', 'senna-finance'),
            'mandates' => __('Active Mandates', 'senna-finance'),
        ];

        foreach ($fields as $key => $label) :
            $type = $key === 'nps' ? 'number' : 'number';
        ?>
            <p>
                <label for="sffc_recruiter_metric_<?php echo esc_attr($key); ?>"><strong><?php echo esc_html($label); ?></strong></label>
                <input type="<?php echo esc_attr($type); ?>" class="widefat" name="sffc_recruiter_metric_<?php echo esc_attr($key); ?>" id="sffc_recruiter_metric_<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($metrics[$key]); ?>">
            </p>
        <?php
        endforeach;
    }

    private function get_repeater_meta($post_id, $meta_key)
    {
        $value = get_post_meta($post_id, $meta_key, true);
        if (empty($value)) {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        return is_array($value) ? $value : [];
    }

    private function sanitize_repeater($input, array $fields)
    {
        $sanitized = [];

        if (empty($input) || !is_array($input)) {
            return $sanitized;
        }

        $row_count = 0;
        foreach ($fields as $field => $callback) {
            if (isset($input[$field]) && is_array($input[$field])) {
                $row_count = max($row_count, count($input[$field]));
            }
        }

        for ($index = 0; $index < $row_count; $index++) {
            $row = [];
            foreach ($fields as $field => $callback) {
                $value = $input[$field][$index] ?? '';
                switch ($callback) {
                    case 'text':
                        $row[$field] = sanitize_text_field($value);
                        break;
                    case 'textarea':
                        $row[$field] = sanitize_textarea_field($value);
                        break;
                    case 'url':
                        $row[$field] = esc_url_raw($value);
                        break;
                    case 'int':
                        $row[$field] = (int) $value;
                        break;
                    case 'float':
                        $row[$field] = is_numeric($value) ? (float) $value : '';
                        break;
                    default:
                        $row[$field] = is_callable($callback) ? call_user_func($callback, $value) : sanitize_text_field($value);
                        break;
                }
            }

            $has_content = array_filter($row, function ($value) {
                return $value !== '' && $value !== null;
            });

            if ($has_content) {
                $sanitized[] = $row;
            }
        }

        return $sanitized;
    }

    private function sanitize_media_reference($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        $url = esc_url_raw($value);
        return $url ?: '';
    }

    public function save_meta_boxes($post_id)
    {
        if (!isset($_POST['sffc_recruiter_meta_nonce']) || !wp_verify_nonce($_POST['sffc_recruiter_meta_nonce'], 'sffc_recruiter_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $this->save_text_meta($post_id, '_sffc_recruiter_tagline', 'sffc_recruiter_tagline');
        $this->save_text_meta($post_id, '_sffc_recruiter_rating', 'sffc_recruiter_rating');
        $this->save_text_meta($post_id, '_sffc_recruiter_location', 'sffc_recruiter_location');
        $this->save_text_meta($post_id, '_sffc_recruiter_founded', 'sffc_recruiter_founded');
        $this->save_text_meta($post_id, '_sffc_recruiter_size', 'sffc_recruiter_size');
        $this->save_text_meta($post_id, '_sffc_recruiter_video', 'sffc_recruiter_video', 'url');

        update_post_meta($post_id, '_sffc_recruiter_frontend_edit', isset($_POST['sffc_recruiter_frontend_edit']) ? '1' : '');

        $this->save_text_meta($post_id, '_sffc_recruiter_cta_jobs', 'sffc_recruiter_cta_jobs', 'url');
        $this->save_text_meta($post_id, '_sffc_recruiter_cta_cv', 'sffc_recruiter_cta_cv', 'url');
        $this->save_text_meta($post_id, '_sffc_recruiter_cta_contact', 'sffc_recruiter_cta_contact', 'url');
        $this->save_text_meta($post_id, '_sffc_recruiter_cta_call', 'sffc_recruiter_cta_call', 'url');

        $this->save_text_meta($post_id, '_sffc_recruiter_locations_summary', 'sffc_recruiter_locations_summary');
        $this->save_text_meta($post_id, '_sffc_recruiter_role_focus', 'sffc_recruiter_role_focus');
        $this->save_text_meta($post_id, '_sffc_recruiter_candidate_seniority', 'sffc_recruiter_candidate_seniority');

        $consultants = $this->sanitize_repeater($_POST['sffc_recruiter_consultants'] ?? [], [
            'name' => 'text',
            'title' => 'text',
            'photo' => [$this, 'sanitize_media_reference'],
            'linkedin' => 'url',
            'specialization' => 'text',
        ]);
        $this->persist_repeater_meta($post_id, '_sffc_recruiter_consultants', $consultants);

        $services = $this->sanitize_repeater($_POST['sffc_recruiter_services'] ?? [], [
            'title' => 'text',
            'description' => 'textarea',
        ]);
        $this->persist_repeater_meta($post_id, '_sffc_recruiter_services', $services);

        $process = $this->sanitize_repeater($_POST['sffc_recruiter_process'] ?? [], [
            'title' => 'text',
            'description' => 'textarea',
        ]);
        $this->persist_repeater_meta($post_id, '_sffc_recruiter_process', $process);

        $logos_input = sanitize_text_field($_POST['sffc_recruiter_client_logos'] ?? '');
        $logos = array_filter(array_map('intval', array_map('trim', explode(',', $logos_input))));
        if (!empty($logos)) {
            update_post_meta($post_id, '_sffc_recruiter_client_logos', $logos);
        } else {
            delete_post_meta($post_id, '_sffc_recruiter_client_logos');
        }

        $testimonials = $this->sanitize_repeater($_POST['sffc_recruiter_testimonials'] ?? [], [
            'quote' => 'textarea',
            'name' => 'text',
            'role' => 'text',
            'rating' => 'float',
        ]);
        $this->persist_repeater_meta($post_id, '_sffc_recruiter_testimonials', $testimonials);

        $case_studies = $this->sanitize_repeater($_POST['sffc_recruiter_case_studies'] ?? [], [
            'title' => 'text',
            'summary' => 'textarea',
            'impact' => 'textarea',
            'link' => 'url',
        ]);
        $this->persist_repeater_meta($post_id, '_sffc_recruiter_case_studies', $case_studies);

        $this->save_text_meta($post_id, '_sffc_recruiter_contact_person', 'sffc_recruiter_contact_person');
        $this->save_text_meta($post_id, '_sffc_recruiter_contact_email', 'sffc_recruiter_contact_email', 'email');
        $this->save_text_meta($post_id, '_sffc_recruiter_contact_phone', 'sffc_recruiter_contact_phone');
        $this->save_text_meta($post_id, '_sffc_recruiter_contact_address', 'sffc_recruiter_contact_address', 'textarea');
        $this->save_text_meta($post_id, '_sffc_recruiter_contact_website', 'sffc_recruiter_contact_website', 'url');
        $this->save_text_meta($post_id, '_sffc_recruiter_contact_cta_label', 'sffc_recruiter_contact_cta_label');
        $this->save_text_meta($post_id, '_sffc_recruiter_contact_cta_url', 'sffc_recruiter_contact_cta_url', 'url');

        $this->save_text_meta($post_id, '_sffc_recruiter_metric_placements', 'sffc_recruiter_metric_placements', 'int');
        $this->save_text_meta($post_id, '_sffc_recruiter_metric_time', 'sffc_recruiter_metric_time', 'int');
        $this->save_text_meta($post_id, '_sffc_recruiter_metric_nps', 'sffc_recruiter_metric_nps', 'float');
        $this->save_text_meta($post_id, '_sffc_recruiter_metric_mandates', 'sffc_recruiter_metric_mandates', 'int');

        $canonical = sanitize_text_field(get_the_title($post_id));
        update_post_meta($post_id, '_sffc_recruiter_canonical_name', $canonical);
    }

    private function save_text_meta($post_id, $meta_key, $field, $type = 'text')
    {
        if (!isset($_POST[$field])) {
            delete_post_meta($post_id, $meta_key);
            return;
        }

        $value = $_POST[$field];
        switch ($type) {
            case 'url':
                $value = esc_url_raw($value);
                break;
            case 'int':
                $value = (int) $value;
                break;
            case 'float':
                $value = is_numeric($value) ? (float) $value : '';
                break;
            case 'email':
                $value = sanitize_email($value);
                break;
            case 'textarea':
                $value = sanitize_textarea_field($value);
                break;
            default:
                $value = sanitize_text_field($value);
        }

        if ($value === '' || $value === null) {
            delete_post_meta($post_id, $meta_key);
            return;
        }

        update_post_meta($post_id, $meta_key, $value);
    }

    private function persist_repeater_meta($post_id, $meta_key, array $entries)
    {
        if (!empty($entries)) {
            update_post_meta($post_id, $meta_key, wp_json_encode($entries));
        } else {
            delete_post_meta($post_id, $meta_key);
        }
    }

    private function print_repeater_assets()
    {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;
        ?>
        <style>
            .sffc-admin-repeatable {
                display: flex;
                flex-direction: column;
                gap: 16px;
            }

            .sffc-admin-repeatable__row {
                border: 1px solid #dcdcde;
                border-radius: 8px;
                padding: 16px;
                background: #fff;
            }

            .sffc-admin-repeatable__grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 12px 16px;
            }

            .sffc-admin-repeatable__actions {
                margin-top: 12px;
            }

            .sffc-admin-repeatable__actions .sffc-admin-row-remove {
                color: #b32d2e;
            }
        </style>
        <script>
            (function($) {
                $(document).on('click', '.sffc-admin-row-add', function(event) {
                    event.preventDefault();
                    var target = $($(this).data('target'));
                    if (!target.length) {
                        return;
                    }
                    var clone = target.children('.sffc-admin-repeatable__row').last().clone(true, true);
                    clone.find('input, textarea').val('');
                    target.append(clone);
                });
                $(document).on('click', '.sffc-admin-row-remove', function(event) {
                    event.preventDefault();
                    var row = $(this).closest('.sffc-admin-repeatable__row');
                    var container = row.parent();
                    if (container.children('.sffc-admin-repeatable__row').length > 1) {
                        row.remove();
                    } else {
                        row.find('input, textarea').val('');
                    }
                });
            })(jQuery);
        </script>
    <?php
    }

    /**
     * Register Recruiter tools submenu for diagnostics / imports.
     */
    public function register_admin_tools()
    {
        add_submenu_page(
            'edit.php?post_type=' . self::POST_TYPE,
            __('Recruiter Tools', 'senna-finance'),
            __('Tools', 'senna-finance'),
            'manage_options',
            'sffc-recruiter-tools',
            [$this, 'render_admin_tools']
        );
    }

    public function render_admin_tools()
    {
        $count = wp_count_posts(self::POST_TYPE);
        $published = $count ? (int) $count->publish : 0;
        $draft = $count ? (int) $count->draft : 0;
        $pending = $count ? (int) $count->pending : 0;

        $selected_role = get_option(self::FRONTEND_ROLE_OPTION, 'administrator');
        $roles = wp_roles()->get_names();
        $role_updated = isset($_GET['recruiter_role']) && $_GET['recruiter_role'] === 'updated';

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Recruiter Tools', 'senna-finance'); ?></h1>
            <p><?php esc_html_e('Use these diagnostics to keep recruiter profiles healthy.', 'senna-finance'); ?></p>

            <?php if ($role_updated) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Front-end editor role updated.', 'senna-finance'); ?></p>
                </div>
            <?php endif; ?>

            <table class="widefat" style="max-width:600px;margin-top:20px;">
                <tbody>
                    <tr>
                        <th><?php esc_html_e('Published recruiters', 'senna-finance'); ?></th>
                        <td><?php echo esc_html(number_format_i18n($published)); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Draft recruiters', 'senna-finance'); ?></th>
                        <td><?php echo esc_html(number_format_i18n($draft)); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Pending recruiters', 'senna-finance'); ?></th>
                        <td><?php echo esc_html(number_format_i18n($pending)); ?></td>
                    </tr>
                </tbody>
            </table>

            <p style="margin-top:20px;">
                <?php esc_html_e('Bulk import, feed synchronisation, and advanced diagnostics will be added here in follow-up iterations.', 'senna-finance'); ?>
            </p>

            <hr style="margin:32px 0;">

            <h2><?php esc_html_e('Front-end Editing Access', 'senna-finance'); ?></h2>
            <p><?php esc_html_e('Select which user role can edit recruiter profiles directly from the front-end (in addition to administrators). Individual profiles must also opt-in via the “Allow front-end editing” checkbox.', 'senna-finance'); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:420px;">
                <?php wp_nonce_field('sffc_save_recruiter_role'); ?>
                <input type="hidden" name="action" value="sffc_save_recruiter_role">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="sffc_recruiter_role_select"><?php esc_html_e('Allowed Role', 'senna-finance'); ?></label></th>
                        <td>
                            <select name="sffc_recruiter_role" id="sffc_recruiter_role_select">
                                <?php foreach ($roles as $role_slug => $label) : ?>
                                    <option value="<?php echo esc_attr($role_slug); ?>" <?php selected($selected_role, $role_slug); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Admins always retain access. Choose the minimum role required to edit from the front-end.', 'senna-finance'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save role', 'senna-finance')); ?>
            </form>
        </div>
        <?php
    }

    public function register_shortcodes()
    {
        add_shortcode('sffc_recruiter_profile', [$this, 'shortcode_recruiter_profile']);
    }

    public function shortcode_recruiter_profile($atts)
    {
        $atts = shortcode_atts([
            'id' => 0,
            'slug' => '',
            'show_schema' => 'false',
        ], $atts, 'sffc_recruiter_profile');

        $post_id = absint($atts['id']);

        if (!$post_id && !empty($atts['slug'])) {
            $maybe_post = get_page_by_path(sanitize_title($atts['slug']), OBJECT, self::POST_TYPE);
            if ($maybe_post instanceof WP_Post) {
                $post_id = $maybe_post->ID;
            }
        }

        if (!$post_id && get_post() instanceof WP_Post && get_post()->post_type === self::POST_TYPE) {
            $post_id = get_post()->ID;
        }

        if (!$post_id) {
            return '';
        }

        $view = $this->get_profile_view_model($post_id);
        if (!$view) {
            return '';
        }

        $show_schema = filter_var($atts['show_schema'], FILTER_VALIDATE_BOOLEAN);

        return $this->render_profile($view, [
            'show_schema' => $show_schema,
        ]);
    }

    public function get_profile_view_model($post_id)
    {
        $post = get_post($post_id);

        if (!$post || $post->post_type !== self::POST_TYPE) {
            return null;
        }

        $meta_map = [
            'tagline' => '_sffc_recruiter_tagline',
            'rating' => '_sffc_recruiter_rating',
            'location' => '_sffc_recruiter_location',
            'founded' => '_sffc_recruiter_founded',
            'size' => '_sffc_recruiter_size',
            'video' => '_sffc_recruiter_video',
            'frontend_edit' => '_sffc_recruiter_frontend_edit',
            'cta_jobs' => '_sffc_recruiter_cta_jobs',
            'cta_cv' => '_sffc_recruiter_cta_cv',
            'cta_contact' => '_sffc_recruiter_cta_contact',
            'cta_call' => '_sffc_recruiter_cta_call',
            'metric_placements' => '_sffc_recruiter_metric_placements',
            'metric_time' => '_sffc_recruiter_metric_time',
            'metric_nps' => '_sffc_recruiter_metric_nps',
            'metric_mandates' => '_sffc_recruiter_metric_mandates',
            'canonical_name' => '_sffc_recruiter_canonical_name',
            'locations_summary' => '_sffc_recruiter_locations_summary',
            'role_focus' => '_sffc_recruiter_role_focus',
            'candidate_seniority' => '_sffc_recruiter_candidate_seniority',
            'contact_person' => '_sffc_recruiter_contact_person',
            'contact_email' => '_sffc_recruiter_contact_email',
            'contact_phone' => '_sffc_recruiter_contact_phone',
            'contact_address' => '_sffc_recruiter_contact_address',
            'contact_website' => '_sffc_recruiter_contact_website',
            'contact_cta_label' => '_sffc_recruiter_contact_cta_label',
            'contact_cta_url' => '_sffc_recruiter_contact_cta_url',
        ];

        $meta = [];
        foreach ($meta_map as $key => $meta_key) {
            $meta[$key] = get_post_meta($post_id, $meta_key, true);
        }

        $consultants = $this->decode_repeatable_meta(get_post_meta($post_id, '_sffc_recruiter_consultants', true));
        $services = $this->decode_repeatable_meta(get_post_meta($post_id, '_sffc_recruiter_services', true));
        $testimonials = $this->decode_repeatable_meta(get_post_meta($post_id, '_sffc_recruiter_testimonials', true));
        $case_studies = $this->decode_repeatable_meta(get_post_meta($post_id, '_sffc_recruiter_case_studies', true));
        $process_steps = $this->decode_repeatable_meta(get_post_meta($post_id, '_sffc_recruiter_process', true));

        $logos_raw = get_post_meta($post_id, '_sffc_recruiter_client_logos', true);
        if (is_string($logos_raw)) {
            $decoded = json_decode($logos_raw, true);
            $logos = is_array($decoded) ? $decoded : [];
        } elseif (is_array($logos_raw)) {
            $logos = $logos_raw;
        } else {
            $logos = [];
        }

        $taxonomies = [
            'industries' => wp_get_post_terms($post_id, 'sffc_recruiter_industry', ['fields' => 'names']),
            'roles' => wp_get_post_terms($post_id, 'sffc_recruiter_role', ['fields' => 'names']),
            'functions' => wp_get_post_terms($post_id, 'sffc_recruiter_function', ['fields' => 'names']),
            'regions' => wp_get_post_terms($post_id, 'sffc_recruiter_region', ['fields' => 'names']),
        ];

        $cta_buttons = array_filter([
            ['label' => __('View Jobs', 'senna-finance'), 'url' => $meta['cta_jobs'], 'anchor' => '#recruiter-jobs'],
            ['label' => __('Submit CV', 'senna-finance'), 'url' => $meta['cta_cv'], 'anchor' => '#recruiter-cta'],
            ['label' => __('Contact Recruiter', 'senna-finance'), 'url' => $meta['cta_contact'], 'anchor' => '#recruiter-cta'],
        ], static function ($cta) {
            return !empty($cta['url']) || !empty($cta['anchor']);
        });

        $rating_value = is_numeric($meta['rating']) ? round((float) $meta['rating'], 1) : '';
        $aggregate_rating = $rating_value ? min(max($rating_value, 0), 5) : '';
        $canonical_name = $meta['canonical_name'] ?: get_the_title($post);

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $canonical_name,
            'url' => get_permalink($post),
            'image' => get_the_post_thumbnail_url($post, 'large'),
            'description' => wp_strip_all_tags(get_the_excerpt($post)),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $meta['location'],
            ],
        ];

        if ($aggregate_rating) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $aggregate_rating,
                'reviewCount' => max(1, count($testimonials)),
            ];
        }

        $hero_highlights = array_values(array_filter(array_unique(array_merge(
            $taxonomies['industries'] ?? [],
            $taxonomies['regions'] ?? []
        ))));
        if (count($hero_highlights) > 3) {
            $hero_highlights = array_slice($hero_highlights, 0, 3);
        }

        $hero_scoreboard = [];
        if ($aggregate_rating) {
            $hero_scoreboard[] = [
                'value' => number_format_i18n($aggregate_rating, 1) . '★',
                'label' => __('Candidate rating', 'senna-finance'),
                'detail' => __('Independent reviews', 'senna-finance'),
            ];
        }
        if (!empty($meta['metric_placements'])) {
            $hero_scoreboard[] = [
                'value' => number_format_i18n((int) $meta['metric_placements']),
                'label' => __('Mandates closed', 'senna-finance'),
                'detail' => __('Trailing 12 months', 'senna-finance'),
            ];
        }
        if (!empty($meta['metric_mandates'])) {
            $hero_scoreboard[] = [
                'value' => number_format_i18n((int) $meta['metric_mandates']),
                'label' => __('Active mandates', 'senna-finance'),
                'detail' => __('Currently retained searches', 'senna-finance'),
            ];
        }
        if (!empty($meta['metric_time']) && count($hero_scoreboard) < 3) {
            $hero_scoreboard[] = [
                'value' => number_format_i18n((int) $meta['metric_time']) . ' ' . __('days', 'senna-finance'),
                'label' => __('Avg. days to shortlist', 'senna-finance'),
                'detail' => __('Brief to finalists', 'senna-finance'),
            ];
        }
        if (!empty($meta['metric_nps']) && count($hero_scoreboard) < 3) {
            $hero_scoreboard[] = [
                'value' => number_format_i18n((int) $meta['metric_nps']),
                'label' => __('Candidate NPS', 'senna-finance'),
                'detail' => __('Latest cohort', 'senna-finance'),
            ];
        }
        $hero_scoreboard = array_slice($hero_scoreboard, 0, 3);

        $hero_facts = array_values(array_filter([
            $meta['location'] ? ['label' => __('Headquarters', 'senna-finance'), 'value' => $meta['location']] : null,
            $meta['founded'] ? ['label' => __('Founded', 'senna-finance'), 'value' => $meta['founded']] : null,
            $meta['size'] ? ['label' => __('Team size', 'senna-finance'), 'value' => $meta['size']] : null,
        ]));

        $hero_focus_points = [];
        if (!empty($hero_highlights)) {
            $hero_focus_points[] = sprintf(__('Focus on %s', 'senna-finance'), $hero_highlights[0]);
        }
        if (!empty($services) && !empty($services[0]['title'])) {
            $hero_focus_points[] = sprintf(__('Advising on %s', 'senna-finance'), $services[0]['title']);
        }
        if (!empty($consultants) && !empty($consultants[0]['name'])) {
            $hero_focus_points[] = sprintf(__('Lead consultant: %s', 'senna-finance'), $consultants[0]['name']);
        }
        $hero_focus_points = array_slice(array_unique(array_filter($hero_focus_points)), 0, 3);

        $metrics_summary = [
            [
                'label' => __('Locations Covered', 'senna-finance'),
                'value' => $meta['locations_summary'] ?: implode(', ', array_slice($taxonomies['regions'] ?? [], 0, 3)),
            ],
            [
                'label' => __('Role Focus', 'senna-finance'),
                'value' => $meta['role_focus'] ?: implode(', ', array_slice($taxonomies['functions'] ?? [], 0, 3)),
            ],
            [
                'label' => __('Candidate Seniority', 'senna-finance'),
                'value' => $meta['candidate_seniority'],
            ],
        ];

        $metrics_has_value = (bool) array_filter($metrics_summary, static function ($metric) {
            return !empty($metric['value']);
        });

        $original_post = $GLOBALS['post'] ?? null;
        $GLOBALS['post'] = $post;
        $content = apply_filters('the_content', $post->post_content);
        if ($original_post instanceof WP_Post) {
            $GLOBALS['post'] = $original_post;
        } else {
            unset($GLOBALS['post']);
        }

        return [
            'post' => $post,
            'meta' => $meta,
            'taxonomies' => $taxonomies,
            'consultants' => $consultants,
            'services' => $services,
            'testimonials' => $testimonials,
            'logos' => $logos,
            'case_studies' => $case_studies,
            'process_steps' => $process_steps,
            'cta_buttons' => $cta_buttons,
            'aggregate_rating' => $aggregate_rating,
            'canonical_name' => $canonical_name,
            'schema' => $schema,
            'hero_highlights' => $hero_highlights,
            'hero_focus_points' => $hero_focus_points,
            'hero_scoreboard' => $hero_scoreboard,
            'hero_facts' => $hero_facts,
            'metrics_summary' => $metrics_summary,
            'metrics_has_value' => $metrics_has_value,
            'content' => $content,
            'thumbnail_id' => get_post_thumbnail_id($post),
            'can_edit' => current_user_can('edit_post', $post_id),
            'recruiter_post_type' => self::POST_TYPE,
        ];
    }

    public function render_profile(array $view, array $args = [])
    {
        $args = wp_parse_args($args, [
            'show_schema' => true,
        ]);

        ob_start();

        $template = SFFC_PLUGIN_DIR . 'templates/recruiter-profile-content.php';
        if (file_exists($template)) {
            $render_args = $args;
            include $template;
        }

        return ob_get_clean();
    }

    private function decode_repeatable_meta($value)
    {
        if (empty($value)) {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
            return [];
        }

        return is_array($value) ? $value : [];
    }

    /**
     * Handle admin form submission for updating the front-end editor role.
     */
    public function handle_role_update()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to update this setting.', 'senna-finance'));
        }

        check_admin_referer('sffc_save_recruiter_role');

        $role = isset($_POST['sffc_recruiter_role']) ? sanitize_key(wp_unslash($_POST['sffc_recruiter_role'])) : 'administrator';
        $available_roles = array_keys(wp_roles()->get_names());

        if (!in_array($role, $available_roles, true)) {
            $role = 'administrator';
        }

        update_option(self::FRONTEND_ROLE_OPTION, $role);

        $redirect = wp_get_referer();
        if (!$redirect) {
            $redirect = admin_url('edit.php?post_type=' . self::POST_TYPE . '&page=sffc-recruiter-tools');
        }

        wp_safe_redirect(add_query_arg('recruiter_role', 'updated', $redirect));
        exit;
    }

    /**
     * Check whether the current user can edit a recruiter from the front-end.
     */
    public static function user_can_frontend_edit($post_id, $user_id = 0)
    {
        $user_id = $user_id ?: get_current_user_id();
        if (!$user_id) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        if (user_can($user, 'manage_options') || user_can($user, 'edit_post', $post_id)) {
            return true;
        }

        $required_role = get_option(self::FRONTEND_ROLE_OPTION, 'administrator');
        if ($required_role && in_array($required_role, (array) $user->roles, true)) {
            return true;
        }

        return false;
    }

    /**
     * Process front-end profile updates submitted by recruiters.
     */
    public function handle_frontend_update()
    {
        if (is_admin()) {
            return;
        }

        if (!is_singular(self::POST_TYPE)) {
            return;
        }

        if (empty($_POST['sffc_recruiter_frontend_update'])) {
            return;
        }

        $post_id = isset($_POST['sffc_recruiter_post_id']) ? (int) $_POST['sffc_recruiter_post_id'] : 0;
        if (!$post_id || get_queried_object_id() !== $post_id) {
            return;
        }

        if (get_post_meta($post_id, '_sffc_recruiter_frontend_edit', true) !== '1') {
            return;
        }

        if (!self::user_can_frontend_edit($post_id)) {
            return;
        }

        if (!isset($_POST['sffc_recruiter_frontend_nonce']) || !wp_verify_nonce($_POST['sffc_recruiter_frontend_nonce'], 'sffc_recruiter_frontend_update')) {
            return;
        }

        $payload = isset($_POST['sffc_recruiter_frontend']) ? wp_unslash($_POST['sffc_recruiter_frontend']) : [];
        if (!is_array($payload)) {
            $payload = [];
        }

        $fields = [
            '_sffc_recruiter_tagline' => isset($payload['tagline']) ? sanitize_text_field($payload['tagline']) : null,
            '_sffc_recruiter_location' => isset($payload['location']) ? sanitize_text_field($payload['location']) : null,
            '_sffc_recruiter_size' => isset($payload['size']) ? sanitize_text_field($payload['size']) : null,
            '_sffc_recruiter_locations_summary' => isset($payload['locations_summary']) ? sanitize_text_field($payload['locations_summary']) : null,
            '_sffc_recruiter_role_focus' => isset($payload['role_focus']) ? sanitize_text_field($payload['role_focus']) : null,
            '_sffc_recruiter_candidate_seniority' => isset($payload['candidate_seniority']) ? sanitize_text_field($payload['candidate_seniority']) : null,
            '_sffc_recruiter_contact_person' => isset($payload['contact_person']) ? sanitize_text_field($payload['contact_person']) : null,
            '_sffc_recruiter_contact_email' => isset($payload['contact_email']) ? sanitize_email($payload['contact_email']) : null,
            '_sffc_recruiter_contact_phone' => isset($payload['contact_phone']) ? sanitize_text_field($payload['contact_phone']) : null,
            '_sffc_recruiter_contact_address' => isset($payload['contact_address']) ? sanitize_textarea_field($payload['contact_address']) : null,
            '_sffc_recruiter_contact_website' => isset($payload['contact_website']) ? esc_url_raw($payload['contact_website']) : null,
            '_sffc_recruiter_contact_cta_label' => isset($payload['contact_cta_label']) ? sanitize_text_field($payload['contact_cta_label']) : null,
            '_sffc_recruiter_contact_cta_url' => isset($payload['contact_cta_url']) ? esc_url_raw($payload['contact_cta_url']) : null,
        ];

        foreach ($fields as $meta_key => $value) {
            if ($value === null) {
                continue;
            }

            if ($value === '' || $value === null) {
                delete_post_meta($post_id, $meta_key);
            } else {
                update_post_meta($post_id, $meta_key, $value);
            }
        }

        if (isset($payload['about'])) {
            $about_content = wp_kses_post($payload['about']);
            wp_update_post([
                'ID' => $post_id,
                'post_content' => $about_content,
            ]);
        }

        clean_post_cache($post_id);

        $redirect = add_query_arg('sffc_recruiter_updated', '1', get_permalink($post_id));
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Load the bundled single template.
     */
    public function maybe_override_single_template($template)
    {
        if (!is_singular(self::POST_TYPE)) {
            return $template;
        }

        $plugin_template = SFFC_PLUGIN_DIR . 'templates/single-sffc_recruiter.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }

        return $template;
    }

    public function find_recruiter_by_canonical($name)
    {
        $name = trim($name);
        if ('' === $name) {
            return null;
        }

        $args = [
            'post_type' => self::POST_TYPE,
            'posts_per_page' => 1,
            'post_status' => 'any',
            'meta_query' => [[
                'key' => '_sffc_recruiter_canonical_name',
                'value' => $name,
                'compare' => '=',
            ]],
        ];

        $posts = get_posts($args);
        if (!empty($posts)) {
            return $posts[0];
        }

        $fallback = get_page_by_title($name, OBJECT, self::POST_TYPE);
        if ($fallback instanceof WP_Post) {
            return $fallback;
        }

        return null;
    }

    public function ensure_canonical_meta($post_id, $canonical)
    {
        $canonical = trim($canonical);
        if ('' === $canonical) {
            return;
        }

        update_post_meta($post_id, '_sffc_recruiter_canonical_name', $canonical);
    }

    public function create_recruiter_shell($name, array $context = [])
    {
        $canonical = trim($name);
        if ('' === $canonical) {
            return null;
        }

        $content = $context['content'] ?? sprintf(
            '<p>%s specialises in sourcing exceptional talent for finance and private markets teams.</p>',
            esc_html($canonical)
        );

        $post_id = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $canonical,
            'post_content' => $content,
            'post_name' => sanitize_title($canonical),
        ]);

        if (!$post_id || is_wp_error($post_id)) {
            return null;
        }

        $this->ensure_canonical_meta($post_id, $canonical);

        if (!empty($context['location'])) {
            update_post_meta($post_id, '_sffc_recruiter_location', sanitize_text_field($context['location']));
        }

        if (!empty($context['tagline'])) {
            update_post_meta($post_id, '_sffc_recruiter_tagline', sanitize_text_field($context['tagline']));
        }

        return $post_id;
    }

    public function increment_job_count($post_id)
    {
        $count = (int) get_post_meta($post_id, '_sffc_recruiter_job_count', true);
        update_post_meta($post_id, '_sffc_recruiter_job_count', $count + 1);
    }

    public function filter_document_title($title)
    {
        if (!is_singular(self::POST_TYPE)) {
            return $title;
        }

        $post_id = get_queried_object_id();
        if (!$post_id) {
            return $title;
        }

        $canonical = get_post_meta($post_id, '_sffc_recruiter_canonical_name', true) ?: get_the_title($post_id);
        $tagline = get_post_meta($post_id, '_sffc_recruiter_tagline', true);

        return $tagline
            ? sprintf('%s – %s | senna Recruiter Directory', $canonical, $tagline)
            : sprintf('%s Recruiter Profile | senna', $canonical);
    }

    public function filter_document_title_parts($parts)
    {
        if (!is_singular(self::POST_TYPE)) {
            return $parts;
        }

        $parts['title'] = $this->filter_document_title($parts['title'] ?? '');
        return $parts;
    }

    public function output_head_meta()
    {
        if (!is_singular(self::POST_TYPE) || defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION')) {
            return;
        }

        $post_id = get_queried_object_id();
        if (!$post_id) {
            return;
        }

        $canonical = get_post_meta($post_id, '_sffc_recruiter_canonical_name', true) ?: get_the_title($post_id);
        $tagline = get_post_meta($post_id, '_sffc_recruiter_tagline', true);
        $location = get_post_meta($post_id, '_sffc_recruiter_location', true);
        $rating = get_post_meta($post_id, '_sffc_recruiter_rating', true);

        $description = $tagline ?: wp_strip_all_tags(get_the_excerpt($post_id));
        if ($location) {
            $description .= ' • ' . $location;
        }
        if ($rating) {
            $description .= ' • ' . sprintf(__('Rated %s/5 by candidates and clients.', 'senna-finance'), number_format_i18n($rating, 1));
        }

        $description = wp_trim_words(trim($description), 32, '…');
        $title = $this->filter_document_title(get_the_title($post_id));
        $permalink = get_permalink($post_id);
        $image = get_the_post_thumbnail_url($post_id, 'large');

        echo '\n<!-- Recruiter meta -->\n';
        printf('<meta name="description" content="%s"/>\n', esc_attr($description));
        printf('<meta property="og:title" content="%s"/>\n', esc_attr($title));
        printf('<meta property="og:description" content="%s"/>\n', esc_attr($description));
        printf('<meta property="og:url" content="%s"/>\n', esc_url($permalink));
        printf('<meta property="og:type" content="%s"/>\n', 'website');
        if ($image) {
            printf('<meta property="og:image" content="%s"/>\n', esc_url($image));
        }
    }

    public function cli_seed_sample_recruiters($args, $assoc_args)
    {
        $samples = $this->get_sample_recruiters();
        $created = 0;
        $jobs_created = 0;

        foreach ($samples as $sample) {
            $result = $this->seed_recruiter_from_template($sample);
            if (!$result['recruiter_id']) {
                WP_CLI::warning(sprintf('Failed to seed recruiter "%s"', $sample['name']));
                continue;
            }
            if ($result['created']) {
                $created++;
            }
            $jobs_created += $result['jobs'];
        }

        WP_CLI::success(sprintf('Recruiters seeded: %d. Sample jobs created: %d.', $created, $jobs_created));
    }

    private function get_sample_recruiters()
    {
        return [
            [
                'name' => 'Finatal Executive Search',
                'content' => '<p>Finatal specialises in private equity backed leadership hires across the UK and Europe.</p>',
                'meta' => [
                    'tagline' => 'Private equity executive search',
                    'rating' => 4.8,
                    'location' => 'London, United Kingdom',
                    'founded' => '2010',
                    'size' => '45 consultants',
                ],
                'metrics' => [
                    'placements' => 62,
                    'time_to_place' => 46,
                    'nps' => 71,
                    'mandates' => 18,
                ],
                'cta' => [
                    'jobs' => home_url('/jobs/?recruiter=finatal'),
                    'cv' => home_url('/submit-cv/'),
                    'contact' => home_url('/contact/'),
                    'call' => home_url('/book-consultation/'),
                ],
                'consultants' => [
                    ['name' => 'Emma Blake', 'title' => 'Partner – CFO Practice', 'photo' => 0, 'linkedin' => '', 'specialization' => 'CFO & senior finance hires for portfolio companies'],
                    ['name' => 'Lucas Grant', 'title' => 'Principal – Deal Advisory', 'photo' => 0, 'linkedin' => '', 'specialization' => 'Operating partner and value creation mandates'],
                ],
                'services' => [
                    ['title' => 'Executive Search', 'description' => 'Retained searches for board and C-suite positions.'],
                    ['title' => 'Interim Management', 'description' => 'Short notice leadership for carve-outs and transformations.'],
                ],
                'testimonials' => [
                    ['quote' => 'They found us an exceptional CFO within five weeks.', 'name' => 'Portfolio CEO', 'role' => 'Industrial Services Platform', 'rating' => 4.7],
                ],
                'taxonomies' => [
                    'sffc_recruiter_industry' => ['Private Equity'],
                    'sffc_recruiter_role' => ['C-Suite'],
                    'sffc_recruiter_function' => ['Finance Leadership'],
                    'sffc_recruiter_region' => ['United Kingdom', 'Europe'],
                ],
                'logos' => [],
                'jobs' => [
                    ['title' => 'Portfolio CFO – Infrastructure Services', 'location' => 'London, United Kingdom', 'description' => 'Lead finance transformation for a PE-backed infrastructure group.', 'salary' => '£180k – £220k', 'type' => 'Full-time'],
                    ['title' => 'Operating Partner – Value Creation', 'location' => 'Paris, France', 'description' => 'Partner with management teams to accelerate EBITDA growth.', 'salary' => 'Competitive', 'type' => 'Full-time'],
                ],
            ],
            [
                'name' => 'Marks Sattin Private Capital',
                'content' => '<p>Marks Sattin covers investment, portfolio, and corporate development mandates for mid-market funds.</p>',
                'meta' => [
                    'tagline' => 'Investment & portfolio talent for UK funds',
                    'rating' => 4.6,
                    'location' => 'Manchester, United Kingdom',
                    'founded' => '1988',
                    'size' => '120 consultants',
                ],
                'metrics' => [
                    'placements' => 94,
                    'time_to_place' => 38,
                    'nps' => 68,
                    'mandates' => 27,
                ],
                'cta' => [
                    'jobs' => home_url('/jobs/?recruiter=marks-sattin'),
                    'cv' => home_url('/submit-cv/'),
                    'contact' => home_url('/contact/'),
                    'call' => home_url('/book-consultation/'),
                ],
                'consultants' => [
                    ['name' => 'Sophie Turner', 'title' => 'Director – Investment Team', 'photo' => 0, 'linkedin' => '', 'specialization' => 'Investment professionals across buyout and growth funds'],
                    ['name' => 'Tom Hughes', 'title' => 'Associate Director – Portfolio', 'photo' => 0, 'linkedin' => '', 'specialization' => 'Value creation, FP&A, and operations mandates'],
                ],
                'services' => [
                    ['title' => 'Investment Team Hiring', 'description' => 'Associates through principals for buyout and growth strategies.'],
                    ['title' => 'Portfolio Talent', 'description' => 'Finance and strategy leaders for portfolio value creation teams.'],
                ],
                'testimonials' => [
                    ['quote' => 'Delivered a strong shortlist for our special situations fund.', 'name' => 'Talent Partner', 'role' => 'UK Buyout Fund', 'rating' => 4.5],
                ],
                'taxonomies' => [
                    'sffc_recruiter_industry' => ['Private Equity'],
                    'sffc_recruiter_role' => ['Investment Team'],
                    'sffc_recruiter_function' => ['Strategy'],
                    'sffc_recruiter_region' => ['United Kingdom'],
                ],
                'logos' => [],
                'jobs' => [
                    ['title' => 'Investment Associate – Mid-Market PE', 'location' => 'Manchester, United Kingdom', 'description' => 'Join a fund investing £20-100m into UK lower mid-market deals.', 'salary' => '£75k + bonus', 'type' => 'Full-time'],
                    ['title' => 'Portfolio Value Creation Lead', 'location' => 'Birmingham, United Kingdom', 'description' => 'Deliver 100-day plans and operational improvement across industrial assets.', 'salary' => '£120k + bonus', 'type' => 'Full-time'],
                ],
            ],
        ];
    }

    private function seed_sample_jobs($recruiter_id, array $sample)
    {
        $created = 0;
        $recruiter_name = $sample['name'];

        foreach ($sample['jobs'] as $job) {
            $existing = get_page_by_title($job['title'], OBJECT, 'sffc_job');
            if ($existing) {
                continue;
            }

            $job_id = wp_insert_post([
                'post_type' => 'sffc_job',
                'post_status' => 'publish',
                'post_title' => $job['title'],
                'post_content' => wp_kses_post($job['description']),
                'post_excerpt' => wp_trim_words($job['description'], 30),
            ]);

            if (is_wp_error($job_id) || !$job_id) {
                continue;
            }

            update_post_meta($job_id, '_sffc_recruiter_id', $recruiter_id);
            update_post_meta($job_id, 'sffc_recruiter_name', $recruiter_name);
            update_post_meta($job_id, 'sffc_location', $job['location']);
            update_post_meta($job_id, 'sffc_location_city', $job['location']);
            update_post_meta($job_id, 'sffc_job_type', $job['type'] ?? 'Full-time');
            update_post_meta($job_id, 'sffc_salary_display', $job['salary']);
            update_post_meta($job_id, 'sffc_posted_date', current_time('mysql'));
            update_post_meta($job_id, 'sffc_via_recruiter', true);
            update_post_meta($job_id, 'sffc_external_id', 'sample_' . md5($job['title']));

            wp_set_object_terms($job_id, ['Private Equity'], 'job_industry', false);
            wp_set_object_terms($job_id, ['Senior'], 'job_level', false);

            $this->increment_job_count($recruiter_id);
            $created++;
        }

        return $created;
    }

    private function seed_recruiter_from_template(array $sample)
    {
        $existing = $this->find_recruiter_by_canonical($sample['name']);
        $recruiter_id = $existing ? $existing->ID : $this->create_recruiter_shell($sample['name'], [
            'location' => $sample['meta']['location'],
            'tagline' => $sample['meta']['tagline'],
            'content' => $sample['content'],
        ]);

        if (!$recruiter_id) {
            return [
                'recruiter_id' => 0,
                'created' => false,
                'jobs' => 0,
            ];
        }

        $created = !$existing;

        $this->ensure_canonical_meta($recruiter_id, $sample['name']);

        update_post_meta($recruiter_id, '_sffc_recruiter_tagline', $sample['meta']['tagline']);
        update_post_meta($recruiter_id, '_sffc_recruiter_rating', $sample['meta']['rating']);
        update_post_meta($recruiter_id, '_sffc_recruiter_location', $sample['meta']['location']);
        update_post_meta($recruiter_id, '_sffc_recruiter_founded', $sample['meta']['founded']);
        update_post_meta($recruiter_id, '_sffc_recruiter_size', $sample['meta']['size']);
        update_post_meta($recruiter_id, '_sffc_recruiter_metric_placements', $sample['metrics']['placements']);
        update_post_meta($recruiter_id, '_sffc_recruiter_metric_time', $sample['metrics']['time_to_place']);
        update_post_meta($recruiter_id, '_sffc_recruiter_metric_nps', $sample['metrics']['nps']);
        update_post_meta($recruiter_id, '_sffc_recruiter_metric_mandates', $sample['metrics']['mandates']);
        update_post_meta($recruiter_id, '_sffc_recruiter_cta_jobs', esc_url_raw($sample['cta']['jobs']));
        update_post_meta($recruiter_id, '_sffc_recruiter_cta_cv', esc_url_raw($sample['cta']['cv']));
        update_post_meta($recruiter_id, '_sffc_recruiter_cta_contact', esc_url_raw($sample['cta']['contact']));
        update_post_meta($recruiter_id, '_sffc_recruiter_cta_call', esc_url_raw($sample['cta']['call']));
        update_post_meta($recruiter_id, '_sffc_recruiter_consultants', wp_json_encode($sample['consultants']));
        update_post_meta($recruiter_id, '_sffc_recruiter_services', wp_json_encode($sample['services']));
        update_post_meta($recruiter_id, '_sffc_recruiter_testimonials', wp_json_encode($sample['testimonials']));
        update_post_meta($recruiter_id, '_sffc_recruiter_client_logos', $sample['logos']);

        if (!empty($sample['taxonomies'])) {
            foreach ($sample['taxonomies'] as $taxonomy => $terms) {
                wp_set_object_terms($recruiter_id, $terms, $taxonomy, false);
            }
        }

        $jobs = $this->seed_sample_jobs($recruiter_id, $sample);

        return [
            'recruiter_id' => $recruiter_id,
            'created' => $created,
            'jobs' => $jobs,
        ];
    }

    public function maybe_seed_samples()
    {
        if (!is_admin() && !(defined('WP_CLI') && WP_CLI)) {
            return;
        }

        if (get_option('sffc_recruiter_samples_seeded')) {
            return;
        }

        $count = wp_count_posts(self::POST_TYPE);
        if ($count && (($count->publish ?? 0) > 0 || ($count->draft ?? 0) > 0)) {
            update_option('sffc_recruiter_samples_seeded', 1, false);
            return;
        }

        $samples = $this->get_sample_recruiters();
        foreach ($samples as $sample) {
            $this->seed_recruiter_from_template($sample);
        }

        update_option('sffc_recruiter_samples_seeded', 1, false);
    }

    public function cli_backfill_recruiters($args, $assoc_args)
    {
        $dry_run = isset($assoc_args['dry-run']);
        $limit = isset($assoc_args['limit']) ? max(1, (int) $assoc_args['limit']) : 500;

        $processed = 0;
        $created = 0;
        $updated = 0;

        $paged = 1;
        while (true) {
            $jobs = new WP_Query([
                'post_type' => 'sffc_job',
                'posts_per_page' => $limit,
                'paged' => $paged,
                'post_status' => 'publish',
                'fields' => 'ids',
                'meta_query' => [[
                    'key' => 'sffc_recruiter_name',
                    'compare' => '!=',
                    'value' => '',
                ]],
            ]);

            if (!$jobs->have_posts()) {
                break;
            }

            foreach ($jobs->posts as $job_id) {
                $processed++;
                $name = get_post_meta($job_id, 'sffc_recruiter_name', true);
                if (!$name) {
                    continue;
                }

                $existing_id = (int) get_post_meta($job_id, '_sffc_recruiter_id', true);
                if ($existing_id && get_post($existing_id)) {
                    $this->increment_job_count($existing_id);
                    $updated++;
                    continue;
                }

                if ($dry_run) {
                    WP_CLI::log(sprintf('Would create recruiter for "%s" (job #%d)', $name, $job_id));
                    continue;
                }

                $recruiter = $this->find_recruiter_by_canonical($name);
                $recruiter_id = $recruiter ? $recruiter->ID : $this->create_recruiter_shell($name, [
                    'location' => get_post_meta($job_id, 'sffc_location', true),
                    'tagline' => get_post_meta($job_id, 'sffc_company', true),
                ]);

                if ($recruiter_id) {
                    update_post_meta($job_id, '_sffc_recruiter_id', $recruiter_id);
                    $this->increment_job_count($recruiter_id);
                    $created++;
                }
            }

            if ($jobs->max_num_pages <= $paged) {
                break;
            }
            $paged++;
        }

        if ($dry_run) {
            WP_CLI::success(sprintf('Dry run complete. Processed %d jobs.', $processed));
        } else {
            WP_CLI::success(sprintf('Processed %d jobs. Recruiters created/linked: %d. Existing updated: %d', $processed, $created, $updated));
        }
    }
}

SFFC_Recruiter_Manager::get_instance();
