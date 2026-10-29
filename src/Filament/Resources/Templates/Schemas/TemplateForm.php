<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Schemas;

use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Templates\Templates;

/**
 * A template: its name, what it is (header, footer…), where it applies and whether it's on, then (once created) its
 * blocks. The blocks are usually built in the visual builder.
 */
class TemplateForm
{
    public const APPLIES_TO = [
        Template::EVERYWHERE => 'Every page',
        Template::ONLY => 'Only the pages chosen',
        Template::EXCEPT => 'Every page except those chosen',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Template'))
                    ->description(__('A header or footer built from blocks, shown in place of the standard one wherever it applies. With no template, the standard one shows, styled in Branding and Navigation.'))
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            TextInput::make('name')
                                ->label(__('Name'))
                                ->helperText(__('Only shown here, e.g. "Main header" or "Footer for the shop pages".'))
                                ->required()
                                ->maxLength(100),
                            Select::make('type')
                                ->label(__('What it is'))
                                ->options(fn (): array => app(Templates::class)->types())
                                ->in(fn (): array => array_keys(app(Templates::class)->types()))
                                ->required()
                                ->selectablePlaceholder(false)
                                ->default('header')
                                ->disabledOn('edit')
                                ->hintIcon(PageBlock::HELP_ICON, __('Its blocks depend on what it is (a header can hold a menu, for example), so it can\'t change once made.')),
                            Select::make('applies_to')
                                ->label(__('Shown on'))
                                ->options(array_map(fn (string $label): string => __($label), self::APPLIES_TO))
                                ->default(Template::EVERYWHERE)
                                ->selectablePlaceholder(false)
                                ->live()
                                ->hintIcon(PageBlock::HELP_ICON, __('Where more than one template could apply, one for chosen pages wins, then the most recently saved.')),
                            Select::make('page_ids')
                                ->label(__('Pages'))
                                ->multiple()
                                ->searchable()
                                ->options(fn (): array => Page::query()->orderBy('title')->pluck('title', 'id')->all())
                                ->required(fn (Get $get): bool => $get('applies_to') !== Template::EVERYWHERE)
                                ->visible(fn (Get $get): bool => $get('applies_to') !== Template::EVERYWHERE),
                            Toggle::make('is_active')
                                ->label(__('On'))
                                ->helperText(__('Off: it isn\'t used anywhere, so you can work on it first.'))
                                ->default(true),
                        ]),
                    ]),
                app(BlockRegistry::class)->editor(Builder::make('blocks'))
                    ->label(__('Blocks'))
                    ->hintIcon(PageBlock::HELP_ICON, __('Easier in the visual builder. A Section with a row of columns lays out a logo, a menu and the account links side by side.'))
                    ->visibleOn('edit')
                    ->addActionLabel(__('Add block')),
            ]);
    }
}
