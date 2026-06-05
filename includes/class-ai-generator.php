<?php

namespace AI_SEO_Captain;

class AI_Generator
{
    private Settings $settings;

    private Content_Indexer $content_indexer;

    private ?Search_Console $search_console;

    public function __construct(Settings $settings, Content_Indexer $content_indexer, ?Search_Console $search_console = null)
    {
        $this->settings = $settings;
        $this->content_indexer = $content_indexer;
        $this->search_console = $search_console;
    }

    /**
     * Resolve the effective model ID based on the active provider.
     *
     * When provider is 'local', uses local_model (stored separately by the
     * Local AI module). For cloud providers, uses model (or custom_model_id).
     */
    private function resolve_model(array $options): string
    {
        $provider = (string) ($options['provider'] ?? 'openai');

        if ('local' === $provider) {
            $local_model = trim((string) ($options['local_model'] ?? ''));
            if ('' !== $local_model) {
                return $local_model;
            }
        }

        return trim((string) ($options['model'] ?? ''));
    }

    public function generate_for_post(int $post_id, array $field_overrides = array()): array
    {
        $post = get_post($post_id);

        if (! $post instanceof \WP_Post) {
            throw new \RuntimeException('The requested page could not be loaded.');
        }

        $options = $this->settings->get();

        $provider = (string) $options['provider'];

        if ('local' !== $provider && empty($options['api_key'])) {
            throw new \RuntimeException('Add an API key in SEO Captain Settings before generating suggestions.');
        }

        $model = $this->resolve_model($options);
        $temperature = $this->get_effective_temperature($options);
        $system_prompt = $this->build_system_prompt((string) $options['system_prompt']);
        $user_prompt = $this->build_user_prompt($post, $field_overrides);

        $call_fn = function () use ($provider, $options, $model, $system_prompt, $user_prompt, $temperature) {
            if ('local' === $provider) {
                return $this->call_local($model, $system_prompt, $user_prompt, $temperature);
            } elseif ('openai' === $provider) {
                return $this->call_openai($options['api_key'], $model, $system_prompt, $user_prompt, $temperature);
            } else {
                return $this->call_google($options['api_key'], $model, $system_prompt, $user_prompt, $temperature);
            }
        };

        $payload = $this->call_ai_and_decode($call_fn, 'metadata #' . $post_id);

        $seo_title = isset($payload['seo_title']) ? sanitize_text_field((string) $payload['seo_title']) : '';
        $meta_description = isset($payload['meta_description']) ? sanitize_textarea_field((string) $payload['meta_description']) : '';
        $focus_keyphrase = isset($payload['focus_keyphrase']) ? sanitize_text_field((string) $payload['focus_keyphrase']) : '';
        $keywords = isset($payload['keywords']) ? sanitize_text_field((string) $payload['keywords']) : '';
        $social_title = isset($payload['social_title']) ? sanitize_text_field((string) $payload['social_title']) : '';
        $social_description = isset($payload['social_description']) ? sanitize_textarea_field((string) $payload['social_description']) : '';
        $notes = isset($payload['notes']) ? sanitize_textarea_field((string) $payload['notes']) : '';

        if ('' === $seo_title || '' === $meta_description) {
            throw new \RuntimeException('The AI response did not include a usable SEO title and meta description.');
        }

        return array(
            'seo_title' => $seo_title,
            'meta_description' => $meta_description,
            'focus_keyphrase' => $focus_keyphrase,
            'keywords' => $keywords,
            'social_title' => $social_title,
            'social_description' => $social_description,
            'notes' => $notes,
            'provider' => $provider,
            'model' => $model,
            'system_prompt' => $system_prompt,
            'user_prompt' => $user_prompt,
        );
    }

    public function generate_site_audit(array $report): array
    {
        $options = $this->settings->get();

        $provider = (string) $options['provider'];

        if ('local' !== $provider && empty($options['api_key'])) {
            throw new \RuntimeException('Add an API key in SEO Captain Settings before generating AI site audits.');
        }

        $model = $this->resolve_model($options);
        $temperature = $this->get_effective_temperature($options);
        $system_prompt = $this->build_site_audit_system_prompt((string) $options['system_prompt']);
        $user_prompt = $this->build_site_audit_user_prompt($report);

        $call_fn = function () use ($provider, $options, $model, $system_prompt, $user_prompt, $temperature) {
            if ('local' === $provider) {
                return $this->call_local($model, $system_prompt, $user_prompt, $temperature);
            } elseif ('openai' === $provider) {
                return $this->call_openai($options['api_key'], $model, $system_prompt, $user_prompt, $temperature);
            } else {
                return $this->call_google($options['api_key'], $model, $system_prompt, $user_prompt, $temperature);
            }
        };

        $payload = $this->call_ai_and_decode($call_fn, 'site audit');
        $audit_title = isset($payload['audit_title']) ? sanitize_text_field((string) $payload['audit_title']) : 'SEO Captain Site Audit';
        $executive_summary = isset($payload['executive_summary']) ? sanitize_textarea_field((string) $payload['executive_summary']) : '';
        $notes = isset($payload['notes']) ? sanitize_textarea_field((string) $payload['notes']) : '';
        $priority_actions = $this->sanitize_string_list($payload['priority_actions'] ?? array(), 5);
        $quick_wins = $this->sanitize_string_list($payload['quick_wins'] ?? array(), 5);

        if ('' === $executive_summary || empty($priority_actions)) {
            throw new \RuntimeException('The AI site audit response did not include a usable summary and priority actions.');
        }

        return array(
            'audit_title' => $audit_title,
            'executive_summary' => $executive_summary,
            'priority_actions' => $priority_actions,
            'quick_wins' => $quick_wins,
            'notes' => $notes,
            'provider' => $provider,
            'model' => $model,
            'system_prompt' => $system_prompt,
            'user_prompt' => $user_prompt,
            'report' => $report,
        );
    }

    public function chat_for_post(int $post_id, string $message, array $recent_messages = array(), bool $deep_analysis = false): array
    {
        $post = get_post($post_id);

        if (! $post instanceof \WP_Post) {
            throw new \RuntimeException('The requested page could not be loaded for AI chat.');
        }

        $message = trim($message);

        if ('' === $message) {
            throw new \RuntimeException('Enter a message before asking the AI Commander.');
        }

        $options = $this->settings->get();

        $provider = (string) $options['provider'];

        if ('local' !== $provider && empty($options['api_key'])) {
            throw new \RuntimeException('Add an API key in SEO Captain Settings before using the AI Commander.');
        }

        $model = $this->resolve_model($options);
        $temperature = $this->get_effective_temperature($options);
        $system_prompt = $this->build_chat_system_prompt((string) $options['system_prompt']);
        $prompt_result = $this->build_chat_user_prompt($post, $message, $recent_messages, $deep_analysis, $model);
        $user_prompt     = $prompt_result['prompt'];
        $memory_pressure = $prompt_result['memory_pressure'];

        if ('local' === $provider) {
            $raw_response = $this->call_local($model, $system_prompt, $user_prompt, $temperature);
        } elseif ('openai' === $provider) {
            $raw_response = $this->call_openai($options['api_key'], $model, $system_prompt, $user_prompt, $temperature);
        } elseif ('google' === $provider) {
            $raw_response = $this->call_google($options['api_key'], $model, $system_prompt, $user_prompt, $temperature);
        } else {
            throw new \RuntimeException('Unsupported AI provider configured.');
        }

        $payload = $this->decode_json_payload($raw_response);
        $reply = isset($payload['reply']) ? sanitize_textarea_field((string) $payload['reply']) : '';
        $suggested_title = isset($payload['suggested_title']) ? sanitize_text_field((string) $payload['suggested_title']) : '';
        $suggested_description = isset($payload['suggested_description']) ? sanitize_textarea_field((string) $payload['suggested_description']) : '';
        $wants_edits = ! empty($payload['wants_edits']);
        $notes = isset($payload['notes']) ? sanitize_textarea_field((string) $payload['notes']) : '';

        if ('' === $reply) {
            throw new \RuntimeException('The AI Commander did not return a usable reply.');
        }

        return array(
            'reply' => $reply,
            'suggested_title' => $suggested_title,
            'suggested_description' => $suggested_description,
            'wants_edits' => $wants_edits,
            'notes' => $notes,
            'provider' => $provider,
            'model' => $model,
            'system_prompt' => $system_prompt,
            'user_prompt' => $user_prompt,
            'memory_pressure' => $memory_pressure,
        );
    }

    private function build_system_prompt(string $custom_prompt): string
    {
        $base_prompt = trim($custom_prompt);
        $branding_suffix = $this->settings->get_branding_suffix();
        $suffix_note = '';

        if ('' !== $branding_suffix) {
            $suffix_len = function_exists('mb_strlen') ? mb_strlen($branding_suffix) : strlen($branding_suffix);
            $page_title_budget = max(10, 60 - $suffix_len);
            $suffix_note = ' IMPORTANT: The site auto-appends "' . $branding_suffix . '" (' . $suffix_len . ' chars) to every SEO title.' .
                ' You must generate ONLY the page-specific part of the title, WITHOUT the separator and brand.' .
                ' Keep seo_title at or under ' . $page_title_budget . ' characters (the system adds ' . $suffix_len . ' chars for branding to reach the 60-char total limit).';
        }

        return trim(
            $base_prompt . "\n\n" .
                'IDENTITY: You are the AI inside the "SEO Captain" WordPress plugin. This plugin handles ALL SEO. The user does NOT use Yoast, RankMath, or any other SEO plugin — never mention them.' . "\n" .
                'Return only valid JSON with exactly these keys: seo_title, meta_description, focus_keyphrase, keywords, social_title, social_description, notes. ' .
                'Do not use markdown fences. Keep the title at or under 60 characters (total including branding). ' .
                'Keep the meta description at or under 155 characters, ideally around 140-155. ' .
                'keywords is a comma-separated list of 5-8 relevant SEO keywords/phrases for the page (used for internal tagging and meta keywords). ' .
                'social_title is the Open Graph / Twitter sharing title — more engaging and attention-grabbing than seo_title, up to 70 characters. ' .
                'social_description is the social sharing description — a compelling hook for clicks, up to 200 characters. ' .
                'Be specific to the real page content and avoid generic claims. ' .
                'CRITICAL: If a focus keyphrase is provided, it MUST appear naturally in both the seo_title and meta_description. ' .
                'If the existing drafts are already well-optimized, return them unchanged — do not rewrite good content unnecessarily.' .
                $suffix_note
        );
    }

    private function build_site_audit_system_prompt(string $custom_prompt): string
    {
        $base_prompt = trim($custom_prompt);

        return trim(
            $base_prompt . "\n\n" .
                'Return only valid JSON with exactly these keys: audit_title, executive_summary, priority_actions, quick_wins, notes. ' .
                'priority_actions and quick_wins must be arrays of short strings. ' .
                'Do not use markdown fences. Focus on practical SEO execution order, not vague advice. ' .
                'Use the deterministic report as the source of truth and do not invent site facts.'
        );
    }

