<?php
/**
 * Match Detail View Template
 *
 * Renders the detailed view of a matched opportunity in the right panel.
 * Used by the NRT Matches tab.
 *
 * Variables expected:
 * @var object $brief         The brief object with all fields
 * @var object $recruiter     Recruiter info (internal user or external recruiter)
 * @var object $match         The user_match record
 * @var array  $score_breakdown  Score breakdown by category
 * @var bool   $is_external   Whether this is an external recruiter
 * @var int    $user_id       Current user ID
 *
 * @package SennaFinanceCareer
 */

if (!defined('ABSPATH')) {
    exit;
}

// Parse skills
$skills = array();
if (!empty($brief->detected_skills)) {
    $skills = is_string($brief->detected_skills)
        ? json_decode($brief->detected_skills, true)
        : (array) $brief->detected_skills;
}

// Get user preferences for skill matching
$user_prefs = get_user_meta($user_id, 'sffc_matching_preferences', true);
$user_skills = isset($user_prefs['skills']) ? (array) $user_prefs['skills'] : array();

// Calculate match score class
$score = round($match->match_score);
$score_class = $score >= 80 ? 'excellent' : ($score >= 60 ? 'good' : ($score >= 40 ? 'fair' : 'low'));

// Parse criteria
$criteria = array();
if (!empty($brief->parsed_criteria)) {
    $criteria = is_string($brief->parsed_criteria)
        ? json_decode($brief->parsed_criteria, true)
        : (array) $brief->parsed_criteria;
}

