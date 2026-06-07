<?php

/**
 * Audit page view.
 *
 * Variables available (set by Admin::render_audit_page):
 *   $report, $summary, $readiness, $options, $has_api_key,
 *   $site_audits, $audit_status, $audit_message,
 *   $indexnow_enabled, $indexnow_auto_submit, $indexnow_key_url,
 *   $indexnow_log, $admin (Admin instance for render_audit_post_links),
 *   $generate_site_audit_action, $submit_indexnow_action, $bulk_frontend_action,
 *   $gsc_summary, $gsc_top_queries, $gsc_top_pages
 *
 * @package AI_SEO_Captain
 */

defined('ABSPATH') || exit;

/** @var array  $report */
/** @var array  $summary */
/** @var array  $readiness */
/** @var array  $options */
/** @var bool   $has_api_key */
/** @var array  $site_audits */
/** @var string $audit_status */
/** @var string $audit_message */
/** @var bool   $indexnow_enabled */
/** @var bool   $indexnow_auto_submit */
/** @var string $indexnow_key_url */
/** @var array  $indexnow_log */
/** @var \AI_SEO_Captain\Admin $admin Instance exposing render_audit_post_links() */
/** @var string $readiness_banner */
/** @var string $generate_site_audit_action */
/** @var string $submit_indexnow_action */
/** @var string $bulk_frontend_action */
/** @var array|null $gsc_summary */
/** @var array  $gsc_top_queries */
/** @var array  $gsc_top_pages */
?>
<div class="wrap aisc-wrap">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px;">
        <img src="<?php echo esc_url(AI_SEO_CAPTAIN_URL . 'assets/img/ai-seo-captain-d.svg'); ?>" alt="SEO Captain" style="width:40px;height:40px;" />
        <h1 style="margin:0;"><?php esc_html_e('SEO Captain Audit', 'ai-seo-captain'); ?></h1>
    </div>
    <p><?php esc_html_e('Deterministic audit layer for content coverage, approval rollout, duplicate signals, and thin content. This page is the stable operational baseline before AI adds strategic prioritization.', 'ai-seo-captain'); ?></p>

    <?php echo $readiness_banner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
    ?>

    <?php if ('' !== $audit_message) : ?>
        <?php
        echo \AI_SEO_Captain\Admin::render_banner(
            'success' === $audit_status ? 'is-success' : 'is-error',
            'success' === $audit_status ? esc_html__('Success', 'ai-seo-captain') : esc_html__('Error', 'ai-seo-captain'),
            esc_html($audit_message),
            true
        );
        ?>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(4,minmax(180px,1fr));gap:16px;max-width:1120px;margin-top:24px;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Readiness', 'ai-seo-captain'); ?></h2>
            <p style="font-size:28px;margin:0;"><?php echo esc_html((string) $readiness['score']); ?>/100</p>
            <p style="margin:8px 0 0;"><?php echo esc_html($readiness['label']); ?></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Draft Coverage', 'ai-seo-captain'); ?><?php echo \AI_SEO_Captain\Admin::info(__('Percentage of published pages that have AI-generated title and description drafts. Higher = more pages ready for review.', 'ai-seo-captain')); ?></h2>
            <p style="font-size:28px;margin:0;"><?php echo esc_html((string) $readiness['draft_coverage']); ?>%</p>
            <p style="margin:8px 0 0;"><?php esc_html_e('Published items with AI title and description drafts.', 'ai-seo-captain'); ?></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Approval Coverage', 'ai-seo-captain'); ?><?php echo \AI_SEO_Captain\Admin::info(__('Percentage of pages where you have approved the AI suggestion. Approved pages are ready for frontend output.', 'ai-seo-captain')); ?></h2>
            <p style="font-size:28px;margin:0;"><?php echo esc_html((string) $readiness['approval_coverage']); ?>%</p>
            <p style="margin:8px 0 0;"><?php echo esc_html(sprintf(__('%d approved suggestions across %d published items.', 'ai-seo-captain'), $summary['approved_items'], $summary['published_items'])); ?></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Frontend Coverage', 'ai-seo-captain'); ?><?php echo \AI_SEO_Captain\Admin::info(__('Pages where SEO Captain metadata actually renders on your live site. Requires: approved suggestion + frontend gate enabled.', 'ai-seo-captain')); ?></h2>
            <p style="font-size:28px;margin:0;"><?php echo esc_html((string) $readiness['frontend_coverage']); ?>%</p>
            <p style="margin:8px 0 0;"><?php echo esc_html(sprintf(__('%d pages are ready to render SEO Captain metadata.', 'ai-seo-captain'), $summary['frontend_ready_items'])); ?></p>
        </div>
        <?php
        $gsc_has_data = $gsc_summary && $gsc_summary['impressions'] > 0;
        $gsc_grey     = ! $gsc_has_data ? 'opacity:.45;pointer-events:none;' : '';
        ?>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;<?php echo $gsc_grey; ?>">
            <h2 style="margin-top:0;">
                <span class="dashicons dashicons-chart-area" style="color:#4285f4;vertical-align:middle;"></span>
                <?php esc_html_e('Search Console (30d)', 'ai-seo-captain'); ?>
            </h2>
            <?php if ($gsc_has_data) : ?>
                <p style="font-size:28px;margin:0;color:#4285f4;"><?php echo esc_html(number_format_i18n($gsc_summary['clicks'])); ?> <small style="font-size:14px;color:#646970;"><?php esc_html_e('clicks', 'ai-seo-captain'); ?></small></p>
                <p style="margin:8px 0 0;">
                    <?php echo esc_html(number_format_i18n($gsc_summary['impressions'])); ?> <?php esc_html_e('impressions', 'ai-seo-captain'); ?>
                    &middot; <?php esc_html_e('Avg. pos:', 'ai-seo-captain'); ?> <?php echo esc_html(number_format($gsc_summary['position'], 1)); ?>
                </p>
                <p style="margin:4px 0 0;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-captain-search-console')); ?>"><?php esc_html_e('View full report →', 'ai-seo-captain'); ?></a>
                </p>
            <?php else : ?>
                <p style="font-size:28px;margin:0;color:#c3c4c7;">0</p>
                <p style="margin:8px 0 0;color:#a7aaad;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-captain-search-console')); ?>"><?php esc_html_e('Connect Google Search Console →', 'ai-seo-captain'); ?></a>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== Search Console Performance Section ===== -->
    <div style="display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:16px;max-width:1120px;margin-top:24px;<?php echo $gsc_grey; ?>">
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;text-align:center;">
            <p style="font-size:28px;margin:0;font-weight:700;color:<?php echo $gsc_has_data ? '#4285f4' : '#c3c4c7'; ?>;"><?php echo $gsc_has_data ? esc_html(number_format_i18n($gsc_summary['clicks'])) : '—'; ?></p>
            <p style="margin:4px 0 0;color:#646970;text-transform:uppercase;font-size:11px;letter-spacing:.5px;"><?php esc_html_e('Clicks', 'ai-seo-captain'); ?></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;text-align:center;">
            <p style="font-size:28px;margin:0;font-weight:700;color:<?php echo $gsc_has_data ? '#5e35b1' : '#c3c4c7'; ?>;"><?php echo $gsc_has_data ? esc_html(number_format_i18n($gsc_summary['impressions'])) : '—'; ?></p>
            <p style="margin:4px 0 0;color:#646970;text-transform:uppercase;font-size:11px;letter-spacing:.5px;"><?php esc_html_e('Impressions', 'ai-seo-captain'); ?></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;text-align:center;">
            <p style="font-size:28px;margin:0;font-weight:700;color:<?php echo $gsc_has_data ? '#00897b' : '#c3c4c7'; ?>;"><?php echo $gsc_has_data ? esc_html(number_format($gsc_summary['ctr'] * 100, 1)) . '%' : '—'; ?></p>
            <p style="margin:4px 0 0;color:#646970;text-transform:uppercase;font-size:11px;letter-spacing:.5px;"><?php esc_html_e('CTR', 'ai-seo-captain'); ?></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;text-align:center;">
            <p style="font-size:28px;margin:0;font-weight:700;color:<?php echo $gsc_has_data ? '#e8710a' : '#c3c4c7'; ?>;"><?php echo $gsc_has_data ? esc_html(number_format($gsc_summary['position'], 1)) : '—'; ?></p>
            <p style="margin:4px 0 0;color:#646970;text-transform:uppercase;font-size:11px;letter-spacing:.5px;"><?php esc_html_e('Avg. Position', 'ai-seo-captain'); ?></p>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(320px,1fr));gap:16px;max-width:1120px;margin-top:16px;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;<?php echo $gsc_grey; ?>">
            <h2 style="margin-top:0;">
                <span class="dashicons dashicons-search" style="color:#4285f4;vertical-align:middle;margin-right:4px;"></span>
                <?php esc_html_e('Top Queries (30 days)', 'ai-seo-captain'); ?>
                <?php if (! empty($gsc_top_queries)) : ?>
                    <span style="background:#4285f4;color:#fff;font-size:11px;padding:2px 8px;border-radius:10px;vertical-align:middle;margin-left:6px;"><?php echo count($gsc_top_queries); ?></span>
                <?php endif; ?>
            </h2>
            <?php if (! $gsc_has_data) : ?>
                <p style="color:#a7aaad;"><?php esc_html_e('Connect Google Search Console to see which search queries bring visitors to your site.', 'ai-seo-captain'); ?></p>
            <?php elseif (empty($gsc_top_queries)) : ?>
                <p style="color:#646970;"><?php esc_html_e('No query data available yet. Sync data from the Search Console page.', 'ai-seo-captain'); ?></p>
            <?php else : ?>
                <table class="widefat striped" style="margin-top:8px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Query', 'ai-seo-captain'); ?></th>
                            <th style="text-align:right;"><?php esc_html_e('Clicks', 'ai-seo-captain'); ?></th>
                            <th style="text-align:right;"><?php esc_html_e('Impressions', 'ai-seo-captain'); ?></th>
                            <th style="text-align:right;"><?php esc_html_e('CTR', 'ai-seo-captain'); ?></th>
                            <th style="text-align:right;"><?php esc_html_e('Position', 'ai-seo-captain'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gsc_top_queries as $q) : ?>
                            <tr>
                                <td><?php echo esc_html($q->dimension_value); ?></td>
                                <td style="text-align:right;"><?php echo esc_html(number_format_i18n($q->clicks)); ?></td>
                                <td style="text-align:right;"><?php echo esc_html(number_format_i18n($q->impressions)); ?></td>
                                <td style="text-align:right;"><?php echo esc_html(number_format($q->ctr * 100, 1)); ?>%</td>
                                <td style="text-align:right;"><?php echo esc_html(number_format($q->position, 1)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;<?php echo $gsc_grey; ?>">
            <h2 style="margin-top:0;">
                <span class="dashicons dashicons-admin-page" style="color:#5e35b1;vertical-align:middle;margin-right:4px;"></span>
                <?php esc_html_e('Top Pages (30 days)', 'ai-seo-captain'); ?>
                <?php if (! empty($gsc_top_pages)) : ?>
                    <span style="background:#5e35b1;color:#fff;font-size:11px;padding:2px 8px;border-radius:10px;vertical-align:middle;margin-left:6px;"><?php echo count($gsc_top_pages); ?></span>
                <?php endif; ?>
            </h2>
            <?php if (! $gsc_has_data) : ?>
                <p style="color:#a7aaad;"><?php esc_html_e('Connect Google Search Console to see which pages get the most traffic from search.', 'ai-seo-captain'); ?></p>
            <?php elseif (empty($gsc_top_pages)) : ?>
                <p style="color:#646970;"><?php esc_html_e('No page data available yet. Sync data from the Search Console page.', 'ai-seo-captain'); ?></p>
            <?php else : ?>
                <table class="widefat striped" style="margin-top:8px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Page', 'ai-seo-captain'); ?></th>
                            <th style="text-align:right;"><?php esc_html_e('Clicks', 'ai-seo-captain'); ?></th>
                            <th style="text-align:right;"><?php esc_html_e('Impressions', 'ai-seo-captain'); ?></th>
                            <th style="text-align:right;"><?php esc_html_e('CTR', 'ai-seo-captain'); ?></th>
                            <th style="text-align:right;"><?php esc_html_e('Position', 'ai-seo-captain'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gsc_top_pages as $pg) :
                            $page_path = $pg->dimension_value;
                            try {
                                $page_path = wp_parse_url($pg->dimension_value, PHP_URL_PATH) ?: $pg->dimension_value;
                            } catch (\Exception $e) { /* keep original */
                            }
                        ?>
                            <tr>
                                <td title="<?php echo esc_attr($pg->dimension_value); ?>"><?php echo esc_html($page_path); ?></td>
                                <td style="text-align:right;"><?php echo esc_html(number_format_i18n($pg->clicks)); ?></td>
                                <td style="text-align:right;"><?php echo esc_html(number_format_i18n($pg->impressions)); ?></td>
                                <td style="text-align:right;"><?php echo esc_html(number_format($pg->ctr * 100, 1)); ?>%</td>
                                <td style="text-align:right;"><?php echo esc_html(number_format($pg->position, 1)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <p style="margin:12px 0 0;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-captain-search-console')); ?>"><?php esc_html_e('View full Search Console report →', 'ai-seo-captain'); ?></a>
            </p>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(280px,1fr));gap:16px;max-width:1120px;margin-top:24px;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Coverage Gaps', 'ai-seo-captain'); ?></h2>
            <p style="margin:0 0 8px;"><?php esc_html_e('Missing AI title drafts:', 'ai-seo-captain'); ?> <strong><?php echo esc_html((string) $summary['missing_title_drafts']); ?></strong></p>
            <p style="margin:0 0 8px;"><?php esc_html_e('Missing AI description drafts:', 'ai-seo-captain'); ?> <strong><?php echo esc_html((string) $summary['missing_description_drafts']); ?></strong></p>
            <p style="margin:0 0 8px;"><?php esc_html_e('Frontend opt-in pages:', 'ai-seo-captain'); ?> <strong><?php echo esc_html((string) $summary['frontend_enabled_items']); ?></strong></p>
            <p style="margin:0 0 8px;"><?php esc_html_e('Frontend-ready pages:', 'ai-seo-captain'); ?> <strong><?php echo esc_html((string) $summary['frontend_ready_items']); ?></strong></p>
            <?php
            global $wpdb;
            $cornerstone_count = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_ai_seo_captain_cornerstone' AND meta_value = '1'"
            );
            ?>
            <p style="margin:0;"><?php esc_html_e('Cornerstone pages:', 'ai-seo-captain'); ?> <strong><?php echo $cornerstone_count; ?></strong></p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('What This Score Means', 'ai-seo-captain'); ?></h2>
            <p style="margin:0 0 8px;"><?php esc_html_e('The readiness score is operational, not predictive. It measures rollout maturity using draft coverage, approval coverage, and frontend readiness.', 'ai-seo-captain'); ?></p>
            <p style="margin:0;"><?php esc_html_e('Use it to prioritize execution order, not to claim rankings.', 'ai-seo-captain'); ?></p>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(320px,1fr));gap:16px;max-width:1120px;margin-top:24px;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('IndexNow Status', 'ai-seo-captain'); ?></h2>
            <p style="margin:0 0 8px;"><?php esc_html_e('Enabled:', 'ai-seo-captain'); ?> <strong><?php echo $indexnow_enabled ? esc_html__('Yes', 'ai-seo-captain') : esc_html__('No', 'ai-seo-captain'); ?></strong></p>
            <p style="margin:0 0 8px;"><?php esc_html_e('Auto-submit:', 'ai-seo-captain'); ?> <strong><?php echo $indexnow_auto_submit ? esc_html__('Yes', 'ai-seo-captain') : esc_html__('No', 'ai-seo-captain'); ?></strong></p>
            <?php if ('' !== $indexnow_key_url) : ?>
                <p style="margin:0 0 8px;"><?php esc_html_e('Key URL:', 'ai-seo-captain'); ?> <a href="<?php echo esc_url($indexnow_key_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($indexnow_key_url); ?></a></p>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px;">
                <?php wp_nonce_field('ai_seo_captain_submit_indexnow'); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr($submit_indexnow_action); ?>" />
                <button type="submit" class="button button-outline"><?php esc_html_e('Submit Priority Queue to IndexNow', 'ai-seo-captain'); ?></button>
            </form>
            <p style="margin:12px 0 0;color:#50575e;"><?php esc_html_e('On localhost this will log a safe skip instead of calling the live IndexNow endpoint.', 'ai-seo-captain'); ?></p>
        </div>

        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <details id="aisc-indexnow-details" open>
                <summary style="cursor:pointer;font-size:14px;font-weight:600;margin-bottom:12px;"><?php esc_html_e('Recent IndexNow Activity', 'ai-seo-captain'); ?> (<?php echo count($indexnow_log); ?>)</summary>
                <?php if (empty($indexnow_log)) : ?>
                    <p style="margin:0;"><?php esc_html_e('No IndexNow submissions or skips have been logged yet.', 'ai-seo-captain'); ?></p>
                <?php else : ?>
                    <div id="aisc-indexnow-entries">
                        <?php foreach ($indexnow_log as $idx => $entry) : ?>
                            <div class="aisc-indexnow-entry" style="padding:12px;border:1px solid #dcdcde;background:#f6f7f7;margin-bottom:12px;<?php echo $idx >= 3 ? 'display:none;' : ''; ?>">
                                <p style="margin:0 0 8px;"><strong><?php echo esc_html(strtoupper((string) $entry['status'])); ?></strong> | <?php echo esc_html((string) $entry['reason']); ?></p>
                                <p style="margin:0 0 8px;"><?php echo esc_html((string) $entry['message']); ?></p>
                                <p style="margin:0;color:#50575e;"><?php esc_html_e('URLs:', 'ai-seo-captain'); ?> <?php echo esc_html((string) $entry['url_count']); ?><?php if (! empty($entry['created_at'])) : ?> | <?php echo esc_html((string) $entry['created_at']); ?><?php endif; ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="display:flex;gap:8px;margin-top:8px;">
                        <?php if (count($indexnow_log) > 3) : ?>
                            <button type="button" id="aisc-indexnow-loadmore" class="button button-secondary" style="font-size:12px;"><?php esc_html_e('Load More', 'ai-seo-captain'); ?></button>
                        <?php endif; ?>
                        <button type="button" id="aisc-indexnow-export" class="button button-secondary" style="font-size:12px;"><?php esc_html_e('Export All (.txt)', 'ai-seo-captain'); ?></button>
                    </div>
                <?php endif; ?>
            </details>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(320px,1fr));gap:16px;max-width:1120px;margin-top:24px;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('AI Strategic Audit', 'ai-seo-captain'); ?></h2>
            <p><?php esc_html_e('Use the deterministic report as the source of truth, then ask the configured model to turn it into a short execution plan.', 'ai-seo-captain'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:16px;">
                <?php wp_nonce_field('ai_seo_captain_generate_site_audit'); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr($generate_site_audit_action); ?>" />
                <button type="submit" class="button" <?php disabled(! $has_api_key); ?>><?php esc_html_e('Generate AI Strategic Audit', 'ai-seo-captain'); ?></button>
            </form>
            <?php if (! $has_api_key) : ?>
                <p style="margin:12px 0 0;color:#8a2424;"><?php esc_html_e('Add an API key in Settings before generating AI strategic audits.', 'ai-seo-captain'); ?></p>
            <?php else : ?>
                <p style="margin:12px 0 0;color:#50575e;"><?php esc_html_e('This keeps the human review step: AI proposes the site-level plan, but deterministic checks continue to drive the facts underneath it.', 'ai-seo-captain'); ?></p>
            <?php endif; ?>
        </div>

        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <details id="aisc-siteaudits-details" open>
                <summary style="cursor:pointer;font-size:14px;font-weight:600;margin-bottom:12px;"><?php esc_html_e('Recent AI Site Audits', 'ai-seo-captain'); ?> (<?php echo count($site_audits); ?>)</summary>
                <?php if (empty($site_audits)) : ?>
                    <p style="margin:0;"><?php esc_html_e('No AI site audits have been generated yet.', 'ai-seo-captain'); ?></p>
                <?php else : ?>
                    <div id="aisc-siteaudits-entries">
                        <?php foreach ($site_audits as $idx => $audit) : ?>
                            <div class="aisc-siteaudit-entry" style="padding:12px;border:1px solid #dcdcde;background:#f6f7f7;margin-bottom:12px;<?php echo $idx >= 3 ? 'display:none;' : ''; ?>">
                                <p style="margin:0 0 8px;"><strong><?php echo esc_html($audit['audit_title']); ?></strong></p>
                                <p style="margin:0 0 8px;"><?php echo esc_html($audit['executive_summary']); ?></p>
                                <?php if (! empty($audit['priority_actions'])) : ?>
                                    <p style="margin:0 0 8px;"><strong><?php esc_html_e('Priority actions', 'ai-seo-captain'); ?></strong></p>
                                    <ul style="margin:0 0 8px;padding-left:20px;">
                                        <?php foreach ($audit['priority_actions'] as $item) : ?>
                                            <li><?php echo esc_html((string) $item); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <?php if (! empty($audit['quick_wins'])) : ?>
                                    <p style="margin:0 0 8px;"><strong><?php esc_html_e('Quick wins', 'ai-seo-captain'); ?></strong></p>
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
                    </div>
                    <div style="display:flex;gap:8px;margin-top:8px;">
                        <?php if (count($site_audits) > 3) : ?>
                            <button type="button" id="aisc-siteaudits-loadmore" class="button button-secondary" style="font-size:12px;"><?php esc_html_e('Load More', 'ai-seo-captain'); ?></button>
                        <?php endif; ?>
                        <button type="button" id="aisc-siteaudits-export" class="button button-secondary" style="font-size:12px;"><?php esc_html_e('Export All (.txt)', 'ai-seo-captain'); ?></button>
                    </div>
                <?php endif; ?>
            </details>
        </div>
    </div>

    <div style="background:#fff;border:1px solid #dcdcde;padding:20px;max-width:1120px;margin-top:24px;">
        <h2><?php esc_html_e('Priority Queue', 'ai-seo-captain'); ?><?php echo \AI_SEO_Captain\Admin::info(__('Pages sorted by urgency: published pages missing AI drafts come first. Work through this list top-to-bottom for maximum impact.', 'ai-seo-captain')); ?> <span style="font-weight:normal;color:#50575e;font-size:14px;">(<?php echo count($report['priority_rows']); ?>)</span></h2>
        <p><?php esc_html_e('These rows are ordered toward published content with missing AI drafts first, then by approval state and freshness.', 'ai-seo-captain'); ?></p>
        <table class="widefat striped" id="aisc-priority-table" style="margin-top:12px;">
            <thead>
                <tr>
                    <th><?php esc_html_e('Content', 'ai-seo-captain'); ?></th>
                    <th><?php esc_html_e('Drafts', 'ai-seo-captain'); ?></th>
                    <th><?php esc_html_e('Approval', 'ai-seo-captain'); ?></th>
                    <th><?php esc_html_e('Frontend', 'ai-seo-captain'); ?></th>
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
                            <?php esc_html_e('Title:', 'ai-seo-captain'); ?> <?php echo $row['has_title_draft'] ? esc_html__('Yes', 'ai-seo-captain') : esc_html__('No', 'ai-seo-captain'); ?><br />
                            <?php esc_html_e('Description:', 'ai-seo-captain'); ?> <?php echo $row['has_description_draft'] ? esc_html__('Yes', 'ai-seo-captain') : esc_html__('No', 'ai-seo-captain'); ?>
                        </td>
                        <td><?php echo $row['has_approved_suggestion'] ? esc_html__('Approved', 'ai-seo-captain') : esc_html__('Pending', 'ai-seo-captain'); ?></td>
                        <td>
                            <?php esc_html_e('Gate:', 'ai-seo-captain'); ?> <?php echo $row['frontend_enabled'] ? esc_html__('On', 'ai-seo-captain') : esc_html__('Off', 'ai-seo-captain'); ?><br />
                            <?php esc_html_e('Ready:', 'ai-seo-captain'); ?> <?php echo $row['frontend_ready'] ? esc_html__('Yes', 'ai-seo-captain') : esc_html__('No', 'ai-seo-captain'); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (count($report['priority_rows']) > 10) : ?>
            <div class="aisc-pagination" id="aisc-priority-pagination" style="display:flex;justify-content:center;margin-top:12px;"></div>
        <?php endif; ?>
    </div>

    <div style="background:#fff;border:1px solid #dcdcde;padding:20px;max-width:1120px;margin-top:24px;">
        <h2><?php esc_html_e('Approved Rollout Queue', 'ai-seo-captain'); ?></h2>
        <p><?php esc_html_e('These pages already have an approved AI suggestion. Use this queue to enable or disable the page-level frontend gate in batches without weakening approval rules.', 'ai-seo-captain'); ?></p>
        <?php if (empty($report['rollout_candidates'])) : ?>
            <p style="margin:0;"><?php esc_html_e('No approved pages are waiting for rollout right now.', 'ai-seo-captain'); ?></p>
        <?php else : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ai_seo_captain_bulk_frontend_rollout'); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr($bulk_frontend_action); ?>" />
                <table class="widefat striped" style="margin-top:12px;">
                    <thead>
                        <tr>
                            <th style="width:36px;"></th>
                            <th><?php esc_html_e('Content', 'ai-seo-captain'); ?></th>
                            <th><?php esc_html_e('Status', 'ai-seo-captain'); ?></th>
                            <th><?php esc_html_e('Frontend Gate', 'ai-seo-captain'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report['rollout_candidates'] as $row) : ?>
                            <tr>
                                <td><input type="checkbox" name="post_ids[]" value="<?php echo (int) $row['object_id']; ?>" /></td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $row['object_id'] . '&action=edit')); ?>"><?php echo esc_html($row['title']); ?></a>
                                    <div style="margin-top:4px;"><a href="<?php echo esc_url($row['permalink']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View page', 'ai-seo-captain'); ?></a></div>
                                </td>
                                <td><?php echo esc_html($row['post_type']); ?> / <?php echo esc_html($row['status']); ?><br /><?php esc_html_e('Approved', 'ai-seo-captain'); ?></td>
                                <td><?php echo $row['frontend_enabled'] ? esc_html__('On', 'ai-seo-captain') : esc_html__('Off', 'ai-seo-captain'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <button type="submit" class="button button-primary" name="bulk_mode" value="enable_frontend"><?php esc_html_e('Enable Frontend For Approved Selections', 'ai-seo-captain'); ?></button>
                    <button type="submit" class="button button-secondary" name="bulk_mode" value="disable_frontend"><?php esc_html_e('Disable Frontend For Selections', 'ai-seo-captain'); ?></button>
                    <span style="color:#50575e;"><?php esc_html_e('When IndexNow is enabled, bulk enable actions also send refresh signals for the changed pages.', 'ai-seo-captain'); ?></span>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(320px,1fr));gap:16px;max-width:1120px;margin-top:24px;">
        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Unapproved Draft Candidates', 'ai-seo-captain'); ?></h2>
            <?php if (empty($report['draft_candidates'])) : ?>
                <p style="margin:0;"><?php esc_html_e('No pages currently have unapproved drafts waiting in the queue.', 'ai-seo-captain'); ?></p>
            <?php else : ?>
                <ul style="margin:0;padding-left:20px;">
                    <?php foreach ($report['draft_candidates'] as $candidate) : ?>
                        <li style="margin-bottom:10px;">
                            <a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $candidate['object_id'] . '&action=edit')); ?>"><?php echo esc_html($candidate['title']); ?></a>:
                            <?php echo $candidate['has_title_draft'] ? esc_html__('title draft', 'ai-seo-captain') : esc_html__('no title draft', 'ai-seo-captain'); ?>,
                            <?php echo $candidate['has_description_draft'] ? esc_html__('description draft', 'ai-seo-captain') : esc_html__('no description draft', 'ai-seo-captain'); ?>,
                            <?php esc_html_e('frontend gate', 'ai-seo-captain'); ?> <?php echo $candidate['frontend_enabled'] ? esc_html__('on', 'ai-seo-captain') : esc_html__('off', 'ai-seo-captain'); ?>.
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Thin Content Risks', 'ai-seo-captain'); ?><?php echo \AI_SEO_Captain\Admin::info(__('Pages with very few words. Google may consider them low-quality and rank them lower. Aim for 300+ words on important pages.', 'ai-seo-captain')); ?></h2>
            <?php if (empty($report['thin_content_rows'])) : ?>
                <p style="margin:0;"><?php esc_html_e('No thin-content risks were flagged by the current word-count threshold.', 'ai-seo-captain'); ?></p>
            <?php else : ?>
                <table class="widefat striped" style="margin-top:12px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Content', 'ai-seo-captain'); ?></th>
                            <th><?php esc_html_e('Words', 'ai-seo-captain'); ?></th>
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
            <h2 style="margin-top:0;"><?php esc_html_e('Duplicate Native Titles', 'ai-seo-captain'); ?><?php echo \AI_SEO_Captain\Admin::info(__('Multiple pages sharing the exact same title. Search engines may struggle to decide which one to rank. Each page should have a unique title.', 'ai-seo-captain')); ?></h2>
            <?php if (empty($report['duplicate_post_titles'])) : ?>
                <p style="margin:0;"><?php esc_html_e('No exact duplicate published page titles were detected in the indexed content.', 'ai-seo-captain'); ?></p>
            <?php else : ?>
                <ul style="margin:0;padding-left:20px;">
                    <?php foreach ($report['duplicate_post_titles'] as $group) : ?>
                        <li style="margin-bottom:10px;">
                            <strong><?php echo esc_html($group['label']); ?></strong> <?php echo esc_html(sprintf(__('appears %d times:', 'ai-seo-captain'), $group['total_count'])); ?>
                            <?php echo $admin->render_audit_post_links($group['ids']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div style="background:#fff;border:1px solid #dcdcde;padding:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Duplicate AI Draft Titles', 'ai-seo-captain'); ?></h2>
            <?php if (empty($report['duplicate_ai_titles'])) : ?>
                <p style="margin:0;"><?php esc_html_e('No exact duplicate AI title drafts were detected.', 'ai-seo-captain'); ?></p>
            <?php else : ?>
                <ul style="margin:0;padding-left:20px;">
                    <?php foreach ($report['duplicate_ai_titles'] as $group) : ?>
                        <li style="margin-bottom:10px;">
                            <strong><?php echo esc_html($group['label']); ?></strong> <?php echo esc_html(sprintf(__('appears %d times:', 'ai-seo-captain'), $group['total_count'])); ?>
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
        <h2 style="margin-top:0;"><?php esc_html_e('Orphaned Content', 'ai-seo-captain'); ?><?php echo \AI_SEO_Captain\Admin::info(__('Pages with zero internal links pointing to them. Search engine crawlers discover pages by following links, so orphaned pages may never get indexed.', 'ai-seo-captain')); ?> <span style="font-weight:normal;color:#50575e;font-size:14px;">(<?php echo esc_html(sprintf(__('%d of %d pages', 'ai-seo-captain'), $orphaned['total_orphans'], $orphaned['total_pages'])); ?>)</span></h2>
        <p style="margin:0 0 12px;color:#50575e;"><?php esc_html_e('Pages with zero inbound internal links. These are invisible to crawlers that follow links and may never get indexed.', 'ai-seo-captain'); ?></p>
        <?php if (empty($orphaned['orphans'])) : ?>
            <p style="margin:0;color:#00a32a;"><strong><?php esc_html_e('No orphaned content detected.', 'ai-seo-captain'); ?></strong> <?php esc_html_e('Every indexed page has at least one internal link pointing to it.', 'ai-seo-captain'); ?></p>
        <?php else : ?>
            <table class="widefat striped ai-seo-sortable" id="aisc-orphaned-table" style="margin-top:8px;">
                <thead>
                    <tr>
                        <th class="ai-seo-sort" data-col="0"><?php esc_html_e('Page', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                        <th class="ai-seo-sort" data-col="1"><?php esc_html_e('Type', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                        <th class="ai-seo-sort" data-col="2"><?php esc_html_e('Inbound links', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orphaned['orphans'] as $row) : ?>
                        <tr>
                            <td data-sort-value="<?php echo esc_attr(strtolower($row['title'])); ?>">
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $row['object_id'] . '&action=edit')); ?>"><?php echo esc_html($row['title']); ?></a>
                                <div style="margin-top:2px;"><a href="<?php echo esc_url($row['permalink']); ?>" target="_blank" rel="noopener" style="color:#50575e;font-size:12px;"><?php esc_html_e('View', 'ai-seo-captain'); ?></a></div>
                            </td>
                            <td data-sort-value="<?php echo esc_attr(strtolower($row['post_type'])); ?>"><?php echo esc_html($row['post_type']); ?></td>
                            <td data-sort-value="0" style="color:#d63638;"><strong>0</strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($orphaned['total_orphans'] > 10) : ?>
                <div class="aisc-pagination" id="aisc-orphaned-pagination" style="display:flex;justify-content:center;margin-top:12px;"></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>