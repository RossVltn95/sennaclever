<?php
/**
 * Recruiter Posts Custom Post Type
 *
 * Admin-managed posts representing opportunities from recruiters
 * Displayed in the Newsroom Terminal's Recruiter Posts tab
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Recruiter_Posts
{
    private static $instance = null;
    private const DEFAULT_TERMS_VERSION = '1';

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomies'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_sffc_recruiter_post', array($this, 'save_meta_boxes'));
        add_filter('manage_sffc_recruiter_post_posts_columns', array($this, 'add_admin_columns'));
        add_action('manage_sffc_recruiter_post_posts_custom_column', array($this, 'render_admin_columns'), 10, 2);

        // Add admin notice with Create Example Post button
        add_action('admin_notices', array($this, 'render_create_example_post_notice'));
        add_action('admin_post_sffc_create_example_recruiter_post', array($this, 'handle_create_example_post'));
    }

    public function register_post_type()
    {
        $labels = array(
            'name'               => __('Recruiter Posts', 'senna-finance'),
            'singular_name'      => __('Recruiter Post', 'senna-finance'),
            'menu_name'          => __('Recruiter Posts', 'senna-finance'),
            'add_new'            => __('Add New Post', 'senna-finance'),
            'add_new_item'       => __('Add New Recruiter Post', 'senna-finance'),
            'edit_item'          => __('Edit Recruiter Post', 'senna-finance'),
            'new_item'           => __('New Recruiter Post', 'senna-finance'),
            'view_item'          => __('View Recruiter Post', 'senna-finance'),
            'search_items'       => __('Search Recruiter Posts', 'senna-finance'),
            'not_found'          => __('No recruiter posts found', 'senna-finance'),
            'not_found_in_trash' => __('No recruiter posts found in trash', 'senna-finance'),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => true,
            'rewrite'             => array('slug' => 'opportunity', 'with_front' => false),
            'capability_type'     => 'post',
            'has_archive'         => true,
            'hierarchical'        => false,
            'menu_position'       => 26,
            'menu_icon'           => 'dashicons-businessman',
            'supports'            => array('title', 'editor', 'thumbnail'),
            'show_in_rest'        => true,
        );

        register_post_type('sffc_recruiter_post', $args);
    }

    public function register_taxonomies()
    {
        // Post Type taxonomy (what kind of opportunity)
        register_taxonomy('recruiter_post_type', 'sffc_recruiter_post', array(
            'labels' => array(
                'name'          => __('Post Types', 'senna-finance'),
                'singular_name' => __('Post Type', 'senna-finance'),
            ),
            'public'            => false,
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => false,
        ));

        // Industry taxonomy
        register_taxonomy('recruiter_post_industry', 'sffc_recruiter_post', array(
            'labels' => array(
                'name'          => __('Industries', 'senna-finance'),
                'singular_name' => __('Industry', 'senna-finance'),
            ),
            'public'            => false,
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => false,
        ));

        // Location taxonomy
        register_taxonomy('recruiter_post_location', 'sffc_recruiter_post', array(
            'labels' => array(
                'name'          => __('Locations', 'senna-finance'),
                'singular_name' => __('Location', 'senna-finance'),
            ),
            'public'            => false,
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => false,
        ));

        $this->maybe_seed_default_terms();
    }

    private function maybe_seed_default_terms()
    {
        if (get_option('sffc_recruiter_post_terms_version') === self::DEFAULT_TERMS_VERSION) {
            return;
        }

        $default_types = array(
            'active-role'       => 'Active Role',
            'talent-pipeline'   => 'Talent Pipeline',
            'market-mapping'    => 'Market Mapping',
            'confidential'      => 'Confidential Search',
            'retained-search'   => 'Retained Search',
        );

        foreach ($default_types as $slug => $name) {
            if (!term_exists($slug, 'recruiter_post_type')) {
                wp_insert_term($name, 'recruiter_post_type', array('slug' => $slug));
            }
        }

        $default_industries = array(
            'private-equity'      => 'Private Equity',
            'investment-banking'  => 'Investment Banking',
            'asset-management'    => 'Asset Management',
            'venture-capital'     => 'Venture Capital',
            'corporate-finance'   => 'Corporate Finance',
            'real-estate'         => 'Real Estate',
            'hedge-funds'         => 'Hedge Funds',
            'consulting'          => 'Consulting',
            'banking'             => 'Banking',
            'fintech'             => 'FinTech',
        );

        foreach ($default_industries as $slug => $name) {
            if (!term_exists($slug, 'recruiter_post_industry')) {
                wp_insert_term($name, 'recruiter_post_industry', array('slug' => $slug));
            }
        }

        $default_locations = array(
            'dubai'         => 'Dubai, UAE',
            'abu-dhabi'     => 'Abu Dhabi, UAE',
            'riyadh'        => 'Riyadh, Saudi Arabia',
            'jeddah'        => 'Jeddah, Saudi Arabia',
            'doha'          => 'Doha, Qatar',
            'kuwait-city'   => 'Kuwait City, Kuwait',
            'manama'        => 'Manama, Bahrain',
            'muscat'        => 'Muscat, Oman',
            'cairo'         => 'Cairo, Egypt',
            'amman'         => 'Amman, Jordan',
        );

        foreach ($default_locations as $slug => $name) {
            if (!term_exists($slug, 'recruiter_post_location')) {
                wp_insert_term($name, 'recruiter_post_location', array('slug' => $slug));
            }
        }

        update_option('sffc_recruiter_post_terms_version', self::DEFAULT_TERMS_VERSION);
    }

    public function add_meta_boxes()
    {
        add_meta_box(
            'sffc_recruiter_post_details',
            __('Recruiter & Role Details', 'senna-finance'),
            array($this, 'render_details_meta_box'),
            'sffc_recruiter_post',
            'normal',
            'high'
        );

        add_meta_box(
            'sffc_recruiter_post_requirements',
            __('Requirements & Compensation', 'senna-finance'),
            array($this, 'render_requirements_meta_box'),
            'sffc_recruiter_post',
            'normal',
            'default'
        );

        add_meta_box(
            'sffc_recruiter_post_display',
            __('Display Settings', 'senna-finance'),
            array($this, 'render_display_meta_box'),
            'sffc_recruiter_post',
            'side',
            'default'
        );
    }

    public function render_details_meta_box($post)
    {
        wp_nonce_field('sffc_recruiter_post_details', 'sffc_recruiter_post_nonce');

        $recruiter_name = get_post_meta($post->ID, '_recruiter_name', true);
        $recruiter_title = get_post_meta($post->ID, '_recruiter_title', true);
        $recruiter_company = get_post_meta($post->ID, '_recruiter_company', true);
        $recruiter_email = get_post_meta($post->ID, '_recruiter_email', true);
        $recruiter_linkedin = get_post_meta($post->ID, '_recruiter_linkedin', true);
        $recruiter_image_id = get_post_meta($post->ID, '_recruiter_image_id', true);
        $recruiter_image_external_url = get_post_meta($post->ID, '_recruiter_image_url', true);
        $recruiter_image_url = $recruiter_image_id ? wp_get_attachment_image_url($recruiter_image_id, 'thumbnail') : $recruiter_image_external_url;
        $company_name = get_post_meta($post->ID, '_company_name', true);
        $job_title = get_post_meta($post->ID, '_job_title', true);
        $job_location = get_post_meta($post->ID, '_job_location', true);

        // Enqueue media scripts
        wp_enqueue_media();
        ?>
        <style>
            .sffc-meta-table { width: 100%; }
            .sffc-meta-table th { text-align: left; padding: 10px 10px 10px 0; width: 200px; vertical-align: top; }
            .sffc-meta-table td { padding: 10px 0; }
            .sffc-section-title { background: #f0f0f1; padding: 10px; margin: 15px -12px 10px; font-weight: 600; border-left: 3px solid #2271b1; }
            .sffc-meta-table input[type="text"],
            .sffc-meta-table input[type="email"],
            .sffc-meta-table input[type="url"] { width: 100%; }
            .sffc-recruiter-image-preview { display: flex; align-items: center; gap: 15px; }
            .sffc-recruiter-image-preview img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd; }
            .sffc-recruiter-image-preview .sffc-avatar-placeholder { width: 80px; height: 80px; border-radius: 50%; background: #0D353E; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 600; }
            .sffc-recruiter-image-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
            .sffc-url-input-wrapper { margin-top: 10px; display: none; }
            .sffc-url-input-wrapper input { width: 100%; margin-bottom: 5px; }
            .sffc-url-input-actions { display: flex; gap: 5px; }
        </style>
        <script>
        jQuery(document).ready(function($) {
            var frame;
            $('#sffc_upload_recruiter_image').on('click', function(e) {
                e.preventDefault();
                if (frame) { frame.open(); return; }
                frame = wp.media({
                    title: 'Select Recruiter Photo',
                    button: { text: 'Use this photo' },
                    multiple: false,
                    library: { type: 'image' }
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#recruiter_image_id').val(attachment.id);
                    $('#recruiter_image_url_field').val('');
                    var thumbUrl = attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                    $('#sffc_recruiter_image_preview').html('<img src="' + thumbUrl + '" alt="">');
                    $('#sffc_remove_recruiter_image').show();
                });
                frame.open();
            });
            $('#sffc_remove_recruiter_image').on('click', function(e) {
                e.preventDefault();
                $('#recruiter_image_id').val('');
                $('#recruiter_image_url_field').val('');
                var initial = $('#recruiter_name').val() ? $('#recruiter_name').val().charAt(0).toUpperCase() : 'R';
                $('#sffc_recruiter_image_preview').html('<div class="sffc-avatar-placeholder">' + initial + '</div>');
                $(this).hide();
            });

            // Paste URL button
            $('#sffc_paste_url_image').on('click', function(e) {
                e.preventDefault();
                $('#sffc_url_input_wrapper').slideDown(200);
                $('#sffc_url_input').focus();
            });

            // Cancel URL paste
            $('#sffc_cancel_url').on('click', function(e) {
                e.preventDefault();
                $('#sffc_url_input_wrapper').slideUp(200);
                $('#sffc_url_input').val('');
            });

            // Apply URL
            $('#sffc_apply_url').on('click', function(e) {
                e.preventDefault();
                var url = $('#sffc_url_input').val().trim();
                if (url) {
                    $('#recruiter_image_id').val('');
                    $('#recruiter_image_url_field').val(url);
                    $('#sffc_recruiter_image_preview').html('<img src="' + url + '" alt="" onerror="this.parentElement.innerHTML=\'<div class=sffc-avatar-placeholder>!</div>\'">');
                    $('#sffc_remove_recruiter_image').show();
                    $('#sffc_url_input_wrapper').slideUp(200);
                    $('#sffc_url_input').val('');
                }
            });

            // Enter key to apply
            $('#sffc_url_input').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#sffc_apply_url').click();
                }
            });
        });
        </script>

        <h4 class="sffc-section-title">Recruiter Information</h4>
        <table class="sffc-meta-table">
            <tr>
                <th><label><?php esc_html_e('Recruiter Photo', 'senna-finance'); ?></label></th>
                <td>
                    <div class="sffc-recruiter-image-preview">
                        <div id="sffc_recruiter_image_preview">
                            <?php if ($recruiter_image_url): ?>
                                <img src="<?php echo esc_url($recruiter_image_url); ?>" alt="">
                            <?php else: ?>
                                <div class="sffc-avatar-placeholder"><?php echo esc_html(substr($recruiter_name ?: 'R', 0, 1)); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="sffc-recruiter-image-actions">
                            <input type="hidden" id="recruiter_image_id" name="recruiter_image_id" value="<?php echo esc_attr($recruiter_image_id); ?>">
                            <input type="hidden" id="recruiter_image_url_field" name="recruiter_image_url" value="<?php echo esc_attr($recruiter_image_external_url); ?>">
                            <button type="button" id="sffc_upload_recruiter_image" class="button"><?php esc_html_e('Upload', 'senna-finance'); ?></button>
                            <button type="button" id="sffc_paste_url_image" class="button"><?php esc_html_e('Paste URL', 'senna-finance'); ?></button>
                            <button type="button" id="sffc_remove_recruiter_image" class="button" style="<?php echo ($recruiter_image_id || $recruiter_image_external_url) ? '' : 'display:none;'; ?>"><?php esc_html_e('Remove', 'senna-finance'); ?></button>
                        </div>
                        <div id="sffc_url_input_wrapper" class="sffc-url-input-wrapper">
                            <input type="url" id="sffc_url_input" placeholder="<?php esc_attr_e('Paste image URL here...', 'senna-finance'); ?>">
                            <div class="sffc-url-input-actions">
                                <button type="button" id="sffc_apply_url" class="button button-primary button-small"><?php esc_html_e('Apply', 'senna-finance'); ?></button>
                                <button type="button" id="sffc_cancel_url" class="button button-small"><?php esc_html_e('Cancel', 'senna-finance'); ?></button>
                            </div>
                        </div>
                    </div>
                    <p class="description"><?php esc_html_e('Upload a photo or paste an external image URL. If none is set, the initial will be shown.', 'senna-finance'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="recruiter_name"><?php esc_html_e('Recruiter Name', 'senna-finance'); ?></label></th>
                <td>
                    <input type="text" id="recruiter_name" name="recruiter_name" value="<?php echo esc_attr($recruiter_name); ?>" />
                    <p class="description"><?php esc_html_e('Full name of the recruiter', 'senna-finance'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="recruiter_title"><?php esc_html_e('Recruiter Title', 'senna-finance'); ?></label></th>
                <td>
                    <input type="text" id="recruiter_title" name="recruiter_title" value="<?php echo esc_attr($recruiter_title); ?>" />
                    <p class="description"><?php esc_html_e('e.g., Senior Consultant, Director', 'senna-finance'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="recruiter_company"><?php esc_html_e('Recruitment Firm', 'senna-finance'); ?></label></th>
                <td>
                    <input type="text" id="recruiter_company" name="recruiter_company" value="<?php echo esc_attr($recruiter_company); ?>" />
                    <p class="description"><?php esc_html_e('Name of the recruitment agency', 'senna-finance'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="recruiter_email"><?php esc_html_e('Recruiter Email', 'senna-finance'); ?></label></th>
                <td>
                    <input type="email" id="recruiter_email" name="recruiter_email" value="<?php echo esc_attr($recruiter_email); ?>" />
                </td>
            </tr>
            <tr>
                <th><label for="recruiter_linkedin"><?php esc_html_e('Recruiter LinkedIn', 'senna-finance'); ?></label></th>
                <td>
                    <input type="url" id="recruiter_linkedin" name="recruiter_linkedin" value="<?php echo esc_url($recruiter_linkedin); ?>" />
                </td>
            </tr>
        </table>

        <h4 class="sffc-section-title">Role Information</h4>
        <table class="sffc-meta-table">
            <tr>
                <th><label for="job_title"><?php esc_html_e('Job Title', 'senna-finance'); ?></label></th>
                <td>
                    <input type="text" id="job_title" name="job_title" value="<?php echo esc_attr($job_title); ?>" />
                    <p class="description"><?php esc_html_e('The actual role title (e.g., Associate, VP)', 'senna-finance'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="company_name"><?php esc_html_e('Hiring Company', 'senna-finance'); ?></label></th>
                <td>
                    <input type="text" id="company_name" name="company_name" value="<?php echo esc_attr($company_name); ?>" />
                    <p class="description"><?php esc_html_e('Leave blank for confidential roles', 'senna-finance'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="job_location"><?php esc_html_e('Location', 'senna-finance'); ?></label></th>
                <td>
                    <input type="text" id="job_location" name="job_location" value="<?php echo esc_attr($job_location); ?>" />
                    <p class="description"><?php esc_html_e('City, Country', 'senna-finance'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function render_requirements_meta_box($post)
    {
        $salary_min = get_post_meta($post->ID, '_salary_min', true);
        $salary_max = get_post_meta($post->ID, '_salary_max', true);
        $salary_currency = get_post_meta($post->ID, '_salary_currency', true) ?: 'AED';
        $experience_years = get_post_meta($post->ID, '_experience_years', true);
        $key_requirements = get_post_meta($post->ID, '_key_requirements', true);
        $ideal_background = get_post_meta($post->ID, '_ideal_background', true);
        ?>
        <table class="sffc-meta-table">
            <tr>
                <th><label for="salary_min"><?php esc_html_e('Salary Range', 'senna-finance'); ?></label></th>
                <td>
                    <select id="salary_currency" name="salary_currency" style="width: 80px;">
                        <option value="AED" <?php selected($salary_currency, 'AED'); ?>>AED</option>
                        <option value="USD" <?php selected($salary_currency, 'USD'); ?>>USD</option>
                        <option value="SAR" <?php selected($salary_currency, 'SAR'); ?>>SAR</option>
                        <option value="QAR" <?php selected($salary_currency, 'QAR'); ?>>QAR</option>
                        <option value="GBP" <?php selected($salary_currency, 'GBP'); ?>>GBP</option>
                        <option value="EUR" <?php selected($salary_currency, 'EUR'); ?>>EUR</option>
                    </select>
                    <input type="number" id="salary_min" name="salary_min" value="<?php echo esc_attr($salary_min); ?>" style="width: 120px;" placeholder="Min" />
                    <span> - </span>
                    <input type="number" id="salary_max" name="salary_max" value="<?php echo esc_attr($salary_max); ?>" style="width: 120px;" placeholder="Max" />
                    <span> per year</span>
                </td>
            </tr>
            <tr>
                <th><label for="experience_years"><?php esc_html_e('Experience Required', 'senna-finance'); ?></label></th>
                <td>
                    <input type="text" id="experience_years" name="experience_years" value="<?php echo esc_attr($experience_years); ?>" style="width: 200px;" />
                    <p class="description"><?php esc_html_e('e.g., "3-5 years" or "5+ years"', 'senna-finance'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="key_requirements"><?php esc_html_e('Key Requirements', 'senna-finance'); ?></label></th>
                <td>
                    <textarea id="key_requirements" name="key_requirements" rows="4" class="large-text"><?php echo esc_textarea($key_requirements); ?></textarea>
                    <p class="description"><?php esc_html_e('One requirement per line', 'senna-finance'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="ideal_background"><?php esc_html_e('Ideal Background', 'senna-finance'); ?></label></th>
                <td>
                    <textarea id="ideal_background" name="ideal_background" rows="3" class="large-text"><?php echo esc_textarea($ideal_background); ?></textarea>
                    <p class="description"><?php esc_html_e('Describe the ideal candidate profile', 'senna-finance'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function render_display_meta_box($post)
    {
        $is_featured = get_post_meta($post->ID, '_is_featured', true);
        $is_urgent = get_post_meta($post->ID, '_is_urgent', true);
        $expire_date = get_post_meta($post->ID, '_expire_date', true);
        $post_date_display = get_post_meta($post->ID, '_post_date_display', true);
        ?>
        <p>
            <label>
                <input type="checkbox" name="is_featured" value="1" <?php checked($is_featured, '1'); ?> />
                <?php esc_html_e('Featured Post', 'senna-finance'); ?>
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="is_urgent" value="1" <?php checked($is_urgent, '1'); ?> />
                <?php esc_html_e('Urgent Hiring', 'senna-finance'); ?>
            </label>
        </p>
        <p>
            <label for="expire_date"><?php esc_html_e('Expiry Date', 'senna-finance'); ?></label><br>
            <input type="date" id="expire_date" name="expire_date" value="<?php echo esc_attr($expire_date); ?>" style="width: 100%;" />
        </p>
        <p>
            <label for="post_date_display"><?php esc_html_e('Display Date', 'senna-finance'); ?></label><br>
            <input type="date" id="post_date_display" name="post_date_display" value="<?php echo esc_attr($post_date_display); ?>" style="width: 100%;" />
            <span class="description"><?php esc_html_e('Override the "posted" date shown to users', 'senna-finance'); ?></span>
        </p>
        <?php
    }

    public function save_meta_boxes($post_id)
    {
        if (!isset($_POST['sffc_recruiter_post_nonce']) ||
            !wp_verify_nonce($_POST['sffc_recruiter_post_nonce'], 'sffc_recruiter_post_details')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Recruiter details
        $text_fields = array(
            'recruiter_name',
            'recruiter_title',
            'recruiter_company',
            'job_title',
            'company_name',
            'job_location',
            'experience_years',
            'salary_currency',
        );

        foreach ($text_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
            }
        }

        // Email field
        if (isset($_POST['recruiter_email'])) {
            update_post_meta($post_id, '_recruiter_email', sanitize_email($_POST['recruiter_email']));
        }

        // URL field
        if (isset($_POST['recruiter_linkedin'])) {
            update_post_meta($post_id, '_recruiter_linkedin', esc_url_raw($_POST['recruiter_linkedin']));
        }

        // Recruiter image field (media library)
        if (isset($_POST['recruiter_image_id'])) {
            $image_id = absint($_POST['recruiter_image_id']);
            if ($image_id) {
                update_post_meta($post_id, '_recruiter_image_id', $image_id);
                // Clear external URL if using media library
                delete_post_meta($post_id, '_recruiter_image_url');
            } else {
                delete_post_meta($post_id, '_recruiter_image_id');
            }
        }

        // Recruiter image external URL
        if (isset($_POST['recruiter_image_url'])) {
            $image_url = esc_url_raw($_POST['recruiter_image_url']);
            if ($image_url) {
                update_post_meta($post_id, '_recruiter_image_url', $image_url);
            } else {
                delete_post_meta($post_id, '_recruiter_image_url');
            }
        }

        // Number fields
        if (isset($_POST['salary_min'])) {
            update_post_meta($post_id, '_salary_min', absint($_POST['salary_min']));
        }
        if (isset($_POST['salary_max'])) {
            update_post_meta($post_id, '_salary_max', absint($_POST['salary_max']));
        }

        // Textarea fields
        if (isset($_POST['key_requirements'])) {
            update_post_meta($post_id, '_key_requirements', sanitize_textarea_field($_POST['key_requirements']));
        }
        if (isset($_POST['ideal_background'])) {
            update_post_meta($post_id, '_ideal_background', sanitize_textarea_field($_POST['ideal_background']));
        }

        // Checkbox fields
        update_post_meta($post_id, '_is_featured', isset($_POST['is_featured']) ? '1' : '0');
        update_post_meta($post_id, '_is_urgent', isset($_POST['is_urgent']) ? '1' : '0');

        // Date fields
        if (isset($_POST['expire_date'])) {
            update_post_meta($post_id, '_expire_date', sanitize_text_field($_POST['expire_date']));
        }
        if (isset($_POST['post_date_display'])) {
            update_post_meta($post_id, '_post_date_display', sanitize_text_field($_POST['post_date_display']));
        }
    }

    public function add_admin_columns($columns)
    {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['recruiter'] = __('Recruiter', 'senna-finance');
                $new_columns['company'] = __('Hiring Company', 'senna-finance');
                $new_columns['location'] = __('Location', 'senna-finance');
            }
        }
        $new_columns['featured'] = __('Featured', 'senna-finance');
        return $new_columns;
    }

    public function render_admin_columns($column, $post_id)
    {
        switch ($column) {
            case 'recruiter':
                $name = get_post_meta($post_id, '_recruiter_name', true);
                $company = get_post_meta($post_id, '_recruiter_company', true);
                echo esc_html($name);
                if ($company) {
                    echo '<br><small>' . esc_html($company) . '</small>';
                }
                break;
            case 'company':
                $company = get_post_meta($post_id, '_company_name', true);
                echo $company ? esc_html($company) : '<em>Confidential</em>';
                break;
            case 'location':
                $location = get_post_meta($post_id, '_job_location', true);
                echo esc_html($location);
                break;
            case 'featured':
                $is_featured = get_post_meta($post_id, '_is_featured', true);
                echo $is_featured ? '<span class="dashicons dashicons-star-filled" style="color: #dba617;"></span>' : '';
                break;
        }
    }

    /**
     * Get recruiter posts for frontend display
     */
    public static function get_posts($args = array())
    {
        $defaults = array(
            'posts_per_page' => 20,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => '_expire_date',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => '_expire_date',
                    'value'   => '',
                    'compare' => '=',
                ),
                array(
                    'key'     => '_expire_date',
                    'value'   => date('Y-m-d'),
                    'compare' => '>=',
                    'type'    => 'DATE',
                ),
            ),
        );

        $args = wp_parse_args($args, $defaults);
        $args['post_type'] = 'sffc_recruiter_post';

        $query = new WP_Query($args);
        $posts = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                $recruiter_image_id = get_post_meta($post_id, '_recruiter_image_id', true);
                $recruiter_image_url = $recruiter_image_id ? wp_get_attachment_image_url($recruiter_image_id, 'thumbnail') : '';

                $posts[] = array(
                    'id'                => $post_id,
                    'title'             => get_the_title(),
                    'content'           => get_the_content(),
                    'excerpt'           => get_the_excerpt(),
                    'date'              => get_the_date('Y-m-d'),
                    'recruiter_name'    => get_post_meta($post_id, '_recruiter_name', true),
                    'recruiter_title'   => get_post_meta($post_id, '_recruiter_title', true),
                    'recruiter_company' => get_post_meta($post_id, '_recruiter_company', true),
                    'recruiter_email'   => get_post_meta($post_id, '_recruiter_email', true),
                    'recruiter_linkedin'=> get_post_meta($post_id, '_recruiter_linkedin', true),
                    'recruiter_image_id'=> $recruiter_image_id,
                    'recruiter_image_url'=> $recruiter_image_url,
                    'job_title'         => get_post_meta($post_id, '_job_title', true),
                    'company_name'      => get_post_meta($post_id, '_company_name', true),
                    'job_location'      => get_post_meta($post_id, '_job_location', true),
                    'salary_min'        => get_post_meta($post_id, '_salary_min', true),
                    'salary_max'        => get_post_meta($post_id, '_salary_max', true),
                    'salary_currency'   => get_post_meta($post_id, '_salary_currency', true) ?: 'AED',
                    'experience_years'  => get_post_meta($post_id, '_experience_years', true),
                    'key_requirements'  => get_post_meta($post_id, '_key_requirements', true),
                    'ideal_background'  => get_post_meta($post_id, '_ideal_background', true),
                    'is_featured'       => get_post_meta($post_id, '_is_featured', true) === '1',
                    'is_urgent'         => get_post_meta($post_id, '_is_urgent', true) === '1',
                    'industries'        => wp_get_post_terms($post_id, 'recruiter_post_industry', array('fields' => 'names')),
                    'post_type'         => wp_get_post_terms($post_id, 'recruiter_post_type', array('fields' => 'names')),
                );
            }
            wp_reset_postdata();
        }

        return $posts;
    }

    /**
     * Get a single recruiter post by ID
     */
    public static function get_post($post_id)
    {
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'sffc_recruiter_post') {
            return null;
        }

        $recruiter_image_id = get_post_meta($post_id, '_recruiter_image_id', true);
        $recruiter_image_url = $recruiter_image_id ? wp_get_attachment_image_url($recruiter_image_id, 'thumbnail') : '';

        return array(
            'id'                => $post_id,
            'title'             => $post->post_title,
            'content'           => $post->post_content,
            'excerpt'           => $post->post_excerpt,
            'date'              => get_the_date('Y-m-d', $post),
            'recruiter_name'    => get_post_meta($post_id, '_recruiter_name', true),
            'recruiter_title'   => get_post_meta($post_id, '_recruiter_title', true),
            'recruiter_company' => get_post_meta($post_id, '_recruiter_company', true),
            'recruiter_email'   => get_post_meta($post_id, '_recruiter_email', true),
            'recruiter_linkedin'=> get_post_meta($post_id, '_recruiter_linkedin', true),
            'recruiter_image_id'=> $recruiter_image_id,
            'recruiter_image_url'=> $recruiter_image_url,
            'job_title'         => get_post_meta($post_id, '_job_title', true),
            'company_name'      => get_post_meta($post_id, '_company_name', true),
            'job_location'      => get_post_meta($post_id, '_job_location', true),
            'salary_min'        => get_post_meta($post_id, '_salary_min', true),
            'salary_max'        => get_post_meta($post_id, '_salary_max', true),
            'salary_currency'   => get_post_meta($post_id, '_salary_currency', true) ?: 'AED',
            'experience_years'  => get_post_meta($post_id, '_experience_years', true),
            'key_requirements'  => get_post_meta($post_id, '_key_requirements', true),
            'ideal_background'  => get_post_meta($post_id, '_ideal_background', true),
            'is_featured'       => get_post_meta($post_id, '_is_featured', true) === '1',
            'is_urgent'         => get_post_meta($post_id, '_is_urgent', true) === '1',
            'industries'        => wp_get_post_terms($post_id, 'recruiter_post_industry', array('fields' => 'names')),
            'post_type'         => wp_get_post_terms($post_id, 'recruiter_post_type', array('fields' => 'names')),
        );
    }

    /**
     * Render admin notice with Create Example Post button
     */
    public function render_create_example_post_notice()
    {
        // Only show on recruiter posts screens
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        // Check if we're on the recruiter posts list or edit page
        $is_recruiter_list = ($screen->id === 'edit-sffc_recruiter_post');
        $is_recruiter_edit = ($screen->id === 'sffc_recruiter_post');

        if (!$is_recruiter_list && !$is_recruiter_edit) {
            return;
        }

        // Check if example post already exists
        $existing = get_posts(array(
            'post_type' => 'sffc_recruiter_post',
            'meta_key' => '_is_example_post',
            'meta_value' => '1',
            'posts_per_page' => 1,
            'post_status' => 'any',
        ));

        // Show success message if just created
        if (isset($_GET['example_created']) && $_GET['example_created'] === '1') {
            $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong>Example recruiter post created successfully!</strong>
                    <?php if ($post_id) : ?>
                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $post_id . '&action=edit')); ?>" class="button button-small" style="margin-left: 10px;">Edit Post</a>
                    <?php endif; ?>
                </p>
                <p>
                    <strong>Shortcode:</strong> <code>[sffc_recruiter_post_article post_id="<?php echo esc_attr($post_id); ?>"]</code>
                </p>
            </div>
            <?php
            return;
        }

        // Only show the create button on the list page (not when editing)
        if (!$is_recruiter_list) {
            return;
        }

        $action_url = wp_nonce_url(
            admin_url('admin-post.php?action=sffc_create_example_recruiter_post'),
            'sffc_create_example_recruiter_post'
        );

        ?>
        <div class="notice notice-info" style="padding: 15px;">
            <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 300px;">
                    <h3 style="margin: 0 0 5px 0;">Test the Recruiter Post Layout</h3>
                    <p style="margin: 0; color: #666;">
                        <?php if (!empty($existing)) : ?>
                            An example recruiter post already exists. You can edit it or create a new one.
                        <?php else : ?>
                            Create an example recruiter post to test the <code>[sffc_recruiter_post_article]</code> shortcode.
                        <?php endif; ?>
                    </p>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <?php if (!empty($existing)) : ?>
                        <a href="<?php echo esc_url(admin_url('post.php?post=' . $existing[0]->ID . '&action=edit')); ?>" class="button button-secondary">
                            Edit Existing Example
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url($action_url); ?>" class="button button-primary">
                        <?php echo !empty($existing) ? 'Create Another Example' : 'Create Example Post'; ?>
                    </a>
                </div>
            </div>
            <?php if (!empty($existing)) : ?>
            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                <strong>Shortcode:</strong> <code>[sffc_recruiter_post_article post_id="<?php echo esc_attr($existing[0]->ID); ?>"]</code>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Handle Create Example Post action
     */
    public function handle_create_example_post()
    {
        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'], 'sffc_create_example_recruiter_post')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_die('You do not have permission to create posts');
        }

        // Create the post
        $post_content = '<h2>About the Role</h2>
