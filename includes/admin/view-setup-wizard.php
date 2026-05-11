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
 *   $ajax_toggle_audit_skip_action, $ajax_save_skip_patterns_action
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

            <!-- TAB 3: Skip Patterns -->
            <details id="aisk-tab-skip" style="border:1px solid #dcdcde;border-radius:4px;margin-top:12px;background:#fff;">
                <summary style="cursor:pointer;padding:14px 18px;font-weight:600;font-size:14px;background:#f6f7f7;border-radius:4px 4px 0 0;user-select:none;">
                    &#128683; Skip Rules
                    <span id="aisk-tab-skip-count" style="font-weight:400;font-size:13px;color:#787c82;margin-left:8px;"></span>
                </summary>
                <div style="padding:18px;">
                    <p style="font-size:13px;margin:0 0 12px;color:#50575e;">
                        Pages skipped here are excluded from future audits only. SEO metadata, sitemaps, and all other features remain active.
                        Use the <strong>&#128683;</strong> button on any page card to skip/unskip individually, or define path patterns below.
                    </p>
                    <div style="margin-bottom:16px;">
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">Path patterns (one per line):</label>
                        <textarea id="aisk-skip-patterns" rows="5" style="width:100%;max-width:500px;font-family:monospace;font-size:13px;" placeholder="/experiments/*&#10;/test-pages/**&#10;/landing/*/thank-you"><?php echo esc_textarea($skip_patterns); ?></textarea>
                        <p style="font-size:12px;color:#787c82;margin:6px 0 0;">
                            Use <code>*</code> to match one path segment, <code>**</code> to match any depth. Lines starting with <code>#</code> are comments.<br>
                            Examples: <code>/experiments/*</code> skips all direct children &bull; <code>/experiments/**</code> skips all descendants &bull; <code>/*/thank-you</code> skips any "thank-you" page.
                        </p>
                    </div>
                    <div style="display:flex;gap:10px;align-items:center;">
                        <button id="aisk-btn-save-patterns" class="button button-primary" type="button">Save Patterns</button>
                        <span id="aisk-patterns-feedback" style="font-size:13px;color:#00a32a;display:none;"></span>
                    </div>
                    <div id="aisk-skip-list" style="margin-top:16px;">
                        <h4 style="margin:0 0 8px;">Individually Skipped Pages:</h4>
                        <div id="aisk-skipped-pages-list" style="font-size:13px;color:#50575e;"></div>
                    </div>
                </div>
            </details>

        </div>
    </div>
</div>

<script type="text/javascript">
    (function($) {
        var nonce = <?php echo wp_json_encode(wp_create_nonce('ai_seo_keeper_setup_wizard')); ?>;
        var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
        var publishedIds = <?php echo $published_ids_json; ?>;
        var skippedIds = <?php echo $skipped_ids_json; ?>;

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
                '✓ ' + this.stats.processed + '  ⏭ ' + this.stats.skipped +
                (this.stats.cached > 0 ? '  📋 ' + this.stats.cached : '') +
                '  ✗ ' + this.stats.errors
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
            $('#aisk-s2-log').show();
            $('#aisk-s2-done').hide();
            $('#aisk-s2-stopped').hide();
            $('#aisk-s2-paused').hide();

            s2processor = new BatchProcessor({
                ids: publishedIds,
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
                },
                onError: function(postId, title, msg) {
                    $('#aisk-s2-log').prepend('<div class="aisk-log-entry" style="color:#d63638;">✗ <strong>' + esc(title) + '</strong> — ' + esc(msg) + '</div>');
                }
            });

            s2processor.start();
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
                html += '<details style="margin-top:8px;"><summary style="cursor:pointer;font-weight:600;color:#d63638;font-size:13px;">Issues (' + d.issues.length + ')</summary><ul style="margin:4px 0 0 16px;padding:0;">';
                for (var i = 0; i < d.issues.length; i++) html += '<li style="font-size:13px;margin:2px 0;">' + esc(d.issues[i]) + '</li>';
                html += '</ul></details>';
            }
            if (d.suggestions && d.suggestions.length > 0) {
                html += '<details style="margin-top:6px;"><summary style="cursor:pointer;font-weight:600;color:#2271b1;font-size:13px;">Suggestions (' + d.suggestions.length + ')</summary><ul style="margin:4px 0 0 16px;padding:0;">';
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
                top10html += '<tr><td>' + (i + 1) + '</td><td><a href="' + esc(top10[i].permalink) + '" target="_blank">' + esc(top10[i].title) + '</a></td>' +
                    '<td style="text-align:center;">' + scoreBadge(top10[i].score) + '</td>' +
                    '<td style="text-align:center;">' + (top10[i].issues ? top10[i].issues.length : 0) + '</td></tr>';
            }
            $('#aisk-top10-table tbody').html(top10html);

            // Bottom 10
            var bottom10 = sorted.slice(-10).reverse();
            var bottom10html = '';
            for (var i = 0; i < bottom10.length; i++) {
                bottom10html += '<tr><td>' + (i + 1) + '</td><td><a href="' + esc(bottom10[i].permalink) + '" target="_blank">' + esc(bottom10[i].title) + '</a></td>' +
                    '<td style="text-align:center;">' + scoreBadge(bottom10[i].score) + '</td>' +
                    '<td style="text-align:center;">' + (bottom10[i].issues ? bottom10[i].issues.length : 0) + '</td></tr>';
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
            $('#aisk-s3-done').hide();
            $('#aisk-s3-stopped').hide();
            $('#aisk-s3-paused').hide();

            // For "Continue": only process pages not yet audited.
            // For "Re-Run": process all pages.
            // Always exclude individually-skipped pages.
            var idsToProcess = publishedIds.filter(function(id) {
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

            if (idsToProcess.length === 0) {
                $('#aisk-s3-done').show();
                $('#aisk-s3-result').text('All ' + publishedIds.length + ' pages already audited. Click "Re-Run Audits" to refresh all scores.');
                btn.prop('disabled', false).text('Re-Run Audits');
                markStepDone(3);
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
                    $('#aisk-s3-status').text('Done in ' + s3processor.timer.getText() + '.');
                    $('#aisk-s3-done').show();
                    $('#aisk-s3-result').text(total + ' pages audited' +
                        (stats.cached > 0 ? ' (' + stats.cached + ' from cache)' : '') +
                        ', ' + stats.errors + ' errors. Total: ' + allAudits.length + ' pages.');
                    btn.prop('disabled', false).text('Re-Run Audits');
                    markStepDone(3);
                    refreshSummaryTab();
                    refreshDetailsTab();
                },
                onError: function(postId, title, msg) {
                    $('#aisk-s3-results').prepend(
                        '<div style="border:1px solid #d63638;padding:12px;margin-bottom:12px;background:#fcf0f1;border-radius:4px;">' +
                        '<strong>' + esc(title) + '</strong> — <span style="color:#d63638;">' + esc(msg) + '</span></div>'
                    );
                }
            });

            s3processor.start();
        });

    })(jQuery);
</script>