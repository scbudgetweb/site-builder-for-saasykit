<?php

namespace ScBudgetWeb\SiteBuilder\Templates;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use ScBudgetWeb\SiteBuilder\Blocks\PageRenderer;
use ScBudgetWeb\SiteBuilder\Branding\Branding;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * How the site header behaves as the page scrolls: the standard header's in Branding › Header & footer, a header
 * template's in its own settings (the visual builder's Header settings).
 *  - Stays at the top: no, always, or only when scrolling back up (it slides away while scrolling down);
 *  - once scrolled (when it stays at the top): it shrinks, and gets a shadow;
 *  - Over the first section: see-through over a Hero or Banner that opens the page, with light or dark text and
 *    optionally the other Branding logo, then solid once scrolled. Elsewhere the header shows as normal.
 *
 * The header gets classes and data attributes (wrapper()); theme/partials/header-script adds the scroll states and
 * resources/css/header.css draws them. Without JavaScript, a header that stays at the top still does (CSS sticky).
 */
class HeaderBehaviour
{
    public const STICKY = [
        'none' => 'No: it scrolls away with the page',
        'always' => 'Yes, always',
        'scroll_up' => 'Only when scrolling back up',
    ];

    public const OVER_TEXT = [
        'light' => 'Light (for a dark or busy section)',
        'dark' => 'Dark (for a pale section)',
    ];

    public const OVER_LOGO = [
        'same' => 'The same logo',
        'dark' => 'The logo for dark backgrounds',
        'light' => 'The logo for light backgrounds',
    ];

    /**
     * The request attribute set by a banner that opens the page (the banner component's "opening"), for theme pages
     * such as the home page and the blog, which aren't site builder pages.
     */
    public const OPENING_BANNER_ATTRIBUTE = 'site-builder.opening-banner';

    /** The page whose first Banner or Hero opens what's shown, when it isn't the page being shown (a template's sample page). */
    public const OPENING_PAGE_ATTRIBUTE = 'site-builder.opening-page';

    /**
     * @return array{sticky: string, shrink: bool, shadow: bool, over: bool, over_text: string, over_logo: string}
     */
    public static function defaults(): array
    {
        return [
            'sticky' => 'none',
            'shrink' => false,
            'shadow' => true,
            'over' => false,
            'over_text' => 'light',
            'over_logo' => 'same',
        ];
    }

    /**
     * Saved settings with anything unexpected replaced by its default.
     *
     * @return array{sticky: string, shrink: bool, shadow: bool, over: bool, over_text: string, over_logo: string}
     */
    public static function clean(mixed $values): array
    {
        $values = is_array($values) ? $values : [];
        $defaults = self::defaults();
        $choice = fn (string $key, array $options): string => is_string($values[$key] ?? null) && isset($options[$values[$key]]) ? $values[$key] : $defaults[$key];
        $toggle = fn (string $key): bool => array_key_exists($key, $values) ? in_array($values[$key], [true, 1, '1'], true) : $defaults[$key];

        return [
            'sticky' => $choice('sticky', self::STICKY),
            'shrink' => $toggle('shrink'),
            'shadow' => $toggle('shadow'),
            'over' => $toggle('over'),
            'over_text' => $choice('over_text', self::OVER_TEXT),
            'over_logo' => $choice('over_logo', self::OVER_LOGO),
        ];
    }

    /**
     * The settings for the header on this page: the header template's that applies, or Branding's.
     *
     * @return array{sticky: string, shrink: bool, shadow: bool, over: bool, over_text: string, over_logo: string}
     */
    public static function forHeader(): array
    {
        $template = app(Templates::class)->for('header');

        return self::clean($template !== null ? $template['settings'] : app(Branding::class)->get(Branding::HEADER_BEHAVIOUR));
    }

    /**
     * The fields, without a state path (Branding and the template's settings each put them in their own).
     *
     * @return array<int, Select|Toggle>
     */
    public static function fields(bool $forTemplate = false): array
    {
        $stays = fn (Get $get): bool => in_array($get('sticky'), ['always', 'scroll_up'], true);
        $options = fn (array $options): array => array_map(fn (string $label): string => __($label), $options);

        return [
            Select::make('sticky')
                ->label(__('Stays at the top'))
                ->options($options(self::STICKY))
                ->default('none')
                ->selectablePlaceholder(false)
                ->live()
                ->helperText(__('"Only when scrolling back up" slides the header away while visitors read down the page, and back as soon as they scroll up.')),
            Toggle::make('shrink')
                ->label(__('Shrinks once the page scrolls'))
                ->helperText(__('A little shorter, with a smaller logo, so it takes less room.'))
                ->visible($stays),
            Toggle::make('shadow')
                ->label(__('Shadow once the page scrolls'))
                ->default(true)
                ->visible($stays),
            Toggle::make('over')
                ->label(__('Over the first section'))
                ->helperText($forTemplate
                    ? __('See-through over a Hero or Banner at the top of the page, then solid once the page scrolls. Pages that start with anything else show the header as normal. Blocks in the header with a background of their own keep it.')
                    : __('See-through over a Hero or Banner at the top of the page, then solid once the page scrolls. Pages that start with anything else show the header as normal.'))
                ->live(),
            Select::make('over_text')
                ->label(__('Text over the section'))
                ->options($options(self::OVER_TEXT))
                ->default('light')
                ->selectablePlaceholder(false)
                ->visible(fn (Get $get): bool => (bool) $get('over')),
            Select::make('over_logo')
                ->label(__('Logo over the section'))
                ->options($options(self::OVER_LOGO))
                ->default('same')
                ->selectablePlaceholder(false)
                ->helperText(__('The two logos are uploaded in Branding › Brand. Once the header turns solid, it shows its usual logo.'))
                ->visible(fn (Get $get): bool => (bool) $get('over')),
        ];
    }

