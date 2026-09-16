# Performance and SEO baseline

Lab measurements required by `ARCHITECTURE.md §12`. The first section is the
pre-rewrite storefront (the imported tree on Laravel 10) and is the reference every
Phase 4 area must beat. Each area appends its own section when it closes.

## Method

- Lighthouse 12, performance category only, default mobile profile
  (Moto G Power emulation, simulated slow 4G, 4x CPU slowdown), one run per
  page. Chrome for Testing 153 headless.
- Server: `php artisan serve` with 8 workers on the local WSL machine,
  MariaDB 10.11 on the same host, anonymised production fixture, product
  images replaced by a 20 KB placeholder, Scout `collection` driver.
- Because the dev server does not compress responses, transfer sizes are
  uncompressed. Production serves gzip; compare the ratios between areas,
  not the absolute bytes, or re-measure behind the same web server.
- DB queries and server time measured in-process by dispatching the request
  through the HTTP kernel three times and keeping the fastest run, with the
  category navigation cache already warm (it is filled at boot by
  `AppServiceProvider`).
- Cart was measured empty. Checkout redirects to the cart when it is empty,
  so its row is the cart with one redirect; it must be re-measured with a
  filled cart when Phase 4(e) starts.

## Pre-rewrite storefront — 2026-09-11, commit `18b1e04` (Laravel 10 tree)

| Page | Score | FCP s | LCP s | CLS | TBT ms | Transfer KB | CSS KB | JS KB | Requests | TTFB ms | DB queries | Server ms | HTML KB |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Home `/` | 57 | 5.7 | 8.8 | 0.000 | 159 | 1654 | 129 | 271 | 117 | 153 | 193 | 85 | 203 |
| Category `/categorie/{slug}` | 61 | 5.0 | 8.1 | 0.000 | 108 | 1618 | 133 | 650 | 85 | 1103 | 217 | 1060 | 250 |
| Product `/prodotti/{slug}` | 63 | 4.1 | 6.8 | 0.033 | 61 | 1063 | 128 | 272 | 86 | 2398 | 110 | 2351 | 100 |
| Cart `/carrello/riepilogo` (empty) | 68 | 4.0 | 6.3 | 0.001 | 38 | 900 | 129 | 272 | 65 | 36 | 0 | 2 | 61 |
| Checkout `/checkout/account` (→ cart) | 64 | 5.0 | 6.9 | 0.000 | 40 | 901 | 129 | 272 | 66 | 35 | 0 | 1 | 0 |
| Listing `/prodotti` | – | – | – | – | – | – | – | – | – | – | 97 | 1216 | 274 |

Targets from `ARCHITECTURE.md §12`: LCP ≤ 2.0 s, CLS ≤ 0.05, TBT ≤ 150 ms,
CSS ≤ 40 KB, JS ≤ 120 KB, ≤ 30 queries per page. Every page fails LCP, CSS
and JS; home fails TBT; home, category, product and listing fail the query
budget.

A first measurement taken the same day had an empty category menu because
the navigation cache had been filled while the database was still
schema-only; those numbers were lower (home 101 requests, LCP 8.0 s) and
were replaced by the table above. Lesson: `cache:clear` after loading a
fixture.

### What the numbers say

- **LCP is dominated by render-blocking CSS and JS from third-party CDNs**:
  UIkit CSS and JS, Bootstrap CSS, Select2, jQuery, a Font Awesome kit and
  the Algolia client are all loaded synchronously in `head`
  (Lighthouse: render-blocking resources 2.5–3.3 s on every page).
  Unused CSS is 0.8–1.25 s per page. On home the LCP element is a heading,
  on category the first product thumbnail, on product a paragraph: the
  page paints late as a whole rather than waiting for one large image.
- **Category JS transfer is 650 KB** against 270 KB elsewhere: the
  Livewire listing pulls the noUiSlider bundle and extra scripts.
- **Server time is the second problem on catalogue pages**: product page
  2.4 s and 110 queries, category 1.1 s and 217 queries, home 193 queries
  in 85 ms (cheap queries, many of them: N+1 on variants and media).
  Product time is spent in printing-price computation, not in I/O.
- **CLS is good** (≤ 0.033 everywhere) and must stay so.
- **117 requests on home, 85 images**: product thumbnails without lazy
  loading plus one icon per category in the navigation menu, loaded on
  every page (the menu alone is ~30 image requests).

### Known identity leaks found while measuring (for Phase 4a)

`head.blade.php` loads a Font Awesome kit by account id and the Zendesk
widget by key from config; both are installation identity and go behind
`config/brand.php` / feature flags when the layout area is rewritten.

## SEO baseline (pre-rewrite) — 2026-09-11

