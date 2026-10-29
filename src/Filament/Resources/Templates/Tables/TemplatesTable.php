<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Tables;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Schemas\TemplateForm;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\TemplateResource;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Templates\Templates;

class TemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->recordUrl(fn (Template $record): ?string => TemplateResource::canEdit($record) ? TemplateResource::getUrl('visual', ['record' => $record]) : null)
            ->emptyStateHeading(__('No templates yet'))
            ->emptyStateDescription(__('The standard header and footer show, styled in Branding and Navigation. Make a template to build your own from blocks.'))
            ->emptyStateIcon(Heroicon::OutlinedRectangleGroup)
            ->emptyStateActions([CreateAction::make()->label(__('New template'))])
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('What it is'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => app(Templates::class)->types()[$state] ?? $state),
                TextColumn::make('applies_to')
                    ->label(__('Shown on'))
                    ->formatStateUsing(fn (Template $record): string => match ($record->applies_to) {
                        Template::ONLY, Template::EXCEPT => __(TemplateForm::APPLIES_TO[$record->applies_to]).' ('.count((array) $record->page_ids).')',
                        default => __(TemplateForm::APPLIES_TO[Template::EVERYWHERE]),
                    }),
                ToggleColumn::make('is_active')
                    ->label(__('On'))
                    ->tooltip(__('Off: it isn\'t used anywhere')),
                TextColumn::make('updated_at')
                    ->label(__('Last updated'))
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('visual')
                    ->label(__('Visual builder'))
                    ->tooltip(__('Build it from blocks, as it looks'))
                    ->icon(Heroicon::OutlinedPaintBrush)
                    ->url(fn (Template $record): string => TemplateResource::getUrl('visual', ['record' => $record])),
                EditAction::make()
                    ->label(__('Edit as a form'))
                    ->tooltip(__('Its name, where it\'s shown, and its blocks as a list')),
                DeleteAction::make(),
            ]);
    }
}
