<?php
/**
 * Intelligence Enrichment Service
 *
 * Extracts entities, financials, and enriches articles with external data.
 * Uses free APIs (Wikidata, Alpha Vantage, Finnhub) + Claude for web search.
 *
 * @package Skill_Farm_Finance_Career
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Intelligence_Enrichment {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * API clients
     */
    private $wikidata;
    private $alpha_vantage;
    private $finnhub;

    /**
     * API keys (from options)
     */
    private $alpha_vantage_key;
    private $finnhub_key;
    private $anthropic_key;

    /**
     * Cache duration in seconds
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
        $this->finnhub_key = get_option('sffc_finnhub_key', '');
        $this->anthropic_key = get_option('sffc_anthropic_key', '');
    }

    /**
     * Enrich an article with intelligence data
     *
     * @param int $post_id The post ID to enrich
     * @return array|WP_Error Enriched intelligence data or error
     */
    public function enrich_article($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        // Step 1: Extract entities and data from article
        $extracted = $this->extract_from_article($post);

        if (is_wp_error($extracted)) {
            return $extracted;
        }

        // Step 2: Query free databases for enrichment
        $db_enriched = $this->query_databases($extracted);

        // Step 3: Identify gaps and use Claude web search
        $gaps = $this->identify_gaps($extracted, $db_enriched);

        if (!empty($gaps)) {
            $web_enriched = $this->claude_web_search($post, $extracted, $gaps);
            $db_enriched = $this->merge_enrichment($db_enriched, $web_enriched);
        }

        // Step 4: Cross-validate data
        $validated = $this->cross_validate($extracted, $db_enriched);

        // Step 5: Build final intelligence structure
        $intelligence = $this->build_intelligence_structure($post, $extracted, $validated);

        // Step 6: Check completeness
        $completeness = $this->check_completeness($intelligence);

        if (!$completeness['is_complete']) {
            // Try deeper search for missing data
            $deep_enriched = $this->claude_deep_search($post, $extracted, $completeness['missing']);
            $intelligence = $this->merge_intelligence($intelligence, $deep_enriched);

            // Re-check
            $completeness = $this->check_completeness($intelligence);
        }

        // Store results
        update_post_meta($post_id, '_sffc_extracted_intelligence', $intelligence);
        update_post_meta($post_id, '_sffc_enrichment_date', current_time('mysql'));
        update_post_meta($post_id, '_sffc_completeness_status', $completeness);

        // Also store database enrichment separately for the assembler
        update_post_meta($post_id, '_sffc_database_enrichment', $db_enriched);

        return $intelligence;
    }

    /**
     * Extract entities and data from article content
     *
     * @param WP_Post $post The post object
     * @return array Extracted data
     */
    public function extract_from_article($post) {
        $title = $post->post_title;
        $content = wp_strip_all_tags($post->post_content);

        // Use Claude for intelligent extraction
        $extraction_prompt = $this->build_extraction_prompt($title, $content);
        $extracted = $this->call_claude($extraction_prompt);

        if (is_wp_error($extracted)) {
            // Fallback to regex-based extraction
            $extracted = $this->regex_extraction($title, $content);
        }

        return $extracted;
    }

    /**
     * Build prompt for Claude extraction
     *
     * @param string $title   Article title
     * @param string $content Article content
     * @return string The prompt
     */
    private function build_extraction_prompt($title, $content) {
        $prompt = <<<PROMPT
Extract structured data from this financial news article. Return ONLY valid JSON.

ARTICLE TITLE: {$title}

ARTICLE CONTENT:
{$content}

Extract and return this JSON structure:
{
  "event": {
    "type": "ipo|acquisition|merger|fundraise|buyout|restructuring|spinoff|listing|other",
    "subtype": "specific subtype if applicable",
    "status": "announced|pending|completed|rumored",
    "title": "brief event title",
    "description": "2-3 sentence description of what's happening"
  },
  "entities": {
    "primary_company": {
      "name": "main company name",
      "ticker": "stock ticker if mentioned",
      "type": "public|private|subsidiary",
      "industry": "sector/industry",
      "country": "headquarters country"
    },
    "secondary_companies": [
      {"name": "", "role": "acquirer|target|investor|partner", "type": "", "country": ""}
    ],
    "people": [
      {"name": "", "role": "", "company": ""}
    ],
    "advisors": [
      {"name": "", "role": "financial_advisor|legal_advisor|other", "representing": ""}
    ]
  },
  "financials": {
    "deal_value": {"amount": null, "currency": "USD", "unit": "million|billion"},
    "valuation": {"amount": null, "currency": "USD", "unit": "million|billion"},
    "premium": {"percentage": null, "description": ""},
    "other_figures": [
      {"label": "", "value": "", "context": ""}
    ]
  },
  "dates": {
    "announced": "",
    "expected_close": "",
    "other": [{"label": "", "date": ""}]
  },
  "geography": {
    "primary_region": "",
    "countries_involved": [],
    "target_market": ""
  },
  "key_quotes": [
    {"text": "", "speaker": "", "role": ""}
  ]
}

Be precise. Only include data explicitly stated or clearly implied in the article. Use null for unknown values.
PROMPT;

        return $prompt;
    }

    /**
     * Fallback regex-based extraction
     *
     * @param string $title   Article title
     * @param string $content Article content
     * @return array Extracted data
     */
    private function regex_extraction($title, $content) {
        $full_text = $title . ' ' . $content;

        $extracted = array(
            'event' => array(
                'type' => $this->detect_event_type($full_text),
                'status' => 'announced',
                'title' => $title,
                'description' => '',
            ),
            'entities' => array(
                'primary_company' => array(
                    'name' => $this->extract_primary_company($title),
                    'ticker' => $this->extract_ticker($full_text),
                    'type' => 'unknown',
                    'industry' => '',
                    'country' => $this->extract_country($full_text),
                ),
                'secondary_companies' => array(),
                'people' => array(),
                'advisors' => array(),
            ),
            'financials' => array(
                'deal_value' => $this->extract_deal_value($full_text),
                'valuation' => $this->extract_valuation($full_text),
                'other_figures' => $this->extract_financial_figures($full_text),
            ),
            'dates' => array(
                'announced' => '',
                'expected_close' => '',
            ),
            'geography' => array(
                'primary_region' => '',
                'countries_involved' => $this->extract_countries($full_text),
            ),
        );

        return $extracted;
    }

    /**
     * Detect event type from text
     *
     * @param string $text The text to analyze
     * @return string Event type
     */
    private function detect_event_type($text) {
        $text_lower = strtolower($text);

        $patterns = array(
            'ipo' => array('ipo', 'initial public offering', 'goes public', 'public listing', 'stock debut'),
            'acquisition' => array('acquires', 'acquisition', 'to acquire', 'buyout', 'takes over', 'bought by'),
            'merger' => array('merger', 'merges with', 'to merge', 'combination'),
            'fundraise' => array('raises', 'funding round', 'series a', 'series b', 'series c', 'venture capital', 'investment round'),
            'buyout' => array('leveraged buyout', 'lbo', 'management buyout', 'mbo', 'private equity buyout'),
            'spinoff' => array('spin-off', 'spinoff', 'spins off', 'carve-out'),
            'restructuring' => array('restructuring', 'reorganization', 'chapter 11', 'bankruptcy'),
            'listing' => array('listing', 'lists on', 'to list'),
        );

        foreach ($patterns as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text_lower, $keyword) !== false) {
                    return $type;
                }
            }
        }

        return 'other';
    }

    /**
     * Extract primary company name from title
     *
     * @param string $title The article title
     * @return string Company name
     */
    private function extract_primary_company($title) {
        // Common patterns: "Company X announces...", "Company X to acquire..."
        $patterns = array(
            '/^([A-Z][A-Za-z0-9\s&\-\.]+?)(?:\s+(?:announces|plans|to|acquires|raises|merges|files|launches|reports|reveals|considers|explores))/i',
            '/^([A-Z][A-Za-z0-9\s&\-\.]+?)(?:\'s?\s+(?:parent|subsidiary|unit))/i',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $title, $matches)) {
                return trim($matches[1]);
            }
        }

        // Take first capitalized words
        if (preg_match('/^([A-Z][A-Za-z0-9\s&\-\.]+?)(?:\s+[a-z])/', $title, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    /**
     * Extract stock ticker from text
     *
     * @param string $text The text
     * @return string|null Ticker symbol
     */
    private function extract_ticker($text) {
        // Pattern: (TICKER) or NASDAQ: TICKER or NYSE: TICKER
        if (preg_match('/\(([A-Z]{1,5})\)/', $text, $matches)) {
            return $matches[1];
        }
        if (preg_match('/(?:NASDAQ|NYSE|LSE|TSX):\s*([A-Z]{1,5})/i', $text, $matches)) {
            return strtoupper($matches[1]);
        }
        return null;
    }

    /**
     * Extract deal value from text
     *
     * @param string $text The text
     * @return array Deal value data
     */
    private function extract_deal_value($text) {
        // Patterns: $X billion, $X million, USD X billion
        $patterns = array(
            '/\$\s*([\d,.]+)\s*(billion|million|bn|mn|m|b)/i',
            '/USD\s*([\d,.]+)\s*(billion|million|bn|mn|m|b)/i',
            '/([\d,.]+)\s*(billion|million|bn|mn|m|b)\s*(?:dollars|usd)/i',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $amount = floatval(str_replace(',', '', $matches[1]));
                $unit = strtolower($matches[2]);

                if (in_array($unit, array('b', 'bn', 'billion'))) {
                    $unit = 'billion';
                } else {
                    $unit = 'million';
                }

                return array(
                    'amount' => $amount,
                    'currency' => 'USD',
                    'unit' => $unit,
                );
            }
        }

        return array('amount' => null, 'currency' => 'USD', 'unit' => 'million');
    }

    /**
     * Extract valuation from text
     *
     * @param string $text The text
     * @return array Valuation data
     */
    private function extract_valuation($text) {
        // Pattern: valued at $X, valuation of $X
        if (preg_match('/(?:valued at|valuation of|worth)\s*\$?\s*([\d,.]+)\s*(billion|million|bn|mn|m|b)/i', $text, $matches)) {
            $amount = floatval(str_replace(',', '', $matches[1]));
            $unit = strtolower($matches[2]);

            if (in_array($unit, array('b', 'bn', 'billion'))) {
                $unit = 'billion';
            } else {
                $unit = 'million';
            }

            return array(
                'amount' => $amount,
                'currency' => 'USD',
                'unit' => $unit,
            );
        }

        return array('amount' => null, 'currency' => 'USD', 'unit' => 'million');
    }

    /**
     * Extract financial figures from text
     *
     * @param string $text The text
     * @return array Financial figures
     */
    private function extract_financial_figures($text) {
        $figures = array();

        // Revenue patterns
        if (preg_match('/revenue(?:s)?\s+(?:of\s+)?\$?([\d,.]+)\s*(billion|million|bn|mn|m|b)/i', $text, $matches)) {
            $figures[] = array(
                'label' => 'Revenue',
                'value' => '$' . $matches[1] . ' ' . $matches[2],
                'context' => 'annual',
            );
        }

        // EBITDA patterns
        if (preg_match('/ebitda\s+(?:of\s+)?\$?([\d,.]+)\s*(billion|million|bn|mn|m|b)/i', $text, $matches)) {
            $figures[] = array(
                'label' => 'EBITDA',
                'value' => '$' . $matches[1] . ' ' . $matches[2],
                'context' => '',
            );
        }

        // Multiple patterns (EV/Revenue, P/E, etc.)
        if (preg_match('/([\d.]+)x\s*(?:revenue|sales|ebitda|earnings)/i', $text, $matches)) {
            $figures[] = array(
                'label' => 'Valuation Multiple',
                'value' => $matches[1] . 'x',
                'context' => $matches[0],
            );
        }

        // Premium patterns
        if (preg_match('/(\d+(?:\.\d+)?)\s*%?\s*premium/i', $text, $matches)) {
            $figures[] = array(
                'label' => 'Premium',
                'value' => $matches[1] . '%',
                'context' => 'to current price',
            );
        }

        return $figures;
    }

    /**
     * Extract country from text
     *
     * @param string $text The text
     * @return string Primary country
     */
    private function extract_country($text) {
        $countries = array(
            'United States' => array('U.S.', 'US', 'USA', 'American', 'New York', 'California', 'Texas'),
            'United Kingdom' => array('U.K.', 'UK', 'Britain', 'British', 'London', 'England'),
            'China' => array('Chinese', 'Beijing', 'Shanghai', 'Hong Kong'),
            'Japan' => array('Japanese', 'Tokyo'),
            'Germany' => array('German', 'Berlin', 'Frankfurt'),
            'France' => array('French', 'Paris'),
            'South Korea' => array('Korean', 'Seoul', 'Korea'),
            'India' => array('Indian', 'Mumbai', 'Delhi', 'Bangalore'),
            'Brazil' => array('Brazilian', 'Sao Paulo'),
            'Canada' => array('Canadian', 'Toronto', 'Vancouver'),
            'Australia' => array('Australian', 'Sydney', 'Melbourne'),
            'Singapore' => array('Singaporean'),
            'Switzerland' => array('Swiss', 'Zurich', 'Geneva'),
        );

        foreach ($countries as $country => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($text, $keyword) !== false) {
                    return $country;
                }
            }
        }

        return '';
    }

    /**
     * Extract all countries mentioned
     *
     * @param string $text The text
     * @return array Countries
     */
    private function extract_countries($text) {
        $found = array();
        $countries = array(
            'United States', 'United Kingdom', 'China', 'Japan', 'Germany', 'France',
            'South Korea', 'India', 'Brazil', 'Canada', 'Australia', 'Singapore',
            'Switzerland', 'Netherlands', 'Spain', 'Italy', 'Sweden', 'Norway',
            'Denmark', 'Finland', 'Ireland', 'Israel', 'UAE', 'Saudi Arabia',
            'Mexico', 'Indonesia', 'Thailand', 'Vietnam', 'Malaysia', 'Philippines',
        );

        foreach ($countries as $country) {
            if (stripos($text, $country) !== false) {
                $found[] = $country;
            }
        }

        return array_unique($found);
    }

    /**
     * Query free databases for enrichment
     *
     * @param array $extracted Extracted data
     * @return array Enriched data from databases
     */
    public function query_databases($extracted) {
        $enriched = array();

        $company_name = $extracted['entities']['primary_company']['name'] ?? '';
        $ticker = $extracted['entities']['primary_company']['ticker'] ?? '';

        // Query Wikidata for company info
        if ($company_name) {
            $wikidata = $this->query_wikidata($company_name);
            if (!is_wp_error($wikidata) && !empty($wikidata)) {
                $enriched['wikidata'] = $wikidata;
            }
        }

        // Query Alpha Vantage for stock data
        if ($ticker && $this->alpha_vantage_key) {
            $stock_data = $this->query_alpha_vantage($ticker);
            if (!is_wp_error($stock_data) && !empty($stock_data)) {
                $enriched['stock_data'] = $stock_data;
            }
        }

        // Query Finnhub for IPO/news data
        if ($this->finnhub_key) {
            $event_type = $extracted['event']['type'] ?? '';
            if ($event_type === 'ipo') {
                $ipo_data = $this->query_finnhub_ipo($company_name);
                if (!is_wp_error($ipo_data) && !empty($ipo_data)) {
                    $enriched['ipo_data'] = $ipo_data;
                }
            }
        }

        return $enriched;
    }

    /**
     * Query Wikidata for company information
     *
     * @param string $company_name Company name
     * @return array|WP_Error Company data
     */
    public function query_wikidata($company_name) {
        $cache_key = 'sffc_wikidata_' . md5($company_name);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // Search for entity
        $search_url = 'https://www.wikidata.org/w/api.php?' . http_build_query(array(
            'action' => 'wbsearchentities',
            'search' => $company_name,
            'language' => 'en',
            'type' => 'item',
            'limit' => 5,
            'format' => 'json',
        ));

        $response = wp_remote_get($search_url, array('timeout' => 10));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['search'])) {
            return array();
        }

        // Get the first result's entity ID
        $entity_id = $body['search'][0]['id'];

        // Fetch entity data
        $entity_url = 'https://www.wikidata.org/w/api.php?' . http_build_query(array(
            'action' => 'wbgetentities',
            'ids' => $entity_id,
            'languages' => 'en',
            'props' => 'labels|descriptions|claims',
            'format' => 'json',
        ));

        $entity_response = wp_remote_get($entity_url, array('timeout' => 10));

        if (is_wp_error($entity_response)) {
            return $entity_response;
        }

        $entity_body = json_decode(wp_remote_retrieve_body($entity_response), true);
        $entity = $entity_body['entities'][$entity_id] ?? array();

        // Parse relevant properties
        $data = array(
            'name' => $entity['labels']['en']['value'] ?? $company_name,
            'description' => $entity['descriptions']['en']['value'] ?? '',
            'founded' => $this->get_wikidata_property($entity, 'P571'), // inception
            'headquarters' => $this->get_wikidata_property($entity, 'P159'), // headquarters location
            'industry' => $this->get_wikidata_property($entity, 'P452'), // industry
            'ceo' => $this->get_wikidata_property($entity, 'P169'), // CEO
            'employees' => $this->get_wikidata_property($entity, 'P1128'), // employees
            'website' => $this->get_wikidata_property($entity, 'P856'), // official website
            'wikidata_id' => $entity_id,
        );

        set_transient($cache_key, $data, $this->cache_duration * 7); // Cache for 7 days

        return $data;
    }

    /**
     * Get Wikidata property value
     *
     * @param array  $entity      Entity data
     * @param string $property_id Property ID
     * @return string|null Property value
     */
    private function get_wikidata_property($entity, $property_id) {
        if (!isset($entity['claims'][$property_id])) {
            return null;
        }

        $claim = $entity['claims'][$property_id][0]['mainsnak']['datavalue'] ?? null;

        if (!$claim) {
            return null;
        }

        switch ($claim['type']) {
            case 'string':
                return $claim['value'];
            case 'time':
                return substr($claim['value']['time'], 1, 4); // Year only
            case 'quantity':
                return number_format(floatval($claim['value']['amount']));
            case 'wikibase-entityid':
                // Would need another query to get the label
                return $claim['value']['id'];
            default:
                return null;
        }
    }

    /**
     * Query Alpha Vantage for stock data
     *
     * @param string $symbol Stock ticker
     * @return array|WP_Error Stock data
     */
    public function query_alpha_vantage($symbol) {
        if (empty($this->alpha_vantage_key)) {
            return new WP_Error('no_api_key', 'Alpha Vantage API key not configured');
        }

        $cache_key = 'sffc_alphav_' . md5($symbol);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // Get company overview
        $url = 'https://www.alphavantage.com/query?' . http_build_query(array(
            'function' => 'OVERVIEW',
            'symbol' => $symbol,
            'apikey' => $this->alpha_vantage_key,
        ));

        $response = wp_remote_get($url, array('timeout' => 15));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['Note'])) {
            // API limit reached
            return new WP_Error('api_limit', 'Alpha Vantage API limit reached');
        }

        if (empty($body) || isset($body['Error Message'])) {
            return array();
        }

        $data = array(
            'name' => $body['Name'] ?? '',
            'description' => $body['Description'] ?? '',
            'exchange' => $body['Exchange'] ?? '',
            'sector' => $body['Sector'] ?? '',
            'industry' => $body['Industry'] ?? '',
            'market_cap' => $body['MarketCapitalization'] ?? '',
            'pe_ratio' => $body['PERatio'] ?? '',
            'ev_revenue' => $body['EVToRevenue'] ?? '',
            'ev_ebitda' => $body['EVToEBITDA'] ?? '',
            'revenue_ttm' => $body['RevenueTTM'] ?? '',
            'profit_margin' => $body['ProfitMargin'] ?? '',
            '52_week_high' => $body['52WeekHigh'] ?? '',
            '52_week_low' => $body['52WeekLow'] ?? '',
            'dividend_yield' => $body['DividendYield'] ?? '',
            'beta' => $body['Beta'] ?? '',
        );

        set_transient($cache_key, $data, 3600); // Cache for 1 hour

        return $data;
    }

    /**
     * Query Finnhub for IPO calendar data
     *
     * @param string $company_name Company name to search
     * @return array|WP_Error IPO data
     */
    public function query_finnhub_ipo($company_name) {
        if (empty($this->finnhub_key)) {
            return new WP_Error('no_api_key', 'Finnhub API key not configured');
        }

        $cache_key = 'sffc_finnhub_ipo_' . date('Y-m');
        $cached = get_transient($cache_key);

        if ($cached === false) {
            // Get IPO calendar for current period
            $from_date = date('Y-m-d', strtotime('-30 days'));
            $to_date = date('Y-m-d', strtotime('+60 days'));

            $url = 'https://finnhub.io/api/v1/calendar/ipo?' . http_build_query(array(
                'from' => $from_date,
                'to' => $to_date,
                'token' => $this->finnhub_key,
            ));

            $response = wp_remote_get($url, array('timeout' => 10));

            if (is_wp_error($response)) {
                return $response;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $cached = $body['ipoCalendar'] ?? array();

            set_transient($cache_key, $cached, $this->cache_duration);
        }

        // Search for matching company
        $company_lower = strtolower($company_name);
        foreach ($cached as $ipo) {
            if (stripos($ipo['name'] ?? '', $company_name) !== false) {
                return array(
                    'name' => $ipo['name'],
                    'symbol' => $ipo['symbol'] ?? '',
                    'date' => $ipo['date'] ?? '',
                    'exchange' => $ipo['exchange'] ?? '',
                    'price_range' => ($ipo['priceRangeLow'] ?? '') . ' - ' . ($ipo['priceRangeHigh'] ?? ''),
                    'shares' => $ipo['numberOfShares'] ?? '',
                    'status' => $ipo['status'] ?? '',
                );
            }
        }

        return array();
    }

    /**
     * Identify gaps in data that need web search
     *
     * @param array $extracted    Extracted data
     * @param array $db_enriched  Database enrichment
     * @return array Missing fields
     */
    public function identify_gaps($extracted, $db_enriched) {
        $required_fields = array(
            'company.description' => 'Company description',
            'company.founded' => 'Company founding year',
            'company.headquarters' => 'Company headquarters',
            'company.employees' => 'Employee count',
            'company.revenue' => 'Company revenue',
            'event.description' => 'Event description',
            'financials.deal_value' => 'Deal value',
            'market.sector_overview' => 'Sector overview',
            'market.coparables' => 'Comparable transactions',
        );

        $gaps = array();

        foreach ($required_fields as $field => $label) {
            $parts = explode('.', $field);
            $value = null;

            // Check extracted data
            if ($parts[0] === 'company') {
                $value = $extracted['entities']['primary_company'][$parts[1]] ?? null;
                if (empty($value) && isset($db_enriched['wikidata'][$parts[1]])) {
                    $value = $db_enriched['wikidata'][$parts[1]];
                }
            } elseif ($parts[0] === 'financials') {
                $value = $extracted['financials'][$parts[1]]['amount'] ?? null;
            }

            if (empty($value)) {
                $gaps[] = array(
                    'field' => $field,
                    'label' => $label,
                );
            }
        }

        return $gaps;
    }

    /**
     * Use Claude to search web for missing data
     *
     * @param WP_Post $post      The post
     * @param array   $extracted Extracted data
     * @param array   $gaps      Missing fields
     * @return array Enriched data from web
     */
    public function claude_web_search($post, $extracted, $gaps) {
        if (empty($this->anthropic_key)) {
            return array();
        }

        $company = $extracted['entities']['primary_company']['name'] ?? '';
        $event_type = $extracted['event']['type'] ?? '';
        $gap_labels = array_column($gaps, 'label');

        $prompt = $this->build_web_search_prompt($post->post_title, $company, $event_type, $gap_labels);

        return $this->call_claude_with_search($prompt);
    }

    /**
     * Build prompt for Claude web search
     *
     * @param string $title      Article title
     * @param string $company    Company name
     * @param string $event_type Event type
     * @param array  $gaps       Missing data labels
     * @return string The prompt
     */
    private function build_web_search_prompt($title, $company, $event_type, $gaps) {
        $gaps_list = implode("\n- ", $gaps);

        return <<<PROMPT
You are researching data for a financial intelligence brief.

ARTICLE: {$title}
COMPANY: {$company}
EVENT TYPE: {$event_type}

I need to find the following missing data:
- {$gaps_list}

Search the web and provide verified data for each item. Return JSON:
{
  "company": {
    "description": {"value": "", "source": "url", "confidence": "high|medium|low"},
    "founded": {"value": "", "source": "url", "confidence": "high|medium|low"},
    "headquarters": {"value": "", "source": "url", "confidence": "high|medium|low"},
    "employees": {"value": "", "source": "url", "confidence": "high|medium|low"},
    "revenue": {"value": "", "source": "url", "confidence": "high|medium|low"},
    "business_model": {"value": "", "source": "url", "confidence": "high|medium|low"}
  },
  "event": {
    "description": {"value": "", "source": "url", "confidence": "high|medium|low"}
  },
  "financials": {
    "deal_value": {"value": "", "source": "url", "confidence": "high|medium|low"},
    "valuation": {"value": "", "source": "url", "confidence": "high|medium|low"}
  },
  "market": {
    "sector_overview": {"value": "", "source": "url", "confidence": "high|medium|low"},
    "market_size": {"value": "", "source": "url", "confidence": "high|medium|low"},
    "growth_rate": {"value": "", "source": "url", "confidence": "high|medium|low"}
  },
  "sources": [
    {"name": "", "url": "", "type": "primary|financial|news", "date": ""}
  ]
}

Prioritize: Official filings > Company website > Major news outlets > Other sources.
Include source URLs for verification. Use null for truly unavailable data.
PROMPT;
    }

    /**
     * Deep search for still-missing critical data
     *
     * @param WP_Post $post      The post
     * @param array   $extracted Extracted data
     * @param array   $missing   Still missing fields
     * @return array Additional enriched data
     */
    public function claude_deep_search($post, $extracted, $missing) {
        if (empty($this->anthropic_key) || empty($missing)) {
            return array();
        }

        $company = $extracted['entities']['primary_company']['name'] ?? '';

        $prompt = <<<PROMPT
CRITICAL: I still need data for a financial intelligence brief. Search exhaustively.

ARTICLE: {$post->post_title}
COMPANY: {$company}

MISSING (REQUIRED):
PROMPT;

        foreach ($missing as $field) {
            $prompt .= "\n- " . $field;
        }

        $prompt .= <<<PROMPT


Try alternative search strategies:
1. Search for parent company or subsidiaries
2. Search for competitor data to estimate
3. Search for industry reports mentioning the company
4. Search for regulatory filings
5. Search for press releases

If exact data unavailable, provide reasonable estimates with explanation.
Return same JSON format as before with source citations.
PROMPT;

        return $this->call_claude_with_search($prompt);
    }

    /**
     * Call Claude API
     *
     * @param string $prompt The prompt
     * @return array|WP_Error Response data
     */
    private function call_claude($prompt) {
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

        // Try to parse JSON from response
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json) {
                return $json;
            }
        }

        return new WP_Error('parse_error', 'Could not parse Claude response');
    }

    /**
     * Call Claude with web search capability
     *
     * @param string $prompt The prompt
     * @return array Response data
     */
    private function call_claude_with_search($prompt) {
        // For now, use regular Claude call
        // In production, this would use Claude's web search tool
        $result = $this->call_claude($prompt);

        if (is_wp_error($result)) {
            return array();
        }

        return $result;
    }

    /**
     * Cross-validate data from multiple sources
     *
     * @param array $extracted   Extracted data
     * @param array $db_enriched Database enrichment
     * @return array Validated data with confidence levels
     */
    public function cross_validate($extracted, $db_enriched) {
        $validated = array();

        // Company data validation
        $company_sources = array();

        // From extraction
        if (!empty($extracted['entities']['primary_company'])) {
            $company_sources['extracted'] = $extracted['entities']['primary_company'];
        }

        // From Wikidata
        if (!empty($db_enriched['wikidata'])) {
            $company_sources['wikidata'] = $db_enriched['wikidata'];
        }

        // From Alpha Vantage
        if (!empty($db_enriched['stock_data'])) {
            $company_sources['alpha_vantage'] = $db_enriched['stock_data'];
        }

        // Merge with confidence scoring
        $validated['company'] = $this->merge_with_confidence($company_sources, array(
            'name', 'description', 'founded', 'headquarters', 'industry', 'employees', 'revenue', 'website'
        ));

        // Financial data validation
        $validated['financials'] = array(
            'deal_value' => $this->validate_financial_figure(
                $extracted['financials']['deal_value'] ?? array(),
                $db_enriched
            ),
            'valuation' => $this->validate_financial_figure(
                $extracted['financials']['valuation'] ?? array(),
                $db_enriched
            ),
        );

        return $validated;
    }

    /**
     * Merge data from multiple sources with confidence scoring
     *
     * @param array $sources Source data arrays
     * @param array $fields  Fields to merge
     * @return array Merged data with confidence
     */
    private function merge_with_confidence($sources, $fields) {
        $merged = array();

        foreach ($fields as $field) {
            $values = array();
            $source_names = array();

            foreach ($sources as $source_name => $source_data) {
                if (!empty($source_data[$field])) {
                    $values[] = $source_data[$field];
                    $source_names[] = $source_name;
                }
            }

            if (empty($values)) {
                $merged[$field] = array(
                    'value' => null,
                    'confidence' => 'low',
                    'sources' => array(),
                );
                continue;
            }

            // Use first non-empty value
            $value = $values[0];

            // Determine confidence
            $confidence = 'low';
            if (count($values) >= 2) {
                $confidence = 'high';
            } elseif (in_array('wikidata', $source_names) || in_array('alpha_vantage', $source_names)) {
                $confidence = 'medium';
            }

            $merged[$field] = array(
                'value' => $value,
                'confidence' => $confidence,
                'sources' => $source_names,
            );
        }

        return $merged;
    }

    /**
     * Validate financial figure
     *
     * @param array $figure    The figure data
     * @param array $enriched  Enrichment data
     * @return array Validated figure
     */
    private function validate_financial_figure($figure, $enriched) {
        if (empty($figure['amount'])) {
            return array(
                'amount' => null,
                'currency' => 'USD',
                'unit' => 'million',
                'confidence' => 'low',
                'sources' => array(),
            );
        }

        return array(
            'amount' => $figure['amount'],
            'currency' => $figure['currency'] ?? 'USD',
            'unit' => $figure['unit'] ?? 'million',
            'confidence' => 'medium', // From article extraction
            'sources' => array('article'),
        );
    }

    /**
     * Merge enrichment data
     *
     * @param array $db_enriched  Database enrichment
     * @param array $web_enriched Web search enrichment
     * @return array Merged enrichment
     */
    private function merge_enrichment($db_enriched, $web_enriched) {
        if (empty($web_enriched)) {
            return $db_enriched;
        }

        return array_merge_recursive($db_enriched, $web_enriched);
    }

    /**
     * Merge intelligence data
     *
     * @param array $intelligence   Current intelligence
     * @param array $deep_enriched  Deep search results
     * @return array Merged intelligence
     */
    private function merge_intelligence($intelligence, $deep_enriched) {
        if (empty($deep_enriched)) {
            return $intelligence;
        }

        // Merge company data
        if (!empty($deep_enriched['company'])) {
            foreach ($deep_enriched['company'] as $field => $data) {
                if (!empty($data['value']) && empty($intelligence['entities']['primary_company'][$field])) {
                    $intelligence['entities']['primary_company'][$field] = $data['value'];
                }
            }
        }

        // Merge market data
        if (!empty($deep_enriched['market'])) {
            if (!isset($intelligence['market_context'])) {
                $intelligence['market_context'] = array();
            }
            foreach ($deep_enriched['market'] as $field => $data) {
                if (!empty($data['value'])) {
                    $intelligence['market_context'][$field] = $data['value'];
                }
            }
        }

        // Add sources
        if (!empty($deep_enriched['sources'])) {
            if (!isset($intelligence['sources'])) {
                $intelligence['sources'] = array();
            }
            $intelligence['sources'] = array_merge($intelligence['sources'], $deep_enriched['sources']);
        }

        return $intelligence;
    }

    /**
     * Build final intelligence structure
     *
     * @param WP_Post $post      The post
     * @param array   $extracted Extracted data
     * @param array   $validated Validated enrichment
     * @return array Intelligence structure
     */
    private function build_intelligence_structure($post, $extracted, $validated) {
        $company = $validated['company'] ?? array();

        return array(
            'extraction_date' => current_time('mysql'),
            'extraction_version' => '1.0',
            'post_id' => $post->ID,

            'event' => array(
                'type' => $extracted['event']['type'] ?? 'other',
                'subtype' => $extracted['event']['subtype'] ?? '',
                'status' => $extracted['event']['status'] ?? 'announced',
                'title' => $extracted['event']['title'] ?? $post->post_title,
                'description' => $extracted['event']['description'] ?? '',
            ),

            'entities' => array(
                'primary_company' => array(
                    'name' => $this->get_validated_value($company, 'name', $extracted['entities']['primary_company']['name'] ?? ''),
                    'ticker' => $extracted['entities']['primary_company']['ticker'] ?? '',
                    'type' => $extracted['entities']['primary_company']['type'] ?? 'private',
                    'industry' => $this->get_validated_value($company, 'industry', ''),
                    'country' => $extracted['entities']['primary_company']['country'] ?? '',
                    'description' => $this->get_validated_value($company, 'description', ''),
                    'founded' => $this->get_validated_value($company, 'founded', ''),
                    'headquarters' => $this->get_validated_value($company, 'headquarters', ''),
                    'employees' => $this->get_validated_value($company, 'employees', ''),
                    'revenue' => $this->get_validated_value($company, 'revenue', ''),
                    'website' => $this->get_validated_value($company, 'website', ''),
                ),
                'secondary_companies' => $extracted['entities']['secondary_companies'] ?? array(),
                'people' => $extracted['entities']['people'] ?? array(),
                'advisors' => $extracted['entities']['advisors'] ?? array(),
            ),

            'financials' => array(
                'deal_value' => $validated['financials']['deal_value'] ?? $extracted['financials']['deal_value'] ?? array(),
                'valuation' => $validated['financials']['valuation'] ?? $extracted['financials']['valuation'] ?? array(),
                'other_figures' => $extracted['financials']['other_figures'] ?? array(),
            ),

            'dates' => $extracted['dates'] ?? array(),
            'geography' => $extracted['geography'] ?? array(),
            'key_quotes' => $extracted['key_quotes'] ?? array(),

            'classification' => array(
                'sector' => $this->get_validated_value($company, 'industry', ''),
                'geography' => $extracted['geography']['primary_region'] ?? '',
            ),

            'confidence_level' => $this->calculate_overall_confidence($validated),
            'sources' => array(),
        );
    }

    /**
     * Get validated value or fallback
     *
     * @param array  $validated Validated data
     * @param string $field     Field name
     * @param mixed  $fallback  Fallback value
     * @return mixed
     */
    private function get_validated_value($validated, $field, $fallback = '') {
        if (isset($validated[$field]['value']) && !empty($validated[$field]['value'])) {
            return $validated[$field]['value'];
        }
        return $fallback;
    }

    /**
     * Calculate overall confidence level
     *
     * @param array $validated Validated data
     * @return string Confidence level
     */
    private function calculate_overall_confidence($validated) {
        $confidence_scores = array('high' => 3, 'medium' => 2, 'low' => 1);
        $total = 0;
        $count = 0;

        foreach ($validated as $section) {
            if (is_array($section)) {
                foreach ($section as $field) {
                    if (isset($field['confidence'])) {
                        $total += $confidence_scores[$field['confidence']] ?? 1;
                        $count++;
                    }
                }
            }
        }

        if ($count === 0) {
            return 'low';
        }

        $avg = $total / $count;

        if ($avg >= 2.5) return 'high';
        if ($avg >= 1.5) return 'medium';
        return 'low';
    }

    /**
     * Check completeness of intelligence data
     *
     * @param array $intelligence Intelligence data
     * @return array Completeness status
     */
    public function check_completeness($intelligence) {
        $required = array(
            'Company Name' => !empty($intelligence['entities']['primary_company']['name']),
            'Company Description' => !empty($intelligence['entities']['primary_company']['description']),
            'Event Type' => !empty($intelligence['event']['type']),
            'Event Description' => !empty($intelligence['event']['description']),
            'Industry/Sector' => !empty($intelligence['entities']['primary_company']['industry']) ||
                                 !empty($intelligence['classification']['sector']),
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
}
