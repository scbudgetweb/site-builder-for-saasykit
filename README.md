# Site Builder for SaaSykit

Branding (site identity), a plain base theme, pages built from blocks, a block library, page revisions, navigation menus and legal pages for [SaaSykit](https://saasykit.com) apps, all managed in the Filament admin panel.

> An independent add-on for SaaSykit. It isn't made or endorsed by SaaSykit. You need your own SaaSykit licence.

<a href="https://buymeacoffee.com/shaunn" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" alt="Buy Me A Coffee" height="40"></a>

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- Filament 5
- A SaaSykit (or SaaSykit Tenancy) app

## Installation

1. Require the package:

   ```bash
   composer require scbudgetweb/site-builder-for-saasykit
   ```

2. Run the installer. It publishes `config/site-builder.php`, runs the migrations (after asking), publishes the bundled fonts and prints the lines below:

   ```bash
   php artisan site-builder:install
   ```

   Migrations are loaded from the package, so later updates only need `php artisan migrate`.

3. Add the plugin to your admin panel provider:

   ```php
   ->plugin(\ScBudgetWeb\SiteBuilder\SiteBuilderPlugin::make())
   ```

4. Add these lines to `resources/css/app.css`, then run `npm run build`:

   ```css
   @import '../../vendor/scbudgetweb/site-builder-for-saasykit/resources/css/site-builder.css';
   @source '../../vendor/scbudgetweb/site-builder-for-saasykit/src';
   @source '../../vendor/scbudgetweb/site-builder-for-saasykit/resources/views';
   ```

   Tailwind only includes classes it finds in its sources, and some of the package's classes are chosen in PHP, so both `@source` lines are needed.

5. Schedule the package's two commands in `routes/console.php`:

   ```php
   // Straight after SaaSykit's sitemap, which rewrites robots.txt.
   Schedule::command('app:generate-sitemap')->everyOddHour()->then(fn () => Artisan::call('site-builder:sitemap'));

   // Page and Branding images nothing uses (older than a day).
   Schedule::command('site-builder:clean-uploads')->daily();
   ```

## Admin

Everything sits in one navigation group, **Website** by default:

| Area | Permission |
|---|---|
| Pages | `view pages`, `create pages`, `update pages`, `delete pages` |
| Block library | the page permissions |
| Navigation | `update pages` |
| Branding | `update settings` |
| Legal pages | `update settings` |

The package's migration creates the page permissions and gives them to the `admin` role. Every permission name can be changed in `config/site-builder.php`.

## The theme

The theme puts its own views in front of SaaSykit's without editing any of SaaSykit's files:
- header, footer and navigation;
- layouts;
- a plain home page (which a page can replace);
- the blog index and categories;
- the Privacy Policy and Terms of Service.

Switch the theme off with `SITE_BUILDER_THEME=false`. After a SaaSykit update, compare SaaSykit's versions of these views with the theme's.

Views are found in this order:
1. the app's own theme folder, `resources/views/theme`, when it's in `config/view.php`'s `paths`;
2. the package's theme;
3. SaaSykit's views (`resources/views`).

So to change one theme view, copy it from the package's `resources/views/theme/` into the same place under the app's `resources/views/theme/` and edit it there. For example, `components/layouts/app/header.blade.php` changes only the header, and the rest of the theme still comes from the package. Without that folder in `paths`, the package's theme comes first, as in 1.0.

Your copy doesn't update with the package. After each package update, check the CHANGELOG for theme changes, `diff` your copy with the package's in `vendor/scbudgetweb/site-builder-for-saasykit/resources/views/theme/`, and bring the changes across. The [extending guide](docs/developers/extending.md#overriding-a-theme-view) has more.

## Configuration

| Key | Default | What it does |
|---|---|---|
| `navigation.group` / `icon` / `collapsed` | Website, globe, collapsed | The admin navigation group |
| `theme.enabled` | `true` (`SITE_BUILDER_THEME`) | The base theme |
| `theme.app_path` | `resource_path('views/theme')` | The app's own theme folder, whose views win over the theme's when it's in `view.paths` |
| `permissions.*` | see above | The permission each area needs |
| `pages.routes` | `true` | Serve pages at `/{slug}` (a fallback route, so a page never hides an app route) |
| `pages.preview_path` | `site-builder/preview` | Where signed previews live |
| `pages.revisions` | `30` | Revisions kept per page |
| `pages.cache` | `true` (`SITE_BUILDER_PAGE_CACHE`) | Cache the HTML of pages whose blocks are all cacheable |
| `uploads.disk` / `directory` | `public` / `site-builder` | Where logos and page images are stored |

## Extending it

A host app registers its own blocks, replaceable built-in pages, menu links (or removes standard ones), live stats, Branding colours and sections, legal pages and cookie-consent checker through the `SiteBuilder` facade:

```php
use ScBudgetWeb\SiteBuilder\Facades\SiteBuilder;

SiteBuilder::blocks([OpeningHoursBlock::class])
    ->slots([Slot::make('contact', 'Contact', fn () => route('contact'))->requires('contact_form')])
    ->links([Link::make('find_a_hall', 'Find a hall', fn () => route('halls.index'))])
    ->withoutLinks(['pricing'])
    ->stats(['halls' => ['Halls listed', fn () => Hall::count()]])
    ->brandingColours('Booking widget', ['booking_bg' => ['Background colour', '--color-booking-bg']])
    ->brandingSection('signup', 'Sign-up box', [BrandingField::text('signup_title', 'Title')])
    ->legalPages([LegalPage::make('cookie_policy', 'Cookie Policy', fn () => route('cookie-policy'))])
    ->consentChecker(fn (string $category): bool => CookieConsent::allows($category));
```

See the [developer guides](docs/README.md#for-developers-and-agents).

## Documentation

- **For people running the site:** [pages](docs/pages.md), [revisions](docs/revisions.md), [the block library](docs/block-library.md), [navigation](docs/navigation.md), [Branding](docs/branding.md) and [legal pages](docs/legal-pages.md).
- **For developers:** [writing a custom block](docs/developers/custom-blocks.md), [extending the site builder](docs/developers/extending.md), [building tenant micro-sites](docs/developers/tenant-micro-sites.md) and [publishing a release](docs/developers/releasing.md).

## Testing

```bash
composer install
composer test
```

The tests always use an in-memory SQLite database, even if your shell sets `DB_*` variables, because they reset the database as they run. To run them against MySQL, copy `phpunit.xml.dist` to `phpunit.xml` (it's git-ignored) and change `DB_CONNECTION` there.

## Licence

MIT. See `LICENSE`.

Icons: [Boxicons](https://boxicons.com) 2.1.4 by Atisa, MIT licence (`resources/icons/boxicons/LICENSE`). They're bundled, so no page loads icons from another server.

Fonts: the bundled fonts are under their own open licences, kept alongside each font in `resources/fonts`.

## Support

If the site builder saves you time, you can buy me a coffee:

<a href="https://buymeacoffee.com/shaunn" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" alt="Buy Me A Coffee" height="40"></a>
