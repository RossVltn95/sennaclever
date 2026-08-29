<?php
/**
 * Expanded Premium Visual Cards Library
 * Comprehensive collection of unbranded, premium publication-style cards
 * 
 * @package SennaCareers  
 * @since 10.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Premium_Visual_Cards_Expanded {
    
    private static $instance = null;
    private $advanced_pattern_matcher;
    
    /**
     * Expanded card registry with premium unbranded designs
     */
    private $expanded_card_registry = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->initialize_expanded_registry();
        
        // Load advanced pattern matcher
        if (!class_exists('SFFC_Advanced_Pattern_Matcher')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-advanced-pattern-matcher.php';
        }
        $this->advanced_pattern_matcher = SFFC_Advanced_Pattern_Matcher::get_instance();
    }
    
    /**
     * Initialize expanded card registry with premium unbranded designs
     */
    private function initialize_expanded_registry() {
        $this->expanded_card_registry = array(
            
            // === PREMIUM NEWSPAPER STYLES ===
            
            'markets_daily_card' => array(
                'name' => 'Markets Daily',
                'style' => 'Pink financial paper with serif typography',
                'inspiration' => 'Premium financial broadsheet',
                'best_for' => array('greeting', 'market_news', 'general'),
                'design_elements' => array(
                    'background' => 'linear-gradient(135deg, #fff5f5 0%, #ffe0e0 100%)',
                    'primary_color' => '#2c1810',
                    'accent_color' => '#d4af37',
                    'typography' => 'Playfair Display, serif',
                    'layout' => 'multi-column newspaper'
                )
            ),
            
            'business_chronicle_card' => array(
                'name' => 'Business Chronicle',
                'style' => 'Classic business journal with authoritative presence',
                'inspiration' => 'Traditional business newspaper',
                'best_for' => array('news', 'headlines', 'market_updates'),
                'design_elements' => array(
                    'background' => '#fef9e7',
                    'primary_color' => '#1a1a1a',
                    'accent_color' => '#d4af37',
                    'typography' => 'Georgia, serif',
                    'layout' => 'front-page newspaper'
                )
            ),
            
            'financial_telegraph_card' => array(
                'name' => 'Financial Telegraph',
                'style' => 'British broadsheet elegance',
                'inspiration' => 'Classic British financial press',
                'best_for' => array('analysis', 'commentary', 'opinion'),
                'design_elements' => array(
                    'background' => '#f9f9f9',
                    'primary_color' => '#0a0a0a',
                    'accent_color' => '#8b7355',
                    'typography' => 'Times New Roman, serif',
                    'layout' => 'traditional broadsheet'
                )
            ),
            
            // === PREMIUM MAGAZINE STYLES ===
            
            'global_investor_card' => array(
                'name' => 'Global Investor',
                'style' => 'Luxury wealth management magazine',
                'inspiration' => 'High-end investor publications',
                'best_for' => array('company_profile', 'wealth_data', 'executive_info'),
                'design_elements' => array(
                    'background' => 'linear-gradient(180deg, #2c1810 0%, #3d261a 100%)',
                    'primary_color' => '#fef9e7',
                    'accent_color' => '#d4af37',
                    'typography' => 'Bodoni MT, serif',
                    'layout' => 'luxury magazine spread'
                )
            ),
            
            'capital_insights_card' => array(
                'name' => 'Capital Insights',
                'style' => 'Analytical intelligence publication',
                'inspiration' => 'Data-driven research journals',
                'best_for' => array('educational', 'deep_analysis', 'research'),
                'design_elements' => array(
                    'background' => 'white',
                    'primary_color' => '#1e3a8a',
                    'accent_color' => '#d4af37',
                    'typography' => 'IBM Plex Sans, sans-serif',
                    'layout' => 'research paper'
                )
            ),
            
            'executive_digest_card' => array(
                'name' => 'Executive Digest',
                'style' => 'C-suite briefing publication',
                'inspiration' => 'Executive summary reports',
                'best_for' => array('deep_dive', 'analysis', 'earnings'),
                'design_elements' => array(
                    'background' => 'linear-gradient(135deg, #f8f8f8 0%, #e8e8e8 100%)',
                    'primary_color' => '#2c1810',
                    'accent_color' => '#b8860b',
                    'typography' => 'Merriweather, serif',
                    'layout' => 'executive brief'
                )
            ),
            
            'equity_insights_card' => array(
                'name' => 'Equity Insights',
                'style' => 'Premium stock selection journal',
                'inspiration' => 'Elite investment advisory',
                'best_for' => array('recommendations', 'stock_picks', 'opportunities'),
                'design_elements' => array(
                    'background' => '#0a0e27',
                    'primary_color' => '#fef9e7',
                    'accent_color' => '#d4af37',
                    'typography' => 'Roboto Slab, serif',
                    'layout' => 'advisory bulletin'
                )
            ),
            
            // === DATA VISUALIZATION CARDS ===
            
            'bloomberg_terminal_card' => array(
                'name' => 'Market Data Terminal',
                'style' => 'Professional trading terminal interface',
                'inspiration' => 'Institutional trading systems',
                'best_for' => array('stock_price', 'live_data', 'quotes'),
                'design_elements' => array(
                    'background' => '#1a1a1a',
                    'primary_color' => '#fef9e7',
                    'accent_color' => '#d4af37',
                    'highlight_positive' => '#4ade80',
                    'highlight_negative' => '#f87171',
                    'typography' => 'JetBrains Mono, monospace',
                    'layout' => 'terminal grid'
                )
            ),
            
            'market_heatmap_card' => array(
                'name' => 'Sector Performance Matrix',
                'style' => 'Visual market sentiment display',
                'inspiration' => 'Professional market monitors',
                'best_for' => array('market_overview', 'sector_performance', 'sentiment'),
                'design_elements' => array(
                    'background' => '#ffffff',
                    'grid_colors' => array('#10b981', '#84cc16', '#eab308', '#ef4444'),
                    'accent_color' => '#d4af37',
                    'typography' => 'Inter, sans-serif',
                    'layout' => 'heatmap grid'
                )
            ),
            
            'analytics_dashboard_card' => array(
                'name' => 'Analytics Command Center',
                'style' => 'Professional data analytics platform',
                'inspiration' => 'Enterprise BI dashboards',
                'best_for' => array('metrics', 'kpi', 'performance'),
                'design_elements' => array(
                    'background' => '#f3f4f6',
                    'primary_color' => '#111827',
                    'accent_color' => '#d4af37',
                    'chart_colors' => array('#3b82f6', '#10b981', '#f59e0b', '#ef4444'),
                    'typography' => 'Inter, sans-serif',
                    'layout' => 'dashboard grid'
                )
            ),
            
            // === INTERACTIVE DECISION CARDS ===
            
            'strategy_choice_card' => array(
                'name' => 'Strategic Decision Matrix',
                'style' => 'Interactive strategy selector',
                'inspiration' => 'Consulting frameworks',
                'best_for' => array('strategy', 'decisions', 'choices'),
                'design_elements' => array(
                    'background' => 'linear-gradient(135deg, #fef9e7 0%, white 100%)',
                    'primary_color' => '#2c1810',
                    'accent_color' => '#d4af37',
                    'typography' => 'Inter, sans-serif',
                    'layout' => 'choice matrix',
                    'interactions' => 'hover states, click actions'
                )
            ),
            
            'comparison_matrix_card' => array(
                'name' => 'Comparative Analysis Grid',
                'style' => 'Side-by-side comparison display',
                'inspiration' => 'Research comparison tables',
                'best_for' => array('comparison', 'versus', 'differences'),
                'design_elements' => array(
                    'background' => 'white',
                    'primary_color' => '#1f2937',
                    'accent_color' => '#d4af37',
                    'winner_highlight' => '#10b981',
                    'typography' => 'Inter, sans-serif',
                    'layout' => 'comparison table'
                )
            ),
            
            'career_roadmap_card' => array(
                'name' => 'Professional Journey Map',
                'style' => 'Career progression timeline',
                'inspiration' => 'Professional development guides',
                'best_for' => array('career', 'path', 'progression'),
                'design_elements' => array(
                    'background' => 'linear-gradient(180deg, #fef9e7 0%, white 100%)',
                    'primary_color' => '#2c1810',
                    'timeline_colors' => array('#d4af37', '#b8860b', '#704214'),
                    'typography' => 'Inter, sans-serif',
                    'layout' => 'vertical timeline'
                )
            ),
            
            // === PRIVATE EQUITY SPECIALIZED ===
            
            'pe_deal_card' => array(
                'name' => 'Deal Announcement Board',
                'style' => 'PE transaction display',
                'inspiration' => 'Deal tombstones',
                'best_for' => array('pe_deals', 'acquisitions', 'transactions'),
                'design_elements' => array(
                    'background' => 'linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%)',
                    'primary_color' => 'white',
                    'accent_color' => '#d4af37',
                    'typography' => 'Inter, sans-serif',
                    'layout' => 'deal structure'
                )
            ),
            
            'fund_performance_card' => array(
                'name' => 'Fund Performance Report',
                'style' => 'Institutional fund metrics',
                'inspiration' => 'LP reports',
                'best_for' => array('fund_performance', 'returns', 'metrics'),
                'design_elements' => array(
                    'background' => '#f9fafb',
                    'primary_color' => '#111827',
                    'accent_color' => '#d4af37',
                    'performance_colors' => array('#10b981', '#ef4444'),
                    'typography' => 'Inter, sans-serif',
                    'layout' => 'performance grid'
                )
            ),
            
            // === EARNINGS & REPORTS ===
            
            'earnings_spotlight_card' => array(
                'name' => 'Earnings Spotlight',
                'style' => 'Quarterly results highlight',
                'inspiration' => 'Earnings call presentations',
                'best_for' => array('earnings', 'quarterly_results', 'guidance'),
                'design_elements' => array(
                    'background' => 'white',
                    'primary_color' => '#0f172a',
                    'accent_color' => '#d4af37',
                    'beat_color' => '#10b981',
                    'miss_color' => '#ef4444',
                    'typography' => 'Inter, sans-serif',
                    'layout' => 'earnings summary'
                )
            ),
            
            // === LEARNING & EDUCATION ===
            
            'learning_journey_card' => array(
                'name' => 'Knowledge Path',
                'style' => 'Educational progression display',
                'inspiration' => 'Online learning platforms',
                'best_for' => array('learning', 'education', 'concepts'),
                'design_elements' => array(
                    'background' => 'linear-gradient(135deg, #fef9e7 0%, #fdf6e3 100%)',
                    'primary_color' => '#2c1810',
                    'accent_color' => '#d4af37',
                    'progress_color' => '#10b981',
                    'typography' => 'Inter, sans-serif',
                    'layout' => 'learning modules'
                )
            ),
            
            // === MARKET SENTIMENT ===
            
            'sentiment_pulse_card' => array(
                'name' => 'Market Sentiment Pulse',
                'style' => 'Real-time sentiment tracker',
                'inspiration' => 'Sentiment analysis displays',
                'best_for' => array('sentiment', 'market_mood', 'investor_confidence'),
                'design_elements' => array(
                    'background' => '#0a0e27',
                    'bullish_color' => '#10b981',
                    'bearish_color' => '#ef4444',
                    'neutral_color' => '#6b7280',
                    'accent_color' => '#d4af37',
                    'typography' => 'Inter, sans-serif',
                    'layout' => 'sentiment gauge'
                )
            ),
            
            // === PORTFOLIO CARDS ===
            
            'portfolio_snapshot_card' => array(
                'name' => 'Portfolio Command View',
                'style' => 'Portfolio overview display',
                'inspiration' => 'Wealth management platforms',
                'best_for' => array('portfolio', 'holdings', 'allocation'),
                'design_elements' => array(
                    'background' => 'white',
                    'primary_color' => '#1f2937',
                    'accent_color' => '#d4af37',
                    'allocation_colors' => array('#3b82f6', '#10b981', '#f59e0b', '#8b5cf6'),
                    'typography' => 'Inter, sans-serif',
                    'layout' => 'portfolio breakdown'
                )
            ),
            
            // === ALERT & NOTIFICATION CARDS ===
            
            'market_alert_card' => array(
                'name' => 'Market Alert Bulletin',
                'style' => 'Breaking news alert',
                'inspiration' => 'News flash alerts',
                'best_for' => array('breaking_news', 'alerts', 'urgent'),
                'design_elements' => array(
                    'background' => 'linear-gradient(135deg, #fef3c7 0%, #fed7aa 100%)',
                    'primary_color' => '#78350f',
                    'alert_color' => '#dc2626',
                    'accent_color' => '#d4af37',
                    'typography' => 'Inter, sans-serif',
                    'layout' => 'alert banner'
                )
            )
        );
    }
    
    /**
     * Select best card using advanced pattern matching
     */
    public function select_best_card($query, $entities = array(), $context = array()) {
        // Use advanced pattern matcher
        $pattern_matches = $this->advanced_pattern_matcher->match_patterns($query, $entities, $context);
        
        if (empty($pattern_matches)) {
            // Fallback to default card
            return array(
                'card' => 'markets_daily_card',
                'confidence' => 50,
                'reason' => 'Default fallback'
            );
        }
        
        // Get best match
        $best_match = $pattern_matches[0];
        
        // Verify card exists in expanded registry
        if (isset($this->expanded_card_registry[$best_match['card']])) {
            return array(
                'card' => $best_match['card'],
                'confidence' => $best_match['confidence'],
                'pattern' => $best_match['pattern'],
                'reason' => $this->advanced_pattern_matcher->explain_match($best_match)
            );
        }
        
        // Fallback if card not found
        return array(
            'card' => 'markets_daily_card',
            'confidence' => 60,
            'reason' => 'Pattern matched but card not found, using fallback'
        );
    }
    
    /**
     * Render premium visual card
     */
    public function render_premium_card($card_id, $data = array()) {
        if (!isset($this->expanded_card_registry[$card_id])) {
            return $this->render_fallback_card($data);
        }
        
        $card_config = $this->expanded_card_registry[$card_id];
        $design = $card_config['design_elements'];
        
        // Build HTML with inline styles for premium look
        $html = '<div class="sffc-premium-card sffc-' . $card_id . '" style="';
        
        // Apply background
        if (isset($design['background'])) {
            if (strpos($design['background'], 'gradient') !== false) {
                $html .= 'background: ' . $design['background'] . ';';
            } else {
                $html .= 'background-color: ' . $design['background'] . ';';
            }
        }
        
        // Apply colors
        $html .= 'color: ' . ($design['primary_color'] ?? '#2c1810') . ';';
        $html .= 'border: 2px solid ' . ($design['accent_color'] ?? '#d4af37') . ';';
        $html .= 'border-radius: 8px; padding: 0; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">';
        
        // Card header with publication name
        $html .= '<header style="background: linear-gradient(90deg, ' . ($design['accent_color'] ?? '#d4af37') . ', ' . $this->darken_color($design['accent_color'] ?? '#d4af37', 20) . ');';
        $html .= 'padding: 15px 25px; border-bottom: 2px solid ' . ($design['accent_color'] ?? '#d4af37') . ';">';
        $html .= '<div style="display: flex; justify-content: space-between; align-items: center;">';
        $html .= '<span style="font-family: ' . ($design['typography'] ?? 'serif') . '; font-size: 20px; font-weight: bold; letter-spacing: 2px; color: ' . ($design['background'] === '#1a1a1a' ? '#1a1a1a' : 'white') . ';">' . strtoupper($card_config['name']) . '</span>';
        $html .= '<span style="font-size: 12px; color: ' . ($design['background'] === '#1a1a1a' ? '#2c1810' : 'white') . ';">' . date('M j, Y | H:i') . '</span>';
        $html .= '</div>';
        $html .= '</header>';
        
        // Card body
        $html .= '<div class="card-body" style="padding: 30px;">';
        
        // Render based on card type
        switch ($card_id) {
            case 'bloomberg_terminal_card':
                $html .= $this->render_terminal_body($data, $design);
                break;
            case 'comparison_matrix_card':
                $html .= $this->render_comparison_body($data, $design);
                break;
            case 'strategy_choice_card':
                $html .= $this->render_strategy_choices($data, $design);
                break;
            case 'market_heatmap_card':
                $html .= $this->render_heatmap_body($data, $design);
                break;
            default:
                $html .= $this->render_standard_body($data, $design);
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return array(
            'html' => $html,
            'card_type' => $card_id,
            'card_name' => $card_config['name'],
            'style' => $card_config['style']
        );
    }
    
    /**
     * Render terminal body with cream/gold theme
     */
    private function render_terminal_body($data, $design) {
        $html = '<div style="background: #1a1a1a; padding: 20px; border-radius: 4px;">';
        
        // Ticker and price
        $html .= '<div style="display: flex; align-items: baseline; gap: 30px; margin-bottom: 20px;">';
        $html .= '<span style="color: #d4af37; font-size: 32px; font-weight: bold; font-family: monospace;">' . ($data['ticker'] ?? 'N/A') . '</span>';
        $html .= '<span style="color: #fef9e7; font-size: 48px; font-weight: bold; font-family: monospace;">' . ($data['price'] ?? '0.00') . '</span>';
        
        $change = $data['change'] ?? 0;
        $change_color = $change >= 0 ? '#4ade80' : '#f87171';
        $html .= '<span style="color: ' . $change_color . '; font-size: 24px; font-family: monospace;">';
        $html .= ($change >= 0 ? '+' : '') . $change . '%</span>';
        $html .= '</div>';
        
        // Market depth
        $html .= '<div style="background: rgba(254, 249, 231, 0.05); padding: 15px; border-radius: 4px;">';
        $html .= '<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; color: #fef9e7; font-family: monospace;">';
        $html .= '<div>BID: <span style="color: #d4af37;">' . ($data['bid'] ?? '0.00') . '</span></div>';
        $html .= '<div>ASK: <span style="color: #d4af37;">' . ($data['ask'] ?? '0.00') . '</span></div>';
        $html .= '<div>VOL: <span style="color: #d4af37;">' . ($data['volume'] ?? '0') . '</span></div>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Render comparison body
     */
    private function render_comparison_body($data, $design) {
        $html = '<div style="overflow-x: auto;">';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<thead>';
        $html .= '<tr style="background: ' . ($design['accent_color'] ?? '#d4af37') . '; color: white;">';
        $html .= '<th style="padding: 12px; text-align: left;">Metric</th>';
        $html .= '<th style="padding: 12px; text-align: center;">' . ($data['company1'] ?? 'Company A') . '</th>';
        $html .= '<th style="padding: 12px; text-align: center;">' . ($data['company2'] ?? 'Company B') . '</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        $metrics = array(
            'Price' => array($data['price1'] ?? 'N/A', $data['price2'] ?? 'N/A'),
            'Market Cap' => array($data['cap1'] ?? 'N/A', $data['cap2'] ?? 'N/A'),
            'P/E Ratio' => array($data['pe1'] ?? 'N/A', $data['pe2'] ?? 'N/A'),
            'Dividend' => array($data['div1'] ?? 'N/A', $data['div2'] ?? 'N/A')
        );
        
        foreach ($metrics as $metric => $values) {
            $html .= '<tr style="border-bottom: 1px solid #e5e7eb;">';
            $html .= '<td style="padding: 10px; font-weight: bold;">' . $metric . '</td>';
            $html .= '<td style="padding: 10px; text-align: center;">' . $values[0] . '</td>';
            $html .= '<td style="padding: 10px; text-align: center;">' . $values[1] . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Render strategy choices
     */
    private function render_strategy_choices($data, $design) {
        $html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">';
        
        $strategies = array(
            array('title' => 'Conservative', 'icon' => '🛡', 'desc' => 'Lower risk, stable returns'),
            array('title' => 'Balanced', 'icon' => '⚖', 'desc' => 'Mixed risk and reward'),
            array('title' => 'Aggressive', 'icon' => '🚀', 'desc' => 'Higher risk, higher potential')
        );
        
        foreach ($strategies as $strategy) {
            $html .= '<div style="background: white; border: 2px solid ' . ($design['accent_color'] ?? '#d4af37') . '; ';
            $html .= 'border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s;">';
            $html .= '<div style="font-size: 36px; margin-bottom: 10px;">' . $strategy['icon'] . '</div>';
            $html .= '<h4 style="margin-bottom: 8px; color: ' . ($design['primary_color'] ?? '#2c1810') . ';">' . $strategy['title'] . '</h4>';
            $html .= '<p style="color: #6b7280; font-size: 14px;">' . $strategy['desc'] . '</p>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Render heatmap body
     */
    private function render_heatmap_body($data, $design) {
        $html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 8px;">';
        
        $sectors = $data['sectors'] ?? array(
            'Technology' => 2.3,
            'Finance' => -0.5,
            'Healthcare' => 1.2,
            'Energy' => -1.8,
            'Consumer' => 0.8,
            'Industrial' => -0.3
        );
        
        foreach ($sectors as $sector => $change) {
            $color = $change > 1 ? '#10b981' : ($change > 0 ? '#84cc16' : ($change > -1 ? '#eab308' : '#ef4444'));
            $html .= '<div style="background: ' . $color . '; color: white; padding: 20px 10px; text-align: center; border-radius: 4px;">';
            $html .= '<div style="font-weight: bold; margin-bottom: 5px;">' . $sector . '</div>';
            $html .= '<div style="font-size: 20px;">' . ($change >= 0 ? '+' : '') . $change . '%</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Render standard body
     */
    private function render_standard_body($data, $design) {
        $html = '<h2 style="font-family: ' . ($design['typography'] ?? 'serif') . '; margin-bottom: 15px; color: ' . ($design['primary_color'] ?? '#2c1810') . ';">';
        $html .= $data['headline'] ?? 'Financial Intelligence Update';
        $html .= '</h2>';
        
        if (isset($data['subtitle'])) {
            $html .= '<p style="font-size: 18px; color: #6b7280; margin-bottom: 20px; font-style: italic;">' . $data['subtitle'] . '</p>';
        }
        
        $html .= '<div style="line-height: 1.8; color: ' . ($design['primary_color'] ?? '#2c1810') . ';">';
        $html .= $data['content'] ?? $data['body_content'] ?? 'Premium financial intelligence and market insights.';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render fallback card
     */
    private function render_fallback_card($data) {
        $html = '<div class="sffc-fallback-card" style="background: linear-gradient(135deg, #fef9e7 0%, white 100%); ';
        $html .= 'border: 2px solid #d4af37; border-radius: 8px; padding: 30px;">';
        $html .= '<h3 style="color: #2c1810; margin-bottom: 15px;">Financial Intelligence</h3>';
        $html .= '<p style="color: #3d261a; line-height: 1.6;">' . ($data['content'] ?? 'Market intelligence and insights.') . '</p>';
        $html .= '</div>';
        
        return array(
            'html' => $html,
            'card_type' => 'fallback',
            'card_name' => 'Default Card'
        );
    }
    
    /**
     * Helper to darken color
     */
    private function darken_color($hex, $percent) {
        $hex = str_replace('#', '', $hex);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = max(0, min(255, $r - ($r * $percent / 100)));
        $g = max(0, min(255, $g - ($g * $percent / 100)));
        $b = max(0, min(255, $b - ($b * $percent / 100)));
        
        return '#' . sprintf('%02x%02x%02x', $r, $g, $b);
    }
}