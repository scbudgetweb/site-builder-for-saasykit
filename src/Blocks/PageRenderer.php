<?php

namespace ScBudgetWeb\SiteBuilder\Blocks;

use Illuminate\Support\HtmlString;
use ScBudgetWeb\SiteBuilder\Blocks\Core\BannerBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;
use Throwable;

/**
 * Renders a page's blocks in order. Each block with Appearance sits in a wrapper built from those settings. A block
 * type that no longer exists is skipped, and a block that fails to render is reported and skipped rather than taking
 * the whole page down (in debug mode it throws, so it's seen). Pages whose blocks are all cacheable are cached
 * (RenderCache).
 *
 * A Section renders its rows' blocks inside its wrapper. Blocks inside take the section's text colour unless they have
 * a background of their own, and its heading decoration unless they choose their own (lesson 7).
 */
class PageRenderer
{
    public function __construct(private BlockRegistry $blocks) {}

    /**
     * The page's blocks as HTML, from the cache when every block can be cached (RenderCache).
     */
    public function render(Page $page): HtmlString
    {
        // The key (with the shared version) is read before the library blocks are expanded, so a library or settings
        // save that lands mid-render leaves this HTML under the old version, never under the new one.
        $key = RenderCache::key($page);
        $blocks = $page->blockList();

        if (! $this->canCache($page, $blocks)) {
            return new HtmlString(implode('', $this->renderBlocks($blocks, $page)));
        }

        return new HtmlString(cache()->remember($key, now()->addDay(), fn (): string => implode('', $this->renderBlocks($blocks, $page))));
    }

    /**
     * Only a saved page (not a revision preview, which fills an old version in without saving) whose blocks, including
     * those in a Section's rows, are all cacheable.
     *
     * @param  array<int, array{type: string, data: array<string, mixed>}>  $blocks
     */
    public function canCache(Page $page, array $blocks): bool
    {
        return RenderCache::isEnabled() && $page->exists && ! $page->isDirty() && $this->allCacheable($blocks);
    }

    /**
     * @param  array<int, array{type: string, data: array<string, mixed>}>  $blocks
     */
    private function allCacheable(array $blocks): bool
    {
        foreach ($blocks as $block) {
            $blockClass = $this->blocks->find($block['type']);

            if ($blockClass !== null && ! $blockClass::isCacheable($block['data'])) {
                return false;
            }

            if (is_a($blockClass, SectionBlock::class, true)) {
                foreach (SectionBlock::rows($block['data']) as $row) {
                    if (! $this->allCacheable($row['blocks'])) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * The HTML of each block, in order (failed, hidden or unknown blocks are left out).
     *
     * @param  array<int, array{type: string, data: array<string, mixed>}>  $blocks
     * @param  bool|null  $parentIsDark  for the blocks in a Section's row: whether the section is dark
     * @param  string|null  $parentHeadingDecoration  for the blocks in a Section's row: the section's heading decoration
     * @return array<int, string>
     */
    public function renderBlocks(array $blocks, Page $page, ?bool $parentIsDark = null, ?string $parentHeadingDecoration = null): array
    {
        $rendered = [];
        $isNested = $parentIsDark !== null;

        foreach (array_values($blocks) as $index => $block) {
            $blockClass = $this->blocks->find($block['type']);

            // Sections go one level deep: a Hero, Banner or Section saved inside one (by hand) is skipped.
            if ($blockClass === null || ($isNested && in_array($block['type'], SectionBlock::EXCLUDED_BLOCKS, true)) || ! $blockClass::isVisible($block['data'])) {
                continue;
            }

            try {
                $appearance = $blockClass::hasAppearance()
                    ? BlockAppearance::resolve($block['data']['appearance'] ?? null, $blockClass::defaultWidth(), $blockClass::defaultBackground(), $blockClass::defaultAnchor(), $parentIsDark, $parentHeadingDecoration)
                    : null;

                $extra = $blockClass::viewData($block['data'], $page);

                if (is_a($blockClass, SectionBlock::class, true)) {
                    $extra['rows'] = array_map(fn (array $row): array => [
                        ...$row,
                        'blocks' => $this->renderBlocks($row['blocks'], $page, $appearance['isDark'] ?? false, $appearance['headingDecoration'] ?? null),
                    ], SectionBlock::rows($block['data']));
                }

                $rendered[] = view('site-builder::blocks.wrapper', [
                    'blockClass' => $blockClass,
                    'block' => $block['data'],
                    'extra' => $extra,
                    'appearance' => $appearance,
                    'page' => $page,
                    // Only the page's first top-level block can hold its h1 (a Banner); blocks in a Section never do.
                    'index' => $isNested ? $index + 1 : $index,
                ])->render();
            } catch (Throwable $exception) {
                report($exception);

                if (app()->hasDebugModeEnabled()) {
                    throw $exception;
                }
            }
        }

        return $rendered;
    }

    /**
     * The standard page banner (with the page title) shows unless it's turned off or the page opens with its own banner.
     */
    public function showsPageBanner(Page $page): bool
    {
        return $page->show_page_banner && ! $this->opensWithBanner($page);
    }

    public function opensWithBanner(Page $page): bool
    {
        return in_array($page->blockList()[0]['type'] ?? null, BannerBlock::TYPES, true);
    }
}
