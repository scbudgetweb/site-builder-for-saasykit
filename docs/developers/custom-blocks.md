# Writing a custom block

An app adds its own blocks, such as a booking search or a list of live venues, by extending `ScBudgetWeb\SiteBuilder\Blocks\PageBlock` and registering the class. A custom block gets everything a core block gets:
- the slide-over editor with Content, Layout, Style and Advanced tabs;
- the shared background, panel, spacing, border and shadow settings;
- previews on the block cards;
- named validation errors ("Block 2 (Opening hours): …");
- use inside Section rows and the block library;
- render caching.

The worked example below is the package's own test fixture, `tests/Fixtures/Blocks/OpeningHoursBlock.php`, with its view in `tests/Fixtures/views/blocks/opening-hours.blade.php`. `tests/ExtensionApiTest.php` shows it working end to end.

## 1. The class

Put it anywhere in your app, for example `app/SiteBuilder/Blocks/OpeningHoursBlock.php`:

```php
namespace App\SiteBuilder\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;

class OpeningHoursBlock extends PageBlock
{
    // The stored type. Never rename it once pages use it.
    public static function name(): string
    {
        return 'opening_hours';
    }

    public static function label(): string
    {
        return __('Opening hours');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedClock;
    }

    // The Content tab.
    protected static function fields(): array
    {
        return [
            ...static::headingFields(),   // optional heading and intro
            Repeater::make('hours')
                ->label(__('Hours'))
                ->schema([
                    TextInput::make('day')->label(__('Day'))->required()->maxLength(30),
                    TextInput::make('times')->label(__('Times'))->required()->maxLength(60),
                ])
                ->minItems(1)
                ->columns(2),
        ];
    }

    // Your own view (core blocks use site-builder::blocks.{name}).
    public static function view(): string
    {
        return 'site-builder.blocks.opening-hours';
    }

    // Re-check every saved value before it reaches the view (lesson 11).
    public static function viewData(array $data, Page $page): array
    {
        return [
            'hours' => collect(is_array($data['hours'] ?? null) ? $data['hours'] : [])
                ->filter(fn ($row) => is_array($row) && is_string($row['day'] ?? null) && is_string($row['times'] ?? null))
                ->values()
                ->all(),
        ];
    }
}
```

## 2. Register it

In a service provider's `boot()`:

```php
use ScBudgetWeb\SiteBuilder\Facades\SiteBuilder;

SiteBuilder::blocks([OpeningHoursBlock::class]);
```

App blocks appear after the core blocks in the picker. A block with the same `name()` as a core block replaces it. Anything that isn't a `PageBlock` throws.

## 3. The view

`resources/views/site-builder/blocks/opening-hours.blade.php`:

```blade
@include('site-builder::blocks.partials.heading')

<dl class="mx-auto grid max-w-md grid-cols-2 gap-y-2">
    @foreach ($hours as $row)
        <dt class="font-semibold">{{ $row['day'] }}</dt>
        <dd>{{ $row['times'] }}</dd>
    @endforeach
</dl>
```

The view receives:
- `$block`: the saved data, untrusted, so don't output it raw;
- `$page`;
- `$index`: the block's position;
- `$isDark`: whether its background is dark;
- `$headingAlign`;
- everything `viewData()` returns.

The wrapper around it (section, background, panel, spacing, border and shadow, anchor) comes from the shared settings, so the view only draws the content.

Conventions that make a block fit in:

| Use | So that |
|---|---|
| `site-builder::blocks.partials.heading` | The heading and intro follow Layout › Heading alignment, Style › Heading decoration and Heading size |
| `sb-h2 block-heading` on a heading of your own | Style › Heading size applies to it |
| `block-text` on body text | Style › Text size applies to it |
| `site-builder::blocks.partials.buttons` with `PageBlock::validButtons()` | Buttons look and behave like everywhere else (see `buttonsRepeater()`) |
| `sb-block-buttons` on the element holding bottom buttons | *Same height* rows line the buttons up at the bottom |
| Theme tokens (`text-primary-600`, `rounded-sb-card`, `shadow-sb-card`) | Branding changes reach the block |

Don't use DaisyUI class names in block views (lesson 5); the theme doesn't load them.

## 4. Tailwind

Tailwind only builds classes it finds in its sources. Make sure your app's CSS scans the folders holding your block views, and any PHP that chooses classes (lesson 4):

```css
@source '../../app/SiteBuilder';
@source '../views/site-builder';
```

## 5. Optional hooks

| Method | Default | Use it to |
|---|---|---|
| `hasAppearance()` | `true` | Return `false` for a block that draws its own full-width section (like Banner). It then gets no shared settings or wrapper |
| `headingSettings()` | `['align', 'decoration', 'size']` | List only the heading settings that do something, or `[]` for a block with no heading |
| `hasBodyText()` | `true` | Return `false` to hide Style › Text size |
| `layoutFields()` / `styleFields()` / `advancedFields()` | `[]` | Add your own settings to those tabs, above the shared ones |
| `contentTabLabel()` | *Content* | Rename the first tab |
| `defaultWidth()` / `defaultBackground()` | contained / none | Change the starting look |
| `defaultAnchor()` | `null` | Give the block an anchor (for example `opening-hours`) when the admin leaves Anchor ID empty, so a menu link can point at it |
| `isVisible($data)` | `true` | Hide the block, with no wrapper or spacing, for example when a live list is empty |
| `isCacheable($data)` | `true` | Return `false` when the HTML depends on the visitor or changes by itself: live figures, signed-in state, cookie consent, a form's CSRF token, any Livewire component. A page is cached only when all its blocks are |
| `itemLabel($state)` / `editorTitle($data)` | label plus heading | The card and panel titles |

