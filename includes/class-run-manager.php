<?php

namespace AI_SEO_Captain;

class Run_Manager
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'ai_seo_captain_runs';
    }

    public function create_run(string $name, array $page_ids, string $description = ''): int
    {
        global $wpdb;

        $page_ids = array_values(array_unique(array_map('intval', $page_ids)));

        $wpdb->insert(
            $this->table,
            array(
                'user_id'     => get_current_user_id(),
                'name'        => sanitize_text_field($name),
                'description' => sanitize_textarea_field($description),
                'page_ids'    => wp_json_encode($page_ids),
                'page_count'  => count($page_ids),
                'status'      => 'active',
                'created_at'  => current_time('mysql'),
                'updated_at'  => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );

        return (int) $wpdb->insert_id;
    }

    public function get_run(int $run_id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $run_id),
            ARRAY_A
        );

        if (! $row) {
            return null;
        }

        $row['page_ids'] = json_decode($row['page_ids'], true) ?: array();

        return $row;
    }

    public function get_all_runs(string $status = 'active'): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE status = %s ORDER BY created_at DESC",
                $status
            ),
            ARRAY_A
        );

        foreach ($rows as &$row) {
            $row['page_ids'] = json_decode($row['page_ids'], true) ?: array();
        }

        return $rows;
    }

    public function update_run_pages(int $run_id, array $page_ids): bool
    {
        global $wpdb;

        $page_ids = array_values(array_unique(array_map('intval', $page_ids)));

        return (bool) $wpdb->update(
            $this->table,
            array(
                'page_ids'   => wp_json_encode($page_ids),
                'page_count' => count($page_ids),
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $run_id),
            array('%s', '%d', '%s'),
            array('%d')
        );
    }

    public function delete_run(int $run_id): bool
    {
        global $wpdb;

        return (bool) $wpdb->delete($this->table, array('id' => $run_id), array('%d'));
    }

    public function get_active_run_ids(): array
    {
        $ids = get_user_meta(get_current_user_id(), '_ai_seo_captain_active_runs', true);

        return is_array($ids) ? array_map('intval', $ids) : array();
    }

    public function set_active_run_ids(array $ids): void
    {
        update_user_meta(
            get_current_user_id(),
            '_ai_seo_captain_active_runs',
            array_values(array_unique(array_map('intval', $ids)))
        );
    }

    public function get_combined_page_ids(): array
    {
        $run_ids  = $this->get_active_run_ids();
        $all_pids = array();

        foreach ($run_ids as $rid) {
            $run = $this->get_run($rid);
            if ($run) {
                $all_pids = array_merge($all_pids, $run['page_ids']);
            }
        }

        return array_values(array_unique($all_pids));
    }

    public function is_multi_run_selected(): bool
    {
        return count($this->get_active_run_ids()) > 1;
    }

    public function get_indexed_pages_for_selector(): array
    {
        global $wpdb;

        $index_table = $wpdb->prefix . 'ai_seo_captain_content_index';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT idx.object_id AS id, idx.title, idx.post_type, idx.slug,
                        CASE WHEN pm.meta_value IS NOT NULL THEN 1 ELSE 0 END AS has_audit
                 FROM {$index_table} idx
                 LEFT JOIN {$wpdb->postmeta} pm
                    ON pm.post_id = idx.object_id AND pm.meta_key = %s
                 WHERE idx.status = 'publish'
                 ORDER BY idx.post_type ASC, idx.title ASC",
                '_ai_seo_captain_page_audit'
            ),
            ARRAY_A
        );
    }

    /**
     * Get all runs enriched with completed_steps data.
     */
    public function get_runs_with_status(): array
    {
        return $this->get_all_runs();
    }

    /**
     * Mark a step as completed for a specific run.
     *
     * @param int    $run_id Run ID.
     * @param string $step   'metadata' or 'audit'.
     */
    public function mark_step_complete(int $run_id, string $step): void
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT completed_steps FROM {$this->table} WHERE id = %d", $run_id),
            ARRAY_A
        );

        if (! $row) {
            return;
        }

        $current = array_filter(explode(',', (string) $row['completed_steps']));
        if (! in_array($step, $current, true)) {
            $current[] = $step;
        }

        $wpdb->update(
            $this->table,
            array(
                'completed_steps' => implode(',', $current),
                'updated_at'      => current_time('mysql'),
            ),
            array('id' => $run_id),
            array('%s', '%s'),
            array('%d')
        );
    }

    /**
     * Check if a run has completed a specific step.
     *
     * @param array  $run  Run data (must include 'completed_steps').
     * @param string $step 'metadata' or 'audit'.
     */
    public static function has_completed_step(array $run, string $step): bool
    {
        $steps = isset($run['completed_steps']) ? (string) $run['completed_steps'] : '';
        return in_array($step, explode(',', $steps), true);
    }

    /**
     * Check if a run has completed BOTH steps.
     */
    public static function is_fully_complete(array $run): bool
    {
        return self::has_completed_step($run, 'metadata')
            && self::has_completed_step($run, 'audit');
    }
}
