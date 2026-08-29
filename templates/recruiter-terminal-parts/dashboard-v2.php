<?php
/**
 * Recruiter Terminal - Dashboard View v2.0
 *
 * Shows overview of briefs and recent activity.
 *
 * @package SennaFinanceCareer
 * @subpackage RecruiterTerminal
 *
 * Variables available from parent template:
 * - $briefs (array): User's briefs with response counts
 * - $status_config (array): Status labels and classes
 * - $base_url (string): Base URL for navigation
 * - $total_briefs, $active_count, $pending_count, $draft_count
 * - $total_responses, $unread_responses
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="rt-dashboard rt-dashboard--v2">
    <!-- Dashboard Header -->
    <div class="rt-dashboard__header">
        <div class="rt-dashboard__title">
            <h1><?php esc_html_e('Dashboard', 'senna-finance'); ?></h1>
            <p class="rt-dashboard__subtitle"><?php esc_html_e('Manage your role briefs and candidate responses', 'senna-finance'); ?></p>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="rt-stats-grid">
        <div class="rt-stat-card">
            <div class="rt-stat-card__icon rt-stat-card__icon--briefs">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                </svg>
            </div>
            <div class="rt-stat-card__content">
                <span class="rt-stat-card__value"><?php echo esc_html($total_briefs); ?></span>
                <span class="rt-stat-card__label"><?php esc_html_e('Total Briefs', 'senna-finance'); ?></span>
            </div>
        </div>

        <div class="rt-stat-card rt-stat-card--active">
            <div class="rt-stat-card__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <div class="rt-stat-card__content">
                <span class="rt-stat-card__value"><?php echo esc_html($active_count); ?></span>
                <span class="rt-stat-card__label"><?php esc_html_e('Active', 'senna-finance'); ?></span>
            </div>
        </div>

        <div class="rt-stat-card rt-stat-card--responses">
            <div class="rt-stat-card__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <div class="rt-stat-card__content">
                <span class="rt-stat-card__value"><?php echo esc_html($total_responses); ?></span>
                <span class="rt-stat-card__label"><?php esc_html_e('Total Responses', 'senna-finance'); ?></span>
            </div>
        </div>

        <div class="rt-stat-card rt-stat-card--unread">
            <div class="rt-stat-card__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
            </div>
            <div class="rt-stat-card__content">
                <span class="rt-stat-card__value"><?php echo esc_html($unread_responses); ?></span>
                <span class="rt-stat-card__label"><?php esc_html_e('Unread', 'senna-finance'); ?></span>
            </div>
        </div>
    </div>

    <!-- Recent Activity / Briefs Section -->
    <?php if (empty($briefs)) : ?>
        <!-- Empty State -->
        <div class="rt-empty-state">
            <div class="rt-empty-state__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="64" height="64">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="12" y1="18" x2="12" y2="12"/>
                    <line x1="9" y1="15" x2="15" y2="15"/>
                </svg>
            </div>
            <h2><?php esc_html_e('Create Your First Brief', 'senna-finance'); ?></h2>
            <p><?php esc_html_e("Start attracting top talent by posting a role brief. We'll match it with qualified passive candidates.", 'senna-finance'); ?></p>
            <a href="<?php echo esc_url(add_query_arg('view', 'create', $base_url)); ?>" class="rt-btn rt-btn--primary rt-btn--large">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                <?php esc_html_e('Create New Brief', 'senna-finance'); ?>
            </a>
        </div>
    <?php else : ?>
        <!-- Briefs List -->
        <div class="rt-dashboard__section">
            <div class="rt-section-header">
                <h2><?php esc_html_e('Your Briefs', 'senna-finance'); ?></h2>
                <a href="<?php echo esc_url(add_query_arg('view', 'create', $base_url)); ?>" class="rt-btn rt-btn--secondary rt-btn--small">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    <?php esc_html_e('New Brief', 'senna-finance'); ?>
                </a>
            </div>

            <div class="rt-briefs-grid">
                <?php foreach ($briefs as $brief_item) :
                    $status_info = isset($status_config[$brief_item->status]) ? $status_config[$brief_item->status] : $status_config['draft'];
                    $response_count = isset($brief_item->response_count) ? $brief_item->response_count : 0;
                    $unread_count = isset($brief_item->unread_count) ? $brief_item->unread_count : 0;

                    // Determine card link
                    if (in_array($brief_item->status, array('draft', 'rejected'))) {
                        $card_url = add_query_arg(array('view' => 'edit', 'brief' => $brief_item->id), $base_url);
                    } else {
                        $card_url = add_query_arg(array('view' => 'inbox', 'brief' => $brief_item->id), $base_url);
                    }
                ?>
                    <div class="rt-brief-card" data-brief-id="<?php echo esc_attr($brief_item->id); ?>" data-status="<?php echo esc_attr($brief_item->status); ?>">
                        <div class="rt-brief-card__header">
                            <span class="rt-badge rt-badge--<?php echo esc_attr($status_info['class']); ?>">
                                <?php echo esc_html($status_info['label']); ?>
                            </span>
                            <?php if ($unread_count > 0) : ?>
                                <span class="rt-brief-card__unread"><?php echo esc_html($unread_count); ?> <?php esc_html_e('new', 'senna-finance'); ?></span>
                            <?php endif; ?>
                        </div>

                        <h3 class="rt-brief-card__title">
                            <a href="<?php echo esc_url($card_url); ?>">
                                <?php echo esc_html($brief_item->title); ?>
                            </a>
                        </h3>

                        <?php if (!empty($brief_item->location) || !empty($brief_item->sector)) : ?>
                            <div class="rt-brief-card__meta">
                                <?php if (!empty($brief_item->location)) : ?>
                                    <span class="rt-brief-card__location">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                            <circle cx="12" cy="10" r="3"/>
                                        </svg>
                                        <?php echo esc_html($brief_item->location); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($brief_item->sector)) : ?>
                                    <span class="rt-brief-card__sector"><?php echo esc_html($brief_item->sector); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($brief_item->brief)) : ?>
                            <p class="rt-brief-card__excerpt"><?php echo esc_html(wp_trim_words($brief_item->brief, 15)); ?></p>
                        <?php endif; ?>

                        <div class="rt-brief-card__footer">
                            <div class="rt-brief-card__stats">
                                <span class="rt-brief-card__stat">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                    </svg>
                                    <?php echo esc_html($response_count); ?> <?php echo esc_html(_n('response', 'responses', $response_count, 'senna-finance')); ?>
                                </span>
                            </div>
                            <span class="rt-brief-card__date">
                                <?php
                                /* translators: %s: relative time ago */
                                printf(esc_html__('%s ago', 'senna-finance'), human_time_diff(strtotime($brief_item->created_at), current_time('timestamp')));
                                ?>
                            </span>
                        </div>

                        <div class="rt-brief-card__actions">
                            <?php if (in_array($brief_item->status, array('draft', 'rejected'))) : ?>
                                <a href="<?php echo esc_url(add_query_arg(array('view' => 'edit', 'brief' => $brief_item->id), $base_url)); ?>" class="rt-btn rt-btn--small rt-btn--secondary">
                                    <?php esc_html_e('Edit', 'senna-finance'); ?>
                                </a>
                                <button type="button" class="rt-btn rt-btn--small rt-btn--ghost rt-btn--danger" data-action="delete-brief" data-brief-id="<?php echo esc_attr($brief_item->id); ?>">
                                    <?php esc_html_e('Delete', 'senna-finance'); ?>
                                </button>
                            <?php elseif ($brief_item->status === 'pending_review') : ?>
                                <span class="rt-brief-card__pending"><?php esc_html_e('Awaiting Approval', 'senna-finance'); ?></span>
                            <?php else : ?>
                                <a href="<?php echo esc_url(add_query_arg(array('view' => 'inbox', 'brief' => $brief_item->id), $base_url)); ?>" class="rt-btn rt-btn--small rt-btn--primary">
                                    <?php esc_html_e('View Responses', 'senna-finance'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
(function() {
    var nonce = '<?php echo esc_js(wp_create_nonce('recruiter_terminal_nonce')); ?>';
    var ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';

    // Delete brief handler
    document.querySelectorAll('[data-action="delete-brief"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var briefId = this.dataset.briefId;
            var card = this.closest('.rt-brief-card');

            if (!confirm('<?php echo esc_js(__('Are you sure you want to delete this brief?', 'senna-finance')); ?>')) {
                return;
            }

            var formData = new FormData();
            formData.append('action', 'rt_delete_brief');
            formData.append('brief_id', briefId);
            formData.append('nonce', nonce);

            btn.disabled = true;
            btn.textContent = '<?php echo esc_js(__('Deleting...', 'senna-finance')); ?>';

            fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        card.style.transition = 'opacity 0.3s, transform 0.3s';
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.95)';
                        setTimeout(function() {
                            card.remove();
                            // Check if no briefs remain
                            if (document.querySelectorAll('.rt-brief-card').length === 0) {
                                location.reload();
                            }
                        }, 300);
                    } else {
                        alert(data.data || '<?php echo esc_js(__('Failed to delete brief.', 'senna-finance')); ?>');
                        btn.disabled = false;
                        btn.textContent = '<?php echo esc_js(__('Delete', 'senna-finance')); ?>';
                    }
                })
                .catch(function() {
                    alert('<?php echo esc_js(__('An error occurred. Please try again.', 'senna-finance')); ?>');
                    btn.disabled = false;
                    btn.textContent = '<?php echo esc_js(__('Delete', 'senna-finance')); ?>';
                });
        });
    });
})();
</script>
