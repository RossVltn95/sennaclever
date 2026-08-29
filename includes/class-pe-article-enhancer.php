<?php

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_PE_Article_Enhancer
{
    private static $instance = null;
    private $http_timeout = 8;
    private $cache_ttl = 1800; // 30 minutes

    private function __construct()
    {
    }

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function build($type, array $headline, array $context = [])
    {
        $type = in_array($type, array('deal', 'news'), true) ? $type : 'news';
        $article_body = $this->fetch_article_body($headline['link'] ?? '');
        
        // Enrich context with additional variables for uniqueness
        $context = $this->enrich_context($context, $headline);

        // Try Claude API first if available
        $claude_content = $this->try_claude_enhancement($headline, $article_body, $context);
        
        if (!empty($claude_content)) {
            $sections = $this->parse_claude_response($claude_content, $type, $headline, $context);
            $method = 'claude_ai';
        } elseif (!empty($article_body)) {
            $sections = $this->build_sections_from_article($article_body, $type, $headline, $context);
            $method = 'remote_article';
        } else {
            $sections = $this->build_fallback_sections($type, $headline, $context);
            $method = 'fallback_template';
        }

        $title = $this->build_seo_title($type, $headline, $context);
        $content = $this->render_sections_html($sections, $headline);
        $excerpt = $this->build_excerpt($sections);
        $keywords = $this->derive_focus_keywords($headline, $context);
        $meta_description = $this->build_meta_description($sections);

        return array(
            'title' => $title,
            'content' => $content,
            'sections' => $sections,
            'excerpt' => $excerpt,
            'focus_keywords' => $keywords,
            'meta_description' => $meta_description,
            'method' => $method,
            'uniqueness_score' => $this->calculate_uniqueness_score($sections),
        );
    }

    private function fetch_article_body($url)
    {
        if (empty($url) || !function_exists('wp_remote_get')) {
            return '';
        }

        $cache_key = 'sffc_pe_article_' . md5($url);
        $cached = get_transient($cache_key);
        if (!empty($cached)) {
            return $cached;
        }

        $response = wp_remote_get($url, array(
            'timeout' => $this->http_timeout,
            'redirection' => 3,
            'user-agent' => $this->random_user_agent(),
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ),
        ));

        if (is_wp_error($response)) {
            return '';
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return '';
        }

        $article = $this->extract_article_content($body);
        if (!empty($article)) {
            set_transient($cache_key, $article, $this->cache_ttl);
        }

        return $article;
    }

    private function extract_article_content($html)
    {
        if (empty($html)) {
            return '';
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();

        if (!$loaded) {
            return '';
        }

        $xpath = new DOMXPath($dom);
        $queries = array(
            '//article',
            '//*[@role="main"]',
            '//*[contains(@class, "article-body")] | //*[contains(@class, "ArticleBody")]',
            '//*[contains(@class, "story-body")] | //*[contains(@class, "content-body")]'
        );

        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes && $nodes->length > 0) {
                $html = $this->collect_node_html($nodes->item(0));
                if (!empty($html)) {
                    return $html;
                }
            }
        }

        $paragraphs = $xpath->query('//p');
        if ($paragraphs && $paragraphs->length > 0) {
            $chunks = array();
            $limit = min(14, $paragraphs->length);
            for ($i = 0; $i < $limit; $i++) {
                $chunks[] = $this->node_to_clean_html($paragraphs->item($i));
            }
            $fallback = implode('', array_filter($chunks));
            if (!empty($fallback)) {
                return $fallback;
            }
        }

        return '';
    }

    private function collect_node_html($node)
    {
        if (!$node || !$node->hasChildNodes()) {
            return '';
        }

        $doc = $node->ownerDocument;
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $doc->saveHTML($child);
        }

        return $this->sanitize_html($html);
    }

    private function node_to_clean_html($node)
    {
        if (!$node) {
            return '';
        }

        return $this->sanitize_html($node->ownerDocument->saveHTML($node));
    }

    private function sanitize_html($html)
    {
        if (empty($html)) {
            return '';
        }

        // Remove script tags and content
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);

        // Remove style tags and content
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

        // Remove noscript tags
        $html = preg_replace('/<noscript\b[^>]*>(.*?)<\/noscript>/is', '', $html);

        // Remove inline style attributes
        $html = preg_replace('/\s*style\s*=\s*["\'][^"\']*["\']/i', '', $html);

        // Remove raw CSS blocks (class selectors)
        $html = preg_replace('/\.[a-zA-Z0-9_-]+\s*\{[^}]*\}/s', '', $html);

        // Remove CSS variable declarations
        $html = preg_replace('/--[a-zA-Z0-9_-]+\s*:[^;]+;/s', '', $html);

        // Remove @media queries
        $html = preg_replace('/@media[^{]*\{([^{}]*\{[^{}]*\})*[^{}]*\}/s', '', $html);

        // Remove JavaScript patterns
        $html = preg_replace('/\(function\s*\(\)\s*\{.*?\}\)\s*\(\s*\)\s*;?/s', '', $html);
        $html = preg_replace('/window\.[a-zA-Z0-9_]+\s*=\s*[^;]+;/s', '', $html);

        $allowed = array(
            'p' => array(),
            'h2' => array(),
            'h3' => array(),
            'ul' => array(),
            'ol' => array(),
            'li' => array(),
            'strong' => array(),
            'em' => array(),
            'a' => array('href' => true, 'title' => true, 'target' => true, 'rel' => true),
            'blockquote' => array(),
        );

        return wp_kses($html, $allowed);
    }

    private function build_sections_from_article($article_html, $type, $headline, $context)
    {
        $paragraphs = $this->extract_paragraphs($article_html);
        $sections = array();
        
        // Detect actual content type from headline and context
        $actual_type = $this->detect_content_type($headline, $context);
        
        // Introduction - Enhanced opening that hooks readers
        $intro = $this->generate_introduction($headline, $paragraphs, $context);
        $sections[] = array(
            'heading' => '',  // No heading for intro
            'html' => $this->wrap_paragraph($intro),
        );
        
        // Key Takeaways - Bullet points for scanners
        $sections[] = array(
            'heading' => 'Key Takeaways',
            'html' => $this->generate_key_takeaways($headline, $paragraphs, $context, $actual_type),
        );
        
        // Build sections based on actual content type
        switch ($actual_type) {
            case 'acquisition':
            case 'merger':
            case 'investment':
                $sections[] = array(
                    'heading' => 'Transaction Details',
                    'html' => $this->generate_deal_analysis($headline, $paragraphs, $context),
                );
                $sections[] = array(
                    'heading' => 'Strategic Rationale',
                    'html' => $this->generate_strategic_implications($headline, $paragraphs, $context),
                );
                break;
                
            case 'earnings':
                $sections[] = array(
                    'heading' => 'Financial Performance',
                    'html' => $this->generate_financial_analysis($headline, $paragraphs, $context),
                );
                $sections[] = array(
                    'heading' => 'Earnings Breakdown',
                    'html' => $this->generate_earnings_details($headline, $paragraphs, $context),
                );
                break;
                
            case 'market_update':
                $sections[] = array(
                    'heading' => 'Market Analysis',
                    'html' => $this->generate_market_analysis($headline, $paragraphs, $context),
                );
                $sections[] = array(
                    'heading' => 'Trading Insights',
                    'html' => $this->generate_trading_insights($headline, $paragraphs, $context),
                );
                break;
                
            case 'regulatory':
                $sections[] = array(
                    'heading' => 'Regulatory Impact',
                    'html' => $this->generate_regulatory_analysis($headline, $paragraphs, $context),
                );
                $sections[] = array(
                    'heading' => 'Compliance Implications',
                    'html' => $this->generate_compliance_details($headline, $paragraphs, $context),
                );
                break;
                
            case 'leadership':
                $sections[] = array(
                    'heading' => 'Leadership Changes',
                    'html' => $this->generate_leadership_analysis($headline, $paragraphs, $context),
                );
                $sections[] = array(
                    'heading' => 'Organizational Impact',
                    'html' => $this->generate_org_impact($headline, $paragraphs, $context),
                );
                break;
                
            case 'product':
                $sections[] = array(
                    'heading' => 'Product Overview',
                    'html' => $this->generate_product_analysis($headline, $paragraphs, $context),
                );
                $sections[] = array(
                    'heading' => 'Market Positioning',
                    'html' => $this->generate_product_positioning($headline, $paragraphs, $context),
                );
                break;
                
            default:
                // Generic news article structure
                $sections[] = array(
                    'heading' => 'Analysis',
                    'html' => $this->generate_industry_impact($headline, $paragraphs, $context),
                );
                $sections[] = array(
                    'heading' => 'Market Implications',
                    'html' => $this->generate_market_outlook($headline, $paragraphs, $context),
                );
                break;
        }
        
        // Expert Perspective - applicable to all types
        $sections[] = array(
            'heading' => 'Expert Commentary',
            'html' => $this->generate_expert_perspective($headline, $paragraphs, $context, $actual_type),
        );
        
        // Conclusion with forward-looking statements
        $sections[] = array(
            'heading' => 'Looking Ahead',
            'html' => $this->generate_conclusion($headline, $paragraphs, $context, $actual_type),
        );
        
        // FAQ Section for SEO
        $sections[] = array(
            'heading' => 'Frequently Asked Questions',
            'html' => $this->generate_faq_section($headline, $context, $actual_type),
        );

        return $sections;
    }

    private function extract_paragraphs($html)
    {
        if (empty($html)) {
            return array();
        }

        $text = wp_strip_all_tags(str_replace(array('</p>', '</li>'), "\n", $html));
        $text = preg_replace('/\s+/', ' ', $text);
        $chunks = preg_split('/(?<=[.!?])\s+/', $text);
        $paragraphs = array();

        foreach ($chunks as $chunk) {
            $chunk = $this->tidy_text($chunk);
            if (strlen($chunk) > 60) {
                $paragraphs[] = $chunk;
            }
        }

        return $paragraphs;
    }

    private function build_snapshot_from_context($type, $headline, $context)
    {
        $companies = $context['companies'] ?? array();
        $lead = $companies[0] ?? ($headline['source'] ?? 'a leading private markets firm');
        $sector = $context['sector'] ?? 'diversified markets';
        $region = $context['region'] ?? 'global markets';

        if ($type === 'deal') {
            $value = $context['value'] ?? 'an undisclosed sum';
            return sprintf('%s is pursuing a %s play focused on %s, signalling appetite for %s opportunities.', $lead, $value, strtolower($sector), strtolower($region));
        }

        $category = $context['category'] ?? 'market intel';
        return sprintf('%s delivers a %s update shaping %s dynamics for the months ahead.', $lead, $category, strtolower($sector));
    }

    private function build_secondary_paragraph($type, $context)
    {
        $sector = $context['sector'] ?? 'key sectors';
        $region = $context['region'] ?? 'target regions';
        $stage = $context['deal_stage'] ?? 'ongoing agenda';

        if ($type === 'deal') {
            return sprintf('The process is currently %s with management teams emphasising operational value creation across %s in %s.', $stage, strtolower($sector), strtolower($region));
        }

        $sentiment = $context['sentiment'] ?? 'balanced';
        return sprintf('Sentiment across %s remains %s with investors digesting the latest catalysts impacting deployment and exits.', strtolower($sector), strtolower($sentiment));
    }

    private function compose_highlights($paragraphs, $context, $type)
    {
        $highlights = array();

        if (!empty($context['value'])) {
            $highlights[] = sprintf('Deal value: %s', $context['value']);
        }
        if (!empty($context['type'])) {
            $highlights[] = sprintf('Structure: %s', ucwords($context['type']));
        }
        if (!empty($context['region'])) {
            $highlights[] = sprintf('Region focus: %s', $context['region']);
        }
        if (!empty($context['sector'])) {
            $highlights[] = sprintf('Sector: %s', $context['sector']);
        }
        if (!empty($context['category'])) {
            $highlights[] = sprintf('Category: %s', $context['category']);
        }

        foreach ($paragraphs as $paragraph) {
            if (count($highlights) >= 5) {
                break;
            }
            $sentence = $this->tidy_text($paragraph);
            if (strlen($sentence) > 80 && substr_count($sentence, ' ') < 30) {
                $highlights[] = $sentence;
            }
        }

        $highlights = array_unique(array_filter($highlights));

        if (empty($highlights)) {
            $highlights[] = 'Deal signals continued appetite for resilient cash-flow assets.';
            $highlights[] = 'Management teams emphasise operational value creation and disciplined leverage.';
        }

        return array_slice($highlights, 0, 5);
    }

    private function compose_stat_bullets($context, $headline)
    {
        $stats = array();
        if (!empty($headline['source'])) {
            $stats[] = sprintf('Source: %s', $headline['source']);
        }
        if (!empty($context['value'])) {
            $stats[] = sprintf('Headline value: %s', $context['value']);
        }
        if (!empty($context['deal_stage'])) {
            $stats[] = sprintf('Stage: %s', ucwords($context['deal_stage']));
        }
        if (!empty($context['metrics']) && is_array($context['metrics'])) {
            foreach ($context['metrics'] as $metric) {
                if (!empty($metric['value'])) {
                    $stats[] = sprintf('Metric: %s', $metric['value']);
                }
            }
        }
        if (!empty($headline['time'])) {
            $stats[] = sprintf('Published: %s', wp_date('j M Y', strtotime($headline['time'])));
        }

        if (empty($stats)) {
            $stats[] = 'Timeline: Live transaction with rolling updates expected within the quarter.';
        }

        return array_slice($stats, 0, 5);
    }

    private function compose_watchlist_line($paragraphs, $context, $type)
    {
        $region = $context['region'] ?? 'key regions';
        $sector = $context['sector'] ?? 'priority sectors';

        if ($type === 'deal') {
            return sprintf('Monitor integration milestones and value-creation levers as %s teams execute across %s.', strtolower($sector), strtolower($region));
        }

        return sprintf('Expect follow-on commentary from %s players as macro drivers evolve across %s.', strtolower($sector), strtolower($region));
    }

    private function build_fallback_sections($type, $headline, $context)
    {
        // FACTUAL ONLY: When we don't have source content, display only verified information
        // NO fabricated quotes, NO fake attributions, NO invented expert commentary
        $paragraphs = array();
        $title = $headline['title'] ?? '';
        $source = $headline['source'] ?? '';
        $description = $headline['description'] ?? '';
        $link = $headline['link'] ?? '';

        // Use actual description if available
        if (!empty($description)) {
            $paragraphs[] = $description;
        } elseif (!empty($title)) {
            // Just the headline - don't add fake attribution
            $paragraphs[] = $title;
        }

        // Add factual context ONLY if we have verified data
        if ($type === 'deal') {
            $value = $context['value'] ?? '';
            $sector = $context['sector'] ?? '';

            if ($value && $sector) {
                // Only state what we know for certain
                $paragraphs[] = "Transaction value: {$value}. Sector: {$sector}.";
            } elseif ($value) {
                $paragraphs[] = "Transaction value: {$value}.";
            } elseif ($sector) {
                $paragraphs[] = "Sector: {$sector}.";
            }
        }

        // Source attribution - only if we have actual source info
        if (!empty($source) && !empty($link)) {
            $paragraphs[] = "Source: {$source}. Read the full article for complete details.";
        } elseif (!empty($source)) {
            $paragraphs[] = "Source: {$source}.";
        }

        // Build HTML - minimal, factual content only
        $html = '';
        foreach ($paragraphs as $para) {
            if (!empty(trim($para))) {
                $html .= '<p>' . esc_html($para) . '</p>';
            }
        }

        // If we have almost no content, be honest about it
        if (empty($html)) {
            $html = '<p>' . esc_html($title) . '</p>';
            $html .= '<p><em>Limited information available. Please refer to the original source for full details.</em></p>';
        }

        return array(
            array('heading' => '', 'html' => $html)
        );
    }

    private function deal_fallback_templates($headline, $context)
    {
        $snapshot = $this->build_snapshot_from_context('deal', $headline, $context);
        $secondary = $this->build_secondary_paragraph('deal', $context);
        $companies = $context['companies'] ?? array();
        $lead = $companies[0] ?? ($headline['source'] ?? 'PE sponsor');
        $target = $companies[1] ?? 'target business';
        $sector = $context['sector'] ?? 'core sectors';
        $region = $context['region'] ?? 'priority regions';
        $value = $context['value'] ?? 'undisclosed terms';

        $templates = array();

        $templates[] = array(
            array('heading' => 'Deal Thesis', 'html' => $this->wrap_paragraph($snapshot)),
            array('heading' => 'Value Creation Plan', 'html' => $this->render_bullet_list(array(
                sprintf('%s backing %s to accelerate scale across %s.', $lead, $target, strtolower($sector)),
                sprintf('Planned investment size: %s with follow-on capacity.', $value),
                sprintf('Geographic thesis anchored in %s demand.', strtolower($region)),
            ))),
            array('heading' => 'Execution Watchlist', 'html' => $this->wrap_paragraph($secondary)),
        );

        $templates[] = array(
            array('heading' => 'Investment Snapshot', 'html' => $this->wrap_paragraph(sprintf('%s is structuring a %s transaction targeting %s capabilities across %s.', $lead, strtolower($context['type'] ?? 'control'), strtolower($target), strtolower($region)))),
            array('heading' => 'Why It Matters', 'html' => $this->render_bullet_list(array(
                sprintf('Sector focus: %s', $sector),
                sprintf('Stage: %s', ucwords($context['deal_stage'] ?? 'announced')),
                sprintf('Sponsor playbook concentrates on %s and bolt-on capacity.', strtolower($context['deal_stage'] ?? 'value creation')),
            ))),
            array('heading' => 'Next Catalysts', 'html' => $this->wrap_paragraph('Watch regulatory filings, financing terms, and portfolio synergy updates over the next two quarters.')),
        );

        return $templates;
    }

    private function news_fallback_templates($headline, $context)
    {
        $snapshot = $this->build_snapshot_from_context('news', $headline, $context);
        $secondary = $this->build_secondary_paragraph('news', $context);
        $sector = $context['sector'] ?? 'private markets';
        $region = $context['region'] ?? 'global regions';
        $category = $context['category'] ?? 'market update';

        $templates = array();

        $templates[] = array(
            array('heading' => 'Market Watch', 'html' => $this->wrap_paragraph($snapshot)),
            array('heading' => 'Signals & Takeaways', 'html' => $this->render_bullet_list(array(
                sprintf('Category: %s', ucfirst($category)),
                sprintf('Sector lens: %s', $sector),
                sprintf('Region spotlight: %s', $region),
            ))),
            array('heading' => 'Impact on Investors', 'html' => $this->wrap_paragraph($secondary)),
        );

        $templates[] = array(
            array('heading' => 'Headline Insight', 'html' => $this->wrap_paragraph(sprintf('%s coverage underscores shifts for %s decision-makers.', $headline['source'] ?? 'Trusted media', strtolower($sector)))),
            array('heading' => 'Data to Cite', 'html' => $this->render_bullet_list($this->compose_stat_bullets($context, $headline))),
            array('heading' => 'Actionable Next Steps', 'html' => $this->wrap_paragraph('Revisit deployment pacing, LP communications, and hedging strategies as the macro narrative evolves.')),
        );

        return $templates;
    }

    private function render_sections_html($sections, $headline)
    {
        $html = array();
        $html[] = '<div class="pe-article-enhanced">';

        foreach ($sections as $section) {
            // Only add heading if it's meaningful (not empty, not generic AI headers)
            $heading = $section['heading'] ?? '';
            $skip_headers = array('', 'Summary', 'Introduction', 'Overview', 'Analysis', 'Conclusion', 'Key Takeaways');

            if (!empty($heading) && !in_array($heading, $skip_headers)) {
                $html[] = sprintf('<section class="pe-article-section"><h3>%s</h3>%s</section>', esc_html($heading), $section['html']);
            } else {
                // No header - just flowing content
                $html[] = $section['html'];
            }
        }

        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function wrap_paragraph($text)
    {
        $text = $this->tidy_text($text);
        if (empty($text)) {
            $text = 'Additional details to follow as the story develops.';
        }

        return sprintf('<p>%s</p>', esc_html($text));
    }

    private function render_bullet_list($items)
    {
        $items = array_values(array_filter(array_map(array($this, 'tidy_text'), (array) $items)));
        if (empty($items)) {
            return $this->wrap_paragraph('Analysts are still reviewing the source data. Check back shortly.');
        }

        $html = '<ul class="pe-article-highlights">';
        foreach ($items as $item) {
            $html .= sprintf('<li>%s</li>', esc_html($item));
        }
        $html .= '</ul>';

        return $html;
    }

    private function build_seo_title($type, $headline, $context)
    {
        // Use the actual headline title exactly as provided
        $title = $headline['title'] ?? 'Private Markets Update';
        
        return $this->truncate_title($title);
    }

    private function truncate_title($title)
    {
        $title = $this->tidy_text($title);
        if (strlen($title) <= 68) {
            return $title;
        }

        return rtrim(substr($title, 0, 65), ' -,:') . '…';
    }

    private function build_excerpt($sections)
    {
        if (empty($sections)) {
            return '';
        }

        $first = wp_strip_all_tags($sections[0]['html']);
        $second = $sections[1]['heading'] ?? '';
        $excerpt = trim($first . ' ' . $second);

        return wp_trim_words($excerpt, 32);
    }

    private function build_meta_description($sections)
    {
        if (empty($sections)) {
            return '';
        }

        $text = array();
        foreach ($sections as $section) {
            $text[] = wp_strip_all_tags($section['html']);
            if (strlen(implode(' ', $text)) > 220) {
                break;
            }
        }

        $description = $this->tidy_text(implode(' ', $text));
        return substr($description, 0, 155);
    }

    private function derive_focus_keywords($headline, $context)
    {
        $keywords = array();
        if (!empty($context['companies']) && is_array($context['companies'])) {
            $keywords = array_merge($keywords, $context['companies']);
        }
        if (!empty($context['sector'])) {
            $keywords[] = $context['sector'];
        }
        if (!empty($context['region'])) {
            $keywords[] = $context['region'];
        }
        if (!empty($context['category'])) {
            $keywords[] = $context['category'];
        }
        if (!empty($headline['source'])) {
            $keywords[] = $headline['source'];
        }

        $keywords = array_unique(array_filter(array_map('trim', $keywords)));
        return array_slice($keywords, 0, 8);
    }

    private function tidy_text($text)
    {
        $text = trim((string) $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return $text;
    }

    private function random_user_agent()
    {
        $agents = array(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17 Safari/605.1.15',
            'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:123.0) Gecko/20100101 Firefox/123.0'
        );

        return $agents[array_rand($agents)];
    }
    
    // Claude API Integration
    
    private function try_claude_enhancement($headline, $article_body, $context)
    {
        if (!class_exists('SFFC_Claude_API_Manager')) {
            return null;
        }

        $claude_manager = SFFC_Claude_API_Manager::get_instance();

        if (!$claude_manager->is_available()) {
            return null;
        }

        $prompt = $this->build_claude_prompt($headline, $article_body, $context);
        $result = $claude_manager->send_message($prompt, array(), 'market');

        if (!empty($result['success']) && ($result['source'] ?? '') === 'claude_api') {
            return $result['response'];
        }

        return null;
    }
    
    private function build_claude_prompt($headline, $article_body, $context)
    {
        $title = $headline['title'] ?? '';
        $source = $headline['source'] ?? '';
        $sector = $context['sector'] ?? 'financial markets';
        $value = $context['value'] ?? '';
        $companies = is_array($context['companies'] ?? null) ? implode(', ', $context['companies']) : '';
        $region = $context['region'] ?? '';

        $prompt = "You are a senior financial journalist at a premium publication like Bloomberg, Financial Times, or Sky News Business. Write a news article about this story.

STORY:
Headline: {$title}
Source: {$source}
Sector: {$sector}";

        if ($value) {
            $prompt .= "\nDeal Value: {$value}";
        }
        if ($companies) {
            $prompt .= "\nCompanies: {$companies}";
        }
        if ($region) {
            $prompt .= "\nRegion: {$region}";
        }

        if (!empty($article_body)) {
            $excerpt = wp_trim_words(strip_tags($article_body), 300, '...');
            $prompt .= "\n\nSOURCE CONTENT:\n{$excerpt}";
        }

        $prompt .= "

WRITE THE ARTICLE FOLLOWING THESE STRICT RULES:

1. STRUCTURE - Write like a real news article:
   - Opening paragraph: Lead with the most important fact (who, what, how much). Get to the point immediately.
   - Second paragraph: Provide crucial context or the 'why this matters'
   - Body: Facts, context, implications - in order of importance (inverted pyramid)
   - NO section headers like 'Analysis:', 'Key Takeaways:', 'Expert Perspective:', 'Conclusion:'
   - NO bullet point lists or numbered lists
   - NO FAQ sections
   - NO word counts or formatting instructions visible in output

2. TONE & STYLE:
   - Authoritative but accessible - like Bloomberg or FT
   - Third person, objective reporting voice
   - Short, punchy paragraphs (2-4 sentences max)
   - Active voice, strong verbs
   - Specific numbers and facts, not vague statements
   - NO phrases like 'In this article we will explore...' or 'Let's dive into...'

3. QUOTES & ATTRIBUTION - CRITICAL:
   - ONLY include quotes that appear VERBATIM in the source content provided above
   - NEVER invent quotes, fabricate statements, or create fake expert names
   - NEVER use phrases like 'according to people familiar with the matter' unless this is stated in the source
   - NEVER use phrases like 'sources say' or 'industry experts note' unless quoting actual named sources
   - If no quotes exist in the source, do NOT add any quotes - just report the facts
   - Attribute facts ONLY to the named source publication (e.g., 'according to Bloomberg')

4. FACTUAL REPORTING ONLY:
   - Report ONLY what is stated in the source content
   - Do NOT speculate about strategic implications unless stated in source
   - Do NOT invent market context, comparable transactions, or multiples
   - If information is not in the source, do NOT fabricate it
   - It is better to write a shorter, factual article than a longer one with invented details

5. LENGTH: 200-400 words of verified, factual prose

OUTPUT: Just the article text. No markdown headers. No meta-commentary. Only include information that can be verified from the source provided.";

        return $prompt;
    }
    
    
    private function parse_claude_response($claude_content, $type, $headline, $context)
    {
        // Clean the response - remove any AI artifacts that slipped through
        $claude_content = $this->clean_ai_artifacts($claude_content);

        // For journalism-style content, we want flowing prose, not sections
        // Split by double newlines to get paragraphs
        $paragraphs = preg_split('/\n\n+/', trim($claude_content));
        $sections = array();

        $html = '';
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if (empty($para)) continue;

            // Skip any remaining headers or list markers
            if (preg_match('/^(#{1,3}\s|[\*\-]\s|\d+\.\s|Key Takeaways|Analysis:|Conclusion:|FAQ)/i', $para)) {
                continue;
            }

            // Format the paragraph
            $html .= '<p>' . esc_html($para) . '</p>';
        }

        // Return as single section (no artificial headers)
        if (!empty($html)) {
            $sections[] = array(
                'heading' => '',
                'html' => $html
            );
        }

        return $sections;
    }

    /**
     * Remove AI-generated artifacts and formatting instructions from content
     */
    private function clean_ai_artifacts($content)
    {
        // Remove section headers that look AI-generated
        $ai_headers = array(
            '/^#{1,3}\s*(Key Takeaways|Analysis|Expert Perspective|Conclusion|Introduction|Overview|Summary|FAQ|Frequently Asked Questions).*$/mi',
            '/^(Key Takeaways|Analysis|Expert Perspective|Conclusion|Introduction|Overview|Summary):?\s*$/mi',
            '/^\*\*?(Key Takeaways|Analysis|Expert Perspective|Conclusion|Introduction|Overview|Summary)\*\*?:?\s*$/mi',
        );

        foreach ($ai_headers as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }

        // Remove bullet points and numbered lists
        $content = preg_replace('/^\s*[\*\-•]\s+/m', '', $content);
        $content = preg_replace('/^\s*\d+\.\s+/m', '', $content);

        // Remove word count instructions
        $content = preg_replace('/\(\d+\s*words?\)/i', '', $content);
        $content = preg_replace('/\[\d+\s*words?\]/i', '', $content);

        // Remove "In this article" type phrases
        $content = preg_replace('/In this article,?\s*(we will|we\'ll|I will|I\'ll)?\s*(explore|discuss|examine|look at|cover)/i', '', $content);
        $content = preg_replace('/Let\'s\s*(dive into|explore|look at|examine)/i', '', $content);

        // Remove FAQ patterns
        $content = preg_replace('/^Q:\s*.+\nA:\s*.+$/m', '', $content);
        $content = preg_replace('/^\*\*Q:\*\*\s*.+\n\*\*A:\*\*\s*.+$/m', '', $content);

        // Clean up multiple newlines
        $content = preg_replace('/\n{3,}/', "\n\n", $content);

        return trim($content);
    }
    
    private function format_claude_content($content)
    {
        // Convert markdown-style lists to HTML
        $content = preg_replace('/^\*\s+(.+)$/m', '<li>$1</li>', $content);
        $content = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $content);
        
        // Wrap paragraphs
        $paragraphs = explode("\n\n", $content);
        $html = '';
        
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if (empty($para)) continue;
            
            if (strpos($para, '<ul>') !== false || strpos($para, '<li>') !== false) {
                $html .= $para;
            } else {
                $html .= '<p>' . $para . '</p>';
            }
        }
        
        return $html;
    }
    
    // Context Enrichment for Uniqueness
    
    private function enrich_context($context, $headline)
    {
        // Add time-based variations
        $context['time_of_day'] = $this->get_time_context();
        $context['day_of_week'] = date('l');
        $context['month'] = date('F');
        $context['quarter'] = 'Q' . ceil(date('n') / 3);
        $context['year'] = date('Y');
        
        // Add random contextual elements
        $context['market_sentiment'] = $this->get_random_sentiment();
        $context['urgency_level'] = $this->get_random_urgency();
        $context['impact_scope'] = $this->get_random_scope();
        
        // Add source-specific context
        $context['source_credibility'] = $this->assess_source_credibility($headline['source'] ?? '');
        $context['content_freshness'] = $this->assess_freshness($headline['pubDate'] ?? '');
        
        // Add variation seeds for templates
        $context['template_seed'] = mt_rand(1, 100);
        $context['style_variation'] = mt_rand(1, 5);
        
        return $context;
    }
    
    private function get_time_context()
    {
        $hour = date('G');
        if ($hour < 6) return 'early morning';
        if ($hour < 9) return 'market pre-open';
        if ($hour < 12) return 'morning trading';
        if ($hour < 14) return 'midday';
        if ($hour < 16) return 'afternoon trading';
        if ($hour < 18) return 'market close';
        if ($hour < 21) return 'after-hours';
        return 'overnight';
    }
    
    private function get_random_sentiment()
    {
        $sentiments = ['bullish', 'bearish', 'neutral', 'cautious', 'optimistic', 'mixed'];
        return $sentiments[array_rand($sentiments)];
    }
    
    private function get_random_urgency()
    {
        $urgencies = ['breaking', 'developing', 'ongoing', 'emerging', 'evolving'];
        return $urgencies[array_rand($urgencies)];
    }
    
    private function get_random_scope()
    {
        $scopes = ['sector-wide', 'industry-specific', 'global', 'regional', 'company-specific', 'market-wide'];
        return $scopes[array_rand($scopes)];
    }
    
    private function assess_source_credibility($source)
    {
        $tier1 = ['Reuters', 'Bloomberg', 'Financial Times', 'Wall Street Journal'];
        $tier2 = ['CNBC', 'MarketWatch', 'Forbes', 'Business Insider'];
        
        if (in_array($source, $tier1)) return 'tier-1';
        if (in_array($source, $tier2)) return 'tier-2';
        return 'standard';
    }
    
    private function assess_freshness($pubDate)
    {
        if (empty($pubDate)) return 'current';
        
        $age_hours = (time() - strtotime($pubDate)) / 3600;
        
        if ($age_hours < 1) return 'breaking';
        if ($age_hours < 6) return 'fresh';
        if ($age_hours < 24) return 'recent';
        if ($age_hours < 72) return 'current';
        return 'archived';
    }
    
    private function calculate_uniqueness_score($sections)
    {
        $score = 0;
        $content = '';
        
        foreach ($sections as $section) {
            $content .= strip_tags($section['html']);
        }
        
        // Calculate based on content length
        $score += min(strlen($content) / 100, 30);
        
        // Calculate based on number of sections
        $score += count($sections) * 5;
        
        // Calculate based on vocabulary diversity
        $words = str_word_count(strtolower($content), 1);
        $unique_words = count(array_unique($words));
        $total_words = count($words);
        
        if ($total_words > 0) {
            $diversity = ($unique_words / $total_words) * 100;
            $score += min($diversity / 2, 40);
        }
        
        return min(round($score), 100);
    }
    
    // Content Generation Methods for SEO-Optimized Articles
    
    private function generate_introduction($headline, $paragraphs, $context)
    {
        $title = $headline['title'] ?? '';
        $source = $headline['source'] ?? '';
        $date = !empty($headline['pubDate']) ? date('F j, Y', strtotime($headline['pubDate'])) : date('F j, Y');
        
        // Use first paragraph if available and enhance it
        if (!empty($paragraphs[0]) && ($context['template_seed'] ?? 0) > 30) {
            $base = $paragraphs[0];
            
            // Vary the introduction style based on context
            $intros = [
                "According to " . $source . ", " . lcfirst($base),
                "In " . ($context['urgency_level'] ?? 'developing') . " news, " . lcfirst($base),
                $base . " This " . ($context['impact_scope'] ?? 'significant') . " development has drawn attention from market participants.",
                "Reports from " . $source . " indicate that " . lcfirst($base),
                "Market sources reveal that " . lcfirst($base)
            ];
            
            return $intros[array_rand($intros)];
        }
        
        // Generate varied introductions based on context
        $sector = $context['sector'] ?? 'financial markets';
        $region = $context['region'] ?? 'global markets';
        $sentiment = $context['market_sentiment'] ?? 'evolving';
        $urgency = $context['urgency_level'] ?? 'significant';
        $time_context = $context['time_of_day'] ?? '';
        
        $templates = [
            sprintf(
                "In a %s development for %s, %s. This news, emerging on %s, highlights important shifts in %s that market participants and investors should closely monitor.",
                $urgency,
                $sector,
                $this->summarize_headline($headline),
                $date,
                $region
            ),
            sprintf(
                "The %s landscape is witnessing %s developments as %s. Industry observers note this %s trend could reshape %s dynamics in the coming period.",
                $sector,
                $urgency,
                $this->summarize_headline($headline),
                $sentiment,
                $region
            ),
            sprintf(
                "%s market activity shows %s. The %s news underscores evolving dynamics in %s as stakeholders assess implications.",
                ucfirst($time_context),
                $this->summarize_headline($headline),
                $urgency,
                $sector
            ),
            sprintf(
                "Financial markets are digesting %s news that %s. This development in %s reflects broader %s trends affecting %s.",
                $urgency,
                $this->summarize_headline($headline),
                $sector,
                $sentiment,
                $region
            ),
            sprintf(
                "A %s shift in %s emerges as %s. Market analysts are evaluating the %s implications for %s positioning.",
                $urgency,
                $sector,
                $this->summarize_headline($headline),
                $context['impact_scope'] ?? 'potential',
                $region
            )
        ];
        
        // Use template seed for consistent but varied selection
        $index = ($context['template_seed'] ?? 0) % count($templates);
        return $templates[$index];
    }
    
    private function generate_key_takeaways($headline, $paragraphs, $context, $type)
    {
        $takeaways = array();
        
        // Extract key points from paragraphs
        if (count($paragraphs) >= 3) {
            $takeaways[] = $this->extract_key_point($paragraphs[0]);
            $takeaways[] = $this->extract_key_point($paragraphs[1]);
            $takeaways[] = $this->extract_key_point($paragraphs[2]);
        }
        
        // Add context-based takeaways
        if (!empty($context['companies'])) {
            $companies = implode(', ', array_slice($context['companies'], 0, 2));
            $takeaways[] = "Key players involved: " . $companies;
        }
        
        if (!empty($context['value'])) {
            $takeaways[] = "Transaction value: " . $context['value'];
        }
        
        if (!empty($context['sector'])) {
            $takeaways[] = "Sector focus: " . ucwords($context['sector']);
        }
        
        // Ensure we have at least 3 takeaways
        while (count($takeaways) < 3) {
            $takeaways[] = $this->generate_contextual_takeaway($headline, $context, $type);
        }
        
        return $this->render_bullet_list(array_slice($takeaways, 0, 5));
    }
    
    private function generate_market_context($headline, $paragraphs, $context)
    {
        $sector = $context['sector'] ?? 'the market';
        $region = $context['region'] ?? 'global markets';
        
        // Use middle paragraphs if available
        if (count($paragraphs) > 3) {
            $context_para = $paragraphs[2] . ' ' . $paragraphs[3];
        } else {
            $context_para = sprintf(
                "The current market environment in %s continues to evolve rapidly, with %s playing a crucial role in shaping investment strategies and capital allocation decisions.",
                $sector,
                $region
            );
        }
        
        // Add market statistics or trends
        $trends = $this->generate_market_trends($context);
        
        return $this->wrap_paragraph($context_para) . $this->render_bullet_list($trends);
    }
    
    private function generate_deal_analysis($headline, $paragraphs, $context)
    {
        $analysis = "";
        
        // Use available paragraphs
        if (count($paragraphs) > 4) {
            $analysis = implode('</p><p>', array_slice($paragraphs, 3, 3));
        } else {
            // Generate analysis based on context
            $companies = $context['companies'] ?? array('The acquiring firm', 'the target company');
            $sector = $context['sector'] ?? 'this sector';
            
            $analysis = sprintf(
                "This transaction represents a strategic move in %s, where %s seeks to strengthen its market position through this acquisition. The deal structure reflects current market dynamics and valuation trends in the industry.",
                $sector,
                $companies[0] ?? 'the buyer'
            );
            
            $analysis .= sprintf(
                " Industry experts note that such transactions are becoming increasingly common as firms seek to achieve scale and operational synergies in a competitive landscape."
            );
        }
        
        return $this->wrap_paragraph($analysis);
    }
    
    private function generate_strategic_implications($headline, $paragraphs, $context)
    {
        $implications = array(
            "Market positioning and competitive advantages",
            "Operational synergies and integration opportunities",
            "Geographic expansion and market access",
            "Technology and capability enhancement",
            "Portfolio optimization and value creation"
        );
        
        $content = "<h3>Key Strategic Drivers</h3>";
        $content .= $this->render_bullet_list(array_slice($implications, 0, 3));
        
        if (count($paragraphs) > 5) {
            $content .= $this->wrap_paragraph($paragraphs[5]);
        }
        
        return $content;
    }
    
    private function generate_industry_impact($headline, $paragraphs, $context)
    {
        $sector = $context['sector'] ?? 'the industry';
        
        $impact = sprintf(
            "This development has significant implications for %s, potentially influencing competitive dynamics, investment patterns, and strategic priorities across the sector.",
            $sector
        );
        
        if (count($paragraphs) > 4) {
            $impact .= ' ' . $paragraphs[4];
        }
        
        return $this->wrap_paragraph($impact);
    }
    
    private function generate_market_outlook($headline, $paragraphs, $context)
    {
        $outlook_points = array(
            "Continued consolidation expected in the sector",
            "Increasing focus on digital transformation and innovation",
            "Growing importance of ESG considerations",
            "Evolution of regulatory frameworks",
            "Shift in investor preferences and capital flows"
        );
        
        $content = "<h3>Forward-Looking Indicators</h3>";
        $content .= $this->render_bullet_list(array_slice($outlook_points, 0, 3));
        
        return $content;
    }
    
    private function generate_expert_perspective($headline, $paragraphs, $context, $type)
    {
        // REMOVED: We no longer generate fake expert commentary
        // Only return actual quotes if they exist in the source paragraphs

        // Check if any paragraph contains an actual quote (indicated by quotation marks)
        foreach ($paragraphs as $para) {
            if (preg_match('/"[^"]{20,}"/', $para) || preg_match('/said\s+[A-Z][a-z]+/', $para)) {
                // This paragraph appears to contain a real quote - use it
                return $this->wrap_paragraph($para);
            }
        }

        // No real quotes found - return empty string instead of fabricating
        // The calling code will handle missing sections gracefully
        return '';
    }
    
    private function generate_conclusion($headline, $paragraphs, $context, $type)
    {
        // REVISED: Only provide factual closing, no speculation or fabricated forward-looking statements
        $source = $headline['source'] ?? '';
        $link = $headline['link'] ?? '';

        // If we have source info, point readers to it
        if (!empty($source) && !empty($link)) {
            return $this->wrap_paragraph("For complete details on this development, refer to the original report from {$source}.");
        } elseif (!empty($source)) {
            return $this->wrap_paragraph("Source: {$source}.");
        }

        // No fabricated conclusions - return empty if no real source info
        return '';
    }
    
    private function generate_faq_section($headline, $context, $type)
    {
        // REMOVED: We no longer generate fabricated FAQ content
        // FAQ sections with invented Q&A are misleading to readers
        // Return empty string - the section will be omitted from output
        return '';
    }
    
    // Helper methods for content generation
    
    private function detect_content_type($headline, $context)
    {
        $title = strtolower($headline['title'] ?? '');
        $description = strtolower($headline['description'] ?? '');
        $combined = $title . ' ' . $description;
        
        // Deal-related keywords
        if (preg_match('/\b(acqui|buy|purchas|merg|deal|transaction|takeov|bid)\b/i', $combined)) {
            if (preg_match('/\bmerg/i', $combined)) return 'merger';
            if (preg_match('/\binvest/i', $combined)) return 'investment';
            return 'acquisition';
        }
        
        // Earnings/Financial results
        if (preg_match('/\b(earning|revenue|profit|quarter|q[1-4]|financial result|eps|ebitda)\b/i', $combined)) {
            return 'earnings';
        }
        
        // Market updates
        if (preg_match('/\b(market|trading|stock|shares|index|dow|s&p|nasdaq|ftse)\b/i', $combined)) {
            return 'market_update';
        }
        
        // Regulatory
        if (preg_match('/\b(regulat|sec|fca|compliance|fine|sanction|investigation)\b/i', $combined)) {
            return 'regulatory';
        }
        
        // Leadership changes
        if (preg_match('/\b(ceo|cfo|cto|appoint|resign|hire|executive|leadership|board)\b/i', $combined)) {
            return 'leadership';
        }
        
        // Product/Service launches
        if (preg_match('/\b(launch|introduc|unveil|releas|new product|new service)\b/i', $combined)) {
            return 'product';
        }
        
        // Default to news
        return 'news';
    }
    
    private function generate_financial_analysis($headline, $paragraphs, $context)
    {
        if (count($paragraphs) > 2) {
            return $this->wrap_paragraph(implode(' ', array_slice($paragraphs, 1, 2)));
        }
        
        return $this->wrap_paragraph(
            "The financial results reflect current market conditions and operational performance. " .
            "Key metrics demonstrate the company's strategic positioning and execution capabilities in a dynamic market environment."
        );
    }
    
    private function generate_earnings_details($headline, $paragraphs, $context)
    {
        $metrics = array(
            "Revenue growth trends and drivers",
            "Margin performance and operational efficiency",
            "Segment performance breakdown",
            "Forward guidance and outlook"
        );
        
        return $this->render_bullet_list($metrics);
    }
    
    private function generate_market_analysis($headline, $paragraphs, $context)
    {
        if (count($paragraphs) > 3) {
            return $this->wrap_paragraph($paragraphs[2] . ' ' . $paragraphs[3]);
        }
        
        return $this->wrap_paragraph(
            "Market movements reflect investor sentiment and broader economic trends. " .
            "Technical and fundamental factors continue to influence trading patterns and valuation metrics across sectors."
        );
    }
    
    private function generate_trading_insights($headline, $paragraphs, $context)
    {
        $insights = array(
            "Volume and momentum indicators",
            "Support and resistance levels",
            "Sector rotation patterns",
            "Institutional positioning"
        );
        
        return $this->render_bullet_list(array_slice($insights, 0, 3));
    }
    
    private function generate_regulatory_analysis($headline, $paragraphs, $context)
    {
        if (count($paragraphs) > 1) {
            return $this->wrap_paragraph($paragraphs[1]);
        }
        
        return $this->wrap_paragraph(
            "Regulatory developments continue to shape industry practices and compliance frameworks. " .
            "Market participants are adapting strategies to align with evolving requirements and standards."
        );
    }
    
    private function generate_compliance_details($headline, $paragraphs, $context)
    {
        return $this->wrap_paragraph(
            "Compliance considerations remain paramount as organizations navigate regulatory requirements. " .
            "Industry best practices continue to evolve in response to regulatory guidance and enforcement trends."
        );
    }
    
    private function generate_leadership_analysis($headline, $paragraphs, $context)
    {
        if (count($paragraphs) > 1) {
            return $this->wrap_paragraph($paragraphs[0] . ' ' . $paragraphs[1]);
        }
        
        return $this->wrap_paragraph(
            "Leadership transitions reflect strategic priorities and organizational evolution. " .
            "The appointments signal commitment to growth strategies and operational excellence."
        );
    }
    
    private function generate_org_impact($headline, $paragraphs, $context)
    {
        return $this->wrap_paragraph(
            "Organizational changes are expected to enhance operational efficiency and strategic execution. " .
            "Stakeholders anticipate positive impacts on culture, performance, and market positioning."
        );
    }
    
    private function generate_product_analysis($headline, $paragraphs, $context)
    {
        if (count($paragraphs) > 2) {
            return $this->wrap_paragraph($paragraphs[1]);
        }
        
        return $this->wrap_paragraph(
            "Product innovations reflect market demands and competitive dynamics. " .
            "The offering addresses key customer needs while positioning for future growth opportunities."
        );
    }
    
    private function generate_product_positioning($headline, $paragraphs, $context)
    {
        $positioning = array(
            "Competitive differentiation and value proposition",
            "Target market segments and use cases",
            "Go-to-market strategy and distribution",
            "Revenue potential and market opportunity"
        );
        
        return $this->render_bullet_list(array_slice($positioning, 0, 3));
    }
    
    private function summarize_headline($headline)
    {
        $title = $headline['title'] ?? '';
        $description = $headline['description'] ?? '';
        
        if (!empty($description) && strlen($description) > 50) {
            return $this->truncate_excerpt($description);
        }
        
        return $title;
    }
    
    private function extract_key_point($paragraph)
    {
        // Extract first sentence or key phrase
        $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph, 2);
        return $sentences[0] ?? $paragraph;
    }
    
    private function generate_contextual_takeaway($headline, $context, $type)
    {
        $templates = array(
            "Market dynamics continue to evolve in response to these developments",
            "Strategic positioning remains crucial for market participants",
            "Industry consolidation trends accelerate across key segments",
            "Investment opportunities emerge from market restructuring",
            "Regulatory considerations shape transaction structures"
        );
        
        return $templates[array_rand($templates)];
    }
    
    private function generate_market_trends($context)
    {
        $sector = $context['sector'] ?? 'the market';
        
        return array(
            "Increasing M&A activity in " . $sector,
            "Growing investor interest in strategic assets",
            "Evolution of valuation methodologies and metrics",
            "Shift toward sustainable and ESG-focused investments"
        );
    }
}
