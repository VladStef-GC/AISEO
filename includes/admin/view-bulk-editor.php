<?php

/**
 * Bulk SEO Editor page view.
 *
 * Variables available (set by Admin::render_bulk_editor_page):
 *   $post_type_filter, $paged, $per_page, $post_types,
 *   $query, $total_pages, $nonce, $meta_title_key, $meta_desc_key
 *
 * @package AI_SEO_Keeper
 */

defined('ABSPATH') || exit;

/** @var string          $post_type_filter */
/** @var int             $paged */
/** @var int             $per_page */
/** @var \WP_Post_Type[] $post_types */
/** @var \WP_Query       $query */
/** @var int             $total_pages */
/** @var string          $nonce */
/** @var string          $meta_title_key */
/** @var string          $meta_desc_key */
?>
<div class="wrap">
    <h1><?php esc_html_e('Bulk SEO Editor', 'ai-seo-keeper'); ?></h1>
    <p class="description"><?php esc_html_e('Edit SEO titles and meta descriptions across all your content. Changes are saved via AJAX — no page reload needed.', 'ai-seo-keeper'); ?></p>

    <form method="get" style="margin:16px 0;">
        <input type="hidden" name="page" value="ai-seo-keeper-bulk-editor" />
        <label for="ai-seo-bulk-pt"><strong><?php esc_html_e('Post type:', 'ai-seo-keeper'); ?></strong></label>
        <select name="pt" id="ai-seo-bulk-pt" onchange="this.form.submit()">
            <?php foreach ($post_types as $pt) : ?>
                <option value="<?php echo esc_attr($pt->name); ?>" <?php selected($post_type_filter, $pt->name); ?>><?php echo esc_html($pt->labels->name); ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($query->have_posts()) : ?>
        <table class="widefat striped ai-seo-sortable" id="ai-seo-bulk-table">
            <thead>
                <tr>
                    <th style="width:30%;" class="ai-seo-sort" data-col="0"><?php esc_html_e('Title', 'ai-seo-keeper'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th style="width:35%;" class="ai-seo-sort" data-col="1"><?php esc_html_e('SEO Title', 'ai-seo-keeper'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th style="width:30%;" class="ai-seo-sort" data-col="2"><?php esc_html_e('Meta Description', 'ai-seo-keeper'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th style="width:5%;"></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($query->have_posts()) : $query->the_post();
                    $post_id = get_the_ID();
                    $seo_title_val = get_post_meta($post_id, $meta_title_key, true);
                    $seo_desc_val = get_post_meta($post_id, $meta_desc_key, true);
                ?>
                    <tr data-post-id="<?php echo (int) $post_id; ?>">
                        <td data-sort-value="<?php echo esc_attr(strtolower(get_the_title())); ?>">
                            <strong><a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>"><?php the_title(); ?></a></strong>
                            <div class="row-actions"><span><a href="<?php echo esc_url(get_permalink($post_id)); ?>" target="_blank"><?php esc_html_e('View', 'ai-seo-keeper'); ?></a></span></div>
                        </td>
                        <td data-sort-value="<?php echo esc_attr(strtolower($seo_title_val)); ?>">
                            <input type="text" class="large-text ai-seo-bulk-title" value="<?php echo esc_attr($seo_title_val); ?>" data-original="<?php echo esc_attr($seo_title_val); ?>" />
                        </td>
                        <td data-sort-value="<?php echo esc_attr(strtolower($seo_desc_val)); ?>">
                            <textarea class="large-text ai-seo-bulk-desc" rows="2" data-original="<?php echo esc_attr($seo_desc_val); ?>"><?php echo esc_textarea($seo_desc_val); ?></textarea>
                        </td>
                        <td>
                            <button type="button" class="button button-small ai-seo-bulk-save" disabled><?php esc_html_e('Save', 'ai-seo-keeper'); ?></button>
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
        <p><?php esc_html_e('No posts found for this post type.', 'ai-seo-keeper'); ?></p>
    <?php endif; ?>
</div>