<p>We are partnering with a leading global private equity firm to identify a <strong>Vice President</strong> for their Dubai office. This is an exceptional opportunity to join one of the most respected investment platforms in the region.</p>

<p>The successful candidate will play a pivotal role in deal execution, portfolio management, and investor relations across the private equity and North Africa region.</p>

<h2>Key Responsibilities</h2>
<ul>
    <li>Lead deal origination and execution across private equity markets</li>
    <li>Conduct comprehensive due diligence on potential investments</li>
    <li>Build and maintain relationships with portfolio company management teams</li>
    <li>Prepare investment committee materials and presentations</li>
    <li>Support fundraising activities and LP relationship management</li>
    <li>Mentor and develop junior team members</li>
</ul>

<h2>Requirements</h2>
<ul>
    <li>7+ years of experience in private equity, investment banking, or strategy consulting</li>
    <li>Strong financial modeling and analytical skills</li>
    <li>Proven track record in deal execution</li>
    <li>MBA from a top-tier business school preferred</li>
    <li>Fluency in English; Arabic is a plus</li>
    <li>Existing network in the private equity region advantageous</li>
</ul>

<h2>What\'s on Offer</h2>
<ul>
    <li>Competitive base salary with significant carried interest</li>
    <li>Opportunity to work on marquee transactions</li>
    <li>Clear path to Managing Director</li>
    <li>Exposure to global investment committee</li>
    <li>Collaborative and entrepreneurial culture</li>
