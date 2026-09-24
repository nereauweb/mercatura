# Deploy templates

Starting points for a Mercatura installation on a plain Linux host (PHP 8.4
FPM, nginx or Apache 2.4, MariaDB 10.11, a queue worker). Copy, rename and fill in the
placeholders; nothing here is required by the code.

| File | Purpose |
|---|---|
| `nginx.conf.example` | Server block: `public/` as root, PHP-FPM, static asset caching |
| `apache.conf.example` | The same for Apache 2.4 with php-fpm (`public/.htaccess` does the rewriting) |
| `supervisor-queue.conf.example` | Queue worker kept alive by supervisor |
| `crontab.example` | Laravel scheduler |
| `deploy.sh` | Release steps to run after `git pull` (or from CI) |

Checklist for a new installation:

1. `cp .env.example .env`, set `APP_URL`, `APP_KEY` (`php artisan key:generate` **after** `composer install`),
   database, `MERCATURA_SKIN`, providers and their credentials (see README
   "Providers"). `MAIL_PROVIDER=log` and `NEWSLETTER_PROVIDER=null` are safe
   until the accounts exist. Always invoke `php8.4` if unversioned `php` is older.
2. `php artisan migrate --force` then `php artisan db:seed --class=CoreSeeder`
   (roles, attributes, sizes, markup bands, quantity breaks). Run `DemoSeeder` only on a demo
   instance. Seed **before** the first supplier import: empty `normalized_tiers_rules`
   makes the catalog write a single quantity-1 price (usually at cost). CoreSeeder is
   insert-if-empty, so later admin edits of Sistema → Regole di prezzo are kept.
3. `php artisan storage:link`, then create the first admin user and give it the
   `admin` role (tinker: `User::create([...])->assignRole('admin')`).
4. Build assets (`npm ci && npm run build`) on the host or in CI and ship
   `public/build`. Filament's own assets are published by `composer install`
   (`filament:upgrade` post-autoload script) into `public/{css,js,fonts}/filament`.
   Vite 8 needs **Node 22**. Debian's `node` is often 18: install 22 beside it
   (e.g. `/opt/node22`) and put it on `PATH` only for this build; do not replace
   system Node on a host that still builds other sites with 18.
5. A test or staging instance: `MERCATURA_STAGING=true` (noindex header and meta on every page, `robots.txt`
   disallowing everything) and, to keep visitors out too, `MERCATURA_STAGING_USER` / `MERCATURA_STAGING_PASSWORD`
   (HTTP basic auth in front of the whole site; `/up`, the Stripe webhook and the payment returns stay open).
   noindex is not a lock: without user/password the pages stay reachable.
6. If the search provider needs an index: `php artisan scout:import "App\Models\Product"`.
7. Webhooks: Stripe → `POST /stripe/webhook` (Cashier, `STRIPE_WEBHOOK_SECRET`).
8. Supplier connectors: require the private packages with Composer (or check
   them out under `connectors/`), set `MERCATURA_CONNECTOR_<KEY>=true` and
   their credentials, run `php artisan migrate` again for their raw tables.
   Composer: project-local `github-oauth` (classic PAT, `repo` scope). Do not
   `composer config --global github-oauth` on a shared host. Fine-grained tokens
   401 the public GitHub zipballs in `composer.lock`. An invalid token is worse
   than none. After `chown www-data`, git as root needs
   `git config --global --add safe.directory /path/to/app`.

Apache notes (`apache.conf.example`): the site file must be named `*.conf` and
`a2ensite`'d; Debian only includes `sites-enabled/*.conf`. PHP 8.4 via
`SetHandler` **in the vhost**. On a host that also runs php8.2-fpm, do not
`a2enconf php8.4-fpm` globally and do not switch the MPM / disable mod_php.

First import: seed CoreSeeder first; run `app:import` as `www-data` in
tmux/nohup (it is synchronous and lasts hours). If download + normalize
finished but catalog processing crashed, resume with
`php artisan app:ProcessNormalizedProductData` (no re-download). After changing
quantity tiers, re-run the supplier `normalize:*` commands then
`app:ProcessNormalizedProductData` — filling `normalized_tiers_rules` does not
rewrite prices already written. Review both markup bands and quantity tiers
under Sistema → Regole di prezzo. Import images are JPEG originals (~1600px),
not WebP. Keep `QUEUE_RETRY_AFTER` above the longest import (7500 in `.env.example`).
