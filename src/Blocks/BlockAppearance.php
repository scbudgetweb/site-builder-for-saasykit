<?php

namespace ScBudgetWeb\SiteBuilder\Blocks;

use Closure;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use ScBudgetWeb\SiteBuilder\Support\Colour;

/**
 * The background, text colour, spacing, width, shape and anchor shared by every block. Presets keep
 * pages on-brand (they follow Branding), with a custom colour, a gradient or an image as the escape hatch.
 *
 * The background fills the full width by default. As a "panel" it sits behind the content only, as a
 * card that can have its own corners, border and shadow, and overlap the block above.
 */
class BlockAppearance
{
    public const BACKGROUNDS = [
        'none' => 'None (page background)',
        'light' => 'Light grey',
        'brand_light' => 'Site colour, light tint',
        'brand' => 'Site colour',
        'brand_dark' => 'Site colour, dark',
        'dark' => 'Dark',
        'custom' => 'Custom colour',
        'gradient' => 'Gradient',
        'image' => 'Image',
    ];

    public const BACKGROUND_AREAS = [
        'full' => 'Full width',
        'panel' => 'Behind the content, as a panel',
    ];

    public const TEXT_COLOURS = [
        'auto' => 'Automatic (from the background)',
        'dark' => 'Dark',
        'light' => 'Light',
    ];

    public const SPACINGS = [
        'none' => 'None',
        'small' => 'Small',
        'medium' => 'Medium',
        'large' => 'Large',
    ];

    public const WIDTHS = [
        'narrow' => 'Narrow (for reading)',
        'contained' => 'Contained',
        'wide' => 'Wide',
        'full' => 'Full width',
    ];

    public const OVERLAY_TYPES = [
        'solid' => 'One colour',
        'gradient' => 'Gradient (fades between two colours)',
    ];

    /** Overlay gradient direction => [label, CSS angle]. */
    public const OVERLAY_DIRECTIONS = [
        'to_bottom' => ['Top to bottom', '180deg'],
        'to_top' => ['Bottom to top', '0deg'],
        'to_right' => ['Left to right', '90deg'],
        'to_left' => ['Right to left', '270deg'],
    ];

    public const PANEL_CORNERS = [
        'site' => 'Site style (Branding)',
        'none' => 'None',
        'small' => 'Small',
        'medium' => 'Medium',
        'large' => 'Large',
        'pill' => 'Pill (very round)',
    ];

    public const OVERLAPS = [
        'none' => 'None',
        'small' => 'Small',
        'medium' => 'Medium',
        'large' => 'Large',
    ];

    public const HEADING_ALIGNMENTS = [
        'block' => 'The block\'s usual alignment',
        'left' => 'Left',
        'center' => 'Centre',
    ];

    /** Per-block heading decoration: the site's (Branding) style, none, or one of Branding's styles for this block. */
    public const HEADING_DECORATIONS = [
        'site' => 'Site style (Branding)',
        'none' => 'None',
        'short_under' => 'Short bar under the heading',
        'line_under' => 'Full-width line under the heading',
        'short_above' => 'Short bar above the heading',
    ];

    /** Short names for the editor's block card tags. */
    private const DECORATION_TAGS = [
        'short_under' => 'Bar under',
        'line_under' => 'Line under',
        'short_above' => 'Bar above',
    ];

    /** Decoration => the class that draws it (site-builder.css); "site" adds none, so Branding's style applies. */
    private const HEADING_DECORATION_CLASSES = [
        'site' => '',
        'none' => 'no-heading-decoration',
        'short_under' => 'heading-decoration-short-under',
        'line_under' => 'heading-decoration-line-under',
        'short_above' => 'heading-decoration-short-above',
    ];

    /** Heading size => [label, CSS font size]; "standard" keeps the block's own size. */
    public const HEADING_SIZES = [
        'standard' => ['Standard', null],
        'small' => ['Small', 'clamp(1.25rem, 1.1rem + 0.5vw, 1.5rem)'],
        'medium' => ['Medium', 'clamp(1.5rem, 1.25rem + 1vw, 1.875rem)'],
        'large' => ['Large', 'clamp(1.875rem, 1.4rem + 1.6vw, 2.5rem)'],
        'xl' => ['Extra large', 'clamp(2.25rem, 1.6rem + 2.4vw, 3.25rem)'],
        'xxl' => ['Huge', 'clamp(2.75rem, 1.8rem + 3.6vw, 4.5rem)'],
    ];

