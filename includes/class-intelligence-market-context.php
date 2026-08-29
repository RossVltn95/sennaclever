<?php
/**
 * Intelligence Market Context Service
 *
 * Provides market context, comparable transactions, sector benchmarks,
 * and industry projections for intelligence briefs.
 *
 * @package Skill_Farm_Finance_Career
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Intelligence_Market_Context {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * API keys
     */
    private $alpha_vantage_key;
    private $anthropic_key;

    /**
     * Cache duration
     */
    private $cache_duration = 86400; // 24 hours

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
        $this->alpha_vantage_key = get_option('sffc_alpha_vantage_key', '');
        $this->anthropic_key = get_option('sffc_anthropic_key', '');
    }

    /**
     * Get complete market context for a brief
     *
     * @param array $intelligence Intelligence data
     * @param array $enrichment   Enrichment data
     * @return array Market context
     */
    public function get_market_context($intelligence, $enrichment = array()) {
        $sector = $this->determine_sector($intelligence, $enrichment);
        $event_type = $intelligence['event']['type'] ?? 'transaction';
        $geography = $intelligence['geography']['primary_region'] ?? '';

        $context = array(
            'industry_overview' => $this->get_industry_overview($sector, $geography),
            'sector_trends' => $this->get_sector_trends($sector),
            'benchmarks' => $this->get_sector_benchmarks($sector, $event_type),
            'comparable_transactions' => $this->get_comparable_transactions($intelligence, $sector, $event_type),
        );

        return $context;
    }

    /**
     * Get industry projections
     *
     * @param array $intelligence Intelligence data
     * @param array $enrichment   Enrichment data
     * @return array Industry projections
     */
    public function get_industry_projections($intelligence, $enrichment = array()) {
        $sector = $this->determine_sector($intelligence, $enrichment);

        $cache_key = 'sffc_projections_' . md5($sector);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // Get projections from Claude with web search
        $projections = $this->fetch_industry_projections($sector, $intelligence);

        if (!is_wp_error($projections)) {
            set_transient($cache_key, $projections, $this->cache_duration * 7);
        }

        return $projections;
    }

    /**
     * Determine sector from intelligence data
     *
     * @param array $intelligence Intelligence data
     * @param array $enrichment   Enrichment data
     * @return string Sector name
     */
    private function determine_sector($intelligence, $enrichment = array()) {
        // Check enrichment first (most reliable)
        if (!empty($enrichment['stock_data']['sector'])) {
            return $enrichment['stock_data']['sector'];
        }

        if (!empty($enrichment['wikidata']['industry'])) {
            return $enrichment['wikidata']['industry'];
        }

        // Check intelligence
        if (!empty($intelligence['classification']['sector'])) {
            return $intelligence['classification']['sector'];
        }

        if (!empty($intelligence['entities']['primary_company']['industry'])) {
            return $intelligence['entities']['primary_company']['industry'];
        }

        return 'General';
    }

    /**
     * Get industry overview
     *
     * @param string $sector    Sector name
     * @param string $geography Geography
     * @return string Industry overview
     */
    private function get_industry_overview($sector, $geography = '') {
        $cache_key = 'sffc_industry_' . md5($sector . $geography);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // Get predefined overview or generate
        $overview = $this->get_predefined_industry_overview($sector);

        if (empty($overview) && $this->anthropic_key) {
            $overview = $this->generate_industry_overview($sector, $geography);
        }

        if (!empty($overview)) {
            set_transient($cache_key, $overview, $this->cache_duration * 7);
        }

        return $overview;
    }

    /**
     * Get predefined industry overview
     *
     * @param string $sector Sector name
     * @return string|null Overview or null
     */
    private function get_predefined_industry_overview($sector) {
        $sector_lower = strtolower($sector);

        $overviews = array(
            'technology' => 'The technology sector encompasses companies involved in software development, hardware manufacturing, semiconductors, IT services, and internet-based businesses. The sector is characterized by rapid innovation cycles, high R&D intensity, and significant scale economies. Major themes include cloud computing adoption, artificial intelligence, cybersecurity, and digital transformation across industries.',

            'fintech' => 'Financial technology (fintech) represents the intersection of finance and technology, disrupting traditional banking, payments, lending, and investment services. The sector has seen explosive growth driven by smartphone adoption, changing consumer preferences, and regulatory evolution. Key segments include digital payments, neobanking, insurtech, wealthtech, and blockchain-based services.',

            'healthcare' => 'The healthcare sector includes pharmaceuticals, biotechnology, medical devices, healthcare providers, and health insurance. The industry faces structural tailwinds from aging demographics, rising chronic disease prevalence, and increasing healthcare spending globally. Innovation in drug development, precision medicine, and digital health are reshaping competitive dynamics.',

            'financial services' => 'Financial services encompasses banking, insurance, asset management, and capital markets. The sector is navigating digital transformation, evolving regulations, and changing customer expectations. Key trends include consolidation, technology investment, fee compression in asset management, and expansion of private credit.',

            'consumer' => 'The consumer sector includes retail, consumer goods, restaurants, and leisure. E-commerce growth, changing shopping habits, and direct-to-consumer models are disrupting traditional retail. Sustainability, health consciousness, and premiumization are driving product innovation across categories.',

            'industrials' => 'The industrials sector covers manufacturing, aerospace & defense, transportation, and building products. Themes include automation, supply chain resilience, infrastructure investment, and the energy transition. The sector is cyclical but benefits from long-term trends in urbanization and modernization.',

            'energy' => 'The energy sector includes oil & gas exploration/production, refining, utilities, and renewable energy. The industry is undergoing a historic transition toward cleaner energy sources. Traditional energy companies are diversifying while pure-play renewables scale rapidly with policy support.',

            'real estate' => 'Real estate encompasses property development, REITs, and real estate services. The sector is adapting to remote work impacts on office demand, e-commerce effects on retail, and housing affordability challenges. Industrial/logistics and data centers have emerged as growth segments.',

            'crypto' => 'The cryptocurrency and blockchain sector includes exchanges, infrastructure providers, and blockchain-based applications. The industry has experienced significant volatility but continues to attract institutional interest. Regulatory clarity remains a key factor for mainstream adoption.',

            'private equity' => 'Private equity involves investing in private companies through buyouts, growth equity, and venture capital. The industry manages trillions in assets and plays a significant role in M&A activity. Key trends include sector specialization, operational value creation, and longer hold periods.',
        );

        foreach ($overviews as $key => $overview) {
            if (strpos($sector_lower, $key) !== false) {
                return $overview;
            }
        }

        return null;
    }

    /**
     * Generate industry overview using Claude
     *
     * @param string $sector    Sector name
     * @param string $geography Geography
     * @return string Overview
     */
    private function generate_industry_overview($sector, $geography = '') {
        $prompt = "Write a 4-5 sentence professional industry overview for the {$sector} sector" .
                  ($geography ? " with focus on {$geography}" : "") .
                  ". Include current state, key trends, and competitive dynamics. Write like a Goldman Sachs industry primer - factual and insightful, not promotional.";

        $result = $this->call_claude($prompt, true);

        if (is_wp_error($result)) {
            return "The {$sector} sector continues to evolve with changing market dynamics and competitive pressures.";
        }

        return $result;
    }

    /**
     * Get sector trends
     *
     * @param string $sector Sector name
     * @return array Trends
     */
    private function get_sector_trends($sector) {
        $sector_lower = strtolower($sector);

        $trends = array(
            'technology' => array(
                'AI and machine learning adoption accelerating across enterprise applications',
                'Cloud migration continuing with hybrid/multi-cloud strategies gaining traction',
                'Cybersecurity spending increasing amid rising threat landscape',
                'Tech valuations normalizing after 2021-2022 reset',
            ),
            'fintech' => array(
                'Embedded finance expanding financial services into non-financial platforms',
                'Regulatory scrutiny increasing, particularly for crypto and BNPL',
                'Profitability focus replacing growth-at-all-costs mentality',
                'Consolidation accelerating as funding environment tightens',
            ),
            'healthcare' => array(
                'GLP-1 drugs reshaping obesity and metabolic disease treatment',
                'Biotech M&A increasing as large pharma faces patent cliffs',
                'Value-based care models gaining adoption',
                'Digital health integration into traditional healthcare delivery',
            ),
            'financial services' => array(
                'Interest rate environment creating both opportunities and challenges',
                'Private credit growth accelerating versus traditional bank lending',
                'Wealth management consolidation continuing',
                'Digital transformation investments remain elevated',
            ),
            'energy' => array(
                'Energy transition driving massive capital reallocation',
                'LNG demand growth supporting new infrastructure investment',
                'Traditional energy companies diversifying portfolios',
                'Grid infrastructure investment accelerating',
            ),
            'crypto' => array(
                'Institutional adoption progressing despite regulatory uncertainty',
                'Layer 2 solutions improving scalability and reducing costs',
                'Stablecoin usage growing for payments and settlements',
                'Regulatory frameworks developing across major jurisdictions',
            ),
        );

        foreach ($trends as $key => $sector_trends) {
            if (strpos($sector_lower, $key) !== false) {
                return $sector_trends;
            }
        }

        // Default trends
        return array(
            'Industry consolidation creating larger, more diversified players',
            'Digital transformation reshaping competitive dynamics',
            'Cost pressures driving operational efficiency initiatives',
            'ESG considerations increasingly influencing strategic decisions',
        );
    }

    /**
     * Get sector benchmarks
     *
     * @param string $sector     Sector name
     * @param string $event_type Event type
     * @return array Benchmarks
     */
    private function get_sector_benchmarks($sector, $event_type = 'transaction') {
        $sector_lower = strtolower($sector);

        // Sector-specific multiples (approximate market data)
        $benchmarks_data = array(
            'technology' => array(
                array('label' => 'Median EV/Revenue', 'value' => '5.2x', 'context' => 'Software sector'),
                array('label' => 'Median EV/EBITDA', 'value' => '18.5x', 'context' => 'Profitable tech'),
                array('label' => 'Revenue Growth', 'value' => '15-25%', 'context' => 'High-growth segment'),
                array('label' => 'Gross Margin', 'value' => '70-80%', 'context' => 'SaaS benchmark'),
            ),
            'fintech' => array(
                array('label' => 'Median EV/Revenue', 'value' => '4.0x', 'context' => 'Fintech sector'),
                array('label' => 'Median P/E', 'value' => '22x', 'context' => 'Profitable fintechs'),
                array('label' => 'Revenue Growth', 'value' => '20-35%', 'context' => 'Growth stage'),
                array('label' => 'Take Rate', 'value' => '1-3%', 'context' => 'Payments segment'),
            ),
            'healthcare' => array(
                array('label' => 'Median EV/Revenue', 'value' => '3.5x', 'context' => 'Healthcare services'),
                array('label' => 'Median EV/EBITDA', 'value' => '14x', 'context' => 'Provider segment'),
                array('label' => 'Pharma P/E', 'value' => '15-18x', 'context' => 'Large cap pharma'),
                array('label' => 'Biotech EV/Revenue', 'value' => '8-12x', 'context' => 'Commercial stage'),
            ),
            'financial services' => array(
                array('label' => 'Bank P/TBV', 'value' => '1.2x', 'context' => 'Large cap banks'),
                array('label' => 'Asset Manager P/E', 'value' => '12-15x', 'context' => 'Traditional AM'),
                array('label' => 'Insurance P/BV', 'value' => '1.0-1.5x', 'context' => 'P&C insurers'),
                array('label' => 'PE GP Multiples', 'value' => '20-25x', 'context' => 'Listed PE firms'),
            ),
            'crypto' => array(
                array('label' => 'Exchange Revenue Multiple', 'value' => '8-15x', 'context' => 'Major exchanges'),
                array('label' => 'Market Dominance', 'value' => '50%+', 'context' => 'Bitcoin share'),
                array('label' => 'Staking Yields', 'value' => '3-8%', 'context' => 'Major PoS chains'),
                array('label' => 'Trading Volume', 'value' => '$50B+', 'context' => 'Daily spot volume'),
            ),
        );

        foreach ($benchmarks_data as $key => $benchmarks) {
            if (strpos($sector_lower, $key) !== false) {
                return $benchmarks;
            }
        }

        // Default benchmarks
        return array(
            array('label' => 'Median EV/EBITDA', 'value' => '10-12x', 'context' => 'Market median'),
            array('label' => 'Median P/E', 'value' => '15-18x', 'context' => 'S&P 500 average'),
            array('label' => 'Median EV/Revenue', 'value' => '2-3x', 'context' => 'Broad market'),
            array('label' => 'M&A Premium', 'value' => '25-35%', 'context' => 'Typical range'),
        );
    }

    /**
     * Get comparable transactions
     *
     * @param array  $intelligence Intelligence data
     * @param string $sector       Sector
     * @param string $event_type   Event type
     * @return array Comparable transactions
     */
    private function get_comparable_transactions($intelligence, $sector, $event_type) {
        $cache_key = 'sffc_comps_' . md5($sector . $event_type);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // First, check if we have the current transaction to include
        $current_transaction = $this->build_current_transaction($intelligence);

        // Get comparable transactions from Claude
        $comps = $this->fetch_comparable_transactions($sector, $event_type, $intelligence);

        if (is_wp_error($comps) || empty($comps)) {
            $comps = $this->get_fallback_comparables($sector, $event_type);
        }

        // Add current transaction as first item
        if ($current_transaction) {
            array_unshift($comps, $current_transaction);
        }

        set_transient($cache_key, $comps, $this->cache_duration);

        return $comps;
    }

    /**
     * Build current transaction for comparables table
     *
     * @param array $intelligence Intelligence data
     * @return array|null Transaction data
     */
    private function build_current_transaction($intelligence) {
        $company = $intelligence['entities']['primary_company']['name'] ?? '';

        if (empty($company)) {
            return null;
        }

        $deal_value = '';
        if (!empty($intelligence['financials']['deal_value']['amount'])) {
            $amount = $intelligence['financials']['deal_value']['amount'];
            $unit = $intelligence['financials']['deal_value']['unit'] ?? 'million';
            $deal_value = '$' . $amount . ($unit === 'billion' ? 'B' : 'M');
        }

        $secondary = $intelligence['entities']['secondary_companies'][0] ?? array();
        $acquirer = $secondary['name'] ?? '';

        return array(
            'target' => $company,
            'acquirer' => $acquirer,
            'date' => date('M Y'),
            'value' => $deal_value ?: 'Undisclosed',
            'ev_revenue' => 'TBD',
            'ev_ebitda' => 'TBD',
            'is_current' => true,
        );
    }

    /**
     * Fetch comparable transactions using Claude
     *
     * @param string $sector       Sector
     * @param string $event_type   Event type
     * @param array  $intelligence Intelligence for context
     * @return array|WP_Error Comparables
     */
    private function fetch_comparable_transactions($sector, $event_type, $intelligence) {
        if (empty($this->anthropic_key)) {
            return new WP_Error('no_api_key', 'API key not configured');
        }

        $company = $intelligence['entities']['primary_company']['name'] ?? 'this company';

        $prompt = <<<PROMPT
Find 4-5 comparable {$event_type} transactions in the {$sector} sector that would be relevant comparisons to {$company}.

Focus on transactions from the past 2-3 years. Return ONLY valid JSON array:

[
  {
    "target": "Target company name",
    "acquirer": "Acquirer name (or 'IPO' for IPOs)",
    "date": "Mon YYYY",
    "value": "$X.XB or $XXXM",
    "ev_revenue": "X.Xx or N/A",
    "ev_ebitda": "X.Xx or N/A"
  }
]

Rules:
- Use real transactions that actually happened
- Include deal value and multiples where publicly known
- Prioritize transactions similar in size and type
- Most recent transactions first
PROMPT;

        $result = $this->call_claude($prompt);

        if (is_wp_error($result)) {
            return $result;
        }

        // If result is an array of transactions, return it
        if (is_array($result) && isset($result[0]['target'])) {
            return $result;
        }

        return new WP_Error('parse_error', 'Could not parse comparables');
    }

    /**
     * Get fallback comparable transactions
     *
     * @param string $sector     Sector
     * @param string $event_type Event type
     * @return array Comparables
     */
    private function get_fallback_comparables($sector, $event_type) {
        // Generic fallback - should be replaced with real data
        return array(
            array(
                'target' => 'Comparable transaction data',
                'acquirer' => 'requires API access',
                'date' => 'N/A',
                'value' => 'N/A',
                'ev_revenue' => 'N/A',
                'ev_ebitda' => 'N/A',
            ),
        );
    }

    /**
     * Fetch industry projections
     *
     * @param string $sector       Sector
     * @param array  $intelligence Intelligence data
     * @return array Projections
     */
    private function fetch_industry_projections($sector, $intelligence) {
        if (empty($this->anthropic_key)) {
            return $this->get_fallback_projections($sector);
        }

        $prompt = <<<PROMPT
Provide industry projections for the {$sector} sector. Return ONLY valid JSON:

{
  "overview": "2-3 sentences on the growth outlook for this sector over the next 3-5 years.",

  "market_size": {
    "current": "$XXB",
    "current_year": "2024",
    "projected": "$XXB",
    "projected_year": "2028",
    "cagr": "X.X%"
  },

  "growth_forecasts": [
    {
      "metric": "Market Size",
      "year_0": "$XXB",
      "year_1": "$XXB",
      "year_2": "$XXB",
      "source": "Industry Reports"
    },
    {
      "metric": "Revenue Growth",
      "year_0": "X%",
      "year_1": "X%",
      "year_2": "X%",
      "source": "Analyst Consensus"
    }
  ],

  "key_drivers": [
    "Primary growth driver",
    "Secondary growth driver",
    "Third growth driver"
  ],

  "headwinds": [
    "Primary challenge or risk",
    "Secondary challenge",
    "Third challenge"
  ]
}

Use realistic figures based on current market research. Be specific to {$sector}.
PROMPT;

        $result = $this->call_claude($prompt);

        if (is_wp_error($result)) {
            return $this->get_fallback_projections($sector);
        }

        return $result;
    }

    /**
     * Get fallback projections
     *
     * @param string $sector Sector
     * @return array Projections
     */
    private function get_fallback_projections($sector) {
        return array(
            'overview' => "The {$sector} sector is expected to continue evolving with both opportunities and challenges ahead.",
            'market_size' => array(
                'current' => 'Data pending',
                'current_year' => date('Y'),
                'projected' => 'Data pending',
                'projected_year' => date('Y') + 4,
                'cagr' => 'TBD',
            ),
            'growth_forecasts' => array(),
            'key_drivers' => array(
                'Technology adoption and digital transformation',
                'Changing consumer/customer preferences',
                'Regulatory evolution',
            ),
            'headwinds' => array(
                'Economic uncertainty',
                'Competitive pressure',
                'Operational challenges',
            ),
        );
    }

    /**
     * Call Claude API
     *
     * @param string $prompt      The prompt
     * @param bool   $text_only   Return text only (not JSON)
     * @return mixed Response
     */
    private function call_claude($prompt, $text_only = false) {
        if (empty($this->anthropic_key)) {
            return new WP_Error('no_api_key', 'Anthropic API key not configured');
        }

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'timeout' => 60,
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $this->anthropic_key,
                'anthropic-version' => '2023-06-01',
            ),
            'body' => json_encode(array(
                'model' => 'claude-haiku-4-5-20251001',
                'max_tokens' => 4096,
                'messages' => array(
                    array('role' => 'user', 'content' => $prompt),
                ),
            )),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('claude_error', $body['error']['message'] ?? 'Unknown error');
        }

        $content = $body['content'][0]['text'] ?? '';

        if ($text_only) {
            return $content;
        }

        // Parse JSON
        if (preg_match('/[\[\{][\s\S]*[\]\}]/', $content, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json) {
                return $json;
            }
        }

        return new WP_Error('parse_error', 'Could not parse response');
    }
}
