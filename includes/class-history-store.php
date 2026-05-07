<?php

namespace AI_SEO_Keeper;

class History_Store
{
    private const APPROVED_MESSAGE_META_KEY = '_ai_seo_keeper_approved_message_id';

    private const SITE_AUDIT_OBJECT_TYPE = 'site_audit';

    private const CHAT_OBJECT_TYPE = 'post_chat';

    public function log_generation(int $object_id, string $object_type, string $conversation_title, array $request_payload, array $response_payload): array
    {
        global $wpdb;

        $conversation_id = $this->get_or_create_conversation($object_id, $object_type, $conversation_title);
        $messages_table = $wpdb->prefix . 'ai_seo_keeper_messages';
        $created_at = current_time('mysql', true);

        $user_inserted = $wpdb->insert(
            $messages_table,
            array(
                'conversation_id' => $conversation_id,
                'role' => 'user',
                'content' => wp_json_encode($request_payload),
                'created_at' => $created_at,
            ),
            array('%d', '%s', '%s', '%s')
        );

        if (false === $user_inserted) {
            throw new \RuntimeException('Could not store the AI request history.');
        }

        $assistant_inserted = $wpdb->insert(
            $messages_table,
            array(
                'conversation_id' => $conversation_id,
                'role' => 'assistant',
                'content' => wp_json_encode($response_payload),
                'created_at' => $created_at,
            ),
            array('%d', '%s', '%s', '%s')
        );

        if (false === $assistant_inserted) {
            throw new \RuntimeException('Could not store the AI suggestion history.');
        }

        $this->touch_conversation($conversation_id, $created_at);

        return array(
            'message_id' => (int) $wpdb->insert_id,
            'created_at' => $created_at,
        );
    }

