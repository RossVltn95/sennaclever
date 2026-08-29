<?php
/**
 * Professional Market Greeting Variations
 * 
 * @package SennaCareers
 * @since 4.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Market_Greeting_Variations {
    
    /**
     * Get professional greeting based on query type
     */
    public static function get_professional_greeting($query_type, $context = array()) {
        $user_name = isset($context['user_first_name']) && !empty($context['user_first_name']) 
            ? $context['user_first_name'] 
            : '';
        
        $greeting = $user_name ? "Hi {$user_name}" : "Welcome";
        
        // Simple, professional greetings without bizarre phrases
        $greetings = array(
            'market_conditions' => "{$greeting}, here's the current market intelligence.",
            'sector_analysis' => "{$greeting}, let me provide sector-specific insights.",
            'pe_deals' => "{$greeting}, here are the latest private equity developments.",
            'credit_markets' => "{$greeting}, reviewing credit market conditions.",
            'ma_activity' => "{$greeting}, here's current M&A activity.",
            'firm_specific' => "{$greeting}, let me get information on that firm.",
            'default' => "{$greeting}, how can I help you today?"
        );
        
        return isset($greetings[$query_type]) ? $greetings[$query_type] : $greetings['default'];
    }
}