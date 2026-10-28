<?php

namespace ScBudgetWeb\SiteBuilder\Blocks;

use Illuminate\Support\Str;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock;

/**
 * The shape of stored blocks (a page's, a library item's or a revision's), and the upgrades from older shapes.
 *
 * Version 1 is 1.4 and earlier. Version 2 (1.5): each column of a Section's row holds its own blocks, and every block,
 * row and column has a `uid`, unique in its list of blocks and kept from save to save, so an editor can point at one
 * (a block is {uid, type, data}, a row {uid, layout, …, columns}, a column {uid, blocks, …its settings}).
 *
 * Code may still save blocks in an older shape (a host's import, say), so each upgrade must leave blocks already in
 * its shape as they are.
 */
class BlockSchema
{
    public const VERSION = 2;

    /** A uid that can be kept: letters, numbers, - and _ only, so it's safe in an HTML attribute or a CSS selector. */
    public const UID_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

    /**
     * Stored blocks as read, upgraded from $version. A block, row or column without a uid (or with one used earlier in
     * the list) gets one from its position, so the blocks read the same every time until they're saved with them.
     *
     * @param  array<int|string, mixed>  $blocks
     * @return array<int|string, mixed>
     */
    public static function read(array $blocks, int $version = 1): array
    {
        return self::withUids(self::upgrade($blocks, $version), isStable: true);
    }

    /**
     * Blocks about to be saved: in today's shape, with a new uid for any block, row or column without one or with one
     * used earlier in the list (a cloned row brings its original's).
     *
     * @param  array<int|string, mixed>  $blocks
     * @return array<int|string, mixed>
     */
    public static function write(array $blocks): array
    {
        return self::withUids(self::upgrade($blocks), isStable: false);
    }

    /**
     * Blocks copied from elsewhere (the block library), with new uids throughout, so they never share one with the
     * blocks they came from.
     *
     * @param  array<int|string, mixed>  $blocks
     * @return array<int|string, mixed>
     */
    public static function withNewUids(array $blocks): array
    {
        $used = [];

        return self::assignUids(self::upgrade($blocks), '', $used, isStable: false, keepsUids: false);
    }

    /**
     * @param  array<int|string, mixed>  $blocks
     * @return array<int|string, mixed>
     */
    public static function upgrade(array $blocks, int $from = 1): array
    {
        if ($from < 2) {
            $blocks = SectionBlock::normaliseBlocks($blocks);
        }

        return $blocks;
    }

    /**
     * @param  array<int|string, mixed>  $blocks
     * @return array<int|string, mixed>
     */
    private static function withUids(array $blocks, bool $isStable): array
    {
        $used = [];

        return self::assignUids($blocks, '', $used, $isStable, keepsUids: true);
    }

    /**
     * @param  array<int|string, mixed>  $blocks
     * @param  array<string, true>  $used
     * @return array<int|string, mixed>
     */
    private static function assignUids(array $blocks, string $path, array &$used, bool $isStable, bool $keepsUids): array
    {
        foreach ($blocks as $key => $block) {
            if (! is_array($block)) {
                continue;
            }

            $blockPath = "{$path}b{$key}";
            $block['uid'] = self::uid($keepsUids ? $block['uid'] ?? null : null, $blockPath, $used, $isStable);

            if (($block['type'] ?? null) === SectionBlock::NAME && is_array($block['data']['rows'] ?? null)) {
                foreach ($block['data']['rows'] as $rowKey => $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $rowPath = "{$blockPath}.r{$rowKey}";
                    $row['uid'] = self::uid($keepsUids ? $row['uid'] ?? null : null, $rowPath, $used, $isStable);

                    foreach (is_array($row['columns'] ?? null) ? $row['columns'] : [] as $columnKey => $column) {
                        if (! is_array($column)) {
                            continue;
                        }

                        $columnPath = "{$rowPath}.c{$columnKey}";
                        $column['uid'] = self::uid($keepsUids ? $column['uid'] ?? null : null, $columnPath, $used, $isStable);
                        $column['blocks'] = self::assignUids(is_array($column['blocks'] ?? null) ? $column['blocks'] : [], "{$columnPath}.", $used, $isStable, $keepsUids);
                        $row['columns'][$columnKey] = $column;
                    }

                    $block['data']['rows'][$rowKey] = $row;
                }
            }

            $blocks[$key] = $block;
        }

        return $blocks;
    }

    /**
     * @param  array<string, true>  $used
     */
    private static function uid(mixed $uid, string $path, array &$used, bool $isStable): string
    {
        if (! is_string($uid) || preg_match(self::UID_PATTERN, $uid) !== 1 || isset($used[$uid])) {
            do {
                $uid = $isStable ? substr(md5("site-builder:{$path}"), 0, 16) : Str::lower((string) Str::ulid());
                $path .= "'";
            } while (isset($used[$uid]));
        }

        $used[$uid] = true;

        return $uid;
    }
}
