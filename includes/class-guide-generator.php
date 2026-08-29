<?php
/**
 * Guide Generator
 *
 * Generates career guides using data-driven templates
 * Claude only fills narrative gaps - 80% of content is data-driven
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Guide_Generator
{
    private static $instance = null;
    private $author_id = 30912; // MENA Careers author
    private $template_library;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        add_action('sffc_generate_daily_guides', array($this, 'generate_daily_batch'));
    }

    /**
     * Get template library instance (lazy load)
     */
    private function get_template_library()
    {
        if (!$this->template_library && class_exists('SFFC_Guide_Template_Library')) {
            $this->template_library = SFFC_Guide_Template_Library::get_instance();
        }
        return $this->template_library;
    }

    /**
     * Generate daily batch of guides
     */
    public function generate_daily_batch($count = 60)
    {
        $crawler = SFFC_Topic_Idea_Crawler::get_instance();
        $topics = $crawler->get_pending_topics($count);

        if (empty($topics)) {
            $crawler->crawl_all_sources(15);
            $topics = $crawler->get_pending_topics($count);
        }

        $generated = 0;
        $processed_urls = array();

        foreach ($topics as $topic) {
            try {
                $post_id = $this->generate_guide($topic);
                if ($post_id) {
                    $generated++;
                    $processed_urls[] = $topic['original_url'];
                }
            } catch (Exception $e) {
                error_log('Guide generation failed for ' . $topic['topic_slug'] . ': ' . $e->getMessage());
            }

            // Rate limit
            if ($generated % 10 === 0) {
                sleep(3);
            }
        }

        $crawler->mark_topics_processed($processed_urls);

        return array(
            'generated' => $generated,
            'total'     => count($topics),
        );
    }

    /**
     * Generate a single guide from topic idea
     */
    public function generate_guide($topic)
    {
        // Determine template type and extract context
        $template_type = $this->determine_template_type($topic);
        $context = $this->extract_context_from_topic($topic);

        // Generate title
        $title = $this->generate_title($topic, $template_type, $context);

        // Check for duplicates
        if ($this->guide_exists($title)) {
            return false;
        }

        // Get the data-driven template (80% of content)
        $template_library = $this->get_template_library();
        $template_content = '';

        if ($template_library) {
            $template_content = $template_library->generate_guide($template_type, $context);
        }

        // Use Claude ONLY to fill narrative slots (20% of content)
        $final_content = $this->fill_narrative_slots($template_content, $title, $template_type, $context);

        if (empty($final_content)) {
            return false;
        }

        // Process shortcodes
        $final_content = $this->process_shortcodes($final_content);

        // Generate SEO metadata
        $seo_data = $this->generate_seo_metadata($title, $final_content, $template_type, $context);

        // Create the post
        $post_id = $this->create_guide_post($title, $final_content, $template_type, $context, $seo_data, $topic);

        return $post_id;
    }

    /**
     * Fill narrative slots with Claude-generated content
     * This is the ONLY place Claude is used - for filling gaps in data-driven templates
     */
    private function fill_narrative_slots($template_content, $title, $template_type, $context)
    {
        // Find all narrative slots
        preg_match_all(
            '/<div class="sffc-narrative-slot" data-slot="([^"]+)">(.*?)<\/div>/s',
            $template_content,
            $matches,
            PREG_SET_ORDER
        );

        if (empty($matches)) {
            // No slots to fill, return template as-is
            return $template_content;
        }

        // Extract slot placeholders
        $slots_to_fill = array();
        foreach ($matches as $match) {
            $slot_name = $match[1];
            $slot_html = $match[0];

            // Extract the placeholder instruction
            preg_match('/<p class="sffc-slot-placeholder">\[CLAUDE:(.*?)\]<\/p>/s', $match[2], $placeholder);
            $instruction = isset($placeholder[1]) ? trim($placeholder[1]) : '';

            if (!empty($instruction)) {
                $slots_to_fill[] = array(
                    'name'        => $slot_name,
                    'html'        => $slot_html,
                    'instruction' => $instruction,
                );
            }
        }

        if (empty($slots_to_fill)) {
            return $template_content;
        }

        // Build a single efficient prompt for all slots
        $narrative_content = $this->generate_narratives_via_claude($title, $template_type, $context, $slots_to_fill);

        if (empty($narrative_content)) {
            // Claude failed, remove slots and return template
            foreach ($slots_to_fill as $slot) {
                $template_content = str_replace($slot['html'], '', $template_content);
            }
            return $template_content;
        }

        // Replace slots with generated content
        foreach ($slots_to_fill as $index => $slot) {
            $slot_content = $narrative_content[$slot['name']] ?? '';

            if (!empty($slot_content)) {
                // Wrap in proper HTML
                $replacement = '<div class="sffc-narrative-content">' . $slot_content . '</div>';
                $template_content = str_replace($slot['html'], $replacement, $template_content);
            } else {
                // Remove empty slot
                $template_content = str_replace($slot['html'], '', $template_content);
            }
        }

        return $template_content;
    }

    /**
     * Generate narrative content via Claude
     * MINIMAL prompt - only fills specific gaps
     */
    private function generate_narratives_via_claude($title, $template_type, $context, $slots)
    {
        $api_key = get_option('sffc_claude_api_key');
        if (empty($api_key)) {
            error_log('Claude API key not configured');
            return array();
        }

        // Build efficient prompt
        $slot_instructions = '';
        foreach ($slots as $index => $slot) {
            $slot_instructions .= "\n\nSLOT " . ($index + 1) . " [{$slot['name']}]:\n{$slot['instruction']}";
        }

        $prompt = <<<PROMPT
You are writing SHORT narrative sections for a data-driven career guide. Most content is already generated from data - you're only filling 2-3 narrative gaps.

GUIDE: {$title}
TYPE: {$template_type}
CONTEXT: Company={$context['company']}, Role={$context['role']}, Location={$context['location']}, Industry={$context['industry']}

Fill these slots with CONCISE, EXPERT content. No fluff. Be specific and actionable.
{$slot_instructions}

RESPONSE FORMAT:
Return JSON only. No markdown, no explanation.
{
  "slot_name": "<html content>",
  "another_slot": "<html content>"
}

Use proper HTML: <p>, <ul>, <li>, <strong>, <h3> for subheadings.
Keep each slot to 100-200 words maximum.
PROMPT;

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'timeout' => 60,
            'headers' => array(
                'Content-Type'      => 'application/json',
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
            ),
            'body' => wp_json_encode(array(
                'model'      => 'claude-sonnet-4-6',
                'max_tokens' => 2000,
                'messages'   => array(
                    array('role' => 'user', 'content' => $prompt),
                ),
            )),
        ));

        if (is_wp_error($response)) {
            error_log('Claude API error: ' . $response->get_error_message());
            return array();
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['content'][0]['text'])) {
            error_log('Empty Claude response');
            return array();
        }

        $text = $body['content'][0]['text'];

        // Parse JSON response
        $narratives = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try to extract JSON from response
            preg_match('/\{[\s\S]*\}/', $text, $json_match);
            if (!empty($json_match[0])) {
                $narratives = json_decode($json_match[0], true);
            }
        }

        return is_array($narratives) ? $narratives : array();
    }

    /**
     * Process embedded shortcodes in content
     */
    private function process_shortcodes($content)
    {
        // Don't process now - let WordPress handle on display
        // Just validate they're properly formatted
        return $content;
    }

    /**
     * Determine best template type for topic
     */
    private function determine_template_type($topic)
    {
        $slug = strtolower($topic['topic_slug']);
        $ideas = $topic['topic_ideas'] ?? array();

        if (!empty($ideas)) {
            return $ideas[0]['type'] ?? 'industry-explainer';
        }

        // Pattern matching
        if (strpos($slug, 'interview') !== false || strpos($slug, 'questions') !== false) {
            return 'interview-guide';
        }
        if (strpos($slug, 'salary') !== false || strpos($slug, 'compensation') !== false || strpos($slug, 'pay') !== false) {
            return 'salary-guide';
        }
        if (strpos($slug, 'career') !== false || strpos($slug, 'path') !== false || strpos($slug, 'break-into') !== false) {
            return 'career-path';
        }
        if (strpos($slug, 'how-to') !== false || strpos($slug, 'tutorial') !== false || strpos($slug, 'guide') !== false) {
            return 'how-to-guide';
        }
        if (strpos($slug, 'working-at') !== false || $this->is_company_slug($slug)) {
            return 'firm-profile';
        }
        if (strpos($slug, 'what-is') !== false || strpos($slug, 'explained') !== false) {
            return 'industry-explainer';
        }
        if (strpos($slug, 'skills') !== false || strpos($slug, 'learn') !== false) {
            return 'skills-guide';
        }
        if (strpos($slug, 'market') !== false || strpos($slug, 'industry') !== false) {
            return 'market-analysis';
        }

        return 'industry-explainer';
    }

    /**
     * Check if slug is a company name
     */
    private function is_company_slug($slug)
    {
        $companies = array(
            'blackstone', 'kkr', 'apollo', 'carlyle', 'tpg', 'bain', 'warburg',
            'goldman', 'morgan-stanley', 'jpmorgan', 'citi', 'bofa', 'barclays',
            'ubs', 'credit-suisse', 'deutsche', 'hsbc', 'lazard', 'evercore',
            'centerview', 'moelis', 'pwp', 'greenhill', 'houlihan',
        );

        foreach ($companies as $company) {
            if (strpos($slug, $company) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract context from topic
     */
    private function extract_context_from_topic($topic)
    {
        $slug = $topic['topic_slug'];
        $words = str_replace(array('-', '_'), ' ', $slug);

        $context = array(
            'raw_topic' => $words,
            'company'   => null,
            'role'      => null,
            'location'  => null,
            'skill'     => null,
            'industry'  => null,
        );

        // Company extraction
        $companies = array(
            'blackstone', 'kkr', 'apollo', 'carlyle', 'tpg', 'bain capital', 'warburg pincus',
            'goldman sachs', 'morgan stanley', 'jpmorgan', 'jp morgan', 'citigroup', 'citi',
            'bank of america', 'bofa', 'barclays', 'ubs', 'credit suisse', 'deutsche bank',
            'hsbc', 'lazard', 'evercore', 'centerview', 'moelis', 'pwp', 'greenhill',
            'bridgewater', 'citadel', 'two sigma', 'de shaw', 'point72', 'millennium',
        );

        foreach ($companies as $company) {
            if (stripos($words, $company) !== false) {
                $context['company'] = ucwords($company);
                break;
            }
        }

        // Role extraction
        $roles = array('analyst', 'associate', 'vice president', 'vp', 'director', 'managing director', 'md', 'partner', 'principal');
        foreach ($roles as $role) {
            if (stripos($words, $role) !== false) {
                $context['role'] = ucwords($role);
                break;
            }
        }

        // Location extraction
        $locations = array(
            'new york', 'nyc', 'london', 'hong kong', 'singapore', 'dubai',
            'san francisco', 'chicago', 'boston', 'los angeles', 'toronto',
            'frankfurt', 'paris', 'tokyo', 'sydney', 'mumbai', 'shanghai',
        );
        foreach ($locations as $location) {
            if (stripos($words, $location) !== false) {
                $context['location'] = ucwords($location);
                break;
            }
        }

        // Industry extraction
        $industries = array(
            'private equity', 'pe', 'investment banking', 'ib', 'hedge fund', 'hf',
            'venture capital', 'vc', 'asset management', 'real estate', 'consulting',
        );
        foreach ($industries as $industry) {
            if (stripos($words, $industry) !== false) {
                $context['industry'] = ucwords($industry);
                break;
            }
        }

        // Skill extraction
        $skills = array(
            'lbo', 'dcf', 'financial modeling', 'valuation', 'excel', 'powerpoint',
            'pitchbook', 'merger model', 'm&a', 'due diligence', 'wacc', 'irr',
        );
        foreach ($skills as $skill) {
            if (stripos($words, $skill) !== false) {
                $context['skill'] = strtoupper($skill);
                break;
            }
        }

        return $context;
    }

    /**
     * Generate title for guide
     */
    private function generate_title($topic, $template_type, $context)
    {
        $ideas = $topic['topic_ideas'] ?? array();

        if (!empty($ideas)) {
            foreach ($ideas as $idea) {
                if ($idea['type'] === $template_type) {
                    return $idea['title'];
                }
            }
            return $ideas[0]['title'];
        }

        $year = date('Y');
        $raw = ucwords(str_replace(array('-', '_'), ' ', $topic['topic_slug']));

        switch ($template_type) {
            case 'interview-guide':
                $company = $context['company'] ?: $raw;
                return "{$company} Interview Questions & Answers ({$year})";

            case 'salary-guide':
                $role = $context['role'] ?: 'Finance';
                $location = $context['location'] ? " in {$context['location']}" : '';
                return "{$role} Salary Guide{$location} ({$year})";

            case 'career-path':
                $industry = $context['industry'] ?: 'Finance';
                return "How to Break Into {$industry} ({$year})";

            case 'how-to-guide':
                $skill = $context['skill'] ?: $raw;
                return "{$skill}: Complete Guide";

            case 'firm-profile':
                $company = $context['company'] ?: $raw;
                return "Working at {$company}: Culture & Compensation ({$year})";

            case 'industry-explainer':
                return "What is {$raw}? Complete Guide";

            case 'skills-guide':
                $skill = $context['skill'] ?: $raw;
                return "{$skill} Skills Guide for Finance";

            case 'market-analysis':
                return "{$raw}: Market Overview ({$year})";

            default:
                return "{$raw}: Complete Guide ({$year})";
        }
    }

    /**
     * Generate SEO metadata
     */
    private function generate_seo_metadata($title, $content, $template_type, $context)
    {
        // Extract first meaningful paragraph
        preg_match('/<p[^>]*>([^<]+)<\/p>/s', $content, $matches);
        $first_para = isset($matches[1]) ? strip_tags($matches[1]) : '';
        $description = substr($first_para, 0, 155);

        // Count words
        $word_count = str_word_count(strip_tags($content));
        $read_time = max(5, round($word_count / 200));

        // Extract FAQs for schema
        $faqs = array();
        preg_match_all('/<h3 class="sffc-faq-question">(.*?)<\/h3>\s*<p class="sffc-faq-answer">(.*?)<\/p>/s', $content, $faq_matches, PREG_SET_ORDER);
        foreach (array_slice($faq_matches, 0, 7) as $match) {
            $faqs[] = array(
                'question' => strip_tags($match[1]),
                'answer'   => strip_tags($match[2]),
            );
        }

        // Primary keyword
        $primary_keywords = array(
            'interview-guide'     => ($context['company'] ?: 'finance') . ' interview questions',
            'salary-guide'        => ($context['role'] ?: 'finance') . ' salary ' . ($context['location'] ?: ''),
            'career-path'         => 'how to break into ' . ($context['industry'] ?: 'finance'),
            'how-to-guide'        => ($context['skill'] ?: 'financial modeling') . ' guide',
            'firm-profile'        => 'working at ' . ($context['company'] ?: 'pe firm'),
            'industry-explainer'  => 'what is ' . ($context['raw_topic'] ?? 'finance'),
            'skills-guide'        => ($context['skill'] ?: 'finance') . ' skills',
            'market-analysis'     => ($context['raw_topic'] ?? 'finance') . ' market',
        );

        return array(
            'seo_title'          => substr($title, 0, 60),
            'seo_description'    => $description,
            'primary_keyword'    => trim($primary_keywords[$template_type] ?? $title),
            'secondary_keywords' => implode(', ', array_filter(array($context['company'], $context['role'], $context['industry'], $context['skill']))),
            'difficulty_level'   => 'intermediate',
            'read_time'          => $read_time,
            'word_count'         => $word_count,
            'faq_data'           => wp_json_encode($faqs),
        );
    }

    /**
     * Check if guide exists
     */
    private function guide_exists($title)
    {
        global $wpdb;

        $similar = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'sffc_career_insights'
             AND post_status IN ('publish', 'draft')
             AND post_title = %s",
            $title
        ));

        return !empty($similar);
    }

    /**
     * Create guide post
     */
    private function create_guide_post($title, $content, $template_type, $context, $seo_data, $topic)
    {
        $post_data = array(
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'draft',
            'post_type'    => 'sffc_career_insights',
            'post_author'  => $this->author_id,
            'post_excerpt' => $seo_data['seo_description'],
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            return false;
        }

        // Save meta
        update_post_meta($post_id, '_seo_title', $seo_data['seo_title']);
        update_post_meta($post_id, '_seo_description', $seo_data['seo_description']);
        update_post_meta($post_id, '_primary_keyword', $seo_data['primary_keyword']);
        update_post_meta($post_id, '_secondary_keywords', $seo_data['secondary_keywords']);
        update_post_meta($post_id, '_difficulty_level', $seo_data['difficulty_level']);
        update_post_meta($post_id, '_read_time', $seo_data['read_time']);
        update_post_meta($post_id, '_word_count', $seo_data['word_count']);
        update_post_meta($post_id, '_faq_data', $seo_data['faq_data']);
        update_post_meta($post_id, '_last_updated', current_time('Y-m-d'));
        update_post_meta($post_id, '_source_topic_url', $topic['original_url']);
        update_post_meta($post_id, '_generation_method', 'data-driven-template');
        update_post_meta($post_id, '_data_sources', 'World Bank, FRED, BLS, MENA Careers Finance Industry Data');

        // Set taxonomy terms
        wp_set_object_terms($post_id, $template_type, 'guide_type');

        if (!empty($context['industry'])) {
            wp_set_object_terms($post_id, sanitize_title($context['industry']), 'guide_industry');
        }

        if (!empty($context['location'])) {
            wp_set_object_terms($post_id, sanitize_title($context['location']), 'guide_region');
        }

        return $post_id;
    }

    /**
     * Get generation statistics
     */
    public function get_stats()
    {
        global $wpdb;

        $total = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'sffc_career_insights'"
        );

        $published = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'sffc_career_insights' AND post_status = 'publish'"
        );

        $draft = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'sffc_career_insights' AND post_status = 'draft'"
        );

        $today = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'sffc_career_insights' AND DATE(post_date) = %s",
            current_time('Y-m-d')
        ));

        return array(
            'total_guides'     => intval($total),
            'published_guides' => intval($published),
            'draft_guides'     => intval($draft),
            'generated_today'  => intval($today),
        );
    }
}

// Initialize
SFFC_Guide_Generator::get_instance();
