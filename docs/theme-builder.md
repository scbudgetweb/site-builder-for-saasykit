# Theme builder

**Where:** Admin › Website › Theme builder
**Who can use it:** administrators with the page permissions (*view*, *create*, *update* and *delete pages*), as for pages and the block library.

The Theme builder lets you build the site's **header** and **footer** from blocks, as you build a page, and choose where each one is shown. Wherever a template applies, it's shown in place of the standard header or footer. With no template, the standard ones show, styled in [Branding](branding.md) and filled from [Navigation](navigation.md), so the site looks right without any templates at all.

## Making a template

1. Go to *Website › Theme builder* and click **New template**.
2. Give it a **Name** (only shown in the admin), choose **What it is** (Header or Footer) and where it's **Shown on**:
   - **Every page**;
   - **Only the pages chosen**;
   - **Every page except those chosen**.
3. Click **Create**. It opens in the visual builder, on its own (the site's current header and footer are hidden while you work on it).
4. Add blocks with **+**, as on a page. A header usually starts with a **Section** with a row of columns (for example ¼ + ½ + ¼): the **Logo** in the first column, the **Menu** in the middle and the **Account links** at the end.
5. **Save**. If the template is on, visitors see it straight away wherever it applies.

**On** (in the list, and in the template's form) turns a template on and off. Turn it off to work on it without visitors seeing it.

What a template is (header or footer) can't change once it's made, because some blocks only fit one or the other.

### Which template is shown

Where more than one template could apply to a page, the one for **chosen pages** wins; otherwise the **most recently saved**. Pages that aren't built in the site builder (the blog, for example) use templates shown on **every page** (or every page except those chosen).

## Template blocks

Besides every ordinary block (text, buttons, images, social icons…), templates have blocks of their own. They're only offered in templates.

| Block | In | What it shows and its settings |
|---|---|---|
| **Logo** | Header, footer | The Branding logo (the header's or the footer's), linking to the home page; its height and line-up. With no logo uploaded, the site's name |
| **Menu** | Header, footer | The header links from Navigation, with dropdowns and mega menus, and a menu button for the mobile menu on smaller screens. Its line-up, where the menu button takes over (tablet or desktop size), and its own link, hover and mobile menu colours (blank: the Branding header colours) |
| **Account links** | Header, footer | *Log in* and a button (e.g. *Get started*) for visitors, and the account menu once signed in. Their text, the button's link, and whether to show the button |
| **Footer links** | Footer | The link columns from Navigation › Footer, in up to four columns |
| **Copyright** | Header, footer | The copyright line from Navigation › Footer, or your own text (`:year` becomes the current year) |

Everything else works as on a page: design options, copying and pasting styles, undo and redo, and *Edit as a form*.

## Speed

A template is cached, so a header and footer on every page add almost nothing to it. Parts that are different for each visitor (the account links, and which menu link is the current page) are filled in on each request. A template is cached again whenever it, a page, the block library, Branding or Navigation is saved.

A template that can't be shown (because of a broken block, say) is skipped, and the standard header or footer shows instead.
