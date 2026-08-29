<?php
/**
 * Headline Optimizer
 *
 * Optimizes syndicated headlines for clarity and readability while maintaining
 * 100% factual accuracy. Focuses on clear, professional financial journalism.
 *
 * PRINCIPLES:
 * - Factual accuracy is paramount - never sensationalize
 * - Prefer clarity over clickbait
 * - Use original headline when it's already good
 * - Only rewrite to improve clarity, not emotional manipulation
 *
 * @package SennaFinanceCareer
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Headline_Optimizer {

    private static $instance = null;
    private $claude_manager = null;

    /**
     * Content type patterns - used only for categorization, not emotional manipulation
     * REMOVED: Loss aversion, consequence, tension triggers - these add sensationalism
     */
    private $content_patterns = array(
        // Deal activity indicators (factual, not emotional)
        'deal' => array(
            'acquires',
            'acquisition',
            'merger',
            'buyout',
            'transaction',
            'deal',
            'purchase',
        ),

        // Exit/sale indicators
        'exit' => array(
            'exit',
            'sale',
            'sells',
            'ipo',
            'listing',
            'divestiture',
        ),

        // Fundraising indicators
        'fundraise' => array(
            'raises',
            'fund',
            'closes',
            'commits',
            'fundraising',
        ),

        // People moves (factual)
        'people' => array(
            'appoints',
            'hires',
            'joins',
            'names',
            'promotes',
            'departs',
        ),

        // Financial data (specificity is good for credibility)
        'financial' => array(
            '$',
            'billion',
            'million',
            '%',
        ),
    );

    /**
     * Title templates by article category
     * Templates are ordered by number of required variables (fewer = more likely to succeed)
     */
    private $templates = array(
        'acquisition' => array(
            // Templates requiring only firm + value (most likely to fill)
            "{firm} Makes {value} Bet on {sector}",
            "{firm}'s {value} Deal Signals Shift in {sector}",
            // Templates requiring more context (less likely)
            "{buyer} Pays {value} for {target}",
            "{buyer}'s {value} {target} Acquisition",
        ),
        'exit' => array(
            "{firm} Exits {sector} Investment at {value}",
            "{firm}'s {value} Exit Marks End of {sector} Bet",
            "{seller} Exits {company} at {value}",
        ),
        'fundraise' => array(
            "{firm} Closes {value} Fund",
            "{firm} Raises {value} for New {sector} Fund",
            "{firm}'s {value} Fundraise Closes",
        ),
        'struggle' => array(
            "{firm} Faces Pressure in {sector}",
            "{firm}'s {sector} Bet Under Scrutiny",
            "Challenges Mount for {firm} in {sector}",
        ),
        'market_trend' => array(
            // These are the most generic - use sparingly
            "{sector} Dealmaking Shifts as Markets Evolve",
            "PE Firms Eye {sector} Amid Market Changes",
            "{sector} Valuations Under Pressure",
        ),
        'people' => array(
            "{person} Joins {firm}",
            "{person} Departs {firm}",
            "{firm} Names New {sector} Head",
        ),
    );

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if (class_exists('SFFC_Claude_API_Manager')) {
            $this->claude_manager = SFFC_Claude_API_Manager::get_instance();
        }
    }

    /**
     * Optimize a headline for maximum CTR while maintaining accuracy
     *
     * @param string $original_title The source/syndicated title
     * @param string $article_content The full article content for context
     * @param array $metadata Optional metadata (company, value, sector, etc.)
     * @return array Optimized title options with scores
     */
    public function optimize_headline($original_title, $article_content, $metadata = array()) {
        // Extract key entities and facts from content
        $extracted = $this->extract_facts($original_title, $article_content);
        $extracted = array_merge($extracted, $metadata);

        // Try Claude-powered optimization first
        if ($this->claude_manager) {
            $claude_titles = $this->get_claude_optimized_titles($original_title, $article_content, $extracted);
            if (!empty($claude_titles)) {
                return $claude_titles;
            }
        }

        // Fallback to template-based optimization
        return $this->get_template_optimized_titles($original_title, $extracted);
    }

    /**
     * Use Claude to generate psychologically optimized headlines
     * Claude extracts entities AND generates the headline in one pass
     */
    private function get_claude_optimized_titles($original_title, $content, $facts) {
        $excerpt = wp_trim_words(wp_strip_all_tags($content), 300);

        // Pass any pre-extracted facts as hints
        $hints = '';
        if (!empty($facts['value'])) $hints .= "Deal value mentioned: {$facts['value']}\n";
        if (!empty($facts['firm'])) $hints .= "Firm mentioned: {$facts['firm']}\n";
        if (!empty($facts['sector'])) $hints .= "Sector: {$facts['sector']}\n";

        $prompt = "You are an editor for a premium financial publication (Bloomberg/FT style).

ORIGINAL HEADLINE: {$original_title}

ARTICLE CONTENT:
{$excerpt}

{$hints}

YOUR TASK:
1. First, identify the KEY ENTITIES from the article:
   - Primary company/firm name (the main subject)
   - Deal value (if any)
   - Target company (if acquisition)
   - Sector/industry
   - Key person (if people news)

2. Then write 3 headline options that are:
   - FACTUALLY ACCURATE - only use information from the article
   - NEUTRAL TONE - do not add sensationalism, drama, or emotional language
   - Professional (Bloomberg/FT style)
   - Clear and informative
   - Under 80 characters

CRITICAL RULES:
- DO NOT add words like: scandal, crisis, chaos, collapse, disaster, shocking, stunning
- DO NOT add negative framing unless the source article explicitly describes something negative
- DO NOT use clickbait patterns like 'What X Means For Y' or 'Inside The...'
- If the original headline is already factual and clear, keep it unchanged or make minimal edits
- NEVER fabricate or exaggerate - if uncertain, use conservative language
- Headlines should inform, not manipulate emotions

Respond with JSON only:
{
    \"entities\": {
        \"primary_company\": \"extracted company name or null\",
        \"deal_value\": \"extracted value or null\",
        \"target_company\": \"if acquisition, target name or null\",
        \"sector\": \"identified sector\",
        \"person\": \"key person name or null\"
    },
    \"titles\": [
        {\"title\": \"Best headline - factual and clear\", \"clarity_score\": 85},
        {\"title\": \"Second option\", \"clarity_score\": 80},
        {\"title\": \"Third option - or original if already good\", \"clarity_score\": 75}
    ]
}";

        $result = $this->claude_manager->send_message($prompt, array(), 'market');

        if (is_array($result) && !empty($result['response'])) {
            return $this->parse_claude_titles($result['response'], $original_title);
        }

        return null;
    }

    /**
     * Parse Claude's title suggestions
     */
    private function parse_claude_titles($response, $original_title) {
        $json_start = strpos($response, '{');
        $json_end = strrpos($response, '}');

        if ($json_start === false || $json_end === false) {
            return null;
        }

        $json_str = substr($response, $json_start, $json_end - $json_start + 1);
        $data = json_decode($json_str, true);

        if (!$data || empty($data['titles'])) {
            return null;
        }

        $titles = array();
        foreach ($data['titles'] as $item) {
            $title = $item['title'] ?? '';

            // Validate the title is actually good
            if (empty($title) || strlen($title) < 15) {
                continue;
            }

            // Check for broken/placeholder patterns
            if ($this->is_broken_headline($title)) {
                continue;
            }

            $titles[] = array(
                'title' => $title,
                'trigger' => $item['trigger'] ?? 'claude',
                'ctr_score' => $item['ctr_score'] ?? 70,
                'original' => $original_title,
            );
        }

        // If Claude didn't produce any valid titles, return null to trigger fallback
        if (empty($titles)) {
            return null;
        }

        return array(
            'titles' => $titles,
            'entities' => $data['entities'] ?? array(),
            'source' => 'claude',
        );
    }

    /**
     * Check if a headline has broken/placeholder patterns
     */
    private function is_broken_headline($headline) {
        $broken_patterns = array(
            "/Inside\s+'s/i",
            "/\s+'s\s+Deal/i",
            "/\s+'s\s+Fund/i",
            "/^'s\s+/",
            "/\{\w+\}/",  // Unfilled template variables
            "/Company\s+Makes/i",  // Generic "Company"
            "/Firm\s+Raises/i",    // Generic "Firm"
            "/:\s*$/",
            "/for\s*$/i",
            "/the\s*$/i",
        );

        foreach ($broken_patterns as $pattern) {
            if (preg_match($pattern, $headline)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Template-based headline optimization (fallback when Claude is unavailable)
     *
     * This is intentionally simple - we only attempt templates when we have
     * high-confidence extracted data. Otherwise, we use the original headline.
     */
    private function get_template_optimized_titles($original_title, $facts) {
        $titles = array();

        // Only attempt template filling if we have strong entity data
        $has_firm = !empty($facts['firm']);
        $has_value = !empty($facts['value']);
        $has_sector = !empty($facts['sector']);

        // We need at least firm + one other field to make a meaningful headline
        if ($has_firm && ($has_value || $has_sector)) {
            $category = $this->detect_article_category($original_title, $facts);

            // Generic market-trend templates are too weak for published titles.
            // If we do not have a more specific category, keep the source headline.
            if ($category === 'market_trend') {
                return array(
                    'titles' => array(
                        array(
                            'title' => $original_title,
                            'trigger' => 'original',
                            'ctr_score' => 50,
                            'original' => $original_title,
                        ),
                    ),
                    'source' => 'template',
                );
            }

            $templates = $this->templates[$category] ?? array();

            foreach ($templates as $template) {
                $filled = $this->fill_template($template, $facts);
                if ($filled && !$this->is_broken_headline($filled)) {
                    $titles[] = array(
                        'title' => $filled,
                        'trigger' => 'template',
                        'ctr_score' => $this->estimate_ctr_score($filled),
                        'original' => $original_title,
                    );
                    break; // Just take the first good one
                }
            }
        }

        // If no template worked, just use the original headline
        // This is better than producing garbage
        if (empty($titles)) {
            $titles[] = array(
                'title' => $original_title,
                'trigger' => 'original',
                'ctr_score' => 50,
                'original' => $original_title,
            );
        }

        return array(
            'titles' => $titles,
            'source' => 'template',
        );
    }

    /**
     * Extract facts and entities from article
     */
    private function extract_facts($title, $content) {
        $text = $title . ' ' . wp_strip_all_tags($content);
        $facts = array();

        // Extract monetary values
        if (preg_match('/\$\s*(\d+(?:\.\d+)?)\s*(billion|bn|b|million|mn|m|trillion|tn)/i', $text, $match)) {
            $value = $match[1];
            $unit = strtolower($match[2]);
            if (in_array($unit, array('billion', 'bn', 'b'))) {
                $facts['value'] = '$' . $value . 'bn';
            } elseif (in_array($unit, array('trillion', 'tn'))) {
                $facts['value'] = '$' . $value . 'tn';
            } else {
                $facts['value'] = '$' . $value . 'mn';
            }
        }

        // Extract percentages
        if (preg_match('/(\d+(?:\.\d+)?)\s*%/', $text, $match)) {
            $facts['percentage'] = $match[1] . '%';
        }

        // Extract company names (basic - looks for capitalized words before common suffixes)
        if (preg_match('/([A-Z][a-zA-Z]+(?:\s+[A-Z][a-zA-Z]+)*)\s+(?:acquires|buys|purchases|sells|exits)/i', $text, $match)) {
            $facts['buyer'] = trim($match[1]);
        }

        // Extract target company
        if (preg_match('/(?:acquires|buys|purchases)\s+([A-Z][a-zA-Z]+(?:\s+[A-Z][a-zA-Z]+)*)/i', $text, $match)) {
            $facts['target'] = trim($match[1]);
        }

        // Known PE firms
        $pe_firms = array(
            'Blackstone', 'KKR', 'Apollo', 'Carlyle', 'TPG', 'Warburg Pincus',
            'CVC', 'EQT', 'Permira', 'Apax', 'Bain Capital', 'Advent',
            'Silver Lake', 'Thoma Bravo', 'Vista Equity', 'Hellman & Friedman',
            'General Atlantic', 'Insight Partners', 'Blue Owl', 'Ares', 'Brookfield',
            'BlackRock', 'Goldman Sachs', 'Morgan Stanley', 'JPMorgan'
        );

        foreach ($pe_firms as $firm) {
            if (stripos($text, $firm) !== false) {
                $facts['firm'] = $firm;
                if (empty($facts['buyer'])) {
                    $facts['buyer'] = $firm;
                }
                break;
            }
        }

        // Detect sector
        $sectors = array(
            'technology' => array('tech', 'software', 'saas', 'cloud', 'ai', 'data'),
            'healthcare' => array('health', 'pharma', 'biotech', 'medical', 'hospital'),
            'financial services' => array('bank', 'insurance', 'fintech', 'asset', 'wealth'),
            'consumer' => array('retail', 'consumer', 'brand', 'food', 'beverage'),
            'industrials' => array('industrial', 'manufacturing', 'aerospace', 'defense'),
            'real estate' => array('real estate', 'property', 'reit', 'housing'),
            'energy' => array('energy', 'oil', 'gas', 'renewable', 'solar', 'wind'),
            'infrastructure' => array('infrastructure', 'transport', 'logistics', 'utilities'),
        );

        $text_lower = strtolower($text);
        foreach ($sectors as $sector => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text_lower, $keyword) !== false) {
                    $facts['sector'] = ucfirst($sector);
                    break 2;
                }
            }
        }

        // Detect sentiment/outcome
        $negative_words = array('loss', 'loses', 'fail', 'struggle', 'decline', 'drop', 'fall', 'risk', 'concern', 'trouble', 'problem', 'lawsuit', 'sue');
        $positive_words = array('gain', 'growth', 'rise', 'profit', 'success', 'record', 'surge', 'boost');

        $negative_count = 0;
        $positive_count = 0;

        foreach ($negative_words as $word) {
            if (stripos($text, $word) !== false) $negative_count++;
        }
        foreach ($positive_words as $word) {
            if (stripos($text, $word) !== false) $positive_count++;
        }

        $facts['sentiment'] = $negative_count > $positive_count ? 'negative' : ($positive_count > $negative_count ? 'positive' : 'neutral');

        return $facts;
    }

    /**
     * Detect article category
     */
    private function detect_article_category($title, $facts) {
        $title_lower = strtolower($title);

        if (preg_match('/acqui|buy|purchase|deal|merger|takeover/i', $title_lower)) {
            return 'acquisition';
        }
        if (preg_match('/exit|sell|sale|ipo|listing/i', $title_lower)) {
            return 'exit';
        }
        if (preg_match('/fund|raise|fundrais|close|commit/i', $title_lower)) {
            return 'fundraise';
        }
        if (preg_match('/loss|fail|struggle|lawsuit|sue|problem|trouble|backfire/i', $title_lower)) {
            return 'struggle';
        }
        if (preg_match('/hire|appoint|join|leave|depart|move/i', $title_lower)) {
            return 'people';
        }

        return 'market_trend';
    }

    /**
     * Fill template with facts
     */
    private function fill_template($template, $facts) {
        // Define replacements - use empty string for missing values
        $replacements = array(
            '{buyer}' => $facts['buyer'] ?? $facts['firm'] ?? '',
            '{seller}' => $facts['seller'] ?? $facts['firm'] ?? '',
            '{firm}' => $facts['firm'] ?? '',
            '{target}' => $facts['target'] ?? $facts['company'] ?? '',
            '{company}' => $facts['company'] ?? $facts['target'] ?? '',
            '{value}' => $facts['value'] ?? '',
            '{sector}' => $facts['sector'] ?? '',
            '{percentage}' => $facts['percentage'] ?? '',
            '{trend}' => $facts['trend'] ?? '',
            '{implication}' => $facts['implication'] ?? '',
            '{consequence}' => $facts['consequence'] ?? '',
            '{outcome}' => $facts['outcome'] ?? '',
            '{insight}' => $facts['insight'] ?? '',
            '{person}' => $facts['person'] ?? '',
            '{destination}' => $facts['destination'] ?? '',
            '{context}' => $facts['context'] ?? '',
            '{multiple}' => $facts['multiple'] ?? '',
            '{years}' => $facts['years'] ?? '',
            '{months}' => $facts['months'] ?? '',
            '{cause}' => $facts['cause'] ?? '',
            '{problem}' => $facts['problem'] ?? '',
            '{investment}' => $facts['investment'] ?? '',
            '{advantage}' => $facts['advantage'] ?? '',
            '{metric}' => $facts['metric'] ?? '',
            '{adjective}' => $facts['adjective'] ?? '',
            '{action}' => $facts['action'] ?? '',
        );

        $filled = str_replace(array_keys($replacements), array_values($replacements), $template);

        // Check if we have enough filled values
        if (preg_match('/\{[a-z_]+\}/', $filled)) {
            return null; // Still has unfilled placeholders
        }

        // Check for broken headlines with empty placeholders that create patterns like:
        // "Inside 's Deal" or "'s Fund" or "Why Is" etc.
        // These indicate the template wasn't properly filled
        $broken_patterns = array(
            "/Inside\s+'s/i",           // Inside 's
            "/\s+'s\s+/",               // space 's space (orphan possessive)
            "/^'s\s+/",                 // starts with 's
            "/:\s*$/",                  // ends with colon and nothing after
            "/\s+the\s+the\s+/i",       // double "the"
            "/\s{2,}/",                 // multiple spaces (will be caught but indicates issue)
            "/Inside the\s+Reshaping/i", // Missing subject
            "/Why\s+Is\s+/i",           // Why Is (missing subject)
            "/What\s+Signals/i",        // What Signals (missing subject before What)
            "/for\s*$/i",               // ends with "for"
            "/the\s*$/i",               // ends with "the"
            "/as\s*$/i",                // ends with "as"
        );

        foreach ($broken_patterns as $pattern) {
            if (preg_match($pattern, $filled)) {
                return null; // Broken headline, reject it
            }
        }

        // Clean up double spaces
        $filled = preg_replace('/\s+/', ' ', trim($filled));

        // Final sanity check - headline should have substantial content
        if (strlen($filled) < 20) {
            return null;
        }

        return $filled;
    }

    /**
     * Simplify title for use in transformations
     */
    private function simplify_title($title) {
        // Remove common prefixes
        $title = preg_replace('/^(breaking:|update:|exclusive:)\s*/i', '', $title);
        // Truncate if too long
        if (strlen($title) > 50) {
            $title = wp_trim_words($title, 8, '');
        }
        return $title;
    }

    /**
     * Get deal type from title
     */
    private function get_deal_type($title) {
        if (preg_match('/acqui|buy|merger/i', $title)) return 'Deal';
        if (preg_match('/fund|raise/i', $title)) return 'Fundraise';
        if (preg_match('/exit|sale|ipo/i', $title)) return 'Exit';
        return 'Move';
    }

    /**
     * Convert statement to question format
     */
    private function convert_to_question($title) {
        // Remove trailing punctuation
        $title = rtrim($title, '.!?');

        // Simple conversion - this is basic, Claude does better
        $title = preg_replace('/^([A-Z][a-zA-Z]+)\s+(acquires|buys)/i', '$1 Is Acquiring', $title);
        $title = preg_replace('/^([A-Z][a-zA-Z]+)\s+(raises|raised)/i', '$1 Raised', $title);

        return $title;
    }

    /**
     * Detect which psychological trigger is used in a title
     */
    private function detect_trigger($title) {
        $title_lower = strtolower($title);

        foreach ($this->trigger_patterns as $trigger => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($title_lower, strtolower($pattern)) !== false) {
                    return $trigger;
                }
            }
        }

        return 'general';
    }

    /**
     * Estimate clarity score based on headline quality factors
     * REVISED: Scores based on clarity and factual content, NOT emotional triggers
     */
    private function estimate_ctr_score($title) {
        $score = 60; // Base score - start higher since we value original headlines
        $title_lower = strtolower($title);

        // REMOVED: Loss aversion and consequence word bonuses
        // These added sensationalism - we no longer reward them

        // Specific numbers (+10) - factual data is valuable
        if (preg_match('/\$\d+|\d+%|\d+bn|\d+mn/', $title_lower)) {
            $score += 10;
        }

        // Named entity (+8) - specific companies are more informative
        if (preg_match('/\b(blackstone|kkr|apollo|carlyle|blackrock|goldman|jpmorgan|tpg|bain|warburg|advent|permira|cvc)\b/i', $title_lower)) {
            $score += 8;
        }

        // Clear action verbs (+5) - good for understanding what happened
        if (preg_match('/\b(acquires|raises|closes|appoints|sells|launches|announces|completes)\b/i', $title_lower)) {
            $score += 5;
        }

        // Sector mention (+3) - context is helpful
        if (preg_match('/\b(technology|healthcare|infrastructure|energy|real estate|financial|consumer)\b/i', $title_lower)) {
            $score += 3;
        }

        // Length penalty (too long - harder to read)
        if (strlen($title) > 80) {
            $score -= 5;
        }

        // Very short headlines might be missing context
        if (strlen($title) < 30) {
            $score -= 3;
        }

        // Optimal length bonus (40-70 characters)
        if (strlen($title) >= 40 && strlen($title) <= 70) {
            $score += 3;
        }

        // PENALTY for sensational words - we don't want these
        if (preg_match('/\b(shocking|stunning|explosive|bombshell|chaos|disaster|scandal)\b/i', $title_lower)) {
            $score -= 10;
        }

        return min(90, max(40, $score));
    }

    /**
     * Batch optimize multiple headlines
     */
    public function batch_optimize($articles) {
        $results = array();

        foreach ($articles as $article) {
            $title = $article['title'] ?? '';
            $content = $article['content'] ?? '';
            $metadata = $article['metadata'] ?? array();

            if (empty($title)) {
                continue;
            }

            $optimized = $this->optimize_headline($title, $content, $metadata);
            $results[] = array(
                'original' => $title,
                'optimized' => $optimized,
                'article_id' => $article['id'] ?? null,
            );

            // Small delay to avoid rate limiting Claude
            if ($this->claude_manager) {
                usleep(100000); // 0.1 second
            }
        }

        return $results;
    }

    /**
     * Get the best title from optimization results
     */
    public function get_best_title($optimization_result) {
        if (empty($optimization_result['titles'])) {
            return null;
        }

        // Sort by CTR score
        $titles = $optimization_result['titles'];
        usort($titles, function($a, $b) {
            return ($b['ctr_score'] ?? 0) - ($a['ctr_score'] ?? 0);
        });

        // Return the best title that isn't broken
        foreach ($titles as $title_data) {
            $title = $title_data['title'] ?? null;
            if ($title && !$this->is_broken_headline($title)) {
                return $title;
            }
        }

        // If no optimized title is good, return the original
        $original = $titles[0]['original'] ?? null;
        return $original;
    }
}

// Initialize
SFFC_Headline_Optimizer::get_instance();
