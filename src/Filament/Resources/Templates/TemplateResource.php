<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Templates;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\CreateTemplate;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\EditTemplate;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\ListTemplates;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages\TemplateVisualEditor;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Schemas\TemplateForm;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Tables\TemplatesTable;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\SiteBuilder;

/**
 * Admin › Website › Theme builder: headers and footers (and template types the app adds) built from blocks, used in
 * place of the theme's standard ones wherever they apply. Access follows TemplatePolicy (the page permissions).
 */
class TemplateResource extends Resource
{
    protected static ?string $model = Template::class;

    protected static ?string $slug = 'site-builder/theme-builder';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return TemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTemplates::route('/'),
            'create' => CreateTemplate::route('/create'),
            'edit' => EditTemplate::route('/{record}/edit'),
            'visual' => TemplateVisualEditor::route('/{record}/visual'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return app(SiteBuilder::class)->navigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return __('Theme builder');
    }

    public static function getModelLabel(): string
    {
        return __('template');
    }

    public static function getPluralModelLabel(): string
    {
        return __('templates');
    }
}
