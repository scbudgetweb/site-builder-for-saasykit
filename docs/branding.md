# Branding

**Where:** Admin › Website › Branding
**Who can use it:** administrators with the *update settings* permission

Branding sets the **look of the whole site**: the public website, the sign-in screens and emails, and the dashboards. Changes apply straight away; reload any open page to see them.

Every setting is optional. Leave one blank to keep the standard look. A **?** beside a setting explains it when you hover over it, and settings most sites leave alone (text colours, dashboard fonts, the header's dropdown and button colours) are in collapsed sections.

| Tab | What's on it |
|---|---|
| **Brand** | Logos (and which shows where), favicon, Apple touch icon, share image and the site's colours |
| **Typography** | Fonts, text and heading sizes, section headings and text colours |
| **Buttons** | Button corners, the four button styles and the *Automatic* buttons |
| **Corners & shadows** | Card, image and form field corners, and card shadows |
| **Header & footer** | The header, footer and announcement bar colours, and any colours your app adds |
| **Banners** | The home page banner (Hero) and the banner on other pages |
| **Dashboards** | The main colour of each dashboard |
| **Custom CSS** | Your own CSS for the public website |

One **Save changes** button saves every tab. The tab you're on is kept when the page reloads. If a colour may be hard to read against its background, a warning appears next to it and again after saving.

## Brand

### Logo & icons

| Setting | What it's for | Files |
|---|---|---|
| **Logo for light backgrounds** | A dark-coloured logo | PNG, SVG or WebP, up to 1 MB |
| **Logo for dark backgrounds** | A light-coloured (for example white) logo | PNG, SVG or WebP, up to 1 MB |
| **Favicon** | The small icon in the browser tab | ICO, PNG or SVG, up to 256 KB. Square, at least 48 × 48 pixels |
| **Apple touch icon** | The icon when someone adds the site to an iPhone or iPad home screen | PNG, 180 × 180 pixels, with no transparency |
| **Share image** | Shown when a page is shared on social media, unless the page has its own | JPG or PNG, 1200 × 630 pixels, up to 2 MB |

A **wide logo around 200 × 50 pixels** works best, on a transparent background. SVG stays sharp at every size. An SVG must be a plain drawing: one with scripts, links to other files or other active content is refused, because uploads are served from the site's own address. If yours is refused, export it again as a plain SVG (most design tools have an option for this), or use a PNG or WebP.

**Which logo to show where** chooses one of the two logos for the **header**, the **footer**, **sign-in screens and emails**, and the **dashboards**. For example, a dark header can show the white logo while the dashboards keep the dark one. **Show the logo in the dashboards** puts your logo at the top of the dashboard menus; when it's off they show the site name.

If a place's chosen logo isn't uploaded, the website header and footer show the site name instead.

### Colours

| Setting | What it changes |
|---|---|
| **Site colour** | The main colour of the public website: buttons, links, highlights, and the blog, pricing and sign-in pages. Lighter and darker shades are worked out from it, and the header and footer follow it unless they have their own colours |
| **Site secondary colour** | Highlights and accents on the public site |

| **Global colours** | Your own named colours (for example *Sunrise orange*), offered in every block's colour settings beside the site colours. A block saves which global colour it uses, so changing one here changes every block using it. A colour can be see-through (8-digit hex, such as `#ffffff80`) |

A warning appears if white text on buttons in your site colour would be hard to read.

Deleting a global colour puts the blocks using it back to their standard look. In a block, **Unlink** swaps a global colour for its current value, so that block no longer follows it.

## Typography

| Setting | What it changes |
|---|---|
| **Website › Heading font** / **Body text font** | Headings (page titles, banner and section headings) and all other text on the website |
| **Body text size** | Small (15px), Standard (16px), Large (17px) or Extra large (18px). Larger text is easier to read for many people |
| **Heading sizes** | Compact, Standard or Large, for every heading at once |
| **Dashboards › Heading font** / **Body text font** | The same for the dashboards |

All fonts are **stored on the site itself**. They're never loaded from Google or other font services, so visitors' details aren't shared with anyone when a page loads. New fonts are added by a developer, and then appear in every font list.

### Section headings

The headings that introduce a part of a page, such as a block's heading.

| Setting | Choices |
|---|---|
| **Weight** | Semi-bold, Bold (standard) or Extra-bold |
| **Decoration** | None (standard), a short bar under the heading, a full-width line under it, or a short bar above it |
| **Decoration colour** | On light backgrounds. Blank uses the site colour |
| **Decoration colour on dark backgrounds** | In blocks with a dark background. Blank matches the heading text, usually white |

Each block can choose a different decoration, or none, on its Design tab (Text toggle).

### Text colours

Blocks use **dark text** on light backgrounds and **light text** on dark ones. Set the heading and body colours of each. Blank keeps the standard near-black and white. Colours set on a block itself, such as a card's title colour, still win.

## Buttons

- **Button corners:** Square, Slightly rounded, Rounded (standard) or Pill, for every button.
- **Four button styles,** named *Primary*, *White*, *Outline* and *Dark* as standard. Each has a **Name** (shown when choosing a style for a button on a page), background, text and border colours, each with a hover colour, **Capital letters**, and **Lift on hover**.
- **Automatic buttons:** buttons set to *Automatic* use one style **on light backgrounds** and another **on dark backgrounds**, so they always stand out.

## Corners & shadows

| Setting | Choices |
|---|---|
| **Cards** | None, Small, Medium (standard) or Large |
| **Images** | None, Small, Medium (standard) or Large. Each image block can choose its own |
| **Form fields** | Match the buttons (standard), Square, Slightly rounded or Rounded |
| **Shadows** | None, Soft (standard) or Strong. This is the *Site style* shadow that blocks, panels and cards can use |

## Header & footer

The header and footer **colours**. Their links are set in [Navigation](navigation.md). Hover colours are also used when a link or button is pressed or reached with the keyboard.

**Header:** background and text (site name and menu icon), menu links and their hover colour, dropdowns and mega menus (background, links, hover, and the column headings and descriptions), and the header button (background, text and their hover colours). The background is used for the mobile menu too.

**Footer:** background, text and headings, links and their hover colour, and social icons and their hover colour. Social icons only show for networks with a link in the app's general settings.

**Announcement bar:** the bar that shows announcements from Admin › Announcements, on the website and in the dashboard. Background, text (also the close button), and the colour of links in the announcement and their hover colour. Left blank, it keeps SaaSykit's standard look, in shades of the site's secondary colour.

Your app may add sections of its own here, for example the colours of a booking box.

## Banners

Most public pages open with a full-width banner holding the page title:

| Banner | Used on |
|---|---|
| **Hero** | The home page |
| **Inner pages** | Other pages, such as the blog, Privacy Policy and Terms of Service, and your pages that show the standard page banner |

Both have the same settings. Left blank, a banner is a gradient in shades of the site colour with a white heading.

**Background:** *Gradient* (start, end and optional middle colour, and the direction), *Colour* (one solid colour) or *Image* (JPG, PNG, WebP or AVIF, up to 2 MB; around 1920 × 600 pixels works best). For an image, choose which part stays in view when it's cropped on narrow screens, and **Add a colour overlay** so the text stands out. The overlay is *One colour* (its colour and **Overlay opacity**) or a *Gradient* that fades from a start colour and strength to an end colour and strength, top to bottom, bottom to top, left to right or right to left. A gradient lets part of the picture show clearly while the text sits on the darker end. A warning appears if text may be hard to read on a busy image: an overlay should be 40% or more (for a gradient, at its stronger end). Settings for the other background types are kept, so you can switch back.

**Text:** the heading colour and the subtitle colour.

## Dashboards

The main colour of each dashboard, such as the admin panel and the customer dashboard. Leave blank to keep its standard colour. Giving the admin panel a colour of its own makes it obvious when you're in admin.

## Custom CSS

Your own CSS for the public website, added last so it wins over everything else. Check the site after saving: CSS can break the layout. For safety, `<`, `@import` and files from other websites aren't allowed: `url()`, `image-set()` and `src()` can point to files on this site (starting with `/`), inline images or a `#` reference. The CSS is checked the way a browser reads it, so a comment or an unclosed `url(` can't hide a file from another site. CSS that can't be checked isn't saved.

Mistakes that don't make the CSS unsafe are saved but listed, each with its line: a property CSS doesn't have (for example `colour` instead of `color`), an extra or missing `{ }` or `( )`, a string or comment that's never closed, and a declaration outside any `{ }`. An extra `}` matters most: the browser can skip the rules after it. Custom properties (`--name`) and vendor-prefixed ones (`-webkit-…`) are always accepted.

Blocks have a **CSS classes** setting (Advanced › CSS ID & classes) for styling one block with your CSS. `fixed` and `sticky` aren't allowed there: they could pin a block over the rest of the page.

## Your app's own tabs

Your app may add tabs of its own after these, for settings of its own features: for example the text, colours, button style and banner of a sign-up box. They work like the rest of Branding:
- leave a field blank to keep its standard text or look;
- button styles offer the four styles from the Buttons tab, plus *Automatic*, which suits the background;
- colours warn when text may be hard to read;
- banners have the same settings as the hero and inner banners.
