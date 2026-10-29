<?php

namespace ScBudgetWeb\SiteBuilder\Blocks;

use Closure;
use Illuminate\Support\Str;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock;

/**
 * Finds and changes the blocks, rows and columns in stored blocks (BlockSchema's shape) by their uid, for the visual
 * builder. Only the server's copy is ever looked through: the browser only names a uid. Keys are kept, so nothing
 * else moves.
 */
class BlockTree
{
    /**
     * The block, row or column with this uid, what it is, and where: a block in a Section's column is nested.
     *
     * @param  array<int|string, mixed>  $blocks
     * @return array{kind: 'block'|'row'|'column', node: array<string, mixed>, isNested: bool}|null
     */
    public static function find(array $blocks, string $uid, bool $isNested = false): ?array
    {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (($block['uid'] ?? null) === $uid) {
                return ['kind' => 'block', 'node' => $block, 'isNested' => $isNested];
            }

            foreach (self::rowsOf($block) as $row) {
                if (($row['uid'] ?? null) === $uid) {
                    return ['kind' => 'row', 'node' => $row, 'isNested' => false];
                }

                foreach ($row['columns'] ?? [] as $column) {
                    if (! is_array($column)) {
                        continue;
                    }

                    if (($column['uid'] ?? null) === $uid) {
                        return ['kind' => 'column', 'node' => $column, 'isNested' => false];
                    }

                    $found = self::find(is_array($column['blocks'] ?? null) ? $column['blocks'] : [], $uid, isNested: true);

                    if ($found !== null) {
                        return $found;
                    }
                }
            }
        }

