# CLAUDE.md — Mercatura core

Read `docs/ARCHITECTURE.md` before touching anything. It defines the
core/installation contract that every change in this repository must respect.
Package-level stack decisions live in `docs/01_STACK_SPECIFICATION.md`; do not
re-decide them here.

## What this repository is

Mercatura is a Laravel B2B/B2C e-commerce core, AGPL-3.0, maintained by
NereauWeb. It was bootstrapped from a production codebase and is
being turned into a **neutral, brand-free core** that customer installations
extend without modifying it.

This repository never contains a customer. No client name, logo, contact,
IBAN, copy, colour or feature flag default that identifies a real installation
may exist here. If you find one, extract it (see "Neutralization rules") — do
not leave it and do not comment it out.

## Current phase: v2a

We are in **v2a**: framework upgrade, storefront rewrite to Tailwind 4 +
Livewire 4, neutralization, skin overlay mechanism, demo seeders: all done.
**v2b** (Filament admin) is done up to v2b.6: the admin is the Filament panel
under `app/Filament` (`docs/02_V2B_ADMIN.md`); the legacy admin no longer
exists. Admin copy lives in `lang/it/admin.php`; every resource action is
gated by a permission (`CoreSeeder`); catalogue side effects live in
`app/Actions`, never in a resource class.

Work proceeds in the numbered phases of `ARCHITECTURE.md §9`. Each phase has a
stop criterion. When the criterion is met, stop and report; do not start the
next phase in the same session unless asked.

## Non-negotiable rules

1. **No installation-specific content in this repo.** Identity → `config/brand.php`
   defaults (neutral placeholders). Copy → lang keys (`lang/it/`). Palette → semantic tokens.
2. **No skins other than `resources/skins/demo`.** The demo skin exists to
   prove the mechanism and to be copied by installations. It overrides a
   handful of files and nothing else.
3. **View names are the API.** `frontend.pages.home`, `frontend.public.layout`,
   `frontend.elements.product-card`, `livewire.frontend-*`, `mail.*`,
   `frontend.pdf.*` — renaming a view is a breaking change for every
   installation. Add views freely; rename only with a deliberate, documented
   decision.
4. **All migrations live here.** Installations never carry migrations. If a
   feature needs schema, it is a core feature.
5. **Parity first.** During framework upgrade and template conversion, do not
   change behaviour: no new validation, no reworked checkout flow, no
   "improved" pricing logic. Functional changes are separate commits in
   separate phases, or separate PRs.
6. **Stack constraints.** No UIkit, Bootstrap or jQuery in new code. Tailwind 4
   (CSS-first `@theme`), Livewire 4, Alpine 3, Vite 6. Legacy UIkit markup is
   removed area by area, never left side by side with Tailwind in the same
   template once that template has been converted.
7. **Supplier code lives in connector packages, never in the core** (ARCHITECTURE §13):
   the core stops at the normalized layer. A supplier name in `app/` is a
   defect; supplier rules reach the storefront only through `ImportConnector` hooks.
8. **Provider-specific code goes behind a contract or a flag.** Direct SDK calls
   (Brevo, Algolia, Stripe, PayPal, reCAPTCHA, Anthropic) live only in the
   driver under `app/Drivers/**` that implements the corresponding contract in
   `app/Contracts`, bound by `DriverServiceProvider` from
   `config/mercatura.php`. Controllers and views never call an SDK
   (`tests/Feature/DriverIsolationTest.php` enforces it).
9. **Nothing that would break the contract in `ARCHITECTURE.md §2`** may be
   merged, even temporarily.

## Conventions

- Code, comments, commit messages, docs: **English**. User-facing copy:
  **Italian**, in lang files, never inline in Blade.
- Commits: conventional commits (`feat:`, `fix:`, `refactor:`, `chore:`,
  `upgrade:`). Small, single-purpose, parity-preserving where the phase
  requires it.
- Before proposing a commit: `composer pint`, `composer larastan`,
  `composer test`, and `php artisan mercatura:skin-check demo` once the
  overlay mechanism exists. All must pass.
- Blade components are the unit of override. Prefer small anonymous
  components composed by pages over long page templates. Expose named slots
  and `@stack` points where an installation would plausibly want to inject
  content without copying the file.
- Semantic Tailwind tokens only in shared markup (`bg-primary`,
  `text-accent`, `border-muted`…). Never a colour name, never a client name.
- New PHP: `declare(strict_types=1)`, typed properties and returns, no
  `mixed` where a real type exists.

## Web Vitals rules

Core Web Vitals (LCP, INP, CLS) on mobile are a primary reason this rewrite
exists. See `ARCHITECTURE.md §12` for targets. In every storefront template:

- **Above-the-fold content is server-rendered in the first response.** No
  lazy Livewire component may own the hero, the first row of products, the
  product gallery or the price. Livewire handles interaction, not first paint.
