<?php
/**
 * Career Advice Manager
 *
 * Handles rotation logic for career advice in Daily Market Briefing.
 * Uses pre-written messages for first 90 days, then switches to Claude API.
 *
 * @package SennaFinanceCareer
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Career_Advice_Manager {

    private static $instance = null;

    /** @var SFFC_Career_Advice_Library */
    private $library;

    /**
     * Claude API settings
     */
    private $claude_api_key;
    private $claude_model = 'claude-3-haiku-20240307';

    /**
     * Day-of-week topic rotation for Claude
     */
    private $claude_topics = [
        1 => 'Career strategy and long-term planning',
        2 => 'Technical skills and professional development',
        3 => 'Networking and relationship building',
        4 => 'Interview preparation and job applications',
        5 => 'Market opportunities and emerging trends',
        6 => 'Leadership and executive mindset',
        0 => 'Work-life balance and sustainable success' // Sunday
    ];

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Load the library
        if (!class_exists('SFFC_Career_Advice_Library')) {
            require_once plugin_dir_path(__FILE__) . 'class-career-advice-library.php';
        }
        $this->library = SFFC_Career_Advice_Library::get_instance();
    }

    private function maybe_load_claude_api_key() {
        if ($this->claude_api_key !== null) {
            return;
        }

        $this->claude_api_key = $this->get_claude_api_key();
    }

    /**
     * Get Claude API key from various possible option locations
     */
    private function get_claude_api_key() {
        // Check primary option
        $key = get_option('sffc_claude_api_key', '');
        if (!empty($key)) {
            return $key;
        }

        // Check messaging dashboard option
        $key = get_option('skillfarm_claude_api_key', '');
        if (!empty($key)) {
            return $key;
        }

        // Check matcher options array
        $matcher_options = get_option('sffc_matcher_options', []);
        if (!empty($matcher_options['claude_api_key'])) {
            return $matcher_options['claude_api_key'];
        }

        return '';
    }

    /**
     * Get today's career advice
     *
     * Logic:
     * - For first 90 days (message_count): Use library rotation
     * - After 90 days: Switch to Claude API generation
     * - If Claude fails: Fall back to library (cycling)
     *
     * @return array|null Array with 'title', 'content', 'category', 'source'
     */
    public function get_todays_advice() {
        $today = date('Y-m-d');
        $last_date = get_option('senna_career_advice_last_date', '');
        $last_index = (int) get_option('senna_career_advice_last_index', 0);

        // Check if we already generated advice today
        if ($last_date === $today && $last_index > 0) {
            $cached = get_transient('senna_career_advice_today');
            if ($cached) {
                return $cached;
            }
        }

        // Calculate which day we're on
        $start_date = get_option('senna_career_advice_start_date', '');
        if (empty($start_date)) {
            // First time running - set start date
            update_option('senna_career_advice_start_date', $today);
            $start_date = $today;
        }

        $days_elapsed = $this->get_days_between($start_date, $today);
        $message_count = $this->library->get_message_count();

        // Determine message index (1-based, cycling through library)
        $message_index = ($days_elapsed % $message_count) + 1;

        // After first cycle (90 days), try Claude API
        $advice = null;
        if ($days_elapsed >= $message_count && $this->should_use_claude()) {
            $advice = $this->generate_claude_advice();
        }

        // If no Claude advice, use library
        if (!$advice) {
            $library_message = $this->library->get_message($message_index);
            if ($library_message) {
                $advice = [
                    'title' => $library_message['title'],
                    'content' => $library_message['content'],
                    'category' => $library_message['category'],
                    'source' => 'library',
                    'index' => $message_index
                ];
            }
        }

        if ($advice) {
            // Save tracking data
            update_option('senna_career_advice_last_date', $today);
            update_option('senna_career_advice_last_index', $message_index);

            // Cache for today
            set_transient('senna_career_advice_today', $advice, DAY_IN_SECONDS);
        }

        return $advice;
    }

    /**
     * Get advice for a specific index (useful for testing)
     */
    public function get_advice_by_index($index) {
        $message = $this->library->get_message($index);
        if ($message) {
            return [
                'title' => $message['title'],
                'content' => $message['content'],
                'category' => $message['category'],
                'source' => 'library',
                'index' => $index
            ];
        }
        return null;
    }

    /**
     * Check if we should use Claude API
     */
    private function should_use_claude() {
        $this->maybe_load_claude_api_key();

        // Check if API key is configured
        if (empty($this->claude_api_key)) {
            return false;
        }

        // Check if Claude is enabled
        $claude_enabled = get_option('senna_career_advice_claude_enabled', false);
        return (bool) $claude_enabled;
    }

    /**
     * Generate career advice using Claude API
     */
    private function generate_claude_advice() {
        $this->maybe_load_claude_api_key();

        if (empty($this->claude_api_key)) {
            return null;
        }

        $day_of_week = (int) date('w');
        $topic = $this->claude_topics[$day_of_week] ?? 'Career development';

        $prompt = $this->build_claude_prompt($topic);

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $this->claude_api_key,
                'anthropic-version' => '2023-06-01'
            ],
            'body' => json_encode([
                'model' => $this->claude_model,
                'max_tokens' => 500,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ])
        ]);

        if (is_wp_error($response)) {
            error_log('Career Advice Claude API error: ' . $response->get_error_message());
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['content'][0]['text'])) {
            error_log('Career Advice Claude API: Empty response');
            return null;
        }

        $content = $body['content'][0]['text'];

        // Parse response (expecting Title: and Content: format)
        $parsed = $this->parse_claude_response($content);

        if ($parsed) {
            $parsed['source'] = 'claude';
            $parsed['category'] = $this->get_category_from_topic($topic);
            return $parsed;
        }

        return null;
    }

    /**
     * Build prompt for Claude
     */
    private function build_claude_prompt($topic) {
        return "You are MENA Careers, a career intelligence platform for experienced professionals targeting private equity, growth equity, and adjacent buy-side roles.

Generate a brief career insight for today's market briefing email.

Requirements:
- Provide a compelling title (5-10 words)
- Write 80-120 words of actionable advice
- Focus on: {$topic}
- Professional but warm tone
- Include one specific, implementable tip
- Reference current market conditions when relevant
- Write for associates, senior associates, VPs, directors, principals, partners, operating partners, and experienced investment professionals rather than students or first-job candidates
- Prioritise advice on deal judgement, leadership visibility, portfolio thinking, stakeholder management, hiring signals, team-building, market credibility, or execution quality where relevant

Format your response exactly like this:
Title: [Your title here]
Content: [Your content here]

Do NOT include:
- Generic platitudes or clichés
- Overly promotional language
- References to specific companies or individuals
- Salary figures or compensation specifics
- Emojis or casual language
- Graduate, internship, or campus-recruiting language unless the topic explicitly requires contrast";
    }

    /**
     * Parse Claude's response into title and content
     */
    private function parse_claude_response($response) {
        $title = '';
        $content = '';

        // Try to extract title
        if (preg_match('/Title:\s*(.+?)(?=\n|Content:|$)/is', $response, $matches)) {
            $title = trim($matches[1]);
        }

        // Try to extract content
        if (preg_match('/Content:\s*(.+?)$/is', $response, $matches)) {
            $content = trim($matches[1]);
        }

        // Validate
        if (!empty($title) && !empty($content) && strlen($content) >= 50) {
            return [
                'title' => $title,
                'content' => $content
            ];
        }

        // If format didn't match, use entire response as content
        if (strlen($response) >= 50 && strlen($response) <= 600) {
            // Generate a title from first sentence
            $first_sentence = strtok($response, '.!?');
            if (strlen($first_sentence) <= 60) {
                return [
                    'title' => trim($first_sentence),
                    'content' => $response
                ];
            }
        }

        return null;
    }

    /**
     * Map topic to category
     */
    private function get_category_from_topic($topic) {
        $mappings = [
            'Career strategy' => 'career_strategy',
            'Technical skills' => 'skills',
            'Networking' => 'networking',
            'Interview' => 'interviews',
            'Market opportunities' => 'market_trends',
            'Leadership' => 'mindset',
            'Work-life' => 'mindset'
        ];

        foreach ($mappings as $keyword => $category) {
            if (stripos($topic, $keyword) !== false) {
                return $category;
            }
        }

        return 'career_strategy';
    }

    /**
     * Calculate days between two dates
     */
    private function get_days_between($start, $end) {
        $start_ts = strtotime($start);
        $end_ts = strtotime($end);

        if ($start_ts === false || $end_ts === false) {
            return 0;
        }

        return max(0, floor(($end_ts - $start_ts) / DAY_IN_SECONDS));
    }

    /**
     * Get stats for admin
     */
    public function get_stats() {
        $start_date = get_option('senna_career_advice_start_date', '');
        $days_elapsed = $start_date ? $this->get_days_between($start_date, date('Y-m-d')) : 0;
        $message_count = $this->library->get_message_count();

        return [
            'total_messages' => $message_count,
            'days_elapsed' => $days_elapsed,
            'current_index' => ($days_elapsed % $message_count) + 1,
            'cycles_completed' => floor($days_elapsed / $message_count),
            'start_date' => $start_date ?: 'Not started',
            'claude_enabled' => $this->should_use_claude(),
            'claude_api_configured' => !empty($this->claude_api_key),
            'using_claude' => $days_elapsed >= $message_count && $this->should_use_claude(),
            'last_sent_date' => get_option('senna_career_advice_last_date', 'Never'),
            'last_sent_index' => get_option('senna_career_advice_last_index', 0)
        ];
    }

    /**
     * Reset tracking (useful for testing)
     */
    public function reset_tracking() {
        delete_option('senna_career_advice_start_date');
        delete_option('senna_career_advice_last_date');
        delete_option('senna_career_advice_last_index');
        delete_transient('senna_career_advice_today');
    }

    /**
     * Enable/disable Claude generation
     */
    public function set_claude_enabled($enabled) {
        update_option('senna_career_advice_claude_enabled', (bool) $enabled);
    }

    /**
     * Get formatted advice for email (with category label)
     */
    public function get_formatted_advice_for_email() {
        $advice = $this->get_todays_advice();

        if (!$advice) {
            return null;
        }

        // Add category display name
        $category_names = [
            'career_strategy' => 'Career Strategy',
            'skills' => 'Skills & Development',
            'networking' => 'Networking',
            'interviews' => 'Interview Success',
            'market_trends' => 'Market Insights',
            'mindset' => 'Leadership & Mindset'
        ];

        $advice['category_display'] = $category_names[$advice['category']] ?? 'Career Insight';

        return $advice;
    }
}

// Initialize
SFFC_Career_Advice_Manager::get_instance();
