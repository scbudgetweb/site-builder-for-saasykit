# Changelog

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
