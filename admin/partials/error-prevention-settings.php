<?php

/**
 * Error Prevention Engine Admin Settings
 * 
 * @package MENA Careers
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle manual scan request
$scan_results = null;
if (isset($_POST['sffc_run_error_scan']) && wp_verify_nonce($_POST['sffc_error_scan_nonce'], 'sffc_error_scan')) {
    // Run manual scan
    require_once plugin_dir_path(dirname(dirname(__FILE__))) . 'includes/class-error-prevention-engine.php';

    // Define manual mode to prevent auto-initialization
    if (!defined('SFFC_ERROR_ENGINE_MANUAL')) {
        define('SFFC_ERROR_ENGINE_MANUAL', true);
    }

    $engine = SFFC_Error_Prevention_Engine::get_instance();
    $scan_results = $engine->run_manual_scan();

    // Save scan results
    update_option('sffc_last_error_scan', [
        'time' => current_time('mysql'),
        'results' => $scan_results
    ]);
}

// Get last scan info
$last_scan = get_option('sffc_last_error_scan');

// Get error prevention settings
$auto_mode = get_option('sffc_error_prevention_auto', 'enabled');
$prevented_errors = get_transient('sffc_prevented_errors') ?: [];

?>

<div class="wrap">
    <h1><?php echo esc_html__('Error Prevention Engine', 'senna'); ?></h1>

    <div class="notice notice-info">
        <p><?php _e('The Error Prevention Engine automatically detects and fixes common database and PHP errors before they cause 500 errors.', 'senna'); ?></p>
    </div>

    <!-- Status Card -->
    <div class="card" style="max-width: 800px; margin: 20px 0;">
        <h2><?php _e('Engine Status', 'senna'); ?></h2>

        <table class="form-table">
            <tr>
                <th><?php _e('Current Mode', 'senna'); ?></th>
                <td>
                    <span class="dashicons <?php echo $auto_mode === 'enabled' ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"
                        style="color: <?php echo $auto_mode === 'enabled' ? '#46b450' : '#ffb900'; ?>;">
                    </span>
                    <?php echo $auto_mode === 'enabled' ? __('Automatic Protection Active', 'senna') : __('Manual Mode Only', 'senna'); ?>
                </td>
            </tr>
            <tr>
                <th><?php _e('Prevented Errors', 'senna'); ?></th>
                <td>
                    <span style="font-size: 24px; font-weight: bold; color: #0073aa;">
                        <?php echo count($prevented_errors); ?>
                    </span>
                    <?php _e('errors blocked in the last hour', 'senna'); ?>
                </td>
            </tr>
            <tr>
                <th><?php _e('Last Manual Scan', 'senna'); ?></th>
                <td>
                    <?php if ($last_scan): ?>
                        <?php echo human_time_diff(strtotime($last_scan['time']), current_time('timestamp')); ?> ago
                        <?php if (!empty($last_scan['results']['issues'])): ?>
                            <span style="color: #d63638;">
                                (<?php echo count($last_scan['results']['issues']); ?> issues found)
                            </span>
                        <?php else: ?>
                            <span style="color: #46b450;">(No issues found)</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php _e('Never', 'senna'); ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>

    <!-- Manual Scan Card -->
    <div class="card" style="max-width: 800px; margin: 20px 0;">
        <h2><?php _e('Manual Scan', 'senna'); ?></h2>
        <p><?php _e('Run a manual scan to check for potential database errors and fix them immediately.', 'senna'); ?></p>

        <form method="post" action="">
            <?php wp_nonce_field('sffc_error_scan', 'sffc_error_scan_nonce'); ?>

            <p>
                <button type="submit" name="sffc_run_error_scan" class="button button-primary button-hero">
                    <span class="dashicons dashicons-search" style="vertical-align: middle;"></span>
                    <?php _e('Run Error Scan Now', 'senna'); ?>
                </button>

                <span id="sffc-scan-spinner" class="spinner" style="display: none; float: none; margin: 0 10px;"></span>
            </p>
        </form>

        <?php if ($scan_results !== null): ?>
            <div class="notice notice-<?php echo empty($scan_results['issues']) ? 'success' : 'warning'; ?> is-dismissible" style="margin-top: 20px;">
                <h3><?php _e('Scan Results', 'senna'); ?></h3>

                <?php if (empty($scan_results['issues'])): ?>
                    <p>
                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php _e('No errors found! Your database is clean.', 'senna'); ?>
                    </p>
                <?php else: ?>
                    <p>
                        <span class="dashicons dashicons-warning" style="color: #ffb900;"></span>
                        <?php printf(__('Found %d potential issues:', 'senna'), count($scan_results['issues'])); ?>
                    </p>

                    <ul style="list-style: disc; margin-left: 30px;">
                        <?php foreach ($scan_results['issues'] as $issue): ?>
                            <li>
                                <strong><?php echo esc_html($issue['type']); ?>:</strong>
                                <?php echo esc_html($issue['description']); ?>
                                <?php if ($issue['fixed']): ?>
                                    <span style="color: #46b450;">(Fixed)</span>
                                <?php else: ?>
                                    <span style="color: #d63638;">(Needs attention)</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <p>
                    <small>
                        <?php _e('Scanned:', 'senna'); ?>
                        <?php echo $scan_results['stats']['tables_checked']; ?> tables,
                        <?php echo $scan_results['stats']['queries_analyzed']; ?> recent queries
                    </small>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Mode Settings Card -->
    <div class="card" style="max-width: 800px; margin: 20px 0;">
        <h2><?php _e('Settings', 'senna'); ?></h2>

        <form method="post" action="options.php">
            <?php settings_fields('sffc_error_prevention_settings'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Protection Mode', 'senna'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="sffc_error_prevention_auto" value="enabled"
                                    <?php checked($auto_mode, 'enabled'); ?>>
                                <strong><?php _e('Automatic', 'senna'); ?></strong> -
                                <?php _e('Continuously monitor and fix errors in real-time', 'senna'); ?>
                            </label>
                            <br><br>
                            <label>
                                <input type="radio" name="sffc_error_prevention_auto" value="manual"
                                    <?php checked($auto_mode, 'manual'); ?>>
                                <strong><?php _e('Manual Only', 'senna'); ?></strong> -
                                <?php _e('Only scan when manually triggered', 'senna'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Error Logging', 'senna'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="sffc_error_verbose_logging" value="1"
                                <?php checked(get_option('sffc_error_verbose_logging'), '1'); ?>>
                            <?php _e('Enable verbose logging (for debugging)', 'senna'); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Save Settings', 'senna')); ?>
        </form>
    </div>

    <!-- Recent Errors Card -->
    <?php if (!empty($prevented_errors)): ?>
        <div class="card" style="max-width: 800px; margin: 20px 0;">
            <h2><?php _e('Recently Prevented Errors', 'senna'); ?></h2>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('Time', 'senna'); ?></th>
                        <th><?php _e('Error Type', 'senna'); ?></th>
                        <th><?php _e('Description', 'senna'); ?></th>
                        <th><?php _e('Query Fragment', 'senna'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($prevented_errors, -10) as $error): ?>
                        <tr>
                            <td><?php echo human_time_diff(strtotime($error['time']), current_time('timestamp')); ?> ago</td>
                            <td><code><?php echo esc_html($error['fix']['type']); ?></code></td>
                            <td><?php echo esc_html($error['fix']['description']); ?></td>
                            <td>
                                <code style="word-break: break-all;">
                                    <?php echo esc_html(substr($error['query'], 0, 50)); ?>...
                                </code>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top: 10px;">
                <a href="#" onclick="if(confirm('Clear all prevented error logs?')) { document.getElementById('clear-logs-form').submit(); } return false;"
                    class="button button-secondary">
                    <?php _e('Clear Error Logs', 'senna'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>

    <!-- Hidden form for clearing logs -->
    <form id="clear-logs-form" method="post" style="display: none;">
        <?php wp_nonce_field('sffc_clear_error_logs', 'sffc_clear_logs_nonce'); ?>
        <input type="hidden" name="sffc_clear_error_logs" value="1">
    </form>
</div>

<script>
    jQuery(document).ready(function($) {
        // Show spinner when scanning
        $('button[name="sffc_run_error_scan"]').on('click', function() {
            $('#sffc-scan-spinner').show().addClass('is-active');
        });

        // Auto-dismiss notices after 10 seconds
        setTimeout(function() {
            $('.notice.is-dismissible').not('.notice-info').fadeOut();
        }, 10000);
    });
</script>

<style>
    .card {
        background: #fff;
        border: 1px solid #c3c4c7;
        box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
        padding: 20px;
    }

    .card h2 {
        margin-top: 0;
        font-size: 1.3em;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }

    .button-hero {
        font-size: 16px !important;
        line-height: 28px !important;
        height: 40px !important;
        padding: 0 20px !important;
    }

    code {
        background: #f0f0f1;
        padding: 2px 4px;
        font-size: 12px;
    }
</style>