<?php
/**
 * Centralized Fallback Manager
 * Coordinates all fallback systems to prevent duplicates and conflicts
 * 
 * @package SennaCareers
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Fallback_Manager {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Fallback priority hierarchy
     */
    private $fallback_priority = array(
        'market_intelligence_service',
        'template_system',
        'static_response'
    );
    
    /**
     * Track if fallback has been used
     */
    private $fallback_used = false;
    
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
        // Initialize dependencies
        $this->init_dependencies();
    }
    
    /**
     * Initialize dependencies
     */
    private function init_dependencies() {
        require_once SFFC_PLUGIN_DIR . 'includes/interfaces/interface-response-format.php';
        require_once SFFC_PLUGIN_DIR . 'includes/services/class-market-intelligence-service.php';
    }
    
    /**
     * Get fallback response using priority hierarchy
     * 
     * @param string $query User query
     * @param string $mode Current mode
     * @param array $context Query context
     * @return array|null Fallback response or null
     */
    public function get_fallback_response($query, $mode, $context = array()) {
        // Prevent multiple fallback systems from running
        if ($this->fallback_used) {
            return null;
        }
        
        $formatter = SFFC_Response_Formatter::get_instance();
        $response = null;
        
        // Try fallback systems in priority order
        foreach ($this->fallback_priority as $fallback_type) {
            switch ($fallback_type) {
                case 'market_intelligence_service':
                    if ($mode === 'market') {
                        $response = $this->get_market_intelligence_fallback($query, $context);
                    }
                    break;
                    
                case 'template_system':
                    $response = $this->get_template_fallback($query, $mode, $context);
                    break;
                    
                case 'static_response':
                    $response = $this->get_static_fallback($mode);
                    break;
            }
            
            if ($response !== null) {
                $this->fallback_used = true;
                return $response;
            }
        }
        
        // Last resort fallback
        $this->fallback_used = true;
        return $formatter->format_fallback(
            "I'm having trouble processing your request right now. Please try again in a moment.",
            null
        );
    }
    
    /**
     * Get Market Intelligence Service fallback
     * 
     * @param string $query User query
     * @param array $context Query context
     * @return array|null Fallback response
     */
    private function get_market_intelligence_fallback($query, $context) {
        $market_service = SFFC_Market_Intelligence_Service::get_instance();
        $formatter = SFFC_Response_Formatter::get_instance();
        
        // Get market headlines
        $headlines = $market_service->get_market_headlines();
        
        if (!empty($headlines)) {
            $message = "Here's the latest market intelligence:\n\n";
            
            if (isset($headlines['data']['stories']) && !empty($headlines['data']['stories'])) {
                foreach ($headlines['data']['stories'] as $story) {
                    $message .= "• " . $story['headline'] . "\n";
                }
            }
            
            return $formatter->format_fallback($message, $headlines);
        }
        
        return null;
    }
    
    /**
     * Get template system fallback
     * 
     * @param string $query User query
     * @param string $mode Current mode
     * @param array $context Query context
     * @return array|null Fallback response
     */
    private function get_template_fallback($query, $mode, $context) {
        $formatter = SFFC_Response_Formatter::get_instance();
        
        // Determine query type
        $query_lower = strtolower($query);
        
        // Greeting queries
        if (preg_match('/^(hi|hello|hey)/i', $query)) {
            $greeting = $this->get_greeting($context);
            $message = "{$greeting} I'm here to help with your finance career. What would you like to explore?";
            
            return $formatter->format_fallback($message, null);
        }
        
        // Market queries - return interactive options
        if ($mode === 'market' && preg_match('/market|stock|trading|analysis/i', $query)) {
            $message = "Let me analyze the markets for you. What specific area would you like to explore?";
            
            $visual = array(
                'type' => 'interactive_options',
                'data' => array(
                    'title' => 'Market Intelligence Options',
                    'options' => array(
                        array('icon' => '→', 'label' => 'Global Markets Overview', 'query' => 'Show me global market overview'),
                        array('icon' => '→', 'label' => 'Sector Performance', 'query' => 'Analyze sector performance today'),
                        array('icon' => '→', 'label' => 'PE/VC Activity', 'query' => 'Latest private equity activity'),
                        array('icon' => '→', 'label' => 'Market Volatility', 'query' => 'Analyze current market volatility')
                    )
                )
            );
            
            return array(
                'message' => $message,
                'visual' => $visual,
                'typing_delay' => 400,
                'source' => 'fallback'
            );
        }
        
        // Career queries
        if ($mode === 'career' || preg_match('/career|job|resume|interview/i', $query)) {
            $message = "I can help you with your finance career journey. Here are some areas we can explore:\n\n";
            $message .= "• Resume optimization for finance roles\n";
            $message .= "• Interview preparation strategies\n";
            $message .= "• Career path planning\n";
            $message .= "• Networking in finance\n\n";
            $message .= "What would you like to focus on?";
            
            return $formatter->format_fallback($message, null);
        }
        
        return null;
    }
    
    /**
     * Get static fallback response
     * 
     * @param string $mode Current mode
     * @return array Static fallback
     */
    private function get_static_fallback($mode) {
        // Always return interactive options, never generic text
        $options_by_mode = array(
            'market' => array(
                'title' => 'Market Intelligence Hub',
                'options' => array(
                    array('icon' => '→', 'label' => 'Market Overview', 'query' => 'Show current market overview'),
                    array('icon' => '→', 'label' => 'Key Movers', 'query' => 'What are today\'s key market movers?'),
                    array('icon' => '→', 'label' => 'Sector Analysis', 'query' => 'Analyze sector performance'),
                    array('icon' => '→', 'label' => 'Market News', 'query' => 'Latest market news and events')
                )
            ),
            'career' => array(
                'title' => 'Career Development Options',
                'options' => array(
                    array('icon' => '→', 'label' => 'Career Path', 'query' => 'Help me plan my finance career path'),
                    array('icon' => '→', 'label' => 'Resume Review', 'query' => 'Review my finance resume'),
                    array('icon' => '→', 'label' => 'Interview Prep', 'query' => 'Prepare for finance interviews'),
                    array('icon' => '→', 'label' => 'Networking', 'query' => 'Finance networking strategies')
                )
            ),
            'skills' => array(
                'title' => 'Skill Development Areas',
                'options' => array(
                    array('icon' => '→', 'label' => 'Financial Modeling', 'query' => 'Learn financial modeling'),
                    array('icon' => '→', 'label' => 'Valuation', 'query' => 'Master valuation techniques'),
                    array('icon' => '→', 'label' => 'LBO Modeling', 'query' => 'Build LBO models'),
                    array('icon' => '→', 'label' => 'DCF Analysis', 'query' => 'Learn DCF analysis')
                )
            ),
            'opportunities' => array(
                'title' => 'Opportunity Types',
                'options' => array(
                    array('icon' => '→', 'label' => 'Investment Banking', 'query' => 'IB opportunities for me'),
                    array('icon' => '→', 'label' => 'Private Equity', 'query' => 'PE opportunities matching my profile'),
                    array('icon' => '→', 'label' => 'Middle East PE', 'query' => 'Private equity opportunities in Dubai Abu Dhabi Riyadh Cairo'),
                    array('icon' => '→', 'label' => 'Finance Roles', 'query' => 'Finance opportunities in Dubai Abu Dhabi Riyadh Cairo')
                )
            )
        );
        
        $mode_key = isset($options_by_mode[$mode]) ? $mode : 'market';
        $message = "Let me help you explore your options:";
        
        return array(
            'message' => $message,
            'visual' => array(
                'type' => 'interactive_options',
                'data' => $options_by_mode[$mode_key]
            ),
            'typing_delay' => 400,
            'source' => 'static_fallback'
        );
    }
    
    /**
     * Get appropriate greeting
     * 
     * @param array $context User context
     * @return string Greeting
     */
    private function get_greeting($context) {
        $hour = date('H');
        $name = isset($context['user_first_name']) ? $context['user_first_name'] : '';
        
        if ($hour < 12) {
            $greeting = "Good morning";
        } elseif ($hour < 17) {
            $greeting = "Good afternoon";
        } else {
            $greeting = "Good evening";
        }
        
        if (!empty($name)) {
            $greeting .= " {$name}!";
        } else {
            $greeting .= "!";
        }
        
        return $greeting;
    }
    
    /**
     * Reset fallback tracking
     */
    public function reset() {
        $this->fallback_used = false;
    }
    
    /**
     * Check if fallback has been used
     * 
     * @return bool True if fallback was used
     */
    public function is_fallback_used() {
        return $this->fallback_used;
    }
}

// Initialize the manager
SFFC_Fallback_Manager::get_instance();
