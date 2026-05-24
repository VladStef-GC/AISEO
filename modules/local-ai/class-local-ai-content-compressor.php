<?php

/**
 * Local AI Content Compressor — Progressive smart compression for small context windows.
 *
 * When a prompt exceeds the model's context window, this engine progressively
 * compresses the HTML content (from sanitize_for_ai) while preserving everything
 * that matters for SEO: headings, images, links, tables, lists.
 *
 * Compression levels (each builds on the previous):
 *   Level 0 — None (fits as-is)
 *   Level 1 — Trim long paragraphs to first sentence
 *   Level 2 — Keep only key paragraphs (first after each heading + intro/conclusion)
 *   Level 3 — Structural skeleton (headings, images, links, tables, lists only)
 *   Level 4 — Ultra-compact (headings + element counts, minimal text)
 *
 * ONLY used for Local AI calls. Cloud providers have massive context windows
 * and never need compression.
 *
 * @package AI_SEO_Captain\Modules\LocalAI
 */

namespace AI_SEO_Captain\Modules\LocalAI;

defined('ABSPATH') || exit;

class Local_AI_Content_Compressor
{
    // ------------------------------------------------------------------
    //  Constants
    // ------------------------------------------------------------------

    /** Characters per token (matches Chat_Memory_Manager). */
    const CHARS_PER_TOKEN = 3.5;

    /** Fraction of context window reserved for input (rest is for output). */
    const INPUT_RATIO = 0.6;

    /** Minimum visible text length before a paragraph is considered "long". */
    const TRIM_THRESHOLD = 150;

    /** Minimum visible chars before accepting a sentence break. */
    const MIN_SENTENCE_CHARS = 40;

    /** Compression level constants. */
    const LEVEL_NONE    = 0;
    const LEVEL_TRIM    = 1;
    const LEVEL_SPARSE  = 2;
    const LEVEL_SKELETON = 3;
    const LEVEL_MINIMAL = 4;

    /** Human-readable level descriptions. */
    const LEVEL_LABELS = array(
        0 => 'none',
        1 => 'paragraphs shortened',
        2 => 'non-key paragraphs removed',
        3 => 'text removed — structure only',
        4 => 'headings and counts only',
    );

    /**
     * Content section markers used in user prompts.
     *
     * Everything BEFORE these markers is SEO metadata / instructions
     * and must NEVER be compressed. Only the HTML body that follows
     * the marker is eligible for compression.
     */
    const CONTENT_MARKERS = array(
        'Main page content: ',
        "Page content (HTML with headings, images, links, and all elements):\n",
        "Full page content:\n",
    );

    // ------------------------------------------------------------------
    //  Public API
    // ------------------------------------------------------------------

