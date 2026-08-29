<?php
/**
 * Model Generator
 *
 * Handles on-demand generation of financial analysis models.
 * Integrates with Claude API to generate analysis and streams Excel/PDF files.
 *
 * @package SFFC
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Model_Generator {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Classifier instance
     */
    private $classifier = null;

    /**
     * Matcher instance
     */
    private $matcher = null;

    /**
     * Cost manager instance
     */
    private $cost_manager = null;

    /**
     * Schema instance
     */
    private $schema = null;

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
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Auto-classify on post save/publish
        add_action('save_post', array($this, 'auto_classify_on_save'), 20, 3);

        // AJAX handlers for generation
        add_action('wp_ajax_sffc_generate_model', array($this, 'ajax_generate_model'));
        add_action('wp_ajax_nopriv_sffc_generate_model', array($this, 'ajax_generate_model_guest'));

        // AJAX handlers for downloads
        add_action('wp_ajax_sffc_download_excel', array($this, 'ajax_download_excel'));
        add_action('wp_ajax_nopriv_sffc_download_excel', array($this, 'ajax_download_excel_guest'));

        add_action('wp_ajax_sffc_download_pdf', array($this, 'ajax_download_pdf'));
        add_action('wp_ajax_nopriv_sffc_download_pdf', array($this, 'ajax_download_pdf_guest'));

        // REST API endpoint for generation status
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Get classifier instance
     */
    private function get_classifier() {
        if ($this->classifier === null) {
            if (!class_exists('SFFC_Article_Classifier')) {
                require_once SFFC_PLUGIN_DIR . 'includes/class-article-classifier.php';
            }
            $this->classifier = SFFC_Article_Classifier::get_instance();
        }
        return $this->classifier;
    }

    /**
     * Get matcher instance
     */
    private function get_matcher() {
        if ($this->matcher === null) {
            if (!class_exists('SFFC_Model_Matcher')) {
                require_once SFFC_PLUGIN_DIR . 'includes/class-model-matcher.php';
            }
            $this->matcher = SFFC_Model_Matcher::get_instance();
        }
        return $this->matcher;
    }

    /**
     * Get file streamer instance
     */
    private function get_file_streamer() {
        if (!class_exists('SFFC_File_Streamer')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-file-streamer.php';
        }
        return SFFC_File_Streamer::get_instance();
    }

    /**
     * Get cost manager instance
     */
    private function get_cost_manager() {
        if ($this->cost_manager === null) {
            if (!class_exists('SFFC_API_Cost_Manager')) {
                require_once SFFC_PLUGIN_DIR . 'includes/class-api-cost-manager.php';
            }
            $this->cost_manager = SFFC_API_Cost_Manager::get_instance();
        }
        return $this->cost_manager;
    }

    /**
     * Get schema instance
     */
    private function get_schema() {
        if ($this->schema === null) {
            if (!class_exists('SFFC_Intelligence_Schema')) {
                require_once SFFC_PLUGIN_DIR . 'includes/class-intelligence-schema.php';
            }
            $this->schema = SFFC_Intelligence_Schema::get_instance();
        }
        return $this->schema;
    }

    /**
     * Auto-classify article on save/publish
     *
     * @param int     $post_id Post ID
     * @param WP_Post $post    Post object
     * @param bool    $update  Whether this is an update
     */
    public function auto_classify_on_save($post_id, $post, $update) {
        // Skip autosaves
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Skip revisions
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // Only process posts (or custom post types as needed)
        if ($post->post_type !== 'post') {
            return;
        }

        // Only process published posts or posts being published
        if ($post->post_status !== 'publish') {
            return;
        }

        $cost_manager = $this->get_cost_manager();

        // Check if classification should be skipped (content unchanged)
        if ($cost_manager->should_skip_classification($post_id)) {
            return; // Use cached classification
        }

        // Check quality threshold
        $quality = $cost_manager->check_quality_threshold($post_id);
        if (!$quality['passes']) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[SFFC] Skipping classification for post %d: %s', $post_id, $quality['reason']));
            }
            return;
        }

        // Check API budget
        $can_classify = $cost_manager->can_perform_operation('classification', $post_id, 0);
        if (!$can_classify['allowed']) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[SFFC] Classification budget exceeded for post %d: %s', $post_id, $can_classify['reason']));
            }
            // Queue for batch processing if budget exceeded
            if ($can_classify['code'] === 'daily_budget_exceeded' || $can_classify['code'] === 'monthly_budget_exceeded') {
                $cost_manager->queue_for_batch($post_id, 'classification');
            }
            return;
        }

        // Perform full classification
        $classifier = $this->get_classifier();
        $classification = $classifier->full_classify($post_id, $post->post_title, $post->post_content);

        // Store content hash for future change detection
        $cost_manager->store_content_hash($post_id);

        // Record API cost
        $cost_manager->record_api_cost('classification', $post_id);

        // Log classification for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[SFFC] Auto-classified post %d: %s (model: %s, confidence: %d%%)',
                $post_id,
                $classification['primary_category'],
                $classification['matched_model'],
                $classification['category_confidence']
            ));
        }
    }

    /**
     * AJAX handler for model generation (logged in users)
     */
    public function ajax_generate_model() {
        check_ajax_referer('sffc_model_generation_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $model_type = isset($_POST['model_type']) ? sanitize_text_field($_POST['model_type']) : '';

        if (!$post_id || !$model_type) {
            wp_send_json_error(array('message' => 'Invalid parameters'));
        }

        $user_id = get_current_user_id();
        $cost_manager = $this->get_cost_manager();

        // Check all limits and constraints via cost manager
        $can_generate = $cost_manager->can_perform_operation('generation', $post_id, $user_id);

        if (!$can_generate['allowed']) {
            // Check if we should use fallback
            if ($can_generate['code'] === 'daily_budget_exceeded' ||
                $can_generate['code'] === 'monthly_budget_exceeded') {
                // Return template fallback instead of error
                $fallback = $cost_manager->get_template_fallback($post_id, $model_type);
                wp_send_json_success(array(
                    'status' => 'fallback',
                    'analysis' => $fallback,
                    'message' => 'Using template defaults. Personalized analysis temporarily unavailable.',
                ));
            }

            wp_send_json_error(array(
                'message' => $can_generate['reason'],
                'code' => $can_generate['code'],
                'cooldown_hours' => $can_generate['cooldown_hours'] ?? null,
            ));
        }

        // Generate the analysis
        $result = $this->generate_analysis($post_id, $model_type, $user_id);

        if (is_wp_error($result)) {
            // On API error, return fallback
            $fallback = $cost_manager->get_template_fallback($post_id, $model_type);
            wp_send_json_success(array(
                'status' => 'fallback',
                'analysis' => $fallback,
                'message' => 'Using template defaults due to an error.',
                'error' => $result->get_error_message(),
            ));
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX handler for model generation (guests)
     */
    public function ajax_generate_model_guest() {
        check_ajax_referer('sffc_model_generation_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $model_type = isset($_POST['model_type']) ? sanitize_text_field($_POST['model_type']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        if (!$post_id || !$model_type) {
            wp_send_json_error(array('message' => 'Invalid parameters'));
        }

        // Require email for guest generation
        if (!$email || !is_email($email)) {
            wp_send_json_error(array(
                'message' => 'Please provide a valid email to download.',
                'code' => 'email_required',
            ));
        }

        $cost_manager = $this->get_cost_manager();

        // Check budget constraints (guests have user_id = 0)
        $can_generate = $cost_manager->can_perform_operation('generation', $post_id, 0);

        if (!$can_generate['allowed'] && $can_generate['code'] !== 'cached') {
            // Return fallback for budget issues
            if ($can_generate['code'] === 'daily_budget_exceeded' ||
                $can_generate['code'] === 'monthly_budget_exceeded') {
                $fallback = $cost_manager->get_template_fallback($post_id, $model_type);
                // Still capture the lead
                $this->capture_lead($email, $post_id, $model_type);
                wp_send_json_success(array(
                    'status' => 'fallback',
                    'analysis' => $fallback,
                    'message' => 'Using template defaults. Personalized analysis temporarily unavailable.',
                ));
            }

            wp_send_json_error(array(
                'message' => $can_generate['reason'],
                'code' => $can_generate['code'],
            ));
        }

        // Store email for lead capture
        $this->capture_lead($email, $post_id, $model_type);

        // Generate the analysis
        $result = $this->generate_analysis($post_id, $model_type, 0);

        if (is_wp_error($result)) {
            // Return fallback on error
            $fallback = $cost_manager->get_template_fallback($post_id, $model_type);
            wp_send_json_success(array(
                'status' => 'fallback',
                'analysis' => $fallback,
                'message' => 'Using template defaults due to an error.',
            ));
        }

        wp_send_json_success($result);
    }

    /**
     * Generate analysis for an article
     *
     * @param int    $post_id    Post ID
     * @param string $model_type Model type
     * @param int    $user_id    User ID (0 for guests)
     * @return array|WP_Error
     */
    public function generate_analysis($post_id, $model_type, $user_id = null) {
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('invalid_post', 'Post not found');
        }

        // Default to current user if not specified
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        $cost_manager = $this->get_cost_manager();
        $schema = $this->get_schema();

        // Check if we already have a recent generation
        $existing = get_post_meta($post_id, '_sffc_generated_analysis', true);
        $generation_time = get_post_meta($post_id, '_sffc_generation_timestamp', true);

        // If generated within last 24 hours, return cached version
        if ($existing && $generation_time && (time() - strtotime($generation_time)) < 86400) {
            return array(
                'status' => 'cached',
                'analysis' => $existing,
                'generated_at' => $generation_time,
            );
        }

        // Get classification data
        $classification = get_post_meta($post_id, '_sffc_article_classification', true);
        if (empty($classification)) {
            $classifier = $this->get_classifier();
            $classification = $classifier->full_classify($post_id, $post->post_title, $post->post_content);
        }

        // Get entities
        $entities = get_post_meta($post_id, '_sffc_extracted_entities', true);
        if (empty($entities)) {
            $classifier = $this->get_classifier();
            $entities = $classifier->extract_entities($post->post_title, $post->post_content);
            update_post_meta($post_id, '_sffc_extracted_entities', $entities);
        }

        // Get model config
        $config_path = SFFC_PLUGIN_DIR . 'templates/models/' . $model_type . '/config.json';
        if (!file_exists($config_path)) {
            return new WP_Error('invalid_model', 'Model type not found');
        }
        $model_config = json_decode(file_get_contents($config_path), true);

        // Enrich config with sector benchmarks from schema
        $sector = $classification['sector'] ?? 'private_equity';
        $model_config['sector_benchmarks'][$sector] = $schema->get_sector_benchmarks($sector);

        // Add LBO/M&A specific benchmarks if applicable
        if ($model_type === 'lbo') {
            $model_config['lbo_benchmarks'] = $schema->get_lbo_benchmarks();
        } elseif (in_array($model_type, array('merger', 'ma'))) {
            $model_config['ma_benchmarks'] = $schema->get_ma_benchmarks();
        } elseif (in_array($model_type, array('commercial-re', 'cre'))) {
            $property_type = $classification['property_type'] ?? 'office';
            $model_config['cre_benchmarks'] = $schema->get_cre_benchmarks($property_type);
        }

        // Build the prompt for Claude
        $prompt = $this->build_generation_prompt($post, $classification, $entities, $model_config);

        // Call Claude API
        $analysis = $this->call_claude_api($prompt, $model_type);

        if (is_wp_error($analysis)) {
            return $analysis;
        }

        // Store the generated analysis
        update_post_meta($post_id, '_sffc_generated_analysis', $analysis);
        update_post_meta($post_id, '_sffc_generation_timestamp', current_time('mysql'));
        update_post_meta($post_id, '_sffc_generated_model_type', $model_type);

        // Track generation via schema
        $schema->track_generation($post_id, $model_type);

        // Record API cost
        $cost_manager->record_api_cost('generation', $post_id);

        // Increment user generation count
        if ($user_id > 0) {
            $schema->increment_user_generation_count($user_id);
        }

        return array(
            'status' => 'generated',
            'analysis' => $analysis,
            'generated_at' => current_time('mysql'),
            'remaining_generations' => $schema->get_remaining_generations($user_id),
        );
    }

    /**
     * Build the generation prompt for Claude
     */
    private function build_generation_prompt($post, $classification, $entities, $model_config) {
        $model_type = $model_config['model_type'];
        $sector = $classification['sector'] ?? 'general';

        // Get sector benchmarks
        $benchmarks = $model_config['sector_benchmarks'][$sector]
            ?? $model_config['sector_benchmarks']['default']
            ?? array();

        // Build entity summary
        $entity_summary = '';
        if (!empty($entities['companies'])) {
            $companies = array_column($entities['companies'], 'name');
            $entity_summary .= "Companies mentioned: " . implode(', ', $companies) . "\n";
        }
        if (!empty($entities['monetary_values'])) {
            $values = array_map(function($v) {
                return $v['raw'] . ' (' . $v['context'] . ')';
            }, array_slice($entities['monetary_values'], 0, 5));
            $entity_summary .= "Financial values: " . implode(', ', $values) . "\n";
        }
        if (!empty($entities['multiples'])) {
            $multiples = array_map(function($m) {
                return $m['raw'];
            }, $entities['multiples']);
            $entity_summary .= "Valuation multiples: " . implode(', ', $multiples) . "\n";
        }

        // Build the prompt based on model type
        $prompt = $this->get_model_prompt_template($model_type);

        // Replace placeholders
        $prompt = str_replace('{ARTICLE_TITLE}', $post->post_title, $prompt);
        $prompt = str_replace('{ARTICLE_CONTENT}', wp_strip_all_tags($post->post_content), $prompt);
        $prompt = str_replace('{SECTOR}', ucfirst(str_replace('_', ' ', $sector)), $prompt);
        $prompt = str_replace('{ENTITY_SUMMARY}', $entity_summary, $prompt);
        $prompt = str_replace('{BENCHMARKS}', json_encode($benchmarks, JSON_PRETTY_PRINT), $prompt);
        $prompt = str_replace('{INPUT_FIELDS}', json_encode(array_keys($model_config['input_mappings'] ?? array())), $prompt);

        return $prompt;
    }

    /**
     * Get prompt template for a model type
     */
    private function get_model_prompt_template($model_type) {
        $templates = array(
            'lbo' => $this->get_lbo_prompt(),
            'merger' => $this->get_merger_prompt(),
            'dcf' => $this->get_dcf_prompt(),
            'three-statement' => $this->get_three_statement_prompt(),
            'commercial-re' => $this->get_commercial_re_prompt(),
        );

        return $templates[$model_type] ?? $templates['dcf'];
    }

    /**
     * LBO model prompt template
     */
    private function get_lbo_prompt() {
        return <<<PROMPT
You are a financial analyst creating an LBO (Leveraged Buyout) model analysis.

ARTICLE TITLE: {ARTICLE_TITLE}

ARTICLE CONTENT:
{ARTICLE_CONTENT}

SECTOR: {SECTOR}

EXTRACTED DATA:
{ENTITY_SUMMARY}

SECTOR BENCHMARKS:
{BENCHMARKS}

Based on the article and available data, generate a JSON response with the following structure:

```json
{
  "summary": "2-3 sentence executive summary of the deal and analysis",
  "inputs": {
    "target_name": "Company name",
    "deal_value": {"amount": number_in_millions, "currency": "USD/GBP/EUR"},
    "ebitda": {"amount": number_in_millions, "currency": "USD/GBP/EUR", "estimated": true/false},
    "entry_multiple": number,
    "equity_percentage": number (0-100),
    "debt_percentage": number (0-100),
    "senior_debt_multiple": number,
    "interest_rate": number (percentage),
    "hold_period": number (years)
  },
  "scenarios": {
    "downside": {"irr": number, "moic": number, "exit_multiple": number},
    "base": {"irr": number, "moic": number, "exit_multiple": number},
    "upside": {"irr": number, "moic": number, "exit_multiple": number}
  },
  "key_drivers": ["driver1", "driver2", "driver3"],
  "risks": [
    {"description": "risk description", "severity": "high/medium/low"}
  ],
  "data_confidence": "high/medium/low",
  "notes": "Any assumptions or caveats"
}
```

If specific data is not available in the article, use sector benchmarks or reasonable estimates and mark them as estimated. Focus on providing actionable financial analysis.
PROMPT;
    }

    /**
     * M&A/Merger model prompt template
     */
    private function get_merger_prompt() {
        return <<<PROMPT
You are a financial analyst creating an M&A (Merger & Acquisition) model analysis.

ARTICLE TITLE: {ARTICLE_TITLE}

ARTICLE CONTENT:
{ARTICLE_CONTENT}

SECTOR: {SECTOR}

EXTRACTED DATA:
{ENTITY_SUMMARY}

SECTOR BENCHMARKS:
{BENCHMARKS}

Based on the article and available data, generate a JSON response with the following structure:

```json
{
  "summary": "2-3 sentence executive summary of the transaction",
  "inputs": {
    "acquirer_name": "Company name",
    "target_name": "Company name",
    "deal_value": {"amount": number_in_millions, "currency": "USD/GBP/EUR"},
    "premium_paid": number (percentage),
    "cash_percentage": number (0-100),
    "stock_percentage": number (0-100),
    "target_revenue": {"amount": number_in_millions, "estimated": true/false},
    "target_ebitda": {"amount": number_in_millions, "estimated": true/false},
    "ev_revenue_multiple": number,
    "ev_ebitda_multiple": number
  },
  "synergies": {
    "cost_synergies": {"amount": number_in_millions, "realization_years": number},
    "revenue_synergies": {"amount": number_in_millions, "realization_years": number}
  },
  "accretion_dilution": {
    "year_1": {"eps_impact": number, "accretive": true/false},
    "year_2": {"eps_impact": number, "accretive": true/false}
  },
  "strategic_rationale": ["reason1", "reason2", "reason3"],
  "risks": [
    {"description": "risk description", "severity": "high/medium/low"}
  ],
  "data_confidence": "high/medium/low",
  "notes": "Any assumptions or caveats"
}
```

If specific data is not available in the article, use sector benchmarks or reasonable estimates and mark them as estimated.
PROMPT;
    }

    /**
     * DCF model prompt template
     */
    private function get_dcf_prompt() {
        return <<<PROMPT
You are a financial analyst creating a DCF (Discounted Cash Flow) valuation analysis.

ARTICLE TITLE: {ARTICLE_TITLE}

ARTICLE CONTENT:
{ARTICLE_CONTENT}

SECTOR: {SECTOR}

EXTRACTED DATA:
{ENTITY_SUMMARY}

SECTOR BENCHMARKS:
{BENCHMARKS}

Based on the article and available data, generate a JSON response with the following structure:

```json
{
  "summary": "2-3 sentence executive summary of the valuation",
  "inputs": {
    "company_name": "Company name",
    "ltm_revenue": {"amount": number_in_millions, "currency": "USD/GBP/EUR", "estimated": true/false},
    "ltm_ebitda": {"amount": number_in_millions, "estimated": true/false},
    "revenue_growth": [number, number, number, number, number],
    "ebitda_margin": number (percentage),
    "tax_rate": number (percentage),
    "capex_percent": number (percentage of revenue),
    "nwc_percent": number (percentage of revenue),
    "wacc": number (percentage),
    "terminal_growth": number (percentage),
    "net_debt": {"amount": number_in_millions, "estimated": true/false},
    "shares_outstanding": number_in_millions
  },
  "valuation": {
    "enterprise_value": number_in_millions,
    "equity_value": number_in_millions,
    "implied_share_price": number,
    "implied_ev_ebitda": number,
    "implied_ev_revenue": number
  },
  "sensitivity": {
    "wacc_range": [number, number],
    "growth_range": [number, number],
    "value_range": [number, number]
  },
  "key_assumptions": ["assumption1", "assumption2", "assumption3"],
  "data_confidence": "high/medium/low",
  "notes": "Any assumptions or caveats"
}
```

If specific data is not available in the article, use sector benchmarks or reasonable estimates and mark them as estimated.
PROMPT;
    }

    /**
     * Three-Statement model prompt template
     */
    private function get_three_statement_prompt() {
        return <<<PROMPT
You are a financial analyst creating a Three-Statement financial model analysis.

ARTICLE TITLE: {ARTICLE_TITLE}

ARTICLE CONTENT:
{ARTICLE_CONTENT}

SECTOR: {SECTOR}

EXTRACTED DATA:
{ENTITY_SUMMARY}

SECTOR BENCHMARKS:
{BENCHMARKS}

Based on the article and available data, generate a JSON response with the following structure:

```json
{
  "summary": "2-3 sentence executive summary",
  "inputs": {
    "company_name": "Company name",
    "currency": "USD/GBP/EUR",
    "base_year_revenue": number_in_millions,
    "revenue_growth": [number, number, number],
    "gross_margin": number (percentage),
    "sga_percent": number (percentage),
    "rd_percent": number (percentage),
    "da_percent": number (percentage),
    "interest_rate": number (percentage),
    "tax_rate": number (percentage),
    "ar_days": number,
    "inventory_days": number,
    "ap_days": number,
    "capex_percent": number (percentage),
    "base_year_cash": number_in_millions,
    "base_year_debt": number_in_millions
  },
  "projections": {
    "year_1": {
      "revenue": number,
      "ebitda": number,
      "net_income": number,
      "free_cash_flow": number
    },
    "year_2": {
      "revenue": number,
      "ebitda": number,
      "net_income": number,
      "free_cash_flow": number
    },
    "year_3": {
      "revenue": number,
      "ebitda": number,
      "net_income": number,
      "free_cash_flow": number
    }
  },
  "ratios": {
    "ebitda_margin": number,
    "net_margin": number,
    "roe": number,
    "debt_to_ebitda": number
  },
  "key_drivers": ["driver1", "driver2", "driver3"],
  "data_confidence": "high/medium/low",
  "notes": "Any assumptions or caveats"
}
```

If specific data is not available in the article, use sector benchmarks or reasonable estimates.
PROMPT;
    }

    /**
     * Commercial RE model prompt template
     */
    private function get_commercial_re_prompt() {
        return <<<PROMPT
You are a financial analyst creating a Commercial Real Estate valuation analysis.

ARTICLE TITLE: {ARTICLE_TITLE}

ARTICLE CONTENT:
{ARTICLE_CONTENT}

SECTOR: Real Estate

EXTRACTED DATA:
{ENTITY_SUMMARY}

SECTOR BENCHMARKS:
{BENCHMARKS}

Based on the article and available data, generate a JSON response with the following structure:

```json
{
  "summary": "2-3 sentence executive summary of the property/transaction",
  "inputs": {
    "property_name": "Property name or address",
    "property_type": "Office/Industrial/Retail/Multifamily",
    "property_class": "A/B/C",
    "total_sqft": number,
    "leasable_sqft": number,
    "year_built": number,
    "occupancy_rate": number (percentage),
    "purchase_price": {"amount": number_in_millions, "currency": "GBP/USD/EUR", "estimated": true/false},
    "avg_rent_psf": number,
    "market_rent_psf": number,
    "walt_years": number,
    "vacancy_allowance": number (percentage),
    "operating_expense_ratio": number (percentage),
    "cap_rate": number (percentage),
    "exit_cap_rate": number (percentage),
    "hold_period_years": number
  },
  "valuation": {
    "noi": number_in_millions,
    "implied_value": number_in_millions,
    "price_per_sqft": number,
    "unlevered_irr": number,
    "levered_irr": number,
    "equity_multiple": number
  },
  "market_context": {
    "submarket": "Location description",
    "market_vacancy": number (percentage),
    "market_rent_growth": number (percentage),
    "comparable_cap_rates": [number, number, number]
  },
  "key_considerations": ["consideration1", "consideration2", "consideration3"],
  "risks": [
    {"description": "risk description", "severity": "high/medium/low"}
  ],
  "data_confidence": "high/medium/low",
  "notes": "Any assumptions or caveats"
}
```

Focus on UK commercial real estate metrics and market context if applicable.
PROMPT;
    }

    /**
     * Call Claude API for generation
     */
    private function call_claude_api($prompt, $model_type) {
        // Get API key from options
        $api_key = get_option('sffc_claude_api_key', '');

        if (empty($api_key)) {
            // Return mock data for testing if no API key
            return $this->get_mock_analysis($model_type);
        }

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
            ),
            'body' => json_encode(array(
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => 2000,
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => $prompt,
                    ),
                ),
            )),
            'timeout' => 60,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('api_error', $body['error']['message'] ?? 'API error');
        }

        // Extract JSON from response
        $content = $body['content'][0]['text'] ?? '';

        // Parse JSON from response
        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $json_str = $matches[1];
        } else {
            $json_str = $content;
        }

        $analysis = json_decode($json_str, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('parse_error', 'Failed to parse analysis response');
        }

        return $analysis;
    }

    /**
     * Get mock analysis for testing without API
     */
    private function get_mock_analysis($model_type) {
        $mocks = array(
            'lbo' => array(
                'summary' => 'This leveraged buyout analysis examines the transaction based on available data and sector benchmarks.',
                'inputs' => array(
                    'target_name' => 'Target Company',
                    'deal_value' => array('amount' => 500, 'currency' => 'GBP'),
                    'ebitda' => array('amount' => 50, 'currency' => 'GBP', 'estimated' => true),
                    'entry_multiple' => 10.0,
                    'equity_percentage' => 40,
                    'debt_percentage' => 60,
                    'senior_debt_multiple' => 4.5,
                    'interest_rate' => 7.5,
                    'hold_period' => 5,
                ),
                'scenarios' => array(
                    'downside' => array('irr' => 12, 'moic' => 1.8, 'exit_multiple' => 8.5),
                    'base' => array('irr' => 20, 'moic' => 2.5, 'exit_multiple' => 10.0),
                    'upside' => array('irr' => 28, 'moic' => 3.2, 'exit_multiple' => 12.0),
                ),
                'key_drivers' => array('Revenue growth', 'Margin expansion', 'Debt paydown'),
                'risks' => array(
                    array('description' => 'Integration execution risk', 'severity' => 'medium'),
                    array('description' => 'Market conditions', 'severity' => 'medium'),
                ),
                'data_confidence' => 'medium',
                'notes' => 'Analysis based on sector benchmarks where specific data not disclosed.',
            ),
            'commercial-re' => array(
                'summary' => 'Commercial real estate analysis based on market benchmarks and available property data.',
                'inputs' => array(
                    'property_name' => 'Subject Property',
                    'property_type' => 'Office',
                    'property_class' => 'A',
                    'total_sqft' => 250000,
                    'occupancy_rate' => 92,
                    'avg_rent_psf' => 55,
                    'cap_rate' => 5.5,
                    'hold_period_years' => 7,
                ),
                'valuation' => array(
                    'noi' => 12.5,
                    'implied_value' => 227,
                    'price_per_sqft' => 908,
                    'unlevered_irr' => 8.5,
                    'levered_irr' => 12.5,
                    'equity_multiple' => 1.9,
                ),
                'market_context' => array(
                    'submarket' => 'City of London',
                    'market_vacancy' => 8,
                    'market_rent_growth' => 2.5,
                ),
                'data_confidence' => 'medium',
                'notes' => 'Analysis uses UK office market benchmarks.',
            ),
        );

        return $mocks[$model_type] ?? $mocks['lbo'];
    }

    /**
     * Check user generation limit
     */
    private function check_generation_limit($user_id) {
        if (!$user_id) {
            return true; // Guests handled separately
        }

        // Premium users have unlimited
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        // Check monthly limit for free users
        $monthly_limit = 3;
        $current_month = date('Y-m');
        $generation_count = get_user_meta($user_id, '_sffc_generations_' . $current_month, true);

        return intval($generation_count) < $monthly_limit;
    }

    /**
     * Track generation for analytics
     */
    private function track_generation($post_id, $model_type) {
        $user_id = get_current_user_id();

        if ($user_id) {
            $current_month = date('Y-m');
            $count = get_user_meta($user_id, '_sffc_generations_' . $current_month, true);
            update_user_meta($user_id, '_sffc_generations_' . $current_month, intval($count) + 1);
        }

        // Track total generations for the post
        $post_generations = get_post_meta($post_id, '_sffc_generation_count', true);
        update_post_meta($post_id, '_sffc_generation_count', intval($post_generations) + 1);
    }

    /**
     * Capture lead email
     */
    private function capture_lead($email, $post_id, $model_type) {
        global $wpdb;

        // Store in a simple log (could be extended to a proper lead table)
        $lead_data = array(
            'email' => $email,
            'post_id' => $post_id,
            'model_type' => $model_type,
            'timestamp' => current_time('mysql'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        );

        $existing_leads = get_option('sffc_lead_captures', array());
        $existing_leads[] = $lead_data;

        // Keep last 1000 leads
        if (count($existing_leads) > 1000) {
            $existing_leads = array_slice($existing_leads, -1000);
        }

        update_option('sffc_lead_captures', $existing_leads);
    }

    /**
     * Get contextual analysis fallback using real market data
     *
     * @param int    $post_id    Post ID
     * @param string $model_type Model type
     * @return array Analysis data structure
     */
    private function get_contextual_analysis_fallback($post_id, $model_type) {
        $post = get_post($post_id);
        if (!$post) {
            return $this->get_empty_analysis_structure($model_type);
        }

        // Use SFFC_Market_Data to get real contextual data
        if (class_exists('SFFC_Market_Data')) {
            $market_data = SFFC_Market_Data::get_instance();
            $contextual = $market_data->build_contextual_model(
                $post_id,
                $post->post_title,
                $post->post_content
            );

            if ($contextual && !empty($contextual['metrics'])) {
                // Convert market data metrics to analysis input format
                $inputs = array(
                    'company_name' => $this->extract_company_from_title($post->post_title),
                    'target_name' => $this->extract_company_from_title($post->post_title),
                );

                // Map metrics to inputs
                foreach ($contextual['metrics'] as $metric) {
                    $key = strtolower(str_replace(' ', '_', $metric['label']));
                    $inputs[$key] = array(
                        'value' => $metric['value'],
                        'label' => $metric['label'],
                        'source' => $contextual['source'] ?? 'Market Data',
                    );
                }

                // Add geography if available
                if (!empty($contextual['geography'])) {
                    $inputs['geography'] = $contextual['geography']['city']
                        ?? $contextual['geography']['country']
                        ?? 'Global';
                }

                return array(
                    'summary' => 'Analysis based on ' . ($contextual['source'] ?? 'market research') . ' for ' . ($contextual['geography']['city'] ?? 'the market') . '.',
                    'inputs' => $inputs,
                    'model_title' => $contextual['model_title'] ?? 'Market Analysis',
                    'data_source' => $contextual['source'] ?? 'Market Research',
                    'data_confidence' => 'medium',
                    'notes' => 'Data sourced from real market benchmarks. Generate full model for detailed projections.',
                );
            }
        }

        // Final fallback - minimal structure
        return $this->get_empty_analysis_structure($model_type);
    }

    /**
     * Get empty analysis structure for a model type
     */
    private function get_empty_analysis_structure($model_type) {
        return array(
            'summary' => 'Analysis available upon generation.',
            'inputs' => array(
                'company_name' => 'Target Company',
                'target_name' => 'Target Company',
            ),
            'data_confidence' => 'low',
            'notes' => 'Generate model to populate with real data.',
        );
    }

    /**
     * Extract company name from article title
     */
    private function extract_company_from_title($title) {
        // Common patterns: "Company Name Acquires...", "Company Name to...", etc.
        $patterns = array(
            '/^([A-Z][A-Za-z0-9\s&\.\-]+?)\s+(?:to|acquires|buys|sells|announces|plans|opens|expands|launches|raises|closes)/i',
            '/^([A-Z][A-Za-z0-9\s&\.\-]+?)\s+(?:joins|partners|merges)/i',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $title, $matches)) {
                return trim($matches[1]);
            }
        }

        // Fallback: use first few words
        $words = explode(' ', $title);
        $company = implode(' ', array_slice($words, 0, 3));
        return $company ?: 'Target Company';
    }

    /**
     * AJAX handler for Excel download (logged in)
     */
    public function ajax_download_excel() {
        check_ajax_referer('sffc_model_generation_nonce', 'nonce');

        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        $model_type = isset($_GET['model_type']) ? sanitize_text_field($_GET['model_type']) : '';

        if (!$post_id || !$model_type) {
            wp_die('Invalid parameters');
        }

        $this->stream_excel($post_id, $model_type);
    }

    /**
     * AJAX handler for Excel download (guests)
     */
    public function ajax_download_excel_guest() {
        // Verify nonce for security
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'sffc_model_generation_nonce')) {
            wp_die('Security check failed');
        }

        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        $model_type = isset($_GET['model_type']) ? sanitize_text_field($_GET['model_type']) : '';

        if (!$post_id || !$model_type) {
            wp_die('Invalid parameters');
        }

        $this->stream_excel($post_id, $model_type);
    }

    /**
     * Stream Excel file to browser
     */
    private function stream_excel($post_id, $model_type) {
        // Get analysis data
        $analysis = get_post_meta($post_id, '_sffc_generated_analysis', true);

        // If no analysis exists, generate it now
        if (empty($analysis)) {
            $result = $this->generate_analysis($post_id, $model_type, get_current_user_id());

            if (is_wp_error($result)) {
                // Fallback to contextual market data
                $analysis = $this->get_contextual_analysis_fallback($post_id, $model_type);
            } else {
                $analysis = $result['analysis'] ?? array();
            }
        }

        // Use the file streamer service
        $streamer = $this->get_file_streamer();
        $result = $streamer->stream_excel($post_id, $model_type, $analysis);

        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }

        // stream_excel handles exit internally
    }

    /**
     * AJAX handler for PDF download (logged in)
     */
    public function ajax_download_pdf() {
        check_ajax_referer('sffc_model_generation_nonce', 'nonce');

        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        $model_type = isset($_GET['model_type']) ? sanitize_text_field($_GET['model_type']) : '';

        if (!$post_id || !$model_type) {
            wp_die('Invalid parameters');
        }

        $this->stream_pdf($post_id, $model_type);
    }

    /**
     * AJAX handler for PDF download (guests)
     */
    public function ajax_download_pdf_guest() {
        // Verify nonce for security
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'sffc_model_generation_nonce')) {
            wp_die('Security check failed');
        }

        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        $model_type = isset($_GET['model_type']) ? sanitize_text_field($_GET['model_type']) : '';

        if (!$post_id || !$model_type) {
            wp_die('Invalid parameters');
        }

        $this->stream_pdf($post_id, $model_type);
    }

    /**
     * Stream PDF file to browser
     */
    private function stream_pdf($post_id, $model_type) {
        // Get analysis data
        $analysis = get_post_meta($post_id, '_sffc_generated_analysis', true);

        // If no analysis exists, generate it now
        if (empty($analysis)) {
            $result = $this->generate_analysis($post_id, $model_type, get_current_user_id());

            if (is_wp_error($result)) {
                // Fallback to contextual market data
                $analysis = $this->get_contextual_analysis_fallback($post_id, $model_type);
            } else {
                $analysis = $result['analysis'] ?? array();
            }
        }

        // Use the file streamer service
        $streamer = $this->get_file_streamer();
        $result = $streamer->stream_pdf($post_id, $model_type, $analysis);

        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }

        // stream_pdf handles exit internally
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('sffc/v1', '/classification/(?P<post_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_classification'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('sffc/v1', '/analysis/(?P<post_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_analysis'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * REST endpoint: Get classification
     */
    public function rest_get_classification($request) {
        $post_id = $request->get_param('post_id');
        $classification = get_post_meta($post_id, '_sffc_article_classification', true);

        if (empty($classification)) {
            return new WP_REST_Response(array('error' => 'Not classified'), 404);
        }

        return new WP_REST_Response($classification, 200);
    }

    /**
     * REST endpoint: Get analysis
     */
    public function rest_get_analysis($request) {
        $post_id = $request->get_param('post_id');
        $analysis = get_post_meta($post_id, '_sffc_generated_analysis', true);

        if (empty($analysis)) {
            return new WP_REST_Response(array('error' => 'No analysis generated'), 404);
        }

        return new WP_REST_Response(array(
            'analysis' => $analysis,
            'generated_at' => get_post_meta($post_id, '_sffc_generation_timestamp', true),
            'model_type' => get_post_meta($post_id, '_sffc_generated_model_type', true),
        ), 200);
    }
}

// Initialize the generator
add_action('plugins_loaded', function() {
    SFFC_Model_Generator::get_instance();
});
