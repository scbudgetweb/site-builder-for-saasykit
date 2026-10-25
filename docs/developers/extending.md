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

Custom blocks have their own guide: [Writing a custom block](custom-blocks.md).

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
- A link whose condition fails, or whose URL closure throws (for example a missing route), simply doesn't show.
- Never change a key once menus use it.

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
| `site-builder:clean-uploads` | Once a day. It deletes page images nothing uses that are older than a day; `--dry-run` lists them |

If your host's cron can skip minutes, avoid schedules pinned to one minute (`dailyAt()`, `hourly()`). Hall Booker uses its own `atMostEvery()` macro for this.
