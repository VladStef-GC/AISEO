<?php

/**
 * Dashboard page view.
 *
 * Variables available (set by Admin::render_dashboard):
 *   $summary, $audit_summary, $audit_rows, $options,
 *   $sync_count, $frontend_conflict, $frontend_enabled,
 *   $frontend_override_conflicts, $llms_url, $llms_full_url, $sitemap_url
 *
 * @package AI_SEO_Keeper
 */

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1>AI SEO Keeper</h1>
    <p>AI SEO Keeper now covers AI-assisted metadata workflows, saved page-level SEO overrides, audit workflows, discovery documents, richer schema, and refresh signaling without silently fighting the existing SEO stack.</p>

    <?php if ($sync_count > 0) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html(sprintf('Site index synced. %d content records stored.', $sync_count)); ?></p>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(3,minmax(220px,1fr));gap:16px;max-width:1120px;margin-top:24px;">
        <div class="ai-seo-stat-card">
            <h2>Indexed content</h2>
            <p style="font-size:28px;margin:0;"><?php echo esc_html((string) $summary['total_items']); ?></p>
        </div>
        <div class="ai-seo-stat-card">
            <h2>Provider</h2>
            <p style="font-size:20px;margin:0;"><?php echo esc_html(strtoupper($options['provider'])); ?></p>
        </div>
        <div class="ai-seo-stat-card">
            <h2>Last sync</h2>
            <p style="font-size:16px;margin:0;"><?php echo $summary['last_sync'] ? esc_html($summary['last_sync']) : 'Never'; ?></p>
        </div>
        <div class="ai-seo-stat-card">
            <h2>Published content</h2>
            <p style="font-size:28px;margin:0;"><?php echo esc_html((string) $audit_summary['published_items']); ?></p>
        </div>
        <div class="ai-seo-stat-card">
            <h2>Approved suggestions</h2>
            <p style="font-size:28px;margin:0;"><?php echo esc_html((string) $audit_summary['approved_items']); ?></p>
        </div>
        <div class="ai-seo-stat-card">
            <h2>Frontend-ready pages</h2>
            <p style="font-size:28px;margin:0;"><?php echo esc_html((string) $audit_summary['frontend_ready_items']); ?></p>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(280px,1fr));gap:16px;max-width:1120px;margin-top:24px;">
        <div class="ai-seo-box ai-seo-box-wide" style="margin-top:0;">
            <h2>Coverage gaps</h2>
            <p style="margin:0 0 8px;">Missing AI title drafts: <strong><?php echo esc_html((string) $audit_summary['missing_title_drafts']); ?></strong></p>
            <p style="margin:0 0 8px;">Missing AI description drafts: <strong><?php echo esc_html((string) $audit_summary['missing_description_drafts']); ?></strong></p>
            <p style="margin:0 0 8px;">Frontend opt-in pages: <strong><?php echo esc_html((string) $audit_summary['frontend_enabled_items']); ?></strong></p>
            <p style="margin:0;">Yoast conflict protection: <strong><?php echo $frontend_conflict ? 'Detected' : 'Not detected'; ?></strong></p>
        </div>
        <div class="ai-seo-box ai-seo-box-wide" style="margin-top:0;">
            <h2>AI discovery surfaces</h2>
            <p style="margin:0 0 8px;"><a href="<?php echo esc_url($llms_url); ?>" target="_blank" rel="noopener noreferrer">llms.txt</a></p>
            <p style="margin:0 0 8px;"><a href="<?php echo esc_url($llms_full_url); ?>" target="_blank" rel="noopener noreferrer">llms-full.txt</a></p>
            <p style="margin:0 0 8px;"><a href="<?php echo esc_url($sitemap_url); ?>" target="_blank" rel="noopener noreferrer">Sitemap</a></p>
            <p style="margin:0;">Frontend output: <strong><?php echo $frontend_enabled ? 'Enabled' : 'Disabled'; ?></strong><?php if ($frontend_conflict) : ?>, conflict override <strong><?php echo $frontend_override_conflicts ? 'enabled' : 'off'; ?></strong><?php endif; ?></p>
        </div>
    </div>

    <div class="ai-seo-box">
        <h2>Site context sync</h2>
        <p>Build the internal site index used for AI prompts, overlap checks, discovery prioritization, and whole-site audits.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('ai_seo_keeper_sync_index'); ?>
            <input type="hidden" name="action" value="ai_seo_keeper_sync_index" />
            <?php submit_button('Sync site index', 'primary', 'submit', false); ?>
        </form>
    </div>

    <div class="ai-seo-box ai-seo-box-wide">
        <h2>Audit snapshot</h2>
        <?php if (empty($audit_rows)) : ?>
            <p style="margin:0;">No indexed content is available yet. Run a site sync first.</p>
        <?php else : ?>
            <table class="widefat striped ai-seo-sortable" id="ai-seo-audit-table" style="margin-top:12px;">
                <thead>
                    <tr>
                        <th class="ai-seo-sort" data-col="0">Content <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                        <th class="ai-seo-sort" data-col="1">Type / Status <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                        <th class="ai-seo-sort" data-col="2">Drafts <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                        <th class="ai-seo-sort" data-col="3">Approval <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                        <th class="ai-seo-sort" data-col="4">Frontend <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($audit_rows as $row) : ?>
                        <tr>
                            <td data-sort-value="<?php echo esc_attr(strtolower($row['title'])); ?>">
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $row['object_id'] . '&action=edit')); ?>"><?php echo esc_html($row['title']); ?></a>
                                <div style="margin-top:4px;"><a href="<?php echo esc_url($row['permalink']); ?>" target="_blank" rel="noopener noreferrer">View page</a></div>
                            </td>
                            <td data-sort-value="<?php echo esc_attr($row['post_type'] . ' ' . $row['status']); ?>"><?php echo esc_html($row['post_type']); ?> / <?php echo esc_html($row['status']); ?></td>
                            <td data-sort-value="<?php echo $row['has_title_draft'] ? 'yes' : 'no'; ?>">
                                Title: <?php echo $row['has_title_draft'] ? 'Yes' : 'No'; ?><br />
                                <p class="description">Global instructions applied to page generation, page chat, and site audit requests.</p>
                            </td>
                            <td data-sort-value="<?php echo $row['has_approved_suggestion'] ? 'approved' : 'pending'; ?>"><?php echo $row['has_approved_suggestion'] ? 'Approved' : 'Pending'; ?></td>
                            <td data-sort-value="<?php echo ($row['frontend_enabled'] ? '1' : '0') . ($row['frontend_ready'] ? '1' : '0'); ?>">
                                Page gate: <?php echo $row['frontend_enabled'] ? 'On' : 'Off'; ?><br />
                                Ready: <?php echo $row['frontend_ready'] ? 'Yes' : 'No'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="ai-seo-box ai-seo-box-wide">
        <h2>Current rollout state</h2>
        <ul style="list-style:disc;padding-left:20px;">
            <li>AI SEO Keeper metadata can render on the frontend only when global settings, conflict rules, and the page-level opt-in all allow it.</li>
            <li>AI discovery documents are live independently at <code>llms.txt</code> and <code>llms-full.txt</code> so they do not interfere with Yoast-managed head output.</li>
            <li>Structured data and IndexNow refresh signals now ride on the same approval and rollout controls, so the audit page can be used as the operational command center.</li>
        </ul>
    </div>
</div>