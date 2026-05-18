<?php

/**
 * Dashboard page view.
 *
 * Variables available (set by Admin::render_dashboard):
 *   $summary, $audit_summary, $audit_rows, $options,
 *   $sync_count, $frontend_conflict, $frontend_enabled,
 *   $frontend_override_conflicts, $llms_url, $llms_full_url, $sitemap_url
 *
 * @package AI_SEO_Captain
 */

defined('ABSPATH') || exit;

/** @var array  $summary */
/** @var array  $audit_summary */
/** @var array  $audit_rows */
/** @var array  $options */
/** @var int    $sync_count */
/** @var bool   $frontend_conflict */
/** @var bool   $frontend_enabled */
/** @var bool   $frontend_override_conflicts */
/** @var string $llms_url */
/** @var string $llms_full_url */
/** @var string $sitemap_url */
/** @var string $readiness_banner */
?>
<div class="wrap">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px;">
        <img src="<?php echo esc_url(AI_SEO_CAPTAIN_URL . 'assets/img/ai-seo-captain-d.svg'); ?>" alt="SEO Captain" style="width:40px;height:40px;" />
        <h1 style="margin:0;"><?php esc_html_e('SEO Captain', 'ai-seo-captain'); ?></h1>
    </div>
    <p><?php esc_html_e('SEO Captain now covers AI-assisted metadata workflows, saved page-level SEO overrides, audit workflows, discovery documents, richer schema, and refresh signaling without silently fighting the existing SEO stack.', 'ai-seo-captain'); ?></p>

    <?php echo $readiness_banner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
    ?>

    <?php if ($sync_count > 0) : ?>
        <?php
        echo \AI_SEO_Captain\Admin::render_banner(
            'is-success',
            esc_html__('Index synced', 'ai-seo-captain'),
            esc_html(sprintf(__('Site index synced. %d content records stored.', 'ai-seo-captain'), $sync_count)),
            true
        );
        ?>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(3,minmax(220px,1fr));gap:16px;max-width:1120px;margin-top:24px;">
        <div class="ai-seo-stat-card">
            <h2><?php esc_html_e('Indexed content', 'ai-seo-captain'); ?></h2>
            <p style="font-size:28px;margin:0;"><?php echo esc_html((string) $summary['total_items']); ?></p>
        </div>
        <div class="ai-seo-stat-card">
            <h2><?php esc_html_e('Provider', 'ai-seo-captain'); ?></h2>
            <p style="font-size:20px;margin:0;"><?php echo esc_html(strtoupper($options['provider'])); ?></p>
        </div>
        <div class="ai-seo-stat-card">
            <h2><?php esc_html_e('Last sync', 'ai-seo-captain'); ?></h2>
            <p style="font-size:16px;margin:0;"><?php echo $summary['last_sync'] ? esc_html($summary['last_sync']) : esc_html__('Never', 'ai-seo-captain'); ?></p>
        </div>
        <div class="ai-seo-stat-card">
            <h2><?php esc_html_e('Published content', 'ai-seo-captain'); ?></h2>
            <p style="font-size:28px;margin:0;"><?php echo esc_html((string) $audit_summary['published_items']); ?></p>
        </div>
        <div class="ai-seo-stat-card">
            <h2><?php esc_html_e('Approved suggestions', 'ai-seo-captain'); ?></h2>
            <p style="font-size:28px;margin:0;"><?php echo esc_html((string) $audit_summary['approved_items']); ?></p>
        </div>
        <div class="ai-seo-stat-card">
            <h2><?php esc_html_e('Frontend-ready pages', 'ai-seo-captain'); ?></h2>
            <p style="font-size:28px;margin:0;"><?php echo esc_html((string) $audit_summary['frontend_ready_items']); ?></p>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(280px,1fr));gap:16px;max-width:1120px;margin-top:24px;">
        <div class="ai-seo-box ai-seo-box-wide" style="margin-top:0;">
            <h2><?php esc_html_e('Coverage gaps', 'ai-seo-captain'); ?></h2>
            <p style="margin:0 0 8px;"><?php esc_html_e('Missing AI title drafts:', 'ai-seo-captain'); ?> <strong><?php echo esc_html((string) $audit_summary['missing_title_drafts']); ?></strong></p>
            <p style="margin:0 0 8px;"><?php esc_html_e('Missing AI description drafts:', 'ai-seo-captain'); ?> <strong><?php echo esc_html((string) $audit_summary['missing_description_drafts']); ?></strong></p>
            <p style="margin:0 0 8px;"><?php esc_html_e('Frontend opt-in pages:', 'ai-seo-captain'); ?> <strong><?php echo esc_html((string) $audit_summary['frontend_enabled_items']); ?></strong></p>
            <p style="margin:0;"><?php esc_html_e('Yoast conflict protection:', 'ai-seo-captain'); ?> <strong><?php echo $frontend_conflict ? esc_html__('Detected', 'ai-seo-captain') : esc_html__('Not detected', 'ai-seo-captain'); ?></strong></p>
        </div>
        <div class="ai-seo-box ai-seo-box-wide" style="margin-top:0;">
            <h2><?php esc_html_e('AI discovery surfaces', 'ai-seo-captain'); ?></h2>
            <p style="margin:0 0 8px;"><a href="<?php echo esc_url($llms_url); ?>" target="_blank" rel="noopener noreferrer">llms.txt</a></p>
            <p style="margin:0 0 8px;"><a href="<?php echo esc_url($llms_full_url); ?>" target="_blank" rel="noopener noreferrer">llms-full.txt</a></p>
            <p style="margin:0 0 8px;"><a href="<?php echo esc_url($sitemap_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Sitemap', 'ai-seo-captain'); ?></a></p>
            <p style="margin:0;"><?php esc_html_e('Frontend output:', 'ai-seo-captain'); ?> <strong><?php echo $frontend_enabled ? esc_html__('Enabled', 'ai-seo-captain') : esc_html__('Disabled', 'ai-seo-captain'); ?></strong><?php if ($frontend_conflict) : ?>, <?php esc_html_e('conflict override', 'ai-seo-captain'); ?> <strong><?php echo $frontend_override_conflicts ? esc_html__('enabled', 'ai-seo-captain') : esc_html__('off', 'ai-seo-captain'); ?></strong><?php endif; ?></p>
        </div>
    </div>

    <div class="ai-seo-box">
        <h2><?php esc_html_e('Site context sync', 'ai-seo-captain'); ?></h2>
        <p><?php esc_html_e('Build the internal site index used for AI prompts, overlap checks, discovery prioritization, and whole-site audits.', 'ai-seo-captain'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('ai_seo_captain_sync_index'); ?>
            <input type="hidden" name="action" value="ai_seo_captain_sync_index" />
            <?php submit_button(__('Sync site index', 'ai-seo-captain'), 'primary', 'submit', false); ?>
        </form>
    </div>

    <div class="ai-seo-box ai-seo-box-wide">
        <h2><?php esc_html_e('Audit snapshot', 'ai-seo-captain'); ?></h2>
        <?php if (empty($audit_rows)) : ?>
            <p style="margin:0;"><?php esc_html_e('No indexed content is available yet. Run a site sync first.', 'ai-seo-captain'); ?></p>
        <?php else : ?>
            <table class="widefat striped ai-seo-sortable" id="ai-seo-audit-table" style="margin-top:12px;">
                <thead>
                    <tr>
                        <th class="ai-seo-sort" data-col="0"><?php esc_html_e('Content', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                        <th class="ai-seo-sort" data-col="1"><?php esc_html_e('Type / Status', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                        <th class="ai-seo-sort" data-col="2"><?php esc_html_e('Drafts', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                        <th class="ai-seo-sort" data-col="3"><?php esc_html_e('Approval', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                        <th class="ai-seo-sort" data-col="4"><?php esc_html_e('Frontend', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($audit_rows as $row) : ?>
                        <tr>
                            <td data-sort-value="<?php echo esc_attr(strtolower($row['title'])); ?>">
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $row['object_id'] . '&action=edit')); ?>"><?php echo esc_html($row['title']); ?></a>
                                <div style="margin-top:4px;"><a href="<?php echo esc_url($row['permalink']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View page', 'ai-seo-captain'); ?></a></div>
                            </td>
                            <td data-sort-value="<?php echo esc_attr($row['post_type'] . ' ' . $row['status']); ?>"><?php echo esc_html($row['post_type']); ?> / <?php echo esc_html($row['status']); ?></td>
                            <td data-sort-value="<?php echo $row['has_title_draft'] ? 'yes' : 'no'; ?>">
                                <?php esc_html_e('Title:', 'ai-seo-captain'); ?> <?php echo $row['has_title_draft'] ? esc_html__('Yes', 'ai-seo-captain') : esc_html__('No', 'ai-seo-captain'); ?><br />
                                <p class="description"><?php esc_html_e('Global instructions applied to page generation, page chat, and site audit requests.', 'ai-seo-captain'); ?></p>
                            </td>
                            <td data-sort-value="<?php echo $row['has_approved_suggestion'] ? 'approved' : 'pending'; ?>"><?php echo $row['has_approved_suggestion'] ? esc_html__('Approved', 'ai-seo-captain') : esc_html__('Pending', 'ai-seo-captain'); ?></td>
                            <td data-sort-value="<?php echo ($row['frontend_enabled'] ? '1' : '0') . ($row['frontend_ready'] ? '1' : '0'); ?>">
                                <?php esc_html_e('Page gate:', 'ai-seo-captain'); ?> <?php echo $row['frontend_enabled'] ? esc_html__('On', 'ai-seo-captain') : esc_html__('Off', 'ai-seo-captain'); ?><br />
                                <?php esc_html_e('Ready:', 'ai-seo-captain'); ?> <?php echo $row['frontend_ready'] ? esc_html__('Yes', 'ai-seo-captain') : esc_html__('No', 'ai-seo-captain'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="ai-seo-box ai-seo-box-wide">
        <h2><?php esc_html_e('Current rollout state', 'ai-seo-captain'); ?></h2>
        <ul style="list-style:disc;padding-left:20px;">
            <li><?php esc_html_e('SEO Captain metadata can render on the frontend only when global settings, conflict rules, and the page-level opt-in all allow it.', 'ai-seo-captain'); ?></li>
            <li><?php esc_html_e('AI discovery documents are live independently at llms.txt and llms-full.txt so they do not interfere with Yoast-managed head output.', 'ai-seo-captain'); ?></li>
            <li><?php esc_html_e('Structured data and IndexNow refresh signals now ride on the same approval and rollout controls, so the audit page can be used as the operational command center.', 'ai-seo-captain'); ?></li>
        </ul>
    </div>
</div>