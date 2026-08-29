<?php
/**
 * Real Data Response Manager
 * Phase 1: Generates responses using real cached data
 * 
 * @package SennaCareers
 * @since 6.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Real_Data_Response_Manager {
    
    private static $instance = null;
    private $data_cache;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Load data cache manager
        if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-data-cache-manager.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-data-cache-manager.php';
            $this->data_cache = SFFC_Data_Cache_Manager::get_instance();
        }
    }
    
    /**
     * Generate market response with real data
     */
    public function generate_market_response($query_type, $context = array()) {
        if (!$this->data_cache) {
            return $this->get_fallback_response();
        }
        
        // Get real market data
        $market_data = $this->data_cache->get_market_data('all');
        $news = $this->data_cache->get_relevant_news(array('limit' => 3));
        
        switch ($query_type) {
            case 'market_overview':
                return $this->format_market_overview($market_data, $news);
                
            case 'sector_performance':
                return $this->format_sector_performance($market_data);
                
            case 'market_movers':
                return $this->format_market_movers($market_data, $news);
                
            case 'volatility_status':
                return $this->format_volatility_status($market_data);
                
            case 'news_summary':
                return $this->format_news_summary($news);
                
            default:
                return $this->format_general_market_update($market_data, $news);
        }
    }
    
    /**
     * Format market overview with real data
     */
    private function format_market_overview($market_data, $news) {
        $response = array();
        
        // Market trend summary
        $trend = $market_data['summary']['trend'];
        $volatility = $market_data['summary']['volatility'];
        
        if ($trend === 'bullish') {
            $response[] = "Markets are trading higher today with positive momentum across major indices.";
        } elseif ($trend === 'bearish') {
            $response[] = "Markets are under pressure with broad-based selling across indices.";
        } else {
            $response[] = "Markets are mixed with indices showing divergent performance.";
        }
        
        // Index performance with real data
        if (!empty($market_data['indices'])) {
            $index_summary = array();
            foreach ($market_data['indices'] as $index) {
                $direction = $index['change_percent'] >= 0 ? 'up' : 'down';
                $index_summary[] = sprintf(
                    "%s %s %.2f%% at %.2f",
                    $index['name'],
                    $direction,
                    abs($index['change_percent']),
                    $index['value']
                );
            }
            
            if (!empty($index_summary)) {
                $response[] = "Key indices: " . implode(', ', array_slice($index_summary, 0, 3)) . ".";
            }
        }
        
        // Sector highlights
        if (!empty($market_data['sectors'])) {
            $top_sector = $market_data['sectors'][0];
            $bottom_sector = end($market_data['sectors']);
            
            $response[] = sprintf(
                "Sector rotation shows %s leading (+%.2f%%) while %s lags (%.2f%%).",
                $top_sector['name'],
                $top_sector['change_percent'],
                $bottom_sector['name'],
                $bottom_sector['change_percent']
            );
        }
        
        // Recent news context
        if (!empty($news)) {
            $latest = $news[0];
            $response[] = sprintf(
                "Market focus: %s (%s, %s).",
                $latest['headline'],
                $latest['source'],
                $latest['published']
            );
        }
        
        // Volatility context
        if ($volatility === 'high') {
            $response[] = "Elevated volatility suggests caution with wider trading ranges expected.";
        } elseif ($volatility === 'low') {
            $response[] = "Low volatility environment favors steady accumulation strategies.";
        }
        
        return implode(' ', $response);
    }
    
    /**
     * Format sector performance with real data
     */
    private function format_sector_performance($market_data) {
        if (empty($market_data['sectors'])) {
            return "Sector performance data is currently being updated. Please check back shortly.";
        }
        
        $response = array();
        $response[] = "Sector Performance Analysis:";
        
        // Sort sectors by performance
        $sectors = $market_data['sectors'];
        usort($sectors, function($a, $b) {
            return $b['change_percent'] <=> $a['change_percent'];
        });
        
        // Top performers
        $leaders = array_slice($sectors, 0, 3);
        $leader_text = array();
        foreach ($leaders as $sector) {
            $leader_text[] = sprintf("%s (+%.2f%%)", $sector['name'], $sector['change_percent']);
        }
        $response[] = "Leading sectors: " . implode(', ', $leader_text) . ".";
        
        // Bottom performers
        $laggards = array_slice($sectors, -3);
        $laggard_text = array();
        foreach ($laggards as $sector) {
            $laggard_text[] = sprintf("%s (%.2f%%)", $sector['name'], $sector['change_percent']);
        }
        $response[] = "Lagging sectors: " . implode(', ', $laggard_text) . ".";
        
        // Rotation insight
        if ($leaders[0]['name'] === 'Technology' && $laggards[0]['name'] === 'Utilities') {
            $response[] = "Risk-on rotation evident with growth sectors outperforming defensives.";
        } elseif ($leaders[0]['name'] === 'Utilities' && $laggards[0]['name'] === 'Technology') {
            $response[] = "Defensive rotation suggests risk-off sentiment in the market.";
        }
        
        return implode(' ', $response);
    }
    
    /**
     * Format market movers with real data
     */
    private function format_market_movers($market_data, $news) {
        $response = array();
        $response[] = "Today's Market Movers:";
        
        // Index movers
        if (!empty($market_data['indices'])) {
            $big_moves = array();
            foreach ($market_data['indices'] as $index) {
                if (abs($index['change_percent']) > 1) {
                    $direction = $index['change_percent'] > 0 ? 'surging' : 'declining';
                    $big_moves[] = sprintf(
                        "%s %s %.2f%%",
                        $index['name'],
                        $direction,
                        abs($index['change_percent'])
                    );
                }
            }
            
            if (!empty($big_moves)) {
                $response[] = "Notable moves: " . implode(', ', $big_moves) . ".";
            }
        }
        
        // Volume insights
        $total_volume = 0;
        foreach ($market_data['indices'] as $index) {
            $total_volume += $index['volume'];
        }
        
        if ($total_volume > 0) {
            $volume_context = $total_volume > 4000000000 ? "above average" : "below average";
            $response[] = "Trading volume is " . $volume_context . " suggesting " . 
                         ($volume_context === "above average" ? "strong conviction" : "lighter participation") . ".";
        }
        
        // News-driven moves
        if (!empty($news)) {
            foreach ($news as $item) {
                if ($item['importance'] >= 7) {
                    $response[] = sprintf(
                        "Key driver: %s impacting %s sentiment.",
                        $item['headline'],
                        $item['sentiment']
                    );
                    break;
                }
            }
        }
        
        return implode(' ', $response);
    }
    
    /**
     * Format volatility status with real data
     */
    private function format_volatility_status($market_data) {
        $response = array();
        
        // Check for VIX data
        $vix_value = null;
        if (!empty($market_data['indices'])) {
            foreach ($market_data['indices'] as $index) {
                if ($index['symbol'] === 'VIX') {
                    $vix_value = $index['value'];
                    break;
                }
            }
        }
        
        if ($vix_value) {
            $response[] = sprintf("Market volatility (VIX) at %.2f ", $vix_value);
            
            if ($vix_value < 15) {
                $response[] = "indicates low volatility and complacency. Consider protection strategies.";
            } elseif ($vix_value < 20) {
                $response[] = "shows normal market conditions with balanced risk.";
            } elseif ($vix_value < 30) {
                $response[] = "reflects elevated uncertainty. Position sizing and risk management crucial.";
            } else {
                $response[] = "signals extreme fear. Opportunities may emerge for contrarian positioning.";
            }
        } else {
            // Use market breadth as proxy
            $volatility = $market_data['summary']['volatility'];
            if ($volatility === 'high') {
                $response[] = "Market volatility is elevated with wider intraday swings and increased uncertainty.";
            } elseif ($volatility === 'low') {
                $response[] = "Volatility remains subdued suggesting steady market conditions.";
            } else {
                $response[] = "Volatility at normal levels with typical trading ranges.";
            }
        }
        
        // Add context
        $response[] = "Monitor position sizes and maintain appropriate hedges.";
        
        return implode(' ', $response);
    }
    
    /**
     * Format news summary with real data
     */
    private function format_news_summary($news) {
        if (empty($news)) {
            return "Markets await fresh catalysts with no major news flow currently impacting sentiment.";
        }
        
        $response = array();
        $response[] = "Key Market Developments:";
        
        foreach ($news as $i => $item) {
            $response[] = sprintf(
                "%d. %s - %s (%s, %s)",
                $i + 1,
                $item['headline'],
                $item['summary'],
                $item['source'],
                $item['published']
            );
            
            // Add entity context if available
            if (!empty($item['entities'])) {
                $response[] = "Focus: " . implode(', ', $item['entities']) . ".";
            }
        }
        
        // Overall sentiment
        $positive = 0;
        $negative = 0;
        foreach ($news as $item) {
            if ($item['sentiment'] === 'positive') $positive++;
            if ($item['sentiment'] === 'negative') $negative++;
        }
        
        if ($positive > $negative) {
            $response[] = "Overall news flow skews positive supporting risk assets.";
        } elseif ($negative > $positive) {
            $response[] = "Negative news flow may weigh on sentiment near-term.";
        }
        
        return implode(' ', $response);
    }
    
    /**
     * Format general market update
     */
    private function format_general_market_update($market_data, $news) {
        // Combine overview elements
        $overview = $this->format_market_overview($market_data, $news);
        
        // Add pre-computed analysis if available
        $analysis = $this->data_cache->get_analysis('market_summary', 'daily');
        if ($analysis) {
            return $overview . " " . $analysis['content'];
        }
        
        return $overview;
    }
    
    /**
     * Get fallback response when no data available
     */
    private function get_fallback_response() {
        return "Market data is currently being updated. Our real-time feed aggregator refreshes every 15 minutes to provide accurate market insights. Please check back shortly for the latest market conditions.";
    }
    
    /**
     * Generate PE/Finance response with real data
     */
    public function generate_finance_response($query_type, $context = array()) {
        // Get relevant news and intelligence
        $pe_news = $this->data_cache->get_relevant_news(array(
            'category' => 'pe_deals',
            'limit' => 5
        ));
        
        $ma_news = $this->data_cache->get_relevant_news(array(
            'category' => 'ma',
            'limit' => 5
        ));
        
        switch ($query_type) {
            case 'pe_activity':
                return $this->format_pe_activity($pe_news);
                
            case 'ma_deals':
                return $this->format_ma_activity($ma_news);
                
            case 'opportunities':
                return $this->format_opportunities();
                
            default:
                return $this->format_general_finance_update($pe_news, $ma_news);
        }
    }
    
    /**
     * Format PE activity with real data
     */
    private function format_pe_activity($pe_news) {
        if (empty($pe_news)) {
            return "Private equity markets remain selective with focus on quality assets and operational improvements. Deal flow continues at measured pace awaiting clearer market direction.";
        }
        
        $response = array();
        $response[] = "Recent Private Equity Activity:";
        
        foreach (array_slice($pe_news, 0, 3) as $deal) {
            $response[] = "• " . $deal['headline'];
            if (!empty($deal['entities'])) {
                $response[] = "  Involving: " . implode(', ', $deal['entities']);
            }
        }
        
        // Add context
        $response[] = "PE firms maintain disciplined approach with focus on sectors showing resilient growth prospects.";
        
        return implode(' ', $response);
    }
    
    /**
     * Format M&A activity
     */
    private function format_ma_activity($ma_news) {
        if (empty($ma_news)) {
            return "M&A activity remains subdued as companies focus on organic growth and operational efficiency in current environment.";
        }
        
        $response = array();
        $response[] = "Merger & Acquisition Highlights:";
        
        foreach (array_slice($ma_news, 0, 3) as $deal) {
            $response[] = "• " . $deal['headline'] . " (" . $deal['published'] . ")";
        }
        
        return implode(' ', $response);
    }
    
    /**
     * Format opportunities
     */
    private function format_opportunities() {
        $analysis = $this->data_cache->get_analysis('opportunities', 'current');
        
        if ($analysis) {
            return $analysis['content'];
        }
        
        return "Current market conditions create selective opportunities in distressed assets, growth equity in resilient sectors, and consolidation plays in fragmented industries. Focus on companies with strong cash flows and competitive moats.";
    }
    
    /**
     * Format general finance update
     */
    private function format_general_finance_update($pe_news, $ma_news) {
        $response = array();
        
        $total_deals = count($pe_news) + count($ma_news);
        
        if ($total_deals > 5) {
            $response[] = "Deal activity shows healthy momentum with " . $total_deals . " transactions recently announced.";
        } else {
            $response[] = "Deal flow remains measured as firms await better entry points.";
        }
        
        // Add specific examples if available
        if (!empty($pe_news)) {
            $latest_pe = $pe_news[0];
            $response[] = "Latest PE: " . $latest_pe['headline'] . ".";
        }
        
        if (!empty($ma_news)) {
            $latest_ma = $ma_news[0];
            $response[] = "Recent M&A: " . $latest_ma['headline'] . ".";
        }
        
        $response[] = "Focus remains on quality assets with clear value creation paths.";
        
        return implode(' ', $response);
    }
}