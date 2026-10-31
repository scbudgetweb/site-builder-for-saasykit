# Theme builder

**Where:** Admin › Website › Theme builder
**Who can use it:** administrators with the page permissions (*view*, *create*, *update* and *delete pages*), as for pages and the block library.

The Theme builder lets you build the site's **header** and **footer** from blocks, as you build a page, and choose where each one is shown. With a blog, it can also lay out **blog posts** and the **blog's pages of posts** (see [Blog post templates](#blog-post-templates) and [Blog archive templates](#blog-archive-templates)). Wherever a template applies, it's shown in place of the standard header or footer. With no template, the standard ones show, styled in [Branding](branding.md) and filled from [Navigation](navigation.md), so the site looks right without any templates at all.

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

## Blog post templates

When your app has SaaSykit's blog, the Theme builder offers a third kind of template, **Blog post**. It lays out each post's page: the template's blocks are the page, between the site's header and footer, and the post's title, description and image still go to search engines and share previews.

- **Shown on:** every post, only posts in the categories chosen, or every post except those in the categories chosen. As with pages, one for chosen categories wins, then the most recently saved. With no Blog post template, posts show as they always have.
- **A new one starts with the standard layout:** a narrow Section holding the post's title, details, image, text and share links, then Related posts under it. Change, move or remove any of them, or add any other block (a Mailing list sign-up, say).
- **On the canvas,** your latest published post fills the blocks, or a sample post before there are any.

Its own blocks:

| Block | What it shows and its settings |
|---|---|
| **Post title** | The post's title, as a Heading block draws one: its level (H1 as standard), alignment and Design › Heading text |
| **Post details** | The date, author and category on one line, each optional; the category, as a pill or a link, goes to its posts |
| **Featured image** | The post's first image, as uploaded or trimmed to a shape, with corners, border and shadow. Nothing shows for a post without one |
| **Post content** | The post's text, as written in Blog › Posts, styled by Design › Heading text and Body text |
| **Share links** | Icons to share the post on X, LinkedIn or Facebook, or by email, with an optional heading |
| **Related posts** | Cards for more posts, from the same category first (or just the latest), up to 12, in 2 to 4 columns. Each card can show its image, date, category and description; Design › Items styles them. **When empty** decides what shows when there are no other posts |

Any text can also show the post's details as [dynamic content](pages.md#block-settings): `{{ post_title }}`, `{{ post_excerpt }}` (its description), `{{ post_date }}`, `{{ post_author }}` and `{{ post_category }}`. On other pages they're blank, or their fallback (`{{ post_title | our news }}`).

## Blog archive templates

A **Blog archive** template lays out the blog's own page (`/blog`) and each category's page, in the same way.

- **Shown on:** the blog and every category, only the categories chosen, or the blog and every category except those chosen. The blog's own page has no category, so a template for chosen categories never shows there. With no Blog archive template, they show as they always have.
- **A new one starts with the standard layout:** a Section with the archive's title, then its posts.
- **On the canvas,** the whole blog's posts fill it.

Its own blocks:

| Block | What it shows and its settings |
|---|---|
| **Archive title** | The category's name on a category's page; on the blog's own page, *The blog's title* (*Blog* if blank). An optional line under it, its level (H1 as standard), alignment (centre as standard) and Design › Heading text |
| **Post list** | The archive's posts, newest first, as cards (as Related posts'), a page at a time: *Posts per page* (12 as standard, up to 48) and *Links to more pages*, with Previous and Next and the page numbers (on phones, Previous, Next and *Page 1 of 3*). Off, only the latest show. **When empty** decides what shows when there are none |

Any text can show `{{ archive_title }}`: the category's name, or *Blog* on the blog's own page.

### Latest posts on any page

The **Latest posts** block, in the block picker whenever your app has a blog, shows the newest posts as the same cards on any page or template: how many (up to 12), from every category or one, and what each card shows. A home page's *From our blog*, say.

## Speed

A template is cached, so a header and footer on every page add almost nothing to it. Parts that are different for each visitor (the account links, and which menu link is the current page) are filled in on each request. A template is cached again whenever it, a page, the block library, Branding or Navigation is saved.

The post's own blocks are drawn for each post, while the rest of a Blog post template stays cached.

A template that can't be shown (because of a broken block, say) is skipped, and the standard header, footer or post shows instead.