    private function build_chat_system_prompt(string $custom_prompt): string
    {
        $base_prompt = trim($custom_prompt);
        $branding_suffix = $this->settings->get_branding_suffix();
        $suffix_note = '';

        if ('' !== $branding_suffix) {
            $suffix_len = function_exists('mb_strlen') ? mb_strlen($branding_suffix) : strlen($branding_suffix);
            $page_title_budget = max(10, 60 - $suffix_len);
            $suffix_note = ' The site auto-appends "' . $branding_suffix . '" (' . $suffix_len . ' chars) to every SEO title.' .
                ' Generate ONLY the page-specific part, at or under ' . $page_title_budget . ' characters.';
        }

        return trim(
            $base_prompt . "\n\n" .
                'IDENTITY: You are the AI inside the "SEO Captain" WordPress plugin. ' .
                'This plugin handles ALL on-page SEO: meta titles, descriptions, schema markup, Open Graph, sitemaps, and audits. ' .
                'The user does NOT use Yoast, RankMath, or any other SEO plugin — never mention them. ' .
                'Always refer to "SEO Captain" when discussing the SEO plugin or its features.' . "\n\n" .
                'Return only valid JSON with exactly these keys: reply, suggested_title, suggested_description, wants_edits, notes. ' .
                'reply should answer the user directly with a clear, actionable plan. ' .
                'wants_edits must be true when the user asks you to improve, fix, or change page CONTENT (headings, paragraphs, links, images, structure). Set it false for questions or metadata-only requests.' . "\n\n" .
                'YOU HAVE FULL KNOWLEDGE of this page: URL, all SEO metadata fields (filled or empty), the full page text, headings, images, links, audit results, ' .
                'the page hierarchy (parent page, sibling pages, child pages — all with their SEO titles, keyphrases, and meta descriptions), ' .
                'keyphrase conflict warnings (other pages targeting the same keyphrase), and the site structure tree. Use ALL of it.' . "\n\n" .
                'TWO SEO CATEGORIES — always distinguish clearly:' . "\n" .
                '  A) SEO METADATA = title, meta description, focus keyphrase, canonical URL, robots directives, schema type, OG tags. These are managed by SEO Captain fields.' . "\n" .
                '  B) SEO CONTENT/STRUCTURE = H1-H6 headings, body text, word count, images with alt tags, internal/external links, CTAs, page hierarchy. These require page content edits.' . "\n\n" .
                'HIERARCHY & CANNIBALIZATION RULES:' . "\n" .
                '8. Use the Page Hierarchy data to understand WHERE this page sits in the site. A child page must have different SEO focus than its parent and siblings.' . "\n" .
                '9. If sibling pages already target similar keyphrases or have similar SEO titles, recommend a UNIQUE angle for this page. Never produce overlapping SEO metadata.' . "\n" .
                '10. If the KEYPHRASE CONFLICT WARNING section is present, ALWAYS mention the conflict in your reply and suggest a differentiated keyphrase.' . "\n" .
                '11. Use the Site Structure tree to suggest internal linking opportunities between related pages.' . "\n\n" .
                'RESPONSE RULES:' . "\n" .
                '1. When user asks to "improve SEO" or "improve all" → report AND suggest fixes for BOTH categories. Fill suggested_title and suggested_description with improved versions. Set wants_edits=true for content fixes.' . "\n" .
                '2. When user asks only about metadata (title, description, keyphrase) → suggest metadata improvements only. Do not discuss content structure.' . "\n" .
                '3. When user asks only about structure/content → suggest content fixes only. Do not alter metadata.' . "\n" .
                '4. ALWAYS flag every real gap found in the data. If keyphrase is missing from title or description, ALWAYS mention it and provide a fix. If title length is bad, say so. Never skip a problem just because another score looks OK.' . "\n" .
                '5. When audit data is present, reference the EXACT issues and suggestions — do not invent new ones or omit any.' . "\n" .
                '6. For suggested_title and suggested_description: incorporate the focus keyphrase naturally. Respect length limits (title ≤ 60 chars, description ≤ 155 chars).' . "\n" .
                '7. Never change metadata that is already correct (right length, keyphrase present, compelling copy). But if ANY gap exists, fix it.' . "\n" .
                'Do not invent facts not supported by the page content or context.' .
                $suffix_note
        );
    }

    private function get_seo_context(\WP_Post $post, array $overrides = array()): array
    {
        $deep_analysis = ! empty($overrides['deep_analysis']);
        // Use browser values if provided, otherwise fall back to saved meta.
        $focus_keyphrase = isset($overrides['focus_keyphrase']) && '' !== $overrides['focus_keyphrase']
            ? trim(wp_strip_all_tags($overrides['focus_keyphrase']))
            : trim(wp_strip_all_tags((string) get_post_meta($post->ID, '_ai_seo_captain_focus_keyphrase', true)));

        $seo_title_draft = isset($overrides['seo_title']) && null !== $overrides['seo_title']
            ? trim($overrides['seo_title'])
            : trim((string) get_post_meta($post->ID, '_ai_seo_captain_meta_title', true));

        $meta_desc_draft = isset($overrides['meta_description']) && null !== $overrides['meta_description']
            ? trim($overrides['meta_description'])
            : trim((string) get_post_meta($post->ID, '_ai_seo_captain_meta_description', true));

        // Snippet score metrics.
        $title_len = mb_strlen($seo_title_draft);
        $desc_len = mb_strlen($meta_desc_draft);
        $kw_in_title = '' !== $focus_keyphrase && '' !== $seo_title_draft && false !== mb_stripos($seo_title_draft, $focus_keyphrase);
        $kw_in_desc = '' !== $focus_keyphrase && '' !== $meta_desc_draft && false !== mb_stripos($meta_desc_draft, $focus_keyphrase);

        // Page audit data (if previously audited).
        $audit_raw = get_post_meta($post->ID, '_ai_seo_captain_page_audit', true);
        $audit_data = is_array($audit_raw) ? $audit_raw : array();

        // Additional tab data for full context.
        $social_title = isset($overrides['social_title']) && null !== $overrides['social_title']
            ? trim($overrides['social_title'])
            : trim((string) get_post_meta($post->ID, '_ai_seo_captain_social_title', true));

        $social_description = isset($overrides['social_description']) && null !== $overrides['social_description']
            ? trim($overrides['social_description'])
            : trim((string) get_post_meta($post->ID, '_ai_seo_captain_social_description', true));

        $schema_type = isset($overrides['schema_type']) && null !== $overrides['schema_type']
            ? trim($overrides['schema_type'])
            : trim((string) get_post_meta($post->ID, '_ai_seo_captain_schema_type', true));

        $canonical_url = isset($overrides['canonical_url']) && null !== $overrides['canonical_url']
            ? trim($overrides['canonical_url'])
            : trim((string) get_post_meta($post->ID, '_ai_seo_captain_canonical_url', true));

        $robots_directives = isset($overrides['robots_directives']) && null !== $overrides['robots_directives']
            ? trim($overrides['robots_directives'])
            : trim((string) get_post_meta($post->ID, '_ai_seo_captain_robots_directives', true));

        $is_cornerstone = isset($overrides['cornerstone']) && null !== $overrides['cornerstone']
            ? ('1' === $overrides['cornerstone'])
            : (! empty(get_post_meta($post->ID, '_ai_seo_captain_cornerstone', true)));

        // WooCommerce product data (supplied by WooCommerce_Integration filter when active).
        $wc_data = apply_filters('ai_seo_captain_product_context', array(), $post);

        // Hierarchy context for AI Chat (parent, siblings, children with SEO meta).
        $hierarchy = $this->content_indexer->get_hierarchy_context((int) $post->ID);

        // Keyphrase cannibalization: other pages targeting the same focus keyphrase.
        $keyphrase_conflicts = $this->content_indexer->get_keyphrase_conflicts((int) $post->ID);

        // Compact site tree for structural awareness.
        $site_tree = $this->content_indexer->get_compact_site_tree((int) $post->ID);

        // Topically related pages: keyword-based cross-hierarchy search.
        $exclude_ids = array();
        if (! empty($hierarchy['siblings'])) {
            foreach ($hierarchy['siblings'] as $sib) {
                $exclude_ids[] = (int) $sib['object_id'];
            }
        }
        if (! empty($hierarchy['children'])) {
            foreach ($hierarchy['children'] as $child) {
                $exclude_ids[] = (int) $child['object_id'];
            }
        }
        if (is_array($hierarchy['parent'] ?? null)) {
            $exclude_ids[] = (int) $hierarchy['parent']['object_id'];
        }
        $topical_pages = $this->content_indexer->get_topically_related_pages((int) $post->ID, $exclude_ids, $deep_analysis, 20);

        // WordPress taxonomy terms (categories, tags, custom taxonomies).
        $taxonomy_terms = array();
        $taxonomies = get_object_taxonomies($post->post_type, 'objects');
        foreach ($taxonomies as $tax_slug => $tax_obj) {
            if (! $tax_obj->public) {
                continue;
            }
            $terms = get_the_terms($post->ID, $tax_slug);
            if (is_array($terms) && ! empty($terms)) {
                $term_names = wp_list_pluck($terms, 'name');
                $taxonomy_terms[$tax_obj->label] = implode(', ', $term_names);
            }
        }

        // Publish and last-modified dates.
        $publish_date  = (string) $post->post_date;
        $modified_date = (string) $post->post_modified;

        // Featured image.
        $has_featured_image = has_post_thumbnail($post->ID);

        // Site language.
        $site_locale = get_locale();

        // --- Content analysis from raw HTML ---
        $raw_html       = Content_Helper::get_content($post);
        $plain_content  = $this->normalize_text($raw_html);
        $home_url       = home_url();
        $word_count     = str_word_count($plain_content);
        $img_count      = preg_match_all('/<img\b/i', $raw_html);
        $img_no_alt     = preg_match_all('/<img(?![^>]*\balt\s*=\s*"[^"]+")[^>]*>/i', $raw_html);
        $int_link_count = preg_match_all('/href=["\']' . preg_quote($home_url, '/') . '/i', $raw_html);
        $ext_total      = preg_match_all('/href=["\'](https?:\/\/)/i', $raw_html);
        $ext_link_count = max(0, $ext_total - $int_link_count);
        $video_count    = preg_match_all('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/|vimeo\.com\/(?:video\/)?\d)/i', $raw_html)
            + preg_match_all('/<video\b/i', $raw_html);
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

        // Deep analysis: gather sibling body content (sanitized excerpts).
        $sibling_content = array();
        if ($deep_analysis && ! empty($hierarchy['siblings'])) {
            foreach ($hierarchy['siblings'] as $sib) {
                $sib_post = get_post((int) $sib['object_id']);
                if ($sib_post instanceof \WP_Post) {
                    $sib_clean = Content_Helper::sanitize_for_ai(Content_Helper::get_content($sib_post));
                    if ('' !== $sib_clean) {
                        // ~200 words ≈ 800 chars — enough structural context for SEO
                        // differentiation without overwhelming small context windows.
                        $sibling_content[(int) $sib['object_id']] = mb_substr($sib_clean, 0, 800);
                    }
                }
            }
        }

        // Google Search Console performance data (graceful when not connected).
        $gsc_performance = null;
        if (
            $this->search_console && $this->search_console->is_connected()
            && '' !== $this->search_console->get_config()['site_url']
        ) {
            $permalink = get_permalink($post);
            if ($permalink) {
                $gsc_performance = $this->search_console->get_page_performance($permalink, 30);
            }
        }

        return array(
            'focus_keyphrase' => $focus_keyphrase,
            'seo_title_draft' => $seo_title_draft,
            'meta_desc_draft' => $meta_desc_draft,
            'title_length' => $title_len,
            'desc_length' => $desc_len,
            'keyphrase_in_title' => $kw_in_title,
            'keyphrase_in_desc' => $kw_in_desc,
            'audit_score' => isset($audit_data['score']) ? (int) $audit_data['score'] : null,
            'audit_issues' => isset($audit_data['issues']) ? $audit_data['issues'] : array(),
            'audit_suggestions' => isset($audit_data['suggestions']) ? $audit_data['suggestions'] : array(),
            'audit_summary' => isset($audit_data['summary']) ? (string) $audit_data['summary'] : '',
            'audit_full_report' => isset($audit_data['full_report']) ? (string) $audit_data['full_report'] : '',
            'audit_audited_at' => isset($audit_data['audited_at']) ? (string) $audit_data['audited_at'] : '',
            'audit_deep_analysis' => ! empty($audit_data['deep_analysis']),
            'social_title' => $social_title,
            'social_description' => $social_description,
            'schema_type' => $schema_type,
            'canonical_url' => $canonical_url,
            'robots_directives' => $robots_directives,
            'is_cornerstone' => $is_cornerstone,
            'wc_data' => $wc_data,
            'hierarchy' => $hierarchy,
            'keyphrase_conflicts' => $keyphrase_conflicts,
            'site_tree' => $site_tree,
            'topical_pages' => $topical_pages,
            'taxonomy_terms' => $taxonomy_terms,
            'publish_date' => $publish_date,
            'modified_date' => $modified_date,
            'has_featured_image' => $has_featured_image,
            'site_locale' => $site_locale,
            // Content analysis.
            'word_count' => $word_count,
            'heading_structure' => $heading_summary,
            'images_total' => $img_count,
            'images_missing_alt' => $img_no_alt,
            'internal_links' => $int_link_count,
            'external_links' => $ext_link_count,
            'internal_link_urls' => $internal_link_urls,
            'external_link_urls' => $external_link_urls,
            'image_details' => $image_details,
            'video_count' => $video_count,
            'doc_count' => $doc_count,
            'sibling_content' => $sibling_content,
            'gsc_performance' => $gsc_performance,
        );
    }

