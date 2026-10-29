<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Panel;

use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;

/**
 * A settings panel: the Content, Design and Advanced tabs, each a list of collapsible toggles
 * (PanelToggle) in a fixed order. Only the first Content toggle starts open. A toggle with changed settings is marked
 * (data-sb-modified, for the panel's dot and "Show only modified") and gets a Reset button that puts its settings back
 * to their defaults.
 */
class SettingsPanel
{
    /**
     * Each level's colour along the top of its cards and panels in every editor and on its canvas outlines
     * (data-sb-level; design-fields-script and the visual builder's canvas), so it's clear which one you're in.
     */
    public const LEVEL_COLOURS = [
        'section' => '#2b87da',
        'row' => '#29c4a9',
        'column' => '#8f42ec',
        'block' => '#4c5866',
    ];

    /**
     * @param  array<int, PanelToggle|null>  $toggles
     * @param  array<string, string>  $tabLabels  tab => label, to rename a tab (a Section's Content tab is "Rows")
     * @return array<int, Component>
     */
    public static function schema(array $toggles, array $tabLabels = []): array
    {
        $tabs = [];

        foreach ([PanelToggle::CONTENT, PanelToggle::DESIGN, PanelToggle::ADVANCED] as $tab) {
            $sections = array_map(
                fn (array $group): Section => self::section($tab, $group),
                self::ordered(array_values(array_filter($toggles, fn (?PanelToggle $toggle): bool => $toggle !== null && $toggle->tab === $tab && $toggle->fields !== []))),
            );

            if ($sections === []) {
                continue;
            }

            $tabs[] = Tab::make($tabLabels[$tab] ?? self::tabLabel($tab))
                ->icon(self::tabIcon($tab))
                ->schema($tab === PanelToggle::ADVANCED
                    ? [Text::make(__('Most blocks don\'t need these. They\'re for linking straight to this block, and for custom styling.'))->extraAttributes(['data-sb-tab-note' => 'true']), ...$sections]
                    : $sections);
        }

        return $tabs === [] ? [] : [Tabs::make()->tabs($tabs)];
    }

    /**
     * The tab's toggles merged by key (several sources can add to one) and put in a fixed order: priority below 10,
     * then none in the order added, then by priority.
     *
     * @param  array<int, PanelToggle>  $toggles
     * @return array<int, array<int, PanelToggle>>
     */
    public static function ordered(array $toggles): array
    {
        $groups = [];

        foreach ($toggles as $index => $toggle) {
            $groups[$toggle->key] ??= ['index' => $index, 'toggles' => []];
            $groups[$toggle->key]['toggles'][] = $toggle;
        }

        $rank = function (array $group): array {
            $priority = collect($group['toggles'])->map(fn (PanelToggle $toggle): ?int => $toggle->priority)->filter(fn (?int $priority): bool => $priority !== null)->min();

            return match (true) {
                $priority !== null && $priority < 10 => [0, $priority, $group['index']],
                $priority === null => [1, 0, $group['index']],
                default => [2, $priority, $group['index']],
            };
        };

        uasort($groups, fn (array $a, array $b): int => $rank($a) <=> $rank($b));

        return array_values(array_map(fn (array $group): array => $group['toggles'], $groups));
    }

    /**
     * @param  array<int, PanelToggle>  $toggles  the parts of one toggle
     */
    private static function section(string $tab, array $toggles): Section
    {
        $first = $toggles[0];
        $isOpen = collect($toggles)->contains(fn (PanelToggle $toggle): bool => $toggle->isOpen);
        $schema = array_map(
            fn (PanelToggle $toggle): Component => Group::make()->statePath($toggle->statePath)->columnSpanFull()->schema($toggle->fields),
            $toggles,
        );

        return Section::make($first->title)
            // Not inherited, so the settings inside keep their own keys.
            ->key("sb-toggle-{$tab}-{$first->key}", isInheritable: false)
            ->collapsible()
            ->collapsed(! $isOpen)
            ->compact()
            ->extraAttributes(fn (Section $component): array => array_filter([
                'data-sb-toggle' => "{$tab}.{$first->key}",
                'data-sb-modified' => self::isModified($component) ? 'true' : null,
            ]))
            ->afterHeader([
                Action::make('reset')
                    ->label(__('Reset'))
                    ->tooltip(__('Put these settings back to their defaults'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->iconButton()
                    ->size('sm')
                    ->color('gray')
                    ->visible(fn (Section $component): bool => self::isModified($component))
                    ->action(fn (Section $component) => self::reset($component)),
            ])
            ->schema($schema);
    }

    /**
     * Whether any visible setting in the toggle differs from its default. A blank setting is unset, so it uses its default.
     */
    public static function isModified(Component $component): bool
    {
        foreach (self::fields($component) as $field) {
            $state = $field->getState();
            $default = $field->getDefaultState();

            if (blank($state)) {
                continue;
            }

            if ($state != $default) {
                return true;
            }
        }

        return false;
    }

    /**
     * Puts the toggle's settings back to their defaults.
     */
    public static function reset(Component $component): void
    {
        foreach (self::fields($component) as $field) {
            $field->state($field->getDefaultState());
            $field->callAfterStateUpdated();
        }
    }

    /**
     * @return array<int, Field>
     */
    private static function fields(Component $component): array
    {
        $schema = $component->getChildSchema();

        return $schema === null ? [] : array_values($schema->getFlatFields());
    }

    private static function tabLabel(string $tab): string
    {
        return match ($tab) {
            PanelToggle::CONTENT => __('Content'),
            PanelToggle::DESIGN => __('Design'),
            default => __('Advanced'),
        };
    }

    private static function tabIcon(string $tab): Heroicon
    {
        return match ($tab) {
            PanelToggle::CONTENT => Heroicon::OutlinedPencilSquare,
            PanelToggle::DESIGN => Heroicon::OutlinedSwatch,
            default => Heroicon::OutlinedCog6Tooth,
        };
    }
}
