<?php
/**
 * Recruiter Terminal Template v2.0
 *
 * Messaging-first interface for recruiters to manage briefs and candidate responses.
 * Follows the cream/green/gold design system from frontend-redesign.css.
 *
 * @package SennaFinanceCareer
 * @subpackage RecruiterTerminal
 *
 * Variables available:
 * - $view (string): Current view (dashboard, create, inbox, detail)
 * - $brief_id (int): Current brief ID (if viewing/editing)
 * - $briefs (array): User's briefs
 * - $brief (object|null): Current brief data
 * - $responses (array): Brief responses
 * - $current_user (WP_User): Current user object
 * - $user_avatar (string): User avatar URL
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure we have required variables
$view = isset($context['view']) ? $context['view'] : 'dashboard';
$brief_id = isset($context['brief_id']) ? $context['brief_id'] : 0;
$briefs = isset($context['briefs']) ? $context['briefs'] : array();
$brief = isset($context['brief']) ? $context['brief'] : null;
$responses = isset($context['responses']) ? $context['responses'] : array();
$current_user = isset($context['current_user']) ? $context['current_user'] : wp_get_current_user();
$user_avatar = isset($context['user_avatar']) ? $context['user_avatar'] : get_avatar_url($current_user->ID, array('size' => 48));

// Get base URL for navigation
$base_url = get_permalink();

// Get recruiter profile info
$recruiter_title = get_user_meta($current_user->ID, 'job_title', true);
$recruiter_company = get_user_meta($current_user->ID, 'company', true);

// Status labels and colors
$status_config = array(
    'draft'          => array('label' => __('Draft', 'senna-finance'), 'class' => 'draft'),
    'pending_review' => array('label' => __('Pending Review', 'senna-finance'), 'class' => 'pending'),
    'active'         => array('label' => __('Active', 'senna-finance'), 'class' => 'active'),
    'closed'         => array('label' => __('Closed', 'senna-finance'), 'class' => 'closed'),
    'rejected'       => array('label' => __('Rejected', 'senna-finance'), 'class' => 'rejected'),
);

// Calculate stats
$total_briefs = count($briefs);
$active_count = 0;
$pending_count = 0;
$draft_count = 0;
$total_responses = 0;
$unread_responses = 0;

foreach ($briefs as $b) {
    if ($b->status === 'active') $active_count++;
    if ($b->status === 'pending_review') $pending_count++;
    if ($b->status === 'draft') $draft_count++;
    if (isset($b->response_count)) $total_responses += $b->response_count;
    if (isset($b->unread_count)) $unread_responses += $b->unread_count;
}
?>

<div class="rt-terminal rt-terminal--v2" data-view="<?php echo esc_attr($view); ?>" data-brief-id="<?php echo esc_attr($brief_id); ?>">

    <!-- Sidebar -->
    <aside class="rt-sidebar">
        <!-- Brand -->
        <div class="rt-sidebar__brand">
            <span class="rt-brand-mark">S</span>
            <span class="rt-brand-text">MENA Careers</span>
            <span class="rt-brand-tag">RECRUITER</span>
        </div>

        <!-- New Brief Button -->
        <a href="<?php echo esc_url(add_query_arg('view', 'create', $base_url)); ?>" class="rt-btn rt-btn--primary rt-btn--new-brief">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            <span><?php esc_html_e('New Brief', 'senna-finance'); ?></span>
        </a>

        <!-- My Briefs List -->
        <div class="rt-sidebar__section">
            <h3 class="rt-sidebar__heading"><?php esc_html_e('My Briefs', 'senna-finance'); ?></h3>

            <?php if (empty($briefs)) : ?>
                <p class="rt-sidebar__empty"><?php esc_html_e('No briefs yet', 'senna-finance'); ?></p>
            <?php else : ?>
                <ul class="rt-brief-list">
                    <?php foreach ($briefs as $brief_item) :
                        $is_active = ($brief_id == $brief_item->id);
                        $status_info = isset($status_config[$brief_item->status]) ? $status_config[$brief_item->status] : $status_config['draft'];
                        $unread = isset($brief_item->unread_count) ? $brief_item->unread_count : 0;
                    ?>
                        <li class="rt-brief-item <?php echo $is_active ? 'rt-brief-item--active' : ''; ?>">
                            <a href="<?php echo esc_url(add_query_arg(array('view' => 'inbox', 'brief' => $brief_item->id), $base_url)); ?>" class="rt-brief-link">
                                <span class="rt-brief-title"><?php echo esc_html(wp_trim_words($brief_item->title, 5)); ?></span>
                                <?php if ($unread > 0) : ?>
                                    <span class="rt-brief-badge"><?php echo esc_html($unread); ?></span>
                                <?php endif; ?>
                                <span class="rt-brief-status rt-brief-status--<?php echo esc_attr($status_info['class']); ?>">
                                    <?php echo esc_html($status_info['label']); ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- User Profile -->
        <div class="rt-sidebar__user">
            <?php if ($user_avatar) : ?>
                <img src="<?php echo esc_url($user_avatar); ?>" alt="" class="rt-user-avatar">
            <?php else : ?>
                <div class="rt-user-initials"><?php echo esc_html(strtoupper(substr($current_user->display_name, 0, 1))); ?></div>
            <?php endif; ?>
            <div class="rt-user-info">
                <span class="rt-user-name"><?php echo esc_html($current_user->display_name); ?></span>
                <?php if ($recruiter_company) : ?>
                    <span class="rt-user-company"><?php echo esc_html($recruiter_company); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="rt-main">
        <?php if ($view === 'dashboard' || $view === '') : ?>
            <!-- Dashboard View -->
            <?php include __DIR__ . '/recruiter-terminal-parts/dashboard-v2.php'; ?>

        <?php elseif ($view === 'create') : ?>
            <!-- Create Brief View -->
            <?php include __DIR__ . '/recruiter-terminal-parts/brief-form.php'; ?>

        <?php elseif ($view === 'edit' && $brief) : ?>
            <!-- Edit Brief View -->
            <?php include __DIR__ . '/recruiter-terminal-parts/brief-form.php'; ?>

        <?php elseif ($view === 'inbox' && $brief) : ?>
            <!-- Inbox View (Responses for a Brief) -->
            <?php include __DIR__ . '/recruiter-terminal-parts/inbox.php'; ?>

        <?php else : ?>
            <!-- Fallback to Dashboard -->
            <?php include __DIR__ . '/recruiter-terminal-parts/dashboard-v2.php'; ?>
        <?php endif; ?>
    </main>

    <!-- Message Viewer Panel (Hidden by default, shown via JS) -->
    <div class="rt-viewer" id="rt-message-viewer">
        <div class="rt-viewer__backdrop"></div>
        <div class="rt-viewer__panel">
            <div class="rt-viewer__header">
                <button type="button" class="rt-viewer__close" id="rt-viewer-close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
                <div class="rt-viewer__title" id="rt-viewer-title"></div>
                <div class="rt-viewer__actions">
                    <button type="button" class="rt-viewer__action" data-action="star" title="<?php esc_attr_e('Star', 'senna-finance'); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                    </button>
                    <button type="button" class="rt-viewer__action" data-action="archive" title="<?php esc_attr_e('Archive', 'senna-finance'); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <polyline points="21 8 21 21 3 21 3 8"/>
                            <rect x="1" y="3" width="22" height="5"/>
                            <line x1="10" y1="12" x2="14" y2="12"/>
                        </svg>
                    </button>
                    <button type="button" class="rt-viewer__action rt-viewer__action--more" data-action="more" title="<?php esc_attr_e('More Actions', 'senna-finance'); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <circle cx="12" cy="12" r="1"/>
                            <circle cx="19" cy="12" r="1"/>
                            <circle cx="5" cy="12" r="1"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="rt-viewer__content" id="rt-viewer-content">
                <!-- Content loaded via JS -->
            </div>
            <div class="rt-viewer__footer">
                <div class="rt-viewer__notes">
                    <h4><?php esc_html_e('Private Notes', 'senna-finance'); ?></h4>
                    <div class="rt-notes-list" id="rt-notes-list"></div>
                    <div class="rt-note-form">
                        <textarea id="rt-note-input" placeholder="<?php esc_attr_e('Add a private note...', 'senna-finance'); ?>"></textarea>
                        <button type="button" class="rt-btn rt-btn--small" id="rt-add-note">
                            <?php esc_html_e('Add Note', 'senna-finance'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Templates -->
<script type="text/template" id="rt-template-response-card">
    <div class="rt-response-card" data-response-id="{{id}}" data-starred="{{starred}}">
        <div class="rt-response-card__indicator {{unread_class}}"></div>
        <div class="rt-response-card__avatar">
            <span class="rt-avatar-initials">{{initials}}</span>
        </div>
        <div class="rt-response-card__content">
            <div class="rt-response-card__header">
                <span class="rt-response-card__name">{{candidate_name}}</span>
                <span class="rt-response-card__time">{{time_ago}}</span>
            </div>
            <div class="rt-response-card__preview">{{message_preview}}</div>
        </div>
        {{#starred}}
        <div class="rt-response-card__star">
            <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
            </svg>
        </div>
        {{/starred}}
    </div>
</script>

<script type="text/template" id="rt-template-viewer-content">
    <div class="rt-viewer-candidate">
        <div class="rt-viewer-candidate__avatar">
            <span class="rt-avatar-initials">{{initials}}</span>
        </div>
        <div class="rt-viewer-candidate__info">
            <h2 class="rt-viewer-candidate__name">{{candidate_name}}</h2>
            <p class="rt-viewer-candidate__contact">
                {{#candidate_email}}<a href="mailto:{{candidate_email}}">{{candidate_email}}</a>{{/candidate_email}}
                {{#candidate_phone}}<span>{{candidate_phone}}</span>{{/candidate_phone}}
            </p>
            {{#candidate_linkedin}}
            <a href="{{candidate_linkedin}}" target="_blank" class="rt-viewer-candidate__linkedin">
                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                    <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/>
                    <rect x="2" y="9" width="4" height="12"/>
                    <circle cx="4" cy="4" r="2"/>
                </svg>
                LinkedIn Profile
            </a>
            {{/candidate_linkedin}}
        </div>
        <div class="rt-viewer-candidate__status">
            <select class="rt-status-select" data-response-id="{{id}}">
                <option value="viewed" {{#is_viewed}}selected{{/is_viewed}}><?php esc_html_e('New', 'senna-finance'); ?></option>
                <option value="responded" {{#is_responded}}selected{{/is_responded}}><?php esc_html_e('Responded', 'senna-finance'); ?></option>
                <option value="hired" {{#is_hired}}selected{{/is_hired}}><?php esc_html_e('Hired', 'senna-finance'); ?></option>
                <option value="rejected" {{#is_rejected}}selected{{/is_rejected}}><?php esc_html_e('Rejected', 'senna-finance'); ?></option>
                <option value="archived" {{#is_archived}}selected{{/is_archived}}><?php esc_html_e('Archived', 'senna-finance'); ?></option>
            </select>
        </div>
    </div>
    <div class="rt-viewer-message">
        <h3><?php esc_html_e('Response', 'senna-finance'); ?></h3>
        <div class="rt-viewer-message__content">{{message}}</div>
        <div class="rt-viewer-message__meta">
            <span><?php esc_html_e('Received', 'senna-finance'); ?> {{created_at}}</span>
        </div>
    </div>
</script>

<script type="text/template" id="rt-template-note">
    <div class="rt-note" data-note-id="{{id}}">
        <div class="rt-note__header">
            <span class="rt-note__author">{{author_name}}</span>
            <span class="rt-note__time">{{created_at}}</span>
        </div>
        <div class="rt-note__content">{{note}}</div>
    </div>
</script>
