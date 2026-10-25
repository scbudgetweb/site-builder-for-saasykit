<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Schemas;

use Closure;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Pages\PageService;
use ScBudgetWeb\SiteBuilder\Pages\PageStatus;
use ScBudgetWeb\SiteBuilder\Pages\Slot;
use ScBudgetWeb\SiteBuilder\SiteBuilder;

class PageForm
{
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make(__('Content'))
                        ->icon(Heroicon::OutlinedRectangleGroup)
                        ->schema([
                            app(BlockRegistry::class)->editor(Builder::make('blocks'))
                                ->hiddenLabel()
                                ->addActionLabel(__('Add block')),
                        ]),
                    Tab::make(__('Settings'))
                        ->icon(Heroicon::OutlinedCog6Tooth)
                        ->columns(2)
                        ->schema([
                            TextInput::make('title')
                                ->label(__('Title'))
                                ->required()
                                ->maxLength(150)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation): void {
                                    if ($operation === 'create' && blank($get('slug'))) {
                                        $set('slug', Str::slug((string) $state));
                                    }
                                }),
                            TextInput::make('slug')
                                ->label(__('URL'))
                                ->prefix(rtrim(url('/'), '/').'/')
                                ->required()
                                ->maxLength(100)
                                ->regex(self::SLUG_PATTERN)
                                ->unique(ignoreRecord: true)
                                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    if (in_array(Str::lower((string) $value), app(PageService::class)->reservedSlugs(), true)) {
                                        $fail(__('This URL is already used by another part of the site. Choose a different one.'));
                                    }
                                })
                                ->validationMessages([
                                    'regex' => __('Use lower-case letters, numbers and hyphens only, e.g. about-us.'),
                                    'unique' => __('Another page already uses this URL (it may be a deleted page: restore or rename it first).'),
                                ]),
                            Select::make('status')
                                ->label(__('Status'))
                                ->options(PageStatus::options())
                                ->default(PageStatus::Draft->value)
                                ->selectablePlaceholder(false)
                                ->required()
                                ->helperText(__('Only published pages are visible on the site. Use Preview to check a draft.')),
                            Select::make('replaces')
                                ->label(__('Replaces built-in page'))
                                ->options(fn (): array => self::slotOptions())
                                ->placeholder(__('No, it\'s a page of its own'))
                                ->unique(ignoreRecord: true)
                                ->validationMessages(['unique' => __('Another page already replaces this page.')])
                                ->live()
                                ->helperText(fn (Get $get): string => self::replacesHelperText($get))
                                ->visible(fn (): bool => self::slotOptions() !== []),
                            Toggle::make('show_page_banner')
                                ->label(__('Show the standard page banner with the page title'))
                                ->helperText(__('Not shown when the page starts with its own Banner block.'))
                                ->default(true)
                                ->columnSpanFull(),
                        ]),
                    Tab::make(__('SEO'))
                        ->icon(Heroicon::OutlinedMagnifyingGlass)
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('seo_title')
                                    ->label(__('Title for search engines'))
                                    ->helperText(__('Shown in search results and browser tabs. Leave blank to use the page title. Around 60 characters works best.'))
                                    ->maxLength(70),
                                PageBlock::imageUpload('share_image', __('Share image'))
                                    ->helperText(__('Shown when the page is shared on social media. 1200 × 630 pixels works best. Leave blank to use the one in Branding.')),
                            ]),
                            Textarea::make('seo_description')
                                ->label(__('Description for search engines'))
                                ->helperText(__('One or two sentences shown under the title in search results. Around 155 characters works best.'))
                                ->rows(3)
                                ->maxLength(300),
                            Toggle::make('noindex')
                                ->label(__('Hide from search engines'))
                                ->helperText(__('Asks search engines not to list this page, and leaves it out of the sitemap.')),
                        ]),
                ]),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function slotOptions(): array
    {
        return array_map(fn (Slot $slot): string => $slot->label(), app(SiteBuilder::class)->registeredSlots());
    }

    private static function replacesHelperText(Get $get): string
    {
        $slot = app(SiteBuilder::class)->slot(is_string($get('replaces')) ? $get('replaces') : null);

        if ($slot === null) {
            return __('Choose a built-in page to show this page in its place. The built-in page comes back if this page is unpublished or deleted.');
        }

        $requiredBlock = $slot->requiredBlock();

        if ($requiredBlock !== null && ! Page::blocksContain(is_array($get('blocks')) ? $get('blocks') : [], $requiredBlock)) {
            return __('Warning: :page needs the ":block" block to keep working. Add it on the Content tab.', [
                'page' => $slot->label(),
                'block' => app(BlockRegistry::class)->labelFor($requiredBlock),
            ]);
        }

        return __('Once published, this page shows at :url instead of the built-in page.', ['url' => $slot->url()]);
    }
}
