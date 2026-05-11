<?php

namespace AI_SEO_Keeper;

/**
 * Site-wide AI Chat — allows users to discuss overall SEO performance,
 * site structure, keyphrase conflicts, and strategic recommendations
 * without being tied to a specific page.
 */
class Site_Chat
{
    public const OBJECT_TYPE = 'site_chat';

    private const OBJECT_ID = 0;

    private Settings $settings;

    private Content_Indexer $content_indexer;

    private Audit_Engine $audit_engine;

    private AI_Generator $ai_generator;

    private History_Store $history_store;

    public function __construct(
        Settings $settings,
        Content_Indexer $content_indexer,
        Audit_Engine $audit_engine,
        AI_Generator $ai_generator,
        History_Store $history_store
    ) {
        $this->settings        = $settings;
        $this->content_indexer = $content_indexer;
        $this->audit_engine    = $audit_engine;
        $this->ai_generator    = $ai_generator;
        $this->history_store   = $history_store;
    }

    // ------------------------------------------------------------------
    //  AJAX handler
    // ------------------------------------------------------------------

    public function handle_chat(): void
    {
        check_ajax_referer('ai_seo_keeper_site_chat', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-seo-keeper')), 403);
        }

        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

        if ('' === trim($message)) {
            wp_send_json_error(array('message' => __('Enter a question before asking the AI assistant.', 'ai-seo-keeper')), 400);
        }

        $options = $this->settings->get();

        if (empty($options['api_key'])) {
            wp_send_json_error(array('message' => __('Add an API key in AI SEO Keeper Settings before using the AI assistant.', 'ai-seo-keeper')), 400);
        }

        if (empty($options['editor_chat_enabled'])) {
            wp_send_json_error(array('message' => __('The AI assistant is disabled in settings.', 'ai-seo-keeper')), 400);
        }

        try {
            $recent_messages = $this->get_recent_messages(8);
            $reply           = $this->send_to_ai($message, $recent_messages, $options);

            $this->history_store->log_generation(
                self::OBJECT_ID,
                self::OBJECT_TYPE,
                'Site-wide AI Chat',
                array('message' => $message),
                array(
                    'reply'    => $reply['reply'],
                    'notes'    => $reply['notes'],
                    'provider' => $reply['provider'],
                    'model'    => $reply['model'],
                )
            );
        } catch (\Throwable $throwable) {
            wp_send_json_error(array('message' => $throwable->getMessage()), 500);
            return;
        }

        $chat_messages = $this->get_recent_messages(20);

        wp_send_json_success(array(
            'message'  => __('AI assistant replied.', 'ai-seo-keeper'),
            'chatHtml' => $this->render_chat_html($chat_messages),
        ));
    }

    public function handle_clear_chat(): void
    {
        check_ajax_referer('ai_seo_keeper_site_chat', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-seo-keeper')), 403);
        }

        $this->clear_messages();

        wp_send_json_success(array(
            'message'  => __('Chat history cleared.', 'ai-seo-keeper'),
            'chatHtml' => $this->render_chat_html(array()),
        ));
    }

    // ------------------------------------------------------------------
    //  AI call
    // ------------------------------------------------------------------

    private function send_to_ai(string $message, array $recent_messages, array $options): array
    {
        $provider    = (string) $options['provider'];
        $model       = trim((string) $options['model']);
        $api_key     = (string) $options['api_key'];
        $temperature = isset($options['ai_temperature']) ? (float) $options['ai_temperature'] : 0.3;

        $system_prompt = $this->build_system_prompt();
        $user_prompt   = $this->build_user_prompt($message, $recent_messages);

        if ('openai' === $provider) {
            $raw = $this->ai_generator->call_provider($provider, $api_key, $model, $system_prompt, $user_prompt, $temperature);
        } elseif ('google' === $provider) {
            $raw = $this->ai_generator->call_provider($provider, $api_key, $model, $system_prompt, $user_prompt, $temperature);
        } else {
            throw new \RuntimeException('Unsupported AI provider configured.');
        }

        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            // The response might be plain text (not JSON) — wrap it.
            $payload = array('reply' => $raw, 'notes' => '');
        }

        $reply = isset($payload['reply']) ? sanitize_textarea_field((string) $payload['reply']) : '';
        $notes = isset($payload['notes']) ? sanitize_textarea_field((string) $payload['notes']) : '';

