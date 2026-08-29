<?php
/**
 * PE Data Generator
 * Generates sample data for testing the PE Intelligence Platform
 * 
 * @package SennaCareers
 * @since 10.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_PE_Data_Generator {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Sample PE firms
     */
    private $firms = array(
        'KKR', 'Blackstone', 'Apollo', 'Carlyle', 'TPG', 
        'Warburg Pincus', 'Bain Capital', 'Advent International'
    );
    
    /**
     * Sample sectors
     */
    private $sectors = array(
        'Technology', 'Healthcare', 'Financial Services', 
        'Consumer', 'Energy', 'Real Estate', 'Infrastructure'
    );
    
    /**
     * Sample regions
     */
    private $regions = array('UK', 'EU', 'US', 'APAC', 'private equity');
    
    /**
     * Get instance
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
        add_action('init', array($this, 'register_ajax_handlers'));
    }
    
    /**
     * Register AJAX handlers
     */
    public function register_ajax_handlers() {
        add_action('wp_ajax_sffc_generate_sample_data', array($this, 'ajax_generate_sample_data'));
        add_action('wp_ajax_nopriv_sffc_generate_sample_data', array($this, 'ajax_generate_sample_data'));
    }
    
    /**
     * Generate sample data via AJAX
     */
    public function ajax_generate_sample_data() {
        check_ajax_referer('sffc_intelligence_nonce', 'nonce');

        wp_send_json_error(array(
            'message' => 'Sample PE data generation is disabled. Use live content sources only.'
        ), 400);
    }
    
    /**
     * Generate sample companies
     */
    public function generate_sample_companies() {
        foreach ($this->firms as $firm) {
            if (class_exists('SFFC_Company_Title_Helper')) {
                $existing_posts = get_posts(array(
                    'post_type' => 'sffc_company',
                    'meta_key' => SFFC_Company_Title_Helper::META_CANONICAL_NAME,
                    'meta_value' => $firm,
                    'posts_per_page' => 1
                ));
                $existing = !empty($existing_posts) ? $existing_posts[0] : null;
            } else {
                $existing = get_page_by_title($firm, OBJECT, 'sffc_company');
            }

            if (!$existing) {
                $company_args = array(
                    'post_title' => class_exists('SFFC_Company_Title_Helper')
                        ? SFFC_Company_Title_Helper::build_seo_title($firm)
                        : $firm,
                    'post_type' => 'sffc_company',
                    'post_status' => 'publish',
                    'post_content' => $this->generate_company_description($firm)
                );

                if (class_exists('SFFC_Company_Title_Helper')) {
                    $company_args['post_name'] = sanitize_title($firm);
                }

                $company_id = wp_insert_post($company_args);

                if ($company_id) {
                    if (class_exists('SFFC_Company_Title_Helper')) {
                        SFFC_Company_Title_Helper::ensure_canonical_meta($company_id, $firm);
                    }
                    // Add meta data
                    update_post_meta($company_id, '_sffc_aum', rand(50, 1000) . '000000000');
                    update_post_meta($company_id, '_sffc_founded', rand(1970, 2000));
                    update_post_meta($company_id, '_sffc_headquarters', $this->get_random_city());
                    update_post_meta($company_id, '_sffc_portfolio_count', rand(50, 300));
                    update_post_meta($company_id, '_sffc_employees', rand(200, 2000));
                }
            }
        }
    }
    
    /**
     * Generate sample news
     */
    public function generate_sample_news() {
        $news_templates = array(
            '%s Completes £%dB Acquisition of %s',
            '%s Raises New £%dB Fund for %s Investments',
            '%s Appoints New Head of %s',
            '%s Portfolio Company %s Files for IPO',
            '%s Exits %s Investment with %dx Return',
            '%s Leading £%dM Investment Round in %s'
        );
        
        for ($i = 0; $i < 20; $i++) {
            $firm = $this->firms[array_rand($this->firms)];
            $template = $news_templates[array_rand($news_templates)];
            $sector = $this->sectors[array_rand($this->sectors)];
            $company_name = $this->generate_company_name();
            
            $title = sprintf($template, $firm, rand(1, 10), $sector);
            
            $post_id = wp_insert_post(array(
                'post_title' => $title,
                'post_type' => 'post',
                'post_status' => 'publish',
                'post_content' => $this->generate_news_content($firm, $sector),
                'post_category' => array($this->get_news_category_id())
            ));
            
            if ($post_id) {
                update_post_meta($post_id, '_news_source', $this->get_random_source());
                update_post_meta($post_id, '_news_region', $this->regions[array_rand($this->regions)]);
                
                // Link to company
                $company = get_page_by_title($firm, OBJECT, 'sffc_company');
                if ($company) {
                    global $wpdb;
                    $table = $wpdb->prefix . 'sffc_company_news_links';
                    $wpdb->insert($table, array(
                        'company_id' => $company->ID,
                        'news_item_id' => $post_id,
                        'relevance_score' => rand(70, 100),
                        'matched_terms' => json_encode(array($firm))
                    ));
                }
            }
        }
    }
    
    /**
     * Generate sample deals
     */
    public function generate_sample_deals() {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_deal_tracking';
        
        foreach ($this->firms as $firm) {
            $company = get_page_by_title($firm, OBJECT, 'sffc_company');
            if (!$company) continue;
            
            for ($i = 0; $i < rand(2, 5); $i++) {
                $wpdb->insert($table, array(
                    'company_id' => $company->ID,
                    'deal_type' => array('acquisition', 'exit', 'ipo', 'merger')[rand(0, 3)],
                    'deal_size' => rand(100, 5000) . '000000',
                    'deal_date' => date('Y-m-d', strtotime('-' . rand(1, 90) . ' days')),
                    'target_company' => $this->generate_company_name(),
                    'sector' => $this->sectors[array_rand($this->sectors)],
                    'region' => $this->regions[array_rand($this->regions)],
                    'status' => array('pending', 'active', 'completed')[rand(0, 2)],
                    'details' => $this->generate_deal_details()
                ));
            }
        }
    }
    
    /**
     * Generate sample jobs
     */
    public function generate_sample_jobs() {
        $job_titles = array(
            'Investment Associate - %s',
            'Principal - %s Investments',
            'Vice President - Portfolio Operations',
            'Director - %s Sector',
            'Senior Analyst - Due Diligence',
            'Managing Director - %s'
        );
        
        $locations = array(
            'London', 'New York', 'Hong Kong', 'Singapore', 
            'Frankfurt', 'Paris', 'Dubai', 'Tokyo'
        );
        
        for ($i = 0; $i < 15; $i++) {
            $firm = $this->firms[array_rand($this->firms)];
            $sector = $this->sectors[array_rand($this->sectors)];
            $title = sprintf($job_titles[array_rand($job_titles)], $sector);
            
            $job_id = wp_insert_post(array(
                'post_title' => $title,
                'post_type' => 'sffc_job',
                'post_status' => 'publish',
                'post_content' => $this->generate_job_description()
            ));
            
            if ($job_id) {
                update_post_meta($job_id, 'company_name', $firm);
                update_post_meta($job_id, 'location', $locations[array_rand($locations)]);
                update_post_meta($job_id, 'salary_range', '£' . rand(80, 400) . ',000 - £' . rand(150, 600) . ',000');
                update_post_meta($job_id, 'experience_required', rand(3, 15) . '+ years');
                update_post_meta($job_id, 'sector', $sector);
            }
        }
    }
    
    /**
     * Generate company description
     */
    private function generate_company_description($firm) {
        return sprintf(
            '%s is a leading global investment firm that manages capital for a diverse group of investors. ' .
            'The firm seeks to create value through investment in companies across multiple industries and sectors. ' .
            'With a strong track record of successful investments and exits, %s continues to be a major player in the private equity landscape.',
            $firm, $firm
        );
    }
    
    /**
     * Generate news content
     */
    private function generate_news_content($firm, $sector) {
        return sprintf(
            'In a significant move for the %s sector, %s has announced a major transaction that underscores ' .
            'the continued strength of private equity investments. The deal represents a strategic expansion ' .
            'of the firm\'s portfolio and demonstrates confidence in market opportunities. ' .
            'Industry analysts view this as a positive signal for continued PE activity in the sector.',
            $sector, $firm
        );
    }
    
    /**
     * Generate job description
     */
    private function generate_job_description() {
        return 'We are seeking an exceptional candidate to join our growing team. The ideal candidate will have ' .
               'strong analytical skills, proven deal experience, and the ability to work in a fast-paced environment. ' .
               'This role offers significant growth potential and the opportunity to work on high-profile transactions.';
    }
    
    /**
     * Generate deal details
     */
    private function generate_deal_details() {
        return 'Strategic transaction aimed at expanding market presence and creating significant value for stakeholders. ' .
               'The deal leverages operational improvements and growth initiatives to drive returns.';
    }
    
    /**
     * Generate company name
     */
    private function generate_company_name() {
        $prefixes = array('Global', 'Premier', 'Strategic', 'Dynamic', 'Innovative');
        $suffixes = array('Solutions', 'Technologies', 'Group', 'Partners', 'Holdings', 'Ventures');
        
        return $prefixes[array_rand($prefixes)] . ' ' . $suffixes[array_rand($suffixes)];
    }
    
    /**
     * Get random city
     */
    private function get_random_city() {
        $cities = array('London', 'New York', 'Hong Kong', 'Singapore', 'Frankfurt', 'Paris');
        return $cities[array_rand($cities)];
    }
    
    /**
     * Get random source
     */
    private function get_random_source() {
        $sources = array('Bloomberg', 'Financial Times', 'WSJ', 'Reuters', 'PE Hub');
        return $sources[array_rand($sources)];
    }
    
    /**
     * Get news category ID
     */
    private function get_news_category_id() {
        $category = get_category_by_slug('market-news');
        
        if (!$category) {
            $cat_id = wp_create_category('Market News');
            return $cat_id;
        }
        
        return $category->term_id;
    }
}

// Initialize
SFFC_PE_Data_Generator::get_instance();