    /** Body text size => [label, CSS font size]; "standard" keeps each block's own sizes. */
    public const TEXT_SIZES = [
        'standard' => ['Standard', null],
        'small' => ['Small', '0.875rem'],
        'medium' => ['Medium', '1rem'],
        'large' => ['Large', '1.125rem'],
        'xl' => ['Extra large', '1.25rem'],
    ];

    /** Space-separated class names (for Branding › Custom CSS). */
    public const CSS_CLASS_PATTERN = '/^-?[A-Za-z_][A-Za-z0-9_-]*( +-?[A-Za-z_][A-Za-z0-9_-]*)*$/';

    /** The utility classes that would pin a block over the rest of the page (and the site's header). */
    private const FIXED_POSITION_CLASS_PATTERN = '/(^| )(fixed|sticky)( |$)/';

    private const BACKGROUND_CLASSES = [
        'none' => '',
        'light' => 'bg-gray-50',
        'brand_light' => 'bg-primary-50',
        'brand' => 'bg-primary-600',
        'brand_dark' => 'bg-primary-900',
        'dark' => 'bg-gray-900',
        'custom' => '',
        'gradient' => '',
        'image' => 'bg-gray-900',
    ];

    private const DARK_BACKGROUNDS = ['brand', 'brand_dark', 'dark', 'image'];

    private const SPACING_CLASSES = [
        'none' => '',
        'small' => 'py-8 sm:py-10',
        'medium' => 'py-12 sm:py-16',
        'large' => 'py-16 sm:py-24',
    ];

    private const WIDTH_CLASSES = [
        'narrow' => 'max-w-3xl mx-auto px-4 sm:px-6',
        'contained' => 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8',
        'wide' => 'max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8',
        'full' => '',
    ];

    private const PANEL_CORNER_CLASSES = [
        'site' => 'rounded-sb-card',
        'none' => 'rounded-none',
        'small' => 'rounded-md',
        'medium' => 'rounded-xl',
        'large' => 'rounded-3xl',
        'pill' => 'rounded-[3rem] sm:rounded-[6rem]',
    ];

    /** Negative top margin; the panel sits above the previous block (z-10). */
    private const OVERLAP_CLASSES = [
        'none' => '',
        'small' => 'z-10 -mt-8 sm:-mt-12',
        'medium' => 'z-10 -mt-16 sm:-mt-20',
        'large' => 'z-10 -mt-24 sm:-mt-32',
    ];

    /**
     * The Style tab: background (with its overlay) and text. $headingSettings: which heading settings the
     * block's heading uses ("align", "decoration", "size"); $hasBodyText: whether Text size does anything.
     *
     * @param  array<string>  $headingSettings
     */
    public static function styleGroup(string $defaultBackground = 'none', array $headingSettings = ['align', 'decoration', 'size'], bool $hasBodyText = true): Group
    {
        [$headingSize, $textSize] = self::sizeFields();

        return Group::make()
            ->statePath('appearance')
            ->schema([
                Fieldset::make(__('Background'))
                    ->columns(2)
                    ->schema(self::backgroundFields($defaultBackground)),
                Fieldset::make(__('Text'))
                    ->columns(2)
                    ->visible($headingSettings !== [] || $hasBodyText)
                    ->schema(array_values(array_filter([
                        in_array('decoration', $headingSettings, true) ? Select::make('heading_decoration')
                            ->label(__('Heading decoration'))
                            ->hintIcon(PageBlock::HELP_ICON, __('A small bar or line that sets off the heading. Site style uses the one chosen in Branding.'))
                            ->options(self::translated(self::HEADING_DECORATIONS))
                            ->default('site')
                            ->selectablePlaceholder(false) : null,
                        in_array('size', $headingSettings, true) ? $headingSize : null,
                        $hasBodyText ? $textSize : null,
                    ]))),
                // On the panel when the background is one, otherwise on the whole block.
                self::moreOptions(__('A border or shadow around the block.'), [
                    ...BorderShadow::borderFields(),
                    ...BorderShadow::shadowFields(automaticLabel: __('Automatic (site style on a panel, none otherwise)')),
                ]),
            ]);
    }

    /**
     * Settings most pages don't need, in a collapsed "More options" so the everyday ones stand out. Only the editor
     * changes: the fields keep their saved keys.
     *
     * @param  array<int, Component|Field>  $fields
     */
    public static function moreOptions(string $description, array $fields): Section
    {
        return Section::make(__('More options'))
            ->description($description)
            ->collapsible()
            ->collapsed()
            ->compact()
            ->gridContainer()
            ->columns(['@md' => 2])
            ->schema($fields);
    }

