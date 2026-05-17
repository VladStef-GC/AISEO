<?php

/**
 * Keyword Tracking page view.
 *
 * Variables available (set by Admin::render_keyword_tracking_page):
 *   $keyphrase_map, $total_with_keyphrase, $cannibalized,
 *   $total_published, $without_keyphrase
 *
 * @package AI_SEO_Captain
 */

defined('ABSPATH') || exit;

/** @var array $keyphrase_map */
/** @var int   $total_with_keyphrase */
/** @var array $cannibalized */
/** @var int   $total_published */
/** @var int   $without_keyphrase */
/** @var string $readiness_banner */
?>
<div class="wrap">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px;">
        <img src="<?php echo esc_url(AI_SEO_KEEPER_URL . 'assets/img/ai-seo-captain-d.svg'); ?>" alt="SEO Captain" style="width:40px;height:40px;" />
        <h1 style="margin:0;"><?php esc_html_e('Keyword Tracking', 'ai-seo-captain'); ?></h1>
    </div>
    <p class="description"><?php esc_html_e('See which focus keyphrases are used across your site and detect keyword cannibalization (same keyphrase targeting multiple pages).', 'ai-seo-captain'); ?></p>

    <?php echo $readiness_banner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
    ?>

    <div style="display:grid;grid-template-columns:repeat(4,minmax(150px,1fr));gap:16px;max-width:900px;margin:20px 0;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;text-align:center;">
            <p style="font-size:28px;margin:0;font-weight:600;"><?php echo count($keyphrase_map); ?></p>
            <p style="margin:4px 0 0;color:#50575e;"><?php esc_html_e('Unique keyphrases', 'ai-seo-captain'); ?></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;text-align:center;">
            <p style="font-size:28px;margin:0;font-weight:600;"><?php echo $total_with_keyphrase; ?></p>
            <p style="margin:4px 0 0;color:#50575e;"><?php esc_html_e('Pages with keyphrase', 'ai-seo-captain'); ?></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;text-align:center;">
            <p style="font-size:28px;margin:0;font-weight:600;color:<?php echo $without_keyphrase > 0 ? '#dba617' : '#00a32a'; ?>;"><?php echo $without_keyphrase; ?></p>
            <p style="margin:4px 0 0;color:#50575e;"><?php esc_html_e('Without keyphrase', 'ai-seo-captain'); ?></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;text-align:center;">
            <p style="font-size:28px;margin:0;font-weight:600;color:<?php echo count($cannibalized) > 0 ? '#d63638' : '#00a32a'; ?>;"><?php echo count($cannibalized); ?></p>
            <p style="margin:4px 0 0;color:#50575e;"><?php esc_html_e('Cannibalization risks', 'ai-seo-captain'); ?></p>
        </div>
    </div>

    <?php if (! empty($cannibalized)) : ?>
        <?php
        echo \AI_SEO_Captain\Admin::render_banner(
            'is-warning',
            esc_html__('Keyword Cannibalization', 'ai-seo-captain'),
            esc_html__('These keyphrases target multiple pages. Consider consolidating content or differentiating the focus keyphrase for each page.', 'ai-seo-captain')
        );
        ?>
        <table class="widefat striped" style="max-width:1120px;margin-bottom:20px;">
            <thead>
                <tr>
                    <th><?php esc_html_e('Keyphrase', 'ai-seo-captain'); ?></th>
                    <th><?php esc_html_e('Pages targeting it', 'ai-seo-captain'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cannibalized as $group) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($group['keyphrase']); ?></strong></td>
                        <td>
                            <?php foreach ($group['pages'] as $p) : ?>
                                <div style="margin-bottom:4px;">
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $p['id'] . '&action=edit')); ?>"><?php echo esc_html($p['title']); ?></a>
                                    <span style="color:#50575e;font-size:12px;">(<?php echo esc_html($p['post_type']); ?>)</span>
                                </div>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if (! empty($keyphrase_map)) : ?>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;max-width:1120px;">
            <h2 style="margin-top:0;"><?php esc_html_e('All Focus Keyphrases', 'ai-seo-captain'); ?></h2>
            <table class="widefat striped ai-seo-sortable" id="ai-seo-keywords-table">
                <thead>
                    <tr>
                        <th class="ai-seo-sort" data-col="0"><?php esc_html_e('Keyphrase', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                        <th class="ai-seo-sort" data-col="1"><?php esc_html_e('Page', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                        <th class="ai-seo-sort" data-col="2"><?php esc_html_e('Type', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($keyphrase_map as $group) : ?>
                        <?php foreach ($group['pages'] as $p) : ?>
                            <tr<?php echo count($group['pages']) > 1 ? ' style="background:#fff3cd;"' : ''; ?>>
                                <td data-sort-value="<?php echo esc_attr(strtolower($group['keyphrase'])); ?>"><strong><?php echo esc_html($group['keyphrase']); ?></strong></td>
                                <td data-sort-value="<?php echo esc_attr(strtolower($p['title'])); ?>"><a href="<?php echo esc_url(admin_url('post.php?post=' . $p['id'] . '&action=edit')); ?>"><?php echo esc_html($p['title']); ?></a></td>
                                <td data-sort-value="<?php echo esc_attr($p['post_type']); ?>"><?php echo esc_html($p['post_type']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else : ?>
        <p><?php esc_html_e("No focus keyphrases have been set yet. Add keyphrases in the editor's SEO tab.", 'ai-seo-captain'); ?></p>
    <?php endif; ?>
</div>