<?php

namespace ScBudgetWeb\SiteBuilder\Templates;

use ScBudgetWeb\SiteBuilder\Navigation\NavigationMenus;

/**
 * The ready-made layouts a new header or footer template starts from (picked with small pictures when it's made):
 * "the standard one", rebuilt as blocks so nothing changes until it's edited, a few more, or nothing. Headers are a
 * Header bar in each of its styles (with a Top bar above it for one); footers are Sections on Branding's footer
 * colours holding the Logo, Footer links, Social follow and Copyright blocks, so links still come from Navigation and
 * colours from Branding.
 */
class TemplateLayouts
{
    public const BLANK = 'blank';

    public const STANDARD = 'standard';

    /** key => [label, description] */
    public const HEADERS = [
        self::STANDARD => ['The standard header', 'As the header is now: the logo on the left, the links, Log in and a button on the right.'],
        'links_centre' => ['Links in the middle', 'The logo on the left, the links in the middle and the buttons on the right.'],
        'centred_logo' => ['Logo in the middle', 'The logo in the middle, with the links on a row underneath.'],
        'split' => ['Logo between the links', 'Half the links on each side of the logo.'],
        'menu_only' => ['Logo and a menu button', 'A clean header whose menu button opens the mobile menu at every screen size.'],
        'top_bar' => ['Top bar and header', 'A slim bar with a short line, phone number, email and social icons above the standard header.'],
        self::BLANK => ['Start empty', 'No blocks: build it yourself.'],
    ];

    public const FOOTERS = [
        self::STANDARD => ['The standard footer', 'As the footer is now: the logo, tagline and social icons beside the link columns, with the copyright line underneath.'],
        'simple' => ['Simple', 'The copyright line and social icons in one slim bar.'],
        'columns' => ['Logo and link columns', 'The logo and tagline beside the link columns, then a bar with the copyright line and social icons.'],
        'centred' => ['Centred', 'The logo, tagline, social icons and copyright line, one under another in the middle.'],
        'cta' => ['Call to action, then columns', 'A call to action with a button, above the logo and link columns.'],
        self::BLANK => ['Start empty', 'No blocks: build it yourself.'],
    ];

    /**
     * The layouts for a template type, translated (none for a type without them).
     *
     * @return array<string, array{label: string, description: string}>
     */
    public static function options(?string $type): array
    {
        return array_map(fn (array $layout): array => ['label' => __($layout[0]), 'description' => __($layout[1])], match ($type) {
            'header' => self::HEADERS,
            'footer' => self::FOOTERS,
            default => [],
        });
    }

    public static function has(?string $type): bool
    {
        return self::options($type) !== [];
    }

    /**
     * The blocks a layout starts with ([] for Start empty, or an unknown layout).
     *
     * @return array<int, array{type: string, data: array<string, mixed>}>
     */
    public static function blocks(string $type, ?string $layout): array
    {
        return match ($type) {
            'header' => self::header($layout ?? self::STANDARD),
            'footer' => self::footer($layout ?? self::STANDARD),
            default => [],
        };
    }

    /**
     * @return array<int, array{type: string, data: array<string, mixed>}>
     */
    private static function header(string $layout): array
    {
        return match ($layout) {
            self::STANDARD => [self::block('site_header', ['style' => 'logo_left'])],
            'links_centre', 'centred_logo', 'split', 'menu_only' => [self::block('site_header', ['style' => $layout])],
            'top_bar' => [
                self::block('site_top_bar', ['text' => __('Open Monday to Friday, 9am to 5pm'), 'show_social' => true]),
                self::block('site_header', ['style' => 'logo_left']),
            ],
            default => [],
        };
    }

    /**
     * @return array<int, array{type: string, data: array<string, mixed>}>
     */
    private static function footer(string $layout): array
    {
        $footer = app(NavigationMenus::class)->footer();
        $tagline = $footer['tagline'] !== '' ? [self::block('text', ['content' => '<p>'.e($footer['tagline']).'</p>'])] : [];
        $hasColumns = $footer['columns'] !== [];
        $logo = fn (string $align = 'left'): array => self::block('site_logo', ['logo' => 'footer', 'align' => $align]);
        $social = fn (string $align = 'left'): array => self::block('social_follow', ['align' => $align]);
        $copyright = fn (string $align = 'left'): array => self::block('site_copyright', ['source' => 'navigation', 'align' => $align]);

        // The logo, tagline (and social icons) beside the link columns: on their own when there are no columns.
        $brandAndLinks = fn (array $brand): array => $hasColumns
            ? ['layout' => '1-2', 'columns' => [['blocks' => $brand], ['blocks' => [self::block('site_footer_links')]]]]
            : ['layout' => 'stacked', 'blocks' => $brand];

        return match ($layout) {
            self::STANDARD => [self::section([
                $brandAndLinks([$logo(), ...$tagline, $social()]),
                ['layout' => 'stacked', 'blocks' => [$copyright()]],
            ])],
            'simple' => [self::section([
                ['layout' => '2', 'align' => 'center', 'columns' => [['blocks' => [$copyright()]], ['blocks' => [$social('right')]]]],
            ], spacing: 'small')],
            'columns' => [self::section([
                $brandAndLinks([$logo(), ...$tagline]),
                ['layout' => '2', 'align' => 'center', 'columns' => [['blocks' => [$copyright()]], ['blocks' => [$social('right')]]]],
            ])],
            'centred' => [self::section([
                ['layout' => 'stacked', 'blocks' => [$logo('center'), ...array_map(fn (array $text): array => self::block('text', [...$text['data'], 'align' => 'center']), $tagline), $social('center'), $copyright('center')]],
            ])],
            'cta' => [
                self::block('call_to_action', [
                    'heading' => __('Ready to get started?'),
                    'text' => __('A line or two about what visitors get when they sign up.'),
                    'buttons' => [['label' => __('Get started'), 'url' => '/#pricing']],
                ]),
                self::section([
                    $brandAndLinks([$logo(), ...$tagline, $social()]),
                    ['layout' => 'stacked', 'blocks' => [$copyright()]],
                ]),
            ],
            default => [],
        };
    }

    /**
     * A Section on Branding's footer colours holding $rows.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{type: string, data: array<string, mixed>}
     */
    private static function section(array $rows, string $spacing = 'medium'): array
    {
        return self::block('section', [
            'gap' => 'medium',
            'appearance' => ['background' => 'footer', 'spacing' => $spacing],
            'rows' => $rows,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{type: string, data: array<string, mixed>}
     */
    private static function block(string $type, array $data = []): array
    {
        return ['type' => $type, 'data' => $data];
    }
}
