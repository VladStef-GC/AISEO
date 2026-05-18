<?php

/**
 * Video SEO page view.
 *
 * Variables available (set by Admin::render_video_seo_page):
 *   $paged, $per_page, $filter, $videos, $total_pages,
 *   $total_videos, $total_with_desc, $total_missing_desc,
 *   $nonce, $readiness_banner
 *
 * @package AI_SEO_Captain
 */

defined('ABSPATH') || exit;

/** @var int    $paged */
/** @var int    $per_page */
/** @var string $filter */
/** @var array  $videos */
/** @var int    $total_pages */
/** @var int    $total_videos */
/** @var int    $total_with_desc */
/** @var int    $total_missing_desc */
/** @var string $nonce */
/** @var string $readiness_banner */
?>
<div class="wrap">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px;">
        <img src="<?php echo esc_url(AI_SEO_CAPTAIN_URL . 'assets/img/ai-seo-captain-d.svg'); ?>" alt="SEO Captain" style="width:40px;height:40px;" />
        <h1 style="margin:0;"><?php esc_html_e('Video SEO', 'ai-seo-captain'); ?></h1>
    </div>
    <p class="description"><?php esc_html_e('Manage SEO metadata for videos embedded in or attached to your published content. Videos detected from YouTube, Vimeo, and self-hosted files.', 'ai-seo-captain'); ?></p>

    <?php echo $readiness_banner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
    ?>

    <?php
    echo \AI_SEO_Captain\Admin::render_banner(
        'is-live',
        esc_html__('Live changes', 'ai-seo-captain'),
        esc_html__('All edits saved on this page are published instantly and visible to visitors right away — no additional publish step is needed.', 'ai-seo-captain')
    );
    ?>

    <div style="display:grid;grid-template-columns:repeat(3,minmax(160px,1fr));gap:16px;max-width:700px;margin:20px 0;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;text-align:center;">
            <p style="font-size:28px;margin:0;font-weight:600;"><?php echo (int) $total_videos; ?></p>
            <p style="margin:4px 0 0;color:#50575e;"><?php esc_html_e('Total videos', 'ai-seo-captain'); ?></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;text-align:center;">
            <p style="font-size:28px;margin:0;font-weight:600;color:#00a32a;"><?php echo (int) $total_with_desc; ?></p>
            <p style="margin:4px 0 0;color:#50575e;"><?php esc_html_e('With description', 'ai-seo-captain'); ?></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;text-align:center;">
            <p style="font-size:28px;margin:0;font-weight:600;color:<?php echo $total_missing_desc > 0 ? '#d63638' : '#00a32a'; ?>;"><?php echo (int) $total_missing_desc; ?></p>
            <p style="margin:4px 0 0;color:#50575e;"><?php esc_html_e('Missing description', 'ai-seo-captain'); ?></p>
        </div>
    </div>

    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin:16px 0;">
        <div style="display:flex;gap:6px;">
            <a href="<?php echo esc_url(add_query_arg('filter', 'all', remove_query_arg('paged'))); ?>" class="button <?php echo 'all' === $filter ? 'button-primary' : ''; ?>"><?php echo esc_html(sprintf(__('All (%d)', 'ai-seo-captain'), $total_videos)); ?></a>
            <a href="<?php echo esc_url(add_query_arg('filter', 'missing_desc', remove_query_arg('paged'))); ?>" class="button <?php echo 'missing_desc' === $filter ? 'button-primary' : ''; ?>"><?php echo esc_html(sprintf(__('Missing description (%d)', 'ai-seo-captain'), $total_missing_desc)); ?></a>
            <a href="<?php echo esc_url(add_query_arg('filter', 'with_desc', remove_query_arg('paged'))); ?>" class="button <?php echo 'with_desc' === $filter ? 'button-primary' : ''; ?>"><?php echo esc_html(sprintf(__('With description (%d)', 'ai-seo-captain'), $total_with_desc)); ?></a>
            <a href="<?php echo esc_url(add_query_arg('filter', 'youtube', remove_query_arg('paged'))); ?>" class="button <?php echo 'youtube' === $filter ? 'button-primary' : ''; ?>"><?php esc_html_e('YouTube', 'ai-seo-captain'); ?></a>
            <a href="<?php echo esc_url(add_query_arg('filter', 'vimeo', remove_query_arg('paged'))); ?>" class="button <?php echo 'vimeo' === $filter ? 'button-primary' : ''; ?>"><?php esc_html_e('Vimeo', 'ai-seo-captain'); ?></a>
            <a href="<?php echo esc_url(add_query_arg('filter', 'self_hosted', remove_query_arg('paged'))); ?>" class="button <?php echo 'self_hosted' === $filter ? 'button-primary' : ''; ?>"><?php esc_html_e('Self-hosted', 'ai-seo-captain'); ?></a>
        </div>
        <div style="flex:1;min-width:200px;max-width:400px;">
            <input type="text" id="aisc-video-search" placeholder="<?php esc_attr_e('Search videos by title…', 'ai-seo-captain'); ?>" style="width:100%;padding:6px 10px;font-size:13px;border:1px solid #8c8f94;border-radius:4px;" />
        </div>
    </div>

    <?php if (! empty($videos)) : ?>
        <table class="widefat striped ai-seo-sortable" id="ai-seo-video-table" style="table-layout:fixed;">
            <thead>
                <tr>
                    <th style="width:90px;"><?php esc_html_e('Preview', 'ai-seo-captain'); ?></th>
                    <th style="width:22%;" class="ai-seo-sort" data-col="1"><?php esc_html_e('Video', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th style="width:30%;" class="ai-seo-sort" data-col="2"><?php esc_html_e('SEO Title', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th style="width:30%;" class="ai-seo-sort" data-col="3"><?php esc_html_e('SEO Description', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th style="width:14%;" class="ai-seo-sort" data-col="4"><?php esc_html_e('Used on', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th style="width:50px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($videos as $v) :
                    $thumb_style = 'width:80px;height:50px;object-fit:cover;border-radius:4px;background:#f0f0f1;';
                    $provider_badge = '';
                    $detail_line = '';
                    if ('youtube' === $v['provider']) {
                        $provider_badge = '<span style="display:inline-block;background:#ff0000;color:#fff;font-size:10px;padding:1px 6px;border-radius:3px;font-weight:600;">YouTube</span>';
                    } elseif ('vimeo' === $v['provider']) {
                        $provider_badge = '<span style="display:inline-block;background:#1ab7ea;color:#fff;font-size:10px;padding:1px 6px;border-radius:3px;font-weight:600;">Vimeo</span>';
                    } else {
                        $provider_badge = '<span style="display:inline-block;background:#50575e;color:#fff;font-size:10px;padding:1px 6px;border-radius:3px;font-weight:600;">Self-hosted</span>';
                    }
                    if (! empty($v['format'])) {
                        $detail_line .= strtoupper($v['format']);
                    }
                    if (! empty($v['filesize'])) {
                        $detail_line .= ('' !== $detail_line ? ' · ' : '') . size_format($v['filesize']);
                    }
                ?>
                    <tr data-video-key="<?php echo esc_attr($v['key']); ?>" data-post-id="<?php echo (int) $v['post_id']; ?>" data-att-id="<?php echo (int) $v['att_id']; ?>">
                        <td>
                            <?php if (! empty($v['thumbnail'])) : ?>
                                <img src="<?php echo esc_url($v['thumbnail']); ?>" alt="" style="<?php echo $thumb_style; ?>" />
                            <?php else : ?>
                                <span style="display:inline-block;<?php echo $thumb_style; ?>text-align:center;line-height:50px;font-size:20px;">🎬</span>
                            <?php endif; ?>
                        </td>
                        <td data-sort-value="<?php echo esc_attr(strtolower($v['label'])); ?>">
                            <strong><?php echo esc_html($v['label']); ?></strong>
                            <div style="margin-top:2px;font-size:11px;color:#787c82;">
                                <?php echo $provider_badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                                ?>
                                <?php if ('' !== $detail_line) : ?>
                                    <span style="margin-left:4px;"><?php echo esc_html($detail_line); ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="margin-top:2px;">
                                <a href="#" class="ai-seo-purge-media" data-att-id="<?php echo (int) $v['att_id']; ?>" data-post-id="<?php echo (int) $v['post_id']; ?>" style="font-size:11px;color:#d63638;"><?php esc_html_e('Purge Cache', 'ai-seo-captain'); ?></a>
                            </div>
                        </td>
                        <td data-sort-value="<?php echo esc_attr(strtolower($v['seo_title'])); ?>">
                            <input type="text" class="large-text ai-seo-vid-title" value="<?php echo esc_attr($v['seo_title']); ?>" data-original="<?php echo esc_attr($v['seo_title']); ?>" placeholder="<?php esc_attr_e('Enter video SEO title…', 'ai-seo-captain'); ?>" />
                        </td>
                        <td data-sort-value="<?php echo esc_attr(strtolower($v['seo_description'])); ?>">
                            <textarea class="large-text ai-seo-vid-desc" rows="2" data-original="<?php echo esc_attr($v['seo_description']); ?>" placeholder="<?php esc_attr_e('Enter video SEO description…', 'ai-seo-captain'); ?>"><?php echo esc_textarea($v['seo_description']); ?></textarea>
                        </td>
                        <td data-sort-value="<?php echo esc_attr(strtolower($v['page_title'])); ?>">
                            <?php if (! empty($v['page_title'])) : ?>
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . $v['post_id'] . '&action=edit')); ?>" style="font-size:12px;"><?php echo esc_html($v['page_title']); ?></a>
                            <?php else : ?>
                                <span style="color:#a7aaad;font-size:12px;font-style:italic;"><?php esc_html_e('Unattached', 'ai-seo-captain'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="button button-small ai-seo-vid-save" disabled><?php esc_html_e('Save', 'ai-seo-captain'); ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom" style="margin-top:12px;">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links(array(
                        'base'    => add_query_arg('paged', '%#%'),
                        'format'  => '',
                        'current' => $paged,
                        'total'   => $total_pages,
                        'type'    => 'plain',
                    ));
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <p><?php echo 'missing_desc' === $filter ? esc_html__('All videos have descriptions — great!', 'ai-seo-captain') : ('with_desc' === $filter ? esc_html__('No videos have descriptions yet.', 'ai-seo-captain') : esc_html__('No videos found in published content.', 'ai-seo-captain')); ?></p>
    <?php endif; ?>

</div>