Measured on the rendered HTML of the same fixture (`grep` on the response).

| Page | `<title>` | meta description | `h1` count | canonical | JSON-LD | robots meta |
|---|---|---|---|---|---|---|
| Home | yes | yes | 1 | yes | none | none |
| Category | yes | yes | **2** | yes | BreadcrumbList | none |
| Product | yes | yes | 1 | yes | Product + Offer | none |
| Listing `/prodotti` | yes | no | 0 | no | none | none (blocked only by robots.txt) |
| Cart | yes | no | 0 | no | none | none |

Gaps against `ARCHITECTURE.md §12` to close in Phase 4: no `Organization`
or `WebSite` JSON-LD on home; two `h1` on category pages; no canonical and
no description on the listing; no `noindex` meta on cart, listing and
search results (only `robots.txt` rules today); no sitemap route (the file
is generated by an artisan command into `public/`).

## After the framework upgrade — 2026-09-11, commit `bdf630d` (Laravel 13)

Same fixture, same machine, in-process metrics run back to back against
the Laravel 10 worktree. Query counts are identical on every page; server
time differs by less than the run-to-run noise on this machine (home 85 →
107 ms, category 1060 → 1138, product 2351 → 2293, listing 1216 → 1371).
The Lighthouse figures above therefore stand as the reference for Phase 4.

## Phase 4(a) — layout and chrome — 2026-09-11

Same method (Lighthouse 12 mobile, dev server without compression, one run
per page, placeholder images). The chrome is Tailwind 4 + Alpine in one Vite
bundle (app.css 26 KB / 5.8 KB gzip, app.js 232 KB / 78.5 KB gzip including
Livewire and Alpine). The legacy UIkit, jQuery, Select2 and Bootstrap
assets are still loaded from `frontend.public.legacy-assets` because the
page bodies of the other areas depend on them; they are removed with 4(h).

| Page | Score | FCP s | LCP s | CLS | TBT ms | Transfer KB | CSS KB | JS KB | Requests | TTFB ms |
|---|---|---|---|---|---|---|---|---|---|---|
| Home | 56 | 6.5 | 8.4 | 0.000 | 190 | 1341 | 155 | 433 | 79 | 112 |
| Category | 60 | 6.0 | 6.8 | 0.001 | 148 | 1077 | 159 | 461 | 47 | 1166 |
| Product | 64 | 5.2 | 5.7 | 0.001 | 93 | 907 | 155 | 433 | 52 | 2360 |
| Cart (empty) | 67 | 5.0 | 5.7 | 0.003 | 61 | 743 | 155 | 433 | 31 | 24 |

What changed and what did not:

- **Requests: −38 on home, −38 on category, −34 on product, −34 on cart.**
  The category icons in the navigation are now lazy, the Font Awesome kit
  and the Algolia browser client are gone (header search calls the core
  suggest endpoint), the icon PNGs are inline SVG.
- **Transfer: −313 KB on home, −541 KB on category, −156 KB on product.**
- **JS transfer is up (+160 KB uncompressed)** because the new bundle is
  added on top of the legacy scripts; it goes back below budget when the
  legacy bundle leaves in 4(h) (the new bundle alone is 78.5 KB gzip).
- **LCP and FCP do not move**: they are bound by the render-blocking
  legacy CSS and JS (3.1 s on home), not by the chrome. The LCP element is
  now the logo on home, category and cart. The area's own lab target
  (LCP ≤ 2.0 s) is therefore not met yet and cannot be until the legacy
  assets are removed; each following area shrinks that bundle.
- **CLS stays ≤ 0.003.** No console errors from the chrome; the one 404
  on home is a home-page image removed in Phase 1 (area 4b fixes it).

## Phase 4(b) — home — 2026-09-11

Same method. Home rebuilt with Tailwind components (`home.slideshow`,
`home.product-slider`, `product.card`, `seo.meta`); legacy assets still
loaded for the other areas.

| Page | FCP s | LCP s | CLS | TBT ms | Transfer KB | CSS KB | JS KB | Images KB | Requests | DB queries (warm cache) | Server ms | HTML KB |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Home | 5.6 | 6.5 | 0.000 | 90 | 1078 | 160 | 433 | 249 | 44 | 0 | 24 | 177 |

Against the pre-rewrite home: requests 117 → 44, transfer 1654 → 1078 KB,
images 3.3 MB → 249 KB (thumbnails are 150 px conversions, lazy below the
fold, the hero is one eager image), TBT 159 → 90 ms, queries 193 → 0 with
warm caches (promo and green products are cached with their colour variants
and main variant eager-loaded; before, every card ran its own queries).
LCP 8.8 → 6.5 s and FCP 5.7 → 5.6 s: still bound by the render-blocking
legacy bundle; the LCP element is the header logo.

