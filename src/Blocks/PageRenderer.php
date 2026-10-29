<?php

namespace ScBudgetWeb\SiteBuilder\Blocks;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use ScBudgetWeb\SiteBuilder\Blocks\Core\BannerBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;
use Throwable;

/**
 * Renders a page's blocks in order. Each block with Appearance sits in a wrapper built from those settings. A block
 * type that no longer exists is skipped, and a block that fails to render is reported and skipped rather than taking
 * the whole page down (in debug mode it throws, so it's seen). Pages whose blocks are all cacheable are cached
 * (RenderCache), with a "hole" for each block with display conditions, rendered on each request.
 *
 * A Section renders its rows' blocks inside its wrapper. Blocks inside take the section's text colour (or their column's,
 * when it has a background) unless they have a background of their own, and its heading decoration unless they choose
 * their own (lesson 7).
 *
 * A block's preset (BlockPresets) fills in the style settings it leaves blank before anything is drawn, and its
 * dynamic content ({{ page_title }}, DynamicContent) is filled in once it's drawn.
 */
class PageRenderer
{
    public function __construct(private BlockRegistry $blocks, private BlockPresets $presets) {}

    /**
     * The page's blocks as HTML, from the cache when every block can be cached (RenderCache).
     */
    public function render(Page $page, bool $marksNodes = false): HtmlString
    {
        // The key (with the shared version) is read before the library blocks are expanded, so a library or settings
        // save that lands mid-render leaves this HTML under the old version, never under the new one.
        $key = RenderCache::key($page);
        $blocks = $page->blockList();

        // The visual builder's canvas is never cached: it shows a draft, marked up for editing.
        if ($marksNodes || ! $this->canCache($page, $blocks)) {
            return new HtmlString(implode('', $this->renderBlocks($blocks, $page, marksNodes: $marksNodes)));
        }

        $built = cache()->remember($key, now()->addDay(), function () use ($blocks, $page): array {
            $holes = [];
            $html = implode('', $this->renderBlocks($blocks, $page, holes: $holes));

            return ['html' => $html, 'holes' => $holes];
        });

        // Cached before holes (1.6): the HTML on its own.
        return new HtmlString(is_string($built) ? $built : $this->fillHoles($built, $page));
    }

