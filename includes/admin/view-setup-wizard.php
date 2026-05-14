<?php

/**
 * Setup Wizard page view.
 *
 * Variables available from render_setup_wizard_page() stub:
 *   $options, $has_api_key, $summary, $has_index, $has_metadata, $meta_count,
 *   $published_ids_json, $audited_count, $existing_audits, $skipped_ids,
 *   $existing_audits_json, $skipped_ids_json, $skip_patterns, $total_pages,
 *   $step2_all_done, $step3_all_done,
 *   $ajax_setup_index_action, $ajax_bulk_generate_action, $ajax_page_audit_action,
 *   $ajax_toggle_audit_skip_action, $ajax_save_skip_patterns_action,
 *   $runs_with_status
 *
 * CSS loaded via: assets/css/page-setup-wizard.css (enqueue_page_assets)
 */

defined('ABSPATH') || exit;

/** @var array  $options */
/** @var bool   $has_api_key */
/** @var array  $summary */
/** @var bool   $has_index */
/** @var bool   $has_metadata */
/** @var int    $meta_count */
/** @var string $published_ids_json */
/** @var int    $audited_count */
/** @var array  $existing_audits */
/** @var array  $skipped_ids */
/** @var string $existing_audits_json */
/** @var string $skipped_ids_json */
/** @var string $skip_patterns */
/** @var int    $total_pages */
/** @var bool   $step2_all_done */
/** @var bool   $step3_all_done */
/** @var string $ajax_setup_index_action */
/** @var string $ajax_bulk_generate_action */
/** @var string $ajax_page_audit_action */
/** @var string $ajax_toggle_audit_skip_action */
/** @var string $ajax_save_skip_patterns_action */
/** @var array  $runs_with_status */
/** @var int    $max_pages */
?>
<div class="wrap aisk-wizard">
    <h1>🚀 <?php esc_html_e('AI SEO Keeper — Setup Wizard', 'ai-seo-keeper'); ?></h1>
    <p style="font-size: 14px; max-width: 700px;">
        <?php esc_html_e('This wizard indexes your site, generates AI-powered SEO metadata, and runs a full page audit — all in three easy steps.', 'ai-seo-keeper'); ?>
    </p>

    <?php if (! $has_api_key) : ?>
        <div class="notice notice-warning" style="margin-top:12px;">
            <p><strong><?php esc_html_e('No API key configured.', 'ai-seo-keeper'); ?></strong> <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-keeper-settings')); ?>"><?php esc_html_e('Go to Settings', 'ai-seo-keeper'); ?></a> <?php esc_html_e('and add your AI provider API key before running the wizard.', 'ai-seo-keeper'); ?></p>
        </div>
    <?php endif; ?>

    <!-- STEP 1: INDEX -->
    <div id="aisk-step-1" class="aisk-step">
        <div class="aisk-step-header">
            <span id="aisk-s1-badge" class="aisk-badge active">1</span>
            <h2 style="margin:0;"><?php esc_html_e('Index Your Site', 'ai-seo-keeper'); ?></h2>
        </div>
        <p><?php esc_html_e('Scan all published pages and build the content index. This is required before AI processing.', 'ai-seo-keeper'); ?></p>
        <div class="aisk-controls">
            <button id="aisk-btn-index" class="button button-primary button-hero" type="button" <?php disabled(! $has_api_key); ?>>
                <?php echo $has_index ? esc_html__('Re-Index Site', 'ai-seo-keeper') : esc_html__('Start Indexing', 'ai-seo-keeper'); ?>
            </button>
        </div>
        <div id="aisk-s1-progress" class="aisk-progress-wrap">
            <div class="aisk-progress-track">
                <div id="aisk-s1-bar" class="aisk-progress-bar" style="background:#2271b1;"></div>
            </div>
            <div class="aisk-progress-info"><span id="aisk-s1-status"></span></div>
        </div>
        <div id="aisk-s1-done" class="aisk-done-banner success">
            <strong>&#10003; <?php esc_html_e('Indexing complete.', 'ai-seo-keeper'); ?></strong> <span id="aisk-s1-result"></span>
            <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-keeper-bulk-editor')); ?>" class="button button-small" style="margin-left:12px;"><?php esc_html_e('View Full Page List', 'ai-seo-keeper'); ?></a>
        </div>
        <div id="aisk-s1-error" class="aisk-error-banner"></div>
    </div>

    <!-- STEP 2: BULK GENERATE -->
    <div id="aisk-step-2" class="aisk-step<?php echo ! $has_index ? ' locked' : ''; ?>">
        <div class="aisk-step-header">
            <span id="aisk-s2-badge" class="aisk-badge <?php echo $has_index ? 'active' : 'pending'; ?>">2</span>
            <h2 style="margin:0;"><?php esc_html_e('Generate SEO Metadata', 'ai-seo-keeper'); ?></h2>
            <span id="aisk-s2-elapsed" class="aisk-elapsed"></span>
        </div>
        <p><?php esc_html_e('AI reads each page and generates an SEO title and meta description. Pages that already have metadata are skipped automatically.', 'ai-seo-keeper'); ?></p>
        <div class="aisk-controls">
            <button id="aisk-btn-generate" class="button button-primary button-hero" type="button" <?php disabled(! $has_index); ?>>
                <?php echo $has_metadata ? esc_html__('Continue Generation', 'ai-seo-keeper') : esc_html__('Start AI Generation', 'ai-seo-keeper'); ?>
            </button>
            <button id="aisk-btn-s2-pause" class="button" type="button" style="display:none;">&#10074;&#10074; <?php esc_html_e('Pause', 'ai-seo-keeper'); ?></button>
            <button id="aisk-btn-s2-stop" class="button" type="button" style="display:none;">&#9632; <?php esc_html_e('Stop', 'ai-seo-keeper'); ?></button>
        </div>
        <div id="aisk-s2-progress" class="aisk-progress-wrap">
            <div class="aisk-progress-track">
                <div id="aisk-s2-bar" class="aisk-progress-bar" style="background:#00a32a;"></div>
            </div>
            <div class="aisk-progress-info">
                <span id="aisk-s2-status"></span>
                <span id="aisk-s2-counts" style="font-size:12px;"></span>
            </div>
        </div>
        <div id="aisk-s2-log" class="aisk-log"></div>
        <div id="aisk-s2-done" class="aisk-done-banner success">
            <strong>&#10003; <?php esc_html_e('Generation complete.', 'ai-seo-keeper'); ?></strong> <span id="aisk-s2-result"></span>
        </div>
        <div id="aisk-s2-paused" class="aisk-done-banner warning" style="display:none;">
            <strong>&#10074;&#10074; Paused.</strong> <span id="aisk-s2-paused-info"></span> Click <em>Resume</em> to continue.
        </div>
        <div id="aisk-s2-stopped" class="aisk-done-banner warning" style="display:none;">
            <strong>&#9632; Stopped.</strong> <span id="aisk-s2-stopped-info"></span> You can restart or continue later.
        </div>
        <div id="aisk-s2-error" class="aisk-error-banner"></div>

        <?php if (! empty($runs_with_status)) : ?>
            <div class="aisk-runs-summary" id="aisk-s2-runs">
                <h4 class="aisk-runs-title"><span class="dashicons dashicons-list-view"></span> <?php esc_html_e('Lists (Metadata)', 'ai-seo-keeper'); ?></h4>
                <?php foreach ($runs_with_status as $run) :
                    $meta_done = in_array('metadata', explode(',', $run['completed_steps'] ?? ''), true);
                    $badge_class = $meta_done ? 'is-complete' : 'is-pending';
                    $badge_label = $meta_done ? 'Done' : 'Pending';
                ?>
                    <div class="aisk-run-badge <?php echo $badge_class; ?>" data-run-id="<?php echo (int) $run['id']; ?>">
                        <strong><?php echo esc_html($run['name']); ?></strong>
                        <span><?php echo esc_html($badge_label); ?></span>
                        <?php if ($meta_done) : ?>
                            <span class="dashicons dashicons-yes-alt"></span>
                        <?php endif; ?>
                        <button type="button" class="aisk-run-delete" data-run-id="<?php echo (int) $run['id']; ?>" title="<?php esc_attr_e('Delete list', 'ai-seo-keeper'); ?>">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- STEP 3: PAGE AUDITS -->
    <div id="aisk-step-3" class="aisk-step<?php echo (! $has_index || ! $has_metadata) ? ' locked' : ''; ?>">
        <div class="aisk-step-header">
            <span id="aisk-s3-badge" class="aisk-badge <?php echo ($has_index && $has_metadata) ? 'active' : 'pending'; ?>">3</span>
            <h2 style="margin:0;"><?php esc_html_e('Full SEO Audit', 'ai-seo-keeper'); ?></h2>
            <span id="aisk-s3-elapsed" class="aisk-elapsed"></span>
        </div>
        <p><?php esc_html_e('AI analyzes each page individually: missing alt tags, content issues, heading structure, and specific improvement suggestions.', 'ai-seo-keeper'); ?></p>
        <?php if ($audited_count > 0 && ! $step3_all_done) : ?>
            <p style="font-size:13px;color:#50575e;">&#128204; <?php echo (int) $audited_count; ?> of <?php echo (int) $total_pages; ?> pages already audited. Previously audited pages load from cache instantly.</p>
        <?php endif; ?>
        <div class="aisk-controls">
            <button id="aisk-btn-audit" class="button button-primary button-hero" type="button" <?php disabled(! $has_index || ! $has_metadata); ?>>
                <?php
                if ($step3_all_done) {
                    echo 'Re-Run Audits';
                } elseif ($audited_count > 0) {
                    echo 'Continue Audits';
                } else {
                    echo 'Start Page Audits';
                }
                ?>
            </button>
            <button id="aisk-btn-s3-pause" class="button" type="button" style="display:none;">&#10074;&#10074; Pause</button>
            <button id="aisk-btn-s3-stop" class="button" type="button" style="display:none;">&#9632; Stop</button>
        </div>
        <div id="aisk-s3-progress" class="aisk-progress-wrap">
            <div class="aisk-progress-track">
                <div id="aisk-s3-bar" class="aisk-progress-bar" style="background:#dba617;"></div>
            </div>
            <div class="aisk-progress-info">
                <span id="aisk-s3-status"></span>
                <span id="aisk-s3-counts" style="font-size:12px;"></span>
            </div>
        </div>
        <div id="aisk-s3-done" class="aisk-done-banner warning">
            <strong>&#10003; Audit complete.</strong> <span id="aisk-s3-result"></span>
        </div>
        <div id="aisk-s3-paused" class="aisk-done-banner warning" style="display:none;">
            <strong>&#10074;&#10074; Paused.</strong> <span id="aisk-s3-paused-info"></span> Click <em>Resume</em> to continue.
        </div>
        <div id="aisk-s3-stopped" class="aisk-done-banner warning" style="display:none;">
            <strong>&#9632; Stopped.</strong> <span id="aisk-s3-stopped-info"></span> You can restart or continue later.
        </div>
        <div id="aisk-s3-error" class="aisk-error-banner"></div>

        <?php if (! empty($runs_with_status)) : ?>
            <div class="aisk-runs-summary" id="aisk-s3-runs">
                <h4 class="aisk-runs-title"><span class="dashicons dashicons-list-view"></span> <?php esc_html_e('Lists (Audit)', 'ai-seo-keeper'); ?></h4>
                <?php foreach ($runs_with_status as $run) :
                    $audit_done = in_array('audit', explode(',', $run['completed_steps'] ?? ''), true);
                    $badge_class = $audit_done ? 'is-complete' : 'is-pending';
                    $badge_label = $audit_done ? 'Done' : 'Pending';
                ?>
                    <div class="aisk-run-badge <?php echo $badge_class; ?>" data-run-id="<?php echo (int) $run['id']; ?>">
                        <strong><?php echo esc_html($run['name']); ?></strong>
                        <span><?php echo esc_html($badge_label); ?></span>
                        <?php if ($audit_done) : ?>
                            <span class="dashicons dashicons-yes-alt"></span>
                        <?php endif; ?>
                        <button type="button" class="aisk-run-delete" data-run-id="<?php echo (int) $run['id']; ?>" title="<?php esc_attr_e('Delete list', 'ai-seo-keeper'); ?>">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Collapsible Tabs -->
        <div id="aisk-s3-tabs" style="margin-top:20px;<?php echo $audited_count === 0 ? 'display:none;' : ''; ?>">

            <!-- TAB 1: Summary Overview -->
            <details id="aisk-tab-summary" open style="border:1px solid #dcdcde;border-radius:4px;margin-bottom:12px;background:#fff;">
                <summary style="cursor:pointer;padding:14px 18px;font-weight:600;font-size:14px;background:#f6f7f7;border-radius:4px 4px 0 0;user-select:none;">
                    &#128202; Audit Overview
                    <span id="aisk-tab-summary-count" style="font-weight:400;font-size:13px;color:#787c82;margin-left:8px;"></span>
                </summary>
                <div style="padding:18px;">
                    <!-- Score distribution summary -->
                    <div id="aisk-score-summary" style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px;"></div>

                    <!-- Top 10 / Bottom 10 tables side by side -->
                    <div style="display:flex;gap:20px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:300px;">
                            <h4 style="margin:0 0 8px;color:#00a32a;">&#127942; Top 10 — Best SEO Scores</h4>
                            <table id="aisk-top10-table" class="widefat striped" style="font-size:13px;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Page</th>
                                        <th style="width:80px;text-align:center;">Score</th>
                                        <th style="width:80px;text-align:center;">Issues</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div style="flex:1;min-width:300px;">
                            <h4 style="margin:0 0 8px;color:#d63638;">&#9888; Bottom 10 — Needs Attention</h4>
                            <table id="aisk-bottom10-table" class="widefat striped" style="font-size:13px;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Page</th>
                                        <th style="width:80px;text-align:center;">Score</th>
                                        <th style="width:80px;text-align:center;">Issues</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </details>

            <!-- TAB 2: Detailed Results -->
            <details id="aisk-tab-details" style="border:1px solid #dcdcde;border-radius:4px;background:#fff;">
                <summary style="cursor:pointer;padding:14px 18px;font-weight:600;font-size:14px;background:#f6f7f7;border-radius:4px 4px 0 0;user-select:none;">
                    &#128196; Detailed Page Reports
                    <span id="aisk-tab-details-count" style="font-weight:400;font-size:13px;color:#787c82;margin-left:8px;"></span>
                </summary>
                <div style="padding:18px;">
                    <!-- Sort controls -->
                    <div style="margin-bottom:14px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                        <label style="font-size:13px;font-weight:600;">Sort by:</label>
                        <select id="aisk-sort-order" style="min-width:180px;">
                            <option value="score-asc">Score: Worst first</option>
                            <option value="score-desc">Score: Best first</option>
                            <option value="title-asc">Title: A → Z</option>
                            <option value="issues-desc">Most issues first</option>
                        </select>
                        <label style="font-size:13px;font-weight:600;margin-left:8px;">Filter:</label>
                        <select id="aisk-score-filter" style="min-width:150px;">
                            <option value="all">All pages</option>
                            <option value="critical">Critical (0-39)</option>
                            <option value="warning">Needs work (40-69)</option>
                            <option value="good">Good (70-100)</option>
                            <option value="skipped">Skipped pages</option>
                            <option value="not-skipped">Active (not skipped)</option>
                        </select>
                    </div>
                    <div id="aisk-s3-results"></div>
                </div>
            </details>

        </div>
    </div>

    <!-- SKIP RULES (visible once index exists, affects both metadata and audits) -->
    <div id="aisk-skip-section" class="aisk-step" style="<?php echo ! $has_index ? 'display:none;' : ''; ?>">
        <details id="aisk-tab-skip" style="border:1px solid #dcdcde;border-radius:4px;background:#fff;">
            <summary style="cursor:pointer;padding:14px 18px;font-weight:600;font-size:14px;background:#f6f7f7;border-radius:4px 4px 0 0;user-select:none;">
                &#128683; <?php esc_html_e('Skip Rules', 'ai-seo-keeper'); ?>
                <span id="aisk-tab-skip-count" style="font-weight:400;font-size:13px;color:#787c82;margin-left:8px;"></span>
            </summary>
            <div style="padding:18px;">
                <p style="font-size:13px;margin:0 0 12px;color:#50575e;">
                    <?php esc_html_e('Pages skipped here are excluded from both SEO Metadata generation and Full SEO Audits.', 'ai-seo-keeper'); ?>
                    <?php esc_html_e('Use the skip button on any page card to skip/unskip individually, or define path patterns below.', 'ai-seo-keeper'); ?>
                </p>
                <div style="margin-bottom:16px;">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;"><?php esc_html_e('Path patterns (one per line):', 'ai-seo-keeper'); ?></label>
                    <textarea id="aisk-skip-patterns" rows="5" style="width:100%;max-width:500px;font-family:monospace;font-size:13px;" placeholder="/experiments/*&#10;/test-pages/**&#10;/landing/*/thank-you"><?php echo esc_textarea($skip_patterns); ?></textarea>
                    <p style="font-size:12px;color:#787c82;margin:6px 0 0;">
                        <?php esc_html_e('Use * to match one path segment, ** to match any depth. Lines starting with # are comments.', 'ai-seo-keeper'); ?><br>
                        <?php esc_html_e('Examples:', 'ai-seo-keeper'); ?> <code>/experiments/*</code> <?php esc_html_e('skips all direct children', 'ai-seo-keeper'); ?> &bull; <code>/experiments/**</code> <?php esc_html_e('skips all descendants', 'ai-seo-keeper'); ?> &bull; <code>/*/thank-you</code> <?php esc_html_e('skips any "thank-you" page.', 'ai-seo-keeper'); ?>
                    </p>
                </div>
                <div style="display:flex;gap:10px;align-items:center;">
                    <button id="aisk-btn-save-patterns" class="button button-primary" type="button"><?php esc_html_e('Save Patterns', 'ai-seo-keeper'); ?></button>
                    <span id="aisk-patterns-feedback" style="font-size:13px;color:#00a32a;display:none;"></span>
                </div>
                <div id="aisk-skip-list" style="margin-top:16px;">
                    <h4 style="margin:0 0 8px;"><?php esc_html_e('Individually Skipped Pages:', 'ai-seo-keeper'); ?></h4>
                    <div id="aisk-skipped-pages-list" style="font-size:13px;color:#50575e;"></div>
                </div>
            </div>
        </details>
    </div>

    <!-- DATA MANAGEMENT -->
    <div class="aisk-data-management" style="margin-top:30px;padding:20px;background:#fff;border:1px solid #dcdcde;border-radius:4px;">
        <h3 style="margin:0 0 8px;font-size:15px;color:#50575e;">
            <span class="dashicons dashicons-database-remove" style="margin-right:4px;"></span>
            <?php esc_html_e('Data Management', 'ai-seo-keeper'); ?>
        </h3>
        <p style="font-size:13px;color:#787c82;margin:0 0 16px;">
            <?php esc_html_e('Clear generated SEO data from the database. This cannot be undone — you will need to re-run the affected steps.', 'ai-seo-keeper'); ?>
        </p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <button type="button" class="button aisk-clear-data-btn" data-scope="metadata" style="color:#b32d2e;border-color:#b32d2e;">
                <?php esc_html_e('Clear All Metadata', 'ai-seo-keeper'); ?>
            </button>
            <button type="button" class="button aisk-clear-data-btn" data-scope="audits" style="color:#b32d2e;border-color:#b32d2e;">
                <?php esc_html_e('Clear All Audits', 'ai-seo-keeper'); ?>
            </button>
            <button type="button" class="button aisk-clear-data-btn" data-scope="all" style="color:#fff;background:#b32d2e;border-color:#b32d2e;">
                <?php esc_html_e('Clear Everything (Metadata + Audits + Lists)', 'ai-seo-keeper'); ?>
            </button>
            <span id="aisk-clear-feedback" style="font-size:13px;color:#00a32a;display:none;"></span>
        </div>
    </div>
