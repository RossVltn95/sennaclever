<?php

/**
 * Claude Insights Generator
 * Generates ongoing career insights using Claude API after the email sequence ends
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Claude_Insights_Generator
{

    private static $instance = null;
    private $claude_api_key;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $options = get_option('sffc_matcher_options');
        $this->claude_api_key = $options['claude_api_key'] ?? '';

        // Check if Emily's ongoing insights are enabled (disabled by default after career advice integration)
        $ongoing_insights_enabled = get_option('sffc_emily_ongoing_insights_enabled', false);

        if ($ongoing_insights_enabled) {
            add_action('sffc_send_claude_insight', [$this, 'send_claude_insight']);
            add_action('init', [$this, 'schedule_recurring_insights']);
        }
    }

    /**
     * Check if ongoing insights are enabled
     */
    public static function is_enabled()
    {
        return (bool) get_option('sffc_emily_ongoing_insights_enabled', false);
    }

    /**
     * Enable or disable ongoing insights
     */
    public static function set_enabled($enabled)
    {
        update_option('sffc_emily_ongoing_insights_enabled', (bool) $enabled);
    }

    /**
     * Schedule recurring insights for users who completed the sequence
     */
    public function schedule_recurring_insights()
    {
        // Only run this check once per day
        $last_check = get_option('sffc_last_claude_insights_check');
        if ($last_check && (time() - $last_check) < DAY_IN_SECONDS) {
            return;
        }

        update_option('sffc_last_claude_insights_check', time());

        // Find users who should receive ongoing insights
        $users_for_insights = $this->get_users_for_ongoing_insights();

        foreach ($users_for_insights as $user_id) {
            $this->schedule_next_insight($user_id);
        }
    }

    /**
     * Get users who should receive ongoing insights
     */
    private function get_users_for_ongoing_insights()
    {
        global $wpdb;

        // Get users who:
        // 1. Completed the 10-email sequence
        // 2. Have ongoing insights enabled
        // 3. Haven't received an insight in the last 3 days

        $messages_table = $wpdb->prefix . 'senna_messages';
        $emily_id = 28134;

        $users = $wpdb->get_col("
            SELECT um1.user_id
            FROM {$wpdb->usermeta} um1
            INNER JOIN {$wpdb->usermeta} um2 ON um1.user_id = um2.user_id
            LEFT JOIN $messages_table m ON um1.user_id = m.recipient_id 
                AND m.sender_id = $emily_id 
                AND m.category = 'claude_insights'
                AND m.created_at > DATE_SUB(NOW(), INTERVAL 3 DAY)
            WHERE um1.meta_key = 'sffc_ongoing_insights_enabled'
            AND um2.meta_key = 'sffc_sequence_email_10_sent'
            AND um2.meta_value IS NOT NULL
            AND m.id IS NULL
            LIMIT 50
        ");

        return array_map('intval', $users);
    }

    /**
     * Schedule next insight for a user
     */
    private function schedule_next_insight($user_id)
    {
        // Schedule insight for 3 days from now
        wp_schedule_single_event(
            time() + (3 * DAY_IN_SECONDS),
            'sffc_send_claude_insight',
            [$user_id]
        );
    }

    /**
     * Send Claude-generated insight to user
     */
    public function send_claude_insight($user_id)
    {
        if (!$this->claude_api_key) {
            error_log("Claude API key not configured for insights");
            return;
        }

        $user = get_userdata($user_id);
        if (!$user) return;

        // Check if user still wants ongoing insights
        $ongoing_enabled = get_user_meta($user_id, 'sffc_ongoing_insights_enabled', true);
        if (!$ongoing_enabled) return;

        try {
            $insight_content = $this->generate_insight_with_claude($user);

            if ($insight_content) {
                $messaging = SkillFarm_Messaging_Messages::get_instance();

                $result = $messaging->send_message([
                    'sender_id' => 28134, // Emily Bradshaw
                    'recipient_id' => $user_id,
                    'subject' => $insight_content['subject'],
                    'content' => $insight_content['content'],
                    'category' => 'claude_insights',
                    'priority' => 'normal'
                ]);

                if (!is_wp_error($result)) {
                    error_log("Claude insight sent to user {$user_id}");

                    // Schedule next insight
                    $this->schedule_next_insight($user_id);
                }
            }
        } catch (Exception $e) {
            error_log("Error sending Claude insight: " . $e->getMessage());
        }
    }

    /**
     * Generate insight using Claude API
     */
    private function generate_insight_with_claude($user)
    {
        $first_name = $user->first_name ?: $user->display_name;

        // Get current market data and trends
        $market_context = $this->get_current_market_context();

        $prompt = $this->build_claude_prompt($first_name, $market_context);

        $response = $this->call_claude_api($prompt);

        if ($response && isset($response['content'])) {
            return $this->parse_claude_response($response['content']);
        }

        return null;
    }

    /**
     * Get current market context for Claude
     */
    private function get_current_market_context()
    {
        return [
            'date' => date('Y-m-d'),
            'market_trends' => [
                'rising_interest_rates',
                'inflation_concerns',
                'esg_integration',
                'digital_transformation',
                'supply_chain_disruption'
            ],
            'hot_sectors' => [
                'healthcare_technology',
                'fintech_infrastructure',
                'energy_transition',
                'defense_technology',
                'ai_and_automation'
            ],
            'recent_deals' => 'major_pe_and_hf_transactions'
        ];
    }

    /**
     * Build prompt for Claude API
     */
    private function build_claude_prompt($first_name, $market_context)
    {
        return "You are Emily Bradshaw, a Career Success Manager at senna, sending career insights to {$first_name}. 

Based on current market conditions in " . date('F Y') . ", create a personalized career insight email for an experienced professional targeting private equity, growth equity, private credit, portfolio operations, or adjacent buy-side roles.

Market Context:
- Current trends: " . implode(', ', $market_context['market_trends']) . "
- Hot sectors: " . implode(', ', $market_context['hot_sectors']) . "
- Focus on actionable career advice, industry insights, market positioning, recruiter access, and networking strategies

Requirements:
1. Professional but warm tone (like Emily's previous emails)
2. Include specific, actionable advice
3. Reference current market conditions and opportunities
4. Provide 2-3 concrete next steps
5. Keep it concise but valuable (500-800 words)
6. Include a compelling subject line
7. Assume the reader already has meaningful finance experience and wants sharper positioning, stronger market visibility, or a more senior move
8. Prioritise advice on stakeholder credibility, leadership signals, deal or capital allocation relevance, commercial ownership, team-building, mandate scope, or buy-side market fit
9. Do not use graduate, internship, campus, or first-job language

Format your response as:
SUBJECT: [email subject line]

CONTENT: [email content with HTML formatting]

Topics to potentially cover (choose 1-2):
- Current market opportunities across private equity and the broader buy-side
- Networking strategies for the current environment
- Skills, judgement, and leadership signals that are in high demand right now
- Industry trends affecting senior career paths
- Success stories and lessons from recent market activity
- Practical career advancement tactics for established finance professionals

Make it feel personal and valuable, as if Emily is sharing insider knowledge with a trusted contact.";
    }

    /**
     * Call Claude API
     */
    private function call_claude_api($prompt)
    {
        $url = 'https://api.anthropic.com/v1/messages';

        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->claude_api_key,
            'anthropic-version: 2023-06-01'
        ];

        $data = [
            'model' => 'claude-3-sonnet-20240229',
            'max_tokens' => 2000,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            return json_decode($response, true);
        } else {
            error_log("Claude API error: HTTP {$http_code} - {$response}");
            return null;
        }
    }

    /**
     * Parse Claude response into subject and content
     */
    private function parse_claude_response($content)
    {
        // Extract subject and content from Claude's response
        if (preg_match('/SUBJECT:\s*(.+)/i', $content, $subject_matches)) {
            $subject = trim($subject_matches[1]);
        } else {
            $subject = 'Career Insights from Emily';
        }

        if (preg_match('/CONTENT:\s*(.+)/is', $content, $content_matches)) {
            $email_content = trim($content_matches[1]);
        } else {
            $email_content = $content;
        }

        // Add Emily's signature if not present
        if (strpos($email_content, 'Emily Bradshaw') === false) {
            $email_content .= "
            
            <p>Best regards,<br>
            <strong>Emily Bradshaw</strong><br>
            <em>Career Success Manager</em><br>
            senna</p>";
        }

        return [
            'subject' => $subject,
            'content' => $email_content
        ];
    }

    /**
     * Fallback insight if Claude API fails
     */
    private function get_fallback_insight($first_name)
    {
        $insights = [
            [
                'subject' => 'Private Equity Update: What\'s Working Now',
                'content' => $this->generate_fallback_content_1($first_name)
            ],
            [
                'subject' => 'Referral Strategy: Building Your Industry Presence',
                'content' => $this->generate_fallback_content_2($first_name)
            ],
            [
                'subject' => 'Market Trends: Opportunities in Uncertain Times',
                'content' => $this->generate_fallback_content_3($first_name)
            ]
        ];

        // Rotate through insights
        $user_insight_count = get_user_meta(get_current_user_id(), 'sffc_fallback_insight_count', true) ?: 0;
        $insight_index = $user_insight_count % count($insights);
        update_user_meta(get_current_user_id(), 'sffc_fallback_insight_count', $user_insight_count + 1);

        return $insights[$insight_index];
    }

    /**
     * Generate fallback content 1
     */
    private function generate_fallback_content_1($first_name)
    {
        return "
        <p>Hello {$first_name},</p>
        
        <p>The private equity market continues to evolve rapidly. Here are three key developments I'm tracking that could impact your career:</p>
        
        <p><strong>1. Hiring is broadening across the buy-side</strong><br>
        Funds, private capital platforms, portfolio teams, and adjacent advisory businesses are hiring across investing, value creation, capital formation, strategy, and finance support functions.</p>
        
        <p><strong>2. Technology-finance convergence is accelerating</strong><br>
        Firms are investing heavily in data analytics, AI, automation, and reporting infrastructure. Leaders who can combine finance judgement with execution oversight and technology fluency are becoming increasingly valuable.</p>
        
        <p><strong>3. Positioning quality matters more</strong><br>
        Professionals who can explain investment judgment, deal relevance, portfolio value, and why they fit a specific fund strategy are standing out faster.</p>
        
        <p><strong>Your Action Steps:</strong></p>
        <ul>
            <li>Identify one emerging trend that strengthens your current mandate or target move</li>
            <li>Attend a relevant industry conference or senior finance roundtable this month</li>
            <li>Reconnect with 3 professionals working in these growth areas</li>
        </ul>
        
        <p>Best regards,<br>
        <strong>Emily Bradshaw</strong><br>
        <em>Career Success Manager</em><br>
        senna</p>
        ";
    }

    /**
     * Generate fallback content 2
     */
    private function generate_fallback_content_2($first_name)
    {
        return "
        <p>Hello {$first_name},</p>
        
        <p>Successful networking in private equity requires strategic thinking. Here's what I'm seeing work best in the current environment:</p>
        
        <p><strong>Quality Over Quantity</strong><br>
        Focus on building 3-5 meaningful relationships per quarter rather than collecting business cards. Deep connections generate real opportunities.</p>
        
        <p><strong>Value-First Approach</strong><br>
        Before asking for anything, provide value: market insights, introductions, or industry intelligence. This approach builds long-term relationship equity.</p>
        
        <p><strong>Digital Presence Matters</strong><br>
        Share thoughtful insights on LinkedIn, participate in industry discussions, and build your reputation as someone trusted with senior mandates.</p>
        
        <p><strong>This Week's Challenge:</strong></p>
        <p>Identify one person in your network who could benefit from an introduction to someone else you know. Make that introduction with a personalized note explaining the strategic or commercial value on both sides.</p>
        
        <p>Best regards,<br>
        <strong>Emily Bradshaw</strong><br>
        <em>Career Success Manager</em><br>
        senna</p>
        ";
    }

    /**
     * Generate fallback content 3
     */
    private function generate_fallback_content_3($first_name)
    {
        return "
        <p>Hello {$first_name},</p>
        
        <p>Market uncertainty creates the biggest opportunities for prepared professionals. Here's how to position yourself:</p>
        
        <p><strong>Distressed Debt Opportunity</strong><br>
        Rising rates and economic pressure are creating distressed situations. Firms need professionals who understand restructuring and turnaround strategies.</p>
        
        <p><strong>Energy Transition Capital</strong><br>
        \$10 trillion investment opportunity in clean energy and climate tech. Understanding both traditional energy and emerging technologies is highly valuable.</p>
        
        <p><strong>Healthcare Innovation</strong><br>
        AI in drug discovery, digital health platforms, and healthcare services consolidation are driving massive investment flows.</p>
        
        <p><strong>Your Opportunity Matrix:</strong></p>
        <ul>
            <li>Which trend aligns with your existing expertise?</li>
            <li>Where can you develop unique knowledge quickly?</li>
            <li>Who in your network operates in these spaces?</li>
        </ul>
        
        <p>Position yourself at the intersection of market trends and investment flows.</p>
        
        <p>Best regards,<br>
        <strong>Emily Bradshaw</strong><br>
        <em>Career Success Manager</em><br>
        senna</p>
        ";
    }

    /**
     * Admin function to test Claude API
     */
    public function test_claude_api()
    {
        if (!current_user_can('manage_options')) {
            return false;
        }

        $test_prompt = "Generate a brief career insight about private equity hiring trends. Keep it to 2 paragraphs.";
        $response = $this->call_claude_api($test_prompt);

        return $response;
    }
}

// Initialize
SFFC_Claude_Insights_Generator::get_instance();
