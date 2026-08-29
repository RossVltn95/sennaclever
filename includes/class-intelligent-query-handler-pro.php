<?php
/**
 * Professional Intelligent Query Handler - No emojis, smart visual cards
 * 
 * @package SennaCareers
 * @since 4.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Intelligent_Query_Handler_Pro {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Query patterns
     */
    private $query_patterns = array();
    
    /**
     * Default visual cards for different contexts
     */
    private $default_visuals = array();
    
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
        // Initialize patterns array (was missing)
        $this->query_patterns = array();
        $this->init_default_visuals();
    }
    
    /**
     * Initialize default visual cards for wildcard scenarios
     */
    private function init_default_visuals() {
        $this->default_visuals = array(
            // General market context - always relevant
            'market_pulse' => array(
                'type' => 'market_pulse',
                'title' => 'Market Pulse',
                'description' => 'Key indices and market movements',
                'applicability' => array('market', 'general', 'firms', 'compensation')
            ),
            
            // Career opportunities - broadly applicable
            'trending_roles' => array(
                'type' => 'trending_roles',
                'title' => 'Trending Opportunities',
                'description' => 'Hot roles in finance right now',
                'applicability' => array('roles_jobs', 'career', 'compensation', 'general')
            ),
            
            // Latest headlines - always useful
            'market_headlines' => array(
                'type' => 'market_headlines', 
                'title' => 'Latest Headlines',
                'description' => 'Breaking news from financial markets',
                'applicability' => array('market', 'firms', 'general')
            ),
            
            // Skills radar - development focused
            'skills_radar' => array(
                'type' => 'skills_radar',
                'title' => 'Skills in Demand',
                'description' => 'Top skills employers are seeking',
                'applicability' => array('skills', 'career', 'interview', 'general')
            )
        );
    }
    
    /**
     * Process query with professional formatting
     */
    public function process_query($query, $mode = 'market', $context = array()) {
        $query_lower = strtolower(trim($query));
        
        // Analyze intent
        $intent = $this->analyze_intent($query_lower);
        
        // Get contextual data
        $contextual_data = $this->get_contextual_data($intent, $query_lower);
        
        // Generate professional response
        $response = $this->generate_professional_response($intent, $query, $contextual_data, $context);
        
        // Add appropriate visual card
        $response = $this->add_visual_card($response, $intent, $query);
        
        return $response;
    }
    
    /**
     * Add visual card based on context
     */
    private function add_visual_card($response, $intent, $query) {
        // If response already has specific visual, keep it
        if (isset($response['visual']) && $response['visual'] !== null) {
            return $response;
        }
        
        // Select best default visual for this context
        $visual = $this->select_contextual_visual($intent, $query);
        
        if ($visual) {
            $response['visual'] = $visual;
            $response['visual_strategy'] = 'contextual_default';
        }
        
        return $response;
    }
    
    /**
     * Select the most appropriate visual card for wildcard scenarios
     */
    private function select_contextual_visual($intent, $query) {
        // For market-related queries, always show market data
        if ($intent === 'market' || stripos($query, 'market') !== false) {
            return $this->get_market_headlines_visual();
        }
        
        // For job/role queries, show trending opportunities
        if ($intent === 'roles_jobs' || $intent === 'career') {
            return $this->get_trending_roles_visual();
        }
        
        // For skill queries, show skills radar
        if ($intent === 'skills' || $intent === 'interview') {
            return $this->get_skills_demand_visual();
        }
        
        // Default: Market pulse (always relevant in finance)
        return $this->get_market_pulse_visual();
    }
    
    /**
     * Get market headlines visual (always fresh from feeds)
     */
    private function get_market_headlines_visual() {
        require_once SFFC_PLUGIN_DIR . 'includes/class-market-feed-manager.php';
        $feed_manager = SFFC_Market_Feed_Manager::get_instance();
        
        $intelligence = $feed_manager->get_market_intelligence('', 10);
        
        return array(
            'type' => 'news_cards',
            'data' => $intelligence,
            'title' => 'Latest Market Intelligence'
        );
    }
    
    /**
     * Get market pulse visual
     */
    private function get_market_pulse_visual() {
        return array(
            'type' => 'market_pulse',
            'data' => array(
                'indices' => array(
                    array('name' => 'S&P 500', 'value' => '4,783.45', 'change' => '+0.8%', 'status' => 'up'),
                    array('name' => 'NASDAQ', 'value' => '15,283.02', 'change' => '+1.2%', 'status' => 'up'),
                    array('name' => 'DOW', 'value' => '37,863.80', 'change' => '+0.5%', 'status' => 'up')
                ),
                'sectors' => array(
                    array('name' => 'Technology', 'change' => '+1.8%', 'status' => 'hot'),
                    array('name' => 'Finance', 'change' => '+0.9%', 'status' => 'up'),
                    array('name' => 'Healthcare', 'change' => '-0.3%', 'status' => 'down')
                )
            ),
            'title' => 'Market Overview'
        );
    }
    
    /**
     * Get trending roles visual
     */
    private function get_trending_roles_visual() {
        return array(
            'type' => 'trending_roles',
            'data' => array(
                'hot_roles' => array(
                    array(
                        'title' => 'Private Equity Associate',
                        'demand' => 'Very High',
                        'avg_comp' => '$200k-250k',
                        'openings' => '150+'
                    ),
                    array(
                        'title' => 'Growth Equity VP',
                        'demand' => 'High',
                        'avg_comp' => '$350k-450k',
                        'openings' => '75+'
                    ),
                    array(
                        'title' => 'Credit Analyst',
                        'demand' => 'High',
                        'avg_comp' => '$130k-180k',
                        'openings' => '200+'
                    )
                ),
                'trending_sectors' => array('Private Equity', 'Credit Funds', 'Growth Equity')
            ),
            'title' => 'Opportunities Dashboard'
        );
    }
    
    /**
     * Get skills demand visual
     */
    private function get_skills_demand_visual() {
        return array(
            'type' => 'skills_radar',
            'data' => array(
                'technical' => array(
                    array('skill' => 'LBO Modeling', 'demand' => 95, 'trend' => 'up'),
                    array('skill' => 'DCF Analysis', 'demand' => 88, 'trend' => 'stable'),
                    array('skill' => 'Python/SQL', 'demand' => 76, 'trend' => 'up'),
                    array('skill' => 'Power BI', 'demand' => 68, 'trend' => 'up')
                ),
                'soft' => array(
                    array('skill' => 'Deal Sourcing', 'demand' => 92, 'trend' => 'up'),
                    array('skill' => 'Client Management', 'demand' => 85, 'trend' => 'stable'),
                    array('skill' => 'Leadership', 'demand' => 78, 'trend' => 'up')
                )
            ),
            'title' => 'Skills in Demand'
        );
    }
    
    /**
     * Generate professional response (no emojis)
     */
    private function generate_professional_response($intent, $original_query, $contextual_data, $context) {
        $user_name = isset($context['user_first_name']) ? $context['user_first_name'] : '';
        
        switch ($intent) {
            case 'roles_jobs':
                return $this->handle_job_query_professional($original_query, $contextual_data, $user_name);
            case 'compensation':
                return $this->handle_compensation_query_professional($original_query, $contextual_data, $user_name);
            case 'market':
                return $this->handle_market_query_professional($original_query, $contextual_data, $user_name);
            case 'firms':
                return $this->handle_firm_query_professional($original_query, $contextual_data, $user_name);
            case 'skills':
                return $this->handle_skills_query_professional($original_query, $contextual_data, $user_name);
            case 'career':
                return $this->handle_career_query_professional($original_query, $contextual_data, $user_name);
            case 'interview':
                return $this->handle_interview_query_professional($original_query, $contextual_data, $user_name);
            default:
                return $this->handle_general_query_professional($original_query, $contextual_data, $user_name);
        }
    }
    
    /**
     * Handle job queries professionally
     */
    private function handle_job_query_professional($query, $data, $user_name) {
        $greeting = $user_name ? "Hi {$user_name}" : "I see";
        
        if (strpos(strtolower($query), 'market') !== false && strpos(strtolower($query), 'role') !== false) {
            $message = "{$greeting}, you're exploring roles in the market. Let me provide current insights.\n\n";
            $message .= "Current Market Dynamics:\n\n";
            $message .= "HIGH DEMAND ROLES:\n";
            $message .= "• Private Equity Associate - 2-4 years IB experience required\n";
            $message .= "• Growth Equity Analyst - Tech sector focus preferred\n";
            $message .= "• Credit Analyst - Distressed debt expertise valued\n\n";
            $message .= "COMPENSATION RANGES:\n";
            $message .= "• Analyst: $100k-150k base plus 50-100% bonus\n";
            $message .= "• Associate: $150k-250k base plus 100-150% bonus\n";
            $message .= "• VP: $275k-400k base plus carry participation\n\n";
            $message .= "To refine your search, please share:\n";
            $message .= "• Your current experience level\n";
            $message .= "• Preferred sectors (PE, VC, HF, Credit)\n";
            $message .= "• Geographic preferences\n";
            $message .= "• Timeline for making a move";
            
            $visual = $this->get_trending_roles_visual();
        } else {
            $message = "{$greeting}, let's identify the right opportunities for your profile.\n\n";
            $message .= "The market is particularly active in:\n";
            $message .= "• Private Equity - Record dry powder deployment\n";
            $message .= "• Credit Funds - Distressed opportunities emerging\n";
            $message .= "• Growth Equity - Tech sector consolidation\n\n";
            $message .= "What best describes your situation?";
            
            $visual = $this->get_trending_roles_visual();
        }
        
        return array(
            'message' => $message,
            'visual' => $visual,
            'typing_delay' => 100,
            'instant' => true,
            'follow_up' => array(
                "I'm an analyst with 2 years IB experience",
                "Show me PE associate opportunities",
                "What skills are most important for PE?"
            )
        );
    }
    
    /**
     * Handle compensation queries professionally
     */
    private function handle_compensation_query_professional($query, $data, $user_name) {
        $message = "Here's the current compensation landscape in finance:\n\n";
        $message .= "INVESTMENT BANKING (2024 Base + Bonus):\n";
        $message .= "• Analyst 1: $100-110k + 70-100% bonus\n";
        $message .= "• Analyst 2: $110-125k + 80-110% bonus\n";
        $message .= "• Associate 1: $175-200k + 100-120% bonus\n";
        $message .= "• Associate 2: $200-225k + 110-130% bonus\n";
        $message .= "• VP: $275-350k + 120-150% bonus\n\n";
        $message .= "PRIVATE EQUITY (Base + Bonus + Carry):\n";
        $message .= "• Associate: $150-200k + 100-150% + carry\n";
        $message .= "• Senior Associate: $200-250k + bonus + carry\n";
        $message .= "• VP/Principal: $300-400k + carry (20-30% of fund)\n";
        $message .= "• Partner/MD: $500k-1M+ + significant carry\n\n";
        $message .= "Note: Mega funds typically pay 20-30% premium\n\n";
        $message .= "What specific compensation details would help you?";
        
        return array(
            'message' => $message,
            'visual' => array(
                'type' => 'compensation_grid',
                'data' => 'structured_comp_data'
            ),
            'typing_delay' => 100,
            'instant' => true,
            'follow_up' => array(
                "How does carry work in PE?",
                "Compare IB vs PE lifetime earnings",
                "Negotiation strategies for PE offers"
            )
        );
    }
    
    /**
     * Handle market queries with real data
     */
    private function handle_market_query_professional($query, $data, $user_name) {
        $visual = $this->get_market_headlines_visual();
        
        $message = "Current Market Intelligence:\n\n";
        
        // Analyze actual feed content
        if (isset($visual['data']['items']) && !empty($visual['data']['items'])) {
            $pe_count = 0;
            $ma_count = 0;
            
            foreach ($visual['data']['items'] as $item) {
                if (stripos($item['title'], 'private equity') !== false) $pe_count++;
                if (stripos($item['title'], 'acquisition') !== false) $ma_count++;
            }
            
            if ($pe_count > 0) {
                $message .= "• {$pe_count} new private equity developments\n";
            }
            if ($ma_count > 0) {
                $message .= "• {$ma_count} M&A transactions announced\n";
            }
            
            $message .= "• Multiple sectors showing movement\n\n";
            $message .= "Latest verified updates displayed below.";
        }
        
        return array(
            'message' => $message,
            'visual' => $visual,
            'typing_delay' => 100,
            'instant' => true,
            'follow_up' => array(
                "Analyze these market trends",
                "Show PE-specific activity",
                "What sectors are most active?"
            )
        );
    }
    
    /**
     * Handle firm queries professionally
     */
    private function handle_firm_query_professional($query, $data, $user_name) {
        $firm = isset($data['firm']) ? ucfirst($data['firm']) : 'this firm';
        
        $message = "Information on {$firm}:\n\n";
        $message .= "What aspect would you like to explore?\n\n";
        $message .= "• Recent transactions and portfolio activity\n";
        $message .= "• Hiring process and culture\n";
        $message .= "• Compensation structure and progression\n";
        $message .= "• Interview preparation and expectations\n";
        $message .= "• Team structure and key personnel\n\n";
        $message .= "Please specify your area of interest.";
        
        // Show market headlines that might include firm news
        $visual = $this->get_market_headlines_visual();
        
        return array(
            'message' => $message,
            'visual' => $visual,
            'typing_delay' => 100,
            'instant' => true,
            'follow_up' => array(
                "Recent deals by {$firm}",
                "How to get hired at {$firm}",
                "Compensation at {$firm}"
            ),
            'trigger_claude' => true
        );
    }
    
    /**
     * Handle general/unclear queries professionally
     */
    private function handle_general_query_professional($query, $data, $user_name) {
        $query_lower = strtolower($query);
        
        $message = "Based on your query about '{$query}', I can help with several areas:\n\n";
        
        // Always show something valuable
        $message .= "QUICK ACCESS:\n";
        $message .= "• Current market conditions and deal flow\n";
        $message .= "• Career opportunities and compensation data\n";
        $message .= "• Skills development and interview preparation\n";
        $message .= "• Firm-specific intelligence and insights\n\n";
        $message .= "Which area would be most helpful for you right now?";
        
        // Default to market pulse - always relevant
        $visual = $this->get_market_pulse_visual();
        
        return array(
            'message' => $message,
            'visual' => $visual,
            'typing_delay' => 100,
            'instant' => true,
            'follow_up' => array(
                "Show current market activity",
                "Career opportunities in PE",
                "Skills for breaking into finance"
            ),
            'trigger_claude' => true
        );
    }
    
    /**
     * Analyze intent
     */
    private function analyze_intent($query_lower) {
        // Same pattern matching as before
        $patterns = array(
            'roles_jobs' => '/\b(role|roles|job|jobs|position|positions|opening|openings|opportunities|opportunity)\b/i',
            'compensation' => '/\b(salary|salaries|compensation|pay|bonus|bonuses|package|comp|earn|earning)\b/i',
            'career' => '/\b(career|path|progression|move|transition|switch|break into)\b/i',
            'skills' => '/\b(learn|skill|skills|model|modeling|course|training|lbo|dcf)\b/i',
            'market' => '/\b(market|markets|deal|deals|transaction|ma|merger|acquisition|news|update|sector|sectors|analysis|analyze|analyse|industry|industries)\b/i',
            'firms' => '/\b(blackstone|kkr|apollo|carlyle|goldman|morgan|jpmorgan|bofa|citi|firm|company)\b/i',
            'interview' => '/\b(interview|interviews|prepare|preparation|questions|case|technical)\b/i'
        );
        
        foreach ($patterns as $intent => $pattern) {
            if (preg_match($pattern, $query_lower)) {
                return $intent;
            }
        }
        
        return 'general';
    }
    
    /**
     * Get contextual data
     */
    private function get_contextual_data($intent, $query) {
        // Same as before but returns structured data
        return array();
    }
    
    // Additional professional handler methods...
    private function handle_skills_query_professional($query, $data, $user_name) {
        $message = "Skills Development Roadmap:\n\n";
        $message .= "TECHNICAL SKILLS (High Priority):\n";
        $message .= "• LBO Modeling - Essential for PE roles\n";
        $message .= "• DCF Analysis - Fundamental valuation skill\n";
        $message .= "• Financial Statement Modeling - Core competency\n";
        $message .= "• Data Analysis (Python/SQL) - Increasingly important\n\n";
        $message .= "SOFT SKILLS (Career Differentiators):\n";
        $message .= "• Deal Sourcing - Network development\n";
        $message .= "• Investment Thesis Development\n";
        $message .= "• Client/LP Management\n";
        $message .= "• Team Leadership\n\n";
        $message .= "Which skill area would accelerate your career most?";
        
        return array(
            'message' => $message,
            'visual' => $this->get_skills_demand_visual(),
            'typing_delay' => 100,
            'instant' => true,
            'follow_up' => array(
                "Build an LBO model step-by-step",
                "Essential Excel shortcuts for finance",
                "Technical skills for PE interviews"
            )
        );
    }
    
    private function handle_career_query_professional($query, $data, $user_name) {
        $message = "Career Transition Pathways:\n\n";
        $message .= "COMMON SUCCESSFUL TRANSITIONS:\n\n";
        $message .= "Into Private Equity:\n";
        $message .= "• IB Analyst (2-3 yrs) → PE Associate\n";
        $message .= "• Management Consulting → PE Operations\n";
        $message .= "• Corporate Development → Growth Equity\n\n";
        $message .= "Career Progression Timeline:\n";
        $message .= "• Years 0-2: Analyst (Foundation building)\n";
        $message .= "• Years 2-5: Associate (Deal execution)\n";
        $message .= "• Years 5-8: VP/Principal (Deal sourcing)\n";
        $message .= "• Years 8+: Partner track (Portfolio management)\n\n";
        $message .= "What's your current position and target destination?";
        
        return array(
            'message' => $message,
            'visual' => $this->get_trending_roles_visual(),
            'typing_delay' => 100,
            'instant' => true,
            'follow_up' => array(
                "IB to PE transition strategy",
                "When to get an MBA for finance",
                "Lateral vs vertical career moves"
            )
        );
    }
    
    private function handle_interview_query_professional($query, $data, $user_name) {
        $message = "Interview Preparation Framework:\n\n";
        $message .= "TECHNICAL PREPARATION:\n";
        $message .= "• LBO walkthrough (10-15 minutes)\n";
        $message .= "• Paper LBO (mental math test)\n";
        $message .= "• Valuation methodologies comparison\n";
        $message .= "• Recent deal discussion\n";
        $message .= "• Market views and thesis\n\n";
        $message .= "BEHAVIORAL PREPARATION:\n";
        $message .= "• Why this firm specifically\n";
        $message .= "• Deal experience examples\n";
        $message .= "• Leadership and teamwork stories\n";
        $message .= "• Investment philosophy\n\n";
        $message .= "What type of interview are you preparing for?";
        
        return array(
            'message' => $message,
            'visual' => $this->get_skills_demand_visual(),
            'typing_delay' => 100,
            'instant' => true,
            'follow_up' => array(
                "PE associate interview guide",
                "Common technical questions",
                "Case study preparation"
            )
        );
    }
}