</div>

<script type="text/javascript">
    (function($) {
        var nonce = <?php echo wp_json_encode(wp_create_nonce('ai_seo_keeper_setup_wizard')); ?>;
        var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
        var publishedIds = <?php echo $published_ids_json; ?>;
        var skippedIds = <?php echo $skipped_ids_json; ?>;
        var runsData = <?php echo wp_json_encode($runs_with_status); ?>;
        var step2AllDone = <?php echo $step2_all_done ? 'true' : 'false'; ?>;
        var step3AllDone = <?php echo $step3_all_done ? 'true' : 'false'; ?>;
        var hasWooProducts = <?php echo (class_exists('WooCommerce') && (int) wp_count_posts('product')->publish > 0) ? 'true' : 'false'; ?>;

        // ── Helpers ──────────────────────────────────────────────────

        function unlockStep(num) {
            $('#aisk-step-' + num).removeClass('locked');
            $('#aisk-s' + num + '-badge').removeClass('pending').addClass('active');
            var btnId = num === 2 ? '#aisk-btn-generate' : '#aisk-btn-audit';
            $(btnId).prop('disabled', false);
        }

        function markStepDone(num) {
            $('#aisk-s' + num + '-badge').removeClass('active pending').addClass('done').text('✓');
        }

        function esc(str) {
            return $('<span>').text(str).html();
        }

        function formatTime(seconds) {
            var m = Math.floor(seconds / 60);
            var s = seconds % 60;
            return (m > 0 ? m + 'm ' : '') + s + 's';
        }

        function showError(prefix, msg) {
            $(prefix + '-error').html('<strong>Error:</strong> ' + esc(msg) +
                ' <br><small style="color:#787c82;">Check your AI provider API key and quota in <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-keeper-settings')); ?>">Settings</a>. ' +
                'Common causes: invalid API key, rate limit exceeded, provider outage, or network timeout.</small>'
            ).show();
        }

        // ── Timer helper ──────────────────────────────────────────────

        function createTimer(displayEl) {
            var startTime = null;
            var interval = null;
            return {
                start: function() {
                    startTime = Date.now();
                    var self = this;
                    interval = setInterval(function() {
                        self.update();
                    }, 1000);
                },
                pause: function() {
                    if (interval) clearInterval(interval);
                },
                resume: function() {
                    var self = this;
                    interval = setInterval(function() {
                        self.update();
                    }, 1000);
                },
                stop: function() {
                    if (interval) clearInterval(interval);
                },
                update: function() {
                    if (!startTime) return;
                    var elapsed = Math.floor((Date.now() - startTime) / 1000);
                    $(displayEl).text('⏱ ' + formatTime(elapsed));
                },
                getText: function() {
                    if (!startTime) return '';
                    return formatTime(Math.floor((Date.now() - startTime) / 1000));
                }
            };
        }

        // ── Batch processor (reusable for Steps 2 and 3) ──────────────

        /**
         * Dynamically add a run badge to the runs summary under a step.
         * If the runs-summary container doesn't exist yet, creates it.
         */
        function addRunBadge(stepPrefix, runId, name, isDone) {
            var containerId = stepPrefix + '-runs';
            var $container = $('#' + containerId);
            if ($container.length === 0) {
                // Create the runs summary container after the error banner.
                var titleLabel = (stepPrefix === 'aisk-s2') ? 'Lists (Metadata)' : 'Lists (Audit)';
                $container = $('<div class="aisk-runs-summary" id="' + containerId + '">' +
                    '<h4 class="aisk-runs-title"><span class="dashicons dashicons-list-view"></span> ' + esc(titleLabel) + '</h4>' +
                    '</div>');
                $('#' + stepPrefix + '-error').after($container);
            }
            // Remove existing badge for same run (if re-created).
            $container.find('.aisk-run-badge[data-run-id="' + runId + '"]').remove();
            var statusClass = isDone ? 'is-complete' : 'is-pending';
            var statusLabel = isDone ? 'Done' : 'Pending';
            var checkmark = isDone ? ' <span class="dashicons dashicons-yes-alt"></span>' : '';
            var badge = $('<div class="aisk-run-badge ' + statusClass + '" data-run-id="' + runId + '">' +
                '<strong>' + esc(name) + '</strong> ' +
                '<span>' + statusLabel + '</span>' +
                checkmark +
                ' <button type="button" class="aisk-run-delete" data-run-id="' + runId + '" title="Delete list">&times;</button>' +
                '</div>');
            $container.append(badge);
        }

        function markRunBadgeDone(stepPrefix, runId) {
            var $badge = $('#' + stepPrefix + '-runs .aisk-run-badge[data-run-id="' + runId + '"]');
            if ($badge.length) {
                $badge.removeClass('is-pending is-partial').addClass('is-complete');
                $badge.find('span').first().text('Done');
                if (!$badge.find('.dashicons-yes-alt').length) {
                    $badge.find('.aisk-run-delete').before(' <span class="dashicons dashicons-yes-alt"></span> ');
                }
            }
            // Update runsData so redo modal reflects new status.
            for (var i = 0; i < runsData.length; i++) {
                if (parseInt(runsData[i].id, 10) === runId) {
                    var step = (stepPrefix === 'aisk-s2') ? 'metadata' : 'audit';
                    var steps = (runsData[i].completed_steps || '').split(',').filter(Boolean);
                    if (steps.indexOf(step) === -1) steps.push(step);
                    runsData[i].completed_steps = steps.join(',');
                    break;
                }
            }
        }

        var LARGE_SITE_THRESHOLD = <?php echo (int) $max_pages; ?>;

        /**
         * Show a proper modal for large-site operations.
         * Shows "Redo" section for existing lists / full-site, and "New" for fresh processing.
         *
         * @param {string} operationName e.g. "SEO Metadata Generation"
         * @param {number} pageCount Number of pages to process
         * @param {number} secondsPerPage Estimated seconds per page
         * @param {string} stepType 'metadata' or 'audit'
         * @param {function} onConfirm Called with page IDs array (all or subset)
         */
        function confirmLargeOperation(operationName, pageCount, secondsPerPage, stepType, onConfirm) {
            // Always show the modal so the user can choose Lists vs Full Site.
            var fullSiteDone = (stepType === 'metadata') ? step2AllDone : step3AllDone;
            var hasExistingRuns = runsData && runsData.length > 0;

            var estMinutes = Math.ceil((pageCount * secondsPerPage) / 60);
            var timeStr = estMinutes > 120 ?
                '~' + (estMinutes / 60).toFixed(1) + ' hours' :
                '~' + estMinutes + ' minutes';

            // Build "Redo" section items.
            var redoHtml = '';
            if (fullSiteDone || hasExistingRuns) {
                redoHtml += '<div class="aisk-modal__section-label">Redo:</div>';
                redoHtml += '<div class="aisk-modal__redo-list">';
                if (fullSiteDone) {
                    redoHtml += '<button type="button" class="aisk-modal__redo-item" data-action="redo-full">' +
                        '<span class="dashicons dashicons-admin-site-alt3"></span>' +
                        '<span class="aisk-modal__redo-info">' +
                        '<strong>Full Site</strong>' +
                        '<small>' + publishedIds.length + ' pages &middot; Re-run ' + esc(operationName) + '</small>' +
                        '</span>' +
                        '<span class="aisk-modal__redo-badge is-complete">&#10003; Done</span>' +
                        '</button>';
                }
                if (hasExistingRuns) {
                    for (var r = 0; r < runsData.length; r++) {
                        var run = runsData[r];
                        var steps = (run.completed_steps || '').split(',');
                        var stepDone = steps.indexOf(stepType) !== -1;
                        var statusClass = stepDone ? 'is-complete' : 'is-pending';
                        var statusLabel = stepDone ? '&#10003; Done' : 'Pending';
                        redoHtml += '<button type="button" class="aisk-modal__redo-item" data-action="redo-run" data-run-id="' + parseInt(run.id, 10) + '">' + '<span class="dashicons dashicons-list-view"></span>' + '<span class="aisk-modal__redo-info">' + '<strong>' + esc(run.name) + '</strong>' + '<small>' + parseInt(run.page_count, 10) + ' pages &middot; Re-run ' + esc(operationName) + '</small>' + '</span>' + '<span class="aisk-modal__redo-badge ' + statusClass + '">' + statusLabel + '</span>' + '</button>';
                    }
                }
                redoHtml += '</div>';
            }

            // Build the modal.
            var $overlay = $('<div class="aisk-modal-overlay"></div>');
            var $modal = $(
                '<div class="aisk-modal">' +
                '<div class="aisk-modal__header">' +
                '<h2>' + esc(operationName) + '</h2>' +
                '<button type="button" class="aisk-modal__close" title="Close">&times;</button>' +
                '</div>' +
                '<div class="aisk-modal__body">' +
                '<div class="aisk-modal__info-banner">' +
                '<span class="dashicons dashicons-info-outline"></span>' +
                '<div>' +
                '<strong>Your site has ' + pageCount.toLocaleString() + ' pages</strong><br>' +
                'Estimated: ~' + pageCount.toLocaleString() + ' API calls &middot; ' + timeStr +
                '</div>' +
                '</div>' +
                '<p class="aisk-modal__prompt">How would you like to proceed?</p>' +
                redoHtml +
                '<div class="aisk-modal__section-label">New:</div>' +
                '<div class="aisk-modal__options">' +
                '<button type="button" class="aisk-modal__option-btn is-primary" data-action="all">' +
                '<span class="dashicons dashicons-admin-site-alt3"></span>' +
                '<span><strong>Process All Pages</strong><br><small>Run ' + esc(operationName) + ' on all ' + pageCount.toLocaleString() + ' pages</small></span>' +
                '</button>' +
                '<button type="button" class="aisk-modal__option-btn is-secondary" data-action="list">' +
                '<span class="dashicons dashicons-list-view"></span>' +
                '<span><strong>Create a List</strong><br><small>Select specific pages and save as a reusable list</small></span>' +
                '</button>' +
                '</div>' +
                '<div class="aisk-modal__list-panel" style="display:none;">' +
                '<div class="aisk-modal__list-header">' +
                '<label>List Name: <input type="text" class="aisk-modal__list-name" placeholder="e.g. Priority Pages, Blog Posts..." maxlength="100" /></label>' +
                '</div>' +
                '<div class="aisk-modal__search-bar">' +
                '<input type="text" class="aisk-modal__search" placeholder="Search pages..." />' +
                '<div class="aisk-modal__filters">' +
                '<button type="button" class="button aisk-modal__filter is-active" data-filter="all">All</button>' +
                '<button type="button" class="button aisk-modal__filter" data-filter="page">Pages</button>' +
                '<button type="button" class="button aisk-modal__filter" data-filter="post">Posts</button>' +
                (hasWooProducts ? '<button type="button" class="button aisk-modal__filter" data-filter="product">Products</button>' : '') +
                '</div>' +
                '<div class="aisk-modal__bulk">' +
                '<button type="button" class="button-link aisk-modal__select-all">Select All</button>' +
                ' | <button type="button" class="button-link aisk-modal__deselect-all">Deselect All</button>' +
                ' <span class="aisk-modal__count">0 selected</span>' +
                '</div>' +
                '</div>' +
                '<div class="aisk-modal__page-list"><div class="aisk-modal__loading">Loading pages...</div></div>' +
                '<div class="aisk-modal__list-footer">' +
                '<button type="button" class="button aisk-modal__back">&larr; Back</button>' +
                '<button type="button" class="button button-primary aisk-modal__create-list" disabled>Create List &amp; Process</button>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>'
            );

            $('body').append($overlay).append($modal);

            // Focus trap
            setTimeout(function() {
                $overlay.addClass('is-visible');
                $modal.addClass('is-visible');
            }, 10);

            function closeModal() {
                $overlay.removeClass('is-visible');
                $modal.removeClass('is-visible');
                setTimeout(function() {
                    $overlay.remove();
                    $modal.remove();
                }, 200);
            }

            $overlay.on('click', closeModal);
            $modal.find('.aisk-modal__close').on('click', closeModal);

            // ── Redo: Full site ──
            $modal.on('click', '[data-action="redo-full"]', function() {
                closeModal();
                onConfirm({
                    ids: publishedIds,
                    runId: null
                });
            });

            // ── Redo: Existing list ──
            $modal.on('click', '[data-action="redo-run"]', function() {
                var redoRunId = parseInt($(this).data('run-id'), 10);
                for (var i = 0; i < runsData.length; i++) {
                    if (parseInt(runsData[i].id, 10) === redoRunId) {
                        var pageIds = runsData[i].page_ids;
                        if (typeof pageIds === 'string') {
                            pageIds = JSON.parse(pageIds);
                        }
                        closeModal();
                        onConfirm({
                            ids: pageIds,
                            runId: redoRunId
                        });
                        return;
                    }
                }
            });

            // ── New: Process All ──
            $modal.find('[data-action="all" ]').on('click', function() {
                closeModal();
                onConfirm({
                    ids: publishedIds,
                    runId: null
                });
            });

            // ── New: Create a List ──
            var allPages = [];
            var loadedPages = false;

            $modal.find('[data-action="list" ]').on('click', function() {
                $modal.find('.aisk-modal__options').slideUp(200);
                $modal.find('.aisk-modal__redo-list').slideUp(200);
                $modal.find('.aisk-modal__section-label').slideUp(200);
                $modal.find('.aisk-modal__prompt').slideUp(200);
                $modal.find('.aisk-modal__list-panel').slideDown(300);
                $modal.find('.aisk-modal__list-name').focus();

                if (!loadedPages) {
                    loadedPages = true;
                    $.post(ajaxUrl, {
                        action: 'ai_seo_keeper_get_pages_for_selector',
                        nonce: nonce
                    }, function(response) {
                        if (response.success) {
                            allPages = response.data.pages;
                            renderPageList(allPages);
                        } else {
                            $modal.find('.aisk-modal__page-list').html('<div class="aisk-modal__loading" style="color:#d63638;">Failed to load pages.</div>');
                        }
                    });
                }
            });

            // Render page checkboxes
            function renderPageList(pages) {
                var $list = $modal.find('.aisk-modal__page-list');
                if (pages.length === 0) {
                    $list.html('<div class="aisk-modal__loading">No pages match your filter.</div>');
                    return;
                }
                var html = '';
                for (var i = 0; i < pages.length; i++) {
                    var p = pages[i];
                    var auditBadge = parseInt(p.has_audit, 10) ? '<span class="aisk-modal__badge is-good">Audited</span>' : '<span class="aisk-modal__badge is-pending">Not audited</span>';
                    html += '<label class="aisk-modal__page-row" data-type="' + esc(p.post_type) + '">' + '<input type="checkbox" value="' + parseInt(p.id, 10) + '" /> ' + '<span class="aisk-modal__page-title">' + esc(p.title) + '</span>' + '<span class="aisk-modal__page-meta">' + esc(p.post_type) + ' &middot; /' + esc(p.slug) + '</span>' +
                        auditBadge + '</label>';
                }
                $list.html(html);
            }

            // Search filter
            $modal.on('input', '.aisk-modal__search', function() {
                var term = $(this).val().toLowerCase();
                $modal.find('.aisk-modal__page-row').each(function() {
                    var text = $(this).text().toLowerCase();
                    $(this).toggle(text.indexOf(term) !== -1);
                });
            });

            // Post type filter
            $modal.on('click', '.aisk-modal__filter', function() {
                $modal.find('.aisk-modal__filter').removeClass('is-active');
                $(this).addClass('is-active');
                var filter = $(this).data('filter');
                $modal.find('.aisk-modal__page-row').each(function() {
                    if (filter === 'all') {
                        $(this).show();
                    } else {
                        $(this).toggle($(this).data('type') === filter);
                    }
                });
            });

            // Select / Deselect all
            $modal.on('click', '.aisk-modal__select-all', function() {
                $modal.find('.aisk-modal__page-row:visible input[type="checkbox" ]').prop('checked', true).trigger('change');
            });
            $modal.on('click', '.aisk-modal__deselect-all', function() {
                $modal.find('.aisk-modal__page-row input[type="checkbox" ]').prop('checked', false).trigger('change');
            });

            // Count selected
            $modal.on('change', 'input[type="checkbox"]', function() {
                var count = $modal.find('.aisk-modal__page-row input:checked').length;
                $modal.find('.aisk-modal__count').text(count + ' selected');
                var hasName = $.trim($modal.find('.aisk-modal__list-name').val()).length > 0;
                $modal.find('.aisk-modal__create-list').prop('disabled', count === 0 || !hasName);
            });

            // List name validation
            $modal.on('input', '.aisk-modal__list-name', function() {
                var count = $modal.find('.aisk-modal__page-row input:checked').length;
                var hasName = $.trim($(this).val()).length > 0;
                $modal.find('.aisk-modal__create-list').prop('disabled', count === 0 || !hasName);
            });

            // Back button
            $modal.on('click', '.aisk-modal__back', function() {
                $modal.find('.aisk-modal__list-panel').slideUp(200);
                $modal.find('.aisk-modal__options').slideDown(300);
                $modal.find('.aisk-modal__redo-list').slideDown(300);
                $modal.find('.aisk-modal__section-label').slideDown(300);
                $modal.find('.aisk-modal__prompt').slideDown(300);
            });

            // Create List & Process
            $modal.on('click', '.aisk-modal__create-list', function() {
                var $btn = $(this);
                var name = $.trim($modal.find('.aisk-modal__list-name').val());
                var selectedIds = [];
                $modal.find('.aisk-modal__page-row input:checked').each(function() {
                    selectedIds.push(parseInt($(this).val(), 10));
                });

                if (!name || selectedIds.length === 0) return;

                $btn.prop('disabled', true).text('Creating...');

                $.post(ajaxUrl, {
                    action: 'ai_seo_keeper_create_run',
                    nonce: nonce,
                    name: name,
                    page_ids: JSON.stringify(selectedIds)
                }, function(response) {
                    if (response.success) {
                        var rd = response.data;
                        // Add to runsData for redo modal.
                        runsData.push({
                            id: rd.run_id,
                            name: rd.name,
                            page_count: rd.page_count,
                            page_ids: rd.page_ids,
                            completed_steps: ''
                        });
                        // Add badges to both steps.
                        addRunBadge('aisk-s2', rd.run_id, rd.name, false);
                        addRunBadge('aisk-s3', rd.run_id, rd.name, false);
                        closeModal();
                        onConfirm({
                            ids: selectedIds,
                            runId: rd.run_id
                        });
                    } else {
                        $btn.prop('disabled', false).text('Create List & Process');
                        alert(response.data.message || 'Error creating list.');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('Create List & Process');
                    alert('Network error. Please try again.');
                });
            });
        }

        function BatchProcessor(config) {
            this.ids = config.ids;
            this.ajaxAction = config.ajaxAction;
            this.prefix = config.prefix; // '#aisk-s2' or '#aisk-s3'
            this.btnStart = config.btnStart;
            this.btnPause = config.btnPause;
            this.btnStop = config.btnStop;
            this.onItem = config.onItem; // function(response, postId)
            this.onDone = config.onDone; // function(stats)
            this.onError = config.onError; // function(postId, msg)
            this.timer = createTimer(config.timerEl);

            this.current = 0;
            this.stats = {
                processed: 0,
                skipped: 0,
                cached: 0,
                errors: 0
            };
            this.state = 'idle'; // idle | running | paused | stopped | done
            this.consecutiveErrors = 0;
        }

        BatchProcessor.prototype.start = function() {
            var self = this;
            this.state = 'running';
            this.timer.start();

            $(this.prefix + '-progress').show();
            $(this.prefix + '-paused').hide();
            $(this.prefix + '-stopped').hide();
            $(this.prefix + '-error').hide();
            $(this.btnStart).prop('disabled', true).text('Processing...');
            $(this.btnPause).show();
            $(this.btnStop).show();

            // Pause button
            $(this.btnPause).off('click').on('click', function() {
                if (self.state === 'running') {
                    self.state = 'paused';
                    self.timer.pause();
                    $(self.btnPause).html('&#9654; Resume');
                    $(self.prefix + '-paused-info').text(
                        self.stats.processed + ' processed, ' + self.stats.skipped + ' skipped, ' +
                        self.stats.errors + ' errors so far.'
                    );
                    $(self.prefix + '-paused').show();
                    $(self.prefix + '-status').text('Paused at ' + self.current + ' of ' + self.ids.length);
                } else if (self.state === 'paused') {
                    self.state = 'running';
                    self.timer.resume();
                    $(self.btnPause).html('&#10074;&#10074; Pause');
                    $(self.prefix + '-paused').hide();
                    self.processNext();
                }
            });

            // Stop button
            $(this.btnStop).off('click').on('click', function() {
                self.state = 'stopped';
                self.timer.stop();
                $(self.btnPause).hide();
                $(self.btnStop).hide();
                $(self.prefix + '-stopped-info').text(
                    self.current + ' of ' + self.ids.length + ' pages processed. ' +
                    self.stats.processed + ' new, ' + self.stats.skipped + ' skipped, ' +
                    self.stats.errors + ' errors.'
                );
                $(self.prefix + '-stopped').show();
                $(self.btnStart).prop('disabled', false).text('Continue');
            });

            this.processNext();
        };

        BatchProcessor.prototype.processNext = function() {
            if (this.state === 'paused' || this.state === 'stopped') return;

            if (this.current >= this.ids.length) {
                this.finish();
                return;
            }

            // Abort after 5 consecutive API errors.
            if (this.consecutiveErrors >= 5) {
                this.timer.stop();
                $(this.btnPause).hide();
                $(this.btnStop).hide();
                showError(this.prefix,
                    '5 consecutive API errors. Processing stopped. Last ' +
                    this.stats.errors + ' pages failed. Please check your API key and provider status.'
                );
                $(this.btnStart).prop('disabled', false).text('Retry');
                this.state = 'stopped';
                return;
            }

            var self = this;
            var postId = this.ids[this.current];
            var pct = Math.round(((this.current + 1) / this.ids.length) * 100);
            $(this.prefix + '-bar').css('width', pct + '%');
            $(this.prefix + '-status').text('Processing ' + (this.current + 1) + ' of ' + this.ids.length + '...');
            $(this.prefix + '-counts').text(
                '✓ ' + this.stats.processed + ' ⏭ ' + this.stats.skipped +
                (this.stats.cached > 0 ? ' 📋 ' + this.stats.cached : '') +
                ' ✗ ' + this.stats.errors
            );

            $.post(ajaxUrl, {
                action: this.ajaxAction,
                nonce: nonce,
                post_id: postId
            }, function(response) {
                self.consecutiveErrors = 0;
                if (response.success) {
                    if (response.data.skipped) {
                        self.stats.skipped++;
                    } else if (response.data.cached) {
                        self.stats.cached++;
                    } else {
                        self.stats.processed++;
                    }
                    self.onItem(response, postId);
                } else {
                    self.stats.errors++;
                    self.consecutiveErrors++;
                    var msg = response.data && response.data.message ? response.data.message : 'Unknown error';
                    var title = response.data && response.data.title ? response.data.title : 'Post #' + postId;
                    self.onError(postId, title, msg);
                }
                self.current++;
                self.processNext();
            }).fail(function(jqXHR, textStatus) {
                self.stats.errors++;
                self.consecutiveErrors++;
                var detail = textStatus === 'timeout' ? 'Request timed out' : 'Network error (' + textStatus + ')';
                self.onError(postId, 'Post #' + postId, detail);
                self.current++;
                self.processNext();
            });
        };

        BatchProcessor.prototype.finish = function() {
            this.state = 'done';
            this.timer.stop();
            $(this.prefix + '-bar').css('width', '100%');
            $(this.btnPause).hide();
            $(this.btnStop).hide();
            this.onDone(this.stats);
        };

        // ── STEP 1: Index ─────────────────────────────────────────────

        <?php if ($has_index) : ?>
            markStepDone(1);
            $('#aisk-s1-done').show();
            $('#aisk-s1-result').text('<?php echo esc_js((string) $summary['total_items']); ?> pages indexed.');
            unlockStep(2);
            $('#aisk-skip-section').show();
        <?php endif; ?>
        <?php if ($has_index && $has_metadata) : ?>
            <?php if ($step2_all_done) : ?>
                markStepDone(2);
                $('#aisk-s2-done').show();
                $('#aisk-s2-result').text('All <?php echo (int) $total_pages; ?> pages have metadata.');
                $('#aisk-btn-generate').text('Re-Generate All');
            <?php endif; ?>
            unlockStep(3);
        <?php endif; ?>
        <?php if ($step3_all_done) : ?>
            markStepDone(3);
            $('#aisk-s3-done').show();
            $('#aisk-s3-result').text('All <?php echo (int) $total_pages; ?> pages audited.');
        <?php endif; ?>

        // ── Delete list handler (delegated) ───────────────────────
        $(document).on('click', '.aisk-run-delete', function(e) {
            e.stopPropagation();
            var btn = $(this);
            var runId = parseInt(btn.data('run-id'), 10);
            if (!confirm('Delete this list? The pages and their SEO data will NOT be affected.')) return;
            btn.prop('disabled', true).text('…');
            $.post(ajaxUrl, {
                action: 'ai_seo_keeper_delete_run',
                nonce: nonce,
                run_id: runId
            }, function(response) {
                if (response.success) {
                    // Remove from both Step 2 and Step 3 lists.
                    $('.aisk-run-badge[data-run-id="' + runId + '"]').fadeOut(300, function() {
                        $(this).remove();
                    });
                    // Remove from runsData array.
                    runsData = runsData.filter(function(r) {
                        return parseInt(r.id, 10) !== runId;
                    });
                } else {
                    btn.prop('disabled', false).text('×');
                }
            }).fail(function() {
                btn.prop('disabled', false).text('×');
            });
        });

        $('#aisk-btn-index').on('click', function() {
            var btn = $(this);
            btn.prop('disabled', true).text('Indexing...');
            $('#aisk-s1-progress').show();
            $('#aisk-s1-done').hide();
            $('#aisk-s1-error').hide();
            $('#aisk-s1-bar').css('width', '50%');
            $('#aisk-s1-status').text('Scanning pages...');

            $.post(ajaxUrl, {
                action: <?php echo wp_json_encode($ajax_setup_index_action); ?>,
                nonce: nonce
            }, function(response) {
                $('#aisk-s1-bar').css('width', '100%');
                if (response.success) {
                    publishedIds = response.data.publishedIds || [];
                    $('#aisk-s1-status').text('Done.');
                    $('#aisk-s1-done').show();
                    $('#aisk-s1-result').text(response.data.count + ' pages indexed.');
                    markStepDone(1);
                    unlockStep(2);
                    $('#aisk-skip-section').show();
                } else {
                    showError('#aisk-s1', response.data && response.data.message ? response.data.message : 'Unknown error');
                    btn.prop('disabled', false).text('Retry Indexing');
                }
            }).fail(function(jqXHR, textStatus) {
                showError('#aisk-s1', 'Network error (' + textStatus + '). Please check your connection and try again.');
                btn.prop('disabled', false).text('Retry Indexing');
            });
        });

        // ── STEP 2: Bulk Generate ────────────────────────────────────

        var s2processor = null;

        $('#aisk-btn-generate').on('click', function() {
            var self = this;
            confirmLargeOperation('SEO Metadata Generation', publishedIds.length, 3, 'metadata', function(result) {
                var idsToUse = result.ids;
                var s2RunId = result.runId;
                $('#aisk-s2-log').show();
                $('#aisk-s2-done').hide();
                $('#aisk-s2-stopped').hide();
                $('#aisk-s2-paused').hide();

                s2processor = new BatchProcessor({
                    ids: idsToUse,
                    ajaxAction: <?php echo wp_json_encode($ajax_bulk_generate_action); ?>,
                    prefix: '#aisk-s2',
                    btnStart: '#aisk-btn-generate',
                    btnPause: '#aisk-btn-s2-pause',
                    btnStop: '#aisk-btn-s2-stop',
                    timerEl: '#aisk-s2-elapsed',
                    onItem: function(response) {
                        var d = response.data;
                        if (d.skipped) {
                            $('#aisk-s2-log').prepend('<div class="aisk-log-entry" style="color:#50575e;">⏭ <strong>' + esc(d.title) + '</strong> — skipped (already has metadata)</div>');
                        } else {
                            $('#aisk-s2-log').prepend('<div class="aisk-log-entry" style="color:#00a32a;">✓ <strong>' + esc(d.title) + '</strong> — ' + esc(d.seo_title) + '</div>');
                        }
                    },
                    onDone: function(stats) {
                        $('#aisk-s2-status').text('Done in ' + s2processor.timer.getText() + '.');
                        $('#aisk-s2-done').show();
                        $('#aisk-s2-result').text(stats.processed + ' generated, ' + stats.skipped + ' skipped, ' + stats.errors + ' errors.');
                        $('#aisk-btn-generate').prop('disabled', false).text('Re-Generate All');
                        markStepDone(2);
                        unlockStep(3);
                        if (s2RunId) {
                            markRunBadgeDone('aisk-s2', s2RunId);
                            $.post(ajaxUrl, {
                                action: 'ai_seo_keeper_mark_run_step',
                                nonce: nonce,
                                run_id: s2RunId,
                                step: 'metadata'
                            });
                        }
                    },
                    onError: function(postId, title, msg) {
                        $('#aisk-s2-log').prepend('<div class="aisk-log-entry" style="color:#d63638;">✗ <strong>' + esc(title) + '</strong> — ' + esc(msg) + '</div>');
                    }
                });

                s2processor.start();
            }); // end confirmLargeOperation
        });

        // ── STEP 3: Page Audits ──────────────────────────────────────

        var s3processor = null;
        var allAudits = <?php echo $existing_audits_json; ?>;

        function scoreColor(score) {
            return score >= 70 ? '#00a32a' : (score >= 40 ? '#dba617' : '#d63638');
        }

        function scoreBadge(score) {
            return '<span style="display:inline-block;min-width:36px;text-align:center;padding:2px 6px;border-radius:3px;color:#fff;font-weight:700;font-size:13px;background:' + scoreColor(score) + ';">' + score + '</span>';
        }

        function renderAuditCard(d) {
            var isSkipped = d.audit_skipped || skippedIds.indexOf(d.post_id) !== -1;
            var cachedTag = d.cached ? ' <span style="font-size:11px;color:#787c82;font-weight:400;">(cached)</span>' : '';
            var skipBadge = isSkipped ? ' <span class="aisk-skip-badge" style="font-size:11px;background:#f0c33c;color:#3c2300;padding:1px 6px;border-radius:3px;font-weight:600;">SKIPPED</span>' : '';
            var skipBtnLabel = isSkipped ? '&#9654; Unskip' : '&#128683; Skip';
            var skipBtnColor = isSkipped ? '#2271b1' : '#b32d2e';
            var html = '<div class="aisk-audit-card" data-score="' + d.score + '" data-title="' + esc(d.title) + '" data-issues="' + (d.issues ? d.issues.length : 0) + '" data-postid="' + d.post_id + '" data-skipped="' + (isSkipped ? '1' : '0') + '"' + (isSkipped ? ' style="opacity:0.6;"' : '') + '>';
            html += '<div class="aisk-audit-header">';
            html += '<div><strong style="font-size:14px;">' + esc(d.title) + '</strong>' + cachedTag + skipBadge;
            html += ' <a href="' + esc(d.permalink) + '" target="_blank" style="font-size:12px;margin-left:6px;">View ↗</a></div>';
            html += '<div style="display:flex;align-items:center;gap:10px;">';
            html += '<button type="button" class="aisk-skip-toggle button-link" data-postid="' + d.post_id + '" style="font-size:12px;color:' + skipBtnColor + ';cursor:pointer;white-space:nowrap;">' + skipBtnLabel + '</button>';
            html += '<div class="aisk-audit-score" style="color:' + scoreColor(d.score) + ';">' + d.score + '<span style="font-size:13px;font-weight:400;">/100</span></div>';
            html += '</div></div>';
            if (d.summary) html += '<p style="margin:8px 0 4px;color:#50575e;font-size:13px;">' + esc(d.summary) + '</p>';
            html += '<p style="margin:4px 0;font-size:12px;color:#787c82;">';
            if (d.heading_structure) html += 'Headings: ' + esc(d.heading_structure) + ' · ';
            html += 'Words: ' + d.word_count + ' · Missing alt: ' + d.missing_alt_tags + '</p>';
            if (d.issues && d.issues.length > 0) {
                html += '<details style="margin-top:8px;"><summary style="cursor:pointer;font-weight:600;color:#d63638;font-size:13px;">Issues(' + d.issues.length + ')</summary><ul style="margin:4px 0 0 16px;padding:0;">';
                for (var i = 0; i < d.issues.length; i++) html += '<li style="font-size:13px;margin:2px 0;">' + esc(d.issues[i]) + '</li>';
                html += '</ul></details>';
            }
            if (d.suggestions && d.suggestions.length > 0) {
                html += '<details style="margin-top:6px;"><summary style="cursor:pointer;font-weight:600;color:#2271b1;font-size:13px;">Suggestions(' + d.suggestions.length + ')</summary><ul style="margin:4px 0 0 16px;padding:0;">';
                for (var i = 0; i < d.suggestions.length; i++) html += '<li style="font-size:13px;margin:2px 0;">' + esc(d.suggestions[i]) + '</li>';
                html += '</ul></details>';
            }
            html += '</div>';
            return html;
        }

        function addOrUpdateAudit(d) {
            var found = false;
            for (var i = 0; i < allAudits.length; i++) {
                if (allAudits[i].post_id === d.post_id) {
                    allAudits[i] = d;
                    found = true;
                    break;
                }
            }
            if (!found) allAudits.push(d);
        }

        function refreshSummaryTab() {
            if (allAudits.length === 0) {
                $('#aisk-s3-tabs').hide();
                return;
            }
            $('#aisk-s3-tabs').show();

            var sorted = allAudits.slice().sort(function(a, b) {
                return b.score - a.score;
            });
            var totalIssues = 0;
            var good = 0,
                warning = 0,
                critical = 0,
                totalScore = 0;
            for (var i = 0; i < sorted.length; i++) {
                totalScore += sorted[i].score;
                totalIssues += (sorted[i].issues ? sorted[i].issues.length : 0);
                if (sorted[i].score >= 70) good++;
                else if (sorted[i].score >= 40) warning++;
                else critical++;
            }
            var avg = Math.round(totalScore / sorted.length);

            $('#aisk-tab-summary-count').text('(' + sorted.length + ' pages)');
            $('#aisk-tab-details-count').text('(' + sorted.length + ' pages)');

            // Score distribution cards
            $('#aisk-score-summary').html(
                '<div style="background:#f0f6fc;border:1px solid #72aee6;border-radius:6px;padding:14px 20px;text-align:center;min-width:120px;">' +
                '<div style="font-size:28px;font-weight:700;color:' + scoreColor(avg) + ';">' + avg + '</div>' +
                '<div style="font-size:12px;color:#50575e;">Average Score</div></div>' +
                '<div style="background:#edf8f1;border:1px solid #00a32a;border-radius:6px;padding:14px 20px;text-align:center;min-width:120px;">' +
                '<div style="font-size:28px;font-weight:700;color:#00a32a;">' + good + '</div>' +
                '<div style="font-size:12px;color:#50575e;">Good (70+)</div></div>' +
                '<div style="background:#fef8e7;border:1px solid #dba617;border-radius:6px;padding:14px 20px;text-align:center;min-width:120px;">' +
                '<div style="font-size:28px;font-weight:700;color:#dba617;">' + warning + '</div>' +
                '<div style="font-size:12px;color:#50575e;">Needs Work (40-69)</div></div>' +
                '<div style="background:#fcf0f1;border:1px solid #d63638;border-radius:6px;padding:14px 20px;text-align:center;min-width:120px;">' +
                '<div style="font-size:28px;font-weight:700;color:#d63638;">' + critical + '</div>' +
                '<div style="font-size:12px;color:#50575e;">Critical (&lt;40)</div></div>' +
                '<div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:6px;padding:14px 20px;text-align:center;min-width:120px;">' +
                '<div style="font-size:28px;font-weight:700;color:#50575e;">' + totalIssues + '</div>' +
                '<div style="font-size:12px;color:#50575e;">Total Issues</div></div>'
            );

            // Top 10
            var top10 = sorted.slice(0, 10);
            var top10html = '';
            for (var i = 0; i < top10.length; i++) {
                top10html += '<tr><td>' + (i + 1) + '</td><td><a href="' + esc(top10[i].permalink) + '" target="_blank">' + esc(top10[i].title) + '</a></td>' + '<td style="text-align:center;">' + scoreBadge(top10[i].score) + '</td>' + '<td style="text-align:center;">' + (top10[i].issues ? top10[i].issues.length : 0) + '</td></tr>';
            }
            $('#aisk-top10-table tbody').html(top10html);

            // Bottom 10
            var bottom10 = sorted.slice(-10).reverse();
            var bottom10html = '';
            for (var i = 0; i < bottom10.length; i++) {
                bottom10html += '<tr><td>' + (i + 1) + '</td><td><a href="' + esc(bottom10[i].permalink) + '" target="_blank">' + esc(bottom10[i].title) + '</a></td>' + '<td style="text-align:center;">' + scoreBadge(bottom10[i].score) + '</td>' + '<td style="text-align:center;">' + (bottom10[i].issues ? bottom10[i].issues.length : 0) + '</td></tr>';
            }
            $('#aisk-bottom10-table tbody').html(bottom10html);
        }

        function refreshDetailsTab() {
            var sortOrder = $('#aisk-sort-order').val();
            var filterVal = $('#aisk-score-filter').val();

            var filtered = allAudits.slice();

            // Apply filter
            if (filterVal === 'critical') filtered = filtered.filter(function(d) {
                return d.score < 40;
            });
            else if (filterVal === 'warning') filtered = filtered.filter(function(d) {
                return d.score >= 40 && d.score < 70;
            });
            else if (filterVal === 'good') filtered = filtered.filter(function(d) {
                return d.score >= 70;
            });
            else if (filterVal === 'skipped') filtered = filtered.filter(function(d) {
                return d.audit_skipped || skippedIds.indexOf(d.post_id) !== -1;
            });
            else if (filterVal === 'not-skipped') filtered = filtered.filter(function(d) {
                return !d.audit_skipped && skippedIds.indexOf(d.post_id) === -1;
            });

            // Apply sort
            if (sortOrder === 'score-asc') filtered.sort(function(a, b) {
                return a.score - b.score;
            });
            else if (sortOrder === 'score-desc') filtered.sort(function(a, b) {
                return b.score - a.score;
            });
            else if (sortOrder === 'title-asc') filtered.sort(function(a, b) {
                return a.title.localeCompare(b.title);
            });
            else if (sortOrder === 'issues-desc') filtered.sort(function(a, b) {
                return (b.issues ? b.issues.length : 0) - (a.issues ? a.issues.length : 0);
            });

            var html = '';
            if (filtered.length === 0) {
                html = '<p style="color:#787c82;font-style:italic;">No pages match the current filter.</p>';
            } else {
                for (var i = 0; i < filtered.length; i++) {
                    html += renderAuditCard(filtered[i]);
                }
            }
            $('#aisk-s3-results').html(html);
        }

        // Sort/filter change handlers
        $('#aisk-sort-order, #aisk-score-filter').on('change', function() {
            refreshDetailsTab();
        });

        // ── Skip toggle (delegated) ───────────────────────────────
        $(document).on('click', '.aisk-skip-toggle', function(e) {
            e.preventDefault();
            var btn = $(this);
            var postId = parseInt(btn.data('postid'), 10);
            btn.prop('disabled', true).text('…');
            $.post(ajaxUrl, {
                action: <?php echo wp_json_encode($ajax_toggle_audit_skip_action); ?>,
                nonce: nonce,
                post_id: postId
            }).done(function(res) {
                if (res.success) {
                    if (res.data.skipped) {
                        if (skippedIds.indexOf(postId) === -1) skippedIds.push(postId);
                    } else {
                        skippedIds = skippedIds.filter(function(id) {
                            return id !== postId;
                        });
                    }
                    // Update allAudits entry
                    for (var i = 0; i < allAudits.length; i++) {
                        if (allAudits[i].post_id === postId) {
                            allAudits[i].audit_skipped = res.data.skipped;
                            break;
                        }
                    }
                    refreshDetailsTab();
                    refreshSkipTab();
                }
            }).always(function() {
                btn.prop('disabled', false);
            });
        });

        // ── Save skip patterns ────────────────────────────────────
        $('#aisk-btn-save-patterns').on('click', function() {
            var btn = $(this);
            var patterns = $('#aisk-skip-patterns').val();
            btn.prop('disabled', true).text('Saving…');
            $('#aisk-patterns-feedback').hide();
            $.post(ajaxUrl, {
                action: <?php echo wp_json_encode($ajax_save_skip_patterns_action); ?>,
                nonce: nonce,
                patterns: patterns
            }).done(function(res) {
                if (res.success) {
                    var msg = 'Saved! ' + res.data.matched_count + ' page(s) match current patterns.';
                    $('#aisk-patterns-feedback').text(msg).show();
                }
            }).always(function() {
                btn.prop('disabled', false).text('Save Patterns');
            });
        });

        // ── Refresh Skip tab (individually skipped pages list) ────
        function refreshSkipTab() {
            var list = '';
            var skipCount = 0;
            for (var i = 0; i < allAudits.length; i++) {
                if (allAudits[i].audit_skipped || skippedIds.indexOf(allAudits[i].post_id) !== -1) {
                    skipCount++;
                    list += '<div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;border-bottom:1px solid #f0f0f0;">';
                    list += '<span>' + esc(allAudits[i].title) + '</span>';
                    list += '<button type="button" class="aisk-skip-toggle button-link" data-postid="' + allAudits[i].post_id + '" style="font-size:12px;color:#2271b1;">&#9654; Unskip</button>';
                    list += '</div>';
                }
            }
            if (skipCount === 0) list = '<em>No individually skipped pages.</em>';
            $('#aisk-skipped-pages-list').html(list);
            $('#aisk-tab-skip-count').text('(' + skipCount + ' skipped)');
        }
        refreshSkipTab();

        // Pre-load existing audits on page load
        if (allAudits.length > 0) {
            refreshSummaryTab();
            refreshDetailsTab();
        }

        $('#aisk-btn-audit').on('click', function() {
            var btn = $(this);
            var isRerun = btn.text().indexOf('Re-Run') !== -1;

            // Calculate actual IDs before confirmation so we can show accurate count.
            var idsForCount = publishedIds.filter(function(id) {
                return skippedIds.indexOf(id) === -1;
            });
            if (!isRerun && allAudits.length > 0) {
                var auditedCheck = {};
                for (var c = 0; c < allAudits.length; c++) {
                    auditedCheck[allAudits[c].post_id] = true;
                }
                idsForCount = idsForCount.filter(function(id) {
                    return !auditedCheck[id];
                });
            }

            confirmLargeOperation('Full SEO Audit', idsForCount.length, 5, 'audit', function(result) {
                var idsFromModal = result.ids;
                var s3RunId = result.runId;
                $('#aisk-s3-done').hide();
                $('#aisk-s3-stopped').hide();
                $('#aisk-s3-paused').hide();

                // If modal returned a subset (list), use those IDs directly.
                // Otherwise apply the skip/already-audited logic.
                var idsToProcess;
                if (idsFromModal.length < publishedIds.length) {
                    // User created a list — use exactly those IDs.
                    idsToProcess = idsFromModal;
                } else {
                    // "Process All" — apply skip + continue logic.
                    idsToProcess = publishedIds.filter(function(id) {
                        return skippedIds.indexOf(id) === -1;
                    });
                    if (!isRerun && allAudits.length > 0) {
                        var auditedIds = {};
                        for (var i = 0; i < allAudits.length; i++) {
                            auditedIds[allAudits[i].post_id] = true;
                        }
                        idsToProcess = idsToProcess.filter(function(id) {
                            return !auditedIds[id];
                        });
                    }
                }

                if (idsToProcess.length === 0) {
                    $('#aisk-s3-done').show();
                    $('#aisk-s3-result').text('All ' + publishedIds.length + ' pages already audited. Click "Re-Run Audits" to refresh all scores.');
                    btn.prop('disabled', false).text('Re-Run Audits');
                    markStepDone(3);
                    if (s3RunId) {
                        markRunBadgeDone('aisk-s3', s3RunId);
                        $.post(ajaxUrl, {
                            action: 'ai_seo_keeper_mark_run_step',
                            nonce: nonce,
                            run_id: s3RunId,
                            step: 'audit'
                        });
                    }
                    return;
                }

                s3processor = new BatchProcessor({
                    ids: idsToProcess,
                    ajaxAction: <?php echo wp_json_encode($ajax_page_audit_action); ?>,
                    prefix: '#aisk-s3',
                    btnStart: '#aisk-btn-audit',
                    btnPause: '#aisk-btn-s3-pause',
                    btnStop: '#aisk-btn-s3-stop',
                    timerEl: '#aisk-s3-elapsed',
                    onItem: function(response) {
                        addOrUpdateAudit(response.data);
                        refreshSummaryTab();
                        refreshDetailsTab();
                    },
                    onDone: function(stats) {
                        var total = stats.processed + stats.cached;
                        $('#aisk-s3-status').text('Done in ' + s3processor.timer.getText() + ' .');
                        $('#aisk-s3-done').show();
                        $('#aisk-s3-result').text(total + ' pages audited' +
                            (stats.cached > 0 ? ' (' + stats.cached + ' from cache)' : '') +
                            ', ' + stats.errors + ' errors. Total: ' + allAudits.length + ' pages.');
                        btn.prop('disabled', false).text('Re-Run Audits');
                        markStepDone(3);
                        refreshSummaryTab();
                        refreshDetailsTab();
                        if (s3RunId) {
                            markRunBadgeDone('aisk-s3', s3RunId);
                            $.post(ajaxUrl, {
                                action: 'ai_seo_keeper_mark_run_step',
                                nonce: nonce,
                                run_id: s3RunId,
                                step: 'audit'
                            });
                        }
                    },
                    onError: function(postId, title, msg) {
                        $('#aisk-s3-results').prepend(
                            '<div style="border:1px solid #d63638;padding:12px;margin-bottom:12px;background:#fcf0f1;border-radius:4px;">' +
                            '<strong>' + esc(title) + '</strong> — <span style="color:#d63638;">' + esc(msg) + '</span></div>'
                        );
                    }
                });

                s3processor.start();
            }); // end confirmLargeOperation
        });

        // ── Data Management: Clear SEO Data ──────────────────────────

        $('.aisk-clear-data-btn').on('click', function() {
            var $btn = $(this);
            var scope = $btn.data('scope');
            var labels = {
                metadata: 'all AI-generated SEO titles and descriptions',
                audits: 'all page audit data',
                all: 'ALL SEO data (metadata, audits, and lists)'
            };
            var warningColors = {
                metadata: '#dba617',
                audits: '#dba617',
                all: '#d63638'
            };

            // Build confirmation modal.
            var $confirmOverlay = $('<div class="aisk-modal-overlay"></div>');
            var $confirmModal = $(
                '<div class="aisk-modal" style="max-width:480px;">' +
                '<div class="aisk-modal__header" style="background:' + warningColors[scope] + ';color:#fff;">' +
                '<h2 style="color:#fff;"><span class="dashicons dashicons-warning" style="margin-right:6px;"></span> Confirm Deletion</h2>' +
                '<button type="button" class="aisk-modal__close" title="Close" style="color:#fff;">&times;</button>' +
                '</div>' +
                '<div class="aisk-modal__body" style="padding:24px;">' +
                '<p style="font-size:14px;margin:0 0 12px;">You are about to permanently delete:</p>' +
                '<p style="font-size:15px;font-weight:700;color:' + warningColors[scope] + ';margin:0 0 16px;">' + labels[scope] + '</p>' +
                '<p style="font-size:13px;color:#787c82;margin:0 0 20px;">This action cannot be undone. You will need to re-run the affected wizard steps to regenerate this data.</p>' +
                '<div style="display:flex;gap:10px;justify-content:flex-end;">' +
                '<button type="button" class="button aisk-confirm-cancel">Cancel</button>' +
                '<button type="button" class="button aisk-confirm-proceed" style="background:' + warningColors[scope] + ';border-color:' + warningColors[scope] + ';color:#fff;">Yes, Delete</button>' +
                '</div>' +
                '</div>' +
                '</div>'
            );

            $('body').append($confirmOverlay).append($confirmModal);
            setTimeout(function() {
                $confirmOverlay.addClass('is-visible');
                $confirmModal.addClass('is-visible');
            }, 10);

            function closeConfirm() {
                $confirmOverlay.removeClass('is-visible');
                $confirmModal.removeClass('is-visible');
                setTimeout(function() {
                    $confirmOverlay.remove();
                    $confirmModal.remove();
                }, 200);
            }

            $confirmOverlay.on('click', closeConfirm);
            $confirmModal.find('.aisk-modal__close, .aisk-confirm-cancel').on('click', closeConfirm);

            $confirmModal.find('.aisk-confirm-proceed').on('click', function() {
                closeConfirm();
                $btn.prop('disabled', true).text('Clearing...');
                $.post(ajaxUrl, {
                    action: 'ai_seo_keeper_clear_seo_data',
                    nonce: nonce,
                    scope: scope
                }, function(response) {
                    if (response.success) {
                        $('#aisk-clear-feedback').text('✓ ' + response.data.message).show();
                        setTimeout(function() {
                            location.reload();
                        }, 1200);
                    } else {
                        $('#aisk-clear-feedback').text('✗ ' + (response.data.message || 'Error')).css('color', '#d63638').show();
                        $btn.prop('disabled', false);
                    }
                }).fail(function() {
                    $('#aisk-clear-feedback').text('✗ Network error.').css('color', '#d63638').show();
                    $btn.prop('disabled', false);
                });
            });
        });

    })(jQuery);
</script>