    private function format_seo_context_lines(array $ctx): string
    {
        $lines = array();
        $branding_suffix = $this->settings->get_branding_suffix();
        $suffix_info = '';
        if ('' !== $branding_suffix) {
            $suffix_len = function_exists('mb_strlen') ? mb_strlen($branding_suffix) : strlen($branding_suffix);
            $suffix_info = ' (page-part only; "' . $branding_suffix . '" auto-appended = ' . $suffix_len . ' extra chars)';
        }
        $lines[] = 'Current SEO title draft: ' . ('' !== $ctx['seo_title_draft'] ? $ctx['seo_title_draft'] . ' (' . $ctx['title_length'] . ' chars' . $suffix_info . ')' : 'Empty — not yet written');
        $lines[] = 'Current meta description draft: ' . ('' !== $ctx['meta_desc_draft'] ? $ctx['meta_desc_draft'] . ' (' . $ctx['desc_length'] . ' chars)' : 'Empty — not yet written');
        $lines[] = 'Focus keyphrase: ' . ('' !== $ctx['focus_keyphrase'] ? $ctx['focus_keyphrase'] : 'None specified');
        $lines[] = 'Keyphrase in title: ' . ($ctx['keyphrase_in_title'] ? 'Found' : 'Missing');
        $lines[] = 'Keyphrase in description: ' . ($ctx['keyphrase_in_desc'] ? 'Found' : 'Missing');

        if (null !== $ctx['audit_score']) {
            $audit_header = '--- AI SEO Audit Results (score ' . $ctx['audit_score'] . '/100';
            if (! empty($ctx['audit_audited_at'])) {
                $audit_header .= ', audited ' . $ctx['audit_audited_at'] . ' UTC';
            }
            if (! empty($ctx['audit_deep_analysis'])) {
                $audit_header .= ', deep analysis';
            }
            $audit_header .= ') ---';
            $lines[] = $audit_header;
            if (! empty($ctx['audit_issues'])) {
                $lines[] = 'Issues found:';
                foreach ($ctx['audit_issues'] as $issue) {
                    $lines[] = '  - ' . $issue;
                }
            }
            if (! empty($ctx['audit_suggestions'])) {
                $lines[] = 'Suggestions:';
                foreach ($ctx['audit_suggestions'] as $suggestion) {
                    $lines[] = '  - ' . $suggestion;
                }
            }
            if ('' !== $ctx['audit_summary']) {
                $lines[] = 'Audit summary: ' . $ctx['audit_summary'];
            }
            if ('' !== ($ctx['audit_full_report'] ?? '')) {
                $lines[] = 'Full audit report:';
                $lines[] = $ctx['audit_full_report'];
            }
        }

        // Additional tab data for full context.
        if ('' !== ($ctx['social_title'] ?? '')) {
            $lines[] = 'Social title override: ' . $ctx['social_title'];
        }
        if ('' !== ($ctx['social_description'] ?? '')) {
            $lines[] = 'Social description override: ' . $ctx['social_description'];
        }
        if ('' !== ($ctx['schema_type'] ?? '')) {
            $lines[] = 'Schema type: ' . $ctx['schema_type'];
        }
        if ('' !== ($ctx['canonical_url'] ?? '')) {
            $lines[] = 'Canonical URL: ' . $ctx['canonical_url'];
        }
        if ('' !== ($ctx['robots_directives'] ?? '')) {
            $lines[] = 'Robots directives: ' . $ctx['robots_directives'];
        }
        if (! empty($ctx['is_cornerstone'])) {
            $lines[] = 'Cornerstone content: Yes (high-priority page)';
        }

        // Site language.
        if (! empty($ctx['site_locale'])) {
            $lines[] = 'Site language: ' . $ctx['site_locale'];
        }

        // Publish and modified dates.
        if (! empty($ctx['publish_date'])) {
            $lines[] = 'Published: ' . $ctx['publish_date'];
        }
        if (! empty($ctx['modified_date']) && $ctx['modified_date'] !== $ctx['publish_date']) {
            $lines[] = 'Last modified: ' . $ctx['modified_date'];
        }

        // Featured image.
        $lines[] = 'Featured image: ' . (! empty($ctx['has_featured_image']) ? 'Yes' : 'None');

        // WordPress taxonomy terms (categories, tags, etc.).
        if (! empty($ctx['taxonomy_terms'])) {
            foreach ($ctx['taxonomy_terms'] as $tax_label => $term_list) {
                $lines[] = $tax_label . ': ' . $term_list;
            }
        }

        // WooCommerce product data.
        if (! empty($ctx['wc_data'])) {
            $wc = $ctx['wc_data'];
            $lines[] = '--- WooCommerce Product Data ---';
            if (! empty($wc['wc_price'])) {
                $lines[] = 'Price: '        . $wc['wc_price'];
            }
            if (! empty($wc['wc_sku'])) {
                $lines[] = 'SKU: '          . $wc['wc_sku'];
            }
            if (! empty($wc['wc_availability'])) {
                $lines[] = 'Availability: ' . $wc['wc_availability'];
            }
            if (! empty($wc['wc_type'])) {
                $lines[] = 'Product type: ' . $wc['wc_type'];
            }
            if (! empty($wc['wc_rating'])) {
                $lines[] = 'Rating: '       . $wc['wc_rating'];
            }
            if (! empty($wc['wc_categories'])) {
                $lines[] = 'Categories: '   . $wc['wc_categories'];
            }
            if (! empty($wc['wc_tags'])) {
                $lines[] = 'Tags: '         . $wc['wc_tags'];
            }
            if (! empty($wc['wc_brand'])) {
                $lines[] = 'Brand: '        . $wc['wc_brand'];
            }
        }

        // Content analysis stats.
        if (isset($ctx['word_count'])) {
            $lines[] = '--- Content Analysis ---';
            $lines[] = 'Word count: ' . (int) $ctx['word_count'];
            $lines[] = 'Heading structure: ' . ('' !== ($ctx['heading_structure'] ?? '') ? $ctx['heading_structure'] : 'No headings found');
            $lines[] = 'Images: ' . (int) ($ctx['images_total'] ?? 0) . ' total, ' . (int) ($ctx['images_missing_alt'] ?? 0) . ' missing alt text';
            $lines[] = 'Internal links: ' . (int) ($ctx['internal_links'] ?? 0) . ', External links: ' . (int) ($ctx['external_links'] ?? 0);
            $lines[] = 'Videos embedded: ' . (int) ($ctx['video_count'] ?? 0);
            $lines[] = 'Documents linked: ' . (int) ($ctx['doc_count'] ?? 0);

            if (! empty($ctx['internal_link_urls'])) {
                $lines[] = 'Internal link URLs:';
                foreach ($ctx['internal_link_urls'] as $url) {
                    $lines[] = '  → ' . $url;
                }
            }
            if (! empty($ctx['external_link_urls'])) {
                $lines[] = 'External link URLs:';
                foreach ($ctx['external_link_urls'] as $url) {
                    $lines[] = '  → ' . $url;
                }
            }
            if (! empty($ctx['image_details'])) {
                $lines[] = 'Image details:';
                foreach ($ctx['image_details'] as $img) {
                    $lines[] = '  - src: ' . $img['src'] . ' | alt: ' . ('' !== $img['alt'] ? '"' . $img['alt'] . '"' : 'MISSING');
                }
            }
        }

        // Google Search Console performance (30-day).
        if (! empty($ctx['gsc_performance'])) {
            $gsc = $ctx['gsc_performance'];
            $lines[] = '--- Google Search Console (last 30 days) ---';
            $lines[] = 'Clicks: ' . (int) $gsc['clicks'] . ' | Impressions: ' . (int) $gsc['impressions']
                . ' | CTR: ' . round((float) $gsc['ctr'] * 100, 1) . '%'
                . ' | Avg position: ' . number_format((float) $gsc['position'], 1);
            if ((int) $gsc['impressions'] > 0 && (int) $gsc['clicks'] === 0) {
                $lines[] = 'NOTE: This page appears in Google results but gets zero clicks — the title and description likely need improvement.';
            } elseif ((int) $gsc['impressions'] === 0) {
                $lines[] = 'NOTE: This page has no Google impressions — it may not be indexed or ranks too low to appear.';
            } elseif ((float) $gsc['ctr'] < 0.02 && (int) $gsc['impressions'] > 100) {
                $lines[] = 'NOTE: Low CTR despite many impressions — consider making the title more compelling or the description more specific.';
            }
            if ((float) $gsc['position'] > 20) {
                $lines[] = 'NOTE: Average position is beyond page 2 of Google. Content quality, internal linking, or keyphrase targeting may need work.';
            }
        } elseif (null === ($ctx['gsc_performance'] ?? null)) {
            $lines[] = '--- Google Search Console ---';
            $lines[] = 'Not connected or no data available for this page.';
        }

        // Page hierarchy context (parent, siblings, children with SEO metadata).
        if (! empty($ctx['hierarchy'])) {
            $h = $ctx['hierarchy'];
            $lines[] = '--- Page Hierarchy ---';
            $lines[] = 'Position: ' . ('' !== $h['position'] ? $h['position'] : 'Unknown');

            if (is_array($h['grandparent'])) {
                $lines[] = 'Grandparent: "' . $h['grandparent']['title'] . '" /' . ltrim($h['grandparent']['slug'], '/') . '/'
                    . ('' !== trim((string) $h['grandparent']['focus_keyphrase']) ? ' [kp: "' . $h['grandparent']['focus_keyphrase'] . '"]' : '')
                    . ('' !== trim((string) ($h['grandparent']['keywords'] ?? '')) ? ' | keywords: "' . $h['grandparent']['keywords'] . '"' : '');
            }

            if (is_array($h['parent'])) {
                $lines[] = 'Parent page: "' . $h['parent']['title'] . '" /' . ltrim($h['parent']['slug'], '/') . '/'
                    . ('' !== trim((string) $h['parent']['seo_title']) ? ' | SEO title: "' . $h['parent']['seo_title'] . '"' : '')
                    . ('' !== trim((string) $h['parent']['focus_keyphrase']) ? ' | kp: "' . $h['parent']['focus_keyphrase'] . '"' : '')
                    . ('' !== trim((string) $h['parent']['meta_description']) ? ' | desc: "' . $h['parent']['meta_description'] . '"' : '')
                    . ('' !== trim((string) ($h['parent']['keywords'] ?? '')) ? ' | keywords: "' . $h['parent']['keywords'] . '"' : '')
                    . ('' !== trim((string) ($h['parent']['social_title'] ?? '')) ? ' | social title: "' . $h['parent']['social_title'] . '"' : '')
                    . ('' !== trim((string) ($h['parent']['social_description'] ?? '')) ? ' | social desc: "' . $h['parent']['social_description'] . '"' : '');
            }

            if (! empty($h['siblings'])) {
                $has_sib_content = ! empty($ctx['sibling_content']);
                $lines[] = 'Sibling pages (' . count($h['siblings']) . '):' . ($has_sib_content ? ' [deep analysis — includes body content excerpts]' : '');
                foreach ($h['siblings'] as $sib) {
                    $lines[] = '  - "' . $sib['title'] . '" /' . ltrim($sib['slug'], '/') . '/'
                        . ('' !== trim((string) $sib['focus_keyphrase']) ? ' [kp: "' . $sib['focus_keyphrase'] . '"]' : '')
                        . ('' !== trim((string) $sib['seo_title']) ? ' | SEO: "' . $sib['seo_title'] . '"' : '')
                        . ('' !== trim((string) $sib['meta_description']) ? ' | desc: "' . $sib['meta_description'] . '"' : '')
                        . ('' !== trim((string) ($sib['keywords'] ?? '')) ? ' | keywords: "' . $sib['keywords'] . '"' : '')
                        . ('' !== trim((string) ($sib['social_title'] ?? '')) ? ' | social title: "' . $sib['social_title'] . '"' : '')
                        . ('' !== trim((string) ($sib['social_description'] ?? '')) ? ' | social desc: "' . $sib['social_description'] . '"' : '');
                    if ($has_sib_content && ! empty($ctx['sibling_content'][(int) $sib['object_id']])) {
                        $lines[] = '    Content preview: ' . $ctx['sibling_content'][(int) $sib['object_id']];
                    }
                }
            }

            if (! empty($h['children'])) {
                $lines[] = 'Child pages (' . count($h['children']) . '):';
                foreach ($h['children'] as $child) {
                    $lines[] = '  - "' . $child['title'] . '" /' . ltrim($child['slug'], '/') . '/'
                        . ('' !== trim((string) $child['focus_keyphrase']) ? ' [kp: "' . $child['focus_keyphrase'] . '"]' : '')
                        . ('' !== trim((string) $child['seo_title']) ? ' | SEO: "' . $child['seo_title'] . '"' : '')
                        . ('' !== trim((string) $child['meta_description']) ? ' | desc: "' . $child['meta_description'] . '"' : '')
                        . ('' !== trim((string) ($child['keywords'] ?? '')) ? ' | keywords: "' . $child['keywords'] . '"' : '')
                        . ('' !== trim((string) ($child['social_title'] ?? '')) ? ' | social title: "' . $child['social_title'] . '"' : '')
                        . ('' !== trim((string) ($child['social_description'] ?? '')) ? ' | social desc: "' . $child['social_description'] . '"' : '');
                }
            }
        }

        // Keyphrase cannibalization warnings.
        if (! empty($ctx['keyphrase_conflicts'])) {
            $lines[] = '--- KEYPHRASE CONFLICT WARNING ---';
            $lines[] = 'The following pages target the SAME focus keyphrase as this page:';
            foreach ($ctx['keyphrase_conflicts'] as $conflict) {
                $lines[] = '  - "' . $conflict['title'] . '" /' . ltrim($conflict['slug'], '/') . '/'
                    . ' | kp: "' . $conflict['focus_keyphrase'] . '"'
                    . ('' !== trim((string) $conflict['seo_title']) ? ' | SEO: "' . $conflict['seo_title'] . '"' : '')
                    . ('' !== trim((string) $conflict['meta_description']) ? ' | desc: "' . $conflict['meta_description'] . '"' : '')
                    . ('' !== trim((string) ($conflict['keywords'] ?? '')) ? ' | keywords: "' . $conflict['keywords'] . '"' : '')
                    . ('' !== trim((string) ($conflict['social_title'] ?? '')) ? ' | social title: "' . $conflict['social_title'] . '"' : '')
                    . ('' !== trim((string) ($conflict['social_description'] ?? '')) ? ' | social desc: "' . $conflict['social_description'] . '"' : '')
                    . ' | type: ' . $conflict['post_type'];
            }
            $lines[] = 'ACTION REQUIRED: Recommend unique keyphrases for this page to avoid SEO cannibalization.';
        }

        // Topically related pages (cross-hierarchy, keyword-based).
        if (! empty($ctx['topical_pages'])) {
            $lines[] = '--- Topically Related Pages (keyword match across site) ---';
            $lines[] = 'These pages share keywords with the current page but are NOT structural siblings/children. Watch for topic overlap and cannibalization:';
            foreach ($ctx['topical_pages'] as $tp) {
                $tp_line = '  - "' . $tp['title'] . '" /' . ltrim($tp['slug'], '/') . '/'
                    . ' | type: ' . $tp['post_type']
                    . ('' !== trim((string) $tp['focus_keyphrase']) ? ' | kp: "' . $tp['focus_keyphrase'] . '"' : '')
                    . ('' !== trim((string) $tp['seo_title']) ? ' | SEO: "' . $tp['seo_title'] . '"' : '')
                    . ('' !== trim((string) $tp['meta_description']) ? ' | desc: "' . $tp['meta_description'] . '"' : '')
                    . ('' !== trim((string) ($tp['keywords'] ?? '')) ? ' | keywords: "' . $tp['keywords'] . '"' : '')
                    . ('' !== trim((string) ($tp['social_title'] ?? '')) ? ' | social title: "' . $tp['social_title'] . '"' : '')
                    . ('' !== trim((string) ($tp['social_description'] ?? '')) ? ' | social desc: "' . $tp['social_description'] . '"' : '');
                $lines[] = $tp_line;
                if (! empty($tp['excerpt_content'])) {
                    $lines[] = '    Content preview: ' . $tp['excerpt_content'];
                }
            }
        }

        // Compact site tree.
        if (! empty($ctx['site_tree'])) {
            $lines[] = '--- Site Structure (titles + keyphrases) ---';
            $lines[] = $ctx['site_tree'];
        }

        return implode("\n", $lines);
    }

