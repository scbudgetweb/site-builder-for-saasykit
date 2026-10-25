<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Schemas;

use Filament\Forms\Components\Builder;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use ScBudgetWeb\SiteBuilder\Blocks\BlockLibrary;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\PageResource;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;

/**
 * A library block: its name and the block itself (any block, or a Section; the "Saved block" block hides itself
 * here, so the library never links to itself). Saving updates every page that links to it.
 */
class SavedBlockForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->helperText(__('How it shows in the library and the "Saved block" picker.'))
                    ->required()
                    ->maxLength(100),
                app(BlockRegistry::class)->editor(Builder::make('blocks'))
                    ->label(__('Block'))
                    ->minItems(1)
                    ->maxItems(1)
                    ->addActionLabel(__('Choose the block')),
                Section::make(__('Used on'))
                    ->description(__('Saving updates the block on all of these pages.'))
                    ->visibleOn('edit')
                    ->schema([
                        TextEntry::make('used_on')
                            ->hiddenLabel()
                            ->state(fn (SavedBlock $record): HtmlString|string => self::usedOn($record)),
                    ]),
            ]);
    }

    private static function usedOn(SavedBlock $record): HtmlString|string
    {
        $pages = app(BlockLibrary::class)->usedOn($record);

        if ($pages->isEmpty()) {
            return __('No pages use it yet. Add it to a page with the "Saved block (library)" block.');
        }

        return new HtmlString('<ul class="list-disc space-y-1 ps-5">'.$pages->map(fn (Page $page): string => '<li><a href="'.e(PageResource::getUrl('edit', ['record' => $page])).'" class="font-medium text-primary-600 underline">'.e($page->title).'</a>'
            .($page->trashed() ? ' '.e(__('(in the bin)')) : '').'</li>')->implode('').'</ul>');
    }
}
