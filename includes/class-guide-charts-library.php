<?php
/**
 * Guide Charts & Calculator Library
 *
 * Pre-built chart configurations and interactive calculators
 * for embedding in career guides
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Guide_Charts_Library
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        add_shortcode('sffc_salary_chart', array($this, 'render_salary_chart'));
        add_shortcode('sffc_career_progression', array($this, 'render_career_progression'));
        add_shortcode('sffc_lbo_calculator', array($this, 'render_lbo_calculator'));
        add_shortcode('sffc_irr_calculator', array($this, 'render_irr_calculator'));
        add_shortcode('sffc_comp_comparison', array($this, 'render_comp_comparison'));
        add_shortcode('sffc_market_indicator', array($this, 'render_market_indicator'));
        add_shortcode('sffc_salary_table', array($this, 'render_salary_table'));
        add_shortcode('sffc_timeline', array($this, 'render_timeline'));

        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets()
    {
        if (!is_singular('sffc_career_insights')) {
            return;
        }

        // Chart.js for visualizations
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', array(), '4.4.1', true);

        // Custom styles
        wp_add_inline_style('sffc-guide-styles', $this->get_chart_styles());
    }

    /**
     * Salary comparison chart
     * Usage: [sffc_salary_chart roles="analyst,associate,vp,director" location="NYC"]
     */
    public function render_salary_chart($atts)
    {
        $atts = shortcode_atts(array(
            'roles'    => 'analyst,associate,vp,director,md',
            'location' => 'US',
            'type'     => 'bar',
            'title'    => 'Compensation by Level',
        ), $atts);

        $roles = array_map('trim', explode(',', $atts['roles']));
        $data_fetcher = SFFC_External_Data_Fetcher::get_instance();

        $chart_data = array(
            'labels' => array(),
            'base'   => array(),
            'bonus'  => array(),
        );

        foreach ($roles as $role) {
            $salary = $data_fetcher->get_salary_estimate($role, $atts['location']);
            $chart_data['labels'][] = ucfirst($role);
            $chart_data['base'][] = $salary['base_salary'];
            $chart_data['bonus'][] = $salary['bonus'];
        }

        $chart_id = 'sffc-salary-chart-' . wp_rand(1000, 9999);

        ob_start();
        ?>
        <div class="sffc-chart-container">
            <h4 class="sffc-chart-title"><?php echo esc_html($atts['title']); ?></h4>
            <canvas id="<?php echo esc_attr($chart_id); ?>" height="300"></canvas>
            <p class="sffc-chart-source">Source: MENA Careers Finance Industry Estimates 2024</p>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('<?php echo esc_js($chart_id); ?>').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo wp_json_encode($chart_data['labels']); ?>,
                    datasets: [{
                        label: 'Base Salary',
                        data: <?php echo wp_json_encode($chart_data['base']); ?>,
                        backgroundColor: '#1a1a1a',
                    }, {
                        label: 'Bonus',
                        data: <?php echo wp_json_encode($chart_data['bonus']); ?>,
                        backgroundColor: '#005cb9',
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: { stacked: true },
                        y: {
                            stacked: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': $' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Career progression timeline
     * Usage: [sffc_career_progression path="pe"]
     */
    public function render_career_progression($atts)
    {
        $atts = shortcode_atts(array(
            'path' => 'pe',
        ), $atts);

        $paths = array(
            'pe' => array(
                array('title' => 'Analyst', 'years' => '0-2', 'comp' => '$100-150K', 'desc' => 'Financial modeling, due diligence support'),
                array('title' => 'Associate', 'years' => '2-5', 'comp' => '$200-350K', 'desc' => 'Lead deal execution, manage analysts'),
                array('title' => 'Senior Associate', 'years' => '5-7', 'comp' => '$350-500K', 'desc' => 'Source deals, lead due diligence'),
                array('title' => 'Vice President', 'years' => '7-10', 'comp' => '$500-800K', 'desc' => 'Deal leadership, client relationships'),
                array('title' => 'Principal', 'years' => '10-13', 'comp' => '$800K-1.5M', 'desc' => 'Investment committee, portfolio oversight'),
                array('title' => 'Partner/MD', 'years' => '13+', 'comp' => '$1.5M+', 'desc' => 'Fund strategy, LP relationships, carry'),
            ),
            'ib' => array(
                array('title' => 'Analyst', 'years' => '0-2', 'comp' => '$150-200K', 'desc' => 'Pitchbooks, models, deal support'),
                array('title' => 'Associate', 'years' => '2-5', 'comp' => '$250-400K', 'desc' => 'Lead live deals, manage analysts'),
                array('title' => 'Vice President', 'years' => '5-8', 'comp' => '$400-700K', 'desc' => 'Client coverage, deal execution'),
                array('title' => 'Director/ED', 'years' => '8-12', 'comp' => '$700K-1.2M', 'desc' => 'Business development, team management'),
                array('title' => 'Managing Director', 'years' => '12+', 'comp' => '$1M-5M+', 'desc' => 'Rainmaking, P&L responsibility'),
            ),
            'hf' => array(
                array('title' => 'Analyst', 'years' => '0-3', 'comp' => '$150-300K', 'desc' => 'Idea generation, research'),
                array('title' => 'Senior Analyst', 'years' => '3-6', 'comp' => '$300-600K', 'desc' => 'Lead coverage, portfolio recommendations'),
                array('title' => 'Portfolio Manager', 'years' => '6-10', 'comp' => '$500K-2M+', 'desc' => 'Capital allocation, risk management'),
                array('title' => 'Senior PM/Partner', 'years' => '10+', 'comp' => '$1M-10M+', 'desc' => 'Fund leadership, LP relations'),
            ),
        );

        $career_path = $paths[$atts['path']] ?? $paths['pe'];

        ob_start();
        ?>
        <div class="sffc-career-progression">
            <div class="sffc-progression-timeline">
                <?php foreach ($career_path as $index => $level): ?>
                <div class="sffc-progression-step">
                    <div class="sffc-step-marker"><?php echo $index + 1; ?></div>
                    <div class="sffc-step-content">
                        <h5 class="sffc-step-title"><?php echo esc_html($level['title']); ?></h5>
                        <div class="sffc-step-meta">
                            <span class="sffc-step-years"><?php echo esc_html($level['years']); ?> years</span>
                            <span class="sffc-step-comp"><?php echo esc_html($level['comp']); ?></span>
                        </div>
                        <p class="sffc-step-desc"><?php echo esc_html($level['desc']); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * LBO Returns Calculator
     * Usage: [sffc_lbo_calculator]
     */
    public function render_lbo_calculator($atts)
    {
        $calc_id = 'sffc-lbo-calc-' . wp_rand(1000, 9999);

        ob_start();
        ?>
        <div class="sffc-calculator" id="<?php echo esc_attr($calc_id); ?>">
            <h4 class="sffc-calc-title">LBO Returns Calculator</h4>
            <div class="sffc-calc-grid">
                <div class="sffc-calc-input">
                    <label>Entry Enterprise Value ($M)</label>
                    <input type="number" id="<?php echo esc_attr($calc_id); ?>-entry-ev" value="500" min="0">
                </div>
                <div class="sffc-calc-input">
                    <label>Entry EBITDA ($M)</label>
                    <input type="number" id="<?php echo esc_attr($calc_id); ?>-entry-ebitda" value="50" min="0">
                </div>
                <div class="sffc-calc-input">
                    <label>Debt / EBITDA</label>
                    <input type="number" id="<?php echo esc_attr($calc_id); ?>-leverage" value="5.0" step="0.1" min="0">
                </div>
                <div class="sffc-calc-input">
                    <label>Exit Multiple</label>
                    <input type="number" id="<?php echo esc_attr($calc_id); ?>-exit-multiple" value="10" step="0.1" min="0">
                </div>
                <div class="sffc-calc-input">
                    <label>EBITDA Growth (% per year)</label>
                    <input type="number" id="<?php echo esc_attr($calc_id); ?>-growth" value="5" min="0">
                </div>
                <div class="sffc-calc-input">
                    <label>Hold Period (years)</label>
                    <input type="number" id="<?php echo esc_attr($calc_id); ?>-hold" value="5" min="1" max="10">
                </div>
            </div>
            <button class="sffc-calc-btn" onclick="calculateLBO_<?php echo esc_js(str_replace('-', '_', $calc_id)); ?>()">Calculate Returns</button>
            <div class="sffc-calc-results" id="<?php echo esc_attr($calc_id); ?>-results">
                <div class="sffc-result-item">
                    <span class="sffc-result-label">Equity Investment</span>
                    <span class="sffc-result-value" id="<?php echo esc_attr($calc_id); ?>-equity">-</span>
                </div>
                <div class="sffc-result-item">
                    <span class="sffc-result-label">Exit Equity Value</span>
                    <span class="sffc-result-value" id="<?php echo esc_attr($calc_id); ?>-exit-equity">-</span>
                </div>
                <div class="sffc-result-item sffc-result-highlight">
                    <span class="sffc-result-label">MOIC</span>
                    <span class="sffc-result-value" id="<?php echo esc_attr($calc_id); ?>-moic">-</span>
                </div>
                <div class="sffc-result-item sffc-result-highlight">
                    <span class="sffc-result-label">IRR</span>
                    <span class="sffc-result-value" id="<?php echo esc_attr($calc_id); ?>-irr">-</span>
                </div>
            </div>
        </div>
        <script>
        function calculateLBO_<?php echo esc_js(str_replace('-', '_', $calc_id)); ?>() {
            const id = '<?php echo esc_js($calc_id); ?>';
            const entryEV = parseFloat(document.getElementById(id + '-entry-ev').value);
            const entryEBITDA = parseFloat(document.getElementById(id + '-entry-ebitda').value);
            const leverage = parseFloat(document.getElementById(id + '-leverage').value);
            const exitMultiple = parseFloat(document.getElementById(id + '-exit-multiple').value);
            const growth = parseFloat(document.getElementById(id + '-growth').value) / 100;
            const hold = parseInt(document.getElementById(id + '-hold').value);

            const debt = entryEBITDA * leverage;
            const equity = entryEV - debt;
            const exitEBITDA = entryEBITDA * Math.pow(1 + growth, hold);
            const exitEV = exitEBITDA * exitMultiple;
            const debtPaydown = debt * 0.3; // Assume 30% paydown
            const exitEquity = exitEV - (debt - debtPaydown);
            const moic = exitEquity / equity;
            const irr = (Math.pow(moic, 1/hold) - 1) * 100;

            document.getElementById(id + '-equity').textContent = '$' + equity.toFixed(1) + 'M';
            document.getElementById(id + '-exit-equity').textContent = '$' + exitEquity.toFixed(1) + 'M';
            document.getElementById(id + '-moic').textContent = moic.toFixed(2) + 'x';
            document.getElementById(id + '-irr').textContent = irr.toFixed(1) + '%';
        }
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * IRR Calculator
     * Usage: [sffc_irr_calculator]
     */
    public function render_irr_calculator($atts)
    {
        $calc_id = 'sffc-irr-calc-' . wp_rand(1000, 9999);

        ob_start();
        ?>
        <div class="sffc-calculator" id="<?php echo esc_attr($calc_id); ?>">
            <h4 class="sffc-calc-title">IRR Calculator</h4>
            <div class="sffc-calc-grid">
                <div class="sffc-calc-input">
                    <label>Initial Investment ($)</label>
                    <input type="number" id="<?php echo esc_attr($calc_id); ?>-initial" value="100000" min="0">
                </div>
                <div class="sffc-calc-input">
                    <label>Final Value ($)</label>
                    <input type="number" id="<?php echo esc_attr($calc_id); ?>-final" value="200000" min="0">
                </div>
                <div class="sffc-calc-input">
                    <label>Years</label>
                    <input type="number" id="<?php echo esc_attr($calc_id); ?>-years" value="5" min="1" max="30">
                </div>
            </div>
            <button class="sffc-calc-btn" onclick="calculateIRR_<?php echo esc_js(str_replace('-', '_', $calc_id)); ?>()">Calculate IRR</button>
            <div class="sffc-calc-results" id="<?php echo esc_attr($calc_id); ?>-results">
                <div class="sffc-result-item sffc-result-highlight">
                    <span class="sffc-result-label">IRR</span>
                    <span class="sffc-result-value" id="<?php echo esc_attr($calc_id); ?>-irr">-</span>
                </div>
                <div class="sffc-result-item">
                    <span class="sffc-result-label">Multiple</span>
                    <span class="sffc-result-value" id="<?php echo esc_attr($calc_id); ?>-multiple">-</span>
                </div>
            </div>
        </div>
        <script>
        function calculateIRR_<?php echo esc_js(str_replace('-', '_', $calc_id)); ?>() {
            const id = '<?php echo esc_js($calc_id); ?>';
            const initial = parseFloat(document.getElementById(id + '-initial').value);
            const final = parseFloat(document.getElementById(id + '-final').value);
            const years = parseInt(document.getElementById(id + '-years').value);

            const multiple = final / initial;
            const irr = (Math.pow(multiple, 1/years) - 1) * 100;

            document.getElementById(id + '-irr').textContent = irr.toFixed(2) + '%';
            document.getElementById(id + '-multiple').textContent = multiple.toFixed(2) + 'x';
        }
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Compensation comparison table
     * Usage: [sffc_comp_comparison firms="blackstone,kkr,apollo" level="associate"]
     */
    public function render_comp_comparison($atts)
    {
        $atts = shortcode_atts(array(
            'firms' => 'blackstone,kkr,apollo,carlyle,tpg',
            'level' => 'associate',
        ), $atts);

        // Estimated compensation by firm (PE)
        $firm_data = array(
            'blackstone' => array('base' => 175000, 'bonus' => 150000, 'stub' => 50000),
            'kkr'        => array('base' => 175000, 'bonus' => 145000, 'stub' => 45000),
            'apollo'     => array('base' => 175000, 'bonus' => 155000, 'stub' => 55000),
            'carlyle'    => array('base' => 170000, 'bonus' => 140000, 'stub' => 40000),
            'tpg'        => array('base' => 170000, 'bonus' => 135000, 'stub' => 40000),
            'bain'       => array('base' => 175000, 'bonus' => 145000, 'stub' => 45000),
            'warburg'    => array('base' => 175000, 'bonus' => 140000, 'stub' => 45000),
            'advent'     => array('base' => 170000, 'bonus' => 130000, 'stub' => 35000),
            'hellman'    => array('base' => 165000, 'bonus' => 125000, 'stub' => 35000),
            'vista'      => array('base' => 170000, 'bonus' => 135000, 'stub' => 40000),
        );

        $firms = array_map('trim', explode(',', strtolower($atts['firms'])));

        ob_start();
        ?>
        <div class="sffc-comp-table-container">
            <table class="sffc-comp-table">
                <thead>
                    <tr>
                        <th>Firm</th>
                        <th>Base Salary</th>
                        <th>Bonus</th>
                        <th>Stub Bonus</th>
                        <th>Total Comp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($firms as $firm):
                        $data = $firm_data[$firm] ?? array('base' => 170000, 'bonus' => 130000, 'stub' => 35000);
                        $total = $data['base'] + $data['bonus'] + $data['stub'];
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html(ucfirst($firm)); ?></strong></td>
                        <td>$<?php echo number_format($data['base']); ?></td>
                        <td>$<?php echo number_format($data['bonus']); ?></td>
                        <td>$<?php echo number_format($data['stub']); ?></td>
                        <td class="sffc-total"><strong>$<?php echo number_format($total); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="sffc-table-note">*Estimates based on industry surveys. Actual compensation varies by performance and market conditions.</p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Market indicator display
     * Usage: [sffc_market_indicator indicator="fed_funds"]
     */
    public function render_market_indicator($atts)
    {
        $atts = shortcode_atts(array(
            'indicator' => 'fed_funds',
            'title'     => '',
        ), $atts);

        $data_fetcher = SFFC_External_Data_Fetcher::get_instance();
        $context = $data_fetcher->get_market_context();

        $indicator_map = array(
            'fed_funds'   => array('data' => 'fed_funds_rate', 'title' => 'Federal Funds Rate', 'suffix' => '%'),
            'treasury_10' => array('data' => 'ten_year_treasury', 'title' => '10-Year Treasury', 'suffix' => '%'),
            'unemployment'=> array('data' => 'unemployment', 'title' => 'Unemployment Rate', 'suffix' => '%'),
            'vix'         => array('data' => 'vix', 'title' => 'VIX Index', 'suffix' => ''),
            'sp500'       => array('data' => 'sp500', 'title' => 'S&P 500', 'suffix' => ''),
        );

        $config = $indicator_map[$atts['indicator']] ?? $indicator_map['fed_funds'];
        $data = $context[$config['data']] ?? null;

        if (!$data || empty($data['values'])) {
            return '<div class="sffc-indicator sffc-indicator-error">Data unavailable</div>';
        }

        $value = reset($data['values']);
        $title = $atts['title'] ?: $config['title'];

        ob_start();
        ?>
        <div class="sffc-market-indicator">
            <div class="sffc-indicator-title"><?php echo esc_html($title); ?></div>
            <div class="sffc-indicator-value"><?php echo number_format($value, 2) . $config['suffix']; ?></div>
            <div class="sffc-indicator-source">Source: <?php echo esc_html($data['source'] ?? 'FRED'); ?></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Salary table by level and location
     * Usage: [sffc_salary_table role="analyst" locations="NYC,London,HK"]
     */
    public function render_salary_table($atts)
    {
        $atts = shortcode_atts(array(
            'role'      => 'analyst',
            'locations' => 'NYC,London,Singapore,HK',
        ), $atts);

        $locations = array_map('trim', explode(',', $atts['locations']));
        $data_fetcher = SFFC_External_Data_Fetcher::get_instance();

        ob_start();
        ?>
        <div class="sffc-salary-table-container">
            <table class="sffc-salary-table">
                <thead>
                    <tr>
                        <th>Location</th>
                        <th>Base Salary</th>
                        <th>Bonus</th>
                        <th>Total Comp</th>
                        <th>Currency</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($locations as $location):
                        $salary = $data_fetcher->get_salary_estimate($atts['role'], $location);
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($location); ?></strong></td>
                        <td><?php echo number_format($salary['base_salary']); ?></td>
                        <td><?php echo number_format($salary['bonus']); ?></td>
                        <td class="sffc-total"><strong><?php echo number_format($salary['total_comp']); ?></strong></td>
                        <td><?php echo esc_html($salary['currency']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="sffc-table-note">Source: <?php echo esc_html($salary['source'] ?? 'Industry estimates'); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Timeline for processes (interview, recruiting, etc)
     * Usage: [sffc_timeline type="pe_recruiting"]
     */
    public function render_timeline($atts)
    {
        $atts = shortcode_atts(array(
            'type' => 'pe_recruiting',
        ), $atts);

        $timelines = array(
            'pe_recruiting' => array(
                array('month' => 'Jan-Feb', 'title' => 'Networking Begins', 'desc' => 'Start reaching out to headhunters and PE professionals'),
                array('month' => 'Mar-Apr', 'title' => 'Headhunter Outreach', 'desc' => 'Formal conversations with recruiters begin'),
                array('month' => 'May-Jul', 'title' => 'First Round Interviews', 'desc' => 'Technical screens and initial interviews'),
                array('month' => 'Aug-Sep', 'title' => 'Superdays', 'desc' => 'Final round interviews at fund offices'),
                array('month' => 'Sep-Oct', 'title' => 'Offers Extended', 'desc' => 'Verbal and written offers made'),
                array('month' => 'Jan (Next)', 'title' => 'Start Date', 'desc' => 'Begin PE associate role'),
            ),
            'ib_recruiting' => array(
                array('month' => 'Jun-Aug', 'title' => 'Summer Internship', 'desc' => '10-week internship program'),
                array('month' => 'Aug', 'title' => 'Return Offers', 'desc' => 'Full-time offers extended to interns'),
                array('month' => 'Sep-Nov', 'title' => 'Full-time Recruiting', 'desc' => 'Recruiting for non-interns'),
                array('month' => 'Jul (Next)', 'title' => 'Start Date', 'desc' => 'Begin analyst program'),
            ),
        );

        $timeline = $timelines[$atts['type']] ?? $timelines['pe_recruiting'];

        ob_start();
        ?>
        <div class="sffc-timeline">
            <?php foreach ($timeline as $step): ?>
            <div class="sffc-timeline-item">
                <div class="sffc-timeline-marker"></div>
                <div class="sffc-timeline-content">
                    <div class="sffc-timeline-date"><?php echo esc_html($step['month']); ?></div>
                    <h5 class="sffc-timeline-title"><?php echo esc_html($step['title']); ?></h5>
                    <p class="sffc-timeline-desc"><?php echo esc_html($step['desc']); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get CSS styles for charts and calculators
     */
    private function get_chart_styles()
    {
        return '
        .sffc-chart-container { margin: 2rem 0; padding: 1.5rem; background: #fafafa; border-radius: 8px; }
        .sffc-chart-title { margin: 0 0 1rem; font-size: 1.1rem; font-weight: 600; }
        .sffc-chart-source { margin-top: 1rem; font-size: 0.85rem; color: #666; }

        .sffc-calculator { margin: 2rem 0; padding: 1.5rem; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef; }
        .sffc-calc-title { margin: 0 0 1.5rem; font-size: 1.2rem; font-weight: 600; }
        .sffc-calc-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .sffc-calc-input label { display: block; margin-bottom: 0.5rem; font-size: 0.9rem; font-weight: 500; }
        .sffc-calc-input input { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; }
        .sffc-calc-btn { background: #1a1a1a; color: #fff; border: none; padding: 0.75rem 2rem; border-radius: 4px; cursor: pointer; font-size: 1rem; }
        .sffc-calc-btn:hover { background: #333; }
        .sffc-calc-results { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #ddd; }
        .sffc-result-item { text-align: center; }
        .sffc-result-label { display: block; font-size: 0.85rem; color: #666; margin-bottom: 0.5rem; }
        .sffc-result-value { display: block; font-size: 1.5rem; font-weight: 700; }
        .sffc-result-highlight .sffc-result-value { color: #005cb9; }

        .sffc-career-progression { margin: 2rem 0; }
        .sffc-progression-timeline { position: relative; padding-left: 2rem; }
        .sffc-progression-step { position: relative; padding-bottom: 2rem; border-left: 2px solid #e9ecef; padding-left: 2rem; }
        .sffc-progression-step:last-child { border-left: 2px solid transparent; }
        .sffc-step-marker { position: absolute; left: -1rem; top: 0; width: 2rem; height: 2rem; background: #1a1a1a; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.9rem; }
        .sffc-step-title { margin: 0 0 0.5rem; font-size: 1.1rem; }
        .sffc-step-meta { display: flex; gap: 1rem; margin-bottom: 0.5rem; }
        .sffc-step-years, .sffc-step-comp { font-size: 0.9rem; color: #666; }
        .sffc-step-comp { font-weight: 600; color: #005cb9; }
        .sffc-step-desc { margin: 0; font-size: 0.95rem; color: #444; }

        .sffc-comp-table-container, .sffc-salary-table-container { margin: 2rem 0; overflow-x: auto; }
        .sffc-comp-table, .sffc-salary-table { width: 100%; border-collapse: collapse; }
        .sffc-comp-table th, .sffc-comp-table td, .sffc-salary-table th, .sffc-salary-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #e9ecef; }
        .sffc-comp-table th, .sffc-salary-table th { background: #f8f9fa; font-weight: 600; }
        .sffc-comp-table .sffc-total, .sffc-salary-table .sffc-total { color: #005cb9; }
        .sffc-table-note { margin-top: 0.5rem; font-size: 0.85rem; color: #666; }

        .sffc-market-indicator { display: inline-block; padding: 1rem 1.5rem; background: #f8f9fa; border-radius: 8px; text-align: center; margin: 1rem 0; }
        .sffc-indicator-title { font-size: 0.9rem; color: #666; margin-bottom: 0.5rem; }
        .sffc-indicator-value { font-size: 1.5rem; font-weight: 700; color: #1a1a1a; }
        .sffc-indicator-source { font-size: 0.75rem; color: #999; margin-top: 0.5rem; }

        .sffc-timeline { position: relative; margin: 2rem 0; }
        .sffc-timeline-item { display: flex; gap: 1.5rem; padding-bottom: 2rem; }
        .sffc-timeline-marker { width: 12px; height: 12px; background: #1a1a1a; border-radius: 50%; margin-top: 0.5rem; flex-shrink: 0; position: relative; }
        .sffc-timeline-marker::after { content: ""; position: absolute; left: 50%; top: 100%; width: 2px; height: calc(100% + 2rem); background: #e9ecef; transform: translateX(-50%); }
        .sffc-timeline-item:last-child .sffc-timeline-marker::after { display: none; }
        .sffc-timeline-date { font-size: 0.9rem; color: #005cb9; font-weight: 600; margin-bottom: 0.25rem; }
        .sffc-timeline-title { margin: 0 0 0.5rem; font-size: 1rem; }
        .sffc-timeline-desc { margin: 0; font-size: 0.9rem; color: #666; }
        ';
    }

    /**
     * Get available chart/calculator shortcodes for Claude
     */
    public static function get_available_components()
    {
        return array(
            array(
                'shortcode'   => '[sffc_salary_chart roles="analyst,associate,vp" location="NYC"]',
                'description' => 'Bar chart showing compensation by career level',
                'use_case'    => 'Salary guides, compensation comparisons',
            ),
            array(
                'shortcode'   => '[sffc_career_progression path="pe"]',
                'description' => 'Visual timeline of career progression with comp at each level',
                'use_case'    => 'Career path guides, "how to break in" articles',
                'paths'       => 'pe, ib, hf',
            ),
            array(
                'shortcode'   => '[sffc_lbo_calculator]',
                'description' => 'Interactive LBO returns calculator',
                'use_case'    => 'LBO guides, modeling tutorials, interview prep',
            ),
            array(
                'shortcode'   => '[sffc_irr_calculator]',
                'description' => 'Simple IRR/MOIC calculator',
                'use_case'    => 'Investment basics, returns explainers',
            ),
            array(
                'shortcode'   => '[sffc_comp_comparison firms="blackstone,kkr,apollo"]',
                'description' => 'Table comparing compensation across firms',
                'use_case'    => 'Firm comparisons, salary guides',
            ),
            array(
                'shortcode'   => '[sffc_salary_table role="analyst" locations="NYC,London,Singapore"]',
                'description' => 'Salary comparison table by location',
                'use_case'    => 'Geographic salary guides, international career guides',
            ),
            array(
                'shortcode'   => '[sffc_market_indicator indicator="fed_funds"]',
                'description' => 'Display current market indicator',
                'use_case'    => 'Market context in guides',
                'indicators'  => 'fed_funds, treasury_10, unemployment, vix, sp500',
            ),
            array(
                'shortcode'   => '[sffc_timeline type="pe_recruiting"]',
                'description' => 'Timeline visualization for processes',
                'use_case'    => 'Recruiting guides, process explainers',
                'types'       => 'pe_recruiting, ib_recruiting',
            ),
        );
    }
}

// Initialize
SFFC_Guide_Charts_Library::get_instance();
