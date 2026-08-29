<?php
if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$user_name = $current_user->display_name;
$user_initial = strtoupper(substr(trim($user_name), 0, 1) ?: 'P');
$dashboard_data = isset($dashboard_data) ? $dashboard_data : array();
$profile = $dashboard_data['profile'] ?? array();
$analytics = $dashboard_data['analytics'] ?? array();
$networking_stats = $dashboard_data['networking_stats'] ?? array();
$subscription_info = $dashboard_data['subscription_info'] ?? array();
$upcoming_events = $dashboard_data['upcoming_events'] ?? array();
$introduction_requests = $dashboard_data['introduction_requests'] ?? array();
$recent_activity = $dashboard_data['recent_activity'] ?? array();
?>

<div class="sffc-dashboard-container">
    <div class="sffc-profile-shell">
        <!-- Professional Dashboard Header -->
        <div class="sffc-professional-header">
            <div class="sffc-professional-header__brand">
                <div class="sffc-professional-logo">MENA Careers Professional</div>
                <div class="sffc-professional-tagline">Your Executive Dashboard</div>
            </div>
            <div class="sffc-professional-header__user">
                <div class="sffc-header-user-info">
                    <span class="sffc-header-user-name"><?php echo esc_html($user_name); ?></span>
                    <span class="sffc-header-user-role">Professional Member</span>
                </div>
                <div class="sffc-header-avatar">
                    <span><?php echo esc_html($user_initial); ?></span>
                </div>
            </div>
        </div>

        <!-- Dashboard Main Content -->
        <div class="sffc-dashboard-main">
            <!-- Dashboard Overview Row -->
            <div class="sffc-dashboard-overview">
                <!-- Usage Analytics Card -->
                <div class="sffc-overview-card sffc-analytics-overview">
                    <div class="sffc-overview-card__header">
                        <h3>Platform Usage</h3>
                        <span class="sffc-overview-period">This Month</span>
                    </div>
                    <div class="sffc-usage-metrics">
                        <div class="sffc-usage-metric">
                            <div class="sffc-usage-metric__value"><?php echo esc_html($analytics['senna_interactions'] ?? 0); ?></div>
                            <div class="sffc-usage-metric__label">MENA Careers Conversations</div>
                            <div class="sffc-usage-metric__trend">+24% vs last month</div>
                        </div>
                        <div class="sffc-usage-metric">
                            <div class="sffc-usage-metric__value"><?php echo esc_html($analytics['profile_views'] ?? 0); ?></div>
                            <div class="sffc-usage-metric__label">Profile Views</div>
                            <div class="sffc-usage-metric__trend">New this month</div>
                        </div>
                    </div>
                </div>

                <!-- Subscription Status Card -->
                <div class="sffc-overview-card sffc-subscription-overview">
                    <div class="sffc-overview-card__header">
                        <h3>Subscription</h3>
                        <span class="sffc-subscription-status sffc-status-active">Active</span>
                    </div>
                    <div class="sffc-subscription-details">
                        <div class="sffc-subscription-plan"><?php echo esc_html($subscription_info['plan_name'] ?? 'MENA Careers Professional'); ?></div>
                        <div class="sffc-subscription-usage">
                            <div class="sffc-usage-bar">
                                <div class="sffc-usage-fill" style="width: <?php echo esc_attr($subscription_info['usage_percentage'] ?? 0); ?>%"></div>
                            </div>
                            <span><?php echo esc_html($subscription_info['usage_percentage'] ?? 0); ?>% used this month</span>
                        </div>
                        <div class="sffc-subscription-renewal">
                            Renews <?php echo esc_html($subscription_info['renewal_date'] ?? 'Feb 15, 2024'); ?>
                        </div>
                    </div>
                </div>

                <!-- Networking Status Card -->
                <div class="sffc-overview-card sffc-networking-overview">
                    <div class="sffc-overview-card__header">
                        <h3>Professional Network</h3>
                        <a href="#networking" class="sffc-overview-link">View All</a>
                    </div>
                    <div class="sffc-networking-summary">
                        <div class="sffc-network-metric">
                            <div class="sffc-network-metric__value"><?php echo esc_html($networking_stats['total_connections'] ?? 0); ?></div>
                            <div class="sffc-network-metric__label">Professional Connections</div>
                        </div>
                        <div class="sffc-network-metric">
                            <div class="sffc-network-metric__value"><?php echo esc_html($networking_stats['pending_introductions'] ?? 0); ?></div>
                            <div class="sffc-network-metric__label">Pending Introductions</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content Grid -->
            <div class="sffc-dashboard-grid">
                <!-- Main Content Column -->
                <div class="sffc-dashboard-primary">
                    <!-- Recent Activity Feed -->
                    <div class="sffc-dashboard-card sffc-activity-feed">
                        <div class="sffc-dashboard-card__header">
                            <h3 class="sffc-dashboard-card__title">Recent Activity</h3>
                            <button class="sffc-action-button sffc-action-button--small">View All</button>
                        </div>
                        <div class="sffc-activity-timeline">
                            <?php if (!empty($recent_activity)): ?>
                                <?php foreach ($recent_activity as $activity): ?>
                                    <div class="sffc-activity-item">
                                        <div class="sffc-activity-icon sffc-activity-icon--<?php echo esc_attr($activity['type']); ?>">
                                            <?php 
                                            $icons = array(
                                                'expertise_verification' => '✓',
                                                'introduction_request' => '👥',
                                                'profile_update' => '📝',
                                                'networking_activity' => '🤝'
                                            );
                                            echo $icons[$activity['type']] ?? '📊';
                                            ?>
                                        </div>
                                        <div class="sffc-activity-content">
                                            <div class="sffc-activity-title"><?php echo esc_html($activity['title']); ?></div>
                                            <div class="sffc-activity-time"><?php echo esc_html($activity['timestamp']); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="sffc-activity-empty">
                                    <p>Your professional activity will appear here</p>
                                    <button class="sffc-action-button sffc-action-button--outline" onclick="window.location.href='#profile'">
                                        Complete Your Profile
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Introduction Requests -->
                    <?php if (!empty($introduction_requests)): ?>
                    <div class="sffc-dashboard-card sffc-introductions-card">
                        <div class="sffc-dashboard-card__header">
                            <h3 class="sffc-dashboard-card__title">Introduction Requests</h3>
                            <span class="sffc-intro-count"><?php echo count($introduction_requests); ?> pending</span>
                        </div>
                        <div class="sffc-introductions-list">
                            <?php foreach ($introduction_requests as $request): ?>
                                <div class="sffc-introduction-request">
                                    <div class="sffc-intro-avatar">
                                        <span><?php echo esc_html(strtoupper(substr($request['requester_name'], 0, 1))); ?></span>
                                    </div>
                                    <div class="sffc-intro-details">
                                        <div class="sffc-intro-name"><?php echo esc_html($request['requester_name']); ?></div>
                                        <div class="sffc-intro-context"><?php echo esc_html($request['context']); ?></div>
                                        <div class="sffc-intro-meta"><?php echo esc_html($request['mutual_connections']); ?> mutual connections</div>
                                    </div>
                                    <div class="sffc-intro-actions">
                                        <button class="sffc-action-button sffc-action-button--small">Accept</button>
                                        <button class="sffc-action-button sffc-action-button--outline sffc-action-button--small">Decline</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar Column -->
                <div class="sffc-dashboard-sidebar">
                    <!-- Quick Actions -->
                    <div class="sffc-dashboard-card sffc-quick-actions-card">
                        <div class="sffc-dashboard-card__header">
                            <h3 class="sffc-dashboard-card__title">Quick Actions</h3>
                        </div>
                        <div class="sffc-dashboard-quick-actions">
                            <a href="#profile" class="sffc-dashboard-action">
                                <div class="sffc-dashboard-action__icon">👤</div>
                                <div class="sffc-dashboard-action__text">
                                    <div class="sffc-dashboard-action__title">Edit Profile</div>
                                    <div class="sffc-dashboard-action__desc">Update professional details</div>
                                </div>
                            </a>
                            <a href="#senna" class="sffc-dashboard-action">
                                <div class="sffc-dashboard-action__icon">🤖</div>
                                <div class="sffc-dashboard-action__text">
                                    <div class="sffc-dashboard-action__title">Chat with MENA Careers</div>
                                    <div class="sffc-dashboard-action__desc">Get career insights</div>
                                </div>
                            </a>
                            <a href="#networking" class="sffc-dashboard-action">
                                <div class="sffc-dashboard-action__icon">🌐</div>
                                <div class="sffc-dashboard-action__text">
                                    <div class="sffc-dashboard-action__title">Network</div>
                                    <div class="sffc-dashboard-action__desc">Connect with professionals</div>
                                </div>
                            </a>
                            <a href="#events" class="sffc-dashboard-action">
                                <div class="sffc-dashboard-action__icon">📅</div>
                                <div class="sffc-dashboard-action__text">
                                    <div class="sffc-dashboard-action__title">Events</div>
                                    <div class="sffc-dashboard-action__desc">Industry gatherings</div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Upcoming Events -->
                    <?php if (!empty($upcoming_events)): ?>
                    <div class="sffc-dashboard-card sffc-events-card">
                        <div class="sffc-dashboard-card__header">
                            <h3 class="sffc-dashboard-card__title">Upcoming Events</h3>
                            <a href="#events" class="sffc-overview-link">View All</a>
                        </div>
                        <div class="sffc-events-list">
                            <?php foreach ($upcoming_events as $event): ?>
                                <div class="sffc-event-item">
                                    <div class="sffc-event-date">
                                        <div class="sffc-event-day"><?php echo date('d', strtotime($event['date'])); ?></div>
                                        <div class="sffc-event-month"><?php echo date('M', strtotime($event['date'])); ?></div>
                                    </div>
                                    <div class="sffc-event-details">
                                        <div class="sffc-event-title"><?php echo esc_html($event['title']); ?></div>
                                        <div class="sffc-event-location"><?php echo esc_html($event['location']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Professional Insights -->
                    <div class="sffc-dashboard-card sffc-insights-card">
                        <div class="sffc-dashboard-card__header">
                            <h3 class="sffc-dashboard-card__title">Professional Insights</h3>
                        </div>
                        <div class="sffc-insights-content">
                            <div class="sffc-insight-item">
                                <div class="sffc-insight-metric"><?php echo esc_html($networking_stats['monthly_networking_score'] ?? 0); ?>/100</div>
                                <div class="sffc-insight-label">Networking Score</div>
                                <div class="sffc-insight-trend">
                                    <?php if (($networking_stats['monthly_networking_score'] ?? 0) >= 70): ?>
                                        <span class="sffc-trend-positive">Excellent networking activity</span>
                                    <?php else: ?>
                                        <span class="sffc-trend-neutral">Room for networking growth</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="sffc-insight-recommendation">
                                <h4>Recommended Action</h4>
                                <p>Consider scheduling 2-3 professional introductions this month to expand your network in private equity.</p>
                                <button class="sffc-action-button sffc-action-button--outline sffc-action-button--small">
                                    Find Connections
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Dashboard-specific styles extending the professional profile CSS */
.sffc-dashboard-main {
    padding: 0 48px 48px;
    max-width: 1200px;
    margin: 0 auto;
}

.sffc-dashboard-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.sffc-overview-card {
    background: var(--sffc-professional-card);
    border-radius: 16px;
    padding: 24px;
    box-shadow: var(--sffc-professional-shadow);
    border: 1px solid var(--sffc-professional-border);
}

.sffc-overview-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.sffc-overview-card__header h3 {
    margin: 0;
    font-family: var(--sffc-font-serif);
    font-size: 18px;
    color: var(--sffc-professional-ink);
}

.sffc-overview-period,
.sffc-overview-link {
    font-size: 12px;
    color: var(--sffc-professional-text-muted);
    text-decoration: none;
}

.sffc-usage-metrics {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.sffc-usage-metric {
    text-align: center;
    padding: 16px;
    background: var(--sffc-professional-panel);
    border-radius: 12px;
}

.sffc-usage-metric__value {
    font-size: 24px;
    font-weight: 700;
    color: var(--sffc-professional-ink);
    font-family: var(--sffc-font-serif);
    margin-bottom: 4px;
}

.sffc-usage-metric__label {
    font-size: 12px;
    color: var(--sffc-professional-text-muted);
    margin-bottom: 4px;
}

.sffc-usage-metric__trend {
    font-size: 11px;
    color: var(--sffc-professional-mint);
    font-weight: 500;
}

.sffc-subscription-details {
    space-y: 16px;
}

.sffc-subscription-plan {
    font-size: 16px;
    font-weight: 600;
    color: var(--sffc-professional-ink);
    margin-bottom: 12px;
}

.sffc-usage-bar {
    width: 100%;
    height: 8px;
    background: var(--sffc-professional-panel);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 8px;
}

.sffc-usage-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--sffc-professional-mint), var(--sffc-professional-ink));
    border-radius: 4px;
}

