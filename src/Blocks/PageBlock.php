<?php

namespace ScBudgetWeb\SiteBuilder\Blocks;

use Filament\Actions\Action;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ScBudgetWeb\SiteBuilder\Branding\BrandingStyle;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Pages\PageService;
use ScBudgetWeb\SiteBuilder\Support\Colour;
use ScBudgetWeb\SiteBuilder\Support\IconLibrary;
use ScBudgetWeb\SiteBuilder\Support\SafeSvg;

/**
 * One page block: its admin form (a Filament Builder block) and its public view. Core blocks' views live in the
 * package (site-builder::blocks.{name-in-kebab-case}); an app's own blocks override view() with one of theirs and
 * are registered with SiteBuilder::blocks().
 */
abstract class PageBlock
{
    public const IMAGE_FILE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /** Block images may also be SVGs, checked on upload (SafeSvg), except where only a picture file works (a share image). */
    public const SVG_FILE_TYPE = 'image/svg+xml';

    /** The icon beside a setting whose tooltip explains it (->hintIcon(PageBlock::HELP_ICON, __('…'))). */
    public const HELP_ICON = Heroicon::OutlinedQuestionMarkCircle;

    public const IMAGE_MAX_KILOBYTES = 2048;

    /** A button's own colours instead of a Branding button style. */
    public const CUSTOM_BUTTON_STYLE = 'custom';

    /** Links a button may use: a full web address, a page on this site, a section of this page, email or phone. */
    public const LINK_PATTERN = '/^(https?:\/\/|\/|#|mailto:|tel:)/i';

    /** Where a block's buttons sit. Each block offers the positions that make sense for it. */
    public const BUTTON_POSITIONS = [
        'after_text' => 'Below the text',
        'before_text' => 'Above the text',
        'below_image' => 'Below the image',
        'above_image' => 'Above the image',
    ];

    /** Button width => [label, classes]. A minimum width lines up buttons with shorter text. */
    public const BUTTON_WIDTHS = [
        'auto' => ['Fit the text', ''],
        'medium' => ['At least medium (160px)', 'min-w-40'],
        'wide' => ['At least wide (224px)', 'min-w-56'],
        'xwide' => ['At least extra wide (288px)', 'min-w-72'],
        'full' => ['Full width', 'w-full'],
    ];

    /**
     * The stored block type, e.g. "image_text". Never rename one once pages use it.
     */
    abstract public static function name(): string;

    abstract public static function label(): string;

    abstract public static function icon(): string|Heroicon;

    /**
     * The Content tab.
     *
     * @return array<Component|Field>
     */
    abstract protected static function fields(): array;

    /**
     * Whether the block gets the shared Appearance settings (background, text, spacing, width, panel, anchor) and
     * the section wrapper they need. A block without them (Banner) draws its own full-width section.
     */
    public static function hasAppearance(): bool
    {
        return true;
    }

    /**
     * The heading settings that affect this block: "align" and "decoration" need the shared heading
     * (.section-heading), "size" any block heading (.section-heading or .block-heading). Blocks without a
     * heading return [], so the editor doesn't offer settings that do nothing.
     *
     * @return array<int, string>
     */
    public static function headingSettings(): array
    {
        return ['align', 'decoration', 'size'];
    }

    /**
     * Whether the block has body text (marked .block-text) for Style › Text size.
     */
    public static function hasBodyText(): bool
    {
        return true;
    }

    public static function contentTabLabel(): string
    {
        return __('Content');
    }

    /**
     * The block's own settings for the Style tab (above the shared ones).
     *
     * @return array<Component|Field>
     */
    protected static function styleFields(): array
    {
        return [];
    }

    /**
     * The block's own settings for the Layout tab (above the shared ones).
     *
     * @return array<Component|Field>
     */
    protected static function layoutFields(): array
    {
        return [];
    }

    /**
     * The block's own settings for the Advanced tab.
     *
     * @return array<Component|Field>
     */
    protected static function advancedFields(): array
    {
        return [];
    }

    public static function defaultWidth(): string
    {
        return 'contained';
    }

    public static function defaultBackground(): string
    {
        return 'none';
    }

