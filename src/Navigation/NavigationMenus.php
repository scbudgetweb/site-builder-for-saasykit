<?php

namespace ScBudgetWeb\SiteBuilder\Navigation;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ScBudgetWeb\SiteBuilder\Pages\PageService;
use ScBudgetWeb\SiteBuilder\SiteBuilder;
use ScBudgetWeb\SiteBuilder\Support\IconLibrary;
use Throwable;

/**
 * The header, footer and mobile menus (Admin › Website › Navigation), stored as three settings (navigation.header,
 * navigation.footer, navigation.mobile). With nothing saved, the defaults are the theme's standard links that apply to
 * the site (Link::isAvailable()). Once saved, menus are the site's own: a package update or a newly registered link
 * never changes them, and Reset to default saves the standard menus in their place.
 *
 * A saved menu item is ['type' => page|builtin|url|none, 'page_id', 'link', 'url', 'label', 'new_tab', 'children' => [...]]
 * (children and "none" only in the header). A header item can instead open a mega menu: 'menu' => 'mega', 'columns' =>
 * [['heading', 'links' => [... + 'description', 'icon']]] and an optional 'promo' panel. Items are re-checked every time
 * they're resolved, so a hand-edited setting can't add a javascript: link, a missing page or an unknown icon, and a
 * setting of the wrong shape reads as the defaults (lesson 9).
 */
class NavigationMenus
{
    public const HEADER_KEY = 'navigation.header';

    public const FOOTER_KEY = 'navigation.footer';

    /** The mobile menu's settings: where it opens from, and the logo and tagline at its top. */
    public const MOBILE_KEY = 'navigation.mobile';

    /** Mobile menu opens from => label. */
    public const MOBILE_OPEN_FROM = [
        'top' => 'The top (slides down)',
        'right' => 'The right (slides in)',
    ];

    public const TYPE_PAGE = 'page';

    public const TYPE_BUILTIN = 'builtin';

    public const TYPE_URL = 'url';

    /** A header item that only opens its dropdown or mega menu. */
    public const TYPE_NONE = 'none';

    public const TYPES = [self::TYPE_PAGE, self::TYPE_BUILTIN, self::TYPE_URL, self::TYPE_NONE];

    public const MENU_DROPDOWN = 'dropdown';

    public const MENU_MEGA = 'mega';

    public const MAX_HEADER_ITEMS = 6;

    public const MAX_DROPDOWN_ITEMS = 8;

    public const MAX_MEGA_COLUMNS = 4;

    public const MAX_MEGA_LINKS = 8;

    public const MAX_FOOTER_COLUMNS = 4;

    public const MAX_FOOTER_LINKS = 10;

    /** The same rule as block buttons. */
    public const URL_PATTERN = '/^(https?:\/\/|\/|#|mailto:|tel:)/i';

    public function __construct(private SiteBuilder $siteBuilder, private PageService $pages) {}

    /**
     * The package's built-in links, for SaaSykit's public pages. A link whose route doesn't exist never shows.
     *
     * @return array<int, Link>
     */
    public static function standardLinks(): array
    {
        return [
            Link::make('home', 'Home', fn (): string => route('home')),
            Link::make('pricing', 'Pricing', fn (): string => route('home').'#pricing')
                ->description('the plans on the home page')
                ->availableWhen(fn (): bool => app(self::class)->homeHasAnchor('pricing')),
            Link::make('blog', 'Blog', fn (): string => route('blog')),
            Link::make('roadmap', 'Roadmap', fn (): string => route('roadmap'))
                ->description('only while the roadmap is switched on')
                ->availableWhen(fn (): bool => (bool) config('app.roadmap_enabled')),
            Link::make('login', 'Log in', fn (): string => route('login')),
            Link::make('register', 'Create account', fn (): string => route('register')),
            Link::make('dashboard', 'Dashboard', fn (): string => route('dashboard'))
                ->description('the customer dashboard; asks visitors to log in'),
            Link::make('privacy_policy', 'Privacy Policy', fn (): string => route('privacy-policy')),
            Link::make('terms_of_service', 'Terms of Service', fn (): string => route('terms-of-service')),
        ];
    }

