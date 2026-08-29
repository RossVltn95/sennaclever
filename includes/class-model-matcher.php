<?php
/**
 * Model Matcher
 *
 * Matches classified articles to appropriate financial models and provides
 * sector benchmarks and template data for the right panel display.
 *
 * @package SFFC
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Model_Matcher {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Classifier instance
     */
    private $classifier = null;

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
        if (class_exists('SFFC_Article_Classifier')) {
            $this->classifier = SFFC_Article_Classifier::get_instance();
        }
    }

    /**
     * Get matched model data for an article
     *
     * @param int $post_id The post ID
     * @return array Model match data
     */
    public function get_model_match($post_id) {
        // Check for cached classification
        $classification = get_post_meta($post_id, '_sffc_article_classification', true);

        // If no classification, perform it now
        if (empty($classification) && $this->classifier) {
            $post = get_post($post_id);
            if ($post) {
                $classification = $this->classifier->classify(
                    $post_id,
                    $post->post_title,
                    $post->post_content
                );
            }
        }

        if (empty($classification)) {
            return $this->get_default_match();
        }

        // Get model config
        $model_type = $classification['matched_model'] ?? 'dcf';
        $model_config = $this->get_model_config($model_type);

        // Get sector benchmarks
        $sector = $classification['sector'] ?? 'default';
        $benchmarks = $this->get_sector_benchmarks($model_type, $sector);

        // Build match result
        return array(
            'model_type' => $model_type,
            'model_name' => $model_config['model_name'] ?? ucfirst(str_replace('-', ' ', $model_type)) . ' Model',
            'model_description' => $model_config['description'] ?? '',
            'use_cases' => $model_config['use_cases'] ?? array(),
            'sheets' => $model_config['sheets'] ?? array(),
            'classification' => $classification,
            'sector' => $sector,
            'benchmarks' => $benchmarks,
            'data_richness' => $classification['data_richness'] ?? 'none',
            'has_deal_data' => $classification['data_richness'] === 'full',
            'confidence' => $classification['category_confidence'] ?? 0,
        );
    }

    /**
     * Get model configuration
     */
    private function get_model_config($model_type) {
        $config_path = SFFC_PLUGIN_DIR . 'templates/models/' . $model_type . '/config.json';

        if (!file_exists($config_path)) {
            // Try fallback to DCF
            $config_path = SFFC_PLUGIN_DIR . 'templates/models/dcf/config.json';
        }

        if (!file_exists($config_path)) {
            return array();
        }

        return json_decode(file_get_contents($config_path), true) ?: array();
    }

    /**
     * Get sector benchmarks for a model type
     */
    private function get_sector_benchmarks($model_type, $sector) {
        $config = $this->get_model_config($model_type);

        $benchmarks = $config['sector_benchmarks'][$sector]
            ?? $config['sector_benchmarks']['default']
            ?? array();

        // Add location benchmarks for real estate
        if ($model_type === 'commercial-re' && isset($config['location_benchmarks'])) {
            $benchmarks['locations'] = $config['location_benchmarks'];
        }

        return $benchmarks;
    }

    /**
     * Get default match for unclassified articles
     */
    private function get_default_match() {
        return array(
            'model_type' => 'three-statement',
            'model_name' => 'Three-Statement Financial Model',
            'model_description' => 'Integrated financial statements with projections and assumptions',
            'use_cases' => array('Financial analysis', 'Projection modeling'),
            'sheets' => array(),
            'classification' => null,
            'sector' => 'default',
            'benchmarks' => array(), // No hardcoded benchmarks - use contextual data only
            'data_richness' => 'none',
            'has_deal_data' => false,
            'confidence' => 0,
        );
    }

    /**
     * Get display data for the right panel
     *
     * @param int $post_id The post ID
     * @return array Display-ready data
     */
    public function get_panel_display_data($post_id) {
        $match = $this->get_model_match($post_id);
        $post = get_post($post_id);

        // Get any existing deal financials
        $deal_financials = get_post_meta($post_id, '_sffc_deal_financials', true);

        // Check for real data - including funding and valuation for fundraising articles
        $has_real_data = !empty($deal_financials) && (
            !empty($deal_financials['deal_value']['amount']) ||
            !empty($deal_financials['funding_amount']['amount']) ||
            !empty($deal_financials['valuation']['amount'])
        );

        // Build display metrics and contextual info
        $metrics = array();
        $contextual_model = null;
        $data_source = 'Market Data';
        $model_title = $match['model_name'];

        if ($has_real_data) {
            // Use real deal data from article
            $metrics = $this->build_real_metrics($deal_financials);

            // Set data source label
            if (!empty($deal_financials['funding_amount']['amount'])) {
                $data_source = 'Disclosed Funding';
            } elseif (!empty($deal_financials['valuation']['amount'])) {
                $data_source = 'Disclosed Valuation';
            } else {
                $data_source = 'Disclosed Deal';
            }
        } else {
            // Use CONTEXTUAL market data - NOT fake hardcoded data
            if (class_exists('SFFC_Market_Data') && $post) {
                $market_data = SFFC_Market_Data::get_instance();
                $contextual_model = $market_data->build_contextual_model(
                    $post_id,
                    $post->post_title,
                    $post->post_content
                );

                if ($contextual_model && !empty($contextual_model['metrics'])) {
                    $metrics = $contextual_model['metrics'];
                    $model_title = $contextual_model['model_title'];
                    $data_source = $contextual_model['source'] ?? 'Market Research';

                    // Override model type if contextual analysis found a better match
                    if (!empty($contextual_model['model_type'])) {
                        $match['model_type'] = $contextual_model['model_type'];
                    }
                }
            }

            // If still no metrics, show minimal info (no fake data)
            if (empty($metrics)) {
                $metrics = array(
                    array(
                        'value' => 'Analysis Available',
                        'label' => 'Model Type',
                        'sub' => $match['model_name'],
                        'type' => 'info',
                    ),
                );
                $data_source = 'Generate for Details';
            }
        }

        // Get company name from deal financials or post title
        $company_name = $deal_financials['parties']['target']['name'] ?? null;
        if (!$company_name && $post) {
            $company_name = $this->extract_company_from_title($post->post_title);
        }

        // Build classification with geographic context if available
        $classification = array(
            'category' => $match['classification']['primary_category'] ?? 'general',
            'sector' => $match['sector'],
            'confidence' => $match['confidence'],
            'deal_type' => $match['classification']['deal_type'] ?? null,
        );

        // Add geography from contextual model if available
        if (!empty($contextual_model['geography'])) {
            $geo = $contextual_model['geography'];
            if (!empty($geo['city'])) {
                $classification['geography'] = $geo['city'];
            } elseif (!empty($geo['country'])) {
                $classification['geography'] = $geo['country'];
            }
        }

        return array(
            'model' => array(
                'type' => $match['model_type'],
                'name' => $model_title,
                'description' => $match['model_description'],
                'icon' => $this->get_model_icon($match['model_type']),
            ),
            'classification' => $classification,
            'data_quality' => array(
                'richness' => $match['data_richness'],
                'has_real_data' => $has_real_data,
                'label' => $this->get_data_quality_label($match['data_richness']),
                'source' => $data_source,
            ),
            'contextual' => $contextual_model,
            'company_name' => $company_name,
            'metrics' => $metrics,
            'benchmarks' => $match['benchmarks'],
            'cta' => array(
                'text' => 'Generate Analysis',
                'subtext' => $has_real_data
                    ? 'Model with disclosed data + AI research'
                    : 'Generate ' . $model_title,
            ),
            'available_downloads' => array(
                'excel' => true,
                'pdf' => true,
            ),
        );
    }

    /**
     * Extract company name from article title
     */
    private function extract_company_from_title($title) {
        // Pattern: "Company Name Raises/Raises/etc"
        if (preg_match('/^([A-Z][A-Za-z0-9\s]+(?:Labs?|Inc\.?|Corp\.?)?)\s+(?:Raises|Secures|Closes|Announces)/i', $title, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Build metrics from real deal data
     */
    private function build_real_metrics($deal_financials) {
        $metrics = array();

        // Funding Amount (for fundraising articles)
        if (!empty($deal_financials['funding_amount']['amount'])) {
            $round_type = $deal_financials['funding_amount']['round_type'] ?? '';
            $metrics[] = array(
                'value' => $this->format_currency(
                    $deal_financials['funding_amount']['amount'],
                    $deal_financials['funding_amount']['currency'] ?? 'USD'
                ),
                'label' => $round_type ? $round_type . ' Funding' : 'Funding Raised',
                'sub' => 'Disclosed',
                'type' => 'currency',
            );
        }

        // Valuation (for fundraising articles)
        if (!empty($deal_financials['valuation']['amount'])) {
            $val_type = $deal_financials['valuation']['valuation_type'] ?? 'post-money';
            $metrics[] = array(
                'value' => $this->format_currency(
                    $deal_financials['valuation']['amount'],
                    $deal_financials['valuation']['currency'] ?? 'USD'
                ),
                'label' => ucfirst($val_type) . ' Valuation',
                'sub' => 'Disclosed',
                'type' => 'currency',
            );
        }

        // Deal Value (for M&A articles) - only show if we don't have funding_amount
        if (empty($deal_financials['funding_amount']['amount']) && !empty($deal_financials['deal_value']['amount'])) {
            $metrics[] = array(
                'value' => $this->format_currency(
                    $deal_financials['deal_value']['amount'],
                    $deal_financials['deal_value']['currency'] ?? 'USD'
                ),
                'label' => 'Deal Value',
                'sub' => $deal_financials['deal_value']['source'] ?? 'Disclosed',
                'type' => 'currency',
            );
        }

        // Implied Dilution (calculate if we have funding and valuation)
        if (!empty($deal_financials['funding_amount']['amount']) && !empty($deal_financials['valuation']['amount'])) {
            $dilution = ($deal_financials['funding_amount']['amount'] / $deal_financials['valuation']['amount']) * 100;
            $metrics[] = array(
                'value' => number_format($dilution, 1) . '%',
                'label' => 'Implied Dilution',
                'sub' => 'Calculated',
                'type' => 'percentage',
            );
        }

        // Net Proceeds
        if (!empty($deal_financials['net_proceeds']['amount'])) {
            $metrics[] = array(
                'value' => $this->format_currency(
                    $deal_financials['net_proceeds']['amount'],
                    $deal_financials['net_proceeds']['currency'] ?? 'USD'
                ),
                'label' => 'Net Proceeds',
                'sub' => '',
                'type' => 'currency',
            );
        }

        // Multiple
        if (!empty($deal_financials['multiples']['disclosed'])) {
            foreach ($deal_financials['multiples']['disclosed'] as $m) {
                if (!empty($m['value'])) {
                    $metrics[] = array(
                        'value' => number_format($m['value'], 1) . 'x',
                        'label' => $m['type'] ?? 'EV/EBITDA',
                        'sub' => '',
                        'type' => 'multiple',
                    );
                    break;
                }
            }
        }

        // Operating Profit or EBITDA
        if (!empty($deal_financials['target_financials']['operating_profit']['amount'])) {
            $metrics[] = array(
                'value' => $this->format_currency(
                    $deal_financials['target_financials']['operating_profit']['amount'],
                    $deal_financials['target_financials']['operating_profit']['currency'] ?? 'USD'
                ),
                'label' => 'Operating Profit',
                'sub' => '',
                'type' => 'currency',
            );
        } elseif (!empty($deal_financials['target_financials']['ebitda']['amount'])) {
            $metrics[] = array(
                'value' => $this->format_currency(
                    $deal_financials['target_financials']['ebitda']['amount'],
                    $deal_financials['target_financials']['ebitda']['currency'] ?? 'USD'
                ),
                'label' => 'EBITDA',
                'sub' => '',
                'type' => 'currency',
            );
        }

        // AI-estimated revenue if available
        if (!empty($deal_financials['ai_enhanced']['estimated_financials']['revenue']['amount'])) {
            $metrics[] = array(
                'value' => $this->format_currency(
                    $deal_financials['ai_enhanced']['estimated_financials']['revenue']['amount'],
                    $deal_financials['deal_value']['currency'] ?? 'USD'
                ),
                'label' => 'Est. Revenue',
                'sub' => 'AI Estimated',
                'type' => 'currency',
            );
        }

        return array_slice($metrics, 0, 4);
    }

    /**
     * Format currency value
     */
    private function format_currency($amount, $currency = 'USD') {
        $symbol = array(
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
        )[$currency] ?? '$';

        if ($amount >= 1000) {
            return $symbol . number_format($amount / 1000, 1) . 'bn';
        } elseif ($amount >= 1) {
            return $symbol . number_format($amount, 0) . 'm';
        } else {
            return $symbol . number_format($amount * 1000, 0) . 'k';
        }
    }

    /**
     * Get model icon SVG
     */
    private function get_model_icon($model_type) {
        $icons = array(
            'lbo' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
            'merger' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="9" r="5"/><circle cx="15" cy="15" r="5"/></svg>',
            'dcf' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>',
            'three-statement' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>',
            'commercial-re' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l8-4 8 4v14"/><path d="M9 21v-4h6v4"/></svg>',
            'ipo' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
            'fund' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v12M8 10h8M8 14h8"/></svg>',
            'debt' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>',
            'restructuring' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        );

        return $icons[$model_type] ?? $icons['dcf'];
    }

    /**
     * Get data quality label
     */
    private function get_data_quality_label($richness) {
        $labels = array(
            'full' => 'Disclosed Data',
            'partial' => 'Partial Data',
            'none' => 'Sector Benchmarks',
        );

        return $labels[$richness] ?? 'Template';
    }

    /**
     * Check if article needs classification
     */
    public function needs_classification($post_id) {
        $classification = get_post_meta($post_id, '_sffc_article_classification', true);
        return empty($classification);
    }

    /**
     * Force reclassification of an article
     */
    public function reclassify($post_id) {
        if (!$this->classifier) {
            return false;
        }

        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        return $this->classifier->classify(
            $post_id,
            $post->post_title,
            $post->post_content
        );
    }
}
