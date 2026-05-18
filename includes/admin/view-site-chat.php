<?php

/**
 * Site-wide AI Chat admin page view.
 *
 * @var \AI_SEO_Captain\Site_Chat $site_chat
 * @var array                    $dashboard
 * @var array                    $chat_messages
 * @var array                    $readiness
 * @var string                   $readiness_banner
 * @var array                    $runs
 * @var array                    $active_run_ids
 */

defined('ABSPATH') || exit;
?>
<div class="wrap ai-seo-captain-site-chat-wrap">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px;">
        <img src="<?php echo esc_url(AI_SEO_CAPTAIN_URL . 'assets/img/ai-seo-captain-d.svg'); ?>" alt="SEO Captain" style="width:40px;height:40px;" />
        <h1 style="margin:0;"><?php esc_html_e('AI Captain', 'ai-seo-captain'); ?></h1>
    </div>
    <p class="description"><?php esc_html_e('Chat with AI about your entire site — structure, keyphrase conflicts, audit results, and strategic recommendations.', 'ai-seo-captain'); ?></p>

    <?php echo $readiness_banner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in get_readiness_banner_html 
    ?>

    <!-- Lists panel — always visible when index exists -->
    <?php if ($readiness['has_index']) : ?>
        <div class="aisc-captain-lists" id="aisc-captain-lists">
            <div class="aisc-captain-lists__header">
                <h3><span class="dashicons dashicons-list-view"></span> <?php esc_html_e('Your Lists', 'ai-seo-captain'); ?></h3>
                <p class="description"><?php esc_html_e('Lists created from the Setup Wizard. AI Captain is enabled when at least one list or the full site has been audited.', 'ai-seo-captain'); ?></p>
            </div>
            <div class="aisc-captain-lists__grid" id="aisc-lists-grid">
                <!-- Populated by JS from localized data -->
            </div>
            <?php if (empty($runs)) : ?>
                <p class="aisc-captain-lists__empty">
                    <span class="dashicons dashicons-info-outline"></span>
                    <?php
                    printf(
                        /* translators: %s: link to Setup Wizard */
                        esc_html__('No lists yet. Go to the %s to create lists by selecting pages for metadata generation or audit.', 'ai-seo-captain'),
                        '<a href="' . esc_url(admin_url('admin.php?page=ai-seo-captain-setup')) . '">' . esc_html__('Setup Wizard', 'ai-seo-captain') . '</a>'
                    );
                    ?>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Summary cards -->
    <div class="ai-seo-captain-site-chat-cards">
        <div class="ai-seo-card">
            <span class="ai-seo-card-number"><?php echo (int) $dashboard['published_pages']; ?></span>
            <span class="ai-seo-card-label"><?php esc_html_e('Published Pages', 'ai-seo-captain'); ?></span>
        </div>
        <div class="ai-seo-card <?php echo (int) $dashboard['readiness_score'] >= 70 ? 'is-good' : ((int) $dashboard['readiness_score'] >= 40 ? 'is-warning' : 'is-bad'); ?>">
            <span class="ai-seo-card-number"><?php echo (int) $dashboard['readiness_score']; ?>%</span>
            <span class="ai-seo-card-label"><?php echo esc_html($dashboard['readiness_label']); ?></span>
        </div>
        <div class="ai-seo-card <?php echo (int) $dashboard['missing_titles'] > 0 ? 'is-warning' : 'is-good'; ?>">
            <span class="ai-seo-card-number"><?php echo (int) $dashboard['missing_titles']; ?></span>
            <span class="ai-seo-card-label"><?php esc_html_e('Missing Titles', 'ai-seo-captain'); ?></span>
        </div>
        <div class="ai-seo-card <?php echo (int) $dashboard['missing_descs'] > 0 ? 'is-warning' : 'is-good'; ?>">
            <span class="ai-seo-card-number"><?php echo (int) $dashboard['missing_descs']; ?></span>
            <span class="ai-seo-card-label"><?php esc_html_e('Missing Descriptions', 'ai-seo-captain'); ?></span>
        </div>
        <div class="ai-seo-card <?php echo (int) $dashboard['orphans'] > 0 ? 'is-bad' : 'is-good'; ?>">
            <span class="ai-seo-card-number"><?php echo (int) $dashboard['orphans']; ?></span>
            <span class="ai-seo-card-label"><?php esc_html_e('Orphan Pages', 'ai-seo-captain'); ?></span>
        </div>
        <div class="ai-seo-card <?php echo (int) $dashboard['errors_404'] > 0 ? 'is-bad' : 'is-good'; ?>">
            <span class="ai-seo-card-number"><?php echo (int) $dashboard['errors_404']; ?></span>
            <span class="ai-seo-card-label"><?php esc_html_e('404 Errors', 'ai-seo-captain'); ?></span>
        </div>
        <div class="ai-seo-card">
            <span class="ai-seo-card-number"><?php echo (int) $dashboard['total_images']; ?></span>
            <span class="ai-seo-card-label"><?php esc_html_e('Images', 'ai-seo-captain'); ?>
                <?php if ((int) $dashboard['missing_alt'] > 0) : ?>
                    <small>(<?php echo (int) $dashboard['missing_alt']; ?> <?php esc_html_e('missing alt', 'ai-seo-captain'); ?>)</small>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <!-- Chat panel — disabled when no audit data -->
    <div class="<?php echo ! $readiness['is_ready'] ? 'ai-seo-captain-disabled-section' : ''; ?>">
        <div class="ai-seo-captain-site-chat-panel">
            <div class="ai-seo-captain-site-chat-intro">
                <?php esc_html_e('Ask about overall SEO health, site structure, keyphrase strategy, content gaps, or any site-wide concern. AI sees your full site tree, all audit scores, and all SEO data.', 'ai-seo-captain'); ?>
            </div>

            <textarea id="ai-seo-site-chat-input" class="widefat ai-seo-captain-chat-input" rows="3"
                placeholder="<?php esc_attr_e('e.g. "What are my biggest SEO issues?" or "Which pages have keyphrase conflicts?"', 'ai-seo-captain'); ?>"></textarea>

            <!-- Focus Pages — select audited pages from any list -->
            <details id="ai-seo-focus-pages-toggle" class="ai-seo-captain-focus-pages">
                <summary style="cursor:pointer;user-select:none;font-weight:600;margin:8px 0 4px;color:#643d87;">
                    <?php esc_html_e('Focus Pages (select from any list)', 'ai-seo-captain'); ?>
                    <span id="ai-seo-capacity-badge" class="ai-seo-captain-capacity-badge"></span>
                </summary>
                <div style="margin-top:8px;">
                    <p class="description" style="margin:0 0 6px;">
                        <?php esc_html_e('Select specific audited pages from any list to discuss with AI. This lets you cross-reference pages across lists.', 'ai-seo-captain'); ?>
                    </p>
                    <div id="ai-seo-capacity-info" class="ai-seo-captain-capacity-info" style="margin:0 0 8px;padding:8px 12px;border-radius:4px;font-size:13px;"></div>
                    <div id="ai-seo-focus-selector" class="aisc-focus-selector">
                        <div class="aisc-focus-selector__search">
                            <input type="text" id="ai-seo-focus-search" placeholder="<?php esc_attr_e('Search pages...', 'ai-seo-captain'); ?>" class="widefat" />
                        </div>
                        <div class="aisc-focus-selector__list" id="ai-seo-focus-list">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                    <p class="description" style="margin:4px 0 0;">
                        <span id="ai-seo-focus-count"><?php esc_html_e('0 pages selected', 'ai-seo-captain'); ?></span>
                    </p>
                </div>
            </details>

            <p class="ai-seo-captain-chat-actions" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <button type="button" id="ai-seo-site-chat-send" class="button button-primary"><?php esc_html_e('Ask AI', 'ai-seo-captain'); ?></button>
                <button type="button" id="ai-seo-site-chat-clear" class="button"><?php esc_html_e('Clear Chat', 'ai-seo-captain'); ?></button>
                <span id="ai-seo-site-chat-status" class="ai-seo-captain-chat-status" aria-live="polite"></span>
            </p>

            <div id="ai-seo-site-chat-shell" class="ai-seo-captain-chat-shell">
                <?php echo $site_chat->render_chat_html($chat_messages); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered with escaping inside render_chat_html 
                ?>
            </div>
        </div>
    </div>
</div>