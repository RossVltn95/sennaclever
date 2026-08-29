<?php
/**
 * MemberPress Campaigns Manager
 * Core business logic for campaign management
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_MemberPress_Campaigns_Manager {
    
    private static $instance = null;
    private $email_sender;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize components
        // Email sender will be initialized when needed
        
        // Hook into MemberPress events for automated campaigns
        add_action('mepr-event-subscription-expired', [$this, 'handle_subscription_expired']);
        add_action('mepr-event-subscription-stopped', [$this, 'handle_subscription_cancelled']);
        add_action('mepr-event-subscription-paused', [$this, 'handle_subscription_paused']);
        
        // Scheduled tasks
        add_action('mp_campaigns_process_queue', [$this, 'process_email_queue']);
        add_action('mp_campaigns_update_stats', [$this, 'update_campaign_stats']);
        
        // Schedule cron jobs if not already scheduled
        if (!wp_next_scheduled('mp_campaigns_process_queue')) {
            wp_schedule_event(time(), 'every_5_minutes', 'mp_campaigns_process_queue');
        }
        
        if (!wp_next_scheduled('mp_campaigns_update_stats')) {
            wp_schedule_event(time(), 'hourly', 'mp_campaigns_update_stats');
        }
        
        // Add custom cron schedule
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['every_5_minutes'] = [
            'interval' => 300,
            'display' => 'Every 5 Minutes'
        ];
        return $schedules;
    }
    
    /**
     * Create a new campaign
     */
    public function create_campaign($data) {
        global $wpdb;
        
        $campaign_data = [
            'name' => sanitize_text_field($data['name']),
            'type' => sanitize_text_field($data['type']),
            'status' => sanitize_text_field($data['status'] ?? 'draft'),
            'target_criteria' => json_encode($data['target_criteria'] ?? []),
            'offer_type' => sanitize_text_field($data['offer_type'] ?? ''),
            'offer_value' => floatval($data['offer_value'] ?? 0),
            'offer_duration' => sanitize_text_field($data['offer_duration'] ?? ''),
            'offer_expiry_days' => intval($data['offer_expiry_days'] ?? 30),
            'email_sequence' => json_encode($data['email_sequence'] ?? []),
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'optimize_send_time' => intval($data['optimize_send_time'] ?? 1),
            'created_by' => get_current_user_id()
        ];
        
        $result = $wpdb->insert(
            "{$wpdb->prefix}mp_campaigns",
            $campaign_data
        );
        
        if ($result) {
            $campaign_id = $wpdb->insert_id;
            
            // Log activity
            $this->log_activity($campaign_id, null, 'campaign_created', 'Campaign created', 'success');
            
            // If campaign is active, start processing
            if ($data['status'] === 'active') {
                $this->activate_campaign($campaign_id);
            }
            
            return $campaign_id;
        }
        
        return false;
    }
    
    /**
     * Update campaign
     */
    public function update_campaign($campaign_id, $data) {
        global $wpdb;
        
        $update_data = [];
        
        // Only update provided fields
        $allowed_fields = ['name', 'type', 'status', 'target_criteria', 'offer_type', 
                          'offer_value', 'offer_duration', 'offer_expiry_days', 
                          'email_sequence', 'start_date', 'end_date', 'optimize_send_time'];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                if (in_array($field, ['target_criteria', 'email_sequence'])) {
                    $update_data[$field] = json_encode($data[$field]);
                } else {
                    $update_data[$field] = $data[$field];
                }
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            "{$wpdb->prefix}mp_campaigns",
            $update_data,
            ['id' => $campaign_id]
        );
        
        if ($result !== false) {
            $this->log_activity($campaign_id, null, 'campaign_updated', 'Campaign updated', 'success');
            return true;
        }
        
        return false;
    }
    
    /**
     * Activate a campaign
     */
    public function activate_campaign($campaign_id) {
        global $wpdb;
        
        // Update campaign status
        $wpdb->update(
            "{$wpdb->prefix}mp_campaigns",
            ['status' => 'active'],
            ['id' => $campaign_id]
        );
        
        // Identify target users
        $target_users = $this->identify_target_users($campaign_id);
        
        // Add users to campaign
        foreach ($target_users as $user) {
            $this->add_user_to_campaign($campaign_id, $user);
        }
        
        // Start sending emails
        $this->queue_campaign_emails($campaign_id);
        
        $this->log_activity($campaign_id, null, 'campaign_activated', 
                           'Campaign activated with ' . count($target_users) . ' users', 'success');
        
        return true;
    }
    
    /**
     * Identify target users for a campaign
     */
    private function identify_target_users($campaign_id) {
        global $wpdb;
        
        $campaign = $this->get_campaign($campaign_id);
        $criteria = json_decode($campaign->target_criteria, true);
        
        $users = [];
        
        // Get legacy users if targeted
        if (in_array('legacy_users', $criteria['segments'] ?? [])) {
            $legacy_users = $wpdb->get_results(
                "SELECT u.ID as user_id, u.user_email as email, u.display_name as name,
                        um1.meta_value as original_tier, um2.meta_value as original_price
                 FROM {$wpdb->users} u
                 LEFT JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = '_legacy_tier'
                 LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = '_legacy_price'
                 WHERE EXISTS (
                     SELECT 1 FROM {$wpdb->usermeta} 
                     WHERE user_id = u.ID AND meta_key = '_is_legacy_user' AND meta_value = '1'
                 )"
            );
            
            $users = array_merge($users, $legacy_users);
        }
        
        // Get expired subscriptions
        if (in_array('expired_subs', $criteria['segments'] ?? [])) {
            if (class_exists('MeprSubscription')) {
                $expired_users = $wpdb->get_results(
                    "SELECT DISTINCT u.ID as user_id, u.user_email as email, u.display_name as name
                     FROM {$wpdb->users} u
                     INNER JOIN {$wpdb->prefix}mepr_subscriptions s ON u.ID = s.user_id
                     WHERE s.status = 'expired' 
                     AND s.expires_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)"
                );
                
                $users = array_merge($users, $expired_users);
            }
        }
        
        // Get cancelled subscriptions
        if (in_array('cancelled_subs', $criteria['segments'] ?? [])) {
            if (class_exists('MeprSubscription')) {
                $cancelled_users = $wpdb->get_results(
                    "SELECT DISTINCT u.ID as user_id, u.user_email as email, u.display_name as name
                     FROM {$wpdb->users} u
                     INNER JOIN {$wpdb->prefix}mepr_subscriptions s ON u.ID = s.user_id
                     WHERE s.status IN ('cancelled', 'stopped')
                     AND s.created_at >= DATE_SUB(NOW(), INTERVAL 180 DAY)"
                );
                
                $users = array_merge($users, $cancelled_users);
            }
        }
        
        // Apply additional filters
        if (!empty($criteria['last_active_from']) || !empty($criteria['last_active_to'])) {
            $users = $this->filter_users_by_activity($users, $criteria);
        }
        
        // Remove duplicates
        $unique_users = [];
        $seen_emails = [];
        
        foreach ($users as $user) {
            if (!in_array($user->email, $seen_emails)) {
                $unique_users[] = $user;
                $seen_emails[] = $user->email;
            }
        }
        
        return $unique_users;
    }
    
    /**
     * Add user to campaign
     */
    private function add_user_to_campaign($campaign_id, $user) {
        global $wpdb;
        
        // Check if user already in campaign
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}mp_campaign_users 
             WHERE campaign_id = %d AND email = %s",
            $campaign_id, $user->email
        ));
        
        if ($existing) {
            return $existing;
        }
        
        // Get user's subscription history if available
        $subscription_data = $this->get_user_subscription_history($user->user_id);
        
        $user_data = [
            'campaign_id' => $campaign_id,
            'user_id' => $user->user_id ?? null,
            'email' => $user->email,
            'name' => $user->name ?? '',
            'original_tier' => $user->original_tier ?? $subscription_data['tier'] ?? '',
            'original_price' => $user->original_price ?? $subscription_data['price'] ?? 0,
            'last_payment_date' => $subscription_data['last_payment'] ?? null,
            'cancel_date' => $subscription_data['cancel_date'] ?? null,
            'total_spent' => $subscription_data['total_spent'] ?? 0,
            'is_legacy' => isset($user->is_legacy) ? 1 : 0,
            'status' => 'pending'
        ];
        
        $wpdb->insert(
            "{$wpdb->prefix}mp_campaign_users",
            $user_data
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get user subscription history
     */
    private function get_user_subscription_history($user_id) {
        if (!$user_id || !class_exists('MeprSubscription')) {
            return [];
        }
        
        global $wpdb;
        
        $history = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                MAX(s.price) as price,
                MAX(s.created_at) as last_payment,
                MAX(CASE WHEN s.status IN ('cancelled', 'stopped') THEN s.created_at END) as cancel_date,
                SUM(t.amount) as total_spent,
                p.post_title as tier
             FROM {$wpdb->prefix}mepr_subscriptions s
             LEFT JOIN {$wpdb->prefix}mepr_transactions t ON s.id = t.subscription_id
             LEFT JOIN {$wpdb->posts} p ON s.product_id = p.ID
             WHERE s.user_id = %d
             GROUP BY s.user_id",
            $user_id
        ));
        
        return [
            'price' => $history->price ?? 0,
            'last_payment' => $history->last_payment,
            'cancel_date' => $history->cancel_date,
            'total_spent' => $history->total_spent ?? 0,
            'tier' => $history->tier ?? ''
        ];
    }
    
    /**
     * Queue campaign emails
     */
    private function queue_campaign_emails($campaign_id) {
        global $wpdb;
        
        $campaign = $this->get_campaign($campaign_id);
        $email_sequence = json_decode($campaign->email_sequence, true);
        
        if (empty($email_sequence)) {
            return false;
        }
        
        // Get campaign users
        $users = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mp_campaign_users 
             WHERE campaign_id = %d AND status = 'pending'",
            $campaign_id
        ));
        
        foreach ($users as $user) {
            // Queue first email
            $this->queue_email($campaign_id, $user->id, 1);
        }
        
        return true;
    }
    
    /**
     * Queue a single email
     */
    private function queue_email($campaign_id, $campaign_user_id, $email_index) {
        global $wpdb;
        
        $campaign = $this->get_campaign($campaign_id);
        $email_sequence = json_decode($campaign->email_sequence, true);
        
        if (!isset($email_sequence[$email_index - 1])) {
            return false;
        }
        
        $email_config = $email_sequence[$email_index - 1];
        
        // Calculate send time
        $send_at = $this->calculate_send_time($email_config['delay'] ?? 0);
        
        $wpdb->insert(
            "{$wpdb->prefix}mp_campaign_emails",
            [
                'campaign_id' => $campaign_id,
                'campaign_user_id' => $campaign_user_id,
                'template_id' => $email_config['template_id'] ?? null,
                'email_index' => $email_index,
                'subject' => $email_config['subject'] ?? '',
                'status' => 'queued',
                'tracking_id' => $this->generate_tracking_id()
            ]
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Process email queue
     */
    public function process_email_queue() {
        global $wpdb;
        
        // Get queued emails ready to send
        $emails = $wpdb->get_results(
            "SELECT e.*, cu.email, cu.name, c.name as campaign_name, 
                    c.offer_type, c.offer_value, c.offer_duration, c.offer_expiry_days
             FROM {$wpdb->prefix}mp_campaign_emails e
             INNER JOIN {$wpdb->prefix}mp_campaign_users cu ON e.campaign_user_id = cu.id
             INNER JOIN {$wpdb->prefix}mp_campaigns c ON e.campaign_id = c.id
             WHERE e.status = 'queued' 
             AND (e.sent_at IS NULL OR e.sent_at <= NOW())
             LIMIT 50"
        );
        
        foreach ($emails as $email) {
            $this->send_campaign_email($email);
        }
    }
    
    /**
     * Send a campaign email
     */
    private function send_campaign_email($email_data) {
        global $wpdb;
        
        // Get template
        $template = $this->get_email_template($email_data->template_id);
        
        if (!$template) {
            return false;
        }
        
        // Prepare variables for replacement
        $variables = $this->prepare_email_variables($email_data);
        
        // Replace variables in template
        $subject = $this->replace_variables($template->subject, $variables);
        $html_content = $this->replace_variables($template->html_content, $variables);
        
        // Send email
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
            'X-Campaign-ID: ' . $email_data->campaign_id,
            'X-Tracking-ID: ' . $email_data->tracking_id
        ];
        
        $sent = wp_mail($email_data->email, $subject, $html_content, $headers);
        
        if ($sent) {
            // Update email status
            $wpdb->update(
                "{$wpdb->prefix}mp_campaign_emails",
                [
                    'status' => 'sent',
                    'sent_at' => current_time('mysql')
                ],
                ['id' => $email_data->id]
            );
            
            // Update campaign user status
            $wpdb->update(
                "{$wpdb->prefix}mp_campaign_users",
                [
                    'status' => 'contacted',
                    'contacted_at' => current_time('mysql'),
                    'last_email_sent' => $email_data->email_index
                ],
                ['id' => $email_data->campaign_user_id]
            );
            
            // Queue next email if exists
            $this->queue_next_email($email_data);
            
            // Log activity
            $this->log_activity($email_data->campaign_id, $email_data->campaign_user_id, 
                              'email_sent', 'Email ' . $email_data->email_index . ' sent', 'success');
        } else {
            // Update status to failed
            $wpdb->update(
                "{$wpdb->prefix}mp_campaign_emails",
                ['status' => 'failed'],
                ['id' => $email_data->id]
            );
            
            $this->log_activity($email_data->campaign_id, $email_data->campaign_user_id, 
                              'email_failed', 'Email ' . $email_data->email_index . ' failed', 'failed');
        }
        
        return $sent;
    }
    
    /**
     * Prepare email variables
     */
    private function prepare_email_variables($email_data) {
        $site_url = home_url();
        $expiry_date = date('F j, Y', strtotime('+' . $email_data->offer_expiry_days . ' days'));
        
        // Calculate hours left for urgency emails
        $hours_left = max(1, $email_data->offer_expiry_days * 24 - 
                          (time() - strtotime($email_data->contacted_at)) / 3600);
        
        // Generate offer URL with tracking
        $offer_url = add_query_arg([
            'campaign' => $email_data->campaign_id,
            'user' => $email_data->campaign_user_id,
            'tracking' => $email_data->tracking_id,
            'action' => 'redeem'
        ], $site_url . '/subscription/');
        
        return [
            '{{name}}' => $email_data->name ?: 'Valued Member',
            '{{site_name}}' => get_bloginfo('name'),
            '{{site_url}}' => $site_url,
            '{{discount}}' => $email_data->offer_value,
            '{{offer_duration}}' => $this->format_duration($email_data->offer_duration),
            '{{expiry_days}}' => $email_data->offer_expiry_days,
            '{{expiry_date}}' => $expiry_date,
            '{{hours_left}}' => round($hours_left),
            '{{offer_url}}' => $offer_url,
            '{{comeback_url}}' => $offer_url,
            '{{legacy_signup_url}}' => $offer_url,
            '{{final_offer_url}}' => $offer_url,
            '{{explore_url}}' => $site_url . '/features/',
            '{{unsubscribe_url}}' => $this->get_unsubscribe_url($email_data),
            '{{preferences_url}}' => $site_url . '/account/preferences/',
            '{{year}}' => date('Y'),
            '{{legacy_price}}' => $email_data->original_price ?: '29.99',
            '{{new_price}}' => '49.99',
            '{{monthly_savings}}' => '20'
        ];
    }
    
    /**
     * Replace variables in content
     */
    private function replace_variables($content, $variables) {
        foreach ($variables as $key => $value) {
            $content = str_replace($key, $value, $content);
        }
        return $content;
    }
    
    /**
     * Queue next email in sequence
     */
    private function queue_next_email($email_data) {
        $campaign = $this->get_campaign($email_data->campaign_id);
        $email_sequence = json_decode($campaign->email_sequence, true);
        
        $next_index = $email_data->email_index + 1;
        
        if (isset($email_sequence[$next_index - 1])) {
            $this->queue_email($email_data->campaign_id, $email_data->campaign_user_id, $next_index);
        }
    }
    
    /**
     * Handle subscription expired event
     */
    public function handle_subscription_expired($event) {
        // Find active win-back campaigns for expired subscriptions
        $campaigns = $this->get_active_campaigns_by_type('winback_expired');
        
        foreach ($campaigns as $campaign) {
            $user = get_userdata($event->user_id);
            if ($user) {
                $user_obj = (object)[
                    'user_id' => $user->ID,
                    'email' => $user->user_email,
                    'name' => $user->display_name
                ];
                
                $campaign_user_id = $this->add_user_to_campaign($campaign->id, $user_obj);
                if ($campaign_user_id) {
                    $this->queue_email($campaign->id, $campaign_user_id, 1);
                }
            }
        }
    }
    
    /**
     * Handle subscription paused event
     */
    public function handle_subscription_paused($event) {
        // Find active re-engagement campaigns
        $campaigns = $this->get_active_campaigns_by_type('reengagement');
        
        foreach ($campaigns as $campaign) {
            $user = get_userdata($event->user_id);
            if ($user) {
                $user_obj = (object)[
                    'user_id' => $user->ID,
                    'email' => $user->user_email,
                    'name' => $user->display_name
                ];
                
                $campaign_user_id = $this->add_user_to_campaign($campaign->id, $user_obj);
                if ($campaign_user_id) {
                    $this->queue_email($campaign->id, $campaign_user_id, 1);
                }
            }
        }
    }
    
    /**
     * Filter users by activity dates
     */
    private function filter_users_by_activity($users, $criteria) {
        if (empty($users)) {
            return $users;
        }
        
        $filtered = [];
        
        foreach ($users as $user) {
            $include = true;
            
            // Check last active from
            if (!empty($criteria['last_active_from'])) {
                $last_active = get_user_meta($user->user_id, 'last_activity', true);
                if ($last_active && strtotime($last_active) < strtotime($criteria['last_active_from'])) {
                    $include = false;
                }
            }
            
            // Check last active to
            if (!empty($criteria['last_active_to'])) {
                $last_active = get_user_meta($user->user_id, 'last_activity', true);
                if ($last_active && strtotime($last_active) > strtotime($criteria['last_active_to'])) {
                    $include = false;
                }
            }
            
            if ($include) {
                $filtered[] = $user;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Handle subscription cancelled event
     */
    public function handle_subscription_cancelled($event) {
        // Find active win-back campaigns for cancelled subscriptions
        $campaigns = $this->get_active_campaigns_by_type('winback_cancelled');
        
        foreach ($campaigns as $campaign) {
            $user = get_userdata($event->user_id);
            if ($user) {
                $user_obj = (object)[
                    'user_id' => $user->ID,
                    'email' => $user->user_email,
                    'name' => $user->display_name
                ];
                
                $campaign_user_id = $this->add_user_to_campaign($campaign->id, $user_obj);
                if ($campaign_user_id) {
                    $this->queue_email($campaign->id, $campaign_user_id, 1);
                }
            }
        }
    }
    
    /**
     * Import legacy users
     */
    public function import_legacy_users($users_data, $default_settings = []) {
        global $wpdb;
        
        $imported = 0;
        
        foreach ($users_data as $user_data) {
            $email = sanitize_email($user_data['email']);
            
            if (!is_email($email)) {
                continue;
            }
            
            // Check if user exists
            $user = get_user_by('email', $email);
            
            if ($user) {
                // Mark existing user as legacy
                update_user_meta($user->ID, '_is_legacy_user', '1');
                update_user_meta($user->ID, '_legacy_tier', 
                               $user_data['tier'] ?? $default_settings['tier'] ?? 'basic');
                update_user_meta($user->ID, '_legacy_price', 
                               $user_data['price'] ?? $default_settings['price'] ?? '29.99');
            } else {
                // Create user account if doesn't exist
                $username = strstr($email, '@', true);
                $username = sanitize_user($username);
                
                // Ensure unique username
                if (username_exists($username)) {
                    $username .= '_' . wp_rand(100, 999);
                }
                
                $user_id = wp_create_user($username, wp_generate_password(), $email);
                
                if (!is_wp_error($user_id)) {
                    // Mark as legacy user
                    update_user_meta($user_id, '_is_legacy_user', '1');
                    update_user_meta($user_id, '_legacy_tier', 
                                   $user_data['tier'] ?? $default_settings['tier'] ?? 'basic');
                    update_user_meta($user_id, '_legacy_price', 
                                   $user_data['price'] ?? $default_settings['price'] ?? '29.99');
                    
                    // Update user display name
                    if (!empty($user_data['name'])) {
                        wp_update_user([
                            'ID' => $user_id,
                            'display_name' => $user_data['name']
                        ]);
                    }
                }
            }
            
            $imported++;
        }
        
        return $imported;
    }
    
    /**
     * Helper methods
     */
    
    private function get_campaign($campaign_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mp_campaigns WHERE id = %d",
            $campaign_id
        ));
    }
    
    private function get_email_template($template_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mp_email_templates WHERE id = %d",
            $template_id
        ));
    }
    
    private function get_active_campaigns_by_type($type) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mp_campaigns 
             WHERE type = %s AND status = 'active'",
            $type
        ));
    }
    
    private function calculate_send_time($delay) {
        // Convert delay to timestamp
        if ($delay === 0 || $delay === 'immediately') {
            return current_time('mysql');
        }
        
        $delay_seconds = $delay * 86400; // Convert days to seconds
        return date('Y-m-d H:i:s', time() + $delay_seconds);
    }
    
    private function generate_tracking_id() {
        return wp_generate_uuid4();
    }
    
    private function format_duration($duration) {
        $durations = [
            '1_month' => '1 month',
            '3_months' => '3 months',
            '6_months' => '6 months',
            '1_year' => '1 year',
            'lifetime' => 'lifetime'
        ];
        
        return $durations[$duration] ?? $duration;
    }
    
    private function get_unsubscribe_url($email_data) {
        return add_query_arg([
            'action' => 'unsubscribe',
            'campaign' => $email_data->campaign_id,
            'user' => $email_data->campaign_user_id,
            'tracking' => $email_data->tracking_id
        ], home_url('/unsubscribe/'));
    }
    
    private function log_activity($campaign_id, $user_id, $action, $details, $result) {
        global $wpdb;
        
        $campaign = $this->get_campaign($campaign_id);
        $user = $user_id ? get_userdata($user_id) : null;
        
        $wpdb->insert(
            "{$wpdb->prefix}mp_campaign_activity",
            [
                'campaign_id' => $campaign_id,
                'campaign_name' => $campaign->name ?? '',
                'user_id' => $user_id,
                'user_email' => $user->user_email ?? '',
                'action' => $action,
                'details' => $details,
                'result' => $result,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]
        );
    }
    
    /**
     * Update campaign statistics
     */
    public function update_campaign_stats() {
        global $wpdb;
        
        // Get active campaigns
        $campaigns = $wpdb->get_results(
            "SELECT id FROM {$wpdb->prefix}mp_campaigns WHERE status = 'active'"
        );
        
        foreach ($campaigns as $campaign) {
            $this->update_campaign_statistics($campaign->id);
        }
    }
    
    private function update_campaign_statistics($campaign_id) {
        global $wpdb;
        
        $today = date('Y-m-d');
        
        // Get today's stats
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT CASE WHEN e.sent_at >= %s THEN e.id END) as emails_sent,
                COUNT(DISTINCT CASE WHEN e.opened_at >= %s THEN e.id END) as emails_opened,
                COUNT(DISTINCT CASE WHEN e.clicked_at >= %s THEN e.id END) as emails_clicked,
                COUNT(DISTINCT CASE WHEN e.bounced = 1 AND e.sent_at >= %s THEN e.id END) as emails_bounced,
                COUNT(DISTINCT CASE WHEN cu.unsubscribed = 1 AND cu.updated_at >= %s THEN cu.id END) as unsubscribes,
                COUNT(DISTINCT CASE WHEN cv.converted_at >= %s THEN cv.id END) as conversions,
                SUM(CASE WHEN cv.converted_at >= %s THEN cv.conversion_value ELSE 0 END) as revenue
             FROM {$wpdb->prefix}mp_campaign_emails e
             LEFT JOIN {$wpdb->prefix}mp_campaign_users cu ON e.campaign_user_id = cu.id
             LEFT JOIN {$wpdb->prefix}mp_campaign_conversions cv ON cu.id = cv.campaign_user_id
             WHERE e.campaign_id = %d",
            $today . ' 00:00:00', $today . ' 00:00:00', $today . ' 00:00:00',
            $today . ' 00:00:00', $today . ' 00:00:00', $today . ' 00:00:00',
            $today . ' 00:00:00', $campaign_id
        ));
        
        // Insert or update stats
        $wpdb->replace(
            "{$wpdb->prefix}mp_campaign_stats",
            [
                'campaign_id' => $campaign_id,
                'date' => $today,
                'emails_sent' => $stats->emails_sent ?? 0,
                'emails_opened' => $stats->emails_opened ?? 0,
                'emails_clicked' => $stats->emails_clicked ?? 0,
                'emails_bounced' => $stats->emails_bounced ?? 0,
                'unsubscribes' => $stats->unsubscribes ?? 0,
                'conversions' => $stats->conversions ?? 0,
                'revenue' => $stats->revenue ?? 0
            ]
        );
    }
}

// Don't initialize here - let the init class handle it