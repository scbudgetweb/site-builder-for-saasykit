<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use ScBudgetWeb\SiteBuilder\Blocks\Core\StatsBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Core\TextBlock;
use ScBudgetWeb\SiteBuilder\Blocks\PageRenderer;

/**
 * Content › When empty, for blocks that pull content in (PageBlock::pullsContent()): hide the block, show its heading
 * only, or its heading and a message. Stats with a live figure the app no longer provides is one.
 */
class WhenEmptyTest extends PageTestCase
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function emptyStats(array $data = []): string
    {
        $this->page(['blocks' => [self::block('stats', [
            'heading' => 'Our numbers',
            'items' => [['source' => 'live:gone', 'label' => 'Halls']],
            ...$data,
        ])]]);

        return $this->get('/about-us')->assertOk()->getContent();
    }

    public function test_an_empty_block_is_hidden_by_default(): void
    {
        $html = $this->emptyStats();

        $this->assertStringNotContainsString('Our numbers', $html);
        $this->assertStringNotContainsString('data-block="stats"', $html, 'No gap where the block would be.');
    }

    public function test_it_can_show_its_heading_only(): void
    {
        $html = $this->emptyStats(['when_empty' => 'heading']);

        $this->assertStringContainsString('Our numbers', $html);
        $this->assertStringNotContainsString('sb-items', $html);
        $this->assertStringNotContainsString('sb-empty-message', $html);
    }

    public function test_it_can_show_a_message(): void
    {
        $html = $this->emptyStats(['when_empty' => 'message', 'empty_message' => 'Figures <soon> for Sometown']);
        $this->assertStringContainsString('Our numbers', $html);
        $this->assertStringContainsString('Figures &lt;soon&gt; for Sometown', $html);
    }

    public function test_a_blank_message_has_a_standard_one_and_a_tampered_choice_hides(): void
    {
        $this->assertStringContainsString('Nothing here yet. Check back soon.', $this->emptyStats(['when_empty' => 'message']));

        $this->assertSame('hide', StatsBlock::whenEmpty(['when_empty' => 'explode']));
    }

    public function test_a_block_with_content_is_unaffected(): void
    {
        $this->page(['blocks' => [self::block('stats', ['when_empty' => 'message', 'items' => [['source' => 'custom', 'value' => 5, 'label' => 'Rooms']]])]]);

        $html = $this->get('/about-us')->getContent();
        $this->assertStringContainsString('Rooms', $html);
        $this->assertStringNotContainsString('sb-empty-message', $html);
    }

    public function test_the_canvas_still_has_something_to_click(): void
    {
        $page = $this->page(['blocks' => [self::block('stats', ['items' => [['source' => 'live:gone', 'label' => 'Halls']]])]]);

        $rendered = implode('', app(PageRenderer::class)->renderBlocks($page->blocks, $page, marksNodes: true));

        $this->assertStringContainsString('sb-canvas-empty', $rendered);
        $this->assertStringContainsString('data-sb-type="stats"', $rendered);
    }

    public function test_only_blocks_that_pull_content_offer_it(): void
    {
        $keys = fn (string $blockClass): array => array_map(fn ($toggle) => $toggle?->key, (new \ReflectionMethod($blockClass, 'toggles'))->invoke(null, false));

        $this->assertContains('when_empty', $keys(StatsBlock::class));
        $this->assertNotContains('when_empty', $keys(TextBlock::class));
    }
}
