<?php
/**
 * Recruiter Terminal - Inbox View
 *
 * Shows responses for a specific brief in a messaging-style interface.
 *
 * @package SennaFinanceCareer
 * @subpackage RecruiterTerminal
 *
 * Variables available from parent template:
 * - $brief (object): Current brief data
 * - $responses (array): Responses to this brief
 * - $base_url (string): Base URL for navigation
 * - $status_config (array): Status labels and classes
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get brief status info
$status_info = isset($status_config[$brief->status]) ? $status_config[$brief->status] : $status_config['draft'];

// Count stats
$total_responses = count($responses);
$unread_count = 0;
$starred_count = 0;
foreach ($responses as $r) {
    if ($r->status === 'pending') $unread_count++;
    if (!empty($r->starred)) $starred_count++;
}

// Filter options
$current_filter = isset($_GET['filter']) ? sanitize_key($_GET['filter']) : 'all';
?>

<div class="rt-inbox">
    <!-- Inbox Header -->
    <div class="rt-inbox__header">
        <div class="rt-inbox__title-area">
            <a href="<?php echo esc_url($base_url); ?>" class="rt-back-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                    <line x1="19" y1="12" x2="5" y2="12"/>
                    <polyline points="12 19 5 12 12 5"/>
                </svg>
            </a>
            <div>
                <h1 class="rt-inbox__title"><?php echo esc_html($brief->title); ?></h1>
                <div class="rt-inbox__meta">
                    <span class="rt-badge rt-badge--<?php echo esc_attr($status_info['class']); ?>">
                        <?php echo esc_html($status_info['label']); ?>
                    </span>
                    <?php if (!empty($brief->location)) : ?>
                        <span class="rt-inbox__location"><?php echo esc_html($brief->location); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="rt-inbox__actions">
            <?php if (in_array($brief->status, array('draft', 'rejected'))) : ?>
                <a href="<?php echo esc_url(add_query_arg(array('view' => 'edit', 'brief' => $brief->id), $base_url)); ?>" class="rt-btn rt-btn--secondary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    <?php esc_html_e('Edit Brief', 'senna-finance'); ?>
                </a>
            <?php endif; ?>

            <?php if ($total_responses > 0) : ?>
                <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=rt_export_responses&brief_id=' . $brief->id . '&nonce=' . wp_create_nonce('recruiter_terminal_nonce'))); ?>" class="rt-btn rt-btn--secondary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    <?php esc_html_e('Export CSV', 'senna-finance'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats Bar -->
    <div class="rt-inbox__stats">
        <div class="rt-inbox__stat">
            <span class="rt-inbox__stat-value"><?php echo esc_html($total_responses); ?></span>
            <span class="rt-inbox__stat-label"><?php esc_html_e('Total', 'senna-finance'); ?></span>
        </div>
        <div class="rt-inbox__stat rt-inbox__stat--unread">
            <span class="rt-inbox__stat-value"><?php echo esc_html($unread_count); ?></span>
            <span class="rt-inbox__stat-label"><?php esc_html_e('Unread', 'senna-finance'); ?></span>
        </div>
        <div class="rt-inbox__stat rt-inbox__stat--starred">
            <span class="rt-inbox__stat-value"><?php echo esc_html($starred_count); ?></span>
            <span class="rt-inbox__stat-label"><?php esc_html_e('Starred', 'senna-finance'); ?></span>
        </div>
    </div>

    <!-- Filters -->
    <div class="rt-inbox__filters">
        <button type="button" class="rt-filter-btn <?php echo $current_filter === 'all' ? 'rt-filter-btn--active' : ''; ?>" data-filter="all">
            <?php esc_html_e('All', 'senna-finance'); ?>
        </button>
        <button type="button" class="rt-filter-btn <?php echo $current_filter === 'unread' ? 'rt-filter-btn--active' : ''; ?>" data-filter="unread">
            <?php esc_html_e('Unread', 'senna-finance'); ?>
        </button>
        <button type="button" class="rt-filter-btn <?php echo $current_filter === 'starred' ? 'rt-filter-btn--active' : ''; ?>" data-filter="starred">
            <?php esc_html_e('Starred', 'senna-finance'); ?>
        </button>
        <button type="button" class="rt-filter-btn <?php echo $current_filter === 'archived' ? 'rt-filter-btn--active' : ''; ?>" data-filter="archived">
            <?php esc_html_e('Archived', 'senna-finance'); ?>
        </button>
    </div>

    <!-- Response List -->
    <div class="rt-inbox__list" id="rt-response-list">
        <?php if (empty($responses)) : ?>
            <div class="rt-inbox__empty">
                <div class="rt-inbox__empty-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                </div>
                <h3><?php esc_html_e('No responses yet', 'senna-finance'); ?></h3>
                <p><?php esc_html_e('Once candidates respond to your brief, their responses will appear here.', 'senna-finance'); ?></p>
            </div>
        <?php else : ?>
            <?php foreach ($responses as $response) :
                $initials = '';
                $name_parts = explode(' ', $response->candidate_name);
                foreach ($name_parts as $part) {
                    $initials .= strtoupper(substr($part, 0, 1));
                }
                $initials = substr($initials, 0, 2);

                $is_unread = ($response->status === 'pending');
                $is_starred = !empty($response->starred);
                $message_preview = wp_trim_words($response->message, 15);
            ?>
                <div class="rt-response-card <?php echo $is_unread ? 'rt-response-card--unread' : ''; ?> <?php echo $is_starred ? 'rt-response-card--starred' : ''; ?>"
                     data-response-id="<?php echo esc_attr($response->id); ?>"
                     data-status="<?php echo esc_attr($response->status); ?>"
                     data-starred="<?php echo $is_starred ? '1' : '0'; ?>">

                    <div class="rt-response-card__indicator"></div>

                    <div class="rt-response-card__avatar">
                        <span class="rt-avatar-initials"><?php echo esc_html($initials); ?></span>
                    </div>

                    <div class="rt-response-card__content">
                        <div class="rt-response-card__header">
                            <span class="rt-response-card__name"><?php echo esc_html($response->candidate_name); ?></span>
                            <span class="rt-response-card__time">
                                <?php echo esc_html(human_time_diff(strtotime($response->created_at), current_time('timestamp'))); ?>
                            </span>
                        </div>
                        <div class="rt-response-card__preview"><?php echo esc_html($message_preview ?: __('(No message)', 'senna-finance')); ?></div>
                        <?php if (!empty($response->candidate_linkedin)) : ?>
                            <div class="rt-response-card__linkedin">
                                <svg viewBox="0 0 24 24" fill="currentColor" width="12" height="12">
                                    <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/>
                                    <rect x="2" y="9" width="4" height="12"/>
                                    <circle cx="4" cy="4" r="2"/>
                                </svg>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_starred) : ?>
                        <div class="rt-response-card__star">
                            <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    var briefId = <?php echo (int) $brief->id; ?>;
    var nonce = '<?php echo esc_js(wp_create_nonce('recruiter_terminal_nonce')); ?>';
    var ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';

    // Response card click handler
    document.querySelectorAll('.rt-response-card').forEach(function(card) {
        card.addEventListener('click', function() {
            var responseId = this.dataset.responseId;
            openResponseViewer(responseId);
        });
    });

    // Filter buttons
    document.querySelectorAll('.rt-filter-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var filter = this.dataset.filter;

            // Update active state
            document.querySelectorAll('.rt-filter-btn').forEach(function(b) {
                b.classList.remove('rt-filter-btn--active');
            });
            this.classList.add('rt-filter-btn--active');

            // Filter cards
            document.querySelectorAll('.rt-response-card').forEach(function(card) {
                var status = card.dataset.status;
                var starred = card.dataset.starred === '1';
                var show = true;

                if (filter === 'unread' && status !== 'pending') show = false;
                if (filter === 'starred' && !starred) show = false;
                if (filter === 'archived' && status !== 'archived') show = false;

                card.style.display = show ? '' : 'none';
            });
        });
    });

    // Open response viewer
    function openResponseViewer(responseId) {
        var viewer = document.getElementById('rt-message-viewer');
        var viewerContent = document.getElementById('rt-viewer-content');
        var viewerTitle = document.getElementById('rt-viewer-title');
        var notesList = document.getElementById('rt-notes-list');

        viewer.classList.add('rt-viewer--open');
        viewer.dataset.responseId = responseId;

        // Load response data
        var formData = new FormData();
        formData.append('action', 'rt_get_response');
        formData.append('response_id', responseId);
        formData.append('nonce', nonce);

        fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var response = data.data.response;
                    var notes = data.data.notes;

                    // Build initials
                    var initials = response.candidate_name.split(' ').map(function(p) { return p[0]; }).join('').toUpperCase().substring(0, 2);

                    // Update viewer title
                    viewerTitle.textContent = response.candidate_name;

                    // Build viewer content
                    var html = '<div class="rt-viewer-candidate">';
                    html += '<div class="rt-viewer-candidate__avatar"><span class="rt-avatar-initials">' + initials + '</span></div>';
                    html += '<div class="rt-viewer-candidate__info">';
                    html += '<h2 class="rt-viewer-candidate__name">' + escapeHtml(response.candidate_name) + '</h2>';
                    html += '<p class="rt-viewer-candidate__contact">';
                    if (response.candidate_email) {
                        html += '<a href="mailto:' + escapeHtml(response.candidate_email) + '">' + escapeHtml(response.candidate_email) + '</a>';
                    }
                    if (response.candidate_phone) {
                        html += '<span class="rt-divider">|</span><span>' + escapeHtml(response.candidate_phone) + '</span>';
                    }
                    html += '</p>';
                    if (response.candidate_linkedin) {
                        html += '<a href="' + escapeHtml(response.candidate_linkedin) + '" target="_blank" class="rt-viewer-candidate__linkedin">LinkedIn Profile</a>';
                    }
                    html += '</div>';
                    html += '<div class="rt-viewer-candidate__status">';
                    html += '<select class="rt-status-select" data-response-id="' + response.id + '">';
                    html += '<option value="viewed"' + (response.status === 'viewed' ? ' selected' : '') + '>New</option>';
                    html += '<option value="responded"' + (response.status === 'responded' ? ' selected' : '') + '>Responded</option>';
                    html += '<option value="hired"' + (response.status === 'hired' ? ' selected' : '') + '>Hired</option>';
                    html += '<option value="rejected"' + (response.status === 'rejected' ? ' selected' : '') + '>Rejected</option>';
                    html += '<option value="archived"' + (response.status === 'archived' ? ' selected' : '') + '>Archived</option>';
                    html += '</select>';
                    html += '</div>';
                    html += '</div>';

                    html += '<div class="rt-viewer-message">';
                    html += '<h3><?php echo esc_js(__('Response', 'senna-finance')); ?></h3>';
                    html += '<div class="rt-viewer-message__content">' + (response.message ? escapeHtml(response.message) : '<em><?php echo esc_js(__('No message provided', 'senna-finance')); ?></em>') + '</div>';
                    html += '<div class="rt-viewer-message__meta"><span><?php echo esc_js(__('Received', 'senna-finance')); ?> ' + formatDate(response.created_at) + '</span></div>';
                    html += '</div>';

                    viewerContent.innerHTML = html;

                    // Status select handler
                    viewerContent.querySelector('.rt-status-select').addEventListener('change', function() {
                        updateResponseStatus(this.dataset.responseId, this.value);
                    });

                    // Render notes
                    notesList.innerHTML = '';
                    if (notes && notes.length > 0) {
                        notes.forEach(function(note) {
                            notesList.innerHTML += '<div class="rt-note"><div class="rt-note__header"><span class="rt-note__author">' + escapeHtml(note.author_name || 'You') + '</span><span class="rt-note__time">' + formatDate(note.created_at) + '</span></div><div class="rt-note__content">' + escapeHtml(note.note) + '</div></div>';
                        });
                    }

                    // Update star button state
                    var starBtn = viewer.querySelector('[data-action="star"]');
                    if (response.starred == 1) {
                        starBtn.classList.add('rt-viewer__action--active');
                    } else {
                        starBtn.classList.remove('rt-viewer__action--active');
                    }

                    // Mark card as read
                    var card = document.querySelector('.rt-response-card[data-response-id="' + responseId + '"]');
                    if (card) {
                        card.classList.remove('rt-response-card--unread');
                        card.dataset.status = 'viewed';
                    }
                }
            });
    }

    // Close viewer
    document.getElementById('rt-viewer-close').addEventListener('click', closeViewer);
    document.querySelector('.rt-viewer__backdrop').addEventListener('click', closeViewer);

    function closeViewer() {
        document.getElementById('rt-message-viewer').classList.remove('rt-viewer--open');
    }

    // Star action
    document.querySelector('[data-action="star"]').addEventListener('click', function() {
        var viewer = document.getElementById('rt-message-viewer');
        var responseId = viewer.dataset.responseId;
        var isStarred = this.classList.contains('rt-viewer__action--active');

        var formData = new FormData();
        formData.append('action', 'rt_update_response_status');
        formData.append('response_id', responseId);
        formData.append('starred', isStarred ? '0' : '1');
        formData.append('nonce', nonce);

        var btn = this;
        fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    btn.classList.toggle('rt-viewer__action--active');

                    // Update card
                    var card = document.querySelector('.rt-response-card[data-response-id="' + responseId + '"]');
                    if (card) {
                        card.classList.toggle('rt-response-card--starred');
                        card.dataset.starred = isStarred ? '0' : '1';
                    }
                }
            });
    });

    // Archive action
    document.querySelector('[data-action="archive"]').addEventListener('click', function() {
        var viewer = document.getElementById('rt-message-viewer');
        var responseId = viewer.dataset.responseId;

        updateResponseStatus(responseId, 'archived');

        // Update card
        var card = document.querySelector('.rt-response-card[data-response-id="' + responseId + '"]');
        if (card) {
            card.dataset.status = 'archived';
        }

        closeViewer();
    });

    // Add note
    document.getElementById('rt-add-note').addEventListener('click', function() {
        var viewer = document.getElementById('rt-message-viewer');
        var responseId = viewer.dataset.responseId;
        var noteInput = document.getElementById('rt-note-input');
        var note = noteInput.value.trim();

        if (!note) return;

        var formData = new FormData();
        formData.append('action', 'rt_add_note');
        formData.append('response_id', responseId);
        formData.append('note', note);
        formData.append('nonce', nonce);

        fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var notesList = document.getElementById('rt-notes-list');
                    var noteData = data.data.note;
                    notesList.innerHTML += '<div class="rt-note"><div class="rt-note__header"><span class="rt-note__author">' + escapeHtml(noteData.author_name || 'You') + '</span><span class="rt-note__time">Just now</span></div><div class="rt-note__content">' + escapeHtml(noteData.note) + '</div></div>';
                    noteInput.value = '';
                }
            });
    });

    function updateResponseStatus(responseId, status) {
        var formData = new FormData();
        formData.append('action', 'rt_update_response_status');
        formData.append('response_id', responseId);
        formData.append('status', status);
        formData.append('nonce', nonce);

        fetch(ajaxUrl, { method: 'POST', body: formData });
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        var date = new Date(dateStr);
        var now = new Date();
        var diff = now - date;

        if (diff < 60000) return '<?php echo esc_js(__('Just now', 'senna-finance')); ?>';
        if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
        if (diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';
        return date.toLocaleDateString();
    }
})();
</script>
