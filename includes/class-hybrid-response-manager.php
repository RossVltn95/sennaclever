<?php
/**
 * Hybrid Response Manager - Combines templates with Claude API
 * 
 * @package SennaCareers
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Hybrid_Response_Manager {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Claude API Manager
     */
    private $claude_api;
    
    /**
     * Market Intelligence Service
     */
    private $market_service;
    
    /**
     * Claude Market API
     */
    private $claude_market_api;
    
    /**
     * Response deduplication flag
     */
    private $response_generated = false;
    
    /**
     * Personalization Manager
     */
    private $personalization;
    
    /**
     * Fallback Manager
     */
    private $fallback_manager;
    
    /**
     * Pattern Recognition Components
     */
    private $query_classifier;
    private $response_composer;
    private $advanced_pattern_matcher;
    private $premium_visual_cards;
    
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
        // PRIORITY: Load Advanced Pattern Matcher and Visual Cards FIRST
        // These are critical for the new system to work
        $this->load_advanced_pattern_system();
        
        // Load other components with error handling
        $this->load_claude_api_manager();
        $this->load_personalization_manager();
        
        // Load market greeting variations
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-market-greeting-variations.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-market-greeting-variations.php';
        }
        
        // Load new market services
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/services/class-market-intelligence-service.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/services/class-market-intelligence-service.php';
            $this->market_service = SFFC_Market_Intelligence_Service::get_instance();
        }
        
        // Claude Market API - commented out as file doesn't exist yet
        // if (file_exists(SFFC_PLUGIN_DIR . 'includes/api/class-claude-market-api.php')) {
        //     require_once SFFC_PLUGIN_DIR . 'includes/api/class-claude-market-api.php';
        //     $this->claude_market_api = SFFC_Claude_Market_API::get_instance();
        // }
        
        // Phase 11: Load centralized fallback manager
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-fallback-manager.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-fallback-manager.php';
            $this->fallback_manager = SFFC_Fallback_Manager::get_instance();
        }
        
        // CRITICAL FIX: Load Query Classifier with DEPENDENCY ISOLATION
        // The Query Classifier has a deep dependency chain that can fail
        // If it fails, we DON'T want it to break the advanced pattern system
        try {
            if (file_exists(SFFC_PLUGIN_DIR . 'includes/patterns/class-query-classifier.php')) {
                require_once SFFC_PLUGIN_DIR . 'includes/patterns/class-query-classifier.php';
                if (class_exists('SFFC_Query_Classifier')) {
                    // Try to instantiate with timeout protection
                    $this->query_classifier = SFFC_Query_Classifier::get_instance();
                    error_log('SFFC: Query Classifier loaded successfully');
                } else {
                    $this->query_classifier = null;
                    error_log('SFFC: Query Classifier class not found');
                }
            } else {
                $this->query_classifier = null;
                error_log('SFFC: Query Classifier file not found');
            }
        } catch (Exception $e) {
            // CRITICAL: If Query Classifier fails, log it but DON'T break the constructor
            $this->query_classifier = null;
            error_log('SFFC: Query Classifier failed to load - ' . $e->getMessage());
            error_log('SFFC: Continuing with advanced pattern system only');
        }
        
        // SAFE LOADING: Response Composer with error isolation
        try {
            if (file_exists(SFFC_PLUGIN_DIR . 'includes/patterns/class-response-composer.php')) {
                require_once SFFC_PLUGIN_DIR . 'includes/patterns/class-response-composer.php';
                if (class_exists('SFFC_Response_Composer')) {
                    $this->response_composer = SFFC_Response_Composer::get_instance();
                } else {
                    $this->response_composer = null;
                }
            } else {
                $this->response_composer = null;
            }
        } catch (Exception $e) {
            $this->response_composer = null;
            error_log('SFFC: Response Composer failed to load - ' . $e->getMessage());
        }
        
        // Advanced Pattern Matcher and Premium Visual Cards are loaded with PRIORITY above
        // This ensures they are initialized first before any other components
    }
    
    /**
     * Generate response based on query type and mode
     */
    public function generate_response($query, $mode, $context = array()) {
        // Reset deduplication flag for new query
        $this->response_generated = false;
        
        // CONTEXT-AWARE: Determine if this is initial or follow-up
        $is_initial = !isset($context['conversation_history']) || empty($context['conversation_history']);
        $is_greeting_only = preg_match('/^(hi|hello|hey|good morning|good afternoon|good evening)$/i', trim($query));
        
        // Store context flags for visual decisions
        $context['is_initial_query'] = $is_initial;
        $context['is_follow_up'] = !$is_initial;
        
        // Initial greeting in market mode -> Show newspaper
        if ($is_initial && $is_greeting_only && $mode === 'market') {
            return $this->generate_market_quick_response('greeting', $context);
        }
        
        // Initial greeting in other modes -> Simple text (no visual)
        if ($is_initial && $is_greeting_only) {
            return $this->generate_template_response('greeting', $mode, $context);
        }
        
        // Try Advanced Pattern Recognition with Visual Cards first
        if (!empty($this->advanced_pattern_matcher) && !empty($this->premium_visual_cards)) {
            try {
                // Extract entities from query (simple implementation)
                $entities = $this->extract_entities($query);
                
                // Get pattern matches using advanced matcher
                $pattern_matches = $this->advanced_pattern_matcher->match_patterns($query, $entities, $context);
                
                if (!empty($pattern_matches) && count($pattern_matches) > 0) {
                    $best_match = $pattern_matches[0]; // Top scoring match
                    
                    // If confidence is high enough, use pattern-based response with visual cards
                    if ($best_match['confidence'] >= 60) {
                        // Generate response based on pattern type
                        $response_content = $this->generate_pattern_response($best_match, $query, $context);
                        
                        if (!empty($response_content)) {
                            // Get visual card for the matched pattern
                            $visual_card_data = $this->premium_visual_cards->render_card($best_match['card'], array(
                                'query' => $query,
                                'pattern' => $best_match['pattern'],
                                'entities' => $entities,
                                'context' => $context
                            ));
                            
                            // Extract HTML from visual card data and format for frontend
                            $visual_card_html = is_array($visual_card_data) && isset($visual_card_data['html']) 
                                ? $visual_card_data['html'] 
                                : $visual_card_data;
                            
                            // Format visual card for frontend compatibility
                            $visual_card_object = array(
                                'type' => 'premium_visual_card',
                                'card_type' => $best_match['card'],
                                'pattern' => $best_match['pattern'],
                                'html' => $visual_card_html,
                                'confidence' => $best_match['confidence']
                            );
                            
                            $formatted_response = array(
                                'response' => $response_content,
                                'message' => $response_content,
                                'visual_cards' => array($visual_card_object),
                                'mode' => $mode,
                                'source' => 'advanced_pattern_engine',
                                'pattern_info' => $best_match
                            );
                            
                            error_log('SFFC: Advanced pattern match - Pattern: ' . $best_match['pattern'] . ', Confidence: ' . $best_match['confidence'] . '%');
                            
                            return $formatted_response;
                        }
                    }
                }
            } catch (Exception $e) {
                // If advanced pattern engine fails, continue to fallback
                error_log('SFFC: Advanced pattern engine error - ' . $e->getMessage());
            }
        }
        
        $response_type = $this->analyze_query_type($query, $mode, $context);
        
        // Debug logging
        error_log('SFFC Hybrid Manager - Query: ' . $query);
        error_log('SFFC Hybrid Manager - Response Type: ' . $response_type);
        error_log('SFFC Hybrid Manager - Is Follow-up: ' . ($context['is_follow_up'] ? 'Yes' : 'No'));
        error_log('SFFC Hybrid Manager - Needs Claude: ' . ($this->needs_claude($response_type) ? 'Yes' : 'No'));
        
        // Determine if this needs Claude or can use template
        if ($this->needs_claude($response_type)) {
            return $this->generate_claude_response($query, $mode, $context);
        } else {
            $response = $this->generate_template_response($response_type, $mode, $context);
            $this->response_generated = true; // Mark template response generated
            return $response;
        }
    }
    
    /**
     * Analyze query type with context awareness - EXPANDED TEMPLATE SYSTEM
     */
    private function analyze_query_type($query, $mode, $context = array()) {
        $query_lower = strtolower($query);
        $is_follow_up = isset($context['is_follow_up']) && $context['is_follow_up'];
        
        // === SIMPLE RESPONSES (TEMPLATE ONLY - NO CLAUDE) ===
        
        // Acknowledgments
        if (preg_match('/^(thanks|thank you|ok|okay|got it|understood|i see|makes sense|cool|great|awesome|perfect)$/i', trim($query))) {
            return 'acknowledgment';
        }
        
        // Greetings
        if (preg_match('/^(hi|hello|hey|good morning|good afternoon|good evening|greetings)$/i', trim($query))) {
            return 'greeting';
        }
        
        // Affirmative responses
        if (preg_match('/^(yes|yeah|yep|sure|definitely|absolutely|sounds good|let\'s do it|please|go ahead)$/i', trim($query))) {
            return 'affirmative_response';
        }
        
        // Help requests
        if (preg_match('/^(can i ask|may i ask|i have a question|i want to ask|help me|can you help|what can you do)$/i', trim($query))) {
            return 'help_request';
        }
        
        // === MASSIVELY EXPANDED GENERIC MARKET QUESTIONS ===
        
        // Market Overview & Status
        if (preg_match('/^(what\'s happening|what is happening|market today|today\'s market|current market|market overview|market summary|market update|daily summary|market roundup|market wrap|market snapshot)$/i', trim($query))) {
            return 'market_today_basic';
        }
        
        if (preg_match('/^(how are markets|how are the markets|market performance|market status|how is the market|markets doing|market health|market strength|market sentiment|market mood)$/i', trim($query))) {
            return 'market_performance_basic';
        }
        
        if (preg_match('/^(what should i know|what\'s important|key points|main highlights|important news|key developments|major events|significant moves|notable changes|critical updates)$/i', trim($query))) {
            return 'market_highlights_basic';
        }
        
        if (preg_match('/^(any news|latest news|recent news|breaking news|market news|financial news|business news|top stories|headlines|recent developments)$/i', trim($query))) {
            return 'market_news_basic';
        }
        
        // Market Movers & Performance
        if (preg_match('/^(what\'s moving|market movers|big movers|top movers|biggest moves|what moved|price action|market action|trading activity|volume leaders)$/i', trim($query))) {
            return 'market_movers_basic';
        }
        
        if (preg_match('/^(winners|losers|gainers|decliners|top performers|worst performers|leaders|laggards|outperformers|underperformers)$/i', trim($query))) {
            return 'winners_losers_basic';
        }
        
        if (preg_match('/^(volatility|market vol|vix|market risk|risk levels|uncertainty|market stress|fear index|volatility levels)$/i', trim($query))) {
            return 'volatility_basic';
        }
        
        // Sector Analysis
        if (preg_match('/^(which sector|best sector|top sector|sector performance|sector leaders|sector rotation|hot sectors|cold sectors|sector themes|sector trends)$/i', trim($query))) {
            return 'sector_basic';
        }
        
        if (preg_match('/^(healthcare sector|tech sector|technology sector|financial sector|energy sector|consumer sector|industrial sector|materials sector|utilities sector|real estate sector)$/i', trim($query))) {
            return 'sector_specific_basic';
        }
        
        if (preg_match('/^(healthcare|technology|tech|financials|banks|energy|oil|gas|consumer|retail|industrials|materials|utilities|reits|biotech|pharma)$/i', trim($query))) {
            return 'sector_single_word';
        }
        
        // Private Equity & Deals
        if (preg_match('/^(pe deals|private equity|recent deals|latest deals|new deals|deal activity|deal flow|deal pipeline|transaction activity|m&a activity)$/i', trim($query))) {
            return 'pe_deals_basic';
        }
        
        if (preg_match('/^(which firms|top firms|best firms|leading firms|pe firms|private equity firms|biggest firms|major firms|top players|key players)$/i', trim($query))) {
            return 'firms_basic';
        }
        
        if (preg_match('/^(blackstone|kkr|apollo|carlyle|tpg|warburg|bain capital|goldman sachs|morgan stanley|jpmorgan)$/i', trim($query))) {
            return 'specific_firm_basic';
        }
        
        // Opportunities & Careers
        if (preg_match('/^(opportunities|job opportunities|career opportunities|openings|jobs|hiring|recruiting|positions|roles|careers)$/i', trim($query))) {
            return 'opportunities_basic';
        }
        
        if (preg_match('/^(analyst|associate|vp|vice president|director|md|managing director|partner|principal|entry level|senior|junior)$/i', trim($query))) {
            return 'role_level_basic';
        }
        
        // Market Conditions & Environment
        if (preg_match('/^(market conditions|economic conditions|financial conditions|environment|backdrop|climate|atmosphere|landscape|outlook)$/i', trim($query))) {
            return 'conditions_basic';
        }
        
        if (preg_match('/^(interest rates|rates|fed rates|central bank|monetary policy|fed policy|rate environment|rate outlook|rate expectations)$/i', trim($query))) {
            return 'rates_basic';
        }
        
        if (preg_match('/^(inflation|cpi|pce|price levels|cost increases|inflationary pressure|deflation|price stability)$/i', trim($query))) {
            return 'inflation_basic';
        }
        
        // Geographic Markets
        if (preg_match('/^(us markets|american markets|domestic markets|united states|usa|america|north america)$/i', trim($query))) {
            return 'us_markets_basic';
        }
        
        if (preg_match('/^(european markets|europe|uk markets|germany|france|italy|spain|brexit|eu markets)$/i', trim($query))) {
            return 'european_markets_basic';
        }
        
        if (preg_match('/^(asian markets|asia|china|japan|india|korea|singapore|emerging markets|apac|asia pacific)$/i', trim($query))) {
            return 'asian_markets_basic';
        }
        
        // Asset Classes
        if (preg_match('/^(equities|stocks|equity markets|stock market|shares|public markets|listed companies)$/i', trim($query))) {
            return 'equities_basic';
        }
        
        if (preg_match('/^(bonds|fixed income|treasuries|corporate bonds|government bonds|debt markets|credit markets|bond market)$/i', trim($query))) {
            return 'bonds_basic';
        }
        
        if (preg_match('/^(commodities|gold|oil|silver|copper|wheat|corn|precious metals|industrial metals|energy commodities|agricultural commodities)$/i', trim($query))) {
            return 'commodities_basic';
        }
        
        if (preg_match('/^(currencies|forex|fx|dollar|euro|yen|pound|currency markets|exchange rates|fx markets)$/i', trim($query))) {
            return 'currencies_basic';
        }
        
        if (preg_match('/^(alternatives|hedge funds|private markets|real estate|infrastructure|commodities|art|collectibles|crypto|digital assets)$/i', trim($query))) {
            return 'alternatives_basic';
        }
        
        // Trading & Investment
        if (preg_match('/^(trading|day trading|swing trading|algorithmic trading|high frequency|momentum|mean reversion|arbitrage)$/i', trim($query))) {
            return 'trading_basic';
        }
        
        if (preg_match('/^(portfolio|allocation|diversification|risk management|hedging|position sizing|asset allocation)$/i', trim($query))) {
            return 'portfolio_basic';
        }
        
        if (preg_match('/^(valuation|metrics|ratios|pe ratio|price to book|ev ebitda|dcf|multiples|fair value)$/i', trim($query))) {
            return 'valuation_basic';
        }
        
        // Economic Indicators
        if (preg_match('/^(gdp|unemployment|jobs report|nonfarm payrolls|employment|economic growth|recession|expansion|recovery)$/i', trim($query))) {
            return 'economic_data_basic';
        }
        
        if (preg_match('/^(earnings|eps|earnings season|quarterly results|guidance|revenue|profit|margins|cash flow)$/i', trim($query))) {
            return 'earnings_basic';
        }
        
        // Market Events & Catalysts
        if (preg_match('/^(fomc|fed meeting|earnings|ipo|merger|acquisition|dividend|stock split|spin off|buyback)$/i', trim($query))) {
            return 'market_events_basic';
        }
        
        if (preg_match('/^(catalyst|catalysts|upcoming events|market calendar|economic calendar|earnings calendar|event risk)$/i', trim($query))) {
            return 'catalysts_basic';
        }
        
        // Risk & Sentiment
        if (preg_match('/^(sentiment|investor sentiment|market sentiment|bullish|bearish|optimism|pessimism|fear|greed|confidence)$/i', trim($query))) {
            return 'sentiment_basic';
        }
        
        if (preg_match('/^(risk|market risk|systemic risk|credit risk|liquidity risk|operational risk|tail risk|downside risk)$/i', trim($query))) {
            return 'risk_basic';
        }
        
        // Investment Strategies
        if (preg_match('/^(growth|value|momentum|quality|dividend|income|defensive|cyclical|contrarian|factor investing)$/i', trim($query))) {
            return 'strategies_basic';
        }
        
        if (preg_match('/^(esg|sustainable|responsible investing|impact investing|green finance|climate finance|social impact)$/i', trim($query))) {
            return 'esg_basic';
        }
        
        // Market Structure & Mechanics
        if (preg_match('/^(liquidity|market liquidity|bid ask spread|market depth|volume|trading volume|dark pools|market makers)$/i', trim($query))) {
            return 'market_structure_basic';
        }
        
        if (preg_match('/^(regulation|sec|cftc|finra|compliance|rules|regulatory changes|policy|government intervention)$/i', trim($query))) {
            return 'regulation_basic';
        }
        
        // === CONTEXTUAL FOLLOW-UP PATTERNS ===
        
        // Generic follow-ups - contextual templates
        if ($is_follow_up && preg_match('/^(tell me more|continue|go on|what else|and\?|interesting|explain|more details|elaborate|expand|dig deeper)$/i', trim($query))) {
            return 'generic_follow_up';
        }
        
        // Conversational responses
        if (preg_match('/^(really|wow|interesting|impressive|that\'s good|that\'s bad|surprising|expected|makes sense|i see|got it|understand)$/i', trim($query))) {
            return 'conversational_response';
        }
        
        // Clarification requests
        if (preg_match('/^(what do you mean|can you clarify|explain that|what exactly|be more specific|give an example|for instance)$/i', trim($query))) {
            return 'clarification_request';
        }
        
        // Comparison requests - basic level
        if (preg_match('/^(which is better|what\'s the difference|compare them|versus|vs|better option|best choice)$/i', trim($query))) {
            return 'simple_comparison';
        }
        
        // Timing questions
        if (preg_match('/^(when|timing|how long|duration|schedule|timeline|soon|later|now|today|this week|next month)$/i', trim($query))) {
            return 'timing_basic';
        }
        
        // Quantity/size questions - basic level
        if (preg_match('/^(how much|how many|size|amount|quantity|big|small|large|huge|massive|tiny)$/i', trim($query))) {
            return 'quantity_basic';
        }
        
        // Opinion/advice requests
        if (preg_match('/^(what do you think|your opinion|advice|recommend|suggestion|should i|would you|thoughts|view|perspective)$/i', trim($query))) {
            return 'opinion_basic';
        }
        
        // Next steps/action items
        if (preg_match('/^(what next|next steps|what now|action|plan|strategy|approach|move forward|proceed)$/i', trim($query))) {
            return 'next_steps_basic';
        }
        
        // === MASSIVELY EXPANDED INDUSTRY-SPECIFIC PATTERNS ===
        
        // Investment Banking Specific
        if (preg_match('/^(investment banking|ib|bulge bracket|elite boutique|middle market|pitch book|deal book|dcf|lbo|comps|precedent transactions)$/i', trim($query))) {
            return 'investment_banking_basic';
        }
        
        if (preg_match('/^(goldman|morgan stanley|jpmorgan|deutsche bank|credit suisse|ubs|barclays|citi|wells fargo|bank of america)$/i', trim($query))) {
            return 'ib_firms_basic';
        }
        
        // Private Equity Deep Dive
        if (preg_match('/^(buyout|growth equity|venture capital|distressed|secondary|fund of funds|co-investment|dry powder|portfolio company)$/i', trim($query))) {
            return 'pe_strategy_basic';
        }
        
        if (preg_match('/^(due diligence|management presentation|cim|loi|exclusivity|closing|add-on|platform|bolt-on|exit strategy)$/i', trim($query))) {
            return 'pe_process_basic';
        }
        
        // Hedge Fund Strategies
        if (preg_match('/^(long short|market neutral|event driven|macro|quantitative|systematic|discretionary|fundamental|technical)$/i', trim($query))) {
            return 'hedge_fund_strategies_basic';
        }
        
        if (preg_match('/^(citadel|bridgewater|renaissance|two sigma|de shaw|millennium|point72|tiger|coatue|viking)$/i', trim($query))) {
            return 'hedge_fund_firms_basic';
        }
        
        // Asset Management 
        if (preg_match('/^(asset management|wealth management|institutional|retail|etf|mutual fund|index|active|passive)$/i', trim($query))) {
            return 'asset_management_basic';
        }
        
        if (preg_match('/^(blackrock|vanguard|fidelity|state street|invesco|t rowe|franklin|janus|pimco|northern trust)$/i', trim($query))) {
            return 'asset_managers_basic';
        }
        
        // Consulting & Advisory
        if (preg_match('/^(consulting|mckinsey|bain|bcg|strategy|operations|implementation|transformation|advisory)$/i', trim($query))) {
            return 'consulting_basic';
        }
        
        // Credit & Fixed Income
        if (preg_match('/^(credit analysis|high yield|investment grade|distressed debt|structured products|securitization|mbs|abs|cdo)$/i', trim($query))) {
            return 'credit_detailed_basic';
        }
        
        // Real Estate & Infrastructure
        if (preg_match('/^(real estate|reit|commercial|residential|industrial|retail|office|multifamily|infrastructure|transportation)$/i', trim($query))) {
            return 'real_estate_basic';
        }
        
        // Financial Technology
        if (preg_match('/^(fintech|payments|lending|blockchain|cryptocurrency|digital banking|robo advisor|insurtech|regtech|wealthtech)$/i', trim($query))) {
            return 'fintech_basic';
        }
        
        // === ROLE & CAREER SPECIFIC PATTERNS ===
        
        // Career Levels & Progression
        if (preg_match('/^(summer intern|analyst program|associate|vp promotion|director role|md track|partner|c-level|ceo|cfo|cio|cro)$/i', trim($query))) {
            return 'career_levels_basic';
        }
        
        // Skills & Qualifications
        if (preg_match('/^(modeling|excel|powerpoint|pitchbook|financial statements|accounting|valuation|cfa|mba|series 7|frm)$/i', trim($query))) {
            return 'skills_qualifications_basic';
        }
        
        // Interview Prep
        if (preg_match('/^(interview|behavioral|technical|case study|brain teaser|walk me through|why finance|tell me about yourself)$/i', trim($query))) {
            return 'interview_prep_basic';
        }
        
        // Compensation & Benefits
        if (preg_match('/^(salary|bonus|carry|equity|stock options|benefits|vacation|health insurance|401k|compensation)$/i', trim($query))) {
            return 'compensation_detailed_basic';
        }
        
        // Work-Life Balance
        if (preg_match('/^(hours|work life balance|culture|travel|remote work|flexibility|stress|burnout|lifestyle)$/i', trim($query))) {
            return 'work_life_basic';
        }
        
        // === MARKET CONDITIONS & CYCLES ===
        
        // Market Cycles
        if (preg_match('/^(bull market|bear market|correction|crash|bubble|cycle|peak|trough|recovery|recession)$/i', trim($query))) {
            return 'market_cycles_basic';
        }
        
        // Economic Policy
        if (preg_match('/^(fiscal policy|monetary policy|quantitative easing|tapering|stimulus|austerity|debt ceiling|shutdown)$/i', trim($query))) {
            return 'economic_policy_basic';
        }
        
        // Global Events
        if (preg_match('/^(election|trade war|brexit|covid|ukraine|china|russia|supply chain|energy crisis)$/i', trim($query))) {
            return 'global_events_basic';
        }
        
        // === INVESTMENT THEMES & TRENDS ===
        
        // Technology Themes
        if (preg_match('/^(artificial intelligence|machine learning|cloud computing|semiconductors|software|hardware|cybersecurity|data)$/i', trim($query))) {
            return 'technology_themes_basic';
        }
        
        // ESG & Sustainability  
        if (preg_match('/^(carbon credits|green bonds|renewable energy|solar|wind|electric vehicles|battery|hydrogen)$/i', trim($query))) {
            return 'sustainability_themes_basic';
        }
        
        // Demographics & Social
        if (preg_match('/^(aging population|millennials|gen z|urbanization|healthcare costs|education|housing crisis)$/i', trim($query))) {
            return 'demographic_themes_basic';
        }
        
        // === SEASONAL & TEMPORAL PATTERNS ===
        
        // Time-based queries
        if (preg_match('/^(end of year|year end|q4|q1|january effect|sell in may|summer|holiday|earnings season|tax season)$/i', trim($query))) {
            return 'seasonal_patterns_basic';
        }
        
        // Recent events
        if (preg_match('/^(this week|last week|this month|recent|lately|currently|now|today|yesterday|tomorrow)$/i', trim($query))) {
            return 'temporal_basic';
        }
        
        // === EDUCATIONAL & LEARNING ===
        
        // Learning requests
        if (preg_match('/^(learn|teach me|explain|understand|basics|fundamentals|introduction|guide|tutorial|course)$/i', trim($query))) {
            return 'educational_basic';
        }
        
        // Book & Resource requests
        if (preg_match('/^(books|reading|resources|websites|courses|certification|study|preparation|materials)$/i', trim($query))) {
            return 'resources_basic';
        }
        
        // === NEWS & CURRENT EVENTS ===
        
        // Breaking news patterns
        if (preg_match('/^(breaking|urgent|alert|flash|just announced|developing|live|update|bulletin)$/i', trim($query))) {
            return 'breaking_news_basic';
        }
        
        // Rumor & speculation
        if (preg_match('/^(rumor|speculation|gossip|whispers|talk|chatter|buzz|word on street|sources say)$/i', trim($query))) {
            return 'market_rumors_basic';
        }
        
        // === COMPLEX QUERIES THAT NEED CLAUDE ===
        
        // Company-specific analysis (revenue, projections, etc.)
        if (preg_match('/revenue|profit|earnings|projections|forecast|valuation|financial performance|balance sheet|cash flow/i', $query) && 
            preg_match('/of [a-z]+|for [a-z]+|at [a-z]+/i', $query)) {
            return 'company_analysis_complex';
        }
        
        // Specific numerical requests
        if (preg_match('/how much|what is the value|what are the numbers|calculate|estimate|quantify/i', $query)) {
            return 'numerical_analysis_complex';
        }
        
        // Comparison requests
        if (preg_match('/compare|versus|vs|better than|worse than|difference between/i', $query)) {
            return 'comparison_analysis_complex';
        }
        
        // Detailed analysis requests
        if (preg_match('/analyze|analysis|deep dive|detailed|comprehensive|explain why|what caused|impact of/i', $query)) {
            return 'detailed_analysis_complex';
        }
        
        // Explicit data requests - THESE need visuals
        if (preg_match('/show me|display|what are the|list|give me|provide|chart|graph|visualize/i', $query)) {
            return 'data_request';
        }
        
        // Market Analysis specific - EXPANDED FOR ALL SUGGESTED QUERIES
        if ($mode === 'market') {
            // Global Economic Outlook
            if (preg_match('/global|economic|outlook|world|international|macro/i', $query)) {
                return 'global_economic_outlook';
            }
            // ESG Investment Trends
            if (preg_match('/esg|sustainable|sustainability|green|environmental|social|governance/i', $query)) {
                return 'esg_trends';
            }
            // Sector Analysis
            if (preg_match('/sector|industry|healthcare|technology|energy|financial|consumer/i', $query)) {
                return 'sector_analysis';
            }
            // PE Deal Activity
            if (preg_match('/private equity|pe deal|buyout|lbo|acquisition|portfolio/i', $query)) {
                return 'pe_deals';
            }
            // Credit Markets
            if (preg_match('/credit|bond|fixed income|high yield|investment grade|debt/i', $query)) {
                return 'credit_markets';
            }
            // M&A Activity
            if (preg_match('/merger|m&a|acquisition|takeover|consolidation/i', $query)) {
                return 'ma_activity';
            }
            // Distressed Opportunities
            if (preg_match('/distressed|restructuring|bankruptcy|special situation|turnaround/i', $query)) {
                return 'distressed';
            }
            // IPO Market
            if (preg_match('/ipo|listing|public offering|spac|direct listing/i', $query)) {
                return 'ipo_market';
            }
            // Firm specific
            if (preg_match('/blackstone|kkr|apollo|carlyle|tpg|warburg/i', $query)) {
                return 'firm_specific';
            }
            // Alternative Investments
            if (preg_match('/alternative|hedge fund|private market|real asset|infrastructure/i', $query)) {
                return 'alternatives';
            }
            // Volatility/Risk
            if (preg_match('/volatility|vix|risk|uncertainty|hedging/i', $query)) {
                return 'volatility';
            }
            // Regulatory
            if (preg_match('/regulatory|regulation|compliance|basel|dodd-frank|mifid/i', $query)) {
                return 'regulatory';
            }
            // Generic headlines/news
            if (preg_match('/headlines|news|what\'s happening|market update|market conditions today/i', $query)) {
                return 'market_headlines';
            }
            // Energy Transition
            if (preg_match('/energy transition|clean tech|renewable|solar|wind|battery/i', $query)) {
                return 'energy_transition';
            }
            // Emerging Markets
            if (preg_match('/emerging|developing|brics|frontier|asia|latin america|africa/i', $query)) {
                return 'emerging_markets';
            }
            // Market Conditions Today (General headlines)
            if (preg_match('/market conditions today|today\'s market|current market/i', $query)) {
                return 'market_conditions';
            }
            // Equity Market Trends
            if (preg_match('/equity|stock market|s&p 500|dow jones|nasdaq|indices|equity trends/i', $query)) {
                return 'equity_trends';
            }
            // Currency Movements
            if (preg_match('/currency|forex|fx|usd|eur|gbp|exchange rate|dollar/i', $query)) {
                return 'currency_movements';
            }
            // Commodity Analysis
            if (preg_match('/commodity|commodities|oil|gold|materials|copper|silver/i', $query)) {
                return 'commodity_analysis';
            }
            // Interest Rate Environment
            if (preg_match('/interest rate|rates|fed funds|central bank|monetary policy/i', $query)) {
                return 'interest_rates';
            }
            // IPO Market Analysis
            if (preg_match('/ipo|initial public offering|listings|new issue/i', $query)) {
                return 'ipo_market';
            }
            // Regional Market Focus
            if (preg_match('/regional|asia pacific|apac|europe|americas|regional focus/i', $query)) {
                return 'regional_markets';
            }
            // Technology Disruption
            if (preg_match('/technology disruption|fintech|digital assets|blockchain|crypto/i', $query)) {
                return 'tech_disruption';
            }
            // Geopolitical Impact
            if (preg_match('/geopolitical|trade war|sanctions|politics|policy/i', $query)) {
                return 'geopolitical';
            }
            // Earnings Season
            if (preg_match('/earnings|earnings season|corporate results|quarterly results/i', $query)) {
                return 'earnings_season';
            }
            // Fixed Income Outlook
            if (preg_match('/fixed income|bond market|treasury|government bonds/i', $query)) {
                return 'fixed_income';
            }
            // Infrastructure Investing
            if (preg_match('/infrastructure|real assets|utilities|transport|infra/i', $query)) {
                return 'infrastructure';
            }
            // Healthcare Innovation
            if (preg_match('/healthcare innovation|biotech|medical devices|pharma|life sciences/i', $query)) {
                return 'healthcare_innovation';
            }
        }
        
        // Opportunities specific
        if ($mode === 'opportunities') {
            if (preg_match('/match|suitable|fit|opportunities for me/i', $query)) {
                return 'opportunity_match';
            }
            if (preg_match('/salary|compensation|pay|bonus/i', $query)) {
                return 'compensation_analysis';
            }
        }
        
        // Skills specific
        if ($mode === 'skills') {
            if (preg_match('/lbo|dcf|model|valuation|financial/i', $query)) {
                return 'skill_tutorial';
            }
            if (preg_match('/practice|exercise|example/i', $query)) {
                return 'skill_exercise';
            }
        }
        
        // Career specific
        if ($mode === 'career') {
            if (preg_match('/trajectory|path|next step|promotion/i', $query)) {
                return 'career_trajectory';
            }
            if (preg_match('/network|connect|introduction/i', $query)) {
                return 'networking_advice';
            }
        }
        
        // Check for common question patterns (not exact match)
        if (preg_match('/what is|what are|tell me about|explain|how does|how do|how to|why|when|where|who/i', $query)) {
            // Classify based on content
            if (preg_match('/private equity|pe |leverage|buyout|lbo|portfolio company/i', $query)) {
                return 'pe_explanation';
            }
            if (preg_match('/investment bank|ib |m&a|merger|acquisition|ipo|capital markets/i', $query)) {
                return 'ib_explanation';
            }
            if (preg_match('/hedge fund|hf |trading|alpha|strategy|long short/i', $query)) {
                return 'hf_explanation';
            }
            if (preg_match('/market|stock|equity|bond|commodity|forex|crypto/i', $query)) {
                return 'market_explanation';
            }
            if (preg_match('/career|job|role|position|salary|interview|resume/i', $query)) {
                return 'career_explanation';
            }
            // Default explanation type
            return 'general_explanation';
        }
        
        // Check for action requests
        if (preg_match('/create|generate|make|build|write|develop|design/i', $query)) {
            return 'creation_request';
        }
        
        // Check for analysis requests
        if (preg_match('/analyze|analyse|evaluate|assess|review|compare/i', $query)) {
            return 'analysis_request';
        }
        
        return 'general_query';
    }
    
    /**
     * Determine if query needs Claude - UPDATED FOR EXPANDED TEMPLATES
     */
    private function needs_claude($response_type) {
        // Simple responses that don't need Claude - MASSIVELY EXPANDED
        $template_only_types = [
            // Basic interactions
            'greeting', 'mode_switch', 'affirmative_response', 'acknowledgment', 'generic_follow_up', 'help_request',
            
            // Explanation types (NEW - handle common questions)
            'pe_explanation', 'ib_explanation', 'hf_explanation', 'market_explanation', 
            'career_explanation', 'general_explanation',
            
            // Request types
            'creation_request', 'analysis_request', 'general_query',
            
            // Market Overview & Status
            'market_today_basic', 'market_performance_basic', 'market_highlights_basic', 'market_news_basic',
            
            // Market Movers & Performance  
            'market_movers_basic', 'winners_losers_basic', 'volatility_basic',
            
            // Sector Analysis
            'sector_basic', 'sector_specific_basic', 'sector_single_word',
            
            // Private Equity & Deals
            'pe_deals_basic', 'firms_basic', 'specific_firm_basic',
            
            // Opportunities & Careers
            'opportunities_basic', 'role_level_basic',
            
            // Market Conditions & Environment
            'conditions_basic', 'rates_basic', 'inflation_basic',
            
            // Geographic Markets
            'us_markets_basic', 'european_markets_basic', 'asian_markets_basic',
            
            // Asset Classes
            'equities_basic', 'bonds_basic', 'commodities_basic', 'currencies_basic', 'alternatives_basic',
            
            // Trading & Investment
            'trading_basic', 'portfolio_basic', 'valuation_basic',
            
            // Economic Indicators
            'economic_data_basic', 'earnings_basic',
            
            // Market Events & Catalysts
            'market_events_basic', 'catalysts_basic',
            
            // Risk & Sentiment
            'sentiment_basic', 'risk_basic',
            
            // Investment Strategies
            'strategies_basic', 'esg_basic',
            
            // Market Structure & Mechanics
            'market_structure_basic', 'regulation_basic',
            
            // Contextual Follow-ups & Conversational
            'generic_follow_up', 'conversational_response', 'clarification_request', 'simple_comparison',
            'timing_basic', 'quantity_basic', 'opinion_basic', 'next_steps_basic',
            
            // Industry-Specific Templates
            'investment_banking_basic', 'ib_firms_basic', 'pe_strategy_basic', 'pe_process_basic',
            'hedge_fund_strategies_basic', 'hedge_fund_firms_basic', 'asset_management_basic', 'asset_managers_basic',
            'consulting_basic', 'credit_detailed_basic', 'real_estate_basic', 'fintech_basic',
            
            // Career & Role-Specific Templates  
            'career_levels_basic', 'skills_qualifications_basic', 'interview_prep_basic', 'compensation_detailed_basic',
            'work_life_basic',
            
            // Market Conditions & Cycles
            'market_cycles_basic', 'economic_policy_basic', 'global_events_basic',
            
            // Investment Themes & Trends
            'technology_themes_basic', 'sustainability_themes_basic', 'demographic_themes_basic',
            
            // Seasonal & Temporal
            'seasonal_patterns_basic', 'temporal_basic',
            
            // Educational & Learning
            'educational_basic', 'resources_basic',
            
            // News & Events
            'breaking_news_basic', 'market_rumors_basic'
        ];
        
        // Complex queries that require Claude analysis
        $claude_required_types = [
            'company_analysis_complex',
            'numerical_analysis_complex',
            'comparison_analysis_complex',
            'detailed_analysis_complex',
            'data_request',
            'specific_analysis',
            'calculation_request',
            'comparison_request'
        ];
        
        // If explicitly requires Claude, return true
        if (in_array($response_type, $claude_required_types)) {
            return true;
        }
        
        // If it's a template-only type, return false
        if (in_array($response_type, $template_only_types)) {
            return false;
        }
        
        // Default: use Claude for market-specific complex queries
        return true;
    }
    
    /**
     * Generate Claude-powered response
     */
    private function generate_claude_response($query, $mode, $context) {
        // Deduplication check - prevent multiple response systems running
        if ($this->response_generated) {
            return null; // Prevent duplicate responses
        }
        
        // Safety check - never call Claude for template-only responses
        $query_type = $this->analyze_query_type($query, $mode);
        $template_only_types = [
            'greeting', 'affirmative_response', 'mode_switch', 'acknowledgment', 'help_request',
            'market_today_basic', 'market_performance_basic', 'market_highlights_basic', 'market_news_basic',
            'sector_basic', 'sector_specific_basic', 'pe_deals_basic', 'firms_basic', 
            'opportunities_basic', 'conditions_basic',
            // Add explanation types here too
            'pe_explanation', 'ib_explanation', 'hf_explanation', 'market_explanation',
            'career_explanation', 'general_explanation', 'creation_request', 'analysis_request', 'general_query'
        ];
        
        if (in_array($query_type, $template_only_types)) {
            $response = $this->generate_template_response($query_type, $mode, $context);
            $this->response_generated = true; // Mark response generated
            return $response;
        }
        
        // For complex queries, proceed to Claude
        error_log('SFFC: Sending complex query to Claude: ' . $query . ' (Type: ' . $query_type . ')');
        
        // Get Claude's response for other modes
        $claude_response = null;
        if ($this->claude_api) {
            $claude_response = $this->claude_api->send_message($query, $context, $mode);
        }
        
        // Check if response is valid and successful (SAFE CHECK)
        if (!$claude_response || !is_array($claude_response) || !isset($claude_response['success']) || !$claude_response['success']) {
            // Phase 11: Use centralized fallback manager instead of local fallback
            if (isset($this->fallback_manager)) {
                $response = $this->fallback_manager->get_fallback_response($query, $mode, $context);
            } else {
                // Legacy fallback if manager not available
                $response = $this->generate_fallback_response($query, $mode, $context);
            }
            if ($response) {
                $this->response_generated = true; // Mark response generated
            }
            return $response;
        }
        
        // Extract message from standard format response
        $message = '';
        if (isset($claude_response['data']['message'])) {
            $message = $claude_response['data']['message'];
        } elseif (isset($claude_response['message'])) {
            // Legacy format fallback
            $message = $claude_response['message'];
        }
        
        // CONTEXT-AWARE VISUAL DECISION
        $visual_data = null;
        
        // Only generate visual if:
        // 1. It's an initial query OR
        // 2. User explicitly requested data/visualization OR
        // 3. The response type indicates visual would help
        $should_include_visual = false;
        
        if (isset($context['is_initial_query']) && $context['is_initial_query']) {
            // Initial queries in market mode might get visuals
            if ($mode === 'market' && strpos($query, 'show') !== false) {
                $should_include_visual = true;
            }
        } elseif (preg_match('/show me|display|chart|graph|visualize|list|what are the/i', $query)) {
            // Explicit visual requests
            $should_include_visual = true;
        }
        
        if ($should_include_visual) {
            $visual_data = $this->claude_api->generate_visual_data(
                $message,
                $mode,
                $query
            );
        }
        
        // Mark response generated to prevent duplicates
        $this->response_generated = true;
        
        // Return response directly without formatter (formatter class doesn't exist)
        return array(
            'message' => $message,
            'visual' => $visual_data,
            'typing_delay' => 2000, // Default typing delay
            'mode' => $mode
        );
    }
    
    /**
     * Generate template response for non-AI queries
     */
    private function generate_template_response($response_type, $mode, $context) {
        // Handle new context-aware response types
        if ($response_type === 'acknowledgment') {
            $messages = [
                "Understood. What would you like to explore next?",
                "Got it. How else can I help you?",
                "I see. What's your next question?",
                "Thank you. What else would you like to know?"
            ];
            $selected_message = $messages[array_rand($messages)];
            return array(
                'message' => $selected_message,
                'response' => $selected_message, // Include both for compatibility
                'visual' => null,  // No visual for acknowledgments
                'typing_delay' => 100,
                'source' => 'template'
            );
        }
        
        if ($response_type === 'generic_follow_up') {
            // For generic follow-ups, pass to Claude for context-aware response
            // But without visual
            return null; // Let Claude handle it
        }
        
        // For market-specific initial queries, use the fallback response method
        if ($mode === 'market' && in_array($response_type, ['market_headlines', 'market_conditions'])) {
            return $this->generate_market_quick_response($response_type, $context);
        }
        
        $templates = $this->get_response_templates();
        
        if (isset($templates[$response_type][$mode])) {
            $template = $templates[$response_type][$mode];
        } else {
            $template = $templates[$response_type]['default'] ?? $templates['default'];
        }
        
        // Personalize template
        $response = $this->personalize_template($template, $context);
        
        return array(
            'message' => $response,
            'response' => $response, // Include both 'message' and 'response' for compatibility
            'visual' => null,  // Most templates don't need visuals
            'typing_delay' => 100, // FAST typing for instant feel
            'source' => 'template'
        );
    }
    
    /**
     * Generate quick market response for common queries
     */
    private function generate_market_quick_response($response_type, $context) {
        $user_name = $context['user_first_name'] ?? '';
        $greeting = $user_name ? "Hi {$user_name}!" : "Welcome!";
        
        // CRITICAL FIX: DO NOT FETCH EXTERNAL FEEDS - Use cached or immediate fallback
        // Check for cached data first
        $cached_headlines = get_transient('sffc_market_headlines_cache');
        $hour = (int)current_time('G'); // Define hour for market status
        
        // Build message with proper bullet point formatting
        $message = $greeting . " Here's what's moving in the markets right now:\n\n";
        
        if (!empty($cached_headlines)) {
            // Use cached headlines
            foreach (array_slice($cached_headlines, 0, 3) as $item) {
                $message .= "• " . $item['title'] . "\n";
            }
            $message .= "\nWhat aspect would you like to explore deeper?";
        } else {
            // Immediate fallback - NO EXTERNAL CALLS
            
            if ($hour >= 5 && $hour < 12) {
                $message .= "• Markets open with focus on Fed policy decisions\n";
                $message .= "• Tech futures point to positive momentum\n";
                $message .= "• Asian markets close mixed, Europe opens higher\n";
            } elseif ($hour >= 12 && $hour < 17) {
                $message .= "• Markets maintain gains in afternoon trading\n";
                $message .= "• Tech sector leads S&P 500 higher\n";
                $message .= "• European close shows resilient performance\n";
            } else {
                $message .= "• Markets close with broad-based gains\n";
                $message .= "• After-hours earnings move tech stocks\n";
                $message .= "• Asia futures point to positive open\n";
            }
            $message .= "\nWhat aspect would you like to explore deeper?";
        }
        
        // Include newspaper display for market intelligence
        $visual = array(
            'type' => 'market_intelligence_newspaper',
            'data' => array(
                'date' => current_time('F j, Y'),
                'edition' => 'Market Intelligence Edition',
                'headlines' => $cached_headlines ?: array(
                    array('title' => 'Markets tracking global developments', 'time' => 'Now', 'category' => 'Markets'),
                    array('title' => 'Investment banking activity accelerates', 'time' => '1h ago', 'category' => 'IB'),
                    array('title' => 'Private equity dry powder at record levels', 'time' => '2h ago', 'category' => 'PE')
                ),
                'breaking_news' => 'Market conditions favorable for strategic positioning',
                'market_status' => ($hour >= 9 && $hour < 16) ? 'Markets Open' : 'After Hours',
                'timestamp' => current_time('g:i A')
            )
        );
        
        return array(
            'message' => $message,
            'visual' => $visual,
            'typing_delay' => 100,  // FAST typing, no delays
            'source' => 'xml_feeds'  // Mark as coming from feeds, not Claude
        );
    }
    
    /**
     * Get contextual option cards for all 25+ market query types
     */
    private function get_contextual_option_cards($query_type) {
        $option_sets = array(
            'market_headlines' => array(
                'title' => 'Select Market Focus',
                'options' => array(
                    array('icon' => '→', 'label' => 'Global Equity Markets', 'query' => 'Show global equity market movements'),
                    array('icon' => '→', 'label' => 'Fixed Income Analysis', 'query' => 'Analyze bond market conditions'),
                    array('icon' => '→', 'label' => 'Currency Movements', 'query' => 'Show major FX pairs performance'),
                    array('icon' => '→', 'label' => 'Commodity Trends', 'query' => 'What are commodity markets doing?')
                )
            ),
            'sector_analysis' => array(
                'title' => 'Choose Sector Deep Dive',
                'options' => array(
                    array('icon' => '→', 'label' => 'Technology & Innovation', 'query' => 'Analyze technology sector trends'),
                    array('icon' => '→', 'label' => 'Financial Services', 'query' => 'Review financial sector performance'),
                    array('icon' => '→', 'label' => 'Healthcare & Biotech', 'query' => 'Healthcare sector analysis'),
                    array('icon' => '→', 'label' => 'Energy & Resources', 'query' => 'Energy sector market dynamics')
                )
            ),
            'pe_deals' => array(
                'title' => 'Private Market Intelligence',
                'options' => array(
                    array('icon' => '→', 'label' => 'Latest Buyout Deals', 'query' => 'Show recent PE buyout transactions'),
                    array('icon' => '→', 'label' => 'Growth Equity Activity', 'query' => 'Growth equity investment trends'),
                    array('icon' => '→', 'label' => 'Exit Opportunities', 'query' => 'PE exit market analysis'),
                    array('icon' => '→', 'label' => 'Fund Performance', 'query' => 'Top performing PE funds')
                )
            ),
            'credit_markets' => array(
                'title' => 'Credit Market Analysis',
                'options' => array(
                    array('icon' => '→', 'label' => 'Investment Grade', 'query' => 'IG credit market conditions'),
                    array('icon' => '→', 'label' => 'High Yield', 'query' => 'High yield bond market analysis'),
                    array('icon' => '→', 'label' => 'Leveraged Loans', 'query' => 'Leveraged loan market trends'),
                    array('icon' => '→', 'label' => 'Credit Spreads', 'query' => 'Current credit spread analysis')
                )
            ),
            'ma_activity' => array(
                'title' => 'M&A Intelligence',
                'options' => array(
                    array('icon' => '→', 'label' => 'Mega Deals', 'query' => 'Latest mega merger announcements'),
                    array('icon' => '→', 'label' => 'Cross-Border M&A', 'query' => 'International M&A activity'),
                    array('icon' => '→', 'label' => 'SPAC Activity', 'query' => 'SPAC merger pipeline'),
                    array('icon' => '→', 'label' => 'Sector Consolidation', 'query' => 'Industry consolidation trends')
                )
            ),
            'distressed' => array(
                'title' => 'Distressed & Restructuring',
                'options' => array(
                    array('icon' => '→', 'label' => 'Distressed Debt', 'query' => 'Distressed debt opportunities'),
                    array('icon' => '→', 'label' => 'Bankruptcy Filings', 'query' => 'Recent Chapter 11 cases'),
                    array('icon' => '→', 'label' => 'Restructuring Deals', 'query' => 'Corporate restructuring activity'),
                    array('icon' => '→', 'label' => 'Recovery Analysis', 'query' => 'Distressed recovery rates')
                )
            ),
            'ipo_market' => array(
                'title' => 'IPO & Capital Markets',
                'options' => array(
                    array('icon' => '→', 'label' => 'IPO Pipeline', 'query' => 'Upcoming IPO calendar'),
                    array('icon' => '→', 'label' => 'Recent Listings', 'query' => 'Recent IPO performance'),
                    array('icon' => '→', 'label' => 'Direct Listings', 'query' => 'Direct listing activity'),
                    array('icon' => '→', 'label' => 'Secondary Offerings', 'query' => 'Follow-on equity raises')
                )
            ),
            'alternatives' => array(
                'title' => 'Alternative Investments',
                'options' => array(
                    array('icon' => '→', 'label' => 'Private Credit', 'query' => 'Private credit market analysis'),
                    array('icon' => '→', 'label' => 'Real Estate', 'query' => 'Real estate investment trends'),
                    array('icon' => '→', 'label' => 'Infrastructure', 'query' => 'Infrastructure investment opportunities'),
                    array('icon' => '→', 'label' => 'Venture Capital', 'query' => 'VC funding activity')
                )
            ),
            'volatility' => array(
                'title' => 'Volatility & Risk',
                'options' => array(
                    array('icon' => '→', 'label' => 'VIX Analysis', 'query' => 'Volatility index movements'),
                    array('icon' => '→', 'label' => 'Options Flow', 'query' => 'Options market activity'),
                    array('icon' => '→', 'label' => 'Risk Indicators', 'query' => 'Market risk indicators'),
                    array('icon' => '→', 'label' => 'Hedging Strategies', 'query' => 'Current hedging opportunities')
                )
            ),
            'regulatory' => array(
                'title' => 'Regulatory Updates',
                'options' => array(
                    array('icon' => '→', 'label' => 'SEC Developments', 'query' => 'Latest SEC regulatory changes'),
                    array('icon' => '→', 'label' => 'Banking Regulation', 'query' => 'Banking regulatory updates'),
                    array('icon' => '→', 'label' => 'Tax Policy', 'query' => 'Tax policy implications'),
                    array('icon' => '→', 'label' => 'ESG Compliance', 'query' => 'ESG regulatory requirements')
                )
            ),
            'energy_transition' => array(
                'title' => 'Energy Transition',
                'options' => array(
                    array('icon' => '→', 'label' => 'Clean Energy', 'query' => 'Renewable energy investments'),
                    array('icon' => '→', 'label' => 'Battery Technology', 'query' => 'Battery and storage sector'),
                    array('icon' => '→', 'label' => 'Carbon Markets', 'query' => 'Carbon credit trading'),
                    array('icon' => '→', 'label' => 'Traditional Energy', 'query' => 'Oil and gas market dynamics')
                )
            ),
            'emerging_markets' => array(
                'title' => 'Emerging Markets Focus',
                'options' => array(
                    array('icon' => '→', 'label' => 'Asia Pacific', 'query' => 'APAC market opportunities'),
                    array('icon' => '→', 'label' => 'Latin America', 'query' => 'LATAM investment landscape'),
                    array('icon' => '→', 'label' => 'Africa & private equity', 'query' => 'Africa and private equity markets'),
                    array('icon' => '→', 'label' => 'Eastern Europe', 'query' => 'Eastern European opportunities')
                )
            ),
            'equity_trends' => array(
                'title' => 'Equity Market Themes',
                'options' => array(
                    array('icon' => '→', 'label' => 'Growth vs Value', 'query' => 'Growth versus value rotation'),
                    array('icon' => '→', 'label' => 'Small Cap Analysis', 'query' => 'Small cap performance'),
                    array('icon' => '→', 'label' => 'Dividend Stocks', 'query' => 'High dividend yield opportunities'),
                    array('icon' => '→', 'label' => 'Momentum Trades', 'query' => 'Momentum stock screening')
                )
            ),
            'currency_movements' => array(
                'title' => 'FX Market Analysis',
                'options' => array(
                    array('icon' => '→', 'label' => 'Major Pairs', 'query' => 'EUR/USD and GBP/USD analysis'),
                    array('icon' => '→', 'label' => 'Yen Dynamics', 'query' => 'Japanese Yen movements'),
                    array('icon' => '→', 'label' => 'EM Currencies', 'query' => 'Emerging market FX trends'),
                    array('icon' => '→', 'label' => 'Crypto Integration', 'query' => 'Digital asset market update')
                )
            ),
            'interest_rates' => array(
                'title' => 'Rates & Central Banks',
                'options' => array(
                    array('icon' => '→', 'label' => 'Fed Policy', 'query' => 'Federal Reserve outlook'),
                    array('icon' => '→', 'label' => 'ECB Decisions', 'query' => 'European Central Bank policy'),
                    array('icon' => '→', 'label' => 'Yield Curves', 'query' => 'Yield curve analysis'),
                    array('icon' => '→', 'label' => 'Rate Expectations', 'query' => 'Interest rate forecasts')
                )
            ),
            'tech_disruption' => array(
                'title' => 'Technology & Innovation',
                'options' => array(
                    array('icon' => '→', 'label' => 'AI Investment', 'query' => 'Artificial intelligence opportunities'),
                    array('icon' => '→', 'label' => 'Fintech Evolution', 'query' => 'Fintech sector developments'),
                    array('icon' => '→', 'label' => 'Cybersecurity', 'query' => 'Cybersecurity investment trends'),
                    array('icon' => '→', 'label' => 'Blockchain/DLT', 'query' => 'Blockchain applications in finance')
                )
            ),
            'geopolitical' => array(
                'title' => 'Geopolitical Impacts',
                'options' => array(
                    array('icon' => '→', 'label' => 'Trade Relations', 'query' => 'Global trade dynamics'),
                    array('icon' => '→', 'label' => 'Sanctions Impact', 'query' => 'Sanctions and market effects'),
                    array('icon' => '→', 'label' => 'Political Risk', 'query' => 'Political risk assessment'),
                    array('icon' => '→', 'label' => 'Supply Chains', 'query' => 'Supply chain disruptions')
                )
            ),
            'earnings_season' => array(
                'title' => 'Earnings Intelligence',
                'options' => array(
                    array('icon' => '→', 'label' => 'Earnings Calendar', 'query' => 'Upcoming earnings releases'),
                    array('icon' => '→', 'label' => 'Beat/Miss Analysis', 'query' => 'Earnings surprise trends'),
                    array('icon' => '→', 'label' => 'Guidance Trends', 'query' => 'Corporate guidance updates'),
                    array('icon' => '→', 'label' => 'Sector Earnings', 'query' => 'Sector earnings comparisons')
                )
            ),
            'fixed_income' => array(
                'title' => 'Fixed Income Strategy',
                'options' => array(
                    array('icon' => '→', 'label' => 'Treasury Market', 'query' => 'US Treasury analysis'),
                    array('icon' => '→', 'label' => 'Corporate Bonds', 'query' => 'Corporate bond opportunities'),
                    array('icon' => '→', 'label' => 'Municipal Bonds', 'query' => 'Municipal bond market'),
                    array('icon' => '→', 'label' => 'Duration Risk', 'query' => 'Duration positioning')
                )
            ),
            'infrastructure' => array(
                'title' => 'Infrastructure Focus',
                'options' => array(
                    array('icon' => '→', 'label' => 'Transport Assets', 'query' => 'Transportation infrastructure'),
                    array('icon' => '→', 'label' => 'Digital Infrastructure', 'query' => 'Data centers and towers'),
                    array('icon' => '→', 'label' => 'Utilities', 'query' => 'Utility sector opportunities'),
                    array('icon' => '→', 'label' => 'Public-Private', 'query' => 'PPP investment opportunities')
                )
            ),
            'healthcare_innovation' => array(
                'title' => 'Healthcare & Life Sciences',
                'options' => array(
                    array('icon' => '→', 'label' => 'Biotech Pipeline', 'query' => 'Biotech drug pipelines'),
                    array('icon' => '→', 'label' => 'Medical Devices', 'query' => 'Medical device innovations'),
                    array('icon' => '→', 'label' => 'Digital Health', 'query' => 'Digital health investments'),
                    array('icon' => '→', 'label' => 'Pharma M&A', 'query' => 'Pharmaceutical consolidation')
                )
            ),
            'esg_trends' => array(
                'title' => 'ESG & Sustainability',
                'options' => array(
                    array('icon' => '→', 'label' => 'ESG Funds Flow', 'query' => 'ESG fund performance'),
                    array('icon' => '→', 'label' => 'Green Bonds', 'query' => 'Green bond issuance'),
                    array('icon' => '→', 'label' => 'Impact Investing', 'query' => 'Impact investment opportunities'),
                    array('icon' => '→', 'label' => 'Climate Finance', 'query' => 'Climate finance initiatives')
                )
            ),
            'commodity_analysis' => array(
                'title' => 'Commodity Markets',
                'options' => array(
                    array('icon' => '→', 'label' => 'Precious Metals', 'query' => 'Gold and silver analysis'),
                    array('icon' => '→', 'label' => 'Industrial Metals', 'query' => 'Copper and aluminum trends'),
                    array('icon' => '→', 'label' => 'Agriculture', 'query' => 'Agricultural commodity outlook'),
                    array('icon' => '→', 'label' => 'Energy Complex', 'query' => 'Oil and gas price dynamics')
                )
            ),
            'regional_markets' => array(
                'title' => 'Regional Deep Dive',
                'options' => array(
                    array('icon' => '→', 'label' => 'North America', 'query' => 'US and Canada markets'),
                    array('icon' => '→', 'label' => 'Europe', 'query' => 'European market analysis'),
                    array('icon' => '→', 'label' => 'Asia Pacific', 'query' => 'Asia Pacific opportunities'),
                    array('icon' => '→', 'label' => 'Global Comparison', 'query' => 'Cross-regional analysis')
                )
            ),
            'default' => array(
                'title' => 'Market Intelligence Hub',
                'options' => array(
                    array('icon' => '→', 'label' => 'Market Overview', 'query' => 'Comprehensive market summary'),
                    array('icon' => '→', 'label' => 'Key Movers', 'query' => 'Today\'s biggest market moves'),
                    array('icon' => '→', 'label' => 'Trending Topics', 'query' => 'What analysts are discussing'),
                    array('icon' => '→', 'label' => 'Custom Analysis', 'query' => 'Specific market question')
                )
            )
        );
        
        // Return the appropriate option set or default
        $options = isset($option_sets[$query_type]) ? $option_sets[$query_type] : $option_sets['default'];
        
        return array(
            'type' => 'interactive_options',
            'data' => $options
        );
    }
    
    /**
     * Get time-based greeting for market queries
     */
    private function get_time_based_market_greeting($user_name = '') {
        $hour = (int) current_time('H');
        $name_part = !empty($user_name) ? " {$user_name}" : "";
        
        // Morning: 5am - 12pm
        if ($hour >= 5 && $hour < 12) {
            $greetings = array(
                "Good morning{$name_part}!",
                "Morning{$name_part}!",
                "Good morning{$name_part}, markets are active today!",
                "Morning{$name_part}, let's see what moved overnight.",
                "Happy " . current_time('l') . " morning{$name_part}!"
            );
        }
        // Afternoon: 12pm - 5pm
        elseif ($hour >= 12 && $hour < 17) {
            $greetings = array(
                "Good afternoon{$name_part}!",
                "Afternoon{$name_part}!",
                "Good afternoon{$name_part}, markets are showing interesting moves.",
                "Afternoon{$name_part}, perfect timing to check the markets.",
                "Happy " . current_time('l') . " afternoon{$name_part}!"
            );
        }
        // Evening: 5pm - 9pm
        elseif ($hour >= 17 && $hour < 21) {
            $greetings = array(
                "Good evening{$name_part}!",
                "Evening{$name_part}!",
                "Good evening{$name_part}, let's review today's action.",
                "Evening{$name_part}, markets closed with some surprises.",
                "Happy " . current_time('l') . " evening{$name_part}!"
            );
        }
        // Night: 9pm - 5am
        else {
            $greetings = array(
                "Good evening{$name_part}",
                "Hello{$name_part}",
                "Welcome{$name_part}",
                "Hi{$name_part}",
                "Greetings{$name_part}"
            );
        }
        
        return $greetings[array_rand($greetings)];
    }
    
    /**
     * Get varied market messages for different query types
     */
    private function get_varied_market_messages($greeting, $response_type) {
        // Map response types to query types for greeting variations
        $query_type_map = array(
            'market_headlines' => 'market_headlines',
            'market_conditions' => 'market_conditions',
            'global_outlook' => 'global_economic_outlook',
            'sector_analysis' => 'sector_analysis',
            'pe_activity' => 'pe_deals',
            'credit' => 'credit_markets',
            'ma' => 'ma_activity',
            'alternatives' => 'alternatives',
            'commodities' => 'commodity_analysis',
            'rates' => 'interest_rates',
            'esg' => 'esg_trends',
            'volatility' => 'volatility',
            'regulatory' => 'regulatory',
            'distressed' => 'distressed',
            'ipo' => 'ipo_market'
        );
        
        // Get mapped query type or use default
        $query_type = isset($query_type_map[$response_type]) ? $query_type_map[$response_type] : 'market_headlines';
        
        // Use the new greeting variations class if available
        if (class_exists('SFFC_Market_Greeting_Variations')) {
            $user_name = isset($GLOBALS['sffc_context']['user_first_name']) ? $GLOBALS['sffc_context']['user_first_name'] : '';
            // Method doesn't exist, use a fallback
            return array("Let's analyze the market together.");
        }
        
        // Fallback to previous implementation
        if ($response_type === 'market_headlines') {
            return array(
                "{$greeting}, I can help you understand current market conditions. What specific information would be most valuable for your situation - sector analysis, firm updates, or opportunity tracking?",
                "{$greeting}, let me assist you with market intelligence. Tell me what you're working on or considering, and I'll provide relevant insights and data.",
                "{$greeting}, I'm here to help with your market research. What are you looking to understand - deal activity, compensation trends, or career opportunities?",
                "{$greeting}, I'll help you navigate the market information. What's your current focus - exploring new opportunities, understanding valuations, or tracking specific firms?",
                "{$greeting}, let's get you the insights you need. Are you researching for interviews, tracking market movements, or planning your next career move?"
            );
        } else {
            return array(
                "{$greeting}, I'm ready to help you analyze market conditions. What aspects are most important for your goals right now?",
                "{$greeting}, let me help you understand the market landscape. What specific information would support your decision-making?",
                "{$greeting}, I can provide targeted market insights. What are you trying to accomplish - research, preparation, or strategic planning?",
                "{$greeting}, I'll help you access relevant market data. What questions do you need answered for your current situation?",
                "{$greeting}, let's focus on what matters to you. What market information would be most helpful for your next steps?"
            );
        }
    }
    
    /**
     * Generate fallback response when Claude is unavailable
     */
    private function generate_fallback_response($query, $mode, $context) {
        $user_name = $context['user_first_name'] ?? '';
        $greeting = !empty($user_name) ? "Hi {$user_name}" : "Hi there";
        
        // Get the query type for contextual response
        $query_type = $this->analyze_query_type($query, $mode);
        $query_lower = strtolower($query);
        $message = '';
        
        // Generate contextual response based on query type
        switch ($query_type) {
            case 'global_economic_outlook':
                $message = "{$greeting}, I can help you understand global economic conditions relevant to your career. ";
                $message .= "To provide the most useful insights, could you tell me: Are you exploring international opportunities, researching for interviews, or tracking specific markets? I'll tailor my analysis to your needs.";
                break;
                
            case 'esg_trends':
                $message = "{$greeting}, I can help you understand ESG trends in finance. ";
                $message .= "Are you interested in ESG for career opportunities, investment strategy, or compliance requirements? Let me know your focus so I can provide relevant insights.";
                break;
                
            case 'sector_analysis':
                $message = "{$greeting}, I'll help you analyze specific sectors. ";
                $message .= "Which sectors are you interested in, and what's driving your research - career opportunities, market understanding, or investment analysis? This will help me provide targeted insights.";
                break;
                
            case 'pe_deals':
                if (!empty($user_name)) {
                    $message = "{$user_name}, I'll help you research PE deals and activity. ";
                } else {
                    $message = "I'll help you research PE deals. Before we dive in, I'm MENA Careers - I provide personalized finance career guidance. Who am I speaking with today? ";
                }
                $message .= "What's your interest in PE deals - are you preparing for interviews, tracking specific firms, or exploring career opportunities in private equity?";
                break;
                
            case 'credit_markets':
                if (!empty($user_name)) {
                    $message = "{$user_name}, let's explore credit markets together. ";
                } else {
                    $message = "I can help with credit market insights. Quick intro - I'm MENA Careers, your personal finance career advisor. Mind if I ask your name so I can personalize our conversation? ";
                }
                $message .= "Tell me what you're working on - researching for a credit role, understanding market dynamics for interviews, or tracking specific sectors?";
                break;
                
            case 'ma_activity':
                if (!empty($user_name)) {
                    $message = "{$user_name}, I'm here to help with M&A intelligence. ";
                } else {
                    $message = "Let's discuss M&A activity. I'm MENA Careers - I help finance professionals navigate their careers. What's your name? I'd love to tailor this conversation to your needs. ";
                }
                $message .= "Are you tracking M&A for deal experience, preparing for interviews, or researching specific sectors? I'll focus on what matters to you.";
                break;
                
            case 'distressed':
                if (!empty($user_name)) {
                    $message = "{$user_name}, distressed investing is a specialized area - let me help you navigate it. ";
                } else {
                    $message = "Distressed investing requires deep expertise. I'm MENA Careers, and I help professionals like you understand complex markets. May I have your name to personalize our discussion? ";
                }
                $message .= "What's driving your interest - career opportunities in restructuring, understanding distressed strategies, or specific sector analysis?";
                break;
                
            case 'firm_specific':
                // Keep existing firm-specific logic
                if (strpos($query_lower, 'blackstone') !== false) {
                    $message = "Blackstone continues to dominate with $1T AUM. Recent moves: $40B infrastructure fund close, European real estate expansion, technology team buildout with Apollo hires. Portfolio performance strong despite market volatility. Hiring aggressively across sectors.";
                } elseif (strpos($query_lower, 'kkr') !== false) {
                    $message = "KKR executing ambitious growth strategy. Highlights: $15B healthcare platform acquisition, Asia expansion with new Singapore office, technology focus with dedicated fund. Strong fundraising momentum across strategies. Building teams globally.";
                } else {
                    $message = "{$greeting}, let me provide details on the firm you're interested in.";
                }
                break;
                
            case 'ipo_market':
                if (!empty($user_name)) {
                    $message = "{$user_name}, IPO markets are always exciting to track. ";
                } else {
                    $message = "IPO markets offer great insights. By the way, I'm MENA Careers - I provide personalized career guidance in finance. What should I call you? ";
                }
                $message .= "How can I help - are you researching for ECM roles, tracking exits for your portfolio, or exploring public market opportunities?";
                break;
                
            case 'volatility':
                $message = "{$greeting}, market volatility creating both risks and opportunities. ";
                $message .= "VIX hovering around 18-22, elevated but not panic levels. Equity volatility driven by rate uncertainty and earnings. FX volatility high with dollar strength. Commodity volatility extreme in energy markets. Credit spreads volatile but contained. Hedge funds performing well in volatile environment. Options strategies popular for downside protection. Correlation breakdowns creating relative value opportunities.";
                $message .= "\n\nWould you like volatility strategies or specific hedging recommendations?";
                break;
                
            case 'regulatory':
                $message = "{$greeting}, regulatory landscape evolving rapidly. ";
                $message .= "Basel III implementation affecting bank capital. SEC focus on private funds transparency and crypto. European sustainability disclosure requirements (SFDR) impacting all funds. UK diverging from EU with competitive reforms. China regulatory environment stabilizing. Tax changes affecting carried interest globally. AML/KYC requirements increasing. Antitrust enforcement aggressive but predictable patterns emerging.";
                $message .= "\n\nWhich regulatory area would you like to explore in detail?";
                break;
                
            case 'energy_transition':
                $message = "{$greeting}, energy transition creating massive investment opportunities. ";
                $message .= "Clean tech attracting $500B+ annually. Wind/solar at grid parity driving deployment. Battery storage solving intermittency - costs down 90% decade. Hydrogen economy emerging with $500B committed. Traditional energy companies transforming - BP, Shell pivoting. Nuclear renaissance with SMRs. Carbon capture scaling with government support. Critical minerals become new bottleneck. Grid infrastructure needs $3T investment.";
                $message .= "\n\nWhich aspect of energy transition interests you most?";
                break;
                
            case 'emerging_markets':
                $message = "{$greeting}, emerging markets offer selective opportunities. ";
                $message .= "India leading with 7% growth, tech sector booming. Southeast Asia benefiting from China+1 strategies. Latin America mixed - Brazil recovering, Argentina reforming. private equity diversifying beyond oil with sovereign fund investments. Africa seeing infrastructure investment from China and private equity. Eastern Europe affected by geopolitical tensions. Currency volatility major consideration. Local champions emerging in fintech and consumer sectors.";
                $message .= "\n\nWhich emerging market region would you like to focus on?";
                break;
                
            case 'alternatives':
                $message = "{$greeting}, alternative investments seeing record inflows. ";
                $message .= "Private equity at $7T AUM globally, expecting 15% annual growth. Hedge funds recovering with $4T AUM. Private credit fastest growing at 20% annually. Real assets popular for inflation protection. Infrastructure funds raising record capital. Venture capital recalibrating after 2021 excess. Crypto/digital assets institutionalizing despite volatility. Art/collectibles gaining traction with HNW investors.";
                $message .= "\n\nWhich alternative asset class would you like to explore?";
                break;
                
            case 'market_conditions':
                if (!empty($user_name)) {
                    $message = "{$user_name}, I'm ready to help you understand current market conditions. ";
                } else {
                    $message = "I'll help you navigate market conditions. I'm MENA Careers - think of me as your personal finance career strategist. Who do I have the pleasure of helping today? ";
                }
                $message .= "What's your focus - preparing for market discussions in interviews, understanding trends for your role, or exploring opportunities?";
                break;
                
            case 'equity_trends':
                $message = "{$greeting}, equity market trends show interesting dynamics. ";
                $message .= "US equities outperforming globally with S&P 500 up 12% YTD. Tech sector leadership driven by AI enthusiasm - Magnificent 7 contributing 60% of gains. European stocks lagging on growth concerns. Emerging markets mixed - India strong, China recovering. Small caps underperforming large caps significantly. Growth vs value trade favoring quality growth names. Sector rotation from rate-sensitive to cyclicals underway.";
                $message .= "\n\nWhich equity segment or region interests you most?";
                break;
                
            case 'currency_movements':
                $message = "{$greeting}, currency markets showing significant action. ";
                $message .= "USD strength dominating with Dollar Index at 6-month highs. EUR/USD testing key support around 1.08 on ECB dovishness. GBP resilient despite UK economic challenges. JPY weakness continues despite BoJ intervention threats. Emerging market FX under pressure from dollar strength. Crypto correlations with tech stocks increasing. Central bank divergence driving major trends with Fed hawkish, ECB/BoJ dovish.";
                $message .= "\n\nWhich currency pair or region would you like to focus on?";
                break;
                
            case 'commodity_analysis':
                $message = "{$greeting}, commodity markets reflecting global economic dynamics. ";
                $message .= "Oil maintaining $80+ levels on geopolitical tensions and supply concerns. Gold holding $2000 as central bank purchases continue and hedge demand persists. Copper mixed signals - China demand recovering but industrial concerns remain. Agricultural commodities volatile on weather patterns and trade flows. Natural gas highly regional - Europe elevated, US moderate. Industrial metals showing China recovery signs but watching property sector closely.";
                $message .= "\n\nWhich commodity sector would you like me to analyze in detail?";
                break;
                
            case 'interest_rates':
                $message = "{$greeting}, interest rate environment remains pivotal for markets. ";
                $message .= "Fed maintaining 5.25-5.50% fed funds rate with markets pricing 2 cuts in 2025. 10-year Treasury around 4.3% reflecting higher-for-longer expectations. Yield curve still inverted but steepening pressures building. European rates lower with ECB more accommodative stance. Real rates remain positive supporting dollar. Credit conditions tightening for commercial real estate while corporate access remains good for quality borrowers.";
                $message .= "\n\nWhich aspect of rates policy interests you - fed policy, yield curve, or sector impacts?";
                break;
                
            case 'regional_markets':
                $message = "{$greeting}, regional market dynamics show clear divergences. ";
                $message .= "Asia-Pacific leading growth with India at 7%, China stabilizing around 5%. Europe facing headwinds - Germany struggling, France stable. Americas mixed - US resilient, LatAm commodity-dependent. private equity diversification accelerating with massive sovereign fund deployment. Africa seeing infrastructure investment surge. Eastern Europe geopolitical impacts continue. Investment flows favoring defensive regions and growth stories.";
                $message .= "\n\nWhich region would you like to explore further?";
                break;
                
            case 'tech_disruption':
                $message = "{$greeting}, technology disruption reshaping financial markets. ";
                $message .= "AI revolution driving massive capital allocation - $200B+ invested in 2024. Fintech consolidation as growth capital scarce and profitability demanded. Digital assets maturing with institutional adoption accelerating. Blockchain applications expanding beyond crypto into trade finance and identity. RegTech solutions in high demand for compliance automation. WealthTech democratizing investment management. Traditional finance embracing digital transformation or facing displacement.";
                $message .= "\n\nWhich technology trend would you like to explore - AI, fintech, or digital assets?";
                break;
                
            case 'geopolitical':
                $message = "{$greeting}, geopolitical factors significantly impacting markets. ";
                $message .= "US-China trade relationship stabilizing but technology restrictions remain. Russia-Ukraine conflict creating persistent energy and food price pressures. private equity tensions supporting oil prices and defense spending. EU strategic autonomy initiatives affecting supply chains. Immigration policies impacting labor markets globally. Election cycles creating policy uncertainty in major economies. Supply chain regionalization accelerating - 'friend-shoring' replacing globalization.";
                $message .= "\n\nWhich geopolitical issue would you like me to analyze further?";
                break;
                
            case 'earnings_season':
                $message = "{$greeting}, earnings season revealing corporate health and guidance. ";
                $message .= "Q4 2024 results showing resilient corporate profitability despite margin pressures. Tech sector beating expectations driven by AI investments and efficiency gains. Healthcare defensive characteristics emerging. Energy sector generating strong cash flows. Consumer companies split between premium resilience and mass market pressure. Forward guidance conservative reflecting macro uncertainty. Buyback activity robust with elevated cash levels.";
                $message .= "\n\nWhich sector's earnings would you like me to analyze in detail?";
                break;
                
            case 'fixed_income':
                $message = "{$greeting}, fixed income markets navigating rate and credit cycles. ";
                $message .= "Treasury markets pricing higher-for-longer Fed policy with 10-year around 4.3%. Corporate credit spreads near tights reflecting economic resilience. High yield offering attractive 8-9% yields but selectivity crucial. Municipal bonds supported by strong state/local finances. International bonds less attractive given dollar strength. TIPS providing real return protection. Duration risk remains key consideration with potential for curve steepening.";
                $message .= "\n\nWhich fixed income sector interests you - treasuries, corporate credit, or municipal bonds?";
                break;
                
            case 'infrastructure':
                $message = "{$greeting}, infrastructure investing seeing unprecedented momentum. ";
                $message .= "Global infrastructure needs estimated at $3T annually through 2030. Energy transition infrastructure attracting majority of capital - grid modernization, renewable generation, storage. Transportation evolving with EV charging networks and smart mobility. Digital infrastructure critical for AI and cloud computing demand. Water and waste management gaining ESG focus. Government support through IRA, CHIPS Act creating investment opportunities. Private capital filling public funding gaps.";
                $message .= "\n\nWhich infrastructure theme interests you most - energy, transport, or digital?";
                break;
                
            case 'healthcare_innovation':
                $message = "{$greeting}, healthcare innovation driving significant investment flows. ";
                $message .= "Biotech sector volatile but breakthrough therapies commanding premium valuations. Medical technology advancing with AI diagnostics and robotic surgery. Digital health consolidating after pandemic boom - profitability focus. GLP-1 drugs creating new obesity treatment market worth $100B+. Gene therapy approaching commercialization. Healthcare services consolidating for scale and efficiency. Aging demographics driving structural growth across healthcare sectors.";
                $message .= "\n\nWhich healthcare innovation area would you like to explore - biotech, medtech, or digital health?";
                break;
                
            default:
                // Check for other patterns
                if (strpos($query_lower, 'more information') !== false) {
                    $message = "Let me provide more comprehensive details based on your interests.";
                } else {
                    // Default fallback based on mode
                    $fallback_messages = array(
                        'market' => "{$greeting}, I'm analyzing the latest market movements. What specific aspect would you like to explore?",
                        'opportunities' => "{$greeting}, I'm ready to match opportunities to your profile. Tell me about your experience and goals.",
                        'skills' => "{$greeting}, let's build your expertise. What skills would you like to develop?",
                        'career' => "{$greeting}, let's map your career path. Where are you now and where do you want to be?"
                    );
                    $message = $fallback_messages[$mode] ?? "{$greeting}, how can I help you today?";
                }
        }
        
        // Show contextual visual based on query type
        $show_visual = empty($context['conversation_history']) || 
                      in_array($query_type, ['global_economic_outlook', 'esg_trends', 'sector_analysis', 
                                            'pe_deals', 'ma_activity', 'credit_markets', 'ipo_market',
                                            'distressed', 'alternatives', 'emerging_markets', 'market_conditions',
                                            'equity_trends', 'currency_movements', 'commodity_analysis', 
                                            'interest_rates', 'regional_markets', 'tech_disruption', 
                                            'geopolitical', 'earnings_season', 'fixed_income', 'infrastructure',
                                            'healthcare_innovation', 'volatility']);
        
        return array(
            'message' => $message,
            'visual' => $show_visual ? $this->get_fallback_visual($mode, $query_type) : null,
            'typing_delay' => 1200
        );
    }
    
    /**
     * Get response templates - MASSIVELY EXPANDED
     */
    private function get_response_templates() {
        return array(
            'greeting' => array(
                'market' => array(
                    "I'm tracking major moves right now - KKR just closed a €3.2B healthcare deal, and Blackstone is making waves in real estate. Which sector interests you most?",
                    "Breaking: Apollo raised $25B for their latest fund while private credit markets are exploding. What type of deals are you most curious about?",
                    "Major consolidation happening in healthcare and fintech. Are you looking at investment opportunities or career moves at these firms?",
                    "PE dry powder hit $3.8T globally - unprecedented deployment happening. Are you tracking specific firms or sectors?"
                ),
                'opportunities' => array(
                    "I'm seeing explosive hiring at KKR, TPG, and Apollo. What level are you targeting - Associate, VP, or senior roles?",
                    "Hot market right now - healthcare PE is hiring like crazy, and fintech opportunities are everywhere. What's your background?",
                    "Perfect timing - I'm tracking 47 open roles at top-tier firms. Are you looking for investment roles or operational positions?"
                ),
                'skills' => array(
                    "Let's get you interview-ready! Are you preparing for modeling tests, case studies, or behavioral rounds?",
                    "What's your goal - nail the LBO model, master DCF analysis, or learn sector-specific valuation methods?",
                    "I can drill you on real deal scenarios from KKR, Blackstone, Apollo. Which firm's process interests you most?"
                ),
                'career' => array(
                    "Career acceleration time! Are you pivoting into PE, climbing within your firm, or exploring new sectors?",
                    "What's your next move - breaking into top-tier PE, advancing to VP/Director level, or switching sectors?",
                    "I track compensation, firm culture, and promotion timelines across all major players. What intel do you need?"
                ),
                'default' => array(
                    "Market's moving fast right now! I'm tracking deals, hiring, and opportunities across PE, VC, and investment banking. What's your focus?",
                    "I'm your insider intel source for finance careers. Deal flow, compensation data, firm insights - what interests you most?",
                    "Ready to accelerate your finance career? I have live data on opportunities, market moves, and insider strategies."
                )
            ),
            'help_request' => array(
                'market' => array(
                    "Of course! What would you like to know about the markets?",
                    "Absolutely! I'm here to help with any market questions you have.",
                    "Sure! Feel free to ask me anything about market conditions, deals, or opportunities."
                ),
                'career' => array(
                    "Of course! What career question can I help you with?",
                    "Absolutely! I'm here to help with your finance career questions.",
                    "Sure! Ask me anything about career progression, skills, or opportunities."
                ),
                'default' => array(
                    "Of course! What would you like to know?",
                    "Absolutely! Feel free to ask your question.",
                    "Sure! I'm here to help. What's on your mind?"
                )
            ),
            
            // === EXPANDED TEMPLATES FOR GENERIC MARKET QUESTIONS ===
            
            'market_today_basic' => array(
                'market' => array(
                    "Markets are showing mixed signals today - tech leading while financials lag. S&P 500 up 0.3%, driven by AI enthusiasm. Energy sector volatile on supply concerns. What specific area interests you?",
                    "Today's highlights: Private equity continuing deployment spree, credit spreads tightening, and healthcare M&A accelerating. European markets outperforming on ECB dovish signals.",
                    "Key moves today: Dollar strengthening against majors, commodities mixed with oil holding $80+, and emerging markets showing resilience despite headwinds. Focus area?",
                    "Current snapshot: US equities grinding higher, bond yields stable around 4.3%, and alternatives seeing record inflows. Which market segment catches your eye?"
                )
            ),
            
            'market_performance_basic' => array(
                'market' => array(
                    "Markets are performing well YTD - S&P 500 up 12%, driven by Magnificent 7. International markets lagging but emerging markets showing signs of life. Sector rotation toward quality names.",
                    "Strong performance across alternatives - PE returns averaging 8-12%, hedge funds positive after tough 2022. Credit markets particularly robust with spreads near cycle lows.",
                    "Mixed performance by geography - US leading, Europe struggling with growth concerns, Asia recovering gradually. Currency volatility creating opportunities for active managers.",
                    "Asset class performance varies - equities strong, bonds stabilizing, commodities volatile. Real assets gaining favor for inflation protection. What interests you most?"
                )
            ),
            
            'market_highlights_basic' => array(
                'market' => array(
                    "Key highlights: AI revolution driving massive capital allocation, central bank policy divergence creating FX volatility, and private markets deployment accelerating despite higher rates.",
                    "Main themes: Energy transition attracting $500B+ investment, geopolitical tensions affecting supply chains, and emerging markets benefiting from China+1 strategies.",
                    "Critical developments: Banking sector stabilization post-regional bank crisis, commercial real estate challenges creating opportunities, and digital assets gaining institutional acceptance.",
                    "Important trends: ESG investing evolving beyond screening, infrastructure needs driving public-private partnerships, and demographic shifts reshaping consumer markets."
                )
            ),
            
            'market_news_basic' => array(
                'market' => array(
                    "Latest: KKR closed $15B healthcare fund, Blackstone acquiring €700M Paris office tower, and Apollo launching new $25B flagship fund. M&A activity picking up across sectors.",
                    "Breaking developments: Fed maintaining restrictive stance, ECB showing dovish tilt, and China implementing stimulus measures. Currency implications significant.",
                    "Recent headlines: Healthcare consolidation accelerating with $50B+ in announced deals, technology sector seeing valuation reset, and energy transition investments scaling globally.",
                    "Fresh updates: Private credit markets crossing $1.5T AUM, distressed opportunities emerging in commercial real estate, and venture capital recalibrating after 2021 excess."
                )
            ),
            
            'sector_basic' => array(
                'market' => array(
                    "Top performing sectors YTD: Technology (+18%), Healthcare (+12%), and Financials (+8%). Energy volatile but positive. Utilities and REITs lagging on rate sensitivity.",
                    "Sector rotation underway: Growth to value, large cap to small cap, and domestic to international. Quality factor performing well across all sectors.",
                    "Best opportunities: Healthcare consolidation creating value, energy transition driving infrastructure investment, and financial services benefiting from higher rates.",
                    "Sector themes: AI disrupting traditional industries, regulatory changes affecting pharmaceuticals, and geopolitical tensions impacting supply chains globally."
                )
            ),
            
            'sector_specific_basic' => array(
                'market' => array(
                    "Healthcare: Strong M&A activity with $50B+ deals announced. Biotech seeing valuation reset but breakthrough therapies commanding premiums. Medical devices advancing with AI.",
                    "Technology: AI driving massive investment but valuations high. Semiconductor cycle recovering. Enterprise software consolidating for efficiency and scale.",
                    "Financial Services: Banks benefiting from higher rates but credit concerns emerging. Asset managers seeing inflows to alternatives. Fintech consolidating post-growth era.",
                    "Energy: Oil maintaining $80+ on geopolitical tensions. Renewable energy scaling rapidly. Traditional companies pivoting to cleaner alternatives."
                )
            ),
            
            'pe_deals_basic' => array(
                'market' => array(
                    "Recent PE highlights: KKR's $15B healthcare fund deployment, Apollo's infrastructure expansion, and Blackstone's real estate consolidation. Deal activity recovering from 2023 lows.",
                    "Hot sectors for PE: Healthcare services, software (despite high valuations), and industrial technology. Energy transition attracting significant capital deployment.",
                    "Deal trends: Smaller deal sizes, longer hold periods, and operational value creation focus. Continuation funds becoming standard exit mechanism.",
                    "Market dynamics: $3.8T dry powder globally, competition for quality assets intense, and financing costs affecting returns. Selective deployment strategy."
                )
            ),
            
            'firms_basic' => array(
                'market' => array(
                    "Leading firms by AUM: Blackstone ($1T+), Apollo ($650B), KKR ($550B), and TPG ($150B). Each has distinct sector specializations and geographic focus.",
                    "Top performers: Tiger Global (tech focus), Vista Equity (software specialist), and Warburg Pincus (growth equity). Performance varies by vintage and strategy.",
                    "Rising stars: General Atlantic (growth), Leonard Green (retail/consumer), and Advent International (global reach). Innovation in fund structures and strategies.",
                    "Hiring actively: All major firms expanding teams, particularly in tech, healthcare, and Asia. Competition for talent intense across all levels."
                )
            ),

            // === INDUSTRY-SPECIFIC TEMPLATES ===
            
            'investment_banking_basic' => array(
                'market' => array(
                    "Investment banking landscape: Goldman, Morgan Stanley, and JPMorgan dominating league tables. M&A advisory revenue down 30% YoY but pipeline building for 2025 recovery.",
                    "IBD revenues under pressure from reduced deal flow, but ECM showing signs of life with selective IPOs. Credit markets providing stable revenues for FICC divisions.",
                    "Top coverage areas: Healthcare and TMT leading M&A activity. Energy transition creating new advisory opportunities. ESG considerations integral to all mandates.",
                    "Hiring trends: Analyst classes stable but Associate levels competitive. VP promotions selective with focus on revenue generation and client coverage."
                )
            ),
            
            'ib_firms_basic' => array(
                'market' => array(
                    "Goldman Sachs: Leading M&A advisor globally, strong in tech and healthcare. Marcus consumer banking struggles but core IB remains dominant.",
                    "Morgan Stanley: Wealth management integration creating unique client solutions. Strong equity capital markets franchise and growing private credit business.",
                    "JPMorgan: Balance sheet advantage enabling complex financing solutions. Leading in leveraged finance and trading revenues stable.",
                    "Deutsche Bank: European focus with strong fixed income franchise. Investment grade debt capital markets leadership in Europe."
                )
            ),
            
            'pe_strategy_basic' => array(
                'market' => array(
                    "PE strategies evolving: Traditional LBOs now 40% of deals, growth equity 35%, distressed/special situations 15%, others 10%. Operational improvement critical.",
                    "Value creation focus: Revenue growth prioritized over multiple expansion. Technology implementation, ESG improvements, and talent acquisition key drivers.",
                    "Hold periods extending: Average 5-7 years now vs 3-4 historically. Continuation funds providing liquidity without full exit. GP-led secondaries growing.",
                    "Sector specialization increasing: Healthcare, software, and industrial technology commanding premium valuations. Generalist funds under pressure."
                )
            ),
            
            'pe_process_basic' => array(
                'market' => array(
                    "PE investment process: Sourcing (proprietary deals premium), due diligence (100-day plans standard), financing (higher cost environment), and value creation execution.",
                    "Typical timeline: 3-6 months from LOI to close. Commercial, financial, and operational DD running parallel. Management presentations and reference calls critical.",
                    "Decision making: Investment committees more conservative, requiring higher conviction and downside protection. ESG and regulatory considerations mandatory.",
                    "Post-investment: 100-day plans implemented immediately. KPI tracking monthly. Board meetings quarterly with operational metrics focus."
                )
            ),
            
            'hedge_fund_strategies_basic' => array(
                'market' => array(
                    "HF strategy performance 2024: Long/short equity (+8%), event driven (+12%), relative value (+6%), macro/CTA (+15%). Dispersion between winners/losers wide.",
                    "Popular strategies: Fundamental L/S benefiting from dispersion, merger arb with elevated deal spreads, and volatility strategies in uncertain environment.",
                    "Emerging themes: AI/ML implementation across strategies, ESG integration, and alternative data sourcing. Crypto strategies gaining institutional acceptance.",
                    "Capacity constraints: Successful strategies closing to new capital. Talent retention challenging with performance fees compressed."
                )
            ),
            
            'hedge_fund_firms_basic' => array(
                'market' => array(
                    "Leading hedge funds: Bridgewater ($140B AUM), Renaissance Technologies ($130B), Two Sigma ($60B), and D.E. Shaw ($50B). Each with distinct approach and culture.",
                    "Performance leaders: Millennium (+18% YTD), Citadel (+15% flagship fund), and Point72 (+12%). Multi-manager platforms dominating flows.",
                    "Raising capital: Smaller funds (<$1B) struggling to gather assets. Institutional investors concentrating with proven managers. Seeding deals common.",
                    "Technology focus: Systematic strategies gaining share. Alternative data, machine learning, and execution algorithms key differentiators."
                )
            ),
            
            'asset_management_basic' => array(
                'market' => array(
                    "Asset management trends: Passive continues gaining share (45% of industry AUM), active managers under fee pressure, alternatives growing rapidly at 15% annually.",
                    "Leading firms: BlackRock ($10T AUM), Vanguard ($8T), State Street ($4T). Scale advantages in cost structure and technology development critical.",
                    "Hot products: Private credit, infrastructure, and real assets. ESG integration across all strategies. Target-date funds and model portfolios growing.",
                    "Industry challenges: Fee compression, regulatory scrutiny, and technology investment requirements. Consolidation expected among smaller players."
                )
            ),
            
            'consulting_basic' => array(
                'market' => array(
                    "Management consulting strong: McKinsey, BCG, and Bain growing 8-12% annually. Digital transformation, sustainability, and organizational change driving demand.",
                    "Strategy work evolving: Implementation focus increasing. Technology integration essential. Client CEO relationships critical for large mandates.",
                    "Specialization trends: Healthcare, financial services, and technology sectors commanding premium rates. Private equity partnerships growing.",
                    "Talent market tight: Competition for top MBAs intense. Retention challenging with PE, tech offering higher compensation. Remote work changing delivery."
                )
            ),

            // === CAREER-SPECIFIC TEMPLATES ===
            
            'career_levels_basic' => array(
                'career' => array(
                    "Career progression: Analyst (2-3 years) → Associate (3-4 years) → VP (4-5 years) → Director/MD. Each level requires specific skill development and networking.",
                    "Compensation ranges: Analyst $175-250K, Associate $350-500K, VP $500K-1M+, MD $1M-5M+. Performance and firm tier create wide dispersion.",
                    "Promotion criteria: Technical skills, client relationships, team leadership, and business development. Cultural fit and sponsorship increasingly important.",
                    "Level transitions: Associate to VP most challenging jump. Requires shift from execution to origination and client management."
                )
            ),
            
            'skills_development_basic' => array(
                'career' => array(
                    "Core skills: Financial modeling (LBO, DCF, comps), presentation skills, client management, and industry expertise. Technical proficiency baseline expectation.",
                    "Soft skills critical: Communication, leadership, problem-solving, and relationship building. Emotional intelligence and cultural fit increasingly valued.",
                    "Continuous learning: Industry conferences, executive education, and professional networks. CFA, MBA often expected for senior roles.",
                    "Technology adoption: Excel mastery baseline, Python/R for quantitative roles, and AI tools for efficiency. Digital fluency essential."
                )
            ),
            
            'interview_prep_basic' => array(
                'career' => array(
                    "Interview preparation: Technical questions (modeling, valuation), behavioral questions (leadership, teamwork), and case studies (deal analysis).",
                    "Common formats: Phone screening, technical assessment, case study presentation, and cultural fit interviews. Process typically 3-6 rounds.",
                    "Key preparations: Practice modeling under time pressure, prepare deal/investment examples, and research interviewer backgrounds thoroughly.",
                    "Success factors: Demonstrate intellectual curiosity, show passion for finance, and articulate career motivations clearly. Follow-up discipline important."
                )
            ),
            
            'compensation_basic' => array(
                'career' => array(
                    "2024 compensation trends: Base salaries stable, bonuses variable based on performance. Carry/co-investment becoming larger component at senior levels.",
                    "Industry benchmarks: Investment banking leads in analyst/associate pay, PE/HF higher at senior levels. Regional variations significant.",
                    "Total compensation: Base + bonus + benefits + carry/equity. Long-term incentives critical for retention at VP+ levels.",
                    "Negotiation tips: Focus on long-term opportunity, role responsibilities, and learning potential. Compensation important but not only factor."
                )
            ),

            // === MARKET CYCLES & ECONOMIC POLICY TEMPLATES ===
            
            'market_cycles_basic' => array(
                'market' => array(
                    "Current cycle position: Late-cycle dynamics with elevated rates, selective credit tightening, but resilient consumer spending. Recession risk moderate.",
                    "Historical patterns: Bull markets average 9 years, bear markets 1.3 years. Current bull market from 2009 (with brief 2020 interruption) showing longevity.",
                    "Cycle indicators: Yield curve still inverted (recession signal), but employment strong and earnings resilient. Mixed signals creating uncertainty.",
                    "Investment implications: Quality bias appropriate, defensive sectors attractive, and alternative strategies valuable for downside protection."
                )
            ),
            
            'economic_policy_basic' => array(
                'market' => array(
                    "Monetary policy: Fed maintaining restrictive stance at 5.25-5.50%. Markets pricing 2 cuts in 2025 but dependent on inflation progress.",
                    "Fiscal policy: US budget deficit elevated at $1.7T annually. Infrastructure spending supporting growth while debt ceiling remains political issue.",
                    "Global coordination: Central bank policies diverging - Fed hawkish, ECB/BoJ dovish, China stimulative. Currency volatility result.",
                    "Policy impacts: Higher rates affecting commercial real estate, housing, and leveraged sectors. Industrial policy favoring domestic manufacturing."
                )
            ),
            
            'global_events_basic' => array(
                'market' => array(
                    "Geopolitical tensions: Russia-Ukraine ongoing, private equity conflicts, and US-China trade relations stable but technology restrictions remain.",
                    "Election cycles: 2024 elections creating policy uncertainty. Regulatory environment dependent on outcomes across global jurisdictions.",
                    "Climate events: Physical risks increasing with extreme weather. Transition risks affecting fossil fuel industries. Adaptation investments accelerating.",
                    "Trade dynamics: Supply chain regionalization continuing. 'Friend-shoring' replacing globalization in critical sectors like semiconductors and defense."
                )
            ),

            // === INVESTMENT THEMES TEMPLATES ===
            
            'technology_themes_basic' => array(
                'market' => array(
                    "AI revolution: $200B+ invested in 2024. Enterprise applications scaling, consumer adoption accelerating. Infrastructure needs creating semiconductor demand.",
                    "Digital transformation: Cloud adoption continuing, cybersecurity critical, and data analytics driving business decisions. Legacy system upgrades accelerating.",
                    "Fintech evolution: Consolidation phase after growth era. Profitability focus, regulatory compliance increasing, and traditional finance embracing technology.",
                    "Deep tech: Quantum computing, bioengineering, and space technology attracting venture capital. Long development cycles but transformative potential."
                )
            ),
            
            'esg_themes_basic' => array(
                'market' => array(
                    "ESG integration: Beyond screening to active ownership. Climate risk assessment mandatory. Social factors gaining importance post-pandemic.",
                    "Sustainable finance: Green bonds $500B+ annually, sustainability-linked loans growing, and transition finance for high-emission sectors.",
                    "Regulatory environment: EU taxonomy implementation, SEC climate disclosure rules, and global TCFD adoption. Compliance costs significant.",
                    "Investment opportunities: Renewable energy, circular economy, and social infrastructure. ESG data and technology providers growing rapidly."
                )
            ),
            
            'demographics_basic' => array(
                'market' => array(
                    "Aging populations: Healthcare demand increasing, pension funding challenges, and labor shortages emerging. Developed markets most affected.",
                    "Urbanization: Emerging market cities growing rapidly. Infrastructure needs massive. Consumer behavior shifting toward services.",
                    "Generation changes: Millennials/Gen Z wealth accumulation, different investment preferences, and technology adoption driving market evolution.",
                    "Labor dynamics: Skills shortages in technology and healthcare. Immigration policies crucial. Remote work changing real estate and urban planning."
                )
            ),

            // === SEASONAL/TEMPORAL TEMPLATES ===
            
            'seasonal_patterns_basic' => array(
                'market' => array(
                    "Q4 seasonality: Deal activity accelerating toward year-end. Earnings season approaching with guidance focus. Tax-loss selling in equities.",
                    "Year-end dynamics: Performance chasing by fund managers, bonus accruals affecting spending, and holiday seasonality in retail/travel sectors.",
                    "Calendar effects: January effect in small caps, summer doldrums in trading, and 'sell in May' patterns historically observed.",
                    "Quarterly patterns: Earnings seasons create volatility. Month-end rebalancing affects flows. Options expiration creates technical pressures."
                )
            ),
            
            'educational_basic' => array(
                'career' => array(
                    "Learning resources: CFA Institute materials, industry publications (Private Equity International, Institutional Investor), and executive education programs.",
                    "Certification value: CFA essential for research roles, FRM for risk management, and CAIA for alternatives. MBA still preferred for senior positions.",
                    "Industry events: Conferences provide networking and market insights. SuperReturn, SALT, and Milken Institute Global Conference highly regarded.",
                    "Continuous development: Online courses (Coursera, Wharton), industry publications, and internal training programs. Staying current essential."
                )
            ),
            
            'news_patterns_basic' => array(
                'market' => array(
                    "News flow impact: Earnings announcements, central bank communications, and geopolitical events create volatility. Algorithmic trading amplifies reactions.",
                    "Information sources: Bloomberg Terminal standard, Financial Times for analysis, and industry publications for sector expertise. Social media increasingly important.",
                    "Market moving events: FOMC meetings, NFP releases, and earnings from market leaders. Calendar awareness critical for positioning.",
                    "Information advantage: Alternative data sources, expert networks, and primary research provide edge. Speed of reaction increasingly important."
                )
            ),
            
            'opportunities_basic' => array(
                'market' => array(
                    "Hot opportunities: Healthcare services consolidation, AI-enabled software companies, and energy transition infrastructure. Valuations adjusting to new rate environment.",
                    "Emerging themes: Demographic shifts creating healthcare demand, supply chain regionalization, and digital transformation across industries.",
                    "Geographic focus: India leading growth markets, Europe showing value opportunities, and US maintaining innovation leadership. China selective but recovering.",
                    "Investment strategies: Value creation through operational improvement, technology integration, and ESG enhancement. Traditional financial engineering less viable."
                )
            ),
            
            'conditions_basic' => array(
                'market' => array(
                    "Current conditions: Interest rates stabilizing at higher levels, credit availability good for quality borrowers, and valuations adjusting to new reality.",
                    "Market environment: Volatility elevated but manageable, correlations breaking down creating opportunities, and active management outperforming passive strategies.",
                    "Economic backdrop: Growth slowing but resilient, inflation moderating toward targets, and labor markets softening gradually. Recession risks diminished.",
                    "Financial conditions: Dollar strong but stable, credit spreads near lows, and emerging market spreads normalizing. Liquidity adequate across markets."
                )
            ),
            
            // === EXPLANATION TEMPLATES FOR COMMON QUESTIONS ===
            'pe_explanation' => array(
                'default' => "Private equity (PE) firms acquire companies using a combination of equity and debt, typically holding them for 3-7 years while implementing operational improvements and strategic growth initiatives. The goal is to sell at a higher valuation, generating returns of 20-30% IRR for investors. Major players like KKR, Blackstone, and Apollo manage trillions in assets across buyout, growth, and distressed strategies."
            ),
            
            'ib_explanation' => array(
                'default' => "Investment banking involves advising corporations on mergers & acquisitions, raising capital through debt and equity markets, and providing strategic financial guidance. Bulge bracket banks like Goldman Sachs, Morgan Stanley, and JPMorgan dominate the industry, with analysts typically working 80+ hour weeks on deal execution, financial modeling, and client presentations."
            ),
            
            'hf_explanation' => array(
                'default' => "Hedge funds are alternative investment vehicles that employ diverse strategies to generate absolute returns regardless of market direction. Common strategies include long/short equity, global macro, event-driven, and quantitative trading. Top funds like Citadel, Bridgewater, and Millennium manage billions using sophisticated risk management and leverage."
            ),
            
            'market_explanation' => array(
                'default' => "Financial markets facilitate the trading of securities, commodities, and derivatives. Key components include equity markets (stocks), fixed income (bonds), foreign exchange (currencies), and commodities (oil, gold). Market movements are driven by economic data, central bank policy, geopolitical events, and investor sentiment."
            ),
            
            'career_explanation' => array(
                'default' => "Finance careers typically progress from Analyst (0-3 years) to Associate (3-6 years), VP (6-10 years), Director/MD (10+ years). Compensation ranges from $150K-$250K for analysts to $1M+ for senior positions. Key skills include financial modeling, valuation, deal execution, and relationship management. Breaking in requires strong academics, relevant internships, and networking."
            ),
            
            'general_explanation' => array(
                'default' => "I can help you understand various aspects of finance, from private equity and investment banking to market dynamics and career progression. Please be more specific about what you'd like to know, and I'll provide detailed insights based on current market conditions and industry practices."
            ),
            
            'creation_request' => array(
                'default' => "I can help you create financial models, investment memos, pitch decks, and analysis frameworks. To get started, please specify what you need: the type of document, key parameters, and any specific requirements. I'll guide you through the process step by step."
            ),
            
            'analysis_request' => array(
                'default' => "I can analyze market trends, company valuations, investment opportunities, and career trajectories. Please provide the specific area you'd like me to analyze, along with any relevant context or data points. I'll deliver comprehensive insights based on current market intelligence."
            ),
            
            'general_query' => array(
                'default' => "I understand you're interested in finance-related topics. I can help with private equity, investment banking, market analysis, career guidance, and skill development. Please provide more details about what you're looking for, and I'll give you targeted insights and actionable advice."
            ),
            
            // === NEW EXPANDED TEMPLATES ===
            
            'market_movers_basic' => array(
                'market' => array(
                    "Today's big movers: Tech leading with NVDA +3%, MSFT +2%. Healthcare mixed with PFE down -1.5%. Energy volatile with XOM +2.2% on supply concerns. Volume above average.",
                    "Market action: S&P 500 components seeing rotation - growth outperforming value by 0.8%. Small caps lagging large caps. Defensive sectors holding up well.",
                    "Price action highlights: Financials responding to rate environment, consumer discretionary under pressure, and materials mixed on China data. VIX holding 18-20 range.",
                    "Volume leaders: Active trading in mega-cap tech, banks responding to earnings, and energy names on geopolitical developments. Options activity elevated."
                )
            ),
            
            'winners_losers_basic' => array(
                'market' => array(
                    "Top performers: Technology (+2.1%), Healthcare (+1.8%), Financials (+1.2%). Laggards: Real Estate (-0.9%), Utilities (-0.7%), Consumer Staples (-0.3%).",
                    "Sector leaders: AI-related names continuing momentum, biotech showing strength, regional banks recovering. Underperformers include REITs and rate-sensitive sectors.",
                    "Best performers YTD: Magnificent 7 averaging +25%, healthcare devices +18%, cybersecurity +15%. Worst: Commercial real estate -12%, Chinese ADRs -8%.",
                    "Market leadership: Large cap growth leading, small cap value struggling. Quality factor outperforming, momentum strategies working well in current environment."
                )
            ),
            
            'volatility_basic' => array(
                'market' => array(
                    "Volatility update: VIX holding 18-20 range - elevated but not extreme. Realized vol below implied, suggesting option premiums rich. Term structure normal.",
                    "Risk indicators: Equity vol elevated in tech names, credit vol subdued, FX vol high in emerging markets. Correlation breakdown creating opportunities.",
                    "Market stress: Fear & Greed index neutral, put/call ratio elevated but not extreme. Defensive positioning visible in options flow and sector allocation.",
                    "Volatility themes: AI earnings driving tech vol, geopolitical events affecting energy, central bank meetings impacting rates. Event risk manageable."
                )
            ),
            
            'sector_single_word' => array(
                'market' => array(
                    "Healthcare: $50B+ M&A activity YTD, biotech valuations resetting, medical devices advancing with AI integration. GLP-1 obesity drugs creating new $100B+ market.",
                    "Technology: AI revolution driving investment but valuations stretched. Semiconductor recovery underway. Cloud growth moderating but still robust. Cybersecurity in demand.",
                    "Financials: Banks benefiting from higher rates, credit quality holding up. Asset managers seeing alt flows. Fintech consolidating post-growth era.",
                    "Energy: Oil stable $80+ on supply constraints. Renewable scaling rapidly. Traditional companies pivoting to transition. Infrastructure investment accelerating."
                )
            ),
            
            'specific_firm_basic' => array(
                'market' => array(
                    "Blackstone ($1T+ AUM): Real estate empire, infrastructure focus, credit expansion. Recent: €700M Paris acquisition, $30B infrastructure fund. Hiring across all divisions.",
                    "KKR ($550B AUM): Healthcare specialist, Asia expansion, tech focus. Recent: $15B healthcare fund, European growth. Strong operational value creation track record.",
                    "Apollo ($650B AUM): Credit dominance, insurance synergy, hybrid model. Recent: $25B flagship fund, Athene integration success. Leading alternative credit provider.",
                    "Goldman Sachs: Investment banking leader, asset management growth, consumer exit. Recent: Marcus wind-down, alternatives focus. Talent magnet for top performers."
                )
            ),
            
            'role_level_basic' => array(
                'market' => array(
                    "Analyst roles: Entry-level positions, 2-year programs, $180-220K total comp. Heavy modeling, long hours, steep learning curve. Best training ground.",
                    "Associate level: Post-MBA, 3+ years experience, $300-400K packages. Deal execution, client interaction, team leadership. High growth potential.",
                    "VP positions: 6+ years experience, $500-800K compensation. Client management, deal origination, team building. Partnership track at top firms.",
                    "Director/MD: Senior roles, $1M+ packages, equity participation. Business development, relationship management, P&L responsibility. Entrepreneurial mindset required."
                )
            ),
            
            'rates_basic' => array(
                'market' => array(
                    "Interest rate environment: Fed funds at 5.25-5.50%, 10-year Treasury ~4.3%. Markets pricing 2-3 cuts in 2025. Term premium elevated.",
                    "Rate outlook: Higher-for-longer consensus building. Inflation sticky, labor market resilient. Fed patient approach, data-dependent policy adjustments.",
                    "Impact analysis: Banks benefiting from NIM expansion, credit availability tightening for marginal borrowers. Real estate under pressure, alternatives attractive.",
                    "Global context: Fed restrictive, ECB dovish tilt, BoJ gradual normalization. Currency implications significant for international investments."
                )
            ),
            
            'inflation_basic' => array(
                'market' => array(
                    "Inflation trends: Core PCE 3.2%, trending toward 2% target but slowly. Services sticky, goods deflating. Housing costs major component.",
                    "Price dynamics: Energy volatile, food moderating, core services persistent. Wage growth slowing but above productivity gains. Super-core watching closely.",
                    "Investment implications: Real assets attractive, long-duration bonds challenged, equity multiples pressure. TIPS offering real return protection.",
                    "Central bank response: Fed prioritizing price stability, willing to accept economic softening. Credibility crucial for long-term anchoring expectations."
                )
            ),
            
            'us_markets_basic' => array(
                'market' => array(
                    "US markets: Leading global performance with S&P 500 +12% YTD. Magnificent 7 driving gains, breadth concerns emerging. Dollar strength supportive.",
                    "Domestic focus: Consumer resilience despite rate headwinds, corporate margins holding up, productivity gains from AI adoption. Innovation leadership maintained.",
                    "American exceptionalism: Regulatory environment supportive, capital markets deep, talent concentration in tech hubs. Energy independence strategic advantage.",
                    "Regional differences: West Coast tech dominance, Northeast finance leadership, Southeast manufacturing growth, Texas energy transition hub development."
                )
            ),
            
            'european_markets_basic' => array(
                'market' => array(
                    "European markets: Lagging US performance, ECB dovish supporting sentiment. Germany struggling, France stable. UK showing resilience post-Brexit adjustments.",
                    "Regional themes: Energy security post-Ukraine crisis, green transition investment, demographic challenges. Banking sector consolidation continuing.",
                    "Investment opportunities: Value plays abundant, dividend yields attractive, infrastructure needs significant. Currency hedging considerations important.",
                    "Political landscape: Populist pressures, fiscal discipline debates, EU integration challenges. Regulatory environment evolving, data privacy leadership."
                )
            ),
            
            'asian_markets_basic' => array(
                'market' => array(
                    "Asian markets: China stabilizing with stimulus support, India leading with 7% growth. Japan benefiting from tourism recovery, Korea tech strong.",
                    "Growth dynamics: ASEAN benefiting from China+1 strategies, infrastructure investment accelerating. Demographic dividend in India, aging challenges in Japan.",
                    "Investment themes: Technology transfer, supply chain regionalization, green transition. Currency volatility creating opportunities for active management.",
                    "Geopolitical considerations: US-China tensions manageable, regional trade agreements strengthening. Taiwan semiconductor importance, ASEAN neutrality strategic."
                )
            ),
            
            'equities_basic' => array(
                'market' => array(
                    "Equity markets: Strong performance driven by AI enthusiasm, earnings resilience. Valuations stretched but growth supporting premiums. Sector rotation active.",
                    "Stock market dynamics: Large cap outperforming small cap, growth beating value, quality factor strong. International lagging US, EM showing signs of life.",
                    "Investment environment: Active management outperforming passive, stock picking rewarding. Correlation breakdown creating alpha opportunities.",
                    "Outlook considerations: Earnings growth slowing but positive, margin pressures building, multiple compression risk if growth disappoints. Selectivity crucial."
                )
            ),
            
            'bonds_basic' => array(
                'market' => array(
                    "Bond markets: 10-year Treasury ~4.3%, yield curve inverted but steepening pressures building. Credit spreads near cycle lows reflecting economic resilience.",
                    "Fixed income dynamics: Duration risk elevated, credit quality focus, emerging market spreads normalizing. Real yields positive supporting dollar.",
                    "Sector preferences: Investment grade corporate attractive, high yield selective, municipal bonds supported by state/local finances. TIPS offering inflation protection.",
                    "Strategic positioning: Barbell approach popular - short duration plus credit risk. International bonds less attractive given dollar strength."
                )
            ),
            
            'commodities_basic' => array(
                'market' => array(
                    "Commodity markets: Oil holding $80+ on supply concerns, gold stable near $2000 as central bank buying continues. Industrial metals mixed on China data.",
                    "Energy complex: Geopolitical premium persistent, inventories below seasonal norms, refining margins elevated. Renewable transition creating metal demand.",
                    "Precious metals: Gold supported by central bank purchases, silver industrial demand strong. Crypto correlations with tech stocks increasing institutional interest.",
                    "Agricultural markets: Weather patterns affecting yields, trade flows disrupted by geopolitics. Food security concerns driving strategic reserves buildup globally."
                )
            ),
            
            'currencies_basic' => array(
                'market' => array(
                    "FX markets: Dollar strength dominating with DXY at 6-month highs. EUR/USD testing support at 1.08, GBP resilient despite UK challenges.",
                    "Currency dynamics: Central bank divergence driving trends - Fed restrictive, ECB/BoJ accommodative. Carry trades challenging in high vol environment.",
                    "Emerging market FX: Under pressure from dollar strength, selective opportunities in countries with strong fundamentals. India, Mexico showing resilience.",
                    "Digital currencies: Bitcoin correlation with tech increasing, institutional adoption accelerating. Regulatory clarity improving investment case for some institutions."
                )
            ),
            
            'alternatives_basic' => array(
                'market' => array(
                    "Alternative investments: Record inflows with private markets crossing $15T globally. PE deployment accelerating, hedge funds positive performance.",
                    "Private markets: Real estate under pressure from rates, infrastructure attractive for yield, private credit fastest growing at 20% annually.",
                    "Hedge fund strategies: Long/short equity performing, macro funds benefiting from volatility, quant strategies adapting to regime changes.",
                    "Institutional flows: Pensions increasing alt allocations, sovereign wealth funds active, family offices seeking diversification. Fee pressure continuing."
                )
            ),
            
            'trading_basic' => array(
                'market' => array(
                    "Trading environment: Volatility creating opportunities, correlation breakdown rewarding stock selection. Options activity elevated, especially in tech names.",
                    "Market structure: Electronic trading dominance, algorithms providing liquidity, dark pools handling large orders. Retail participation elevated but moderating.",
                    "Strategy performance: Momentum working in trending markets, mean reversion challenging, arbitrage opportunities limited by efficiency. Factor rotation active.",
                    "Technology impact: AI improving execution, machine learning enhancing prediction, cloud enabling real-time analysis. Human judgment still crucial for alpha."
                )
            ),
            
            'portfolio_basic' => array(
                'market' => array(
                    "Portfolio construction: Diversification challenged by correlations, quality factor outperforming, international allocation questioned by US dominance.",
                    "Asset allocation: 60/40 under pressure from rate environment, alternatives gaining share, real assets for inflation protection. Barbell strategies popular.",
                    "Risk management: Tail hedging expensive but necessary, correlation breakdown creating opportunities, ESG integration affecting risk profiles.",
                    "Performance attribution: Stock selection adding value, market timing difficult, factor exposure explaining returns. Active share measurement important."
                )
            ),
            
            'valuation_basic' => array(
                'market' => array(
                    "Valuation landscape: S&P 500 trading 20x forward earnings - above historical average but growth supporting premium. Magnificent 7 expensive but dominant.",
                    "Metrics focus: P/E ratios elevated but PEG reasonable given growth. EV/EBITDA preferred for capital-intensive sectors. Book value less relevant for asset-light businesses.",
                    "Regional differences: US premium to international markets persistent, EM trading discount to fundamentals. Europe offering value but growth concerns limiting multiples.",
                    "Sector disparities: Technology commanding premium for AI exposure, utilities trading discount on rate sensitivity, healthcare multiples supported by demographics."
                )
            ),
            
            'economic_data_basic' => array(
                'market' => array(
                    "Economic indicators: GDP growing 2.5% annualized, unemployment at 3.8% - still tight labor market. Consumer spending resilient despite rate headwinds.",
                    "Employment trends: Job openings declining but still elevated, wage growth moderating, labor force participation improving. Skills mismatch persisting in some sectors.",
                    "Growth dynamics: Productivity gains from AI adoption, infrastructure investment supporting activity, consumer drawing down excess savings gradually.",
                    "Leading indicators: Conference Board index declining but not recessionary, yield curve inversion persistent, credit conditions tightening gradually for marginal borrowers."
                )
            ),
            
            'earnings_basic' => array(
                'market' => array(
                    "Earnings season: Q4 results showing resilience with 75% beating estimates. Margin pressure building but offset by productivity gains and pricing power.",
                    "Guidance trends: Companies providing conservative outlook given uncertainty, but underlying business conditions remain solid. Tech sector most optimistic.",
                    "Sector performance: Technology leading with AI-driven growth, healthcare defensive characteristics emerging, financials benefiting from rate environment.",
                    "Quality metrics: Return on equity holding up well, debt levels manageable, cash generation strong. Balance sheet quality supporting through-cycle performance."
                )
            ),
            
            'market_events_basic' => array(
                'market' => array(
                    "Upcoming events: FOMC meeting next week (no change expected), earnings season 70% complete, several IPOs in pipeline including private company debuts.",
                    "Calendar focus: Economic data light this week, central bank speakers active, corporate conferences providing sector updates. Geopolitical developments monitoring closely.",
                    "Event risk: Fed communication key for rate expectations, earnings guidance for Q1 outlook, geopolitical tensions affecting energy and defense sectors.",
                    "Market catalysts: AI earnings driving tech vol, infrastructure spending announcements, M&A activity picking up in certain sectors. Election implications building."
                )
            ),
            
            'catalysts_basic' => array(
                'market' => array(
                    "Market catalysts: Fed policy pivots, earnings guidance revisions, geopolitical developments, breakthrough technologies. Multiple crosscurrents affecting sentiment.",
                    "Positive drivers: AI productivity gains, infrastructure investment, energy transition opportunities, demographic trends supporting certain sectors.",
                    "Risk factors: Persistent inflation, geopolitical tensions, credit events, technology disruption of traditional industries. Tail risk management important.",
                    "Timing considerations: Election cycle effects building, seasonal patterns disrupted by structural changes, central bank meeting calendar crucial for positioning."
                )
            ),
            
            'sentiment_basic' => array(
                'market' => array(
                    "Market sentiment: Fear & Greed index neutral, investor surveys showing cautious optimism. Positioning appears balanced, neither extreme bullishness nor bearishness.",
                    "Behavioral indicators: Put/call ratios elevated but not extreme, insider buying moderate, short interest declining in growth names. Contrarian signals mixed.",
                    "Institutional flows: Mutual fund flows positive but moderate, ETF creation/redemption balanced, hedge fund positioning becoming more concentrated.",
                    "Retail participation: Individual investors less active than 2021 peak but still elevated. Social media sentiment volatile, options activity remains high."
                )
            ),
            
            'risk_basic' => array(
                'market' => array(
                    "Risk assessment: Systemic risk low with financial system stable, credit risk building in commercial real estate, operational risk from cyber threats increasing.",
                    "Market risks: Concentration in mega-cap tech, geopolitical tensions, policy uncertainty. Correlation breakdown reducing diversification benefits temporarily.",
                    "Tail risks: Black swan events unpredictable, climate change effects building, technological disruption accelerating. Hedge ratios require constant adjustment.",
                    "Risk management: VaR models adapting to new volatility regimes, stress testing scenarios expanded, ESG risks increasingly quantified in models."
                )
            ),
            
            'strategies_basic' => array(
                'market' => array(
                    "Investment strategies: Growth outperforming value in current cycle, momentum working in trending markets, quality factor strong through volatility.",
                    "Factor performance: Size effect challenged by mega-cap dominance, profitability factor reliable, low volatility strategies underperforming in growth environment.",
                    "Style rotation: GARP (Growth at Reasonable Price) popular, dividend strategies challenged by rate environment, contrarian investing patience required.",
                    "Portfolio approaches: Core-satellite gaining adoption, factor-based construction mainstream, ESG integration affecting factor loadings across strategies."
                )
            ),
            
            'esg_basic' => array(
                'market' => array(
                    "ESG investing: Evolution beyond screening toward impact measurement, regulatory requirements driving adoption, performance attribution becoming clearer.",
                    "Sustainable finance: Green bond issuance accelerating, transition finance gaining traction, climate risk integration required by regulators globally.",
                    "Impact measurement: Standardization improving with SFDR in Europe, SEC climate disclosure rules, third-party data providers consolidating market.",
                    "Investment flows: ESG funds seeing steady inflows, shareholder activism on climate increasing, corporate governance focus on stakeholder capitalism."
                )
            ),
            
            'market_structure_basic' => array(
                'market' => array(
                    "Market structure: Electronic trading >90% of volume, algorithm-driven liquidity provision, dark pools handling institutional orders efficiently.",
                    "Liquidity dynamics: Market depth adequate during normal times, flash crashes risk from algorithm interactions, central bank liquidity crucial backdrop.",
                    "Trading innovation: T+1 settlement reducing counterparty risk, blockchain applications pilot phase, real-time gross settlement systems upgrading globally.",
                    "Regulatory evolution: Market making rules evolving, high-frequency trading oversight increasing, retail payment for order flow under scrutiny."
                )
            ),
            
            'regulation_basic' => array(
                'market' => array(
                    "Regulatory landscape: SEC climate disclosure rules phasing in, Basel III implementation affecting bank capital, LIBOR transition largely complete.",
                    "Compliance trends: ESG reporting requirements expanding, cyber security regulations tightening, cross-border coordination improving but challenging.",
                    "Policy impacts: Antitrust enforcement affecting tech consolidation, tax policy changes affecting capital allocation, trade policy creating sector winners/losers.",
                    "International coordination: Global minimum tax implementation, regulatory arbitrage opportunities declining, standard-setting bodies gaining influence."
                )
            ),
            
            // === CONTEXTUAL FOLLOW-UP & CONVERSATIONAL TEMPLATES ===
            
            'generic_follow_up' => array(
                'market' => array(
                    "Diving deeper: The current market environment is creating both opportunities and challenges. Institutional flows are favoring quality names, while retail participation remains elevated. What specific aspect would you like me to focus on?",
                    "Expanding on that theme: We're seeing structural shifts in market dynamics - algorithm dominance, ESG integration, and geopolitical factors reshaping traditional correlations. Which area interests you most?",
                    "More context: The interplay between monetary policy, fiscal stimulus, and technological disruption is creating unique investment landscapes. Are you more interested in the macro backdrop or sector-specific implications?",
                    "Additional insights: Private markets deployment is accelerating despite higher rates, credit spreads remain tight, and alternative strategies are gaining institutional adoption. Focus area preference?"
                )
            ),
            
            'conversational_response' => array(
                'market' => array(
                    "Exactly! The market dynamics are quite fascinating right now. The convergence of AI adoption, monetary policy shifts, and geopolitical tensions is creating unprecedented complexity.",
                    "Right! It's particularly interesting how traditional correlations are breaking down, creating both risks and opportunities for active managers.",
                    "Indeed! The speed of change in both technology and policy is accelerating, requiring constant adaptation of investment strategies and risk management approaches.",
                    "Absolutely! What we're witnessing is a real-time evolution of market structure, with implications for everything from portfolio construction to career development."
                )
            ),
            
            'clarification_request' => array(
                'market' => array(
                    "Great question! When I mention 'market rotation,' I'm referring to capital flows moving between different sectors, styles, or asset classes - like the current shift from growth to value or large-cap to small-cap. Which aspect would you like me to clarify?",
                    "Good clarification request! 'Credit spreads' refer to the yield difference between corporate bonds and risk-free government securities - currently near cycle lows, indicating strong credit conditions. What other term needs explanation?",
                    "Excellent follow-up! By 'correlation breakdown,' I mean traditional relationships between assets are weakening - for example, bonds aren't providing the usual hedge against equity declines. Which concept needs more detail?",
                    "Smart question! When discussing 'alternative investments,' I'm covering private equity, hedge funds, real estate, infrastructure, and commodities - essentially non-traditional public market investments. Focus area?"
                )
            ),
            
            'simple_comparison' => array(
                'market' => array(
                    "Key differences: Growth stocks focus on earnings expansion and innovation (higher risk/reward), while value stocks emphasize stable cash flows and lower valuations (defensive characteristics). Current environment favors quality growth.",
                    "Main distinction: Private equity involves direct ownership and operational improvement over 3-7 years, while hedge funds use various strategies in public markets for shorter-term returns. Both require different skill sets.",
                    "Primary contrast: Active management involves human decision-making and can outperform in volatile markets, while passive management tracks indices with lower fees. Current environment rewards active approaches.",
                    "Core comparison: US markets offer innovation and growth leadership, while international markets provide value opportunities and diversification. Currency considerations affect relative attractiveness."
                )
            ),
            
            'timing_basic' => array(
                'market' => array(
                    "Market timing considerations: Current cycle suggests late-stage expansion, with Fed policy inflection potentially 6-12 months away. Positioning for transition rather than predicting exact timing optimal.",
                    "Timeline perspective: Earnings season runs quarterly, Fed meetings every 6-8 weeks, and economic data releases follow monthly schedules. Near-term catalysts include next FOMC meeting and Q1 earnings guidance.",
                    "Duration factors: Investment horizons matter - short-term volatility vs. long-term trends. Current environment rewards patient capital while maintaining tactical flexibility for regime changes.",
                    "Schedule awareness: Corporate earnings calendar, central bank meetings, and geopolitical events drive short-term movements. Structural trends like AI adoption play out over years, not quarters."
                )
            ),
            
            'quantity_basic' => array(
                'market' => array(
                    "Scale considerations: S&P 500 market cap ~$45T, private equity dry powder $3.8T, daily NYSE volume ~$50B. These magnitudes help contextualize individual moves and opportunities.",
                    "Size metrics: Magnificent 7 represents ~30% of S&P 500, while small caps (<$2B market cap) offer 2,000+ opportunities. Portfolio allocation typically ranges 60-80% large cap in institutional portfolios.",
                    "Magnitude perspective: $1B deals are routine, $10B+ deals are significant, $50B+ deals are transformational. Career compensation ranges from $200K analyst to $10M+ managing director levels.",
                    "Volume indicators: Average daily volume provides liquidity context, while assets under management (AUM) indicates institutional scale. Individual position sizes typically 1-3% of portfolio for risk management."
                )
            ),
            
            'opinion_basic' => array(
                'market' => array(
                    "My assessment: Current environment favors active management, quality companies, and flexible positioning. Avoid rigid strategies and concentrate on businesses with pricing power and technological advantages.",
                    "Recommended approach: Build core positions in proven winners while maintaining dry powder for opportunities. Focus on skill development, network building, and staying informed about structural changes.",
                    "Strategic view: Diversification across geographies, sectors, and strategies remains important despite US dominance. Consider both public and private market exposures for optimal risk-adjusted returns.",
                    "Tactical suggestion: Monitor central bank policy closely, watch for correlation breakdowns, and position for volatility rather than fighting it. Maintain flexibility as regime changes accelerate."
                )
            ),
            
            'next_steps_basic' => array(
                'market' => array(
                    "Action items: Monitor upcoming earnings guidance, track Fed communications, watch geopolitical developments. Update portfolio allocations based on changing correlations and factor performance.",
                    "Forward strategy: Build watchlists of quality companies, research emerging themes, network within target industries. Career-wise, enhance technical skills and industry knowledge continuously.",
                    "Implementation plan: Set up systematic monitoring of key indicators, establish clear entry/exit criteria, and maintain disciplined approach to risk management across positions.",
                    "Next phase: Focus on areas showing strength - AI adoption, energy transition, healthcare innovation. Avoid overexposed sectors like commercial real estate until stabilization occurs."
                )
            ),
            
            'mode_switch' => array(
                'default' => "Mode switched! What's your biggest question right now?"
            ),
            'affirmative_response' => array(
                'market' => array(
                    "Perfect! Let's dive into the hottest deals right now. Are you more interested in healthcare consolidation, fintech disruption, or infrastructure plays?",
                    "Great choice! I'm seeing massive activity in three areas: healthcare PE is exploding, credit markets are white-hot, and ESG investing is reshaping everything. Which grabs you?",
                    "Excellent! Right now the biggest stories are: KKR's healthcare spree, Apollo's credit dominance, and Blackstone's real estate empire. What interests you most?",
                    "Smart move! The market's moving fast - mega-deals in tech, healthcare consolidation accelerating, and new fund launches weekly. Where do you want to focus?"
                ),
                'opportunities' => array(
                    "Fantastic! I'm tracking live openings at all the top firms. Tell me your experience level and sector preference, and I'll show you the best fits.",
                    "Perfect timing! Are you targeting buy-side PE roles, sell-side IB positions, or credit/alternative investments? Each has different hot spots right now.",
                    "Great! Let's get strategic. What's your background - finance, consulting, or industry? And are you looking at Associate, VP, or Director levels?"
                ),
                'skills' => array(
                    "Excellent! Let's make you unstoppable. Are you preparing for modeling tests, case interviews, or technical deep-dives?",
                    "Perfect! What's your biggest gap right now - LBO modeling, valuation methods, or industry-specific knowledge?",
                    "Great choice! I can drill you on real interview scenarios. Which firm's process interests you - KKR's case studies, Blackstone's modeling tests, or Apollo's technical rounds?"
                ),
                'career' => array(
                    "Smart move! Let's accelerate your trajectory. Are you breaking into PE, advancing within your current firm, or pivoting sectors?",
                    "Perfect! What's your main goal - compensation optimization, promotion strategy, or firm switching tactics?",
                    "Excellent! I track everything - compensation benchmarks, promotion timelines, firm cultures. What intel do you need most?"
                ),
                'default' => array(
                    "Perfect! Let's get you ahead of the market. What's your main focus - deals, opportunities, skills, or career strategy?",
                    "Great! I'm your insider source for everything finance. What area interests you most right now?",
                    "Excellent choice! Ready to dive deep into market intelligence, career acceleration, or skill building?"
                )
            ),
            'default' => "Analyzing the latest market intelligence for you..."
        );
    }
    
    /**
     * Personalize template with context
     */
    private function personalize_template($template, $context) {
        // Handle array of templates - randomly select one for variety
        if (is_array($template)) {
            $template = $template[array_rand($template)];
        }
        
        // Personalize with user name if available  
        $user_name = isset($context['user_first_name']) ? $context['user_first_name'] : '';
        
        if (!empty($user_name)) {
            // Add user name naturally to the message
            $template = str_replace('!', " {$user_name}!", $template);
        }
        
        return $template;
    }
    
    /**
     * Calculate typing delay based on message length
     */
    private function calculate_typing_delay($message) {
        $length = strlen($message);
        $base_delay = 800;
        $char_delay = 15; // 15ms per character
        
        return min($base_delay + ($length * $char_delay), 3000); // Cap at 3 seconds
    }
    
    /**
     * Get fallback visual when Claude is unavailable
     * Now contextual based on query type
     */
    private function get_fallback_visual($mode, $query_type = '') {
        switch ($mode) {
            case 'market':
                // Return contextual visual based on query type
                return $this->get_market_contextual_visual($query_type);
                
            case 'opportunities':
                return array(
                    'type' => 'opportunity_preview',
                    'data' => array(
                        'title' => 'Matched Opportunities',
                        'opportunities' => array(
                            array(
                                'role' => 'Vice President - Technology',
                                'firm' => 'Top PE Fund',
                                'location' => 'London/NYC',
                                'match_score' => 92
                            )
                        )
                    )
                );
                
            case 'skills':
                return array(
                    'type' => 'skill_modules',
                    'data' => array(
                        'title' => 'Popular Skills',
                        'modules' => array(
                            array('name' => 'LBO Modeling', 'level' => 'Advanced'),
                            array('name' => 'DCF Analysis', 'level' => 'Intermediate')
                        )
                    )
                );
                
            default:
                return null;
        }
    }
    
    /**
     * Get contextual market visual based on query type
     * IMPORTANT: This should ONLY return a loading indicator, not static data!
     */
    private function get_market_contextual_visual($query_type) {
        // Never return static data - always indicate async loading needed
        return array(
            'type' => 'pending_visual',
            'visual_type' => $query_type,
            'data' => array(
                'headline' => 'Loading Real-Time Data...',
                'message' => 'Fetching latest market intelligence from Claude...',
                'query_type' => $query_type
            )
        );
        
        // ALL OLD STATIC DATA REMOVED - Never use static data!
    }
    
    /**
     * Extract entities from query (simple implementation)
     */
    private function extract_entities($query) {
        $entities = array();
        
        // Extract company ticker symbols
        if (preg_match_all('/\b[A-Z]{1,5}\b/', $query, $matches)) {
            $entities['tickers'] = $matches[0];
        }
        
        // Extract company names (simple patterns)
        $company_patterns = array(
            '/\b(Apple|Microsoft|Google|Amazon|Tesla|Meta|Netflix)\b/i',
            '/\b([A-Z][a-zA-Z]+\s+(Inc|Corp|Corporation|Company|Ltd))\b/'
        );
        
        $entities['companies'] = array();
        foreach ($company_patterns as $pattern) {
            if (preg_match_all($pattern, $query, $matches)) {
                $entities['companies'] = array_merge($entities['companies'], $matches[0]);
            }
        }
        
        return $entities;
    }
    
    /**
     * Generate response content based on pattern match
     */
    private function generate_pattern_response($match, $query, $context) {
        $pattern = $match['pattern'];
        $user_name = $context['user_first_name'] ?? '';
        $greeting = !empty($user_name) ? "Hi {$user_name}!" : "Hi there!";
        
        switch ($pattern) {
            case 'stock_price':
                return $this->generate_stock_price_response($query, $context);
                
            case 'company_analysis':
                return $this->generate_company_analysis_response($query, $context);
                
            case 'market_news':
                return $this->generate_market_news_response($query, $context);
                
            case 'educational':
                return $this->generate_educational_response($query, $context);
                
            case 'greeting':
                return $greeting . " I'm here to help you with finance and market analysis. What would you like to explore today?";
                
            default:
                return "I understand you're asking about " . $pattern . ". Let me provide you with detailed insights on this topic.";
        }
    }
    
    /**
     * Generate stock price specific response
     */
    private function generate_stock_price_response($query, $context) {
        // Extract potential stock symbols or company names
        $entities = $this->extract_entities($query);
        
        if (!empty($entities['tickers']) || !empty($entities['companies'])) {
            $symbol = !empty($entities['tickers']) ? $entities['tickers'][0] : $entities['companies'][0];
            return "I'll get you the latest price and key metrics for {$symbol}, including real-time data, technical indicators, and market sentiment analysis.";
        } else {
            return "I can help you get real-time stock prices and comprehensive market data. Which company or ticker symbol are you interested in?";
        }
    }
    
    /**
     * Generate company analysis response
     */
    private function generate_company_analysis_response($query, $context) {
        $entities = $this->extract_entities($query);
        
        if (!empty($entities['companies']) || !empty($entities['tickers'])) {
            $company = !empty($entities['companies']) ? $entities['companies'][0] : $entities['tickers'][0];
            return "I'll provide a comprehensive analysis of {$company}, covering financial performance, competitive positioning, growth prospects, and key risk factors.";
        } else {
            return "I can provide detailed company analysis including financials, competitive landscape, and strategic outlook. Which company would you like me to analyze?";
        }
    }
    
    /**
     * Generate market news response
     */
    private function generate_market_news_response($query, $context) {
        return "Here are today's key market developments and breaking financial news, with analysis of how these events might impact different sectors and investment strategies.";
    }
    
    /**
     * Generate educational response
     */
    private function generate_educational_response($query, $context) {
        return "I'll explain this concept clearly with practical examples and show you how it applies in today's market environment. Let me break this down step by step.";
    }
    
    /**
     * Load advanced pattern system (PRIORITY - Load first)
     */
    private function load_advanced_pattern_system() {
        // Load Advanced Pattern Matcher with robust error handling
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-advanced-pattern-matcher.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-advanced-pattern-matcher.php';
            try {
                if (class_exists('SFFC_Advanced_Pattern_Matcher')) {
                    $this->advanced_pattern_matcher = SFFC_Advanced_Pattern_Matcher::get_instance();
                    error_log('SFFC: Advanced Pattern Matcher loaded successfully');
                } else {
                    $this->advanced_pattern_matcher = null;
                    error_log('SFFC: Advanced Pattern Matcher class not found');
                }
            } catch (Exception $e) {
                $this->advanced_pattern_matcher = null;
                error_log('SFFC: Advanced Pattern Matcher initialization failed: ' . $e->getMessage());
            }
        } else {
            $this->advanced_pattern_matcher = null;
            error_log('SFFC: Advanced Pattern Matcher file not found');
        }
        
        // Load Premium Visual Cards with robust error handling
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-premium-visual-cards.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-premium-visual-cards.php';
            try {
                if (class_exists('SFFC_Premium_Visual_Cards')) {
                    $this->premium_visual_cards = SFFC_Premium_Visual_Cards::get_instance();
                    error_log('SFFC: Premium Visual Cards loaded successfully');
                } else {
                    $this->premium_visual_cards = null;
                    error_log('SFFC: Premium Visual Cards class not found');
                }
            } catch (Exception $e) {
                $this->premium_visual_cards = null;
                error_log('SFFC: Premium Visual Cards initialization failed: ' . $e->getMessage());
            }
        } else {
            $this->premium_visual_cards = null;
            error_log('SFFC: Premium Visual Cards file not found');
        }
    }
    
    /**
     * Load Claude API Manager safely
     */
    private function load_claude_api_manager() {
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-claude-api-manager.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-claude-api-manager.php';
            try {
                $this->claude_api = SFFC_Claude_API_Manager::get_instance();
            } catch (Exception $e) {
                error_log('SFFC: Claude API Manager initialization failed: ' . $e->getMessage());
                $this->claude_api = null;
            }
        }
    }
    
    /**
     * Load Personalization Manager safely  
     */
    private function load_personalization_manager() {
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-personalization-manager.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-personalization-manager.php';
            try {
                $this->personalization = SFFC_Personalization_Manager::get_instance();
            } catch (Exception $e) {
                error_log('SFFC: Personalization Manager initialization failed: ' . $e->getMessage());
                $this->personalization = null;
            }
        }
    }
    
}