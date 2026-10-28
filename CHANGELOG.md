# Changelog

## 1.5.0 - 2026-10-28

- **Columns hold their own blocks.** In a Section's row, each column now has its own blocks, one under another, so a column can hold a heading, text and an image beside one large image in the next. The row editor shows a box per column (*Column 1*, *Column 2*…), each with its own *Add block to column…* button. A block card has left and right arrows to move it to the next column, and columns can be swapped. Changing a row's layout keeps every block: with fewer columns, the extra columns' blocks move to the end of the last one.
  - *Line up blocks at the* is now *Line up columns at the*, and *Last block first on phones* is *Last column first on phones*. Both show only for rows with columns. With *Same height*, a column's last block stretches.
  - Validation messages name the column too: *Row 1 › Column 2 › Block 1 (Call to action)*.
- **Column settings.** Each column has its own background (a preset, a custom colour or a gradient), text colour, space inside, corners, border, shadow and vertical alignment (*Column settings*, collapsed in each column). A dark column makes its blocks' text light, and a light column in a dark section makes it dark.
- **More column layouts:** 5 equal columns, ¼ + ¾, ¾ + ¼, 40% + 60%, 60% + 40%, and ¼ + ½ + ¼.
- **Space between columns per row**, from *None* (columns touch) to *Large*, or the section's setting.
- **Easier for someone new.** A **?** beside the harder settings explains each one when you hover over it, and every button on a block card, row and column has a tooltip (*Duplicate this block*, *Swap with the column on the right*…). Settings most pages don't need (border, shadow, the background panel, and a column's space inside and corners) are under a collapsed **More options**. Layouts have plain names, grouped by how many columns they have (*Narrow left, wide right (⅓ + ⅔)*), a Section explains how rows, columns and blocks fit together, rows are numbered, and a block's panel in a Section says where it is: *Section › Row 1 › Column 2 › Text*. Nothing about saved pages changes.
  - **Every block:** tabs have icons, and the Advanced tab says most blocks don't need it. Buttons on cards, items, stats, logos and other lists say what they do (*Duplicate this card*, *Move this button up*). Rarely used settings are under More options: a button's icon, shadow and new tab; an image's border and shadow; and a card's colours, border line, shadow and text sizes.
  - **Pages:** the page editor and lists explain what they're for, and an empty list has a button to make the first one. *URL* is now **Web address**, the *SEO* tab is **Search & sharing**, and the list's *Replaces* column is **Replaces built-in page**.
  - **Block library:** explains that changing a linked block updates every page it's on.
  - **Navigation:** every move, drag and delete button says what it moves, and a mega menu's highlight panel is a collapsed section.
  - **Branding:** tooltips on the harder settings (tints, hover colours, outline buttons, heading sizes), collapsed sections for text colours, dashboard fonts and the header's dropdown and button colours, and the Custom CSS box now says it's for people who write CSS.
