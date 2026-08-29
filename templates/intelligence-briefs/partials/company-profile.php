<?php
/**
 * Intelligence Brief - Company Profile
 * Page 3 of 12
 */

if (!defined('ABSPATH')) {
    exit;
}

$company = sffc_brief_get($brief, 'company', array());
$name = sffc_brief_get($company, 'name', 'Company');
$description = sffc_brief_get($company, 'description', '');
$founded = sffc_brief_get($company, 'founded', '');
$headquarters = sffc_brief_get($company, 'headquarters', '');
$employees = sffc_brief_get($company, 'employees', '');
$revenue = sffc_brief_get($company, 'revenue', '');
$industry = sffc_brief_get($company, 'industry', '');
$business_model = sffc_brief_get($company, 'business_model', '');
$market_position = sffc_brief_get($company, 'market_position', '');
$ownership = sffc_brief_get($company, 'ownership', '');
$website = sffc_brief_get($company, 'website', '');

// Get company initial for logo placeholder
$initial = strtoupper(substr($name, 0, 1));
?>

<div class="page">
    <div class="page-header">
        <svg class="logo-mark" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="8" cy="8" r="4" fill="#1E6B4A"/>
            <path d="M8 16C8 16 4 20 4 28C4 32 6 36 12 36C18 36 28 36 32 32C36 28 36 20 32 16C28 12 20 12 16 16C12 20 8 16 8 16Z" fill="#1E6B4A"/>
        </svg>
        <div class="page-number">3</div>
    </div>

    <div class="page-content">
        <div class="section-box">
            <h2>The Company</h2>
        </div>

        <div class="company-card">
            <div class="company-header">
                <div class="company-logo"><?php echo esc_html($initial); ?></div>
                <div class="company-info">
                    <h3><?php echo esc_html($name); ?></h3>
                    <?php if ($industry) : ?>
                    <div class="company-tagline"><?php echo esc_html(ucfirst(str_replace('_', ' ', $industry))); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="company-facts">
                <?php if ($founded) : ?>
                <div class="company-fact">
                    <div class="company-fact-label">Founded</div>
                    <div class="company-fact-value"><?php echo esc_html($founded); ?></div>
                </div>
                <?php endif; ?>

                <?php if ($headquarters) : ?>
                <div class="company-fact">
                    <div class="company-fact-label">Headquarters</div>
                    <div class="company-fact-value"><?php echo esc_html($headquarters); ?></div>
                </div>
                <?php endif; ?>

                <?php if ($employees) : ?>
                <div class="company-fact">
                    <div class="company-fact-label">Employees</div>
                    <div class="company-fact-value"><?php echo esc_html($employees); ?></div>
                </div>
                <?php endif; ?>

                <?php if ($revenue) : ?>
                <div class="company-fact">
                    <div class="company-fact-label">Revenue</div>
                    <div class="company-fact-value"><?php echo esc_html($revenue); ?></div>
                </div>
                <?php endif; ?>

                <?php if ($website) : ?>
                <div class="company-fact">
                    <div class="company-fact-label">Website</div>
                    <div class="company-fact-value"><?php echo esc_html($website); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($description) : ?>
        <div class="mb-lg">
            <h4 class="heading-section mb-sm" style="font-size: 10pt;">Company Overview</h4>
            <p><?php echo esc_html($description); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($business_model) : ?>
        <div class="info-box mb-lg">
            <div class="info-box-title">Business Model</div>
            <p class="mb-0"><?php echo esc_html($business_model); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($market_position) : ?>
        <div class="mb-lg">
            <h4 class="heading-section mb-sm" style="font-size: 10pt;">Market Position</h4>
            <p><?php echo esc_html($market_position); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($ownership) : ?>
        <div class="highlight-box">
            <h4 class="heading-section mb-sm" style="font-size: 10pt; color: var(--color-primary);">Ownership Structure</h4>
            <p class="mb-0"><?php echo esc_html($ownership); ?></p>
        </div>
        <?php endif; ?>
    </div>

    <div class="page-footer">
        <div class="footer-brand">MENA Careers Finance</div>
        <div class="footer-confidential">Intelligence Brief</div>
    </div>
</div>
