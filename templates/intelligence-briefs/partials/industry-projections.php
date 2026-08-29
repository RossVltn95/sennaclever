<?php
/**
 * Intelligence Brief - Industry Projections
 * Page 8 of 12
 */

if (!defined('ABSPATH')) {
    exit;
}

$projections = sffc_brief_get($brief, 'industry_projections', array());
$overview = sffc_brief_get($projections, 'overview', '');
$growth_forecasts = sffc_brief_get($projections, 'growth_forecasts', array());
$drivers = sffc_brief_get($projections, 'key_drivers', array());
$headwinds = sffc_brief_get($projections, 'headwinds', array());
$chart_url = sffc_brief_get($projections, 'chart_url', '');
$market_size = sffc_brief_get($projections, 'market_size', array());
?>

<div class="page">
    <div class="page-header">
        <svg class="logo-mark" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="8" cy="8" r="4" fill="#1E6B4A"/>
            <path d="M8 16C8 16 4 20 4 28C4 32 6 36 12 36C18 36 28 36 32 32C36 28 36 20 32 16C28 12 20 12 16 16C12 20 8 16 8 16Z" fill="#1E6B4A"/>
        </svg>
        <div class="page-number">8</div>
    </div>

    <div class="page-content">
        <div class="section-box">
            <h2>Industry Projections</h2>
        </div>

        <?php if ($overview) : ?>
        <p class="mb-lg"><?php echo esc_html($overview); ?></p>
        <?php endif; ?>

        <?php if (!empty($market_size)) : ?>
        <div class="highlight-box mb-lg">
            <div class="two-column">
                <div>
                    <div class="data-label">Current Market Size</div>
                    <div style="font-family: 'Playfair Display', Georgia, serif; font-size: 24pt; font-weight: 700; color: var(--color-navy);">
                        <?php echo esc_html(sffc_brief_get($market_size, 'current', 'N/A')); ?>
                    </div>
                    <div style="font-size: 10pt; color: var(--color-text-light);">
                        <?php echo esc_html(sffc_brief_get($market_size, 'current_year', date('Y'))); ?>
                    </div>
                </div>
                <div>
                    <div class="data-label">Projected Market Size</div>
                    <div style="font-family: 'Playfair Display', Georgia, serif; font-size: 24pt; font-weight: 700; color: var(--color-primary);">
                        <?php echo esc_html(sffc_brief_get($market_size, 'projected', 'N/A')); ?>
                    </div>
                    <div style="font-size: 10pt; color: var(--color-text-light);">
                        <?php echo esc_html(sffc_brief_get($market_size, 'projected_year', date('Y') + 5)); ?>
                        <?php if (sffc_brief_get($market_size, 'cagr', '')) : ?>
                        (CAGR: <?php echo esc_html(sffc_brief_get($market_size, 'cagr', '')); ?>)
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($growth_forecasts)) : ?>
        <div class="mb-lg">
            <h4 class="heading-section mb-sm" style="font-size: 10pt;">Growth Forecasts</h4>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Metric</th>
                        <th class="numeric"><?php echo esc_html(date('Y')); ?></th>
                        <th class="numeric"><?php echo esc_html(date('Y') + 1); ?></th>
                        <th class="numeric"><?php echo esc_html(date('Y') + 2); ?></th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($growth_forecasts as $forecast) : ?>
                    <tr>
                        <td><strong><?php echo esc_html(sffc_brief_get($forecast, 'metric', '')); ?></strong></td>
                        <td class="numeric"><?php echo esc_html(sffc_brief_get($forecast, 'year_0', 'N/A')); ?></td>
                        <td class="numeric"><?php echo esc_html(sffc_brief_get($forecast, 'year_1', 'N/A')); ?></td>
                        <td class="numeric"><?php echo esc_html(sffc_brief_get($forecast, 'year_2', 'N/A')); ?></td>
                        <td style="font-size: 9pt; color: var(--color-text-light);"><?php echo esc_html(sffc_brief_get($forecast, 'source', '')); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($chart_url) : ?>
        <div class="chart-container mb-lg">
            <div class="chart-title">Industry Growth Projection</div>
            <img src="<?php echo esc_url($chart_url); ?>" alt="Projection Chart" class="chart-image" style="max-height: 150pt;">
            <div class="chart-source">Source: Industry reports, analyst estimates</div>
        </div>
        <?php endif; ?>

        <div class="two-column">
            <?php if (!empty($drivers)) : ?>
            <div>
                <div class="info-box" style="border-left-color: var(--color-risk-low);">
                    <div class="info-box-title" style="color: var(--color-risk-low);">Key Growth Drivers</div>
                    <ul class="bullet-list mb-0">
                        <?php foreach ($drivers as $driver) : ?>
                        <li style="font-size: 10pt;"><?php echo esc_html($driver); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($headwinds)) : ?>
            <div>
                <div class="info-box" style="border-left-color: var(--color-risk-high);">
                    <div class="info-box-title" style="color: var(--color-risk-high);">Headwinds & Challenges</div>
                    <ul class="bullet-list mb-0">
                        <?php foreach ($headwinds as $headwind) : ?>
                        <li style="font-size: 10pt;"><?php echo esc_html($headwind); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="page-footer">
        <div class="footer-brand">MENA Careers Finance</div>
        <div class="footer-confidential">Intelligence Brief</div>
    </div>
</div>
