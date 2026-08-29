<?php
/**
 * Visual Data Generator - Phase 6
 * Generates charts, tables, cards and other visual data representations
 * 
 * @package SennaCareers
 * @since 6.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Visual_Data_Generator {
    
    private static $instance = null;
    private $chart_types = array();
    private $card_templates = array();
    private $color_schemes = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->initialize_chart_types();
        $this->initialize_card_templates();
        $this->initialize_color_schemes();
    }
    
    /**
     * Initialize available chart types and their configurations
     */
    private function initialize_chart_types() {
        $this->chart_types = array(
            'price_chart' => array(
                'type' => 'line',
                'library' => 'chart.js',
                'options' => array(
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'scales' => array(
                        'y' => array(
                            'beginAtZero' => false,
                            'grid' => array('color' => 'rgba(255, 255, 255, 0.1)')
                        ),
                        'x' => array(
                            'grid' => array('color' => 'rgba(255, 255, 255, 0.1)')
                        )
                    ),
                    'plugins' => array(
                        'legend' => array('display' => true),
                        'tooltip' => array('enabled' => true)
                    )
                )
            ),
            'comparison_chart' => array(
                'type' => 'bar',
                'library' => 'chart.js',
                'options' => array(
                    'responsive' => true,
                    'plugins' => array(
                        'legend' => array('position' => 'top'),
                        'title' => array('display' => true)
                    )
                )
            ),
            'performance_chart' => array(
                'type' => 'doughnut',
                'library' => 'chart.js',
                'options' => array(
                    'responsive' => true,
                    'plugins' => array(
                        'legend' => array('position' => 'bottom')
                    )
                )
            ),
            'heatmap' => array(
                'type' => 'custom',
                'library' => 'custom',
                'options' => array(
                    'cellSize' => 50,
                    'colorScale' => array('#e74c3c', '#f39c12', '#2ecc71')
                )
            ),
            'candlestick' => array(
                'type' => 'candlestick',
                'library' => 'custom',
                'options' => array(
                    'wickColor' => '#8884d8',
                    'bullish' => '#2ecc71',
                    'bearish' => '#e74c3c'
                )
            )
        );
    }
    
    /**
     * Initialize card templates for different data types
     */
    private function initialize_card_templates() {
        $this->card_templates = array(
            'company_snapshot' => array(
                'layout' => 'vertical',
                'sections' => array('header', 'metrics', 'footer'),
                'fields' => array(
                    'company_name' => array('position' => 'header', 'style' => 'h3'),
                    'ticker' => array('position' => 'header', 'style' => 'badge'),
                    'price' => array('position' => 'metrics', 'style' => 'large'),
                    'change' => array('position' => 'metrics', 'style' => 'change-indicator'),
                    'volume' => array('position' => 'metrics', 'style' => 'small'),
                    'market_cap' => array('position' => 'footer', 'style' => 'info')
                )
            ),
            'deal_announcement' => array(
                'layout' => 'horizontal',
                'sections' => array('icon', 'content', 'action'),
                'fields' => array(
                    'deal_type' => array('position' => 'icon', 'style' => 'icon'),
                    'headline' => array('position' => 'content', 'style' => 'h4'),
                    'parties' => array('position' => 'content', 'style' => 'subtitle'),
                    'value' => array('position' => 'content', 'style' => 'highlight'),
                    'date' => array('position' => 'content', 'style' => 'timestamp'),
                    'link' => array('position' => 'action', 'style' => 'button')
                )
            ),
            'market_summary' => array(
                'layout' => 'grid',
                'sections' => array('indices', 'movers', 'sectors'),
                'fields' => array(
                    'sp500' => array('position' => 'indices', 'style' => 'index-card'),
                    'nasdaq' => array('position' => 'indices', 'style' => 'index-card'),
                    'dow' => array('position' => 'indices', 'style' => 'index-card'),
                    'top_gainers' => array('position' => 'movers', 'style' => 'list'),
                    'top_losers' => array('position' => 'movers', 'style' => 'list'),
                    'sector_performance' => array('position' => 'sectors', 'style' => 'heatmap')
                )
            ),
            'news_card' => array(
                'layout' => 'media',
                'sections' => array('image', 'content', 'meta'),
                'fields' => array(
                    'thumbnail' => array('position' => 'image', 'style' => 'cover'),
                    'headline' => array('position' => 'content', 'style' => 'h4'),
                    'summary' => array('position' => 'content', 'style' => 'text'),
                    'source' => array('position' => 'meta', 'style' => 'small'),
                    'time' => array('position' => 'meta', 'style' => 'timestamp'),
                    'tags' => array('position' => 'meta', 'style' => 'tags')
                )
            ),
            'pe_infographic' => array(
                'layout' => 'infographic',
                'sections' => array('title', 'stats', 'process', 'key_players'),
                'fields' => array(
                    'title' => array('position' => 'title', 'style' => 'h2'),
                    'aum' => array('position' => 'stats', 'style' => 'big-number'),
                    'dry_powder' => array('position' => 'stats', 'style' => 'big-number'),
                    'avg_returns' => array('position' => 'stats', 'style' => 'percentage'),
                    'process_steps' => array('position' => 'process', 'style' => 'flow-chart'),
                    'top_firms' => array('position' => 'key_players', 'style' => 'logo-grid')
                )
            )
        );
    }
    
    /**
     * Initialize color schemes for different contexts
     */
    private function initialize_color_schemes() {
        $this->color_schemes = array(
            'default' => array(
                'primary' => '#1a1f36',
                'secondary' => '#4361ee',
                'success' => '#2ecc71',
                'danger' => '#e74c3c',
                'warning' => '#f39c12',
                'info' => '#3498db',
                'light' => '#ecf0f1',
                'dark' => '#2c3e50'
            ),
            'market' => array(
                'bullish' => '#2ecc71',
                'bearish' => '#e74c3c',
                'neutral' => '#95a5a6',
                'volume' => '#3498db',
                'grid' => 'rgba(255, 255, 255, 0.1)'
            ),
            'pe_theme' => array(
                'primary' => '#1e3a8a',
                'accent' => '#f59e0b',
                'background' => '#111827',
                'text' => '#f3f4f6',
                'highlight' => '#10b981'
            )
        );
    }
    
    /**
     * Generate visual based on data and type
     */
    public function generate_visual($data, $visual_type, $options = array()) {
        switch ($visual_type) {
            case 'price_chart':
                return $this->generate_price_chart($data, $options);
                
            case 'comparison_chart':
            case 'comparison_table':
                return $this->generate_comparison_visual($data, $options);
                
            case 'company_card':
            case 'company_snapshot':
                return $this->generate_company_card($data, $options);
                
            case 'news_feed':
            case 'news_cards':
                return $this->generate_news_cards($data, $options);
                
            case 'pe_infographic':
                return $this->generate_pe_infographic($data, $options);
                
            case 'market_dashboard':
            case 'analysis_dashboard':
                return $this->generate_dashboard($data, $options);
                
            case 'performance_chart':
                return $this->generate_performance_chart($data, $options);
                
            case 'sector_heatmap':
                return $this->generate_heatmap($data, $options);
                
            default:
                return $this->generate_default_visual($data, $options);
        }
    }
    
    /**
     * Generate price chart
     */
    private function generate_price_chart($data, $options = array()) {
        $chart_config = $this->chart_types['price_chart'];
        
        // Prepare chart data
        $chart_data = array(
            'type' => $chart_config['type'],
            'data' => array(
                'labels' => $this->generate_time_labels($data),
                'datasets' => array(
                    array(
                        'label' => $data['company'] ?? 'Stock Price',
                        'data' => $data['prices'] ?? array(),
                        'borderColor' => $this->color_schemes['default']['primary'],
                        'backgroundColor' => 'rgba(26, 31, 54, 0.1)',
                        'tension' => 0.4
                    )
                )
            ),
            'options' => array_merge($chart_config['options'], $options)
        );
        
        // Add volume bars if available
        if (isset($data['volumes'])) {
            $chart_data['data']['datasets'][] = array(
                'label' => 'Volume',
                'type' => 'bar',
                'data' => $data['volumes'],
                'backgroundColor' => 'rgba(52, 152, 219, 0.3)',
                'yAxisID' => 'y1'
            );
        }
        
        return array(
            'type' => 'chart',
            'config' => $chart_data,
            'html' => $this->render_chart_html($chart_data),
            'requires' => array('chart.js')
        );
    }
    
    /**
     * Generate comparison visual (chart or table)
     */
    private function generate_comparison_visual($data, $options = array()) {
        $use_table = isset($options['format']) && $options['format'] === 'table';
        
        if ($use_table) {
            return $this->generate_comparison_table($data, $options);
        }
        
        $chart_config = $this->chart_types['comparison_chart'];
        
        $companies = array_keys($data['companies'] ?? array());
        $metrics = array();
        
        // Extract metrics for comparison
        foreach ($data['companies'] as $company => $info) {
            $metrics['price'][] = $info['price'] ?? 0;
            $metrics['change'][] = $info['change'] ?? 0;
            $metrics['pe_ratio'][] = $info['pe_ratio'] ?? 0;
        }
        
        $chart_data = array(
            'type' => $chart_config['type'],
            'data' => array(
                'labels' => $companies,
                'datasets' => array(
                    array(
                        'label' => 'Stock Price',
                        'data' => $metrics['price'],
                        'backgroundColor' => $this->color_schemes['default']['primary']
                    ),
                    array(
                        'label' => 'Change %',
                        'data' => $metrics['change'],
                        'backgroundColor' => $this->color_schemes['default']['secondary']
                    )
                )
            ),
            'options' => array_merge($chart_config['options'], $options)
        );
        
        return array(
            'type' => 'chart',
            'config' => $chart_data,
            'html' => $this->render_chart_html($chart_data),
            'requires' => array('chart.js')
        );
    }
    
    /**
     * Generate comparison table
     */
    private function generate_comparison_table($data, $options = array()) {
        $table_html = '<div class="sffc-comparison-table">';
        $table_html .= '<table class="sffc-data-table">';
        
        // Header
        $table_html .= '<thead><tr>';
        $table_html .= '<th>Company</th>';
        $metrics = array('Price', 'Change', 'Volume', 'Market Cap', 'P/E Ratio');
        foreach ($metrics as $metric) {
            $table_html .= '<th>' . $metric . '</th>';
        }
        $table_html .= '</tr></thead>';
        
        // Body
        $table_html .= '<tbody>';
        foreach ($data['companies'] as $company => $info) {
            $table_html .= '<tr>';
            $table_html .= '<td class="company-name">' . esc_html($company) . '</td>';
            $table_html .= '<td class="price">' . $this->format_price($info['price'] ?? 0) . '</td>';
            $table_html .= '<td class="change ' . ($info['change'] >= 0 ? 'positive' : 'negative') . '">';
            $table_html .= $this->format_change($info['change'] ?? 0) . '</td>';
            $table_html .= '<td class="volume">' . $this->format_volume($info['volume'] ?? 0) . '</td>';
            $table_html .= '<td class="market-cap">' . $this->format_market_cap($info['market_cap'] ?? 0) . '</td>';
            $table_html .= '<td class="pe-ratio">' . number_format($info['pe_ratio'] ?? 0, 2) . '</td>';
            $table_html .= '</tr>';
        }
        $table_html .= '</tbody>';
        
        $table_html .= '</table>';
        $table_html .= '</div>';
        
        return array(
            'type' => 'table',
            'html' => $table_html,
            'css_class' => 'sffc-comparison-table',
            'requires' => array()
        );
    }
    
    /**
     * Generate company card
     */
    private function generate_company_card($data, $options = array()) {
        $template = $this->card_templates['company_snapshot'];
        
        $card_html = '<div class="sffc-company-card">';
        
        // Header section
        $card_html .= '<div class="card-header">';
        $card_html .= '<h3>' . esc_html($data['company_name'] ?? 'Company') . '</h3>';
        $card_html .= '<span class="ticker-badge">' . esc_html($data['ticker'] ?? '') . '</span>';
        $card_html .= '</div>';
        
        // Metrics section
        $card_html .= '<div class="card-metrics">';
        $card_html .= '<div class="metric-primary">';
        $card_html .= '<span class="price">' . $this->format_price($data['price'] ?? 0) . '</span>';
        $change_class = ($data['change'] ?? 0) >= 0 ? 'positive' : 'negative';
        $card_html .= '<span class="change ' . $change_class . '">' . $this->format_change($data['change'] ?? 0) . '</span>';
        $card_html .= '</div>';
        
        if (isset($data['volume'])) {
            $card_html .= '<div class="metric-secondary">';
            $card_html .= '<label>Volume:</label> ' . $this->format_volume($data['volume']);
            $card_html .= '</div>';
        }
        
        if (isset($data['market_cap'])) {
            $card_html .= '<div class="metric-secondary">';
            $card_html .= '<label>Market Cap:</label> ' . $this->format_market_cap($data['market_cap']);
            $card_html .= '</div>';
        }
        $card_html .= '</div>';
        
        // Footer section
        if (isset($data['last_updated'])) {
            $card_html .= '<div class="card-footer">';
            $card_html .= '<small>Updated: ' . $this->format_time($data['last_updated']) . '</small>';
            $card_html .= '</div>';
        }
        
        $card_html .= '</div>';
        
        return array(
            'type' => 'card',
            'html' => $card_html,
            'css_class' => 'sffc-company-card',
            'requires' => array()
        );
    }
    
    /**
     * Generate news cards
     */
    private function generate_news_cards($data, $options = array()) {
        $cards_html = '<div class="sffc-news-cards">';
        
        $headlines = $data['headlines'] ?? array();
        $max_cards = $options['limit'] ?? 5;
        $count = 0;
        
        foreach ($headlines as $news) {
            if ($count >= $max_cards) break;
            
            $cards_html .= '<div class="news-card">';
            
            // Image if available
            if (isset($news['thumbnail'])) {
                $cards_html .= '<div class="news-image">';
                $cards_html .= '<img src="' . esc_url($news['thumbnail']) . '" alt="">';
                $cards_html .= '</div>';
            }
            
            // Content
            $cards_html .= '<div class="news-content">';
            $cards_html .= '<h4>' . esc_html($news['title'] ?? '') . '</h4>';
            
            if (isset($news['summary'])) {
                $cards_html .= '<p>' . esc_html(substr($news['summary'], 0, 150)) . '...</p>';
            }
            
            // Meta
            $cards_html .= '<div class="news-meta">';
            if (isset($news['source'])) {
                $cards_html .= '<span class="source">' . esc_html($news['source']) . '</span>';
            }
            if (isset($news['time'])) {
                $cards_html .= '<span class="time">' . esc_html($news['time']) . '</span>';
            }
            $cards_html .= '</div>';
            
            if (isset($news['link'])) {
                $cards_html .= '<a href="' . esc_url($news['link']) . '" class="read-more" target="_blank">Read More →</a>';
            }
            
            $cards_html .= '</div>';
            $cards_html .= '</div>';
            
            $count++;
        }
        
        $cards_html .= '</div>';
        
        return array(
            'type' => 'cards',
            'html' => $cards_html,
            'css_class' => 'sffc-news-cards',
            'requires' => array()
        );
    }
    
    /**
     * Generate PE infographic
     */
    private function generate_pe_infographic($data, $options = array()) {
        $template = $this->card_templates['pe_infographic'];
        
        $html = '<div class="sffc-pe-infographic">';
        
        // Title
        $html .= '<div class="infographic-header">';
        $html .= '<h2>Private Equity Overview</h2>';
        $html .= '</div>';
        
        // Key Stats
        $html .= '<div class="pe-stats">';
        $html .= '<div class="stat-card">';
        $html .= '<div class="stat-value">$4.2T</div>';
        $html .= '<div class="stat-label">Assets Under Management</div>';
        $html .= '</div>';
        $html .= '<div class="stat-card">';
        $html .= '<div class="stat-value">$3.7T</div>';
        $html .= '<div class="stat-label">Dry Powder</div>';
        $html .= '</div>';
        $html .= '<div class="stat-card">';
        $html .= '<div class="stat-value">15-25%</div>';
        $html .= '<div class="stat-label">Average Net IRR</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Process Flow
        $html .= '<div class="pe-process">';
        $html .= '<h3>The PE Process</h3>';
        $html .= '<div class="process-flow">';
        $steps = array('Fundraising', 'Deal Sourcing', 'Due Diligence', 'Acquisition', 'Value Creation', 'Exit');
        foreach ($steps as $index => $step) {
            $html .= '<div class="process-step">';
            $html .= '<div class="step-number">' . ($index + 1) . '</div>';
            $html .= '<div class="step-label">' . $step . '</div>';
            $html .= '</div>';
            if ($index < count($steps) - 1) {
                $html .= '<div class="step-arrow">→</div>';
            }
        }
        $html .= '</div>';
        $html .= '</div>';
        
        // Top Firms
        $html .= '<div class="pe-firms">';
        $html .= '<h3>Leading PE Firms</h3>';
        $html .= '<div class="firms-grid">';
        $firms = array('KKR', 'Blackstone', 'Apollo', 'Carlyle', 'TPG', 'Warburg Pincus');
        foreach ($firms as $firm) {
            $html .= '<div class="firm-tile">' . $firm . '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return array(
            'type' => 'infographic',
            'html' => $html,
            'css_class' => 'sffc-pe-infographic',
            'requires' => array('infographic.css')
        );
    }
    
    /**
     * Generate dashboard with multiple visuals
     */
    private function generate_dashboard($data, $options = array()) {
        $dashboard_html = '<div class="sffc-dashboard">';
        
        // Generate different sections based on data
        if (isset($data['price_data'])) {
            $chart = $this->generate_price_chart($data['price_data']);
            $dashboard_html .= '<div class="dashboard-section chart-section">' . $chart['html'] . '</div>';
        }
        
        if (isset($data['companies'])) {
            $comparison = $this->generate_comparison_visual($data);
            $dashboard_html .= '<div class="dashboard-section comparison-section">' . $comparison['html'] . '</div>';
        }
        
        if (isset($data['news'])) {
            $news = $this->generate_news_cards(array('headlines' => $data['news']), array('limit' => 3));
            $dashboard_html .= '<div class="dashboard-section news-section">' . $news['html'] . '</div>';
        }
        
        $dashboard_html .= '</div>';
        
        return array(
            'type' => 'dashboard',
            'html' => $dashboard_html,
            'css_class' => 'sffc-dashboard',
            'requires' => array('chart.js', 'dashboard.css')
        );
    }
    
    /**
     * Generate performance chart (doughnut/pie)
     */
    private function generate_performance_chart($data, $options = array()) {
        $chart_config = $this->chart_types['performance_chart'];
        
        $chart_data = array(
            'type' => $chart_config['type'],
            'data' => array(
                'labels' => array_keys($data['segments'] ?? array()),
                'datasets' => array(
                    array(
                        'data' => array_values($data['segments'] ?? array()),
                        'backgroundColor' => array(
                            $this->color_schemes['market']['bullish'],
                            $this->color_schemes['market']['bearish'],
                            $this->color_schemes['market']['neutral'],
                            $this->color_schemes['default']['info']
                        )
                    )
                )
            ),
            'options' => array_merge($chart_config['options'], $options)
        );
        
        return array(
            'type' => 'chart',
            'config' => $chart_data,
            'html' => $this->render_chart_html($chart_data),
            'requires' => array('chart.js')
        );
    }
    
    /**
     * Generate heatmap
     */
    private function generate_heatmap($data, $options = array()) {
        $html = '<div class="sffc-heatmap">';
        
        $sectors = $data['sectors'] ?? array();
        
        foreach ($sectors as $sector => $performance) {
            $color = $this->get_heatmap_color($performance);
            $html .= '<div class="heatmap-cell" style="background-color: ' . $color . ';">';
            $html .= '<div class="sector-name">' . esc_html($sector) . '</div>';
            $html .= '<div class="sector-value">' . $this->format_change($performance) . '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return array(
            'type' => 'heatmap',
            'html' => $html,
            'css_class' => 'sffc-heatmap',
            'requires' => array()
        );
    }
    
    /**
     * Generate default visual fallback
     */
    private function generate_default_visual($data, $options = array()) {
        $html = '<div class="sffc-data-display">';
        
        if (is_array($data)) {
            $html .= '<ul class="data-list">';
            foreach ($data as $key => $value) {
                $html .= '<li><strong>' . esc_html($key) . ':</strong> ' . esc_html($value) . '</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<p>' . esc_html($data) . '</p>';
        }
        
        $html .= '</div>';
        
        return array(
            'type' => 'default',
            'html' => $html,
            'css_class' => 'sffc-data-display',
            'requires' => array()
        );
    }
    
    /**
     * Render chart HTML container
     */
    private function render_chart_html($chart_data) {
        $chart_id = 'sffc-chart-' . uniqid();
        
        $html = '<div class="sffc-chart-container">';
        $html .= '<canvas id="' . $chart_id . '"></canvas>';
        $html .= '<script>';
        $html .= 'document.addEventListener("DOMContentLoaded", function() {';
        $html .= 'var ctx = document.getElementById("' . $chart_id . '").getContext("2d");';
        $html .= 'new Chart(ctx, ' . json_encode($chart_data) . ');';
        $html .= '});';
        $html .= '</script>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate time labels for charts
     */
    private function generate_time_labels($data) {
        if (isset($data['timestamps'])) {
            return array_map(function($ts) {
                return date('M d', $ts);
            }, $data['timestamps']);
        }
        
        // Generate default labels
        $labels = array();
        $days = isset($data['days']) ? $data['days'] : 7;
        for ($i = $days - 1; $i >= 0; $i--) {
            $labels[] = date('M d', strtotime("-$i days"));
        }
        return $labels;
    }
    
    /**
     * Format price for display
     */
    private function format_price($price) {
        if ($price > 1000) {
            return '$' . number_format($price, 0);
        }
        return '$' . number_format($price, 2);
    }
    
    /**
     * Format change percentage
     */
    private function format_change($change) {
        $symbol = $change >= 0 ? '+' : '';
        return $symbol . number_format($change, 2) . '%';
    }
    
    /**
     * Format volume
     */
    private function format_volume($volume) {
        if (is_string($volume)) {
            return $volume;
        }
        if ($volume > 1000000000) {
            return number_format($volume / 1000000000, 1) . 'B';
        }
        if ($volume > 1000000) {
            return number_format($volume / 1000000, 1) . 'M';
        }
        if ($volume > 1000) {
            return number_format($volume / 1000, 1) . 'K';
        }
        return number_format($volume);
    }
    
    /**
     * Format market cap
     */
    private function format_market_cap($cap) {
        if ($cap > 1000000000000) {
            return '$' . number_format($cap / 1000000000000, 2) . 'T';
        }
        if ($cap > 1000000000) {
            return '$' . number_format($cap / 1000000000, 2) . 'B';
        }
        if ($cap > 1000000) {
            return '$' . number_format($cap / 1000000, 2) . 'M';
        }
        return '$' . number_format($cap);
    }
    
    /**
     * Format timestamp
     */
    private function format_time($timestamp) {
        if (is_string($timestamp)) {
            return $timestamp;
        }
        $diff = time() - $timestamp;
        if ($diff < 3600) {
            return floor($diff / 60) . ' min ago';
        }
        if ($diff < 86400) {
            return floor($diff / 3600) . ' hours ago';
        }
        return date('M d, Y', $timestamp);
    }
    
    /**
     * Get heatmap color based on value
     */
    private function get_heatmap_color($value) {
        if ($value > 2) {
            return $this->color_schemes['market']['bullish'];
        }
        if ($value > 0) {
            return '#27ae60'; // Light green
        }
        if ($value > -2) {
            return $this->color_schemes['market']['neutral'];
        }
        return $this->color_schemes['market']['bearish'];
    }
    
    /**
     * Generate CSS for visuals
     */
    public function generate_css() {
        $css = '
        .sffc-chart-container { 
            position: relative; 
            height: 400px; 
            margin: 20px 0; 
        }
        .sffc-company-card {
            background: #1a1f36;
            border-radius: 8px;
            padding: 20px;
            color: #fff;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .sffc-company-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .sffc-company-card .ticker-badge {
            background: #4361ee;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .sffc-company-card .price {
            font-size: 28px;
            font-weight: bold;
        }
        .sffc-company-card .change.positive {
            color: #2ecc71;
        }
        .sffc-company-card .change.negative {
            color: #e74c3c;
        }
        .sffc-comparison-table {
            overflow-x: auto;
        }
        .sffc-comparison-table table {
            width: 100%;
            border-collapse: collapse;
            background: #1a1f36;
            color: #fff;
        }
        .sffc-comparison-table th,
        .sffc-comparison-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sffc-comparison-table th {
            background: #4361ee;
            font-weight: 600;
        }
        .sffc-news-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .sffc-news-cards .news-card {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .sffc-pe-infographic {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 40px;
            border-radius: 12px;
        }
        .sffc-pe-infographic .pe-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .sffc-pe-infographic .stat-card {
            text-align: center;
        }
        .sffc-pe-infographic .stat-value {
            font-size: 36px;
            font-weight: bold;
        }
        .sffc-pe-infographic .process-flow {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 20px 0;
        }
        .sffc-heatmap {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 4px;
        }
        .sffc-heatmap .heatmap-cell {
            padding: 15px;
            text-align: center;
            color: #fff;
            border-radius: 4px;
        }
        ';
        
        return $css;
    }
}