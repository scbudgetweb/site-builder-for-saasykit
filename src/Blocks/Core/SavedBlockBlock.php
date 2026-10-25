<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use ScBudgetWeb\SiteBuilder\Blocks\BlockLibrary;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages\CreateSavedBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages\EditSavedBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\SavedBlockResource;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;

/**
 * A linked block from the block library: it shows the library item's block, so editing the item in Website › Block
 * library updates every page that uses it. Swapped for the item's block before rendering (BlockLibrary::expand()), so
 * it never renders itself. Not offered in the library's own editor (no loops).
 */
class SavedBlockBlock extends PageBlock
{
    public const NAME = 'saved';

    public static function name(): string
    {
        return self::NAME;
    }

    public static function label(): string
    {
        return __('Saved block (library)');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedBookmark;
    }

    /**
     * The library item's own settings apply.
     */
    public static function hasAppearance(): bool
    {
        return false;
    }

    public static function headingSettings(): array
    {
        return [];
    }

    /**
     * A link that wasn't swapped (its item is gone, or it was saved somewhere links can't go) shows nothing.
     */
    public static function isVisible(array $data): bool
    {
        return false;
    }

    /**
     * One field: which library item. In a Section's row, only items that can go in one are offered.
     */
    public static function make(bool $isNested = false): Block
    {
        return Block::make(static::name())
            ->label(fn (?array $state): string => $state === null ? static::label() : static::itemLabel($state))
            ->icon(static::icon())
            ->hidden(fn (mixed $livewire): bool => $livewire instanceof CreateSavedBlock || $livewire instanceof EditSavedBlock)
            ->schema([
                Hidden::make('preview_label')
                    ->afterStateHydrated(fn (Hidden $component) => $component->state(static::label()))
                    ->dehydrated(false),
                static::savedBlockSelect($isNested),
            ])
            ->preview('site-builder::filament.blocks.block-preview');
    }

    protected static function fields(): array
    {
        return [static::savedBlockSelect()];
    }

    protected static function savedBlockSelect(bool $isNested = false): Select
    {
        return Select::make('saved_block_id')
            ->label(__('Saved block'))
            ->options(fn (): array => SavedBlock::query()
                ->orderBy('name')
                ->get()
                ->filter(fn (SavedBlock $item): bool => ! $isNested || BlockLibrary::canGoInSection($item))
                ->mapWithKeys(fn (SavedBlock $item): array => [$item->getKey() => $item->name.' ('.app(BlockRegistry::class)->labelFor($item->type).')'])
                ->all())
            ->searchable()
            ->required()
            ->live()
            ->helperText(fn (Get $get): HtmlString|string => is_numeric($get('saved_block_id'))
                ? new HtmlString(e(__('Linked: changes to it in the block library show on every page that uses it.'))
                    .' <a href="'.e(SavedBlockResource::getUrl('edit', ['record' => (int) $get('saved_block_id')])).'" target="_blank" class="font-medium text-primary-600 underline">'.e(__('Edit it in the library')).'</a>')
                : __('Choose a block saved in the block library. To change it for this page only, use "Make an editable copy" on its card.'));
    }

    /**
     * "Saved block (library): Contact form".
     *
     * @param  array<string, mixed>  $state
     */
    protected static function itemLabel(array $state): string
    {
        $name = static::itemName($state['saved_block_id'] ?? null);

        return $name !== null ? static::label().': '.$name : static::label();
    }

    public static function itemName(mixed $id): ?string
    {
        $name = is_numeric($id) ? SavedBlock::query()->whereKey((int) $id)->value('name') : null;

        return is_string($name) ? $name : null;
    }
}