    /**
     * Whether what's being shown opens with a Banner or Hero: the page's first block, its standard page banner, or a
     * theme page's own banner (OPENING_BANNER_ATTRIBUTE).
     */
    public static function pageOpensWithBanner(): bool
    {
        if (request()->attributes->get(self::OPENING_BANNER_ATTRIBUTE)) {
            return true;
        }

        $page = request()->attributes->get(Templates::PAGE_ATTRIBUTE);

        if (! $page instanceof Page) {
            return false;
        }

        $renderer = app(PageRenderer::class);

        return $renderer->opensWithBanner($page) || $renderer->showsPageBanner($page);
    }

    /**
     * Whether a Banner or Hero that's the first block of $page opens what's shown (so it leaves room for a header over it).
     */
    public static function isOpeningPage(mixed $page): bool
    {
        $opening = request()->attributes->get(self::OPENING_PAGE_ATTRIBUTE) ?? request()->attributes->get(Templates::PAGE_ATTRIBUTE);

        return $page instanceof Page && $page === $opening;
    }

    /**
     * The page under a header template on its visual builder canvas: a Hero, then enough text to scroll, so the
     * header's behaviour can be seen and tried. Never saved.
     */
    public static function samplePage(): Page
    {
        $text = '<p>'.e(__('This text is here so the page is long enough to scroll. Scroll down and back up to see what the header does: whether it stays at the top, shrinks, gets a shadow or turns solid.')).'</p>'
            .'<p>'.e(__('Your own pages show under the header in the same way. Change how it behaves under Header settings in the toolbar.')).'</p>';

        return (new Page)->forceFill([
            'title' => __('Sample page'),
            'slug' => 'theme-builder-sample',
            'show_page_banner' => false,
            'blocks' => [
                ['type' => 'hero', 'data' => [
                    'heading' => __('A sample page'),
                    'subtitle' => __('It\'s here so you can see how the header sits over the first section and behaves as you scroll. It isn\'t part of the template, and visitors never see it.'),
                ]],
                ['type' => 'text', 'data' => ['content' => '<h2>'.e(__('A section of text')).'</h2>'.$text]],
                ['type' => 'text', 'data' => ['content' => '<h2>'.e(__('Another section')).'</h2>'.$text]],
                ['type' => 'text', 'data' => ['content' => '<h2>'.e(__('And one more')).'</h2>'.$text]],
            ],
        ]);
    }

    /**
     * The header element's classes and data attributes, and whether it needs the header script.
     *
     * @param  array<string, mixed>  $behaviour  clean()'s keys
     * @return array{classes: array<int, string>, attributes: array<string, string>, needsScript: bool}
     */
    public static function wrapper(array $behaviour, bool $opensWithBanner): array
    {
        $behaviour = self::clean($behaviour);
        $stays = $behaviour['sticky'] !== 'none';
        $isOver = $behaviour['over'] && $opensWithBanner;
        $classes = array_keys(array_filter([
            'sb-header-sticky' => $stays,
            'sb-header-hides' => $behaviour['sticky'] === 'scroll_up',
            'sb-header-shrinks' => $stays && $behaviour['shrink'],
            'sb-header-shadows' => $stays && $behaviour['shadow'],
            'sb-header-over' => $isOver,
        ]));
        $attributes = [];

        if ($isOver) {
            $attributes['data-over-text'] = $behaviour['over_text'];
            $logo = $behaviour['over_logo'] === 'same' ? null : app(Branding::class)->logoUrl($behaviour['over_logo']);

            if ($logo !== null) {
                $attributes['data-over-logo'] = $logo;
            }
        }

        $needsScript = $classes !== [];

        if ($needsScript) {
            $attributes['data-sb-header'] = '';
        }

        return ['classes' => $classes, 'attributes' => $attributes, 'needsScript' => $needsScript];
    }
}
