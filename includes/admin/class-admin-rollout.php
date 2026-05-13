<?php

namespace AI_SEO_Keeper\Admin;

use AI_SEO_Keeper\Content_Indexer;
use AI_SEO_Keeper\AI_Generator;
use AI_SEO_Keeper\History_Store;
use AI_SEO_Keeper\IndexNow;
use AI_SEO_Keeper\Audit_Engine;
use AI_SEO_Keeper\Settings;
use AI_SEO_Keeper\Admin as AdminBase;

/**
 * Bulk operations — sync index, generate site audit, IndexNow submit,
 * bulk frontend rollout, and redirect helpers.
 */
class Admin_Rollout
{
    private Content_Indexer $content_indexer;
    private AI_Generator    $ai_generator;
    private History_Store   $history_store;
    private Audit_Engine    $audit_engine;
    private ?IndexNow       $indexnow_service;
    private AdminBase       $admin;

    public function __construct(
        Content_Indexer $content_indexer,
        AI_Generator    $ai_generator,
        History_Store   $history_store,
        Audit_Engine    $audit_engine,
        ?IndexNow       $indexnow_service,
        AdminBase       $admin
    ) {
        $this->content_indexer = $content_indexer;
        $this->ai_generator    = $ai_generator;
        $this->history_store   = $history_store;
        $this->audit_engine    = $audit_engine;
        $this->indexnow_service = $indexnow_service;
        $this->admin           = $admin;
    }

    // ------------------------------------------------------------------
    //  Sync Index
    // ------------------------------------------------------------------

    public function handle_sync_index(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You are not allowed to do that.');
        }

        check_admin_referer('ai_seo_keeper_sync_index');

        $sync_count = $this->content_indexer->sync();

