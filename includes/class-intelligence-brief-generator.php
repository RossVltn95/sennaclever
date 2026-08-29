<?php
/**
 * Intelligence Brief PDF Generator
 *
 * Generates professional PDF intelligence briefs using Dompdf.
 *
 * @package Skill_Farm_Finance_Career
 */

if (!defined('ABSPATH')) {
    exit;
}

use Dompdf\Dompdf;
use Dompdf\Options;

class SFFC_Intelligence_Brief_Generator {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Template directory path
     */
    private $template_dir;

    /**
     * Chart generator instance
     */
    private $chart_generator;

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
        $this->template_dir = SFFC_PLUGIN_DIR . 'templates/intelligence-briefs/';
        $this->chart_generator = new SFFC_Chart_Generator();
    }

    /**
     * Generate PDF from brief data
     *
     * @param array $brief_data The intelligence brief data
     * @param bool  $stream     Whether to stream directly to browser
     * @return string|void PDF content or streams to browser
     */
    public function generate_pdf($brief_data, $stream = true) {
        // Validate brief data completeness
        $validation = $this->validate_brief_data($brief_data);
        if (!$validation['is_complete']) {
            return new WP_Error('incomplete_data', 'Brief data is incomplete: ' . implode(', ', $validation['missing']));
        }

        // Generate charts for the brief
        $brief_data = $this->generate_charts($brief_data);

        // Render HTML from templates
        $html = $this->render_html($brief_data);

        if (is_wp_error($html)) {
            return $html;
        }

        // Configure Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'sans-serif');
        $options->set('dpi', 150);
        $options->set('defaultMediaType', 'print');
        $options->set('isFontSubsettingEnabled', true);

        // Create Dompdf instance
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Generate filename
        $filename = $this->generate_filename($brief_data);

        if ($stream) {
            // Stream to browser
            $dompdf->stream($filename, array('Attachment' => true));
            exit;
        }

        // Return PDF content
        return $dompdf->output();
    }

    /**
     * Validate that brief data has all required sections
     *
     * @param array $brief_data The brief data to validate
     * @return array Validation result with is_complete and missing fields
     */
    public function validate_brief_data($brief_data) {
        // Only require the absolute minimum fields
        // Other sections will show with fallback content
        $required_sections = array(
            'title' => 'Title',
            'company.name' => 'Company Name',
            'event.type' => 'Event Type',
        );

        // Nice-to-have sections that won't block generation
        $optional_sections = array(
            'executive_summary.overview' => 'Executive Summary',
            'company.description' => 'Company Description',
            'strategic_analysis.rationale' => 'Strategic Analysis',
            'risks' => 'Risk Assessment',
            'market_context.industry_overview' => 'Market Context',
        );

        $missing = array();
        $incomplete = array();

        foreach ($required_sections as $key => $label) {
            $value = $this->get_nested_value($brief_data, $key);
            if (empty($value)) {
                $missing[] = $label;
            }
        }

        foreach ($optional_sections as $key => $label) {
            $value = $this->get_nested_value($brief_data, $key);
            if (empty($value)) {
                $incomplete[] = $label;
            }
        }

        return array(
            'is_complete' => empty($missing), // Only required fields block completion
            'missing' => $missing,
            'incomplete' => $incomplete, // These have fallbacks but could be better
        );
    }

    /**
     * Get nested array value using dot notation
     *
     * @param array  $array The array to search
     * @param string $key   Dot-notation key
     * @return mixed The value or null
     */
    private function get_nested_value($array, $key) {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Generate charts for the brief using QuickChart.io
     *
     * @param array $brief_data The brief data
     * @return array Brief data with chart URLs populated
     */
    private function generate_charts($brief_data) {
        // Financial chart
        if (!empty($brief_data['financials']['chart_data'])) {
            $brief_data['financials']['chart_url'] = $this->chart_generator->generate_bar_chart(
                $brief_data['financials']['chart_data'],
                'Valuation Comparison'
            );
        }

        // Market context chart
        if (!empty($brief_data['market_context']['chart_data'])) {
            $brief_data['market_context']['chart_url'] = $this->chart_generator->generate_line_chart(
                $brief_data['market_context']['chart_data'],
                'Market Trend'
            );
        }

        // Industry projections chart
        if (!empty($brief_data['industry_projections']['chart_data'])) {
            $brief_data['industry_projections']['chart_url'] = $this->chart_generator->generate_area_chart(
                $brief_data['industry_projections']['chart_data'],
                'Industry Growth Projection'
            );
        }

        return $brief_data;
    }

    /**
     * Render HTML from templates
     *
     * @param array $brief_data The brief data
     * @return string|WP_Error The rendered HTML or error
     */
    private function render_html($brief_data) {
        $template_file = $this->template_dir . 'master.php';

        if (!file_exists($template_file)) {
            return new WP_Error('template_missing', 'Master template file not found');
        }

        // Make brief data available to template
        $brief = $brief_data;

        // Start output buffering
        ob_start();

        // Include the template
        include $template_file;

        // Get the rendered HTML
        $html = ob_get_clean();

        return $html;
    }

    /**
     * Generate filename for the PDF
     *
     * @param array $brief_data The brief data
     * @return string The filename
     */
    private function generate_filename($brief_data) {
        $title = isset($brief_data['title']) ? $brief_data['title'] : 'Intelligence Brief';
        $date = isset($brief_data['date']) ? $brief_data['date'] : date('Y-m-d');

        // Sanitize title for filename
        $safe_title = sanitize_title($title);
        $safe_title = substr($safe_title, 0, 50); // Limit length

        return sprintf('senna-intelligence-brief-%s-%s.pdf', $safe_title, date('Ymd', strtotime($date)));
    }

    /**
     * Generate brief from post ID
     *
     * @param int  $post_id       The post ID
     * @param bool $force_refresh Force refresh of cached data
     * @return array|WP_Error Brief data or error
     */
    public function generate_brief_from_post($post_id, $force_refresh = false) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        // Use the assembler to build the complete brief
        if (class_exists('SFFC_Intelligence_Brief_Assembler')) {
            $assembler = SFFC_Intelligence_Brief_Assembler::get_instance();
            return $assembler->assemble_brief($post_id, $force_refresh);
        }

        // Fallback to legacy method if assembler not available
        $intelligence = get_post_meta($post_id, '_sffc_extracted_intelligence', true);
        $contextual = get_post_meta($post_id, '_sffc_contextual_data', true);

        if (empty($intelligence)) {
            return new WP_Error('no_intelligence', 'No intelligence data extracted for this article. Run enrichment first.');
        }

        return $this->build_brief_structure($post, $intelligence, $contextual);
    }

    /**
     * Build brief data structure from extracted intelligence
     *
     * @param WP_Post $post         The post object
     * @param array   $intelligence Extracted intelligence data
     * @param array   $contextual   Contextual enrichment data
     * @return array The brief data structure
     */
    private function build_brief_structure($post, $intelligence, $contextual = array()) {
        $brief = array(
            'title' => $post->post_title,
            'date' => get_the_date('F j, Y', $post),
            'generated_date' => date('F j, Y'),

            // Classification
            'classification' => array(
                'level' => 'For Professional Use',
                'sector' => $this->get_value($intelligence, 'classification.sector', ''),
                'geography' => $this->get_value($intelligence, 'classification.geography', ''),
            ),

            // Event
            'event' => array(
                'type' => $this->get_value($intelligence, 'event.type', 'analysis'),
                'title' => $this->get_value($intelligence, 'event.title', ''),
                'description' => $this->get_value($intelligence, 'event.description', ''),
                'educational' => $this->get_value($intelligence, 'event.educational', ''),
                'how_it_works' => $this->get_value($intelligence, 'event.how_it_works', ''),
                'timeline' => $this->get_value($intelligence, 'event.timeline', array()),
            ),

            // Executive Summary
            'executive_summary' => array(
                'overview' => $this->get_value($intelligence, 'executive_summary.overview', ''),
                'key_points' => $this->get_value($intelligence, 'executive_summary.key_points', array()),
                'metrics' => $this->get_value($intelligence, 'executive_summary.metrics', array()),
                'confidence' => $this->get_value($intelligence, 'confidence_level', 'medium'),
            ),

            // Company
            'company' => array(
                'name' => $this->get_value($intelligence, 'entities.primary_company.name', ''),
                'description' => $this->get_value($intelligence, 'entities.primary_company.description', ''),
                'founded' => $this->get_value($intelligence, 'entities.primary_company.founded', ''),
                'headquarters' => $this->get_value($intelligence, 'entities.primary_company.headquarters', ''),
                'employees' => $this->get_value($intelligence, 'entities.primary_company.employees', ''),
                'revenue' => $this->get_value($intelligence, 'entities.primary_company.revenue', ''),
                'industry' => $this->get_value($intelligence, 'entities.primary_company.industry', ''),
                'business_model' => $this->get_value($intelligence, 'entities.primary_company.business_model', ''),
                'market_position' => $this->get_value($intelligence, 'entities.primary_company.market_position', ''),
                'ownership' => $this->get_value($intelligence, 'entities.primary_company.ownership', ''),
                'website' => $this->get_value($intelligence, 'entities.primary_company.website', ''),
            ),

            // Key Players
            'key_players' => $this->get_value($intelligence, 'entities.key_players', array()),
            'key_players_relationship' => $this->get_value($intelligence, 'entities.relationship_description', ''),
            'advisors' => $this->get_value($intelligence, 'entities.advisors', array()),

            // Financials
            'financials' => array(
                'deal_value' => $this->get_value($intelligence, 'financials.deal_value', array()),
                'valuation' => $this->get_value($intelligence, 'financials.valuation', array()),
                'multiples' => $this->get_value($intelligence, 'financials.multiples', array()),
                'key_metrics' => $this->get_value($intelligence, 'financials.key_metrics', array()),
                'deal_structure' => $this->get_value($intelligence, 'financials.deal_structure', array()),
                'analysis' => $this->get_value($intelligence, 'financials.analysis', ''),
                'chart_data' => $this->get_value($intelligence, 'financials.chart_data', array()),
            ),

            // Market Context
            'market_context' => array(
                'industry_overview' => $this->get_value($contextual, 'market_data.industry_overview',
                    $this->get_value($intelligence, 'market_context.industry_overview', '')),
                'sector_trends' => $this->get_value($contextual, 'market_data.sector_trends',
                    $this->get_value($intelligence, 'market_context.sector_trends', array())),
                'comparable_transactions' => $this->get_value($contextual, 'market_data.coparable_transactions',
                    $this->get_value($intelligence, 'market_context.coparable_transactions', array())),
                'benchmarks' => $this->get_value($contextual, 'market_data.benchmarks',
                    $this->get_value($intelligence, 'market_context.benchmarks', array())),
                'chart_data' => $this->get_value($intelligence, 'market_context.chart_data', array()),
            ),

            // Industry Projections
            'industry_projections' => array(
                'overview' => $this->get_value($contextual, 'projections.overview',
                    $this->get_value($intelligence, 'industry_projections.overview', '')),
                'growth_forecasts' => $this->get_value($contextual, 'projections.growth_forecasts',
                    $this->get_value($intelligence, 'industry_projections.growth_forecasts', array())),
                'market_size' => $this->get_value($contextual, 'projections.market_size',
                    $this->get_value($intelligence, 'industry_projections.market_size', array())),
                'key_drivers' => $this->get_value($contextual, 'projections.key_drivers',
                    $this->get_value($intelligence, 'industry_projections.key_drivers', array())),
                'headwinds' => $this->get_value($contextual, 'projections.headwinds',
                    $this->get_value($intelligence, 'industry_projections.headwinds', array())),
                'chart_data' => $this->get_value($intelligence, 'industry_projections.chart_data', array()),
            ),

            // Strategic Analysis
            'strategic_analysis' => array(
                'why_happening' => $this->get_value($intelligence, 'strategic_analysis.why_happening', ''),
                'rationale' => $this->get_value($intelligence, 'strategic_analysis.rationale', ''),
                'competitive_implications' => $this->get_value($intelligence, 'strategic_analysis.copetitive_implications', ''),
                'synergies' => $this->get_value($intelligence, 'strategic_analysis.synergies', array()),
                'winners' => $this->get_value($intelligence, 'strategic_analysis.winners', array()),
                'losers' => $this->get_value($intelligence, 'strategic_analysis.losers', array()),
            ),

            // Risks
            'risks' => $this->get_value($intelligence, 'risks', array()),
            'risk_summary' => $this->get_value($intelligence, 'risk_summary', ''),
            'risk_mitigation' => $this->get_value($intelligence, 'risk_mitigation', ''),

            // Insights
            'insights' => array(
                'unique_observations' => $this->get_value($intelligence, 'insights.unique_observations', array()),
                'what_to_watch' => $this->get_value($intelligence, 'insights.what_to_watch', array()),
                'potential_outcomes' => $this->get_value($intelligence, 'insights.potential_outcomes', array()),
                'next_steps' => $this->get_value($intelligence, 'insights.next_steps', array()),
                'outlook_summary' => $this->get_value($intelligence, 'insights.outlook_summary', ''),
            ),

            // Sources
            'sources' => $this->get_value($intelligence, 'sources', array()),
            'methodology' => $this->get_value($intelligence, 'methodology', ''),
            'confidence_explanation' => $this->get_value($intelligence, 'confidence_explanation', ''),
            'disclaimer' => '',
        );

        return $brief;
    }

    /**
     * Helper to get nested value with default
     *
     * @param array  $array   The array
     * @param string $key     Dot notation key
     * @param mixed  $default Default value
     * @return mixed
     */
    private function get_value($array, $key, $default = '') {
        if (empty($array)) {
            return $default;
        }

        $value = $this->get_nested_value($array, $key);
        return !empty($value) ? $value : $default;
    }
}

