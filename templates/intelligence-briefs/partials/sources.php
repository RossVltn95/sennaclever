<?php
/**
 * Intelligence Brief - Sources & Methodology
 * Page 12 of 12
 */

if (!defined('ABSPATH')) {
    exit;
}

$sources = sffc_brief_get($brief, 'sources', array());
$methodology = sffc_brief_get($brief, 'methodology', '');
$confidence_explanation = sffc_brief_get($brief, 'confidence_explanation', '');
$disclaimer = sffc_brief_get($brief, 'disclaimer', '');
$generated_date = sffc_brief_get($brief, 'generated_date', date('F j, Y'));

// Default disclaimer if none provided
if (empty($disclaimer)) {
    $disclaimer = 'This intelligence brief is provided for informational purposes only and does not constitute investment advice, financial advice, or any other type of professional advice. The information contained herein has been compiled from sources believed to be reliable, but no representation or warranty, express or implied, is made as to its accuracy, completeness, or correctness. Past performance is not indicative of future results. Readers should conduct their own due diligence and consult with qualified professionals before making any investment decisions.';
}

// Default methodology if none provided
if (empty($methodology)) {
    $methodology = 'This brief was compiled using a combination of publicly available data sources, regulatory filings, news reports, and proprietary analysis. Financial data was sourced from company disclosures and market data providers. Market context was derived from industry reports and comparable transaction analysis.';
}

// Organize sources by type
$source_types = array(
    'primary' => array('label' => 'Primary Sources', 'sources' => array()),
    'financial' => array('label' => 'Financial Data', 'sources' => array()),
    'market' => array('label' => 'Market Data', 'sources' => array()),
    'news' => array('label' => 'News & Media', 'sources' => array()),
    'other' => array('label' => 'Additional Sources', 'sources' => array()),
);

foreach ($sources as $source) {
    $type = strtolower(sffc_brief_get($source, 'type', 'other'));
    if (!isset($source_types[$type])) {
        $type = 'other';
    }
    $source_types[$type]['sources'][] = $source;
}
?>

<div class="page">
    <div class="page-header">
        <svg class="logo-mark" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="8" cy="8" r="4" fill="#1E6B4A"/>
            <path d="M8 16C8 16 4 20 4 28C4 32 6 36 12 36C18 36 28 36 32 32C36 28 36 20 32 16C28 12 20 12 16 16C12 20 8 16 8 16Z" fill="#1E6B4A"/>
        </svg>
        <div class="page-number">12</div>
    </div>

    <div class="page-content">
        <div class="section-box">
            <h2>Sources & Methodology</h2>
        </div>

        <!-- Data Sources -->
        <div class="mb-lg">
            <h4 class="heading-section mb-md" style="font-size: 10pt;">Data Sources</h4>

            <?php foreach ($source_types as $type => $type_data) : ?>
                <?php if (!empty($type_data['sources'])) : ?>
                <div class="mb-md">
                    <h5 style="font-size: 9pt; color: var(--color-text-light); text-transform: uppercase; letter-spacing: 0.5pt; margin-bottom: 6pt;">
                        <?php echo esc_html($type_data['label']); ?>
                    </h5>
                    <ul class="source-list">
                        <?php foreach ($type_data['sources'] as $source) : ?>
                        <li class="source-item">
                            <span class="source-name"><?php echo esc_html(sffc_brief_get($source, 'name', '')); ?></span>
                            <?php if (sffc_brief_get($source, 'url', '')) : ?>
                            <br><span class="source-url"><?php echo esc_html(sffc_brief_get($source, 'url', '')); ?></span>
                            <?php endif; ?>
                            <?php if (sffc_brief_get($source, 'date', '')) : ?>
                            <span class="source-date"> - Accessed <?php echo esc_html(sffc_brief_get($source, 'date', '')); ?></span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Methodology -->
        <div class="info-box mb-lg">
            <div class="info-box-title">Methodology</div>
            <p class="mb-0" style="font-size: 10pt;"><?php echo esc_html($methodology); ?></p>
        </div>

        <!-- Confidence Levels Explained -->
        <?php if ($confidence_explanation) : ?>
        <div class="mb-lg">
            <h4 class="heading-section mb-sm" style="font-size: 10pt;">Confidence Levels</h4>
            <p style="font-size: 10pt;"><?php echo esc_html($confidence_explanation); ?></p>
        </div>
        <?php else : ?>
        <div class="mb-lg">
            <h4 class="heading-section mb-sm" style="font-size: 10pt;">Confidence Levels</h4>
            <div style="font-size: 10pt;">
                <p class="mb-sm"><strong style="color: var(--color-confidence-high);">HIGH</strong> - Data verified from 2+ authoritative sources (regulatory filings, official company statements)</p>
                <p class="mb-sm"><strong style="color: var(--color-confidence-medium);">MEDIUM</strong> - Data from single authoritative source or multiple secondary sources</p>
                <p class="mb-0"><strong style="color: var(--color-confidence-low);">LOW</strong> - Data from unverified sources or estimates based on comparable analysis</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Disclaimer -->
        <div style="background: var(--color-background); border: 1px solid var(--color-border); padding: var(--spacing-md); font-size: 9pt; color: var(--color-text-light);">
            <strong style="text-transform: uppercase; letter-spacing: 0.5pt;">Disclaimer</strong>
            <p class="mb-0" style="margin-top: 6pt;"><?php echo esc_html($disclaimer); ?></p>
        </div>

        <!-- Generated Info -->
        <div style="margin-top: var(--spacing-lg); text-align: center; font-size: 9pt; color: var(--color-text-light);">
            <p class="mb-sm">
                <strong>Generated:</strong> <?php echo esc_html($generated_date); ?>
            </p>
            <p class="mb-0">
                <strong>MENA CAREERS FINANCE</strong> | Intelligence & Analytics
            </p>
        </div>
    </div>

    <div class="page-footer">
        <div class="footer-brand">MENA Careers Finance</div>
        <div class="footer-confidential">Intelligence Brief</div>
    </div>
</div>
