# Mercatura — Architecture and core/installation contract

Status: v1, September 2026. Governs the v2a work and every installation
built on this core. Stack details are in `01_STACK_SPECIFICATION.md`; this
document does not repeat them.

## 1. Purpose and topology

Mercatura is a neutral e-commerce core. Customers are served by NereauWeb as
**installations**: separate private repositories that clone the core, add a
skin and configuration, and nothing else.

```
nereauweb/mercatura                     (public, AGPL-3.0)  the core: code, default storefront, migrations, demo skin, demo data
nereauweb/mercatura-connector-<key>     (private)           one Composer package per supplier import (§13)
nereauweb/mercatura-<client>            (private)           core + resources/skins/<client> + config + require of its connectors
```

Three roles: the core (functional code, public), the connectors (supplier
code, private packages consumed by installations, never by the core), the
installations (skin, configuration, data, requires). Supplier code never
enters the core and functional code never lives in an installation.

Two flows, one direction each:

- **core → installation**: `git merge upstream/main`. Clean by construction if
  the contract in §2 holds.
- **installation → core**: functional work is developed on a branch created
  from `upstream/main`, opened as a PR against the core, and reaches the
  installation through the next merge. Nothing functional lives only in an
  installation.

The core is deployed on its own as the development, test and demo instance,
seeded with neutral data. It is never a customer site.

The long-term target is a Composer package (`mercatura-core`) consumed by
customer projects. The clone-with-upstream model is the bridge; the contract
below is designed so that the move to a package changes where files live,
not how overrides work (§10).

## 2. The contract

An installation **adds files; it never modifies core files.** Concretely, the
diff between an installation and `upstream/main` may only contain:

```
resources/skins/<name>/**
public/skins/<name>/**
.env.example
README.md
deploy/**
```

Everything else — controllers, models, migrations, routes, default views,
lang defaults, config defaults, tests, `composer.json` — belongs to the core
and is changed only there.

Corollaries:

- **No migrations in installations.** A schema change is a core change.
- **No dependency changes in installations.** Providers that an installation
  may choose between (mail transports, newsletter, payment gateways) are all
  present in the core's `composer.json`, selected by configuration. The cost
  of an unused package is lower than the cost of divergent lockfiles.
- **No route or controller overrides by editing core files.** Level 5 in §4
  is done through the container, Livewire aliases and configuration, never by
  editing.

Enforcement, both directions:

- Core CI rejects any PR touching `resources/skins/**` or `public/skins/**`
  except `skins/demo`.
- Installation CI runs
  `git diff --name-only upstream/main | grep -vE '^(resources/skins/<name>/|public/skins/<name>/|\.env\.example$|README\.md$|deploy/)'`
  and fails if the output is non-empty.
- Feature branches for core work are created from `upstream/main` in the
  installation checkout (`git checkout -b feat/x upstream/main`). The skin
  disappears from the working tree and the site renders as the core default,
  which is the correct environment for functional work.

## 3. Override mechanism

The core resolves storefront views through stable names
(`frontend.pages.home`, `@extends('frontend.public.layout')`,
`@include('frontend.elements.product-card')`, `view('livewire.frontend-product-list')`).
A skin is a directory whose files shadow those names.

Resolution: `config('mercatura.skin')` (from `MERCATURA_SKIN`) names a
directory under `resources/skins/`. If it exists, it is **prepended** to the
view finder locations at boot (`App\Support\Skin::activate()`, called by
`MercaturaServiceProvider`, the first provider in `bootstrap/providers.php`,
before any `view()` call). Controllers, Livewire components, the exception handler and
Blade directives keep using the same names; the finder is the switch.

```
resources/skins/<skin>/frontend/pages/home.blade.php    ← wins if present
resources/views/frontend/pages/home.blade.php           ← default
```

Rules:

- Skin templates use the **same** names in `@extends`/`@include`
  (`frontend.public.layout`, never `skins.<skin>...`). Skins live outside
  `resources/views/` precisely so that the skin path is not addressable as a
  view name.
- The skin name is sanitised to `[a-z0-9_-]` before building a path. No
  runtime switching: a skin is an installation property, read once at boot.
