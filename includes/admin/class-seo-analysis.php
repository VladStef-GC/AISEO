<?php

namespace AI_SEO_Keeper\Admin;

use AI_SEO_Keeper\Content_Helper;

/**
 * Deterministic SEO checks — keyphrase analysis, readability scoring,
 * link quality, content structure evaluation.
 *
 * All methods are static and pure (no side-effects, no DB writes).
 */
class SEO_Analysis
{
    public const TITLE_MIN_LENGTH = 30;
    public const TITLE_MAX_LENGTH = 60;
    public const DESCRIPTION_MIN_LENGTH = 70;
    public const DESCRIPTION_MAX_LENGTH = 155;

    private const READABILITY_TRANSITION_WORDS = array(
        'however',
        'therefore',
        'meanwhile',
        'moreover',
        'instead',
        'because',
        'also',
        'next',
        'finally',
        'for example',
        'for instance',
        'in addition',
        'as a result',
        'on the other hand',
        'in contrast',
        'then',
        'yet',
        'otherwise',
    );

    private const GENERIC_ANCHOR_TEXTS = array(
        'click here',
        'read more',
        'learn more',
        'more',
        'here',
        'link',
        'this page',
        'view more',
    );

    /**
     * Render the full Basic SEO checks + readability panel HTML.
     */
    public static function render_checks_markup(\WP_Post $post, string $focus_keyphrase, string $seo_title, string $seo_description): string
    {
        $title_length = self::get_text_length($seo_title);
        $description_length = self::get_text_length($seo_description);
        $raw_content = Content_Helper::get_content($post);
        $content = wp_strip_all_tags($raw_content);
        $normalized_content = self::normalize_text_for_match($content);
        $content_word_count = '' === $normalized_content ? 0 : count(preg_split('/\s+/', $normalized_content));
        $subheading_count = preg_match_all('/<h[2-6][^>]*>/i', $raw_content);
        $sentences = self::extract_sentences($content);
        $sentence_count = count($sentences);
        $paragraphs = self::extract_content_blocks($raw_content);
        $paragraph_count = count($paragraphs);
        $transition_word_count = self::count_transition_words($normalized_content);
        $passive_voice_sentence_count = self::count_passive_voice_sentences($sentences);
        $repeated_sentence_start_count = self::count_repeated_sentence_starts($sentences);
        $list_count = preg_match_all('/<(ul|ol)\b/i', $raw_content);
        $question_heading_count = self::count_question_style_headings($raw_content);
        $long_sentence_count = 0;
        $total_sentence_words = 0;
        $long_paragraph_count = 0;
        $image_matches = array();
        $images_with_alt = 0;
        $internal_link_count = 0;
        $external_link_count = 0;
        $generic_anchor_count = 0;
        $link_count = 0;
        $site_host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);

        foreach ($sentences as $sentence) {
            $sentence_word_count = self::count_words($sentence);

            if ($sentence_word_count <= 0) {
                continue;
            }

            $total_sentence_words += $sentence_word_count;

            if ($sentence_word_count > 24) {
                $long_sentence_count += 1;
            }
        }

        foreach ($paragraphs as $paragraph) {
            if (self::count_words($paragraph) > 120) {
                $long_paragraph_count += 1;
            }
        }

        $average_sentence_words = $sentence_count > 0 ? round($total_sentence_words / $sentence_count, 1) : 0;

        preg_match_all('/<img\b[^>]*>/i', $raw_content, $image_matches);

        foreach ($image_matches[0] as $image_html) {
            if (preg_match('/\balt=("|\')(.*?)\1/i', $image_html, $alt_match) && '' !== trim(wp_strip_all_tags($alt_match[2]))) {
                $images_with_alt += 1;
            }
        }

        $link_matches = array();
        preg_match_all('/<a\s[^>]*href=("|\')(.*?)\1[^>]*>(.*?)<\/a>/is', $raw_content, $link_matches, PREG_SET_ORDER);