    /**
     * Background (a preset, a custom colour, a gradient, or with $withImage an image and its overlay) and text colour.
     * Shared by blocks and a Section's columns, which have no image (backgroundLook()).
     *
     * @return array<int, Select|ColorPicker|FileUpload|Slider>
     */
    public static function backgroundFields(string $defaultBackground = 'none', bool $withImage = true): array
    {
        $background = fn (Get $get): ?string => $get('background');

        return [
            Select::make('background')
                ->label(__('Background'))
                ->hintIcon(PageBlock::HELP_ICON, __('The colour or picture behind it. The site colours follow Branding, so they change if your brand colour does.'))
                ->options(self::translated($withImage ? self::BACKGROUNDS : array_diff_key(self::BACKGROUNDS, ['image' => true])))
                ->default($defaultBackground)
                ->selectablePlaceholder(false)
                ->live(),
            Select::make('text')
                ->label(__('Text colour'))
                ->hintIcon(PageBlock::HELP_ICON, __('Automatic picks dark or light text, whichever is easier to read on the background.'))
                ->options(self::translated(self::TEXT_COLOURS))
                ->default('auto')
                ->selectablePlaceholder(false),
            ColorPicker::make('background_color')
                ->label(__('Background colour'))
                ->regex('/^#[0-9a-fA-F]{6}$/')
                ->required()
                ->visible(fn (Get $get): bool => $background($get) === 'custom'),
            ColorPicker::make('gradient_start')
                ->label(__('Gradient start colour'))
                ->regex('/^#[0-9a-fA-F]{6}$/')
                ->required()
                ->visible(fn (Get $get): bool => $background($get) === 'gradient'),
            ColorPicker::make('gradient_end')
                ->label(__('Gradient end colour'))
                ->regex('/^#[0-9a-fA-F]{6}$/')
                ->required()
                ->visible(fn (Get $get): bool => $background($get) === 'gradient'),
            ColorPicker::make('gradient_middle')
                ->label(__('Gradient middle colour (optional)'))
                ->regex('/^#[0-9a-fA-F]{6}$/')
                ->visible(fn (Get $get): bool => $background($get) === 'gradient'),
            Select::make('gradient_direction')
                ->label(__('Gradient direction'))
                ->options(array_map(fn (string $label): string => __($label), Colour::GRADIENT_DIRECTIONS))
                ->default(Colour::DEFAULT_GRADIENT_DIRECTION)
                ->selectablePlaceholder(false)
                ->visible(fn (Get $get): bool => $background($get) === 'gradient'),
            ...($withImage ? [
                PageBlock::imageUpload('background_image', __('Background image'))
                    ->required()
                    ->visible(fn (Get $get): bool => $background($get) === 'image')
                    ->columnSpanFull(),
                ...self::overlayFields(fn (Get $get): bool => $background($get) === 'image'),
            ] : []),
        ];
    }

    /**
     * The Layout tab: width, spacing, heading alignment and the background panel.
     *
     * @param  array<string>  $headingSettings
     */
    public static function layoutGroup(string $defaultWidth = 'contained', string $defaultSpacing = 'medium', array $headingSettings = ['align', 'decoration', 'size']): Group
    {
        $background = fn (Get $get): ?string => $get('background');
        $isPanel = fn (Get $get): bool => $get('background_area') === 'panel' && $background($get) !== 'none';

        return Group::make()
            ->statePath('appearance')
            ->schema([
                Fieldset::make(__('Width & spacing'))
                    ->columns(2)
                    ->schema(array_values(array_filter([
                        Select::make('width')
                            ->label(__('Content width'))
                            ->hintIcon(PageBlock::HELP_ICON, __('How wide the content can get on a big screen. Narrow is easiest for long text; Full width goes from edge to edge.'))
                            ->options(self::translated(self::WIDTHS))
                            ->default($defaultWidth)
                            ->selectablePlaceholder(false),
                        Select::make('spacing')
                            ->label(__('Space above and below'))
                            ->hintIcon(PageBlock::HELP_ICON, __('Empty space between this block and the ones above and below it.'))
                            ->options(self::translated(self::SPACINGS))
                            ->default($defaultSpacing)
                            ->selectablePlaceholder(false),
                        in_array('align', $headingSettings, true) ? Select::make('heading_align')
                            ->label(__('Heading alignment'))
                            ->options(self::translated(self::HEADING_ALIGNMENTS))
                            ->default('block')
                            ->selectablePlaceholder(false) : null,
                    ]))),
                self::moreOptions(__('Show the background as a card behind the content, and float it over the block above.'), [

                    Select::make('background_area')
                        ->label(__('Background area'))
                        ->options(self::translated(self::BACKGROUND_AREAS))
                        ->default('full')
                        ->selectablePlaceholder(false)
                        ->live()
                        ->helperText(__('A panel is a card behind the content, which can have rounded corners, and a border and shadow (Style tab).')),
                    Select::make('corners')
                        ->label(__('Panel corners'))
                        ->options(self::translated(self::PANEL_CORNERS))
                        ->default('site')
                        ->selectablePlaceholder(false)
                        ->visible($isPanel),
                    Select::make('overlap')
                        ->label(__('Overlap the block above'))
                        ->options(self::translated(self::OVERLAPS))
                        ->default('none')
                        ->selectablePlaceholder(false)
                        ->helperText(__('Moves the panel up so it floats over the bottom of the block above, e.g. over a banner.'))
                        ->visible($isPanel),
                ])->visible(fn (Get $get): bool => $background($get) !== 'none'),
            ]);
    }

