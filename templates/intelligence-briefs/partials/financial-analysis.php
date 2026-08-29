<?php
/**
 * Intelligence Brief - Financial Analysis
 * Page 6 of 12
 */

if (!defined('ABSPATH')) {
    exit;
}

$financials = sffc_brief_get($brief, 'financials', array());
$deal_value = sffc_brief_get($financials, 'deal_value', array());
$valuation = sffc_brief_get($financials, 'valuation', array());
$multiples = sffc_brief_get($financials, 'multiples', array());
$key_metrics = sffc_brief_get($financials, 'key_metrics', array());
$deal_structure = sffc_brief_get($financials, 'deal_structure', array());
$chart_url = sffc_brief_get($financials, 'chart_url', '');
$analysis_text = sffc_brief_get($financials, 'analysis', '');
?>

<div class="page">
    <div class="page-header">
        <svg class="logo-mark" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="8" cy="8" r="4" fill="#1E6B4A"/>
            <path d="M8 16C8 16 4 20 4 28C4 32 6 36 12 36C18 36 28 36 32 32C36 28 36 20 32 16C28 12 20 12 16 16C12 20 8 16 8 16Z" fill="#1E6B4A"/>
        </svg>
        <div class="page-number">6</div>
    </div>

    <div class="page-content">
        <div class="section-box">
            <h2>Financial Analysis</h2>
        </div>

        <?php if (!empty($key_metrics)) : ?>
        <div class="metrics-grid mb-lg">
            <div class="metrics-row">
                <?php
                $metric_count = 0;
                foreach ($key_metrics as $metric) :
                    if ($metric_count >= 4) break;
                    $metric_count++;
                ?>
                <div class="metric-card">
                    <div class="metric-value"><?php echo esc_html(sffc_brief_get($metric, 'value', 'N/A')); ?></div>
                    <div class="metric-label"><?php echo esc_html(sffc_brief_get($metric, 'label', '')); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($multiples)) : ?>
        <div class="mb-lg">
            <h4 class="heading-section mb-sm" style="font-size: 10pt;">Valuation Multiples</h4>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Multiple</th>
                        <th class="numeric">Value</th>
                        <th class="numeric">Sector Median</th>
                        <th>Assessment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($multiples as $multiple) : ?>
                    <tr>
                        <td><strong><?php echo esc_html(sffc_brief_get($multiple, 'name', '')); ?></strong></td>
                        <td class="numeric"><?php echo esc_html(sffc_brief_get($multiple, 'value', 'N/A')); ?></td>
                        <td class="numeric"><?php echo esc_html(sffc_brief_get($multiple, 'sector_median', 'N/A')); ?></td>
                        <td>
                            <?php
                            $assessment = sffc_brief_get($multiple, 'assessment', '');
                            $assessment_class = '';
                            if (stripos($assessment, 'premium') !== false || stripos($assessment, 'above') !== false) {
                                $assessment_class = 'color: var(--color-risk-high);';
                            } elseif (stripos($assessment, 'discount') !== false || stripos($assessment, 'below') !== false) {
                                $assessment_class = 'color: var(--color-risk-low);';
                            }
                            ?>
                            <span style="<?php echo $assessment_class; ?>"><?php echo esc_html($assessment); ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (!empty($deal_structure)) : ?>
        <div class="info-box mb-lg">
            <div class="info-box-title">Deal Structure</div>
            <div class="two-column">
                <div>
                    <?php if (sffc_brief_get($deal_structure, 'equity_component', '')) : ?>
                    <p class="mb-sm"><strong>Equity Component:</strong> <?php echo esc_html(sffc_brief_get($deal_structure, 'equity_component', '')); ?></p>
                    <?php endif; ?>
                    <?php if (sffc_brief_get($deal_structure, 'cash_component', '')) : ?>
                    <p class="mb-sm"><strong>Cash Component:</strong> <?php echo esc_html(sffc_brief_get($deal_structure, 'cash_component', '')); ?></p>
                    <?php endif; ?>
                    <?php if (sffc_brief_get($deal_structure, 'debt_component', '')) : ?>
                    <p class="mb-0"><strong>Debt Component:</strong> <?php echo esc_html(sffc_brief_get($deal_structure, 'debt_component', '')); ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if (sffc_brief_get($deal_structure, 'premium', '')) : ?>
                    <p class="mb-sm"><strong>Premium Paid:</strong> <?php echo esc_html(sffc_brief_get($deal_structure, 'premium', '')); ?></p>
                    <?php endif; ?>
                    <?php if (sffc_brief_get($deal_structure, 'financing', '')) : ?>
                    <p class="mb-0"><strong>Financing:</strong> <?php echo esc_html(sffc_brief_get($deal_structure, 'financing', '')); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($chart_url) : ?>
        <div class="chart-container">
            <div class="chart-title">Valuation Comparison</div>
            <img src="<?php echo esc_url($chart_url); ?>" alt="Financial Chart" class="chart-image" style="max-height: 180pt;">
            <div class="chart-source">Source: Company filings, market data</div>
        </div>
        <?php endif; ?>

        <?php if ($analysis_text) : ?>
        <div class="highlight-box">
            <h4 class="heading-section mb-sm" style="font-size: 10pt; color: var(--color-primary);">Analysis</h4>
            <p class="mb-0"><?php echo esc_html($analysis_text); ?></p>
        </div>
        <?php endif; ?>
    </div>

    <div class="page-footer">
        <div class="footer-brand">MENA Careers Finance</div>
        <div class="footer-confidential">Intelligence Brief</div>
    </div>
</div>
