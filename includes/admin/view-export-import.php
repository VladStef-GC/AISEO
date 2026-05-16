<?php

/**
 * Export / Import page view (v2 — multi-step AJAX import).
 *
 * Variables available:
 *   $import_status, $import_msg
 *
 * @package AI_SEO_Keeper
 */

defined('ABSPATH') || exit;

/** @var string $import_status */
/** @var string $import_msg */
?>
<div class="wrap">
    <h1><?php esc_html_e('Export / Import', 'ai-seo-keeper'); ?></h1>

    <?php if ('' !== $import_msg) : ?>
        <div class="notice <?php echo 'success' === $import_status ? 'notice-success' : 'notice-error'; ?> is-dismissible">
            <p><?php echo esc_html($import_msg); ?></p>
        </div>
    <?php endif; ?>

    <div class="aisk-ei-wrap">

        <!-- ========== EXPORT PANEL ========== -->
        <div class="aisk-ei-panel">
            <h2><span class="dashicons dashicons-download"></span> <?php esc_html_e('Export', 'ai-seo-keeper'); ?></h2>
            <p><?php esc_html_e('Download a JSON file with your AI SEO Keeper data. Choose which sections to include in the export.', 'ai-seo-keeper'); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ai_seo_keeper_export'); ?>
                <input type="hidden" name="action" value="ai_seo_keeper_export" />

                <fieldset>
                    <legend class="aisk-ei-section-title"><?php esc_html_e('Sections to Export', 'ai-seo-keeper'); ?></legend>
                    <div class="aisk-checkbox-grid">
                        <label><input type="checkbox" name="export_settings" value="1" checked /> <?php esc_html_e('Plugin Settings', 'ai-seo-keeper'); ?></label>
                        <label><input type="checkbox" name="export_seo_meta" value="1" checked /> <?php esc_html_e('SEO Metadata (Posts & Pages)', 'ai-seo-keeper'); ?></label>
                        <label><input type="checkbox" name="export_seo_terms" value="1" checked /> <?php esc_html_e('SEO Metadata (Categories & Tags)', 'ai-seo-keeper'); ?></label>
                        <label><input type="checkbox" name="export_audits" value="1" checked /> <?php esc_html_e('Page Audits & Content Index', 'ai-seo-keeper'); ?></label>
                        <label><input type="checkbox" name="export_redirects" value="1" checked /> <?php esc_html_e('Redirect Rules', 'ai-seo-keeper'); ?></label>
                        <label><input type="checkbox" name="export_four_oh_four" value="1" /> <?php esc_html_e('404 Log', 'ai-seo-keeper'); ?></label>
                        <label><input type="checkbox" name="export_runs" value="1" /> <?php esc_html_e('Batch Run Lists', 'ai-seo-keeper'); ?></label>
                        <label><input type="checkbox" name="export_chat_history" value="1" /> <?php esc_html_e('AI Chat History', 'ai-seo-keeper'); ?></label>
                    </div>
                </fieldset>

                <div class="aisk-btn-row">
                    <button type="submit" class="aisk-btn aisk-btn-primary">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Download Export File', 'ai-seo-keeper'); ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- ========== IMPORT PANEL ========== -->
        <div class="aisk-ei-panel">
            <h2><span class="dashicons dashicons-upload"></span> <?php esc_html_e('Import', 'ai-seo-keeper'); ?></h2>
            <p><?php esc_html_e('Upload a previously exported JSON file. You\'ll preview page matches and choose your import mode before any data is changed.', 'ai-seo-keeper'); ?></p>

            <!-- Step indicator -->
            <div class="aisk-steps" id="aisk-step-indicator">
                <div class="aisk-step active" data-step="upload"><span class="aisk-step-num">1</span> Upload</div>
                <div class="aisk-step-line"></div>
                <div class="aisk-step" data-step="config"><span class="aisk-step-num">2</span> Configure</div>
                <div class="aisk-step-line"></div>
                <div class="aisk-step" data-step="match"><span class="aisk-step-num">3</span> Preview</div>
                <div class="aisk-step-line"></div>
                <div class="aisk-step" data-step="progress"><span class="aisk-step-num">4</span> Import</div>
                <div class="aisk-step-line"></div>
                <div class="aisk-step" data-step="report"><span class="aisk-step-num">5</span> Done</div>
            </div>

            <!-- Inline error notice (replaces alert()) -->
            <div id="aisk-import-notice" class="aisk-notice aisk-notice-error"></div>

            <!-- Step 1: Upload -->
            <div id="aisk-import-step-upload">
                <div id="aisk-import-dropzone" class="aisk-dropzone">
                    <span class="dashicons dashicons-cloud-upload"></span>
                    <p><?php esc_html_e('Click or drag & drop your .json export file here', 'ai-seo-keeper'); ?></p>
                    <p class="aisk-dropzone-hint"><?php esc_html_e('Only .json files exported from AI SEO Keeper are accepted', 'ai-seo-keeper'); ?></p>
                    <input type="file" id="aisk-import-file" accept=".json" style="display:none;" />
                </div>

                <div id="aisk-import-file-info" class="aisk-info-bar aisk-info-bar-blue" style="display:none;">
                    <span class="dashicons dashicons-media-code"></span>
                    <div>
                        <strong id="aisk-import-filename"></strong>
                        <span id="aisk-import-filesize" style="color:var(--aisk-text-muted);margin-left:8px;"></span>
                    </div>
                </div>

                <div class="aisk-btn-row">
                    <button id="aisk-import-upload-btn" class="aisk-btn aisk-btn-primary" disabled>
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e('Upload & Validate', 'ai-seo-keeper'); ?>
                    </button>
                    <span id="aisk-import-upload-spinner" class="spinner"></span>
                </div>
            </div>

            <!-- Step 2: Summary + Mode Selection -->
            <div id="aisk-import-step-config" style="display:none;">
                <div id="aisk-import-summary" class="aisk-info-bar aisk-info-bar-blue"></div>

                <div class="aisk-ei-section-title"><?php esc_html_e('Import Mode', 'ai-seo-keeper'); ?></div>
                <div class="aisk-mode-options">
                    <label class="aisk-mode-card active">
                        <input type="radio" name="aisk_import_mode" value="update" checked />
                        <div class="aisk-mode-card-body">
                            <div class="aisk-mode-card-title"><?php esc_html_e('Update Import', 'ai-seo-keeper'); ?></div>
                            <div class="aisk-mode-card-desc"><?php esc_html_e('Only fill in empty/missing fields. Existing data is never overwritten. The safest option.', 'ai-seo-keeper'); ?></div>
                        </div>
                    </label>
                    <label class="aisk-mode-card">
                        <input type="radio" name="aisk_import_mode" value="overwrite" />
                        <div class="aisk-mode-card-body">
                            <div class="aisk-mode-card-title"><?php esc_html_e('Overwrite Import', 'ai-seo-keeper'); ?></div>
                            <div class="aisk-mode-card-desc"><?php esc_html_e('Export data wins for all matched pages. Extra pages and fields on your site are left alone.', 'ai-seo-keeper'); ?></div>
                        </div>
                    </label>
                    <label class="aisk-mode-card aisk-mode-danger">
                        <input type="radio" name="aisk_import_mode" value="force" />
                        <div class="aisk-mode-card-body">
                            <div class="aisk-mode-card-title"><?php esc_html_e('Force Import', 'ai-seo-keeper'); ?></div>
                            <div class="aisk-mode-card-desc"><?php esc_html_e('Delete all existing SEO data on matched pages, then replace with export data. Use with caution.', 'ai-seo-keeper'); ?></div>
                        </div>
                    </label>
                </div>

                <fieldset id="aisk-import-sections">
                    <legend class="aisk-ei-section-title"><?php esc_html_e('Sections to Import', 'ai-seo-keeper'); ?></legend>
                    <div class="aisk-checkbox-grid" id="aisk-import-sections-grid">
                        <!-- Populated by JS based on what's in the export file -->
                    </div>
                </fieldset>

                <div id="aisk-import-domain-rewrite" class="aisk-info-bar aisk-info-bar-amber" style="display:none;">
                    <span class="dashicons dashicons-warning"></span>
                    <div>
                        <strong><?php esc_html_e('Domain Mismatch', 'ai-seo-keeper'); ?></strong>
                        <p id="aisk-domain-info" style="margin:4px 0 6px;"></p>
                        <label><input type="checkbox" id="aisk-rewrite-urls" checked /> <?php esc_html_e('Automatically rewrite URLs from source domain to this site', 'ai-seo-keeper'); ?></label>
                    </div>
                </div>

                <div class="aisk-btn-row">
                    <button id="aisk-import-match-btn" class="aisk-btn aisk-btn-primary">
                        <span class="dashicons dashicons-search"></span>
                        <?php esc_html_e('Analyze & Match Pages', 'ai-seo-keeper'); ?>
                    </button>
                    <button id="aisk-import-back-upload" class="aisk-btn aisk-btn-secondary"><?php esc_html_e('← Back', 'ai-seo-keeper'); ?></button>
                    <span id="aisk-import-match-spinner" class="spinner"></span>
                </div>
            </div>

            <!-- Step 3: Matching Preview -->
            <div id="aisk-import-step-match" style="display:none;">
                <div class="aisk-ei-section-title"><?php esc_html_e('Page Matching Results', 'ai-seo-keeper'); ?></div>
                <div id="aisk-match-summary" style="margin-bottom:12px;font-size:14px;"></div>

                <div id="aisk-match-table-wrap" class="aisk-match-table-wrap">
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th style="width:36px;"></th>
                                <th><?php esc_html_e('Export Page', 'ai-seo-keeper'); ?></th>
                                <th style="width:100px;"><?php esc_html_e('Status', 'ai-seo-keeper'); ?></th>
                                <th><?php esc_html_e('Target Match', 'ai-seo-keeper'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="aisk-match-table-body"></tbody>
                    </table>
                </div>

                <div id="aisk-match-terms-wrap" style="display:none;margin-bottom:16px;">
                    <div class="aisk-ei-section-title"><?php esc_html_e('Term Matching Results', 'ai-seo-keeper'); ?></div>
                    <div id="aisk-term-match-summary" style="margin-bottom:8px;font-size:14px;"></div>
                    <div class="aisk-match-table-wrap">
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th style="width:36px;"></th>
                                    <th><?php esc_html_e('Export Term', 'ai-seo-keeper'); ?></th>
                                    <th style="width:100px;"><?php esc_html_e('Status', 'ai-seo-keeper'); ?></th>
                                    <th><?php esc_html_e('Target Match', 'ai-seo-keeper'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="aisk-term-match-table-body"></tbody>
                        </table>
                    </div>
                </div>

                <div class="aisk-info-bar aisk-info-bar-blue">
                    <span class="dashicons dashicons-info-outline"></span>
                    <span><?php esc_html_e('Only checked items will be imported. Pages on your site that are NOT in the export will never be modified or deleted.', 'ai-seo-keeper'); ?></span>
                </div>

                <!-- Force confirmation -->
                <div id="aisk-force-confirm" class="aisk-info-bar aisk-info-bar-red" style="display:none;">
                    <span class="dashicons dashicons-warning"></span>
                    <div>
                        <strong><?php esc_html_e('Force Import Warning', 'ai-seo-keeper'); ?></strong>
                        <p id="aisk-force-msg" style="margin:4px 0 8px;"></p>
                        <label>
                            <?php esc_html_e('Type FORCE to confirm:', 'ai-seo-keeper'); ?>
                            <input type="text" id="aisk-force-input" class="aisk-force-input" autocomplete="off" />
                        </label>
                    </div>
                </div>

                <div class="aisk-btn-row">
                    <button id="aisk-import-start-btn" class="aisk-btn aisk-btn-primary">
                        <span class="dashicons dashicons-migrate"></span>
                        <?php esc_html_e('Start Import', 'ai-seo-keeper'); ?>
                    </button>
                    <button id="aisk-import-back-config" class="aisk-btn aisk-btn-secondary"><?php esc_html_e('← Back', 'ai-seo-keeper'); ?></button>
                </div>
            </div>

            <!-- Step 4: Progress -->
            <div id="aisk-import-step-progress" style="display:none;">
                <div class="aisk-info-bar aisk-info-bar-amber">
                    <span class="dashicons dashicons-warning"></span>
                    <strong><?php esc_html_e('Import in progress — do not close or refresh this page!', 'ai-seo-keeper'); ?></strong>
                </div>

                <div class="aisk-progress-wrap">
                    <div class="aisk-progress-label" id="aisk-progress-label"><?php esc_html_e('Starting import...', 'ai-seo-keeper'); ?></div>
                    <div class="aisk-progress-track">
                        <div class="aisk-progress-fill" id="aisk-progress-bar">
                            <span class="aisk-progress-pct" id="aisk-progress-pct">0%</span>
                        </div>
                    </div>
                </div>

                <div id="aisk-progress-log" class="aisk-log"></div>
            </div>

            <!-- Step 5: Report -->
            <div id="aisk-import-step-report" style="display:none;">
                <div class="aisk-info-bar aisk-info-bar-green">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <strong><?php esc_html_e('Import Complete!', 'ai-seo-keeper'); ?></strong>
                </div>

                <div id="aisk-import-report" class="aisk-report"></div>

                <div class="aisk-btn-row">
                    <button id="aisk-import-done-btn" class="aisk-btn aisk-btn-primary">
                        <span class="dashicons dashicons-yes"></span>
                        <?php esc_html_e('Done', 'ai-seo-keeper'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>