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
    <h1>ðŸš€ <?php esc_html_e('AI SEO Keeper â€” Setup Wizard', 'ai-seo-keeper'); ?></h1>
    <p style="font-size: 14px; max-width: 700px;">
        <?php esc_html_e('This wizard indexes your site, generates AI-powered SEO metadata, and runs a full page audit â€” all in three easy steps.', 'ai-seo-keeper'); ?>
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
                            <h4 style="margin:0 0 8px;color:#00a32a;">&#127942; Top 10 â€” Best SEO Scores</h4>
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
                            <h4 style="margin:0 0 8px;color:#d63638;">&#9888; Bottom 10 â€” Needs Attention</h4>
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
                            <option value="title-asc">Title: A â†’ Z</option>
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
            <?php esc_html_e('Clear generated SEO data from the database. This cannot be undone â€” you will need to re-run the affected steps.', 'ai-seo-keeper'); ?>
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
