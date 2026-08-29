<?php
/**
 * Recruiter Terminal Admin
 *
 * Admin page for reviewing and approving role briefs (v2.0) and legacy campaigns.
 *
 * @package SennaFinanceCareer
 * @subpackage RecruiterTerminal
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Recruiter_Terminal_Admin {
    private static $table_exists_cache = array();


    /**
     * Initialize admin
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_menu_page'), 20); // Priority 20 to load after main menu
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
    }

    /**
     * Add admin menu pages
     */
    public static function add_menu_page() {
        // v2.0 Brief Review (primary)
        $brief_pending_count = self::get_pending_brief_count();
        $brief_menu_title = __('Brief Review', 'senna-finance');

        if ($brief_pending_count > 0) {
            $brief_menu_title .= sprintf(
                ' <span class="awaiting-mod">%d</span>',
                $brief_pending_count
            );
        }

        add_submenu_page(
            'sffc-dashboard',
            __('Recruiter Brief Review', 'senna-finance'),
            $brief_menu_title,
            'manage_options',
            'recruiter-brief-review',
            array(__CLASS__, 'render_brief_page')
        );

        // Legacy Campaign Review
        $campaign_pending_count = self::get_pending_campaign_count();
        if ($campaign_pending_count > 0) {
            $campaign_menu_title = __('Campaign Review', 'senna-finance');
            $campaign_menu_title .= sprintf(
                ' <span class="awaiting-mod">%d</span>',
                $campaign_pending_count
            );

            add_submenu_page(
                'sffc-dashboard',
                __('Recruiter Campaign Review', 'senna-finance'),
                $campaign_menu_title,
                'manage_options',
                'recruiter-campaign-review',
                array(__CLASS__, 'render_page')
            );
        }

        // External Recruiters Management
        add_submenu_page(
            'sffc-dashboard',
            __('External Recruiters', 'senna-finance'),
            __('External Recruiters', 'senna-finance'),
            'manage_options',
            'external-recruiters',
            array(__CLASS__, 'render_external_recruiters_page')
        );
    }

    /**
     * Get count of pending briefs (v2.0)
     */
    private static function get_pending_brief_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'rt_briefs';

        $cached = get_transient('sffc_rt_pending_brief_count');
        if (false !== $cached) {
            return (int) $cached;
        }

        if (!self::table_exists($table)) {
            return 0;
        }

        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE status = 'pending_review'"
        );

        set_transient('sffc_rt_pending_brief_count', $count, 5 * MINUTE_IN_SECONDS);

        return $count;
    }

    /**
     * Get count of pending campaigns (legacy)
     */
    private static function get_pending_campaign_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'rt_campaigns';

        $cached = get_transient('sffc_rt_pending_campaign_count');
        if (false !== $cached) {
            return (int) $cached;
        }

        if (!self::table_exists($table)) {
            return 0;
        }

        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE status = 'pending_review'"
        );

        set_transient('sffc_rt_pending_campaign_count', $count, 5 * MINUTE_IN_SECONDS);

        return $count;
    }

    private static function table_exists($table) {
        global $wpdb;

        if (array_key_exists($table, self::$table_exists_cache)) {
            return self::$table_exists_cache[$table];
        }

        $cache_key = 'sffc_rt_table_exists_' . md5($table);
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            $exists = ($cached === '1');
            self::$table_exists_cache[$table] = $exists;
            return $exists;
        }

        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
        self::$table_exists_cache[$table] = $exists;

        set_transient($cache_key, $exists ? '1' : '0', DAY_IN_SECONDS);

        return $exists;
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_assets($hook) {
        // Handle both brief and campaign review pages
        $is_brief_review = strpos($hook, 'recruiter-brief-review') !== false;
        $is_campaign_review = strpos($hook, 'recruiter-campaign-review') !== false;

        if (!$is_brief_review && !$is_campaign_review) {
            return;
        }

        wp_enqueue_style(
            'rt-admin',
            SFFC_PLUGIN_URL . 'admin/css/recruiter-terminal-admin.css',
            array(),
            '2.0.0'
        );

        wp_enqueue_script(
            'rt-admin',
            SFFC_PLUGIN_URL . 'admin/js/recruiter-terminal-admin.js',
            array('jquery'),
            '2.0.0',
            true
        );

        $strings = array(
            'approving'       => __('Approving...', 'senna-finance'),
            'rejecting'       => __('Rejecting...', 'senna-finance'),
            'approved'        => __('Approved successfully', 'senna-finance'),
            'rejected'        => __('Rejected', 'senna-finance'),
            'confirmApprove'  => $is_brief_review
                ? __('Approve this brief? It will become active and visible to candidates.', 'senna-finance')
                : __('Approve this campaign? Emails will be sent according to the schedule.', 'senna-finance'),
            'confirmReject'   => __('Reject? Please provide a reason.', 'senna-finance'),
            'errorGeneric'    => __('An error occurred. Please try again.', 'senna-finance'),
            'copySuccess'     => __('Link copied to clipboard!', 'senna-finance'),
        );

        wp_localize_script('rt-admin', 'rtAdmin', array(
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('recruiter_terminal_nonce'),
            'mode'     => $is_brief_review ? 'brief' : 'campaign',
            'strings'  => $strings,
        ));
    }

    /**
     * Render admin page
     */
    public static function render_page() {
        // Get current tab
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'pending';

        // Get campaigns based on tab
        $campaigns = self::get_campaigns_for_tab($current_tab);

        // Get counts for tabs
        $counts = self::get_tab_counts();

        ?>
        <div class="wrap rt-admin-wrap">
            <h1 class="wp-heading-inline">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="28" height="28" style="vertical-align: middle; margin-right: 8px;">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="8.5" cy="7" r="4"/>
                    <line x1="20" y1="8" x2="20" y2="14"/>
                    <line x1="23" y1="11" x2="17" y2="11"/>
                </svg>
                <?php esc_html_e('Campaign Review', 'senna-finance'); ?>
            </h1>

            <nav class="nav-tab-wrapper wp-clearfix">
                <a href="<?php echo esc_url(add_query_arg('tab', 'pending')); ?>"
                   class="nav-tab <?php echo $current_tab === 'pending' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Pending Review', 'senna-finance'); ?>
                    <?php if ($counts['pending'] > 0) : ?>
                        <span class="count">(<?php echo esc_html($counts['pending']); ?>)</span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'approved')); ?>"
                   class="nav-tab <?php echo $current_tab === 'approved' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Approved', 'senna-finance'); ?>
                    <span class="count">(<?php echo esc_html($counts['approved']); ?>)</span>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'rejected')); ?>"
                   class="nav-tab <?php echo $current_tab === 'rejected' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Rejected', 'senna-finance'); ?>
                    <span class="count">(<?php echo esc_html($counts['rejected']); ?>)</span>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'all')); ?>"
                   class="nav-tab <?php echo $current_tab === 'all' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('All Campaigns', 'senna-finance'); ?>
                    <span class="count">(<?php echo esc_html($counts['all']); ?>)</span>
                </a>
            </nav>

            <div class="rt-admin-content">
                <?php if (empty($campaigns)) : ?>
                    <div class="rt-admin-empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        <h3><?php esc_html_e('No campaigns found', 'senna-finance'); ?></h3>
                        <p>
                            <?php
                            switch ($current_tab) {
                                case 'pending':
                                    esc_html_e('No campaigns are waiting for review.', 'senna-finance');
                                    break;
                                case 'approved':
                                    esc_html_e('No campaigns have been approved yet.', 'senna-finance');
                                    break;
                                case 'rejected':
                                    esc_html_e('No campaigns have been rejected.', 'senna-finance');
                                    break;
                                default:
                                    esc_html_e('No campaigns have been created yet.', 'senna-finance');
                            }
                            ?>
                        </p>
                    </div>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped rt-campaigns-table">
                        <thead>
                            <tr>
                                <th class="column-title"><?php esc_html_e('Campaign', 'senna-finance'); ?></th>
                                <th class="column-recruiter"><?php esc_html_e('Recruiter', 'senna-finance'); ?></th>
                                <th class="column-targets"><?php esc_html_e('Targets', 'senna-finance'); ?></th>
                                <th class="column-schedule"><?php esc_html_e('Schedule', 'senna-finance'); ?></th>
                                <th class="column-status"><?php esc_html_e('Status', 'senna-finance'); ?></th>
                                <th class="column-date"><?php esc_html_e('Submitted', 'senna-finance'); ?></th>
                                <th class="column-actions"><?php esc_html_e('Actions', 'senna-finance'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($campaigns as $campaign) : ?>
                                <?php self::render_campaign_row($campaign); ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Campaign Preview Modal -->
        <div id="rt-preview-modal" class="rt-modal" style="display: none;">
            <div class="rt-modal__backdrop"></div>
            <div class="rt-modal__dialog rt-modal__dialog--large">
                <div class="rt-modal__header">
                    <h2><?php esc_html_e('Campaign Details', 'senna-finance'); ?></h2>
                    <button type="button" class="rt-modal__close" data-action="close-modal">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="rt-modal__body" id="rt-preview-content">
                    <!-- Content loaded via AJAX -->
                </div>
                <div class="rt-modal__footer" id="rt-preview-footer">
                    <!-- Actions loaded based on status -->
                </div>
            </div>
        </div>

        <!-- Rejection Reason Modal -->
        <div id="rt-reject-modal" class="rt-modal" style="display: none;">
            <div class="rt-modal__backdrop"></div>
            <div class="rt-modal__dialog">
                <div class="rt-modal__header">
                    <h2><?php esc_html_e('Reject Campaign', 'senna-finance'); ?></h2>
                    <button type="button" class="rt-modal__close" data-action="close-modal">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="rt-modal__body">
                    <p><?php esc_html_e('Please provide a reason for rejecting this campaign. This will be shared with the recruiter.', 'senna-finance'); ?></p>
                    <textarea id="rt-reject-reason" rows="4" class="large-text" placeholder="<?php esc_attr_e('Enter rejection reason...', 'senna-finance'); ?>"></textarea>
                    <input type="hidden" id="rt-reject-campaign-id" value="">
                </div>
                <div class="rt-modal__footer">
                    <button type="button" class="button" data-action="close-modal"><?php esc_html_e('Cancel', 'senna-finance'); ?></button>
                    <button type="button" class="button button-primary" id="rt-confirm-reject"><?php esc_html_e('Reject Campaign', 'senna-finance'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render a campaign row
     */
    private static function render_campaign_row($campaign) {
        $recruiter = get_userdata($campaign->user_id);
        $stats = Recruiter_Terminal_DB::get_campaign_stats($campaign->id);

        $status_labels = array(
            'draft'          => __('Draft', 'senna-finance'),
            'pending_review' => __('Pending Review', 'senna-finance'),
            'approved'       => __('Approved', 'senna-finance'),
            'active'         => __('Active', 'senna-finance'),
            'paused'         => __('Paused', 'senna-finance'),
            'completed'      => __('Completed', 'senna-finance'),
            'rejected'       => __('Rejected', 'senna-finance'),
        );

        $status_class = 'rt-status--' . str_replace('_', '-', $campaign->status);
        ?>
        <tr data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
            <td class="column-title">
                <strong>
                    <a href="#" class="row-title" data-action="preview" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
                        <?php echo esc_html($campaign->title); ?>
                    </a>
                </strong>
                <?php if (!empty($campaign->brief)) : ?>
                    <p class="description"><?php echo esc_html(wp_trim_words($campaign->brief, 15)); ?></p>
                <?php endif; ?>
            </td>
            <td class="column-recruiter">
                <?php if ($recruiter) : ?>
                    <div class="rt-recruiter">
                        <?php echo get_avatar($recruiter->ID, 32); ?>
                        <span><?php echo esc_html($recruiter->display_name); ?></span>
                    </div>
                <?php else : ?>
                    <em><?php esc_html_e('Unknown', 'senna-finance'); ?></em>
                <?php endif; ?>
            </td>
            <td class="column-targets">
                <span class="rt-target-count"><?php echo esc_html($stats->total ?? 0); ?></span>
                <?php esc_html_e('candidates', 'senna-finance'); ?>
            </td>
            <td class="column-schedule">
                <?php if (!empty($campaign->scheduled_at)) : ?>
                    <span class="rt-schedule">
                        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($campaign->scheduled_at))); ?>
                    </span>
                <?php else : ?>
                    <span class="rt-schedule rt-schedule--immediate"><?php esc_html_e('Immediate', 'senna-finance'); ?></span>
                <?php endif; ?>
            </td>
            <td class="column-status">
                <span class="rt-status <?php echo esc_attr($status_class); ?>">
                    <?php echo esc_html($status_labels[$campaign->status] ?? $campaign->status); ?>
                </span>
            </td>
            <td class="column-date">
                <?php
                $date = !empty($campaign->submitted_at) ? $campaign->submitted_at : $campaign->created_at;
                echo esc_html(human_time_diff(strtotime($date), current_time('timestamp'))) . ' ' . esc_html__('ago', 'senna-finance');
                ?>
            </td>
            <td class="column-actions">
                <div class="rt-actions">
                    <button type="button" class="button button-small" data-action="preview" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php esc_html_e('View', 'senna-finance'); ?>
                    </button>
                    <?php if ($campaign->status === 'pending_review') : ?>
                        <button type="button" class="button button-small button-primary" data-action="approve" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Approve', 'senna-finance'); ?>
                        </button>
                        <button type="button" class="button button-small" data-action="reject" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
                            <span class="dashicons dashicons-no"></span>
                            <?php esc_html_e('Reject', 'senna-finance'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php
    }

    /**
     * Get campaigns for current tab
     */
    private static function get_campaigns_for_tab($tab) {
        global $wpdb;
        $table = $wpdb->prefix . 'rt_campaigns';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return array();
        }

        $where = '';
        switch ($tab) {
            case 'pending':
                $where = "WHERE status = 'pending_review'";
                break;
            case 'approved':
                $where = "WHERE status IN ('approved', 'active', 'completed')";
                break;
            case 'rejected':
                $where = "WHERE status = 'rejected'";
                break;
            default:
                // All campaigns except draft
                $where = "WHERE status != 'draft'";
        }

        return $wpdb->get_results(
            "SELECT * FROM $table $where ORDER BY
                CASE status
                    WHEN 'pending_review' THEN 1
                    WHEN 'active' THEN 2
                    ELSE 3
                END,
                created_at DESC
            LIMIT 100"
        );
    }

    /**
     * Get counts for tabs
     */
    private static function get_tab_counts() {
        global $wpdb;
        $table = $wpdb->prefix . 'rt_campaigns';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return array(
                'pending'  => 0,
                'approved' => 0,
                'rejected' => 0,
                'all'      => 0,
            );
        }

        return array(
            'pending'  => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending_review'"),
            'approved' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status IN ('approved', 'active', 'completed')"),
            'rejected' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'rejected'"),
            'all'      => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status != 'draft'"),
        );
    }

    /**
     * Get campaign preview HTML (called via AJAX)
     */
    public static function get_campaign_preview($campaign_id) {
        $campaign = Recruiter_Terminal_DB::get_campaign($campaign_id);

        if (!$campaign) {
            return '<p>' . esc_html__('Campaign not found.', 'senna-finance') . '</p>';
        }

        $recruiter = get_userdata($campaign->user_id);
        $targets = Recruiter_Terminal_DB::get_campaign_targets($campaign_id);
        $stats = Recruiter_Terminal_DB::get_campaign_stats($campaign_id);
        $preview = Recruiter_Terminal_Email::get_preview($campaign);

        ob_start();
        ?>
        <div class="rt-preview">
            <div class="rt-preview__section">
                <h3><?php esc_html_e('Campaign Brief', 'senna-finance'); ?></h3>
                <div class="rt-preview__brief">
                    <?php echo nl2br(esc_html($campaign->brief)); ?>
                </div>
            </div>

            <div class="rt-preview__section">
                <h3><?php esc_html_e('Recruiter', 'senna-finance'); ?></h3>
                <div class="rt-preview__recruiter">
                    <?php if ($recruiter) : ?>
                        <?php echo get_avatar($recruiter->ID, 48); ?>
                        <div>
                            <strong><?php echo esc_html($recruiter->display_name); ?></strong>
                            <br>
                            <a href="mailto:<?php echo esc_attr($recruiter->user_email); ?>"><?php echo esc_html($recruiter->user_email); ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="rt-preview__section">
                <h3><?php esc_html_e('Target Candidates', 'senna-finance'); ?></h3>
                <p><?php printf(esc_html__('%d candidates selected', 'senna-finance'), count($targets)); ?></p>
                <?php if (!empty($targets)) : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Name', 'senna-finance'); ?></th>
                                <th><?php esc_html_e('Title', 'senna-finance'); ?></th>
                                <th><?php esc_html_e('Company', 'senna-finance'); ?></th>
                                <th><?php esc_html_e('Email', 'senna-finance'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($targets, 0, 10) as $target) : ?>
                                <tr>
                                    <td><?php echo esc_html($target->candidate_name); ?></td>
                                    <td><?php echo esc_html($target->candidate_title); ?></td>
                                    <td><?php echo esc_html($target->candidate_company); ?></td>
                                    <td><?php echo esc_html($target->candidate_email); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($targets) > 10) : ?>
                                <tr>
                                    <td colspan="4" class="rt-preview__more">
                                        <?php printf(esc_html__('...and %d more candidates', 'senna-finance'), count($targets) - 10); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="rt-preview__section">
                <h3><?php esc_html_e('Email Preview', 'senna-finance'); ?></h3>
                <div class="rt-preview__email">
                    <div class="rt-preview__email-header">
                        <strong><?php esc_html_e('Subject:', 'senna-finance'); ?></strong>
                        <?php echo esc_html($preview['subject']); ?>
                    </div>
                    <div class="rt-preview__email-body">
                        <?php echo nl2br(esc_html($preview['body'])); ?>
                    </div>
                </div>
            </div>

            <div class="rt-preview__section">
                <h3><?php esc_html_e('Schedule', 'senna-finance'); ?></h3>
                <?php if (!empty($campaign->scheduled_at)) : ?>
                    <p><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($campaign->scheduled_at))); ?></p>
                <?php else : ?>
                    <p><?php esc_html_e('Send immediately after approval', 'senna-finance'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // =========================================================================
    // BRIEF REVIEW (v2.0)
    // =========================================================================

    /**
     * Render brief review page (v2.0)
     */
    public static function render_brief_page() {
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'pending';
        $briefs = self::get_briefs_for_tab($current_tab);
        $counts = self::get_brief_tab_counts();

        // Get opportunity page URL for generating links
        $opportunity_page_id = get_option('rt_opportunity_page_id', 0);
        $opportunity_base_url = $opportunity_page_id ? get_permalink($opportunity_page_id) : home_url('/opportunity/');
        ?>
        <div class="wrap rt-admin-wrap rt-admin-wrap--v2">
            <h1 class="wp-heading-inline">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="28" height="28" style="vertical-align: middle; margin-right: 8px;">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                </svg>
                <?php esc_html_e('Role Brief Review', 'senna-finance'); ?>
            </h1>

            <p class="rt-admin-description">
                <?php esc_html_e('Review and approve briefs submitted by recruiters. Approved briefs can be shared with candidates.', 'senna-finance'); ?>
            </p>

            <nav class="nav-tab-wrapper wp-clearfix">
                <a href="<?php echo esc_url(add_query_arg('tab', 'pending', remove_query_arg('paged'))); ?>"
                   class="nav-tab <?php echo $current_tab === 'pending' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Pending Review', 'senna-finance'); ?>
                    <?php if ($counts['pending'] > 0) : ?>
                        <span class="count">(<?php echo esc_html($counts['pending']); ?>)</span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'active', remove_query_arg('paged'))); ?>"
                   class="nav-tab <?php echo $current_tab === 'active' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Active', 'senna-finance'); ?>
                    <span class="count">(<?php echo esc_html($counts['active']); ?>)</span>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'rejected', remove_query_arg('paged'))); ?>"
                   class="nav-tab <?php echo $current_tab === 'rejected' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Rejected', 'senna-finance'); ?>
                    <span class="count">(<?php echo esc_html($counts['rejected']); ?>)</span>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'all', remove_query_arg('paged'))); ?>"
                   class="nav-tab <?php echo $current_tab === 'all' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('All Briefs', 'senna-finance'); ?>
                    <span class="count">(<?php echo esc_html($counts['all']); ?>)</span>
                </a>
            </nav>

            <div class="rt-admin-content">
                <?php if (empty($briefs)) : ?>
                    <div class="rt-admin-empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        <h3><?php esc_html_e('No briefs found', 'senna-finance'); ?></h3>
                        <p>
                            <?php
                            switch ($current_tab) {
                                case 'pending':
                                    esc_html_e('No briefs are waiting for review.', 'senna-finance');
                                    break;
                                case 'active':
                                    esc_html_e('No briefs are currently active.', 'senna-finance');
                                    break;
                                case 'rejected':
                                    esc_html_e('No briefs have been rejected.', 'senna-finance');
                                    break;
                                default:
                                    esc_html_e('No briefs have been submitted yet.', 'senna-finance');
                            }
                            ?>
                        </p>
                    </div>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped rt-briefs-table">
                        <thead>
                            <tr>
                                <th class="column-title"><?php esc_html_e('Brief', 'senna-finance'); ?></th>
                                <th class="column-recruiter"><?php esc_html_e('Recruiter', 'senna-finance'); ?></th>
                                <th class="column-location"><?php esc_html_e('Location', 'senna-finance'); ?></th>
                                <th class="column-sector"><?php esc_html_e('Sector', 'senna-finance'); ?></th>
                                <th class="column-responses"><?php esc_html_e('Responses', 'senna-finance'); ?></th>
                                <th class="column-status"><?php esc_html_e('Status', 'senna-finance'); ?></th>
                                <th class="column-date"><?php esc_html_e('Submitted', 'senna-finance'); ?></th>
                                <th class="column-actions"><?php esc_html_e('Actions', 'senna-finance'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($briefs as $brief) : ?>
                                <?php self::render_brief_row($brief, $opportunity_base_url); ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Brief Preview Modal -->
        <div id="rt-preview-modal" class="rt-modal" style="display: none;">
            <div class="rt-modal__backdrop"></div>
            <div class="rt-modal__dialog rt-modal__dialog--large">
                <div class="rt-modal__header">
                    <h2><?php esc_html_e('Brief Details', 'senna-finance'); ?></h2>
                    <button type="button" class="rt-modal__close" data-action="close-modal">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="rt-modal__body" id="rt-preview-content">
                    <!-- Content loaded via AJAX -->
                </div>
                <div class="rt-modal__footer" id="rt-preview-footer">
                    <!-- Actions loaded based on status -->
                </div>
            </div>
        </div>

        <!-- Rejection Reason Modal -->
        <div id="rt-reject-modal" class="rt-modal" style="display: none;">
            <div class="rt-modal__backdrop"></div>
            <div class="rt-modal__dialog">
                <div class="rt-modal__header">
                    <h2><?php esc_html_e('Reject Brief', 'senna-finance'); ?></h2>
                    <button type="button" class="rt-modal__close" data-action="close-modal">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="rt-modal__body">
                    <p><?php esc_html_e('Please provide a reason for rejecting this brief. This will be shared with the recruiter.', 'senna-finance'); ?></p>
                    <textarea id="rt-reject-reason" rows="4" class="large-text" placeholder="<?php esc_attr_e('Enter rejection reason...', 'senna-finance'); ?>"></textarea>
                    <input type="hidden" id="rt-reject-brief-id" value="">
                </div>
                <div class="rt-modal__footer">
                    <button type="button" class="button" data-action="close-modal"><?php esc_html_e('Cancel', 'senna-finance'); ?></button>
                    <button type="button" class="button button-primary" id="rt-confirm-reject"><?php esc_html_e('Reject Brief', 'senna-finance'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render a brief row
     */
    private static function render_brief_row($brief, $opportunity_base_url) {
        $recruiter = get_userdata($brief->user_id);
        $response_count = self::get_brief_response_count($brief->id);

        $status_labels = array(
            'draft'          => __('Draft', 'senna-finance'),
            'pending_review' => __('Pending Review', 'senna-finance'),
            'active'         => __('Active', 'senna-finance'),
            'closed'         => __('Closed', 'senna-finance'),
            'rejected'       => __('Rejected', 'senna-finance'),
        );

        $sector_labels = array(
            'asset_management' => __('Asset Management', 'senna-finance'),
            'banking'          => __('Banking', 'senna-finance'),
            'consultancy'      => __('Consultancy', 'senna-finance'),
            'fintech'          => __('Fintech', 'senna-finance'),
            'hedge_fund'       => __('Hedge Fund', 'senna-finance'),
            'insurance'        => __('Insurance', 'senna-finance'),
            'private_equity'   => __('Private Equity', 'senna-finance'),
            'venture_capital'  => __('Venture Capital', 'senna-finance'),
            'real_estate'      => __('Real Estate', 'senna-finance'),
            'other'            => __('Other', 'senna-finance'),
        );

        $status_class = 'rt-status--' . str_replace('_', '-', $brief->status);
        $opportunity_url = add_query_arg('b', $brief->id, $opportunity_base_url);
        ?>
        <tr data-brief-id="<?php echo esc_attr($brief->id); ?>">
            <td class="column-title">
                <strong>
                    <a href="#" class="row-title" data-action="preview" data-brief-id="<?php echo esc_attr($brief->id); ?>">
                        <?php echo esc_html($brief->title); ?>
                    </a>
                </strong>
                <?php if (!empty($brief->brief)) : ?>
                    <p class="description"><?php echo esc_html(wp_trim_words($brief->brief, 12)); ?></p>
                <?php endif; ?>
            </td>
            <td class="column-recruiter">
                <?php if ($recruiter) : ?>
                    <div class="rt-recruiter">
                        <?php echo get_avatar($recruiter->ID, 32); ?>
                        <span><?php echo esc_html($recruiter->display_name); ?></span>
                    </div>
                <?php else : ?>
                    <em><?php esc_html_e('Unknown', 'senna-finance'); ?></em>
                <?php endif; ?>
            </td>
            <td class="column-location">
                <?php echo !empty($brief->location) ? esc_html($brief->location) : '—'; ?>
            </td>
            <td class="column-sector">
                <?php
                $sector_display = isset($sector_labels[$brief->sector]) ? $sector_labels[$brief->sector] : $brief->sector;
                echo !empty($sector_display) ? esc_html($sector_display) : '—';
                ?>
            </td>
            <td class="column-responses">
                <span class="rt-response-count"><?php echo esc_html($response_count); ?></span>
            </td>
            <td class="column-status">
                <span class="rt-status <?php echo esc_attr($status_class); ?>">
                    <?php echo esc_html($status_labels[$brief->status] ?? $brief->status); ?>
                </span>
            </td>
            <td class="column-date">
                <?php
                $date = !empty($brief->submitted_at) ? $brief->submitted_at : $brief->created_at;
                echo esc_html(human_time_diff(strtotime($date), current_time('timestamp'))) . ' ' . esc_html__('ago', 'senna-finance');
                ?>
            </td>
            <td class="column-actions">
                <div class="rt-actions">
                    <button type="button" class="button button-small" data-action="preview" data-brief-id="<?php echo esc_attr($brief->id); ?>">
                        <span class="dashicons dashicons-visibility"></span>
                    </button>
                    <?php if ($brief->status === 'pending_review') : ?>
                        <button type="button" class="button button-small button-primary" data-action="approve" data-brief-id="<?php echo esc_attr($brief->id); ?>">
                            <span class="dashicons dashicons-yes"></span>
                        </button>
                        <button type="button" class="button button-small" data-action="reject" data-brief-id="<?php echo esc_attr($brief->id); ?>">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    <?php elseif ($brief->status === 'active') : ?>
                        <button type="button" class="button button-small" data-action="copy-link" data-url="<?php echo esc_url($opportunity_url); ?>" title="<?php esc_attr_e('Copy candidate link', 'senna-finance'); ?>">
                            <span class="dashicons dashicons-admin-links"></span>
                        </button>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php
    }

    /**
     * Get briefs for current tab
     */
    private static function get_briefs_for_tab($tab) {
        global $wpdb;
        $table = $wpdb->prefix . 'rt_briefs';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return array();
        }

        $where = '';
        switch ($tab) {
            case 'pending':
                $where = "WHERE status = 'pending_review'";
                break;
            case 'active':
                $where = "WHERE status = 'active'";
                break;
            case 'rejected':
                $where = "WHERE status = 'rejected'";
                break;
            default:
                // All briefs except draft
                $where = "WHERE status != 'draft'";
        }

        return $wpdb->get_results(
            "SELECT * FROM $table $where ORDER BY
                CASE status
                    WHEN 'pending_review' THEN 1
                    WHEN 'active' THEN 2
                    ELSE 3
                END,
                created_at DESC
            LIMIT 100"
        );
    }

    /**
     * Get counts for brief tabs
     */
    private static function get_brief_tab_counts() {
        global $wpdb;
        $table = $wpdb->prefix . 'rt_briefs';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return array(
                'pending'  => 0,
                'active'   => 0,
                'rejected' => 0,
                'all'      => 0,
            );
        }

        return array(
            'pending'  => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending_review'"),
            'active'   => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'active'"),
            'rejected' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'rejected'"),
            'all'      => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status != 'draft'"),
        );
    }

    /**
     * Get response count for a brief
     */
    private static function get_brief_response_count($brief_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rt_responses';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return 0;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE brief_id = %d",
            $brief_id
        ));
    }

    /**
     * Get brief preview HTML (called via AJAX)
     */
    public static function get_brief_preview($brief_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rt_briefs';
        $brief = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $brief_id));

        if (!$brief) {
            return '<p>' . esc_html__('Brief not found.', 'senna-finance') . '</p>';
        }

        $recruiter = get_userdata($brief->user_id);
        $recruiter_title = $recruiter ? get_user_meta($recruiter->ID, 'job_title', true) : '';
        $recruiter_company = $recruiter ? get_user_meta($recruiter->ID, 'company', true) : '';
        $response_count = self::get_brief_response_count($brief_id);

        // Parse criteria
        $criteria = array();
        if (!empty($brief->criteria)) {
            $criteria = json_decode($brief->criteria, true) ?: array();
        }

        ob_start();
        ?>
        <div class="rt-preview rt-preview--brief">
            <div class="rt-preview__section">
                <h3><?php esc_html_e('Brief Description', 'senna-finance'); ?></h3>
                <div class="rt-preview__brief">
                    <?php echo nl2br(esc_html($brief->brief)); ?>
                </div>
            </div>

            <div class="rt-preview__grid">
                <div class="rt-preview__section">
                    <h3><?php esc_html_e('Recruiter', 'senna-finance'); ?></h3>
                    <div class="rt-preview__recruiter">
                        <?php if ($recruiter) : ?>
                            <?php echo get_avatar($recruiter->ID, 48); ?>
                            <div>
                                <strong><?php echo esc_html($recruiter->display_name); ?></strong>
                                <?php if ($recruiter_title || $recruiter_company) : ?>
                                    <br>
                                    <span class="description">
                                        <?php echo esc_html($recruiter_title); ?>
                                        <?php if ($recruiter_title && $recruiter_company) echo ' @ '; ?>
                                        <?php echo esc_html($recruiter_company); ?>
                                    </span>
                                <?php endif; ?>
                                <br>
                                <a href="mailto:<?php echo esc_attr($recruiter->user_email); ?>"><?php echo esc_html($recruiter->user_email); ?></a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rt-preview__section">
                    <h3><?php esc_html_e('Role Details', 'senna-finance'); ?></h3>
                    <table class="rt-preview__details">
                        <tr>
                            <th><?php esc_html_e('Location', 'senna-finance'); ?></th>
                            <td><?php echo !empty($brief->location) ? esc_html($brief->location) : '—'; ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Sector', 'senna-finance'); ?></th>
                            <td><?php echo !empty($brief->sector) ? esc_html(ucwords(str_replace('_', ' ', $brief->sector))) : '—'; ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Salary Range', 'senna-finance'); ?></th>
                            <td><?php echo !empty($brief->salary_range) ? esc_html($brief->salary_range) : '—'; ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Responses', 'senna-finance'); ?></th>
                            <td><?php echo esc_html($response_count); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <?php if (!empty($criteria)) : ?>
            <div class="rt-preview__section">
                <h3><?php esc_html_e('Candidate Criteria', 'senna-finance'); ?></h3>
                <table class="rt-preview__details">
                    <?php if (!empty($criteria['experience_level'])) : ?>
                    <tr>
                        <th><?php esc_html_e('Experience Level', 'senna-finance'); ?></th>
                        <td><?php echo esc_html(ucwords(str_replace('_', ' ', $criteria['experience_level']))); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($criteria['target_locations'])) : ?>
                    <tr>
                        <th><?php esc_html_e('Target Locations', 'senna-finance'); ?></th>
                        <td><?php echo esc_html($criteria['target_locations']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($criteria['keywords'])) : ?>
                    <tr>
                        <th><?php esc_html_e('Keywords', 'senna-finance'); ?></th>
                        <td><?php echo esc_html($criteria['keywords']); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            <?php endif; ?>

            <?php if ($brief->status === 'rejected' && !empty($brief->rejection_reason)) : ?>
            <div class="rt-preview__section rt-preview__section--rejection">
                <h3><?php esc_html_e('Rejection Reason', 'senna-finance'); ?></h3>
                <div class="rt-preview__rejection">
                    <?php echo esc_html($brief->rejection_reason); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render External Recruiters admin page
     */
    public static function render_external_recruiters_page() {
        // Handle form submissions
        $message = '';
        $message_type = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rt_external_action'])) {
            check_admin_referer('rt_external_recruiter_action');

            $action = sanitize_key($_POST['rt_external_action']);

            if ($action === 'create' || $action === 'update') {
                $data = array(
                    'name'         => sanitize_text_field($_POST['name'] ?? ''),
                    'company'      => sanitize_text_field($_POST['company'] ?? ''),
                    'title'        => sanitize_text_field($_POST['title'] ?? ''),
                    'email'        => sanitize_email($_POST['email'] ?? ''),
                    'phone'        => sanitize_text_field($_POST['phone'] ?? ''),
                    'website'      => esc_url_raw($_POST['website'] ?? ''),
                    'location'     => sanitize_text_field($_POST['location'] ?? ''),
                    'photo_url'    => esc_url_raw($_POST['photo_url'] ?? ''),
                    'rating'       => floatval($_POST['rating'] ?? 0),
                    'review_count' => absint($_POST['review_count'] ?? 0),
                    'bio'          => sanitize_textarea_field($_POST['bio'] ?? ''),
                    'is_active'    => isset($_POST['is_active']) ? 1 : 0,
                );

                if (empty($data['name'])) {
                    $message = __('Name is required.', 'senna-finance');
                    $message_type = 'error';
                } else {
                    if ($action === 'create') {
                        $result = Recruiter_Terminal_DB::create_external_recruiter($data);
                        if ($result) {
                            $message = __('External recruiter created successfully.', 'senna-finance');
                            $message_type = 'success';
                        } else {
                            $message = __('Failed to create external recruiter.', 'senna-finance');
                            $message_type = 'error';
                        }
                    } else {
                        $recruiter_id = absint($_POST['recruiter_id'] ?? 0);
                        if ($recruiter_id) {
                            $result = Recruiter_Terminal_DB::update_external_recruiter($recruiter_id, $data);
                            if ($result) {
                                $message = __('External recruiter updated successfully.', 'senna-finance');
                                $message_type = 'success';
                            } else {
                                $message = __('Failed to update external recruiter.', 'senna-finance');
                                $message_type = 'error';
                            }
                        }
                    }
                }
            } elseif ($action === 'delete') {
                $recruiter_id = absint($_POST['recruiter_id'] ?? 0);
                if ($recruiter_id) {
                    $result = Recruiter_Terminal_DB::delete_external_recruiter($recruiter_id);
                    if ($result) {
                        $message = __('External recruiter deleted successfully.', 'senna-finance');
                        $message_type = 'success';
                    } else {
                        $message = __('Failed to delete external recruiter.', 'senna-finance');
                        $message_type = 'error';
                    }
                }
            }
        }

        // Get current view
        $view = isset($_GET['view']) ? sanitize_key($_GET['view']) : 'list';
        $edit_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
        $editing_recruiter = null;

        if ($edit_id > 0) {
            $editing_recruiter = Recruiter_Terminal_DB::get_external_recruiter($edit_id);
            $view = 'edit';
        }

        // Get all external recruiters
        $recruiters = Recruiter_Terminal_DB::get_all_external_recruiters();
        $total_count = Recruiter_Terminal_DB::count_external_recruiters();
        ?>
        <div class="wrap rt-admin-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('External Recruiters', 'senna-finance'); ?></h1>
            <a href="<?php echo esc_url(add_query_arg('view', 'add', admin_url('admin.php?page=external-recruiters'))); ?>" class="page-title-action">
                <?php esc_html_e('Add New', 'senna-finance'); ?>
            </a>
            <hr class="wp-header-end">

            <?php if ($message) : ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($view === 'add' || $view === 'edit') : ?>
                <?php self::render_external_recruiter_form($editing_recruiter); ?>
            <?php else : ?>
                <p class="description">
                    <?php esc_html_e('Manage external recruiters whose briefs appear in the NRT Matches tab. External recruiters are not platform users.', 'senna-finance'); ?>
                </p>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 60px;"><?php esc_html_e('Photo', 'senna-finance'); ?></th>
                            <th scope="col"><?php esc_html_e('Name', 'senna-finance'); ?></th>
                            <th scope="col"><?php esc_html_e('Company', 'senna-finance'); ?></th>
                            <th scope="col"><?php esc_html_e('Location', 'senna-finance'); ?></th>
                            <th scope="col" style="width: 80px;"><?php esc_html_e('Rating', 'senna-finance'); ?></th>
                            <th scope="col" style="width: 80px;"><?php esc_html_e('Status', 'senna-finance'); ?></th>
                            <th scope="col" style="width: 120px;"><?php esc_html_e('Actions', 'senna-finance'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recruiters)) : ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 20px;">
                                    <?php esc_html_e('No external recruiters found. Add your first external recruiter to get started.', 'senna-finance'); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($recruiters as $recruiter) : ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($recruiter->photo_url)) : ?>
                                            <img src="<?php echo esc_url($recruiter->photo_url); ?>" alt="" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                        <?php else : ?>
                                            <div style="width: 40px; height: 40px; border-radius: 50%; background: #e0e0e0; display: flex; align-items: center; justify-content: center; font-weight: 600; color: #666;">
                                                <?php echo esc_html(strtoupper(substr($recruiter->name, 0, 1))); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($recruiter->name); ?></strong>
                                        <?php if (!empty($recruiter->title)) : ?>
                                            <br><small><?php echo esc_html($recruiter->title); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($recruiter->company ?: '—'); ?></td>
                                    <td><?php echo esc_html($recruiter->location ?: '—'); ?></td>
                                    <td>
                                        <?php if ($recruiter->rating > 0) : ?>
                                            <?php echo esc_html(number_format($recruiter->rating, 1)); ?>
                                            <small>(<?php echo esc_html($recruiter->review_count); ?>)</small>
                                        <?php else : ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($recruiter->is_active) : ?>
                                            <span style="color: green;">Active</span>
                                        <?php else : ?>
                                            <span style="color: #999;">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url(add_query_arg('edit', $recruiter->id, admin_url('admin.php?page=external-recruiters'))); ?>" class="button button-small">
                                            <?php esc_html_e('Edit', 'senna-finance'); ?>
                                        </a>
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field('rt_external_recruiter_action'); ?>
                                            <input type="hidden" name="rt_external_action" value="delete">
                                            <input type="hidden" name="recruiter_id" value="<?php echo esc_attr($recruiter->id); ?>">
                                            <button type="submit" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this recruiter?', 'senna-finance'); ?>');">
                                                <?php esc_html_e('Delete', 'senna-finance'); ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render external recruiter add/edit form
     */
    private static function render_external_recruiter_form($recruiter = null) {
        $is_edit = !empty($recruiter);
        ?>
        <form method="post" class="rt-external-form">
            <?php wp_nonce_field('rt_external_recruiter_action'); ?>
            <input type="hidden" name="rt_external_action" value="<?php echo $is_edit ? 'update' : 'create'; ?>">
            <?php if ($is_edit) : ?>
                <input type="hidden" name="recruiter_id" value="<?php echo esc_attr($recruiter->id); ?>">
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="name"><?php esc_html_e('Name', 'senna-finance'); ?> <span class="required">*</span></label></th>
                    <td>
                        <input type="text" name="name" id="name" class="regular-text" required
                               value="<?php echo esc_attr($is_edit ? $recruiter->name : ''); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="company"><?php esc_html_e('Company', 'senna-finance'); ?></label></th>
                    <td>
                        <input type="text" name="company" id="company" class="regular-text"
                               value="<?php echo esc_attr($is_edit ? $recruiter->company : ''); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="title"><?php esc_html_e('Job Title', 'senna-finance'); ?></label></th>
                    <td>
                        <input type="text" name="title" id="title" class="regular-text"
                               value="<?php echo esc_attr($is_edit ? $recruiter->title : ''); ?>">
                        <p class="description"><?php esc_html_e('e.g., Senior Recruiter, Managing Director', 'senna-finance'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="email"><?php esc_html_e('Email', 'senna-finance'); ?></label></th>
                    <td>
                        <input type="email" name="email" id="email" class="regular-text"
                               value="<?php echo esc_attr($is_edit ? $recruiter->email : ''); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="phone"><?php esc_html_e('Phone', 'senna-finance'); ?></label></th>
                    <td>
                        <input type="text" name="phone" id="phone" class="regular-text"
                               value="<?php echo esc_attr($is_edit ? $recruiter->phone : ''); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="website"><?php esc_html_e('Website', 'senna-finance'); ?></label></th>
                    <td>
                        <input type="url" name="website" id="website" class="regular-text"
                               value="<?php echo esc_url($is_edit ? $recruiter->website : ''); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="location"><?php esc_html_e('Location', 'senna-finance'); ?></label></th>
                    <td>
                        <input type="text" name="location" id="location" class="regular-text"
                               value="<?php echo esc_attr($is_edit ? $recruiter->location : ''); ?>">
                        <p class="description"><?php esc_html_e('e.g., London, UK or New York, NY', 'senna-finance'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="photo_url"><?php esc_html_e('Photo URL', 'senna-finance'); ?></label></th>
                    <td>
                        <input type="url" name="photo_url" id="photo_url" class="large-text"
                               value="<?php echo esc_url($is_edit ? $recruiter->photo_url : ''); ?>">
                        <p class="description"><?php esc_html_e('URL to recruiter profile photo (recommended: square, min 200x200px)', 'senna-finance'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="rating"><?php esc_html_e('Rating', 'senna-finance'); ?></label></th>
                    <td>
                        <input type="number" name="rating" id="rating" class="small-text" step="0.1" min="0" max="5"
                               value="<?php echo esc_attr($is_edit ? $recruiter->rating : '0'); ?>">
                        <span>/5</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="review_count"><?php esc_html_e('Review Count', 'senna-finance'); ?></label></th>
                    <td>
                        <input type="number" name="review_count" id="review_count" class="small-text" min="0"
                               value="<?php echo esc_attr($is_edit ? $recruiter->review_count : '0'); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bio"><?php esc_html_e('Bio', 'senna-finance'); ?></label></th>
                    <td>
                        <textarea name="bio" id="bio" rows="4" class="large-text"><?php echo esc_textarea($is_edit ? $recruiter->bio : ''); ?></textarea>
                        <p class="description"><?php esc_html_e('Brief description of the recruiter and their expertise.', 'senna-finance'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Status', 'senna-finance'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="is_active" value="1" <?php checked($is_edit ? $recruiter->is_active : 1); ?>>
                            <?php esc_html_e('Active (visible in matches)', 'senna-finance'); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php echo $is_edit ? esc_html__('Update Recruiter', 'senna-finance') : esc_html__('Add Recruiter', 'senna-finance'); ?>
                </button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=external-recruiters')); ?>" class="button">
                    <?php esc_html_e('Cancel', 'senna-finance'); ?>
                </a>
            </p>
        </form>
        <?php
    }
}
