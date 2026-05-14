<?php

namespace AI_SEO_Keeper\Admin;

use AI_SEO_Keeper\AI_Generator;
use AI_SEO_Keeper\Content_Indexer;
use AI_SEO_Keeper\Content_Writer;
use AI_SEO_Keeper\History_Store;
use AI_SEO_Keeper\Run_Manager;
use AI_SEO_Keeper\Settings;
use AI_SEO_Keeper\Admin as AdminBase;

/**
 * All wp_ajax_* handler methods for the editor metabox, setup wizard,
 * bulk editor, and image SEO.
 */
class Admin_Ajax
{
    private AdminBase       $admin;
    private Settings        $settings;
    private AI_Generator    $ai_generator;
    private Content_Indexer $content_indexer;
    private History_Store   $history_store;
    private Run_Manager     $run_manager;

    public function __construct(
        AdminBase       $admin,
        Settings        $settings,
        AI_Generator    $ai_generator,
        Content_Indexer $content_indexer,
        History_Store   $history_store,
        Run_Manager     $run_manager
    ) {
        $this->admin           = $admin;
        $this->settings        = $settings;
        $this->ai_generator    = $ai_generator;
        $this->content_indexer = $content_indexer;
        $this->history_store   = $history_store;
        $this->run_manager     = $run_manager;
    }

    // ------------------------------------------------------------------
    //  Editor meta (save / generate / approve)
    // ------------------------------------------------------------------

