<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Tables;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use ScBudgetWeb\SiteBuilder\Blocks\BlockLibrary;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages\EditSavedBlock;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;

class SavedBlocksTable
{
    public static function configure(Table $table): Table
    {
        $registry = app(BlockRegistry::class);

        return $table
            ->defaultSort('name')
            ->emptyStateHeading(__('No library blocks yet'))
            ->emptyStateDescription(__('Save a block or section from a page with the bookmark button on its card, or create one here. Then reuse it on any page.'))
            ->emptyStateIcon(Heroicon::OutlinedBookmark)
            ->emptyStateActions([CreateAction::make()->label(__('New library block'))])
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('Block'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $registry->labelFor($state)),
                TextColumn::make('used_on')
                    ->label(__('Used on'))
                    ->tooltip(__('Pages that link to this block. They all change when you save it.'))
                    ->state(function (SavedBlock $record): string {
                        $count = app(BlockLibrary::class)->usedOn($record)->count();

                        return $count === 0 ? __('Not used yet') : trans_choice(':count page|:count pages', $count, ['count' => $count]);
                    }),
                TextColumn::make('updated_at')
                    ->label(__('Last updated'))
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('Block'))
                    ->options(fn (): array => SavedBlock::query()->distinct()->orderBy('type')->pluck('type')
                        ->mapWithKeys(fn (string $type): array => [$type => $registry->labelFor($type)])
                        ->all()),
            ])
            ->recordActions([
                EditAction::make(),
                EditSavedBlock::refuseDeleteWhileInUse(DeleteAction::make()),
            ]);
    }
}