    private function build_user_prompt(\WP_Post $post, array $field_overrides = array()): string
    {
        $ctx = $this->get_seo_context($post, $field_overrides);
        $page_content = Content_Helper::sanitize_for_ai(Content_Helper::get_content($post));
        $page_excerpt = $this->normalize_text((string) $post->post_excerpt);

        // Cap body content to ~4 000 words (≈20 000 chars) for metadata generation.
        // The AI only needs enough context to write a title + description; sending
        // a full 10 000-word post wastes tokens without improving output quality.
        $content_cap = 20000;
        if (function_exists('mb_strlen') ? mb_strlen($page_content) > $content_cap : strlen($page_content) > $content_cap) {
            $page_content = $this->truncate_text($page_content, $content_cap) . ' [content truncated for token efficiency]';
        }

        $branding_suffix = $this->settings->get_branding_suffix();
        $branding_note = '';
        if ('' !== $branding_suffix) {
            $suffix_len = function_exists('mb_strlen') ? mb_strlen($branding_suffix) : strlen($branding_suffix);
            $page_title_budget = max(10, 60 - $suffix_len);
            $branding_note = 'Title branding: The system auto-appends "' . $branding_suffix . '" (' . $suffix_len . ' chars) to the title. Generate ONLY the page-specific part — max ' . $page_title_budget . ' chars. Do NOT include the separator or brand name in seo_title.';
        }

        // Build the "preserve if good" guard based on existing drafts.
        $preserve_instruction = '';
        $has_existing_title = '' !== $ctx['seo_title_draft'];
        $has_existing_desc = '' !== $ctx['meta_desc_draft'];
        if ($has_existing_title && $has_existing_desc) {
            $preserve_instruction = 'IMPORTANT — Preservation rule: The page already has an SEO title and meta description draft (shown above). '
                . 'Do NOT blindly replace them. Evaluate whether they are accurate, well-written, contain the focus keyphrase, and have correct length. '
                . 'If the existing drafts are already good, return them unchanged and explain in notes why no changes were needed. '
                . 'Only rewrite them if there is a concrete problem: wrong length, missing keyphrase, factual inaccuracy, poor differentiation from related pages, or low relevance to the page content.';
        }

        $site_context = trim((string) ($this->settings->get()['site_chat_context'] ?? ''));

        $prompt_parts = array(
            'Task: Generate or refine the SEO title and meta description for the current WordPress page.',
            'Output format: {"seo_title":"...","meta_description":"...","focus_keyphrase":"...","keywords":"...","social_title":"...","social_description":"...","notes":"..."}',
            'Requirements: Make the draft clearly differentiated from the topically related pages and hierarchy siblings shown in the SEO context below. Keep seo_title at or under ' . ('' !== $branding_suffix ? (string) $page_title_budget : '60') . ' characters and meta_description at or under 155 characters. social_title is the Open Graph / Twitter sharing title — it can be more engaging and attention-grabbing than seo_title, up to 70 characters. social_description is the social sharing description — a compelling hook for clicks, up to 200 characters. keywords is a comma-separated list of 5-8 relevant SEO keywords/phrases for the page. Do not invent services, guarantees, or facts not present on the page. The focus_keyphrase should be the single most important 2-4 word phrase this page should rank for. When a focus keyphrase is already provided, keep it in your output AND ensure it appears naturally in both seo_title and meta_description. Notes should be one or two short sentences explaining the positioning choice or why the existing draft was kept.',
            'Site: ' . get_bloginfo('name'),
            'Page type: ' . $post->post_type,
            'Current page title: ' . (string) $post->post_title,
            'Page URL: ' . (string) get_permalink($post),
            'Existing excerpt: ' . ('' !== $page_excerpt ? $page_excerpt : 'None'),
            $this->format_seo_context_lines($ctx),
            'Main page content: ' . ('' !== $page_content ? $page_content : 'No body content is available.'),
        );

        if ('' !== $site_context) {
            $prompt_parts[] = "Site owner's description of the business and goals:\n" . $site_context;
        }

        if ('' !== $preserve_instruction) {
            $prompt_parts[] = $preserve_instruction;
        }

        if ('' !== $branding_note) {
            $prompt_parts[] = $branding_note;
        }

        return implode("\n\n", $prompt_parts);
    }

