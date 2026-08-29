<?php
/**
 * Dynamic Article Builder
 *
 * Generates comprehensive, journalism-quality articles using:
 * - Fact Layer: RSS headline data only (legally clean)
 * - Context Layer: Claude web research + knowledge (original analysis)
 * - Dynamic Templates: Section structure adapts to content type
 *
 * @package SennaCareers
 * @since 11.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Dynamic_Article_Builder
{
    private static $instance = null;

    /**
     * Content type definitions with their section templates
     */
    private $content_types = array();

    private function __construct()
    {
        $this->register_content_types();
    }

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register all content types and their dynamic section templates
     */
    private function register_content_types()
    {
        $this->content_types = array(

            // Investment/Funding
            'investment' => array(
                'label' => 'Investment',
                'keywords' => array(
                    'invest', 'backing', 'stake', 'funding', 'fundrais',
                    'series a', 'series b', 'series c', 'series d', 'series e',
                    'seed round', 'pre-seed', 'angel round', 'bridge round',
                    'raises', 'raised', 'secures funding', 'closes round',
                    'unicorn', 'valuation', 'capital injection', 'led by',
                    'participated in', 'growth equity', 'venture capital',
                ),
                'sections' => array(
                    array('key' => 'the_investment', 'title' => 'The Investment', 'type' => 'fact'),
                    array('key' => 'about_company', 'title' => 'About {company}', 'type' => 'research'),
                    array('key' => 'investor_profile', 'title' => 'The Investor', 'type' => 'research'),
                    array('key' => 'market_context', 'title' => 'Market Context', 'type' => 'analysis'),
                    array('key' => 'what_this_signals', 'title' => 'What This Signals', 'type' => 'analysis'),
                ),
            ),

            // Acquisition/M&A
            'acquisition' => array(
                'label' => 'Acquisition',
                'keywords' => array('acqui', '买', 'purchase', 'takeover', 'bid for', 'agrees to buy', 'set to buy', 'deal to buy'),
                'sections' => array(
                    array('key' => 'the_deal', 'title' => 'The Deal', 'type' => 'fact'),
                    array('key' => 'the_acquirer', 'title' => 'The Acquirer', 'type' => 'research'),
                    array('key' => 'the_target', 'title' => 'The Target', 'type' => 'research'),
                    array('key' => 'strategic_rationale', 'title' => 'Strategic Rationale', 'type' => 'analysis'),
                    array('key' => 'deal_implications', 'title' => 'Deal Implications', 'type' => 'analysis'),
                ),
            ),

            // Merger
            'merger' => array(
                'label' => 'Merger',
                'keywords' => array('merger', 'merge', 'combines with', 'joins forces', 'tie-up'),
                'sections' => array(
                    array('key' => 'the_merger', 'title' => 'The Merger', 'type' => 'fact'),
                    array('key' => 'company_one', 'title' => 'About {company1}', 'type' => 'research'),
                    array('key' => 'company_two', 'title' => 'About {company2}', 'type' => 'research'),
                    array('key' => 'combined_entity', 'title' => 'The Combined Entity', 'type' => 'analysis'),
                    array('key' => 'industry_impact', 'title' => 'Industry Impact', 'type' => 'analysis'),
                ),
            ),

            // IPO/Public Listing
            'ipo' => array(
                'label' => 'IPO',
                'keywords' => array('ipo', 'public listing', 'goes public', 'stock debut', 'market debut', 'files for ipo', 'plans ipo'),
                'sections' => array(
                    array('key' => 'the_ipo', 'title' => 'The IPO', 'type' => 'fact'),
                    array('key' => 'company_profile', 'title' => 'Company Profile', 'type' => 'research'),
                    array('key' => 'financials', 'title' => 'Financial Snapshot', 'type' => 'research'),
                    array('key' => 'market_conditions', 'title' => 'Market Conditions', 'type' => 'analysis'),
                    array('key' => 'investor_considerations', 'title' => 'Investor Considerations', 'type' => 'analysis'),
                ),
            ),

            // Earnings/Financial Results
            'earnings' => array(
                'label' => 'Earnings',
                'keywords' => array('earnings', 'quarter', 'q1', 'q2', 'q3', 'q4', 'revenue', 'profit', 'loss', 'results', 'beats estimates', 'misses estimates', 'ebitda', 'eps'),
                'sections' => array(
                    array('key' => 'the_numbers', 'title' => 'The Numbers', 'type' => 'fact'),
                    array('key' => 'performance_breakdown', 'title' => 'Performance Breakdown', 'type' => 'analysis'),
                    array('key' => 'segment_analysis', 'title' => 'Segment Analysis', 'type' => 'analysis'),
                    array('key' => 'market_reaction', 'title' => 'Market Reaction', 'type' => 'research'),
                    array('key' => 'forward_outlook', 'title' => 'Forward Outlook', 'type' => 'analysis'),
                ),
            ),

            // Leadership/Executive Changes
            'leadership' => array(
                'label' => 'Leadership Change',
                'keywords' => array('ceo', 'cfo', 'cto', 'coo', 'appoint', 'names', 'hire', 'depart', 'resign', 'steps down', 'executive', 'chief', 'president', 'board'),
                'sections' => array(
                    array('key' => 'the_appointment', 'title' => 'The Appointment', 'type' => 'fact'),
                    array('key' => 'executive_profile', 'title' => 'About {person}', 'type' => 'research'),
                    array('key' => 'company_context', 'title' => 'Company Context', 'type' => 'research'),
                    array('key' => 'strategic_direction', 'title' => 'Strategic Direction', 'type' => 'analysis'),
                    array('key' => 'market_implications', 'title' => 'Market Implications', 'type' => 'analysis'),
                ),
            ),

            // Partnership/Alliance
            'partnership' => array(
                'label' => 'Partnership',
                'keywords' => array('partner', 'alliance', 'collaboration', 'joint venture', 'teams up', 'joins with', 'agreement with'),
                'sections' => array(
                    array('key' => 'the_partnership', 'title' => 'The Partnership', 'type' => 'fact'),
                    array('key' => 'partner_one', 'title' => 'About {company1}', 'type' => 'research'),
                    array('key' => 'partner_two', 'title' => 'About {company2}', 'type' => 'research'),
                    array('key' => 'strategic_value', 'title' => 'Strategic Value', 'type' => 'analysis'),
                    array('key' => 'competitive_landscape', 'title' => 'Competitive Landscape', 'type' => 'analysis'),
                ),
            ),

            // Product/Launch
            'product' => array(
                'label' => 'Product Launch',
                'keywords' => array('launch', 'unveil', 'introduce', 'release', 'announce', 'new product', 'new service', 'rolls out'),
                'sections' => array(
                    array('key' => 'the_launch', 'title' => 'The Launch', 'type' => 'fact'),
                    array('key' => 'product_details', 'title' => 'Product Details', 'type' => 'research'),
                    array('key' => 'company_strategy', 'title' => 'Company Strategy', 'type' => 'research'),
                    array('key' => 'market_opportunity', 'title' => 'Market Opportunity', 'type' => 'analysis'),
                    array('key' => 'competitive_positioning', 'title' => 'Competitive Positioning', 'type' => 'analysis'),
                ),
            ),

            // Regulatory/Legal
            'regulatory' => array(
                'label' => 'Regulatory',
                'keywords' => array(
                    'regulat', 'sec', 'fca', 'doj', 'ftc', 'antitrust',
                    'investigation', 'probe', 'fine', 'penalty', 'compliance',
                    'ruling', 'lawsuit', 'settlement', 'settle', 'settles',
                    'pays', 'pay fine', 'money laundering', 'fraud', 'sanction',
                    'violation', 'enforcement', 'consent order', 'cease and desist',
                    'whistleblower', 'guilty', 'plea', 'indictment', 'charged',
                ),
                'sections' => array(
                    array('key' => 'the_ruling', 'title' => 'The Ruling', 'type' => 'fact'),
                    array('key' => 'regulatory_background', 'title' => 'Regulatory Background', 'type' => 'research'),
                    array('key' => 'affected_parties', 'title' => 'Who\'s Affected', 'type' => 'analysis'),
                    array('key' => 'industry_response', 'title' => 'Industry Response', 'type' => 'research'),
                    array('key' => 'broader_implications', 'title' => 'Broader Implications', 'type' => 'analysis'),
                ),
            ),

            // Restructuring/Layoffs
            'restructuring' => array(
                'label' => 'Restructuring',
                'keywords' => array('restructur', 'layoff', 'cut', 'job cuts', 'workforce reduction', 'downsiz', 'reorganiz', 'streamlin'),
                'sections' => array(
                    array('key' => 'the_restructuring', 'title' => 'The Restructuring', 'type' => 'fact'),
                    array('key' => 'company_situation', 'title' => 'Company Situation', 'type' => 'research'),
                    array('key' => 'strategic_rationale', 'title' => 'Strategic Rationale', 'type' => 'analysis'),
                    array('key' => 'employee_impact', 'title' => 'Employee Impact', 'type' => 'analysis'),
                    array('key' => 'market_outlook', 'title' => 'Market Outlook', 'type' => 'analysis'),
                ),
            ),

            // Expansion/Growth
            'expansion' => array(
                'label' => 'Expansion',
                'keywords' => array('expand', 'enter', 'new market', 'growth', 'opens', 'launches in', 'enters'),
                'sections' => array(
                    array('key' => 'the_expansion', 'title' => 'The Expansion', 'type' => 'fact'),
                    array('key' => 'company_profile', 'title' => 'Company Profile', 'type' => 'research'),
                    array('key' => 'target_market', 'title' => 'Target Market', 'type' => 'research'),
                    array('key' => 'growth_strategy', 'title' => 'Growth Strategy', 'type' => 'analysis'),
                    array('key' => 'competitive_dynamics', 'title' => 'Competitive Dynamics', 'type' => 'analysis'),
                ),
            ),

            // Exit/Sale
            'exit' => array(
                'label' => 'Exit',
                'keywords' => array('exit', 'sells stake', 'divest', 'disposal', 'sells business', 'offload'),
                'sections' => array(
                    array('key' => 'the_exit', 'title' => 'The Exit', 'type' => 'fact'),
                    array('key' => 'seller_profile', 'title' => 'The Seller', 'type' => 'research'),
                    array('key' => 'asset_overview', 'title' => 'The Asset', 'type' => 'research'),
                    array('key' => 'deal_rationale', 'title' => 'Deal Rationale', 'type' => 'analysis'),
                    array('key' => 'market_signal', 'title' => 'What This Signals', 'type' => 'analysis'),
                ),
            ),

            // Incident/Outage/Crisis
            'incident' => array(
                'label' => 'Incident',
                'keywords' => array(
                    'outage', 'disrupts', 'disruption', 'down', 'offline',
                    'incident', 'breach', 'hack', 'attack', 'failure',
                    'crash', 'crashed', 'widespread', 'affecting', 'impacted',
                    'issues', 'problems', 'emergency', 'crisis', 'chaos',
                    'shutdown', 'blackout', 'glitch', 'bug', 'vulnerability',
                    'cyberattack', 'ransomware', 'malware', 'data leak',
                ),
                'sections' => array(
                    array('key' => 'the_incident', 'title' => 'The Incident', 'type' => 'fact'),
                    array('key' => 'affected_services', 'title' => 'Affected Services', 'type' => 'research'),
                    array('key' => 'company_response', 'title' => 'Company Response', 'type' => 'research'),
                    array('key' => 'root_cause', 'title' => 'Root Cause Analysis', 'type' => 'analysis'),
                    array('key' => 'broader_impact', 'title' => 'Broader Impact', 'type' => 'analysis'),
                ),
            ),

            // Default/General News
            'general' => array(
                'label' => 'Market News',
                'keywords' => array(),
                'sections' => array(
                    array('key' => 'the_news', 'title' => 'The News', 'type' => 'fact'),
                    array('key' => 'background', 'title' => 'Background', 'type' => 'research'),
                    array('key' => 'key_players', 'title' => 'Key Players', 'type' => 'research'),
                    array('key' => 'market_context', 'title' => 'Market Context', 'type' => 'analysis'),
                    array('key' => 'looking_ahead', 'title' => 'Looking Ahead', 'type' => 'analysis'),
                ),
            ),
        );
    }

    /**
     * Build a comprehensive article from headline data
     *
     * @param array $headline RSS headline data (title, description, source, link, pubDate)
     * @param array $context Additional context (sector, region, companies, etc.)
     * @return array Article payload
     */
    public function build(array $headline, array $context = array())
    {
        // Step 1: Detect content type from headline
        $content_type = $this->detect_content_type($headline);
        $type_config = $this->content_types[$content_type];

        // Step 2: Extract entities from headline
        $entities = $this->extract_entities($headline, $context);

        // Step 3: Build section structure with dynamic titles
        $section_template = $this->build_section_template($type_config['sections'], $entities);

        // Step 4: Generate content via Claude with web research
        $sections = $this->generate_sections($headline, $section_template, $entities, $context);

        // Step 5: Compile final article
        return array(
            'title' => $headline['title'],
            'content' => $this->render_article_html($sections, $headline),
            'sections' => $sections,
            'excerpt' => $this->build_excerpt($sections),
            'content_type' => $content_type,
            'content_type_label' => $type_config['label'],
            'entities' => $entities,
            'meta_description' => $this->build_meta_description($sections),
            'focus_keywords' => $this->extract_keywords($headline, $entities),
            'method' => 'dynamic_research',
        );
    }

    /**
     * Detect content type from headline
     */
    public function detect_content_type(array $headline)
    {
        $text = strtolower($headline['title'] . ' ' . ($headline['description'] ?? ''));

        // Check each content type's keywords
        foreach ($this->content_types as $type => $config) {
            if ($type === 'general') continue; // Skip default

            foreach ($config['keywords'] as $keyword) {
                if (strpos($text, strtolower($keyword)) !== false) {
                    return $type;
                }
            }
        }

        return 'general';
    }

    /**
     * Extract named entities from headline
     */
    private function extract_entities(array $headline, array $context)
    {
        $entities = array(
            'companies' => array(),
            'people' => array(),
            'amounts' => array(),
            'percentages' => array(),
            'dates' => array(),
        );

        $text = $headline['title'] . ' ' . ($headline['description'] ?? '');

        // Extract monetary amounts
        preg_match_all('/[\$£€]\s*[\d,.]+\s*(?:billion|million|bn|m|B|M)?/i', $text, $amounts);
        $entities['amounts'] = $amounts[0] ?? array();

        // Extract percentages
        preg_match_all('/\d+(?:\.\d+)?\s*%/', $text, $percentages);
        $entities['percentages'] = $percentages[0] ?? array();

        // Use context for companies if available
        if (!empty($context['companies'])) {
            $entities['companies'] = (array) $context['companies'];
        }

        // Use context for people if available
        if (!empty($context['people'])) {
            $entities['people'] = (array) $context['people'];
        }

        // Extract from headline using common patterns
        // Company patterns (words before Inc, Corp, Ltd, LLC, etc.)
        preg_match_all('/([A-Z][a-zA-Z]+(?:\s+[A-Z][a-zA-Z]+)*)\s+(?:Inc|Corp|Ltd|LLC|PLC|Group|Holdings|Capital|Partners)/i', $text, $company_matches);
        if (!empty($company_matches[0])) {
            $entities['companies'] = array_merge($entities['companies'], $company_matches[0]);
        }

        $entities['companies'] = array_unique(array_filter($entities['companies']));
        $entities['people'] = array_unique(array_filter($entities['people']));

        return $entities;
    }

    /**
     * Build section template with dynamic titles
     */
    private function build_section_template(array $sections, array $entities)
    {
        $template = array();

        foreach ($sections as $section) {
            $title = $section['title'];

            // Replace placeholders with actual entity names
            if (!empty($entities['companies'])) {
                $title = str_replace('{company}', $entities['companies'][0] ?? 'the Company', $title);
                $title = str_replace('{company1}', $entities['companies'][0] ?? 'Company A', $title);
                $title = str_replace('{company2}', $entities['companies'][1] ?? 'Company B', $title);
            }

            if (!empty($entities['people'])) {
                $title = str_replace('{person}', $entities['people'][0] ?? 'the Executive', $title);
            }

            $template[] = array(
                'key' => $section['key'],
                'title' => $title,
                'type' => $section['type'],
            );
        }

        return $template;
    }

    /**
     * Generate all sections using Claude with web research
     */
    private function generate_sections(array $headline, array $section_template, array $entities, array $context)
    {
        if (!class_exists('SFFC_Claude_API_Manager')) {
            return $this->build_fallback_sections($headline, $section_template);
        }

        $claude = SFFC_Claude_API_Manager::get_instance();

        if (!$claude->is_available()) {
            return $this->build_fallback_sections($headline, $section_template);
        }

        // Build the comprehensive research prompt
        $prompt = $this->build_research_prompt($headline, $section_template, $entities, $context);

        // Send to Claude with web search enabled
        $result = $claude->send_message($prompt, array(), 'research');

        if (empty($result['success']) || empty($result['response'])) {
            return $this->build_fallback_sections($headline, $section_template);
        }

        // Parse Claude's response into sections
        return $this->parse_research_response($result['response'], $section_template);
    }

    /**
     * Build the research prompt for Claude
     */
    private function build_research_prompt(array $headline, array $section_template, array $entities, array $context)
    {
        $title = $headline['title'];
        $source = $headline['source'] ?? 'News Source';
        $description = $headline['description'] ?? '';
        $sector = $context['sector'] ?? '';
        $region = $context['region'] ?? '';

        $sections_list = "";
        foreach ($section_template as $i => $section) {
            $num = $i + 1;
            $sections_list .= "{$num}. **{$section['title']}** ({$section['type']})\n";
        }

        $companies_str = !empty($entities['companies']) ? implode(', ', $entities['companies']) : 'Not specified';
        $amounts_str = !empty($entities['amounts']) ? implode(', ', $entities['amounts']) : 'Not disclosed';

        $prompt = <<<PROMPT
You are a senior financial journalist at a premium publication. Your task is to write a comprehensive analysis article.

## THE NEWS (Fact Layer - This is all we know for certain)
**Headline:** {$title}
**Source:** {$source}
**Description:** {$description}
**Companies Mentioned:** {$companies_str}
**Amounts:** {$amounts_str}
**Sector:** {$sector}
**Region:** {$region}

## YOUR TASK
Research this topic and write a comprehensive article with the following sections:

{$sections_list}

## CRITICAL RULES

### For FACT sections:
- State ONLY what is confirmed in the headline/description above
- Do NOT add details, quotes, or specifics not provided
- Use phrases like "according to {$source}" for attribution
- If details are unknown, say "Terms were not disclosed" rather than inventing them

### For RESEARCH sections:
- Use your knowledge to provide background on companies, people, markets
- Search for relevant context about the entities mentioned
- Provide factual information about company history, market position, previous activities
- All information must be verifiable facts, not speculation

### For ANALYSIS sections:
- Provide thoughtful analysis of what this news means
- Discuss market implications, strategic rationale, competitive dynamics
- Use phrases like "This suggests...", "Market observers note...", "This positions..."
- Analysis should be logical inference, not invented facts

## FORMAT
Write each section with its header exactly as specified. Use this format:

## [Section Title]
[2-4 paragraphs of content]

## TONE
- Professional, authoritative, like Bloomberg or Financial Times
- No hype, no speculation presented as fact
- Specific where you have facts, measured where you're analyzing
- Short paragraphs, clear writing

## LENGTH
Each section: 100-200 words
Total article: 500-1000 words

Write the article now, following this exact section structure.
PROMPT;

        return $prompt;
    }

    /**
     * Parse Claude's response into sections
     */
    private function parse_research_response($response, array $section_template)
    {
        $sections = array();

        // Split by section headers
        $parts = preg_split('/##\s+/', $response);

        foreach ($section_template as $template) {
            $found = false;

            foreach ($parts as $part) {
                $part = trim($part);
                if (empty($part)) continue;

                // Check if this part matches the section title
                $lines = explode("\n", $part, 2);
                $header = trim($lines[0]);
                $content = isset($lines[1]) ? trim($lines[1]) : '';

                // Fuzzy match on title
                if (stripos($header, $template['title']) !== false ||
                    stripos($template['title'], $header) !== false ||
                    $this->titles_match($header, $template['title'])) {

                    $sections[] = array(
                        'key' => $template['key'],
                        'title' => $template['title'],
                        'type' => $template['type'],
                        'content' => $this->format_section_content($content),
                    );
                    $found = true;
                    break;
                }
            }

            // If section not found, skip it (don't add placeholder sections)
            // The fallback method will handle missing content
        }

        return $sections;
    }

    /**
     * Check if two titles match (fuzzy)
     */
    private function titles_match($a, $b)
    {
        $a = strtolower(preg_replace('/[^a-z0-9]/', '', $a));
        $b = strtolower(preg_replace('/[^a-z0-9]/', '', $b));

        similar_text($a, $b, $percent);
        return $percent > 70;
    }

    /**
     * Format section content as HTML
     */
    private function format_section_content($content)
    {
        if (empty($content)) {
            return '';
        }

        // Split into paragraphs
        $paragraphs = preg_split('/\n\n+/', trim($content));
        $html = '';

        foreach ($paragraphs as $para) {
            $para = trim($para);
            if (empty($para)) continue;

            // Skip lines that look like headers or instructions
            if (preg_match('/^(#{1,3}|Note:|Important:|Remember:)/i', $para)) {
                continue;
            }

            // Handle bullet points
            if (preg_match('/^[\*\-•]\s/', $para)) {
                $items = preg_split('/\n[\*\-•]\s*/', $para);
                $html .= '<ul>';
                foreach ($items as $item) {
                    $item = trim(preg_replace('/^[\*\-•]\s*/', '', $item));
                    if (!empty($item)) {
                        $html .= '<li>' . esc_html($item) . '</li>';
                    }
                }
                $html .= '</ul>';
            } else {
                $html .= '<p>' . esc_html($para) . '</p>';
            }
        }

        return $html;
    }

    /**
     * Build fallback sections when Claude is unavailable
     * Uses the actual RSS content instead of placeholder text
     */
    private function build_fallback_sections(array $headline, array $section_template)
    {
        $sections = array();
        $title = $headline['title'] ?? '';
        $description = $headline['description'] ?? '';
        $source = $headline['source'] ?? '';
        $link = $headline['link'] ?? '';
        $full_content = $headline['content'] ?? $headline['content:encoded'] ?? '';

        // Try to fetch actual content from the source URL if we don't have it
        if (empty($full_content) && !empty($link)) {
            $full_content = $this->fetch_article_content($link);
        }

        // Clean and prepare the content
        $full_content = wp_strip_all_tags($full_content);
        $description = wp_strip_all_tags($description);

        // Split content into paragraphs for distribution
        $paragraphs = array_filter(preg_split('/\n\n+/', $full_content));
        if (empty($paragraphs) && !empty($description)) {
            $paragraphs = array($description);
        }

        $para_index = 0;
        $total_paras = count($paragraphs);

        foreach ($section_template as $i => $template) {
            $content = '';

            if ($i === 0) {
                // First section (The News): use headline and description
                if (!empty($description)) {
                    $content = '<p>' . esc_html($description) . '</p>';
                } else {
                    $content = '<p>' . esc_html($title) . '</p>';
                }

                // Add first paragraph of content if available
                if ($total_paras > 0 && $para_index < $total_paras) {
                    $content .= '<p>' . esc_html($paragraphs[$para_index]) . '</p>';
                    $para_index++;
                }

                if (!empty($source)) {
                    $content .= '<p><em>Reported by ' . esc_html($source) . '</em></p>';
                }
            } else {
                // Distribute remaining content across sections
                $section_content = '';

                // Add 1-2 paragraphs per section
                $paras_for_section = min(2, max(1, ceil(($total_paras - $para_index) / (count($section_template) - $i))));

                for ($j = 0; $j < $paras_for_section && $para_index < $total_paras; $j++) {
                    $para = trim($paragraphs[$para_index]);
                    if (!empty($para) && strlen($para) > 20) {
                        $section_content .= '<p>' . esc_html($para) . '</p>';
                    }
                    $para_index++;
                }

                if (!empty($section_content)) {
                    $content = $section_content;
                } else {
                    // Only show this if we truly have no content
                    $content = '<p>For complete details, please refer to the <a href="' . esc_url($link) . '" target="_blank" rel="noopener">original article</a>.</p>';
                }
            }

            $sections[] = array(
                'key' => $template['key'],
                'title' => $template['title'],
                'type' => $template['type'],
                'content' => $content,
            );
        }

        return $sections;
    }

    /**
     * Fetch article content from URL
     */
    private function fetch_article_content($url)
    {
        if (empty($url)) {
            return '';
        }

        // Check cache first
        $cache_key = 'sffc_article_' . md5($url);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'user-agent' => 'Mozilla/5.0 (compatible; SennaBot/1.0)',
        ));

        if (is_wp_error($response)) {
            return '';
        }

        $html = wp_remote_retrieve_body($response);
        if (empty($html)) {
            return '';
        }

        // Extract main content
        $content = $this->extract_main_content($html);

        // Cache for 1 hour
        set_transient($cache_key, $content, HOUR_IN_SECONDS);

        return $content;
    }

    /**
     * Extract main content from HTML
     */
    private function extract_main_content($html)
    {
        // Remove scripts and styles
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);

        // Try to find article content
        $patterns = array(
            '/<article[^>]*>(.*?)<\/article>/is',
            '/<div[^>]*class="[^"]*article[^"]*"[^>]*>(.*?)<\/div>/is',
            '/<div[^>]*class="[^"]*content[^"]*"[^>]*>(.*?)<\/div>/is',
            '/<main[^>]*>(.*?)<\/main>/is',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $content = wp_strip_all_tags($matches[1]);
                $content = preg_replace('/\s+/', ' ', $content);
                $content = trim($content);
                if (strlen($content) > 200) {
                    return $content;
                }
            }
        }

        // Fallback: get all paragraph content
        preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $matches);
        if (!empty($matches[1])) {
            $paragraphs = array_map('wp_strip_all_tags', $matches[1]);
            $paragraphs = array_filter($paragraphs, function($p) {
                return strlen(trim($p)) > 50;
            });
            return implode("\n\n", array_slice($paragraphs, 0, 10));
        }

        return '';
    }

    /**
     * Render final article HTML
     */
    private function render_article_html(array $sections, array $headline)
    {
        $html = '<article class="sffc-dynamic-article">';

        foreach ($sections as $section) {
            $html .= '<section class="sffc-article-section sffc-section-' . esc_attr($section['type']) . '" data-section="' . esc_attr($section['key']) . '">';
            $html .= '<h2 class="sffc-section-title">' . esc_html($section['title']) . '</h2>';
            $html .= '<div class="sffc-section-content">' . $section['content'] . '</div>';
            $html .= '</section>';
        }

        // Source attribution
        if (!empty($headline['source']) || !empty($headline['link'])) {
            $html .= '<footer class="sffc-article-source">';
            $html .= '<p class="sffc-source-attribution">';
            if (!empty($headline['source'])) {
                $html .= 'Based on reporting from <strong>' . esc_html($headline['source']) . '</strong>';
            }
            if (!empty($headline['link'])) {
                $html .= ' · <a href="' . esc_url($headline['link']) . '" target="_blank" rel="noopener">Read original source</a>';
            }
            $html .= '</p>';
            $html .= '<p class="sffc-analysis-note">Analysis by MENA Careers AI</p>';
            $html .= '</footer>';
        }

        $html .= '</article>';

        return $html;
    }

    /**
     * Build excerpt from sections
     */
    private function build_excerpt(array $sections)
    {
        if (empty($sections)) {
            return '';
        }

        $text = '';
        foreach ($sections as $section) {
            $text .= ' ' . wp_strip_all_tags($section['content']);
            if (strlen($text) > 200) break;
        }

        return wp_trim_words(trim($text), 30);
    }

    /**
     * Build meta description
     */
    private function build_meta_description(array $sections)
    {
        if (empty($sections)) {
            return '';
        }

        $first_content = wp_strip_all_tags($sections[0]['content'] ?? '');
        return substr(trim($first_content), 0, 155);
    }

    /**
     * Extract keywords from headline and entities
     */
    private function extract_keywords(array $headline, array $entities)
    {
        $keywords = array();

        // Add companies
        if (!empty($entities['companies'])) {
            $keywords = array_merge($keywords, $entities['companies']);
        }

        // Add people
        if (!empty($entities['people'])) {
            $keywords = array_merge($keywords, $entities['people']);
        }

        // Extract key terms from title
        $title_words = str_word_count(strtolower($headline['title']), 1);
        $stopwords = array('the', 'a', 'an', 'in', 'on', 'at', 'to', 'for', 'of', 'and', 'or', 'but', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'shall', 'can', 'with', 'by', 'from', 'as', 'into', 'through', 'during', 'before', 'after', 'above', 'below', 'between', 'under', 'again', 'further', 'then', 'once');

        foreach ($title_words as $word) {
            if (strlen($word) > 3 && !in_array($word, $stopwords)) {
                $keywords[] = $word;
            }
        }

        return array_unique(array_slice($keywords, 0, 10));
    }

    /**
     * Get all registered content types
     */
    public function get_content_types()
    {
        return $this->content_types;
    }

    /**
     * Get section template for a content type
     */
    public function get_section_template($type)
    {
        return $this->content_types[$type]['sections'] ?? $this->content_types['general']['sections'];
    }
}