    /**
     * The Advanced tab: anchor and CSS classes.
     */
    public static function advancedGroup(): Group
    {
        return Group::make()
            ->statePath('appearance')
            ->columns(2)
            ->schema([
                TextInput::make('anchor')
                    ->label(__('Anchor ID'))
                    ->hintIcon(PageBlock::HELP_ICON, __('A short name for this part of the page, so a button or menu link can jump straight to it.'))
                    ->helperText(__('Optional. Lets buttons link to this block, e.g. an anchor of "pricing" makes a link to #pricing scroll here.'))
                    ->alphaDash()
                    ->maxLength(50),
                self::cssClassField(),
            ]);
    }

    /**
     * Short tags for the editor's block card, so styling shows without opening the block:
     * e.g. ["Gradient", "Panel", "Overlaps", "Heading: Extra large", "#pricing", ".promo"].
     * Only recognised values are named, so the card never shows hand-edited text.
     *
     * @param  array<string, mixed>|null  $appearance
     * @return array<string>
     */
    public static function summary(?array $appearance, mixed $cssClass = null): array
    {
        $appearance ??= [];
        $background = self::pick($appearance['background'] ?? null, self::BACKGROUNDS, 'none');
        $isPanel = $background !== 'none' && ($appearance['background_area'] ?? null) === 'panel';
        $headingSize = self::pick($appearance['heading_size'] ?? null, self::HEADING_SIZES, 'standard');
        $decoration = self::pick($appearance['heading_decoration'] ?? null, self::HEADING_DECORATIONS, 'site');
        $textSize = self::pick($appearance['text_size'] ?? null, self::TEXT_SIZES, 'standard');
        $classes = self::cssClasses($cssClass ?? ($appearance['css_class'] ?? null));
        $anchor = is_string($appearance['anchor'] ?? null) && preg_match('/^[A-Za-z0-9_-]+$/', $appearance['anchor']) ? $appearance['anchor'] : null;

        return array_values(array_filter([
            $background !== 'none' ? __(self::BACKGROUNDS[$background]) : null,
            $isPanel ? __('Panel') : null,
            $isPanel && self::pick($appearance['overlap'] ?? null, self::OVERLAPS, 'none') !== 'none' ? __('Overlaps') : null,
            $decoration !== 'site' ? __('Decoration: :style', ['style' => $decoration === 'none' ? __('None') : __(self::DECORATION_TAGS[$decoration])]) : null,
            $headingSize !== 'standard' ? __('Heading: :size', ['size' => __(self::HEADING_SIZES[$headingSize][0])]) : null,
            $textSize !== 'standard' ? __('Text: :size', ['size' => __(self::TEXT_SIZES[$textSize][0])]) : null,
            ...BorderShadow::summary($appearance),
            $anchor !== null ? '#'.$anchor : null,
            $classes !== '' ? '.'.str_replace(' ', ' .', $classes) : null,
        ]));
    }