Lesson recorded in the slider component: a scroll-snap track whose first
snap point is not at 0 scrolls on load, and a scroll before first paint
makes Chrome drop LCP reporting entirely (Lighthouse `NO_LCP`). Any future
carousel must keep its first snap point at 0.

## Phase 4(c) — catalogue and search — 2026-09-11

Same method. Listing, category and brand pages rebuilt; the Livewire list
owns its filters (checkbox groups, price bounds, sort, page size) and the
grid; pagination emits real links. Filter options and the sidebar navigation
are computed by `App\Support\CatalogFilterOptions` (cached one hour) and
stay out of the Livewire snapshot; option lists longer than 12 entries
render a preview with a "show all" action.

| Page | FCP s | LCP s | CLS | TBT ms | Transfer KB | CSS KB | JS KB | Requests | TTFB ms | DB queries | Server ms | HTML KB |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Category | 5.3 | 5.6 | 0.007 | 122 | 891 | 159 | 413 | 38 | 47 | 9 | 35 | 148 |
| Listing `/prodotti` | 5.9 | 7.1 | 0.003 | 145 | 1048 | 159 | 414 | 40 | 75 | 8 | 62 | 131 |

Against the pre-rewrite category page: queries 217 → 9, server time
1060 → 35 ms, TTFB 1103 → 47 ms, requests 85 → 38, transfer 1618 → 891 KB,
TBT 108 → 122 ms (Livewire hydration of the list; was 283 ms before the
snapshot was trimmed), LCP 8.1 → 5.6 s. Listing: 97 → 8 queries,
1216 → 62 ms, HTML 274 → 131 KB (359 KB before the option preview: the
whole catalogue has 732 colour options). Select2, noUiSlider and
matchHeight are no longer loaded anywhere.

Query budget (≤ 30) met on both pages. LCP still bound by the legacy
bundle (render-blocking 2.5 s); the LCP element is the header logo.

## Phase 4(d) — product page and print configurator — 2026-09-11

Same method. Product page rebuilt around `App\Support\ProductPageData`
(breadcrumbs, gallery, colours, configurator data, price tables, details,
related products) and the Alpine `productConfigurator` component; the
configurator is server-rendered instead of fetched after load.

| Page | FCP s | LCP s | CLS | TBT ms | Transfer KB | CSS KB | JS KB | Images KB | Requests | TTFB ms | DB queries | Server ms | HTML KB |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Product | 5.3 | 5.5 | 0.014 | 106 | 792 | 161 | 417 | 34 | 33 | 1070 | 67 | 1059 | 148 |

Against the pre-rewrite product page: requests 86 → 33, transfer
1063 → 792 KB, LCP 6.8 → 5.5 s, TBT 61 → 106 ms, queries 110 → 67,
server time 2351 → 1059 ms. No console errors; the configurator, related
products and bestsellers no longer need three requests after load.

Still above budget: 67 queries and ~1 s of server time. They come from the
pricing helpers the page calls per table cell (`price_per_quantity` with
the default printing, markup lookups) and from `attribute_value()` per
attribute; that code is business logic that Phase 4 leaves untouched. The
catalogue response cache scheduled after Phase 6 (§12) covers the TTFB;
the query count is a Phase 5 item once the pricing pipeline is behind a
contract.

## Phase 4 close — areas (e) to (h) and legacy bundle removed — 2026-09-11

Same method as the baseline (Lighthouse 12 mobile, one run per page, dev
server without compression, placeholder images). Cart, checkout, quotation,
auth, account, contact, newsletter, CMS pages, blog, mail, PDF, error pages
and cookie consent are rewritten; UIkit, jQuery, Bootstrap, Select2 and the
legacy stylesheets are no longer loaded by any storefront page. The whole
storefront ships one CSS file (42 KB / 8.1 KB gzip) and one JS bundle
(244 KB / 82 KB gzip including Livewire and Alpine).

| Page | Score | FCP s | LCP s | CLS | TBT ms | Transfer KB | CSS KB | JS KB | Requests | TTFB ms | DB queries | Server ms | HTML KB |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Home | 75 | 4.1 | 4.3 | 0.000 | 30 | 846 | 66 | 315 | 29 | 36 | 0 | 24 | 177 |
| Category | 78 | 3.9 | 4.0 | 0.000 | 36 | 668 | 66 | 315 | 26 | 38 | 9 | 35 | 148 |
| Product | 77 | 3.9 | 4.1 | 0.000 | 28 | 563 | 66 | 315 | 21 | 783 | 67 | 1059 | 148 |
| Cart (empty) | 83 | 3.5 | 3.5 | 0.001 | 4 | 504 | 66 | 315 | 16 | 16 | 0 | 1 | 32 |
| Listing `/prodotti` | 80 | 3.8 | 3.8 | 0.000 | 34 | 826 | 66 | 315 | 28 | 66 | 8 | 62 | 131 |

