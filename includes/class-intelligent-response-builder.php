<?php
/**
 * Intelligent Response Builder
 * Uses query analysis to build data-driven, contextual responses
 * 
 * @package SennaCareers
 * @since 10.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Intelligent_Response_Builder {
    
    private static $instance = null;
    private $query_engine;
    private $feed_processor;
    private $context_manager;
    private $template_selector;
    private $visual_cards;
    private $personalization_engine;
    private $data_sources = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->initialize_data_sources();
        
        // Load query engine
        if (!class_exists('SFFC_Intelligent_Query_Engine')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-intelligent-query-engine.php';
        }
        $this->query_engine = SFFC_Intelligent_Query_Engine::get_instance();
        
        // Load XML feed processor for live data
        if (!class_exists('SFFC_XML_Feed_Processor')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-xml-feed-processor.php';
        }
        $this->feed_processor = SFFC_XML_Feed_Processor::get_instance();
        
        // Load conversation context manager
        if (!class_exists('SFFC_Conversation_Context_Manager')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-conversation-context-manager.php';
        }
        $this->context_manager = SFFC_Conversation_Context_Manager::get_instance();
        
        // Load smart template selector
        if (!class_exists('SFFC_Smart_Template_Selector')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-smart-template-selector.php';
        }
        $this->template_selector = SFFC_Smart_Template_Selector::get_instance();
        
        // Load premium visual cards
        if (!class_exists('SFFC_Premium_Visual_Cards')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-premium-visual-cards.php';
        }
        $this->visual_cards = SFFC_Premium_Visual_Cards::get_instance();
        
        // Load personalization engine
        if (!class_exists('SFFC_Personalization_Engine')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-personalization-engine.php';
        }
        $this->personalization_engine = SFFC_Personalization_Engine::get_instance();
    }
    
    /**
     * Initialize data sources
     */
    private function initialize_data_sources() {
        // Mock data sources - in production these would be live APIs
        $this->data_sources = array(
            'stock_prices' => array(
                'BARC.L' => array('price' => 2.15, 'change' => 1.2, 'volume' => '45M', 'last_updated' => time()),
                'GS' => array('price' => 394.50, 'change' => -0.8, 'volume' => '2.1M', 'last_updated' => time()),
                'JPM' => array('price' => 147.20, 'change' => 0.5, 'volume' => '12.3M', 'last_updated' => time()),
                'BX' => array('price' => 89.45, 'change' => 2.1, 'volume' => '8.9M', 'last_updated' => time()),
                'KKR' => array('price' => 72.30, 'change' => 1.8, 'volume' => '4.2M', 'last_updated' => time()),
                'APO' => array('price' => 98.70, 'change' => -1.2, 'volume' => '3.1M', 'last_updated' => time()),
                'MS' => array('price' => 85.60, 'change' => 0.3, 'volume' => '15.2M', 'last_updated' => time())
            ),
            'company_news' => array(
                'barclays' => array(
                    array('title' => 'Barclays Q3 earnings beat estimates with strong trading revenue', 'time' => '2h ago'),
                    array('title' => 'Barclays increases dividend by 8% as capital ratios improve', 'time' => '1 day ago')
                ),
                'kkr' => array(
                    array('title' => 'KKR closes $15B healthcare fund, largest in firm history', 'time' => '4h ago'),
                    array('title' => 'KKR North America Fund XIII raises $19B, exceeds target', 'time' => '2 days ago')
                ),
                'goldman' => array(
                    array('title' => 'Goldman Sachs reports strong Q3 investment banking fees', 'time' => '1h ago'),
                    array('title' => 'Goldman launches $2B sustainability-focused credit fund', 'time' => '6h ago')
                )
            ),
            'pe_explanations' => array(
                'basic' => "Private equity firms like KKR, Blackstone, and Apollo acquire companies using leverage, improve operations over 3-7 years, then sell for profit. They target 20-30% annual returns and manage over $4 trillion globally.",
                'detailed' => "Private equity involves acquiring mature companies using 60-80% debt financing, implementing operational improvements, strategic initiatives, and financial engineering to create value. Leading firms like KKR, Blackstone, and Apollo have generated average net IRRs of 15-25% over the past decade. The industry manages $4.2T in assets with $3.7T in dry powder ready for deployment.",
                'current_context' => "Private equity is experiencing record activity in 2024 with $3.7T in dry powder. Recent mega-deals include KKR's $15B healthcare fund and Blackstone's focus on infrastructure. The industry is adapting to higher interest rates by focusing on operational value creation rather than financial engineering."
            )
        );
    }
    
    /**
     * Main response generation function
     */
    public function generate_response($query, $mode = 'career', $context = array()) {
        // Get session ID for personalization
        $session_id = $context['session_id'] ?? session_id();
        
        // Resolve pronouns using context
        $resolved_query = $this->context_manager->resolve_pronouns($query);
        
        // Analyze the resolved query
        $analysis = $this->query_engine->analyze_query($resolved_query, $context);
        
        // Get context adjustments
        $adjustments = $this->context_manager->get_response_adjustments();
        
        // Get personalization adjustments
        if ($this->personalization_engine) {
            $personalization = $this->personalization_engine->get_personalization_adjustments($session_id, $analysis);
            $adjustments = array_merge($adjustments, $personalization);
        }
        
        // Generate response based on analysis
        $response = null;
        switch ($analysis['response_type']) {
            case 'stock_price_response':
                $response = $this->build_stock_price_response($analysis, $adjustments);
                break;
                
            case 'concept_explanation':
                $response = $this->build_concept_explanation($analysis, $adjustments);
                break;
                
            case 'comparison_response':
                $response = $this->build_comparison_response($analysis, $adjustments);
                break;
                
            case 'analytical_response':
                $response = $this->build_analytical_response($analysis, $adjustments);
                break;
                
            case 'recommendation_response':
                $response = $this->build_recommendation_response($analysis, $adjustments);
                break;
                
            default:
                $response = $this->build_informational_response($analysis, $adjustments);
        }
        
        // Update context with this interaction
        $this->context_manager->update_context($query, $analysis, $response);
        
        // Track interaction for personalization
        if ($this->personalization_engine) {
            $this->personalization_engine->track_interaction($session_id, $query, $analysis, $response);
        }
        
        // Add context reference if needed
        if ($adjustments['reference_previous']) {
            $response = $this->add_context_reference($response, $adjustments);
        }
        
        // Generate visual card if appropriate
        if ($response && isset($response['success']) && $response['success']) {
            $visual_card = $this->generate_visual_card($analysis, $response, $adjustments);
            if ($visual_card) {
                $response['visual_card'] = $visual_card;
            }
        }
        
        // Add proactive insights if available
        if (!empty($adjustments['proactive_insights'])) {
            $response['proactive_insights'] = $adjustments['proactive_insights'];
        }
        
        // Add follow-up suggestions if available
        if (!empty($adjustments['suggested_follow_ups'])) {
            $response['suggested_follow_ups'] = $adjustments['suggested_follow_ups'];
        }
        
        return $response;
    }
    
    /**
     * Build stock price response with live data
     */
    private function build_stock_price_response($analysis, $adjustments = array()) {
        if (empty($analysis['entities']['companies'])) {
            return $this->build_fallback_response($analysis, 'No company specified for price query');
        }
        
        $company = $analysis['entities']['companies'][0];
        $ticker = $company['ticker'];
        $company_name = ucfirst($company['name']);
        
        // Get live price data
        $price_data = $this->get_stock_price($ticker);
        
        if (!$price_data) {
            return $this->build_fallback_response($analysis, "Price data not available for {$company_name}");
        }
        
        // Format response
        $change_direction = $price_data['change'] >= 0 ? 'up' : 'down';
        $change_symbol = $price_data['change'] >= 0 ? '+' : '';
        $currency = $this->get_currency_for_ticker($ticker);
        
        $message = "{$company_name} ({$ticker}) is currently trading at {$currency}" . number_format($price_data['price'], 2) . ", ";
        $message .= "{$change_direction} {$change_symbol}{$price_data['change']}% from yesterday's close. ";
        $message .= "Trading volume is {$price_data['volume']}.";
        
        // Add recent news if available
        $news = $this->get_company_news($company['name']);
        if (!empty($news)) {
            $message .= " Recent news: {$news[0]['title']} ({$news[0]['time']}).";
        }
        
        return array(
            'success' => true,
            'message' => $message,
            'response' => $message,
            'data' => array(
                'ticker' => $ticker,
                'price' => $price_data['price'],
                'change' => $price_data['change'],
                'volume' => $price_data['volume'],
                'currency' => $currency
            ),
            'visual_type' => 'price_chart',
            'confidence' => $analysis['confidence'],
            'source' => 'intelligent_response_builder'
        );
    }
    
    /**
     * Build concept explanation (PE, IB, etc.)
     */
    private function build_concept_explanation($analysis, $adjustments = array()) {
        // Use template selector for proper formatting
        $selected_template = $this->template_selector->select_template($analysis, $adjustments);
        
        // Determine expertise level for explanation depth
        $expertise_level = $adjustments['expertise_level'] ?? 'intermediate';
        $entities = $analysis['entities']['financial_terms'] ?? array();
        
        // Build the explanation based on expertise level
        $message = '';
        
        // Check if this is a PE-related query
        $is_pe_query = false;
        foreach ($entities as $entity) {
            if ($entity['category'] === 'pe_related') {
                $is_pe_query = true;
                break;
            }
        }
        
        // Also check for PE in the query itself
        if (!$is_pe_query && stripos($analysis['original_query'], 'private equity') !== false) {
            $is_pe_query = true;
        }
        
        if ($is_pe_query) {
            // Check if query indicates beginner level
            $query_lower = strtolower($analysis['original_query']);
            $is_beginner_query = (strpos($query_lower, 'what is') !== false || 
                                  strpos($query_lower, 'can you explain') !== false ||
                                  strpos($query_lower, 'to me') !== false);
            
            // Provide comprehensive PE explanation based on expertise level
            if ($expertise_level === 'beginner' || $adjustments['include_definitions'] || $is_beginner_query) {
                $message = "Private equity (PE) is an investment approach where firms raise capital from institutional investors to acquire companies that are not publicly traded. ";
                $message .= "Here's how it works: PE firms like KKR, Blackstone, and Apollo raise funds from pension funds, endowments, and wealthy individuals. ";
                $message .= "They use this capital, combined with borrowed money (leverage), to buy companies, improve their operations, and sell them for a profit after 3-7 years. ";
                $message .= "PE firms typically target returns of 20-30% annually and currently manage over $4 trillion globally. ";
                $message .= "Key players include the General Partners (GPs) who manage the funds and Limited Partners (LPs) who provide the capital.";
            } elseif ($expertise_level === 'expert') {
                $message = "Private equity employs leveraged buyout strategies with typical capital structures of 60-80% debt financing. ";
                $message .= "Leading firms (KKR, Blackstone, Apollo, Carlyle) have generated average net IRRs of 15-25% over the past decade through operational improvements, multiple expansion, and financial engineering. ";
                $message .= "The industry manages $4.2T AUM with $3.7T in dry powder. Current environment: higher rates (SOFR 5.3%) are pressuring leverage ratios and exit multiples, ";
                $message .= "shifting focus from financial engineering to operational value creation. Fundraising reached $1.2T in 2023 despite LP liquidity constraints. ";
                $message .= "Key trends: sector specialization, longer hold periods (5-7 years), add-on acquisitions, and ESG integration.";
            } else {
                // Intermediate level
                $message = $this->data_sources['pe_explanations']['detailed'];
            }
            
            // Add current context if requested
            if (stripos($analysis['original_query'], 'current') !== false || 
                stripos($analysis['original_query'], 'now') !== false ||
                stripos($analysis['original_query'], 'today') !== false) {
                $message .= " " . $this->data_sources['pe_explanations']['current_context'];
            }
        } else {
            // Handle other financial concepts
            $message = $this->build_general_explanation($analysis, $adjustments);
        }
        
        // Fill template if selected
        if ($selected_template) {
            $template_data = array(
                'term' => 'Private Equity',
                'definition' => $message,
                'industry' => 'finance',
                'explanation' => $message,
                'example' => 'KKR\'s acquisition of RJR Nabisco for $25B in 1989 remains the most famous PE deal',
                'importance' => 'PE drives significant economic activity, employing millions and managing trillions in assets'
            );
            
            $filled = $this->template_selector->fill_template($selected_template, $template_data);
            if ($filled && isset($filled['visual'])) {
                $visual_type = $filled['visual'];
            }
        }
        
        return array(
            'success' => true,
            'message' => $message,
            'response' => $message,
            'visual_type' => $visual_type ?? 'pe_infographic',
            'confidence' => max($analysis['confidence'], 95), // High confidence for educational content
            'source' => 'intelligent_response_builder',
            'template_used' => $selected_template ? 'educational' : 'direct'
        );
    }
    
    /**
     * Build general explanation for non-PE concepts
     */
    private function build_general_explanation($analysis, $adjustments) {
        $query_lower = strtolower($analysis['original_query']);
        
        if (stripos($query_lower, 'investment banking') !== false) {
            return "Investment banking involves advising companies on mergers & acquisitions, raising capital through IPOs and debt offerings, and providing market-making services. Major players include Goldman Sachs, Morgan Stanley, and JPMorgan.";
        } elseif (stripos($query_lower, 'hedge fund') !== false) {
            return "If your end goal is private equity, I would not center your search on hedge fund paths. I would instead focus on whether the role builds the signals that matter for private equity hiring: transaction judgment, commercial diligence, financial modeling, and clear investment thinking.";
        } elseif (stripos($query_lower, 'venture capital') !== false) {
            return "Venture capital provides funding to early-stage startups with high growth potential in exchange for equity. VC firms like Sequoia and Andreessen Horowitz have backed companies like Google, Facebook, and Airbnb from inception.";
        } else {
            return "I can explain private equity, investment banking, asset management, and broader finance markets across the Middle East. What specific aspect would you like to understand?";
        }
    }
    
    /**
     * Build comparison response
     */
    private function build_comparison_response($analysis, $adjustments = array()) {
        $companies = $analysis['entities']['companies'];
        
        if (count($companies) < 2) {
            return $this->build_fallback_response($analysis, 'Need at least two companies to compare');
        }
        
        $company1 = $companies[0];
        $company2 = $companies[1];
        
        $price1 = $this->get_stock_price($company1['ticker']);
        $price2 = $this->get_stock_price($company2['ticker']);
        
        $message = "Comparing {$company1['name']} vs {$company2['name']}: ";
        
        if ($price1 && $price2) {
            $message .= "{$company1['name']} is trading at {$this->get_currency_for_ticker($company1['ticker'])}" . number_format($price1['price'], 2) . " ";
            $message .= "(" . sprintf("%+.1f", $price1['change']) . "%) while {$company2['name']} is at ";
            $message .= "{$this->get_currency_for_ticker($company2['ticker'])}" . number_format($price2['price'], 2) . " (" . sprintf("%+.1f", $price2['change']) . "%). ";
            
            // Determine which is performing better
            if ($price1['change'] > $price2['change']) {
                $message .= "{$company1['name']} is outperforming today.";
            } elseif ($price2['change'] > $price1['change']) {
                $message .= "{$company2['name']} is outperforming today.";
            } else {
                $message .= "Both companies are performing similarly today.";
            }
        } else {
            $message .= "Both are major players in the financial sector with different specializations and market focuses.";
        }
        
        return array(
            'success' => true,
            'message' => $message,
            'response' => $message,
            'visual_type' => 'comparison_table',
            'data' => array(
                'companies' => $companies,
                'price1' => $price1,
                'price2' => $price2
            ),
            'confidence' => $analysis['confidence'],
            'source' => 'intelligent_response_builder'
        );
    }
    
    /**
     * Build analytical response
     */
    private function build_analytical_response($analysis, $adjustments = array()) {
        $companies = $analysis['entities']['companies'];
        
        if (empty($companies)) {
            return $this->build_fallback_response($analysis, 'Analysis requires a specific company or topic');
        }
        
        $company = $companies[0];
        $price_data = $this->get_stock_price($company['ticker']);
        $news = $this->get_company_news($company['name']);
        
        $message = "Analysis of {$company['name']}: ";
        
        if ($price_data) {
            $performance = $price_data['change'] > 2 ? 'strong' : ($price_data['change'] > 0 ? 'positive' : 'negative');
            $message .= "Stock shows {$performance} performance today with " . sprintf("%+.1f", $price_data['change']) . "% movement. ";
            
            if ($price_data['change'] > 0) {
                $message .= "The upward momentum suggests investor confidence. ";
            } else {
                $message .= "The decline may present buying opportunities for long-term investors. ";
            }
        }
        
        if (!empty($news)) {
            $message .= "Recent developments include: {$news[0]['title']}. ";
        }
        
        $message .= "Would you like me to dive deeper into specific fundamentals or recent developments?";
        
        return array(
            'success' => true,
            'message' => $message,
            'response' => $message,
            'visual_type' => 'analysis_dashboard',
            'confidence' => $analysis['confidence'],
            'source' => 'intelligent_response_builder'
        );
    }
    
    /**
     * Build recommendation response
     */
    private function build_recommendation_response($analysis, $adjustments = array()) {
        // This is a placeholder - recommendations require careful compliance considerations
        $message = "I can provide educational information and analysis to help inform your decisions, but I don't provide specific investment recommendations. ";
        $message .= "Would you like me to explain the factors you should consider when evaluating this investment?";
        
        return array(
            'success' => true,
            'message' => $message,
            'response' => $message,
            'confidence' => $analysis['confidence'],
            'source' => 'intelligent_response_builder'
        );
    }
    
    /**
     * Build general informational response
     */
    private function build_informational_response($analysis, $adjustments = array()) {
        $message = "I understand you're interested in finance-related topics. ";
        
        if (!empty($analysis['entities']['companies'])) {
            $company_names = array_column($analysis['entities']['companies'], 'name');
            $message .= "You mentioned " . implode(' and ', $company_names) . ". ";
            $message .= "I can provide stock prices, recent news, analysis, or explanations. What specific information would you like?";
        } else {
            $message .= "I can help with stock prices, market analysis, private equity insights, career guidance, and financial concepts. ";
            $message .= "What specific area interests you?";
        }
        
        return array(
            'success' => true,
            'message' => $message,
            'response' => $message,
            'confidence' => $analysis['confidence'],
            'source' => 'intelligent_response_builder'
        );
    }
    
    /**
     * Build fallback response when something goes wrong
     */
    private function build_fallback_response($analysis, $reason = '') {
        $message = "I understand your question";
        
        if (!empty($analysis['entities']['companies'])) {
            $company = $analysis['entities']['companies'][0]['name'];
            $message .= " about {$company}";
        }
        
        $message .= ". Let me help you with that. Could you provide a bit more detail about what specific information you're looking for?";
        
        return array(
            'success' => true,
            'message' => $message,
            'response' => $message,
            'confidence' => max($analysis['confidence'], 50), // Ensure fallback has decent confidence
            'source' => 'intelligent_response_builder_fallback',
            'debug_reason' => $reason
        );
    }
    
    /**
     * Get stock price data
     */
    private function get_stock_price($ticker) {
        // Try live feed first
        if ($this->feed_processor) {
            $live_price = $this->feed_processor->get_stock_price_from_feeds($ticker);
            if ($live_price) {
                return array(
                    'price' => $live_price['price'],
                    'change' => $this->data_sources['stock_prices'][$ticker]['change'] ?? 0,
                    'volume' => $this->data_sources['stock_prices'][$ticker]['volume'] ?? 'N/A',
                    'last_updated' => $live_price['timestamp'],
                    'source' => 'live_feed'
                );
            }
        }
        
        // Fall back to mock data
        return isset($this->data_sources['stock_prices'][$ticker]) 
            ? $this->data_sources['stock_prices'][$ticker] 
            : null;
    }
    
    /**
     * Get company news
     */
    private function get_company_news($company_name) {
        // Try live feed first
        if ($this->feed_processor) {
            $live_news = $this->feed_processor->get_company_news_from_feeds($company_name);
            if (!empty($live_news)) {
                return $live_news;
            }
        }
        
        // Fall back to mock data
        return isset($this->data_sources['company_news'][$company_name]) 
            ? $this->data_sources['company_news'][$company_name] 
            : array();
    }
    
    /**
     * Get currency symbol for ticker
     */
    private function get_currency_for_ticker($ticker) {
        // UK stocks
        if (strpos($ticker, '.L') !== false) {
            return '£';
        }
        // Default to USD
        return '$';
    }
    
    /**
     * Determine visual type for explanations
     */
    private function determine_explanation_visual($entities) {
        foreach ($entities as $entity) {
            if ($entity['category'] === 'pe_related') {
                return 'pe_infographic';
            }
        }
        return null;
    }
    
    /**
     * Add context reference to response
     */
    private function add_context_reference($response, $adjustments) {
        if (!empty($adjustments['current_company'])) {
            // Add subtle reference to continuing discussion
            $prefix = "Continuing our discussion about {$adjustments['current_company']}, ";
            if (isset($response['message'])) {
                $response['message'] = $prefix . lcfirst($response['message']);
            }
        }
        return $response;
    }
    
    /**
     * Generate visual card for response
     */
    private function generate_visual_card($analysis, $response, $context) {
        // Get selected template for context
        $template = $this->template_selector->select_template($analysis, $context);
        
        // Select appropriate card(s)
        $selected_cards = $this->visual_cards->select_card($analysis, $template, $context);
        
        if (empty($selected_cards)) {
            return null;
        }
        
        // Use the best matching card
        $best_card = $selected_cards[0];
        
        // Prepare data for the card
        $card_data = $this->prepare_card_data($analysis, $response, $context);
        
        // Render the card
        $rendered_card = $this->visual_cards->render_card(
            $best_card['card_id'],
            $card_data,
            array('theme' => $this->determine_theme($context))
        );
        
        return $rendered_card;
    }
    
    /**
     * Prepare data for visual card
     */
    private function prepare_card_data($analysis, $response, $context) {
        $data = array();
        
        // Extract data from response
        if (isset($response['data'])) {
            $data = array_merge($data, $response['data']);
        }
        
        // Add entity data
        if (!empty($analysis['entities']['companies'])) {
            $company = $analysis['entities']['companies'][0];
            $data['company'] = $company['name'];
            $data['ticker'] = $company['ticker'] ?? '';
            $data['company_name'] = ucfirst($company['name']);
        }
        
        // Add message content
        if (isset($response['message'])) {
            $data['headline'] = $this->extract_headline($response['message']);
            $data['body_content'] = $response['message'];
            $data['lead_paragraph'] = $this->extract_lead($response['message']);
        }
        
        // Add context data
        $data['expertise_level'] = $context['expertise_level'] ?? 'intermediate';
        $data['conversation_mode'] = $context['conversation_mode'] ?? 'general';
        
        // Add live data if available
        if ($this->feed_processor) {
            $live_data = $this->feed_processor->get_live_data(
                $analysis['response_type'] ?? 'general',
                $analysis['entities'] ?? array()
            );
            
            if (!empty($live_data['headlines'])) {
                $data['related_news'] = $live_data['headlines'];
            }
            
            if (!empty($live_data['data_points'])) {
                $data['market_data'] = $live_data['data_points'];
            }
        }
        
        return $data;
    }
    
    /**
     * Extract headline from message
     */
    private function extract_headline($message) {
        // Take first sentence or first 100 chars
        $sentences = explode('.', $message);
        if (!empty($sentences[0])) {
            return trim($sentences[0]);
        }
        return substr($message, 0, 100) . '...';
    }
    
    /**
     * Extract lead paragraph
     */
    private function extract_lead($message) {
        // Take first 2-3 sentences
        $sentences = explode('.', $message);
        $lead = '';
        for ($i = 0; $i < min(3, count($sentences)); $i++) {
            if (!empty(trim($sentences[$i]))) {
                $lead .= trim($sentences[$i]) . '. ';
            }
        }
        return trim($lead);
    }
    
    /**
     * Determine theme based on context
     */
    private function determine_theme($context) {
        if (isset($context['expertise_level'])) {
            if ($context['expertise_level'] === 'expert') {
                return 'dark';
            }
            if ($context['expertise_level'] === 'beginner') {
                return 'light';
            }
        }
        return 'premium';
    }
}
