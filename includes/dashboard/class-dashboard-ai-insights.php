<?php
/**
 * Dashboard AI Insights Generator
 *
 * Uses Claude AI to generate career insights from market data.
 * Analyzes news, deals, and job postings to provide actionable intelligence.
 *
 * @package SFFC_Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Dashboard_AI_Insights {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Cache duration in seconds (4 hours)
     */
    const CACHE_DURATION = 14400;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Initialize
    }

    /**
     * Generate trend insights based on chart data
     */
    public function generate_trend_insight($trends_data, $series_type, $user_profile = null) {
        $cache_key = 'sffc_trend_insight_' . md5(serialize($trends_data) . $series_type);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // Analyze the data to generate insights
        $insight = $this->analyze_trends($trends_data, $series_type, $user_profile);

        set_transient($cache_key, $insight, self::CACHE_DURATION);

        return $insight;
    }

    /**
     * Analyze trends data and generate insights
     */
    private function analyze_trends($data, $series_type, $user_profile) {
        if (empty($data['datasets'])) {
            return $this->get_default_insight($series_type);
        }

        $datasets = $data['datasets'];
        $analysis = array();

        // Analyze each series
        foreach ($datasets as $ds) {
            $label = $ds['label'] ?? 'Unknown';
            $values = $ds['data'] ?? array();

            if (count($values) >= 2) {
                $first = $values[0];
                $last = end($values);
                $change = $last - $first;
                $percent_change = $first > 0 ? round(($change / $first) * 100) : 0;

                $analysis[] = array(
                    'label' => $label,
                    'change' => $change,
                    'percent' => $percent_change,
                    'direction' => $change > 5 ? 'growth' : ($change < -5 ? 'decline' : 'stable'),
                    'current' => $last,
                );
            }
        }

        // Generate insight based on analysis
        return $this->format_trend_insight($analysis, $series_type, $user_profile);
    }

    /**
     * Format trend insight for display
     */
    private function format_trend_insight($analysis, $series_type, $user_profile) {
        if (empty($analysis)) {
            return $this->get_default_insight($series_type);
        }

        // Find strongest growth and decline
        $growth_items = array_filter($analysis, function($a) {
            return $a['direction'] === 'growth';
        });

        $decline_items = array_filter($analysis, function($a) {
            return $a['direction'] === 'decline';
        });

        // Sort by percent change
        usort($growth_items, function($a, $b) {
            return $b['percent'] - $a['percent'];
        });

        usort($decline_items, function($a, $b) {
            return $a['percent'] - $b['percent'];
        });

        // Build insight message
        $insights = array();

        switch ($series_type) {
            case 'locations':
                if (!empty($growth_items)) {
                    $top_growth = $growth_items[0];
                    $insights[] = sprintf(
                        '%s shows strong hiring momentum with %d%% growth in job postings.',
                        $top_growth['label'],
                        abs($top_growth['percent'])
                    );

                    // Add user-specific advice if profile available
                    if ($user_profile) {
                        $user_locations = (array)($user_profile['preferred_locations'] ?? array());
                        $is_user_location = $this->location_matches($top_growth['label'], $user_locations);

                        if ($is_user_location) {
                            $insights[] = 'This aligns with your preferred locations - consider increasing applications here.';
                        } else {
                            $insights[] = 'Consider adding ' . $top_growth['label'] . ' to your target locations for more opportunities.';
                        }
                    }
                }

                if (!empty($decline_items)) {
                    $top_decline = $decline_items[0];
                    if (abs($top_decline['percent']) > 10) {
                        $insights[] = sprintf(
                            '%s has seen a %d%% decrease in opportunities.',
                            $top_decline['label'],
                            abs($top_decline['percent'])
                        );
                    }
                }
                break;

            case 'industries':
                if (!empty($growth_items)) {
                    $top_growth = $growth_items[0];
                    $insights[] = sprintf(
                        '%s sector is expanding rapidly with %d%% more roles this period.',
                        $top_growth['label'],
                        abs($top_growth['percent'])
                    );

                    if ($user_profile) {
                        $user_industries = (array)($user_profile['preferred_industries'] ?? array());
                        $is_user_industry = $this->industry_matches($top_growth['label'], $user_industries);

                        if ($is_user_industry) {
                            $insights[] = 'Your target sector is performing well - good timing for applications.';
                        }
                    }
                }
                break;

            case 'roles':
                if (!empty($growth_items)) {
                    $top_growth = $growth_items[0];
                    $insights[] = sprintf(
                        '%s positions are in high demand with %d%% growth.',
                        $top_growth['label'],
                        abs($top_growth['percent'])
                    );
                }
                break;
        }

        // Add recent market news context
        $news_context = $this->get_relevant_news_context($series_type, $analysis);
        if ($news_context) {
            $insights[] = $news_context;
        }

        if (empty($insights)) {
            return $this->get_default_insight($series_type);
        }

        return implode(' ', array_slice($insights, 0, 3));
    }

    /**
     * Get relevant news context for insights
     */
    private function get_relevant_news_context($series_type, $analysis) {
        global $wpdb;

        // Get recent news that might explain trends
        $news = $wpdb->get_results("
            SELECT post_title, post_content
            FROM {$wpdb->posts}
            WHERE post_type = 'sffc_pe_news'
            AND post_status = 'publish'
            AND post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY post_date DESC
            LIMIT 10
        ");

        if (empty($news)) {
            return null;
        }

        // Look for relevant keywords based on top performers
        $keywords = array();
        foreach ($analysis as $item) {
            if ($item['direction'] === 'growth') {
                $keywords[] = strtolower($item['label']);
            }
        }

        // Check news for mentions
        foreach ($news as $article) {
            $content = strtolower($article->post_title . ' ' . $article->post_content);

            foreach ($keywords as $keyword) {
                if (strpos($content, $keyword) !== false) {
                    // Found relevant news
                    if (strpos($content, 'expan') !== false || strpos($content, 'grow') !== false || strpos($content, 'invest') !== false) {
                        return 'Recent news supports this growth trend with increased investment activity.';
                    }
                    if (strpos($content, 'hiring') !== false || strpos($content, 'recruit') !== false) {
                        return 'Industry news indicates active hiring initiatives in this area.';
                    }
                }
            }
        }

        return null;
    }

    /**
     * Check if location matches user preferences
     */
    private function location_matches($location, $user_locations) {
        $location_lower = strtolower($location);
        foreach ($user_locations as $user_loc) {
            if (strpos($location_lower, strtolower($user_loc)) !== false ||
                strpos(strtolower($user_loc), $location_lower) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if industry matches user preferences
     */
    private function industry_matches($industry, $user_industries) {
        $industry_lower = strtolower($industry);
        foreach ($user_industries as $user_ind) {
            if (strpos($industry_lower, strtolower($user_ind)) !== false ||
                strpos(strtolower($user_ind), $industry_lower) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get default insight for series type
     */
    private function get_default_insight($series_type) {
        $defaults = array(
            'locations' => 'Monitor location trends to identify emerging opportunities. Major financial centers continue to show diverse hiring patterns.',
            'industries' => 'Industry dynamics are shifting. Stay informed about sector movements to time your applications effectively.',
            'roles' => 'Role demand varies by seniority level. Consider how your experience aligns with current market needs.',
        );

        return $defaults[$series_type] ?? 'Analyzing market trends to provide personalized insights...';
    }

    /**
     * Generate market signal from news
     */
    public function generate_market_signals($user_profile = null) {
        $cache_key = 'sffc_market_signals_' . ($user_profile ? md5(serialize($user_profile)) : 'general');
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;

        // Get recent news and deals
        $news = $wpdb->get_results("
            SELECT ID, post_title, post_content, post_date
            FROM {$wpdb->posts}
            WHERE post_type IN ('sffc_pe_news', 'sffc_pe_deals')
            AND post_status = 'publish'
            AND post_date >= DATE_SUB(NOW(), INTERVAL 14 DAY)
            ORDER BY post_date DESC
            LIMIT 20
        ");

        $signals = array();

        foreach ($news as $item) {
            $signal = $this->analyze_news_item($item, $user_profile);
            if ($signal) {
                $signals[] = $signal;
            }
        }

        // Limit to top 5 most relevant signals
        $signals = array_slice($signals, 0, 5);

        set_transient($cache_key, $signals, self::CACHE_DURATION / 2);

        return $signals;
    }

    /**
     * Analyze a news item for market signals
     */
    private function analyze_news_item($item, $user_profile) {
        $content = strtolower($item->post_title . ' ' . $item->post_content);

        // Positive signals
        $positive_keywords = array(
            'expansion' => 'hiring expansion expected',
            'growth' => 'sector growth',
            'hiring' => 'increased hiring activity',
            'investment' => 'new investment activity',
            'acquisition' => 'M&A activity could mean new roles',
            'new office' => 'new office openings',
            'record' => 'record performance may boost hiring',
            'profit' => 'strong profits may lead to expansion',
        );

        // Negative signals
        $negative_keywords = array(
            'layoff' => 'workforce reductions announced',
            'downsiz' => 'downsizing activity',
            'cut' => 'cost cutting measures',
            'restructur' => 'restructuring may affect roles',
            'declin' => 'sector decline',
            'clos' => 'office closures reported',
        );

        // Check for positive signals
        foreach ($positive_keywords as $keyword => $description) {
            if (strpos($content, $keyword) !== false) {
                return array(
                    'type' => 'positive',
                    'text' => $this->extract_signal_summary($item->post_title, $description),
                    'date' => $item->post_date,
                );
            }
        }

        // Check for negative signals
        foreach ($negative_keywords as $keyword => $description) {
            if (strpos($content, $keyword) !== false) {
                return array(
                    'type' => 'negative',
                    'text' => $this->extract_signal_summary($item->post_title, $description),
                    'date' => $item->post_date,
                );
            }
        }

        return null;
    }

    /**
     * Extract signal summary from title
     */
    private function extract_signal_summary($title, $description) {
        // Get company/location from title if possible
        $words = explode(' ', $title);
        $entity = '';

        // Look for capitalized words (likely company names)
        foreach ($words as $word) {
            if (strlen($word) > 2 && ctype_upper($word[0])) {
                $entity = $word;
                break;
            }
        }

        if ($entity) {
            return $entity . ' - ' . $description;
        }

        return ucfirst($description);
    }

    /**
     * Generate salary insight for user
     */
    public function generate_salary_insight($salary_data, $user_profile) {
        if (empty($salary_data['estimate'])) {
            return 'Complete your profile to receive personalized salary insights.';
        }

        $estimate = $salary_data['estimate'];
        $percentile = $salary_data['percentile'] ?? 50;
        $factors = $salary_data['factors'] ?? array();

        $insights = array();

        // Percentile insight
        if ($percentile >= 75) {
            $insights[] = sprintf(
                'Your profile puts you in the top %d%% of earners in your field.',
                100 - $percentile
            );
        } elseif ($percentile >= 50) {
            $insights[] = 'Your expected compensation is above the median for your profile.';
        } else {
            $insights[] = 'There is room to increase your earning potential with additional skills or experience.';
        }

        // Factors insight
        $positive_factors = array_filter($factors, function($f) {
            return $f['impact'] === 'positive';
        });

        if (!empty($positive_factors)) {
            $factor = reset($positive_factors);
            $insights[] = sprintf(
                'Your %s contributes %s to your earning potential.',
                strtolower($factor['label']),
                $factor['value']
            );
        }

        return implode(' ', $insights);
    }

    /**
     * Generate skills gap insight
     */
    public function generate_skills_insight($skills_analysis, $user_profile) {
        if (empty($skills_analysis['skills'])) {
            return 'Add your skills to receive demand analysis and upskilling recommendations.';
        }

        $skills = $skills_analysis['skills'];
        $recommendations = $skills_analysis['recommendations'] ?? array();

        // Find high-demand skills user has
        $high_demand = array_filter($skills, function($s) {
            return ($s['demand'] ?? 0) >= 70;
        });

        // Find trending skills
        $trending = array_filter($skills, function($s) {
            return ($s['trend'] ?? 'stable') === 'up';
        });

        $insights = array();

        if (!empty($high_demand)) {
            $count = count($high_demand);
            $insights[] = sprintf(
                'You have %d high-demand skill%s that employers are actively seeking.',
                $count,
                $count > 1 ? 's' : ''
            );
        }

        if (!empty($trending)) {
            $skill_names = array_map(function($s) {
                return $s['name'];
            }, array_slice($trending, 0, 2));

            $insights[] = sprintf(
                '%s %s trending upward in job requirements.',
                implode(' and ', $skill_names),
                count($skill_names) > 1 ? 'are' : 'is'
            );
        }

        if (!empty($recommendations)) {
            $top_rec = $recommendations[0];
            $insights[] = sprintf(
                'Consider adding %s to your skillset - it has %s priority for your target roles.',
                $top_rec['skill'],
                strtolower($top_rec['importance'])
            );
        }

        if (empty($insights)) {
            return 'Your skills profile is being analyzed against current market demands.';
        }

        return implode(' ', array_slice($insights, 0, 2));
    }

    /**
     * Clear all insight caches
     */
    public function clear_caches() {
        global $wpdb;

        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sffc_trend_insight_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_sffc_trend_insight_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sffc_market_signals_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_sffc_market_signals_%'");
    }
}

// Initialize
SFFC_Dashboard_AI_Insights::get_instance();