/**
 * Chart Generator using QuickChart.io
 */
class SFFC_Chart_Generator {

    /**
     * QuickChart.io base URL
     */
    private $base_url = 'https://quickchart.io/chart';

    /**
     * Brand colors
     */
    private $colors = array(
        'primary' => '#007380',
        'secondary' => '#0A1628',
        'green' => '#1E6B4A',
        'light' => '#E6F4F5',
    );

    /**
     * Generate a bar chart URL
     *
     * @param array  $data  Chart data with labels and values
     * @param string $title Chart title
     * @return string Chart URL
     */
    public function generate_bar_chart($data, $title = '') {
        $labels = isset($data['labels']) ? $data['labels'] : array();
        $values = isset($data['values']) ? $data['values'] : array();

        $chart_config = array(
            'type' => 'bar',
            'data' => array(
                'labels' => $labels,
                'datasets' => array(
                    array(
                        'label' => $title,
                        'data' => $values,
                        'backgroundColor' => array_fill(0, count($values), $this->colors['primary']),
                        'borderColor' => array_fill(0, count($values), $this->colors['secondary']),
                        'borderWidth' => 1,
                    ),
                ),
            ),
            'options' => array(
                'legend' => array('display' => false),
                'title' => array(
                    'display' => !empty($title),
                    'text' => $title,
                ),
                'scales' => array(
                    'yAxes' => array(
                        array('ticks' => array('beginAtZero' => true)),
                    ),
                ),
            ),
        );

        return $this->build_url($chart_config);
    }

