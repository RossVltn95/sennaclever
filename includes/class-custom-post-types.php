<?php

/**
 * Custom Post Types and Taxonomies for Programmatic SEO
 * 
 * Creates job posts, company profiles, and related content types
 * Based on Workday and XML feed data structures
 * 
 * @package MENA Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Custom_Post_Types
{

    /**
     * Instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        add_action('init', [$this, 'register_post_types']);
        add_action('init', [$this, 'register_meta_fields']);
    }

    /**
     * Register Custom Post Types
     */
    public function register_post_types()
    {
        // SALARY GUIDES CPT - Dynamic salary pages (kept)
        register_post_type('sffc_salary_guide', [
            'labels' => [
                'name' => 'Salary Guides',
                'singular_name' => 'Salary Guide',
                'menu_name' => 'Salary Guides'
            ],
            'public' => true,
            'rewrite' => ['slug' => 'salary-guide', 'with_front' => false],
            'supports' => ['title', 'editor', 'custom-fields'],
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-chart-line'
        ]);

        if (apply_filters('sffc_pe_markets_enabled', false)) {
            register_post_type('sffc_pe_markets', [
                'labels' => [
                    'name' => 'Markets',
                    'singular_name' => 'Market Article',
                    'menu_name' => 'Markets',
                    'add_new' => 'Add Market Article',
                    'add_new_item' => 'Add New Market Article',
                    'edit_item' => 'Edit Market Article',
                    'new_item' => 'New Market Article',
                    'view_item' => 'View Market Article',
                    'search_items' => 'Search Market Articles',
                    'not_found' => 'No market articles found',
                    'not_found_in_trash' => 'No market articles found in Trash',
                ],
                'public' => true,
                'publicly_queryable' => true,
                'show_ui' => true,
                'show_in_menu' => 'sffc-dashboard',
                'show_in_rest' => true,
                'rewrite' => ['slug' => 'markets', 'with_front' => false],
                'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields'],
                'menu_icon' => 'dashicons-chart-area',
                'has_archive' => false,
            ]);
        }
    }

    /**
     * Register Meta Fields for Jobs
     * These align with Workday and XML feed data
     */
    public function register_meta_fields()
    {
        // Job meta fields are registered elsewhere
    }


    /**
     * Normalize term names for consistency
     */
    private function normalize_term_name($name, $taxonomy)
    {
        // Trim whitespace
        $name = trim($name);

        if ($taxonomy === 'sffc_company_tag') {
            // Company-specific normalization
            $company_mappings = [
                // Standardize common variations
                'j.p. morgan' => 'J.P. Morgan',
                'jp morgan' => 'J.P. Morgan',
                'jpmorgan' => 'J.P. Morgan',
                'goldman sachs' => 'Goldman Sachs',
                'goldman' => 'Goldman Sachs',
                'morgan stanley' => 'Morgan Stanley',
                'morganstanley' => 'Morgan Stanley',
                'bank of america' => 'Bank of America',
                'bofa' => 'Bank of America',
                'bankofamerica' => 'Bank of America',
                'citi' => 'Citi',
                'citigroup' => 'Citi',
                'citibank' => 'Citi',
                'deutsche bank' => 'Deutsche Bank',
                'deutschebank' => 'Deutsche Bank',
                'db' => 'Deutsche Bank',
                'ubs' => 'UBS',
                'credit suisse' => 'Credit Suisse',
                'cs' => 'Credit Suisse',
                'barclays' => 'Barclays',
                'hsbc' => 'HSBC',
                'bnp paribas' => 'BNP Paribas',
                'bnpparibas' => 'BNP Paribas',
                'societe generale' => 'Societe Generale',
                'socgen' => 'Societe Generale',
                'rothschild & co' => 'Rothschild & Co',
                'rothschild' => 'Rothschild & Co',
                'blackrock' => 'BlackRock',
                'black rock' => 'BlackRock',
                'blackstone' => 'Blackstone',
                'black stone' => 'Blackstone',
                'kkr' => 'KKR',
                'apollo' => 'Apollo Global Management',
                'apollo global' => 'Apollo Global Management',
                'carlyle' => 'The Carlyle Group',
                'carlyle group' => 'The Carlyle Group',
                'tpg' => 'TPG',
                'warburg pincus' => 'Warburg Pincus',
                'warburg' => 'Warburg Pincus',
                'bain capital' => 'Bain Capital',
                'bain' => 'Bain Capital',
                'advent' => 'Advent International',
                'advent international' => 'Advent International',
                'cvc' => 'CVC Capital Partners',
                'cvc capital' => 'CVC Capital Partners',
                'permira' => 'Permira',
                'bridgewater' => 'Bridgewater Associates',
                'bridgewater associates' => 'Bridgewater Associates',
                'citadel' => 'Citadel',
                'millennium' => 'Millennium Management',
                'millennium management' => 'Millennium Management',
                'two sigma' => 'Two Sigma',
                'twosigma' => 'Two Sigma',
                'de shaw' => 'D. E. Shaw',
                'd.e. shaw' => 'D. E. Shaw',
                'deshaw' => 'D. E. Shaw',
                'jane street' => 'Jane Street',
                'janestreet' => 'Jane Street',
                'point72' => 'Point72',
                'point 72' => 'Point72',
                'lloyds' => 'Lloyds Banking Group',
                'lloyds banking' => 'Lloyds Banking Group',
                'santander' => 'Santander',
                'banco santander' => 'Santander',
                'state street' => 'State Street',
                'statestreet' => 'State Street',
                'northern trust' => 'Northern Trust',
                'northerntrust' => 'Northern Trust',
                'bny mellon' => 'BNY Mellon',
                'bnymellon' => 'BNY Mellon',
                'bank of new york mellon' => 'BNY Mellon',
                'wells fargo' => 'Wells Fargo',
                'wellsfargo' => 'Wells Fargo',
                'rbc' => 'RBC',
                'royal bank of canada' => 'RBC',
                'td' => 'TD Bank',
                'td bank' => 'TD Bank',
                'toronto dominion' => 'TD Bank',
                'scotiabank' => 'Scotiabank',
                'bank of nova scotia' => 'Scotiabank',
                'bmo' => 'BMO',
                'bank of montreal' => 'BMO',
                'cibc' => 'CIBC',
                'aviva' => 'Aviva',
                'axa' => 'AXA',
                'allianz' => 'Allianz',
                'prudential' => 'Prudential',
                'metlife' => 'MetLife',
                'met life' => 'MetLife',
                'zurich' => 'Zurich Insurance',
                'zurich insurance' => 'Zurich Insurance',
                'swiss re' => 'Swiss Re',
                'swissre' => 'Swiss Re',
                'munich re' => 'Munich Re',
                'munichre' => 'Munich Re',
                'aon' => 'Aon',
                'willis towers watson' => 'Willis Towers Watson',
                'wtw' => 'Willis Towers Watson',
                'marsh' => 'Marsh & McLennan',
                'marsh mclennan' => 'Marsh & McLennan',
                'mmc' => 'Marsh & McLennan',
                'jefferies' => 'Jefferies',
                'evercore' => 'Evercore',
                'lazard' => 'Lazard',
                'moelis' => 'Moelis & Company',
                'moelis & company' => 'Moelis & Company',
                'houlihan lokey' => 'Houlihan Lokey',
                'houlihanlokey' => 'Houlihan Lokey',
                'perella weinberg' => 'Perella Weinberg Partners',
                'perella' => 'Perella Weinberg Partners',
                'pwp' => 'Perella Weinberg Partners',
                'centerview' => 'Centerview Partners',
                'centerview partners' => 'Centerview Partners',
                'qatalyst' => 'Qatalyst Partners',
                'qatalyst partners' => 'Qatalyst Partners',
                'greenhill' => 'Greenhill & Co',
                'greenhill & co' => 'Greenhill & Co',
                'piper sandler' => 'Piper Sandler',
                'pipersandler' => 'Piper Sandler',
                'piper jaffray' => 'Piper Sandler',
                'raymond james' => 'Raymond James',
                'raymondjames' => 'Raymond James',
                'stifel' => 'Stifel',
                'robert w baird' => 'Robert W. Baird',
                'baird' => 'Robert W. Baird',
                'william blair' => 'William Blair',
                'williamblair' => 'William Blair',
                'cowen' => 'Cowen',
                'td cowen' => 'TD Cowen',
                'oppenheimer' => 'Oppenheimer',
                'cantor fitzgerald' => 'Cantor Fitzgerald',
                'cantor' => 'Cantor Fitzgerald',
                'bgc' => 'BGC Partners',
                'bgc partners' => 'BGC Partners',
                'numis' => 'Numis',
                'liberum' => 'Liberum',
                'panmure gordon' => 'Panmure Gordon',
                'panmure' => 'Panmure Gordon',
                'shore capital' => 'Shore Capital',
                'shorecapital' => 'Shore Capital',
                'finncap' => 'finnCap',
                'finn cap' => 'finnCap',
                'cenkos' => 'Cenkos',
                'zeus' => 'Zeus Capital',
                'zeus capital' => 'Zeus Capital',
                'sp angel' => 'SP Angel',
                'spangel' => 'SP Angel',
                'fca' => 'FCA',
                'financial conduct authority' => 'FCA',
                'pra' => 'PRA',
                'prudential regulation authority' => 'PRA',
                'boe' => 'Bank of England',
                'bank of england' => 'Bank of England',
                'ecb' => 'European Central Bank',
                'european central bank' => 'European Central Bank',
                'fed' => 'Federal Reserve',
                'federal reserve' => 'Federal Reserve',
                'imf' => 'IMF',
                'international monetary fund' => 'IMF',
                'world bank' => 'World Bank',
                'worldbank' => 'World Bank',
                'bis' => 'BIS',
                'bank for international settlements' => 'BIS',
                'mfs' => 'MFS Investment Management',
                'mfs investment' => 'MFS Investment Management',
                'fidelity' => 'Fidelity Investments',
                'fidelity investments' => 'Fidelity Investments',
                'vanguard' => 'Vanguard',
                'invesco' => 'Invesco',
                'schroders' => 'Schroders',
                'aberdeen' => 'abrdn',
                'abrdn' => 'abrdn',
                'standard life aberdeen' => 'abrdn',
                'm&g' => 'M&G',
                'm & g' => 'M&G',
                'legal & general' => 'Legal & General',
                'legal and general' => 'Legal & General',
                'l&g' => 'Legal & General',
                'standard chartered' => 'Standard Chartered',
                'standardchartered' => 'Standard Chartered',
                'stanchart' => 'Standard Chartered',
                'nomura' => 'Nomura',
                'mizuho' => 'Mizuho',
                'mufg' => 'MUFG',
                'mitsubishi ufj' => 'MUFG',
                'sumitomo mitsui' => 'SMBC',
                'smbc' => 'SMBC',
                'daiwa' => 'Daiwa',
                'icbc' => 'ICBC',
                'china construction bank' => 'China Construction Bank',
                'ccb' => 'China Construction Bank',
                'agricultural bank of china' => 'Agricultural Bank of China',
                'abc' => 'Agricultural Bank of China',
                'bank of china' => 'Bank of China',
                'boc' => 'Bank of China',

                // Recruiters and other companies
                'finatal' => 'Finatal',
                'marks sattin' => 'Marks Sattin',
                'marks_sattin' => 'Marks Sattin',
                'markssattin' => 'Marks Sattin',
                'pearse partners' => 'Pearse Partners',
                'pearse_partners' => 'Pearse Partners',
                'pearsepartners' => 'Pearse Partners',
                'dartmouth partners' => 'Dartmouth Partners',
                'dartmouth_partners' => 'Dartmouth Partners',
                'dartmouthpartners' => 'Dartmouth Partners',
                'barton partnership' => 'The Barton Partnership',
                'barton_partnership' => 'The Barton Partnership',
                'thebartonpartnership' => 'The Barton Partnership',
                'via recruiter' => 'Various',
                'recruiter' => 'Various',
                'confidential' => 'Confidential'
            ];

            $lower_name = strtolower($name);
            if (isset($company_mappings[$lower_name])) {
                return $company_mappings[$lower_name];
            }

            // Clean up common suffixes/prefixes
            $name = preg_replace('/\s+(inc|incorporated|corp|corporation|ltd|limited|llc|llp|plc|ag|sa|gmbh|co\.?|company|group|holdings|partners|capital|management|investments?|advisors?|securities|services|solutions|consulting|associates|international|global)\.?$/i', '', $name);
            $name = preg_replace('/^(the|a|an)\s+/i', '', $name);

            // Ensure proper capitalization for known patterns
            $name = preg_replace_callback('/\b([a-z])/i', function ($matches) {
                return strtoupper($matches[1]);
            }, $name);
        }

        return trim($name);
    }

    /**
     * Extract city from location string
     */
    private function extract_city($location)
    {
        // Extract first part before comma
        $parts = explode(',', $location);
        return trim($parts[0]);
    }

    /**
     * Extract country from location string
     */
    private function extract_country($location)
    {
        // Extract last part after comma
        $parts = explode(',', $location);
        return trim(end($parts));
    }

    /**
     * Add meta boxes for job posts
     */
    public function add_company_meta_boxes()
    {
        add_meta_box(
            'sffc_company_portfolio',
            __('Portfolio Companies', 'senna-finance'),
            [$this, 'render_company_portfolio_meta_box'],
            'sffc_company',
            'normal',
            'high'
        );

        add_meta_box(
            'sffc_company_active_funds',
            __('Active Funds', 'senna-finance'),
            [$this, 'render_company_active_funds_meta_box'],
            'sffc_company',
            'normal',
            'default'
        );
    }

    public function render_company_portfolio_meta_box($post)
    {
        $this->print_company_meta_nonce_field();
        $this->print_company_meta_assets();

        $defaults = [
            'name' => '',
            'sector' => '',
            'region' => '',
            'status' => '',
            'url' => '',
            'notes' => '',
        ];

        $entries = $this->get_repeatable_meta_entries($post->ID, '_sffc_portfolio_list', $defaults);

        if (empty($entries)) {
            $entries[] = $defaults;
        }

?>
        <p class="description"><?php esc_html_e('Entries appear in the portfolio grid and the editor-facing submission form.', 'senna-finance'); ?></p>
        <div id="sffc-portfolio-repeatable" class="sffc-admin-repeatable">
            <?php foreach ($entries as $entry) : ?>
                <div class="sffc-admin-repeatable__row">
                    <div class="sffc-admin-repeatable__grid">
                        <p>
                            <label><?php esc_html_e('Company Name', 'senna-finance'); ?></label>
                            <input type="text" name="sffc_portfolio[name][]" value="<?php echo esc_attr($entry['name']); ?>" />
                        </p>
                        <p>
                            <label><?php esc_html_e('Sector', 'senna-finance'); ?></label>
                            <input type="text" name="sffc_portfolio[sector][]" value="<?php echo esc_attr($entry['sector']); ?>" />
                        </p>
                        <p>
                            <label><?php esc_html_e('Region', 'senna-finance'); ?></label>
                            <input type="text" name="sffc_portfolio[region][]" value="<?php echo esc_attr($entry['region']); ?>" />
                        </p>
                        <p>
                            <label><?php esc_html_e('Status', 'senna-finance'); ?></label>
                            <input type="text" name="sffc_portfolio[status][]" value="<?php echo esc_attr($entry['status']); ?>" />
                        </p>
                        <p>
                            <label><?php esc_html_e('Website', 'senna-finance'); ?></label>
                            <input type="url" name="sffc_portfolio[url][]" value="<?php echo esc_attr($entry['url']); ?>" placeholder="https://" />
                        </p>
                    </div>
                    <p>
                        <label><?php esc_html_e('Notes', 'senna-finance'); ?></label>
                        <textarea name="sffc_portfolio[notes][]" rows="2"><?php echo esc_textarea($entry['notes']); ?></textarea>
                    </p>
                    <p class="sffc-admin-repeatable__actions">
                        <button type="button" class="button-link-delete sffc-admin-row-remove"><?php esc_html_e('Remove entry', 'senna-finance'); ?></button>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
        <p>
            <button type="button" class="button button-secondary sffc-admin-row-add" data-target="#sffc-portfolio-repeatable"><?php esc_html_e('Add portfolio company', 'senna-finance'); ?></button>
        </p>
    <?php
    }

    public function render_company_active_funds_meta_box($post)
    {
        $this->print_company_meta_nonce_field();
        $this->print_company_meta_assets();

        $defaults = [
            'name' => '',
            'vintage' => '',
            'size' => '',
            'focus' => '',
            'notes' => '',
        ];

        $entries = $this->get_repeatable_meta_entries($post->ID, '_sffc_active_funds', $defaults);

        if (empty($entries)) {
            $entries[] = $defaults;
        }

    ?>
        <p class="description"><?php esc_html_e('Populate the “Currently deploying capital” grid with concise fund details.', 'senna-finance'); ?></p>
        <div id="sffc-active-funds-repeatable" class="sffc-admin-repeatable">
            <?php foreach ($entries as $entry) : ?>
                <div class="sffc-admin-repeatable__row">
                    <div class="sffc-admin-repeatable__grid">
                        <p>
                            <label><?php esc_html_e('Fund Name', 'senna-finance'); ?></label>
                            <input type="text" name="sffc_active_funds[name][]" value="<?php echo esc_attr($entry['name']); ?>" />
                        </p>
                        <p>
                            <label><?php esc_html_e('Vintage Year', 'senna-finance'); ?></label>
                            <input type="text" name="sffc_active_funds[vintage][]" value="<?php echo esc_attr($entry['vintage']); ?>" />
                        </p>
                        <p>
                            <label><?php esc_html_e('Fund Size', 'senna-finance'); ?></label>
                            <input type="text" name="sffc_active_funds[size][]" value="<?php echo esc_attr($entry['size']); ?>" />
                        </p>
                        <p>
                            <label><?php esc_html_e('Focus', 'senna-finance'); ?></label>
                            <input type="text" name="sffc_active_funds[focus][]" value="<?php echo esc_attr($entry['focus']); ?>" />
                        </p>
                    </div>
                    <p>
                        <label><?php esc_html_e('Notes', 'senna-finance'); ?></label>
                        <textarea name="sffc_active_funds[notes][]" rows="2"><?php echo esc_textarea($entry['notes']); ?></textarea>
                    </p>
                    <p class="sffc-admin-repeatable__actions">
                        <button type="button" class="button-link-delete sffc-admin-row-remove"><?php esc_html_e('Remove entry', 'senna-finance'); ?></button>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
        <p>
            <button type="button" class="button button-secondary sffc-admin-row-add" data-target="#sffc-active-funds-repeatable"><?php esc_html_e('Add active fund', 'senna-finance'); ?></button>
        </p>
    <?php
    }

    private function print_company_meta_nonce_field()
    {
        static $printed = false;

        if ($printed) {
            return;
        }

        $printed = true;
        wp_nonce_field('sffc_save_company_meta', 'sffc_company_meta_nonce');
    }

    private function print_company_meta_assets()
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

            .sffc-admin-repeatable__grid label,
            .sffc-admin-repeatable__actions {
                font-weight: 600;
            }

            .sffc-admin-repeatable__row textarea,
            .sffc-admin-repeatable__row input[type="text"],
            .sffc-admin-repeatable__row input[type="url"] {
                width: 100%;
            }

            .sffc-admin-repeatable__actions {
                margin-top: 12px;
            }

            .sffc-admin-repeatable__actions .sffc-admin-row-remove {
                color: #b32d2e;
            }
        </style>
        <script type="text/javascript">
            (function($) {
                $(document).on('click', '.sffc-admin-row-add', function(event) {
                    event.preventDefault();
                    var target = $($(this).data('target'));

                    if (!target.length) {
                        return;
                    }

                    var clone = target.children('.sffc-admin-repeatable__row').last().clone(true, true);
                    clone.find('input[type="text"], input[type="url"], textarea').val('');
                    target.append(clone);
                });

                $(document).on('click', '.sffc-admin-row-remove', function(event) {
                    event.preventDefault();
                    var row = $(this).closest('.sffc-admin-repeatable__row');
                    var container = row.parent();

                    if (container.children('.sffc-admin-repeatable__row').length > 1) {
                        row.remove();
                    } else {
                        row.find('input[type="text"], input[type="url"], textarea').val('');
                    }
                });
            })(jQuery);
        </script>
    <?php
    }

    private function get_repeatable_meta_entries($post_id, $meta_key, array $defaults)
    {
        $raw = get_post_meta($post_id, $meta_key, true);

        if (empty($raw)) {
            return [];
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $raw = $decoded;
            }
        }

        if (!is_array($raw)) {
            return [];
        }

        $entries = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $entries[] = array_merge($defaults, array_intersect_key($item, $defaults));
        }

        return $entries;
    }

    private function sanitize_repeatable_input($input, array $sanitizers, $required_field)
    {
        $sanitized = [];

        if (empty($input) || !is_array($input)) {
            return $sanitized;
        }

        $input = wp_unslash($input);

        $row_count = 0;
        foreach ($sanitizers as $field => $callback) {
            if (isset($input[$field]) && is_array($input[$field])) {
                $row_count = max($row_count, count($input[$field]));
            }
        }

        for ($index = 0; $index < $row_count; $index++) {
            $row = [];

            foreach ($sanitizers as $field => $callback) {
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
                    default:
                        $row[$field] = is_callable($callback) ? call_user_func($callback, $value) : sanitize_text_field($value);
                        break;
                }
            }

            $required_value = isset($row[$required_field]) ? $row[$required_field] : '';

            if ($required_value === '') {
                continue;
            }

            $sanitized[] = $row;
        }

        return $sanitized;
    }

    private function persist_repeatable_meta($post_id, $meta_key, array $entries)
    {
        if (!empty($entries)) {
            update_post_meta($post_id, $meta_key, wp_json_encode($entries));
            return true;
        }

        if (metadata_exists('post', $post_id, $meta_key)) {
            delete_post_meta($post_id, $meta_key);
            return true;
        }

        return false;
    }

    public function filter_company_insert_data($data, $postarr)
    {
        if (!class_exists('SFFC_Company_Title_Helper')) {
            return $data;
        }

        if (!isset($data['post_type']) || $data['post_type'] !== 'sffc_company') {
            return $data;
        }

        if (!empty($data['post_status']) && $data['post_status'] === 'auto-draft') {
            return $data;
        }

        $raw_input = $postarr['post_title'] ?? ($data['post_title'] ?? '');
        $canonical = SFFC_Company_Title_Helper::strip_seo_suffix($raw_input);

        if ($canonical === '' && !empty($postarr['ID'])) {
            $canonical = SFFC_Company_Title_Helper::get_canonical_name((int) $postarr['ID']);
        }

        if ($canonical === '') {
            return $data;
        }

        $data['post_title'] = SFFC_Company_Title_Helper::build_seo_title($canonical);

        $post_id = !empty($postarr['ID']) ? (int) $postarr['ID'] : 0;
        if ($post_id > 0) {
            $existing_slug = get_post_field('post_name', $post_id);
            if (!empty($existing_slug)) {
                $data['post_name'] = $existing_slug;
            }
        } elseif (empty($postarr['post_name'])) {
            $data['post_name'] = sanitize_title($canonical);
        }

        return $data;
    }

    public function ensure_company_canonical_meta($post_id, $post, $update)
    {
        if (!class_exists('SFFC_Company_Title_Helper') || !($post instanceof WP_Post)) {
            return;
        }

        if ($post->post_type !== 'sffc_company' || wp_is_post_revision($post_id)) {
            return;
        }

        $canonical = SFFC_Company_Title_Helper::strip_seo_suffix($post->post_title);

        if ($canonical === '') {
            $canonical = SFFC_Company_Title_Helper::get_canonical_name($post);
        }

        if ($canonical === '') {
            return;
        }

        update_post_meta($post_id, SFFC_Company_Title_Helper::META_CANONICAL_NAME, $canonical);
    }

    public function save_company_meta($post_id)
    {
        if (!isset($_POST['sffc_company_meta_nonce']) || !wp_verify_nonce($_POST['sffc_company_meta_nonce'], 'sffc_save_company_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $portfolio_entries = $this->sanitize_repeatable_input(
            $_POST['sffc_portfolio'] ?? [],
            [
                'name' => 'text',
                'sector' => 'text',
                'region' => 'text',
                'status' => 'text',
                'url' => 'url',
                'notes' => 'textarea',
            ],
            'name'
        );

        $fund_entries = $this->sanitize_repeatable_input(
            $_POST['sffc_active_funds'] ?? [],
            [
                'name' => 'text',
                'vintage' => 'text',
                'size' => 'text',
                'focus' => 'text',
                'notes' => 'textarea',
            ],
            'name'
        );

        $portfolio_changed = $this->persist_repeatable_meta($post_id, '_sffc_portfolio_list', $portfolio_entries);
        $funds_changed = $this->persist_repeatable_meta($post_id, '_sffc_active_funds', $fund_entries);

        if (($portfolio_changed || $funds_changed) && class_exists('SFFC_Company_Profile_Aggregator')) {
            SFFC_Company_Profile_Aggregator::clear_profile_cache($post_id);
        }
    }

    public function add_job_meta_boxes()
    {
        // Job Details Meta Box
        add_meta_box(
            'sffc_job_details',
            'Job Details',
            [$this, 'render_job_details_meta_box'],
            'sffc_job',
            'normal',
            'high'
        );

        // Job Content Meta Box
        add_meta_box(
            'sffc_job_content',
            'Job Content & Skills',
            [$this, 'render_job_content_meta_box'],
            'sffc_job',
            'normal',
            'high'
        );

        // Source Information Meta Box
        add_meta_box(
            'sffc_job_source',
            'Source Information',
            [$this, 'render_job_source_meta_box'],
            'sffc_job',
            'side',
            'default'
        );
    }

    /**
     * Render job details meta box
     */
    public function render_job_details_meta_box($post)
    {
        // Add nonce for security
        wp_nonce_field('sffc_save_job_meta', 'sffc_job_meta_nonce');

        // Get existing values
        $company = get_post_meta($post->ID, 'sffc_company', true);
        $location = get_post_meta($post->ID, 'sffc_location', true);
        $salary_display = get_post_meta($post->ID, 'sffc_salary_display', true);
        $job_type = get_post_meta($post->ID, 'sffc_job_type', true);
    ?>
        <table class="form-table">
            <tr>
                <th><label for="sffc_company">Company</label></th>
                <td><input type="text" id="sffc_company" name="sffc_company" value="<?php echo esc_attr($company); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="sffc_location">Location</label></th>
                <td><input type="text" id="sffc_location" name="sffc_location" value="<?php echo esc_attr($location); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="sffc_salary_display">Salary Range</label></th>
                <td><input type="text" id="sffc_salary_display" name="sffc_salary_display" value="<?php echo esc_attr($salary_display); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="sffc_job_type">Job Type</label></th>
                <td><input type="text" id="sffc_job_type" name="sffc_job_type" value="<?php echo esc_attr($job_type); ?>" class="regular-text" /></td>
            </tr>
        </table>
    <?php
    }

    /**
     * Render job content meta box
     */
    public function render_job_content_meta_box($post)
    {
        // Get existing values
        $responsibilities = get_post_meta($post->ID, 'sffc_responsibilities', true);
        $qualifications = get_post_meta($post->ID, 'sffc_qualifications', true);
        $skills = get_post_meta($post->ID, 'sffc_skills', true);
        $skills_list = get_post_meta($post->ID, 'sffc_skills_list', true);
    ?>
        <table class="form-table">
            <tr>
                <th><label for="sffc_responsibilities">Responsibilities</label></th>
                <td><textarea id="sffc_responsibilities" name="sffc_responsibilities" rows="5" class="large-text"><?php echo esc_textarea($responsibilities); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="sffc_qualifications">Qualifications</label></th>
                <td><textarea id="sffc_qualifications" name="sffc_qualifications" rows="5" class="large-text"><?php echo esc_textarea($qualifications); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="sffc_skills_list">Skills (comma-separated)</label></th>
                <td>
                    <input type="text" id="sffc_skills_list" name="sffc_skills_list" value="<?php echo esc_attr($skills_list); ?>" class="large-text" />
                    <p class="description">Enter skills separated by commas</p>
                    <?php if (is_array($skills) && !empty($skills)): ?>
                        <p class="description">Extracted skills: <?php echo esc_html(implode(', ', array_slice($skills, 0, 10))); ?>
                            <?php if (count($skills) > 10): ?>... and <?php echo count($skills) - 10; ?> more<?php endif; ?></p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    <?php
    }

    /**
     * Render job source meta box
     */
    public function render_job_source_meta_box($post)
    {
        // Get existing values
        $source_type = get_post_meta($post->ID, 'sffc_source_type', true);
        $source_name = get_post_meta($post->ID, 'sffc_source_name', true);
        $external_id = get_post_meta($post->ID, 'sffc_external_id', true);
        $source_url = get_post_meta($post->ID, 'sffc_source_url', true);
    ?>
        <p>
            <strong>Source Type:</strong><br>
            <?php echo esc_html($source_type ?: 'Not set'); ?>
        </p>
        <p>
            <strong>Source Name:</strong><br>
            <?php echo esc_html($source_name ?: 'Not set'); ?>
        </p>
        <p>
            <strong>External ID:</strong><br>
            <code><?php echo esc_html($external_id ?: 'Not set'); ?></code>
        </p>
        <?php if ($source_url): ?>
            <p>
                <strong>Source URL:</strong><br>
                <a href="<?php echo esc_url($source_url); ?>" target="_blank">View Original</a>
            </p>
        <?php endif; ?>
<?php
    }

    /**
     * Save meta box data
     */
    public function save_job_meta_box_data($post_id)
    {
        // Check nonce
        if (!isset($_POST['sffc_job_meta_nonce']) || !wp_verify_nonce($_POST['sffc_job_meta_nonce'], 'sffc_save_job_meta')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save fields that can be edited
        if (isset($_POST['sffc_company'])) {
            update_post_meta($post_id, 'sffc_company', sanitize_text_field($_POST['sffc_company']));
        }

        if (isset($_POST['sffc_location'])) {
            update_post_meta($post_id, 'sffc_location', sanitize_text_field($_POST['sffc_location']));
        }

        if (isset($_POST['sffc_salary_display'])) {
            update_post_meta($post_id, 'sffc_salary_display', sanitize_text_field($_POST['sffc_salary_display']));
        }

        if (isset($_POST['sffc_job_type'])) {
            update_post_meta($post_id, 'sffc_job_type', sanitize_text_field($_POST['sffc_job_type']));
        }

        if (isset($_POST['sffc_responsibilities'])) {
            update_post_meta($post_id, 'sffc_responsibilities', sanitize_textarea_field($_POST['sffc_responsibilities']));
        }

        if (isset($_POST['sffc_qualifications'])) {
            update_post_meta($post_id, 'sffc_qualifications', sanitize_textarea_field($_POST['sffc_qualifications']));
        }

        if (isset($_POST['sffc_skills_list'])) {
            $skills_list = sanitize_text_field($_POST['sffc_skills_list']);
            update_post_meta($post_id, 'sffc_skills_list', $skills_list);

            // Also update skills array
            $skills_array = array_map('trim', explode(',', $skills_list));
            $skills_array = array_filter($skills_array); // Remove empty values
            update_post_meta($post_id, 'sffc_skills', $skills_array);
        }

        // Refresh formatted HTML composites after manual save
        $this->generate_formatted_content($post_id);
    }

    /**
     * Generate formatted HTML composites for highlights and description.
     */
    private function generate_formatted_content($post_id, array $job_data = [])
    {
        $salary = $job_data['salary_display']
            ?? ($job_data['estimated_salary']['display'] ?? get_post_meta($post_id, 'sffc_salary_display', true));

        $skills = [];
        if (!empty($job_data['skills']) && is_array($job_data['skills'])) {
            $skills = $job_data['skills'];
        } else {
            $skills_meta = get_post_meta($post_id, 'sffc_skills', true);
            if (is_array($skills_meta)) {
                $skills = $skills_meta;
            } else {
                $skills_list = $job_data['skills_list'] ?? get_post_meta($post_id, 'sffc_skills_list', true);
                if (!empty($skills_list)) {
                    $skills = array_map('trim', explode(',', $skills_list));
                }
            }
        }

        $level = $job_data['experience_level']
            ?? $job_data['seniority_level']
            ?? get_post_meta($post_id, 'sffc_experience_level', true);

        $experience = $job_data['pe_years_experience']
            ?? $job_data['years_experience']
            ?? $job_data['experience_years']
            ?? $job_data['experience_required']
            ?? get_post_meta($post_id, 'sffc_pe_years_experience', true);
        $experience = $this->normalize_experience_text($experience);

        $highlights_html = $this->build_job_highlights_html([
            'salary' => $salary,
            'skills' => $skills,
            'experience' => $experience,
            'level' => $level,
        ]);
        update_post_meta($post_id, 'sffc_job_highlights_css', $this->get_highlights_styles());
        update_post_meta($post_id, 'sffc_job_highlights_html', $highlights_html);

        $description = $job_data['description'] ?? get_post_meta($post_id, 'sffc_description', true);
        $responsibilities = $job_data['responsibilities'] ?? get_post_meta($post_id, 'sffc_responsibilities', true);
        $qualifications = $job_data['qualifications'] ?? get_post_meta($post_id, 'sffc_qualifications', true);
        $additional = $job_data['additional_info']
            ?? $job_data['additional_information']
            ?? get_post_meta($post_id, 'sffc_additional_info', true);

        $company = $job_data['company']
            ?? $job_data['actual_company']
            ?? get_post_meta($post_id, 'sffc_actual_company', true);
        $location_city = $job_data['location_city'] ?? get_post_meta($post_id, 'sffc_location_city', true);
        $location_country = $job_data['location_country'] ?? get_post_meta($post_id, 'sffc_location_country', true);

        $description_html = $this->build_job_description_html([
            'title' => get_the_title($post_id),
            'company' => $company,
            'location_city' => $location_city,
            'location_country' => $location_country,
            'salary' => $salary,
            'experience' => $experience,
            'level' => $level,
            'description' => $description,
            'responsibilities' => $responsibilities,
            'qualifications' => $qualifications,
            'skills' => $skills,
            'additional' => $additional,
            'post_id' => $post_id,
        ]);
        update_post_meta($post_id, 'sffc_description_html', $description_html);
    }

    /**
     * Build highlight HTML inspired by infographic layout.
     */
    private function build_job_highlights_html(array $context)
    {
        $salary = $this->clean_plain_text($context['salary'] ?? '');
        $experience = $this->clean_plain_text($context['experience'] ?? '');
        $level = $this->clean_plain_text($context['level'] ?? '');

        $skills = [];
        foreach ((array) ($context['skills'] ?? []) as $skill) {
            $cleaned = $this->clean_plain_text($skill);
            if ($cleaned !== '') {
                $skills[] = $cleaned;
            }
        }
        $skills = array_slice(array_unique($skills), 0, 6);

        $blocks = [];

        if ($salary !== '') {
            $blocks[] = '<div class="sffc-highlight-card"><h3>Salary Snapshot</h3><p class="sffc-highlight-value">' . esc_html($salary) . '</p></div>';
        }

        if (!empty($skills)) {
            $items = '';
            foreach ($skills as $skill) {
                $items .= '<li>' . esc_html($skill) . '</li>';
            }
            $blocks[] = '<div class="sffc-highlight-card"><h3>Core Skills</h3><ul class="sffc-highlight-list">' . $items . '</ul></div>';
        }

        if ($experience !== '' || $level !== '') {
            $details = '';
            if ($experience !== '') {
                $details .= '<p class="sffc-highlight-value">' . esc_html($experience) . '</p>';
            }
            if ($level !== '') {
                $details .= '<p class="sffc-highlight-note">' . esc_html($level) . '</p>';
            }
            $blocks[] = '<div class="sffc-highlight-card"><h3>Experience</h3>' . $details . '</div>';
        }

        if (empty($blocks)) {
            return '';
        }

        $html = '<div class="sffc-job-highlights">' . implode('', $blocks) . '</div>';

        return $this->sanitize_formatted_html($html);
    }

    private function get_highlights_styles()
    {
        return implode('', [
            '.sffc-job-highlights{display:grid;gap:18px;margin:30px auto;padding:24px;',
            'border-radius:14px;background:linear-gradient(135deg,#0d353e 0%,#1a5a65 100%);color:#fff;',
            'font-family:"Inter",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;box-shadow:0 20px 40px rgba(13,53,62,0.35);',
            'max-width:960px;}',
            '.sffc-highlight-card{background:rgba(255,255,255,0.08);border-radius:12px;padding:22px;',
            'box-shadow:0 14px 26px rgba(0,0,0,0.16);display:flex;flex-direction:column;gap:10px;',
            'border:1px solid rgba(255,255,255,0.15);}',
            '.sffc-highlight-card h3{margin:0;font-size:16px;font-weight:600;letter-spacing:0.8px;',
            'text-transform:uppercase;color:rgba(255,255,255,0.85);}',
            '.sffc-highlight-value{font-size:28px;font-weight:600;margin:0;color:#fff;}',
            '.sffc-highlight-note{margin:0;font-size:14px;color:rgba(255,255,255,0.82);}',
            '.sffc-highlight-list,.sffc-highlight-list li{list-style:none;margin:0;padding:0;}',
            '.sffc-highlight-list{display:flex;flex-wrap:wrap;gap:12px;}',
            '.sffc-highlight-list li{background:rgba(255,255,255,0.18);border-radius:24px;padding:8px 16px;',
            'font-size:13px;font-weight:500;}',
            '@media(max-width:840px){.sffc-job-highlights{grid-template-columns:1fr;}}',
        ]);
    }

    private function get_description_styles()
    {
        return implode('', [
            '.sffc-job-cv{font-family:"Inter",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;',
            'background:#ffffff;border-radius:12px;box-shadow:0 12px 28px rgba(19,45,81,0.12);overflow:hidden;',
            'max-width:860px;margin:35px auto;}',
            '.sffc-job-cv__header{background:linear-gradient(135deg,#0d353e 0%,#1a5a65 100%);color:#fff;',
            'padding:32px 26px;text-align:center;}',
            '.sffc-job-cv__header h2{margin:0;font-size:26px;font-weight:600;color:#ffffff;}',
            '.sffc-job-cv__header p{margin:10px 0 0;font-size:14px;opacity:0.9;}',
            '.sffc-job-profile__section{padding:28px 32px;border-top:1px solid #edf1f6;}',
            '.sffc-job-profile__section:first-child{border-top:none;}',
            '.sffc-job-profile__section h3{margin:0 0 14px;font-size:18px;font-weight:600;',
            'text-transform:uppercase;letter-spacing:0.6px;color:#1e4670;}',
            '.sffc-section__intro{margin:0 0 16px;font-size:14px;line-height:1.6;background:#fff8f3;',
            'padding:14px 16px;border-left:4px solid #b08d57;border-radius:6px;color:#4a3a2a;}',
            '.sffc-section__body{margin:0 0 16px;font-size:15px;color:#2d3f52;line-height:1.7;}',
            '.sffc-detail-list{list-style:none;margin:0;padding:0;border-radius:10px;background:#f6fafc;',
            'display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));overflow:hidden;}',
            '.sffc-detail-list li{padding:16px 18px;border-bottom:1px solid #e2ebf4;display:flex;',
            'flex-direction:column;gap:6px;}',
            '.sffc-detail-list li:nth-last-child(1){border-bottom:none;}',
            '.sffc-detail-list__label{text-transform:uppercase;font-size:12px;letter-spacing:0.5px;',
            'color:#6b7a8b;}',
            '.sffc-detail-list__value{font-size:15px;font-weight:600;color:#16314a;}',
            '.sffc-section__list{list-style:none;margin:0;padding:0;display:grid;gap:12px;}',
            '.sffc-section__list li{background:#f7faff;border:1px solid #d5e5ff;border-radius:12px;',
            'padding:14px 16px;font-size:14px;line-height:1.65;color:#1f3650;}',
            '.sffc-section__list--stacked li{display:flex;flex-direction:column;gap:6px;}',
            '.sffc-section__list--stacked span{font-size:13px;color:#52647c;}',
            '.sffc-skill-tags{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:12px;}',
            '.sffc-skill-tags li{background:linear-gradient(135deg,#0d353e 0%,#1a5a65 100%);color:#fff;',
            'padding:8px 16px;border-radius:22px;font-size:13px;font-weight:500;}',
            '.sffc-job-profile__section--credentials{background:#fffaf4;}',
            '.sffc-job-cv__footer{text-align:center;padding:24px;border-top:1px solid #edf1f6;background:#f9fcfe;}',
            '.sffc-job-cv__btn{display:inline-flex;align-items:center;gap:10px;background:linear-gradient(135deg,#b08d57 0%,#cb997e 100%);',
            'color:#fff;text-decoration:none;padding:12px 26px;border-radius:8px;font-weight:600;font-size:15px;',
            'transition:all 0.2s ease;box-shadow:0 8px 18px rgba(176,141,87,0.35);}',
            '.sffc-job-cv__btn:hover{transform:translateY(-2px);box-shadow:0 12px 22px rgba(176,141,87,0.45);}',
            '.sffc-job-profile__section--credentials{background:#fffaf4;}',
            '@media(max-width:760px){.sffc-job-cv{margin:20px auto;} .sffc-job-profile__section{padding:24px;} .sffc-detail-list{grid-template-columns:1fr;}}',
        ]);
    }

    public function print_job_inline_styles()
    {
        if (!is_singular('sffc_job')) {
            return;
        }

        $post_id = get_queried_object_id();
        if (!$post_id) {
            return;
        }

        $styles = [];

        $highlights_css = get_post_meta($post_id, 'sffc_job_highlights_css', true);
        if (!empty($highlights_css)) {
            $styles[] = trim((string) $highlights_css);
        }

        $description_css = get_post_meta($post_id, 'sffc_description_css', true);
        if (!empty($description_css)) {
            $styles[] = trim((string) $description_css);
        }

        if (empty($styles)) {
            return;
        }

        echo "\n<style id=\"sffc-job-inline-css\">\n" . implode("\n\n", $styles) . "\n</style>\n";
    }

    public function add_job_admin_columns($columns)
    {
        $columns['sffc_recruiter'] = __('Recruiter', 'senna-finance');
        return $columns;
    }

    public function render_job_admin_column($column, $post_id)
    {
        if ($column !== 'sffc_recruiter') {
            return;
        }

        $recruiter_id = (int) get_post_meta($post_id, '_sffc_recruiter_id', true);
        if ($recruiter_id && get_post_status($recruiter_id)) {
            $title = get_the_title($recruiter_id);
            echo '<a href="' . esc_url(get_edit_post_link($recruiter_id)) . '">' . esc_html($title) . '</a>';
            return;
        }

        $name = get_post_meta($post_id, 'sffc_recruiter_name', true);
        echo $name ? esc_html($name) : '—';
    }

    /**
     * Build a structured description layout inspired by the CV template.
     */
    private function build_job_description_html(array $context)
    {
        $post_id = intval($context['post_id'] ?? 0);
        $title_fallback = $context['title'] ?? '';

        $parsed = $this->parse_description_content($context['description'] ?? '');

        $intro = $parsed['intro'];
        $details = $parsed['details'];
        $responsibilities = $parsed['responsibilities'];
        $qualifications = $parsed['qualifications'];
        $narrative_sections = $parsed['sections'];
        $raw_text = $parsed['raw_text'];

        if (empty($responsibilities)) {
            $responsibilities = $this->extract_list_items($context['responsibilities'] ?? '');
        }

        if (empty($qualifications)) {
            $qualifications = $this->extract_list_items($context['qualifications'] ?? '');
        }

        if (empty($narrative_sections) && !empty($context['additional'])) {
            $supplement = $this->format_paragraphs($context['additional']);
            if (!empty($supplement)) {
                $narrative_sections[] = [
                    'key' => 'other',
                    'heading' => 'Additional Insights',
                    'paragraphs' => $supplement,
                    'type' => 'paragraphs',
                ];
            }
        }

        $skills = [];
        foreach ((array) ($context['skills'] ?? []) as $skill) {
            $cleaned = $this->clean_plain_text($skill);
            if ($cleaned !== '') {
                $skills[] = $cleaned;
            }
        }
        $skills = array_slice(array_unique($skills), 0, 12);

        $certifications = $this->detect_certification_mentions($raw_text, $qualifications, $skills);

        $sections = [];

        if (!empty($intro) || !empty($details)) {
            $section = '<section class="sffc-job-profile__section sffc-job-profile__section--overview">';
            $section .= '<h3>Role Overview</h3>';
            $section .= '<p class="sffc-section__intro">Start by showing recruiters you understand the team\'s mission and environment.</p>';
            foreach ($intro as $paragraph) {
                $section .= '<p class="sffc-section__body">' . nl2br(esc_html($paragraph)) . '</p>';
            }
            if (!empty($details)) {
                $section .= '<ul class="sffc-detail-list">';
                foreach ($details as $detail) {
                    $section .= '<li><span class="sffc-detail-list__label">' . esc_html($detail['label']) . '</span><span class="sffc-detail-list__value">' . esc_html($detail['value']) . '</span></li>';
                }
                $section .= '</ul>';
            }
            $section .= '</section>';
            $sections[] = $section;
        }

        if (!empty($responsibilities)) {
            $items = '';
            foreach (array_slice($responsibilities, 0, 12) as $item) {
                $items .= '<li>' . esc_html($item) . '</li>';
            }
            $section = '<section class="sffc-job-profile__section sffc-job-profile__section--responsibilities">';
            $section .= '<h3>What You\'ll Do</h3>';
            $section .= '<p class="sffc-section__intro">Focus your CV bullets on these outcomes and examples of ownership.</p>';
            $section .= '<ul class="sffc-section__list">' . $items . '</ul>';
            $section .= '</section>';
            $sections[] = $section;
        }

        if (!empty($qualifications)) {
            $items = '';
            foreach (array_slice($qualifications, 0, 12) as $item) {
                $items .= '<li>' . esc_html($item) . '</li>';
            }
            $section = '<section class="sffc-job-profile__section sffc-job-profile__section--qualifications">';
            $section .= '<h3>What You\'ll Bring</h3>';
            $section .= '<p class="sffc-section__intro">Highlight these strengths explicitly in your resume and interviews.</p>';
            $section .= '<ul class="sffc-section__list">' . $items . '</ul>';
            $section .= '</section>';
            $sections[] = $section;
        }

        if (!empty($certifications)) {
            $items = '';
            foreach ($certifications as $cert) {
                $items .= '<li><strong>' . esc_html($cert['label']) . '</strong><span>' . esc_html($cert['note']) . '</span></li>';
            }
            $section = '<section class="sffc-job-profile__section sffc-job-profile__section--credentials">';
            $section .= '<h3>Credential Spotlight</h3>';
            $section .= '<p class="sffc-section__intro">If you\'re progressing toward these certifications, make it visible.</p>';
            $section .= '<ul class="sffc-section__list sffc-section__list--stacked">' . $items . '</ul>';
            $section .= '</section>';
            $sections[] = $section;
        }

        if (!empty($skills)) {
            $items = '';
            foreach ($skills as $skill) {
                $items .= '<li>' . esc_html($skill) . '</li>';
            }
            $section = '<section class="sffc-job-profile__section sffc-job-profile__section--skills">';
            $section .= '<h3>Skills Snapshot</h3>';
            $section .= '<p class="sffc-section__intro">Double down on these tools and frameworks in your application.</p>';
            $section .= '<ul class="sffc-skill-tags">' . $items . '</ul>';
            $section .= '</section>';
            $sections[] = $section;
        }

        foreach ($narrative_sections as $segment) {
            $titleMeta = $this->map_narrative_section_heading($segment['key'], $segment['heading']);
            $title = $titleMeta['title'];
            $introCopy = $titleMeta['intro'];

            $contentBlocks = '';
            foreach ($segment['paragraphs'] as $block) {
                $listItems = $this->normalize_list_items($block, 12);
                if (count($listItems) >= 2) {
                    $contentBlocks .= '<ul class="sffc-section__list">';
                    foreach ($listItems as $item) {
                        $contentBlocks .= '<li>' . esc_html($item) . '</li>';
                    }
                    $contentBlocks .= '</ul>';
                } else {
                    $contentBlocks .= '<p class="sffc-section__body">' . nl2br(esc_html($block)) . '</p>';
                }
            }

            if ($contentBlocks === '') {
                continue;
            }

            $section = '<section class="sffc-job-profile__section sffc-job-profile__section--' . esc_attr($segment['key']) . '">';
            $section .= '<h3>' . esc_html($title) . '</h3>';
            if ($introCopy !== '') {
                $section .= '<p class="sffc-section__intro">' . esc_html($introCopy) . '</p>';
            }
            $section .= $contentBlocks;
            $section .= '</section>';
            $sections[] = $section;
        }

        $body = implode('', $sections);

        if ($body === '') {
            return '';
        }

        $heading = $title_fallback;
        if ($post_id) {
            $post_title = get_the_title($post_id);
            if (!empty($post_title)) {
                $heading = $post_title;
            }
        }

        $link = home_url('/senna-ai/');
        $container = '<div class="sffc-job-cv">'
            . '<div class="sffc-job-cv__header">'
            . '<h2>' . esc_html($heading) . '</h2>'
            . '<p>Curated brief to help you tailor your application.</p>'
            . '</div>'
            . '<div class="sffc-job-profile">' . $body . '</div>'
            . '<div class="sffc-job-cv__footer">'
            . '<a class="sffc-job-cv__btn" href="' . esc_url(add_query_arg([
                'utm_source' => 'job_pages',
                'utm_medium' => 'guidance',
                'utm_campaign' => 'sffc_jobs',
            ], $link)) . '">'
            . '<span>Improve this application with MENA Careers AI</span>'
            . '</a>'
            . '</div>'
            . '</div>';

        $html = $this->sanitize_formatted_html($container);

        update_post_meta($post_id, 'sffc_description_css', $this->get_description_styles());

        return $html;
    }

    private function parse_description_content($text)
    {
        $result = [
            'intro' => [],
            'details' => [],
            'responsibilities' => [],
            'qualifications' => [],
            'sections' => [],
            'raw_text' => '',
        ];

        if ($text === null || $text === '') {
            return $result;
        }

        $normalized = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/<br\s*\/?>(\s)*/i', "\n", $normalized);
        $normalized = preg_replace('/<li[^>]*>/i', "\n• ", $normalized);
        $normalized = preg_replace('/<\/li>/i', "\n", $normalized);
        $normalized = preg_replace('/<\/(p|div|h[1-6])>/i', "</$1>\n\n", $normalized);
        $normalized = preg_replace('/\r\n?/', "\n", $normalized);
        $normalized = preg_replace('/\n{3,}/', "\n\n", $normalized);

        $result['raw_text'] = wp_strip_all_tags($normalized);

        $paragraphs = preg_split('/\n{2,}/', $normalized);
        if ($paragraphs === false) {
            $paragraphs = [$normalized];
        }

        $currentKey = 'intro';
        $currentSectionIndex = null;

        foreach ($paragraphs as $paragraph) {
            $plain = trim(wp_strip_all_tags($paragraph));
            if ($plain === '') {
                continue;
            }

            $heading = $this->detect_description_heading($plain);
            if ($heading) {
                $currentKey = $heading['key'];
                if (in_array($currentKey, ['program', 'compensation', 'application', 'other'], true)) {
                    $result['sections'][] = [
                        'key' => $currentKey,
                        'heading' => $heading['heading'],
                        'paragraphs' => [],
                        'type' => $heading['type'],
                    ];
                    $currentSectionIndex = count($result['sections']) - 1;
                } else {
                    $currentSectionIndex = null;
                }
                continue;
            }

            if ($currentKey === 'intro') {
                if (preg_match('/^([A-Za-z][A-Za-z0-9 \-&\/]+):\s*(.+)$/', $plain, $matches) && strlen($matches[1]) <= 60) {
                    $result['details'][] = [
                        'label' => trim($matches[1]),
                        'value' => trim($matches[2]),
                    ];
                    continue;
                }
                $result['intro'][] = $plain;
                continue;
            }

            if ($currentKey === 'responsibilities') {
                $result['responsibilities'] = array_merge(
                    $result['responsibilities'],
                    $this->normalize_list_items($plain, 20)
                );
                continue;
            }

            if ($currentKey === 'qualifications') {
                $result['qualifications'] = array_merge(
                    $result['qualifications'],
                    $this->normalize_list_items($plain, 20)
                );
                continue;
            }

            if (in_array($currentKey, ['program', 'compensation', 'application', 'other'], true)) {
                if ($currentSectionIndex !== null) {
                    $result['sections'][$currentSectionIndex]['paragraphs'][] = $plain;
                }
                continue;
            }

            // Fallback: treat as additional narrative
            $result['sections'][] = [
                'key' => 'other',
                'heading' => '',
                'paragraphs' => [$plain],
                'type' => 'paragraphs',
            ];
        }

        $result['intro'] = array_slice($result['intro'], 0, 4);
        $result['responsibilities'] = $this->normalize_list_items($result['responsibilities'], 20);
        $result['qualifications'] = $this->normalize_list_items($result['qualifications'], 20);

        return $result;
    }

    private function detect_description_heading($line)
    {
        $clean = strtolower(trim($line));
        $clean = ltrim($clean, "•*- ");
        $clean = rtrim($clean, ':');
        $clean = preg_replace('/\s+/', ' ', $clean);

        $map = [
            'responsibilities' => ['key responsibilities', 'responsibilities', 'what you will do', 'what you\'ll do', 'primary responsibilities', 'main responsibilities', 'day-to-day responsibilities', 'duties', 'key responsibilities include'],
            'qualifications' => ['qualifications', 'required qualifications', 'preferred qualifications', 'what you will bring', 'what you\'ll bring', 'skills and experience', 'skills & experience', 'experience & skills', 'who you are', 'candidate profile', 'requirements'],
            'program' => ['program description', 'about the role', 'about the program', 'about the team', 'job description', 'role description', 'about blackstone', 'about us'],
            'compensation' => ['compensation', 'salary', 'salary range', 'reward', 'benefits', 'additional compensation', 'remuneration'],
            'application' => ['how to apply', 'application instructions', 'application process', 'to submit your application', 'next steps'],
            'other' => ['additional information', 'important information', 'notes', 'what we offer', 'why it matters']
        ];

        foreach ($map as $key => $labels) {
            foreach ($labels as $label) {
                $pattern = '/^' . preg_quote($label, '/') . '(\b|\s|$)/';
                if ($clean === $label || preg_match($pattern, $clean)) {
                    return [
                        'key' => $key,
                        'heading' => ucwords($label),
                        'type' => in_array($key, ['responsibilities', 'qualifications'], true) ? 'list' : 'paragraphs',
                    ];
                }
            }
        }

        return null;
    }

    private function map_narrative_section_heading($key, $fallback)
    {
        $titles = [
            'program' => [
                'title' => 'Inside the Program',
                'intro' => 'Understand how the rotation and mentorship will run.',
            ],
            'compensation' => [
                'title' => 'Reward & Progression',
                'intro' => 'See how compensation, benefits, and growth are framed.',
            ],
            'application' => [
                'title' => 'Application Guidance',
                'intro' => 'Prepare these materials before you press submit.',
            ],
            'other' => [
                'title' => $fallback !== '' ? $fallback : 'Additional Insights',
                'intro' => 'Extra context the hiring team thought you should know.',
            ],
        ];

        if (isset($titles[$key])) {
            return $titles[$key];
        }

        return [
            'title' => $fallback !== '' ? $fallback : 'Additional Insights',
            'intro' => '',
        ];
    }

    private function normalize_list_items($value, $max = 12)
    {
        $lines = is_array($value) ? $value : preg_split('/\n+/', (string) $value);
        if ($lines === false) {
            $lines = [(string) $value];
        }

        $items = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/^[\-•\*\u2022\u2023\u25E6\u2043\u2219]+\s*/u', '', (string) $line));
            if ($line === '') {
                continue;
            }
            if ($this->detect_description_heading($line)) {
                continue;
            }
            $items[] = $line;
            if (count($items) >= $max) {
                break;
            }
        }

        return array_values(array_unique($items));
    }

    private function detect_certification_mentions($text, array $qualifications, array $skills)
    {
        $haystack = strtolower($text . ' ' . implode(' ', $qualifications) . ' ' . implode(' ', $skills));

        $certifications = [
            'ACA' => 'Show evidence of ACA exam progress or chartered status.',
            'ACCA' => 'Reference your ACCA papers or membership level.',
            'CIMA' => 'Link CIMA progress to commercial or FP&A experience.',
            'CFA' => 'Call out CFA exam level, passes, or charterholder status.',
            'CPA' => 'Demonstrate public accounting or audit experience supporting the CPA.',
            'FRM' => 'Highlight risk management projects that align with the FRM.',
            'CAIA' => 'Connect CAIA studies to alternative investment exposure.',
            'CMA' => 'Tie CMA knowledge to management accounting deliverables.',
            'MBA' => 'Emphasise MBA focus areas relevant to the mandate.',
        ];

        $found = [];
        foreach ($certifications as $label => $note) {
            if (strpos($haystack, strtolower($label)) !== false) {
                $found[$label] = [
                    'label' => $label,
                    'note' => $note,
                ];
            }
        }

        // Additional phrases without abbreviations
        $patterns = [
            'Certified Public Accountant' => 'Showcase audit rotations or controllership projects tied to the CPA.',
            'Chartered Financial Analyst' => 'Mention equity research or portfolio work that supports the CFA journey.',
        ];

        foreach ($patterns as $phrase => $note) {
            if (strpos($haystack, strtolower($phrase)) !== false) {
                $label = $phrase;
                if (!isset($found[$label])) {
                    $found[$label] = [
                        'label' => $phrase,
                        'note' => $note,
                    ];
                }
            }
        }

        return array_values($found);
    }

    /**
     * Constrain HTML output to safe tags and attributes.
     */
    private function sanitize_formatted_html($html)
    {
        $html = (string) $html;
        if ($html === '') {
            return '';
        }

        $allowed = wp_kses_allowed_html('post');
        $tags = ['div', 'section', 'header', 'h2', 'h3', 'h4', 'p', 'ul', 'ol', 'li', 'span', 'strong', 'em', 'a'];
        foreach ($tags as $tag) {
            if (!isset($allowed[$tag])) {
                $allowed[$tag] = [];
            }
            $allowed[$tag]['class'] = true;
            if ($tag === 'a') {
                $allowed[$tag]['href'] = true;
                $allowed[$tag]['target'] = true;
                $allowed[$tag]['rel'] = true;
            }
        }

        return wp_kses($html, $allowed);
    }

    /**
     * Normalize plain text strings.
     */
    private function clean_plain_text($value)
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            $value = implode(' ', $value);
        }

        $value = wp_strip_all_tags((string) $value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }

    /**
     * Normalize experience copy for consistent display.
     */
    private function normalize_experience_text($value)
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_array($value)) {
            $value = implode(' ', $value);
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        $clean = $this->clean_plain_text($raw);

        if (preg_match('/^\d+$/', $clean)) {
            return $clean . ' years experience';
        }

        if (preg_match('/^\d+\s*[-–]\s*\d+$/', $clean)) {
            return $clean . ' years experience';
        }

        if (preg_match('/\d/', $clean) && stripos($clean, 'year') === false) {
            return $clean . ' years experience';
        }

        if (stripos($clean, 'experience') === false && stripos($clean, 'year') === false) {
            return $clean . ' experience';
        }

        return $clean;
    }

    /**
     * Convert free-form text into paragraphs.
     */
    private function format_paragraphs($text)
    {
        if ($text === null || $text === '') {
            return [];
        }

        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/<br\s*\/?>(\s)*/i', "\n", $text);
        $text = preg_replace('/<\/p>/i', "</p>\n", $text);
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\r\n?/', "\n", $text);
        $text = preg_replace('/\n{2,}/', "\n\n", $text);
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $parts = preg_split('/\n\n+/', $text);
        if ($parts === false) {
            $parts = [$text];
        }

        $parts = array_values(array_filter(array_map('trim', $parts)));

        if (empty($parts)) {
            $parts = [trim($text)];
        }

        return $parts;
    }

    /**
     * Extract list-style items from mixed text content.
     */
    private function extract_list_items($text)
    {
        if ($text === null || $text === '') {
            return [];
        }

        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/<li[^>]*>/i', '<li>', $text);
        $text = preg_replace('/<\/li>/i', "</li>\n", $text);
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\r\n?/', "\n", $text);
        $text = preg_replace('/[\u2022\u2023\u25E6\u2043\u2219]/u', "\n", $text);
        $text = preg_replace('/^\s*[-–—]\s+/m', '', $text);
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $lines = preg_split('/\n+/', $text);
        if ($lines === false) {
            $lines = [$text];
        }

        $lines = array_filter(array_map('trim', $lines));

        if (empty($lines)) {
            $sentences = preg_split('/\.\s*/', $text);
            $lines = array_filter(array_map('trim', $sentences));
        }

        $unique = array_unique($lines);

        return array_slice($unique, 0, 8);
    }
}

// Initialize
function sffc_custom_post_types()
{
    return SFFC_Custom_Post_Types::get_instance();
}
