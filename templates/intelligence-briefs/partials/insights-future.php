<?php
/**
 * Intelligence Brief - Unique Insights & Future Outlook
 * Page 11 of 12
 */

if (!defined('ABSPATH')) {
    exit;
}

$insights = sffc_brief_get($brief, 'insights', array());
$unique_observations = sffc_brief_get($insights, 'unique_observations', array());
$what_to_watch = sffc_brief_get($insights, 'what_to_watch', array());
$potential_outcomes = sffc_brief_get($insights, 'potential_outcomes', array());
$next_steps = sffc_brief_get($insights, 'next_steps', array());
$outlook_summary = sffc_brief_get($insights, 'outlook_summary', '');
?>

<div class="page">
    <div class="page-header">
        <svg class="logo-mark" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="8" cy="8" r="4" fill="#1E6B4A"/>
            <path d="M8 16C8 16 4 20 4 28C4 32 6 36 12 36C18 36 28 36 32 32C36 28 36 20 32 16C28 12 20 12 16 16C12 20 8 16 8 16Z" fill="#1E6B4A"/>
        </svg>
        <div class="page-number">11</div>
    </div>

    <div class="page-content">
        <div class="section-box">
            <h2>Insights & Future Outlook</h2>
        </div>

        <?php if (!empty($unique_observations)) : ?>
        <div class="mb-lg">
            <h4 class="heading-section mb-md" style="font-size: 10pt;">Non-Obvious Observations</h4>
            <ol class="numbered-list">
                <?php foreach ($unique_observations as $observation) : ?>
                <li>
                    <?php if (is_array($observation)) : ?>
                    <strong><?php echo esc_html(sffc_brief_get($observation, 'title', '')); ?></strong>
                    <?php if (sffc_brief_get($observation, 'detail', '')) : ?>
                    <br><span style="color: var(--color-text-light);"><?php echo esc_html(sffc_brief_get($observation, 'detail', '')); ?></span>
                    <?php endif; ?>
                    <?php else : ?>
                    <?php echo esc_html($observation); ?>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <?php endif; ?>

        <?php if (!empty($what_to_watch)) : ?>
        <div class="highlight-box mb-lg">
            <h4 class="heading-section mb-sm" style="font-size: 10pt; color: var(--color-primary);">What to Watch For</h4>
            <ul class="bullet-list mb-0">
                <?php foreach ($what_to_watch as $item) : ?>
                <li><?php echo esc_html($item); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($potential_outcomes)) : ?>
        <div class="mb-lg">
            <h4 class="heading-section mb-md" style="font-size: 10pt;">Potential Outcomes</h4>
            <div class="two-column">
                <?php
                $outcome_count = 0;
                foreach ($potential_outcomes as $outcome) :
                    if ($outcome_count % 2 === 0 && $outcome_count > 0) echo '</div><div class="two-column">';
                    $outcome_count++;
                    $probability = sffc_brief_get($outcome, 'probability', 'medium');
                    $prob_color = 'var(--color-text-light)';
                    if ($probability === 'high' || $probability === 'likely') $prob_color = 'var(--color-risk-low)';
                    if ($probability === 'low' || $probability === 'unlikely') $prob_color = 'var(--color-risk-high)';
                ?>
                <div>
                    <div class="info-box" style="margin-bottom: var(--spacing-md);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6pt;">
                            <strong><?php echo esc_html(sffc_brief_get($outcome, 'scenario', '')); ?></strong>
                            <span style="font-size: 9pt; color: <?php echo $prob_color; ?>; text-transform: uppercase;">
                                <?php echo esc_html(ucfirst($probability)); ?>
                            </span>
                        </div>
                        <p class="mb-0" style="font-size: 10pt; color: var(--color-text-light);">
                            <?php echo esc_html(sffc_brief_get($outcome, 'description', '')); ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($next_steps)) : ?>
        <div class="mb-lg">
            <h4 class="heading-section mb-md" style="font-size: 10pt;">Timeline of Next Steps</h4>
            <div class="timeline">
                <?php foreach ($next_steps as $step) : ?>
                <div class="timeline-item <?php echo esc_attr(sffc_brief_get($step, 'status', 'pending')); ?>">
                    <div class="timeline-date"><?php echo esc_html(sffc_brief_get($step, 'timeframe', '')); ?></div>
                    <div class="timeline-title"><?php echo esc_html(sffc_brief_get($step, 'event', '')); ?></div>
                    <?php if (sffc_brief_get($step, 'detail', '')) : ?>
                    <div class="timeline-description"><?php echo esc_html(sffc_brief_get($step, 'detail', '')); ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($outlook_summary) : ?>
        <div class="info-box" style="border-left-color: var(--color-green);">
            <div class="info-box-title" style="color: var(--color-green);">Outlook Summary</div>
            <p class="mb-0"><?php echo esc_html($outlook_summary); ?></p>
        </div>
        <?php endif; ?>
    </div>

    <div class="page-footer">
        <div class="footer-brand">MENA Careers Finance</div>
        <div class="footer-confidential">Intelligence Brief</div>
    </div>
</div>
