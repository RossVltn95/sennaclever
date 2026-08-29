<?php

/**
 * Autosuggestion Library Manager
 * Enhanced for contextual relevance, fuzzy matching, and dynamic generation
 * 
 * @package SennaCareers
 * @since 10.23.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Autosuggestion_Library
{

    private static $instance = null;
    private $suggestions_cache = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        if (function_exists('add_action')) {
            add_action('init', array($this, 'maybe_rebuild_cache'));
        }
    }

    /**
     * Get suggestions based on query and mode
     */
    public function get_suggestions($query, $mode = 'jobs', $limit = 8)
    {
        $query = strtolower(trim($query));
        static $runtime_cache = [];
        $this->update_search_popularity($query, $mode);
        if (strlen($query) < 2) {
            return $this->get_trending_suggestions($mode, $limit);
        }
        $cache_key = $mode . '_' . md5($query);
        if (isset($runtime_cache[$cache_key])) {
            return $runtime_cache[$cache_key];
        }

        $all_suggestions = $this->get_all_suggestions();
        $mode_suggestions = isset($all_suggestions[$mode]) ? $all_suggestions[$mode] : [];

        $expanded_queries = $this->expand_query_terms($query);
        $scored_suggestions = [];

        foreach ($expanded_queries as $expanded_query) {
            foreach ($mode_suggestions as $suggestion) {
                $score = $this->calculate_relevance_score($expanded_query, $suggestion);
                if ($score > 0) {
                    // Keep highest score if same suggestion text appears multiple times
                    $existing_index = array_search($suggestion['text'], array_column($scored_suggestions, 'text'));
                    if ($existing_index !== false) {
                        if ($score > $scored_suggestions[$existing_index]['score']) {
                            $scored_suggestions[$existing_index]['score'] = $score;
                        }
                    } else {
                        $suggestion['score'] = $score;
                        $scored_suggestions[] = $suggestion;
                    }
                }
            }
        }


        usort($scored_suggestions, function ($a, $b) {
            if ($a['score'] == $b['score']) {
                return strlen($a['text']) - strlen($b['text']);
            }
            return $b['score'] - $a['score'];
        });

        return $runtime_cache[$cache_key] = array_slice($scored_suggestions, 0, $limit);
    }
    /**
     * Expand abbreviations, synonyms, and partial forms in queries
     */
    private function expand_query_terms($query)
    {
        $query = strtolower(trim($query));

        $synonyms = [
            'pe' => ['private equity'],
            'vc' => ['venture capital'],
            'ib' => ['investment banking'],
            'ir' => ['investor relations'],
            'md' => ['managing director'],
            'assoc' => ['associate'],
            'mgr' => ['manager'],
            'infra' => ['infrastructure'],
            'tech' => ['technology'],
            'cons' => ['consumer'],
            'fin' => ['finance', 'financial'],
            'corp dev' => ['corporate development'],
            're' => ['real estate'],
            'lp' => ['limited partner'],
            'gp' => ['general partner']
        ];

        $alternatives = [$query];

        foreach ($synonyms as $key => $values) {
            if (strpos($query, $key) !== false) {
                foreach ($values as $val) {
                    $alternatives[] = str_replace($key, $val, $query);
                    $alternatives[] = str_replace($key, $val . ' ', $query);
                }
            }
        }

        // Add plural or minor variants
        $tokens = explode(' ', $query);
        foreach ($tokens as $token) {
            if (strlen($token) > 2 && substr($token, -1) !== 's') {
                $alternatives[] = str_replace($token, $token . 's', $query);
            }
        }

        return array_values(array_unique($alternatives));
    }

    /**
     * Clean search query to remove duplicates and excessive repetition
     */
    private function clean_search_query($query) {
        $query = strtolower(trim($query));
        
        if (empty($query)) {
            return '';
        }
        
        // Split by common delimiters and whitespace
        $terms = preg_split('/[\s,+&]+/', $query);
        
        // Remove empty terms
        $terms = array_filter($terms, function($term) {
            return !empty(trim($term));
        });
        
        // Remove duplicates
        $unique_terms = array_unique($terms);
        
        // Remove excessive repetition patterns
        $final_terms = array();
        $prev_term = '';
        
        foreach ($unique_terms as $term) {
            if ($term !== $prev_term && strlen($term) > 1) {
                $final_terms[] = $term;
                $prev_term = $term;
            }
        }
        
        // Rejoin with single spaces and limit length
        $cleaned = implode(' ', $final_terms);
        
        // Limit overall query length to prevent extremely long URLs
        return substr($cleaned, 0, 150);
    }

    /**
     * Update search popularity counts
     */
    private function update_search_popularity($query, $mode = 'jobs')
    {
        if (!function_exists('get_option') || !function_exists('update_option')) return;

        $key = 'sffc_search_popularity_' . $mode;
        $popularity = get_option($key, []);

        // Clean the query before storing
        $query = $this->clean_search_query($query);
        $timestamp = time();

        if (!isset($popularity[$query])) {
            $popularity[$query] = ['count' => 1, 'last_search' => $timestamp];
        } else {
            $popularity[$query]['count'] += 1;
            $popularity[$query]['last_search'] = $timestamp;
        }

        // Keep top 500 to prevent bloat
        if (count($popularity) > 500) {
            uasort($popularity, fn($a, $b) => $b['count'] <=> $a['count']);
            $popularity = array_slice($popularity, 0, 500, true);
        }

        update_option($key, $popularity);
    }


    /**
     * Improved fuzzy + contextual scoring
     */
    private function calculate_relevance_score($query, $suggestion)
    {
        $text = strtolower($suggestion['text']);
        $query = strtolower($query);
        $score = 0;

        if ($text === $query) return 2000;
        if (strpos($text, $query) === 0) $score += 1200;
        elseif (strpos($text, $query) !== false) $score += 600;

        // Fuzzy match
        $lev = levenshtein($query, $text);
        if ($lev < 5) $score += 800 - ($lev * 100);

        // Word-level matching
        $query_words = explode(' ', $query);
        $text_words = explode(' ', $text);
        foreach ($query_words as $i => $qword) {
            foreach ($text_words as $j => $tword) {
                $distance = levenshtein($qword, $tword);
                if ($distance <= 1) $score += 300;
                elseif (strpos($tword, $qword) === 0) $score += 200;
                elseif (strpos($tword, $qword) !== false) $score += 100;
                if ($i === $j) $score += 50;
            }
        }

        $type_weights = [
            'popular_search' => 1.6,
            'company' => 1.3,
            'company_jobs' => 1.3,
            'location' => 1.2,
            'location_jobs' => 1.2,
            'job_title' => 1.5,
            'template' => 1.1,
            'recruiter' => 1.2,
            'insight' => 1.1
        ];
        // --- POPULARITY BOOST ---
        $popularity_score = $this->get_popularity_boost($suggestion['text'], $suggestion['type']);
        $score += $popularity_score;

        $score *= $type_weights[$suggestion['type']] ?? 1.0;

        if (isset($suggestion['category'])) {
            if (in_array(strtolower($suggestion['category']), ['job title', 'company'])) {
                $score *= 1.15;
            }
        }

        return round($score);
    }

    /**
     * Boost score based on past search popularity and recency
     */
    private function get_popularity_boost($text, $type, $mode = 'jobs')
    {
        if (!function_exists('get_option')) return 0;

        $key = 'sffc_search_popularity_' . $mode;
        $popularity = get_option($key, []);
        $text = strtolower(trim($text));

        if (!isset($popularity[$text])) return 0;

        $data = $popularity[$text];
        $count = $data['count'] ?? 0;
        $last = $data['last_search'] ?? 0;

        // Decay older searches (7-day half-life)
        $days_old = (time() - $last) / 86400;
        $decay_factor = max(0.3, pow(0.5, $days_old / 7));

        // Logarithmic scaling for frequency
        $popularity_boost = log(1 + $count) * 150 * $decay_factor;

        // Extra boost for trending or popular search types
        if (in_array($type, ['popular_search', 'trending'])) {
            $popularity_boost *= 1.2;
        }

        return $popularity_boost;
    }

    /**
     * Trending fallback
     */
    public function get_trending_suggestions($mode = 'jobs', $limit = 8)
    {
        $trending = $this->get_trending_from_settings($mode);
        if (empty($trending)) $trending = $this->get_default_trending($mode);
        return array_slice($trending, 0, $limit);
    }

    private function get_trending_from_settings($mode)
    {
        if (!function_exists('get_option')) return [];

        $option_keys = [
            'sffc_trending_searches_' . $mode,
            'sffc_search_trending_' . $mode,
            'sffc_popular_searches_' . $mode,
            'sffc_trending_queries',
            'sffc_search_settings'
        ];

        $trending = [];
        foreach ($option_keys as $key) {
            $data = get_option($key, []);
            if (empty($data)) continue;

            if (is_array($data)) {
                if ($key === 'sffc_search_settings') {
                    $trending = $data['trending'][$mode] ?? $data['trending'] ?? [];
                } elseif ($key === 'sffc_trending_queries') {
                    $trending = $data[$mode] ?? $data;
                } else {
                    $trending = $data;
                }
            }

            if (!empty($trending)) break;
        }

        $formatted = [];
        foreach ($trending as $item) {
            $text = is_string($item) ? $item : ($item['text'] ?? $item['query'] ?? '');
            if ($text) {
                $formatted[] = [
                    'text' => $text,
                    'type' => 'trending',
                    'icon' => $this->get_icon_for_type('trending'),
                    'score' => 1000,
                    'category' => ''
                ];
            }
        }

        return $formatted;
    }

    private function get_default_trending($mode)
    {
        $trending = [
            'jobs' => [
                ['text' => 'Private equity associate London'],
                ['text' => 'Investor relations analyst'],
                ['text' => 'Portfolio manager'],
                ['text' => 'Due diligence specialist'],
                ['text' => 'Fund accountant'],
                ['text' => 'Investor relations'],
                ['text' => 'Blackstone careers'],
                ['text' => 'KKR opportunities']
            ],
            'insights' => [
                ['text' => 'Mid-market buyouts 2025'],
                ['text' => 'European PE deals'],
                ['text' => 'ESG investing trends']
            ]
        ];
        $mode_trending = $trending[$mode] ?? $trending['jobs'];
        foreach ($mode_trending as &$item) {
            $item['type'] = 'trending';
            $item['icon'] = $this->get_icon_for_type('trending');
            $item['score'] = 1000;
            $item['category'] = '';
        }
        return $mode_trending;
    }

    /**
     * Core suggestion data
     */
    private function get_all_suggestions()
    {
        if ($this->suggestions_cache !== null) return $this->suggestions_cache;

        $this->suggestions_cache = [
            'jobs' => $this->get_job_suggestions(),
            'insights' => $this->get_insights_suggestions(),
            'recruiters' => $this->get_recruiter_suggestions(),
            'templates' => $this->get_template_suggestions()
        ];

        // Deduplicate
        $this->suggestions_cache = array_map(function ($mode_list) {
            $seen = [];
            return array_values(array_filter($mode_list, function ($item) use (&$seen) {
                $key = strtolower(trim($item['text']));
                if (isset($seen[$key])) return false;
                $seen[$key] = true;
                return true;
            }));
        }, $this->suggestions_cache);

        return $this->suggestions_cache;
    }

    /**
     * Enhanced job suggestions
     */
    private function get_job_suggestions()
    {
        $suggestions = [];

        $base_titles = [
            'Analyst',
            'Associate',
            'Vice President',
            'Director',
            'Principal',
            'Managing Director',
            'Partner',
            'Investment Manager',
            'Portfolio Manager',
            'Investment Director',
            'Investment Associate',
            'Senior Associate',
            'Analyst Intern'
        ];

        $sectors = [
            'Private Equity',
            'Venture Capital',
            'Growth Equity',
            'Buyout',
            'Infrastructure',
            'Real Estate',
            'Credit',
            'Distressed Debt',
            'Energy',
            'Healthcare',
            'Technology',
            'Consumer',
            'Industrial',
            'Financial Services',
            'TMT',
            'Sustainability',
            'ESG',
            'Impact Investing'
        ];

        $functional_roles = [
            'Fund Accountant',
            'Investor Relations',
            'Fundraising',
            'Operations',
            'Compliance',
            'Legal Counsel',
            'Tax Manager',
            'Finance Director',
            'Financial Controller',
            'Risk Analyst',
            'Quantitative Analyst',
            'Data Analyst',
            'ESG Specialist',
            'Due Diligence',
            'Business Development',
            'Deal Origination',
            'Strategy',
            'M&A Advisory'
        ];

        $locations = ['Paris', 'London', 'Frankfurt', 'Amsterdam', 'Madrid', 'Milan', 'Luxembourg'];
        $companies = ['Blackstone', 'KKR', 'Apollo', 'Carlyle', 'TPG', 'EQT', 'Bridgepoint', 'Hg Capital', 'Bain Capital'];

        $add = function ($text, $type, $category) use (&$suggestions) {
            $suggestions[] = [
                'text' => $text,
                'type' => $type,
                'icon' => $this->get_icon_for_type($type),
                'category' => $category
            ];
        };

        foreach ($sectors as $sector) {
            foreach ($base_titles as $title) {
                $add("$sector $title", 'job_title', 'Job Title');
                $add("$title in $sector", 'job_title', 'Job Title');
            }
        }

        foreach ($functional_roles as $role) {
            $add($role, 'job_title', 'Functional Role');
            foreach ($sectors as $sector) {
                $add("$role in $sector", 'job_title', 'Functional Role');
            }
        }

        foreach ($companies as $company) {
            $add("$company jobs", 'company_jobs', 'Company');
            $add("$company careers", 'company_jobs', 'Company');
            foreach ($base_titles as $title) {
                $add("$title at $company", 'company_jobs', 'Company Role');
            }
        }

        foreach ($locations as $loc) {
            $add("Private equity jobs in $loc", 'location_jobs', 'Location');
            $add("Investment banking jobs in $loc", 'location_jobs', 'Location');
            $add("$loc finance roles", 'location_jobs', 'Location');
            $add("$loc PE associate", 'location_jobs', 'Location');
        }

        $patterns = [
            'entry level private equity',
            'senior private equity roles',
            'private equity internships',
            'investment banking to PE',
            'consulting to private equity',
            'MBA private equity recruiting',
            'private equity headhunters',
            'PE recruiting process',
            'private equity interview questions',
            'PE case studies',
            'private equity salary',
            'PE bonus structure',
            'private equity exit opportunities',
            'growth equity vs buyout',
            'venture capital jobs',
            'hedge fund careers'
        ];

        foreach ($patterns as $pattern) {
            $add($pattern, 'popular_search', 'Popular Search');
        }

        $abbreviations = [
            'PE Associate',
            'PE Analyst',
            'IB Analyst',
            'VC Associate',
            'LBO Model',
            'M&A Analyst'
        ];
        foreach ($abbreviations as $abbr) {
            $add($abbr, 'job_title', 'Abbreviation');
        }

        $seen = [];
        $suggestions = array_values(array_filter($suggestions, function ($s) use (&$seen) {
            $key = strtolower(trim($s['text']));
            if (isset($seen[$key])) return false;
            $seen[$key] = true;
            return true;
        }));

        return $suggestions;
    }

    private function get_insights_suggestions()
    {
        $insights_terms = [
            'Private equity deals 2025',
            'M&A activity',
            'Buyout trends',
            'Growth equity investments',
            'Venture capital funding',
            'IPO pipeline',
            'ESG investing',
            'Digital transformation deals',
            'Fintech acquisitions',
            'Healthcare buyouts',
            'AI investments',
            'Technology growth deals'
        ];
        return array_map(fn($term) => [
            'text' => $term,
            'type' => 'insight',
            'icon' => $this->get_icon_for_type('insight'),
            'category' => 'Market Intelligence'
        ], $insights_terms);
    }

    private function get_recruiter_suggestions()
    {
        $recruiter_terms = [
            'Private equity recruiters London',
            'Investment banking headhunters',
            'Executive search firms',
            'Finance recruitment agencies',
            'PE headhunters UK',
            'Growth equity recruiters',
            'Hedge fund executive search'
        ];
        return array_map(fn($term) => [
            'text' => $term,
            'type' => 'recruiter',
            'icon' => $this->get_icon_for_type('recruiter'),
            'category' => 'Recruiters'
        ], $recruiter_terms);
    }

    private function get_template_suggestions()
    {
        $template_terms = [
            'Investment committee memo',
            'Due diligence checklist',
            'LP quarterly report',
            'Fund performance dashboard',
            'Deal sourcing tracker',
            'LBO financial model',
            'DCF analysis template',
            'Comparable company analysis',
            'Pitch deck template',
            'Term sheet template'
        ];
        return array_map(fn($term) => [
            'text' => $term,
            'type' => 'template',
            'icon' => $this->get_icon_for_type('template'),
            'category' => 'Templates'
        ], $template_terms);
    }

    private function get_icon_for_type($type)
    {
        $icons = [
            'job_title' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect></svg>',
            'company' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"></path><path d="M5 21V7l8-4v18"></path></svg>',
            'company_jobs' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"></path><path d="M5 21V7l8-4v18"></path></svg>',
            'location' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="10" r="3"></circle><path d="M12 21.7c5-4.7 8-9.7 8-11.7"></path></svg>',
            'location_jobs' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="10" r="3"></circle></svg>',
            'popular_search' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6H5V8h6"></path></svg>',
            'trending' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22,12 18,12 15,21 9,3 6,12 2,12"></polyline></svg>',
            'insight' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6v20h12V9z"></path></svg>',
            'recruiter' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="7" r="4"></circle></svg>',
            'template' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"></rect></svg>',
            'default' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle></svg>'
        ];
        return $icons[$type] ?? $icons['default'];
    }

    public function maybe_rebuild_cache()
    {
        if (!function_exists('get_option') || !defined('DAY_IN_SECONDS')) return;
        $last_rebuild = get_option('sffc_suggestions_last_rebuild', 0);
        if ((time() - $last_rebuild) > DAY_IN_SECONDS) $this->rebuild_cache();
    }

    public function rebuild_cache()
    {
        if (!function_exists('update_option')) return;
        $this->suggestions_cache = null;
        $this->get_all_suggestions();
        update_option('sffc_suggestions_last_rebuild', time());
        update_option('sffc_autosuggestion_cache', $this->suggestions_cache);
    }

    public function clear_cache()
    {
        $this->suggestions_cache = null;
        delete_option('sffc_autosuggestion_cache');
        delete_option('sffc_suggestions_last_rebuild');
    }
}

if (function_exists('add_action') && function_exists('get_option')) {
    SFFC_Autosuggestion_Library::get_instance();
}
