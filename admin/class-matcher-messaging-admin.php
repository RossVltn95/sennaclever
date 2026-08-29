<?php

/**
 * Matcher Messaging Admin Settings
 * Admin interface for managing the matcher-messaging integration system
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Matcher_Messaging_Admin
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'add_redirect_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_sffc_test_emily_message', [$this, 'ajax_test_emily_message']);
        add_action('wp_ajax_sffc_send_onboarding_message', [$this, 'ajax_send_onboarding_message']);
        add_action('wp_ajax_sffc_update_match_settings', [$this, 'ajax_update_match_settings']);
        add_action('wp_ajax_sffc_test_claude_api', [$this, 'ajax_test_claude_api']);
        add_action('wp_ajax_sffc_get_redirect_urls', [$this, 'ajax_get_redirect_urls']);
        add_action('wp_ajax_nopriv_sffc_get_redirect_urls', [$this, 'ajax_get_redirect_urls']);

        // First login detection and welcome messages
        add_action('wp_login', [$this, 'handle_user_login'], 10, 2);
        add_action('init', [$this, 'check_profile_completion_reminders']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'senna-messaging', // Parent slug from messaging dashboard
            'Matcher Settings',
            '🎯 Matcher Settings',
            'manage_options',
            'sffc-matcher-settings',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings()
    {
        register_setting('sffc_matcher_settings', 'sffc_matcher_options');

        // Initialize default settings
        if (!get_option('sffc_matcher_options')) {
            $defaults = [
                'min_match_score' => 70,
                'daily_message_limit' => 5,
                'emily_enabled' => true,
                'onboarding_enabled' => true,
                'profile_reminders' => true,
                'cv_upload_prompts' => true,
                'message_frequency' => 'immediate',
                'emily_personality' => 'professional_warm',
                'custom_greeting' => '',
                'excluded_job_titles' => '',
                'debug_mode' => false,
                'career_sequence_enabled' => true,
                'claude_api_key' => '',
                'ongoing_insights_enabled' => true
            ];
            update_option('sffc_matcher_options', $defaults);
        }
    }

    /**
     * Render admin page
     */
    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $options = get_option('sffc_matcher_options');
        $integration = SFFC_Matcher_Messaging_Integration::get_instance();
        $config_status = $integration->get_configuration_status();
        $stats = $integration->get_integration_statistics();