</ul>

<p><em>This is a retained search being conducted on an exclusive basis. All applications will be treated in strict confidence.</em></p>';

        $post_id = wp_insert_post(array(
            'post_title'   => 'Vice President - Private Equity (private equity)',
            'post_content' => $post_content,
            'post_status'  => 'publish',
            'post_type'    => 'sffc_recruiter_post',
            'post_author'  => get_current_user_id(),
        ));

        if (is_wp_error($post_id)) {
            wp_die('Failed to create post: ' . $post_id->get_error_message());
        }

        // Add meta data
        $meta_data = array(
            '_is_example_post'    => '1',
            '_recruiter_name'     => 'Sarah Mitchell',
            '_recruiter_title'    => 'Director, Financial Services Practice',
            '_recruiter_company'  => 'Heidrick & Struggles',
            '_recruiter_email'    => 'smitchell@heidrick.com',
            '_recruiter_linkedin' => 'https://linkedin.com/in/sarahmitchell',
            '_company_name'       => 'Confidential PE Firm',
            '_job_title'          => 'Vice President',
            '_job_location'       => 'Dubai, UAE',
            '_salary_min'         => '500000',
            '_salary_max'         => '750000',
            '_salary_currency'    => 'AED',
            '_experience_years'   => '7',
            '_key_requirements'   => 'PE/IB experience, MBA preferred, private equity network',
            '_ideal_background'   => 'Top-tier PE fund, bulge bracket IB, or MBB consulting',
            '_is_featured'        => '1',
            '_is_urgent'          => '0',
        );

        foreach ($meta_data as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }

        // Set taxonomies
        wp_set_object_terms($post_id, 'retained-search', 'recruiter_post_type');
        wp_set_object_terms($post_id, 'private-equity', 'recruiter_post_industry');
        wp_set_object_terms($post_id, 'dubai', 'recruiter_post_location');

        // Redirect back to list with success message
        wp_redirect(admin_url('edit.php?post_type=sffc_recruiter_post&example_created=1&post_id=' . $post_id));
        exit;
    }
}

// Initialize
SFFC_Recruiter_Posts::get_instance();