    /**
     * Heading size and text size. In a Section, they apply to the blocks inside unless a block sets its own.
     *
     * @return array<Select>
     */
    public static function sizeFields(): array
    {
        return [
            Select::make('heading_size')
                ->label(__('Heading size'))
                ->options(array_map(fn (array $size): string => __($size[0]), self::HEADING_SIZES))
                ->default('standard')
                ->selectablePlaceholder(false),
            Select::make('text_size')
                ->label(__('Text size'))
                ->hintIcon(PageBlock::HELP_ICON, __('The size of the body text. On a Section, the blocks inside use it unless they choose their own.'))
                ->options(array_map(fn (array $size): string => __($size[0]), self::TEXT_SIZES))
                ->default('standard')
                ->selectablePlaceholder(false),
        ];
    }

    public static function cssClassField(): TextInput
    {
        return TextInput::make('css_class')
            ->label(__('CSS classes'))
            ->hintIcon(PageBlock::HELP_ICON, __('For people who write CSS: names that Branding › Custom CSS can style. Leave it empty if you don\'t need it.'))
            ->helperText(__('Optional. Names to style in Branding › Custom CSS, separated by spaces, e.g. "promo dark-cards".'))
            ->maxLength(100)
            ->regex(self::CSS_CLASS_PATTERN)
            ->notRegex(self::FIXED_POSITION_CLASS_PATTERN)
            ->validationMessages([
                'regex' => __('Use letters, numbers, - and _ only, with spaces between names.'),
                'not_regex' => __('"fixed" and "sticky" aren\'t allowed: they could pin the block over the rest of the page.'),
            ]);
    }

    /**
     * The saved custom classes, if they're valid.
     */
    public static function cssClasses(mixed $value): string
    {
        if (! is_string($value) || strlen($value) > 100 || ! preg_match(self::CSS_CLASS_PATTERN, trim($value))) {
            return '';
        }

        // "fixed" and "sticky" (saved before they were refused) are left out, so a block can't cover the page.
        return implode(' ', array_diff(preg_split('/ +/', trim($value)) ?: [], ['fixed', 'sticky']));
    }

    /**
     * Classes and CSS variables for the chosen heading and text sizes. Variables inherit, so a Section's
     * sizes reach its blocks, and a block's own choice wins inside it.
     *
     * @param  array<string, mixed>  $settings
     * @return array{class: string, style: string}
     */
    public static function sizes(array $settings): array
    {
        $headingSize = self::HEADING_SIZES[self::pick($settings['heading_size'] ?? null, self::HEADING_SIZES, 'standard')][1];
        $textSize = self::TEXT_SIZES[self::pick($settings['text_size'] ?? null, self::TEXT_SIZES, 'standard')][1];

        return [
            'class' => trim(($headingSize ? 'has-heading-size ' : '').($textSize ? 'has-text-size' : '')),
            'style' => trim(($headingSize ? "--heading-size: {$headingSize}; " : '').($textSize ? "--text-size: {$textSize};" : '')),
        ];
    }

