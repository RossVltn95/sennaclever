<?php
/**
 * Intelligence Brief - Event Explained
 * Page 4 of 12
 */

if (!defined('ABSPATH')) {
    exit;
}

$event = sffc_brief_get($brief, 'event', array());
$event_type = sffc_brief_get($event, 'type', 'transaction');
$event_title = sffc_brief_get($event, 'title', '');
$event_description = sffc_brief_get($event, 'description', '');
$educational_content = sffc_brief_get($event, 'educational', '');
$how_it_works = sffc_brief_get($event, 'how_it_works', '');
$timeline = sffc_brief_get($event, 'timeline', array());
$key_terms = sffc_brief_get($event, 'key_terms', array());

// Event type labels for educational context
$event_labels = array(
    'ipo' => 'Initial Public Offering (IPO)',
    'merger' => 'Merger',
    'acquisition' => 'Acquisition',
    'm&a' => 'Merger & Acquisition',
    'fundraise' => 'Capital Raise / Fundraising',
    'restructuring' => 'Corporate Restructuring',
    'spinoff' => 'Spin-Off',
    'buyout' => 'Leveraged Buyout (LBO)',
    'spac' => 'SPAC Merger',
    'listing' => 'Stock Exchange Listing',
    'delisting' => 'Delisting',
    'dividend' => 'Special Dividend',
    'bankruptcy' => 'Bankruptcy / Restructuring',
);

$event_type_label = isset($event_labels[strtolower($event_type)])
    ? $event_labels[strtolower($event_type)]
    : ucfirst(str_replace('_', ' ', $event_type));
?>

<div class="page">
    <div class="page-header">
        <svg class="logo-mark" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="8" cy="8" r="4" fill="#1E6B4A"/>
            <path d="M8 16C8 16 4 20 4 28C4 32 6 36 12 36C18 36 28 36 32 32C36 28 36 20 32 16C28 12 20 12 16 16C12 20 8 16 8 16Z" fill="#1E6B4A"/>
        </svg>
        <div class="page-number">4</div>
    </div>

    <div class="page-content">
        <div class="section-box">
            <h2>The Event Explained</h2>
        </div>

        <div class="highlight-box mb-lg">
            <h3 style="font-family: 'Playfair Display', Georgia, serif; font-size: 16pt; color: var(--color-navy); margin-bottom: 8pt;">
                What is a<?php echo in_array(strtolower($event_type[0]), array('a', 'e', 'i', 'o', 'u')) ? 'n' : ''; ?> <?php echo esc_html($event_type_label); ?>?
            </h3>
            <?php if ($educational_content) : ?>
            <p class="mb-0"><?php echo esc_html($educational_content); ?></p>
            <?php else : ?>
            <p class="mb-0">
                <?php
                // Default educational content based on event type
                $default_educational = array(
                    'ipo' => 'An Initial Public Offering (IPO) is when a private company offers shares to the public for the first time. This allows the company to raise capital from public investors and provides an exit opportunity for early investors. The company must meet regulatory requirements and ongoing disclosure obligations.',
                    'acquisition' => 'An acquisition occurs when one company purchases most or all of another company\'s shares or assets to gain control. This can be friendly (approved by target\'s board) or hostile (directly to shareholders). The acquirer typically pays a premium above the current market price.',
                    'merger' => 'A merger is the combination of two companies into one new legal entity. Unlike an acquisition, mergers are typically between companies of similar size and are structured as a mutual decision. Shareholders of both companies receive shares in the new combined entity.',
                    'fundraise' => 'A capital raise or fundraising round is when a company issues new equity or debt to raise money from investors. This can be through private placements, venture capital rounds, or public offerings. The terms and valuation depend on the company\'s stage and market conditions.',
                    'buyout' => 'A Leveraged Buyout (LBO) is an acquisition financed primarily with debt, where the target company\'s assets and cash flows serve as collateral. Private equity firms commonly use LBOs to acquire companies, improve operations, and exit at a profit.',
                    'spac' => 'A SPAC (Special Purpose Acquisition Company) merger is when a private company goes public by merging with an already-listed shell company. This provides a faster path to public markets with more pricing certainty than a traditional IPO.',
                );
                echo esc_html(isset($default_educational[strtolower($event_type)]) ? $default_educational[strtolower($event_type)] : 'This transaction involves a significant corporate event that may impact the company\'s ownership structure, capital, or market position.');
                ?>
            </p>
            <?php endif; ?>
        </div>

        <?php if ($event_description || $event_title) : ?>
        <div class="mb-lg">
            <h4 class="heading-section mb-sm" style="font-size: 10pt;">This Specific Transaction</h4>
            <?php if ($event_title) : ?>
            <p><strong><?php echo esc_html($event_title); ?></strong></p>
            <?php endif; ?>
            <?php if ($event_description) : ?>
            <p><?php echo esc_html($event_description); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($how_it_works) : ?>
        <div class="info-box mb-lg">
            <div class="info-box-title">How This Transaction Works</div>
            <p class="mb-0"><?php echo esc_html($how_it_works); ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($timeline)) : ?>
        <div class="mb-lg">
            <h4 class="heading-section mb-md" style="font-size: 10pt;">Transaction Timeline</h4>
            <div class="timeline">
                <?php foreach ($timeline as $item) : ?>
                <div class="timeline-item <?php echo esc_attr(sffc_brief_get($item, 'status', 'pending')); ?>">
                    <div class="timeline-date"><?php echo esc_html(sffc_brief_get($item, 'date', '')); ?></div>
                    <div class="timeline-title"><?php echo esc_html(sffc_brief_get($item, 'title', '')); ?></div>
                    <?php if (sffc_brief_get($item, 'description', '')) : ?>
                    <div class="timeline-description"><?php echo esc_html(sffc_brief_get($item, 'description', '')); ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($key_terms)) : ?>
        <div class="info-box">
            <div class="info-box-title">Key Terms to Know</div>
            <dl class="mb-0" style="margin: 0;">
                <?php foreach ($key_terms as $term) : ?>
                <?php if (!empty($term['term'])) : ?>
                <dt style="font-weight: 600; margin-top: 8pt; margin-bottom: 2pt;"><?php echo esc_html($term['term']); ?></dt>
                <dd style="margin: 0 0 0 12pt; color: var(--color-text-light); font-size: 10pt;"><?php echo esc_html($term['definition'] ?? ''); ?></dd>
                <?php endif; ?>
                <?php endforeach; ?>
            </dl>
        </div>
        <?php endif; ?>
    </div>

    <div class="page-footer">
        <div class="footer-brand">MENA Careers Finance</div>
        <div class="footer-confidential">Intelligence Brief</div>
    </div>
</div>
