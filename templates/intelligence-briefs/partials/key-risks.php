<?php
/**
 * Intelligence Brief - Key Risks
 * Page 10 of 12
 */

if (!defined('ABSPATH')) {
    exit;
}

$risks = sffc_brief_get($brief, 'risks', array());
$risk_summary = sffc_brief_get($brief, 'risk_summary', '');

// Organize risks by category
$risk_categories = array(
    'regulatory' => array('label' => 'Regulatory Risks', 'risks' => array()),
    'market' => array('label' => 'Market Risks', 'risks' => array()),
    'execution' => array('label' => 'Execution Risks', 'risks' => array()),
    'financial' => array('label' => 'Financial Risks', 'risks' => array()),
    'operational' => array('label' => 'Operational Risks', 'risks' => array()),
    'other' => array('label' => 'Other Risks', 'risks' => array()),
);

foreach ($risks as $risk) {
    $category = strtolower(sffc_brief_get($risk, 'category', 'other'));
    if (!isset($risk_categories[$category])) {
        $category = 'other';
    }
    $risk_categories[$category]['risks'][] = $risk;
}

// Count risks by severity
$severity_counts = array('high' => 0, 'medium' => 0, 'low' => 0);
foreach ($risks as $risk) {
    $severity = strtolower(sffc_brief_get($risk, 'severity', 'medium'));
    if (isset($severity_counts[$severity])) {
        $severity_counts[$severity]++;
    }
}
?>

<div class="page">
    <div class="page-header">
        <svg class="logo-mark" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="8" cy="8" r="4" fill="#1E6B4A"/>
            <path d="M8 16C8 16 4 20 4 28C4 32 6 36 12 36C18 36 28 36 32 32C36 28 36 20 32 16C28 12 20 12 16 16C12 20 8 16 8 16Z" fill="#1E6B4A"/>
        </svg>
        <div class="page-number">10</div>
    </div>

    <div class="page-content">
        <div class="section-box">
            <h2>Key Risks</h2>
        </div>

        <!-- Risk Summary Box -->
        <div class="metrics-grid mb-lg">
            <div class="metrics-row">
                <div class="metric-card" style="border-left: 4px solid var(--color-risk-high);">
                    <div class="metric-value" style="color: var(--color-risk-high);"><?php echo esc_html($severity_counts['high']); ?></div>
                    <div class="metric-label">High Severity</div>
                </div>
                <div class="metric-card" style="border-left: 4px solid var(--color-risk-medium);">
                    <div class="metric-value" style="color: var(--color-risk-medium);"><?php echo esc_html($severity_counts['medium']); ?></div>
                    <div class="metric-label">Medium Severity</div>
                </div>
                <div class="metric-card" style="border-left: 4px solid var(--color-risk-low);">
                    <div class="metric-value" style="color: var(--color-risk-low);"><?php echo esc_html($severity_counts['low']); ?></div>
                    <div class="metric-label">Low Severity</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo esc_html(count($risks)); ?></div>
                    <div class="metric-label">Total Risks</div>
                </div>
            </div>
        </div>

        <?php if ($risk_summary) : ?>
        <p class="mb-lg"><?php echo esc_html($risk_summary); ?></p>
        <?php endif; ?>

        <!-- Risk List -->
        <ul class="risk-list">
            <?php
            // Show high severity first, then medium, then low
            $sorted_risks = array();
            foreach ($risks as $risk) {
                $severity = strtolower(sffc_brief_get($risk, 'severity', 'medium'));
                $order = array('high' => 0, 'medium' => 1, 'low' => 2);
                $risk['_sort'] = isset($order[$severity]) ? $order[$severity] : 1;
                $sorted_risks[] = $risk;
            }
            usort($sorted_risks, function($a, $b) {
                return $a['_sort'] - $b['_sort'];
            });

            foreach ($sorted_risks as $risk) :
                $severity = strtolower(sffc_brief_get($risk, 'severity', 'medium'));
                $category = sffc_brief_get($risk, 'category', '');
            ?>
            <li class="risk-item">
                <div class="risk-severity <?php echo esc_attr($severity); ?>"></div>
                <div class="risk-content">
                    <div class="risk-title"><?php echo esc_html(sffc_brief_get($risk, 'title', '')); ?></div>
                    <?php if (sffc_brief_get($risk, 'description', '')) : ?>
                    <div class="risk-description"><?php echo esc_html(sffc_brief_get($risk, 'description', '')); ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($category) : ?>
                <div class="risk-category"><?php echo esc_html(ucfirst($category)); ?></div>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>

        <!-- Mitigation Note -->
        <?php if (sffc_brief_get($brief, 'risk_mitigation', '')) : ?>
        <div class="info-box mt-lg">
            <div class="info-box-title">Risk Mitigation Factors</div>
            <p class="mb-0"><?php echo esc_html(sffc_brief_get($brief, 'risk_mitigation', '')); ?></p>
        </div>
        <?php endif; ?>
    </div>

    <div class="page-footer">
        <div class="footer-brand">MENA Careers Finance</div>
        <div class="footer-confidential">Intelligence Brief</div>
    </div>
</div>
