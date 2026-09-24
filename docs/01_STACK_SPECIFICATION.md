# Mercatura — Stack specification

Status: **v1.0, approved 2026-09-11.** Written from `ARCHITECTURE.md §6` and
from the state of the imported tree. Items marked *(inherited)* were
already in the imported `composer.json`; items marked *(§6)* are fixed by the
architecture document; items marked *(proposed)* were decided here and
approved. This file is the single place where package-level decisions live;
`CLAUDE.md` forbids re-deciding them elsewhere.

Decisions here are not frozen: a change is welcome when it is necessary or
brings a clear advantage, and it is made by editing this file in the same
PR that introduces it, with the reason recorded in §7.

## 1. Runtime

| Component | Target | Notes |
|---|---|---|
| PHP | 8.4 *(§6)* | `composer.json` requires `^8.4` since Phase 2. 8.5 is not a target. |
| Framework | Laravel 13 *(§6)* | Upgraded 10 → 11 → 12 → 13 in Phase 2 (2026-09-11). |
| Database | MariaDB 10.11 *(§6)* | Production dumps use HASH unique indexes; schema baseline must reproduce them. |
| Cache / queue / session | Redis *(§6)* | `file`/`sync` allowed in local development only. |
| Node | 22 LTS *(proposed)* | Build only; no Node at runtime. Toolchain: Vite 8, Tailwind 4 via `@tailwindcss/vite`. |
| PDF rendering | Chromium via `spatie/browsershot` *(inherited)* | Puppeteer, configured in `.puppeteerrc.cjs`. Works, but the setup is heavy (Node + Chromium on the server); see §7. |

## 2. Frontend

