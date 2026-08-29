<?php
/**
 * Relocation Charts - HTML Chart Components
 *
 * Generates pure HTML/CSS charts for relocation comparison data.
 * No JavaScript dependencies required.
 *
 * @package SFFC_Careers
 * @since 11.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Relocation_Charts {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Chart color palette
     */
    private $colors = array(
        'primary' => '#1a365d',      // Dark blue
        'secondary' => '#2c5282',     // Medium blue
        'accent' => '#4299e1',        // Light blue
        'success' => '#38a169',       // Green
        'warning' => '#dd6b20',       // Orange
        'danger' => '#e53e3e',        // Red
        'neutral' => '#718096',       // Gray
        'light' => '#edf2f7',         // Light gray
        'from' => '#667eea',          // Purple (origin)
        'to' => '#48bb78',            // Green (destination)
    );

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    /**
     * Enqueue chart styles
     */
    public function enqueue_styles() {
        // Check for both singular post type and dynamic URL routes
        $from = get_query_var('sffc_from_location');
        $to = get_query_var('sffc_to_location');

        if (is_singular('sffc_relocation') || (!empty($from) && !empty($to))) {
            wp_enqueue_style(
                'sffc-relocation-charts',
                SFFC_PLUGIN_URL . 'assets/css/relocation-charts.css',
                array(),
                SFFC_VERSION
            );
        }
    }

    /**
     * Render a horizontal bar comparison chart
     */
    public function render_comparison_bar($from_value, $to_value, $from_label, $to_label, $options = array()) {
        $defaults = array(
            'title' => '',
            'unit' => '',
            'max' => max($from_value, $to_value) * 1.2,
            'format' => 'number', // number, currency, percent
            'show_difference' => true,
        );
        $options = wp_parse_args($options, $defaults);

        $max = $options['max'] > 0 ? $options['max'] : 100;
        $from_width = ($from_value / $max) * 100;
        $to_width = ($to_value / $max) * 100;

        $from_formatted = $this->format_value($from_value, $options['format'], $options['unit']);
        $to_formatted = $this->format_value($to_value, $options['format'], $options['unit']);

        $difference = $to_value - $from_value;
        $diff_percent = $from_value > 0 ? round(($difference / $from_value) * 100) : 0;
        $diff_class = $difference > 0 ? 'higher' : ($difference < 0 ? 'lower' : 'same');

        ob_start();
        ?>
        <div class="sffc-chart sffc-comparison-bar">
            <?php if (!empty($options['title'])): ?>
                <h4 class="sffc-chart-title"><?php echo esc_html($options['title']); ?></h4>
            <?php endif; ?>

            <div class="sffc-bar-group">
                <div class="sffc-bar-row">
                    <span class="sffc-bar-label"><?php echo esc_html($from_label); ?></span>
                    <div class="sffc-bar-track">
                        <div class="sffc-bar sffc-bar-from" style="width: <?php echo esc_attr($from_width); ?>%;">
                            <span class="sffc-bar-value"><?php echo esc_html($from_formatted); ?></span>
                        </div>
                    </div>
                </div>

                <div class="sffc-bar-row">
                    <span class="sffc-bar-label"><?php echo esc_html($to_label); ?></span>
                    <div class="sffc-bar-track">
                        <div class="sffc-bar sffc-bar-to" style="width: <?php echo esc_attr($to_width); ?>%;">
                            <span class="sffc-bar-value"><?php echo esc_html($to_formatted); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($options['show_difference'] && $difference != 0): ?>
                <div class="sffc-difference sffc-diff-<?php echo esc_attr($diff_class); ?>">
                    <?php
                    $arrow = $difference > 0 ? '&uarr;' : '&darr;';
                    echo $arrow . ' ' . abs($diff_percent) . '% ' . ($difference > 0 ? 'higher' : 'lower');
                    ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a comparison card
     */
    public function render_comparison_card($from_data, $to_data, $title, $icon = '') {
        ob_start();
        ?>
        <div class="sffc-comparison-card">
            <div class="sffc-card-header">
                <?php if ($icon): ?>
                    <span class="sffc-card-icon"><?php echo $icon; ?></span>
                <?php endif; ?>
                <h3 class="sffc-card-title"><?php echo esc_html($title); ?></h3>
            </div>

            <div class="sffc-card-comparison">
                <div class="sffc-card-side sffc-side-from">
                    <div class="sffc-side-label">From</div>
                    <div class="sffc-side-value"><?php echo esc_html($from_data['value']); ?></div>
                    <?php if (!empty($from_data['sublabel'])): ?>
                        <div class="sffc-side-sublabel"><?php echo esc_html($from_data['sublabel']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="sffc-card-arrow">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </div>

                <div class="sffc-card-side sffc-side-to">
                    <div class="sffc-side-label">To</div>
                    <div class="sffc-side-value"><?php echo esc_html($to_data['value']); ?></div>
                    <?php if (!empty($to_data['sublabel'])): ?>
                        <div class="sffc-side-sublabel"><?php echo esc_html($to_data['sublabel']); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($from_data['insight'])): ?>
                <div class="sffc-card-insight">
                    <?php echo esc_html($from_data['insight']); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a metrics grid
     */
    public function render_metrics_grid($metrics) {
        ob_start();
        ?>
        <div class="sffc-metrics-grid">
            <?php foreach ($metrics as $metric): ?>
                <div class="sffc-metric-card">
                    <div class="sffc-metric-value"><?php echo esc_html($metric['value']); ?></div>
                    <div class="sffc-metric-label"><?php echo esc_html($metric['label']); ?></div>
                    <?php if (!empty($metric['change'])): ?>
                        <div class="sffc-metric-change sffc-change-<?php echo esc_attr($metric['change_type'] ?? 'neutral'); ?>">
                            <?php echo esc_html($metric['change']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a progress bar with label
     */
    public function render_progress_bar($value, $max, $label, $options = array()) {
        $defaults = array(
            'color' => 'primary',
            'show_value' => true,
            'format' => 'number',
        );
        $options = wp_parse_args($options, $defaults);

        $percentage = $max > 0 ? min(100, ($value / $max) * 100) : 0;
        $formatted = $this->format_value($value, $options['format']);

        ob_start();
        ?>
        <div class="sffc-progress-item">
            <div class="sffc-progress-header">
                <span class="sffc-progress-label"><?php echo esc_html($label); ?></span>
                <?php if ($options['show_value']): ?>
                    <span class="sffc-progress-value"><?php echo esc_html($formatted); ?></span>
                <?php endif; ?>
            </div>
            <div class="sffc-progress-track">
                <div class="sffc-progress-bar sffc-color-<?php echo esc_attr($options['color']); ?>"
                     style="width: <?php echo esc_attr($percentage); ?>%;"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a donut chart (CSS-only)
     */
    public function render_donut_chart($value, $max, $options = array()) {
        $defaults = array(
            'title' => '',
            'subtitle' => '',
            'color' => 'primary',
            'size' => 'medium', // small, medium, large
        );
        $options = wp_parse_args($options, $defaults);

        $percentage = $max > 0 ? min(100, ($value / $max) * 100) : 0;
        $degrees = ($percentage / 100) * 360;

        ob_start();
        ?>
        <div class="sffc-donut-chart sffc-donut-<?php echo esc_attr($options['size']); ?>">
            <div class="sffc-donut-ring" style="background: conic-gradient(
                <?php echo esc_attr($this->colors[$options['color']] ?? $this->colors['primary']); ?> 0deg <?php echo esc_attr($degrees); ?>deg,
                <?php echo esc_attr($this->colors['light']); ?> <?php echo esc_attr($degrees); ?>deg 360deg
            );">
                <div class="sffc-donut-center">
                    <span class="sffc-donut-value"><?php echo esc_html(round($percentage)); ?>%</span>
                    <?php if (!empty($options['subtitle'])): ?>
                        <span class="sffc-donut-subtitle"><?php echo esc_html($options['subtitle']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($options['title'])): ?>
                <div class="sffc-donut-title"><?php echo esc_html($options['title']); ?></div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a radar/spider chart (simplified CSS version)
     */
    public function render_radar_chart($from_scores, $to_scores, $labels) {
        ob_start();
        ?>
        <div class="sffc-radar-chart">
            <div class="sffc-radar-legend">
                <span class="sffc-legend-item sffc-legend-from"><span class="sffc-legend-dot"></span> Origin</span>
                <span class="sffc-legend-item sffc-legend-to"><span class="sffc-legend-dot"></span> Destination</span>
            </div>

            <div class="sffc-radar-bars">
                <?php foreach ($labels as $index => $label): ?>
                    <?php
                    $from_val = $from_scores[$index] ?? 0;
                    $to_val = $to_scores[$index] ?? 0;
                    ?>
                    <div class="sffc-radar-category">
                        <div class="sffc-radar-label"><?php echo esc_html($label); ?></div>
                        <div class="sffc-radar-tracks">
                            <div class="sffc-radar-track">
                                <div class="sffc-radar-bar sffc-bar-from" style="width: <?php echo esc_attr($from_val); ?>%;"></div>
                            </div>
                            <div class="sffc-radar-track">
                                <div class="sffc-radar-bar sffc-bar-to" style="width: <?php echo esc_attr($to_val); ?>%;"></div>
                            </div>
                        </div>
                        <div class="sffc-radar-values">
                            <span class="sffc-val-from"><?php echo esc_html($from_val); ?></span>
                            <span class="sffc-val-to"><?php echo esc_html($to_val); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a salary comparison table
     */
    public function render_salary_table($from_salaries, $to_salaries, $from_label, $to_label) {
        ob_start();
        ?>
        <div class="sffc-salary-table">
            <table>
                <thead>
                    <tr>
                        <th>Role</th>
                        <th><?php echo esc_html($from_label); ?></th>
                        <th><?php echo esc_html($to_label); ?></th>
                        <th>Difference</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($from_salaries as $role => $from_salary): ?>
                        <?php
                        $to_salary = $to_salaries[$role] ?? 0;
                        $diff = $to_salary - $from_salary;
                        $diff_pct = $from_salary > 0 ? round(($diff / $from_salary) * 100) : 0;
                        $diff_class = $diff > 0 ? 'positive' : ($diff < 0 ? 'negative' : 'neutral');
                        ?>
                        <tr>
                            <td><?php echo esc_html($role); ?></td>
                            <td><?php echo esc_html($this->format_value($from_salary, 'currency')); ?></td>
                            <td><?php echo esc_html($this->format_value($to_salary, 'currency')); ?></td>
                            <td class="sffc-diff-<?php echo esc_attr($diff_class); ?>">
                                <?php
                                $arrow = $diff > 0 ? '+' : '';
                                echo $arrow . $diff_pct . '%';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a timeline/steps visualization
     */
    public function render_timeline($steps) {
        ob_start();
        ?>
        <div class="sffc-timeline">
            <?php foreach ($steps as $index => $step): ?>
                <div class="sffc-timeline-item">
                    <div class="sffc-timeline-marker">
                        <span class="sffc-timeline-number"><?php echo $index + 1; ?></span>
                    </div>
                    <div class="sffc-timeline-content">
                        <h4 class="sffc-timeline-title"><?php echo esc_html($step['title']); ?></h4>
                        <?php if (!empty($step['description'])): ?>
                            <p class="sffc-timeline-desc"><?php echo esc_html($step['description']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($step['duration'])): ?>
                            <span class="sffc-timeline-duration"><?php echo esc_html($step['duration']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a pros/cons comparison
     */
    public function render_pros_cons($pros, $cons) {
        ob_start();
        ?>
        <div class="sffc-pros-cons">
            <div class="sffc-pros">
                <h4 class="sffc-list-title">
                    <span class="sffc-icon-plus">+</span> Advantages
                </h4>
                <ul class="sffc-list">
                    <?php foreach ($pros as $pro): ?>
                        <li><?php echo esc_html($pro); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="sffc-cons">
                <h4 class="sffc-list-title">
                    <span class="sffc-icon-minus">-</span> Considerations
                </h4>
                <ul class="sffc-list">
                    <?php foreach ($cons as $con): ?>
                        <li><?php echo esc_html($con); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a key facts box
     */
    public function render_key_facts($facts) {
        ob_start();
        ?>
        <div class="sffc-key-facts">
            <h4 class="sffc-facts-title">Key Facts</h4>
            <dl class="sffc-facts-list">
                <?php foreach ($facts as $label => $value): ?>
                    <div class="sffc-fact-item">
                        <dt><?php echo esc_html($label); ?></dt>
                        <dd><?php echo esc_html($value); ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the full comparison dashboard
     */
    public function render_comparison_dashboard($comparison_data) {
        $from = $comparison_data['from'];
        $to = $comparison_data['to'];
        $mobility = $comparison_data['mobility'] ?? array();

        ob_start();
        ?>
        <div class="sffc-comparison-dashboard">
            <!-- Header with locations -->
            <div class="sffc-dashboard-header">
                <div class="sffc-location-badge sffc-location-from">
                    <span class="sffc-location-name"><?php echo esc_html($from['display_name']); ?></span>
                    <span class="sffc-location-type"><?php echo ucfirst($from['type']); ?></span>
                </div>

                <div class="sffc-route-arrow">
                    <svg width="40" height="24" viewBox="0 0 40 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M0 12h36M30 6l6 6-6 6"/>
                    </svg>
                </div>

                <div class="sffc-location-badge sffc-location-to">
                    <span class="sffc-location-name"><?php echo esc_html($to['display_name']); ?></span>
                    <span class="sffc-location-type"><?php echo ucfirst($to['type']); ?></span>
                </div>
            </div>

            <!-- Mobility Score -->
            <?php if (!empty($mobility['mobility_score'])): ?>
                <div class="sffc-mobility-score-section">
                    <?php
                    echo $this->render_donut_chart(
                        $mobility['mobility_score'],
                        100,
                        array(
                            'title' => 'Mobility Score',
                            'subtitle' => 'Overall',
                            'color' => $mobility['mobility_score'] >= 65 ? 'success' : ($mobility['mobility_score'] >= 40 ? 'warning' : 'danger'),
                            'size' => 'large',
                        )
                    );
                    ?>
                    <p class="sffc-score-interpretation">
                        <?php
                        if ($mobility['mobility_score'] >= 65) {
                            echo 'This relocation is likely to be favorable based on available data.';
                        } elseif ($mobility['mobility_score'] >= 40) {
                            echo 'This relocation has both advantages and challenges to consider.';
                        } else {
                            echo 'This relocation may present significant challenges.';
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Cost of Living -->
            <?php if (!empty($mobility['cost_of_living'])): ?>
                <div class="sffc-section">
                    <h3>Cost of Living</h3>
                    <?php
                    $col = $mobility['cost_of_living'];
                    $from_index = $col['from']['cost_index'] ?? 100;
                    $to_index = $col['to']['cost_index'] ?? 100;

                    echo $this->render_comparison_bar(
                        $from_index,
                        $to_index,
                        $from['display_name'],
                        $to['display_name'],
                        array(
                            'title' => 'Cost Index (100 = NYC baseline)',
                            'format' => 'number',
                        )
                    );
                    ?>
                </div>
            <?php endif; ?>

            <!-- Tax Rates -->
            <?php if (!empty($mobility['tax_rates'])): ?>
                <div class="sffc-section">
                    <h3>Tax Comparison</h3>
                    <?php
                    $tax = $mobility['tax_rates'];
                    $from_rate = ($tax['from']['top_income_tax_rate'] ?? 0);
                    $to_rate = ($tax['to']['top_income_tax_rate'] ?? 0);

                    echo $this->render_comparison_bar(
                        $from_rate,
                        $to_rate,
                        $from['display_name'],
                        $to['display_name'],
                        array(
                            'title' => 'Top Income Tax Rate',
                            'unit' => '%',
                            'format' => 'percent',
                            'max' => 60,
                        )
                    );
                    ?>
                </div>
            <?php endif; ?>

            <!-- Visa Requirements -->
            <?php if (!empty($mobility['visa_requirements'])): ?>
                <div class="sffc-section">
                    <h3>Visa Requirements</h3>
                    <?php
                    $visa = $mobility['visa_requirements'];
                    $facts = array(
                        'Difficulty' => $visa['difficulty'] ?? 'Unknown',
                        'Work Visa Type' => $visa['work_visa_type'] ?? 'Varies',
                        'Processing Time' => $visa['processing_time'] ?? 'Varies',
                    );
                    echo $this->render_key_facts($facts);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Quality of Life -->
            <?php if (!empty($mobility['quality_of_life'])): ?>
                <div class="sffc-section">
                    <h3>Quality of Life</h3>
                    <?php
                    $qol = $mobility['quality_of_life'];
                    $from_score = $qol['from']['quality_score'] ?? 50;
                    $to_score = $qol['to']['quality_score'] ?? 50;

                    echo $this->render_comparison_bar(
                        $from_score,
                        $to_score,
                        $from['display_name'],
                        $to['display_name'],
                        array(
                            'title' => 'Quality of Life Score',
                            'format' => 'number',
                            'max' => 100,
                        )
                    );
                    ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Format a value based on type
     */
    private function format_value($value, $format = 'number', $unit = '') {
        switch ($format) {
            case 'currency':
                return '$' . number_format($value);

            case 'percent':
                return number_format($value, 1) . '%';

            case 'number':
            default:
                $formatted = number_format($value, is_float($value) ? 1 : 0);
                return $unit ? $formatted . $unit : $formatted;
        }
    }

    /**
     * Get inline CSS for charts (for email/print)
     */
    public function get_inline_css() {
        return <<<CSS
.sffc-chart { margin: 1.5rem 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
.sffc-chart-title { font-size: 0.875rem; color: #4a5568; margin-bottom: 0.75rem; font-weight: 600; }
.sffc-bar-group { display: flex; flex-direction: column; gap: 0.5rem; }
.sffc-bar-row { display: flex; align-items: center; gap: 0.75rem; }
.sffc-bar-label { width: 100px; font-size: 0.875rem; color: #4a5568; flex-shrink: 0; }
.sffc-bar-track { flex: 1; height: 28px; background: #edf2f7; border-radius: 4px; overflow: hidden; }
.sffc-bar { height: 100%; display: flex; align-items: center; justify-content: flex-end; padding-right: 8px; transition: width 0.5s ease; }
.sffc-bar-from { background: linear-gradient(90deg, #667eea, #764ba2); }
.sffc-bar-to { background: linear-gradient(90deg, #48bb78, #38a169); }
.sffc-bar-value { color: white; font-size: 0.75rem; font-weight: 600; }
.sffc-difference { text-align: center; font-size: 0.875rem; margin-top: 0.5rem; padding: 0.25rem 0.5rem; border-radius: 4px; }
.sffc-diff-higher { color: #c53030; background: #fed7d7; }
.sffc-diff-lower { color: #276749; background: #c6f6d5; }
CSS;
    }
}

// Initialize
add_action('plugins_loaded', function() {
    SFFC_Relocation_Charts::get_instance();
});
