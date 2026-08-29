<?php

/**
 * Company Intelligence Engine
 * Core system for managing PE firm intelligence and relationships
 * 
 * @package SennaCareers
 * @since 10.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Company_Intelligence_Engine
{

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * PE firm data cache
     */
    private $firms_cache = array();

    /**
     * Top PE firms to track - Comprehensive list
     */
    private $top_firms = array();

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
        $this->initialize_firms_data();
        $this->init_hooks();
    }

    /**
     * Initialize comprehensive PE firms data
     */
    private function initialize_firms_data()
    {
        // Major Global PE Firms - Tier 1
        $tier1_firms = array(
            'KKR',
            'EQT',
            'Blackstone',
            'Thoma Bravo',
            'TPG',
            'CVC Capital Partners',
            'Hg',
            'Hellman & Friedman',
            'Clayton, Dubilier & Rice',
            'Insight Partners',
            'Silver Lake',
            'Clearlake Capital Group',
            'General Atlantic',
            'Goldman Sachs Asset Management',
            'Bain Capital',
            'Advent International',
            'The Carlyle Group',
            'Warburg Pincus',
            'Andreessen Horowitz',
            'Vista Equity Partners',
            'Apollo Global Management'
        );

        // Major Global PE Firms - Tier 2
        $tier2_firms = array(
            'Neuberger Berman',
            'TA Associates',
            'GTCR',
            'Veritas Capital',
            'Bridgepoint',
            'New Mountain Capital',
            'Partners Group',
            'Cinven',
            'Apax Partners',
            'Stone Point Capital',
            'Nordic Capital',
            'Leonard Green & Partners',
            'Francisco Partners',
            'Tiger Global Management',
            'Blue Owl Capital',
            'Brookfield Asset Management',
            'Genstar Capital',
            'Permira Advisers',
            'BDT & MSD Partners',
            'L Catterton',
            'Summit Partners',
            'Ardian',
            'Platinum Equity'
        );

        // Regional Powerhouses
        $regional_firms = array(
            'China Merchants Capital',
            'Hillhouse Capital Group',
            'PSG',
            'HarbourVest Partners',
            'The Jordan Company',
            'ICONIQ Capital',
            'Hamilton Lane',
            'BlackRock',
            'Astorg',
            'China Reform Fund Management Corporation',
            'Vitruvian Partners',
            'PAI Partners',
            'MBK Partners',
            'HongShan'
        );

        // Tech-Focused Firms
        $tech_firms = array(
            'Accel',
            'Lightspeed Venture Partners',
            'Coatue Management',
            'General Catalyst Partners',
            'Thrive Capital',
            'New Enterprise Associates',
            'Bessemer Venture Partners',
            'TCV',
            'Index Ventures',
            'Battery Ventures',
            'Founders Fund',
            'Kleiner Perkins',
            'Khosla Ventures',
            'Y Combinator',
            'Sequoia Capital',
            'Menlo Ventures',
            'GreenOaks Capital Partners'
        );

        // Growth & Mid-Market Firms
        $growth_firms = array(
            'Berkshire Partners',
            'Roark Capital Group',
            'H.I.G Capital',
            'Thomas H. Lee Partners',
            'BC Partners',
            'LGT Capital Partners',
            'Adams Street Partners',
            'Morgan Stanley Investment Management',
            'Oak Hill Capital',
            'GI Partners',
            'Oaktree Capital Management',
            'KPS Capital Partners',
            'Centerbridge Partners',
            'IK Partners',
            'Alpine Investors',
            'Madison Dearborn Partners',
            'Lindsay Goldberg'
        );

        // Specialized & Sector-Focused
        $specialized_firms = array(
            'Quantum Energy Partners',
            'K1 Investment Management',
            'Patient Square Capital',
            'Arctos Partners',
            'ARCH Venture Partners',
            'Waterland Private Equity Investments',
            'TSG Consumer Partners',
            'Archimed',
            'Frazier Healthcare Partners',
            'OrbiMed Advisors',
            'NGP Energy Capital Management',
            'Energy Impact Partners',
            'Oak HC/FT',
            'Vivo Capital',
            'LS Power Group',
            'Denham Capital Management'
        );

        // European Firms
        $european_firms = array(
            'EQT',
            'CVC Capital Partners',
            'Hg',
            'Cinven',
            'Apax Partners',
            'Nordic Capital',
            'Bridgepoint',
            'PAI Partners',
            'Permira Advisers',
            'Ardian',
            'Astorg',
            'IK Partners',
            'Waterland Private Equity Investments',
            'Oakley Capital Private Equity',
            'Inflexion Private Equity Partners',
            'Eurazeo',
            'Investindustrial',
            'TowerBrook Capital Partners',
            'Montagu Private Equity',
            'TDR Capital',
            'Triton Partners',
            'Keensight Capital',
            'EMK Capital',
            'Main Capital Partners',
            'Montefiore Investment',
            'Antin',
            'Equistone Partners Europe',
            'Mid Europa Partners',
            '3i Group'
        );

        // Additional Major Firms
        $additional_firms = array(
            'Welsh, Carson, Anderson & Stowe',
            'Providence Equity Partners',
            'Lexington Partners',
            'Cerberus Capital Management',
            'Reverence Capital Partners',
            'The Riverside Company',
            'American Securities',
            'Shore Capital Partners',
            'Crestview Partners',
            'ABRY Partners',
            'ACON Investments',
            'Altaris Capital Partners',
            'Court Square Capital Partners',
            'Freeman Spogli & Co',
            'Littlejohn & Co',
            'Marlin Equity Partners',
            'MidOcean Partners',
            'Olympus Partners',
            'Sun Capital Partners',
            'Sycamore Partners',
            'Vestar Capital Partners'
        );

        // Combine all firms and remove duplicates
        $all_firms = array_unique(array_merge(
            $tier1_firms,
            $tier2_firms,
            $regional_firms,
            $tech_firms,
            $growth_firms,
            $specialized_firms,
            $european_firms,
            $additional_firms
        ));

        // Initialize the firms array with proper structure
        foreach ($all_firms as $firm_name) {
            $slug = $this->generate_firm_slug($firm_name);
            $this->top_firms[$slug] = array(
                'name' => $firm_name,
                'full_name' => $firm_name,
                'aliases' => $this->generate_firm_aliases($firm_name),
                'type' => $this->determine_firm_type($firm_name),
                'focus' => $this->determine_firm_focus($firm_name)
            );
        }
    }

    /**
     * Generate slug from firm name
     */
    private function generate_firm_slug($name)
    {
        return strtolower(str_replace(array(' ', '&', '.', ','), '-', $name));
    }

    /**
     * Generate common aliases for a firm
     */
    private function generate_firm_aliases($name)
    {
        $aliases = array();

        // Add common variations
        $aliases[] = str_replace('&', 'and', $name);
        $aliases[] = str_replace(' Capital', '', $name);
        $aliases[] = str_replace(' Partners', '', $name);
        $aliases[] = str_replace(' Management', '', $name);
        $aliases[] = str_replace(' Group', '', $name);

        // Special cases
        if (strpos($name, 'Goldman Sachs') !== false) {
            $aliases[] = 'Goldman';
            $aliases[] = 'GS';
            $aliases[] = 'GSAM';
        }
        if ($name === 'KKR') {
            $aliases[] = 'Kohlberg Kravis Roberts';
            $aliases[] = 'KKR & Co';
        }
        if ($name === 'TPG') {
            $aliases[] = 'Texas Pacific Group';
            $aliases[] = 'TPG Capital';
        }
        if (strpos($name, 'Carlyle') !== false) {
            $aliases[] = 'Carlyle';
            $aliases[] = 'CG';
        }

        return array_unique(array_filter($aliases));
    }

    /**
     * Determine firm type based on name
     */
    private function determine_firm_type($name)
    {
        if (in_array($name, array('Y Combinator', 'Techstars'))) {
            return 'accelerator';
        }
        if (strpos($name, 'Venture') !== false || strpos($name, 'Ventures') !== false) {
            return 'venture_capital';
        }
        if (strpos($name, 'Growth') !== false) {
            return 'growth_equity';
        }
        return 'private_equity';
    }

    /**
     * Determine firm focus based on name
     */
    private function determine_firm_focus($name)
    {
        if (strpos($name, 'Healthcare') !== false || strpos($name, 'OrbiMed') !== false) {
            return 'healthcare';
        }
        if (strpos($name, 'Energy') !== false || strpos($name, 'Quantum') !== false) {
            return 'energy';
        }
        if (strpos($name, 'Technology') !== false || in_array($name, array('Thoma Bravo', 'Vista Equity Partners', 'Insight Partners'))) {
            return 'technology';
        }
        if (strpos($name, 'Consumer') !== false || $name === 'TSG Consumer Partners') {
            return 'consumer';
        }
        return 'generalist';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Register company post type - HIGH PRIORITY to ensure it's registered early
        add_action('init', array($this, 'register_company_post_type'), 5);

        // Register company post type enhancements
        add_action('init', array($this, 'enhance_company_post_type'), 20);

        // News processing hooks
        add_filter('sffc_process_news_item', array($this, 'link_news_to_company'), 10, 2);
        add_action('sffc_news_saved', array($this, 'process_news_relationships'), 10, 2);

        // AJAX endpoints
        add_action('wp_ajax_sffc_get_company_intelligence', array($this, 'ajax_get_company_intelligence'));
        add_action('wp_ajax_nopriv_sffc_get_company_intelligence', array($this, 'ajax_get_company_intelligence'));

        // Cron jobs
        add_action('sffc_update_company_metrics', array($this, 'update_company_metrics'));

        if (!wp_next_scheduled('sffc_update_company_metrics')) {
            wp_schedule_event(time(), 'hourly', 'sffc_update_company_metrics');
        }
    }

    /**
     * Register the company post type
     */
    public function register_company_post_type()
    {
        $labels = array(
            'name'                  => _x('PE Firms', 'Post type general name', 'senna-finance'),
            'singular_name'         => _x('PE Firm', 'Post type singular name', 'senna-finance'),
            'menu_name'             => _x('PE Firms', 'Admin Menu text', 'senna-finance'),
            'name_admin_bar'        => _x('PE Firm', 'Add New on Toolbar', 'senna-finance'),
            'add_new'               => __('Add New', 'senna-finance'),
            'add_new_item'          => __('Add New PE Firm', 'senna-finance'),
            'new_item'              => __('New PE Firm', 'senna-finance'),
            'edit_item'             => __('Edit PE Firm', 'senna-finance'),
            'view_item'             => __('View PE Firm', 'senna-finance'),
            'all_items'             => __('All PE Firms', 'senna-finance'),
            'search_items'          => __('Search PE Firms', 'senna-finance'),
            'parent_item_colon'     => __('Parent PE Firms:', 'senna-finance'),
            'not_found'             => __('No PE firms found.', 'senna-finance'),
            'not_found_in_trash'    => __('No PE firms found in Trash.', 'senna-finance'),
            'featured_image'        => _x('PE Firm Logo', 'Overrides the "Featured Image" phrase', 'senna-finance'),
            'set_featured_image'    => _x('Set firm logo', 'Overrides the "Set featured image" phrase', 'senna-finance'),
            'remove_featured_image' => _x('Remove firm logo', 'Overrides the "Remove featured image" phrase', 'senna-finance'),
            'use_featured_image'    => _x('Use as firm logo', 'Overrides the "Use as featured image" phrase', 'senna-finance'),
            'archives'              => _x('PE Firm archives', 'The post type archive label', 'senna-finance'),
            'insert_into_item'      => _x('Insert into PE firm', 'Overrides the "Insert into post" phrase', 'senna-finance'),
            'uploaded_to_this_item' => _x('Uploaded to this PE firm', 'Overrides the "Uploaded to this post" phrase', 'senna-finance'),
            'filter_items_list'     => _x('Filter PE firms list', 'Screen reader text', 'senna-finance'),
            'items_list_navigation' => _x('PE firms list navigation', 'Screen reader text', 'senna-finance'),
            'items_list'            => _x('PE firms list', 'Screen reader text', 'senna-finance'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => 'sffc-dashboard', // Add to senna menu
            'show_in_admin_bar'  => true,
            'show_in_nav_menus'  => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'firm'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-building',
            'supports'           => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'),
            'map_meta_cap'       => true,
            'show_in_rest'       => true, // Enable Gutenberg editor
            'rest_base'          => 'firm',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        );

        register_post_type('sffc_company', $args);

        // Flush rewrite rules if the post type was just registered
        if (!get_option('sffc_company_post_type_registered')) {
            flush_rewrite_rules();
            update_option('sffc_company_post_type_registered', true);
        }
    }

    /**
     * Ensure database tables exist
     */
    private function ensure_tables_exist()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Company news relationships table
        $table_news = $wpdb->prefix . 'sffc_company_news_links';
        $sql_news = "CREATE TABLE IF NOT EXISTS $table_news (
            id int(11) NOT NULL AUTO_INCREMENT,
            company_id int(11) NOT NULL,
            news_item_id int(11) NOT NULL,
            relevance_score float DEFAULT 0,
            matched_terms text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY company_id (company_id),
            KEY news_item_id (news_item_id),
            KEY relevance_score (relevance_score)
        ) $charset_collate;";

        // Company metrics table
        $table_metrics = $wpdb->prefix . 'sffc_company_metrics';
        $sql_metrics = "CREATE TABLE IF NOT EXISTS $table_metrics (
            id int(11) NOT NULL AUTO_INCREMENT,
            company_id int(11) NOT NULL,
            metric_type varchar(50) NOT NULL,
            metric_value text,
            metric_date date,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY company_id (company_id),
            KEY metric_type (metric_type),
            KEY metric_date (metric_date)
        ) $charset_collate;";

        // Deal tracking table
        $table_deals = $wpdb->prefix . 'sffc_deal_tracking';
        $sql_deals = "CREATE TABLE IF NOT EXISTS $table_deals (
            id int(11) NOT NULL AUTO_INCREMENT,
            company_id int(11) NOT NULL,
            deal_type varchar(50),
            deal_size varchar(50),
            deal_date date,
            target_company varchar(255),
            sector varchar(100),
            region varchar(50),
            status varchar(50),
            details text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY company_id (company_id),
            KEY deal_date (deal_date),
            KEY sector (sector),
            KEY region (region)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_news);
        dbDelta($sql_metrics);
        dbDelta($sql_deals);
    }

    /**
     * Enhance company post type with PE-specific fields
     */
    public function enhance_company_post_type()
    {
        // Add custom meta boxes for company profiles
        add_action('add_meta_boxes', array($this, 'add_company_meta_boxes'));
        add_action('save_post_sffc_company', array($this, 'save_company_meta'));

        // Add custom columns to admin list
        add_filter('manage_sffc_company_posts_columns', array($this, 'add_company_columns'));
        add_action('manage_sffc_company_posts_custom_column', array($this, 'populate_company_columns'), 10, 2);
        add_filter('manage_edit-sffc_company_sortable_columns', array($this, 'make_company_columns_sortable'));
    }

    /**
     * Add custom columns to PE Firms admin list
     */
    public function add_company_columns($columns)
    {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['aum'] = __('AUM', 'senna-finance');
        $new_columns['headquarters'] = __('Headquarters', 'senna-finance');
        $new_columns['sectors'] = __('Sectors', 'senna-finance');
        $new_columns['news_count'] = __('News (7d)', 'senna-finance');
        $new_columns['deals_count'] = __('Active Deals', 'senna-finance');
        $new_columns['date'] = $columns['date'];

        return $new_columns;
    }

    /**
     * Populate custom columns in PE Firms admin list
     */
    public function populate_company_columns($column, $post_id)
    {
        switch ($column) {
            case 'aum':
                $aum = get_post_meta($post_id, '_sffc_aum', true);
                if ($aum) {
                    $billions = round($aum / 1000000000, 1);
                    echo '$' . $billions . 'B';
                } else {
                    echo '—';
                }
                break;

            case 'headquarters':
                $hq = get_post_meta($post_id, '_sffc_headquarters', true);
                echo $hq ?: '—';
                break;

            case 'sectors':
                $sectors = get_post_meta($post_id, '_sffc_sectors', true);
                if ($sectors) {
                    $sector_array = explode(',', $sectors);
                    echo implode(', ', array_slice($sector_array, 0, 2));
                    if (count($sector_array) > 2) {
                        echo ' +' . (count($sector_array) - 2) . ' more';
                    }
                } else {
                    echo '—';
                }
                break;

            case 'news_count':
                $news_week = get_post_meta($post_id, '_sffc_news_count_week', true);
                echo $news_week ?: '0';
                break;

            case 'deals_count':
                $active_deals = get_post_meta($post_id, '_sffc_active_deals', true);
                echo $active_deals ?: '0';
                break;
        }
    }

    /**
     * Make columns sortable
     */
    public function make_company_columns_sortable($columns)
    {
        $columns['aum'] = 'aum';
        $columns['headquarters'] = 'headquarters';
        return $columns;
    }

    /**
     * Add company meta boxes
     */
    public function add_company_meta_boxes()
    {
        add_meta_box(
            'sffc_company_intelligence',
            'Company Intelligence Data',
            array($this, 'render_company_meta_box'),
            'sffc_company',
            'normal',
            'high'
        );
    }

    /**
     * Render company meta box
     */
    public function render_company_meta_box($post)
    {
        wp_nonce_field('sffc_company_meta', 'sffc_company_meta_nonce');

        // Get existing values
        $aum = get_post_meta($post->ID, '_sffc_aum', true);
        $founded = get_post_meta($post->ID, '_sffc_founded', true);
        $headquarters = get_post_meta($post->ID, '_sffc_headquarters', true);
        $regions = get_post_meta($post->ID, '_sffc_regions', true);
        $sectors = get_post_meta($post->ID, '_sffc_sectors', true);
        $portfolio_companies = get_post_meta($post->ID, '_sffc_portfolio_companies', true);
?>
        <div class="sffc-company-meta">
            <p>
                <label for="sffc_aum">AUM (in USD):</label>
                <input type="text" id="sffc_aum" name="sffc_aum" value="<?php echo esc_attr($aum); ?>" class="widefat" />
            </p>
            <p>
                <label for="sffc_founded">Founded Year:</label>
                <input type="number" id="sffc_founded" name="sffc_founded" value="<?php echo esc_attr($founded); ?>" />
            </p>
            <p>
                <label for="sffc_headquarters">Headquarters:</label>
                <input type="text" id="sffc_headquarters" name="sffc_headquarters" value="<?php echo esc_attr($headquarters); ?>" class="widefat" />
            </p>
            <p>
                <label for="sffc_regions">Regions (comma-separated):</label>
                <input type="text" id="sffc_regions" name="sffc_regions" value="<?php echo esc_attr($regions); ?>" class="widefat" />
            </p>
            <p>
                <label for="sffc_sectors">Sectors (comma-separated):</label>
                <textarea id="sffc_sectors" name="sffc_sectors" class="widefat"><?php echo esc_textarea($sectors); ?></textarea>
            </p>
            <p>
                <label for="sffc_portfolio_companies">Portfolio Companies Count:</label>
                <input type="number" id="sffc_portfolio_companies" name="sffc_portfolio_companies" value="<?php echo esc_attr($portfolio_companies); ?>" />
            </p>
        </div>
<?php
    }

    /**
     * Save company meta data
     */
    public function save_company_meta($post_id)
    {
        if (
            !isset($_POST['sffc_company_meta_nonce']) ||
            !wp_verify_nonce($_POST['sffc_company_meta_nonce'], 'sffc_company_meta')
        ) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save meta fields
        $fields = array('aum', 'founded', 'headquarters', 'regions', 'sectors', 'portfolio_companies');
        foreach ($fields as $field) {
            if (isset($_POST['sffc_' . $field])) {
                update_post_meta($post_id, '_sffc_' . $field, sanitize_text_field($_POST['sffc_' . $field]));
            }
        }
    }

    /**
     * Link news item to company based on content analysis
     */
    public function link_news_to_company($news_item, $source)
    {
        if (!isset($news_item['title']) || !isset($news_item['description'])) {
            return $news_item;
        }

        $content = strtolower($news_item['title'] . ' ' . $news_item['description']);
        $matched_companies = array();

        // Check each PE firm
        foreach ($this->top_firms as $firm_slug => $firm_data) {
            $relevance_score = 0;
            $matched_terms = array();

            // Check main name
            if (stripos($content, strtolower($firm_data['name'])) !== false) {
                $relevance_score += 10;
                $matched_terms[] = $firm_data['name'];
            }

            // Check aliases
            if (!empty($firm_data['aliases'])) {
                foreach ($firm_data['aliases'] as $alias) {
                    if (stripos($content, strtolower($alias)) !== false) {
                        $relevance_score += 8;
                        $matched_terms[] = $alias;
                    }
                }
            }

            if ($relevance_score > 0) {
                $matched_companies[] = array(
                    'firm_slug' => $firm_slug,
                    'firm_name' => $firm_data['name'],
                    'relevance_score' => $relevance_score,
                    'matched_terms' => $matched_terms
                );
            }
        }

        if (!empty($matched_companies)) {
            $news_item['matched_companies'] = $matched_companies;
        }

        return $news_item;
    }

    /**
     * Process news relationships after saving
     */
    public function process_news_relationships($news_id, $news_data)
    {
        if (!isset($news_data['matched_companies']) || empty($news_data['matched_companies'])) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sffc_company_news_links';

        foreach ($news_data['matched_companies'] as $company_match) {
            // Get or create company post
            $company_id = $this->get_or_create_company_post($company_match['firm_slug']);

            if ($company_id) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'company_id' => $company_id,
                        'news_item_id' => $news_id,
                        'relevance_score' => $company_match['relevance_score'],
                        'matched_terms' => json_encode($company_match['matched_terms'])
                    ),
                    array('%d', '%d', '%f', '%s')
                );
            }
        }
    }

    /**
     * Get or create company post
     */
    private function get_or_create_company_post($firm_slug)
    {
        // Check if company post exists
        $args = array(
            'post_type' => 'sffc_company',
            'meta_key' => '_sffc_firm_slug',
            'meta_value' => $firm_slug,
            'posts_per_page' => 1
        );

        $posts = get_posts($args);

        if (!empty($posts)) {
            return $posts[0]->ID;
        }

        // Create new company post
        if (!isset($this->top_firms[$firm_slug])) {
            return false;
        }

        $firm_data = $this->top_firms[$firm_slug];

        $post_args = array(
            'post_title' => class_exists('SFFC_Company_Title_Helper')
                ? SFFC_Company_Title_Helper::build_seo_title($firm_data['name'])
                : $firm_data['name'],
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => 'sffc_company'
        );

        if (class_exists('SFFC_Company_Title_Helper')) {
            $post_args['post_name'] = sanitize_title($firm_data['name']);
        }

        $post_id = wp_insert_post($post_args);

        if ($post_id && !is_wp_error($post_id)) {
            // Save meta data
            update_post_meta($post_id, '_sffc_firm_slug', $firm_slug);
            update_post_meta($post_id, '_sffc_aum', $firm_data['aum']);
            update_post_meta($post_id, '_sffc_founded', $firm_data['founded']);
            update_post_meta($post_id, '_sffc_headquarters', $firm_data['headquarters']);
            update_post_meta($post_id, '_sffc_regions', implode(', ', $firm_data['regions']));
            update_post_meta($post_id, '_sffc_sectors', implode(', ', $firm_data['sectors']));

            if (class_exists('SFFC_Company_Title_Helper')) {
                SFFC_Company_Title_Helper::ensure_canonical_meta($post_id, $firm_data['name']);
            }

            return $post_id;
        }

        return false;
    }

    /**
     * Get company intelligence data
     */
    public function get_company_intelligence($company_id)
    {
        global $wpdb;

        $intelligence = array(
            'profile' => $this->get_company_profile($company_id),
            'recent_news' => $this->get_company_news($company_id, 10),
            'deals' => $this->get_company_deals($company_id),
            'metrics' => $this->get_company_metrics($company_id),
            'jobs' => $this->get_company_jobs($company_id)
        );

        return $intelligence;
    }

    /**
     * Get company profile
     */
    private function get_company_profile($company_id)
    {
        $profile = array(
            'name' => get_the_title($company_id),
            'aum' => get_post_meta($company_id, '_sffc_aum', true),
            'founded' => get_post_meta($company_id, '_sffc_founded', true),
            'headquarters' => get_post_meta($company_id, '_sffc_headquarters', true),
            'regions' => explode(', ', get_post_meta($company_id, '_sffc_regions', true)),
            'sectors' => explode(', ', get_post_meta($company_id, '_sffc_sectors', true)),
            'portfolio_companies' => get_post_meta($company_id, '_sffc_portfolio_companies', true)
        );

        return $profile;
    }

    /**
     * Get company news
     */
    private function get_company_news($company_id, $limit = 10)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sffc_company_news_links';

        $news_items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE company_id = %d 
            ORDER BY created_at DESC 
            LIMIT %d",
            $company_id,
            $limit
        ));

        return $news_items;
    }

    /**
     * Get company deals
     */
    private function get_company_deals($company_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sffc_deal_tracking';

        $deals = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE company_id = %d 
            ORDER BY deal_date DESC",
            $company_id
        ));

        return $deals;
    }

    /**
     * Get company metrics
     */
    private function get_company_metrics($company_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sffc_company_metrics';

        $metrics = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE company_id = %d 
            ORDER BY metric_date DESC",
            $company_id
        ));

        return $metrics;
    }

    /**
     * Get company jobs
     */
    private function get_company_jobs($company_id)
    {
        $company_name = get_the_title($company_id);

        $args = array(
            'post_type' => 'sffc_job',
            'meta_query' => array(
                array(
                    'key' => 'company_name',
                    'value' => $company_name,
                    'compare' => 'LIKE'
                )
            ),
            'posts_per_page' => 10
        );

        return get_posts($args);
    }

    /**
     * AJAX handler for getting company intelligence
     */
    public function ajax_get_company_intelligence()
    {
        $company_id = isset($_POST['company_id']) ? intval($_POST['company_id']) : 0;

        if (!$company_id) {
            wp_send_json_error('Invalid company ID');
        }

        $intelligence = $this->get_company_intelligence($company_id);

        wp_send_json_success($intelligence);
    }

    /**
     * Update company metrics (cron job)
     */
    public function update_company_metrics()
    {
        // Get all companies
        $companies = get_posts(array(
            'post_type' => 'sffc_company',
            'posts_per_page' => -1
        ));

        foreach ($companies as $company) {
            // Update news count
            $news_count = $this->get_company_news_count($company->ID);
            update_post_meta($company->ID, '_sffc_news_count_today', $news_count['today']);
            update_post_meta($company->ID, '_sffc_news_count_week', $news_count['week']);

            // Update deal count
            $deal_count = $this->get_company_deal_count($company->ID);
            update_post_meta($company->ID, '_sffc_active_deals', $deal_count);
        }
    }

    /**
     * Get company news count
     */
    private function get_company_news_count($company_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sffc_company_news_links';

        $today = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
            WHERE company_id = %d 
            AND DATE(created_at) = CURDATE()",
            $company_id
        ));

        $week = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
            WHERE company_id = %d 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $company_id
        ));

        return array('today' => $today, 'week' => $week);
    }

    /**
     * Get company deal count
     */
    private function get_company_deal_count($company_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sffc_deal_tracking';

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
            WHERE company_id = %d 
            AND status = 'active'",
            $company_id
        ));
    }

    /**
     * Get top companies for display
     */
    public function get_top_companies($limit = 12)
    {
        $companies = get_posts(array(
            'post_type' => 'sffc_company',
            'posts_per_page' => $limit,
            'meta_key' => '_sffc_aum',
            'orderby' => 'meta_value_num',
            'order' => 'DESC'
        ));

        $company_data = array();

        foreach ($companies as $company) {
            $display_name = $company->post_title;
            $canonical_name = class_exists('SFFC_Company_Title_Helper')
                ? SFFC_Company_Title_Helper::get_canonical_name($company)
                : $display_name;

            $company_data[] = array(
                'id' => $company->ID,
                'name' => $display_name,
                'canonical_name' => $canonical_name,
                'slug' => $company->post_name,
                'aum' => get_post_meta($company->ID, '_sffc_aum', true),
                'portfolio_companies' => get_post_meta($company->ID, '_sffc_portfolio_companies', true),
                'news_today' => get_post_meta($company->ID, '_sffc_news_count_today', true),
                'active_deals' => get_post_meta($company->ID, '_sffc_active_deals', true)
            );
        }

        return $company_data;
    }

    /**
     * Get all firms data for extraction
     */
    public function get_all_firms()
    {
        return $this->top_firms;
    }
}

// Initialize
SFFC_Company_Intelligence_Engine::get_instance();