    private function build_site_audit_user_prompt(array $report): string
    {
        $summary = isset($report['summary']) && is_array($report['summary']) ? $report['summary'] : array();
        $priority_rows = isset($report['priority_rows']) && is_array($report['priority_rows']) ? $report['priority_rows'] : array();
        $duplicate_live_titles = isset($report['duplicate_live_titles']) && is_array($report['duplicate_live_titles']) ? $report['duplicate_live_titles'] : array();
        $duplicate_ai_titles = isset($report['duplicate_ai_titles']) && is_array($report['duplicate_ai_titles']) ? $report['duplicate_ai_titles'] : array();
        $duplicate_ai_descriptions = isset($report['duplicate_ai_descriptions']) && is_array($report['duplicate_ai_descriptions']) ? $report['duplicate_ai_descriptions'] : array();
        $thin_content_rows = isset($report['thin_content_rows']) && is_array($report['thin_content_rows']) ? $report['thin_content_rows'] : array();

        $priority_lines = array();
        foreach ($priority_rows as $row) {
            $priority_lines[] = sprintf(
                '- %s | %s | title draft: %s | description draft: %s | approved: %s | frontend ready: %s',
                isset($row['title']) ? (string) $row['title'] : '(untitled)',
                isset($row['post_type']) ? (string) $row['post_type'] : 'unknown',
                ! empty($row['has_title_draft']) ? 'yes' : 'no',
                ! empty($row['has_description_draft']) ? 'yes' : 'no',
                ! empty($row['has_approved_suggestion']) ? 'yes' : 'no',
                ! empty($row['frontend_ready']) ? 'yes' : 'no'
            );
        }

        if (empty($priority_lines)) {
            $priority_lines[] = '- No priority rows were found.';
        }

        $branding_suffix = $this->settings->get_branding_suffix();
        $branding_context = '';
        if ('' !== $branding_suffix) {
            $branding_context = 'Title branding: All page titles auto-append "' . $branding_suffix . '". Stored titles contain only the page-specific part.';
        }

        $prompt_parts = array(
            'Task: Turn this deterministic WordPress SEO audit into a prioritized execution summary for the site owner.',
            'Output format: {"audit_title":"...","executive_summary":"...","priority_actions":["..."],"quick_wins":["..."],"notes":"..."}',
            'Requirements: Prioritize concrete fixes first. Mention conflicts with an existing SEO plugin only when relevant. Do not recommend deleting proven safety gates. Keep the output concise and operator-focused.',
            'Site: ' . get_bloginfo('name'),
            'Summary counts: ' . wp_json_encode($summary),
            "Priority content rows:\n" . implode("\n", $priority_lines),
            "Duplicate live titles:\n" . $this->format_duplicate_prompt_lines($duplicate_live_titles),
            "Duplicate AI title drafts:\n" . $this->format_duplicate_prompt_lines($duplicate_ai_titles),
            "Duplicate AI description drafts:\n" . $this->format_duplicate_prompt_lines($duplicate_ai_descriptions),
            "Thin content rows:\n" . $this->format_thin_content_prompt_lines($thin_content_rows),
        );

        if ('' !== $branding_context) {
            $prompt_parts[] = $branding_context;
        }

        // Inject Google Search Console data when available.
        if (
            $this->search_console && $this->search_console->is_connected()
            && '' !== $this->search_console->get_config()['site_url']
        ) {
            $gsc_summary = $this->search_console->get_site_summary(30);
            if ($gsc_summary['impressions'] > 0) {
                $gsc_end   = gmdate('Y-m-d', strtotime('-2 days'));
                $gsc_start = gmdate('Y-m-d', strtotime('-30 days'));
                $top_queries = $this->search_console->get_top_items($gsc_start, $gsc_end, 'query', 10);
                $top_pages   = $this->search_console->get_top_items($gsc_start, $gsc_end, 'page', 10);

                $gsc_lines = array(
                    '--- Google Search Console (last 30 days) ---',
                    'Site totals: ' . $gsc_summary['clicks'] . ' clicks, '
                        . $gsc_summary['impressions'] . ' impressions, '
                        . round($gsc_summary['ctr'] * 100, 1) . '% CTR, '
                        . 'avg position ' . number_format($gsc_summary['position'], 1),
                );

                if (! empty($top_queries)) {
                    $gsc_lines[] = 'Top search queries:';
                    foreach ($top_queries as $q) {
                        $gsc_lines[] = '  - "' . $q->dimension_value . '" | '
                            . $q->clicks . ' clicks, ' . $q->impressions . ' impr, '
                            . round($q->ctr * 100, 1) . '% CTR, pos ' . number_format($q->position, 1);
                    }
                }

                if (! empty($top_pages)) {
                    $gsc_lines[] = 'Top pages by clicks:';
                    foreach ($top_pages as $pg) {
                        $path = wp_parse_url($pg->dimension_value, PHP_URL_PATH) ?: $pg->dimension_value;
                        $gsc_lines[] = '  - ' . $path . ' | '
                            . $pg->clicks . ' clicks, ' . $pg->impressions . ' impr, '
                            . round($pg->ctr * 100, 1) . '% CTR, pos ' . number_format($pg->position, 1);
                    }
                }

                $prompt_parts[] = implode("\n", $gsc_lines);
            }
        }

        return implode("\n\n", $prompt_parts);
    }

    private function build_chat_user_prompt(\WP_Post $post, string $message, array $recent_messages, bool $deep_analysis = false, string $model_id = ''): array
    {
        $ctx = $this->get_seo_context($post, $deep_analysis ? array('deep_analysis' => true) : array());

        // Send FULL page content — no truncation. AI needs every element for proper SEO analysis.
        // Sanitized HTML preserves structure (headings, images with position, links, videos)
        // while stripping all builder junk, inline styles, scripts, and non-semantic wrappers.
        $page_html = Content_Helper::sanitize_for_ai(Content_Helper::get_content($post));
        $page_excerpt = $this->normalize_text((string) $post->post_excerpt);

        $branding_suffix = $this->settings->get_branding_suffix();
        $branding_note = '';
        if ('' !== $branding_suffix) {
            $suffix_len = function_exists('mb_strlen') ? mb_strlen($branding_suffix) : strlen($branding_suffix);
            $page_title_budget = max(10, 60 - $suffix_len);
            $branding_note = 'Title branding: "' . $branding_suffix . '" (' . $suffix_len . ' chars) is auto-appended. suggested_title must be ONLY the page-specific part — max ' . $page_title_budget . ' chars.';
        }

        $site_context = trim((string) ($this->settings->get()['site_chat_context'] ?? ''));

        // Build "fixed context" — everything EXCEPT conversation history.
        $fixed_parts = array(
            'Task: Answer the editor user as an SEO copilot for the current WordPress page.',
            'Output format: {"reply":"...","suggested_title":"...","suggested_description":"...","wants_edits":true/false,"notes":"..."}',
            'Requirements: You receive the COMPLETE page content, ALL metadata fields, full audit results, the page hierarchy (parent, siblings, children with their SEO data), keyphrase conflict warnings, and the site structure tree. Use ALL of it. Differentiate this page from its siblings. Flag cannibalization risks. Ground your advice in what you actually see below.',
            'Site: ' . get_bloginfo('name'),
            'Page type: ' . $post->post_type,
            'Current page title: ' . (string) $post->post_title,
            'Page URL: ' . (string) get_permalink($post),
            $this->format_seo_context_lines($ctx),
            'Existing excerpt: ' . ('' !== $page_excerpt ? $page_excerpt : 'None'),
            "Page content (HTML with headings, images, links, and all elements):\n" . ('' !== $page_html ? $page_html : 'No body content is available.'),
        );

        if ('' !== $site_context) {
            $fixed_parts[] = "Site owner's description of the business and goals:\n" . $site_context;
        }

        if ('' !== $branding_note) {
            $fixed_parts[] = $branding_note;
        }

        // The user's current message is also fixed context (always included).
        $fixed_parts[] = 'User question: ' . $message;

        $fixed_context = implode("\n\n", $fixed_parts);

        // Token-budgeted conversation history.
        $memory = Chat_Memory_Manager::budget_history($recent_messages, $fixed_context, $model_id);

        // Assemble final prompt: fixed context + budgeted history + user question.
        // Insert conversation history before the user question.
        $prompt_parts = array_slice($fixed_parts, 0, -1); // Everything except "User question"

        if (! empty($memory['conversation_lines'])) {
            $prompt_parts[] = "Recent conversation:\n" . implode("\n", $memory['conversation_lines']);
        }

        $prompt_parts[] = 'User question: ' . $message;

        return array(
            'prompt'          => implode("\n\n", $prompt_parts),
            'memory_pressure' => $memory['memory_pressure'],
        );
    }

    /**
     * Public bridge to the provider-specific API calls. Used by Site_Chat.
     */
    public function call_provider(string $provider, string $api_key, string $model, string $system_prompt, string $user_prompt, float $temperature): string
    {
        if ('local' === $provider) {
            return $this->call_local($model, $system_prompt, $user_prompt, $temperature);
        }

        if ('openai' === $provider) {
            return $this->call_openai($api_key, $model, $system_prompt, $user_prompt, $temperature);
        }

        if ('google' === $provider) {
            return $this->call_google($api_key, $model, $system_prompt, $user_prompt, $temperature);
        }

        throw new \RuntimeException('Unsupported AI provider: ' . $provider);
    }

    /**
     * Retry an API call on transient failures (5xx, rate limits).
     *
     * @param callable $make_request Returns wp_remote_post response.
     * @param string   $provider     'openai' or 'google'.
     * @param int      $max_retries  Maximum number of retries (default 2).
     * @return string Extracted response text.
     */
    private function call_with_retry(callable $make_request, string $provider, int $max_retries = 2): string
    {
        $attempt = 0;

        while (true) {
            try {
                $response = $make_request();
                return $this->extract_response_text($response, $provider);
            } catch (RateLimitException $e) {
                if ($attempt >= $max_retries) {
                    throw $e;
                }
                $wait = min($e->get_retry_after(), 10);
                sleep($wait);
            } catch (\RuntimeException $e) {
                // Only retry on server-side (5xx) errors.
                $is_server_error = str_contains(strtolower($e->getMessage()), 'temporarily unavailable')
                    || str_contains(strtolower($e->getMessage()), 'server error');

                if (! $is_server_error || $attempt >= $max_retries) {
                    throw $e;
                }
                sleep(min(2 ** $attempt, 8));
            }

            ++$attempt;
        }
    }

    /**
     * Call the local AI server (LM Studio / Ollama) via Local_AI_Provider.
     *
     * Bridges the Local_AI_Provider array-based response into the string return
     * expected by the rest of AI_Generator.
     */
    private function call_local(string $model, string $system_prompt, string $user_prompt, float $temperature): string
    {
        if (! class_exists('\\AI_SEO_Captain\\Modules\\LocalAI\\Local_AI_Provider')) {
            throw new \RuntimeException('Local AI module is not installed. Place the local-ai module in wp-content/plugins/ai-seo-captain/modules/local-ai/.');
        }

        $provider = \AI_SEO_Captain\Modules\LocalAI\Local_AI_Provider::from_options();

        // Build the initial conversation.
        $messages = array(
            array('role' => 'system', 'content' => $system_prompt),
            array('role' => 'user', 'content' => $user_prompt),
        );

        // ── Smart compression for small context windows ────────────────
        // If the prompt exceeds the model's input budget, progressively
        // compress the HTML content (headings/images/links always preserved).
        // Only activates for Local AI — cloud providers never hit this path.
        if (class_exists('\\AI_SEO_Captain\\Modules\\LocalAI\\Local_AI_Content_Compressor')) {
            $ctx_window = $this->settings->get_context_window($model);
            $fit = \AI_SEO_Captain\Modules\LocalAI\Local_AI_Content_Compressor::fit_messages($messages, $ctx_window);
            $messages = $fit['messages'];

            if ($fit['compressed']) {
                error_log(sprintf(
                    '[SEO Captain] Content compressed (level %d: %s) — %s → %s tokens to fit %s-token window.',
                    $fit['level'],
                    $fit['level_label'],
                    number_format($fit['original_tokens']),
                    number_format($fit['final_tokens']),
                    number_format($fit['context_window'])
                ));
            }

            // Pre-flight check: if even max compression couldn't fit, fail early
            // with a clear error instead of sending a doomed request.
            // Allow 2% tolerance because CHARS_PER_TOKEN is an estimate;
            // a few tokens over the calculated budget won't actually overflow.
            $input_budget = (int) ($ctx_window * 0.6);
            $tolerance = max(50, (int) ($input_budget * 0.02));
            if ($fit['final_tokens'] > $input_budget + $tolerance) {
                $msg = sprintf(
                    'This page needs ~%s tokens but your %s-token context window only fits ~%s tokens of input (after compression level %d: %s). ' .
                        'Increase the Context Window in Local AI settings, or use a model with a larger context.',
                    number_format($fit['final_tokens']),
                    number_format($ctx_window),
                    number_format($input_budget),
                    $fit['level'],
                    $fit['level_label']
                );
                error_log('[SEO Captain] Pre-flight token check failed: ' . $msg);
                throw new \RuntimeException('Local AI error: ' . $msg);
            }
        }

        // ── Multi-batch continuation loop ──────────────────────────────
        // No max_tokens is sent to the API — the model generates freely until
        // it finishes (EOS) or runs out of context window. If the latter
        // happens (finish_reason=length), we send a continuation request and
        // concatenate the fragments. This works with any model size.
        $max_continuations = 10;
        $accumulated       = '';

        for ($round = 0; $round <= $max_continuations; $round++) {
            $result = $provider->chat($messages, $model, $temperature);

            if (empty($result['success'])) {
                $error = $result['error'] ?? 'Local AI request failed.';
                error_log('[SEO Captain] Local AI error: ' . $error);
                throw new \RuntimeException('Local AI error: ' . $error);
            }

            $chunk = $result['content'] ?? '';
            $accumulated .= $chunk;
            $finish_reason = $result['finish_reason'] ?? 'stop';

            // Model finished naturally — we have the complete response.
            if ('length' !== $finish_reason) {
                break;
            }

            // Response was truncated — ask the model to continue.
            // Add the partial response as assistant, then a continue instruction.
            $messages[] = array('role' => 'assistant', 'content' => $chunk);
            $messages[] = array(
                'role'    => 'user',
                'content' => 'Your previous response was cut off at the token limit. '
                    . 'Continue EXACTLY where you stopped — do not repeat anything you already wrote. '
                    . 'Do not add any preamble like "Certainly" or "Here is the rest". '
                    . 'Just output the remaining content starting from the exact cut-off point.',
            );

            error_log(sprintf(
                '[SEO Captain] Local AI continuation round %d — finish_reason=length, accumulated %d chars so far.',
                $round + 1,
                strlen($accumulated)
            ));
        }

        if ('' === trim($accumulated)) {
            throw new \RuntimeException('The local AI model returned an empty response. Check that your model is loaded in LM Studio.');
        }

        return $accumulated;
    }

