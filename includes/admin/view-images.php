<?php

/**
 * Image SEO page view.
 *
 * Variables available (set by Admin::render_image_seo_page):
 *   $paged, $per_page, $filter, $query, $total_pages,
 *   $total_images, $total_with_alt, $total_missing_alt,
 *   $used_on_map, $nonce
 *
 * @package AI_SEO_Keeper
 */

defined('ABSPATH') || exit;

/** @var int        $paged */
/** @var int        $per_page */
/** @var string     $filter */
/** @var \WP_Query  $query */
/** @var int        $total_pages */
/** @var int        $total_images */
/** @var int        $total_with_alt */
/** @var int        $total_missing_alt */
/** @var array      $used_on_map */
/** @var string     $nonce */
?>
<div class="wrap">
    <h1><?php esc_html_e('Image SEO', 'ai-seo-keeper'); ?></h1>
    <p class="description"><?php esc_html_e('Manage alt text across your published images. Only images attached to or used as featured image on published content are shown.', 'ai-seo-keeper'); ?></p>

    <?php echo $readiness_banner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

    <div style="display:grid;grid-template-columns:repeat(3,minmax(160px,1fr));gap:16px;max-width:700px;margin:20px 0;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;text-align:center;">
            <p style="font-size:28px;margin:0;font-weight:600;"><?php echo $total_images; ?></p>
            <p style="margin:4px 0 0;color:#50575e;"><?php esc_html_e('Total images', 'ai-seo-keeper'); ?></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;text-align:center;">
            <p style="font-size:28px;margin:0;font-weight:600;color:#00a32a;"><?php echo $total_with_alt; ?></p>
            <p style="margin:4px 0 0;color:#50575e;"><?php esc_html_e('With alt text', 'ai-seo-keeper'); ?></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;text-align:center;">
            <p style="font-size:28px;margin:0;font-weight:600;color:<?php echo $total_missing_alt > 0 ? '#d63638' : '#00a32a'; ?>;"><?php echo $total_missing_alt; ?></p>
            <p style="margin:4px 0 0;color:#50575e;"><?php esc_html_e('Missing alt text', 'ai-seo-keeper'); ?></p>
        </div>
    </div>

    <div style="margin:16px 0;">
        <a href="<?php echo esc_url(add_query_arg('filter', 'all', remove_query_arg('paged'))); ?>" class="button <?php echo 'all' === $filter ? 'button-primary' : ''; ?>"><?php echo esc_html(sprintf(__('All images (%d)', 'ai-seo-keeper'), $total_images)); ?></a>
        <a href="<?php echo esc_url(add_query_arg('filter', 'missing_alt', remove_query_arg('paged'))); ?>" class="button <?php echo 'missing_alt' === $filter ? 'button-primary' : ''; ?>"><?php echo esc_html(sprintf(__('Missing alt (%d)', 'ai-seo-keeper'), $total_missing_alt)); ?></a>
        <a href="<?php echo esc_url(add_query_arg('filter', 'with_alt', remove_query_arg('paged'))); ?>" class="button <?php echo 'with_alt' === $filter ? 'button-primary' : ''; ?>"><?php echo esc_html(sprintf(__('With alt (%d)', 'ai-seo-keeper'), $total_with_alt)); ?></a>
    </div>

    <?php if ($query->have_posts()) : ?>
        <table class="widefat striped ai-seo-sortable" id="ai-seo-image-table">
            <thead>
                <tr>
                    <th style="width:80px;"><?php esc_html_e('Image', 'ai-seo-keeper'); ?></th>
                    <th style="width:25%;" class="ai-seo-sort" data-col="1"><?php esc_html_e('File', 'ai-seo-keeper'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th style="width:40%;" class="ai-seo-sort" data-col="2"><?php esc_html_e('Alt text', 'ai-seo-keeper'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th style="width:20%;" class="ai-seo-sort" data-col="3"><?php esc_html_e('Used on', 'ai-seo-keeper'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th style="width:5%;"></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($query->have_posts()) : $query->the_post();
                    $att_id = get_the_ID();
                    $thumb = wp_get_attachment_image_url($att_id, 'thumbnail');
                    $filename = basename(get_attached_file($att_id));
                    $alt = get_post_meta($att_id, '_wp_attachment_image_alt', true);
                    $parent_id = wp_get_post_parent_id($att_id);
                    $used_on_pages = array();
                    if ($parent_id && 'publish' === get_post_status($parent_id)) {
                        $used_on_pages[$parent_id] = get_the_title($parent_id);
                    }
                    if (isset($used_on_map[$att_id])) {
                        foreach ($used_on_map[$att_id] as $pid => $ptitle) {
                            $used_on_pages[$pid] = $ptitle;
                        }
                    }
                    $used_on_count = count($used_on_pages);
                    $used_on_first_title = $used_on_count > 0 ? reset($used_on_pages) : '';
                ?>
                    <tr data-att-id="<?php echo (int) $att_id; ?>">
                        <td>
                            <?php if ($thumb) : ?>
                                <img src="<?php echo esc_url($thumb); ?>" alt="" style="width:60px;height:60px;object-fit:cover;border-radius:4px;" />
                            <?php endif; ?>
                        </td>
                        <td data-sort-value="<?php echo esc_attr(strtolower($filename)); ?>">
                            <strong><?php echo esc_html($filename); ?></strong>
                            <div style="margin-top:2px;"><a href="<?php echo esc_url(admin_url('upload.php?item=' . $att_id)); ?>" style="font-size:12px;color:#50575e;"><?php esc_html_e('Edit in Media', 'ai-seo-keeper'); ?></a></div>
                        </td>
                        <td data-sort-value="<?php echo esc_attr(strtolower($alt)); ?>">
                            <input type="text" class="large-text ai-seo-img-alt" value="<?php echo esc_attr($alt); ?>" data-original="<?php echo esc_attr($alt); ?>" placeholder="<?php esc_attr_e('Enter alt text\u2026', 'ai-seo-keeper'); ?>" />
                        </td>
                        <td data-sort-value="<?php echo esc_attr(strtolower($used_on_first_title)); ?>">
                            <?php if ($used_on_count > 0) : ?>
                                <?php $first_pid = array_key_first($used_on_pages); ?>
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . $first_pid . '&action=edit')); ?>" style="font-size:12px;"><?php echo esc_html($used_on_pages[$first_pid]); ?></a>
                                <?php if ($used_on_count > 1) : ?>
                                    <span class="ai-seo-used-toggle" style="display:inline-block;margin-left:4px;background:#2271b1;color:#fff;border-radius:10px;padding:0 7px;font-size:11px;cursor:pointer;vertical-align:middle;" title="<?php echo esc_attr(sprintf(__('Used on %d pages', 'ai-seo-keeper'), $used_on_count)); ?>">+<?php echo $used_on_count - 1; ?></span>
                                    <div class="ai-seo-used-list" style="display:none;margin-top:6px;">
                                        <?php $i = 0;
                                        foreach ($used_on_pages as $pid => $ptitle) : $i++;
                                            if (1 === $i) continue; ?>
                                            <div style="margin-bottom:3px;"><a href="<?php echo esc_url(admin_url('post.php?post=' . $pid . '&action=edit')); ?>" style="font-size:12px;"><?php echo esc_html($ptitle); ?></a></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else : ?>
                                <span style="color:#50575e;font-size:12px;"><?php esc_html_e('Unattached', 'ai-seo-keeper'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="button button-small ai-seo-img-save" disabled><?php esc_html_e('Save', 'ai-seo-keeper'); ?></button>
                        </td>
                    </tr>
                <?php endwhile;
                wp_reset_postdata(); ?>
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
        <p><?php echo 'missing_alt' === $filter ? esc_html__('All images have alt text — great!', 'ai-seo-keeper') : ('with_alt' === $filter ? esc_html__('No images have alt text yet.', 'ai-seo-keeper') : esc_html__('No published images found.', 'ai-seo-keeper')); ?></p>
    <?php endif; ?>
</div>