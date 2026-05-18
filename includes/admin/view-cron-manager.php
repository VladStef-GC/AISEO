<?php

/**
 * Scheduled Tasks (Cron Manager) page view.
 *
 * Variables available (set by Admin::render_cron_manager_page):
 *   $readiness_banner, $jobs, $log
 *
 * @package AI_SEO_Captain
 */

defined('ABSPATH') || exit;

/** @var string $readiness_banner */
/** @var array  $jobs */
/** @var array  $log */

$nonce = wp_create_nonce('ai_seo_captain_cron_manager');
?>
<div class="wrap">
    <h1>
        <img src="<?php echo esc_url(AI_SEO_CAPTAIN_URL . 'assets/img/ai-seo-captain-d.svg'); ?>" alt="" style="width:40px;height:40px;vertical-align:middle;margin-right:10px;" />
        <?php esc_html_e('Scheduled Tasks', 'ai-seo-captain'); ?>
    </h1>
    <p class="description"><?php esc_html_e('Monitor and control all SEO Captain background tasks. Tasks run automatically via WordPress Cron to keep your SEO data fresh and accurate.', 'ai-seo-captain'); ?></p>

    <?php echo $readiness_banner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
    ?>

    <!-- Summary Cards -->
    <div class="aisc-cron-summary">
        <?php
        $active_count = 0;
        $paused_count = 0;
        $error_count  = 0;
        foreach ($jobs as $job) {
            if (! empty($job['paused'])) {
                ++$paused_count;
            } elseif (! empty($job['error_count'])) {
                ++$error_count;
            } else {
                ++$active_count;
            }
        }
        ?>
        <div class="aisc-cron-card aisc-cron-card--active">
            <span class="aisc-cron-card__number"><?php echo (int) $active_count; ?></span>
            <span class="aisc-cron-card__label"><?php esc_html_e('Active', 'ai-seo-captain'); ?></span>
        </div>
        <div class="aisc-cron-card aisc-cron-card--paused">
            <span class="aisc-cron-card__number"><?php echo (int) $paused_count; ?></span>
            <span class="aisc-cron-card__label"><?php esc_html_e('Paused', 'ai-seo-captain'); ?></span>
        </div>
        <div class="aisc-cron-card aisc-cron-card--error">
            <span class="aisc-cron-card__number"><?php echo (int) $error_count; ?></span>
            <span class="aisc-cron-card__label"><?php esc_html_e('Errors', 'ai-seo-captain'); ?></span>
        </div>
        <div class="aisc-cron-card">
            <span class="aisc-cron-card__number"><?php echo count($jobs); ?></span>
            <span class="aisc-cron-card__label"><?php esc_html_e('Total Tasks', 'ai-seo-captain'); ?></span>
        </div>
    </div>

    <!-- Job Table -->
    <div class="aisc-cron-section">
        <h2><?php esc_html_e('Scheduled Tasks', 'ai-seo-captain'); ?></h2>
        <table class="widefat aisc-cron-table" id="aisc-cron-jobs-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Task', 'ai-seo-captain'); ?></th>
                    <th><?php esc_html_e('Schedule', 'ai-seo-captain'); ?></th>
                    <th><?php esc_html_e('Status', 'ai-seo-captain'); ?></th>
                    <th><?php esc_html_e('Last Run', 'ai-seo-captain'); ?></th>
                    <th><?php esc_html_e('Next Run', 'ai-seo-captain'); ?></th>
                    <th><?php esc_html_e('Actions', 'ai-seo-captain'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($jobs)) : ?>
                    <tr>
                        <td colspan="6"><?php esc_html_e('No scheduled tasks found.', 'ai-seo-captain'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($jobs as $hook => $job) :
                        // Determine status badge.
                        if (! empty($job['paused'])) {
                            $badge_class = 'aisc-badge--paused';
                            $badge_text  = __('Paused', 'ai-seo-captain');
                        } elseif (! empty($job['error_count'])) {
                            $badge_class = 'aisc-badge--error';
                            $badge_text  = sprintf(__('Error (%d)', 'ai-seo-captain'), $job['error_count']);
                        } else {
                            $badge_class = 'aisc-badge--active';
                            $badge_text  = __('Active', 'ai-seo-captain');
                        }

                        // Format last run.
                        $last_run_display = '';
                        if (! empty($job['last_run'])) {
                            $last_run_ts = strtotime($job['last_run']);
                            $last_run_display = human_time_diff($last_run_ts, time()) . ' ' . __('ago', 'ai-seo-captain');
                            if (! empty($job['last_duration'])) {
                                $last_run_display .= ' (' . $job['last_duration'] . 's)';
                            }
                        } else {
                            $last_run_display = '—';
                        }

                        // Format next run.
                        if (! empty($job['paused'])) {
                            $next_run_display = __('Paused', 'ai-seo-captain');
                        } elseif (! empty($job['next_run'])) {
                            $next_ts = strtotime($job['next_run']);
                            if ($next_ts < time()) {
                                $next_run_display = __('Overdue', 'ai-seo-captain');
                            } else {
                                $next_run_display = human_time_diff(time(), $next_ts);
                            }
                        } else {
                            $next_run_display = __('Not scheduled', 'ai-seo-captain');
                        }
                    ?>
                        <tr data-hook="<?php echo esc_attr($hook); ?>">
                            <td>
                                <strong class="aisc-cron-task-name"><?php echo esc_html($job['label']); ?></strong>
                                <p class="aisc-cron-task-desc"><?php echo esc_html($job['description']); ?></p>
                                <?php if (! empty($job['last_message']) && ! empty($job['last_status'])) : ?>
                                    <div class="aisc-cron-last-message aisc-cron-last-message--<?php echo esc_attr($job['last_status']); ?>">
                                        <?php echo esc_html($job['last_message']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><span class="aisc-cron-schedule"><?php echo esc_html($job['schedule_display']); ?></span></td>
                            <td><span class="aisc-badge <?php echo esc_attr($badge_class); ?>"><?php echo esc_html($badge_text); ?></span></td>
                            <td><?php echo esc_html($last_run_display); ?></td>
                            <td><?php echo esc_html($next_run_display); ?></td>
                            <td class="aisc-cron-actions">
                                <?php if (! empty($job['paused'])) : ?>
                                    <button type="button" class="button aisc-cron-btn" data-action="resume" data-hook="<?php echo esc_attr($hook); ?>" title="<?php esc_attr_e('Resume this task', 'ai-seo-captain'); ?>">
                                        <span class="dashicons dashicons-controls-play"></span> <?php esc_html_e('Resume', 'ai-seo-captain'); ?>
                                    </button>
                                <?php else : ?>
                                    <button type="button" class="button aisc-cron-btn" data-action="pause" data-hook="<?php echo esc_attr($hook); ?>" title="<?php esc_attr_e('Pause this task', 'ai-seo-captain'); ?>">
                                        <span class="dashicons dashicons-controls-pause"></span> <?php esc_html_e('Pause', 'ai-seo-captain'); ?>
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="button button-primary aisc-cron-btn" data-action="run_now" data-hook="<?php echo esc_attr($hook); ?>" title="<?php esc_attr_e('Execute this task immediately', 'ai-seo-captain'); ?>">
                                    <span class="dashicons dashicons-update"></span> <?php esc_html_e('Run Now', 'ai-seo-captain'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Execution Log -->
    <div class="aisc-cron-section">
        <h2><?php esc_html_e('Execution Log', 'ai-seo-captain'); ?></h2>
        <p class="description"><?php esc_html_e('Recent task executions, showing the most recent first. Up to 100 entries are retained.', 'ai-seo-captain'); ?></p>

        <?php if (empty($log)) : ?>
            <p class="aisc-cron-empty"><?php esc_html_e('No executions recorded yet. Tasks will appear here after their first run.', 'ai-seo-captain'); ?></p>
        <?php else : ?>
            <table class="widefat aisc-cron-table aisc-cron-log-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Time', 'ai-seo-captain'); ?></th>
                        <th><?php esc_html_e('Task', 'ai-seo-captain'); ?></th>
                        <th><?php esc_html_e('Status', 'ai-seo-captain'); ?></th>
                        <th><?php esc_html_e('Duration', 'ai-seo-captain'); ?></th>
                        <th><?php esc_html_e('Message', 'ai-seo-captain'); ?></th>
                        <th><?php esc_html_e('Trigger', 'ai-seo-captain'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($log as $entry) :
                        $log_status_class = ('success' === $entry['status']) ? 'aisc-badge--active' : (('error' === $entry['status']) ? 'aisc-badge--error' : 'aisc-badge--paused');
                        $trigger_label    = ! empty($entry['manual']) ? __('Manual', 'ai-seo-captain') : __('Scheduled', 'ai-seo-captain');
                        $time_display     = '';
                        if (! empty($entry['timestamp'])) {
                            $ts = strtotime($entry['timestamp']);
                            $time_display = human_time_diff($ts, time()) . ' ' . __('ago', 'ai-seo-captain');
                        }
                    ?>
                        <tr>
                            <td title="<?php echo esc_attr($entry['timestamp'] ?? ''); ?>"><?php echo esc_html($time_display); ?></td>
                            <td><?php echo esc_html($entry['label'] ?? $entry['hook']); ?></td>
                            <td><span class="aisc-badge <?php echo esc_attr($log_status_class); ?>"><?php echo esc_html(ucfirst($entry['status'])); ?></span></td>
                            <td><?php echo esc_html($entry['duration'] . 's'); ?></td>
                            <td class="aisc-cron-log-message"><?php echo esc_html($entry['message']); ?></td>
                            <td><span class="aisc-cron-trigger"><?php echo esc_html($trigger_label); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
    var aiscCronNonce = <?php echo wp_json_encode($nonce); ?>;
    var aiscAjaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
</script>