<?php
/**
 * Intelligence Narrative Generator
 *
 * Generates professional narratives for intelligence briefs using Claude AI.
 * Creates executive summaries, strategic analysis, risk assessments, and insights.
 *
 * @package Skill_Farm_Finance_Career
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Intelligence_Narrative_Generator {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Anthropic API key
     */
    private $api_key;

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
        $this->api_key = get_option('sffc_anthropic_key', '');
    }

    /**
     * Generate all narratives for a brief
     *
     * @param array $intelligence Extracted intelligence data
     * @param array $enrichment   Enrichment data from databases
     * @return array Generated narratives
     */
    public function generate_all_narratives($intelligence, $enrichment = array()) {
        $narratives = array();

        // Generate executive summary
        $narratives['executive_summary'] = $this->generate_executive_summary($intelligence, $enrichment);

        // Generate event explanation
        $narratives['event_explanation'] = $this->generate_event_explanation($intelligence);

        // Generate strategic analysis
        $narratives['strategic_analysis'] = $this->generate_strategic_analysis($intelligence, $enrichment);

        // Generate risk assessment
        $narratives['risk_assessment'] = $this->generate_risk_assessment($intelligence, $enrichment);

        // Generate insights and outlook
        $narratives['insights'] = $this->generate_insights($intelligence, $enrichment);

        return $narratives;
    }

    /**
     * Generate executive summary
     *
     * @param array $intelligence Intelligence data
     * @param array $enrichment   Enrichment data
     * @return array Executive summary components
     */
    public function generate_executive_summary($intelligence, $enrichment = array()) {
        $cache_key = 'sffc_exec_summary_' . md5(serialize($intelligence));
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $context = $this->build_context_string($intelligence, $enrichment);

        $prompt = <<<PROMPT
You are a senior financial analyst writing an executive summary for a professional intelligence brief.

CONTEXT:
{$context}

Generate an executive summary with these components. Return ONLY valid JSON:

{
  "overview": "A compelling 3-4 sentence overview that captures the essence of this transaction/event. Write in active voice, be specific with names and figures. No fluff.",

  "key_points": [
    "First critical point the reader must understand",
    "Second critical point with specific detail",
    "Third critical point about implications",
    "Fourth critical point about what to watch"
  ],

  "metrics": [
    {"label": "Deal Value", "value": "$X.XB", "context": "brief context"},
    {"label": "Valuation", "value": "$X.XB", "context": "brief context"},
    {"label": "Premium", "value": "XX%", "context": "to undisturbed price"},
    {"label": "Multiple", "value": "X.Xx", "context": "EV/Revenue or relevant"}
  ],

  "headline_insight": "One sentence that captures the most important non-obvious insight"
}

Rules:
- Use actual figures from the context, not placeholders
- If a figure is unknown, use "Undisclosed" or estimate with "~" prefix
- Write like a Goldman Sachs analyst, not AI
- Be specific and actionable
- Metrics should have 4 items max, use most relevant ones
PROMPT;

        $result = $this->call_claude($prompt);

        if (is_wp_error($result)) {
            // Return basic structure
            return $this->get_fallback_executive_summary($intelligence);
        }

        set_transient($cache_key, $result, $this->cache_duration);

        return $result;
    }

    /**
     * Generate event explanation with educational content
     *
     * @param array $intelligence Intelligence data
     * @return array Event explanation components
     */
    public function generate_event_explanation($intelligence) {
        $event_type = $intelligence['event']['type'] ?? 'transaction';

        $cache_key = 'sffc_event_explain_' . md5($event_type . serialize($intelligence['event']));
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // Get educational content for event type
        $educational = $this->get_educational_content($event_type);

        $context = $this->build_event_context($intelligence);

        $prompt = <<<PROMPT
You are explaining a financial transaction to a smart professional who may not be an expert in this specific type of deal.

EVENT TYPE: {$event_type}
EDUCATIONAL CONTEXT: {$educational}

SPECIFIC TRANSACTION:
{$context}

Generate an explanation with these components. Return ONLY valid JSON:

{
  "educational": "{$educational}",

  "this_transaction": "2-3 sentences explaining what specifically is happening in THIS deal. Be concrete with names, figures, and parties involved.",

  "how_it_works": "3-4 sentences explaining the mechanics of how this specific transaction will work. What are the steps? What needs to happen?",

  "timeline": [
    {"date": "Month Year or Q1 2024", "title": "Event name", "description": "Brief description", "status": "completed|current|pending"},
    {"date": "", "title": "", "description": "", "status": "pending"},
    {"date": "", "title": "", "description": "", "status": "pending"}
  ],

  "key_terms": [
    {"term": "Technical term used", "definition": "Simple explanation"},
    {"term": "", "definition": ""}
  ]
}

Rules:
- Timeline should have 3-5 items showing key milestones
- Key terms should explain 2-4 technical terms relevant to this deal
- Write clearly but don't be condescending
- Use actual dates and figures when known
PROMPT;

        $result = $this->call_claude($prompt);

        if (is_wp_error($result)) {
            return $this->get_fallback_event_explanation($intelligence, $educational);
        }

        // Ensure educational content is included
        if (empty($result['educational'])) {
            $result['educational'] = $educational;
        }

        set_transient($cache_key, $result, $this->cache_duration * 7); // Cache longer for educational

        return $result;
    }

    /**
     * Get educational content for event type
     *
     * @param string $event_type Event type
     * @return string Educational explanation
     */
    private function get_educational_content($event_type) {
        $educational = array(
            'ipo' => 'An Initial Public Offering (IPO) is when a private company offers shares to the public for the first time on a stock exchange. This allows the company to raise capital from public investors, provides liquidity for existing shareholders, and subjects the company to ongoing regulatory disclosure requirements. The process typically involves selecting underwriters, filing registration documents with regulators, conducting a roadshow, and pricing the offering.',

            'acquisition' => 'An acquisition occurs when one company (the acquirer) purchases a controlling stake in another company (the target). This can be achieved through purchasing shares, assets, or a combination. Acquisitions can be friendly (approved by target\'s board) or hostile (directly appealing to shareholders). The acquirer typically pays a premium above the current market price to incentivize shareholders to sell.',

            'merger' => 'A merger is the combination of two companies into a single new legal entity. Unlike an acquisition, mergers are typically structured as a combination of equals, though one company often has more leverage. Shareholders of both companies receive shares in the new combined entity based on an agreed exchange ratio. Mergers require approval from both boards and often regulatory bodies.',

            'fundraise' => 'A capital raise or fundraising round is when a company issues new equity to raise money from investors. For private companies, this is typically through venture capital or private equity rounds (Series A, B, C, etc.). The valuation, amount raised, and terms depend on the company\'s stage, market conditions, and investor appetite. New shares dilute existing shareholders but provide growth capital.',

            'buyout' => 'A Leveraged Buyout (LBO) is an acquisition where a significant portion of the purchase price is financed with debt. The target company\'s assets and cash flows typically serve as collateral for the loans. Private equity firms use LBOs to acquire companies, improve operations, reduce costs, and exit at a profit through sale or IPO. High leverage amplifies returns but increases risk.',

            'spac' => 'A SPAC (Special Purpose Acquisition Company) merger is when a private company goes public by merging with an already-listed shell company. The SPAC raises money through its own IPO, then seeks a target company to acquire. This provides a faster, more certain path to public markets than a traditional IPO, with the valuation negotiated upfront rather than determined by market demand.',

            'spinoff' => 'A spin-off is when a company creates an independent company by distributing shares of a subsidiary or division to existing shareholders. The parent company separates a business unit that can operate independently, often to unlock value, increase focus, or satisfy regulatory requirements. Shareholders typically receive shares in the new company proportional to their holdings.',

            'restructuring' => 'Corporate restructuring involves significant changes to a company\'s structure, operations, or finances to improve performance or address financial distress. This can include debt restructuring, asset sales, workforce reductions, or operational changes. In severe cases, it may occur through bankruptcy proceedings which provide legal protection while the company reorganizes.',

            'listing' => 'A stock exchange listing is when a company\'s shares become tradable on a public exchange. This can occur through an IPO or direct listing (no new shares issued). Listing provides liquidity, establishes a market price, and enables the company to use stock for acquisitions and employee compensation. It also brings disclosure obligations and regulatory oversight.',

            'delisting' => 'Delisting occurs when a company\'s shares are removed from a stock exchange. This can be voluntary (going private, moving exchanges) or involuntary (failing to meet listing requirements). Going-private transactions typically involve a buyout at a premium, while involuntary delistings often result from financial distress or compliance failures.',
        );

        return $educational[$event_type] ?? 'This transaction involves a significant corporate event that may impact the company\'s ownership structure, capital position, or strategic direction. Understanding the specific terms, parties, and mechanics is essential for evaluating its implications.';
    }

    /**
     * Generate strategic analysis
     *
     * @param array $intelligence Intelligence data
     * @param array $enrichment   Enrichment data
     * @return array Strategic analysis components
     */
    public function generate_strategic_analysis($intelligence, $enrichment = array()) {
        $cache_key = 'sffc_strategic_' . md5(serialize($intelligence));
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $context = $this->build_context_string($intelligence, $enrichment);

        $prompt = <<<PROMPT
You are a senior strategy consultant analyzing a corporate transaction for a client brief.

CONTEXT:
{$context}

Generate strategic analysis. Return ONLY valid JSON:

{
  "why_happening": "2-3 sentences explaining the strategic rationale - WHY is this happening now? What's driving this decision? Be specific about market conditions, competitive pressures, or company-specific factors.",

  "rationale": "3-4 sentences on the deeper strategic logic. What does each party gain? What's the strategic vision? How does this fit into broader industry trends?",

  "competitive_implications": "2-3 sentences on how this affects the competitive landscape. Who gains market power? Who is threatened? What might competitors do in response?",

  "synergies": [
    {"title": "Revenue Synergies", "description": "Specific revenue opportunities from the combination"},
    {"title": "Cost Synergies", "description": "Specific cost reduction opportunities"},
    {"title": "Strategic Benefits", "description": "Non-financial strategic advantages"}
  ],

  "winners": [
    {"name": "Party name", "reason": "Why they benefit from this transaction"},
    {"name": "", "reason": ""}
  ],

  "losers": [
    {"name": "Party or competitor name", "reason": "Why they are disadvantaged"},
    {"name": "", "reason": ""}
  ]
}

Rules:
- Be specific with company names, not generic
- Provide concrete, actionable insights
- Winners/losers should include 2-3 each
- Synergies should be realistic and specific to this deal
- Think like an investment banker advising a client
PROMPT;

        $result = $this->call_claude($prompt);

        if (is_wp_error($result)) {
            return $this->get_fallback_strategic_analysis($intelligence);
        }

        set_transient($cache_key, $result, $this->cache_duration);

        return $result;
    }

    /**
     * Generate risk assessment
     *
     * @param array $intelligence Intelligence data
     * @param array $enrichment   Enrichment data
     * @return array Risk assessment components
     */
    public function generate_risk_assessment($intelligence, $enrichment = array()) {
        $cache_key = 'sffc_risks_' . md5(serialize($intelligence));
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $context = $this->build_context_string($intelligence, $enrichment);
        $event_type = $intelligence['event']['type'] ?? 'transaction';

        $prompt = <<<PROMPT
You are a risk analyst assessing a corporate transaction for an investment committee.

EVENT TYPE: {$event_type}

CONTEXT:
{$context}

Generate risk assessment. Return ONLY valid JSON:

{
  "summary": "2-3 sentences summarizing the overall risk profile of this transaction. Is it high/medium/low risk overall? What's the biggest concern?",

  "risks": [
    {
      "title": "Specific risk name",
      "description": "2-3 sentences explaining this risk and its potential impact",
      "severity": "high|medium|low",
      "category": "regulatory|market|execution|financial|operational"
    },
    {
      "title": "",
      "description": "",
      "severity": "",
      "category": ""
    }
  ],

  "mitigation": "2-3 sentences on factors that mitigate the key risks. What protections exist? What gives confidence the deal will succeed?"
}

Rules:
- Include 4-6 specific risks
- At least one risk should be high severity
- Mix of categories (regulatory, market, execution, financial, operational)
- Be specific to this transaction, not generic
- Regulatory risks should mention specific regulators if relevant (FTC, SEC, EU Commission, etc.)
- Execution risks should be specific to this deal type
PROMPT;

        $result = $this->call_claude($prompt);

        if (is_wp_error($result)) {
            return $this->get_fallback_risk_assessment($intelligence);
        }

        set_transient($cache_key, $result, $this->cache_duration);

        return $result;
    }

    /**
     * Generate insights and outlook
     *
     * @param array $intelligence Intelligence data
     * @param array $enrichment   Enrichment data
     * @return array Insights components
     */
    public function generate_insights($intelligence, $enrichment = array()) {
        $cache_key = 'sffc_insights_' . md5(serialize($intelligence));
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $context = $this->build_context_string($intelligence, $enrichment);

        $prompt = <<<PROMPT
You are a senior analyst providing unique insights and forward-looking analysis.

CONTEXT:
{$context}

Generate insights and outlook. Return ONLY valid JSON:

{
  "unique_observations": [
    {"title": "Non-obvious observation 1", "detail": "Why this matters and what it implies"},
    {"title": "Non-obvious observation 2", "detail": "Why this matters and what it implies"},
    {"title": "Non-obvious observation 3", "detail": "Why this matters and what it implies"}
  ],

  "what_to_watch": [
    "Specific thing to monitor - be actionable",
    "Another specific indicator or event to track",
    "Third thing that will signal success or failure",
    "Fourth key metric or milestone"
  ],

  "potential_outcomes": [
    {
      "scenario": "Bull Case",
      "description": "What happens if everything goes well",
      "probability": "likely|possible|unlikely"
    },
    {
      "scenario": "Base Case",
      "description": "Most probable outcome",
      "probability": "likely"
    },
    {
      "scenario": "Bear Case",
      "description": "What happens if things go wrong",
      "probability": "possible|unlikely"
    }
  ],

  "next_steps": [
    {"timeframe": "Near-term (1-3 months)", "event": "Expected milestone", "detail": "What this means"},
    {"timeframe": "Medium-term (3-12 months)", "event": "Expected milestone", "detail": "What this means"},
    {"timeframe": "Long-term (1-2 years)", "event": "Expected outcome", "detail": "What this means"}
  ],

  "outlook_summary": "2-3 sentences capturing your overall view. Be opinionated but balanced. What's your bottom line assessment?"
}

Rules:
- Unique observations should be things a casual reader would miss
- What to watch should be specific and actionable
- Potential outcomes should cover the range of possibilities
- Next steps should have realistic timeframes
- Be forward-looking and analytical, not just descriptive
PROMPT;

        $result = $this->call_claude($prompt);

        if (is_wp_error($result)) {
            return $this->get_fallback_insights($intelligence);
        }

        set_transient($cache_key, $result, $this->cache_duration);

        return $result;
    }

    /**
     * Build context string from intelligence data
     *
     * @param array $intelligence Intelligence data
     * @param array $enrichment   Enrichment data
     * @return string Context string
     */
    private function build_context_string($intelligence, $enrichment = array()) {
        $parts = array();

        // Event info
        $event = $intelligence['event'] ?? array();
        if (!empty($event['type'])) {
            $parts[] = "EVENT TYPE: " . strtoupper($event['type']);
        }
        if (!empty($event['title'])) {
            $parts[] = "HEADLINE: " . $event['title'];
        }
        if (!empty($event['description'])) {
            $parts[] = "DESCRIPTION: " . $event['description'];
        }

        // Primary company
        $company = $intelligence['entities']['primary_company'] ?? array();
        if (!empty($company['name'])) {
            $company_info = "PRIMARY COMPANY: " . $company['name'];
            if (!empty($company['industry'])) {
                $company_info .= " (Industry: " . $company['industry'] . ")";
            }
            if (!empty($company['country'])) {
                $company_info .= " (Country: " . $company['country'] . ")";
            }
            $parts[] = $company_info;

            if (!empty($company['description'])) {
                $parts[] = "COMPANY DESCRIPTION: " . $company['description'];
            }
        }

        // Secondary companies
        $secondary = $intelligence['entities']['secondary_companies'] ?? array();
        if (!empty($secondary)) {
            $sec_names = array_map(function($c) {
                return $c['name'] . (!empty($c['role']) ? ' (' . $c['role'] . ')' : '');
            }, $secondary);
            $parts[] = "OTHER PARTIES: " . implode(', ', $sec_names);
        }

        // Financials
        $financials = $intelligence['financials'] ?? array();
        if (!empty($financials['deal_value']['amount'])) {
            $parts[] = "DEAL VALUE: $" . $financials['deal_value']['amount'] . ' ' . ($financials['deal_value']['unit'] ?? 'million');
        }
        if (!empty($financials['valuation']['amount'])) {
            $parts[] = "VALUATION: $" . $financials['valuation']['amount'] . ' ' . ($financials['valuation']['unit'] ?? 'million');
        }

        // Other figures
        if (!empty($financials['other_figures'])) {
            foreach ($financials['other_figures'] as $fig) {
                if (!empty($fig['label']) && !empty($fig['value'])) {
                    $parts[] = strtoupper($fig['label']) . ": " . $fig['value'];
                }
            }
        }

        // Enrichment data
        if (!empty($enrichment['wikidata'])) {
            $wiki = $enrichment['wikidata'];
            if (!empty($wiki['founded'])) {
                $parts[] = "FOUNDED: " . $wiki['founded'];
            }
            if (!empty($wiki['employees'])) {
                $parts[] = "EMPLOYEES: " . $wiki['employees'];
            }
        }

        if (!empty($enrichment['stock_data'])) {
            $stock = $enrichment['stock_data'];
            if (!empty($stock['market_cap'])) {
                $parts[] = "MARKET CAP: $" . number_format($stock['market_cap']);
            }
            if (!empty($stock['sector'])) {
                $parts[] = "SECTOR: " . $stock['sector'];
            }
        }

        // Geography
        $geo = $intelligence['geography'] ?? array();
        if (!empty($geo['countries_involved'])) {
            $parts[] = "GEOGRAPHY: " . implode(', ', $geo['countries_involved']);
        }

        return implode("\n", $parts);
    }

    /**
     * Build event context string
     *
     * @param array $intelligence Intelligence data
     * @return string Event context
     */
    private function build_event_context($intelligence) {
        $parts = array();

        $event = $intelligence['event'] ?? array();
        $company = $intelligence['entities']['primary_company'] ?? array();
        $financials = $intelligence['financials'] ?? array();

        if (!empty($company['name'])) {
            $parts[] = "Company: " . $company['name'];
        }

        if (!empty($event['title'])) {
            $parts[] = "Event: " . $event['title'];
        }

        if (!empty($event['status'])) {
            $parts[] = "Status: " . ucfirst($event['status']);
        }

        if (!empty($financials['deal_value']['amount'])) {
            $parts[] = "Deal Size: $" . $financials['deal_value']['amount'] . ' ' . ($financials['deal_value']['unit'] ?? 'million');
        }

        $secondary = $intelligence['entities']['secondary_companies'] ?? array();
        if (!empty($secondary)) {
            foreach ($secondary as $sec) {
                if (!empty($sec['name']) && !empty($sec['role'])) {
                    $parts[] = ucfirst($sec['role']) . ": " . $sec['name'];
                }
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Call Claude API
     *
     * @param string $prompt The prompt
     * @return array|WP_Error Response data
     */
    private function call_claude($prompt) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'Anthropic API key not configured');
        }

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'timeout' => 60,
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $this->api_key,
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

        // Parse JSON from response
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json) {
                return $json;
            }
        }

        return new WP_Error('parse_error', 'Could not parse Claude response');
    }

    /**
     * Fallback executive summary
     */
    private function get_fallback_executive_summary($intelligence) {
        $company = $intelligence['entities']['primary_company']['name'] ?? 'The company';
        $event_type = $intelligence['event']['type'] ?? 'transaction';

        return array(
            'overview' => "{$company} has announced a significant {$event_type}. This transaction marks an important strategic milestone for the company and could have meaningful implications for the broader market.",
            'key_points' => array(
                'Transaction details are still emerging',
                'Market reaction will be closely watched',
                'Regulatory approval may be required',
                'Timing and completion remain subject to conditions',
            ),
            'metrics' => array(),
            'headline_insight' => 'Further details are needed to fully assess the implications of this transaction.',
        );
    }

    /**
     * Fallback event explanation
     */
    private function get_fallback_event_explanation($intelligence, $educational) {
        return array(
            'educational' => $educational,
            'this_transaction' => $intelligence['event']['description'] ?? 'Details of this specific transaction are being compiled.',
            'how_it_works' => 'The mechanics of this transaction will depend on the final terms agreed between parties.',
            'timeline' => array(),
            'key_terms' => array(),
        );
    }

    /**
     * Fallback strategic analysis
     */
    private function get_fallback_strategic_analysis($intelligence) {
        return array(
            'why_happening' => 'The strategic rationale for this transaction requires further analysis.',
            'rationale' => 'Multiple factors likely contributed to this decision, including market conditions and competitive dynamics.',
            'competitive_implications' => 'The competitive impact will become clearer as details emerge.',
            'synergies' => array(),
            'winners' => array(),
            'losers' => array(),
        );
    }

    /**
     * Fallback risk assessment
     */
    private function get_fallback_risk_assessment($intelligence) {
        $event_type = $intelligence['event']['type'] ?? 'transaction';

        return array(
            'summary' => "This {$event_type} carries typical risks associated with transactions of this type.",
            'risks' => array(
                array(
                    'title' => 'Execution Risk',
                    'description' => 'The transaction may face challenges in completion or integration.',
                    'severity' => 'medium',
                    'category' => 'execution',
                ),
                array(
                    'title' => 'Market Risk',
                    'description' => 'Market conditions could change before or after completion.',
                    'severity' => 'medium',
                    'category' => 'market',
                ),
                array(
                    'title' => 'Regulatory Risk',
                    'description' => 'Regulatory approval may be required and could face scrutiny.',
                    'severity' => 'medium',
                    'category' => 'regulatory',
                ),
            ),
            'mitigation' => 'Standard transaction protections and due diligence processes are expected to be in place.',
        );
    }

    /**
     * Fallback insights
     */
    private function get_fallback_insights($intelligence) {
        return array(
            'unique_observations' => array(),
            'what_to_watch' => array(
                'Official announcements and filings',
                'Market reaction and analyst commentary',
                'Regulatory developments',
                'Timeline for completion',
            ),
            'potential_outcomes' => array(
                array(
                    'scenario' => 'Base Case',
                    'description' => 'Transaction completes as announced with expected timeline.',
                    'probability' => 'likely',
                ),
            ),
            'next_steps' => array(),
            'outlook_summary' => 'The outlook will depend on execution and market conditions.',
        );
    }
}