    /**
     * Generate a line chart URL
     *
     * @param array  $data  Chart data with labels and datasets
     * @param string $title Chart title
     * @return string Chart URL
     */
    public function generate_line_chart($data, $title = '') {
        $labels = isset($data['labels']) ? $data['labels'] : array();
        $values = isset($data['values']) ? $data['values'] : array();

        $chart_config = array(
            'type' => 'line',
            'data' => array(
                'labels' => $labels,
                'datasets' => array(
                    array(
                        'label' => $title,
                        'data' => $values,
                        'fill' => false,
                        'borderColor' => $this->colors['primary'],
                        'backgroundColor' => $this->colors['primary'],
                        'tension' => 0.1,
                    ),
                ),
            ),
            'options' => array(
                'legend' => array('display' => false),
                'title' => array(
                    'display' => !empty($title),
                    'text' => $title,
                ),
            ),
        );

        return $this->build_url($chart_config);
    }

    /**
     * Generate an area chart URL
     *
     * @param array  $data  Chart data
     * @param string $title Chart title
     * @return string Chart URL
     */
    public function generate_area_chart($data, $title = '') {
        $labels = isset($data['labels']) ? $data['labels'] : array();
        $values = isset($data['values']) ? $data['values'] : array();

        $chart_config = array(
            'type' => 'line',
            'data' => array(
                'labels' => $labels,
                'datasets' => array(
                    array(
                        'label' => $title,
                        'data' => $values,
                        'fill' => true,
                        'borderColor' => $this->colors['primary'],
                        'backgroundColor' => $this->hex_to_rgba($this->colors['primary'], 0.2),
                        'tension' => 0.4,
                    ),
                ),
            ),
            'options' => array(
                'legend' => array('display' => false),
                'title' => array(
                    'display' => !empty($title),
                    'text' => $title,
                ),
            ),
        );

        return $this->build_url($chart_config);
    }

