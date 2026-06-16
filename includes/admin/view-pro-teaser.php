<?php

/**
 * Pro feature teaser / upgrade page.
 *
 * Shown to Free users in place of a locked Pro feature (Cache, Search Console,
 * Export/Import). Rendered by Admin::render_pro_teaser().
 *
 * Variables:
 *   string   $feature_title
 *   string   $feature_tagline
 *   string[] $benefits
 *   string   $upgrade_url
 *
 * @package AI_SEO_Captain
 */

defined('ABSPATH') || exit;

/** @var string   $feature_title */
/** @var string   $feature_tagline */
/** @var string[] $benefits */
/** @var string   $upgrade_url */
?>
<div class="wrap aisc-wrap">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px;">
        <img src="<?php echo esc_url(AI_SEO_CAPTAIN_URL . 'assets/img/ai-seo-captain-d.svg'); ?>" alt="SEO Captain" style="width:40px;height:40px;" />
        <h1 style="margin:0;display:flex;align-items:center;gap:10px;">
            <?php echo esc_html($feature_title); ?>
            <span style="font-size:11px;font-weight:700;letter-spacing:.04em;background:#089564;color:#fff;border-radius:4px;padding:2px 8px;text-transform:uppercase;">Pro</span>
        </h1>
    </div>

    <div style="max-width:760px;margin-top:18px;background:#fff;border:1px solid #dcdcde;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.06);overflow:hidden;">
        <div style="background:linear-gradient(135deg,#089564 0%,#02b77c 100%);color:#fff;padding:28px 32px;">
            <span class="dashicons dashicons-lock" style="font-size:34px;width:34px;height:34px;opacity:.9;"></span>
            <h2 style="margin:10px 0 6px;color:#fff;font-size:22px;font-weight:700;"><?php echo esc_html(sprintf(/* translators: %s: feature name */ __('Unlock %s', 'ai-seo-captain'), $feature_title)); ?></h2>
            <p style="margin:0;font-size:15px;line-height:1.5;opacity:.95;max-width:540px;"><?php echo esc_html($feature_tagline); ?></p>
        </div>

        <div style="padding:26px 32px;">
            <?php if (! empty($benefits)) : ?>
                <p style="margin:0 0 14px;font-weight:600;color:#1d2327;font-size:14px;"><?php esc_html_e('What you get with Pro:', 'ai-seo-captain'); ?></p>
                <ul style="margin:0 0 24px;padding:0;list-style:none;display:grid;gap:12px;">
                    <?php foreach ($benefits as $benefit) : ?>
                        <li style="display:flex;align-items:flex-start;gap:10px;font-size:14px;color:#3c434a;line-height:1.45;">
                            <span class="dashicons dashicons-yes-alt" style="color:#089564;font-size:20px;width:20px;height:20px;flex:0 0 auto;margin-top:1px;"></span>
                            <span><?php echo esc_html($benefit); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-primary button-hero" style="background:#089564;border-color:#077a52;box-shadow:0 1px 0 #077a52;text-shadow:none;display:inline-flex;align-items:center;gap:8px;">
                    <span class="dashicons dashicons-star-filled" style="font-size:18px;width:18px;height:18px;"></span>
                    <?php esc_html_e('Upgrade to Pro', 'ai-seo-captain'); ?>
                </a>
                <span style="font-size:13px;color:#646970;"><?php esc_html_e('Manual SEO editing stays free and unlimited — Pro adds the power tools.', 'ai-seo-captain'); ?></span>
            </div>
        </div>
    </div>
</div>
<style>
    /* Brand the upgrade button hover to match the plugin green. */
    .aisc-wrap .button-primary.button-hero:hover,
    .aisc-wrap .button-primary.button-hero:focus {
        background: #02b77c !important;
        border-color: #077a52 !important;
        color: #fff !important;
    }
</style>
