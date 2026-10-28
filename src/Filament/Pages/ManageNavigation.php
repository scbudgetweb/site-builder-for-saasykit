<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Pages;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page as SitePage;
use ScBudgetWeb\SiteBuilder\Navigation\NavigationMenus;
use ScBudgetWeb\SiteBuilder\SiteBuilder;

/**
 * Admin › Website › Navigation: the header links (with dropdowns and mega menus), the mobile menu and the footer.
 * Opens with the saved menus, or the theme's standard links when nothing is saved yet. For the people who build
 * pages (the "update pages" permission), since they decide where pages are linked from.
 *
 * @property-read Schema $form
 */
class ManageNavigation extends Page
{
    protected string $view = 'site-builder::filament.pages.manage-navigation';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'navigation';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Every page (drafts too, so a menu can be prepared before publishing), as id => [title, isPublished].
     *
     * @var array<int, array{title: string, isPublished: bool}>|null
     */
    private ?array $pages = null;

    public static function getNavigationGroup(): ?string
    {
        return app(SiteBuilder::class)->navigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return __('Navigation');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Navigation');
    }

    public function getSubheading(): ?string
    {
        return __('The links in the website header and footer. Add your own pages, built-in pages or any web address.');
    }

    public static function canAccess(): bool
    {
        $permission = config('site-builder.permissions.pages.update');

        return is_string($permission) && (bool) auth()->user()?->can($permission);
    }

