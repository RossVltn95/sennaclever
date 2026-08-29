<?php
/**
 * Intelligence Brief - Strategic Analysis
 * Page 9 of 12
 */

if (!defined('ABSPATH')) {
    exit;
}

$strategic = sffc_brief_get($brief, 'strategic_analysis', array());
$rationale = sffc_brief_get($strategic, 'rationale', '');
$why_happening = sffc_brief_get($strategic, 'why_happening', '');
$competitive_implications = sffc_brief_get($strategic, 'competitive_implications', '');
$synergies = sffc_brief_get($strategic, 'synergies', array());
$winners = sffc_brief_get($strategic, 'winners', array());
$losers = sffc_brief_get($strategic, 'losers', array());
?>

<div class="page">
    <div class="page-header">
        <svg class="logo-mark" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="8" cy="8" r="4" fill="#1E6B4A"/>
            <path d="M8 16C8 16 4 20 4 28C4 32 6 36 12 36C18 36 28 36 32 32C36 28 36 20 32 16C28 12 20 12 16 16C12 20 8 16 8 16Z" fill="#1E6B4A"/>
        </svg>
        <div class="page-number">9</div>
    </div>

    <div class="page-content">
        <div class="section-box">
            <h2>Strategic Analysis</h2>
        </div>

        <?php if ($why_happening) : ?>
        <div class="highlight-box mb-lg">
            <h4 class="heading-section mb-sm" style="font-size: 10pt; color: var(--color-primary);">Why Is This Happening?</h4>
            <p class="mb-0"><?php echo esc_html($why_happening); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($rationale) : ?>
        <div class="mb-lg">
            <h4 class="heading-section mb-sm" style="font-size: 10pt;">Strategic Rationale</h4>
            <p><?php echo esc_html($rationale); ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($synergies)) : ?>
        <div class="info-box mb-lg">
            <div class="info-box-title">Expected Synergies & Benefits</div>
            <ol class="numbered-list mb-0">
                <?php foreach ($synergies as $synergy) : ?>
                <li>
                    <?php if (is_array($synergy)) : ?>
                    <strong><?php echo esc_html(sffc_brief_get($synergy, 'title', '')); ?></strong>
                    <?php if (sffc_brief_get($synergy, 'description', '')) : ?>
                    <br><span style="font-size: 10pt; color: var(--color-text-light);"><?php echo esc_html(sffc_brief_get($synergy, 'description', '')); ?></span>
                    <?php endif; ?>
                    <?php else : ?>
                    <?php echo esc_html($synergy); ?>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <?php endif; ?>

        <?php if ($competitive_implications) : ?>
        <div class="mb-lg">
            <h4 class="heading-section mb-sm" style="font-size: 10pt;">Competitive Implications</h4>
            <p><?php echo esc_html($competitive_implications); ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($winners) || !empty($losers)) : ?>
        <div class="two-column">
            <?php if (!empty($winners)) : ?>
            <div>
                <div class="info-box" style="border-left-color: var(--color-risk-low);">
                    <div class="info-box-title" style="color: var(--color-risk-low);">Winners</div>
                    <ul class="bullet-list mb-0">
                        <?php foreach ($winners as $winner) : ?>
                        <li>
                            <?php if (is_array($winner)) : ?>
                            <strong><?php echo esc_html(sffc_brief_get($winner, 'name', '')); ?></strong>
                            <?php if (sffc_brief_get($winner, 'reason', '')) : ?>
                            <br><span style="font-size: 9pt; color: var(--color-text-light);"><?php echo esc_html(sffc_brief_get($winner, 'reason', '')); ?></span>
                            <?php endif; ?>
                            <?php else : ?>
                            <?php echo esc_html($winner); ?>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($losers)) : ?>
            <div>
                <div class="info-box" style="border-left-color: var(--color-risk-high);">
                    <div class="info-box-title" style="color: var(--color-risk-high);">Losers / At Risk</div>
                    <ul class="bullet-list mb-0">
                        <?php foreach ($losers as $loser) : ?>
                        <li>
                            <?php if (is_array($loser)) : ?>
                            <strong><?php echo esc_html(sffc_brief_get($loser, 'name', '')); ?></strong>
                            <?php if (sffc_brief_get($loser, 'reason', '')) : ?>
                            <br><span style="font-size: 9pt; color: var(--color-text-light);"><?php echo esc_html(sffc_brief_get($loser, 'reason', '')); ?></span>
                            <?php endif; ?>
                            <?php else : ?>
                            <?php echo esc_html($loser); ?>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="page-footer">
        <div class="footer-brand">MENA Careers Finance</div>
        <div class="footer-confidential">Intelligence Brief</div>
    </div>
</div>
