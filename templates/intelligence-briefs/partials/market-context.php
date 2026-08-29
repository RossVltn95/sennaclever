<?php
/**
 * Intelligence Brief - Market Context
 * Page 7 of 12
 */

if (!defined('ABSPATH')) {
    exit;
}

$market = sffc_brief_get($brief, 'market_context', array());
$industry_overview = sffc_brief_get($market, 'industry_overview', '');
$sector_trends = sffc_brief_get($market, 'sector_trends', array());
$comparables = sffc_brief_get($market, 'comparable_transactions', array());
$benchmarks = sffc_brief_get($market, 'benchmarks', array());
$chart_url = sffc_brief_get($market, 'chart_url', '');
?>

<div class="page">
    <div class="page-header">
        <svg class="logo-mark" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="8" cy="8" r="4" fill="#1E6B4A"/>
            <path d="M8 16C8 16 4 20 4 28C4 32 6 36 12 36C18 36 28 36 32 32C36 28 36 20 32 16C28 12 20 12 16 16C12 20 8 16 8 16Z" fill="#1E6B4A"/>
        </svg>
        <div class="page-number">7</div>
    </div>

    <div class="page-content">
        <div class="section-box">
            <h2>Market Context</h2>
        </div>

        <?php if ($industry_overview) : ?>
        <div class="mb-lg">
            <h4 class="heading-section mb-sm" style="font-size: 10pt;">Industry Overview</h4>
            <p><?php echo esc_html($industry_overview); ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($sector_trends)) : ?>
        <div class="info-box mb-lg">
            <div class="info-box-title">Key Sector Trends</div>
            <ul class="bullet-list mb-0">
                <?php foreach ($sector_trends as $trend) : ?>
                <li><?php echo esc_html($trend); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($benchmarks)) : ?>
        <div class="metrics-grid mb-lg">
            <div class="metrics-row">
                <?php
                $bench_count = 0;
                foreach ($benchmarks as $benchmark) :
                    if ($bench_count >= 4) break;
                    $bench_count++;
                ?>
                <div class="metric-card">
                    <div class="metric-value"><?php echo esc_html(sffc_brief_get($benchmark, 'value', '')); ?></div>
                    <div class="metric-label"><?php echo esc_html(sffc_brief_get($benchmark, 'label', '')); ?></div>
                    <?php if (sffc_brief_get($benchmark, 'context', '')) : ?>
                    <div style="font-size: 8pt; color: var(--color-text-light); margin-top: 4pt;">
                        <?php echo esc_html(sffc_brief_get($benchmark, 'context', '')); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($comparables)) : ?>
        <div class="mb-lg">
            <h4 class="heading-section mb-sm" style="font-size: 10pt;">Comparable Transactions</h4>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Transaction</th>
                        <th>Date</th>
                        <th class="numeric">Value</th>
                        <th class="numeric">EV/Revenue</th>
                        <th class="numeric">EV/EBITDA</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comparables as $index => $comp) : ?>
                    <tr class="<?php echo $index === 0 ? 'highlight-row' : ''; ?>">
                        <td>
                            <strong><?php echo esc_html(sffc_brief_get($comp, 'target', '')); ?></strong>
                            <?php if (sffc_brief_get($comp, 'acquirer', '')) : ?>
                            <br><span style="font-size: 9pt; color: var(--color-text-light);">by <?php echo esc_html(sffc_brief_get($comp, 'acquirer', '')); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(sffc_brief_get($comp, 'date', '')); ?></td>
                        <td class="numeric"><?php echo esc_html(sffc_brief_get($comp, 'value', 'N/A')); ?></td>
                        <td class="numeric"><?php echo esc_html(sffc_brief_get($comp, 'ev_revenue', 'N/A')); ?></td>
                        <td class="numeric"><?php echo esc_html(sffc_brief_get($comp, 'ev_ebitda', 'N/A')); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="font-size: 9pt; color: var(--color-text-light); margin-top: 6pt;">
                * First row represents the current transaction for comparison
            </p>
        </div>
        <?php endif; ?>

        <?php if ($chart_url) : ?>
        <div class="chart-container">
            <div class="chart-title">Market Performance</div>
            <img src="<?php echo esc_url($chart_url); ?>" alt="Market Chart" class="chart-image" style="max-height: 160pt;">
            <div class="chart-source">Source: Market data, industry reports</div>
        </div>
        <?php endif; ?>
    </div>

    <div class="page-footer">
        <div class="footer-brand">MENA Careers Finance</div>
        <div class="footer-confidential">Intelligence Brief</div>
    </div>
</div>
