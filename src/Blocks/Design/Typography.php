<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Design;

use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\HoverMode;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\PanelToggle;
use ScBudgetWeb\SiteBuilder\Branding\Branding;
use ScBudgetWeb\SiteBuilder\Filament\Forms\DeviceValue;
use ScBudgetWeb\SiteBuilder\Filament\Forms\SiteColour;

/**
 * Style › Heading text, Body text and Links: font (a Branding font), weight, style, size, line height,
 * letter spacing, alignment, colour and shadow for the block's headings and its body text, and colour, hover colour,
 * weight and underline for its links. Sizes, line heights and letter spacing can differ by screen size. Saved under
 * data.design.typography as {heading: {…}, body: {…}, links: {…}, lists: {…}}; resources/css/design/typography.css reads it.
 *
 * Everything goes on the section and is inherited by what's inside. A custom size wins over Style › Heading size and
 * Text size, and a colour wins over the text scheme's.
 */
final class Typography implements DesignGroup
{
    /** Text => the short name in its classes and properties (sb-d-hf, --sb-hf…). */
    private const TEXTS = ['heading' => 'h', 'body' => 't'];

    /** Heading level => the short name in its classes and properties (sb-d-hl2c, --sb-hl2c…). */
    public const LEVELS = ['h1' => 'hl1', 'h2' => 'hl2', 'h3' => 'hl3', 'h4' => 'hl4', 'h5' => 'hl5', 'h6' => 'hl6'];

    public const WEIGHTS = [
        '100' => 'Thin (100)',
        '200' => 'Extra light (200)',
        '300' => 'Light (300)',
        '400' => 'Regular (400)',
        '500' => 'Medium (500)',
        '600' => 'Semi-bold (600)',
        '700' => 'Bold (700)',
        '800' => 'Extra bold (800)',
        '900' => 'Black (900)',
    ];

    public const STYLES = ['italic' => 'Italic', 'upper' => 'Capitals', 'underline' => 'Underline', 'smallcaps' => 'Small capitals'];

    public const ALIGNMENTS = ['left' => 'Left', 'center' => 'Centre', 'right' => 'Right', 'justify' => 'Justified'];

    public const SHADOWS = ['soft' => 'Soft', 'medium' => 'Medium', 'strong' => 'Strong'];

    public const LINK_UNDERLINES = ['always' => 'Always', 'hover' => 'When pointed at', 'never' => 'Never'];

    /** No em: body text nested in .block-text would grow at each level. */
    public const SIZE_UNITS = ['px', 'rem'];

    public const LINE_HEIGHT_UNITS = ['', 'px', 'em'];

    public const LETTER_SPACING_UNITS = ['px', 'em'];

    /** List bullet => label (CSS list-style-type). */
    public const BULLETS = [
        'disc' => 'Dots',
        'circle' => 'Circles',
        'square' => 'Squares',
        'none' => 'None',
    ];

    /** List numbering => label (CSS list-style-type). */
    public const NUMBERINGS = [
        'decimal' => '1, 2, 3',
        'decimal-leading-zero' => '01, 02, 03',
        'lower-alpha' => 'a, b, c',
        'upper-alpha' => 'A, B, C',
        'lower-roman' => 'i, ii, iii',
        'upper-roman' => 'I, II, III',
        'none' => 'None',
    ];

    public static function key(): string
    {
        return 'typography';
    }