// Match status
$match_status = $match->status ?? 'pending';
?>
<div class="nrt-match-detail">
    <!-- Recruiter Header Section -->
    <div class="nrt-match-detail__recruiter">
        <div class="nrt-match-detail__recruiter-header">
            <?php if (!empty($recruiter->photo_url)) : ?>
                <img src="<?php echo esc_url($recruiter->photo_url); ?>" alt="<?php echo esc_attr($recruiter->name ?? ''); ?>" class="nrt-match-detail__avatar">
            <?php else : ?>
                <div class="nrt-match-detail__avatar nrt-match-detail__avatar--initials">
                    <?php echo esc_html(strtoupper(substr($recruiter->name ?? 'R', 0, 1))); ?>
                </div>
            <?php endif; ?>

            <div class="nrt-match-detail__recruiter-info">
                <h3 class="nrt-match-detail__recruiter-name"><?php echo esc_html($recruiter->name ?? 'Recruiter'); ?></h3>
                <?php if (!empty($recruiter->title)) : ?>
                    <span class="nrt-match-detail__recruiter-title"><?php echo esc_html($recruiter->title); ?></span>
                <?php endif; ?>
                <?php if (!empty($recruiter->company)) : ?>
                    <span class="nrt-match-detail__recruiter-company"><?php echo esc_html($recruiter->company); ?></span>
                <?php endif; ?>

                <?php if (!empty($recruiter->rating) && $recruiter->rating > 0) : ?>
                    <div class="nrt-match-detail__rating">
                        <div class="nrt-match-detail__stars">
                            <?php for ($i = 1; $i <= 5; $i++) :
                                $filled = $i <= floor($recruiter->rating);
                                $half = !$filled && ($i - 0.5) <= $recruiter->rating;
                            ?>
                                <svg viewBox="0 0 24 24" width="14" height="14" class="nrt-star <?php echo $filled ? 'is-filled' : ($half ? 'is-half' : ''); ?>">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" fill="<?php echo $filled ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                            <?php endfor; ?>
                        </div>
                        <span class="nrt-match-detail__rating-text"><?php echo esc_html(number_format($recruiter->rating, 1)); ?></span>
                        <?php if (!empty($recruiter->review_count)) : ?>
                            <span class="nrt-match-detail__review-count">(<?php echo esc_html($recruiter->review_count); ?> reviews)</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="nrt-match-detail__contact">
                    <?php if (!empty($recruiter->location)) : ?>
                        <span class="nrt-match-detail__contact-item">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                            <?php echo esc_html($recruiter->location); ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($recruiter->website)) : ?>
                        <a href="<?php echo esc_url($recruiter->website); ?>" target="_blank" rel="noopener" class="nrt-match-detail__contact-item nrt-match-detail__contact-link">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="2" y1="12" x2="22" y2="12"/>
                                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                            </svg>
                            Website
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($is_external) : ?>
                <span class="nrt-match-detail__badge nrt-match-detail__badge--external">External</span>
            <?php endif; ?>
        </div>

        <?php if (!empty($recruiter->bio)) : ?>
            <p class="nrt-match-detail__bio"><?php echo esc_html($recruiter->bio); ?></p>
        <?php endif; ?>
    </div>

    <!-- Match Score Section -->
    <div class="nrt-match-detail__score-section">
        <div class="nrt-match-detail__score nrt-match-detail__score--<?php echo esc_attr($score_class); ?>">
            <svg viewBox="0 0 36 36" class="nrt-match-detail__score-ring">
                <path class="nrt-match-detail__score-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                <path class="nrt-match-detail__score-fill" stroke-dasharray="<?php echo esc_attr($score); ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
            </svg>
            <div class="nrt-match-detail__score-text">
                <span class="nrt-match-detail__score-value"><?php echo esc_html($score); ?>%</span>
                <span class="nrt-match-detail__score-label">Match</span>
            </div>
        </div>

        <?php if (!empty($score_breakdown)) : ?>
            <div class="nrt-match-detail__breakdown">
                <?php
                $breakdown_labels = array(
                    'keywords' => 'Keywords',
                    'industry' => 'Industry',
                    'location' => 'Location',
                    'level' => 'Level',
                    'skills' => 'Skills'
                );
                foreach ($score_breakdown as $key => $value) :
                    $label = isset($breakdown_labels[$key]) ? $breakdown_labels[$key] : ucwords(str_replace('_', ' ', $key));
                    $max_value = 30; // Adjust based on weight (keywords=30, industry=25, etc.)
                    if ($key === 'industry') $max_value = 25;
                    elseif ($key === 'location') $max_value = 20;
                    elseif ($key === 'level') $max_value = 15;
                    elseif ($key === 'skills') $max_value = 10;
                    $percentage = min(100, ($value / $max_value) * 100);
                ?>
                    <div class="nrt-match-detail__breakdown-item">
                        <span class="nrt-match-detail__breakdown-label"><?php echo esc_html($label); ?></span>
                        <div class="nrt-match-detail__breakdown-bar">
                            <div class="nrt-match-detail__breakdown-fill" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                        </div>
                        <span class="nrt-match-detail__breakdown-value"><?php echo esc_html(round($value)); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Brief Details -->
    <div class="nrt-match-detail__brief">
        <h2 class="nrt-match-detail__title"><?php echo esc_html($brief->title); ?></h2>

        <div class="nrt-match-detail__meta">
            <?php if (!empty($brief->location)) : ?>
                <div class="nrt-match-detail__meta-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                    <span><?php echo esc_html($brief->location); ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($brief->salary_range)) : ?>
                <div class="nrt-match-detail__meta-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                    <span><?php echo esc_html($brief->salary_range); ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($brief->sector)) : ?>
                <div class="nrt-match-detail__meta-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                    </svg>
                    <span><?php echo esc_html(ucwords(str_replace('_', ' ', $brief->sector))); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($brief->brief)) : ?>
            <div class="nrt-match-detail__description">
                <h4>About the Role</h4>
                <div class="nrt-match-detail__content">
                    <?php echo wp_kses_post(wpautop($brief->brief)); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($criteria)) : ?>
            <div class="nrt-match-detail__criteria">
                <h4>Requirements</h4>
                <div class="nrt-match-detail__criteria-grid">
                    <?php if (!empty($criteria['experience_level'])) : ?>
                        <div class="nrt-match-detail__criteria-item">
                            <span class="nrt-match-detail__criteria-label">Experience Level</span>
                            <span class="nrt-match-detail__criteria-value"><?php echo esc_html(ucwords(str_replace('_', ' ', $criteria['experience_level']))); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($criteria['target_locations'])) : ?>
                        <div class="nrt-match-detail__criteria-item">
                            <span class="nrt-match-detail__criteria-label">Target Locations</span>
                            <span class="nrt-match-detail__criteria-value"><?php echo esc_html($criteria['target_locations']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($criteria['education'])) : ?>
                        <div class="nrt-match-detail__criteria-item">
                            <span class="nrt-match-detail__criteria-label">Education</span>
                            <span class="nrt-match-detail__criteria-value"><?php echo esc_html($criteria['education']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($skills)) : ?>
            <div class="nrt-match-detail__skills">
                <h4>Key Skills</h4>
                <div class="nrt-match-detail__skills-list">
                    <?php foreach (array_slice($skills, 0, 15) as $skill) :
                        $skill_label = ucwords(str_replace('_', ' ', $skill));
                        $is_matched = in_array($skill, $user_skills);
                    ?>
                        <span class="nrt-match-detail__skill <?php echo $is_matched ? 'is-matched' : ''; ?>">
                            <?php if ($is_matched) : ?>
                                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="3">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                            <?php endif; ?>
                            <?php echo esc_html($skill_label); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Actions -->
    <?php if ($match_status === 'pending') : ?>
        <div class="nrt-match-detail__actions">
            <button type="button" class="nrt-match-detail__action nrt-match-detail__action--skip" data-action="skip" data-brief-id="<?php echo esc_attr($brief->id); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
                <span>Not Interested</span>
            </button>
            <button type="button" class="nrt-match-detail__action nrt-match-detail__action--interested" data-action="interested" data-brief-id="<?php echo esc_attr($brief->id); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
                <span>I'm Interested</span>
            </button>
        </div>
    <?php elseif ($match_status === 'interested') : ?>
        <div class="nrt-match-detail__actions nrt-match-detail__actions--responded">
            <div class="nrt-match-detail__confirmed">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <div class="nrt-match-detail__confirmed-text">
                    <span class="nrt-match-detail__confirmed-title">Interest Sent!</span>
                    <span class="nrt-match-detail__confirmed-subtitle">The recruiter has been notified of your interest.</span>
                </div>
            </div>
        </div>
    <?php elseif ($match_status === 'skipped') : ?>
        <div class="nrt-match-detail__actions nrt-match-detail__actions--skipped">
            <p class="nrt-match-detail__skipped-text">You passed on this opportunity.</p>
            <button type="button" class="nrt-match-detail__action nrt-match-detail__action--undo" data-action="pending" data-brief-id="<?php echo esc_attr($brief->id); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                    <path d="M3 3v5h5"/>
                </svg>
                <span>Undo</span>
            </button>
        </div>
    <?php endif; ?>
</div>
