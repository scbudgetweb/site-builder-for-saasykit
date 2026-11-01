<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Schemas;

use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Blog\Blog;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Templates\HeaderBehaviour;
use ScBudgetWeb\SiteBuilder\Templates\Templates;

/**
 * A template: its name, what it is (header, footer, blog post…), where it applies (pages, or a blog type's categories)
 * and whether it's on, then (once created) its blocks. The blocks are usually built in the visual builder.
 */
class TemplateForm
{
    public const APPLIES_TO = [
        Template::EVERYWHERE => 'Every page',
        Template::ONLY => 'Only the pages chosen',
        Template::EXCEPT => 'Every page except those chosen',
    ];

    /** Where a Blog post template applies, by the post's category. */
    public const APPLIES_TO_POSTS = [
        Template::EVERYWHERE => 'Every post',
        Template::ONLY => 'Posts in the categories chosen',
        Template::EXCEPT => 'Every post except those in the categories chosen',
    ];

    public const APPLIES_TO_ARCHIVES = [
        Template::EVERYWHERE => 'The blog and every category',
        Template::ONLY => 'The categories chosen',
        Template::EXCEPT => 'The blog and every category except those chosen',
    ];

    /**
     * Where a template of this type can apply, translated.
     *
     * @return array<string, string>
     */
    public static function appliesToOptions(?string $type): array
    {
        return array_map(fn (string $label): string => __($label), match ($type) {
            Templates::BLOG_POST => self::APPLIES_TO_POSTS,
            Templates::BLOG_ARCHIVE => self::APPLIES_TO_ARCHIVES,
            default => self::APPLIES_TO,
        });
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Template'))
                    ->description(__('A header, footer or blog layout built from blocks, shown in place of the standard one wherever it applies. With no template, the standard one shows, styled in Branding and Navigation.'))
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
                                ->live()
                                ->disabledOn('edit')
                                ->hintIcon(PageBlock::HELP_ICON, __('Its blocks depend on what it is (a header can hold a menu, for example), so it can\'t change once made.')),
                            Select::make('applies_to')
                                ->label(__('Shown on'))
                                ->options(fn (Get $get): array => self::appliesToOptions($get('type')))
                                ->default(Template::EVERYWHERE)
                                ->selectablePlaceholder(false)
                                ->live()
                                ->hintIcon(PageBlock::HELP_ICON, __('Where more than one template could apply, one for chosen pages (or categories) wins, then the most recently saved.')),
                            Select::make('page_ids')
                                ->label(__('Pages'))
                                ->multiple()
                                ->searchable()
                                ->options(fn (): array => Page::query()->orderBy('title')->pluck('title', 'id')->all())
                                ->required(fn (Get $get): bool => self::choosesWhere($get, byCategory: false))
                                ->visible(fn (Get $get): bool => self::choosesWhere($get, byCategory: false)),
                            Select::make('category_ids')
                                ->label(__('Categories'))
                                ->multiple()
                                ->searchable()
                                ->options(fn (): array => app(Blog::class)->categories())
                                ->required(fn (Get $get): bool => self::choosesWhere($get, byCategory: true))
                                ->visible(fn (Get $get): bool => self::choosesWhere($get, byCategory: true)),
                            Toggle::make('is_active')
                                ->label(__('On'))
                                ->helperText(__('Off: it isn\'t used anywhere, so you can work on it first.'))
                                ->default(true),
                        ]),
                    ]),
                Section::make(__('Header behaviour'))
                    ->description(__('How this header behaves as the page scrolls. Also under Header settings in the visual builder, where you can try it on a sample page.'))
                    ->visible(fn (Get $get): bool => $get('type') === 'header')
                    ->schema([
                        Group::make(HeaderBehaviour::fields(forTemplate: true))
                            ->statePath('settings')
                            ->columns(['default' => 1, 'md' => 2]),
                    ]),
                app(BlockRegistry::class)->editor(Builder::make('blocks'))
                    ->label(__('Blocks'))
                    ->hintIcon(PageBlock::HELP_ICON, __('Easier in the visual builder. A Section with a row of columns lays out a logo, a menu and the account links side by side.'))
                    ->visibleOn('edit')
                    ->addActionLabel(__('Add block')),
            ]);
    }

    /**
     * Whether the form asks which pages (or, for a blog type, which categories) the template is for.
     */
    private static function choosesWhere(Get $get, bool $byCategory): bool
    {
        return $get('applies_to') !== Template::EVERYWHERE && Templates::isBlogType($get('type')) === $byCategory;
    }
}
