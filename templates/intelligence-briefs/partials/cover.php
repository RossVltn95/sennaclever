<?php
/**
 * Intelligence Brief - Cover Page
 * Page 1 of 12
 */

if (!defined('ABSPATH')) {
    exit;
}

$title = sffc_brief_get($brief, 'title', 'Intelligence Brief');
$date = sffc_brief_get($brief, 'date', date('F j, Y'));
$event_type = sffc_brief_get($brief, 'event.type', 'Analysis');
$sector = sffc_brief_get($brief, 'classification.sector', '');
$geography = sffc_brief_get($brief, 'classification.geography', '');
$classification = sffc_brief_get($brief, 'classification.level', 'For Professional Use');
?>

<div class="page page-cover">
    <div class="cover-header">
        <svg class="cover-logo" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="8" cy="8" r="4" fill="#1E6B4A"/>
            <path d="M8 16C8 16 4 20 4 28C4 32 6 36 12 36C18 36 28 36 32 32C36 28 36 20 32 16C28 12 20 12 16 16C12 20 8 16 8 16Z" fill="#1E6B4A"/>
        </svg>
        <div class="cover-badge"><?php echo esc_html(ucfirst($event_type)); ?></div>
    </div>

    <div class="cover-main">
        <div class="cover-label">Intelligence Brief</div>
        <h1 class="cover-title"><?php echo esc_html($title); ?></h1>
        <div class="cover-meta">
            <div class="cover-meta-item">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span><?php echo esc_html($date); ?></span>
            </div>
            <?php if ($sector) : ?>
            <div class="cover-meta-item">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                </svg>
                <span><?php echo esc_html(ucfirst(str_replace('_', ' ', $sector))); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($geography) : ?>
            <div class="cover-meta-item">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="2" y1="12" x2="22" y2="12"></line>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                </svg>
                <span><?php echo esc_html(ucfirst(str_replace('_', ' ', $geography))); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="cover-footer">
        <div class="cover-brand">MENA CAREERS FINANCE</div>
        <div class="cover-classification"><?php echo esc_html($classification); ?></div>
    </div>
</div>
