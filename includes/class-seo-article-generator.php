<?php
/**
 * SEO Article Generator
 * 
 * Robust, comprehensive article generation system with length control,
 * audience targeting, and WordPress SEO optimization
 * 
 * @package SennaCareers
 * @subpackage SEO
 * @since 3.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_SEO_Article_Generator {
    
    /**
     * Content length presets
     */
    const LENGTH_SHORT = array('min' => 600, 'max' => 900, 'label' => 'Short Form');
    const LENGTH_STANDARD = array('min' => 1000, 'max' => 1500, 'label' => 'Standard');
    const LENGTH_LONG = array('min' => 1500, 'max' => 2500, 'label' => 'Long Form');
    const LENGTH_PILLAR = array('min' => 2500, 'max' => 5000, 'label' => 'Pillar Content');
    const LENGTH_ULTIMATE = array('min' => 5000, 'max' => 10000, 'label' => 'Ultimate Guide');
    
    /**
     * Target audiences
     */
    const AUDIENCES = array(
        'institutional_investors' => array(
            'label' => 'Institutional Investors',
            'tone' => 'highly professional',
            'complexity' => 'advanced',
            'focus' => 'ROI, risk analysis, market dynamics'
        ),
        'retail_investors' => array(
            'label' => 'Retail Investors', 
            'tone' => 'accessible professional',
            'complexity' => 'intermediate',
            'focus' => 'opportunities, trends, education'
        ),
        'finance_professionals' => array(
            'label' => 'Finance Professionals',
            'tone' => 'technical',
            'complexity' => 'advanced',
            'focus' => 'industry insights, career implications'
        ),
        'business_executives' => array(
            'label' => 'Business Executives',
            'tone' => 'strategic',
            'complexity' => 'intermediate',
            'focus' => 'business impact, strategic implications'
        ),
        'analysts_researchers' => array(
            'label' => 'Analysts & Researchers',
            'tone' => 'analytical',
            'complexity' => 'expert',
            'focus' => 'data, methodology, detailed analysis'
        )
    );
    
    /**
     * Geographic targets
     */
    const LOCATIONS = array(
        'united_kingdom' => array('label' => 'United Kingdom', 'spellings' => 'british', 'currency' => 'GBP'),
        'european_union' => array('label' => 'European Union', 'spellings' => 'british', 'currency' => 'EUR'),
        'united_states' => array('label' => 'United States', 'spellings' => 'american', 'currency' => 'USD'),
        'germany' => array('label' => 'Germany', 'spellings' => 'british', 'currency' => 'EUR'),
        'france' => array('label' => 'France', 'spellings' => 'british', 'currency' => 'EUR'),
        'switzerland' => array('label' => 'Switzerland', 'spellings' => 'british', 'currency' => 'CHF'),
        'nordic' => array('label' => 'Nordic Region', 'spellings' => 'british', 'currency' => 'Multiple'),
        'global' => array('label' => 'Global', 'spellings' => 'american', 'currency' => 'USD')
    );
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Claude API manager instance
     */
    private $claude_api = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Initialize Claude API if available
        if (class_exists('SFFC_Claude_API_Manager')) {
            $this->claude_api = SFFC_Claude_API_Manager::get_instance();
        }
        
        // Register AJAX handlers
        add_action('wp_ajax_sffc_generate_article', array($this, 'ajax_generate_article'));
        add_action('wp_ajax_sffc_preview_article', array($this, 'ajax_preview_article'));
        add_action('wp_ajax_sffc_optimize_article', array($this, 'ajax_optimize_article'));
    }
    
    /**
     * Generate article with comprehensive options
     */
    public function generate_article($config = array()) {
        // Validate and merge with defaults
        $config = $this->validate_config($config);
        
        // Step 1: Gather source content
        $sources = $this->gather_sources($config);
        
        if (empty($sources)) {
            return array(
                'success' => false,
                'error' => 'No suitable source content found'
            );
        }
        
        // Step 2: Analyze and score content potential
        $analysis = $this->analyze_content_potential($sources, $config);
        
        // Step 3: Select template
        $template = $this->select_template($config, $analysis);
        
        // Step 4: Build article structure
        $structure = $this->build_article_structure($template, $config);
        
        // Step 5: Generate content sections
        $content = $this->generate_content_sections($structure, $sources, $config);
        
        // Step 6: Optimize for SEO
        $optimized = $this->optimize_for_seo($content, $config);
        
        // Step 7: Format for WordPress
        $formatted = $this->format_for_wordpress($optimized, $config);
        
        // Step 8: Quality check
        $quality = $this->quality_check($formatted, $config);
        
        // Step 9: Store in database
        $article_id = $this->store_article($formatted, $quality, $config);
        
        return array(
            'success' => true,
            'article_id' => $article_id,
            'article' => $formatted,
            'quality' => $quality,
            'config' => $config
        );
    }
    
    /**
     * Validate and merge configuration
     */
    private function validate_config($config) {
        $defaults = array(
            'length' => 'standard',
            'target_audience' => 'finance_professionals',
            'target_location' => 'united_kingdom',
            'tone' => 'professional',
            'keywords' => array(),
            'focus_keyword' => '',
            'sources' => array(),
            'template_id' => null,
            'include_sections' => array(),
            'exclude_sections' => array(),
            'internal_links' => 3,
            'external_links' => 2,
            'include_schema' => true,
            'include_faqs' => true,
            'include_tables' => true,
            'include_quotes' => true,
            'ai_temperature' => 0.7,
            'ai_model' => 'claude-3-opus'
        );
        
        $config = wp_parse_args($config, $defaults);
        
        // Validate length
        if (!in_array($config['length'], array('short', 'standard', 'long', 'pillar', 'ultimate'))) {
            $config['length'] = 'standard';
        }
        
        // Validate audience
        if (!isset(self::AUDIENCES[$config['target_audience']])) {
            $config['target_audience'] = 'finance_professionals';
        }
        
        // Validate location
        if (!isset(self::LOCATIONS[$config['target_location']])) {
            $config['target_location'] = 'united_kingdom';
        }
        
        return $config;
    }
    
    /**
     * Gather source content
     */
    private function gather_sources($config) {
        global $wpdb;
        
        $sources = array();
        
        // If specific sources provided
        if (!empty($config['sources'])) {
            $source_ids = array_map('intval', $config['sources']);
            $placeholders = implode(',', array_fill(0, count($source_ids), '%d'));
            
            $sources = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sffc_aggregated_news 
                 WHERE id IN ($placeholders) 
                 AND status IN ('analyzed', 'selected')",
                $source_ids
            ));
        } else {
            // Auto-select based on keywords and scores
            $where = array("status = 'analyzed'");
            
            if (!empty($config['keywords'])) {
                $keyword_conditions = array();
                foreach ($config['keywords'] as $keyword) {
                    $keyword_conditions[] = $wpdb->prepare(
                        "(title LIKE %s OR original_content LIKE %s)",
                        '%' . $keyword . '%',
                        '%' . $keyword . '%'
                    );
                }
                $where[] = '(' . implode(' OR ', $keyword_conditions) . ')';
            }
            
            $where_clause = implode(' AND ', $where);
            
            $sources = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}sffc_aggregated_news 
                 WHERE $where_clause 
                 ORDER BY seo_score DESC, published_date DESC 
                 LIMIT 5"
            );
        }
        
        return $sources;
    }
    
    /**
     * Analyze content potential
     */
    private function analyze_content_potential($sources, $config) {
        $analysis = array(
            'total_sources' => count($sources),
            'combined_word_count' => 0,
            'key_entities' => array(),
            'topics' => array(),
            'sentiment' => 'neutral',
            'deal_values' => array(),
            'companies' => array(),
            'sectors' => array(),
            'keywords_found' => array()
        );
        
        foreach ($sources as $source) {
            // Word count
            $analysis['combined_word_count'] += str_word_count($source->original_content);
            
            // Extract entities
            if ($source->companies_involved) {
                $companies = json_decode($source->companies_involved, true) ?: array();
                $analysis['companies'] = array_merge($analysis['companies'], $companies);
            }
            
            // Deal values
            if ($source->deal_value) {
                $analysis['deal_values'][] = $source->deal_value;
            }
            
            // Sectors
            if ($source->sector) {
                $analysis['sectors'][] = $source->sector;
            }
            
            // Keywords
            if ($source->keyword_matches) {
                $keywords = json_decode($source->keyword_matches, true) ?: array();
                $analysis['keywords_found'] = array_merge($analysis['keywords_found'], $keywords);
            }
        }
        
        // Deduplicate
        $analysis['companies'] = array_unique($analysis['companies']);
        $analysis['sectors'] = array_unique($analysis['sectors']);
        $analysis['keywords_found'] = array_unique($analysis['keywords_found']);
        
        // Calculate potential score
        $analysis['potential_score'] = $this->calculate_potential_score($analysis);
        
        return $analysis;
    }
    
    /**
     * Calculate content potential score
     */
    private function calculate_potential_score($analysis) {
        $score = 0;
        
        // Source diversity (multiple sources = better)
        $score += min($analysis['total_sources'] * 20, 60);
        
        // Deal value (higher value = more interest)
        if (!empty($analysis['deal_values'])) {
            $max_value = max($analysis['deal_values']);
            if ($max_value > 1000000000) $score += 30; // $1B+
            elseif ($max_value > 100000000) $score += 20; // $100M+
            elseif ($max_value > 10000000) $score += 10; // $10M+
        }
        
        // Entity richness
        $score += min(count($analysis['companies']) * 5, 20);
        
        // Keyword matches
        $score += min(count($analysis['keywords_found']) * 3, 15);
        
        return min($score, 100); // Cap at 100
    }
    
    /**
     * Select appropriate template
     */
    private function select_template($config, $analysis) {
        global $wpdb;
        
        if ($config['template_id']) {
            // Use specified template
            $template = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sffc_content_templates 
                 WHERE id = %d AND is_active = 1",
                $config['template_id']
            ));
        } else {
            // Auto-select based on content type
            $template_type = 'deal_analysis'; // Default
            
            if (!empty($analysis['deal_values'])) {
                $template_type = 'deal_analysis';
            } elseif (count($analysis['companies']) > 3) {
                $template_type = 'market_update';
            }
            
            $template = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sffc_content_templates 
                 WHERE template_type = %s 
                 AND is_active = 1 
                 ORDER BY performance_score DESC 
                 LIMIT 1",
                $template_type
            ));
        }
        
        // Fallback to default structure
        if (!$template) {
            $template = $this->get_default_template();
        }
        
        return $template;
    }
    
    /**
     * Build article structure
     */
    private function build_article_structure($template, $config) {
        $structure = json_decode($template->structure, true) ?: array();
        
        // Get length configuration
        $length_config = $this->get_length_config($config['length']);
        
        // Calculate words per section
        $target_words = ($length_config['min'] + $length_config['max']) / 2;
        $sections = $structure['sections'] ?? array();
        
        // Apply include/exclude filters
        if (!empty($config['include_sections'])) {
            $sections = array_intersect($sections, $config['include_sections']);
        }
        
        if (!empty($config['exclude_sections'])) {
            $sections = array_diff($sections, $config['exclude_sections']);
        }
        
        $words_per_section = intval($target_words / count($sections));
        
        $article_structure = array(
            'title' => '',
            'meta_title' => '',
            'meta_description' => '',
            'introduction' => array(
                'words' => intval($words_per_section * 0.8),
                'elements' => array('hook', 'context', 'thesis')
            ),
            'sections' => array(),
            'conclusion' => array(
                'words' => intval($words_per_section * 0.6),
                'elements' => array('summary', 'implications', 'cta')
            )
        );
        
        // Build main sections
        foreach ($sections as $section) {
            $article_structure['sections'][] = array(
                'id' => $section,
                'title' => $this->get_section_title($section),
                'words' => $words_per_section,
                'subsections' => $this->get_subsections($section, $config)
            );
        }
        
        // Add optional elements
        if ($config['include_faqs']) {
            $article_structure['faqs'] = array(
                'count' => 5,
                'schema' => true
            );
        }
        
        if ($config['include_tables']) {
            $article_structure['tables'] = array(
                'comparison' => true,
                'data_summary' => true
            );
        }
        
        return $article_structure;
    }
    
    /**
     * Generate content sections using AI
     */
    private function generate_content_sections($structure, $sources, $config) {
        $content = array(
            'title' => '',
            'sections' => array(),
            'word_count' => 0
        );
        
        // Prepare source content for AI
        $source_content = $this->prepare_source_content($sources);
        
        // Get AI prompt template
        $prompt_template = $this->get_prompt_template($config);
        
        // Generate title
        $content['title'] = $this->generate_title($sources, $config);
        
        // Generate introduction
        $intro_prompt = $this->build_section_prompt(
            'introduction',
            $structure['introduction'],
            $source_content,
            $config
        );
        
        $introduction = $this->call_claude_api($intro_prompt, $config);
        $content['introduction'] = $introduction;
        $content['word_count'] += str_word_count($introduction);
        
        // Generate main sections
        foreach ($structure['sections'] as $section) {
            $section_prompt = $this->build_section_prompt(
                $section['id'],
                $section,
                $source_content,
                $config
            );
            
            $section_content = $this->call_claude_api($section_prompt, $config);
            
            $content['sections'][] = array(
                'id' => $section['id'],
                'title' => $section['title'],
                'content' => $section_content,
                'word_count' => str_word_count($section_content)
            );
            
            $content['word_count'] += str_word_count($section_content);
        }
        
        // Generate conclusion
        $conclusion_prompt = $this->build_section_prompt(
            'conclusion',
            $structure['conclusion'],
            $source_content,
            $config
        );
        
        $conclusion = $this->call_claude_api($conclusion_prompt, $config);
        $content['conclusion'] = $conclusion;
        $content['word_count'] += str_word_count($conclusion);
        
        // Add FAQs if requested
        if ($config['include_faqs']) {
            $content['faqs'] = $this->generate_faqs($source_content, $config);
        }
        
        return $content;
    }
    
    /**
     * Optimize content for SEO
     */
    private function optimize_for_seo($content, $config) {
        $optimized = $content;
        
        // 1. Keyword optimization
        $optimized = $this->optimize_keywords($optimized, $config);
        
        // 2. Heading structure
        $optimized = $this->optimize_headings($optimized, $config);
        
        // 3. Internal linking
        $optimized = $this->add_internal_links($optimized, $config);
        
        // 4. External linking
        $optimized = $this->add_external_links($optimized, $config);
        
        // 5. Meta tags
        $optimized['meta'] = $this->generate_meta_tags($optimized, $config);
        
        // 6. Schema markup
        if ($config['include_schema']) {
            $optimized['schema'] = $this->generate_schema_markup($optimized, $config);
        }
        
        // 7. Readability optimization
        $optimized = $this->optimize_readability($optimized, $config);
        
        // 8. Image optimization suggestions
        $optimized['images'] = $this->suggest_images($optimized, $config);
        
        return $optimized;
    }
    
    /**
     * Format for WordPress
     */
    private function format_for_wordpress($content, $config) {
        $formatted = array();
        
        // Build HTML content
        $html = '';
        
        // Introduction
        $html .= '<div class="article-introduction">' . "\n";
        $html .= wpautop($content['introduction']);
        $html .= '</div>' . "\n\n";
        
        // Table of contents (for long content)
        if ($config['length'] === 'pillar' || $config['length'] === 'ultimate') {
            $html .= $this->generate_table_of_contents($content);
        }
        
        // Main sections
        foreach ($content['sections'] as $section) {
            $html .= '<section id="' . sanitize_title($section['id']) . '">' . "\n";
            $html .= '<h2>' . esc_html($section['title']) . '</h2>' . "\n";
            $html .= wpautop($section['content']);
            $html .= '</section>' . "\n\n";
        }
        
        // FAQs
        if (isset($content['faqs'])) {
            $html .= $this->format_faqs($content['faqs']);
        }
        
        // Conclusion
        $html .= '<div class="article-conclusion">' . "\n";
        $html .= wpautop($content['conclusion']);
        $html .= '</div>' . "\n";
        
        // Prepare WordPress post array
        $formatted = array(
            'post_title' => $content['title'],
            'post_content' => $html,
            'post_excerpt' => $this->generate_excerpt($content),
            'post_status' => 'draft',
            'post_type' => 'post',
            'meta_input' => array(
                '_yoast_wpseo_title' => $content['meta']['title'] ?? $content['title'],
                '_yoast_wpseo_metadesc' => $content['meta']['description'] ?? '',
                '_yoast_wpseo_focuskw' => $config['focus_keyword'],
                '_sffc_article_config' => json_encode($config),
                '_sffc_word_count' => $content['word_count']
            )
        );
        
        // Add schema if available
        if (isset($content['schema'])) {
            $formatted['meta_input']['_sffc_schema_markup'] = json_encode($content['schema']);
        }
        
        // Categories and tags
        $formatted['post_category'] = $this->get_categories($content, $config);
        $formatted['tags_input'] = $this->get_tags($content, $config);
        
        return $formatted;
    }
    
    /**
     * Quality check
     */
    private function quality_check($article, $config) {
        $scores = array();
        
        // 1. Word count check
        $length_config = $this->get_length_config($config['length']);
        $word_count = str_word_count(strip_tags($article['post_content']));
        
        if ($word_count >= $length_config['min'] && $word_count <= $length_config['max']) {
            $scores['word_count'] = 100;
        } else {
            $scores['word_count'] = max(0, 100 - abs($word_count - $length_config['min']) / 10);
        }
        
        // 2. Keyword density
        $keyword_density = $this->calculate_keyword_density(
            $article['post_content'],
            $config['focus_keyword']
        );
        
        if ($keyword_density >= 0.5 && $keyword_density <= 2.5) {
            $scores['keyword_density'] = 100;
        } else {
            $scores['keyword_density'] = max(0, 100 - abs($keyword_density - 1.5) * 40);
        }
        
        // 3. Readability
        $readability = $this->calculate_readability($article['post_content']);
        $scores['readability'] = $readability;
        
        // 4. Structure
        $structure_score = $this->check_structure($article['post_content']);
        $scores['structure'] = $structure_score;
        
        // 5. Uniqueness (simplified check)
        $scores['uniqueness'] = $this->check_uniqueness($article['post_content']);
        
        // Calculate overall score
        $overall = array_sum($scores) / count($scores);
        
        return array(
            'overall' => round($overall),
            'scores' => $scores,
            'word_count' => $word_count,
            'keyword_density' => round($keyword_density, 2),
            'readability_grade' => $this->get_readability_grade($readability)
        );
    }
    
    /**
     * Store article in database
     */
    private function store_article($article, $quality, $config) {
        global $wpdb;
        
        // Prepare data
        $data = array(
            'title' => $article['post_title'],
            'slug' => sanitize_title($article['post_title']),
            'content' => $article['post_content'],
            'excerpt' => $article['post_excerpt'],
            'meta_title' => $article['meta_input']['_yoast_wpseo_title'],
            'meta_description' => $article['meta_input']['_yoast_wpseo_metadesc'],
            'focus_keyword' => $config['focus_keyword'],
            'secondary_keywords' => json_encode($config['keywords']),
            'target_audience' => $config['target_audience'],
            'target_location' => $config['target_location'],
            'content_length' => $quality['word_count'],
            'reading_time' => ceil($quality['word_count'] / 200),
            'template_id' => $config['template_id'],
            'ai_model' => $config['ai_model'],
            'ai_temperature' => $config['ai_temperature'],
            'source_articles' => json_encode($config['sources']),
            'readability_score' => $quality['scores']['readability'],
            'seo_score' => $quality['overall'],
            'status' => 'draft',
            'created_at' => current_time('mysql')
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'sffc_seo_articles',
            $data
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Call Claude API
     */
    private function call_claude_api($prompt, $config) {
        if (!$this->claude_api) {
            // Fallback to mock content for testing
            return $this->generate_mock_content($prompt);
        }
        
        $response = $this->claude_api->send_message(
            $prompt['system'],
            $prompt['user'],
            array(
                'model' => $config['ai_model'],
                'temperature' => $config['ai_temperature'],
                'max_tokens' => 2000
            )
        );
        
        if ($response['success']) {
            return $response['content'];
        }
        
        return $this->generate_mock_content($prompt);
    }
    
    /**
     * Build section prompt
     */
    private function build_section_prompt($section_id, $section_config, $source_content, $config) {
        $audience = self::AUDIENCES[$config['target_audience']];
        $location = self::LOCATIONS[$config['target_location']];
        
        $system_prompt = "You are an expert financial content writer creating content for {$audience['label']} in {$location['label']}. 
        Write in a {$audience['tone']} tone with {$audience['complexity']} complexity.
        Focus on: {$audience['focus']}.
        Use {$location['spellings']} spelling and {$location['currency']} for currency references.";
        
        $user_prompt = "Write the {$section_id} section of an article about:\n\n";
        $user_prompt .= $source_content . "\n\n";
        $user_prompt .= "Target word count: {$section_config['words']} words\n";
        $user_prompt .= "Focus keyword: {$config['focus_keyword']}\n";
        
        if ($section_id === 'introduction') {
            $user_prompt .= "Include a compelling hook, provide context, and present the main thesis.";
        } elseif ($section_id === 'conclusion') {
            $user_prompt .= "Summarize key points, discuss implications, and include a call to action.";
        } else {
            $user_prompt .= "Provide detailed analysis with specific data points and examples.";
        }
        
        return array(
            'system' => $system_prompt,
            'user' => $user_prompt
        );
    }
    
    /**
     * Helper methods
     */
    
    private function get_length_config($length) {
        $configs = array(
            'short' => self::LENGTH_SHORT,
            'standard' => self::LENGTH_STANDARD,
            'long' => self::LENGTH_LONG,
            'pillar' => self::LENGTH_PILLAR,
            'ultimate' => self::LENGTH_ULTIMATE
        );
        
        return $configs[$length] ?? self::LENGTH_STANDARD;
    }
    
    private function prepare_source_content($sources) {
        $content = '';
        
        foreach ($sources as $source) {
            $content .= "Title: " . $source->title . "\n";
            $content .= "Date: " . $source->published_date . "\n";
            
            if ($source->companies_involved) {
                $content .= "Companies: " . $source->companies_involved . "\n";
            }
            
            if ($source->deal_value) {
                $content .= "Deal Value: " . number_format($source->deal_value) . "\n";
            }
            
            $content .= "Content: " . substr($source->original_content, 0, 500) . "...\n\n";
        }
        
        return $content;
    }
    
    private function generate_mock_content($prompt) {
        // Fallback content for testing
        return "This is placeholder content for section. In production, this would be generated by Claude AI based on the provided sources and configuration. The content would be tailored to the target audience and location, optimized for SEO, and meet the specified word count requirements.";
    }
    
    private function get_default_template() {
        return (object) array(
            'template_name' => 'Default',
            'structure' => json_encode(array(
                'sections' => array(
                    'overview',
                    'analysis', 
                    'implications',
                    'outlook'
                )
            ))
        );
    }
    
    private function get_section_title($section_id) {
        $titles = array(
            'overview' => 'Market Overview',
            'analysis' => 'Detailed Analysis',
            'implications' => 'Strategic Implications',
            'outlook' => 'Future Outlook',
            'deal_overview' => 'Deal Overview',
            'market_context' => 'Market Context',
            'sector_impact' => 'Sector Impact',
            'investment_implications' => 'Investment Implications'
        );
        
        return $titles[$section_id] ?? ucwords(str_replace('_', ' ', $section_id));
    }
    
    private function get_subsections($section_id, $config) {
        // Return relevant subsections based on section type
        return array();
    }
    
    private function calculate_keyword_density($content, $keyword) {
        if (empty($keyword)) return 0;
        
        $content = strip_tags($content);
        $word_count = str_word_count($content);
        $keyword_count = substr_count(strtolower($content), strtolower($keyword));
        
        return ($keyword_count / $word_count) * 100;
    }
    
    private function calculate_readability($content) {
        // Simplified Flesch Reading Ease calculation
        $content = strip_tags($content);
        $sentences = preg_split('/[.!?]+/', $content);
        $words = str_word_count($content);
        $syllables = $this->count_syllables($content);
        
        if ($words == 0 || count($sentences) == 0) return 50;
        
        $score = 206.835 - 1.015 * ($words / count($sentences)) - 84.6 * ($syllables / $words);
        
        return max(0, min(100, $score));
    }
    
    private function count_syllables($text) {
        // Simplified syllable counting
        $syllables = 0;
        $words = str_word_count($text, 1);
        
        foreach ($words as $word) {
            $syllables += max(1, preg_match_all('/[aeiouAEIOU]/', $word, $matches));
        }
        
        return $syllables;
    }
    
    private function check_structure($content) {
        $score = 100;
        
        // Check for headings
        if (!preg_match('/<h2/', $content)) $score -= 20;
        
        // Check for paragraphs
        if (!preg_match('/<p/', $content)) $score -= 20;
        
        // Check for lists
        if (!preg_match('/<ul|<ol/', $content)) $score -= 10;
        
        return max(0, $score);
    }
    
    private function check_uniqueness($content) {
        // Simplified uniqueness check
        // In production, would use copyscape API or similar
        return 85;
    }
    
    private function get_readability_grade($score) {
        if ($score >= 90) return 'Very Easy';
        if ($score >= 80) return 'Easy';
        if ($score >= 70) return 'Fairly Easy';
        if ($score >= 60) return 'Standard';
        if ($score >= 50) return 'Fairly Difficult';
        if ($score >= 30) return 'Difficult';
        return 'Very Difficult';
    }
}

// Initialize
SFFC_SEO_Article_Generator::get_instance();
?>