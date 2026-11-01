# Navigation

**Where:** Admin › Website › Navigation
**Who can use it:** anyone who can update pages

Choose the links in the website's **header**, how the **mobile menu** looks, and what's in the **footer**. Until you save anything, the site shows its standard links.

The settings are on three tabs: **Header**, **Mobile menu** and **Footer**. One **Save changes** button saves all three.

## Links

Every link in the header, a dropdown, a mega menu or the footer points at one of these:

| Link to | What it does |
|---|---|
| **A page you built** | One of your [pages](pages.md). If you change the page's address, or it replaces a built-in page, the link follows it. While the page is a draft or in the bin, the link is hidden. Drafts are marked *(draft, hidden until published)* in the list, for people who can see the pages list (otherwise only published pages are listed) |
| **A built-in page** | A page the site already has, such as Home, Blog, Log in, Create account, Dashboard, Privacy Policy or Terms of Service. Only pages that apply to your site are listed: Pricing only when the home page has a pricing section, and Roadmap only while the roadmap is switched on. Your app may add more, or leave some out. A link that stops applying stays in your menu but is hidden, and shows as *(not available on this site, so hidden)* |
| **A web address** | Any address starting with `https://`, `/` (a page on this site), `#` (a part of the page), `mailto:` or `tel:` |
| **Nothing** | Header links only: the link just opens its dropdown or mega menu |

Each link has a **Label**. For a page or built-in page it's optional: leave it blank to use the page's own name. **Open in a new tab** is for links to other websites.

The header link for the page a visitor is on is highlighted.

## Header

Up to **6 links**. The **Log in** and **Get started** buttons, or the account menu once someone has logged in, are added automatically.

Each header link can open a menu. Choose its **Menu style**:

- **Dropdown:** a short list of up to 8 **Dropdown links** under the link.
- **Mega menu:** a wide panel under the header with up to 4 **columns**, each with an optional heading and up to 8 links. Each link can have a **short description** and an **icon**. A **highlight panel** on the right can add a heading, a line of text and a button.

Dropdowns and mega menus open when the mouse is over the link, or when its arrow is clicked or reached with the keyboard. **Esc** closes them.

## Mobile menu

On phones and tablets, the menu button opens a menu that scrolls on its own. Dropdowns and mega menus open and close inside it, and one holding the current page starts open. These settings apply to every mobile menu on the site: the standard header's, a Menu block's and a Header bar's.

| Setting | What it does |
|---|---|
| **Opens as** | Drops down from the top, slides in from the right or the left (a panel, with the page still showing beside it on tablets, dimmed), or fills the screen with large links in the middle |
| **Blur the page beside it** | For the side panels: the page behind is blurred as well as dimmed. A tap on it closes the menu |
| **Show the logo at the top** | The header logo from Branding, or the site name when none is uploaded. On a background of its own, the menu shows Branding's logo that reads on it: the one for dark backgrounds under light text, and the one for light backgrounds under dark text |
| **Text under the logo** | An optional short line, for example *Book online in minutes* |
| **Links: Size** | Normal, Large or Extra large (both in the heading font) |
| **Links: Line up** | Left or centre (filling the screen, always centre) |
| **Lines between the links** | A thin line between each link |
| **Slide the links in one after another** | As the menu opens, each link fades and slides up just after the one before. Visitors who turn off animations see them straight away |
| **Background** | The header colours from Branding, a colour, a gradient (start and end colours) or an image (with an overlay colour and strength, so the links stay readable), plus a **Text colour** (blank: white, or near-black on a light colour). A Header bar or Menu block with mobile menu colours of its own keeps those |
| **At the bottom** | **Log in** for visitors who aren't signed in, **the header's button** at full width (Get started, or a Header bar's own button), **social icons** (from Settings › General Settings › Social Links), and an optional **phone number** and **email**, which can be tapped |
| **Menu button** | Its **Icon** (three lines, two lines or three dots), **Shape** (plain, in a circle or in a rounded square) and whether **"Menu"** shows beside it. Inside the menu, its lines turn into an X to close it |

## Footer

| Setting | What it does |
|---|---|
| **Tagline** | A line under the footer logo. Leave it empty to hide it |
| **Link columns** | Up to **4 columns** beside the logo, each with an optional heading and up to 10 links |
| **Copyright line** | The line at the very bottom. `{{ current_year }}` becomes this year and `{{ site_name }}` the site name, as in any text (the older `{year}` and `{site_name}` still work). It can hold links, written like `<a href="https://example.com">Example</a>`, with an address starting with `https://`, `/`, `#`, `mailto:` or `tel:`. Links are underlined, in the footer's link colours from [Branding](branding.md), and links to other sites open in a new tab. Any other HTML shows as plain text. Leave it empty for the standard line |

Social media icons in the footer come from the app's general settings (only web addresses starting with `https://` or `http://` are linked), and the footer's colours from [Branding](branding.md).

## Reset to default

**Reset to default**, at the top of the page, replaces your header, footer and mobile menu with the standard ones. Before anything changes, it lists the links each menu will get (only those that apply to your site) and asks you to confirm, because your own links are removed and it can't be undone.

The standard menus may come from your app rather than the site builder, for example with links to your app's own pages or a Cookie settings link. Until you save the Navigation page, the site shows them too.

Your saved menus are yours: updates to the site builder never change them, and links you haven't added never appear in them.
