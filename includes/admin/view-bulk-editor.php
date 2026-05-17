<?php

/**
 * Bulk SEO Editor page view.
 *
 * Variables available (set by Admin::render_bulk_editor_page):
 *   $post_type_filter, $paged, $per_page, $post_types,
 *   $query, $total_pages, $nonce, $meta_title_key, $meta_desc_key,
 *   $has_woo, $tree_data
 *
 * @package AI_SEO_Captain
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
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px;">
        <img src="<?php echo esc_url(AI_SEO_KEEPER_URL . 'assets/img/ai-seo-captain-d.svg'); ?>" alt="SEO Captain" style="width:40px;height:40px;" />
        <h1 style="margin:0;"><?php esc_html_e('Bulk SEO Editor', 'ai-seo-captain'); ?></h1>
    </div>
    <p class="description"><?php esc_html_e('Edit SEO titles and meta descriptions across all your content. Changes are saved via AJAX — no page reload needed.', 'ai-seo-captain'); ?></p>

    <?php echo $readiness_banner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
    ?>

    <div class="ai-seo-captain-notice is-live">
        <span class="ai-seo-captain-notice__icon" aria-hidden="true">&#9888;</span>
        <div class="ai-seo-captain-notice__body">
            <strong class="ai-seo-captain-notice__title"><?php esc_html_e('Live changes', 'ai-seo-captain'); ?></strong>
            <span class="ai-seo-captain-notice__text"><?php esc_html_e('All edits saved on this page are published instantly and visible to visitors right away — no additional publish step is needed.', 'ai-seo-captain'); ?></span>
        </div>
    </div>

    <!-- Filters & Search -->
    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin:16px 0;">
        <form method="get" style="margin:0;display:flex;align-items:center;gap:8px;">
            <input type="hidden" name="page" value="ai-seo-captain-bulk-editor" />
            <label for="ai-seo-bulk-pt"><strong><?php esc_html_e('Post type:', 'ai-seo-captain'); ?></strong></label>
            <select name="pt" id="ai-seo-bulk-pt" onchange="this.form.submit()">
                <?php foreach ($post_types as $pt) : ?>
                    <?php if ($pt->name === 'product' && ! $has_woo) continue; ?>
                    <option value="<?php echo esc_attr($pt->name); ?>" <?php selected($post_type_filter, $pt->name); ?>><?php echo esc_html($pt->labels->name); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <div style="flex:1;min-width:200px;max-width:400px;">
            <input type="text" id="aisc-bulk-search" placeholder="<?php esc_attr_e('Search pages by title...', 'ai-seo-captain'); ?>" style="width:100%;padding:6px 10px;font-size:13px;border:1px solid #8c8f94;border-radius:4px;" />
        </div>
        <label style="display:flex;align-items:center;gap:4px;font-size:13px;cursor:pointer;">
            <input type="checkbox" id="aisc-bulk-col-keyphrase" /> <?php esc_html_e('Keyphrase', 'ai-seo-captain'); ?>
        </label>
        <label style="display:flex;align-items:center;gap:4px;font-size:13px;cursor:pointer;">
            <input type="checkbox" id="aisc-bulk-col-keywords" /> <?php esc_html_e('Keywords', 'ai-seo-captain'); ?>
        </label>
    </div>

    <?php if ($query->have_posts()) : ?>
        <table class="widefat striped ai-seo-sortable" id="ai-seo-bulk-table" style="table-layout:fixed;">
            <thead>
                <tr>
                    <th style="width:40px;text-align:center;">#</th>
                    <th class="ai-seo-sort aisc-col-title" data-col="1"><?php esc_html_e('Title', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th class="ai-seo-sort aisc-col-seotitle" data-col="2"><?php esc_html_e('SEO Title', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th class="ai-seo-sort aisc-col-desc" data-col="3"><?php esc_html_e('Meta Description', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th class="aisc-col-keyphrase ai-seo-sort" data-col="4" style="display:none;"><?php esc_html_e('Keyphrase', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th class="aisc-col-keywords ai-seo-sort" data-col="5" style="display:none;"><?php esc_html_e('Keywords', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th style="width:50px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $keyphrase_meta_key = \AI_SEO_Captain\Admin::FOCUS_KEYPHRASE_META_KEY;
                $keywords_meta_key = \AI_SEO_Captain\Admin::KEYWORDS_META_KEY;
                $row_counter = ($paged - 1) * $per_page;
                while ($query->have_posts()) : $query->the_post();
                    $row_counter++;
                    $post_id = get_the_ID();
                    $seo_title_val = get_post_meta($post_id, $meta_title_key, true);
                    $seo_desc_val = get_post_meta($post_id, $meta_desc_key, true);
                    $keyphrase_val = get_post_meta($post_id, $keyphrase_meta_key, true);
                    $keywords_val = (string) get_post_meta($post_id, $keywords_meta_key, true);
                ?>
                    <tr data-post-id="<?php echo (int) $post_id; ?>">
                        <td style="text-align:center;color:#787c82;font-size:12px;" class="aisc-row-num"><?php echo (int) $row_counter; ?></td>
                        <td data-sort-value="<?php echo esc_attr(strtolower(get_the_title())); ?>">
                            <strong><a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>"><?php the_title(); ?></a></strong>
                            <div class="row-actions"><span><a href="<?php echo esc_url(get_permalink($post_id)); ?>" target="_blank"><?php esc_html_e('View', 'ai-seo-captain'); ?></a></span></div>
                        </td>
                        <td data-sort-value="<?php echo esc_attr(strtolower($seo_title_val)); ?>">
                            <input type="text" class="large-text ai-seo-bulk-title" value="<?php echo esc_attr($seo_title_val); ?>" data-original="<?php echo esc_attr($seo_title_val); ?>" />
                        </td>
                        <td data-sort-value="<?php echo esc_attr(strtolower($seo_desc_val)); ?>">
                            <textarea class="large-text ai-seo-bulk-desc" rows="2" data-original="<?php echo esc_attr($seo_desc_val); ?>"><?php echo esc_textarea($seo_desc_val); ?></textarea>
                        </td>
                        <td class="aisc-col-keyphrase" style="display:none;" data-sort-value="<?php echo esc_attr(strtolower($keyphrase_val)); ?>">
                            <input type="text" class="large-text ai-seo-bulk-keyphrase" value="<?php echo esc_attr($keyphrase_val); ?>" data-original="<?php echo esc_attr($keyphrase_val); ?>" placeholder="<?php esc_attr_e('Focus keyphrase…', 'ai-seo-captain'); ?>" />
                        </td>
                        <td class="aisc-col-keywords" style="display:none;" data-sort-value="<?php echo esc_attr(strtolower($keywords_val)); ?>">
                            <input type="text" class="large-text ai-seo-bulk-keywords" value="<?php echo esc_attr($keywords_val); ?>" data-original="<?php echo esc_attr($keywords_val); ?>" placeholder="<?php esc_attr_e('keyword1, keyword2, …', 'ai-seo-captain'); ?>" />
                        </td>
                        <td>
                            <button type="button" class="button button-small ai-seo-bulk-save" disabled><?php esc_html_e('Save', 'ai-seo-captain'); ?></button>
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
        <p><?php esc_html_e('No posts found for this post type.', 'ai-seo-captain'); ?></p>
    <?php endif; ?>

    <!-- SITE STRUCTURE TREE -->
    <?php if (! empty($tree_data)) : ?>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:20px;margin-top:30px;max-width:1120px;">
            <h2 style="margin:0 0 4px;"><?php esc_html_e('Site Structure', 'ai-seo-captain'); ?></h2>
            <p class="description" style="margin:0 0 16px;"><?php esc_html_e('Visual hierarchy of your indexed pages. Click +/− to expand or collapse branches.', 'ai-seo-captain'); ?></p>
            <div style="display:flex;gap:8px;margin-bottom:12px;">
                <button type="button" id="aisc-tree-expand-all" class="button button-small"><?php esc_html_e('Expand All', 'ai-seo-captain'); ?></button>
                <button type="button" id="aisc-tree-collapse-all" class="button button-small"><?php esc_html_e('Collapse All', 'ai-seo-captain'); ?></button>
            </div>
            <div id="aisc-site-tree" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,sans-serif;font-size:13px;line-height:1.6;"></div>
        </div>
    <?php endif; ?>

</div>