| Concern | Decision |
|---|---|
| Templates | Blade; anonymous components are the unit of override *(§6)* |
| Interactivity | Livewire 4 (server), Alpine 3 (local state) *(§6)*. Alpine is the instance bundled with Livewire (`livewire.esm`), never a second copy. |
| CSS | Tailwind 4, CSS-first `@theme`, semantic tokens only in shared markup *(§6)* |
| Bundler | Vite 8 with `laravel-vite-plugin` 3 *(§6 said Vite 6; the plugin's current major requires Vite 8, adopted in Phase 4a)* |
| Forbidden | UIkit, Bootstrap, jQuery in new code *(CLAUDE.md)*. Legacy UIkit remains only in the frozen admin and in storefront areas not yet converted. |
| Fonts | Self-hosted, subsetted, `font-display: swap`, metric-matched fallbacks *(CLAUDE.md)* |
| Images | `spatie/laravel-medialibrary` 11 *(inherited, major bump in Phase 2)*; named WebP conversions on `ProductVariant` (`thumb` 320, `web` 800, `large` 1600) and the same `web`/`large` sidecars for home slides on the public disk (not Spatie — slides store a bare filename). Originals kept as JPEG/PNG. Not Spatie `withResponsiveImages()` |

## 3. Packages

### Kept from the originating installation, bumped only as the upgrade requires

| Package | Role |
|---|---|
| `laravel/scout` 11 *(§6)* | Search abstraction |
| `algolia/algoliasearch-client-php` 4 *(§6)* | Algolia driver; v3 → v4 in Phase 2 if the upgrade forces it, otherwise Phase 5 |
| `laravel/cashier` 16 *(§6)* | Stripe payments |
| `srmklive/paypal` *(inherited)* | PayPal payments |
| `symfony/postmark-mailer` *(added, Phase 5)* | Postmark transport for the Blade mail driver |
| `laravel/sanctum` *(inherited)* | API tokens |
| `spatie/laravel-medialibrary` *(inherited)* | Media |
| `spatie/laravel-permission` *(inherited)* | Roles and permissions (admin) |
| `spatie/laravel-sitemap` *(inherited)* | Sitemap generation |
| `spatie/laravel-pdf` + `spatie/browsershot` *(inherited)* | PDF quotes and order documents |
| `spatie/laravel-cookie-consent` *(inherited)* | Present; the originating installation uses a custom banner. Decide in Phase 4(h) whether to drop it. |
| `maatwebsite/excel` *(inherited)* | Admin exports and supplier price-table imports |
| `saloonphp/xml-wrangler` *(inherited)* | Supplier XML feeds |
| `enshrined/svg-sanitize` *(inherited)* | Uploaded SVG sanitisation |
| `filament/filament` 5 + `filament/spatie-laravel-media-library-plugin` *(v2b)* | Admin panel; replaced the legacy admin and rappasoft tables in v2b.6; Filament 5 / Livewire 4 since v2b.7 |
| `timehunter/laravel-google-recaptcha-v3` *(inherited)* | reCAPTCHA v3 driver behind `CaptchaProvider` |

### Mail and newsletter

Symfony Mailer with **Brevo** and **Postmark** transports *(§6)*.
The originating installation also carries Mandrill (transactional) and Mailchimp Marketing
(newsletter) drivers behind its own `TransactionalMailer` and
`NewsletterSubscriber` contracts, selected by `MAIL_PROVIDER` /
`NEWSLETTER_PROVIDER`.

*(decided, Phase 5)* The Mandrill and Mailchimp drivers stay in the core as
configuration-selectable providers (the contract in `ARCHITECTURE.md §2`
says all selectable providers live in the core `composer.json`).
`symfony/postmark-mailer` is added so `MAIL_PROVIDER=postmark` works out of
the box. Transactional mail has two kinds of driver: hosted templates
(`brevo`, `mandrill`, template ids from `.env`) and the Blade driver, used
for every other value of `MAIL_PROVIDER` (`postmark`, `smtp`, `log`,
`array`, `default`…): it renders the `mail.*` views with copy from
`lang/it/mail.php` and sends through the Laravel mailer of that name, so the
demo instance and tests need no mail account. `NewsletterProvider` is
minimal: subscribe / unsubscribe / syncContact.

### Dropped

| Package | Reason |
|---|---|
| `laravel/sail` *(proposed, done in Phase 2)* | Not used; Docker is not part of the development setup. It also blocked the Laravel 13 resolution. |

### Development

| Package | Role |
|---|---|
| `laravel/pint` | Formatting, Laravel preset. `composer pint` |
| `larastan/larastan` *(proposed)* | Static analysis. Level 5 on new code; the imported tree starts with a generated baseline that is only ever shrunk. `composer larastan` |
| `phpunit/phpunit` 11+ *(inherited, bumped)* | Tests. Pest is not adopted. `composer test` |

## 4. Provider contracts

All provider-specific code lives in drivers under `app/Drivers/**`
(namespace `App\Drivers\<Area>\<Driver>`, decided in Phase 5: kept apart
from `app/Providers`, which holds Laravel service providers) and is reached
only through the interfaces in `app/Contracts`, bound by
`App\Providers\DriverServiceProvider` from `config('mercatura.providers.*')`.
`tests/Feature/DriverIsolationTest.php` fails when an SDK namespace or facade
appears outside `app/Drivers`.

| Contract | v2a drivers | Selection | Notes |
|---|---|---|---|
| `SearchEngine` | `ScoutSearchEngine` (any Scout engine: algolia, meilisearch, typesense, database, collection, null) | `providers.search` → mirrored into `scout.driver` | Index settings stay in `config/scout.php` |
| `CaptchaProvider` | `RecaptchaV3CaptchaProvider`, `NullCaptchaProvider` | `providers.captcha` | Views use `<x-frontend::captcha>` / `<x-frontend::captcha-init>`, controllers `App\Rules\Captcha::rules($action)`; the form field name comes from the driver |
| `NewsletterProvider` | `BrevoNewsletterProvider`, `MailchimpNewsletterProvider`, `NullNewsletterProvider` | `providers.newsletter` | subscribe / unsubscribe / syncContact; `AlreadySubscribedException` |
| `TransactionalMailer` | `BrevoTransactionalMailer`, `MandrillTransactionalMailer`, `BladeTransactionalMailer` (postmark, smtp, log, array, default…) | `providers.mail` | Template keys in `config/mail-templates.php`; Blade views `mail.*` are part of the override surface |
| `PaymentGateway` | `StripePaymentGateway` (Cashier), `PayPalPaymentGateway` | `payments.gateways` (method → class), `checkout.payment_methods` | `start()` returns the redirect URL, `confirm()` a `PaymentConfirmation`; `bank_transfer` has no gateway |
| `AIEnrichmentProvider` | `NullAIEnrichmentProvider` | `providers.ai` (`anthropic` reserved) | Anthropic driver when a feature needs it |
| `PersonalizationProvider` | `NullPersonalizationProvider` | `providers.personalization` | recommendedProductIds / track |
| `ImportConnector` | none in the core; one private package per supplier (`nereauweb/mercatura-connector-<key>`) | `features.connectors.<key>` | `App\Support\ImportConnectors` registry, `BaseConnector`, `ConnectorCommand`, `MarkupRules`, `PrintingPipeline`; see `ARCHITECTURE.md §13` |

## 5. Schema policy

- All migrations live in the core (`CLAUDE.md` rule 4).
- The originating schema was largely created outside its migrations, so
  the **schema dump** (`database/schema/mysql-schema.sql`, regenerated from a
  fresh migrate at the end of every phase that touches the schema) is the
  baseline of a fresh installation: `migrate` loads it and runs nothing
  else. The migration files exist for databases migrated from the
  originating platform: three consolidated, idempotent, forward-only
  migrations (`2026_09_01_*`: catalogue and SEO, pricing rules,
  customizations) take such a database to the current schema; their result
  is verified against the dump on the monolith fixture. A new schema change
  is a new guarded migration that is folded into the dump and, when it
  reworks something a previous migration did, into that migration rather
  than appended after it — the sequence must read as the schema, not as
  its history.
- Migrations are idempotent where they touch pre-existing tables.

## 6. Open decisions carried from ARCHITECTURE.md §11

- Skin lang precedence mechanism — Phase 3.
- Skins with a Vite entry vs static assets only — static in Phase 3.
- Multilingual via `spatie/laravel-translatable` — not installed; add only
  when an installation needs it.

## 7. Revisit list

Decisions that stand today but are explicitly open to replacement when a
better option appears. Each entry names the trigger for revisiting.

| Decision | Why it may change | Trigger |
|---|---|---|
| PDF via `spatie/laravel-pdf` + Browsershot (Chromium) | Output quality is good, but the server setup (Node, Puppeteer, Chromium and its libraries) is complex and fragile on shared hosting. | A pure-PHP or service-based renderer that reproduces the quote and order documents with equal fidelity and simpler deployment. `spatie/laravel-pdf` 2 (installed in Phase 2) is driver-based and ships a DomPdf driver with no external binary and a Cloudflare Browser Rendering driver: switching is a config change, so the evaluation in Phase 4(h) is a rendering-fidelity test, not a package swap. |
| Named WebP conversions (`thumb`/`web`/`large`), no AVIF, no Spatie `withResponsiveImages()` | AVIF compresses better; a width scale would pick the file automatically. The catalogue is too large for a file per width step, and encode time of AVIF is not acceptable during import. | LCP or transfer still over budget after WebP; AVIF encode becomes cheap enough to run on the queue. |
