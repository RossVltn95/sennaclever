<?php
/**
 * Role Analyzer Template
 * Shows role details with CV analysis functionality
 *
 * @package SennaCareers
 */

if (!defined('ABSPATH')) {
    exit;
}

function sffc_render_role_analyzer($args = array()) {
    $post_id = $args['post_id'] ?? get_the_ID();
    if (!$post_id) {
        return;
    }

    $is_logged_in = is_user_logged_in();
    $prefilled_cv_text = '';

    if ($is_logged_in && !class_exists('SFFC_CRM_CV_Manager') && defined('SFFC_PLUGIN_DIR')) {
        $cv_manager_file = SFFC_PLUGIN_DIR . 'includes/crm/models/class-crm-cv.php';
        if (file_exists($cv_manager_file)) {
            require_once $cv_manager_file;
        }
    }

    if ($is_logged_in && class_exists('SFFC_CRM_CV_Manager')) {
        $cv_manager = new SFFC_CRM_CV_Manager();
        $default_cv = $cv_manager->get_default(get_current_user_id());
        if (!empty($default_cv['content'])) {
            $prefilled_cv_text = $default_cv['content'];
        }
    }

    $crm_nonce = wp_create_nonce('sffc_crm_nonce');
    $cv_upload_nonce = wp_create_nonce('sffc_cv_upload');

    // Enqueue CRM match styling + analyzer skin
    if (!wp_style_is('sffc-crm-main', 'enqueued')) {
        wp_enqueue_style(
            'sffc-crm-main',
            SFFC_PLUGIN_URL . 'assets/css/crm/crm-main.css',
            array(),
            defined('SFFC_VERSION') ? SFFC_VERSION : '1.0.0'
        );
    }

    if (!wp_style_is('sffc-role-analyzer', 'enqueued')) {
        wp_enqueue_style(
            'sffc-role-analyzer',
            SFFC_PLUGIN_URL . 'assets/css/role-analyzer.css',
            array('sffc-crm-main'),
            defined('SFFC_VERSION') ? SFFC_VERSION : '1.0.0'
        );
    }

    // Get job metadata
    $job_title = get_the_title($post_id);
    $job_content = get_post_field('post_content', $post_id);

    // Get location
    $job_location = get_post_meta($post_id, '_job_location', true);
    $locations = wp_get_post_terms($post_id, 'recruiter_post_location', ['fields' => 'names']);
    $location_label = !empty($locations) ? $locations[0] : $job_location;

    // Get experience and seniority
    $experience_range = get_post_meta($post_id, '_experience_range', true);
    $seniority_level = get_post_meta($post_id, '_seniority_level', true);

    // Get CRM post ID for gap analyzer
    global $wpdb;
    $crm_post_id = (int) get_post_meta($post_id, '_crm_post_id', true);
    if (!$crm_post_id && isset($wpdb)) {
        $crm_post_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sffc_crm_posts WHERE wp_post_id = %d LIMIT 1",
            $post_id
        ));
    }

    ?>
        <div class="sffc-role-analyzer">

        <!-- Role Header -->
        <div class="sffc-role-analyzer__header">
            <div class="sffc-role-analyzer__title-section">
                <h1 class="sffc-role-analyzer__title"><?php echo esc_html($job_title); ?></h1>

                <div class="sffc-role-analyzer__meta">
                    <?php if ($location_label): ?>
                    <div class="sffc-role-analyzer__meta-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        <span><?php echo esc_html($location_label); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($experience_range): ?>
                    <div class="sffc-role-analyzer__meta-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <span><?php echo esc_html($experience_range); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($seniority_level): ?>
                    <div class="sffc-role-analyzer__meta-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        <span><?php echo esc_html($seniority_level); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <button type="button"
                    class="sffc-role-analyzer__analyze-btn"
                    id="roleAnalyzerAnalyzeBtn"
                    data-post-id="<?php echo esc_attr($post_id); ?>"
                    data-crm-post-id="<?php echo esc_attr($crm_post_id); ?>"
                    data-job-title="<?php echo esc_attr($job_title); ?>"
                    data-apply-url="<?php echo esc_url(get_permalink($post_id) . '#apply-options'); ?>"
                    data-nonce="<?php echo esc_attr($crm_nonce); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 11l3 3L22 4"></path>
                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                </svg>
                Analyze CV
            </button>
        </div>

        <!-- Job Description -->
        <div class="sffc-role-analyzer__content">
            <h2>Job Description</h2>
            <div class="sffc-role-analyzer__description">
                <?php echo apply_filters('the_content', $job_content); ?>
            </div>
        </div>

        <div class="sffc-role-analyzer__cv-panel">
            <div class="sffc-role-analyzer__cv-header">
                <div>
                    <h3>Upload or Paste your CV</h3>
                    <p>MENA Careers needs your CV text to run the role match. Upload a PDF/Word file or paste the text manually.</p>
                </div>
                <label class="sffc-role-analyzer__cv-upload" for="roleAnalyzerCvFile">
                    <input type="file" id="roleAnalyzerCvFile" accept=".pdf,.doc,.docx,.txt">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14"></path>
                        <path d="M5 12l7-7 7 7"></path>
                        <path d="M5 19h14"></path>
                    </svg>
                    Upload CV
                </label>
            </div>
            <div class="sffc-role-analyzer__cv-status" id="roleAnalyzerCvStatus">No CV detected yet. Upload a file or paste text below.</div>
            <textarea id="roleAnalyzerCvTextarea" class="sffc-role-analyzer__cv-textarea" maxlength="8000" placeholder="Paste your CV text here (max 8,000 characters)"><?php echo esc_textarea($prefilled_cv_text); ?></textarea>
            <div class="sffc-role-analyzer__cv-footer">
                <div class="sffc-role-analyzer__cv-count" id="roleAnalyzerCvCount">0 / 8000 chars</div>
                <?php if ($is_logged_in): ?>
                <label class="sffc-role-analyzer__cv-save">
                    <input type="checkbox" id="roleAnalyzerPersistCv" checked>
                    <span>Save this CV to my profile</span>
                </label>
                <?php endif; ?>
            </div>
        </div>

        <!-- Match Results Section (hidden initially) -->
        <div class="sffc-role-analyzer__results" id="roleAnalyzerResults" style="display: none;">
            <div class="sffc-role-analyzer__results-header">
                <h2>Match Insights</h2>
                <button type="button" class="sffc-role-analyzer__close-results" id="roleAnalyzerCloseResults">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <div class="sffc-role-analyzer__match-card" id="roleAnalyzerMatchRow">
                <div class="sffc-role-analyzer__match-loader" id="roleAnalyzerMatchLoader">
                    <div class="sffc-role-analyzer__spinner"></div>
                    <span>MENA Careers is analyzing your match…</span>
                </div>
                <div class="sffc-crm-match-row">
                    <div class="sffc-crm-match-indicator">
                        <div class="sffc-crm-match-circle-container">
                            <svg class="sffc-crm-match-circle" width="120" height="120" viewBox="0 0 120 120">
                                <circle class="sffc-crm-match-circle-bg" cx="60" cy="60" r="54" fill="none" stroke="#e5e7eb" stroke-width="8"></circle>
                                <circle class="sffc-crm-match-circle-fg" id="roleAnalyzerCircle" cx="60" cy="60" r="54" fill="none" stroke="#059669" stroke-width="8" stroke-dasharray="339.29" stroke-dashoffset="339.29" stroke-linecap="round"></circle>
                            </svg>
                            <div class="sffc-crm-match-score" id="roleAnalyzerScore">0%</div>
                        </div>
                        <div class="sffc-crm-match-recruiter-name" id="roleAnalyzerLabel">Calculating...</div>
                        <div class="sffc-role-analyzer__source-note" id="roleAnalyzerSourceNotice" style="display: none;"></div>
                    </div>

                    <div class="sffc-crm-match-content">
                        <div class="sffc-crm-match-header">
                            <h4 class="sffc-crm-match-title" id="roleAnalyzerJobHeader"><?php echo esc_html($job_title); ?></h4>
                            <div class="sffc-crm-match-meta" id="roleAnalyzerMeta"></div>
                        </div>

                        <div class="sffc-role-analyzer__summary" id="roleAnalyzerSummary"></div>

                        <ul class="sffc-crm-match-reasons" id="roleAnalyzerReasons"></ul>

                        <div class="sffc-crm-match-warning" id="roleAnalyzerWarnings" style="display: none;"></div>

                        <div class="sffc-role-analyzer__pill-sections">
                            <div class="sffc-role-analyzer__pill-group">
                                <h4>Strengths</h4>
                                <div class="sffc-role-analyzer__pill-list" id="roleAnalyzerStrengths"></div>
                            </div>
                            <div class="sffc-role-analyzer__pill-group">
                                <h4>Gaps to Improve</h4>
                                <div class="sffc-role-analyzer__pill-list" id="roleAnalyzerGaps"></div>
                            </div>
                            <div class="sffc-role-analyzer__pill-group">
                                <h4>Recommended Actions</h4>
                                <div class="sffc-role-analyzer__pill-list" id="roleAnalyzerRecommendations"></div>
                            </div>
                        </div>

                        <div class="sffc-role-analyzer__keywords" id="roleAnalyzerKeywords"></div>
                    </div>

                    <div class="sffc-crm-match-actions sffc-role-analyzer__match-actions">
                    <button type="button" class="sffc-crm-btn sffc-crm-btn-secondary" id="roleAnalyzerViewDetails">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        View MENA Careers Analysis
                    </button>
                        <button type="button" class="sffc-crm-btn sffc-crm-btn-primary" id="roleAnalyzerApply"
                                data-apply-url="<?php echo esc_url(get_permalink($post_id) . '#apply-options'); ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 19l9 2-3-9-9-9-7 7 9 9z"></path>
                                <path d="M7 7l9 9"></path>
                            </svg>
                            Apply Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="sffc-role-analyzer__modal" id="roleAnalyzerModal" aria-hidden="true">
            <div class="sffc-role-analyzer__modal-backdrop" id="roleAnalyzerModalBackdrop"></div>
            <div class="sffc-role-analyzer__modal-dialog" role="dialog" aria-modal="true" aria-labelledby="roleAnalyzerModalTitle">
                <div class="sffc-role-analyzer__modal-header">
                    <h3 id="roleAnalyzerModalTitle">MENA Careers Match Analysis</h3>
                    <button type="button" class="sffc-role-analyzer__modal-close" id="roleAnalyzerModalClose" aria-label="Close analysis details">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <div class="sffc-role-analyzer__modal-body" id="roleAnalyzerModalBody">
                    <p>Run an analysis to see MENA Careers's detailed breakdown.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        document.addEventListener('DOMContentLoaded', function() {
            const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
            const crmNonce = '<?php echo esc_js($crm_nonce); ?>';
            const cvUploadNonce = '<?php echo esc_js($cv_upload_nonce); ?>';
            const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
            const analyzeBtn = document.getElementById('roleAnalyzerAnalyzeBtn');
            const resultsSection = document.getElementById('roleAnalyzerResults');
            const closeResultsBtn = document.getElementById('roleAnalyzerCloseResults');
            const applyBtn = document.getElementById('roleAnalyzerApply');
            const viewDetailsBtn = document.getElementById('roleAnalyzerViewDetails');
            const sourceNotice = document.getElementById('roleAnalyzerSourceNotice');
            const warningsEl = document.getElementById('roleAnalyzerWarnings');
            const reasonsEl = document.getElementById('roleAnalyzerReasons');
            const summaryEl = document.getElementById('roleAnalyzerSummary');
            const strengthsEl = document.getElementById('roleAnalyzerStrengths');
            const gapsEl = document.getElementById('roleAnalyzerGaps');
            const recsEl = document.getElementById('roleAnalyzerRecommendations');
            const keywordsEl = document.getElementById('roleAnalyzerKeywords');
            const metaEl = document.getElementById('roleAnalyzerMeta');
            const jobHeader = document.getElementById('roleAnalyzerJobHeader');
            const modal = document.getElementById('roleAnalyzerModal');
            const modalBody = document.getElementById('roleAnalyzerModalBody');
            const modalClose = document.getElementById('roleAnalyzerModalClose');
            const modalBackdrop = document.getElementById('roleAnalyzerModalBackdrop');
            const matchCard = document.getElementById('roleAnalyzerMatchRow');
            const matchLoader = document.getElementById('roleAnalyzerMatchLoader');
            const cvTextarea = document.getElementById('roleAnalyzerCvTextarea');
            const cvFileInput = document.getElementById('roleAnalyzerCvFile');
            const cvStatus = document.getElementById('roleAnalyzerCvStatus');
            const cvCount = document.getElementById('roleAnalyzerCvCount');
            const persistCvCheckbox = document.getElementById('roleAnalyzerPersistCv');
            const CV_MAX_LENGTH = 8000;
            const cvStorageKey = 'sffc_role_analyzer_cv_text';

            let latestAnalysis = null;
            setMatchLoading(false);

            if (!analyzeBtn) return;

            // Analyze CV button
            analyzeBtn.addEventListener('click', function() {
                const crmPostId = this.dataset.crmPostId;
                const jobTitle = this.dataset.jobTitle;
                const cvTextValue = cvTextarea ? cvTextarea.value.trim() : '';
                const persistCv = persistCvCheckbox && persistCvCheckbox.checked ? '1' : '0';

                if (!isLoggedIn && !cvTextValue) {
                    alert('Upload or paste your CV before running the analysis.');
                    return;
                }

                if (!crmPostId) {
                    alert('This role is not available for analysis at this time.');
                    return;
                }

                // Show loading state
                analyzeBtn.disabled = true;
                analyzeBtn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;"><path d="M21 13a9 9 0 1 1-3-7.7"></path><path d="M21 3v6h-6"></path></svg>Analyzing...';
                setMatchLoading(true);

                // Show results section with loading state
                resultsSection.style.display = 'block';
                resultsSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

                // Call analysis endpoint
                const params = new URLSearchParams({
                    action: 'sffc_analyze_role_match',
                    post_id: crmPostId,
                    _ajax_nonce: crmNonce
                });

                if (cvTextValue) {
                    params.append('cv_text', cvTextValue);
                }

                if (isLoggedIn && cvTextValue && persistCv === '1') {
                    params.append('persist_cv', '1');
                }

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: params
                })
                .then(response => response.json())
                .then(data => {
                    analyzeBtn.disabled = false;
                    analyzeBtn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>Analyze CV';

                    if (data.success && data.data && data.data.analysis) {
                        latestAnalysis = data.data.analysis;
                        displayResults(data.data.analysis, data.data.job_title || jobTitle, data.data.company || '');
                        setMatchLoading(false);
                    } else {
                        const errorMessage = data.data && data.data.message ? data.data.message : 'Failed to analyze CV. Please ensure you have uploaded your CV.';
                        alert(errorMessage);
                        resultsSection.style.display = 'none';
                        setMatchLoading(false);
                    }
                })
                .catch(error => {
                    console.error('Analysis error:', error);
                    analyzeBtn.disabled = false;
                    analyzeBtn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>Analyze CV';
                    alert('An error occurred during analysis. Please try again.');
                    resultsSection.style.display = 'none';
                    setMatchLoading(false);
                });
            });

            // Display results
            function escapeHtml(str) {
                return (str || '').replace(/[&<>"']/g, function(char) {
                    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
                    return map[char] || char;
                });
            }

            function setCvStatus(message, type) {
                if (!cvStatus) {
                    return;
                }
                cvStatus.textContent = message;
                cvStatus.className = 'sffc-role-analyzer__cv-status';
                if (type) {
                    cvStatus.classList.add('is-' + type);
                }
            }

            function updateCvCount() {
                if (!cvTextarea || !cvCount) return;
                cvCount.textContent = cvTextarea.value.length + ' / ' + CV_MAX_LENGTH + ' chars';
            }

            function persistCvLocally(text) {
                if (isLoggedIn || !cvTextarea) {
                    return;
                }
                try {
                    if (text && text.trim().length) {
                        localStorage.setItem(cvStorageKey, text.trim().slice(0, CV_MAX_LENGTH));
                    } else {
                        localStorage.removeItem(cvStorageKey);
                    }
                } catch (e) {
                    console.warn('Unable to persist CV locally', e);
                }
            }

            function setMatchLoading(isLoading) {
                if (!matchCard) {
                    return;
                }
                if (isLoading) {
                    matchCard.classList.add('is-loading');
                    if (matchLoader) {
                        matchLoader.style.display = 'flex';
                        matchLoader.setAttribute('aria-hidden', 'false');
                    }
                } else {
                    matchCard.classList.remove('is-loading');
                    if (matchLoader) {
                        matchLoader.style.display = 'none';
                        matchLoader.setAttribute('aria-hidden', 'true');
                    }
                }
            }

            function uploadCvFile(file) {
                if (!file) {
                    return;
                }
                const formData = new FormData();
                formData.append('action', 'professional_cv_upload');
                formData.append('nonce', cvUploadNonce);
                formData.append('cv_file', file);
                setCvStatus('Extracting text from ' + file.name + '...', 'loading');
                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data && data.success && data.data && data.data.text) {
                        let text = data.data.text || '';
                        text = text.replace(/\r\n?/g, '\n').trim();
                        if (text.length > CV_MAX_LENGTH) {
                            text = text.slice(0, CV_MAX_LENGTH);
                            setCvStatus('Text extracted. Trimmed to first 8,000 characters for analysis.', 'warning');
                        } else {
                            setCvStatus('Text extracted successfully from ' + file.name + '.', 'success');
                        }
                        if (cvTextarea) {
                            cvTextarea.value = text;
                            updateCvCount();
                            persistCvLocally(text);
                        }
                    } else {
                        throw new Error(data?.data?.message || 'Unable to extract text from that file. Please paste your CV.');
                    }
                })
                .catch(error => {
                    console.error('CV upload error:', error);
                    setCvStatus(error.message || 'Could not extract text. Please paste manually.', 'error');
                });
            }

            if (cvTextarea) {
                let initialStatusSet = false;

                if (!isLoggedIn && !cvTextarea.value.trim()) {
                    try {
                        const cachedCv = localStorage.getItem(cvStorageKey);
                        if (cachedCv) {
                            cvTextarea.value = cachedCv.slice(0, CV_MAX_LENGTH);
                            setCvStatus('Loaded your saved CV draft from this browser.', 'success');
                            initialStatusSet = true;
                        }
                    } catch (storageError) {
                        console.warn('Unable to load cached CV text', storageError);
                    }
                }

                updateCvCount();
                if (cvTextarea.value.trim().length > 0 && !initialStatusSet) {
                    setCvStatus('Loaded CV text from your profile.', 'success');
                } else if (!initialStatusSet) {
                    setCvStatus('No CV detected. Upload a file or paste text to continue.', 'warning');
                }

                if (!isLoggedIn && cvTextarea.value.trim().length > 0) {
                    persistCvLocally(cvTextarea.value);
                }

                cvTextarea.addEventListener('input', function() {
                    updateCvCount();
                    if (this.value.trim().length > 0) {
                        setCvStatus('CV text ready for analysis.', 'success');
                    } else {
                        setCvStatus('No CV detected. Upload a file or paste text to continue.', 'warning');
                    }
                    persistCvLocally(this.value);
                });
            }

            if (cvFileInput) {
                cvFileInput.addEventListener('change', function(event) {
                    const file = event.target.files ? event.target.files[0] : null;
                    if (file) {
                        uploadCvFile(file);
                    }
                });
            }

            function renderPills(container, primaryItems, fallbackItems) {
                if (!container) return;
                var source = Array.isArray(primaryItems) && primaryItems.length ? primaryItems : (Array.isArray(fallbackItems) ? fallbackItems : []);
                if (source && source.length) {
                    container.innerHTML = source.slice(0, 6).map(function(text) {
                        return '<span class="sffc-role-analyzer__pill">' + escapeHtml(text) + '</span>';
                    }).join('');
                } else {
                    container.innerHTML = '<span class="sffc-role-analyzer__pill sffc-role-analyzer__pill--muted">No insights yet</span>';
                }
            }

            function displayResults(analysis, jobTitle, company) {
                const scoreValue = document.getElementById('roleAnalyzerScore');
                const scoreLabel = document.getElementById('roleAnalyzerLabel');
                const scoreCircle = document.getElementById('roleAnalyzerCircle');

                const score = analysis.match_score || 0;
                const circumference = 2 * Math.PI * 54;
                const offset = circumference - (score / 100) * circumference;
                scoreCircle.style.strokeDasharray = circumference.toFixed(2);
                scoreCircle.style.strokeDashoffset = offset;

                let labelText = 'Low Match';
                let color = '#dc2626';
                if (score >= 80) {
                    labelText = 'Excellent Match';
                    color = '#059669';
                } else if (score >= 60) {
                    labelText = 'Good Match';
                    color = '#2563eb';
                } else if (score >= 40) {
                    labelText = 'Moderate Match';
                    color = '#d97706';
                }

                scoreCircle.style.stroke = color;
                scoreValue.textContent = score + '%';
                scoreLabel.textContent = analysis.match_level || labelText;
                scoreLabel.style.color = color;

                if (jobHeader) {
                    var sennaTitle = '';
                    if (analysis.raw && analysis.raw.executive_summary && analysis.raw.executive_summary.role_title) {
                        sennaTitle = analysis.raw.executive_summary.role_title;
                    }
                    jobHeader.textContent = jobTitle || sennaTitle || 'Role Match';
                }

                if (metaEl) {
                    const metaParts = [];
                    if (company) metaParts.push(escapeHtml(company));
                    if (analysis.risk_level) metaParts.push(escapeHtml(analysis.risk_level.replace(/_/g, ' ')));
                    if (analysis.recommendation) metaParts.push(escapeHtml(analysis.recommendation.replace(/_/g, ' ')));
                    metaEl.innerHTML = metaParts.join(' • ');
                }

                if (summaryEl) {
                    var summaryText = analysis.summary || analysis.verdict || '';
                    summaryEl.innerHTML = summaryText ? '<p>' + escapeHtml(summaryText) + '</p>' : '';
                }

                if (reasonsEl) {
                    if (analysis.match_reasons && analysis.match_reasons.length) {
                        reasonsEl.innerHTML = analysis.match_reasons.slice(0, 3).map(function(reason) {
                            return '<li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg><span>' + escapeHtml(reason) + '</span></li>';
                        }).join('');
                    } else {
                        reasonsEl.innerHTML = '';
                    }
                }

                if (warningsEl) {
                    if (analysis.warnings && analysis.warnings.length) {
                        warningsEl.style.display = 'flex';
                        warningsEl.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4"></path><path d="M12 17h.01"></path><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path></svg><span>' + escapeHtml(analysis.warnings[0]) + '</span>';
                    } else {
                        warningsEl.style.display = 'none';
                        warningsEl.innerHTML = '';
                    }
                }

                renderPills(strengthsEl, analysis.strengths, analysis.match_reasons);
                renderPills(gapsEl, analysis.gaps, analysis.warnings);
                renderPills(recsEl, analysis.recommendations, analysis.gaps);

                if (keywordsEl) {
                    const keywordData = analysis.keyword_analysis || {};
                    const matched = keywordData.matched;
                    const total = keywordData.total;
                    const percent = keywordData.match_percentage;
                    const missing = keywordData.critical_missing || [];
                    let html = '';
                    if (percent || matched) {
                        const coverage = percent ? percent + '%' : (matched && total ? Math.round((matched / total) * 100) + '%' : 'N/A');
                        html += '<div class="sffc-role-analyzer__keywords-card"><strong>Keyword Coverage:</strong> ' + escapeHtml(coverage) + ' matched.';
                        if (missing.length) {
                            html += '<div class="sffc-role-analyzer__keywords-missing"><span>Missing:</span> ' + missing.slice(0, 5).map(function(keyword) {
                                return '<span>' + escapeHtml(keyword) + '</span>';
                            }).join(' ') + '</div>';
                        }
                        html += '</div>';
                    }
                    keywordsEl.innerHTML = html;
                }

                if (applyBtn) {
                    applyBtn.disabled = !analysis.apply_allowed;
                    applyBtn.classList.toggle('sffc-role-analyzer__apply-disabled', !analysis.apply_allowed);
                    applyBtn.setAttribute('title', analysis.apply_message || '');
                }

                if (sourceNotice) {
                    if (analysis.source === 'keyword_fallback') {
                        sourceNotice.style.display = 'block';
                        sourceNotice.textContent = 'Fallback: advanced keyword scoring (MENA Careers temporarily offline)';
                    } else {
                        sourceNotice.style.display = 'none';
                        sourceNotice.textContent = '';
                    }
                }
            }

            // Close results
            if (closeResultsBtn) {
                closeResultsBtn.addEventListener('click', function() {
                    resultsSection.style.display = 'none';
                });
            }

            // Apply button tied into application flow
            if (applyBtn) {
                applyBtn.addEventListener('click', function() {
                    if (!latestAnalysis) {
                        alert('Analyze your CV match before applying.');
                        return;
                    }

                    if (applyBtn.disabled) {
                        alert(latestAnalysis.apply_message || 'Reach at least 60% match to unlock Apply.');
                        return;
                    }

                    if (triggerApplyFlow()) {
                        return;
                    }

                    const fallbackUrl = applyBtn.dataset.applyUrl || (analyzeBtn ? analyzeBtn.dataset.applyUrl : '');
                    if (fallbackUrl) {
                        window.location.href = fallbackUrl;
                    } else {
                        alert('Application section not found. Please use the job apply page.');
                    }
                });
            }

            // View detailed MENA Careers feedback
            if (viewDetailsBtn) {
                viewDetailsBtn.addEventListener('click', function() {
                    if (!latestAnalysis) {
                        alert('Run a MENA Careers analysis first to view detailed feedback.');
                        return;
                    }

                    const modalHtml = buildModalContent(latestAnalysis);
                    openModal(modalHtml);
                });
            }

            if (modalClose) {
                modalClose.addEventListener('click', closeModal);
            }

            if (modalBackdrop) {
                modalBackdrop.addEventListener('click', closeModal);
            }

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && modal && modal.classList.contains('is-visible')) {
                    closeModal();
                }
            });

            function triggerApplyFlow() {
                const applySection = document.getElementById('apply-options');
                if (applySection) {
                    applySection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    applySection.classList.add('sffc-role-analyzer__apply-highlight');
                    setTimeout(function() {
                        applySection.classList.remove('sffc-role-analyzer__apply-highlight');
                    }, 1800);
                    return true;
                }

                const applyScrollBtn = document.querySelector('[data-scroll-to="apply-options"]');
                if (applyScrollBtn) {
                    applyScrollBtn.click();
                    return true;
                }

                return false;
            }

            function openModal(contentHtml) {
                if (!modal || !modalBody) return;
                modalBody.innerHTML = contentHtml;
                modal.classList.add('is-visible');
                document.body.classList.add('sffc-role-analyzer-modal-open');
            }

            function closeModal() {
                if (!modal) return;
                modal.classList.remove('is-visible');
                document.body.classList.remove('sffc-role-analyzer-modal-open');
            }

            function buildModalContent(analysis) {
                let html = '';
                const exec = analysis.raw && analysis.raw.executive_summary ? analysis.raw.executive_summary : null;
                const scores = analysis.raw && analysis.raw.scores ? analysis.raw.scores : null;

                html += '<section class="sffc-role-analyzer__modal-section">';
                html += '<h4>Executive Verdict</h4>';
                html += '<p>' + escapeHtml(analysis.verdict || (exec ? exec.verdict || '' : analysis.summary || 'Analysis summary unavailable.')) + '</p>';
                if (exec && exec.key_insight) {
                    html += '<div class="sffc-role-analyzer__modal-pill">Key Insight: ' + escapeHtml(exec.key_insight) + '</div>';
                }
                if (analysis.risk_level) {
                    html += '<div class="sffc-role-analyzer__modal-pill sffc-role-analyzer__modal-pill--risk">Risk: ' + escapeHtml(analysis.risk_level.replace(/_/g, ' ')) + '</div>';
                }
                if (analysis.recommendation) {
                    html += '<div class="sffc-role-analyzer__modal-pill">Recommendation: ' + escapeHtml(analysis.recommendation.replace(/_/g, ' ')) + '</div>';
                }
                html += '</section>';

                if (scores) {
                    html += '<section class="sffc-role-analyzer__modal-section">';
                    html += '<h4>Score Breakdown</h4>';
                    html += '<div class="sffc-role-analyzer__pill-list">';
                    Object.keys(scores).forEach(function(key) {
                        html += '<span class="sffc-role-analyzer__pill">' + escapeHtml(key.replace(/_/g, ' ')) + ': ' + escapeHtml(String(scores[key])) + '%</span>';
                    });
                    html += '</div>';
                    html += '</section>';
                }

                const requirements = analysis.raw && Array.isArray(analysis.raw.requirements_analysis)
                    ? analysis.raw.requirements_analysis.slice(0, 6)
                    : [];

                if (requirements.length) {
                    html += '<section class="sffc-role-analyzer__modal-section">';
                    html += '<h4>Requirement Coverage</h4>';
                    html += '<table class="sffc-role-analyzer__modal-table">';
                    html += '<thead><tr><th>Requirement</th><th>Status</th><th>Action</th></tr></thead><tbody>';
                    requirements.forEach(function(req) {
                        html += '<tr>';
                        html += '<td>' + escapeHtml(req.requirement || '') + '</td>';
                        html += '<td>' + escapeHtml((req.match_status || '').replace(/_/g, ' ')) + '</td>';
                        html += '<td>' + escapeHtml(req.action_needed || '') + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table>';
                    html += '</section>';
                }

                if (analysis.strengths && analysis.strengths.length) {
                    html += '<section class="sffc-role-analyzer__modal-section">';
                    html += '<h4>Strengths</h4>';
                    html += '<ul class="sffc-role-analyzer__modal-list">';
                    analysis.strengths.slice(0, 6).forEach(function(item) {
                        html += '<li>' + escapeHtml(item) + '</li>';
                    });
                    html += '</ul></section>';
                }

                if (analysis.gaps && analysis.gaps.length) {
                    html += '<section class="sffc-role-analyzer__modal-section">';
                    html += '<h4>Gaps & Risks</h4>';
                    html += '<ul class="sffc-role-analyzer__modal-list">';
                    analysis.gaps.slice(0, 6).forEach(function(item) {
                        html += '<li>' + escapeHtml(item) + '</li>';
                    });
                    html += '</ul></section>';
                }

                if (analysis.recommendations && analysis.recommendations.length) {
                    html += '<section class="sffc-role-analyzer__modal-section">';
                    html += '<h4>Recommended Actions</h4>';
                    html += '<ul class="sffc-role-analyzer__modal-list">';
                    analysis.recommendations.slice(0, 6).forEach(function(item) {
                        html += '<li>' + escapeHtml(item) + '</li>';
                    });
                    html += '</ul></section>';
                }

                if (analysis.source === 'keyword_fallback') {
                    html += '<section class="sffc-role-analyzer__modal-section">';
                    html += '<h4>Fallback Mode</h4>';
                    html += '<p>MENA Careers was temporarily unavailable. Advanced keyword scoring provided this analysis.</p>';
                    html += '</section>';
                }

                return html || '<p>Detailed analysis unavailable.</p>';
            }
        });
    })();
    </script>
    <?php
}
