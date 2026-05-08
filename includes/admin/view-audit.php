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
    <h1>AI SEO Keeper Audit</h1>
    <p>Deterministic audit layer for content coverage, approval rollout, duplicate signals, and thin content. This page is the stable operational baseline before AI adds strategic prioritization.</p>

    <?php if ('' !== $audit_message) : ?>
        <div class="notice <?php echo 'success' === $audit_status ? 'notice-success' : 'notice-error'; ?> is-dismissible">
            <p><?php echo esc_html($audit_message); ?></p>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(4,minmax(180px,1fr));gap:16px;max-width:1120px;margin-top:24px;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;">
            <h2 style="margin-top:0;">Readiness</h2>
            <p style="font-size:28px;margin:0;"><?php echo esc_html((string) $readiness['score']); ?>/100</p>
            <p style="margin:8px 0 0;"><?php echo esc_html($readiness['label']); ?></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;">
            <h2 style="margin-top:0;">Draft Coverage</h2>
            <p style="font-size:28px;margin:0;"><?php echo esc_html((string) $readiness['draft_coverage']); ?>%</p>
            <p style="margin:8px 0 0;">Published items with AI title and description drafts.</p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;">
            <h2 style="margin-top:0;">Approval Coverage</h2>
            <p style="font-size:28px;margin:0;"><?php echo esc_html((string) $readiness['approval_coverage']); ?>%</p>
            <p style="margin:8px 0 0;"><?php echo esc_html((string) $summary['approved_items']); ?> approved suggestions across <?php echo esc_html((string) $summary['published_items']); ?> published items.</p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;">
            <h2 style="margin-top:0;">Frontend Coverage</h2>
            <p style="font-size:28px;margin:0;"><?php echo esc_html((string) $readiness['frontend_coverage']); ?>%</p>
            <p style="margin:8px 0 0;"><?php echo esc_html((string) $summary['frontend_ready_items']); ?> pages are ready to render AI SEO Keeper metadata.</p>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(280px,1fr));gap:16px;max-width:1120px;margin-top:24px;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;">Coverage Gaps</h2>
            <p style="margin:0 0 8px;">Missing AI title drafts: <strong><?php echo esc_html((string) $summary['missing_title_drafts']); ?></strong></p>
            <p style="margin:0 0 8px;">Missing AI description drafts: <strong><?php echo esc_html((string) $summary['missing_description_drafts']); ?></strong></p>
            <p style="margin:0 0 8px;">Frontend opt-in pages: <strong><?php echo esc_html((string) $summary['frontend_enabled_items']); ?></strong></p>
            <p style="margin:0 0 8px;">Frontend-ready pages: <strong><?php echo esc_html((string) $summary['frontend_ready_items']); ?></strong></p>
            <?php
            global $wpdb;
            $cornerstone_count = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_ai_seo_keeper_cornerstone' AND meta_value = '1'"
            );
            ?>
            <p style="margin:0;">Cornerstone pages: <strong><?php echo $cornerstone_count; ?></strong></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;">What This Score Means</h2>
            <p style="margin:0 0 8px;">The readiness score is operational, not predictive. It measures rollout maturity using draft coverage, approval coverage, and frontend readiness.</p>
            <p style="margin:0;">Use it to prioritize execution order, not to claim rankings.</p>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(320px,1fr));gap:16px;max-width:1120px;margin-top:24px;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;">IndexNow Status</h2>
            <p style="margin:0 0 8px;">Enabled: <strong><?php echo $indexnow_enabled ? 'Yes' : 'No'; ?></strong></p>
            <p style="margin:0 0 8px;">Auto-submit: <strong><?php echo $indexnow_auto_submit ? 'Yes' : 'No'; ?></strong></p>
            <?php if ('' !== $indexnow_key_url) : ?>
                <p style="margin:0 0 8px;">Key URL: <a href="<?php echo esc_url($indexnow_key_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($indexnow_key_url); ?></a></p>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px;">
                <?php wp_nonce_field('ai_seo_keeper_submit_indexnow'); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr($submit_indexnow_action); ?>" />
                <button type="submit" class="button button-secondary">Submit Priority Queue to IndexNow</button>
            </form>
            <p style="margin:12px 0 0;color:#50575e;">On localhost this will log a safe skip instead of calling the live IndexNow endpoint.</p>
        </div>

        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;">Recent IndexNow Activity</h2>
            <?php if (empty($indexnow_log)) : ?>
                <p style="margin:0;">No IndexNow submissions or skips have been logged yet.</p>
            <?php else : ?>
                <?php foreach ($indexnow_log as $entry) : ?>
                    <div style="padding:12px;border:1px solid #dcdcde;background:#f6f7f7;margin-bottom:12px;">
                        <p style="margin:0 0 8px;"><strong><?php echo esc_html(strtoupper((string) $entry['status'])); ?></strong> | <?php echo esc_html((string) $entry['reason']); ?></p>
                        <p style="margin:0 0 8px;"><?php echo esc_html((string) $entry['message']); ?></p>
                        <p style="margin:0;color:#50575e;">URLs: <?php echo esc_html((string) $entry['url_count']); ?><?php if (! empty($entry['created_at'])) : ?> | <?php echo esc_html((string) $entry['created_at']); ?><?php endif; ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(320px,1fr));gap:16px;max-width:1120px;margin-top:24px;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;">AI Strategic Audit</h2>
            <p>Use the deterministic report as the source of truth, then ask the configured model to turn it into a short execution plan.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:16px;">
                <?php wp_nonce_field('ai_seo_keeper_generate_site_audit'); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr($generate_site_audit_action); ?>" />
                <button type="submit" class="button button-primary" <?php disabled(! $has_api_key); ?>>Generate AI Strategic Audit</button>
            </form>
            <?php if (! $has_api_key) : ?>
                <p style="margin:12px 0 0;color:#8a2424;">Add an API key in Settings before generating AI strategic audits.</p>
            <?php else : ?>
                <p style="margin:12px 0 0;color:#50575e;">This keeps the human review step: AI proposes the site-level plan, but deterministic checks continue to drive the facts underneath it.</p>
            <?php endif; ?>
        </div>

        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;">Recent AI Site Audits</h2>
            <?php if (empty($site_audits)) : ?>
                <p style="margin:0;">No AI site audits have been generated yet.</p>
            <?php else : ?>
                <?php foreach ($site_audits as $audit) : ?>
                    <div style="padding:12px;border:1px solid #dcdcde;background:#f6f7f7;margin-bottom:12px;">
                        <p style="margin:0 0 8px;"><strong><?php echo esc_html($audit['audit_title']); ?></strong></p>
                        <p style="margin:0 0 8px;"><?php echo esc_html($audit['executive_summary']); ?></p>
                        <?php if (! empty($audit['priority_actions'])) : ?>
                            <p style="margin:0 0 8px;"><strong>Priority actions</strong></p>
                            <ul style="margin:0 0 8px;padding-left:20px;">
                                <?php foreach ($audit['priority_actions'] as $item) : ?>
                                    <li><?php echo esc_html((string) $item); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (! empty($audit['quick_wins'])) : ?>
                            <p style="margin:0 0 8px;"><strong>Quick wins</strong></p>
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
        <h2>Priority Queue</h2>
        <p>These rows are ordered toward published content with missing AI drafts first, then by approval state and freshness.</p>
        <table class="widefat striped" style="margin-top:12px;">
            <thead>
                <tr>
                    <th>Content</th>
                    <th>Drafts</th>
                    <th>Approval</th>
                    <th>Frontend</th>
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
                            Title: <?php echo $row['has_title_draft'] ? 'Yes' : 'No'; ?><br />
                            Description: <?php echo $row['has_description_draft'] ? 'Yes' : 'No'; ?>
                        </td>
                        <td><?php echo $row['has_approved_suggestion'] ? 'Approved' : 'Pending'; ?></td>
                        <td>
                            Gate: <?php echo $row['frontend_enabled'] ? 'On' : 'Off'; ?><br />
                            Ready: <?php echo $row['frontend_ready'] ? 'Yes' : 'No'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="background:#fff;border:1px solid #dcdcde;padding:20px;max-width:1120px;margin-top:24px;">
        <h2>Approved Rollout Queue</h2>
        <p>These pages already have an approved AI suggestion. Use this queue to enable or disable the page-level frontend gate in batches without weakening approval rules.</p>
        <?php if (empty($report['rollout_candidates'])) : ?>
            <p style="margin:0;">No approved pages are waiting for rollout right now.</p>
        <?php else : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ai_seo_keeper_bulk_frontend_rollout'); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr($bulk_frontend_action); ?>" />
                <table class="widefat striped" style="margin-top:12px;">
                    <thead>
                        <tr>
                            <th style="width:36px;"></th>
                            <th>Content</th>
                            <th>Status</th>
                            <th>Frontend Gate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report['rollout_candidates'] as $row) : ?>
                            <tr>
                                <td><input type="checkbox" name="post_ids[]" value="<?php echo (int) $row['object_id']; ?>" /></td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $row['object_id'] . '&action=edit')); ?>"><?php echo esc_html($row['title']); ?></a>
                                    <div style="margin-top:4px;"><a href="<?php echo esc_url($row['permalink']); ?>" target="_blank" rel="noopener noreferrer">View page</a></div>
                                </td>
                                <td><?php echo esc_html($row['post_type']); ?> / <?php echo esc_html($row['status']); ?><br />Approved</td>
                                <td><?php echo $row['frontend_enabled'] ? 'On' : 'Off'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <button type="submit" class="button button-primary" name="bulk_mode" value="enable_frontend">Enable Frontend For Approved Selections</button>
                    <button type="submit" class="button button-secondary" name="bulk_mode" value="disable_frontend">Disable Frontend For Selections</button>
                    <span style="color:#50575e;">When IndexNow is enabled, bulk enable actions also send refresh signals for the changed pages.</span>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(320px,1fr));gap:16px;max-width:1120px;margin-top:24px;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;">Unapproved Draft Candidates</h2>
            <?php if (empty($report['draft_candidates'])) : ?>
                <p style="margin:0;">No pages currently have unapproved drafts waiting in the queue.</p>
            <?php else : ?>
                <ul style="margin:0;padding-left:20px;">
                    <?php foreach ($report['draft_candidates'] as $candidate) : ?>
                        <li style="margin-bottom:10px;">
                            <a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $candidate['object_id'] . '&action=edit')); ?>"><?php echo esc_html($candidate['title']); ?></a>
                            :
                            <?php echo $candidate['has_title_draft'] ? 'title draft' : 'no title draft'; ?>,
                            <?php echo $candidate['has_description_draft'] ? 'description draft' : 'no description draft'; ?>,
                            frontend gate <?php echo $candidate['frontend_enabled'] ? 'on' : 'off'; ?>.
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;">Thin Content Risks</h2>
            <?php if (empty($report['thin_content_rows'])) : ?>
                <p style="margin:0;">No thin-content risks were flagged by the current word-count threshold.</p>
            <?php else : ?>
                <table class="widefat striped" style="margin-top:12px;">
                    <thead>
                        <tr>
                            <th>Content</th>
                            <th>Words</th>
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
            <h2 style="margin-top:0;">Duplicate Native Titles</h2>
            <?php if (empty($report['duplicate_post_titles'])) : ?>
                <p style="margin:0;">No exact duplicate published page titles were detected in the indexed content.</p>
            <?php else : ?>
                <ul style="margin:0;padding-left:20px;">
                    <?php foreach ($report['duplicate_post_titles'] as $group) : ?>
                        <li style="margin-bottom:10px;">
                            <strong><?php echo esc_html($group['label']); ?></strong> appears <?php echo esc_html((string) $group['total_count']); ?> times:
                            <?php echo $admin->render_audit_post_links($group['ids']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;">Duplicate AI Draft Titles</h2>
            <?php if (empty($report['duplicate_ai_titles'])) : ?>
                <p style="margin:0;">No exact duplicate AI title drafts were detected.</p>
            <?php else : ?>
                <ul style="margin:0;padding-left:20px;">
                    <?php foreach ($report['duplicate_ai_titles'] as $group) : ?>
                        <li style="margin-bottom:10px;">
                            <strong><?php echo esc_html($group['label']); ?></strong> appears <?php echo esc_html((string) $group['total_count']); ?> times:
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
        <h2 style="margin-top:0;">Orphaned Content <span style="font-weight:normal;color:#50575e;font-size:14px;">(<?php echo (int) $orphaned['total_orphans']; ?> of <?php echo (int) $orphaned['total_pages']; ?> pages)</span></h2>
        <p style="margin:0 0 12px;color:#50575e;">Pages with zero inbound internal links. These are invisible to crawlers that follow links and may never get indexed.</p>
        <?php if (empty($orphaned['orphans'])) : ?>
            <p style="margin:0;color:#00a32a;"><strong>No orphaned content detected.</strong> Every indexed page has at least one internal link pointing to it.</p>
        <?php else : ?>
            <table class="widefat striped" style="margin-top:8px;">
                <thead>
                    <tr>
                        <th>Page</th>
                        <th>Type</th>
                        <th>Inbound links</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orphaned['orphans'] as $row) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $row['object_id'] . '&action=edit')); ?>"><?php echo esc_html($row['title']); ?></a>
                                <div style="margin-top:2px;"><a href="<?php echo esc_url($row['permalink']); ?>" target="_blank" rel="noopener" style="color:#50575e;font-size:12px;">View</a></div>
                            </td>
                            <td><?php echo esc_html($row['post_type']); ?></td>
                            <td style="color:#d63638;"><strong>0</strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($orphaned['total_orphans'] > count($orphaned['orphans'])) : ?>
                <p style="margin:8px 0 0;color:#50575e;">Showing <?php echo count($orphaned['orphans']); ?> of <?php echo (int) $orphaned['total_orphans']; ?> orphaned pages.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>