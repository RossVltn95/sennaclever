<?php
/**
 * Recruiter Terminal - Campaign Creator View
 *
 * 5-step workflow for creating/editing campaigns.
 * Steps: Brief → Match → Select → Compose → Schedule
 *
 * @package SennaFinanceCareer
 * @subpackage RecruiterTerminal
 *
 * Variables available from parent template:
 * - $campaign (object|null): Current campaign data
 * - $campaign_id (int): Campaign ID
 * - $step (int): Current step (1-5)
 * - $targets (array): Campaign targets
 * - $templates (array): Email templates
 * - $base_url (string): Base URL for navigation
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_edit = !empty($campaign);
$step_titles = array(
    1 => __('Campaign Brief', 'senna-finance'),
    2 => __('Find Matches', 'senna-finance'),
    3 => __('Select Candidates', 'senna-finance'),
    4 => __('Compose Message', 'senna-finance'),
    5 => __('Schedule & Review', 'senna-finance'),
);

// Parse criteria from JSON if available
$parsed_criteria = array();
if ($campaign && !empty($campaign->parsed_criteria)) {
    $parsed_criteria = json_decode($campaign->parsed_criteria, true);
    if (!is_array($parsed_criteria)) {
        $parsed_criteria = array();
    }
}
$target_seniority = isset($parsed_criteria['seniority']) ? $parsed_criteria['seniority'] : '';
$target_location = isset($parsed_criteria['location']) ? $parsed_criteria['location'] : '';

// Calculate sidebar stats
$selected_count = is_array($targets) ? count($targets) : 0;
$avg_match_score = 0;
if ($selected_count > 0) {
    $total_score = 0;
    foreach ($targets as $t) {
        $total_score += isset($t->match_score) ? (int) $t->match_score : 0;
    }
    $avg_match_score = round($total_score / $selected_count);
}
?>

<div class="rt-creator" data-campaign-id="<?php echo esc_attr($campaign_id); ?>" data-step="<?php echo esc_attr($step); ?>">
    <!-- Progress Bar -->
    <div class="rt-progress">
        <div class="rt-progress__bar">
            <div class="rt-progress__fill" style="width: <?php echo esc_attr(($step / 5) * 100); ?>%;"></div>
        </div>
        <div class="rt-progress__steps">
            <?php for ($i = 1; $i <= 5; $i++) : ?>
                <div class="rt-progress__step <?php echo $i < $step ? 'rt-progress__step--completed' : ($i === $step ? 'rt-progress__step--active' : ''); ?>" data-step="<?php echo esc_attr($i); ?>">
                    <span class="rt-progress__step-number"><?php echo esc_html($i); ?></span>
                    <span class="rt-progress__step-label"><?php echo esc_html($step_titles[$i]); ?></span>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <div class="rt-creator__layout">
        <!-- Campaign Summary Sidebar -->
        <aside class="rt-sidebar" id="rt-campaign-sidebar">
            <div class="rt-sidebar__header">
                <h3 class="rt-sidebar__title"><?php esc_html_e('Campaign Summary', 'senna-finance'); ?></h3>
            </div>

            <div class="rt-sidebar__content">
                <!-- Title Section -->
                <div class="rt-sidebar__section">
                    <span class="rt-sidebar__label"><?php esc_html_e('Title', 'senna-finance'); ?></span>
                    <span class="rt-sidebar__value" id="sidebar-title"><?php echo $campaign ? esc_html($campaign->title) : '—'; ?></span>
                </div>

                <!-- Brief Section -->
                <div class="rt-sidebar__section">
                    <span class="rt-sidebar__label"><?php esc_html_e('Brief', 'senna-finance'); ?></span>
                    <span class="rt-sidebar__value rt-sidebar__value--truncate" id="sidebar-brief"><?php echo $campaign && $campaign->brief ? esc_html(wp_trim_words($campaign->brief, 20)) : '—'; ?></span>
                </div>

                <div class="rt-sidebar__divider"></div>

                <!-- Candidates Section -->
                <div class="rt-sidebar__section">
                    <span class="rt-sidebar__label"><?php esc_html_e('Candidates', 'senna-finance'); ?></span>
                    <div class="rt-sidebar__stats">
                        <div class="rt-sidebar__stat">
                            <span class="rt-sidebar__stat-value" id="sidebar-candidate-count"><?php echo esc_html($selected_count); ?></span>
                            <span class="rt-sidebar__stat-label"><?php esc_html_e('selected', 'senna-finance'); ?></span>
                        </div>
                        <div class="rt-sidebar__stat">
                            <span class="rt-sidebar__stat-value" id="sidebar-avg-score"><?php echo esc_html($avg_match_score); ?></span>
                            <span class="rt-sidebar__stat-label"><?php esc_html_e('avg. score', 'senna-finance'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="rt-sidebar__divider"></div>

                <!-- Schedule Section -->
                <div class="rt-sidebar__section">
                    <span class="rt-sidebar__label"><?php esc_html_e('Schedule', 'senna-finance'); ?></span>
                    <span class="rt-sidebar__value" id="sidebar-schedule">
                        <?php
                        if ($campaign && $campaign->scheduled_at) {
                            echo esc_html(date_i18n('M j, Y \a\t g:i A', strtotime($campaign->scheduled_at)));
                        } else {
                            esc_html_e('Not scheduled', 'senna-finance');
                        }
                        ?>
                    </span>
                </div>
            </div>

            <div class="rt-sidebar__footer">
                <button type="button" class="rt-btn rt-btn--ghost rt-btn--block" data-action="save-draft" id="sidebar-save-draft">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <polyline points="17 21 17 13 7 13 7 21"/>
                        <polyline points="7 3 7 8 15 8"/>
                    </svg>
                    <?php esc_html_e('Save Draft', 'senna-finance'); ?>
                </button>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="rt-creator__main">
            <!-- Step Content -->
            <form class="rt-creator__form" id="rt-campaign-form">
        <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign_id); ?>">
        <input type="hidden" name="current_step" value="<?php echo esc_attr($step); ?>">

        <!-- Step 1: Campaign Brief -->
        <div class="rt-step <?php echo $step === 1 ? 'rt-step--active' : ''; ?>" data-step="1">
            <div class="rt-step__header">
                <h2><?php esc_html_e('Define Your Ideal Candidate', 'senna-finance'); ?></h2>
                <p><?php esc_html_e('Tell us about the role and the type of candidate you are looking for.', 'senna-finance'); ?></p>
            </div>

            <div class="rt-step__content">
                <div class="rt-form-group">
                    <label for="campaign-title" class="rt-label"><?php esc_html_e('Campaign Title', 'senna-finance'); ?> <span class="rt-required">*</span></label>
                    <input type="text" id="campaign-title" name="title" class="rt-input" placeholder="<?php esc_attr_e('e.g., Senior Fund Manager - PE Growth Fund', 'senna-finance'); ?>" value="<?php echo esc_attr($campaign ? $campaign->title : ''); ?>" required>
                    <span class="rt-hint"><?php esc_html_e('Internal reference name for this campaign', 'senna-finance'); ?></span>
                </div>

                <div class="rt-form-group">
                    <label for="candidate-brief" class="rt-label"><?php esc_html_e('Candidate Brief', 'senna-finance'); ?> <span class="rt-required">*</span></label>
                    <textarea id="candidate-brief" name="brief" class="rt-textarea" rows="6" placeholder="<?php esc_attr_e('Describe the ideal candidate: experience level, skills, industry background, location preferences, etc.', 'senna-finance'); ?>" required><?php echo esc_textarea($campaign ? $campaign->brief : ''); ?></textarea>
                    <span class="rt-hint"><?php esc_html_e('This will be used to find matching candidates in our database', 'senna-finance'); ?></span>
                </div>

                <div class="rt-form-row">
                    <div class="rt-form-group rt-form-group--half">
                        <label for="target-seniority" class="rt-label"><?php esc_html_e('Target Seniority', 'senna-finance'); ?></label>
                        <select id="target-seniority" name="target_seniority" class="rt-select">
                            <option value=""><?php esc_html_e('Any Level', 'senna-finance'); ?></option>
                            <option value="entry" <?php selected($target_seniority === 'entry'); ?>><?php esc_html_e('Entry Level', 'senna-finance'); ?></option>
                            <option value="mid" <?php selected($target_seniority === 'mid'); ?>><?php esc_html_e('Mid Level', 'senna-finance'); ?></option>
                            <option value="senior" <?php selected($target_seniority === 'senior'); ?>><?php esc_html_e('Senior', 'senna-finance'); ?></option>
                            <option value="director" <?php selected($target_seniority === 'director'); ?>><?php esc_html_e('Director+', 'senna-finance'); ?></option>
                            <option value="c-suite" <?php selected($target_seniority === 'c-suite'); ?>><?php esc_html_e('C-Suite', 'senna-finance'); ?></option>
                        </select>
                    </div>
                    <div class="rt-form-group rt-form-group--half">
                        <label for="target-location" class="rt-label"><?php esc_html_e('Location', 'senna-finance'); ?></label>
                        <input type="text" id="target-location" name="target_location" class="rt-input" placeholder="<?php esc_attr_e('e.g., London, New York, Remote', 'senna-finance'); ?>" value="<?php echo esc_attr($target_location); ?>">
                    </div>
                </div>
            </div>

            <div class="rt-step__footer">
                <a href="<?php echo esc_url($base_url); ?>" class="rt-btn rt-btn--ghost"><?php esc_html_e('Cancel', 'senna-finance'); ?></a>
                <button type="button" class="rt-btn rt-btn--primary" data-action="next-step">
                    <?php esc_html_e('Find Matches', 'senna-finance'); ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Step 2: Find Matches -->
        <div class="rt-step <?php echo $step === 2 ? 'rt-step--active' : ''; ?>" data-step="2">
            <div class="rt-step__header">
                <h2><?php esc_html_e('Matching Candidates', 'senna-finance'); ?></h2>
                <p><?php esc_html_e('We found candidates that match your brief. Review and adjust if needed.', 'senna-finance'); ?></p>
            </div>

            <div class="rt-step__content">
                <div class="rt-match-status">
                    <div class="rt-match-status__searching" style="display: none;">
                        <div class="rt-spinner"></div>
                        <span><?php esc_html_e('Searching for matching candidates...', 'senna-finance'); ?></span>
                    </div>
                    <div class="rt-match-status__results">
                        <span class="rt-match-status__count">0</span>
                        <span class="rt-match-status__label"><?php esc_html_e('candidates found', 'senna-finance'); ?></span>
                    </div>
                </div>

                <div class="rt-match-filters">
                    <div class="rt-form-group">
                        <label class="rt-label"><?php esc_html_e('Refine Results', 'senna-finance'); ?></label>
                        <div class="rt-filter-chips" id="rt-filter-chips">
                            <!-- Dynamically populated -->
                        </div>
                    </div>
                </div>

                <div class="rt-candidates-preview" id="rt-candidates-preview">
                    <!-- Populated via AJAX -->
                </div>
            </div>

            <div class="rt-step__footer">
                <button type="button" class="rt-btn rt-btn--ghost" data-action="prev-step">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                    <?php esc_html_e('Back', 'senna-finance'); ?>
                </button>
                <button type="button" class="rt-btn rt-btn--primary" data-action="next-step">
                    <?php esc_html_e('Select Candidates', 'senna-finance'); ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Step 3: Select Candidates -->
        <div class="rt-step <?php echo $step === 3 ? 'rt-step--active' : ''; ?>" data-step="3">
            <div class="rt-step__header">
                <h2><?php esc_html_e('Select Your Targets', 'senna-finance'); ?></h2>
                <p><?php esc_html_e('Choose which candidates to include in your outreach campaign.', 'senna-finance'); ?></p>
            </div>

            <div class="rt-step__content">
                <div class="rt-selection-bar">
                    <div class="rt-selection-bar__info">
                        <span class="rt-selection-bar__count">0</span>
                        <span><?php esc_html_e('candidates selected', 'senna-finance'); ?></span>
                    </div>
                    <div class="rt-selection-bar__actions">
                        <button type="button" class="rt-btn rt-btn--small rt-btn--ghost" data-action="select-all">
                            <?php esc_html_e('Select All', 'senna-finance'); ?>
                        </button>
                        <button type="button" class="rt-btn rt-btn--small rt-btn--ghost" data-action="deselect-all">
                            <?php esc_html_e('Clear Selection', 'senna-finance'); ?>
                        </button>
                    </div>
                </div>

                <div class="rt-candidates-list" id="rt-candidates-list">
                    <!-- Populated via AJAX with checkboxes -->
                </div>
            </div>

            <div class="rt-step__footer">
                <button type="button" class="rt-btn rt-btn--ghost" data-action="prev-step">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                    <?php esc_html_e('Back', 'senna-finance'); ?>
                </button>
                <button type="button" class="rt-btn rt-btn--primary" data-action="next-step">
                    <?php esc_html_e('Compose Message', 'senna-finance'); ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Step 4: Compose Message -->
        <div class="rt-step <?php echo $step === 4 ? 'rt-step--active' : ''; ?>" data-step="4">
            <div class="rt-step__header">
                <h2><?php esc_html_e('Craft Your Message', 'senna-finance'); ?></h2>
                <p><?php esc_html_e('Write a compelling outreach email. Use variables to personalize.', 'senna-finance'); ?></p>
            </div>

            <div class="rt-step__content">
                <div class="rt-composer">
                    <div class="rt-composer__editor">
                        <div class="rt-form-group">
                            <label for="outreach-subject" class="rt-label"><?php esc_html_e('Subject Line', 'senna-finance'); ?> <span class="rt-required">*</span></label>
                            <input type="text" id="outreach-subject" name="outreach_subject" class="rt-input" placeholder="<?php esc_attr_e('e.g., {{candidate_name}}, opportunity at leading PE firm', 'senna-finance'); ?>" value="<?php echo esc_attr($campaign ? $campaign->outreach_subject : ''); ?>">
                        </div>

                        <div class="rt-form-group">
                            <label for="outreach-message" class="rt-label"><?php esc_html_e('Email Body', 'senna-finance'); ?> <span class="rt-required">*</span></label>
                            <textarea id="outreach-message" name="outreach_message" class="rt-textarea rt-textarea--large" rows="12" placeholder="<?php esc_attr_e('Write your message here...', 'senna-finance'); ?>"><?php echo esc_textarea($campaign ? $campaign->outreach_message : ''); ?></textarea>
                        </div>

                        <div class="rt-variables">
                            <span class="rt-variables__label"><?php esc_html_e('Insert Variable:', 'senna-finance'); ?></span>
                            <div class="rt-variables__list">
                                <button type="button" class="rt-variable-btn" data-variable="{{candidate_name}}">Name</button>
                                <button type="button" class="rt-variable-btn" data-variable="{{candidate_title}}">Title</button>
                                <button type="button" class="rt-variable-btn" data-variable="{{candidate_company}}">Company</button>
                                <button type="button" class="rt-variable-btn" data-variable="{{role_title}}">Role</button>
                                <button type="button" class="rt-variable-btn" data-variable="{{recruiter_name}}">Your Name</button>
                            </div>
                        </div>
                    </div>

                    <div class="rt-composer__preview">
                        <div class="rt-preview-header">
                            <h4><?php esc_html_e('Preview', 'senna-finance'); ?></h4>
                            <button type="button" class="rt-btn rt-btn--small rt-btn--ghost" data-action="refresh-preview">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                    <polyline points="23,4 23,10 17,10"/>
                                    <path d="M20.49,15a9,9,0,1,1-2.12-9.36L23,10"/>
                                </svg>
                            </button>
                        </div>
                        <div class="rt-preview-email" id="rt-preview-email">
                            <div class="rt-preview-email__subject">
                                <span class="rt-preview-email__label"><?php esc_html_e('Subject:', 'senna-finance'); ?></span>
                                <span class="rt-preview-email__value" id="preview-subject"></span>
                            </div>
                            <div class="rt-preview-email__body" id="preview-body">
                                <!-- Preview rendered here -->
                            </div>
                            <div class="rt-preview-email__buttons">
                                <span class="rt-preview-btn rt-preview-btn--primary"><?php esc_html_e("Yes, I'm interested", 'senna-finance'); ?></span>
                                <span class="rt-preview-btn rt-preview-btn--secondary"><?php esc_html_e('No thanks', 'senna-finance'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rt-step__footer">
                <button type="button" class="rt-btn rt-btn--ghost" data-action="prev-step">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                    <?php esc_html_e('Back', 'senna-finance'); ?>
                </button>
                <button type="button" class="rt-btn rt-btn--secondary" data-action="send-test">
                    <?php esc_html_e('Send Test Email', 'senna-finance'); ?>
                </button>
                <button type="button" class="rt-btn rt-btn--primary" data-action="next-step">
                    <?php esc_html_e('Schedule', 'senna-finance'); ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Step 5: Schedule & Review -->
        <div class="rt-step <?php echo $step === 5 ? 'rt-step--active' : ''; ?>" data-step="5">
            <div class="rt-step__header">
                <h2><?php esc_html_e('Review & Schedule', 'senna-finance'); ?></h2>
                <p><?php esc_html_e('Final review before submitting for approval.', 'senna-finance'); ?></p>
            </div>

            <div class="rt-step__content">
                <div class="rt-review">
                    <!-- Campaign Summary -->
                    <div class="rt-review__section">
                        <h4 class="rt-review__section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                            </svg>
                            <?php esc_html_e('Campaign Summary', 'senna-finance'); ?>
                        </h4>
                        <div class="rt-review__content">
                            <div class="rt-review__row">
                                <span class="rt-review__label"><?php esc_html_e('Title', 'senna-finance'); ?></span>
                                <span class="rt-review__value" id="review-title"><?php echo esc_html($campaign ? $campaign->title : ''); ?></span>
                            </div>
                            <div class="rt-review__row">
                                <span class="rt-review__label"><?php esc_html_e('Brief', 'senna-finance'); ?></span>
                                <span class="rt-review__value" id="review-brief"><?php echo esc_html($campaign ? wp_trim_words($campaign->brief, 30) : ''); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Audience -->
                    <div class="rt-review__section">
                        <h4 class="rt-review__section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                            <?php esc_html_e('Target Audience', 'senna-finance'); ?>
                        </h4>
                        <div class="rt-review__content">
                            <div class="rt-review__row">
                                <span class="rt-review__label"><?php esc_html_e('Selected Candidates', 'senna-finance'); ?></span>
                                <span class="rt-review__value rt-review__value--highlight" id="review-candidate-count">0</span>
                            </div>
                        </div>
                    </div>

                    <!-- Schedule -->
                    <div class="rt-review__section">
                        <h4 class="rt-review__section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            <?php esc_html_e('Schedule', 'senna-finance'); ?>
                        </h4>
                        <div class="rt-review__content">
                            <div class="rt-schedule-options">
                                <label class="rt-radio-card">
                                    <input type="radio" name="schedule_type" value="now" checked>
                                    <span class="rt-radio-card__content">
                                        <span class="rt-radio-card__title"><?php esc_html_e('Send Immediately', 'senna-finance'); ?></span>
                                        <span class="rt-radio-card__desc"><?php esc_html_e('Campaign will go live after approval', 'senna-finance'); ?></span>
                                    </span>
                                </label>
                                <label class="rt-radio-card">
                                    <input type="radio" name="schedule_type" value="scheduled">
                                    <span class="rt-radio-card__content">
                                        <span class="rt-radio-card__title"><?php esc_html_e('Schedule for Later', 'senna-finance'); ?></span>
                                        <span class="rt-radio-card__desc"><?php esc_html_e('Choose a specific date and time', 'senna-finance'); ?></span>
                                    </span>
                                </label>
                            </div>

                            <div class="rt-schedule-datetime" id="rt-schedule-datetime" style="display: none;">
                                <div class="rt-form-row">
                                    <div class="rt-form-group rt-form-group--half">
                                        <label for="schedule-date" class="rt-label"><?php esc_html_e('Date', 'senna-finance'); ?></label>
                                        <input type="date" id="schedule-date" name="schedule_date" class="rt-input" min="<?php echo esc_attr(date('Y-m-d')); ?>">
                                    </div>
                                    <div class="rt-form-group rt-form-group--half">
                                        <label for="schedule-time" class="rt-label"><?php esc_html_e('Time', 'senna-finance'); ?></label>
                                        <input type="time" id="schedule-time" name="schedule_time" class="rt-input" value="09:00">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Approval Notice -->
                <div class="rt-notice rt-notice--info">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="16" x2="12" y2="12"/>
                        <line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    <div>
                        <strong><?php esc_html_e('Approval Required', 'senna-finance'); ?></strong>
                        <p><?php esc_html_e('Your campaign will be reviewed by our team before sending. This usually takes less than 24 hours.', 'senna-finance'); ?></p>
                    </div>
                </div>
            </div>

            <div class="rt-step__footer">
                <button type="button" class="rt-btn rt-btn--ghost" data-action="prev-step">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                    <?php esc_html_e('Back', 'senna-finance'); ?>
                </button>
                <button type="button" class="rt-btn rt-btn--secondary" data-action="save-draft">
                    <?php esc_html_e('Save as Draft', 'senna-finance'); ?>
                </button>
                <button type="submit" class="rt-btn rt-btn--primary rt-btn--large" data-action="submit-campaign">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13"/>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                    </svg>
                    <?php esc_html_e('Submit for Approval', 'senna-finance'); ?>
                </button>
            </div>
        </div>
        </form>
        </div><!-- /.rt-creator__main -->
    </div><!-- /.rt-creator__layout -->
</div><!-- /.rt-creator -->

<!-- Test Email Modal -->
<div class="rt-modal" id="rt-test-email-modal" style="display: none;">
    <div class="rt-modal__backdrop"></div>
    <div class="rt-modal__content">
        <div class="rt-modal__header">
            <h3><?php esc_html_e('Send Test Email', 'senna-finance'); ?></h3>
            <button type="button" class="rt-modal__close" data-action="close-modal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="rt-modal__body">
            <div class="rt-form-group">
                <label for="test-email-address" class="rt-label"><?php esc_html_e('Email Address', 'senna-finance'); ?></label>
                <input type="email" id="test-email-address" class="rt-input" placeholder="<?php esc_attr_e('your@email.co', 'senna-finance'); ?>" value="<?php echo esc_attr($current_user->user_email); ?>">
            </div>
        </div>
        <div class="rt-modal__footer">
            <button type="button" class="rt-btn rt-btn--ghost" data-action="close-modal"><?php esc_html_e('Cancel', 'senna-finance'); ?></button>
            <button type="button" class="rt-btn rt-btn--primary" data-action="confirm-test-email"><?php esc_html_e('Send Test', 'senna-finance'); ?></button>
        </div>
    </div>
</div>
