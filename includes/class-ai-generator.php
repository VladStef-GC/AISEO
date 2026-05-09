<?php

namespace AI_SEO_Keeper;

require_once __DIR__ . '/class-settings.php';
require_once __DIR__ . '/class-content-indexer.php';

class AI_Generator
{
    private Settings $settings;

    private Content_Indexer $content_indexer;

    public function __construct(Settings $settings, Content_Indexer $content_indexer)
    {
        $this->settings = $settings;
        $this->content_indexer = $content_indexer;
    }

    public function generate_for_post(int $post_id, array $field_overrides = array()): array
    {
        $post = get_post($post_id);

        if (! $post instanceof \WP_Post) {
            throw new \RuntimeException('The requested page could not be loaded.');
        }

        $options = $this->settings->get();

        if (empty($options['api_key'])) {
            throw new \RuntimeException('Add an API key in AI SEO Keeper Settings before generating suggestions.');
        }

        $provider = (string) $options['provider'];
        $model = trim((string) $options['model']);
        $temperature = $this->get_effective_temperature($options);
        $system_prompt = $this->build_system_prompt((string) $options['system_prompt']);
        $user_prompt = $this->build_user_prompt($post, $field_overrides);

        if ('openai' === $provider) {
            $raw_response = $this->call_openai($options['api_key'], $model, $system_prompt, $user_prompt, $temperature);
        } elseif ('google' === $provider) {
            $raw_response = $this->call_google($options['api_key'], $model, $system_prompt, $user_prompt, $temperature);
        } else {
            throw new \RuntimeException('Unsupported AI provider configured.');
        }

        $payload = $this->decode_json_payload($raw_response);

        $seo_title = isset($payload['seo_title']) ? sanitize_text_field((string) $payload['seo_title']) : '';
        $meta_description = isset($payload['meta_description']) ? sanitize_textarea_field((string) $payload['meta_description']) : '';
        $focus_keyphrase = isset($payload['focus_keyphrase']) ? sanitize_text_field((string) $payload['focus_keyphrase']) : '';
        $notes = isset($payload['notes']) ? sanitize_textarea_field((string) $payload['notes']) : '';

        if ('' === $seo_title || '' === $meta_description) {
            throw new \RuntimeException('The AI response did not include a usable SEO title and meta description.');
        }

        return array(
            'seo_title' => $seo_title,
            'meta_description' => $meta_description,
            'focus_keyphrase' => $focus_keyphrase,
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

        if (empty($options['api_key'])) {
            throw new \RuntimeException('Add an API key in AI SEO Keeper Settings before generating AI site audits.');
        }

        $provider = (string) $options['provider'];
        $model = trim((string) $options['model']);
        $temperature = $this->get_effective_temperature($options);
        $system_prompt = $this->build_site_audit_system_prompt((string) $options['system_prompt']);
        $user_prompt = $this->build_site_audit_user_prompt($report);

        if ('openai' === $provider) {
            $raw_response = $this->call_openai($options['api_key'], $model, $system_prompt, $user_prompt, $temperature);
        } elseif ('google' === $provider) {
            $raw_response = $this->call_google($options['api_key'], $model, $system_prompt, $user_prompt, $temperature);
        } else {
            throw new \RuntimeException('Unsupported AI provider configured.');
        }

        $payload = $this->decode_json_payload($raw_response);
        $audit_title = isset($payload['audit_title']) ? sanitize_text_field((string) $payload['audit_title']) : 'AI SEO Keeper Site Audit';
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

    public function chat_for_post(int $post_id, string $message, array $recent_messages = array()): array
    {
        $post = get_post($post_id);

        if (! $post instanceof \WP_Post) {
            throw new \RuntimeException('The requested page could not be loaded for AI chat.');
        }

        $message = trim($message);

        if ('' === $message) {
            throw new \RuntimeException('Enter a message before asking the AI assistant.');
        }

        $options = $this->settings->get();

        if (empty($options['api_key'])) {
            throw new \RuntimeException('Add an API key in AI SEO Keeper Settings before using the AI assistant.');
        }

        $provider = (string) $options['provider'];
        $model = trim((string) $options['model']);
        $temperature = $this->get_effective_temperature($options);
        $system_prompt = $this->build_chat_system_prompt((string) $options['system_prompt']);
        $user_prompt = $this->build_chat_user_prompt($post, $message, $recent_messages);

        if ('openai' === $provider) {
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
            throw new \RuntimeException('The AI assistant did not return a usable reply.');
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
                'Return only valid JSON with exactly these keys: seo_title, meta_description, focus_keyphrase, notes. ' .
                'Do not use markdown fences. Keep the title at or under 60 characters (total including branding). ' .
                'Keep the meta description at or under 155 characters, ideally around 140-155. ' .
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
                'Return only valid JSON with exactly these keys: reply, suggested_title, suggested_description, wants_edits, notes. ' .
                'reply should answer the user directly and briefly. ' .
                'Only fill suggested_title and suggested_description when the user is EXPLICITLY asking for metadata changes or new suggestions. ' .
                'wants_edits must be true when the user is asking you to edit, fix, improve, rewrite, or change the actual page content (headings, paragraphs, text). Set it false for questions, advice, or metadata-only requests. ' .
                'CRITICAL ANTI-BIAS RULES: ' .
                '1. If the current SEO title and meta description are already strong (metadata fit score ≥ 75 or audit score ≥ 70), do NOT suggest replacements unless the user explicitly asks you to rewrite them. ' .
                '2. When asked "what improvements to make", focus on page CONTENT issues (headings, word count, missing alt tags, internal links) — not on replacing metadata that is already good. ' .
                '3. Never change metadata just for the sake of changing it. If the current title and description are effective, say so. ' .
                '4. If the user asks about audit results, report the actual issues and suggestions from the audit data — do not invent new ones. ' .
                '5. When the user asks for content changes, set wants_edits to true — the system will automatically generate page edit proposals for review. ' .
                'When you provide suggested_title, keep it at or under 60 characters. ' .
                'When you provide suggested_description, keep it at or under 155 characters. ' .
                'Do not invent facts that are not supported by the page content or site context.' .
                $suffix_note
        );
    }

    private function get_seo_context(\WP_Post $post, array $overrides = array()): array
    {
        // Use browser values if provided, otherwise fall back to saved meta.
        $focus_keyphrase = isset($overrides['focus_keyphrase']) && '' !== $overrides['focus_keyphrase']
            ? trim(wp_strip_all_tags($overrides['focus_keyphrase']))
            : trim(wp_strip_all_tags((string) get_post_meta($post->ID, '_ai_seo_keeper_focus_keyphrase', true)));

        $seo_title_draft = isset($overrides['seo_title']) && null !== $overrides['seo_title']
            ? trim($overrides['seo_title'])
            : trim((string) get_post_meta($post->ID, '_ai_seo_keeper_meta_title', true));

        $meta_desc_draft = isset($overrides['meta_description']) && null !== $overrides['meta_description']
            ? trim($overrides['meta_description'])
            : trim((string) get_post_meta($post->ID, '_ai_seo_keeper_meta_description', true));

        // Snippet score metrics.
        $title_len = mb_strlen($seo_title_draft);
        $desc_len = mb_strlen($meta_desc_draft);
        $kw_in_title = '' !== $focus_keyphrase && '' !== $seo_title_draft && false !== mb_stripos($seo_title_draft, $focus_keyphrase);
        $kw_in_desc = '' !== $focus_keyphrase && '' !== $meta_desc_draft && false !== mb_stripos($meta_desc_draft, $focus_keyphrase);

        // Page audit data (if previously audited).
        $audit_raw = get_post_meta($post->ID, '_ai_seo_keeper_page_audit', true);
        $audit_data = is_array($audit_raw) ? $audit_raw : array();

        // Additional tab data for full context.
        $social_title = isset($overrides['social_title']) && null !== $overrides['social_title']
            ? trim($overrides['social_title'])
            : trim((string) get_post_meta($post->ID, '_ai_seo_keeper_social_title', true));

        $social_description = isset($overrides['social_description']) && null !== $overrides['social_description']
            ? trim($overrides['social_description'])
            : trim((string) get_post_meta($post->ID, '_ai_seo_keeper_social_description', true));

        $schema_type = isset($overrides['schema_type']) && null !== $overrides['schema_type']
            ? trim($overrides['schema_type'])
            : trim((string) get_post_meta($post->ID, '_ai_seo_keeper_schema_type', true));

        $canonical_url = isset($overrides['canonical_url']) && null !== $overrides['canonical_url']
            ? trim($overrides['canonical_url'])
            : trim((string) get_post_meta($post->ID, '_ai_seo_keeper_canonical_url', true));

        $robots_directives = isset($overrides['robots_directives']) && null !== $overrides['robots_directives']
            ? trim($overrides['robots_directives'])
            : trim((string) get_post_meta($post->ID, '_ai_seo_keeper_robots_directives', true));

        $is_cornerstone = isset($overrides['cornerstone']) && null !== $overrides['cornerstone']
            ? ('1' === $overrides['cornerstone'])
            : (! empty(get_post_meta($post->ID, '_ai_seo_keeper_cornerstone', true)));

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
            'social_title' => $social_title,
            'social_description' => $social_description,
            'schema_type' => $schema_type,
            'canonical_url' => $canonical_url,
            'robots_directives' => $robots_directives,
            'is_cornerstone' => $is_cornerstone,
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
            $lines[] = 'Last audit score: ' . $ctx['audit_score'] . '/100';
            if (! empty($ctx['audit_issues'])) {
                $lines[] = 'Audit issues: ' . implode('; ', array_slice($ctx['audit_issues'], 0, 5));
            }
            if ('' !== $ctx['audit_summary']) {
                $lines[] = 'Audit summary: ' . $ctx['audit_summary'];
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

        return implode("\n", $lines);
    }

    private function build_user_prompt(\WP_Post $post, array $field_overrides = array()): string
    {
        $ctx = $this->get_seo_context($post, $field_overrides);
        $page_content = $this->truncate_text($this->normalize_text(Content_Helper::get_content($post)), 5000);
        $page_excerpt = $this->truncate_text($this->normalize_text((string) $post->post_excerpt), 400);
        $related_pages = $this->content_indexer->get_related_entries((int) $post->ID, (string) $post->post_type, (int) $post->post_parent, 5);
        $related_lines = array();

        foreach ($related_pages as $related_page) {
            $related_lines[] = sprintf(
                '- %s | /%s/ | %s',
                trim((string) $related_page['title']) !== '' ? (string) $related_page['title'] : '(untitled)',
                ltrim((string) $related_page['slug'], '/'),
                $this->truncate_text($this->normalize_text((string) $related_page['excerpt']), 220)
            );
        }

        if (empty($related_lines)) {
            $related_lines[] = '- No related indexed pages were found.';
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

        $prompt_parts = array(
            'Task: Generate or refine the SEO title and meta description for the current WordPress page.',
            'Output format: {"seo_title":"...","meta_description":"...","focus_keyphrase":"...","notes":"..."}',
            'Requirements: Make the draft clearly differentiated from the related pages. Keep seo_title at or under ' . ('' !== $branding_suffix ? (string) $page_title_budget : '60') . ' characters and meta_description at or under 155 characters. Do not invent services, guarantees, or facts not present on the page. The focus_keyphrase should be the single most important 2-4 word phrase this page should rank for. When a focus keyphrase is already provided, keep it in your output AND ensure it appears naturally in both seo_title and meta_description. Notes should be one or two short sentences explaining the positioning choice or why the existing draft was kept.',
            'Site: ' . get_bloginfo('name'),
            'Page type: ' . $post->post_type,
            'Current page title: ' . (string) $post->post_title,
            'Page URL: ' . (string) get_permalink($post),
            'Existing excerpt: ' . ('' !== $page_excerpt ? $page_excerpt : 'None'),
            $this->format_seo_context_lines($ctx),
            'Main page content: ' . ('' !== $page_content ? $page_content : 'No body content is available.'),
            "Related pages to avoid overlapping with:\n" . implode("\n", $related_lines),
        );

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

        return implode("\n\n", $prompt_parts);
    }

    private function build_chat_user_prompt(\WP_Post $post, string $message, array $recent_messages): string
    {
        $ctx = $this->get_seo_context($post);
        $page_content = $this->truncate_text($this->normalize_text(Content_Helper::get_content($post)), 6000);
        $page_excerpt = $this->truncate_text($this->normalize_text((string) $post->post_excerpt), 300);
        $related_pages = $this->content_indexer->get_related_entries((int) $post->ID, (string) $post->post_type, (int) $post->post_parent, 5);
        $related_lines = array();

        foreach ($related_pages as $related_page) {
            $related_lines[] = sprintf(
                '- %s | /%s/ | %s',
                trim((string) $related_page['title']) !== '' ? (string) $related_page['title'] : '(untitled)',
                ltrim((string) $related_page['slug'], '/'),
                $this->truncate_text($this->normalize_text((string) $related_page['excerpt']), 180)
            );
        }

        if (empty($related_lines)) {
            $related_lines[] = '- No related indexed pages were found.';
        }

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

            $conversation_lines[] = strtoupper($role) . ': ' . $this->truncate_text($content, 300);
        }

        if (empty($conversation_lines)) {
            $conversation_lines[] = '- No recent chat context.';
        }

        $branding_suffix = $this->settings->get_branding_suffix();
        $branding_note = '';
        if ('' !== $branding_suffix) {
            $suffix_len = function_exists('mb_strlen') ? mb_strlen($branding_suffix) : strlen($branding_suffix);
            $page_title_budget = max(10, 60 - $suffix_len);
            $branding_note = 'Title branding: "' . $branding_suffix . '" (' . $suffix_len . ' chars) is auto-appended. suggested_title must be ONLY the page-specific part — max ' . $page_title_budget . ' chars.';
        }

        $prompt_parts = array(
            'Task: Answer the editor user as an SEO copilot for the current WordPress page.',
            'Output format: {"reply":"...","suggested_title":"...","suggested_description":"...","notes":"..."}',
            'Requirements: Ground advice in the real page content, related pages, and the current user question. Be concise and specific. When the user is not explicitly asking for metadata, keep suggested_title and suggested_description empty. You have FULL access to the page SEO data below — reference it precisely when the user asks about scores, issues, or metadata.',
            'Site: ' . get_bloginfo('name'),
            'Page type: ' . $post->post_type,
            'Current page title: ' . (string) $post->post_title,
            'Page URL: ' . (string) get_permalink($post),
            $this->format_seo_context_lines($ctx),
            'Existing excerpt: ' . ('' !== $page_excerpt ? $page_excerpt : 'None'),
            'Main page content: ' . ('' !== $page_content ? $page_content : 'No body content is available.'),
            "Related pages to avoid overlapping with:\n" . implode("\n", $related_lines),
            "Recent conversation:\n" . implode("\n", $conversation_lines),
            'User question: ' . $message,
        );

        if ('' !== $branding_note) {
            $prompt_parts[] = $branding_note;
        }

        return implode("\n\n", $prompt_parts);
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

        $response = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . trim($api_key),
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 60,
                'body' => wp_json_encode($payload),
            )
        );

        return $this->extract_response_text($response, 'openai');
    }

    public function test_model_connection(string $provider, string $api_key, string $model, float $temperature = 0.3): array
    {
        $provider = sanitize_key($provider);
        $model = trim(sanitize_text_field($model));
        $api_key = trim($api_key);
        $temperature = $this->normalize_temperature($temperature);

        if ('' === $api_key) {
            throw new \RuntimeException('API key is required to test model availability.');
        }

        $system_prompt = 'You are a connectivity test assistant. Return short plain text only.';
        $user_prompt = 'Reply with exactly: OK';

        if ('openai' === $provider) {
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
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            rawurlencode($effective_model),
            rawurlencode(trim($api_key))
        );

        $response = wp_remote_post(
            $url,
            array(
                'headers' => array(
                    'Content-Type' => 'application/json',
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
            )
        );

        return $this->extract_response_text($response, 'google');
    }

    private function extract_response_text($response, string $provider): string
    {
        if (is_wp_error($response)) {
            throw new \RuntimeException($response->get_error_message());
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($status_code < 200 || $status_code >= 300) {
            $message = $this->extract_error_message($decoded);
            throw new \RuntimeException($message ?: sprintf('The %s API returned HTTP %d.', ucfirst($provider), $status_code));
        }

        if ('openai' === $provider) {
            $content = $decoded['choices'][0]['message']['content'] ?? '';
        } else {
            $content = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        }

        if (! is_string($content) || '' === trim($content)) {
            throw new \RuntimeException('The AI provider returned an empty response.');
        }

        return $content;
    }

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

    private function decode_json_payload(string $content): array
    {
        $normalized = trim($content);

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $normalized, $matches)) {
            $normalized = $matches[1];
        }

        $start = strpos($normalized, '{');
        $end = strrpos($normalized, '}');

        if (false !== $start && false !== $end) {
            $normalized = substr($normalized, $start, ($end - $start) + 1);
        }

        $decoded = json_decode($normalized, true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('The AI response was not valid JSON.');
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

    public function generate_page_audit(int $post_id): array
    {
        $post = get_post($post_id);

        if (! $post instanceof \WP_Post) {
            throw new \RuntimeException('The requested page could not be loaded.');
        }

        $options = $this->settings->get();

        if (empty($options['api_key'])) {
            throw new \RuntimeException('Add an API key in AI SEO Keeper Settings before generating page audits.');
        }

        $provider = (string) $options['provider'];
        $model = trim((string) $options['model']);
        $temperature = $this->get_effective_temperature($options);
        $system_prompt = $this->build_page_audit_system_prompt((string) $options['system_prompt']);
        $user_prompt = $this->build_page_audit_user_prompt($post);

        if ('openai' === $provider) {
            $raw_response = $this->call_openai($options['api_key'], $model, $system_prompt, $user_prompt, $temperature);
        } elseif ('google' === $provider) {
            $raw_response = $this->call_google($options['api_key'], $model, $system_prompt, $user_prompt, $temperature);
        } else {
            throw new \RuntimeException('Unsupported AI provider configured.');
        }

        $payload = $this->decode_json_payload($raw_response);

        return array(
            'score' => isset($payload['score']) ? max(0, min(100, (int) $payload['score'])) : 0,
            'issues' => $this->sanitize_string_list($payload['issues'] ?? array(), 10),
            'suggestions' => $this->sanitize_string_list($payload['suggestions'] ?? array(), 10),
            'missing_alt_tags' => isset($payload['missing_alt_tags']) ? (int) $payload['missing_alt_tags'] : 0,
            'word_count' => isset($payload['word_count']) ? (int) $payload['word_count'] : 0,
            'heading_structure' => isset($payload['heading_structure']) ? sanitize_text_field((string) $payload['heading_structure']) : '',
            'summary' => isset($payload['summary']) ? sanitize_textarea_field((string) $payload['summary']) : '',
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

        if (empty($options['api_key'])) {
            throw new \RuntimeException('Add an API key in AI SEO Keeper Settings before requesting content changes.');
        }

        $provider = (string) $options['provider'];
        $model = trim((string) $options['model']);
        $temperature = $this->get_effective_temperature($options);
        $system_prompt = $this->build_content_edit_system_prompt((string) $options['system_prompt']);
        $user_prompt = $this->build_content_edit_user_prompt($post, $instruction, $recent_messages);

        if ('openai' === $provider) {
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
        $page_content_raw = Content_Helper::get_content($post);
        $page_content = $this->truncate_text($page_content_raw, 30000);

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

            $conversation_lines[] = strtoupper($role) . ': ' . $this->truncate_text($content, 400);
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
                'Return only valid JSON with exactly these keys: score, issues, suggestions, missing_alt_tags, word_count, heading_structure, summary. ' .
                'score is 0-100 representing overall SEO health. ' .
                'issues is an array of short strings describing problems found. ' .
                'suggestions is an array of short strings with actionable improvements. ' .
                'missing_alt_tags is the count of images without alt text. ' .
                'word_count is the word count of the main content. ' .
                'heading_structure is a brief note about heading hierarchy (e.g. "H1: 1, H2: 3, H3: 2 — good structure"). ' .
                'summary is 1-2 sentences about overall page SEO quality. ' .
                'Do not use markdown fences. Be specific and factual.'
        );
    }

    private function build_page_audit_user_prompt(\WP_Post $post): string
    {
        $page_content_raw = Content_Helper::get_content($post);
        $page_content = $this->truncate_text($this->normalize_text($page_content_raw), 6000);

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

        return implode(
            "\n\n",
            array(
                'Task: Perform a comprehensive SEO audit of this WordPress page and provide specific, actionable findings.',
                'Output format: {"score":...,"issues":[...],"suggestions":[...],"missing_alt_tags":...,"word_count":...,"heading_structure":"...","summary":"..."}',
                'Site: ' . get_bloginfo('name'),
                'Page type: ' . $post->post_type,
                'Page title: ' . (string) $post->post_title,
                'Page URL: ' . (string) get_permalink($post),
                'Word count: ' . $word_count,
                'Images total: ' . $img_count . ', Images missing alt text: ' . $img_no_alt,
                'Heading structure found: ' . ('' !== $heading_summary ? $heading_summary : 'No headings found'),
                'Internal links: ' . $internal_links . ', External links: ' . $external_links,
                'Main page content: ' . ('' !== $page_content ? $page_content : 'No body content is available.'),
            )
        );
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
