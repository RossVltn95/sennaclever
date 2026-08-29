<?php
/**
 * Dashboard Membership Handler
 *
 * Handles MemberPress integration, feature gating, and upgrade prompts
 * for the career dashboard.
 *
 * @package SFFC_Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Dashboard_Membership_Handler {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Tier definitions with features
     */
    private $tiers = array(
        'free' => array(
            'name' => 'Free',
            'price' => 0,
            'features' => array(
                'stats_refresh' => 'weekly',
                'trends_range' => 30,
                'skills_limit' => 5,
                'news_limit' => 5,
                'salary_locations' => 1,
                'export_enabled' => false,
                'ai_insights' => false,
                'priority_refresh' => false,
            ),
            'limits' => array(
                'profile_views' => 10,
                'job_matches' => 5,
                'saved_articles' => 5,
            ),
        ),
        'professional' => array(
            'name' => 'Professional',
            'price' => 49.99,
            'currency' => 'GBP',
            'features' => array(
                'stats_refresh' => 'daily',
                'trends_range' => 365,
                'skills_limit' => 999,
                'news_limit' => 20,
                'salary_locations' => 3,
                'export_enabled' => true,
                'ai_insights' => false,
                'priority_refresh' => false,
            ),
            'limits' => array(
                'profile_views' => 100,
                'job_matches' => 25,
                'saved_articles' => 50,
            ),
        ),
        'executive' => array(
            'name' => 'Executive',
            'price' => 69.99,
            'currency' => 'GBP',
            'features' => array(
                'stats_refresh' => 'realtime',
                'trends_range' => 999,
                'skills_limit' => 999,
                'news_limit' => 999,
                'salary_locations' => 999,
                'export_enabled' => true,
                'ai_insights' => true,
                'priority_refresh' => true,
            ),
            'limits' => array(
                'profile_views' => 999,
                'job_matches' => 999,
                'saved_articles' => 999,
            ),
        ),
    );

    /**
     * Feature descriptions for upgrade prompts
     */
    private $feature_descriptions = array(
        'stats_refresh' => array(
            'free' => 'Weekly stats refresh',
            'professional' => 'Daily stats refresh',
            'executive' => 'Real-time stats refresh',
        ),
        'trends_range' => array(
            'free' => '30-day trend history',
            'professional' => '12-month trend history',
            'executive' => 'Unlimited trend history',
        ),
        'skills_limit' => array(
            'free' => 'Top 5 skills analysis',
            'professional' => 'Full skills analysis',
            'executive' => 'Full skills analysis + AI recommendations',
        ),
        'news_limit' => array(
            'free' => '5 news articles per day',
            'professional' => '20 news articles per day',
            'executive' => 'Unlimited news access',
        ),
        'salary_locations' => array(
            'free' => '1 location comparison',
            'professional' => '3 location comparisons',
            'executive' => 'Unlimited location comparisons',
        ),
        'export_enabled' => array(
            'free' => 'No export',
            'professional' => 'Export reports',
            'executive' => 'Export all data',
        ),
        'ai_insights' => array(
            'free' => 'Basic insights',
            'professional' => 'Basic insights',
            'executive' => 'AI-powered insights',
        ),
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
        // Register AJAX handlers
        add_action('wp_ajax_sffc_get_membership_info', array($this, 'ajax_get_membership_info'));
        add_action('wp_ajax_sffc_check_feature_access', array($this, 'ajax_check_feature_access'));
        add_action('wp_ajax_sffc_track_usage', array($this, 'ajax_track_usage'));
    }

    /**
     * Get user's current membership tier
     */
    public function get_user_tier($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return 'free';
        }

        // Check MemberPress
        if (class_exists('MeprUser')) {
            $mepr_user = new MeprUser($user_id);
            $active_subs = $mepr_user->active_product_subscriptions();

            if (!empty($active_subs)) {
                $executive_products = get_option('sffc_executive_product_ids', array());
                $professional_products = get_option('sffc_professional_product_ids', array());

                foreach ($active_subs as $product_id) {
                    if (in_array($product_id, (array)$executive_products)) {
                        return 'executive';
                    }
                    if (in_array($product_id, (array)$professional_products)) {
                        return 'professional';
                    }
                }
            }
        }

        // Check user meta as fallback
        $meta_level = get_user_meta($user_id, 'sffc_membership_level', true);
        if (!empty($meta_level) && isset($this->tiers[$meta_level])) {
            return $meta_level;
        }

        return 'free';
    }

    /**
     * Get full membership data for a user
     */
    public function get_membership_data($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $tier = $this->get_user_tier($user_id);
        $tier_data = $this->tiers[$tier];

        // Get subscription details from MemberPress
        $subscription = $this->get_subscription_details($user_id);

        // Get usage stats
        $usage = $this->get_usage_stats($user_id);

        return array(
            'tier' => $tier,
            'name' => $tier_data['name'],
            'price' => $tier_data['price'],
            'currency' => $tier_data['currency'] ?? 'GBP',
            'features' => $tier_data['features'],
            'limits' => $tier_data['limits'],
            'subscription' => $subscription,
            'usage' => $usage,
            'upgrade_available' => $tier !== 'executive',
            'next_tier' => $this->get_next_tier($tier),
        );
    }

    /**
     * Get subscription details from MemberPress
     */
    private function get_subscription_details($user_id) {
        $details = array(
            'status' => 'none',
            'expires' => null,
            'auto_renew' => false,
            'payment_method' => null,
            'next_billing' => null,
        );

        if (!class_exists('MeprUser')) {
            return $details;
        }

        $mepr_user = new MeprUser($user_id);
        $subscriptions = $mepr_user->active_product_subscriptions('ids', true);

        if (empty($subscriptions)) {
            return $details;
        }

        // Get the most relevant subscription
        if (class_exists('MeprSubscription')) {
            $user_subs = MeprSubscription::get_all_by_user_id($user_id);
            foreach ($user_subs as $sub) {
                if ($sub->status === 'active') {
                    $details['status'] = 'active';
                    $details['expires'] = $sub->expires_at !== '0000-00-00 00:00:00' ? $sub->expires_at : null;
                    $details['auto_renew'] = $sub->status === 'active' && empty($sub->expires_at);
                    $details['next_billing'] = $sub->next_billing_at ?? null;

                    // Get payment method
                    if (!empty($sub->gateway)) {
                        $details['payment_method'] = $this->format_payment_method($sub->gateway);
                    }
                    break;
                }
            }
        }

        return $details;
    }

    /**
     * Format payment method for display
     */
    private function format_payment_method($gateway) {
        $methods = array(
            'stripe' => 'Credit Card (Stripe)',
            'paypal' => 'PayPal',
            'manual' => 'Manual Payment',
            'free' => 'Free',
        );

        return $methods[$gateway] ?? ucfirst($gateway);
    }

    /**
     * Get usage statistics for a user
     */
    public function get_usage_stats($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $tier = $this->get_user_tier($user_id);
        $limits = $this->tiers[$tier]['limits'];

        // Get current usage from transient (resets daily)
        $usage_key = 'sffc_usage_' . $user_id . '_' . date('Y-m-d');
        $usage = get_transient($usage_key);

        if (!$usage) {
            $usage = array(
                'profile_views' => 0,
                'job_matches' => 0,
                'saved_articles' => $this->count_saved_articles($user_id),
                'salary_comparisons' => 0,
                'exports' => 0,
            );
        }

        // Calculate percentages
        $stats = array();
        foreach ($limits as $key => $limit) {
            $current = $usage[$key] ?? 0;
            $stats[$key] = array(
                'current' => $current,
                'limit' => $limit,
                'percentage' => $limit > 0 ? min(100, round(($current / $limit) * 100)) : 0,
                'remaining' => max(0, $limit - $current),
                'unlimited' => $limit >= 999,
            );
        }

        return $stats;
    }

    /**
     * Count saved articles for a user
     */
    private function count_saved_articles($user_id) {
        $saved = get_user_meta($user_id, 'sffc_saved_articles', true);
        return is_array($saved) ? count($saved) : 0;
    }

    /**
     * Track usage increment
     */
    public function track_usage($user_id, $type) {
        $usage_key = 'sffc_usage_' . $user_id . '_' . date('Y-m-d');
        $usage = get_transient($usage_key);

        if (!$usage) {
            $usage = array();
        }

        $usage[$type] = ($usage[$type] ?? 0) + 1;

        // Store for 24 hours
        set_transient($usage_key, $usage, DAY_IN_SECONDS);

        return $usage[$type];
    }

    /**
     * Check if user has access to a feature
     */
    public function has_feature_access($user_id, $feature, $value = null) {
        $tier = $this->get_user_tier($user_id);
        $tier_data = $this->tiers[$tier];

        if (!isset($tier_data['features'][$feature])) {
            return true; // Feature not gated
        }

        $feature_value = $tier_data['features'][$feature];

        // Boolean features
        if (is_bool($feature_value)) {
            return $feature_value;
        }

        // Numeric limits
        if (is_numeric($feature_value) && $value !== null) {
            return $value <= $feature_value;
        }

        return true;
    }

    /**
     * Check usage limit
     */
    public function check_limit($user_id, $limit_type) {
        $tier = $this->get_user_tier($user_id);
        $limits = $this->tiers[$tier]['limits'];

        if (!isset($limits[$limit_type])) {
            return true;
        }

        $limit = $limits[$limit_type];
        if ($limit >= 999) {
            return true; // Unlimited
        }

        $usage = $this->get_usage_stats($user_id);
        return ($usage[$limit_type]['current'] ?? 0) < $limit;
    }

    /**
     * Get next tier info
     */
    public function get_next_tier($current_tier) {
        $order = array('free', 'professional', 'executive');
        $current_index = array_search($current_tier, $order);

        if ($current_index === false || $current_index >= count($order) - 1) {
            return null;
        }

        $next_tier_key = $order[$current_index + 1];
        $next_tier = $this->tiers[$next_tier_key];

        return array(
            'key' => $next_tier_key,
            'name' => $next_tier['name'],
            'price' => $next_tier['price'],
            'currency' => $next_tier['currency'] ?? 'GBP',
        );
    }

    /**
     * Get upgrade prompt data for a feature
     */
    public function get_upgrade_prompt($feature, $current_tier = null) {
        if (!$current_tier) {
            $current_tier = $this->get_user_tier();
        }

        $next_tier = $this->get_next_tier($current_tier);
        if (!$next_tier) {
            return null;
        }

        $current_feature = $this->feature_descriptions[$feature][$current_tier] ?? '';
        $next_feature = $this->feature_descriptions[$feature][$next_tier['key']] ?? '';

        return array(
            'feature' => $feature,
            'current_value' => $current_feature,
            'upgrade_value' => $next_feature,
            'next_tier' => $next_tier,
            'upgrade_url' => $this->get_upgrade_url($next_tier['key']),
            'cta_text' => 'Upgrade to ' . $next_tier['name'],
        );
    }

    /**
     * Get upgrade URL
     */
    public function get_upgrade_url($tier = null) {
        $base_url = get_option('sffc_membership_page_url', '/membership/');

        if ($tier) {
            return add_query_arg('plan', $tier, $base_url);
        }

        return $base_url;
    }

    /**
     * Get plan comparison data
     */
    public function get_plan_comparison() {
        $comparison = array();

        $features_to_compare = array(
            'stats_refresh' => 'Stats Refresh Rate',
            'trends_range' => 'Trend History',
            'skills_limit' => 'Skills Analysis',
            'news_limit' => 'News Articles',
            'salary_locations' => 'Salary Locations',
            'export_enabled' => 'Export Reports',
            'ai_insights' => 'AI Insights',
        );

        foreach ($features_to_compare as $feature_key => $feature_label) {
            $comparison[] = array(
                'feature' => $feature_label,
                'free' => $this->feature_descriptions[$feature_key]['free'] ?? '-',
                'professional' => $this->feature_descriptions[$feature_key]['professional'] ?? '-',
                'executive' => $this->feature_descriptions[$feature_key]['executive'] ?? '-',
            );
        }

        return array(
            'features' => $comparison,
            'tiers' => array(
                'free' => array(
                    'name' => 'Free',
                    'price' => 0,
                    'period' => '',
                ),
                'professional' => array(
                    'name' => 'Professional',
                    'price' => 49.99,
                    'period' => '/month',
                    'currency' => 'GBP',
                ),
                'executive' => array(
                    'name' => 'Executive',
                    'price' => 69.99,
                    'period' => '/month',
                    'currency' => 'GBP',
                    'popular' => true,
                ),
            ),
        );
    }

    /**
     * Apply feature gating to data
     */
    public function apply_gating($data, $data_type, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $tier = $this->get_user_tier($user_id);
        $features = $this->tiers[$tier]['features'];

        switch ($data_type) {
            case 'trends':
                // Limit trends range
                $max_days = $features['trends_range'];
                if (isset($data['labels']) && is_array($data['labels'])) {
                    $data['labels'] = array_slice($data['labels'], -$max_days);
                    foreach ($data['datasets'] as &$dataset) {
                        $dataset['data'] = array_slice($dataset['data'], -$max_days);
                    }
                }
                $data['gated'] = $tier === 'free';
                $data['upgrade_prompt'] = $tier === 'free' ? $this->get_upgrade_prompt('trends_range', $tier) : null;
                break;

            case 'skills':
                // Limit number of skills
                $max_skills = $features['skills_limit'];
                if (isset($data['skills']) && is_array($data['skills'])) {
                    $data['total_skills'] = count($data['skills']);
                    $data['skills'] = array_slice($data['skills'], 0, $max_skills);
                    $data['limited'] = count($data['skills']) < $data['total_skills'];
                }
                $data['gated'] = $tier === 'free';
                $data['upgrade_prompt'] = $tier === 'free' ? $this->get_upgrade_prompt('skills_limit', $tier) : null;
                break;

            case 'news':
                // Limit news items
                $max_news = $features['news_limit'];
                if (isset($data['news']) && is_array($data['news'])) {
                    $data['total_news'] = count($data['news']);
                    $data['news'] = array_slice($data['news'], 0, $max_news);
                    $data['limited'] = count($data['news']) < $data['total_news'];
                }
                $data['gated'] = $tier === 'free';
                $data['upgrade_prompt'] = $tier === 'free' ? $this->get_upgrade_prompt('news_limit', $tier) : null;
                break;

            case 'salary':
                // Limit location comparisons
                $max_locations = $features['salary_locations'];
                $data['max_locations'] = $max_locations;
                $data['gated'] = $max_locations < 3;
                $data['upgrade_prompt'] = $max_locations < 3 ? $this->get_upgrade_prompt('salary_locations', $tier) : null;
                break;

            case 'export':
                $data['enabled'] = $features['export_enabled'];
                $data['gated'] = !$features['export_enabled'];
                $data['upgrade_prompt'] = !$features['export_enabled'] ? $this->get_upgrade_prompt('export_enabled', $tier) : null;
                break;

            case 'ai_insights':
                $data['enabled'] = $features['ai_insights'];
                $data['gated'] = !$features['ai_insights'];
                $data['upgrade_prompt'] = !$features['ai_insights'] ? $this->get_upgrade_prompt('ai_insights', $tier) : null;
                break;
        }

        return $data;
    }

    /**
     * Get billing history URL
     */
    public function get_billing_history_url() {
        $account_page = get_option('mepr_account_page_id');
        if ($account_page) {
            return get_permalink($account_page) . '?action=subscriptions';
        }
        return '/account/subscriptions';
    }

    /**
     * AJAX: Get membership info
     */
    public function ajax_get_membership_info() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $user_id = get_current_user_id();
        $data = $this->get_membership_data($user_id);
        $data['plan_comparison'] = $this->get_plan_comparison();
        $data['billing_url'] = $this->get_billing_history_url();
        $data['upgrade_url'] = $this->get_upgrade_url();

        wp_send_json_success($data);
    }

    /**
     * AJAX: Check feature access
     */
    public function ajax_check_feature_access() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $feature = isset($_POST['feature']) ? sanitize_text_field($_POST['feature']) : '';
        $value = isset($_POST['value']) ? intval($_POST['value']) : null;

        if (empty($feature)) {
            wp_send_json_error(array('message' => 'Feature not specified'));
        }

        $user_id = get_current_user_id();
        $has_access = $this->has_feature_access($user_id, $feature, $value);

        $response = array(
            'has_access' => $has_access,
            'feature' => $feature,
        );

        if (!$has_access) {
            $response['upgrade_prompt'] = $this->get_upgrade_prompt($feature);
        }

        wp_send_json_success($response);
    }

    /**
     * AJAX: Track usage
     */
    public function ajax_track_usage() {
        check_ajax_referer('sffc_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $type = isset($_POST['usage_type']) ? sanitize_text_field($_POST['usage_type']) : '';

        if (empty($type)) {
            wp_send_json_error(array('message' => 'Usage type not specified'));
        }

        $user_id = get_current_user_id();
        $new_count = $this->track_usage($user_id, $type);
        $within_limit = $this->check_limit($user_id, $type);

        wp_send_json_success(array(
            'count' => $new_count,
            'within_limit' => $within_limit,
        ));
    }
}

// Initialize
SFFC_Dashboard_Membership_Handler::get_instance();
