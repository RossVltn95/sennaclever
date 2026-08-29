<?php
/**
 * Intelligence Brief Assembler
 *
 * Assembles all components into a complete intelligence brief.
 * Coordinates extraction, enrichment, narrative generation, and market context.
 *
 * @package Skill_Farm_Finance_Career
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Intelligence_Brief_Assembler {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Component services
     */
    private $enrichment;
    private $narrative;
    private $market_context;

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
        // Services are lazy-loaded
    }

    /**
     * Get enrichment service
     */
    private function get_enrichment() {
        if (null === $this->enrichment) {
            if (!class_exists('SFFC_Intelligence_Enrichment')) {
                require_once SFFC_PLUGIN_DIR . 'includes/class-intelligence-enrichment.php';
            }
            $this->enrichment = SFFC_Intelligence_Enrichment::get_instance();
        }
        return $this->enrichment;
    }

    /**
     * Get narrative service
     */
    private function get_narrative() {
        if (null === $this->narrative) {
            if (!class_exists('SFFC_Intelligence_Narrative_Generator')) {
                require_once SFFC_PLUGIN_DIR . 'includes/class-intelligence-narrative-generator.php';
            }
            $this->narrative = SFFC_Intelligence_Narrative_Generator::get_instance();
        }
        return $this->narrative;
    }

    /**
     * Get market context service
     */
    private function get_market_context() {
        if (null === $this->market_context) {
            if (!class_exists('SFFC_Intelligence_Market_Context')) {
                require_once SFFC_PLUGIN_DIR . 'includes/class-intelligence-market-context.php';
            }
            $this->market_context = SFFC_Intelligence_Market_Context::get_instance();
        }
        return $this->market_context;
    }

    /**
     * Assemble complete brief for a post
     *
     * @param int  $post_id       Post ID
     * @param bool $force_refresh Force refresh of cached data
     * @return array|WP_Error Complete brief data or error
     */
    public function assemble_brief($post_id, $force_refresh = false) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        // Check for cached complete brief
        if (!$force_refresh) {
            $cached_brief = get_post_meta($post_id, '_sffc_complete_brief', true);
            $cached_date = get_post_meta($post_id, '_sffc_brief_generated_date', true);

            // Use cache if less than 24 hours old
            if ($cached_brief && $cached_date && strtotime($cached_date) > strtotime('-24 hours')) {
                return $cached_brief;
            }
        }

        // Step 1: Get or generate intelligence data
        $intelligence = $this->get_intelligence($post_id, $force_refresh);

        if (is_wp_error($intelligence)) {
            return $intelligence;
        }

        // Step 2: Get enrichment data
        $enrichment = get_post_meta($post_id, '_sffc_database_enrichment', true) ?: array();

        // Step 3: Generate narratives
        $narratives = $this->get_narrative()->generate_all_narratives($intelligence, $enrichment);

        // Step 4: Get market context
        $market_context_service = $this->get_market_context();
        $market_context = $market_context_service->get_market_context($intelligence, $enrichment);
        $industry_projections = $market_context_service->get_industry_projections($intelligence, $enrichment);

        // Step 5: Assemble complete brief
        $brief = $this->build_complete_brief(
            $post,
            $intelligence,
            $enrichment,
            $narratives,
            $market_context,
            $industry_projections
        );

        // Step 6: Validate completeness
        $validation = $this->validate_brief($brief);

        if (!$validation['is_complete']) {
            // Try to fill remaining gaps
            $brief = $this->fill_remaining_gaps($brief, $validation['missing']);
        }

        // Step 7: Add sources and metadata
        $brief = $this->add_sources_and_metadata($brief, $intelligence, $enrichment);

        // Cache the complete brief
        update_post_meta($post_id, '_sffc_complete_brief', $brief);
        update_post_meta($post_id, '_sffc_brief_generated_date', current_time('mysql'));

        return $brief;
    }

    /**
     * Get intelligence data for post
     *
     * @param int  $post_id       Post ID
     * @param bool $force_refresh Force refresh
     * @return array|WP_Error Intelligence data
     */
    private function get_intelligence($post_id, $force_refresh = false) {
        $intelligence = get_post_meta($post_id, '_sffc_extracted_intelligence', true);
        $enrichment_date = get_post_meta($post_id, '_sffc_enrichment_date', true);

        // Re-enrich if needed
        $needs_enrichment = $force_refresh ||
            empty($intelligence) ||
            empty($enrichment_date) ||
            strtotime($enrichment_date) < strtotime('-24 hours');

        if ($needs_enrichment) {
            $intelligence = $this->get_enrichment()->enrich_article($post_id);
        }

        return $intelligence;
    }

    /**
     * Build complete brief structure
     *
     * @param WP_Post $post                 Post object
     * @param array   $intelligence         Intelligence data
     * @param array   $enrichment           Enrichment data
     * @param array   $narratives           Generated narratives
     * @param array   $market_context       Market context
     * @param array   $industry_projections Industry projections
     * @return array Complete brief
     */
    private function build_complete_brief($post, $intelligence, $enrichment, $narratives, $market_context, $industry_projections) {
        $exec_summary = $narratives['executive_summary'] ?? array();
        $event_explanation = $narratives['event_explanation'] ?? array();
        $strategic = $narratives['strategic_analysis'] ?? array();
        $risks = $narratives['risk_assessment'] ?? array();
        $insights = $narratives['insights'] ?? array();

        return array(
            // Meta
            'title' => $post->post_title,
            'date' => get_the_date('F j, Y', $post),
            'generated_date' => current_time('F j, Y'),
            'post_id' => $post->ID,

            // Classification
            'classification' => array(
                'level' => 'For Professional Use',
                'sector' => $intelligence['classification']['sector'] ?? '',
                'geography' => $intelligence['classification']['geography'] ?? '',
            ),

            // Event (Page 4)
            'event' => array(
                'type' => $intelligence['event']['type'] ?? 'transaction',
                'title' => $intelligence['event']['title'] ?? $post->post_title,
                'description' => $event_explanation['this_transaction'] ?? $intelligence['event']['description'] ?? '',
                'educational' => $event_explanation['educational'] ?? '',
                'how_it_works' => $event_explanation['how_it_works'] ?? '',
                'timeline' => $event_explanation['timeline'] ?? array(),
                'key_terms' => $event_explanation['key_terms'] ?? array(),
            ),

            // Executive Summary (Page 2)
            'executive_summary' => array(
                'overview' => $exec_summary['overview'] ?? '',
                'key_points' => $exec_summary['key_points'] ?? array(),
                'metrics' => $exec_summary['metrics'] ?? $this->build_metrics_from_intelligence($intelligence),
                'headline_insight' => $exec_summary['headline_insight'] ?? '',
                'confidence' => $intelligence['confidence_level'] ?? 'medium',
            ),

            // Company (Page 3)
            'company' => $this->build_company_data($intelligence, $enrichment),

            // Key Players (Page 5)
            'key_players' => $intelligence['entities']['key_players'] ?? $this->build_key_players($intelligence),
            'key_players_relationship' => $intelligence['entities']['relationship_description'] ?? '',
            'advisors' => $intelligence['entities']['advisors'] ?? array(),

            // Financials (Page 6)
            'financials' => array(
                'deal_value' => $intelligence['financials']['deal_value'] ?? array(),
                'valuation' => $intelligence['financials']['valuation'] ?? array(),
                'multiples' => $this->build_multiples($intelligence, $market_context),
                'key_metrics' => $exec_summary['metrics'] ?? array(),
                'deal_structure' => $intelligence['financials']['deal_structure'] ?? array(),
                'analysis' => $strategic['rationale'] ?? '',
                'chart_data' => $this->build_financial_chart_data($intelligence, $market_context),
            ),

            // Market Context (Page 7)
            'market_context' => array(
                'industry_overview' => $market_context['industry_overview'] ?? '',
                'sector_trends' => $market_context['sector_trends'] ?? array(),
                'comparable_transactions' => $market_context['comparable_transactions'] ?? array(),
                'benchmarks' => $market_context['benchmarks'] ?? array(),
                'chart_data' => $this->build_market_chart_data($market_context),
            ),

            // Industry Projections (Page 8)
            'industry_projections' => array(
                'overview' => $industry_projections['overview'] ?? '',
                'market_size' => $industry_projections['market_size'] ?? array(),
                'growth_forecasts' => $industry_projections['growth_forecasts'] ?? array(),
                'key_drivers' => $industry_projections['key_drivers'] ?? array(),
                'headwinds' => $industry_projections['headwinds'] ?? array(),
                'chart_data' => $this->build_projection_chart_data($industry_projections),
            ),

            // Strategic Analysis (Page 9)
            'strategic_analysis' => array(
                'why_happening' => $strategic['why_happening'] ?? '',
                'rationale' => $strategic['rationale'] ?? '',
                'competitive_implications' => $strategic['competitive_implications'] ?? '',
                'synergies' => $strategic['synergies'] ?? array(),
                'winners' => $strategic['winners'] ?? array(),
                'losers' => $strategic['losers'] ?? array(),
            ),

            // Risks (Page 10)
            'risks' => $risks['risks'] ?? array(),
            'risk_summary' => $risks['summary'] ?? '',
            'risk_mitigation' => $risks['mitigation'] ?? '',

            // Insights (Page 11)
            'insights' => array(
                'unique_observations' => $insights['unique_observations'] ?? array(),
                'what_to_watch' => $insights['what_to_watch'] ?? array(),
                'potential_outcomes' => $insights['potential_outcomes'] ?? array(),
                'next_steps' => $insights['next_steps'] ?? array(),
                'outlook_summary' => $insights['outlook_summary'] ?? '',
            ),

            // Sources (Page 12) - filled in later
            'sources' => array(),
            'methodology' => '',
            'confidence_explanation' => '',
            'disclaimer' => '',
        );
    }

    /**
     * Build company data from intelligence and enrichment
     *
     * @param array $intelligence Intelligence data
     * @param array $enrichment   Enrichment data
     * @return array Company data
     */
    private function build_company_data($intelligence, $enrichment) {
        $company = $intelligence['entities']['primary_company'] ?? array();
        $wikidata = $enrichment['wikidata'] ?? array();
        $stock = $enrichment['stock_data'] ?? array();

        return array(
            'name' => $company['name'] ?? '',
            'description' => $company['description'] ?? $wikidata['description'] ?? $stock['description'] ?? '',
            'founded' => $company['founded'] ?? $wikidata['founded'] ?? '',
            'headquarters' => $company['headquarters'] ?? $wikidata['headquarters'] ?? '',
            'employees' => $company['employees'] ?? $wikidata['employees'] ?? '',
            'revenue' => $company['revenue'] ?? '',
            'industry' => $company['industry'] ?? $wikidata['industry'] ?? $stock['industry'] ?? '',
            'business_model' => $company['business_model'] ?? '',
            'market_position' => $company['market_position'] ?? '',
            'ownership' => $company['ownership'] ?? '',
            'website' => $company['website'] ?? $wikidata['website'] ?? '',
            'ticker' => $company['ticker'] ?? '',
            'market_cap' => $stock['market_cap'] ?? '',
        );
    }

    /**
     * Build key players from intelligence
     *
     * @param array $intelligence Intelligence data
     * @return array Key players
     */
    private function build_key_players($intelligence) {
        $players = array();

        // Primary company
        $primary = $intelligence['entities']['primary_company'] ?? array();
        if (!empty($primary['name'])) {
            $players[] = array(
                'name' => $primary['name'],
                'role' => $intelligence['event']['type'] === 'acquisition' ? 'target' : 'company',
                'description' => $primary['description'] ?? '',
                'type' => $primary['type'] ?? '',
                'country' => $primary['country'] ?? '',
            );
        }

        // Secondary companies
        $secondary = $intelligence['entities']['secondary_companies'] ?? array();
        foreach ($secondary as $sec) {
            if (!empty($sec['name'])) {
                $players[] = array(
                    'name' => $sec['name'],
                    'role' => $sec['role'] ?? 'party',
                    'description' => $sec['description'] ?? '',
                    'type' => $sec['type'] ?? '',
                    'country' => $sec['country'] ?? '',
                );
            }
        }

        return $players;
    }

    /**
     * Build metrics from intelligence data
     *
     * @param array $intelligence Intelligence data
     * @return array Metrics
     */
    private function build_metrics_from_intelligence($intelligence) {
        $metrics = array();
        $financials = $intelligence['financials'] ?? array();

        if (!empty($financials['deal_value']['amount'])) {
            $amount = $financials['deal_value']['amount'];
            $unit = $financials['deal_value']['unit'] ?? 'million';
            $metrics[] = array(
                'label' => 'Deal Value',
                'value' => '$' . $amount . ($unit === 'billion' ? 'B' : 'M'),
            );
        }

        if (!empty($financials['valuation']['amount'])) {
            $amount = $financials['valuation']['amount'];
            $unit = $financials['valuation']['unit'] ?? 'million';
            $metrics[] = array(
                'label' => 'Valuation',
                'value' => '$' . $amount . ($unit === 'billion' ? 'B' : 'M'),
            );
        }

        // Add other figures
        $other = $financials['other_figures'] ?? array();
        foreach ($other as $fig) {
            if (!empty($fig['label']) && !empty($fig['value']) && count($metrics) < 4) {
                $metrics[] = array(
                    'label' => $fig['label'],
                    'value' => $fig['value'],
                );
            }
        }

        return $metrics;
    }

    /**
     * Build multiples data
     *
     * @param array $intelligence   Intelligence data
     * @param array $market_context Market context
     * @return array Multiples
     */
    private function build_multiples($intelligence, $market_context) {
        $multiples = array();
        $benchmarks = $market_context['benchmarks'] ?? array();

        // Extract multiples from other figures
        $figures = $intelligence['financials']['other_figures'] ?? array();
        foreach ($figures as $fig) {
            $label = strtolower($fig['label'] ?? '');
            if (strpos($label, 'multiple') !== false || strpos($label, 'ev/') !== false || strpos($label, 'p/e') !== false) {
                $multiple = array(
                    'name' => $fig['label'],
                    'value' => $fig['value'],
                    'sector_median' => 'N/A',
                    'assessment' => '',
                );

                // Try to find matching benchmark
                foreach ($benchmarks as $bench) {
                    if (stripos($bench['label'], substr($fig['label'], 0, 5)) !== false) {
                        $multiple['sector_median'] = $bench['value'];
                        break;
                    }
                }

                $multiples[] = $multiple;
            }
        }

        return $multiples;
    }

    /**
     * Build financial chart data
     *
     * @param array $intelligence   Intelligence data
     * @param array $market_context Market context
     * @return array Chart data
     */
    private function build_financial_chart_data($intelligence, $market_context) {
        $labels = array();
        $values = array();

        $comps = $market_context['comparable_transactions'] ?? array();
        foreach (array_slice($comps, 0, 5) as $comp) {
            if (!empty($comp['target']) && !empty($comp['value'])) {
                $labels[] = substr($comp['target'], 0, 15);
                // Extract numeric value
                $value = preg_replace('/[^0-9.]/', '', $comp['value']);
                $values[] = floatval($value);
            }
        }

        if (empty($labels)) {
            return array();
        }

        return array(
            'labels' => $labels,
            'values' => $values,
        );
    }

    /**
     * Build market chart data
     *
     * @param array $market_context Market context
     * @return array Chart data
     */
    private function build_market_chart_data($market_context) {
        // Return empty - charts will be generated based on available data
        return array();
    }

    /**
     * Build projection chart data
     *
     * @param array $projections Industry projections
     * @return array Chart data
     */
    private function build_projection_chart_data($projections) {
        $forecasts = $projections['growth_forecasts'] ?? array();

        if (empty($forecasts)) {
            return array();
        }

        $labels = array(date('Y'), date('Y') + 1, date('Y') + 2);
        $values = array();

        // Use first forecast with numeric data
        foreach ($forecasts as $forecast) {
            $y0 = preg_replace('/[^0-9.]/', '', $forecast['year_0'] ?? '');
            $y1 = preg_replace('/[^0-9.]/', '', $forecast['year_1'] ?? '');
            $y2 = preg_replace('/[^0-9.]/', '', $forecast['year_2'] ?? '');

            if ($y0 && $y1 && $y2) {
                $values = array(floatval($y0), floatval($y1), floatval($y2));
                break;
            }
        }

        if (empty($values)) {
            return array();
        }

        return array(
            'labels' => $labels,
            'values' => $values,
        );
    }

    /**
     * Validate brief completeness
     *
     * @param array $brief Brief data
     * @return array Validation result
     */
    private function validate_brief($brief) {
        $required = array(
            'Executive Summary' => !empty($brief['executive_summary']['overview']),
            'Company Name' => !empty($brief['company']['name']),
            'Company Description' => !empty($brief['company']['description']),
            'Event Type' => !empty($brief['event']['type']),
            'Event Description' => !empty($brief['event']['description']),
            'Strategic Analysis' => !empty($brief['strategic_analysis']['rationale']),
            'Risk Assessment' => !empty($brief['risks']),
            'Market Context' => !empty($brief['market_context']['industry_overview']),
        );

        $missing = array();
        foreach ($required as $field => $has_value) {
            if (!$has_value) {
                $missing[] = $field;
            }
        }

        return array(
            'is_complete' => empty($missing),
            'missing' => $missing,
            'completeness_score' => (count($required) - count($missing)) / count($required) * 100,
        );
    }

    /**
     * Fill remaining gaps in brief
     *
     * @param array $brief   Brief data
     * @param array $missing Missing fields
     * @return array Updated brief
     */
    private function fill_remaining_gaps($brief, $missing) {
        // Add placeholder content for missing sections
        foreach ($missing as $field) {
            switch ($field) {
                case 'Executive Summary':
                    if (empty($brief['executive_summary']['overview'])) {
                        $brief['executive_summary']['overview'] = 'This intelligence brief provides analysis of ' .
                            ($brief['company']['name'] ?? 'the company') . ' and its recent ' .
                            ($brief['event']['type'] ?? 'transaction') . '.';
                    }
                    break;

                case 'Company Description':
                    if (empty($brief['company']['description'])) {
                        $brief['company']['description'] = ($brief['company']['name'] ?? 'The company') .
                            ' operates in the ' . ($brief['company']['industry'] ?? 'industry') . ' sector.';
                    }
                    break;

                case 'Event Description':
                    if (empty($brief['event']['description'])) {
                        $brief['event']['description'] = 'This ' . ($brief['event']['type'] ?? 'transaction') .
                            ' involves ' . ($brief['company']['name'] ?? 'the company') . '.';
                    }
                    break;

                case 'Strategic Analysis':
                    if (empty($brief['strategic_analysis']['rationale'])) {
                        $brief['strategic_analysis']['rationale'] = 'The strategic rationale for this transaction ' .
                            'reflects broader industry trends and company-specific objectives.';
                    }
                    break;

                case 'Market Context':
                    if (empty($brief['market_context']['industry_overview'])) {
                        $brief['market_context']['industry_overview'] = 'The ' .
                            ($brief['company']['industry'] ?? 'relevant') .
                            ' sector continues to evolve with changing market dynamics.';
                    }
                    break;
            }
        }

        return $brief;
    }

    /**
     * Add sources and metadata to brief
     *
     * @param array $brief        Brief data
     * @param array $intelligence Intelligence data
     * @param array $enrichment   Enrichment data
     * @return array Updated brief
     */
    private function add_sources_and_metadata($brief, $intelligence, $enrichment) {
        $sources = array();

        // Add sources from intelligence
        if (!empty($intelligence['sources'])) {
            $sources = array_merge($sources, $intelligence['sources']);
        }

        // Add database sources
        if (!empty($enrichment['wikidata'])) {
            $sources[] = array(
                'name' => 'Wikidata',
                'url' => 'https://www.wikidata.org',
                'type' => 'primary',
                'date' => date('M j, Y'),
            );
        }

        if (!empty($enrichment['stock_data'])) {
            $sources[] = array(
                'name' => 'Alpha Vantage',
                'url' => 'https://www.alphavantage.com',
                'type' => 'financial',
                'date' => date('M j, Y'),
            );
        }

        // Add article source
        $sources[] = array(
            'name' => 'Original Article',
            'url' => get_permalink($brief['post_id']),
            'type' => 'primary',
            'date' => $brief['date'],
        );

        $brief['sources'] = $sources;

        $brief['methodology'] = 'This intelligence brief was compiled using a combination of article analysis, ' .
            'publicly available data sources (Wikidata, SEC EDGAR, market data providers), and AI-assisted ' .
            'research and narrative generation. Financial data was cross-validated where multiple sources were available.';

        $brief['confidence_explanation'] = 'Confidence levels are assigned based on source reliability and ' .
            'cross-validation: HIGH indicates verification from 2+ authoritative sources, MEDIUM indicates ' .
            'single authoritative source or multiple secondary sources, LOW indicates estimates or unverified data.';

        return $brief;
    }
}
