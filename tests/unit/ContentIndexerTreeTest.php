<?php

declare(strict_types=1);

namespace AI_SEO_Captain\Tests\Unit;

use AI_SEO_Captain\Content_Indexer;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for Content_Indexer tree-rendering helpers.
 * Tests the pure formatting logic without database access.
 *
 * @covers \AI_SEO_Captain\Content_Indexer
 */
class ContentIndexerTreeTest extends TestCase
{
    private Content_Indexer $indexer;

    protected function setUp(): void
    {
        $this->indexer = new Content_Indexer();
    }

    // ------------------------------------------------------------------
    //  format_tree_node()
    // ------------------------------------------------------------------

    public function test_format_tree_node_basic(): void
    {
        $method = new ReflectionMethod(Content_Indexer::class, 'format_tree_node');
        $method->setAccessible(true);

        $row = array(
            'object_id'       => 10,
            'title'           => 'About Us',
            'slug'            => 'about-us',
            'focus_keyphrase' => 'about us company',
        );

        $result = $method->invoke($this->indexer, $row, 0, 99);

        $this->assertStringContainsString('/about-us/', $result);
        $this->assertStringContainsString('"About Us"', $result);
        $this->assertStringContainsString('[kp: "about us company"]', $result);
        $this->assertStringNotContainsString('YOU ARE HERE', $result);
    }

    public function test_format_tree_node_current_page_marker(): void
    {
        $method = new ReflectionMethod(Content_Indexer::class, 'format_tree_node');
        $method->setAccessible(true);

        $row = array(
            'object_id'       => 42,
            'title'           => 'Current Page',
            'slug'            => 'current',
            'focus_keyphrase' => '',
        );

        $result = $method->invoke($this->indexer, $row, 1, 42);

        $this->assertStringContainsString('← YOU ARE HERE', $result);
        $this->assertStringNotContainsString('[kp:', $result);
    }

    public function test_format_tree_node_indentation(): void
    {
        $method = new ReflectionMethod(Content_Indexer::class, 'format_tree_node');
        $method->setAccessible(true);

        $row = array(
            'object_id'       => 5,
            'title'           => 'Deep Page',
            'slug'            => 'deep',
            'focus_keyphrase' => '',
        );

        $result_depth0 = $method->invoke($this->indexer, $row, 0, 99);
        $result_depth3 = $method->invoke($this->indexer, $row, 3, 99);

        $this->assertStringStartsWith('/', $result_depth0);
        $this->assertStringStartsWith('      /', $result_depth3); // 3 × 2 spaces
    }

    // ------------------------------------------------------------------
    //  render_tree_level()
    // ------------------------------------------------------------------

    public function test_render_tree_level_builds_nested_tree(): void
    {
        $method = new ReflectionMethod(Content_Indexer::class, 'render_tree_level');
        $method->setAccessible(true);

        $by_parent = array(
            0 => array(
                array('object_id' => 1, 'title' => 'Home', 'slug' => 'home', 'focus_keyphrase' => ''),
                array('object_id' => 2, 'title' => 'Services', 'slug' => 'services', 'focus_keyphrase' => 'our services'),
            ),
            2 => array(
                array('object_id' => 3, 'title' => 'Web Dev', 'slug' => 'web-dev', 'focus_keyphrase' => 'web development'),
                array('object_id' => 4, 'title' => 'AI Consulting', 'slug' => 'ai-consulting', 'focus_keyphrase' => 'AI consulting'),
            ),
        );

        $lines = array();
        $args = array($by_parent, 0, 0, 3, &$lines);
        $method->invokeArgs($this->indexer, $args);

        $this->assertCount(4, $lines);
        $this->assertStringContainsString('"Home"', $lines[0]);
        $this->assertStringContainsString('"Services"', $lines[1]);
        $this->assertStringContainsString('"Web Dev"', $lines[2]);
        $this->assertStringContainsString('← YOU ARE HERE', $lines[2]);
        $this->assertStringStartsWith('  ', $lines[2]); // indented under Services
        $this->assertStringContainsString('"AI Consulting"', $lines[3]);
    }

    public function test_render_tree_level_empty_parent(): void
    {
        $method = new ReflectionMethod(Content_Indexer::class, 'render_tree_level');
        $method->setAccessible(true);

        $by_parent = array();
        $lines = array();
        $args = array($by_parent, 0, 0, 1, &$lines);
        $method->invokeArgs($this->indexer, $args);

        $this->assertEmpty($lines);
    }

    // ------------------------------------------------------------------
    //  build_branch_tree()
    // ------------------------------------------------------------------

    public function test_build_branch_tree_shows_ancestor_chain(): void
    {
        $method = new ReflectionMethod(Content_Indexer::class, 'build_branch_tree');
        $method->setAccessible(true);

        $by_id = array(
            1 => array('object_id' => 1, 'title' => 'Root', 'slug' => 'root', 'parent_id' => 0, 'focus_keyphrase' => ''),
            2 => array('object_id' => 2, 'title' => 'Services', 'slug' => 'services', 'parent_id' => 1, 'focus_keyphrase' => 'services'),
            3 => array('object_id' => 3, 'title' => 'AI', 'slug' => 'ai', 'parent_id' => 2, 'focus_keyphrase' => 'AI services'),
            4 => array('object_id' => 4, 'title' => 'Cloud', 'slug' => 'cloud', 'parent_id' => 2, 'focus_keyphrase' => 'cloud services'),
        );

        $by_parent = array(
            0 => array($by_id[1]),
            1 => array($by_id[2]),
            2 => array($by_id[3], $by_id[4]),
        );

        $result = $method->invoke($this->indexer, $by_id, $by_parent, 3);

        // Should show "Large site" note.
        $this->assertStringContainsString('Large site', $result);
        // Should show ancestor chain.
        $this->assertStringContainsString('"Root"', $result);
        $this->assertStringContainsString('"Services"', $result);
        // Current page and sibling.
        $this->assertStringContainsString('"AI"', $result);
        $this->assertStringContainsString('← YOU ARE HERE', $result);
        $this->assertStringContainsString('"Cloud"', $result);
    }

    public function test_build_branch_tree_includes_children_of_current(): void
    {
        $method = new ReflectionMethod(Content_Indexer::class, 'build_branch_tree');
        $method->setAccessible(true);

        $by_id = array(
            1 => array('object_id' => 1, 'title' => 'Root', 'slug' => 'root', 'parent_id' => 0, 'focus_keyphrase' => ''),
            2 => array('object_id' => 2, 'title' => 'Parent', 'slug' => 'parent', 'parent_id' => 0, 'focus_keyphrase' => ''),
            3 => array('object_id' => 3, 'title' => 'Child A', 'slug' => 'child-a', 'parent_id' => 2, 'focus_keyphrase' => ''),
        );

        $by_parent = array(
            0 => array($by_id[1], $by_id[2]),
            2 => array($by_id[3]),
        );

        // Current page is 2 (top-level), should include its child.
        $result = $method->invoke($this->indexer, $by_id, $by_parent, 2);

        $this->assertStringContainsString('"Parent"', $result);
        $this->assertStringContainsString('← YOU ARE HERE', $result);
        $this->assertStringContainsString('"Child A"', $result);
    }
}