    /**
     * The built-in links that apply to this site, for the admin picker, as key => "Label (note)". A menu item's current
     * choice is kept even when it no longer applies (or was removed), marked as hidden, so the item still loads and
     * saves without losing it.
     *
     * @return array<string, string>
     */
    public function builtinOptions(?string $current = null): array
    {
        $options = array_map(
            fn (Link $link): string => $link->descriptionText() ? $link->label().' ('.$link->descriptionText().')' : $link->label(),
            array_filter($this->siteBuilder->registeredLinks(), fn (Link $link): bool => $link->isAvailable()),
        );

        if (is_string($current) && $current !== '' && ! isset($options[$current])) {
            $options[$current] = __(':link (not available on this site, so hidden)', [
                'link' => $this->siteBuilder->link($current)?->label() ?? Str::headline($current),
            ]);
        }

        return $options;
    }

    /**
     * Whether the home page visitors see has a part with this id, e.g. "pricing" for links to /#pricing: a block with
     * that anchor on the page replacing the home page, or otherwise an id="…" in the home view's template (the theme's
     * home and SaaSykit's both have id="pricing").
     */
    public function homeHasAnchor(string $anchor): bool
    {
        $homePage = $this->pages->forSlot('home');

        if ($homePage !== null) {
            return $homePage->hasAnchor($anchor);
        }

        try {
            $template = File::get(app('view')->getFinder()->find('home'));
        } catch (Throwable) {
            return false;
        }

        return preg_match('/\bid=["\']'.preg_quote($anchor, '/').'["\']/', $template) === 1;
    }

    /**
     * The header's links, ready to render.
     *
     * @return array<int, array{label: string, url: ?string, new_tab: bool, description: ?string, icon: ?string, children: array<int, array<string, mixed>>, mega: ?array{columns: array<int, array{heading: string, links: array<int, array<string, mixed>>}>, promo: ?array{heading: string, text: string, button_label: ?string, button_url: ?string}}}>
     */
    public function header(): array
    {
        return $this->resolveItems($this->headerItems(), allowChildren: true);
    }

    /**
     * The footer's tagline, link columns and copyright line, ready to render.
     *
     * @return array{tagline: string, copyright: string, columns: array<int, array{heading: string, links: array<int, array<string, mixed>>}>}
     */
    public function footer(): array
    {
        $footer = $this->footerSettings();

        $columns = collect($footer['columns'])
            ->filter(fn (mixed $column): bool => is_array($column))
            ->map(fn (array $column): array => [
                'heading' => trim((string) ($column['heading'] ?? '')),
                'links' => $this->resolveItems(is_array($column['links'] ?? null) ? $column['links'] : [], allowChildren: false),
            ])
            ->filter(fn (array $column): bool => $column['links'] !== [])
            ->take(self::MAX_FOOTER_COLUMNS)
            ->values()
            ->all();

        $copyright = filled($footer['copyright']) ? $footer['copyright'] : self::defaultCopyright();

        return [
            'tagline' => trim((string) $footer['tagline']),
            'copyright' => strtr($copyright, ['{year}' => date('Y'), '{site_name}' => (string) config('app.name')]),
            'columns' => $columns,
        ];
    }

    /**
     * The saved header items (or the defaults), as the admin form edits them.
     *
     * @return array<int|string, mixed>
     */
    public function headerItems(): array
    {
        $saved = $this->siteBuilder->settings()->get(self::HEADER_KEY);

        return is_array($saved) ? $saved : $this->standardHeader();
    }

    /**
     * The saved footer (or the defaults), as the admin form edits it.
     *
     * @return array{tagline: ?string, copyright: ?string, columns: array<int|string, mixed>}
     */
    public function footerSettings(): array
    {
        $saved = $this->siteBuilder->settings()->get(self::FOOTER_KEY);

        if (! is_array($saved)) {
            return $this->standardFooter();
        }

        return [
            'tagline' => is_string($saved['tagline'] ?? null) ? $saved['tagline'] : null,
            'copyright' => is_string($saved['copyright'] ?? null) ? $saved['copyright'] : null,
            'columns' => is_array($saved['columns'] ?? null) ? $saved['columns'] : [],
        ];
    }

    /**
     * The mobile menu's settings (standard: opens from the top, with the logo).
     *
     * @return array{open_from: string, show_logo: bool, tagline: string}
     */
    public function mobile(): array
    {
        $saved = $this->siteBuilder->settings()->get(self::MOBILE_KEY);
        $saved = is_array($saved) ? $saved : [];

        return [
            'open_from' => array_key_exists($saved['open_from'] ?? '', self::MOBILE_OPEN_FROM) ? $saved['open_from'] : 'top',
            'show_logo' => (bool) ($saved['show_logo'] ?? true),
            'tagline' => is_string($saved['tagline'] ?? null) ? trim($saved['tagline']) : '',
        ];
    }

