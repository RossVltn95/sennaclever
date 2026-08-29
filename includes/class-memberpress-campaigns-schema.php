<?php
/**
 * MemberPress Campaigns Database Schema
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_MemberPress_Campaigns_Schema {
    
    /**
     * Create campaign tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Campaigns table
        $sql_campaigns = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mp_campaigns (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft',
            target_criteria longtext,
            offer_type varchar(50),
            offer_value decimal(10,2),
            offer_duration varchar(50),
            offer_expiry_days int(11) DEFAULT 30,
            email_sequence longtext,
            start_date datetime DEFAULT NULL,
            end_date datetime DEFAULT NULL,
            optimize_send_time tinyint(1) DEFAULT 1,
            created_by bigint(20) UNSIGNED,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY type (type),
            KEY start_date (start_date),
            KEY end_date (end_date)
        ) $charset_collate;";
        
        // Campaign users (legacy users and targets)
        $sql_campaign_users = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mp_campaign_users (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED,
            email varchar(255) NOT NULL,
            name varchar(255),
            original_tier varchar(50),
            original_price decimal(10,2),
            last_payment_date datetime,
            cancel_date datetime,
            total_spent decimal(10,2),
            is_legacy tinyint(1) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            contacted_at datetime DEFAULT NULL,
            last_email_sent int DEFAULT 0,
            converted_at datetime DEFAULT NULL,
            conversion_value decimal(10,2),
            unsubscribed tinyint(1) DEFAULT 0,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY user_id (user_id),
            KEY email (email),
            KEY status (status),
            KEY is_legacy (is_legacy),
            KEY converted_at (converted_at)
        ) $charset_collate;";
        
        // Email templates
        $sql_email_templates = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mp_email_templates (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type varchar(50),
            subject varchar(500),
            html_content longtext,
            text_content longtext,
            variables text,
            category varchar(50),
            is_default tinyint(1) DEFAULT 0,
            created_by bigint(20) UNSIGNED,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY category (category),
            KEY is_default (is_default)
        ) $charset_collate;";
        
        // Campaign emails (sent emails log)
        $sql_campaign_emails = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mp_campaign_emails (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) UNSIGNED NOT NULL,
            campaign_user_id bigint(20) UNSIGNED NOT NULL,
            template_id bigint(20) UNSIGNED,
            email_index int DEFAULT 1,
            subject varchar(500),
            status varchar(20) DEFAULT 'pending',
            sent_at datetime DEFAULT NULL,
            opened_at datetime DEFAULT NULL,
            clicked_at datetime DEFAULT NULL,
            open_count int DEFAULT 0,
            click_count int DEFAULT 0,
            bounced tinyint(1) DEFAULT 0,
            bounce_reason text,
            tracking_id varchar(100),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY campaign_user_id (campaign_user_id),
            KEY template_id (template_id),
            KEY status (status),
            KEY sent_at (sent_at),
            KEY tracking_id (tracking_id)
        ) $charset_collate;";
        
        // Campaign activity log
        $sql_campaign_activity = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mp_campaign_activity (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) UNSIGNED,
            campaign_name varchar(255),
            user_id bigint(20) UNSIGNED,
            user_email varchar(255),
            action varchar(50),
            details text,
            result varchar(20),
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Campaign conversions
        $sql_campaign_conversions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mp_campaign_conversions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) UNSIGNED NOT NULL,
            campaign_user_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED,
            subscription_id bigint(20),
            product_id bigint(20),
            conversion_type varchar(50),
            conversion_value decimal(10,2),
            original_price decimal(10,2),
            discounted_price decimal(10,2),
            discount_amount decimal(10,2),
            coupon_code varchar(100),
            converted_at datetime DEFAULT CURRENT_TIMESTAMP,
            lifetime_value decimal(10,2),
            notes text,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY campaign_user_id (campaign_user_id),
            KEY user_id (user_id),
            KEY converted_at (converted_at)
        ) $charset_collate;";
        
        // Campaign stats (aggregated statistics)
        $sql_campaign_stats = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mp_campaign_stats (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) UNSIGNED NOT NULL,
            date date NOT NULL,
            emails_sent int DEFAULT 0,
            emails_opened int DEFAULT 0,
            emails_clicked int DEFAULT 0,
            emails_bounced int DEFAULT 0,
            unsubscribes int DEFAULT 0,
            conversions int DEFAULT 0,
            revenue decimal(10,2) DEFAULT 0.00,
            new_subscribers int DEFAULT 0,
            reactivated_subscribers int DEFAULT 0,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY campaign_date (campaign_id, date),
            KEY date (date)
        ) $charset_collate;";
        
        // Execute table creation
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_campaigns);
        dbDelta($sql_campaign_users);
        dbDelta($sql_email_templates);
        dbDelta($sql_campaign_emails);
        dbDelta($sql_campaign_activity);
        dbDelta($sql_campaign_conversions);
        dbDelta($sql_campaign_stats);
        
        // Add indexes for performance
        self::add_indexes();
        
        // Insert default email templates
        self::insert_default_templates();
        
        // Update version
        update_option('mp_campaigns_db_version', '1.0.0');
    }
    
    /**
     * Add additional indexes
     */
    private static function add_indexes() {
        global $wpdb;
        
        // Add composite indexes for common queries
        $wpdb->query("ALTER TABLE {$wpdb->prefix}mp_campaign_users 
                     ADD INDEX idx_campaign_status (campaign_id, status)");
        
        $wpdb->query("ALTER TABLE {$wpdb->prefix}mp_campaign_emails 
                     ADD INDEX idx_campaign_sent (campaign_id, sent_at)");
        
        $wpdb->query("ALTER TABLE {$wpdb->prefix}mp_campaign_conversions 
                     ADD INDEX idx_campaign_conversion (campaign_id, converted_at)");
    }
    
    /**
     * Insert default email templates
     */
    private static function insert_default_templates() {
        global $wpdb;
        
        $templates = self::get_default_templates();
        
        foreach ($templates as $template) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}mp_email_templates WHERE name = %s",
                $template['name']
            ));
            
            if (!$existing) {
                $wpdb->insert(
                    "{$wpdb->prefix}mp_email_templates",
                    $template
                );
            }
        }
    }
    
    /**
     * Get default email templates
     */
    private static function get_default_templates() {
        return [
            [
                'name' => 'Win-Back Email 1 - We Miss You',
                'type' => 'winback',
                'subject' => '{{name}}, we\'ve missed you at {{site_name}}',
                'html_content' => self::get_winback_template_1(),
                'category' => 'winback',
                'is_default' => 1
            ],
            [
                'name' => 'Win-Back Email 2 - Special Offer',
                'type' => 'winback',
                'subject' => '{{name}}, here\'s an exclusive offer just for you',
                'html_content' => self::get_winback_template_2(),
                'category' => 'winback',
                'is_default' => 1
            ],
            [
                'name' => 'Win-Back Email 3 - Last Chance',
                'type' => 'winback',
                'subject' => 'Final hours: Your exclusive {{discount}}% discount expires soon',
                'html_content' => self::get_winback_template_3(),
                'category' => 'winback',
                'is_default' => 1
            ],
            [
                'name' => 'Legacy Pricing Announcement',
                'type' => 'legacy',
                'subject' => '{{name}}, your original pricing is back (limited time)',
                'html_content' => self::get_legacy_pricing_template(),
                'category' => 'legacy',
                'is_default' => 1
            ],
            [
                'name' => 'Re-engagement Email',
                'type' => 'reengagement',
                'subject' => 'See what\'s new since you left',
                'html_content' => self::get_reengagement_template(),
                'category' => 'reengagement',
                'is_default' => 1
            ]
        ];
    }
    
    /**
     * Win-back Template 1 - We Miss You
     */
    private static function get_winback_template_1() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600&family=Source+Sans+Pro:wght@300;400;500&display=swap");
        
        body { margin: 0; padding: 0; font-family: "Source Sans Pro", Arial, sans-serif; background-color: #FEFCF6; color: #2C2C2C; }
        .container { max-width: 600px; margin: 0 auto; background: #FEFCF6; }
        .header { background: linear-gradient(135deg, #2C2C2C 0%, #1A1A1A 100%); padding: 40px 30px; text-align: center; }
        .logo { font-family: "Playfair Display", serif; font-size: 32px; font-weight: 600; color: #D4AF37; margin: 0; }
        .content { background: white; padding: 50px 40px; }
        .greeting { font-size: 24px; font-family: "Playfair Display", serif; color: #2C2C2C; margin: 0 0 20px 0; }
        .message { font-size: 16px; line-height: 1.7; color: #2C2C2C; margin: 20px 0; }
        .highlight-box { background: linear-gradient(135deg, #FEFCF6 0%, #F8F6F0 100%); border-left: 4px solid #D4AF37; padding: 20px; margin: 30px 0; }
        .cta-button { display: inline-block; background: linear-gradient(135deg, #D4AF37 0%, #B8941F 100%); color: white; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-weight: 500; font-size: 16px; text-transform: uppercase; letter-spacing: 0.5px; }
        .footer { background: #2C2C2C; color: #FEFCF6; padding: 40px 30px; text-align: center; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="logo">{{site_name}}</h1>
        </div>
        <div class="content">
            <h2 class="greeting">Hi {{name}},</h2>
            
            <p class="message">
                We noticed you haven\'t been around lately, and honestly? We\'ve missed having you as part of our community.
            </p>
            
            <div class="highlight-box">
                <strong>Since you\'ve been away, we\'ve added:</strong>
                <ul>
                    <li>Enhanced AI-powered career matching</li>
                    <li>New premium job opportunities from top firms</li>
                    <li>Personalized salary negotiation tools</li>
                    <li>Expert career coaching sessions</li>
                </ul>
            </div>
            
            <p class="message">
                Your career journey is unique, and we\'d love to continue supporting you. That\'s why we\'re reaching out with something special...
            </p>
            
            <p class="message">
                <strong>As a valued former member, you qualify for exclusive benefits that aren\'t available to new subscribers.</strong>
            </p>
            
            <div style="text-align: center; margin: 40px 0;">
                <a href="{{comeback_url}}" class="cta-button">See What\'s New</a>
            </div>
            
            <p class="message" style="font-size: 14px; color: #8B7355;">
                P.S. Keep an eye on your inbox - we have an exclusive offer coming your way in the next few days that we think you\'ll love.
            </p>
        </div>
        <div class="footer">
            <p>© {{year}} {{site_name}}. All rights reserved.</p>
            <p><a href="{{unsubscribe_url}}" style="color: #8B7355;">Unsubscribe</a> | <a href="{{preferences_url}}" style="color: #8B7355;">Update Preferences</a></p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Win-back Template 2 - Special Offer
     */
    private static function get_winback_template_2() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600&family=Source+Sans+Pro:wght@300;400;500&display=swap");
        
        body { margin: 0; padding: 0; font-family: "Source Sans Pro", Arial, sans-serif; background-color: #FEFCF6; color: #2C2C2C; }
        .container { max-width: 600px; margin: 0 auto; background: #FEFCF6; }
        .header { background: linear-gradient(135deg, #D4AF37 0%, #B8941F 100%); padding: 50px 30px; text-align: center; }
        .logo { font-family: "Playfair Display", serif; font-size: 32px; font-weight: 600; color: white; margin: 0; }
        .tagline { color: white; font-size: 18px; margin: 10px 0 0 0; }
        .content { background: white; padding: 50px 40px; }
        .offer-box { background: linear-gradient(135deg, #2C2C2C 0%, #1A1A1A 100%); color: white; padding: 30px; border-radius: 12px; text-align: center; margin: 30px 0; }
        .discount-amount { font-size: 48px; font-weight: 600; color: #D4AF37; font-family: "Playfair Display", serif; }
        .offer-details { font-size: 18px; margin: 15px 0; }
        .timer { background: #D4AF37; color: #2C2C2C; padding: 15px; border-radius: 8px; font-weight: 600; margin: 20px 0; }
        .cta-button { display: inline-block; background: white; color: #2C2C2C; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-weight: 600; font-size: 16px; text-transform: uppercase; margin: 20px 0; }
        .testimonial { background: #F8F6F0; padding: 25px; border-radius: 8px; margin: 30px 0; font-style: italic; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="logo">Welcome Back Offer</h1>
            <p class="tagline">Exclusively for {{name}}</p>
        </div>
        <div class="content">
            <div class="offer-box">
                <div class="discount-amount">{{discount}}% OFF</div>
                <div class="offer-details">Your First {{offer_duration}} Back</div>
                <div class="timer">⏰ Expires in {{expiry_days}} days</div>
                <a href="{{offer_url}}" class="cta-button">Claim Your Discount</a>
            </div>
            
            <p style="font-size: 18px; text-align: center; margin: 30px 0;">
                <strong>This exclusive offer is only available to returning members like you.</strong>
            </p>
            
            <div class="testimonial">
                "I came back after 6 months and was amazed by the improvements. The new AI matching found me my dream role at Goldman Sachs!"
                <br><strong>- Sarah K., Premium Member</strong>
            </div>
            
            <h3 style="font-family: \'Playfair Display\', serif;">What you\'ll get:</h3>
            <ul style="line-height: 1.8;">
                <li>Instant access to all premium features</li>
                <li>{{discount}}% discount for {{offer_duration}}</li>
                <li>No commitment - cancel anytime</li>
                <li>Access to exclusive job opportunities</li>
                <li>Priority support from our career experts</li>
            </ul>
            
            <div style="background: #FFF4D4; padding: 20px; border-radius: 8px; margin: 30px 0;">
                <strong>🎯 Quick Fact:</strong> 73% of returning members land their target role within 90 days of reactivating their subscription.
            </div>
            
            <div style="text-align: center; margin: 40px 0;">
                <a href="{{offer_url}}" class="cta-button" style="background: linear-gradient(135deg, #D4AF37 0%, #B8941F 100%); color: white;">
                    Activate {{discount}}% Discount
                </a>
            </div>
        </div>
        <div class="footer" style="background: #2C2C2C; color: #FEFCF6; padding: 30px; text-align: center; font-size: 12px;">
            <p>This offer expires on {{expiry_date}}</p>
            <p><a href="{{unsubscribe_url}}" style="color: #8B7355;">Unsubscribe</a></p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Win-back Template 3 - Last Chance
     */
    private static function get_winback_template_3() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin: 0; padding: 0; font-family: Arial, sans-serif; background: #FEFCF6; }
        .urgent-header { background: #A02020; color: white; padding: 20px; text-align: center; font-size: 18px; font-weight: 600; }
        .container { max-width: 600px; margin: 0 auto; background: white; }
        .content { padding: 40px; }
        .countdown-box { background: #FFF4D4; border: 2px dashed #D4AF37; padding: 30px; text-align: center; margin: 30px 0; }
        .countdown-timer { font-size: 36px; font-weight: 600; color: #A02020; }
        .cta-urgent { display: inline-block; background: #A02020; color: white; padding: 18px 50px; text-decoration: none; font-size: 18px; font-weight: 600; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="urgent-header">⚡ FINAL NOTICE: Your Exclusive Offer Expires Tonight</div>
    <div class="container">
        <div class="content">
            <h2>{{name}}, this is your last chance.</h2>
            
            <div class="countdown-box">
                <div class="countdown-timer">{{hours_left}} HOURS LEFT</div>
                <p>Your {{discount}}% discount expires at midnight</p>
            </div>
            
            <p style="font-size: 18px;">
                <strong>After tonight, this offer disappears forever.</strong>
            </p>
            
            <p>We won\'t be extending this offer or sending another one. This truly is your final opportunity to come back at this special rate.</p>
            
            <div style="text-align: center; margin: 40px 0;">
                <a href="{{final_offer_url}}" class="cta-urgent">Claim My {{discount}}% Discount Now</a>
            </div>
            
            <p style="text-align: center; color: #8B7355; font-size: 14px;">
                No tricks, no extensions. When the timer hits zero, this page will show full price.
            </p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Legacy Pricing Template
     */
    private static function get_legacy_pricing_template() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600&family=Source+Sans+Pro:wght@300;400;500&display=swap");
        body { margin: 0; padding: 0; font-family: "Source Sans Pro", Arial, sans-serif; background: #FEFCF6; }
        .vip-banner { background: linear-gradient(135deg, #D4AF37 0%, #B8941F 100%); color: white; padding: 15px; text-align: center; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .container { max-width: 600px; margin: 0 auto; background: white; }
        .content { padding: 50px 40px; }
        .legacy-badge { display: inline-block; background: #D4AF37; color: white; padding: 8px 20px; border-radius: 20px; font-weight: 600; margin: 20px 0; }
        .price-comparison { display: flex; justify-content: space-around; margin: 40px 0; }
        .price-box { padding: 20px; text-align: center; }
        .old-price { text-decoration: line-through; color: #8B7355; font-size: 18px; }
        .your-price { font-size: 36px; font-weight: 600; color: #2C2C2C; }
        .savings { color: #1B5E3F; font-weight: 600; }
    </style>
</head>
<body>
    <div class="vip-banner">🏆 VIP LEGACY MEMBER EXCLUSIVE</div>
    <div class="container">
        <div class="content">
            <h2 style="font-family: \'Playfair Display\', serif; font-size: 32px;">{{name}}, your loyalty is being rewarded.</h2>
            
            <div class="legacy-badge">FOUNDING MEMBER PRICING</div>
            
            <p style="font-size: 18px;">As one of our earliest supporters, you\'ve earned something new members can\'t get:</p>
            
            <div class="price-comparison">
                <div class="price-box">
                    <div class="old-price">New Member Price</div>
                    <div style="font-size: 28px; color: #8B7355;">${{new_price}}/mo</div>
                </div>
                <div class="price-box" style="background: #FFF4D4; border: 2px solid #D4AF37;">
                    <div style="color: #D4AF37; font-weight: 600;">YOUR PRICE</div>
                    <div class="your-price">${{legacy_price}}/mo</div>
                    <div class="savings">Save ${{monthly_savings}}/month</div>
                </div>
            </div>
            
            <div style="background: linear-gradient(135deg, #FEFCF6 0%, #F8F6F0 100%); padding: 25px; border-left: 4px solid #D4AF37; margin: 30px 0;">
                <strong>This price is locked in forever.</strong> As long as you maintain your subscription, you\'ll never pay more than ${{legacy_price}}/month, even when prices increase for new members.
            </div>
            
            <p><strong>Why this matters:</strong> We\'re planning a 40% price increase next quarter for new members. But you\'ll be protected with your legacy rate.</p>
            
            <div style="text-align: center; margin: 40px 0;">
                <a href="{{legacy_signup_url}}" style="display: inline-block; background: linear-gradient(135deg, #D4AF37 0%, #B8941F 100%); color: white; padding: 18px 50px; text-decoration: none; font-size: 18px; font-weight: 600; border-radius: 8px;">
                    Lock In My Legacy Price
                </a>
                <p style="color: #A02020; font-weight: 600; margin: 15px 0;">⏰ Available for {{expiry_days}} days only</p>
            </div>
            
            <p style="font-size: 14px; color: #8B7355; text-align: center;">
                Once this window closes, you\'ll only have access to current market pricing.
            </p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Re-engagement Template
     */
    private static function get_reengagement_template() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; }
        .header { background: linear-gradient(135deg, #2C2C2C 0%, #1A1A1A 100%); padding: 40px; text-align: center; }
        .content { padding: 40px; }
        .feature-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 30px 0; }
        .feature { padding: 20px; background: #F8F6F0; border-radius: 8px; }
        .cta-button { display: inline-block; background: #D4AF37; color: white; padding: 15px 40px; text-decoration: none; border-radius: 8px; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="color: #D4AF37; margin: 0;">Welcome Back to {{site_name}}</h1>
        </div>
        <div class="content">
            <h2>See what\'s new since you\'ve been away</h2>
            
            <div class="feature-grid">
                <div class="feature">
                    <strong>🚀 AI Job Matching</strong>
                    <p>Our new AI finds perfect matches based on your unique profile</p>
                </div>
                <div class="feature">
                    <strong>💼 Elite Opportunities</strong>
                    <p>Exclusive positions from top-tier firms</p>
                </div>
                <div class="feature">
                    <strong>📊 Salary Intelligence</strong>
                    <p>Real-time compensation data and negotiation tools</p>
                </div>
                <div class="feature">
                    <strong>🎯 Career Coaching</strong>
                    <p>1-on-1 sessions with industry experts</p>
                </div>
            </div>
            
            <div style="text-align: center; margin: 40px 0;">
                <a href="{{explore_url}}" class="cta-button">Explore New Features</a>
            </div>
        </div>
    </div>
</body>
</html>';
    }
}