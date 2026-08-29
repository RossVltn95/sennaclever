<?php
/**
 * Real Data Enforcer - Ensures only verified data is presented to users
 * 
 * @package SennaCareers
 * @since 3.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Real_Data_Enforcer {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Patterns that indicate fabricated content
     */
    private $fabrication_patterns = array(
        // Generic firm activities
        '/(?:announces|announced|launching|launches|expanding|expands)\s+(?:new|its|their)\s+(?:fund|office|team|division)/i',
        
        // Vague numbers without source
        '/(?:raised|raising|closed|closing)\s+\$\d+(?:\.\d+)?\s*(?:billion|million)(?!\s+according|\s+as reported|\s+source:)/i',
        
        // Generic headlines without attribution
        '/^(?:Breaking|Update|News):\s+[A-Z][^.]+(?:surges|drops|rallies|falls)(?!\s+according|\s+reports)/i',
        
        // Made-up executive quotes
        '/["\'].*?["\'],?\s+(?:said|says|stated|commented)\s+(?:a\s+)?(?:spokesperson|executive|source)/i',
        
        // Future predictions without basis
        '/(?:expected|likely|probably|potentially)\s+to\s+(?:reach|exceed|grow|expand).*?(?:by|in)\s+20\d{2}/i'
    );
    
    /**
     * Keywords that require real data
     */
    private $data_required_keywords = array(
        'latest', 'current', 'today', 'breaking', 'recent',
        'market conditions', 'what\'s happening', 'news',
        'deals', 'transactions', 'activity', 'updates'
    );
    
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
     * Check if response contains fabricated data
     * 
     * @param array $response
     * @return array Validated response
     */
    public function validate_response($response) {
        // Check message for fabrication
        if (isset($response['message'])) {
            $response['message'] = $this->validate_text($response['message']);
        }
        
        // Check visual data
        if (isset($response['visual']) && is_array($response['visual'])) {
            $response['visual'] = $this->validate_visual($response['visual']);
        }
        
        return $response;
    }
    
    /**
     * Validate text content
     * 
     * @param string $text
     * @return string
     */
    private function validate_text($text) {
        // Check for fabrication patterns
        foreach ($this->fabrication_patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                // Add disclaimer if pattern found
                $text = $this->add_speculation_disclaimer($text);
                break;
            }
        }
        
        return $text;
    }
    
    /**
     * Validate visual data
     * 
     * @param array $visual
     * @return array
     */
    private function validate_visual($visual) {
        if (!isset($visual['type'])) {
            return $visual;
        }
        
        // For market data visuals, ensure real data
        if (in_array($visual['type'], array('market_headlines', 'market_snapshot', 'market_pulse'))) {
            if (isset($visual['data'])) {
                $visual['data'] = $this->validate_market_data($visual['data']);
            }
        }
        
        return $visual;
    }
    
    /**
     * Validate market data
     * 
     * @param array $data
     * @return array
     */
    private function validate_market_data($data) {
        // Check stories/headlines
        if (isset($data['stories']) && is_array($data['stories'])) {
            $validated_stories = array();
            
            foreach ($data['stories'] as $story) {
                if ($this->is_real_story($story)) {
                    $validated_stories[] = $story;
                }
            }
            
            // If no real stories, return null to trigger feed fetch
            if (empty($validated_stories)) {
                return $this->get_real_market_data();
            }
            
            $data['stories'] = $validated_stories;
        }
        
        return $data;
    }
    
    /**
     * Check if story is real
     * 
     * @param array $story
     * @return bool
     */
    private function is_real_story($story) {
        // Must have source and timestamp
        if (empty($story['source']) || empty($story['time'])) {
            return false;
        }
        
        // Check headline for fabrication
        $headline = $story['headline'] ?? '';
        foreach ($this->fabrication_patterns as $pattern) {
            if (preg_match($pattern, $headline)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get real market data from feeds
     * 
     * @return array
     */
    private function get_real_market_data() {
        require_once SFFC_PLUGIN_DIR . 'includes/class-market-feed-manager.php';
        $feed_manager = SFFC_Market_Feed_Manager::get_instance();
        
        $intelligence = $feed_manager->get_market_intelligence('', 10);
        
        if (!empty($intelligence['items'])) {
            return array(
                'headline' => 'Live Market Intelligence',
                'timestamp' => current_time('g:i A'),
                'source' => 'Verified Feeds',
                'items' => $intelligence['items']
            );
        }
        
        return array(
            'headline' => 'Market Update',
            'message' => 'Loading real-time data...',
            'loading' => true
        );
    }
    
    /**
     * Add speculation disclaimer
     * 
     * @param string $text
     * @return string
     */
    private function add_speculation_disclaimer($text) {
        // Don't add multiple disclaimers
        if (strpos($text, 'Note:') !== false || strpos($text, 'Based on') !== false) {
            return $text;
        }
        
        // Add contextual disclaimer
        $disclaimer = "\n\n*Note: This is analysis based on market patterns. For real-time verified data, I'll fetch the latest market feeds.*";
        
        return $text . $disclaimer;
    }
    
    /**
     * Check if query requires real data
     * 
     * @param string $query
     * @return bool
     */
    public function requires_real_data($query) {
        $query_lower = strtolower($query);
        
        foreach ($this->data_required_keywords as $keyword) {
            if (strpos($query_lower, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Enhanced prompt to prevent fabrication
     * 
     * @param string $prompt
     * @return string
     */
    public function enhance_prompt($prompt) {
        $anti_fabrication = "

CRITICAL INSTRUCTIONS:
1. NEVER invent specific company names, deal values, or market events
2. If asked about specific recent events, say 'Let me fetch the latest verified data for you'
3. For market analysis, use phrases like 'Based on typical patterns' or 'Generally speaking'
4. DO NOT create fake headlines or stories
5. If you don't have real data, respond conversationally about general concepts
6. Always indicate when you're providing analysis vs. reporting facts

ACCEPTABLE RESPONSES:
✓ 'Let me get you the latest verified market data'
✓ 'Based on typical market patterns...'
✓ 'Generally, private equity firms focus on...'
✓ 'I'll fetch real-time updates for you'

UNACCEPTABLE RESPONSES:
✗ 'KKR announced a $2B fund' (unless verified)
✗ 'Blackstone expanded its London office' (unless verified)
✗ 'Markets surged 3.2% today' (unless verified)
✗ Creating specific firm names or deal values

Remember: Users want REAL market intelligence, not fabricated stories.";
        
        return $prompt . $anti_fabrication;
    }
    
    /**
     * Filter Claude response to remove fabrications
     * 
     * @param string $response
     * @return string
     */
    public function filter_response($response) {
        // Remove lines that look like fake headlines
        $lines = explode("\n", $response);
        $filtered = array();
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip if matches fabrication pattern
            $is_fabricated = false;
            foreach ($this->fabrication_patterns as $pattern) {
                if (preg_match($pattern, $line)) {
                    $is_fabricated = true;
                    break;
                }
            }
            
            if (!$is_fabricated && !empty($line)) {
                $filtered[] = $line;
            }
        }
        
        $filtered_response = implode("\n", $filtered);
        
        // If we removed too much, provide alternative
        if (strlen($filtered_response) < 50) {
            return "I'll fetch you the latest verified market intelligence instead of speculating. One moment...";
        }
        
        return $filtered_response;
    }
    
    /**
     * Generate safe fallback response
     * 
     * @param string $query
     * @return array
     */
    public function get_safe_fallback($query) {
        return array(
            'message' => $this->generate_safe_message($query),
            'visual' => array(
                'type' => 'news_cards',
                'loading' => true
            ),
            'typing_delay' => 1000
        );
    }
    
    /**
     * Generate safe conversational message
     * 
     * @param string $query
     * @return string
     */
    private function generate_safe_message($query) {
        $query_lower = strtolower($query);
        
        if (strpos($query_lower, 'market') !== false) {
            return "I'm fetching the latest verified market intelligence from our trusted sources. This includes real-time updates from Bloomberg, PE Wire, and other authoritative feeds...";
        }
        
        if (strpos($query_lower, 'deal') !== false || strpos($query_lower, 'private equity') !== false) {
            return "Let me pull the latest private equity deal activity from our verified sources. I'll show you actual transactions with source attribution...";
        }
        
        if (strpos($query_lower, 'news') !== false || strpos($query_lower, 'latest') !== false) {
            return "Accessing real-time news feeds to bring you verified, up-to-date information with proper source attribution...";
        }
        
        return "I'm gathering verified data to answer your question accurately. This ensures you receive real market intelligence rather than speculation...";
    }
}

// Initialize
SFFC_Real_Data_Enforcer::get_instance();