    /**
     * Fit a messages array to the model's context window.
     *
     * Tries progressive compression levels on the largest user message
     * until the total prompt fits within the input budget, or Level 4
     * (maximum compression) is reached.
     *
     * @param array  $messages       OpenAI-format messages [{role, content}, …].
     * @param int    $context_window Model's total context window in tokens.
     * @return array{
     *     messages: array,
     *     compressed: bool,
     *     level: int,
     *     level_label: string,
     *     original_tokens: int,
     *     final_tokens: int,
     *     context_window: int
     * }
     */
    public static function fit_messages(array $messages, int $context_window): array
    {
        $budget_chars = (int) ($context_window * self::INPUT_RATIO * self::CHARS_PER_TOKEN);

        // Calculate total character count across all messages.
        $total_chars = 0;
        foreach ($messages as $msg) {
            $total_chars += mb_strlen($msg['content'] ?? '');
        }

        // Fits already — nothing to do.
        if ($total_chars <= $budget_chars) {
            return self::build_result(
                $messages,
                self::LEVEL_NONE,
                self::estimate_tokens($total_chars),
                self::estimate_tokens($total_chars),
                $context_window
            );
        }

        // Find the largest user message (the one with page content).
        $target_idx = null;
        $target_len = 0;
        foreach ($messages as $i => $msg) {
            if ('user' === ($msg['role'] ?? '') && mb_strlen($msg['content'] ?? '') > $target_len) {
                $target_idx = $i;
                $target_len = mb_strlen($msg['content']);
            }
        }

        if (null === $target_idx) {
            // No user message found — can't compress.
            return self::build_result(
                $messages,
                self::LEVEL_NONE,
                self::estimate_tokens($total_chars),
                self::estimate_tokens($total_chars),
                $context_window
            );
        }

        $original_tokens = self::estimate_tokens($total_chars);
        $user_content    = $messages[$target_idx]['content'];

        // ── Split: SEO metadata (never compressed) vs HTML body (compressible) ──
        // All SEO fields (title drafts, meta descriptions, focus keyphrase, audit
        // results, hierarchy, GSC data, etc.) sit BEFORE the content marker and
        // are ALWAYS sent to the AI in full. Only the page HTML body is compressed.
        list($seo_prefix, $html_body) = self::split_at_content($user_content);

        if ('' === $html_body) {
            // No content marker found — the prompt is all metadata / instructions.
            // Cannot compress safely, return as-is.
            return self::build_result(
                $messages,
                self::LEVEL_NONE,
                $original_tokens,
                $original_tokens,
                $context_window
            );
        }

        $prefix_len = mb_strlen($seo_prefix);
        $body_len   = mb_strlen($html_body);

        // Try each compression level on the HTML body only.
        foreach (array(self::LEVEL_TRIM, self::LEVEL_SPARSE, self::LEVEL_SKELETON, self::LEVEL_MINIMAL) as $level) {
            $compressed_body = self::compress($html_body, $level);

            $new_total = $total_chars - $target_len + $prefix_len + mb_strlen($compressed_body);

            if ($new_total <= $budget_chars || self::LEVEL_MINIMAL === $level) {
                // Add transparency note so AI knows content was condensed.
                $final_tokens = self::estimate_tokens($new_total);
                $reassembled  = self::prepend_compression_note(
                    $seo_prefix . $compressed_body,
                    $level,
                    $original_tokens,
                    $final_tokens,
                    $context_window
                );

                $messages[$target_idx]['content'] = $reassembled;

                return self::build_result($messages, $level, $original_tokens, $final_tokens, $context_window);
            }
        }

        // Should never reach here, but safety net.
        return self::build_result($messages, self::LEVEL_NONE, $original_tokens, $original_tokens, $context_window);
    }

    /**
     * Compress HTML content at the given level.
     *
     * Each level is progressively more aggressive. The input should be
     * a user prompt string containing sanitized HTML (from sanitize_for_ai).
     * Non-HTML portions of the string (prompt instructions) are untouched.
     *
     * @param string $text  User prompt text (mix of instructions + sanitized HTML).
     * @param int    $level Compression level (1–4).
     * @return string Compressed text.
     */
    public static function compress(string $text, int $level): string
    {
        switch ($level) {
            case self::LEVEL_TRIM:
                return self::level_trim($text);

            case self::LEVEL_SPARSE:
                return self::level_sparse($text);

            case self::LEVEL_SKELETON:
                return self::level_skeleton($text);

            case self::LEVEL_MINIMAL:
                return self::level_minimal($text);

            default:
                return $text;
        }
    }

    // ------------------------------------------------------------------
    //  Level 1 — Trim long paragraphs to first sentence
    // ------------------------------------------------------------------

    private static function level_trim(string $text): string
    {
        // Trim long <p> blocks.
        $text = preg_replace_callback(
            '/<p\b[^>]*>(.*?)<\/p>/is',
            function ($m) {
                return self::trim_block($m[0], $m[1], 'p');
            },
            $text
        );

        // Trim long <blockquote> blocks.
        $text = preg_replace_callback(
            '/<blockquote\b[^>]*>(.*?)<\/blockquote>/is',
            function ($m) {
                return self::trim_block($m[0], $m[1], 'blockquote');
            },
            $text
        );

        // Trim long <pre> / <code> blocks (keep first 3 lines).
        $text = preg_replace_callback(
            '/<pre\b[^>]*>(.*?)<\/pre>/is',
            function ($m) {
                $inner = $m[1];
                $lines = explode("\n", $inner);
                if (count($lines) > 3) {
                    return '<pre>' . implode("\n", array_slice($lines, 0, 3)) . "\n[…]</pre>";
                }
                return $m[0];
            },
            $text
        );

        // Trim long <li> items.
        $text = preg_replace_callback(
            '/<li\b[^>]*>(.*?)<\/li>/is',
            function ($m) {
                $plain_len = mb_strlen(strip_tags($m[1]));
                if ($plain_len > 200) {
                    $first = self::first_sentence($m[1]);
                    return '<li>' . $first . ' […]</li>';
                }
                return $m[0];
            },
            $text
        );

        return $text;
    }