    /**
     * The anchor used when the admin leaves Anchor ID empty, for blocks that built-in links point at.
     */
    public static function defaultAnchor(): ?string
    {
        return null;
    }

    /**
     * The Builder block, for the page's builder or (with $isNested) a Section's, where Appearance defaults to no
     * extra spacing and the full width of its column, because the section provides both.
     *
     * Its editor is split into tabs (Content, Layout, Style, Advanced), each with an icon; tabs only change the
     * editor, and every setting keeps its saved key (shared settings stay under "appearance"). The Advanced tab says
     * most blocks don't need it.
     */
    public static function make(bool $isNested = false): Block
    {
        $hasAppearance = static::hasAppearance();
        $advanced = [
            ...static::advancedFields(),
            ...($hasAppearance ? [BlockAppearance::advancedGroup()] : []),
        ];
        $icons = [
            static::contentTabLabel() => Heroicon::OutlinedPencilSquare,
            __('Layout') => Heroicon::OutlinedViewColumns,
            __('Style') => Heroicon::OutlinedSwatch,
            __('Advanced') => Heroicon::OutlinedCog6Tooth,
        ];

        $tabs = array_filter([
            static::contentTabLabel() => static::fields(),
            __('Layout') => [
                ...static::layoutFields(),
                ...($hasAppearance ? [BlockAppearance::layoutGroup($isNested ? 'full' : static::defaultWidth(), $isNested ? 'none' : 'medium', static::headingSettings())] : []),
            ],
            __('Style') => [
                ...static::styleFields(),
                ...($hasAppearance ? [BlockAppearance::styleGroup(static::defaultBackground(), static::headingSettings(), static::hasBodyText())] : []),
            ],
            __('Advanced') => $advanced === [] ? [] : [
                Text::make(__('Most blocks don\'t need these. They\'re for linking straight to this block, and for custom styling.')),
                ...$advanced,
            ],
        ]);

        return Block::make(static::name())
            ->label(fn (?array $state): string => $state === null ? static::label() : static::itemLabel($state))
            ->icon(static::icon())
            ->schema([
                // Not saved: only gives the admin preview card the block's name.
                Hidden::make('preview_label')
                    ->afterStateHydrated(fn (Hidden $component) => $component->state(static::label()))
                    ->dehydrated(false),
                ...(count($tabs) === 1 ? reset($tabs) : [
                    Tabs::make()
                        ->tabs(array_map(
                            fn (string $label, array $fields): Tab => Tab::make($label)->icon($icons[$label] ?? null)->schema($fields),
                            array_keys($tabs),
                            $tabs,
                        )),
                ]),
            ])
            ->preview('site-builder::filament.blocks.block-preview');
    }

    /**
     * The editor's title for a saved block, e.g. "Call to action: Get started today".
     *
     * @param  array<string, mixed>  $data
     */
    public static function editorTitle(array $data): string
    {
        return static::itemLabel($data);
    }

    public static function view(): string
    {
        return 'site-builder::blocks.'.str_replace('_', '-', static::name());
    }

    /**
     * Whether the block shows on the public page. A hidden block takes no space (no wrapper or spacing).
     *
     * @param  array<string, mixed>  $data
     */
    public static function isVisible(array $data): bool
    {
        return true;
    }

    /**
     * Whether the block's HTML can be cached with the page (RenderCache): true unless it shows live figures or depends
     * on the visitor (cookie consent, who's signed in, a form's CSRF token…). A page is cached only when all its
     * blocks are, so a host block that shows anything per visitor must return false.
     *
     * @param  array<string, mixed>  $data
     */
    public static function isCacheable(array $data): bool
    {
        return true;
    }

