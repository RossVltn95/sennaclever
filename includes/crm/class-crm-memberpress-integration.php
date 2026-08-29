<?php
/**
 * CRM MemberPress Integration
 * Feature gating based on membership levels
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_MemberPress_Integration {

    private static $instance = null;
    private $mepr_integration;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Get the main MemberPress integration
        if (class_exists('SFFC_MemberPress_Integration')) {
            $this->mepr_integration = SFFC_MemberPress_Integration::get_instance();
        }

        // Register CRM-specific filters
        add_filter('sffc_crm_outreach_limit', [$this, 'filter_outreach_limit'], 10, 2);
        add_filter('sffc_crm_sequence_limit', [$this, 'filter_sequence_limit'], 10, 2);
        add_filter('sffc_crm_recruiter_limit', [$this, 'filter_recruiter_limit'], 10, 2);
        add_filter('sffc_crm_posts_limit', [$this, 'filter_posts_limit'], 10, 2);
        add_filter('sffc_crm_features', [$this, 'filter_crm_features'], 10, 2);
        add_filter('sffc_crm_expert_outreach_limit', [$this, 'filter_expert_outreach_limit'], 10, 2);
        add_filter('sffc_crm_outreach_list_limit', [$this, 'filter_outreach_list_limit'], 10, 2);
        add_filter('sffc_crm_list_recruiter_limit', [$this, 'filter_list_recruiter_limit'], 10, 2);
        add_filter('sffc_crm_bulk_outreach_limit', [$this, 'filter_bulk_outreach_limit'], 10, 2);
    }

    /**
     * Get CRM tier based on membership
     *
     * Checks multiple sources to verify membership:
     * 1. Admin capability
     * 2. MemberPress active subscriptions
     * 3. Stripe customer ID in user meta (backup verification)
     */
    public function get_crm_tier($user_id) {
        if (!$user_id) {
            return 'free';
        }

        // Admin users get insider tier (unrestricted access)
        if (user_can($user_id, 'manage_options')) {
            return 'insider';
        }

        // Check if user has active Stripe subscription (backup check)
        $has_stripe_subscription = $this->has_stripe_subscription($user_id);

        if (!$this->mepr_integration) {
            // If MemberPress not active but user has Stripe subscription
            // Give them pro access as fallback
            return $has_stripe_subscription ? 'pro' : 'free';
        }

        // Check Insider Access first (highest tier)
        if ($this->mepr_integration->has_insider_access($user_id)) {
            return 'insider';
        }

        // Check Pro tier
        if ($this->mepr_integration->has_premium_access($user_id, 'pro')) {
            return 'pro';
        }

        // Check Basic tier
        if ($this->mepr_integration->has_premium_access($user_id, 'basic')) {
            return 'basic';
        }

        // Check any premium access
        if ($this->mepr_integration->has_premium_access($user_id)) {
            return 'pro'; // Default to pro if has some access
        }

        // Final fallback: If Stripe subscription exists but MemberPress check failed
        // This handles edge cases where MemberPress might be out of sync
        if ($has_stripe_subscription) {
            error_log("SFFC CRM: User {$user_id} has Stripe subscription but no MemberPress access. Granting Pro tier.");
            return 'pro';
        }

        return 'free';
    }

    /**
     * Check if user has active Stripe subscription
     * Looks for _mepr_stripe_customer_id_* in user meta
     *
     * @param int $user_id
     * @return bool
     */
    private function has_stripe_subscription($user_id) {
        global $wpdb;

        // Check for any Stripe customer ID meta keys
        $stripe_meta = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_key
             FROM {$wpdb->usermeta}
             WHERE user_id = %d
             AND meta_key LIKE %s
             LIMIT 1",
            $user_id,
            $wpdb->esc_like('_mepr_stripe_customer_id_') . '%'
        ));

        return !empty($stripe_meta);
    }

    /**
     * Filter outreach message limits
     *
     * Free: 0/month (browse only, no messaging)
     * Basic: 10/month
     * Pro: 100/month
     * Insider: Unlimited (-1)
     */
    public function filter_outreach_limit($limit, $user_id) {
        $tier = $this->get_crm_tier($user_id);

        return match($tier) {
            'insider' => -1,      // Unlimited
            'pro'     => 100,
            'basic'   => 10,
            default   => 0        // No outreach for free users
        };
    }

    /**
     * Filter active sequence limits
     *
     * Free: 0 (no sequences)
     * Basic: 0
     * Pro: 3
     * Insider: Unlimited (-1)
     */
    public function filter_sequence_limit($limit, $user_id) {
        $tier = $this->get_crm_tier($user_id);

        return match($tier) {
            'insider' => -1,      // Unlimited
            'pro'     => 3,
            default   => 0        // No sequences for free/basic
        };
    }

    /**
     * Filter saved recruiter limits
     *
     * Free: 10 (reduced from 20)
     * Basic: 50
     * Pro: Unlimited
     * Insider: Unlimited
     */
    public function filter_recruiter_limit($limit, $user_id) {
        $tier = $this->get_crm_tier($user_id);

        return match($tier) {
            'insider' => -1,      // Unlimited
            'pro'     => -1,      // Unlimited
            'basic'   => 50,
            default   => 10       // Reduced for free tier
        };
    }

    /**
     * Filter posts per day limit (feed access)
     *
     * Free: 50/day
     * Basic: 50/day
     * Pro: Unlimited
     * Insider: Unlimited
     */
    public function filter_posts_limit($limit, $user_id) {
        $tier = $this->get_crm_tier($user_id);

        return match($tier) {
            'insider' => -1,
            'pro'     => -1,
            default   => 50
        };
    }

    /**
     * Filter expert outreach request limits (per month)
     * This is the premium service where MENA Careers team reaches out on user's behalf
     *
     * Free: 0 (no access)
     * Basic: 2/month
     * Pro: 10/month
     * Insider: Unlimited (-1)
     *
     * Limits can be customized via admin settings
     */
    public function filter_expert_outreach_limit($limit, $user_id) {
        $tier = $this->get_crm_tier($user_id);

        // Check for custom limits in settings
        $custom_limits = get_option('sffc_crm_expert_outreach_limits', []);

        if (!empty($custom_limits[$tier])) {
            $custom_limit = intval($custom_limits[$tier]);
            return $custom_limit === -1 ? -1 : $custom_limit;
        }

        // Default limits
        return match($tier) {
            'insider' => -1,      // Unlimited
            'pro'     => 10,
            'basic'   => 2,
            default   => 0        // No access for free users
        };
    }

    /**
     * Get expert outreach usage for the current month
     */
    public function get_expert_outreach_usage($user_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_crm_expert_outreach';
        $month_start = date('Y-m-01 00:00:00');

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE user_id = %d
             AND created_at >= %s
             AND status != 'cancelled'",
            $user_id,
            $month_start
        ));

        return (int) $count;
    }

    /**
     * Check if user can submit expert outreach request
     */
    public function can_submit_expert_outreach($user_id) {
        $limit = apply_filters('sffc_crm_expert_outreach_limit', 0, $user_id);

        // Unlimited access
        if ($limit === -1) {
            return [
                'allowed' => true,
                'limit' => -1,
                'used' => $this->get_expert_outreach_usage($user_id),
                'remaining' => -1
            ];
        }

        // No access
        if ($limit === 0) {
            return [
                'allowed' => false,
                'limit' => 0,
                'used' => 0,
                'remaining' => 0,
                'reason' => 'no_access',
                'message' => __('Expert Outreach is not available on your current plan. Please upgrade to access this feature.', 'senna-finance')
            ];
        }

        $used = $this->get_expert_outreach_usage($user_id);
        $remaining = max(0, $limit - $used);

        if ($remaining <= 0) {
            return [
                'allowed' => false,
                'limit' => $limit,
                'used' => $used,
                'remaining' => 0,
                'reason' => 'limit_reached',
                'message' => sprintf(
                    __('You have used all %d expert outreach requests for this month. Your limit resets on the 1st of next month.', 'senna-finance'),
                    $limit
                )
            ];
        }

        return [
            'allowed' => true,
            'limit' => $limit,
            'used' => $used,
            'remaining' => $remaining
        ];
    }

    /**
     * Filter outreach list limits (how many lists a user can create)
     *
     * Free: 1 list
     * Basic: 3 lists
     * Pro: 10 lists
     * Insider: Unlimited (-1)
     */
    public function filter_outreach_list_limit($limit, $user_id) {
        $tier = $this->get_crm_tier($user_id);

        return match($tier) {
            'insider' => -1,      // Unlimited
            'pro'     => 10,
            'basic'   => 3,
            default   => 1        // Free tier: 1 list only
        };
    }

    /**
     * Filter recruiters per list limit (how many recruiters in one list)
     *
     * Free: 3 recruiters max per list
     * Basic: 10 recruiters max per list
     * Pro: 50 recruiters max per list
     * Insider: Unlimited (-1)
     */
    public function filter_list_recruiter_limit($limit, $user_id) {
        $tier = $this->get_crm_tier($user_id);

        return match($tier) {
            'insider' => -1,      // Unlimited
            'pro'     => 50,
            'basic'   => 10,
            default   => 3        // Free tier: 3 recruiters max
        };
    }

    /**
     * Filter bulk outreach limits (how many recruiters can be messaged at once)
     *
     * Free: 0 (no bulk outreach)
     * Basic: 3 at once
     * Pro: 6 at once
     * Insider: 20 at once
     */
    public function filter_bulk_outreach_limit($limit, $user_id) {
        $tier = $this->get_crm_tier($user_id);

        return match($tier) {
            'insider' => 20,      // Up to 20 at once
            'pro'     => 6,       // 3-6 range (frontend enforces min 3)
            'basic'   => 3,       // Exactly 3
            default   => 0        // No bulk for free tier
        };
    }

    /**
     * Get all CRM features for user
     */
    public function filter_crm_features($features, $user_id) {
        $tier = $this->get_crm_tier($user_id);

        return array_merge($features, [
            // AI features
            'ai_personalization'    => in_array($tier, ['pro', 'insider']),
            'ai_message_generation' => in_array($tier, ['pro', 'insider']),

            // Outreach features
            'email_sending'         => in_array($tier, ['pro', 'insider']),
            'email_tracking'        => in_array($tier, ['pro', 'insider']),
            'sequences'             => in_array($tier, ['pro', 'insider']),

            // Sync features
            'email_sync'            => $tier === 'insider',
            'gmail_integration'     => $tier === 'insider',
            'outlook_integration'   => $tier === 'insider',

            // Intelligence features
            'recruiter_intelligence'=> $tier === 'insider',
            'response_analytics'    => $tier === 'insider',
            'best_time_analysis'    => $tier === 'insider',

            // Advanced features
            'advanced_analytics'    => $tier === 'insider',
            'export'                => $tier === 'insider',
            'bulk_actions'          => in_array($tier, ['pro', 'insider']),

            // Pipeline features
            'full_pipeline'         => in_array($tier, ['pro', 'insider']),

            // Support
            'priority_support'      => $tier === 'insider',
        ]);
    }

    /**
     * Check if user can perform action (with usage tracking)
     */
    public function can_perform_action($user_id, $action) {
        // Admin users can perform all actions
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        $tier = $this->get_crm_tier($user_id);
        $features = $this->filter_crm_features([], $user_id);

        switch ($action) {
            case 'send_outreach':
                $limit = $this->filter_outreach_limit(0, $user_id);
                if ($limit === -1) return true;

                $usage = $this->get_monthly_usage($user_id, 'outreach');
                return $usage < $limit;

            case 'create_sequence':
                $limit = $this->filter_sequence_limit(0, $user_id);
                if ($limit === -1) return true;
                if ($limit === 0) return false;

                $active = $this->get_active_sequences_count($user_id);
                return $active < $limit;

            case 'save_recruiter':
                $limit = $this->filter_recruiter_limit(0, $user_id);
                if ($limit === -1) return true;

                $saved = $this->get_saved_recruiters_count($user_id);
                return $saved < $limit;

            case 'use_ai':
                return !empty($features['ai_personalization']);

            case 'send_email':
                return !empty($features['email_sending']);

            case 'use_sequences':
                return !empty($features['sequences']);

            case 'sync_email':
                return !empty($features['email_sync']);

            case 'export':
                return !empty($features['export']);

            default:
                return true;
        }
    }

    /**
     * Get monthly usage count
     */
    public function get_monthly_usage($user_id, $type) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_crm_usage';
        $month = date('Y-m');

        $column = $type . '_count';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT {$column} FROM {$table} WHERE user_id = %d AND period_month = %s",
            $user_id,
            $month
        ));

        return (int) $count;
    }

    /**
     * Increment usage count
     */
    public function increment_usage($user_id, $type) {
        global $wpdb;

        $table = $wpdb->prefix . 'sffc_crm_usage';
        $month = date('Y-m');
        $column = $type . '_count';

        // Try to update first
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET {$column} = {$column} + 1, updated_at = NOW()
             WHERE user_id = %d AND period_month = %s",
            $user_id,
            $month
        ));

        // If no row updated, insert new
        if ($updated === 0) {
            $wpdb->insert($table, [
                'user_id' => $user_id,
                'period_month' => $month,
                $column => 1,
            ]);
        }
    }

    /**
     * Get active sequences count
     */
    private function get_active_sequences_count($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_crm_sequences';

        // Table doesn't exist yet in Phase 1
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return 0;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_active = 1",
            $user_id
        ));
    }

    /**
     * Get outreach lists count for user
     */
    public function get_outreach_lists_count($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_crm_outreach_lists';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            // Fallback to user meta (old method)
            $lists = get_user_meta($user_id, 'sffc_outreach_lists', true);
            return is_array($lists) ? count($lists) : 0;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
            $user_id
        ));
    }

    /**
     * Check if user can create another outreach list
     */
    public function can_create_outreach_list($user_id) {
        $limit = apply_filters('sffc_crm_outreach_list_limit', 1, $user_id);

        // Unlimited
        if ($limit === -1) {
            return [
                'allowed' => true,
                'limit' => -1,
                'current' => $this->get_outreach_lists_count($user_id),
                'remaining' => -1
            ];
        }

        $current = $this->get_outreach_lists_count($user_id);
        $remaining = max(0, $limit - $current);

        if ($remaining <= 0) {
            return [
                'allowed' => false,
                'limit' => $limit,
                'current' => $current,
                'remaining' => 0,
                'reason' => 'limit_reached',
                'message' => sprintf(
                    __('You have reached your outreach list limit (%d lists). Upgrade to create more lists.', 'senna-finance'),
                    $limit
                )
            ];
        }

        return [
            'allowed' => true,
            'limit' => $limit,
            'current' => $current,
            'remaining' => $remaining
        ];
    }

    /**
     * Get saved recruiters count
     */
    private function get_saved_recruiters_count($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sffc_crm_saved_recruiters';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
            $user_id
        ));
    }

    /**
     * Get upgrade prompt data
     */
    public function get_upgrade_prompt($user_id, $feature) {
        $tier = $this->get_crm_tier($user_id);

        $prompts = [
            'outreach_limit' => [
                'title' => __('Outreach Limit Reached', 'senna-finance'),
                'message' => __('You\'ve used all your outreach messages this month.', 'senna-finance'),
                'upgrade_to' => 'pro',
                'benefits' => [
                    __('100 outreach messages/month', 'senna-finance'),
                    __('AI-powered personalization', 'senna-finance'),
                    __('Email sending (not just copy)', 'senna-finance'),
                    __('3 active sequences', 'senna-finance'),
                ],
            ],
            'recruiter_limit' => [
                'title' => __('Recruiter Limit Reached', 'senna-finance'),
                'message' => __('You\'ve saved the maximum number of recruiters.', 'senna-finance'),
                'upgrade_to' => 'pro',
                'benefits' => [
                    __('Unlimited saved recruiters', 'senna-finance'),
                    __('Full CRM features', 'senna-finance'),
                ],
            ],
            'ai_required' => [
                'title' => __('AI Features', 'senna-finance'),
                'message' => __('AI-powered message generation requires a Pro subscription.', 'senna-finance'),
                'upgrade_to' => 'pro',
                'benefits' => [
                    __('AI-generated personalized messages', 'senna-finance'),
                    __('Smart variable substitution', 'senna-finance'),
                    __('Tone and style matching', 'senna-finance'),
                ],
            ],
            'sequences_required' => [
                'title' => __('Sequences Feature', 'senna-finance'),
                'message' => __('Automated sequences require a Pro subscription.', 'senna-finance'),
                'upgrade_to' => 'pro',
                'benefits' => [
                    __('Multi-step outreach campaigns', 'senna-finance'),
                    __('Automated follow-ups', 'senna-finance'),
                    __('Auto-pause on reply', 'senna-finance'),
                ],
            ],
            'expert_outreach_no_access' => [
                'title' => __('Expert Outreach', 'senna-finance'),
                'message' => __('Expert Outreach requires a Basic, Pro, or Insider subscription.', 'senna-finance'),
                'upgrade_to' => 'basic',
                'benefits' => [
                    __('Our team reaches out to recruiters on your behalf', 'senna-finance'),
                    __('Professionally crafted personalized messages', 'senna-finance'),
                    __('Higher response rates', 'senna-finance'),
                    __('2 expert outreach requests per month', 'senna-finance'),
                ],
            ],
            'expert_outreach_limit' => [
                'title' => __('Expert Outreach Limit Reached', 'senna-finance'),
                'message' => __('You\'ve used all your expert outreach requests this month.', 'senna-finance'),
                'upgrade_to' => 'pro',
                'benefits' => [
                    __('10 expert outreach requests per month', 'senna-finance'),
                    __('AI-powered personalization', 'senna-finance'),
                    __('Priority processing', 'senna-finance'),
                ],
            ],
            'email_sync_required' => [
                'title' => __('Email Sync', 'senna-finance'),
                'message' => __('Gmail/Outlook sync requires Insider Access.', 'senna-finance'),
                'upgrade_to' => 'insider',
                'benefits' => [
                    __('Sync sent and received emails', 'senna-finance'),
                    __('Unified inbox', 'senna-finance'),
                    __('Automatic recruiter matching', 'senna-finance'),
                    __('Full conversation history', 'senna-finance'),
                ],
            ],
            'outreach_list_limit' => [
                'title' => __('Outreach List Limit Reached', 'senna-finance'),
                'message' => __('You\'ve created the maximum number of outreach lists for your plan.', 'senna-finance'),
                'upgrade_to' => 'pro',
                'benefits' => [
                    __('Create up to 10 outreach lists', 'senna-finance'),
                    __('Up to 50 recruiters per list', 'senna-finance'),
                    __('Bulk outreach (3-6 recruiters at once)', 'senna-finance'),
                    __('AI-powered message generation', 'senna-finance'),
                ],
            ],
            'bulk_outreach_required' => [
                'title' => __('Bulk Outreach', 'senna-finance'),
                'message' => __('Bulk outreach requires a paid subscription.', 'senna-finance'),
                'upgrade_to' => 'basic',
                'benefits' => [
                    __('Message 3 recruiters at once (Basic)', 'senna-finance'),
                    __('Message 6 recruiters at once (Pro)', 'senna-finance'),
                    __('Message 20 recruiters at once (Insider)', 'senna-finance'),
                    __('Save time with batch operations', 'senna-finance'),
                ],
            ],
            'outreach_no_access' => [
                'title' => __('Upgrade to Send Messages', 'senna-finance'),
                'message' => __('Free users can browse and save recruiters. Upgrade to send outreach messages.', 'senna-finance'),
                'upgrade_to' => 'basic',
                'benefits' => [
                    __('Send up to 10 outreach messages/month (Basic)', 'senna-finance'),
                    __('100 messages/month + AI generation (Pro)', 'senna-finance'),
                    __('Unlimited messages (Insider)', 'senna-finance'),
                    __('Track responses and analytics', 'senna-finance'),
                ],
            ],
        ];

        $prompt = $prompts[$feature] ?? $prompts['ai_required'];

        // Add upgrade URL
        if ($this->mepr_integration) {
            if ($prompt['upgrade_to'] === 'insider') {
                $prompt['upgrade_url'] = $this->mepr_integration->get_insider_signup_url();
            } else {
                // Try to get Pro signup URL
                $prompt['upgrade_url'] = $this->get_pro_signup_url();
            }
        } else {
            $prompt['upgrade_url'] = 'https://joinsenna.com/memberships/';
        }

        return $prompt;
    }

    /**
     * Get Pro signup URL
     */
    private function get_pro_signup_url() {
        if (!$this->mepr_integration || !method_exists($this->mepr_integration, 'get_available_memberships')) {
            return 'https://joinsenna.com/memberships/';
        }

        $memberships = $this->mepr_integration->get_available_memberships();

        foreach ($memberships as $membership) {
            if (stripos($membership['name'], 'pro') !== false) {
                return $membership['register_url'] ?? 'https://joinsenna.com/memberships/';
            }
        }

        return 'https://joinsenna.com/memberships/';
    }

    /**
     * Get upgrade URL for a specific tier
     */
    public function get_upgrade_url($tier = 'pro') {
        if (!$this->mepr_integration) {
            return 'https://joinsenna.com/memberships/';
        }

        $memberships = $this->mepr_integration->get_available_memberships();

        foreach ($memberships as $membership) {
            $name = strtolower($membership['name'] ?? '');
            if (strpos($name, $tier) !== false) {
                return $membership['register_url'] ?? 'https://joinsenna.com/memberships/';
            }
        }

        // Fallback to memberships page
        return 'https://joinsenna.com/memberships/';
    }

    /**
     * Get user's subscription info for display
     */
    public function get_subscription_display($user_id) {
        $tier = $this->get_crm_tier($user_id);

        $display = [
            'tier' => $tier,
            'tier_label' => ucfirst($tier),
            'limits' => [
                'outreach' => [
                    'limit' => $this->filter_outreach_limit(0, $user_id),
                    'used' => $this->get_monthly_usage($user_id, 'outreach'),
                ],
                'recruiters' => [
                    'limit' => $this->filter_recruiter_limit(0, $user_id),
                    'used' => $this->get_saved_recruiters_count($user_id),
                ],
                'sequences' => [
                    'limit' => $this->filter_sequence_limit(0, $user_id),
                    'used' => $this->get_active_sequences_count($user_id),
                ],
                'expert_outreach' => [
                    'limit' => $this->filter_expert_outreach_limit(0, $user_id),
                    'used' => $this->get_expert_outreach_usage($user_id),
                ],
            ],
            'features' => $this->filter_crm_features([], $user_id),
        ];

        // Format limits for display
        foreach ($display['limits'] as $key => &$limit) {
            if ($limit['limit'] === -1) {
                $limit['display'] = __('Unlimited', 'senna-finance');
                $limit['percentage'] = 0;
            } else {
                $limit['display'] = $limit['used'] . ' / ' . $limit['limit'];
                $limit['percentage'] = $limit['limit'] > 0 ? round(($limit['used'] / $limit['limit']) * 100) : 0;
            }
        }

        return $display;
    }
}

// Initialize
SFFC_CRM_MemberPress_Integration::get_instance();
