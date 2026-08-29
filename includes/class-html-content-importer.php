<?php

/**
 * HTML Content Importer
 *
 * Provides a CSV-based workflow to create posts or custom post types
 * using Gutenberg HTML blocks populated with combined column content.
 *
 * @package SennaCareers
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_HTML_Content_Importer
{
    private static $instance = null;

    private $page_slug = 'sffc-html-content-importer';

    private $transient_prefix = 'sffc_html_importer_';

    private $notices_key = 'sffc_html_importer_notice_';

    private $job_prefix = 'sffc_html_import_job_';

    /**
     * Get singleton instance.
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', array($this, 'register_menu'), 20);
        add_action('admin_init', array($this, 'maybe_handle_form_submission'));
        add_action('admin_notices', array($this, 'render_notices'));
        add_action('wp_ajax_sffc_html_importer_init', array($this, 'ajax_init_import'));
        add_action('wp_ajax_sffc_html_importer_run', array($this, 'ajax_run_import'));
    }

    /**
     * Register admin submenu.
     */
    public function register_menu()
    {
        add_submenu_page(
            'sffc-dashboard',
            __('HTML Importer', 'senna-finance'),
            __('HTML Importer', 'senna-finance'),
            'manage_options',
            $this->page_slug,
            array($this, 'render_page')
        );
    }

    /**
     * Handle uploads and imports.
     */
    public function maybe_handle_form_submission()
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['sffc_html_importer_clear'])) {
            $this->handle_clear_request();
        }

        if (empty($_POST['sffc_html_importer_action'])) {
            return;
        }

        $action = sanitize_text_field(wp_unslash($_POST['sffc_html_importer_action']));

        if ('upload' === $action) {
            $this->handle_upload();
        } elseif ('import' === $action) {
            $this->handle_import();
        }
    }

    /**
     * Render admin notices from transient store.
     */
    public function render_notices()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $notice = $this->get_notice();

        if (empty($notice)) {
            return;
        }

        $class = 'success' === $notice['type'] ? 'notice notice-success' : 'notice notice-error';

        echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($notice['message']) . '</p>';

        if (!empty($notice['details']['errors'])) {
            echo '<ul style="margin-left:20px;">';
            foreach ($notice['details']['errors'] as $error_message) {
                echo '<li>' . esc_html($error_message) . '</li>';
            }
            echo '</ul>';
        }

        echo '</div>';

        $this->clear_notice();
    }

    /**
     * Render main importer page.
     */
    public function render_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $state_token = isset($_GET['import_key']) ? sanitize_key($_GET['import_key']) : '';
        $state = $state_token ? $this->get_upload_state($state_token) : null;

        $page_url = $this->get_page_url();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('HTML Content Importer', 'senna-finance') . '</h1>';

        if ($state && !empty($state['headers'])) {
            $this->render_mapping_form($state_token, $state, $page_url);
        } else {
            $this->render_upload_form();
        }

        echo '</div>';
    }

    private function render_upload_form()
    {
        $page_url = $this->get_page_url();

        echo '<div class="card">';
        echo '<h2>' . esc_html__('Step 1: Upload CSV', 'senna-finance') . '</h2>';
        echo '<p>' . esc_html__('Choose a CSV file containing a title column and one or more HTML columns you would like to merge into a Custom HTML block.', 'senna-finance') . '</p>';
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url($page_url) . '">';
        wp_nonce_field('sffc_html_importer_upload', 'sffc_html_importer_nonce');
        echo '<input type="hidden" name="sffc_html_importer_action" value="upload">';
        echo '<table class="form-table">';
        echo '<tr><th scope="row"><label for="sffc-html-importer-file">' . esc_html__('CSV File', 'senna-finance') . '</label></th>';
        echo '<td><input type="file" id="sffc-html-importer-file" name="import_file" accept=".csv" required></td></tr>';
        echo '<tr><th scope="row"><label for="sffc-html-importer-delimiter">' . esc_html__('Delimiter (optional)', 'senna-finance') . '</label></th>';
        echo '<td><input type="text" id="sffc-html-importer-delimiter" name="delimiter" placeholder="," style="width:80px;"><p class="description">' . esc_html__('Leave blank to auto-detect.', 'senna-finance') . '</p></td></tr>';
        echo '</table>';
        submit_button(__('Continue to Column Mapping', 'senna-finance'));
        echo '</form>';
        echo '</div>';
    }

    private function render_mapping_form($token, $state, $page_url)
    {
        $headers = $state['headers'];
        $sample_rows = $this->get_sample_rows($state['file'], $state['delimiter']);
        $post_types = $this->get_supported_post_types();
        $taxonomy_map = $this->get_taxonomy_map(array_keys($post_types));

        echo '<div class="card">';
        echo '<h2>' . esc_html__('Step 2: Map Columns & Import', 'senna-finance') . '</h2>';
        echo '<p>' . esc_html__('Select which column should become the post title and which columns contain HTML that should be merged into a Custom HTML block.', 'senna-finance') . '</p>';

        echo '<form id="sffc-html-importer-mapping-form" method="post" action="' . esc_url($page_url) . '">';
        wp_nonce_field('sffc_html_importer_import', 'sffc_html_importer_nonce');
        echo '<input type="hidden" name="sffc_html_importer_action" value="import">';
        echo '<input type="hidden" name="import_key" value="' . esc_attr($token) . '">';

        echo '<table class="form-table">';

        echo '<tr><th scope="row"><label for="sffc-html-importer-post-type">' . esc_html__('Post Type', 'senna-finance') . '</label></th>';
        echo '<td><select id="sffc-html-importer-post-type" name="post_type" required>';
        echo '<option value="">' . esc_html__('Select a post type', 'senna-finance') . '</option>';
        foreach ($post_types as $type => $label) {
            echo '<option value="' . esc_attr($type) . '">' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="sffc-html-importer-title">' . esc_html__('Title Column', 'senna-finance') . '</label></th>';
        echo '<td><select id="sffc-html-importer-title" name="title_column" required>';
        echo '<option value="">' . esc_html__('Select column', 'senna-finance') . '</option>';
        foreach ($headers as $index => $header) {
            echo '<option value="' . esc_attr($index) . '">' . esc_html($header) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="sffc-html-importer-content">' . esc_html__('HTML Columns', 'senna-finance') . '</label></th>';
        echo '<td><select id="sffc-html-importer-content" name="content_columns[]" multiple size="6" style="min-width:260px;" required>';
        foreach ($headers as $index => $header) {
            echo '<option value="' . esc_attr($index) . '">' . esc_html($header) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Hold Command (⌘) or Control (Ctrl) to select multiple columns. Content will be joined in the order selected by the CSV.', 'senna-finance') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="sffc-html-importer-separator">' . esc_html__('HTML Joiner', 'senna-finance') . '</label></th>';
        echo '<td><input type="text" id="sffc-html-importer-separator" name="content_separator" value="\n\n" style="width:120px;">';
        echo '<p class="description">' . esc_html__('Use special tokens like \n for new lines or \n\n for paragraph breaks.', 'senna-finance') . '</p></td></tr>';

        echo '<tr><th scope="row"><label for="sffc-html-importer-status">' . esc_html__('Post Status', 'senna-finance') . '</label></th>';
        echo '<td><select id="sffc-html-importer-status" name="post_status" required>';
        echo '<option value="draft">' . esc_html__('Draft', 'senna-finance') . '</option>';
        echo '<option value="publish">' . esc_html__('Publish', 'senna-finance') . '</option>';
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><span>' . esc_html__('Assign Categories & Terms', 'senna-finance') . '</span></th>';
        echo '<td>';

        if (!empty($taxonomy_map)) {
            echo '<p class="description">' . esc_html__('After choosing a post type, pick any categories or taxonomy terms to apply to every imported post.', 'senna-finance') . '</p>';
            echo '<div id="sffc-html-importer-taxonomy-container">';

            foreach ($taxonomy_map as $post_type_key => $taxonomies) {
                $style = 'style="display:none;"';
                echo '<div class="sffc-html-importer-tax-group" data-post-type="' . esc_attr($post_type_key) . '" ' . $style . '>';

                if (empty($taxonomies)) {
                    echo '<p class="description">' . esc_html__('This post type does not have any public taxonomies.', 'senna-finance') . '</p>';
                } else {
                    foreach ($taxonomies as $taxonomy => $taxonomy_data) {
                        $size = max(4, min(12, count($taxonomy_data['terms'])));
                        echo '<label for="sffc-html-importer-tax-' . esc_attr($taxonomy) . '"><strong>' . esc_html($taxonomy_data['label']) . '</strong></label>';
                        echo '<select id="sffc-html-importer-tax-' . esc_attr($taxonomy) . '" name="tax_input[' . esc_attr($taxonomy) . '][]" multiple size="' . esc_attr($size) . '" disabled style="min-width:260px;">';

                        if (empty($taxonomy_data['terms'])) {
                            echo '<option value="" disabled>' . esc_html__('No terms found', 'senna-finance') . '</option>';
                        } else {
                            foreach ($taxonomy_data['terms'] as $term) {
                                echo '<option value="' . esc_attr($term['id']) . '">' . esc_html($term['label']) . '</option>';
                            }
                        }

                        echo '</select>';
                    }
                }

                echo '</div>';
            }

            echo '</div>';
        } else {
            echo '<p class="description">' . esc_html__('No public taxonomies are available for the selected content types.', 'senna-finance') . '</p>';
        }

        echo '</td></tr>';

        echo '</table>';

        if (!empty($sample_rows)) {
            echo '<h3>' . esc_html__('Preview', 'senna-finance') . '</h3>';
            echo '<table class="widefat striped" style="max-width:100%;">';
            echo '<thead><tr>';
            foreach ($headers as $header) {
                echo '<th>' . esc_html($header) . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ($sample_rows as $row) {
                echo '<tr>';
                foreach ($headers as $index => $header) {
                    $value = isset($row[$index]) ? $row[$index] : '';
                    echo '<td><code style="white-space:pre-wrap; display:block; max-height:120px; overflow:auto;">' . esc_html($this->truncate_preview($value)) . '</code></td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        submit_button(__('Import Content', 'senna-finance'));

        echo '<div id="sffc-html-importer-progress" class="notice notice-info" style="display:none; margin-top:20px;">';
        echo '<p><strong>' . esc_html__('Importing… Please keep this tab open.', 'senna-finance') . '</strong></p>';
        echo '<progress value="0" max="100" style="width:100%; height:18px;"></progress>';
        echo '<p class="sffc-progress-text" style="margin-top:8px;"></p>';
        echo '<div class="sffc-progress-errors" style="display:none; margin-top:12px;">';
        echo '<p><strong>' . esc_html__('Issues encountered:', 'senna-finance') . '</strong></p>';
        echo '<ul style="margin-left:20px;"></ul>';
        echo '</div>';
        echo '<div class="sffc-progress-summary" style="display:none; margin-top:12px;"></div>';
        echo '</div>';

        $clear_url = wp_nonce_url(add_query_arg('sffc_html_importer_clear', rawurlencode($token), $page_url), 'sffc_html_importer_clear', '_wpnonce');
        echo '<a href="' . esc_url($clear_url) . '" class="button-link" style="margin-left:12px;">' . esc_html__('Start Over', 'senna-finance') . '</a>';

        echo '</form>';

        $this->render_mapping_form_script();
        echo '</div>';
    }

    private function render_mapping_form_script()
    {
        static $printed = false;

        if ($printed) {
            return;
        }

        $printed = true;

        $strings = array(
            'preparing'   => __('Preparing import…', 'senna-finance'),
            'init_error'  => __('Unable to start the import.', 'senna-finance'),
            'batch_error' => __('Import halted due to an error.', 'senna-finance'),
            'complete_title' => __('Import finished', 'senna-finance'),
            'complete_text'  => __('Imported %1$d posts and skipped %2$d rows.', 'senna-finance'),
        );

        echo '<script type="text/javascript">(function(window, document){';
        echo 'const form=document.getElementById("sffc-html-importer-mapping-form");';
        echo 'if(!form){return;}';

        echo 'const postTypeSelect=document.getElementById("sffc-html-importer-post-type");';
        echo 'const taxonomyGroups=document.querySelectorAll(".sffc-html-importer-tax-group");';
        echo 'const toggleTaxonomies=function(){if(!taxonomyGroups.length){return;}const chosen=postTypeSelect?postTypeSelect.value:"";taxonomyGroups.forEach(function(group){const matches=chosen&&group.dataset.postType===chosen;group.style.display=matches?"block":"none";group.querySelectorAll("select").forEach(function(select){select.disabled=!matches;});});};';
        echo 'if(postTypeSelect){postTypeSelect.addEventListener("change",toggleTaxonomies);toggleTaxonomies();}';

        echo 'if(!window.fetch){return;}';

        echo 'const progressWrap=document.getElementById("sffc-html-importer-progress");';
        echo 'const progressBar=progressWrap?progressWrap.querySelector("progress"):null;';
        echo 'const progressText=progressWrap?progressWrap.querySelector(".sffc-progress-text"):null;';
        echo 'const errorsWrap=progressWrap?progressWrap.querySelector(".sffc-progress-errors"):null;';
        echo 'const errorsList=errorsWrap?errorsWrap.querySelector("ul"):null;';
        echo 'const summaryWrap=progressWrap?progressWrap.querySelector(".sffc-progress-summary"):null;';
        echo 'const submitButton=form.querySelector("button[type=submit], input[type=submit]");';

        echo 'form.addEventListener("submit",function(event){if(!progressWrap||!progressBar||!progressText){return;}event.preventDefault();if(submitButton){submitButton.disabled=true;}';
        echo 'if(errorsWrap){errorsWrap.style.display="none";}if(errorsList){errorsList.innerHTML="";}if(summaryWrap){summaryWrap.style.display="none";}';
        echo 'progressWrap.style.display="block";progressBar.value=0;progressBar.max=100;progressText.textContent=' . wp_json_encode($strings['preparing']) . ';';

        echo 'const formData=new FormData(form);';
        echo 'formData.set("action","sffc_html_importer_init");';
        echo 'formData.set("sffc_html_importer_action","init");';

        echo 'window.fetch(window.ajaxurl,{method:"POST",credentials:"same-origin",body:formData}).then(function(response){return response.json();}).then(function(response){';
        echo 'if(!response||!response.success){throw new Error(response&&response.data&&response.data.message?response.data.message:' . wp_json_encode($strings['init_error']) . ');}';
        echo 'const data=response.data||{};const total=parseInt(data.total_rows,10)||0;const batchSize=parseInt(data.batch_size,10)||10;';
        echo 'if(progressBar){progressBar.max=total>0?total:1;progressBar.value=data.processed||0;}';
        echo 'if(progressText){progressText.textContent=data.status_text||"";}';
        echo 'if(data.done){if(summaryWrap){summaryWrap.style.display="block";summaryWrap.innerHTML=data.summary_html||"";}if(submitButton){submitButton.disabled=false;}return;}';
        echo 'runBatch(batchSize);}).catch(function(error){if(progressText){progressText.textContent=(error&&error.message)?error.message:' . wp_json_encode($strings['init_error']) . ';}if(submitButton){submitButton.disabled=false;}});';
        echo '});';

        echo 'function runBatch(batchSize){const nonceField=form.querySelector("input[name=sffc_html_importer_nonce]");const tokenField=form.querySelector("input[name=import_key]");if(!nonceField||!tokenField){return;}const batchForm=new FormData();batchForm.append("action","sffc_html_importer_run");batchForm.append("import_key",tokenField.value);batchForm.append("batch_size",batchSize);batchForm.append("sffc_html_importer_nonce",nonceField.value);window.fetch(window.ajaxurl,{method:"POST",credentials:"same-origin",body:batchForm}).then(function(response){return response.json();}).then(function(response){if(!response||!response.success){throw new Error(response&&response.data&&response.data.message?response.data.message:' . wp_json_encode($strings['batch_error']) . ');}const data=response.data||{};if(progressBar){progressBar.max=data.total||progressBar.max||1;progressBar.value=data.processed||0;}if(progressText){progressText.textContent=data.status_text||"";}if(data.errors&&data.errors.length&&errorsWrap&&errorsList){errorsWrap.style.display="block";errorsList.innerHTML="";data.errors.forEach(function(message){var item=document.createElement("li");item.textContent=message;errorsList.appendChild(item);});}if(data.done){if(summaryWrap){summaryWrap.style.display="block";summaryWrap.innerHTML=data.summary_html||"";}if(submitButton){submitButton.disabled=false;}return;}window.setTimeout(function(){runBatch(batchSize);},200);}).catch(function(error){if(progressText){progressText.textContent=(error&&error.message)?error.message:' . wp_json_encode($strings['batch_error']) . ';}if(submitButton){submitButton.disabled=false;}});}';

        echo '})(window,document);</script>';
    }

    /**
     * Handle clearing existing upload state.
     */
    private function handle_clear_request()
    {
        $token = isset($_GET['sffc_html_importer_clear']) ? sanitize_key(wp_unslash($_GET['sffc_html_importer_clear'])) : '';
        if (!$token) {
            return;
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'sffc_html_importer_clear')) {
            return;
        }

        $this->delete_upload_state($token);
        $this->add_notice('success', __('Upload cleared.', 'senna-finance'));
        wp_safe_redirect($this->get_page_url());
        exit;
    }

    /**
     * Process CSV upload and store temporary state.
     */
    private function handle_upload()
    {
        check_admin_referer('sffc_html_importer_upload', 'sffc_html_importer_nonce');

        if (empty($_FILES['import_file']['name'])) {
            $this->add_notice('error', __('Please select a CSV file to continue.', 'senna-finance'));
            wp_safe_redirect($this->get_page_url());
            exit;
        }

        $delimiter = isset($_POST['delimiter']) ? $this->sanitize_delimiter($_POST['delimiter']) : '';

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $uploaded = wp_handle_upload($_FILES['import_file'], array('test_form' => false));

        if (!empty($uploaded['error'])) {
            $this->add_notice('error', sprintf(__('Upload failed: %s', 'senna-finance'), $uploaded['error']));
            wp_safe_redirect($this->get_page_url());
            exit;
        }

        $file_path = $uploaded['file'];
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        if ('csv' !== $extension) {
            wp_delete_file($file_path);
            $this->add_notice('error', __('Only CSV files are supported.', 'senna-finance'));
            wp_safe_redirect($this->get_page_url());
            exit;
        }

        if (!$delimiter) {
            $delimiter = $this->detect_delimiter($file_path);
        }

        if (function_exists('ini_set')) {
            @ini_set('auto_detect_line_endings', '1');
        }

        $headers = $this->get_headers_from_file($file_path, $delimiter);

        if (empty($headers)) {
            wp_delete_file($file_path);
            $this->add_notice('error', __('Unable to read column headings from the CSV file.', 'senna-finance'));
            wp_safe_redirect($this->get_page_url());
            exit;
        }

        $token = $this->store_upload_state($file_path, $headers, $delimiter);

        $this->add_notice('success', __('File uploaded. Map the columns to finish the import.', 'senna-finance'));
        wp_safe_redirect(add_query_arg('import_key', rawurlencode($token), $this->get_page_url()));
        exit;
    }

    /**
     * Execute the import after mapping.
     */
    private function handle_import()
    {
        check_admin_referer('sffc_html_importer_import', 'sffc_html_importer_nonce');

        $token = isset($_POST['import_key']) ? sanitize_key(wp_unslash($_POST['import_key'])) : '';
        $state = $token ? $this->get_upload_state($token) : null;

        if (!$state) {
            $this->add_notice('error', __('Import session expired. Please upload the file again.', 'senna-finance'));
            wp_safe_redirect($this->get_page_url());
            exit;
        }

        $params = $this->extract_import_parameters($state, $_POST);

        if (is_wp_error($params)) {
            $this->add_notice('error', $params->get_error_message());
            wp_safe_redirect(add_query_arg('import_key', rawurlencode($token), $this->get_page_url()));
            exit;
        }

        $results = $this->import_rows(
            $state['file'],
            $state['delimiter'],
            $params['title_index'],
            $params['content_indices'],
            $params['separator'],
            $params['post_type'],
            $params['post_status'],
            $params['tax_input']
        );

        $this->delete_upload_state($token);

        if ($results['imported'] > 0) {
            $message = sprintf(
                /* translators: 1: number imported, 2: skipped */
                __('Imported %1$d posts. Skipped %2$d rows.', 'senna-finance'),
                $results['imported'],
                $results['skipped']
            );
            $this->add_notice('success', $message, array('errors' => $results['errors']));
        } else {
            $this->add_notice('error', __('No posts were imported. Review the CSV data and try again.', 'senna-finance'), array('errors' => $results['errors']));
        }

        wp_safe_redirect($this->get_page_url());
        exit;
    }

    public function ajax_init_import()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to run imports.', 'senna-finance')), 403);
        }

        check_ajax_referer('sffc_html_importer_import', 'sffc_html_importer_nonce');

        $token = isset($_POST['import_key']) ? sanitize_key(wp_unslash($_POST['import_key'])) : '';
        $state = $token ? $this->get_upload_state($token) : null;

        if (!$state) {
            wp_send_json_error(array('message' => __('Import session expired. Please upload the file again.', 'senna-finance')), 400);
        }

        $params = $this->extract_import_parameters($state, $_POST);

        if (is_wp_error($params)) {
            wp_send_json_error(array('message' => $params->get_error_message()), 400);
        }

        $job = $this->create_import_job(
            $state['file'],
            $state['delimiter'],
            $params['title_index'],
            $params['content_indices'],
            $params['separator'],
            $params['post_type'],
            $params['post_status'],
            $params['tax_input']
        );

        if (is_wp_error($job)) {
            wp_send_json_error(array('message' => $job->get_error_message()), 400);
        }

        $total_rows = (int) $job['total_rows'];
        $batch_size = (int) apply_filters('sffc_html_importer_batch_size', 15);
        $status_text = $total_rows > 0
            ? sprintf(__('Ready to import %d rows…', 'senna-finance'), $total_rows)
            : __('No rows detected in the CSV file.', 'senna-finance');

        $response = array(
            'total_rows'  => $total_rows,
            'batch_size'  => max(1, $batch_size),
            'processed'   => $job['processed'],
            'status_text' => $status_text,
            'done'        => $total_rows === 0,
        );

        if (0 === $total_rows) {
            $response['summary_html'] = sprintf(
                '<p><strong>%s</strong></p><p>%s</p>',
                esc_html__('Import finished', 'senna-finance'),
                esc_html__('There were no rows to import from this CSV file.', 'senna-finance')
            );
        } else {
            $this->store_import_job($token, $job);
        }

        wp_send_json_success($response);
    }

    public function ajax_run_import()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to run imports.', 'senna-finance')), 403);
        }

        check_ajax_referer('sffc_html_importer_import', 'sffc_html_importer_nonce');

        $token = isset($_POST['import_key']) ? sanitize_key(wp_unslash($_POST['import_key'])) : '';

        if (!$token) {
            wp_send_json_error(array('message' => __('Import session not found. Please upload the file again.', 'senna-finance')), 400);
        }

        $job = $this->get_import_job($token);

        if (!$job) {
            wp_send_json_error(array('message' => __('Import session not found. Please upload the file again.', 'senna-finance')), 400);
        }

        $batch_size = isset($_POST['batch_size']) ? (int) $_POST['batch_size'] : 15;
        $batch_size = max(1, $batch_size);

        $result = $this->process_job_batch($job, $batch_size);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 500);
        }

        if ($result['done']) {
            $this->delete_upload_state($token);
        } else {
            $this->store_import_job($token, $job);
        }

        $summary_html = '';

        if ($result['done']) {
            $summary_html = sprintf(
                '<p><strong>%s</strong></p><p>%s</p>%s',
                esc_html__('Import finished', 'senna-finance'),
                esc_html(sprintf(__('Imported %1$d posts and skipped %2$d rows.', 'senna-finance'), $job['imported'], $job['skipped'])),
                !empty($job['errors'])
                    ? '<p>' . esc_html__('Review the issues listed above for rows that were skipped.', 'senna-finance') . '</p>'
                    : ''
            );
        }

        $status_text = $job['total_rows'] > 0
            ? sprintf(__('Imported %1$d of %2$d rows (%3$d skipped).', 'senna-finance'), $job['imported'], $job['total_rows'], $job['skipped'])
            : __('Processing import…', 'senna-finance');

        wp_send_json_success(array(
            'processed'    => $job['processed'],
            'imported'     => $job['imported'],
            'skipped'      => $job['skipped'],
            'total'        => $job['total_rows'],
            'errors'       => $result['errors'],
            'done'         => (bool) $result['done'],
            'status_text'  => $status_text,
            'summary_html' => $summary_html,
        ));
    }

    /**
     * Run through CSV rows and create posts.
     */
    private function import_rows($file_path, $delimiter, $title_index, $content_indices, $separator, $post_type, $post_status, $tax_input)
    {
        $job = $this->create_import_job($file_path, $delimiter, $title_index, $content_indices, $separator, $post_type, $post_status, $tax_input);

        if (is_wp_error($job)) {
            return array(
                'imported' => 0,
                'skipped'  => 0,
                'errors'   => array($job->get_error_message()),
            );
        }

        $batch = $this->process_job_batch($job, -1);

        if (is_wp_error($batch)) {
            return array(
                'imported' => 0,
                'skipped'  => 0,
                'errors'   => array($batch->get_error_message()),
            );
        }

        return array(
            'imported' => $job['imported'],
            'skipped'  => $job['skipped'],
            'errors'   => $this->merge_errors($job['errors'], $batch['errors']),
        );
    }

    private function wrap_with_html_block($html)
    {
        return "<!-- wp:html -->\n" . $html . "\n<!-- /wp:html -->";
    }

    private function truncate_preview($value)
    {
        $value = (string) $value;
        if (mb_strlen($value) > 200) {
            return mb_substr($value, 0, 200) . '…';
        }
        return $value;
    }

    private function convert_tokens($value)
    {
        if ('' === $value) {
            return "\n\n";
        }

        $map = array(
            '\\n' => "\n",
            '\\r' => "\r",
            '\\t' => "\t",
        );

        return strtr($value, $map);
    }

    private function sanitize_delimiter($value)
    {
        $value = trim(wp_unslash($value));
        if ('' === $value) {
            return '';
        }

        return substr($value, 0, 1);
    }

    private function detect_delimiter($file_path)
    {
        $delimiters = array(',', ';', "\t", '|');
        $best_delimiter = ',';
        $best_count = 0;

        $handle = fopen($file_path, 'r');
        if (false === $handle) {
            return $best_delimiter;
        }

        $sample_line = fgets($handle);
        fclose($handle);

        if (false === $sample_line) {
            return $best_delimiter;
        }

        foreach ($delimiters as $delimiter) {
            $fields = str_getcsv($sample_line, $delimiter);
            if (count($fields) > $best_count) {
                $best_count = count($fields);
                $best_delimiter = $delimiter;
            }
        }

        return $best_delimiter;
    }

    private function get_headers_from_file($file_path, $delimiter)
    {
        $handle = fopen($file_path, 'r');

        if (false === $handle) {
            return array();
        }

        $headers = fgetcsv($handle, 0, $delimiter);
        fclose($handle);

        if (empty($headers)) {
            return array();
        }

        $headers = array_map(array($this, 'normalize_header'), $headers);

        return $headers;
    }

    private function normalize_header($header)
    {
        $header = (string) $header;
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        $header = trim($header);

        if ('' === $header) {
            static $counter = 1;
            $label = sprintf(__('Column %d', 'senna-finance'), $counter);
            $counter++;
            return $label;
        }

        return $header;
    }

    private function get_sample_rows($file_path, $delimiter)
    {
        $rows = array();
        $handle = fopen($file_path, 'r');

        if (false === $handle) {
            return $rows;
        }

        // Skip header.
        fgetcsv($handle, 0, $delimiter);

        $max = 3;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false && count($rows) < $max) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function get_supported_post_types()
    {
        $objects = get_post_types(array('show_ui' => true), 'objects');
        $post_types = array();

        foreach ($objects as $type => $object) {
            if ('attachment' === $type) {
                continue;
            }

            $post_types[$type] = $object->labels->singular_name;
        }

        asort($post_types);

        return $post_types;
    }

    private function get_taxonomy_map($post_type_keys)
    {
        $map = array();

        foreach ($post_type_keys as $post_type) {
            $taxonomies = get_object_taxonomies($post_type, 'objects');
            $map[$post_type] = array();

            foreach ($taxonomies as $taxonomy => $object) {
                if (!$object->show_ui) {
                    continue;
                }

                $terms = get_terms(array(
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => false,
                ));

                if (is_wp_error($terms)) {
                    $terms = array();
                }

                $map[$post_type][$taxonomy] = array(
                    'label' => $object->labels->name,
                    'terms' => $this->format_terms_for_select($terms, $object->hierarchical),
                );
            }
        }

        return $map;
    }

    private function format_terms_for_select($terms, $hierarchical)
    {
        if (empty($terms)) {
            return array();
        }

        if (!$hierarchical) {
            $terms = wp_list_sort($terms, 'name');
            $options = array();
            foreach ($terms as $term) {
                $options[] = array(
                    'id'    => $term->term_id,
                    'label' => $term->name,
                );
            }
            return $options;
        }

        $children = array();
        foreach ($terms as $term) {
            $children[$term->parent][] = $term;
        }

        foreach ($children as $parent => $items) {
            $children[$parent] = wp_list_sort($items, 'name');
        }

        $ordered = array();
        $this->flatten_terms($children, 0, 0, $ordered);

        return $ordered;
    }

    private function flatten_terms($children, $parent, $depth, &$ordered)
    {
        if (empty($children[$parent])) {
            return;
        }

        foreach ($children[$parent] as $term) {
            $ordered[] = array(
                'id'    => $term->term_id,
                'label' => str_repeat('— ', $depth) . $term->name,
            );

            $this->flatten_terms($children, $term->term_id, $depth + 1, $ordered);
        }
    }

    private function store_upload_state($file_path, $headers, $delimiter)
    {
        $token = strtolower(wp_generate_password(12, false, false));
        $transient_key = $this->get_transient_key($token);

        set_transient(
            $transient_key,
            array(
                'file'      => $file_path,
                'headers'   => $headers,
                'delimiter' => $delimiter,
                'user_id'   => get_current_user_id(),
                'time'      => time(),
            ),
            HOUR_IN_SECONDS
        );

        return $token;
    }

    private function create_import_job($file_path, $delimiter, $title_index, $content_indices, $separator, $post_type, $post_status, $tax_input)
    {
        if (!file_exists($file_path)) {
            return new WP_Error('sffc_html_importer_missing_file', __('Import file could not be found. Please upload it again.', 'senna-finance'));
        }

        return array(
            'file'            => $file_path,
            'delimiter'       => $delimiter,
            'title_index'     => $title_index,
            'content_indices' => $content_indices,
            'separator'       => $separator,
            'post_type'       => $post_type,
            'post_status'     => $post_status,
            'tax_input'       => $tax_input,
            'total_rows'      => $this->count_total_rows($file_path, $delimiter),
            'processed'       => 0,
            'imported'        => 0,
            'skipped'         => 0,
            'errors'          => array(),
            'position'        => 0,
            'completed'       => false,
        );
    }

    private function process_job_batch(array &$job, $batch_size = -1)
    {
        if (!file_exists($job['file'])) {
            return new WP_Error('sffc_html_importer_missing_file', __('Import file could not be reopened. Please upload it again.', 'senna-finance'));
        }

        if (function_exists('ini_set')) {
            @ini_set('auto_detect_line_endings', '1');
        }

        $handle = fopen($job['file'], 'r');

        if (false === $handle) {
            return new WP_Error('sffc_html_importer_file_unreadable', __('Unable to open the CSV file for reading.', 'senna-finance'));
        }

        $delimiter = $job['delimiter'];

        if (empty($job['position'])) {
            fgetcsv($handle, 0, $delimiter);
            $job['position'] = ftell($handle);
        } else {
            fseek($handle, (int) $job['position']);
        }

        $batch_imported = 0;
        $batch_skipped = 0;
        $batch_errors = array();
        $processed_in_batch = 0;

        while ($batch_size < 0 || $processed_in_batch < $batch_size) {
            $row = fgetcsv($handle, 0, $delimiter);

            if (false === $row) {
                $job['completed'] = true;
                break;
            }

            $job['position'] = ftell($handle);

            if (empty(array_filter($row, 'strlen'))) {
                continue;
            }

            $processed_in_batch++;
            $job['processed']++;

            $row_result = $this->process_import_row($row, $job);

            if ('imported' === $row_result['status']) {
                $job['imported']++;
                $batch_imported++;
            } elseif ('skipped' === $row_result['status']) {
                $job['skipped']++;
                $batch_skipped++;
                if (!empty($row_result['message'])) {
                    $batch_errors[] = $row_result['message'];
                }
            }
        }

        fclose($handle);

        $job['errors'] = $this->merge_errors(isset($job['errors']) ? $job['errors'] : array(), $batch_errors);

        return array(
            'imported' => $batch_imported,
            'skipped'  => $batch_skipped,
            'errors'   => $this->limit_errors($batch_errors),
            'done'     => !empty($job['completed']) || $job['processed'] >= $job['total_rows'],
            'processed' => $job['processed'],
        );
    }

    private function process_import_row($row, $job)
    {
        $title = isset($row[$job['title_index']]) ? trim((string) $row[$job['title_index']]) : '';

        if ('' === $title) {
            return array(
                'status'  => 'skipped',
                'message' => __('Skipped a row with an empty title.', 'senna-finance'),
            );
        }

        $content_segments = array();

        foreach ($job['content_indices'] as $index) {
            if (!array_key_exists($index, $row)) {
                continue;
            }

            $value = $row[$index];

            if ('' === trim((string) $value)) {
                continue;
            }

            $content_segments[] = $value;
        }

        if (empty($content_segments)) {
            return array(
                'status'  => 'skipped',
                'message' => sprintf(__('Skipped "%s" because no HTML columns contained content.', 'senna-finance'), $title),
            );
        }

        $combined_html = implode($job['separator'], $content_segments);
        $block_content = $this->wrap_with_html_block($combined_html);

        $post_data = array(
            'post_title'   => wp_strip_all_tags($title),
            'post_content' => $block_content,
            'post_type'    => $job['post_type'],
            'post_status'  => $job['post_status'],
        );

        if (!empty($job['tax_input'])) {
            $post_data['tax_input'] = $job['tax_input'];
        }

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return array(
                'status'  => 'skipped',
                'message' => sprintf(__('Failed to import "%1$s": %2$s', 'senna-finance'), $title, $post_id->get_error_message()),
            );
        }

        update_post_meta($post_id, '_wp_page_template', 'elementor_full_width');

        return array('status' => 'imported');
    }

    private function merge_errors($existing, $new)
    {
        $existing = is_array($existing) ? $existing : array();
        $new = is_array($new) ? $new : array();

        return $this->limit_errors(array_merge($existing, $new));
    }

    private function get_upload_state($token)
    {
        $transient_key = $this->get_transient_key($token);
        $state = get_transient($transient_key);

        if (empty($state)) {
            return null;
        }

        if ((int) $state['user_id'] !== get_current_user_id()) {
            return null;
        }

        if (empty($state['file']) || !file_exists($state['file'])) {
            delete_transient($transient_key);
            return null;
        }

        return $state;
    }

    private function delete_upload_state($token)
    {
        $transient_key = $this->get_transient_key($token);
        $state = get_transient($transient_key);

        if (!empty($state['file']) && file_exists($state['file'])) {
            wp_delete_file($state['file']);
        }

        delete_transient($transient_key);
        $this->delete_import_job($token);
    }

    private function sanitize_tax_input($post_type, $tax_input)
    {
        $sanitized = array();

        if (empty($tax_input) || !is_array($tax_input)) {
            return $sanitized;
        }

        foreach ($tax_input as $taxonomy => $terms) {
            $taxonomy = sanitize_key($taxonomy);

            if (!taxonomy_exists($taxonomy)) {
                continue;
            }

            if (!is_object_in_taxonomy($post_type, $taxonomy)) {
                continue;
            }

            $term_ids = array();

            foreach ((array) $terms as $term_id) {
                $term_id = (int) $term_id;

                if ($term_id <= 0) {
                    continue;
                }

                $term = get_term($term_id, $taxonomy);

                if (is_wp_error($term) || !$term) {
                    continue;
                }

                $term_ids[] = $term_id;
            }

            if (!empty($term_ids)) {
                $sanitized[$taxonomy] = array_values(array_unique($term_ids));
            }
        }

        return $sanitized;
    }

    private function extract_import_parameters($state, $posted)
    {
        $post_type = isset($posted['post_type']) ? sanitize_key(wp_unslash($posted['post_type'])) : '';

        if (!$post_type || !post_type_exists($post_type)) {
            return new WP_Error('sffc_html_importer_invalid_post_type', __('Select a valid post type.', 'senna-finance'));
        }

        $title_index = isset($posted['title_column']) ? (int) $posted['title_column'] : null;

        if (null === $title_index || !array_key_exists($title_index, $state['headers'])) {
            return new WP_Error('sffc_html_importer_invalid_title', __('Select a valid title column.', 'senna-finance'));
        }

        $content_indices = isset($posted['content_columns']) ? (array) $posted['content_columns'] : array();
        $content_indices = array_map('intval', $content_indices);

        if (empty($content_indices)) {
            return new WP_Error('sffc_html_importer_missing_content', __('Choose at least one HTML column.', 'senna-finance'));
        }

        $separator = isset($posted['content_separator']) ? $this->convert_tokens(wp_unslash($posted['content_separator'])) : "\n\n";

        $post_status = isset($posted['post_status']) ? sanitize_key(wp_unslash($posted['post_status'])) : 'draft';
        $allowed_statuses = array('draft', 'publish', 'pending', 'private');
        if (!in_array($post_status, $allowed_statuses, true)) {
            $post_status = 'draft';
        }

        $tax_input = $this->sanitize_tax_input($post_type, isset($posted['tax_input']) ? (array) $posted['tax_input'] : array());

        return array(
            'post_type'       => $post_type,
            'title_index'     => $title_index,
            'content_indices' => $content_indices,
            'separator'       => $separator,
            'post_status'     => $post_status,
            'tax_input'       => $tax_input,
        );
    }

    private function count_total_rows($file_path, $delimiter)
    {
        if (!file_exists($file_path)) {
            return 0;
        }

        if (function_exists('ini_set')) {
            @ini_set('auto_detect_line_endings', '1');
        }

        $handle = fopen($file_path, 'r');

        if (false === $handle) {
            return 0;
        }

        fgetcsv($handle, 0, $delimiter);

        $count = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (!empty(array_filter($row, 'strlen'))) {
                $count++;
            }
        }

        fclose($handle);

        return $count;
    }

    private function store_import_job($token, array $job)
    {
        set_transient($this->get_job_key($token), $job, MINUTE_IN_SECONDS * 30);
    }

    private function get_import_job($token)
    {
        $job = get_transient($this->get_job_key($token));

        if (empty($job)) {
            return null;
        }

        if (!file_exists($job['file'])) {
            $this->delete_import_job($token);
            return null;
        }

        return $job;
    }

    private function delete_import_job($token)
    {
        delete_transient($this->get_job_key($token));
    }

    private function get_job_key($token)
    {
        return $this->job_prefix . $token;
    }

    private function get_transient_key($token)
    {
        return $this->transient_prefix . $token;
    }

    private function add_notice($type, $message, $details = array())
    {
        set_transient($this->get_notice_key(), array(
            'type'    => $type,
            'message' => $message,
            'details' => $details,
        ), MINUTE_IN_SECONDS * 5);
    }

    private function get_notice()
    {
        return get_transient($this->get_notice_key());
    }

    private function clear_notice()
    {
        delete_transient($this->get_notice_key());
    }

    private function get_notice_key()
    {
        return $this->notices_key . get_current_user_id();
    }

    private function get_page_url()
    {
        return admin_url('admin.php?page=' . $this->page_slug);
    }

    private function limit_errors($errors)
    {
        $errors = array_values(array_filter(array_unique($errors)));
        if (count($errors) > 5) {
            $display = array_slice($errors, 0, 5);
            $display[] = sprintf(__('…and %d more.', 'senna-finance'), count($errors) - 5);
            return $display;
        }

        return $errors;
    }
}
