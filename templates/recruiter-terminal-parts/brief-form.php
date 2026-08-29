<?php
/**
 * Recruiter Terminal - Brief Creation/Edit Form
 *
 * Form for creating and editing role briefs.
 *
 * @package SennaFinanceCareer
 * @subpackage RecruiterTerminal
 *
 * Variables available from parent template:
 * - $brief (object|null): Existing brief data if editing
 * - $current_user (WP_User): Current user object
 * - $user_avatar (string): User avatar URL
 * - $base_url (string): Base URL for navigation
 */

if (!defined('ABSPATH')) {
    exit;
}

// Determine if editing
$is_editing = !empty($brief);
$brief_id = $is_editing ? $brief->id : 0;

// Get existing values or defaults
$title = $is_editing ? $brief->title : '';
$brief_text = $is_editing ? $brief->brief : '';
$location = $is_editing ? $brief->location : '';
$sector = $is_editing ? $brief->sector : '';
$salary_range = $is_editing ? $brief->salary_range : '';

// Parse criteria
$criteria = array();
if ($is_editing && !empty($brief->criteria)) {
    $criteria = json_decode($brief->criteria, true) ?: array();
}
$experience_level = isset($criteria['experience_level']) ? $criteria['experience_level'] : '';
$target_locations = isset($criteria['target_locations']) ? $criteria['target_locations'] : '';
$keywords = isset($criteria['keywords']) ? $criteria['keywords'] : '';

// Get recruiter profile info
$recruiter_title = get_user_meta($current_user->ID, 'job_title', true);
$recruiter_company = get_user_meta($current_user->ID, 'company', true);

// Sector options
$sectors = array(
    ''               => __('Select Sector', 'senna-finance'),
    'asset_management' => __('Asset Management', 'senna-finance'),
    'banking'        => __('Banking', 'senna-finance'),
    'consultancy'    => __('Consultancy', 'senna-finance'),
    'fintech'        => __('Fintech', 'senna-finance'),
    'private_credit' => __('Private Credit', 'senna-finance'),
    'insurance'      => __('Insurance', 'senna-finance'),
    'private_equity' => __('Private Equity', 'senna-finance'),
    'venture_capital'=> __('Venture Capital', 'senna-finance'),
    'real_estate'    => __('Real Estate', 'senna-finance'),
    'other'          => __('Other', 'senna-finance'),
);

// Experience level options
$experience_levels = array(
    ''           => __('Any Experience', 'senna-finance'),
    'entry'      => __('Entry Level (0-2 years)', 'senna-finance'),
    'mid'        => __('Mid Level (3-5 years)', 'senna-finance'),
    'senior'     => __('Senior (5-10 years)', 'senna-finance'),
    'director'   => __('Director/VP (10+ years)', 'senna-finance'),
    'c_suite'    => __('C-Suite/Executive', 'senna-finance'),
);
?>