    /**
     * Wrapper attributes for a block, from its saved appearance. Every value is re-checked against the
     * whitelists here, so a hand-edited database value can't inject classes or CSS.
     *
     * The background (colour, gradient or image) goes on the section, or on the panel when it is one.
     * $parentIsDark is set for a block inside a Section: with no background of its own, it keeps the
     * section's text colour, spacing and width defaults to none / full.
     *
     * Heading decoration: "site" inside a Section takes the section's choice ($parentHeadingDecoration). The
     * decoration class goes on the block (not on a Section, whose choice reaches its blocks this way), so a
     * block's own choice always wins over its section's (lesson 7).
     *
     * @return array{id: ?string, class: string, style: string, container: string, panel: ?array{class: string, style: string}, image: ?string, overlayStyle: ?string, isDark: bool, textScheme: ?string, headingAlign: ?string, headingDecoration: string, headingDecorationClass: string}
     */
    public static function resolve(mixed $appearance, string $defaultWidth = 'contained', string $defaultBackground = 'none', ?string $defaultAnchor = null, ?bool $parentIsDark = null, ?string $parentHeadingDecoration = null): array
    {
        $appearance = is_array($appearance) ? $appearance : [];

        $background = self::pick($appearance['background'] ?? null, self::BACKGROUNDS, $defaultBackground);
        $isNested = $parentIsDark !== null;
        $spacing = self::pick($appearance['spacing'] ?? null, self::SPACINGS, $isNested ? 'none' : 'medium');
        $width = self::pick($appearance['width'] ?? null, self::WIDTHS, $isNested ? 'full' : $defaultWidth);
        $text = self::pick($appearance['text'] ?? null, self::TEXT_COLOURS, 'auto');
        $isPanel = $background !== 'none' && self::pick($appearance['background_area'] ?? null, self::BACKGROUND_AREAS, 'full') === 'panel';

        $customColor = Colour::isValidHex($appearance['background_color'] ?? null) ? $appearance['background_color'] : null;
        $gradientStops = $background === 'gradient' ? self::gradientStops($appearance) : [];
        $gradient = $gradientStops !== [] ? Colour::gradientCss($appearance) : null;
        $image = $background === 'image' ? PageBlock::imageUrl($appearance['background_image'] ?? null) : null;

        // Inside a section, a block without its own background takes the section's text colour.
        $inheritsText = $isNested && $background === 'none' && $text === 'auto';

        $isDark = match (true) {
            $text === 'dark' => false,
            $text === 'light' => true,
            $inheritsText => $parentIsDark,
            default => self::isDarkBackground($background, $customColor, $gradientStops),
        };

        $backgroundStyle = match (true) {
            $background === 'custom' && $customColor !== null => "background-color: {$customColor};",
            $gradient !== null => "background-image: {$gradient};",
            default => '',
        };

        $backgroundClasses = self::BACKGROUND_CLASSES[$background];
        $sizes = self::sizes($appearance);
        $headingDecoration = self::pick($appearance['heading_decoration'] ?? null, self::HEADING_DECORATIONS, 'site');
        $headingDecoration = $headingDecoration === 'site' && isset(self::HEADING_DECORATIONS[(string) $parentHeadingDecoration]) ? (string) $parentHeadingDecoration : $headingDecoration;
        $headingAlign = self::pick($appearance['heading_align'] ?? null, self::HEADING_ALIGNMENTS, 'block');

        return [
            'id' => is_string($appearance['anchor'] ?? null) && preg_match('/^[A-Za-z0-9_-]+$/', $appearance['anchor']) ? $appearance['anchor'] : $defaultAnchor,
            'class' => trim(implode(' ', array_filter([
                'relative',
                $isPanel ? '' : $backgroundClasses,
                self::SPACING_CLASSES[$spacing],
                $isPanel ? self::OVERLAP_CLASSES[self::pick($appearance['overlap'] ?? null, self::OVERLAPS, 'none')] : '',
                $inheritsText ? '' : ($isDark ? 'text-white' : 'text-gray-900'),
                $sizes['class'],
                self::cssClasses($appearance['css_class'] ?? null),
            ]))),
            'style' => trim(($isPanel ? '' : $backgroundStyle.' '.BorderShadow::style($appearance)).' '.$sizes['style']),
            'container' => self::WIDTH_CLASSES[$width],
            'panel' => $isPanel ? [
                'class' => trim(implode(' ', array_filter([
                    // No overflow-hidden: the background image is clipped in its own layer (wrapper view).
                    'relative px-6 py-10 sm:px-12 sm:py-14',
                    self::BACKGROUND_CLASSES[$background],
                    self::PANEL_CORNER_CLASSES[self::pick($appearance['corners'] ?? null, self::PANEL_CORNERS, 'site')],
                ]))),
                // A panel has the site's card shadow unless the block chooses another.
                'style' => trim($backgroundStyle.' '.BorderShadow::style($appearance, defaultShadow: 'site')),
            ] : null,
            'image' => $image,
            'overlayStyle' => $image ? self::overlayStyle($appearance) : null,
            'isDark' => $isDark,
            // Dark text on light backgrounds, light text on dark ones (Branding › Typography › Text colours);
            // null for a block in a Section that takes the section's text.
            'textScheme' => $inheritsText ? null : ($isDark ? 'light' : 'dark'),
            'headingAlign' => $headingAlign === 'block' ? null : $headingAlign,
            'headingDecoration' => $headingDecoration,
            'headingDecorationClass' => self::HEADING_DECORATION_CLASSES[$headingDecoration],
        ];
    }