    /**
     * Trim a block element to its first sentence if it exceeds the threshold.
     */
    private static function trim_block(string $full_match, string $inner, string $tag): string
    {
        $plain_len = mb_strlen(strip_tags($inner));

        if ($plain_len <= self::TRIM_THRESHOLD) {
            return $full_match;
        }

        $first = self::first_sentence($inner);

        // Only trim if the first sentence is meaningfully shorter.
        if (mb_strlen($first) < mb_strlen($inner) * 0.85) {
            return '<' . $tag . '>' . $first . ' […]</' . $tag . '>';
        }

        return $full_match;
    }

    // ------------------------------------------------------------------
    //  Level 2 — Keep only key paragraphs
    // ------------------------------------------------------------------

    private static function level_sparse(string $text): string
    {
        // First, apply Level 1 trimming to shorten what we keep.
        $text = self::level_trim($text);

        // Split the text by <p>...</p> blocks.
        // Strategy: keep the first <p> after each heading, plus the very first
        // and very last <p> in the entire text. Remove all others.
        $parts  = preg_split('/(<p\b[^>]*>.*?<\/p>)/is', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $total_p = 0;

        // First pass: count total <p> blocks.
        foreach ($parts as $part) {
            if (self::is_p_block($part)) {
                $total_p++;
            }
        }

        if ($total_p <= 3) {
            return $text; // Too few paragraphs — nothing to remove.
        }

        // Second pass: decide which <p> blocks to keep.
        $result          = '';
        $p_index         = 0;
        $heading_just_seen = true; // Treat document start as "after heading".
        $removed_count   = 0;

        foreach ($parts as $part) {
            if (self::is_p_block($part)) {
                $p_index++;
                $is_first = (1 === $p_index);
                $is_last  = ($p_index === $total_p);
                $is_after_heading = $heading_just_seen;

                if ($is_first || $is_last || $is_after_heading) {
                    $result .= $part;
                } else {
                    $removed_count++;
                }

                $heading_just_seen = false;
            } else {
                $result .= $part;

                // Check if this non-paragraph segment contains a heading.
                if (preg_match('/<h[1-6]\b/i', $part)) {
                    $heading_just_seen = true;
                }
            }
        }

        // Also trim <blockquote> blocks more aggressively — first sentence only.
        $result = preg_replace_callback(
            '/<blockquote\b[^>]*>(.*?)<\/blockquote>/is',
            function ($m) {
                $first = self::first_sentence($m[1]);
                if (mb_strlen($first) < mb_strlen($m[1])) {
                    return '<blockquote>' . $first . ' […]</blockquote>';
                }
                return $m[0];
            },
            $result
        );

        // Remove <pre>/<code> blocks entirely at this level.
        $result = preg_replace('/<pre\b[^>]*>.*?<\/pre>/is', '[code block omitted]', $result);

        return $result;
    }

    // ------------------------------------------------------------------
    //  Level 3 — Structural skeleton
    // ------------------------------------------------------------------

    private static function level_skeleton(string $text): string
    {
        // Remove ALL <p> blocks.
        $text = preg_replace('/<p\b[^>]*>.*?<\/p>/is', '', $text);

        // Remove ALL <blockquote> blocks.
        $text = preg_replace('/<blockquote\b[^>]*>.*?<\/blockquote>/is', '', $text);

        // Remove <pre>/<code> blocks.
        $text = preg_replace('/<pre\b[^>]*>.*?<\/pre>/is', '', $text);

        // Trim <li> items to first sentence (keep list structure).
        $text = preg_replace_callback(
            '/<li\b[^>]*>(.*?)<\/li>/is',
            function ($m) {
                $plain_len = mb_strlen(strip_tags($m[1]));
                if ($plain_len > 100) {
                    $first = self::first_sentence($m[1]);
                    return '<li>' . $first . '</li>';
                }
                return $m[0];
            },
            $text
        );

        // Collapse blank lines.
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    // ------------------------------------------------------------------
    //  Level 4 — Ultra-compact
    // ------------------------------------------------------------------

    private static function level_minimal(string $text): string
    {
        // Start from Level 3.
        $text = self::level_skeleton($text);

        // Remove tables entirely.
        $text = preg_replace('/<table\b[^>]*>.*?<\/table>/is', '[table omitted]', $text);

        // Remove lists entirely.
        $text = preg_replace('/<(?:ul|ol)\b[^>]*>.*?<\/(?:ul|ol)>/is', '[list omitted]', $text);
        // Catch any orphaned <li> tags.
        $text = preg_replace('/<li\b[^>]*>.*?<\/li>/is', '', $text);

        // Remove definition lists.
        $text = preg_replace('/<dl\b[^>]*>.*?<\/dl>/is', '[list omitted]', $text);

        // Convert images to compact text placeholders.
        $text = preg_replace_callback(
            '/<img\b([^>]*)>/i',
            function ($m) {
                $alt = '';
                if (preg_match('/\balt\s*=\s*["\']([^"\']*)/i', $m[1], $am)) {
                    $alt = trim($am[1]);
                }
                return '[image' . ('' !== $alt ? ': ' . $alt : '') . ']';
            },
            $text
        );

        // Unwrap links — keep anchor text, drop the <a> tag and href.
        $text = preg_replace('/<a\b[^>]*>(.*?)<\/a>/is', '$1', $text);

        // Remove videos and iframes.
        $text = preg_replace('/<(?:iframe|video)\b[^>]*>.*?<\/(?:iframe|video)>/is', '[video]', $text);
        $text = preg_replace('/<(?:iframe|video)\b[^>]*\/?>/i', '[video]', $text);

        // Remove figure wrappers (content already handled).
        $text = preg_replace('/<\/?figure\b[^>]*>/i', '', $text);
        $text = preg_replace('/<figcaption\b[^>]*>.*?<\/figcaption>/is', '', $text);

        // Remove remaining inline formatting tags.
        $text = preg_replace('/<\/?(strong|b|em|i|u|mark|small|sub|sup|br|hr)\b[^>]*>/i', '', $text);

        // Collapse excessive whitespace.
        $text = (string) preg_replace('/[ \t]+/', ' ', $text);
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    // ------------------------------------------------------------------
    //  Sentence extraction
    // ------------------------------------------------------------------

    /**
     * Extract the first sentence from an HTML fragment.
     *
     * Walks character-by-character, ignoring content inside HTML tags,
     * and looks for sentence-ending punctuation (. ! ?) followed by
     * whitespace or a tag boundary.
     *
     * Returns the original HTML up to and including the sentence terminator,
     * preserving all inline tags (<strong>, <a>, etc.) within the sentence.
     *
     * @param string $html Sanitized HTML fragment (e.g. inner content of a <p>).
     * @return string The first sentence with its HTML markup.
     */
    private static function first_sentence(string $html): string
    {
        $in_tag       = false;
        $visible_count = 0;
        $len          = strlen($html); // Byte-level safe for ASCII punctuation.

        for ($i = 0; $i < $len; $i++) {
            $ch = $html[$i];

            if ('<' === $ch) {
                $in_tag = true;
                continue;
            }
            if ('>' === $ch && $in_tag) {
                $in_tag = false;
                continue;
            }
            if ($in_tag) {
                continue;
            }

            $visible_count++;

            // Look for sentence terminators after minimum length.
            if ($visible_count >= self::MIN_SENTENCE_CHARS && ('.' === $ch || '!' === $ch || '?' === $ch)) {
                // Avoid false positives: abbreviations (U.S.), decimals (3.5).
                $prev = ($i > 0) ? $html[$i - 1] : ' ';
                if ('.' === $prev || ctype_digit($prev)) {
                    continue; // Likely abbreviation or decimal.
                }

                // Check what follows: space, tag start, newline, or end of string.
                $next = ($i + 1 < $len) ? $html[$i + 1] : '';
                if ('' === $next || ' ' === $next || '<' === $next || "\n" === $next || "\r" === $next) {
                    // Include any trailing closing tags that belong to this sentence.
                    $cut = $i + 1;
                    while ($cut < $len && '<' === $html[$cut] && '/' === ($html[$cut + 1] ?? '')) {
                        $close_end = strpos($html, '>', $cut);
                        if (false === $close_end) {
                            break;
                        }
                        $cut = $close_end + 1;
                    }

                    return substr($html, 0, $cut);
                }
            }
        }

        // No sentence end found — return as-is (short content).
        return $html;
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    /**
     * Check if a string is a <p>...</p> block.
     */
    private static function is_p_block(string $part): bool
    {
        return (bool) preg_match('/^<p\b[^>]*>/i', trim($part));
    }

    /**
     * Split a user prompt into SEO metadata prefix and HTML body.
     *
     * Looks for the first known content marker (e.g. "Main page content: ").
     * Everything up to and including the marker is the SEO prefix (kept in
     * full). Everything after the marker is the HTML body (compressible).
     *
     * If no marker is found, returns ['', $text] — nothing is protected and
     * fit_messages() will skip compression as a safety measure.
     *
     * @param string $text Full user prompt.
     * @return array{0: string, 1: string} [seo_prefix, html_body]
     */
    private static function split_at_content(string $text): array
    {
        foreach (self::CONTENT_MARKERS as $marker) {
            $pos = mb_strpos($text, $marker);
            if (false !== $pos) {
                $split = $pos + mb_strlen($marker);
                return array(
                    mb_substr($text, 0, $split),
                    mb_substr($text, $split),
                );
            }
        }

        // No content marker found — cannot safely separate metadata from body.
        return array('', $text);
    }

    /**
     * Estimate token count from character count.
     */
    private static function estimate_tokens(int $chars): int
    {
        return (int) ceil($chars / self::CHARS_PER_TOKEN);
    }

    /**
     * Prepend a transparency note to the user prompt.
     */
    private static function prepend_compression_note(
        string $text,
        int $level,
        int $original_tokens,
        int $final_tokens,
        int $context_window
    ): string {
        $reduction = $original_tokens > 0
            ? round((1 - $final_tokens / $original_tokens) * 100)
            : 0;

        $preserved = array('ALL SEO metadata (titles, descriptions, keyphrases, audit data, hierarchy — uncompressed)', 'all headings (H1–H6)');
        if ($level <= self::LEVEL_SKELETON) {
            $preserved[] = 'all images with alt text';
            $preserved[] = 'all links with URLs';
            $preserved[] = 'tables and lists';
        }
        if ($level >= self::LEVEL_MINIMAL) {
            $preserved = array('ALL SEO metadata (uncompressed)', 'all headings (H1–H6)', 'element placeholders');
        }

        $note = sprintf(
            "[COMPRESSION NOTE: Only the page HTML body was condensed (Level %d: %s) to fit the %s-token context window. "
                . "ALL SEO metadata fields above (title drafts, meta descriptions, focus keyphrase, audit results, hierarchy, GSC data) are complete and unmodified. "
                . "Preserved in body: %s. "
                . "Prompt reduced from ~%s to ~%s tokens (%d%% smaller). "
                . "Base your analysis on the full SEO metadata and the structural elements shown. "
                . "For full-text body analysis, the user should load a model with a larger context window.]\n\n",
            $level,
            self::LEVEL_LABELS[$level] ?? 'unknown',
            number_format($context_window),
            implode(', ', $preserved),
            number_format($original_tokens),
            number_format($final_tokens),
            $reduction
        );

        return $note . $text;
    }

    /**
     * Build the standard result array.
     */
    private static function build_result(
        array $messages,
        int $level,
        int $original_tokens,
        int $final_tokens,
        int $context_window
    ): array {
        return array(
            'messages'        => $messages,
            'compressed'      => $level > self::LEVEL_NONE,
            'level'           => $level,
            'level_label'     => self::LEVEL_LABELS[$level] ?? 'unknown',
            'original_tokens' => $original_tokens,
            'final_tokens'    => $final_tokens,
            'context_window'  => $context_window,
        );
    }
}