    public static function toggles(): array
    {
        $path = 'design.'.self::key();

        return [
            PanelToggle::design('heading_text', __('Heading text'), [self::headingTabs()], 50, $path),
            PanelToggle::design('body_text', __('Body text'), [self::textFields('body', self::blockBackground())], 51, "{$path}.body"),
            PanelToggle::design('link_text', __('Link text'), [
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    SiteColour::make('colour')
                        ->label(__('Colour')),
                    SiteColour::make('hover_colour')
                        ->label(__('Colour when pointed at'))
                        ->hintIcon(PageBlock::HELP_ICON, __('The link\'s colour while the mouse is over it.')),
                    Select::make('weight')
                        ->label(__('Weight'))
                        ->options(self::translated(self::WEIGHTS))
                        ->placeholder(__('Same as the text')),
                    Select::make('underline')
                        ->label(__('Underline'))
                        ->options(self::translated(self::LINK_UNDERLINES))
                        ->placeholder(__('Site style')),
                ]),
            ], 52, "{$path}.links"),
            PanelToggle::design('list_text', __('Lists & quotes'), [
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    Select::make('bullet')
                        ->label(__('Bullets'))
                        ->options(self::translated(self::BULLETS))
                        ->placeholder(__('Site style')),
                    Select::make('numbering')
                        ->label(__('Numbers'))
                        ->options(self::translated(self::NUMBERINGS))
                        ->placeholder(__('Site style')),
                    Select::make('position')
                        ->label(__('Bullets and numbers sit'))
                        ->options(['outside' => __('Outside the text'), 'inside' => __('Inside the text')])
                        ->placeholder(__('Site style')),
                    DeviceValue::make('indent')
                        ->label(__('List indent'))
                        ->units(['px', 'em', 'rem'])
                        ->range(0, 200)
                        ->sameOnEveryScreen(),
                    DeviceValue::make('quote_border')
                        ->label(__('Quote line width'))
                        ->units(['px'])
                        ->range(0, 20)
                        ->sameOnEveryScreen(),
                    SiteColour::make('quote_colour')
                        ->label(__('Quote line colour')),
                ]),
            ], 53, "{$path}.lists"),
        ];
    }

    /**
     * Heading text: every heading at once (All headings, saved as "heading"), then each level on its own
     * (H1 to H6, saved as "h1"…"h6"), which wins over All headings for that level. Headings typed in rich text count.
     */
    private static function headingTabs(): Tabs
    {
        return Tabs::make()
            ->contained(false)
            ->tabs([
                Tab::make(__('All'))
                    ->schema([Group::make()->statePath('heading')->schema([self::textFields('heading', self::blockBackground())])]),
                ...array_map(
                    fn (string $level): Tab => Tab::make(strtoupper($level))
                        ->schema([Group::make()->statePath($level)->schema([self::textFields($level, self::blockBackground())])]),
                    array_keys(self::LEVELS),
                ),
            ]);
    }

    /**
     * The block's own background colour, from a text's settings (data.design.typography.{text}), for the contrast
     * warning: only a custom single colour, as the standard backgrounds already pick a readable text colour.
     */
    private static function blockBackground(): Closure
    {
        return fn (Get $get): ?string => $get('../../../appearance.background') === 'custom' ? $get('../../../appearance.background_color') : null;
    }

    /**
     * A text's settings: font, weight, size, line height, letter spacing, alignment, style, colour (and its hover
     * colour) and shadow. Also each text part of a block's items (Items).
     */
    public static function textFields(string $text, ?Closure $background = null): Grid
    {
        return Grid::make(['default' => 1, 'sm' => 2])
            ->schema([
                Select::make('font')
                    ->label(__('Font'))
                    ->hintIcon(PageBlock::HELP_ICON, __('One of the fonts stored on this site (Branding › Typography). Blank: the site\'s own.'))
                    ->options(fn (): array => app(Branding::class)->availableFonts())
                    ->placeholder(__('Site font')),
                Select::make('weight')
                    ->label(__('Weight'))
                    ->hintIcon(PageBlock::HELP_ICON, __('How thick the letters are. Not every font has every weight.'))
                    ->options(self::translated(self::WEIGHTS))
                    ->placeholder(__('Site style')),
                DeviceValue::make('size')
                    ->label(__('Size'))
                    ->hintIcon(PageBlock::HELP_ICON, __('Replaces Heading size or Text size when set. Can be smaller on tablets and phones.'))
                    ->units(self::SIZE_UNITS)
                    ->range(0, 200)
                    ->columnSpanFull(),
                DeviceValue::make('line_height')
                    ->label(__('Line height'))
                    ->hintIcon(PageBlock::HELP_ICON, __('The space between lines. A plain number is times the text size: 1.5 is roomy, 1.1 is tight.'))
                    ->units(self::LINE_HEIGHT_UNITS)
                    ->range(0, 200, 0.05)
                    ->columnSpanFull(),
                DeviceValue::make('letter_spacing')
                    ->label(__('Letter spacing'))
                    ->hintIcon(PageBlock::HELP_ICON, __('Extra space between letters. Below 0 squeezes them together.'))
                    ->units(self::LETTER_SPACING_UNITS)
                    ->range(-20, 50, 0.1)
                    ->columnSpanFull(),
                ToggleButtons::make('align')
                    ->label(__('Alignment'))
                    ->options(self::translated(self::ALIGNMENTS))
                    ->icons(['left' => 'heroicon-m-bars-3-bottom-left', 'center' => 'heroicon-m-bars-3', 'right' => 'heroicon-m-bars-3-bottom-right', 'justify' => 'heroicon-m-bars-4'])
                    ->inline()
                    ->grouped()
                    // Icons only: four labelled buttons don't fit the panel.
                    ->hiddenButtonLabels()
                    ->columnSpanFull(),
                ToggleButtons::make('style')
                    ->label(__('Style'))
                    ->options(self::translated(self::STYLES))
                    ->multiple()
                    ->inline()
                    ->columnSpanFull(),
                // The colour and its hover value share a cell: only one shows at a time.
                Group::make()->gap(false)->schema([
                    HoverMode::normal(SiteColour::make('colour')
                        ->label(__('Colour'))
                        ->hintIcon(PageBlock::HELP_ICON, __('Replaces the automatic dark or light text colour.'))
                        // Warns when it's hard to read on the colour behind it, where that's one colour.
                        ->when($background !== null, fn (SiteColour $field): SiteColour => $field->readableOn($background))),
                    HoverMode::only(SiteColour::make('colour_hover')
                        ->label(__('Colour on hover'))),
                ]),
                Select::make('shadow')
                    ->label(__('Shadow'))
                    ->hintIcon(PageBlock::HELP_ICON, __('A soft shadow behind the letters, which helps text stand out on a picture.'))
                    ->options(self::translated(self::SHADOWS))
                    ->placeholder(__('None')),
            ]);
    }

    public static function resolve(array $values, bool $hasPanel): DesignOutput
    {
        $output = new DesignOutput;

        foreach ([...self::TEXTS, ...self::LEVELS] as $text => $short) {
            self::addText($output, $values[$text] ?? null, $short);
        }

        $links = is_array($values['links'] ?? null) ? $values['links'] : [];

        if (($colour = DesignValue::colour($links['colour'] ?? null)) !== null) {
            $output->addClass('section', 'sb-d-lc')->setProperty('section', '--sb-lc', $colour);
        }

        if (($colour = DesignValue::colour($links['hover_colour'] ?? null)) !== null) {
            $output->addClass('section', 'sb-d-lch')->setProperty('section', '--sb-lch', $colour);
        }

        if (($weight = DesignValue::choice($links['weight'] ?? null, self::WEIGHTS)) !== null) {
            $output->addClass('section', 'sb-d-lw')->setProperty('section', '--sb-lw', $weight);
        }

        $lists = is_array($values['lists'] ?? null) ? $values['lists'] : [];

        if (($bullet = DesignValue::choice($lists['bullet'] ?? null, self::BULLETS)) !== null) {
            $output->addClass('section', 'sb-d-ul')->setProperty('section', '--sb-ul', $bullet);
        }

        if (($numbering = DesignValue::choice($lists['numbering'] ?? null, self::NUMBERINGS)) !== null) {
            $output->addClass('section', 'sb-d-ol')->setProperty('section', '--sb-ol', $numbering);
        }

        if (($position = DesignValue::choice($lists['position'] ?? null, ['outside' => true, 'inside' => true])) !== null) {
            $output->addClass('section', "sb-d-list-{$position}");
        }

        DesignValue::addLength($output, 'section', 'li-indent', $lists['indent'] ?? null, ['px', 'em', 'rem'], 0, 200);
        DesignValue::addLength($output, 'section', 'quote-bw', $lists['quote_border'] ?? null, ['px'], 0, 20);

        if (($colour = DesignValue::colour($lists['quote_colour'] ?? null)) !== null) {
            $output->addClass('section', 'sb-d-quote-bc')->setProperty('section', '--sb-quote-bc', $colour);
        }

        if (($underline = DesignValue::choice($links['underline'] ?? null, self::LINK_UNDERLINES)) !== null) {
            $output->addClass('section', "sb-d-l-underline-{$underline}");
        }

        return $output;
    }

    /**
     * One text's settings (textFields()) as section classes and properties named after $short: sb-d-{short}f and
     * --sb-{short}f for its font, and so on. The CSS decides what they reach (typography.css, items.css).
     */
    public static function addText(DesignOutput $output, mixed $settings, string $short): void
    {
        $settings = is_array($settings) ? $settings : [];

        $font = $settings['font'] ?? null;
        $fonts = app(Branding::class)->availableFonts();

        if (is_string($font) && isset($fonts[$font])) {
            $family = trim((string) preg_replace('/[^A-Za-z0-9 ]/', '', app(Branding::class)->fontFamily($font)));

            if ($family !== '') {
                $output->addFont($font)
                    ->addClass('section', "sb-d-{$short}f")
                    ->setProperty('section', "--sb-{$short}f", "'{$family}', ui-sans-serif, system-ui, sans-serif");
            }
        }

        if (($weight = DesignValue::choice($settings['weight'] ?? null, self::WEIGHTS)) !== null) {
            $output->addClass('section', "sb-d-{$short}w")->setProperty('section', "--sb-{$short}w", $weight);
        }

        foreach (is_array($settings['style'] ?? null) ? $settings['style'] : [] as $style) {
            if (($style = DesignValue::choice($style, self::STYLES)) !== null) {
                $output->addClass('section', "sb-d-{$short}-{$style}");
            }
        }

        DesignValue::addLength($output, 'section', "{$short}s", $settings['size'] ?? null, self::SIZE_UNITS, 0, 200);
        DesignValue::addLength($output, 'section', "{$short}lh", $settings['line_height'] ?? null, self::LINE_HEIGHT_UNITS, 0, 200);
        DesignValue::addLength($output, 'section', "{$short}ls", $settings['letter_spacing'] ?? null, self::LETTER_SPACING_UNITS, -20, 50);

        if (($align = DesignValue::choice($settings['align'] ?? null, self::ALIGNMENTS)) !== null) {
            $output->addClass('section', "sb-d-{$short}-align-{$align}");
        }

        if (($colour = DesignValue::colour($settings['colour_hover'] ?? null)) !== null) {
            $output->addClass('section', "sb-d-{$short}c-h")->setProperty('section', "--sb-{$short}c-h", $colour);
        }

        if (($colour = DesignValue::colour($settings['colour'] ?? null)) !== null) {
            $output->addClass('section', "sb-d-{$short}c")->setProperty('section', "--sb-{$short}c", $colour);
        }

        if (($shadow = DesignValue::choice($settings['shadow'] ?? null, self::SHADOWS)) !== null) {
            $output->addClass('section', "sb-d-{$short}-shadow-{$shadow}");
        }
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
