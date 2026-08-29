<?php
/**
 * MemberPress Integration
 * Bridges the gap between MemberPress subscriptions and user profiles
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_MemberPress_Integration {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Check if MemberPress is active
        if (!$this->is_memberpress_active()) {
            return;
        }
        
        // Hook into profile system
        add_action('init', [$this, 'init_integration']);
        
        // AJAX handlers for subscription management
        add_action('wp_ajax_sffc_get_subscription_status', [$this, 'ajax_get_subscription_status']);
        add_action('wp_ajax_sffc_get_subscription_details', [$this, 'ajax_get_subscription_details']);
        add_action('wp_ajax_sffc_get_payment_history', [$this, 'ajax_get_payment_history']);
        add_action('wp_ajax_sffc_cancel_subscription', [$this, 'ajax_cancel_subscription']);
        add_action('wp_ajax_sffc_update_payment_method', [$this, 'ajax_update_payment_method']);
        
        // Hook into MemberPress events
        add_action('mepr-event-subscription-created', [$this, 'on_subscription_created']);
        add_action('mepr-event-subscription-paused', [$this, 'on_subscription_paused']);
        add_action('mepr-event-subscription-resumed', [$this, 'on_subscription_resumed']);
        add_action('mepr-event-subscription-stopped', [$this, 'on_subscription_stopped']);
        add_action('mepr-event-subscription-expired', [$this, 'on_subscription_expired']);
        
        // Add premium features to job matching
        add_filter('sffc_job_match_limit', [$this, 'filter_job_match_limit'], 10, 2);
        add_filter('sffc_profile_features', [$this, 'filter_profile_features'], 10, 2);
    }
    
    /**
     * Check if MemberPress is active
     */
    public function is_memberpress_active() {
        return class_exists('MeprUser') && class_exists('MeprSubscription');
    }
    
    /**
     * Initialize integration
     */
    public function init_integration() {
        // Add subscription fields to profile completion
        add_filter('sffc_profile_completion_fields', [$this, 'add_subscription_fields']);
    }
    
    /**
     * Get user's active subscriptions
     */
    public function get_user_subscriptions($user_id) {
        if (!$this->is_memberpress_active()) {
            return [];
        }
        
        $mepr_user = new MeprUser($user_id);
        $subscriptions = $mepr_user->active_product_subscriptions('subscriptions', true);
        
        $formatted_subs = [];
        foreach ($subscriptions as $sub) {
            $product = new MeprProduct($sub->product_id);
            
            $formatted_subs[] = [
                'id' => $sub->id,
                'subscription_id' => $sub->subscr_id,
                'product_id' => $sub->product_id,
                'product_name' => $product->post_title,
                'status' => $sub->status,
                'price' => $sub->price,
                'total' => $sub->total,
                'period' => $sub->period,
                'period_type' => $sub->period_type,
                'created_at' => $sub->created_at,
                'expires_at' => $sub->expires_at,
                'trial' => $sub->trial,
                'trial_days' => $sub->trial_days,
                'limit_cycles' => $sub->limit_cycles,
                'gateway' => $sub->gateway,
                'cc_last4' => $sub->cc_last4,
                'cc_exp_month' => $sub->cc_exp_month,
                'cc_exp_year' => $sub->cc_exp_year
            ];
        }
        
        return $formatted_subs;
    }
    
    /**
     * Get user's payment history
     */
    public function get_payment_history($user_id, $limit = 10) {
        if (!$this->is_memberpress_active()) {
            return [];
        }
        
        global $wpdb;
        $mepr_db = MeprDb::fetch();
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$mepr_db->transactions} 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d",
            $user_id,
            $limit
        );
        
        $transactions = $wpdb->get_results($query);
        
        $formatted_transactions = [];
        foreach ($transactions as $txn) {
            $product = new MeprProduct($txn->product_id);
            
            $formatted_transactions[] = [
                'id' => $txn->id,
                'trans_num' => $txn->trans_num,
                'amount' => $txn->amount,
                'total' => $txn->total,
                'tax_amount' => $txn->tax_amount,
                'product_name' => $product->post_title,
                'status' => $txn->status,
                'gateway' => $txn->gateway,
                'created_at' => $txn->created_at,
                'invoice_url' => $this->get_invoice_url($txn->trans_num)
            ];
        }
        
        return $formatted_transactions;
    }
    
    /**
     * Check if user has premium access
     */
    public function has_premium_access($user_id, $level = 'any') {
        // Admin users always have premium access
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        if (!$this->is_memberpress_active()) {
            return false;
        }

        $mepr_user = new MeprUser($user_id);

        if ($level === 'any') {
            return $mepr_user->is_active();
        }

        // Check for specific membership level
        $active_products = $mepr_user->active_product_subscriptions('products');

        foreach ($active_products as $product) {
            if ($product->post_name === $level || $product->ID == $level) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has Insider Access (for recruiter introductions)
     *
     * Insider Access is a subscription tier that enables:
     * - Direct recruiter introductions
     * - Priority application review
     * - Higher response rates
     *
     * @param int $user_id User ID to check
     * @return bool True if user has Insider Access
     */
    public function has_insider_access($user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        // Admin users always have insider access
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        if (!$this->is_memberpress_active()) {
            return false;
        }

        $mepr_user = new MeprUser($user_id);
        $active_products = $mepr_user->active_product_subscriptions('products');

        // Check for Insider Access membership
        // This checks for products with "insider" in the slug or specific product IDs
        $insider_product_slugs = apply_filters('sffc_insider_access_products', [
            'insider-access',
            'insider',
            'premium-insider',
            'career-insider'
        ]);

        // Also allow specific product IDs to be set
        $insider_product_ids = apply_filters('sffc_insider_access_product_ids', []);

        foreach ($active_products as $product) {
            // Check by slug
            if (in_array($product->post_name, $insider_product_slugs, true)) {
                return true;
            }

            // Check by ID
            if (in_array($product->ID, $insider_product_ids, true)) {
                return true;
            }

            // Check by name containing "Insider"
            if (stripos($product->post_title, 'insider') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the signup URL for Insider Access
     *
     * @return string URL to the Insider Access signup page
     */
    public function get_insider_signup_url() {
        if (!$this->is_memberpress_active()) {
            return home_url('/pricing/');
        }

        // Try to find the Insider Access product
        $products = MeprCptModel::all('MeprProduct');

        $insider_product_slugs = apply_filters('sffc_insider_access_products', [
            'insider-access',
            'insider',
            'premium-insider',
            'career-insider'
        ]);

        foreach ($products as $product) {
            // Check by slug
            if (in_array($product->post_name, $insider_product_slugs, true)) {
                return $this->get_product_signup_url($product);
            }

            // Check by name containing "Insider"
            if (stripos($product->post_title, 'insider') !== false) {
                return $this->get_product_signup_url($product);
            }
        }

        // Fallback to first available product or pricing page
        if (!empty($products)) {
            return $this->get_product_signup_url($products[0]);
        }

        return home_url('/pricing/');
    }

    /**
     * Get signup URL for a MemberPress product
     * Handles different MemberPress versions
     *
     * @param object $product MemberPress product object
     * @return string Signup URL
     */
    private function get_product_signup_url($product) {
        // Try the url() method first (older versions)
        if (method_exists($product, 'url')) {
            return $product->url();
        }

        // Try signup_url() method (some versions)
        if (method_exists($product, 'signup_url')) {
            return $product->signup_url();
        }

        // Try get_permalink (standard WP)
        if (isset($product->ID)) {
            return get_permalink($product->ID);
        }

        // Fallback to pricing page
        return home_url('/pricing/');
    }

    /**
     * Get Insider Access data for JavaScript/frontend
     *
     * @param int $user_id User ID
     * @return array Insider access data
     */
    public function get_insider_data($user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        return [
            'has_access' => $this->has_insider_access($user_id),
            'signup_url' => $this->get_insider_signup_url(),
            'is_logged_in' => $user_id > 0
        ];
    }
    
    /**
     * Get available membership levels
     */
    public function get_available_memberships() {
        if (!$this->is_memberpress_active()) {
            return [];
        }
        
        $products = MeprCptModel::all('MeprProduct');
        $memberships = [];
        
        foreach ($products as $product) {
            $memberships[] = [
                'id' => $product->ID,
                'name' => $product->post_title,
                'description' => $product->post_excerpt,
                'price' => $product->price,
                'period' => $product->period,
                'period_type' => $product->period_type,
                'trial' => $product->trial,
                'trial_days' => $product->trial_days,
                'limit_cycles' => $product->limit_cycles,
                'access_url' => $product->url(),
                'register_url' => $product->signup_url()
            ];
        }
        
        return $memberships;
    }
    
    /**
     * Get subscription management URLs
     */
    public function get_subscription_urls($user_id) {
        $mepr_options = MeprOptions::fetch();
        
        return [
            'account_url' => $mepr_options->account_page_url(),
            'login_url' => $mepr_options->login_page_url(),
            'subscriptions_url' => $mepr_options->account_page_url('subscriptions'),
            'payments_url' => $mepr_options->account_page_url('payments'),
            'billing_url' => $mepr_options->account_page_url('billing')
        ];
    }
    
    /**
     * Get invoice URL for transaction
     */
    private function get_invoice_url($trans_num) {
        $mepr_options = MeprOptions::fetch();
        return $mepr_options->account_page_url("invoice?ti={$trans_num}");
    }
    
    /**
     * Update user profile with subscription status
     */
    public function update_user_subscription_status($user_id) {
        global $wpdb;
        
        $mepr_user = new MeprUser($user_id);
        $is_active = $mepr_user->is_active();
        $subscriptions = $this->get_user_subscriptions($user_id);
        
        // Determine membership level
        $membership_level = 'free';
        $subscription_expires = null;
        
        if (!empty($subscriptions)) {
            $highest_sub = $subscriptions[0]; // Assume first is highest
            $product = new MeprProduct($highest_sub['product_id']);
            
            // Map product to membership level
            if (stripos($product->post_title, 'premium') !== false) {
                $membership_level = 'premium';
            } elseif (stripos($product->post_title, 'pro') !== false) {
                $membership_level = 'pro';
            } elseif (stripos($product->post_title, 'basic') !== false) {
                $membership_level = 'basic';
            }
            
            $subscription_expires = $highest_sub['expires_at'];
        }
        
        // Update user profile
        $wpdb->update(
            $wpdb->prefix . 'sffc_user_profiles',
            [
                'membership_level' => $membership_level,
                'subscription_status' => $is_active ? 'active' : 'inactive',
                'subscription_expires' => $subscription_expires,
                'premium_features_enabled' => $is_active ? 1 : 0
            ],
            ['user_id' => $user_id],
            ['%s', '%s', '%s', '%d'],
            ['%d']
        );
        
        return true;
    }
    
    /**
     * Render subscription widget for profile
     */
    public function render_subscription_widget($user_id) {
        if (!$this->is_memberpress_active()) {
            return '';
        }
        
        $subscriptions = $this->get_user_subscriptions($user_id);
        $has_premium = $this->has_premium_access($user_id);
        
        ob_start();
        ?>
        <div class="sffc-subscription-widget">
            <h3>Membership Status</h3>
            
            <?php if ($has_premium && !empty($subscriptions)): ?>
                <?php foreach ($subscriptions as $sub): ?>
                    <div class="subscription-item">
                        <h4><?php echo esc_html($sub['product_name']); ?></h4>
                        <p class="status <?php echo esc_attr($sub['status']); ?>">
                            Status: <?php echo ucfirst($sub['status']); ?>
                        </p>
                        <?php if ($sub['expires_at']): ?>
                            <p>Expires: <?php echo date('M j, Y', strtotime($sub['expires_at'])); ?></p>
                        <?php endif; ?>
                        <p>Price: <?php echo MeprAppHelper::format_currency($sub['total']); ?> / <?php echo $sub['period'] . ' ' . $sub['period_type']; ?></p>
                    </div>
                <?php endforeach; ?>
                
                <div class="subscription-actions">
                    <a href="<?php echo $this->get_subscription_urls($user_id)['subscriptions_url']; ?>" class="button">
                        Manage Subscriptions
                    </a>
                    <a href="<?php echo $this->get_subscription_urls($user_id)['billing_url']; ?>" class="button">
                        Update Billing
                    </a>
                </div>
            <?php else: ?>
                <p>You currently have a free membership.</p>
                <div class="upgrade-prompt">
                    <h4>Unlock Premium Features:</h4>
                    <ul>
                        <li>✓ Unlimited mentorship sessions</li>
                        <li>✓ Advanced requirements analysis</li>
                        <li>✓ Priority support</li>
                        <li>✓ Career coaching tools</li>
                    </ul>
                    <a href="<?php echo $this->get_available_memberships()[0]['register_url'] ?? '#'; ?>" class="button button-primary">
                        Upgrade to Premium
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * MemberPress Event Handlers
     */
    public function on_subscription_created($event) {
        $subscription = $event->get_data();
        $this->update_user_subscription_status($subscription->user_id);
    }
    
    public function on_subscription_paused($event) {
        $subscription = $event->get_data();
        $this->update_user_subscription_status($subscription->user_id);
    }
    
    public function on_subscription_resumed($event) {
        $subscription = $event->get_data();
        $this->update_user_subscription_status($subscription->user_id);
    }
    
    public function on_subscription_stopped($event) {
        $subscription = $event->get_data();
        $this->update_user_subscription_status($subscription->user_id);
    }
    
    public function on_subscription_expired($event) {
        $subscription = $event->get_data();
        $this->update_user_subscription_status($subscription->user_id);
    }
    
    /**
     * Filter job match limit based on membership
     */
    public function filter_job_match_limit($limit, $user_id) {
        if ($this->has_premium_access($user_id, 'premium')) {
            return -1; // Unlimited
        } elseif ($this->has_premium_access($user_id, 'pro')) {
            return 100;
        } elseif ($this->has_premium_access($user_id, 'basic')) {
            return 50;
        }
        
        return 20; // Free tier
    }
    
    /**
     * Filter profile features based on membership
     */
    public function filter_profile_features($features, $user_id) {
        if ($this->has_premium_access($user_id)) {
            $features['advanced_matching'] = true;
            $features['requirements_analysis'] = true;
            $features['career_pathways'] = true;
            $features['priority_support'] = true;
            $features['export_data'] = true;
            $features['api_access'] = $this->has_premium_access($user_id, 'premium');
        }
        
        return $features;
    }
    
    /**
     * AJAX: Get subscription status
     */
    public function ajax_get_subscription_status() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not authenticated']);
        }
        
        $user_id = get_current_user_id();
        $has_premium = $this->has_premium_access($user_id);
        $subscriptions = $this->get_user_subscriptions($user_id);
        
        wp_send_json_success([
            'has_premium' => $has_premium,
            'subscriptions' => $subscriptions,
            'urls' => $this->get_subscription_urls($user_id)
        ]);
    }
    
    /**
     * AJAX: Get subscription details
     */
    public function ajax_get_subscription_details() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not authenticated']);
        }
        
        $user_id = get_current_user_id();
        $subscriptions = $this->get_user_subscriptions($user_id);
        $memberships = $this->get_available_memberships();
        
        wp_send_json_success([
            'current_subscriptions' => $subscriptions,
            'available_memberships' => $memberships
        ]);
    }
    
    /**
     * AJAX: Get payment history
     */
    public function ajax_get_payment_history() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not authenticated']);
        }
        
        $user_id = get_current_user_id();
        $limit = intval($_POST['limit'] ?? 10);
        
        $history = $this->get_payment_history($user_id, $limit);
        
        wp_send_json_success([
            'transactions' => $history
        ]);
    }
    
    /**
     * AJAX: Cancel subscription
     */
    public function ajax_cancel_subscription() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not authenticated']);
        }
        
        $user_id = get_current_user_id();
        $subscription_id = intval($_POST['subscription_id'] ?? 0);
        
        if (!$subscription_id) {
            wp_send_json_error(['message' => 'Invalid subscription ID']);
        }
        
        // Verify subscription belongs to user
        $subscription = new MeprSubscription($subscription_id);
        if ($subscription->user_id != $user_id) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        // Cancel the subscription
        try {
            $product = new MeprProduct($subscription->product_id);
            $product_name = $product->post_title ?: 'MENA Careers Membership';
            $subscription->cancel();
            $this->update_user_subscription_status($user_id);
            $this->send_subscription_cancellation_emails($user_id, $subscription, $product_name);
            
            wp_send_json_success([
                'message' => 'Your cancellation request has been received. We have emailed you a confirmation.'
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => 'Failed to cancel subscription: ' . $e->getMessage()
            ]);
        }
    }

    private function send_subscription_cancellation_emails($user_id, $subscription, $product_name) {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $sender = $this->get_email_sender_for_context('subscription_cancellation');
        $from_email = $sender['from_email'];
        $from_name = $sender['from_name'];
        $reply_to_email = $sender['reply_to_email'] ?: $from_email;
        $reply_to_name = $sender['reply_to_name'] ?: $from_name;
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: ' . $reply_to_name . ' <' . $reply_to_email . '>',
        ];

        $first_name = trim((string) $user->first_name);
        if ($first_name === '') {
            $name_parts = preg_split('/\s+/', trim((string) $user->display_name));
            $first_name = $name_parts[0] ?? 'there';
        }

        $subject = __('Sorry to see you go', 'senna-finance');
        $body = $this->build_subscription_cancellation_user_email($first_name, $product_name);
        wp_mail($user->user_email, $subject, $body, $headers);

        $admin_email = get_option('admin_email');
        if ($admin_email) {
            $admin_subject = sprintf(__('%s requested to cancel their subscription', 'senna-finance'), $user->display_name ?: $user->user_login);
            $admin_body = sprintf(
                '<p><strong>%s</strong> requested to cancel their subscription.</p><p><strong>User email:</strong> %s</p><p><strong>Plan:</strong> %s</p><p><strong>Subscription ID:</strong> %s</p><p><strong>Requested at:</strong> %s</p>',
                esc_html($user->display_name ?: $user->user_login),
                esc_html($user->user_email),
                esc_html($product_name),
                esc_html((string) ($subscription->id ?? '')),
                esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), current_time('timestamp')))
            );
            wp_mail($admin_email, $admin_subject, $admin_body, $headers);
        }
    }

    private function get_email_sender_for_context($context) {
        if (function_exists('sffc_crm_get_email_sender')) {
            $sender = sffc_crm_get_email_sender($context);
            if (!empty($sender['from_email'])) {
                return [
                    'from_name' => sanitize_text_field($sender['from_name'] ?? 'MENA Careers Support'),
                    'from_email' => sanitize_email($sender['from_email']),
                    'reply_to_name' => sanitize_text_field($sender['reply_to_name'] ?? ''),
                    'reply_to_email' => sanitize_email($sender['reply_to_email'] ?? ''),
                ];
            }
        }

        $senders = get_option('sffc_crm_email_senders', []);
        $contexts = get_option('sffc_crm_email_sender_contexts', []);
        $sender_key = is_array($contexts) ? sanitize_key($contexts[$context] ?? ($contexts['default'] ?? 'support')) : 'support';
        $sender = is_array($senders) && isset($senders[$sender_key]) ? $senders[$sender_key] : null;

        if (!$sender && is_array($senders)) {
            $sender = reset($senders);
        }

        $from_email = sanitize_email($sender['from_email'] ?? 'support.team@joinsenna.com');
        return [
            'from_name' => sanitize_text_field($sender['from_name'] ?? 'MENA Careers Support') ?: 'MENA Careers Support',
            'from_email' => $from_email ?: 'support.team@joinsenna.com',
            'reply_to_name' => sanitize_text_field($sender['reply_to_name'] ?? ''),
            'reply_to_email' => sanitize_email($sender['reply_to_email'] ?? ''),
        ];
    }

    private function build_subscription_cancellation_user_email($first_name, $product_name) {
        $first_name = esc_html($first_name ?: 'there');
        $product_name = esc_html($product_name ?: 'MENA Careers Membership');

        return '<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Inter,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Arial,sans-serif;color:#102024;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f6f8;padding:28px 14px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border:1px solid #e5eaee;border-radius:18px;overflow:hidden;">
                    <tr>
                        <td style="padding:28px 30px 18px;border-bottom:1px solid #edf1f4;">
                            <div style="font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#0d6955;">MENA Careers</div>
                            <h1 style="margin:12px 0 8px;font-size:28px;line-height:1.2;color:#0d353e;">Sorry to see you go</h1>
                            <p style="margin:0;color:#5f6f73;font-size:15px;line-height:1.65;">Hi ' . $first_name . ', your cancellation request for ' . $product_name . ' has been received.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px 30px;">
                            <p style="margin:0 0 16px;color:#27383c;font-size:15px;line-height:1.7;">Thank you for spending part of your career journey with MENA Careers. We know subscriptions have to earn their place, and we appreciate the trust you gave us while you were here.</p>
                            <p style="margin:0 0 16px;color:#27383c;font-size:15px;line-height:1.7;">Before you go, is there anything we can do to fix an issue you faced or make the product more useful for you? If something did not feel right, reply to this email and I will personally make sure it reaches the right person.</p>
                            <div style="margin:22px 0;padding:18px 20px;background:#f7fbf9;border:1px solid #dcefe7;border-radius:14px;">
                                <p style="margin:0;color:#0d353e;font-size:14px;line-height:1.65;">Your account remains yours, and you can return any time when MENA Careers is useful again.</p>
                            </div>
                            <p style="margin:0;color:#27383c;font-size:15px;line-height:1.7;">Warmly,<br><strong>Emily O</strong><br><span style="color:#66777b;">MENA Careers Support</span></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }
    
    /**
     * AJAX: Update payment method
     */
    public function ajax_update_payment_method() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not authenticated']);
        }
        
        // Redirect to MemberPress billing page
        $user_id = get_current_user_id();
        $urls = $this->get_subscription_urls($user_id);
        
        wp_send_json_success([
            'redirect_url' => $urls['billing_url']
        ]);
    }
}

// Initialize
SFFC_MemberPress_Integration::get_instance();
