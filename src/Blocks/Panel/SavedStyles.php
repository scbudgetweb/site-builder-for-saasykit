<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Panel;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use ScBudgetWeb\SiteBuilder\Blocks\BlockPresets;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Blocks\StyleClipboard;

/**
 * A saved style picker at the top of a toggle (BlockPresets' saved styles): choose one of the group's saved styles, or
 * save this block's settings for the group as a new one, update or delete it. Saving moves the block's settings into
 * the style, so the block then follows it, as a preset does. Saved as the block's "style_presets.{group}".
 */
final class SavedStyles
{
    /** Group (StyleClipboard) => the toggle it shows in: [tab, toggle key]. */
    public const TOGGLES = [
        'items' => [PanelToggle::DESIGN, 'items'],
        'eyebrow' => [PanelToggle::DESIGN, 'eyebrow'],
        'heading' => [PanelToggle::DESIGN, 'heading_text'],
        'body' => [PanelToggle::DESIGN, 'body_text'],
        'links' => [PanelToggle::DESIGN, 'link_text'],
        'buttons' => [PanelToggle::DESIGN, 'button'],
        'background' => [PanelToggle::CONTENT, 'background'],
        'spacing' => [PanelToggle::DESIGN, 'spacing'],
        'borders' => [PanelToggle::DESIGN, 'border'],
        'effects' => [PanelToggle::DESIGN, 'filters'],
        'position' => [PanelToggle::DESIGN, 'transform'],
    ];

    /**
     * The panel's toggles with a saved style picker for each group whose toggle the panel has, put just before that
     * toggle's first part: so it comes first in it, takes its title, and the toggles keep their order.
     *
     * @param  array<int, PanelToggle|null>  $toggles
     * @return array<int, PanelToggle|null>
     */
    public static function withPickers(array $toggles): array
    {
        $groups = [];

        foreach (self::TOGGLES as $group => [$tab, $key]) {
            $groups["{$tab}.{$key}"] = $group;
        }

        $result = [];

        foreach ($toggles as $toggle) {
            $place = $toggle !== null && $toggle->fields !== [] ? $toggle->tab.'.'.$toggle->key : null;

            if ($place !== null && isset($groups[$place])) {
                $result[] = new PanelToggle($toggle->tab, $toggle->key, $toggle->title, [self::picker($groups[$place])], statePath: 'style_presets');
                unset($groups[$place]);
            }

            $result[] = $toggle;
        }

        return $result;
    }

    private static function picker(string $group): Grid
    {
        $presets = app(BlockPresets::class);
        $tops = array_values(array_unique(array_filter(
            array_map(fn (string $path): string => Str::before($path, '.'), StyleClipboard::settingPaths($group)),
            fn (string $top): bool => ! str_contains($top, '*'),
        )));
        // The block's settings for the group as they are in the panel now (the picker sits under style_presets).
        $current = function (Get $get) use ($tops): array {
            $data = [];

            foreach ($tops as $top) {
                $data[$top] = $get("../{$top}");
            }

            return array_filter($data, fn (mixed $value): bool => $value !== null);
        };
        // Clears the block's settings for the group, so it follows the style.
        $clear = function (Get $get, Set $set) use ($tops, $current, $group): void {
            $cleared = StyleClipboard::apply($current($get), [], StyleClipboard::settingPaths($group));

            foreach ($tops as $top) {
                $set("../{$top}", $cleared[$top] ?? null);
            }
        };
        $chosen = fn (Get $get): ?string => is_string($get($group)) && isset($presets->styleNames($group)[$get($group)]) ? $get($group) : null;

        return Grid::make(['default' => 1, 'sm' => 2])
            ->visible(fn (): bool => BlockPresets::canManage() || $presets->styleNames($group) !== [])
            ->extraAttributes(['data-sb-saved-style' => $group])
            ->schema([
                Select::make($group)
                    ->label(__('Saved style'))
                    ->hintIcon(PageBlock::HELP_ICON, __('A style for these settings, saved once and used on any block. It fills in every setting here this block leaves as it is, so changing the style changes every block using it.'))
                    ->options(fn (): array => $presets->styleNames($group))
                    ->placeholder(__('None'))
                    ->live(),
                Actions::make([
                    Action::make("saveStyle_{$group}")
                        ->label(__('Save as style'))
                        ->icon(Heroicon::OutlinedBookmark)
                        ->link()
                        ->size('sm')
                        ->visible(fn (): bool => BlockPresets::canManage())
                        ->modalHeading(__('Save these settings as a style'))
                        ->modalDescription(__('They move into the style, and this block uses it. Any block can then pick it.'))
                        ->modalWidth('md')
                        ->schema([
                            TextInput::make('name')
                                ->label(__('Name'))
                                ->placeholder(__('e.g. Stat grid with dividers'))
                                ->required()
                                ->maxLength(60),
                        ])
                        ->action(function (array $data, Get $get, Set $set) use ($presets, $group, $current, $clear): void {
                            $id = $presets->createStyle($group, (string) $data['name'], $current($get));
                            $clear($get, $set);
                            $set($group, $id);
                        }),
                    Action::make("updateStyle_{$group}")
                        ->label(__('Update style'))
                        ->tooltip(__('Save this block\'s changes into the style, for every block using it'))
                        ->icon(Heroicon::OutlinedArrowUpTray)
                        ->link()
                        ->size('sm')
                        ->visible(fn (Get $get): bool => BlockPresets::canManage() && $chosen($get) !== null)
                        ->action(function (Get $get, Set $set) use ($presets, $group, $current, $clear, $chosen): void {
                            $id = (string) $chosen($get);
                            $presets->updateStyle($group, $id, BlockPresets::fillBlanks($current($get), $presets->styleValues($group, $id)));
                            $clear($get, $set);
                        }),
                    Action::make("deleteStyle_{$group}")
                        ->label(__('Delete style'))
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->link()
                        ->size('sm')
                        ->visible(fn (Get $get): bool => BlockPresets::canManage() && $chosen($get) !== null)
                        ->requiresConfirmation()
                        ->modalHeading(__('Delete this style?'))
                        ->modalDescription(__('Every block using it loses these settings and goes back to its own. This can\'t be undone.'))
                        ->action(function (Get $get, Set $set) use ($presets, $group, $chosen): void {
                            $presets->deleteStyle($group, (string) $chosen($get));
                            $set($group, null);
                        }),
                ])
                    ->key("sb-saved-style-{$group}")
                    ->verticallyAlignEnd(),
            ]);
    }
}