    public function get_recent_suggestions(int $object_id, string $object_type, int $limit = 5): array
    {
        global $wpdb;

        $conversations_table = $wpdb->prefix . 'ai_seo_keeper_conversations';
        $messages_table = $wpdb->prefix . 'ai_seo_keeper_messages';
        $limit = max(1, min(10, $limit));
        $approved_message_id = $this->get_approved_suggestion_id($object_id, $object_type);

        $sql = $wpdb->prepare(
            "SELECT m.id, m.content, m.created_at
            FROM {$messages_table} m
            INNER JOIN {$conversations_table} c ON c.id = m.conversation_id
            WHERE c.object_type = %s
                AND c.object_id = %d
                AND m.role = %s
            ORDER BY m.id DESC
            LIMIT %d",
            $object_type,
            $object_id,
            'assistant',
            $limit
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (! is_array($rows)) {
            return array();
        }

        $history = array();

        foreach ($rows as $row) {
            $payload = json_decode((string) $row['content'], true);

            if (! is_array($payload)) {
                continue;
            }

            $history[] = array(
                'id' => (int) $row['id'],
                'created_at' => (string) $row['created_at'],
                'seo_title' => isset($payload['seo_title']) ? (string) $payload['seo_title'] : '',
                'meta_description' => isset($payload['meta_description']) ? (string) $payload['meta_description'] : '',
                'notes' => isset($payload['notes']) ? (string) $payload['notes'] : '',
                'provider' => isset($payload['provider']) ? (string) $payload['provider'] : '',
                'model' => isset($payload['model']) ? (string) $payload['model'] : '',
                'is_approved' => (int) $row['id'] === $approved_message_id,
            );
        }

        return $history;
    }

    public function approve_suggestion(int $object_id, string $object_type, int $message_id): array
    {
        $suggestion = $this->get_suggestion_by_id($object_id, $object_type, $message_id);

        if (empty($suggestion)) {
            throw new \RuntimeException('The selected suggestion could not be found.');
        }

        if ('post' !== $object_type) {
            throw new \RuntimeException('Suggestion approval is only supported for posts and pages right now.');
        }

        update_post_meta($object_id, self::APPROVED_MESSAGE_META_KEY, $message_id);

        $suggestion['is_approved'] = true;

        return $suggestion;
    }

    public function get_approved_suggestion_id(int $object_id, string $object_type): int
    {
        if ('post' !== $object_type) {
            return 0;
        }

        return (int) get_post_meta($object_id, self::APPROVED_MESSAGE_META_KEY, true);
    }

    public function get_approved_suggestion(int $object_id, string $object_type): array
    {
        $message_id = $this->get_approved_suggestion_id($object_id, $object_type);

        if ($message_id <= 0) {
            return array();
        }

        return $this->get_suggestion_by_id($object_id, $object_type, $message_id);
    }

    public function get_recent_site_audits(int $limit = 5): array
    {
        global $wpdb;

        $conversations_table = $wpdb->prefix . 'ai_seo_keeper_conversations';
        $messages_table = $wpdb->prefix . 'ai_seo_keeper_messages';
        $limit = max(1, min(10, $limit));

        $sql = $wpdb->prepare(
            "SELECT m.id, m.content, m.created_at
            FROM {$messages_table} m
            INNER JOIN {$conversations_table} c ON c.id = m.conversation_id
            WHERE c.object_type = %s
                AND c.object_id = %d
                AND m.role = %s
            ORDER BY m.id DESC
            LIMIT %d",
            self::SITE_AUDIT_OBJECT_TYPE,
            0,
            'assistant',
            $limit
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (! is_array($rows)) {
            return array();
        }

        $history = array();

        foreach ($rows as $row) {
            $payload = json_decode((string) $row['content'], true);

            if (! is_array($payload)) {
                continue;
            }

            $history[] = array(
                'id' => (int) $row['id'],
                'created_at' => (string) $row['created_at'],
                'audit_title' => isset($payload['audit_title']) ? (string) $payload['audit_title'] : 'AI SEO Keeper Site Audit',
                'executive_summary' => isset($payload['executive_summary']) ? (string) $payload['executive_summary'] : '',
                'priority_actions' => isset($payload['priority_actions']) && is_array($payload['priority_actions']) ? array_values($payload['priority_actions']) : array(),
                'quick_wins' => isset($payload['quick_wins']) && is_array($payload['quick_wins']) ? array_values($payload['quick_wins']) : array(),
                'notes' => isset($payload['notes']) ? (string) $payload['notes'] : '',
                'provider' => isset($payload['provider']) ? (string) $payload['provider'] : '',
                'model' => isset($payload['model']) ? (string) $payload['model'] : '',
            );
        }

        return $history;
    }

    public function get_recent_chat_messages(int $object_id, int $limit = 10): array
    {
        global $wpdb;

        $conversations_table = $wpdb->prefix . 'ai_seo_keeper_conversations';
        $messages_table = $wpdb->prefix . 'ai_seo_keeper_messages';
        $limit = max(1, min(20, $limit));

        $sql = $wpdb->prepare(
            "SELECT m.id, m.role, m.content, m.created_at
            FROM {$messages_table} m
            INNER JOIN {$conversations_table} c ON c.id = m.conversation_id
            WHERE c.object_type = %s
                AND c.object_id = %d
            ORDER BY m.id DESC
            LIMIT %d",
            self::CHAT_OBJECT_TYPE,
            $object_id,
            $limit
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (! is_array($rows)) {
            return array();
        }

        $rows = array_reverse($rows);
        $messages = array();

        foreach ($rows as $row) {
            $payload = json_decode((string) $row['content'], true);

            if (! is_array($payload)) {
                continue;
            }

            $messages[] = array(
                'id' => (int) $row['id'],
                'role' => (string) $row['role'],
                'created_at' => (string) $row['created_at'],
                'message' => isset($payload['message']) ? (string) $payload['message'] : '',
                'reply' => isset($payload['reply']) ? (string) $payload['reply'] : '',
                'suggested_title' => isset($payload['suggested_title']) ? (string) $payload['suggested_title'] : '',
                'suggested_description' => isset($payload['suggested_description']) ? (string) $payload['suggested_description'] : '',
                'notes' => isset($payload['notes']) ? (string) $payload['notes'] : '',
                'provider' => isset($payload['provider']) ? (string) $payload['provider'] : '',
                'model' => isset($payload['model']) ? (string) $payload['model'] : '',
            );
        }

        return $messages;
    }

    public function get_suggestion_by_id(int $object_id, string $object_type, int $message_id): array
    {
        global $wpdb;

        $conversations_table = $wpdb->prefix . 'ai_seo_keeper_conversations';
        $messages_table = $wpdb->prefix . 'ai_seo_keeper_messages';

        $sql = $wpdb->prepare(
            "SELECT m.id, m.content, m.created_at
            FROM {$messages_table} m
            INNER JOIN {$conversations_table} c ON c.id = m.conversation_id
            WHERE c.object_type = %s
                AND c.object_id = %d
                AND m.role = %s
                AND m.id = %d
            LIMIT 1",
            $object_type,
            $object_id,
            'assistant',
            $message_id
        );

        $row = $wpdb->get_row($sql, ARRAY_A);

        if (! is_array($row)) {
            return array();
        }

        $payload = json_decode((string) $row['content'], true);

        if (! is_array($payload)) {
            return array();
        }

        return array(
            'id' => (int) $row['id'],
            'created_at' => (string) $row['created_at'],
            'seo_title' => isset($payload['seo_title']) ? (string) $payload['seo_title'] : '',
            'meta_description' => isset($payload['meta_description']) ? (string) $payload['meta_description'] : '',
            'notes' => isset($payload['notes']) ? (string) $payload['notes'] : '',
            'provider' => isset($payload['provider']) ? (string) $payload['provider'] : '',
            'model' => isset($payload['model']) ? (string) $payload['model'] : '',
            'is_approved' => (int) $row['id'] === $this->get_approved_suggestion_id($object_id, $object_type),
        );
    }

    private function get_or_create_conversation(int $object_id, string $object_type, string $title): int
    {
        global $wpdb;

        $conversations_table = $wpdb->prefix . 'ai_seo_keeper_conversations';

        $conversation_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$conversations_table} WHERE object_type = %s AND object_id = %d ORDER BY id ASC LIMIT 1",
                $object_type,
                $object_id
            )
        );

        if ($conversation_id > 0) {
            return $conversation_id;
        }

        $created_at = current_time('mysql', true);
        $inserted = $wpdb->insert(
            $conversations_table,
            array(
                'user_id' => (int) get_current_user_id(),
                'object_id' => $object_id,
                'object_type' => $object_type,
                'title' => $title,
                'created_at' => $created_at,
                'updated_at' => $created_at,
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s')
        );

        if (false === $inserted) {
            throw new \RuntimeException('Could not create the AI history conversation.');
        }

        return (int) $wpdb->insert_id;
    }

    private function touch_conversation(int $conversation_id, string $updated_at): void
    {
        global $wpdb;

        $conversations_table = $wpdb->prefix . 'ai_seo_keeper_conversations';

        $wpdb->update(
            $conversations_table,
            array('updated_at' => $updated_at),
            array('id' => $conversation_id),
            array('%s'),
            array('%d')
        );
    }

    public function clear_chat_messages(int $object_id): int
    {
        global $wpdb;

        $conversations_table = $wpdb->prefix . 'ai_seo_keeper_conversations';
        $messages_table = $wpdb->prefix . 'ai_seo_keeper_messages';

        $conversation_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$conversations_table} WHERE object_type = %s AND object_id = %d",
                self::CHAT_OBJECT_TYPE,
                $object_id
            )
        );

        if (empty($conversation_ids)) {
            return 0;
        }

        $ids_in = implode(',', array_map('intval', $conversation_ids));
        $deleted = (int) $wpdb->query("DELETE FROM {$messages_table} WHERE conversation_id IN ({$ids_in})");
        $wpdb->query("DELETE FROM {$conversations_table} WHERE id IN ({$ids_in})");

        return $deleted;
    }

    /**
     * Update the status of the most recent content_edit plan for a post.
     *
     * @param int    $object_id The post ID.
     * @param string $status    New status: 'pending' or 'published'.
     */
    public function update_content_edit_status(int $object_id, string $status): void
    {
        global $wpdb;

        $conversations_table = $wpdb->prefix . 'ai_seo_keeper_conversations';
        $messages_table = $wpdb->prefix . 'ai_seo_keeper_messages';

        // Find the most recent content_edit conversation for this post.
        $conversation_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$conversations_table}
                 WHERE object_type = 'content_edit' AND object_id = %d
                 ORDER BY updated_at DESC LIMIT 1",
                $object_id
            )
        );

        if (! $conversation_id) {
            return;
        }

        // Find the most recent assistant message in that conversation.
        $message_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$messages_table}
                 WHERE conversation_id = %d AND role = 'assistant'
                 ORDER BY id DESC LIMIT 1",
                $conversation_id
            )
        );

        if (! $message_id) {
            return;
        }

        $raw = $wpdb->get_var(
            $wpdb->prepare("SELECT content FROM {$messages_table} WHERE id = %d", $message_id)
        );

        $payload = json_decode((string) $raw, true);
        if (! is_array($payload)) {
            return;
        }

        $payload['content_edit_status'] = $status;
        if ('published' === $status) {
            $payload['published_at'] = current_time('mysql');
        }

        $wpdb->update(
            $messages_table,
            array('content' => wp_json_encode($payload)),
            array('id' => (int) $message_id),
            array('%s'),
            array('%d')
        );
    }

    /**
     * Get recent content edit plans for a post.
     *
     * @param int $object_id The post ID.
     * @param int $limit     Maximum entries to return.
     * @return array
     */
    public function get_recent_content_edits(int $object_id, int $limit = 5): array
    {
        global $wpdb;

        $conversations_table = $wpdb->prefix . 'ai_seo_keeper_conversations';
        $messages_table = $wpdb->prefix . 'ai_seo_keeper_messages';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.id, m.content, m.created_at
                 FROM {$messages_table} m
                 JOIN {$conversations_table} c ON c.id = m.conversation_id
                 WHERE c.object_type = 'content_edit'
                   AND c.object_id = %d
                   AND m.role = 'assistant'
                 ORDER BY m.id DESC
                 LIMIT %d",
                $object_id,
                $limit
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return array();
        }

        $entries = array();
        foreach ($rows as $row) {
            $payload = json_decode((string) $row['content'], true);
            if (! is_array($payload)) {
                continue;
            }

            $entries[] = array(
                'id' => (int) $row['id'],
                'summary' => $payload['content_edit_summary'] ?? '',
                'change_count' => (int) ($payload['content_edit_count'] ?? 0),
                'status' => $payload['content_edit_status'] ?? 'pending',
                'published_at' => $payload['published_at'] ?? '',
                'created_at' => $row['created_at'] ?? '',
                'changes' => $payload['changes'] ?? array(),
            );
        }

        return $entries;
    }
}