        foreach ($link_matches as $link_match) {
            $href = isset($link_match[2]) ? trim(html_entity_decode((string) $link_match[2])) : '';
            $anchor_text = isset($link_match[3]) ? trim(wp_strip_all_tags(html_entity_decode((string) $link_match[3]))) : '';

            if ('' === $href || 0 === strpos($href, '#') || 0 === strpos($href, 'mailto:') || 0 === strpos($href, 'tel:')) {
                continue;
            }

            $link_count += 1;

            if (self::is_generic_anchor_text($anchor_text)) {
                $generic_anchor_count += 1;
            }

            $link_host = (string) wp_parse_url($href, PHP_URL_HOST);

            if ('' === $link_host || $link_host === $site_host) {
                $internal_link_count += 1;
                continue;
            }

            $external_link_count += 1;
        }

        $checks = array(
            array(
                'label' => __('SEO title length', 'ai-seo-keeper'),
                'passed' => $title_length >= self::TITLE_MIN_LENGTH && $title_length <= self::TITLE_MAX_LENGTH,
                'message' => '' === $seo_title
                    ? __('Add an SEO title.', 'ai-seo-keeper')
                    : sprintf(__('Current length: %d characters. Target roughly %d to %d, with a maximum of %d.', 'ai-seo-keeper'), $title_length, self::TITLE_MIN_LENGTH, self::TITLE_MAX_LENGTH, self::TITLE_MAX_LENGTH),
            ),
            array(
                'label' => __('Meta description length', 'ai-seo-keeper'),
                'passed' => $description_length >= self::DESCRIPTION_MIN_LENGTH && $description_length <= self::DESCRIPTION_MAX_LENGTH,
                'message' => '' === $seo_description
                    ? __('Add a meta description.', 'ai-seo-keeper')
                    : sprintf(__('Current length: %d characters. Target roughly %d to %d, with a maximum of %d.', 'ai-seo-keeper'), $description_length, self::DESCRIPTION_MIN_LENGTH, self::DESCRIPTION_MAX_LENGTH, self::DESCRIPTION_MAX_LENGTH),
            ),
            array(
                'label' => __('Content length', 'ai-seo-keeper'),
                'passed' => $content_word_count >= 250,
                'message' => 0 === $content_word_count
                    ? __('Add meaningful page content before relying on metadata alone.', 'ai-seo-keeper')
                    : sprintf(__('Current body length: %d words. For most pages, aim for at least 250 words of useful content.', 'ai-seo-keeper'), $content_word_count),
            ),
            array(
                'label' => __('Subheadings in content', 'ai-seo-keeper'),
                'passed' => $subheading_count >= 1,
                'message' => $subheading_count >= 1
                    ? sprintf(__('Found %d subheading%s in the page body.', 'ai-seo-keeper'), $subheading_count, 1 === $subheading_count ? '' : 's')
                    : __('Add at least one H2 or H3 subheading to make the page easier to scan.', 'ai-seo-keeper'),
            ),
            array(
                'label' => __('List structure in content', 'ai-seo-keeper'),
                'passed' => $list_count >= 1 || $content_word_count < 220,
                'message' => $list_count >= 1
                    ? sprintf(__('Found %d list%s in the page body for scannable structure.', 'ai-seo-keeper'), $list_count, 1 === $list_count ? '' : 's')
                    : ($content_word_count < 220
                        ? __('This page is short enough that list structure is optional.', 'ai-seo-keeper')
                        : __('Consider adding at least one bullet or numbered list for steps, features, or grouped points.', 'ai-seo-keeper')),
            ),
            array(
                'label' => __('Question-style subheadings', 'ai-seo-keeper'),
                'passed' => $question_heading_count >= 1 || $subheading_count < 2 || $content_word_count < 250,
                'message' => $question_heading_count >= 2
                    ? sprintf(__('Found %d question-style heading%s. This content may qualify for live FAQ schema output when each question is followed by a direct answer.', 'ai-seo-keeper'), $question_heading_count, 1 === $question_heading_count ? '' : 's')
                    : ($question_heading_count === 1
                        ? __('Found one question-style heading that can help match informational search intent.', 'ai-seo-keeper')
                        : (($subheading_count < 2 || $content_word_count < 250)
                            ? __('Question-style headings are optional on shorter or simpler pages.', 'ai-seo-keeper')
                            : __('Consider adding a question-style subheading when the page targets informational search intent or FAQ-style queries.', 'ai-seo-keeper'))),
            ),
            array(
                'label' => __('Internal links in content', 'ai-seo-keeper'),
                'passed' => $internal_link_count >= 1,
                'message' => $internal_link_count >= 1
                    ? sprintf(__('Found %d internal link%s that connect this page to the rest of the site.', 'ai-seo-keeper'), $internal_link_count, 1 === $internal_link_count ? '' : 's')
                    : __('Add at least one internal link to strengthen crawl paths and user navigation.', 'ai-seo-keeper'),
            ),
            array(
                'label' => __('Outbound links in content', 'ai-seo-keeper'),
                'passed' => $external_link_count >= 1,
                'message' => $external_link_count >= 1
                    ? sprintf(__('Found %d outbound link%s that point to external sources or references.', 'ai-seo-keeper'), $external_link_count, 1 === $external_link_count ? '' : 's')
                    : __('Add an outbound link when the page benefits from citing a credible outside source or reference.', 'ai-seo-keeper'),
            ),
            array(
                'label' => __('Descriptive anchor text', 'ai-seo-keeper'),
                'passed' => 0 === $link_count || 0 === $generic_anchor_count,
                'message' => 0 === $link_count
                    ? __('No content links were found yet, so anchor-text quality cannot be assessed.', 'ai-seo-keeper')
                    : (0 === $generic_anchor_count
                        ? sprintf(__('Checked %d link%s and none use obvious generic anchor text.', 'ai-seo-keeper'), $link_count, 1 === $link_count ? '' : 's')
                        : sprintf(__('Generic anchor text detected on %d of %d link%s. Replace phrases like "click here" with destination-specific wording.', 'ai-seo-keeper'), $generic_anchor_count, $link_count, 1 === $link_count ? '' : 's')),
            ),
            array(
                'label' => __('Image alt coverage', 'ai-seo-keeper'),
                'passed' => 0 === count($image_matches[0]) || $images_with_alt === count($image_matches[0]),
                'message' => 0 === count($image_matches[0])
                    ? __('No inline images found in the page body, so alt text is not required here.', 'ai-seo-keeper')
                    : sprintf(__('Images with alt text: %d of %d.', 'ai-seo-keeper'), $images_with_alt, count($image_matches[0])),
            ),
        );

