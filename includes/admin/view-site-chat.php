<?php

/**
 * Site-wide AI Chat admin page view.
 *
 * @var \AI_SEO_Keeper\Site_Chat $site_chat
 * @var array                    $dashboard
 * @var array                    $chat_messages
 */

defined('ABSPATH') || exit;
?>
<div class="wrap ai-seo-keeper-site-chat-wrap">
    <h1><?php esc_html_e('AI SEO Strategist', 'ai-seo-keeper'); ?></h1>
    <p class="description"><?php esc_html_e('Chat with AI about your entire site — structure, keyphrase conflicts, audit results, and strategic recommendations.', 'ai-seo-keeper'); ?></p>

    <!-- Summary cards -->
    <div class="ai-seo-keeper-site-chat-cards">
        <div class="ai-seo-card">
            <span class="ai-seo-card-number"><?php echo (int) $dashboard['published_pages']; ?></span>
            <span class="ai-seo-card-label"><?php esc_html_e('Published Pages', 'ai-seo-keeper'); ?></span>
        </div>
        <div class="ai-seo-card <?php echo (int) $dashboard['readiness_score'] >= 70 ? 'is-good' : ((int) $dashboard['readiness_score'] >= 40 ? 'is-warning' : 'is-bad'); ?>">
            <span class="ai-seo-card-number"><?php echo (int) $dashboard['readiness_score']; ?>%</span>
            <span class="ai-seo-card-label"><?php echo esc_html($dashboard['readiness_label']); ?></span>
        </div>
        <div class="ai-seo-card <?php echo (int) $dashboard['missing_titles'] > 0 ? 'is-warning' : 'is-good'; ?>">
            <span class="ai-seo-card-number"><?php echo (int) $dashboard['missing_titles']; ?></span>
            <span class="ai-seo-card-label"><?php esc_html_e('Missing Titles', 'ai-seo-keeper'); ?></span>
        </div>
        <div class="ai-seo-card <?php echo (int) $dashboard['missing_descs'] > 0 ? 'is-warning' : 'is-good'; ?>">
            <span class="ai-seo-card-number"><?php echo (int) $dashboard['missing_descs']; ?></span>
            <span class="ai-seo-card-label"><?php esc_html_e('Missing Descriptions', 'ai-seo-keeper'); ?></span>
        </div>
        <div class="ai-seo-card <?php echo (int) $dashboard['orphans'] > 0 ? 'is-bad' : 'is-good'; ?>">
            <span class="ai-seo-card-number"><?php echo (int) $dashboard['orphans']; ?></span>
            <span class="ai-seo-card-label"><?php esc_html_e('Orphan Pages', 'ai-seo-keeper'); ?></span>
        </div>
        <div class="ai-seo-card <?php echo (int) $dashboard['errors_404'] > 0 ? 'is-bad' : 'is-good'; ?>">
            <span class="ai-seo-card-number"><?php echo (int) $dashboard['errors_404']; ?></span>
            <span class="ai-seo-card-label"><?php esc_html_e('404 Errors', 'ai-seo-keeper'); ?></span>
        </div>
        <div class="ai-seo-card">
            <span class="ai-seo-card-number"><?php echo (int) $dashboard['total_images']; ?></span>
            <span class="ai-seo-card-label"><?php esc_html_e('Images', 'ai-seo-keeper'); ?>
                <?php if ((int) $dashboard['missing_alt'] > 0) : ?>
                    <small>(<?php echo (int) $dashboard['missing_alt']; ?> <?php esc_html_e('missing alt', 'ai-seo-keeper'); ?>)</small>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <!-- Chat panel -->
    <div class="ai-seo-keeper-site-chat-panel">
        <div class="ai-seo-keeper-site-chat-intro">
            <?php esc_html_e('Ask about overall SEO health, site structure, keyphrase strategy, content gaps, or any site-wide concern. AI sees your full site tree, all audit scores, and all SEO data.', 'ai-seo-keeper'); ?>
        </div>

        <textarea id="ai-seo-site-chat-input" class="widefat ai-seo-keeper-chat-input" rows="3"
            placeholder="<?php esc_attr_e('e.g. "What are my biggest SEO issues?" or "Which pages have keyphrase conflicts?"', 'ai-seo-keeper'); ?>"></textarea>

        <p class="ai-seo-keeper-chat-actions" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <button type="button" id="ai-seo-site-chat-send" class="button button-primary"><?php esc_html_e('Ask AI', 'ai-seo-keeper'); ?></button>
            <button type="button" id="ai-seo-site-chat-clear" class="button"><?php esc_html_e('Clear Chat', 'ai-seo-keeper'); ?></button>
            <span id="ai-seo-site-chat-status" class="ai-seo-keeper-chat-status" aria-live="polite"></span>
        </p>

        <div id="ai-seo-site-chat-shell" class="ai-seo-keeper-chat-shell">
            <?php echo $site_chat->render_chat_html($chat_messages); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered with escaping inside render_chat_html 
            ?>
        </div>
    </div>
</div>