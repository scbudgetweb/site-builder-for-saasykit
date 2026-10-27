# Extending the site builder

A host app plugs into the site builder through one facade, `ScBudgetWeb\SiteBuilder\Facades\SiteBuilder`, from a service provider's `boot()`. Every method can be chained, and an entry with the same key (or title) as an earlier one replaces it.

```php
use ScBudgetWeb\SiteBuilder\Facades\SiteBuilder;

SiteBuilder::blocks([...])
    ->slots([...])
    ->links([...])
    ->stats([...])
    ->brandingColours('Booking widget', [...], [...])
    ->legalPages([...])
    ->consentChecker(fn (string $category) => ...);
```

Custom blocks have their own guide: [Writing a custom block](custom-blocks.md). To change a core block rather than add one, see [Extending a core block](custom-blocks.md#6-extending-a-core-block).

## Overriding a theme view

The theme's views are found after the app's own theme folder and before SaaSykit's views, so you can replace any one of them without copying the rest.

1. In `config/view.php`, put the app's theme folder first:

   ```php
   'paths' => [
       resource_path('views/theme'),
       resource_path('views'),
   ],
   ```

2. Copy the view from the package's `resources/views/theme/` to the same path under `resources/views/theme/`, for example `components/layouts/partials/head.blade.php`, and edit your copy.

The package's theme is placed straight after that folder (`site-builder.theme.app_path`, which you can change). When the folder isn't in `view.paths`, the theme goes in front of every path, as in 1.0. With `SITE_BUILDER_THEME=false`, the package's theme is left out, and your folder sits in front of SaaSykit's views as normal.

**Watch for drift.** Your copy is frozen at the version you copied. When the package updates a view you've overridden (a new Branding setting in the header, say), your site won't get it until you copy the change across. After each package update:
- check the CHANGELOG for theme changes;
- diff each overridden view with the package's: `diff resources/views/theme/<path> vendor/scbudgetweb/site-builder-for-saasykit/resources/views/theme/<path>`;
- keep overrides few and small. A partial (the header, the head) is easier to keep up to date than a whole layout.

## Slots: replaceable built-in pages

A slot is one of the app's own pages that an admin can replace with a page they built (Pages › Settings › *Replaces built-in page*). The theme registers `home`.

```php
use ScBudgetWeb\SiteBuilder\Pages\Slot;

SiteBuilder::slots([
    Slot::make('contact', 'Contact', fn () => route('contact'))
        ->requires('contact_form'),   // optional: warn when the replacing page lacks this block
]);
```

The URL is resolved on every request. The built-in page's view then shows the replacing page when there is one:

```blade
@if ($builtPage = app(\ScBudgetWeb\SiteBuilder\Pages\PageService::class)->forSlot('contact'))
    @include('site-builder::pages.show', ['page' => $builtPage])
@else
    {{-- the built-in page --}}
@endif
```

Other behaviour:
- `forSlot()` never throws, so the built-in page still shows before the package is migrated.
- A replacing page's own `/{slug}` address redirects (301) to the slot's URL.
- Menus link to the slot's URL.

## Built-in menu links

Admins add "a built-in page" to the header and footer in Website › Navigation. The package registers Home, Pricing, Blog, Roadmap, Log in, Create account, Dashboard, Privacy Policy and Terms of Service. Add your own, or replace one by its key:

```php
use ScBudgetWeb\SiteBuilder\Navigation\Link;

SiteBuilder::links([
    Link::make('find_a_hall', 'Find a hall', fn () => route('halls.index'))
        ->description('the hall search')                      // shown in the admin picker
        ->availableWhen(fn () => config('halls.search_enabled')),
]);
```

How links behave:
- The URL is worked out on every request.
- A link **applies** when its condition passes and its URL closure doesn't throw (for example a missing route). Only links that apply are offered in the admin picker and put in the standard menus.
- A saved menu that has a link which no longer applies keeps it in its data. The menu just doesn't show it, and the picker shows it as *(not available on this site, so hidden)*, so the menu still saves.
- Never change a key once menus use it.

When the standard links apply:
- **Pricing** goes to `/#pricing`, so it applies only when the home page visitors see has that section: a block anchored `pricing` on the page replacing the home page (in a Section's rows too), or otherwise `id="pricing"` in the home view's template. The theme's home and SaaSykit's both have one.
- **Roadmap** applies while `app.roadmap_enabled` is on.
- The others apply when their route exists.

To take standard links out entirely, whichever provider boots first:

```php
SiteBuilder::withoutLinks(['pricing', 'roadmap']);
```

A removed link is left out of the picker and the standard menus, and hidden in any saved menu (its data is kept). To change a standard link instead, register your own with the same key.

**Saved menus are the site's own.** With nothing saved, the menus are the standard ones that apply. Once an admin saves Website › Navigation, a package update or a newly available link never changes them. **Reset to default** lists the standard links, says it can't be undone, and then saves the standard menus as the site's own.

## Standard menus

With nothing saved in Website › Navigation, and after **Reset to default**, the site uses its standard menus. They're the package's (Blog in the header; Blog, Privacy Policy and Terms of Service in the footer) until your app sets its own:

```php
use ScBudgetWeb\SiteBuilder\Navigation\Link;

SiteBuilder::links([
    Link::make('whats_on', 'What\'s on', fn () => route('events.index')),
    Link::make('cookie_settings', 'Cookie settings', fn () => '#cookie-settings'),
])->defaultMenus(
    header: fn () => [
        ['type' => 'builtin', 'link' => 'whats_on'],
        ['type' => 'none', 'label' => __('Visit'), 'menu' => 'dropdown', 'children' => [
            ['type' => 'url', 'url' => '/find-us', 'label' => __('Find us')],
        ]],
        ['type' => 'builtin', 'link' => 'blog'],
    ],
    footer: fn () => [
        'tagline' => config('app.description'),
        'copyright' => null,                     // the standard line
        'columns' => [
            ['heading' => __('Legal'), 'links' => [
                ['type' => 'builtin', 'link' => 'privacy_policy'],
                ['type' => 'builtin', 'link' => 'cookie_settings'],
            ]],
        ],
    ],
    // mobile: left out, so it stays the package's (opens from the top, with the logo)
);
```

How standard menus work:
- **Same format as saved menus:** header items, the footer (`tagline`, `copyright`, `columns` of `heading` and `links`) and the mobile menu (`open_from`, `show_logo`, `tagline`). Items can be built-in links, pages (`'type' => 'page', 'page_id'`), web addresses, dropdowns and mega menus.
- **Closures are read each time** the menus are, so `__()` labels follow the visitor's language. Arrays work too.
- **A menu left `null` isn't changed:** until your app sets it, it's the package's standard one. A footer without a `tagline` gets the site description, as the package's does.
- **They're checked like a saved menu:**
  - only known fields are kept, within the limits;
  - built-in links that don't apply (or were removed with `withoutLinks()`) are left out, in dropdowns and mega menus too;
  - a dropdown or column left empty goes.
  - A closure that throws or doesn't return an array counts as not set, so the package's standard menu is used.
- **Saved menus win.** Standard menus never change a saved menu. Reset to default lists exactly what your standard menus hold, then saves them as the site's own.

## Live stats

The Stats block can show live figures. Each source appears as *Live: {label}* in the block's picker, and the value is cached for ten minutes.

```php
SiteBuilder::stats([
    'halls' => ['Halls listed', fn () => Hall::query()->listed()->count()],
]);
```

A Stats block showing a live figure isn't render-cached. If a source is removed later, blocks using it show nothing rather than a wrong zero.

## Branding colours

Give your own elements (a booking box, a status badge) colours that admins set on Branding › Header & footer, in a section of their own:

```php
SiteBuilder::brandingColours('Booking widget', [
    'booking_bg' => ['Background colour', '--color-booking-bg'],
    'booking_text' => ['Text colour', ['--color-booking-text', '--color-booking-icon']],
], [
    'booking_text' => 'booking_bg',   // text => background pairs get the low-contrast warning
]);
```

How these colours work:
- Each colour is saved as `branding.app_{key}`.
- When it's set, it's written as `{variable}: #hex` into the site's `:root` style. Give your variables a default in your own CSS, and use them in your views. With Tailwind 4, define them in `@theme` (for example `--color-booking-bg: #ffffff;`) to get `bg-booking-bg`.
- Keys may only use `a-z`, `0-9` and `_`, and variables must look like `--name`. Anything else throws, so nothing unsafe reaches the page.

## Branding sections

Give your own features settings that admins change on Website › Branding: text, switches, choices, colours, button styles, images and banners. Each section is a tab after the package's.

```php
use ScBudgetWeb\SiteBuilder\Branding\BrandingField;

SiteBuilder::brandingSection('signup', 'Sign-up box', [
    BrandingField::text('signup_title', 'Title', maxLength: 80)->default('Stay in touch'),
    BrandingField::textarea('signup_text', 'Text under the title'),
    BrandingField::toggle('signup_show_privacy', 'Show the privacy note')->default(true),
    BrandingField::select('signup_layout', 'Layout', ['stacked' => 'Stacked', 'inline' => 'Side by side'])->default('stacked'),
    BrandingField::colour('signup_bg', 'Background colour', '--color-signup-bg'),
    BrandingField::colour('signup_text_color', 'Text colour', '--color-signup-text')->contrastWith('signup_bg'),
    BrandingField::buttonStyle('signup_button', 'Button style'),
    BrandingField::image('signup_image', 'Image'),
    BrandingField::banner('signup_banner', 'Sign-up banner')->helperText('Behind the sign-up box.'),
], description: 'The sign-up box at the foot of each page.', icon: 'heroicon-o-envelope');
```

| Field | Saved as | Read it with |
|---|---|---|
| `text`, `textarea` | `branding.app_{key}` | `Branding::appValue($key)`: the text, or its default while blank |
| `toggle` | `branding.app_{key}` (`true` / `false`) | `appValue($key)`: a bool |
| `select` | `branding.app_{key}` | `appValue($key)`: one of the options |
| `colour` | `branding.app_{key}` | `appValue($key)`. Its CSS variables are set like `brandingColours()` ones, and `contrastWith()` adds the low-contrast warning |
| `buttonStyle` | `branding.app_{key}` (`auto`, `1`–`4`) | `BrandingStyle::appButtonSlot($key, $isOnDarkBackground)`: the style for `site-button site-button-{slot}`. Automatic suits the background |
| `image` | `branding.app_{key}` (a path) | `Branding::appImageUrl($key)` |
| `banner` | `branding.app_{key}_{field}`, like the hero and inner banners | `<x-site-builder::banner variant="app_{key}">`. Its text colours fall back to the inner banner's |

Each field takes `->helperText()` and `->default()`. Image fields also take `->fileTypes()` (JPG, PNG and WebP by default) and `->maxSize()` (2 MB by default).

How they behave:
- Every value is checked again each time it's read (lesson 11). Text is trimmed and kept within its length. Choices and button styles must be listed ones, and colours 6-digit hex. Images must be a file in the Branding upload folder. Anything else reads as the default (or `null`).
- The form validates the same way before saving.
- Images and banner images go on the package's upload disk in its Branding folder (`site-builder/branding` by default). A replaced or removed image is deleted when Branding is saved. `site-builder:clean-uploads` keeps every image a Branding setting uses, and deletes ones that were uploaded but never saved.
- Keys follow the `brandingColours()` rules: `a-z`, `0-9` and `_` only. A key two fields would save (including a banner's own settings, or a `brandingColours()` colour) throws. A section with the same key as an earlier one replaces it.
- `brandingColours()` still works as before. Use it for a few colours on Header & footer, and a section for anything more.

## Legal pages

The package edits the Privacy Policy and Terms of Service. Add others:

```php
use ScBudgetWeb\SiteBuilder\Legal\LegalPage;

SiteBuilder::legalPages([
    LegalPage::make('cookie_policy', 'Cookie Policy', fn () => route('cookie-policy'), 'legal.cookie-policy-default'),
]);
```

How they work:
- Each page gets a tab in Website › Legal pages, and a `{key}_url` merge tag.
- The optional fourth argument is a view holding the standard text. It pre-fills the editor and shows until content is saved.
- Show the page in your own view with the component:

  ```blade
  <x-site-builder::legal-page key="cookie_policy" />
  ```

  It outputs "Last updated" and the saved text, or the template.

## Cookie consent

Video blocks are click-to-play until the visitor allows marketing cookies. Tell the package how to ask your cookie banner:

```php
SiteBuilder::consentChecker(fn (string $category): bool => CookieConsent::allows($category));
```

Until a checker is set, nothing is allowed. If it throws, the answer is "not allowed" and the error is reported.

## Settings, caching and scheduling

Settings and caching:
- **Settings** (Branding, Navigation, Legal pages) live in one cached key/value table. Read them through `SiteBuilder::settings()`, and expect a key to be missing: a forever cache can outlive a deploy that adds one (lesson 9).
- **Page HTML is cached** when every block is cacheable. Saving any page, library block or setting makes every cached page stale. Turn it off with `SITE_BUILDER_PAGE_CACHE=false`.

Schedule these two commands in the host:

| Command | When |
|---|---|
| `site-builder:sitemap` | Straight after SaaSykit's `app:generate-sitemap`, which rewrites `robots.txt` |
| `site-builder:clean-uploads` | Once a day. It deletes images nothing uses that are older than a day: page images no page, library block or revision uses, and Branding images no Branding setting uses (the package's or the app's sections'). `--dry-run` lists them |

If your host's cron can skip minutes, avoid schedules pinned to one minute (`dailyAt()`, `hourly()`). Hall Booker uses its own `atMostEvery()` macro for this.