?>
        <div class="wrap">
            <h1>🎯 Matcher-Messaging Settings</h1>

            <!-- System Status -->
            <div class="notice notice-<?php echo $config_status['overall_status'] ? 'success' : 'error'; ?>">
                <p>
                    <strong>System Status:</strong>
                    <?php echo $config_status['overall_status'] ? '✅ Operational' : '❌ Issues Detected'; ?>
                    <span style="float: right;">
                        <button type="button" class="button button-secondary" onclick="location.reload()">Refresh Status</button>
                    </span>
                </p>
            </div>

            <!-- Quick Actions -->
            <div class="card" style="margin-bottom: 20px;">
                <h2>Quick Actions</h2>
                <p>
                    <button type="button" class="button button-primary" id="test-emily-message">
                        📧 Test Emily Message
                    </button>
                    <button type="button" class="button button-secondary" id="send-onboarding-batch">
                        👋 Send Onboarding to All New Users
                    </button>
                    <button type="button" class="button button-secondary" onclick="window.open('<?php echo admin_url('admin.php?page=senna-messaging'); ?>', '_blank')">
                        💬 View All Messages
                    </button>
                    <button type="button" class="button button-secondary" id="test-claude-api">
                        🤖 Test Claude API
                    </button>
                </p>
            </div>

            <!-- Statistics Dashboard -->
            <div class="card" style="margin-bottom: 20px;">
                <h2>📊 Performance Statistics</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div class="stat-box">
                        <h3>Match Messages</h3>
                        <p class="stat-number"><?php echo number_format($stats['matching']['total_matches_sent'] ?? 0); ?></p>
                        <small>Total sent</small>
                    </div>
                    <div class="stat-box">
                        <h3>Today's Activity</h3>
                        <p class="stat-number"><?php echo number_format($stats['matching']['matches_today'] ?? 0); ?></p>
                        <small>Matches sent today</small>
                    </div>
                    <div class="stat-box">
                        <h3>Average Score</h3>
                        <p class="stat-number"><?php echo round($stats['matching']['avg_match_score'] ?? 0, 1); ?>%</p>
                        <small>Match quality</small>
                    </div>
                    <div class="stat-box">
                        <h3>Response Rate</h3>
                        <p class="stat-number"><?php echo round($stats['matching']['response_rate'] ?? 0, 1); ?>%</p>
                        <small>User engagement</small>
                    </div>
                </div>
            </div>

            <!-- Settings Form -->
            <form method="post" action="options.php" id="matcher-settings-form">
                <?php settings_fields('sffc_matcher_settings'); ?>

                <!-- Emily Settings -->
                <div class="card">
                    <h2>👩‍💼 Emily Settings</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Enable Emily Messages</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sffc_matcher_options[emily_enabled]" value="1"
                                        <?php checked($options['emily_enabled'] ?? true); ?> />
                                    Enable automatic messages from Emily Bradshaw
                                </label>
                                <p class="description">Emily Bradshaw (User ID: 28134) - Career Success Manager will send personalized job match notifications</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Emily's Personality</th>
                            <td>
                                <select name="sffc_matcher_options[emily_personality]">
                                    <option value="professional_warm" <?php selected($options['emily_personality'] ?? 'professional_warm', 'professional_warm'); ?>>
                                        Professional & Warm
                                    </option>
                                    <option value="friendly_casual" <?php selected($options['emily_personality'] ?? 'professional_warm', 'friendly_casual'); ?>>
                                        Friendly & Casual
                                    </option>
                                    <option value="executive_formal" <?php selected($options['emily_personality'] ?? 'professional_warm', 'executive_formal'); ?>>
                                        Executive & Formal
                                    </option>
                                    <option value="mentor_supportive" <?php selected($options['emily_personality'] ?? 'professional_warm', 'mentor_supportive'); ?>>
                                        Mentor & Supportive
                                    </option>
                                </select>
                                <p class="description">Choose Emily's communication style for all messages</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Custom Greeting</th>
                            <td>
                                <textarea name="sffc_matcher_options[custom_greeting]" rows="3" cols="50"
                                    placeholder="Leave empty to use default personalized greetings"><?php echo esc_textarea($options['custom_greeting'] ?? ''); ?></textarea>
                                <p class="description">Optional custom greeting for Emily's messages (use {first_name} for personalization)</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Onboarding Settings -->
                <div class="card">
                    <h2>👋 Onboarding & Welcome Messages</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">First Login Welcome</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sffc_matcher_options[onboarding_enabled]" value="1"
                                        <?php checked($options['onboarding_enabled'] ?? true); ?> />
                                    Send welcome message on first login
                                </label>
                                <p class="description">Emily will introduce herself and explain the matching system</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Profile Completion Reminders</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sffc_matcher_options[profile_reminders]" value="1"
                                        <?php checked($options['profile_reminders'] ?? true); ?> />
                                    Send gentle reminders to complete profile
                                </label>
                                <p class="description">Encourage users to fill out their career information</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">CV Upload Prompts</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sffc_matcher_options[cv_upload_prompts]" value="1"
                                        <?php checked($options['cv_upload_prompts'] ?? true); ?> />
                                    Encourage CV uploads in MENA Careers chat
                                </label>
                                <p class="description">Emily will suggest uploading CV for better matches</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Matching Settings -->
                <div class="card">
                    <h2>🎯 Matching Settings</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Minimum Match Score</th>
                            <td>
                                <input type="range" name="sffc_matcher_options[min_match_score]"
                                    min="50" max="95" step="5"
                                    value="<?php echo $options['min_match_score'] ?? 70; ?>"
                                    oninput="this.nextElementSibling.value = this.value + '%'" />
                                <output><?php echo $options['min_match_score'] ?? 70; ?>%</output>
                                <p class="description">Only send matches above this quality threshold</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Daily Message Limit</th>
                            <td>
                                <select name="sffc_matcher_options[daily_message_limit]">
                                    <option value="3" <?php selected($options['daily_message_limit'] ?? 5, 3); ?>>3 messages per day</option>
                                    <option value="5" <?php selected($options['daily_message_limit'] ?? 5, 5); ?>>5 messages per day</option>
                                    <option value="7" <?php selected($options['daily_message_limit'] ?? 5, 7); ?>>7 messages per day</option>
                                    <option value="10" <?php selected($options['daily_message_limit'] ?? 5, 10); ?>>10 messages per day</option>
                                </select>
                                <p class="description">Maximum mentorship sessions included for each user</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Message Frequency</th>
                            <td>
                                <select name="sffc_matcher_options[message_frequency]">
                                    <option value="immediate" <?php selected($options['message_frequency'] ?? 'immediate', 'immediate'); ?>>
                                        Immediate (as matches are found)
                                    </option>
                                    <option value="daily_digest" <?php selected($options['message_frequency'] ?? 'immediate', 'daily_digest'); ?>>
                                        Daily Digest (once per day)
                                    </option>
                                    <option value="weekly_summary" <?php selected($options['message_frequency'] ?? 'immediate', 'weekly_summary'); ?>>
                                        Weekly Summary
                                    </option>
                                </select>
                                <p class="description">How often to send job match notifications</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Career Insights Sequence -->
                <div class="card">
                    <h2>📚 Career Insights Sequence</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Enable Career Sequence</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sffc_matcher_options[career_sequence_enabled]" value="1"
                                        <?php checked($options['career_sequence_enabled'] ?? true); ?> />
                                    Send 10-email career insights sequence to new users
                                </label>
                                <p class="description">Triggers on first login, sends one email every 2 days over 20 days</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Claude API Key</th>
                            <td>
                                <input type="password" name="sffc_matcher_options[claude_api_key]"
                                    value="<?php echo esc_attr($options['claude_api_key'] ?? ''); ?>"
                                    size="50" placeholder="sk-ant-api03-..." />
                                <p class="description">Required for ongoing AI-generated career insights after sequence completion</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Ongoing AI Insights</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sffc_matcher_options[ongoing_insights_enabled]" value="1"
                                        <?php checked($options['ongoing_insights_enabled'] ?? true); ?> />
                                    Send AI-generated insights every 3 days after sequence completion
                                </label>
                                <p class="description">Uses Claude API to generate personalized career insights</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Advanced Settings -->
                <div class="card">
                    <h2>⚙️ Advanced Settings</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Excluded Job Titles</th>
                            <td>
                                <textarea name="sffc_matcher_options[excluded_job_titles]" rows="3" cols="50"
                                    placeholder="Enter job titles to exclude, one per line"><?php echo esc_textarea($options['excluded_job_titles'] ?? ''); ?></textarea>
                                <p class="description">Job titles that should never be matched (one per line)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Debug Mode</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sffc_matcher_options[debug_mode]" value="1"
                                        <?php checked($options['debug_mode'] ?? false); ?> />
                                    Enable detailed logging
                                </label>
                                <p class="description">Log detailed matching information for troubleshooting</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button('Save Settings'); ?>
            </form>

            <!-- System Health -->
            <div class="card">
                <h2>🏥 System Health</h2>
                <div class="health-grid">
                    <?php foreach ($config_status as $component => $status): ?>
                        <?php if ($component === 'integration_health' || $component === 'overall_status') continue; ?>
                        <div class="health-item">
                            <span class="health-icon"><?php echo $status ? '✅' : '❌'; ?></span>
                            <span class="health-label"><?php echo ucwords(str_replace('_', ' ', $component)); ?></span>
                            <span class="health-status"><?php echo $status ? 'OK' : 'Error'; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card">
                <h2>📋 Recent Activity</h2>
                <?php $this->render_recent_activity(); ?>
            </div>
        </div>

        <style>
            .card {
                background: white;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                margin-bottom: 20px;
                box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
            }

            .card h2 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }

            .stat-box {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 6px;
                text-align: center;
            }

            .stat-number {
                font-size: 28px;
                font-weight: bold;
                margin: 5px 0;
                color: #2271b1;
            }

            .health-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 10px;
            }

            .health-item {
                display: flex;
                align-items: center;
                padding: 10px;
                background: #f8f9fa;
                border-radius: 4px;
            }

            .health-icon {
                margin-right: 10px;
                font-size: 18px;
            }

            .health-label {
                flex-grow: 1;
                font-weight: 500;
            }

            .health-status {
                font-size: 12px;
                color: #666;
            }
        </style>
