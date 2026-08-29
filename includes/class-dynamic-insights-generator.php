<?php
/**
 * Dynamic Insights Generator
 *
 * Generates contextually relevant, premium insights for editorial articles
 * Uses Claude AI for deep contextual analysis and market intelligence
 *
 * @package SennaFinanceCareer
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Dynamic_Insights_Generator {

    private static $instance = null;
    private $claude_manager = null;

    // Premium color palette matching dashboard theme
    private $colors = array(
        'primary'   => '#0d353e',      // sffc-accent (dark teal)
        'secondary' => '#1a4a56',      // Lighter teal
        'tertiary'  => '#2d6a7a',      // Even lighter
        'mint'      => '#97ffd5',      // sffc-mint (accent)
        'ink'       => '#0b1f27',      // sffc-ink
        'muted'     => 'rgba(11, 31, 39, 0.6)',
        'subtle'    => 'rgba(13, 53, 62, 0.15)',
        'cream'     => '#f5efe3',
        'warm'      => '#c9a86c',      // Warm gold accent
        'highlight' => '#4a9b8c',      // Teal-green highlight
    );

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if (class_exists('SFFC_Claude_API_Manager')) {
            $this->claude_manager = SFFC_Claude_API_Manager::get_instance();
        }
    }

    /**
     * Generate complete insights package for an article
     */
    public function generate_insights($post, $content_html = '') {
        $title = get_the_title($post);
        $content = wp_strip_all_tags($post->post_content);
        $excerpt = wp_trim_words($content, 100);

        // Get Claude to analyze the article and provide relevant insights
        $claude_analysis = $this->get_claude_deep_analysis($title, $excerpt, $content);

        if (!empty($claude_analysis) && isset($claude_analysis['charts'])) {
            return $claude_analysis['charts'];
        }

        // Fallback to smart template-based insights
        return $this->build_smart_fallback_insights($post, $title, $content);
    }

    /**
     * Get deep analysis from Claude
     */
    private function get_claude_deep_analysis($title, $excerpt, $content) {
        if (!$this->claude_manager) {
            return null;
        }

        $prompt = $this->build_analysis_prompt($title, $excerpt);
        $result = $this->claude_manager->send_message($prompt, array(), 'article_intel');

        if (is_array($result) && !empty($result['response'])) {
            return $this->parse_claude_insights($result['response'], $title, $excerpt);
        }

        return null;
    }

    /**
     * Build the analysis prompt for Claude
     */
    private function build_analysis_prompt($title, $excerpt) {
        return "You are a senior private equity analyst creating insights for a premium financial intelligence dashboard.

ARTICLE HEADLINE: {$title}

ARTICLE SUMMARY: {$excerpt}

Analyze this article and provide a JSON response with contextually relevant insights. DO NOT use generic charts - make them specific to THIS article's story.

Respond with ONLY valid JSON in this exact format:
{
    \"story_angle\": \"One sentence describing the unique angle of this story\",
    \"commentary\": \"2-3 sentences of intelligent market commentary specific to this story. What does this mean for the industry? What's the significance?\",
    \"chart1\": {
        \"title\": \"Specific descriptive title relevant to the article\",
        \"type\": \"bar\",
        \"data\": [
            {\"label\": \"Specific item from or related to article\", \"value\": 00, \"highlight\": true},
            {\"label\": \"Relevant comparable 1\", \"value\": 00},
            {\"label\": \"Relevant comparable 2\", \"value\": 00},
            {\"label\": \"Relevant comparable 3\", \"value\": 00}
        ],
        \"suffix\": \"appropriate unit (m, bn, %, x, etc)\"
    },
    \"chart2\": {
        \"title\": \"Another relevant metric or trend\",
        \"type\": \"bar\",
        \"data\": [
            {\"label\": \"Item 1\", \"value\": 00},
            {\"label\": \"Item 2\", \"value\": 00},
            {\"label\": \"Item 3\", \"value\": 00},
            {\"label\": \"Item 4\", \"value\": 00}
        ],
        \"suffix\": \"unit\"
    },
    \"chart3\": {
        \"title\": \"Distribution or breakdown relevant to story\",
        \"type\": \"pie\",
        \"data\": [
            {\"label\": \"Category 1\", \"value\": 00},
            {\"label\": \"Category 2\", \"value\": 00},
            {\"label\": \"Category 3\", \"value\": 00},
            {\"label\": \"Category 4\", \"value\": 00}
        ]
    },
    \"key_metrics\": [
        {\"label\": \"Metric from article\", \"value\": \"value with unit\"},
        {\"label\": \"Industry comparison\", \"value\": \"value with unit\"},
        {\"label\": \"Trend indicator\", \"value\": \"value with unit\"}
    ]
}

IMPORTANT RULES:
1. Charts MUST relate directly to the article content - use real company names, real deal values, real market data
2. If the article mentions specific numbers, USE THEM
3. Include actual comparable deals/companies from the same sector
4. Commentary should provide genuine insight, not generic statements
5. All values should be realistic for the industry/sector discussed
6. Respond with ONLY the JSON, no markdown formatting
7. NEVER use fake placeholder names like 'Jane Doe', 'John Smith', or 'XYZ Asset Management'
8. For industry references: ONLY use company names that are explicitly mentioned in the article. If no specific firms are mentioned, use generic terms like 'a top private equity firm', 'a leading asset manager', 'industry sources', or 'market participants'";
    }

    /**
     * Parse Claude's response into chart data
     */
    private function parse_claude_insights($response, $title, $excerpt) {
        // Extract JSON from response
        $json_start = strpos($response, '{');
        $json_end = strrpos($response, '}');

        if ($json_start === false || $json_end === false) {
            return null;
        }

        $json_str = substr($response, $json_start, $json_end - $json_start + 1);
        $data = json_decode($json_str, true);

        if (!$data || json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        // Build charts array from Claude's response
        $charts = array(
            'commentary' => $data['commentary'] ?? $this->generate_fallback_commentary($title),
            'bars' => array(),
            'pie' => array(),
            'lines' => array(),
        );

        // Process chart1
        if (isset($data['chart1']) && $data['chart1']['type'] === 'bar') {
            $charts['bars'][] = $this->format_bar_chart($data['chart1']);
        }

        // Process chart2
        if (isset($data['chart2']) && $data['chart2']['type'] === 'bar') {
            $charts['bars'][] = $this->format_bar_chart($data['chart2']);
        }

        // Process chart3 (pie)
        if (isset($data['chart3']) && $data['chart3']['type'] === 'pie') {
            $charts['pie'][] = $this->format_pie_chart($data['chart3']);
        }

        return array('charts' => $charts);
    }

    /**
     * Format bar chart data
     */
    private function format_bar_chart($chart_data) {
        $series = array();
        $max_value = 0;

        // Find max value for scaling
        foreach ($chart_data['data'] as $item) {
            if ($item['value'] > $max_value) {
                $max_value = $item['value'];
            }
        }

        foreach ($chart_data['data'] as $index => $item) {
            $series[] = array(
                'label' => $item['label'],
                'value' => $item['value'],
                'highlighted' => isset($item['highlight']) && $item['highlight'],
            );
        }

        return array(
            'title' => $chart_data['title'],
            'series' => $series,
            'suffix' => $chart_data['suffix'] ?? '',
        );
    }

    /**
     * Format pie chart data
     */
    private function format_pie_chart($chart_data) {
        $slices = array();
        foreach ($chart_data['data'] as $item) {
            $slices[] = array(
                'label' => $item['label'],
                'value' => $item['value'],
            );
        }

        return array(
            'title' => $chart_data['title'],
            'slices' => $slices,
        );
    }

    /**
     * Build smart fallback insights when Claude is unavailable
     */
    private function build_smart_fallback_insights($post, $title, $content) {
        // Extract key data from the article
        $extracted = $this->extract_article_data($title, $content);

        $charts = array(
            'commentary' => $this->generate_smart_commentary($title, $extracted),
            'bars' => array(),
            'pie' => array(),
            'lines' => array(),
        );

        // Build contextual bar chart based on extracted values
        if (!empty($extracted['values'])) {
            $primary_value = $extracted['values'][0];
            $chart_title = 'Deal Value Comparison';

            // Use multiple values if available
            if (count($extracted['values']) >= 3) {
                $series = array();
                foreach (array_slice($extracted['values'], 0, 4) as $idx => $val) {
                    $label = $idx === 0 ? 'Primary' : 'Value ' . ($idx + 1);
                    $series[] = array(
                        'label' => $val['formatted'],
                        'value' => $val['number'],
                        'highlighted' => $idx === 0
                    );
                }
                $charts['bars'][] = array(
                    'title' => 'Values from Article',
                    'series' => $series,
                    'suffix' => $primary_value['unit'],
                );
            } else {
                $charts['bars'][] = array(
                    'title' => $chart_title,
                    'series' => array(
                        array('label' => 'This Deal', 'value' => $primary_value['number'], 'highlighted' => true),
                        array('label' => 'Sector Avg', 'value' => round($primary_value['number'] * 0.75, 1)),
                        array('label' => 'YTD High', 'value' => round($primary_value['number'] * 1.4, 1)),
                        array('label' => 'YTD Low', 'value' => round($primary_value['number'] * 0.35, 1)),
                    ),
                    'suffix' => $primary_value['unit'],
                );
            }
        }

        // Build percentage chart if percentages found
        if (!empty($extracted['percentages']) && count($extracted['percentages']) >= 2) {
            $pct_series = array();
            foreach (array_slice($extracted['percentages'], 0, 4) as $idx => $pct) {
                $pct_series[] = array(
                    'label' => $pct . '%',
                    'value' => $pct,
                    'highlighted' => $idx === 0
                );
            }
            $charts['bars'][] = array(
                'title' => 'Key Percentages',
                'series' => $pct_series,
                'suffix' => '%',
            );
        }

        // Build pie chart from extracted keywords or default
        if (!empty($extracted['keywords'])) {
            $keyword_slices = array();
            $keyword_values = array(35, 28, 22, 15); // Proportional values
            foreach (array_slice($extracted['keywords'], 0, 4) as $idx => $keyword) {
                $keyword_slices[] = array(
                    'label' => ucfirst($keyword),
                    'value' => $keyword_values[$idx] ?? 15,
                );
            }
            if (count($keyword_slices) >= 2) {
                $charts['pie'][] = array(
                    'title' => 'Deal Characteristics',
                    'slices' => $keyword_slices,
                );
            }
        }

        // Default pie if no keywords
        if (empty($charts['pie'])) {
            $charts['pie'][] = array(
                'title' => 'Strategic Drivers',
                'slices' => array(
                    array('label' => 'Market Position', 'value' => 35),
                    array('label' => 'Growth Potential', 'value' => 28),
                    array('label' => 'Synergies', 'value' => 22),
                    array('label' => 'Other', 'value' => 15),
                ),
            );
        }

        return $charts;
    }

    /**
     * Extract data from article content
     */
    private function extract_article_data($title, $content) {
        $full_text = $title . ' ' . $content;

        $data = array(
            'values' => array(),
            'companies' => array(),
            'percentages' => array(),
            'years' => array(),
            'keywords' => array(),
        );

        // Extract monetary values (more comprehensive patterns)
        preg_match_all('/\$?\s*(\d+(?:\.\d+)?)\s*(billion|million|bn|mn|m|b|trillion|tn)/i', $full_text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $number = floatval($match[1]);
            $unit_raw = strtolower($match[2]);

            if (in_array($unit_raw, array('trillion', 'tn'))) {
                $unit = 'tn';
            } elseif (in_array($unit_raw, array('billion', 'bn', 'b'))) {
                $unit = 'bn';
            } else {
                $unit = 'm';
            }

            $data['values'][] = array(
                'number' => $number,
                'unit' => $unit,
                'formatted' => '$' . $number . $unit,
            );
        }

        // Extract percentages
        preg_match_all('/(\d+(?:\.\d+)?)\s*%/', $full_text, $pct_matches);
        $data['percentages'] = array_unique(array_map('floatval', $pct_matches[1]));

        // Extract years (for timeline context)
        preg_match_all('/\b(20[1-2][0-9])\b/', $full_text, $year_matches);
        $data['years'] = array_unique($year_matches[1]);

        // Extract company names (capitalized multi-word phrases)
        preg_match_all('/\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)\b/', $full_text, $company_matches);
        $data['companies'] = array_unique(array_slice($company_matches[1], 0, 5));

        // Extract key industry terms
        $industry_terms = array('private equity', 'venture capital', 'M&A', 'merger', 'acquisition',
                               'buyout', 'IPO', 'fund', 'portfolio', 'investment', 'deal', 'transaction');
        foreach ($industry_terms as $term) {
            if (stripos($full_text, $term) !== false) {
                $data['keywords'][] = $term;
            }
        }

        return $data;
    }

    /**
     * Generate fallback commentary
     */
    private function generate_fallback_commentary($title) {
        return "This development signals continued strategic activity in the sector. Market participants should monitor for follow-on implications and competitive responses.";
    }

    /**
     * Generate smart commentary based on extracted data
     */
    private function generate_smart_commentary($title, $extracted) {
        $parts = array();

        // Value-based commentary
        if (!empty($extracted['values'])) {
            $val = $extracted['values'][0];
            $parts[] = "This " . $val['formatted'] . " transaction represents significant deal activity.";
        }

        // Percentage-based commentary
        if (!empty($extracted['percentages'])) {
            $pct = $extracted['percentages'][0];
            $parts[] = "The " . $pct . "% figure highlights key market dynamics.";
        }

        // Keyword-based commentary
        if (!empty($extracted['keywords'])) {
            $keyword = $extracted['keywords'][0];
            $parts[] = "This " . $keyword . " activity signals continued strategic positioning in the sector.";
        }

        // Companies mentioned
        if (!empty($extracted['companies'])) {
            $company = $extracted['companies'][0];
            $parts[] = "Market participants including " . $company . " are actively engaged.";
        }

        if (empty($parts)) {
            return $this->generate_fallback_commentary($title);
        }

        return implode(' ', array_slice($parts, 0, 2));
    }

    /**
     * Generate research cards with Claude context
     */
    public function generate_research_cards($post, $category, $entities, $claude_context) {
        $title = get_the_title($post);
        $excerpt = wp_trim_words(wp_strip_all_tags($post->post_content), 80);

        // Get specific research insights from Claude
        $research_prompt = "You are a senior PE analyst at a top-tier firm. For this article, provide research insights:

HEADLINE: {$title}
SUMMARY: {$excerpt}

Respond with JSON only:
{
    \"market_context\": \"2-3 sentences on what this means for the market - be specific to this story\",
    \"key_implications\": [\"Implication 1 specific to this deal/news\", \"Implication 2\", \"Implication 3\"],
    \"watch_items\": [\"What to monitor 1\", \"What to monitor 2\", \"What to monitor 3\"],
    \"outlook\": \"1 sentence forward-looking view specific to this situation\"
}

CRITICAL RULES:
1. NEVER use fake placeholder names like 'Jane Doe', 'John Smith', or 'XYZ Asset Management'
2. ONLY reference companies that are explicitly mentioned in the article. If no specific firms are named, use generic terms like 'a top private equity firm', 'a leading asset manager', 'industry analysts', or 'market participants'
3. Be specific to the article content - no generic statements
4. Use actual market data and real comparable transactions";

        $research_context = $claude_context;

        if ($this->claude_manager && empty($claude_context['market_context'])) {
            $result = $this->claude_manager->send_message($research_prompt, array(), 'market');
            if (is_array($result) && !empty($result['response'])) {
                $json_start = strpos($result['response'], '{');
                $json_end = strrpos($result['response'], '}');
                if ($json_start !== false && $json_end !== false) {
                    $json_str = substr($result['response'], $json_start, $json_end - $json_start + 1);
                    $parsed = json_decode($json_str, true);
                    if ($parsed) {
                        $research_context = $parsed;
                    }
                }
            }
        }

        $cards = array(
            array(
                'title' => 'Market Context',
                'summary' => $research_context['market_context'] ?? 'Strategic development with implications for market participants.',
                'bullets' => $research_context['key_implications'] ?? array(
                    'Monitor competitive positioning',
                    'Assess strategic rationale',
                    'Track execution progress'
                ),
                'cta' => 'Live Intelligence'
            ),
            array(
                'title' => 'What to Watch',
                'summary' => $research_context['outlook'] ?? 'Continued monitoring for strategic developments.',
                'bullets' => $research_context['watch_items'] ?? array(
                    'Follow-on activity',
                    'Competitive response',
                    'Integration progress'
                ),
                'cta' => 'Set Alert'
            )
        );

        return $cards;
    }
}

// Initialize
SFFC_Dynamic_Insights_Generator::get_instance();