    /**
     * The image overlay settings read by imageOverlay(): one colour, or a gradient between two colours and strengths.
     * $prefix goes before each field's name (Branding's banners use e.g. "hero_"), $isShown says when they show (an
     * image background), and $live makes the strengths live, for a page that warns about readability as they change.
     *
     * @param  Closure(Get): bool  $isShown
     * @return array<int, Select|ColorPicker|Slider>
     */
    public static function overlayFields(Closure $isShown, string $prefix = '', bool $live = false, ?string $opacityHelperText = null): array
    {
        $isGradient = fn (Get $get): bool => $isShown($get) && $get("{$prefix}overlay_type") === 'gradient';

        return [
            Select::make("{$prefix}overlay_type")
                ->label(__('Overlay'))
                ->hintIcon(PageBlock::HELP_ICON, __('A see-through colour laid over the picture, so text on top of it is easy to read.'))
                ->options(self::translated(self::OVERLAY_TYPES))
                ->default('solid')
                ->selectablePlaceholder(false)
                ->live()
                ->visible($isShown),
            Select::make("{$prefix}overlay_direction")
                ->label(__('Overlay direction'))
                ->options(array_map(fn (array $option): string => __($option[0]), self::OVERLAY_DIRECTIONS))
                ->default('to_bottom')
                ->selectablePlaceholder(false)
                ->visible($isGradient),
            ColorPicker::make("{$prefix}overlay_color")
                ->label(fn (Get $get): string => $isGradient($get) ? __('Overlay start colour') : __('Overlay colour'))
                ->regex('/^#[0-9a-fA-F]{6}$/')
                ->placeholder(Colour::DEFAULT_OVERLAY_COLOR)
                ->visible($isShown),
            Slider::make("{$prefix}overlay_opacity")
                ->label(fn (Get $get): string => $isGradient($get) ? __('Overlay start strength (%)') : __('Overlay opacity (%)'))
                ->helperText($opacityHelperText)
                ->range(minValue: 0, maxValue: 100)
                ->step(5)
                ->decimalPlaces(0)
                ->tooltips()
                ->default(Colour::DEFAULT_OVERLAY_OPACITY)
                ->live(condition: $live)
                ->visible($isShown),
            ColorPicker::make("{$prefix}overlay_end_color")
                ->label(__('Overlay end colour'))
                ->regex('/^#[0-9a-fA-F]{6}$/')
                ->placeholder(Colour::DEFAULT_OVERLAY_COLOR)
                ->visible($isGradient),
            Slider::make("{$prefix}overlay_end_opacity")
                ->label(__('Overlay end strength (%)'))
                ->range(minValue: 0, maxValue: 100)
                ->step(5)
                ->decimalPlaces(0)
                ->tooltips()
                ->default(0)
                ->live(condition: $live)
                ->visible($isGradient),
        ];
    }

    /**
     * An image overlay from its settings, every value checked again: overlay_type ("solid" or "gradient"),
     * overlay_color and overlay_opacity, and for a gradient overlay_direction, overlay_end_color and
     * overlay_end_opacity. A solid overlay is one colour at the chosen opacity ("gradient" is null); a gradient fades
     * from the start colour and strength to the end ones, and "opacity" is its stronger end. Used by the Style tab, the
     * Banner and Hero blocks, and Branding's banners.
     *
     * @param  array<string, mixed>  $values
     * @return array{color: string, opacity: float, gradient: ?string}
     */
    public static function imageOverlay(array $values): array
    {
        $startColor = Colour::isValidHex($values['overlay_color'] ?? null) ? $values['overlay_color'] : Colour::DEFAULT_OVERLAY_COLOR;
        $startOpacity = self::percentage($values['overlay_opacity'] ?? null, Colour::DEFAULT_OVERLAY_OPACITY);

        if (($values['overlay_type'] ?? 'solid') !== 'gradient') {
            return ['color' => $startColor, 'opacity' => $startOpacity / 100.0, 'gradient' => null];
        }

        $endColor = Colour::isValidHex($values['overlay_end_color'] ?? null) ? $values['overlay_end_color'] : Colour::DEFAULT_OVERLAY_COLOR;
        $endOpacity = self::percentage($values['overlay_end_opacity'] ?? null, 0);
        $direction = self::OVERLAY_DIRECTIONS[self::pick($values['overlay_direction'] ?? null, self::OVERLAY_DIRECTIONS, 'to_bottom')][1];

        return [
            'color' => $startColor,
            'opacity' => max($startOpacity, $endOpacity) / 100.0,
            'gradient' => "linear-gradient({$direction}, ".self::rgba($startColor, $startOpacity).', '.self::rgba($endColor, $endOpacity).')',
        ];
    }

    /**
     * An overlay's inline style (from imageOverlay()), or null when it's fully transparent.
     *
     * @param  array{color: string, opacity: float, gradient: ?string}|null  $overlay
     */
    public static function overlayCss(?array $overlay): ?string
    {
        if ($overlay === null || $overlay['opacity'] <= 0) {
            return null;
        }

        return $overlay['gradient'] !== null
            ? "background-image: {$overlay['gradient']};"
            : "background-color: {$overlay['color']}; opacity: {$overlay['opacity']};";
    }