        $recommended_transition_count = $sentence_count >= 8 ? 3 : ($sentence_count >= 3 ? 2 : 1);
        $recommended_passive_voice_limit = max(1, (int) ceil(max(1, $sentence_count) * 0.2));
        $recommended_repeated_starts_limit = max(1, (int) ceil(max(1, $sentence_count - 1) * 0.15));
        $readability_checks = array(
            array(
                'label' => __('Sentence length balance', 'ai-seo-keeper'),
                'passed' => $sentence_count > 0 && $average_sentence_words <= 20 && $long_sentence_count <= max(1, (int) ceil($sentence_count * 0.25)),
                'message' => 0 === $sentence_count
                    ? __('Add body copy before readability can be assessed.', 'ai-seo-keeper')
                    : sprintf(__('Average sentence length: %s words. Long sentences over 24 words: %d of %d.', 'ai-seo-keeper'), number_format_i18n($average_sentence_words, 1), $long_sentence_count, $sentence_count),
            ),
            array(
                'label' => __('Paragraph length balance', 'ai-seo-keeper'),
                'passed' => $paragraph_count > 0 && $long_paragraph_count <= max(1, (int) ceil($paragraph_count * 0.34)),
                'message' => 0 === $paragraph_count
                    ? __('Add structured paragraphs to assess reading flow.', 'ai-seo-keeper')
                    : sprintf(__('Detected %d paragraph%s. Long paragraphs over 120 words: %d.', 'ai-seo-keeper'), $paragraph_count, 1 === $paragraph_count ? '' : 's', $long_paragraph_count),
            ),
            array(
                'label' => __('Transition word usage', 'ai-seo-keeper'),
                'passed' => $sentence_count < 2 || $transition_word_count >= $recommended_transition_count,
                'message' => sprintf(__('Detected %d transition word%s. Aim for at least %d to improve flow between ideas.', 'ai-seo-keeper'), $transition_word_count, 1 === $transition_word_count ? '' : 's', $recommended_transition_count),
            ),
            array(
                'label' => __('Passive voice estimate', 'ai-seo-keeper'),
                'passed' => $sentence_count < 2 || $passive_voice_sentence_count <= $recommended_passive_voice_limit,
                'message' => 0 === $sentence_count
                    ? __('Add body copy before passive voice can be estimated.', 'ai-seo-keeper')
                    : sprintf(__('Estimated passive-voice sentences: %d of %d. This is a heuristic, not a full grammar parser.', 'ai-seo-keeper'), $passive_voice_sentence_count, $sentence_count),
            ),
            array(
                'label' => __('Repeated sentence starts', 'ai-seo-keeper'),
                'passed' => $sentence_count < 3 || $repeated_sentence_start_count <= $recommended_repeated_starts_limit,
                'message' => 0 === $sentence_count
                    ? __('Add body copy before sentence-start variety can be assessed.', 'ai-seo-keeper')
                    : sprintf(__('Consecutive repeated sentence openings detected: %d. Varying openings keeps the copy from sounding mechanical.', 'ai-seo-keeper'), $repeated_sentence_start_count),
            ),
        );

