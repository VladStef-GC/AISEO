<?php

declare(strict_types=1);

namespace AI_SEO_Keeper\Tests\Unit;

use AI_SEO_Keeper\Admin\SEO_Analysis;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SEO_Analysis — pure static methods, no WordPress required.
 *
 * @covers \AI_SEO_Keeper\Admin\SEO_Analysis
 */
class SEOAnalysisTest extends TestCase
{
    // ------------------------------------------------------------------
    //  Constants
    // ------------------------------------------------------------------

    public function test_title_length_constants(): void
    {
        $this->assertSame(30, SEO_Analysis::TITLE_MIN_LENGTH);
        $this->assertSame(60, SEO_Analysis::TITLE_MAX_LENGTH);
        $this->assertSame(70, SEO_Analysis::DESCRIPTION_MIN_LENGTH);
        $this->assertSame(155, SEO_Analysis::DESCRIPTION_MAX_LENGTH);
    }

    // ------------------------------------------------------------------
    //  get_text_length
    // ------------------------------------------------------------------

    public function test_get_text_length_empty(): void
    {
        $this->assertSame(0, SEO_Analysis::get_text_length(''));
    }

    public function test_get_text_length_ascii(): void
    {
        $this->assertSame(5, SEO_Analysis::get_text_length('hello'));
    }

    public function test_get_text_length_multibyte(): void
    {
        // 4 characters (each multibyte).
        $this->assertSame(4, SEO_Analysis::get_text_length('über'));
    }

    public function test_get_text_length_exactly_title_max(): void
    {
        $this->assertSame(SEO_Analysis::TITLE_MAX_LENGTH, SEO_Analysis::get_text_length(str_repeat('a', SEO_Analysis::TITLE_MAX_LENGTH)));
    }

    // ------------------------------------------------------------------
    //  truncate_text
    // ------------------------------------------------------------------

    public function test_truncate_text_no_op_when_within_limit(): void
    {
        $this->assertSame('hello', SEO_Analysis::truncate_text('hello', 10));
    }

    public function test_truncate_text_no_op_when_equal(): void
    {
        $this->assertSame('hello', SEO_Analysis::truncate_text('hello', 5));
    }

    public function test_truncate_text_cuts_excess(): void
    {
        $this->assertSame('hel', SEO_Analysis::truncate_text('hello', 3));
    }

    public function test_truncate_text_empty_string(): void
    {
        $this->assertSame('', SEO_Analysis::truncate_text('', 10));
    }

    public function test_truncate_text_zero_limit(): void
    {
        $this->assertSame('hello', SEO_Analysis::truncate_text('hello', 0));
    }

    public function test_truncate_text_title_at_max(): void
    {
        $long_title = str_repeat('a', 80);
        $result = SEO_Analysis::truncate_text($long_title, SEO_Analysis::TITLE_MAX_LENGTH);
        $this->assertSame(SEO_Analysis::TITLE_MAX_LENGTH, SEO_Analysis::get_text_length($result));
    }

    // ------------------------------------------------------------------
    //  count_words
    // ------------------------------------------------------------------

    public function test_count_words_empty(): void
    {
        $this->assertSame(0, SEO_Analysis::count_words(''));
    }

    public function test_count_words_single_word(): void
    {
        $this->assertSame(1, SEO_Analysis::count_words('hello'));
    }

    public function test_count_words_multiple_words(): void
    {
        $this->assertSame(4, SEO_Analysis::count_words('The quick brown fox'));
    }

    public function test_count_words_extra_whitespace(): void
    {
        $this->assertSame(3, SEO_Analysis::count_words('  one  two  three  '));
    }

    // ------------------------------------------------------------------
    //  normalize_text_for_match
    // ------------------------------------------------------------------

    public function test_normalize_removes_html(): void
    {
        $this->assertSame('hello world', SEO_Analysis::normalize_text_for_match('<b>Hello</b> World'));
    }

    public function test_normalize_lowercases(): void
    {
        $this->assertSame('hello world', SEO_Analysis::normalize_text_for_match('Hello World'));
    }

    public function test_normalize_strips_punctuation(): void
    {
        $this->assertSame('hello world', SEO_Analysis::normalize_text_for_match('hello, world!'));
    }