        $redirect_url = add_query_arg(
            array(
                'page'   => 'ai-seo-keeper',
                'synced' => $sync_count,
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    // ------------------------------------------------------------------
    //  Generate Site Audit
    // ------------------------------------------------------------------

    public function handle_generate_site_audit(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You are not allowed to do that.');
        }

        check_admin_referer('ai_seo_keeper_generate_site_audit');

        try {
            $options   = get_option(Settings::OPTION_NAME, array());
            $model     = trim((string) ($options['model'] ?? ''));
            $max_pages = Settings::get_max_pages_for_model($model);
            $report    = $this->content_indexer->build_site_audit_report($max_pages);
            $audit  = $this->ai_generator->generate_site_audit($report);

            $this->history_store->log_generation(
                0,
                'site_audit',
                'AI SEO Keeper Site Audit',
                array(
                    'provider'       => $audit['provider'],
                    'model'          => $audit['model'],
                    'system_prompt'  => $audit['system_prompt'],
                    'user_prompt'    => $audit['user_prompt'],
                    'report_summary' => $report['summary'],
                ),
                array(
                    'audit_title'       => $audit['audit_title'],
                    'executive_summary' => $audit['executive_summary'],
                    'priority_actions'  => $audit['priority_actions'],
                    'quick_wins'        => $audit['quick_wins'],
                    'notes'             => $audit['notes'],
                    'provider'          => $audit['provider'],
                    'model'             => $audit['model'],
                )
            );

            $redirect_url = add_query_arg(
                array(
                    'page'          => 'ai-seo-keeper-audit',
                    'audit_status'  => 'success',
                    'audit_message' => rawurlencode('AI strategic audit generated successfully.'),
                ),
                admin_url('admin.php')
            );
        } catch (\Throwable $throwable) {
            $redirect_url = add_query_arg(
                array(
                    'page'          => 'ai-seo-keeper-audit',
                    'audit_status'  => 'error',
                    'audit_message' => rawurlencode($throwable->getMessage()),
                ),
                admin_url('admin.php')
            );
        }

        wp_safe_redirect($redirect_url);
        exit;
    }

    // ------------------------------------------------------------------
    //  IndexNow Submit
    // ------------------------------------------------------------------

    public function handle_submit_indexnow(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You are not allowed to do that.');
        }

        check_admin_referer('ai_seo_keeper_submit_indexnow');

        $report = $this->audit_engine->get_report(10);
        $urls   = array();

        foreach ($report['priority_rows'] as $row) {
            if (! empty($row['permalink'])) {
                $urls[] = (string) $row['permalink'];
            }
        }

        $result = $this->indexnow_service
            ? $this->indexnow_service->submit_urls($urls, 'manual_priority_queue')
            : array('status' => 'error', 'message' => 'IndexNow service is not available.');

        $redirect_url = add_query_arg(
            array(
                'page'          => 'ai-seo-keeper-audit',
                'audit_status'  => 'success' === ($result['status'] ?? '') ? 'success' : 'error',
                'audit_message' => rawurlencode((string) ($result['message'] ?? 'IndexNow request finished.')),
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    // ------------------------------------------------------------------
    //  Bulk Frontend Rollout
    // ------------------------------------------------------------------

    public function handle_bulk_frontend_rollout(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You are not allowed to do that.');
        }

        check_admin_referer('ai_seo_keeper_bulk_frontend_rollout');

        $post_ids = isset($_POST['post_ids']) ? array_map('intval', (array) wp_unslash($_POST['post_ids'])) : array();
        $mode     = isset($_POST['bulk_mode']) ? sanitize_key((string) wp_unslash($_POST['bulk_mode'])) : '';

        if (empty($post_ids) || ! in_array($mode, array('enable_frontend', 'disable_frontend'), true)) {
            $this->admin->redirect_to_audit_page('error', 'Select at least one row and a valid bulk action.');
        }

        $result  = $this->apply_bulk_frontend_gate($post_ids, 'enable_frontend' === $mode);
        $message = 'enable_frontend' === $mode
            ? sprintf('Enabled frontend output on %d page(s).', $result['updated'])
            : sprintf('Disabled frontend output on %d page(s).', $result['updated']);

        if ($result['unchanged'] > 0) {
            $message .= ' ' . sprintf('%d page(s) were already in that state.', $result['unchanged']);
        }

        if ($result['skipped_unapproved'] > 0) {
            $message .= ' ' . sprintf('%d page(s) were skipped because they do not have an approved suggestion or saved SEO data.', $result['skipped_unapproved']);
        }

        if ('enable_frontend' === $mode && ! empty($result['urls']) && $this->indexnow_service) {
            $indexnow_result = $this->indexnow_service->submit_urls($result['urls'], 'bulk_frontend_rollout');
            if (! empty($indexnow_result['message'])) {
                $message .= ' IndexNow: ' . (string) $indexnow_result['message'];
            }
        }

        $this->admin->redirect_to_audit_page('success', $message);
    }

    private function apply_bulk_frontend_gate(array $post_ids, bool $enabled): array
    {
        $updated             = 0;
        $unchanged           = 0;
        $skipped_unapproved  = 0;
        $urls                = array();

        foreach (array_values(array_unique(array_filter($post_ids))) as $post_id) {
            $post = get_post((int) $post_id);

            if (! $post instanceof \WP_Post) {
                continue;
            }

            $currently_enabled = '1' === (string) get_post_meta((int) $post_id, AdminBase::FRONTEND_ENABLE_META_KEY, true);

            if ($enabled) {
                $approved_id             = $this->history_store->get_approved_suggestion_id((int) $post_id, 'post');
                $has_saved_frontend_data = $this->admin->has_saved_frontend_data_for_post((int) $post_id);

                if ($approved_id <= 0 && ! $has_saved_frontend_data) {
                    $skipped_unapproved++;
                    continue;
                }

                if ($currently_enabled) {
                    $unchanged++;
                    continue;
                }

                update_post_meta((int) $post_id, AdminBase::FRONTEND_ENABLE_META_KEY, '1');
                $updated++;
                $urls[] = (string) get_permalink((int) $post_id);
                continue;
            }

            if (! $currently_enabled) {
                $unchanged++;
                continue;
            }

            delete_post_meta((int) $post_id, AdminBase::FRONTEND_ENABLE_META_KEY);
            $updated++;
        }

        return array(
            'updated'             => $updated,
            'unchanged'           => $unchanged,
            'skipped_unapproved'  => $skipped_unapproved,
            'urls'                => $urls,
        );
    }
}