        if ('' !== $focus_keyphrase) {
            $normalized_keyphrase = self::normalize_text_for_match($focus_keyphrase);
            $checks[] = array(
                'label' => __('Focus keyphrase in SEO title', 'ai-seo-keeper'),
                'passed' => '' !== $normalized_keyphrase && false !== strpos(self::normalize_text_for_match($seo_title), $normalized_keyphrase),
                'message' => __('Use the focus keyphrase naturally in the SEO title.', 'ai-seo-keeper'),
            );
            $checks[] = array(
                'label' => __('Focus keyphrase in meta description', 'ai-seo-keeper'),
                'passed' => '' !== $normalized_keyphrase && false !== strpos(self::normalize_text_for_match($seo_description), $normalized_keyphrase),
                'message' => __('Use the focus keyphrase naturally in the meta description.', 'ai-seo-keeper'),
            );
            $checks[] = array(
                'label' => __('Focus keyphrase in URL', 'ai-seo-keeper'),
                'passed' => '' !== $normalized_keyphrase && false !== strpos(self::normalize_text_for_match((string) get_permalink($post)), $normalized_keyphrase),
                'message' => __('Short URLs that reflect the target phrase are easier for users and crawlers.', 'ai-seo-keeper'),
            );
            $checks[] = array(
                'label' => __('Focus keyphrase in page content', 'ai-seo-keeper'),
                'passed' => '' !== $normalized_keyphrase && false !== strpos(self::normalize_text_for_match($content), $normalized_keyphrase),
                'message' => __('The real page content should reinforce the target phrase, not just the metadata.', 'ai-seo-keeper'),
            );

            // Keyphrase density.
            if ($content_word_count > 0 && '' !== $normalized_keyphrase) {
                $keyphrase_word_count = count(preg_split('/\s+/', $normalized_keyphrase));
                $keyphrase_occurrences = mb_substr_count(self::normalize_text_for_match($content), $normalized_keyphrase);
                $density = ($keyphrase_occurrences * $keyphrase_word_count / $content_word_count) * 100;
                $density_rounded = round($density, 1);
                $density_ok = $density >= 0.3 && $density <= 3.0;

                $checks[] = array(
                    'label' => __('Focus keyphrase density', 'ai-seo-keeper'),
                    'passed' => $density_ok,
                    'message' => sprintf(
                        __('Found %d occurrence%s (%.1f%% density). Aim for 0.5%%–2.5%% for a natural feel — too low means weak signal, too high risks keyword stuffing.', 'ai-seo-keeper'),
                        $keyphrase_occurrences,
                        1 === $keyphrase_occurrences ? '' : 's',
                        $density_rounded
                    ),
                );
            }

            // Keyphrase in first paragraph.
            if ('' !== $normalized_keyphrase) {
                $first_paragraph = '';
                if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $raw_content, $fp_match)) {
                    $first_paragraph = self::normalize_text_for_match(wp_strip_all_tags($fp_match[1]));
                } elseif (! empty($sentences)) {
                    $first_paragraph = self::normalize_text_for_match(implode(' ', array_slice($sentences, 0, 2)));
                }
                $kw_in_intro = '' !== $first_paragraph && false !== strpos($first_paragraph, $normalized_keyphrase);
                $checks[] = array(
                    'label' => __('Focus keyphrase in introduction', 'ai-seo-keeper'),
                    'passed' => $kw_in_intro,
                    'message' => $kw_in_intro
                        ? __('The keyphrase appears in the opening paragraph — good for early relevance signal.', 'ai-seo-keeper')
                        : __('Try to include the focus keyphrase in the first paragraph so search engines see it early.', 'ai-seo-keeper'),
                );
            }

            // Keyphrase in subheadings (H2-H6).
            if ('' !== $normalized_keyphrase && $subheading_count > 0) {
                $subheading_kw_count = 0;
                preg_match_all('/<h[2-6][^>]*>(.*?)<\/h[2-6]>/is', $raw_content, $sub_matches);
                foreach ($sub_matches[1] as $sub_text) {
                    if (false !== strpos(self::normalize_text_for_match(wp_strip_all_tags($sub_text)), $normalized_keyphrase)) {
                        $subheading_kw_count++;
                    }
                }
                $checks[] = array(
                    'label' => __('Focus keyphrase in subheadings', 'ai-seo-keeper'),
                    'passed' => $subheading_kw_count >= 1,
                    'message' => $subheading_kw_count >= 1
                        ? sprintf(__('The keyphrase appears in %d of %d subheading%s.', 'ai-seo-keeper'), $subheading_kw_count, $subheading_count, $subheading_count > 1 ? 's' : '')
                        : __('Consider adding the keyphrase to at least one H2 or H3 subheading to reinforce topical relevance.', 'ai-seo-keeper'),
                );
            }

            // Keyphrase in image alt attributes.
            if ('' !== $normalized_keyphrase && count($image_matches[0]) > 0) {
                $img_alt_kw_count = 0;
                foreach ($image_matches[0] as $img_tag) {
                    if (preg_match('/\balt=("|\')(.*?)\1/i', $img_tag, $alt_match)) {
                        if (false !== strpos(self::normalize_text_for_match($alt_match[2]), $normalized_keyphrase)) {
                            $img_alt_kw_count++;
                        }
                    }
                }
                $checks[] = array(
                    'label' => __('Focus keyphrase in image alt tags', 'ai-seo-keeper'),
                    'passed' => $img_alt_kw_count >= 1,
                    'message' => $img_alt_kw_count >= 1
                        ? sprintf(__('The keyphrase appears in %d image alt tag%s.', 'ai-seo-keeper'), $img_alt_kw_count, $img_alt_kw_count > 1 ? 's' : '')
                        : __('Add the focus keyphrase to at least one image alt attribute — helps image search and reinforces page relevance.', 'ai-seo-keeper'),
                );
            }
        }

        ob_start();