    public function test_normalize_collapses_spaces(): void
    {
        $this->assertSame('a b c', SEO_Analysis::normalize_text_for_match('a   b   c'));
    }

    public function test_normalize_empty(): void
    {
        $this->assertSame('', SEO_Analysis::normalize_text_for_match(''));
    }

    // ------------------------------------------------------------------
    //  extract_sentences
    // ------------------------------------------------------------------

    public function test_extract_sentences_empty(): void
    {
        $this->assertSame(array(), SEO_Analysis::extract_sentences(''));
    }

    public function test_extract_sentences_single(): void
    {
        $sentences = SEO_Analysis::extract_sentences('Hello world.');
        $this->assertCount(1, $sentences);
        $this->assertSame('Hello world.', $sentences[0]);
    }

    public function test_extract_sentences_multiple(): void
    {
        $sentences = SEO_Analysis::extract_sentences('First sentence. Second sentence. Third one!');
        $this->assertCount(3, $sentences);
    }

    public function test_extract_sentences_question(): void
    {
        $sentences = SEO_Analysis::extract_sentences('Is this working? Yes it is.');
        $this->assertCount(2, $sentences);
    }

    // ------------------------------------------------------------------
    //  count_transition_words
    // ------------------------------------------------------------------

    public function test_count_transition_words_none(): void
    {
        $this->assertSame(0, SEO_Analysis::count_transition_words('the cat sat on the mat'));
    }

    public function test_count_transition_words_however(): void
    {
        $count = SEO_Analysis::count_transition_words('however this is good however bad');
        $this->assertSame(2, $count);
    }

