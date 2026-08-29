<?php
/**
 * Personalized Feed Generator
 *
 * Generates curated feeds of news, deals, and jobs based on user preferences.
 * This is the intelligence engine that matches content to user interests.
 *
 * @package SennaFinanceCareer
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Personalized_Feed {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * User preferences instance
     */
    private $preferences;

    /**
     * Cache duration in seconds (15 minutes)
     */
    const CACHE_DURATION = 900;

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
        $this->preferences = SFFC_User_Preferences::get_instance();

        // Register REST API routes
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // AJAX handler
        add_action('wp_ajax_sffc_get_personalized_feed', array($this, 'ajax_get_feed'));
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('sffc/v1', '/feed', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_feed'),
            'permission_callback' => '__return_true', // Allow both logged in and guests
            'args' => array(
                'limit' => array(
                    'default' => 20,
                    'sanitize_callback' => 'absint',
                ),
                'offset' => array(
                    'default' => 0,
                    'sanitize_callback' => 'absint',
                ),
                'filter' => array(
                    'default' => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }

    /**
     * Get personalized feed for a user
     */
    public function get_feed($user_id = null, $limit = 20, $offset = 0, $filter = 'all') {
        // Check cache first
        $cache_key = 'sffc_feed_' . ($user_id ?: 'guest') . '_' . $filter . '_' . $limit . '_' . $offset;
        $cached = get_transient($cache_key);

        if ($cached !== false && !defined('SFFC_DISABLE_FEED_CACHE')) {
            return $cached;
        }

        // Get user preferences if logged in
        $prefs = array();
        if ($user_id) {
            $prefs = array(
                'topics' => $this->preferences->get_feed_topics($user_id),
                'industries' => $this->preferences->get_feed_industries($user_id),
                'regions' => $this->preferences->get_feed_regions($user_id),
                'deal_types' => $this->preferences->get_feed_deal_types($user_id),
                'firms' => $this->preferences->get_feed_firms($user_id),
                'keywords' => $this->preferences->get_feed_keywords($user_id),
            );
        }

        // Query content based on filter
        $feed_items = array();

        if ($filter === 'all' || $filter === 'news') {
            $news_items = $this->query_news($prefs, $limit, $offset);
            $feed_items = array_merge($feed_items, $news_items);
        }

        if ($filter === 'all' || $filter === 'jobs') {
            $job_items = $this->query_jobs($prefs, $limit, $offset);
            $feed_items = array_merge($feed_items, $job_items);
        }

        if ($filter === 'all' || $filter === 'deals') {
            $deal_items = $this->query_deals($prefs, $limit, $offset);
            $feed_items = array_merge($feed_items, $deal_items);
        }

        // Sort by score and date
        usort($feed_items, function($a, $b) {
            // Higher score first
            if ($a['score'] !== $b['score']) {
                return $b['score'] - $a['score'];
            }
            // Then by date (newer first)
            return strtotime($b['date']) - strtotime($a['date']);
        });

        // Apply limit after merge and sort
        if ($filter === 'all') {
            $feed_items = array_slice($feed_items, 0, $limit);
        }

        // Group by date
        $grouped_feed = $this->group_by_date($feed_items);

        // Cache the result
        set_transient($cache_key, $grouped_feed, self::CACHE_DURATION);

        return $grouped_feed;
    }

    /**
     * Query news posts matching preferences
     */
    private function query_news($prefs, $limit, $offset) {
        $args = array(
            'post_type' => array('post', 'sffc_pe_news'),
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'offset' => $offset,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        // Add taxonomy queries if user has preferences
        $tax_query = array();

        if (!empty($prefs['topics'])) {
            // Map topics to taxonomy terms if applicable
            $tax_query[] = array(
                'taxonomy' => 'category',
                'field' => 'slug',
                'terms' => $prefs['topics'],
                'operator' => 'IN',
            );
        }

        if (!empty($tax_query)) {
            $args['tax_query'] = array_merge(array('relation' => 'OR'), $tax_query);
        }

        // Add keyword search if set
        if (!empty($prefs['keywords'])) {
            $args['s'] = $prefs['keywords'];
        }

        $query = new WP_Query($args);
        $items = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                $items[] = array(
                    'id' => $post_id,
                    'type' => 'news',
                    'title' => get_the_title(),
                    'excerpt' => wp_trim_words(get_the_excerpt(), 25),
                    'date' => get_the_date('c'),
                    'date_display' => get_the_date('M j, Y'),
                    'url' => get_permalink(),
                    'image' => get_the_post_thumbnail_url($post_id, 'medium'),
                    'score' => $this->calculate_match_score(get_post(), $prefs),
                    'matches' => $this->get_match_tags(get_post(), $prefs),
                    'source' => $this->get_news_source($post_id),
                );
            }
            wp_reset_postdata();
        }

        return $items;
    }

    /**
     * Query jobs matching preferences
     */
    private function query_jobs($prefs, $limit, $offset) {
        // Check if jobs exist in the system
        $job_post_types = array('job_listing', 'sffc_job', 'jobs');
        $active_job_type = null;

        foreach ($job_post_types as $type) {
            if (post_type_exists($type)) {
                $active_job_type = $type;
                break;
            }
        }

        if (!$active_job_type) {
            // Return empty if no job post type exists
            return array();
        }

        $args = array(
            'post_type' => $active_job_type,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'offset' => $offset,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        // Add meta queries based on job preferences
        $meta_query = array();

        // Could add location, salary, etc. filters here based on matching_preferences

        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        $query = new WP_Query($args);
        $items = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                $items[] = array(
                    'id' => $post_id,
                    'type' => 'job',
                    'title' => get_the_title(),
                    'excerpt' => wp_trim_words(get_the_excerpt(), 20),
                    'date' => get_the_date('c'),
                    'date_display' => get_the_date('M j, Y'),
                    'url' => get_permalink(),
                    'company' => $this->get_job_company($post_id),
                    'location' => $this->get_job_location($post_id),
                    'score' => $this->calculate_job_match_score($post_id, $prefs),
                    'matches' => array('Jobs'),
                );
            }
            wp_reset_postdata();
        }

        return $items;
    }

    /**
     * Query deals matching preferences
     */
    private function query_deals($prefs, $limit, $offset) {
        // Check if deals post type exists
        if (!post_type_exists('sffc_pe_deal')) {
            return array();
        }

        $args = array(
            'post_type' => 'sffc_pe_deal',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'offset' => $offset,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        // Filter by deal types if set
        if (!empty($prefs['deal_types'])) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'deal_type',
                    'field' => 'slug',
                    'terms' => $prefs['deal_types'],
                    'operator' => 'IN',
                ),
            );
        }

        $query = new WP_Query($args);
        $items = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                $items[] = array(
                    'id' => $post_id,
                    'type' => 'deal',
                    'title' => get_the_title(),
                    'excerpt' => wp_trim_words(get_the_excerpt(), 20),
                    'date' => get_the_date('c'),
                    'date_display' => get_the_date('M j, Y'),
                    'url' => get_permalink(),
                    'deal_value' => get_post_meta($post_id, '_deal_value', true),
                    'deal_type' => $this->get_deal_type($post_id),
                    'score' => $this->calculate_match_score(get_post(), $prefs),
                    'matches' => $this->get_match_tags(get_post(), $prefs),
                );
            }
            wp_reset_postdata();
        }

        return $items;
    }

    /**
     * Calculate match score for a post
     */
    private function calculate_match_score($post, $prefs) {
        if (empty($prefs)) {
            return 50; // Default score for guests
        }

        $score = 50; // Base score

        // Check topic matches
        $post_categories = wp_get_post_categories($post->ID, array('fields' => 'slugs'));
        if (!empty($prefs['topics'])) {
            $topic_matches = array_intersect($prefs['topics'], $post_categories);
            $score += count($topic_matches) * 10;
        }

        // Check keyword matches in title/content
        if (!empty($prefs['keywords'])) {
            $keywords = array_map('trim', explode(',', $prefs['keywords']));
            $content = strtolower($post->post_title . ' ' . $post->post_content);

            foreach ($keywords as $keyword) {
                if (stripos($content, strtolower($keyword)) !== false) {
                    $score += 5;
                }
            }
        }

        // Check firm matches (if firms are mentioned in content)
        if (!empty($prefs['firms'])) {
            $content = strtolower($post->post_title . ' ' . $post->post_content);
            foreach ($prefs['firms'] as $firm) {
                if (stripos($content, strtolower($firm)) !== false) {
                    $score += 15; // Higher weight for tracked firms
                }
            }
        }

        // Boost recent content
        $days_old = (time() - strtotime($post->post_date)) / DAY_IN_SECONDS;
        if ($days_old < 1) {
            $score += 10; // Today's content
        } elseif ($days_old < 7) {
            $score += 5; // This week's content
        }

        return min($score, 100); // Cap at 100
    }

    /**
     * Calculate job match score
     */
    private function calculate_job_match_score($post_id, $prefs) {
        // Get user's matching preferences from the matching modal
        $user_id = get_current_user_id();
        if (!$user_id) {
            return 50;
        }

        $matching_prefs = get_user_meta($user_id, 'sffc_matching_preferences', true);
        if (empty($matching_prefs)) {
            return 50;
        }

        $score = 50;

        // Could compare job location with preferred_locations
        // Could compare job type with career_stage
        // Could compare salary with salary expectations
        // etc.

        return min($score, 100);
    }

    /**
     * Get match tags for display
     */
    private function get_match_tags($post, $prefs) {
        $tags = array();

        if (empty($prefs)) {
            return array();
        }

        // Get matching topics
        $post_categories = wp_get_post_categories($post->ID, array('fields' => 'slugs'));
        if (!empty($prefs['topics'])) {
            $topic_options = $this->preferences->get_topic_options();
            foreach ($prefs['topics'] as $topic) {
                if (in_array($topic, $post_categories) && isset($topic_options[$topic])) {
                    $tags[] = $topic_options[$topic];
                }
            }
        }

        // Check for firm matches
        if (!empty($prefs['firms'])) {
            $content = strtolower($post->post_title . ' ' . $post->post_content);
            foreach ($prefs['firms'] as $firm) {
                if (stripos($content, strtolower($firm)) !== false) {
                    $tags[] = $firm;
                }
            }
        }

        return array_slice($tags, 0, 3); // Limit to 3 tags
    }

    /**
     * Group feed items by date
     */
    private function group_by_date($items) {
        $grouped = array();
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        foreach ($items as $item) {
            $item_date = date('Y-m-d', strtotime($item['date']));

            if ($item_date === $today) {
                $group_label = 'Today';
            } elseif ($item_date === $yesterday) {
                $group_label = 'Yesterday';
            } else {
                $group_label = date('F j, Y', strtotime($item['date']));
            }

            if (!isset($grouped[$group_label])) {
                $grouped[$group_label] = array();
            }

            $grouped[$group_label][] = $item;
        }

        return $grouped;
    }

    /**
     * Get news source
     */
    private function get_news_source($post_id) {
        $source = get_post_meta($post_id, '_news_source', true);
        return $source ?: 'MENA Careers Intelligence';
    }

    /**
     * Get job company
     */
    private function get_job_company($post_id) {
        $company = get_post_meta($post_id, '_company_name', true);
        if (!$company) {
            $company = get_post_meta($post_id, 'company', true);
        }
        return $company ?: '';
    }

    /**
     * Get job location
     */
    private function get_job_location($post_id) {
        $location = get_post_meta($post_id, '_job_location', true);
        if (!$location) {
            $location = get_post_meta($post_id, 'location', true);
        }
        return $location ?: '';
    }

    /**
     * Get deal type
     */
    private function get_deal_type($post_id) {
        $terms = wp_get_post_terms($post_id, 'deal_type', array('fields' => 'names'));
        return !empty($terms) && !is_wp_error($terms) ? $terms[0] : '';
    }

    /**
     * REST API: Get feed
     */
    public function rest_get_feed($request) {
        $user_id = get_current_user_id();
        $limit = $request->get_param('limit');
        $offset = $request->get_param('offset');
        $filter = $request->get_param('filter');

        $feed = $this->get_feed($user_id, $limit, $offset, $filter);

        return new WP_REST_Response(array(
            'success' => true,
            'feed' => $feed,
            'has_preferences' => $user_id && $this->user_has_preferences($user_id),
        ), 200);
    }

    /**
     * Check if user has set any preferences
     */
    private function user_has_preferences($user_id) {
        $topics = $this->preferences->get_feed_topics($user_id);
        $industries = $this->preferences->get_feed_industries($user_id);
        $keywords = $this->preferences->get_feed_keywords($user_id);

        return !empty($topics) || !empty($industries) || !empty($keywords);
    }

    /**
     * AJAX: Get feed
     */
    public function ajax_get_feed() {
        $user_id = get_current_user_id();
        $limit = isset($_GET['limit']) ? absint($_GET['limit']) : 20;
        $offset = isset($_GET['offset']) ? absint($_GET['offset']) : 0;
        $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';

        $feed = $this->get_feed($user_id, $limit, $offset, $filter);

        wp_send_json_success(array(
            'feed' => $feed,
            'has_preferences' => $user_id && $this->user_has_preferences($user_id),
        ));
    }

    /**
     * Clear feed cache for a user
     */
    public function clear_user_cache($user_id) {
        global $wpdb;

        // Delete all transients for this user's feed
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_sffc_feed_' . $user_id . '%'
        ));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_sffc_feed_' . $user_id . '%'
        ));
    }
}

// Initialize
add_action('plugins_loaded', function() {
    // Ensure preferences class is loaded first
    if (class_exists('SFFC_User_Preferences')) {
        SFFC_Personalized_Feed::get_instance();
    }
}, 20);

/**
 * Helper function to get feed instance
 */
function sffc_personalized_feed() {
    return SFFC_Personalized_Feed::get_instance();
}