    public function handle_save_editor_meta(): void
    {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        if (! $post_id) {
            wp_send_json_error(array('message' => __('Missing post id.', 'ai-seo-keeper')), 400);
        }

        check_ajax_referer('ai_seo_keeper_save_editor_meta', 'nonce');

        $post_type = get_post_type($post_id);

        if (! $post_type || ! $this->admin->is_supported_post_type($post_type)) {
            wp_send_json_error(array('message' => __('Unsupported content type.', 'ai-seo-keeper')), 400);
        }

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('You are not allowed to edit this post.', 'ai-seo-keeper')), 403);
        }

        $saved_meta = $this->admin->persist_editor_meta($post_id, $_POST);
        $post       = get_post($post_id);

        wp_send_json_success(
            array(
                'message'      => 'SEO draft saved.',
                'savedAt'      => current_time('mysql'),
                'analysisHtml' => $post instanceof \WP_Post
                    ? SEO_Analysis::render_checks_markup($post, $saved_meta['focus_keyphrase'], $saved_meta['seo_title'], $saved_meta['seo_description'])
                    : '',
            )
        );
    }

    public function handle_generate_editor_meta(): void
    {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        if (! $post_id) {
            wp_send_json_error(array('message' => __('Missing post id.', 'ai-seo-keeper')), 400);
        }

        check_ajax_referer('ai_seo_keeper_save_editor_meta', 'nonce');

        $post_type = get_post_type($post_id);

        if (! $post_type || ! $this->admin->is_supported_post_type($post_type)) {
            wp_send_json_error(array('message' => __('Unsupported content type.', 'ai-seo-keeper')), 400);
        }

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('You are not allowed to edit this post.', 'ai-seo-keeper')), 403);
        }

        $field_overrides = array(
            'focus_keyphrase'    => isset($_POST['current_focus_keyphrase']) ? sanitize_text_field(wp_unslash($_POST['current_focus_keyphrase'])) : null,
            'seo_title'          => isset($_POST['current_seo_title']) ? sanitize_text_field(wp_unslash($_POST['current_seo_title'])) : null,
            'meta_description'   => isset($_POST['current_meta_description']) ? sanitize_textarea_field(wp_unslash($_POST['current_meta_description'])) : null,
            'social_title'       => isset($_POST['current_social_title']) ? sanitize_text_field(wp_unslash($_POST['current_social_title'])) : null,
            'social_description' => isset($_POST['current_social_description']) ? sanitize_textarea_field(wp_unslash($_POST['current_social_description'])) : null,
            'schema_type'        => isset($_POST['current_schema_type']) ? sanitize_text_field(wp_unslash($_POST['current_schema_type'])) : null,
            'canonical_url'      => isset($_POST['current_canonical_url']) ? esc_url_raw(wp_unslash($_POST['current_canonical_url'])) : null,
            'robots_directives'  => isset($_POST['current_robots_directives']) ? sanitize_text_field(wp_unslash($_POST['current_robots_directives'])) : null,
            'cornerstone'        => isset($_POST['current_cornerstone']) ? sanitize_text_field(wp_unslash($_POST['current_cornerstone'])) : null,
            'deep_analysis'      => ! empty($_POST['deep_analysis']) && '1' === $_POST['deep_analysis'],
        );

        try {
            $suggestion = $this->ai_generator->generate_for_post($post_id, $field_overrides);
        } catch (\Throwable $throwable) {
            wp_send_json_error(array('message' => $throwable->getMessage()), 500);
            return;
        }

        $suggestion = array_merge(
            $suggestion,
            SEO_Analysis::apply_editor_text_limits(
                array(
                    'seo_title'        => isset($suggestion['seo_title']) ? (string) $suggestion['seo_title'] : '',
                    'meta_description' => isset($suggestion['meta_description']) ? (string) $suggestion['meta_description'] : '',
                )
            )
        );

        $history_warning = '';

        try {
            $this->history_store->log_generation(
                $post_id,
                'post',
                get_the_title($post_id) . ' SEO history',
                array(
                    'system_prompt' => $suggestion['system_prompt'],
                    'user_prompt'   => $suggestion['user_prompt'],
                    'provider'      => $suggestion['provider'],
                    'model'         => $suggestion['model'],
                ),
                array(
                    'seo_title'        => $suggestion['seo_title'],
                    'meta_description' => $suggestion['meta_description'],
                    'notes'            => $suggestion['notes'],
                    'provider'         => $suggestion['provider'],
                    'model'            => $suggestion['model'],
                )
            );
        } catch (\Throwable $throwable) {
            $history_warning = ' The suggestion was generated, but history could not be stored.';
        }

        $recent_suggestions  = $this->history_store->get_recent_suggestions($post_id, 'post', 3);
        $post                = get_post($post_id);
        $user_keyphrase      = isset($field_overrides['focus_keyphrase']) ? (string) $field_overrides['focus_keyphrase'] : '';
        $effective_keyphrase  = '' !== $user_keyphrase ? $user_keyphrase : ($suggestion['focus_keyphrase'] ?? '');

        wp_send_json_success(
            array(
                'message'         => 'AI suggestion loaded. Review it and save the draft if you want to keep it.' . $history_warning,
                'seoTitle'        => $suggestion['seo_title'],
                'metaDescription' => $suggestion['meta_description'],
                'focusKeyphrase'  => $suggestion['focus_keyphrase'] ?? '',
                'socialTitle'     => $suggestion['social_title'] ?? '',
                'socialDescription' => $suggestion['social_description'] ?? '',
                'notes'           => $suggestion['notes'],
                'provider'        => $suggestion['provider'],
                'model'           => $suggestion['model'],
                'historyHtml'     => $this->admin->render_history_markup($recent_suggestions, $this->history_store->get_recent_content_edits($post_id, 5)),
                'analysisHtml'    => $post instanceof \WP_Post
                    ? SEO_Analysis::render_checks_markup($post, $effective_keyphrase, $suggestion['seo_title'], $suggestion['meta_description'])
                    : '',
            )
        );
    }

    public function handle_approve_suggestion(): void
    {
        $post_id    = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $message_id = isset($_POST['message_id']) ? (int) $_POST['message_id'] : 0;

        if (! $post_id || ! $message_id) {
            wp_send_json_error(array('message' => __('Missing suggestion approval details.', 'ai-seo-keeper')), 400);
        }

        check_ajax_referer('ai_seo_keeper_save_editor_meta', 'nonce');

        $post_type = get_post_type($post_id);

        if (! $post_type || ! $this->admin->is_supported_post_type($post_type)) {
            wp_send_json_error(array('message' => __('Unsupported content type.', 'ai-seo-keeper')), 400);
        }

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('You are not allowed to edit this post.', 'ai-seo-keeper')), 403);
        }

        try {
            $approved = $this->history_store->approve_suggestion($post_id, 'post', $message_id);
        } catch (\Throwable $throwable) {
            wp_send_json_error(array('message' => $throwable->getMessage()), 500);
            return;
        }

        $approved = array_merge(
            $approved,
            SEO_Analysis::apply_editor_text_limits(
                array(
                    'seo_title'        => isset($approved['seo_title']) ? (string) $approved['seo_title'] : '',
                    'meta_description' => isset($approved['meta_description']) ? (string) $approved['meta_description'] : '',
                )
            )
        );

        $recent_suggestions = $this->history_store->get_recent_suggestions($post_id, 'post', 5);
        $post               = get_post($post_id);
        $focus_keyphrase    = (string) get_post_meta($post_id, AdminBase::FOCUS_KEYPHRASE_META_KEY, true);

        wp_send_json_success(
            array(
                'message'      => 'Suggestion approved for future output.',
                'seoTitle'     => $approved['seo_title'],
                'metaDescription' => $approved['meta_description'],
                'notes'        => $approved['notes'],
                'historyHtml'  => $this->admin->render_history_markup($recent_suggestions, $this->history_store->get_recent_content_edits($post_id, 5)),
                'analysisHtml' => $post instanceof \WP_Post
                    ? SEO_Analysis::render_checks_markup($post, $focus_keyphrase, $approved['seo_title'], $approved['meta_description'])
                    : '',
            )
        );
    }

    // ------------------------------------------------------------------
    //  Chat for post
    // ------------------------------------------------------------------

    public function handle_chat_for_post(): void
    {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

        if (! $post_id) {
            wp_send_json_error(array('message' => __('Missing post id.', 'ai-seo-keeper')), 400);
        }

        check_ajax_referer('ai_seo_keeper_save_editor_meta', 'nonce');

        $post_type = get_post_type($post_id);

        if (! $post_type || ! $this->admin->is_supported_post_type($post_type)) {
            wp_send_json_error(array('message' => __('Unsupported content type.', 'ai-seo-keeper')), 400);
        }

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('You are not allowed to edit this post.', 'ai-seo-keeper')), 403);
        }

        if ('' === trim($message)) {
            wp_send_json_error(array('message' => __('Enter a question before asking the AI assistant.', 'ai-seo-keeper')), 400);
        }

        $options = $this->settings->get();

        if (empty($options['editor_chat_enabled'])) {
            wp_send_json_error(array('message' => __('The AI assistant is disabled in settings.', 'ai-seo-keeper')), 400);
        }

        try {
            $recent_messages = $this->history_store->get_recent_chat_messages($post_id, 8);
            $deep_analysis   = ! empty($_POST['deep_analysis']) && '1' === $_POST['deep_analysis'];
            $reply           = $this->ai_generator->chat_for_post($post_id, $message, $recent_messages, $deep_analysis);

            $this->history_store->log_generation(
                $post_id,
                AdminBase::CHAT_OBJECT_TYPE,
                get_the_title($post_id) . ' AI chat',
                array('message' => $message),
                array(
                    'reply'                 => $reply['reply'],
                    'suggested_title'       => $reply['suggested_title'],
                    'suggested_description' => $reply['suggested_description'],
                    'notes'                 => $reply['notes'],
                    'provider'              => $reply['provider'],
                    'model'                 => $reply['model'],
                )
            );

            // If AI decided content edits are needed, auto-generate proposals.
            $content_changes = null;
            $content_summary = '';
            $content_builder = '';

            if (! empty($reply['wants_edits'])) {
                try {
                    $all_messages   = $this->history_store->get_recent_chat_messages($post_id, 12);
                    $edit_result    = $this->ai_generator->generate_content_changes($post_id, $message, $all_messages);
                    $content_changes = $edit_result['changes'];
                    $content_summary = $edit_result['summary'];
                    $content_builder = Content_Writer::detect_builder($post_id);
                } catch (\Throwable $edit_err) {
                    $content_changes = null;
                    $content_summary = 'Content edit proposals could not be generated: ' . $edit_err->getMessage();
                }
            }
        } catch (\Throwable $throwable) {
            wp_send_json_error(array('message' => $throwable->getMessage()), 500);
            return;
        }

        $chat_messages = $this->history_store->get_recent_chat_messages($post_id, 12);

        $response_data = array(
            'message' => __('AI assistant replied.', 'ai-seo-keeper'),
            'notes'   => $reply['notes'],
            'chatHtml' => $this->admin->render_chat_history_markup($chat_messages),
        );

        if (null !== $content_changes) {
            $response_data['changes'] = $content_changes;
            $response_data['summary'] = $content_summary;
            $response_data['builder'] = $content_builder;
        }

        wp_send_json_success($response_data);
    }

    // ------------------------------------------------------------------
    //  Setup wizard AJAX
    // ------------------------------------------------------------------

    public function handle_setup_index(): void
    {
        check_ajax_referer('ai_seo_keeper_setup_wizard', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-seo-keeper')), 403);
        }

        $count = $this->content_indexer->sync();

        global $wpdb;
        $table = $wpdb->prefix . 'ai_seo_keeper_content_index';
        $ids   = $wpdb->get_col("SELECT object_id FROM {$table} WHERE object_type = 'post' AND status = 'publish' ORDER BY object_id ASC");

        wp_send_json_success(array(
            'message'      => sprintf('Site index synced. %d content records stored.', $count),
            'count'        => $count,
            'publishedIds' => array_map('intval', $ids ?: array()),
        ));
    }

    public function handle_bulk_generate(): void
    {
        check_ajax_referer('ai_seo_keeper_setup_wizard', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-seo-keeper')), 403);
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        if ($post_id <= 0) {
            wp_send_json_error(array('message' => __('Missing post id.', 'ai-seo-keeper')), 400);
        }

        $post = get_post($post_id);

        if (! $post instanceof \WP_Post) {
            wp_send_json_error(array('message' => __('Page not found.', 'ai-seo-keeper')), 404);
        }

        // Respect skip rules (individual skip + path patterns).
        if ($this->admin->is_audit_skipped($post_id)) {
            wp_send_json_success(array(
                'message' => 'Skipped by skip rules.',
                'skipped' => true,
                'post_id' => $post_id,
                'title'   => $post->post_title,
            ));
            return;
        }

        $existing_title     = trim((string) get_post_meta($post_id, AdminBase::META_TITLE_KEY, true));
        $existing_desc      = trim((string) get_post_meta($post_id, AdminBase::META_DESCRIPTION_KEY, true));
        $existing_keyphrase = trim((string) get_post_meta($post_id, AdminBase::FOCUS_KEYPHRASE_META_KEY, true));

        if ('' !== $existing_title && '' !== $existing_desc && '' !== $existing_keyphrase) {
            wp_send_json_success(array(
                'message'  => 'Already has all metadata, skipped.',
                'skipped'  => true,
                'post_id'  => $post_id,
                'title'    => $post->post_title,
            ));
            return;
        }

        try {
            $suggestion = $this->ai_generator->generate_for_post($post_id);
        } catch (\Throwable $throwable) {
            wp_send_json_error(array(
                'message' => $throwable->getMessage(),
                'post_id' => $post_id,
                'title'   => $post->post_title,
            ), 500);
            return;
        }

        $suggestion = array_merge(
            $suggestion,
            SEO_Analysis::apply_editor_text_limits(array(
                'seo_title'        => isset($suggestion['seo_title']) ? (string) $suggestion['seo_title'] : '',
                'meta_description' => isset($suggestion['meta_description']) ? (string) $suggestion['meta_description'] : '',
            ))
        );

        if ('' === $existing_title) {
            update_post_meta($post_id, AdminBase::META_TITLE_KEY, $suggestion['seo_title']);
        }
        if ('' === $existing_desc) {
            update_post_meta($post_id, AdminBase::META_DESCRIPTION_KEY, $suggestion['meta_description']);
        }
        if ('' === $existing_keyphrase && ! empty($suggestion['focus_keyphrase'])) {
            update_post_meta($post_id, AdminBase::FOCUS_KEYPHRASE_META_KEY, $suggestion['focus_keyphrase']);
        }
        if (! empty($suggestion['social_title'])) {
            $existing_social_title = trim((string) get_post_meta($post_id, AdminBase::SOCIAL_TITLE_META_KEY, true));
            if ('' === $existing_social_title) {
                update_post_meta($post_id, AdminBase::SOCIAL_TITLE_META_KEY, $suggestion['social_title']);
            }
        }
        if (! empty($suggestion['social_description'])) {
            $existing_social_desc = trim((string) get_post_meta($post_id, AdminBase::SOCIAL_DESCRIPTION_META_KEY, true));
            if ('' === $existing_social_desc) {
                update_post_meta($post_id, AdminBase::SOCIAL_DESCRIPTION_META_KEY, $suggestion['social_description']);
            }
        }

        try {
            $this->history_store->log_generation(
                $post_id,
                'post',
                $post->post_title . ' SEO history',
                array(
                    'system_prompt' => $suggestion['system_prompt'],
                    'user_prompt'   => $suggestion['user_prompt'],
                    'provider'      => $suggestion['provider'],
                    'model'         => $suggestion['model'],
                ),
                array(
                    'seo_title'        => $suggestion['seo_title'],
                    'meta_description' => $suggestion['meta_description'],
                    'notes'            => $suggestion['notes'],
                    'provider'         => $suggestion['provider'],
                    'model'            => $suggestion['model'],
                )
            );
        } catch (\Throwable $throwable) {
            // History failure is non-fatal
        }

        wp_send_json_success(array(
            'message'            => 'Generated metadata for: ' . $post->post_title,
            'skipped'            => false,
            'post_id'            => $post_id,
            'title'              => $post->post_title,
            'seo_title'          => $suggestion['seo_title'],
            'meta_description'   => $suggestion['meta_description'],
            'focus_keyphrase'    => $suggestion['focus_keyphrase'] ?? '',
            'social_title'       => $suggestion['social_title'] ?? '',
            'social_description' => $suggestion['social_description'] ?? '',
            'notes'              => $suggestion['notes'],
        ));
    }

    // ------------------------------------------------------------------
    //  Page audit
    // ------------------------------------------------------------------

    public function handle_page_audit(): void
    {
        // Accept both editor nonce and wizard nonce since this is called from both contexts.
        if (
            ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'ai_seo_keeper_save_editor_meta')
            && ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'ai_seo_keeper_setup_wizard')
        ) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-seo-keeper')), 403);
        }

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-seo-keeper')), 403);
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        if ($post_id <= 0) {
            wp_send_json_error(array('message' => __('Missing post id.', 'ai-seo-keeper')), 400);
        }

        $post = get_post($post_id);

        if (! $post instanceof \WP_Post) {
            wp_send_json_error(array('message' => __('Page not found.', 'ai-seo-keeper')), 404);
        }

        // Return cached audit if already completed.
        $cached = get_post_meta($post_id, '_ai_seo_keeper_page_audit', true);

        if (is_array($cached) && isset($cached['score'])) {
            wp_send_json_success(array(
                'post_id'           => $post_id,
                'title'             => $post->post_title,
                'permalink'         => get_permalink($post_id),
                'score'             => $cached['score'],
                'issues'            => $cached['issues'],
                'suggestions'       => $cached['suggestions'],
                'missing_alt_tags'  => $cached['missing_alt_tags'],
                'word_count'        => $cached['word_count'],
                'heading_structure' => $cached['heading_structure'],
                'summary'           => $cached['summary'],
                'cached'            => true,
            ));
            return;
        }

        try {
            $audit = $this->ai_generator->generate_page_audit($post_id);
        } catch (\Throwable $throwable) {
            wp_send_json_error(array(
                'message' => $throwable->getMessage(),
                'post_id' => $post_id,
                'title'   => $post->post_title,
            ), 500);
            return;
        }

        update_post_meta($post_id, '_ai_seo_keeper_page_audit', array(
            'score'             => $audit['score'],
            'issues'            => $audit['issues'],
            'suggestions'       => $audit['suggestions'],
            'missing_alt_tags'  => $audit['missing_alt_tags'],
            'word_count'        => $audit['word_count'],
            'heading_structure' => $audit['heading_structure'],
            'summary'           => $audit['summary'],
            'audited_at'        => current_time('mysql', true),
        ));

        wp_send_json_success(array(
            'post_id'           => $post_id,
            'title'             => $post->post_title,
            'permalink'         => get_permalink($post_id),
            'score'             => $audit['score'],
            'issues'            => $audit['issues'],
            'suggestions'       => $audit['suggestions'],
            'missing_alt_tags'  => $audit['missing_alt_tags'],
            'word_count'        => $audit['word_count'],
            'heading_structure' => $audit['heading_structure'],
            'summary'           => $audit['summary'],
            'cached'            => false,
        ));
    }

    public function handle_toggle_audit_skip(): void
    {
        check_ajax_referer('ai_seo_keeper_setup_wizard', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-seo-keeper')), 403);
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        if ($post_id <= 0) {
            wp_send_json_error(array('message' => __('Missing post id.', 'ai-seo-keeper')), 400);
        }

        $current   = get_post_meta($post_id, '_ai_seo_keeper_audit_skip', true);
        $new_value = empty($current) ? '1' : '';

        if ('' === $new_value) {
            delete_post_meta($post_id, '_ai_seo_keeper_audit_skip');
        } else {
            update_post_meta($post_id, '_ai_seo_keeper_audit_skip', '1');
        }

        wp_send_json_success(array(
            'post_id' => $post_id,
            'skipped' => '' !== $new_value,
        ));
    }

    public function handle_save_skip_patterns(): void
    {
        check_ajax_referer('ai_seo_keeper_setup_wizard', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-seo-keeper')), 403);
        }

        $patterns = isset($_POST['patterns']) ? sanitize_textarea_field(wp_unslash($_POST['patterns'])) : '';

        $options                       = $this->settings->get();
        $options['audit_skip_patterns'] = $patterns;
        update_option('ai_seo_keeper_options', $options);

        $matched = $this->admin->count_pages_matching_skip_patterns($patterns);

        wp_send_json_success(array(
            'patterns'      => $patterns,
            'matched_count' => $matched,
        ));
    }

    // ------------------------------------------------------------------
    //  Content editing
    // ------------------------------------------------------------------

    public function handle_content_edit(): void
    {
        $post_id     = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $instruction = isset($_POST['instruction']) ? sanitize_textarea_field(wp_unslash($_POST['instruction'])) : '';

        if (! $post_id) {
            wp_send_json_error(array('message' => __('Missing post id.', 'ai-seo-keeper')), 400);
        }

        check_ajax_referer('ai_seo_keeper_save_editor_meta', 'nonce');

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('You are not allowed to edit this post.', 'ai-seo-keeper')), 403);
        }

        if ('' === trim($instruction)) {
            wp_send_json_error(array('message' => __('Provide an instruction for the AI content editor.', 'ai-seo-keeper')), 400);
        }

        try {
            $result = $this->ai_generator->generate_content_changes($post_id, $instruction);
        } catch (\Throwable $throwable) {
            wp_send_json_error(array('message' => $throwable->getMessage()), 500);
            return;
        }

        wp_send_json_success(array(
            'changes'  => $result['changes'],
            'summary'  => $result['summary'],
            'provider' => $result['provider'],
            'model'    => $result['model'],
            'builder'  => Content_Writer::detect_builder($post_id),
        ));
    }

    public function handle_apply_changes(): void
    {
        $post_id      = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $changes_json = isset($_POST['changes']) ? wp_unslash($_POST['changes']) : '';
        $summary      = isset($_POST['summary']) ? sanitize_textarea_field(wp_unslash($_POST['summary'])) : '';

        if (! $post_id) {
            wp_send_json_error(array('message' => __('Missing post id.', 'ai-seo-keeper')), 400);
        }

        check_ajax_referer('ai_seo_keeper_save_editor_meta', 'nonce');

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('You are not allowed to edit this post.', 'ai-seo-keeper')), 403);
        }

        $changes = json_decode($changes_json, true);
        if (! is_array($changes) || empty($changes)) {
            wp_send_json_error(array('message' => __('No changes to apply.', 'ai-seo-keeper')), 400);
        }

        Content_Writer::store_pending_changes($post_id, $changes, $summary);

        $this->history_store->log_generation(
            $post_id,
            'content_edit',
            get_the_title($post_id) . ' — content edit plan',
            array('instruction' => $summary),
            array(
                'content_edit_summary' => $summary,
                'content_edit_count'   => count($changes),
                'content_edit_status'  => 'pending',
                'changes'              => $changes,
                'provider'             => 'ai',
                'model'                => '',
            )
        );

        wp_send_json_success(array(
            'applied' => count($changes),
            'failed'  => 0,
            'details' => array(),
            'message' => sprintf(
                '%d change(s) approved. Preview the page, then click Update to publish them.',
                count($changes)
            ),
        ));
    }

    public function handle_apply_suggestion(): void
    {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $field   = isset($_POST['field']) ? sanitize_text_field(wp_unslash($_POST['field'])) : '';
        $value   = isset($_POST['value']) ? wp_unslash($_POST['value']) : '';

        if (! $post_id) {
            wp_send_json_error(array('message' => __('Missing post id.', 'ai-seo-keeper')), 400);
        }

        check_ajax_referer('ai_seo_keeper_save_editor_meta', 'nonce');

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('You are not allowed to edit this post.', 'ai-seo-keeper')), 403);
        }

        $allowed_fields = array(
            'meta_title'       => AdminBase::META_TITLE_KEY,
            'meta_description' => AdminBase::META_DESCRIPTION_KEY,
        );

        if (! isset($allowed_fields[$field])) {
            wp_send_json_error(array('message' => __('Invalid field: ', 'ai-seo-keeper') . $field), 400);
        }

        $meta_key  = $allowed_fields[$field];
        $sanitized = 'meta_title' === $field ? sanitize_text_field($value) : sanitize_textarea_field($value);
        update_post_meta($post_id, $meta_key, $sanitized);

        wp_send_json_success(array(
            'field'   => $field,
            'value'   => $sanitized,
            'message' => ucfirst(str_replace('_', ' ', $field)) . ' updated.',
        ));
    }

    public function handle_restore_backup(): void
    {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        if (! $post_id) {
            wp_send_json_error(array('message' => __('Missing post id.', 'ai-seo-keeper')), 400);
        }

        check_ajax_referer('ai_seo_keeper_save_editor_meta', 'nonce');

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('You are not allowed to edit this post.', 'ai-seo-keeper')), 403);
        }

        $restored = Content_Writer::restore_backup($post_id);

        if (! $restored) {
            wp_send_json_error(array('message' => __('No backup found for this page.', 'ai-seo-keeper')), 404);
        }

        wp_send_json_success(array('message' => __('Content restored to the version before AI edits.', 'ai-seo-keeper')));
    }

    public function handle_clear_chat(): void
    {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        if (! $post_id) {
            wp_send_json_error(array('message' => __('Missing post id.', 'ai-seo-keeper')), 400);
        }

        check_ajax_referer('ai_seo_keeper_save_editor_meta', 'nonce');

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-seo-keeper')), 403);
        }

        $deleted = $this->history_store->clear_chat_messages($post_id);

        wp_send_json_success(array('message' => $deleted . ' message(s) cleared.', 'deleted' => $deleted));
    }

    // ------------------------------------------------------------------
    //  Model test
    // ------------------------------------------------------------------

    public function handle_test_model(): void
    {
        check_ajax_referer('ai_seo_keeper_settings_test_model', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You are not allowed to test AI model access.', 'ai-seo-keeper')), 403);
        }

        $options            = $this->settings->get();
        $provider           = isset($_POST['provider']) ? sanitize_key((string) wp_unslash($_POST['provider'])) : (string) ($options['provider'] ?? 'openai');
        $supported_providers = Settings::get_supported_providers();

        if (! in_array($provider, $supported_providers, true)) {
            wp_send_json_error(array('message' => __('Unsupported AI provider selected.', 'ai-seo-keeper')), 400);
        }

        $requested_model = isset($_POST['model']) ? sanitize_text_field((string) wp_unslash($_POST['model'])) : '';
        $allowed_models  = Settings::get_models_for_provider($provider);

        if (isset($allowed_models[$requested_model])) {
            $model = $requested_model;
        } else {
            $custom_model = Settings::sanitize_custom_model_id($requested_model);
            $model        = '' !== $custom_model ? $custom_model : Settings::sanitize_provider_model($provider, $requested_model);
        }

        $posted_temperature = isset($_POST['temperature']) ? (string) wp_unslash($_POST['temperature']) : '';
        $temperature        = is_numeric($posted_temperature) ? (float) $posted_temperature : (float) ($options['ai_temperature'] ?? 0.3);
        $temperature        = max(0.0, min(2.0, $temperature));
        $temperature        = round($temperature, 1);

        $posted_api_key = isset($_POST['api_key']) ? sanitize_text_field((string) wp_unslash($_POST['api_key'])) : '';
        $api_key        = '' !== trim($posted_api_key) ? $posted_api_key : (string) ($options['api_key'] ?? '');

        if ('' === trim($api_key)) {
            wp_send_json_error(array('message' => __('Enter an API key before testing model availability.', 'ai-seo-keeper')), 400);
        }

        try {
            $result = $this->ai_generator->test_model_connection($provider, $api_key, $model, $temperature);
        } catch (\Throwable $throwable) {
            wp_send_json_error(array('message' => $throwable->getMessage()), 400);
        }

        $preview          = isset($result['preview']) ? sanitize_text_field((string) $result['preview']) : '';
        $temperature_note = '';

        if ('openai' === $provider && preg_match('/^o[1-9]/i', $model)) {
            $temperature_note = ' (provider default temperature mode)';
        }

        $message = sprintf(
            'Model is available: %s / %s%s',
            ucfirst($provider),
            $model,
            $temperature_note . ('' !== $preview ? ' - ' . $preview : '')
        );

        wp_send_json_success(array(
            'message'     => $message,
            'provider'    => $provider,
            'model'       => $model,
            'temperature' => $temperature,
        ));
    }

    public function handle_delete_edit_plan(): void
    {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $edit_id = isset($_POST['edit_id']) ? (int) $_POST['edit_id'] : 0;

        if (! $post_id || ! $edit_id) {
            wp_send_json_error(array('message' => __('Missing parameters.', 'ai-seo-keeper')), 400);
        }

        check_ajax_referer('ai_seo_keeper_save_editor_meta', 'nonce');

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-seo-keeper')), 403);
        }

        $this->history_store->delete_content_edit_plan($edit_id);

        wp_send_json_success(array('message' => __('Plan removed from history.', 'ai-seo-keeper')));
    }

    // ------------------------------------------------------------------
    //  Bulk editor + Image SEO
    // ------------------------------------------------------------------

    public function handle_bulk_save_seo(): void
    {
        check_ajax_referer('ai_seo_keeper_nonce', '_nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        if ($post_id <= 0 || ! get_post($post_id)) {
            wp_send_json_error('Invalid post ID.');
        }

        $seo_title       = isset($_POST['seo_title']) ? sanitize_text_field(wp_unslash($_POST['seo_title'])) : '';
        $seo_description = isset($_POST['seo_description']) ? sanitize_textarea_field(wp_unslash($_POST['seo_description'])) : '';

        update_post_meta($post_id, AdminBase::META_TITLE_KEY, $seo_title);
        update_post_meta($post_id, AdminBase::META_DESCRIPTION_KEY, $seo_description);

        wp_send_json_success(array('message' => __('Saved.', 'ai-seo-keeper')));
    }

    public function handle_save_image_alt(): void
    {
        check_ajax_referer('ai_seo_keeper_nonce', '_nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $attachment_id = isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;

        if ($attachment_id <= 0 || 'attachment' !== get_post_type($attachment_id)) {
            wp_send_json_error('Invalid attachment.');
        }

        $alt_text = isset($_POST['alt_text']) ? sanitize_text_field(wp_unslash($_POST['alt_text'])) : '';
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);

        wp_send_json_success(array('message' => __('Alt text saved.', 'ai-seo-keeper')));
    }

    // ------------------------------------------------------------------
    //  Runs (Lists) — create, list, delete
    // ------------------------------------------------------------------

    public function handle_create_run(): void
    {
        check_ajax_referer('ai_seo_keeper_setup_wizard', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        if (empty($name)) {
            wp_send_json_error(array('message' => __('List name is required.', 'ai-seo-keeper')), 400);
        }

        $page_ids_raw = isset($_POST['page_ids']) ? wp_unslash($_POST['page_ids']) : '[]';
        $page_ids     = json_decode($page_ids_raw, true);
        if (! is_array($page_ids) || empty($page_ids)) {
            wp_send_json_error(array('message' => __('Select at least one page.', 'ai-seo-keeper')), 400);
        }

        $page_ids = array_map('intval', $page_ids);

        $run_id = $this->run_manager->create_run($name, $page_ids);

        if (! $run_id) {
            wp_send_json_error(array('message' => __('Failed to create list.', 'ai-seo-keeper')));
        }

        // Auto-activate this run.
        $this->run_manager->set_active_run_ids(array($run_id));

        wp_send_json_success(array(
            'run_id'     => $run_id,
            'name'       => $name,
            'page_count' => count($page_ids),
            'page_ids'   => $page_ids,
            'message'    => sprintf(
                /* translators: 1: list name, 2: page count */
                __('List "%1$s" created with %2$d pages.', 'ai-seo-keeper'),
                $name,
                count($page_ids)
            ),
        ));
    }

    public function handle_get_runs(): void
    {
        check_ajax_referer('ai_seo_keeper_setup_wizard', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $runs       = $this->run_manager->get_all_runs();
        $active_ids = $this->run_manager->get_active_run_ids();

        wp_send_json_success(array(
            'runs'       => $runs,
            'active_ids' => $active_ids,
        ));
    }

    public function handle_delete_run(): void
    {
        check_ajax_referer('ai_seo_keeper_setup_wizard', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $run_id = isset($_POST['run_id']) ? (int) $_POST['run_id'] : 0;
        if (! $run_id) {
            wp_send_json_error(array('message' => __('Invalid list ID.', 'ai-seo-keeper')), 400);
        }

        $this->run_manager->delete_run($run_id);

        // Remove from active list.
        $active = $this->run_manager->get_active_run_ids();
        $active = array_values(array_diff($active, array($run_id)));
        $this->run_manager->set_active_run_ids($active);

        wp_send_json_success(array('message' => __('List deleted.', 'ai-seo-keeper')));
    }

    public function handle_set_active_runs(): void
    {
        check_ajax_referer('ai_seo_keeper_setup_wizard', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $ids_raw = isset($_POST['run_ids']) ? wp_unslash($_POST['run_ids']) : '[]';
        $ids     = json_decode($ids_raw, true);
        if (! is_array($ids)) {
            $ids = array();
        }

        $this->run_manager->set_active_run_ids(array_map('intval', $ids));

        wp_send_json_success(array('active_ids' => array_map('intval', $ids)));
    }

    public function handle_get_pages_for_selector(): void
    {
        check_ajax_referer('ai_seo_keeper_setup_wizard', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $pages = $this->run_manager->get_indexed_pages_for_selector();

        wp_send_json_success(array('pages' => $pages));
    }

    public function handle_mark_run_step(): void
    {
        check_ajax_referer('ai_seo_keeper_setup_wizard', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $run_id = isset($_POST['run_id']) ? (int) $_POST['run_id'] : 0;
        $step   = isset($_POST['step']) ? sanitize_text_field(wp_unslash($_POST['step'])) : '';

        if (! $run_id || ! in_array($step, array('metadata', 'audit'), true)) {
            wp_send_json_error(array('message' => __('Invalid parameters.', 'ai-seo-keeper')), 400);
        }

        $this->run_manager->mark_step_complete($run_id, $step);

        wp_send_json_success(array('message' => 'Step marked complete.'));
    }

    public function handle_clear_seo_data(): void
    {
        check_ajax_referer('ai_seo_keeper_setup_wizard', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $scope = isset($_POST['scope']) ? sanitize_text_field(wp_unslash($_POST['scope'])) : '';

        if (! in_array($scope, array('metadata', 'audits', 'all'), true)) {
            wp_send_json_error(array('message' => __('Invalid scope.', 'ai-seo-keeper')), 400);
        }

        global $wpdb;
        $deleted = 0;

        if ($scope === 'metadata' || $scope === 'all') {
            $deleted += (int) $wpdb->query(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (
                    '_ai_seo_keeper_meta_title',
                    '_ai_seo_keeper_meta_description',
                    '_ai_seo_keeper_focus_keyphrase',
                    '_ai_seo_keeper_social_title',
                    '_ai_seo_keeper_social_description',
                    '_ai_seo_keeper_social_image',
                    '_ai_seo_keeper_canonical_url',
                    '_ai_seo_keeper_robots_directives',
                    '_ai_seo_keeper_schema_type',
                    '_ai_seo_keeper_title_branding_off',
                    '_ai_seo_keeper_cornerstone',
                    '_ai_seo_keeper_hreflang',
                    '_ai_seo_keeper_pending_content_changes',
                    '_ai_seo_keeper_content_backup'
                )"
            );
        }

        if ($scope === 'audits' || $scope === 'all') {
            $deleted += (int) $wpdb->query(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (
                    '_ai_seo_keeper_page_audit',
                    '_ai_seo_keeper_audit_skip'
                )"
            );
        }

        if ($scope === 'all') {
            // Clear approval and frontend toggles.
            $deleted += (int) $wpdb->query(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (
                    '_ai_seo_keeper_approved_message_id',
                    '_ai_seo_keeper_frontend_enabled'
                )"
            );

            // Clear term meta (taxonomy SEO fields).
            $wpdb->query(
                "DELETE FROM {$wpdb->termmeta} WHERE meta_key IN (
                    '_ai_seo_keeper_seo_title',
                    '_ai_seo_keeper_meta_description',
                    '_ai_seo_keeper_canonical',
                    '_ai_seo_keeper_noindex'
                )"
            );

            // Delete all lists.
            $runs_table = $wpdb->prefix . 'ai_seo_keeper_runs';
            $wpdb->query("DELETE FROM {$runs_table}");

            // Clear conversations and messages.
            $messages_table      = $wpdb->prefix . 'ai_seo_keeper_messages';
            $conversations_table = $wpdb->prefix . 'ai_seo_keeper_conversations';
            $wpdb->query("DELETE FROM {$messages_table}");
            $wpdb->query("DELETE FROM {$conversations_table}");

            // Clear site audit log and IndexNow log.
            delete_option('ai_seo_keeper_indexnow_log');

            // Clear active runs user meta for current user.
            delete_user_meta(get_current_user_id(), '_ai_seo_keeper_active_runs');
        }

        wp_send_json_success(array(
            'message' => sprintf(__('%d entries cleared.', 'ai-seo-keeper'), $deleted),
            'deleted' => $deleted,
        ));
    }
}
