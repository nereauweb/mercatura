# Mercatura

Neutral B2B/B2C e-commerce core on Laravel, maintained by NereauWeb.
Licensed under the GNU Affero General Public License v3.0 (see `LICENSE`).

Customer installations clone this repository, add a skin under
`resources/skins/<name>/` and configuration, and nothing else. The contract
that makes this possible is in `docs/ARCHITECTURE.md`; the stack decisions
are in `docs/01_STACK_SPECIFICATION.md`. Both must be read before changing
anything here.

## Status

v2a in progress. See `docs/ARCHITECTURE.md §9` for the phases and their
stop criteria. Phases 1–6 are done: upgraded framework, Tailwind/Livewire
storefront, skin overlay, provider contracts, demo data.

## Quick start (demo shop)

```
composer install
cp .env.example .env && php artisan key:generate   # set DB_* first
php artisan migrate --seed
php artisan storage:link
npm install && npm run build
php artisan serve
```

`migrate --seed` runs `CoreSeeder` (roles, attributes, sizes, markup bands, quantity breaks)
and `DemoSeeder`: a neutral catalogue of 24 products with pictures generated
at seed time, printing options, CMS pages, home slides, a blog, customers
with orders and quotations. No external account is needed: the defaults use
the `collection` search driver, the `log`/`array` mailers and the null
newsletter driver; set `MERCATURA_CAPTCHA_PROVIDER=null` to submit forms
without reCAPTCHA keys. `MERCATURA_SKIN=demo` shows the skin overlay.

Demo logins (password `password`): `admin@example.com` (admin),
`cliente.azienda@example.com`, `cliente.privato@example.com`,
`cliente.pa@example.com`. The admin area is the legacy one (v2b rewrites it).

Installations run `php artisan db:seed --class=CoreSeeder` instead of the
demo, then load their own data. Deploy templates are in `deploy/`.

## Requirements

- PHP 8.4, Composer 2 (Laravel 13)
- MariaDB 10.11
- Node 22, npm
- Redis (optional in development)

## Skins

`MERCATURA_SKIN=<name>` activates `resources/skins/<name>/`, whose files
shadow the core storefront views, lang files and `config/brand.php`. The
`demo` skin is the reference: `php artisan mercatura:skin-check demo` lists
what it overrides and fails on anything the core does not have;
`php artisan mercatura:skin-override frontend.pages.home` copies a core view
into the active skin with its version header.

## Providers

Every third-party integration sits behind a contract in `app/Contracts`,
implemented by drivers under `app/Drivers` and selected in
`config/mercatura.php` (`.env` names in `.env.example`):

| `.env` | Values |
|---|---|
| `MERCATURA_SEARCH_PROVIDER` | any Scout engine: `collection` (default, no service), `algolia`, `meilisearch`, `typesense`, `database`, `null` |
| `MERCATURA_CAPTCHA_PROVIDER` | `recaptcha` (default), `null` |
| `MAIL_PROVIDER` | `brevo`, `mandrill` (hosted templates) or a mailer from `config/mail.php` (`postmark`, `smtp`, `log`, `array`, `default`) rendering the `mail.*` Blade views |
| `NEWSLETTER_PROVIDER` | `brevo`, `mailchimp`, `null` (default) |
| `MERCATURA_PAYMENT_METHODS` | `bank_transfer,stripe,paypal`; gateways in `config/mercatura.php` |
| `MERCATURA_VAT_RATE`, `MERCATURA_DELIVERY_COST`, `MERCATURA_FREE_DELIVERY_FROM`, `MERCATURA_UNDER_MINIMUM_SURCHARGE` | pricing constants (`config/mercatura.php` pricing) |
| `MERCATURA_CUSTOMIZATIONS_CLEANUP_DAYS` | days without an import touch after which `cleanup:customizations` deletes a customization (default 90) |
| `MERCATURA_CONNECTOR_<KEY>` | one switch per installed supplier connector package, off by default |
| `MERCATURA_REPORTS_ACCESS` | user reports to the admin: `off`, `auth` (default, logged-in users), `public` (guests too, needs a working captcha) |
| `MERCATURA_MESSAGE_FORWARDER` | `null` (default) or the name an installation package binds to forward reports to a helpdesk |
| `MERCATURA_REPORTS_ATTACHMENT_RETENTION_DAYS` | days after which `cleanup:report-attachments` deletes report attachments and captures (default 90, `0` keeps them) |

Switching a provider never needs a code change; adding one means a new
driver class and a case in `App\Providers\DriverServiceProvider`.

## User reports

Users report a problem from the page where they see it (account area,
"Segnala un problema" in the footer); the report reaches the admin in
*Vendite → Messaggi* with the page, the area, the browser, recent JavaScript
errors and up to five attachments, optionally a screen capture taken with
the user's consent (desktop browsers). Attachments are re-encoded, kept on a
private disk, downloaded only from the panel and deleted after the retention
period. An installation may add a forwarder (`App\Contracts\MessageForwarder`)
so that admins forward a report to their helpdesk, pseudonymised, and send
their own reports there. Before going live, review the consent and notice
texts in `lang/it/frontend.php` (`reports.privacy`, `reports.capture.consent`;
bump `mercatura.reports.screenshot_consent_version` when the consent text
changes) and mention reports in the privacy policy. Details:
`docs/06_USER_REPORTS.md`.

## Supplier connectors

Supplier imports are separate private packages (`nereauweb/mercatura-connector-<key>`),
discovered from `connectors/<key>/` or installed with Composer, and switched
on per installation with `MERCATURA_CONNECTOR_<KEY>=true`
(`docs/ARCHITECTURE.md §13`). The core ships the normalized layer, the
generic processing, the import jobs and the admin screens for runs, logs and
category aliases; each package brings its commands, raw tables, config and
its own admin page. The demo shop needs none.

## Admin

The admin panel is Filament 4 at `/admin` (`app/Filament`, provider
`app/Providers/Filament/AdminPanelProvider.php`, copy in `lang/it/admin.php`);
users with the `admin` role can enter, and each area is gated by a
permission seeded by `CoreSeeder`. It replaced the legacy admin in v2b
(`docs/02_V2B_ADMIN.md`). Filament's assets are published by
`composer install` (`filament:upgrade`) and are not committed.

## Development

```
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
npm install && npm run build
composer pint && composer larastan && composer test
php artisan mercatura:skin-check demo
```

Tests need a migrated `mercatura_test` database (`DB_DATABASE=mercatura_test
php artisan migrate`); `tests/Feature/DemoSeederTest.php` seeds the demo
inside a transaction and checks the stop criterion of Phase 6.

Copyright (c) 2026 NereauWeb di Andrea Porcheddu.
