<?php

namespace ScBudgetWeb\SiteBuilder\Blocks;

use Illuminate\Support\Arr;

/**
 * Copying a block's style and pasting it on others: all of it, or one group (its background, spacing,
 * heading text, buttons…). Each group is a fixed list of saved keys (a trailing * matches keys starting with what's
 * before it), so a paste only ever replaces style settings, never content. Pasting replaces the group on the target:
 * a setting the copied block doesn't have is cleared, so the result looks the same.
 *
 * "All styles" also takes the block's own style settings (its Style and Layout tabs, e.g. its cards' look), but only
 * onto a block of the same type; between types it's the shared groups.
 *
 * The clipboard lives in the person's session (SESSION_KEY): built on the server, so nothing pasted comes from the
 * browser, and it's there on another page too.
 */
final class StyleClipboard
{
    public const SESSION_KEY = 'site-builder.style-clipboard';

    /** Group => label. */
    public const GROUPS = [
        'all' => 'All styles',
        'background' => 'Background',
        'spacing' => 'Spacing & size',
        'heading' => 'Heading text',
        'body' => 'Body text',
        'links' => 'Links',
        'borders' => 'Borders, corners & shadow',
        'buttons' => 'Buttons',
        'effects' => 'Effects',
        'position' => 'Transform, position & motion',
    ];

    private const PATHS = [
        'background' => ['appearance.background', 'appearance.text', 'appearance.background_color', 'appearance.gradient_*', 'appearance.background_image', 'appearance.overlay_*', 'appearance.image_*', 'design.dividers'],
        'spacing' => ['appearance.width', 'appearance.spacing', 'appearance.background_area', 'appearance.corners', 'appearance.overlap', 'design.spacing'],
        'heading' => ['appearance.heading_size', 'appearance.heading_decoration', 'appearance.heading_align', 'design.typography.heading'],
        'body' => ['appearance.text_size', 'design.typography.body'],
        'links' => ['design.typography.links'],
        'borders' => ['appearance.border_*', 'appearance.shadow*', 'design.borders'],
        'buttons' => ['button_design'],
        'effects' => ['design.effects'],
        'position' => ['design.position', 'design.motion'],
    ];

    /**
     * The keys a group covers. "All styles" between blocks of the same type also covers the block's own style settings.
     *
     * @param  class-string<PageBlock>|null  $blockClass
     * @return array<int, string>
     */
    public static function paths(string $group, ?string $blockClass = null): array
    {
        if ($group !== 'all') {
            return self::PATHS[$group] ?? [];
        }

        return array_values(array_unique([...array_merge(...array_values(self::PATHS)), ...($blockClass ? $blockClass::ownStyleKeys() : [])]));
    }

    /**
     * The block data's values for these keys (only those it has).
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $paths
     * @return array<string, mixed>
     */
    public static function extract(array $data, array $paths): array
    {
        $values = [];

        foreach (self::matchingKeys($data, $paths) as $key) {
            Arr::set($values, $key, Arr::get($data, $key));
        }

        return $values;
    }

    /**
     * The block data with these keys replaced by the copied values: each is cleared first, so a setting the copy
     * doesn't have goes back to its default.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $paths
     * @return array<string, mixed>
     */
    public static function apply(array $data, array $values, array $paths): array
    {
        foreach (self::matchingKeys($data, $paths) as $key) {
            Arr::forget($data, $key);
        }

        foreach (self::matchingKeys($values, $paths) as $key) {
            Arr::set($data, $key, Arr::get($values, $key));
        }

        return $data;
    }

    /**
     * The dot keys in $data that the paths cover.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    private static function matchingKeys(array $data, array $paths): array
    {
        $keys = [];

        foreach ($paths as $path) {
            if (! str_ends_with($path, '*')) {
                if (Arr::has($data, $path)) {
                    $keys[] = $path;
                }

                continue;
            }

            $parentPath = str_contains($path, '.') ? substr($path, 0, (int) strrpos($path, '.')) : null;
            $prefix = substr($path, $parentPath === null ? 0 : strlen($parentPath) + 1, -1);
            $parent = $parentPath === null ? $data : Arr::get($data, $parentPath);

            foreach (is_array($parent) ? array_keys($parent) : [] as $key) {
                if (is_string($key) && str_starts_with($key, $prefix)) {
                    $keys[] = $parentPath === null ? $key : "{$parentPath}.{$key}";
                }
            }
        }

        return array_values(array_unique($keys));
    }
}
