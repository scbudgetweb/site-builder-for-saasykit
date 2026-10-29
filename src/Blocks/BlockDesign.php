<?php

namespace ScBudgetWeb\SiteBuilder\Blocks;

use ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Design\BordersBackgrounds;
use ScBudgetWeb\SiteBuilder\Blocks\Design\DesignGroup;
use ScBudgetWeb\SiteBuilder\Blocks\Design\DesignOutput;
use ScBudgetWeb\SiteBuilder\Blocks\Design\Dividers;
use ScBudgetWeb\SiteBuilder\Blocks\Design\Effects;
use ScBudgetWeb\SiteBuilder\Blocks\Design\Motion;
use ScBudgetWeb\SiteBuilder\Blocks\Design\Position;
use ScBudgetWeb\SiteBuilder\Blocks\Design\Spacing;
use ScBudgetWeb\SiteBuilder\Blocks\Design\Typography;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\PanelToggle;
use ScBudgetWeb\SiteBuilder\Branding\Branding;

/**
 * A block's design options: typography, spacing and size, borders and backgrounds, and effects, on top of
 * the shared Appearance settings. Saved under data.design, one key per group (DesignGroup); all optional, so a block
 * without them renders exactly as before.
 *
 * Every value becomes a CSS custom property and a class from a fixed list (DesignOutput), read by the package's CSS
 * (resources/css/design), never CSS written for the page: so pages stay cacheable and nothing typed into a setting can
 * reach a style sheet. Sizes and spacing can differ by screen size (DesignValue).
 */
class BlockDesign
{
    /**
     * @return array<int, class-string<DesignGroup>>
     */
    public static function groups(): array
    {
        return [Typography::class, Spacing::class, BordersBackgrounds::class, Effects::class, Position::class, Dividers::class, Motion::class];
    }

    /**
     * Every group's settings, as toggles in the block's panel, less those $without names (sections, rows and columns leave text settings
     * off sections, rows and columns): a group's key ("typography"), or one of its settings ("spacing.margin").
     *
     * @param  array<int, string>  $without
     * @return array<int, PanelToggle>
     */
    public static function toggles(array $without = []): array
    {
        $toggles = [];

        foreach (self::groups() as $group) {
            if (in_array($group::key(), $without, true)) {
                continue;
            }

            foreach ($group::toggles() as $toggle) {
                $fields = array_values(array_filter(
                    $toggle->fields,
                    fn (object $field): bool => ! method_exists($field, 'getName') || ! in_array($group::key().'.'.$field->getName(), $without, true),
                ));

                $toggles[] = new PanelToggle($toggle->tab, $toggle->key, $toggle->title, $fields, $toggle->priority, $toggle->statePath, $toggle->isOpen);
            }
        }

        return $toggles;
    }

    /**
     * A block's saved design less the settings its panel doesn't offer (toggles()' $without), so a value saved before
     * a setting was taken off still can't apply out of sight.
     *
     * @param  array<int, string>  $without
     * @return array<string, mixed>
     */
    public static function without(mixed $design, array $without): array
    {
        $design = is_array($design) ? $design : [];

        foreach ($without as $name) {
            [$group, $setting] = array_pad(explode('.', $name, 2), 2, null);

            if ($setting === null) {
                unset($design[$group]);
            } elseif (is_array($design[$group] ?? null)) {
                unset($design[$group][$setting]);
            }
        }

        return $design;
    }

    /**
     * A block's saved design (data.design) as classes and CSS properties.
     */
    public static function resolve(mixed $design, bool $hasPanel = false): DesignOutput
    {
        $output = new DesignOutput;
        $design = is_array($design) ? $design : [];

        foreach (self::groups() as $group) {
            $values = $design[$group::key()] ?? null;

            if (is_array($values) && $values !== []) {
                $output->merge($group::resolve($values, $hasPanel));
            }
        }

        return $output;
    }

    /**
     * The stylesheets of the Branding fonts that blocks (including those in a Section's columns) choose for themselves.
     * Worked out on every page view, so kept with the cached page HTML (RenderCache): finding them goes through every
     * block, and a block type with a preset builds its form to read its defaults.
     *
     * @param  array<int|string, mixed>  $blocks
     * @return array<int, string>
     */
    public static function fontStylesheets(array $blocks): array
    {
        return RenderCache::remember('fonts', $blocks, fn (): array => self::findFontStylesheets($blocks));
    }

    /**
     * @param  array<int|string, mixed>  $blocks
     * @return array<int, string>
     */
    private static function findFontStylesheets(array $blocks): array
    {
        $branding = app(Branding::class);
        $available = $branding->availableFonts();

        return array_values(array_map(
            fn (string $slug): string => $branding->fontStylesheetUrl($slug),
            array_filter(array_unique(self::fontsIn($blocks)), fn (string $slug): bool => isset($available[$slug])),
        ));
    }

    /**
     * @param  array<int|string, mixed>  $blocks
     * @return array<int, string>
     */
    private static function fontsIn(array $blocks): array
    {
        $fonts = [];

        foreach ($blocks as $block) {
            if (! is_array($block) || ! is_array($block['data'] ?? null)) {
                continue;
            }

            $blockClass = is_string($block['type'] ?? null) ? app(BlockRegistry::class)->find($block['type']) : null;
            // With its preset's settings, which can choose a font too.
            $data = $blockClass !== null ? app(BlockPresets::class)->apply($blockClass, $block['data']) : $block['data'];
            $fonts = [...$fonts, ...self::resolve($blockClass !== null ? self::without($data['design'] ?? null, $blockClass::designWithout()) : ($data['design'] ?? null))->fonts()];

            if (($block['type'] ?? null) === SectionBlock::NAME) {
                foreach (SectionBlock::rows($block['data']) as $row) {
                    foreach ($row['columns'] as $column) {
                        $fonts = [...$fonts, ...self::fontsIn($column['blocks'])];
                    }
                }
            }
        }

        return $fonts;
    }
}
