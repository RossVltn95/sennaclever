<?php
/**
 * Application Pack Toolkit Renderer
 *
 * Renders the Application Toolkit section on job listing pages with smart mock previews.
 * Previews are based on job data, not actual generated documents (no API calls).
 *
 * @package SFFC_Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Application_Pack_Toolkit {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Tiers manager instance
     */
    private $tiers;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->tiers = SFFC_Application_Pack_Tiers::get_instance();
    }

    /**
     * Render the toolkit section for a job
     *
     * @param int   $job_id Job post ID
     * @param array $meta   Job meta data
     * @param array $user_profile Optional user profile data
     * @return string HTML output
     */
    public function render($job_id, $meta = array(), $user_profile = null) {
        // Check if toolkit is enabled
        if (!SFFC_Application_Pack_Admin::is_enabled()) {
            return '';
        }

        // Get job data
        $job = get_post($job_id);
        if (!$job) {
            return '';
        }

        $job_title = $job->post_title;
        $company = $meta['sffc_actual_company'] ?? $meta['sffc_source_name'] ?? 'this company';
        $industry = $meta['sffc_industry'] ?? '';
        $location = isset($meta['sffc_location']) ? ($meta['sffc_location_city'] ?? '') . ', ' . ($meta['sffc_location_country'] ?? '') : '';
        $location = trim($location, ', ');

        // Get enhanced summary for rich preview data
        $enhanced_summary = get_post_meta($job_id, 'sffc_enhanced_summary', true);
        if (is_string($enhanced_summary)) {
            $enhanced_summary = json_decode($enhanced_summary, true);
        }
        if (!is_array($enhanced_summary)) {
            $enhanced_summary = array();
        }

        // Get user info
        $is_logged_in = is_user_logged_in();
        $user_tier_info = $this->tiers->get_user_tier_info();
        $user_name = '';

        if ($is_logged_in && $user_profile) {
            $user_name = $user_profile['name'] ?? '';
        } elseif ($is_logged_in) {
            $current_user = wp_get_current_user();
            $user_name = $current_user->display_name;
        }

        // Get documents and determine which to show
        $documents = $this->tiers->get_enabled_documents();
        $initial_docs = array_slice($documents, 0, 3, true);
        $hidden_docs = array_slice($documents, 3, null, true);

        // Calculate a mock match score for demonstration
        $base_score = 48 + (strlen($job_title) % 20);
        $improved_score = min($base_score + 38, 95);

        ob_start();
        ?>
        <section class="sffc-content-section sffc-application-toolkit sffc-application-toolkit--enhanced">

            <div class="sffc-toolkit sffc-toolkit--v2"
                 data-job-id="<?php echo esc_attr($job_id); ?>"
                 data-user-tier="<?php echo esc_attr($user_tier_info['id']); ?>"
                 data-logged-in="<?php echo $is_logged_in ? 'true' : 'false'; ?>">

                <!-- Enhanced Header -->
                <div class="sffc-toolkit__header-v2">
                    <div class="sffc-toolkit__badge-v2">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                            <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        <span>Application Toolkit Ready</span>
                    </div>
                    <h2 class="sffc-toolkit__title-v2">Boost Your Application</h2>
                    <p class="sffc-toolkit__subtitle-v2">
                        Tailored specifically for <strong><?php echo esc_html($job_title); ?></strong> at <strong><?php echo esc_html($company); ?></strong>
                    </p>
                    <?php if ($is_logged_in): ?>
                    <div class="sffc-toolkit__tier-badge-v2" data-tier="<?php echo esc_attr($user_tier_info['id']); ?>" style="background: <?php echo esc_attr($user_tier_info['bg_color']); ?>; color: <?php echo esc_attr($user_tier_info['color']); ?>;">
                        <?php echo esc_html($user_tier_info['name']); ?> Member
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Before/After Comparison -->
                <div class="sffc-toolkit__comparison-v2">
                    <div class="sffc-toolkit__compare-card sffc-toolkit__compare-card--before">
                        <div class="sffc-toolkit__compare-label">
                            <span class="sffc-toolkit__compare-dot sffc-toolkit__compare-dot--red"></span>
                            Without Toolkit
                        </div>
                        <div class="sffc-toolkit__compare-score">
                            <div class="sffc-toolkit__score-circle sffc-toolkit__score-circle--low">
                                <span><?php echo esc_html($base_score); ?>%</span>
                            </div>
                            <span class="sffc-toolkit__score-label">Match Score</span>
                        </div>
                        <ul class="sffc-toolkit__compare-list">
                            <li class="sffc-toolkit__compare-item sffc-toolkit__compare-item--negative">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                Generic cover letter
                            </li>
                            <li class="sffc-toolkit__compare-item sffc-toolkit__compare-item--negative">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                Missing ATS keywords
                            </li>
                            <li class="sffc-toolkit__compare-item sffc-toolkit__compare-item--negative">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                ~15% response rate
                            </li>
                        </ul>
                    </div>

                    <div class="sffc-toolkit__compare-arrow">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </div>

                    <div class="sffc-toolkit__compare-card sffc-toolkit__compare-card--after">
                        <div class="sffc-toolkit__compare-label">
                            <span class="sffc-toolkit__compare-dot sffc-toolkit__compare-dot--green"></span>
                            With Toolkit
                        </div>
                        <div class="sffc-toolkit__compare-score">
                            <div class="sffc-toolkit__score-circle sffc-toolkit__score-circle--high">
                                <span><?php echo esc_html($improved_score); ?>%</span>
                            </div>
                            <span class="sffc-toolkit__score-label">Match Score</span>
                        </div>
                        <ul class="sffc-toolkit__compare-list">
                            <li class="sffc-toolkit__compare-item sffc-toolkit__compare-item--positive">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
                                Tailored cover letter
                            </li>
                            <li class="sffc-toolkit__compare-item sffc-toolkit__compare-item--positive">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
                                ATS-optimized CV
                            </li>
                            <li class="sffc-toolkit__compare-item sffc-toolkit__compare-item--positive">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
                                ~47% response rate
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Section Title for Documents -->
                <div class="sffc-toolkit__section-header">
                    <h3 class="sffc-toolkit__section-title-v2">What's In Your Toolkit</h3>
                </div>

                <!-- Initial Document Previews (3) -->
                <div class="sffc-toolkit__documents">
                    <?php
                    foreach ($initial_docs as $doc_id => $doc) {
                        echo $this->render_document_card($doc_id, $doc, array(
                            'job_id' => $job_id,
                            'job_title' => $job_title,
                            'company' => $company,
                            'industry' => $industry,
                            'location' => $location,
                            'user_name' => $user_name,
                            'user_tier' => $user_tier_info['id'],
                            'is_logged_in' => $is_logged_in,
                            'enhanced_summary' => $enhanced_summary,
                            'meta' => $meta,
                        ));
                    }
                    ?>
                </div>

                <?php if (!empty($hidden_docs)): ?>
                <!-- Show More Button -->
                <div class="sffc-toolkit__expand">
                    <button class="sffc-toolkit__expand-btn" data-action="show-more">
                        <span>Show <?php echo count($hidden_docs); ?> more document<?php echo count($hidden_docs) > 1 ? 's' : ''; ?></span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </div>

                <!-- Hidden Documents -->
                <div class="sffc-toolkit__documents sffc-toolkit__documents--hidden">
                    <?php
                    foreach ($hidden_docs as $doc_id => $doc) {
                        echo $this->render_document_card($doc_id, $doc, array(
                            'job_id' => $job_id,
                            'job_title' => $job_title,
                            'company' => $company,
                            'industry' => $industry,
                            'location' => $location,
                            'user_name' => $user_name,
                            'user_tier' => $user_tier_info['id'],
                            'is_logged_in' => $is_logged_in,
                            'enhanced_summary' => $enhanced_summary,
                            'meta' => $meta,
                        ));
                    }
                    ?>
                </div>
                <?php endif; ?>

                <?php if (!$is_logged_in || $user_tier_info['id'] === 'basic'): ?>
                <!-- Upgrade Prompt -->
                <div class="sffc-toolkit__upgrade">
                    <div class="sffc-toolkit__upgrade-content">
                        <?php if (!$is_logged_in): ?>
                        <p><strong>Sign in</strong> to access the Application Toolkit</p>
                        <a href="<?php echo esc_url(wp_login_url(get_permalink($job_id))); ?>" class="sffc-toolkit__upgrade-btn">Sign In</a>
                        <?php else: ?>
                        <p>Upgrade to <strong>Pro</strong> or <strong>Advanced</strong> for full access</p>
                        <a href="<?php echo esc_url($this->tiers->get_upgrade_url()); ?>" class="sffc-toolkit__upgrade-btn">View Plans</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a single document card
     */
    private function render_document_card($doc_id, $doc, $context) {
        $required_tier = $this->tiers->get_required_tier($doc_id);
        $has_access = $context['is_logged_in'] && $this->tiers->user_has_access($doc_id);
        $is_featured = !empty($doc['featured']);

        $card_classes = array('sffc-toolkit__doc');
        if ($is_featured) {
            $card_classes[] = 'sffc-toolkit__doc--featured';
        }
        if (!$has_access) {
            $card_classes[] = 'sffc-toolkit__doc--locked';
        }

        ob_start();
        ?>
        <article class="<?php echo esc_attr(implode(' ', $card_classes)); ?>"
                 data-doc-id="<?php echo esc_attr($doc_id); ?>"
                 data-has-access="<?php echo $has_access ? 'true' : 'false'; ?>"
                 data-required-tier="<?php echo esc_html(ucfirst($required_tier)); ?>"
                 data-upgrade-url="<?php echo esc_url($this->tiers->get_upgrade_url()); ?>">

            <?php if ($is_featured): ?>
            <div class="sffc-toolkit__doc-featured-badge">Best Value</div>
            <?php endif; ?>

            <div class="sffc-toolkit__doc-preview">
                <?php echo $this->render_mock_preview($doc_id, $context); ?>
            </div>

            <div class="sffc-toolkit__doc-info">
                <h4><?php echo esc_html($doc['name']); ?></h4>
                <p><?php echo esc_html($doc['description']); ?></p>
                <div class="sffc-toolkit__doc-meta">
                    <?php foreach ($doc['formats'] as $format): ?>
                    <span class="sffc-toolkit__format <?php echo $format === 'text' ? 'sffc-toolkit__format--free' : ''; ?>">
                        <?php echo $format === 'text' ? 'Copy & Paste' : strtoupper($format); ?>
                    </span>
                    <?php endforeach; ?>
                    <span class="sffc-toolkit__tier-tag" data-tier="<?php echo esc_attr($required_tier); ?>">
                        <?php echo esc_html(ucfirst($required_tier)); ?>
                    </span>
                </div>
            </div>

            <button class="sffc-toolkit__doc-action <?php echo $is_featured ? 'sffc-toolkit__doc-action--primary' : ''; ?>"
                    data-action="generate"
                    data-doc-type="<?php echo esc_attr($doc_id); ?>"
                    data-job-id="<?php echo esc_attr($context['job_id']); ?>"
                    <?php echo !$has_access ? 'data-locked="true"' : ''; ?>>
                <?php if ($has_access): ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                <?php echo $is_featured ? 'Generate All' : 'Generate'; ?>
                <?php else: ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0110 0v4"/>
                </svg>
                Upgrade
                <?php endif; ?>
            </button>
        </article>
        <?php
        return ob_get_clean();
    }

    /**
     * Render mock preview for a document type
     * Uses actual job data for personalized, meaningful previews
     */
    private function render_mock_preview($doc_id, $context) {
        $job_title = $context['job_title'];
        $company = $context['company'];
        $user_name = trim((string) ($context['user_name'] ?? ''));
        if ($user_name === '') {
            $current_user = wp_get_current_user();
            if ($current_user instanceof WP_User && $current_user->exists()) {
                $user_name = trim((string) $current_user->first_name);
                if ($user_name === '') {
                    $name_parts = preg_split('/\s+/', trim((string) $current_user->display_name));
                    $user_name = trim((string) ($name_parts[0] ?? ''));
                }
            }
        } else {
            $name_parts = preg_split('/\s+/', $user_name);
            $user_name = trim((string) ($name_parts[0] ?? $user_name));
        }
        if ($user_name === '') {
            $user_name = 'Candidate';
        }
        $company_initial = strtoupper(substr($company, 0, 1));
        $today = date('M j, Y');
        $enhanced = $context['enhanced_summary'] ?? array();
        $meta = $context['meta'] ?? array();

        // Extract rich data
        $key_skills = $enhanced['key_skills'] ?? array();
        $interview_battlecard = $enhanced['interview_battlecard'] ?? array();
        $role_reality = $enhanced['role_reality'] ?? array();
        $salary_range = $meta['sffc_salary_range'] ?? '';
        $experience_level = $meta['sffc_experience_level'] ?? '';
        $company_size = $meta['sffc_company_size'] ?? '';
        $location = $context['location'] ?? '';

        ob_start();

        switch ($doc_id) {
            case 'cv':
                // Show actual skills that will be highlighted
                $top_skills = array_slice($key_skills, 0, 3);
                ?>
                <div class="sffc-toolkit__mock sffc-toolkit__mock--cv">
                    <div class="sffc-toolkit__mock-header">
                        <div class="sffc-toolkit__mock-name"><?php echo esc_html($user_name); ?></div>
                        <div class="sffc-toolkit__mock-title"><?php echo esc_html($this->truncate($job_title, 25)); ?></div>
                    </div>
                    <?php if (!empty($top_skills)): ?>
                    <div class="sffc-toolkit__mock-skills">
                        <?php foreach ($top_skills as $skill): ?>
                        <span class="sffc-toolkit__mock-skill"><?php echo esc_html($this->truncate($skill, 12)); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="sffc-toolkit__mock-section">
                        <div class="sffc-toolkit__mock-heading"></div>
                        <div class="sffc-toolkit__mock-line"></div>
                    </div>
                    <?php endif; ?>
                    <div class="sffc-toolkit__mock-highlight">
                        <span>Optimized for <?php echo esc_html($this->truncate($company, 15)); ?></span>
                    </div>
                </div>
                <?php
                break;

            case 'cover_letter':
                // Show opening line preview
                $opening = "I am writing to express my strong interest in the {$this->truncate($job_title, 20)} position";
                ?>
                <div class="sffc-toolkit__mock sffc-toolkit__mock--letter">
                    <div class="sffc-toolkit__mock-date"><?php echo esc_html($today); ?></div>
                    <div class="sffc-toolkit__mock-salutation">Dear Hiring Manager,</div>
                    <div class="sffc-toolkit__mock-body">
                        <div class="sffc-toolkit__mock-text"><?php echo esc_html($this->truncate($opening, 45)); ?>...</div>
                        <div class="sffc-toolkit__mock-highlight">
                            <span><?php echo esc_html($company); ?></span>
                        </div>
                    </div>
                </div>
                <?php
                break;

            case 'interview_prep':
                // Show actual interview questions if available
                $questions = array();
                if (!empty($interview_battlecard['likely_questions'])) {
                    $questions = array_slice($interview_battlecard['likely_questions'], 0, 2);
                } elseif (!empty($interview_battlecard['technical_questions'])) {
                    $questions = array_slice($interview_battlecard['technical_questions'], 0, 2);
                }
                ?>
                <div class="sffc-toolkit__mock sffc-toolkit__mock--prep">
                    <div class="sffc-toolkit__mock-prep-header">
                        <?php echo esc_html($this->truncate($company, 18)); ?> Interview
                    </div>
                    <?php if (!empty($questions)): ?>
                    <div class="sffc-toolkit__mock-prep-section">
                        <?php foreach ($questions as $q):
                            $question_text = is_array($q) ? ($q['question'] ?? $q[0] ?? '') : $q;
                        ?>
                        <div class="sffc-toolkit__mock-prep-item">
                            <span class="sffc-toolkit__mock-bullet">Q</span>
                            <span class="sffc-toolkit__mock-q-text"><?php echo esc_html($this->truncate($question_text, 28)); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="sffc-toolkit__mock-prep-section">
                        <div class="sffc-toolkit__mock-prep-item">
                            <span class="sffc-toolkit__mock-bullet">Q</span>
                            <span class="sffc-toolkit__mock-q-text">Why <?php echo esc_html($this->truncate($company, 12)); ?>?</span>
                        </div>
                        <div class="sffc-toolkit__mock-prep-item">
                            <span class="sffc-toolkit__mock-bullet">Q</span>
                            <span class="sffc-toolkit__mock-q-text">Role-specific questions...</span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="sffc-toolkit__mock-prep-count">+ answers & talking points</div>
                </div>
                <?php
                break;

            case 'company_brief':
                // Show actual company data
                $industry = $context['industry'] ?: 'Finance';
                $firm_type = $meta['sffc_firm_type'] ?? '';
                ?>
                <div class="sffc-toolkit__mock sffc-toolkit__mock--intel">
                    <div class="sffc-toolkit__mock-intel-header">
                        <div class="sffc-toolkit__mock-company-logo"><?php echo esc_html($company_initial); ?></div>
                        <div class="sffc-toolkit__mock-company-name"><?php echo esc_html($this->truncate($company, 15)); ?></div>
                    </div>
                    <div class="sffc-toolkit__mock-intel-grid">
                        <div class="sffc-toolkit__mock-intel-stat">
                            <span class="sffc-toolkit__mock-stat-label">Industry</span>
                            <span class="sffc-toolkit__mock-stat-value"><?php echo esc_html($this->truncate($industry, 10)); ?></span>
                        </div>
                        <div class="sffc-toolkit__mock-intel-stat">
                            <span class="sffc-toolkit__mock-stat-label"><?php echo $firm_type ? 'Type' : 'Location'; ?></span>
                            <span class="sffc-toolkit__mock-stat-value"><?php echo esc_html($this->truncate($firm_type ?: $location ?: '—', 10)); ?></span>
                        </div>
                    </div>
                    <div class="sffc-toolkit__mock-intel-more">Culture • Competitors • Interview Intel</div>
                </div>
                <?php
                break;

            case 'networking':
                // Show personalized message preview
                ?>
                <div class="sffc-toolkit__mock sffc-toolkit__mock--message">
                    <div class="sffc-toolkit__mock-msg-tabs">
                        <span class="sffc-toolkit__mock-msg-tab active">LinkedIn</span>
                        <span class="sffc-toolkit__mock-msg-tab">Email</span>
                        <span class="sffc-toolkit__mock-msg-tab">Referral</span>
                    </div>
                    <div class="sffc-toolkit__mock-msg-body">
                        <div class="sffc-toolkit__mock-msg-subject">Re: <?php echo esc_html($this->truncate($job_title, 20)); ?></div>
                        <div class="sffc-toolkit__mock-msg-preview">Hi, I noticed <?php echo esc_html($company); ?> is hiring...</div>
                    </div>
                    <div class="sffc-toolkit__mock-msg-count">5 templates included</div>
                </div>
                <?php
                break;

            case 'full_pack':
                // Show what's included with job context
                ?>
                <div class="sffc-toolkit__mock sffc-toolkit__mock--pack">
                    <div class="sffc-toolkit__mock-pack-title">Complete Pack for</div>
                    <div class="sffc-toolkit__mock-pack-role"><?php echo esc_html($this->truncate($job_title, 22)); ?></div>
                    <div class="sffc-toolkit__mock-pack-items">
                        <span>CV</span>
                        <span>Cover Letter</span>
                        <span>Interview Prep</span>
                        <span>+3 more</span>
                    </div>
                    <div class="sffc-toolkit__mock-pack-label">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="12" height="12">
                            <path d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
                        </svg>
                        <span>ZIP Download</span>
                    </div>
                </div>
                <?php
                break;

            default:
                ?>
                <div class="sffc-toolkit__mock">
                    <div class="sffc-toolkit__mock-line"></div>
                    <div class="sffc-toolkit__mock-line"></div>
                    <div class="sffc-toolkit__mock-line sffc-toolkit__mock-line--short"></div>
                </div>
                <?php
                break;
        }

        return ob_get_clean();
    }

    /**
     * Truncate text to a maximum length
     */
    private function truncate($text, $max_length) {
        // Handle array input (e.g., skill arrays with 'name' key)
        if (is_array($text)) {
            $text = $text['name'] ?? $text['skill'] ?? (string) reset($text);
        }

        // Ensure we have a string
        if (!is_string($text)) {
            $text = (string) $text;
        }

        if (strlen($text) <= $max_length) {
            return $text;
        }
        return substr($text, 0, $max_length - 3) . '...';
    }

    /**
     * Enqueue toolkit styles (uses main application-pack.css)
     */
    public static function enqueue_styles() {
        // Styles are included in application-pack.css
        // This method is kept for backwards compatibility
    }

    /**
     * Enqueue toolkit scripts (uses main application-pack.js)
     */
    public static function enqueue_scripts() {
        // Scripts are included in application-pack.js
        // This method is kept for backwards compatibility
    }
}

// Initialize
SFFC_Application_Pack_Toolkit::get_instance();
