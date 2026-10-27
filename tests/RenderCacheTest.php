<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use ScBudgetWeb\SiteBuilder\Blocks\BlockLibrary;
use ScBudgetWeb\SiteBuilder\Blocks\PageRenderer;
use ScBudgetWeb\SiteBuilder\Blocks\RenderCache;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;
use ScBudgetWeb\SiteBuilder\Settings\Settings;
use ScBudgetWeb\SiteBuilder\SiteBuilder;

/**
 * Cached page HTML (HB-97): only for pages whose blocks are all cacheable, and never stale.
 */
class RenderCacheTest extends PageTestCase
{
    public function test_a_page_of_cacheable_blocks_is_rendered_once_until_something_is_saved(): void
    {
        $page = $this->page(['blocks' => [self::block('text', ['content' => '<p>Cached words</p>'])]]);

        $this->assertStringContainsString('Cached words', $this->render($page));

        // Changed behind the model's back (no events): the cached HTML is still used.
        $this->sneakyUpdate($page, [self::block('text', ['content' => '<p>Sneaky words</p>'])]);
        $this->assertStringContainsString('Cached words', $this->render($page));

        // Any setting saved (Branding, Navigation, Legal pages) makes it stale.
        app(Settings::class)->set(['branding.site_color' => '#123456']);
        $this->assertStringContainsString('Sneaky words', $this->render($page));
    }

    public function test_saving_a_page_or_a_library_block_makes_cached_pages_stale(): void
    {
        $item = app(BlockLibrary::class)->save('Call', 'call_to_action', ['heading' => 'Old heading']);
        $page = $this->page(['blocks' => [self::block('saved', ['saved_block_id' => $item->id])]]);
        $this->assertStringContainsString('Old heading', $this->render($page));

        $item->update(['blocks' => [self::block('call_to_action', ['heading' => 'New heading'])]]);
        $this->assertStringContainsString('New heading', $this->render($page));

        $page->update(['blocks' => [self::block('text', ['content' => '<p>Rewritten</p>'])]]);
        $this->assertStringContainsString('Rewritten', $this->render($page));
    }

    public function test_a_library_save_during_a_render_never_leaves_old_html_under_the_new_version(): void
    {
        $item = app(BlockLibrary::class)->save('Call', 'call_to_action', ['heading' => 'Old heading']);
        $page = $this->page(['blocks' => [self::block('saved', ['saved_block_id' => $item->id])]]);

        // Another request saves the library item just after this render has read it.
        $hasSaved = false;
        SavedBlock::retrieved(function (SavedBlock $retrieved) use (&$hasSaved): void {
            if ($hasSaved) {
                return;
            }

            $hasSaved = true;
            SavedBlock::query()->whereKey($retrieved->getKey())->toBase()
                ->update(['blocks' => json_encode([self::block('call_to_action', ['heading' => 'New heading'])])]);
            RenderCache::flush();
        });

        $this->assertStringContainsString('Old heading', $this->render($page));
        $this->assertStringContainsString('New heading', $this->render($page->fresh()));
    }

    public function test_pages_with_live_figures_or_videos_anywhere_are_never_cached(): void
    {
        app(SiteBuilder::class)->stats(['halls' => ['Halls', fn (): int => 3]]);
        $renderer = app(PageRenderer::class);

        $cases = [
            'live stats' => [self::block('stats', ['items' => [['source' => 'live:halls', 'label' => 'Halls']]])],
            'video' => [self::block('video', ['url' => 'https://youtu.be/dQw4w9WgXcQ'])],
            'video in a row' => [self::block('section', ['rows' => [['layout' => 'stacked', 'blocks' => [self::block('video', ['url' => 'https://youtu.be/dQw4w9WgXcQ'])]]]])],
        ];

        foreach ($cases as $case => $blocks) {
            $page = $this->page(['slug' => str($case)->slug()->value(), 'blocks' => $blocks]);
            $this->assertFalse($renderer->canCache($page, $page->blockList()), "A page with {$case} isn't cached.");
        }

        $typed = $this->page(['slug' => 'typed', 'blocks' => [self::block('stats', ['items' => [['source' => 'custom', 'value' => 5, 'label' => 'Rooms']]])]]);
        $this->assertTrue($renderer->canCache($typed, $typed->blockList()), 'Typed-in figures are cacheable.');
    }

    public function test_a_revision_preview_and_a_switched_off_cache_render_fresh(): void
    {
        $page = $this->page(['blocks' => [self::block('text', ['content' => '<p>Saved words</p>'])]]);
        $this->render($page);

        $page->forceFill(['blocks' => [self::block('text', ['content' => '<p>Old revision</p>'])]]);
        $this->assertStringContainsString('Old revision', $this->render($page));

        config(['site-builder.pages.cache' => false]);
        $fresh = Page::query()->find($page->id);
        $this->sneakyUpdate($fresh, [self::block('text', ['content' => '<p>Uncached words</p>'])]);
        $this->assertStringContainsString('Uncached words', $this->render(Page::query()->find($page->id)));
    }

    private function render(Page $page): string
    {
        return (string) app(PageRenderer::class)->render($page);
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function sneakyUpdate(Page $page, array $blocks): void
    {
        Page::query()->whereKey($page->id)->toBase()->update(['blocks' => json_encode($blocks)]);
        $page->setRawAttributes(Page::query()->find($page->id)->getAttributes(), sync: true);
    }
}
