<?php
/**
 * Feed Scheduler Admin Page Template
 * 
 * @package SFFC
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html__('Feed Scheduler', 'sffc'); ?></h1>
    <p><?php echo esc_html__('Automatically fetch jobs from selected feeds on a schedule.', 'sffc'); ?></p>

    <?php if (isset($_GET['message'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($_GET['message']); ?></p>
        </div>
    <?php endif; ?>

    <!-- Status Overview -->
    <div class="sffc-scheduler-status">
        <h2><?php echo esc_html__('Status', 'sffc'); ?></h2>
        <table class="widefat">
            <tbody>
                <tr>
                    <th><?php echo esc_html__('Scheduler Status', 'sffc'); ?></th>
                    <td>
                        <?php if ($settings['enabled']): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                            <?php echo esc_html__('Enabled', 'sffc'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-dismiss" style="color: red;"></span>
                            <?php echo esc_html__('Disabled', 'sffc'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Next Scheduled Run', 'sffc'); ?></th>
                    <td>
                        <?php if ($next_run): ?>
                            <?php echo esc_html($next_run['date_time']); ?>
                            (<?php printf(__('in %s', 'sffc'), $next_run['human_time']); ?>)
                        <?php else: ?>
                            <?php echo esc_html__('No scheduled runs', 'sffc'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Total Available Feeds', 'sffc'); ?></th>
                    <td>
                        <?php 
                        $total_feeds = count($all_feeds['workday']) + count($all_feeds['xml']);
                        echo esc_html($total_feeds);
                        ?>
                        (<?php echo count($all_feeds['workday']); ?> Workday, <?php echo count($all_feeds['xml']); ?> XML/Other)
                    </td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Selected Feeds', 'sffc'); ?></th>
                    <td><?php echo count($settings['feeds']); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Settings Form -->
    <form id="sffc-scheduler-settings-form" method="post">
        <?php wp_nonce_field('sffc_scheduler_settings', 'sffc_scheduler_nonce'); ?>
        
        <h2><?php echo esc_html__('Scheduler Settings', 'sffc'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="scheduler-enabled"><?php echo esc_html__('Enable Scheduler', 'sffc'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="scheduler-enabled" name="enabled" value="1" <?php checked($settings['enabled']); ?>>
                        <?php echo esc_html__('Enable automatic feed fetching', 'sffc'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="scheduler-frequency"><?php echo esc_html__('Frequency', 'sffc'); ?></label>
                </th>
                <td>
                    <select id="scheduler-frequency" name="frequency">
                        <option value="every_30_minutes" <?php selected($settings['frequency'], 'every_30_minutes'); ?>>
                            <?php echo esc_html__('Every 30 minutes', 'sffc'); ?>
                        </option>
                        <option value="hourly" <?php selected($settings['frequency'], 'hourly'); ?>>
                            <?php echo esc_html__('Hourly', 'sffc'); ?>
                        </option>
                        <option value="every_3_hours" <?php selected($settings['frequency'], 'every_3_hours'); ?>>
                            <?php echo esc_html__('Every 3 hours', 'sffc'); ?>
                        </option>
                        <option value="every_6_hours" <?php selected($settings['frequency'], 'every_6_hours'); ?>>
                            <?php echo esc_html__('Every 6 hours', 'sffc'); ?>
                        </option>
                        <option value="every_12_hours" <?php selected($settings['frequency'], 'every_12_hours'); ?>>
                            <?php echo esc_html__('Every 12 hours', 'sffc'); ?>
                        </option>
                        <option value="daily" <?php selected($settings['frequency'], 'daily'); ?>>
                            <?php echo esc_html__('Daily', 'sffc'); ?>
                        </option>
                        <option value="every_3_days" <?php selected($settings['frequency'], 'every_3_days'); ?>>
                            <?php echo esc_html__('Every 3 days', 'sffc'); ?>
                        </option>
                        <option value="weekly" <?php selected($settings['frequency'], 'weekly'); ?>>
                            <?php echo esc_html__('Weekly', 'sffc'); ?>
                        </option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="max-jobs-per-feed"><?php echo esc_html__('Max Jobs Per Feed', 'sffc'); ?></label>
                </th>
                <td>
                    <input type="number" id="max-jobs-per-feed" name="max_jobs_per_feed" 
                           value="<?php echo esc_attr($settings['max_jobs_per_feed']); ?>" 
                           min="1" max="500">
                    <p class="description">
                        <?php echo esc_html__('Maximum number of jobs to fetch from each feed per run.', 'sffc'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php echo esc_html__('Save Jobs', 'sffc'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="save_to_database" value="1" <?php checked($settings['save_to_database']); ?>>
                        <?php echo esc_html__('Save fetched jobs to database', 'sffc'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php echo esc_html__('Email Notifications', 'sffc'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="email_notifications" value="1" <?php checked($settings['email_notifications']); ?>>
                        <?php echo esc_html__('Send email notifications after each run', 'sffc'); ?>
                    </label>
                    <br><br>
                    <input type="email" name="notification_email" 
                           value="<?php echo esc_attr($settings['notification_email']); ?>" 
                           placeholder="<?php echo esc_attr__('Email address', 'sffc'); ?>"
                           style="width: 300px;">
                </td>
            </tr>
        </table>
        
        <h2><?php echo esc_html__('Select Feeds', 'sffc'); ?></h2>
        <p class="description">
            <?php echo esc_html__('Select which feeds to include in scheduled fetching.', 'sffc'); ?>
        </p>
        
        <div class="sffc-feeds-selector">
            <!-- Workday Feeds -->
            <?php if (!empty($all_feeds['workday'])): ?>
                <h3><?php echo esc_html__('Workday Feeds', 'sffc'); ?></h3>
                <div class="feeds-checkboxes">
                    <label style="font-weight: bold;">
                        <input type="checkbox" class="select-all-workday">
                        <?php echo esc_html__('Select All Workday Feeds', 'sffc'); ?>
                    </label>
                    <hr>
                    <?php foreach ($all_feeds['workday'] as $key => $feed): ?>
                        <?php $feed_id = 'workday:' . $key; ?>
                        <label style="display: block; margin: 5px 0;">
                            <input type="checkbox" name="feeds[]" value="<?php echo esc_attr($feed_id); ?>"
                                   class="workday-feed-checkbox"
                                   <?php checked(in_array($feed_id, $settings['feeds'])); ?>>
                            <?php echo esc_html($feed['name']); ?>
                            <span style="color: #666; font-size: 12px;">
                                (<?php echo esc_html($feed['status']); ?>)
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- XML/Other Feeds -->
            <?php if (!empty($all_feeds['xml'])): ?>
                <h3><?php echo esc_html__('XML/RSS/API Feeds', 'sffc'); ?></h3>
                <div class="feeds-checkboxes">
                    <label style="font-weight: bold;">
                        <input type="checkbox" class="select-all-xml">
                        <?php echo esc_html__('Select All XML Feeds', 'sffc'); ?>
                    </label>
                    <hr>
                    <?php foreach ($all_feeds['xml'] as $key => $feed): ?>
                        <?php $feed_id = 'xml:' . $key; ?>
                        <label style="display: block; margin: 5px 0;">
                            <input type="checkbox" name="feeds[]" value="<?php echo esc_attr($feed_id); ?>"
                                   class="xml-feed-checkbox"
                                   <?php checked(in_array($feed_id, $settings['feeds'])); ?>>
                            <?php echo esc_html($feed['name']); ?>
                            <span style="color: #666; font-size: 12px;">
                                (<?php echo esc_html($feed['type']); ?>)
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <p class="submit">
            <button type="submit" name="save_settings" class="button button-primary">
                <?php echo esc_html__('Save Settings', 'sffc'); ?>
            </button>
            <button type="button" id="run-scheduler-now" class="button button-secondary">
                <?php echo esc_html__('Run Now', 'sffc'); ?>
            </button>
            <button type="button" id="clear-history" class="button">
                <?php echo esc_html__('Clear History', 'sffc'); ?>
            </button>
        </p>
    </form>
    
    <!-- Fetch History -->
    <h2><?php echo esc_html__('Recent Fetch History', 'sffc'); ?></h2>
    <?php if (!empty($history)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Date/Time', 'sffc'); ?></th>
                    <th><?php echo esc_html__('Type', 'sffc'); ?></th>
                    <th><?php echo esc_html__('Feeds', 'sffc'); ?></th>
                    <th><?php echo esc_html__('Fetched', 'sffc'); ?></th>
                    <th><?php echo esc_html__('Saved', 'sffc'); ?></th>
                    <th><?php echo esc_html__('Time', 'sffc'); ?></th>
                    <th><?php echo esc_html__('Status', 'sffc'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $entry): ?>
                    <tr>
                        <td><?php echo esc_html($entry['timestamp']); ?></td>
                        <td><?php echo esc_html($entry['type']); ?></td>
                        <td><?php echo esc_html($entry['feeds_processed'] ?? 0); ?></td>
                        <td><?php echo esc_html($entry['total_fetched'] ?? 0); ?></td>
                        <td><?php echo esc_html($entry['total_saved'] ?? 0); ?></td>
                        <td><?php echo esc_html($entry['execution_time'] ?? 0); ?>s</td>
                        <td>
                            <?php if ($entry['status'] === 'success'): ?>
                                <span style="color: green;">✓ <?php echo esc_html__('Success', 'sffc'); ?></span>
                            <?php elseif ($entry['status'] === 'partial'): ?>
                                <span style="color: orange;">⚠ <?php echo esc_html__('Partial', 'sffc'); ?></span>
                            <?php else: ?>
                                <span style="color: red;">✗ <?php echo esc_html__('Failed', 'sffc'); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($entry['errors'])): ?>
                                <div style="font-size: 11px; color: red;">
                                    <?php foreach ($entry['errors'] as $error): ?>
                                        <?php echo esc_html($error); ?><br>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p><?php echo esc_html__('No fetch history available yet.', 'sffc'); ?></p>
    <?php endif; ?>
</div>

<style>
.sffc-scheduler-status {
    background: white;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.sffc-feeds-selector {
    background: #f9f9f9;
    padding: 15px;
    border: 1px solid #ddd;
    max-height: 400px;
    overflow-y: auto;
    margin: 20px 0;
}

.feeds-checkboxes {
    margin-bottom: 20px;
}

.feeds-checkboxes label {
    display: block;
    padding: 3px 0;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Select all functionality
    $('.select-all-workday').on('change', function() {
        $('.workday-feed-checkbox').prop('checked', $(this).prop('checked'));
    });
    
    $('.select-all-xml').on('change', function() {
        $('.xml-feed-checkbox').prop('checked', $(this).prop('checked'));
    });
    
    // Save settings via AJAX
    $('#sffc-scheduler-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        
        $button.prop('disabled', true).text('<?php echo esc_js(__('Saving...', 'sffc')); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: $form.serialize() + '&action=sffc_scheduler_update_settings&nonce=<?php echo wp_create_nonce('sffc_scheduler'); ?>',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Error saving settings', 'sffc')); ?>');
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php echo esc_js(__('Save Settings', 'sffc')); ?>');
            }
        });
    });
    
    // Run now functionality
    $('#run-scheduler-now').on('click', function() {
        if (!confirm('<?php echo esc_js(__('Are you sure you want to run the scheduler now? This may take several minutes.', 'sffc')); ?>')) {
            return;
        }
        
        var $button = $(this);
        $button.prop('disabled', true).text('<?php echo esc_js(__('Running...', 'sffc')); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sffc_scheduler_run_now',
                nonce: '<?php echo wp_create_nonce('sffc_scheduler'); ?>'
            },
            timeout: 300000, // 5 minutes timeout
            success: function(response) {
                if (response.success) {
                    alert('<?php echo esc_js(__('Scheduler executed successfully!', 'sffc')); ?>\n\n' +
                          '<?php echo esc_js(__('Fetched:', 'sffc')); ?> ' + response.data.results.total_fetched + ' <?php echo esc_js(__('jobs', 'sffc')); ?>\n' +
                          '<?php echo esc_js(__('Saved:', 'sffc')); ?> ' + response.data.results.total_saved + ' <?php echo esc_js(__('jobs', 'sffc')); ?>');
                    location.reload();
                } else {
                    alert('Error: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Error running scheduler', 'sffc')); ?>');
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php echo esc_js(__('Run Now', 'sffc')); ?>');
            }
        });
    });
    
    // Clear history
    $('#clear-history').on('click', function() {
        if (!confirm('<?php echo esc_js(__('Are you sure you want to clear the fetch history?', 'sffc')); ?>')) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sffc_scheduler_clear_history',
                nonce: '<?php echo wp_create_nonce('sffc_scheduler'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                }
            }
        });
    });
});
</script>