<div class="rt-brief-form-container">
    <!-- Header -->
    <div class="rt-form-header">
        <a href="<?php echo esc_url($base_url); ?>" class="rt-back-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12 19 5 12 12 5"/>
            </svg>
            <?php esc_html_e('Back', 'senna-finance'); ?>
        </a>
        <h1><?php echo $is_editing ? esc_html__('Edit Brief', 'senna-finance') : esc_html__('Create New Brief', 'senna-finance'); ?></h1>
    </div>

    <!-- Form -->
    <form id="rt-brief-form" class="rt-brief-form">
        <input type="hidden" name="action" value="rt_save_brief">
        <input type="hidden" name="brief_id" value="<?php echo esc_attr($brief_id); ?>">
        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('recruiter_terminal_nonce')); ?>">

        <!-- Your Profile Preview -->
        <div class="rt-form-section">
            <h2 class="rt-form-section__title"><?php esc_html_e('Your Profile', 'senna-finance'); ?></h2>
            <p class="rt-form-section__subtitle"><?php esc_html_e('This information will be shown to candidates', 'senna-finance'); ?></p>

            <div class="rt-profile-preview">
                <?php if ($user_avatar) : ?>
                    <img src="<?php echo esc_url($user_avatar); ?>" alt="" class="rt-profile-preview__avatar">
                <?php else : ?>
                    <div class="rt-profile-preview__initials"><?php echo esc_html(strtoupper(substr($current_user->display_name, 0, 1))); ?></div>
                <?php endif; ?>
                <div class="rt-profile-preview__info">
                    <span class="rt-profile-preview__name"><?php echo esc_html($current_user->display_name); ?></span>
                    <?php if ($recruiter_title || $recruiter_company) : ?>
                        <span class="rt-profile-preview__title">
                            <?php echo esc_html($recruiter_title); ?>
                            <?php if ($recruiter_title && $recruiter_company) echo ' @ '; ?>
                            <?php echo esc_html($recruiter_company); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <a href="<?php echo esc_url(admin_url('profile.php')); ?>" class="rt-profile-preview__edit">
                    <?php esc_html_e('Edit Profile', 'senna-finance'); ?>
                </a>
            </div>
        </div>

        <!-- Brief Details -->
        <div class="rt-form-section">
            <h2 class="rt-form-section__title"><?php esc_html_e('Brief Details', 'senna-finance'); ?></h2>

            <div class="rt-form-group">
                <label for="brief_title"><?php esc_html_e('Role Title', 'senna-finance'); ?> <span class="required">*</span></label>
                <input type="text" id="brief_title" name="title" value="<?php echo esc_attr($title); ?>" required
                       placeholder="<?php esc_attr_e('e.g., Finance Director', 'senna-finance'); ?>">
            </div>

            <div class="rt-form-row rt-form-row--two">
                <div class="rt-form-group">
                    <label for="brief_location"><?php esc_html_e('Location', 'senna-finance'); ?></label>
                    <input type="text" id="brief_location" name="location" value="<?php echo esc_attr($location); ?>"
                           placeholder="<?php esc_attr_e('e.g., Dubai, Abu Dhabi, Riyadh, Cairo', 'senna-finance'); ?>">
                </div>

                <div class="rt-form-group">
                    <label for="brief_sector"><?php esc_html_e('Sector', 'senna-finance'); ?></label>
                    <select id="brief_sector" name="sector">
                        <?php foreach ($sectors as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($sector, $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="rt-form-group">
                <label for="brief_salary"><?php esc_html_e('Salary Range', 'senna-finance'); ?> <span class="optional">(<?php esc_html_e('optional', 'senna-finance'); ?>)</span></label>
                <input type="text" id="brief_salary" name="salary_range" value="<?php echo esc_attr($salary_range); ?>"
                       placeholder="<?php esc_attr_e('e.g., £80,000 - £120,000 or Competitive', 'senna-finance'); ?>">
            </div>

            <div class="rt-form-group">
                <label for="brief_content"><?php esc_html_e('Brief Description', 'senna-finance'); ?> <span class="required">*</span></label>
                <textarea id="brief_content" name="brief" rows="8" required
                          placeholder="<?php esc_attr_e('Describe the role, requirements, and what makes this opportunity compelling...', 'senna-finance'); ?>"><?php echo esc_textarea($brief_text); ?></textarea>
                <p class="rt-form-hint"><?php esc_html_e('Tip: Be specific about the opportunity. Candidates respond better to authentic, detailed briefs.', 'senna-finance'); ?></p>
            </div>
        </div>

        <!-- Candidate Criteria -->
        <div class="rt-form-section">
            <h2 class="rt-form-section__title"><?php esc_html_e('Candidate Criteria', 'senna-finance'); ?></h2>
            <p class="rt-form-section__subtitle"><?php esc_html_e('Help us find the right candidates for your role', 'senna-finance'); ?></p>

            <div class="rt-form-row rt-form-row--two">
                <div class="rt-form-group">
                    <label for="experience_level"><?php esc_html_e('Experience Level', 'senna-finance'); ?></label>
                    <select id="experience_level" name="experience_level">
                        <?php foreach ($experience_levels as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($experience_level, $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="rt-form-group">
                    <label for="target_locations"><?php esc_html_e('Target Locations', 'senna-finance'); ?></label>
                    <input type="text" id="target_locations" name="target_locations" value="<?php echo esc_attr($target_locations); ?>"
                           placeholder="<?php esc_attr_e('e.g., Dubai, Abu Dhabi, Riyadh, Cairo', 'senna-finance'); ?>">
                </div>
            </div>

            <div class="rt-form-group">
                <label for="keywords"><?php esc_html_e('Role Keywords', 'senna-finance'); ?></label>
                <input type="text" id="keywords" name="keywords" value="<?php echo esc_attr($keywords); ?>"
                       placeholder="<?php esc_attr_e('e.g., IR, Investor Relations, Fundraising, Asset Management', 'senna-finance'); ?>">
                <p class="rt-form-hint"><?php esc_html_e('Comma-separated keywords to help match with relevant candidates', 'senna-finance'); ?></p>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="rt-form-actions">
            <button type="button" class="rt-btn rt-btn--secondary" id="rt-save-draft">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                </svg>
                <?php esc_html_e('Save Draft', 'senna-finance'); ?>
            </button>
            <button type="submit" class="rt-btn rt-btn--primary" id="rt-submit-brief">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                    <line x1="22" y1="2" x2="11" y2="13"/>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
                <?php esc_html_e('Submit for Review', 'senna-finance'); ?>
            </button>
        </div>

        <!-- Status Message -->
        <div class="rt-form-status" id="rt-form-status"></div>
    </form>
</div>

<script>
(function() {
    var form = document.getElementById('rt-brief-form');
    var saveDraftBtn = document.getElementById('rt-save-draft');
    var submitBtn = document.getElementById('rt-submit-brief');
    var statusEl = document.getElementById('rt-form-status');

    function showStatus(message, type) {
        statusEl.textContent = message;
        statusEl.className = 'rt-form-status rt-form-status--' + type;
        statusEl.style.display = 'block';
        if (type === 'success') {
            setTimeout(function() {
                statusEl.style.display = 'none';
            }, 3000);
        }
    }

    function submitForm(submitForReview) {
        var formData = new FormData(form);

        // Determine button to disable
        var btn = submitForReview ? submitBtn : saveDraftBtn;
        var originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="rt-spinner"></span> ' + (submitForReview ? '<?php echo esc_js(__('Submitting...', 'senna-finance')); ?>' : '<?php echo esc_js(__('Saving...', 'senna-finance')); ?>');

        fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                // Update brief_id if new brief
                if (data.data.brief_id) {
                    form.querySelector('[name="brief_id"]').value = data.data.brief_id;
                }

                if (submitForReview) {
                    // Submit for review
                    var submitData = new FormData();
                    submitData.append('action', 'rt_submit_brief');
                    submitData.append('brief_id', data.data.brief_id);
                    submitData.append('nonce', form.querySelector('[name="nonce"]').value);

                    return fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                        method: 'POST',
                        body: submitData
                    }).then(function(r) { return r.json(); });
                } else {
                    showStatus('<?php echo esc_js(__('Draft saved successfully!', 'senna-finance')); ?>', 'success');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    return null;
                }
            } else {
                throw new Error(data.data || '<?php echo esc_js(__('Failed to save brief.', 'senna-finance')); ?>');
            }
        })
        .then(function(submitResult) {
            if (submitResult) {
                if (submitResult.success) {
                    showStatus('<?php echo esc_js(__('Brief submitted for review! Redirecting...', 'senna-finance')); ?>', 'success');
                    setTimeout(function() {
                        window.location.href = '<?php echo esc_url($base_url); ?>';
                    }, 1500);
                } else {
                    throw new Error(submitResult.data || '<?php echo esc_js(__('Failed to submit brief.', 'senna-finance')); ?>');
                }
            }
        })
        .catch(function(error) {
            showStatus(error.message, 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    }

    // Save draft
    saveDraftBtn.addEventListener('click', function(e) {
        e.preventDefault();
        submitForm(false);
    });

    // Submit for review
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        // Validate required fields
        var title = form.querySelector('[name="title"]').value.trim();
        var brief = form.querySelector('[name="brief"]').value.trim();

        if (!title) {
            showStatus('<?php echo esc_js(__('Please enter a role title.', 'senna-finance')); ?>', 'error');
            form.querySelector('[name="title"]').focus();
            return;
        }

        if (!brief) {
            showStatus('<?php echo esc_js(__('Please enter a brief description.', 'senna-finance')); ?>', 'error');
            form.querySelector('[name="brief"]').focus();
            return;
        }

        submitForm(true);
    });
})();
</script>