- **Local interactions stay in Alpine.** Menus, tabs, quantity steppers,
  filter toggles before "apply", gallery thumbnails: no server round-trip.
  Livewire requests always show immediate visual state (`wire:loading`,
  disabled controls) and re-render the smallest component that changed —
  never the whole listing for one checkbox.
- **Every `<img>` has `width` and `height` (or a fixed `aspect-ratio`)**,
  uses `srcset`/`sizes`, modern formats via the image pipeline, `loading="lazy"`
  below the fold and `fetchpriority="high"` on the LCP image only.
- **Fonts are self-hosted**, subsetted, `font-display: swap`, with
  `size-adjust`/metric overrides on the fallback. No third-party font CSS.
- **Nothing pushes layout after paint.** Cookie banner and notices overlay;
  Livewire placeholders reserve the height of what replaces them; no
  late-injected banners above content.
- **No render-blocking third-party scripts in `head`.** Analytics, consent,
  chat widgets load deferred and only after consent where required.
- Long tasks are defects: no synchronous work over ~50 ms on interaction;
  configurator work is chunked or off the main thread.

## SEO rules

SEO is a first-class concern of the storefront, at parity with the platform
being replaced and better where the rewrite allows.

- **URLs are stable and canonical.** Slugs come from the model, one URL per
  entity, trailing-slash and case policy applied in one place (middleware),
  `rel="canonical"` on every indexable page, self-referencing by default.
  Filtered/sorted/paginated listings canonicalise to the base listing unless
  a deliberate decision says otherwise.
- **Every indexable page has a `<title>` and meta description** from model
  fields (`meta_title`, `meta_description`) with sensible generated fallbacks;
  one `<h1>` per page; heading hierarchy respected in components.
- **Structured data** (JSON-LD): `Product` with `Offer`, `BreadcrumbList`,
  `Organization` from `config/brand.php`, `WebSite`. Emitted by components,
  validated in tests.
- **Sitemap and robots** are generated by the core (products, categories,
  CMS pages, images), with `noindex` for account, cart, checkout, search
  results and internal listings. Redirect handling stays in the
  `legacy_redirects` mechanism: 301, never chains, never 302 for permanent
  moves.
- **Images carry meaningful `alt`** from model fields with fallbacks; product
  image URLs are stable.
- **Pagination** uses real links (`<a href>`), not only Livewire actions, so
  crawlers can traverse listings; `rel="next"/"prev"` optional, crawlable
  links mandatory.
- **SEO fields are stored, rendered and editable.** `seo_title`,
  `seo_description`, `canonical_url`, `noindex`, `og_*` on products,
  categories, pages, brands and articles (`HasSeoFields`), image `alt` on
  media; the storefront reads them through `frontend.components.seo.meta`
  and the panel edits them in the SEO tab of each resource.

## What to ask and what to assume

Ask before: renaming a view; changing a route name or URL; adding a
dependency; changing a migration that already exists; removing or renaming
an admin permission.

Assume and state the assumption for: where a lang key goes; which component
a chunk of markup becomes; naming of semantic tokens; ordering inside a phase.

When existing code is ambiguous (two ways of doing the same thing), pick the
one used in the most recently written area of the codebase, note it in the
commit message, and move on. Do not refactor the other one unless the phase
is about that.

## Useful commands

```
php artisan mercatura:skin-check {skin}      # lists overridable views; fails if the skin overrides a view that does not exist in core
php artisan mercatura:skin-override {view}   # copies one view into resources/skins/{MERCATURA_SKIN}/ (installation tooling, works here for demo)
php artisan migrate --seed                   # CoreSeeder + DemoSeeder: neutral demo shop
php artisan db:seed --class=CoreSeeder       # roles, attributes, sizes, markup bands (installations)
```

## Repository layout (the parts that matter for the contract)

```
config/brand.php            neutral identity defaults, overridden by skins
config/mercatura.php        skin selection, feature flags, provider selection
app/Contracts/              provider contracts (search, captcha, newsletter, mail, payment, AI, personalization, import)
app/Drivers/                the only place that calls a provider SDK
resources/views/frontend/   the default storefront (this IS the parent theme)
resources/views/livewire/   frontend-* views are part of the storefront surface
resources/views/mail/       overridable
lang/it/                    default copy, overridable per skin
resources/skins/demo/       the only skin allowed here
public/skins/demo/          its assets
database/seeders/Demo*      neutral demo data
docs/ARCHITECTURE.md        the contract
docs/01_STACK_SPECIFICATION.md
app/Filament/              the admin panel (resources, pages, widgets); app/Actions holds its side effects
app/Support/Connectors/    the connector framework (ARCHITECTURE §13); connector packages live outside the repo
connectors/                git-ignored checkout of private connector packages (development, or deploy by directory)
docs/02_V2B_ADMIN.md        v2b (Filament admin) analysis, plan and deviations
docs/03_CUSTOMIZATIONS.md   v2c (printing → customizations) analysis and plan, awaiting approval
```