Against the pre-rewrite baseline: LCP 8.8 → 4.3 s on home, 8.1 → 4.0 s on
category, 6.8 → 4.1 s on product, 6.3 → 3.5 s on cart; TBT from 159/108/61
ms to ≤ 36 ms; CLS 0 on every page; requests 117/85/86/65 → 29/26/21/16;
transfer 1654/1618/1063/900 → 846/668/563/504 KB; no console errors.

Where the remaining time goes: the dev server does not compress
(Lighthouse: "uses-text-compression" 1.6–2.3 s per page). Sizes as served
by production with gzip are the ones in the lab targets: CSS 8 KB ≤ 40 KB,
JS 82 KB ≤ 120 KB. With those transfers the simulated slow-4G LCP is well
under the 2.5 s hard limit on the pages whose server time is small; the
product page stays bound by its ~0.8–1.0 s server time (pricing helpers,
see 4(d)), which the catalogue response cache after Phase 6 addresses.
LCP elements are now text or the h1 on every page except the home hero.

Query budget: met on home, category, listing, cart; product page at 67 (see
4(d)).

## v2b.0 — model hardening — 2026-09-13

Same method, same three pages, after casts, soft deletes, the SEO fields
migration, the brands table and the legacy redirects. Nothing moved: the
storefront is unchanged by construction (the new columns are only read when
set).

| Page | Score | FCP s | LCP s | CLS | TBT ms | Transfer KB | CSS KB | JS KB | Requests | TTFB ms |
|---|---|---|---|---|---|---|---|---|---|---|
| Home | 76 | 4.1 | 4.3 | 0.000 | 27 | 819 | 59 | 315 | 29 | 31 |
| Category | 78 | 3.9 | 4.0 | 0.000 | 44 | 661 | 59 | 315 | 26 | 613* |
| Product | 77 | 4.0 | 4.1 | 0.000 | 35 | 556 | 59 | 315 | 21 | 863 |

\* first request after `cache:clear` (filter options cache cold).

## v2b.7 — Livewire 4 + Filament 5 — 2026-09-14

Same method, same three pages, after the upgrade (Livewire 3.8 → 4.4,
Filament 4.13 → 5.8). Livewire 4 is a bigger runtime: the storefront bundle
goes from 244 KB / 82 KB gzip to 356 KB / 117 KB gzip, still inside the
120 KB gzip JS budget but with little room left. Lab metrics unchanged
within noise, no console errors on any page nor on the admin login.

| Page | Score | LCP s | CLS | TBT ms | JS transfer KB (uncompressed) |
|---|---|---|---|---|---|
| Home | 78 | 4.1 | 0.000 | 28 | 348 |
| Category | 81 | 3.8 | 0.000 | 33 | 348 |
| Product | 76 | 4.5 | 0.000 | 40 | 348 |

Next lever on the JS budget: Livewire 4 lazy/islands for the listing
component, or the CSP-safe build if a CSP is adopted.

## v2d — storefront flows — 2026-09-17

Same method (Lighthouse 13.4, Chrome for Testing 153, mobile, one run),
`php artisan serve` on the demo catalogue (`DemoSeeder`, 41 KB placeholder
thumbnails), core defaults (panel configurator, steps checkout, quotation
page). The product-page components (both configurators, the sample
request) are a lazy chunk: main bundle 355 KB / 117 KB gzip, chunk
13.5 KB / 3.8 KB gzip.

| Page | Score | FCP s | LCP s | CLS | TBT ms | Transfer KB | JS KB | Requests | TTFB ms |
|---|---|---|---|---|---|---|---|---|---|
| Home | 83 | 3.3 | 3.6 | 0.000 | 20 | 538 | 347 | 26 | 27 |
| Category | 85 | 3.2 | 3.5 | 0.000 | 13 | 464 | 347 | 9 | 52 |
| Product | 81 | 3.5 | 3.8 | 0.000 | 74 | 557 | 360 | 11 | 86 |

Measured on the gesca84 preview (all flows on, skin, fonts) the same
pages score 77 / 81 / 77 with CLS 0 after two core fixes found there:
the gallery box sized to the image (`flex-1` in the column layout) and
the first product tab hidden by `x-cloak` until Alpine started, each
worth a 0.16 shift when the image or the script arrives after first
paint. Query budget: home and category within 30; product page 89 with
the tabs and the modal configurator (the price helpers, as in 4(d)).