    /**
     * @param  array<int|string, mixed>  $headerItems
     * @param  array{tagline?: ?string, copyright?: ?string, columns?: array<int|string, mixed>}  $footer
     * @param  array{open_from?: ?string, show_logo?: mixed, tagline?: ?string}  $mobile
     */
    public function save(array $headerItems, array $footer, array $mobile): void
    {
        $this->siteBuilder->settings()->set([
            self::HEADER_KEY => array_slice($this->cleanItems($headerItems, allowChildren: true), 0, self::MAX_HEADER_ITEMS),
            self::FOOTER_KEY => [
                'tagline' => trim((string) ($footer['tagline'] ?? '')),
                'copyright' => trim((string) ($footer['copyright'] ?? '')) ?: null,
                'columns' => collect(is_array($footer['columns'] ?? null) ? $footer['columns'] : [])
                    ->filter(fn (mixed $column): bool => is_array($column))
                    ->map(fn (array $column): array => [
                        'heading' => trim((string) ($column['heading'] ?? '')),
                        'links' => array_slice($this->cleanItems(is_array($column['links'] ?? null) ? $column['links'] : [], allowChildren: false), 0, self::MAX_FOOTER_LINKS),
                    ])
                    ->take(self::MAX_FOOTER_COLUMNS)
                    ->values()
                    ->all(),
            ],
            self::MOBILE_KEY => [
                'open_from' => array_key_exists($mobile['open_from'] ?? '', self::MOBILE_OPEN_FROM) ? $mobile['open_from'] : 'top',
                'show_logo' => (bool) ($mobile['show_logo'] ?? true),
                'tagline' => trim((string) ($mobile['tagline'] ?? '')),
            ],
        ]);
    }

    /**
     * Replaces the header, footer and mobile menu with the standard ones (the standard links that apply to this site),
     * saved as the site's own, so later package updates or newly available links don't change them.
     */
    public function reset(): void
    {
        $this->save($this->standardHeader(), $this->standardFooter(), self::defaultMobile());
    }

    /**
     * What Reset to default puts in the header and footer, for its confirmation: the link labels, in order.
     *
     * @return array{header: array<int, string>, footer: array<int, string>}
     */
    public function standardMenuLabels(): array
    {
        $labels = fn (array $items): array => array_values(array_filter(array_map(
            fn (array $item): ?string => is_string($item['link'] ?? null) ? $this->siteBuilder->link($item['link'])?->label() : null,
            $items,
        )));

        return [
            'header' => $labels($this->standardHeader()),
            'footer' => $labels(array_merge([], ...array_map(fn (array $column): array => $column['links'], $this->standardFooter()['columns']))),
        ];
    }

    /**
     * The standard header: defaultHeader() without the links that don't apply to this site or were removed.
     *
     * @return array<int, array<string, mixed>>
     */
    public function standardHeader(): array
    {
        return $this->withoutUnavailableLinks(self::defaultHeader());
    }

    /**
     * The standard footer: defaultFooter() without the links that don't apply to this site or were removed (and any
     * column left empty).
     *
     * @return array{tagline: ?string, copyright: null, columns: array<int, array{heading: string, links: array<int, array<string, mixed>>}>}
     */
    public function standardFooter(): array
    {
        $footer = self::defaultFooter();
        $footer['columns'] = array_values(array_filter(
            array_map(fn (array $column): array => [...$column, 'links' => $this->withoutUnavailableLinks($column['links'])], $footer['columns']),
            fn (array $column): bool => $column['links'] !== [],
        ));

        return $footer;
    }

    /**
     * The standard mobile menu: opens from the top, with the logo and no tagline.
     *
     * @return array{open_from: string, show_logo: bool, tagline: string}
     */
    public static function defaultMobile(): array
    {
        return ['open_from' => 'top', 'show_logo' => true, 'tagline' => ''];
    }

    /**
     * Whether a menu link points at the page being viewed (links to a part of a page, e.g. /#pricing, never are).
     */
    public function isCurrent(?string $url): bool
    {
        if ($url === null || str_contains($url, '#') || ! preg_match('/^(https?:\/\/|\/)/i', $url)) {
            return false;
        }

        return rtrim(url($url), '/') === rtrim(url()->current(), '/');
    }