- Overlay, not copy. A skin contains only the files that differ. Installations
  do not copy the whole tree "to be safe"; that is how skins rot.
- Fallback is always on. A missing skin file means the core default renders.
  `mercatura:skin-check` reports which core views a skin overrides and fails
  if a skin file has no core counterpart (typo, or a view the core renamed).

Perimeter of the overridable surface (all follow the skin automatically
because they are resolved by name):

| Surface | Core location | Skin location |
|---|---|---|
| Storefront pages, layout, chrome, elements, partials | `resources/views/frontend/**` | `resources/skins/<name>/frontend/**` |
| Storefront Livewire views | `resources/views/livewire/frontend-*.blade.php` | `resources/skins/<name>/livewire/frontend-*.blade.php` |
| Mail templates | `resources/views/mail/**` | `resources/skins/<name>/mail/**` |
| PDF layouts | `resources/views/frontend/pdf/**`, `frontend/public/layout_pdf` | same path under the skin |
| Error pages, cookie consent | `frontend.errors.*`, `frontend.cookie-consent.*` | same path under the skin |
| Lang | `lang/it/**` | `resources/skins/<name>/lang/it/**` (added as a translator loader path after the core one, so its keys win; unlisted keys keep the core value) |
| Identity | `config/brand.php` | `resources/skins/<name>/brand.php` (merged over defaults) |
| Assets | `public/build/**` (Vite) | `public/skins/<name>/**` or a skin Vite entry |

Outside the perimeter, deliberately: `resources/views/admin/**` and anything
under `app/`. Admin is not skinnable. If an installation needs different
admin behaviour, that is a core feature behind a flag.

Assets: the core ships a compiled default bundle. A skin either provides its
own static assets under `public/skins/<name>/` and overrides
`frontend.public.head` to load them, or registers an extra Vite entry. The
default `head` never contains skin conditionals.

`resources/skins/demo` is the only skin in the core. It overrides
`frontend/public/header`, `frontend/pages/home`, `brand.php` and one lang file.
It is the template for new installations and the fixture for CI.

## 4. Override levels

Documented so that the lightest sufficient level is chosen. From lightest to
heaviest:

1. **Configuration** — `brand.php` in the skin, `.env`, feature flags. No
   Blade touched.
2. **Content** — lang keys in the skin's lang directory. No Blade touched.
3. **Injection** — named slots on components and `@stack` points in layouts.
   Content added without copying the file. The core exposes stacks at least
   at: `head`, `scripts`, `header-before`, `header-after`, `footer-before`,
   `footer-after`, `product-after-price`, `product-after-description`,
   `cart-summary-after`. Stacks are filled by pages and components rendered
   before the layout; a partial that the layout itself includes (header,
   footer) cannot push to a stack the layout has already rendered.
4. **Component or page override** — copy one file into the skin. The skin
   file carries the core version header it was copied from
   (`{{-- @mercatura-view frontend.elements.product-card @version 2 --}}`).
   `mercatura:skin-check` warns when the core version has moved past the
   copied one.