    /**
     * Cached HTML with its holes filled: each block that's rendered on every request (it depends on the visitor, or
     * has display conditions) rendered now, where its marker is.
     *
     * @param  array{html?: mixed, holes?: mixed}  $built
     */
    public function fillHoles(array $built, Page $page): string
    {
        $filled = [];

        foreach (is_array($built['holes'] ?? null) ? $built['holes'] : [] as $marker => $hole) {
            $filled[$marker] = is_array($hole['block'] ?? null)
                ? implode('', $this->renderBlocks([$hole['block']], $page, $hole['parentIsDark'] ?? null, $hole['parentHeadingDecoration'] ?? null))
                : '';
        }

        return strtr(is_string($built['html'] ?? null) ? $built['html'] : '', $filled);
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
     * @param  bool  $marksNodes  for the visual builder's canvas: blocks, rows and columns carry data-sb-node and data-sb-uid
     * @param  int|null  $onlyIndex  only the block in this position (as it would render on the page), for the canvas to
     *                               swap in one changed block
     * @param  array<string, array{block: array<string, mixed>, parentIsDark: ?bool, parentHeadingDecoration: ?string}>|null  $holes
     *                                                                                                                                an array to collect blocks that can't be cached (PageBlock::isCacheable()): each is left as a marker,
     *                                                                                                                                and recorded to render on every request (TemplateRenderer), so everything around it can be cached
     * @return array<int, string>
     */
    public function renderBlocks(array $blocks, Page $page, ?bool $parentIsDark = null, ?string $parentHeadingDecoration = null, bool $marksNodes = false, ?int $onlyIndex = null, ?array &$holes = null): array
    {
        $rendered = [];
        $isNested = $parentIsDark !== null;

        foreach (array_values($blocks) as $index => $block) {
            if ($onlyIndex !== null && $index !== $onlyIndex) {
                continue;
            }

            $blockClass = $this->blocks->find($block['type']);

            // Sections go one level deep: a Hero, Banner or Section saved inside one (by hand) is skipped.
            if ($blockClass === null || ($isNested && in_array($block['type'], SectionBlock::EXCLUDED_BLOCKS, true))) {
                continue;
            }

            $block['data'] = $this->presets->apply($blockClass, is_array($block['data'] ?? null) ? $block['data'] : [], $isNested);

            if (! $blockClass::isVisible($block['data'])) {
                // On the canvas, a block with nothing to show yet (e.g. just added) still needs something to click.
                if ($marksNodes) {
                    $rendered[] = view('site-builder::blocks.canvas-placeholder', [
                        'uid' => is_string($block['uid'] ?? null) ? $block['uid'] : null,
                        'type' => $blockClass::name(),
                        'label' => $blockClass::label(),
                    ])->render();
                }

                continue;
            }

            // Display conditions depend on the visitor and the time: checked on each request, never on the canvas.
            $isConditional = ! $marksNodes && BlockConditions::has($block['data']);

            // A Section's own settings only: a block in its rows with dynamic content is a hole of its own.
            $hasPerRequestContent = DynamicContent::isPerRequest(is_a($blockClass, SectionBlock::class, true) ? array_diff_key($block['data'], ['rows' => true]) : $block['data']);

            if ($holes !== null && (! $blockClass::isCacheable($block['data']) || $isConditional || $hasPerRequestContent)) {
                $marker = '<!--sb-hole:'.Str::random(20).'-->';
                $holes[$marker] = ['block' => $block, 'parentIsDark' => $parentIsDark, 'parentHeadingDecoration' => $parentHeadingDecoration];
                $rendered[] = $marker;

                continue;
            }

            if ($isConditional && ! BlockConditions::passes($block['data'], auth()->user())) {
                continue;
            }

            try {
                $appearance = $blockClass::hasAppearance()
                    ? BlockAppearance::resolve($blockClass::shownAppearance($block['data']['appearance'] ?? null), $blockClass::defaultWidth(), $blockClass::defaultBackground(), $blockClass::defaultAnchor(), $parentIsDark, $parentHeadingDecoration)
                    : null;

                $extra = $blockClass::viewData($block['data'], $page);

                if (is_a($blockClass, SectionBlock::class, true)) {
                    // A column with its own background (or text colour) gives its blocks its text colour.
                    $extra['rows'] = [];

                    foreach (SectionBlock::rows($block['data']) as $row) {
                        foreach ($row['columns'] as $columnKey => $column) {
                            $row['columns'][$columnKey]['blocks'] = $this->renderBlocks(
                                $column['blocks'],
                                $page,
                                // The column's text colour, else its row's, else the section's.
                                match (true) {
                                    $column['textScheme'] !== null => $column['textScheme'] === 'light',
                                    ($row['textScheme'] ?? null) !== null => $row['textScheme'] === 'light',
                                    default => $appearance['isDark'] ?? false,
                                },
                                $appearance['headingDecoration'] ?? null,
                                $marksNodes,
                                holes: $holes,
                            );
                        }

                        $extra['rows'][] = $row;
                    }
                }

                $rendered[] = DynamicContent::replace(view('site-builder::blocks.wrapper', [
                    'blockClass' => $blockClass,
                    'block' => $block['data'],
                    'extra' => $extra,
                    'appearance' => $appearance,
                    'page' => $page,
                    // Only the page's first top-level block can hold its h1 (a Banner); blocks in a Section never do.
                    'index' => $isNested ? $index + 1 : $index,
                    'marksNodes' => $marksNodes,
                    'uid' => is_string($block['uid'] ?? null) ? $block['uid'] : null,
                    // Typography, spacing, borders and effects (data.design), on blocks with the Appearance wrapper.
                    'design' => $appearance === null ? null : BlockDesign::resolve(BlockDesign::without($block['data']['design'] ?? null, $blockClass::designWithout()), $appearance['panel'] !== null),
                ])->render(), $page);
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
