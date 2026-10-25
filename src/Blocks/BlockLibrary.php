<?php

namespace ScBudgetWeb\SiteBuilder\Blocks;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SavedBlockBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;

/**
 * The block library: linked "Saved block" blocks point at a library item and show its block, so editing the item
 * updates every page that uses it. A library item never holds a linked block, so there are no loops.
 */
class BlockLibrary
{
    /**
     * Swaps each linked block for its library item's block, at the top level and in a Section's rows, keeping the
     * keys. One query for every item used. A missing item, or one that can't go where it's linked (a Section linked
     * inside a Section by hand), is left out.
     *
     * @param  array<int|string, mixed>  $blocks
     * @return array<int|string, mixed>
     */
    public function expand(array $blocks): array
    {
        $ids = $this->linkedIds($blocks);

        if ($ids === []) {
            return $blocks;
        }

        $items = SavedBlock::query()->whereKey($ids)->get()->keyBy('id');

        return $this->expandWith($blocks, $items, isNested: false);
    }

    /**
     * Pages (including ones in the bin) that link to the item, at the top level or in a Section's rows.
     *
     * @return Collection<int, Page>
     */
    public function usedOn(SavedBlock $savedBlock): Collection
    {
        // Pages are few, so narrow to pages with any linked block and check the IDs here. A plain LIKE: the "_"s
        // match any character, which only widens the match (lesson 13).
        return Page::withTrashed()
            ->where('blocks', 'like', '%saved_block_id%')
            ->orderBy('title')
            ->get()
            ->filter(fn (Page $page): bool => in_array($savedBlock->getKey(), $this->linkedIds($page->blocks ?? []), true))
            ->values();
    }

    /**
     * Saves a builder item ({type, data}) to the library.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(string $name, string $type, array $data): SavedBlock
    {
        return SavedBlock::query()->create([
            'name' => $name,
            'blocks' => [['type' => $type, 'data' => $data]],
        ]);
    }

    /**
     * A copy of a library item's block for a page, with fresh keys for a Section's rows and their blocks, so the copy
     * never shares builder item IDs with the library.
     *
     * @return array{type: string, data: array<string, mixed>}|null
     */
    public function copy(mixed $id): ?array
    {
        $block = is_numeric($id) ? SavedBlock::query()->find((int) $id)?->block() : null;

        if ($block !== null && $block['type'] === SectionBlock::NAME && is_array($block['data']['rows'] ?? null)) {
            $block['data']['rows'] = collect($block['data']['rows'])
                ->mapWithKeys(fn (mixed $row): array => [(string) Str::uuid() => is_array($row) && is_array($row['blocks'] ?? null)
                    ? [...$row, 'blocks' => collect($row['blocks'])->mapWithKeys(fn (mixed $inner): array => [(string) Str::uuid() => $inner])->all()]
                    : $row])
                ->all();
        }

        return $block;
    }

    /**
     * Whether a library item's block can go in a Section's row (not a Hero, Banner or Section).
     */
    public static function canGoInSection(SavedBlock $savedBlock): bool
    {
        return ! in_array($savedBlock->type, SectionBlock::EXCLUDED_BLOCKS, true);
    }

    /**
     * @param  array<int|string, mixed>  $blocks
     * @return array<int, int>
     */
    private function linkedIds(array $blocks): array
    {
        $ids = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (($block['type'] ?? null) === SavedBlockBlock::NAME && is_numeric($block['data']['saved_block_id'] ?? null)) {
                $ids[] = (int) $block['data']['saved_block_id'];
            }

            if (($block['type'] ?? null) === SectionBlock::NAME && is_array($block['data']['rows'] ?? null)) {
                foreach ($block['data']['rows'] as $row) {
                    if (is_array($row) && is_array($row['blocks'] ?? null)) {
                        $ids = [...$ids, ...$this->linkedIds($row['blocks'])];
                    }
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<int|string, mixed>  $blocks
     * @param  Collection<int, SavedBlock>  $items
     * @return array<int|string, mixed>
     */
    private function expandWith(array $blocks, Collection $items, bool $isNested): array
    {
        $expanded = [];

        foreach ($blocks as $key => $block) {
            if (is_array($block) && ($block['type'] ?? null) === SavedBlockBlock::NAME) {
                $item = $items->get((int) ($block['data']['saved_block_id'] ?? 0));
                $block = $item?->block();

                // Library items never hold linked blocks, and Hero, Banner and Section can't go in a Section.
                if ($block === null || $block['type'] === SavedBlockBlock::NAME || ($isNested && ! self::canGoInSection($item))) {
                    continue;
                }
            } elseif (! $isNested && is_array($block) && ($block['type'] ?? null) === SectionBlock::NAME && is_array($block['data']['rows'] ?? null)) {
                // A page's own Section: its rows can link to library items. (A linked Section's rows come from the
                // library, which holds no links.)
                foreach ($block['data']['rows'] as $rowKey => $row) {
                    if (is_array($row) && is_array($row['blocks'] ?? null)) {
                        $block['data']['rows'][$rowKey]['blocks'] = $this->expandWith($row['blocks'], $items, isNested: true);
                    }
                }
            }

            $expanded[$key] = $block;
        }

        return $expanded;
    }
}
