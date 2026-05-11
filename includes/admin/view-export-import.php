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
    <h1><?php esc_html_e('Export / Import', 'ai-seo-keeper'); ?></h1>

    <?php if ('' !== $import_msg) : ?>
        <div class="notice <?php echo 'success' === $import_status ? 'notice-success' : 'notice-error'; ?> is-dismissible">
            <p><?php echo esc_html($import_msg); ?></p>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(320px,1fr));gap:24px;max-width:1120px;margin-top:20px;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Export', 'ai-seo-keeper'); ?></h2>
            <p><?php esc_html_e('Download a JSON file with all AI SEO Keeper settings, per-page SEO metadata, redirect rules, and cornerstone flags.', 'ai-seo-keeper'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ai_seo_keeper_export'); ?>
                <input type="hidden" name="action" value="ai_seo_keeper_export" />
                <p>
                    <label><input type="checkbox" name="export_settings" value="1" checked /> <?php esc_html_e('Plugin settings', 'ai-seo-keeper'); ?></label><br />
                    <label><input type="checkbox" name="export_seo_meta" value="1" checked /> <?php esc_html_e('Per-page SEO metadata (titles, descriptions, keyphrases, overrides)', 'ai-seo-keeper'); ?></label><br />
                    <label><input type="checkbox" name="export_redirects" value="1" checked /> <?php esc_html_e('Redirect rules', 'ai-seo-keeper'); ?></label>
                </p>
                <button type="submit" class="button button-primary"><?php esc_html_e('Download export', 'ai-seo-keeper'); ?></button>
            </form>
        </div>

        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Import', 'ai-seo-keeper'); ?></h2>
            <p><?php esc_html_e('Upload a previously exported JSON file to restore settings and SEO metadata. Existing values will be overwritten.', 'ai-seo-keeper'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('ai_seo_keeper_import'); ?>
                <input type="hidden" name="action" value="ai_seo_keeper_import" />
                <p><input type="file" name="import_file" accept=".json" required /></p>
                <button type="submit" class="button button-primary"><?php esc_html_e('Upload &amp; import', 'ai-seo-keeper'); ?></button>
            </form>
        </div>
    </div>
</div>