    /**
     * Extra data the public view needs (queries for live blocks, cleaned HTML, image URLs). Every saved value is
     * re-checked here or in the view (lesson 11).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function viewData(array $data, Page $page): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected static function itemLabel(array $state): string
    {
        $heading = $state['heading'] ?? null;

        return is_string($heading) && filled($heading) ? static::label().': '.Str::limit($heading, 60) : static::label();
    }

    /**
     * Optional heading and intro shown above the block's content.
     *
     * @return array<Field>
     */
    protected static function headingFields(bool $isRequired = false): array
    {
        return [
            TextInput::make('heading')
                ->label(__('Heading'))
                ->maxLength(150)
                ->required($isRequired)
                ->live(onBlur: true),
            Textarea::make('intro')
                ->label(__('Intro text'))
                ->rows(2)
                ->maxLength(500),
        ];
    }

    protected static function richText(string $name, string $label): RichEditor
    {
        return RichEditor::make($name)
            ->label($label)
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'link'],
                ['h2', 'h3', 'h4', 'paragraph'],
                ['bulletList', 'orderedList', 'blockquote', 'horizontalRule', 'table'],
                ['clearFormatting', 'undo', 'redo'],
            ]);
    }

    /**
     * A block image: JPG, PNG or WebP, and (with $allowsSvg) an SVG, which stays sharp at any size. An SVG is served
     * from the site's own address, so it's only accepted when it's a plain drawing, with no scripts or links (SafeSvg).
     */
    public static function imageUpload(string $name, string $label, bool $allowsSvg = true): FileUpload
    {
        $pages = app(PageService::class);

        return FileUpload::make($name)
            ->label($label)
            ->disk($pages->uploadDisk())
            ->directory($pages->uploadDirectory())
            ->visibility('public')
            ->image()
            ->acceptedFileTypes($allowsSvg ? [...self::IMAGE_FILE_TYPES, self::SVG_FILE_TYPE] : self::IMAGE_FILE_TYPES)
            ->rule(new SafeSvg)
            ->maxSize(self::IMAGE_MAX_KILOBYTES)
            ->imagePreviewHeight('120')
            // Filament only trusts existing paths from a model attribute, but block images live inside the blocks JSON,
            // and a block's panel stores its upload before the page is saved. So trust any file in the pages upload
            // folder, and nothing else on the disk (Branding files, other uploads…) (lesson 1).
            ->preventFilePathTampering(allowFilePathUsing: fn (string $file): bool => app(PageService::class)->isPageUpload($file));
    }

    /**
     * A searchable icon picker over the bundled Boxicons, showing each icon. Saves "bx:{style}/{name}".
     */
    public static function iconSelect(string $name = 'icon', ?string $label = null): Select
    {
        return Select::make($name)
            ->label($label ?? __('Icon'))
            ->searchable()
            ->allowHtml()
            ->options(fn (): array => IconLibrary::initialOptions())
            ->disableOptionWhen(fn (string $value): bool => $value === IconLibrary::SEARCH_HINT)
            ->getSearchResultsUsing(fn (string $search): array => IconLibrary::search($search))
            ->getOptionLabelUsing(fn (?string $value): ?string => IconLibrary::optionHtml($value))
            ->placeholder(__('No icon'))
            ->helperText(__('Type to search over 1,600 icons, e.g. "calendar", "map" or "trophy". Add "filled" or "logo" for those styles.'))
            ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                if (($value !== null && ! is_string($value)) || ! IconLibrary::isValid($value)) {
                    $fail(__('Choose an icon from the list.'));
                }
            });
    }

    /**
     * Image corners for this block: the site's Branding › Corners & shadows choice, or one of its sizes.
     */
    protected static function imageCornersSelect(): Select
    {
        return Select::make('image_corners')
            ->label(__('Image corners'))
            ->hintIcon(self::HELP_ICON, __('How round the image\'s corners are. Site style uses the one chosen in Branding.'))
            ->options(['site' => __('Site style (Branding)')] + BrandingStyle::labels(BrandingStyle::IMAGE_CORNER_OPTIONS))
            ->default('site')
            ->selectablePlaceholder(false);
    }

    /**
     * The Style tab's "Image" settings: corners, with border and shadow under More options.
     */
    protected static function imageStyleFieldset(?string $automaticShadowLabel = null): Fieldset
    {
        return Fieldset::make(__('Image'))
            ->columns(2)
            ->schema([
                static::imageCornersSelect()->columnSpanFull(),
                BlockAppearance::moreOptions(__('A border or shadow around the image.'), [
                    ...BorderShadow::borderFields('image_', withSides: false),
                    ...BorderShadow::shadowFields('image_', $automaticShadowLabel),
                ])->columnSpanFull(),
            ]);
    }

    /**
     * The inline style for a block image: its corners, border and shadow.
     *
     * @param  array<string, mixed>  $data
     * @param  string|null  $defaultShadow  the block's own image shadow, when its shadow is Automatic
     */
    public static function imageStyle(array $data, ?string $defaultShadow = null): string
    {
        return trim(static::imageCornerStyle($data).' '.BorderShadow::style($data, 'image_', $defaultShadow));
    }

    /**
     * The inline style for a block image's corners: empty for the site style, otherwise an override of the
     * --radius-sb-image token (only for a size from the fixed list).
     *
     * @param  array<string, mixed>  $data
     */
    public static function imageCornerStyle(array $data): string
    {
        $choice = $data['image_corners'] ?? 'site';

        return is_string($choice) && isset(BrandingStyle::IMAGE_CORNER_OPTIONS[$choice])
            ? '--radius-sb-image: '.BrandingStyle::IMAGE_CORNER_OPTIONS[$choice][1].';'
            : '';
    }

    /**
     * Up to $max buttons: text, link, style and width, with a shadow, an icon and whether it opens in a new tab under
     * More options.
     */
    protected static function buttonsRepeater(int $max = 2, string $name = 'buttons'): Repeater
    {
        return static::itemTooltips(Repeater::make($name), __('button'))
            ->label(__('Buttons'))
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('label')->label(__('Button text'))->required()->maxLength(60),
                    TextInput::make('url')
                        ->label(__('Link'))
                        ->required()
                        ->maxLength(500)
                        ->regex(self::LINK_PATTERN)
                        ->validationMessages(['regex' => __('Start the link with https://, / (a page on this site), # (a section on this page), mailto: or tel:.')])
                        ->placeholder('/about-us')
                        ->hintIcon(self::HELP_ICON, __('Where the button goes: a page on this site (/about-us), a part of this page (#pricing), a full web address (https://…), an email (mailto:…) or a phone number (tel:…).')),
                    Select::make('style')
                        ->label(__('Style'))
                        ->hintIcon(self::HELP_ICON, __('Automatic picks the button style that stands out best on the background. Custom colours are for this button only.'))
                        ->options(fn (): array => ['auto' => __('Automatic (suits the background)')] + app(BrandingStyle::class)->buttonNames() + [self::CUSTOM_BUTTON_STYLE => __('Custom colours')])
                        ->helperText(__('Button styles are set in Branding.'))
                        ->default('auto')
                        ->selectablePlaceholder(false)
                        ->live(),
                    Select::make('width')
                        ->label(__('Width'))
                        ->options(array_map(fn (array $width): string => __($width[0]), self::BUTTON_WIDTHS))
                        ->helperText(__('Give buttons side by side the same width to line them up.'))
                        ->default('auto')
                        ->selectablePlaceholder(false),
                    Grid::make(2)
                        ->visible(fn (Get $get): bool => $get('style') === self::CUSTOM_BUTTON_STYLE)
                        ->columnSpanFull()
                        ->schema([
                            self::buttonColorField('bg_color', __('Background colour'))->required(),
                            self::buttonColorField('text_color', __('Text colour'))->required(),
                            self::buttonColorField('hover_bg_color', __('Hover background colour'))->helperText(__('Blank: a little darker.')),
                            self::buttonColorField('hover_text_color', __('Hover text colour'))->helperText(__('Blank: the same text colour.')),
                            self::buttonColorField('border_color', __('Border colour (optional)'))->live(onBlur: true),
                            Select::make('border_style')
                                ->label(__('Border line'))
                                ->options(array_map(fn (string $label): string => __($label), CardStyle::BORDER_STYLES))
                                ->default('solid')
                                ->selectablePlaceholder(false)
                                ->visible(fn (Get $get): bool => filled($get('border_color'))),
                            TextInput::make('border_width')
                                ->label(__('Border width'))
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->maxValue(8)
                                ->suffix('px')
                                ->default(2)
                                ->visible(fn (Get $get): bool => filled($get('border_color'))),
                        ]),
                    BlockAppearance::moreOptions(__('An icon, a shadow, and opening in a new tab.'), [
                        static::iconSelect('icon', __('Icon (optional)'))
                            ->helperText(null)
                            ->columnSpanFull(),
                        Select::make('shadow')
                            ->label(__('Shadow'))
                            ->options(BorderShadow::shadowOptions(withCustom: false))
                            ->default('none')
                            ->selectablePlaceholder(false),
                        Toggle::make('new_tab')
                            ->label(__('Open in a new tab'))
                            ->hintIcon(self::HELP_ICON, __('Best for links to other websites. Links to this site usually open in the same tab.'))
                            ->inline(false),
                    ])->columnSpanFull(),
                ]),
            ])
            ->maxItems($max)
            ->defaultItems(0)
            ->collapsible()
            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
            ->addActionLabel(__('Add button'));
    }

    /**
     * Tooltips for a repeater's item buttons that name the item, e.g. "Duplicate this card" and "Delete this card".
     */
    public static function itemTooltips(Repeater $repeater, string $item): Repeater
    {
        $tooltip = fn (string $text): \Closure => fn (Action $action): Action => $action->tooltip(__($text, ['item' => $item]));

        return $repeater
            ->cloneAction($tooltip('Duplicate this :item'))
            ->deleteAction($tooltip('Delete this :item'))
            ->moveUpAction($tooltip('Move this :item up'))
            ->moveDownAction($tooltip('Move this :item down'))
            ->reorderAction($tooltip('Drag to move this :item'))
            ->collapseAction($tooltip('Collapse'))
            ->expandAction($tooltip('Expand'));
    }

    /**
     * A saved choice if it's one of the options (a hand-edited value can't reach the page), otherwise the default.
     *
     * @param  array<string, mixed>  $options
     */
    protected static function pick(mixed $value, array $options, string $default): string
    {
        // Selects hand numeric keys back as integers (lesson 2).
        return (is_string($value) || is_int($value)) && array_key_exists((string) $value, $options) ? (string) $value : $default;
    }

    /**
     * Columns on wide screens. Saved as an integer (lesson 2); read it back with columns().
     *
     * @param  array<int, int>  $choices
     */
    protected static function columnsSelect(int $default = 3, array $choices = [2, 3, 4]): Select
    {
        return Select::make('columns')
            ->label(__('Columns on wide screens'))
            ->hintIcon(self::HELP_ICON, __('How many sit side by side on a computer. Tablets show fewer, and phones show one under another.'))
            ->options(collect($choices)->mapWithKeys(fn (int $count): array => [$count => (string) $count])->all())
            ->default($default)
            ->selectablePlaceholder(false);
    }

    /**
     * The saved column count if it's one of the choices, otherwise the default.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $choices
     */
    public static function columns(array $data, int $default = 3, array $choices = [2, 3, 4]): int
    {
        $columns = $data['columns'] ?? null;

        return is_numeric($columns) && in_array((int) $columns, $choices, true) ? (int) $columns : $default;
    }

    /**
     * @param  array<int, string>  $positions  keys of BUTTON_POSITIONS, the first being the default
     */
    protected static function buttonPositionSelect(array $positions): Select
    {
        return Select::make('button_position')
            ->label(__('Buttons position'))
            ->options(array_map(fn (string $label): string => __($label), array_intersect_key(self::BUTTON_POSITIONS, array_flip($positions))))
            ->default($positions[0])
            ->selectablePlaceholder(false);
    }

    /**
     * The saved buttons position if this block offers it, otherwise its first (default) position.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $positions
     */
    public static function buttonPosition(array $data, array $positions): string
    {
        $position = $data['button_position'] ?? null;

        return is_string($position) && in_array($position, $positions, true) ? $position : $positions[0];
    }

    private static function buttonColorField(string $name, string $label): ColorPicker
    {
        return ColorPicker::make($name)
            ->label($label)
            ->regex('/^#[0-9a-fA-F]{6}$/')
            ->validationMessages(['regex' => __('Enter a 6-digit hex colour, for example #1d41d5.')]);
    }

    /**
     * The buttons worth showing: each with text and a link of an allowed kind, so a hand-edited value can't add
     * a javascript: link.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function validButtons(mixed $buttons): array
    {
        return array_values(array_filter(
            is_array($buttons) ? $buttons : [],
            fn (mixed $button): bool => is_array($button)
                && is_string($button['label'] ?? null) && filled($button['label'])
                && is_string($button['url'] ?? null) && preg_match(self::LINK_PATTERN, $button['url']) === 1,
        ));
    }

    /**
     * A block button's classes and inline style: its Branding style, or its own colours (set as the .site-button
     * variables, from validated hex colours only), plus its width.
     *
     * @param  array<string, mixed>  $button
     * @return array{class: string, style: string}
     */
    public static function buttonLook(array $button, bool $isDark): array
    {
        $color = fn (string $key): ?string => Colour::isValidHex($button[$key] ?? null) ? $button[$key] : null;
        $isCustom = ($button['style'] ?? null) === self::CUSTOM_BUTTON_STYLE && $color('bg_color') !== null && $color('text_color') !== null;
        $width = self::BUTTON_WIDTHS[static::pick($button['width'] ?? null, self::BUTTON_WIDTHS, 'auto')][1];

        $style = '';

        if ($isCustom) {
            $style = "--site-button-bg: {$color('bg_color')}; --site-button-text: {$color('text_color')};"
                .' --site-button-bg-hover: '.($color('hover_bg_color') ?? "color-mix(in oklab, {$color('bg_color')} 85%, black)").';'
                .($color('hover_text_color') ? " --site-button-text-hover: {$color('hover_text_color')};" : '')
                .self::customButtonBorder($button, $color('border_color'));
        }

        // Presets only (no custom shadow on buttons), through the .site-button variable so the border is kept.
        $shadow = $button['shadow'] ?? null;

        if (is_string($shadow) && isset(BorderShadow::SHADOWS[$shadow]) && ! in_array($shadow, ['none', 'custom'], true)) {
            $style .= ' --site-button-shadow: '.BorderShadow::SHADOWS[$shadow][1].';';
        }

        return [
            'class' => implode(' ', array_filter([
                'site-button',
                $isCustom ? '' : 'site-button-'.app(BrandingStyle::class)->buttonSlot(is_string($button['style'] ?? null) ? $button['style'] : null, $isDark),
                $width,
            ])),
            'style' => trim($style),
        ];
    }

    /**
     * A custom button's border: a solid line is the button's inset border (same size as other buttons); dashed,
     * dotted and double lines need a real border.
     *
     * @param  array<string, mixed>  $button
     */
    private static function customButtonBorder(array $button, ?string $color): string
    {
        if ($color === null) {
            return '';
        }

        $width = is_numeric($button['border_width'] ?? null) ? max(1, min(8, (int) $button['border_width'])) : 2;
        $line = static::pick($button['border_style'] ?? null, CardStyle::BORDER_STYLES, 'solid');

        return $line === 'solid'
            ? " --site-button-border: {$color}; --site-button-border-width: {$width}px;"
            : " border: {$width}px {$line} {$color};";
    }

    /**
     * Rich text from the admin editor, rendered and sanitised by Filament (Symfony's HTML sanitiser) for output
     * with {!! !!}. Rich text never reaches a page unsanitised.
     */
    public static function html(mixed $content): string
    {
        if (blank($content) || (! is_string($content) && ! is_array($content))) {
            return '';
        }

        return RichContentRenderer::make($content)->toHtml();
    }

    /**
     * The public URL of a block image, only for files in the pages upload folder.
     */
    public static function imageUrl(mixed $path): ?string
    {
        // A single-file upload may arrive as a one-item array.
        if (is_array($path)) {
            $path = array_values($path)[0] ?? null;
        }

        $pages = app(PageService::class);

        return $pages->isPageUpload($path) ? Storage::disk($pages->uploadDisk())->url($path) : null;
    }
}
