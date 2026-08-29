<?php
/**
 * Premium Visual Cards Library - Phase 6 Enhanced
 * Comprehensive magazine-style cards with deep integration to pattern recognition and templates
 * 
 * @package SennaCareers
 * @since 6.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Premium_Visual_Cards {
    
    private static $instance = null;
    private $card_registry = array();
    private $pattern_matcher = null;
    private $template_selector = null;
    private $response_builder = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->initialize_card_registry();
        // Dependencies will be loaded lazily when needed
    }
    
    /**
     * Load pattern recognition and template systems
     */
    private function load_dependencies() {
        // Load pattern recognition engine lazily
        if (!$this->pattern_matcher && class_exists('SFFC_Intelligent_Query_Engine')) {
            $this->pattern_matcher = SFFC_Intelligent_Query_Engine::get_instance();
        }
        
        // Load template selector lazily
        if (!$this->template_selector && class_exists('SFFC_Smart_Template_Selector')) {
            $this->template_selector = SFFC_Smart_Template_Selector::get_instance();
        }
        
        // Don't load response builder here to avoid circular dependency
    }
    
    /**
     * Initialize comprehensive card registry
     */
    private function initialize_card_registry() {
        $this->card_registry = array(
            
            // Magazine-Style Editorial Cards
            'markets_daily_card' => array(
                'type' => 'magazine',
                'layout' => 'editorial',
                'pattern_triggers' => array('market_news', 'breaking_news', 'analysis', 'greeting', 'hello', 'hi'),
                'template_compatibility' => array('news_summary', 'market_data', 'greeting_message'),
                'structure' => array(
                    'masthead' => array(
                        'logo' => 'MARKETS DAILY',
                        'date' => 'dynamic',
                        'edition' => 'Premium Edition'
                    ),
                    'headline' => array(
                        'main' => '{headline}',
                        'subtitle' => '{subtitle}',
                        'byline' => 'Analysis by AI Intelligence'
                    ),
                    'content' => array(
                        'lead' => '{lead_paragraph}',
                        'body' => '{body_content}',
                        'pullquote' => '{key_insight}'
                    ),
                    'sidebar' => array(
                        'related_stocks' => '{ticker_list}',
                        'market_impact' => '{impact_score}',
                        'read_time' => '{estimated_time}'
                    )
                )
            ),
            
            'global_investor_card' => array(
                'type' => 'magazine',
                'layout' => 'wealth_profile',
                'pattern_triggers' => array('company_profile', 'executive_info', 'wealth_data', 'company'),
                'template_compatibility' => array('company_snapshot', 'analytical_response'),
                'structure' => array(
                    'header' => array(
                        'publication' => 'GLOBAL INVESTOR',
                        'category' => 'Company Profile'
                    ),
                    'profile_grid' => array(
                        'company_name' => '{company}',
                        'market_cap' => '{market_cap}',
                        'ceo' => '{executive}',
                        'founded' => '{year}',
                        'headquarters' => '{location}',
                        'employees' => '{employee_count}'
                    ),
                    'performance_metrics' => array(
                        'revenue' => '{annual_revenue}',
                        'profit' => '{net_income}',
                        'growth' => '{yoy_growth}',
                        'stock_performance' => '{ytd_return}'
                    ),
                    'visual_elements' => array(
                        'performance_chart' => 'mini_sparkline',
                        'sector_comparison' => 'peer_benchmark',
                        'analyst_rating' => 'star_rating'
                    )
                )
            ),
            
            'capital_insights_card' => array(
                'type' => 'magazine',
                'layout' => 'analytical',
                'pattern_triggers' => array('deep_analysis', 'market_trends', 'economic_data', 'what_is'),
                'template_compatibility' => array('analytical_response', 'educational'),
                'structure' => array(
                    'header' => array(
                        'series' => 'CAPITAL INSIGHTS',
                        'issue' => 'Intelligence Report'
                    ),
                    'thesis' => array(
                        'statement' => '{main_argument}',
                        'supporting_data' => '{key_statistics}'
                    ),
                    'analysis_sections' => array(
                        'market_context' => '{context}',
                        'data_points' => '{evidence}',
                        'implications' => '{forward_looking}',
                        'risks' => '{risk_factors}'
                    ),
                    'infographic' => array(
                        'key_chart' => 'data_visualization',
                        'comparison_table' => 'peer_analysis',
                        'timeline' => 'historical_context'
                    )
                )
            ),
            
            // Interactive Option Cards
            'strategy_choice_card' => array(
                'type' => 'interactive',
                'layout' => 'choice_matrix',
                'pattern_triggers' => array('decision_request', 'strategy_question', 'what_should'),
                'template_compatibility' => array('recommendation_response', 'advisory'),
                'structure' => array(
                    'question_header' => '{user_query}',
                    'options' => array(
                        'option_a' => array(
                            'title' => 'Conservative Approach',
                            'icon' => 'shield-check',
                            'description' => '{conservative_desc}',
                            'pros' => array('{pro1}', '{pro2}'),
                            'cons' => array('{con1}', '{con2}'),
                            'action' => 'explore_conservative'
                        ),
                        'option_b' => array(
                            'title' => 'Balanced Strategy',
                            'icon' => 'balance-scale',
                            'description' => '{balanced_desc}',
                            'pros' => array('{pro1}', '{pro2}'),
                            'cons' => array('{con1}', '{con2}'),
                            'action' => 'explore_balanced'
                        ),
                        'option_c' => array(
                            'title' => 'Aggressive Growth',
                            'icon' => 'rocket',
                            'description' => '{aggressive_desc}',
                            'pros' => array('{pro1}', '{pro2}'),
                            'cons' => array('{con1}', '{con2}'),
                            'action' => 'explore_aggressive'
                        )
                    ),
                    'comparison_metrics' => array(
                        'risk_level' => 'risk_meter',
                        'potential_return' => 'return_scale',
                        'time_horizon' => 'timeline_indicator'
                    )
                )
            ),
            
            'learning_path_card' => array(
                'type' => 'interactive',
                'layout' => 'journey_selector',
                'pattern_triggers' => array('learn_about', 'teach_me', 'understand'),
                'template_compatibility' => array('educational', 'career_guidance'),
                'structure' => array(
                    'path_header' => 'Choose Your Learning Path',
                    'paths' => array(
                        'beginner' => array(
                            'title' => 'Fundamentals First',
                            'icon' => 'graduation-cap',
                            'modules' => array('{module1}', '{module2}', '{module3}'),
                            'duration' => '15 min',
                            'action' => 'start_beginner'
                        ),
                        'intermediate' => array(
                            'title' => 'Deep Dive Analysis',
                            'icon' => 'microscope',
                            'modules' => array('{module1}', '{module2}', '{module3}'),
                            'duration' => '30 min',
                            'action' => 'start_intermediate'
                        ),
                        'advanced' => array(
                            'title' => 'Expert Strategies',
                            'icon' => 'chess-king',
                            'modules' => array('{module1}', '{module2}', '{module3}'),
                            'duration' => '45 min',
                            'action' => 'start_advanced'
                        )
                    ),
                    'progress_tracker' => 'visual_progress_bar'
                )
            ),
            
            'action_menu_card' => array(
                'type' => 'interactive',
                'layout' => 'action_grid',
                'pattern_triggers' => array('what_can', 'help_me', 'options'),
                'template_compatibility' => array('clarification', 'general'),
                'structure' => array(
                    'menu_title' => 'What would you like to explore?',
                    'actions' => array(
                        array(
                            'icon' => 'chart-line',
                            'label' => 'Market Analysis',
                            'description' => 'Real-time market data and trends',
                            'action' => 'analyze_markets'
                        ),
                        array(
                            'icon' => 'building',
                            'label' => 'Company Research',
                            'description' => 'Deep dive into specific companies',
                            'action' => 'research_company'
                        ),
                        array(
                            'icon' => 'briefcase',
                            'label' => 'Career Insights',
                            'description' => 'PE and IB career guidance',
                            'action' => 'career_advice'
                        ),
                        array(
                            'icon' => 'book-open',
                            'label' => 'Learn Concepts',
                            'description' => 'Financial concepts explained',
                            'action' => 'education_mode'
                        )
                    )
                )
            ),
            
            // Premium Data Cards
            'bloomberg_terminal_card' => array(
                'type' => 'data',
                'layout' => 'terminal_view',
                'pattern_triggers' => array('stock_price', 'market_data', 'live_price', 'price', 'quote'),
                'template_compatibility' => array('stock_price_live', 'market_data'),
                'structure' => array(
                    'terminal_header' => array(
                        'ticker' => '{TICKER}',
                        'exchange' => '{EXCHANGE}',
                        'timestamp' => '{LIVE_TIME}'
                    ),
                    'price_panel' => array(
                        'last' => '{current_price}',
                        'change' => '{change_amount}',
                        'change_pct' => '{change_percent}',
                        'volume' => '{volume}',
                        'avg_volume' => '{avg_volume}'
                    ),
                    'market_depth' => array(
                        'bid' => '{bid_price}',
                        'ask' => '{ask_price}',
                        'spread' => '{bid_ask_spread}'
                    ),
                    'key_stats' => array(
                        'open' => '{open_price}',
                        'high' => '{day_high}',
                        'low' => '{day_low}',
                        'prev_close' => '{previous_close}',
                        '52w_high' => '{year_high}',
                        '52w_low' => '{year_low}'
                    ),
                    'mini_chart' => 'intraday_sparkline'
                )
            ),
            
            'pe_deal_card' => array(
                'type' => 'data',
                'layout' => 'deal_announcement',
                'pattern_triggers' => array('pe_deals', 'acquisitions', 'buyout'),
                'template_compatibility' => array('pe_fund_performance', 'news_summary'),
                'structure' => array(
                    'deal_header' => array(
                        'type' => 'PRIVATE EQUITY TRANSACTION',
                        'status' => '{deal_status}'
                    ),
                    'deal_parties' => array(
                        'acquirer' => array(
                            'name' => '{pe_firm}',
                            'logo' => 'firm_logo',
                            'fund' => '{fund_name}'
                        ),
                        'target' => array(
                            'name' => '{target_company}',
                            'sector' => '{industry}',
                            'revenue' => '{target_revenue}'
                        )
                    ),
                    'deal_metrics' => array(
                        'value' => '{transaction_value}',
                        'multiple' => '{ebitda_multiple}',
                        'leverage' => '{debt_ratio}',
                        'equity' => '{equity_contribution}'
                    ),
                    'timeline' => array(
                        'announced' => '{announcement_date}',
                        'expected_close' => '{closing_date}'
                    )
                )
            ),
            
            'market_heatmap_card' => array(
                'type' => 'data',
                'layout' => 'sector_heatmap',
                'pattern_triggers' => array('market_overview', 'sector_performance', 'market_movers'),
                'template_compatibility' => array('market_data', 'analysis_dashboard'),
                'structure' => array(
                    'heatmap_title' => 'Market Sector Performance',
                    'sectors' => array(
                        'technology' => array('value' => '{tech_change}', 'volume' => '{tech_vol}'),
                        'finance' => array('value' => '{fin_change}', 'volume' => '{fin_vol}'),
                        'healthcare' => array('value' => '{health_change}', 'volume' => '{health_vol}'),
                        'energy' => array('value' => '{energy_change}', 'volume' => '{energy_vol}'),
                        'consumer' => array('value' => '{consumer_change}', 'volume' => '{consumer_vol}'),
                        'industrial' => array('value' => '{industrial_change}', 'volume' => '{industrial_vol}')
                    ),
                    'legend' => array(
                        'strong_buy' => '+3%',
                        'buy' => '+1%',
                        'neutral' => '0%',
                        'sell' => '-1%',
                        'strong_sell' => '-3%'
                    ),
                    'top_movers' => array(
                        'gainers' => array('{gainer1}', '{gainer2}', '{gainer3}'),
                        'losers' => array('{loser1}', '{loser2}', '{loser3}')
                    )
                )
            ),
            
            'earnings_calendar_card' => array(
                'type' => 'data',
                'layout' => 'calendar_view',
                'pattern_triggers' => array('earnings', 'earnings_date', 'results'),
                'template_compatibility' => array('earnings_report', 'company_news'),
                'structure' => array(
                    'calendar_header' => 'Upcoming Earnings',
                    'earnings_events' => array(
                        'today' => array(
                            'companies' => array('{company1}', '{company2}'),
                            'highlight' => '{major_company}'
                        ),
                        'this_week' => array(
                            'monday' => array('{mon_companies}'),
                            'tuesday' => array('{tue_companies}'),
                            'wednesday' => array('{wed_companies}'),
                            'thursday' => array('{thu_companies}'),
                            'friday' => array('{fri_companies}')
                        )
                    ),
                    'earnings_preview' => array(
                        'company' => '{preview_company}',
                        'expected_eps' => '{consensus_eps}',
                        'expected_revenue' => '{consensus_revenue}',
                        'whisper_number' => '{whisper_eps}'
                    )
                )
            ),
            
            // Premium Magazine Layout Cards
            'business_chronicle_card' => array(
                'type' => 'magazine',
                'layout' => 'newspaper_front',
                'pattern_triggers' => array('news', 'today', 'headlines', 'latest'),
                'template_compatibility' => array('news_summary', 'market_data'),
                'structure' => array(
                    'masthead' => array(
                        'publication' => 'BUSINESS CHRONICLE',
                        'date' => '{current_date}',
                        'edition' => 'Digital Edition',
                        'weather' => 'Market Climate: {market_sentiment}'
                    ),
                    'above_fold' => array(
                        'main_story' => array(
                            'headline' => '{main_headline}',
                            'subhead' => '{main_subhead}',
                            'lead' => '{main_lead}',
                            'image' => 'feature_chart',
                            'continued' => 'page_A6'
                        ),
                        'secondary_story' => array(
                            'headline' => '{secondary_headline}',
                            'summary' => '{secondary_summary}'
                        )
                    ),
                    'columns' => array(
                        'left' => array(
                            'section' => 'Markets',
                            'stories' => array('{market_story1}', '{market_story2}')
                        ),
                        'center' => array(
                            'section' => 'Companies',
                            'stories' => array('{company_story1}', '{company_story2}')
                        ),
                        'right' => array(
                            'section' => 'Analysis',
                            'stories' => array('{analysis_story1}', '{analysis_story2}')
                        )
                    ),
                    'market_snapshot' => array(
                        'dow' => '{dow_change}',
                        'sp500' => '{sp_change}',
                        'nasdaq' => '{nasdaq_change}',
                        'gold' => '{gold_price}',
                        'oil' => '{oil_price}'
                    )
                )
            ),
            
            'executive_digest_card' => array(
                'type' => 'magazine',
                'layout' => 'feature_spread',
                'pattern_triggers' => array('deep_dive', 'analysis', 'profile', 'explain'),
                'template_compatibility' => array('analytical_response', 'company_snapshot'),
                'structure' => array(
                    'spread_header' => array(
                        'category' => 'EXECUTIVE DIGEST',
                        'reading_time' => '{time} min read'
                    ),
                    'hero_section' => array(
                        'title' => '{feature_title}',
                        'deck' => '{feature_deck}',
                        'author' => 'AI Research Team',
                        'hero_image' => 'data_visualization'
                    ),
                    'article_body' => array(
                        'introduction' => '{intro_text}',
                        'key_points' => array(
                            'point1' => array('icon' => 'trending-up', 'text' => '{point1}'),
                            'point2' => array('icon' => 'target', 'text' => '{point2}'),
                            'point3' => array('icon' => 'award', 'text' => '{point3}')
                        ),
                        'data_callouts' => array(
                            'stat1' => array('number' => '{value1}', 'label' => '{label1}'),
                            'stat2' => array('number' => '{value2}', 'label' => '{label2}'),
                            'stat3' => array('number' => '{value3}', 'label' => '{label3}')
                        )
                    ),
                    'sidebar' => array(
                        'quick_facts' => array('{fact1}', '{fact2}', '{fact3}'),
                        'related_companies' => array('{company1}', '{company2}'),
                        'expert_take' => '{expert_quote}'
                    )
                )
            ),
            
            'equity_insights_card' => array(
                'type' => 'magazine',
                'layout' => 'stock_picks',
                'pattern_triggers' => array('recommendations', 'best_stocks', 'opportunities', 'suggest'),
                'template_compatibility' => array('recommendation_response', 'market_data'),
                'structure' => array(
                    'picks_header' => array(
                        'title' => "EQUITY SELECTIONS",
                        'subtitle' => 'Data-Driven Opportunities'
                    ),
                    'featured_pick' => array(
                        'company' => '{featured_company}',
                        'ticker' => '{featured_ticker}',
                        'thesis' => '{investment_thesis}',
                        'target_price' => '{price_target}',
                        'upside' => '{upside_potential}',
                        'risk_rating' => 'risk_meter'
                    ),
                    'additional_picks' => array(
                        'pick1' => array(
                            'company' => '{company1}',
                            'ticker' => '{ticker1}',
                            'rating' => 'star_rating',
                            'one_liner' => '{thesis1}'
                        ),
                        'pick2' => array(
                            'company' => '{company2}',
                            'ticker' => '{ticker2}',
                            'rating' => 'star_rating',
                            'one_liner' => '{thesis2}'
                        )
                    ),
                    'market_context' => array(
                        'sector_outlook' => '{sector_analysis}',
                        'risk_factors' => array('{risk1}', '{risk2}')
                    )
                )
            ),
            
            // Specialized Cards
            'career_roadmap_card' => array(
                'type' => 'interactive',
                'layout' => 'career_timeline',
                'pattern_triggers' => array('career', 'job', 'path', 'become'),
                'template_compatibility' => array('career_guidance'),
                'structure' => array(
                    'roadmap_title' => 'Your Path to {target_role}',
                    'timeline' => array(
                        'stage1' => array(
                            'title' => 'Foundation',
                            'duration' => '0-2 years',
                            'milestones' => array('{milestone1}', '{milestone2}'),
                            'skills' => array('{skill1}', '{skill2}'),
                            'icon' => 'foundation'
                        ),
                        'stage2' => array(
                            'title' => 'Growth',
                            'duration' => '2-5 years',
                            'milestones' => array('{milestone3}', '{milestone4}'),
                            'skills' => array('{skill3}', '{skill4}'),
                            'icon' => 'growth'
                        ),
                        'stage3' => array(
                            'title' => 'Leadership',
                            'duration' => '5+ years',
                            'milestones' => array('{milestone5}', '{milestone6}'),
                            'skills' => array('{skill5}', '{skill6}'),
                            'icon' => 'leadership'
                        )
                    ),
                    'action_items' => array(
                        'immediate' => array('{action1}', '{action2}'),
                        'short_term' => array('{action3}', '{action4}'),
                        'long_term' => array('{action5}', '{action6}')
                    )
                )
            ),
            
            'comparison_matrix_card' => array(
                'type' => 'data',
                'layout' => 'comparison_grid',
                'pattern_triggers' => array('compare', 'versus', 'difference'),
                'template_compatibility' => array('market_comparison', 'comparison_response'),
                'structure' => array(
                    'comparison_title' => 'Head-to-Head Analysis',
                    'entities' => array(
                        'entity1' => '{company1}',
                        'entity2' => '{company2}'
                    ),
                    'metrics' => array(
                        'market_cap' => array('label' => 'Market Cap', 'entity1' => '{cap1}', 'entity2' => '{cap2}'),
                        'revenue' => array('label' => 'Revenue', 'entity1' => '{rev1}', 'entity2' => '{rev2}'),
                        'profit_margin' => array('label' => 'Margin', 'entity1' => '{margin1}', 'entity2' => '{margin2}'),
                        'pe_ratio' => array('label' => 'P/E', 'entity1' => '{pe1}', 'entity2' => '{pe2}'),
                        'dividend' => array('label' => 'Dividend', 'entity1' => '{div1}', 'entity2' => '{div2}'),
                        'analyst_rating' => array('label' => 'Rating', 'entity1' => '{rating1}', 'entity2' => '{rating2}')
                    ),
                    'winner_highlights' => array(
                        'best_growth' => '{growth_winner}',
                        'best_value' => '{value_winner}',
                        'best_momentum' => '{momentum_winner}'
                    ),
                    'visual_comparison' => 'radar_chart'
                )
            )
        );
    }
    
    /**
     * Select appropriate card based on query analysis and template
     */
    public function select_card($analysis, $template, $context = array()) {
        // Load dependencies if needed
        $this->load_dependencies();
        
        $selected_cards = array();
        
        // Get pattern matches from query analysis
        $patterns = $this->extract_patterns($analysis);
        
        // Get template type
        $template_type = $this->get_template_type($template);
        
        // Score each card based on pattern match and template compatibility
        foreach ($this->card_registry as $card_id => $card) {
            $score = 0;
            
            // Check pattern triggers
            foreach ($card['pattern_triggers'] as $trigger) {
                if (in_array($trigger, $patterns)) {
                    $score += 10;
                }
            }
            
            // Check template compatibility
            if (in_array($template_type, $card['template_compatibility'])) {
                $score += 5;
            }
            
            // Context bonus
            if ($this->matches_context($card, $context)) {
                $score += 3;
            }
            
            if ($score > 0) {
                $selected_cards[$card_id] = $score;
            }
        }
        
        // Sort by score and return top matches
        arsort($selected_cards);
        $top_cards = array_slice($selected_cards, 0, 3, true);
        
        // Return the best matching card(s)
        $result = array();
        foreach ($top_cards as $card_id => $score) {
            $result[] = array(
                'card_id' => $card_id,
                'card' => $this->card_registry[$card_id],
                'score' => $score
            );
        }
        
        return $result;
    }
    
    /**
     * Render card with data
     */
    public function render_card($card_id, $data, $options = array()) {
        if (!isset($this->card_registry[$card_id])) {
            return $this->render_fallback_card($data);
        }
        
        $card = $this->card_registry[$card_id];
        $theme = $options['theme'] ?? 'premium';
        
        // Generate HTML based on card type
        switch ($card['type']) {
            case 'magazine':
                return $this->render_magazine_card($card, $data, $theme);
            case 'interactive':
                return $this->render_interactive_card($card, $data, $theme);
            case 'data':
                return $this->render_data_card($card, $data, $theme);
            default:
                return $this->render_standard_card($card, $data, $theme);
        }
    }
    
    /**
     * Render magazine-style card
     */
    private function render_magazine_card($card, $data, $theme) {
        $layout = $card['layout'];
        $html = '<div class="sffc-magazine-card sffc-' . $layout . ' sffc-theme-' . $theme . '">';
        
        // Render based on specific layout
        switch ($layout) {
            case 'editorial':
                $html .= $this->render_editorial_layout($card['structure'], $data);
                break;
            case 'wealth_profile':
                $html .= $this->render_wealth_profile_layout($card['structure'], $data);
                break;
            case 'analytical':
                $html .= $this->render_analytical_layout($card['structure'], $data);
                break;
            case 'newspaper_front':
                $html .= $this->render_newspaper_layout($card['structure'], $data);
                break;
            case 'feature_spread':
                $html .= $this->render_feature_spread($card['structure'], $data);
                break;
            case 'stock_picks':
                $html .= $this->render_stock_picks_layout($card['structure'], $data);
                break;
            default:
                $html .= $this->render_generic_magazine($card['structure'], $data);
        }
        
        $html .= '</div>';
        
        return array(
            'html' => $html,
            'css_class' => 'sffc-magazine-card',
            'requires_js' => ($card['type'] === 'interactive'),
            'theme' => $theme
        );
    }
    
    /**
     * Render editorial layout
     */
    private function render_editorial_layout($structure, $data) {
        $html = '<article class="editorial-layout">';
        
        // Masthead
        $html .= '<header class="masthead">';
        $html .= '<div class="publication-name">' . $structure['masthead']['logo'] . '</div>';
        $html .= '<div class="publication-date">' . date('F j, Y') . '</div>';
        $html .= '<div class="edition">' . $structure['masthead']['edition'] . '</div>';
        $html .= '</header>';
        
        // Headline section
        $html .= '<div class="headline-section">';
        $html .= '<h1 class="main-headline">' . $this->fill_placeholder($structure['headline']['main'], $data) . '</h1>';
        if (!empty($structure['headline']['subtitle'])) {
            $html .= '<h2 class="subtitle">' . $this->fill_placeholder($structure['headline']['subtitle'], $data) . '</h2>';
        }
        $html .= '<div class="byline">' . $structure['headline']['byline'] . '</div>';
        $html .= '</div>';
        
        // Content with sidebar
        $html .= '<div class="content-grid">';
        
        // Main content
        $html .= '<div class="main-content">';
        $html .= '<p class="lead">' . $this->fill_placeholder($structure['content']['lead'], $data) . '</p>';
        $html .= '<div class="body">' . $this->fill_placeholder($structure['content']['body'], $data) . '</div>';
        if (!empty($structure['content']['pullquote'])) {
            $html .= '<blockquote class="pullquote">' . $this->fill_placeholder($structure['content']['pullquote'], $data) . '</blockquote>';
        }
        $html .= '</div>';
        
        // Sidebar
        $html .= '<aside class="sidebar">';
        if (!empty($data['related_stocks'])) {
            $html .= '<div class="related-stocks">';
            $html .= '<h3>Related Securities</h3>';
            $html .= '<ul>';
            foreach ($data['related_stocks'] as $stock) {
                $html .= '<li>' . $stock . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }
        $html .= '</aside>';
        
        $html .= '</div>';
        $html .= '</article>';
        
        return $html;
    }
    
    /**
     * Render wealth profile layout
     */
    private function render_wealth_profile_layout($structure, $data) {
        $html = '<article class="wealth-profile-layout" style="background: linear-gradient(135deg, #fef9e7 0%, white 100%); padding: 30px; border-radius: 8px;">';
        
        // Header
        $html .= '<header style="border-bottom: 2px solid #d4af37; padding-bottom: 15px; margin-bottom: 25px;">';
        $html .= '<div style="font-size: 14px; text-transform: uppercase; letter-spacing: 2px; color: #704214;">' . $structure['header']['publication'] . '</div>';
        $html .= '<div style="font-size: 18px; color: #2c1810; margin-top: 5px;">' . $structure['header']['category'] . '</div>';
        $html .= '</header>';
        
        // Profile grid
        $html .= '<div class="profile-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">';
        $html .= '<div><strong style="color: #704214;">Company:</strong> <span style="color: #2c1810; font-size: 20px;">' . ($data['company'] ?? 'N/A') . '</span></div>';
        $html .= '<div><strong style="color: #704214;">Market Cap:</strong> <span style="color: #2c1810;">' . ($data['market_cap'] ?? 'N/A') . '</span></div>';
        $html .= '<div><strong style="color: #704214;">CEO:</strong> <span style="color: #2c1810;">' . ($data['ceo'] ?? 'N/A') . '</span></div>';
        $html .= '<div><strong style="color: #704214;">Founded:</strong> <span style="color: #2c1810;">' . ($data['founded'] ?? 'N/A') . '</span></div>';
        $html .= '</div>';
        
        // Performance metrics
        $html .= '<div class="performance-metrics" style="background: rgba(212, 175, 55, 0.1); padding: 20px; border-radius: 6px;">';
        $html .= '<h3 style="color: #2c1810; margin-bottom: 15px;">Performance Metrics</h3>';
        $html .= '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">';
        $html .= '<div><strong>Revenue:</strong> ' . ($data['annual_revenue'] ?? 'N/A') . '</div>';
        $html .= '<div><strong>Profit:</strong> ' . ($data['net_income'] ?? 'N/A') . '</div>';
        $html .= '<div><strong>Growth:</strong> ' . ($data['yoy_growth'] ?? 'N/A') . '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</article>';
        return $html;
    }
    
    /**
     * Render analytical layout
     */
    private function render_analytical_layout($structure, $data) {
        $html = '<article class="analytical-layout" style="background: white; border: 1px solid #d4af37; padding: 30px; border-radius: 8px;">';
        
        // Header
        $html .= '<header style="border-bottom: 2px solid #d4af37; padding-bottom: 15px; margin-bottom: 25px;">';
        $html .= '<div style="font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #704214;">' . $structure['header']['series'] . '</div>';
        $html .= '<div style="font-size: 18px; color: #2c1810; margin-top: 5px; font-weight: bold;">' . $structure['header']['issue'] . '</div>';
        $html .= '</header>';
        
        // Thesis
        $html .= '<div class="thesis" style="background: linear-gradient(135deg, #fef9e7 0%, white 100%); padding: 20px; border-left: 4px solid #d4af37; margin-bottom: 25px;">';
        $html .= '<h3 style="color: #2c1810; margin-bottom: 10px;">Key Insight</h3>';
        $html .= '<p style="font-size: 18px; line-height: 1.6; color: #3d261a;">' . ($data['main_argument'] ?? $data['headline'] ?? 'Analysis in progress...') . '</p>';
        $html .= '</div>';
        
        // Analysis sections
        $html .= '<div class="analysis-sections" style="display: grid; gap: 20px;">';
        $html .= '<div><strong style="color: #704214;">Market Context:</strong> ' . ($data['context'] ?? 'Current market conditions apply') . '</div>';
        $html .= '<div><strong style="color: #704214;">Evidence:</strong> ' . ($data['evidence'] ?? 'Based on market data and trends') . '</div>';
        $html .= '<div><strong style="color: #704214;">Implications:</strong> ' . ($data['forward_looking'] ?? 'Strategic considerations ahead') . '</div>';
        $html .= '</div>';
        
        $html .= '</article>';
        return $html;
    }
    
    /**
     * Render newspaper layout
     */
    private function render_newspaper_layout($structure, $data) {
        $html = '<article class="newspaper-layout" style="background: #fef9e7; border: 1px solid #2c1810; padding: 0;">';
        
        // Masthead
        $html .= '<header class="masthead" style="background: #2c1810; color: #d4af37; padding: 20px; text-align: center; border-bottom: 3px solid #d4af37;">';
        $html .= '<div style="font-size: 36px; font-weight: bold; letter-spacing: 3px; font-family: serif;">' . $structure['masthead']['publication'] . '</div>';
        $html .= '<div style="font-size: 14px; margin-top: 5px;">' . date('l, F j, Y') . ' | ' . $structure['masthead']['edition'] . '</div>';
        $html .= '</header>';
        
        // Above fold
        $html .= '<div class="above-fold" style="padding: 30px;">';
        $html .= '<h1 style="font-size: 42px; line-height: 1.1; margin-bottom: 15px; color: #2c1810; font-family: serif;">' . ($data['main_headline'] ?? $data['headline'] ?? 'Breaking News') . '</h1>';
        $html .= '<p style="font-size: 20px; color: #704214; font-style: italic; margin-bottom: 20px;">' . ($data['main_subhead'] ?? $data['subtitle'] ?? '') . '</p>';
        $html .= '<div style="font-size: 16px; line-height: 1.8; color: #3d261a; column-count: 2; column-gap: 30px;">' . ($data['main_lead'] ?? $data['body_content'] ?? 'Story developing...') . '</div>';
        $html .= '</div>';
        
        $html .= '</article>';
        return $html;
    }
    
    /**
     * Render feature spread
     */
    private function render_feature_spread($structure, $data) {
        $html = '<article class="feature-spread" style="background: white; border: 2px solid #d4af37; padding: 0; border-radius: 8px; overflow: hidden;">';
        
        // Spread header
        $html .= '<header style="background: linear-gradient(90deg, #d4af37, #b8860b); padding: 15px 30px; color: white;">';
        $html .= '<div style="display: flex; justify-content: space-between; align-items: center;">';
        $html .= '<span style="font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">' . $structure['spread_header']['category'] . '</span>';
        $html .= '<span style="font-size: 14px;">' . ($data['reading_time'] ?? '5') . ' min read</span>';
        $html .= '</div>';
        $html .= '</header>';
        
        // Hero section
        $html .= '<div class="hero-section" style="padding: 40px; background: linear-gradient(135deg, #fef9e7 0%, white 100%);">';
        $html .= '<h1 style="font-size: 36px; color: #2c1810; margin-bottom: 15px; font-family: serif;">' . ($data['feature_title'] ?? $data['headline'] ?? 'Feature Story') . '</h1>';
        $html .= '<p style="font-size: 20px; color: #704214; font-style: italic; margin-bottom: 20px;">' . ($data['feature_deck'] ?? $data['subtitle'] ?? '') . '</p>';
        $html .= '<div style="font-size: 12px; color: #999; text-transform: uppercase;">By ' . ($data['author'] ?? 'AI Research Team') . '</div>';
        $html .= '</div>';
        
        // Article body
        $html .= '<div class="article-body" style="padding: 30px;">';
        $html .= '<p style="font-size: 18px; line-height: 1.8; color: #3d261a; margin-bottom: 20px;">' . ($data['intro_text'] ?? $data['body_content'] ?? 'Content loading...') . '</p>';
        
        // Key points
        if (!empty($data['key_points'])) {
            $html .= '<div style="background: rgba(212, 175, 55, 0.1); padding: 20px; border-left: 4px solid #d4af37; margin: 30px 0;">';
            $html .= '<h3 style="color: #2c1810; margin-bottom: 15px;">Key Points</h3>';
            $html .= '<ul style="color: #3d261a; line-height: 1.8;">';
            foreach ((array)$data['key_points'] as $point) {
                $html .= '<li>' . $point . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</article>';
        return $html;
    }
    
    /**
     * Render stock picks layout
     */
    private function render_stock_picks_layout($structure, $data) {
        $html = '<article class="stock-picks-layout" style="background: linear-gradient(135deg, #2c1810 0%, #3d261a 100%); color: white; padding: 30px; border-radius: 8px;">';
        
        // Header
        $html .= '<header style="border-bottom: 2px solid #d4af37; padding-bottom: 15px; margin-bottom: 25px;">';
        $html .= '<h2 style="font-size: 28px; color: #d4af37; margin-bottom: 5px;">' . $structure['picks_header']['title'] . '</h2>';
        $html .= '<p style="font-size: 16px; color: #fef9e7;">' . $structure['picks_header']['subtitle'] . '</p>';
        $html .= '</header>';
        
        // Featured pick
        $html .= '<div class="featured-pick" style="background: rgba(254, 249, 231, 0.1); padding: 25px; border-radius: 6px; margin-bottom: 25px;">';
        $html .= '<h3 style="color: #d4af37; font-size: 24px; margin-bottom: 15px;">Featured Selection</h3>';
        $html .= '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
        $html .= '<div>';
        $html .= '<div style="font-size: 20px; color: #fef9e7; font-weight: bold;">' . ($data['featured_company'] ?? 'Top Pick') . '</div>';
        $html .= '<div style="font-size: 16px; color: #d4af37; margin-top: 5px;">' . ($data['featured_ticker'] ?? 'TICKER') . '</div>';
        $html .= '</div>';
        $html .= '<div style="text-align: right;">';
        $html .= '<div style="font-size: 14px; color: #fef9e7;">Target Price</div>';
        $html .= '<div style="font-size: 24px; color: #4ade80; font-weight: bold;">' . ($data['price_target'] ?? '$100') . '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<p style="color: #fef9e7; margin-top: 15px; line-height: 1.6;">' . ($data['investment_thesis'] ?? 'Strong fundamentals and growth potential') . '</p>';
        $html .= '</div>';
        
        // Additional picks
        $html .= '<div class="additional-picks">';
        $html .= '<h4 style="color: #d4af37; margin-bottom: 15px;">Also Consider</h4>';
        $html .= '<div style="display: grid; gap: 15px;">';
        for ($i = 1; $i <= 2; $i++) {
            $html .= '<div style="background: rgba(254, 249, 231, 0.05); padding: 15px; border-left: 3px solid #d4af37;">';
            $html .= '<span style="color: #fef9e7; font-weight: bold;">' . ($data["company$i"] ?? "Company $i") . '</span> ';
            $html .= '<span style="color: #d4af37;">(' . ($data["ticker$i"] ?? "TICK$i") . ')</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</article>';
        return $html;
    }
    
    /**
     * Render generic magazine layout
     */
    private function render_generic_magazine($structure, $data) {
        $html = '<article class="generic-magazine" style="background: linear-gradient(135deg, #fef9e7 0%, white 100%); border: 1px solid #d4af37; padding: 30px; border-radius: 8px;">';
        $html .= '<h2 style="color: #2c1810; margin-bottom: 20px;">' . ($data['headline'] ?? 'Financial Intelligence') . '</h2>';
        $html .= '<p style="color: #3d261a; line-height: 1.8;">' . ($data['body_content'] ?? 'Content loading...') . '</p>';
        $html .= '</article>';
        return $html;
    }
    
    /**
     * Render career timeline
     */
    private function render_career_timeline($structure, $data) {
        $html = '<div class="career-timeline" style="background: linear-gradient(135deg, #fef9e7 0%, white 100%); padding: 30px; border-radius: 8px; border: 1px solid #d4af37;">';
        $html .= '<h3 style="color: #2c1810; text-align: center; margin-bottom: 30px;">Your Career Roadmap</h3>';
        
        // Timeline stages
        $html .= '<div style="display: grid; gap: 25px;">';
        $stages = array(
            array('title' => 'Foundation', 'duration' => '0-2 years', 'color' => '#d4af37'),
            array('title' => 'Growth', 'duration' => '2-5 years', 'color' => '#b8860b'),
            array('title' => 'Leadership', 'duration' => '5+ years', 'color' => '#704214')
        );
        
        foreach ($stages as $stage) {
            $html .= '<div style="border-left: 4px solid ' . $stage['color'] . '; padding-left: 20px;">';
            $html .= '<h4 style="color: ' . $stage['color'] . '; margin-bottom: 5px;">' . $stage['title'] . '</h4>';
            $html .= '<p style="color: #666; font-size: 14px;">' . $stage['duration'] . '</p>';
            $html .= '</div>';
        }
        $html .= '</div>';
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Render interactive card
     */
    private function render_interactive_card($card, $data, $theme) {
        $layout = $card['layout'];
        $html = '<div class="sffc-interactive-card sffc-' . $layout . '">';
        
        switch ($layout) {
            case 'choice_matrix':
                $html .= $this->render_choice_matrix($card['structure'], $data);
                break;
            case 'journey_selector':
                $html .= $this->render_journey_selector($card['structure'], $data);
                break;
            case 'action_grid':
                $html .= $this->render_action_grid($card['structure'], $data);
                break;
            case 'career_timeline':
                $html .= $this->render_career_timeline($card['structure'], $data);
                break;
        }
        
        $html .= '</div>';
        
        // Add JavaScript for interactivity
        $html .= $this->generate_card_javascript($card, $data);
        
        return array(
            'html' => $html,
            'css_class' => 'sffc-interactive-card',
            'requires_js' => true,
            'theme' => $theme
        );
    }
    
    /**
     * Render data visualization card
     */
    private function render_data_card($card, $data, $theme = 'premium') {
        $layout = $card['layout'] ?? 'terminal';
        $html = '<div class="sffc-data-card sffc-' . $layout . '">';
        
        switch ($layout) {
            case 'terminal':
                $html .= $this->render_terminal_view($card['structure'] ?? array(), $data);
                break;
            case 'heatmap':
                $html .= $this->render_heatmap_view($card['structure'] ?? array(), $data);
                break;
            case 'dashboard':
                $html .= $this->render_pe_deals_dashboard($card['structure'] ?? array(), $data);
                break;
            default:
                // Default data display
                $html .= '<div class="data-container">';
                $html .= '<h3>Data View</h3>';
                foreach ($data as $key => $value) {
                    if (!is_array($value)) {
                        $html .= '<div class="data-item">';
                        $html .= '<span class="data-label">' . ucfirst(str_replace('_', ' ', $key)) . ':</span>';
                        $html .= '<span class="data-value">' . esc_html($value) . '</span>';
                        $html .= '</div>';
                    }
                }
                $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return array(
            'html' => $html,
            'css_class' => 'sffc-data-card',
            'requires_js' => false,
            'theme' => $theme
        );
    }
    
    /**
     * Generate premium CSS for all cards
     */
    public function generate_premium_css() {
        $css = '
        /* Premium Magazine Card Styles */
        .sffc-magazine-card {
            background: linear-gradient(135deg, #fef9e7 0%, #fdf6e3 100%);
            border: 1px solid #d4af37;
            border-radius: 0;
            padding: 0;
            font-family: "Playfair Display", Georgia, serif;
            color: #2c1810;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .sffc-magazine-card .masthead {
            background: linear-gradient(90deg, #2c1810 0%, #3d261a 100%);
            color: #d4af37;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #d4af37;
            font-family: "Bebas Neue", sans-serif;
        }
        
        .sffc-magazine-card .publication-name {
            font-size: 24px;
            letter-spacing: 3px;
            font-weight: bold;
        }
        
        .sffc-magazine-card .editorial-layout {
            padding: 40px;
        }
        
        .sffc-magazine-card .main-headline {
            font-size: 48px;
            line-height: 1.1;
            margin-bottom: 20px;
            font-weight: 700;
            color: #2c1810;
            font-family: "Playfair Display", serif;
        }
        
        .sffc-magazine-card .subtitle {
            font-size: 24px;
            color: #704214;
            margin-bottom: 15px;
            font-weight: 400;
            font-style: italic;
        }
        
        .sffc-magazine-card .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
            margin-top: 30px;
        }
        
        .sffc-magazine-card .lead {
            font-size: 20px;
            line-height: 1.6;
            margin-bottom: 25px;
            font-weight: 500;
            color: #3d261a;
            border-left: 4px solid #d4af37;
            padding-left: 20px;
        }
        
        .sffc-magazine-card .pullquote {
            font-size: 28px;
            font-style: italic;
            color: #704214;
            margin: 40px 0;
            padding: 30px;
            background: rgba(212, 175, 55, 0.05);
            border-left: 5px solid #d4af37;
            position: relative;
        }
        
        .sffc-magazine-card .pullquote::before {
            content: """;
            font-size: 60px;
            color: #d4af37;
            position: absolute;
            top: -10px;
            left: 10px;
        }
        
        .sffc-magazine-card .sidebar {
            background: rgba(212, 175, 55, 0.08);
            padding: 25px;
            border-radius: 8px;
        }
        
        .sffc-magazine-card .sidebar h3 {
            font-size: 18px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #704214;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        /* Interactive Card Styles */
        .sffc-interactive-card {
            background: linear-gradient(135deg, #ffffff 0%, #fef9e7 100%);
            border: 2px solid #d4af37;
            border-radius: 12px;
            padding: 30px;
            font-family: "Inter", sans-serif;
        }
        
        .sffc-interactive-card .choice-matrix {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .sffc-interactive-card .option-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 25px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .sffc-interactive-card .option-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #d4af37, #f4e4bc);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .sffc-interactive-card .option-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.2);
            border-color: #d4af37;
        }
        
        .sffc-interactive-card .option-card:hover::before {
            transform: scaleX(1);
        }
        
        .sffc-interactive-card .option-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #d4af37, #f4e4bc);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            color: white;
        }
        
        .sffc-interactive-card .option-title {
            font-size: 20px;
            font-weight: 600;
            color: #2c1810;
            margin-bottom: 10px;
        }
        
        .sffc-interactive-card .option-description {
            color: #6b7280;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        
        .sffc-interactive-card .pros-cons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
        
        .sffc-interactive-card .pros,
        .sffc-interactive-card .cons {
            font-size: 14px;
        }
        
        .sffc-interactive-card .pros h4,
        .sffc-interactive-card .cons h4 {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .sffc-interactive-card .pros h4 {
            color: #10b981;
        }
        
        .sffc-interactive-card .cons h4 {
            color: #ef4444;
        }
        
        /* Data Card Styles - Bloomberg Terminal with Cream/Gold */
        .sffc-data-card {
            background: #1a1a1a;
            border: 2px solid #d4af37;
            border-radius: 8px;
            padding: 0;
            font-family: "JetBrains Mono", monospace;
            color: #fef9e7;
            overflow: hidden;
        }
        
        .sffc-data-card.sffc-terminal {
            background: linear-gradient(135deg, #1a1a1a 0%, #2c1810 100%);
            border: 3px solid #d4af37;
            box-shadow: 0 0 40px rgba(212, 175, 55, 0.2);
        }
        
        .sffc-data-card .terminal-header {
            background: linear-gradient(90deg, #d4af37, #b8860b);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #fef9e7;
        }
        
        .sffc-data-card .ticker-symbol {
            font-size: 24px;
            font-weight: bold;
            color: #1a1a1a;
            letter-spacing: 1px;
        }
        
        .sffc-data-card .price-panel {
            padding: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
        }
        
        .sffc-data-card .price-item {
            display: flex;
            flex-direction: column;
        }
        
        .sffc-data-card .price-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        
        .sffc-data-card .price-value {
            font-size: 28px;
            font-weight: bold;
            color: #fef9e7;
        }
        
        .sffc-data-card .price-value.positive {
            color: #4ade80;
        }
        
        .sffc-data-card .price-value.negative {
            color: #f87171;
        }
        
        .sffc-data-card .mini-chart {
            height: 60px;
            margin: 20px;
            background: rgba(212, 175, 55, 0.05);
            border-radius: 4px;
            position: relative;
        }
        
        /* Heatmap Styles */
        .sffc-heatmap-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 25px;
        }
        
        .sffc-heatmap-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 8px;
            margin-top: 20px;
        }
        
        .sffc-heatmap-cell {
            padding: 20px 15px;
            text-align: center;
            border-radius: 6px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }
        
        .sffc-heatmap-cell.strong-positive {
            background: #10b981;
            color: white;
        }
        
        .sffc-heatmap-cell.positive {
            background: #34d399;
            color: white;
        }
        
        .sffc-heatmap-cell.neutral {
            background: #e5e7eb;
            color: #374151;
        }
        
        .sffc-heatmap-cell.negative {
            background: #f87171;
            color: white;
        }
        
        .sffc-heatmap-cell.strong-negative {
            background: #ef4444;
            color: white;
        }
        
        .sffc-heatmap-cell:hover {
            transform: scale(1.05);
            z-index: 10;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .sffc-magazine-card .content-grid {
                grid-template-columns: 1fr;
            }
            
            .sffc-magazine-card .main-headline {
                font-size: 32px;
            }
            
            .sffc-interactive-card .choice-matrix {
                grid-template-columns: 1fr;
            }
        }
        
        /* Premium Theme Overrides */
        .sffc-theme-premium {
            background: linear-gradient(135deg, #fef9e7 0%, #ffffff 50%, #fdf6e3 100%);
            box-shadow: 0 20px 60px rgba(212, 175, 55, 0.15);
        }
        
        .sffc-theme-premium .masthead {
            background: linear-gradient(90deg, #2c1810 0%, #704214 50%, #2c1810 100%);
        }
        
        /* Dark Theme */
        .sffc-theme-dark {
            background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%);
            color: #ffffff;
            border-color: #d4af37;
        }
        
        .sffc-theme-dark .masthead {
            background: linear-gradient(90deg, #d4af37 0%, #f4e4bc 100%);
            color: #0a0e27;
        }
        ';
        
        return $css;
    }
    
    /**
     * Helper function to fill placeholders with data
     */
    private function fill_placeholder($template, $data) {
        if (!is_string($template)) {
            return $template;
        }
        
        preg_match_all('/\{([^}]+)\}/', $template, $matches);
        $result = $template;
        
        foreach ($matches[1] as $key) {
            if (isset($data[$key])) {
                $result = str_replace('{' . $key . '}', $data[$key], $result);
            }
        }
        
        return $result;
    }
    
    /**
     * Extract patterns from query analysis
     */
    private function extract_patterns($analysis) {
        $patterns = array();
        
        // Add intent patterns
        if (isset($analysis['intent'])) {
            if (is_array($analysis['intent'])) {
                $patterns = array_merge($patterns, $analysis['intent']);
            } else {
                $patterns[] = $analysis['intent'];
            }
        }
        
        // Add response type patterns
        if (isset($analysis['response_type'])) {
            $patterns[] = str_replace('_response', '', $analysis['response_type']);
            $patterns[] = $analysis['response_type'];
        }
        
        // Add entity-based patterns
        if (!empty($analysis['entities']['companies'])) {
            $patterns[] = 'company_data';
            $patterns[] = 'company';
            $patterns[] = 'company_profile';
        }
        
        if (!empty($analysis['entities']['financial_terms'])) {
            foreach ($analysis['entities']['financial_terms'] as $term) {
                if ($term['category'] === 'pe_related') {
                    $patterns[] = 'pe_deals';
                    $patterns[] = 'private_equity';
                }
            }
        }
        
        // Add query-based patterns - MORE COMPREHENSIVE
        $query_lower = strtolower($analysis['original_query'] ?? '');
        
        // Greetings
        if (preg_match('/\b(hello|hi|hey|good morning|good afternoon|how are you)\b/', $query_lower)) {
            $patterns[] = 'greeting';
            $patterns[] = 'hello';
            $patterns[] = 'hi';
        }
        
        // News
        if (strpos($query_lower, 'news') !== false || strpos($query_lower, 'headline') !== false || strpos($query_lower, 'latest') !== false) {
            $patterns[] = 'news';
            $patterns[] = 'headlines';
            $patterns[] = 'latest';
            $patterns[] = 'market_news';
        }
        
        // Stock price
        if (strpos($query_lower, 'price') !== false || strpos($query_lower, 'quote') !== false || strpos($query_lower, 'trading') !== false) {
            $patterns[] = 'stock_price';
            $patterns[] = 'price';
            $patterns[] = 'quote';
            $patterns[] = 'market_data';
        }
        
        // Comparison
        if (strpos($query_lower, 'compare') !== false || strpos($query_lower, 'versus') !== false || strpos($query_lower, ' vs ') !== false) {
            $patterns[] = 'compare';
            $patterns[] = 'versus';
            $patterns[] = 'comparison';
        }
        
        // Analysis
        if (strpos($query_lower, 'analyze') !== false || strpos($query_lower, 'analysis') !== false || strpos($query_lower, 'deep dive') !== false) {
            $patterns[] = 'analysis';
            $patterns[] = 'deep_analysis';
            $patterns[] = 'analytical';
            $patterns[] = 'deep_dive';
        }
        
        // Educational
        if (strpos($query_lower, 'what is') !== false || strpos($query_lower, 'explain') !== false || strpos($query_lower, 'how does') !== false) {
            $patterns[] = 'educational';
            $patterns[] = 'what_is';
            $patterns[] = 'explain';
            $patterns[] = 'concept_explanation';
        }
        
        // Recommendations
        if (strpos($query_lower, 'recommend') !== false || strpos($query_lower, 'suggest') !== false || strpos($query_lower, 'should i') !== false || strpos($query_lower, 'best') !== false) {
            $patterns[] = 'recommendations';
            $patterns[] = 'suggest';
            $patterns[] = 'best_stocks';
            $patterns[] = 'opportunities';
        }
        
        // Career
        if (strpos($query_lower, 'career') !== false || strpos($query_lower, 'job') !== false || strpos($query_lower, 'become') !== false || strpos($query_lower, 'analyst') !== false) {
            $patterns[] = 'career';
            $patterns[] = 'job';
            $patterns[] = 'career_advice';
            $patterns[] = 'path';
        }
        
        // Strategy
        if (strpos($query_lower, 'strategy') !== false || strpos($query_lower, 'choose') !== false || strpos($query_lower, 'investment') !== false) {
            $patterns[] = 'strategy';
            $patterns[] = 'decision_request';
            $patterns[] = 'strategy_question';
            $patterns[] = 'what_should';
        }
        
        return array_unique($patterns);
    }
    
    /**
     * Get template type from template object
     */
    private function get_template_type($template) {
        if (is_array($template)) {
            if (isset($template['visual'])) {
                return $template['visual'];
            }
            if (isset($template['structure']['visual'])) {
                return $template['structure']['visual'];
            }
        }
        return 'general';
    }
    
    /**
     * Check if card matches context
     */
    private function matches_context($card, $context) {
        // Check expertise level match
        if (isset($context['expertise_level'])) {
            if ($context['expertise_level'] === 'beginner' && $card['type'] === 'interactive') {
                return true;
            }
            if ($context['expertise_level'] === 'expert' && $card['type'] === 'data') {
                return true;
            }
        }
        
        // Check conversation mode match
        if (isset($context['conversation_mode'])) {
            if ($context['conversation_mode'] === 'market_data' && in_array('data', array($card['type']))) {
                return true;
            }
            if ($context['conversation_mode'] === 'educational' && $card['type'] === 'magazine') {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate JavaScript for interactive cards
     */
    private function generate_card_javascript($card, $data) {
        $js = '<script>';
        $js .= 'document.addEventListener("DOMContentLoaded", function() {';
        $js .= 'const cards = document.querySelectorAll(".sffc-interactive-card .option-card");';
        $js .= 'cards.forEach(card => {';
        $js .= 'card.addEventListener("click", function() {';
        $js .= 'const action = this.dataset.action;';
        $js .= 'if (action) {';
        $js .= 'window.sffcHandleCardAction(action, ' . json_encode($data) . ');';
        $js .= '}';
        $js .= '});';
        $js .= '});';
        $js .= '});';
        $js .= '</script>';
        
        return $js;
    }
    
    /**
     * Render choice matrix layout
     */
    private function render_choice_matrix($structure, $data) {
        $html = '<div class="choice-matrix-container">';
        $html .= '<h3 class="matrix-title">Choose Your Path</h3>';
        $html .= '<div class="choice-grid">';
        
        $choices = array(
            array('title' => 'Conservative', 'desc' => $data['conservative_desc'] ?? 'Lower risk, stable returns', 'icon' => 'shield'),
            array('title' => 'Balanced', 'desc' => $data['balanced_desc'] ?? 'Mixed risk and reward', 'icon' => 'balance'),
            array('title' => 'Aggressive', 'desc' => $data['aggressive_desc'] ?? 'Higher risk, higher potential', 'icon' => 'rocket')
        );
        
        foreach ($choices as $choice) {
            $html .= '<div class="option-card" data-action="select_' . strtolower($choice['title']) . '">';
            $html .= '<div class="option-icon" data-icon="' . $choice['icon'] . '"></div>';
            $html .= '<h4 class="option-title">' . $choice['title'] . '</h4>';
            $html .= '<p class="option-desc">' . $choice['desc'] . '</p>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Render journey selector layout
     */
    private function render_journey_selector($structure, $data) {
        $html = '<div class="journey-selector-container">';
        $html .= '<h3 class="journey-title">Select Your Learning Path</h3>';
        $html .= '<div class="journey-paths">';
        
        $paths = array(
            array('title' => 'Fundamentals', 'desc' => 'Start with the basics', 'level' => 'Beginner'),
            array('title' => 'Advanced Topics', 'desc' => 'Deep dive into complex concepts', 'level' => 'Expert'),
            array('title' => 'Practical Application', 'desc' => 'Real-world examples', 'level' => 'Intermediate')
        );
        
        foreach ($paths as $path) {
            $html .= '<div class="path-card" data-action="select_path_' . strtolower($path['title']) . '">';
            $html .= '<span class="level-badge">' . $path['level'] . '</span>';
            $html .= '<h4>' . $path['title'] . '</h4>';
            $html .= '<p>' . $path['desc'] . '</p>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Render action grid layout
     */
    private function render_action_grid($structure, $data) {
        $html = '<div class="action-grid-container">';
        $html .= '<h3 class="grid-title">Quick Actions</h3>';
        $html .= '<div class="action-grid">';
        
        $actions = array(
            array('title' => 'View Details', 'icon' => 'chart'),
            array('title' => 'Compare', 'icon' => 'refresh'),
            array('title' => 'Analyze', 'icon' => 'analytics'),
            array('title' => 'Export', 'icon' => 'save')
        );
        
        foreach ($actions as $action) {
            $html .= '<div class="action-item" data-action="' . strtolower(str_replace(' ', '_', $action['title'])) . '">';
            $html .= '<div class="action-icon" data-icon="' . $action['icon'] . '"></div>';
            $html .= '<span class="action-label">' . $action['title'] . '</span>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Render terminal view for Bloomberg-style card
     */
    private function render_terminal_view($structure, $data) {
        $html = '<div class="terminal-container" style="background: linear-gradient(135deg, #1a1a1a 0%, #2c1810 100%); border: 3px solid #d4af37;">';
        $html .= '<div class="terminal-header" style="background: linear-gradient(90deg, #d4af37, #b8860b); color: #1a1a1a;">';
        $html .= '<span class="terminal-title" style="color: #1a1a1a; font-weight: bold;">MARKET DATA TERMINAL</span>';
        $html .= '<span class="terminal-time" style="color: #2c1810;">' . ($data['LIVE_TIME'] ?? date('H:i:s')) . '</span>';
        $html .= '</div>';
        $html .= '<div class="terminal-body">';
        
        // Price panel
        $html .= '<div class="price-panel">';
        $html .= '<div class="price-main" style="color: #fef9e7;">';
        $html .= '<span class="ticker" style="color: #d4af37; font-size: 32px; font-weight: bold;">' . ($data['TICKER'] ?? 'N/A') . '</span>';
        $html .= '<span class="price" style="color: #fef9e7; font-size: 48px; font-weight: bold; margin: 0 20px;">' . ($data['current_price'] ?? '0.00') . '</span>';
        $html .= '<span class="change ' . (($data['change_percent'] ?? 0) >= 0 ? 'positive' : 'negative') . '" style="color: ' . (($data['change_percent'] ?? 0) >= 0 ? '#4ade80' : '#f87171') . '; font-size: 24px;">';
        $html .= ($data['change_percent'] ?? 0) >= 0 ? '+' : '';
        $html .= ($data['change_percent'] ?? '0.00') . '%</span>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Market depth
        $html .= '<div class="market-depth" style="background: rgba(254, 249, 231, 0.1); padding: 20px; margin: 20px; border-radius: 4px;">';
        $html .= '<div class="bid-ask" style="display: flex; gap: 30px;">';
        $html .= '<div class="bid" style="color: #fef9e7;">BID: <span style="color: #d4af37; font-weight: bold;">' . ($data['bid'] ?? $data['current_price'] ?? '0.00') . '</span></div>';
        $html .= '<div class="ask" style="color: #fef9e7;">ASK: <span style="color: #d4af37; font-weight: bold;">' . ($data['ask'] ?? $data['current_price'] ?? '0.00') . '</span></div>';
        $html .= '</div>';
        $html .= '<div style="color: #fef9e7; margin-top: 10px;">VOL: <span style="color: #d4af37;">' . ($data['volume'] ?? '0') . '</span> | HIGH: <span style="color: #4ade80;">' . ($data['day_high'] ?? '0.00') . '</span> | LOW: <span style="color: #f87171;">' . ($data['day_low'] ?? '0.00') . '</span></div>';
        $html .= '</div>';
        
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Render heatmap view
     */
    private function render_heatmap_view($structure, $data) {
        $html = '<div class="heatmap-container">';
        $html .= '<h3 class="heatmap-title">Market Heatmap</h3>';
        $html .= '<div class="heatmap-grid">';
        
        $sectors = $data['sectors'] ?? array(
            'Technology' => 2.3,
            'Finance' => -0.5,
            'Healthcare' => 1.2,
            'Energy' => -1.8
        );
        
        foreach ($sectors as $sector => $change) {
            $color_class = $change > 1 ? 'hot' : ($change > 0 ? 'warm' : ($change > -1 ? 'cool' : 'cold'));
            $html .= '<div class="heatmap-cell ' . $color_class . '">';
            $html .= '<span class="sector-name">' . $sector . '</span>';
            $html .= '<span class="sector-change">' . ($change >= 0 ? '+' : '') . $change . '%</span>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Render PE deals dashboard
     */
    private function render_pe_deals_dashboard($structure, $data) {
        $html = '<div class="pe-deals-container">';
        $html .= '<h3 class="deals-title">Recent PE Deals</h3>';
        $html .= '<div class="deals-list">';
        
        $deals = $data['deals'] ?? array(
            array('firm' => 'KKR', 'target' => 'TechCo', 'value' => '$2.5B', 'date' => '2024-01'),
            array('firm' => 'Blackstone', 'target' => 'RealEstate Inc', 'value' => '$4.1B', 'date' => '2024-01')
        );
        
        foreach ($deals as $deal) {
            $html .= '<div class="deal-card">';
            $html .= '<div class="deal-header">';
            $html .= '<span class="firm-name">' . $deal['firm'] . '</span>';
            $html .= '<span class="deal-value">' . $deal['value'] . '</span>';
            $html .= '</div>';
            $html .= '<div class="deal-target">' . $deal['target'] . '</div>';
            $html .= '<div class="deal-date">' . $deal['date'] . '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Render fallback card when no specific card matches
     */
    private function render_fallback_card($data) {
        $html = '<div class="sffc-fallback-card" style="background: linear-gradient(135deg, #fef9e7 0%, white 100%); border: 2px solid #d4af37; border-radius: 8px; padding: 30px;">';
        $html .= '<h3 style="color: #2c1810; margin-bottom: 15px;">Financial Intelligence</h3>';
        $html .= '<p style="color: #3d261a; line-height: 1.6;">' . (isset($data['message']) ? esc_html($data['message']) : 'Premium financial intelligence and insights.') . '</p>';
        $html .= '</div>';
        
        return array(
            'html' => $html,
            'css_class' => 'sffc-fallback-card',
            'requires_js' => false,
            'theme' => 'premium'
        );
    }
    
    /**
     * Render standard card format
     */
    private function render_standard_card($card, $data, $theme) {
        $html = '<div class="sffc-standard-card sffc-theme-' . $theme . '" style="background: linear-gradient(135deg, #fef9e7 0%, white 100%); border: 1px solid #d4af37; border-radius: 8px; padding: 25px;">';
        
        // Card header
        if (!empty($data['headline'])) {
            $html .= '<h2 style="color: #2c1810; margin-bottom: 15px; font-family: serif;">' . esc_html($data['headline']) . '</h2>';
        }
        
        // Card subtitle
        if (!empty($data['subtitle'])) {
            $html .= '<p style="color: #704214; font-style: italic; margin-bottom: 20px;">' . esc_html($data['subtitle']) . '</p>';
        }
        
        // Card body
        if (!empty($data['body_content'])) {
            $html .= '<div style="color: #3d261a; line-height: 1.8;">' . esc_html($data['body_content']) . '</div>';
        }
        
        // Additional data points
        if (!empty($data['data_points'])) {
            $html .= '<div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #d4af37;">';
            foreach ($data['data_points'] as $label => $value) {
                $html .= '<div style="display: flex; justify-content: space-between; margin-bottom: 10px;">';
                $html .= '<span style="color: #704214; font-weight: bold;">' . esc_html($label) . ':</span>';
                $html .= '<span style="color: #2c1810;">' . esc_html($value) . '</span>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return array(
            'html' => $html,
            'css_class' => 'sffc-standard-card',
            'requires_js' => false,
            'theme' => $theme
        );
    }
}