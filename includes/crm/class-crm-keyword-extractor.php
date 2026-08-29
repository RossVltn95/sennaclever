<?php
/**
 * CRM Keyword Extractor
 * Hybrid extraction using regex + Claude API for post keywords
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Keyword_Extractor {

    private $claude_api = null;
    private $cities_database = [];

    public function __construct() {
        $this->init_claude_api();
        $this->init_cities_database();
    }

    /**
     * Initialize Claude API Manager (standard pattern across codebase)
     */
    private function init_claude_api() {
        if (class_exists('SFFC_Claude_API_Manager')) {
            $this->claude_api = SFFC_Claude_API_Manager::get_instance();
        }
    }

    /**
     * Initialize cities database for location matching
     */
    private function init_cities_database() {
        $this->cities_database = [
            'Paris', 'London', 'New York', 'NYC', 'Frankfurt', 'Amsterdam', 'Zurich',
            'Geneva', 'Luxembourg', 'Brussels', 'Dublin', 'Milan', 'Madrid', 'Barcelona',
            'Berlin', 'Munich', 'Vienna', 'Copenhagen', 'Stockholm', 'Oslo', 'Helsinki',
            'Singapore', 'Hong Kong', 'Tokyo', 'Dubai', 'Sydney', 'Toronto', 'Chicago',
            'San Francisco', 'Boston', 'Los Angeles', 'Seattle', 'Austin', 'Miami'
        ];
    }

    /**
     * Extract keywords from post description (hybrid approach)
     *
     * @param string $description Post description
     * @param array $post_data Optional post data for context
     * @return array Categorized keywords
     */
    public function extract_keywords($description, $post_data = []) {
        $keywords = [];
        $description = trim((string) $description);
        if ($description === '') {
            return [];
        }

        // Step 1: Extract structured data with regex (instant, 100% accuracy)
        $regex_keywords = $this->extract_with_regex($description, $post_data);
        $keywords = array_merge($keywords, $regex_keywords);

        // Step 2: If the keyword set is thin or weak, call Claude for contextual extraction
        if ($this->should_call_claude_for_keywords($keywords) && $this->has_api_key()) {
            $claude_keywords = $this->extract_with_claude($description, $post_data);
            $keywords = array_merge($keywords, $claude_keywords);
        }

        // Step 3: Deduplicate and prioritize
        $keywords = $this->deduplicate_keywords($keywords);
        $keywords = $this->filter_low_value_keywords($keywords);

        // Step 4: Limit to 10 max, prioritized by category
        $keywords = $this->prioritize_keywords($keywords, 10);

        return $keywords;
    }

    /**
     * Extract keywords using regex patterns (structured data)
     */
    private function extract_with_regex($description, $post_data) {
        $keywords = [];

        // Detect the role archetype first so the extractor can bias toward the right lane.
        $archetype_keywords = $this->extract_archetype_keywords($description, $post_data);
        $keywords = array_merge($keywords, $archetype_keywords);

        // Extract role / firm / sector context first
        $context_keywords = $this->extract_context_keywords($description, $post_data);
        $keywords = array_merge($keywords, $context_keywords);

        // Extract high-value role signals and core investing phrases
        $core_signal_keywords = $this->extract_core_role_signals($description, $post_data);
        $keywords = array_merge($keywords, $core_signal_keywords);

        // Mine recurring section-weighted phrases from the JD itself
        $phrase_keywords = $this->extract_section_weighted_phrase_keywords($description);
        $keywords = array_merge($keywords, $phrase_keywords);

        // Extract salary/compensation
        $salary_keywords = $this->extract_salary($description);
        $keywords = array_merge($keywords, $salary_keywords);

        // Extract experience level
        $experience_keywords = $this->extract_experience($description);
        $keywords = array_merge($keywords, $experience_keywords);

        // Extract locations from cities database
        $location_keywords = $this->extract_locations($description, $post_data);
        $keywords = array_merge($keywords, $location_keywords);

        // Extract work arrangement
        $work_keywords = $this->extract_work_arrangement($description);
        $keywords = array_merge($keywords, $work_keywords);

        // Extract finance skills and tools
        $skill_keywords = $this->extract_skills($description, $post_data);
        $keywords = array_merge($keywords, $skill_keywords);

        // Extract language requirements
        $language_keywords = $this->extract_languages($description);
        $keywords = array_merge($keywords, $language_keywords);

        // Extract certifications and credentials that materially affect fit.
        $credential_keywords = $this->extract_credentials($description);
        $keywords = array_merge($keywords, $credential_keywords);

        // Extract process / application signals
        $process_keywords = $this->extract_process_signals($description);
        $keywords = array_merge($keywords, $process_keywords);

        // Extract deadlines
        $deadline_keywords = $this->extract_deadlines($description);
        $keywords = array_merge($keywords, $deadline_keywords);

        return $keywords;
    }

    private function detect_role_archetypes($description, $post_data = []) {
        $haystack = strtolower(trim(
            $description . ' ' .
            ($post_data['role_title'] ?? '') . ' ' .
            ($post_data['sector'] ?? '') . ' ' .
            ($post_data['seniority'] ?? '') . ' ' .
            ($post_data['company'] ?? '')
        ));
        $sector = strtolower(trim((string) ($post_data['sector'] ?? '')));

        $archetypes = [
            'private_equity' => [
                '/\bprivate equity\b/i',
                '/\bbuyout\b/i',
                '/\bgrowth equity\b/i',
                '/\bportfolio compan(?:y|ies)\b/i',
                '/\bvalue creation\b/i',
                '/\binvestment committee\b/i',
                '/\bfundraising\b/i',
                '/\bplacement agent\b/i',
                '/\binvestor relations?\b/i',
                '/\bddqs?\b/i',
                '/\broadshows?\b/i',
                '/\bsyndication\b/i',
                '/\bprivate credit\b/i',
                '/\bcapital solutions?\b/i',
                '/\binvestment grade debt\b/i',
                '/\breal estate finance\b/i',
                '/\besg\b/i',
                '/\bsustainability\b/i',
            ],
            'venture_capital' => [
                '/\bventure capital\b/i',
                '/\bearly[- ]stage\b/i',
                '/\bseed\b/i',
                '/\bseries a\b/i',
                '/\bseries b\b/i',
                '/\bstart-?up ecosystem\b/i',
                '/\bfounders?\b/i',
            ],
            'asset_management' => [
                '/\basset management\b/i',
                '/\binvestment management\b/i',
                '/\bportfolio management\b/i',
                '/\bpublic equities\b/i',
                '/\bequity team\b/i',
            ],
            'buy_side_research' => [
                '/\bbuy-side\b/i',
                '/\binvestment research\b/i',
                '/\bidea generation\b/i',
                '/\bfundamental analysis\b/i',
                '/\btarget prices?\b/i',
                '/\binvestment theses?\b/i',
            ],
            'sell_side_research' => [
                '/\bsell-side\b/i',
                '/\bthematic research\b/i',
                '/\bclient marketing\b/i',
                '/\binstitutional investors?\b/i',
                '/\bconference calls?\b/i',
                '/\broadshows?\b/i',
                '/\becm\b/i',
                '/\bcorporate broking\b/i',
            ],
            'institutional_investing' => [
                '/\breal assets?\b/i',
                '/\bresponsible investment\b/i',
                '/\bstewardship\b/i',
                '/\bvoting processes?\b/i',
                '/\binvestment governance\b/i',
                '/\bportfolio managers?\b/i',
                '/\binvestment groups?\b/i',
            ],
            'private_credit' => [
                '/\bprivate credit\b/i',
                '/\bcredit\b/i',
                '/\breinsurance\b/i',
                '/\binsurance solutions?\b/i',
                '/\bdebt\b/i',
            ],
        ];

        $scores = [];
        foreach ($archetypes as $archetype => $patterns) {
            $score = 0;
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $haystack)) {
                    $score++;
                }
            }
            if ($archetype === 'private_equity' && in_array($sector, ['pe', 'private_credit', 'real_estate', 'infrastructure'], true)) {
                $score += 3;
            }
            if ($score > 0) {
                $scores[$archetype] = $score;
            }
        }

        arsort($scores);
        return $scores;
    }

    private function extract_archetype_keywords($description, $post_data = []) {
        $scores = $this->detect_role_archetypes($description, $post_data);
        if (empty($scores)) {
            return [];
        }

        $top_archetypes = array_slice(array_keys($scores), 0, 2);
        $haystack = strtolower($description . ' ' . ($post_data['role_title'] ?? '') . ' ' . ($post_data['sector'] ?? ''));
        $definitions = $this->get_archetype_keyword_definitions();
        $keywords = [];

        foreach ($top_archetypes as $archetype) {
            foreach ((array) ($definitions[$archetype] ?? []) as $definition) {
                $pattern = (string) ($definition['pattern'] ?? '');
                if ($pattern !== '' && !preg_match($pattern, $haystack)) {
                    continue;
                }

                $keywords[] = [
                    'label' => (string) $definition['label'],
                    'type' => (string) ($definition['type'] ?? $this->categorize_keyword((string) $definition['label'])),
                    'priority' => (int) ($definition['priority'] ?? 9),
                ];
            }
        }

        return $keywords;
    }

    private function get_archetype_keyword_definitions() {
        return [
            'private_equity' => [
                ['label' => 'Private Equity', 'pattern' => '/\bprivate equity|buyout|growth equity\b/i', 'type' => 'type', 'priority' => 10],
                ['label' => 'Due Diligence', 'pattern' => '/\bdue diligence\b/i', 'type' => 'skill', 'priority' => 10],
                ['label' => 'Financial Modelling', 'pattern' => '/\bfinancial modelling|financial modeling|financial models?\b/i', 'type' => 'skill', 'priority' => 10],
                ['label' => 'Valuation', 'pattern' => '/\bvaluation|valuations\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Transaction Execution', 'pattern' => '/\bexecution of transactions|transaction execution|assist with execution\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Value Creation', 'pattern' => '/\bvalue creation\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Portfolio Companies', 'pattern' => '/\bportfolio compan(?:y|ies)\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Investment Committee', 'pattern' => '/\binvestment committee\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Investment Thesis', 'pattern' => '/\binvestment thesis|attractive investment opportunity\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Industry Research', 'pattern' => '/\bindustry research|thematic and industry research\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Global Impact Investing', 'pattern' => '/\bglobal impact|environmental|social challenges\b/i', 'type' => 'type', 'priority' => 8],
                ['label' => 'Fundraising', 'pattern' => '/\bfundraising\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Investor Relations', 'pattern' => '/\binvestor relations?|lp base|lp information requests?\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Investor Due Diligence', 'pattern' => '/\binvestor due diligence\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'DDQs', 'pattern' => '/\bddqs?\b|due diligence questionnaires?\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'RFPs', 'pattern' => '/\brfps?\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Pitch Decks', 'pattern' => '/\bpitch decks?\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'PPMs', 'pattern' => '/\bppms?\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Investor Updates', 'pattern' => '/\binvestor updates?\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Roadshows', 'pattern' => '/\broadshows?\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Institutional Investors', 'pattern' => '/\binstitutional investors?\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'KYC', 'pattern' => '/\bkyc\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'AML', 'pattern' => '/\baml\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Investor Onboarding', 'pattern' => '/\binvestor onboarding\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Syndication', 'pattern' => '/\bsyndication\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Capital Markets', 'pattern' => '/\bcapital markets?\b/i', 'type' => 'type', 'priority' => 8],
                ['label' => 'Private Credit', 'pattern' => '/\bprivate credit\b/i', 'type' => 'type', 'priority' => 9],
                ['label' => 'Private Placements', 'pattern' => '/\bprivate placements?\b|4\(a\)\(2\)|144a\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Investment Grade Debt', 'pattern' => '/\binvestment grade debt|investment grade bonds?\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Structured Equity', 'pattern' => '/\bstructured equity|hybrid instruments?\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Pricing Analysis', 'pattern' => '/\bpricing analyses?|pricing recommendations?\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Term Sheets', 'pattern' => '/\bterm sheet comparisons?|term sheets?\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Credit Analysis', 'pattern' => '/\bcredit analysis|credit memos?\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Covenant Analysis', 'pattern' => '/\bcovenant analysis|financial covenants?\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Relative Value', 'pattern' => '/\brelative value\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Real Estate Finance', 'pattern' => '/\breal estate finance\b/i', 'type' => 'type', 'priority' => 8],
                ['label' => 'Underwriting', 'pattern' => '/\bunderwriting\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Offering Memoranda', 'pattern' => '/\boffering memoranda|offering memorandum\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Comparable Transactions', 'pattern' => '/\bcomparable transactions?\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'ESG', 'pattern' => '/\besg\b/i', 'type' => 'type', 'priority' => 8],
                ['label' => 'Sustainability', 'pattern' => '/\bsustainability\b/i', 'type' => 'type', 'priority' => 8],
                ['label' => 'Carbon Emissions', 'pattern' => '/\bcarbon emissions?\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Decarbonisation', 'pattern' => '/\bdecarbonisation|decarbonization\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Dashboards', 'pattern' => '/\bdashboards?\b/i', 'type' => 'skill', 'priority' => 7],
                ['label' => 'Automation', 'pattern' => '/\bautomation|automate|power automate|n8n\b/i', 'type' => 'skill', 'priority' => 7],
            ],
            'venture_capital' => [
                ['label' => 'Venture Capital', 'pattern' => '/\bventure capital|vc\b/i', 'type' => 'type', 'priority' => 10],
                ['label' => 'Early-Stage Investing', 'pattern' => '/\bearly[- ]stage|seed to series b|seed stage|series a|series b\b/i', 'type' => 'type', 'priority' => 10],
                ['label' => 'Deal Sourcing', 'pattern' => '/\bdeal sourcing|deal sources?\b/i', 'type' => 'skill', 'priority' => 10],
                ['label' => 'Due Diligence', 'pattern' => '/\bdue diligence\b/i', 'type' => 'skill', 'priority' => 10],
                ['label' => 'Investment Analysis', 'pattern' => '/\binvestment analysis|analyse investment opportunities|analyzing early[- ]stage companies\b/i', 'type' => 'skill', 'priority' => 10],
                ['label' => 'Startup Ecosystem', 'pattern' => '/\bstart-?up ecosystem|entrepreneurs\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Founder Relationships', 'pattern' => '/\bfounders?\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Business Models', 'pattern' => '/\bbusiness models?\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Unit Economics', 'pattern' => '/\bunit economics\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Portfolio Support', 'pattern' => '/\bportfolio support|support founders|value-creation\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'AI', 'pattern' => '/\bartificial intelligence|\bai\b/i', 'type' => 'type', 'priority' => 8],
                ['label' => 'Fintech', 'pattern' => '/\bfintech\b/i', 'type' => 'type', 'priority' => 8],
                ['label' => 'Enterprise Software', 'pattern' => '/\benterprise\b/i', 'type' => 'type', 'priority' => 8],
            ],
            'asset_management' => [
                ['label' => 'Investment Management', 'pattern' => '/\binvestment management|asset management\b/i', 'type' => 'type', 'priority' => 10],
                ['label' => 'Portfolio Management', 'pattern' => '/\bportfolio management|portfolio managers?\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Capital Markets', 'pattern' => '/\bcapital markets?\b/i', 'type' => 'type', 'priority' => 8],
                ['label' => 'Financial Modelling', 'pattern' => '/\bfinancial modelling|financial modeling\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Valuation Analysis', 'pattern' => '/\bvaluation analysis|valuations?\b/i', 'type' => 'skill', 'priority' => 9],
            ],
            'buy_side_research' => [
                ['label' => 'Buy-Side Research', 'pattern' => '/\bbuy-side\b/i', 'type' => 'type', 'priority' => 10],
                ['label' => 'Fundamental Analysis', 'pattern' => '/\bfundamental analysis\b/i', 'type' => 'skill', 'priority' => 10],
                ['label' => 'Idea Generation', 'pattern' => '/\bidea generation|new ideas\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Portfolio Monitoring', 'pattern' => '/\bmonitoring existing positions|maintaining coverage\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Industry Analysis', 'pattern' => '/\bindustry analysis\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Financial Models', 'pattern' => '/\bfinancial models?\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Investment Thesis', 'pattern' => '/\binvestment theses?|improving dynamics\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Management Meetings', 'pattern' => '/\bmanagement through meetings|company management\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Target Prices', 'pattern' => '/\btarget prices?\b/i', 'type' => 'skill', 'priority' => 8],
            ],
            'sell_side_research' => [
                ['label' => 'Sell-Side Research', 'pattern' => '/\bsell-side\b/i', 'type' => 'type', 'priority' => 10],
                ['label' => 'Thematic Research', 'pattern' => '/\bthematic research\b/i', 'type' => 'skill', 'priority' => 10],
                ['label' => 'Institutional Investors', 'pattern' => '/\binstitutional investors?\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Client Marketing', 'pattern' => '/\bclient marketing|client dialogue\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Management Relationships', 'pattern' => '/\bmanagement teams?\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Conference Calls', 'pattern' => '/\bconference calls?\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Investable Ideas', 'pattern' => '/\binvestable ideas?\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Sector Positioning', 'pattern' => '/\bsector positioning\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Sustainability Investing', 'pattern' => '/\bsustainability investing|sustainability narrative|sustainability\b/i', 'type' => 'type', 'priority' => 8],
                ['label' => 'Capital Markets', 'pattern' => '/\bfinancial markets|ecm|capital markets?\b/i', 'type' => 'type', 'priority' => 8],
            ],
            'institutional_investing' => [
                ['label' => 'Investment Research', 'pattern' => '/\binvestment research\b/i', 'type' => 'skill', 'priority' => 10],
                ['label' => 'Investment Due Diligence', 'pattern' => '/\binvestment due diligence|robust investment due diligence\b/i', 'type' => 'skill', 'priority' => 10],
                ['label' => 'Portfolio Oversight', 'pattern' => '/\bmanagement and oversight of multiple investment portfolios|portfolio oversight\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Macroeconomic Analysis', 'pattern' => '/\bmacroeconomic strategy analysis|macroeconomic\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Real Assets', 'pattern' => '/\breal assets?\b/i', 'type' => 'type', 'priority' => 9],
                ['label' => 'Investment Recommendations', 'pattern' => '/\binvestment recommendations?\b/i', 'type' => 'skill', 'priority' => 9],
                ['label' => 'Responsible Investment', 'pattern' => '/\bresponsible investment\b/i', 'type' => 'type', 'priority' => 9],
                ['label' => 'ESG', 'pattern' => '/\besg\b/i', 'type' => 'type', 'priority' => 9],
                ['label' => 'Stewardship', 'pattern' => '/\bstewardship\b/i', 'type' => 'skill', 'priority' => 8],
                ['label' => 'Investment Governance', 'pattern' => '/\binvestment governance|governance arrangements\b/i', 'type' => 'skill', 'priority' => 8],
            ],
        ];
    }

    private function extract_context_keywords($description, $post_data) {
        $keywords = [];

        $role_title = trim((string) ($post_data['role_title'] ?? ''));
        $company = trim((string) ($post_data['company'] ?? ''));
        $sector = trim((string) ($post_data['sector'] ?? ''));
        $seniority = trim((string) ($post_data['seniority'] ?? ''));
        $text = strtolower($description . ' ' . $role_title . ' ' . $company);

        $sector_map = [
            'pe' => 'Private Equity',
            'ib' => 'Investment Banking',
            'vc' => 'Venture Capital',
            'hedge_fund' => 'Hedge Fund',
            'asset_management' => 'Investment Management',
            'private_credit' => 'Private Credit',
            'family_office' => 'Family Office',
            'consulting' => 'Consulting',
            'corporate' => 'Corporate Finance',
            'fintech' => 'FinTech',
            'real_estate' => 'Real Estate',
            'infrastructure' => 'Infrastructure',
        ];

        if ($sector !== '' && !empty($sector_map[$sector])) {
            $keywords[] = [
                'label' => $sector_map[$sector],
                'type' => 'type',
                'priority' => 8
            ];
        }

        if (preg_match('/\b(private equity|buyout|growth equity|lbo)\b/i', $text)) {
            $keywords[] = ['label' => 'Private Equity', 'type' => 'type', 'priority' => 8];
        } elseif (preg_match('/\b(investment management|asset management|portfolio management)\b/i', $text)) {
            $keywords[] = ['label' => 'Investment Management', 'type' => 'type', 'priority' => 8];
        } elseif (preg_match('/\b(hedge fund|long\/short|public equities)\b/i', $text)) {
            $keywords[] = ['label' => 'Hedge Fund', 'type' => 'type', 'priority' => 8];
        } elseif (preg_match('/\b(venture capital|seed stage|series a)\b/i', $text)) {
            $keywords[] = ['label' => 'Venture Capital', 'type' => 'type', 'priority' => 8];
        }

        if ($seniority !== '') {
            $seniority_map = [
                'intern' => 'Internship',
                'analyst' => 'Analyst',
                'senior_analyst' => 'Senior Analyst',
                'associate' => 'Associate',
                'senior_associate' => 'Senior Associate',
                'vp' => 'Vice President',
                'senior_vp' => 'Senior Vice President',
                'director' => 'Director',
                'md' => 'Managing Director',
                'board' => 'Board / Advisor',
            ];
            if (!empty($seniority_map[$seniority])) {
                $keywords[] = [
                    'label' => $seniority_map[$seniority],
                    'type' => 'type',
                    'priority' => 7
                ];
            }
        }

        return $keywords;
    }

    private function extract_core_role_signals($description, $post_data = []) {
        $keywords = [];
        $haystack = strtolower($description . ' ' . ($post_data['role_title'] ?? '') . ' ' . ($post_data['sector'] ?? ''));

        $signal_patterns = [
            'Private Equity' => '/\bprivate equity|buyout|growth equity\b/i',
            'Venture Capital' => '/\bventure capital|vc fund|vc\b/i',
            'Early-stage Investing' => '/\bearly[- ]stage|seed to series b|seed stage|series a|series b\b/i',
            'Technology Investing' => '/\btechnology companies|technology investing|tech investing\b/i',
            'Deal Sourcing' => '/\bdeal sourcing|sourcing\b/i',
            'Startup Scouting' => '/\bscouting companies|startup scouting|sourcing early[- ]stage companies\b/i',
            'Due Diligence' => '/\bdue diligence\b/i',
            'Investment Analysis' => '/\banalys(?:e|ing|is) investment opportunities|investment analysis\b/i',
            'Market Research' => '/\bmarket (?:research|conditions?)\b/i',
            'Startup Ecosystem' => '/\bstart-?up ecosystem|tech start-?up ecosystem|ecosystem\b/i',
            'AI' => '/\bartificial intelligence|\bai\b/i',
            'Fintech' => '/\bfintech\b/i',
            'Enterprise Software' => '/\benterprise\b/i',
            'Business Models' => '/\bbusiness models?\b/i',
            'Unit Economics' => '/\bunit economics\b/i',
            'Founder Relationships' => '/\bfounders?\b/i',
            'Investment Memo' => '/\binvestment memo|investment case|investment committee\b/i',
            'Portfolio Support' => '/\bportfolio support|post-investment|value-creation|portfolio companies?\b/i',
            'Financial Analysis' => '/\bfinancial analysis|financial aspects\b/i',
            'Commercial Analysis' => '/\bcommercial analysis|operational aspects\b/i',
            'Investment Thesis' => '/\battractive investment opportunity|investment thesis|what makes an attractive investment\b/i',
            'Investment Committee' => '/\binvestment committee\b/i',
            'Fundraising' => '/\bfundraising\b/i',
            'Investor Relations' => '/\binvestor relations?\b/i',
            'DDQs' => '/\bddqs?\b|due diligence questionnaires?\b/i',
            'Roadshows' => '/\broadshows?\b/i',
            'Institutional Investors' => '/\binstitutional investors?\b/i',
            'KYC' => '/\bkyc\b/i',
            'AML' => '/\baml\b/i',
            'Investor Onboarding' => '/\binvestor onboarding\b/i',
            'Syndication' => '/\bsyndication\b/i',
            'Private Credit' => '/\bprivate credit\b/i',
            'Capital Markets' => '/\bcapital markets?\b/i',
            'Credit Analysis' => '/\bcredit analysis|credit memos?\b/i',
            'Covenant Analysis' => '/\bcovenant analysis|financial covenants?\b/i',
            'Relative Value' => '/\brelative value\b/i',
            'Real Estate Finance' => '/\breal estate finance\b/i',
            'Underwriting' => '/\bunderwriting\b/i',
            'ESG' => '/\besg\b/i',
            'Sustainability' => '/\bsustainability\b/i',
            'Networking' => '/\bnetworking with entrepreneurs\b/i',
        ];

        foreach ($signal_patterns as $label => $pattern) {
            if (preg_match($pattern, $haystack)) {
                $keywords[] = [
                    'label' => $label,
                    'type' => $this->categorize_keyword($label),
                    'priority' => $this->get_core_signal_priority($label)
                ];
            }
        }

        return $keywords;
    }

    private function extract_section_weighted_phrase_keywords($description) {
        $keywords = [];
        $phrase_scores = [];
        $section_lines = $this->get_weighted_section_lines($description);
        $anchor_terms = $this->get_keyword_anchor_terms();
        $blocked_phrases = $this->get_blocked_phrase_labels();
        $stopwords = $this->get_ngram_stopwords();

        foreach ($section_lines as $line_data) {
            $line = (string) ($line_data['text'] ?? '');
            $weight = (int) ($line_data['weight'] ?? 1);
            if ($line === '' || $weight <= 0) {
                continue;
            }

            $tokens = $this->tokenize_phrase_line($line);
            $token_count = count($tokens);
            if ($token_count < 1) {
                continue;
            }

            for ($start = 0; $start < $token_count; $start++) {
                for ($length = 1; $length <= 4 && ($start + $length) <= $token_count; $length++) {
                    $gram_tokens = array_slice($tokens, $start, $length);
                    if (!$this->is_viable_phrase_candidate($gram_tokens, $anchor_terms, $stopwords)) {
                        continue;
                    }

                    $label = $this->normalize_phrase_label($gram_tokens);
                    $label_key = strtolower($label);
                    if ($label_key === '' || in_array($label_key, $blocked_phrases, true)) {
                        continue;
                    }

                    $score = $weight;
                    $score += max(0, $length - 1);

                    foreach ($gram_tokens as $token) {
                        if (in_array($token, $anchor_terms, true)) {
                            $score += 2;
                        }
                    }

                    if (!isset($phrase_scores[$label_key])) {
                        $phrase_scores[$label_key] = [
                            'label' => $label,
                            'score' => 0,
                            'mentions' => 0,
                        ];
                    }

                    $phrase_scores[$label_key]['score'] += $score;
                    $phrase_scores[$label_key]['mentions']++;
                }
            }
        }

        uasort($phrase_scores, function ($a, $b) {
            if ($a['score'] === $b['score']) {
                return $b['mentions'] <=> $a['mentions'];
            }
            return $b['score'] <=> $a['score'];
        });

        $used_roots = [];
        foreach ($phrase_scores as $phrase) {
            $label = (string) ($phrase['label'] ?? '');
            $score = (int) ($phrase['score'] ?? 0);
            $mentions = (int) ($phrase['mentions'] ?? 0);

            if ($label === '' || $score < 6) {
                continue;
            }

            $root = strtolower(preg_replace('/[^a-z0-9]+/', ' ', $label));
            $root = trim(preg_replace('/\s+/', ' ', $root));
            if ($root === '' || isset($used_roots[$root])) {
                continue;
            }

            $priority = min(10, max(6, (int) floor($score / 2) + ($mentions > 1 ? 1 : 0)));
            $keywords[] = [
                'label' => $label,
                'type' => $this->categorize_keyword($label),
                'priority' => $priority,
            ];
            $used_roots[$root] = true;

            if (count($keywords) >= 12) {
                break;
            }
        }

        return $keywords;
    }

    /**
     * Extract salary information
     */
    private function extract_salary($text) {
        $keywords = [];

        // Match patterns: €50K, €50-60K, £45K, $80K, etc.
        $patterns = [
            '/([€£$]\s*\d{1,3}[kK](?:\s*-\s*\d{1,3}[kK])?)/i',
            '/(\d{1,3}[kK]?\s*(?:EUR|GBP|USD|CHF))/i',
            '/(\d{2,3},?\d{3}\s*(?:euros?|pounds?|dollars?))/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $salary = trim($matches[1]);
                // Normalize format
                $salary = preg_replace('/\s+/', '', $salary);
                $salary = str_replace(',', '', $salary);

                $keywords[] = [
                    'label' => $salary,
                    'type' => 'compensation',
                    'priority' => 10
                ];
                break; // Only extract first salary mention
            }
        }

        return $keywords;
    }

    /**
     * Extract experience level
     */
    private function extract_experience($text) {
        $keywords = [];

        $patterns = [
            '/(\d+\+?\s*(?:years?|yrs?)\s*(?:of\s*)?(?:experience|exp))/i' => 'experience',
            '/\b(graduate|entry[- ]level|junior|internship?)\b/i' => 'entry',
            '/\b(senior|sr\.?|experienced)\b/i' => 'senior',
            '/\b(vp|vice president|director|md|managing director)\b/i' => 'leadership',
        ];

        foreach ($patterns as $pattern => $type) {
            if (preg_match($pattern, $text, $matches)) {
                $label = trim($matches[1]);

                // Normalize common terms
                $label = str_ireplace(['yr', 'years', 'year'], 'Years', $label);
                $label = ucfirst(strtolower($label));

                $keywords[] = [
                    'label' => $label,
                    'type' => 'type',
                    'priority' => 7
                ];
                break;
            }
        }

        return $keywords;
    }

    /**
     * Extract locations from text
     */
    private function extract_locations($text, $post_data) {
        $keywords = [];

        // Check post_data first
        if (!empty($post_data['location'])) {
            foreach ($this->cities_database as $city) {
                if (stripos($post_data['location'], $city) !== false) {
                    $keywords[] = [
                        'label' => $city,
                        'type' => 'location',
                        'priority' => 3
                    ];
                    return $keywords; // Only one location
                }
            }
        }

        // Check description text
        foreach ($this->cities_database as $city) {
            if (preg_match('/\b' . preg_quote($city, '/') . '\b/i', $text)) {
                $keywords[] = [
                    'label' => $city,
                    'type' => 'location',
                    'priority' => 3
                ];
                return $keywords; // Only one location
            }
        }

        return $keywords;
    }

    /**
     * Extract work arrangement (Remote, Hybrid, On-site)
     */
    private function extract_work_arrangement($text) {
        $keywords = [];

        if (preg_match('/\b(remote|work from home|wfh|fully remote|100%\s*remote)\b/i', $text)) {
            $keywords[] = [
                'label' => 'Remote',
                'type' => 'type',
                'priority' => 8
            ];
        } elseif (preg_match('/\b(hybrid|flexible|partial remote)\b/i', $text)) {
            $keywords[] = [
                'label' => 'Hybrid',
                'type' => 'type',
                'priority' => 8
            ];
        } elseif (preg_match('/\b(on-?site|office|in-person)\b/i', $text)) {
            $keywords[] = [
                'label' => 'On-site',
                'type' => 'type',
                'priority' => 8
            ];
        }

        return $keywords;
    }

    private function extract_skills($text, $post_data = []) {
        $keywords = [];
        $haystack = strtolower($text . ' ' . ($post_data['role_title'] ?? '') . ' ' . ($post_data['company'] ?? ''));

        $skill_patterns = [
            'Financial Modeling' => '/\b(financial modelling|financial modeling|financial models?)\b/i',
            'Excel' => '/\bexcel\b/i',
            'PowerPoint' => '/\bpowerpoint\b/i',
            'Valuation' => '/\bvaluation\b/i',
            'Equity Research' => '/\bequity research\b/i',
            'Corporate Governance' => '/\bcorporate governance\b/i',
            'Portfolio Management' => '/\bportfolio management\b/i',
            'Regulatory Filings' => '/\bregulatory filings?\b/i',
            'C-Suite Exposure' => '/\bc-?suite\b/i',
            'Trade Execution' => '/\bexecution of orders|booking of trades|trade execution\b/i',
            'Investor Materials' => '/\b(investment memo|pitch book|presentation materials?)\b/i',
            'Pitch Decks' => '/\bpitch decks?\b/i',
            'PPMs' => '/\bppms?\b/i',
            'DDQs' => '/\bddqs?\b|due diligence questionnaires?\b/i',
            'RFPs' => '/\brfps?\b/i',
            'Salesforce' => '/\bsalesforce\b/i',
            'Investment Analysis' => '/\binvestment analysis|analyse investment opportunities\b/i',
            'Commercial Analysis' => '/\bcommercial analysis\b/i',
            'Financial Analysis' => '/\bfinancial analysis\b/i',
            'Idea Generation' => '/\bidea generation|generating and validating new ideas\b/i',
            'Fundamental Analysis' => '/\bfundamental analysis\b/i',
            'Target Prices' => '/\btarget prices?\b/i',
            'Management Meetings' => '/\bmeetings \/ calls with management|company management\b/i',
            'Scenario Testing' => '/\bscenario testing\b/i',
            'Syndication' => '/\bsyndication\b/i',
            'Pricing Analysis' => '/\bpricing analyses?|pricing recommendations?\b/i',
            'Term Sheets' => '/\bterm sheet comparisons?|term sheets?\b/i',
            'Private Placements' => '/\bprivate placements?\b|4\(a\)\(2\)|144a\b/i',
            'Structured Equity' => '/\bstructured equity|hybrid instruments?\b/i',
            'Credit Analysis' => '/\bcredit analysis|credit memos?\b/i',
            'Covenant Analysis' => '/\bcovenant analysis|financial covenants?\b/i',
            'Relative Value' => '/\brelative value\b/i',
            'Real Assets' => '/\breal assets?\b/i',
            'Real Estate Finance' => '/\breal estate finance\b/i',
            'Underwriting' => '/\bunderwriting\b/i',
            'Offering Memoranda' => '/\boffering memoranda|offering memorandum\b/i',
            'Comparable Transactions' => '/\bcomparable transactions?\b/i',
            'Responsible Investment' => '/\bresponsible investment\b/i',
            'ESG' => '/\besg\b/i',
            'Stewardship' => '/\bstewardship\b/i',
            'Sustainability Investing' => '/\bsustainability investing|sustainability narrative|sustainability sector\b/i',
            'Carbon Emissions' => '/\bcarbon emissions?\b/i',
            'Decarbonisation' => '/\bdecarbonisation|decarbonization\b/i',
            'Dashboards' => '/\bdashboards?\b/i',
            'Automation' => '/\bautomation|power automate|n8n|process automation\b/i',
            'Client Marketing' => '/\bclient marketing\b/i',
            'Thematic Research' => '/\bthematic research\b/i',
            'Capital Markets' => '/\bcapital markets?\b/i',
            'FIG' => '/\bfig\b/i',
            'Python' => '/\bpython\b/i',
            'SQL' => '/\bsql\b/i',
            'Bloomberg' => '/\bbloomberg\b/i',
            'Capital IQ' => '/\bcapital iq\b/i',
            'Dealogic' => '/\bdealogic\b/i',
            'PitchBook' => '/\bpitchbook\b/i',
            'Ipreo' => '/\bipreo\b/i',
            'BlackRock Aladdin' => '/\bblackrock aladdin\b|\baladdin\b/i',
        ];

        foreach ($skill_patterns as $label => $pattern) {
            if (preg_match($pattern, $haystack)) {
                $keywords[] = [
                    'label' => $label,
                    'type' => 'skill',
                    'priority' => 7
                ];
            }
        }

        return $keywords;
    }

    private function extract_languages($text) {
        $keywords = [];
        $language_patterns = [
            'Korean' => ['pattern' => '/\bkorean\b/i', 'hard_gate' => '/\bmust be fluent in korean|native or business-level korean|required.*korean\b/i'],
            'English' => ['pattern' => '/\benglish\b/i', 'hard_gate' => '/\bmust be fluent in english|native or business-level english|required.*english\b/i'],
            'Mandarin' => ['pattern' => '/\bmandarin\b/i', 'hard_gate' => '/\bmust be fluent in mandarin|required.*mandarin\b/i'],
            'Japanese' => ['pattern' => '/\bjapanese\b/i', 'hard_gate' => '/\bmust be fluent in japanese|required.*japanese\b/i'],
            'Arabic' => ['pattern' => '/\barabic\b/i', 'hard_gate' => '/\bmust be fluent in arabic|required.*arabic\b/i'],
            'French' => ['pattern' => '/\bfrench\b/i', 'hard_gate' => '/\bmust be fluent in french|required.*french\b/i'],
            'German' => ['pattern' => '/\bgerman\b/i', 'hard_gate' => '/\bmust be fluent in german|required.*german\b/i'],
            'Spanish' => ['pattern' => '/\bspanish\b/i', 'hard_gate' => '/\bmust be fluent in spanish|required.*spanish\b/i'],
            'Italian' => ['pattern' => '/\bitalian\b/i', 'hard_gate' => '/\bmust be fluent in italian|required.*italian\b/i'],
            'Portuguese' => ['pattern' => '/\bportuguese\b/i', 'hard_gate' => '/\bmust be fluent in portuguese|required.*portuguese\b/i'],
        ];

        foreach ($language_patterns as $label => $rules) {
            if (preg_match($rules['pattern'], $text)) {
                $priority = preg_match($rules['hard_gate'], $text) ? 10 : 8;
                $keywords[] = [
                    'label' => $label,
                    'type' => 'skill',
                    'priority' => $priority
                ];
            }
        }

        return $keywords;
    }

    private function extract_credentials($text) {
        $keywords = [];
        $credential_patterns = [
            'CFA' => '/\bcfa\b|chartered financial analyst/i',
            'CAIA' => '/\bcaia\b/i',
            'MBA' => '/\bmba\b|master.?s\/mba/i',
            'ACA' => '/\baca\b/i',
            'ACCA' => '/\bacca\b/i',
        ];

        foreach ($credential_patterns as $label => $pattern) {
            if (preg_match($pattern, $text)) {
                $keywords[] = [
                    'label' => $label,
                    'type' => 'skill',
                    'priority' => 8,
                ];
            }
        }

        return $keywords;
    }

    private function extract_process_signals($text) {
        $keywords = [];

        $process_patterns = [
            'Apply via LinkedIn' => '/\bapply via linkedin\b/i',
            'Cover Letter' => '/\bcover letter\b/i',
            'Travel Required' => '/\btravel(?:ing)?\b/i',
            'Immediate Start' => '/\b(asap|immediate|immediate start)\b/i',
        ];

        foreach ($process_patterns as $label => $pattern) {
            if (preg_match($pattern, $text)) {
                $keywords[] = [
                    'label' => $label,
                    'type' => 'urgent',
                    'priority' => 5
                ];
            }
        }

        return $keywords;
    }

    /**
     * Extract deadlines
     */
    private function extract_deadlines($text) {
        $keywords = [];

        if (preg_match('/\b(urgent|asap|immediate|immediate start)\b/i', $text)) {
            $keywords[] = [
                'label' => 'Urgent',
                'type' => 'urgent',
                'priority' => 9
            ];
        } elseif (preg_match('/\b(rolling|rolling basis)\b/i', $text)) {
            $keywords[] = [
                'label' => 'Rolling',
                'type' => 'urgent',
                'priority' => 5
            ];
        } elseif (preg_match('/(closes?|deadline|apply by)\s*:?\s*([A-Za-z]+\s+\d{1,2})/i', $text, $matches)) {
            $keywords[] = [
                'label' => 'Closes ' . trim($matches[2]),
                'type' => 'urgent',
                'priority' => 7
            ];
        }

        return $keywords;
    }

    /**
     * Extract keywords using Claude API (contextual data)
     */
    private function extract_with_claude($description, $post_data) {
        if (!$this->has_api_key()) {
            return [];
        }

        $archetypes = array_keys($this->detect_role_archetypes($description, $post_data));

        $prompt = "Extract 5-8 strong keywords from this finance job description.\n\n";
        $prompt .= "Prioritise:\n";
        $prompt .= "1. Sector / investing style (Private Equity, Venture Capital, Investment Management, Hedge Fund, Private Credit)\n";
        $prompt .= "2. Recurring workstreams and responsibilities (Deal Sourcing, Due Diligence, Investment Analysis, Market Research, Investment Memo, Portfolio Support)\n";
        $prompt .= "3. Domain focus and market lane (AI, Fintech, Enterprise Software, Energy Transition, Infrastructure, Healthcare, etc.)\n";
        $prompt .= "4. Commercial / investing concepts that define the role (Unit Economics, Business Models, Founder Relationships, Investment Thesis)\n";
        $prompt .= "5. Hard gates and real screening signals when clearly required (German, French, Spanish, Italian, CFA, CAIA, Excel, PowerPoint)\n\n";
        $prompt .= "Reason about what is central to the role, not what is merely mentioned once.\n";
        $prompt .= "Heavily weight responsibilities and required skills over company intro or application logistics.\n";
        $prompt .= "Avoid generic fluff like Team, Opportunity, Dynamic Firm, Leading Company, Resume, CV, London, Office, Hybrid unless they are genuinely central to the role.\n";
        if (!empty($archetypes)) {
            $prompt .= "Detected role archetype hints: " . implode(', ', $archetypes) . ". Use them only if the description supports them.\n";
        }
        $prompt .= "Prefer short multi-word phrases that would genuinely help a candidate understand what this role is about.\n";
        $prompt .= "Description:\n" . substr($description, 0, 2400) . "\n\n";
        $prompt .= "Context:\n" . wp_json_encode([
            'role_title' => $post_data['role_title'] ?? '',
            'company' => $post_data['company'] ?? '',
            'sector' => $post_data['sector'] ?? '',
            'seniority' => $post_data['seniority'] ?? '',
            'location' => $post_data['location'] ?? '',
            'experience_years' => $post_data['experience_years'] ?? '',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
        $prompt .= "Return ONLY a JSON array of strings. Example: [\"Investment Management\", \"Financial Modeling\", \"Korean\", \"Portfolio Management\"]";

        // Use standard Claude API Manager
        $response = $this->claude_api->call_api($prompt, [
            'model' => 'claude-3-haiku-20240307',
            'max_tokens' => 150,
            'temperature' => 0.3,
            'mode' => 'keyword_extraction'
        ]);

        if (empty($response['content'][0]['text'])) {
            return [];
        }

        $text = trim($response['content'][0]['text']);

        // Extract JSON array from response
        if (preg_match('/\[([^\]]+)\]/', $text, $matches)) {
            $json = '[' . $matches[1] . ']';
            $extracted = json_decode($json, true);

            if (is_array($extracted)) {
                $keywords = [];
                foreach ($extracted as $keyword) {
                    if (is_string($keyword) && !empty(trim($keyword))) {
                        $keywords[] = [
                            'label' => trim($keyword),
                            'type' => $this->categorize_keyword($keyword),
                            'priority' => 5
                        ];
                    }
                }
                return $keywords;
            }
        }

        return [];
    }

    /**
     * Categorize a keyword based on its content
     */
    private function categorize_keyword($keyword) {
        $keyword_lower = strtolower($keyword);

        // Benefits
        if (preg_match('/visa|sponsorship|relocation|sign-on|bonus/i', $keyword)) {
            return 'benefit';
        }

        // Skills
        if (preg_match('/python|excel|vba|sql|r|tableau|power bi|modeling|modelling|dcf|lbo|valuation|research|governance|portfolio|due diligence|deal sourcing|unit economics|investment memo|investment thesis|financial analysis|commercial analysis/i', $keyword)) {
            return 'skill';
        }

        if (preg_match('/private equity|investment management|asset management|hedge fund|private credit|venture capital|investment banking|technology investing|early-stage investing/i', $keyword)) {
            return 'type';
        }

        // Type/Category
        return 'type';
    }

    private function shorten_label($label, $max = 40) {
        $label = trim((string) $label);
        if (strlen($label) <= $max) {
            return $label;
        }

        $short = substr($label, 0, $max);
        $short = preg_replace('/\s+\S*$/', '', $short);
        return trim($short);
    }

    /**
     * Deduplicate keywords (keep highest priority)
     */
    private function deduplicate_keywords($keywords) {
        $seen = [];
        $result = [];

        foreach ($keywords as $keyword) {
            $label_lower = strtolower($keyword['label']);

            // Skip if we've seen this or very similar keyword
            $is_duplicate = false;
            foreach ($seen as $seen_label) {
                similar_text($label_lower, $seen_label, $percent);
                if ($percent > 80) {
                    $is_duplicate = true;
                    break;
                }
            }

            if (!$is_duplicate) {
                $seen[] = $label_lower;
                $result[] = $keyword;
            }
        }

        return $result;
    }

    private function filter_low_value_keywords($keywords) {
        $blocked_labels = $this->get_blocked_phrase_labels();

        $result = [];

        foreach ($keywords as $keyword) {
            $label = strtolower(trim((string) ($keyword['label'] ?? '')));
            if ($label === '') {
                continue;
            }

            if (in_array($label, $blocked_labels, true)) {
                continue;
            }

            if (preg_match('/^(team|opportunity|dynamic firm|leading company|office)$/i', $label)) {
                continue;
            }

            $result[] = $keyword;
        }

        return $result;
    }

    /**
     * Prioritize and limit keywords
     */
    private function prioritize_keywords($keywords, $limit = 10) {
        $type_quota = 4;
        $skill_quota = 6;

        // Sort by priority (higher first)
        usort($keywords, function($a, $b) {
            return $b['priority'] - $a['priority'];
        });

        $result = [];
        $type_count = 0;
        $skill_count = 0;

        foreach ($keywords as $keyword) {
            $type = (string) ($keyword['type'] ?? 'type');

            if ($type === 'location' || $type === 'urgent' || $type === 'benefit') {
                continue;
            }

            if ($type === 'type' && $type_count >= $type_quota) {
                continue;
            }

            if ($type === 'skill' && $skill_count >= $skill_quota) {
                continue;
            }

            $result[] = $keyword;

            if ($type === 'type') {
                $type_count++;
            } elseif ($type === 'skill') {
                $skill_count++;
            }

            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    private function should_call_claude_for_keywords($keywords) {
        if (count($keywords) < 6) {
            return true;
        }

        $high_signal = 0;
        foreach ((array) $keywords as $keyword) {
            $type = (string) ($keyword['type'] ?? '');
            $priority = (int) ($keyword['priority'] ?? 0);
            if (in_array($type, ['location', 'urgent', 'benefit'], true)) {
                continue;
            }
            if ($priority >= 8) {
                $high_signal++;
            }
        }

        return $high_signal < 4;
    }

    private function get_core_signal_priority($label) {
        $highest = [
            'Deal Sourcing',
            'Due Diligence',
            'Investment Analysis',
            'Early-stage Investing',
            'Venture Capital',
            'Technology Investing',
        ];

        if (in_array($label, $highest, true)) {
            return 10;
        }

        $high = [
            'Market Research',
            'Business Models',
            'Unit Economics',
            'Investment Memo',
            'Portfolio Support',
            'Financial Analysis',
            'Commercial Analysis',
            'Investment Thesis',
            'Startup Ecosystem',
            'Founder Relationships',
        ];

        if (in_array($label, $high, true)) {
            return 9;
        }

        return 8;
    }

    private function get_weighted_section_lines($description) {
        $lines = preg_split('/\r\n|\r|\n/', (string) $description);
        $weighted = [];
        $current_weight = 1;

        foreach ($lines as $line) {
            $clean = trim(wp_strip_all_tags((string) $line));
            if ($clean === '') {
                continue;
            }

            $lower = strtolower($clean);

            if (preg_match('/^(responsibilities|key responsibilities|what you.ll do|the role|role overview)$/i', $clean)) {
                $current_weight = 4;
                continue;
            }

            if (preg_match('/^(what we.re looking for|required qualifications|skills \& experience|requirements|candidate profile|about you)$/i', $clean)) {
                $current_weight = 4;
                continue;
            }

            if (preg_match('/^(overview|about|about the role|investment team|why this role stands out|we offer)$/i', $clean)) {
                $current_weight = 2;
                continue;
            }

            if (preg_match('/^(to apply|how to apply|application process)$/i', $clean)) {
                $current_weight = 0;
                continue;
            }

            $weighted[] = [
                'text' => $clean,
                'weight' => $current_weight,
            ];
        }

        return $weighted;
    }

    private function tokenize_phrase_line($line) {
        $normalized = strtolower((string) $line);
        $normalized = str_replace(['&amp;', '&'], ' and ', $normalized);
        $normalized = preg_replace('/[^a-z0-9\-\s]+/', ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', trim((string) $normalized));
        if ($normalized === '') {
            return [];
        }

        return array_values(array_filter(explode(' ', $normalized)));
    }

    private function is_viable_phrase_candidate(array $tokens, array $anchor_terms, array $stopwords) {
        $count = count($tokens);
        if ($count < 1 || $count > 4) {
            return false;
        }

        if ($count === 1 && !in_array($tokens[0], ['ai', 'fintech'], true)) {
            return false;
        }

        if (in_array($tokens[0], $stopwords, true) || in_array($tokens[$count - 1], $stopwords, true)) {
            return false;
        }

        $contains_anchor = false;
        foreach ($tokens as $token) {
            if (strlen($token) < 2) {
                return false;
            }
            if (in_array($token, $anchor_terms, true)) {
                $contains_anchor = true;
            }
        }

        return $contains_anchor;
    }

    private function normalize_phrase_label(array $tokens) {
        $acronyms = ['ai', 'vc', 'uk', 'eu', 'saas'];
        $normalized = [];

        foreach ($tokens as $token) {
            if (in_array($token, $acronyms, true)) {
                $normalized[] = strtoupper($token);
                continue;
            }

            if ($token === 'and') {
                $normalized[] = 'and';
                continue;
            }

            $normalized[] = ucfirst($token);
        }

        return trim(implode(' ', $normalized));
    }

    private function get_keyword_anchor_terms() {
        return [
            'venture', 'capital', 'early-stage', 'early', 'stage', 'technology',
            'tech', 'startup', 'start-up', 'sourcing', 'deal', 'deals', 'diligence',
            'investment', 'investing', 'analysis', 'research', 'market', 'markets',
            'ai', 'fintech', 'enterprise', 'software', 'business', 'models',
            'model', 'economics', 'unit', 'founder', 'founders', 'memo',
            'portfolio', 'financial', 'commercial', 'thesis', 'committee',
            'fundraising', 'valuation', 'governance', 'screening', 'scouting',
        ];
    }

    private function get_ngram_stopwords() {
        return [
            'a', 'an', 'the', 'and', 'or', 'for', 'with', 'across', 'into', 'from',
            'of', 'to', 'in', 'on', 'at', 'by', 'our', 'their', 'your', 'this',
            'that', 'these', 'those', 'will', 'can', 'are', 'is', 'be', 'as',
            'how', 'what', 'why', 'who',
        ];
    }

    private function get_blocked_phrase_labels() {
        return [
            'resume required',
            'resume',
            'cv',
            'london',
            'paris',
            'singapore',
            'dubai',
            'new york',
            'remote',
            'hybrid',
            'on-site',
            'apply via linkedin',
            'cover letter',
            'travel required',
            'immediate start',
            'urgent',
            'rolling',
            'team',
            'opportunity',
            'dynamic firm',
            'leading company',
            'office',
            'jobs',
            'piccadilly',
            'work authorisation',
            'uk work authorisation',
        ];
    }

    /**
     * Check if API is configured
     */
    public function has_api_key() {
        return $this->claude_api && $this->claude_api->is_available();
    }
}
