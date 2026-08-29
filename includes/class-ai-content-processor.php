<?php

/**
 * AI Content Processor
 * 
 * Intelligent content processing with Claude API integration
 * Handles rewriting, enhancement, and generation with full control
 * 
 * @package SennaCareers
 * @subpackage SEO
 * @since 3.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_AI_Content_Processor
{

    /**
     * Processing modes
     */
    const MODE_REWRITE = 'rewrite';
    const MODE_ENHANCE = 'enhance';
    const MODE_GENERATE = 'generate';
    const MODE_SYNTHESIZE = 'synthesize';
    const MODE_SUMMARIZE = 'summarize';

    /**
     * Content tones
     */
    const TONES = array(
        'professional' => 'Professional and authoritative',
        'analytical' => 'Data-driven and analytical',
        'conversational' => 'Approachable yet professional',
        'technical' => 'Highly technical and detailed',
        'strategic' => 'Strategic and forward-thinking',
        'educational' => 'Educational and informative'
    );

    /**
     * Writing styles
     */
    const STYLES = array(
        'news' => 'News article style with inverted pyramid structure',
        'analysis' => 'In-depth analysis with supporting data',
        'report' => 'Formal report with executive summary',
        'blog' => 'Engaging blog post with personality',
        'whitepaper' => 'Authoritative whitepaper style',
        'case_study' => 'Case study with problem-solution-results'
    );

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Claude API instance
     */
    private $claude_api = null;

    /**
     * Processing statistics
     */
    private $stats = array(
        'total_processed' => 0,
        'total_tokens' => 0,
        'total_time' => 0
    );

    /**
     * Get instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        // Initialize Claude API
        if (class_exists('SFFC_Claude_API_Manager')) {
            $this->claude_api = SFFC_Claude_API_Manager::get_instance();
        }

        // Background processing hooks
        add_action('sffc_process_content_queue', array($this, 'process_queue_batch'));

        // Schedule processing if not scheduled
        if (!wp_next_scheduled('sffc_process_content_queue')) {
            wp_schedule_event(time(), 'hourly', 'sffc_process_content_queue');
        }

        // AJAX handlers
        add_action('wp_ajax_sffc_process_content', array($this, 'ajax_process_content'));
        add_action('wp_ajax_sffc_test_ai_prompt', array($this, 'ajax_test_prompt'));
    }

    /**
     * Process content queue batch
     */
    public function process_queue_batch()
    {
        global $wpdb;

        // Get pending items from queue
        $queue_items = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}sffc_content_queue 
             WHERE status = 'pending' 
             AND queue_type = 'generation'
             ORDER BY priority DESC, created_at ASC 
             LIMIT 5"
        );

        foreach ($queue_items as $item) {
            $this->process_queue_item($item);

            // Rate limiting
            sleep(2);
        }
    }

    /**
     * Process single queue item
     */
    private function process_queue_item($item)
    {
        global $wpdb;

        // Mark as processing
        $wpdb->update(
            $wpdb->prefix . 'sffc_content_queue',
            array(
                'status' => 'processing',
                'started_at' => current_time('mysql'),
                'attempts' => $item->attempts + 1
            ),
            array('id' => $item->id)
        );

        try {
            // Parse task data
            $task_data = json_decode($item->task_data, true);

            // Get source articles
            $source_ids = explode(',', $item->source_ids);
            $sources = $this->get_source_articles($source_ids);

            // Generate article configuration
            $config = array(
                'sources' => $source_ids,
                'length' => $this->determine_length($item->target_length),
                'target_audience' => $item->target_audience,
                'target_location' => $item->target_location,
                'template_id' => $item->template_id,
                'focus_keyword' => $this->extract_focus_keyword($sources),
                'keywords' => $this->extract_keywords($sources)
            );

            // Use SEO Article Generator
            if (class_exists('SFFC_SEO_Article_Generator')) {
                $generator = SFFC_SEO_Article_Generator::get_instance();
                $result = $generator->generate_article($config);

                if ($result['success']) {
                    // Update queue item
                    $wpdb->update(
                        $wpdb->prefix . 'sffc_content_queue',
                        array(
                            'status' => 'completed',
                            'completed_at' => current_time('mysql'),
                            'result_id' => $result['article_id'],
                            'processing_time' => time() - strtotime($item->started_at)
                        ),
                        array('id' => $item->id)
                    );

                    // Mark source articles as processed
                    $this->mark_sources_processed($source_ids, $result['article_id']);

                    return true;
                } else {
                    throw new Exception($result['error'] ?? 'Generation failed');
                }
            } else {
                throw new Exception('Article generator not available');
            }
        } catch (Exception $e) {
            // Handle error
            $wpdb->update(
                $wpdb->prefix . 'sffc_content_queue',
                array(
                    'status' => ($item->attempts >= 3) ? 'failed' : 'pending',
                    'error_message' => $e->getMessage(),
                    'error_details' => json_encode(array(
                        'trace' => $e->getTraceAsString(),
                        'time' => current_time('mysql')
                    ))
                ),
                array('id' => $item->id)
            );

            return false;
        }
    }

    /**
     * Process content with AI
     */
    public function process_content($content, $mode, $options = array())
    {
        $defaults = array(
            'tone' => 'professional',
            'sub_tone' => 'insightful, educational',
            'style' => 'structured_analysis',
            'content_type' => 'long_form_article',
            'intent' => 'educate_and_engage',

            'target_audience' => 'finance_professionals',
            'audience_seniority' => 'junior_to_mid',
            'target_industry' => 'investment_and_private_markets',

            // 🌍 Multi-region support
            'target_locations' => [
                'united_kingdom',
                'france',
                'germany',
                'italy',
                'spain',
                'switzerland',
                'brazil',
                'united_states',
                'singapore',
                'hong_kong',
                'uae'
            ],
            'regional_variations' => [
                'british_english',
                'french',
                'german',
                'italian',
                'spanish',
                'portuguese',
            ],

            'word_count' => 1500,
            'heading_structure' => 'h2_h3_subsections',
            'include_summary' => true,
            'include_conclusion' => true,
            'include_bullet_points' => true,
            'include_examples' => true,

            'temperature' => 0.65,
            'top_p' => 0.9,

            'model' => 'claude-3-opus',
            'preserve_facts' => true,
            'add_data' => true,
            'improve_structure' => true,
            'verify_sources' => true,
            'formatting_guidelines' => 'markdown',

            'focus_keyword' => '',
            'secondary_keywords' => [],
            'meta_description' => '',
            'slug' => '',
            'internal_links' => [],
            'external_links' => [],

            'include_regulatory_context' => true,
            'include_statistics' => true,
            'include_trends' => true,
            'citation_style' => 'inline',
        );

        $options = wp_parse_args($options, $defaults);

        // Get appropriate prompt
        $prompt = $this->build_prompt($content, $mode, $options);

        // Call Claude API
        $response = $this->call_ai($prompt, $options);

        if ($response['success']) {
            // Post-process the content
            $processed = $this->post_process_content($response['content'], $options);

            // Update statistics
            $this->update_stats($response);

            return array(
                'success' => true,
                'content' => $processed,
                'metadata' => array(
                    'mode' => $mode,
                    'word_count' => str_word_count($processed),
                    'tokens_used' => $response['tokens'] ?? 0,
                    'processing_time' => $response['time'] ?? 0,
                    'model' => $options['model']
                )
            );
        }

        return array(
            'success' => false,
            'error' => $response['error'] ?? 'Processing failed'
        );
    }

    /**
     * Build AI prompt based on mode and options
     */
    private function build_prompt($content, $mode, $options)
    {
        $prompt = array();

        // System prompt
        $prompt['system'] = $this->get_system_prompt($mode, $options);

        // User prompt
        switch ($mode) {
            case self::MODE_REWRITE:
                $prompt['user'] = $this->build_rewrite_prompt($content, $options);
                break;

            case self::MODE_ENHANCE:
                $prompt['user'] = $this->build_enhance_prompt($content, $options);
                break;

            case self::MODE_GENERATE:
                $prompt['user'] = $this->build_generate_prompt($content, $options);
                break;

            case self::MODE_SYNTHESIZE:
                $prompt['user'] = $this->build_synthesize_prompt($content, $options);
                break;

            case self::MODE_SUMMARIZE:
                $prompt['user'] = $this->build_summarize_prompt($content, $options);
                break;

            default:
                $prompt['user'] = $content;
        }

        return $prompt;
    }

    /**
     * Get system prompt
     */
    private function get_system_prompt($mode, $options)
    {
        $audience = $options['target_audience'] ?? 'finance_professionals';
        $seniority = $options['audience_seniority'] ?? 'junior_to_mid';
        $locations = $options['target_locations'] ?? [$options['target_location'] ?? 'global'];
        $location_list = implode(', ', $locations);
        $tone = self::TONES[$options['tone']] ?? self::TONES['professional'];
        $style = self::STYLES[$options['style']] ?? self::STYLES['analysis'];
        $subTone = $options['sub_tone'] ?? 'insightful';
        $regional_variations = $options['regional_variations'] ?? [];
        $content_type = $options['content_type'] ?? 'long_form_article';
        $intent = $options['intent'] ?? 'educate_and_engage';

        $base = "You are an expert financial content strategist and writer. ";
        $base .= "Your task is to create {$content_type} aimed at {$audience} with {$seniority} experience. ";
        $base .= "The content should be relevant to professionals in {$location_list}. ";

        if (!empty($regional_variations)) {
            $base .= "Adjust language, examples, and references to reflect " . implode(', ', $regional_variations) . " linguistic and cultural nuances. ";
        }

        $base .= "Adopt a {$tone} and {$subTone} tone, using a {$style} style. ";
        $base .= "Structure the content with clear headings (H2/H3), concise paragraphs, bullet points where useful, and include practical examples. ";
        $base .= "Ensure the narrative flows logically and is engaging, data-informed, and actionable. ";
        $base .= "The intent of the content is to {$intent}. ";

        switch ($mode) {
            case self::MODE_REWRITE:
                $base .= "Your task is to rewrite existing content completely while preserving all key facts and insights. ";
                $base .= "Make it original, well-structured, SEO-optimized, and tailored for the specified audience and regions. ";
                $base .= "Improve readability, clarity, and engagement while maintaining factual integrity. ";
                break;

            case self::MODE_ENHANCE:
                $base .= "Your task is to enhance existing content. ";
                $base .= "Improve structure, depth, and flow. Add missing insights, refine arguments, and strengthen clarity and engagement. ";
                $base .= "Preserve the original message but elevate the quality to a professional, publish-ready standard. ";
                break;

            case self::MODE_GENERATE:
                $base .= "Your task is to generate comprehensive, original content from provided context. ";
                $base .= "Create an in-depth, data-supported piece that is insightful, accurate, and relevant to the audience and target locations. ";
                $base .= "Use clear structure, practical examples, regional perspectives, and ensure SEO best practices are followed. ";
                break;

            case self::MODE_SYNTHESIZE:
                $base .= "Your task is to synthesize multiple sources into a single, cohesive piece. ";
                $base .= "Identify key themes, reconcile differences, and produce a structured, insightful narrative. ";
                $base .= "Add original perspective, regional context, and ensure smooth logical flow. ";
                break;

            case self::MODE_SUMMARIZE:
                $base .= "Your task is to summarize content clearly and concisely. ";
                $base .= "Focus on the most important facts, insights, and implications. Use a structured, digestible format. ";
                break;

            default:
                $base .= "Your task is to produce high-quality, structured content that matches the specified audience and tone. ";
                break;
        }

        if (!empty($options['focus_keyword'])) {
            $base .= "Incorporate the focus keyword '{$options['focus_keyword']}' naturally throughout the piece for SEO purposes. ";
        }

        if (!empty($options['secondary_keywords'])) {
            $secondary_keywords = implode(', ', $options['secondary_keywords']);
            $base .= "Integrate these secondary keywords where relevant: {$secondary_keywords}. ";
        }

        if (!empty($options['formatting_guidelines'])) {
            $base .= "Follow {$options['formatting_guidelines']} formatting conventions. ";
        }

        if (!empty($options['include_summary']) || !empty($options['include_conclusion'])) {
            $base .= "Include a brief executive summary at the beginning and a clear, actionable conclusion at the end. ";
        }

        if ($options['preserve_facts'] ?? false) {
            $base .= "Preserve all factual information, statistics, and data accurately. Do not introduce inaccuracies. ";
        }

        if ($options['add_data'] ?? false) {
            $base .= "Where relevant, include supporting industry data, market trends, or case examples to enrich the content. ";
        }

        if ($options['include_regulatory_context'] ?? false) {
            $base .= "Incorporate relevant regulatory considerations or compliance context across different regions where appropriate. ";
        }

        if ($options['verify_sources'] ?? false) {
            $base .= "Ensure all data and facts are verifiable, using reputable financial and industry sources. ";
        }

        return $base;
    }



    /**
     * Build rewrite prompt
     */
    private function build_rewrite_prompt($content, $options)
    {
        $audience = $options['target_audience'] ?? 'finance_professionals';
        $seniority = $options['audience_seniority'] ?? 'junior_to_mid';
        $locations = $options['target_locations'] ?? [$options['target_location'] ?? 'global'];
        $location_list = implode(', ', $locations);
        $tone = $options['tone'] ?? 'professional';
        $subTone = $options['sub_tone'] ?? 'insightful';
        $style = $options['style'] ?? 'analysis';
        $regional_variations = $options['regional_variations'] ?? [];
        $word_count = $options['word_count'] ?? 1000;
        $focus_keyword = $options['focus_keyword'] ?? '';
        $secondary_keywords = !empty($options['secondary_keywords']) ? implode(', ', $options['secondary_keywords']) : '';

        $prompt = "Completely rewrite the following content:\n\n";
        $prompt .= $content . "\n\n";

        $prompt .= "Requirements:\n";
        $prompt .= "- Target audience: {$audience} ({$seniority} level)\n";
        $prompt .= "- Target locations: {$location_list}\n";

        if (!empty($regional_variations)) {
            $prompt .= "- Adjust language and examples for regional variations: " . implode(', ', $regional_variations) . "\n";
        }

        $prompt .= "- Desired tone: {$tone} ({$subTone})\n";
        $prompt .= "- Style: {$style}\n";
        $prompt .= "- Target word count: {$word_count} words\n";

        if (!empty($focus_keyword)) {
            $prompt .= "- Focus keyword: {$focus_keyword}\n";
        }

        if (!empty($secondary_keywords)) {
            $prompt .= "- Include these secondary keywords naturally: {$secondary_keywords}\n";
        }

        $prompt .= "- Completely restructure the content — do not follow the original sentence or paragraph order\n";
        $prompt .= "- Use different sentence structures, vocabulary, and transitions\n";
        $prompt .= "- Add unique insights, regional context, and relevant examples\n";
        $prompt .= "- Ensure originality — no plagiarism or direct copying\n";
        $prompt .= "- Optimize for SEO in a natural way, avoiding keyword stuffing\n";
        $prompt .= "- Format with clear H2/H3 headings, bullet points, and concise paragraphs\n";
        $prompt .= "- Maintain factual accuracy, data points, and regulatory relevance where applicable\n";

        if (!empty($options['include_summary']) || !empty($options['include_conclusion'])) {
            $prompt .= "- Include an executive summary at the beginning and a strong, actionable conclusion at the end\n";
        }

        if (!empty($options['add_data'])) {
            $prompt .= "- Where appropriate, enrich the content with relevant industry data, trends, or examples\n";
        }

        if (!empty($options['verify_sources'])) {
            $prompt .= "- Ensure that all facts and data are accurate and derived from reputable financial sources\n";
        }

        return $prompt;
    }

    /**
     * Build enhance prompt
     */
    private function build_enhance_prompt($content, $options)
    {
        $audience = $options['target_audience'] ?? 'finance_professionals';
        $seniority = $options['audience_seniority'] ?? 'junior_to_mid';
        $locations = $options['target_locations'] ?? [$options['target_location'] ?? 'global'];
        $location_list = implode(', ', $locations);
        $tone = $options['tone'] ?? 'professional';
        $subTone = $options['sub_tone'] ?? 'insightful';
        $style = $options['style'] ?? 'analysis';
        $regional_variations = $options['regional_variations'] ?? [];
        $word_count = $options['word_count'] ?? 1200;
        $focus_keyword = $options['focus_keyword'] ?? '';
        $secondary_keywords = !empty($options['secondary_keywords']) ? implode(', ', $options['secondary_keywords']) : '';

        $prompt = "Enhance the following content:\n\n";
        $prompt .= $content . "\n\n";

        $prompt .= "Enhancement requirements:\n";
        $prompt .= "- Audience: {$audience} ({$seniority} level)\n";
        $prompt .= "- Target locations: {$location_list}\n";

        if (!empty($regional_variations)) {
            $prompt .= "- Reflect regional linguistic and cultural nuances: " . implode(', ', $regional_variations) . "\n";
        }

        $prompt .= "- Desired tone: {$tone} ({$subTone})\n";
        $prompt .= "- Style: {$style}\n";
        $prompt .= "- Target length: {$word_count} words\n";

        if (!empty($focus_keyword)) {
            $prompt .= "- Optimize for focus keyword: {$focus_keyword}\n";
        }

        if (!empty($secondary_keywords)) {
            $prompt .= "- Naturally incorporate secondary keywords: {$secondary_keywords}\n";
        }

        $prompt .= "- Strengthen the overall structure with clear sections, logical flow, and descriptive H2/H3 headings\n";
        $prompt .= "- Craft a compelling introduction that clearly sets context and a strong, insightful conclusion\n";
        $prompt .= "- Improve transitions between paragraphs to ensure a cohesive narrative\n";
        $prompt .= "- Add relevant industry data, statistics, or case examples where useful\n";
        $prompt .= "- Integrate expert insights, analysis, or commentary to deepen the content\n";
        $prompt .= "- Optimize the content for readability and SEO, but avoid keyword stuffing\n";
        $prompt .= "- Maintain all original facts, data points, and regulatory references accurately\n";

        if (!empty($options['improve_structure'])) {
            $prompt .= "- Add subheadings approximately every 200–300 words\n";
            $prompt .= "- Use bullet points or numbered lists where appropriate\n";
            $prompt .= "- Include a 'Key Takeaways' section to summarize the main points\n";
        }

        if (!empty($options['include_summary'])) {
            $prompt .= "- Add a brief executive summary at the top if missing\n";
        }

        if (!empty($options['include_conclusion'])) {
            $prompt .= "- End with a clear, actionable conclusion\n";
        }

        if (!empty($options['add_data'])) {
            $prompt .= "- Enrich the piece with additional supporting data, trends, or insights where relevant\n";
        }

        if (!empty($options['verify_sources'])) {
            $prompt .= "- Ensure all facts and figures are accurate and sourced from reputable industry references\n";
        }

        return $prompt;
    }


    /**
     * Build generate prompt
     */
    private function build_generate_prompt($content, $options)
    {
        $audience = $options['target_audience'] ?? 'finance_professionals';
        $seniority = $options['audience_seniority'] ?? 'junior_to_mid';
        $locations = $options['target_locations'] ?? [$options['target_location'] ?? 'global'];
        $location_list = implode(', ', $locations);
        $tone = $options['tone'] ?? 'professional';
        $subTone = $options['sub_tone'] ?? 'insightful';
        $style = $options['style'] ?? 'analysis';
        $regional_variations = $options['regional_variations'] ?? [];
        $word_count = $options['word_count'] ?? 1500;
        $focus_keyword = $options['focus_keyword'] ?? '';
        $secondary_keywords = !empty($options['secondary_keywords']) ? implode(', ', $options['secondary_keywords']) : '';

        $prompt = "Generate a comprehensive, original article based on the information below:\n\n";
        $prompt .= $content . "\n\n";

        $prompt .= "Article generation requirements:\n";
        $prompt .= "- Target audience: {$audience} ({$seniority} level)\n";
        $prompt .= "- Target locations: {$location_list}\n";

        if (!empty($regional_variations)) {
            $prompt .= "- Adapt language, examples, and cultural references for these regions: " . implode(', ', $regional_variations) . "\n";
        }

        $prompt .= "- Desired tone: {$tone} ({$subTone})\n";
        $prompt .= "- Style: {$style}\n";
        $prompt .= "- Target length: {$word_count} words\n";

        if (!empty($focus_keyword)) {
            $prompt .= "- Focus keyword: {$focus_keyword}\n";
        }

        if (!empty($secondary_keywords)) {
            $prompt .= "- Include secondary keywords naturally where appropriate: {$secondary_keywords}\n";
        }

        $prompt .= "- Structure the article as follows:\n";
        $prompt .= "  * Executive summary (brief, 2–3 sentences)\n";
        $prompt .= "  * Introduction with clear context\n";
        $prompt .= "  * 4–6 main sections with descriptive H2/H3 headings\n";
        $prompt .= "  * Bullet points or lists where relevant\n";
        $prompt .= "  * A 'Key Takeaways' section summarizing main points\n";
        $prompt .= "  * A strong conclusion with actionable insights\n";

        $prompt .= "- Ensure logical flow and smooth transitions between sections\n";
        $prompt .= "- Provide clear explanations suitable for junior-to-mid finance professionals\n";
        $prompt .= "- Include relevant industry context, market data, and regulatory considerations for the regions\n";
        $prompt .= "- Integrate specific examples, case studies, or notable transactions to illustrate key points\n";
        $prompt .= "- Use credible data to support arguments, and include forward-looking perspectives or emerging trends\n";
        $prompt .= "- Maintain factual accuracy and avoid speculation without basis\n";
        $prompt .= "- Optimize the article for SEO without keyword stuffing\n";
        $prompt .= "- Format in markdown with proper headings, spacing, and lists for readability\n";
        $prompt .= "- Ensure the article is engaging, informative, and publication-ready\n";

        if (!empty($options['add_data'])) {
            $prompt .= "- Enrich the content with up-to-date industry data, statistics, or trend analysis where relevant\n";
        }

        if (!empty($options['verify_sources'])) {
            $prompt .= "- Ensure that all facts and data points are accurate and derived from reputable financial sources\n";
        }

        if (!empty($options['include_regulatory_context'])) {
            $prompt .= "- Where relevant, highlight regulatory frameworks or compliance differences across regions\n";
        }

        return $prompt;
    }


    /**
     * Build synthesize prompt
     */
    private function build_synthesize_prompt($content, $options)
    {
        $audience = $options['target_audience'] ?? 'finance_professionals';
        $seniority = $options['audience_seniority'] ?? 'junior_to_mid';
        $locations = $options['target_locations'] ?? [$options['target_location'] ?? 'global'];
        $location_list = implode(', ', $locations);
        $tone = $options['tone'] ?? 'professional';
        $subTone = $options['sub_tone'] ?? 'insightful';
        $style = $options['style'] ?? 'analysis';
        $regional_variations = $options['regional_variations'] ?? [];
        $word_count = $options['word_count'] ?? 1500;
        $focus_keyword = $options['focus_keyword'] ?? '';
        $secondary_keywords = !empty($options['secondary_keywords']) ? implode(', ', $options['secondary_keywords']) : '';

        $prompt = "Synthesize the following multiple sources into a single, cohesive, high-quality article:\n\n";
        $prompt .= $content . "\n\n";

        $prompt .= "Synthesis requirements:\n";
        $prompt .= "- Target audience: {$audience} ({$seniority} level)\n";
        $prompt .= "- Target locations: {$location_list}\n";

        if (!empty($regional_variations)) {
            $prompt .= "- Adapt language, examples, and cultural context for: " . implode(', ', $regional_variations) . "\n";
        }

        $prompt .= "- Desired tone: {$tone} ({$subTone})\n";
        $prompt .= "- Style: {$style}\n";
        $prompt .= "- Target length: {$word_count} words\n";

        if (!empty($focus_keyword)) {
            $prompt .= "- Focus keyword: {$focus_keyword}\n";
        }

        if (!empty($secondary_keywords)) {
            $prompt .= "- Include secondary keywords naturally where relevant: {$secondary_keywords}\n";
        }

        $prompt .= "- Combine information from all sources into a **unified narrative** rather than summarizing each source separately\n";
        $prompt .= "- Identify and reconcile conflicting information, ensuring a balanced and accurate representation\n";
        $prompt .= "- Add **connecting analysis** and context between sources to explain relationships and transitions\n";
        $prompt .= "- Highlight **patterns, trends, and key themes** emerging across sources\n";
        $prompt .= "- Use data, examples, and case studies from the sources to support your analysis\n";
        $prompt .= "- Maintain factual accuracy and cite information implicitly from the sources\n";

        $prompt .= "- Structure the article as follows:\n";
        $prompt .= "  * Executive summary (2–3 sentences)\n";
        $prompt .= "  * Introduction with synthesis objective and context\n";
        $prompt .= "  * 4–6 logically structured sections with descriptive headings\n";
        $prompt .= "  * Bullet points or lists where useful\n";
        $prompt .= "  * Key Takeaways section summarizing key insights\n";
        $prompt .= "  * Conclusion with actionable insights and forward-looking perspective\n";

        $prompt .= "- Ensure smooth transitions and logical flow throughout\n";
        $prompt .= "- Provide clear explanations appropriate for a junior-to-mid finance audience\n";
        $prompt .= "- Optimize for SEO naturally, without keyword stuffing\n";
        $prompt .= "- Format the article in markdown with proper headings and spacing\n";

        if (!empty($options['add_data'])) {
            $prompt .= "- Where relevant, enrich the synthesis with additional industry data, trends, or context to strengthen the analysis\n";
        }

        if (!empty($options['verify_sources'])) {
            $prompt .= "- Ensure all data points and facts are accurate and supported by reputable sources\n";
        }

        if (!empty($options['include_regulatory_context'])) {
            $prompt .= "- Highlight regulatory or legal differences across regions where relevant to the topic\n";
        }

        return $prompt;
    }


    /**
     * Build summarize prompt
     */
    private function build_summarize_prompt($content, $options)
    {
        $audience = $options['target_audience'] ?? 'finance_professionals';
        $seniority = $options['audience_seniority'] ?? 'junior_to_mid';
        $locations = $options['target_locations'] ?? [$options['target_location'] ?? 'global'];
        $location_list = implode(', ', $locations);
        $tone = $options['tone'] ?? 'professional';
        $subTone = $options['sub_tone'] ?? 'insightful';
        $style = $options['style'] ?? 'analysis';
        $regional_variations = $options['regional_variations'] ?? [];
        $word_count = min(300, $options['word_count'] ?? 300);
        $focus_keyword = $options['focus_keyword'] ?? '';
        $secondary_keywords = !empty($options['secondary_keywords']) ? implode(', ', $options['secondary_keywords']) : '';

        $prompt = "Create a clear, professional summary of the following content:\n\n";
        $prompt .= $content . "\n\n";

        $prompt .= "Summary requirements:\n";
        $prompt .= "- Target audience: {$audience} ({$seniority} level)\n";
        $prompt .= "- Target locations: {$location_list}\n";

        if (!empty($regional_variations)) {
            $prompt .= "- Adjust language and examples for: " . implode(', ', $regional_variations) . "\n";
        }

        $prompt .= "- Desired tone: {$tone} ({$subTone})\n";
        $prompt .= "- Style: {$style}\n";
        $prompt .= "- Length: approximately {$word_count} words\n";

        if (!empty($focus_keyword)) {
            $prompt .= "- Integrate focus keyword naturally: {$focus_keyword}\n";
        }

        if (!empty($secondary_keywords)) {
            $prompt .= "- Include secondary keywords where relevant: {$secondary_keywords}\n";
        }

        $prompt .= "- Capture and concisely present all **key points and arguments** from the content\n";
        $prompt .= "- Use **clear, concise language** suitable for junior-to-mid finance professionals\n";
        $prompt .= "- Maintain **factual accuracy** throughout the summary\n";
        $prompt .= "- Highlight the **most important data, figures, or statistics** to support the main points\n";
        $prompt .= "- Include **main conclusions or takeaways**, presented succinctly\n";
        $prompt .= "- Use bullet points or short paragraphs where appropriate to increase readability\n";
        $prompt .= "- Ensure the summary has a logical flow, starting with a brief context or executive statement\n";
        $prompt .= "- Optimize for SEO subtly without keyword stuffing\n";

        if (!empty($options['verify_sources'])) {
            $prompt .= "- Ensure all key data points are accurate and consistent with reputable sources\n";
        }

        if (!empty($options['include_regulatory_context'])) {
            $prompt .= "- Include any essential regulatory or regional nuances where relevant\n";
        }

        return $prompt;
    }


    /**
     * Call AI API
     */
    private function call_ai($prompt, $options)
    {
        $start_time = microtime(true);

        if (!$this->claude_api) {
            // Check if we should use local API
            if (get_option('sffc_use_local_ai', false)) {
                return $this->call_local_ai($prompt, $options);
            }

            return array(
                'success' => false,
                'error' => 'AI API not configured'
            );
        }

        try {
            $response = $this->claude_api->send_message(
                $prompt['system'],
                $prompt['user'],
                array(
                    'model' => $options['model'],
                    'temperature' => $options['temperature'],
                    'max_tokens' => min($options['word_count'] * 2, 4000)
                )
            );

            if ($response['success']) {
                return array(
                    'success' => true,
                    'content' => $response['content'],
                    'tokens' => $response['usage']['total_tokens'] ?? 0,
                    'time' => microtime(true) - $start_time
                );
            }

            return array(
                'success' => false,
                'error' => $response['error'] ?? 'AI processing failed'
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Post-process AI content
     */
    private function post_process_content($content, $options)
    {
        // Clean up any AI artifacts
        $content = $this->clean_ai_artifacts($content);

        // Ensure proper HTML formatting
        $content = $this->format_html($content);

        // Optimize keyword density
        if (!empty($options['focus_keyword'])) {
            $content = $this->optimize_keyword_density($content, $options['focus_keyword']);
        }

        // Add internal link placeholders
        $content = $this->add_link_placeholders($content);

        // Ensure target word count
        $content = $this->adjust_word_count($content, $options['word_count']);

        return $content;
    }

    /**
     * Clean AI artifacts
     */
    private function clean_ai_artifacts($content)
    {
        // Remove any AI disclaimers
        $patterns = array(
            '/As an AI.{0,50}?I cannot/i',
            '/I\'m an AI/i',
            '/\[.*?\]/', // Remove brackets
            '/Note:.*?(\n|$)/i'
        );

        foreach ($patterns as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }

        // Clean up extra whitespace
        $content = preg_replace('/\n{3,}/', "\n\n", $content);

        return trim($content);
    }

    /**
     * Format HTML
     */
    private function format_html($content)
    {
        // Normalize line endings
        $content = str_replace("\r\n", "\n", $content);

        // Trim excess spaces
        $content = trim($content);

        // Headings
        $content = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $content);
        $content = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $content);
        $content = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $content);

        // Bold (**bold**) and italic (*italic*)
        $content = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $content);
        $content = preg_replace('/\*(.*?)\*/s', '<em>$1</em>', $content);

        // Links [text](url)
        $content = preg_replace('/\[(.*?)\]\((https?:\/\/[^\s)]+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', $content);

        // Blockquotes
        $content = preg_replace('/^> (.*?)$/m', '<blockquote>$1</blockquote>', $content);

        // Horizontal rules (--- or ***)
        $content = preg_replace('/^(-{3,}|\*{3,})$/m', '<hr>', $content);

        // Unordered lists (* item)
        $content = preg_replace('/^\* (.*?)$/m', '<li>$1</li>', $content);
        $content = preg_replace_callback('/(<li>.*?<\/li>\n?)+/s', function ($matches) {
            return "<ul>\n" . trim($matches[0]) . "\n</ul>";
        }, $content);

        // Ordered lists (1. item)
        $content = preg_replace('/^\d+\. (.*?)$/m', '<li>$1</li>', $content);
        $content = preg_replace_callback('/(<li>.*?<\/li>\n?)+/s', function ($matches) {
            // Avoid wrapping unordered list twice
            if (strpos($matches[0], '<ul>') !== false) {
                return $matches[0];
            }
            return "<ol>\n" . trim($matches[0]) . "\n</ol>";
        }, $content);

        // Code blocks (```...```)
        $content = preg_replace_callback('/```(.*?)```/s', function ($matches) {
            $code = htmlspecialchars(trim($matches[1]), ENT_QUOTES, 'UTF-8');
            return "<pre><code>{$code}</code></pre>";
        }, $content);

        // Paragraph wrapping
        $lines = preg_split('/\n\s*\n/', $content);
        $formatted = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line) {
                continue;
            }

            // Don't wrap block-level elements
            if (preg_match('/^<(h\d|ul|ol|li|blockquote|pre|hr)/i', $line)) {
                $formatted[] = $line;
            } else {
                $formatted[] = "<p>{$line}</p>";
            }
        }

        return implode("\n\n", $formatted);
    }

    /**
     * Optimize keyword density
     */
    private function optimize_keyword_density($content, $keyword)
    {
        if (empty($keyword)) {
            return $content;
        }

        // Normalize content and keyword
        $normalized_content = strtolower(strip_tags($content));
        $normalized_keyword = strtolower(trim($keyword));

        // Count words and keyword occurrences using regex with word boundaries
        $word_count = str_word_count($normalized_content);
        $keyword_count = preg_match_all('/\b' . preg_quote($normalized_keyword, '/') . '\b/i', $content);

        if ($word_count === 0) {
            return $content;
        }

        $current_density = ($keyword_count / $word_count) * 100;
        $min_density = 1.0;  // 1%
        $max_density = 2.5;  // 2.5%

        // If density already good, return unchanged
        if ($current_density >= $min_density && $current_density <= $max_density) {
            return $content;
        }

        // Split content by paragraph to insert strategically
        $sections = preg_split('/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        // Helper to check if section already contains keyword
        $containsKeyword = function ($text) use ($normalized_keyword) {
            return stripos($text, $normalized_keyword) !== false;
        };

        // Smart insertion points:
        // 1. After introduction (first 1–2 paragraphs)
        // 2. Somewhere in the middle
        // 3. In the conclusion (last paragraph)
        $insertion_phrases = [
            "In the context of {$keyword}, ",
            "When it comes to {$keyword}, ",
            "A key consideration related to {$keyword} is ",
        ];

        // Insert if too low
        if ($current_density < $min_density) {
            // Intro section
            if (isset($sections[0]) && !$containsKeyword($sections[0])) {
                $sections[0] = str_replace(
                    '<p>',
                    '<p>' . $insertion_phrases[array_rand($insertion_phrases)],
                    $sections[0]
                );
            }

            // Middle section
            $middle_index = intval(count($sections) / 2);
            if (isset($sections[$middle_index]) && !$containsKeyword($sections[$middle_index])) {
                $sections[$middle_index] = str_replace(
                    '<p>',
                    '<p>' . $insertion_phrases[array_rand($insertion_phrases)],
                    $sections[$middle_index]
                );
            }

            // Conclusion section (last non-empty paragraph)
            for ($i = count($sections) - 1; $i >= 0; $i--) {
                if (trim(strip_tags($sections[$i])) !== '') {
                    if (!$containsKeyword($sections[$i])) {
                        $sections[$i] = str_replace(
                            '<p>',
                            '<p>' . $insertion_phrases[array_rand($insertion_phrases)],
                            $sections[$i]
                        );
                    }
                    break;
                }
            }
        }

        // If too high, reduce by removing extra occurrences in the middle paragraphs
        if ($current_density > $max_density) {
            foreach ($sections as $i => $section) {
                if ($i > 1 && $i < count($sections) - 2) {
                    // Remove keyword only if it appears multiple times
                    $sections[$i] = preg_replace(
                        '/\b' . preg_quote($normalized_keyword, '/') . '\b/i',
                        '',
                        $sections[$i],
                        1 // Remove one occurrence at a time
                    );

                    // Recalculate density to avoid over-removal
                    $updated_count = preg_match_all('/\b' . preg_quote($normalized_keyword, '/') . '\b/i', implode('', $sections));
                    $updated_density = ($updated_count / $word_count) * 100;

                    if ($updated_density <= $max_density) {
                        break;
                    }
                }
            }
        }

        return implode('', $sections);
    }

    /**
     * Add internal link placeholders
     */
    private function add_link_placeholders($content)
    {
        // Define internal link opportunities (expandable)
        $link_opportunities = [
            'private equity' => '[INTERNAL_LINK:private-equity]',
            'pe' => '[INTERNAL_LINK:private-equity]',
            'mergers and acquisitions' => '[INTERNAL_LINK:mergers-acquisitions]',
            'm&a' => '[INTERNAL_LINK:mergers-acquisitions]',
            'investment' => '[INTERNAL_LINK:investment-strategies]',
            'investments' => '[INTERNAL_LINK:investment-strategies]',
            'investment strategy' => '[INTERNAL_LINK:investment-strategies]',
            'market analysis' => '[INTERNAL_LINK:market-analysis]',
            'ipo' => '[INTERNAL_LINK:ipo-guide]',
            'fundraising' => '[INTERNAL_LINK:fundraising]',
            'venture capital' => '[INTERNAL_LINK:venture-capital]',
            'asset management' => '[INTERNAL_LINK:asset-management]',
            'portfolio management' => '[INTERNAL_LINK:portfolio-management]',
            'due diligence' => '[INTERNAL_LINK:due-diligence]',
            'valuation' => '[INTERNAL_LINK:valuation-methods]',
            'exit strategy' => '[INTERNAL_LINK:exit-strategies]'
        ];

        // Avoid replacing inside already existing placeholders or HTML tags
        foreach ($link_opportunities as $term => $placeholder) {
            $pattern = '/\b' . preg_quote($term, '/') . '\b/i';

            $content = preg_replace_callback($pattern, function ($matches) use ($placeholder) {
                $match = $matches[0];

                // Don't replace inside [INTERNAL_LINK:...] or <a> tags
                if (preg_match('/\[(INTERNAL_LINK|EXTERNAL_LINK):[^\]]+\]/i', $match)) {
                    return $match;
                }
                if (preg_match('/<a [^>]+>.*' . preg_quote($match, '/') . '.*<\/a>/i', $match)) {
                    return $match;
                }

                // Replace only the first occurrence per term
                static $replaced_terms = [];
                $normalized_term = strtolower($match);

                if (isset($replaced_terms[$normalized_term])) {
                    return $match;
                }

                $replaced_terms[$normalized_term] = true;
                return $placeholder;
            }, $content, 1);
        }

        return $content;
    }

    /**
     * Adjust word count
     */
    private function adjust_word_count($content, $target)
    {
        $current = str_word_count(strip_tags($content));

        // Define acceptable tolerance range (e.g. ±10%)
        $min_target = $target * 0.9;
        $max_target = $target * 1.1;

        // If content is too short → intelligently expand
        if ($current < $min_target) {
            // Calculate how much more we need
            $missing_words = $target - $current;

            // Add a Key Takeaways section if missing
            if (strpos($content, '<h2>Key Takeaways</h2>') === false) {
                $content .= "\n\n<h2>Key Takeaways</h2>\n";
                $content .= "<p>This analysis underscores the importance of understanding current market trends and adapting strategies accordingly. ";
                $content .= "Professionals should continuously monitor developments to stay competitive in a rapidly evolving environment.</p>";
            }

            // Add an Outlook section if still short
            if ($missing_words > 100 && strpos($content, '<h2>Market Outlook</h2>') === false) {
                $content .= "\n\n<h2>Market Outlook</h2>\n";
                $content .= "<p>Looking ahead, market participants should be prepared for shifts driven by macroeconomic factors, regulatory changes, and evolving investment opportunities. ";
                $content .= "A forward-thinking approach will be critical in navigating uncertainty and identifying growth potential.</p>";
            }

            // Add a Strategic Considerations section if still significantly short
            if ($missing_words > 200 && strpos($content, '<h2>Strategic Considerations</h2>') === false) {
                $content .= "\n\n<h2>Strategic Considerations</h2>\n";
                $content .= "<p>To strengthen positioning, professionals can focus on deepening analytical capabilities, fostering partnerships, and leveraging technology. ";
                $content .= "These steps can enhance agility and drive long-term performance across investment cycles.</p>";
            }

            // Optional: repeat a key takeaway sentence to naturally lengthen without filler
            if ($missing_words > 300) {
                $content .= "<p>Ultimately, aligning strategy with market realities is essential for sustained success in dynamic financial environments.</p>";
            }
        }

        // If content is too long → trim gracefully
        if ($current > $max_target) {
            $allowed_words = $target;

            // Strip tags temporarily to count words accurately
            $text_only = strip_tags($content);
            $words = preg_split('/\s+/', $text_only);
            if (count($words) > $allowed_words) {
                $truncated_text = implode(' ', array_slice($words, 0, $allowed_words)) . '…';

                // Re-wrap truncated text into basic paragraphs to preserve structure
                $paragraphs = preg_split('/\n\s*\n/', $truncated_text);
                $content = '';
                foreach ($paragraphs as $p) {
                    $p = trim($p);
                    if ($p !== '') {
                        $content .= "<p>{$p}</p>\n\n";
                    }
                }
            }
        }

        return $content;
    }

    /**
     * Helper methods
     */

    private function get_source_articles($ids)
    {
        global $wpdb;

        if (empty($ids)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sffc_aggregated_news 
             WHERE id IN ($placeholders)",
            $ids
        ));
    }

    private function determine_length($word_count)
    {
        if ($word_count < 900) return 'short';
        if ($word_count < 1500) return 'standard';
        if ($word_count < 2500) return 'long';
        if ($word_count < 5000) return 'pillar';
        return 'ultimate';
    }

    private function extract_focus_keyword($sources)
    {
        if (empty($sources)) {
            return '';
        }

        // Extract most common company or deal type
        $keywords = array();

        foreach ($sources as $source) {
            if ($source->companies_involved) {
                $companies = json_decode($source->companies_involved, true);
                $keywords = array_merge($keywords, $companies);
            }

            if ($source->deal_type) {
                $keywords[] = $source->deal_type;
            }
        }

        if (empty($keywords)) {
            return '';
        }

        // Return most frequent
        $counts = array_count_values($keywords);
        arsort($counts);

        return key($counts);
    }

    private function extract_keywords($sources)
    {
        $keywords = array();

        foreach ($sources as $source) {
            if ($source->keyword_matches) {
                $matches = json_decode($source->keyword_matches, true);
                $keywords = array_merge($keywords, $matches);
            }

            if ($source->sector) {
                $keywords[] = $source->sector;
            }
        }

        return array_unique($keywords);
    }

    private function mark_sources_processed($source_ids, $article_id)
    {
        global $wpdb;

        if (empty($source_ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($source_ids), '%d'));

        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}sffc_aggregated_news 
             SET status = 'processed', 
                 article_id = %d,
                 ai_processed = 1 
             WHERE id IN ($placeholders)",
            array_merge(array($article_id), $source_ids)
        ));
    }

    private function update_stats($response)
    {
        $this->stats['total_processed']++;
        $this->stats['total_tokens'] += $response['tokens'] ?? 0;
        $this->stats['total_time'] += $response['time'] ?? 0;

        // Store in options for persistence
        update_option('sffc_ai_processor_stats', $this->stats);
    }

    /**
     * Call local AI (fallback)
     */
    private function call_local_ai($prompt, $options)
    {
        // This would integrate with local LLM if available
        // For now, return mock response

        return array(
            'success' => true,
            'content' => 'This is a placeholder response. In production, this would be generated by the AI model.',
            'tokens' => 100,
            'time' => 1.0
        );
    }

    /**
     * Get processing statistics
     */
    public function get_statistics()
    {
        $saved_stats = get_option('sffc_ai_processor_stats', array());

        return array_merge($this->stats, $saved_stats, array(
            'avg_tokens' => $this->stats['total_processed'] > 0
                ? round($this->stats['total_tokens'] / $this->stats['total_processed'])
                : 0,
            'avg_time' => $this->stats['total_processed'] > 0
                ? round($this->stats['total_time'] / $this->stats['total_processed'], 2)
                : 0
        ));
    }

    /**
     * AJAX handler for content processing
     */
    public function ajax_process_content()
    {
        check_ajax_referer('sffc_ajax_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $content = wp_unslash($_POST['content'] ?? '');
        $mode = sanitize_text_field($_POST['mode'] ?? self::MODE_ENHANCE);
        $options = array(
            'tone' => sanitize_text_field($_POST['tone'] ?? 'professional'),
            'style' => sanitize_text_field($_POST['style'] ?? 'analysis'),
            'word_count' => intval($_POST['word_count'] ?? 1500),
            'focus_keyword' => sanitize_text_field($_POST['focus_keyword'] ?? '')
        );

        $result = $this->process_content($content, $mode, $options);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX handler for testing prompts
     */
    public function ajax_test_prompt()
    {
        check_ajax_referer('sffc_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $prompt_id = intval($_POST['prompt_id'] ?? 0);
        $test_content = wp_unslash($_POST['test_content'] ?? '');

        if (!$prompt_id) {
            wp_send_json_error(array('message' => 'Invalid prompt ID'));
        }

        // Get prompt from database
        global $wpdb;
        $prompt = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sffc_ai_prompts WHERE id = %d",
            $prompt_id
        ));

        if (!$prompt) {
            wp_send_json_error(array('message' => 'Prompt not found'));
        }

        // Test the prompt
        $ai_prompt = array(
            'system' => $prompt->system_prompt,
            'user' => str_replace('{content}', $test_content, $prompt->user_prompt_template)
        );

        $response = $this->call_ai($ai_prompt, array(
            'model' => $prompt->model,
            'temperature' => $prompt->temperature,
            'word_count' => 500
        ));

        wp_send_json_success($response);
    }
}

// Initialize
SFFC_AI_Content_Processor::get_instance();
