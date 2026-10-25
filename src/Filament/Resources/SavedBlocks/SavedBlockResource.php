<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages\CreateSavedBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages\EditSavedBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages\ListSavedBlocks;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Schemas\SavedBlockForm;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Tables\SavedBlocksTable;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;
use ScBudgetWeb\SiteBuilder\SiteBuilder;

/**
 * Admin › Website › Block library: blocks and Sections saved for reuse, linked from pages by "Saved block" blocks.
 * Access follows SavedBlockPolicy (the page permissions).
 */
class SavedBlockResource extends Resource
{
    protected static ?string $model = SavedBlock::class;

    protected static ?string $slug = 'site-builder/block-library';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return SavedBlockForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SavedBlocksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSavedBlocks::route('/'),
            'create' => CreateSavedBlock::route('/create'),
            'edit' => EditSavedBlock::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return app(SiteBuilder::class)->navigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return __('Block library');
    }

    public static function getModelLabel(): string
    {
        return __('library block');
    }

    public static function getPluralModelLabel(): string
    {
        return __('library blocks');
    }
}
