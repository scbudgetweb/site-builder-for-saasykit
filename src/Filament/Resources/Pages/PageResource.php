<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Pages;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\CreatePage;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\EditPage;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\ListPages;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Schemas\PageForm;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Tables\PagesTable;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\SiteBuilder;

/**
 * Admin › Website › Pages: pages built from blocks. Access follows PagePolicy (config site-builder.permissions.pages).
 */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $slug = 'site-builder/pages';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return PageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
            'visual' => VisualEditor::route('/{record}/visual'),
        ];
    }

    /**
     * Deleted pages stay in the bin (the table's Deleted filter), so they can still be opened and restored.
     *
     * @return Builder<Page>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getNavigationGroup(): ?string
    {
        return app(SiteBuilder::class)->navigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return __('Pages');
    }

    public static function getModelLabel(): string
    {
        return __('page');
    }

    public static function getPluralModelLabel(): string
    {
        return __('pages');
    }
}
