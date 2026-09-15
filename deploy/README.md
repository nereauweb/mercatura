# Deploy templates

Starting points for a Mercatura installation on a plain Linux host (PHP 8.4
FPM, nginx, MariaDB 10.11, a queue worker). Copy, rename and fill in the
placeholders; nothing here is required by the code.

| File | Purpose |
|---|---|
| `nginx.conf.example` | Server block: `public/` as root, PHP-FPM, static asset caching |
| `supervisor-queue.conf.example` | Queue worker kept alive by supervisor |
| `crontab.example` | Laravel scheduler |
| `deploy.sh` | Release steps to run after `git pull` (or from CI) |

Checklist for a new installation:

1. `cp .env.example .env`, set `APP_URL`, `APP_KEY` (`php artisan key:generate`),
   database, `MERCATURA_SKIN`, providers and their credentials (see README
   "Providers"). `MAIL_PROVIDER=log` and `NEWSLETTER_PROVIDER=null` are safe
   until the accounts exist.
2. `php artisan migrate --force` then `php artisan db:seed --class=CoreSeeder`
   (roles, attributes, sizes, markup bands). Run `DemoSeeder` only on a demo
   instance.
3. `php artisan storage:link`, then create the first admin user and give it the
   `admin` role (tinker: `User::create([...])->assignRole('admin')`).
4. Build assets (`npm ci && npm run build`) on the host or in CI and ship
   `public/build`. Filament's own assets are published by `composer install`
   (`filament:upgrade` post-autoload script) into `public/{css,js,fonts}/filament`.
5. If the search provider needs an index: `php artisan scout:import "App\Models\Product"`.
6. Webhooks: Stripe → `POST /stripe/webhook` (Cashier, `STRIPE_WEBHOOK_SECRET`).
7. Supplier connectors: require the private packages with Composer (or check
   them out under `connectors/`), set `MERCATURA_CONNECTOR_<KEY>=true` and
   their credentials, run `php artisan migrate` again for their raw tables.
