<?php
/**
 * Intelligent Query Handler - Smart wildcard processing
 * Provides instant contextual responses based on query analysis
 * 
 * @package SennaCareers
 * @since 4.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Intelligent_Query_Handler {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Query patterns and their intelligent responses
     */
    private $query_patterns = array();
    
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
        $this->init_patterns();
    }
    
    /**
     * Initialize query patterns
     */
    private function init_patterns() {
        $this->query_patterns = array(
            // Job/Role queries
            'roles_jobs' => array(
                'patterns' => array('/\b(role|roles|job|jobs|position|positions|opening|openings)\b/i'),
                'keywords' => array('market', 'finance', 'pe', 'vc', 'analyst', 'associate', 'banking'),
                'handler' => 'handle_job_query'
            ),
            
            // Salary/Compensation
            'compensation' => array(
                'patterns' => array('/\b(salary|salaries|compensation|pay|bonus|bonuses|package)\b/i'),
                'keywords' => array('analyst', 'associate', 'vp', 'director', 'md'),
                'handler' => 'handle_compensation_query'
            ),
            
            // Career advice
            'career' => array(
                'patterns' => array('/\b(career|path|progression|move|transition|switch)\b/i'),
                'keywords' => array('from', 'to', 'into', 'banking', 'pe', 'consulting'),
                'handler' => 'handle_career_query'
            ),
            
            // Skills/Learning
            'skills' => array(
                'patterns' => array('/\b(learn|skill|skills|model|modeling|course|training)\b/i'),
                'keywords' => array('lbo', 'dcf', 'valuation', 'excel', 'financial'),
                'handler' => 'handle_skills_query'
            ),
            
            // Market/Deal queries
            'market' => array(
                'patterns' => array('/\b(market|markets|deal|deals|transaction|ma|merger|acquisition)\b/i'),
                'keywords' => array('today', 'latest', 'recent', 'news', 'update'),
                'handler' => 'handle_market_query'
            ),
            
            // Firm/Company specific
            'firms' => array(
                'patterns' => array('/\b(blackstone|kkr|apollo|carlyle|goldman|morgan|jpmorgan|bofa|citi)\b/i'),
                'keywords' => array(),
                'handler' => 'handle_firm_query'
            ),
            
            // Interview prep
            'interview' => array(
                'patterns' => array('/\b(interview|interviews|prepare|preparation|questions|case)\b/i'),
                'keywords' => array('pe', 'ib', 'banking', 'finance', 'technical'),
                'handler' => 'handle_interview_query'
            )
        );
    }
    
    /**
     * Process query and generate intelligent response
     */
    public function process_query($query, $mode = 'market', $context = array()) {
        $query_lower = strtolower(trim($query));
        
        // Analyze query to understand intent
        $intent = $this->analyze_intent($query_lower);
        
        // Get contextual data
        $contextual_data = $this->get_contextual_data($intent, $query_lower);
        
        // Generate response based on intent
        $response = $this->generate_intelligent_response($intent, $query, $contextual_data, $context);
        
        return $response;
    }
    
    /**
     * Analyze query intent
     */
    private function analyze_intent($query_lower) {
        $matched_intents = array();
        
        foreach ($this->query_patterns as $intent_name => $pattern_data) {
            foreach ($pattern_data['patterns'] as $pattern) {
                if (preg_match($pattern, $query_lower)) {
                    $score = 1;
                    
                    // Boost score if keywords match
                    foreach ($pattern_data['keywords'] as $keyword) {
                        if (strpos($query_lower, $keyword) !== false) {
                            $score++;
                        }
                    }
                    
                    $matched_intents[$intent_name] = $score;
                }
            }
        }
        
        if (empty($matched_intents)) {
            return 'general';
        }
        
        // Return highest scoring intent
        arsort($matched_intents);
        return key($matched_intents);
    }
    
    /**
     * Get contextual data based on intent
     */
    private function get_contextual_data($intent, $query) {
        $data = array();
        
        // Extract specific elements from query
        if ($intent === 'roles_jobs') {
            // Extract level (analyst, associate, VP, etc.)
            if (preg_match('/\b(analyst|associate|vp|vice president|director|md|managing director)\b/i', $query, $matches)) {
                $data['level'] = $matches[1];
            }
            
            // Extract sector
            if (preg_match('/\b(pe|private equity|vc|venture|banking|ib|hedge fund|asset management)\b/i', $query, $matches)) {
                $data['sector'] = $matches[1];
            }
        }
        
        if ($intent === 'firms') {
            // Extract firm name
            if (preg_match('/\b(blackstone|kkr|apollo|carlyle|goldman|morgan|jpmorgan|bofa|citi)\b/i', $query, $matches)) {
                $data['firm'] = $matches[1];
            }
        }
        
        return $data;
    }
    
    /**
     * Generate intelligent response
     */
    private function generate_intelligent_response($intent, $original_query, $contextual_data, $context) {
        $user_name = isset($context['user_first_name']) ? $context['user_first_name'] : '';
        
        switch ($intent) {
            case 'roles_jobs':
                return $this->handle_job_query($original_query, $contextual_data, $user_name);
                
            case 'compensation':
                return $this->handle_compensation_query($original_query, $contextual_data, $user_name);
                
            case 'career':
                return $this->handle_career_query($original_query, $contextual_data, $user_name);
                
            case 'skills':
                return $this->handle_skills_query($original_query, $contextual_data, $user_name);
                
            case 'market':
                return $this->handle_market_query($original_query, $contextual_data, $user_name);
                
            case 'firms':
                return $this->handle_firm_query($original_query, $contextual_data, $user_name);
                
            case 'interview':
                return $this->handle_interview_query($original_query, $contextual_data, $user_name);
                
            default:
                return $this->handle_general_query($original_query, $contextual_data, $user_name);
        }
    }
    
    /**
     * Handle job/role queries like "market roles"
     */
    private function handle_job_query($query, $data, $user_name) {
        $greeting = $user_name ? "Hi {$user_name}" : "I see";
        
        // Build specific response based on what they mentioned
        $level = isset($data['level']) ? $data['level'] : null;
        $sector = isset($data['sector']) ? $data['sector'] : null;
        
        if (strpos(strtolower($query), 'market') !== false && strpos(strtolower($query), 'role') !== false) {
            // "market roles" query
            $message = "{$greeting}, you're exploring roles in the market. Let me help you navigate the opportunities!\n\n";
            $message .= "I can show you:\n";
            $message .= "📊 **Current hot roles**: PE Associate, Growth Equity Analyst, Credit Analyst\n";
            $message .= "💼 **Sectors hiring now**: Private Equity, Venture Capital, Private Credit\n";
            $message .= "💰 **Comp ranges**: $100k-250k+ depending on level\n\n";
            $message .= "**To personalize your search, tell me:**\n";
            $message .= "• Your current role/experience level?\n";
            $message .= "• Target sectors (PE, VC, Private Credit, IB)?\n";
            $message .= "• Location preference?\n";
            $message .= "• Are you making a lateral move or stepping up?";
            
            $follow_up = array(
                "I'm an analyst looking for PE associate roles",
                "Show me VP level opportunities in venture capital",
                "What skills do I need for private equity?"
            );
        } elseif ($level) {
            $message = "{$greeting}, looking for {$level} positions! The market is active for {$level} talent.\n\n";
            $message .= $this->get_level_specific_insights($level);
            $message .= "\n**Quick questions to refine your search:**\n";
            $message .= "• Years of experience?\n";
            $message .= "• Current firm type?\n";
            $message .= "• Geographic flexibility?";
            
            $follow_up = array(
                "Show me {$level} openings at top PE firms",
                "What's the typical comp for {$level}?",
                "How do I stand out for {$level} roles?"
            );
        } else {
            $message = "{$greeting}, let's find the right opportunities for you!\n\n";
            $message .= "**The market is particularly strong for:**\n";
            $message .= "• Private Equity Associates (2-4 years IB experience)\n";
            $message .= "• Credit Analysts (distressed debt focus)\n";
            $message .= "• Growth Equity VPs (tech sector expertise)\n\n";
            $message .= "**What describes you best?**";
            
            $follow_up = array(
                "I'm in investment banking looking to move to PE",
                "Show me entry-level finance roles",
                "I want to transition from consulting to finance"
            );
        }
        
        return array(
            'message' => $message,
            'visual' => null, // Will fetch relevant job listings
            'typing_delay' => 500,
            'instant' => true,
            'follow_up' => $follow_up,
            'trigger_claude' => true  // Get Claude to provide detailed analysis after
        );
    }
    
    /**
     * Handle compensation queries
     */
    private function handle_compensation_query($query, $data, $user_name) {
        $message = "Let me pull the latest compensation data for you.\n\n";
        $message .= "**2024 Finance Compensation Ranges:**\n";
        $message .= "📊 **Investment Banking:**\n";
        $message .= "• Analyst 1: $100-110k base + 70-100% bonus\n";
        $message .= "• Associate: $175-225k + 100-120% bonus\n";
        $message .= "• VP: $275-350k + 120-150% bonus\n\n";
        $message .= "🎯 **Private Equity:**\n";
        $message .= "• Associate: $150-200k + 100-150% bonus + carry\n";
        $message .= "• Senior Associate: $200-250k + bonus + carry\n";
        $message .= "• Principal: $300-400k + significant carry\n\n";
        $message .= "**What specific comp info do you need?**";
        
        return array(
            'message' => $message,
            'visual' => array('type' => 'compensation_table'),
            'typing_delay' => 500,
            'instant' => true,
            'follow_up' => array(
                "How do I negotiate PE compensation?",
                "What's the carry structure at mega funds?",
                "Compare IB vs PE total comp over 5 years"
            )
        );
    }
    
    /**
     * Handle market queries
     */
    private function handle_market_query($query, $data, $user_name) {
        // Use the feed system for instant market data
        require_once SFFC_PLUGIN_DIR . 'includes/class-market-feed-manager.php';
        $feed_manager = SFFC_Market_Feed_Manager::get_instance();
        
        $intelligence = $feed_manager->get_market_intelligence($query, 15);
        
        $message = "Here's what's moving in the markets right now:\n\n";
        
        // Quick analysis of actual headlines
        if (!empty($intelligence['items'])) {
            $pe_deals = 0;
            $ma_activity = 0;
            
            foreach ($intelligence['items'] as $item) {
                if (stripos($item['title'], 'private equity') !== false) $pe_deals++;
                if (stripos($item['title'], 'acquisition') !== false) $ma_activity++;
            }
            
            if ($pe_deals > 0) {
                $message .= "🔥 {$pe_deals} new PE developments today\n";
            }
            if ($ma_activity > 0) {
                $message .= "🎯 {$ma_activity} M&A transactions announced\n";
            }
        }
        
        return array(
            'message' => $message,
            'visual' => array(
                'type' => 'news_cards',
                'data' => $intelligence
            ),
            'typing_delay' => 300,
            'instant' => true,
            'follow_up' => array(
                "What do these deals mean for the market?",
                "Show me more PE activity",
                "Analyze sector trends"
            )
        );
    }
    
    /**
     * Handle firm-specific queries
     */
    private function handle_firm_query($query, $data, $user_name) {
        $firm = isset($data['firm']) ? ucfirst($data['firm']) : 'the firm';
        
        $message = "Let me get you the latest on {$firm}.\n\n";
        $message .= "**What aspect interests you?**\n";
        $message .= "• Recent deals and investments\n";
        $message .= "• Hiring and culture\n";
        $message .= "• Compensation and career progression\n";
        $message .= "• Interview process and preparation\n";
        
        return array(
            'message' => $message,
            'visual' => null,
            'typing_delay' => 500,
            'instant' => true,
            'follow_up' => array(
                "Show me {$firm}'s recent deals",
                "How do I get hired at {$firm}?",
                "What's the culture like at {$firm}?"
            ),
            'trigger_claude' => true
        );
    }
    
    /**
     * Handle general/unclear queries
     */
    private function handle_general_query($query, $data, $user_name) {
        // Don't just say "tell me more" - make educated guesses
        $query_lower = strtolower($query);
        
        $message = "I can help with that! Based on '{$query}', here are a few directions we could explore:\n\n";
        
        // Guess possible intents
        if (strlen($query) < 20) {
            // Short query - likely looking for quick info
            $message .= "📊 **Quick Access:**\n";
            $message .= "• Latest market headlines\n";
            $message .= "• Today's PE deals\n";
            $message .= "• Career opportunities\n";
            $message .= "• Skill development resources\n\n";
            $message .= "**Or tell me more specifically what you're looking for?**";
        } else {
            // Longer query - extract key topics
            $message .= "Let me address your question about {$query}...";
        }
        
        return array(
            'message' => $message,
            'visual' => null,
            'typing_delay' => 500,
            'instant' => true,
            'follow_up' => array(
                "Show me market updates",
                "I need career advice",
                "Help with financial modeling"
            ),
            'trigger_claude' => true
        );
    }
    
    /**
     * Get level-specific insights
     */
    private function get_level_specific_insights($level) {
        $insights = array(
            'analyst' => "**Analyst opportunities are hot right now!**\n• 200+ openings across PE/VC/HF\n• Average comp: $100-150k + bonus\n• Key skills: Financial modeling, PowerPoint, deal sourcing",
            'associate' => "**Associate demand is at an all-time high!**\n• PE firms fighting for IB talent\n• Comp: $175-250k + bonus + carry\n• 2-4 years IB experience typically required",
            'vp' => "**VP roles offering significant upside!**\n• Focus on deal execution and team building\n• Comp: $350-500k all-in\n• Path to Partner in 3-5 years at many firms",
            'director' => "**Director positions are selective but rewarding!**\n• Deal sourcing and LP relations focus\n• Comp: $500k-1M+ with carry\n• Strong network essential"
        );
        
        $level_lower = strtolower($level);
        foreach ($insights as $key => $insight) {
            if (strpos($level_lower, $key) !== false) {
                return $insight;
            }
        }
        
        return "**Strong demand across all levels in current market!**";
    }
    
    /**
     * Handle skills/learning queries
     */
    private function handle_skills_query($query, $data, $user_name) {
        $message = "Let's build the skills that matter!\n\n";
        $message .= "**Top skills in demand:**\n";
        $message .= "💡 **Technical:**\n";
        $message .= "• LBO Modeling (PE must-have)\n";
        $message .= "• DCF & Comps Analysis\n";
        $message .= "• Financial Statement Modeling\n\n";
        $message .= "🎯 **Soft Skills:**\n";
        $message .= "• Deal sourcing & networking\n";
        $message .= "• Client management\n";
        $message .= "• Investment thesis development\n\n";
        $message .= "**What skill would accelerate your career most?**";
        
        return array(
            'message' => $message,
            'visual' => null,
            'typing_delay' => 500,
            'instant' => true,
            'follow_up' => array(
                "Teach me LBO modeling step-by-step",
                "How do I build a 3-statement model?",
                "What technical skills do PE firms test?"
            )
        );
    }
    
    /**
     * Handle interview prep queries
     */
    private function handle_interview_query($query, $data, $user_name) {
        $message = "Let's ace that interview!\n\n";
        $message .= "**Interview prep resources:**\n";
        $message .= "📝 **Technical Questions:**\n";
        $message .= "• Walk me through an LBO\n";
        $message .= "• Paper LBO exercises\n";
        $message .= "• Valuation methodologies\n\n";
        $message .= "💭 **Behavioral Questions:**\n";
        $message .= "• Why PE/VC/HF?\n";
        $message .= "• Deal experience stories\n";
        $message .= "• Leadership examples\n\n";
        $message .= "**What stage of prep are you in?**";
        
        return array(
            'message' => $message,
            'visual' => null,
            'typing_delay' => 500,
            'instant' => true,
            'follow_up' => array(
                "I have a PE interview next week",
                "Common technical questions for associates",
                "How to prepare a stock pitch"
            )
        );
    }
    
    /**
     * Handle career transition queries
     */
    private function handle_career_query($query, $data, $user_name) {
        $message = "Let's map out your career move!\n\n";
        $message .= "**Common successful transitions:**\n";
        $message .= "🎯 **Into PE:**\n";
        $message .= "• IB Analyst/Associate → PE Associate\n";
        $message .= "• Consulting → PE Operations\n";
        $message .= "• Corporate Dev → PE/Growth Equity\n\n";
        $message .= "📈 **Career progression paths:**\n";
        $message .= "• IB → PE → Portfolio Company exec\n";
        $message .= "• Big 4 → MM PE → MBA → MF PE\n";
        $message .= "• VC analyst → Principal → Partner track\n\n";
        $message .= "**What's your current situation and target?**";
        
        return array(
            'message' => $message,
            'visual' => null,
            'typing_delay' => 500,
            'instant' => true,
            'follow_up' => array(
                "I'm in IB and want to move to PE",
                "Best path from consulting to private equity?",
                "When should I get my MBA for finance?"
            )
        );
    }
}