    /**
     * The Style tab's image overlay as an inline style, or null when it's fully transparent.
     *
     * @param  array<string, mixed>  $appearance
     */
    private static function overlayStyle(array $appearance): ?string
    {
        return self::overlayCss(self::imageOverlay($appearance));
    }

    /**
     * The classes and inline style for a background without an image (a Section's column): its preset, custom colour or
     * gradient, and its text colour. "textScheme" is null when the text follows the area around it (no background and
     * Automatic text). Every value is re-checked against the whitelists, as in resolve().
     *
     * @param  array<string, mixed>  $values
     * @return array{class: string, style: string, textScheme: ?string, hasBackground: bool}
     */
    public static function backgroundLook(array $values): array
    {
        $background = self::pick($values['background'] ?? null, array_diff_key(self::BACKGROUNDS, ['image' => true]), 'none');
        $text = self::pick($values['text'] ?? null, self::TEXT_COLOURS, 'auto');
        $customColor = Colour::isValidHex($values['background_color'] ?? null) ? $values['background_color'] : null;
        $gradientStops = $background === 'gradient' ? self::gradientStops($values) : [];
        $gradient = $gradientStops !== [] ? Colour::gradientCss($values) : null;

        $textScheme = match (true) {
            $text === 'dark' => 'dark',
            $text === 'light' => 'light',
            $background === 'none' => null,
            default => self::isDarkBackground($background, $customColor, $gradientStops) ? 'light' : 'dark',
        };

        return [
            'class' => trim(self::BACKGROUND_CLASSES[$background].' '.match ($textScheme) {
                'light' => 'text-white',
                'dark' => 'text-gray-900',
                default => '',
            }),
            'style' => match (true) {
                $background === 'custom' && $customColor !== null => "background-color: {$customColor};",
                $gradient !== null => "background-image: {$gradient};",
                default => '',
            },
            'textScheme' => $textScheme,
            'hasBackground' => $background !== 'none',
        ];
    }

    /**
     * Whether a background is dark enough for light text: a dark preset, or a custom colour or gradient that white
     * text reads well on.
     *
     * @param  array<string>  $gradientStops
     */
    private static function isDarkBackground(string $background, ?string $customColor, array $gradientStops): bool
    {
        return in_array($background, self::DARK_BACKGROUNDS, true)
            || ($background === 'custom' && $customColor !== null && Colour::isReadableTextOn('#ffffff', $customColor))
            || ($gradientStops !== [] && self::suitsLightText($gradientStops));
    }

    /**
     * @param  array<string, mixed>  $appearance
     * @return array<string> the valid gradient colours, or [] when the start or end is missing
     */
    private static function gradientStops(array $appearance): array
    {
        return Colour::gradientStops($appearance) ?? [];
    }

    /**
     * Whether white text reads better than dark text across a gradient: white must be readable on at
     * least as many of its colours.
     *
     * @param  array<string>  $stops
     */
    private static function suitsLightText(array $stops): bool
    {
        $whiteReadable = count(array_filter($stops, fn (string $stop): bool => Colour::isReadableTextOn('#ffffff', $stop)));
        $darkReadable = count(array_filter($stops, fn (string $stop): bool => Colour::isReadableTextOn('#111827', $stop)));

        return $whiteReadable >= $darkReadable && $whiteReadable > 0;
    }

    private static function percentage(mixed $value, int $default): int
    {
        return is_numeric($value) ? max(0, min(100, (int) $value)) : $default;
    }

    private static function rgba(string $hex, int $opacity): string
    {
        [$red, $green, $blue] = sscanf($hex, '#%02x%02x%02x');

        return "rgba({$red}, {$green}, {$blue}, ".($opacity / 100.0).')';
    }

    /**
     * A saved choice if it's one of the options, otherwise the default. Selects hand numeric keys back as
     * integers (lesson 2), so those count too.
     *
     * @param  array<string, mixed>  $options
     */
    private static function pick(mixed $value, array $options, string $default): string
    {
        if ((is_string($value) || is_int($value)) && array_key_exists((string) $value, $options)) {
            return (string) $value;
        }

        return array_key_exists($default, $options) ? $default : (string) array_key_first($options);
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string, string>
     */
    private static function translated(array $options): array
    {
        return array_map(fn (string $label): string => __($label), $options);
    }
}