5. **Logic override** — rebind a Livewire component
   (`Livewire::component('frontend-product-list', Custom::class)` in the
   installation's own service provider) or a controller through the
   container. Requires a service provider in the skin
   (`resources/skins/<name>/SkinServiceProvider.php`, auto-registered when
   present; it must declare `Mercatura\Skins\<StudlyName>\SkinServiceProvider`).
   Rare; if it happens twice for the same reason, the core needs a flag or
   a contract.

The core is written so that levels 1–3 cover most branding work. Pages are
composition of components; long monolithic page templates are a defect.

## 5. Neutralization rules

What must never be in a core Blade file, and where it goes instead.

| Kind | Examples | Destination |
|---|---|---|
| Identity | legal name, logo, favicon, address, VAT, phone, email, IBAN/BIC, social links, GTM id | `config/brand.php` (neutral defaults) → skin `brand.php` → `.env` for secrets only |
| Copy | homepage claims, section titles, legal pages, cookie banner text, empty-state messages, email subjects and bodies | lang keys (`frontend.*`, `mail.*`) |
| Palette and type | colour classes, brand fonts | Tailwind `@theme` semantic tokens; shared markup uses `bg-primary`, `text-accent`, etc. |
| Provider code | Brevo SDK calls, Algolia client, reCAPTCHA, Stripe, PayPal, Anthropic | drivers under `app/Drivers/**` behind the contracts in `01_STACK_SPECIFICATION.md §4` (`SearchEngine`, `CaptchaProvider`, `NewsletterProvider`, `TransactionalMailer`, `PaymentGateway`, `AIEnrichmentProvider`, `PersonalizationProvider`, `ImportConnector`), selected in `config/mercatura.php` |
| Client data | product feeds, price lists, catalogue PDFs, customer lists, import files | never in the repo; DB or storage of the installation |
| Client features | supplier-specific import/print/price logic | `mercatura-connector-*` packages (see strategy doc); until they exist, behind a feature flag in the core |

Configuration layering, lowest to highest precedence:
`config/*.php` defaults → skin `brand.php` (identity only) → `.env`.
`.env` stays short: skin name, provider selection, flags, credentials, URLs.
Identity is versioned in the installation repo, not typed into a server.

## 6. Stack scope for v2a

Target per `01_STACK_SPECIFICATION.md`: PHP 8.4, Laravel 13, MariaDB 10.11,
Redis, Blade + Livewire 4 + Alpine 3 + Tailwind 4, Vite 6, Scout 11 + Algolia
v4 behind `SearchEngine`, Symfony Mailer with Brevo and Postmark transports,
Cashier 16.

In v2a: framework upgrade, storefront rewrite, neutralization, overlay,
contracts for provider code, demo data, CI.

Out of v2a (deferred, not cancelled): Filament admin including the SEO
management UI (v2b), Vue configurator island, Meilisearch/Typesense drivers beyond scaffold, Italian fiscal
exporters, newsletter MJML pipeline.

The legacy admin (Blade, UIkit) was frozen during v2a and replaced by the
Filament panel in v2b (`02_V2B_ADMIN.md`).

## 7. Storefront structure

`resources/views/frontend/`:

```
public/        layout, head, header, navbar, footer, layout_pdf
pages/         one file per route; each page composes components and declares stacks
components/    anonymous Blade components, the unit of override, used as <x-frontend::product.card />,
               <x-frontend::icon /> … (namespace `frontend` → `frontend.components.*`, resolved through the
               view finder so a skin shadows them like any view)
elements/      legacy include-style partials, migrated to components area by area
partials/      form helpers (field-error, …)
pdf/
errors/
cookie-consent/
```

Livewire storefront components render `livewire.frontend-*` views and contain
no markup beyond what Livewire requires; their markup composes the same
components pages use.

Assets: one Vite bundle (`resources/css/app.css`, `resources/js/app.js`).
Livewire and Alpine are bundled from `livewire.esm` so that Alpine exists on
every page, with or without Livewire components (`inject_assets` is off,
the layout emits `@livewireScriptConfig`). Semantic colour tokens are
`@theme` variables in `app.css`; a skin restyles by redefining them at
`:root` in its own stylesheet. The storefront loads no third-party CSS or
JavaScript from CDNs: everything is in the bundle, and third-party tags
(GTM, chat widget, captcha) are deferred and consent-gated.

## 8. Repository workflow

- `main` is always deployable to the demo instance. Feature branches, PRs,
  conventional commits, CI green (`pint`, `larastan`, `test`, `skin-check demo`,
  contract check).
- Releases are tagged (`v2.0.0`…). Installations merge tags, not arbitrary
  commits, in production.
- Installation checkouts have two remotes: `origin` (private repo) and
  `upstream` (core). `git fetch upstream && git merge upstream/vX.Y.Z`.
  An installation is created from the core (`git clone` of the core, `origin`
  renamed to `upstream`, the private repository added as `origin`), so its
  history is the core history plus the skin, the assets and the requires.
- Connectors reach an installation through Composer: `repositories` of type
  `vcs` pointing at the private connector repositories and `require`
  constraints (`^1.0`); the server pulls them with a read-only deploy key.
  `composer.lock` is the one file that conflicts on `git merge upstream`:
  take the upstream lock, then `composer update nereauweb/mercatura-connector-*`
  to add the packages back. The `connectors/<key>/` checkout (§13) is for
  development on the connector and the core at once.
- Connectors are tagged on their own (`v1.x.y`) and declare the core they
  need in `composer.json` (`extra.mercatura.core`, e.g. `^2.0`); a change to
  `App\Contracts\ImportConnector` bumps the core minor and the constraint.
- CI: the core runs its suite with no connector present (the `Connectors`
  test suite is empty there); a connector's CI checks out the core it
  declares, places the package under `connectors/<key>/` and runs `pint`,
  `larastan` and `phpunit --testsuite Connectors`; an installation runs the
  core CI plus `mercatura:skin-check <skin>` with the deploy key as secret.
- Deploy: `deploy/deploy.sh` and the templates in `deploy/` are the core's;
  an installation runs them from its own checkout and keeps no copy.
- Core feature work from inside an installation: branch from
  `upstream/main`, PR to core, merge back through the next tag.
- The core history starts clean: the tree of the originating installation
  was imported as a single commit after removal of client assets and data.
  That repository is archived, not the ancestor.

## 9. Phases and stop criteria

Each phase is a set of PRs. The stop criterion is what "done" means; it is
also what the next phase assumes.

**Phase 1 — Bootstrap.** Empty repo with `CLAUDE.md`, this document,
`01_STACK_SPECIFICATION.md`, `LICENSE` (AGPL-3.0), CI skeleton, then the
cleaned tree of the originating installation as one commit.
Stop: `composer install` and the existing test suite run; no client asset,
data file or credential in the tree or history.

**Phase 2 — Framework upgrade at parity.** Laravel 10 → 11 (skeleton change,
`bootstrap/app.php`) → 12 → 13, PHP 8.4, dependency bumps required by the
upgrade only (including Algolia SDK v3 → v4 if forced; otherwise Phase 5).
Stop: tests green, storefront and admin render on demo data as before, no
functional change, one commit range clearly labelled `upgrade:`, and a
performance/SEO baseline of the pre-rewrite storefront recorded in
`docs/PERF_BASELINE.md` (§12).

**Phase 3 — Overlay mechanism.** `config/mercatura.php`, `config/brand.php`,
view-path prepend, brand overlay merge, skin lang precedence, skin service
provider auto-registration, `mercatura:skin-check`, `mercatura:skin-override`,
`resources/skins/demo`, contract CI checks.
Stop: with `MERCATURA_SKIN=demo` the demo overrides render; with it unset the
default renders; `skin-check demo` passes; core CI rejects a test PR adding
`resources/skins/foo`.

**Phase 4 — Storefront rewrite with contextual neutralization.** Area by
area, each area a PR: (a) layout and chrome, (b) home, (c) catalogue and
search, (d) product and print configurator, (e) cart and checkout, (f) auth
and account, (g) mail, (h) PDF, errors, cookie consent. In each area:
UIkit → Tailwind/components, identity → `brand.php`, copy → lang, colours →
semantic tokens, stacks and slots exposed. Behaviour unchanged.
Stop per area: no UIkit class left in the area; no client string left
(`grep` for the known client identifiers returns nothing); `skin-check demo`
passes; the area works on demo data with and without the demo skin; the
area's templates meet the lab targets in §12 on mobile; the SEO checklist in
§12 passes for every indexable page in the area.

**Phase 5 — Provider contracts.** Brevo direct calls behind
`NewsletterProvider`; `SearchEngine`, `CaptchaProvider`, `AIEnrichmentProvider`
wrapped where not already; selection in `config/mercatura.php`; Postmark
transport configured alongside Brevo.
Stop: no SDK call outside `app/Drivers/**` (the namespace chosen for the
drivers); switching provider is a config change with no code change.

**Phase 6 — Demo data and deploy.** `DemoSeeder` (categories, products with
variants, print techniques, price rules, a few orders and customers), README,
deploy templates, demo instance live.
Stop: fresh clone + `migrate --seed` produces a browsable, neutral shop.
Development moves to Cursor from here.

**Then:** the first installation (clone, remotes, contract CI, skin, redirect
map loaded into `legacy_redirects` with `mercatura:redirects-import`),
go-live, the second installation, v2b.

## 10. Path to the Composer package

When the core stabilises, `mercatura` becomes `mercatura-core`, a package with
a service provider registering views under the `mercatura` namespace. Because
skins already use stable view names and never address the core by path, the
migration for an installation is:

- `resources/skins/<name>/**` → `resources/views/vendor/mercatura/**`
  (Laravel's native package-view override);
- `brand.php` → published `config/brand.php`;
- skin service provider → the customer project's own provider.

View names, component names, lang keys and stack names do not change. This is
why they are the API (CLAUDE.md, rule 3).

## 11. Open decisions

- ~~Skin lang precedence~~: decided in Phase 3 — the skin `lang/` directory
  is added as an extra `FileLoader` path; Laravel merges paths in order with
  `array_replace_recursive`, so skin keys override and missing keys fall
  back to the core. No JSON override.
- Whether skins may ship a Vite entry or only static assets; default to
  static in Phase 3, revisit at the first installation.
- ~~`NewsletterProvider` interface shape~~: decided in Phase 5 — subscribe /
  unsubscribe / syncContact, nothing more until a feature needs it.
- Feature-flag mechanism: plain config booleans in `config/mercatura.php`
  until a second installation shows the need for anything richer.

## 12. Performance and SEO requirements

Core Web Vitals on mobile and organic search visibility are primary drivers
of the storefront rewrite, on a par with the neutralization. They are
acceptance criteria of Phase 4, not a later optimisation pass.

### Measurement

Google evaluates Core Web Vitals on field data (CrUX, 28-day window, per URL
group). A new origin starts with no field data, and field data does not
transfer between domains. During development the reference is therefore lab
data: Lighthouse / PageSpeed Insights, mobile profile, simulated slow 4G,
against demo data with and without the demo skin. Lab metrics are proxies:
TBT stands in for INP and is necessary, not sufficient.

`docs/PERF_BASELINE.md` records, for home, category listing, product page,
cart and checkout: LCP, CLS, TBT, total transfer, CSS/JS bytes, request
count, number of DB queries, TTFB — first for the pre-rewrite storefront
(Phase 2), then per area as Phase 4 closes it. After go-live of an
installation, Search Console field data is added to the installation's own
records once available.

### Lab targets per template (mobile)

| Metric | Target | Hard limit |
|---|---|---|
| LCP | ≤ 2.0 s | 2.5 s |
| CLS | ≤ 0.05 | 0.1 |
| TBT | ≤ 150 ms | 200 ms |
| CSS transferred | ≤ 40 KB | — |
| JS transferred (excluding configurator) | ≤ 120 KB | — |
| DB queries per page | ≤ 30, no N+1 | — |

Targets are for the core default on demo data; a skin may add weight, and
the installation is responsible for staying within the hard limits.

### Design rules

Detailed in `CLAUDE.md` ("Web Vitals rules"). In summary: first paint is
server-rendered; Livewire for interaction, Alpine for local state; every
image sized, responsive and lazy below the fold; fonts self-hosted with
metric-matched fallbacks; nothing shifts layout after paint; third-party
scripts deferred and consent-gated; a response cache for catalogue pages
(category, product, CMS) with catalogue-driven invalidation is a core
feature scheduled immediately after Phase 6, since TTFB is part of LCP.

### SEO

At parity with the platform being replaced, then better. The core owns:

- URL policy in one middleware (trailing slash, case, canonical host) and
  the `legacy_redirects` mechanism (301, no chains); slugs on models with
  history so an old slug redirects to the current one.
- Per-entity SEO fields on products, categories, CMS pages and brands:
  `seo_title`, `seo_description`, `canonical_url`, `noindex`, `og_title`,
  `og_description`, `og_image` (since v2b.0, `HasSeoFields`); image `alt` on
  media. Rendered by `frontend.components.seo.meta`; admin UI in v2b.4.
  `legacy_redirects` (table, `LegacyRedirect` model, served by the exception
  handler for unknown paths, recorded automatically on slug changes) exists
  since v2b.0.
- Rendering: `<title>` and description with generated fallbacks, single
  `<h1>`, canonical on every indexable page, `noindex` on account, cart,
  checkout, search results and filtered listings (filtered listings
  canonicalise to the base listing unless configured otherwise).
- Structured data as JSON-LD from components: `Product`/`Offer` (price,
  availability, currency from config), `BreadcrumbList`, `Organization`
  (from `config/brand.php`), `WebSite`. Covered by tests that validate the
  emitted JSON.
- `sitemap.xml` (products, categories, pages, images; split when large) and
  `robots.txt` generated by the core, with per-installation additions via
  configuration, not overrides.
- Crawlable pagination and filters: real links exist for every listing
  state a crawler should reach; Livewire enhances, never replaces, them.
- Multilingual (when `spatie/laravel-translatable` is active): one URL per
  locale, `hreflang` reciprocal links, locale-specific sitemaps.

Per-area SEO checklist for the Phase 4 stop criterion: title and description
present and non-duplicated across demo pages; one `h1`; canonical correct;
structured data valid; images with `alt`; no indexable page returning
non-200; sitemap includes the area's entities.

## 13. Supplier connectors

A connector is the code that turns one supplier's feeds into the normalized
layer. The normalized layer (`normalized_products`, `normalized_products_variants`
with colours, images, prices and future stocks, `customizations*`,
`normalized_rules_*`) is the contract: the core knows nothing before it and
everything after it (`app:ProcessNormalizedProductData` → products, variants,
prices, attributes, media, categories).

- **Packaging.** Each connector is a Composer package (`nereauweb/mercatura-connector-<key>`,
  namespace `Mercatura\Connectors\<Name>`), private, never in the core
  repository and never required by the core `composer.json`. An
  installation requires it with Composer or checks it out under
  `connectors/<key>/` (git-ignored), which `MercaturaServiceProvider`
  discovers at boot from the package's `composer.json` (PSR-4 autoload and
  Laravel providers). Both paths give the same result.
- **Contract.** `App\Contracts\ImportConnector`, implemented by extending
  `App\Support\Connectors\BaseConnector` (neutral defaults): `key()`,
  `label()`, `sourceAliases()` (legacy spellings of the source in existing
  data), `commands($stage)` for the download / products / customizations stages,
  and the runtime hooks the storefront and the pricing need:
  `processingDays()`, `variantDimensions()`, `markupPercent()` and
  `appliesMarkupToSingleTier()` (through `MarkupRules`), `customizationPipelines()`
  (through `CustomizationPipeline`), `shouldDeactivateVariant()`, `rawProductData()`
  / `rawVariantData()`, `catalogFilterLabel()`, `adminPlugin()`.
- **Registration.** The package's service provider merges its config, loads
  its migrations (raw tables), translations, views and commands, and
  registers the connector on `App\Support\ImportConnectors` with
  `$this->app->resolving(ImportConnectors::class, ...)`. Commands extend
  `App\Support\Connectors\ConnectorCommand` (import log rows, pricing
  helpers, connector guard) and set `$connector` so they refuse to run while
  the flag is off.
- **Switching on.** The package's own `connector-<key>.enabled` (env
  `MERCATURA_CONNECTOR_<KEY>=true`), overridable by
  `mercatura.features.connectors.<key>`; the core config names no supplier.
  A registered but disabled connector is
  skipped by the import jobs, its commands refuse to run, its admin pages
  and storefront filter do not appear.
- **Source values.** New connectors write their `key()` in every `source`
  column. Existing data keeps the spellings the legacy import used; the
  registry matches sources case-insensitively against key, label and
  aliases, so no data migration is required.
- **Admin.** Generic screens stay in the core (import runs and log, category
  aliases, markup bands and quantity tiers under Sistema → Regole di prezzo);
  connector-specific pages come with the
  package as a Filament plugin returned by `adminPlugin()`.
- **Schema.** Raw supplier tables are created by the package migrations
  (guarded with `hasTable` for installations migrated from the monolith);
  the core schema dump holds no raw table. The normalized `source` columns
  are strings, not enums.

Reference implementations: the private packages of the two suppliers of
the originating installation; a third package is a skeleton (key and switch
only) for a supplier of a future installation. A new supplier is a new
package following the same layout; the core does not change.

The same packaging serves installation extensions that are not connectors
(for instance a feed for a legacy platform): a private package discovered
or required the same way, listening to `App\Events\ImportStageCompleted`
for post-import work. The core runs nothing after an import.