        return null;
    }

    /**
     * The blocks with the block, row or column with this uid replaced by $change($node). Nothing changes when no
     * item has the uid.
     *
     * @param  array<int|string, mixed>  $blocks
     * @param  Closure(array<string, mixed>): array<string, mixed>  $change
     * @return array<int|string, mixed>
     */
    public static function update(array $blocks, string $uid, Closure $change): array
    {
        foreach ($blocks as $key => $block) {
            if (! is_array($block)) {
                continue;
            }

            if (($block['uid'] ?? null) === $uid) {
                $blocks[$key] = $change($block);

                return $blocks;
            }

            if (! self::rowsOf($block)) {
                continue;
            }

            foreach ($block['data']['rows'] as $rowKey => $row) {
                if (! is_array($row)) {
                    continue;
                }

                if (($row['uid'] ?? null) === $uid) {
                    $blocks[$key]['data']['rows'][$rowKey] = $change($row);

                    return $blocks;
                }

                foreach ($row['columns'] ?? [] as $columnKey => $column) {
                    if (! is_array($column)) {
                        continue;
                    }

                    if (($column['uid'] ?? null) === $uid) {
                        $blocks[$key]['data']['rows'][$rowKey]['columns'][$columnKey] = $change($column);

                        return $blocks;
                    }

                    $inner = is_array($column['blocks'] ?? null) ? $column['blocks'] : [];
                    $changed = self::update($inner, $uid, $change);

                    if ($changed !== $inner) {
                        $blocks[$key]['data']['rows'][$rowKey]['columns'][$columnKey]['blocks'] = $changed;

                        return $blocks;
                    }
                }
            }
        }

        return $blocks;
    }

    /**
     * The blocks with $change applied to every block, including those in a Section's columns (a Section's own change
     * first, then its blocks').
     *
     * @param  array<int|string, mixed>  $blocks
     * @param  Closure(array<string, mixed>): array<string, mixed>  $change
     * @return array<int|string, mixed>
     */
    public static function map(array $blocks, Closure $change): array
    {
        foreach ($blocks as $key => $block) {
            if (! is_array($block)) {
                continue;
            }

            $block = $change($block);

            foreach (array_keys(self::rowsOf($block)) as $rowKey) {
                foreach ($block['data']['rows'][$rowKey]['columns'] ?? [] as $columnKey => $column) {
                    if (is_array($column) && is_array($column['blocks'] ?? null)) {
                        $block['data']['rows'][$rowKey]['columns'][$columnKey]['blocks'] = self::map($column['blocks'], $change);
                    }
                }
            }

            $blocks[$key] = $block;
        }

        return $blocks;
    }

    /**
     * Where the block, row or column with this uid is: the keys from the top down to the list holding it, and its key
     * in that list. A block's list is the page's (path []) or a column's; a row's is its Section's rows; a column's is
     * its row's columns.
     *
     * @param  array<int|string, mixed>  $blocks
     * @param  array<int, int|string>  $path
     * @return array{kind: 'block'|'row'|'column', path: array<int, int|string>, key: int|string, node: array<string, mixed>}|null
     */
    public static function locate(array $blocks, string $uid, array $path = []): ?array
    {
        foreach ($blocks as $key => $block) {
            if (! is_array($block)) {
                continue;
            }

            if (($block['uid'] ?? null) === $uid) {
                return ['kind' => 'block', 'path' => $path, 'key' => $key, 'node' => $block];
            }

            foreach (self::rowsOf($block) as $rowKey => $row) {
                $rowsPath = [...$path, $key, 'data', 'rows'];

                if (($row['uid'] ?? null) === $uid) {
                    return ['kind' => 'row', 'path' => $rowsPath, 'key' => $rowKey, 'node' => $row];
                }

                foreach ($row['columns'] ?? [] as $columnKey => $column) {
                    if (! is_array($column)) {
                        continue;
                    }

                    if (($column['uid'] ?? null) === $uid) {
                        return ['kind' => 'column', 'path' => [...$rowsPath, $rowKey, 'columns'], 'key' => $columnKey, 'node' => $column];
                    }

                    $found = self::locate(is_array($column['blocks'] ?? null) ? $column['blocks'] : [], $uid, [...$rowsPath, $rowKey, 'columns', $columnKey, 'blocks']);

                    if ($found !== null) {
                        return $found;
                    }
                }
            }
        }

        return null;
    }

    /**
     * The way down to the block, row or column with this uid, from the top: each Section, row and column it's in, then
     * itself (the visual builder's panel breadcrumb). Empty when there's no such item.
     *
     * @param  array<int|string, mixed>  $blocks
     * @return array<int, array{kind: 'block'|'row'|'column', node: array<string, mixed>}>
     */
    public static function trail(array $blocks, string $uid): array
    {
        $found = self::locate($blocks, $uid);

        if ($found === null) {
            return [];
        }

        $trail = [];
        $list = $blocks;
        $path = [...$found['path'], $found['key']];

        // The path alternates list keys and the names of the lists inside: block › data › rows › row › columns › column › blocks…
        for ($index = 0; $index < count($path); $index++) {
            $node = $list[$path[$index]] ?? null;

            if (! is_array($node)) {
                return [];
            }

            $next = $path[$index + 1] ?? null;
            $kind = match ($next) {
                'columns' => 'row',
                'blocks' => 'column',
                default => 'block',
            };
            $trail[] = ['kind' => $index === count($path) - 1 ? $found['kind'] : $kind, 'node' => $node];

            if ($next === 'data') {
                // A Section: on to its rows.
                $list = is_array($node['data']['rows'] ?? null) ? $node['data']['rows'] : [];
                $index += 2;
            } elseif ($next !== null) {
                $list = is_array($node[$next] ?? null) ? $node[$next] : [];
                $index++;
            }
        }

        return $trail;
    }

    /**
     * The blocks without the block or row with this uid (a column can't be removed: a row's layout sets them), or
     * null when there's no such block or row.
     *
     * @param  array<int|string, mixed>  $blocks
     * @return array<int|string, mixed>|null
     */
    public static function remove(array $blocks, string $uid): ?array
    {
        $found = self::locate($blocks, $uid);

        if ($found === null || $found['kind'] === 'column') {
            return null;
        }

        $list = self::listAt($blocks, $found['path']);
        $isList = array_is_list($list);
        unset($list[$found['key']]);

        return self::withListAt($blocks, $found['path'], $isList ? array_values($list) : $list);
    }

    /**
     * The blocks with $node (a block, or a row) added: before or after the block or row with $targetUid, at the end of
     * a column ($position "in" and a column's uid; a row: "in" and a Section's uid), or at the end of the page (no
     * target). Null when it can't go there: a row only goes in a Section, and a Section, Hero or Banner only on the
     * page itself, never in a column.
     *
     * @param  array<string, mixed>  $node
     * @param  'before'|'after'|'in'  $position
     * @return array<int|string, mixed>|null
     */
    public static function insert(array $blocks, array $node, ?string $targetUid = null, string $position = 'after'): ?array
    {
        $isRow = ! isset($node['type']);

        if ($targetUid === null) {
            return $isRow ? null : self::withListAt($blocks, [], self::insertInto($blocks, count($blocks), $node));
        }

        $target = self::locate($blocks, $targetUid);

        if ($target === null) {
            return null;
        }

        if ($position === 'in') {
            $path = match (true) {
                ! $isRow && $target['kind'] === 'column' => [...$target['path'], $target['key'], 'blocks'],
                $isRow && $target['kind'] === 'block' && ($target['node']['type'] ?? null) === SectionBlock::NAME => [...$target['path'], $target['key'], 'data', 'rows'],
                default => null,
            };

            if ($path === null || (! $isRow && ! self::canGoIn($node, $path))) {
                return null;
            }

            $list = self::listAt($blocks, $path);

            return self::withListAt($blocks, $path, self::insertInto($list, count($list), $node));
        }

        if ($target['kind'] !== ($isRow ? 'row' : 'block') || (! $isRow && ! self::canGoIn($node, $target['path']))) {
            return null;
        }

        $list = self::listAt($blocks, $target['path']);
        $index = (int) array_search($target['key'], array_keys($list), true) + ($position === 'after' ? 1 : 0);

        return self::withListAt($blocks, $target['path'], self::insertInto($list, $index, $node));
    }

    /**
     * The blocks with a copy of the block or row with this uid (new uids throughout) just after it, and the copy's uid.
     *
     * @param  array<int|string, mixed>  $blocks
     * @return array{0: array<int|string, mixed>, 1: string}|null
     */
    public static function duplicate(array $blocks, string $uid): ?array
    {
        $found = self::locate($blocks, $uid);

        if ($found === null || $found['kind'] === 'column') {
            return null;
        }

        $copy = $found['kind'] === 'block'
            ? BlockSchema::withNewUids([$found['node']])[0]
            : array_values(BlockSchema::withNewUids([['type' => SectionBlock::NAME, 'data' => ['rows' => [$found['node']]]]])[0]['data']['rows'])[0];

        $changed = self::insert($blocks, $copy, $uid, 'after');

        return $changed === null ? null : [$changed, $copy['uid']];
    }

    /**
     * The blocks with the block or row with this uid moved before or after another (or "in" a column or Section, as
     * insert() takes), or null when it can't go there (e.g. into itself).
     *
     * @param  'before'|'after'|'in'  $position
     * @param  array<int|string, mixed>  $blocks
     * @return array<int|string, mixed>|null
     */
    public static function move(array $blocks, string $uid, string $targetUid, string $position): ?array
    {
        $found = self::locate($blocks, $uid);

        if ($found === null || $found['kind'] === 'column' || $uid === $targetUid || self::find([$found['node']], $targetUid) !== null
            || ($found['kind'] === 'row' && self::find([['type' => SectionBlock::NAME, 'data' => ['rows' => [$found['node']]]]], $targetUid) !== null)) {
            return null;
        }

        $without = self::remove($blocks, $uid);

        return $without === null ? null : self::insert($without, $found['node'], $targetUid, $position);
    }

    /**
     * One step: up or down among its neighbours, or (a block in a Section's column) to the end of the column on its
     * left or right. Null when it can't go further.
     *
     * @param  'up'|'down'|'left'|'right'  $direction
     * @param  array<int|string, mixed>  $blocks
     * @return array<int|string, mixed>|null
     */
    public static function step(array $blocks, string $uid, string $direction): ?array
    {
        $found = self::locate($blocks, $uid);

        if ($found === null || $found['kind'] === 'column') {
            return null;
        }

        if (in_array($direction, ['up', 'down'], true)) {
            $keys = array_keys(self::listAt($blocks, $found['path']));
            $neighbour = $keys[(int) array_search($found['key'], $keys, true) + ($direction === 'up' ? -1 : 1)] ?? null;
            $neighbourUid = $neighbour === null ? null : (self::listAt($blocks, $found['path'])[$neighbour]['uid'] ?? null);

            return is_string($neighbourUid) ? self::move($blocks, $uid, $neighbourUid, $direction === 'up' ? 'before' : 'after') : null;
        }

        // A block in a column: its row's columns are the list two levels up.
        if ($found['kind'] !== 'block' || count($found['path']) < 3) {
            return null;
        }

        $columnsPath = array_slice($found['path'], 0, -2);
        $columns = self::listAt($blocks, $columnsPath);
        $columnKeys = array_keys($columns);
        $nextKey = $columnKeys[(int) array_search($found['path'][count($found['path']) - 2], $columnKeys, true) + ($direction === 'left' ? -1 : 1)] ?? null;
        $nextUid = $nextKey === null ? null : ($columns[$nextKey]['uid'] ?? null);

        return is_string($nextUid) ? self::move($blocks, $uid, $nextUid, 'in') : null;
    }

    /**
     * Whether a block can go in the list at $path: anything on the page itself, but in a column, not a Section, Hero or
     * Banner.
     *
     * @param  array<string, mixed>  $node
     * @param  array<int, int|string>  $path
     */
    private static function canGoIn(array $node, array $path): bool
    {
        return $path === [] || ! in_array($node['type'] ?? null, SectionBlock::EXCLUDED_BLOCKS, true);
    }

    /**
     * @param  array<int|string, mixed>  $blocks
     * @param  array<int, int|string>  $path
     * @return array<int|string, mixed>
     */
    private static function listAt(array $blocks, array $path): array
    {
        foreach ($path as $key) {
            $blocks = is_array($blocks[$key] ?? null) ? $blocks[$key] : [];
        }

        return $blocks;
    }

    /**
     * @param  array<int|string, mixed>  $blocks
     * @param  array<int, int|string>  $path
     * @param  array<int|string, mixed>  $list
     * @return array<int|string, mixed>
     */
    private static function withListAt(array $blocks, array $path, array $list): array
    {
        if ($path === []) {
            return $list;
        }

        $key = array_shift($path);
        $blocks[$key] = self::withListAt(is_array($blocks[$key] ?? null) ? $blocks[$key] : [], $path, $list);

        return $blocks;
    }

    /**
     * $list with $node at $index: spliced into a plain list, or under a new key (as the editor's item IDs) otherwise.
     *
     * @param  array<int|string, mixed>  $list
     * @param  array<string, mixed>  $node
     * @return array<int|string, mixed>
     */
    private static function insertInto(array $list, int $index, array $node): array
    {
        if (array_is_list($list)) {
            array_splice($list, $index, 0, [$node]);

            return $list;
        }

        return array_slice($list, 0, $index, preserve_keys: true) + [(string) Str::uuid() => $node] + array_slice($list, $index, preserve_keys: true);
    }

    /**
     * A Section's rows (in BlockSchema's shape, with columns), or none for any other block.
     *
     * @param  array<string, mixed>  $block
     * @return array<int|string, array<string, mixed>>
     */
    private static function rowsOf(array $block): array
    {
        if (($block['type'] ?? null) !== SectionBlock::NAME || ! is_array($block['data']['rows'] ?? null)) {
            return [];
        }

        return array_filter($block['data']['rows'], 'is_array');
    }
}
