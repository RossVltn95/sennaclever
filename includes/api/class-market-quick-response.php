<?php
/**
 * Quick Response System - Instant responses without Claude
 * 
 * @package SennaCareers
 * @since 3.5.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Market_Quick_Response {
    
    /**
     * Instance
     */
    private static $instance = null;
    
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
     * Check if query can be handled instantly (without Claude)
     */
    public function can_handle_instantly($query) {
        $instant_patterns = array(
            // Market overview queries
            '/^(what\'s|whats|what is).*(happening|going on|news).*(market|markets|today)/i',
            '/^(show|get|display).*(market|markets|news|headlines)/i',
            '/^market (news|update|headlines|summary)/i',
            
            // PE specific
            '/^(show|get).*(pe|private equity) (deals|news|activity)/i',
            '/^latest.*(deals|transactions|buyouts)/i',
            
            // Sector queries
            '/^(tech|healthcare|finance|energy) (news|updates|stocks)/i',
            
            // Simple greetings
            '/^(hi|hello|hey|good morning)/i',
            '/^help$/i'
        );
        
        foreach ($instant_patterns as $pattern) {
            if (preg_match($pattern, trim($query))) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate instant response (no Claude needed)
     */
    public function generate_instant_response($query, $context = array()) {
        $query_lower = strtolower(trim($query));
        
        // Market overview
        if (strpos($query_lower, 'market') !== false || strpos($query_lower, 'happening') !== false) {
            return $this->get_market_overview_response();
        }
        
        // PE specific
        if (strpos($query_lower, 'private equity') !== false || strpos($query_lower, ' pe ') !== false) {
            return $this->get_pe_focused_response();
        }
        
        // Sector specific
        if (preg_match('/(tech|healthcare|finance|energy|infrastructure)/i', $query, $matches)) {
            return $this->get_sector_response($matches[1]);
        }
        
        // Greeting
        if (preg_match('/^(hi|hello|hey)/i', $query)) {
            return $this->get_greeting_response($context);
        }
        
        // Default to market overview
        return $this->get_market_overview_response();
    }
    
    /**
     * Market overview response - INSTANT
     */
    private function get_market_overview_response() {
        // Get feed manager
        require_once SFFC_PLUGIN_DIR . 'includes/class-market-feed-manager.php';
        $feed_manager = SFFC_Market_Feed_Manager::get_instance();
        
        // Get latest headlines (cached for 15 mins)
        $intelligence = $feed_manager->get_market_intelligence('', 20);
        
        // Quick analysis of top stories
        $pe_count = 0;
        $ma_count = 0;
        $market_count = 0;
        
        foreach ($intelligence['items'] as $item) {
            $text = strtolower($item['title'] . ' ' . $item['description']);
            if (strpos($text, 'private equity') !== false || strpos($text, 'buyout') !== false) {
                $pe_count++;
            }
            if (strpos($text, 'merger') !== false || strpos($text, 'acquisition') !== false) {
                $ma_count++;
            }
            if (strpos($text, 'market') !== false || strpos($text, 'stock') !== false) {
                $market_count++;
            }
        }
        
        // Build contextual message based on actual content
        $message = "Here's your real-time market intelligence:\n\n";
        
        if ($pe_count > 3) {
            $message .= "📊 Strong PE activity today with {$pe_count} new developments. ";
        }
        if ($ma_count > 2) {
            $message .= "🔄 M&A momentum continues with several transactions. ";
        }
        if ($market_count > 5) {
            $message .= "📈 Markets showing significant movement across sectors.";
        }
        
        if ($pe_count == 0 && $ma_count == 0) {
            $message .= "📰 Latest market updates across all sectors below.";
        }
        
        // Only show visual for initial query or explicit request
        $visual = null;
        if (isset($context['is_initial_query']) && $context['is_initial_query']) {
            $visual = array(
                'type' => 'newspaper_display',  // Use newspaper instead of news_cards
                'data' => array(
                    'market_intel' => $intelligence,
                    'container_id' => 'sffc-newspaper-' . uniqid(),
                    'user_name' => $context['user_first_name'] ?? null
                )
            );
        }
        
        return array(
            'message' => $message,
            'visual' => $visual,
            'typing_delay' => 500,  // Quick response
            'instant' => true,      // Flag that this was instant
            'follow_up' => array(
                'Analyze the PE deals in detail',
                'What do these trends mean for investors?',
                'Show me more about ' . ($pe_count > 3 ? 'private equity' : 'market movements')
            )
        );
    }
    
    /**
     * PE focused response
     */
    private function get_pe_focused_response() {
        require_once SFFC_PLUGIN_DIR . 'includes/class-market-feed-manager.php';
        $feed_manager = SFFC_Market_Feed_Manager::get_instance();
        
        // Get PE-specific feeds
        $intelligence = $feed_manager->get_market_intelligence('private equity buyout LBO', 15);
        
        $message = "📊 Private Equity Intelligence Dashboard\n\n";
        $message .= "Displaying latest PE deals, fund raises, and exit activity from leading sources.";
        
        // Only show visual if explicitly requested (contains "show", "display", etc.)
        $visual = null;
        if (preg_match('/show|display|list|what are/i', $context['query'] ?? '')) {
            $visual = array(
                'type' => 'pe_deals_dashboard',  // Specific PE visual type
                'data' => $intelligence
            );
        }
        
        return array(
            'message' => $message,
            'visual' => $visual,
            'typing_delay' => 500,
            'instant' => true
        );
    }
    
    /**
     * Sector specific response
     */
    private function get_sector_response($sector) {
        require_once SFFC_PLUGIN_DIR . 'includes/class-market-feed-manager.php';
        $feed_manager = SFFC_Market_Feed_Manager::get_instance();
        
        $intelligence = $feed_manager->get_market_intelligence($sector, 15);
        
        $sector_title = ucfirst($sector);
        $message = "📈 {$sector_title} Sector Update\n\n";
        $message .= "Latest developments and market movements in {$sector_title}:";
        
        // Only show visual if explicitly requested
        $visual = null;
        if (preg_match('/show|display|analyze|breakdown/i', $context['query'] ?? '')) {
            $visual = array(
                'type' => 'sector_analysis',  // Specific sector visual
                'data' => array(
                    'sector' => $sector_title,
                    'intelligence' => $intelligence
                )
            );
        }
        
        return array(
            'message' => $message,
            'visual' => $visual,
            'typing_delay' => 500,
            'instant' => true
        );
    }
    
    /**
     * Greeting response
     */
    private function get_greeting_response($context) {
        $user_name = isset($context['user_first_name']) ? $context['user_first_name'] : '';
        $greeting = !empty($user_name) ? "Hi {$user_name}" : "Hello";
        
        $hour = date('G');
        $time_greeting = ($hour < 12) ? "Good morning" : (($hour < 17) ? "Good afternoon" : "Good evening");
        
        $message = "{$time_greeting}! I'm MENA Careers, ready with today's market intelligence.\n\n";
        $message .= "I can show you:\n";
        $message .= "• Real-time market updates\n";
        $message .= "• Private equity deals\n";
        $message .= "• Sector analysis\n";
        $message .= "• Career opportunities\n\n";
        $message .= "What would you like to explore?";
        
        return array(
            'message' => $message,
            'visual' => null,  // No visual for greeting
            'typing_delay' => 300,
            'instant' => true,
            'follow_up' => array(
                "Show me today's market headlines",
                "What PE deals are happening?",
                "I need career advice"
            )
        );
    }
}