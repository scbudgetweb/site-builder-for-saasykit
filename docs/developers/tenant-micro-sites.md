# Building tenant micro-sites

The site builder edits **one** public website. Per-organisation sites (a page for each tenant at `{label}.example.com`) aren't part of the package (D0a-4). This guide is for building them in a host app. It's drawn from a production build in an earlier SaaSykit app: career pages for each employer on a job board.

The shape that worked: each tenant gets a small site on a **subdomain of the main host**, on a fixed template filled from data the tenant already keeps (name, logo, about text, images, colours). It's one page plus its detail pages, not a website the tenant builds.

## 1. Start with a host guard

Before any micro-site exists, every subdomain probably serves the **whole main site**, which is duplicate content with the wrong canonical address. Build the guard first and ship it on its own.

- **Make it global middleware, not `web` group middleware.** Filament panels don't use the `web` group, so `/admin` on a subdomain would slip past a `web`-only guard.
- **Decide by path.** Routing hasn't run when global middleware does. Allow only the micro-site's paths (`/`, its detail pages) and Livewire's (`livewire-{hash}/*`, the update endpoint and, unless published, the JS route).
- **Redirect everything else.** Use a 301 to the same path and query on the main host: log-in, registration, dashboards, admin, search and anything unknown.
- **Handle the edge cases:**
  - an unknown label, a switched-off feature, or a tenant that shouldn't have a site → 301 to the main host;
  - nested subdomains (`a.b.example.com`) → 301 too, or the main site's routes answer them;
  - leave the main host, `www.` and unrelated hosts (a hosting preview domain, an IP address) alone.
- **Resolve once.** Put the resolved tenant on the request (an attribute) so controllers don't look it up again.

## 2. Store the label, never the host

Store only the subdomain **label** (`acme-golf-club`), never the full host. Database copies then work across local, staging and production, and across sites sharing the code. SaaSykit's `tenants.domain` column (unique, nullable) suits it, and is compatible with Filament's `tenantDomain()` if dashboard subdomains are ever switched on.

- Make the label from the tenant's name: slug it, limit it to 63 characters, and avoid a **reserved list** (`www`, `admin`, `api`, `app`, `mail`, `dashboard`, `static`, `assets`, `cdn`, `blog`…).
- Add `-2`, `-3` and so on until it's unique.
- Don't generate one for placeholder names (SaaSykit's auto-named workspaces). Generate it on the first real name.
- Don't change it when the tenant is renamed. Let admins edit it, with format, reserved and unique checks, and warn that the old address stops working.
- Backfill existing tenants in a data migration, chunked by ID, numbering clashes in ID order.

## 3. Routes

Put a `Route::domain('{label}.'.$mainHost)` group **before** the app's other routes, and check the order really holds (match a request in a test). Otherwise the main site's `/` route answers first. `route:cache` bakes in the host, which is fine per environment.

## 4. Links across hosts

Laravel builds URLs from the request's host, so on a subdomain `route('pricing')` points at the subdomain.

- Build **main-site links explicitly** (a helper like `mainUrl($path)`): Privacy Policy, Terms, log-in, apply, the main logo.
- Pass links **into** shared partials rather than building them inside, wherever a partial is reused on micro-sites.
- **Never** call `URL::forceRootUrl()` or `useOrigin()` on micro-site requests. Livewire's update URL would move to the main host, giving cross-origin requests and CSRF failures. Livewire works on the subdomain as long as nothing moves it.
- Anything missed still works: the host guard redirects it to the main host.

## 5. Search engines

- Decide whether micro-sites are for search engines. On the job board they weren't: every micro-site page had `noindex, follow`, no canonical and no structured data, and the main site's pages kept their SEO.
- `robots.txt` is shared by every host, so it must **not** block subdomains, or search engines can't see the `noindex`.

## 6. Cookies and consent

Cookies are per host, so the session, CSRF token and cookie banner choice don't carry over from the main site. The banner shows again on each micro-site, which is acceptable and the simplest honest option. Micro-sites need no log-in, only Livewire's token. Keep the main site's Privacy Policy and Terms links in the footer, because the main site collects the data.

## 7. Look and feel

- Reuse the main site's Branding variables, then let each tenant override a few, such as the primary colour, the header colour and fonts. Output only valid values as CSS variables, and check contrast (`Colour::isReadableTextOn()`, `hasAccessibleButtonContrast()`).
- Let admins, not tenants, add custom CSS, checked with the same rules as Branding's (`Support\CustomCss`).
- Keep admin-only values out of the tenant's form state entirely, not just hidden.
- Use a layout of its own, without the main site's navigation.
- Classes chosen in PHP need a Tailwind `@source`. Classes written literally in Blade are simplest.

## 8. DNS, SSL and hosting

Each site needs:
- a **wildcard DNS record** (`*`) pointing at the app;
- the web server answering every subdomain from the same document root;
- a **certificate** covering `*.example.com`.

Behind Cloudflare, the edge certificate covers the wildcard. *Full (strict)* with a Cloudflare origin certificate is better than *Flexible*. Locally, Herd serves `*.{site}.test` with a wildcard certificate.

Check a made-up subdomain returns the guard's redirect before launching.

## 9. Tests worth having

- **Host guard:**
  - the main host and `www.` are untouched;
  - a valid label serves `/` and its detail pages;
  - log-in, dashboards and admin redirect with the same path and query;
  - unknown labels and nested subdomains redirect.
- **Labels:** made from the name, reserved words avoided, numbered when taken, 63-character limit, not changed on rename.
- **Pages:**
  - another tenant's record gives a 404;
  - `noindex` is present;
  - footer links point at the main host;
  - unsafe custom CSS isn't output.

Use unique labels in tests, because the app suite shares a database.
