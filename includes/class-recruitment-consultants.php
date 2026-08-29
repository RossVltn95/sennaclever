<?php
/**
 * Recruitment Consultants Management
 * Handles recruitment consultant post type and related functionality
 * 
 * @package SennaCareers
 * @since 11.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Recruitment_Consultants {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_consultant_meta'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    public function register_post_type() {
        $labels = array(
            'name'                  => _x('Recruitment Consultants', 'Post Type General Name', 'senna-finance'),
            'singular_name'         => _x('Recruitment Consultant', 'Post Type Singular Name', 'senna-finance'),
            'menu_name'             => __('Consultants', 'senna-finance'),
            'name_admin_bar'        => __('Recruitment Consultant', 'senna-finance'),
            'archives'              => __('Consultant Archives', 'senna-finance'),
            'attributes'            => __('Consultant Attributes', 'senna-finance'),
            'parent_item_colon'     => __('Parent Consultant:', 'senna-finance'),
            'all_items'             => __('All Consultants', 'senna-finance'),
            'add_new_item'          => __('Add New Consultant', 'senna-finance'),
            'add_new'               => __('Add New', 'senna-finance'),
            'new_item'              => __('New Consultant', 'senna-finance'),
            'edit_item'             => __('Edit Consultant', 'senna-finance'),
            'update_item'           => __('Update Consultant', 'senna-finance'),
            'view_item'             => __('View Consultant', 'senna-finance'),
            'view_items'            => __('View Consultants', 'senna-finance'),
            'search_items'          => __('Search Consultants', 'senna-finance'),
            'not_found'             => __('Not found', 'senna-finance'),
            'not_found_in_trash'    => __('Not found in Trash', 'senna-finance'),
            'featured_image'        => __('Consultant Photo', 'senna-finance'),
            'set_featured_image'    => __('Set consultant photo', 'senna-finance'),
            'remove_featured_image' => __('Remove consultant photo', 'senna-finance'),
            'use_featured_image'    => __('Use as consultant photo', 'senna-finance'),
            'insert_into_item'      => __('Insert into consultant', 'senna-finance'),
            'uploaded_to_this_item' => __('Uploaded to this consultant', 'senna-finance'),
            'items_list'            => __('Consultants list', 'senna-finance'),
            'items_list_navigation' => __('Consultants list navigation', 'senna-finance'),
            'filter_items_list'     => __('Filter consultants list', 'senna-finance'),
        );
        
        $args = array(
            'label'                 => __('Recruitment Consultant', 'senna-finance'),
            'description'           => __('Recruitment consultants and their information', 'senna-finance'),
            'labels'                => $labels,
            'supports'              => array('title', 'editor', 'thumbnail', 'excerpt', 'revisions'),
            'taxonomies'            => array(),
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => 'sffc-dashboard',
            'menu_position'         => 25,
            'menu_icon'             => 'dashicons-businessperson',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => 'consultants',
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'post',
            'show_in_rest'          => true,
        );
        
        register_post_type('sffc_consultant', $args);
    }
    
    public function add_meta_boxes() {
        add_meta_box(
            'sffc_consultant_details',
            __('Consultant Details', 'senna-finance'),
            array($this, 'render_consultant_details_meta_box'),
            'sffc_consultant',
            'normal',
            'high'
        );
        
        add_meta_box(
            'sffc_consultant_contact',
            __('Contact Information', 'senna-finance'),
            array($this, 'render_consultant_contact_meta_box'),
            'sffc_consultant',
            'normal',
            'high'
        );
        
        add_meta_box(
            'sffc_consultant_specialties',
            __('Specialties & Expertise', 'senna-finance'),
            array($this, 'render_consultant_specialties_meta_box'),
            'sffc_consultant',
            'normal',
            'high'
        );
        
        add_meta_box(
            'sffc_consultant_reviews',
            __('Reviews & Testimonials', 'senna-finance'),
            array($this, 'render_consultant_reviews_meta_box'),
            'sffc_consultant',
            'normal',
            'high'
        );
    }
    
    public function render_consultant_details_meta_box($post) {
        wp_nonce_field('sffc_consultant_meta_nonce', 'sffc_consultant_meta_nonce');
        
        $job_title = get_post_meta($post->ID, '_sffc_consultant_job_title', true);
        $company = get_post_meta($post->ID, '_sffc_consultant_company', true);
        $years_experience = get_post_meta($post->ID, '_sffc_consultant_years_experience', true);
        $linkedin_url = get_post_meta($post->ID, '_sffc_consultant_linkedin', true);
        $location = get_post_meta($post->ID, '_sffc_consultant_location', true);
        $languages = get_post_meta($post->ID, '_sffc_consultant_languages', true);
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sffc_consultant_job_title"><?php _e('Job Title', 'senna-finance'); ?></label>
                </th>
                <td>
                    <input type="text" id="sffc_consultant_job_title" name="sffc_consultant_job_title" 
                           value="<?php echo esc_attr($job_title); ?>" class="regular-text" 
                           placeholder="Senior Recruitment Consultant" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sffc_consultant_company"><?php _e('Company/Organization', 'senna-finance'); ?></label>
                </th>
                <td>
                    <input type="text" id="sffc_consultant_company" name="sffc_consultant_company" 
                           value="<?php echo esc_attr($company); ?>" class="regular-text" 
                           placeholder="MENA Careers Career Services" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sffc_consultant_years_experience"><?php _e('Years of Experience', 'senna-finance'); ?></label>
                </th>
                <td>
                    <input type="number" id="sffc_consultant_years_experience" name="sffc_consultant_years_experience" 
                           value="<?php echo esc_attr($years_experience); ?>" min="0" max="50" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sffc_consultant_location"><?php _e('Location', 'senna-finance'); ?></label>
                </th>
                <td>
                    <input type="text" id="sffc_consultant_location" name="sffc_consultant_location" 
                           value="<?php echo esc_attr($location); ?>" class="regular-text" 
                           placeholder="London, UK" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sffc_consultant_linkedin"><?php _e('LinkedIn Profile', 'senna-finance'); ?></label>
                </th>
                <td>
                    <input type="url" id="sffc_consultant_linkedin" name="sffc_consultant_linkedin" 
                           value="<?php echo esc_attr($linkedin_url); ?>" class="regular-text" 
                           placeholder="https://linkedin.com/in/profile" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sffc_consultant_languages"><?php _e('Languages', 'senna-finance'); ?></label>
                </th>
                <td>
                    <input type="text" id="sffc_consultant_languages" name="sffc_consultant_languages" 
                           value="<?php echo esc_attr($languages); ?>" class="regular-text" 
                           placeholder="English, French, German" />
                    <p class="description"><?php _e('Comma-separated list of languages spoken', 'senna-finance'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    public function render_consultant_contact_meta_box($post) {
        $email = get_post_meta($post->ID, '_sffc_consultant_email', true);
        $phone = get_post_meta($post->ID, '_sffc_consultant_phone', true);
        $calendar_link = get_post_meta($post->ID, '_sffc_consultant_calendar_link', true);
        $show_contact_publicly = get_post_meta($post->ID, '_sffc_consultant_show_contact_publicly', true);
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sffc_consultant_email"><?php _e('Email Address', 'senna-finance'); ?></label>
                </th>
                <td>
                    <input type="email" id="sffc_consultant_email" name="sffc_consultant_email" 
                           value="<?php echo esc_attr($email); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sffc_consultant_phone"><?php _e('Phone Number', 'senna-finance'); ?></label>
                </th>
                <td>
                    <input type="tel" id="sffc_consultant_phone" name="sffc_consultant_phone" 
                           value="<?php echo esc_attr($phone); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sffc_consultant_calendar_link"><?php _e('Calendar Booking Link', 'senna-finance'); ?></label>
                </th>
                <td>
                    <input type="url" id="sffc_consultant_calendar_link" name="sffc_consultant_calendar_link" 
                           value="<?php echo esc_attr($calendar_link); ?>" class="regular-text" 
                           placeholder="https://calendly.com/consultant" />
                    <p class="description"><?php _e('Calendly, Acuity, or other booking system link', 'senna-finance'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sffc_consultant_show_contact_publicly"><?php _e('Public Contact Display', 'senna-finance'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="sffc_consultant_show_contact_publicly" 
                               name="sffc_consultant_show_contact_publicly" value="1" 
                               <?php checked($show_contact_publicly, '1'); ?> />
                        <?php _e('Show contact details to logged-in premium users', 'senna-finance'); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }
    
    public function render_consultant_specialties_meta_box($post) {
        $specialties = get_post_meta($post->ID, '_sffc_consultant_specialties', true);
        $sectors = get_post_meta($post->ID, '_sffc_consultant_sectors', true);
        $job_levels = get_post_meta($post->ID, '_sffc_consultant_job_levels', true);
        $salary_range = get_post_meta($post->ID, '_sffc_consultant_salary_range', true);
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sffc_consultant_specialties"><?php _e('Recruitment Specialties', 'senna-finance'); ?></label>
                </th>
                <td>
                    <textarea id="sffc_consultant_specialties" name="sffc_consultant_specialties" 
                              rows="3" class="large-text"><?php echo esc_textarea($specialties); ?></textarea>
                    <p class="description"><?php _e('E.g., Private Equity, Investment Banking, Asset Management', 'senna-finance'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sffc_consultant_sectors"><?php _e('Industry Sectors', 'senna-finance'); ?></label>
                </th>
                <td>
                    <textarea id="sffc_consultant_sectors" name="sffc_consultant_sectors" 
                              rows="3" class="large-text"><?php echo esc_textarea($sectors); ?></textarea>
                    <p class="description"><?php _e('Specific industries or sectors of expertise', 'senna-finance'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sffc_consultant_job_levels"><?php _e('Job Levels', 'senna-finance'); ?></label>
                </th>
                <td>
                    <select id="sffc_consultant_job_levels" name="sffc_consultant_job_levels[]" multiple class="regular-text">
                        <?php
                        $levels = array(
                            'entry' => 'Entry Level',
                            'associate' => 'Associate',
                            'vice_president' => 'Vice President',
                            'director' => 'Director',
                            'managing_director' => 'Managing Director',
                            'partner' => 'Partner',
                            'c_level' => 'C-Level'
                        );
                        $selected_levels = is_array($job_levels) ? $job_levels : explode(',', $job_levels ?? '');
                        foreach ($levels as $value => $label) {
                            $selected = in_array($value, $selected_levels) ? 'selected' : '';
                            echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
                        }
                        ?>
                    </select>
                    <p class="description"><?php _e('Hold Ctrl/Cmd to select multiple levels', 'senna-finance'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sffc_consultant_salary_range"><?php _e('Typical Salary Range', 'senna-finance'); ?></label>
                </th>
                <td>
                    <input type="text" id="sffc_consultant_salary_range" name="sffc_consultant_salary_range" 
                           value="<?php echo esc_attr($salary_range); ?>" class="regular-text" 
                           placeholder="£50K - £200K+" />
                </td>
            </tr>
        </table>
        <?php
    }
    
    public function render_consultant_reviews_meta_box($post) {
        $reviews = get_post_meta($post->ID, '_sffc_consultant_reviews', true);
        if (!is_array($reviews)) {
            $reviews = array();
        }
        ?>
        <div id="sffc-consultant-reviews">
            <div id="reviews-container">
                <?php foreach ($reviews as $index => $review): ?>
                <div class="review-item" data-index="<?php echo $index; ?>">
                    <table class="form-table">
                        <tr>
                            <th scope="row">Client Name</th>
                            <td>
                                <input type="text" name="reviews[<?php echo $index; ?>][client_name]" 
                                       value="<?php echo esc_attr($review['client_name'] ?? ''); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Company</th>
                            <td>
                                <input type="text" name="reviews[<?php echo $index; ?>][company]" 
                                       value="<?php echo esc_attr($review['company'] ?? ''); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Rating</th>
                            <td>
                                <select name="reviews[<?php echo $index; ?>][rating]">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php selected($review['rating'] ?? 5, $i); ?>>
                                        <?php echo $i; ?> <?php echo $i === 1 ? 'Star' : 'Stars'; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Review Text</th>
                            <td>
                                <textarea name="reviews[<?php echo $index; ?>][review_text]" 
                                          rows="3" class="large-text"><?php echo esc_textarea($review['review_text'] ?? ''); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <button type="button" class="button remove-review">Remove Review</button>
                            </td>
                        </tr>
                    </table>
                    <hr>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="add-review" class="button button-secondary">Add Review</button>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var reviewIndex = <?php echo count($reviews); ?>;
            
            $('#add-review').click(function() {
                var reviewHtml = `
                <div class="review-item" data-index="${reviewIndex}">
                    <table class="form-table">
                        <tr>
                            <th scope="row">Client Name</th>
                            <td><input type="text" name="reviews[${reviewIndex}][client_name]" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row">Company</th>
                            <td><input type="text" name="reviews[${reviewIndex}][company]" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row">Rating</th>
                            <td>
                                <select name="reviews[${reviewIndex}][rating]">
                                    <option value="5">5 Stars</option>
                                    <option value="4">4 Stars</option>
                                    <option value="3">3 Stars</option>
                                    <option value="2">2 Stars</option>
                                    <option value="1">1 Star</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Review Text</th>
                            <td><textarea name="reviews[${reviewIndex}][review_text]" rows="3" class="large-text"></textarea></td>
                        </tr>
                        <tr>
                            <td colspan="2"><button type="button" class="button remove-review">Remove Review</button></td>
                        </tr>
                    </table>
                    <hr>
                </div>`;
                $('#reviews-container').append(reviewHtml);
                reviewIndex++;
            });
            
            $(document).on('click', '.remove-review', function() {
                $(this).closest('.review-item').remove();
            });
        });
        </script>
        <?php
    }
    
    public function save_consultant_meta($post_id) {
        if (!isset($_POST['sffc_consultant_meta_nonce']) || 
            !wp_verify_nonce($_POST['sffc_consultant_meta_nonce'], 'sffc_consultant_meta_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (get_post_type($post_id) !== 'sffc_consultant') {
            return;
        }
        
        // Save consultant details
        $fields = array(
            'sffc_consultant_job_title',
            'sffc_consultant_company',
            'sffc_consultant_years_experience',
            'sffc_consultant_location',
            'sffc_consultant_linkedin',
            'sffc_consultant_languages',
            'sffc_consultant_email',
            'sffc_consultant_phone',
            'sffc_consultant_calendar_link',
            'sffc_consultant_specialties',
            'sffc_consultant_sectors',
            'sffc_consultant_salary_range'
        );
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
            }
        }
        
        // Save checkbox fields
        update_post_meta($post_id, '_sffc_consultant_show_contact_publicly', 
                        isset($_POST['sffc_consultant_show_contact_publicly']) ? '1' : '0');
        
        // Save job levels
        if (isset($_POST['sffc_consultant_job_levels'])) {
            update_post_meta($post_id, '_sffc_consultant_job_levels', $_POST['sffc_consultant_job_levels']);
        }
        
        // Save reviews
        if (isset($_POST['reviews'])) {
            $reviews = array();
            foreach ($_POST['reviews'] as $review) {
                if (!empty($review['client_name']) || !empty($review['review_text'])) {
                    $reviews[] = array(
                        'client_name' => sanitize_text_field($review['client_name']),
                        'company' => sanitize_text_field($review['company']),
                        'rating' => intval($review['rating']),
                        'review_text' => sanitize_textarea_field($review['review_text'])
                    );
                }
            }
            update_post_meta($post_id, '_sffc_consultant_reviews', $reviews);
        }
    }
    
    // Job integration methods
    public function add_job_consultant_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['consultant'] = __('Consultant', 'senna-finance');
            }
        }
        return $new_columns;
    }
    
    public function show_job_consultant_column($column, $post_id) {
        if ($column === 'consultant') {
            $consultant_id = get_post_meta($post_id, '_sffc_job_consultant_id', true);
            if ($consultant_id) {
                $consultant = get_post($consultant_id);
                if ($consultant) {
                    echo '<a href="' . get_edit_post_link($consultant_id) . '">' . esc_html($consultant->post_title) . '</a>';
                } else {
                    echo '<span style="color: #999;">Consultant not found</span>';
                }
            } else {
                echo '<span style="color: #999;">—</span>';
            }
        }
    }
    
    public function add_job_consultant_meta_box() {
        add_meta_box(
            'sffc_job_consultant',
            __('Recruitment Consultant', 'senna-finance'),
            array($this, 'render_job_consultant_meta_box'),
            'sffc_job',
            'side',
            'default'
        );
    }
    
    public function render_job_consultant_meta_box($post) {
        wp_nonce_field('sffc_job_consultant_nonce', 'sffc_job_consultant_nonce');
        
        $consultant_id = get_post_meta($post->ID, '_sffc_job_consultant_id', true);
        $application_url = get_post_meta($post->ID, '_sffc_job_application_url', true);
        
        // Get all consultants
        $consultants = get_posts(array(
            'post_type' => 'sffc_consultant',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sffc_job_consultant_id"><?php _e('Assigned Consultant', 'senna-finance'); ?></label>
                </th>
                <td>
                    <select id="sffc_job_consultant_id" name="sffc_job_consultant_id" class="widefat">
                        <option value=""><?php _e('Select Consultant', 'senna-finance'); ?></option>
                        <?php foreach ($consultants as $consultant): ?>
                        <option value="<?php echo $consultant->ID; ?>" <?php selected($consultant_id, $consultant->ID); ?>>
                            <?php echo esc_html($consultant->post_title); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sffc_job_application_url"><?php _e('Application URL', 'senna-finance'); ?></label>
                </th>
                <td>
                    <input type="url" id="sffc_job_application_url" name="sffc_job_application_url" 
                           value="<?php echo esc_attr($application_url); ?>" class="widefat" 
                           placeholder="https://company.com/apply" />
                    <p class="description"><?php _e('Direct link to job application form', 'senna-finance'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    public function save_job_consultant_meta($post_id) {
        if (!isset($_POST['sffc_job_consultant_nonce']) || 
            !wp_verify_nonce($_POST['sffc_job_consultant_nonce'], 'sffc_job_consultant_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (get_post_type($post_id) !== 'sffc_job') {
            return;
        }
        
        if (isset($_POST['sffc_job_consultant_id'])) {
            update_post_meta($post_id, '_sffc_job_consultant_id', intval($_POST['sffc_job_consultant_id']));
        }
        
        if (isset($_POST['sffc_job_application_url'])) {
            update_post_meta($post_id, '_sffc_job_application_url', esc_url_raw($_POST['sffc_job_application_url']));
        }
    }
    
    public function enqueue_admin_assets($hook) {
        global $post_type;
        
        if (($hook === 'post.php' || $hook === 'post-new.php') && $post_type === 'sffc_consultant') {
            wp_enqueue_media();
            wp_enqueue_script('jquery');
        }
    }
    
    // Helper methods for frontend
    public static function get_consultant_details($consultant_id) {
        if (!$consultant_id) {
            return false;
        }
        
        $consultant = get_post($consultant_id);
        if (!$consultant || $consultant->post_type !== 'sffc_consultant') {
            return false;
        }
        
        return array(
            'id' => $consultant->ID,
            'name' => $consultant->post_title,
            'bio' => $consultant->post_content,
            'excerpt' => $consultant->post_excerpt,
            'job_title' => get_post_meta($consultant->ID, '_sffc_consultant_job_title', true),
            'company' => get_post_meta($consultant->ID, '_sffc_consultant_company', true),
            'years_experience' => get_post_meta($consultant->ID, '_sffc_consultant_years_experience', true),
            'location' => get_post_meta($consultant->ID, '_sffc_consultant_location', true),
            'linkedin' => get_post_meta($consultant->ID, '_sffc_consultant_linkedin', true),
            'languages' => get_post_meta($consultant->ID, '_sffc_consultant_languages', true),
            'email' => get_post_meta($consultant->ID, '_sffc_consultant_email', true),
            'phone' => get_post_meta($consultant->ID, '_sffc_consultant_phone', true),
            'calendar_link' => get_post_meta($consultant->ID, '_sffc_consultant_calendar_link', true),
            'show_contact_publicly' => get_post_meta($consultant->ID, '_sffc_consultant_show_contact_publicly', true),
            'specialties' => get_post_meta($consultant->ID, '_sffc_consultant_specialties', true),
            'sectors' => get_post_meta($consultant->ID, '_sffc_consultant_sectors', true),
            'job_levels' => get_post_meta($consultant->ID, '_sffc_consultant_job_levels', true),
            'salary_range' => get_post_meta($consultant->ID, '_sffc_consultant_salary_range', true),
            'reviews' => get_post_meta($consultant->ID, '_sffc_consultant_reviews', true),
            'avatar' => get_the_post_thumbnail_url($consultant->ID, 'medium'),
            'rating' => self::calculate_average_rating($consultant->ID)
        );
    }
    
    public static function calculate_average_rating($consultant_id) {
        $reviews = get_post_meta($consultant_id, '_sffc_consultant_reviews', true);
        if (!is_array($reviews) || empty($reviews)) {
            return 5.0; // Default rating
        }
        
        $total = 0;
        $count = 0;
        foreach ($reviews as $review) {
            if (isset($review['rating']) && is_numeric($review['rating'])) {
                $total += $review['rating'];
                $count++;
            }
        }
        
        return $count > 0 ? round($total / $count, 1) : 5.0;
    }
}

// Initialize
SFFC_Recruitment_Consultants::get_instance();