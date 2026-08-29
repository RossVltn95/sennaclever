<?php
/**
 * Dynamic Memo Generator
 *
 * Generates topic-aware memo sections for the left panel
 * based on article classification and content type.
 *
 * @package SennaFinanceCareer
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Memo_Generator {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Memo section templates by article type
     */
    private $section_templates = array();

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_section_templates();
    }

    /**
     * Initialize section templates for different article types
     */
    private function init_section_templates() {
        // M&A / Deal Articles
        $this->section_templates['deal'] = array(
            array(
                'key' => 'executive_summary',
                'title' => 'Executive Summary',
                'icon' => 'file-text',
                'priority' => 10,
            ),
            array(
                'key' => 'transaction_overview',
                'title' => 'Transaction Overview',
                'icon' => 'briefcase',
                'priority' => 20,
            ),
            array(
                'key' => 'strategic_rationale',
                'title' => 'Strategic Rationale',
                'icon' => 'target',
                'priority' => 30,
            ),
            array(
                'key' => 'valuation_analysis',
                'title' => 'Valuation Analysis',
                'icon' => 'trending-up',
                'priority' => 40,
            ),
            array(
                'key' => 'deal_structure',
                'title' => 'Deal Structure & Financing',
                'icon' => 'layers',
                'priority' => 50,
            ),
            array(
                'key' => 'risk_factors',
                'title' => 'Risk Factors',
                'icon' => 'alert-triangle',
                'priority' => 60,
            ),
            array(
                'key' => 'market_context',
                'title' => 'Market Context',
                'icon' => 'globe',
                'priority' => 70,
            ),
            array(
                'key' => 'comparable_transactions',
                'title' => 'Comparable Transactions',
                'icon' => 'grid',
                'priority' => 80,
            ),
        );

        // Corporate News (lease, expansion, etc.)
        $this->section_templates['corporate'] = array(
            array(
                'key' => 'executive_summary',
                'title' => 'Executive Summary',
                'icon' => 'file-text',
                'priority' => 10,
            ),
            array(
                'key' => 'announcement_details',
                'title' => 'Announcement Details',
                'icon' => 'info',
                'priority' => 20,
            ),
            array(
                'key' => 'company_context',
                'title' => 'Company Context',
                'icon' => 'building',
                'priority' => 30,
            ),
            array(
                'key' => 'market_implications',
                'title' => 'Market Implications',
                'icon' => 'trending-up',
                'priority' => 40,
            ),
            array(
                'key' => 'sector_benchmarks',
                'title' => 'Sector Benchmarks',
                'icon' => 'bar-chart-2',
                'priority' => 50,
            ),
            array(
                'key' => 'strategic_analysis',
                'title' => 'Strategic Analysis',
                'icon' => 'compass',
                'priority' => 60,
            ),
        );

        // Earnings / Results
        $this->section_templates['earnings'] = array(
            array(
                'key' => 'executive_summary',
                'title' => 'Executive Summary',
                'icon' => 'file-text',
                'priority' => 10,
            ),
            array(
                'key' => 'performance_highlights',
                'title' => 'Performance Highlights',
                'icon' => 'award',
                'priority' => 20,
            ),
            array(
                'key' => 'segment_analysis',
                'title' => 'Segment Analysis',
                'icon' => 'pie-chart',
                'priority' => 30,
            ),
            array(
                'key' => 'guidance_outlook',
                'title' => 'Guidance & Outlook',
                'icon' => 'calendar',
                'priority' => 40,
            ),
            array(
                'key' => 'peer_comparison',
                'title' => 'Peer Comparison',
                'icon' => 'users',
                'priority' => 50,
            ),
            array(
                'key' => 'valuation_impact',
                'title' => 'Valuation Impact',
                'icon' => 'dollar-sign',
                'priority' => 60,
            ),
        );

        // People / Leadership
        $this->section_templates['leadership'] = array(
            array(
                'key' => 'executive_summary',
                'title' => 'Executive Summary',
                'icon' => 'file-text',
                'priority' => 10,
            ),
            array(
                'key' => 'appointment_details',
                'title' => 'Appointment Details',
                'icon' => 'user-plus',
                'priority' => 20,
            ),
            array(
                'key' => 'background_profile',
                'title' => 'Background Profile',
                'icon' => 'user',
                'priority' => 30,
            ),
            array(
                'key' => 'strategic_implications',
                'title' => 'Strategic Implications',
                'icon' => 'compass',
                'priority' => 40,
            ),
            array(
                'key' => 'compensation_context',
                'title' => 'Compensation Context',
                'icon' => 'dollar-sign',
                'priority' => 50,
            ),
        );

        // Real Estate
        $this->section_templates['real_estate'] = array(
            array(
                'key' => 'executive_summary',
                'title' => 'Executive Summary',
                'icon' => 'file-text',
                'priority' => 10,
            ),
            array(
                'key' => 'property_details',
                'title' => 'Property Details',
                'icon' => 'home',
                'priority' => 20,
            ),
            array(
                'key' => 'lease_terms',
                'title' => 'Lease Terms & Structure',
                'icon' => 'file',
                'priority' => 30,
            ),
            array(
                'key' => 'market_analysis',
                'title' => 'Market Analysis',
                'icon' => 'map-pin',
                'priority' => 40,
            ),
            array(
                'key' => 'financial_impact',
                'title' => 'Financial Impact',
                'icon' => 'dollar-sign',
                'priority' => 50,
            ),
            array(
                'key' => 'comparable_properties',
                'title' => 'Comparable Properties',
                'icon' => 'grid',
                'priority' => 60,
            ),
        );

        // Fundraising / Capital Markets
        $this->section_templates['fundraising'] = array(
            array(
                'key' => 'executive_summary',
                'title' => 'Executive Summary',
                'icon' => 'file-text',
                'priority' => 10,
            ),
            array(
                'key' => 'fund_details',
                'title' => 'Fund Details',
                'icon' => 'briefcase',
                'priority' => 20,
            ),
            array(
                'key' => 'investment_strategy',
                'title' => 'Investment Strategy',
                'icon' => 'target',
                'priority' => 30,
            ),
            array(
                'key' => 'track_record',
                'title' => 'Track Record',
                'icon' => 'trending-up',
                'priority' => 40,
            ),
            array(
                'key' => 'market_context',
                'title' => 'Market Context',
                'icon' => 'globe',
                'priority' => 50,
            ),
            array(
                'key' => 'lp_considerations',
                'title' => 'LP Considerations',
                'icon' => 'users',
                'priority' => 60,
            ),
        );

        // IPO / Public Offerings
        $this->section_templates['ipo'] = array(
            array(
                'key' => 'executive_summary',
                'title' => 'Executive Summary',
                'icon' => 'file-text',
                'priority' => 10,
            ),
            array(
                'key' => 'offering_details',
                'title' => 'Offering Details',
                'icon' => 'package',
                'priority' => 20,
            ),
            array(
                'key' => 'company_overview',
                'title' => 'Company Overview',
                'icon' => 'building',
                'priority' => 30,
            ),
            array(
                'key' => 'financial_highlights',
                'title' => 'Financial Highlights',
                'icon' => 'bar-chart-2',
                'priority' => 40,
            ),
            array(
                'key' => 'valuation_analysis',
                'title' => 'Valuation Analysis',
                'icon' => 'trending-up',
                'priority' => 50,
            ),
            array(
                'key' => 'risk_factors',
                'title' => 'Risk Factors',
                'icon' => 'alert-triangle',
                'priority' => 60,
            ),
            array(
                'key' => 'use_of_proceeds',
                'title' => 'Use of Proceeds',
                'icon' => 'dollar-sign',
                'priority' => 70,
            ),
        );

        // Macro / Economic News
        $this->section_templates['macro'] = array(
            array(
                'key' => 'executive_summary',
                'title' => 'Executive Summary',
                'icon' => 'file-text',
                'priority' => 10,
            ),
            array(
                'key' => 'key_data_points',
                'title' => 'Key Data Points',
                'icon' => 'activity',
                'priority' => 20,
            ),
            array(
                'key' => 'market_reaction',
                'title' => 'Market Reaction',
                'icon' => 'trending-up',
                'priority' => 30,
            ),
            array(
                'key' => 'sector_impact',
                'title' => 'Sector Impact',
                'icon' => 'layers',
                'priority' => 40,
            ),
            array(
                'key' => 'policy_implications',
                'title' => 'Policy Implications',
                'icon' => 'flag',
                'priority' => 50,
            ),
            array(
                'key' => 'outlook',
                'title' => 'Outlook',
                'icon' => 'eye',
                'priority' => 60,
            ),
        );

        // Default / Generic
        $this->section_templates['default'] = array(
            array(
                'key' => 'executive_summary',
                'title' => 'Executive Summary',
                'icon' => 'file-text',
                'priority' => 10,
            ),
            array(
                'key' => 'key_details',
                'title' => 'Key Details',
                'icon' => 'info',
                'priority' => 20,
            ),
            array(
                'key' => 'analysis',
                'title' => 'Analysis',
                'icon' => 'bar-chart-2',
                'priority' => 30,
            ),
            array(
                'key' => 'implications',
                'title' => 'Implications',
                'icon' => 'compass',
                'priority' => 40,
            ),
        );
    }

    /**
     * Get memo sections for an article
     *
     * @param int $post_id The post ID
     * @return array Array of memo sections
     */
    public function get_memo_sections($post_id) {
        // Get classification data
        $classification = get_post_meta($post_id, '_sffc_article_classification', true);

        if (empty($classification)) {
            // Try to classify if not already done
            if (class_exists('SFFC_Article_Classifier')) {
                $classifier = SFFC_Article_Classifier::get_instance();
                $post = get_post($post_id);
                if ($post) {
                    $classification = $classifier->classify($post_id, $post->post_title, $post->post_content);
                }
            }
        }

        // Determine article type from classification
        $article_type = $this->determine_article_type($classification);

        // Get section template
        $template = $this->section_templates[$article_type] ?? $this->section_templates['default'];

        // Get existing sections content
        $existing_sections = get_post_meta($post_id, '_article_sections', true);
        $existing_sections = !empty($existing_sections) ? json_decode($existing_sections, true) : array();

        // Map existing content to template sections
        $sections = $this->map_content_to_sections($template, $existing_sections, $classification);

        return array(
            'type' => $article_type,
            'sections' => $sections,
            'classification' => $classification,
        );
    }

    /**
     * Determine article type from classification
     *
     * @param array $classification Classification data
     * @return string Article type key
     */
    private function determine_article_type($classification) {
        if (empty($classification)) {
            return 'default';
        }

        $category = $classification['category'] ?? '';
        $deal_type = $classification['deal_type'] ?? '';
        $sector = $classification['sector'] ?? '';

        // Check for deal articles
        if (in_array($category, array('m_and_a', 'private_equity'))) {
            if (in_array($deal_type, array('acquisition', 'merger', 'buyout', 'take_private', 'carve_out'))) {
                return 'deal';
            }
            if ($deal_type === 'fundraise') {
                return 'fundraising';
            }
            if ($deal_type === 'ipo') {
                return 'ipo';
            }
        }

        // Check for real estate
        if ($category === 'real_estate' || $sector === 'real_estate') {
            return 'real_estate';
        }

        // Check for earnings
        if ($category === 'corporate_news') {
            // Look for earnings-related keywords in deal_type
            if (strpos($deal_type, 'earnings') !== false || strpos($deal_type, 'results') !== false) {
                return 'earnings';
            }
            return 'corporate';
        }

        // Check for leadership
        if ($category === 'people_leadership' || $deal_type === 'executive_appointment') {
            return 'leadership';
        }

        // Check for capital markets
        if ($category === 'capital_markets') {
            if ($deal_type === 'ipo' || $deal_type === 'spac') {
                return 'ipo';
            }
            if ($deal_type === 'fundraise') {
                return 'fundraising';
            }
        }

        // Check for macro
        if ($category === 'macro_markets') {
            return 'macro';
        }

        return 'default';
    }

    /**
     * Map existing content to template sections
     *
     * @param array $template Section template
     * @param array $existing Existing sections content
     * @param array $classification Classification data
     * @return array Mapped sections
     */
    private function map_content_to_sections($template, $existing, $classification) {
        $sections = array();

        foreach ($template as $section) {
            $section_data = array(
                'key' => $section['key'],
                'title' => $section['title'],
                'icon' => $section['icon'],
                'priority' => $section['priority'],
                'content' => '',
                'has_content' => false,
            );

            // Try to find matching content in existing sections
            foreach ($existing as $existing_section) {
                $existing_title = strtolower($existing_section['title'] ?? '');
                $section_key = strtolower(str_replace('_', ' ', $section['key']));

                // Check for title match or key match
                if (strpos($existing_title, $section_key) !== false ||
                    $this->section_matches($existing_title, $section['key'])) {
                    $section_data['content'] = $existing_section['content'] ?? '';
                    $section_data['has_content'] = !empty($section_data['content']);
                    break;
                }
            }

            // If no match found, try to generate placeholder based on classification
            if (!$section_data['has_content']) {
                $section_data['placeholder'] = $this->get_section_placeholder($section['key'], $classification);
            }

            $sections[] = $section_data;
        }

        // Sort by priority
        usort($sections, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });

        return $sections;
    }

    /**
     * Check if section title matches a section key
     *
     * @param string $title Section title
     * @param string $key Section key
     * @return bool Whether they match
     */
    private function section_matches($title, $key) {
        $key_variations = array(
            'executive_summary' => array('summary', 'overview', 'executive'),
            'transaction_overview' => array('transaction', 'deal overview', 'deal details'),
            'strategic_rationale' => array('strategic', 'rationale', 'strategy'),
            'valuation_analysis' => array('valuation', 'value', 'pricing'),
            'deal_structure' => array('structure', 'financing', 'terms'),
            'risk_factors' => array('risk', 'risks', 'concerns'),
            'market_context' => array('market', 'context', 'environment'),
            'comparable_transactions' => array('comparable', 'precedent', 'similar deals'),
            'announcement_details' => array('announcement', 'news', 'details'),
            'company_context' => array('company', 'background', 'about'),
            'market_implications' => array('implications', 'impact'),
            'sector_benchmarks' => array('benchmark', 'sector', 'industry'),
            'performance_highlights' => array('performance', 'highlights', 'results'),
            'segment_analysis' => array('segment', 'division', 'business unit'),
            'guidance_outlook' => array('guidance', 'outlook', 'forecast'),
            'property_details' => array('property', 'asset', 'building'),
            'lease_terms' => array('lease', 'terms', 'rental'),
            'fund_details' => array('fund', 'vehicle', 'strategy'),
            'offering_details' => array('offering', 'ipo', 'listing'),
        );

        $variations = $key_variations[$key] ?? array();

        foreach ($variations as $variation) {
            if (strpos($title, $variation) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get placeholder text for a section
     *
     * @param string $key Section key
     * @param array $classification Classification data
     * @return string Placeholder text
     */
    private function get_section_placeholder($key, $classification) {
        $company = $classification['entities']['companies'][0]['name'] ?? 'the company';
        $sector = ucfirst(str_replace('_', ' ', $classification['sector'] ?? 'the sector'));

        $placeholders = array(
            'executive_summary' => "This section provides a high-level overview of the key developments and their significance.",
            'transaction_overview' => "Details of the transaction including parties involved, deal value, and structure.",
            'strategic_rationale' => "Analysis of the strategic drivers behind this development.",
            'valuation_analysis' => "Examination of the valuation metrics and how they compare to market benchmarks.",
            'deal_structure' => "Breakdown of the financing structure and key deal terms.",
            'risk_factors' => "Key risks and considerations that stakeholders should be aware of.",
            'market_context' => "How this development fits within the broader {$sector} landscape.",
            'comparable_transactions' => "Similar transactions and precedent deals for context.",
            'announcement_details' => "Full details of the announcement and its immediate implications.",
            'company_context' => "Background on {$company} and relevant company history.",
            'market_implications' => "Potential impact on the market and competitive dynamics.",
            'sector_benchmarks' => "How key metrics compare to {$sector} benchmarks.",
            'performance_highlights' => "Key performance metrics and notable achievements.",
            'segment_analysis' => "Breakdown by business segment or geographic region.",
            'guidance_outlook' => "Forward-looking statements and management guidance.",
            'property_details' => "Specifications and characteristics of the property.",
            'lease_terms' => "Key lease terms, duration, and financial arrangements.",
            'fund_details' => "Fund size, strategy, and key characteristics.",
            'offering_details' => "Details of the offering including size and pricing.",
        );

        return $placeholders[$key] ?? "Additional analysis and context for this section.";
    }

    /**
     * Get section template for a specific article type
     *
     * @param string $type Article type
     * @return array Section template
     */
    public function get_section_template($type) {
        return $this->section_templates[$type] ?? $this->section_templates['default'];
    }

    /**
     * Get all available article types
     *
     * @return array Article types
     */
    public function get_article_types() {
        return array_keys($this->section_templates);
    }

    /**
     * Generate memo HTML for display
     *
     * @param int $post_id The post ID
     * @return string HTML output
     */
    public function render_memo_sections($post_id) {
        $memo_data = $this->get_memo_sections($post_id);
        $sections = $memo_data['sections'];

        ob_start();
        ?>
        <div class="inst-memo-dynamic" data-article-type="<?php echo esc_attr($memo_data['type']); ?>">
            <?php foreach ($sections as $section) : ?>
                <?php if ($section['has_content']) : ?>
                <section class="inst-memo-section inst-memo-section-<?php echo esc_attr($section['key']); ?>">
                    <div class="inst-memo-section-header">
                        <svg class="inst-memo-section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <?php echo $this->get_icon_path($section['icon']); ?>
                        </svg>
                        <h2 class="inst-memo-section-title"><?php echo esc_html($section['title']); ?></h2>
                    </div>
                    <div class="inst-memo-section-content">
                        <?php echo wp_kses_post($section['content']); ?>
                    </div>
                </section>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get SVG path for an icon
     *
     * @param string $icon Icon name
     * @return string SVG path
     */
    private function get_icon_path($icon) {
        $icons = array(
            'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
            'briefcase' => '<rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
            'target' => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
            'trending-up' => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>',
            'layers' => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
            'alert-triangle' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
            'globe' => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
            'grid' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
            'info' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
            'building' => '<rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/>',
            'bar-chart-2' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
            'compass' => '<circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>',
            'award' => '<circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>',
            'pie-chart' => '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>',
            'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
            'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'dollar-sign' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
            'user-plus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>',
            'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'home' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
            'file' => '<path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/>',
            'map-pin' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
            'package' => '<line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
            'activity' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
            'flag' => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>',
            'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        );

        return $icons[$icon] ?? $icons['file-text'];
    }
}
