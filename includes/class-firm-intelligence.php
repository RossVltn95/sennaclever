<?php
/**
 * Firm Intelligence
 *
 * Provides company-specific intelligence by querying internal news/deals data
 * with Claude AI fallback for companies without internal coverage.
 *
 * @package SennaCareers
 * @since 11.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Firm_Intelligence {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Cache TTL in seconds (7 days)
     */
    const CACHE_TTL = 604800;

    /**
     * Company aliases for name normalization
     */
    private $company_aliases = array(
        // Investment Banks
        'gs' => 'Goldman Sachs',
        'goldman' => 'Goldman Sachs',
        'ms' => 'Morgan Stanley',
        'morgan stanley' => 'Morgan Stanley',
        'jpm' => 'J.P. Morgan',
        'jpmorgan' => 'J.P. Morgan',
        'jp morgan' => 'J.P. Morgan',
        'bofa' => 'Bank of America',
        'boa' => 'Bank of America',
        'citi' => 'Citigroup',
        'citibank' => 'Citigroup',
        'ubs' => 'UBS',
        'cs' => 'Credit Suisse',
        'db' => 'Deutsche Bank',
        'deutsche' => 'Deutsche Bank',
        'barclays' => 'Barclays',
        'hsbc' => 'HSBC',
        'nomura' => 'Nomura',
        'jefferies' => 'Jefferies',
        'lazard' => 'Lazard',
        'evercore' => 'Evercore',
        'moelis' => 'Moelis',
        'pjt' => 'PJT Partners',
        'rothschild' => 'Rothschild & Co',
        'perella' => 'Perella Weinberg',
        'pwp' => 'Perella Weinberg',
        'centerview' => 'Centerview Partners',
        'guggenheim' => 'Guggenheim',
        'houlihan' => 'Houlihan Lokey',

        // PE Firms
        'blackstone' => 'Blackstone',
        'bx' => 'Blackstone',
        'kkr' => 'KKR',
        'apollo' => 'Apollo Global',
        'carlyle' => 'The Carlyle Group',
        'tpg' => 'TPG',
        'warburg' => 'Warburg Pincus',
        'warburg pincus' => 'Warburg Pincus',
        'bain' => 'Bain Capital',
        'bain capital' => 'Bain Capital',
        'advent' => 'Advent International',
        'permira' => 'Permira',
        'cvc' => 'CVC Capital',
        'eqt' => 'EQT',
        'thoma bravo' => 'Thoma Bravo',
        'vista' => 'Vista Equity',
        'silver lake' => 'Silver Lake',
        'hellman' => 'Hellman & Friedman',
        'h&f' => 'Hellman & Friedman',
        'ga' => 'General Atlantic',
        'general atlantic' => 'General Atlantic',
        'leonard green' => 'Leonard Green',
        'lg' => 'Leonard Green',
        'cinven' => 'Cinven',
        'bc partners' => 'BC Partners',
        'apax' => 'Apax Partners',
        'bridgepoint' => 'Bridgepoint',
        'pai' => 'PAI Partners',
        'ardian' => 'Ardian',
        'hg capital' => 'HgCapital',
        'hg' => 'HgCapital',
        'oakley' => 'Oakley Capital',
        'montagu' => 'Montagu Private Equity',
        'graphite' => 'Graphite Capital',
        'livingbridge' => 'LivingBridge',
        'ldc' => 'LDC',
        'inflexion' => 'Inflexion',
        'bowmark' => 'Bowmark Capital',

        // Asset Managers
        'blackrock' => 'BlackRock',
        'vanguard' => 'Vanguard',
        'fidelity' => 'Fidelity',
        'pimco' => 'PIMCO',
        'schroders' => 'Schroders',
        'abrdn' => 'abrdn',
        'aberdeen' => 'abrdn',
        'm&g' => 'M&G',
        'legal & general' => 'Legal & General',
        'lgim' => 'Legal & General',
        'aviva' => 'Aviva Investors',
        'janus henderson' => 'Janus Henderson',
        'invesco' => 'Invesco',
        't rowe' => 'T. Rowe Price',
        't. rowe price' => 'T. Rowe Price',
        'wellington' => 'Wellington Management',
        'citadel' => 'Citadel',
        'millennium' => 'Millennium',
        'two sigma' => 'Two Sigma',
        'de shaw' => 'D.E. Shaw',
        'd.e. shaw' => 'D.E. Shaw',
        'point72' => 'Point72',
        'bridgewater' => 'Bridgewater',
        'aqr' => 'AQR',
        'man group' => 'Man Group',
        'winton' => 'Winton',
        'marshall wace' => 'Marshall Wace',
        'brevan howard' => 'Brevan Howard',
        'balyasny' => 'Balyasny',

        // Big 4 & Consulting
        'pwc' => 'PwC',
        'pricewaterhousecoopers' => 'PwC',
        'deloitte' => 'Deloitte',
        'ey' => 'EY',
        'ernst & young' => 'EY',
        'ernst young' => 'EY',
        'kpmg' => 'KPMG',
        'mckinsey' => 'McKinsey',
        'bcg' => 'BCG',
        'boston consulting' => 'BCG',
        'bain consulting' => 'Bain & Company',
        'bain & company' => 'Bain & Company',
    );

    /**
     * Firm type detection patterns
     */
    private $firm_type_patterns = array(
        'investment_bank' => array('bank', 'securities', 'advisory', 'capital markets'),
        'pe_firm' => array('private equity', 'buyout', 'capital', 'partners', 'investments'),
        'asset_manager' => array('asset management', 'investments', 'fund', 'capital'),
        'hedge_fund' => array('hedge', 'trading', 'macro', 'quant'),
        'vc' => array('venture', 'ventures', 'vc'),
        'consulting' => array('consulting', 'advisory', 'strategy'),
        'accounting' => array('accounting', 'audit', 'tax'),
        'law_firm' => array('law', 'legal', 'llp', 'solicitors'),
        'corporate' => array('corp', 'inc', 'plc', 'ltd'),
        'recruiter' => array('recruitment', 'staffing', 'search', 'headhunter'),
    );

    /**
     * Get instance
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
        // Load company intelligence engine for additional aliases
        if (class_exists('SFFC_Company_Intelligence_Engine')) {
            $this->load_additional_aliases();
        }
    }

    /**
     * Load additional aliases from Company Intelligence Engine
     */
    private function load_additional_aliases() {
        try {
            $engine = SFFC_Company_Intelligence_Engine::get_instance();
            $firms = $engine->get_all_firms();

            foreach ($firms as $firm) {
                if (!empty($firm['aliases'])) {
                    foreach ($firm['aliases'] as $alias) {
                        $this->company_aliases[strtolower($alias)] = $firm['name'];
                    }
                }
            }
        } catch (Exception $e) {
            error_log('SFFC Firm Intelligence: Failed to load additional aliases - ' . $e->getMessage());
        }
    }

    /**
     * Get firm intelligence for a company
     *
     * @param string $company_name The company name to look up
     * @param bool $force_refresh Force cache refresh
     * @return array Intelligence data
     */
    public function get_intelligence($company_name, $force_refresh = false) {
        if (empty($company_name)) {
            return $this->get_empty_response('No company name provided');
        }

        // Normalize company name
        $normalized_name = $this->normalize_company_name($company_name);
        $cache_key = 'sffc_firm_intel_' . md5(strtolower($normalized_name));

        // Check cache first
        if (!$force_refresh) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                $cached['from_cache'] = true;
                return $cached;
            }
        }

        // Detect firm type
        $firm_type = $this->detect_firm_type($normalized_name);

        // Query internal data
        $internal_data = $this->query_internal_data($normalized_name);

        // Build intelligence response
        if ($this->has_sufficient_data($internal_data)) {
            $intelligence = $this->build_intelligence_from_internal($normalized_name, $firm_type, $internal_data);
        } else {
            // Fallback to Claude
            $intelligence = $this->get_claude_fallback($normalized_name, $firm_type);
        }

        // Add metadata
        $intelligence['company_name'] = $normalized_name;
        $intelligence['firm_type'] = $firm_type;
        $intelligence['last_updated'] = current_time('mysql');
        $intelligence['from_cache'] = false;

        // Cache the result
        set_transient($cache_key, $intelligence, self::CACHE_TTL);

        return $intelligence;
    }

    /**
     * Normalize company name using aliases
     */
    public function normalize_company_name($name) {
        $name = trim($name);
        $lower_name = strtolower($name);

        // Check direct alias match
        if (isset($this->company_aliases[$lower_name])) {
            return $this->company_aliases[$lower_name];
        }

        // Check partial matches
        foreach ($this->company_aliases as $alias => $canonical) {
            if (strpos($lower_name, $alias) !== false) {
                return $canonical;
            }
        }

        // Clean up common suffixes
        $suffixes = array(' inc', ' inc.', ' ltd', ' ltd.', ' llc', ' plc', ' lp', ' llp', ' group', ' holdings', ' partners');
        foreach ($suffixes as $suffix) {
            if (substr($lower_name, -strlen($suffix)) === $suffix) {
                $name = trim(substr($name, 0, -strlen($suffix)));
                break;
            }
        }

        return $name;
    }

    /**
     * Detect firm type based on name and patterns
     */
    private function detect_firm_type($company_name) {
        $lower_name = strtolower($company_name);

        // Check known firm types
        $known_types = array(
            // PE Firms
            'Blackstone' => 'pe_firm',
            'KKR' => 'pe_firm',
            'Apollo Global' => 'pe_firm',
            'The Carlyle Group' => 'pe_firm',
            'TPG' => 'pe_firm',
            'Warburg Pincus' => 'pe_firm',
            'Bain Capital' => 'pe_firm',
            'Advent International' => 'pe_firm',
            'Permira' => 'pe_firm',
            'CVC Capital' => 'pe_firm',

            // Investment Banks
            'Goldman Sachs' => 'investment_bank',
            'Morgan Stanley' => 'investment_bank',
            'J.P. Morgan' => 'investment_bank',
            'Bank of America' => 'investment_bank',
            'Citigroup' => 'investment_bank',
            'Barclays' => 'investment_bank',
            'Deutsche Bank' => 'investment_bank',
            'UBS' => 'investment_bank',
            'Lazard' => 'investment_bank',
            'Evercore' => 'investment_bank',
            'Moelis' => 'investment_bank',
            'Rothschild & Co' => 'investment_bank',

            // Asset Managers
            'BlackRock' => 'asset_manager',
            'Vanguard' => 'asset_manager',
            'Fidelity' => 'asset_manager',
            'PIMCO' => 'asset_manager',
            'Schroders' => 'asset_manager',

            // Hedge Funds
            'Citadel' => 'hedge_fund',
            'Millennium' => 'hedge_fund',
            'Two Sigma' => 'hedge_fund',
            'Bridgewater' => 'hedge_fund',
            'Point72' => 'hedge_fund',

            // Big 4
            'PwC' => 'accounting',
            'Deloitte' => 'accounting',
            'EY' => 'accounting',
            'KPMG' => 'accounting',

            // Consulting
            'McKinsey' => 'consulting',
            'BCG' => 'consulting',
            'Bain & Company' => 'consulting',
        );

        if (isset($known_types[$company_name])) {
            return $known_types[$company_name];
        }

        // Pattern-based detection
        foreach ($this->firm_type_patterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($lower_name, $pattern) !== false) {
                    return $type;
                }
            }
        }

        return 'corporate'; // Default
    }

    /**
     * Query internal news and deals data
     */
    private function query_internal_data($company_name) {
        global $wpdb;

        $data = array(
            'deals' => array(),
            'news' => array(),
            'signals' => array(),
            'market_intel' => array(),
        );

        $search_terms = $this->get_search_terms($company_name);
        $date_limit = date('Y-m-d', strtotime('-12 months'));

        // Search PE Deals
        $data['deals'] = $this->search_posts_by_company(
            'sffc_pe_deal',
            $search_terms,
            $date_limit,
            array('_companies_involved', '_deal_value', '_deal_type', '_sector', '_region')
        );

        // Search PE News
        $data['news'] = $this->search_posts_by_company(
            'sffc_pe_news',
            $search_terms,
            $date_limit,
            array('_companies_mentioned', '_news_category', '_region')
        );

        // Search PE Signals (high-impact news)
        $data['signals'] = $this->search_posts_by_company(
            'sffc_pe_signal',
            $search_terms,
            $date_limit,
            array('_signal_type', '_controversy_level', '_ranking_score')
        );

        // Search Market Intel
        $data['market_intel'] = $this->search_posts_by_company(
            'sffc_market_intel',
            $search_terms,
            $date_limit,
            array('_intel_type', '_market_impact')
        );

        return $data;
    }

    /**
     * Get search terms for a company (including variations)
     */
    private function get_search_terms($company_name) {
        $terms = array($company_name);

        // Add lowercase version
        $terms[] = strtolower($company_name);

        // Find reverse aliases
        foreach ($this->company_aliases as $alias => $canonical) {
            if (strcasecmp($canonical, $company_name) === 0) {
                $terms[] = $alias;
            }
        }

        return array_unique($terms);
    }

    /**
     * Search posts by company name in meta fields
     */
    private function search_posts_by_company($post_type, $search_terms, $date_limit, $meta_keys) {
        global $wpdb;

        $results = array();

        // Build meta query for company mentions
        $meta_conditions = array();
        foreach ($search_terms as $term) {
            $escaped_term = '%' . $wpdb->esc_like($term) . '%';
            $meta_conditions[] = $wpdb->prepare(
                "(pm.meta_key IN ('_companies_involved', '_companies_mentioned') AND pm.meta_value LIKE %s)",
                $escaped_term
            );
            // Also search in title
            $meta_conditions[] = $wpdb->prepare(
                "(p.post_title LIKE %s)",
                $escaped_term
            );
        }

        $meta_where = implode(' OR ', $meta_conditions);

        $query = $wpdb->prepare(
            "SELECT DISTINCT p.ID, p.post_title, p.post_date, p.post_content
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND p.post_date >= %s
            AND ({$meta_where})
            ORDER BY p.post_date DESC
            LIMIT 20",
            $post_type,
            $date_limit
        );

        $posts = $wpdb->get_results($query);

        if (!empty($posts)) {
            foreach ($posts as $post) {
                $item = array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'date' => $post->post_date,
                    'excerpt' => wp_trim_words($post->post_content, 30),
                    'url' => get_permalink($post->ID),
                    'meta' => array(),
                );

                // Get requested meta
                foreach ($meta_keys as $key) {
                    $value = get_post_meta($post->ID, $key, true);
                    if (!empty($value)) {
                        $item['meta'][$key] = $value;
                    }
                }

                // Get source URL
                $source_url = get_post_meta($post->ID, '_source_url', true);
                if ($source_url) {
                    $item['source_url'] = $source_url;
                }

                $results[] = $item;
            }
        }

        return $results;
    }

    /**
     * Check if we have sufficient internal data
     */
    private function has_sufficient_data($internal_data) {
        $total_items = count($internal_data['deals'])
                     + count($internal_data['news'])
                     + count($internal_data['signals'])
                     + count($internal_data['market_intel']);

        return $total_items >= 1;
    }

    /**
     * Build intelligence response from internal data
     */
    private function build_intelligence_from_internal($company_name, $firm_type, $internal_data) {
        $intelligence = array(
            'status' => 'found',
            'source' => 'internal',
            'sections' => array(),
            'sentiment' => array(
                'score' => 'stable',
                'label' => 'Stable',
                'color' => 'yellow',
                'reason' => '',
            ),
            'summary' => '',
        );

        // Calculate sentiment
        $sentiment_data = $this->calculate_sentiment($internal_data);
        $intelligence['sentiment'] = $sentiment_data;

        // Build sections based on available data
        $sections = array();

        // Recent Deals section
        if (!empty($internal_data['deals'])) {
            $sections['deals'] = array(
                'title' => $this->get_section_title('deals', $firm_type),
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>',
                'items' => $this->format_deals($internal_data['deals']),
            );
        }

        // News section
        if (!empty($internal_data['news'])) {
            $sections['news'] = array(
                'title' => 'In The News',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8z"/></svg>',
                'items' => $this->format_news($internal_data['news']),
            );
        }

        // Signals section (high-impact news)
        if (!empty($internal_data['signals'])) {
            $sections['signals'] = array(
                'title' => 'Key Developments',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
                'items' => $this->format_signals($internal_data['signals']),
            );
        }

        // Market Intel section
        if (!empty($internal_data['market_intel'])) {
            $sections['market_intel'] = array(
                'title' => 'Market Intelligence',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>',
                'items' => $this->format_market_intel($internal_data['market_intel']),
            );
        }

        $intelligence['sections'] = $sections;

        // Generate summary
        $intelligence['summary'] = $this->generate_summary($company_name, $firm_type, $sections, $sentiment_data);

        return $intelligence;
    }

    /**
     * Get section title based on firm type
     */
    private function get_section_title($section, $firm_type) {
        $titles = array(
            'deals' => array(
                'pe_firm' => 'Recent Investments',
                'investment_bank' => 'Advisory Mandates',
                'asset_manager' => 'Fund Activity',
                'hedge_fund' => 'Portfolio Moves',
                'vc' => 'Recent Investments',
                'default' => 'Recent Transactions',
            ),
        );

        if (isset($titles[$section][$firm_type])) {
            return $titles[$section][$firm_type];
        }

        return $titles[$section]['default'] ?? ucfirst($section);
    }

    /**
     * Format deals for display
     */
    private function format_deals($deals) {
        $formatted = array();
        $count = 0;

        foreach ($deals as $deal) {
            if ($count >= 5) break;

            $item = array(
                'title' => $deal['title'],
                'date' => date('M j, Y', strtotime($deal['date'])),
                'time_ago' => human_time_diff(strtotime($deal['date']), current_time('timestamp')) . ' ago',
            );

            // Add deal value if available
            if (!empty($deal['meta']['_deal_value'])) {
                $item['value'] = $deal['meta']['_deal_value'];
            }

            // Add deal type
            if (!empty($deal['meta']['_deal_type'])) {
                $item['type'] = ucfirst($deal['meta']['_deal_type']);
            }

            // Add sector
            if (!empty($deal['meta']['_sector'])) {
                $item['sector'] = $deal['meta']['_sector'];
            }

            // Add link
            if (!empty($deal['source_url'])) {
                $item['url'] = $deal['source_url'];
            }

            $formatted[] = $item;
            $count++;
        }

        return $formatted;
    }

    /**
     * Format news for display
     */
    private function format_news($news) {
        $formatted = array();
        $count = 0;

        foreach ($news as $item) {
            if ($count >= 5) break;

            $formatted[] = array(
                'title' => $item['title'],
                'date' => date('M j, Y', strtotime($item['date'])),
                'time_ago' => human_time_diff(strtotime($item['date']), current_time('timestamp')) . ' ago',
                'category' => !empty($item['meta']['_news_category']) ? ucfirst($item['meta']['_news_category']) : 'News',
                'url' => !empty($item['source_url']) ? $item['source_url'] : $item['url'],
            );

            $count++;
        }

        return $formatted;
    }

    /**
     * Format signals for display
     */
    private function format_signals($signals) {
        $formatted = array();
        $count = 0;

        foreach ($signals as $signal) {
            if ($count >= 3) break;

            $controversy = $signal['meta']['_controversy_level'] ?? 'low';
            $type = $signal['meta']['_signal_type'] ?? 'general';

            $formatted[] = array(
                'title' => $signal['title'],
                'date' => date('M j, Y', strtotime($signal['date'])),
                'time_ago' => human_time_diff(strtotime($signal['date']), current_time('timestamp')) . ' ago',
                'type' => ucfirst(str_replace('_', ' ', $type)),
                'impact' => ucfirst($controversy),
                'impact_color' => $this->get_impact_color($controversy),
                'url' => !empty($signal['source_url']) ? $signal['source_url'] : $signal['url'],
            );

            $count++;
        }

        return $formatted;
    }

    /**
     * Format market intel for display
     */
    private function format_market_intel($intel) {
        $formatted = array();
        $count = 0;

        foreach ($intel as $item) {
            if ($count >= 3) break;

            $formatted[] = array(
                'title' => $item['title'],
                'date' => date('M j, Y', strtotime($item['date'])),
                'time_ago' => human_time_diff(strtotime($item['date']), current_time('timestamp')) . ' ago',
                'type' => !empty($item['meta']['_intel_type']) ? ucfirst($item['meta']['_intel_type']) : 'Intel',
                'url' => !empty($item['source_url']) ? $item['source_url'] : $item['url'],
            );

            $count++;
        }

        return $formatted;
    }

    /**
     * Get impact color
     */
    private function get_impact_color($level) {
        switch ($level) {
            case 'high':
                return 'red';
            case 'medium':
                return 'orange';
            case 'low':
            default:
                return 'green';
        }
    }

    /**
     * Calculate sentiment from internal data
     */
    private function calculate_sentiment($internal_data) {
        $positive_score = 0;
        $negative_score = 0;
        $reasons = array();

        // Analyze deals
        foreach ($internal_data['deals'] as $deal) {
            $title = strtolower($deal['title']);
            $type = strtolower($deal['meta']['_deal_type'] ?? '');

            // Positive deal signals
            if (preg_match('/acquires|acquisition|closes|completes|raises|invests|expansion|growth/', $title . ' ' . $type)) {
                $positive_score += 2;
            }

            // Exit deals are neutral-positive
            if (preg_match('/exit|sells|divests/', $title . ' ' . $type)) {
                $positive_score += 1;
            }
        }

        // Analyze news
        foreach ($internal_data['news'] as $news) {
            $title = strtolower($news['title']);
            $category = strtolower($news['meta']['_news_category'] ?? '');

            // Positive news signals
            if (preg_match('/fundraising|expansion|growth|record|strong|success|award|hire|appoint|promote/', $title)) {
                $positive_score += 1;
            }

            // Negative news signals
            if (preg_match('/layoff|cut|restructur|loss|decline|depart|leave|exit|struggle|challenge|concern/', $title)) {
                $negative_score += 1;
            }
        }

        // Analyze signals
        foreach ($internal_data['signals'] as $signal) {
            $controversy = strtolower($signal['meta']['_controversy_level'] ?? 'low');
            $type = strtolower($signal['meta']['_signal_type'] ?? '');

            if ($controversy === 'high' || preg_match('/scandal|investigation|lawsuit|collapse/', $type)) {
                $negative_score += 3;
                $reasons[] = 'High-impact negative news detected';
            } elseif ($controversy === 'medium') {
                $negative_score += 1;
            }

            if (preg_match('/deal_activity|market_activity/', $type)) {
                $positive_score += 1;
            }
        }

        // Determine overall sentiment
        $net_score = $positive_score - $negative_score;

        if ($net_score >= 3) {
            return array(
                'score' => 'growing',
                'label' => 'Growing',
                'color' => 'green',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
                'reason' => $this->generate_sentiment_reason('growing', $positive_score, $negative_score, $internal_data),
            );
        } elseif ($net_score <= -2) {
            return array(
                'score' => 'caution',
                'label' => 'Caution',
                'color' => 'red',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
                'reason' => $this->generate_sentiment_reason('caution', $positive_score, $negative_score, $internal_data),
            );
        } else {
            return array(
                'score' => 'stable',
                'label' => 'Stable',
                'color' => 'yellow',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>',
                'reason' => $this->generate_sentiment_reason('stable', $positive_score, $negative_score, $internal_data),
            );
        }
    }

    /**
     * Generate sentiment reason text
     */
    private function generate_sentiment_reason($sentiment, $positive, $negative, $data) {
        $deal_count = count($data['deals']);
        $news_count = count($data['news']);

        switch ($sentiment) {
            case 'growing':
                if ($deal_count > 0) {
                    return "Active deal flow with {$deal_count} recent transaction" . ($deal_count > 1 ? 's' : '');
                }
                return "Positive market presence and activity";

            case 'caution':
                if ($negative > 2) {
                    return "Recent challenging news coverage";
                }
                return "Mixed signals in recent coverage";

            case 'stable':
            default:
                if ($deal_count > 0 || $news_count > 0) {
                    return "Steady market presence";
                }
                return "Limited recent activity in our coverage";
        }
    }

    /**
     * Generate summary text
     */
    private function generate_summary($company_name, $firm_type, $sections, $sentiment) {
        $parts = array();

        // Deal activity
        if (!empty($sections['deals'])) {
            $deal_count = count($sections['deals']['items']);
            $parts[] = "{$deal_count} recent transaction" . ($deal_count > 1 ? 's' : '') . " tracked";
        }

        // News coverage
        if (!empty($sections['news'])) {
            $news_count = count($sections['news']['items']);
            $parts[] = "{$news_count} news mention" . ($news_count > 1 ? 's' : '');
        }

        // Signals
        if (!empty($sections['signals'])) {
            $parts[] = "key developments flagged";
        }

        if (empty($parts)) {
            return "Limited recent activity for {$company_name} in our coverage.";
        }

        return implode(', ', $parts) . ". Sentiment: {$sentiment['label']}.";
    }

    /**
     * Get Claude fallback for companies without internal data
     */
    private function get_claude_fallback($company_name, $firm_type) {
        $intelligence = array(
            'status' => 'fallback',
            'source' => 'ai',
            'sections' => array(),
            'sentiment' => array(
                'score' => 'unknown',
                'label' => 'Unknown',
                'color' => 'gray',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
                'reason' => 'Insufficient recent data for sentiment analysis',
            ),
            'summary' => '',
            'disclaimer' => 'AI-curated from public knowledge • May not reflect latest developments',
        );

        // Try to get Claude response
        if (class_exists('SFFC_Claude_API_Manager')) {
            try {
                $claude = SFFC_Claude_API_Manager::get_instance();

                $firm_type_label = str_replace('_', ' ', $firm_type);
                $prompt = "Provide a brief, factual summary about {$company_name} ({$firm_type_label}). Include:
1. What they're known for (1 sentence)
2. Their market position (1 sentence)
3. Any notable recent developments you're aware of (1-2 sentences)

Keep it professional and factual. If you're unsure about recent developments, focus on their established reputation. Format as a short paragraph, not bullet points.";

                $response = $claude->send_message($prompt, array(
                    'max_tokens' => 300,
                    'temperature' => 0.3,
                ));

                if ($response && !is_wp_error($response)) {
                    // Handle response - could be array or string
                    $content = '';
                    if (is_array($response) && isset($response['response'])) {
                        $content = $response['response'];
                    } elseif (is_string($response)) {
                        $content = $response;
                    }

                    if (!empty($content)) {
                        $intelligence['sections']['overview'] = array(
                            'title' => 'Company Overview',
                            'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l8-4 8 4v14"/><path d="M9 21v-4h6v4"/></svg>',
                            'content' => trim($content),
                        );
                        $intelligence['summary'] = "AI-generated overview based on public knowledge.";
                    }
                }
            } catch (Exception $e) {
                error_log('SFFC Firm Intelligence: Claude fallback failed - ' . $e->getMessage());
            }
        }

        // If Claude failed, provide basic fallback
        if (empty($intelligence['sections'])) {
            $intelligence['sections']['overview'] = array(
                'title' => 'Limited Coverage',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
                'content' => "We don't have recent news or deal activity for {$company_name} in our database. This could mean they're a smaller firm, operate in a niche market, or simply haven't been in the financial press recently.",
            );
            $intelligence['summary'] = "No recent activity tracked for this company.";
        }

        return $intelligence;
    }

    /**
     * Get empty response
     */
    private function get_empty_response($reason = '') {
        return array(
            'status' => 'error',
            'source' => 'none',
            'error' => $reason,
            'sections' => array(),
            'sentiment' => array(
                'score' => 'unknown',
                'label' => 'Unknown',
                'color' => 'gray',
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
                'reason' => $reason,
            ),
            'summary' => $reason,
        );
    }

    /**
     * Clear cache for a specific company
     */
    public function clear_cache($company_name) {
        $normalized_name = $this->normalize_company_name($company_name);
        $cache_key = 'sffc_firm_intel_' . md5(strtolower($normalized_name));
        delete_transient($cache_key);
    }

    /**
     * Clear all firm intelligence cache
     */
    public function clear_all_cache() {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sffc_firm_intel_%' OR option_name LIKE '_transient_timeout_sffc_firm_intel_%'"
        );
    }

    /**
     * Get firm type label
     */
    public function get_firm_type_label($type) {
        $labels = array(
            'investment_bank' => 'Investment Bank',
            'pe_firm' => 'Private Equity',
            'asset_manager' => 'Asset Manager',
            'hedge_fund' => 'Hedge Fund',
            'vc' => 'Venture Capital',
            'consulting' => 'Consulting',
            'accounting' => 'Accounting',
            'law_firm' => 'Law Firm',
            'corporate' => 'Corporate',
            'recruiter' => 'Recruitment',
        );

        return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }
}

// Initialize
SFFC_Firm_Intelligence::get_instance();
