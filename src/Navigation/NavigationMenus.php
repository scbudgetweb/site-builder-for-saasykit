<?php

namespace ScBudgetWeb\SiteBuilder\Navigation;

use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
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

    /** A link in the copyright line: <a …>text</a>. */
    private const LINK_TAG_PATTERN = '#<a((?:\s[^>]*)?)>(.*?)</a\s*>#is';

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
     * "copyright" is plain text (each link replaced by its text); "copyrightHtml" keeps the links (linkedText()).
     *
     * @return array{tagline: string, copyright: string, copyrightHtml: HtmlString, columns: array<int, array{heading: string, links: array<int, array<string, mixed>>}>}
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
        $copyright = strtr($copyright, ['{year}' => date('Y'), '{site_name}' => (string) config('app.name')]);

        return [
            'tagline' => trim((string) $footer['tagline']),
            'copyright' => self::plainText($copyright),
            // Links take the footer's link colours from Branding, and stay underlined so they stand out from the text.
            'copyrightHtml' => self::linkedText($copyright, 'underline text-sb-footer-link transition-colors hover:text-sb-footer-link-hover focus-visible:text-sb-footer-link-hover'),
            'columns' => $columns,
        ];
    }

    /**
     * Text that may hold simple links, e.g. 'Web design by <a href="https://example.com">Example</a>', as HTML. A link's
     * address is checked like a menu link's (URL_PATTERN), and a link to another site opens in a new tab. Everything
     * else, including any other HTML, a link's own attributes and HTML inside its text, is shown as plain text. A link
     * whose address isn't allowed shows just its text. $linkClass styles the links.
     */
    public static function linkedText(string $text, string $linkClass = 'underline hover:no-underline'): HtmlString
    {
        // Text, then each link's attributes and text, in turn.
        $parts = preg_split(self::LINK_TAG_PATTERN, $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return new HtmlString(e($text, false));
        }

        $html = '';

        foreach (array_chunk($parts, 3) as $chunk) {
            $html .= e($chunk[0], false);

            if (isset($chunk[1], $chunk[2])) {
                $address = self::linkAddress($chunk[1]);
                $label = e($chunk[2], false);

                $html .= $address === null || preg_match(self::URL_PATTERN, $address) !== 1
                    ? $label
                    : '<a href="'.e($address).'" class="'.e($linkClass).'"'
                        .(preg_match('#^https?://#i', $address) === 1 ? ' target="_blank" rel="noopener"' : '')
                        .'>'.$label.'</a>';
            }
        }

        return new HtmlString($html);
    }

    /**
     * The same text with each link replaced by its text, for places that can't show links.
     */
    public static function plainText(string $text): string
    {
        return preg_replace(self::LINK_TAG_PATTERN, '$2', $text) ?? $text;
    }

    /**
     * The address of every link in the text, as typed (null for a link without one), to check before saving.
     *
     * @return array<int, ?string>
     */
    public static function linkAddresses(string $text): array
    {
        preg_match_all(self::LINK_TAG_PATTERN, $text, $matches);

        return array_map(fn (string $attributes): ?string => self::linkAddress($attributes), $matches[1] ?? []);
    }

    /**
     * The href of a link's attributes, decoded, or null when there isn't one.
     */
    private static function linkAddress(string $attributes): ?string
    {
        if (preg_match('/(?:^|\s)href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))/i', $attributes, $match) !== 1) {
            return null;
        }

        return trim(html_entity_decode($match[1] !== '' ? $match[1] : (($match[2] ?? '') !== '' ? $match[2] : ($match[3] ?? '')), ENT_QUOTES | ENT_HTML5));
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

        return is_array($saved) ? $this->cleanMobile($saved) : $this->standardMobile();
    }

    /**
     * @param  array<int|string, mixed>  $headerItems
     * @param  array{tagline?: ?string, copyright?: ?string, columns?: array<int|string, mixed>}  $footer
     * @param  array{open_from?: ?string, show_logo?: mixed, tagline?: ?string}  $mobile
     */
    public function save(array $headerItems, array $footer, array $mobile): void
    {
        $this->siteBuilder->settings()->set([
            self::HEADER_KEY => $this->cleanHeader($headerItems),
            self::FOOTER_KEY => $this->cleanFooter($footer),
            self::MOBILE_KEY => $this->cleanMobile($mobile),
        ]);
    }

    /**
     * Replaces the header, footer and mobile menu with the standard ones (the standard links that apply to this site),
     * saved as the site's own, so later package updates or newly available links don't change them.
     */
    public function reset(): void
    {
        $this->save($this->standardHeader(), $this->standardFooter(), $this->standardMobile());
    }

    /**
     * What Reset to default puts in the header and footer, for its confirmation: each link's label as it will show,
     * in order, with a dropdown's or mega menu's links in brackets, e.g. "Jobs (Find jobs, Post a job)".
     *
     * @return array{header: array<int, string>, footer: array<int, string>}
     */
    public function standardMenuLabels(): array
    {
        $describe = fn (array $item): string => ($inner = [
            ...array_column($item['children'], 'label'),
            ...array_merge([], ...array_map(fn (array $column): array => array_column($column['links'], 'label'), $item['mega']['columns'] ?? [])),
        ]) === [] ? $item['label'] : $item['label'].' ('.implode(', ', $inner).')';

        return [
            'header' => array_map($describe, $this->resolveItems($this->standardHeader(), allowChildren: true)),
            'footer' => array_column(array_merge([], ...array_map(
                fn (array $column): array => $this->resolveItems($column['links'], allowChildren: false),
                $this->standardFooter()['columns'],
            )), 'label'),
        ];
    }

    /**
     * The standard header: the app's (SiteBuilder::defaultMenus()) or the package's (defaultHeader()), checked like a
     * saved menu and without the links that don't apply to this site or were removed.
     *
     * @return array<int, array<string, mixed>>
     */
    public function standardHeader(): array
    {
        return $this->withoutUnavailableLinks($this->cleanHeader($this->siteBuilder->defaultMenu('header') ?? self::defaultHeader()));
    }

    /**
     * The standard footer: the app's or the package's (defaultFooter()), checked like a saved one, without the links
     * that don't apply to this site or were removed, and without any column left empty. With no tagline given, it's
     * the site description, as in the package's.
     *
     * @return array{tagline: string, copyright: ?string, columns: array<int, array{heading: string, links: array<int, array<string, mixed>>}>}
     */
    public function standardFooter(): array
    {
        $default = $this->siteBuilder->defaultMenu('footer') ?? self::defaultFooter();
        $footer = $this->cleanFooter(['tagline' => self::defaultFooter()['tagline'], ...$default]);
        $footer['columns'] = array_values(array_filter(
            array_map(fn (array $column): array => [...$column, 'links' => $this->withoutUnavailableLinks($column['links'])], $footer['columns']),
            fn (array $column): bool => $column['links'] !== [],
        ));

        return $footer;
    }

    /**
     * The standard mobile menu: the app's or the package's (defaultMobile()), checked like a saved one.
     *
     * @return array{open_from: string, show_logo: bool, tagline: string}
     */
    public function standardMobile(): array
    {
        return $this->cleanMobile([...self::defaultMobile(), ...($this->siteBuilder->defaultMenu('mobile') ?? [])]);
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
     * Leaves out built-in links that don't apply to this site or were removed, in dropdowns and mega menu columns too,
     * then any dropdown-only item ("none") or mega menu column left with nothing in it.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function withoutUnavailableLinks(array $items): array
    {
        $applies = fn (array $item): bool => ($item['type'] ?? null) !== self::TYPE_BUILTIN || ($this->siteBuilder->link($item['link'] ?? null)?->isAvailable() ?? false);
        $kept = [];

        foreach (array_filter($items, $applies) as $item) {
            if (isset($item['children'])) {
                $item['children'] = $this->withoutUnavailableLinks($item['children']);
            }

            if (isset($item['columns'])) {
                $item['columns'] = array_values(array_filter(
                    array_map(fn (array $column): array => [...$column, 'links' => $this->withoutUnavailableLinks($column['links'])], $item['columns']),
                    fn (array $column): bool => $column['links'] !== [],
                ));
            }

            if (($item['type'] ?? null) === self::TYPE_NONE && ($item['children'] ?? []) === [] && ($item['columns'] ?? []) === []) {
                continue;
            }

            $kept[] = $item;
        }

        return $kept;
    }

    /**
     * Header items as they're saved: known fields only, within the limits.
     *
     * @param  array<int|string, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function cleanHeader(array $items): array
    {
        return array_slice($this->cleanItems($items, allowChildren: true), 0, self::MAX_HEADER_ITEMS);
    }

    /**
     * The footer as it's saved: its tagline, copyright line and link columns, within the limits.
     *
     * @param  array<string, mixed>  $footer
     * @return array{tagline: string, copyright: ?string, columns: array<int, array{heading: string, links: array<int, array<string, mixed>>}>}
     */
    private function cleanFooter(array $footer): array
    {
        return [
            'tagline' => is_string($footer['tagline'] ?? null) ? trim($footer['tagline']) : '',
            'copyright' => is_string($footer['copyright'] ?? null) && trim($footer['copyright']) !== '' ? trim($footer['copyright']) : null,
            'columns' => collect(is_array($footer['columns'] ?? null) ? $footer['columns'] : [])
                ->filter(fn (mixed $column): bool => is_array($column))
                ->map(fn (array $column): array => [
                    'heading' => trim((string) (is_string($column['heading'] ?? null) ? $column['heading'] : '')),
                    'links' => array_slice($this->cleanItems(is_array($column['links'] ?? null) ? $column['links'] : [], allowChildren: false), 0, self::MAX_FOOTER_LINKS),
                ])
                ->take(self::MAX_FOOTER_COLUMNS)
                ->values()
                ->all(),
        ];
    }

    /**
     * The mobile menu's settings as they're saved (standard: opens from the top, with the logo).
     *
     * @param  array<string, mixed>  $mobile
     * @return array{open_from: string, show_logo: bool, tagline: string}
     */
    private function cleanMobile(array $mobile): array
    {
        return [
            'open_from' => is_string($mobile['open_from'] ?? null) && array_key_exists($mobile['open_from'], self::MOBILE_OPEN_FROM) ? $mobile['open_from'] : 'top',
            'show_logo' => (bool) ($mobile['show_logo'] ?? true),
            'tagline' => is_string($mobile['tagline'] ?? null) ? trim($mobile['tagline']) : '',
        ];
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
