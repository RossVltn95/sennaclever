<?php
/**
 * Intelligence Brief - Executive Summary
 * Page 2 of 12
 */

if (!defined('ABSPATH')) {
    exit;
}

$summary = sffc_brief_get($brief, 'executive_summary.overview', '');
$key_points = sffc_brief_get($brief, 'executive_summary.key_points', array());
$metrics = sffc_brief_get($brief, 'executive_summary.metrics', array());
$confidence = sffc_brief_get($brief, 'executive_summary.confidence', 'medium');

// Confidence level to dots
$confidence_levels = array('low' => 1, 'medium' => 2, 'high' => 3);
$confidence_dots = isset($confidence_levels[$confidence]) ? $confidence_levels[$confidence] : 2;
?>

<div class="page">
    <div class="page-header">
        <svg class="logo-mark" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="8" cy="8" r="4" fill="#1E6B4A"/>
            <path d="M8 16C8 16 4 20 4 28C4 32 6 36 12 36C18 36 28 36 32 32C36 28 36 20 32 16C28 12 20 12 16 16C12 20 8 16 8 16Z" fill="#1E6B4A"/>
        </svg>
        <div class="page-number">2</div>
    </div>

    <div class="page-content">
        <div class="section-box">
            <h2>Executive Summary</h2>
        </div>

        <?php if ($summary) : ?>
        <p class="mb-lg" style="font-size: 12pt; line-height: 1.6;"><?php echo esc_html($summary); ?></p>
        <?php endif; ?>

        <?php if (!empty($metrics)) : ?>
        <div class="metrics-grid mb-lg">
            <div class="metrics-row">
                <?php
                $metric_count = 0;
                foreach ($metrics as $metric) :
                    if ($metric_count >= 4) break;
                    $metric_count++;
                ?>
                <div class="metric-card">
                    <div class="metric-value"><?php echo esc_html($metric['value']); ?></div>
                    <div class="metric-label"><?php echo esc_html($metric['label']); ?></div>
                    <?php if (!empty($metric['change'])) : ?>
                    <div class="metric-change <?php echo floatval($metric['change']) >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo floatval($metric['change']) >= 0 ? '+' : ''; ?><?php echo esc_html($metric['change']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($key_points)) : ?>
        <div class="info-box">
            <div class="info-box-title">What You Need to Know</div>
            <ul class="bullet-list">
                <?php foreach ($key_points as $point) : ?>
                <li><?php echo esc_html($point); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="confidence-indicator" style="margin-top: var(--spacing-xl);">
            <span class="confidence-label">Analysis Confidence:</span>
            <div class="confidence-dots">
                <?php for ($i = 1; $i <= 3; $i++) : ?>
                <div class="confidence-dot <?php echo $i <= $confidence_dots ? 'filled' : ''; ?>"></div>
                <?php endfor; ?>
            </div>
            <span style="text-transform: capitalize; font-weight: 600;"><?php echo esc_html($confidence); ?></span>
        </div>
    </div>

    <div class="page-footer">
        <div class="footer-brand">MENA Careers Finance</div>
        <div class="footer-confidential">Intelligence Brief</div>
    </div>
</div>
