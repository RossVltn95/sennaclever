<?php
/**
 * Recruiter Terminal - Campaign Detail View
 *
 * Live tracking view for active/completed campaigns.
 *
 * @package SennaFinanceCareer
 * @subpackage RecruiterTerminal
 *
 * Variables available from parent template:
 * - $campaign (object): Campaign data
 * - $campaign_id (int): Campaign ID
 * - $targets (array): Campaign targets
 * - $stats (object): Campaign statistics
 * - $status_config (array): Status labels and classes
 * - $base_url (string): Base URL for navigation
 */

if (!defined('ABSPATH')) {
    exit;
}

$status_info = isset($status_config[$campaign->status]) ? $status_config[$campaign->status] : $status_config['draft'];

// Calculate rates
$open_rate = $stats->sent > 0 ? round(($stats->opened / $stats->sent) * 100, 1) : 0;
$response_rate = $stats->sent > 0 ? round(($stats->responded / $stats->sent) * 100, 1) : 0;
$interest_rate = $stats->responded > 0 ? round(($stats->interested / $stats->responded) * 100, 1) : 0;

// Get recent activity
$activity = Recruiter_Terminal_DB::get_campaign_activity($campaign_id, 20);
?>

<div class="rt-detail" data-campaign-id="<?php echo esc_attr($campaign_id); ?>">
    <!-- Detail Header -->
    <div class="rt-detail__header">
        <div class="rt-detail__breadcrumb">
            <a href="<?php echo esc_url($base_url); ?>" class="rt-breadcrumb__link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                <?php esc_html_e('All Campaigns', 'senna-finance'); ?>
            </a>
        </div>

        <div class="rt-detail__title-row">
            <div class="rt-detail__title-group">
                <h1 class="rt-detail__title"><?php echo esc_html($campaign->title); ?></h1>
                <span class="rt-badge rt-badge--<?php echo esc_attr($status_info['class']); ?> rt-badge--large">
                    <?php echo esc_html($status_info['label']); ?>
                </span>
            </div>

            <div class="rt-detail__actions">
                <?php if ($campaign->status === 'active') : ?>
                    <button type="button" class="rt-btn rt-btn--secondary" data-action="pause-campaign">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="6" y="4" width="4" height="16"/>
                            <rect x="14" y="4" width="4" height="16"/>
                        </svg>
                        <?php esc_html_e('Pause', 'senna-finance'); ?>
                    </button>
                <?php elseif ($campaign->status === 'paused') : ?>
                    <button type="button" class="rt-btn rt-btn--primary" data-action="resume-campaign">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="5 3 19 12 5 21 5 3"/>
                        </svg>
                        <?php esc_html_e('Resume', 'senna-finance'); ?>
                    </button>
                <?php endif; ?>

                <button type="button" class="rt-btn rt-btn--ghost" data-action="export-results">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    <?php esc_html_e('Export', 'senna-finance'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="rt-stats-grid">
        <div class="rt-stat-card">
            <div class="rt-stat-card__icon rt-stat-card__icon--total">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <div class="rt-stat-card__content">
                <span class="rt-stat-card__value"><?php echo esc_html($stats->total); ?></span>
                <span class="rt-stat-card__label"><?php esc_html_e('Total Targeted', 'senna-finance'); ?></span>
            </div>
        </div>

        <div class="rt-stat-card">
            <div class="rt-stat-card__icon rt-stat-card__icon--sent">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="22" y1="2" x2="11" y2="13"/>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
            </div>
            <div class="rt-stat-card__content">
                <span class="rt-stat-card__value"><?php echo esc_html($stats->sent); ?></span>
                <span class="rt-stat-card__label"><?php esc_html_e('Emails Sent', 'senna-finance'); ?></span>
            </div>
            <?php if ($stats->pending > 0) : ?>
                <div class="rt-stat-card__sub">
                    <span><?php printf(esc_html__('%d pending', 'senna-finance'), $stats->pending); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="rt-stat-card">
            <div class="rt-stat-card__icon rt-stat-card__icon--opened">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
            </div>
            <div class="rt-stat-card__content">
                <span class="rt-stat-card__value"><?php echo esc_html($stats->opened); ?></span>
                <span class="rt-stat-card__label"><?php esc_html_e('Opened', 'senna-finance'); ?></span>
            </div>
            <div class="rt-stat-card__rate">
                <span class="rt-stat-card__rate-value"><?php echo esc_html($open_rate); ?>%</span>
                <span class="rt-stat-card__rate-label"><?php esc_html_e('open rate', 'senna-finance'); ?></span>
            </div>
        </div>

        <div class="rt-stat-card rt-stat-card--highlight">
            <div class="rt-stat-card__icon rt-stat-card__icon--responded">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                </svg>
            </div>
            <div class="rt-stat-card__content">
                <span class="rt-stat-card__value"><?php echo esc_html($stats->responded); ?></span>
                <span class="rt-stat-card__label"><?php esc_html_e('Responded', 'senna-finance'); ?></span>
            </div>
            <div class="rt-stat-card__rate">
                <span class="rt-stat-card__rate-value"><?php echo esc_html($response_rate); ?>%</span>
                <span class="rt-stat-card__rate-label"><?php esc_html_e('response rate', 'senna-finance'); ?></span>
            </div>
        </div>
    </div>

    <!-- Response Breakdown -->
    <div class="rt-response-breakdown">
        <h3 class="rt-section-title"><?php esc_html_e('Response Breakdown', 'senna-finance'); ?></h3>
        <div class="rt-response-bars">
            <div class="rt-response-bar">
                <div class="rt-response-bar__label">
                    <span class="rt-response-bar__indicator rt-response-bar__indicator--interested"></span>
                    <?php esc_html_e('Interested', 'senna-finance'); ?>
                </div>
                <div class="rt-response-bar__track">
                    <div class="rt-response-bar__fill rt-response-bar__fill--interested" style="width: <?php echo esc_attr($interest_rate); ?>%;"></div>
                </div>
                <span class="rt-response-bar__value"><?php echo esc_html($stats->interested); ?></span>
            </div>
            <div class="rt-response-bar">
                <div class="rt-response-bar__label">
                    <span class="rt-response-bar__indicator rt-response-bar__indicator--not-interested"></span>
                    <?php esc_html_e('Not Interested', 'senna-finance'); ?>
                </div>
                <div class="rt-response-bar__track">
                    <div class="rt-response-bar__fill rt-response-bar__fill--not-interested" style="width: <?php echo $stats->responded > 0 ? esc_attr(round(($stats->not_interested / $stats->responded) * 100, 1)) : 0; ?>%;"></div>
                </div>
                <span class="rt-response-bar__value"><?php echo esc_html($stats->not_interested); ?></span>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="rt-detail__grid">
        <!-- Activity Feed -->
        <div class="rt-detail__activity">
            <div class="rt-panel">
                <div class="rt-panel__header">
                    <h3><?php esc_html_e('Recent Activity', 'senna-finance'); ?></h3>
                    <button type="button" class="rt-btn rt-btn--small rt-btn--ghost" data-action="refresh-activity">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <polyline points="23,4 23,10 17,10"/>
                            <path d="M20.49,15a9,9,0,1,1-2.12-9.36L23,10"/>
                        </svg>
                    </button>
                </div>
                <div class="rt-panel__body">
                    <div class="rt-feed" id="rt-activity-feed">
                        <?php if (empty($activity)) : ?>
                            <div class="rt-feed__empty">
                                <p><?php esc_html_e('No activity yet. Activity will appear here as emails are sent and responses come in.', 'senna-finance'); ?></p>
                            </div>
                        <?php else : ?>
                            <?php foreach ($activity as $item) :
                                $icon = '';
                                $type_class = '';

                                switch ($item->action_type) {
                                    case 'email_sent':
                                        $icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>';
                                        $type_class = 'sent';
                                        break;
                                    case 'email_opened':
                                        $icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
                                        $type_class = 'opened';
                                        break;
                                    case 'response_interested':
                                        $icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>';
                                        $type_class = 'interested';
                                        break;
                                    case 'response_not_interested':
                                        $icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"></path></svg>';
                                        $type_class = 'not-interested';
                                        break;
                                    default:
                                        $icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>';
                                        $type_class = 'default';
                                }

                                $action_data = json_decode($item->action_data, true);
                            ?>
                                <div class="rt-feed__item rt-feed__item--<?php echo esc_attr($type_class); ?>">
                                    <div class="rt-feed__icon"><?php echo $icon; ?></div>
                                    <div class="rt-feed__content">
                                        <?php if (!empty($item->candidate_name)) : ?>
                                            <span class="rt-feed__name"><?php echo esc_html($item->candidate_name); ?></span>
                                        <?php endif; ?>
                                        <span class="rt-feed__action">
                                            <?php
                                            switch ($item->action_type) {
                                                case 'email_sent':
                                                    esc_html_e('Email sent', 'senna-finance');
                                                    break;
                                                case 'email_opened':
                                                    esc_html_e('Opened email', 'senna-finance');
                                                    break;
                                                case 'response_interested':
                                                    esc_html_e('Responded: Interested', 'senna-finance');
                                                    break;
                                                case 'response_not_interested':
                                                    esc_html_e('Responded: Not Interested', 'senna-finance');
                                                    break;
                                                case 'campaign_activated':
                                                    esc_html_e('Campaign started', 'senna-finance');
                                                    break;
                                                case 'campaign_completed':
                                                    esc_html_e('Campaign completed', 'senna-finance');
                                                    break;
                                                default:
                                                    echo esc_html(ucwords(str_replace('_', ' ', $item->action_type)));
                                            }
                                            ?>
                                        </span>
                                        <span class="rt-feed__time"><?php echo esc_html(human_time_diff(strtotime($item->created_at), current_time('timestamp')) . ' ago'); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Interested Candidates -->
        <div class="rt-detail__interested">
            <div class="rt-panel">
                <div class="rt-panel__header">
                    <h3><?php esc_html_e('Interested Candidates', 'senna-finance'); ?></h3>
                    <span class="rt-panel__count"><?php echo esc_html($stats->interested); ?></span>
                </div>
                <div class="rt-panel__body">
                    <?php
                    $interested_targets = array_filter($targets, function($t) {
                        return $t->response_status === 'interested';
                    });

                    if (empty($interested_targets)) : ?>
                        <div class="rt-panel__empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/>
                            </svg>
                            <p><?php esc_html_e('No interested responses yet.', 'senna-finance'); ?></p>
                        </div>
                    <?php else : ?>
                        <div class="rt-interested-list">
                            <?php foreach ($interested_targets as $target) : ?>
                                <div class="rt-interested-item">
                                    <div class="rt-interested-item__info">
                                        <span class="rt-interested-item__name"><?php echo esc_html($target->candidate_name); ?></span>
                                        <span class="rt-interested-item__title"><?php echo esc_html($target->candidate_title); ?></span>
                                        <span class="rt-interested-item__company"><?php echo esc_html($target->candidate_company); ?></span>
                                    </div>
                                    <div class="rt-interested-item__actions">
                                        <a href="mailto:<?php echo esc_attr($target->candidate_email); ?>" class="rt-btn rt-btn--small rt-btn--primary">
                                            <?php esc_html_e('Contact', 'senna-finance'); ?>
                                        </a>
                                    </div>
                                    <?php if (!empty($target->response_message)) : ?>
                                        <div class="rt-interested-item__message">
                                            <span class="rt-interested-item__message-label"><?php esc_html_e('Message:', 'senna-finance'); ?></span>
                                            "<?php echo esc_html($target->response_message); ?>"
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- All Targets List -->
    <div class="rt-detail__targets">
        <div class="rt-panel">
            <div class="rt-panel__header">
                <h3><?php esc_html_e('All Candidates', 'senna-finance'); ?></h3>
                <span class="rt-panel__count"><?php echo esc_html(count($targets)); ?></span>
            </div>
            <div class="rt-panel__body">
                <?php if (empty($targets)) : ?>
                    <div class="rt-panel__empty">
                        <p><?php esc_html_e('No candidates targeted yet.', 'senna-finance'); ?></p>
                    </div>
                <?php else : ?>
                    <div class="rt-targets-table">
                        <table class="rt-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Name', 'senna-finance'); ?></th>
                                    <th><?php esc_html_e('Email Status', 'senna-finance'); ?></th>
                                    <th><?php esc_html_e('Response', 'senna-finance'); ?></th>
                                    <th><?php esc_html_e('Actions', 'senna-finance'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($targets as $target) :
                                    $email_status_class = '';
                                    $email_status_text = '';
                                    switch ($target->email_status) {
                                        case 'sent':
                                            $email_status_class = 'sent';
                                            $email_status_text = __('Sent', 'senna-finance');
                                            break;
                                        case 'opened':
                                            $email_status_class = 'opened';
                                            $email_status_text = __('Opened', 'senna-finance');
                                            break;
                                        case 'clicked':
                                            $email_status_class = 'clicked';
                                            $email_status_text = __('Clicked', 'senna-finance');
                                            break;
                                        case 'responded':
                                            $email_status_class = 'responded';
                                            $email_status_text = __('Responded', 'senna-finance');
                                            break;
                                        case 'bounced':
                                            $email_status_class = 'bounced';
                                            $email_status_text = __('Bounced', 'senna-finance');
                                            break;
                                        default:
                                            $email_status_class = 'pending';
                                            $email_status_text = __('Pending', 'senna-finance');
                                    }

                                    $response_class = '';
                                    $response_text = '—';
                                    if ($target->response_status && $target->response_status !== 'none') {
                                        switch ($target->response_status) {
                                            case 'interested':
                                                $response_class = 'interested';
                                                $response_text = __('Interested', 'senna-finance');
                                                break;
                                            case 'not_interested':
                                                $response_class = 'not-interested';
                                                $response_text = __('Not Interested', 'senna-finance');
                                                break;
                                            case 'maybe':
                                                $response_class = 'maybe';
                                                $response_text = __('Maybe', 'senna-finance');
                                                break;
                                        }
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <div class="rt-target-info">
                                                <span class="rt-target-info__name"><?php echo esc_html($target->candidate_name); ?></span>
                                                <span class="rt-target-info__meta"><?php echo esc_html($target->candidate_title); ?> <?php if ($target->candidate_company) : ?>@ <?php echo esc_html($target->candidate_company); ?><?php endif; ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="rt-status-pill rt-status-pill--<?php echo esc_attr($email_status_class); ?>">
                                                <?php echo esc_html($email_status_text); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($response_class) : ?>
                                                <span class="rt-response-pill rt-response-pill--<?php echo esc_attr($response_class); ?>">
                                                    <?php echo esc_html($response_text); ?>
                                                </span>
                                            <?php else : ?>
                                                <span class="rt-response-pill rt-response-pill--none"><?php echo esc_html($response_text); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="mailto:<?php echo esc_attr($target->candidate_email); ?>" class="rt-btn rt-btn--small rt-btn--ghost" title="<?php esc_attr_e('Send Email', 'senna-finance'); ?>">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                                    <polyline points="22,6 12,13 2,6"/>
                                                </svg>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Campaign Details Panel -->
    <div class="rt-detail__info">
        <div class="rt-panel rt-panel--collapsible">
            <button class="rt-panel__header rt-panel__header--toggle" data-action="toggle-panel">
                <h3><?php esc_html_e('Campaign Details', 'senna-finance'); ?></h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="rt-panel__chevron">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>
            <div class="rt-panel__body rt-panel__body--collapsed">
                <div class="rt-detail-grid">
                    <div class="rt-detail-item">
                        <span class="rt-detail-item__label"><?php esc_html_e('Candidate Brief', 'senna-finance'); ?></span>
                        <p class="rt-detail-item__value"><?php echo esc_html($campaign->brief); ?></p>
                    </div>
                    <?php if (!empty($campaign->outreach_subject)) : ?>
                        <div class="rt-detail-item">
                            <span class="rt-detail-item__label"><?php esc_html_e('Email Subject', 'senna-finance'); ?></span>
                            <p class="rt-detail-item__value"><?php echo esc_html($campaign->outreach_subject); ?></p>
                        </div>
                    <?php endif; ?>
                    <div class="rt-detail-item">
                        <span class="rt-detail-item__label"><?php esc_html_e('Created', 'senna-finance'); ?></span>
                        <p class="rt-detail-item__value"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($campaign->created_at))); ?></p>
                    </div>
                    <?php if (!empty($campaign->scheduled_at)) : ?>
                        <div class="rt-detail-item">
                            <span class="rt-detail-item__label"><?php esc_html_e('Scheduled For', 'senna-finance'); ?></span>
                            <p class="rt-detail-item__value"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($campaign->scheduled_at))); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
