<?php
/**
 * Dashboard Market Intelligence
 *
 * Aggregates news, deals, and market signals for the career dashboard.
 * Provides personalized market insights based on user preferences.
 *
 * @package SFFC_Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Dashboard_Market_Intelligence {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Cache duration in seconds (30 minutes)
     */
    const CACHE_DURATION = 1800;

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
     * Get market intelligence data for user
     */
    public function get_market_data($user_id, $filter = 'all') {
        $cache_key = 'sffc_market_intel_' . $user_id . '_' . $filter;
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $user_profile = $this->get_user_profile($user_id);

        $data = array(
            'news' => $this->get_news_feed($user_profile, $filter),
            'deals' => $this->get_deals_feed($user_profile, $filter),
            'signals' => $this->generate_market_signals($user_profile),
            'trending_topics' => $this->get_trending_topics(),
            'saved_articles' => $this->get_saved_articles($user_id),
        );

        set_transient($cache_key, $data, self::CACHE_DURATION);

        return $data;
    }

    /**
     * Get news feed from sffc_pe_news post type
     */
    private function get_news_feed($user_profile, $filter) {
        if ($filter === 'deals') {
            return array();
        }

        $args = array(
            'post_type' => 'sffc_pe_news',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        // Filter by user's industries if available
        $industries = (array)($user_profile['preferred_industries'] ?? array());
        if (!empty($industries)) {
            // Try to filter by taxonomy if it exists
            $args['meta_query'] = array(
                'relation' => 'OR',
            );

            foreach ($industries as $industry) {
                $args['meta_query'][] = array(
                    'key' => 'industry',
                    'value' => $industry,
                    'compare' => 'LIKE',
                );
            }
        }

        $posts = get_posts($args);
        $news = array();

        foreach ($posts as $post) {
            $sentiment = $this->analyze_sentiment($post->post_title . ' ' . $post->post_content);
            $relevance = $this->calculate_relevance($post, $user_profile);

            $news[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'excerpt' => $this->get_excerpt($post->post_content, 120),
                'date' => $this->format_date($post->post_date),
                'date_raw' => $post->post_date,
                'source' => get_post_meta($post->ID, 'source', true) ?: 'Industry News',
                'url' => get_permalink($post->ID),
                'type' => 'news',
                'sentiment' => $sentiment,
                'relevance' => $relevance,
                'industry' => get_post_meta($post->ID, 'industry', true) ?: 'Finance',
                'location' => get_post_meta($post->ID, 'location', true) ?: '',
                'impact' => $this->assess_career_impact($post, $user_profile),
            );
        }

        // Sort by relevance
        usort($news, function($a, $b) {
            return $b['relevance'] - $a['relevance'];
        });

        return array_slice($news, 0, 15);
    }

    /**
     * Get deals feed from sffc_pe_deals post type
     */
    private function get_deals_feed($user_profile, $filter) {
        if ($filter === 'news') {
            return array();
        }

        $args = array(
            'post_type' => 'sffc_pe_deal',
            'post_status' => 'publish',
            'posts_per_page' => 15,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $posts = get_posts($args);
        $deals = array();

        foreach ($posts as $post) {
            $deal_value = get_post_meta($post->ID, 'deal_value', true);
            $deal_type = get_post_meta($post->ID, 'deal_type', true) ?: 'Investment';
            $sector = get_post_meta($post->ID, 'sector', true) ?: 'Finance';

            $deals[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'excerpt' => $this->get_excerpt($post->post_content, 100),
                'date' => $this->format_date($post->post_date),
                'date_raw' => $post->post_date,
                'type' => 'deal',
                'deal_type' => $deal_type,
                'deal_value' => $this->format_deal_value($deal_value),
                'deal_value_raw' => floatval($deal_value),
                'sector' => $sector,
                'url' => get_permalink($post->ID),
                'hiring_impact' => $this->assess_hiring_impact($deal_type, $deal_value),
                'companies' => get_post_meta($post->ID, 'companies', true) ?: '',
            );
        }

        return $deals;
    }

    /**
     * Generate market signals from recent news and deals
     */
    private function generate_market_signals($user_profile) {
        // Use AI insights if available
        if (class_exists('SFFC_Dashboard_AI_Insights')) {
            $ai = SFFC_Dashboard_AI_Insights::get_instance();
            $signals = $ai->generate_market_signals($user_profile);
            if (!empty($signals)) {
                return $signals;
            }
        }

        // Fallback: Generate signals from recent posts
        $signals = array();

        // Get recent news for signal analysis
        $recent_news = get_posts(array(
            'post_type' => array('sffc_pe_news', 'sffc_pe_deals'),
            'post_status' => 'publish',
            'posts_per_page' => 30,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        $positive_count = 0;
        $negative_count = 0;
        $locations_mentioned = array();
        $industries_mentioned = array();

        foreach ($recent_news as $post) {
            $content = strtolower($post->post_title . ' ' . $post->post_content);

            // Positive signals
            if (preg_match('/(expan|grow|hir|invest|profit|record|boom|surge)/i', $content)) {
                $positive_count++;
            }

            // Negative signals
            if (preg_match('/(layoff|cut|downsiz|declin|loss|close|restructur)/i', $content)) {
                $negative_count++;
            }

            // Extract locations
            $location = get_post_meta($post->ID, 'location', true);
            if ($location) {
                $locations_mentioned[$location] = ($locations_mentioned[$location] ?? 0) + 1;
            }

            // Extract industries
            $industry = get_post_meta($post->ID, 'industry', true);
            if ($industry) {
                $industries_mentioned[$industry] = ($industries_mentioned[$industry] ?? 0) + 1;
            }
        }

        // Generate signals based on analysis
        if ($positive_count > $negative_count * 2) {
            $signals[] = array(
                'type' => 'positive',
                'text' => 'Market sentiment is strongly positive with increased hiring activity across sectors',
                'icon' => 'trending-up',
            );
        } elseif ($negative_count > $positive_count) {
            $signals[] = array(
                'type' => 'negative',
                'text' => 'Some sectors showing contraction - consider diversifying your target industries',
                'icon' => 'alert',
            );
        } else {
            $signals[] = array(
                'type' => 'neutral',
                'text' => 'Market conditions stable with balanced hiring activity',
                'icon' => 'info',
            );
        }

        // Location-specific signals
        arsort($locations_mentioned);
        $top_location = key($locations_mentioned);
        if ($top_location) {
            $user_locations = (array)($user_profile['preferred_locations'] ?? array());
            $is_user_location = in_array($top_location, $user_locations);

            $signals[] = array(
                'type' => $is_user_location ? 'positive' : 'neutral',
                'text' => $top_location . ' is seeing high activity in finance sector' .
                    ($is_user_location ? ' - matches your preference' : ''),
                'icon' => 'map-pin',
            );
        }

        // Industry-specific signals
        arsort($industries_mentioned);
        $top_industry = key($industries_mentioned);
        if ($top_industry) {
            $user_industries = (array)($user_profile['preferred_industries'] ?? array());
            $is_user_industry = false;

            foreach ($user_industries as $ui) {
                if (stripos($top_industry, $ui) !== false || stripos($ui, $top_industry) !== false) {
                    $is_user_industry = true;
                    break;
                }
            }

            $signals[] = array(
                'type' => $is_user_industry ? 'positive' : 'neutral',
                'text' => $top_industry . ' sector showing strong deal flow' .
                    ($is_user_industry ? ' - aligns with your interests' : ''),
                'icon' => 'briefcase',
            );
        }

        return array_slice($signals, 0, 5);
    }

    /**
     * Get trending topics from recent content
     */
    private function get_trending_topics() {
        $cache_key = 'sffc_trending_topics';
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $topics = array();

        // Get recent posts
        $posts = get_posts(array(
            'post_type' => array('sffc_pe_news', 'sffc_pe_deals'),
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        // Extract keywords
        $keywords = array();
        $finance_terms = array(
            'M&A', 'IPO', 'PE', 'VC', 'hedge fund', 'investment banking',
            'fintech', 'crypto', 'AI', 'ESG', 'private credit', 'restructuring',
            'secondary', 'buyout', 'growth equity', 'venture'
        );

        foreach ($posts as $post) {
            $content = $post->post_title . ' ' . $post->post_content;

            foreach ($finance_terms as $term) {
                if (stripos($content, $term) !== false) {
                    $keywords[$term] = ($keywords[$term] ?? 0) + 1;
                }
            }
        }

        // Sort by frequency
        arsort($keywords);

        // Format as topics
        $count = 0;
        foreach ($keywords as $term => $freq) {
            if ($count >= 8) break;

            $topics[] = array(
                'term' => $term,
                'count' => $freq,
                'trend' => $freq > 5 ? 'hot' : ($freq > 2 ? 'rising' : 'stable'),
            );
            $count++;
        }

        set_transient($cache_key, $topics, HOUR_IN_SECONDS);

        return $topics;
    }

    /**
     * Get user's saved articles
     */
    private function get_saved_articles($user_id) {
        $saved = get_user_meta($user_id, 'sffc_saved_articles', true);
        return is_array($saved) ? $saved : array();
    }

    /**
     * Save an article for later
     */
    public function save_article($user_id, $post_id) {
        $saved = $this->get_saved_articles($user_id);

        if (!in_array($post_id, $saved)) {
            $saved[] = $post_id;
            update_user_meta($user_id, 'sffc_saved_articles', $saved);
        }

        return true;
    }

    /**
     * Remove saved article
     */
    public function unsave_article($user_id, $post_id) {
        $saved = $this->get_saved_articles($user_id);
        $saved = array_diff($saved, array($post_id));
        update_user_meta($user_id, 'sffc_saved_articles', array_values($saved));

        return true;
    }

    /**
     * Analyze sentiment of text
     */
    private function analyze_sentiment($text) {
        $text = strtolower($text);

        $positive_words = array(
            'growth', 'profit', 'success', 'expansion', 'hire', 'hiring',
            'invest', 'investment', 'opportunity', 'record', 'surge', 'boom',
            'increase', 'gain', 'positive', 'strong', 'robust'
        );

        $negative_words = array(
            'layoff', 'cut', 'loss', 'decline', 'downturn', 'closure',
            'restructure', 'downsize', 'reduce', 'weak', 'concern', 'risk',
            'warning', 'drop', 'fall', 'struggle'
        );

        $positive_score = 0;
        $negative_score = 0;

        foreach ($positive_words as $word) {
            $positive_score += substr_count($text, $word);
        }

        foreach ($negative_words as $word) {
            $negative_score += substr_count($text, $word);
        }

        if ($positive_score > $negative_score + 1) {
            return 'positive';
        } elseif ($negative_score > $positive_score + 1) {
            return 'negative';
        }

        return 'neutral';
    }

    /**
     * Calculate relevance score for user
     */
    private function calculate_relevance($post, $user_profile) {
        $score = 50; // Base score

        $content = strtolower($post->post_title . ' ' . $post->post_content);
        $post_industry = strtolower(get_post_meta($post->ID, 'industry', true) ?: '');
        $post_location = strtolower(get_post_meta($post->ID, 'location', true) ?: '');

        // Industry match
        $user_industries = (array)($user_profile['preferred_industries'] ?? array());
        foreach ($user_industries as $industry) {
            if (stripos($content, $industry) !== false || stripos($post_industry, strtolower($industry)) !== false) {
                $score += 20;
                break;
            }
        }

        // Location match
        $user_locations = (array)($user_profile['preferred_locations'] ?? array());
        foreach ($user_locations as $location) {
            if (stripos($content, $location) !== false || stripos($post_location, strtolower($location)) !== false) {
                $score += 15;
                break;
            }
        }

        // Recency bonus
        $days_old = (time() - strtotime($post->post_date)) / DAY_IN_SECONDS;
        if ($days_old < 1) {
            $score += 15;
        } elseif ($days_old < 3) {
            $score += 10;
        } elseif ($days_old < 7) {
            $score += 5;
        }

        return min(100, $score);
    }

    /**
     * Assess career impact of news
     */
    private function assess_career_impact($post, $user_profile) {
        $content = strtolower($post->post_title . ' ' . $post->post_content);

        // High impact keywords
        if (preg_match('/(major hire|expansion|new office|record bonus|significant invest)/i', $content)) {
            return array(
                'level' => 'high',
                'message' => 'High potential impact on job opportunities',
            );
        }

        // Medium impact
        if (preg_match('/(hiring|growth|invest|partnership|deal)/i', $content)) {
            return array(
                'level' => 'medium',
                'message' => 'May create new opportunities',
            );
        }

        // Negative impact
        if (preg_match('/(layoff|downsiz|cut|restructur)/i', $content)) {
            return array(
                'level' => 'negative',
                'message' => 'May reduce opportunities in this area',
            );
        }

        return array(
            'level' => 'low',
            'message' => 'General industry update',
        );
    }

    /**
     * Assess hiring impact of a deal
     */
    private function assess_hiring_impact($deal_type, $deal_value) {
        $value = floatval($deal_value);

        $impact = array(
            'level' => 'low',
            'message' => 'Limited direct hiring impact',
        );

        if ($deal_type === 'Acquisition' || $deal_type === 'Buyout') {
            $impact = array(
                'level' => 'high',
                'message' => 'M&A activity often creates advisory and integration roles',
            );
        } elseif ($deal_type === 'Investment' || $deal_type === 'Funding') {
            if ($value >= 100) { // $100M+
                $impact = array(
                    'level' => 'high',
                    'message' => 'Large funding round typically leads to significant hiring',
                );
            } elseif ($value >= 20) {
                $impact = array(
                    'level' => 'medium',
                    'message' => 'Funding may support team expansion',
                );
            }
        } elseif ($deal_type === 'IPO') {
            $impact = array(
                'level' => 'high',
                'message' => 'IPOs often require expanded finance and compliance teams',
            );
        }

        return $impact;
    }

    /**
     * Format deal value
     */
    private function format_deal_value($value) {
        $num = floatval($value);

        if ($num >= 1000) {
            return '$' . number_format($num / 1000, 1) . 'B';
        } elseif ($num >= 1) {
            return '$' . number_format($num, 0) . 'M';
        } elseif ($num > 0) {
            return '$' . number_format($num * 1000, 0) . 'K';
        }

        return 'Undisclosed';
    }

    /**
     * Get excerpt from content
     */
    private function get_excerpt($content, $length = 150) {
        $content = wp_strip_all_tags($content);
        $content = strip_shortcodes($content);

        if (strlen($content) <= $length) {
            return $content;
        }

        $excerpt = substr($content, 0, $length);
        $last_space = strrpos($excerpt, ' ');

        if ($last_space !== false) {
            $excerpt = substr($excerpt, 0, $last_space);
        }

        return $excerpt . '...';
    }

    /**
     * Format date for display
     */
    private function format_date($date) {
        $timestamp = strtotime($date);
        $diff = time() - $timestamp;

        if ($diff < HOUR_IN_SECONDS) {
            $mins = round($diff / MINUTE_IN_SECONDS);
            return $mins . 'm ago';
        } elseif ($diff < DAY_IN_SECONDS) {
            $hours = round($diff / HOUR_IN_SECONDS);
            return $hours . 'h ago';
        } elseif ($diff < WEEK_IN_SECONDS) {
            $days = round($diff / DAY_IN_SECONDS);
            return $days . 'd ago';
        }

        return date('M j', $timestamp);
    }

    /**
     * Get user profile
     */
    private function get_user_profile($user_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_user_profiles';

        $profile = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        if ($profile) {
            // Decode JSON fields
            $json_fields = array('preferred_industries', 'preferred_locations');
            foreach ($json_fields as $field) {
                if (!empty($profile[$field]) && is_string($profile[$field])) {
                    $decoded = json_decode($profile[$field], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $profile[$field] = $decoded;
                    }
                }
            }
        }

        return $profile ?: array();
    }

    /**
     * Clear caches
     */
    public function clear_caches($user_id = null) {
        global $wpdb;

        if ($user_id) {
            delete_transient('sffc_market_intel_' . $user_id . '_all');
            delete_transient('sffc_market_intel_' . $user_id . '_news');
            delete_transient('sffc_market_intel_' . $user_id . '_deals');
        } else {
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sffc_market_intel_%'");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_sffc_market_intel_%'");
        }

        delete_transient('sffc_trending_topics');
    }
}

// Initialize
SFFC_Dashboard_Market_Intelligence::get_instance();
