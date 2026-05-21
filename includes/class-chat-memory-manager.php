<?php

namespace AI_SEO_Captain;

/**
 * Token-aware conversation history manager for AI chat.
 *
 * Ensures the first prompt always gets full page context (zero history cost),
 * and subsequent prompts intelligently trim/summarize older messages to fit
 * within the model's context window.
 *
 * Works for both Editor Chat (per-page) and Site Chat (site-wide).
 */
class Chat_Memory_Manager
{
    /**
     * Average characters per token (conservative estimate for English text).
     * OpenAI/Gemini tokenizers average ~3.5-4 chars/token; we use 3.5 to be safe.
     */
    private const CHARS_PER_TOKEN = 3.5;

    /**
     * Safety margin: use only 60% of context window for input.
     */
    private const INPUT_BUDGET_RATIO = 0.6;

    /**
     * Reserve tokens for system prompt + response generation.
     */
    private const SYSTEM_OVERHEAD_TOKENS = 4000;

    /**
     * When remaining history budget is below this fraction of total input budget,
     * flag memory pressure to the frontend.
     */
    private const MEMORY_PRESSURE_THRESHOLD = 0.15;

    /**
     * Maximum conversation turns (user+assistant pairs) to ever load from DB,
     * regardless of token budget. Prevents excessive DB reads.
     */
    private const MAX_DB_MESSAGES = 30;

    /**
     * Estimate token count from a string using character-based heuristic.
     */
    public static function estimate_tokens(string $text): int
    {
        $char_count = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);