?>
        <strong><?php esc_html_e('Basic SEO checks', 'ai-seo-keeper'); ?></strong>
        <p class="ai-seo-keeper-muted" style="margin:8px 0 12px;"><?php esc_html_e('Lightweight deterministic checks against the saved draft fields and the current page body.', 'ai-seo-keeper'); ?></p>
        <?php if ('' === $focus_keyphrase) : ?>
            <p class="ai-seo-keeper-muted" style="margin:0 0 12px;"><?php esc_html_e("Add a focus keyphrase to unlock phrase-matching checks similar to Yoast's page analysis.", 'ai-seo-keeper'); ?></p>
        <?php endif; ?>
        <div class="ai-seo-keeper-check-section">
            <p class="ai-seo-keeper-check-section-title"><strong><?php esc_html_e('SEO and structure', 'ai-seo-keeper'); ?></strong></p>
            <ul class="ai-seo-keeper-check-list">
                <?php foreach ($checks as $check) : ?>
                    <li class="ai-seo-keeper-check-item">
                        <span class="ai-seo-keeper-check-pill <?php echo $check['passed'] ? 'is-pass' : 'is-warning'; ?>"><?php echo $check['passed'] ? esc_html__('Pass', 'ai-seo-keeper') : esc_html__('Needs work', 'ai-seo-keeper'); ?></span>
                        <strong><?php echo esc_html($check['label']); ?></strong><br />
                        <span class="ai-seo-keeper-muted"><?php echo esc_html($check['message']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="ai-seo-keeper-check-section">
            <p class="ai-seo-keeper-check-section-title"><strong><?php esc_html_e('Readability and flow', 'ai-seo-keeper'); ?></strong></p>
            <p class="ai-seo-keeper-muted" style="margin:0 0 12px;"><?php esc_html_e('A first-pass reading-flow scan based on sentence length, paragraph size, and transition usage.', 'ai-seo-keeper'); ?></p>
            <div class="ai-seo-keeper-metrics-grid">
                <div class="ai-seo-keeper-metric-card">
                    <span class="ai-seo-keeper-metric-label"><?php esc_html_e('Sentences', 'ai-seo-keeper'); ?></span>
                    <span class="ai-seo-keeper-metric-value"><?php echo esc_html((string) $sentence_count); ?></span>
                </div>
                <div class="ai-seo-keeper-metric-card">
                    <span class="ai-seo-keeper-metric-label"><?php esc_html_e('Avg sentence', 'ai-seo-keeper'); ?></span>
                    <span class="ai-seo-keeper-metric-value"><?php echo esc_html((string) number_format_i18n($average_sentence_words, 1)); ?></span>
                </div>
                <div class="ai-seo-keeper-metric-card">
                    <span class="ai-seo-keeper-metric-label"><?php esc_html_e('Paragraphs', 'ai-seo-keeper'); ?></span>
                    <span class="ai-seo-keeper-metric-value"><?php echo esc_html((string) $paragraph_count); ?></span>
                </div>
                <div class="ai-seo-keeper-metric-card">
                    <span class="ai-seo-keeper-metric-label"><?php esc_html_e('Transitions', 'ai-seo-keeper'); ?></span>
                    <span class="ai-seo-keeper-metric-value"><?php echo esc_html((string) $transition_word_count); ?></span>
                </div>
                <div class="ai-seo-keeper-metric-card">
                    <span class="ai-seo-keeper-metric-label"><?php esc_html_e('Passive est.', 'ai-seo-keeper'); ?></span>
                    <span class="ai-seo-keeper-metric-value"><?php echo esc_html((string) $passive_voice_sentence_count); ?></span>
                </div>
                <div class="ai-seo-keeper-metric-card">
                    <span class="ai-seo-keeper-metric-label"><?php esc_html_e('Repeat starts', 'ai-seo-keeper'); ?></span>
                    <span class="ai-seo-keeper-metric-value"><?php echo esc_html((string) $repeated_sentence_start_count); ?></span>
                </div>
            </div>
            <ul class="ai-seo-keeper-check-list">
                <?php foreach ($readability_checks as $check) : ?>
                    <li class="ai-seo-keeper-check-item">
                        <span class="ai-seo-keeper-check-pill <?php echo $check['passed'] ? 'is-pass' : 'is-warning'; ?>"><?php echo $check['passed'] ? esc_html__('Pass', 'ai-seo-keeper') : esc_html__('Needs work', 'ai-seo-keeper'); ?></span>
                        <strong><?php echo esc_html($check['label']); ?></strong><br />
                        <span class="ai-seo-keeper-muted"><?php echo esc_html($check['message']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
<?php

        return (string) ob_get_clean();
    }

    // ------------------------------------------------------------------
    //  Content parsing utilities
    // ------------------------------------------------------------------

    public static function extract_content_blocks(string $raw_content): array
    {
        $content_with_breaks = preg_replace('/<\/(p|div|section|article|li|blockquote|h[1-6])>/i', "$0\n\n", $raw_content);
        $plain_content = trim(wp_strip_all_tags((string) $content_with_breaks));

        if ('' === $plain_content) {
            return array();
        }

        $blocks = preg_split('/\n\s*\n+/u', $plain_content);

        return array_values(
            array_filter(
                array_map('trim', is_array($blocks) ? $blocks : array()),
                static function ($block): bool {
                    return '' !== (string) $block;
                }
            )
        );
    }

    public static function extract_sentences(string $content): array
    {
        $content = trim(preg_replace('/\s+/u', ' ', $content));

        if ('' === $content) {
            return array();
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $content);

        return array_values(
            array_filter(
                array_map('trim', is_array($sentences) ? $sentences : array()),
                static function ($sentence): bool {
                    return '' !== (string) $sentence;
                }
            )
        );
    }

    public static function count_words(string $text): int
    {
        $normalized_text = self::normalize_text_for_match($text);

        if ('' === $normalized_text) {
            return 0;
        }

        return count(preg_split('/\s+/u', $normalized_text));
    }

    public static function count_transition_words(string $normalized_content): int
    {
        if ('' === $normalized_content) {
            return 0;
        }

        $transition_word_count = 0;

        foreach (self::READABILITY_TRANSITION_WORDS as $transition_word) {
            $matches = preg_match_all('/\b' . preg_quote($transition_word, '/') . '\b/u', $normalized_content, $found_matches);

            if (false !== $matches) {
                $transition_word_count += $matches;
            }
        }

        return $transition_word_count;
    }

    public static function count_passive_voice_sentences(array $sentences): int
    {
        $passive_voice_sentence_count = 0;

        foreach ($sentences as $sentence) {
            $normalized_sentence = self::normalize_text_for_match($sentence);

            if ('' === $normalized_sentence) {
                continue;
            }

            if (preg_match('/\b(am|is|are|was|were|be|been|being)\b\s+(?:\w+\s+){0,2}\w+(ed|en)\b/u', $normalized_sentence)) {
                $passive_voice_sentence_count += 1;
            }
        }

        return $passive_voice_sentence_count;
    }

    public static function count_repeated_sentence_starts(array $sentences): int
    {
        $repeated_sentence_start_count = 0;
        $previous_signature = '';

        foreach ($sentences as $sentence) {
            $normalized_sentence = self::normalize_text_for_match($sentence);

            if ('' === $normalized_sentence) {
                continue;
            }

            $words = preg_split('/\s+/u', $normalized_sentence);
            $signature = implode(' ', array_slice(is_array($words) ? $words : array(), 0, 2));

            if ('' === $signature) {
                continue;
            }

            if ($signature === $previous_signature) {
                $repeated_sentence_start_count += 1;
            }

            $previous_signature = $signature;
        }

        return $repeated_sentence_start_count;
    }

    public static function count_question_style_headings(string $raw_content): int
    {
        $heading_matches = array();
        $question_heading_count = 0;

        preg_match_all('/<h[2-6][^>]*>(.*?)<\/h[2-6]>/is', $raw_content, $heading_matches);

        foreach ($heading_matches[1] as $heading_html) {
            $heading_text = trim(wp_strip_all_tags((string) html_entity_decode($heading_html)));

            if ('' === $heading_text) {
                continue;
            }

            if (self::is_question_style_heading($heading_text)) {
                $question_heading_count += 1;
            }
        }

        return $question_heading_count;
    }

    public static function is_question_style_heading(string $heading_text): bool
    {
        $normalized_heading = self::normalize_text_for_match($heading_text);

        if ('' === $normalized_heading) {
            return false;
        }

        if (false !== strpos($heading_text, '?')) {
            return true;
        }

        return 1 === preg_match('/^(how|what|why|when|where|who|can|should|is|are|do|does|will|which)\b/u', $normalized_heading);
    }

    public static function is_generic_anchor_text(string $anchor_text): bool
    {
        $normalized_anchor = self::normalize_text_for_match($anchor_text);

        if ('' === $normalized_anchor) {
            return true;
        }

        return in_array($normalized_anchor, self::GENERIC_ANCHOR_TEXTS, true);
    }

    public static function normalize_text_for_match(string $text): string
    {
        $text = remove_accents(wp_strip_all_tags($text));
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text);

        return trim(preg_replace('/\s+/', ' ', (string) $text));
    }

    // ------------------------------------------------------------------
    //  Text length helpers
    // ------------------------------------------------------------------

    public static function get_text_length(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }

    public static function truncate_text(string $text, int $max_length): string
    {
        if ('' === $text || $max_length < 1 || self::get_text_length($text) <= $max_length) {
            return $text;
        }

        return function_exists('mb_substr') ? mb_substr($text, 0, $max_length) : substr($text, 0, $max_length);
    }

    /**
     * Apply practical search-snippet caps to editor field values.
     */
    public static function apply_editor_text_limits(array $fields): array
    {
        $limit_map = array(
            'seo_title' => self::TITLE_MAX_LENGTH,
            'meta_description' => self::DESCRIPTION_MAX_LENGTH,
            'social_title' => self::TITLE_MAX_LENGTH,
            'social_description' => self::DESCRIPTION_MAX_LENGTH,
        );

        foreach ($limit_map as $field_key => $max_length) {
            if (! array_key_exists($field_key, $fields)) {
                continue;
            }

            $fields[$field_key] = self::truncate_text((string) $fields[$field_key], $max_length);
        }

        return $fields;
    }

    /**
     * Schema type options for the editor dropdown.
     */
    public static function get_schema_type_options(): array
    {
        return array(
            '' => 'Automatic detection',
            'WebPage' => 'Web Page',
            'AboutPage' => 'About Page',
            'ContactPage' => 'Contact Page',
            'Article' => 'Article',
            'Service' => 'Service',
            'Product' => 'Product',
            'CollectionPage' => 'Collection Page',
        );
    }

    /**
     * Robots directive options for the editor dropdown.
     */
    public static function get_robots_directive_options(): array
    {
        return array(
            '' => 'Automatic site default',
            'index,follow' => 'Index, follow',
            'noindex,follow' => 'Noindex, follow',
            'index,nofollow' => 'Index, nofollow',
            'noindex,nofollow' => 'Noindex, nofollow',
        );
    }
}
