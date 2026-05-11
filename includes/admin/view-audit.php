<?php

/**
 * Audit page view.
 *
 * Variables available (set by Admin::render_audit_page):
 *   $report, $summary, $readiness, $options, $has_api_key,
 *   $site_audits, $audit_status, $audit_message,
 *   $indexnow_enabled, $indexnow_auto_submit, $indexnow_key_url,
 *   $indexnow_log, $admin (Admin instance for render_audit_post_links),
 *   $generate_site_audit_action, $submit_indexnow_action, $bulk_frontend_action
 *
 * @package AI_SEO_Keeper
 */

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1><?php esc_html_e('AI SEO Keeper Audit', 'ai-seo-keeper'); ?></h1>
    <p><?php esc_html_e('Deterministic audit layer for content coverage, approval rollout, duplicate signals, and thin content. This page is the stable operational baseline before AI adds strategic prioritization.', 'ai-seo-keeper'); ?></p>

    <?php if ('' !== $audit_message) : ?>
        <div class="notice <?php echo 'success' === $audit_status ? 'notice-success' : 'notice-error'; ?> is-dismissible">
            <p><?php echo esc_html($audit_message); ?></p>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(4,minmax(180px,1fr));gap:16px;max-width:1120px;margin-top:24px;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Readiness', 'ai-seo-keeper'); ?></h2>
            <p style="font-size:28px;margin:0;"><?php echo esc_html((string) $readiness['score']); ?>/100</p>
            <p style="margin:8px 0 0;"><?php echo esc_html($readiness['label']); ?></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Draft Coverage', 'ai-seo-keeper'); ?></h2>
            <p style="font-size:28px;margin:0;"><?php echo esc_html((string) $readiness['draft_coverage']); ?>%</p>
            <p style="margin:8px 0 0;"><?php esc_html_e('Published items with AI title and description drafts.', 'ai-seo-keeper'); ?></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Approval Coverage', 'ai-seo-keeper'); ?></h2>
            <p style="font-size:28px;margin:0;"><?php echo esc_html((string) $readiness['approval_coverage']); ?>%</p>
            <p style="margin:8px 0 0;"><?php echo esc_html(sprintf(__('%d approved suggestions across %d published items.', 'ai-seo-keeper'), $summary['approved_items'], $summary['published_items'])); ?></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Frontend Coverage', 'ai-seo-keeper'); ?></h2>
            <p style="font-size:28px;margin:0;"><?php echo esc_html((string) $readiness['frontend_coverage']); ?>%</p>
            <p style="margin:8px 0 0;"><?php echo esc_html(sprintf(__('%d pages are ready to render AI SEO Keeper metadata.', 'ai-seo-keeper'), $summary['frontend_ready_items'])); ?></p>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(280px,1fr));gap:16px;max-width:1120px;margin-top:24px;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Coverage Gaps', 'ai-seo-keeper'); ?></h2>
            <p style="margin:0 0 8px;"><?php esc_html_e('Missing AI title drafts:', 'ai-seo-keeper'); ?> <strong><?php echo esc_html((string) $summary['missing_title_drafts']); ?></strong></p>
            <p style="margin:0 0 8px;"><?php esc_html_e('Missing AI description drafts:', 'ai-seo-keeper'); ?> <strong><?php echo esc_html((string) $summary['missing_description_drafts']); ?></strong></p>
            <p style="margin:0 0 8px;"><?php esc_html_e('Frontend opt-in pages:', 'ai-seo-keeper'); ?> <strong><?php echo esc_html((string) $summary['frontend_enabled_items']); ?></strong></p>
            <p style="margin:0 0 8px;"><?php esc_html_e('Frontend-ready pages:', 'ai-seo-keeper'); ?> <strong><?php echo esc_html((string) $summary['frontend_ready_items']); ?></strong></p>
            <?php
            global $wpdb;
            $cornerstone_count = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_ai_seo_keeper_cornerstone' AND meta_value = '1'"
            );
            ?>
            <p style="margin:0;"><?php esc_html_e('Cornerstone pages:', 'ai-seo-keeper'); ?> <strong><?php echo $cornerstone_count; ?></strong></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('What This Score Means', 'ai-seo-keeper'); ?></h2>
            <p style="margin:0 0 8px;"><?php esc_html_e('The readiness score is operational, not predictive. It measures rollout maturity using draft coverage, approval coverage, and frontend readiness.', 'ai-seo-keeper'); ?></p>
            <p style="margin:0;"><?php esc_html_e('Use it to prioritize execution order, not to claim rankings.', 'ai-seo-keeper'); ?></p>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(320px,1fr));gap:16px;max-width:1120px;margin-top:24px;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('IndexNow Status', 'ai-seo-keeper'); ?></h2>
            <p style="margin:0 0 8px;"><?php esc_html_e('Enabled:', 'ai-seo-keeper'); ?> <strong><?php echo $indexnow_enabled ? esc_html__('Yes', 'ai-seo-keeper') : esc_html__('No', 'ai-seo-keeper'); ?></strong></p>
            <p style="margin:0 0 8px;"><?php esc_html_e('Auto-submit:', 'ai-seo-keeper'); ?> <strong><?php echo $indexnow_auto_submit ? esc_html__('Yes', 'ai-seo-keeper') : esc_html__('No', 'ai-seo-keeper'); ?></strong></p>
            <?php if ('' !== $indexnow_key_url) : ?>
                <p style="margin:0 0 8px;"><?php esc_html_e('Key URL:', 'ai-seo-keeper'); ?> <a href="<?php echo esc_url($indexnow_key_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($indexnow_key_url); ?></a></p>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px;">
                <?php wp_nonce_field('ai_seo_keeper_submit_indexnow'); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr($submit_indexnow_action); ?>" />
                <button type="submit" class="button button-secondary"><?php esc_html_e('Submit Priority Queue to IndexNow', 'ai-seo-keeper'); ?></button>
            </form>
            <p style="margin:12px 0 0;color:#50575e;"><?php esc_html_e('On localhost this will log a safe skip instead of calling the live IndexNow endpoint.', 'ai-seo-keeper'); ?></p>
        </div>

        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Recent IndexNow Activity', 'ai-seo-keeper'); ?></h2>
            <?php if (empty($indexnow_log)) : ?>
                <p style="margin:0;"><?php esc_html_e('No IndexNow submissions or skips have been logged yet.', 'ai-seo-keeper'); ?></p>
            <?php else : ?>
                <?php foreach ($indexnow_log as $entry) : ?>
                    <div style="padding:12px;border:1px solid #dcdcde;background:#f6f7f7;margin-bottom:12px;">
                        <p style="margin:0 0 8px;"><strong><?php echo esc_html(strtoupper((string) $entry['status'])); ?></strong> | <?php echo esc_html((string) $entry['reason']); ?></p>
                        <p style="margin:0 0 8px;"><?php echo esc_html((string) $entry['message']); ?></p>
                        <p style="margin:0;color:#50575e;"><?php esc_html_e('URLs:', 'ai-seo-keeper'); ?> <?php echo esc_html((string) $entry['url_count']); ?><?php if (! empty($entry['created_at'])) : ?> | <?php echo esc_html((string) $entry['created_at']); ?><?php endif; ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(320px,1fr));gap:16px;max-width:1120px;margin-top:24px;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('AI Strategic Audit', 'ai-seo-keeper'); ?></h2>
            <p><?php esc_html_e('Use the deterministic report as the source of truth, then ask the configured model to turn it into a short execution plan.', 'ai-seo-keeper'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:16px;">
                <?php wp_nonce_field('ai_seo_keeper_generate_site_audit'); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr($generate_site_audit_action); ?>" />
                <button type="submit" class="button button-primary" <?php disabled(! $has_api_key); ?>><?php esc_html_e('Generate AI Strategic Audit', 'ai-seo-keeper'); ?></button>
            </form>
            <?php if (! $has_api_key) : ?>
                <p style="margin:12px 0 0;color:#8a2424;"><?php esc_html_e('Add an API key in Settings before generating AI strategic audits.', 'ai-seo-keeper'); ?></p>
            <?php else : ?>
                <p style="margin:12px 0 0;color:#50575e;"><?php esc_html_e('This keeps the human review step: AI proposes the site-level plan, but deterministic checks continue to drive the facts underneath it.', 'ai-seo-keeper'); ?></p>
            <?php endif; ?>
        </div>

        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Recent AI Site Audits', 'ai-seo-keeper'); ?></h2>
            <?php if (empty($site_audits)) : ?>
                <p style="margin:0;"><?php esc_html_e('No AI site audits have been generated yet.', 'ai-seo-keeper'); ?></p>
            <?php else : ?>
                <?php foreach ($site_audits as $audit) : ?>
                    <div style="padding:12px;border:1px solid #dcdcde;background:#f6f7f7;margin-bottom:12px;">
                        <p style="margin:0 0 8px;"><strong><?php echo esc_html($audit['audit_title']); ?></strong></p>
                        <p style="margin:0 0 8px;"><?php echo esc_html($audit['executive_summary']); ?></p>
                        <?php if (! empty($audit['priority_actions'])) : ?>
                            <p style="margin:0 0 8px;"><strong><?php esc_html_e('Priority actions', 'ai-seo-keeper'); ?></strong></p>
                            <ul style="margin:0 0 8px;padding-left:20px;">
                                <?php foreach ($audit['priority_actions'] as $item) : ?>
                                    <li><?php echo esc_html((string) $item); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (! empty($audit['quick_wins'])) : ?>
                            <p style="margin:0 0 8px;"><strong><?php esc_html_e('Quick wins', 'ai-seo-keeper'); ?></strong></p>
                            <ul style="margin:0 0 8px;padding-left:20px;">
                                <?php foreach ($audit['quick_wins'] as $item) : ?>
                                    <li><?php echo esc_html((string) $item); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if ('' !== $audit['notes']) : ?>
                            <p style="margin:0 0 8px;"><em><?php echo esc_html($audit['notes']); ?></em></p>
                        <?php endif; ?>
                        <p style="margin:0;color:#50575e;">
                            <?php echo esc_html(strtoupper($audit['provider'])); ?>
                            <?php if ('' !== $audit['model']) : ?>
                                <?php echo ' | ' . esc_html($audit['model']); ?>
                            <?php endif; ?>
                            <?php if ('' !== $audit['created_at']) : ?>
                                <?php echo ' | ' . esc_html($audit['created_at']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div style="background:#fff;border:1px solid #dcdcde;padding:20px;max-width:1120px;margin-top:24px;">
        <h2><?php esc_html_e('Priority Queue', 'ai-seo-keeper'); ?></h2>
        <p><?php esc_html_e('These rows are ordered toward published content with missing AI drafts first, then by approval state and freshness.', 'ai-seo-keeper'); ?></p>
        <table class="widefat striped" style="margin-top:12px;">
            <thead>
                <tr>
                    <th><?php esc_html_e('Content', 'ai-seo-keeper'); ?></th>
                    <th><?php esc_html_e('Drafts', 'ai-seo-keeper'); ?></th>
                    <th><?php esc_html_e('Approval', 'ai-seo-keeper'); ?></th>
                    <th><?php esc_html_e('Frontend', 'ai-seo-keeper'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['priority_rows'] as $row) : ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $row['object_id'] . '&action=edit')); ?>"><?php echo esc_html($row['title']); ?></a>
                            <div style="margin-top:4px;color:#50575e;"><?php echo esc_html($row['post_type']); ?> / <?php echo esc_html($row['status']); ?></div>
                        </td>
                        <td>
                            <?php esc_html_e('Title:', 'ai-seo-keeper'); ?> <?php echo $row['has_title_draft'] ? esc_html__('Yes', 'ai-seo-keeper') : esc_html__('No', 'ai-seo-keeper'); ?><br />
                            <?php esc_html_e('Description:', 'ai-seo-keeper'); ?> <?php echo $row['has_description_draft'] ? esc_html__('Yes', 'ai-seo-keeper') : esc_html__('No', 'ai-seo-keeper'); ?>
                        </td>
                        <td><?php echo $row['has_approved_suggestion'] ? esc_html__('Approved', 'ai-seo-keeper') : esc_html__('Pending', 'ai-seo-keeper'); ?></td>
                        <td>
                            <?php esc_html_e('Gate:', 'ai-seo-keeper'); ?> <?php echo $row['frontend_enabled'] ? esc_html__('On', 'ai-seo-keeper') : esc_html__('Off', 'ai-seo-keeper'); ?><br />
                            <?php esc_html_e('Ready:', 'ai-seo-keeper'); ?> <?php echo $row['frontend_ready'] ? esc_html__('Yes', 'ai-seo-keeper') : esc_html__('No', 'ai-seo-keeper'); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="background:#fff;border:1px solid #dcdcde;padding:20px;max-width:1120px;margin-top:24px;">
        <h2><?php esc_html_e('Approved Rollout Queue', 'ai-seo-keeper'); ?></h2>
        <p><?php esc_html_e('These pages already have an approved AI suggestion. Use this queue to enable or disable the page-level frontend gate in batches without weakening approval rules.', 'ai-seo-keeper'); ?></p>
        <?php if (empty($report['rollout_candidates'])) : ?>
            <p style="margin:0;"><?php esc_html_e('No approved pages are waiting for rollout right now.', 'ai-seo-keeper'); ?></p>
        <?php else : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ai_seo_keeper_bulk_frontend_rollout'); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr($bulk_frontend_action); ?>" />
                <table class="widefat striped" style="margin-top:12px;">
                    <thead>
                        <tr>
                            <th style="width:36px;"></th>
                            <th><?php esc_html_e('Content', 'ai-seo-keeper'); ?></th>
                            <th><?php esc_html_e('Status', 'ai-seo-keeper'); ?></th>
                            <th><?php esc_html_e('Frontend Gate', 'ai-seo-keeper'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report['rollout_candidates'] as $row) : ?>
                            <tr>
                                <td><input type="checkbox" name="post_ids[]" value="<?php echo (int) $row['object_id']; ?>" /></td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $row['object_id'] . '&action=edit')); ?>"><?php echo esc_html($row['title']); ?></a>
                                    <div style="margin-top:4px;"><a href="<?php echo esc_url($row['permalink']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View page', 'ai-seo-keeper'); ?></a></div>
                                </td>
                                <td><?php echo esc_html($row['post_type']); ?> / <?php echo esc_html($row['status']); ?><br /><?php esc_html_e('Approved', 'ai-seo-keeper'); ?></td>
                                <td><?php echo $row['frontend_enabled'] ? esc_html__('On', 'ai-seo-keeper') : esc_html__('Off', 'ai-seo-keeper'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <button type="submit" class="button button-primary" name="bulk_mode" value="enable_frontend"><?php esc_html_e('Enable Frontend For Approved Selections', 'ai-seo-keeper'); ?></button>
                    <button type="submit" class="button button-secondary" name="bulk_mode" value="disable_frontend"><?php esc_html_e('Disable Frontend For Selections', 'ai-seo-keeper'); ?></button>
                    <span style="color:#50575e;"><?php esc_html_e('When IndexNow is enabled, bulk enable actions also send refresh signals for the changed pages.', 'ai-seo-keeper'); ?></span>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(320px,1fr));gap:16px;max-width:1120px;margin-top:24px;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Unapproved Draft Candidates', 'ai-seo-keeper'); ?></h2>
            <?php if (empty($report['draft_candidates'])) : ?>
                <p style="margin:0;"><?php esc_html_e('No pages currently have unapproved drafts waiting in the queue.', 'ai-seo-keeper'); ?></p>
            <?php else : ?>
                <ul style="margin:0;padding-left:20px;">
                    <?php foreach ($report['draft_candidates'] as $candidate) : ?>
                        <li style="margin-bottom:10px;">
                            <a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $candidate['object_id'] . '&action=edit')); ?>"><?php echo esc_html($candidate['title']); ?></a>:
                            <?php echo $candidate['has_title_draft'] ? esc_html__('title draft', 'ai-seo-keeper') : esc_html__('no title draft', 'ai-seo-keeper'); ?>,
                            <?php echo $candidate['has_description_draft'] ? esc_html__('description draft', 'ai-seo-keeper') : esc_html__('no description draft', 'ai-seo-keeper'); ?>,
                            <?php esc_html_e('frontend gate', 'ai-seo-keeper'); ?> <?php echo $candidate['frontend_enabled'] ? esc_html__('on', 'ai-seo-keeper') : esc_html__('off', 'ai-seo-keeper'); ?>.
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Thin Content Risks', 'ai-seo-keeper'); ?></h2>
            <?php if (empty($report['thin_content_rows'])) : ?>
                <p style="margin:0;"><?php esc_html_e('No thin-content risks were flagged by the current word-count threshold.', 'ai-seo-keeper'); ?></p>
            <?php else : ?>
                <table class="widefat striped" style="margin-top:12px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Content', 'ai-seo-keeper'); ?></th>
                            <th><?php esc_html_e('Words', 'ai-seo-keeper'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report['thin_content_rows'] as $row) : ?>
                            <tr>
                                <td><a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $row['object_id'] . '&action=edit')); ?>"><?php echo esc_html($row['title']); ?></a></td>
                                <td><?php echo esc_html((string) $row['word_count']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(320px,1fr));gap:16px;max-width:1120px;margin-top:24px;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Duplicate Native Titles', 'ai-seo-keeper'); ?></h2>
            <?php if (empty($report['duplicate_post_titles'])) : ?>
                <p style="margin:0;"><?php esc_html_e('No exact duplicate published page titles were detected in the indexed content.', 'ai-seo-keeper'); ?></p>
            <?php else : ?>
                <ul style="margin:0;padding-left:20px;">
                    <?php foreach ($report['duplicate_post_titles'] as $group) : ?>
                        <li style="margin-bottom:10px;">
                            <strong><?php echo esc_html($group['label']); ?></strong> <?php echo esc_html(sprintf(__('appears %d times:', 'ai-seo-keeper'), $group['total_count'])); ?>
                            <?php echo $admin->render_audit_post_links($group['ids']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Duplicate AI Draft Titles', 'ai-seo-keeper'); ?></h2>
            <?php if (empty($report['duplicate_ai_titles'])) : ?>
                <p style="margin:0;"><?php esc_html_e('No exact duplicate AI title drafts were detected.', 'ai-seo-keeper'); ?></p>
            <?php else : ?>
                <ul style="margin:0;padding-left:20px;">
                    <?php foreach ($report['duplicate_ai_titles'] as $group) : ?>
                        <li style="margin-bottom:10px;">
                            <strong><?php echo esc_html($group['label']); ?></strong> <?php echo esc_html(sprintf(__('appears %d times:', 'ai-seo-keeper'), $group['total_count'])); ?>
                            <?php echo $admin->render_audit_post_links($group['ids']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <?php
    $orphaned = $report['orphaned_content'] ?? array('orphans' => array(), 'total_orphans' => 0, 'total_pages' => 0);
    ?>
    <div style="background:#fff;border:1px solid #dcdcde;padding:20px;max-width:1120px;margin-top:24px;">
        <h2 style="margin-top:0;"><?php esc_html_e('Orphaned Content', 'ai-seo-keeper'); ?> <span style="font-weight:normal;color:#50575e;font-size:14px;">(<?php echo esc_html(sprintf(__('%d of %d pages', 'ai-seo-keeper'), $orphaned['total_orphans'], $orphaned['total_pages'])); ?>)</span></h2>
        <p style="margin:0 0 12px;color:#50575e;"><?php esc_html_e('Pages with zero inbound internal links. These are invisible to crawlers that follow links and may never get indexed.', 'ai-seo-keeper'); ?></p>
        <?php if (empty($orphaned['orphans'])) : ?>
            <p style="margin:0;color:#00a32a;"><strong><?php esc_html_e('No orphaned content detected.', 'ai-seo-keeper'); ?></strong> <?php esc_html_e('Every indexed page has at least one internal link pointing to it.', 'ai-seo-keeper'); ?></p>
        <?php else : ?>
            <table class="widefat striped" style="margin-top:8px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Page', 'ai-seo-keeper'); ?></th>
                        <th><?php esc_html_e('Type', 'ai-seo-keeper'); ?></th>
                        <th><?php esc_html_e('Inbound links', 'ai-seo-keeper'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orphaned['orphans'] as $row) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $row['object_id'] . '&action=edit')); ?>"><?php echo esc_html($row['title']); ?></a>
                                <div style="margin-top:2px;"><a href="<?php echo esc_url($row['permalink']); ?>" target="_blank" rel="noopener" style="color:#50575e;font-size:12px;"><?php esc_html_e('View', 'ai-seo-keeper'); ?></a></div>
                            </td>
                            <td><?php echo esc_html($row['post_type']); ?></td>
                            <td style="color:#d63638;"><strong>0</strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($orphaned['total_orphans'] > count($orphaned['orphans'])) : ?>
                <p style="margin:8px 0 0;color:#50575e;"><?php echo esc_html(sprintf(__('Showing %d of %d orphaned pages.', 'ai-seo-keeper'), count($orphaned['orphans']), $orphaned['total_orphans'])); ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>