    public function mount(NavigationMenus $menus): void
    {
        $this->fillForm($menus);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetToDefault')
                ->label(__('Reset to default'))
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->requiresConfirmation()
                ->modalIcon(Heroicon::OutlinedExclamationTriangle)
                ->modalIconColor('danger')
                ->modalHeading(__('Reset the menus to the standard ones?'))
                ->modalDescription(fn (NavigationMenus $menus): Htmlable => $this->resetDescription($menus))
                ->modalSubmitActionLabel(__('Replace my menus'))
                ->color('gray')
                ->action(function (NavigationMenus $menus): void {
                    $menus->reset();
                    $this->fillForm($menus);

                    Notification::make()->title(__('The menus are now this site\'s standard ones.'))->success()->send();
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Tabs::make()
                        ->persistTabInQueryString('navigation-tab')
                        ->tabs([
                            Tab::make(__('Header'))
                                ->icon(Heroicon::OutlinedBars3)
                                ->schema([$this->headerRepeater()]),
                            Tab::make(__('Mobile menu'))
                                ->icon(Heroicon::OutlinedDevicePhoneMobile)
                                ->schema($this->mobileFields()),
                            Tab::make(__('Footer'))
                                ->icon(Heroicon::OutlinedRectangleGroup)
                                ->schema($this->footerFields()),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label(__('Save changes'))
                                ->submit('save'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(NavigationMenus $menus): void
    {
        $data = $this->form->getState();

        $menus->save($data['header'] ?? [], $data['footer'] ?? [], $data['mobile'] ?? []);

        Notification::make()
            ->title(__('Navigation saved'))
            ->success()
            ->send();
    }

    /**
     * Exactly what Reset to default does: what each standard menu holds (the app's, or the package's), and that it
     * can't be undone.
     */
    private function resetDescription(NavigationMenus $menus): Htmlable
    {
        $labels = $menus->standardMenuLabels();
        $footer = $menus->standardFooter();
        $mobile = $menus->standardMobile();
        $list = fn (array $items): string => $items === [] ? e(__('no links')) : e(implode(', ', $items));

        $footerExtras = __('with :tagline and :copyright', [
            'tagline' => $footer['tagline'] !== '' ? __('the tagline ":tagline"', ['tagline' => $footer['tagline']]) : __('no tagline'),
            'copyright' => $footer['copyright'] !== null ? __('the copyright line ":line"', ['line' => $footer['copyright']]) : __('the standard copyright line'),
        ]);
        $mobileText = __('opens from :side, :logo, and :tagline', [
            'side' => $mobile['open_from'] === 'right' ? __('the right') : __('the top'),
            'logo' => $mobile['show_logo'] ? __('with the logo') : __('without the logo'),
            'tagline' => $mobile['tagline'] !== '' ? __('the tagline ":tagline"', ['tagline' => $mobile['tagline']]) : __('no tagline'),
        ]);

        return new HtmlString(
            '<p>'.e(__('This replaces your current header, footer and mobile menu with this site\'s standard ones:')).'</p>'
            .'<ul class="mt-2 list-disc ps-5 text-start">'
            .'<li><strong>'.e(__('Header')).':</strong> '.$list($labels['header']).'</li>'
            .'<li><strong>'.e(__('Footer')).':</strong> '.$list($labels['footer']).', '.e($footerExtras).'</li>'
            .'<li><strong>'.e(__('Mobile menu')).':</strong> '.e($mobileText).'</li>'
            .'</ul>'
            .'<p class="mt-2">'.e(__('Your own links, dropdowns and mega menus are removed. This can\'t be undone.')).'</p>'
        );
    }

    private function fillForm(NavigationMenus $menus): void
    {
        $this->form->fill([
            'header' => $menus->headerItems(),
            'footer' => $menus->footerSettings(),
            'mobile' => $menus->mobile(),
        ]);
    }

    private function headerRepeater(): Repeater
    {
        return self::withButtonTips(Repeater::make('header'), __('link'))
            ->hiddenLabel()
            ->helperText(__('The main links in the header, on desktop and in the mobile menu. Give a link "Dropdown links" to turn it into a dropdown, or choose a mega menu for a wide panel with columns. The Log in and account buttons are added automatically.'))
            ->schema([
                ...$this->linkFields(isHeaderItem: true),
                Select::make('menu')
                    ->label(__('Menu style'))
                    ->hintIcon(PageBlock::HELP_ICON, __('What opens when someone points at this link: a short list of links, or a wide panel with columns of links, descriptions and icons.'))
                    ->options([
                        NavigationMenus::MENU_DROPDOWN => __('Dropdown (a list under the link)'),
                        NavigationMenus::MENU_MEGA => __('Mega menu (a full-width panel with columns)'),
                    ])
                    ->default(NavigationMenus::MENU_DROPDOWN)
                    ->afterStateHydrated(fn (Select $component, ?string $state) => $component->state($state ?? NavigationMenus::MENU_DROPDOWN))
                    ->selectablePlaceholder(false)
                    ->live(),
                self::withButtonTips(Repeater::make('children'), __('link'))
                    ->label(__('Dropdown links'))
                    ->schema($this->linkFields(isHeaderItem: false))
                    ->visible(fn (Get $get): bool => $get('menu') !== NavigationMenus::MENU_MEGA)
                    ->minItems(fn (Get $get): int => $get('type') === NavigationMenus::TYPE_NONE ? 1 : 0)
                    ->maxItems(NavigationMenus::MAX_DROPDOWN_ITEMS)
                    ->defaultItems(0)
                    ->collapsible()
                    ->collapsed()
                    ->itemLabel(fn (array $state): string => $this->itemLabel($state))
                    ->addActionLabel(__('Add dropdown link')),
                ...$this->megaMenuFields(),
            ])
            ->maxItems(NavigationMenus::MAX_HEADER_ITEMS)
            ->defaultItems(0)
            ->reorderableWithButtons()
            ->collapsible()
            ->collapsed()
            ->itemLabel(fn (array $state): string => $this->itemLabel($state))
            ->addActionLabel(__('Add header link'));
    }

    /**
     * @return array<int, Select|Toggle|TextInput>
     */
    private function mobileFields(): array
    {
        return [
            Select::make('mobile.open_from')
                ->label(__('Opens from'))
                ->options(array_map(fn (string $label): string => __($label), NavigationMenus::MOBILE_OPEN_FROM))
                ->helperText(__('The menu (☰) on phones and tablets covers the screen and scrolls on its own. Dropdowns and mega menus open and close inside it.'))
                ->selectablePlaceholder(false),
            Toggle::make('mobile.show_logo')
                ->label(__('Show the logo at the top'))
                ->helperText(__('The header logo (Branding › Brand), or the site name when none is uploaded.'))
                ->live(),
            TextInput::make('mobile.tagline')
                ->label(__('Text under the logo (optional)'))
                ->placeholder(__('e.g. Book your local hall'))
                ->maxLength(60)
                ->visible(fn (Get $get): bool => (bool) $get('mobile.show_logo')),
        ];
    }

    /**
     * @return array<int, TextInput|Repeater>
     */
    private function footerFields(): array
    {
        return [
            TextInput::make('footer.tagline')
                ->label(__('Tagline'))
                ->helperText(__('Shown under the logo. Leave empty to hide it.'))
                ->maxLength(200),
            self::withButtonTips(Repeater::make('footer.columns'), __('column'))
                ->label(__('Link columns'))
                ->schema([
                    TextInput::make('heading')
                        ->label(__('Column heading (optional)'))
                        ->maxLength(60)
                        ->live(onBlur: true),
                    self::withButtonTips(Repeater::make('links'), __('link'))
                        ->label(__('Links'))
                        ->schema($this->linkFields(isHeaderItem: false))
                        ->minItems(1)
                        ->maxItems(NavigationMenus::MAX_FOOTER_LINKS)
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->collapsed()
                        ->itemLabel(fn (array $state): string => $this->itemLabel($state))
                        ->addActionLabel(__('Add link')),
                ])
                ->maxItems(NavigationMenus::MAX_FOOTER_COLUMNS)
                ->defaultItems(0)
                ->reorderableWithButtons()
                ->collapsible()
                ->itemLabel(fn (array $state): string => filled($state['heading'] ?? null) ? (string) $state['heading'] : __('Column'))
                ->addActionLabel(__('Add column')),
            TextInput::make('footer.copyright')
                ->label(__('Copyright line'))
                ->hintIcon(PageBlock::HELP_ICON, __('The small print at the very bottom of every page.'))
                ->placeholder(NavigationMenus::defaultCopyright())
                ->helperText(__('{year} becomes the current year and {site_name} the site name. Add a link like <a href="https://example.com">Example</a>; links to other sites open in a new tab. Leave empty for the standard line.'))
                ->maxLength(300)
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    foreach (NavigationMenus::linkAddresses(is_string($value) ? $value : '') as $address) {
                        if ($address === null || preg_match(NavigationMenus::URL_PATTERN, $address) !== 1) {
                            $fail(__('Each link needs an address starting with https://, / (a page on this site), #, mailto: or tel:, e.g. <a href="https://example.com">Example</a>.'));

                            return;
                        }
                    }
                }),
        ];
    }

    /**
     * One menu link: what it points at, its label and whether it opens in a new tab.
     *
     * @return array<int, Grid>
     */
    private function linkFields(bool $isHeaderItem): array
    {
        $types = [
            NavigationMenus::TYPE_PAGE => __('A page you built'),
            NavigationMenus::TYPE_BUILTIN => __('A built-in page'),
            NavigationMenus::TYPE_URL => __('A web address'),
        ];

        if ($isHeaderItem) {
            $types[NavigationMenus::TYPE_NONE] = __('Nothing (only opens its dropdown or mega menu)');
        }

        return [
            Grid::make(2)->schema([
                Select::make('type')
                    ->label(__('Link to'))
                    ->hintIcon(PageBlock::HELP_ICON, __('A page you built here, one of the site\'s own pages (such as Log in or Contact), or any web address, including another website.'))
                    ->options($types)
                    ->default(NavigationMenus::TYPE_BUILTIN)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live(),
                Select::make('page_id')
                    ->label(__('Page'))
                    ->options(fn (): array => array_map(
                        fn (array $page): string => $page['isPublished'] ? $page['title'] : __(':title (draft, hidden until published)', ['title' => $page['title']]),
                        $this->pages(),
                    ))
                    ->searchable()
                    ->required()
                    ->visible(fn (Get $get): bool => $get('type') === NavigationMenus::TYPE_PAGE),
                Select::make('link')
                    ->label(__('Built-in page'))
                    // Only links that apply to this site, plus this item's current one (marked as hidden) if it no longer does.
                    ->options(fn (NavigationMenus $menus, ?string $state): array => $menus->builtinOptions($state))
                    ->searchable()
                    ->required()
                    ->visible(fn (Get $get): bool => $get('type') === NavigationMenus::TYPE_BUILTIN),
                TextInput::make('url')
                    ->label(__('Web address'))
                    ->required()
                    ->maxLength(500)
                    ->regex(NavigationMenus::URL_PATTERN)
                    ->validationMessages(['regex' => __('Start the link with https://, / (a page on this site), # (a section on the page), mailto: or tel:.')])
                    ->placeholder('https://')
                    ->visible(fn (Get $get): bool => $get('type') === NavigationMenus::TYPE_URL),
                TextInput::make('label')
                    ->label(__('Label'))
                    ->maxLength(60)
                    ->required(fn (Get $get): bool => in_array($get('type'), [NavigationMenus::TYPE_URL, NavigationMenus::TYPE_NONE], true))
                    ->placeholder(fn (Get $get): ?string => match ($get('type')) {
                        NavigationMenus::TYPE_PAGE => __('Defaults to the page title'),
                        NavigationMenus::TYPE_BUILTIN => __('Defaults to the page name'),
                        default => null,
                    })
                    ->live(onBlur: true),
                Toggle::make('new_tab')
                    ->label(__('Open in a new tab'))
                    ->inline(false)
                    ->visible(fn (Get $get): bool => $get('type') !== NavigationMenus::TYPE_NONE),
            ]),
        ];
    }

    /**
     * A mega menu: up to four columns of links (each with an optional icon and description), side by side, and an
     * optional highlight panel on the right.
     *
     * @return array<int, Repeater|Section>
     */
    private function megaMenuFields(): array
    {
        $isMega = fn (Get $get): bool => $get('menu') === NavigationMenus::MENU_MEGA;

        return [
            self::withButtonTips(Repeater::make('columns'), __('column'))
                ->label(__('Mega menu columns'))
                ->schema([
                    TextInput::make('heading')
                        ->label(__('Column heading (optional)'))
                        ->maxLength(60)
                        ->live(onBlur: true),
                    self::withButtonTips(Repeater::make('links'), __('link'))
                        ->label(__('Links'))
                        ->schema([
                            ...$this->linkFields(isHeaderItem: false),
                            Grid::make(2)->schema([
                                TextInput::make('description')
                                    ->label(__('Short description (optional)'))
                                    ->helperText(__('A line under the link, e.g. "Rooms for parties and meetings".'))
                                    ->maxLength(120),
                                PageBlock::iconSelect('icon', __('Icon (optional)'))->helperText(null),
                            ]),
                        ])
                        ->minItems(1)
                        ->maxItems(NavigationMenus::MAX_MEGA_LINKS)
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->collapsed()
                        ->itemLabel(fn (array $state): string => $this->itemLabel($state))
                        ->addActionLabel(__('Add link')),
                ])
                ->visible($isMega)
                ->minItems(1)
                ->maxItems(NavigationMenus::MAX_MEGA_COLUMNS)
                ->defaultItems(1)
                ->reorderableWithButtons()
                ->collapsible()
                ->itemLabel(fn (array $state): string => filled($state['heading'] ?? null) ? (string) $state['heading'] : __('Column'))
                ->addActionLabel(__('Add column')),
            // A highlight panel is an extra, so it starts folded away.
            Section::make(__('Highlight panel (optional)'))
                ->description(__('A box beside the columns to promote one thing, with a heading, a line of text and a button.'))
                ->collapsible()
                ->collapsed()
                ->compact()
                ->statePath('promo')
                ->visible($isMega)
                ->columns(2)
                ->schema([
                    TextInput::make('heading')->label(__('Heading'))->maxLength(80),
                    TextInput::make('button_label')->label(__('Button text'))->maxLength(40),
                    Textarea::make('text')->label(__('Text'))->rows(2)->maxLength(250),
                    TextInput::make('button_url')
                        ->label(__('Button link'))
                        ->maxLength(500)
                        ->regex(NavigationMenus::URL_PATTERN)
                        ->validationMessages(['regex' => __('Start the link with https://, / (a page on this site), # (a section on the page), mailto: or tel:.')])
                        ->requiredWith('button_label')
                        ->placeholder('/contact'),
                ]),
        ];
    }

    /**
     * Tooltips on a menu list's icon buttons, e.g. "Move this link up", "Delete this column".
     */
    private static function withButtonTips(Repeater $repeater, string $thing): Repeater
    {
        return $repeater
            ->moveUpAction(fn (Action $action): Action => $action->tooltip(__('Move this :thing up', ['thing' => $thing])))
            ->moveDownAction(fn (Action $action): Action => $action->tooltip(__('Move this :thing down', ['thing' => $thing])))
            ->reorderAction(fn (Action $action): Action => $action->tooltip(__('Drag to move this :thing', ['thing' => $thing])))
            ->deleteAction(fn (Action $action): Action => $action->tooltip(__('Delete this :thing', ['thing' => $thing])))
            ->collapseAction(fn (Action $action): Action => $action->tooltip(__('Collapse')))
            ->expandAction(fn (Action $action): Action => $action->tooltip(__('Expand')));
    }

    /**
     * The collapsed item's title: its label, or the name it will show with when the label is empty.
     *
     * @param  array<string, mixed>  $state
     */
    private function itemLabel(array $state): string
    {
        $page = is_numeric($state['page_id'] ?? null) ? ($this->pages()[(int) $state['page_id']] ?? null) : null;

        $name = filled($state['label'] ?? null) ? (string) $state['label'] : match ($state['type'] ?? null) {
            NavigationMenus::TYPE_PAGE => $page['title'] ?? __('Page'),
            NavigationMenus::TYPE_BUILTIN => app(SiteBuilder::class)->link(is_string($state['link'] ?? null) ? $state['link'] : null)?->label() ?? __('Built-in page'),
            NavigationMenus::TYPE_URL => (string) ($state['url'] ?? '') ?: __('Web address'),
            default => __('Dropdown'),
        };

        if (($state['type'] ?? null) === NavigationMenus::TYPE_PAGE && $page !== null && ! $page['isPublished']) {
            return __(':name (draft, hidden until published)', ['name' => $name]);
        }

        return $name;
    }

    /**
     * @return array<int, array{title: string, isPublished: bool}>
     */
    private function pages(): array
    {
        // Drafts can be linked before they're published, but only people who can see the page list see their titles.
        return $this->pages ??= SitePage::query()
            ->when(! (bool) auth()->user()?->can('viewAny', SitePage::class), fn ($query) => $query->published())
            ->orderBy('title')
            ->get(['id', 'title', 'status'])
            ->mapWithKeys(fn (SitePage $page): array => [$page->id => ['title' => $page->title, 'isPublished' => $page->isPublished()]])
            ->all();
    }
}