        return max(1, (int) ceil($char_count / self::CHARS_PER_TOKEN));
    }

    /**
     * Build a token-budgeted conversation history for the AI prompt.
     *
     * @param array  $all_messages     Recent messages from DB (chronological order, newest last).
     *                                 Each entry: ['role'=>..., 'message'=>..., 'reply'=>..., ...]
     * @param string $fixed_context    The assembled prompt context EXCLUDING conversation history
     *                                 (SEO data, page content, system prompt, user's current message).
     * @param string $model_id         The AI model ID (for context window lookup).
     *
     * @return array{
     *     conversation_lines: string[],
     *     memory_pressure: bool,
     *     history_tokens_used: int,
     *     history_tokens_budget: int,
     *     messages_included: int,
     *     messages_summarized: int,
     *     summary_text: string
     * }
     */
    public static function budget_history(array $all_messages, string $fixed_context, string $model_id): array
    {
        $context_window = Settings::get_context_window($model_id);
        $input_budget   = (int) ($context_window * self::INPUT_BUDGET_RATIO);
        $fixed_tokens   = self::estimate_tokens($fixed_context) + self::SYSTEM_OVERHEAD_TOKENS;
        $history_budget = max(0, $input_budget - $fixed_tokens);

        // No history = first prompt. Full context, zero history cost.
        if (empty($all_messages)) {
            return array(
                'conversation_lines'  => array(),
                'memory_pressure'     => false,
                'history_tokens_used' => 0,
                'history_tokens_budget' => $history_budget,
                'messages_included'   => 0,
                'messages_summarized' => 0,
                'summary_text'        => '',
            );
        }

        // Estimate tokens per message.
        $message_tokens = array();
        foreach ($all_messages as $i => $msg) {
            $role    = isset($msg['role']) ? (string) $msg['role'] : '';
            $content = 'user' === $role
                ? (string) ($msg['message'] ?? '')
                : (string) ($msg['reply'] ?? '');
            $message_tokens[$i] = self::estimate_tokens(strtoupper($role) . ': ' . $content);
        }

        // Try to fit all messages (newest-first priority).
        $total_msg_count = count($all_messages);
        $reversed_indices = array_reverse(array_keys($all_messages));

        $included_indices = array();
        $tokens_used      = 0;

        foreach ($reversed_indices as $idx) {
            $cost = $message_tokens[$idx];
            if (($tokens_used + $cost) <= $history_budget) {
                $included_indices[] = $idx;
                $tokens_used       += $cost;
            } else {
                break; // Budget exhausted — remaining (older) messages need summarizing.
            }
        }

        // Restore chronological order for included messages.
        sort($included_indices);

        $summarized_indices = array();
        foreach (array_keys($all_messages) as $idx) {
            if (! in_array($idx, $included_indices, true)) {
                $summarized_indices[] = $idx;
            }
        }

        // Build summary of excluded (older) messages.
        $summary_text = '';
        if (! empty($summarized_indices)) {
            $summary_text = self::summarize_messages($all_messages, $summarized_indices);
            $summary_tokens = self::estimate_tokens($summary_text);

            // If summary itself is too large, truncate it to fit remaining budget.
            $remaining_for_summary = max(0, $history_budget - $tokens_used);
            if ($summary_tokens > $remaining_for_summary) {
                $max_chars = (int) ($remaining_for_summary * self::CHARS_PER_TOKEN);
                $summary_text = self::truncate_at_word($summary_text, $max_chars);
                $summary_tokens = self::estimate_tokens($summary_text);
            }

            $tokens_used += $summary_tokens;
        }

        // Build conversation lines.
        $conversation_lines = array();

        if ('' !== $summary_text) {
            $conversation_lines[] = '[EARLIER CONVERSATION SUMMARY] ' . $summary_text;
        }

        foreach ($included_indices as $idx) {
            $msg     = $all_messages[$idx];
            $role    = isset($msg['role']) ? (string) $msg['role'] : '';
            $content = 'user' === $role
                ? (string) ($msg['message'] ?? '')
                : (string) ($msg['reply'] ?? '');

            if ('' !== trim($content)) {
                $conversation_lines[] = strtoupper($role) . ': ' . $content;
            }
        }

        if (empty($conversation_lines)) {
            $conversation_lines[] = '- No recent chat context.';
        }

        // Memory pressure check.
        $memory_pressure = ($history_budget > 0)
            && ($history_budget - $tokens_used) < ($input_budget * self::MEMORY_PRESSURE_THRESHOLD);

        return array(
            'conversation_lines'    => $conversation_lines,
            'memory_pressure'       => $memory_pressure,
            'history_tokens_used'   => $tokens_used,
            'history_tokens_budget' => $history_budget,
            'messages_included'     => count($included_indices),
            'messages_summarized'   => count($summarized_indices),
            'summary_text'          => $summary_text,
        );
    }

    /**
     * Build a compact summary of older messages that didn't fit in the token budget.
     *
     * Extracts key facts from each message:
     * - User questions (shortened)
     * - AI suggestions (titles, descriptions, key recommendations)
     * - Decisions made (approved, rejected, changed)
     */
    private static function summarize_messages(array $all_messages, array $indices): string
    {
        $summary_parts = array();
        $turn_count    = 0;

        foreach ($indices as $idx) {
            $msg  = $all_messages[$idx];
            $role = isset($msg['role']) ? (string) $msg['role'] : '';

            if ('user' === $role) {
                $question = trim((string) ($msg['message'] ?? ''));
                if ('' !== $question) {
                    // Keep user questions concise — first 200 chars.
                    $summary_parts[] = 'User asked: ' . self::truncate_at_word($question, 200);
                    ++$turn_count;
                }
            } elseif ('assistant' === $role) {
                $reply = trim((string) ($msg['reply'] ?? ''));
                if ('' !== $reply) {
                    // Extract key AI recommendations — first 300 chars.
                    $summary_parts[] = 'AI replied: ' . self::truncate_at_word($reply, 300);
                }

                // Capture any suggested metadata changes.
                $suggested_title = trim((string) ($msg['suggested_title'] ?? ''));
                $suggested_desc  = trim((string) ($msg['suggested_description'] ?? ''));
                if ('' !== $suggested_title || '' !== $suggested_desc) {
                    $meta_note = 'AI suggested:';
                    if ('' !== $suggested_title) {
                        $meta_note .= ' title="' . $suggested_title . '"';
                    }
                    if ('' !== $suggested_desc) {
                        $meta_note .= ' desc="' . self::truncate_at_word($suggested_desc, 120) . '"';
                    }
                    $summary_parts[] = $meta_note;
                }
            }
        }

        if (empty($summary_parts)) {
            return '';
        }

        return 'Summary of ' . $turn_count . ' earlier exchange(s): ' . implode(' | ', $summary_parts);
    }

    /**
     * Truncate text at word boundary.
     */
    private static function truncate_at_word(string $text, int $max_chars): string
    {
        if (function_exists('mb_strlen') && mb_strlen($text) <= $max_chars) {
            return $text;
        }
        if (! function_exists('mb_strlen') && strlen($text) <= $max_chars) {
            return $text;
        }

        $truncated = function_exists('mb_substr') ? mb_substr($text, 0, $max_chars) : substr($text, 0, $max_chars);
        $last_space = strrpos($truncated, ' ');

        if (false !== $last_space && $last_space > ($max_chars * 0.6)) {
            $truncated = substr($truncated, 0, $last_space);
        }

        return $truncated . '…';
    }
}