- **SVG images in blocks.** Block images (Image, Logo strip, Image cards, Gallery, Banner, Team, Testimonials and others) can now be SVGs, which stay sharp at any size. They get the same check as SVG logos (`Support\SafeSvg`), so one with scripts, event handlers, links to other files or other active content is refused on upload. A page's share image stays JPG, PNG or WebP, because social sites don't show SVGs.
  - `SafeSvg` now accepts the plain DOCTYPE line that design apps such as Illustrator add (it's never loaded); a DOCTYPE with its own declarations is still refused. It now also refuses processing instructions such as `xml-stylesheet`, which could load a style sheet from another site. Its message says how to export a plain SVG.
  - For developers: `PageBlock::imageUpload()` takes `allowsSvg: false` for an image that must be a picture file, and `PageBlock::SVG_FILE_TYPE` is new. `IMAGE_FILE_TYPES` is unchanged.
- **Wider block panels.** A Section's panel is now much wider, so its columns sit side by side with room for their blocks and settings, and other blocks' panels are a little wider. A narrow column's settings stack one under another.
- **Existing pages look the same.** Rows saved by 1.4 and earlier kept one list of blocks that filled the columns in order. They're read into columns automatically, each column getting the blocks that sat in it, whenever a page, library block or revision is loaded, and saved in the new shape the next time the page is saved. Nothing needs migrating. A column with one block has the same HTML as before (plus `data-column`). The one visible difference: a 1.4 row with more blocks than columns used to wrap onto a new line, and those blocks now sit under the column they were in.
- **Ready for a visual editor.** Every block, row and column is now saved with a `uid` that stays the same from save to save, and stored blocks have a version number (`blocks_version`, now 2), so later changes to their shape can be upgraded in order.
  - **Run your migrations** (`php artisan migrate`): one adds `blocks_version` to `site_builder_pages` and `site_builder_saved_blocks`. Rows already there are version 1 and are upgraded when read.
- **For developers:**
  - Stored blocks are now version 2 (`BlockSchema::VERSION`): a block is `{uid, type, data}`, a Section's row `{uid, layout, align, gap, …, columns: [{uid, blocks: […], …column settings}, …]}`, with as many columns as its layout has (one when stacked). Blocks, rows and columns without a uid get one: from their position when read (the same every time, so reading never counts as a change), and a new one when saved; a copied uid (a cloned row) is replaced. Library copies get new uids, and a linked library block takes its link's uid.
  - `SectionBlock::rows()` gives each row its `uid`, `gap` and `columns`, each `{uid, blocks, class, style, textScheme}`, and, as before, all its `blocks` in order. If your app's code reads `$row['blocks']` from stored data, read `SectionBlock::rows()` instead.
  - New: `BlockSchema` (`read()`, `write()`, `upgrade()`, `withNewUids()`), the `StoredBlocks` cast, which `Page` and `SavedBlock` now use for `blocks`, `SectionBlock::normaliseRow()`, `normaliseBlocks()`, `fitColumns()`, `columnCount()`, `layoutOptions()` and `placeOf()`, `BlockAppearance::backgroundFields()`, `backgroundLook()` and `moreOptions()`, and `PageBlock::HELP_ICON` for your own blocks' tooltips (`->hintIcon(PageBlock::HELP_ICON, __('…'))`).
  - `PageBlock::itemTooltips($repeater, 'card')` gives a host block's list items the same button tooltips, and `BrandingField::tooltip()` gives an app's own Branding setting a **?**.
  - Section layouts' labels changed (e.g. *2 equal columns* is now *Two equal columns*); their keys didn't. If your code or tests compare `SectionBlock::rowLabel()` or `LAYOUTS` labels, update them. Revisions record their `blocks_version`.
  - Code can still save blocks in the 1.4 shape (an import, say): they're upgraded on save.
  - The new layouts and column settings use Tailwind classes from the package's `src/`, and site-builder.css changed (*Same height* with several blocks in a column), so rebuild your app's CSS (`npm run build`).

## 1.4.0 - 2026-10-27

- **Gradient overlays on banners.** The Banner and Hero blocks' image overlay, and Branding's hero, inner and app banners, can now be *One colour* (as before) or a *Gradient*, like a block's image background on the Style tab. A gradient fades from a start colour and strength to an end colour and strength, in one of four directions. Existing banners are unchanged (the HTML of a one-colour overlay is the same).
  - New: `BlockAppearance::imageOverlay()` reads the overlay settings, `BlockAppearance::overlayFields()` builds them, and `BlockAppearance::overlayCss()` gives the inline style.
  - The overlay arrays from `Branding::banner()` and the banner components gain a `gradient` key (null for one colour). An `imageOverlay` without it still works.
  - Branding's readability warning counts a gradient by its stronger end.
- **Announcement bar colours.** Branding › Header & footer has an *Announcement bar* section: background, text and close button, link and link hover colour, with the same low-contrast warning as the header and footer.
  - It colours SaaSykit's announcements (Admin › Announcements) on the website and in the dashboard. The theme now shadows SaaSykit's `livewire/announcement/view.blade.php`, with the same markup and behaviour.
  - The colours are inline styles from validated hex values, because the dashboard's stylesheet doesn't include the site builder's utilities. Left blank, SaaSykit's own classes are used, so nothing changes.
  - New: `Branding::announcementColours()` and `Branding::ANNOUNCEMENT_*_COLOR`, also set as `--color-sb-announcement-*` variables on the website.
  - When merging SaaSykit updates, compare its announcement view with the theme's copy.
- **Links in the footer's copyright line.** Website › Navigation › Footer › Copyright line can hold links written as `<a href="https://example.com">Example</a>`. The address is checked like a menu link's, both on saving and on output. Links are underlined, in the footer's link colours (Branding › Header & footer › Links), and a link to another site opens in a new tab with `rel="noopener"`. Any other HTML, and a link's other attributes, show as plain text.
  - `NavigationMenus::footer()` gains `copyrightHtml`, an escaped `HtmlString` with the links. `NavigationMenus::linkedText($text, $linkClass)` does the same for any text.
  - `copyright` is now plain text, with each link replaced by its text, so an app view that prints it stays safe. Print `copyrightHtml` to show the links.
  - The line can be up to 300 characters (it was 200).
- **Custom CSS mistakes are listed.** CSS that's safe but has mistakes is still saved, with a warning naming each mistake's line, also shown under the editor. The mistakes are:
  - a property CSS doesn't have (e.g. `colour`);
  - an extra or missing bracket;
  - a string or comment that's never closed;
  - a declaration outside any rule.

  The property names come from known-css-properties 0.37.0 (MIT), in `resources/data/css-properties.php`. The Custom CSS section's description now matches the rules. New: `CustomCss::warnings()`.
- **Security hardening** (from a source review of the whole package):
  - **Custom CSS:**
    - It's checked the way a browser reads it. A `url()` hidden between a `"/*"` and a `"*/"` inside strings, a `url(` that's never closed, a later candidate in `image-set()`, and `src()` can no longer load files from another site.
    - CSS that can't be checked is rejected (it no longer passes if a regex fails).
    - The check takes time in step with the CSS.
    - `url(#fragment)` is allowed.
  - **SVG logos and favicons** must be plain drawings (`Support\SafeSvg`). Scripts, event handlers, `foreignObject`, links to other files, DOCTYPEs and unsafe styles are refused on upload, because uploads are served from the site's own address. Files already uploaded aren't changed.
  - **Social links** in the theme footer and the Social follow block are only linked when they start with `https://` or `http://` (`SocialFollowBlock::isWebAddress()`).
  - **Block CSS classes** can't include `fixed` or `sticky`. The editor refuses them, and ones saved before are left out when the page renders.
  - **Branding's permission check** fails closed when `site-builder.permissions.branding` isn't a permission name, like the other pages.
  - **Icon picker:** a stored icon value that isn't a library icon is shown escaped (*Unknown icon (…)*), never as HTML. The icon rule refuses non-text values.
  - **Pages in the bin** can still be edited, but their status and the built-in page they replace are locked until they're restored.
  - **Duplicate** always picks an address no page (including one in the bin) or other part of the site uses.
  - **Navigation:** the page picker lists draft titles only to people who can see the pages list.
  - **Redirects and previews:**
    - A wrongly cased address, or a page that replaces a built-in page, redirects to a path on this site rather than an absolute URL built from the request's Host header.
    - Preview responses send `Cache-Control: private, no-store`.
  - **Caches:**
    - The published-page map, the menu page links and the settings are cached under a version that changes once a save is committed, so a request that overlaps a save can't put old data back (`Support\VersionedCache`). Old cache entries are simply never read again.
    - The render cache's version also changes after the commit.
    - A page's cache key is read before its library blocks are expanded.
    - Live stats use `Cache::flexible`: after ten minutes the last figure is shown while one request works out the new one.
  - **Uploads:** the in-use check for page uploads encodes paths the way they're stored, so a file with an accented name is found where it's used.
  - **Fonts:** an unknown font slug can't put anything but letters, digits and spaces into CSS.
  - **The page permissions migration:**
    - It only grants the site-wide `admin` role, never a tenant's, matching `tenant_id`, the permissions' guard and the lowest id.
    - Rolling back removes only the package's standard permission names, so an app's own permission mapped to a page ability is kept.
  - **The package's tests** always use in-memory SQLite (`force="true"`), even when the shell exports `DB_*`. To use MySQL, copy `phpunit.xml.dist` to `phpunit.xml`, which is now git-ignored.

## 1.3.0 - 2026-10-27

- **Apps can set the standard menus.** `SiteBuilder::defaultMenus(array|Closure|null $header = null, array|Closure|null $footer = null, array|Closure|null $mobile = null)` sets the header, footer and mobile menu the site uses while nothing is saved in Website › Navigation, and that Reset to default saves.
  - They use the saved menu format, with built-in links, pages, web addresses, dropdowns and mega menus.
  - Closures are read each time, so labels can be translated.
  - A menu left `null` stays the package's standard one.
  - Defaults are checked like a saved menu: known fields, limits, and only links that apply (in dropdowns and mega menus too). A broken default falls back to the package's.
  - Saved menus are never changed by them.
- Reset to default's confirmation now describes the site's own standard menus: each header link (with its dropdown or mega menu links), the footer links, tagline and copyright line, and the mobile menu's settings. It still says it can't be undone.
- New: `NavigationMenus::standardMobile()`. The menus' cleaning is shared by saving and the standard menus.

## 1.2.1 - 2026-10-27

- Fixed: a Banner subclass's extra content set to `below` (`BannerBlock::extraContentView()`) now spans the banner's full content width, after the text column, instead of sitting inside the text's narrower `max-w-4xl` column, where wide content such as a search form was squashed. It still follows the banner's text alignment and has `data-banner-extra="below"`. To keep it narrow, constrain it in your own view. `beside` is unchanged.
- Fixed: with no page image and no Branding share image, the theme's social cards pointed at `images/twitter-card.png` and `images/facebook-card.png`, which SaaSykit doesn't ship (404s). They now output no image tags and a `summary` Twitter card. With an image, nothing changes.

## 1.2.0 - 2026-10-26

- **Navigation: saved menus are the site's own.**
  - Only standard links that apply to the site are offered in the picker and put in the standard menus. `Link::isAvailable()` decides both.
  - **Pricing** (`/#pricing`) now applies only when the home page visitors see has a pricing section: a block anchored `pricing` on the page replacing the home page, or `id="pricing"` in the home view's template, as in the theme's and SaaSykit's. A site whose home page has no pricing section no longer gets a Pricing link that goes nowhere.
  - A link that no longer applies, or was removed, stays in a saved menu's data and is just hidden. The picker shows it as *(not available on this site, so hidden)*, so the menu still saves.
- `SiteBuilder::withoutLinks(array $keys)` removes standard (or any) links entirely, whichever provider boots first.
- **Reset to default is honest.** Its confirmation lists the links the header and footer will get, describes the mobile menu, and says it can't be undone. It now saves the standard menus as the site's own rather than clearing them, so a later package update or a newly available link doesn't change them.
- **Branding sections.** `SiteBuilder::brandingSection($key, $title, $fields, $description, $icon)` adds the app's own tab to Website › Branding, after the package's.
  - `BrandingField` offers text, textarea, toggle, select, colour (CSS variables and the contrast warning), button style (the four styles plus Automatic), image and banner (the same settings as the hero and inner banners).
  - Values are saved as `branding.app_{key}`, validated in the form, and re-checked every time they're read: `Branding::appValue()`, `Branding::appImageUrl()` and `BrandingStyle::appButtonSlot()`.
  - App banners render with `<x-site-builder::banner variant="app_{key}">`.
  - Images go in the Branding upload folder, are deleted when replaced, and are never touched by `site-builder:clean-uploads`.
  - Keys follow the `brandingColours()` rules, and a key two fields (or a colour) would share throws. `brandingColours()` is unchanged.
- `BrandingStyle::siteButtonSlot()` takes an optional `$isOnDarkBackground` and understands `auto`. With nothing chosen it still gives style 1.
- **`site-builder:clean-uploads` also clears the Branding folder** (`site-builder/branding` by default). It deletes images older than a day that no Branding setting uses: images uploaded and never saved, from the package's settings or an app's sections. Logos, icons, the share image and banner images that are saved are kept, even for a banner currently set to a gradient. `--dry-run` lists them too.
- **Behaviour change: banner images must be Branding uploads.** The hero and inner banners (and app banners) only show a saved image when it's a file in the Branding upload folder. A hand-edited path anywhere else on the disk, or one containing `..`, now shows the banner's colour or gradient instead (lesson 11). Images uploaded on Website › Branding are always in that folder, so sites that only used the Branding page see no change.

## 1.1.0 - 2026-10-26

- **An app's own theme views win over the package's theme.** When `config/view.php`'s `paths` includes the app's theme folder (`resources/views/theme`, set by the new `site-builder.theme.app_path`), the package's theme goes straight after it instead of in front of everything. Views are then found in the app's theme folder first, then the package's theme, then SaaSykit's views, so an app can override one theme view (the header, the head) and keep the rest. Apps without that folder in `paths` see no change. The README and the extending guide explain how to override a view and how to keep a copy up to date after package updates.
- **Extending a core block.** A host block that extends a core block and has the same name replaces it everywhere: the picker (in the same place), Section rows, the block library, and cached pages. A Section replaced by a subclass now keeps rendering its rows (the renderer checked for the exact class).
- Banner and Hero have two hooks for a subclass to show its own content with the banner's text: `extraContentView(array $data): ?string` names a view (it gets the banner's data, including the subclass's own `viewData()`), and `extraContentPosition(array $data): string` puts it `below` the text and buttons or `beside` them on wide screens (`BannerBlock::EXTRA_CONTENT_POSITIONS`). The banner's HTML is unchanged.
- Documentation: "Extending a core block" in the custom blocks guide, with a worked example in the tests. It explains that extra content with a Livewire component, a CSRF token or per-visitor data must make `isCacheable()` return `false`.

## 1.0.1 - 2026-10-26

- Security (low): **Save to library** on a block card now needs the library's *create* permission (`create pages` by default). Before, anyone who could edit a page could create library blocks. **Make an editable copy** now checks the library's *view* permission too. Found in a focused security review.

## 1.0.0 - 2026-10-25

- Renamed to `scbudgetweb/site-builder-for-saasykit` (it was `scbudgetweb/saasykit-site-builder`) before the first release. It's an independent add-on, not made or endorsed by SaaSykit.
- Documentation:
  - user guides in `docs/` (UK English, no code) for pages, revisions, the block library, navigation, Branding and legal pages;
  - developer guides for custom blocks, the extension API, tenant micro-sites and publishing a release.
  - The README covers every admin area, both scheduled commands and the config options.
- Package skeleton: service provider, config, settings store, Filament plugin (the *Website* admin group) and the `site-builder:install` command.
- Branding (Admin › Website › Branding): logos and which logo shows where, favicon, Apple touch icon, site colours, self-hosted fonts (six bundled, plus the app's own), body text size and heading scale, section headings, text colours, four button styles with Automatic, corners and shadows, header and footer colours, hero and inner banners, a colour per Filament panel, and custom CSS. Panels are rebranded without editing their providers, and keep their own colour and font until one is chosen.
- Pages (Admin › Website › Pages): pages built from blocks at `/{slug}` (fallback route, so they never hide an app route), with a signed preview, Content, Settings and SEO tabs, duplicate, and a bin with restore and permanent delete. A page can replace a built-in page (slots: `home` from the theme, more with `SiteBuilder::slots()`). Images removed from a page are deleted unless another page uses them. `site-builder:sitemap` writes `sitemap-pages.xml` and adds it to `robots.txt`.
- First blocks: Banner, Text, Image + text and Call to action. Apps add their own with `SiteBuilder::blocks()`.
- Branding: a default share image for social media.
- Block style system: every block's Style, Layout and Advanced tabs. The options are background presets, a custom colour, a gradient, or an image with a solid or gradient overlay; text colour; heading decoration and size; text size; width; spacing; heading alignment; a background panel with corners, shadow and overlap; anchor and CSS classes. Card style settings are added for card blocks. Each block offers only the settings that apply to it.
- Fixed: Branding's heading decorations didn't draw (the bars had no `content`).
- The rest of the core blocks: Hero, Image, Features, Image cards (with a carousel), Steps, Stats (typed numbers, plus the app's live figures with `SiteBuilder::stats()`), Buttons, Testimonials, Team, FAQ (with FAQPage structured data), Tabs, Gallery (with a lightbox), Video (YouTube no-cookie, Vimeo and Adilo; click-to-play until `SiteBuilder::consentChecker()` allows marketing cookies), Logo strip (row, loop or carousel, with pause), Social follow and Divider.
- Icons: Boxicons 2.1.4 bundled and drawn inline, with a searchable icon picker that understands everyday words. Buttons can have icons. A `<x-site-builder::social-icon>` component draws social logos, and the theme footer uses it.
- Sections: one styled area holding rows of blocks. Each row has its own layout (stacked, two to four columns, or narrow + wide), alignment and blocks, and an option to show the last block first on phones. Blocks in a section take its text colour and heading decoration, open in a second panel, and are named "Section › Row › Block" in error messages.
- Borders and shadows everywhere: blocks (on the section or its panel), Section rows (with corners and padding), images, cards and buttons. Borders can be solid, dashed, dotted or double, with a width, colour and sides. Shadows come as presets or custom (offset, blur, spread, colour, strength).
- Editor tabs are now Content, Layout, Style, Advanced.
- Extension API: `SiteBuilder::brandingColours()` adds an app's own element colours to Branding › Header & footer, as CSS variables with contrast warnings. The facade now documents every extension point.
- `site-builder:clean-uploads` deletes page images that nothing uses (older than a day by default; `--dry-run` lists them). Schedule it daily.
- Render caching: pages made only of cacheable blocks are served from the cache until any page, library block or setting is saved. Blocks opt out with `isCacheable()` (live stats and videos do). Switch it off with `SITE_BUILDER_PAGE_CACHE=false`.
- Page revisions: every save that changes a page is kept (the last 30, configurable with `site-builder.pages.revisions`), with who saved it. The editor's **Revisions** panel previews any version and restores it as unsaved changes. Images stay as long as a kept revision uses them.
- Legal pages (Admin › Website › Legal pages): edit the Privacy Policy and Terms of Service in a rich editor with merge tags (site name, site URL, support email, page URLs). Pages start from SaaSykit's own text, show "Last updated" once edited, and go back to the template when cleared. Apps add more with `SiteBuilder::legalPages()` and show them with `<x-site-builder::legal-page>`.
- Navigation (Admin › Website › Navigation): header links with dropdowns or mega menus (columns, icons, descriptions and a highlight panel), a full-screen mobile menu that opens from the top or the right, and footer columns, tagline and copyright line. Links can go to a page you built (it follows the page's address and hides while it's a draft), a built-in page, or any web address. Reset to default brings back the standard links. Apps add built-in links with `SiteBuilder::links()`.
- Block library (Admin › Website › Block library): save a block or a whole section from its card and reuse it on any page with the *Saved block (library)* block. Linked blocks update everywhere when the library block changes, and *Make an editable copy* turns one into a one-off. Shows where each library block is used, refuses to delete one that's in use, and keeps shared images until nothing uses them.
