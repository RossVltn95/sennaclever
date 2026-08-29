<?php
/**
 * Recruiter Terminal - Opportunity Page Template
 *
 * Public-facing page for candidates to respond to opportunities.
 * No login required.
 *
 * @package SennaFinanceCareer
 * @subpackage RecruiterTerminal
 *
 * Variables available:
 * - $brief (object): Brief data from rt_briefs table
 * - $recruiter (WP_User|null): Recruiter user object
 * - $tracking_id (string): Unique tracking identifier
 * - $mode (string): 'new_response' or 'existing_response'
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get recruiter display info
$recruiter_name = $recruiter ? $recruiter->display_name : __('Recruiter', 'senna-finance');
$recruiter_avatar = $recruiter ? get_avatar_url($recruiter->ID, array('size' => 80)) : '';
$recruiter_title = $recruiter ? get_user_meta($recruiter->ID, 'job_title', true) : '';
$recruiter_company = $recruiter ? get_user_meta($recruiter->ID, 'company', true) : '';

// Check if already responded
$has_responded = ($mode === 'existing_response');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html($brief->title); ?> - <?php bloginfo('name'); ?></title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Serif:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">

    <?php wp_head(); ?>

    <style>
        :root {
            --cream-primary: #fefcf6;
            --cream-secondary: #faf6ed;
            --cream-accent: #f5f0e5;
            --cream-dark: #f0ebe0;
            --green-primary: #1b3b2f;
            --green-secondary: #2d5444;
            --green-accent: #4a7259;
            --green-light: #6b8f6f;
            --gold-primary: #c8a882;
            --gold-secondary: #b8956c;
            --gold-light: #d4c4a8;
            --text-primary: #1a1a1a;
            --text-secondary: #555555;
            --text-tertiary: #888888;
            --border-elegant: #e5dfd0;
            --border-gold: #d4c4a8;
            --shadow-elegant: 0 12px 48px rgba(27, 59, 47, 0.06);
            --shadow-luxury: 0 24px 64px rgba(27, 59, 47, 0.08);
            --shadow-subtle: 0 4px 16px rgba(27, 59, 47, 0.04);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(180deg, var(--cream-primary) 0%, var(--cream-secondary) 50%, var(--cream-accent) 100%);
            color: var(--text-primary);
            line-height: 1.6;
            font-weight: 300;
            letter-spacing: -0.01em;
            min-height: 100vh;
        }

        /* Header */
        .rt-opp-header {
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid var(--border-elegant);
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .rt-opp-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Noto Serif', Georgia, serif;
            font-size: 18px;
            font-weight: 500;
            color: var(--green-primary);
        }

        .rt-opp-logo-mark {
            width: 32px;
            height: 32px;
            background: var(--green-primary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold-primary);
            font-weight: 600;
            font-size: 16px;
        }

        /* Main Container */
        .rt-opp-container {
            max-width: 680px;
            margin: 0 auto;
            padding: 48px 24px 80px;
        }

        /* Recruiter Card */
        .rt-opp-recruiter {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 32px;
        }

        .rt-opp-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-gold);
        }

        .rt-opp-avatar-placeholder {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--green-primary);
            color: var(--cream-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 20px;
        }

        .rt-opp-recruiter-info {
            flex: 1;
        }

        .rt-opp-recruiter-name {
            font-size: 17px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .rt-opp-recruiter-title {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .rt-opp-date {
            font-size: 13px;
            color: var(--text-tertiary);
            padding-top: 2px;
        }

        /* Brief Card */
        .rt-opp-brief {
            background: #ffffff;
            border-radius: 16px;
            padding: 32px;
            box-shadow: var(--shadow-elegant);
            border: 1px solid var(--border-elegant);
            margin-bottom: 32px;
        }

        .rt-opp-title {
            font-family: 'Noto Serif', Georgia, serif;
            font-size: 28px;
            font-weight: 500;
            color: var(--green-primary);
            line-height: 1.3;
            margin-bottom: 20px;
        }

        .rt-opp-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border-elegant);
        }

        .rt-opp-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .rt-opp-meta-item svg {
            width: 18px;
            height: 18px;
            color: var(--gold-secondary);
            flex-shrink: 0;
        }

        .rt-opp-content {
            font-size: 16px;
            line-height: 1.75;
            color: var(--text-primary);
        }

        .rt-opp-content p {
            margin-bottom: 16px;
        }

        .rt-opp-content p:last-child {
            margin-bottom: 0;
        }

        .rt-opp-cta {
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid var(--border-elegant);
            font-size: 15px;
            color: var(--text-secondary);
            font-style: italic;
        }

        /* Response Form */
        .rt-opp-response {
            background: #ffffff;
            border-radius: 16px;
            padding: 32px;
            box-shadow: var(--shadow-elegant);
            border: 1px solid var(--border-elegant);
        }

        .rt-opp-response-header {
            margin-bottom: 24px;
        }

        .rt-opp-response-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--green-primary);
            margin-bottom: 4px;
        }

        .rt-opp-response-subtitle {
            font-size: 14px;
            color: var(--text-tertiary);
        }

        .rt-form-row {
            margin-bottom: 20px;
        }

        .rt-form-row--two {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .rt-form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .rt-form-group label .required {
            color: var(--gold-secondary);
        }

        .rt-form-group input,
        .rt-form-group textarea {
            width: 100%;
            padding: 14px 16px;
            font-family: inherit;
            font-size: 15px;
            color: var(--text-primary);
            background: var(--cream-secondary);
            border: 1px solid var(--border-elegant);
            border-radius: 10px;
            transition: all 0.2s ease;
        }

        .rt-form-group input:focus,
        .rt-form-group textarea:focus {
            outline: none;
            border-color: var(--gold-primary);
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(200, 168, 130, 0.15);
        }

        .rt-form-group input::placeholder,
        .rt-form-group textarea::placeholder {
            color: var(--text-tertiary);
        }

        .rt-form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        /* Form Actions */
        .rt-form-actions {
            display: flex;
            gap: 12px;
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid var(--border-elegant);
        }

        .rt-btn {
            padding: 14px 28px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 500;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .rt-btn--primary {
            flex: 1;
            background: var(--green-primary);
            color: #ffffff;
        }

        .rt-btn--primary:hover {
            background: var(--green-secondary);
            transform: translateY(-1px);
            box-shadow: var(--shadow-subtle);
        }

        .rt-btn--primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .rt-btn--secondary {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-elegant);
        }

        .rt-btn--secondary:hover {
            background: var(--cream-secondary);
            border-color: var(--border-gold);
        }

        /* Success State */
        .rt-opp-success {
            display: none;
            background: #ffffff;
            border-radius: 16px;
            padding: 48px 32px;
            box-shadow: var(--shadow-elegant);
            border: 1px solid var(--border-elegant);
            text-align: center;
        }

        .rt-opp-success.visible {
            display: block;
        }

        .rt-success-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 24px;
            background: rgba(27, 59, 47, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .rt-success-icon svg {
            width: 36px;
            height: 36px;
            color: var(--green-primary);
        }

        .rt-success-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--green-primary);
            margin-bottom: 12px;
        }

        .rt-success-message {
            font-size: 16px;
            color: var(--text-secondary);
            max-width: 400px;
            margin: 0 auto;
        }

        /* Already Responded State */
        .rt-opp-responded {
            background: var(--cream-secondary);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            color: var(--text-secondary);
            font-size: 15px;
        }

        .rt-opp-responded svg {
            width: 24px;
            height: 24px;
            color: var(--green-accent);
            margin-bottom: 8px;
        }

        /* Not Interested Confirmation */
        .rt-not-interested-confirm {
            display: none;
            background: #ffffff;
            border-radius: 16px;
            padding: 32px;
            box-shadow: var(--shadow-elegant);
            border: 1px solid var(--border-elegant);
            text-align: center;
        }

        .rt-not-interested-confirm.visible {
            display: block;
        }

        .rt-not-interested-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .rt-not-interested-message {
            font-size: 15px;
            color: var(--text-secondary);
            margin-bottom: 24px;
        }

        .rt-not-interested-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        /* Footer */
        .rt-opp-footer {
            text-align: center;
            padding: 32px 24px;
            color: var(--text-tertiary);
            font-size: 13px;
        }

        .rt-opp-footer a {
            color: var(--green-accent);
            text-decoration: none;
        }

        .rt-opp-footer a:hover {
            text-decoration: underline;
        }

        /* Spinner */
        .rt-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: rt-spin 0.8s linear infinite;
        }

        @keyframes rt-spin {
            to { transform: rotate(360deg); }
        }

        /* Mobile Responsive */
        @media (max-width: 640px) {
            .rt-opp-container {
                padding: 32px 16px 60px;
            }

            .rt-opp-brief,
            .rt-opp-response {
                padding: 24px 20px;
                border-radius: 12px;
            }

            .rt-opp-title {
                font-size: 22px;
            }

            .rt-opp-meta {
                gap: 12px;
            }

            .rt-form-row--two {
                grid-template-columns: 1fr;
            }

            .rt-form-actions {
                flex-direction: column-reverse;
            }

            .rt-btn {
                width: 100%;
            }

            .rt-btn--secondary {
                order: 1;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="rt-opp-header">
        <div class="rt-opp-logo">
            <span class="rt-opp-logo-mark">S</span>
            MENA Careers
        </div>
    </header>

    <!-- Main Content -->
    <main class="rt-opp-container">
        <!-- Recruiter Info -->
        <div class="rt-opp-recruiter">
            <?php if ($recruiter_avatar): ?>
                <img src="<?php echo esc_url($recruiter_avatar); ?>" alt="" class="rt-opp-avatar">
            <?php else: ?>
                <div class="rt-opp-avatar-placeholder">
                    <?php echo esc_html(strtoupper(substr($recruiter_name, 0, 1))); ?>
                </div>
            <?php endif; ?>

            <div class="rt-opp-recruiter-info">
                <div class="rt-opp-recruiter-name"><?php echo esc_html($recruiter_name); ?></div>
                <?php if ($recruiter_title || $recruiter_company): ?>
                    <div class="rt-opp-recruiter-title">
                        <?php echo esc_html($recruiter_title); ?>
                        <?php if ($recruiter_title && $recruiter_company) echo ' at '; ?>
                        <?php echo esc_html($recruiter_company); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="rt-opp-date">
                <?php echo esc_html(human_time_diff(strtotime($brief->created_at), current_time('timestamp')) . ' ago'); ?>
            </div>
        </div>

        <!-- Brief Card -->
        <div class="rt-opp-brief">
            <h1 class="rt-opp-title"><?php echo esc_html($brief->title); ?></h1>

            <?php if (!empty($brief->location) || !empty($brief->salary_range) || !empty($brief->sector)): ?>
                <div class="rt-opp-meta">
                    <?php if (!empty($brief->location)): ?>
                        <span class="rt-opp-meta-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <?php echo esc_html($brief->location); ?>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($brief->salary_range)): ?>
                        <span class="rt-opp-meta-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="1" x2="12" y2="23"></line>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            </svg>
                            <?php echo esc_html($brief->salary_range); ?>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($brief->sector)): ?>
                        <span class="rt-opp-meta-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                            </svg>
                            <?php echo esc_html($brief->sector); ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="rt-opp-content">
                <?php echo wp_kses_post(wpautop($brief->brief)); ?>
            </div>

            <p class="rt-opp-cta">
                <?php esc_html_e('Interested? Share your contact details below to get started.', 'senna-finance'); ?>
            </p>
        </div>

        <!-- Response Form -->
        <div class="rt-opp-response" id="rt-response-section">
            <?php if ($has_responded): ?>
                <div class="rt-opp-responded">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <p><?php esc_html_e("You've already responded to this opportunity. The recruiter will be in touch soon.", 'senna-finance'); ?></p>
                </div>
            <?php else: ?>
                <div class="rt-opp-response-header">
                    <h2 class="rt-opp-response-title"><?php esc_html_e('Express your interest', 'senna-finance'); ?></h2>
                    <p class="rt-opp-response-subtitle"><?php esc_html_e('Share your preferred contact details', 'senna-finance'); ?></p>
                </div>

                <form id="rt-opportunity-form" class="rt-opportunity-form">
                    <input type="hidden" name="action" value="rt_submit_response">
                    <input type="hidden" name="tracking_id" value="<?php echo esc_attr($tracking_id); ?>">
                    <input type="hidden" name="brief_id" value="<?php echo esc_attr($brief->id); ?>">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('rt_submit_response')); ?>">

                    <div class="rt-form-row">
                        <div class="rt-form-group">
                            <label for="candidate_name"><?php esc_html_e('Your Name', 'senna-finance'); ?> <span class="required">*</span></label>
                            <input type="text" id="candidate_name" name="candidate_name" required autocomplete="name">
                        </div>
                    </div>

                    <div class="rt-form-row rt-form-row--two">
                        <div class="rt-form-group">
                            <label for="candidate_email"><?php esc_html_e('Email', 'senna-finance'); ?> <span class="required">*</span></label>
                            <input type="email" id="candidate_email" name="candidate_email" required autocomplete="email">
                        </div>
                        <div class="rt-form-group">
                            <label for="candidate_phone"><?php esc_html_e('Phone', 'senna-finance'); ?></label>
                            <input type="tel" id="candidate_phone" name="candidate_phone" autocomplete="tel">
                        </div>
                    </div>

                    <div class="rt-form-row">
                        <div class="rt-form-group">
                            <label for="candidate_linkedin"><?php esc_html_e('LinkedIn Profile', 'senna-finance'); ?></label>
                            <input type="url" id="candidate_linkedin" name="candidate_linkedin" placeholder="https://linkedin.com/in/yourprofile">
                        </div>
                    </div>

                    <div class="rt-form-row">
                        <div class="rt-form-group">
                            <label for="candidate_message"><?php esc_html_e('Message (optional)', 'senna-finance'); ?></label>
                            <textarea id="candidate_message" name="candidate_message" placeholder="<?php esc_attr_e('Tell the recruiter a bit about yourself or ask any questions...', 'senna-finance'); ?>"></textarea>
                        </div>
                    </div>

                    <div class="rt-form-actions">
                        <button type="button" class="rt-btn rt-btn--secondary" id="rt-not-interested-btn">
                            <?php esc_html_e('Not Interested', 'senna-finance'); ?>
                        </button>
                        <button type="submit" class="rt-btn rt-btn--primary" id="rt-submit-btn">
                            <?php esc_html_e('Submit Response', 'senna-finance'); ?>
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <!-- Success Message (hidden by default) -->
        <div class="rt-opp-success" id="rt-success-section">
            <div class="rt-success-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
            <h2 class="rt-success-title"><?php esc_html_e('Response Submitted!', 'senna-finance'); ?></h2>
            <p class="rt-success-message"><?php printf(esc_html__('%s will be in touch soon to discuss this opportunity with you.', 'senna-finance'), esc_html($recruiter_name)); ?></p>
        </div>

        <!-- Not Interested Confirmation (hidden by default) -->
        <div class="rt-not-interested-confirm" id="rt-not-interested-section">
            <h3 class="rt-not-interested-title"><?php esc_html_e('Are you sure?', 'senna-finance'); ?></h3>
            <p class="rt-not-interested-message"><?php esc_html_e("We'll let the recruiter know you're not interested at this time.", 'senna-finance'); ?></p>
            <div class="rt-not-interested-actions">
                <button type="button" class="rt-btn rt-btn--secondary" id="rt-cancel-decline">
                    <?php esc_html_e('Go Back', 'senna-finance'); ?>
                </button>
                <button type="button" class="rt-btn rt-btn--primary" id="rt-confirm-decline">
                    <?php esc_html_e('Confirm', 'senna-finance'); ?>
                </button>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="rt-opp-footer">
        <p>
            <?php esc_html_e('Powered by', 'senna-finance'); ?>
            <a href="<?php echo esc_url(home_url()); ?>"><?php bloginfo('name'); ?></a>
        </p>
    </footer>

    <?php if (!$has_responded): ?>
    <script>
    (function() {
        var form = document.getElementById('rt-opportunity-form');
        var responseSection = document.getElementById('rt-response-section');
        var successSection = document.getElementById('rt-success-section');
        var notInterestedSection = document.getElementById('rt-not-interested-section');
        var submitBtn = document.getElementById('rt-submit-btn');
        var notInterestedBtn = document.getElementById('rt-not-interested-btn');
        var cancelDeclineBtn = document.getElementById('rt-cancel-decline');
        var confirmDeclineBtn = document.getElementById('rt-confirm-decline');

        // Form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(form);
            formData.append('response_type', 'interested');

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="rt-spinner"></span>';

            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    responseSection.style.display = 'none';
                    successSection.classList.add('visible');
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    alert(data.data || '<?php echo esc_js(__('Something went wrong. Please try again.', 'senna-finance')); ?>');
                    submitBtn.disabled = false;
                    submitBtn.textContent = '<?php echo esc_js(__('Submit Response', 'senna-finance')); ?>';
                }
            })
            .catch(function() {
                alert('<?php echo esc_js(__('Connection error. Please try again.', 'senna-finance')); ?>');
                submitBtn.disabled = false;
                submitBtn.textContent = '<?php echo esc_js(__('Submit Response', 'senna-finance')); ?>';
            });
        });

        // Not interested flow
        notInterestedBtn.addEventListener('click', function() {
            responseSection.style.display = 'none';
            notInterestedSection.classList.add('visible');
        });

        cancelDeclineBtn.addEventListener('click', function() {
            notInterestedSection.classList.remove('visible');
            responseSection.style.display = 'block';
        });

        confirmDeclineBtn.addEventListener('click', function() {
            confirmDeclineBtn.disabled = true;
            confirmDeclineBtn.innerHTML = '<span class="rt-spinner"></span>';

            var formData = new FormData();
            formData.append('action', 'rt_submit_response');
            formData.append('tracking_id', '<?php echo esc_js($tracking_id); ?>');
            formData.append('brief_id', '<?php echo esc_js($brief->id); ?>');
            formData.append('response_type', 'not_interested');
            formData.append('nonce', '<?php echo esc_js(wp_create_nonce('rt_submit_response')); ?>');

            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    notInterestedSection.innerHTML = '<p style="text-align:center;color:var(--text-secondary);padding:32px;"><?php echo esc_js(__("Thank you for letting us know. We've noted your preference.", 'senna-finance')); ?></p>';
                } else {
                    alert(data.data || '<?php echo esc_js(__('Something went wrong. Please try again.', 'senna-finance')); ?>');
                    confirmDeclineBtn.disabled = false;
                    confirmDeclineBtn.textContent = '<?php echo esc_js(__('Confirm', 'senna-finance')); ?>';
                }
            })
            .catch(function() {
                alert('<?php echo esc_js(__('Connection error. Please try again.', 'senna-finance')); ?>');
                confirmDeclineBtn.disabled = false;
                confirmDeclineBtn.textContent = '<?php echo esc_js(__('Confirm', 'senna-finance')); ?>';
            });
        });
    })();
    </script>
    <?php endif; ?>

    <?php wp_footer(); ?>
</body>
</html>
