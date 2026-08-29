<?php
/**
 * Recruiter Terminal - Dashboard View
 *
 * Displays campaign list with statistics.
 *
 * @package SennaFinanceCareer
 * @subpackage RecruiterTerminal
 *
 * Variables available from parent template:
 * - $campaigns (array): User's campaigns
 * - $status_config (array): Status labels and classes
 * - $base_url (string): Base URL for navigation
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="rt-dashboard">
    <!-- Dashboard Header -->
    <div class="rt-dashboard__header">
        <div class="rt-dashboard__title">
            <h1><?php esc_html_e('Campaign Dashboard', 'senna-finance'); ?></h1>
            <p class="rt-dashboard__subtitle"><?php esc_html_e('Manage your talent outreach campaigns', 'senna-finance'); ?></p>
        </div>
        <div class="rt-dashboard__actions">
            <a href="<?php echo esc_url(add_query_arg('view', 'create', $base_url)); ?>" class="rt-btn rt-btn--primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                <span><?php esc_html_e('New Campaign', 'senna-finance'); ?></span>
            </a>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="rt-stats-bar">
        <?php
        $total_campaigns = count($campaigns);
        $active_count = 0;
        $pending_count = 0;
        $completed_count = 0;
        $draft_count = 0;

        foreach ($campaigns as $c) {
            if ($c->status === 'active') $active_count++;
            if ($c->status === 'pending_review') $pending_count++;
            if ($c->status === 'completed') $completed_count++;
            if ($c->status === 'draft') $draft_count++;
        }
        ?>
        <div class="rt-stats-bar__item">
            <span class="rt-stats-bar__value"><?php echo esc_html($total_campaigns); ?></span>
            <span class="rt-stats-bar__label"><?php esc_html_e('Total', 'senna-finance'); ?></span>
        </div>
        <div class="rt-stats-bar__item rt-stats-bar__item--active">
            <span class="rt-stats-bar__value"><?php echo esc_html($active_count); ?></span>
            <span class="rt-stats-bar__label"><?php esc_html_e('Active', 'senna-finance'); ?></span>
        </div>
        <div class="rt-stats-bar__item rt-stats-bar__item--pending">
            <span class="rt-stats-bar__value"><?php echo esc_html($pending_count); ?></span>
            <span class="rt-stats-bar__label"><?php esc_html_e('Pending', 'senna-finance'); ?></span>
        </div>
        <div class="rt-stats-bar__item rt-stats-bar__item--completed">
            <span class="rt-stats-bar__value"><?php echo esc_html($completed_count); ?></span>
            <span class="rt-stats-bar__label"><?php esc_html_e('Completed', 'senna-finance'); ?></span>
        </div>
    </div>

    <!-- Status Filters -->
    <?php if (!empty($campaigns)) : ?>
    <div class="rt-filters">
        <button type="button" class="rt-filter rt-filter--active" data-filter="all">
            <?php esc_html_e('All', 'senna-finance'); ?>
            <span class="rt-filter__count"><?php echo esc_html($total_campaigns); ?></span>
        </button>
        <button type="button" class="rt-filter" data-filter="draft">
            <?php esc_html_e('Draft', 'senna-finance'); ?>
            <span class="rt-filter__count"><?php echo esc_html($draft_count); ?></span>
        </button>
        <button type="button" class="rt-filter" data-filter="active">
            <?php esc_html_e('Active', 'senna-finance'); ?>
            <span class="rt-filter__count"><?php echo esc_html($active_count); ?></span>
        </button>
        <button type="button" class="rt-filter" data-filter="pending_review">
            <?php esc_html_e('Pending', 'senna-finance'); ?>
            <span class="rt-filter__count"><?php echo esc_html($pending_count); ?></span>
        </button>
        <button type="button" class="rt-filter" data-filter="completed">
            <?php esc_html_e('Completed', 'senna-finance'); ?>
            <span class="rt-filter__count"><?php echo esc_html($completed_count); ?></span>
        </button>
    </div>
    <?php endif; ?>

    <!-- Campaign Grid -->
    <?php if (empty($campaigns)) : ?>
        <div class="rt-empty">
            <div class="rt-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="2" y="3" width="20" height="14" rx="2"/>
                    <path d="M8 21h8"/>
                    <path d="M12 17v4"/>
                    <path d="M7 8h4"/>
                    <path d="M7 11h2"/>
                </svg>
            </div>
            <h3><?php esc_html_e('No Campaigns Yet', 'senna-finance'); ?></h3>
            <p><?php esc_html_e('Create your first campaign to start reaching out to potential candidates.', 'senna-finance'); ?></p>
            <a href="<?php echo esc_url(add_query_arg('view', 'create', $base_url)); ?>" class="rt-btn rt-btn--primary">
                <?php esc_html_e('Create Campaign', 'senna-finance'); ?>
            </a>
        </div>
    <?php else : ?>
        <div class="rt-campaign-grid">
            <?php foreach ($campaigns as $campaign_item) :
                $item_stats = Recruiter_Terminal_DB::get_campaign_stats($campaign_item->id);
                $status_info = isset($status_config[$campaign_item->status]) ? $status_config[$campaign_item->status] : $status_config['draft'];
            ?>
                <div class="rt-card" data-campaign-id="<?php echo esc_attr($campaign_item->id); ?>" data-status="<?php echo esc_attr($campaign_item->status); ?>">
                    <div class="rt-card__header">
                        <h3 class="rt-card__title"><?php echo esc_html($campaign_item->title); ?></h3>
                        <span class="rt-badge rt-badge--<?php echo esc_attr($status_info['class']); ?>">
                            <?php echo esc_html($status_info['label']); ?>
                        </span>
                    </div>

                    <?php if (!empty($campaign_item->brief)) : ?>
                        <p class="rt-card__brief"><?php echo esc_html(wp_trim_words($campaign_item->brief, 20)); ?></p>
                    <?php endif; ?>

                    <div class="rt-card__stats">
                        <div class="rt-card__stat">
                            <span class="rt-card__stat-value"><?php echo esc_html($item_stats->total); ?></span>
                            <span class="rt-card__stat-label"><?php esc_html_e('Targeted', 'senna-finance'); ?></span>
                        </div>
                        <div class="rt-card__stat">
                            <span class="rt-card__stat-value"><?php echo esc_html($item_stats->sent); ?></span>
                            <span class="rt-card__stat-label"><?php esc_html_e('Sent', 'senna-finance'); ?></span>
                        </div>
                        <div class="rt-card__stat">
                            <span class="rt-card__stat-value"><?php echo esc_html($item_stats->opened); ?></span>
                            <span class="rt-card__stat-label"><?php esc_html_e('Opened', 'senna-finance'); ?></span>
                        </div>
                        <div class="rt-card__stat">
                            <span class="rt-card__stat-value"><?php echo esc_html($item_stats->responded); ?></span>
                            <span class="rt-card__stat-label"><?php esc_html_e('Responded', 'senna-finance'); ?></span>
                        </div>
                    </div>

                    <div class="rt-card__footer">
                        <span class="rt-card__date">
                            <?php
                            /* translators: %s: relative time ago */
                            printf(esc_html__('Created %s', 'senna-finance'), human_time_diff(strtotime($campaign_item->created_at), current_time('timestamp')) . ' ago');
                            ?>
                        </span>
                        <div class="rt-card__actions">
                            <?php if (in_array($campaign_item->status, array('draft', 'rejected'))) : ?>
                                <a href="<?php echo esc_url(add_query_arg(array('view' => 'edit', 'campaign' => $campaign_item->id), $base_url)); ?>" class="rt-btn rt-btn--small rt-btn--secondary">
                                    <?php esc_html_e('Edit', 'senna-finance'); ?>
                                </a>
                            <?php elseif (in_array($campaign_item->status, array('active', 'approved', 'paused', 'completed'))) : ?>
                                <a href="<?php echo esc_url(add_query_arg(array('view' => 'detail', 'campaign' => $campaign_item->id), $base_url)); ?>" class="rt-btn rt-btn--small rt-btn--secondary">
                                    <?php esc_html_e('View', 'senna-finance'); ?>
                                </a>
                            <?php elseif ($campaign_item->status === 'pending_review') : ?>
                                <span class="rt-card__pending-label"><?php esc_html_e('Awaiting Approval', 'senna-finance'); ?></span>
                            <?php endif; ?>

                            <?php if ($campaign_item->status === 'draft') : ?>
                                <button type="button" class="rt-btn rt-btn--small rt-btn--ghost rt-btn--danger" data-action="delete" data-campaign-id="<?php echo esc_attr($campaign_item->id); ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                        <polyline points="3,6 5,6 21,6"/>
                                        <path d="M19,6v14a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6M8,6V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2V6"/>
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