    /**
     * The theme's standard header links, before leaving out those that don't apply (see standardHeader()).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function defaultHeader(): array
    {
        return [
            self::builtin('pricing'),
            self::builtin('blog'),
            self::builtin('roadmap'),
        ];
    }

    /**
     * The theme's standard footer: the site description and one column of links, before leaving out those that don't
     * apply (see standardFooter()).
     *
     * @return array{tagline: ?string, copyright: null, columns: array<int, array{heading: string, links: array<int, array<string, mixed>>}>}
     */
    public static function defaultFooter(): array
    {
        $description = config('app.description');

        return [
            'tagline' => is_string($description) ? $description : null,
            'copyright' => null,
            'columns' => [
                [
                    'heading' => '',
                    'links' => [
                        self::builtin('blog'),
                        self::builtin('privacy_policy'),
                        self::builtin('terms_of_service'),
                    ],
                ],
            ],
        ];
    }

    public static function defaultCopyright(): string
    {
        return __('© {year} {site_name}. All rights reserved.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function withoutUnavailableLinks(array $items): array
    {
        return array_values(array_filter(
            $items,
            fn (array $item): bool => ($item['type'] ?? null) !== self::TYPE_BUILTIN || ($this->siteBuilder->link($item['link'] ?? null)?->isAvailable() ?? false),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private static function builtin(string $key, ?string $label = null): array
    {
        return ['type' => self::TYPE_BUILTIN, 'link' => $key, 'label' => $label, 'new_tab' => false];
    }

    /**
     * @param  array<int|string, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function resolveItems(array $items, bool $allowChildren): array
    {
        return collect($items)
            ->map(fn (mixed $item): ?array => is_array($item) ? $this->resolveItem($item, $allowChildren) : null)
            ->filter()
            ->take($allowChildren ? self::MAX_HEADER_ITEMS : self::MAX_FOOTER_LINKS)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function resolveItem(array $item, bool $allowChildren): ?array
    {
        $type = $item['type'] ?? null;
        $label = trim((string) ($item['label'] ?? ''));
        $mega = $allowChildren && ($item['menu'] ?? null) === self::MENU_MEGA ? $this->resolveMega($item) : null;
        $children = $allowChildren && $mega === null && is_array($item['children'] ?? null)
            ? array_slice($this->resolveItems($item['children'], allowChildren: false), 0, self::MAX_DROPDOWN_ITEMS)
            : [];

        [$url, $defaultLabel] = match ($type) {
            self::TYPE_PAGE => $this->pageLink($item['page_id'] ?? null),
            self::TYPE_BUILTIN => $this->builtinLink($item['link'] ?? null),
            self::TYPE_URL => [is_string($item['url'] ?? null) && preg_match(self::URL_PATTERN, $item['url']) ? $item['url'] : null, null],
            default => [null, null],
        };

        $label = $label !== '' ? $label : $defaultLabel;

        // A link needs somewhere to go; a "none" item only makes sense as a dropdown or mega menu.
        $isUsable = $label !== null && ($url !== null || ($allowChildren && $type === self::TYPE_NONE && ($children !== [] || $mega !== null)));

        if (! $isUsable) {
            return null;
        }

        return [
            'label' => $label,
            'url' => $url,
            'new_tab' => (bool) ($item['new_tab'] ?? false) && $url !== null,
            'description' => is_string($item['description'] ?? null) && trim($item['description']) !== '' ? Str::limit(trim($item['description']), 120) : null,
            'icon' => is_string($item['icon'] ?? null) && IconLibrary::isValid($item['icon']) ? $item['icon'] : null,
            'children' => $children,
            'mega' => $mega,
        ];
    }

    /**
     * A mega menu's link columns and optional promo panel, or null when it has no usable links.
     *
     * @param  array<string, mixed>  $item
     * @return array{columns: array<int, array{heading: string, links: array<int, array<string, mixed>>}>, promo: ?array{heading: string, text: string, button_label: ?string, button_url: ?string}}|null
     */
    private function resolveMega(array $item): ?array
    {
        $columns = collect(is_array($item['columns'] ?? null) ? $item['columns'] : [])
            ->filter(fn (mixed $column): bool => is_array($column))
            ->map(fn (array $column): array => [
                'heading' => trim((string) ($column['heading'] ?? '')),
                'links' => array_slice($this->resolveItems(is_array($column['links'] ?? null) ? $column['links'] : [], allowChildren: false), 0, self::MAX_MEGA_LINKS),
            ])
            ->filter(fn (array $column): bool => $column['links'] !== [])
            ->take(self::MAX_MEGA_COLUMNS)
            ->values()
            ->all();

        if ($columns === []) {
            return null;
        }

        $promo = is_array($item['promo'] ?? null) ? $item['promo'] : [];
        $buttonUrl = is_string($promo['button_url'] ?? null) && preg_match(self::URL_PATTERN, $promo['button_url']) ? $promo['button_url'] : null;
        $buttonLabel = trim((string) ($promo['button_label'] ?? ''));
        $promo = [
            'heading' => trim((string) ($promo['heading'] ?? '')),
            'text' => trim((string) ($promo['text'] ?? '')),
            'button_label' => $buttonUrl !== null && $buttonLabel !== '' ? $buttonLabel : null,
            'button_url' => $buttonLabel !== '' ? $buttonUrl : null,
        ];

        return [
            'columns' => $columns,
            'promo' => $promo['heading'] !== '' || $promo['text'] !== '' ? $promo : null,
        ];
    }

    /**
     * A published page's URL and title. A draft, binned or deleted page has none, so the menu skips it.
     *
     * @return array{0: ?string, 1: ?string} url, default label
     */
    private function pageLink(mixed $pageId): array
    {
        $page = is_numeric($pageId) ? ($this->pages->publishedPageLinks()[(int) $pageId] ?? null) : null;

        return $page ? [$page['url'], $page['title']] : [null, null];
    }

    /**
     * @return array{0: ?string, 1: ?string} url, default label
     */
    private function builtinLink(mixed $key): array
    {
        $link = is_string($key) ? $this->siteBuilder->link($key) : null;
        $url = $link?->url();

        return $url !== null ? [$url, $link->label()] : [null, null];
    }

    /**
     * Keeps only the known fields of each item (the admin form also holds Repeater keys).
     *
     * @param  array<int|string, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function cleanItems(array $items, bool $allowChildren): array
    {
        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item) && in_array($item['type'] ?? null, $allowChildren ? self::TYPES : array_diff(self::TYPES, [self::TYPE_NONE]), true))
            ->map(fn (array $item): array => array_filter([
                ...$this->cleanMega($item, $allowChildren),
                'type' => $item['type'],
                'page_id' => $item['type'] === self::TYPE_PAGE && is_numeric($item['page_id'] ?? null) ? (int) $item['page_id'] : null,
                'link' => $item['type'] === self::TYPE_BUILTIN && is_string($item['link'] ?? null) ? $item['link'] : null,
                'url' => $item['type'] === self::TYPE_URL && is_string($item['url'] ?? null) ? trim($item['url']) : null,
                'label' => filled($item['label'] ?? null) ? trim((string) $item['label']) : null,
                'new_tab' => (bool) ($item['new_tab'] ?? false),
                'description' => ! $allowChildren && filled($item['description'] ?? null) ? trim((string) $item['description']) : null,
                'icon' => ! $allowChildren && filled($item['icon'] ?? null) && is_string($item['icon']) ? $item['icon'] : null,
                'children' => $allowChildren && ($item['menu'] ?? null) !== self::MENU_MEGA
                    ? array_slice($this->cleanItems(is_array($item['children'] ?? null) ? $item['children'] : [], allowChildren: false), 0, self::MAX_DROPDOWN_ITEMS)
                    : null,
            ], fn (mixed $value): bool => $value !== null))
            ->values()
            ->all();
    }

    /**
     * A header item's mega menu fields (none for links in a column, or for a plain dropdown).
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function cleanMega(array $item, bool $allowChildren): array
    {
        if (! $allowChildren || ($item['menu'] ?? null) !== self::MENU_MEGA) {
            return [];
        }

        $promo = is_array($item['promo'] ?? null) ? $item['promo'] : [];

        return [
            'menu' => self::MENU_MEGA,
            'columns' => collect(is_array($item['columns'] ?? null) ? $item['columns'] : [])
                ->filter(fn (mixed $column): bool => is_array($column))
                ->map(fn (array $column): array => [
                    'heading' => trim((string) ($column['heading'] ?? '')),
                    'links' => array_slice($this->cleanItems(is_array($column['links'] ?? null) ? $column['links'] : [], allowChildren: false), 0, self::MAX_MEGA_LINKS),
                ])
                ->take(self::MAX_MEGA_COLUMNS)
                ->values()
                ->all(),
            'promo' => array_map(fn (mixed $value): string => trim((string) $value), array_intersect_key($promo, array_flip(['heading', 'text', 'button_label', 'button_url']))),
        ];
    }
}