<?php
    }

    /**
     * Render recent activity
     */
    private function render_recent_activity()
    {
        global $wpdb;

        $sent_table = $wpdb->prefix . 'sffc_sent_matches';
        $messages_table = $wpdb->prefix . 'senna_messages';

        // Get recent matches
        $recent_matches = $wpdb->get_results(
            "SELECT sm.*, p.post_title as job_title, u.display_name as user_name
             FROM $sent_table sm
             LEFT JOIN {$wpdb->posts} p ON sm.job_id = p.ID
             LEFT JOIN {$wpdb->users} u ON sm.user_id = u.ID
             ORDER BY sm.sent_at DESC
             LIMIT 10"
        );

        if (!empty($recent_matches)) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>Date</th><th>User</th><th>Job</th><th>Score</th><th>Response</th>';
            echo '</tr></thead><tbody>';

            foreach ($recent_matches as $match) {
                echo '<tr>';
                echo '<td>' . esc_html(date('M j, H:i', strtotime($match->sent_at))) . '</td>';
                echo '<td>' . esc_html($match->user_name ?: 'Unknown') . '</td>';
                echo '<td>' . esc_html(wp_trim_words($match->job_title ?: 'Unknown Job', 5)) . '</td>';
                echo '<td><strong>' . esc_html($match->match_score) . '%</strong></td>';
                echo '<td>';
                switch ($match->user_response) {
                    case 'interested':
                        echo '<span style="color: green;">✅ Interested</span>';
                        break;
                    case 'applied':
                        echo '<span style="color: blue;">🎯 Applied</span>';
                        break;
                    case 'not_interested':
                        echo '<span style="color: orange;">❌ Not Interested</span>';
                        break;
                    default:
                        echo '<span style="color: gray;">⏳ Pending</span>';
                }
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        } else {
            echo '<p>No recent activity to display.</p>';
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'sffc-matcher-settings') === false) {
            return;
        }

        wp_enqueue_script(
            'sffc-matcher-admin',
            plugin_dir_url(__FILE__) . '../assets/js/matcher-admin.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script('sffc-matcher-admin', 'sffc_matcher_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_matcher_admin'),
            'messages' => [
                'test_success' => 'Test message sent successfully!',
                'test_error' => 'Failed to send test message.',
                'onboarding_success' => 'Onboarding messages sent to new users!',
                'settings_saved' => 'Settings saved successfully!'
            ]
        ]);
    }

    /**
     * AJAX: Test Emily message
     */
    public function ajax_test_emily_message()
    {
        check_ajax_referer('sffc_matcher_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        try {
            $messaging = SkillFarm_Messaging_Messages::get_instance();
            $current_user = wp_get_current_user();

            $test_message = $this->generate_test_message($current_user);

            $result = $messaging->send_message([
                'sender_id' => 28134, // Emily
                'recipient_id' => get_current_user_id(),
                'subject' => 'Test Message from Emily - Matcher System',
                'content' => $test_message,
                'category' => 'system_test',
                'priority' => 'normal'
            ]);

            if (!is_wp_error($result)) {
                wp_send_json_success(['message' => 'Test message sent successfully!']);
            } else {
                wp_send_json_error(['message' => 'Failed to send message: ' . $result->get_error_message()]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Generate test message
     */
    private function generate_test_message($user)
    {
        $first_name = $user->first_name ?: $user->display_name;

        return "
        <p>Hello {$first_name},</p>
        
        <p>This is a test message from the Matcher-Messaging system. I'm Emily Bradshaw, your Career Success Manager at MENA Careers.</p>
        
        <p><strong>🎯 System Status:</strong> All components are working perfectly!</p>
        
        <p><strong>✅ What's Working:</strong></p>
        <ul>
            <li>CV-based job matching</li>
            <li>Automatic message delivery</li>
            <li>Personalized match scoring</li>
            <li>Integration with MENA Careers chat</li>
        </ul>
        
        <p>The system is ready to help connect our users with their perfect career opportunities.</p>
        
        <p>Best regards,<br>
        <strong>Emily Bradshaw</strong><br>
        <em>Career Success Manager</em><br>
        MENA Careers</p>
        ";
    }

    /**
     * AJAX: Send onboarding messages
     */
    public function ajax_send_onboarding_message()
    {
        check_ajax_referer('sffc_matcher_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        try {
            $sent_count = $this->send_onboarding_to_new_users();
            wp_send_json_success(['message' => "Onboarding messages sent to {$sent_count} users!"]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Send onboarding messages to users who haven't received them
     */
    private function send_onboarding_to_new_users()
    {
        global $wpdb;

        $messages_table = $wpdb->prefix . 'senna_messages';
        $emily_id = 28134;

        // Get users who haven't received onboarding messages
        $users_without_onboarding = $wpdb->get_col("
            SELECT u.ID FROM {$wpdb->users} u
            LEFT JOIN $messages_table m ON u.ID = m.recipient_id 
                AND m.sender_id = $emily_id 
                AND m.subject LIKE '%Welcome to MENA Careers%'
            WHERE m.id IS NULL
            AND u.ID != $emily_id
            LIMIT 50
        ");

        $sent_count = 0;
        $messaging = SkillFarm_Messaging_Messages::get_instance();

        foreach ($users_without_onboarding as $user_id) {
            $user = get_userdata($user_id);
            if (!$user) continue;

            $onboarding_message = $this->generate_onboarding_message($user);

            $result = $messaging->send_message([
                'sender_id' => $emily_id,
                'recipient_id' => $user_id,
                'subject' => 'Welcome to MENA Careers - Let\'s Find Your Perfect Role!',
                'content' => $onboarding_message,
                'category' => 'onboarding',
                'priority' => 'high'
            ]);

            if (!is_wp_error($result)) {
                $sent_count++;
            }
        }

        return $sent_count;
    }

    /**
     * Generate personalized onboarding message
     */
    private function generate_onboarding_message($user)
    {
        $first_name = $user->first_name ?: $user->display_name;
        $options = get_option('sffc_matcher_options');

        // Custom greeting if set
        if (!empty($options['custom_greeting'])) {
            $greeting = str_replace('{first_name}', $first_name, $options['custom_greeting']);
        } else {
            $greeting = "Hello {$first_name}";
        }

        // Personality-based message variations
        $personality = $options['emily_personality'] ?? 'professional_warm';

        switch ($personality) {
            case 'friendly_casual':
                return $this->generate_friendly_onboarding($greeting, $first_name);
            case 'executive_formal':
                return $this->generate_executive_onboarding($greeting, $first_name);
            case 'mentor_supportive':
                return $this->generate_mentor_onboarding($greeting, $first_name);
            default:
                return $this->generate_professional_onboarding($greeting, $first_name);
        }
    }

    /**
     * Generate professional warm onboarding message
     */
    private function generate_professional_onboarding($greeting, $first_name)
    {
        return "
        <p>{$greeting},</p>
        
        <p>I'm Emily Bradshaw, your dedicated Career Success Manager here at MENA Careers. I wanted to personally welcome you and share how excited I am to help accelerate your career in financial services.</p>
        
        <p><strong>Here's how I'll be supporting you:</strong></p>
        
        <p>🎯 <strong>Intelligent Job Matching:</strong> I'm constantly scanning the market for opportunities that align perfectly with your experience and career goals. When I find something special, I'll reach out immediately.</p>
        
        <p>📋 <strong>To get started, I'd love if you could:</strong></p>
        <ol>
            <li><strong>Complete your profile</strong> - The more I know about your background and aspirations, the better I can match you</li>
            <li><strong>Upload your CV</strong> - This helps me understand your experience deeply and find roles that truly fit</li>
            <li><strong>Chat with MENA Careers</strong> - Our AI career strategist can help refine your profile and discuss your goals</li>
        </ol>
        
        <p>💼 <strong>What makes this different?</strong> I don't just send you every job posting. I personally review each opportunity and only reach out when I find roles that could be genuinely transformative for your career.</p>
        
        <p>I'm here whenever you need guidance, have questions about an opportunity, or want to discuss your career strategy. Your success is my priority.</p>
        
        <p>Looking forward to finding your next great opportunity!</p>
        
        <p>Warm regards,<br>
        <strong>Emily Bradshaw</strong><br>
        <em>Career Success Manager</em><br>
        senna | <a href=\"mailto:emily.bradshaw@senna.com\">emily.bradshaw@senna.com</a></p>
        ";
    }

    /**
     * Generate friendly casual onboarding message
     */
    private function generate_friendly_onboarding($greeting, $first_name)
    {
        return "
        <p>{$greeting}! 👋</p>
        
        <p>Emily here from MENA Careers! So glad you've joined us - I think we're going to do great things together.</p>
        
        <p>Here's the deal: I'm like your career wingwoman. I spend my days hunting down amazing opportunities in finance and matching them with talented people like you. When I spot something that's perfect for your background, you'll be the first to know!</p>
        
        <p><strong>Let's get you set up for success:</strong></p>
        
        <p>✨ <strong>Upload your CV</strong> - This is my secret weapon for finding you the perfect roles<br>
        📝 <strong>Fill out your profile</strong> - Tell me about your dream job, salary expectations, location preferences<br>
        💬 <strong>Chat with MENA Careers</strong> - Our AI assistant is brilliant at helping clarify your career goals</p>
        
        <p>The cool thing about our system? I don't spam you with every job under the sun. I'm super selective and only reach out when I find something that could genuinely be your next career move.</p>
        
        <p>Questions? Ideas? Just want to chat about your career? I'm here for all of it!</p>
        
        <p>Excited to help you find something amazing! 🚀</p>
        
        <p>Cheers,<br>
        <strong>Emily</strong><br>
        <em>Your Career Success Partner</em><br>
        MENA Careers</p>
        ";
    }

    /**
     * Generate executive formal onboarding message
     */
    private function generate_executive_onboarding($greeting, $first_name)
    {
        return "
        <p>{$greeting},</p>
        
        <p>I am Emily Bradshaw, Career Success Manager at MENA Careers. It is my privilege to personally oversee your career advancement within our exclusive network.</p>
        
        <p><strong>Your Executive Partnership:</strong></p>
        
        <p>As a senior professional, you deserve a sophisticated approach to career development. I provide discretionary, high-level matching services, connecting you exclusively with C-suite, managing director, and partnership opportunities.</p>
        
        <p><strong>Immediate Next Steps:</strong></p>
        <ul>
            <li><strong>Executive Profile Completion:</strong> Comprehensive background documentation enables precise opportunity targeting</li>
            <li><strong>Confidential Career Assessment:</strong> Upload your executive CV for detailed capability assessment</li>
            <li><strong>Strategic Consultation:</strong> Engage with our AI strategist to refine your positioning</li>
        </ul>
        
        <p><strong>Our Commitment:</strong> I personally evaluate each opportunity before presentation. You will only receive communications regarding roles that represent genuine career advancement and align with your executive trajectory.</p>
        
        <p>I look forward to facilitating your next strategic career move.</p>
        
        <p>Respectfully,<br>
        <strong>Emily Bradshaw</strong><br>
        <em>Career Success Manager</em><br>
        MENA Careers Executive Services</p>
        ";
    }

    /**
     * Generate mentor supportive onboarding message
     */
    private function generate_mentor_onboarding($greeting, $first_name)
    {
        return "
        <p>{$greeting},</p>
        
        <p>I'm Emily Bradshaw, and I'm genuinely thrilled to be part of your career journey. Having spent years helping financial services professionals navigate their careers, I know how important it is to have someone in your corner who truly understands your aspirations.</p>
        
        <p><strong>Think of me as your career advocate.</strong> My role is to understand not just what you do, but who you want to become professionally. Every opportunity I share with you is carefully considered against your personal goals and values.</p>
        
        <p><strong>Let's build your success foundation together:</strong></p>
        
        <p>🤝 <strong>Complete your profile</strong> - Share your story, ambitions, and what success looks like for you<br>
        📄 <strong>Upload your CV</strong> - This helps me see your unique strengths and potential<br>
        💭 <strong>Explore with MENA Careers</strong> - Sometimes talking through your goals helps clarify your path forward</p>
        
        <p><strong>My promise to you:</strong> I won't overwhelm you with opportunities. Instead, I'll focus on quality connections that align with your career vision. Every message from me is because I genuinely believe the role could be meaningful for your journey.</p>
        
        <p>I'm here to support you, celebrate your wins, and help navigate any challenges. Your growth and satisfaction matter deeply to me.</p>
        
        <p>Here's to your continued success!</p>
        
        <p>With warm regards,<br>
        <strong>Emily Bradshaw</strong><br>
        <em>Career Success Mentor</em><br>
        MENA Careers | Always here when you need guidance</p>
        ";
    }

    /**
     * AJAX: Update match settings
     */
    public function ajax_update_match_settings()
    {
        check_ajax_referer('sffc_matcher_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $options = get_option('sffc_matcher_options');

        // Update settings from AJAX request
        if (isset($_POST['min_match_score'])) {
            $options['min_match_score'] = intval($_POST['min_match_score']);
        }

        if (isset($_POST['daily_message_limit'])) {
            $options['daily_message_limit'] = intval($_POST['daily_message_limit']);
        }

        update_option('sffc_matcher_options', $options);

        wp_send_json_success(['message' => 'Settings updated successfully!']);
    }

    /**
     * AJAX: Test Claude API
     */
    public function ajax_test_claude_api()
    {
        check_ajax_referer('sffc_matcher_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        try {
            $claude_generator = SFFC_Claude_Insights_Generator::get_instance();
            $test_result = $claude_generator->test_claude_api();

            if ($test_result) {
                wp_send_json_success(['message' => 'Claude API connection successful!', 'data' => $test_result]);
            } else {
                wp_send_json_error(['message' => 'Claude API test failed. Check your API key.']);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error testing Claude API: ' . $e->getMessage()]);
        }
    }

    /**
     * Handle user login and send welcome message if first time
     */
    public function handle_user_login($user_login, $user)
    {
        $options = get_option('sffc_matcher_options');

        // Check if onboarding is enabled
        if (!($options['onboarding_enabled'] ?? true)) {
            return;
        }

        // Check if this is the first login
        $first_login_check = get_user_meta($user->ID, 'sffc_first_login_handled', true);

        if (!$first_login_check) {
            // Mark as handled to prevent duplicate messages
            update_user_meta($user->ID, 'sffc_first_login_handled', time());

            // Schedule welcome message to be sent after page load
            wp_schedule_single_event(time() + 30, 'sffc_send_welcome_message', [$user->ID]);
        }
    }

    /**
     * Send welcome message (via scheduled event)
     */
    public function send_welcome_message($user_id)
    {
        try {
            $user = get_userdata($user_id);
            if (!$user) return;

            $messaging = SkillFarm_Messaging_Messages::get_instance();
            $onboarding_message = $this->generate_onboarding_message($user);

            $result = $messaging->send_message([
                'sender_id' => 28134, // Emily
                'recipient_id' => $user_id,
                'subject' => 'Welcome to MENA Careers - Let\'s Find Your Perfect Role!',
                'content' => $onboarding_message,
                'category' => 'onboarding',
                'priority' => 'high'
            ]);

            if (!is_wp_error($result)) {
                error_log("Welcome message sent to user {$user_id} on first login");
            }
        } catch (Exception $e) {
            error_log("Error sending welcome message: " . $e->getMessage());
        }
    }

    /**
     * Check for profile completion reminders
     */
    public function check_profile_completion_reminders()
    {
        // Only run once per day
        $last_check = get_option('sffc_last_profile_reminder_check');
        if ($last_check && (time() - $last_check) < DAY_IN_SECONDS) {
            return;
        }

        update_option('sffc_last_profile_reminder_check', time());

        $options = get_option('sffc_matcher_options');

        // Check if reminders are enabled
        if (!($options['profile_reminders'] ?? true)) {
            return;
        }

        // Find users who need profile completion reminders
        $users_needing_reminders = $this->get_users_needing_profile_reminders();

        foreach ($users_needing_reminders as $user_id) {
            $this->send_profile_completion_reminder($user_id);
        }
    }

    /**
     * Get users who need profile completion reminders
     */
    private function get_users_needing_profile_reminders()
    {
        global $wpdb;

        // Get users who:
        // 1. Have been registered for more than 3 days
        // 2. Haven't completed their profile
        // 3. Haven't received a reminder in the last 7 days

        $messages_table = $wpdb->prefix . 'senna_messages';
        $emily_id = 28134;

        $users = $wpdb->get_col("
            SELECT u.ID 
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = 'sffc_profile_complete'
            LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'sffc_cv_uploaded'
            LEFT JOIN $messages_table m ON u.ID = m.recipient_id 
                AND m.sender_id = $emily_id 
                AND m.subject LIKE '%Complete Your Profile%'
                AND m.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            WHERE u.user_registered < DATE_SUB(NOW(), INTERVAL 3 DAY)
            AND u.ID != $emily_id
            AND (um1.meta_value IS NULL OR um1.meta_value = '0')
            AND (um2.meta_value IS NULL OR um2.meta_value = '0')
            AND m.id IS NULL
            LIMIT 20
        ");

        return array_map('intval', $users);
    }

    /**
     * Send profile completion reminder
     */
    private function send_profile_completion_reminder($user_id)
    {
        try {
            $user = get_userdata($user_id);
            if (!$user) return;

            $messaging = SkillFarm_Messaging_Messages::get_instance();
            $reminder_message = $this->generate_profile_reminder_message($user);

            $result = $messaging->send_message([
                'sender_id' => 28134, // Emily
                'recipient_id' => $user_id,
                'subject' => 'Complete Your Profile - Better Matches Await!',
                'content' => $reminder_message,
                'category' => 'profile_reminder',
                'priority' => 'normal'
            ]);

            if (!is_wp_error($result)) {
                error_log("Profile completion reminder sent to user {$user_id}");
            }
        } catch (Exception $e) {
            error_log("Error sending profile reminder: " . $e->getMessage());
        }
    }

    /**
     * Generate profile completion reminder message
     */
    private function generate_profile_reminder_message($user)
    {
        $first_name = $user->first_name ?: $user->display_name;
        $options = get_option('sffc_matcher_options');
        $personality = $options['emily_personality'] ?? 'professional_warm';

        switch ($personality) {
            case 'friendly_casual':
                return "
                <p>Hi {$first_name}! 👋</p>
                
                <p>Emily here from MENA Careers! I hope you're settling in well. I wanted to check in because I've noticed your profile could use some love, and I'm excited to help you get the most out of our platform.</p>
                
                <p><strong>Here's the thing:</strong> The more you tell me about yourself, the better I can match you with amazing opportunities. Right now, I'm working a bit blind, and I know there are probably some fantastic roles out there with your name on them!</p>
                
                <p><strong>Quick wins to boost your matches:</strong></p>
                <ul>
                    <li>📝 <strong>Complete your profile</strong> - Tell me about your experience, goals, and what excites you</li>
                    <li>📄 <strong>Upload your CV</strong> - This is my secret weapon for finding perfect matches</li>
                    <li>💬 <strong>Chat with MENA Careers</strong> - Our AI can help clarify your career goals</li>
                </ul>
                
                <p>Trust me, it's worth the 10 minutes! I've seen users go from zero matches to multiple exciting opportunities just by completing their profile.</p>
                
                <p>Ready to unlock some amazing career opportunities? 🚀</p>
                
                <p>Cheers,<br>
                <strong>Emily</strong><br>
                <em>Your Career Success Partner</em></p>
                ";

            case 'executive_formal':
                return "
                <p>Dear {$first_name},</p>
                
                <p>I trust this message finds you well. As your Career Success Manager, I wanted to address an important opportunity to optimize your career advancement through our platform.</p>
                
                <p><strong>Current Profile Status:</strong> Incomplete</p>
                <p><strong>Impact on Matching:</strong> Limited visibility to executive opportunities</p>
                
                <p><strong>Recommended Actions:</strong></p>
                <ol>
                    <li><strong>Profile Completion:</strong> Comprehensive background documentation enables precise targeting of C-suite and senior management roles</li>
                    <li><strong>Executive CV Upload:</strong> Detailed capability assessment allows for strategic opportunity alignment</li>
                    <li><strong>Strategic Consultation:</strong> Utilize our AI strategist to refine your executive positioning</li>
                </ol>
                
                <p><strong>Business Impact:</strong> Complete profiles receive 5x more high-level match notifications and access to our exclusive executive opportunity pipeline.</p>
                
                <p>I recommend prioritizing profile completion to maximize your strategic career advancement potential.</p>
                
                <p>Respectfully,<br>
                <strong>Emily Bradshaw</strong><br>
                <em>Career Success Manager</em></p>
                ";

            case 'mentor_supportive':
                return "
                <p>Hello {$first_name},</p>
                
                <p>I hope you're doing well and feeling excited about the possibilities ahead. I wanted to reach out personally because I believe there's so much potential in your career journey, and I'd love to help you unlock it.</p>
                
                <p><strong>Here's what I'm thinking:</strong> Your profile is like a story waiting to be told, and right now, we're only seeing the first chapter. I know there's so much more to you - your experiences, aspirations, and unique strengths that make you special.</p>
                
                <p><strong>Let's complete your story together:</strong></p>
                <ul>
                    <li>🌟 <strong>Share your journey</strong> - Tell me about where you've been and where you want to go</li>
                    <li>📋 <strong>Upload your CV</strong> - This helps me see the full picture of your amazing experience</li>
                    <li>💭 <strong>Explore your goals</strong> - Sometimes talking through your aspirations with MENA Careers helps clarify your path</li>
                </ul>
                
                <p><strong>Why this matters:</strong> I've watched countless professionals transform their careers simply by taking the time to properly showcase who they are. You deserve opportunities that truly recognize your worth and potential.</p>
                
                <p>I'm here to support you every step of the way. Your growth and satisfaction matter deeply to me.</p>
                
                <p>With warm encouragement,<br>
                <strong>Emily Bradshaw</strong><br>
                <em>Career Success Mentor</em></p>
                ";

            default: // professional_warm
                return "
                <p>Hello {$first_name},</p>
                
                <p>I hope you're settling in well at MENA Careers. As your Career Success Manager, I wanted to personally check in and share some insights that could significantly enhance your experience with us.</p>
                
                <p><strong>I've noticed your profile needs some attention,</strong> and I'm excited to help you optimize it for better career opportunities. A complete profile is the foundation of our matching system, and it makes all the difference in connecting you with roles that truly align with your goals.</p>
                
                <p><strong>Here's what would help me find you perfect matches:</strong></p>
                <ol>
                    <li><strong>Complete your professional profile</strong> - Share your experience, skills, and career aspirations</li>
                    <li><strong>Upload your CV</strong> - This allows me to understand your background in depth</li>
                    <li><strong>Define your preferences</strong> - Salary expectations, location, role types you're interested in</li>
                </ol>
                
                <p><strong>The difference is remarkable:</strong> Users with complete profiles receive 4x more relevant job matches and are 3x more likely to hear about exclusive opportunities before they're publicly posted.</p>
                
                <p>I'm committed to finding you opportunities that genuinely match your skills and ambitions. The more you share with me, the better I can serve you.</p>
                
                <p>Looking forward to helping you take the next step in your career!</p>
                
                <p>Best regards,<br>
                <strong>Emily Bradshaw</strong><br>
                <em>Career Success Manager</em></p>
                ";
        }
    }

    /**
     * Add URL redirect settings to admin
     */
    public function add_redirect_settings()
    {
        // Add settings section for URL redirects
        add_settings_section(
            'sffc_url_redirects',
            'UI Element Redirects',
            array($this, 'url_redirects_section_callback'),
            'sffc_matcher_messaging_settings'
        );

        // Send button and chat suggestion redirects
        add_settings_field(
            'sffc_senna_ai_url',
            'MENA Careers AI Page URL',
            array($this, 'url_field_callback'),
            'sffc_matcher_messaging_settings',
            'sffc_url_redirects',
            array(
                'field_name' => 'sffc_senna_ai_url',
                'default' => 'https://joinsenna.com/senna-ai',
                'description' => 'URL for send-btn and chat-suggestion-item clicks'
            )
        );

        // MENA Careers chat URL
        add_settings_field(
            'sffc_senna_chat_url',
            'MENA Careers Chat URL',
            array($this, 'url_field_callback'),
            'sffc_matcher_messaging_settings',
            'sffc_url_redirects',
            array(
                'field_name' => 'sffc_senna_chat_url',
                'default' => 'https://joinsenna.com/senna-chat',
                'description' => 'URL for new_chat, ai-rail-btn, Browse live mandates, and Build prep plan'
            )
        );

        // Login/Auth URL for profile
        add_settings_field(
            'sffc_login_auth_url',
            'Login/Auth Page URL',
            array($this, 'url_field_callback'),
            'sffc_matcher_messaging_settings',
            'sffc_url_redirects',
            array(
                'field_name' => 'sffc_login_auth_url',
                'default' => 'https://joinsenna.com/login-auth/',
                'description' => 'URL for profile button (logged-in users)'
            )
        );

        // Request meeting URL
        add_settings_field(
            'sffc_request_meeting_url',
            'Request Meeting URL',
            array($this, 'url_field_callback'),
            'sffc_matcher_messaging_settings',
            'sffc_url_redirects',
            array(
                'field_name' => 'sffc_request_meeting_url',
                'default' => 'https://joinsenna.com/request-meeting/',
                'description' => 'URL for "Book a strategy session"'
            )
        );

        // Register all settings
        register_setting('sffc_matcher_messaging_settings', 'sffc_senna_ai_url');
        register_setting('sffc_matcher_messaging_settings', 'sffc_senna_chat_url');
        register_setting('sffc_matcher_messaging_settings', 'sffc_login_auth_url');
        register_setting('sffc_matcher_messaging_settings', 'sffc_request_meeting_url');
    }

    /**
     * URL redirects section callback
     */
    public function url_redirects_section_callback()
    {
        echo '<p>Configure redirect URLs for various UI elements. These URLs will be used when users click specific buttons or links.</p>';
    }

    /**
     * URL field callback
     */
    public function url_field_callback($args)
    {
        $field_name = $args['field_name'];
        $default = $args['default'];
        $description = $args['description'];
        $value = get_option($field_name, $default);

        echo '<input type="url" name="' . esc_attr($field_name) . '" value="' . esc_url($value) . '" class="regular-text" />';
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }

    /**
     * Get redirect URLs for frontend
     */
    public function get_redirect_urls()
    {
        return array(
            'senna_ai_url' => get_option('sffc_senna_ai_url', 'https://joinsenna.com/senna-ai'),
            'senna_chat_url' => get_option('sffc_senna_chat_url', 'https://joinsenna.com/senna-chat'),
            'login_auth_url' => get_option('sffc_login_auth_url', 'https://joinsenna.com/login-auth/'),
            'request_meeting_url' => get_option('sffc_request_meeting_url', 'https://joinsenna.com/request-meeting/')
        );
    }

    /**
     * AJAX handler to get redirect URLs
     */
    public function ajax_get_redirect_urls()
    {
        check_ajax_referer('skillfarm_messaging_nonce', 'nonce');

        wp_send_json_success($this->get_redirect_urls());
    }
}

// Initialize
SFFC_Matcher_Messaging_Admin::get_instance();

// Register the welcome message action
add_action('sffc_send_welcome_message', [SFFC_Matcher_Messaging_Admin::get_instance(), 'send_welcome_message']);
