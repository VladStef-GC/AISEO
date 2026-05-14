<?php

/**
 * Keyword Tracking page view.
 *
 * Variables available (set by Admin::render_keyword_tracking_page):
 *   $keyphrase_map, $total_with_keyphrase, $cannibalized,
 *   $total_published, $without_keyphrase
 *
 * @package AI_SEO_Keeper
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
    <h1><?php esc_html_e('Keyword Tracking', 'ai-seo-keeper'); ?></h1>
    <p class="description"><?php esc_html_e('See which focus keyphrases are used across your site and detect keyword cannibalization (same keyphrase targeting multiple pages).', 'ai-seo-keeper'); ?></p>

    <?php echo $readiness_banner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
    ?>

    <div style="display:grid;grid-template-columns:repeat(4,minmax(150px,1fr));gap:16px;max-width:900px;margin:20px 0;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;text-align:center;">
            <p style="font-size:28px;margin:0;font-weight:600;"><?php echo count($keyphrase_map); ?></p>
            <p style="margin:4px 0 0;color:#50575e;"><?php esc_html_e('Unique keyphrases', 'ai-seo-keeper'); ?></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;text-align:center;">
            <p style="font-size:28px;margin:0;font-weight:600;"><?php echo $total_with_keyphrase; ?></p>
            <p style="margin:4px 0 0;color:#50575e;"><?php esc_html_e('Pages with keyphrase', 'ai-seo-keeper'); ?></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;text-align:center;">
            <p style="font-size:28px;margin:0;font-weight:600;color:<?php echo $without_keyphrase > 0 ? '#dba617' : '#00a32a'; ?>;"><?php echo $without_keyphrase; ?></p>
            <p style="margin:4px 0 0;color:#50575e;"><?php esc_html_e('Without keyphrase', 'ai-seo-keeper'); ?></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;text-align:center;">
            <p style="font-size:28px;margin:0;font-weight:600;color:<?php echo count($cannibalized) > 0 ? '#d63638' : '#00a32a'; ?>;"><?php echo count($cannibalized); ?></p>
            <p style="margin:4px 0 0;color:#50575e;"><?php esc_html_e('Cannibalization risks', 'ai-seo-keeper'); ?></p>
        </div>
    </div>

    <?php if (! empty($cannibalized)) : ?>
        <div style="background:#fff3cd;border:1px solid #ffc107;padding:16px;max-width:1120px;margin-bottom:20px;">
            <h2 style="margin-top:0;color:#856404;"><?php esc_html_e('Keyword Cannibalization', 'ai-seo-keeper'); ?></h2>
            <p style="margin:0 0 12px;color:#856404;"><?php esc_html_e('These keyphrases target multiple pages. Consider consolidating content or differentiating the focus keyphrase for each page.', 'ai-seo-keeper'); ?></p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Keyphrase', 'ai-seo-keeper'); ?></th>
                        <th><?php esc_html_e('Pages targeting it', 'ai-seo-keeper'); ?></th>
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
        </div>
    <?php endif; ?>

    <?php if (! empty($keyphrase_map)) : ?>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;max-width:1120px;">
            <h2 style="margin-top:0;"><?php esc_html_e('All Focus Keyphrases', 'ai-seo-keeper'); ?></h2>
            <table class="widefat striped ai-seo-sortable" id="ai-seo-keywords-table">
                <thead>
                    <tr>
                        <th class="ai-seo-sort" data-col="0"><?php esc_html_e('Keyphrase', 'ai-seo-keeper'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                        <th class="ai-seo-sort" data-col="1"><?php esc_html_e('Page', 'ai-seo-keeper'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                        <th class="ai-seo-sort" data-col="2"><?php esc_html_e('Type', 'ai-seo-keeper'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
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
        <p><?php esc_html_e("No focus keyphrases have been set yet. Add keyphrases in the editor's SEO tab.", 'ai-seo-keeper'); ?></p>
    <?php endif; ?>
</div>