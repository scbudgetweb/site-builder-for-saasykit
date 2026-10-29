<?php

namespace ScBudgetWeb\SiteBuilder\Blocks;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
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
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\PanelToggle;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\SettingsPanel;
use ScBudgetWeb\SiteBuilder\Branding\BrandingStyle;
use ScBudgetWeb\SiteBuilder\Filament\Forms\ButtonsRepeater;
use ScBudgetWeb\SiteBuilder\Filament\Forms\HeadingInput;
use ScBudgetWeb\SiteBuilder\Filament\Forms\SiteColour;
use ScBudgetWeb\SiteBuilder\Filament\Forms\SiteRichEditor;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Pages\PageService;
use ScBudgetWeb\SiteBuilder\Support\Colour;
use ScBudgetWeb\SiteBuilder\Support\IconLibrary;
use ScBudgetWeb\SiteBuilder\Support\SafeSvg;
use Throwable;

/**
 * One page block: its admin form (a Filament Builder block) and its public view. Core blocks' views live in the
 * package (site-builder::blocks.{name-in-kebab-case}); an app's own blocks override view() with one of theirs and
 * are registered with SiteBuilder::blocks().
 */
abstract class PageBlock
{
    public const IMAGE_FILE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];

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
     * Images uploaded in an open panel but not saved yet (key => their temporary preview URL), shown by imageUrl() while
     * the visual builder renders a live preview (previewingImages()).
     *
     * @var array<string, string>
     */
    private static array $previewImages = [];

    /**
     * Renders with images that are uploaded but not yet saved: imageUrl() gives each key its preview URL, for this
     * render only.
     *
     * @template T
     *
     * @param  array<string, string>  $urls  key => preview URL
     * @param  Closure(): T  $render
     * @return T
     */
    public static function previewingImages(array $urls, Closure $render): mixed
    {
        $before = self::$previewImages;
        self::$previewImages = $urls + $before;

        try {
            return $render();
        } finally {
            self::$previewImages = $before;
        }
    }

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
     * (.section-heading), "size" any block heading (.section-heading or .block-heading), so the editor doesn't offer
     * settings that do nothing.
     *
     * Found from the block's fields (uses()): all three with the shared heading (headingFields()), none without. A block
     * that draws a heading of its own overrides this, e.g. ['size'] for a .block-heading.
     *
     * @return array<int, string>
     */
    public static function headingSettings(): array
    {
        return static::uses(HeadingInput::class) ? ['align', 'decoration', 'size'] : [];
    }

    /**
     * Whether the block has body text (marked .block-text) for Style › Text size. Found from its fields: the shared
     * heading's intro or rich text (richText()). A block that draws body text of its own overrides this.
     */
    public static function hasBodyText(): bool
    {
        return static::uses(HeadingInput::class) || static::uses(SiteRichEditor::class);
    }

    /**
     * Whether the block's settings hold a field of this class anywhere (in its own toggles, groups and lists, but not in
     * blocks nested in a Builder): how a block's panel finds what it needs without the block declaring it
     * (ButtonsRepeater, HeadingInput, SiteRichEditor). Worked out once per type.
     *
     * @param  class-string<Component>  $fieldClass
     */
    public static function uses(string $fieldClass): bool
    {
        static $cache = [];

        $classes = $cache[static::class] ??= (function (): array {
            $classes = [];
            $walk = function (array $components) use (&$walk, &$classes): void {
                foreach ($components as $component) {
                    if (! $component instanceof Component) {
                        continue;
                    }

                    $classes[$component::class] = true;

                    // A Section's Builder holds other blocks: theirs are their own.
                    if ($component instanceof Builder) {
                        continue;
                    }

                    $children = $component->getDefaultChildComponents();
                    $walk(is_array($children) ? $children : []);
                }
            };

            // Its content, and its own toggles (Layout, Style and Advanced settings among them).
            $walk(static::fields());

            foreach (static::blockToggles() as $toggle) {
                $walk($toggle?->fields ?? []);
            }

            return array_keys($classes);
        })();

        foreach ($classes as $class) {
            if (is_a($class, $fieldClass, true)) {
                return true;
            }
        }

        return false;
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
        return Block::make(static::name())
            ->label(fn (?array $state): string => $state === null ? static::label() : static::itemLabel($state))
            ->icon(static::icon())
            // Its level's colour along the top of its card (SettingsPanel::LEVEL_COLOURS).
            ->extraAttributes(['data-sb-level' => static::structureLevel()])
            // Built only when a block of this type is shown or edited: a page's Builder, and each Section column's,
            // offers every block type, and building every form up front costs a lot of memory on each request.
            ->schema(fn (): array => static::editorSchema($isNested))
            ->preview('site-builder::filament.blocks.block-preview');
    }

    /**
     * The block's editor (SettingsPanel): the Content, Design and Advanced tabs, each a list of
     * toggles. In a Section ($isNested), Appearance defaults to no extra spacing and the full width of its column.
     *
     * @return array<Component|Field>
     */
    protected static function editorSchema(bool $isNested): array
    {
        $toggles = array_values(array_filter(static::toggles($isNested), fn (?PanelToggle $toggle): bool => $toggle !== null && $toggle->fields !== []));
        $isContentOnly = count($toggles) === 1 && $toggles[0]->key === 'main';

        return [
            // Not saved: only gives the admin preview card the block's name.
            Hidden::make('preview_label')
                ->afterStateHydrated(fn (Hidden $component) => $component->state(static::label()))
                ->dehydrated(false),
            ...($isContentOnly ? $toggles[0]->fields : [
                View::make('site-builder::filament.blocks.panel-tools'),
                ...(static::hasAppearance() ? static::presetFields($isNested) : []),
                ...SettingsPanel::schema($toggles, [PanelToggle::CONTENT => static::contentTabLabel()]),
            ]),
        ];
    }

    /**
     * Presets, at the top of the panel (BlockPresets): pick one of the saved styles for this kind of block, or
     * (with the presets permission) save this block's style as a new one, update one from it, make one the default or
     * delete one. Saving or updating moves the block's style into the preset, so the block then follows it.
     *
     * @return array<int, Component>
     */
    protected static function presetFields(bool $isNested): array
    {
        $presets = app(BlockPresets::class);
        $type = static::name();
        $paths = fn (): array => StyleClipboard::paths('all', static::class);
        $tops = fn (): array => array_values(array_unique(array_filter(array_map(fn (string $path): string => Str::before($path, '.'), $paths()), fn (string $top): bool => ! str_contains($top, '*'))));
        // The block's style settings as they are in the panel now.
        $style = function (Get $get) use ($paths, $tops): array {
            $data = [];

            foreach ($tops() as $top) {
                $data[$top] = $get($top);
            }

            return StyleClipboard::extract(array_filter($data, fn (mixed $value): bool => $value !== null), $paths());
        };
        // Clears the block's style settings, so it follows its preset.
        $clearStyle = function (Get $get, Set $set) use ($paths, $tops): void {
            $data = [];

            foreach ($tops() as $top) {
                $data[$top] = $get($top);
            }

            $cleared = StyleClipboard::apply(array_filter($data, fn (mixed $value): bool => $value !== null), [], $paths());

            foreach ($tops() as $top) {
                $set($top, $cleared[$top] ?? null);
            }
        };
        $chosen = fn (Get $get): ?string => match (true) {
            $get('preset') === BlockPresets::NONE => null,
            is_string($get('preset')) && isset($presets->names($type)[$get('preset')]) => $get('preset'),
            default => $presets->defaultId($type),
        };

        return [
            Grid::make(['default' => 1, 'sm' => 2])
                ->visible(fn (): bool => BlockPresets::canManage() || $presets->names($type) !== [])
                ->schema([
                    Select::make('preset')
                        ->label(__('Preset'))
                        ->hintIcon(self::HELP_ICON, __('A saved style for this kind of block. The preset fills in every style setting this block leaves as it is, so changing the preset changes every block using it.'))
                        ->options(fn (): array => [
                            BlockPresets::NONE => __('No preset'),
                            ...$presets->names($type),
                        ])
                        ->placeholder(fn (): string => ($default = $presets->defaultId($type)) !== null
                            ? __('Default preset (:name)', ['name' => $presets->names($type)[$default]])
                            : __('Default preset (none yet)'))
                        ->live(),
                    Actions::make([
                        Action::make('savePreset')
                            ->label(__('Save as preset'))
                            ->icon(Heroicon::OutlinedBookmark)
                            ->link()
                            ->size('sm')
                            ->visible(fn (): bool => BlockPresets::canManage())
                            ->modalHeading(__('Save this block\'s style as a preset'))
                            ->modalDescription(__('Its style settings move into the preset, and the block uses it. Content isn\'t saved.'))
                            ->modalWidth('md')
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->required()
                                    ->maxLength(60),
                                Toggle::make('is_default')
                                    ->label(__('Make it the default'))
                                    ->helperText(__('Used by every block of this kind that hasn\'t picked another preset.')),
                            ])
                            ->action(function (array $data, Get $get, Set $set) use ($presets, $type, $style, $clearStyle): void {
                                $id = $presets->create($type, (string) $data['name'], $style($get), (bool) ($data['is_default'] ?? false));
                                $clearStyle($get, $set);
                                $set('preset', ($data['is_default'] ?? false) ? null : $id);
                            }),
                        Action::make('updatePreset')
                            ->label(__('Update preset'))
                            ->tooltip(__('Save this block\'s style changes into its preset, for every block using it'))
                            ->icon(Heroicon::OutlinedArrowUpTray)
                            ->link()
                            ->size('sm')
                            ->visible(fn (Get $get): bool => BlockPresets::canManage() && $chosen($get) !== null)
                            ->action(function (Get $get, Set $set) use ($presets, $type, $style, $clearStyle, $chosen, $isNested): void {
                                $id = (string) $chosen($get);
                                $presets->update($type, $id, BlockPresets::fillBlanks($style($get), $presets->valuesFor($type, ['preset' => $id]), static::fieldDefaults($isNested)));
                                $clearStyle($get, $set);
                            }),
                        Action::make('makeDefaultPreset')
                            ->label(__('Make default'))
                            ->icon(Heroicon::OutlinedStar)
                            ->link()
                            ->size('sm')
                            ->visible(fn (Get $get): bool => BlockPresets::canManage() && is_string($get('preset')) && isset($presets->names($type)[$get('preset')]) && $presets->defaultId($type) !== $get('preset'))
                            ->action(function (Get $get, Set $set) use ($presets, $type): void {
                                $presets->makeDefault($type, (string) $get('preset'));
                                $set('preset', null);
                            }),
                        Action::make('deletePreset')
                            ->label(__('Delete preset'))
                            ->icon(Heroicon::OutlinedTrash)
                            ->color('danger')
                            ->link()
                            ->size('sm')
                            ->visible(fn (Get $get): bool => BlockPresets::canManage() && $chosen($get) !== null)
                            ->requiresConfirmation()
                            ->modalHeading(__('Delete this preset?'))
                            ->modalDescription(__('Every block using it loses its style and goes back to the default preset (or none). This can\'t be undone.'))
                            ->action(function (Get $get, Set $set) use ($presets, $type, $chosen): void {
                                $presets->delete($type, (string) $chosen($get));
                                $set('preset', null);
                            }),
                    ])->verticallyAlignEnd(),
                ]),
        ];
    }

    /**
     * Every toggle in the block's panel: its content (open), its own settings (blockToggles()), its buttons' look, and
     * the shared settings (Appearance, the design options and an admin label).
     *
     * @return array<int, PanelToggle|null>
     */
    protected static function toggles(bool $isNested = false): array
    {
        return [
            PanelToggle::content('main', static::label(), static::fields(), isOpen: true),
            ...static::blockToggles(),
            static::hasButtons() ? PanelToggle::design('button', __('Buttons'), [static::buttonDesignFields()], 70) : null,
            ...(static::hasAppearance() ? [
                ...BlockAppearance::toggles($isNested ? 'full' : static::defaultWidth(), $isNested ? 'none' : 'medium', static::defaultBackground(), static::headingSettings(), static::hasBodyText()),
                ...BlockDesign::toggles(static::designWithout()),
            ] : []),
            PanelToggle::content('admin_label', __('Admin label'), [static::adminLabelField()], 99),
            BlockConditions::toggle(),
        ];
    }

    /**
     * The block's own settings beyond its content: Layout (layoutFields()) first on the Design tab, then its look
     * (styleFields(), titled styleToggleTitle()), and advancedFields() under CSS ID & classes. A block can return its
     * own toggles instead, to name or split them.
     *
     * @return array<int, PanelToggle|null>
     */
    protected static function blockToggles(): array
    {
        return [
            PanelToggle::design('layout', __('Layout'), static::layoutFields()),
            PanelToggle::design('style', static::styleToggleTitle(), static::styleFields()),
            PanelToggle::advanced('classes', __('CSS ID & classes'), static::advancedFields()),
        ];
    }

    /**
     * The title of the Design toggle holding styleFields(), e.g. "Image" or "Cards".
     */
    protected static function styleToggleTitle(): string
    {
        return __('Style');
    }

    /**
     * The block's own name in the builder (canvas label, layers, form cards). Visitors never see it.
     */
    protected static function adminLabelField(): TextInput
    {
        return TextInput::make('admin_label')
            ->label(__('Admin label'))
            ->hintIcon(self::HELP_ICON, __('Your own name for this block, shown in the builder so it\'s easy to find. Visitors never see it.'))
            ->maxLength(60);
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
        $adminLabel = $state['admin_label'] ?? null;

        if (is_string($adminLabel) && filled($adminLabel)) {
            return static::label().': '.Str::limit($adminLabel, 60);
        }

        $heading = $state['heading'] ?? null;

        return is_string($heading) && filled($heading) ? static::label().': '.Str::limit($heading, 60) : static::label();
    }

    /** How a small heading (eyebrow) looks: small capitals, or a pill (a badge). */
    public const EYEBROW_STYLES = [
        'text' => 'Small capitals',
        'pill' => 'Pill',
    ];

    /**
     * A small heading above the block's heading, e.g. "A solid SaaS" or "Most popular", and how it looks.
     */
    public static function eyebrowFields(): Grid
    {
        return Grid::make(['default' => 1, 'sm' => 3])->schema([
            TextInput::make('eyebrow')
                ->label(__('Small heading (optional)'))
                ->hintIcon(self::HELP_ICON, __('A short label above the heading, e.g. "New" or "Most popular".'))
                ->maxLength(60)
                ->live(onBlur: true)
                ->columnSpan(['sm' => 2]),
            Select::make('eyebrow_style')
                ->label(__('Looks like'))
                ->options(array_map(fn (string $label): string => __($label), self::EYEBROW_STYLES))
                ->default('text')
                ->selectablePlaceholder(false)
                ->visible(fn (Get $get): bool => filled($get('eyebrow'))),
        ]);
    }

    /**
     * Optional heading and intro shown above the block's content.
     *
     * @return array<Component|Field>
     */
    protected static function headingFields(bool $isRequired = false): array
    {
        return [
            static::eyebrowFields(),
            HeadingInput::make('heading')
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
        return SiteRichEditor::make($name)
            ->label($label)
            // Dynamic content, as Filament's merge tags: type {{ or use the toolbar's tags button.
            ->mergeTags(DynamicContent::labels())
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'link', 'code'],
                ['h2', 'h3', 'h4', 'paragraph'],
                ['alignStart', 'alignCenter', 'alignEnd'],
                ['bulletList', 'orderedList', 'blockquote', 'codeBlock', 'horizontalRule', 'table'],
                ['mergeTags'],
                ['clearFormatting', 'undo', 'redo'],
            ]);
    }

    /**
     * A block image: JPG, PNG, WebP or AVIF, and (with $allowsSvg) an SVG, which stays sharp at any size. An SVG is served
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
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
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
    protected static function imageStyleFieldset(?string $automaticShadowLabel = null): Grid
    {
        return Grid::make(['default' => 1, 'sm' => 2])
            ->schema([
                static::imageCornersSelect()->columnSpanFull(),
                ...BorderShadow::borderFields('image_', withSides: false),
                ...BorderShadow::shadowFields('image_', $automaticShadowLabel),
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
     * Up to $max buttons: each one's text, link, icon and whether it opens in a new tab. Their look (style, width,
     * colours, border, shadow) is set for the whole block in Design › Buttons (buttonDesignFields()); a button that should
     * look different turns on "This button looks different" and gets its own (hasOwnLook()).
     */
    protected static function buttonsRepeater(int $max = 2, string $name = 'buttons'): Repeater
    {
        return static::itemTooltips(ButtonsRepeater::make($name), __('button'))
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
                    static::iconSelect('icon', __('Icon (optional)'))
                        ->helperText(null),
                    Toggle::make('new_tab')
                        ->label(__('Open in a new tab'))
                        ->hintIcon(self::HELP_ICON, __('Best for links to other websites. Links to this site usually open in the same tab.'))
                        ->inline(false),
                    Toggle::make('own_style')
                        ->label(__('This button looks different'))
                        ->hintIcon(self::HELP_ICON, __('Buttons take the look set in Style › Buttons. Turn this on to give this one its own, e.g. a plainer second button.'))
                        // Buttons saved before the block-wide look kept their own; they still do. (Turned off, a button's
                        // look fields are hidden and not saved, so a button with a look of its own was saved before.)
                        ->afterStateHydrated(fn (Toggle $component, mixed $state, Get $get) => ! $state && $component->state(self::hasOwnLook([
                            'style' => $get('style'), 'width' => $get('width'), 'shadow' => $get('shadow'),
                        ])))
                        ->live()
                        ->columnSpanFull(),
                    Section::make(__('This button\'s look'))
                        ->compact()
                        ->columns(2)
                        ->columnSpanFull()
                        ->visible(fn (Get $get): bool => (bool) $get('own_style'))
                        ->schema(self::buttonLookFields()),
                ]),
            ])
            ->maxItems($max)
            ->defaultItems(0)
            ->collapsible()
            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
            ->addActionLabel(__('Add button'));
    }

    /**
     * The saved keys of the block's own Style and Layout settings (e.g. its cards' look), for copying "All styles" to a
     * block of the same type (StyleClipboard). Fields' names, or the state path of a group that has one.
     *
     * @return array<int, string>
     */
    public static function ownStyleKeys(): array
    {
        $keys = [];
        $walk = function (array $components) use (&$walk, &$keys): void {
            foreach ($components as $component) {
                if ($component instanceof Field) {
                    $keys[] = $component->getName();
                } elseif ($component instanceof Component && filled($statePath = $component->getStatePath(isAbsolute: false))) {
                    $keys[] = $statePath;
                } elseif ($component instanceof Component) {
                    $children = $component->getDefaultChildComponents();
                    $walk(is_array($children) ? $children : []);
                }
            }
        };

        $walk([...static::styleFields(), ...static::layoutFields()]);

        return array_values(array_unique(array_filter($keys)));
    }

    /**
     * Each setting's default in the block's editor, by saved dot key (e.g. "appearance.spacing" => "medium"), so a
     * preset can tell a setting the admin chose from one that was only saved with its default (BlockPresets). Settings
     * inside lists (repeaters) aren't included. Worked out once per type.
     *
     * @return array<string, mixed>
     */
    public static function fieldDefaults(bool $isNested = false): array
    {
        static $cache = [];

        return $cache[static::class][(int) $isNested] ??= (function () use ($isNested): array {
            $defaults = [];
            $walk = function (array $components, string $prefix) use (&$walk, &$defaults): void {
                foreach ($components as $component) {
                    if ($component instanceof Field) {
                        try {
                            $defaults[$prefix.$component->getName()] = $component->getDefaultState();
                        } catch (Throwable) {
                            // A default worked out from the form's other values: there's no fixed one.
                        }
                    } elseif ($component instanceof Component) {
                        $statePath = $component->getStatePath(isAbsolute: false);
                        $children = $component->getDefaultChildComponents();
                        $walk(is_array($children) ? $children : [], filled($statePath) ? $prefix.$statePath.'.' : $prefix);
                    }
                }
            };

            $walk(static::editorSchema($isNested), '');

            return $defaults;
        })();
    }

    /**
     * The Theme Builder template types the block is made for (e.g. ["header", "footer"] for a menu), or null for an
     * ordinary block, offered on pages and in every template. A block made for templates is never offered on a page.
     *
     * @return array<int, string>|null
     */
    public static function templateTypes(): ?array
    {
        return null;
    }

    /**
     * Whether the block has buttons, so its panel offers Design › Buttons. Found from its fields: a buttonsRepeater()
     * (ButtonsRepeater) anywhere. A block that draws buttons of its own overrides this.
     */
    public static function hasButtons(): bool
    {
        return static::uses(ButtonsRepeater::class);
    }

    /**
     * The block's level in the page's structure, for its colour in the editors (SettingsPanel::LEVEL_COLOURS): a
     * block, or a Section.
     */
    public static function structureLevel(): string
    {
        return 'block';
    }

    /**
     * The design options this block's panel leaves out (BlockDesign::toggles()): a group's key, or "group.setting".
     * Saved values for them don't apply either.
     *
     * @return array<int, string>
     */
    public static function designWithout(): array
    {
        return [];
    }

    /**
     * The block's Appearance values as they're drawn. A block that takes settings off its panel can leave their saved
     * values out here, so they can't apply out of sight (a Section's text settings).
     *
     * @return array<string, mixed>
     */
    public static function shownAppearance(mixed $appearance): array
    {
        return is_array($appearance) ? $appearance : [];
    }

    /**
     * Style › Buttons: the look of all the block's buttons (data.button_design), unless a button has its own.
     */
    protected static function buttonDesignFields(): Group
    {
        return Group::make()
            ->statePath('button_design')
            ->columns(2)
            ->schema([
                Text::make(__('Every button in the block, except one with "This button looks different" (in its content) set to a look of its own.'))
                    ->columnSpanFull(),
                ...self::buttonLookFields(),
            ]);
    }

    /**
     * A button's look: style, width, custom colours and border, and shadow. Used for the whole block and for a button
     * with its own look; saved under the same keys either way.
     *
     * @return array<Component|Field>
     */
    private static function buttonLookFields(): array
    {
        return [
            Select::make('style')
                ->label(__('Style'))
                ->hintIcon(self::HELP_ICON, __('Automatic picks the button style that stands out best on the background. Button styles are set in Branding.'))
                ->options(fn (): array => ['auto' => __('Automatic (suits the background)')] + app(BrandingStyle::class)->buttonNames() + [self::CUSTOM_BUTTON_STYLE => __('Custom colours')])
                ->default('auto')
                ->selectablePlaceholder(false)
                ->live(),
            Select::make('width')
                ->label(__('Width'))
                ->hintIcon(self::HELP_ICON, __('Give buttons side by side the same width to line them up.'))
                ->options(array_map(fn (array $width): string => __($width[0]), self::BUTTON_WIDTHS))
                ->default('auto')
                ->selectablePlaceholder(false),
            Grid::make(2)
                ->visible(fn (Get $get): bool => $get('style') === self::CUSTOM_BUTTON_STYLE)
                ->columnSpanFull()
                ->schema([
                    self::buttonColorField('bg_color', __('Background colour'))->helperText(__('Blank: see-through, for an outline button.')),
                    self::buttonColorField('text_color', __('Text colour'))->helperText(__('Blank: white or dark, whichever reads best.')),
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
            Select::make('shadow')
                ->label(__('Shadow'))
                ->options(BorderShadow::shadowOptions(withCustom: false))
                ->default('none')
                ->selectablePlaceholder(false),
        ];
    }

    /** The keys that make up a button's look. */
    public const BUTTON_LOOK_KEYS = ['style', 'width', 'bg_color', 'text_color', 'hover_bg_color', 'hover_text_color', 'border_color', 'border_style', 'border_width', 'shadow'];

    /**
     * Whether a button has its own look rather than the block's: "This button looks different" is on, or (saved before
     * that existed) it has a style, width or shadow of its own.
     *
     * @param  array<string, mixed>  $button
     */
    public static function hasOwnLook(array $button): bool
    {
        $hasLookOfItsOwn = ! in_array($button['style'] ?? 'auto', ['auto', null, ''], true)
            || ! in_array($button['width'] ?? 'auto', ['auto', null, ''], true)
            || ! in_array($button['shadow'] ?? 'none', ['none', null, ''], true);

        // Turned on but left as it was (Automatic, nothing else set): it follows the block's look, as it looks the same.
        if (array_key_exists('own_style', $button) && $button['own_style'] !== null) {
            return (bool) $button['own_style'] && $hasLookOfItsOwn;
        }

        return $hasLookOfItsOwn;
    }

    /**
     * The button with the look it shows: its own, or the block's (Style › Buttons). buttonLook() checks every value.
     *
     * @param  array<string, mixed>  $button
     * @return array<string, mixed>
     */
    public static function withLook(array $button, mixed $blockLook): array
    {
        if (self::hasOwnLook($button)) {
            return $button;
        }

        $keys = array_flip(self::BUTTON_LOOK_KEYS);

        return [...array_diff_key($button, $keys), ...(is_array($blockLook) ? array_intersect_key($blockLook, $keys) : [])];
    }

    /**
     * Tooltips for a repeater's item buttons that name the item, e.g. "Duplicate this card" and "Delete this card".
     */
    public static function itemTooltips(Repeater $repeater, string $item): Repeater
    {
        $tooltip = fn (string $text): Closure => fn (Action $action): Action => $action->tooltip(__($text, ['item' => $item]));

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

    private static function buttonColorField(string $name, string $label): SiteColour
    {
        return SiteColour::make($name)
            ->label($label);
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
        $color = fn (string $key): ?string => Colour::css($button[$key] ?? null);
        // A blank background is see-through: an outline (ghost) button, e.g. white text and border on a banner.
        // Custom with a background or a text colour. Without a text colour: white or dark, whichever reads on the
        // background (or on the area around it, for an outline button).
        $isCustom = ($button['style'] ?? null) === self::CUSTOM_BUTTON_STYLE && ($color('text_color') !== null || $color('bg_color') !== null);
        $background = $color('bg_color') ?? 'transparent';
        $backgroundHex = Colour::hex($button['bg_color'] ?? null);
        $text = $color('text_color') ?? match (true) {
            $backgroundHex !== null => Colour::isReadableTextOn('#ffffff', $backgroundHex) ? '#ffffff' : '#111827',
            default => $isDark ? '#ffffff' : '#111827',
        };
        $width = self::BUTTON_WIDTHS[static::pick($button['width'] ?? null, self::BUTTON_WIDTHS, 'auto')][1];

        $style = '';

        if ($isCustom) {
            $style = "--site-button-bg: {$background}; --site-button-text: {$text};"
                .' --site-button-bg-hover: '.($color('hover_bg_color') ?? ($color('bg_color') !== null ? "color-mix(in oklab, {$background} 85%, black)" : 'color-mix(in oklab, currentColor 12%, transparent)')).';'
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

        // Dynamic content (merge tags) stays as its tag, filled in by DynamicContent::replace() with the page.
        return RichContentRenderer::make($content)->mergeTags(DynamicContent::placeholders())->toHtml();
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

        if (is_string($path) && isset(self::$previewImages[$path])) {
            return self::$previewImages[$path];
        }

        $pages = app(PageService::class);

        return $pages->isPageUpload($path) ? Storage::disk($pages->uploadDisk())->url($path) : null;
    }
}
