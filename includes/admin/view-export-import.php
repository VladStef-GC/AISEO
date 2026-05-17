<?php

/**
 * Export / Import page view (v2 — multi-step AJAX import).
 *
 * Variables available:
 *   $import_status, $import_msg
 *
 * @package AI_SEO_Captain
 */

defined('ABSPATH') || exit;

/** @var string $import_status */
/** @var string $import_msg */
?>
<div class="wrap">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px;">
        <img src="<?php echo esc_url(AI_SEO_KEEPER_URL . 'assets/img/ai-seo-captain-d.svg'); ?>" alt="SEO Captain" style="width:40px;height:40px;" />
        <h1 style="margin:0;"><?php esc_html_e('Export / Import', 'ai-seo-captain'); ?></h1>
    </div>

    <?php if ('' !== $import_msg) : ?>
        <?php
        echo \AI_SEO_Captain\Admin::render_banner(
            'success' === $import_status ? 'is-success' : 'is-error',
            'success' === $import_status ? esc_html__('Import complete', 'ai-seo-captain') : esc_html__('Import failed', 'ai-seo-captain'),
            esc_html($import_msg),
            true
        );
        ?>
    <?php endif; ?>

    <div class="aisc-ei-wrap">

        <!-- ========== EXPORT PANEL ========== -->
        <div class="aisc-ei-panel">
            <h2><span class="dashicons dashicons-download"></span> <?php esc_html_e('Export', 'ai-seo-captain'); ?></h2>
            <p><?php esc_html_e('Download a JSON file with your SEO Captain data. Choose which sections to include in the export.', 'ai-seo-captain'); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ai_seo_captain_export'); ?>
                <input type="hidden" name="action" value="ai_seo_captain_export" />

                <fieldset>
                    <legend class="aisc-ei-section-title"><?php esc_html_e('Sections to Export', 'ai-seo-captain'); ?></legend>
                    <div class="aisc-checkbox-grid">
                        <label><input type="checkbox" name="export_settings" value="1" checked /> <?php esc_html_e('Plugin Settings', 'ai-seo-captain'); ?></label>
                        <label><input type="checkbox" name="export_seo_meta" value="1" checked /> <?php esc_html_e('SEO Metadata (Posts & Pages)', 'ai-seo-captain'); ?></label>
                        <label><input type="checkbox" name="export_seo_terms" value="1" checked /> <?php esc_html_e('SEO Metadata (Categories & Tags)', 'ai-seo-captain'); ?></label>
                        <label><input type="checkbox" name="export_audits" value="1" checked /> <?php esc_html_e('Page Audits & Content Index', 'ai-seo-captain'); ?></label>
                        <label><input type="checkbox" name="export_redirects" value="1" checked /> <?php esc_html_e('Redirect Rules', 'ai-seo-captain'); ?></label>
                        <label><input type="checkbox" name="export_four_oh_four" value="1" /> <?php esc_html_e('404 Log', 'ai-seo-captain'); ?></label>
                        <label><input type="checkbox" name="export_runs" value="1" /> <?php esc_html_e('Batch Run Lists', 'ai-seo-captain'); ?></label>
                        <label><input type="checkbox" name="export_chat_history" value="1" /> <?php esc_html_e('AI Chat History', 'ai-seo-captain'); ?></label>
                    </div>
                </fieldset>

                <div class="aisc-btn-row">
                    <button type="submit" class="aisc-btn aisc-btn-primary">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Download Export File', 'ai-seo-captain'); ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- ========== IMPORT PANEL ========== -->
        <div class="aisc-ei-panel">
            <h2><span class="dashicons dashicons-upload"></span> <?php esc_html_e('Import', 'ai-seo-captain'); ?></h2>
            <p><?php esc_html_e('Upload a previously exported JSON file. You\'ll preview page matches and choose your import mode before any data is changed.', 'ai-seo-captain'); ?></p>

            <!-- Step indicator -->
            <div class="aisc-steps" id="aisc-step-indicator">
                <div class="aisc-step active" data-step="upload"><span class="aisc-step-num">1</span> Upload</div>
                <div class="aisc-step-line"></div>
                <div class="aisc-step" data-step="config"><span class="aisc-step-num">2</span> Configure</div>
                <div class="aisc-step-line"></div>
                <div class="aisc-step" data-step="match"><span class="aisc-step-num">3</span> Preview</div>
                <div class="aisc-step-line"></div>
                <div class="aisc-step" data-step="progress"><span class="aisc-step-num">4</span> Import</div>
                <div class="aisc-step-line"></div>
                <div class="aisc-step" data-step="report"><span class="aisc-step-num">5</span> Done</div>
            </div>

            <!-- Inline error notice (replaces alert()) -->
            <div id="aisc-import-notice" class="aisc-notice aisc-notice-error"></div>

            <!-- Step 1: Upload -->
            <div id="aisc-import-step-upload">
                <div id="aisc-import-dropzone" class="aisc-dropzone">
                    <span class="dashicons dashicons-cloud-upload"></span>
                    <p><?php esc_html_e('Click or drag & drop your .json export file here', 'ai-seo-captain'); ?></p>
                    <p class="aisc-dropzone-hint"><?php esc_html_e('Only .json files exported from SEO Captain are accepted', 'ai-seo-captain'); ?></p>
                    <input type="file" id="aisc-import-file" accept=".json" style="display:none;" />
                </div>

                <div id="aisc-import-file-info" class="aisc-info-bar aisc-info-bar-blue" style="display:none;">
                    <span class="dashicons dashicons-media-code"></span>
                    <div>
                        <strong id="aisc-import-filename"></strong>
                        <span id="aisc-import-filesize" style="color:var(--aisc-text-muted);margin-left:8px;"></span>
                    </div>
                </div>

                <div class="aisc-btn-row">
                    <button id="aisc-import-upload-btn" class="aisc-btn aisc-btn-primary" disabled>
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e('Upload & Validate', 'ai-seo-captain'); ?>
                    </button>
                    <span id="aisc-import-upload-spinner" class="spinner"></span>
                </div>
            </div>

            <!-- Step 2: Summary + Mode Selection -->
            <div id="aisc-import-step-config" style="display:none;">
                <div id="aisc-import-summary" class="aisc-info-bar aisc-info-bar-blue"></div>

                <div class="aisc-ei-section-title"><?php esc_html_e('Import Mode', 'ai-seo-captain'); ?></div>
                <div class="aisc-mode-options">
                    <label class="aisc-mode-card active">
                        <input type="radio" name="aisc_import_mode" value="update" checked />
                        <div class="aisc-mode-card-body">
                            <div class="aisc-mode-card-title"><?php esc_html_e('Update Import', 'ai-seo-captain'); ?></div>
                            <div class="aisc-mode-card-desc"><?php esc_html_e('Only fill in empty/missing fields. Existing data is never overwritten. The safest option.', 'ai-seo-captain'); ?></div>
                        </div>
                    </label>
                    <label class="aisc-mode-card">
                        <input type="radio" name="aisc_import_mode" value="overwrite" />
                        <div class="aisc-mode-card-body">
                            <div class="aisc-mode-card-title"><?php esc_html_e('Overwrite Import', 'ai-seo-captain'); ?></div>
                            <div class="aisc-mode-card-desc"><?php esc_html_e('Export data wins for all matched pages. Extra pages and fields on your site are left alone.', 'ai-seo-captain'); ?></div>
                        </div>
                    </label>
                    <label class="aisc-mode-card aisc-mode-danger">
                        <input type="radio" name="aisc_import_mode" value="force" />
                        <div class="aisc-mode-card-body">
                            <div class="aisc-mode-card-title"><?php esc_html_e('Force Import', 'ai-seo-captain'); ?></div>
                            <div class="aisc-mode-card-desc"><?php esc_html_e('Delete all existing SEO data on matched pages, then replace with export data. Use with caution.', 'ai-seo-captain'); ?></div>
                        </div>
                    </label>
                </div>

                <fieldset id="aisc-import-sections">
                    <legend class="aisc-ei-section-title"><?php esc_html_e('Sections to Import', 'ai-seo-captain'); ?></legend>
                    <div class="aisc-checkbox-grid" id="aisc-import-sections-grid">
                        <!-- Populated by JS based on what's in the export file -->
                    </div>
                </fieldset>

                <div id="aisc-import-domain-rewrite" class="aisc-info-bar aisc-info-bar-amber" style="display:none;">
                    <span class="dashicons dashicons-warning"></span>
                    <div>
                        <strong><?php esc_html_e('Domain Mismatch', 'ai-seo-captain'); ?></strong>
                        <p id="aisc-domain-info" style="margin:4px 0 6px;"></p>
                        <label><input type="checkbox" id="aisc-rewrite-urls" checked /> <?php esc_html_e('Automatically rewrite URLs from source domain to this site', 'ai-seo-captain'); ?></label>
                    </div>
                </div>

                <div class="aisc-btn-row">
                    <button id="aisc-import-match-btn" class="aisc-btn aisc-btn-primary">
                        <span class="dashicons dashicons-search"></span>
                        <?php esc_html_e('Analyze & Match Pages', 'ai-seo-captain'); ?>
                    </button>
                    <button id="aisc-import-back-upload" class="aisc-btn aisc-btn-secondary"><?php esc_html_e('← Back', 'ai-seo-captain'); ?></button>
                    <span id="aisc-import-match-spinner" class="spinner"></span>
                </div>
            </div>

            <!-- Step 3: Matching Preview -->
            <div id="aisc-import-step-match" style="display:none;">
                <div class="aisc-ei-section-title"><?php esc_html_e('Page Matching Results', 'ai-seo-captain'); ?></div>
                <div id="aisc-match-summary" style="margin-bottom:12px;font-size:14px;"></div>

                <div id="aisc-match-table-wrap" class="aisc-match-table-wrap">
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th style="width:36px;"></th>
                                <th><?php esc_html_e('Export Page', 'ai-seo-captain'); ?></th>
                                <th style="width:100px;"><?php esc_html_e('Status', 'ai-seo-captain'); ?></th>
                                <th><?php esc_html_e('Target Match', 'ai-seo-captain'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="aisc-match-table-body"></tbody>
                    </table>
                </div>

                <div id="aisc-match-terms-wrap" style="display:none;margin-bottom:16px;">
                    <div class="aisc-ei-section-title"><?php esc_html_e('Term Matching Results', 'ai-seo-captain'); ?></div>
                    <div id="aisc-term-match-summary" style="margin-bottom:8px;font-size:14px;"></div>
                    <div class="aisc-match-table-wrap">
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th style="width:36px;"></th>
                                    <th><?php esc_html_e('Export Term', 'ai-seo-captain'); ?></th>
                                    <th style="width:100px;"><?php esc_html_e('Status', 'ai-seo-captain'); ?></th>
                                    <th><?php esc_html_e('Target Match', 'ai-seo-captain'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="aisc-term-match-table-body"></tbody>
                        </table>
                    </div>
                </div>

                <div class="aisc-info-bar aisc-info-bar-blue">
                    <span class="dashicons dashicons-info-outline"></span>
                    <span><?php esc_html_e('Only checked items will be imported. Pages on your site that are NOT in the export will never be modified or deleted.', 'ai-seo-captain'); ?></span>
                </div>

                <!-- Force confirmation -->
                <div id="aisc-force-confirm" class="aisc-info-bar aisc-info-bar-red" style="display:none;">
                    <span class="dashicons dashicons-warning"></span>
                    <div>
                        <strong><?php esc_html_e('Force Import Warning', 'ai-seo-captain'); ?></strong>
                        <p id="aisc-force-msg" style="margin:4px 0 8px;"></p>
                        <label>
                            <?php esc_html_e('Type FORCE to confirm:', 'ai-seo-captain'); ?>
                            <input type="text" id="aisc-force-input" class="aisc-force-input" autocomplete="off" />
                        </label>
                    </div>
                </div>

                <div class="aisc-btn-row">
                    <button id="aisc-import-start-btn" class="aisc-btn aisc-btn-primary">
                        <span class="dashicons dashicons-migrate"></span>
                        <?php esc_html_e('Start Import', 'ai-seo-captain'); ?>
                    </button>
                    <button id="aisc-import-back-config" class="aisc-btn aisc-btn-secondary"><?php esc_html_e('← Back', 'ai-seo-captain'); ?></button>
                </div>
            </div>

            <!-- Step 4: Progress -->
            <div id="aisc-import-step-progress" style="display:none;">
                <div class="aisc-info-bar aisc-info-bar-amber">
                    <span class="dashicons dashicons-warning"></span>
                    <strong><?php esc_html_e('Import in progress — do not close or refresh this page!', 'ai-seo-captain'); ?></strong>
                </div>

                <div class="aisc-progress-wrap">
                    <div class="aisc-progress-label" id="aisc-progress-label"><?php esc_html_e('Starting import...', 'ai-seo-captain'); ?></div>
                    <div class="aisc-progress-track">
                        <div class="aisc-progress-fill" id="aisc-progress-bar">
                            <span class="aisc-progress-pct" id="aisc-progress-pct">0%</span>
                        </div>
                    </div>
                </div>

                <div id="aisc-progress-log" class="aisc-log"></div>
            </div>

            <!-- Step 5: Report -->
            <div id="aisc-import-step-report" style="display:none;">
                <div class="aisc-info-bar aisc-info-bar-green">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <strong><?php esc_html_e('Import Complete!', 'ai-seo-captain'); ?></strong>
                </div>

                <div id="aisc-import-report" class="aisc-report"></div>

                <div class="aisc-btn-row">
                    <button id="aisc-import-done-btn" class="aisc-btn aisc-btn-primary">
                        <span class="dashicons dashicons-yes"></span>
                        <?php esc_html_e('Done', 'ai-seo-captain'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>