    public function test_count_transition_words_multiple(): void
    {
        $count = SEO_Analysis::count_transition_words('however the result is good therefore we continue');
        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function test_count_transition_words_empty(): void
    {
        $this->assertSame(0, SEO_Analysis::count_transition_words(''));
    }

    // ------------------------------------------------------------------
    //  count_passive_voice_sentences
    // ------------------------------------------------------------------

    public function test_count_passive_voice_none(): void
    {
        $sentences = array('The cat chased the dog.', 'She wrote a letter.');
        $this->assertSame(0, SEO_Analysis::count_passive_voice_sentences($sentences));
    }

    public function test_count_passive_voice_detected(): void
    {
        $sentences = array('The letter was written by her.', 'The cake was eaten.');
        $this->assertGreaterThanOrEqual(1, SEO_Analysis::count_passive_voice_sentences($sentences));
    }

    public function test_count_passive_voice_empty_array(): void
    {
        $this->assertSame(0, SEO_Analysis::count_passive_voice_sentences(array()));
    }

    // ------------------------------------------------------------------
    //  count_repeated_sentence_starts
    // ------------------------------------------------------------------

    public function test_count_repeated_starts_none(): void
    {
        $sentences = array('The cat sat.', 'A dog ran.', 'My friend left.');
        $this->assertSame(0, SEO_Analysis::count_repeated_sentence_starts($sentences));
    }

    public function test_count_repeated_starts_detected(): void
    {
        // Same first two words: "The cat" appears in both sentence 1 and 2 → 1 repeat.
        $sentences = array('The cat sat on the mat.', 'The cat ran away quickly.', 'A bird flew.');
        $this->assertSame(1, SEO_Analysis::count_repeated_sentence_starts($sentences));
    }

    public function test_count_repeated_starts_empty(): void
    {
        $this->assertSame(0, SEO_Analysis::count_repeated_sentence_starts(array()));
    }

    // ------------------------------------------------------------------
    //  is_question_style_heading
    // ------------------------------------------------------------------

    public function test_question_heading_with_question_mark(): void
    {
        $this->assertTrue(SEO_Analysis::is_question_style_heading('How does this work?'));
    }

    public function test_question_heading_how(): void
    {
        $this->assertTrue(SEO_Analysis::is_question_style_heading('How to configure SEO settings'));
    }

    public function test_question_heading_what(): void
    {
        $this->assertTrue(SEO_Analysis::is_question_style_heading('What is SEO'));
    }

    public function test_question_heading_why(): void
    {
        $this->assertTrue(SEO_Analysis::is_question_style_heading('Why use structured data'));
    }

    public function test_non_question_heading(): void
    {
        $this->assertFalse(SEO_Analysis::is_question_style_heading('Getting Started with SEO'));
    }

    public function test_non_question_heading_empty(): void
    {
        $this->assertFalse(SEO_Analysis::is_question_style_heading(''));
    }

    // ------------------------------------------------------------------
    //  is_generic_anchor_text
    // ------------------------------------------------------------------

    public function test_generic_anchor_click_here(): void
    {
        $this->assertTrue(SEO_Analysis::is_generic_anchor_text('click here'));
    }

    public function test_generic_anchor_read_more(): void
    {
        $this->assertTrue(SEO_Analysis::is_generic_anchor_text('read more'));
    }

    public function test_generic_anchor_here(): void
    {
        $this->assertTrue(SEO_Analysis::is_generic_anchor_text('here'));
    }

    public function test_generic_anchor_empty(): void
    {
        $this->assertTrue(SEO_Analysis::is_generic_anchor_text(''));
    }

    public function test_non_generic_anchor(): void
    {
        $this->assertFalse(SEO_Analysis::is_generic_anchor_text('WordPress SEO guide'));
    }

    public function test_non_generic_anchor_descriptive(): void
    {
        $this->assertFalse(SEO_Analysis::is_generic_anchor_text('learn about keyphrase density'));
    }

    // ------------------------------------------------------------------
    //  count_question_style_headings
    // ------------------------------------------------------------------

    public function test_count_question_headings_none(): void
    {
        $html = '<h2>About Us</h2><h3>Our Services</h3>';
        $this->assertSame(0, SEO_Analysis::count_question_style_headings($html));
    }

    public function test_count_question_headings_one(): void
    {
        $html = '<h2>How to use this tool?</h2><h3>About Us</h3>';
        $this->assertSame(1, SEO_Analysis::count_question_style_headings($html));
    }

    public function test_count_question_headings_multiple(): void
    {
        $html = '<h2>What is SEO?</h2><h3>Why does it matter?</h3><h4>How to get started</h4>';
        $this->assertSame(3, SEO_Analysis::count_question_style_headings($html));
    }

    public function test_count_question_headings_empty(): void
    {
        $this->assertSame(0, SEO_Analysis::count_question_style_headings(''));
    }

    // ------------------------------------------------------------------
    //  apply_editor_text_limits
    // ------------------------------------------------------------------

    public function test_apply_limits_does_not_truncate_within_bounds(): void
    {
        $fields = array(
            'seo_title'       => str_repeat('a', 50),
            'meta_description' => str_repeat('b', 100),
        );
        $result = SEO_Analysis::apply_editor_text_limits($fields);
        $this->assertSame($fields['seo_title'], $result['seo_title']);
        $this->assertSame($fields['meta_description'], $result['meta_description']);
    }

    public function test_apply_limits_truncates_title(): void
    {
        $fields = array('seo_title' => str_repeat('a', 80));
        $result = SEO_Analysis::apply_editor_text_limits($fields);
        $this->assertSame(SEO_Analysis::TITLE_MAX_LENGTH, SEO_Analysis::get_text_length($result['seo_title']));
    }

    public function test_apply_limits_truncates_description(): void
    {
        $fields = array('meta_description' => str_repeat('b', 200));
        $result = SEO_Analysis::apply_editor_text_limits($fields);
        $this->assertSame(SEO_Analysis::DESCRIPTION_MAX_LENGTH, SEO_Analysis::get_text_length($result['meta_description']));
    }

    public function test_apply_limits_empty_array(): void
    {
        $this->assertSame(array(), SEO_Analysis::apply_editor_text_limits(array()));
    }

    public function test_apply_limits_passthrough_other_fields(): void
    {
        $fields = array(
            'custom_field' => str_repeat('x', 200),
        );
        $result = SEO_Analysis::apply_editor_text_limits($fields);
        // Fields not in the limit map pass through unchanged.
        $this->assertSame($fields['custom_field'], $result['custom_field']);
    }
}