    /**
     * Generate a donut chart URL
     *
     * @param array  $data  Chart data with labels and values
     * @param string $title Chart title
     * @return string Chart URL
     */
    public function generate_donut_chart($data, $title = '') {
        $labels = isset($data['labels']) ? $data['labels'] : array();
        $values = isset($data['values']) ? $data['values'] : array();

        $colors = array(
            $this->colors['primary'],
            $this->colors['green'],
            $this->colors['secondary'],
            '#718096',
            '#A0AEC0',
        );

        $chart_config = array(
            'type' => 'doughnut',
            'data' => array(
                'labels' => $labels,
                'datasets' => array(
                    array(
                        'data' => $values,
                        'backgroundColor' => array_slice($colors, 0, count($values)),
                    ),
                ),
            ),
            'options' => array(
                'legend' => array(
                    'position' => 'right',
                ),
                'title' => array(
                    'display' => !empty($title),
                    'text' => $title,
                ),
            ),
        );

        return $this->build_url($chart_config);
    }

    /**
     * Build QuickChart URL from config
     *
     * @param array $config Chart configuration
     * @return string The chart URL
     */
    private function build_url($config) {
        $chart_json = json_encode($config);
        return $this->base_url . '?c=' . urlencode($chart_json) . '&w=500&h=300&bkg=white';
    }

    /**
     * Convert hex color to rgba
     *
     * @param string $hex   Hex color
     * @param float  $alpha Alpha value
     * @return string RGBA color string
     */
    private function hex_to_rgba($hex, $alpha = 1) {
        $hex = str_replace('#', '', $hex);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "rgba($r, $g, $b, $alpha)";
    }
}
