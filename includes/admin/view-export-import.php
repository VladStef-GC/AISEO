<?php

/**
 * Export / Import page view.
 *
 * Variables available (set by Admin::render_export_import_page):
 *   $import_status, $import_msg
 *
 * @package AI_SEO_Keeper
 */

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1>Export / Import</h1>

    <?php if ('' !== $import_msg) : ?>
        <div class="notice <?php echo 'success' === $import_status ? 'notice-success' : 'notice-error'; ?> is-dismissible">
            <p><?php echo esc_html($import_msg); ?></p>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(320px,1fr));gap:24px;max-width:1120px;margin-top:20px;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;">Export</h2>
            <p>Download a JSON file with all AI SEO Keeper settings, per-page SEO metadata, redirect rules, and cornerstone flags.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ai_seo_keeper_export'); ?>
                <input type="hidden" name="action" value="ai_seo_keeper_export" />
                <p>
                    <label><input type="checkbox" name="export_settings" value="1" checked /> Plugin settings</label><br />
                    <label><input type="checkbox" name="export_seo_meta" value="1" checked /> Per-page SEO metadata (titles, descriptions, keyphrases, overrides)</label><br />
                    <label><input type="checkbox" name="export_redirects" value="1" checked /> Redirect rules</label>
                </p>
                <button type="submit" class="button button-primary">Download export</button>
            </form>
        </div>

        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;">Import</h2>
            <p>Upload a previously exported JSON file to restore settings and SEO metadata. Existing values will be overwritten.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('ai_seo_keeper_import'); ?>
                <input type="hidden" name="action" value="ai_seo_keeper_import" />
                <p><input type="file" name="import_file" accept=".json" required /></p>
                <button type="submit" class="button button-primary">Upload &amp; import</button>
            </form>
        </div>
    </div>
</div>