.sffc-subscription-usage,
.sffc-subscription-renewal {
    font-size: 12px;
    color: var(--sffc-professional-text-muted);
}

.sffc-subscription-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.sffc-status-active {
    background: rgba(151, 255, 213, 0.2);
    color: var(--sffc-professional-ink);
    border: 1px solid var(--sffc-professional-mint);
}

.sffc-dashboard-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 32px;
}

.sffc-dashboard-card {
    background: var(--sffc-professional-card);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: var(--sffc-professional-shadow);
    border: 1px solid var(--sffc-professional-border);
}

.sffc-dashboard-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--sffc-professional-border);
}

.sffc-dashboard-card__title {
    margin: 0;
    font-family: var(--sffc-font-serif);
    font-size: 18px;
    color: var(--sffc-professional-ink);
}

.sffc-activity-timeline {
    display: grid;
    gap: 16px;
}

.sffc-activity-item {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 16px;
    background: var(--sffc-professional-panel);
    border-radius: 12px;
    transition: all 0.2s ease;
}

.sffc-activity-item:hover {
    transform: translateX(4px);
    box-shadow: var(--sffc-professional-shadow);
}

.sffc-activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: var(--sffc-professional-mint);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.sffc-activity-title {
    font-weight: 600;
    color: var(--sffc-professional-ink);
    margin-bottom: 4px;
}

.sffc-activity-time {
    font-size: 12px;
    color: var(--sffc-professional-text-muted);
}

.sffc-professional-header__user {
    display: flex;
    align-items: center;
    gap: 16px;
}

.sffc-header-user-info {
    text-align: right;
}

.sffc-header-user-name {
    display: block;
    font-weight: 600;
    font-size: 16px;
}

.sffc-header-user-role {
    display: block;
    font-size: 12px;
    opacity: 0.8;
    color: var(--sffc-professional-mint);
}

.sffc-header-avatar {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: var(--sffc-professional-mint);
    color: var(--sffc-professional-ink);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 18px;
}

@media (max-width: 1024px) {
    .sffc-dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .sffc-dashboard-main {
        padding: 0 24px 32px;
    }
    
    .sffc-usage-metrics {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .sffc-professional-header__user {
        flex-direction: column;
        gap: 8px;
        text-align: center;
    }
    
    .sffc-header-user-info {
        text-align: center;
    }
}
</style>