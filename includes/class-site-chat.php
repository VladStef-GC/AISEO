<?php

namespace AI_SEO_Captain;

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
        check_ajax_referer('ai_seo_captain_site_chat', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-seo-captain')), 403);
        }

        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

        if ('' === trim($message)) {
            wp_send_json_error(array('message' => __('Enter a question before asking the AI Captain.', 'ai-seo-captain')), 400);
        }

        $options = $this->settings->get();

        if (empty($options['api_key'])) {
            wp_send_json_error(array('message' => __('Add an API key in SEO Captain Settings before using the AI Captain.', 'ai-seo-captain')), 400);
        }

        if (empty($options['editor_chat_enabled'])) {
            wp_send_json_error(array('message' => __('The AI Captain is disabled in settings.', 'ai-seo-captain')), 400);
        }

        try {
            $recent_messages = $this->get_recent_messages(8);

            // Focus pages mode — JSON array of post IDs or legacy URL/ID format.
            $focus_ids = array();
            if (! empty($_POST['focus_page_ids'])) {
                $decoded = json_decode(sanitize_text_field(wp_unslash($_POST['focus_page_ids'])), true);
                if (is_array($decoded)) {
                    $focus_ids = array_map('absint', $decoded);
                }
            } elseif (! empty($_POST['focus_pages'])) {
                $raw = sanitize_textarea_field(wp_unslash($_POST['focus_pages']));

                // Support both comma-separated IDs and newline-separated URLs.
                $items = preg_split('/[\n,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);

                foreach ($items as $item) {
                    $item = trim($item);
                    if ('' === $item) {
                        continue;
                    }

                    if (is_numeric($item)) {
                        $focus_ids[] = absint($item);
                    } elseif (filter_var($item, FILTER_VALIDATE_URL)) {
                        $post_id = url_to_postid($item);
                        if ($post_id > 0) {
                            $focus_ids[] = $post_id;
                        }
                    }
                }

                $focus_ids = array_unique(array_filter($focus_ids));
            }

            // Audit inclusion — subset of focus IDs that should include their full audit report.
            $audit_ids = array();
            if (! empty($_POST['audit_page_ids'])) {
                $decoded_audit = json_decode(sanitize_text_field(wp_unslash($_POST['audit_page_ids'])), true);
                if (is_array($decoded_audit)) {
                    $audit_ids = array_map('absint', $decoded_audit);
                    // Only allow audit IDs that are in the focus set.
                    $audit_ids = array_intersect($audit_ids, $focus_ids);
                }
            }

            $reply = $this->send_to_ai($message, $recent_messages, $options, $focus_ids, $audit_ids);

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
            'message'  => __('AI Captain replied.', 'ai-seo-captain'),
            'chatHtml' => $this->render_chat_html($chat_messages),
        ));
    }

    public function handle_clear_chat(): void
    {
        check_ajax_referer('ai_seo_captain_site_chat', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-seo-captain')), 403);
        }

        $this->clear_messages();

        wp_send_json_success(array(
            'message'  => __('Chat history cleared.', 'ai-seo-captain'),
            'chatHtml' => $this->render_chat_html(array()),
        ));
    }

    // ------------------------------------------------------------------
    //  AI call
    // ------------------------------------------------------------------

    private function send_to_ai(string $message, array $recent_messages, array $options, array $focus_ids = array(), array $audit_ids = array()): array
    {
        $provider    = (string) $options['provider'];
        $model       = trim((string) $options['model']);
        $api_key     = (string) $options['api_key'];
        $temperature = isset($options['ai_temperature']) ? (float) $options['ai_temperature'] : 0.3;

        $is_focus_mode = ! empty($focus_ids);

        // --- Page count gate: warn if selection exceeds model capacity ---
        if ($is_focus_mode) {
            // Focus mode: each page = 1 slot, each audit = 1 extra slot.
            $max_focus   = Settings::get_max_focus_pages_for_model($model);
            $total_slots = count($focus_ids) + count($audit_ids);
            if ($total_slots > $max_focus) {
                $context_window = Settings::get_context_window($model);
                throw new \RuntimeException(sprintf(
                    'You selected %s pages + %s audits (%s total slots) but the model (%s, %s-token context) supports up to %s slots. ' .
                        'Please reduce your selection, uncheck some audits, or switch to a larger model.',
                    number_format_i18n(count($focus_ids)),
                    number_format_i18n(count($audit_ids)),
                    number_format_i18n($total_slots),
                    esc_html($model),
                    number_format_i18n($context_window),
                    number_format_i18n($max_focus)
                ));
            }
        } else {
            // Site-wide mode: lightweight tree view — use tree limit.
            $max_pages      = Settings::get_max_pages_for_model($model);
            $effective_count = $this->content_indexer->get_published_page_count();
            if ($effective_count > $max_pages) {
                $context_window = Settings::get_context_window($model);
                throw new \RuntimeException(sprintf(
                    'Your site has %s pages but the selected model (%s, %s-token context) can safely analyze up to %s pages at once. ' .
                        'Options: 1) Use Skip Patterns in Settings to exclude template/utility pages. ' .
                        '2) Use Focus Pages mode to analyze a specific set of pages. ' .
                        '3) Switch to a model with a larger context window.',
                    number_format_i18n($effective_count),
                    esc_html($model),
                    number_format_i18n($context_window),
                    number_format_i18n($max_pages)
                ));
            }
        }

        $system_prompt = $this->build_system_prompt($is_focus_mode);
        $user_prompt   = $this->build_user_prompt($message, $recent_messages, $focus_ids, $audit_ids);

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
            throw new \RuntimeException('The AI Captain did not return a usable reply.');
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

    private function build_system_prompt(bool $focus_mode = false): string
    {
        $base = 'IDENTITY: You are the AI inside the "SEO Captain" WordPress plugin. ' .
            'Never mention Yoast, RankMath, or any other SEO plugin.' . "\n\n" .
            'Return only valid JSON with exactly these keys: reply, notes.' . "\n" .
            'reply should be a clear, actionable, and comprehensive answer using Markdown formatting (headings, lists, bold).' . "\n" .
            'notes should be a one-sentence internal note about the analysis approach.' . "\n\n";

        if ($focus_mode) {
            $base .= 'MODE: FOCUS PAGES — You are comparing specific pages selected by the user. ' .
                'You see the FULL body content, ALL SEO metadata, content analysis, and SEO score for each page. ' .
                'The data is real-time (fetched from the live site right now). ' .
                'Use this to compare pages side by side, find content gaps, detect keyphrase conflicts, ' .
                'suggest internal linking between these specific pages, and provide detailed improvement recommendations.' . "\n\n" .
                'RESPONSE RULES:' . "\n" .
                '1. Always reference specific pages by title and URL when discussing issues.' . "\n" .
                '2. Compare pages side by side — identify which page does something well and which needs improvement.' . "\n" .
                '3. Flag keyphrase cannibalization — pages competing for the same keyphrase.' . "\n" .
                '4. Suggest internal linking opportunities between the selected pages.' . "\n" .
                '5. When asked to improve, provide a numbered priority list of specific fixes per page.' . "\n" .
                '6. You see the full body content — use it to assess content quality, keyword usage, and topical coverage.' . "\n" .
                '7. Do not invent data that is not in the context below.' . "\n" .
                '8. "SEO Score" is the AI audit score (0-100) from the last audit run. If "Not audited yet", recommend running an audit.';
        } else {
            $base .= 'MODE: SITE-WIDE — the user is asking about overall site SEO, not specific pages. ' .
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
                '7. Do not invent pages, URLs, or data that are not in the context below.' . "\n" .
                '8. When explaining scores or formulas, use ONLY the exact numbers, weights, and ranges provided in the data — never guess or approximate them.' . "\n" .
                '9. Pages marked [template] are UI fragments (headers, footers, popups) — do NOT recommend adding SEO content to them.' . "\n\n" .
                'GLOSSARY:' . "\n" .
                '- "approved" = the site owner has manually reviewed and accepted the AI-generated SEO title/description.' . "\n" .
                '- "frontend" = the SEO metadata is actively served on the live site (visible to search engines).' . "\n" .
                '- "draft coverage" = percentage of pages that have an AI-generated title + description draft.' . "\n" .
                '- "approval coverage" = percentage of pages whose drafts have been approved by the owner.' . "\n" .
                '- "frontend coverage" = percentage of pages whose approved SEO data is live on the frontend.';
        }

        return trim($base);
    }

    private function build_user_prompt(string $message, array $recent_messages, array $focus_ids = array(), array $audit_ids = array()): string
    {
        $parts = array();

        $parts[] = 'Task: Answer the site owner as a site-wide SEO captain.';
        $parts[] = 'Output format: {"reply":"...","notes":"..."}';
        $parts[] = 'Site: ' . get_bloginfo('name') . ' (' . home_url('/') . ')';
        $parts[] = 'Data collected: ' . wp_date('Y-m-d H:i') . ' (server time)';

        // --- Owner-provided site context ---
        $site_context = trim((string) ($this->settings->get()['site_chat_context'] ?? ''));
        if ('' !== $site_context) {
            $parts[] = "Site owner's description of the business and goals:\n" . $site_context;
        }

        // =====================================================================
        //  FOCUS PAGES MODE — full body content + all SEO data per page
        // =====================================================================
        if (! empty($focus_ids)) {
            $audit_label = ! empty($audit_ids) ? ' (' . count($audit_ids) . ' with full audit reports)' : '';
            $parts[] = 'MODE: Focus Pages — You are analyzing ' . count($focus_ids) . ' selected pages' . $audit_label . ' with their FULL content and ALL SEO metadata. Compare them side by side.';

            $focus_pages_data = $this->get_focus_pages_full_data($focus_ids);

            foreach ($focus_pages_data as $i => $page) {
                $page_block = array();
                $page_block[] = '========== PAGE ' . ($i + 1) . ' of ' . count($focus_pages_data) . ' ==========';
                $page_block[] = 'Title: ' . $page['title'];
                $page_block[] = 'URL: ' . $page['permalink'];
                $page_block[] = 'Post type: ' . $page['post_type'];
                $page_block[] = 'Status: ' . $page['status'];

                // Site tree position.
                if ('' !== $page['hierarchy_position']) {
                    $page_block[] = 'Site tree position: ' . $page['hierarchy_position'];
                }
                if ('' !== $page['parent_title']) {
                    $page_block[] = 'Parent page: "' . $page['parent_title'] . '" (' . $page['parent_url'] . ')';
                }

                // Dates and meta.
                $page_block[] = 'Published: ' . $page['publish_date'];
                if ($page['modified_date'] !== $page['publish_date']) {
                    $page_block[] = 'Last modified: ' . $page['modified_date'];
                }
                $page_block[] = 'Featured image: ' . ($page['has_featured_image'] ? 'Yes' : 'None');

                // Taxonomy terms.
                if (! empty($page['taxonomy_terms'])) {
                    foreach ($page['taxonomy_terms'] as $tax_label => $term_list) {
                        $page_block[] = $tax_label . ': ' . $term_list;
                    }
                }

                // SEO metadata.
                $page_block[] = '--- SEO Metadata ---';
                $page_block[] = 'Focus keyphrase: ' . ('' !== $page['focus_keyphrase'] ? $page['focus_keyphrase'] : 'None specified');
                $page_block[] = 'SEO title draft: ' . ('' !== $page['seo_title'] ? $page['seo_title'] . ' (' . $page['title_length'] . ' chars)' : 'Empty — not yet written');
                $page_block[] = 'Meta description draft: ' . ('' !== $page['meta_description'] ? $page['meta_description'] . ' (' . $page['desc_length'] . ' chars)' : 'Empty — not yet written');
                if ('' !== ($page['keywords'] ?? '')) {
                    $page_block[] = 'Keywords: ' . $page['keywords'];
                }
                $page_block[] = 'Keyphrase in title: ' . ($page['keyphrase_in_title'] ? 'Found' : 'Missing');
                $page_block[] = 'Keyphrase in description: ' . ($page['keyphrase_in_desc'] ? 'Found' : 'Missing');

                if ('' !== $page['social_title']) {
                    $page_block[] = 'Social title: ' . $page['social_title'];
                }
                if ('' !== $page['social_description']) {
                    $page_block[] = 'Social description: ' . $page['social_description'];
                }
                if ('' !== $page['schema_type']) {
                    $page_block[] = 'Schema type: ' . $page['schema_type'];
                }
                if ('' !== $page['canonical_url']) {
                    $page_block[] = 'Canonical URL: ' . $page['canonical_url'];
                }
                if ('' !== $page['robots_directives']) {
                    $page_block[] = 'Robots directives: ' . $page['robots_directives'];
                }
                if ($page['is_cornerstone']) {
                    $page_block[] = 'Cornerstone content: Yes';
                }

                // SEO audit score.
                if (null !== $page['audit_score']) {
                    $page_block[] = 'SEO Score: ' . $page['audit_score'] . '/100';
                } else {
                    $page_block[] = 'SEO Score: Not audited yet';
                }

                // WooCommerce data.
                if (! empty($page['wc_data'])) {
                    $wc = $page['wc_data'];
                    $page_block[] = '--- WooCommerce Product ---';
                    foreach (array('wc_price' => 'Price', 'wc_sku' => 'SKU', 'wc_availability' => 'Availability', 'wc_type' => 'Type', 'wc_rating' => 'Rating') as $k => $l) {
                        if (! empty($wc[$k])) {
                            $page_block[] = $l . ': ' . $wc[$k];
                        }
                    }
                }

                // Content stats.
                $page_block[] = '--- Content Analysis ---';
                $page_block[] = 'Word count: ' . $page['word_count'];
                $page_block[] = 'Images: ' . $page['images_total'] . ' total, ' . $page['images_missing_alt'] . ' missing alt text';
                $page_block[] = 'Videos embedded: ' . $page['video_count'];
                $page_block[] = 'Documents linked: ' . $page['doc_count'];
                $page_block[] = 'Heading structure: ' . ('' !== $page['heading_structure'] ? $page['heading_structure'] : 'No headings found');
                $page_block[] = 'Internal links: ' . $page['internal_links'] . ', External links: ' . $page['external_links'];

                // Links detail (URLs).
                if (! empty($page['internal_link_urls'])) {
                    $page_block[] = 'Internal link URLs:';
                    foreach ($page['internal_link_urls'] as $url) {
                        $page_block[] = '  → ' . $url;
                    }
                }
                if (! empty($page['external_link_urls'])) {
                    $page_block[] = 'External link URLs:';
                    foreach ($page['external_link_urls'] as $url) {
                        $page_block[] = '  → ' . $url;
                    }
                }

                // Images detail (src + alt).
                if (! empty($page['image_details'])) {
                    $page_block[] = 'Image details:';
                    foreach ($page['image_details'] as $img) {
                        $page_block[] = '  - src: ' . $img['src'] . ' | alt: ' . ('' !== $img['alt'] ? '"' . $img['alt'] . '"' : 'MISSING');
                    }
                }

                // Full body content.
                $page_block[] = '--- Full Page Content ---';
                $page_block[] = ('' !== $page['body_content'] ? $page['body_content'] : 'No body content available.');

                // Full audit report (only for pages the user opted in).
                if (in_array((int) $page['post_id'], $audit_ids, true)) {
                    $audit_meta = get_post_meta((int) $page['post_id'], '_ai_seo_captain_page_audit', true);
                    if (is_array($audit_meta) && ! empty($audit_meta['full_report'])) {
                        $page_block[] = '--- Full SEO Audit Report ---';
                        $page_block[] = $audit_meta['full_report'];
                    } else {
                        $page_block[] = '--- Full SEO Audit Report ---';
                        $page_block[] = 'No audit report available for this page. The page has not been audited yet.';
                    }
                }

                $parts[] = implode("\n", $page_block);
            }

            // Cross-page keyphrase conflicts among selected pages.
            $kp_map = array();
            foreach ($focus_pages_data as $page) {
                $kp = strtolower(trim($page['focus_keyphrase']));
                if ('' !== $kp) {
                    $kp_map[$kp][] = '"' . $page['title'] . '"';
                }
            }
            $conflicts = array();
            foreach ($kp_map as $kp => $titles) {
                if (count($titles) > 1) {
                    $conflicts[] = '- "' . $kp . '" → CONFLICT: ' . implode(', ', $titles);
                }
            }
            if (! empty($conflicts)) {
                $parts[] = "KEYPHRASE CANNIBALIZATION among selected pages:\n" . implode("\n", $conflicts);
            }
        } else {
            // ==============================================================
            //  FULL SITE MODE — lightweight tree + aggregates (no body content)
            // ==============================================================

            // Audit summary.
            $summary = $this->content_indexer->get_audit_summary();
            $parts[] = 'Audit summary: ' . wp_json_encode($summary);

            // Readiness / scores.
            $report = $this->audit_engine->get_report(500);
            if (! empty($report['readiness'])) {
                $parts[] = 'Readiness score: ' . (int) $report['readiness']['score'] . '/100 (' . $report['readiness']['label'] . ')'
                    . ' | Formula: (draft_coverage × 50%) + (approval_coverage × 30%) + (frontend_coverage × 20%)'
                    . ' | Label ranges: Starting 0-29, Early 30-54, Building 55-79, Strong 80-100'
                    . ' | Draft coverage: ' . (int) $report['readiness']['draft_coverage'] . '% (weight 50%)'
                    . ' | Approval coverage: ' . (int) $report['readiness']['approval_coverage'] . '% (weight 30%)'
                    . ' | Frontend coverage: ' . (int) $report['readiness']['frontend_coverage'] . '% (weight 20%)';
            }

            // Priority rows.
            if (! empty($report['priority_rows'])) {
                $priority_lines = array();
                foreach ($report['priority_rows'] as $row) {
                    $permalink = (string) ($row['permalink'] ?? '');
                    $tag       = $this->is_template_page($permalink) ? ' [template]' : '';
                    $priority_lines[] = sprintf(
                        '- "%s"%s (%s) | title draft: %s | desc draft: %s | approved: %s | frontend: %s',
                        $row['title'] ?? '(untitled)',
                        $tag,
                        $permalink,
                        ! empty($row['has_title_draft']) ? 'yes' : 'NO',
                        ! empty($row['has_description_draft']) ? 'yes' : 'NO',
                        ! empty($row['has_approved_suggestion']) ? 'yes' : 'no',
                        ! empty($row['frontend_ready']) ? 'yes' : 'no'
                    );
                }
                $parts[] = "Priority pages needing SEO work:\n" . implode("\n", $priority_lines);
            }

            // Duplicate titles.
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

            // Thin content.
            if (! empty($report['thin_content_rows'])) {
                $thin_lines = array();
                foreach ($report['thin_content_rows'] as $row) {
                    $permalink = (string) ($row['permalink'] ?? '');
                    $tag       = $this->is_template_page($permalink) ? ' [template]' : '';
                    $thin_lines[] = sprintf('- "%s"%s (%s) — %d words', $row['title'] ?? '', $tag, $permalink, $row['word_count'] ?? 0);
                }
                $parts[] = "Thin content pages (< 120 words):\n" . implode("\n", $thin_lines);
            }

            // Orphaned content.
            $orphan_data = $this->audit_engine->get_orphaned_content(200);
            if (! empty($orphan_data['orphans'])) {
                $orphan_lines = array();
                foreach ($orphan_data['orphans'] as $orphan) {
                    $permalink = (string) ($orphan['permalink'] ?? '');
                    $tag       = $this->is_template_page($permalink) ? ' [template]' : '';
                    $orphan_lines[] = sprintf('- "%s"%s (%s)', $orphan['title'] ?? '', $tag, $permalink);
                }
                $parts[] = 'Orphaned pages (no internal links pointing to them): ' . ($orphan_data['total_orphans'] ?? 0) . " total\n" . implode("\n", $orphan_lines);
            }

            // Site tree with keyphrases.
            $site_tree = $this->content_indexer->get_compact_site_tree(0);
            if ('' !== $site_tree && 'No published pages found.' !== $site_tree) {
                $parts[] = "Complete site structure (slug, title, focus keyphrase):\n" . $site_tree;
            }

            // Per-page audit scores.
            $page_scores = $this->get_all_page_audit_scores();
            if (! empty($page_scores)) {
                $score_lines = array();
                foreach ($page_scores as $ps) {
                    $tag = $this->is_template_page($ps['permalink']) ? ' [template]' : '';
                    $score_lines[] = sprintf(
                        '- "%s"%s (%s) — score: %d/100 | issues: %d',
                        $ps['title'],
                        $tag,
                        $ps['permalink'],
                        $ps['score'],
                        $ps['issue_count']
                    );
                }
                $parts[] = "Page audit scores (all audited pages):\n" . implode("\n", $score_lines);
            }

            // Keyphrase cannibalization.
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

            // Image stats.
            $image_stats = $this->get_image_stats();
            if (! empty($image_stats)) {
                $parts[] = sprintf(
                    'Image usage: %d total images | %d missing alt text (%d%%)',
                    $image_stats['total'],
                    $image_stats['missing_alt'],
                    $image_stats['total'] > 0 ? (int) round($image_stats['missing_alt'] / $image_stats['total'] * 100) : 0
                );
            }

            // Sitemap status.
            $sitemap_info = $this->get_sitemap_summary();
            $parts[] = 'Sitemap: ' . $sitemap_info;

            // Redirect/404 stats.
            $redirect_stats = $this->get_redirect_stats();
            if (! empty($redirect_stats)) {
                $parts[] = sprintf(
                    'Redirects & 404s: %d active redirects | %d monitored 404s | top 404: %s',
                    $redirect_stats['redirects'],
                    $redirect_stats['errors_404'],
                    $redirect_stats['top_404']
                );
            }
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
     * Gather FULL data for each focus page: body content, all SEO metadata,
     * content analysis (links, images, headings), hierarchy position, and audit score.
     *
     * This is the core data provider for Focus Pages chat mode.
     *
     * @param int[] $focus_ids Post IDs to gather data for.
     * @return array[] One entry per page with all fields populated.
     */
    private function get_focus_pages_full_data(array $focus_ids): array
    {
        $pages = array();
        $home_url = home_url();

        foreach ($focus_ids as $post_id) {
            $post = get_post($post_id);
            if (! $post instanceof \WP_Post) {
                continue;
            }

            // --- Body content (raw HTML for analysis, plain text for AI) ---
            $raw_html     = Content_Helper::get_content($post);
            $body_content = $this->normalize_text($raw_html);

            // --- Content stats from raw HTML ---
            $word_count     = str_word_count($body_content);
            $img_count      = preg_match_all('/<img\b/i', $raw_html);
            $img_no_alt     = preg_match_all('/<img(?![^>]*\balt\s*=\s*"[^"]+")[^>]*>/i', $raw_html);
            $internal_links = preg_match_all('/href=["\']' . preg_quote($home_url, '/') . '/i', $raw_html);
            $ext_total      = preg_match_all('/href=["\'](https?:\/\/)/i', $raw_html);
            $external_links = max(0, $ext_total - $internal_links);
            $video_count    = preg_match_all('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/|vimeo\.com\/(?:video\/)?\d)/i', $raw_html);
            $video_count   += preg_match_all('/<video\b/i', $raw_html);
            $doc_count      = preg_match_all('/href=["\'][^"\']*\.(?:pdf|docx?|xlsx?|pptx?|odt|ods|odp|csv|rtf)["\s>]/i', $raw_html);

            // Heading structure.
            $heading_matches = array();
            preg_match_all('/<h([1-6])\b/i', $raw_html, $heading_matches);
            $heading_summary = '';
            if (! empty($heading_matches[1])) {
                $counts = array_count_values($heading_matches[1]);
                ksort($counts);
                $parts_h = array();
                foreach ($counts as $level => $count) {
                    $parts_h[] = 'H' . $level . ': ' . $count;
                }
                $heading_summary = implode(', ', $parts_h);
            }

            // Internal link URLs (deduplicated, max 50).
            $internal_link_urls = array();
            if (preg_match_all('/href=["\'](' . preg_quote($home_url, '/') . '[^"\']*)/i', $raw_html, $int_m)) {
                $internal_link_urls = array_unique(array_slice($int_m[1], 0, 50));
            }

            // External link URLs (deduplicated, max 30).
            $external_link_urls = array();
            if (preg_match_all('/href=["\'](https?:\/\/[^"\']+)/i', $raw_html, $ext_m)) {
                $all_urls = array_unique($ext_m[1]);
                foreach ($all_urls as $url) {
                    if (0 !== strpos($url, $home_url)) {
                        $external_link_urls[] = $url;
                        if (count($external_link_urls) >= 30) {
                            break;
                        }
                    }
                }
            }

            // Image details (src + alt, max 30).
            $image_details = array();
            if (preg_match_all('/<img\b([^>]*)>/i', $raw_html, $img_m)) {
                foreach (array_slice($img_m[1], 0, 30) as $attrs) {
                    $src = '';
                    $alt = '';
                    if (preg_match('/\bsrc\s*=\s*["\']([^"\']+)/i', $attrs, $sm)) {
                        $src = $sm[1];
                    }
                    if (preg_match('/\balt\s*=\s*["\']([^"\']*)/i', $attrs, $am)) {
                        $alt = $am[1];
                    }
                    $image_details[] = array('src' => $src, 'alt' => $alt);
                }
            }

            // --- SEO metadata from postmeta ---
            $seo_title        = trim((string) get_post_meta($post_id, '_ai_seo_captain_meta_title', true));
            $meta_description = trim((string) get_post_meta($post_id, '_ai_seo_captain_meta_description', true));
            $focus_keyphrase  = trim((string) get_post_meta($post_id, '_ai_seo_captain_focus_keyphrase', true));
            $keywords         = trim((string) get_post_meta($post_id, '_ai_seo_captain_keywords', true));
            $social_title     = trim((string) get_post_meta($post_id, '_ai_seo_captain_social_title', true));
            $social_desc      = trim((string) get_post_meta($post_id, '_ai_seo_captain_social_description', true));
            $schema_type      = trim((string) get_post_meta($post_id, '_ai_seo_captain_schema_type', true));
            $canonical_url    = trim((string) get_post_meta($post_id, '_ai_seo_captain_canonical_url', true));
            $robots           = trim((string) get_post_meta($post_id, '_ai_seo_captain_robots_directives', true));
            $is_cornerstone   = (bool) get_post_meta($post_id, '_ai_seo_captain_cornerstone', true);

            $title_length = function_exists('mb_strlen') ? mb_strlen($seo_title) : strlen($seo_title);
            $desc_length  = function_exists('mb_strlen') ? mb_strlen($meta_description) : strlen($meta_description);

            $kp_lower = strtolower($focus_keyphrase);
            $kp_in_title = '' !== $kp_lower && false !== strpos(strtolower($seo_title), $kp_lower);
            $kp_in_desc  = '' !== $kp_lower && false !== strpos(strtolower($meta_description), $kp_lower);

            // --- Audit score (just the number) ---
            $audit_data  = get_post_meta($post_id, '_ai_seo_captain_page_audit', true);
            $audit_score = null;
            if (is_array($audit_data) && isset($audit_data['score'])) {
                $audit_score = (int) $audit_data['score'];
            } elseif (is_string($audit_data)) {
                $decoded = maybe_unserialize($audit_data);
                if (is_array($decoded) && isset($decoded['score'])) {
                    $audit_score = (int) $decoded['score'];
                }
            }

            // --- Hierarchy position (parent chain, no siblings) ---
            $hierarchy_position = '';
            $parent_title       = '';
            $parent_url         = '';
            if ($post->post_parent > 0) {
                $parent = get_post($post->post_parent);
                if ($parent) {
                    $parent_title = (string) $parent->post_title;
                    $parent_url   = (string) get_permalink($parent);

                    // Build breadcrumb chain.
                    $chain   = array($parent_title);
                    $current = $parent;
                    while ($current->post_parent > 0) {
                        $current = get_post($current->post_parent);
                        if ($current) {
                            array_unshift($chain, (string) $current->post_title);
                        } else {
                            break;
                        }
                    }
                    $hierarchy_position = implode(' → ', $chain) . ' → ' . $post->post_title;
                }
            }

            // --- Taxonomy terms ---
            $taxonomy_terms = array();
            $taxonomies     = get_object_taxonomies($post->post_type, 'objects');
            foreach ($taxonomies as $tax) {
                if (! $tax->public) {
                    continue;
                }
                $terms = get_the_terms($post, $tax->name);
                if (! empty($terms) && ! is_wp_error($terms)) {
                    $taxonomy_terms[ucfirst($tax->label)] = implode(', ', wp_list_pluck($terms, 'name'));
                }
            }

            // --- WooCommerce data ---
            $wc_data = array();
            if ('product' === $post->post_type && function_exists('wc_get_product')) {
                $wc_data = (array) apply_filters('ai_seo_captain_product_context', array(), $post);
            }

            // --- Dates ---
            $publish_date  = (string) $post->post_date;
            $modified_date = (string) $post->post_modified;

            // --- Featured image ---
            $has_featured_image = has_post_thumbnail($post_id);

            $pages[] = array(
                'post_id'             => $post_id,
                'title'               => (string) $post->post_title,
                'permalink'           => (string) get_permalink($post),
                'post_type'           => (string) $post->post_type,
                'status'              => (string) $post->post_status,
                'body_content'        => $body_content,
                'word_count'          => $word_count,
                'images_total'        => $img_count,
                'images_missing_alt'  => $img_no_alt,
                'internal_links'      => $internal_links,
                'external_links'      => $external_links,
                'video_count'         => $video_count,
                'doc_count'           => $doc_count,
                'heading_structure'   => $heading_summary,
                'internal_link_urls'  => $internal_link_urls,
                'external_link_urls'  => $external_link_urls,
                'image_details'       => $image_details,
                'seo_title'           => $seo_title,
                'meta_description'    => $meta_description,
                'focus_keyphrase'     => $focus_keyphrase,
                'keywords'            => $keywords,
                'title_length'        => $title_length,
                'desc_length'         => $desc_length,
                'keyphrase_in_title'  => $kp_in_title,
                'keyphrase_in_desc'   => $kp_in_desc,
                'social_title'        => $social_title,
                'social_description'  => $social_desc,
                'schema_type'         => $schema_type,
                'canonical_url'       => $canonical_url,
                'robots_directives'   => $robots,
                'is_cornerstone'      => $is_cornerstone,
                'audit_score'         => $audit_score,
                'hierarchy_position'  => $hierarchy_position,
                'parent_title'        => $parent_title,
                'parent_url'          => $parent_url,
                'taxonomy_terms'      => $taxonomy_terms,
                'wc_data'             => $wc_data,
                'publish_date'        => $publish_date,
                'modified_date'       => $modified_date,
                'has_featured_image'  => $has_featured_image,
            );
        }

        return $pages;
    }

    /**
     * Strip shortcodes and HTML tags, collapse whitespace (same as AI_Generator::normalize_text).
     */
    private function normalize_text(string $text): string
    {
        $text = strip_shortcodes($text);
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\s+/', ' ', $text) ?: $text;

        return trim($text);
    }

    /**
     * Detect if a page is a template/UI fragment (not a real landing page).
     */
    private function is_template_page(string $permalink): bool
    {
        return (bool) preg_match('/template-item|popup|header|footer/i', $permalink);
    }

    /**
     * Get audit scores for all pages that have been audited.
     */
    private function get_all_page_audit_scores(): array
    {
        global $wpdb;

        $table    = $wpdb->prefix . 'ai_seo_captain_content_index';
        $postmeta = $wpdb->postmeta;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT idx.object_id, idx.title, idx.permalink,
                        pm_audit.meta_value AS audit_data
                 FROM {$table} idx
                 INNER JOIN {$postmeta} pm_audit
                    ON pm_audit.post_id = idx.object_id
                   AND pm_audit.meta_key = '_ai_seo_captain_page_audit'
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
                'object_id'   => (int) $row['object_id'],
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

        $table    = $wpdb->prefix . 'ai_seo_captain_content_index';
        $postmeta = $wpdb->postmeta;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT idx.object_id, idx.title, idx.slug,
                        LOWER(TRIM(pm_kp.meta_value)) AS focus_keyphrase
                 FROM {$table} idx
                 INNER JOIN {$postmeta} pm_kp
                    ON pm_kp.post_id = idx.object_id
                   AND pm_kp.meta_key = '_ai_seo_captain_focus_keyphrase'
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

        $table = $wpdb->prefix . 'ai_seo_captain_redirects';

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

        $conversations_table = $wpdb->prefix . 'ai_seo_captain_conversations';
        $messages_table      = $wpdb->prefix . 'ai_seo_captain_messages';
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

        $conversations_table = $wpdb->prefix . 'ai_seo_captain_conversations';
        $messages_table      = $wpdb->prefix . 'ai_seo_captain_messages';

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

        $placeholders = implode(',', array_fill(0, count($conversation_ids), '%d'));
        $wpdb->query($wpdb->prepare("DELETE FROM {$messages_table} WHERE conversation_id IN ({$placeholders})", ...$conversation_ids));
        $wpdb->query($wpdb->prepare("DELETE FROM {$conversations_table} WHERE id IN ({$placeholders})", ...$conversation_ids));
    }

    // ------------------------------------------------------------------
    //  Chat HTML renderer
    // ------------------------------------------------------------------

    public function render_chat_html(array $chat_messages): string
    {
        ob_start();
        if (empty($chat_messages)) :
?>
            <p class="ai-seo-captain-empty-state">No site-wide AI chat messages yet. Ask about your overall SEO performance, site structure, or keyphrase strategy.</p>
        <?php else : ?>
            <div class="ai-seo-captain-stack">
                <?php foreach ($chat_messages as $entry) : ?>
                    <div class="ai-seo-captain-chat-item <?php echo 'assistant' === $entry['role'] ? 'is-assistant' : ''; ?>">
                        <p style="margin:0 0 8px;"><strong><?php echo 'assistant' === $entry['role'] ? 'AI Captain' : 'You'; ?></strong></p>
                        <?php if ('assistant' === $entry['role']) : ?>
                            <div class="ai-seo-captain-site-chat-reply"><?php echo wp_kses_post($this->render_markdown($entry['reply'])); ?></div>
                            <?php if ('' !== $entry['notes']) : ?>
                                <p style="margin:8px 0 0;"><em class="ai-seo-captain-chat-meta"><?php echo esc_html($entry['notes']); ?></em></p>
                            <?php endif; ?>
                        <?php else : ?>
                            <p style="margin:0;"><?php echo esc_html($entry['message']); ?></p>
                        <?php endif; ?>
                        <p class="ai-seo-captain-chat-meta" style="margin:4px 0 0;">
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
     * Proxy to content_indexer for published page count (used by admin controller).
     */
    public function get_published_page_count_via_indexer(): int
    {
        return $this->content_indexer->get_published_page_count();
    }

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
