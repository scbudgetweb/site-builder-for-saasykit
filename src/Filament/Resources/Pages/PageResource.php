<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Pages;

use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
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

    protected static ?int $navigationSort = 2;

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
     * View page: the live page in a new tab, only for a published page that isn't in the bin. The pages list, Edit as
     * a form and the visual builder share it.
     */
    public static function viewPageAction(): Action
    {
        return Action::make('view')
            ->label(__('View page'))
            ->tooltip(__('Open the live page in a new tab'))
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->visible(fn (Page $record): bool => $record->isPublished() && ! $record->trashed())
            ->url(fn (Page $record): string => $record->url(), shouldOpenInNewTab: true);
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
