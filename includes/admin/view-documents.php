<?php

/**
 * Document SEO page view.
 *
 * Variables available (set by Admin::render_document_seo_page):
 *   $paged, $per_page, $filter, $query, $total_pages,
 *   $total_docs, $total_with_title, $total_missing_title,
 *   $used_on_map, $nonce, $readiness_banner
 *
 * @package AI_SEO_Captain
 */

defined('ABSPATH') || exit;

/** @var int        $paged */
/** @var int        $per_page */
/** @var string     $filter */
/** @var \WP_Query  $query */
/** @var int        $total_pages */
/** @var int        $total_docs */
/** @var int        $total_with_title */
/** @var int        $total_missing_title */
/** @var array      $used_on_map */
/** @var string     $nonce */
/** @var string     $readiness_banner */

$doc_icons = array(
    'pdf'          => '📕',
    'doc'          => '📘',
    'docx'         => '📘',
    'xls'          => '📗',
    'xlsx'         => '📗',
    'csv'          => '📗',
    'ppt'          => '📙',
    'pptx'         => '📙',
    'odt'          => '📄',
    'ods'          => '📄',
    'odp'          => '📄',
    'rtf'          => '📄',
    'txt'          => '📄',
);

$format_labels = array(
    'pdf'          => 'PDF',
    'doc'          => 'Word (DOC)',
    'docx'         => 'Word (DOCX)',
    'xls'          => 'Excel (XLS)',
    'xlsx'         => 'Excel (XLSX)',
    'csv'          => 'CSV',
    'ppt'          => 'PowerPoint (PPT)',
    'pptx'         => 'PowerPoint (PPTX)',
    'odt'          => 'OpenDocument Text',
    'ods'          => 'OpenDocument Spreadsheet',
    'odp'          => 'OpenDocument Presentation',
    'rtf'          => 'Rich Text',
    'txt'          => 'Plain Text',
);
?>
<div class="wrap">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px;">
        <img src="<?php echo esc_url(AI_SEO_CAPTAIN_URL . 'assets/img/ai-seo-captain-d.svg'); ?>" alt="SEO Captain" style="width:40px;height:40px;" />
        <h1 style="margin:0;"><?php esc_html_e('Document SEO', 'ai-seo-captain'); ?></h1>
    </div>
    <p class="description"><?php esc_html_e('Manage SEO titles and descriptions for documents (PDFs, Word, Excel, PowerPoint, etc.) uploaded to your site. Good metadata improves discoverability in search results.', 'ai-seo-captain'); ?></p>

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
            <p style="font-size:28px;margin:0;font-weight:600;"><?php echo (int) $total_docs; ?></p>
            <p style="margin:4px 0 0;color:#50575e;"><?php esc_html_e('Total documents', 'ai-seo-captain'); ?></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;text-align:center;">
            <p style="font-size:28px;margin:0;font-weight:600;color:#00a32a;"><?php echo (int) $total_with_title; ?></p>
            <p style="margin:4px 0 0;color:#50575e;"><?php esc_html_e('With SEO title', 'ai-seo-captain'); ?></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;text-align:center;">
            <p style="font-size:28px;margin:0;font-weight:600;color:<?php echo $total_missing_title > 0 ? '#d63638' : '#00a32a'; ?>;"><?php echo (int) $total_missing_title; ?></p>
            <p style="margin:4px 0 0;color:#50575e;"><?php esc_html_e('Missing SEO title', 'ai-seo-captain'); ?></p>
        </div>
    </div>

    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin:16px 0;">
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <a href="<?php echo esc_url(add_query_arg('filter', 'all', remove_query_arg('paged'))); ?>" class="button <?php echo 'all' === $filter ? 'button-primary' : ''; ?>"><?php echo esc_html(sprintf(__('All (%d)', 'ai-seo-captain'), $total_docs)); ?></a>
            <a href="<?php echo esc_url(add_query_arg('filter', 'missing_title', remove_query_arg('paged'))); ?>" class="button <?php echo 'missing_title' === $filter ? 'button-primary' : ''; ?>"><?php echo esc_html(sprintf(__('Missing title (%d)', 'ai-seo-captain'), $total_missing_title)); ?></a>
            <a href="<?php echo esc_url(add_query_arg('filter', 'with_title', remove_query_arg('paged'))); ?>" class="button <?php echo 'with_title' === $filter ? 'button-primary' : ''; ?>"><?php echo esc_html(sprintf(__('With title (%d)', 'ai-seo-captain'), $total_with_title)); ?></a>
            <a href="<?php echo esc_url(add_query_arg('filter', 'pdf', remove_query_arg('paged'))); ?>" class="button <?php echo 'pdf' === $filter ? 'button-primary' : ''; ?>"><?php esc_html_e('PDF', 'ai-seo-captain'); ?></a>
            <a href="<?php echo esc_url(add_query_arg('filter', 'word', remove_query_arg('paged'))); ?>" class="button <?php echo 'word' === $filter ? 'button-primary' : ''; ?>"><?php esc_html_e('Word', 'ai-seo-captain'); ?></a>
            <a href="<?php echo esc_url(add_query_arg('filter', 'spreadsheet', remove_query_arg('paged'))); ?>" class="button <?php echo 'spreadsheet' === $filter ? 'button-primary' : ''; ?>"><?php esc_html_e('Spreadsheet', 'ai-seo-captain'); ?></a>
            <a href="<?php echo esc_url(add_query_arg('filter', 'presentation', remove_query_arg('paged'))); ?>" class="button <?php echo 'presentation' === $filter ? 'button-primary' : ''; ?>"><?php esc_html_e('Presentation', 'ai-seo-captain'); ?></a>
        </div>
        <div style="flex:1;min-width:200px;max-width:400px;">
            <input type="text" id="aisc-doc-search" placeholder="<?php esc_attr_e('Search documents by filename…', 'ai-seo-captain'); ?>" style="width:100%;padding:6px 10px;font-size:13px;border:1px solid #8c8f94;border-radius:4px;" />
        </div>
    </div>

    <?php if ($query->have_posts()) : ?>
        <table class="widefat striped ai-seo-sortable" id="ai-seo-doc-table" style="table-layout:fixed;">
            <thead>
                <tr>
                    <th style="width:50px;"></th>
                    <th style="width:22%;" class="ai-seo-sort" data-col="1"><?php esc_html_e('File', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th style="width:26%;" class="ai-seo-sort" data-col="2"><?php esc_html_e('SEO Title', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th style="width:28%;" class="ai-seo-sort" data-col="3"><?php esc_html_e('Description', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th style="width:14%;" class="ai-seo-sort" data-col="4"><?php esc_html_e('Used on', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    <th style="width:50px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($query->have_posts()) : $query->the_post();
                    $att_id = get_the_ID();
                    $filepath = get_attached_file($att_id);
                    $filename = $filepath ? basename($filepath) : '(unknown)';
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    $icon = isset($doc_icons[$ext]) ? $doc_icons[$ext] : '📎';
                    $format_label = isset($format_labels[$ext]) ? $format_labels[$ext] : strtoupper($ext);
                    $filesize = $filepath && file_exists($filepath) ? filesize($filepath) : 0;
                    $seo_title = get_the_title($att_id);
                    $seo_desc = get_post_field('post_excerpt', $att_id);
                    $download_url = wp_get_attachment_url($att_id);

                    // Find pages that link to this document.
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
                        <td style="text-align:center;font-size:24px;"><?php echo $icon; ?></td>
                        <td data-sort-value="<?php echo esc_attr(strtolower($filename)); ?>">
                            <strong><?php echo esc_html($filename); ?></strong>
                            <div style="margin-top:2px;font-size:11px;color:#787c82;">
                                <span><?php echo esc_html($format_label); ?></span>
                                <?php if ($filesize > 0) : ?>
                                    <span style="margin-left:4px;">· <?php echo esc_html(size_format($filesize)); ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="margin-top:2px;">
                                <?php if ($download_url) : ?>
                                    <a href="<?php echo esc_url($download_url); ?>" target="_blank" style="font-size:11px;color:#50575e;"><?php esc_html_e('View file', 'ai-seo-captain'); ?></a>
                                    <span style="color:#dcdcde;margin:0 3px;">|</span>
                                <?php endif; ?>
                                <a href="<?php echo esc_url(admin_url('upload.php?item=' . $att_id)); ?>" style="font-size:11px;color:#50575e;"><?php esc_html_e('Edit in Media', 'ai-seo-captain'); ?></a>
                                <br/>
                                <a href="#" class="ai-seo-purge-media" data-att-id="<?php echo (int) $att_id; ?>" style="font-size:11px;color:#d63638;"><?php esc_html_e('Purge Cache', 'ai-seo-captain'); ?></a>
                            </div>
                        </td>
                        <td data-sort-value="<?php echo esc_attr(strtolower($seo_title)); ?>">
                            <input type="text" class="large-text ai-seo-doc-title" value="<?php echo esc_attr($seo_title); ?>" data-original="<?php echo esc_attr($seo_title); ?>" placeholder="<?php esc_attr_e('Enter SEO title…', 'ai-seo-captain'); ?>" />
                        </td>
                        <td data-sort-value="<?php echo esc_attr(strtolower($seo_desc)); ?>">
                            <textarea class="large-text ai-seo-doc-desc" rows="2" data-original="<?php echo esc_attr($seo_desc); ?>" placeholder="<?php esc_attr_e('Enter description…', 'ai-seo-captain'); ?>"><?php echo esc_textarea($seo_desc); ?></textarea>
                        </td>
                        <td data-sort-value="<?php echo esc_attr(strtolower($used_on_first_title)); ?>">
                            <?php if ($used_on_count > 0) : ?>
                                <?php $first_pid = array_key_first($used_on_pages); ?>
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . $first_pid . '&action=edit')); ?>" style="font-size:12px;"><?php echo esc_html($used_on_pages[$first_pid]); ?></a>
                                <?php if ($used_on_count > 1) : ?>
                                    <span class="ai-seo-used-toggle" style="display:inline-block;margin-left:4px;background:#2271b1;color:#fff;border-radius:10px;padding:0 7px;font-size:11px;cursor:pointer;vertical-align:middle;" title="<?php echo esc_attr(sprintf(__('Used on %d pages', 'ai-seo-captain'), $used_on_count)); ?>">+<?php echo $used_on_count - 1; ?></span>
                                    <div class="ai-seo-used-list" style="display:none;margin-top:6px;">
                                        <?php $i = 0;
                                        foreach ($used_on_pages as $pid => $ptitle) : $i++;
                                            if (1 === $i) continue; ?>
                                            <div style="margin-bottom:3px;"><a href="<?php echo esc_url(admin_url('post.php?post=' . $pid . '&action=edit')); ?>" style="font-size:12px;"><?php echo esc_html($ptitle); ?></a></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else : ?>
                                <span style="color:#50575e;font-size:12px;"><?php esc_html_e('Unattached', 'ai-seo-captain'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="button button-small ai-seo-doc-save" disabled><?php esc_html_e('Save', 'ai-seo-captain'); ?></button>
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
        <p><?php echo 'missing_title' === $filter ? esc_html__('All documents have SEO titles — great!', 'ai-seo-captain') : ('with_title' === $filter ? esc_html__('No documents have SEO titles yet.', 'ai-seo-captain') : esc_html__('No documents found.', 'ai-seo-captain')); ?></p>
    <?php endif; ?>

</div>