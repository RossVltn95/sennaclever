<?php
/**
 * User Preferences Manager
 *
 * Handles storage and retrieval of user feed preferences,
 * integrating with existing matching and newsletter preferences.
 *
 * @package SennaFinanceCareer
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_User_Preferences {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * User meta keys for preferences
     */
    const META_FEED_TOPICS = 'sffc_feed_topics';
    const META_FEED_INDUSTRIES = 'sffc_feed_industries';
    const META_FEED_REGIONS = 'sffc_feed_regions';
    const META_FEED_DEAL_TYPES = 'sffc_feed_deal_types';
    const META_FEED_FIRMS = 'sffc_feed_firms';
    const META_FEED_KEYWORDS = 'sffc_feed_keywords';
    const META_PROFILE_HEADLINE = 'sffc_profile_headline';
    const META_PROFILE_AVATAR = 'sffc_profile_avatar';
    const META_SAVED_ARTICLES = 'sffc_saved_articles';
    const META_SAVED_JOBS = 'sffc_saved_jobs';
    const META_SAVED_DEALS = 'sffc_saved_deals';

    /**
     * Existing meta keys from other modals (reuse these)
     */
    const META_MATCHING_PREFS = 'sffc_matching_preferences';
    const META_NEWSLETTER_PREFS = 'senna_newsletter_preferences';

    /**
     * Available topic options
     */
    private $topic_options = array(
        'private_equity' => 'Private Equity',
        'ma' => 'M&A',
        'venture_capital' => 'Venture Capital',
        'hedge_funds' => 'Hedge Funds',
        'asset_management' => 'Asset Management',
        'investment_banking' => 'Investment Banking',
        'corporate_finance' => 'Corporate Finance',
        'restructuring' => 'Restructuring',
        'real_estate' => 'Real Estate',
        'infrastructure' => 'Infrastructure',
        'credit' => 'Credit / Debt',
        'esg' => 'ESG / Impact'
    );

    /**
     * Available industry options
     */
    private $industry_options = array(
        'technology' => 'Technology',
        'healthcare' => 'Healthcare / Life Sciences',
        'financial_services' => 'Financial Services',
        'consumer' => 'Consumer / Retail',
        'industrials' => 'Industrials / Manufacturing',
        'energy' => 'Energy / Utilities',
        'media' => 'Media / Entertainment',
        'telecom' => 'Telecommunications',
        'real_estate' => 'Real Estate',
        'transportation' => 'Transportation / Logistics'
    );

    /**
     * Available region options
     */
    private $region_options = array(
        'uk' => 'United Kingdom',
        'europe' => 'Europe (ex-UK)',
        'north_america' => 'North America',
        'asia_pacific' => 'Asia Pacific',
        'private_equity' => 'private equity',
        'latin_america' => 'Latin America',
        'africa' => 'Africa'
    );

    /**
     * Available deal type options
     */
    private $deal_type_options = array(
        'buyout' => 'Buyouts (LBO)',
        'growth' => 'Growth Equity',
        'venture' => 'Venture Rounds',
        'exits' => 'Exits / IPOs',
        'secondary' => 'Secondary Transactions',
        'addon' => 'Add-on Acquisitions',
        'fundraising' => 'Fundraising',
        'personnel' => 'Personnel Moves'
    );

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
        // Register REST API routes
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // AJAX handlers for logged-in users
        add_action('wp_ajax_sffc_save_feed_preferences', array($this, 'ajax_save_feed_preferences'));
        add_action('wp_ajax_sffc_toggle_saved_item', array($this, 'ajax_toggle_saved_item'));
        add_action('wp_ajax_sffc_get_preferences', array($this, 'ajax_get_preferences'));
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('sffc/v1', '/preferences', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'rest_get_preferences'),
                'permission_callback' => array($this, 'check_user_logged_in'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'rest_update_preferences'),
                'permission_callback' => array($this, 'check_user_logged_in'),
            ),
        ));

        register_rest_route('sffc/v1', '/saved/toggle', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_toggle_saved'),
            'permission_callback' => array($this, 'check_user_logged_in'),
        ));

        register_rest_route('sffc/v1', '/saved', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_saved_items'),
            'permission_callback' => array($this, 'check_user_logged_in'),
        ));
    }

    /**
     * Permission callback - check if user is logged in
     */
    public function check_user_logged_in() {
        return is_user_logged_in();
    }

    /**
     * Get all preferences for a user
     */
    public function get_all_preferences($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return null;
        }

        return array(
            // Feed preferences
            'topics' => $this->get_feed_topics($user_id),
            'industries' => $this->get_feed_industries($user_id),
            'regions' => $this->get_feed_regions($user_id),
            'deal_types' => $this->get_feed_deal_types($user_id),
            'firms' => $this->get_feed_firms($user_id),
            'keywords' => $this->get_feed_keywords($user_id),

            // Profile info
            'headline' => get_user_meta($user_id, self::META_PROFILE_HEADLINE, true),
            'avatar' => get_user_meta($user_id, self::META_PROFILE_AVATAR, true),

            // Saved items counts
            'saved_articles_count' => count($this->get_saved_items($user_id, 'articles')),
            'saved_jobs_count' => count($this->get_saved_items($user_id, 'jobs')),
            'saved_deals_count' => count($this->get_saved_items($user_id, 'deals')),

            // Existing preferences from other modals
            'matching_preferences' => get_user_meta($user_id, self::META_MATCHING_PREFS, true),
            'newsletter_preferences' => get_user_meta($user_id, self::META_NEWSLETTER_PREFS, true),

            // Available options for UI
            'available_options' => array(
                'topics' => $this->topic_options,
                'industries' => $this->industry_options,
                'regions' => $this->region_options,
                'deal_types' => $this->deal_type_options,
            ),
        );
    }

    /**
     * Get feed topics
     */
    public function get_feed_topics($user_id) {
        $topics = get_user_meta($user_id, self::META_FEED_TOPICS, true);
        return is_array($topics) ? $topics : array();
    }

    /**
     * Set feed topics
     */
    public function set_feed_topics($user_id, $topics) {
        $valid_topics = array_keys($this->topic_options);
        $topics = array_intersect((array) $topics, $valid_topics);
        return update_user_meta($user_id, self::META_FEED_TOPICS, $topics);
    }

    /**
     * Get feed industries
     */
    public function get_feed_industries($user_id) {
        $industries = get_user_meta($user_id, self::META_FEED_INDUSTRIES, true);
        return is_array($industries) ? $industries : array();
    }

    /**
     * Set feed industries
     */
    public function set_feed_industries($user_id, $industries) {
        $valid_industries = array_keys($this->industry_options);
        $industries = array_intersect((array) $industries, $valid_industries);
        return update_user_meta($user_id, self::META_FEED_INDUSTRIES, $industries);
    }

    /**
     * Get feed regions
     */
    public function get_feed_regions($user_id) {
        $regions = get_user_meta($user_id, self::META_FEED_REGIONS, true);
        return is_array($regions) ? $regions : array();
    }

    /**
     * Set feed regions
     */
    public function set_feed_regions($user_id, $regions) {
        $valid_regions = array_keys($this->region_options);
        $regions = array_intersect((array) $regions, $valid_regions);
        return update_user_meta($user_id, self::META_FEED_REGIONS, $regions);
    }

    /**
     * Get feed deal types
     */
    public function get_feed_deal_types($user_id) {
        $deal_types = get_user_meta($user_id, self::META_FEED_DEAL_TYPES, true);
        return is_array($deal_types) ? $deal_types : array();
    }

    /**
     * Set feed deal types
     */
    public function set_feed_deal_types($user_id, $deal_types) {
        $valid_types = array_keys($this->deal_type_options);
        $deal_types = array_intersect((array) $deal_types, $valid_types);
        return update_user_meta($user_id, self::META_FEED_DEAL_TYPES, $deal_types);
    }

    /**
     * Get tracked firms
     */
    public function get_feed_firms($user_id) {
        $firms = get_user_meta($user_id, self::META_FEED_FIRMS, true);
        return is_array($firms) ? $firms : array();
    }

    /**
     * Set tracked firms
     */
    public function set_feed_firms($user_id, $firms) {
        $firms = array_map('sanitize_text_field', (array) $firms);
        return update_user_meta($user_id, self::META_FEED_FIRMS, $firms);
    }

    /**
     * Get feed keywords
     */
    public function get_feed_keywords($user_id) {
        return get_user_meta($user_id, self::META_FEED_KEYWORDS, true) ?: '';
    }

    /**
     * Set feed keywords
     */
    public function set_feed_keywords($user_id, $keywords) {
        return update_user_meta($user_id, self::META_FEED_KEYWORDS, sanitize_text_field($keywords));
    }

    /**
     * Get saved items
     */
    public function get_saved_items($user_id, $type = 'articles') {
        $meta_key = '';
        switch ($type) {
            case 'articles':
                $meta_key = self::META_SAVED_ARTICLES;
                break;
            case 'jobs':
                $meta_key = self::META_SAVED_JOBS;
                break;
            case 'deals':
                $meta_key = self::META_SAVED_DEALS;
                break;
            default:
                return array();
        }

        $saved = get_user_meta($user_id, $meta_key, true);
        return is_array($saved) ? $saved : array();
    }

    /**
     * Toggle saved item (add or remove)
     */
    public function toggle_saved_item($user_id, $item_id, $type) {
        $item_id = absint($item_id);
        if (!$item_id) {
            return array('success' => false, 'message' => 'Invalid item ID');
        }

        $meta_key = '';
        switch ($type) {
            case 'article':
            case 'articles':
                $meta_key = self::META_SAVED_ARTICLES;
                break;
            case 'job':
            case 'jobs':
                $meta_key = self::META_SAVED_JOBS;
                break;
            case 'deal':
            case 'deals':
                $meta_key = self::META_SAVED_DEALS;
                break;
            default:
                return array('success' => false, 'message' => 'Invalid item type');
        }

        $saved = get_user_meta($user_id, $meta_key, true);
        if (!is_array($saved)) {
            $saved = array();
        }

        $is_saved = in_array($item_id, $saved);

        if ($is_saved) {
            // Remove from saved
            $saved = array_diff($saved, array($item_id));
            $saved = array_values($saved); // Re-index
        } else {
            // Add to saved
            $saved[] = $item_id;
        }

        update_user_meta($user_id, $meta_key, $saved);

        return array(
            'success' => true,
            'is_saved' => !$is_saved,
            'message' => !$is_saved ? 'Item saved' : 'Item removed from saved'
        );
    }

    /**
     * Check if item is saved
     */
    public function is_item_saved($user_id, $item_id, $type) {
        $saved = $this->get_saved_items($user_id, $type);
        return in_array(absint($item_id), $saved);
    }

    /**
     * Update profile headline
     */
    public function set_profile_headline($user_id, $headline) {
        return update_user_meta($user_id, self::META_PROFILE_HEADLINE, sanitize_text_field($headline));
    }

    /**
     * REST API: Get preferences
     */
    public function rest_get_preferences($request) {
        $user_id = get_current_user_id();
        $preferences = $this->get_all_preferences($user_id);

        return new WP_REST_Response($preferences, 200);
    }

    /**
     * REST API: Update preferences
     */
    public function rest_update_preferences($request) {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();

        if (isset($params['topics'])) {
            $this->set_feed_topics($user_id, $params['topics']);
        }
        if (isset($params['industries'])) {
            $this->set_feed_industries($user_id, $params['industries']);
        }
        if (isset($params['regions'])) {
            $this->set_feed_regions($user_id, $params['regions']);
        }
        if (isset($params['deal_types'])) {
            $this->set_feed_deal_types($user_id, $params['deal_types']);
        }
        if (isset($params['firms'])) {
            $this->set_feed_firms($user_id, $params['firms']);
        }
        if (isset($params['keywords'])) {
            $this->set_feed_keywords($user_id, $params['keywords']);
        }
        if (isset($params['headline'])) {
            $this->set_profile_headline($user_id, $params['headline']);
        }

        // Clear any cached feed data
        delete_transient('sffc_feed_' . $user_id);

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Preferences updated',
            'preferences' => $this->get_all_preferences($user_id)
        ), 200);
    }

    /**
     * REST API: Toggle saved item
     */
    public function rest_toggle_saved($request) {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();

        $item_id = isset($params['item_id']) ? absint($params['item_id']) : 0;
        $item_type = isset($params['item_type']) ? sanitize_text_field($params['item_type']) : '';

        $result = $this->toggle_saved_item($user_id, $item_id, $item_type);

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * REST API: Get saved items
     */
    public function rest_get_saved_items($request) {
        $user_id = get_current_user_id();
        $type = $request->get_param('type') ?: 'all';

        $result = array();

        if ($type === 'all' || $type === 'articles') {
            $result['articles'] = $this->get_saved_items_with_data($user_id, 'articles');
        }
        if ($type === 'all' || $type === 'jobs') {
            $result['jobs'] = $this->get_saved_items_with_data($user_id, 'jobs');
        }
        if ($type === 'all' || $type === 'deals') {
            $result['deals'] = $this->get_saved_items_with_data($user_id, 'deals');
        }

        return new WP_REST_Response($result, 200);
    }

    /**
     * Get saved items with their full data
     */
    private function get_saved_items_with_data($user_id, $type) {
        $item_ids = $this->get_saved_items($user_id, $type);

        if (empty($item_ids)) {
            return array();
        }

        $items = array();
        foreach ($item_ids as $item_id) {
            $post = get_post($item_id);
            if ($post && $post->post_status === 'publish') {
                $items[] = array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'excerpt' => wp_trim_words($post->post_content, 20),
                    'date' => get_the_date('M j, Y', $post),
                    'url' => get_permalink($post),
                    'type' => $type,
                );
            }
        }

        return $items;
    }

    /**
     * AJAX: Save feed preferences
     */
    public function ajax_save_feed_preferences() {
        check_ajax_referer('sffc_preferences_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        if (isset($_POST['topics'])) {
            $this->set_feed_topics($user_id, $_POST['topics']);
        }
        if (isset($_POST['industries'])) {
            $this->set_feed_industries($user_id, $_POST['industries']);
        }
        if (isset($_POST['regions'])) {
            $this->set_feed_regions($user_id, $_POST['regions']);
        }
        if (isset($_POST['deal_types'])) {
            $this->set_feed_deal_types($user_id, $_POST['deal_types']);
        }
        if (isset($_POST['keywords'])) {
            $this->set_feed_keywords($user_id, sanitize_text_field($_POST['keywords']));
        }

        // Clear cached feed
        delete_transient('sffc_feed_' . $user_id);

        wp_send_json_success(array('message' => 'Preferences saved'));
    }

    /**
     * AJAX: Toggle saved item
     */
    public function ajax_toggle_saved_item() {
        check_ajax_referer('sffc_saved_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        $item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;
        $item_type = isset($_POST['item_type']) ? sanitize_text_field($_POST['item_type']) : '';

        $result = $this->toggle_saved_item($user_id, $item_id, $item_type);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Get preferences
     */
    public function ajax_get_preferences() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }

        wp_send_json_success($this->get_all_preferences($user_id));
    }

    /**
     * Get topic options
     */
    public function get_topic_options() {
        return $this->topic_options;
    }

    /**
     * Get industry options
     */
    public function get_industry_options() {
        return $this->industry_options;
    }

    /**
     * Get region options
     */
    public function get_region_options() {
        return $this->region_options;
    }

    /**
     * Get deal type options
     */
    public function get_deal_type_options() {
        return $this->deal_type_options;
    }
}

// Initialize
add_action('plugins_loaded', function() {
    SFFC_User_Preferences::get_instance();
});

/**
 * Helper function to get user preferences instance
 */
function sffc_user_preferences() {
    return SFFC_User_Preferences::get_instance();
}