        if ('' === $reply) {
            throw new \RuntimeException('The AI assistant did not return a usable reply.');
        }

        return array(
            'reply'    => $reply,
            'notes'    => $notes,
            'provider' => $provider,
            'model'    => $model,
        );
    }

    // ------------------------------------------------------------------
    //  Prompt builders
    // ------------------------------------------------------------------

    private function build_system_prompt(): string
    {
        return trim(
            'IDENTITY: You are the AI inside the "AI SEO Keeper" WordPress plugin. ' .
            'You are in SITE-WIDE CHAT mode — the user is asking about overall site SEO, not a specific page. ' .
            'Never mention Yoast, RankMath, or any other SEO plugin.' . "\n\n" .
            'Return only valid JSON with exactly these keys: reply, notes.' . "\n" .
            'reply should be a clear, actionable, and comprehensive answer using Markdown formatting (headings, lists, bold).' . "\n" .
            'notes should be a one-sentence internal note about the analysis approach.' . "\n\n" .
            'YOU HAVE FULL KNOWLEDGE of: the complete site tree with every page and its focus keyphrase, ' .
            'audit summary scores, duplicate title issues, orphaned content, thin content pages, ' .
            'keyphrase cannibalization, sitemap configuration, redirect/404 stats, and image usage. ' .
            'Use ALL of this data to answer the user.' . "\n\n" .
            'RESPONSE RULES:' . "\n" .
            '1. Always reference specific pages by title and URL when discussing issues.' . "\n" .
            '2. Prioritize actionable fixes over general advice.' . "\n" .
            '3. When discussing site structure, reference the actual hierarchy tree you see.' . "\n" .
            '4. Flag keyphrase cannibalization — pages competing for the same keyphrase.' . "\n" .
            '5. Suggest internal linking opportunities based on the site tree.' . "\n" .
            '6. When asked to improve the site, provide a numbered priority list of specific fixes.' . "\n" .
            '7. Do not invent pages, URLs, or data that are not in the context below.'
        );
    }

    private function build_user_prompt(string $message, array $recent_messages): string
    {
        $parts = array();

        $parts[] = 'Task: Answer the site owner as a site-wide SEO strategist.';
        $parts[] = 'Output format: {"reply":"...","notes":"..."}';
        $parts[] = 'Site: ' . get_bloginfo('name') . ' (' . home_url('/') . ')';

        // --- Audit summary ---
        $summary = $this->content_indexer->get_audit_summary();
        $parts[] = 'Audit summary: ' . wp_json_encode($summary);

        // --- Readiness / scores ---
        $report = $this->audit_engine->get_report(10);
        if (! empty($report['readiness'])) {
            $parts[] = 'Readiness score: ' . (int) $report['readiness']['score'] . '/100 (' . $report['readiness']['label'] . ')' .
                ' | Draft coverage: ' . (int) $report['readiness']['draft_coverage'] . '%' .
                ' | Approval coverage: ' . (int) $report['readiness']['approval_coverage'] . '%' .
                ' | Frontend coverage: ' . (int) $report['readiness']['frontend_coverage'] . '%';
        }

        // --- Priority rows (pages needing attention) ---
        if (! empty($report['priority_rows'])) {
            $priority_lines = array();
            foreach ($report['priority_rows'] as $row) {
                $priority_lines[] = sprintf(
                    '- "%s" (%s) | title draft: %s | desc draft: %s | approved: %s | frontend: %s',
                    $row['title'] ?? '(untitled)',
                    $row['permalink'] ?? '',
                    ! empty($row['has_title_draft']) ? 'yes' : 'NO',
                    ! empty($row['has_description_draft']) ? 'yes' : 'NO',
                    ! empty($row['has_approved_suggestion']) ? 'yes' : 'no',
                    ! empty($row['frontend_ready']) ? 'yes' : 'no'
                );
            }
            $parts[] = "Priority pages needing SEO work:\n" . implode("\n", $priority_lines);
        }

        // --- Duplicate titles ---
        if (! empty($report['duplicate_post_titles'])) {
            $dup_lines = array();
            foreach ($report['duplicate_post_titles'] as $group) {
                $entries_list = array_map(function ($e) {
                    return '"' . $e['title'] . '" (' . $e['permalink'] . ')';
                }, $group['entries'] ?? array());
                $dup_lines[] = '- ' . implode(' vs ', $entries_list);
            }
            $parts[] = "Duplicate page titles (SEO conflict):\n" . implode("\n", $dup_lines);
        }

        // --- Thin content ---
        if (! empty($report['thin_content_rows'])) {
            $thin_lines = array();
            foreach ($report['thin_content_rows'] as $row) {
                $thin_lines[] = sprintf('- "%s" (%s) — %d words', $row['title'] ?? '', $row['permalink'] ?? '', $row['word_count'] ?? 0);
            }
            $parts[] = "Thin content pages (< 120 words):\n" . implode("\n", $thin_lines);
        }

        // --- Orphaned content ---
        if (! empty($report['orphaned_content'])) {
            $orphan_data = $report['orphaned_content'];
            if (! empty($orphan_data['orphans'])) {
                $orphan_lines = array();
                foreach ($orphan_data['orphans'] as $orphan) {
                    $orphan_lines[] = sprintf('- "%s" (%s)', $orphan['title'] ?? '', $orphan['permalink'] ?? '');
                }
                $parts[] = 'Orphaned pages (no internal links pointing to them): ' . ($orphan_data['total_orphans'] ?? 0) . " total\n" . implode("\n", $orphan_lines);
            }
        }

        // --- Site tree with keyphrases ---
        $site_tree = $this->content_indexer->get_compact_site_tree(0);
        if ('' !== $site_tree && 'No published pages found.' !== $site_tree) {
            $parts[] = "Complete site structure (slug, title, focus keyphrase):\n" . $site_tree;
        }

        // --- Per-page audit scores ---
        $page_scores = $this->get_all_page_audit_scores();
        if (! empty($page_scores)) {
            $score_lines = array();
            foreach ($page_scores as $ps) {
                $score_lines[] = sprintf(
                    '- "%s" (%s) — score: %d/100 | issues: %d',
                    $ps['title'],
                    $ps['permalink'],
                    $ps['score'],
                    $ps['issue_count']
                );
            }
            $parts[] = "Page audit scores (all audited pages):\n" . implode("\n", $score_lines);
        }

        // --- Keyphrase distribution / conflicts ---
        $keyphrase_map = $this->get_keyphrase_distribution();
        if (! empty($keyphrase_map)) {
            $kp_lines = array();
            foreach ($keyphrase_map as $kp => $pages) {
                if (count($pages) > 1) {
                    $kp_lines[] = '- "' . $kp . '" → CONFLICT: ' . implode(', ', array_map(function ($p) {
                        return '"' . $p['title'] . '"';
                    }, $pages));
                }
            }
            if (! empty($kp_lines)) {
                $parts[] = "Keyphrase cannibalization (multiple pages targeting same keyphrase):\n" . implode("\n", $kp_lines);
            }
        }

        // --- Image stats ---
        $image_stats = $this->get_image_stats();
        if (! empty($image_stats)) {
            $parts[] = sprintf(
                'Image usage: %d total images across all pages | %d missing alt text (%d%%)',
                $image_stats['total'],
                $image_stats['missing_alt'],
                $image_stats['total'] > 0 ? (int) round($image_stats['missing_alt'] / $image_stats['total'] * 100) : 0
            );
        }

        // --- Sitemap status ---
        $sitemap_info = $this->get_sitemap_summary();
        $parts[] = 'Sitemap: ' . $sitemap_info;

        // --- Redirect/404 stats ---
        $redirect_stats = $this->get_redirect_stats();
        if (! empty($redirect_stats)) {
            $parts[] = sprintf(
                'Redirects & 404s: %d active redirects | %d monitored 404s | top 404: %s',
                $redirect_stats['redirects'],
                $redirect_stats['errors_404'],
                $redirect_stats['top_404']
            );
        }

        // --- Conversation history ---
        if (! empty($recent_messages)) {
            $conv_lines = array();
            foreach ($recent_messages as $msg) {
                if (! is_array($msg)) {
                    continue;
                }
                $role    = isset($msg['role']) ? (string) $msg['role'] : '';
                $content = 'user' === $role
                    ? (string) ($msg['message'] ?? '')
                    : (string) ($msg['reply'] ?? '');
                if ('' !== trim($content)) {
                    $conv_lines[] = strtoupper($role) . ': ' . $content;
                }
            }
            if (! empty($conv_lines)) {
                $parts[] = "Recent conversation:\n" . implode("\n", $conv_lines);
            }
        }

        $parts[] = 'User question: ' . $message;

        return implode("\n\n", $parts);
    }

    // ------------------------------------------------------------------
    //  Data aggregation helpers
    // ------------------------------------------------------------------

    /**
     * Get audit scores for all pages that have been audited.
     */
    private function get_all_page_audit_scores(): array
    {
        global $wpdb;

        $table    = $wpdb->prefix . 'ai_seo_keeper_content_index';
        $postmeta = $wpdb->postmeta;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT idx.object_id, idx.title, idx.permalink,
                        pm_audit.meta_value AS audit_data
                 FROM {$table} idx
                 INNER JOIN {$postmeta} pm_audit
                    ON pm_audit.post_id = idx.object_id
                   AND pm_audit.meta_key = '_ai_seo_keeper_page_audit'
                 WHERE idx.object_type = %s AND idx.status = %s
                 ORDER BY idx.title ASC",
                'post',
                'publish'
            ),
            ARRAY_A
        );

        if (! is_array($rows)) {
            return array();
        }

        $scores = array();
        foreach ($rows as $row) {
            $audit = maybe_unserialize($row['audit_data']);
            if (! is_array($audit) || ! isset($audit['score'])) {
                continue;
            }
            $scores[] = array(
                'title'       => (string) $row['title'],
                'permalink'   => (string) $row['permalink'],
                'score'       => (int) $audit['score'],
                'issue_count' => isset($audit['issues']) && is_array($audit['issues']) ? count($audit['issues']) : 0,
            );
        }

        // Sort by score ascending (worst first).
        usort($scores, function ($a, $b) {
            return $a['score'] - $b['score'];
        });

        return $scores;
    }

    /**
     * Get a map of focus keyphrases to the pages targeting them (for cannibalization detection).
     */
    private function get_keyphrase_distribution(): array
    {
        global $wpdb;

        $table    = $wpdb->prefix . 'ai_seo_keeper_content_index';
        $postmeta = $wpdb->postmeta;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT idx.object_id, idx.title, idx.slug,
                        LOWER(TRIM(pm_kp.meta_value)) AS focus_keyphrase
                 FROM {$table} idx
                 INNER JOIN {$postmeta} pm_kp
                    ON pm_kp.post_id = idx.object_id
                   AND pm_kp.meta_key = '_ai_seo_keeper_focus_keyphrase'
                 WHERE idx.object_type = %s
                   AND idx.status = %s
                   AND TRIM(COALESCE(pm_kp.meta_value, '')) != ''
                 ORDER BY idx.title ASC",
                'post',
                'publish'
            ),
            ARRAY_A
        );

        if (! is_array($rows)) {
            return array();
        }

        $map = array();
        foreach ($rows as $row) {
            $kp = (string) $row['focus_keyphrase'];
            if ('' === $kp) {
                continue;
            }
            $map[$kp][] = array(
                'object_id' => (int) $row['object_id'],
                'title'     => (string) $row['title'],
                'slug'      => (string) $row['slug'],
            );
        }

        return $map;
    }

    /**
     * Get aggregate image stats across all published content.
     */
    private function get_image_stats(): array
    {
        global $wpdb;

        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_content FROM {$wpdb->posts}
                 WHERE post_status = %s
                   AND post_type IN ('post', 'page')
                 ORDER BY ID ASC",
                'publish'
            ),
            ARRAY_A
        );

        if (! is_array($posts) || empty($posts)) {
            return array('total' => 0, 'missing_alt' => 0);
        }

        $total       = 0;
        $missing_alt = 0;

        foreach ($posts as $row) {
            $content = (string) $row['post_content'];
            if ('' === $content) {
                continue;
            }
            // Count <img> tags.
            preg_match_all('/<img\b[^>]*>/i', $content, $images);
            $page_total = count($images[0]);
            $total += $page_total;

            // Count images without alt or with empty alt.
            foreach ($images[0] as $img_tag) {
                if (! preg_match('/\balt\s*=\s*["\'][^"\']+["\']/i', $img_tag)) {
                    $missing_alt++;
                }
            }
        }

        return array('total' => $total, 'missing_alt' => $missing_alt);
    }

    /**
     * Get sitemap configuration summary.
     */
    private function get_sitemap_summary(): string
    {
        $options = $this->settings->get();
        $parts   = array();

        if (empty($options['sitemap_enabled'])) {
            return 'Disabled';
        }

        $parts[] = 'Enabled';
        $types   = array();
        if (! empty($options['sitemap_include_posts'])) {
            $types[] = 'posts';
        }
        if (! empty($options['sitemap_include_pages'])) {
            $types[] = 'pages';
        }
        if (! empty($options['sitemap_include_categories'])) {
            $types[] = 'categories';
        }
        if (! empty($options['sitemap_include_tags'])) {
            $types[] = 'tags';
        }
        if (! empty($options['wc_integration_enabled'])) {
            if (! empty($options['sitemap_include_wc_products'])) {
                $types[] = 'WC products';
            }
            if (! empty($options['sitemap_include_wc_product_cat'])) {
                $types[] = 'WC categories';
            }
        }

        if (! empty($types)) {
            $parts[] = 'includes: ' . implode(', ', $types);
        }

        $parts[] = 'URL: ' . home_url('/sitemap.xml');

        return implode(' | ', $parts);
    }

    /**
     * Get redirect and 404 statistics.
     */
    private function get_redirect_stats(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_seo_keeper_redirects';

        // Check if table exists.
        $table_exists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $table)
        );

        if (! $table_exists) {
            return array();
        }

        $redirects = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE type = %s", 'redirect')
        );

        $errors_404 = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE type = %s", '404')
        );

        $top_404_url = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT source_url FROM {$table} WHERE type = %s ORDER BY hit_count DESC LIMIT 1",
                '404'
            )
        );

        return array(
            'redirects'  => $redirects,
            'errors_404' => $errors_404,
            'top_404'    => $top_404_url ? (string) $top_404_url : 'none',
        );
    }

    // ------------------------------------------------------------------
    //  Conversation storage (delegates to History_Store via site object type)
    // ------------------------------------------------------------------

    public function get_recent_messages(int $limit = 20): array
    {
        global $wpdb;

        $conversations_table = $wpdb->prefix . 'ai_seo_keeper_conversations';
        $messages_table      = $wpdb->prefix . 'ai_seo_keeper_messages';
        $limit               = max(1, min(50, $limit));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.id, m.role, m.content, m.created_at
                 FROM {$messages_table} m
                 INNER JOIN {$conversations_table} c ON c.id = m.conversation_id
                 WHERE c.object_type = %s AND c.object_id = %d
                 ORDER BY m.id DESC
                 LIMIT %d",
                self::OBJECT_TYPE,
                self::OBJECT_ID,
                $limit
            ),
            ARRAY_A
        );

        if (! is_array($rows)) {
            return array();
        }

        $rows     = array_reverse($rows);
        $messages = array();

        foreach ($rows as $row) {
            $payload = json_decode((string) $row['content'], true);
            if (! is_array($payload)) {
                continue;
            }
            $messages[] = array(
                'id'         => (int) $row['id'],
                'role'       => (string) $row['role'],
                'created_at' => (string) $row['created_at'],
                'message'    => isset($payload['message']) ? (string) $payload['message'] : '',
                'reply'      => isset($payload['reply']) ? (string) $payload['reply'] : '',
                'notes'      => isset($payload['notes']) ? (string) $payload['notes'] : '',
                'provider'   => isset($payload['provider']) ? (string) $payload['provider'] : '',
                'model'      => isset($payload['model']) ? (string) $payload['model'] : '',
            );
        }

        return $messages;
    }

    private function clear_messages(): void
    {
        global $wpdb;

        $conversations_table = $wpdb->prefix . 'ai_seo_keeper_conversations';
        $messages_table      = $wpdb->prefix . 'ai_seo_keeper_messages';

        $conversation_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$conversations_table} WHERE object_type = %s AND object_id = %d",
                self::OBJECT_TYPE,
                self::OBJECT_ID
            )
        );

        if (empty($conversation_ids)) {
            return;
        }

        $ids_in = implode(',', array_map('intval', $conversation_ids));
        $wpdb->query("DELETE FROM {$messages_table} WHERE conversation_id IN ({$ids_in})");
        $wpdb->query("DELETE FROM {$conversations_table} WHERE id IN ({$ids_in})");
    }

    // ------------------------------------------------------------------
    //  Chat HTML renderer
    // ------------------------------------------------------------------

    public function render_chat_html(array $chat_messages): string
    {
        ob_start();
        if (empty($chat_messages)) :
            ?>
            <p class="ai-seo-keeper-empty-state">No site-wide AI chat messages yet. Ask about your overall SEO performance, site structure, or keyphrase strategy.</p>
        <?php else : ?>
            <div class="ai-seo-keeper-stack">
                <?php foreach ($chat_messages as $entry) : ?>
                    <div class="ai-seo-keeper-chat-item <?php echo 'assistant' === $entry['role'] ? 'is-assistant' : ''; ?>">
                        <p style="margin:0 0 8px;"><strong><?php echo 'assistant' === $entry['role'] ? 'AI SEO Strategist' : 'You'; ?></strong></p>
                        <?php if ('assistant' === $entry['role']) : ?>
                            <div class="ai-seo-keeper-site-chat-reply"><?php echo wp_kses_post($this->render_markdown($entry['reply'])); ?></div>
                            <?php if ('' !== $entry['notes']) : ?>
                                <p style="margin:8px 0 0;"><em class="ai-seo-keeper-chat-meta"><?php echo esc_html($entry['notes']); ?></em></p>
                            <?php endif; ?>
                        <?php else : ?>
                            <p style="margin:0;"><?php echo esc_html($entry['message']); ?></p>
                        <?php endif; ?>
                        <p class="ai-seo-keeper-chat-meta" style="margin:4px 0 0;">
                            <?php if ('assistant' === $entry['role']) : ?>
                                <?php echo esc_html(strtoupper($entry['provider'])); ?>
                                <?php if ('' !== $entry['model']) : ?>
                                    | <?php echo esc_html($entry['model']); ?>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ('' !== $entry['created_at']) : ?>
                                <?php echo ('assistant' === $entry['role'] ? ' | ' : '') . esc_html($entry['created_at']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif;

        return (string) ob_get_clean();
    }

    /**
     * Minimal Markdown-to-HTML for AI replies (headings, bold, lists, line breaks).
     */
    private function render_markdown(string $text): string
    {
        $text = esc_html($text);

        // Headings: ### Heading → <strong>Heading</strong>
        $text = (string) preg_replace('/^#{1,4}\s+(.+)$/m', '<strong>$1</strong>', $text);

        // Bold: **text** → <strong>text</strong>
        $text = (string) preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);

        // Numbered lists: 1. item → <br>1. item
        $text = (string) preg_replace('/^(\d+\.\s)/m', '<br>$1', $text);

        // Unordered lists: - item → <br>• item
        $text = (string) preg_replace('/^-\s+/m', '<br>• ', $text);

        // Line breaks.
        $text = (string) str_replace("\n\n", '<br><br>', $text);
        $text = (string) str_replace("\n", '<br>', $text);

        // Clean up leading <br>.
        $text = (string) preg_replace('/^(<br\s*\/?>)+/', '', $text);

        return $text;
    }

    // ------------------------------------------------------------------
    //  Admin page data (for the view)
    // ------------------------------------------------------------------

    /**
     * Get a summary array for the admin page header cards.
     */
    public function get_dashboard_data(): array
    {
        $summary    = $this->content_indexer->get_audit_summary();
        $report     = $this->audit_engine->get_report(5);
        $image_stats = $this->get_image_stats();
        $redirect_stats = $this->get_redirect_stats();

        return array(
            'total_pages'      => (int) ($summary['total_items'] ?? 0),
            'published_pages'  => (int) ($summary['published_items'] ?? 0),
            'readiness_score'  => (int) ($report['readiness']['score'] ?? 0),
            'readiness_label'  => (string) ($report['readiness']['label'] ?? 'Unknown'),
            'missing_titles'   => (int) ($summary['missing_title_drafts'] ?? 0),
            'missing_descs'    => (int) ($summary['missing_description_drafts'] ?? 0),
            'total_images'     => $image_stats['total'],
            'missing_alt'      => $image_stats['missing_alt'],
            'redirects'        => $redirect_stats['redirects'] ?? 0,
            'errors_404'       => $redirect_stats['errors_404'] ?? 0,
            'thin_content'     => count($report['thin_content_rows'] ?? array()),
            'orphans'          => isset($report['orphaned_content']['total_orphans']) ? (int) $report['orphaned_content']['total_orphans'] : 0,
            'duplicates'       => count($report['duplicate_post_titles'] ?? array()),
        );
    }
}