    private function call_openai(string $api_key, string $model, string $system_prompt, string $user_prompt, float $temperature): string
    {
        $payload = array(
            'model' => '' !== $model ? $model : 'gpt-4.1-mini',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $system_prompt,
                ),
                array(
                    'role' => 'user',
                    'content' => $user_prompt,
                ),
            ),
        );

        // OpenAI o-series models only support their default temperature behavior.
        if ($this->supports_openai_custom_temperature($model)) {
            $payload['temperature'] = $this->normalize_temperature($temperature);
        }

        $request_args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . trim($api_key),
                'Content-Type' => 'application/json',
            ),
            'timeout' => 60,
            'body' => wp_json_encode($payload),
        );

        return $this->call_with_retry(
            static function () use ($request_args) {
                return wp_remote_post('https://api.openai.com/v1/chat/completions', $request_args);
            },
            'openai'
        );
    }

    public function test_model_connection(string $provider, string $api_key, string $model, float $temperature = 0.3): array
    {
        $provider = sanitize_key($provider);
        $model = trim(sanitize_text_field($model));
        $api_key = trim($api_key);
        $temperature = $this->normalize_temperature($temperature);

        if ('local' !== $provider && '' === $api_key) {
            throw new \RuntimeException('API key is required to test model availability.');
        }

        $system_prompt = 'You are a connectivity test assistant. Return short plain text only.';
        $user_prompt = 'Reply with exactly: OK';

        if ('local' === $provider) {
            $content = $this->call_local($model, $system_prompt, $user_prompt, $temperature);
        } elseif ('openai' === $provider) {
            $content = $this->call_openai($api_key, $model, $system_prompt, $user_prompt, $temperature);
        } elseif ('google' === $provider) {
            $content = $this->call_google($api_key, $model, $system_prompt, $user_prompt, $temperature);
        } else {
            throw new \RuntimeException('Unsupported AI provider configured.');
        }

        $preview = trim(preg_replace('/\s+/', ' ', sanitize_text_field($content)) ?? '');
        if (function_exists('mb_substr')) {
            $preview = mb_substr($preview, 0, 80);
        } else {
            $preview = substr($preview, 0, 80);
        }

        return array(
            'provider' => $provider,
            'model' => $model,
            'preview' => $preview,
        );
    }

    private function call_google(string $api_key, string $model, string $system_prompt, string $user_prompt, float $temperature): string
    {
        $effective_model = '' !== $model ? $model : 'gemini-2.0-flash';
        $temperature = $this->normalize_temperature($temperature);
        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            rawurlencode($effective_model)
        );

        $request_args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-goog-api-key' => trim($api_key),
            ),
            'timeout' => 60,
            'body' => wp_json_encode(
                array(
                    'systemInstruction' => array(
                        'parts' => array(
                            array(
                                'text' => $system_prompt,
                            ),
                        ),
                    ),
                    'contents' => array(
                        array(
                            'role' => 'user',
                            'parts' => array(
                                array(
                                    'text' => $user_prompt,
                                ),
                            ),
                        ),
                    ),
                    'generationConfig' => array(
                        'temperature' => $temperature,
                        'responseMimeType' => 'application/json',
                    ),
                )
            ),
        );

        return $this->call_with_retry(
            static function () use ($url, $request_args) {
                return wp_remote_post($url, $request_args);
            },
            'google'
        );
    }

    /**
     * @param array|\WP_Error $response
     */
    private function extract_response_text($response, string $provider): string
    {
        if (is_wp_error($response)) {
            // Log raw error for debugging, show safe message to user.
            error_log('[SEO Captain] ' . ucfirst($provider) . ' API connection error: ' . $response->get_error_message());
            throw new \RuntimeException(
                'Could not connect to the AI service. Please check your internet connection and try again.'
            );
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if (429 === $status_code) {
            $retry_after = (int) wp_remote_retrieve_header($response, 'retry-after');
            if ($retry_after <= 0) {
                $retry_after = 5;
            }
            throw new RateLimitException(
                sprintf('The %s API rate limit was reached. Retry after %d seconds.', ucfirst($provider), $retry_after),
                $retry_after
            );
        }

        if ($status_code < 200 || $status_code >= 300) {
            $raw_message = $this->extract_error_message($decoded);
            // Log the raw API error for admin debugging.
            error_log('[SEO Captain] ' . ucfirst($provider) . ' API error (HTTP ' . $status_code . '): ' . ($raw_message ?: $body));
            throw new \RuntimeException($this->humanize_api_error($provider, $status_code, $raw_message));
        }

        if ('openai' === $provider || 'local' === $provider) {
            $content = $decoded['choices'][0]['message']['content'] ?? '';
        } else {
            $content = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        }

        if (! is_string($content) || '' === trim($content)) {
            error_log('[SEO Captain] ' . ucfirst($provider) . ' API returned empty content. HTTP ' . $status_code . '. Body: ' . substr($body, 0, 500));
            throw new \RuntimeException('The AI returned an empty response. Please try again or switch to a different model.');
        }

        return $content;
    }

    /**
     * Convert raw API error details into a user-friendly message.
     * Keeps technical details out of the UI while remaining actionable.
     */
    private function humanize_api_error(string $provider, int $status_code, string $raw_message): string
    {
        $lower = strtolower($raw_message);

        // Authentication / key issues.
        if (401 === $status_code || str_contains($lower, 'invalid_api_key') || str_contains($lower, 'api key not valid') || str_contains($lower, 'incorrect api key')) {
            return 'Your ' . ucfirst($provider) . ' API key is invalid or expired. Please update it in SEO Captain Settings.';
        }

        // Quota / billing.
        if (str_contains($lower, 'quota') || str_contains($lower, 'billing') || str_contains($lower, 'insufficient_quota')) {
            return 'Your ' . ucfirst($provider) . ' API quota has been exceeded. Check your plan and billing at your provider dashboard.';
        }

        // Context length exceeded.
        if (str_contains($lower, 'context length') || str_contains($lower, 'maximum context') || str_contains($lower, 'too many tokens') || str_contains($lower, 'content too large') || str_contains($lower, 'token limit')) {
            return 'The page content exceeds this model\'s capacity. Try a model with a larger context window, or use a shorter page.';
        }

        // Model not found / deprecated.
        if (404 === $status_code || str_contains($lower, 'model not found') || str_contains($lower, 'does not exist')) {
            return 'The selected AI model is not available. It may have been deprecated. Please choose a different model in Settings.';
        }

        // Permission denied.
        if (403 === $status_code || str_contains($lower, 'permission') || str_contains($lower, 'forbidden')) {
            return 'Your API key does not have permission to use the selected model. Check your ' . ucfirst($provider) . ' account access.';
        }

        // Server-side errors.
        if ($status_code >= 500) {
            return 'The ' . ucfirst($provider) . ' API is temporarily unavailable (server error). Please try again in a moment.';
        }

        // Fallback — generic but safe.
        return 'The AI service returned an error (HTTP ' . $status_code . '). Please try again or check your API configuration in Settings.';
    }

    /**
     * @param array|null $decoded
     */
    private function extract_error_message($decoded): string
    {
        if (! is_array($decoded)) {
            return '';
        }

        if (isset($decoded['error']['message']) && is_string($decoded['error']['message'])) {
            return $decoded['error']['message'];
        }

        if (isset($decoded['error']['status']) && is_string($decoded['error']['status'])) {
            return $decoded['error']['status'];
        }

        return '';
    }

    /**
     * Call the AI provider and decode the JSON response, retrying once on
     * parse failure. LLMs under sustained batch load sometimes produce
     * truncated or malformed JSON randomly; a single retry typically
     * succeeds because the randomness means the next response is different.
     *
     * @param callable $call_fn Zero-argument callable that returns the raw AI response string.
     * @param string   $context Optional label for log messages (e.g. "page 358").
     * @return array Decoded JSON payload.
     * @throws \RuntimeException If both attempts fail.
     */
    private function call_ai_and_decode(callable $call_fn, string $context = ''): array
    {
        $raw_response = $call_fn();
        try {
            return $this->decode_json_payload($raw_response);
        } catch (\RuntimeException $e) {
            if (false === strpos($e->getMessage(), 'not valid JSON')) {
                throw $e;
            }
            $label = $context ? " ($context)" : '';
            error_log('[SEO Captain] JSON parse failed' . $label . ' — retrying once.');
            $raw_response = $call_fn();
            return $this->decode_json_payload($raw_response);
        }
    }

    private function decode_json_payload(string $content): array
    {
        $normalized = trim($content);

        // Strip markdown code fences wrapping the JSON.
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $normalized, $matches)) {
            $normalized = $matches[1];
        } else {
            // Just start from the first '{'.
            // IMPORTANT: we do NOT use strrpos('}') here because for
            // truncated responses the last '}' is often inside a string
            // value (CSS, code, markdown), which corrupts the extraction.
            $start = strpos($normalized, '{');
            if (false !== $start) {
                $normalized = substr($normalized, $start);
            }
        }

        // Fix unescaped control characters inside JSON string values.
        // LLMs often emit literal newlines/tabs in multi-line fields like
        // full_report (Markdown content). Use a character scanner instead
        // of regex to avoid backtrack limits on large strings.
        $normalized = $this->escape_json_strings($normalized);

        // Now find the real JSON boundary using structure-aware scanning.
        // This tracks brace depth + string boundaries, unlike strrpos('}').
        $extracted = $this->extract_json_object_safe($normalized);
        if (null !== $extracted) {
            $normalized = $extracted;
        }

        // Fix common LLM JSON issues: trailing commas before } or ].
        $normalized = preg_replace('/,\s*([\]}])/s', '$1', $normalized) ?? $normalized;

        $decoded = json_decode($normalized, true);

        // If still failing, try repairing truncated JSON.
        // LLMs with limited output tokens often produce valid JSON that is
        // cut off mid-string or mid-array. We close open structures to
        // salvage as much data as possible.
        if (! is_array($decoded)) {
            $repaired = $this->repair_truncated_json($normalized);
            if (null !== $repaired) {
                $decoded = json_decode($repaired, true);
            }
        }

        if (! is_array($decoded)) {
            // Log the raw AND escaped response for debugging.
            $len = strlen($content);
            $preview_start = substr($content, 0, 300);
            $preview_end   = substr($content, -300);
            $json_error = json_last_error_msg();
            error_log('[SEO Captain] JSON decode failed: ' . $json_error . ' — Length: ' . $len);
            error_log('[SEO Captain] JSON START (raw): ' . $preview_start);
            error_log('[SEO Captain] JSON END (raw): ' . $preview_end);
            error_log('[SEO Captain] JSON END (escaped): ' . substr($normalized, -300));
            throw new \RuntimeException('The AI response was not valid JSON. (' . $json_error . ')');
        }

        return $decoded;
    }

    private function normalize_text(string $text): string
    {
        $text = strip_shortcodes($text);
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\s+/', ' ', $text) ?: $text;

        return trim($text);
    }

    private function truncate_text(string $text, int $limit): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $limit);
        }

        return substr($text, 0, $limit);
    }

    /**
     * Escape literal control characters inside JSON string values.
     *
     * Uses a character-by-character scanner (no regex) to reliably handle
     * large strings that would hit PCRE backtrack limits.
     *
     * @param string $json Raw JSON text that may contain literal newlines inside strings.
     * @return string Fixed JSON with control characters properly escaped.
     */
    private function escape_json_strings(string $json): string
    {
        $len       = strlen($json);
        $out       = '';
        $in_string = false;
        $i         = 0;

        while ($i < $len) {
            $c = $json[$i];

            if (!$in_string) {
                $out .= $c;
                if ('"' === $c) {
                    $in_string = true;
                }
                $i++;
                continue;
            }

            // Inside a JSON string value.
            if ('\\' === $c && $i + 1 < $len) {
                $next_char = $json[$i + 1];

                // Valid JSON escape sequences: \", \\, \/, \b, \f, \n, \r, \t, \uXXXX.
                if (in_array($next_char, array('"', '\\', '/', 'b', 'f', 'n', 'r', 't', 'u'), true)) {
                    $out .= $c . $next_char;
                    $i += 2;
                    continue;
                }

                // Invalid escape (e.g. \* \_ \[ from Markdown).
                // Double the backslash so json_decode sees \\ + char.
                $out .= '\\\\' . $next_char;
                $i += 2;
                continue;
            }

            if ('"' === $c) {
                // Check if this is the real end of the string or an
                // unescaped quote in the content. LLMs often forget to
                // escape quotes in natural language (e.g. "Contact us").
                // Heuristic: after a real closing quote, the next
                // non-whitespace char must be a JSON structural char.
                $next_nws = '';
                for ($j = $i + 1; $j < $len; $j++) {
                    if (!ctype_space($json[$j])) {
                        $next_nws = $json[$j];
                        break;
                    }
                }
                if ('' !== $next_nws && !in_array($next_nws, array(',', '}', ']', ':'), true)) {
                    // Not a structural char — this quote is content.
                    $out .= '\\"';
                    $i++;
                    continue;
                }

                // Real end of string.
                $out .= $c;
                $in_string = false;
                $i++;
                continue;
            }

            // Replace literal control characters with escape sequences.
            $ord = ord($c);
            if ($ord < 32) {
                switch ($c) {
                    case "\n":
                        $out .= '\\n';
                        break;
                    case "\r":
                        // Skip \r if followed by \n (will be caught as \n next).
                        if ($i + 1 < $len && "\n" === $json[$i + 1]) {
                            $i++;
                            $out .= '\\n';
                        } else {
                            $out .= '\\n';
                        }
                        break;
                    case "\t":
                        $out .= '\\t';
                        break;
                    default:
                        // Other control chars: unicode escape.
                        $out .= sprintf('\\u%04x', $ord);
                        break;
                }
                $i++;
                continue;
            }

            $out .= $c;
            $i++;
        }

        return $out;
    }

    /**
     * Structure-aware extraction of the outermost JSON object.
     *
     * Unlike strrpos('}'), this tracks brace depth and string boundaries
     * to find the REAL closing '}'. Must be called AFTER escape_json_strings()
     * so that string delimiters are reliable.
     *
     * @param string $text Escaped text that may contain a JSON object.
     * @return string|null The extracted JSON, or the substring from '{' to end if unclosed (truncated).
     */
    private function extract_json_object_safe(string $text): ?string
    {
        $start = strpos($text, '{');

        if (false === $start) {
            return null;
        }

        $depth     = 0;
        $in_string = false;
        $len       = strlen($text);

        for ($i = $start; $i < $len; $i++) {
            $c = $text[$i];

            if ($in_string) {
                if ('\\' === $c && $i + 1 < $len) {
                    $i++; // skip escaped character
                    continue;
                }
                if ('"' === $c) {
                    $in_string = false;
                }
                continue;
            }

            if ('"' === $c) {
                $in_string = true;
            } elseif ('{' === $c) {
                $depth++;
            } elseif ('}' === $c) {
                $depth--;
                if (0 === $depth) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        // JSON not closed (truncated) — return everything from '{' to end.
        return substr($text, $start);
    }

    /**
     * Attempt to repair truncated JSON from LLMs.
     *
     * When a model runs out of output tokens mid-response, it produces
     * structurally valid JSON that is simply cut off (e.g. an open string
     * or unclosed arrays/objects). This method tries to close all open
     * structures so json_decode() can salvage the successfully generated fields.
     *
     * @param string $json The broken JSON string.
     * @return string|null Repaired JSON, or null if repair wasn't possible.
     */
    private function repair_truncated_json(string $json): ?string
    {
        $json = trim($json);

        // Must start with {.
        if ('' === $json || '{' !== $json[0]) {
            return null;
        }

        // Note: escape_json_strings() has already been called on the input,
        // so literal newlines/tabs inside strings are already fixed.

        // Walk the string to find the deepest valid parse point.
        // Track nesting: { } [ ] and whether we're inside a string.
        $in_string = false;
        $escape    = false;
        $stack     = array(); // tracks { and [
        $last_safe = 0;      // last position after a complete value

        for ($i = 0, $len = strlen($json); $i < $len; $i++) {
            $c = $json[$i];

            if ($escape) {
                $escape = false;
                continue;
            }

            if ($in_string) {
                if ('\\' === $c) {
                    $escape = true;
                } elseif ('"' === $c) {
                    $in_string = false;
                    $last_safe = $i + 1;
                }
                continue;
            }

            switch ($c) {
                case '"':
                    $in_string = true;
                    break;
                case '{':
                case '[':
                    $stack[] = $c;
                    break;
                case '}':
                    array_pop($stack);
                    $last_safe = $i + 1;
                    break;
                case ']':
                    array_pop($stack);
                    $last_safe = $i + 1;
                    break;
                case ',':
                case ':':
                    break;
                default:
                    // Literals (numbers, true, false, null).
                    if (!ctype_space($c)) {
                        $last_safe = $i + 1;
                    }
                    break;
            }
        }

        // If we're inside a string, close it.
        $repaired = $json;
        if ($in_string) {
            // Truncate to last safe point if there's enough data,
            // otherwise just close the string.
            $repaired .= ' [truncated]"';
        }

        // Remove any trailing comma.
        $repaired = rtrim($repaired);
        $repaired = preg_replace('/,\s*$/', '', $repaired) ?? $repaired;

        // Close all open structures.
        // Re-scan to get current stack state.
        $in_string = false;
        $escape = false;
        $stack = array();
        for ($i = 0, $len = strlen($repaired); $i < $len; $i++) {
            $c = $repaired[$i];
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($in_string) {
                if ('\\' === $c) {
                    $escape = true;
                } elseif ('"' === $c) {
                    $in_string = false;
                }
                continue;
            }
            if ('"' === $c) {
                $in_string = true;
            } elseif ('{' === $c || '[' === $c) {
                $stack[] = $c;
            } elseif ('}' === $c || ']' === $c) {
                array_pop($stack);
            }
        }

        // Close remaining open structures in reverse order.
        while (!empty($stack)) {
            $opener = array_pop($stack);
            $repaired .= ('{' === $opener) ? '}' : ']';
        }

        // Validate the repair worked.
        $test = json_decode($repaired, true);
        if (is_array($test)) {
            error_log('[SEO Captain] Repaired truncated JSON — salvaged ' . count($test) . ' top-level fields.');
            return $repaired;
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    private function sanitize_string_list($value, int $limit): array
    {
        if (! is_array($value)) {
            return array();
        }

        $items = array();

        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }

            $sanitized = sanitize_text_field($item);

            if ('' === $sanitized) {
                continue;
            }

            $items[] = $sanitized;

            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    public function generate_page_audit(int $post_id, bool $deep_analysis = false): array
    {
        $post = get_post($post_id);

        if (! $post instanceof \WP_Post) {
            throw new \RuntimeException('The requested page could not be loaded.');
        }

        $options = $this->settings->get();

        $provider = (string) $options['provider'];

        if ('local' !== $provider && empty($options['api_key'])) {
            throw new \RuntimeException('Add an API key in SEO Captain Settings before generating page audits.');
        }

        $model = $this->resolve_model($options);
        $temperature = $this->get_effective_temperature($options);
        $system_prompt = $this->build_page_audit_system_prompt((string) $options['system_prompt']);
        $user_prompt = $this->build_page_audit_user_prompt($post, $deep_analysis);

        $call_fn = function () use ($provider, $options, $model, $system_prompt, $user_prompt, $temperature) {
            if ('local' === $provider) {
                return $this->call_local($model, $system_prompt, $user_prompt, $temperature);
            } elseif ('openai' === $provider) {
                return $this->call_openai($options['api_key'], $model, $system_prompt, $user_prompt, $temperature);
            } else {
                return $this->call_google($options['api_key'], $model, $system_prompt, $user_prompt, $temperature);
            }
        };

        $payload = $this->call_ai_and_decode($call_fn, 'page audit #' . $post_id);

        return array(
            'score' => isset($payload['score']) ? max(0, min(100, (int) $payload['score'])) : 0,
            'issues' => $this->sanitize_string_list($payload['issues'] ?? array(), 10),
            'suggestions' => $this->sanitize_string_list($payload['suggestions'] ?? array(), 10),
            'missing_alt_tags' => isset($payload['missing_alt_tags']) ? (int) $payload['missing_alt_tags'] : 0,
            'word_count' => isset($payload['word_count']) ? (int) $payload['word_count'] : 0,
            'heading_structure' => isset($payload['heading_structure']) ? sanitize_text_field((string) $payload['heading_structure']) : '',
            'summary' => isset($payload['summary']) ? sanitize_textarea_field((string) $payload['summary']) : '',
            'full_report' => isset($payload['full_report']) ? wp_kses_post((string) $payload['full_report']) : '',
            'provider' => $provider,
            'model' => $model,
        );
    }

    public function generate_content_changes(int $post_id, string $instruction, array $recent_messages = array()): array
    {
        $post = get_post($post_id);

        if (! $post instanceof \WP_Post) {
            throw new \RuntimeException('The requested page could not be loaded.');
        }

        $options = $this->settings->get();

        $provider = (string) $options['provider'];

        if ('local' !== $provider && empty($options['api_key'])) {
            throw new \RuntimeException('Add an API key in SEO Captain Settings before requesting content changes.');
        }

        $model = $this->resolve_model($options);
        $temperature = $this->get_effective_temperature($options);
        $system_prompt = $this->build_content_edit_system_prompt((string) $options['system_prompt']);
        $user_prompt = $this->build_content_edit_user_prompt($post, $instruction, $recent_messages);

        if ('local' === $provider) {
            $raw_response = $this->call_local($model, $system_prompt, $user_prompt, $temperature);
        } elseif ('openai' === $provider) {
            $raw_response = $this->call_openai($options['api_key'], $model, $system_prompt, $user_prompt, $temperature);
        } elseif ('google' === $provider) {
            $raw_response = $this->call_google($options['api_key'], $model, $system_prompt, $user_prompt, $temperature);
        } else {
            throw new \RuntimeException('Unsupported AI provider configured.');
        }

        $payload = $this->decode_json_payload($raw_response);

        $changes = array();
        $raw_changes = isset($payload['changes']) && is_array($payload['changes']) ? $payload['changes'] : array();
        foreach ($raw_changes as $idx => $ch) {
            if (! is_array($ch) || empty($ch['old']) || empty($ch['new'])) {
                continue;
            }
            $changes[] = array(
                'id' => $idx,
                'section' => isset($ch['section']) ? sanitize_text_field((string) $ch['section']) : 'Section ' . ($idx + 1),
                'old' => (string) $ch['old'],
                'new' => (string) $ch['new'],
                'reason' => isset($ch['reason']) ? sanitize_text_field((string) $ch['reason']) : '',
                'tag_change' => isset($ch['tag_change']) ? sanitize_text_field((string) $ch['tag_change']) : '',
            );
        }

        $summary = isset($payload['summary']) ? sanitize_textarea_field((string) $payload['summary']) : '';

        if (empty($changes)) {
            throw new \RuntimeException('The AI did not propose any content changes. The page may already be well optimized.');
        }

        return array(
            'changes' => $changes,
            'summary' => $summary,
            'provider' => $provider,
            'model' => $model,
        );
    }

    private function build_content_edit_system_prompt(string $custom_prompt): string
    {
        $base_prompt = trim($custom_prompt);

        return trim(
            $base_prompt . "\n\n" .
                'You are an SEO content editor. Return only valid JSON with exactly these keys: changes, summary. ' .
                'changes is an array of objects, each with: section (string label), old (exact original text), new (replacement text), reason (why this improves SEO), tag_change (e.g. "h3→h2" or empty string). ' .
                'CRITICAL RULES: ' .
                '1. The "old" field must contain the EXACT original text as it appears in the page content — character for character. ' .
                '2. Only rephrase text and fix heading hierarchy (H tags). ' .
                '3. NEVER remove or add buttons, images, forms, iframes, scripts, shortcodes, or widgets. ' .
                '4. NEVER change href URLs — you may rephrase anchor text only. ' .
                '5. Preserve all HTML attributes: classes, IDs, styles, data-* attributes. ' .
                '6. Keep HTML tags (bold, italic, links) in the output — only change the text content inside them. ' .
                '7. Each change must have a clear SEO reason. ' .
                '8. Maximum 30 changes per request. ' .
                '9. summary is 1-2 sentences about what was improved overall. ' .
                'Do not use markdown fences.'
        );
    }

    private function build_content_edit_user_prompt(\WP_Post $post, string $instruction, array $recent_messages = array()): string
    {
        $ctx = $this->get_seo_context($post);
        $page_content = Content_Helper::sanitize_for_ai(Content_Helper::get_content($post));

        $conversation_lines = array();
        foreach ($recent_messages as $recent_message) {
            if (! is_array($recent_message)) {
                continue;
            }

            $role = isset($recent_message['role']) ? (string) $recent_message['role'] : '';
            $content = 'user' === $role
                ? (string) ($recent_message['message'] ?? '')
                : (string) ($recent_message['reply'] ?? '');

            if ('' === trim($content)) {
                continue;
            }

            $conversation_lines[] = strtoupper($role) . ': ' . $content;
        }

        $parts = array(
            'Task: Analyze the page content and propose specific text changes to improve SEO.',
            'Output format: {"changes":[{"section":"...","old":"...","new":"...","reason":"...","tag_change":"..."}],"summary":"..."}',
            'Site: ' . get_bloginfo('name'),
            'Page type: ' . $post->post_type,
            'Page title: ' . (string) $post->post_title,
            'Page URL: ' . (string) get_permalink($post),
            $this->format_seo_context_lines($ctx),
        );

        if (! empty($conversation_lines)) {
            $parts[] = "Recent conversation context:\n" . implode("\n", $conversation_lines);
        }

        $parts[] = 'User instruction: ' . $instruction;
        $parts[] = "Full page content:\n" . ('' !== $page_content ? $page_content : 'No body content is available.');

        return implode("\n\n", $parts);
    }

    private function build_page_audit_system_prompt(string $custom_prompt): string
    {
        $base_prompt = trim($custom_prompt);

        return trim(
            $base_prompt . "\n\n" .
                'IDENTITY: You are the AI inside the "SEO Captain" WordPress plugin. This plugin handles ALL SEO. The user does NOT use Yoast, RankMath, or any other SEO plugin — never mention them.' . "\n" .
                'Return only valid JSON with exactly these keys: score, issues, suggestions, missing_alt_tags, word_count, heading_structure, summary, full_report. ' .
                'score is 0-100 representing overall SEO health. ' .
                'issues is an array of short strings describing problems found (max 10). ' .
                'suggestions is an array of short strings with actionable improvements (max 10). ' .
                'missing_alt_tags is the count of images without alt text. ' .
                'word_count is the word count of the main content. ' .
                'heading_structure is a brief note about heading hierarchy (e.g. "H1: 1, H2: 3, H3: 2 — good structure"). ' .
                'summary is 1-2 sentences about overall page SEO quality. ' .
                'full_report is the COMPLETE detailed audit report in Markdown format. This is the most important output — it must be comprehensive, specific, and actionable. Include: ' .
                '(A) Executive summary of the page SEO health; ' .
                '(B) Every issue found with a clear explanation of WHY it hurts SEO and HOW to fix it; ' .
                '(C) Content analysis: heading structure assessment, keyword density, readability, thin/duplicate content risks; ' .
                '(D) Media audit: list every image/video/document with missing alt text or SEO issues — specify which elements need fixing; ' .
                '(E) Internal/external link analysis: missing opportunities, broken patterns, nofollow recommendations; ' .
                '(F) Metadata assessment: title length, description quality, keyphrase placement, social tags, schema; ' .
                '(G) Cannibalization risks: if sibling or related pages overlap in topic/keyphrase, explain the conflict and recommend differentiation; ' .
                '(H) Prioritized action list: numbered steps the user should take, ordered by impact, each with the specific text/element to change. ' .
                'Do NOT summarize or abbreviate the full_report — include every finding with full context. ' .
                'Do not use markdown fences around the JSON. Be specific and factual.'
        );
    }

    private function build_page_audit_user_prompt(\WP_Post $post, bool $deep_analysis = false): string
    {
        $ctx = $this->get_seo_context($post, $deep_analysis ? array('deep_analysis' => true) : array());
        $site_context = trim((string) ($this->settings->get()['site_chat_context'] ?? ''));

        $page_content_raw = Content_Helper::get_content($post);
        // Send FULL content to AI for audit — no truncation.
        // Sanitized HTML keeps structure (headings, images, links, videos in position)
        // while stripping builder wrappers, styles, scripts, and non-semantic markup.
        $page_content = Content_Helper::sanitize_for_ai($page_content_raw);

        $img_count = preg_match_all('/<img\b/i', $page_content_raw);
        $img_no_alt = preg_match_all('/<img(?![^>]*\balt\s*=\s*"[^"]+")[^>]*>/i', $page_content_raw);
        $heading_matches = array();
        preg_match_all('/<h([1-6])\b/i', $page_content_raw, $heading_matches);
        $heading_summary = '';
        if (! empty($heading_matches[1])) {
            $counts = array_count_values($heading_matches[1]);
            ksort($counts);
            $parts = array();
            foreach ($counts as $level => $count) {
                $parts[] = 'H' . $level . ': ' . $count;
            }
            $heading_summary = implode(', ', $parts);
        }

        $word_count = str_word_count($this->normalize_text($page_content_raw));
        $internal_links = preg_match_all('/href=["\']' . preg_quote(home_url(), '/') . '/i', $page_content_raw);
        $external_links_total = preg_match_all('/href=["\'](https?:\/\/)/i', $page_content_raw);
        $external_links = max(0, $external_links_total - $internal_links);

        // Count embedded videos.
        $video_count = preg_match_all('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/|vimeo\.com\/(?:video\/)?\d)/i', $page_content_raw);
        $video_count += preg_match_all('/<video\b/i', $page_content_raw);

        // Count linked documents.
        $doc_count = preg_match_all('/href=["\'][^"\']*\.(?:pdf|docx?|xlsx?|pptx?|odt|ods|odp|csv|rtf)["\s>]/i', $page_content_raw);

        $prompt_parts = array(
            'Task: Perform a comprehensive SEO audit of this WordPress page and provide specific, actionable findings.',
            'Output format: {"score":...,"issues":[...],"suggestions":[...],"missing_alt_tags":...,"word_count":...,"heading_structure":"...","summary":"...","full_report":"..."}',
            'Requirements: You receive the COMPLETE page content, ALL metadata fields, full audit results, the page hierarchy (parent, siblings, children with their SEO data), keyphrase conflict warnings, and the site structure tree. Use ALL of it. Detect cannibalization risks. Flag pages that overlap with this page. Ground your audit in the actual data below.',
            'Site: ' . get_bloginfo('name'),
            'Page type: ' . $post->post_type,
            'Page title: ' . (string) $post->post_title,
            'Page URL: ' . (string) get_permalink($post),
            'Word count: ' . $word_count,
            'Images total: ' . $img_count . ', Images missing alt text: ' . $img_no_alt,
            'Videos embedded: ' . $video_count,
            'Documents linked: ' . $doc_count,
            'Heading structure found: ' . ('' !== $heading_summary ? $heading_summary : 'No headings found'),
            'Internal links: ' . $internal_links . ', External links: ' . $external_links,
            $this->format_seo_context_lines($ctx),
            'Main page content: ' . ('' !== $page_content ? $page_content : 'No body content is available.'),
        );

        if ('' !== $site_context) {
            $prompt_parts[] = "Site owner's description of the business and goals:\n" . $site_context;
        }

        return implode("\n\n", $prompt_parts);
    }

    private function format_duplicate_prompt_lines(array $groups): string
    {
        if (empty($groups)) {
            return '- None';
        }

        $lines = array();

        foreach ($groups as $group) {
            $lines[] = sprintf(
                '- %s (%d pages)',
                isset($group['value']) ? (string) $group['value'] : '(empty)',
                isset($group['count']) ? (int) $group['count'] : 0
            );
        }

        return implode("\n", $lines);
    }

    private function format_thin_content_prompt_lines(array $rows): string
    {
        if (empty($rows)) {
            return '- None';
        }

        $lines = array();

        foreach ($rows as $row) {
            $lines[] = sprintf(
                '- %s | %s words',
                isset($row['title']) ? (string) $row['title'] : '(untitled)',
                isset($row['word_count']) ? (int) $row['word_count'] : 0
            );
        }

        return implode("\n", $lines);
    }

    private function get_effective_temperature(array $options): float
    {
        $raw = $options['ai_temperature'] ?? 0.3;

        return $this->normalize_temperature((float) $raw);
    }

    private function normalize_temperature(float $temperature): float
    {
        return round(max(0.0, min(2.0, $temperature)), 1);
    }

    private function supports_openai_custom_temperature(string $model): bool
    {
        $model = strtolower(trim($model));

        return ! preg_match('/^o[1-9]/', $model);
    }
}