Helpers for fields:
- `headingFields()`;
- `richText()`, whose output goes through `PageBlock::html()`;
- `imageUpload()` and `PageBlock::imageUrl()`;
- `iconSelect()` and `IconLibrary::render()`;
- `buttonsRepeater()`;
- `columnsSelect()` and `PageBlock::columns()`;
- `imageStyleFieldset()` and `PageBlock::imageStyle()`;
- `pick()`, which whitelists a saved choice and accepts integer keys (lesson 2).

## 6. Extending a core block

To change a core block everywhere, extend its class and register the subclass. It has the same `name()`, so it replaces the core block in the picker (in the same place), in Section rows (for blocks that can go in one), in the block library and on pages already using it. Saved data stays valid because the stored type doesn't change.

```php
use ScBudgetWeb\SiteBuilder\Blocks\Core\BannerBlock as CoreBannerBlock;

class BannerBlock extends CoreBannerBlock
{
    protected static function fields(): array
    {
        $showsHighlight = fn (Get $get): bool => (bool) $get('show_highlight');

        return [
            ...parent::fields(),
            Toggle::make('show_highlight')->label(__('Show a highlight'))->live(),
            TextInput::make('highlight')->label(__('Highlight'))->maxLength(100)->required($showsHighlight)->visible($showsHighlight),
            Select::make('highlight_position')
                ->label(__('Highlight position'))
                ->options(self::EXTRA_CONTENT_POSITIONS)
                ->default('below')
                ->selectablePlaceholder(false)
                ->visible($showsHighlight),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        return [
            ...parent::viewData($data, $page),
            'highlight' => is_string($data['highlight'] ?? null) ? $data['highlight'] : '',
        ];
    }

    protected static function extraContentView(array $data): ?string
    {
        return ($data['show_highlight'] ?? false) === true ? 'site-builder.blocks.banner-highlight' : null;
    }

    protected static function extraContentPosition(array $data): string
    {
        return static::pick($data['highlight_position'] ?? null, self::EXTRA_CONTENT_POSITIONS, 'below');
    }
}
```

```php
SiteBuilder::blocks([BannerBlock::class]);
```

How it fits together:
- **Fields:** call `parent::fields()` (or `parent::layoutFields()`, `styleFields()`, `advancedFields()`) and add yours. Don't copy the parent's fields; you'd miss the package's later changes.
- **View data:** start from `parent::viewData()`, so the core view gets everything it needs.
- **The view:** keep the core view and add to it where the block offers a hook, or override `view()` with one of your own. Your own view stops getting the package's fixes to that block.
- **Banner and Hero** have `extraContentView($data)`, which names a view to include with the banner's data (yours included), and `extraContentPosition($data)`, `below` the text and buttons or `beside` them on wide screens. `BannerBlock::EXTRA_CONTENT_POSITIONS` has the labels for a Select.
- **Caching:** the subclass's `isCacheable()` is used, and a cached page's HTML is the same for every visitor. If your extra content contains a Livewire component, or anything with a CSRF token or per-visitor data, return `false` whenever it's shown. A cached Livewire component would serve every visitor the same snapshot and token, and break.

  ```php
  public static function isCacheable(array $data): bool
  {
      return static::extraContentView($data) === null && parent::isCacheable($data);
  }
  ```
- **After a package update,** check the CHANGELOG for changes to the block you've extended.

The package's `tests/ExtendingCoreBlocksTest.php` and `tests/Fixtures/Blocks/HighlightBannerBlock.php` are a worked example.

## 7. Blocks that a replaceable page needs

If one of your built-in pages only works with your block (say a booking page that needs the booking search), say so on its slot:

```php
Slot::make('book', 'Book a hall', fn () => route('book'))->requires('booking_search');
```

The page editor then warns when a page replacing it doesn't have the block. A block linked from the block library counts. See [Extending the site builder](extending.md#slots-replaceable-built-in-pages).

## 8. Links: visitors or search engines?

When a block lists records that have their own detail pages (venues, events, posts), decide where a **visitor** should land. Often that's a list or search view with the record open, which is better for browsing, while the single record page exists for search engines and shared links. Link to the visitor's view from the block, and keep the record page for the sitemap and structured data (lesson 16).

## 9. Testing it

The package's `tests/ExtensionApiTest.php` shows the checks worth copying:
- the block is in the picker and in Section rows;
- it renders from your view, with damaged data left out;
- its panel has the shared tabs;
- a validation problem is named in the "page wasn't saved" notification.

Use unique names in app tests, because the app suite shares a database (lesson 12).
