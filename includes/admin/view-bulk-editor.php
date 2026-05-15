<?php

/**
 * Bulk SEO Editor page view.
 *
 * Variables available (set by Admin::render_bulk_editor_page):
 *   $post_type_filter, $paged, $per_page, $post_types,
 *   $query, $total_pages, $nonce, $meta_title_key, $meta_desc_key,
 *   $has_woo, $tree_data
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
/** @var string          $readiness_banner */
/** @var bool            $has_woo */
/** @var array           $tree_data */
?>
<div class="wrap">
    <h1><?php esc_html_e('Bulk SEO Editor', 'ai-seo-keeper'); ?></h1>
    <p class="description"><?php esc_html_e('Edit SEO titles and meta descriptions across all your content. Changes are saved via AJAX — no page reload needed.', 'ai-seo-keeper'); ?></p>

    <?php echo $readiness_banner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
    ?>

    <!-- Filters & Search -->
    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin:16px 0;">
        <form method="get" style="margin:0;display:flex;align-items:center;gap:8px;">
            <input type="hidden" name="page" value="ai-seo-keeper-bulk-editor" />
            <label for="ai-seo-bulk-pt"><strong><?php esc_html_e('Post type:', 'ai-seo-keeper'); ?></strong></label>
            <select name="pt" id="ai-seo-bulk-pt" onchange="this.form.submit()">
                <?php foreach ($post_types as $pt) : ?>
                    <?php if ($pt->name === 'product' && ! $has_woo) continue; ?>
                    <option value="<?php echo esc_attr($pt->name); ?>" <?php selected($post_type_filter, $pt->name); ?>><?php echo esc_html($pt->labels->name); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <div style="flex:1;min-width:200px;max-width:400px;">
            <input type="text" id="aisk-bulk-search" placeholder="<?php esc_attr_e('Search pages by title...', 'ai-seo-keeper'); ?>" style="width:100%;padding:6px 10px;font-size:13px;border:1px solid #8c8f94;border-radius:4px;" />
        </div>
        <label style="display:flex;align-items:center;gap:4px;font-size:13px;cursor:pointer;">
            <input type="checkbox" id="aisk-bulk-col-keyphrase" /> <?php esc_html_e('Keyphrase', 'ai-seo-keeper'); ?>
        </label>
        <label style="display:flex;align-items:center;gap:4px;font-size:13px;cursor:pointer;">
            <input type="checkbox" id="aisk-bulk-col-keywords" /> <?php esc_html_e('Keywords', 'ai-seo-keeper'); ?>
        </label>
    </div>

    <?php if ($query->have_posts()) : ?>
        <table class="widefat striped ai-seo-sortable" id="ai-seo-bulk-table" style="table-layout:fixed;">
            <thead>
                <tr>
                    <th style="width:40px;text-align:center;">#</th>
                    <th class="ai-seo-sort aisk-col-title" data-col="1"><?php esc_html_e('Title', 'ai-seo-keeper'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th class="ai-seo-sort aisk-col-seotitle" data-col="2"><?php esc_html_e('SEO Title', 'ai-seo-keeper'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th class="ai-seo-sort aisk-col-desc" data-col="3"><?php esc_html_e('Meta Description', 'ai-seo-keeper'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th class="aisk-col-keyphrase ai-seo-sort" data-col="4" style="display:none;"><?php esc_html_e('Keyphrase', 'ai-seo-keeper'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th class="aisk-col-keywords ai-seo-sort" data-col="5" style="display:none;"><?php esc_html_e('Keywords', 'ai-seo-keeper'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th style="width:50px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $keyphrase_meta_key = \AI_SEO_Keeper\Admin::FOCUS_KEYPHRASE_META_KEY;
                $row_counter = ($paged - 1) * $per_page;
                while ($query->have_posts()) : $query->the_post();
                    $row_counter++;
                    $post_id = get_the_ID();
                    $seo_title_val = get_post_meta($post_id, $meta_title_key, true);
                    $seo_desc_val = get_post_meta($post_id, $meta_desc_key, true);
                    $keyphrase_val = get_post_meta($post_id, $keyphrase_meta_key, true);
                    $post_terms = array();
                    foreach (get_object_taxonomies(get_post_type(), 'objects') as $tax) {
                        if (! $tax->public) continue;
                        $terms = get_the_terms($post_id, $tax->name);
                        if (! empty($terms) && ! is_wp_error($terms)) {
                            foreach ($terms as $t) {
                                $post_terms[] = $t->name;
                            }
                        }
                    }
                    $keywords_val = implode(', ', $post_terms);
                ?>
                    <tr data-post-id="<?php echo (int) $post_id; ?>">
                        <td style="text-align:center;color:#787c82;font-size:12px;" class="aisk-row-num"><?php echo (int) $row_counter; ?></td>
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
                        <td class="aisk-col-keyphrase" style="display:none;font-size:13px;color:#1d2327;" data-sort-value="<?php echo esc_attr(strtolower($keyphrase_val)); ?>"><?php echo esc_html($keyphrase_val); ?></td>
                        <td class="aisk-col-keywords" style="display:none;font-size:13px;color:#50575e;" data-sort-value="<?php echo esc_attr(strtolower($keywords_val)); ?>"><?php echo esc_html($keywords_val); ?></td>
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

    <!-- SITE STRUCTURE TREE -->
    <?php if (! empty($tree_data)) : ?>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:20px;margin-top:30px;max-width:1120px;">
            <h2 style="margin:0 0 4px;"><?php esc_html_e('Site Structure', 'ai-seo-keeper'); ?></h2>
            <p class="description" style="margin:0 0 16px;"><?php esc_html_e('Visual hierarchy of your indexed pages. Click +/− to expand or collapse branches.', 'ai-seo-keeper'); ?></p>
            <div style="display:flex;gap:8px;margin-bottom:12px;">
                <button type="button" id="aisk-tree-expand-all" class="button button-small"><?php esc_html_e('Expand All', 'ai-seo-keeper'); ?></button>
                <button type="button" id="aisk-tree-collapse-all" class="button button-small"><?php esc_html_e('Collapse All', 'ai-seo-keeper'); ?></button>
            </div>
            <div id="aisk-site-tree" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,sans-serif;font-size:13px;line-height:1.6;"></div>
        </div>
    <?php endif; ?>

</div>